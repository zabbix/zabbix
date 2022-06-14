
# PostgreSQL by Zabbix agent 2

## Overview

For Zabbix version: 6.0 and higher  
The template is developed for monitoring DBMS PostgreSQL and its forks.



This template was tested on:

- PostgreSQL, version 10, 11, 12

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

1\. Create PostgreSQL user for monitoring (`<password>` at your discretion):

```bash
CREATE USER zbx_monitor WITH PASSWORD '<PASSWORD>' INHERIT;
GRANT EXECUTE ON FUNCTION pg_catalog.pg_ls_dir(text) TO zbx_monitor;
GRANT EXECUTE ON FUNCTION pg_catalog.pg_stat_file(text) TO zbx_monitor;
GRANT EXECUTE ON FUNCTION pg_catalog.pg_ls_waldir() TO zbx_monitor;
```

2\. Edit pg_hba.conf to allow connections from Zabbix agent:
  
```bash
# TYPE  DATABASE        USER            ADDRESS                 METHOD
  host       all        zbx_monitor     localhost               md5
```

For more information please read the PostgreSQL documentation https://www.postgresql.org/docs/current/auth-pg-hba-conf.html.

3\. Set in the {$PG.URI} macro the system data source name of the PostgreSQL instance such as <protocol(host:port)>.

4\. Set the user name and password in host macros ({$PG.USER} and {$PG.PASSWORD}) if you want to override parameters from the Zabbix agent configuration file.



## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PG.CONFLICTS.MAX.WARN} |<p>-</p> |`0` |
|{$PG.CONN_TOTAL_PCT.MAX.WARN} |<p>-</p> |`90` |
|{$PG.DATABASE} |<p>-</p> |`postgres` |
|{$PG.DEADLOCKS.MAX.WARN} |<p>-</p> |`0` |
|{$PG.LLD.FILTER.APPLICATION} |<p>-</p> |`(.+)` |
|{$PG.LLD.FILTER.DBNAME} |<p>-</p> |`(.+)` |
|{$PG.PASSWORD} |<p>-</p> |`postgres` |
|{$PG.QUERY_ETIME.MAX.WARN} |<p>Execution time limit for count of slow queries.</p> |`30` |
|{$PG.SLOW_QUERIES.MAX.WARN} |<p>Slow queries count threshold for a trigger.</p> |`5` |
|{$PG.URI} |<p>-</p> |`tcp://localhost:5432` |
|{$PG.USER} |<p>-</p> |`postgres` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Database discovery |<p>-</p> |ZABBIX_PASSIVE |pgsql.db.discovery["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]<p>**Filter**:</p>AND <p>- {#DBNAME} MATCHES_REGEX `{$PG.LLD.FILTER.DBNAME}`</p> |
|Replication Discovery |<p>-</p> |ZABBIX_PASSIVE |pgsql.replication.process.discovery["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]<p>**Filter**:</p>AND <p>- {#APPLICATION_NAME} MATCHES_REGEX `{$PG.LLD.FILTER.APPLICATION}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|PostgreSQL |PostgreSQL: Custom queries |<p>Execute custom queries from file *.sql (check for option Plugins.Postgres.CustomQueriesPath at agent configuration)</p> |ZABBIX_PASSIVE |pgsql.custom.query["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}",""] |
|PostgreSQL |WAL: Bytes written |<p>WAL write in bytes</p> |DEPENDENT |pgsql.wal.write<p>**Preprocessing**:</p><p>- JSONPATH: `$.write`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |WAL: Bytes received |<p>WAL receive in bytes</p> |DEPENDENT |pgsql.wal.receive<p>**Preprocessing**:</p><p>- JSONPATH: `$.receive`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |WAL: Segments count |<p>Number of WAL segments</p> |DEPENDENT |pgsql.wal.count<p>**Preprocessing**:</p><p>- JSONPATH: `$.count`</p> |
|PostgreSQL |Bgwriter: Buffers allocated |<p>Number of buffers allocated</p> |DEPENDENT |pgsql.bgwriter.buffers_alloc.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffers_alloc`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Buffers written directly by a backend |<p>Number of buffers written directly by a backend</p> |DEPENDENT |pgsql.bgwriter.buffers_backend.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffers_backend`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Number of bgwriter stopped |<p>Number of times the background writer stopped a cleaning scan because it had written too many buffers</p> |DEPENDENT |pgsql.bgwriter.maxwritten_clean.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.maxwritten_clean`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Times a backend execute its own fsync |<p>Number of times a backend had to execute its own fsync call (normally the background writer handles those even when the backend does its own write)</p> |DEPENDENT |pgsql.bgwriter.buffers_backend_fsync.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffers_backend_fsync`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Checkpoint: Buffers background written |<p>Number of buffers written by the background writer</p> |DEPENDENT |pgsql.bgwriter.buffers_clean.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffers_clean`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Checkpoint: Buffers checkpoints written |<p>Number of buffers written during checkpoints</p> |DEPENDENT |pgsql.bgwriter.buffers_checkpoint.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffers_checkpoint`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Checkpoint: By timeout |<p>Number of scheduled checkpoints that have been performed</p> |DEPENDENT |pgsql.bgwriter.checkpoints_timed.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.checkpoints_timed`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Checkpoint: Requested |<p>Number of requested checkpoints that have been performed</p> |DEPENDENT |pgsql.bgwriter.checkpoints_req.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.checkpoints_req`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Checkpoint: Checkpoint write time |<p>Total amount of time that has been spent in the portion of checkpoint processing where files are written to disk, in milliseconds</p> |DEPENDENT |pgsql.bgwriter.checkpoint_write_time.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.checkpoint_write_time`</p><p>- MULTIPLIER: `0.001`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Checkpoint: Checkpoint write time |<p>Total amount of time that has been spent in the portion of checkpoint processing where files are synchronized to disk, in milliseconds</p> |DEPENDENT |pgsql.bgwriter.checkpoint_sync_time.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.checkpoint_sync_time`</p><p>- MULTIPLIER: `0.001`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Checkpoint: Checkpoint sync time |<p>Total amount of time that has been spent in the portion of checkpoint processing where files are synchronized to disk</p> |DEPENDENT |pgsql.bgwriter.checkpoint_sync_time.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.checkpoint_sync_time`</p><p>- MULTIPLIER: `0.001`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Archive: Count of archive files |<p>Collect all metrics from pg_stat_activity</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ARCHIVER-VIEW</p> |DEPENDENT |pgsql.archive.count_archived_files<p>**Preprocessing**:</p><p>- JSONPATH: `$.archived_count`</p> |
|PostgreSQL |Archive: Count of attempts to archive files |<p>Collect all metrics from pg_stat_activity</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ARCHIVER-VIEW</p> |DEPENDENT |pgsql.archive.failed_trying_to_archive<p>**Preprocessing**:</p><p>- JSONPATH: `$.failed_count`</p> |
|PostgreSQL |Archive: Count of files in archive_status need to archive |<p>-</p> |DEPENDENT |pgsql.archive.count_files_to_archive<p>**Preprocessing**:</p><p>- JSONPATH: `$.count_files`</p> |
|PostgreSQL |Archive: Count of files need to archive |<p>Size of files to archive</p> |DEPENDENT |pgsql.archive.size_files_to_archive<p>**Preprocessing**:</p><p>- JSONPATH: `$.size_files`</p> |
|PostgreSQL |Dbstat: Blocks read time |<p>Time spent reading data file blocks by backends, in milliseconds</p> |DEPENDENT |pgsql.dbstat.sum.blk_read_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.blk_read_time`</p><p>- MULTIPLIER: `0.001`</p> |
|PostgreSQL |Dbstat: Blocks write time |<p>Time spent writing data file blocks by backends, in milliseconds</p> |DEPENDENT |pgsql.dbstat.sum.blk_write_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.blk_read_time`</p><p>- MULTIPLIER: `0.001`</p> |
|PostgreSQL |Dbstat: Checksum failures |<p>Number of data page checksum failures detected (or on a shared object), or NULL if data checksums are not enabled. This metric included in PostgreSQL 12</p> |DEPENDENT |pgsql.dbstat.sum.checksum_failures.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.checksum_failures`</p><p>- MATCHES_REGEX: `^\d*$`</p><p>- CHANGE_PER_SECOND</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> -1`</p> |
|PostgreSQL |Dbstat: Committed transactions |<p>Number of transactions that have been committed</p> |DEPENDENT |pgsql.dbstat.sum.xact_commit.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.xact_commit`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Conflicts |<p>Number of queries canceled due to conflicts with recovery.  (Conflicts occur only on standby servers; see pg_stat_database_conflicts for details.)</p> |DEPENDENT |pgsql.dbstat.sum.conflicts.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.conflicts`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Deadlocks |<p>Number of deadlocks detected</p> |DEPENDENT |pgsql.dbstat.sum.deadlocks.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.deadlocks`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Disk blocks read |<p>Number of disk blocks read</p> |DEPENDENT |pgsql.dbstat.sum.blks_read.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.blks_read`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Hit blocks read |<p>Number of times disk blocks were found already in the buffer cache</p> |DEPENDENT |pgsql.dbstat.sum.blks_hit.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.blks_hit`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Number temp bytes |<p>Total amount of data written to temporary files by queries</p> |DEPENDENT |pgsql.dbstat.sum.temp_bytes.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.temp_bytes`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Number temp bytes |<p>Number of temporary files created by queries</p> |DEPENDENT |pgsql.dbstat.sum.temp_files.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.temp_files`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Roll backed transactions |<p>Number of transactions that have been rolled back</p> |DEPENDENT |pgsql.dbstat.sum.xact_rollback.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.xact_rollback`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Rows deleted |<p>Number of rows deleted by queries</p> |DEPENDENT |pgsql.dbstat.sum.tup_deleted.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.tup_deleted`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Rows fetched |<p>Number of rows fetched by queries</p> |DEPENDENT |pgsql.dbstat.sum.tup_fetched.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.tup_fetched`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Rows inserted |<p>Number of rows inserted by queries</p> |DEPENDENT |pgsql.dbstat.sum.tup_inserted.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.tup_inserted`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Rows returned |<p>Number of rows returned by queries</p> |DEPENDENT |pgsql.dbstat.sum.tup_returned.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.tup_returned`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Rows updated |<p>Number of rows updated by queries</p> |DEPENDENT |pgsql.dbstat.sum.tup_updated.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.tup_updated`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Dbstat: Backends connected |<p>Number of connected backends</p> |DEPENDENT |pgsql.dbstat.sum.numbackends<p>**Preprocessing**:</p><p>- JSONPATH: `$.numbackends`</p> |
|PostgreSQL |Connections sum: Active |<p>Total number of connections executing a query</p> |DEPENDENT |pgsql.connections.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.active`</p> |
|PostgreSQL |Connections sum: Fastpath function call |<p>Total number of connections executing a fast-path function</p> |DEPENDENT |pgsql.connections.fastpath_function_call<p>**Preprocessing**:</p><p>- JSONPATH: `$.idle_in_transaction`</p> |
|PostgreSQL |Connections sum: Idle |<p>Total number of connections waiting for a new client command</p> |DEPENDENT |pgsql.connections.idle<p>**Preprocessing**:</p><p>- JSONPATH: `$.idle`</p> |
|PostgreSQL |Connections sum: Idle in transaction |<p>Total number of connections in a transaction state, but not executing a query</p> |DEPENDENT |pgsql.connections.idle_in_transaction<p>**Preprocessing**:</p><p>- JSONPATH: `$.idle_in_transaction`</p> |
|PostgreSQL |Connections sum: Prepared |<p>Total number of prepared transactions</p><p>https://www.postgresql.org/docs/current/sql-prepare-transaction.html</p> |DEPENDENT |pgsql.connections.prepared<p>**Preprocessing**:</p><p>- JSONPATH: `$.prepared`</p> |
|PostgreSQL |Connections sum: Total |<p>Total number of connections</p> |DEPENDENT |pgsql.connections.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.total`</p> |
|PostgreSQL |Connections sum: Total % |<p>Total number of connections in percentage</p> |DEPENDENT |pgsql.connections.total_pct<p>**Preprocessing**:</p><p>- JSONPATH: `$.total_pct`</p> |
|PostgreSQL |Connections sum: Waiting |<p>Total number of waiting connections</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#WAIT-EVENT-TABLE</p> |DEPENDENT |pgsql.connections.waiting<p>**Preprocessing**:</p><p>- JSONPATH: `$.waiting`</p> |
|PostgreSQL |Connections sum: Idle in transaction (aborted) |<p>Total number of connections in a transaction state, but not executing a query and one of the statements in the transaction caused an error.</p> |DEPENDENT |pgsql.connections.idle_in_transaction_aborted<p>**Preprocessing**:</p><p>- JSONPATH: `$.idle_in_transaction_aborted`</p> |
|PostgreSQL |Connections sum: Disabled |<p>Total number of disabled connections</p> |DEPENDENT |pgsql.connections.disabled<p>**Preprocessing**:</p><p>- JSONPATH: `$.disabled`</p> |
|PostgreSQL |PostgreSQL: Age of oldest xid |<p>Age of oldest xid.</p> |ZABBIX_PASSIVE |pgsql.oldest.xid["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|PostgreSQL |Autovacuum: Count of autovacuum workers |<p>Number of autovacuum workers.</p> |ZABBIX_PASSIVE |pgsql.autovacuum.count["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|PostgreSQL |PostgreSQL: Cache hit |<p>-</p> |CALCULATED |pgsql.cache.hit["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]<p>**Expression**:</p>`last(//pgsql.dbstat.sum.blks_hit.rate) * 100 / (last(//pgsql.dbstat.sum.blks_hit.rate) + last(//pgsql.dbstat.sum.blks_read.rate))` |
|PostgreSQL |PostgreSQL: Uptime |<p>-</p> |ZABBIX_PASSIVE |pgsql.uptime["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|PostgreSQL |Replication: Lag in bytes |<p>Replication lag with Master in byte.</p> |ZABBIX_PASSIVE |pgsql.replication.lag.b["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|PostgreSQL |Replication: Lag in seconds |<p>Replication lag with Master in seconds.</p> |ZABBIX_PASSIVE |pgsql.replication.lag.sec["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|PostgreSQL |Replication: Recovery role |<p>Replication role: 1 — recovery is still in progress (standby mode), 0 — master mode.</p> |ZABBIX_PASSIVE |pgsql.replication.recovery_role["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|PostgreSQL |Replication: Standby count |<p>Number of standby servers</p> |ZABBIX_PASSIVE |pgsql.replication.count["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|PostgreSQL |Replication: Status |<p>Replication status: 0 — streaming is down, 1 — streaming is up, 2 — master mode</p> |ZABBIX_PASSIVE |pgsql.replication.status["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|PostgreSQL |PostgreSQL: Ping |<p>-</p> |ZABBIX_PASSIVE |pgsql.ping["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|PostgreSQL |Application {#APPLICATION_NAME}: Replication flush lag | |DEPENDENT |pgsql.replication.process.flush_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#APPLICATION_NAME}'].flush_lag`</p> |
|PostgreSQL |Application {#APPLICATION_NAME}: Replication replay lag | |DEPENDENT |pgsql.replication.process.replay_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#APPLICATION_NAME}'].replay_lag`</p> |
|PostgreSQL |Application {#APPLICATION_NAME}: Replication write lag | |DEPENDENT |pgsql.replication.process.write_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#APPLICATION_NAME}'].write_lag`</p> |
|PostgreSQL |DB {#DBNAME}: Database age |<p>Database age</p> |ZABBIX_PASSIVE |pgsql.db.age["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"] |
|PostgreSQL |DB {#DBNAME}: Get bloating tables |<p>Number of bloating tables</p> |ZABBIX_PASSIVE |pgsql.db.bloating_tables["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"] |
|PostgreSQL |DB {#DBNAME}: Database size |<p>Database size</p> |ZABBIX_PASSIVE |pgsql.db.size["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"] |
|PostgreSQL |DB {#DBNAME}: Blocks hit per second |<p>Total number of times disk blocks were found already in the buffer cache, so that a read was not necessary</p> |DEPENDENT |pgsql.dbstat.blks_hit.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].blks_hit`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Disk blocks read per second |<p>Total number of disk blocks read in this database</p> |DEPENDENT |pgsql.dbstat.blks_read.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].blks_read`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Detected conflicts per second |<p>Total number of queries canceled due to conflicts with recovery in this database</p> |DEPENDENT |pgsql.dbstat.conflicts.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].conflicts`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Detected deadlocks per second |<p>Total number of detected deadlocks in this database</p> |DEPENDENT |pgsql.dbstat.deadlocks.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].deadlocks`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Temp_bytes written per second |<p>Total amount of data written to temporary files by queries in this database</p> |DEPENDENT |pgsql.dbstat.temp_bytes.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].temp_bytes`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Temp_files created per second |<p>Total number of temporary files created by queries in this database</p> |DEPENDENT |pgsql.dbstat.temp_files.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].temp_files`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Tuples deleted per second |<p>Total number of rows deleted by queries in this database</p> |DEPENDENT |pgsql.dbstat.tup_deleted.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tup_deleted`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Tuples fetched per second |<p>Total number of rows fetched by queries in this database</p> |DEPENDENT |pgsql.dbstat.tup_fetched.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tup_fetched`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Tuples inserted per second |<p>Total number of rows inserted by queries in this database</p> |DEPENDENT |pgsql.dbstat.tup_inserted.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tup_inserted`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Tuples returned per second |<p>Number of rows returned by queries in this database</p> |DEPENDENT |pgsql.dbstat.tup_returned.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tup_returned`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Tuples updated per second |<p>Total number of rows updated by queries in this database</p> |DEPENDENT |pgsql.dbstat.tup_updated.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tup_updated`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Commits per second |<p>Number of transactions in this database that have been committed</p> |DEPENDENT |pgsql.dbstat.xact_commit.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].xact_commit`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Rollbacks per second |<p>Total number of transactions in this database that have been rolled back</p> |DEPENDENT |pgsql.dbstat.xact_rollback.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].xact_rollback`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Backends connected |<p>Number of backends currently connected to this database</p> |DEPENDENT |pgsql.dbstat.numbackends["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].numbackends`</p> |
|PostgreSQL |DB {#DBNAME}: Checksum failures |<p>Number of data page checksum failures detected in this database</p> |DEPENDENT |pgsql.dbstat.checksum_failures.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].checksum_failures`</p><p>- MATCHES_REGEX: `^\d*$`</p><p>- CHANGE_PER_SECOND</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> -1`</p> |
|PostgreSQL |DB {#DBNAME}: Disk blocks read time |<p>Time spent reading data file blocks by backends, in milliseconds</p> |DEPENDENT |pgsql.dbstat.blk_read_time.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].blk_read_time`</p><p>- MULTIPLIER: `0.001`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Disk blocks write time |<p>Time spent writing data file blocks by backends, in milliseconds</p> |DEPENDENT |pgsql.dbstat.blk_write_time.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].blk_write_time`</p><p>- MULTIPLIER: `0.001`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Num of accessexclusive locks |<p>Number of accessexclusive locks for each database</p> |DEPENDENT |pgsql.locks.accessexclusive["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].accessexclusive`</p> |
|PostgreSQL |DB {#DBNAME}: Num of accessshare locks |<p>Number of accessshare locks for each database</p> |DEPENDENT |pgsql.locks.accessshare["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].accessshare`</p> |
|PostgreSQL |DB {#DBNAME}: Num of exclusive locks |<p>Number of exclusive locks for each database</p> |DEPENDENT |pgsql.locks.exclusive["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].exclusive`</p> |
|PostgreSQL |DB {#DBNAME}: Num of rowexclusive locks |<p>Number of rowexclusive locks for each database</p> |DEPENDENT |pgsql.locks.rowexclusive["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].rowexclusive`</p> |
|PostgreSQL |DB {#DBNAME}: Num of rowshare locks |<p>Number of rowshare locks for each database</p> |DEPENDENT |pgsql.locks.rowshare["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].rowshare`</p> |
|PostgreSQL |DB {#DBNAME}: Num of sharerowexclusive locks |<p>Number of total sharerowexclusive for each database</p> |DEPENDENT |pgsql.locks.sharerowexclusive["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].sharerowexclusive`</p> |
|PostgreSQL |DB {#DBNAME}: Num of shareupdateexclusive locks |<p>Number of shareupdateexclusive locks for each database</p> |DEPENDENT |pgsql.locks.shareupdateexclusive["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].shareupdateexclusive`</p> |
|PostgreSQL |DB {#DBNAME}: Num of share locks |<p>Number of share locks for each database</p> |DEPENDENT |pgsql.locks.share["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].share`</p> |
|PostgreSQL |DB {#DBNAME}: Num of total locks |<p>Number of total locks for each database</p> |DEPENDENT |pgsql.locks.total["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].total`</p> |
|PostgreSQL |DB {#DBNAME}: Queries max maintenance time |<p>Max maintenance query time</p> |DEPENDENT |pgsql.queries.mro.time_max["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].mro_time_max`</p> |
|PostgreSQL |DB {#DBNAME}: Queries max query time |<p>Max query time</p> |DEPENDENT |pgsql.queries.query.time_max["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].query_time_max`</p> |
|PostgreSQL |DB {#DBNAME}: Queries max transaction time |<p>Max transaction query time</p> |DEPENDENT |pgsql.queries.tx.time_max["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tx_time_max`</p> |
|PostgreSQL |DB {#DBNAME}: Queries slow maintenance count |<p>Slow maintenance query count</p> |DEPENDENT |pgsql.queries.mro.slow_count["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].mro_slow_count`</p> |
|PostgreSQL |DB {#DBNAME}: Queries slow query count |<p>Slow query count</p> |DEPENDENT |pgsql.queries.query.slow_count["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].query_slow_count`</p> |
|PostgreSQL |DB {#DBNAME}: Queries slow transaction count |<p>Slow transaction query count</p> |DEPENDENT |pgsql.queries.tx.slow_count["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tx_slow_count`</p> |
|PostgreSQL |DB {#DBNAME}: Queries sum maintenance time |<p>Sum maintenance query time</p> |DEPENDENT |pgsql.queries.mro.time_sum["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].mro_time_sum`</p> |
|PostgreSQL |DB {#DBNAME}: Queries sum query time |<p>Sum query time</p> |DEPENDENT |pgsql.queries.query.time_sum["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].query_time_sum`</p> |
|PostgreSQL |DB {#DBNAME}: Queries sum transaction time |<p>Sum transaction query time</p> |DEPENDENT |pgsql.queries.tx.time_sum["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tx_time_sum`</p> |
|Zabbix raw items |PostgreSQL: Get bgwriter |<p>https://www.postgresql.org/docs/12/monitoring-stats.html#PG-STAT-BGWRITER-VIEW</p> |ZABBIX_PASSIVE |pgsql.bgwriter["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|Zabbix raw items |PostgreSQL: Get archive |<p>Collect archive status metrics</p> |ZABBIX_PASSIVE |pgsql.archive["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|Zabbix raw items |PostgreSQL: Get dbstat |<p>Collect all metrics from pg_stat_database per database</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p> |ZABBIX_PASSIVE |pgsql.dbstat["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|Zabbix raw items |PostgreSQL: Get dbstat sum |<p>Collect all metrics from pg_stat_database per database</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p> |ZABBIX_PASSIVE |pgsql.dbstat.sum["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|Zabbix raw items |PostgreSQL: Get connections |<p>Collect all metrics from pg_stat_activity</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ACTIVITY-VIEW</p> |ZABBIX_PASSIVE |pgsql.connections["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|Zabbix raw items |PostgreSQL: Get WAL |<p>Collect WAL metrics</p> |ZABBIX_PASSIVE |pgsql.wal.stat["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|Zabbix raw items |PostgreSQL: Get locks |<p>Collect all metrics from pg_locks per database</p><p>https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-TABLES</p> |ZABBIX_PASSIVE |pgsql.locks["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|Zabbix raw items |PostgreSQL: Get replication |<p>Collect metrics from the pg_stat_replication, which contains information about the WAL sender process, showing statistics about replication to that sender's connected standby server.</p> |ZABBIX_PASSIVE |pgsql.replication.process["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"] |
|Zabbix raw items |PostgreSQL: Get queries |<p>Collect all metrics by query execution time</p> |ZABBIX_PASSIVE |pgsql.queries["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{$PG.QUERY_ETIME.MAX.WARN}"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Connections sum: Total number of connections is too high |<p>-</p> |`min(/PostgreSQL by Zabbix agent 2/pgsql.connections.total_pct,5m) > {$PG.CONN_TOTAL_PCT.MAX.WARN}` |AVERAGE | |
|PostgreSQL: Oldest xid is too big |<p>-</p> |`last(/PostgreSQL by Zabbix agent 2/pgsql.oldest.xid["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]) > 18000000` |AVERAGE | |
|PostgreSQL: Service has been restarted |<p>-</p> |`last(/PostgreSQL by Zabbix agent 2/pgsql.uptime["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]) < 600` |AVERAGE | |
|PostgreSQL: Service is down |<p>-</p> |`last(/PostgreSQL by Zabbix agent 2/pgsql.ping["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"])=0` |HIGH | |
|DB {#DBNAME}: Too many recovery conflicts |<p>The primary and standby servers are in many ways loosely connected. Actions on the primary will have an effect on the standby. As a result, there is potential for negative interactions or conflicts between them.</p><p>https://www.postgresql.org/docs/current/hot-standby.html#HOT-STANDBY-CONFLICT</p> |`min(/PostgreSQL by Zabbix agent 2/pgsql.dbstat.conflicts.rate["{#DBNAME}"],5m) > {$PG.CONFLICTS.MAX.WARN:"{#DBNAME}"}` |AVERAGE | |
|DB {#DBNAME}: Deadlock occurred |<p>-</p> |`min(/PostgreSQL by Zabbix agent 2/pgsql.dbstat.deadlocks.rate["{#DBNAME}"],5m) > {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}` |HIGH | |
|DB {#DBNAME}: Too many slow queries |<p>-</p> |`min(/PostgreSQL by Zabbix agent 2/pgsql.queries.query.slow_count["{#DBNAME}"],5m)>{$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384190-%C2%A0discussion-thread-for-official-zabbix-template-db-postgresql).

