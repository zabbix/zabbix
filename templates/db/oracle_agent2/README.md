
# Oracle by Zabbix agent 2

## Overview

The template is developed to monitor a single DBMS Oracle Database instance with Zabbix agent 2.

## Supported versions

Oracle Database 12c2 and newer.

**Important! This integration queries the `V$ACTIVE_SESSION_HISTORY` dynamic performance view which is part of the Oracle Diagnostics Pack. Please make sure that you have the licence required for using this management pack.**

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Oracle Database 12c2, 18c, 19c, 21c, 23c

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Setup and configure Zabbix agent 2 compiled with the Oracle monitoring plugin. See the setup instructions for [Oracle Database plugin](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/src/go/plugins/oracle/README.md).
2. Set the {$ORACLE.CONNSTRING} macro value using either <protocol(host:port)> or named session.
3. If you want to override parameters from Zabbix agent configuration file, set the user name, password and service name in host macros ({$ORACLE.USER}, {$ORACLE.PASSWORD}, and {$ORACLE.SERVICE}).

   User can contain sysdba, sysoper, sysasm privileges. It must be used with `as` as a separator e.g `user as sysdba`, privilege can be upper or lowercase, and must be at the end of username string.

Test availability:
 ```zabbix_get -s oracle-host -k  oracle.ping["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]```

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ORACLE.USER}|<p>Oracle username.</p>|`zabbix`|
|{$ORACLE.PASSWORD}|<p>Oracle user's password.</p>|`zabbix_password`|
|{$ORACLE.CONNSTRING}|<p>Oracle URI or a session name.</p>|`tcp://localhost:1521`|
|{$ORACLE.SERVICE}|<p>Oracle Service Name.</p>|`ORA`|
|{$ORACLE.DBNAME.MATCHES}|<p>Used in database discovery. It can be overridden on the host or linked template level.</p>|`.*`|
|{$ORACLE.DBNAME.NOT_MATCHES}|<p>Used in database discovery. It can be overridden on the host or linked template level.</p>|`PDB\$SEED`|
|{$ORACLE.TABLESPACE.CONTAINER.MATCHES}|<p>Used in tablespace discovery. It can be overridden on the host or linked template level.</p>|`.*`|
|{$ORACLE.TABLESPACE.CONTAINER.NOT_MATCHES}|<p>Used in tablespace discovery. It can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$ORACLE.TABLESPACE.NAME.MATCHES}|<p>Used in tablespace discovery. It can be overridden on the host or linked template level.</p>|`.*`|
|{$ORACLE.TABLESPACE.NAME.NOT_MATCHES}|<p>Used in tablespace discovery. It can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$ORACLE.TBS.USED.PCT.FROM.MAX.WARN}|<p>Warning severity alert threshold for the maximum percentage of tablespace usage from maximum tablespace size (used bytes/max bytes) for the Warning trigger expression.</p>|`90`|
|{$ORACLE.TBS.USED.PCT.FROM.MAX.HIGH}|<p>High severity alert threshold for the maximum percentage of tablespace usage (used bytes/max bytes) for the High trigger expression.</p>|`95`|
|{$ORACLE.TBS.USED.PCT.MAX.WARN}|<p>Warning severity alert threshold for the maximum percentage of tablespace usage (used bytes/allocated bytes) for the Warning trigger expression.</p>|`90`|
|{$ORACLE.TBS.USED.PCT.MAX.HIGH}|<p>High severity alert threshold for the maximum percentage of tablespace usage (used bytes/allocated bytes) for the High trigger expression.</p>|`95`|
|{$ORACLE.TBS.UTIL.PCT.MAX.WARN}|<p>Warning severity alert threshold for the maximum percentage of tablespace utilization (allocated bytes/max bytes) for the High trigger expression.</p>|`80`|
|{$ORACLE.TBS.UTIL.PCT.MAX.HIGH}|<p>High severity alert threshold for the maximum percentage of tablespace utilization (allocated bytes/max bytes) for the High trigger expression.</p>|`90`|
|{$ORACLE.PROCESSES.MAX.WARN}|<p>Alert threshold for the maximum percentage of active processes for the Warning trigger expression.</p>|`80`|
|{$ORACLE.SESSIONS.MAX.WARN}|<p>Alert threshold for the maximum percentage of active sessions for the Warning trigger expression.</p>|`80`|
|{$ORACLE.DB.FILE.MAX.WARN}|<p>The maximum percentage of used database files for the Warning trigger expression.</p>|`80`|
|{$ORACLE.PGA.USE.MAX.WARN}|<p>Alert threshold for the maximum percentage of the Program Global Area (PGA) usage for the Warning trigger expression.</p>|`90`|
|{$ORACLE.SESSIONS.LOCK.MAX.WARN}|<p>Alert threshold for the maximum percentage of locked sessions for the Warning trigger expression.</p>|`20`|
|{$ORACLE.SESSION.LOCK.MAX.TIME}|<p>The maximum duration of the session lock in seconds to count the session as a prolongedly locked query.</p>|`600`|
|{$ORACLE.SESSION.LONG.LOCK.MAX.WARN}|<p>Alert threshold for the maximum number of the prolongedly locked sessions for the Warning trigger expression.</p>|`3`|
|{$ORACLE.CONCURRENCY.MAX.WARN}|<p>The maximum percentage of session concurrency for the Warning trigger expression.</p>|`80`|
|{$ORACLE.REDO.MIN.WARN}|<p>Alert threshold for the minimum number of redo logs for the Warning trigger expression.</p>|`3`|
|{$ORACLE.SHARED.FREE.MIN.WARN}|<p>Alert threshold for the minimum percentage of free shared pool for the Warning trigger expression.</p>|`5`|
|{$ORACLE.EXPIRE.PASSWORD.MIN.WARN}|<p>The number of days before the password expires for the Warning trigger expression.</p>|`7`|
|{$ORACLE.ASM.USED.PCT.MAX.WARN}|<p>The maximum percentage of used space in the Automatic Storage Management (ASM) disk group for the Warning trigger expression.</p>|`90`|
|{$ORACLE.ASM.USED.PCT.MAX.HIGH}|<p>The maximum percentage of used space in the Automatic Storage Management (ASM) disk group for the High trigger expression.</p>|`95`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ping|<p>Test the connection to Oracle Database state.</p>|Zabbix agent|oracle.ping["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Get instance state|<p>Gets the state of the current instance.</p>|Zabbix agent|oracle.instance.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|
|Version|<p>The Oracle Server version.</p>|Zabbix agent|oracle.version["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Uptime|<p>The Oracle instance uptime expressed in seconds.</p>|Dependent item|oracle.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p></li></ul>|
|Instance status|<p>The status of the instance.</p>|Dependent item|oracle.instance_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li></ul>|
|Archiver state|<p>The status of automatic archiving.</p>|Dependent item|oracle.archiver_state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..archiver.first()`</p></li></ul>|
|Instance name|<p>The name of the instance.</p>|Dependent item|oracle.instance_name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.instance`</p></li></ul>|
|Instance hostname|<p>The name of the host machine.</p>|Dependent item|oracle.instance_hostname<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..hostname.first()`</p></li></ul>|
|Instance role|<p>Indicates whether the instance is an active instance or an inactive secondary instance.</p>|Dependent item|oracle.instance.role<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.role`</p></li></ul>|
|Get system metrics|<p>Gets the values of the system metrics.</p>|Zabbix agent|oracle.sys.metrics["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|
|Buffer cache hit ratio|<p>The ratio of buffer cache hits ((LogRead - PhyRead)/LogRead).</p>|Dependent item|oracle.buffer_cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Buffer Cache Hit Ratio']`</p></li></ul>|
|Cursor cache hit ratio|<p>The ratio of cursor cache hits (CursorCacheHit/SoftParse).</p>|Dependent item|oracle.cursor_cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Cursor Cache Hit Ratio']`</p></li></ul>|
|Library cache hit ratio|<p>The ratio of library cache hits (Hits/Pins).</p>|Dependent item|oracle.library_cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Library Cache Hit Ratio']`</p></li></ul>|
|Shared pool free %|<p>Free memory of a shared pool expressed in %.</p>|Dependent item|oracle.shared_pool_free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Shared Pool Free %']`</p></li></ul>|
|Physical reads per second|<p>Reads per second.</p>|Dependent item|oracle.physical_reads_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Physical Reads Per Sec']`</p></li></ul>|
|Physical writes per second|<p>Writes per second.</p>|Dependent item|oracle.physical_writes_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Physical Writes Per Sec']`</p></li></ul>|
|Physical reads bytes per second|<p>Read bytes per second.</p>|Dependent item|oracle.physical_read_bytes_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Physical Read Bytes Per Sec']`</p></li></ul>|
|Physical writes bytes per second|<p>Write bytes per second.</p>|Dependent item|oracle.physical_write_bytes_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Physical Write Bytes Per Sec']`</p></li></ul>|
|Enqueue timeouts per second|<p>Enqueue timeouts per second.</p>|Dependent item|oracle.enqueue_timeouts_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Enqueue Timeouts Per Sec']`</p></li></ul>|
|GC CR block received per second|<p>The global cache (GC) and the consistent read (CR) block received per second.</p>|Dependent item|oracle.gc_cr_block_received_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['GC CR Block Received Per Second']`</p></li></ul>|
|Global cache blocks corrupted|<p>The number of blocks that encountered corruption or checksum failure during the interconnect.</p>|Dependent item|oracle.cache_blocks_corrupt<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Global Cache Blocks Corrupted']`</p></li></ul>|
|Global cache blocks lost|<p>The number of lost global cache blocks.</p>|Dependent item|oracle.cache_blocks_lost<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Global Cache Blocks Lost']`</p></li></ul>|
|Logons per second|<p>The number of logon attempts.</p>|Dependent item|oracle.logons_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Logons Per Sec']`</p></li></ul>|
|Average active sessions|<p>The average number of active sessions at a point in time that are either working or waiting.</p>|Dependent item|oracle.active_sessions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Average Active Sessions']`</p></li></ul>|
|Active serial sessions|<p>The number of active serial sessions.</p>|Dependent item|oracle.active_serial_sessions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Active Serial Sessions']`</p></li></ul>|
|Active parallel sessions|<p>The number of active parallel sessions.</p>|Dependent item|oracle.active_parallel_sessions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Active Parallel Sessions']`</p></li></ul>|
|Long table scans per second|<p>The number of long table scans per second. A table is considered long if it is not cached and if its high water mark is greater than five blocks.</p>|Dependent item|oracle.long_table_scans_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Long Table Scans Per Sec']`</p></li></ul>|
|SQL service response time|<p>The Structured Query Language (SQL) service response time expressed in seconds.</p>|Dependent item|oracle.service_response_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['SQL Service Response Time']`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|User rollbacks per second|<p>The number of times that users manually issued the `ROLLBACK` statement or an error occurred during the users' transactions.</p>|Dependent item|oracle.user_rollbacks_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['User Rollbacks Per Sec']`</p></li></ul>|
|Total sorts per user call|<p>The total sorts per user call.</p>|Dependent item|oracle.sorts_per_user_call<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Total Sorts Per User Call']`</p></li></ul>|
|Rows per sort|<p>The average number of rows per sort for all types of sorts performed.</p>|Dependent item|oracle.rows_per_sort<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Rows Per Sort']`</p></li></ul>|
|Disk sort per second|<p>The number of sorts going to disk per second.</p>|Dependent item|oracle.disk_sorts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Disk Sort Per Sec']`</p></li></ul>|
|Memory sorts ratio|<p>The percentage of sorts (from `ORDER BY` clauses or index building) that are done to disk vs. in-memory.</p>|Dependent item|oracle.memory_sorts_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Memory Sorts Ratio']`</p></li></ul>|
|Database wait time ratio|<p>Wait time - the time that the server process spends waiting for available shared resources to be released by other server processes such as latches, locks, data buffers, etc.</p>|Dependent item|oracle.database_wait_time_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Database Wait Time Ratio']`</p></li></ul>|
|Database CPU time ratio|<p>The ratio calculated by dividing the total CPU (used by the database) by the Oracle time model statistic DB time.</p>|Dependent item|oracle.database_cpu_time_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Database CPU Time Ratio']`</p></li></ul>|
|Temp space used|<p>Used temporary space.</p>|Dependent item|oracle.temp_space_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['Temp Space Used']`</p></li></ul>|
|Get system parameters|<p>Get a set of system parameter values.</p>|Zabbix agent|oracle.sys.params["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|
|Sessions limit|<p>The user and system sessions.</p>|Dependent item|oracle.session_limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sessions`</p></li></ul>|
|Datafiles limit|<p>The maximum allowable number of datafiles.</p>|Dependent item|oracle.db_files_limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.db_files`</p></li></ul>|
|Processes limit|<p>The maximum number of user processes.</p>|Dependent item|oracle.processes_limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.processes`</p></li></ul>|
|Get sessions stats|<p>Get sessions statistics. {$ORACLE.SESSION.LOCK.MAX.TIME} -- maximum seconds in the current wait condition for counting long time locked sessions. Default: 600 seconds.</p>|Zabbix agent|oracle.sessions.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}","{$ORACLE.SESSION.LOCK.MAX.TIME}"]|
|Session count|<p>The session count.</p>|Dependent item|oracle.session_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|Active user sessions|<p>The number of active user sessions.</p>|Dependent item|oracle.session_active_user<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_user`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Active background sessions|<p>The number of active background sessions.</p>|Dependent item|oracle.session_active_background<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_background`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Inactive user sessions|<p>The number of inactive user sessions.</p>|Dependent item|oracle.session_inactive_user<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.inactive_user`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Sessions lock rate|<p>The percentage of locked sessions. Locks are mechanisms that prevent destructive interaction between transactions accessing the same resource - either user objects, such as tables and rows or system objects not visible to users, such as shared data structures in memory and data dictionary rows.</p>|Dependent item|oracle.session_lock_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lock_rate`</p></li></ul>|
|Sessions locked over {$ORACLE.SESSION.LOCK.MAX.TIME}s|<p>The count of the prolongedly locked sessions. (You can change the duration of the maximum session lock in seconds for a query using the `{$ORACLE.SESSION.LOCK.MAX.TIME}` macro. Default = 600 s).</p>|Dependent item|oracle.session_long_time_locked<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.long_time_locked`</p></li></ul>|
|Sessions concurrency|<p>The percentage of concurrency. Concurrency is a database behavior when different transactions request to change the same resource. In the case of modifying data transactions, it sequentially temporarily blocks the right to change the data, and the rest of the transactions wait for access. When the access to a resource is locked for a long time, the concurrency grows (like the transaction queue), often leaving an extremely negative impact on performance. A high contention value does not indicate the root cause of the problem, but is a signal to search for it.</p>|Dependent item|oracle.session_concurrency_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.concurrency_rate`</p></li></ul>|
|Get PGA stats|<p>Get PGA statistics.</p>|Zabbix agent|oracle.pga.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|
|PGA, Total inuse|<p>The amount of Program Global Area (PGA) memory currently consumed by work areas. This number can be used to determine how much memory is consumed by other consumers of the PGA memory (for example, PL/SQL or Java).</p>|Dependent item|oracle.total_pga_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['total PGA inuse']`</p></li></ul>|
|PGA, Aggregate target parameter|<p>The current value of the `PGA_AGGREGATE_TARGET` initialization parameter. If this parameter is not set, then its value is "0" and automatic management of the PGA memory is disabled.</p>|Dependent item|oracle.pga_target<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['aggregate PGA target parameter']`</p></li></ul>|
|PGA, Total allocated|<p>The current amount of the PGA memory allocated by the instance. The Oracle Database attempts to keep this number below the value of the `PGA_AGGREGATE_TARGET` initialization parameter. However, it is possible for the PGA allocated to exceed that value by a small percentage and for a short period of time when the work area workload is increasing very rapidly or when `PGA_AGGREGATE_TARGET` is set to a small value.</p>|Dependent item|oracle.total_pga_allocated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['total PGA allocated']`</p></li></ul>|
|PGA, Total freeable|<p>The number of bytes of the PGA memory in all processes that could be freed back to the OS.</p>|Dependent item|oracle.total_pga_freeable<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['total freeable PGA memory']`</p></li></ul>|
|PGA, Global memory bound|<p>The maximum size of a work area executed in automatic mode.</p>|Dependent item|oracle.pga_global_bound<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['global memory bound']`</p></li></ul>|
|Get FRA stats|<p>Get FRA statistics.</p>|Zabbix agent|oracle.fra.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|
|FRA, Space limit|<p>The maximum amount of disk space (in bytes) that the database can use for the Fast Recovery Area (FRA).</p>|Dependent item|oracle.fra_space_limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space_limit`</p></li></ul>|
|FRA, Used space|<p>The amount of disk space (in bytes) used by FRA files created in the current and all the previous FRAs.</p>|Dependent item|oracle.fra_space_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space_used`</p></li></ul>|
|FRA, Space reclaimable|<p>The total amount of disk space (in bytes) that can be created by deleting obsolete, redundant, and other low-priority files from the FRA.</p>|Dependent item|oracle.fra_space_reclaimable<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.space_reclaimable`</p></li></ul>|
|FRA, Number of files|<p>The number of files in the FRA.</p>|Dependent item|oracle.fra_number_of_files<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.number_of_files`</p></li></ul>|
|FRA, Usable space in %|<p>Percentage of space usable in the FRA.</p>|Dependent item|oracle.fra_usable_pct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usable_pct`</p></li></ul>|
|FRA, Number of restore points|<p>Number of restore points in the FRA.</p>|Dependent item|oracle.fra_restore_point<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.restore_point`</p></li></ul>|
|Get SGA stats|<p>Get SGA statistics.</p>|Zabbix agent|oracle.sga.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|
|SGA, java pool|<p>The memory is allocated from the Java pool.</p>|Dependent item|oracle.sga_java_pool<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.java_pool`</p></li></ul>|
|SGA, large pool|<p>The memory is allocated from a large pool.</p>|Dependent item|oracle.sga_large_pool<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.large_pool`</p></li></ul>|
|SGA, shared pool|<p>The memory is allocated from a shared pool.</p>|Dependent item|oracle.sga_shared_pool<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.shared_pool`</p></li></ul>|
|SGA, log buffer|<p>The number of bytes allocated for the redo log buffer.</p>|Dependent item|oracle.sga_log_buffer<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.log_buffer`</p></li></ul>|
|SGA, fixed|<p>The fixed System Global Area (SGA) is an internal housekeeping area.</p>|Dependent item|oracle.sga_fixed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fixed_sga`</p></li></ul>|
|SGA, buffer cache|<p>The size of standard block cache.</p>|Dependent item|oracle.sga_buffer_cache<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffer_cache`</p></li></ul>|
|User's expire password|<p>The number of days before the Zabbix account password expires.</p>|Zabbix agent|oracle.user.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.exp_passwd_days_before`</p></li></ul>|
|Redo logs available to switch|<p>The number of inactive/unused redo logs available for log switching.</p>|Zabbix agent|oracle.redolog.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.available`</p></li></ul>|
|Number of processes|<p>The current number of user processes.</p>|Zabbix agent|oracle.proc.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.proc_num`</p></li></ul>|
|Datafiles count|<p>The current number of datafiles.</p>|Zabbix agent|oracle.datafiles.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.datafile_num`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Oracle: Connection to database is unavailable|<p>Connection to Oracle Database is currently unavailable.</p>|`last(/Oracle by Zabbix agent 2/oracle.ping["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"])=0`|Disaster||
|Oracle: Version has changed|<p>The Oracle Database version has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by Zabbix agent 2/oracle.version["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.version["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],#2) and length(last(/Oracle by Zabbix agent 2/oracle.version["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]))>0`|Info|**Manual close**: Yes|
|Oracle: Failed to fetch info data|<p>Zabbix has not received any data for the items for the last 5 minutes. The database might be unavailable for connecting.</p>|`nodata(/Oracle by Zabbix agent 2/oracle.uptime,30m)=1`|Info||
|Oracle: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Oracle by Zabbix agent 2/oracle.uptime)<10m`|Info|**Manual close**: Yes|
|Oracle: Instance name has changed|<p>An Oracle Database instance name has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by Zabbix agent 2/oracle.instance_name,#1)<>last(/Oracle by Zabbix agent 2/oracle.instance_name,#2) and length(last(/Oracle by Zabbix agent 2/oracle.instance_name))>0`|Info|**Manual close**: Yes|
|Oracle: Instance hostname has changed|<p>An Oracle Database instance hostname has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by Zabbix agent 2/oracle.instance_hostname,#1)<>last(/Oracle by Zabbix agent 2/oracle.instance_hostname,#2) and length(last(/Oracle by Zabbix agent 2/oracle.instance_hostname))>0`|Info|**Manual close**: Yes|
|Oracle: Shared pool free is too low|<p>The free memory percent of the shared pool has been less than `{$ORACLE.SHARED.FREE.MIN.WARN}`% for the last 5 minutes.</p>|`max(/Oracle by Zabbix agent 2/oracle.shared_pool_free,5m)<{$ORACLE.SHARED.FREE.MIN.WARN}`|Warning||
|Oracle: Too many active sessions|<p>Active sessions are using more than `{$ORACLE.SESSIONS.MAX.WARN}`% of the available sessions.</p>|`min(/Oracle by Zabbix agent 2/oracle.session_count,5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.session_limit) > {$ORACLE.SESSIONS.MAX.WARN}`|Warning||
|Oracle: Too many locked sessions|<p>The number of locked sessions exceeds `{$ORACLE.SESSIONS.LOCK.MAX.WARN}`% of the running sessions.</p>|`min(/Oracle by Zabbix agent 2/oracle.session_lock_rate,5m) > {$ORACLE.SESSIONS.LOCK.MAX.WARN}`|Warning||
|Oracle: Too many sessions locked|<p>The number of locked sessions exceeding `{$ORACLE.SESSION.LOCK.MAX.TIME}` seconds is too high. Long-term locks can negatively affect the database performance. Therefore, if they are detected, you should first find the most difficult queries from the database point of view and then analyze possible resource leaks.</p>|`min(/Oracle by Zabbix agent 2/oracle.session_long_time_locked,5m) > {$ORACLE.SESSION.LONG.LOCK.MAX.WARN}`|Warning||
|Oracle: Too high database concurrency|<p>The concurrency rate exceeds `{$ORACLE.CONCURRENCY.MAX.WARN}`%. A high contention value does not indicate the root cause of the problem, but is a signal to review resource consumption (determine the "heaviest" queries in the database, trace sessions, etc.) This will help find the root cause and possible optimization points both in database configuration and the logic of building queries.</p>|`min(/Oracle by Zabbix agent 2/oracle.session_concurrency_rate,5m) > {$ORACLE.CONCURRENCY.MAX.WARN}`|Warning||
|Oracle: Total PGA inuse is too high|<p>The total PGA in use is more than `{$ORACLE.PGA.USE.MAX.WARN}`% of `PGA_AGGREGATE_TARGET`.</p>|`min(/Oracle by Zabbix agent 2/oracle.total_pga_used,5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.pga_target) > {$ORACLE.PGA.USE.MAX.WARN}`|Warning||
|Oracle: Zabbix account will expire soon|<p>The password for the Zabbix user in the database expires soon.</p>|`last(/Oracle by Zabbix agent 2/oracle.user.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"])  < {$ORACLE.EXPIRE.PASSWORD.MIN.WARN}`|Warning||
|Oracle: Number of REDO logs available for switching is too low|<p>The number of inactive/unused redos available for log switching is low (risk of database downtime).</p>|`max(/Oracle by Zabbix agent 2/oracle.redolog.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],5m) < {$ORACLE.REDO.MIN.WARN}`|Warning||
|Oracle: Too many active processes|<p>Active processes are using more than `{$ORACLE.PROCESSES.MAX.WARN}`% of the available number of processes.</p>|`min(/Oracle by Zabbix agent 2/oracle.proc.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.processes_limit) > {$ORACLE.PROCESSES.MAX.WARN}`|Warning||
|Oracle: Too many database files|<p>The number of datafiles is higher than `{$ORACLE.DB.FILE.MAX.WARN}`% of the available datafile limit.</p>|`min(/Oracle by Zabbix agent 2/oracle.datafiles.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.db_files_limit) > {$ORACLE.DB.FILE.MAX.WARN}`|Warning||

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Scanning databases in the database management system (DBMS).</p>|Zabbix agent|oracle.db.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database '{#DBNAME}': Get CDB and No-CDB info|<p>Gets the information about the CDB and non-CDB database on an instance.</p>|Zabbix agent|oracle.cdb.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}","{#DBNAME}"]|
|Database '{#DBNAME}': Open status|<p>1 - MOUNTED;</p><p>2 - READ WRITE;</p><p>3 - READ ONLY;</p><p>4 - READ ONLY WITH APPLY (a physical standby database is open in real-time query mode).</p>|Dependent item|oracle.db_open_mode["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..{#DBNAME}.open_mode.first()`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Database '{#DBNAME}': Role|<p>The current role of the database where:</p><p>1 - SNAPSHOT STANDBY;</p><p>2 - LOGICAL STANDBY;</p><p>3 - PHYSICAL STANDBY;</p><p>4 - PRIMARY;</p><p>5 - FAR SYNC.</p>|Dependent item|oracle.db_role["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..{#DBNAME}.role.first()`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Database '{#DBNAME}': Log mode|<p>The archive log mode where:</p><p>0 - NOARCHIVELOG;</p><p>1 - ARCHIVELOG;</p><p>2 - MANUAL.</p>|Dependent item|oracle.db_log_mode["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..{#DBNAME}.log_mode.first()`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Database '{#DBNAME}': Force logging|<p>Indicates whether the database is under force logging mode (`YES`/`NO`).</p>|Dependent item|oracle.db_force_logging["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..{#DBNAME}.force_logging.first()`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|

### Trigger prototypes for Database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Oracle: Database '{#DBNAME}': Open status in mount mode|<p>The Oracle Database is in a mounted state.</p>|`last(/Oracle by Zabbix agent 2/oracle.db_open_mode["{#DBNAME}"])=1`|Warning||
|Oracle: Database '{#DBNAME}': Open status has changed|<p>The Oracle Database open status has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by Zabbix agent 2/oracle.db_open_mode["{#DBNAME}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.db_open_mode["{#DBNAME}"],#2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Oracle: Database '{#DBNAME}': Open status in mount mode</li></ul>|
|Oracle: Database '{#DBNAME}': Role has changed|<p>The Oracle Database role has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by Zabbix agent 2/oracle.db_role["{#DBNAME}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.db_role["{#DBNAME}"],#2)`|Info|**Manual close**: Yes|
|Oracle: Database '{#DBNAME}': Force logging is deactivated for DB with active Archivelog|<p>Force logging mode is a very important metric for databases in `ARCHIVELOG`. This feature allows to forcibly write all the transactions to the redo log.</p>|`last(/Oracle by Zabbix agent 2/oracle.db_force_logging["{#DBNAME}"]) = 0 and last(/Oracle by Zabbix agent 2/oracle.db_log_mode["{#DBNAME}"]) = 1`|Warning||

### LLD rule PDB discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PDB discovery|<p>Scanning a pluggable database (PDB) in DBMS.</p>|Zabbix agent|oracle.pdb.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|

### Item prototypes for PDB discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database '{#DBNAME}': Get PDB info|<p>Gets the information about the PDB database on an instance.</p>|Zabbix agent|oracle.pdb.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}","{#DBNAME}"]|
|Database '{#DBNAME}': Open status|<p>1 - MOUNTED;</p><p>2 - READ WRITE;</p><p>3 - READ ONLY;</p><p>4 - READ ONLY WITH APPLY (a physical standby database is open in real-time query mode).</p>|Dependent item|oracle.pdb_open_mode["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..{#DBNAME}.open_mode.first()`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|

### Trigger prototypes for PDB discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Oracle: Database '{#DBNAME}': Open status in mount mode|<p>The Oracle Database is in a mounted state.</p>|`last(/Oracle by Zabbix agent 2/oracle.pdb_open_mode["{#DBNAME}"])=1`|Warning||
|Oracle: Database '{#DBNAME}': Open status has changed|<p>The Oracle Database open status has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by Zabbix agent 2/oracle.pdb_open_mode["{#DBNAME}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.pdb_open_mode["{#DBNAME}"],#2)`|Info|**Manual close**: Yes|

### LLD rule Tablespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tablespace discovery|<p>Scanning tablespaces in DBMS.</p>|Zabbix agent|oracle.ts.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|

### Item prototypes for Tablespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|'{#CON_NAME}' TBS '{#TABLESPACE}': Get tablespaces stats|<p>Gets the statistics of the tablespace.</p>|Zabbix agent|oracle.ts.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}","{#TABLESPACE}","{#CONTENTS}","{#CON_NAME}"]|
|'{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace allocated, bytes|<p>Currently allocated bytes for the tablespace (sum of the current size of datafiles).</p>|Dependent item|oracle.tbs_alloc_bytes["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#TABLESPACE}'].file_bytes.first()`</p></li></ul>|
|'{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace MAX size, bytes|<p>The maximum size of the tablespace.</p>|Dependent item|oracle.tbs_max_bytes["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#TABLESPACE}'].max_bytes.first()`</p></li></ul>|
|'{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace used, bytes|<p>Currently used bytes for the tablespace (current size of datafiles minus the free space).</p>|Dependent item|oracle.tbs_used_bytes["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#TABLESPACE}'].used_bytes.first()`</p></li></ul>|
|'{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace free, bytes|<p>Free bytes of the allocated space.</p>|Dependent item|oracle.tbs_free_bytes["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#TABLESPACE}'].free_bytes.first()`</p></li></ul>|
|'{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage, percent|<p>Used bytes/allocated bytes*100.</p>|Dependent item|oracle.tbs_used_file_pct["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#TABLESPACE}'].used_file_pct.first()`</p></li></ul>|
|'{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace allocated, percent|<p>Allocated bytes/max bytes*100.</p>|Dependent item|oracle.tbs_used_pct["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#TABLESPACE}'].used_pct_max.first()`</p></li></ul>|
|'{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage from MAX, percent|<p>Used bytes/max bytes*100.</p>|Dependent item|oracle.tbs_used_from_max_pct["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#TABLESPACE}'].used_from_max_pct.first()`</p></li></ul>|
|'{#CON_NAME}' TBS '{#TABLESPACE}': Open status|<p>The tablespace status where:</p><p>1 - ONLINE;</p><p>2 - OFFLINE;</p><p>3 - READ ONLY.</p>|Dependent item|oracle.tbs_status["{#CON_NAME}","{#TABLESPACE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#TABLESPACE}'].status.first()`</p></li></ul>|

### Trigger prototypes for Tablespace discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage is too high|<p>The usage of the tablespace `{#TABLESPACE}` exceeds `{$ORACLE.TBS.USED.PCT.MAX.WARN}`%</p>|`min(/Oracle by Zabbix agent 2/oracle.tbs_used_file_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage is too high</li></ul>|
|Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage is too high|<p>The usage of the tablespace `{#TABLESPACE}` exceeds `{$ORACLE.TBS.USED.PCT.MAX.HIGH}`%</p>|`min(/Oracle by Zabbix agent 2/oracle.tbs_used_file_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.HIGH}`|High||
|Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace utilization is too high|<p>The utilization of the tablespace `{#TABLESPACE}` exceeds `{$ORACLE.TBS.UTIL.PCT.MAX.WARN}`%</p>|`min(/Oracle by Zabbix agent 2/oracle.tbs_used_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.UTIL.PCT.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace utilization is too high</li></ul>|
|Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace utilization is too high|<p>The utilization of the tablespace `{#TABLESPACE}` exceeds `{$ORACLE.TBS.UTIL.PCT.MAX.HIGH}`%</p>|`min(/Oracle by Zabbix agent 2/oracle.tbs_used_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.UTIL.PCT.MAX.HIGH}`|High||
|Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace usage from MAX is too high|<p>The usage of the tablespace `{#TABLESPACE}` from MAX exceeds `{$ORACLE.TBS.USED.PCT.FROM.MAX.WARN}`%</p>|`min(/Oracle by Zabbix agent 2/oracle.tbs_used_from_max_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.FROM.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace utilization from MAX is too high</li></ul>|
|Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace utilization from MAX is too high|<p>The usage of the tablespace `{#TABLESPACE}` from MAX exceeds `{$ORACLE.TBS.USED.PCT.FROM.MAX.HIGH}`%</p>|`min(/Oracle by Zabbix agent 2/oracle.tbs_used_from_max_pct["{#CON_NAME}","{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.FROM.MAX.HIGH}`|High||
|Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace is OFFLINE|<p>The tablespace is in the offline state.</p>|`last(/Oracle by Zabbix agent 2/oracle.tbs_status["{#CON_NAME}","{#TABLESPACE}"])=2`|Warning||
|Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace status has changed|<p>Oracle tablespace status has changed. Acknowledge to close the problem manually.</p>|`last(/Oracle by Zabbix agent 2/oracle.tbs_status["{#CON_NAME}","{#TABLESPACE}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.tbs_status["{#CON_NAME}","{#TABLESPACE}"],#2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Oracle: '{#CON_NAME}' TBS '{#TABLESPACE}': Tablespace is OFFLINE</li></ul>|

### LLD rule Archive log discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Archive log discovery|<p>Destinations of the log archive.</p>|Zabbix agent|oracle.archive.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|

### Item prototypes for Archive log discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Archivelog '{#DEST_NAME}': Get archive log info|<p>Gets the archive log statistics.</p>|Zabbix agent|oracle.archive.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}","{#DEST_NAME}"]|
|Archivelog '{#DEST_NAME}': Error|<p>Displays the error message.</p>|Dependent item|oracle.archivelog_error["{#DEST_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#DEST_NAME}'].error.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Archivelog '{#DEST_NAME}': Last sequence|<p>Identifies the sequence number of the last archived redo log to be archived.</p>|Dependent item|oracle.archivelog_log_sequence["{#DEST_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#DEST_NAME}'].log_sequence.first()`</p></li></ul>|
|Archivelog '{#DEST_NAME}': Status|<p>Identifies the current status of the destination where:</p><p>1 - VALID;</p><p>2 - DEFERRED;</p><p>3 - ERROR;</p><p>0 - UNKNOWN.</p>|Dependent item|oracle.archivelog_log_status["{#DEST_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#DEST_NAME}'].status.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Archive log discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Oracle: Archivelog '{#DEST_NAME}': Log Archive is not valid|<p>The trigger will launch if the archive log destination is not in one of these states:<br>2 - DEFERRED;<br>3 - VALID.</p>|`last(/Oracle by Zabbix agent 2/oracle.archivelog_log_status["{#DEST_NAME}"])<2`|High||

### LLD rule ASM disk groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ASM disk groups discovery|<p>The ASM disk groups.</p>|Zabbix agent|oracle.diskgroups.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]|

### Item prototypes for ASM disk groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ASM '{#DGNAME}': Get ASM stats|<p>Gets the ASM disk group statistics.</p>|Zabbix agent|oracle.diskgroups.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}","{#DGNAME}"]|
|ASM '{#DGNAME}': Total size|<p>The total size of the ASM disk group.</p>|Dependent item|oracle.asm_total_size["{#DGNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#DGNAME}'].total_bytes.first()`</p></li></ul>|
|ASM '{#DGNAME}': Free size|<p>The free size of the ASM disk group.</p>|Dependent item|oracle.asm_free_size["{#DGNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#DGNAME}'].free_bytes.first()`</p></li></ul>|
|ASM '{#DGNAME}': Used size, percent|<p>Usage of the ASM disk group expressed in %.</p>|Dependent item|oracle.asm_used_pct["{#DGNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#DGNAME}'].used_pct.first()`</p></li></ul>|

### Trigger prototypes for ASM disk groups discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Oracle: ASM '{#DGNAME}': Disk group usage is too high|<p>The usage of the ASM disk group expressed in % exceeds `{$ORACLE.ASM.USED.PCT.MAX.WARN}`.</p>|`min(/Oracle by Zabbix agent 2/oracle.asm_used_pct["{#DGNAME}"],5m)>{$ORACLE.ASM.USED.PCT.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Oracle: ASM '{#DGNAME}': Disk group usage is too high</li></ul>|
|Oracle: ASM '{#DGNAME}': Disk group usage is too high|<p>The usage of the ASM disk group expressed in % exceeds `{$ORACLE.ASM.USED.PCT.MAX.HIGH}`.</p>|`min(/Oracle by Zabbix agent 2/oracle.asm_used_pct["{#DGNAME}"],5m)>{$ORACLE.ASM.USED.PCT.MAX.HIGH}`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

