<?php
$GLOBALS['THRIFT_ROOT'] = dirname(__FILE__) . '/thrift/';
require_once $GLOBALS['THRIFT_ROOT'].'/packages/cassandra/Cassandra.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/TSocket.php';
require_once $GLOBALS['THRIFT_ROOT'].'/protocol/TBinaryProtocol.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/TFramedTransport.php';
require_once $GLOBALS['THRIFT_ROOT'].'/transport/TBufferedTransport.php';

/**
 * The ConnectionPool was unable to open a connection to any of the
 * servers in the provided list.
 * @package phpcassa
 * @subpackage connection
 */
class NoServerAvailable extends Exception { }

/**
 * The Cassanda API version detected on the server is not compatible with
 * this release of phpcassa.
 * @package phpcassa
 * @subpackage connection
 */
class IncompatibleAPIException extends Exception { }

/**
 * An operation was retried up to the specified maximum number of times,
 * but every attempt failed.
 * @package phpcassa
 * @subpackage connection
 */
class MaxRetriesException extends Exception { }

/**
 * @package phpcassa
 * @subpackage connection
 */
class ConnectionWrapper {

    const LOWEST_COMPATIBLE_VERSION = 17;
    const DEFAULT_PORT = 9160;
    public $keyspace;
    public $client;
    public $op_count;

    public function __construct($keyspace,
                                $server,
                                $credentials=null,
                                $framed_transport=True,
                                $send_timeout=null,
                                $recv_timeout=null)
    {
        $this->server = $server;
        $server = explode(':', $server);
        $host = $server[0];
        if(count($server) == 2)
            $port = (int)$server[1];
        else
            $port = self::DEFAULT_PORT;
        $socket = new TSocket($host, $port);

        if($send_timeout) $socket->setSendTimeout($send_timeout);
        if($recv_timeout) $socket->setRecvTimeout($recv_timeout);

        if($framed_transport) {
            $transport = new TFramedTransport($socket, true, true);
        } else {
            $transport = new TBufferedTransport($socket, 1024, 1024);
        }

        $this->client = new CassandraClient(new TBinaryProtocolAccelerated($transport));
        $transport->open();

        $server_version = explode(".", $this->client->describe_version());
        $server_version = $server_version[0];
        if ($server_version < self::LOWEST_COMPATIBLE_VERSION) {
            $ver = self::LOWEST_COMPATIBLE_VERSION;
            throw new IncompatibleAPIException("The server's API version is too ".
                "low to be comptible with phpcassa (server: $server_version, ".
                "lowest compatible version: $ver)");
        }

        $this->set_keyspace($keyspace);

        if ($credentials) {
            $request = new cassandra_AuthenticationRequest(array("credentials" => $credentials));
            $this->client->login($request);
        }

        $this->keyspace = $keyspace;
        $this->transport = $transport;
        $this->op_count = 0;
    }

    public function close() {
        $this->transport->close();
    }

    public function set_keyspace($keyspace) {
        if ($keyspace !== NULL) {
            $this->client->set_keyspace($keyspace);
            $this->keyspace = $keyspace;
        }
    }

}

/**
 * A pool of connections to a set of servers in a cluster.
 * Each ConnectionPool is keyspace specific.
 * @package phpcassa
 * @subpackage connection
 */
class ConnectionPool {

    const BASE_BACKOFF = 0.1;
    const MICROS = 1000000;
    const MAX_RETRIES = 2147483647; // 2^31 - 1

    const DEFAULT_MAX_RETRIES = 5;
    const DEFAULT_RECYCLE = 10000;

    private static $default_servers = array('localhost:9160');

    public $keyspace;
    private $servers;
    private $pool_size;
    private $send_timeout;
    private $recv_timeout;
    private $credentials;
    private $framed_transport;
    private $queue;
    private $keyspace_description = NULL;

    /**
     * int $max_retries how many times an operation should be retried before
     *     throwing a MaxRetriesException. Using 0 disables retries; using -1 causes
     *     unlimited retries. The default is 5.
     */
    public $max_retries = self::DEFAULT_MAX_RETRIES;

    /**
     * int $recycle after this many operations, a connection will be automatically
     *     closed and replaced. Defaults to 10,000.
     */
    public $recycle = self::DEFAULT_RECYCLE;

