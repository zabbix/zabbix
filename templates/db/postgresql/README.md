
# PostgreSQL by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  
Templates to monitor PostgreSQL by Zabbix.
This template was tested on PostgreSQL versions 9.6, 10 and 11 on Linux and Windows.


## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

1. Install Zabbix agent and create a read-only `zbx_monitor` user with proper access to your PostgreSQL server.

    For PostgreSQL version 10 and above:

    ```sql
    CREATE USER zbx_monitor WITH PASSWORD '<PASSWORD>' INHERIT;
    GRANT pg_monitor TO zbx_monitor;
    ```

    For PostgreSQL version 9.6 and below:

    ```sql
    CREATE USER zbx_monitor WITH PASSWORD '<PASSWORD>';
    GRANT SELECT ON pg_stat_database TO zbx_monitor;

    -- To collect WAL metrics, the user must have a `superuser` role.
    ALTER USER zbx_monitor WITH SUPERUSER;
    ```

2. Copy `postgresql/` to Zabbix agent home directory `/var/lib/zabbix/`. The `postgresql/` directory contains the files needed to obtain metrics from PostgreSQL.

3. Copy `template_db_postgresql.conf` to Zabbix agent configuration directory `/etc/zabbix/zabbix_agentd.d/` and restart Zabbix agent service.

4. Edit `pg_hba.conf` to allow connections from Zabbix agent https://www.postgresql.org/docs/current/auth-pg-hba-conf.html.

    Add rows (for example):

    ```bash
    host all zbx_monitor 127.0.0.1/32 trust
    host all zbx_monitor 0.0.0.0/0 md5
    host all zbx_monitor ::0/0 md5
    ```

5. Import template file to Zabbix and link it to the target host

6. Set {$PG.HOST}, {$PG.PORT}, {$PG.USER}, {$PG.PASSWORD} and {$PG.DB} macros values.

## Zabbix configuration

