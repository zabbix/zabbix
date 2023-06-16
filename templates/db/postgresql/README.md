
# PostgreSQL by Zabbix agent

## Overview

This template is designed for the effortless deployment of PostgreSQL monitoring by Zabbix via Zabbix agent and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- PostgreSQL 10-15

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

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

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PG.CACHE_HITRATIO.MIN.WARN}||`90`|
|{$PG.CHECKPOINTS_REQ.MAX.WARN}||`5`|
|{$PG.CONFLICTS.MAX.WARN}||`0`|
|{$PG.CONN_IDLE_IN_TRANS.MAX.WARN}||`5`|
|{$PG.CONN_TOTAL_PCT.MAX.WARN}||`90`|
|{$PG.CONN_WAIT.MAX.WARN}||`0`|
|{$PG.DB}||`postgres`|
|{$PG.DEADLOCKS.MAX.WARN}||`0`|
|{$PG.FROZENXID_PCT_STOP.MIN.HIGH}||`75`|
|{$PG.HOST}||`127.0.0.1`|
|{$PG.LLD.FILTER.DBNAME}||`(.*)`|
|{$PG.LOCKS.MAX.WARN}||`100`|
|{$PG.PING_TIME.MAX.WARN}||`1s`|
|{$PG.PORT}||`5432`|
|{$PG.QUERY_ETIME.MAX.WARN}||`30`|
|{$PG.REPL_LAG.MAX.WARN}||`10m`|
|{$PG.SLOW_QUERIES.MAX.WARN}||`5`|
|{$PG.TRANS_ACTIVE.MAX.WARN}||`30s`|
|{$PG.TRANS_IDLE.MAX.WARN}||`30s`|
|{$PG.TRANS_WAIT.MAX.WARN}||`30s`|
|{$PG.USER}||`zbx_monitor`|
|{$PG.PASSWORD}|<p>Please set user's password in this macro.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Bgwriter: Buffers allocated per second|<p>Number of buffers allocated</p>|Dependent item|pgsql.bgwriter.buffers_alloc.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_alloc`</p></li><li>Change per second</li></ul>|
|Bgwriter: Buffers written directly by a backend per second|<p>Number of buffers written directly by a backend</p>|Dependent item|pgsql.bgwriter.buffers_backend.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend`</p></li><li>Change per second</li></ul>|
|Bgwriter: Buffers backend fsync per second|<p>Number of times a backend had to execute its own fsync call (normally the background writer handles those even when the backend does its own write)</p>|Dependent item|pgsql.bgwriter.buffers_backend_fsync.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend_fsync`</p></li><li>Change per second</li></ul>|
|Bgwriter: Buffers written during checkpoints per second|<p>Number of buffers written during checkpoints</p>|Dependent item|pgsql.bgwriter.buffers_checkpoint.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_checkpoint`</p></li><li>Change per second</li></ul>|
|Bgwriter: Buffers written by the background writer per second|<p>Number of buffers written by the background writer</p>|Dependent item|pgsql.bgwriter.buffers_clean.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_clean`</p></li><li>Change per second</li></ul>|
|Bgwriter: Requested checkpoints per second|<p>Number of requested checkpoints that have been performed</p>|Dependent item|pgsql.bgwriter.checkpoints_req.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_req`</p></li><li>Change per second</li></ul>|
|Bgwriter: Scheduled checkpoints per second|<p>Number of scheduled checkpoints that have been performed</p>|Dependent item|pgsql.bgwriter.checkpoints_timed.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_timed`</p></li><li>Change per second</li></ul>|
|Bgwriter: Checkpoint sync time|<p>Total amount of time that has been spent in the portion of checkpoint processing where files are synchronized to disk</p>|Dependent item|pgsql.bgwriter.checkpoint_sync_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_sync_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Bgwriter: Checkpoint write time|<p>Total amount of time that has been spent in the portion of checkpoint processing where files are written to disk, in milliseconds</p>|Dependent item|pgsql.bgwriter.checkpoint_write_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Bgwriter: Max written per second|<p>Number of times the background writer stopped a cleaning scan because it had written too many buffers</p>|Dependent item|pgsql.bgwriter.maxwritten_clean.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxwritten_clean`</p></li><li>Change per second</li></ul>|
|PostgreSQL: Get bgwriter|<p>Statistics about the background writer process's activity</p>|Zabbix agent|pgsql.bgwriter["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|Status: Cache hit ratio %|<p>Cache hit ratio</p>|Zabbix agent|pgsql.cache.hit["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|Status: Config hash|<p>PostgreSQL configuration hash</p>|Zabbix agent|pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Connections sum: Active|<p>Total number of connections executing a query</p>|Dependent item|pgsql.connections.sum.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p></li></ul>|
|Connections sum: Idle|<p>Total number of connections waiting for a new client command</p>|Dependent item|pgsql.connections.sum.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li></ul>|
|Connections sum: Idle in transaction|<p>Total number of connections in a transaction state, but not executing a query</p>|Dependent item|pgsql.connections.sum.idle_in_transaction<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction`</p></li></ul>|
|Connections sum: Prepared|<p>Total number of prepared transactions</p><p>https://www.postgresql.org/docs/current/sql-prepare-transaction.html</p>|Dependent item|pgsql.connections.sum.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prepared`</p></li></ul>|
|Connections sum: Total|<p>Total number of connections</p>|Dependent item|pgsql.connections.sum.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|Connections sum: Total %|<p>Total number of connections in percentage</p>|Dependent item|pgsql.connections.sum.total_pct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_pct`</p></li></ul>|
|Connections sum: Waiting|<p>Total number of waiting connections</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#WAIT-EVENT-TABLE</p>|Dependent item|pgsql.connections.sum.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting`</p></li></ul>|
|PostgreSQL: Get connections sum|<p>Collect all metrics from pg_stat_activity</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ACTIVITY-VIEW</p>|Zabbix agent|pgsql.connections.sum["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|PostgreSQL: Get dbstat|<p>Collect all metrics from pg_stat_database per database</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p>|Zabbix agent|pgsql.dbstat["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|PostgreSQL: Get locks|<p>Collect all metrics from pg_locks per database</p><p>https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-TABLES</p>|Zabbix agent|pgsql.locks["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|Status: Ping time||Zabbix agent|pgsql.ping.time["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]<p>**Preprocessing**</p><ul><li><p>Regular expression: `Time:\s+(\d+\.\d+)\s+ms \1`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Status: Ping||Zabbix agent|pgsql.ping["{$PG.HOST}","{$PG.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PostgreSQL: Get queries|<p>Collect all metrics by query execution time</p>|Zabbix agent|pgsql.queries["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}","{$PG.QUERY_ETIME.MAX.WARN}"]|
|Replication: standby count|<p>Number of standby servers</p>|Zabbix agent|pgsql.replication.count["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|Replication: lag in seconds|<p>Replication lag with Master in seconds</p>|Zabbix agent|pgsql.replication.lag.sec["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|Replication: recovery role|<p>Replication role: 1 — recovery is still in progress (standby mode), 0 — master mode.</p>|Zabbix agent|pgsql.replication.recovery_role["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|Replication: status|<p>Replication status: 0 — streaming is down, 1 — streaming is up, 2 — master mode</p>|Zabbix agent|pgsql.replication.status["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|Transactions: Max active transaction time|<p>Current max active transaction time</p>|Dependent item|pgsql.transactions.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p></li></ul>|
|Transactions: Max idle transaction time|<p>Current max idle transaction time</p>|Dependent item|pgsql.transactions.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li></ul>|
|Transactions: Max prepared transaction time|<p>Current max prepared transaction time</p>|Dependent item|pgsql.transactions.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prepared`</p></li></ul>|
|Transactions: Max waiting transaction time|<p>Current max waiting transaction time</p>|Dependent item|pgsql.transactions.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting`</p></li></ul>|
|PostgreSQL: Get transactions|<p>Collect metrics by transaction execution time</p>|Zabbix agent|pgsql.transactions["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|Status: Uptime||Zabbix agent|pgsql.uptime["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|Status: Version|<p>PostgreSQL version</p>|Zabbix agent|pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|WAL: Segments count|<p>Number of WAL segments</p>|Dependent item|pgsql.wal.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count`</p></li></ul>|
|PostgreSQL: Get WAL|<p>Master item to collect WAL metrics</p>|Zabbix agent|pgsql.wal.stat["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|
|WAL: Bytes written|<p>WAL write in bytes</p>|Dependent item|pgsql.wal.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write`</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Required checkpoints occurs too frequently|<p>Checkpoints are points in the sequence of transactions at which it is guaranteed that the heap and index data files have been updated with all information written before that checkpoint. At checkpoint time, all dirty data pages are flushed to disk and a special checkpoint record is written to the log file.https://www.postgresql.org/docs/current/wal-configuration.html</p>|`last(/PostgreSQL by Zabbix agent/pgsql.bgwriter.checkpoints_req.rate) > {$PG.CHECKPOINTS_REQ.MAX.WARN}`|Average||
|PostgreSQL: Failed to get items|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/PostgreSQL by Zabbix agent/pgsql.bgwriter["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],30m) = 1`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Service is down</li></ul>|
|PostgreSQL: Cache hit ratio too low||`max(/PostgreSQL by Zabbix agent/pgsql.cache.hit["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],5m) < {$PG.CACHE_HITRATIO.MIN.WARN}`|Warning||
|PostgreSQL: Configuration has changed||`last(/PostgreSQL by Zabbix agent/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],#1)<>last(/PostgreSQL by Zabbix agent/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],#2) and length(last(/PostgreSQL by Zabbix agent/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]))>0`|Info||
|PostgreSQL: Total number of connections is too high||`min(/PostgreSQL by Zabbix agent/pgsql.connections.sum.total_pct,5m) > {$PG.CONN_TOTAL_PCT.MAX.WARN}`|Average||
|PostgreSQL: Response too long||`min(/PostgreSQL by Zabbix agent/pgsql.ping.time["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],5m) > {$PG.PING_TIME.MAX.WARN}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Service is down</li></ul>|
|PostgreSQL: Service is down||`last(/PostgreSQL by Zabbix agent/pgsql.ping["{$PG.HOST}","{$PG.PORT}"]) = 0`|High||
|PostgreSQL: Streaming lag with {#MASTER} is too high||`min(/PostgreSQL by Zabbix agent/pgsql.replication.lag.sec["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],5m) > {$PG.REPL_LAG.MAX.WARN}`|Average||
|PostgreSQL: Replication is down||`max(/PostgreSQL by Zabbix agent/pgsql.replication.status["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],5m)=0`|Average||
|PostgreSQL: Service has been restarted|<p>PostgreSQL uptime is less than 10 minutes.</p>|`last(/PostgreSQL by Zabbix agent/pgsql.uptime["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]) < 10m`|Info||
|PostgreSQL: Version has changed||`last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],#1)<>last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"],#2) and length(last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]))>0`|Info||

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery||Zabbix agent|pgsql.discovery.db["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}"]|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DB [{#DBNAME}]: Get dbstat|<p>Get dbstat metrics for {#DBNAME}</p>|Dependent item|pgsql.dbstat.get_metrics["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Get queries|<p>Get locks metrics for {#DBNAME}</p>|Dependent item|pgsql.queries.get_metrics["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Database size|<p>Database size</p>|Zabbix agent|pgsql.db.size["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DB}","{#DBNAME}"]|
|DB [{#DBNAME}]: Blocks hit per second|<p>Total number of times disk blocks were found already in the buffer cache, so that a read was not necessary</p>|Dependent item|pgsql.dbstat.blks_hit.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_hit`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Disk blocks read per second|<p>Total number of disk blocks read in this database</p>|Dependent item|pgsql.dbstat.blks_read.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_read`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Detected conflicts per second|<p>Total number of queries canceled due to conflicts with recovery in this database</p>|Dependent item|pgsql.dbstat.conflicts.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conflicts`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Detected deadlocks per second|<p>Total number of detected deadlocks in this database</p>|Dependent item|pgsql.dbstat.deadlocks.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlocks`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Temp_bytes written per second|<p>Total amount of data written to temporary files by queries in this database</p>|Dependent item|pgsql.dbstat.temp_bytes.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_bytes`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Temp_files created per second|<p>Total number of temporary files created by queries in this database</p>|Dependent item|pgsql.dbstat.temp_files.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_files`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples deleted per second|<p>Total number of rows deleted by queries in this database</p>|Dependent item|pgsql.dbstat.tup_deleted.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_deleted`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples fetched per second|<p>Total number of rows fetched by queries in this database</p>|Dependent item|pgsql.dbstat.tup_fetched.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_fetched`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples inserted per second|<p>Total number of rows inserted by queries in this database</p>|Dependent item|pgsql.dbstat.tup_inserted.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_inserted`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples returned per second|<p>Total number of rows updated by queries in this database</p>|Dependent item|pgsql.dbstat.tup_returned.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_returned`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Tuples updated per second|<p>Total number of rows updated by queries in this database</p>|Dependent item|pgsql.dbstat.tup_updated.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_updated`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Commits per second|<p>Number of transactions in this database that have been committed</p>|Dependent item|pgsql.dbstat.xact_commit.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_commit`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Rollbacks per second|<p>Total number of transactions in this database that have been rolled back</p>|Dependent item|pgsql.dbstat.xact_rollback.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_rollback`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Frozen XID before avtovacuum %|<p>Preventing Transaction ID Wraparound Failures</p><p>https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND</p>|Dependent item|pgsql.frozenxid.prc_before_av["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prc_before_av`</p></li></ul>|
|DB [{#DBNAME}]: Frozen XID before stop %|<p>Preventing Transaction ID Wraparound Failures</p><p>https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND</p>|Dependent item|pgsql.frozenxid.prc_before_stop["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prc_before_stop`</p></li></ul>|
|DB [{#DBNAME}]: Get frozen XID||Zabbix agent|pgsql.frozenxid["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|
|DB [{#DBNAME}]: Locks total|<p>Total number of locks in the database</p>|Dependent item|pgsql.locks.total["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}'].total`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow maintenance count|<p>Slow maintenance query count</p>|Dependent item|pgsql.queries.mro.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries max maintenance time|<p>Max maintenance query time</p>|Dependent item|pgsql.queries.mro.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum maintenance time|<p>Sum maintenance query time</p>|Dependent item|pgsql.queries.mro.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow query count|<p>Slow query count</p>|Dependent item|pgsql.queries.query.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries max query time|<p>Max query time</p>|Dependent item|pgsql.queries.query.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum query time|<p>Sum query time</p>|Dependent item|pgsql.queries.query.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow transaction count|<p>Slow transaction query count</p>|Dependent item|pgsql.queries.tx.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries max transaction time|<p>Max transaction query time</p>|Dependent item|pgsql.queries.tx.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum transaction time|<p>Sum transaction query time</p>|Dependent item|pgsql.queries.tx.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Index scans per second|<p>Number of index scans in the database</p>|Dependent item|pgsql.scans.idx.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idx`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Sequential scans per second|<p>Number of sequential scans in the database</p>|Dependent item|pgsql.scans.seq.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.seq`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Get scans|<p>Number of scans done for table/index in the database</p>|Zabbix agent|pgsql.scans["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|

### Trigger prototypes for Database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|DB [{#DBNAME}]: Too many recovery conflicts|<p>The primary and standby servers are in many ways loosely connected. Actions on the primary will have an effect on the standby. As a result, there is potential for negative interactions or conflicts between them.https://www.postgresql.org/docs/current/hot-standby.html#HOT-STANDBY-CONFLICT</p>|`min(/PostgreSQL by Zabbix agent/pgsql.dbstat.conflicts.rate["{#DBNAME}"],5m) > {$PG.CONFLICTS.MAX.WARN:"{#DBNAME}"}`|Average||
|DB [{#DBNAME}]: Deadlock occurred||`min(/PostgreSQL by Zabbix agent/pgsql.dbstat.deadlocks.rate["{#DBNAME}"],5m) > {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}`|High||
|DB [{#DBNAME}]: VACUUM FREEZE is required to prevent wraparound|<p>Preventing Transaction ID Wraparound Failureshttps://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND</p>|`last(/PostgreSQL by Zabbix agent/pgsql.frozenxid.prc_before_stop["{#DBNAME}"])<{$PG.FROZENXID_PCT_STOP.MIN.HIGH:"{#DBNAME}"}`|Average||
|DB [{#DBNAME}]: Number of locks is too high||`min(/PostgreSQL by Zabbix agent/pgsql.locks.total["{#DBNAME}"],5m)>{$PG.LOCKS.MAX.WARN:"{#DBNAME}"}`|Warning||
|DB [{#DBNAME}]: Too many slow queries||`min(/PostgreSQL by Zabbix agent/pgsql.queries.query.slow_count["{#DBNAME}"],5m)>{$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

