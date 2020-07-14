
# Template DB Oracle by ODBC

## Overview

For Zabbix version: 5.0  
The template is developed for monitoring DBMS Oracle Database via ODBC.

This template was tested on:

- Zabbix, version 5.0
- Oracle Database, version 12c2, 18c, 19c

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/current/manual/config/templates_out_of_the_box/odbc_checks) for basic instructions.

1. Create an Oracle DB user for monitoring.
 ```
 CREATE USER zabbix_mon IDENTIFIED BY <PASSWORD>;
 -- Grant access to the zabbix_mon user.
 GRANT CONNECT, CREATE SESSION TO zabbix_mon;
 GRANT SELECT ON V_$instance TO zabbix_mon;
 GRANT SELECT ON V_$database TO zabbix_mon;
 GRANT SELECT ON v_$sysmetric TO zabbix_mon;
 GRANT SELECT ON v$recovery_file_dest TO zabbix_mon;
 GRANT SELECT ON v$restore_point TO zabbix_mon;
 GRANT SELECT ON v$process TO zabbix_mon;
 GRANT SELECT ON v$datafile TO zabbix_mon;
 GRANT SELECT ON v$pgastat TO zabbix_mon;
 GRANT SELECT ON v$sgastat TO zabbix_mon;
 GRANT SELECT ON v$archive_dest TO zabbix_mon;
 GRANT SELECT ON sys.dba_data_files TO zabbix_mon;
 GRANT SELECT ON DBA_TABLESPACES TO zabbix_mon;
 GRANT SELECT ON DBA_TABLESPACE_USAGE_METRICS TO zabbix_mon;
 ```


2. Set the user name and password in host macros ({$ORACLE.USER} and {$ORACLE.PASSWORD}).
3. Set the {$ORACLE.DSN} in host macros.


  Do not forget to install the ODBC driver on the Zabbix server or the Zabbix proxy.
  See Oracle documentation for instructions: https://www.oracle.com/database/technologies/releasenote-odbc-ic.html.
  **Note! Credentials in the odbc.ini do not work for Oracle.**
  **Note! Be sure that ODBC connects to Oracle with session parameter NLS_NUMERIC_CHARACTERS= '.,' It is important for correct display float numbers in Zabbix.**


  The "Service's TCP port state" item uses {HOST.CONN} and {$ORACLE.PORT} macros to check the availability of the listener.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ORACLE.DB.FILE.MAX.WARN} |<p>Maximum percentage of database files for trigger expression.</p> |`80` |
