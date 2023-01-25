
# Oracle by ODBC

## Overview

For Zabbix version: 6.4 and higher.
The template is developed to monitor a single DBMS Oracle Database instance with ODBC.

This template was tested on:

- Oracle Database, version 12c2, 18c, 19c

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/odbc_checks) for basic instructions.

1. Create an Oracle DB user for monitoring:

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

   Edit or add a new file:

   * ```/etc/sysconfig/zabbix-server # for server```

   * ```/etc/sysconfig/zabbix-proxy # for proxy```

   Then, add:
   ```
   export ORACLE_HOME=/usr/lib/oracle/19.6/client64
   export PATH=$PATH:$ORACLE_HOME/bin
   export LD_LIBRARY_PATH=$ORACLE_HOME/lib:/usr/lib64:/usr/lib:$ORACLE_HOME/bin
   export TNS_ADMIN=$ORACLE_HOME/network/admin
   ```

4. Restart Zabbix server or Zabbix proxy.

5. Set the username and password in the host macros ({$ORACLE.USER} and {$ORACLE.PASSWORD}).

6. Set the {$ORACLE.DRIVER} and {$ORACLE.SERVICE} in the host macros.
  {$ORACLE.DRIVER} is a path to the driver location in OS.
  The "Service's TCP port state" item uses {HOST.CONN} and {$ORACLE.PORT} macros to check the availability of the listener.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ORACLE.ASM.USED.PCT.MAX.HIGH} |<p>The maximum percentage of used Automatic Storage Management (ASM) disk group for a high trigger expression.</p> |`95` |
