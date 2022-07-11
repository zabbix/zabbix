
# Oracle by Zabbix agent 2

## Overview

For Zabbix version: 6.0 and higher  
The template is developed for monitoring DBMS Oracle Database single instance via Zabbix agent 2.

This template was tested on:

- Oracle Database, version 12c2, 18c, 19c

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

1. Setup and configure zabbix-agent2 compiled with the [Oracle monitoring plugin](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/src/go/plugins/oracle/README.md).
2. Set the {$ORACLE.CONNSTRING} such as <protocol(host:port)> or named session.
3. Set the user name and password in host macros ({$ORACLE.USER}, {$ORACLE.PASSWORD}, {$ORACLE.SERVICE}) if you want to override parameters from the Zabbix agent configuration file.

Test availability:
 ```zabbix_get -s oracle-host -k  oracle.ping["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]```


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ORACLE.ASM.USED.PCT.MAX.HIGH} |<p>Maximum percentage of used ASM disk group for high trigger expression.</p> |`95` |
|{$ORACLE.ASM.USED.PCT.MAX.WARN} |<p>Maximum percentage of used ASM disk group for warning trigger expression.</p> |`90` |
|{$ORACLE.CONCURRENCY.MAX.WARN} |<p>Maximum percentage of sessions concurrency usage for trigger expression.</p> |`80` |
|{$ORACLE.CONNSTRING} |<p>-</p> |`tcp://localhost:1521` |
|{$ORACLE.DB.FILE.MAX.WARN} |<p>Maximum percentage of database files for trigger expression.</p> |`80` |
|{$ORACLE.DBNAME.MATCHES} |<p>This macro is used in database discovery. It can be overridden on the host or linked template level.</p> |`.*` |
|{$ORACLE.DBNAME.NOT_MATCHES} |<p>This macro is used in database discovery. It can be overridden on a host or linked template level.</p> |`PDB\$SEED` |
|{$ORACLE.EXPIRE.PASSWORD.MIN.WARN} |<p>Number of days of warning before password expires (for trigger expression).</p> |`7` |
|{$ORACLE.PASSWORD} |<p>Oracle user password.</p> |`zabbix_password` |
|{$ORACLE.PGA.USE.MAX.WARN} |<p>Maximum percentage of PGA usage alert threshold (for trigger expression).</p> |`90` |
|{$ORACLE.PROCESSES.MAX.WARN} |<p>Maximum percentage of active processes alert threshold (for trigger expression).</p> |`80` |
|{$ORACLE.REDO.MIN.WARN} |<p>Minimum number of REDO logs alert threshold (for trigger expression).</p> |`3` |
|{$ORACLE.SERVICE} |<p>Oracle Service Name</p> |`ORA` |
|{$ORACLE.SESSION.LOCK.MAX.TIME} |<p>Maximum session lock duration in seconds for count the session as a prolongedly locked query.</p> |`600` |
|{$ORACLE.SESSION.LONG.LOCK.MAX.WARN} |<p>Maximum number of the prolongedly locked sessions alert threshold (for trigger expression).</p> |`3` |
|{$ORACLE.SESSIONS.LOCK.MAX.WARN} |<p>Maximum percentage of locked sessions alert threshold (for trigger expression).</p> |`20` |
|{$ORACLE.SESSIONS.MAX.WARN} |<p>Maximum percentage of active sessions alert threshold (for trigger expression).</p> |`80` |
|{$ORACLE.SHARED.FREE.MIN.WARN} |<p>Minimum percentage of free shared pool alert threshold (for trigger expression).</p> |`5` |
|{$ORACLE.TABLESPACE.NAME.MATCHES} |<p>This macro is used in tablespace discovery. It can be overridden on a host or linked template level.</p> |`.*` |
|{$ORACLE.TABLESPACE.NAME.NOT_MATCHES} |<p>This macro is used in tablespace discovery. It can be overridden on a host or linked template level.</p> |`CHANGE_IF_NEEDED` |
|{$ORACLE.TBS.USED.PCT.MAX.HIGH} |<p>Maximum percentage of used (Used bytes/Allocated bytes) tablespace high severity alert threshold (for trigger expression).</p> |`95` |
|{$ORACLE.TBS.USED.PCT.MAX.WARN} |<p>Maximum percentage of used (Used bytes/Allocated bytes) tablespace warning severity alert threshold (for trigger expression).</p> |`90` |
|{$ORACLE.TBS.UTIL.PCT.MAX.HIGH} |<p>Maximum percentage of utilization (Allocated bytes/Max bytes) tablespace high severity alert threshold (for trigger expression).</p> |`90` |
|{$ORACLE.TBS.UTIL.PCT.MAX.WARN} |<p>Maximum percentage of utilization (Allocated bytes/Max bytes) tablespace warning severity alert threshold (for trigger expression).</p> |`80` |
|{$ORACLE.USER} |<p>Oracle username.</p> |`zabbix` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Archive log discovery |<p>Log archive destinations.</p> |ZABBIX_PASSIVE |oracle.archive.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|ASM disk groups discovery |<p>ASM disk groups</p> |ZABBIX_PASSIVE |oracle.diskgroups.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Database discovery |<p>Scanning databases in DBMS.</p> |ZABBIX_PASSIVE |oracle.db.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Filter**:</p>AND <p>- {#DBNAME} MATCHES_REGEX `{$ORACLE.DBNAME.MATCHES}`</p><p>- {#DBNAME} NOT_MATCHES_REGEX `{$ORACLE.DBNAME.NOT_MATCHES}`</p> |
|PDB discovery |<p>Scanning PDB in DBMS.</p> |ZABBIX_PASSIVE |oracle.pdb.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Filter**:</p>AND <p>- {#DBNAME} MATCHES_REGEX `{$ORACLE.DBNAME.MATCHES}`</p><p>- {#DBNAME} NOT_MATCHES_REGEX `{$ORACLE.DBNAME.NOT_MATCHES}`</p> |
|Tablespace discovery |<p>Scanning tablespaces in DBMS.</p> |ZABBIX_PASSIVE |oracle.ts.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Filter**:</p>AND <p>- {#TABLESPACE} MATCHES_REGEX `{$ORACLE.TABLESPACE.NAME.MATCHES}`</p><p>- {#TABLESPACE} NOT_MATCHES_REGEX `{$ORACLE.TABLESPACE.NAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Oracle |Oracle: Ping |<p>Test the connection to Oracle Database state</p> |ZABBIX_PASSIVE |oracle.ping["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Oracle |Oracle: Version |<p>Oracle Server version.</p> |DEPENDENT |oracle.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Oracle |Oracle: Uptime |<p>Oracle instance uptime in seconds.</p> |DEPENDENT |oracle.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.uptime`</p> |
|Oracle |Oracle: Instance status |<p>Status of the instance.</p> |DEPENDENT |oracle.instance_status<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p> |
|Oracle |Oracle: Archiver state |<p>Automatic archiving status.</p> |DEPENDENT |oracle.archiver_state<p>**Preprocessing**:</p><p>- JSONPATH: `$..archiver.first()`</p> |
|Oracle |Oracle: Instance name |<p>Name of the instance.</p> |DEPENDENT |oracle.instance_name<p>**Preprocessing**:</p><p>- JSONPATH: `$.instance`</p> |
|Oracle |Oracle: Instance hostname |<p>Name of the host machine.</p> |DEPENDENT |oracle.instance_hostname<p>**Preprocessing**:</p><p>- JSONPATH: `$..hostname.first()`</p> |
|Oracle |Oracle: Instance role |<p>Indicates whether the instance is an active instance or an inactive secondary instance.</p> |DEPENDENT |oracle.instance.role<p>**Preprocessing**:</p><p>- JSONPATH: `$.role`</p> |
|Oracle |Oracle: Buffer cache hit ratio |<p>Ratio of buffer cache hits. (LogRead - PhyRead)/LogRead</p> |DEPENDENT |oracle.buffer_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Buffer Cache Hit Ratio']`</p> |
|Oracle |Oracle: Cursor cache hit ratio |<p>Ratio of cursor cache hits. CursorCacheHit/SoftParse</p> |DEPENDENT |oracle.cursor_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Cursor Cache Hit Ratio']`</p> |
|Oracle |Oracle: Library cache hit ratio |<p>Ratio of library cache hits. Hits/Pins</p> |DEPENDENT |oracle.library_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Library Cache Hit Ratio']`</p> |
|Oracle |Oracle: Shared pool free % |<p>Shared pool free memory percent. Free/Total</p> |DEPENDENT |oracle.shared_pool_free<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Shared Pool Free %']`</p> |
|Oracle |Oracle: Physical reads per second |<p>Reads per second.</p> |DEPENDENT |oracle.physical_reads_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Physical Reads Per Sec']`</p> |
|Oracle |Oracle: Physical writes per second |<p>Writes per second.</p> |DEPENDENT |oracle.physical_writes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Physical Writes Per Sec']`</p> |
|Oracle |Oracle: Physical reads bytes per second |<p>Read bytes per second.</p> |DEPENDENT |oracle.physical_read_bytes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Physical Read Bytes Per Sec']`</p> |
|Oracle |Oracle: Physical writes bytes per second |<p>Write bytes per second.</p> |DEPENDENT |oracle.physical_write_bytes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Physical Write Bytes Per Sec']`</p> |
|Oracle |Oracle: Enqueue timeouts per second |<p>Enqueue timeouts per second.</p> |DEPENDENT |oracle.enqueue_timeouts_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Enqueue Timeouts Per Sec']`</p> |
|Oracle |Oracle: GC CR block received per second |<p>GC CR block received per second.</p> |DEPENDENT |oracle.gc_cr_block_received_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['GC CR Block Received Per Second']`</p> |
|Oracle |Oracle: Global cache blocks corrupted |<p>The number of blocks that encountered a corruption or checksum failure during interconnect.</p> |DEPENDENT |oracle.cache_blocks_corrupt<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Global Cache Blocks Corrupted']`</p> |
|Oracle |Oracle: Global cache blocks lost |<p>The number of global cache blocks lost</p> |DEPENDENT |oracle.cache_blocks_lost<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Global Cache Blocks Lost']`</p> |
|Oracle |Oracle: Logons per second |<p>The number of logon attempts.</p> |DEPENDENT |oracle.logons_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Logons Per Sec']`</p> |
|Oracle |Oracle: Average active sessions |<p>The average active sessions at a point in time. It is the number of sessions that are either working or waiting.</p> |DEPENDENT |oracle.active_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Average Active Sessions']`</p> |
|Oracle |Oracle: Active serial sessions |<p>The number of active serial sessions.</p> |DEPENDENT |oracle.active_serial_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Active Serial Sessions']`</p> |
|Oracle |Oracle: Active parallel sessions |<p>The number of active parallel sessions.</p> |DEPENDENT |oracle.active_parallel_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Active Parallel Sessions']`</p> |
|Oracle |Oracle: Long table scans per second |<p>The number of long table scans per second. A table is considered 'long' if the table is not cached and if its high-water mark is greater than 5 blocks.</p> |DEPENDENT |oracle.long_table_scans_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Long Table Scans Per Sec']`</p> |
|Oracle |Oracle: SQL service response time |<p>SQL service response time in seconds.</p> |DEPENDENT |oracle.service_response_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.['SQL Service Response Time']`</p><p>- MULTIPLIER: `0.01`</p> |
|Oracle |Oracle: User rollbacks per second |<p>The number of times that users manually issue the ROLLBACK statement or an error occurred during a user's transactions.</p> |DEPENDENT |oracle.user_rollbacks_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['User Rollbacks Per Sec']`</p> |
|Oracle |Oracle: Total sorts per user call |<p>Total sorts per user call.</p> |DEPENDENT |oracle.sorts_per_user_call<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Total Sorts Per User Call']`</p> |
|Oracle |Oracle: Rows per sort |<p>The average number of rows per sort for all types of sorts performed.</p> |DEPENDENT |oracle.rows_per_sort<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Rows Per Sort']`</p> |
|Oracle |Oracle: Disk sort per second |<p>The number of sorts going to disk per second</p> |DEPENDENT |oracle.disk_sorts<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Disk Sort Per Sec']`</p> |
|Oracle |Oracle: Memory sorts ratio |<p>The percentage of sorts (from ORDER BY clauses or index building) that are done to disk vs in-memory.</p> |DEPENDENT |oracle.memory_sorts_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Memory Sorts Ratio']`</p> |
|Oracle |Oracle: Database wait time ratio |<p>Wait time: the time that the server process spends waiting for available shared resources (to be released by other server processes) such as latches, locks, data buffers, and so on</p> |DEPENDENT |oracle.database_wait_time_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Database Wait Time Ratio']`</p> |
|Oracle |Oracle: Database CPU time ratio |<p>Calculated by dividing the total CPU used by the database by the Oracle time model statistic DB time.</p> |DEPENDENT |oracle.database_cpu_time_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Database CPU Time Ratio']`</p> |
|Oracle |Oracle: Temp space used |<p>Temp space used.</p> |DEPENDENT |oracle.temp_space_used<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Temp Space Used']`</p> |
|Oracle |Oracle: Sessions limit |<p>User and system sessions.</p> |DEPENDENT |oracle.session_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.sessions`</p> |
|Oracle |Oracle: Datafiles limit |<p>Max allowable number of  datafile.</p> |DEPENDENT |oracle.db_files_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.db_files`</p> |
|Oracle |Oracle: Processes limit |<p>Max user processes.</p> |DEPENDENT |oracle.processes_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.processes`</p> |
|Oracle |Oracle: Session count |<p>Session count.</p> |DEPENDENT |oracle.session_count<p>**Preprocessing**:</p><p>- JSONPATH: `$.total`</p> |
|Oracle |Oracle: Active user sessions |<p>The number of active user sessions.</p> |DEPENDENT |oracle.session_active_user<p>**Preprocessing**:</p><p>- JSONPATH: `$.active_user`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: Active background sessions |<p>The number of active background sessions.</p> |DEPENDENT |oracle.session_active_background<p>**Preprocessing**:</p><p>- JSONPATH: `$.active_background`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: Inactive user sessions |<p>The number of inactive user sessions.</p> |DEPENDENT |oracle.session_inactive_user<p>**Preprocessing**:</p><p>- JSONPATH: `$.inactive_user`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: Sessions lock rate |<p>The percentage of locked sessions. Locks are mechanisms that prevent destructive interaction between transactions accessing the same resource—either user objects such as tables and rows or system objects not visible to users, such as shared data structures in memory and data dictionary rows.</p> |DEPENDENT |oracle.session_lock_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.lock_rate`</p> |
|Oracle |Oracle: Sessions locked over {$ORACLE.SESSION.LOCK.MAX.TIME}s |<p>Count of the prolongedly locked sessions. (You can change maximum session lock duration in seconds for query by {$ORACLE.SESSION.LOCK.MAX.TIME} macro. Default 600 sec)</p> |DEPENDENT |oracle.session_long_time_locked<p>**Preprocessing**:</p><p>- JSONPATH: `$.long_time_locked`</p> |
|Oracle |Oracle: Sessions concurrency |<p>The percentage of concurrency. Concurrency is a DB behavior when different transactions request to change the same resource - in case of modifying data transactions sequentially block temporarily the right to change data, the rest of the transactions are waiting for access. In the case when access for resource is locked for a long time, then the concurrency grows (like the transaction queue) and this often has an extremely negative impact on performance. A high contention value does not indicate the root cause of the problem, but is a signal to search for it.</p> |DEPENDENT |oracle.session_concurrency_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.concurrency_rate`</p> |
|Oracle |Oracle: PGA, Total inuse |<p>Indicates how much PGA memory is currently consumed by work areas. This number can be used to determine how much memory is consumed by other consumers of the PGA memory (for example, PL/SQL or Java).</p> |DEPENDENT |oracle.total_pga_used<p>**Preprocessing**:</p><p>- JSONPATH: `$.['total PGA inuse']`</p> |
|Oracle |Oracle: PGA, Aggregate target parameter |<p>Current value of the PGA_AGGREGATE_TARGET initialization parameter. If this parameter is not set, then its value is 0 and automatic management of PGA memory is disabled.</p> |DEPENDENT |oracle.pga_target<p>**Preprocessing**:</p><p>- JSONPATH: `$.['aggregate PGA target parameter']`</p> |
|Oracle |Oracle: PGA, Total allocated |<p>Current amount of PGA memory allocated by the instance. The Oracle Database attempts to keep this number below the value of the PGA_AGGREGATE_TARGET initialization parameter. However, it is possible for the PGA allocated to exceed that value by a small percentage and for a short period of time when the work area workload is increasing very rapidly or when PGA_AGGREGATE_TARGET is set to a small value.</p> |DEPENDENT |oracle.total_pga_allocated<p>**Preprocessing**:</p><p>- JSONPATH: `$.['total PGA allocated']`</p> |
|Oracle |Oracle: PGA, Total freeable |<p>Number of bytes of PGA memory in all processes that could be freed back to the operating system.</p> |DEPENDENT |oracle.total_pga_freeable<p>**Preprocessing**:</p><p>- JSONPATH: `$.['total freeable PGA memory']`</p> |
|Oracle |Oracle: PGA, Global memory bound |<p>Maximum size of a work area executed in automatic mode.</p> |DEPENDENT |oracle.pga_global_bound<p>**Preprocessing**:</p><p>- JSONPATH: `$.['global memory bound']`</p> |
|Oracle |Oracle: FRA, Space limit |<p>Maximum amount of disk space (in bytes) that the database can use for the fast recovery area.</p> |DEPENDENT |oracle.fra_space_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.space_limit`</p> |
|Oracle |Oracle: FRA, Used space |<p>Amount of disk space (in bytes) used by fast recovery area files created in current and all previous fast recovery areas.</p> |DEPENDENT |oracle.fra_space_used<p>**Preprocessing**:</p><p>- JSONPATH: `$.space_used`</p> |
|Oracle |Oracle: FRA, Space reclaimable |<p>Total amount of disk space (in bytes) that can be created by deleting obsolete, redundant, and other low priority files from the fast recovery area.</p> |DEPENDENT |oracle.fra_space_reclaimable<p>**Preprocessing**:</p><p>- JSONPATH: `$.space_reclaimable`</p> |
|Oracle |Oracle: FRA, Number of files |<p>Number of files in the fast recovery area</p> |DEPENDENT |oracle.fra_number_of_files<p>**Preprocessing**:</p><p>- JSONPATH: `$.number_of_files`</p> |
|Oracle |Oracle: FRA, Usable space in % | |DEPENDENT |oracle.fra_usable_pct<p>**Preprocessing**:</p><p>- JSONPATH: `$.usable_pct`</p> |
|Oracle |Oracle: FRA, Number of restore points | |DEPENDENT |oracle.fra_restore_point<p>**Preprocessing**:</p><p>- JSONPATH: `$.restore_point`</p> |
|Oracle |Oracle: SGA, java pool |<p>Memory is allocated from the java pool.</p> |DEPENDENT |oracle.sga_java_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$.java_pool`</p> |
|Oracle |Oracle: SGA, large pool |<p>Memory is allocated from the large pool.</p> |DEPENDENT |oracle.sga_large_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$.large_pool`</p> |
|Oracle |Oracle: SGA, shared pool |<p>Memory is allocated from the shared pool.</p> |DEPENDENT |oracle.sga_shared_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$.shared_pool`</p> |
|Oracle |Oracle: SGA, log buffer |<p>The number of bytes allocated for the redo log buffer.</p> |DEPENDENT |oracle.sga_log_buffer<p>**Preprocessing**:</p><p>- JSONPATH: `$.log_buffer`</p> |
|Oracle |Oracle: SGA, fixed |<p>The fixed SGA is an internal housekeeping area.</p> |DEPENDENT |oracle.sga_fixed<p>**Preprocessing**:</p><p>- JSONPATH: `$.fixed_sga`</p> |
|Oracle |Oracle: SGA, buffer cache |<p>The size of the cache of standard blocks.</p> |DEPENDENT |oracle.sga_buffer_cache<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffer_cache`</p> |
|Oracle |Oracle: User's expire password |<p>The number of days before zabbix account password expired.</p> |ZABBIX_PASSIVE |oracle.user.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.exp_passwd_days_before`</p> |
|Oracle |Oracle: Redo logs available to switch |<p>Number of available for log switching inactive/unused REDO logs.</p> |ZABBIX_PASSIVE |oracle.redolog.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.available`</p> |
|Oracle |Oracle: Number of processes | |ZABBIX_PASSIVE |oracle.proc.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.proc_num`</p> |
|Oracle |Oracle: Datafiles count |<p>Current number of datafile.</p> |ZABBIX_PASSIVE |oracle.datafiles.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.datafile_num`</p> |
|Oracle |Oracle Database '{#DBNAME}': Open status |<p>1 - 'MOUNTED', 2 - 'READ WRITE', 3 - 'READ ONLY', 4 - 'READ ONLY WITH APPLY' (A physical standby database is open in real-time query mode)</p> |DEPENDENT |oracle.db_open_mode["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..{#DBNAME}.open_mode.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Role |<p>Current role of the database, 1 - 'SNAPSHOT STANDBY', 2 - 'LOGICAL STANDBY', 3 - 'PHYSICAL STANDBY', 4 - 'PRIMARY ', 5 -'FAR SYNC'</p> |DEPENDENT |oracle.db_role["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..{#DBNAME}.role.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Log mode |<p>Archive log mode, 0 - 'NOARCHIVELOG', 1 - 'ARCHIVELOG', 2 - 'MANUAL'</p> |DEPENDENT |oracle.db_log_mode["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..{#DBNAME}.log_mode.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Force logging |<p>Indicates whether the database is under force logging mode (YES) or not (NO)</p> |DEPENDENT |oracle.db_force_logging["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..{#DBNAME}.force_logging.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Open status |<p>1 - 'MOUNTED', 2 - 'READ WRITE', 3 - 'READ ONLY', 4 - 'READ ONLY WITH APPLY' (A physical standby database is open in real-time query mode)</p> |DEPENDENT |oracle.pdb_open_mode["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..{#DBNAME}.open_mode.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace allocated, bytes |<p>Currently allocated bytes for tablespace (sum of the current size of datafiles).</p> |DEPENDENT |oracle.tbs_alloc_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].file_bytes.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace MAX size, bytes |<p>Maximum size of tablespace.</p> |DEPENDENT |oracle.tbs_max_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].max_bytes.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace used, bytes |<p>Currently used bytes for tablespace (current size of datafiles-free space).</p> |DEPENDENT |oracle.tbs_used_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].used_bytes.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace free, bytes |<p>Free bytes of allocated space.</p> |DEPENDENT |oracle.tbs_free_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].free_bytes.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace usage, percent |<p>Used bytes/Allocated bytes*100</p> |DEPENDENT |oracle.tbs_used_file_pct["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].used_file_pct.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace allocated, percent |<p>Allocated bytes/Max bytes*100</p> |DEPENDENT |oracle.tbs_used_pct["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].used_pct_max.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Open status |<p>Tablespace status. 1 - 'ONLINE' 2 - 'OFFLINE' 3- 'READ ONLY'</p> |DEPENDENT |oracle.tbs_status["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].status.first()`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Error |<p>Displays the error text</p> |DEPENDENT |oracle.archivelog_error["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DEST_NAME}'].error.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Last sequence |<p>Identifies the sequence number of the last archived redo log to be archived</p> |DEPENDENT |oracle.archivelog_log_sequence["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DEST_NAME}'].log_sequence.first()`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Status |<p> Identifies the current status of the destination: 1 - 'Valid', 2 - 'Deferred',3 - 'Error', 0 - 'Unknown'</p> |DEPENDENT |oracle.archivelog_log_status["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DEST_NAME}'].status.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Oracle |ASM '{#DG_NAME}': Total size |<p>Total size of ASM disk group.</p> |DEPENDENT |oracle.asm_total_size["{#DG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DG_NAME}'].size_byte.first()`</p> |
|Oracle |ASM '{#DG_NAME}': Free size |<p>Free size of ASM disk group.</p> |DEPENDENT |oracle.asm_free_size["{#DG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DG_NAME}'].free_size_byte.first()`</p> |
|Oracle |ASM '{#DG_NAME}': Free size |<p>Usage percent of ASM disk group.</p> |DEPENDENT |oracle.asm_used_pct["{#DG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DG_NAME}'].used_percent.first()`</p> |
|Zabbix raw items |Oracle: Get instance state |<p>The item gets state of the current instance.</p> |ZABBIX_PASSIVE |oracle.instance.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get system metrics |<p>The item gets system metric values.</p> |ZABBIX_PASSIVE |oracle.sys.metrics["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get system parameters |<p>Get a set of system parameter values.</p> |ZABBIX_PASSIVE |oracle.sys.params["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get sessions stats |<p>Get sessions statistics. {$ORACLE.SESSION.LOCK.MAX.TIME} -- maximum seconds in the current wait condition for counting long time locked sessions. Default: 600 seconds.</p> |ZABBIX_PASSIVE |oracle.sessions.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}","{$ORACLE.SESSION.LOCK.MAX.TIME}"] |
|Zabbix raw items |Oracle: Get PGA stats |<p>Get PGA statistics.</p> |ZABBIX_PASSIVE |oracle.pga.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get FRA stats |<p>Get FRA statistics.</p> |ZABBIX_PASSIVE |oracle.fra.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get SGA stats |<p>Get SGA statistics.</p> |ZABBIX_PASSIVE |oracle.sga.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get tablespaces stats |<p>Get tablespaces stats.</p> |ZABBIX_PASSIVE |oracle.ts.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get CDB and No-CDB info |<p>Get info about CDB and  No-CDB databases on instansce.</p> |ZABBIX_PASSIVE |oracle.cdb.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get PDB info |<p>Get info about PDB databases on instansce.</p> |ZABBIX_PASSIVE |oracle.pdb.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get archive log info | |ZABBIX_PASSIVE |oracle.archive.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get ASM stats |<p>Get ASM disk groups stats.</p> |ZABBIX_PASSIVE |oracle.diskgroups.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Oracle: Connection to database is unavailable |<p>Connection to Oracle Database is currently unavailable.</p> |`last(/Oracle by Zabbix agent 2/oracle.ping["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"])=0` |DISASTER | |
|Oracle: Version has changed |<p>Oracle DB version has changed. Ack to close.</p> |`last(/Oracle by Zabbix agent 2/oracle.version,#1)<>last(/Oracle by Zabbix agent 2/oracle.version,#2) and length(last(/Oracle by Zabbix agent 2/oracle.version))>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Failed to fetch info data |<p>Zabbix has not received data for items for the last 5 minutes. The database might be unavailable for connecting.</p> |`nodata(/Oracle by Zabbix agent 2/oracle.uptime,30m)=1` |INFO | |
|Oracle: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Oracle by Zabbix agent 2/oracle.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Oracle: Instance name has changed |<p>Oracle DB Instance name has changed. Ack to close.</p> |`last(/Oracle by Zabbix agent 2/oracle.instance_name,#1)<>last(/Oracle by Zabbix agent 2/oracle.instance_name,#2) and length(last(/Oracle by Zabbix agent 2/oracle.instance_name))>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Instance hostname has changed |<p>Oracle DB Instance hostname has changed. Ack to close.</p> |`last(/Oracle by Zabbix agent 2/oracle.instance_hostname,#1)<>last(/Oracle by Zabbix agent 2/oracle.instance_hostname,#2) and length(last(/Oracle by Zabbix agent 2/oracle.instance_hostname))>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Shared pool free is too low |<p>The shared pool free memory percent has been less than {$ORACLE.SHARED.FREE.MIN.WARN}% in the last 5 minutes.</p> |`max(/Oracle by Zabbix agent 2/oracle.shared_pool_free,5m)<{$ORACLE.SHARED.FREE.MIN.WARN}` |WARNING | |
|Oracle: Too many active sessions |<p>Active sessions are using more than {$ORACLE.SESSIONS.MAX.WARN}% of the available sessions.</p> |`min(/Oracle by Zabbix agent 2/oracle.session_count,5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.session_limit) > {$ORACLE.SESSIONS.MAX.WARN}` |WARNING | |
|Oracle: Too many locked sessions |<p>Number of locked sessions is over {$ORACLE.SESSIONS.LOCK.MAX.WARN}% of the running sessions.</p> |`min(/Oracle by Zabbix agent 2/oracle.session_lock_rate,5m) > {$ORACLE.SESSIONS.LOCK.MAX.WARN}` |WARNING | |
|Oracle: Too many sessions locked |<p>Number of sessions locked over {$ORACLE.SESSION.LOCK.MAX.TIME} seconds is too high. Long-term locks can negatively affect database performance, therefore, if they are detected, you should first find the most difficult queries from the database point of view and analyze possible resource leaks.</p> |`min(/Oracle by Zabbix agent 2/oracle.session_long_time_locked,5m) > {$ORACLE.SESSION.LONG.LOCK.MAX.WARN}` |WARNING | |
|Oracle: Too high database concurrency |<p>Concurrency rate is over {$ORACLE.CONCURRENCY.MAX.WARN}%. A high contention value does not indicate the root cause of the problem, but is a signal to search for it. In the case of high competition, an analysis of resource consumption should be carried out, the most "heavy" queries made in the database, possibly - session tracing. All this will help determine the root cause and possible optimization points both in the database configuration and in the logic of building queries of the application itself.</p> |`min(/Oracle by Zabbix agent 2/oracle.session_concurrency_rate,5m) > {$ORACLE.CONCURRENCY.MAX.WARN}` |WARNING | |
|Oracle: Total PGA inuse is too high |<p>Total PGA in use is more than {$ORACLE.PGA.USE.MAX.WARN}% of PGA_AGGREGATE_TARGET.</p> |`min(/Oracle by Zabbix agent 2/oracle.total_pga_used,5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.pga_target) > {$ORACLE.PGA.USE.MAX.WARN}` |WARNING | |
|Oracle: Zabbix account will expire soon |<p>Password for zabbix user in the database will expire soon.</p> |`last(/Oracle by Zabbix agent 2/oracle.user.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"])  < {$ORACLE.EXPIRE.PASSWORD.MIN.WARN}` |WARNING | |
|Oracle: Number of REDO logs available for switching is too low |<p>Number of available for log switching inactive/unused REDOs is low (Database down risk)</p> |`max(/Oracle by Zabbix agent 2/oracle.redolog.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],5m) < {$ORACLE.REDO.MIN.WARN}` |WARNING | |
|Oracle: Too many active processes |<p>Active processes are using more than {$ORACLE.PROCESSES.MAX.WARN}% of the available number of processes.</p> |`min(/Oracle by Zabbix agent 2/oracle.proc.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.processes_limit) > {$ORACLE.PROCESSES.MAX.WARN}` |WARNING | |
|Oracle: Too many database files |<p>Number of datafiles is higher than {$ORACLE.DB.FILE.MAX.WARN}% of the available datafile files limit.</p> |`min(/Oracle by Zabbix agent 2/oracle.datafiles.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.db_files_limit) > {$ORACLE.DB.FILE.MAX.WARN}` |WARNING | |
|Oracle Database '{#DBNAME}': Open status in mount mode |<p>The Oracle DB has a MOUNTED state.</p> |`last(/Oracle by Zabbix agent 2/oracle.db_open_mode["{#DBNAME}"])=1` |WARNING | |
|Oracle Database '{#DBNAME}': Open status has changed |<p>Oracle DB open status has changed. Ack to close.</p> |`last(/Oracle by Zabbix agent 2/oracle.db_open_mode["{#DBNAME}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.db_open_mode["{#DBNAME}"],#2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Oracle Database '{#DBNAME}': Open status in mount mode</p> |
|Oracle Database '{#DBNAME}': Role has changed |<p>Oracle DB role has changed. Ack to close.</p> |`last(/Oracle by Zabbix agent 2/oracle.db_role["{#DBNAME}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.db_role["{#DBNAME}"],#2)` |INFO |<p>Manual close: YES</p> |
|Oracle Database '{#DBNAME}': Force logging is deactivated for DB with active Archivelog |<p>Force Logging mode  - it is very important metric for Databases in 'ARCHIVELOG'. This feature allows to forcibly write all transactions to the REDO.</p> |`last(/Oracle by Zabbix agent 2/oracle.db_force_logging["{#DBNAME}"]) = 0 and last(/Oracle by Zabbix agent 2/oracle.db_log_mode["{#DBNAME}"]) = 1` |WARNING | |
|Oracle Database '{#DBNAME}': Open status in mount mode |<p>The Oracle DB has a MOUNTED state.</p> |`last(/Oracle by Zabbix agent 2/oracle.pdb_open_mode["{#DBNAME}"])=1` |WARNING | |
|Oracle Database '{#DBNAME}': Open status has changed |<p>Oracle DB open status has changed. Ack to close.</p> |`last(/Oracle by Zabbix agent 2/oracle.pdb_open_mode["{#DBNAME}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.pdb_open_mode["{#DBNAME}"],#2)` |INFO |<p>Manual close: YES</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace usage is too high |<p>-</p> |`min(/Oracle by Zabbix agent 2/oracle.tbs_used_file_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespace usage is too high</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace usage is too high |<p>-</p> |`min(/Oracle by Zabbix agent 2/oracle.tbs_used_file_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.HIGH}` |HIGH | |
|Oracle TBS '{#TABLESPACE}': Tablespace utilization is too high |<p>-</p> |`min(/Oracle by Zabbix agent 2/oracle.tbs_used_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespace utilization is too high</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace utilization is too high |<p>-</p> |`min(/Oracle by Zabbix agent 2/oracle.tbs_used_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.UTIL.PCT.MAX.HIGH}` |HIGH | |
|Oracle TBS '{#TABLESPACE}': Tablespace is OFFLINE |<p>The tablespace is in the offline state.</p> |`last(/Oracle by Zabbix agent 2/oracle.tbs_status["{#TABLESPACE}"])=2` |WARNING | |
|Oracle TBS '{#TABLESPACE}': Tablespace status has changed |<p>Oracle tablespace status has changed. Ack to close.</p> |`last(/Oracle by Zabbix agent 2/oracle.tbs_status["{#TABLESPACE}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.tbs_status["{#TABLESPACE}"],#2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespace is OFFLINE</p> |
|Archivelog '{#DEST_NAME}': Log Archive is not valid |<p>ARL destination not in 3 - Valid or 2 - Deferred.</p> |`last(/Oracle by Zabbix agent 2/oracle.archivelog_log_status["{#DEST_NAME}"])<2` |HIGH | |
|ASM '{#DG_NAME}': Disk group usage is too high |<p>Usage percent of ASM disk group is over {$ORACLE.ASM.USED.PCT.MAX.WARN}</p> |`min(/Oracle by Zabbix agent 2/oracle.asm_used_pct["{#DG_NAME}"],5m)>{$ORACLE.ASM.USED.PCT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- ASM '{#DG_NAME}': Disk group usage is too high</p> |
|ASM '{#DG_NAME}': Disk group usage is too high |<p>Usage percent of ASM disk group is over {$ORACLE.ASM.USED.PCT.MAX.WARN}</p> |`min(/Oracle by Zabbix agent 2/oracle.asm_used_pct["{#DG_NAME}"],5m)>{$ORACLE.ASM.USED.PCT.MAX.HIGH}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