|{$ORACLE.DBNAME.MATCHES} |<p>This macro is used in database discovery. It can be overridden on the host or linked template level.</p> |`.*` |
|{$ORACLE.DBNAME.NOT_MATCHES} |<p>This macro is used in database discovery. It can be overridden on the host or linked template level.</p> |`PDB$SEED` |
|{$ORACLE.DSN} |<p>System data source name</p> |`<Put your DSN here>` |
|{$ORACLE.PASSWORD} |<p>Oracle user password.</p> |`<Put your password here>` |
|{$ORACLE.PGA.USE.MAX.WARN} |<p>Maximum percentage of PGA usage for trigger expression.</p> |`90` |
|{$ORACLE.PORT} |<p>Oracle DB TCP port.</p> |`1521` |
|{$ORACLE.PROCESSES.MAX.WARN} |<p>Maximum percentage of active processes for trigger expression.</p> |`80` |
|{$ORACLE.SESSIONS.MAX.WARN} |<p>Maximum percentage of active sessions for trigger expression.</p> |`80` |
|{$ORACLE.TABLESPACE.NAME.MATCHES} |<p>This macro is used in tablespace discovery. It can be overridden on the host or linked template level.</p> |`.*` |
|{$ORACLE.TABLESPACE.NAME.NOT_MATCHES} |<p>This macro is used in tablespace discovery. It can be overridden on the host or linked template level.</p> |`CHANGE_IF_NEEDED` |
|{$ORACLE.TBS.USED.PCT.MAX.HIGH} |<p>Maximum percentage of used tablespace for high trigger expression.</p> |`90` |
|{$ORACLE.TBS.USED.PCT.MAX.WARN} |<p>Maximum percentage of used tablespace for warning trigger expression.</p> |`80` |
|{$ORACLE.USER} |<p>Oracle username.</p> |`<Put your username here>` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Database discovery |<p>Scanning databases in DBMS.</p> |ODBC |db.odbc.discovery[dbname,"{$ORACLE.DSN}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- A: {#DBNAME} MATCHES_REGEX `{$ORACLE.DBNAME.MATCHES}`</p><p>- B: {#DBNAME} NOT_MATCHES_REGEX `{$ORACLE.DBNAME.NOT_MATCHES}`</p> |
|Tablespace discovery |<p>Scanning tablespaces in DBMS.</p> |ODBC |db.odbc.discovery[tbsname,"{$ORACLE.DSN}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- A: {#TABLESPACE} MATCHES_REGEX `{$ORACLE.TABLESPACE.NAME.MATCHES}`</p><p>- B: {#TABLESPACE} NOT_MATCHES_REGEX `{$ORACLE.TABLESPACE.NAME.NOT_MATCHES}`</p> |
|Archive log discovery |<p>Log archive destinations.</p> |ODBC |db.odbc.discovery[archivelog,"{$ORACLE.DSN}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Oracle |Oracle: Service's TCP port state |<p>Test the availability of Oracle on TCP port.</p> |ZABBIX_PASSIVE |net.tcp.service[tcp,{HOST.CONN},{$ORACLE.PORT}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Oracle |Oracle: Number of LISTENER processes |<p>Number of LISTENER processes running</p> |ZABBIX_PASSIVE |proc.num[,,,"tnslsnr LISTENER"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Oracle |Oracle: Version |<p>Oracle Server version.</p> |DEPENDENT |oracle.version<p>**Preprocessing**:</p><p>- JSONPATH: `$..VERSION.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Oracle |Oracle: Uptime |<p>Oracle instance uptime in seconds.</p> |DEPENDENT |oracle.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$..UPTIME.first()`</p> |
|Oracle |Oracle: Instance status |<p>Status of the instance.</p> |DEPENDENT |oracle.instance_status<p>**Preprocessing**:</p><p>- JSONPATH: `$..STATUS.first()`</p> |
|Oracle |Oracle: Archiver state |<p>Automatic archiving status.</p> |DEPENDENT |oracle.archiver_state<p>**Preprocessing**:</p><p>- JSONPATH: `$..ARCHIVER.first()`</p> |
|Oracle |Oracle: Instance name |<p>Name of the instance.</p> |DEPENDENT |oracle.instance_name<p>**Preprocessing**:</p><p>- JSONPATH: `$..INSTANCE_NAME.first()`</p> |
|Oracle |Oracle: Instance hostname |<p>Name of the host machine.</p> |DEPENDENT |oracle.instance_hostname<p>**Preprocessing**:</p><p>- JSONPATH: `$..HOST_NAME.first()`</p> |
|Oracle |Oracle: Instance role |<p>Indicates whether the instance is an active instance or an inactive secondary instance.</p> |DEPENDENT |oracle.instance_role<p>**Preprocessing**:</p><p>- JSONPATH: `$..INSTANCE_ROLE.first()`</p> |
|Oracle |Oracle: Sessions limit |<p>User and system sessions.</p> |DEPENDENT |oracle.session_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='parameters' && @.METRIC_NAME=='sessions')].VALUE.first()`</p> |
|Oracle |Oracle: Datafiles limit |<p>Max allowable number of  datafile.</p> |DEPENDENT |oracle.db_files_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='parameters' && @.METRIC_NAME=='db_files')].VALUE.first()`</p> |
|Oracle |Oracle: Processes limit |<p>User processes.</p> |DEPENDENT |oracle.processes_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='parameters' && @.METRIC_NAME=='processes')].VALUE.first()`</p> |
|Oracle |Oracle: Number of processes | |DEPENDENT |oracle.processes_count<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='processes' && @.METRIC_NAME=='procnum')].VALUE.first()`</p> |
|Oracle |Oracle: Datafiles count |<p>Current number of datafile.</p> |DEPENDENT |oracle.db_files_count<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='datafile' && @.METRIC_NAME=='count')].VALUE.first()`</p> |
|Oracle |Oracle: Buffer cache hit ratio |<p>Ratio of buffer cache hits. (LogRead - PhyRead)/LogRead</p> |DEPENDENT |oracle.buffer_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Buffer Cache Hit Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Cursor Cache Hit Ratio |<p>Ratio of cursor cache hits. CursorCacheHit/SoftParse</p> |DEPENDENT |oracle.cursor_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC_NAME=='Cursor Cache Hit Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Library Cache Hit Ratio |<p>Ratio of library cache hits. Hits/Pins</p> |DEPENDENT |oracle.library_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Library Cache Hit Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Shared Pool Free % |<p>Shared pool free memory percent. Free/Total</p> |DEPENDENT |oracle.shared_pool_free<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Shared Pool Free %')].VALUE.first()`</p> |
|Oracle |Oracle: Physical Reads per second |<p>Reads per second.</p> |DEPENDENT |oracle.physical_reads_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Physical Reads Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Physical Writes per second |<p>Writes per second.</p> |DEPENDENT |oracle.physical_writes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Physical Writes Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Physical Reads Bytes per second |<p>Read bytes per second.</p> |DEPENDENT |oracle.physical_read_bytes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Physical Read Bytes Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Physical Writes Bytes per second |<p>Write bytes per second.</p> |DEPENDENT |oracle.physical_write_bytes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Physical Write Bytes Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Enqueue Timeouts per second |<p>Enqueue timeouts per second.</p> |DEPENDENT |oracle.enqueue_timeouts_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Enqueue Timeouts Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: GC CR Block Received per second |<p>GC CR block received per second.</p> |DEPENDENT |oracle.gc_cr_block_received_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='GC CR Block Received Per Second')].VALUE.first()`</p> |
|Oracle |Oracle: Global Cache Blocks Corrupted |<p>The number of blocks that encountered a corruption or checksum failure during interconnect.</p> |DEPENDENT |oracle.cache_blocks_corrupt<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Global Cache Blocks Corrupted')].VALUE.first()`</p> |
|Oracle |Oracle: Global Cache Blocks Lost |<p>The number of global cache blocks lost</p> |DEPENDENT |oracle.cache_blocks_lost<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Global Cache Blocks Lost')].VALUE.first()`</p> |
|Oracle |Oracle: Logons per second |<p>The number of logon attempts.</p> |DEPENDENT |oracle.logons_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Logons Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Average active sessions |<p>The average active sessions at a point in time. It is the number of sessions that are either working or waiting.</p> |DEPENDENT |oracle.active_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Average Active Sessions')].VALUE.first()`</p> |
|Oracle |Oracle: Session count |<p>Session count.</p> |DEPENDENT |oracle.session_count<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sessions' && @.METRIC_NAME=='total')].VALUE.first()`</p> |
|Oracle |Oracle: Active user sessions |<p>The number of active user sessions.</p> |DEPENDENT |oracle.session_active_user<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sessions' && @.METRIC_NAME=='ACTIVE_USER')].VALUE.first()`</p> |
|Oracle |Oracle: Active background sessions |<p>The number of active background sessions.</p> |DEPENDENT |oracle.session_active_background<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sessions' && @.METRIC_NAME=='ACTIVE_BACKGROUND')].VALUE.first()`</p> |
|Oracle |Oracle: Inactive user sessions |<p>The number of inactive user sessions.</p> |DEPENDENT |oracle.session_inactive_user<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sessions' && @.METRIC_NAME=='INACTIVE_USER')].VALUE.first()`</p> |
|Oracle |Oracle: Active serial sessions |<p>The number of active serial sessions.</p> |DEPENDENT |oracle.active_serial_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Active Serial Sessions')].VALUE.first()`</p> |
|Oracle |Oracle: Active parallel sessions |<p>The number of active parallel sessions.</p> |DEPENDENT |oracle.active_parallel_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Active Parallel Sessions')].VALUE.first()`</p> |
|Oracle |Oracle: Long table scans per second |<p>The number of long table scans per second. A table is considered 'long' if the table is not cached and if its high-water mark is greater than 5 blocks.</p> |DEPENDENT |oracle.long_table_scans_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Long Table Scans Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: SQL Service response time |<p>SQL service response time in seconds.</p> |DEPENDENT |oracle.service_response_time<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='SQL Service Response Time')].VALUE.first()`</p><p>- MULTIPLIER: `0.01`</p> |
|Oracle |Oracle: User rollbacks per second |<p>The number of times that users manually issue the ROLLBACK statement or an error occurred during a user's transactions.</p> |DEPENDENT |oracle.user_rollbacks_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='User Rollbacks Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Total sorts per user call |<p>Total sorts per user call.</p> |DEPENDENT |oracle.sorts_per_user_call<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Total Sorts Per User Call')].VALUE.first()`</p> |
|Oracle |Oracle: Rows per sort |<p>The average number of rows per sort for all types of sorts performed.</p> |DEPENDENT |oracle.rows_per_sort<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Rows Per Sort')].VALUE.first()`</p> |
|Oracle |Oracle: Disk sort per second |<p>The number of sorts going to disk per second</p> |DEPENDENT |oracle.disk_sorts<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Disk Sort Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Memory sorts ratio |<p>The percentage of sorts (from ORDER BY clauses or index building) that are done to disk vs in-memory.</p> |DEPENDENT |oracle.memory_sorts_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Memory Sorts Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Database Wait time ratio |<p>Wait time: the time that the server process spends waiting for available shared resources (to be released by other server processes) such as latches, locks, data buffers, and so on</p> |DEPENDENT |oracle.database_wait_time_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Database Wait Time Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Database CPU time ratio |<p>Calculated by dividing the total CPU used by the database by the Oracle time model statistic DB time.</p> |DEPENDENT |oracle.database_cpu_time_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Database CPU Time Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Temp space used |<p>Temp space used.</p> |DEPENDENT |oracle.temp_space_used<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='sysmetrics' && @.METRIC_NAME=='Temp Space Used')].VALUE.first()`</p> |
|Oracle |Oracle: PGA, Total inuse |<p>Indicates how much PGA memory is currently consumed by work areas. This number can be used to determine how much memory is consumed by other consumers of the PGA memory (for example, PL/SQL or Java).</p> |DEPENDENT |oracle.total_pga_used<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='pga' && @.METRIC_NAME=='total PGA inuse')].VALUE.first()`</p> |
|Oracle |Oracle: PGA, Aggregate target parameter |<p>Current value of the PGA_AGGREGATE_TARGET initialization parameter. If this parameter is not set, then its value is 0 and automatic management of PGA memory is disabled.</p> |DEPENDENT |oracle.pga_target<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='pga' && @.METRIC_NAME=='aggregate PGA target parameter')].VALUE.first()`</p> |
|Oracle |Oracle: PGA, Total allocated |<p>Current amount of PGA memory allocated by the instance. The Oracle Database attempts to keep this number below the value of the PGA_AGGREGATE_TARGET initialization parameter. However, it is possible for the PGA allocated to exceed that value by a small percentage and for a short period of time when the work area workload is increasing very rapidly or when PGA_AGGREGATE_TARGET is set to a small value.</p> |DEPENDENT |oracle.total_pga_allocated<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='pga' && @.METRIC_NAME=='total PGA allocated')].VALUE.first()`</p> |
|Oracle |Oracle: PGA, Total freeable |<p>Number of bytes of PGA memory in all processes that could be freed back to the operating system.</p> |DEPENDENT |oracle.total_pga_freeable<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='pga' && @.METRIC_NAME=='total freeable PGA memory')].VALUE.first()`</p> |
|Oracle |Oracle: PGA, Global memory bound |<p>Maximum size of a work area executed in automatic mode.</p> |DEPENDENT |oracle.pga_global_bound<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='pga' && @.METRIC_NAME=='global memory bound')].VALUE.first()`</p> |
|Oracle |Oracle: FRA, Space limit |<p>Maximum amount of disk space (in bytes) that the database can use for the fast recovery area.</p> |DEPENDENT |oracle.fra_space_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='fra' && @.METRIC_NAME=='space_limit')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: FRA, Used space |<p>Amount of disk space (in bytes) used by fast recovery area files created in current and all previous fast recovery areas.</p> |DEPENDENT |oracle.fra_space_used<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='fra' && @.METRIC_NAME=='space_used')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: FRA, Space reclaimable |<p>Total amount of disk space (in bytes) that can be created by deleting obsolete, redundant, and other low priority files from the fast recovery area.</p> |DEPENDENT |oracle.fra_space_reclaimable<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='fra' && @.METRIC_NAME=='space_reclaimable')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: FRA, Number of files |<p>Number of files in the fast recovery area</p> |DEPENDENT |oracle.fra_number_of_files<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='fra' && @.METRIC_NAME=='number_of_files')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: FRA, Used space in % | |DEPENDENT |oracle.fra_usable_pct<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='fra' && @.METRIC_NAME=='usable_pct')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: FRA, Number of restore points | |DEPENDENT |oracle.fra_restore_point<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='fra' && @.METRIC_NAME=='restore_point')].VALUE.first()`</p> |
|Oracle |Oracle: SGA, java pool |<p>Memory is allocated from the java pool.</p> |DEPENDENT |oracle.sga_java_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='SGA Pool' && @.METRIC_NAME=='java pool')].VALUE.first()`</p> |
|Oracle |Oracle: SGA, large pool |<p>Memory is allocated from the large pool.</p> |DEPENDENT |oracle.sga_large_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='SGA Pool' && @.METRIC_NAME=='large pool')].VALUE.first()`</p> |
|Oracle |Oracle: SGA, shared pool |<p>Memory is allocated from the shared pool.</p> |DEPENDENT |oracle.sga_shared_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='SGA Pool' && @.METRIC_NAME=='shared pool')].VALUE.first()`</p> |
|Oracle |Oracle: SGA, log buffer |<p>The number of bytes allocated for the redo log buffer.</p> |DEPENDENT |oracle.sga_log_buffer<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='SGA component name' && @.METRIC_NAME=='log_buffer')].VALUE.first()`</p> |
|Oracle |Oracle: SGA, fixed |<p>The fixed SGA is an internal housekeeping area.</p> |DEPENDENT |oracle.sga_fixed<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='SGA component name' && @.METRIC_NAME=='fixed_sga')].VALUE.first()`</p> |
|Oracle |Oracle: SGA, buffer cache |<p>The size of the cache of standard blocks.</p> |DEPENDENT |oracle.sga_buffer_cache<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TYPE=='SGA component name' && @.METRIC_NAME=='buffer_cache')].VALUE.first()`</p> |
|Oracle |Oracle Database '{#DBNAME}': Open status |<p>1 - 'MOUNTED', 2 - 'READ WRITE', 3 - 'READ ONLY', 4 - 'READ ONLY WITH APPLY' (A physical standby database is open in real-time query mode)</p> |DEPENDENT |oracle.db_open_mode["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.DBNAME=='{#DBNAME}')].OPEN_STATUS.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Role |<p>-</p> |DEPENDENT |oracle.db_role["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.DBNAME=='{#DBNAME}')].ROLE.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace Usage | |DEPENDENT |oracle.tbs_used_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TABLESPACE=='{#TABLESPACE}')].USED_BYTES.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace size | |DEPENDENT |oracle.tbs_max_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TABLESPACE=='{#TABLESPACE}')].MAX_BYTES.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace free | |DEPENDENT |oracle.tbs_free_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TABLESPACE=='{#TABLESPACE}')].FREE_BYTES.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace Usage percent | |DEPENDENT |oracle.tbs_used_pct["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TABLESPACE=='{#TABLESPACE}')].USED_PCT.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Open status |<p>Tablespace status. 1 - 'ONLINE' 2 - 'OFFLINE' 3- 'READ ONLY'</p> |DEPENDENT |oracle.tbs_status["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.TABLESPACE=='{#TABLESPACE}')].STATUS.first()`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Error |<p>Displays the error text</p> |DEPENDENT |oracle.archivelog_error["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.DEST_NAME=='{#DEST_NAME}')].ERROR.first()`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Last sequence |<p>Identifies the sequence number of the last archived redo log to be archived</p> |DEPENDENT |oracle.archivelog_log_sequence["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.DEST_NAME=='{#DEST_NAME}')].LOG_SEQUENCE.first()`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Status |<p> Identifies the current status of the destination: 1 - 'Valid', 2 - 'Dederred',3 - 'Error', 0 - 'Unknown'</p> |DEPENDENT |oracle.archivelog_log_status["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.DEST_NAME=='{#DEST_NAME}')].STATUS.first()`</p> |
|Zabbix_raw_items |Oracle: Get instance state |<p>The item gets state of the current instance.</p> |ODBC |db.odbc.get[get_instance_state,"{$ORACLE.DSN}"]<p>**Expression**:</p>`SELECT  INSTANCE_NAME, HOST_NAME,  VERSION || '-' || EDITION AS VERSION,  floor((SYSDATE - startup_time)*60*60*24) AS UPTIME,  decode(status,'STARTED',1,'MOUNTED',2,'OPEN',3,'OPEN MIGRATE',4, 0) AS STATUS,  decode(archiver,'STOPPED',1,'STARTED',2,'FAILED',3, 0) AS  ARCHIVER,  decode(INSTANCE_ROLE,'PRIMARY_INSTANCE',1,'SECONDARY_INSTANCE',2, 0) AS  INSTANCE_ROLE FROM v$instance; ` |
|Zabbix_raw_items |Oracle: Get system metrics |<p>The item gets system metric values.</p> |ODBC |db.odbc.get[get_system_metrics,"{$ORACLE.DSN}"]<p>**Expression**:</p>`SELECT 'sysmetrics' AS  TYPE, METRIC_NAME, VALUE FROM V$SYSMETRIC WHERE GROUP_ID = 2 UNION SELECT 'parameters' AS  TYPE, NAME AS METRIC_NAME, to_number(VALUE) FROM V$SYSTEM_PARAMETER WHERE NAME IN ('sessions','processes','db_files') UNION SELECT 'sessions' AS TYPE , status||'_'||TYPE AS METRIC_NAME, COUNT(*) as VALUE FROM v$session GROUP BY status, TYPE UNION SELECT 'sessions' AS TYPE ,'total' , COUNT(*) as VALUE FROM v$session UNION SELECT 'pga' AS TYPE, name, value FROM v$pgastat UNION SELECT 'fra', 'space_limit', space_limit FROM v$recovery_file_dest def UNION SELECT 'fra', 'space_used', space_used FROM v$recovery_file_dest def UNION SELECT 'fra', 'space_reclaimable', space_reclaimable FROM v$recovery_file_dest def UNION SELECT 'fra', 'number_of_files', number_of_files FROM v$recovery_file_dest def UNION SELECT 'fra', 'usable_pct', DECODE(space_limit, 0, 0,(100-(100 *(space_used - space_reclaimable)/ space_limit))) FROM v$recovery_file_dest UNION SELECT 'fra', 'restore_point', COUNT(*) FROM v$restore_point UNION SELECT 'processes', 'procnum', COUNT(*)  FROM v$process UNION SELECT 'datafile', 'count', COUNT(*)  FROM v$datafile UNION SELECT 'SGA Pool', pool, SUM(bytes) FROM V$SGASTAT WHERE pool IN ( 'java pool', 'large pool' ) GROUP BY pool UNION SELECT 'SGA Pool', 'shared pool', SUM(bytes) FROM V$SGASTAT WHERE pool = 'shared pool' AND name NOT IN ('library cache', 'dictionary cache', 'free memory','sql area') UNION SELECT 'SGA component name', name, bytes FROM V$SGASTAT WHERE pool IS NULL AND name IN ('log_buffer', 'fixed_sga') UNION SELECT 'SGA component name', 'buffer_cache', SUM(bytes) FROM V$SGASTAT WHERE pool IS NULL AND name IN ('buffer_cache', 'db_block_buffers'); ` |
|Zabbix_raw_items |Oracle: Get Tablespaces stats |<p>Get tablespaces stats.</p> |ODBC |db.odbc.get[tablespace_stats,"{$ORACLE.DSN}"]<p>**Expression**:</p>`SELECT df.tablespace_name AS tablespace, df.type AS TYPE, SUM(df.bytes) AS used_bytes, SUM(df.max_bytes) AS max_bytes, SUM(f.free) AS free_bytes, ROUND(SUM(df.bytes)/ SUM(df.max_bytes)* 100, 2) AS used_pct, DECODE(df.status, 'ONLINE', 1, 'OFFLINE', 2, 'READ ONLY', 3, 0) AS status FROM ( SELECT    ddf.file_id,    dt.contents AS TYPE,    dt.STATUS ,    ddf.file_name,    ddf.tablespace_name,    TRUNC(ddf.bytes) AS bytes,    TRUNC(GREATEST(ddf.bytes, ddf.maxbytes)) AS max_bytes    FROM     dba_data_files ddf,     dba_tablespaces dt   WHERE    ddf.tablespace_name = dt.tablespace_name ) df,    ( SELECT TRUNC(SUM(bytes)) AS FREE, file_id FROM dba_free_space GROUP BY file_id ) f    WHERE df.file_id = f.file_id (+)    GROUP BY df.tablespace_name, df.TYPE, df.status UNION ALL SELECT    Y.name AS tablespace_name,    Y.type AS TYPE,    SUM(Y.bytes) AS bytes,    SUM(Y.max_bytes) AS max_bytes,    MAX(NVL(Y.free_bytes, 0)) AS FREE,    ROUND(SUM(Y.bytes)/ SUM(Y.max_bytes)* 100, 2) AS used_pct,    DECODE(Y.tbs_status, 'ONLINE', 1, 'OFFLINE', 2, 'READ ONLY', 3, 0) AS status    FROM ( SELECT      dtf.tablespace_name AS name,      dt.contents AS TYPE,      dt.STATUS AS tbs_status,      dtf.status AS status,      dtf.bytes AS bytes,      (SELECT        ((f.total_blocks - s.tot_used_blocks)* vp.value)        FROM ( SELECT tablespace_name, SUM(used_blocks) tot_used_blocks FROM gv$sort_segment           WHERE tablespace_name != 'DUMMY'           GROUP BY tablespace_name) s,      ( SELECT tablespace_name, SUM(blocks) total_blocks FROM dba_temp_files        WHERE tablespace_name != 'DUMMY'        GROUP BY tablespace_name) f,      ( SELECT value FROM v$parameter WHERE name = 'db_block_size') vp    WHERE      f.tablespace_name = s.tablespace_name      AND f.tablespace_name = dtf.tablespace_name ) AS free_bytes,      CASE WHEN dtf.maxbytes = 0 THEN dtf.bytes      ELSE dtf.maxbytes END AS max_bytes    FROM     sys.dba_temp_files dtf,     sys.dba_tablespaces dt    WHERE    dtf.tablespace_name = dt.tablespace_name ) Y    GROUP BY Y.name, Y.TYPE, Y.tbs_status ORDER BY tablespace; ` |
|Zabbix_raw_items |Oracle: Get all databases |<p>Get list of all (PDB, CDB, No-CDB) databases on instansce.</p> |ODBC |db.odbc.get[get_all_db,"{$ORACLE.DSN}"]<p>**Expression**:</p>`SELECT NAME as DBNAME, DECODE(open_mode, 'MOUNTED', 1, 'READ ONLY', 2, 'READ WRITE', 3, 'READ ONLY WITH APPLY', 4, 'MIGRATE', 5, 0) AS open_status, DECODE(role, 'SNAPSHOT STANDBY', 1, 'LOGICAL STANDBY', 2, 'PHYSICAL STANDBY', 3, 'PRIMARY', 4, 'FAR SYNC', 5, 0) AS role FROM ( SELECT name,  open_mode, Null AS ROLE FROM v$pdbs UNION SELECT name,  open_mode, database_role FROM v$database ); ` |
|Zabbix_raw_items |Oracle: Get archive log info | |ODBC |db.odbc.get[get_archivelog_stat,"{$ORACLE.DSN}"]<p>**Expression**:</p>`SELECT d.dest_name, DECODE (d.status, 'VALID',3, 'DEFERRED', 2, 'ERROR', 1, 0) AS status, d.log_sequence, d.error FROM v$archive_dest d , v$database db WHERE d.status != 'INACTIVE' AND db.log_mode = 'ARCHIVELOG'; ` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Oracle: Port {$ORACLE.PORT} is unavailable |<p>The TCP port of the Oracle Server service is currently unavailable.</p> |`{TEMPLATE_NAME:net.tcp.service[tcp,{HOST.CONN},{$ORACLE.PORT}].max(#3)}=0  and {Template DB Oracle by ODBC:proc.num[,,,"tnslsnr LISTENER"].max(#3)}>0` |DISASTER | |
|Oracle: LISTENER process is not running | |`{TEMPLATE_NAME:proc.num[,,,"tnslsnr LISTENER"].max(#3)}=0` |DISASTER | |
|Oracle: Version has changed (new version value received: {ITEM.VALUE}) |<p>Oracle DB version has changed. Ack to close.</p> |`{TEMPLATE_NAME:oracle.version.diff()}=1 and {TEMPLATE_NAME:oracle.version.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Oracle: has been restarted (uptime < 10m) |<p>Uptime is less than 10 minutes</p> |`{TEMPLATE_NAME:oracle.uptime.last()}<10m` |INFO |<p>Manual close: YES</p> |
|Oracle: Failed to fetch info data (or no data for 30m) |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`{TEMPLATE_NAME:oracle.uptime.nodata(30m)}=1` |INFO |<p>**Depends on**:</p><p>- Oracle: Port {$ORACLE.PORT} is unavailable</p> |
|Oracle: Instance name has changed (new name received: {ITEM.VALUE}) |<p>Oracle DB Instance name has changed. Ack to close.</p> |`{TEMPLATE_NAME:oracle.instance_name.diff()}=1 and {TEMPLATE_NAME:oracle.instance_name.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Instance hostname has changed (new hostname received: {ITEM.VALUE}) |<p>Oracle DB Instance hostname has changed. Ack to close.</p> |`{TEMPLATE_NAME:oracle.instance_hostname.diff()}=1 and {TEMPLATE_NAME:oracle.instance_hostname.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Too many active processes (over {$ORACLE.PROCESSES.MAX.WARN}% for 5 min) |<p>Active process are using more than {$ORACLE.PROCESSES.MAX.WARN}% of the available Process number</p> |`{TEMPLATE_NAME:oracle.processes_count.last()} * 100 / {Template DB Oracle by ODBC:oracle.processes_limit.last()} > {$ORACLE.PROCESSES.MAX.WARN}` |WARNING | |
|Oracle: Too many database files (over {$ORACLE.DB.FILE.MAX.WARN}% for 5 min) |<p>Number of datafiles more than {$ORACLE.DB.FILE.MAX.WARN}% of the available datafile files limit.</p> |`{TEMPLATE_NAME:oracle.db_files_count.last()} * 100 / {Template DB Oracle by ODBC:oracle.db_files_limit.last()} > {$ORACLE.DB.FILE.MAX.WARN}` |WARNING | |
|Oracle: Too many active sessions (over {$ORACLE.SESSIONS.MAX.WARN}% for 5 min) |<p>Active sessions are using more than {$ORACLE.SESSIONS.MAX.WARN}% of the available sessions</p> |`{TEMPLATE_NAME:oracle.session_count.last()} * 100 / {Template DB Oracle by ODBC:oracle.session_limit.last()} > {$ORACLE.SESSIONS.MAX.WARN}` |WARNING | |
|Oracle: Total PGA inuse is too high (over {$ORACLE.PGA.USE.MAX.WARN}% for 5 min) |<p>Total PGA inuse is more than {$ORACLE.PGA.USE.MAX.WARN}% of PGA_AGGREGATE_TARGET.</p> |`{TEMPLATE_NAME:oracle.total_pga_used.last()} * 100 / {Template DB Oracle by ODBC:oracle.pga_target.last()} > {$ORACLE.PGA.USE.MAX.WARN}` |WARNING | |
|Oracle Database '{#DBNAME}': Open status in mount mode |<p>The Oracle DB has a MOUNTED state.</p> |`{TEMPLATE_NAME:oracle.db_open_mode["{#DBNAME}"].last()}=1` |WARNING | |
|Oracle Database '{#DBNAME}': Open status has changed (new value received: {ITEM.VALUE}) |<p>Oracle DB open status has changed. Ack to close.</p> |`{TEMPLATE_NAME:oracle.db_open_mode["{#DBNAME}"].diff()}=1` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Oracle Database '{#DBNAME}': Open status in mount mode</p> |
|Oracle Database '{#DBNAME}': Role has changed (new value received: {ITEM.VALUE}) |<p>Oracle DB role has changed. Ack to close.</p> |`{TEMPLATE_NAME:oracle.db_role["{#DBNAME}"].diff()}=1` |INFO |<p>Manual close: YES</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace Usage is is too high (over {$ORACLE.TBS.USED.PCT.MAX.WARN}% for 5m) | |`{TEMPLATE_NAME:oracle.tbs_used_pct["{#TABLESPACE}"].min(5m)}>{$ORACLE.TBS.USED.PCT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespace Usage is is too high (over {$ORACLE.TBS.USED.PCT.MAX.HIGH}% for 5m)</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace Usage is is too high (over {$ORACLE.TBS.USED.PCT.MAX.HIGH}% for 5m) | |`{TEMPLATE_NAME:oracle.tbs_used_pct["{#TABLESPACE}"].min(5m)}>{$ORACLE.TBS.USED.PCT.MAX.HIGH}` |HIGH | |
|Oracle TBS '{#TABLESPACE}': Tablespase is OFFLINE |<p>The tablespase is in offline state.</p> |`{TEMPLATE_NAME:oracle.tbs_status["{#TABLESPACE}"].last()}=2` |WARNING | |
|Oracle TBS '{#TABLESPACE}': Tablespace status has changed (new value received: {ITEM.VALUE}) |<p>Oracle tablespace status has changed. Ack to close.</p> |`{TEMPLATE_NAME:oracle.tbs_status["{#TABLESPACE}"].diff()}=1` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespase is OFFLINE</p> |
|Archivelog '{#DEST_NAME}': Log Archive is not valid |<p>ARL destination not in 3 - Valid or 2 - Deferred.</p> |`{TEMPLATE_NAME:oracle.archivelog_log_status["{#DEST_NAME}"].last()}<2` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

