# Oracle Database plugin

This plugin provides a native Zabbix solution to monitor Oracle Database (multi-model database management system).
It can monitor several Oracle instances simultaneously; remote or local to Zabbix agent 2.
The plugin keeps connections in an open state to reduce network congestion, latency, CPU and
memory usage. It is highly recommended to use it in conjunction with the official
[Oracle template.](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/db/oracle_agent2)
You can extend it or create your own template to cater to specific needs.

**Important! This integration queries the `V$ACTIVE_SESSION_HISTORY` dynamic performance view which is part of the Oracle Diagnostics Pack. Please make sure that you have the license required for using this management pack.**

## Supported versions

* Oracle Database 12c2 and newer

## Installation

1. [Install Oracle Instant Client](https://www.oracle.com/database/technologies/instant-client/downloads.html).
2. Create an Oracle DB user and grant permissions.

In CDB installations, it is possible to monitor tablespaces from CDB _(container database)_ and all PDBs _(pluggable databases)_. In this case, a common user is needed with the correct rights:

```
CREATE USER c##zabbix_mon IDENTIFIED BY <PASSWORD>;
-- Grant access to the c##zabbix_mon user.
ALTER USER c##zabbix_mon SET CONTAINER_DATA=ALL CONTAINER=CURRENT;
GRANT CONNECT, CREATE SESSION TO c##zabbix_mon;
GRANT SELECT_CATALOG_ROLE to c##zabbix_mon;
GRANT SELECT ON v_$instance TO c##zabbix_mon;
GRANT SELECT ON v_$database TO c##zabbix_mon;
GRANT SELECT ON v_$sysmetric TO c##zabbix_mon;
GRANT SELECT ON v_$system_parameter TO c##zabbix_mon;
GRANT SELECT ON v_$session TO c##zabbix_mon;
GRANT SELECT ON v_$recovery_file_dest TO c##zabbix_mon;
GRANT SELECT ON v_$active_session_history TO c##zabbix_mon;
GRANT SELECT ON v_$osstat TO c##zabbix_mon;
GRANT SELECT ON v_$process TO c##zabbix_mon;
GRANT SELECT ON v_$datafile TO c##zabbix_mon;
GRANT SELECT ON v_$pgastat TO c##zabbix_mon;
GRANT SELECT ON v_$sgastat TO c##zabbix_mon;
GRANT SELECT ON v_$log TO c##zabbix_mon;
GRANT SELECT ON v_$archive_dest TO c##zabbix_mon;
GRANT SELECT ON v_$asm_diskgroup TO c##zabbix_mon;
GRANT SELECT ON v_$asm_diskgroup_stat TO c##zabbix_mon;
GRANT SELECT ON DBA_USERS TO c##zabbix_mon;
```
This is needed because the template uses ```CDB_*``` views to monitor tablespaces from CDB and different PDBs, and, therefore, the monitoring user needs access to the container data objects on all PDBs.

However, if you wish to monitor only a single PDB or non-CDB instance, a local user is enough:

```
CREATE USER zabbix_mon IDENTIFIED BY <PASSWORD>;
-- Grant access to the zabbix_mon user.
GRANT CONNECT, CREATE SESSION TO zabbix_mon;
GRANT SELECT_CATALOG_ROLE to zabbix_mon;
GRANT SELECT ON DBA_USERS TO zabbix_mon;
GRANT SELECT ON V_$ACTIVE_SESSION_HISTORY TO zabbix_mon;
GRANT SELECT ON V_$ARCHIVE_DEST TO zabbix_mon;
GRANT SELECT ON V_$ASM_DISKGROUP TO zabbix_mon;
GRANT SELECT ON V_$ASM_DISKGROUP_STAT TO zabbix_mon;
GRANT SELECT ON V_$DATABASE TO zabbix_mon;
GRANT SELECT ON V_$DATAFILE TO zabbix_mon;
GRANT SELECT ON V_$INSTANCE TO zabbix_mon;
GRANT SELECT ON V_$LOG TO zabbix_mon;
GRANT SELECT ON V_$OSSTAT TO zabbix_mon;
GRANT SELECT ON V_$PGASTAT TO zabbix_mon;
GRANT SELECT ON V_$PROCESS TO zabbix_mon;
GRANT SELECT ON V_$RECOVERY_FILE_DEST TO zabbix_mon;
GRANT SELECT ON V_$SESSION TO zabbix_mon;
GRANT SELECT ON V_$SGASTAT TO zabbix_mon;
GRANT SELECT ON V_$SYSMETRIC TO zabbix_mon;
GRANT SELECT ON V_$SYSTEM_PARAMETER TO zabbix_mon;
```

**Important! These privileges grant the monitoring user `SELECT_CATALOG_ROLE`, which, in turn, gives access to thousands of tables in the database.**
This role is required to access the `V$RESTORE_POINT` dynamic performance view.
However, there are ways to go around this, if the `SELECT_CATALOG_ROLE` assigned to a monitoring user raises any security issues.
One way to do this is using **pipelined table functions**:

  1. Log into your database as the `SYS` user or make sure that your administration user has the required privileges to execute the steps below;

  2. Create types for the table function:

      ```sql
      CREATE OR REPLACE TYPE zbx_mon_restore_point_row AS OBJECT (
        SCN                           NUMBER,
        DATABASE_INCARNATION#         NUMBER,
        GUARANTEE_FLASHBACK_DATABASE  VARCHAR2(3),
        STORAGE_SIZE                  NUMBER,
        TIME                          TIMESTAMP(9),
        RESTORE_POINT_TIME            TIMESTAMP(9),
        PRESERVED                     VARCHAR2(3),
        NAME                          VARCHAR2(128),
        PDB_RESTORE_POINT             VARCHAR2(3),
        CLEAN_PDB_RESTORE_POINT       VARCHAR2(3),
        PDB_INCARNATION#              NUMBER,
        REPLICATED                    VARCHAR2(3),
        CON_ID                        NUMBER
      );
      CREATE OR REPLACE TYPE zbx_mon_restore_point_tab IS TABLE OF zbx_mon_restore_point_row;
      ```

  3. Create the pipelined table function:

      ```sql
      CREATE OR REPLACE FUNCTION zbx_mon_restore_point RETURN zbx_mon_restore_point_tab PIPELINED AS
      BEGIN
        FOR i IN (SELECT * FROM V$RESTORE_POINT) LOOP
          PIPE ROW (zbx_mon_restore_point_row(i.SCN, i.DATABASE_INCARNATION#, i.GUARANTEE_FLASHBACK_DATABASE, i.STORAGE_SIZE, i.TIME, i.RESTORE_POINT_TIME, i.PRESERVED, i.NAME, i.PDB_RESTORE_POINT, i.CLEAN_PDB_RESTORE_POINT, i.PDB_INCARNATION#, i.REPLICATED, i.CON_ID));
        END LOOP;
        RETURN;
      END;
      ```

  4. Grant the Zabbix monitoring user the Execute privilege on the created pipelined table function and replace the monitoring user `V$RESTORE_POINT` view with the `SYS` user function (in this example, the `SYS` user is used to create DB types and function):

    ```sql
    GRANT EXECUTE ON zbx_mon_restore_point TO c##zabbix_mon;
    CREATE OR REPLACE VIEW c##zabbix_mon.V$RESTORE_POINT AS SELECT * FROM TABLE(SYS.zbx_mon_restore_point);
    ```

  5. Finally, revoke the `SELECT_CATALOG_ROLE` and grant additional permissions that were previously covered by the `SELECT_CATALOG_ROLE`.

    `sql
    REVOKE SELECT_CATALOG_ROLE FROM c##zabbix_mon;
    GRANT SELECT ON v_$pdbs TO c##zabbix_mon;
    GRANT SELECT ON v_$sort_segment TO c##zabbix_mon;
    GRANT SELECT ON v_$parameter TO c##zabbix_mon;
    GRANT SELECT ON CDB_TABLESPACES TO c##zabbix_mon;
    GRANT SELECT ON CDB_DATA_FILES TO c##zabbix_mon;
    GRANT SELECT ON CDB_FREE_SPACE TO c##zabbix_mon;
    GRANT SELECT ON CDB_TEMP_FILES TO c##zabbix_mon;
    ```

  > Note that in these examples, the monitoring user is named `c##zabbix_mon` and the system user - `SYS`. Change these example usernames to ones that are appropriate for your environment.

  If this workaround does not work for you, there are more options available, such as __materialized views__, but look out for data refresh as `V$RESTORE_POINT` is the dynamic performance view.

  6. Make sure a TNS<sup>[2](#footnote-2)</sup> Listener and an Oracle instance are available for the connection.

  _Note_:
  Upgrading

## Configuration

To configure plugins, Zabbix agent 2 configuration file is used.

**Plugins.Oracle.CallTimeout** — the maximum wait time in seconds for a request to be completed.
Currently has no effect and the maximum wait time is determined by item timeout instead.
*Default value:* equals the global<sup>[1](#footnote-1)</sup> "Timeout" configuration option.
*Limits:* 1-30

**Plugins.Oracle.ConnectTimeout** — the maximum wait time in seconds for a connection to be established.
*Default value:* equals the global<sup>[1](#footnote-1)</sup> "Timeout" configuration option.
*Limits:* 1-30

**Plugins.Oracle.CustomQueriesPath** — full pathname of a directory containing *.sql* files with custom queries.
*Default value:*:
- `/usr/local/share/zabbix/custom-queries/oracle` for unix systems.

- `*:\Program Files\Zabbix Agent 2\Custom Queries\Oracle` for Microsoft Windows systems,
where * is drive name taken from `ProgramFiles` environment variable.

**Plugins.Oracle.CustomQueriesEnabled** — if set, enables the execution of `mysql.custom.query`item key.
If disabled, will not load any queries from the custom query dir path.
*Default value:* false

**Plugins.Oracle.KeepAlive** — sets the time for waiting before unused connections will be closed.
*Default value:* 300 sec.
*Limits:* 60-900

**Plugins.Oracle.ResolveTNS** — specifies how to interpret the URI string — provided either in the metrics key (ConnString part) or the Uri option parameter in Oracle plugin's config file — for a connection to the Oracle server. If ResolveTNS set to true, the URI schema and port will be ignored and only hostname part will be used as TNS descriptor — either key or value. If the descriptor is a TNS key, the Oracle client looks up the connection description in the tnsnames.ora file. If a TNS value (begins with `(DESCRIPTION...`), the Oracle client will use it for the Oracle server connection as is.
*Default value:* true

### Configuring connection

The connection can be configured using either key parameters or named sessions.

*Notes*:
* You can leave any connection parameter value empty; in this case, the default - hard-coded value will be used.
* Embedded URI credentials (e.g., user credentials) are not supported and will be ignored. It is not possible to override the credentials this way:

      oracle.ping[tcp://USER:password@127.0.0.1/XE] — WRONG

  The correct way is:

      oracle.ping[tcp://127.0.0.1,USER,password,XE]

* The only supported URI network schema is "tcp". Examples of valid URIs:

    - tcp://localhost:1521
    - tcp://localhost
    - localhost:1521
    - localhost

    The hostname "localhost" in examples can be IP address, e.g., 127.0.0.1 or any other hostname.

* Usernames are supported only if written in uppercase characters.

* Both oracle TNS key and value can be specified as Hostname (omitting schema and port). See chapter [Using TNS Names](#using-tns-names).

#### Multitenant architecture tablespace monitoring (across CDB and PDBs)

To be able to monitor tablespaces across multiple containers, the Oracle service name needs to be pointed to the root CDB and a common user must be used for a connection.

#### Using key parameters

Common parameters for all keys are: [ConnString][User][Password][Service] where `ConnString` can be an URI, session name, TNS key or its value.
`ConnString` will be treated as follows:
  - as session name — if such a name is found in the plugin's configuration file
  - as URI — if no any session with the given name is found, and Plugins.Oracle.ResolveTNS is set to false. If it contains a schema, e.g., "tcp://", port, e.g., 1521 or both, ResolveTNS option will not be taken into account, and it anyway will be treated as URI
  - as TNS key — if the plugin's option ResolveTNS is set to true, and none of the above conditions apply
  - as TNS value — if it starts with the open bracket “(“ (leading spaces ignored). In this case, the ResolveTNS option is not taken into account.


 See the chapter [Using TNS Names](#using-tns-names) for the details about how the plugin resolves TNS names.

The parameter User can contain sysdba, sysoper, sysasm, sysbackup, sysdg, syskm, sysrac privileges. It must be used with `as` as a separator
e.g `user as sysdba`, privilege can be upper or lowercase, and must be at the end of the username string.
If you use `ConnString` as a session name, you can skip the rest of the connection parameters.

_Note:_
If ResolveTNS=true and ConnString is specified for TNS lookup, but the name does not exist in tnsnames.ora, the Oracle client tries to resolve the ConnString as a DNS name.
In the case where it is a non-existent DNS name, the process usually takes a long time and ends with an error response: "Timeout occurred while gathering data" (if the default timeout option is used).
If the specified ConnString is resolved as a DNS name, the Oracle client attempts to connect using the hostname.

#### Using named sessions

Named sessions allow defining specific parameters for each Oracle instance. Currently, there are four supported parameters: `Uri`, `User`, `Password` and `Service`.
This option to store credentials is a slightly more secure way compared to item keys or macros.

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

_Notes_:
- Session names are case-sensitive.
- URI can also contain either TNS key or value.
- ConnectTimeout option parameter is not applicable when the Oracle client looks the TNS key up in the tnsnames.ora file.

#### Using TNS Names
TNS names are useful if you want to specify a connection description of some clustered DB environment (it is not possible using only key params without TNS). TNS names also hide details of an Oracle connection from a Zabbix environment.
Example of TNS descriptions:

As a TNS descriptor you can use either the TNS key or its value.

```
zbx_tns_example=
    (DESCRIPTION=
        (ADDRESS=
                (PROTOCOL=TCP)
                (HOST=localhost)
                (PORT= 1521)
          )
          (CONNECT_DATA=
                (SERVICE_NAME=xe)
                )
    )
```
In the example above, `zbx_tns_example`  is the TNS key, while the rest — starting from `(DESCRIPTION...` — is the TNS value. Either a key or value can be used in keys params, named sessions, and default options. Usually TNS names are stored in a tnsnames.ora file. Refer to Oracle documentation to find the file location and compose a correct TNS name description.

_Usage Examples_

- TNS key in the plugin's config file (either in the named session or in the default option):
`Plugins.Oracle.Default.Uri=zbx_tns_example`
`Plugins.Oracle.Sessions.MySession.Uri=zbx_tns_example`

- TNS value in the plugin's config file (either in the named session or default option). Must be composed without whitespaces:
`Plugins.Oracle.Default.Uri=(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=xe)))`
`Plugins.Oracle.Sessions.MySession.Uri=(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=xe)))`

- TNS key in the metric key's ConnString part (the service name "XE" will be ignored):
`oracle.ping[zbx_tns_example,ZABBIX_MON,zabbix,xe]`

- TNS value in the metric key (the service name "XE" will be ignored):
`oracle.ping["(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=xe)))",ZABBIX_MON,zabbix]`

The plugin will interpret ConnString as TNS only if the option Plugins.Oracle.ResolveTNS is set to true.  Although some nuances exist (see the chapter [Using key parameters](#Using-key-parameters)).

## Supported keys

**oracle.diskgroups.stats[\<commonParams\>,\<diskgroup\>]** — returns Automatic Storage Management (ASM) disk groups statistics.

*Parameters:*
`diskgroup` (optional) — the name of a diskgroup.

**oracle.diskgroups.discovery[\<commonParams\>]** — returns a list of ASM disk groups in LLD format.

**oracle.archive.info[\<commonParams\>,\<destination\>]** — returns archive logs statistics.
*Parameters:*
`destination` (optional) — the name of the destination.

**oracle.archive.discovery[\<commonParams\>]** — returns a list of archive logs in LLD format.

**oracle.cdb.info[\<commonParams\>,\<database\>]** — returns Container Databases (CDBs) info.
*Parameters:*
`database` (optional) — the name of a database.

**oracle.custom.query[\<commonParams\>,queryName[,args...]]** — returns the result of a custom query.

*Parameters:*
`queryName` (required) — the name of a custom query (must be equal to the name of a *sql* file without an extension).
`args` (optional) — one or more arguments to pass to a query.

**oracle.datafiles.stats[\<commonParams\>]** — returns data files statistics.

**oracle.db.discovery[\<commonParams\>]** — returns a list of databases in LLD format.

**oracle.fra.stats[\<commonParams\>]** — returns Fast Recovery Area (FRA) statistics.

**oracle.instance.info[\<commonParams\>]** — returns instance statistics.

**oracle.pdb.info[\<commonParams\>,\<database\>]** — returns a Pluggable Databases (PDBs) information.
*Parameters:*
`database` (optional) — the name of a database.

**oracle.pdb.discovery[\<commonParams\>]** — returns a list of PDBs in LLD format.

**oracle.pga.stats[\<commonParams\>]** — returns the Program Global Area (PGA) statistics.

**oracle.ping[\<commonParams\>]** — performs a simple ping to check if the connection is alive or not.

*Returns:*
- "1" if a connection is alive.
- "0" if a connection is broken (if there is any error presented including authorization and configuration issues).

**oracle.proc.stats[\<commonParams\>]** — returns processes' statistics.

**oracle.redolog.info[\<commonParams\>]** — returns log file information from the control file.

**oracle.sga.stats[\<commonParams\>]** — returns System Global Area (SGA) statistics.

**oracle.sessions.stats[\<commonParams\>,[lockMaxTime]]** — returns sessions' statistics.

*Parameters:*
`lockMaxTime` (optional) — the maximum duration of the session lock in seconds to count the session as locked prolongedly.
Default: 600 seconds.

**oracle.sys.metrics[\<commonParams\>[,duration]]** — returns a set of the system metric values.

*Parameters:*
Duration (optional) — capturing interval in seconds of system metric values.
Possible values:
60 — long duration (default).
15 — short duration.

**oracle.sys.params[\<commonParams\>]** — returns a set of system parameter values.

**oracle.ts.stats[\<commonParams\>,[tablespace],[type],[conname]]** — returns tablespace statistics.

*Parameters:*
`tablespace` (optional) — a name of a tablespace.
`type` (optional) — a list of a tablespace.
`conname` (optional) — the container name for which information is required.

**oracle.ts.discovery[\<commonParams\>]** — returns a list of tablespaces in the Low-level discovery (LLD) format.

**oracle.user.info[\<commonParams\>[,username]]** — returns user information.

*Parameters:*
Username (optional) — the username for which information is required. Usernames written in lowercase characters are not supported.
Default: the current user.

**oracle.version[\<commonParams\>]** — returns database server information.

## Custom queries

It is possible to extend the functionality of the plugin using user-defined queries. To do it, you should place all your queries in a specified directory in `Plugins.Oracle.CustomQueriesPath` (there is no default path) as it is for *.sql* files.
For example, you can have the following tree:

    /etc/zabbix/oracle/sql/
    ├── long_tx.sql
    ├── payment.sql
    └── top_proc.sql

Then, you should set `Plugins.Oracle.CustomQueriesPath=/etc/zabbix/oracle/sql`.

Finally, when the queries are located in the right place, you can execute them:

    oracle.custom.query[<commonParams>,top_proc]
    oracle.custom.query[<commonParams>,long_tx,600]

You can pass as many parameters to a query as you need.
The syntax for placeholder parameters uses ":#" where "#" is an index number of the parameter.
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

### Developer Section
Golang tests also include integration tests that run when Oracle server is started and available. The default port ```1521``` should be configured for Oracle Server. Other Oracle connection configuration, see in the type testConfig in the file oracle_export_integration_test.go.
To run integration tests, use tag ```-tags integration_tests```. Some negative subtests run long. To skip them, use ```-test.short``` argument: ```go test -test.short``` <pckg>


### Notes
 * Returned data is automatically converted into JSON.
 * Avoid returning JSON directly from queries, as the result will become corrupted when the plugin attempts to convert the result into JSON again.

## Current limitations

* The System Identifier (SID) connection method is not supported.
* Only usernames written in uppercase characters are supported.

## Troubleshooting

The plugin uses logs of Zabbix agent 2. You can increase the debugging level of Zabbix agent 2 if you need more details about the current situation.
The environment variable DPI_DEBUG_LEVEL can be used to selectively turn on the printing of various logging messages from Oracle Database Programming Interface for C (ODPI-C).
See [ODPI-C Debugging](https://oracle.github.io/odpi/doc/user_guide/debugging.html) for details.

## Footnotes
<a name="footnote-1">1</a>: Global timeout is defined in Zabbix agent 2 configuration file zabbix_agent2.conf or zabbix_agent2.win.conf the option "Timeout".

<a name="footnote-2">2</a>: TNS or Transparent Network Substrate is a connection description format used by Oracle databases to manage communication between clients (like SQL*Plus, Oracle SQL Developer, etc.) and Oracle databases. TNS is part of Oracle Net Services, which is responsible for enabling communication between different systems in an Oracle environment.