|{$ORACLE.ASM.USED.PCT.MAX.WARN} |<p>The maximum percentage of used ASM disk group for a warning trigger expression.</p> |`90` |
|{$ORACLE.CONCURRENCY.MAX.WARN} |<p>The maximum percentage of sessions concurrency usage for a trigger expression.</p> |`80` |
|{$ORACLE.DB.FILE.MAX.WARN} |<p>The maximum percentage of used database files for a trigger expression.</p> |`80` |
|{$ORACLE.DBNAME.MATCHES} |<p>This macro is used in database discovery. It can be overridden on host level or its linked template level.</p> |`.*` |
|{$ORACLE.DBNAME.NOT_MATCHES} |<p>This macro is used in database discovery. It can be overridden on host level or its linked template level.</p> |`PDB\$SEED` |
|{$ORACLE.DRIVER} |<p>The Oracle driver path. For example: `/usr/lib/oracle/21/client64/lib/libsqora.so.21.1`</p> |`<Put path to oracle driver here>` |
|{$ORACLE.EXPIRE.PASSWORD.MIN.WARN} |<p>The number of warning days before the password expires for a trigger expression.</p> |`7` |
|{$ORACLE.PASSWORD} |<p>The Oracle user's password.</p> |`<Put your password here>` |
|{$ORACLE.PGA.USE.MAX.WARN} |<p>Alert threshold for the maximum percentage of the Program Global Area (PGA) usage for a trigger expression.</p> |`90` |
|{$ORACLE.PORT} |<p>Oracle DB TCP port.</p> |`1521` |
|{$ORACLE.PROCESSES.MAX.WARN} |<p>Alert threshold for the maximum percentage of active processes for a trigger expression.</p> |`80` |
|{$ORACLE.REDO.MIN.WARN} |<p>Alert threshold for the minimum number of REDO logs for a trigger expression.</p> |`3` |
|{$ORACLE.SERVICE} |<p>Oracle Service Name.</p> |`<Put oracle service name here>` |
|{$ORACLE.SESSION.LOCK.MAX.TIME} |<p>The maximum duration of the session lock in seconds to count the session as a prolongedly locked query.</p> |`600` |
|{$ORACLE.SESSION.LONG.LOCK.MAX.WARN} |<p>Alert threshold for the maximum number of the prolongedly locked sessions for a trigger expression.</p> |`3` |
|{$ORACLE.SESSIONS.LOCK.MAX.WARN} |<p>Alert threshold for the maximum percentage of locked sessions for a trigger expression.</p> |`20` |
|{$ORACLE.SESSIONS.MAX.WARN} |<p>Alert threshold for the maximum percentage of active sessions for a trigger expression.</p> |`80` |
|{$ORACLE.SHARED.FREE.MIN.WARN} |<p>Alert threshold for the minimum percentage of free shared pool for a trigger expression.</p> |`5` |
|{$ORACLE.TABLESPACE.NAME.MATCHES} |<p>This macro is used in tablespace discovery. It can be overridden on host level or its linked template level.</p> |`.*` |
|{$ORACLE.TABLESPACE.NAME.NOT_MATCHES} |<p>This macro is used in tablespace discovery. It can be overridden on host level or its linked template level.</p> |`CHANGE_IF_NEEDED` |
|{$ORACLE.TBS.USED.PCT.MAX.HIGH} |<p>High severity alert threshold for the maximum percentage of tablespace usage (used bytes/allocated bytes) for a trigger expression.</p> |`95` |
|{$ORACLE.TBS.USED.PCT.MAX.WARN} |<p>Warning severity alert threshold for the maximum percentage of tablespace usage (used bytes/allocated bytes) for a trigger expression.</p> |`90` |
|{$ORACLE.TBS.UTIL.PCT.MAX.HIGH} |<p>High severity alert threshold for the maximum percentage of tablespace utilization (allocated bytes/max bytes) for a trigger expression.</p> |`90` |
|{$ORACLE.TBS.UTIL.PCT.MAX.WARN} |<p>Warning severity alert threshold for the maximum percentage of tablespace utilization (allocated bytes/max bytes) for a trigger expression.</p> |`80` |
|{$ORACLE.USER} |<p>Oracle username.</p> |`<Put your username here>` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Archive log discovery |<p>Destinations of the log archive.</p> |ODBC |db.odbc.discovery[archivelog,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"] |
|ASM disk groups discovery |<p>The ASM disk groups.</p> |ODBC |db.odbc.discovery[asm,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"] |
|Database discovery |<p>Scanning databases in the database management system (DBMS).</p> |ODBC |db.odbc.discovery[db_list,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Filter**:</p>AND <p>- {#DBNAME} MATCHES_REGEX `{$ORACLE.DBNAME.MATCHES}`</p><p>- {#DBNAME} NOT_MATCHES_REGEX `{$ORACLE.DBNAME.NOT_MATCHES}`</p> |
|PDB discovery |<p>Scanning a pluggable database (PDB) in DBMS.</p> |ODBC |db.odbc.discovery[pdb_list,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Filter**:</p>AND <p>- {#DBNAME} MATCHES_REGEX `{$ORACLE.DBNAME.MATCHES}`</p><p>- {#DBNAME} NOT_MATCHES_REGEX `{$ORACLE.DBNAME.NOT_MATCHES}`</p> |
|Tablespace discovery |<p>Scanning tablespaces in DBMS.</p> |ODBC |db.odbc.discovery[tbsname,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Filter**:</p>AND <p>- {#TABLESPACE} MATCHES_REGEX `{$ORACLE.TABLESPACE.NAME.MATCHES}`</p><p>- {#TABLESPACE} NOT_MATCHES_REGEX `{$ORACLE.TABLESPACE.NAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Oracle |Oracle: Service's TCP port state |<p>It checks the availability of Oracle on the TCP port.</p> |ZABBIX_PASSIVE |net.tcp.service[tcp,{HOST.CONN},{$ORACLE.PORT}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Oracle |Oracle: Number of LISTENER processes |<p>The number of running LISTENER processes.</p> |ZABBIX_PASSIVE |proc.num[,,,"tnslsnr LISTENER"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Oracle |Oracle: Version |<p>The Oracle Server version.</p> |DEPENDENT |oracle.version<p>**Preprocessing**:</p><p>- JSONPATH: `$..VERSION.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Oracle |Oracle: Uptime |<p>The Oracle instance uptime expressed in seconds.</p> |DEPENDENT |oracle.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$..UPTIME.first()`</p> |
|Oracle |Oracle: Instance status |<p>The status of the instance.</p> |DEPENDENT |oracle.instance_status<p>**Preprocessing**:</p><p>- JSONPATH: `$..STATUS.first()`</p> |
|Oracle |Oracle: Archiver state |<p>The status of automatic archiving.</p> |DEPENDENT |oracle.archiver_state<p>**Preprocessing**:</p><p>- JSONPATH: `$..ARCHIVER.first()`</p> |
|Oracle |Oracle: Instance name |<p>The name of an instance.</p> |DEPENDENT |oracle.instance_name<p>**Preprocessing**:</p><p>- JSONPATH: `$..INSTANCE_NAME.first()`</p> |
|Oracle |Oracle: Instance hostname |<p>The name of the host machine.</p> |DEPENDENT |oracle.instance_hostname<p>**Preprocessing**:</p><p>- JSONPATH: `$..HOST_NAME.first()`</p> |
|Oracle |Oracle: Instance role |<p>It indicates whether the instance is an active instance or an inactive secondary instance.</p> |DEPENDENT |oracle.instance.role<p>**Preprocessing**:</p><p>- JSONPATH: `$..INSTANCE_ROLE.first()`</p> |
|Oracle |Oracle: Sessions limit |<p>The user and system sessions.</p> |DEPENDENT |oracle.session_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYSPARAM::Sessions')].VALUE.first()`</p> |
|Oracle |Oracle: Datafiles limit |<p>The maximum allowable number of datafiles.</p> |DEPENDENT |oracle.db_files_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYSPARAM::Db_Files')].VALUE.first()`</p> |
|Oracle |Oracle: Processes limit |<p>The maximum number of user processes.</p> |DEPENDENT |oracle.processes_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYSPARAM::Processes')].VALUE.first()`</p> |
|Oracle |Oracle: Number of processes | |DEPENDENT |oracle.processes_count<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='PROC::Procnum')].VALUE.first()`</p> |
|Oracle |Oracle: Datafiles count |<p>The current number of datafiles.</p> |DEPENDENT |oracle.db_files_count<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='DATAFILE::Count')].VALUE.first()`</p> |
|Oracle |Oracle: Buffer cache hit ratio |<p>The ratio of buffer cache hits ((LogRead - PhyRead)/LogRead).</p> |DEPENDENT |oracle.buffer_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Buffer Cache Hit Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Cursor cache hit ratio |<p>The ratio of cursor cache hits (CursorCacheHit/SoftParse).</p> |DEPENDENT |oracle.cursor_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Cursor Cache Hit Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Library cache hit ratio |<p>The ratio of library cache hits (Hits/Pins).</p> |DEPENDENT |oracle.library_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Library Cache Hit Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Shared pool free % |<p>Free memory of a shared pool expressed in %.</p> |DEPENDENT |oracle.shared_pool_free<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Shared Pool Free %')].VALUE.first()`</p> |
|Oracle |Oracle: Physical reads per second |<p>Reads per second.</p> |DEPENDENT |oracle.physical_reads_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Physical Reads Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Physical writes per second |<p>Writes per second.</p> |DEPENDENT |oracle.physical_writes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Physical Writes Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Physical reads bytes per second |<p>Read bytes per second.</p> |DEPENDENT |oracle.physical_read_bytes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Physical Read Bytes Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Physical writes bytes per second |<p>Write bytes per second.</p> |DEPENDENT |oracle.physical_write_bytes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Physical Write Bytes Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Enqueue timeouts per second |<p>Enqueue timeouts per second.</p> |DEPENDENT |oracle.enqueue_timeouts_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Enqueue Timeouts Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: GC CR block received per second |<p>The global cache (GC) and the consistent read (CR) block received per second.</p> |DEPENDENT |oracle.gc_cr_block_received_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::GC CR Block Received Per Second')].VALUE.first()`</p> |
|Oracle |Oracle: Global cache blocks corrupted |<p>The number of blocks that encountered corruption or checksum failure during the interconnect.</p> |DEPENDENT |oracle.cache_blocks_corrupt<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Global Cache Blocks Corrupted')].VALUE.first()`</p> |
|Oracle |Oracle: Global cache blocks lost |<p>The number of lost global cache blocks.</p> |DEPENDENT |oracle.cache_blocks_lost<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Global Cache Blocks Lost')].VALUE.first()`</p> |
|Oracle |Oracle: Logons per second |<p>The number of logon attempts.</p> |DEPENDENT |oracle.logons_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Logons Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Average active sessions |<p>The average active sessions at a point in time. The number of sessions that are either working or waiting.</p> |DEPENDENT |oracle.active_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Average Active Sessions')].VALUE.first()`</p> |
|Oracle |Oracle: Session count |<p>The count of sessions.</p> |DEPENDENT |oracle.session_count<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SESSION::Total')].VALUE.first()`</p> |
|Oracle |Oracle: Active user sessions |<p>The number of active user sessions.</p> |DEPENDENT |oracle.session_active_user<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SESSION::Active User')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: Active background sessions |<p>The number of active background sessions.</p> |DEPENDENT |oracle.session_active_background<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SESSION::Active Background')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: Inactive user sessions |<p>The number of inactive user sessions.</p> |DEPENDENT |oracle.session_inactive_user<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SESSION::Inactive User')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: Sessions lock rate |<p>The percentage of locked sessions. Locks are mechanisms that prevent destructive interaction between transactions accessing the same resource — either user objects, such as tables and rows or system objects not visible to users, such as shared data structures in memory and data dictionary rows.</p> |DEPENDENT |oracle.session_lock_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SESSION::Lock rate')].VALUE.first()`</p> |
|Oracle |Oracle: Sessions locked over {$ORACLE.SESSION.LOCK.MAX.TIME}s |<p>The count of the prolongedly locked sessions. (You can change the duration of maximum session lock in seconds for a query by {$ORACLE.SESSION.LOCK.MAX.TIME} macro. Default is 600 sec).</p> |DEPENDENT |oracle.session_long_time_locked<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SESSION::Long time locked')].VALUE.first()`</p> |
|Oracle |Oracle: Sessions concurrency |<p>The percentage of concurrency. Concurrency is a DB behavior when different transactions request to change the same resource. In the case of modifying data transactions, it sequentially temporarily blocks the right to change the data, the rest of the transactions are waiting for the access. In the case when the access for the resource is locked for a long time, then the concurrency grows (like the transaction queue) and this often has an extremely negative impact on the performance. A high contention value does not indicate the root cause of the problem but is a signal to search for it.</p> |DEPENDENT |oracle.session_concurrency_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SESSION::Concurrency rate')].VALUE.first()`</p> |
|Oracle |Oracle: User '{$ORACLE.USER}' expire password |<p>The number of days before the password of Zabbix account expires.</p> |DEPENDENT |oracle.user_expire_password<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='USER::Expire password')].VALUE.first()`</p> |
|Oracle |Oracle: Active serial sessions |<p>The number of active serial sessions.</p> |DEPENDENT |oracle.active_serial_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Active Serial Sessions')].VALUE.first()`</p> |
|Oracle |Oracle: Active parallel sessions |<p>The number of active parallel sessions.</p> |DEPENDENT |oracle.active_parallel_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Active Parallel Sessions')].VALUE.first()`</p> |
|Oracle |Oracle: Long table scans per second |<p>The number of long table scans per second. A table is considered 'long' if the table is not cached and if its high-water mark is greater than five blocks.</p> |DEPENDENT |oracle.long_table_scans_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Long Table Scans Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: SQL service response time |<p>The Structured Query Language (SQL) service response time expressed in seconds.</p> |DEPENDENT |oracle.service_response_time<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::SQL Service Response Time')].VALUE.first()`</p><p>- MULTIPLIER: `0.01`</p> |
|Oracle |Oracle: User rollbacks per second |<p>The number of times that users manually issue the ROLLBACK statement or an error occurred during the users' transactions.</p> |DEPENDENT |oracle.user_rollbacks_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::User Rollbacks Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Total sorts per user call |<p>The total sorts per user call.</p> |DEPENDENT |oracle.sorts_per_user_call<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Total Sorts Per User Call')].VALUE.first()`</p> |
|Oracle |Oracle: Rows per sort |<p>The average number of rows per sort for all types of sorts performed.</p> |DEPENDENT |oracle.rows_per_sort<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Rows Per Sort')].VALUE.first()`</p> |
|Oracle |Oracle: Disk sort per second |<p>The number of sorts going to disk per second.</p> |DEPENDENT |oracle.disk_sorts<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Disk Sort Per Sec')].VALUE.first()`</p> |
|Oracle |Oracle: Memory sorts ratio |<p>The percentage of sorts (from ORDER BY clauses or index building) that are done to disk vs in-memory.</p> |DEPENDENT |oracle.memory_sorts_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Memory Sorts Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Database wait time ratio |<p>Wait time - the time that the server process spends waiting for available shared resources to be released by other server processes, such as latches, locks, data buffers, etc.</p> |DEPENDENT |oracle.database_wait_time_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Database Wait Time Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Database CPU time ratio |<p>It is calculated by dividing the total CPU (used by the database) by the Oracle time model statistic DB time.</p> |DEPENDENT |oracle.database_cpu_time_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Database CPU Time Ratio')].VALUE.first()`</p> |
|Oracle |Oracle: Temp space used |<p>Used temporary space.</p> |DEPENDENT |oracle.temp_space_used<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SYS::Temp Space Used')].VALUE.first()`</p> |
|Oracle |Oracle: PGA, Total inuse |<p>It indicates how much the Program Global Area (PGA) memory is currently consumed by work areas. This number can be used to determine how much memory is consumed by other consumers of the PGA memory (for example, PL/SQL or Java).</p> |DEPENDENT |oracle.total_pga_used<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='PGA::Total Pga Inuse')].VALUE.first()`</p> |
|Oracle |Oracle: PGA, Aggregate target parameter |<p>The current value of the PGA_AGGREGATE_TARGET initialization parameter. If this parameter is not set, then its value is 0 and automatic management of the PGA memory is disabled.</p> |DEPENDENT |oracle.pga_target<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='PGA::Aggregate Pga Target Parameter')].VALUE.first()`</p> |
|Oracle |Oracle: PGA, Total allocated |<p>The current amount of the PGA memory allocated by the instance. The Oracle Database attempts to keep this number below the value of the PGA_AGGREGATE_TARGET initialization parameter. However, it is possible for the PGA allocated to exceed that value by a small percentage and for a short period of time when the work area workload is increasing very rapidly or when PGA_AGGREGATE_TARGET is set to a small value.</p> |DEPENDENT |oracle.total_pga_allocated<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='PGA::Total Pga Allocated')].VALUE.first()`</p> |
|Oracle |Oracle: PGA, Total freeable |<p>The number of bytes of the PGA memory in all processes that could be freed back to the operating system.</p> |DEPENDENT |oracle.total_pga_freeable<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='PGA::Total Freeable Pga Memory')].VALUE.first()`</p> |
|Oracle |Oracle: PGA, Global memory bound |<p>The maximum size of work area executed in automatic mode.</p> |DEPENDENT |oracle.pga_global_bound<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='PGA::Global Memory Bound')].VALUE.first()`</p> |
|Oracle |Oracle: FRA, Space limit |<p>The maximum amount of disk space (in bytes) that the database can use for the Fast Recovery Area (FRA).</p> |DEPENDENT |oracle.fra_space_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='FRA::Space Limit')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: FRA, Used space |<p>The amount of disk space (in bytes) used by FRA files created in the current and all the previous FRAs.</p> |DEPENDENT |oracle.fra_space_used<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='FRA::Space Used')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: FRA, Space reclaimable |<p>The total amount of disk space (in bytes) that can be created by deleting obsolete, redundant, and other low priority files from the FRA.</p> |DEPENDENT |oracle.fra_space_reclaimable<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='FRA::Space Reclaimable')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: FRA, Number of files |<p>The number of files in the FRA.</p> |DEPENDENT |oracle.fra_number_of_files<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='FRA::Number Of Files')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: FRA, Usable space in % | |DEPENDENT |oracle.fra_usable_pct<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='FRA::Usable Pct')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: FRA, Number of restore points | |DEPENDENT |oracle.fra_restore_point<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='FRA::Restore Point')].VALUE.first()`</p> |
|Oracle |Oracle: SGA, java pool |<p>The memory is allocated from the Java pool.</p> |DEPENDENT |oracle.sga_java_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SGA::Java Pool')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: SGA, large pool |<p>The memory is allocated from a large pool.</p> |DEPENDENT |oracle.sga_large_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SGA::Large Pool')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: SGA, shared pool |<p>The memory is allocated from a shared pool.</p> |DEPENDENT |oracle.sga_shared_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SGA::Shared Pool')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: SGA, log buffer |<p>The number of bytes allocated for the redo log buffer.</p> |DEPENDENT |oracle.sga_log_buffer<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SGA::Log_Buffer')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: SGA, fixed |<p>The fixed System Global Area (SGA) is an internal housekeeping area.</p> |DEPENDENT |oracle.sga_fixed<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SGA::Fixed_Sga')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: SGA, buffer cache |<p>The size of standard block cache.</p> |DEPENDENT |oracle.sga_buffer_cache<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='SGA::Buffer_Cache')].VALUE.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: Redo logs available to switch |<p>The number of inactive/unused redo logs available for log switching.</p> |DEPENDENT |oracle.redo_logs_available<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.METRIC=='REDO::Available')].VALUE.first()`</p> |
|Oracle |Oracle Database '{#DBNAME}': Open status |<p>1 - 'MOUNTED';</p><p>2 - 'READ WRITE';</p><p>3 - 'READ ONLY';</p><p>4 - 'READ ONLY WITH APPLY' (a physical standby database is open in real-time query mode).</p> |DEPENDENT |oracle.db_open_mode["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.OPEN_MODE`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Role |<p>The current role of the database where:</p><p>1 - 'SNAPSHOT STANDBY';</p><p>2 - 'LOGICAL STANDBY';</p><p>3 - 'PHYSICAL STANDBY';</p><p>4 - 'PRIMARY ';</p><p>5 - 'FAR SYNC'.</p> |DEPENDENT |oracle.db_role["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ROLE`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Log mode |<p>The archive log mode where:</p><p>0 - 'NOARCHIVELOG';</p><p>1 - 'ARCHIVELOG';</p><p>2 - 'MANUAL'.</p> |DEPENDENT |oracle.db_log_mode["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.LOG_MODE`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Force logging |<p>It indicates whether the database is under force logging mode 'YES' or 'NO'.</p> |DEPENDENT |oracle.db_force_logging["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.FORCE_LOGGING`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Open status |<p>1 - 'MOUNTED';</p><p>2 - 'READ WRITE';</p><p>3 - 'READ ONLY';</p><p>4 - 'READ ONLY WITH APPLY' (a physical standby database is open in real-time query mode).</p> |DEPENDENT |oracle.pdb_open_mode["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.OPEN_MODE`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace allocated, bytes |<p>Currently allocated bytes for the tablespace (sum of the current size of datafiles).</p> |DEPENDENT |oracle.tbs_alloc_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.FILE_BYTES`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace MAX size, bytes |<p>The maximum size of the tablespace.</p> |DEPENDENT |oracle.tbs_max_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.MAX_BYTES`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace used, bytes |<p>Currently used bytes for the tablespace (current size of datafiles - the free space).</p> |DEPENDENT |oracle.tbs_used_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.USED_BYTES`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace free, bytes |<p>Free bytes of the allocated space.</p> |DEPENDENT |oracle.tbs_free_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.FREE_BYTES`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace allocated, percent |<p>Allocated bytes/max bytes*100.</p> |DEPENDENT |oracle.tbs_used_pct["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.USED_PCT_MAX`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace usage, percent |<p>Used bytes/allocated bytes*100.</p> |DEPENDENT |oracle.tbs_used_file_pct["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.USED_FILE_PCT`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Open status |<p>The tablespace status where:</p><p>1 - 'ONLINE';</p><p>2 - 'OFFLINE';</p><p>3 - 'READ ONLY'.</p> |DEPENDENT |oracle.tbs_status["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.STATUS`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Error |<p>It displays the error message.</p> |DEPENDENT |oracle.archivelog_error["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ERROR`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Last sequence |<p>It identifies the sequence number of the last archived redo log to be archived.</p> |DEPENDENT |oracle.archivelog_log_sequence["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.LOG_SEQUENCE`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Status |<p>It identifies the current status of the destination where:</p><p>1 - 'VALID';</p><p>2 - 'DEFERRED';</p><p>3 - 'ERROR';</p><p>0 - 'UNKNOWN'.</p> |DEPENDENT |oracle.archivelog_log_status["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.STATUS`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Oracle |ASM '{#DGNAME}': Total size |<p>The total size of the ASM disk group.</p> |DEPENDENT |oracle.asm_total_size["{#DGNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.SIZE_BYTE`</p> |
|Oracle |ASM '{#DGNAME}': Free size |<p>The free size of the ASM disk group.</p> |DEPENDENT |oracle.asm_free_size["{#DGNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.FREE_SIZE_BYTE`</p> |
|Oracle |ASM '{#DGNAME}': Free size |<p>Usage of the ASM disk group expressed in %.</p> |DEPENDENT |oracle.asm_used_pct["{#DGNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.USED_PERCENT`</p> |
|Zabbix raw items |Oracle: Get instance state |<p>The item gets its state of the current instance.</p> |ODBC |db.odbc.get[get_instance_state,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Oracle: Get system metrics |<p>The item gets the values of the system metrics.</p> |ODBC |db.odbc.get[get_system_metrics,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Oracle Database '{#DBNAME}': Get CDB and No-CDB info |<p>It gets the information about the container database (CDB) and non-CDB database on an instance.</p> |ODBC |db.odbc.get[get_cdb_{#DBNAME}_info,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Oracle Database '{#DBNAME}': Get PDB info |<p>It gets the information about the PDB database on an instance.</p> |ODBC |db.odbc.get[get_pdb_{#DBNAME}_info,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Oracle TBS '{#TABLESPACE}': Get tablespaces stats |<p>It gets the statistics of the tablespace.</p> |ODBC |db.odbc.get[get_tablespace_{#TABLESPACE}_stats,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Archivelog '{#DEST_NAME}': Get archive log info |<p>It gets the archivelog statistics.</p> |ODBC |db.odbc.get[get_archivelog_{#DEST_NAME}_stat,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |ASM '{#DGNAME}': Get ASM stats |<p>It gets the ASM disk group statistics.</p> |ODBC |db.odbc.get[get_asm_{#DGNAME}_stat,,"Driver={$ORACLE.DRIVER};DBQ=//{HOST.CONN}:{$ORACLE.PORT}/{$ORACLE.SERVICE};"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>**Expression**:</p>`The text is too long. Please see the template.` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Oracle: Port {$ORACLE.PORT} is unavailable |<p>The TCP port of the Oracle Server service is currently unavailable.</p> |`max(/Oracle by ODBC/net.tcp.service[tcp,{HOST.CONN},{$ORACLE.PORT}],#3)=0  and max(/Oracle by ODBC/proc.num[,,,"tnslsnr LISTENER"],#3)>0` |DISASTER | |
|Oracle: LISTENER process is not running |<p>-</p> |`max(/Oracle by ODBC/proc.num[,,,"tnslsnr LISTENER"],#3)=0` |DISASTER | |
|Oracle: Version has changed |<p>The Oracle DB version has changed. Acknowledge to close manually.</p> |`last(/Oracle by ODBC/oracle.version,#1)<>last(/Oracle by ODBC/oracle.version,#2) and length(last(/Oracle by ODBC/oracle.version))>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Host has been restarted |<p>The host uptime is less than 10 minutes.</p> |`last(/Oracle by ODBC/oracle.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Oracle: Failed to fetch info data |<p>Zabbix has not received any data for the items for the last 5 minutes. The database might be unavailable for connecting.</p> |`nodata(/Oracle by ODBC/oracle.uptime,5m)=1` |WARNING |<p>**Depends on**:</p><p>- Oracle: Port {$ORACLE.PORT} is unavailable</p> |
|Oracle: Instance name has changed |<p>The Oracle DB instance has changed. Ack to close manually.</p> |`last(/Oracle by ODBC/oracle.instance_name,#1)<>last(/Oracle by ODBC/oracle.instance_name,#2) and length(last(/Oracle by ODBC/oracle.instance_name))>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Instance hostname has changed |<p>Oracle DB Instance hostname has changed. Ack to close.</p> |`last(/Oracle by ODBC/oracle.instance_hostname,#1)<>last(/Oracle by ODBC/oracle.instance_hostname,#2) and length(last(/Oracle by ODBC/oracle.instance_hostname))>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Too many active processes |<p>Active processes are using more than {$ORACLE.PROCESSES.MAX.WARN}% of the available number of processes.</p> |`min(/Oracle by ODBC/oracle.processes_count,5m) * 100 / last(/Oracle by ODBC/oracle.processes_limit) > {$ORACLE.PROCESSES.MAX.WARN}` |WARNING | |
|Oracle: Too many database files |<p>The number of datafiles is higher than {$ORACLE.DB.FILE.MAX.WARN}% of the available datafiles limit.</p> |`min(/Oracle by ODBC/oracle.db_files_count,5m) * 100 / last(/Oracle by ODBC/oracle.db_files_limit) > {$ORACLE.DB.FILE.MAX.WARN}` |WARNING | |
|Oracle: Shared pool free is too low |<p>The free memory percent of the shared pool has been less than {$ORACLE.SHARED.FREE.MIN.WARN}% for the last 5 minutes.</p> |`max(/Oracle by ODBC/oracle.shared_pool_free,5m)<{$ORACLE.SHARED.FREE.MIN.WARN}` |WARNING | |
|Oracle: Too many active sessions |<p>Active sessions are using more than {$ORACLE.SESSIONS.MAX.WARN}% of the available sessions.</p> |`min(/Oracle by ODBC/oracle.session_count,5m) * 100 / last(/Oracle by ODBC/oracle.session_limit) > {$ORACLE.SESSIONS.MAX.WARN}` |WARNING | |
|Oracle: Too many locked sessions |<p>The number of locked sessions exceeds {$ORACLE.SESSIONS.LOCK.MAX.WARN}% of the running sessions.</p> |`min(/Oracle by ODBC/oracle.session_lock_rate,5m) > {$ORACLE.SESSIONS.LOCK.MAX.WARN}` |WARNING | |
|Oracle: Too many sessions locked |<p>The number of locked sessions exceeding {$ORACLE.SESSION.LOCK.MAX.TIME} seconds is too high. Long-term locks can negatively affect the database performance. Therefore, if they are detected, you should first find the most difficult queries from the database point of view and then analyze possible resource leaks.</p> |`min(/Oracle by ODBC/oracle.session_long_time_locked,5m) > {$ORACLE.SESSION.LONG.LOCK.MAX.WARN}` |WARNING | |
|Oracle: Too high database concurrency |<p>The concurrency rate exceeds {$ORACLE.CONCURRENCY.MAX.WARN}%. A high contention value does not indicate the root cause of the problem, but it is a signal to search for it. In the case of high competition, the analysis of resource consumption should be carried out. Which are the most "heavy" queries made in the database? Possibly, also session tracing. All this will help to determine the root cause and possible optimization points both in the database configuration and in the logic of building queries of the application itself.</p> |`min(/Oracle by ODBC/oracle.session_concurrency_rate,5m) > {$ORACLE.CONCURRENCY.MAX.WARN}` |WARNING | |
|Oracle: Zabbix account will expire soon |<p>The password for Zabbix user in the database expires soon.</p> |`last(/Oracle by ODBC/oracle.user_expire_password)  < {$ORACLE.EXPIRE.PASSWORD.MIN.WARN}` |WARNING | |
|Oracle: Total PGA inuse is too high |<p>The total PGA in use is more than {$ORACLE.PGA.USE.MAX.WARN}% of PGA_AGGREGATE_TARGET.</p> |`min(/Oracle by ODBC/oracle.total_pga_used,5m) * 100 / last(/Oracle by ODBC/oracle.pga_target) > {$ORACLE.PGA.USE.MAX.WARN}` |WARNING | |
|Oracle: Number of REDO logs available for switching is too low |<p>The number of inactive/unused REDOs available for log switching is low (database down risk).</p> |`max(/Oracle by ODBC/oracle.redo_logs_available,5m) < {$ORACLE.REDO.MIN.WARN}` |WARNING | |
|Oracle Database '{#DBNAME}': Open status in mount mode |<p>The Oracle DB is in a mounted state.</p> |`last(/Oracle by ODBC/oracle.db_open_mode["{#DBNAME}"])=1` |WARNING | |
|Oracle Database '{#DBNAME}': Open status has changed |<p>The Oracle DB open status has changed. Ack to close manually.</p> |`last(/Oracle by ODBC/oracle.db_open_mode["{#DBNAME}"],#1)<>last(/Oracle by ODBC/oracle.db_open_mode["{#DBNAME}"],#2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Oracle Database '{#DBNAME}': Open status in mount mode</p> |
|Oracle Database '{#DBNAME}': Role has changed |<p>The Oracle DB role has changed. Ack to close manually.</p> |`last(/Oracle by ODBC/oracle.db_role["{#DBNAME}"],#1)<>last(/Oracle by ODBC/oracle.db_role["{#DBNAME}"],#2)` |INFO |<p>Manual close: YES</p> |
|Oracle Database '{#DBNAME}': Force logging is deactivated for DB with active Archivelog |<p>Force Logging mode - it is very important metric for Databases in 'ARCHIVELOG'. This feature allows to forcibly write all the transactions to the REDO.</p> |`last(/Oracle by ODBC/oracle.db_force_logging["{#DBNAME}"]) = 0 and last(/Oracle by ODBC/oracle.db_log_mode["{#DBNAME}"]) = 1` |WARNING | |
|Oracle Database '{#DBNAME}': Open status in mount mode |<p>The Oracle DB is in a mounted state.</p> |`last(/Oracle by ODBC/oracle.pdb_open_mode["{#DBNAME}"])=1` |WARNING | |
|Oracle Database '{#DBNAME}': Open status has changed |<p>The Oracle DB open status has changed. Ack to close manually.</p> |`last(/Oracle by ODBC/oracle.pdb_open_mode["{#DBNAME}"],#1)<>last(/Oracle by ODBC/oracle.pdb_open_mode["{#DBNAME}"],#2)` |INFO |<p>Manual close: YES</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace utilization is too high |<p>-</p> |`min(/Oracle by ODBC/oracle.tbs_used_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespace utilization is too high</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace utilization is too high |<p>-</p> |`min(/Oracle by ODBC/oracle.tbs_used_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.UTIL.PCT.MAX.HIGH}` |HIGH | |
|Oracle TBS '{#TABLESPACE}': Tablespace usage is too high |<p>-</p> |`min(/Oracle by ODBC/oracle.tbs_used_file_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespace usage is too high</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace usage is too high |<p>-</p> |`min(/Oracle by ODBC/oracle.tbs_used_file_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.HIGH}` |HIGH | |
|Oracle TBS '{#TABLESPACE}': Tablespace is OFFLINE |<p>The tablespace is in the offline state.</p> |`last(/Oracle by ODBC/oracle.tbs_status["{#TABLESPACE}"])=2` |WARNING | |
|Oracle TBS '{#TABLESPACE}': Tablespace status has changed |<p>Oracle tablespace status has changed. Ack to close.</p> |`last(/Oracle by ODBC/oracle.tbs_status["{#TABLESPACE}"],#1)<>last(/Oracle by ODBC/oracle.tbs_status["{#TABLESPACE}"],#2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespace is OFFLINE</p> |
|Archivelog '{#DEST_NAME}': Log Archive is not valid |<p>The trigger will launch if the archive log destination is not in one of these states:</p><p>2 - 'DEFERRED';</p><p>3 - 'VALID'."</p> |`last(/Oracle by ODBC/oracle.archivelog_log_status["{#DEST_NAME}"])<2` |HIGH | |
|ASM '{#DGNAME}': Disk group usage is too high |<p>The usage of the ASM disk group expressed in % exceeds {$ORACLE.ASM.USED.PCT.MAX.WARN}.</p> |`min(/Oracle by ODBC/oracle.asm_used_pct["{#DGNAME}"],5m)>{$ORACLE.ASM.USED.PCT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- ASM '{#DGNAME}': Disk group usage is too high</p> |
|ASM '{#DGNAME}': Disk group usage is too high |<p>The usage of the ASM disk group expressed in % exceeds {$ORACLE.ASM.USED.PCT.MAX.WARN}.</p> |`min(/Oracle by ODBC/oracle.asm_used_pct["{#DGNAME}"],5m)>{$ORACLE.ASM.USED.PCT.MAX.HIGH}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

