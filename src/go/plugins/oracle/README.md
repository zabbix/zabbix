# Oracle Database plugin
Provides native Zabbix solution for monitoring Oracle Database. It can monitor several 
Oracle instances simultaneously, remote or local to the Zabbix Agent.
The plugin keeps connections in the open state to reduce network congestion, latency, CPU and 
memory usage. Best for use in conjunction with the official 
[Template DB Oracle Agent 2](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/db/oracle_agent2). 
You can extend it or create your template for your specific needs. 

## Requirements
- Zabbix Agent 2
- Go >= 1.13
- Oracle Instant Client >= 12

## Supported versions of Oracle
* Oracle 12c2
* Oracle 18c
* Oracle 19c

## Installation
* [Install Oracle Instant Client](https://www.oracle.com/database/technologies/instant-client/downloads.html)
* Create an Oracle DB user and grant permissions 
```
CREATE USER zabbix_mon IDENTIFIED BY <PASSWORD>;
-- Grant access to the zabbix_mon user.
GRANT CONNECT, CREATE SESSION TO zabbix_mon;
GRANT SELECT ON DBA_TABLESPACE_USAGE_METRICS TO zabbix_mon;
GRANT SELECT ON DBA_TABLESPACES TO zabbix_mon;
GRANT SELECT ON DBA_USERS TO zabbix_mon;
GRANT SELECT ON SYS.DBA_DATA_FILES TO zabbix_mon;
GRANT SELECT ON V$ACTIVE_SESSION_HISTORY TO zabbix_mon;
GRANT SELECT ON V$ARCHIVE_DEST TO zabbix_mon;
GRANT SELECT ON V$ASM_DISKGROUP TO zabbix_mon;
GRANT SELECT ON V$DATABASE TO zabbix_mon;
GRANT SELECT ON V$DATAFILE TO zabbix_mon;
GRANT SELECT ON V$INSTANCE TO zabbix_mon;
GRANT SELECT ON V$LOG TO zabbix_mon;
GRANT SELECT ON V$OSSTAT TO zabbix_mon;
GRANT SELECT ON V$PGASTAT TO zabbix_mon;
GRANT SELECT ON V$PROCESS TO zabbix_mon;
GRANT SELECT ON V$RECOVERY_FILE_DEST TO zabbix_mon;
GRANT SELECT ON V$RESTORE_POINT TO zabbix_mon;
GRANT SELECT ON V$SESSION TO zabbix_mon;
GRANT SELECT ON V$SGASTAT TO zabbix_mon;
GRANT SELECT ON V$SYSMETRIC TO zabbix_mon;
GRANT SELECT ON V$SYSTEM_PARAMETER TO zabbix_mon;
```
* Make sure a TNS Listener and an Oracle instance are available for connection.  

## Configuration
The Zabbix agent 2 configuration file is used to configure plugins.

**Plugins.Oracle.CallTimeout** — The maximum time in seconds for waiting when a request has to be done.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

**Plugins.Oracle.ConnectTimeout** — The maximum time in seconds for waiting when a connection has to be established.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

**Plugins.Oracle.CustomQueriesPath** — Full pathname of a directory containing *.sql* files with custom queries.  
*Default value:* — (the feature is disabled by default)

**Plugins.Oracle.KeepAlive** — Sets a time for waiting before unused connections will be closed.  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Oracle.Service** — A service name to be used for connection (SID is not supported).  
*Default value:* XE

**Plugins.Oracle.Uri** — Uri to connect.  
*Default value:* tcp://localhost:1521  
*Limits:*
- Must match the URI format.
- The only supported schema is "tcp".
- Embedded credentials are forbidden (will be ignored).
  
*Examples:*
- tcp://127.0.0.1:1521
- tcp://localhost 

### Authentication
The plugin can authenticate using credentials specified as key parameters or within named sessions.
Embedded URI credentials (userinfo) will be ignored. So, you can't pass the credentials by this:   

    oracle.ping[tcp://user:password@127.0.0.1/XE] — WRONG  

The correct way is:
  
    oracle.ping[tcp://127.0.0.1,user,password,XE]

Note: lowercase user names are not supported.

#### Using named sessions
Named sessions allow you to define specific parameters for each Oracle instance. Currently, there are only four supported
parameters: Uri, User, Password and Service. It's a bit more secure way to store credentials compared to 
item keys or macros.  

E.g: suppose you have two instances: "Oracle12" and "Oracle19". 
You should add the following options to the agent configuration file:   

    Plugins.Oracle.Sessions.Oracle12.Uri=tcp://192.168.1.1:1521
    Plugins.Oracle.Sessions.Oracle12.User=<UserForOracle12>
    Plugins.Oracle.Sessions.Oracle12.Password=<PasswordForOracle12>
    Plugins.Oracle.Sessions.Oracle12.Service=orcl
        
    Plugins.Oracle.Sessions.Oracle19.Uri=tcp://192.168.1.2:1521
    Plugins.Oracle.Sessions.Oracle19.User=<UserForOracle19>
    Plugins.Oracle.Sessions.Oracle19.Password=<PasswordForOracle19>
    Plugins.Oracle.Sessions.Oracle19.Service=orcl
    
You can omit either a Uri or a Service if they are already specified as 1st level parameters:

    Plugins.Oracle.Uri=tcp://192.168.1.1:1521
    Plugins.Oracle.Service=orcl
    
Then you will be able to use these names as connStrings in keys instead of URIs, e.g:

    oracle.ping[Oracle12]
    oracle.ping[Oracle19]
    
### Parameters priority
There are 4 levels of parameters overwriting:
1. Hardcoded default values →
2. 1st level config parameters (Plugins.Oracle.\<parameter\>) →
3. Named sessions (Plugins.Oracle.Sessions.\<sessionName\>.\<parameter\>) →
4. Item keys parameters.

## Supported keys
The common parameters for all keys are: [connString][,user][,password][,service]

**oracle.diskgroups.stats[\<commonParams\>]** — Returns ASM disk groups statistics.  

**oracle.diskgroups.discovery[\<commonParams\>]** — Returns list of ASM disk groups in LLD format.  

**oracle.archive.info[\<commonParams\>]** — Returns archive logs statistics.  

**oracle.archive.discovery[\<commonParams\>]** — Returns list of archive logs in LLD format.  

**oracle.cdb.info[\<commonParams\>]** — Returns CDBs info.  

**oracle.custom.query[\<commonParams\>,queryName[,args...]]** — Returns result of a custom query.  
*Parameters:*  
queryName (required) — name of a custom query (must be equal to a name of an sql file without an extension).  
args (optional) — one or more arguments to pass to a query.

**oracle.datafiles.stats[\<commonParams\>]** — Returns data files statistics.  

**oracle.db.discovery[\<commonParams\>]** — Returns list of databases in LLD format.  

**oracle.fra.stats[\<commonParams\>]** — Returns FRA statistics.  

**oracle.instance.info[\<commonParams\>]** — Returns instance stats.  

**oracle.pdb.info[\<commonParams\>]** — Returns PDBs info.  

**oracle.pdb.discovery[\<commonParams\>]** — Returns list of PDBs in LLD format.  

**oracle.pga.stats[\<commonParams\>]** — Returns PGA statistics.  

**oracle.ping[\<commonParams\>]** — Tests if connection is alive or not.  
*Returns:*
- "1" if a connection is alive.
- "0" if a connection is broken (if there is any error presented including AUTH and configuration issues).

**oracle.proc.stats[\<commonParams\>]** — Returns processes statistics.  

**oracle.redolog.info[\<commonParams\>]** — Returns log file information from the control file.

**oracle.sga.stats[\<commonParams\>]** — Returns SGA statistics.  

**oracle.sessions.stats[\<commonParams\>,[lockMaxTime]]** — Returns sessions statistics.
*Parameters:*    
lockMaxTime (optional) — maximum session lock duration in seconds to count the session as a prolongedly locked.
Default: 600 seconds.    

**oracle.sys.metrics[\<commonParams\>[,duration]]** — Returns a set of system metric values.  
*Parameters:*  
duration (optional) — capturing interval (in seconds) of system metric values. Possible values:  
60 — long duration (default).  
15 — short duration.  

**oracle.sys.params[\<commonParams\>]** — Returns a set of system parameter values.  

**oracle.ts.stats[\<commonParams\>]** — Returns tablespaces statistics.  

**oracle.ts.discovery[\<commonParams\>]** — Returns list of tablespaces in LLD format.

**oracle.user.info[\<commonParams\>[,username]]** — Returns user information.  
*Parameters:*  
username (optional) — a username for which the information is needed. Lowercase user names are not supported.
Default: current user.        

## Custom queries
It's possible to extend functionality of the plugin using user-defined queries. To do that you should place all your
queries in a directory specified in Plugins.Oracle.CustomQueriesPath (there is no default path) as *.sql files.
For example, you have a tree:

    /etc/zabbix/oracle/sql/  
    ├── long_tx.sql
    ├── payment.sql    
    └── top_proc.sql
     
You should set Plugins.Oracle.CustomQueriesPath=/etc/zabbix/oracle/sql     
     
So, when the queries are in place, you can execute them:
  
    oracle.custom.query[<commonParams>,top_proc]  
    oracle.custom.query[<commonParams>,long_tx,600]
          
You can pass as many parameters to a query as you need.   
The syntax for placeholder parameters uses ":#", where "#" is an index number of a parameter.   
E.g: 
```
/* payment.sql */

SELECT 
    amount 
FROM 
    payment 
WHERE
    user = :1
    AND service_id = :2
    AND date = :3
``` 

    oracle.custom.query[<commonParams>,payment,"John Doe",1,"10/25/2020"]

## Troubleshooting
The plugin uses Zabbix agent's logs. You can increase a debug level of Zabbix agent if you need more details about 
what is happening.  
The environment variable DPI_DEBUG_LEVEL can be used to selectively turn on the printing of various logging messages
from ODPI-C. See [ODPI-C Debugging](https://oracle.github.io/odpi/doc/user_guide/debugging.html) for details.
