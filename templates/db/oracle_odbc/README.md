
# Oracle by ODBC

## Overview

The template is developed to monitor a single DBMS Oracle Database instance with ODBC and can monitor CDB or non-CDB installations.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Oracle Database 12c2, 18c, 19c

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Oracle DB user for monitoring:

    In CDB installations it is possible to monitor tablespaces from CDB _(container database)_ and all PDBs _(pluggable databases)_. In such case, a common user is needed with the correct rights:

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
    GRANT SELECT ON v_$restore_point TO c##zabbix_mon;
    GRANT SELECT ON v_$process TO c##zabbix_mon;
    GRANT SELECT ON v_$datafile TO c##zabbix_mon;
    GRANT SELECT ON v_$pgastat TO c##zabbix_mon;
    GRANT SELECT ON v_$sgastat TO c##zabbix_mon;
    GRANT SELECT ON v_$log TO c##zabbix_mon;
    GRANT SELECT ON v_$archive_dest TO c##zabbix_mon;
    GRANT SELECT ON v_$asm_diskgroup TO c##zabbix_mon;
    GRANT SELECT ON sys.dba_data_files TO c##zabbix_mon;
    GRANT SELECT ON DBA_TABLESPACES TO c##zabbix_mon;
    GRANT SELECT ON DBA_TABLESPACE_USAGE_METRICS TO c##zabbix_mon;
    GRANT SELECT ON DBA_USERS TO c##zabbix_mon;
    ```
    This is needed because the template uses ```CDB_*``` views to monitor tablespaces from CDB and different PDBs, and, therefore, the monitoring user needs access to the container data objects on all PDBs.

    However, if you wish to monitor only a single PDB or non-CDB instance, a local user is sufficient:
    
    ```
    CREATE USER zabbix_mon IDENTIFIED BY <PASSWORD>;
    -- Grant access to the zabbix_mon user.
    GRANT CONNECT, CREATE SESSION TO zabbix_mon;
    GRANT SELECT_CATALOG_ROLE to zabbix_mon;
    GRANT SELECT ON v_$instance TO zabbix_mon;
    GRANT SELECT ON v_$database TO zabbix_mon;
    GRANT SELECT ON v_$sysmetric TO zabbix_mon;
    GRANT SELECT ON v_$system_parameter TO zabbix_mon;
    GRANT SELECT ON v_$session TO zabbix_mon;
    GRANT SELECT ON v_$recovery_file_dest TO zabbix_mon;
    GRANT SELECT ON v_$active_session_history TO zabbix_mon;
    GRANT SELECT ON v_$osstat TO zabbix_mon;
    GRANT SELECT ON v_$restore_point TO zabbix_mon;
    GRANT SELECT ON v_$process TO zabbix_mon;
    GRANT SELECT ON v_$datafile TO zabbix_mon;
    GRANT SELECT ON v_$pgastat TO zabbix_mon;
    GRANT SELECT ON v_$sgastat TO zabbix_mon;
    GRANT SELECT ON v_$log TO zabbix_mon;
    GRANT SELECT ON v_$archive_dest TO zabbix_mon;
    GRANT SELECT ON v_$asm_diskgroup TO zabbix_mon;
    GRANT SELECT ON sys.dba_data_files TO zabbix_mon;
    GRANT SELECT ON DBA_TABLESPACES TO zabbix_mon;
    GRANT SELECT ON DBA_TABLESPACE_USAGE_METRICS TO zabbix_mon;
    GRANT SELECT ON DBA_USERS TO zabbix_mon;
    ```
    **Note! Ensure that ODBC connects to Oracle with session parameter NLS_NUMERIC_CHARACTERS= '.,'. It is important for displaying the float numbers in Zabbix correctly.**

2. Install the ODBC driver on Zabbix server or Zabbix proxy.
  See the [Oracle documentation](https://www.oracle.com/database/technologies/releasenote-odbc-ic.html) for instructions.

3. Configure Zabbix server or Zabbix proxy for the usage of Oracle Environment:

    This step is required only when:
    
    * installing Oracle Instant Client with .rpm packages with version < 19.3 (if Instant Client is the only Oracle Software installed on Zabbix server or Zabbix proxy);
    
    * installing Oracle Instant Client manually with .zip files.
  
    There are multiple ways of achieving this:
    
      1. Using `LDCONFIG` utility **(recommended option)**:
  
          To update the runtime link path, it is recommended to use the ```LDCONFIG``` utility, for example:
    
          ```
          # sh -c "echo /opt/oracle/instantclient_19_18 > /etc/ld.so.conf.d/oracle-instantclient.conf"
          # ldconfig
          ```
  
      2. Using application configuration file:
  
          An alternative solution is to export the required variables by editing or adding a new application configuration file:
    
           * ```/etc/sysconfig/zabbix-server # for server```
    
           * ```/etc/sysconfig/zabbix-proxy # for proxy```
    
          And then, adding:
        
          ```
          # Oracle Instant Client library
          LD_LIBRARY_PATH=/opt/oracle/instantclient_19_18:$LD_LIBRARY_PATH
          export LD_LIBRARY_PATH
          ```
    
    Keep in mind that the library paths will vary depending on your installation.

    This is a minimal configuration example. Depending on the Oracle Instant Client version, required functionality and host operating system, a different set of additional packages might need to be installed.
    For more detailed configuration instructions, see the [official Oracle Instant Client installation instructions for Linux](https://www.oracle.com/database/technologies/instant-client/linux-x86-64-downloads.html).

4. Restart Zabbix server or Zabbix proxy.

5. Set the username and password in the host macros ({$ORACLE.USER} and {$ORACLE.PASSWORD}).

6. Set the {$ORACLE.DRIVER} and {$ORACLE.SERVICE} in the host macros.
  
    * ```{$ORACLE.DRIVER}``` is a path to the driver location in OS. The ODBC driver file should be found in __Instant Client__ directory and named ```libsqora.so.XX.Y```.
    
    * ```{$ORACLE.SERVICE}``` is a service name to which the host will connect to. The value in this macro is important as it determines if the connection is established to a non-CDB, CDB or PDB. If you wish to monitor tablespaces of all PDBs, you will need to set a service name that points to the CDB.
      Active service names can be seen from the instance running Oracle Database with ```lsnrctl status```.
      
    **Note! Make sure that the user created in step #1 is present on the specified service.**

    The "Service's TCP port state" item uses ```{HOST.CONN}``` and ```{$ORACLE.PORT}``` macros to check the availability of the listener.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ORACLE.DRIVER}|<p>The Oracle driver path. For example: `/usr/lib/oracle/21/client64/lib/libsqora.so.21.1`</p>|`<Put path to oracle driver here>`|
