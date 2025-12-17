# MySQL plugin

This plugin provides a native solution for monitoring MySQL servers via Zabbix.

The plugin can monitor several remote or local MySQL instances simultaneously via Zabbix agent 2. Both TCP and Unix-socket connections are supported. Native connection encryption is also supported.

The plugin keeps connections in the open state to reduce network congestion, latency, CPU, and memory usage. It can be used in conjunction with the official [Zabbix MySQL template](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/db/mysql_agent2), which you can extend; alternatively, you can create your own template for your specific needs.

## Supported versions

* MySQL, version 5.7+
* Percona, version 8.0+
* MariaDB, version 10.4+

## Database configuration

The plugin requires a user with the following permissions;

* MySQL (version 5.7), Percona (version 8.0), MariaDB (version 10.4):
```
CREATE USER 'zbx_monitor'@'%' IDENTIFIED BY '<password>';
GRANT REPLICATION CLIENT, PROCESS, SHOW DATABASES, SHOW VIEW ON *.* TO 'zbx_monitor'@'%';
```
* MariaDB slave instance (version >= 10.5.8-5):
```
CREATE USER 'zbx_monitor'@'%' IDENTIFIED BY '<password>';
GRANT REPLICATION CLIENT, PROCESS, SHOW DATABASES, SHOW VIEW, SLAVE MONITOR ON *.* TO 'zbx_monitor'@'%';
```

## Plugin installation

The plugin is supplied as part of Zabbix agent 2, and does not require any additional installation steps. Once Zabbix agent 2 is installed, the plugin is ready. You then need to make sure that a MySQL instance is available for connection and configure monitoring.

## Plugin configuration

Open the Zabbix agent configuration file (`zabbix_agent2.conf`) and set the required parameters.

`Plugins.Mysql.CallTimeout`—The maximum time, in seconds, for waiting when a request has to be completed.
<br>Default value: equals the global timeout configuration parameter.
<br>Range: 1–30

`Plugins.Mysql.CustomQueriesPath`—Full pathname of a directory containing `.sql` files with custom queries.
<br>Default value for Unix systems: `/usr/local/share/zabbix/custom-queries/mysql`.
<br>Default value for Windows systems: `*:\Program Files\Zabbix Agent 2\Custom Queries\Mysql`, where `*` is the drive name taken from the `ProgramFiles` environment variable.

`Plugins.Mysql.CustomQueriesEnabled`—If set, enables the execution of the `mysql.custom.query` item key. If disabled, will not load any queries from the custom query directory path.
<br>Default value: false

`Plugins.Mysql.Timeout`—The maximum time, in seconds, for waiting when a connection has to be established.
<br>Default value: equals the global timeout configuration parameter.
<br>Range: 1–30

`Plugins.Mysql.KeepAlive`—Sets a time, in seconds, for waiting before unused connections are closed.
<br>Default value: 300
<br>Range: 60–900

`Plugins.Mysql.Sessions.<session_name>.TLSConnect`—Encryption type for the MySQL connection. `*` should be replaced with a session name.
<br>Supported values: `required`, `verify_ca`, `verify_full`

`Plugins.Mysql.Sessions.<session_name>.TLSCAFile`—Full pathname of a file containing the top-level CA certificates for MySQL.

`Plugins.Mysql.Sessions.<session_name>.TLSCertFile`—Full pathname of a file containing the MySQL certificate or certificate chain.

`Plugins.Mysql.Sessions.<session_name>.TLSKeyFile`—Full pathname of a file containing the MySQL private key.

### Configuring connection

A connection can be configured using either key parameters or named sessions.

