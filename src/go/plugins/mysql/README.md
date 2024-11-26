# MySQL plugin
This plugin provides a native solution for monitoring MySQL servers (relational database management system) by Zabbix. 
The plugin can monitor several remote or local MySQL instances simultaneously via Zabbix agent 2. Both TCP and 
Unix-socket connections are supported. Native connection encryption is also supported. The plugin keeps connections 
in the open state to reduce network congestion, latency, CPU, and memory usage. It can be used in conjunction with the official 
[Mysql template.](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/db/mysql_agent2) 
You can extend it or create your template for your specific needs.

## Requirements
* Zabbix Agent 2
* Go >= 1.13 (required only to build from source)

## Tested DB versions
* MySQL, version 5.7
* Percona, version 8.0
* MariaDB, version 10.4

## DB configuration
The plugin requires a user with the following permissions.

* For MySQL (version 5.7), Percona (version 8.0), MariaDB (version 10.4).
```
CREATE USER 'zbx_monitor'@'%' IDENTIFIED BY '<password>';
GRANT REPLICATION CLIENT, PROCESS, SHOW DATABASES, SHOW VIEW ON *.* TO 'zbx_monitor'@'%';
```
* MariaDB (version >= 10.5.8-5).
```
CREATE USER 'zbx_monitor'@'%' IDENTIFIED BY '<password>';
GRANT REPLICATION CLIENT, PROCESS, SHOW DATABASES, SHOW VIEW, SLAVE MONITOR ON *.* TO 'zbx_monitor'@'%';
```


## Plugin Installation
The plugin is supplied as part of the Zabbix Agent 2 and does not require any special installation steps. Once 
Zabbix Agent 2 is installed, the plugin is ready to work. Now you need to make sure that a MySQL instance is 
available for connection and configure monitoring.

## Plugin configuration
Open the Zabbix Agent configuration file (zabbix_agent2.conf) and set the required parameters.

**Plugins.Mysql.CallTimeout** — The maximum time in seconds for waiting when a request has to be done.  
*Default value:* equals the global Timeout configuration parameter.    
*Limits:* 1-30

**Plugins.Mysql.Timeout** — The maximum time in seconds for waiting when a connection has to be established.  
*Default value:* equals the global Timeout configuration parameter.    
*Limits:* 1-30

**Plugins.Mysql.KeepAlive** — Sets a time for waiting before unused connections will be closed.  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Mysql.Sessions.<session_name>.TLSConnect** — Encryption type for MySQL connection. "*" should be replaced with a session name.
*Default value:* 
*Accepted values:*  required, verify_ca, verify_full

**Plugins.Mysql.Sessions.<session_name>.TLSCAFile** — Full pathname of a file containing the top-level CA(s) certificates for mysql
*Default value:* 

**Plugins.Mysql.Sessions.<session_name>.TLSCertFile** — Full pathname of a file containing the mysql certificate or certificate chain.
*Default value:* 

**Plugins.Mysql.Sessions.<session_name>.TLSKeyFile** — Full pathname of a file containing the mysql private key.
*Default value:* 

### Configuring connection
A connection can be configured using either keys' parameters or named sessions.     

*Notes*:  
* You can leave any connection parameter empty, a default hard-coded value will be used in the such case.
* TLS information can be passed only with sessions.
* Embedded URI credentials (userinfo) are forbidden and will be ignored. So, you can't pass the credentials by this:   
  
      mysql.ping[tcp://user:password@127.0.0.1] — WRONG  
  
  The correct way is:
    
      mysql.ping[tcp://127.0.0.1,user,password]
      
* The only supported network schemas for a URI are "tcp" and "unix".  
Examples of valid URIs:
    - tcp://127.0.0.1:3306
    - tcp://localhost
    - localhost
    - unix:/var/run/mysql.sock
    - /var/run/mysql.sock
      
#### Using keys' parameters
The common parameters for all keys are: [ConnString][,User][,Password]  
Where ConnString can be either a URI or a session name.   
ConnString will be treated as a URI if no session with the given name is found.  
If you use ConnString as a session name, just skip the rest of the connection parameters.  
 
#### Using named sessions
Named sessions allow you to define specific parameters for each Mysql instance. Currently, these are the supported 
parameters: Uri, User, Password, TLSConnect, TLSCAFile, TLSCertFile and TLSKeyFile. It's a bit more secure way to 
store credentials compared to item keys or macros.

E.g: suppose you have two Mysql instances: "Prod" and "Test". 
You should add the following options to the agent configuration file:   

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
        
Then you will be able to use these names as the 1st parameter (ConnString) in keys instead of URIs, e.g:

    mysql.ping[Prod]
    mysql.ping[Test]

*Note*: sessions names are case-sensitive.
  
## Supported keys
**mysql.custom.query[\<commonParams\>,queryName[,args...]** — Returns the result of a custom query.
*Parameters:*  
queryName (required) — the name of a custom query (must be equal to the name of an *sql* file without an extension).
args (optional) — one or more arguments to pass to a query.

**mysql.db.discovery[\<commonParams\>]** — Returns list of databases in LLD format.

**mysql.db.size[\<commonParams\>,database]** — Returns size of given database in bytes.  
*Parameters:*  
database (required) — database name.

**mysql.ping[\<commonParams\>]** — Tests if connection is alive or not.  
*Returns:*
- "1" if the connection is alive.
- "0" if the connection is broken (returned if there was any error during the test, including AUTH and configuration issues).

**mysql.replication.discovery[\<commonParams\>]** — Returns replication information in LLD format.   

**mysql.replication.get_slave_status[\<commonParams\>,\<masterHost\>]** — Returns replication status.

*Parameters:*  
`masterHost` (optional) — the name of the master host.

**mysql.get_status_variables[\<commonParams\>]** — Returns values of global status variables.

**mysql.version[\<commonParams\>]** — Returns MySQL version.      

## Custom queries

It is possible to extend the functionality of the plugin using user-defined queries. In order to do it, you should place all your queries in a specified directory in `Plugins.Mysql.CustomQueriesPath` (there is no default path) as it is for *.sql* files.
For example, you can have a following tree:

    /etc/zabbix/mysql/sql/  
    ├── long_tx.sql
    ├── payment.sql    
    └── top_proc.sql
     
Then, you should set `Plugins.Mysql.CustomQueriesPath=/etc/zabbix/mysql/sql`.
     
Finally, when the queries are located in the right place, you can execute them:

    mysql.custom.query[<commonParams>,top_proc]  
    mysql.custom.query[<commonParams>,long_tx,600]
          
You can pass as many parameters to a query as you need.   
The syntax for the placeholder parameters uses "?" where "?" is the parameter in order as provided. 
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
The plugin uses Zabbix agent's logs. You can increase debugging level of Zabbix Agent if you need more details about 
what is happening. 
