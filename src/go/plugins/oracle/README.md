# Oracle Database plugin

This plugin provides native Zabbix solution to monitor Oracle Database (multi-model database management system).
It can monitor several Oracle instances simultaneously; remote or local to Zabbix agent.
The plugin keeps connections in an open state to reduce network congestion, latency, CPU and
memory usage. It is highly recommended to use in conjunction with the official 
[Oracle template.](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/db/oracle_agent2) 
You can extend it or create your own template to cater specific needs.

## Requirements

* Zabbix Agent 2
* Go >= 1.18 (required only to build from source)
* Oracle Instant Client >= 12

## Supported versions

* Oracle Database 12c2
* Oracle Database 18c
* Oracle Database 19c

## Installation

1. [Install Oracle Instant Client](https://www.oracle.com/database/technologies/instant-client/downloads.html).
2. Create an Oracle DB user and grant permissions. 

```
CREATE USER zabbix_mon IDENTIFIED BY <PASSWORD>;
-- Grant access to the zabbix_mon user.
GRANT CONNECT, CREATE SESSION TO zabbix_mon;
GRANT SELECT_CATALOG_ROLE to zabbix_mon;
GRANT SELECT ON DBA_TABLESPACE_USAGE_METRICS TO zabbix_mon;
GRANT SELECT ON DBA_TABLESPACES TO zabbix_mon;
GRANT SELECT ON DBA_USERS TO zabbix_mon;
GRANT SELECT ON SYS.DBA_DATA_FILES TO zabbix_mon;
GRANT SELECT ON V_$ACTIVE_SESSION_HISTORY TO zabbix_mon;
GRANT SELECT ON V_$ARCHIVE_DEST TO zabbix_mon;
GRANT SELECT ON V_$ASM_DISKGROUP TO zabbix_mon;
GRANT SELECT ON V_$DATABASE TO zabbix_mon;
GRANT SELECT ON V_$DATAFILE TO zabbix_mon;
GRANT SELECT ON V_$INSTANCE TO zabbix_mon;
GRANT SELECT ON V_$LOG TO zabbix_mon;
GRANT SELECT ON V_$OSSTAT TO zabbix_mon;
GRANT SELECT ON V_$PGASTAT TO zabbix_mon;
GRANT SELECT ON V_$PROCESS TO zabbix_mon;
GRANT SELECT ON V_$RECOVERY_FILE_DEST TO zabbix_mon;
GRANT SELECT ON V_$RESTORE_POINT TO zabbix_mon;
GRANT SELECT ON V_$SESSION TO zabbix_mon;
GRANT SELECT ON V_$SGASTAT TO zabbix_mon;
GRANT SELECT ON V_$SYSMETRIC TO zabbix_mon;
GRANT SELECT ON V_$SYSTEM_PARAMETER TO zabbix_mon;
```
3. Make sure a TNS Listener and an Oracle instance are available for the connection.  

## Configuration

To configure plugins, Zabbix agent 2 configuration file is used.

**Plugins.Oracle.CallTimeout** — the maximum time in seconds for waiting when a request has to be done.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

**Plugins.Oracle.ConnectTimeout** — the maximum time in seconds for waiting when a connection has to be established.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

**Plugins.Oracle.CustomQueriesPath** — the full pathname of a directory containing *.sql* files with custom queries.  
*Default value:* —  the feature is disabled by default.

**Plugins.Oracle.KeepAlive** — sets the time for waiting before unused connections will be closed.
*Default value:* 300 sec.  
*Limits:* 60-900

### Configuring connection

The connection can be configured using either key parameters or named sessions.

*Notes*:  
* You can leave any connection parameter value empty; in this case the default, hard-coded value, will be used.
* Embedded URI credentials (e.g. user credentials) are not supported and will be ignored. It is not possible to override the credentials this way: 
  
      oracle.ping[tcp://USER:password@127.0.0.1/XE] — WRONG  
  
  The correct way is:
    
      oracle.ping[tcp://127.0.0.1,USER,password,XE]
      
* The only supported URI network schema is "tcp".
Examples of valid URIs:

    - tcp://127.0.0.1:1521
    - tcp://localhost
    - localhost
    
* Usernames are supported only if written in uppercase characters.
      
#### Using key parameters

Common parameters for all the keys are: [ConnString][User][Password][Service] where `ConnString` can be either a URI or a session name.
`ConnString` will be treated as a URI if no session with the given name is found.
User can contain sysdba, sysoper, sysasm privileges. It must be used with `as` as a separator
e.g `user as sysdba`, privilege can be upper or lowercase, and must be at the end of username string.
If you use `ConnString` as a session name, you can skip the rest of the connection parameters.
 
#### Using named sessions

Named sessions allow to define specific parameters for each Oracle instance. Currently, there are only four supported parameters: `Uri`, `User`, `Password` and `Service`.
This option to store the credentials is slightly more secure way compared to item keys or macros.

For example, if you have two Oracle instances: "Oracle12" and "Oracle19", you should add the following options to the agent configuration file:   

    Plugins.Oracle.Sessions.Oracle12.Uri=tcp://192.168.1.1:1521
    Plugins.Oracle.Sessions.Oracle12.User=<USERFORORACLE12>
    Plugins.Oracle.Sessions.Oracle12.Password=<PasswordForOracle12>
    Plugins.Oracle.Sessions.Oracle12.Service=orcl
        
    Plugins.Oracle.Sessions.Oracle19.Uri=tcp://192.168.1.2:1521
    Plugins.Oracle.Sessions.Oracle19.User=<USERFORORACLE19>
    Plugins.Oracle.Sessions.Oracle19.Password=<PasswordForOracle19>
    Plugins.Oracle.Sessions.Oracle19.Service=orcl
        
Then you will be able to use these names as the first parameter (ConnString) in keys instead of URIs.
For example:

    oracle.ping[Oracle12]
    oracle.ping[Oracle19]

Note: session names are case-sensitive.

## Supported keys

**oracle.diskgroups.stats[\<commonParams\>,\<diskgroup\>]** — returns Automatic Storage Management (ASM) disk groups statistics.

*Parameters:*  
`diskgroup` (optional) — the name of the diskgroup.

**oracle.diskgroups.discovery[\<commonParams\>]** — returns the list of ASM disk groups in LLD format.

**oracle.archive.info[\<commonParams\>,\<destination\>]** — returns archive logs statistics.
*Parameters:*  
`destination` (optional) — the name of the destination.

**oracle.archive.discovery[\<commonParams\>]** — returns the list of archive logs in LLD format.

**oracle.cdb.info[\<commonParams\>,\<database\>]** — returns the Container Databases (CDBs) info.
*Parameters:*  
`database` (optional) — the name of the database.

**oracle.custom.query[\<commonParams\>,queryName[,args...]]** — returns the result of a custom query.

*Parameters:*  
`queryName` (required) — the name of a custom query (must be equal to the name of an *sql* file without an extension).
`args` (optional) — one or more arguments to pass to a query.

**oracle.datafiles.stats[\<commonParams\>]** — returns the data files statistics.

**oracle.db.discovery[\<commonParams\>]** — returns the list of databases in LLD format.

**oracle.fra.stats[\<commonParams\>]** — returns Fast Recovery Area (FRA) statistics.

**oracle.instance.info[\<commonParams\>]** — returns instance statistics.

**oracle.pdb.info[\<commonParams\>,\<database\>]** — returns the Plugable Databases (PDBs) information.
*Parameters:*  
`database` (optional) — the name of the database.

**oracle.pdb.discovery[\<commonParams\>]** — returns the list of PDBs in LLD format.

**oracle.pga.stats[\<commonParams\>]** — returns the Program Global Area (PGA) statistics.  

**oracle.ping[\<commonParams\>]** —  performs a simple ping to check if the connection is alive or not.

*Returns:*
- "1" if a connection is alive.
- "0" if a connection is broken (if there is any error presented including authorization and configuration issues).

**oracle.proc.stats[\<commonParams\>]** — returns the processes' statistics. 

**oracle.redolog.info[\<commonParams\>]** — returns the log file information from the control file.

**oracle.sga.stats[\<commonParams\>]** —  returns the System Global Area (SGA) statistics.

**oracle.sessions.stats[\<commonParams\>,[lockMaxTime]]** — returns the sessions' statistics.

*Parameters:*    
`lockMaxTime` (optional) — the maximum duration of the session lock in seconds to count the session as locked prolongedly.
Default: 600 seconds.    

**oracle.sys.metrics[\<commonParams\>[,duration]]** —  returns a set of the system metric values.

*Parameters:*  
Duration (optional) — capturing interval in seconds of the system metric values.
Possible values:  
60 — long duration (default).  
15 — short duration.  

**oracle.sys.params[\<commonParams\>]** — returns a set of the system parameter values.

**oracle.ts.stats[\<commonParams\>,\<tablespace\>,\<type\>]** — returns the tablespace statistics. 
*Parameters:*  
`tablespace` (optional) — the name of the tablespace.
`type` (optional) — the type of the tablespace.

**oracle.ts.discovery[\<commonParams\>]** — returns the list of tablespaces in Low-level discovery (LLD) format.

**oracle.user.info[\<commonParams\>[,username]]** — returns the user information.

*Parameters:*  
Username (optional) — the username for which the information is required. Usernames written in lowercase characters are not supported.
Default: the current user.

**oracle.version[\<commonParams\>]** — returns the database server information.

## Custom queries

It is possible to extend the functionality of the plugin using user-defined queries. In order to do it, you should place all your queries in a specified directory in `Plugins.Oracle.CustomQueriesPath` (there is no default path) as it is for *.sql* files.
For example, you can have a following tree:

    /etc/zabbix/oracle/sql/  
    ├── long_tx.sql
    ├── payment.sql    
    └── top_proc.sql
     
Then, you should set `Plugins.Oracle.CustomQueriesPath=/etc/zabbix/oracle/sql`.
     
Finally, when the queries are located in the right place, you can execute them:

    oracle.custom.query[<commonParams>,top_proc]  
    oracle.custom.query[<commonParams>,long_tx,600]
          
You can pass as many parameters to a query as you need.   
The syntax for the placeholder parameters uses ":#" where "#" is an index number of the parameter. 
For example: 

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

## Current limitations

* The System Identifier (SID) connection method is not supported.
* Only usernames written in uppercase characters are supported.

## Troubleshooting

The plugin uses logs of Zabbix agent 2. You can increase debugging level of Zabbix agent 2 if you need more details about the current situation.
The environment variable DPI_DEBUG_LEVEL can be used to selectively turn on the printing of various logging messages from Oracle Database Programming Interface for C (ODPI-C).
See [ODPI-C Debugging](https://oracle.github.io/odpi/doc/user_guide/debugging.html) for details.