    /**
     * Constructs a ConnectionPool.
     *
     * @param string $keyspace the keyspace all connections will use
     * @param mixed $servers an array of strings representing the servers to
     *        open connections to.  Each item in the array should be a string
     *        of the form 'host' or 'host:port'.  If a port is not given, 9160
     *        is assumed.  If $servers is NULL, 'localhost:9160' will be used.
     * @param int $pool_size the number of open connections to keep in the pool.
     *        If $pool_size is left as NULL, max(5, count($servers) * 2) will be
     *        used.
     * @param int $max_retries how many times an operation should be retried before
     *        throwing a MaxRetriesException. Using 0 disables retries; using -1 causes
     *        unlimited retries. The default is 5.
     * @param int $send_timeout the socket send timeout in milliseconds. Defaults to 5000.
     * @param int $recv_timeout the socket receive timeout in milliseconds. Defaults to 5000.
     * @param int $recycle after this many operations, a connection will be automatically
     *        closed and replaced. Defaults to 10,000.
     * @param mixed $credentials if using authentication or authorization with Cassandra,
     *        a username and password need to be supplied. This should be in the form
     *        array("username" => username, "password" => password)
     * @param bool $framed_transport whether to use framed transport or buffered transport.
     *        This must match Cassandra's configuration.  In Cassandra 0.7, framed transport
     *        is the default. The default value is true.
     */
    public function __construct($keyspace,
                                $servers=NULL,
                                $pool_size=NULL,
                                $max_retries=self::DEFAULT_MAX_RETRIES,
                                $send_timeout=5000,
                                $recv_timeout=5000,
                                $recycle=self::DEFAULT_RECYCLE,
                                $credentials=NULL,
                                $framed_transport=true)
    {
        $this->keyspace = $keyspace;
        $this->send_timeout = $send_timeout;
        $this->recv_timeout = $recv_timeout;
        $this->recycle = $recycle;
        $this->max_retries = $max_retries;
        $this->credentials = $credentials;
        $this->framed_transport = $framed_transport;

        $this->stats = array(
            'created' => 0,
            'failed' => 0,
            'recycled' => 0);

        if (is_null($servers))
            $servers = self::$default_servers;
        $this->servers = $servers;

        if (is_null($pool_size))
            $this->pool_size = max(count($this->servers) * 2, 5);
        else
            $this->pool_size = $pool_size;

        $this->queue = array();

        // Randomly permute the server list
        shuffle($this->servers);
        $this->list_position = 0;
    }

    private function make_conn() {
        // Keep trying to make a new connection, stopping after we've
        // tried every server twice
        $err = "";
        foreach (range(1, count($this->servers) * 2) as $i)
        {
            try {
                $this->list_position = ($this->list_position + 1) % count($this->servers);
                $new_conn = new ConnectionWrapper($this->keyspace, $this->servers[$this->list_position],
                    $this->credentials, $this->framed_transport, $this->send_timeout, $this->recv_timeout);
                array_push($this->queue, $new_conn);
                $this->stats['created'] += 1;
                return;
            } catch (TException $e) {
                $h = $this->servers[$this->list_position];
                $err = (string)$e;
                error_log("Error connecting to $h: $err", 0);
                $this->stats['failed'] += 1;
            }
        }
        throw new NoServerAvailable("An attempt was made to connect to every server twice, but " .
                                    "all attempts failed. The last error was: $err");
    }

    /**
     * Adds connections to the pool until $pool_size connections
     * are in the pool.
     */
    public function fill() {
        while (count($this->queue) < $this->pool_size)
            $this->make_conn();
    }

    /**
     * Retrieves a connection from the pool.
     *
     * If the pool has fewer than $pool_size connections in
     * it, a new connection will be created.
     *
     * @return ConnectionWrapper a connection
     */
    public function get() {
        $num_conns = count($this->queue);
        if ($num_conns < $this->pool_size) {
            try {
                $this->make_conn();
            } catch (NoServerAvailable $e) {
                if ($num_conns == 0)
                    throw $e;
            }
        }
        return array_shift($this->queue);
    }

    /**
     * Returns a connection to the pool.
     * @param ConnectionWrapper $connection
     */
    public function return_connection($connection) {
        if ($connection->op_count >= $this->recycle) {
            $this->stats['recycled'] += 1;
            $connection->close();
            $this->make_conn();
            $connection = $this->get();
        }
        array_push($this->queue, $connection);
    }

