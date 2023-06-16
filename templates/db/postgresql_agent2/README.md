
# PostgreSQL by Zabbix agent 2

## Overview

This template is designed for the effortless deployment of PostgreSQL monitoring by Zabbix via Zabbix agent 2 and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- PostgreSQL 10-15

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Deploy Zabbix agent2 with Postgres plugin. Starting with Zabbix versions 6.0.10 / 6.2.4 / 6.4 postgres metrics moved to a loadable plugin and requires separate package installation or [compilation of a plugin from sources](https://www.zabbix.com/documentation/6.0/manual/extensions/plugins/build).

2. Create PostgreSQL user to monitor (`<password>` at your discretion) and inherit permissions from the default role `pg_monitor`:

```bash
CREATE USER zbx_monitor WITH PASSWORD '<PASSWORD>' INHERIT;
GRANT pg_monitor TO zbx_monitor;
```

3. Edit `pg_hba.conf` to allow connections from Zabbix agent:
  
```bash
# TYPE  DATABASE        USER            ADDRESS                 METHOD
  host       all        zbx_monitor     localhost               md5
```

For more information please read the PostgreSQL documentation https://www.postgresql.org/docs/current/auth-pg-hba-conf.html.

4. Set in the `{$PG.URI}` macro the system data source name of the PostgreSQL instance such as `<protocol(host:port)>`.

5. Set the user name and password in host macros (`{$PG.USER}` and `{$PG.PASSWORD}`) if you want to override parameters from the Zabbix agent configuration file.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PG.PASSWORD}||`postgres`|
|{$PG.URI}||`tcp://localhost:5432`|
|{$PG.USER}||`postgres`|
|{$PG.LLD.FILTER.DBNAME}||`(.+)`|
|{$PG.CONN_TOTAL_PCT.MAX.WARN}||`90`|
|{$PG.DATABASE}||`postgres`|
|{$PG.DEADLOCKS.MAX.WARN}||`0`|
|{$PG.LLD.FILTER.APPLICATION}||`(.+)`|
|{$PG.CONFLICTS.MAX.WARN}||`0`|
|{$PG.QUERY_ETIME.MAX.WARN}|<p>Execution time limit for count of slow queries.</p>|`30`|
|{$PG.SLOW_QUERIES.MAX.WARN}|<p>Slow queries count threshold for a trigger.</p>|`5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PostgreSQL: Get bgwriter|<p>https://www.postgresql.org/docs/12/monitoring-stats.html#PG-STAT-BGWRITER-VIEW</p>|Zabbix agent|pgsql.bgwriter["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get archive|<p>Collect archive status metrics</p>|Zabbix agent|pgsql.archive["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get dbstat|<p>Collect all metrics from pg_stat_database per database</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p>|Zabbix agent|pgsql.dbstat["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get dbstat sum|<p>Collect all metrics from pg_stat_database per database</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p>|Zabbix agent|pgsql.dbstat.sum["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get connections|<p>Collect all metrics from pg_stat_activity</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ACTIVITY-VIEW</p>|Zabbix agent|pgsql.connections["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get WAL|<p>Collect WAL metrics</p>|Zabbix agent|pgsql.wal.stat["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get locks|<p>Collect all metrics from pg_locks per database</p><p>https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-TABLES</p>|Zabbix agent|pgsql.locks["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Custom queries|<p>Execute custom queries from file *.sql (check for option Plugins.Postgres.CustomQueriesPath at agent configuration)</p>|Zabbix agent|pgsql.custom.query["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}",""]|
|PostgreSQL: Get replication|<p>Collect metrics from the pg_stat_replication, which contains information about the WAL sender process, showing statistics about replication to that sender's connected standby server.</p>|Zabbix agent|pgsql.replication.process["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get queries|<p>Collect all metrics by query execution time</p>|Zabbix agent|pgsql.queries["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{$PG.QUERY_ETIME.MAX.WARN}"]|
|WAL: Bytes written|<p>WAL write in bytes</p>|Dependent item|pgsql.wal.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write`</p></li><li>Change per second</li></ul>|
|WAL: Bytes received|<p>WAL receive in bytes</p>|Dependent item|pgsql.wal.receive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.receive`</p></li><li>Change per second</li></ul>|
|WAL: Segments count|<p>Number of WAL segments</p>|Dependent item|pgsql.wal.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count`</p></li></ul>|
|Bgwriter: Buffers allocated|<p>Number of buffers allocated</p>|Dependent item|pgsql.bgwriter.buffers_alloc.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_alloc`</p></li><li>Change per second</li></ul>|
|Bgwriter: Buffers written directly by a backend|<p>Number of buffers written directly by a backend</p>|Dependent item|pgsql.bgwriter.buffers_backend.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend`</p></li><li>Change per second</li></ul>|
|Bgwriter: Number of bgwriter stopped|<p>Number of times the background writer stopped a cleaning scan because it had written too many buffers</p>|Dependent item|pgsql.bgwriter.maxwritten_clean.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxwritten_clean`</p></li><li>Change per second</li></ul>|
|Bgwriter: Times a backend execute its own fsync|<p>Number of times a backend had to execute its own fsync call (normally the background writer handles those even when the backend does its own write)</p>|Dependent item|pgsql.bgwriter.buffers_backend_fsync.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend_fsync`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers background written|<p>Number of buffers written by the background writer</p>|Dependent item|pgsql.bgwriter.buffers_clean.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_clean`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers checkpoints written|<p>Number of buffers written during checkpoints</p>|Dependent item|pgsql.bgwriter.buffers_checkpoint.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_checkpoint`</p></li><li>Change per second</li></ul>|
|Checkpoint: By timeout|<p>Number of scheduled checkpoints that have been performed</p>|Dependent item|pgsql.bgwriter.checkpoints_timed.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_timed`</p></li><li>Change per second</li></ul>|
|Checkpoint: Requested|<p>Number of requested checkpoints that have been performed</p>|Dependent item|pgsql.bgwriter.checkpoints_req.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_req`</p></li><li>Change per second</li></ul>|
|Checkpoint: Checkpoint write time|<p>Total amount of time that has been spent in the portion of checkpoint processing where files are written to disk, in milliseconds</p>|Dependent item|pgsql.bgwriter.checkpoint_write_time.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Checkpoint: Checkpoint sync time|<p>Total amount of time that has been spent in the portion of checkpoint processing where files are synchronized to disk</p>|Dependent item|pgsql.bgwriter.checkpoint_sync_time.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_sync_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Archive: Count of archive files|<p>Collect all metrics from pg_stat_activity</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ARCHIVER-VIEW</p>|Dependent item|pgsql.archive.count_archived_files<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.archived_count`</p></li></ul>|
|Archive: Count of attempts to archive files|<p>Collect all metrics from pg_stat_activity</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ARCHIVER-VIEW</p>|Dependent item|pgsql.archive.failed_trying_to_archive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.failed_count`</p></li></ul>|
|Archive: Count of files in archive_status need to archive||Dependent item|pgsql.archive.count_files_to_archive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count_files`</p></li></ul>|
|Archive: Count of files need to archive|<p>Size of files to archive</p>|Dependent item|pgsql.archive.size_files_to_archive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.size_files`</p></li></ul>|
|Dbstat: Blocks read time|<p>Time spent reading data file blocks by backends, in milliseconds</p>|Dependent item|pgsql.dbstat.sum.blk_read_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_read_time`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Dbstat: Blocks write time|<p>Time spent writing data file blocks by backends, in milliseconds</p>|Dependent item|pgsql.dbstat.sum.blk_write_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Dbstat: Checksum failures|<p>Number of data page checksum failures detected (or on a shared object), or NULL if data checksums are not enabled. This metric included in PostgreSQL 12</p>|Dependent item|pgsql.dbstat.sum.checksum_failures.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checksum_failures`</p></li><li><p>Matches regular expression: `^\d*$`</p><p>⛔️Custom on fail: Set value to: `-2`</p></li><li><p>Change per second</p><p>⛔️Custom on fail: Set value to: `-1`</p></li></ul>|
|Dbstat: Committed transactions|<p>Number of transactions that have been committed</p>|Dependent item|pgsql.dbstat.sum.xact_commit.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_commit`</p></li><li>Change per second</li></ul>|
|Dbstat: Conflicts|<p>Number of queries canceled due to conflicts with recovery.  (Conflicts occur only on standby servers; see pg_stat_database_conflicts for details.)</p>|Dependent item|pgsql.dbstat.sum.conflicts.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conflicts`</p></li><li>Change per second</li></ul>|
|Dbstat: Deadlocks|<p>Number of deadlocks detected</p>|Dependent item|pgsql.dbstat.sum.deadlocks.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlocks`</p></li><li>Change per second</li></ul>|
|Dbstat: Disk blocks read|<p>Number of disk blocks read</p>|Dependent item|pgsql.dbstat.sum.blks_read.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_read`</p></li><li>Change per second</li></ul>|
|Dbstat: Hit blocks read|<p>Number of times disk blocks were found already in the buffer cache</p>|Dependent item|pgsql.dbstat.sum.blks_hit.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_hit`</p></li><li>Change per second</li></ul>|
|Dbstat: Number temp bytes|<p>Total amount of data written to temporary files by queries</p>|Dependent item|pgsql.dbstat.sum.temp_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_bytes`</p></li><li>Change per second</li></ul>|
|Dbstat: Number temp bytes|<p>Number of temporary files created by queries</p>|Dependent item|pgsql.dbstat.sum.temp_files.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_files`</p></li><li>Change per second</li></ul>|
|Dbstat: Roll backed transactions|<p>Number of transactions that have been rolled back</p>|Dependent item|pgsql.dbstat.sum.xact_rollback.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_rollback`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows deleted|<p>Number of rows deleted by queries</p>|Dependent item|pgsql.dbstat.sum.tup_deleted.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_deleted`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows fetched|<p>Number of rows fetched by queries</p>|Dependent item|pgsql.dbstat.sum.tup_fetched.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_fetched`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows inserted|<p>Number of rows inserted by queries</p>|Dependent item|pgsql.dbstat.sum.tup_inserted.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_inserted`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows returned|<p>Number of rows returned by queries</p>|Dependent item|pgsql.dbstat.sum.tup_returned.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_returned`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows updated|<p>Number of rows updated by queries</p>|Dependent item|pgsql.dbstat.sum.tup_updated.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_updated`</p></li><li>Change per second</li></ul>|
|Dbstat: Backends connected|<p>Number of connected backends</p>|Dependent item|pgsql.dbstat.sum.numbackends<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numbackends`</p></li></ul>|
|Connections sum: Active|<p>Total number of connections executing a query</p>|Dependent item|pgsql.connections.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p></li></ul>|
|Connections sum: Fastpath function call|<p>Total number of connections executing a fast-path function</p>|Dependent item|pgsql.connections.fastpath_function_call<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction`</p></li></ul>|
|Connections sum: Idle|<p>Total number of connections waiting for a new client command</p>|Dependent item|pgsql.connections.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li></ul>|
|Connections sum: Idle in transaction|<p>Total number of connections in a transaction state, but not executing a query</p>|Dependent item|pgsql.connections.idle_in_transaction<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction`</p></li></ul>|
|Connections sum: Prepared|<p>Total number of prepared transactions</p><p>https://www.postgresql.org/docs/current/sql-prepare-transaction.html</p>|Dependent item|pgsql.connections.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prepared`</p></li></ul>|
|Connections sum: Total|<p>Total number of connections</p>|Dependent item|pgsql.connections.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|Connections sum: Total %|<p>Total number of connections in percentage</p>|Dependent item|pgsql.connections.total_pct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_pct`</p></li></ul>|
|Connections sum: Waiting|<p>Total number of waiting connections</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#WAIT-EVENT-TABLE</p>|Dependent item|pgsql.connections.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting`</p></li></ul>|
|Connections sum: Idle in transaction (aborted)|<p>Total number of connections in a transaction state, but not executing a query and one of the statements in the transaction caused an error.</p>|Dependent item|pgsql.connections.idle_in_transaction_aborted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction_aborted`</p></li></ul>|
|Connections sum: Disabled|<p>Total number of disabled connections</p>|Dependent item|pgsql.connections.disabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disabled`</p></li></ul>|
|PostgreSQL: Age of oldest xid|<p>Age of oldest xid.</p>|Zabbix agent|pgsql.oldest.xid["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|Autovacuum: Count of autovacuum workers|<p>Number of autovacuum workers.</p>|Zabbix agent|pgsql.autovacuum.count["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Cache hit||Calculated|pgsql.cache.hit["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Uptime||Zabbix agent|pgsql.uptime["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|Replication: Lag in bytes|<p>Replication lag with Master in byte.</p>|Zabbix agent|pgsql.replication.lag.b["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|Replication: Lag in seconds|<p>Replication lag with Master in seconds.</p>|Zabbix agent|pgsql.replication.lag.sec["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|Replication: Recovery role|<p>Replication role: 1 — recovery is still in progress (standby mode), 0 — master mode.</p>|Zabbix agent|pgsql.replication.recovery_role["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|Replication: Standby count|<p>Number of standby servers</p>|Zabbix agent|pgsql.replication.count["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|Replication: Status|<p>Replication status: 0 — streaming is down, 1 — streaming is up, 2 — master mode</p>|Zabbix agent|pgsql.replication.status["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Ping||Zabbix agent|pgsql.ping["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dbstat: Checksum failures detected|<p>Data page checksum failures were detected on that DB instance.https://www.postgresql.org/docs/current/checksums.html</p>|`last(/PostgreSQL by Zabbix agent 2/pgsql.dbstat.sum.checksum_failures.rate)>0`|Average||
|Connections sum: Total number of connections is too high||`min(/PostgreSQL by Zabbix agent 2/pgsql.connections.total_pct,5m) > {$PG.CONN_TOTAL_PCT.MAX.WARN}`|Average||
|PostgreSQL: Oldest xid is too big||`last(/PostgreSQL by Zabbix agent 2/pgsql.oldest.xid["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]) > 18000000`|Average||
|PostgreSQL: Service has been restarted||`last(/PostgreSQL by Zabbix agent 2/pgsql.uptime["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]) < 600`|Average||
|PostgreSQL: Service is down||`last(/PostgreSQL by Zabbix agent 2/pgsql.ping["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"])=0`|High||

### LLD rule Replication Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication Discovery||Zabbix agent|pgsql.replication.process.discovery["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|

### Item prototypes for Replication Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Application [{#APPLICATION_NAME}]: Get replication||Dependent item|pgsql.replication.get_metrics["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#APPLICATION_NAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Application [{#APPLICATION_NAME}]: Replication flush lag||Dependent item|pgsql.replication.process.flush_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.flush_lag`</p></li></ul>|
|Application [{#APPLICATION_NAME}]: Replication replay lag||Dependent item|pgsql.replication.process.replay_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replay_lag`</p></li></ul>|
|Application [{#APPLICATION_NAME}]: Replication write lag||Dependent item|pgsql.replication.process.write_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write_lag`</p></li></ul>|

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery||Zabbix agent|pgsql.db.discovery["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}"]|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DB [{#DBNAME}]: Get dbstat|<p>Get dbstat metrics for {#DBNAME}</p>|Dependent item|pgsql.dbstat.get_metrics["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Get locks|<p>Get locks metrics for {#DBNAME}</p>|Dependent item|pgsql.locks.get_metrics["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Get queries|<p>Get locks metrics for {#DBNAME}</p>|Dependent item|pgsql.queries.get_metrics["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Database age|<p>Database age</p>|Zabbix agent|pgsql.db.age["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|
|DB [{#DBNAME}]: Bloating tables|<p>Number of bloating tables</p>|Zabbix agent|pgsql.db.bloating_tables["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|
|DB [{#DBNAME}]: Database size|<p>Database size</p>|Zabbix agent|pgsql.db.size["{$PG.URI}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|
|DB [{#DBNAME}]: Blocks hit per second|<p>Total number of times disk blocks were found already in the buffer cache, so that a read was not necessary</p>|Dependent item|pgsql.dbstat.blks_hit.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_hit`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Disk blocks read per second|<p>Total number of disk blocks read in this database</p>|Dependent item|pgsql.dbstat.blks_read.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_read`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Detected conflicts per second|<p>Total number of queries canceled due to conflicts with recovery in this database</p>|Dependent item|pgsql.dbstat.conflicts.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conflicts`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Detected deadlocks per second|<p>Total number of detected deadlocks in this database</p>|Dependent item|pgsql.dbstat.deadlocks.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlocks`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Temp_bytes written per second|<p>Total amount of data written to temporary files by queries in this database</p>|Dependent item|pgsql.dbstat.temp_bytes.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_bytes`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Temp_files created per second|<p>Total number of temporary files created by queries in this database</p>|Dependent item|pgsql.dbstat.temp_files.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_files`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples deleted per second|<p>Total number of rows deleted by queries in this database</p>|Dependent item|pgsql.dbstat.tup_deleted.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_deleted`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples fetched per second|<p>Total number of rows fetched by queries in this database</p>|Dependent item|pgsql.dbstat.tup_fetched.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_fetched`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples inserted per second|<p>Total number of rows inserted by queries in this database</p>|Dependent item|pgsql.dbstat.tup_inserted.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_inserted`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples returned per second|<p>Number of rows returned by queries in this database</p>|Dependent item|pgsql.dbstat.tup_returned.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_returned`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples updated per second|<p>Total number of rows updated by queries in this database</p>|Dependent item|pgsql.dbstat.tup_updated.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_updated`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Commits per second|<p>Number of transactions in this database that have been committed</p>|Dependent item|pgsql.dbstat.xact_commit.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_commit`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Rollbacks per second|<p>Total number of transactions in this database that have been rolled back</p>|Dependent item|pgsql.dbstat.xact_rollback.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_rollback`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Backends connected|<p>Number of backends currently connected to this database</p>|Dependent item|pgsql.dbstat.numbackends["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numbackends`</p></li></ul>|
|DB [{#DBNAME}]: Checksum failures|<p>Number of data page checksum failures detected in this database</p>|Dependent item|pgsql.dbstat.checksum_failures.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checksum_failures`</p></li><li><p>Matches regular expression: `^\d*$`</p><p>⛔️Custom on fail: Set value to: `-2`</p></li><li><p>Change per second</p><p>⛔️Custom on fail: Set value to: `-1`</p></li></ul>|
|DB [{#DBNAME}]: Disk blocks read time|<p>Time spent reading data file blocks by backends, in milliseconds</p>|Dependent item|pgsql.dbstat.blk_read_time.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_read_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Disk blocks write time|<p>Time spent writing data file blocks by backends, in milliseconds</p>|Dependent item|pgsql.dbstat.blk_write_time.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Num of accessexclusive locks|<p>Number of accessexclusive locks for each database</p>|Dependent item|pgsql.locks.accessexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.accessexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of accessshare locks|<p>Number of accessshare locks for each database</p>|Dependent item|pgsql.locks.accessshare["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.accessshare`</p></li></ul>|
|DB [{#DBNAME}]: Num of exclusive locks|<p>Number of exclusive locks for each database</p>|Dependent item|pgsql.locks.exclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.exclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of rowexclusive locks|<p>Number of rowexclusive locks for each database</p>|Dependent item|pgsql.locks.rowexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rowexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of rowshare locks|<p>Number of rowshare locks for each database</p>|Dependent item|pgsql.locks.rowshare["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}'].rowshare`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Num of sharerowexclusive locks|<p>Number of total sharerowexclusive for each database</p>|Dependent item|pgsql.locks.sharerowexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sharerowexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of shareupdateexclusive locks|<p>Number of shareupdateexclusive locks for each database</p>|Dependent item|pgsql.locks.shareupdateexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.shareupdateexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of share locks|<p>Number of share locks for each database</p>|Dependent item|pgsql.locks.share["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.share`</p></li></ul>|
|DB [{#DBNAME}]: Num of total locks|<p>Number of total locks for each database</p>|Dependent item|pgsql.locks.total["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|DB [{#DBNAME}]: Queries max maintenance time|<p>Max maintenance query time</p>|Dependent item|pgsql.queries.mro.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries max query time|<p>Max query time</p>|Dependent item|pgsql.queries.query.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries max transaction time|<p>Max transaction query time</p>|Dependent item|pgsql.queries.tx.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow maintenance count|<p>Slow maintenance query count</p>|Dependent item|pgsql.queries.mro.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow query count|<p>Slow query count</p>|Dependent item|pgsql.queries.query.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow transaction count|<p>Slow transaction query count</p>|Dependent item|pgsql.queries.tx.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum maintenance time|<p>Sum maintenance query time</p>|Dependent item|pgsql.queries.mro.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum query time|<p>Sum query time</p>|Dependent item|pgsql.queries.query.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum transaction time|<p>Sum transaction query time</p>|Dependent item|pgsql.queries.tx.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_sum`</p></li></ul>|

### Trigger prototypes for Database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|DB [{#DBNAME}]: Too many recovery conflicts|<p>The primary and standby servers are in many ways loosely connected. Actions on the primary will have an effect on the standby. As a result, there is potential for negative interactions or conflicts between them.https://www.postgresql.org/docs/current/hot-standby.html#HOT-STANDBY-CONFLICT</p>|`min(/PostgreSQL by Zabbix agent 2/pgsql.dbstat.conflicts.rate["{#DBNAME}"],5m) > {$PG.CONFLICTS.MAX.WARN:"{#DBNAME}"}`|Average||
|DB [{#DBNAME}]: Deadlock occurred||`min(/PostgreSQL by Zabbix agent 2/pgsql.dbstat.deadlocks.rate["{#DBNAME}"],5m) > {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}`|High||
|DB [{#DBNAME}]: Checksum failures detected|<p>Data page checksum failures were detected on that database.https://www.postgresql.org/docs/current/checksums.html</p>|`last(/PostgreSQL by Zabbix agent 2/pgsql.dbstat.checksum_failures.rate["{#DBNAME}"])>0`|Average||
|DB [{#DBNAME}]: Too many slow queries||`min(/PostgreSQL by Zabbix agent 2/pgsql.queries.query.slow_count["{#DBNAME}"],5m)>{$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