|{$ORACLE.SERVICE}|<p>Oracle Service Name.</p>|`<Put oracle service name here>`|
|{$ORACLE.USER}|<p>Oracle username.</p>|`<Put your username here>`|
|{$ORACLE.PASSWORD}|<p>The Oracle user's password.</p>|`<Put your password here>`|
|{$ORACLE.PORT}|<p>Oracle DB TCP port.</p>|`1521`|
|{$ORACLE.DBNAME.MATCHES}|<p>This macro is used in database discovery. It can be overridden on host level or its linked template level.</p>|`.*`|
|{$ORACLE.DBNAME.NOT_MATCHES}|<p>This macro is used in database discovery. It can be overridden on host level or its linked template level.</p>|`PDB\$SEED`|
|{$ORACLE.TABLESPACE.CONTAINER.MATCHES}|<p>This macro is used in tablespace discovery. It can be overridden on host level or its linked template level.</p>|`.*`|
|{$ORACLE.TABLESPACE.CONTAINER.NOT_MATCHES}|<p>This macro is used in tablespace discovery. It can be overridden on host level or its linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$ORACLE.TABLESPACE.NAME.MATCHES}|<p>This macro is used in tablespace discovery. It can be overridden on host level or its linked template level.</p>|`.*`|
|{$ORACLE.TABLESPACE.NAME.NOT_MATCHES}|<p>This macro is used in tablespace discovery. It can be overridden on host level or its linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$ORACLE.TBS.USED.PCT.FROM.MAX.WARN}|<p>Warning severity alert threshold for the maximum percentage of tablespace usage from maximum tablespace size (used bytes/max bytes) for a trigger expression.</p>|`90`|
|{$ORACLE.TBS.USED.PCT.FROM.MAX.HIGH}|<p>High severity alert threshold for the maximum percentage of tablespace usage from maximum tablespace size (used bytes/max bytes) for a trigger expression.</p>|`95`|
|{$ORACLE.TBS.USED.PCT.MAX.WARN}|<p>Warning severity alert threshold for the maximum percentage of tablespace usage (used bytes/allocated bytes) for a trigger expression.</p>|`90`|
|{$ORACLE.TBS.USED.PCT.MAX.HIGH}|<p>High severity alert threshold for the maximum percentage of tablespace usage (used bytes/allocated bytes) for a trigger expression.</p>|`95`|
|{$ORACLE.TBS.UTIL.PCT.MAX.WARN}|<p>Warning severity alert threshold for the maximum percentage of tablespace utilization (allocated bytes/max bytes) for a trigger expression.</p>|`80`|
|{$ORACLE.TBS.UTIL.PCT.MAX.HIGH}|<p>High severity alert threshold for the maximum percentage of tablespace utilization (allocated bytes/max bytes) for a trigger expression.</p>|`90`|
|{$ORACLE.PROCESSES.MAX.WARN}|<p>Alert threshold for the maximum percentage of active processes for a trigger expression.</p>|`80`|
|{$ORACLE.SESSIONS.MAX.WARN}|<p>Alert threshold for the maximum percentage of active sessions for a trigger expression.</p>|`80`|
|{$ORACLE.DB.FILE.MAX.WARN}|<p>The maximum percentage of used database files for a trigger expression.</p>|`80`|
|{$ORACLE.PGA.USE.MAX.WARN}|<p>Alert threshold for the maximum percentage of the Program Global Area (PGA) usage for a trigger expression.</p>|`90`|
|{$ORACLE.SESSIONS.LOCK.MAX.WARN}|<p>Alert threshold for the maximum percentage of locked sessions for a trigger expression.</p>|`20`|
|{$ORACLE.SESSION.LOCK.MAX.TIME}|<p>The maximum duration of the session lock in seconds to count the session as a prolongedly locked query.</p>|`600`|
|{$ORACLE.SESSION.LONG.LOCK.MAX.WARN}|<p>Alert threshold for the maximum number of the prolongedly locked sessions for a trigger expression.</p>|`3`|
|{$ORACLE.CONCURRENCY.MAX.WARN}|<p>The maximum percentage of sessions concurrency usage for a trigger expression.</p>|`80`|
|{$ORACLE.REDO.MIN.WARN}|<p>Alert threshold for the minimum number of REDO logs for a trigger expression.</p>|`3`|
|{$ORACLE.SHARED.FREE.MIN.WARN}|<p>Alert threshold for the minimum percentage of free shared pool for a trigger expression.</p>|`5`|
|{$ORACLE.EXPIRE.PASSWORD.MIN.WARN}|<p>The number of warning days before the password expires for a trigger expression.</p>|`7`|
|{$ORACLE.ASM.USED.PCT.MAX.WARN}|<p>The maximum percentage of used ASM disk group for a warning trigger expression.</p>|`90`|
|{$ORACLE.ASM.USED.PCT.MAX.HIGH}|<p>The maximum percentage of used Automatic Storage Management (ASM) disk group for a high trigger expression.</p>|`95`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Oracle: Service's TCP port state|<p>It checks the availability of Oracle on the TCP port.</p>|Zabbix agent|net.tcp.service[tcp,{HOST.CONN},{$ORACLE.PORT}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Oracle: Number of LISTENER processes|<p>The number of running LISTENER processes.</p>|Zabbix agent|proc.num[,,,"tnslsnr LISTENER"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Oracle: Get instance state|<p>The item gets its state of the current instance.</p>|Database monitor|db.odbc.get[get_instance_state,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]|
|Oracle: Version|<p>The Oracle Server version.</p>|Dependent item|oracle.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..VERSION.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Oracle: Uptime|<p>The Oracle instance uptime expressed in seconds.</p>|Dependent item|oracle.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..UPTIME.first()`</p></li></ul>|
|Oracle: Instance status|<p>The status of the instance.</p>|Dependent item|oracle.instance_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..STATUS.first()`</p></li></ul>|
|Oracle: Archiver state|<p>The status of automatic archiving.</p>|Dependent item|oracle.archiver_state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..ARCHIVER.first()`</p></li></ul>|
|Oracle: Instance name|<p>The name of an instance.</p>|Dependent item|oracle.instance_name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..INSTANCE_NAME.first()`</p></li></ul>|
|Oracle: Instance hostname|<p>The name of the host machine.</p>|Dependent item|oracle.instance_hostname<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..HOST_NAME.first()`</p></li></ul>|
|Oracle: Instance role|<p>It indicates whether the instance is an active instance or an inactive secondary instance.</p>|Dependent item|oracle.instance.role<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..INSTANCE_ROLE.first()`</p></li></ul>|
|Oracle: Get system metrics|<p>The item gets the values of the system metrics.</p>|Database monitor|db.odbc.get[get_system_metrics,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]|
|Oracle: Sessions limit|<p>The user and system sessions.</p>|Dependent item|oracle.session_limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYSPARAM::Sessions')].VALUE.first()`</p></li></ul>|
|Oracle: Datafiles limit|<p>The maximum allowable number of datafiles.</p>|Dependent item|oracle.db_files_limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYSPARAM::Db_Files')].VALUE.first()`</p></li></ul>|
|Oracle: Processes limit|<p>The maximum number of user processes.</p>|Dependent item|oracle.processes_limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYSPARAM::Processes')].VALUE.first()`</p></li></ul>|
|Oracle: Number of processes||Dependent item|oracle.processes_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='PROC::Procnum')].VALUE.first()`</p></li></ul>|
|Oracle: Datafiles count|<p>The current number of datafiles.</p>|Dependent item|oracle.db_files_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='DATAFILE::Count')].VALUE.first()`</p></li></ul>|
|Oracle: Buffer cache hit ratio|<p>The ratio of buffer cache hits ((LogRead - PhyRead)/LogRead).</p>|Dependent item|oracle.buffer_cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Buffer Cache Hit Ratio')].VALUE.first()`</p></li></ul>|
|Oracle: Cursor cache hit ratio|<p>The ratio of cursor cache hits (CursorCacheHit/SoftParse).</p>|Dependent item|oracle.cursor_cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Cursor Cache Hit Ratio')].VALUE.first()`</p></li></ul>|
|Oracle: Library cache hit ratio|<p>The ratio of library cache hits (Hits/Pins).</p>|Dependent item|oracle.library_cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Library Cache Hit Ratio')].VALUE.first()`</p></li></ul>|
|Oracle: Shared pool free %|<p>Free memory of a shared pool expressed in %.</p>|Dependent item|oracle.shared_pool_free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Shared Pool Free %')].VALUE.first()`</p></li></ul>|
|Oracle: Physical reads per second|<p>Reads per second.</p>|Dependent item|oracle.physical_reads_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Physical Reads Per Sec')].VALUE.first()`</p></li></ul>|
|Oracle: Physical writes per second|<p>Writes per second.</p>|Dependent item|oracle.physical_writes_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Physical Writes Per Sec')].VALUE.first()`</p></li></ul>|
|Oracle: Physical reads bytes per second|<p>Read bytes per second.</p>|Dependent item|oracle.physical_read_bytes_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: Physical writes bytes per second|<p>Write bytes per second.</p>|Dependent item|oracle.physical_write_bytes_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: Enqueue timeouts per second|<p>Enqueue timeouts per second.</p>|Dependent item|oracle.enqueue_timeouts_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: GC CR block received per second|<p>The global cache (GC) and the consistent read (CR) block received per second.</p>|Dependent item|oracle.gc_cr_block_received_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: Global cache blocks corrupted|<p>The number of blocks that encountered corruption or checksum failure during the interconnect.</p>|Dependent item|oracle.cache_blocks_corrupt<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: Global cache blocks lost|<p>The number of lost global cache blocks.</p>|Dependent item|oracle.cache_blocks_lost<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: Logons per second|<p>The number of logon attempts.</p>|Dependent item|oracle.logons_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Logons Per Sec')].VALUE.first()`</p></li></ul>|
|Oracle: Average active sessions|<p>The average active sessions at a point in time. The number of sessions that are either working or waiting.</p>|Dependent item|oracle.active_sessions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Average Active Sessions')].VALUE.first()`</p></li></ul>|
|Oracle: Session count|<p>The count of sessions.</p>|Dependent item|oracle.session_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SESSION::Total')].VALUE.first()`</p></li></ul>|
|Oracle: Active user sessions|<p>The number of active user sessions.</p>|Dependent item|oracle.session_active_user<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SESSION::Active User')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: Active background sessions|<p>The number of active background sessions.</p>|Dependent item|oracle.session_active_background<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SESSION::Active Background')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: Inactive user sessions|<p>The number of inactive user sessions.</p>|Dependent item|oracle.session_inactive_user<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SESSION::Inactive User')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: Sessions lock rate|<p>The percentage of locked sessions. Locks are mechanisms that prevent destructive interaction between transactions accessing the same resource — either user objects, such as tables and rows or system objects not visible to users, such as shared data structures in memory and data dictionary rows.</p>|Dependent item|oracle.session_lock_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SESSION::Lock rate')].VALUE.first()`</p></li></ul>|
|Oracle: Sessions locked over {$ORACLE.SESSION.LOCK.MAX.TIME}s|<p>The count of the prolongedly locked sessions. (You can change the duration of maximum session lock in seconds for a query by {$ORACLE.SESSION.LOCK.MAX.TIME} macro. Default is 600 sec).</p>|Dependent item|oracle.session_long_time_locked<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SESSION::Long time locked')].VALUE.first()`</p></li></ul>|
|Oracle: Sessions concurrency|<p>The percentage of concurrency. Concurrency is a DB behavior when different transactions request to change the same resource. In the case of modifying data transactions, it sequentially temporarily blocks the right to change the data, the rest of the transactions are waiting for the access. In the case when the access for the resource is locked for a long time, then the concurrency grows (like the transaction queue) and this often has an extremely negative impact on the performance. A high contention value does not indicate the root cause of the problem but is a signal to search for it.</p>|Dependent item|oracle.session_concurrency_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SESSION::Concurrency rate')].VALUE.first()`</p></li></ul>|
|Oracle: User '{$ORACLE.USER}' expire password|<p>The number of days before the password of Zabbix account expires.</p>|Dependent item|oracle.user_expire_password<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='USER::Expire password')].VALUE.first()`</p></li></ul>|
|Oracle: Active serial sessions|<p>The number of active serial sessions.</p>|Dependent item|oracle.active_serial_sessions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Active Serial Sessions')].VALUE.first()`</p></li></ul>|
|Oracle: Active parallel sessions|<p>The number of active parallel sessions.</p>|Dependent item|oracle.active_parallel_sessions<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: Long table scans per second|<p>The number of long table scans per second. A table is considered 'long' if the table is not cached and if its high-water mark is greater than five blocks.</p>|Dependent item|oracle.long_table_scans_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: SQL service response time|<p>The Structured Query Language (SQL) service response time expressed in seconds.</p>|Dependent item|oracle.service_response_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Oracle: User rollbacks per second|<p>The number of times that users manually issue the ROLLBACK statement or an error occurred during the users' transactions.</p>|Dependent item|oracle.user_rollbacks_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::User Rollbacks Per Sec')].VALUE.first()`</p></li></ul>|
|Oracle: Total sorts per user call|<p>The total sorts per user call.</p>|Dependent item|oracle.sorts_per_user_call<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: Rows per sort|<p>The average number of rows per sort for all types of sorts performed.</p>|Dependent item|oracle.rows_per_sort<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Rows Per Sort')].VALUE.first()`</p></li></ul>|
|Oracle: Disk sort per second|<p>The number of sorts going to disk per second.</p>|Dependent item|oracle.disk_sorts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Disk Sort Per Sec')].VALUE.first()`</p></li></ul>|
|Oracle: Memory sorts ratio|<p>The percentage of sorts (from ORDER BY clauses or index building) that are done to disk vs in-memory.</p>|Dependent item|oracle.memory_sorts_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Memory Sorts Ratio')].VALUE.first()`</p></li></ul>|
|Oracle: Database wait time ratio|<p>Wait time - the time that the server process spends waiting for available shared resources to be released by other server processes, such as latches, locks, data buffers, etc.</p>|Dependent item|oracle.database_wait_time_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: Database CPU time ratio|<p>It is calculated by dividing the total CPU (used by the database) by the Oracle time model statistic DB time.</p>|Dependent item|oracle.database_cpu_time_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Database CPU Time Ratio')].VALUE.first()`</p></li></ul>|
|Oracle: Temp space used|<p>Used temporary space.</p>|Dependent item|oracle.temp_space_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SYS::Temp Space Used')].VALUE.first()`</p></li></ul>|
|Oracle: PGA, Total inuse|<p>It indicates how much the Program Global Area (PGA) memory is currently consumed by work areas. This number can be used to determine how much memory is consumed by other consumers of the PGA memory (for example, PL/SQL or Java).</p>|Dependent item|oracle.total_pga_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='PGA::Total Pga Inuse')].VALUE.first()`</p></li></ul>|
|Oracle: PGA, Aggregate target parameter|<p>The current value of the PGA_AGGREGATE_TARGET initialization parameter. If this parameter is not set, then its value is 0 and automatic management of the PGA memory is disabled.</p>|Dependent item|oracle.pga_target<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: PGA, Total allocated|<p>The current amount of the PGA memory allocated by the instance. The Oracle Database attempts to keep this number below the value of the PGA_AGGREGATE_TARGET initialization parameter. However, it is possible for the PGA allocated to exceed that value by a small percentage and for a short period of time when the work area workload is increasing very rapidly or when PGA_AGGREGATE_TARGET is set to a small value.</p>|Dependent item|oracle.total_pga_allocated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='PGA::Total Pga Allocated')].VALUE.first()`</p></li></ul>|
|Oracle: PGA, Total freeable|<p>The number of bytes of the PGA memory in all processes that could be freed back to the operating system.</p>|Dependent item|oracle.total_pga_freeable<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Oracle: PGA, Global memory bound|<p>The maximum size of work area executed in automatic mode.</p>|Dependent item|oracle.pga_global_bound<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='PGA::Global Memory Bound')].VALUE.first()`</p></li></ul>|
|Oracle: FRA, Space limit|<p>The maximum amount of disk space (in bytes) that the database can use for the Fast Recovery Area (FRA).</p>|Dependent item|oracle.fra_space_limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='FRA::Space Limit')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: FRA, Used space|<p>The amount of disk space (in bytes) used by FRA files created in the current and all the previous FRAs.</p>|Dependent item|oracle.fra_space_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='FRA::Space Used')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: FRA, Space reclaimable|<p>The total amount of disk space (in bytes) that can be created by deleting obsolete, redundant, and other low priority files from the FRA.</p>|Dependent item|oracle.fra_space_reclaimable<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='FRA::Space Reclaimable')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: FRA, Number of files|<p>The number of files in the FRA.</p>|Dependent item|oracle.fra_number_of_files<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='FRA::Number Of Files')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: FRA, Usable space in %||Dependent item|oracle.fra_usable_pct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='FRA::Usable Pct')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: FRA, Number of restore points||Dependent item|oracle.fra_restore_point<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='FRA::Restore Point')].VALUE.first()`</p></li></ul>|
|Oracle: SGA, java pool|<p>The memory is allocated from the Java pool.</p>|Dependent item|oracle.sga_java_pool<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SGA::Java Pool')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: SGA, large pool|<p>The memory is allocated from a large pool.</p>|Dependent item|oracle.sga_large_pool<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SGA::Large Pool')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: SGA, shared pool|<p>The memory is allocated from a shared pool.</p>|Dependent item|oracle.sga_shared_pool<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SGA::Shared Pool')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: SGA, log buffer|<p>The number of bytes allocated for the redo log buffer.</p>|Dependent item|oracle.sga_log_buffer<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SGA::Log_Buffer')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: SGA, fixed|<p>The fixed System Global Area (SGA) is an internal housekeeping area.</p>|Dependent item|oracle.sga_fixed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SGA::Fixed_Sga')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: SGA, buffer cache|<p>The size of standard block cache.</p>|Dependent item|oracle.sga_buffer_cache<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='SGA::Buffer_Cache')].VALUE.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Oracle: Redo logs available to switch|<p>The number of inactive/unused redo logs available for log switching.</p>|Dependent item|oracle.redo_logs_available<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.METRIC=='REDO::Available')].VALUE.first()`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Oracle: Port {$ORACLE.PORT} is unavailable|<p>The TCP port of the Oracle Server service is currently unavailable.</p>|`max(/Oracle by ODBC/net.tcp.service[tcp,{HOST.CONN},{$ORACLE.PORT}],#3)=0  and max(/Oracle by ODBC/proc.num[,,,"tnslsnr LISTENER"],#3)>0`|Disaster||
|Oracle: LISTENER process is not running||`max(/Oracle by ODBC/proc.num[,,,"tnslsnr LISTENER"],#3)=0`|Disaster||
|Oracle: Version has changed|<p>The Oracle DB version has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by ODBC/oracle.version,#1)<>last(/Oracle by ODBC/oracle.version,#2) and length(last(/Oracle by ODBC/oracle.version))>0`|Info|**Manual close**: Yes|
|Oracle: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Oracle by ODBC/oracle.uptime)<10m`|Info|**Manual close**: Yes|
|Oracle: Failed to fetch info data|<p>Zabbix has not received any data for the items for the last 5 minutes. The database might be unavailable for connecting.</p>|`nodata(/Oracle by ODBC/oracle.uptime,5m)=1`|Warning|**Depends on**:<br><ul><li>Oracle: Port {$ORACLE.PORT} is unavailable</li></ul>|
|Oracle: Instance name has changed|<p>The Oracle DB instance has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by ODBC/oracle.instance_name,#1)<>last(/Oracle by ODBC/oracle.instance_name,#2) and length(last(/Oracle by ODBC/oracle.instance_name))>0`|Info|**Manual close**: Yes|
|Oracle: Instance hostname has changed|<p>Oracle DB Instance hostname has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by ODBC/oracle.instance_hostname,#1)<>last(/Oracle by ODBC/oracle.instance_hostname,#2) and length(last(/Oracle by ODBC/oracle.instance_hostname))>0`|Info|**Manual close**: Yes|
|Oracle: Too many active processes|<p>Active processes are using more than {$ORACLE.PROCESSES.MAX.WARN}% of the available number of processes.</p>|`min(/Oracle by ODBC/oracle.processes_count,5m) * 100 / last(/Oracle by ODBC/oracle.processes_limit) > {$ORACLE.PROCESSES.MAX.WARN}`|Warning||
|Oracle: Too many database files|<p>The number of datafiles is higher than {$ORACLE.DB.FILE.MAX.WARN}% of the available datafiles limit.</p>|`min(/Oracle by ODBC/oracle.db_files_count,5m) * 100 / last(/Oracle by ODBC/oracle.db_files_limit) > {$ORACLE.DB.FILE.MAX.WARN}`|Warning||
|Oracle: Shared pool free is too low|<p>The free memory percent of the shared pool has been less than {$ORACLE.SHARED.FREE.MIN.WARN}% for the last 5 minutes.</p>|`max(/Oracle by ODBC/oracle.shared_pool_free,5m)<{$ORACLE.SHARED.FREE.MIN.WARN}`|Warning||
|Oracle: Too many active sessions|<p>Active sessions are using more than {$ORACLE.SESSIONS.MAX.WARN}% of the available sessions.</p>|`min(/Oracle by ODBC/oracle.session_count,5m) * 100 / last(/Oracle by ODBC/oracle.session_limit) > {$ORACLE.SESSIONS.MAX.WARN}`|Warning||
|Oracle: Too many locked sessions|<p>The number of locked sessions exceeds {$ORACLE.SESSIONS.LOCK.MAX.WARN}% of the running sessions.</p>|`min(/Oracle by ODBC/oracle.session_lock_rate,5m) > {$ORACLE.SESSIONS.LOCK.MAX.WARN}`|Warning||
|Oracle: Too many sessions locked|<p>The number of locked sessions exceeding {$ORACLE.SESSION.LOCK.MAX.TIME} seconds is too high. Long-term locks can negatively affect the database performance. Therefore, if they are detected, you should first find the most difficult queries from the database point of view and then analyze possible resource leaks.</p>|`min(/Oracle by ODBC/oracle.session_long_time_locked,5m) > {$ORACLE.SESSION.LONG.LOCK.MAX.WARN}`|Warning||
|Oracle: Too high database concurrency|<p>The concurrency rate exceeds {$ORACLE.CONCURRENCY.MAX.WARN}%. A high contention value does not indicate the root cause of the problem, but it is a signal to search for it. In the case of high competition, the analysis of resource consumption should be carried out. Which are the most "heavy" queries made in the database? Possibly, also session tracing. All this will help to determine the root cause and possible optimization points both in the database configuration and in the logic of building queries of the application itself.</p>|`min(/Oracle by ODBC/oracle.session_concurrency_rate,5m) > {$ORACLE.CONCURRENCY.MAX.WARN}`|Warning||
|Oracle: Zabbix account will expire soon|<p>The password for Zabbix user in the database expires soon.</p>|`last(/Oracle by ODBC/oracle.user_expire_password)  < {$ORACLE.EXPIRE.PASSWORD.MIN.WARN}`|Warning||
|Oracle: Total PGA inuse is too high|<p>The total PGA currently consumed by work areas is more than {$ORACLE.PGA.USE.MAX.WARN}% of PGA_AGGREGATE_TARGET.</p>|`min(/Oracle by ODBC/oracle.total_pga_used,5m) * 100 / last(/Oracle by ODBC/oracle.pga_target) > {$ORACLE.PGA.USE.MAX.WARN}`|Warning||
|Oracle: Number of REDO logs available for switching is too low|<p>The number of inactive/unused REDOs available for log switching is low (database down risk).</p>|`max(/Oracle by ODBC/oracle.redo_logs_available,5m) < {$ORACLE.REDO.MIN.WARN}`|Warning||

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Scanning databases in the database management system (DBMS).</p>|Database monitor|db.odbc.discovery[db_list,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Oracle Database '{#DBNAME}': Get CDB and No-CDB info|<p>It gets the information about the container database (CDB) and non-CDB database on an instance.</p>|Database monitor|db.odbc.get[get_cdb_{#DBNAME}_info,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Oracle Database '{#DBNAME}': Open status|<p>1 - 'MOUNTED';</p><p>2 - 'READ WRITE';</p><p>3 - 'READ ONLY';</p><p>4 - 'READ ONLY WITH APPLY' (a physical standby database is open in real-time query mode).</p>|Dependent item|oracle.db_open_mode["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.OPEN_MODE`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Oracle Database '{#DBNAME}': Role|<p>The current role of the database where:</p><p>1 - 'SNAPSHOT STANDBY';</p><p>2 - 'LOGICAL STANDBY';</p><p>3 - 'PHYSICAL STANDBY';</p><p>4 - 'PRIMARY ';</p><p>5 - 'FAR SYNC'.</p>|Dependent item|oracle.db_role["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ROLE`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Oracle Database '{#DBNAME}': Log mode|<p>The archive log mode where:</p><p>0 - 'NOARCHIVELOG';</p><p>1 - 'ARCHIVELOG';</p><p>2 - 'MANUAL'.</p>|Dependent item|oracle.db_log_mode["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LOG_MODE`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Oracle Database '{#DBNAME}': Force logging|<p>It indicates whether the database is under force logging mode 'YES' or 'NO'.</p>|Dependent item|oracle.db_force_logging["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.FORCE_LOGGING`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|

### Trigger prototypes for Database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Oracle Database '{#DBNAME}': Open status in mount mode|<p>The Oracle DB is in a mounted state.</p>|`last(/Oracle by ODBC/oracle.db_open_mode["{#DBNAME}"])=1`|Warning||
|Oracle Database '{#DBNAME}': Open status has changed|<p>The Oracle DB open status has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by ODBC/oracle.db_open_mode["{#DBNAME}"],#1)<>last(/Oracle by ODBC/oracle.db_open_mode["{#DBNAME}"],#2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Oracle Database '{#DBNAME}': Open status in mount mode</li></ul>|
|Oracle Database '{#DBNAME}': Role has changed|<p>The Oracle DB role has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by ODBC/oracle.db_role["{#DBNAME}"],#1)<>last(/Oracle by ODBC/oracle.db_role["{#DBNAME}"],#2)`|Info|**Manual close**: Yes|
|Oracle Database '{#DBNAME}': Force logging is deactivated for DB with active Archivelog|<p>Force Logging mode - it is very important metric for Databases in 'ARCHIVELOG'. This feature allows to forcibly write all the transactions to the REDO.</p>|`last(/Oracle by ODBC/oracle.db_force_logging["{#DBNAME}"]) = 0 and last(/Oracle by ODBC/oracle.db_log_mode["{#DBNAME}"]) = 1`|Warning||

### LLD rule PDB discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PDB discovery|<p>Scanning a pluggable database (PDB) in DBMS.</p>|Database monitor|db.odbc.discovery[pdb_list,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]|

### Item prototypes for PDB discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Oracle Database '{#DBNAME}': Get PDB info|<p>It gets the information about the PDB database on an instance.</p>|Database monitor|db.odbc.get[get_pdb_{#DBNAME}_info,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Oracle Database '{#DBNAME}': Open status|<p>1 - 'MOUNTED';</p><p>2 - 'READ WRITE';</p><p>3 - 'READ ONLY';</p><p>4 - 'READ ONLY WITH APPLY' (a physical standby database is open in real-time query mode).</p>|Dependent item|oracle.pdb_open_mode["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.OPEN_MODE`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|

### Trigger prototypes for PDB discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Oracle Database '{#DBNAME}': Open status in mount mode|<p>The Oracle DB is in a mounted state.</p>|`last(/Oracle by ODBC/oracle.pdb_open_mode["{#DBNAME}"])=1`|Warning||
|Oracle Database '{#DBNAME}': Open status has changed|<p>The Oracle DB open status has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by ODBC/oracle.pdb_open_mode["{#DBNAME}"],#1)<>last(/Oracle by ODBC/oracle.pdb_open_mode["{#DBNAME}"],#2)`|Info|**Manual close**: Yes|

### LLD rule Tablespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tablespace discovery|<p>Scanning tablespaces in DBMS.</p>|Database monitor|db.odbc.discovery[tbsname,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]|

### Item prototypes for Tablespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Get tablespaces stats|<p>It gets the statistics of the tablespace.</p>|Database monitor|db.odbc.get[get_{#CON_NAME}_tablespace_{#TABLESPACE}_stats,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace allocated, bytes|<p>Currently allocated bytes for the tablespace (sum of the current size of datafiles).</p>|Dependent item|oracle.tbs_alloc_bytes["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.FILE_BYTES`</p></li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace MAX size, bytes|<p>The maximum size of the tablespace.</p>|Dependent item|oracle.tbs_max_bytes["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MAX_BYTES`</p></li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace used, bytes|<p>Currently used bytes for the tablespace (current size of datafiles - the free space).</p>|Dependent item|oracle.tbs_used_bytes["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.USED_BYTES`</p></li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace free, bytes|<p>Free bytes of the allocated space.</p>|Dependent item|oracle.tbs_free_bytes["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.FREE_BYTES`</p></li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace allocated, percent|<p>Allocated bytes/max bytes*100.</p>|Dependent item|oracle.tbs_used_pct["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.USED_PCT_MAX`</p></li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage, percent|<p>Used bytes/allocated bytes*100.</p>|Dependent item|oracle.tbs_used_file_pct["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.USED_FILE_PCT`</p></li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage from MAX, percent|<p>Used bytes/max bytes*100.</p>|Dependent item|oracle.tbs_used_from_max_pct["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.USED_FROM_MAX_PCT`</p></li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Open status|<p>The tablespace status where:</p><p>1 - 'ONLINE';</p><p>2 - 'OFFLINE';</p><p>3 - 'READ ONLY'.</p>|Dependent item|oracle.tbs_status["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.STATUS`</p></li></ul>|

### Trigger prototypes for Tablespace discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace utilization is too high||`min(/Oracle by ODBC/oracle.tbs_used_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace utilization is too high</li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace utilization is too high||`min(/Oracle by ODBC/oracle.tbs_used_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.UTIL.PCT.MAX.HIGH}`|High||
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage is too high||`min(/Oracle by ODBC/oracle.tbs_used_file_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage is too high</li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage is too high||`min(/Oracle by ODBC/oracle.tbs_used_file_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.HIGH}`|High||
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage from MAX is too high||`min(/Oracle by ODBC/oracle.tbs_used_from_max_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.FROM.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage from MAX is too high</li></ul>|
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage from MAX is too high||`min(/Oracle by ODBC/oracle.tbs_used_from_max_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.FROM.MAX.HIGH}`|High||
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace is OFFLINE|<p>The tablespace is in the offline state.</p>|`last(/Oracle by ODBC/oracle.tbs_status["{#CON_NAME}","{#TABLESPACE}"])=2`|Warning||
|Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace status has changed|<p>Oracle tablespace status has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by ODBC/oracle.tbs_status["{#CON_NAME}","{#TABLESPACE}"],#1)<>last(/Oracle by ODBC/oracle.tbs_status["{#CON_NAME}","{#TABLESPACE}"],#2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Oracle '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace is OFFLINE</li></ul>|

### LLD rule Archive log discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Archive log discovery|<p>Destinations of the log archive.</p>|Database monitor|db.odbc.discovery[archivelog,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]|

### Item prototypes for Archive log discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Archivelog '{#DEST_NAME}': Get archive log info|<p>It gets the archivelog statistics.</p>|Database monitor|db.odbc.get[get_archivelog_{#DEST_NAME}_stat,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Archivelog '{#DEST_NAME}': Error|<p>It displays the error message.</p>|Dependent item|oracle.archivelog_error["{#DEST_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ERROR`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Archivelog '{#DEST_NAME}': Last sequence|<p>It identifies the sequence number of the last archived redo log to be archived.</p>|Dependent item|oracle.archivelog_log_sequence["{#DEST_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LOG_SEQUENCE`</p></li></ul>|
|Archivelog '{#DEST_NAME}': Status|<p>It identifies the current status of the destination where:</p><p>1 - 'VALID';</p><p>2 - 'DEFERRED';</p><p>3 - 'ERROR';</p><p>0 - 'UNKNOWN'.</p>|Dependent item|oracle.archivelog_log_status["{#DEST_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.STATUS`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Archive log discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Archivelog '{#DEST_NAME}': Log Archive is not valid|<p>The trigger will launch if the archive log destination is not in one of these states:<br>2 - 'DEFERRED';<br>3 - 'VALID'."</p>|`last(/Oracle by ODBC/oracle.archivelog_log_status["{#DEST_NAME}"])<2`|High||

### LLD rule ASM disk groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ASM disk groups discovery|<p>The ASM disk groups.</p>|Database monitor|db.odbc.discovery[asm,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]|

### Item prototypes for ASM disk groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ASM '{#DGNAME}': Get ASM stats|<p>It gets the ASM disk group statistics.</p>|Database monitor|db.odbc.get[get_asm_{#DGNAME}_stat,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ASM '{#DGNAME}': Total size|<p>The total size of the ASM disk group.</p>|Dependent item|oracle.asm_total_size["{#DGNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SIZE_BYTE`</p></li></ul>|
|ASM '{#DGNAME}': Free size|<p>The free size of the ASM disk group.</p>|Dependent item|oracle.asm_free_size["{#DGNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.FREE_SIZE_BYTE`</p></li></ul>|
|ASM '{#DGNAME}': Used size, percent|<p>Usage of the ASM disk group expressed in %.</p>|Dependent item|oracle.asm_used_pct["{#DGNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.USED_PERCENT`</p></li></ul>|

### Trigger prototypes for ASM disk groups discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ASM '{#DGNAME}': Disk group usage is too high|<p>The usage of the ASM disk group expressed in % exceeds {$ORACLE.ASM.USED.PCT.MAX.WARN}.</p>|`min(/Oracle by ODBC/oracle.asm_used_pct["{#DGNAME}"],5m)>{$ORACLE.ASM.USED.PCT.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>ASM '{#DGNAME}': Disk group usage is too high</li></ul>|
|ASM '{#DGNAME}': Disk group usage is too high|<p>The usage of the ASM disk group expressed in % exceeds {$ORACLE.ASM.USED.PCT.MAX.WARN}.</p>|`min(/Oracle by ODBC/oracle.asm_used_pct["{#DGNAME}"],5m)>{$ORACLE.ASM.USED.PCT.MAX.HIGH}`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