    /**
     * Gets the keyspace description, caching the results for later lookups.
     * @return mixed
     */
    public function describe_keyspace() {
        if (NULL === $this->keyspace_description) {
            $this->keyspace_description = $this->call("describe_keyspace", $this->keyspace);
        }

        return $this->keyspace_description;
    }

    /**
     * Closes all connections in the pool.
     */
    public function dispose() {
        foreach($this->queue as $conn)
            $conn->close();
    }

    /**
     * Closes all connections in the pool.
     */
    public function close() {
        $this->dispose();
    }

    /**
     * Returns information about the number of opened connections, failed
     * operations, and recycled connections.
     * @return array Stats in the form array("failed" => failure_count,
     *         "created" => creation_count, "recycled" => recycle_count)
     */
    public function stats() {
        return $this->stats;
    }

    /**
     * Performs a Thrift operation using a connection from the pool.
     * The first argument should be the name of the function. The following
     * arguments should be the arguments for that Thrift function.
     *
     * If the connect fails with any exception other than a NotFoundException,
     * the connection will be closed and replaced in the pool. If the
     * Exception is suitable for retrying the operation (TimedOutException,
     * UnavailableException, TTransportException), the operation will be
     * retried with a new connection after an exponentially increasing
     * backoff is performed.
     *
     * To avoid automatic retries, create a ConnectionPool with the
     * $max_retries argument set to 0.
     *
     * In general, this method should *not* be used by users of the
     * library. It is primarily intended for internal use, but is left
     * exposed as an open workaround if needed.
     *
     * @return mixed
     */
    public function call() {
        $args = func_get_args(); // Get all of the args passed to this function
        $f = array_shift($args); // pull the function from the beginning

        $retry_count = 0;
        if ($this->max_retries == -1)
            $tries =  self::MAX_RETRIES;
        elseif ($this->max_retries == 0)
            $tries = 1;
        else
            $tries = $this->max_retries + 1;

        foreach (range(1, $tries) as $retry_count) {
            $conn = $this->get();

            $conn->op_count += 1;
            try {
                $resp = call_user_func_array(array($conn->client, $f), $args);
                $this->return_connection($conn);
                return $resp;
            } catch (cassandra_NotFoundException $nfe) {
                $this->return_connection($conn);
                throw $nfe;
            } catch (cassandra_TimedOutException $toe) {
                $last_err = $toe;
                $this->handle_conn_failure($conn, $f, $toe, $retry_count);
            } catch (cassandra_UnavailableException $ue) {
                $last_err = $ue;
                $this->handle_conn_failure($conn, $f, $ue, $retry_count);
            } catch (TTransportException $tte) {
                $last_err = $tte;
                $this->handle_conn_failure($conn, $f, $tte, $retry_count);
            } catch (Exception $e) {
                $this->handle_conn_failure($conn, $f, $e, $retry_count);
                throw $e;
            }
        }
        throw new MaxRetriesException("An attempt to execute $f failed $tries times.".
                                      " The last error was " . (string)$last_err);
    }

    private function handle_conn_failure($conn, $f, $exc, $retry_count) {
        $err = (string)$exc;
        error_log("Error performing $f on $conn->server: $err", 0);
        $conn->close();
        $this->stats['failed'] += 1;
        usleep(self::BASE_BACKOFF * pow(2, $retry_count) * self::MICROS);
        $this->make_conn();
    }

}

class Connection extends ConnectionPool {


    // Here for backwards compatibility reasons only
    public function __construct($keyspace,
                                $servers=NULL,
                                $credentials=NULL,
                                $framed_transport=true,
                                $send_timeout=5000,
                                $recv_timeout=5000,
                                $retry_time=10)
    {
        trigger_error("The Connection class has been deprecated.  Use ConnectionPool instead.",
            E_USER_NOTICE);

        if ($servers != NULL) {
            $new_servers = array();
            foreach ($servers as $server) {
                $new_servers[] = $server['host'] . ':' . (string)$server['port'];
            }
            $pool_size = count($new_servers);
        } else {
            $new_servers = NULL;
            $pool_size = NULL;
        }

        parent::__construct($keyspace, $new_servers, $pool_size,
            ConnectionPool::DEFAULT_MAX_RETRIES, $send_timeout, $recv_timeout,
            ConnectionPool::DEFAULT_RECYCLE, $credentials, $framed_transport);
    }
}
?>
