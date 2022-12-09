
# Oracle by Zabbix agent 2

## Overview

For Zabbix version: 6.4 and higher.
The template is developed to monitor a single DBMS Oracle Database instance with Zabbix agent 2.

This template was tested on:

- Oracle Database, version 12c2, 18c, 19c

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

1. Setup and configure Zabbix agent 2 compiled with the Oracle monitoring plugin. See the setup instructions for [Oracle Database plugin](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/src/go/plugins/oracle/README.md).
2. Set the {$ORACLE.CONNSTRING} macro value using either <protocol(host:port)> or named session.
3. If you want to override parameters from Zabbix agent configuration file, set the user name, password and service name in host macros ({$ORACLE.USER}, {$ORACLE.PASSWORD}, and {$ORACLE.SERVICE}).

Test availability:
 ```zabbix_get -s oracle-host -k  oracle.ping["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]```


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ORACLE.ASM.USED.PCT.MAX.HIGH} |<p>The maximum percentage of used Automatic Storage Management (ASM) disk group for a high trigger expression.</p> |`95` |
|{$ORACLE.ASM.USED.PCT.MAX.WARN} |<p>The maximum percentage of used ASM disk group for a warning trigger expression.</p> |`90` |
|{$ORACLE.CONCURRENCY.MAX.WARN} |<p>The maximum percentage of sessions concurrency usage for a trigger expression.</p> |`80` |
|{$ORACLE.CONNSTRING} |<p>-</p> |`tcp://localhost:1521` |
|{$ORACLE.DB.FILE.MAX.WARN} |<p>The maximum percentage of used database files for a trigger expression.</p> |`80` |
|{$ORACLE.DBNAME.MATCHES} |<p>This macro is used in discovery of the database. It can be overridden on host level or its linked template level.</p> |`.*` |
|{$ORACLE.DBNAME.NOT_MATCHES} |<p>This macro is used in discovery of the database. It can be overridden on host level or its linked template level.</p> |`PDB\$SEED` |
|{$ORACLE.EXPIRE.PASSWORD.MIN.WARN} |<p>The number of warning days before the password expires for a trigger expression.</p> |`7` |
|{$ORACLE.PASSWORD} |<p>The Oracle user's password.</p> |`zabbix_password` |
|{$ORACLE.PGA.USE.MAX.WARN} |<p>The maximum percentage of the Program Global Area (PGA) usage that alerts the threshold for a trigger expression.</p> |`90` |
|{$ORACLE.PROCESSES.MAX.WARN} |<p>Alert threshold for the maximum percentage of active processes for a trigger expression.</p> |`80` |
|{$ORACLE.REDO.MIN.WARN} |<p>Alert threshold for the minimum number of REDO logs for a trigger expression.</p> |`3` |
|{$ORACLE.SERVICE} |<p>Oracle Service Name.</p> |`ORA` |
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
|{$ORACLE.USER} |<p>Oracle username.</p> |`zabbix` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Archive log discovery |<p>Destinations of the log archive.</p> |ZABBIX_PASSIVE |oracle.archive.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|ASM disk groups discovery |<p>The ASM disk groups.</p> |ZABBIX_PASSIVE |oracle.diskgroups.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Database discovery |<p>Scanning databases in the database management system (DBMS).</p> |ZABBIX_PASSIVE |oracle.db.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Filter**:</p>AND <p>- {#DBNAME} MATCHES_REGEX `{$ORACLE.DBNAME.MATCHES}`</p><p>- {#DBNAME} NOT_MATCHES_REGEX `{$ORACLE.DBNAME.NOT_MATCHES}`</p> |
|PDB discovery |<p>Scanning a pluggable database (PDB) in DBMS.</p> |ZABBIX_PASSIVE |oracle.pdb.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Filter**:</p>AND <p>- {#DBNAME} MATCHES_REGEX `{$ORACLE.DBNAME.MATCHES}`</p><p>- {#DBNAME} NOT_MATCHES_REGEX `{$ORACLE.DBNAME.NOT_MATCHES}`</p> |
|Tablespace discovery |<p>Scanning tablespaces in DBMS.</p> |ZABBIX_PASSIVE |oracle.ts.discovery["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Filter**:</p>AND <p>- {#TABLESPACE} MATCHES_REGEX `{$ORACLE.TABLESPACE.NAME.MATCHES}`</p><p>- {#TABLESPACE} NOT_MATCHES_REGEX `{$ORACLE.TABLESPACE.NAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Oracle |Oracle: Ping |<p>Test the connection to Oracle Database state.</p> |ZABBIX_PASSIVE |oracle.ping["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Oracle |Oracle: Version |<p>The Oracle Server version.</p> |DEPENDENT |oracle.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Oracle |Oracle: Uptime |<p>The Oracle instance uptime expressed in seconds.</p> |DEPENDENT |oracle.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.uptime`</p> |
|Oracle |Oracle: Instance status |<p>The status of the instance.</p> |DEPENDENT |oracle.instance_status<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p> |
|Oracle |Oracle: Archiver state |<p>The status of automatic archiving.</p> |DEPENDENT |oracle.archiver_state<p>**Preprocessing**:</p><p>- JSONPATH: `$..archiver.first()`</p> |
|Oracle |Oracle: Instance name |<p>The name of an instance.</p> |DEPENDENT |oracle.instance_name<p>**Preprocessing**:</p><p>- JSONPATH: `$.instance`</p> |
|Oracle |Oracle: Instance hostname |<p>The name of the host machine.</p> |DEPENDENT |oracle.instance_hostname<p>**Preprocessing**:</p><p>- JSONPATH: `$..hostname.first()`</p> |
|Oracle |Oracle: Instance role |<p>It indicates whether the instance is an active instance or an inactive secondary instance.</p> |DEPENDENT |oracle.instance.role<p>**Preprocessing**:</p><p>- JSONPATH: `$.role`</p> |
|Oracle |Oracle: Buffer cache hit ratio |<p>The ratio of buffer cache hits ((LogRead - PhyRead)/LogRead).</p> |DEPENDENT |oracle.buffer_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Buffer Cache Hit Ratio']`</p> |
|Oracle |Oracle: Cursor cache hit ratio |<p>The ratio of cursor cache hits (CursorCacheHit/SoftParse).</p> |DEPENDENT |oracle.cursor_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Cursor Cache Hit Ratio']`</p> |
|Oracle |Oracle: Library cache hit ratio |<p>The ratio of library cache hits (Hits/Pins).</p> |DEPENDENT |oracle.library_cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Library Cache Hit Ratio']`</p> |
|Oracle |Oracle: Shared pool free % |<p>Free memory of a shared pool expressed in %.</p> |DEPENDENT |oracle.shared_pool_free<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Shared Pool Free %']`</p> |
|Oracle |Oracle: Physical reads per second |<p>Reads per second.</p> |DEPENDENT |oracle.physical_reads_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Physical Reads Per Sec']`</p> |
|Oracle |Oracle: Physical writes per second |<p>Writes per second.</p> |DEPENDENT |oracle.physical_writes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Physical Writes Per Sec']`</p> |
|Oracle |Oracle: Physical reads bytes per second |<p>Read bytes per second.</p> |DEPENDENT |oracle.physical_read_bytes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Physical Read Bytes Per Sec']`</p> |
|Oracle |Oracle: Physical writes bytes per second |<p>Write bytes per second.</p> |DEPENDENT |oracle.physical_write_bytes_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Physical Write Bytes Per Sec']`</p> |
|Oracle |Oracle: Enqueue timeouts per second |<p>Enqueue timeouts per second.</p> |DEPENDENT |oracle.enqueue_timeouts_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Enqueue Timeouts Per Sec']`</p> |
|Oracle |Oracle: GC CR block received per second |<p>The global cache (GC) and the consistent read (CR) block received per second.</p> |DEPENDENT |oracle.gc_cr_block_received_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['GC CR Block Received Per Second']`</p> |
|Oracle |Oracle: Global cache blocks corrupted |<p>The number of blocks that encountered corruption or checksum failure during the interconnect.</p> |DEPENDENT |oracle.cache_blocks_corrupt<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Global Cache Blocks Corrupted']`</p> |
|Oracle |Oracle: Global cache blocks lost |<p>The number of lost global cache blocks.</p> |DEPENDENT |oracle.cache_blocks_lost<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Global Cache Blocks Lost']`</p> |
|Oracle |Oracle: Logons per second |<p>The number of logon attempts.</p> |DEPENDENT |oracle.logons_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Logons Per Sec']`</p> |
|Oracle |Oracle: Average active sessions |<p>The average active sessions at a point in time. The number of sessions that are either working or waiting.</p> |DEPENDENT |oracle.active_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Average Active Sessions']`</p> |
|Oracle |Oracle: Active serial sessions |<p>The number of active serial sessions.</p> |DEPENDENT |oracle.active_serial_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Active Serial Sessions']`</p> |
|Oracle |Oracle: Active parallel sessions |<p>The number of active parallel sessions.</p> |DEPENDENT |oracle.active_parallel_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Active Parallel Sessions']`</p> |
|Oracle |Oracle: Long table scans per second |<p>The number of long table scans per second. A table is considered 'long' if the table is not cached and if its high-water mark is greater than five blocks.</p> |DEPENDENT |oracle.long_table_scans_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Long Table Scans Per Sec']`</p> |
|Oracle |Oracle: SQL service response time |<p>The Structured Query Language (SQL) service response time expressed in seconds.</p> |DEPENDENT |oracle.service_response_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.['SQL Service Response Time']`</p><p>- MULTIPLIER: `0.01`</p> |
|Oracle |Oracle: User rollbacks per second |<p>The number of times that users manually issue the ROLLBACK statement or an error occurred during the users' transactions.</p> |DEPENDENT |oracle.user_rollbacks_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['User Rollbacks Per Sec']`</p> |
|Oracle |Oracle: Total sorts per user call |<p>The total sorts per user call.</p> |DEPENDENT |oracle.sorts_per_user_call<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Total Sorts Per User Call']`</p> |
|Oracle |Oracle: Rows per sort |<p>The average number of rows per sort for all types of sorts performed.</p> |DEPENDENT |oracle.rows_per_sort<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Rows Per Sort']`</p> |
|Oracle |Oracle: Disk sort per second |<p>The number of sorts going to disk per second.</p> |DEPENDENT |oracle.disk_sorts<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Disk Sort Per Sec']`</p> |
|Oracle |Oracle: Memory sorts ratio |<p>The percentage of sorts (from ORDER BY clauses or index building) that are done to disk vs in-memory.</p> |DEPENDENT |oracle.memory_sorts_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Memory Sorts Ratio']`</p> |
|Oracle |Oracle: Database wait time ratio |<p>Wait time - the time that the server process spends waiting for available shared resources to be released by other server processes, such as latches, locks, data buffers, etc.</p> |DEPENDENT |oracle.database_wait_time_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Database Wait Time Ratio']`</p> |
|Oracle |Oracle: Database CPU time ratio |<p>It is calculated by dividing the total CPU (used by the database) by the Oracle time model statistic DB time.</p> |DEPENDENT |oracle.database_cpu_time_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Database CPU Time Ratio']`</p> |
|Oracle |Oracle: Temp space used |<p>Used temporary space.</p> |DEPENDENT |oracle.temp_space_used<p>**Preprocessing**:</p><p>- JSONPATH: `$.['Temp Space Used']`</p> |
|Oracle |Oracle: Sessions limit |<p>The user and system sessions.</p> |DEPENDENT |oracle.session_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.sessions`</p> |
|Oracle |Oracle: Datafiles limit |<p>The maximum allowable number of datafiles.</p> |DEPENDENT |oracle.db_files_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.db_files`</p> |
|Oracle |Oracle: Processes limit |<p>The maximum number of user processes.</p> |DEPENDENT |oracle.processes_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.processes`</p> |
|Oracle |Oracle: Session count |<p>The count of sessions.</p> |DEPENDENT |oracle.session_count<p>**Preprocessing**:</p><p>- JSONPATH: `$.total`</p> |
|Oracle |Oracle: Active user sessions |<p>The number of active user sessions.</p> |DEPENDENT |oracle.session_active_user<p>**Preprocessing**:</p><p>- JSONPATH: `$.active_user`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: Active background sessions |<p>The number of active background sessions.</p> |DEPENDENT |oracle.session_active_background<p>**Preprocessing**:</p><p>- JSONPATH: `$.active_background`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: Inactive user sessions |<p>The number of inactive user sessions.</p> |DEPENDENT |oracle.session_inactive_user<p>**Preprocessing**:</p><p>- JSONPATH: `$.inactive_user`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Oracle |Oracle: Sessions lock rate |<p>The percentage of locked sessions. Locks are mechanisms that prevent destructive interaction between transactions accessing the same resource — either user objects, such as tables and rows or system objects not visible to users, such as shared data structures in memory and data dictionary rows.</p> |DEPENDENT |oracle.session_lock_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.lock_rate`</p> |
|Oracle |Oracle: Sessions locked over {$ORACLE.SESSION.LOCK.MAX.TIME}s |<p>The count of the prolongedly locked sessions. (You can change the duration of maximum session lock in seconds for a query by {$ORACLE.SESSION.LOCK.MAX.TIME} macro. Default is 600 sec).</p> |DEPENDENT |oracle.session_long_time_locked<p>**Preprocessing**:</p><p>- JSONPATH: `$.long_time_locked`</p> |
|Oracle |Oracle: Sessions concurrency |<p>The percentage of concurrency. Concurrency is a DB behavior when different transactions request to change the same resource. In the case of modifying data transactions, it sequentially temporarily blocks the right to change the data, the rest of the transactions are waiting for the access. In the case when the access for the resource is locked for a long time, then the concurrency grows (like the transaction queue) and this often has an extremely negative impact on the performance. A high contention value does not indicate the root cause of the problem but is a signal to search for it.</p> |DEPENDENT |oracle.session_concurrency_rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.concurrency_rate`</p> |
|Oracle |Oracle: PGA, Total inuse |<p>It indicates how much the Program Global Area (PGA) memory is currently consumed by work areas. This number can be used to determine how much memory is consumed by other consumers of the PGA memory (for example, PL/SQL or Java).</p> |DEPENDENT |oracle.total_pga_used<p>**Preprocessing**:</p><p>- JSONPATH: `$.['total PGA inuse']`</p> |
|Oracle |Oracle: PGA, Aggregate target parameter |<p>The current value of the PGA_AGGREGATE_TARGET initialization parameter. If this parameter is not set, then its value is 0 and automatic management of the PGA memory is disabled.</p> |DEPENDENT |oracle.pga_target<p>**Preprocessing**:</p><p>- JSONPATH: `$.['aggregate PGA target parameter']`</p> |
|Oracle |Oracle: PGA, Total allocated |<p>The current amount of the PGA memory allocated by the instance. The Oracle Database attempts to keep this number below the value of the PGA_AGGREGATE_TARGET initialization parameter. However, it is possible for the PGA allocated to exceed that value by a small percentage and for a short period of time when the work area workload is increasing very rapidly or when PGA_AGGREGATE_TARGET is set to a small value.</p> |DEPENDENT |oracle.total_pga_allocated<p>**Preprocessing**:</p><p>- JSONPATH: `$.['total PGA allocated']`</p> |
|Oracle |Oracle: PGA, Total freeable |<p>The number of bytes of the PGA memory in all processes that could be freed back to the operating system.</p> |DEPENDENT |oracle.total_pga_freeable<p>**Preprocessing**:</p><p>- JSONPATH: `$.['total freeable PGA memory']`</p> |
|Oracle |Oracle: PGA, Global memory bound |<p>The maximum size of work area executed in automatic mode.</p> |DEPENDENT |oracle.pga_global_bound<p>**Preprocessing**:</p><p>- JSONPATH: `$.['global memory bound']`</p> |
|Oracle |Oracle: FRA, Space limit |<p>The maximum amount of disk space (in bytes) that the database can use for the Fast Recovery Area (FRA).</p> |DEPENDENT |oracle.fra_space_limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.space_limit`</p> |
|Oracle |Oracle: FRA, Used space |<p>The amount of disk space (in bytes) used by FRA files created in the current and all the previous FRAs.</p> |DEPENDENT |oracle.fra_space_used<p>**Preprocessing**:</p><p>- JSONPATH: `$.space_used`</p> |
|Oracle |Oracle: FRA, Space reclaimable |<p>The total amount of disk space (in bytes) that can be created by deleting obsolete, redundant, and other low priority files from the FRA.</p> |DEPENDENT |oracle.fra_space_reclaimable<p>**Preprocessing**:</p><p>- JSONPATH: `$.space_reclaimable`</p> |
|Oracle |Oracle: FRA, Number of files |<p>The number of files in the FRA.</p> |DEPENDENT |oracle.fra_number_of_files<p>**Preprocessing**:</p><p>- JSONPATH: `$.number_of_files`</p> |
|Oracle |Oracle: FRA, Usable space in % | |DEPENDENT |oracle.fra_usable_pct<p>**Preprocessing**:</p><p>- JSONPATH: `$.usable_pct`</p> |
|Oracle |Oracle: FRA, Number of restore points | |DEPENDENT |oracle.fra_restore_point<p>**Preprocessing**:</p><p>- JSONPATH: `$.restore_point`</p> |
|Oracle |Oracle: SGA, java pool |<p>The memory is allocated from the Java pool.</p> |DEPENDENT |oracle.sga_java_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$.java_pool`</p> |
|Oracle |Oracle: SGA, large pool |<p>The memory is allocated from a large pool.</p> |DEPENDENT |oracle.sga_large_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$.large_pool`</p> |
|Oracle |Oracle: SGA, shared pool |<p>The memory is allocated from a shared pool.</p> |DEPENDENT |oracle.sga_shared_pool<p>**Preprocessing**:</p><p>- JSONPATH: `$.shared_pool`</p> |
|Oracle |Oracle: SGA, log buffer |<p>The number of bytes allocated for the redo log buffer.</p> |DEPENDENT |oracle.sga_log_buffer<p>**Preprocessing**:</p><p>- JSONPATH: `$.log_buffer`</p> |
|Oracle |Oracle: SGA, fixed |<p>The fixed System Global Area (SGA) is an internal housekeeping area.</p> |DEPENDENT |oracle.sga_fixed<p>**Preprocessing**:</p><p>- JSONPATH: `$.fixed_sga`</p> |
|Oracle |Oracle: SGA, buffer cache |<p>The size of standard block cache.</p> |DEPENDENT |oracle.sga_buffer_cache<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffer_cache`</p> |
|Oracle |Oracle: User's expire password |<p>The number of days before the password of Zabbix account expires.</p> |ZABBIX_PASSIVE |oracle.user.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.exp_passwd_days_before`</p> |
|Oracle |Oracle: Redo logs available to switch |<p>The number of inactive/unused redo logs available for log switching.</p> |ZABBIX_PASSIVE |oracle.redolog.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.available`</p> |
|Oracle |Oracle: Number of processes | |ZABBIX_PASSIVE |oracle.proc.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.proc_num`</p> |
|Oracle |Oracle: Datafiles count |<p>The current number of datafiles.</p> |ZABBIX_PASSIVE |oracle.datafiles.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.datafile_num`</p> |
|Oracle |Oracle Database '{#DBNAME}': Open status |<p>1 - 'MOUNTED';</p><p>2 - 'READ WRITE';</p><p>3 - 'READ ONLY';</p><p>4 - 'READ ONLY WITH APPLY' (a physical standby database is open in real-time query mode).</p> |DEPENDENT |oracle.db_open_mode["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..{#DBNAME}.open_mode.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Role |<p>The current role of the database where:</p><p>1 - 'SNAPSHOT STANDBY';</p><p>2 - 'LOGICAL STANDBY';</p><p>3 - 'PHYSICAL STANDBY';</p><p>4 - 'PRIMARY ';</p><p>5 - 'FAR SYNC'.</p> |DEPENDENT |oracle.db_role["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..{#DBNAME}.role.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Log mode |<p>The archive log mode where:</p><p>0 - 'NOARCHIVELOG';</p><p>1 - 'ARCHIVELOG';</p><p>2 - 'MANUAL'.</p> |DEPENDENT |oracle.db_log_mode["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..{#DBNAME}.log_mode.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Force logging |<p>It indicates whether the database is under force logging mode 'YES' or 'NO'.</p> |DEPENDENT |oracle.db_force_logging["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..{#DBNAME}.force_logging.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle Database '{#DBNAME}': Open status |<p>1 - 'MOUNTED';</p><p>2 - 'READ WRITE';</p><p>3 - 'READ ONLY';</p><p>4 - 'READ ONLY WITH APPLY' (a physical standby database is open in real-time query mode).</p> |DEPENDENT |oracle.pdb_open_mode["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..{#DBNAME}.open_mode.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace allocated, bytes |<p>Currently allocated bytes for the tablespace (sum of the current size of datafiles).</p> |DEPENDENT |oracle.tbs_alloc_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].file_bytes.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace MAX size, bytes |<p>The maximum size of the tablespace.</p> |DEPENDENT |oracle.tbs_max_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].max_bytes.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace used, bytes |<p>Currently used bytes for the tablespace (current size of datafiles - the free space).</p> |DEPENDENT |oracle.tbs_used_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].used_bytes.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace free, bytes |<p>Free bytes of the allocated space.</p> |DEPENDENT |oracle.tbs_free_bytes["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].free_bytes.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace usage, percent |<p>Used bytes/allocated bytes*100.</p> |DEPENDENT |oracle.tbs_used_file_pct["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].used_file_pct.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Tablespace allocated, percent |<p>Allocated bytes/max bytes*100.</p> |DEPENDENT |oracle.tbs_used_pct["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].used_pct_max.first()`</p> |
|Oracle |Oracle TBS '{#TABLESPACE}': Open status |<p>The tablespace status where:</p><p>1 - 'ONLINE';</p><p>2 - 'OFFLINE';</p><p>3 - 'READ ONLY'.</p> |DEPENDENT |oracle.tbs_status["{#TABLESPACE}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#TABLESPACE}'].status.first()`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Error |<p>It displays the error message.</p> |DEPENDENT |oracle.archivelog_error["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DEST_NAME}'].error.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Last sequence |<p>It identifies the sequence number of the last archived redo log to be archived.</p> |DEPENDENT |oracle.archivelog_log_sequence["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DEST_NAME}'].log_sequence.first()`</p> |
|Oracle |Archivelog '{#DEST_NAME}': Status |<p>It identifies the current status of the destination where:</p><p>1 - 'VALID';</p><p>2 - 'DEFERRED';</p><p>3 - 'ERROR';</p><p>0 - 'UNKNOWN'.</p> |DEPENDENT |oracle.archivelog_log_status["{#DEST_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DEST_NAME}'].status.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Oracle |ASM '{#DG_NAME}': Total size |<p>The total size of the ASM disk group.</p> |DEPENDENT |oracle.asm_total_size["{#DG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DG_NAME}'].size_byte.first()`</p> |
|Oracle |ASM '{#DG_NAME}': Free size |<p>The free size of the ASM disk group.</p> |DEPENDENT |oracle.asm_free_size["{#DG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DG_NAME}'].free_size_byte.first()`</p> |
|Oracle |ASM '{#DG_NAME}': Free size |<p>Usage of the ASM disk group expressed in %.</p> |DEPENDENT |oracle.asm_used_pct["{#DG_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#DG_NAME}'].used_percent.first()`</p> |
|Zabbix raw items |Oracle: Get instance state |<p>The item gets its state of the current instance.</p> |ZABBIX_PASSIVE |oracle.instance.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get system metrics |<p>The item gets the values of the system metrics.</p> |ZABBIX_PASSIVE |oracle.sys.metrics["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get system parameters |<p>Get a set of system parameter values.</p> |ZABBIX_PASSIVE |oracle.sys.params["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get sessions stats |<p>Get sessions statistics. {$ORACLE.SESSION.LOCK.MAX.TIME} -- maximum seconds in the current wait condition for counting long time locked sessions. Default: 600 seconds.</p> |ZABBIX_PASSIVE |oracle.sessions.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}","{$ORACLE.SESSION.LOCK.MAX.TIME}"] |
|Zabbix raw items |Oracle: Get PGA stats |<p>Get PGA statistics.</p> |ZABBIX_PASSIVE |oracle.pga.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get FRA stats |<p>Get FRA statistics.</p> |ZABBIX_PASSIVE |oracle.fra.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get SGA stats |<p>Get SGA statistics.</p> |ZABBIX_PASSIVE |oracle.sga.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get tablespaces stats |<p>It gets the statistics of the tablespaces.</p> |ZABBIX_PASSIVE |oracle.ts.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get CDB and No-CDB info |<p>It gets the information about the container database (CDB) and non-CDB databases on an instance.</p> |ZABBIX_PASSIVE |oracle.cdb.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get PDB info |<p>It gets the information about the PDB databases on an instance.</p> |ZABBIX_PASSIVE |oracle.pdb.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get archive log info | |ZABBIX_PASSIVE |oracle.archive.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |
|Zabbix raw items |Oracle: Get ASM stats |<p>It gets the ASM disk groups statistics.</p> |ZABBIX_PASSIVE |oracle.diskgroups.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Oracle: Connection to database is unavailable |<p>Connection to Oracle Database is currently unavailable.</p> |`last(/Oracle by Zabbix agent 2/oracle.ping["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"])=0` |DISASTER | |
|Oracle: Version has changed |<p>The Oracle DB version has changed. Acknowledge (Ack) to close manually.</p> |`last(/Oracle by Zabbix agent 2/oracle.version,#1)<>last(/Oracle by Zabbix agent 2/oracle.version,#2) and length(last(/Oracle by Zabbix agent 2/oracle.version))>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Failed to fetch info data |<p>Zabbix has not received any data for the items for the last 5 minutes. The database might be unavailable for connecting.</p> |`nodata(/Oracle by Zabbix agent 2/oracle.uptime,30m)=1` |INFO | |
|Oracle: Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`last(/Oracle by Zabbix agent 2/oracle.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Oracle: Instance name has changed |<p>Oracle DB Instance name has changed. Ack to close manually.</p> |`last(/Oracle by Zabbix agent 2/oracle.instance_name,#1)<>last(/Oracle by Zabbix agent 2/oracle.instance_name,#2) and length(last(/Oracle by Zabbix agent 2/oracle.instance_name))>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Instance hostname has changed |<p>Oracle DB Instance hostname has changed. Ack to close.</p> |`last(/Oracle by Zabbix agent 2/oracle.instance_hostname,#1)<>last(/Oracle by Zabbix agent 2/oracle.instance_hostname,#2) and length(last(/Oracle by Zabbix agent 2/oracle.instance_hostname))>0` |INFO |<p>Manual close: YES</p> |
|Oracle: Shared pool free is too low |<p>The free memory percent of the shared pool has been less than {$ORACLE.SHARED.FREE.MIN.WARN}% for the last 5 minutes.</p> |`max(/Oracle by Zabbix agent 2/oracle.shared_pool_free,5m)<{$ORACLE.SHARED.FREE.MIN.WARN}` |WARNING | |
|Oracle: Too many active sessions |<p>Active sessions are using more than {$ORACLE.SESSIONS.MAX.WARN}% of the available sessions.</p> |`min(/Oracle by Zabbix agent 2/oracle.session_count,5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.session_limit) > {$ORACLE.SESSIONS.MAX.WARN}` |WARNING | |
|Oracle: Too many locked sessions |<p>The number of locked sessions exceeds {$ORACLE.SESSIONS.LOCK.MAX.WARN}% of the running sessions.</p> |`min(/Oracle by Zabbix agent 2/oracle.session_lock_rate,5m) > {$ORACLE.SESSIONS.LOCK.MAX.WARN}` |WARNING | |
|Oracle: Too many sessions locked |<p>The number of locked sessions exceeding {$ORACLE.SESSION.LOCK.MAX.TIME} seconds is too high. Long-term locks can negatively affect the database performance. Therefore, if they are detected, you should first find the most difficult queries from the database point of view and then analyze possible resource leaks.</p> |`min(/Oracle by Zabbix agent 2/oracle.session_long_time_locked,5m) > {$ORACLE.SESSION.LONG.LOCK.MAX.WARN}` |WARNING | |
|Oracle: Too high database concurrency |<p>The concurrency rate exceeds {$ORACLE.CONCURRENCY.MAX.WARN}%. A high contention value does not indicate the root cause of the problem, but it is a signal to search for it. In the case of high competition, the analysis of resource consumption should be carried out. Which are the most "heavy" queries made in the database? Possibly, also session tracing. All this will help to determine the root cause and possible optimization points both in the database configuration and in the logic of building queries of the application itself.</p> |`min(/Oracle by Zabbix agent 2/oracle.session_concurrency_rate,5m) > {$ORACLE.CONCURRENCY.MAX.WARN}` |WARNING | |
|Oracle: Total PGA inuse is too high |<p>The total PGA in use is more than {$ORACLE.PGA.USE.MAX.WARN}% of PGA_AGGREGATE_TARGET.</p> |`min(/Oracle by Zabbix agent 2/oracle.total_pga_used,5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.pga_target) > {$ORACLE.PGA.USE.MAX.WARN}` |WARNING | |
|Oracle: Zabbix account will expire soon |<p>The password for Zabbix user in the database expires soon.</p> |`last(/Oracle by Zabbix agent 2/oracle.user.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"])  < {$ORACLE.EXPIRE.PASSWORD.MIN.WARN}` |WARNING | |
|Oracle: Number of REDO logs available for switching is too low |<p>The number of inactive/unused REDOs available for log switching is low (database down risk).</p> |`max(/Oracle by Zabbix agent 2/oracle.redolog.info["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],5m) < {$ORACLE.REDO.MIN.WARN}` |WARNING | |
|Oracle: Too many active processes |<p>Active processes are using more than {$ORACLE.PROCESSES.MAX.WARN}% of the available number of processes.</p> |`min(/Oracle by Zabbix agent 2/oracle.proc.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.processes_limit) > {$ORACLE.PROCESSES.MAX.WARN}` |WARNING | |
|Oracle: Too many database files |<p>The number of datafiles is higher than {$ORACLE.DB.FILE.MAX.WARN}% of the available datafiles limit.</p> |`min(/Oracle by Zabbix agent 2/oracle.datafiles.stats["{$ORACLE.CONNSTRING}","{$ORACLE.USER}","{$ORACLE.PASSWORD}","{$ORACLE.SERVICE}"],5m) * 100 / last(/Oracle by Zabbix agent 2/oracle.db_files_limit) > {$ORACLE.DB.FILE.MAX.WARN}` |WARNING | |
|Oracle Database '{#DBNAME}': Open status in mount mode |<p>The Oracle DB is in a mounted state.</p> |`last(/Oracle by Zabbix agent 2/oracle.db_open_mode["{#DBNAME}"])=1` |WARNING | |
|Oracle Database '{#DBNAME}': Open status has changed |<p>The Oracle DB open status has changed. Ack to close manually.</p> |`last(/Oracle by Zabbix agent 2/oracle.db_open_mode["{#DBNAME}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.db_open_mode["{#DBNAME}"],#2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Oracle Database '{#DBNAME}': Open status in mount mode</p> |
|Oracle Database '{#DBNAME}': Role has changed |<p>The Oracle DB role has changed. Ack to close manually.</p> |`last(/Oracle by Zabbix agent 2/oracle.db_role["{#DBNAME}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.db_role["{#DBNAME}"],#2)` |INFO |<p>Manual close: YES</p> |
|Oracle Database '{#DBNAME}': Force logging is deactivated for DB with active Archivelog |<p>Force Logging mode - it is very important metric for Databases in 'ARCHIVELOG'. This feature allows to forcibly write all the transactions to the REDO.</p> |`last(/Oracle by Zabbix agent 2/oracle.db_force_logging["{#DBNAME}"]) = 0 and last(/Oracle by Zabbix agent 2/oracle.db_log_mode["{#DBNAME}"]) = 1` |WARNING | |
|Oracle Database '{#DBNAME}': Open status in mount mode |<p>The Oracle DB is in a mounted state.</p> |`last(/Oracle by Zabbix agent 2/oracle.pdb_open_mode["{#DBNAME}"])=1` |WARNING | |
|Oracle Database '{#DBNAME}': Open status has changed |<p>The Oracle DB open status has changed. Ack to close manually.</p> |`last(/Oracle by Zabbix agent 2/oracle.pdb_open_mode["{#DBNAME}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.pdb_open_mode["{#DBNAME}"],#2)` |INFO |<p>Manual close: YES</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace usage is too high |<p>-</p> |`min(/Oracle by Zabbix agent 2/oracle.tbs_used_file_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespace usage is too high</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace usage is too high |<p>-</p> |`min(/Oracle by Zabbix agent 2/oracle.tbs_used_file_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.HIGH}` |HIGH | |
|Oracle TBS '{#TABLESPACE}': Tablespace utilization is too high |<p>-</p> |`min(/Oracle by Zabbix agent 2/oracle.tbs_used_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.USED.PCT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespace utilization is too high</p> |
|Oracle TBS '{#TABLESPACE}': Tablespace utilization is too high |<p>-</p> |`min(/Oracle by Zabbix agent 2/oracle.tbs_used_pct["{#TABLESPACE}"],5m)>{$ORACLE.TBS.UTIL.PCT.MAX.HIGH}` |HIGH | |
|Oracle TBS '{#TABLESPACE}': Tablespace is OFFLINE |<p>The tablespace is in the offline state.</p> |`last(/Oracle by Zabbix agent 2/oracle.tbs_status["{#TABLESPACE}"])=2` |WARNING | |
|Oracle TBS '{#TABLESPACE}': Tablespace status has changed |<p>Oracle tablespace status has changed. Ack to close.</p> |`last(/Oracle by Zabbix agent 2/oracle.tbs_status["{#TABLESPACE}"],#1)<>last(/Oracle by Zabbix agent 2/oracle.tbs_status["{#TABLESPACE}"],#2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Oracle TBS '{#TABLESPACE}': Tablespace is OFFLINE</p> |
|Archivelog '{#DEST_NAME}': Log Archive is not valid |<p>The trigger will launch if the archive log destination is not in one of these states:</p><p>2 - 'DEFERRED';</p><p>3 - 'VALID'.</p> |`last(/Oracle by Zabbix agent 2/oracle.archivelog_log_status["{#DEST_NAME}"])<2` |HIGH | |
|ASM '{#DG_NAME}': Disk group usage is too high |<p>The usage of the ASM disk group expressed in % exceeds {$ORACLE.ASM.USED.PCT.MAX.WARN}</p> |`min(/Oracle by Zabbix agent 2/oracle.asm_used_pct["{#DG_NAME}"],5m)>{$ORACLE.ASM.USED.PCT.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- ASM '{#DG_NAME}': Disk group usage is too high</p> |
|ASM '{#DG_NAME}': Disk group usage is too high |<p>The usage of the ASM disk group expressed in % exceeds {$ORACLE.ASM.USED.PCT.MAX.HIGH}</p> |`min(/Oracle by Zabbix agent 2/oracle.asm_used_pct["{#DG_NAME}"],5m)>{$ORACLE.ASM.USED.PCT.MAX.HIGH}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