* You can leave any connection parameter empty, in which case a default hard-coded value will be used.
* TLS information can be passed only with sessions.
* Embedded URI credentials (username and password) are forbidden and will be ignored. The following is incorrect:
  
      mysql.ping[tcp://user:password@127.0.0.1]
  
  Correct:
    
      mysql.ping[tcp://127.0.0.1,user,password]
      
* The only supported network schemas for a URI are `tcp` and `unix`.
Examples of valid URIs:
    - `tcp://127.0.0.1:3306`
    - `tcp://localhost`
    - `localhost`
    - `unix:/var/run/mysql.sock`
    - `/var/run/mysql.sock`
      
#### Using key parameters

The common parameters for all keys are `[ConnString][,User][,Password]`, where `ConnString` can be either a URI or a session name.
`ConnString` will be treated as a URI if no session with the given name is found.
If you use `ConnString` as a session name, skip the rest of the connection parameters.

#### Using named sessions

Named sessions allow you to define specific parameters for each MySQL instance. Currently, these are the supported parameters: `Uri`, `User`, `Password`, `TLSConnect`, `TLSCAFile`, `TLSCertFile`, and `TLSKeyFile`. It's a more secure way to store credentials compared to item keys or macros.

For example, if you have two MySQL instances, "Prod" and "Test", you need to add the following options to the agent configuration file:

    Plugins.Mysql.Sessions.Prod.Uri=tcp://192.168.1.1:3306
    Plugins.Mysql.Sessions.Prod.User=<UserForProd>  
    Plugins.Mysql.Sessions.Prod.Password=<PasswordForProd>
    Plugins.Mysql.Sessions.Prod.TLSConnect=verify_full
    Plugins.Mysql.Sessions.Prod.TLSCAFile=/path/to/ca_file
    Plugins.Mysql.Sessions.Prod.TLSCertFile=/path/to/cert_file
    Plugins.Mysql.Sessions.Prod.TLSKeyFile=/path/to/key_file
      
    Plugins.Mysql.Sessions.Test.Uri=tcp://192.168.0.1:3306
    Plugins.Mysql.Sessions.Test.User=<UserForTest>   
    Plugins.Mysql.Sessions.Test.Password=<PasswordForTest>
    Plugins.Mysql.Sessions.Test.TLSConnect=verify_ca
    Plugins.Mysql.Sessions.Test.TLSCAFile=/path/to/test/ca_file
    Plugins.Mysql.Sessions.Test.TLSCertFile=/path/to/test/cert_file
    Plugins.Mysql.Sessions.Test.TLSKeyFile=/path/to/test/key_file
        
You will then be able to use these session names as the first parameter (`ConnString`) in your keys instead of URIs, e.g.:

    mysql.ping[Prod]
    mysql.ping[Test]

Note that session names are case-sensitive.

## Supported keys

`mysql.custom.query[<commonParams>,queryName,<args...>] `— Returns the result of a custom query.
<br>Parameters:
<br>`queryName` (required)—the name of a custom query (must be equal to the name of an `sql` file without an extension).
<br>`args` (optional)—one or more arguments to pass to a query.

`mysql.db.discovery[<commonParams>]` — Returns a list of databases in LLD format.

`mysql.db.size[<commonParams>,database]`— Returns the size of a given database in bytes.
<br>Parameters:
<br>`database` (required) — database name.

`mysql.ping[<commonParams>]` — Tests if a connection is alive or not.
<br>Returns:
<br>- `1` if the connection is alive;
<br>- `0` if the connection is broken (returned if there was any error during the test, including authentication and configuration issues).

`mysql.replication.discovery[<commonParams>]` — Returns replication information in LLD format*.

`mysql.replication.get_slave_status[<commonParams>,<masterHost>]` — Returns the replication status.
<br>Parameters:
<br>`masterHost` (optional) — The name of the master host*.

`mysql.get_status_variables[<commonParams>]` — Returns values of global status variables.

`mysql.version[<commonParams>]` — Returns the MySQL version.

\* In some MySQL server versions, the replication status query `SHOW SLAVE STATUS` is deprecated or has been fully replaced by `SHOW REPLICA STATUS`. The result key names have also changed — from `Master` to `Source` and from `Slave` to `Replica`.
* the old style terminology:	`"Master_Host": "myserver"` or `"Slave_IO_Running": "Yes"`
* the new style, respectively:  `"Source_Host": "myserver"` or `"Replica_IO_Running": "Yes"`.  

To maintain compatibility, the result set includes duplicated keys using both terminology styles.

## Custom queries

It is possible to extend the functionality of the plugin using user-defined queries. To do so, place all your queries in a specified directory in `Plugins.Mysql.CustomQueriesPath`, as with `.sql` files.

For example, you can have the following tree:

    /etc/zabbix/mysql/sql/  
    ├── long_tx.sql
    ├── payment.sql    
    └── top_proc.sql
     
Then, you should set `Plugins.Mysql.CustomQueriesPath=/etc/zabbix/mysql/sql`.
     
Finally, when the queries are located in the right place, you can execute them:

    mysql.custom.query[<commonParams>,top_proc]  
    mysql.custom.query[<commonParams>,long_tx,600]
          
You can pass as many parameters to a query as you need.
The syntax for the placeholder parameters uses `?`, with each `?` representing a parameter in the order they are provided.

For example:

```
/* payment.sql */

SELECT 
    amount 
FROM 
    payment 
WHERE
    user = ?
    AND service_id = ?
    AND date = ?
``` 

    mysql.custom.query[<commonParams>,payment,"John Doe",1,"10/25/2020"]

## Troubleshooting

This plugin uses Zabbix agent logs. If extended monitoring is needed, you can increase the debug level of Zabbix agent.
