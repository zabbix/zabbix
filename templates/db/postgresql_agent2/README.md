
# PostgreSQL by Zabbix agent 2

## Overview

This template is designed for the deployment of PostgreSQL monitoring by Zabbix via Zabbix agent 2 and uses a loadable plugin to run SQL queries.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- PostgreSQL 10-15

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Deploy Zabbix agent 2 with the PostgreSQL plugin. Starting with Zabbix versions 6.0.10 / 6.2.4 / 6.4 PostgreSQL metrics are moved to a loadable plugin and require installation of a separate package or [`compilation of the plugin from sources`](https://www.zabbix.com/documentation/7.0/manual/extensions/plugins/build).

2. Create the PostgreSQL user for monitoring (`<password>` at your discretion) and inherit permissions from the default role `pg_monitor`:

```sql
CREATE USER zbx_monitor WITH PASSWORD '<PASSWORD>' INHERIT;
GRANT pg_monitor TO zbx_monitor;
```

3. Edit the `pg_hba.conf` configuration file to allow connections for the user `zbx_monitor`. For example, you could add one of the following rows to allow local TCP connections from the same host:

```bash
# TYPE  DATABASE        USER            ADDRESS                 METHOD
  host       all        zbx_monitor     localhost               trust
  host       all        zbx_monitor     127.0.0.1/32            md5
  host       all        zbx_monitor     ::1/128                 scram-sha-256
```

For more information please read the PostgreSQL documentation `https://www.postgresql.org/docs/current/auth-pg-hba-conf.html`.

4. Set the connection string for the PostgreSQL instance in the `{$PG.CONNSTRING}` macro as URI, such as `<protocol(host:port)>`, or specify the named session - `<sessionname>`.

**Note:** if you want to use SSL/TLS encryption to protect communications with the remote PostgreSQL instance, a named session must be used. In that case, the instance URI should be specified in the `Plugins.PostgreSQL.Sessions.*.Uri` parameter in the PostgreSQL plugin configuration files alongside all the encryption parameters (type, cerfiticate/key filepaths if needed etc.).

You can check the [`PostgreSQL plugin documentation`](https://git.zabbix.com/projects/AP/repos/postgresql/browse?at=refs%2Fheads%2Frelease%2F7.0) for details about agent plugin parameters and named sessions.

Also, it is assumed that you set up the PostgreSQL instance to work in the desired encryption mode. Check the [`PostgreSQL documentation`](https://www.postgresql.org/docs/current/ssl-tcp.html) for details.

**Note:** plugin TLS certificate validation relies on checking the Subject Alternative Names (SAN) instead of the Common Name (CN), check the cryptography package [`documentation`](https://pkg.go.dev/crypto/x509) for details.

For example, to enable required encryption in transport mode without identity checks you could create the file `/etc/zabbix/zabbix_agent2.d/postgresql_myconn.conf` with the following configuration for the named session `myconn` (replace `<instanceip>` with the address of the PostgreSQL instance):

```bash
Plugins.PostgreSQL.Sessions.myconn.Uri=tcp://<instanceip>:5432
Plugins.PostgreSQL.Sessions.myconn.TLSConnect=required
```

Then set the `{$PG.CONNSTRING}` macro to `myconn` to use this named session.

5. Set the password that you specified in step 2 in the macro `{$PG.PASSWORD}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PG.PASSWORD}|<p>PostgreSQL user password.</p>|`<Put the password here>`|
|{$PG.CONNSTRING}|<p>URI or named session of the PostgreSQL instance.</p>|`tcp://localhost:5432`|
|{$PG.USER}|<p>PostgreSQL username.</p>|`zbx_monitor`|
|{$PG.LLD.FILTER.DBNAME}|<p>Filter of discoverable databases.</p>|`.+`|
|{$PG.CONN_TOTAL_PCT.MAX.WARN}|<p>Maximum percentage of current connections for trigger expression.</p>|`90`|
|{$PG.DATABASE}|<p>Default PostgreSQL database for the connection.</p>|`postgres`|
|{$PG.DEADLOCKS.MAX.WARN}|<p>Maximum number of detected deadlocks for trigger expression.</p>|`0`|
|{$PG.LLD.FILTER.APPLICATION}|<p>Filter of discoverable applications.</p>|`.+`|
|{$PG.CONFLICTS.MAX.WARN}|<p>Maximum number of recovery conflicts for trigger expression.</p>|`0`|
|{$PG.QUERY_ETIME.MAX.WARN}|<p>Execution time limit for count of slow queries.</p>|`30`|
|{$PG.SLOW_QUERIES.MAX.WARN}|<p>Slow queries count threshold for a trigger.</p>|`5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PostgreSQL: Get bgwriter|<p>Collect all metrics from pg_stat_bgwriter:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-BGWRITER-VIEW</p>|Zabbix agent|pgsql.bgwriter["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get archive|<p>Collect archive status metrics.</p>|Zabbix agent|pgsql.archive["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get dbstat|<p>Collect all metrics from pg_stat_database per database:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p>|Zabbix agent|pgsql.dbstat["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get dbstat sum|<p>Collect all metrics from pg_stat_database as sums for all databases:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p>|Zabbix agent|pgsql.dbstat.sum["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get connections sum|<p>Collect all metrics from pg_stat_activity:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ACTIVITY-VIEW</p>|Zabbix agent|pgsql.connections["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get WAL|<p>Collect write-ahead log (WAL) metrics.</p>|Zabbix agent|pgsql.wal.stat["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get locks|<p>Collect all metrics from pg_locks per database:</p><p>https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-TABLES</p>|Zabbix agent|pgsql.locks["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Custom queries|<p>Execute custom queries from file *.sql (check for option Plugins.Postgres.CustomQueriesPath at agent configuration).</p>|Zabbix agent|pgsql.custom.query["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}",""]|
|PostgreSQL: Get replication|<p>Collect metrics from the pg_stat_replication, which contains information about the WAL sender process, showing statistics about replication to that sender's connected standby server.</p>|Zabbix agent|pgsql.replication.process["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Get queries|<p>Collect all metrics by query execution time.</p>|Zabbix agent|pgsql.queries["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{$PG.QUERY_ETIME.MAX.WARN}"]|
|PostgreSQL: Version|<p>PostgreSQL version.</p>|Zabbix agent|pgsql.version["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|WAL: Bytes written|<p>WAL write, in bytes.</p>|Dependent item|pgsql.wal.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write`</p></li><li>Change per second</li></ul>|
|WAL: Bytes received|<p>WAL receive, in bytes.</p>|Dependent item|pgsql.wal.receive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.receive`</p></li><li>Change per second</li></ul>|
|WAL: Segments count|<p>Number of WAL segments.</p>|Dependent item|pgsql.wal.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count`</p></li></ul>|
|Bgwriter: Buffers allocated per second|<p>Number of buffers allocated per second.</p>|Dependent item|pgsql.bgwriter.buffers_alloc.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_alloc`</p></li><li>Change per second</li></ul>|
|Bgwriter: Buffers written directly by a backend per second|<p>Number of buffers written directly by a backend per second.</p>|Dependent item|pgsql.bgwriter.buffers_backend.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend`</p></li><li>Change per second</li></ul>|
|Bgwriter: Number of bgwriter cleaning scan stopped per second|<p>Number of times the background writer stopped a cleaning scan because it had written too many buffers per second.</p>|Dependent item|pgsql.bgwriter.maxwritten_clean.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxwritten_clean`</p></li><li>Change per second</li></ul>|
|Bgwriter: Times a backend executed its own fsync per second|<p>Number of times a backend had to execute its own fsync call per second (normally the background writer handles those even when the backend does its own write).</p>|Dependent item|pgsql.bgwriter.buffers_backend_fsync.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend_fsync`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers written by the background writer per second|<p>Number of buffers written by the background writer per second.</p>|Dependent item|pgsql.bgwriter.buffers_clean.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_clean`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers written during checkpoints per second|<p>Number of buffers written during checkpoints per second.</p>|Dependent item|pgsql.bgwriter.buffers_checkpoint.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_checkpoint`</p></li><li>Change per second</li></ul>|
|Checkpoint: Scheduled per second|<p>Number of scheduled checkpoints that have been performed per second.</p>|Dependent item|pgsql.bgwriter.checkpoints_timed.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_timed`</p></li><li>Change per second</li></ul>|
|Checkpoint: Requested per second|<p>Number of requested checkpoints that have been performed per second.</p>|Dependent item|pgsql.bgwriter.checkpoints_req.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_req`</p></li><li>Change per second</li></ul>|
|Checkpoint: Checkpoint write time per second|<p>Total amount of time per second that has been spent in the portion of checkpoint processing where files are written to disk.</p>|Dependent item|pgsql.bgwriter.checkpoint_write_time.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Checkpoint: Checkpoint sync time per second|<p>Total amount of time per second that has been spent in the portion of checkpoint processing where files are synchronized to disk.</p>|Dependent item|pgsql.bgwriter.checkpoint_sync_time.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_sync_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Archive: Count of archived files|<p>Count of archived files.</p>|Dependent item|pgsql.archive.count_archived_files<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.archived_count`</p></li></ul>|
|Archive: Count of failed attempts to archive files|<p>Count of failed attempts to archive files.</p>|Dependent item|pgsql.archive.failed_trying_to_archive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.failed_count`</p></li></ul>|
|Archive: Count of files in archive_status need to archive|<p>Count of files to archive.</p>|Dependent item|pgsql.archive.count_files_to_archive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count_files`</p></li></ul>|
|Archive: Size of files need to archive|<p>Size of files to archive.</p>|Dependent item|pgsql.archive.size_files_to_archive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.size_files`</p></li></ul>|
|Dbstat: Blocks read time|<p>Time spent reading data file blocks by backends.</p>|Dependent item|pgsql.dbstat.sum.blk_read_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_read_time`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Dbstat: Blocks write time|<p>Time spent writing data file blocks by backends.</p>|Dependent item|pgsql.dbstat.sum.blk_write_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Dbstat: Checksum failures per second|<p>Number of data page checksum failures per second detected (or on a shared object), or NULL if data checksums are not enabled. This metric is available since PostgreSQL 12.</p>|Dependent item|pgsql.dbstat.sum.checksum_failures.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checksum_failures`</p></li><li><p>Matches regular expression: `^\d*$`</p><p>⛔️Custom on fail: Set value to: `-2`</p></li><li><p>Change per second</p><p>⛔️Custom on fail: Set value to: `-1`</p></li></ul>|
|Dbstat: Committed transactions per second|<p>Number of transactions that have been committed per second.</p>|Dependent item|pgsql.dbstat.sum.xact_commit.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_commit`</p></li><li>Change per second</li></ul>|
|Dbstat: Conflicts per second|<p>Number of queries canceled per second due to conflicts with recovery (conflicts occur only on standby servers; see pg_stat_database_conflicts for details).</p>|Dependent item|pgsql.dbstat.sum.conflicts.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conflicts`</p></li><li>Change per second</li></ul>|
|Dbstat: Deadlocks per second|<p>Number of deadlocks detected per second.</p>|Dependent item|pgsql.dbstat.sum.deadlocks.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlocks`</p></li><li>Change per second</li></ul>|
|Dbstat: Disk blocks read per second|<p>Number of disk blocks read per second.</p>|Dependent item|pgsql.dbstat.sum.blks_read.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_read`</p></li><li>Change per second</li></ul>|
|Dbstat: Hit blocks read per second|<p>Number of times per second disk blocks were found already in the buffer cache</p>|Dependent item|pgsql.dbstat.sum.blks_hit.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_hit`</p></li><li>Change per second</li></ul>|
|Dbstat: Number temp bytes per second|<p>Total amount of data written per second to temporary files by queries.</p>|Dependent item|pgsql.dbstat.sum.temp_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_bytes`</p></li><li>Change per second</li></ul>|
|Dbstat: Number temp files per second|<p>Number of temporary files created by queries per second.</p>|Dependent item|pgsql.dbstat.sum.temp_files.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_files`</p></li><li>Change per second</li></ul>|
|Dbstat: Roll backed transactions per second|<p>Number of transactions that have been rolled back per second.</p>|Dependent item|pgsql.dbstat.sum.xact_rollback.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_rollback`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows deleted per second|<p>Number of rows deleted by queries per second.</p>|Dependent item|pgsql.dbstat.sum.tup_deleted.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_deleted`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows fetched per second|<p>Number of rows fetched by queries per second.</p>|Dependent item|pgsql.dbstat.sum.tup_fetched.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_fetched`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows inserted per second|<p>Number of rows inserted by queries per second.</p>|Dependent item|pgsql.dbstat.sum.tup_inserted.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_inserted`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows returned per second|<p>Number of rows returned by queries per second.</p>|Dependent item|pgsql.dbstat.sum.tup_returned.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_returned`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows updated per second|<p>Number of rows updated by queries per second.</p>|Dependent item|pgsql.dbstat.sum.tup_updated.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_updated`</p></li><li>Change per second</li></ul>|
|Dbstat: Backends connected|<p>Number of connected backends.</p>|Dependent item|pgsql.dbstat.sum.numbackends<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numbackends`</p></li></ul>|
|Connections sum: Active|<p>Total number of connections executing a query.</p>|Dependent item|pgsql.connections.sum.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p></li></ul>|
|Connections sum: Fastpath function call|<p>Total number of connections executing a fast-path function.</p>|Dependent item|pgsql.connections.sum.fastpath_function_call<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fastpath_function_call`</p></li></ul>|
|Connections sum: Idle|<p>Total number of connections waiting for a new client command.</p>|Dependent item|pgsql.connections.sum.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li></ul>|
|Connections sum: Idle in transaction|<p>Total number of connections in a transaction state but not executing a query.</p>|Dependent item|pgsql.connections.sum.idle_in_transaction<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction`</p></li></ul>|
|Connections sum: Prepared|<p>Total number of prepared transactions:</p><p>https://www.postgresql.org/docs/current/sql-prepare-transaction.html</p>|Dependent item|pgsql.connections.sum.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prepared`</p></li></ul>|
|Connections sum: Total|<p>Total number of connections.</p>|Dependent item|pgsql.connections.sum.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|Connections sum: Total, %|<p>Total number of connections, in percentage.</p>|Dependent item|pgsql.connections.sum.total_pct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_pct`</p></li></ul>|
|Connections sum: Waiting|<p>Total number of waiting connections:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#WAIT-EVENT-TABLE</p>|Dependent item|pgsql.connections.sum.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting`</p></li></ul>|
|Connections sum: Idle in transaction (aborted)|<p>Total number of connections in a transaction state but not executing a query, and where one of the statements in the transaction caused an error.</p>|Dependent item|pgsql.connections.sum.idle_in_transaction_aborted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction_aborted`</p></li></ul>|
|Connections sum: Disabled|<p>Total number of disabled connections.</p>|Dependent item|pgsql.connections.sum.disabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disabled`</p></li></ul>|
|PostgreSQL: Age of oldest xid|<p>Age of oldest xid.</p>|Zabbix agent|pgsql.oldest.xid["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Count of autovacuum workers|<p>Number of autovacuum workers.</p>|Zabbix agent|pgsql.autovacuum.count["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Cache hit ratio, %|<p>Cache hit ratio.</p>|Calculated|pgsql.cache.hit|
|PostgreSQL: Uptime|<p>Time since the server started.</p>|Zabbix agent|pgsql.uptime["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Replication: Lag in bytes|<p>Replication lag with master, in bytes.</p>|Zabbix agent|pgsql.replication.lag.b["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Replication: Lag in seconds|<p>Replication lag with master, in seconds.</p>|Zabbix agent|pgsql.replication.lag.sec["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Replication: Recovery role|<p>Replication role: 1 — recovery is still in progress (standby mode), 0 — master mode.</p>|Zabbix agent|pgsql.replication.recovery_role["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Replication: Standby count|<p>Number of standby servers.</p>|Zabbix agent|pgsql.replication.count["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Replication: Status|<p>Replication status: 0 — streaming is down, 1 — streaming is up, 2 — master mode.</p>|Zabbix agent|pgsql.replication.status["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|
|PostgreSQL: Ping|<p>Used to test a connection to see if it is alive. It is set to 0 if the query is unsuccessful.</p>|Zabbix agent|pgsql.ping["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Version has changed||`last(/PostgreSQL by Zabbix agent 2/pgsql.version["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"],#1)<>last(/PostgreSQL by Zabbix agent 2/pgsql.version["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"],#2) and length(last(/PostgreSQL by Zabbix agent 2/pgsql.version["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]))>0`|Info||
|Dbstat: Checksum failures detected|<p>Data page checksum failures were detected on that DB instance:<br>https://www.postgresql.org/docs/current/checksums.html</p>|`last(/PostgreSQL by Zabbix agent 2/pgsql.dbstat.sum.checksum_failures.rate)>0`|Average||
|PostgreSQL: Total number of connections is too high|<p>Total number of current connections exceeds the limit of {$PG.CONN_TOTAL_PCT.MAX.WARN}% out of the maximum number of concurrent connections to the database server (the "max_connections" setting).</p>|`min(/PostgreSQL by Zabbix agent 2/pgsql.connections.sum.total_pct,5m) > {$PG.CONN_TOTAL_PCT.MAX.WARN}`|Average||
|PostgreSQL: Oldest xid is too big||`last(/PostgreSQL by Zabbix agent 2/pgsql.oldest.xid["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]) > 18000000`|Average||
|PostgreSQL: Service has been restarted|<p>PostgreSQL uptime is less than 10 minutes.</p>|`last(/PostgreSQL by Zabbix agent 2/pgsql.uptime["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]) < 10m`|Average||
|PostgreSQL: Service is down|<p>Last test of a connection was unsuccessful.</p>|`last(/PostgreSQL by Zabbix agent 2/pgsql.ping["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"])=0`|High||

### LLD rule Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication discovery|<p>Discovers replication lag metrics.</p>|Zabbix agent|pgsql.replication.process.discovery["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|

### Item prototypes for Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Application [{#APPLICATION_NAME}]: Get replication|<p>Collect metrics from the "pg_stat_replication" about the application "{#APPLICATION_NAME}" that is connected to this WAL sender, which contains information about the WAL sender process, showing statistics about replication to that sender's connected standby server.</p>|Dependent item|pgsql.replication.get_metrics["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#APPLICATION_NAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Application [{#APPLICATION_NAME}]: Replication flush lag||Dependent item|pgsql.replication.process.flush_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.flush_lag`</p></li></ul>|
|Application [{#APPLICATION_NAME}]: Replication replay lag||Dependent item|pgsql.replication.process.replay_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replay_lag`</p></li></ul>|
|Application [{#APPLICATION_NAME}]: Replication write lag||Dependent item|pgsql.replication.process.write_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write_lag`</p></li></ul>|

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Discovers databases (DB) in the database management system (DBMS), except:</p><p>- templates;</p><p>- DBs that do not allow connections.</p>|Zabbix agent|pgsql.db.discovery["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}"]|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DB [{#DBNAME}]: Get dbstat|<p>Get dbstat metrics for database "{#DBNAME}".</p>|Dependent item|pgsql.dbstat.get_metrics["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Get locks|<p>Get locks metrics for database "{#DBNAME}".</p>|Dependent item|pgsql.locks.get_metrics["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Get queries|<p>Get queries metrics for database "{#DBNAME}".</p>|Dependent item|pgsql.queries.get_metrics["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Database age|<p>Database age.</p>|Zabbix agent|pgsql.db.age["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|
|DB [{#DBNAME}]: Bloating tables|<p>Number of bloating tables.</p>|Zabbix agent|pgsql.db.bloating_tables["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|
|DB [{#DBNAME}]: Database size|<p>Database size.</p>|Zabbix agent|pgsql.db.size["{$PG.CONNSTRING}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|
|DB [{#DBNAME}]: Blocks hit per second|<p>Total number of times per second disk blocks were found already in the buffer cache, so that a read was not necessary.</p>|Dependent item|pgsql.dbstat.blks_hit.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_hit`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Disk blocks read per second|<p>Total number of disk blocks read per second in this database.</p>|Dependent item|pgsql.dbstat.blks_read.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_read`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Detected conflicts per second|<p>Total number of queries canceled due to conflicts with recovery in this database per second.</p>|Dependent item|pgsql.dbstat.conflicts.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conflicts`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Detected deadlocks per second|<p>Total number of detected deadlocks in this database per second.</p>|Dependent item|pgsql.dbstat.deadlocks.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlocks`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Temp_bytes written per second|<p>Total amount of data written to temporary files by queries in this database.</p>|Dependent item|pgsql.dbstat.temp_bytes.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_bytes`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Temp_files created per second|<p>Total number of temporary files created by queries in this database.</p>|Dependent item|pgsql.dbstat.temp_files.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_files`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples deleted per second|<p>Total number of rows deleted by queries in this database per second.</p>|Dependent item|pgsql.dbstat.tup_deleted.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_deleted`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples fetched per second|<p>Total number of rows fetched by queries in this database per second.</p>|Dependent item|pgsql.dbstat.tup_fetched.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_fetched`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples inserted per second|<p>Total number of rows inserted by queries in this database per second.</p>|Dependent item|pgsql.dbstat.tup_inserted.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_inserted`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples returned per second|<p>Number of rows returned by queries in this database per second.</p>|Dependent item|pgsql.dbstat.tup_returned.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_returned`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples updated per second|<p>Total number of rows updated by queries in this database per second.</p>|Dependent item|pgsql.dbstat.tup_updated.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_updated`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Commits per second|<p>Number of transactions in this database that have been committed per second.</p>|Dependent item|pgsql.dbstat.xact_commit.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_commit`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Rollbacks per second|<p>Total number of transactions in this database that have been rolled back.</p>|Dependent item|pgsql.dbstat.xact_rollback.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_rollback`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Backends connected|<p>Number of backends currently connected to this database.</p>|Dependent item|pgsql.dbstat.numbackends["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numbackends`</p></li></ul>|
|DB [{#DBNAME}]: Checksum failures|<p>Number of data page checksum failures detected in this database.</p>|Dependent item|pgsql.dbstat.checksum_failures.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checksum_failures`</p></li><li><p>Matches regular expression: `^\d*$`</p><p>⛔️Custom on fail: Set value to: `-2`</p></li><li><p>Change per second</p><p>⛔️Custom on fail: Set value to: `-1`</p></li></ul>|
|DB [{#DBNAME}]: Disk blocks read time per second|<p>Time spent reading data file blocks by backends per second.</p>|Dependent item|pgsql.dbstat.blk_read_time.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_read_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Disk blocks write time|<p>Time spent writing data file blocks by backends per second.</p>|Dependent item|pgsql.dbstat.blk_write_time.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Num of accessexclusive locks|<p>Number of accessexclusive locks for this database.</p>|Dependent item|pgsql.locks.accessexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.accessexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of accessshare locks|<p>Number of accessshare locks for this database.</p>|Dependent item|pgsql.locks.accessshare["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.accessshare`</p></li></ul>|
|DB [{#DBNAME}]: Num of exclusive locks|<p>Number of exclusive locks for this database.</p>|Dependent item|pgsql.locks.exclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.exclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of rowexclusive locks|<p>Number of rowexclusive locks for this database.</p>|Dependent item|pgsql.locks.rowexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rowexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of rowshare locks|<p>Number of rowshare locks for this database.</p>|Dependent item|pgsql.locks.rowshare["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rowshare`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Num of sharerowexclusive locks|<p>Number of total sharerowexclusive for this database.</p>|Dependent item|pgsql.locks.sharerowexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sharerowexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of shareupdateexclusive locks|<p>Number of shareupdateexclusive locks for this database.</p>|Dependent item|pgsql.locks.shareupdateexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.shareupdateexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of share locks|<p>Number of share locks for this database.</p>|Dependent item|pgsql.locks.share["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.share`</p></li></ul>|
|DB [{#DBNAME}]: Num of locks total|<p>Total number of locks in this database.</p>|Dependent item|pgsql.locks.total["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|DB [{#DBNAME}]: Queries max maintenance time|<p>Max maintenance query time for this database.</p>|Dependent item|pgsql.queries.mro.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries max query time|<p>Max query time for this database.</p>|Dependent item|pgsql.queries.query.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries max transaction time|<p>Max transaction query time for this database.</p>|Dependent item|pgsql.queries.tx.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow maintenance count|<p>Slow maintenance query count for this database.</p>|Dependent item|pgsql.queries.mro.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow query count|<p>Slow query count for this database.</p>|Dependent item|pgsql.queries.query.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow transaction count|<p>Slow transaction query count for this database.</p>|Dependent item|pgsql.queries.tx.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum maintenance time|<p>Sum maintenance query time for this database.</p>|Dependent item|pgsql.queries.mro.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum query time|<p>Sum query time for this database.</p>|Dependent item|pgsql.queries.query.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum transaction time|<p>Sum transaction query time for this database.</p>|Dependent item|pgsql.queries.tx.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_sum`</p></li></ul>|

### Trigger prototypes for Database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|DB [{#DBNAME}]: Too many recovery conflicts|<p>The primary and standby servers are in many ways loosely connected. Actions on the primary will have an effect on the standby. As a result, there is potential for negative interactions or conflicts between them.<br>https://www.postgresql.org/docs/current/hot-standby.html#HOT-STANDBY-CONFLICT</p>|`min(/PostgreSQL by Zabbix agent 2/pgsql.dbstat.conflicts.rate["{#DBNAME}"],5m) > {$PG.CONFLICTS.MAX.WARN:"{#DBNAME}"}`|Average||
|DB [{#DBNAME}]: Deadlock occurred|<p>Number of deadlocks detected per second exceeds {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"} for 5m.</p>|`min(/PostgreSQL by Zabbix agent 2/pgsql.dbstat.deadlocks.rate["{#DBNAME}"],5m) > {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}`|High||
|DB [{#DBNAME}]: Checksum failures detected|<p>Data page checksum failures were detected on that database:<br>https://www.postgresql.org/docs/current/checksums.html</p>|`last(/PostgreSQL by Zabbix agent 2/pgsql.dbstat.checksum_failures.rate["{#DBNAME}"])>0`|Average||
|DB [{#DBNAME}]: Too many slow queries|<p>The number of detected slow queries exceeds the limit of {$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}.</p>|`min(/PostgreSQL by Zabbix agent 2/pgsql.queries.query.slow_count["{#DBNAME}"],5m)>{$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