If PostgreSQL is installed from the `PGDG` repository, then add the path to `pg_isready` to the `PATH` environment variable for `zabbix` user.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PG.CACHE_HITRATIO.MIN.WARN} |<p>-</p> |`90` |
|{$PG.CHECKPOINTS_REQ.MAX.WARN} |<p>-</p> |`5` |
|{$PG.CONFLICTS.MAX.WARN} |<p>-</p> |`0` |
|{$PG.CONN_IDLE_IN_TRANS.MAX.WARN} |<p>-</p> |`5` |
|{$PG.CONN_TOTAL_PCT.MAX.WARN} |<p>-</p> |`90` |
|{$PG.CONN_WAIT.MAX.WARN} |<p>-</p> |`0` |
|{$PG.DB} |<p>-</p> |`postgres` |
|{$PG.DEADLOCKS.MAX.WARN} |<p>-</p> |`0` |
|{$PG.FROZENXID_PCT_STOP.MIN.HIGH} |<p>-</p> |`75` |
|{$PG.HOST} |<p>-</p> |`127.0.0.1` |
|{$PG.LLD.FILTER.DBNAME} |<p>-</p> |`(.*)` |
|{$PG.LOCKS.MAX.WARN} |<p>-</p> |`100` |
|{$PG.PASSWORD} |<p>Please set user's password in this macro.</p> |`` |
|{$PG.PING_TIME.MAX.WARN} |<p>-</p> |`1s` |
|{$PG.PORT} |<p>-</p> |`5432` |
|{$PG.QUERY_ETIME.MAX.WARN} |<p>-</p> |`30` |
|{$PG.REPL_LAG.MAX.WARN} |<p>-</p> |`10m` |
|{$PG.SLOW_QUERIES.MAX.WARN} |<p>-</p> |`5` |
|{$PG.TRANS_ACTIVE.MAX.WARN} |<p>-</p> |`30s` |
|{$PG.TRANS_IDLE.MAX.WARN} |<p>-</p> |`30s` |
|{$PG.TRANS_WAIT.MAX.WARN} |<p>-</p> |`30s` |
|{$PG.USER} |<p>-</p> |`zbx_monitor` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Database discovery |<p>-</p> |ZABBIX_PASSIVE |pgsql.discovery.db["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]<p>**Filter**:</p> <p>- {#DBNAME} MATCHES_REGEX `{$PG.LLD.FILTER.DBNAME}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|PostgreSQL |Bgwriter: Buffers allocated per second |<p>Number of buffers allocated</p> |DEPENDENT |pgsql.bgwriter.buffers_alloc.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffers_alloc`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Buffers written directly by a backend per second |<p>Number of buffers written directly by a backend</p> |DEPENDENT |pgsql.bgwriter.buffers_backend.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffers_backend`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Buffers backend fsync per second |<p>Number of times a backend had to execute its own fsync call (normally the background writer handles those even when the backend does its own write)</p> |DEPENDENT |pgsql.bgwriter.buffers_backend_fsync.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffers_backend_fsync`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Buffers written during checkpoints per second |<p>Number of buffers written during checkpoints</p> |DEPENDENT |pgsql.bgwriter.buffers_checkpoint.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffers_checkpoint`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Buffers written by the background writer per second |<p>Number of buffers written by the background writer</p> |DEPENDENT |pgsql.bgwriter.buffers_clean.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.buffers_clean`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Requested checkpoints per second |<p>Number of requested checkpoints that have been performed</p> |DEPENDENT |pgsql.bgwriter.checkpoints_req.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.checkpoints_req`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Scheduled checkpoints per second |<p>Number of scheduled checkpoints that have been performed</p> |DEPENDENT |pgsql.bgwriter.checkpoints_timed.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.checkpoints_timed`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Checkpoint sync time |<p>Total amount of time that has been spent in the portion of checkpoint processing where files are synchronized to disk</p> |DEPENDENT |pgsql.bgwriter.checkpoint_sync_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.checkpoint_sync_time`</p><p>- MULTIPLIER: `0.001`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Checkpoint write time |<p>Total amount of time that has been spent in the portion of checkpoint processing where files are written to disk, in milliseconds</p> |DEPENDENT |pgsql.bgwriter.checkpoint_write_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.checkpoint_write_time`</p><p>- MULTIPLIER: `0.001`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Bgwriter: Max written per second |<p>Number of times the background writer stopped a cleaning scan because it had written too many buffers</p> |DEPENDENT |pgsql.bgwriter.maxwritten_clean.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.maxwritten_clean`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |Status: Cache hit ratio % |<p>Cache hit ratio</p> |ZABBIX_PASSIVE |pgsql.cache.hit["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|PostgreSQL |Status: Config hash |<p>PostgreSQL configuration hash</p> |ZABBIX_PASSIVE |pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|PostgreSQL |Connections sum: Active |<p>Total number of connections executing a query</p> |DEPENDENT |pgsql.connections.sum.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.active`</p> |
|PostgreSQL |Connections sum: Idle |<p>Total number of connections waiting for a new client command</p> |DEPENDENT |pgsql.connections.sum.idle<p>**Preprocessing**:</p><p>- JSONPATH: `$.idle`</p> |
|PostgreSQL |Connections sum: Idle in transaction |<p>Total number of connections in a transaction state, but not executing a query</p> |DEPENDENT |pgsql.connections.sum.idle_in_transaction<p>**Preprocessing**:</p><p>- JSONPATH: `$.idle_in_transaction`</p> |
|PostgreSQL |Connections sum: Prepared |<p>Total number of prepared transactions</p><p>https://www.postgresql.org/docs/current/sql-prepare-transaction.html</p> |DEPENDENT |pgsql.connections.sum.prepared<p>**Preprocessing**:</p><p>- JSONPATH: `$.prepared`</p> |
|PostgreSQL |Connections sum: Total |<p>Total number of connections</p> |DEPENDENT |pgsql.connections.sum.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.total`</p> |
|PostgreSQL |Connections sum: Total % |<p>Total number of connections in percentage</p> |DEPENDENT |pgsql.connections.sum.total_pct<p>**Preprocessing**:</p><p>- JSONPATH: `$.total_pct`</p> |
|PostgreSQL |Connections sum: Waiting |<p>Total number of waiting connections</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#WAIT-EVENT-TABLE</p> |DEPENDENT |pgsql.connections.sum.waiting<p>**Preprocessing**:</p><p>- JSONPATH: `$.waiting`</p> |
|PostgreSQL |Status: Ping time |<p>-</p> |ZABBIX_PASSIVE |pgsql.ping.time["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]<p>**Preprocessing**:</p><p>- REGEX: `Time:\s+(\d+\.\d+)\s+ms \1`</p><p>- MULTIPLIER: `0.001`</p> |
|PostgreSQL |Status: Ping |<p>-</p> |ZABBIX_PASSIVE |pgsql.ping["{$PG.HOST}","{$PG.PORT}"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return value.search(/accepting connections/)>0 ? 1 : 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|PostgreSQL |Replication: standby count |<p>Number of standby servers</p> |ZABBIX_PASSIVE |pgsql.replication.count["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|PostgreSQL |Replication: lag in seconds |<p>Replication lag with Master in seconds</p> |ZABBIX_PASSIVE |pgsql.replication.lag.sec["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|PostgreSQL |Replication: recovery role |<p>Replication role: 1 — recovery is still in progress (standby mode), 0 — master mode.</p> |ZABBIX_PASSIVE |pgsql.replication.recovery_role["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|PostgreSQL |Replication: status |<p>Replication status: 0 — streaming is down, 1 — streaming is up, 2 — master mode</p> |ZABBIX_PASSIVE |pgsql.replication.status["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|PostgreSQL |Transactions: Max active transaction time |<p>Current max active transaction time</p> |DEPENDENT |pgsql.transactions.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.active`</p> |
|PostgreSQL |Transactions: Max idle transaction time |<p>Current max idle transaction time</p> |DEPENDENT |pgsql.transactions.idle<p>**Preprocessing**:</p><p>- JSONPATH: `$.idle`</p> |
|PostgreSQL |Transactions: Max prepared transaction time |<p>Current max prepared transaction time</p> |DEPENDENT |pgsql.transactions.prepared<p>**Preprocessing**:</p><p>- JSONPATH: `$.prepared`</p> |
|PostgreSQL |Transactions: Max waiting transaction time |<p>Current max waiting transaction time</p> |DEPENDENT |pgsql.transactions.waiting<p>**Preprocessing**:</p><p>- JSONPATH: `$.waiting`</p> |
|PostgreSQL |Status: Uptime |<p>-</p> |ZABBIX_PASSIVE |pgsql.uptime["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|PostgreSQL |Status: Version |<p>PostgreSQL version</p> |ZABBIX_PASSIVE |pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|PostgreSQL |WAL: Segments count |<p>Number of WAL segments</p> |DEPENDENT |pgsql.wal.count<p>**Preprocessing**:</p><p>- JSONPATH: `$.count`</p> |
|PostgreSQL |WAL: Bytes written |<p>WAL write in bytes</p> |DEPENDENT |pgsql.wal.write<p>**Preprocessing**:</p><p>- JSONPATH: `$.write`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Database size |<p>Database size</p> |ZABBIX_PASSIVE |pgsql.db.size["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}","{#DBNAME}"] |
|PostgreSQL |DB {#DBNAME}: Blocks hit per second |<p>Total number of times disk blocks were found already in the buffer cache, so that a read was not necessary</p> |DEPENDENT |pgsql.dbstat.blks_hit.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].blks_hit`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Disk blocks read per second |<p>Total number of disk blocks read in this database</p> |DEPENDENT |pgsql.dbstat.blks_read.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].blks_read`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Detected conflicts per second |<p>Total number of queries canceled due to conflicts with recovery in this database</p> |DEPENDENT |pgsql.dbstat.conflicts.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].conflicts`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Detected deadlocks per second |<p>Total number of detected deadlocks in this database</p> |DEPENDENT |pgsql.dbstat.deadlocks.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].deadlocks`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Temp_bytes written per second |<p>Total amount of data written to temporary files by queries in this database</p> |DEPENDENT |pgsql.dbstat.temp_bytes.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].temp_bytes`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Temp_files created per second |<p>Total number of temporary files created by queries in this database</p> |DEPENDENT |pgsql.dbstat.temp_files.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].temp_files`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Tuples deleted per second |<p>Total number of rows deleted by queries in this database</p> |DEPENDENT |pgsql.dbstat.tup_deleted.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tup_deleted`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Tuples fetched per second |<p>Total number of rows fetched by queries in this database</p> |DEPENDENT |pgsql.dbstat.tup_fetched.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tup_fetched`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Tuples inserted per second |<p>Total number of rows inserted by queries in this database</p> |DEPENDENT |pgsql.dbstat.tup_inserted.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tup_inserted`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Tuples returned per second |<p>Total number of rows updated by queries in this database</p> |DEPENDENT |pgsql.dbstat.tup_returned.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tup_returned`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Tuples updated per second |<p>Total number of rows updated by queries in this database</p> |DEPENDENT |pgsql.dbstat.tup_updated.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tup_updated`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Commits per second |<p>Number of transactions in this database that have been committed</p> |DEPENDENT |pgsql.dbstat.xact_commit.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].xact_commit`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Rollbacks per second |<p>Total number of transactions in this database that have been rolled back</p> |DEPENDENT |pgsql.dbstat.xact_rollback.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].xact_rollback`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Frozen XID before avtovacuum % |<p>reventing Transaction ID Wraparound Failures</p><p>https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND</p> |DEPENDENT |pgsql.frozenxid.prc_before_av["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.prc_before_av`</p> |
|PostgreSQL |DB {#DBNAME}: Frozen XID before stop % |<p>Preventing Transaction ID Wraparound Failures</p><p>https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND</p> |DEPENDENT |pgsql.frozenxid.prc_before_stop["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.prc_before_stop`</p> |
|PostgreSQL |DB {#DBNAME}: Locks total |<p>Total number of locks in the database</p> |DEPENDENT |pgsql.locks.total["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].total`</p> |
|PostgreSQL |DB {#DBNAME}: Queries slow maintenance count |<p>Slow maintenance query count</p> |DEPENDENT |pgsql.queries.mro.slow_count["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].mro_slow_count`</p> |
|PostgreSQL |DB {#DBNAME}: Queries max maintenance time |<p>Max maintenance query time</p> |DEPENDENT |pgsql.queries.mro.time_max["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].mro_time_max`</p> |
|PostgreSQL |DB {#DBNAME}: Queries sum maintenance time |<p>Sum maintenance query time</p> |DEPENDENT |pgsql.queries.mro.time_sum["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].mro_time_sum`</p> |
|PostgreSQL |DB {#DBNAME}: Queries slow query count |<p>Slow query count</p> |DEPENDENT |pgsql.queries.query.slow_count["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].query_slow_count`</p> |
|PostgreSQL |DB {#DBNAME}: Queries max query time |<p>Max query time</p> |DEPENDENT |pgsql.queries.query.time_max["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].query_time_max`</p> |
|PostgreSQL |DB {#DBNAME}: Queries sum query time |<p>Sum query time</p> |DEPENDENT |pgsql.queries.query.time_sum["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].query_time_sum`</p> |
|PostgreSQL |DB {#DBNAME}: Queries slow transaction count |<p>Slow transaction query count</p> |DEPENDENT |pgsql.queries.tx.slow_count["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tx_slow_count`</p> |
|PostgreSQL |DB {#DBNAME}: Queries max transaction time |<p>Max transaction query time</p> |DEPENDENT |pgsql.queries.tx.time_max["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tx_time_max`</p> |
|PostgreSQL |DB {#DBNAME}: Queries sum transaction time |<p>Sum transaction query time</p> |DEPENDENT |pgsql.queries.tx.time_sum["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$['{#DBNAME}'].tx_time_sum`</p> |
|PostgreSQL |DB {#DBNAME}: Index scans per second |<p>Number of index scans in the database</p> |DEPENDENT |pgsql.scans.idx.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.idx`</p><p>- CHANGE_PER_SECOND</p> |
|PostgreSQL |DB {#DBNAME}: Sequential scans per second |<p>Number of sequential scans in the database</p> |DEPENDENT |pgsql.scans.seq.rate["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.seq`</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix raw items |PostgreSQL: Get bgwriter |<p>Statistics about the background writer process's activity</p> |ZABBIX_PASSIVE |pgsql.bgwriter["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|Zabbix raw items |PostgreSQL: Get connections sum |<p>Collect all metrics from pg_stat_activity</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ACTIVITY-VIEW</p> |ZABBIX_PASSIVE |pgsql.connections.sum["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|Zabbix raw items |PostgreSQL: Get dbstat |<p>Collect all metrics from pg_stat_database per database</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p> |ZABBIX_PASSIVE |pgsql.dbstat["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|Zabbix raw items |PostgreSQL: Get locks |<p>Collect all metrics from pg_locks per database</p><p>https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-TABLES</p> |ZABBIX_PASSIVE |pgsql.locks["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|Zabbix raw items |PostgreSQL: Get queries |<p>Collect all metrics by query execution time</p> |ZABBIX_PASSIVE |pgsql.queries["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}","{$PG.QUERY_ETIME.MAX.WARN}"] |
|Zabbix raw items |PostgreSQL: Get transactions |<p>Collect metrics by transaction execution time</p> |ZABBIX_PASSIVE |pgsql.transactions["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|Zabbix raw items |PostgreSQL: Get WAL |<p>Master item to collect WAL metrics</p> |ZABBIX_PASSIVE |pgsql.wal.stat["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"] |
|Zabbix raw items |DB {#DBNAME}: Get frozen XID |<p>-</p> |ZABBIX_PASSIVE |pgsql.frozenxid["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"] |
|Zabbix raw items |DB {#DBNAME}: Get scans |<p>Number of scans done for table/index in the database</p> |ZABBIX_PASSIVE |pgsql.scans["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|PostgreSQL: Required checkpoints occurs too frequently |<p>Checkpoints are points in the sequence of transactions at which it is guaranteed that the heap and index data files have been updated with all information written before that checkpoint. At checkpoint time, all dirty data pages are flushed to disk and a special checkpoint record is written to the log file.</p><p>https://www.postgresql.org/docs/current/wal-configuration.html</p> |`last(/PostgreSQL by Zabbix agent/pgsql.bgwriter.checkpoints_req.rate) > {$PG.CHECKPOINTS_REQ.MAX.WARN}` |AVERAGE | |
|PostgreSQL: Cache hit ratio too low |<p>-</p> |`max(/PostgreSQL by Zabbix agent/pgsql.cache.hit["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],5m) < {$PG.CACHE_HITRATIO.MIN.WARN}` |WARNING | |
|PostgreSQL: Configuration has changed |<p>-</p> |`last(/PostgreSQL by Zabbix agent/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],#1)<>last(/PostgreSQL by Zabbix agent/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],#2) and length(last(/PostgreSQL by Zabbix agent/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]))>0` |INFO | |
|PostgreSQL: Total number of connections is too high |<p>-</p> |`min(/PostgreSQL by Zabbix agent/pgsql.connections.sum.total_pct,5m) > {$PG.CONN_TOTAL_PCT.MAX.WARN}` |AVERAGE | |
|PostgreSQL: Response too long |<p>-</p> |`min(/PostgreSQL by Zabbix agent/pgsql.ping.time["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],5m) > {$PG.PING_TIME.MAX.WARN}` |AVERAGE |<p>**Depends on**:</p><p>- PostgreSQL: Service is down</p> |
|PostgreSQL: Service is down |<p>-</p> |`last(/PostgreSQL by Zabbix agent/pgsql.ping["{$PG.HOST}","{$PG.PORT}"]) = 0` |HIGH | |
|PostgreSQL: Streaming lag with {#MASTER} is too high |<p>-</p> |`min(/PostgreSQL by Zabbix agent/pgsql.replication.lag.sec["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],5m) > {$PG.REPL_LAG.MAX.WARN}` |AVERAGE | |
|PostgreSQL: Replication is down |<p>-</p> |`max(/PostgreSQL by Zabbix agent/pgsql.replication.status["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],5m)=0` |AVERAGE | |
|PostgreSQL: Service has been restarted |<p>PostgreSQL uptime is less than 10 minutes</p> |`last(/PostgreSQL by Zabbix agent/pgsql.uptime["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]) < 10m` |INFO | |
|PostgreSQL: Version has changed |<p>-</p> |`last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],#1)<>last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],#2) and length(last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]))>0` |INFO | |
|DB {#DBNAME}: Too many recovery conflicts |<p>The primary and standby servers are in many ways loosely connected. Actions on the primary will have an effect on the standby. As a result, there is potential for negative interactions or conflicts between them.</p><p>https://www.postgresql.org/docs/current/hot-standby.html#HOT-STANDBY-CONFLICT</p> |`min(/PostgreSQL by Zabbix agent/pgsql.dbstat.conflicts.rate["{#DBNAME}"],5m) > {$PG.CONFLICTS.MAX.WARN:"{#DBNAME}"}` |AVERAGE | |
|DB {#DBNAME}: Deadlock occurred |<p>-</p> |`min(/PostgreSQL by Zabbix agent/pgsql.dbstat.deadlocks.rate["{#DBNAME}"],5m) > {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}` |HIGH | |
|DB {#DBNAME}: VACUUM FREEZE is required to prevent wraparound |<p>Preventing Transaction ID Wraparound Failures</p><p>https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND</p> |`last(/PostgreSQL by Zabbix agent/pgsql.frozenxid.prc_before_stop["{#DBNAME}"])<{$PG.FROZENXID_PCT_STOP.MIN.HIGH:"{#DBNAME}"}` |AVERAGE | |
|DB {#DBNAME}: Number of locks is too high |<p>-</p> |`min(/PostgreSQL by Zabbix agent/pgsql.locks.total["{#DBNAME}"],5m)>{$PG.LOCKS.MAX.WARN:"{#DBNAME}"}` |WARNING | |
|DB {#DBNAME}: Too many slow queries |<p>-</p> |`min(/PostgreSQL by Zabbix agent/pgsql.queries.query.slow_count["{#DBNAME}"],5m)>{$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}` |WARNING | |
|PostgreSQL: Failed to get items |<p>Zabbix has not received data for items for the last 30 minutes</p> |`nodata(/PostgreSQL by Zabbix agent/pgsql.bgwriter["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],30m) = 1` |WARNING |<p>**Depends on**:</p><p>- PostgreSQL: Service is down</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384190-%C2%A0discussion-thread-for-official-zabbix-template-db-postgresql).

