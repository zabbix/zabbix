<?php

namespace LLNW\Zabbix;

use JsonRpc\Server;

class RPC extends Server {
    /**
     * A map of classes that should handle the corresponding API objects requests.
     * NB: method overrides included for non-conventional legacy RPC calls.
     *
     * @var array
     */
    protected static $classMap = array(
        'add.squelch' => array('Squelch', 'add'),
        'clear.squelch' => array('Squelch', 'clear'),
        'ack' => 'Ack',
        'add.ack' => array('Ack', 'add'),
        'get.ack' => array('Ack', 'get'),
        'hostlist' => 'Hostlist',
        'proxymap' => 'Proxymap',
        'alertqueue' => 'AlertQueue',
        'proxy' => 'Proxy',
        'dm' => 'DM', // TODO: Consider re-using Proxy...
        'queue' => 'Queue',
    );
    // TODO: add.squelch,clear.squelch :: Squelch.php
    // TODO: get.ack,ack.get,add.ack,ack.add :: Ack.php
    // TODO: hostlist.get,hostlist.pull :: Hostlist.php
    // TODO: proxymap.get :: proxy.php --> Proxymap.php
    // TODO: alertqueue.create :: alert-queue.php --> AlertQueue.php
    // TODO: proxy.status, proxy.reassign :: proxy-assign.php --> Proxy.php
    // TODO: dm.info :: dm-info.php --> DM.php
    // TODO: queue.info :: queue-info.php --> Queue.php

    /**
     * JSONRPC input string to be used in the RPC callback.
     * @var string
     */
    protected $input;

    public function __construct($json = '') {
        // Setup class and method call
        // Peek into RPC message to lookup predefined class names
        $jsonrpc = \JsonRpc\Base\Rpc::decode($json, $batch);
        $method = empty($jsonrpc['method']) ? '' : $jsonrpc['method'];

        // Convention handling
        $resource = 'RPC'; // Default class to instantiate
        $action = $method;

        $method = strtolower($method); // Lower by convention
        if (in_array($method, $this::$classMap)) {
            if (is_array($this::$classMap[$method])) {
                $resource = $this::$classMap[$method][0];
                $action = $this::$classMap[$method][1];
            }
        }
        elseif (substr_count($method, '.') == 1) {
            // Account for CLASS.METHOD convention
            list($resource, $action) = explode('.', $method);
            if (in_array($resource, $this::$classMap)) {
                if (!is_array($this::$classMap[$method])) {
                    $resource = $this::$classMap[$method];
                }
            }
        }

        $resource = "\\LLNW\\Zabbix\\$resource"; // TODO: consider using RPC namespace
        $methodHandler = new $resource(); // TODO: Error handle instantiation
        $jsonrpc['method'] = $action; // Override the called method convention
        $this->input = json_encode($jsonrpc);
        // TODO: alter transport to use framework HTTP request.
        parent::__construct($methodHandler);
    }

    public function receive() {
        // Use altered jsonrpc method to align with jsonrpc parent class call.
        return parent::receive($this->input);
    }
}