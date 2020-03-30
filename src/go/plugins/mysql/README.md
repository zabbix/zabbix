# MySQL plugin
This plugin provides a native solution for monitoring MySQL servers by Zabbix (in-memory data structure store). 
The plugin can monitor several remote or local MySQL instances simultaneously via Zabbix agent 2. Both TCP and 
Unix-socket connections are supported. The plugin keeps connections in the open state to reduce network congestion, 
latency, CPU, and memory usage. It can be used in conjunction with the official "Template DB MySQL by Zabbix agent 2" 
monitoring template (it is also possible to edit or extend the default template as needed or create a new one for 
your specific needs).

## Requirements
- Zabbix Agent 2
- Go >= 1.12 (required only to build from source)

## Installation
The plugin is supplied as part of the Zabbix Agent 2 and does not require any special installation steps. Once 
Zabbix Agent 2 is installed, the plugin is ready to work. Now you need to make sure that a MySQL instance is 
available for connection and configure monitoring.

## Configuration
Open the Zabbix Agent configuration file (zabbix_agent2.conf) and set the required parameters.

**Plugins.Mysql.Uri** — a URI to connect.  
*Default value:* tcp://localhost:3306  
*Requirements:*  
- Must match the URI format.
- Supported sockets: TCP, Unix.

*Examples:*
- tcp://myhost
- unix:/var/run/mysql.sock

**Plugins.Mysql.User** — a username to be used for MySQL authentication.  
*Default value:* root.

**Plugins.Mysql.Password** — a password to be used for MySQL authentication.  
*Default value:* none.

**Plugins.Mysql.KeepAlive** — inactive connection timeout (how long a connection can remain unused before it gets closed).  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Mysql.Timeout** — request execution timeout (how long to wait for a request to complete before shutting it down).  
*Default value:* equals the global 'Timeout' (configuration parameter set in zabbix_agent2.conf).  
*Limits:* 1-30

### Authentication
The plugin uses username and password set in the Agent's configuration file for MySQL authentication (no password by default). 
It is possible to monitor several MySQL instances by creating named sessions in the configuration file and providing 
different usernames, passwords and URIs for each session.

**Note:** For security reasons, it is forbidden to pass embedded credentials within the connString item key parameter 
(can be either a URI or a session name) — such credentials will be ignored.

- If passing a URI as the connString and the connection requires authentication, you can use the username and password 
in item key parameters or the Plugins.Mysql.User and Plugins.Mysql.Password parameters (the 1st level password) in 
the configuration file. In other words, once defined, these parameters will be used for authenticating all connections 
where the connString is represented by URI.

- To use different usernames and passwords for different MySQL instances, create named session in the config file for each 
instance and define a session-level username and password.

#### Named sessions
Named sessions allow you to define specific parameters for each MySQL instance. Currently, only three parameters are supported: 
URI, username, and password.

*Example:*  
If you have two instances: "MySQL1" and "MySQL2", the following options have to be added to the agent configuration:

    Plugins.Mysql.Sessions.MySQL1.Uri=tcp://127.0.0.1:3306
    Plugins.Mysql.Sessions.MySQL1.User=<UsernameForMySQL1>
    Plugins.Mysql.Sessions.MySQL1.Password=<PasswordForMySQL1>    
    Plugins.Mysql.Sessions.MySQL2.Uri=tcp://127.0.0.1:3307   
    Plugins.Mysql.Sessions.MySQL2.User=<UsernameForMySQL2>
    Plugins.Mysql.Sessions.MySQL2.Password=<PasswordForMySQL2>  
    
Now, these names can be used as connStrings in keys instead of URIs:

    mysql.ping[MySQL1]
    mysql.ping[MySQL2]

### Parameters priority
There are 4 levels of parameters overwriting:
1. Hardcoded default values →
2. 1st level config params (Plugins.Mysql.\<parameter\>) →
3. Named sessions (Plugins.Mysql.Sessions.\<sessionName\>.\<parameter\>) →
4. Item key parameters.

## Supported keys

**mysql.ping[connString,username,password]** — tests whether a connection is alive or not.  
*Returns:*
- "1" if the connection is alive.
- "0" if the connection is broken (returned if there was any error during the test, including AUTH and configuration issues).

**mysql.version[connString,username,password]** — MySQL version.  
*Returns:*
String with MySQL instance version.

**mysql.get_status_variables[connString,username,password]** — Values of global status variables.  
*Returns:*
Result of the "show global status" SQL query in JSON format.

**mysql.db.discovery[connString,username,password]** — Databases discovery.  
*Returns:*
Result of the "show databases" SQL query in LLD JSON format.

**mysql.db.size[connString,username,password,dbName]** — Database size in bytes.  
*Params:*  
dbName — Database name. Mandatory.  
*Returns:*  
Result of the "select coalesce(sum(data_length + index_length),0) as size from information_schema.tables where table_schema=\<dbName\>" 
SQL query for specific database in bytes.

**mysql.replication.discovery[connString,username,password]** — Replication discovery.  
*Returns:*  
Result of the "show slave status" SQL query in LLD JSON format.  

**mysql.replication.get_slave_status[connString,username,password,masterHost]** — Replication status.  
*Params:*  
masterHost — Replication master host name. Optional.  
*Returns:*  
Result of the "show slave status" SQL query in JSON format.

## Troubleshooting
The plugin uses Zabbix Agent logs. To receive more detailed information about logged events, consider increasing a debug level 
of Zabbix Agent.
