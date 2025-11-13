
# MSSQL by Zabbix agent 2

## Overview

This template is designed for the effortless deployment of MSSQL monitoring by Zabbix via Zabbix agent 2 and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Microsoft SQL, version 2017, 2019, 2022

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Deploy Zabbix agent 2 with the MSSQL plugin. You can use this template starting with version 8.0.0 of both Zabbix and the MSSQL plugin. For more information, see [MSSQL plugin documentation](https://git.zabbix.com/projects/AP/repos/mssql/browse).


Loadable plugin requires installation of a separate package or binary file or [compilation from sources](https://www.zabbix.com/documentation/8.0/manual/extensions/plugins/build).

2. Create a monitoring user on MSSQL for Zabbix to connect to:
- for MSSQL version 2022
  ```sql
  CREATE LOGIN zabbix WITH PASSWORD = 'password'
  GRANT VIEW SERVER PERFORMANCE STATE TO zabbix
  GRANT VIEW ANY DEFINITION TO zabbix
  USE msdb
  CREATE USER zabbix FOR LOGIN zabbix
  GRANT EXECUTE ON msdb.dbo.agent_datetime TO zabbix
  GRANT SELECT ON msdb.dbo.sysjobactivity TO zabbix
  GRANT SELECT ON msdb.dbo.sysjobservers TO zabbix
  GRANT SELECT ON msdb.dbo.sysjobs TO zabbix
  GO
  ```
- for MSSQL versions 2017 and 2019
  ```sql
  CREATE LOGIN zabbix WITH PASSWORD = 'password'
  GRANT VIEW SERVER STATE TO zabbix
  GRANT VIEW ANY DEFINITION TO zabbix
  USE msdb
  CREATE USER zabbix FOR LOGIN zabbix
  GRANT EXECUTE ON msdb.dbo.agent_datetime TO zabbix
  GRANT SELECT ON msdb.dbo.sysjobactivity TO zabbix
  GRANT SELECT ON msdb.dbo.sysjobservers TO zabbix
  GRANT SELECT ON msdb.dbo.sysjobs TO zabbix
  GO
  ```

For more information, see MSSQL documentation:

[Create a database user](https://docs.microsoft.com/en-us/sql/relational-databases/security/authentication-access/create-a-database-user?view=sql-server-ver16)

[GRANT Server Permissions](https://docs.microsoft.com/en-us/sql/t-sql/statements/grant-server-permissions-transact-sql?view=sql-server-ver16)

[Configure a User to Create and Manage SQL Server Agent Jobs](https://docs.microsoft.com/en-us/sql/ssms/agent/configure-a-user-to-create-and-manage-sql-server-agent-jobs?view=sql-server-ver16)

3. Set the username and password in the host macros `{$MSSQL.USER}` and `{$MSSQL.PASSWORD}`.

4. Set the connection string for the MSSQL instance in the `{$MSSQL.URI}` macro as a URI, such as `<protocol://host:port>`, or specify the named session - `<sessionname>`.

The `Service's TCP port state` item uses the `{$MSSQL.HOST}` and `{$MSSQL.PORT}` macros to check the availability of the MSSQL instance, change these if necessary. Keep in mind that if dynamic ports are used on the MSSQL server side, this check will not work correctly.

Note: You can use the context macros `{$MSSQL.BACKUP_FULL.USED}`, `{$MSSQL.BACKUP_LOG.USED}`, and `{$MSSQL.BACKUP_DIFF.USED}` to disable backup age triggers for a certain database. If set to a value other than "1", the trigger expression for the backup age will not fire.

Note: Since version 7.2.0, you can also connect to the MSSQL instance using its name. To do this, set the connection string in the `{$MSSQL.URI}` macro as `<protocol://host/instance_name>`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MSSQL.URI}|<p>Connection string.</p>||
|{$MSSQL.USER}|<p>MSSQL database username.</p>||
|{$MSSQL.PASSWORD}|<p>MSSQL database password.</p>||
|{$MSSQL.HOST}|<p>The hostname or IP address of the MSSQL instance.</p>|`localhost`|
|{$MSSQL.PORT}|<p>MSSQL TCP port.</p>|`1433`|
|{$MSSQL.WORK_FILES.MAX}|<p>The maximum number of work files created per second - for the trigger expression.</p>|`20`|
|{$MSSQL.WORK_TABLES.MAX}|<p>The maximum number of work tables created per second - for the trigger expression.</p>|`20`|
|{$MSSQL.WORKTABLES_FROM_CACHE_RATIO.MIN.CRIT}|<p>The minimum percentage of work tables from the cache ratio - for the High trigger expression.</p>|`90`|
|{$MSSQL.BUFFER_CACHE_RATIO.MIN.WARN}|<p>The minimum buffer cache hit ratio, in percent - for the Warning trigger expression.</p>|`50`|
|{$MSSQL.BUFFER_CACHE_RATIO.MIN.CRIT}|<p>The minimum buffer cache hit ratio, in percent - for the High trigger expression.</p>|`30`|
|{$MSSQL.FREE_LIST_STALLS.MAX}|<p>The maximum free list stalls per second - for the trigger expression.</p>|`2`|
|{$MSSQL.LAZY_WRITES.MAX}|<p>The maximum lazy writes per second - for the trigger expression.</p>|`20`|
|{$MSSQL.PAGE_LIFE_EXPECTANCY.MIN}|<p>The minimum page life expectancy - for the trigger expression.</p>|`300`|
|{$MSSQL.PAGE_READS.MAX}|<p>The maximum page reads per second - for the trigger expression.</p>|`90`|
|{$MSSQL.PAGE_WRITES.MAX}|<p>The maximum page writes per second - for the trigger expression.</p>|`90`|
|{$MSSQL.AVERAGE_WAIT_TIME.MAX}|<p>The maximum average wait time, in milliseconds - for the trigger expression.</p>|`500`|
|{$MSSQL.LOCK_REQUESTS.MAX}|<p>The maximum lock requests per second - for the trigger expression.</p>|`1000`|
|{$MSSQL.LOCK_TIMEOUTS.MAX}|<p>The maximum lock timeouts per second - for the trigger expression.</p>|`1`|
|{$MSSQL.DEADLOCKS.MAX}|<p>The maximum deadlocks per second - for the trigger expression.</p>|`1`|
|{$MSSQL.LOG_FLUSH_WAITS.MAX}|<p>The maximum log flush waits per second - for the trigger expression.</p>|`1`|
|{$MSSQL.LOG_FLUSH_WAIT_TIME.MAX}|<p>The maximum log flush wait time, in milliseconds - for the trigger expression.</p>|`1`|
|{$MSSQL.PERCENT_LOG_USED.MAX}|<p>The maximum percentage of log used - for the trigger expression.</p>|`80`|
|{$MSSQL.PERCENT_COMPILATIONS.MAX}|<p>The maximum percentage of Transact-SQL compilations - for the trigger expression.</p>|`10`|
|{$MSSQL.PERCENT_RECOMPILATIONS.MAX}|<p>The maximum percentage of Transact-SQL recompilations - for the trigger expression.</p>|`10`|
|{$MSSQL.PERCENT_READAHEAD.MAX}|<p>The maximum percentage of pages read per second in anticipation of use - for the trigger expression.</p>|`20`|
|{$MSSQL.BACKUP_DIFF.WARN}|<p>The maximum of days without a differential backup - for the Warning trigger expression.</p>|`3d`|
|{$MSSQL.BACKUP_DIFF.CRIT}|<p>The maximum of days without a differential backup - for the High trigger expression.</p>|`6d`|
|{$MSSQL.BACKUP_FULL.WARN}|<p>The maximum of days without a full backup - for the Warning trigger expression.</p>|`9d`|
|{$MSSQL.BACKUP_FULL.CRIT}|<p>The maximum of days without a full backup - for the High trigger expression.</p>|`10d`|
|{$MSSQL.BACKUP_LOG.WARN}|<p>The maximum of days without a log backup - for the Warning trigger expression.</p>|`4h`|
|{$MSSQL.BACKUP_LOG.CRIT}|<p>The maximum of days without a log backup - for the High trigger expression.</p>|`8h`|
|{$MSSQL.JOB_DURATION.WARN}|<p>The maximum job duration - for the Warning trigger expression.</p>|`1h`|
|{$MSSQL.BACKUP_FULL.USED}|<p>The flag for checking the age of a full backup. If set to a value other than "1", the trigger expression for the full backup age will not fire. Can be used with context for database name.</p>|`1`|
|{$MSSQL.BACKUP_LOG.USED}|<p>The flag for checking the age of a log backup. If set to a value other than "1", the trigger expression for the log backup age will not fire. Can be used with context for database name.</p>|`1`|
|{$MSSQL.BACKUP_DIFF.USED}|<p>The flag for checking the age of a differential backup. If set to a value other than "1", the trigger expression for the differential backup age will not fire. Can be used with context for database name.</p>|`1`|
|{$MSSQL.DBNAME.MATCHES}|<p>This macro is used in database discovery. It can be overridden on the host or linked template level.</p>|`.*`|
|{$MSSQL.DBNAME.NOT_MATCHES}|<p>This macro is used in database discovery. It can be overridden on the host or linked template level.</p>|`master\|tempdb\|model\|msdb`|
|{$MSSQL.JOB.MATCHES}|<p>This macro is used in job discovery. It can be overridden on the host or linked template level.</p>|`.*`|
|{$MSSQL.JOB.NOT_MATCHES}|<p>This macro is used in job discovery. It can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$MSSQL.QUORUM.MEMBER.DISCOVERY.NAME.MATCHES}|<p>Filter to include discovered quorum member by name.</p>|`.*`|
|{$MSSQL.QUORUM.MEMBER.DISCOVERY.NAME.NOT_MATCHES}|<p>Filter to exclude discovered quorum member by name.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service's TCP port state|<p>Test the availability of MSSQL Server on a TCP port.</p>|Simple check|net.tcp.service[tcp,{$MSSQL.HOST},{$MSSQL.PORT}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Get last backup|<p>The item gets information about backup processes.</p>|Zabbix agent|mssql.last.backup.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Get job status|<p>The item gets the SQL agent job status.</p>|Zabbix agent|mssql.job.status.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Get performance counters|<p>The item gets server global status information.</p>|Zabbix agent|mssql.perfcounter.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Get availability groups|<p>The item gets availability group states - name, primary and secondary health, synchronization health.</p>|Zabbix agent|mssql.availability.group.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Get local DB|<p>Getting the states of the local availability database.</p>|Zabbix agent|mssql.local.db.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Get DB mirroring|<p>Getting DB mirroring.</p>|Zabbix agent|mssql.mirroring.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Get non-local DB|<p>Getting the non-local availability database.</p>|Zabbix agent|mssql.nonlocal.db.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Get replica|<p>Getting the database replica.</p>|Zabbix agent|mssql.replica.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Get quorum|<p>Getting quorum - cluster name, type, and state.</p>|Zabbix agent|mssql.quorum.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Get quorum member|<p>Getting quorum members - member name, type, state, and number of quorum votes.</p>|Zabbix agent|mssql.quorum.member.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Get database|<p>Getting databases - database name and recovery model.</p>|Zabbix agent|mssql.db.get["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]|
|Version|<p>MSSQL Server version.</p>|Zabbix agent|mssql.version["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Uptime|<p>MSSQL Server uptime in the format "N days, hh:mm:ss".</p>|Dependent item|mssql.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Uptime')].cntr_value.first()`</p></li></ul>|
|Get Access Methods counters|<p>The item gets server information about access methods.</p>|Dependent item|mssql.access_methods.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.object_name=~'.*Access Methods')]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Forwarded records per second|<p>Number of records per second fetched through forwarded record pointers.</p>|Dependent item|mssql.forwarded_records_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Full scans per second|<p>Number of unrestricted full scans per second. These can be either base-table or full-index scans. Values greater than 1 or 2 indicate that there are table / index page scans. If that is combined with high CPU, this counter requires further investigation, otherwise, if the full scans are on small tables, it can be ignored.</p>|Dependent item|mssql.full_scans_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Full Scans/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Index searches per second|<p>Number of index searches per second. These are used to start a range scan, reposition a range scan, revalidate a scan point, fetch a single index record, and search down the index to locate where to insert a new row.</p>|Dependent item|mssql.index_searches_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Page splits per second|<p>Number of page splits per second that occur as a result of overflowing index pages.</p>|Dependent item|mssql.page_splits_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Page Splits/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Work files created per second|<p>Number of work files created per second. For example, work files can be used to store temporary results for hash joins and hash aggregates.</p>|Dependent item|mssql.workfiles_created_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Work tables created per second|<p>Number of work tables created per second. For example, work tables can be used to store temporary results for query spool, LOB variables, XML variables, and cursors.</p>|Dependent item|mssql.worktables_created_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Table lock escalations per second|<p>Number of times locks on a table were escalated to the TABLE or HoBT granularity.</p>|Dependent item|mssql.table_lock_escalations.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Worktables from cache ratio|<p>Percentage of work tables created where the initial two pages of the work table were not allocated but were immediately available from the work table cache.</p>|Dependent item|mssql.worktables_from_cache_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Get Buffer Manager counters|<p>The item gets server information about the buffer pool.</p>|Dependent item|mssql.buffer_manager.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.object_name=~'.*Buffer Manager')]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Buffer cache hit ratio|<p>Indicates the percentage of pages found in the buffer cache without having to read from the disk. The ratio is the total number of cache hits divided by the total number of cache lookups over the last few thousand page accesses. After a long period of time, the ratio changes very little. Since reading from the cache is much less expensive than reading from the disk, a higher value is preferred for this item. To increase the buffer cache hit ratio, consider increasing the amount of memory available to MSSQL Server or using the buffer pool extension feature.</p>|Dependent item|mssql.buffer_cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Checkpoint pages per second|<p>Indicates the number of pages flushed to the disk per second by a checkpoint or other operation which required all dirty pages to be flushed.</p>|Dependent item|mssql.checkpoint_pages_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Database pages|<p>Indicates the number of pages in the buffer pool with database content.</p>|Dependent item|mssql.database_pages<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Database pages')].cntr_value.first()`</p></li></ul>|
|Free list stalls per second|<p>Indicates the number of requests per second that had to wait for a free page.</p>|Dependent item|mssql.free_list_stalls_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Lazy writes per second|<p>Indicates the number of buffers written per second by the buffer manager's lazy writer. The lazy writer is a system process that flushes out batches of dirty, aged buffers (buffers that contain changes that must be written back to the disk before the buffer can be reused for a different page) and makes them available to user processes. The lazy writer eliminates the need to perform frequent checkpoints in order to create available buffers.</p>|Dependent item|mssql.lazy_writes_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Lazy writes/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Page life expectancy|<p>Indicates the number of seconds a page will stay in the buffer pool without references.</p>|Dependent item|mssql.page_life_expectancy<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Page lookups per second|<p>Indicates the number of requests per second to find a page in the buffer pool.</p>|Dependent item|mssql.page_lookups_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Page lookups/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Page reads per second|<p>Indicates the number of physical database page reads that are issued per second. This statistic displays the total number of physical page reads across all databases. As physical I/O is expensive, you may be able to minimize the cost either by using a larger data cache, intelligent indexes, and more efficient queries, or by changing the database design.</p>|Dependent item|mssql.page_reads_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Page reads/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Page writes per second|<p>Indicates the number of physical database page writes that are issued per second.</p>|Dependent item|mssql.page_writes_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Page writes/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Read-ahead pages per second|<p>Indicates the number of pages read per second in anticipation of use.</p>|Dependent item|mssql.readahead_pages_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Target pages|<p>The optimal number of pages in the buffer pool.</p>|Dependent item|mssql.target_pages<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Target pages')].cntr_value.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get DB counters|<p>The item gets summary information about databases.</p>|Dependent item|mssql.db_info.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Total data file size|<p>Total size of all data files.</p>|Dependent item|mssql.data_files_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Total log file size|<p>Total size of all the transaction log files.</p>|Dependent item|mssql.log_files_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Total log file used size|<p>The cumulative size of all the log files in the database.</p>|Dependent item|mssql.log_files_used_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Total transactions per second|<p>Total number of transactions started for all databases per second.</p>|Dependent item|mssql.transactions_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Transactions/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Get General Statistics counters|<p>The item gets general statistics information.</p>|Dependent item|mssql.general_statistics.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.object_name=~'.*General Statistics')]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Logins per second|<p>Total number of logins started per second. This does not include pooled connections. Any value over 2 may indicate insufficient connection pooling.</p>|Dependent item|mssql.logins_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Logins/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Logouts per second|<p>Total number of logout operations started per second. Any value over 2 may indicate insufficient connection pooling.</p>|Dependent item|mssql.logouts_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Logouts/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Number of blocked processes|<p>Number of currently blocked processes.</p>|Dependent item|mssql.processes_blocked<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Processes blocked')].cntr_value.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Number of users connected|<p>Number of users connected to MSSQL Server.</p>|Dependent item|mssql.user_connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='User Connections')].cntr_value.first()`</p></li></ul>|
|Average latch wait time|<p>Average latch wait time (in milliseconds) for latch requests that had to wait.</p>|Calculated|mssql.average_latch_wait_time|
|Get Latches counters|<p>The item gets server information about latches.</p>|Dependent item|mssql.latches_info.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.object_name=~'.*Latches')]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Average latch wait time raw|<p>Average latch wait time (in milliseconds) for latch requests that had to wait.</p>|Dependent item|mssql.average_latch_wait_time_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Average latch wait time base|<p>For internal use only.</p>|Dependent item|mssql.average_latch_wait_time_base<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Latch waits per second|<p>The number of latch requests that could not be granted immediately. Latches are lightweight means of holding a very transient server resource, such as an address in memory.</p>|Dependent item|mssql.latch_waits_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Latch Waits/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Total latch wait time|<p>Total latch wait time (in milliseconds) for latch requests in the last second. This value should stay stable compared to the number of latch waits per second.</p>|Dependent item|mssql.total_latch_wait_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Total average wait time|<p>The average wait time, in milliseconds, for each lock request that had to wait.</p>|Calculated|mssql.average_wait_time|
|Get Locks counters|<p>The item gets server information about locks.</p>|Dependent item|mssql.locks_info.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.object_name=~'.*Locks' && @.instance_name=='_Total')]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Total average wait time raw|<p>Average amount of wait time (in milliseconds) for each lock request that resulted in a wait. Information for all locks.</p>|Dependent item|mssql.average_wait_time_raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Total average wait time base|<p>For internal use only.</p>|Dependent item|mssql.average_wait_time_base<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Total lock requests per second|<p>Number of new locks and lock conversions per second requested from the lock manager.</p>|Dependent item|mssql.lock_requests_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Lock Requests/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Total lock requests per second that timed out|<p>Number of timed out lock requests per second, including requests for NOWAIT locks.</p>|Dependent item|mssql.lock_timeouts_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Lock Timeouts/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Total lock requests per second that required waiting|<p>Number of lock requests per second that required the caller to wait.</p>|Dependent item|mssql.lock_waits_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Lock Waits/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|Lock wait time|<p>Average of total wait time (in milliseconds) for locks in the last second.</p>|Dependent item|mssql.lock_wait_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Total lock requests per second that have deadlocks|<p>Number of lock requests per second that resulted in a deadlock.</p>|Dependent item|mssql.number_deadlocks_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Get Memory counters|<p>The item gets memory information.</p>|Dependent item|mssql.mem_manager.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.object_name=~'.*Memory Manager')]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Granted Workspace Memory|<p>Specifies the total amount of memory currently granted to executing processes, such as hash, sort, bulk copy, and index creation operations.</p>|Dependent item|mssql.granted_workspace_memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Maximum workspace memory|<p>Indicates the maximum amount of memory available for executing processes, such as hash, sort, bulk copy, and index creation operations.</p>|Dependent item|mssql.maximum_workspace_memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Memory grants outstanding|<p>Specifies the total number of processes that have successfully acquired a workspace memory grant.</p>|Dependent item|mssql.memory_grants_outstanding<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Memory grants pending|<p>Specifies the total number of processes waiting for a workspace memory grant.</p>|Dependent item|mssql.memory_grants_pending<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Target server memory|<p>Indicates the ideal amount of memory the server can consume.</p>|Dependent item|mssql.target_server_memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Total server memory|<p>Specifies the amount of memory the server has committed using the memory manager.</p>|Dependent item|mssql.total_server_memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Get Cache counters|<p>The item gets server information about cache.</p>|Dependent item|mssql.cache_info.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Cache hit ratio|<p>Ratio between cache hits and lookups.</p>|Dependent item|mssql.cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='CacheHitRatio')].cntr_value.first()`</p></li></ul>|
|Cache object counts|<p>Number of cache objects in the cache.</p>|Dependent item|mssql.cache_object_counts<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Cache objects in use|<p>Number of cache objects in use.</p>|Dependent item|mssql.cache_objects_in_use<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Cache pages|<p>Number of 8-kilobyte (KB) pages used by cache objects.</p>|Dependent item|mssql.cache_pages<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Cache Pages')].cntr_value.first()`</p></li></ul>|
|Get SQL Errors counters|<p>The item gets SQL error information.</p>|Dependent item|mssql.sql_errors.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.object_name=~'.*SQL Errors')]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Errors per second (DB offline errors)|<p>Number of errors per second.</p>|Dependent item|mssql.offline_errors_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Errors per second (Info errors)|<p>Number of errors per second.</p>|Dependent item|mssql.info_errors_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Errors per second (Kill connection errors)|<p>Number of errors per second.</p>|Dependent item|mssql.kill_connection_errors_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Errors per second (User errors)|<p>Number of errors per second.</p>|Dependent item|mssql.user_errors_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Total errors per second|<p>Number of errors per second.</p>|Dependent item|mssql.errors_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Get SQL Statistics counters|<p>The item gets SQL statistics information.</p>|Dependent item|mssql.sql_statistics.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.object_name=~'.*SQL Statistics')]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Auto-param attempts per second|<p>Number of auto-parameterization attempts per second. The total should be the sum of the failed, safe, and unsafe auto-parameterizations. Auto-parameterization occurs when an instance of SQL Server tries to parameterize a Transact-SQL request by replacing some literals with parameters so that reuse of the resulting cached execution plan across multiple similar-looking requests is possible. Note that auto-parameterizations are also known as simple parameterizations in the newer versions of SQL Server. This counter does not include forced parameterizations.</p>|Dependent item|mssql.autoparam_attempts_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Batch requests per second|<p>Number of Transact-SQL command batches received per second. This statistic is affected by all constraints (such as I/O, number of users, cache size, complexity of requests, and so on). High batch requests mean good throughput.</p>|Dependent item|mssql.batch_requests_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Percent of ad hoc queries running|<p>The ratio of SQL compilations per second to batch requests per second, in percent.</p>|Calculated|mssql.percent_of_adhoc_queries|
|Percent of Recompiled Transact-SQL Objects|<p>The ratio of SQL re-compilations per second to SQL compilations per second, in percent.</p>|Calculated|mssql.percent_recompilations_to_compilations|
|Full scans to Index searches ratio|<p>The ratio of full scans per second to index searches per second. The threshold recommendation is strictly for OLTP workloads.</p>|Calculated|mssql.scan_to_search|
|Failed auto-params per second|<p>Number of failed auto-parameterization attempts per second. This number should be small. Note that auto-parameterizations are also known as simple parameterizations in the newer versions of SQL Server.</p>|Dependent item|mssql.failed_autoparams_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Safe auto-params per second|<p>Number of safe auto-parameterization attempts per second. Safe refers to a determination that a cached execution plan can be shared between different similar-looking Transact-SQL statements. SQL Server makes many auto-parameterization attempts, some of which turn out to be safe and others fail. Note that auto-parameterizations are also known as simple parameterizations in the newer versions of SQL Server. This does not include forced parameterizations.</p>|Dependent item|mssql.safe_autoparams_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|SQL compilations per second|<p>Number of SQL compilations per second. Indicates the number of times the compile code path is entered. Includes runs caused by statement-level recompilations in SQL Server. After SQL Server user activity is stable, this value reaches a steady state.</p>|Dependent item|mssql.sql_compilations_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|SQL re-compilations per second|<p>Number of statement recompiles per second. Counts the number of times statement recompiles are triggered. Generally, you want the recompiles to be low.</p>|Dependent item|mssql.sql_recompilations_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Unsafe auto-params per second|<p>Number of unsafe auto-parameterization attempts per second. For example, the query has some characteristics that prevent the cached plan from being shared. These are designated as unsafe. This does not count the number of forced parameterizations.</p>|Dependent item|mssql.unsafe_autoparams_sec.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Total transactions number|<p>The number of currently active transactions of all types.</p>|Dependent item|mssql.transactions<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MSSQL: Service is unavailable|<p>The TCP port of the MSSQL Server service is currently unavailable.</p>|`last(/MSSQL by Zabbix agent 2/net.tcp.service[tcp,{$MSSQL.HOST},{$MSSQL.PORT}])=0`|Disaster||
|MSSQL: Version has changed|<p>MSSQL version has changed. Acknowledge to close the problem manually.</p>|`last(/MSSQL by Zabbix agent 2/mssql.version["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"],#1)<>last(/MSSQL by Zabbix agent 2/mssql.version["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"],#2) and length(last(/MSSQL by Zabbix agent 2/mssql.version["{$MSSQL.URI}","{$MSSQL.USER}","{$MSSQL.PASSWORD}"]))>0`|Info|**Manual close**: Yes|
|MSSQL: Service has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/MSSQL by Zabbix agent 2/mssql.uptime)<10m`|Info|**Manual close**: Yes|
|MSSQL: Failed to fetch info data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/MSSQL by Zabbix agent 2/mssql.uptime,30m)=1`|Info|**Depends on**:<br><ul><li>MSSQL: Service is unavailable</li></ul>|
|MSSQL: Too frequently using pointers|<p>Rows with VARCHAR columns can experience expansion when VARCHAR values are updated with a longer string. In the case where the row cannot fit in the existing page, the row migrates, and access to the row will traverse a pointer. This only happens on heaps (tables without clustered indexes). In cases where clustered indexes cannot be used, drop non-clustered indexes, build a clustered index to reorg pages and rows, drop the clustered index, then recreate non-clustered indexes.</p>|`last(/MSSQL by Zabbix agent 2/mssql.forwarded_records_sec.rate) * 100 > 10 * last(/MSSQL by Zabbix agent 2/mssql.batch_requests_sec.rate)`|Warning||
|MSSQL: Number of work files created per second is high|<p>Too many work files created per second to store temporary results for hash joins and hash aggregates.</p>|`min(/MSSQL by Zabbix agent 2/mssql.workfiles_created_sec.rate,5m)>{$MSSQL.WORK_FILES.MAX}`|Average||
|MSSQL: Number of work tables created per second is high|<p>Too many work tables created per second to store temporary results for query spool, LOB variables, XML variables, and cursors.</p>|`min(/MSSQL by Zabbix agent 2/mssql.worktables_created_sec.rate,5m)>{$MSSQL.WORK_TABLES.MAX}`|Average||
|MSSQL: Percentage of work tables available from the work table cache is low|<p>A value less than 90% may indicate insufficient memory, since execution plans are being dropped, or, on 32-bit systems, may indicate the need for an upgrade to a 64-bit system.</p>|`max(/MSSQL by Zabbix agent 2/mssql.worktables_from_cache_ratio,5m)<{$MSSQL.WORKTABLES_FROM_CACHE_RATIO.MIN.CRIT}`|High||
|MSSQL: Percentage of the buffer cache efficiency is low|<p>Too low buffer cache hit ratio.</p>|`max(/MSSQL by Zabbix agent 2/mssql.buffer_cache_hit_ratio,5m)<{$MSSQL.BUFFER_CACHE_RATIO.MIN.CRIT}`|High||
|MSSQL: Percentage of the buffer cache efficiency is low|<p>Low buffer cache hit ratio.</p>|`max(/MSSQL by Zabbix agent 2/mssql.buffer_cache_hit_ratio,5m)<{$MSSQL.BUFFER_CACHE_RATIO.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>MSSQL: Percentage of the buffer cache efficiency is low</li></ul>|
|MSSQL: Number of rps waiting for a free page is high|<p>Some requests have to wait for a free page.</p>|`min(/MSSQL by Zabbix agent 2/mssql.free_list_stalls_sec.rate,5m)>{$MSSQL.FREE_LIST_STALLS.MAX}`|Warning||
|MSSQL: Number of buffers written per second by the lazy writer is high|<p>The number of buffers written per second by the buffer manager's lazy writer exceeds the threshold.</p>|`min(/MSSQL by Zabbix agent 2/mssql.lazy_writes_sec.rate,5m)>{$MSSQL.LAZY_WRITES.MAX}`|Warning||
|MSSQL: Page life expectancy is low|<p>The page stays in the buffer pool without references for less time than the threshold value.</p>|`max(/MSSQL by Zabbix agent 2/mssql.page_life_expectancy,15m)<{$MSSQL.PAGE_LIFE_EXPECTANCY.MIN}`|High||
|MSSQL: Number of physical database page reads per second is high|<p>The physical database page reads are issued too frequently.</p>|`min(/MSSQL by Zabbix agent 2/mssql.page_reads_sec.rate,5m)>{$MSSQL.PAGE_READS.MAX}`|Warning||
|MSSQL: Number of physical database page writes per second is high|<p>The physical database page writes are issued too frequently.</p>|`min(/MSSQL by Zabbix agent 2/mssql.page_writes_sec.rate,5m)>{$MSSQL.PAGE_WRITES.MAX}`|Warning||
|MSSQL: Too many physical reads occurring|<p>If this value makes up even a sizeable minority of the total "Page Reads/sec" (say, greater than 20% of the total page reads), you may have too many physical reads occurring.</p>|`last(/MSSQL by Zabbix agent 2/mssql.readahead_pages_sec.rate) > {$MSSQL.PERCENT_READAHEAD.MAX} / 100 * last(/MSSQL by Zabbix agent 2/mssql.page_reads_sec.rate)`|Warning||
|MSSQL: Total average wait time for locks is high|<p>An average wait time longer than 500 ms may indicate excessive blocking. This value should generally correlate to "Lock Waits/sec" and move up or down with it accordingly.</p>|`min(/MSSQL by Zabbix agent 2/mssql.average_wait_time,5m)>{$MSSQL.AVERAGE_WAIT_TIME.MAX}`|Warning||
|MSSQL: Total number of locks per second is high|<p>Number of new locks and lock conversions per second requested from the lock manager is high.</p>|`min(/MSSQL by Zabbix agent 2/mssql.lock_requests_sec.rate,5m)>{$MSSQL.LOCK_REQUESTS.MAX}`|Warning||
|MSSQL: Total lock requests per second that timed out is high|<p>The total number of timed out lock requests per second, including requests for NOWAIT locks, is high.</p>|`min(/MSSQL by Zabbix agent 2/mssql.lock_timeouts_sec.rate,5m)>{$MSSQL.LOCK_TIMEOUTS.MAX}`|Warning||
|MSSQL: Some blocking is occurring for 5m|<p>Values greater than zero indicate at least some blocking is occurring, while a value of zero can quickly eliminate blocking as a potential root-cause problem.</p>|`min(/MSSQL by Zabbix agent 2/mssql.lock_waits_sec.rate,5m)>0`|Average||
|MSSQL: Number of deadlocks is high|<p>Too many deadlocks are occurring currently.</p>|`min(/MSSQL by Zabbix agent 2/mssql.number_deadlocks_sec.rate,5m)>{$MSSQL.DEADLOCKS.MAX}`|Average||
|MSSQL: Percent of ad hoc queries running is high|<p>The lower this value is, the better. High values often indicate excessive ad hoc querying and should be as low as possible. If excessive ad hoc querying is happening, try rewriting the queries as procedures or invoke the queries using `sp_executeSQL`. When rewriting isn't possible, consider using a plan guide or setting the database to parameterization forced mode.</p>|`min(/MSSQL by Zabbix agent 2/mssql.percent_of_adhoc_queries,15m) > {$MSSQL.PERCENT_COMPILATIONS.MAX}`|Warning||
|MSSQL: Percent of times statement recompiles is high|<p>This number should be at or near zero, since recompiles can cause deadlocks and exclusive compile locks. This counter's value should follow in proportion to "Batch Requests/sec" and "SQL Compilations/sec".</p>|`min(/MSSQL by Zabbix agent 2/mssql.percent_recompilations_to_compilations,15m) > {$MSSQL.PERCENT_RECOMPILATIONS.MAX}`|Warning||
|MSSQL: Number of index and table scans exceeds index searches in the last 15m|<p>Index searches are preferable to index and table scans. For OLTP applications, optimize for more index searches and less scans (preferably, 1 full scan for every 1000 index searches). Index and table scans are expensive I/O operations.</p>|`min(/MSSQL by Zabbix agent 2/mssql.scan_to_search,15m) > 0.001`|Warning||

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Scanning databases in DBMS.</p>|Dependent item|mssql.database.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MSSQL DB '{#DBNAME}': Get performance counters|<p>The item gets server status information for {#DBNAME}.</p>|Dependent item|mssql.db.perf_raw["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MSSQL DB '{#DBNAME}': Get last backup|<p>The item gets information about backup processes for {#DBNAME}.</p>|Dependent item|mssql.backup.raw["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.dbname=='{#DBNAME}')]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MSSQL DB '{#DBNAME}': State|<p>0 = Online</p><p>1 = Restoring</p><p>2 = Recovering \| SQL Server 2008 and later</p><p>3 = Recovery_pending \| SQL Server 2008 and later</p><p>4 = Suspect</p><p>5 = Emergency \| SQL Server 2008 and later</p><p>6 = Offline \| SQL Server 2008 and later</p><p>7 = Copying \| Azure SQL Database Active Geo-Replication</p><p>10 = Offline_secondary \| Azure SQL Database Active Geo-Replication</p>|Dependent item|mssql.db.state["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='State')].cntr_value.first()`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Active transactions|<p>Number of active transactions for the database.</p>|Dependent item|mssql.db.active_transactions["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Data file size|<p>Cumulative size of all the data files in the database including any automatic growth. Monitoring this counter is useful, for example, for determining the correct size of `tempdb`.</p>|Dependent item|mssql.db.data_files_size["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Log bytes flushed per second|<p>Total number of log bytes flushed per second. Useful for determining trends and utilization of the transaction log.</p>|Dependent item|mssql.db.log_bytes_flushed_sec.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|MSSQL DB '{#DBNAME}': Log file size|<p>Cumulative size of all the transaction log files in the database.</p>|Dependent item|mssql.db.log_files_size["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Log file used size|<p>Cumulative size of all the log files in the database.</p>|Dependent item|mssql.db.log_files_used_size["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Log flushes per second|<p>Number of log flushes per second.</p>|Dependent item|mssql.db.log_flushes_sec.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Log Flushes/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|MSSQL DB '{#DBNAME}': Log flush waits per second|<p>Number of commits per second waiting for the log flush.</p>|Dependent item|mssql.db.log_flush_waits_sec.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|MSSQL DB '{#DBNAME}': Log flush wait time|<p>Total wait time (in milliseconds) to flush the log. On an Always On secondary database, this value indicates the wait time for log records to be hardened to disk.</p>|Dependent item|mssql.db.log_flush_wait_time["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|MSSQL DB '{#DBNAME}': Log growths|<p>Total number of times the transaction log for the database has been expanded.</p>|Dependent item|mssql.db.log_growths["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Log Growths')].cntr_value.first()`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Log shrinks|<p>Total number of times the transaction log for the database has been shrunk.</p>|Dependent item|mssql.db.log_shrinks["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Log Shrinks')].cntr_value.first()`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Log truncations|<p>Number of times the transaction log has been shrunk.</p>|Dependent item|mssql.db.log_truncations["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Log Truncations')].cntr_value.first()`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Percent log used|<p>Percentage of log space in use.</p>|Dependent item|mssql.db.percent_log_used["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Percent Log Used')].cntr_value.first()`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Transactions per second|<p>Number of transactions started for the database per second.</p>|Dependent item|mssql.db.transactions_sec.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.counter_name=='Transactions/sec')].cntr_value.first()`</p></li><li>Change per second</li></ul>|
|MSSQL DB '{#DBNAME}': Last diff backup duration|<p>Duration of the last differential backup.</p>|Dependent item|mssql.backup.diff.duration["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.type=='I')].duration.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Last diff backup (time ago)|<p>The amount of time since the last differential backup.</p>|Dependent item|mssql.backup.diff["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.type=='I')].time_since_last_backup.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Last full backup duration|<p>Duration of the last full backup.</p>|Dependent item|mssql.backup.full.duration["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.type=='D')].duration.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Last full backup (time ago)|<p>The amount of time since the last full backup.</p>|Dependent item|mssql.backup.full["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.type=='D')].time_since_last_backup.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Last log backup duration|<p>Duration of the last log backup.</p>|Dependent item|mssql.backup.log.duration["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.type=='L')].duration.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Last log backup (time ago)|<p>The amount of time since the last log backup.</p>|Dependent item|mssql.backup.log["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.type=='L')].time_since_last_backup.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|MSSQL DB '{#DBNAME}': Recovery model|<p>Recovery model selected:</p><p>1 = Full</p><p>2 = Bulk_logged</p><p>3 = Simple</p>|Dependent item|mssql.backup.recovery_model["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].db_recovery_model`</p><p>⛔️Custom on fail: Set value to: `1`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MSSQL: DB '{#DBNAME}': State is {ITEM.VALUE}|<p>The DB has a non-working state.</p>|`last(/MSSQL by Zabbix agent 2/mssql.db.state["{#DBNAME}"])>1`|High||
|MSSQL: DB '{#DBNAME}': Number of commits waiting for the log flush is high|<p>Too many commits are waiting for the log flush.</p>|`min(/MSSQL by Zabbix agent 2/mssql.db.log_flush_waits_sec.rate["{#DBNAME}"],5m)>{$MSSQL.LOG_FLUSH_WAITS.MAX:"{#DBNAME}"}`|Warning||
|MSSQL: DB '{#DBNAME}': Total wait time to flush the log is high|<p>The wait time to flush the log is too long.</p>|`min(/MSSQL by Zabbix agent 2/mssql.db.log_flush_wait_time["{#DBNAME}"],5m)>{$MSSQL.LOG_FLUSH_WAIT_TIME.MAX:"{#DBNAME}"}`|Warning||
|MSSQL: DB '{#DBNAME}': Percent of log usage is high|<p>There's not enough space left in the log.</p>|`min(/MSSQL by Zabbix agent 2/mssql.db.percent_log_used["{#DBNAME}"],5m)>{$MSSQL.PERCENT_LOG_USED.MAX:"{#DBNAME}"}`|Warning||
|MSSQL: DB '{#DBNAME}': Diff backup is old|<p>The differential backup has not been executed for a long time.</p>|`last(/MSSQL by Zabbix agent 2/mssql.backup.diff["{#DBNAME}"])>{$MSSQL.BACKUP_DIFF.CRIT:"{#DBNAME}"} and {$MSSQL.BACKUP_DIFF.USED:"{#DBNAME}"}=1`|High|**Manual close**: Yes|
|MSSQL: DB '{#DBNAME}': Diff backup is old|<p>The differential backup has not been executed for a long time.</p>|`last(/MSSQL by Zabbix agent 2/mssql.backup.diff["{#DBNAME}"])>{$MSSQL.BACKUP_DIFF.WARN:"{#DBNAME}"} and {$MSSQL.BACKUP_DIFF.USED:"{#DBNAME}"}=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>MSSQL: DB '{#DBNAME}': Diff backup is old</li></ul>|
|MSSQL: DB '{#DBNAME}': Full backup is old|<p>The full backup has not been executed for a long time.</p>|`last(/MSSQL by Zabbix agent 2/mssql.backup.full["{#DBNAME}"])>{$MSSQL.BACKUP_FULL.CRIT:"{#DBNAME}"} and {$MSSQL.BACKUP_FULL.USED:"{#DBNAME}"}=1`|High|**Manual close**: Yes|
|MSSQL: DB '{#DBNAME}': Full backup is old|<p>The full backup has not been executed for a long time.</p>|`last(/MSSQL by Zabbix agent 2/mssql.backup.full["{#DBNAME}"])>{$MSSQL.BACKUP_FULL.WARN:"{#DBNAME}"} and {$MSSQL.BACKUP_FULL.USED:"{#DBNAME}"}=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>MSSQL: DB '{#DBNAME}': Full backup is old</li></ul>|
|MSSQL: DB '{#DBNAME}': Log backup is old|<p>The log backup has not been executed for a long time.</p>|`last(/MSSQL by Zabbix agent 2/mssql.backup.log["{#DBNAME}"])>{$MSSQL.BACKUP_LOG.CRIT:"{#DBNAME}"} and {$MSSQL.BACKUP_LOG.USED:"{#DBNAME}"}=1 and last(/MSSQL by Zabbix agent 2/mssql.backup.recovery_model["{#DBNAME}"])<>3`|High|**Manual close**: Yes|
|MSSQL: DB '{#DBNAME}': Log backup is old|<p>The log backup has not been executed for a long time.</p>|`last(/MSSQL by Zabbix agent 2/mssql.backup.log["{#DBNAME}"])>{$MSSQL.BACKUP_LOG.WARN:"{#DBNAME}"} and {$MSSQL.BACKUP_LOG.USED:"{#DBNAME}"}=1 and last(/MSSQL by Zabbix agent 2/mssql.backup.recovery_model["{#DBNAME}"])<>3`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>MSSQL: DB '{#DBNAME}': Log backup is old</li></ul>|

### LLD rule Availability group discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Availability group discovery|<p>Discovery of the existing availability groups.</p>|Dependent item|mssql.availability.group.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Availability group discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MSSQL AG '{#GROUP_NAME}': Primary replica recovery health|<p>Indicates the recovery health of the primary replica:</p><p>0 = In progress</p><p>1 = Online</p><p>2 = Unavailable</p>|Dependent item|mssql.primary_recovery_health["{#GROUP_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}': Primary replica name|<p>Name of the server instance that is hosting the current primary replica.</p>|Dependent item|mssql.primary_replica["{#GROUP_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.group_name=='{#GROUP_NAME}')].primary_replica.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}': Secondary replica recovery health|<p>Indicates the recovery health of a secondary replica:</p><p>0 = In progress</p><p>1 = Online</p><p>2 = Unavailable</p>|Dependent item|mssql.secondary_recovery_health["{#GROUP_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}': Synchronization health|<p>Reflects a rollup of the `synchronization_health` of all availability replicas in the availability group:</p><p>0 = Not healthy. None of the availability replicas have a healthy synchronization.</p><p>1 = Partially healthy. The synchronization of some, but not all, availability replicas is healthy.</p><p>2 = Healthy. The synchronization of every availability replica is healthy.</p>|Dependent item|mssql.synchronization_health["{#GROUP_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Availability group discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MSSQL: AG '{#GROUP_NAME}': Primary replica recovery health in progress|<p>The primary replica is in the synchronization process.</p>|`last(/MSSQL by Zabbix agent 2/mssql.primary_recovery_health["{#GROUP_NAME}"])=0`|Warning||
|MSSQL: AG '{#GROUP_NAME}': Secondary replica recovery health in progress|<p>The secondary replica is in the synchronization process.</p>|`last(/MSSQL by Zabbix agent 2/mssql.secondary_recovery_health["{#GROUP_NAME}"])=0`|Warning||
|MSSQL: AG '{#GROUP_NAME}': All replicas unhealthy|<p>None of the availability replicas have a healthy synchronization.</p>|`last(/MSSQL by Zabbix agent 2/mssql.synchronization_health["{#GROUP_NAME}"])=0`|Disaster||
|MSSQL: AG '{#GROUP_NAME}': Some replicas unhealthy|<p>The synchronization health of some, but not all, availability replicas is healthy.</p>|`last(/MSSQL by Zabbix agent 2/mssql.synchronization_health["{#GROUP_NAME}"])=1`|High||

### LLD rule Local database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Local database discovery|<p>Discovery of the local availability databases.</p>|Dependent item|mssql.local.db.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Local database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MSSQL AG '{#GROUP_NAME}' Local DB '{#DBNAME}': State|<p>0 = Online</p><p>1 = Restoring</p><p>2 = Recovering</p><p>3 = Recovery pending</p><p>4 = Suspect</p><p>5 = Emergency</p><p>6 = Offline</p>|Dependent item|mssql.local_db.state["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}' Local DB '{#DBNAME}': Suspended|<p>Database state:</p><p>0 = Resumed</p><p>1 = Suspended</p>|Dependent item|mssql.local_db.is_suspended["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}' Local DB '{#DBNAME}': Synchronization health|<p>Reflects the intersection of the synchronization state of a database that is joined to the availability group on the availability replica and the availability mode of the availability replica (synchronous-commit or asynchronous-commit mode):</p><p>0 = Not healthy. The synchronization_state of the database is 0 ("Not synchronizing").</p><p>1 = Partially healthy. A database on a synchronous-commit availability replica is considered partially healthy if synchronization_state is 1 ("Synchronizing").</p><p>2 = Healthy. A database on an synchronous-commit availability replica is considered healthy if synchronization_state is 2 ("Synchronized"), and a database on an asynchronous-commit availability replica is considered healthy if synchronization_state is 1 ("Synchronizing").</p>|Dependent item|mssql.local_db.synchronization_health["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Local database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MSSQL: AG '{#GROUP_NAME}' Local DB '{#DBNAME}': "{#DBNAME}" is {ITEM.VALUE}|<p>The local availability database has a non-working state.</p>|`last(/MSSQL by Zabbix agent 2/mssql.local_db.state["{#DBNAME}"])>0`|Warning||
|MSSQL: AG '{#GROUP_NAME}' Local DB '{#DBNAME}': "{#DBNAME}" is Not healthy|<p>The synchronization state of the local availability database is "Not synchronizing".</p>|`last(/MSSQL by Zabbix agent 2/mssql.local_db.synchronization_health["{#DBNAME}"])=0`|High||
|MSSQL: AG '{#GROUP_NAME}' Local DB '{#DBNAME}': "{#DBNAME}" is Partially healthy|<p>A database on a synchronous-commit availability replica is considered partially healthy if synchronization state is "Synchronizing".</p>|`last(/MSSQL by Zabbix agent 2/mssql.local_db.synchronization_health["{#DBNAME}"])=1`|Average||

### LLD rule Non-local database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Non-local database discovery|<p>Discovery of the non-local (not local to SQL Server instance) availability databases.</p>|Dependent item|mssql.non.local.db.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Non-local database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MSSQL AG '{#GROUP_NAME}' Non-Local DB '*{#REPLICA_NAME}*{#DBNAME}': Log queue size|<p>Amount of the log records of the primary database that has not been sent to the secondary databases.</p>|Dependent item|mssql.non-local_db.log_send_queue_size["{#GROUP_NAME}*{#REPLICA_NAME}*{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}' Non-Local DB '*{#REPLICA_NAME}*{#DBNAME}': Redo log queue size|<p>Amount of log records in the log files of the secondary replica that has not yet been redone.</p>|Dependent item|mssql.non-local_db.redo_queue_size["{#GROUP_NAME}*{#REPLICA_NAME}*{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Non-local database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MSSQL: AG '{#GROUP_NAME}' Non-Local DB '*{#REPLICA_NAME}*{#DBNAME}': Log queue size is growing|<p>The log records of the primary database are not sent to the secondary databases.</p>|`last(/MSSQL by Zabbix agent 2/mssql.non-local_db.log_send_queue_size["{#GROUP_NAME}*{#REPLICA_NAME}*{#DBNAME}"],#1)>last(/MSSQL by Zabbix agent 2/mssql.non-local_db.log_send_queue_size["{#GROUP_NAME}*{#REPLICA_NAME}*{#DBNAME}"],#2) and last(/MSSQL by Zabbix agent 2/mssql.non-local_db.log_send_queue_size["{#GROUP_NAME}*{#REPLICA_NAME}*{#DBNAME}"],#2)>last(/MSSQL by Zabbix agent 2/mssql.non-local_db.log_send_queue_size["{#GROUP_NAME}*{#REPLICA_NAME}*{#DBNAME}"],#3)`|High||
|MSSQL: AG '{#GROUP_NAME}' Non-Local DB '*{#REPLICA_NAME}*{#DBNAME}': Redo log queue size is growing|<p>The log records in the log files of the secondary replica have not yet been redone.</p>|`last(/MSSQL by Zabbix agent 2/mssql.non-local_db.redo_queue_size["{#GROUP_NAME}*{#REPLICA_NAME}*{#DBNAME}"],#1)>last(/MSSQL by Zabbix agent 2/mssql.non-local_db.redo_queue_size["{#GROUP_NAME}*{#REPLICA_NAME}*{#DBNAME}"],#2) and last(/MSSQL by Zabbix agent 2/mssql.non-local_db.redo_queue_size["{#GROUP_NAME}*{#REPLICA_NAME}*{#DBNAME}"],#2)>last(/MSSQL by Zabbix agent 2/mssql.non-local_db.redo_queue_size["{#GROUP_NAME}*{#REPLICA_NAME}*{#DBNAME}"],#3)`|High||

### LLD rule Quorum discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Quorum discovery|<p>Discovery of the quorum of the WSFC cluster.</p>|Dependent item|mssql.quorum.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Quorum discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MSSQL Cluster '{#CLUSTER_NAME}': Quorum type|<p>Type of quorum used by this WSFC cluster, one of:</p><p>0 = Node Majority. This quorum configuration can sustain failures of half the nodes (rounding up) minus one.</p><p>1 = Node and Disk Majority. If the disk witness remains on line, this quorum configuration can sustain failures of half the nodes (rounding up).</p><p>2 = Node and File Share Majority. This quorum configuration works in a similar way to Node and Disk Majority, but uses a file-share witness instead of a disk witness.</p><p>3 = No Majority: Disk Only. If the quorum disk is online, this quorum configuration can sustain failures of all nodes except one.</p><p>4 = Unknown Quorum. Unknown quorum for the cluster.</p><p>5 = Cloud Witness. Cluster utilizes Microsoft Azure for quorum arbitration. If the cloud witness is available, the cluster can sustain the failure of half the nodes (rounding up).</p>|Dependent item|mssql.quorum.type.[{#CLUSTER_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.cluster_name=='{#CLUSTER_NAME}')].quorum_type.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|MSSQL Cluster '{#CLUSTER_NAME}': Quorum state|<p>State of the WSFC quorum, one of:</p><p>0 = Unknown quorum state</p><p>1 = Normal quorum</p><p>2 = Forced quorum</p>|Dependent item|mssql.quorum.state.[{#CLUSTER_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.cluster_name=='{#CLUSTER_NAME}')].quorum_state.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Quorum members discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Quorum members discovery|<p>Discovery of the quorum members of the WSFC cluster.</p>|Dependent item|mssql.quorum.member.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Quorum members discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MSSQL Cluster member '{#MEMBER_NAME}': Number of quorum votes|<p>Number of quorum votes possessed by this quorum member.</p>|Dependent item|mssql.quorum_members.number_of_quorum_votes.[{#MEMBER_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|MSSQL Cluster member '{#MEMBER_NAME}': Member type|<p>The type of member, one of:</p><p>0 = WSFC node</p><p>1 = Disk witness</p><p>2 = File share witness</p><p>3 = Cloud Witness</p>|Dependent item|mssql.quorum_members.member_type.[{#MEMBER_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.member_name=='{#MEMBER_NAME}')].member_type.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|MSSQL Cluster member '{#MEMBER_NAME}': Member state|<p>The member state, one of:</p><p>0 = Offline</p><p>1 = Online</p>|Dependent item|mssql.quorum_members.member_state.[{#MEMBER_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.member_name=='{#MEMBER_NAME}')].member_state.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication discovery|<p>Discovery of the database replicas.</p>|Dependent item|mssql.replica.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MSSQL AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': Connected state|<p>Whether a secondary replica is currently connected to the primary replica:</p><p>0 = Disconnected. The response of an availability replica to the "Disconnected" state depends on its role:</p><p>On the primary replica, if a secondary replica is disconnected, its secondary databases are marked as "Not synchronized" on the primary replica, which waits for the secondary to reconnect;</p><p>On a secondary replica, upon detecting that it is disconnected, the secondary replica attempts to reconnect to the primary replica.</p><p>1 = Connected. Each primary replica tracks the connection state for every secondary replica in the same availability group. Secondary replicas track the connection state of only the primary replica.</p>|Dependent item|mssql.replica.connected_state["{#GROUP_NAME}_{#REPLICA_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': Is local|<p>Whether the replica is local:</p><p>0 = Indicates a remote secondary replica in an availability group whose primary replica is hosted by the local server instance. This value occurs only on the primary replica location.</p><p>1 = Indicates a local replica. On secondary replicas, this is the only available value for the availability group to which the replica belongs.</p>|Dependent item|mssql.replica.is_local["{#GROUP_NAME}_{#REPLICA_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': Join state|<p>0 = Not joined</p><p>1 = Joined, standalone instance</p><p>2 = Joined, failover cluster instance</p>|Dependent item|mssql.replica.join_state["{#GROUP_NAME}_{#REPLICA_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': Operational state|<p>Current operational state of the replica:</p><p>0 = Pending failover</p><p>1 = Pending</p><p>2 = Online</p><p>3 = Offline</p><p>4 = Failed</p><p>5 = Failed, no quorum</p><p>6 = Not local</p>|Dependent item|mssql.replica.operational_state["{#GROUP_NAME}_{#REPLICA_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': Recovery health|<p>Rollup of the "database_state" column of the `sys.dm_hadr_database_replica_states` dynamic management view:</p><p>0 = In progress. At least one joined database has a database state other than "Online" (database_state is not "0").</p><p>1 = Online. All the joined databases have a database state of "Online" (database_state is "0").</p>|Dependent item|mssql.replica.recovery_health["{#GROUP_NAME}_{#REPLICA_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': Role|<p>Current Always On availability group role of a local replica or a connected remote replica:</p><p>0 = Resolving</p><p>1 = Primary</p><p>2 = Secondary</p>|Dependent item|mssql.replica.role["{#GROUP_NAME}_{#REPLICA_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': Sync health|<p>Reflects a rollup of the database synchronization state (synchronization_state) of all joined availability databases (also known as replicas) and the availability mode of the replica (synchronous-commit or asynchronous-commit mode). The rollup will reflect the least healthy accumulated state of the databases on the replica:</p><p>0 = Not healthy. At least one joined database is in the "Not synchronizing" state.</p><p>1 = Partially healthy. Some replicas are not in the target synchronization state: synchronous-commit replicas should be synchronized, and asynchronous-commit replicas should be synchronizing.</p><p>2 = Healthy. All replicas are in the target synchronization state: synchronous-commit replicas are synchronized, and asynchronous-commit replicas are synchronizing.</p>|Dependent item|mssql.replica.synchronization_health["{#GROUP_NAME}_{#REPLICA_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Replication discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MSSQL: AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': {#REPLICA_NAME} is disconnected|<p>The response of an availability replica to the "Disconnected" state depends on its role:<br>On the primary replica, if a secondary replica is disconnected, its secondary databases are marked as "Not synchronized" on the primary replica, which waits for the secondary to reconnect;<br>On a secondary replica, upon detecting that it is disconnected, the secondary replica attempts to reconnect to the primary replica.</p>|`last(/MSSQL by Zabbix agent 2/mssql.replica.connected_state["{#GROUP_NAME}_{#REPLICA_NAME}"])=0 and last(/MSSQL by Zabbix agent 2/mssql.replica.role["{#GROUP_NAME}_{#REPLICA_NAME}"])=2`|Warning||
|MSSQL: AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': {#REPLICA_NAME} is {ITEM.VALUE}|<p>The operational state of the replica in a given availability group is "Pending" or "Offline".</p>|`last(/MSSQL by Zabbix agent 2/mssql.replica.operational_state["{#GROUP_NAME}_{#REPLICA_NAME}"])=0 or last(/MSSQL by Zabbix agent 2/mssql.replica.operational_state["{#GROUP_NAME}_{#REPLICA_NAME}"])=1 or last(/MSSQL by Zabbix agent 2/mssql.replica.operational_state["{#GROUP_NAME}_{#REPLICA_NAME}"])=3`|Warning||
|MSSQL: AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': {#REPLICA_NAME} is {ITEM.VALUE}|<p>The operational state of the replica in a given availability group is "Failed".</p>|`last(/MSSQL by Zabbix agent 2/mssql.replica.operational_state["{#GROUP_NAME}_{#REPLICA_NAME}"])=4`|Average||
|MSSQL: AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': {#REPLICA_NAME} is {ITEM.VALUE}|<p>The operational state of the replica in a given availability group is "Failed, no quorum".</p>|`last(/MSSQL by Zabbix agent 2/mssql.replica.operational_state["{#GROUP_NAME}_{#REPLICA_NAME}"])=5`|High||
|MSSQL: AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': {#REPLICA_NAME} Recovery in progress|<p>At least one joined database has a database state other than "Online".</p>|`last(/MSSQL by Zabbix agent 2/mssql.replica.recovery_health["{#GROUP_NAME}_{#REPLICA_NAME}"])=0`|Info||
|MSSQL: AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': {#REPLICA_NAME} is Not healthy|<p>At least one joined database is in the "Not synchronizing" state.</p>|`last(/MSSQL by Zabbix agent 2/mssql.replica.synchronization_health["{#GROUP_NAME}_{#REPLICA_NAME}"])=0`|Average||
|MSSQL: AG '{#GROUP_NAME}' Replica '{#REPLICA_NAME}': {#REPLICA_NAME} is Partially healthy|<p>Some replicas are not in the target synchronization state: synchronous-commit replicas should be synchronized, and asynchronous-commit replicas should be synchronizing.</p>|`last(/MSSQL by Zabbix agent 2/mssql.replica.synchronization_health["{#GROUP_NAME}_{#REPLICA_NAME}"])=1`|Warning||

### LLD rule Mirroring discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mirroring discovery|<p>To see the row for a database other than master or tempdb, you must either be the database owner or have at least ALTER ANY DATABASE or VIEW ANY DATABASE server-level permission or CREATE DATABASE permission in the master database. To see non-NULL values on a mirror database, you must be a member of the sysadmin fixed server role.</p>|Dependent item|mssql.mirroring.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Mirroring discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MSSQL Mirroring '{#DBNAME}': Role|<p>Current role of the local database plays in the database mirroring session.</p><p>1 = Principal</p><p>2 = Mirror</p>|Dependent item|mssql.mirroring.role["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.dbname=='{#DBNAME}')].mirroring_role.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL Mirroring '{#DBNAME}': Role sequence|<p>The number of times that mirroring partners have switched the principal and mirror roles due to a failover or forced service.</p>|Dependent item|mssql.mirroring.role_sequence["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.dbname=='{#DBNAME}')].mirroring_role_sequence.first()`</p></li><li>Simple change</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL Mirroring '{#DBNAME}': State|<p>State of the mirror database and of the database mirroring session.</p><p>0 = Suspended</p><p>1 = Disconnected from the other partner</p><p>2 = Synchronizing</p><p>3 = Pending failover</p><p>4 = Synchronized</p><p>5 = The partners are not synchronized. Failover is not possible now.</p><p>6 = The partners are synchronized. Failover is potentially possible. For information about the requirements for the failover, see Database Mirroring Operating Modes: https://learn.microsoft.com/en-us/sql/database-engine/database-mirroring/database-mirroring-operating-modes?view=sql-server-ver16.</p>|Dependent item|mssql.mirroring.state["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.dbname=='{#DBNAME}')].mirroring_state.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL Mirroring '{#DBNAME}': Witness state|<p>State of the witness in the database mirroring session of the database:</p><p>0 = Unknown</p><p>1 = Connected</p><p>2 = Disconnected</p>|Dependent item|mssql.mirroring.witness_state["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.dbname=='{#DBNAME}')].mirroring_witness_state.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MSSQL Mirroring '{#DBNAME}': Safety level|<p>Safety setting for updates on the mirror database:</p><p>0 = Unknown state</p><p>1 = Off [asynchronous]</p><p>2 = Full [synchronous]</p>|Dependent item|mssql.mirroring.safety_level["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.dbname=='{#DBNAME}')].mirroring_safety_level.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Mirroring discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MSSQL: Mirroring '{#DBNAME}': "{#DBNAME}" is {ITEM.VALUE}|<p>The state of the mirror database and of the database mirroring session is "Suspended", "Disconnected from the other partner", or "Synchronizing".</p>|`last(/MSSQL by Zabbix agent 2/mssql.mirroring.state["{#DBNAME}"])>=0 and last(/MSSQL by Zabbix agent 2/mssql.mirroring.state["{#DBNAME}"])<=2`|Info||
|MSSQL: Mirroring '{#DBNAME}': "{#DBNAME}" is {ITEM.VALUE}|<p>The state of the mirror database and of the database mirroring session is "Pending failover".</p>|`last(/MSSQL by Zabbix agent 2/mssql.mirroring.state["{#DBNAME}"])=3`|Warning||
|MSSQL: Mirroring '{#DBNAME}': "{#DBNAME}" is {ITEM.VALUE}|<p>The state of the mirror database and of the database mirroring session is "Not synchronized". The partners are not synchronized. A failover is not possible now.</p>|`last(/MSSQL by Zabbix agent 2/mssql.mirroring.state["{#DBNAME}"])=5`|High||
|MSSQL: Mirroring '{#DBNAME}': "{#DBNAME}" Witness is disconnected|<p>The state of the witness in the database mirroring session of the database is "Disconnected".</p>|`last(/MSSQL by Zabbix agent 2/mssql.mirroring.witness_state["{#DBNAME}"])=2`|Warning||

### LLD rule Job discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Job discovery|<p>Scanning jobs in DBMS.</p>|Dependent item|mssql.job.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Job discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MSSQL Job '{#JOBNAME}': Get job status|<p>The item gets the status of SQL agent job {#JOBNAME}.</p>|Dependent item|mssql.job.status_raw["{#JOBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.job_name=='{#JOBNAME}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MSSQL Job '{#JOBNAME}': Enabled|<p>The possible values of the job status:</p><p>0 = Disabled</p><p>1 = Enabled</p>|Dependent item|mssql.job.enabled["{#JOBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enabled`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|MSSQL Job '{#JOBNAME}': Last run date-time|<p>The last date-time of the job run.</p>|Dependent item|mssql.job.lastrundatetime["{#JOBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.last_run_date_time`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|MSSQL Job '{#JOBNAME}': Next run date-time|<p>The next date-time of the job run.</p>|Dependent item|mssql.job.nextrundatetime["{#JOBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.next_run_date_time`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|MSSQL Job '{#JOBNAME}': Last run status message|<p>An informational message about the last run of the job.</p>|Dependent item|mssql.job.lastrunstatusmessage["{#JOBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.last_run_status_message`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|MSSQL Job '{#JOBNAME}': Run status|<p>The possible values of the job status:</p><p>0 ⇒ Failed</p><p>1 ⇒ Succeeded</p><p>2 ⇒ Retry</p><p>3 ⇒ Canceled</p><p>4 ⇒ Running</p>|Dependent item|mssql.job.runstatus["{#JOBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.run_status`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|MSSQL Job '{#JOBNAME}': Run duration|<p>Duration of the last-run job.</p>|Dependent item|mssql.job.run_duration["{#JOBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.run_duration`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|

### Trigger prototypes for Job discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MSSQL: Job '{#JOBNAME}': Failed to run|<p>The last run of the job has failed.</p>|`last(/MSSQL by Zabbix agent 2/mssql.job.runstatus["{#JOBNAME}"])=0`|Warning|**Manual close**: Yes|
|MSSQL: Job '{#JOBNAME}': Job duration is high|<p>The job is taking too long.</p>|`last(/MSSQL by Zabbix agent 2/mssql.job.run_duration["{#JOBNAME}"])>{$MSSQL.JOB_DURATION.WARN:"{#JOBNAME}"}`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

