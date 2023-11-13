
# PostgreSQL by Zabbix agent

## Overview

This template is designed for the deployment of PostgreSQL monitoring by Zabbix via Zabbix agent and uses user parameters to run SQL queries with the `psql` command-line tool.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- PostgreSQL 10-15

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

**Note:**
- The template requires `pg_isready` and `psql` utilities to be installed on the same host with Zabbix agent.

1. Deploy Zabbix agent and create the PostgreSQL user for monitoring (`<password>` at your discretion) with proper access rights to your PostgreSQL instance.

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

2. Copy the `postgresql/` directory to the `zabbix` user home directory - `/var/lib/zabbix/`. The `postgresql/` directory contains the files with SQL queries needed to obtain metrics from PostgreSQL instance.

If the home directory of the `zabbix` user doesn't exist, create it first:

```bash
mkdir -m u=rwx,g=rwx,o= -p /var/lib/zabbix
chown zabbix:zabbix /var/lib/zabbix
```

3. Copy the `template_db_postgresql.conf` file, containing user parameters, to the Zabbix agent configuration directory `/etc/zabbix/zabbix_agentd.d/` and restart Zabbix agent service.

**Note:** if you want to use SSL/TLS encryption to protect communications with the remote PostgreSQL instance, you can modify the connection string in user parameters. For example, to enable required encryption in transport mode without identity checks you could append `?sslmode=required` to the end of the connection string for all keys that use `psql`:

```bash
UserParameter=pgsql.bgwriter[*], psql -qtAX postgresql://"$3":"$4"@"$1":"$2"/"$5"?sslmode=required -f "/var/lib/zabbix/postgresql/pgsql.bgwriter.sql"
```

Consult the PostgreSQL documentation about [`protection modes`](https://www.postgresql.org/docs/current/libpq-ssl.html#LIBPQ-SSL-PROTECTION) and [`client connection parameters`](https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-CONNECT-SSLMODE).

Also, it is assumed that you set up the PostgreSQL instance to work in the desired encryption mode. Check the [`PostgreSQL documentation`](https://www.postgresql.org/docs/current/ssl-tcp.html) for details.

4. Edit the `pg_hba.conf` configuration file to allow connections for the user `zbx_monitor`. For example, you could add one of the following rows to allow local TCP connections from the same host:

```bash
# TYPE  DATABASE        USER            ADDRESS                 METHOD
  host       all        zbx_monitor     localhost               trust
  host       all        zbx_monitor     127.0.0.1/32            md5
  host       all        zbx_monitor     ::1/128                 scram-sha-256
```

For more information please read the PostgreSQL documentation `https://www.postgresql.org/docs/current/auth-pg-hba-conf.html`.

5. Specify the host name or IP address in the `{$PG.HOST}` macro. Adjust the port number with `{$PG.PORT}` macro if needed.

6. Set the password that you specified in step 1 in the macro `{$PG.PASSWORD}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PG.CACHE_HITRATIO.MIN.WARN}|<p>Minimum cache hit ratio percentage for trigger expression.</p>|`90`|
|{$PG.CHECKPOINTS_REQ.MAX.WARN}|<p>Maximum required checkpoint occurrences for trigger expression.</p>|`5`|
|{$PG.CONFLICTS.MAX.WARN}|<p>Maximum number of recovery conflicts for trigger expression.</p>|`0`|
|{$PG.CONN_TOTAL_PCT.MAX.WARN}|<p>Maximum percentage of current connections for trigger expression.</p>|`90`|
|{$PG.DATABASE}|<p>Default PostgreSQL database for the connection.</p>|`postgres`|
|{$PG.DEADLOCKS.MAX.WARN}|<p>Maximum number of detected deadlocks for trigger expression.</p>|`0`|
|{$PG.FROZENXID_PCT_STOP.MIN.HIGH}|<p>Minimum frozen XID before stop percentage for trigger expression.</p>|`75`|
|{$PG.HOST}|<p>Hostname or IP of PostgreSQL host.</p>|`localhost`|
|{$PG.LLD.FILTER.DBNAME}|<p>Filter of discoverable databases.</p>|`.+`|
|{$PG.LOCKS.MAX.WARN}|<p>Maximum number of locks for trigger expression.</p>|`100`|
|{$PG.PING_TIME.MAX.WARN}|<p>Maximum time of connection response for trigger expression.</p>|`1s`|
|{$PG.PORT}|<p>PostgreSQL service port.</p>|`5432`|
|{$PG.QUERY_ETIME.MAX.WARN}|<p>Execution time limit for count of slow queries.</p>|`30`|
|{$PG.REPL_LAG.MAX.WARN}|<p>Maximum replication lag time for trigger expression.</p>|`10m`|
|{$PG.SLOW_QUERIES.MAX.WARN}|<p>Slow queries count threshold for a trigger.</p>|`5`|
|{$PG.USER}|<p>PostgreSQL username.</p>|`zbx_monitor`|
|{$PG.PASSWORD}|<p>PostgreSQL user password.</p>|`<Put the password here>`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Bgwriter: Buffers allocated per second|<p>Number of buffers allocated per second.</p>|Dependent item|pgsql.bgwriter.buffers_alloc.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_alloc`</p></li><li>Change per second</li></ul>|
|Bgwriter: Buffers written directly by a backend per second|<p>Number of buffers written directly by a backend per second.</p>|Dependent item|pgsql.bgwriter.buffers_backend.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend`</p></li><li>Change per second</li></ul>|
|Bgwriter: Times a backend executed its own fsync per second|<p>Number of times a backend had to execute its own fsync call per second (normally the background writer handles those even when the backend does its own write).</p>|Dependent item|pgsql.bgwriter.buffers_backend_fsync.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend_fsync`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers written during checkpoints per second|<p>Number of buffers written during checkpoints per second.</p>|Dependent item|pgsql.bgwriter.buffers_checkpoint.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_checkpoint`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers written by the background writer per second|<p>Number of buffers written by the background writer per second.</p>|Dependent item|pgsql.bgwriter.buffers_clean.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_clean`</p></li><li>Change per second</li></ul>|
|Checkpoint: Requested per second|<p>Number of requested checkpoints that have been performed per second.</p>|Dependent item|pgsql.bgwriter.checkpoints_req.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_req`</p></li><li>Change per second</li></ul>|
|Checkpoint: Scheduled per second|<p>Number of scheduled checkpoints that have been performed per second.</p>|Dependent item|pgsql.bgwriter.checkpoints_timed.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_timed`</p></li><li>Change per second</li></ul>|
|Checkpoint: Checkpoint sync time per second|<p>Total amount of time per second that has been spent in the portion of checkpoint processing where files are synchronized to disk.</p>|Dependent item|pgsql.bgwriter.checkpoint_sync_time.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_sync_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Checkpoint: Checkpoint write time per second|<p>Total amount of time per second that has been spent in the portion of checkpoint processing where files are written to disk.</p>|Dependent item|pgsql.bgwriter.checkpoint_write_time.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Bgwriter: Number of bgwriter cleaning scan stopped per second|<p>Number of times the background writer stopped a cleaning scan because it had written too many buffers per second.</p>|Dependent item|pgsql.bgwriter.maxwritten_clean.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxwritten_clean`</p></li><li>Change per second</li></ul>|
|PostgreSQL: Get bgwriter|<p>Collect all metrics from pg_stat_bgwriter:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-BGWRITER-VIEW</p>|Zabbix agent|pgsql.bgwriter["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|PostgreSQL: Cache hit ratio, %|<p>Cache hit ratio.</p>|Zabbix agent|pgsql.cache.hit["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|PostgreSQL: Config hash|<p>PostgreSQL configuration hash.</p>|Zabbix agent|pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Connections sum: Active|<p>Total number of connections executing a query.</p>|Dependent item|pgsql.connections.sum.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p></li></ul>|
|Connections sum: Idle|<p>Total number of connections waiting for a new client command.</p>|Dependent item|pgsql.connections.sum.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li></ul>|
|Connections sum: Idle in transaction|<p>Total number of connections in a transaction state but not executing a query.</p>|Dependent item|pgsql.connections.sum.idle_in_transaction<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction`</p></li></ul>|
|Connections sum: Prepared|<p>Total number of prepared transactions:</p><p>https://www.postgresql.org/docs/current/sql-prepare-transaction.html</p>|Dependent item|pgsql.connections.sum.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prepared`</p></li></ul>|
|Connections sum: Total|<p>Total number of connections.</p>|Dependent item|pgsql.connections.sum.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|Connections sum: Total, %|<p>Total number of connections, in percentage.</p>|Dependent item|pgsql.connections.sum.total_pct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_pct`</p></li></ul>|
|Connections sum: Waiting|<p>Total number of waiting connections:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#WAIT-EVENT-TABLE</p>|Dependent item|pgsql.connections.sum.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting`</p></li></ul>|
|PostgreSQL: Get connections sum|<p>Collect all metrics from pg_stat_activity:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ACTIVITY-VIEW</p>|Zabbix agent|pgsql.connections.sum["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|PostgreSQL: Get dbstat|<p>Collect all metrics from pg_stat_database per database:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p>|Zabbix agent|pgsql.dbstat["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|PostgreSQL: Get locks|<p>Collect all metrics from pg_locks per database:</p><p>https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-TABLES</p>|Zabbix agent|pgsql.locks["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|PostgreSQL: Ping time|<p>Used to get the `SELECT 1` query execution time.</p>|Zabbix agent|pgsql.ping.time["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Regular expression: `Time:\s+(\d+\.\d+)\s+ms \1`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|PostgreSQL: Ping|<p>Used to test a connection to see if it is alive. It is set to 0 if the instance doesn't accept the connections.</p>|Zabbix agent|pgsql.ping["{$PG.HOST}","{$PG.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return value.search(/accepting connections/)>0 ? 1 : 0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PostgreSQL: Get queries|<p>Collect all metrics by query execution time.</p>|Zabbix agent|pgsql.queries["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{$PG.QUERY_ETIME.MAX.WARN}"]|
|PostgreSQL: Replication: Standby count|<p>Number of standby servers.</p>|Zabbix agent|pgsql.replication.count["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|PostgreSQL: Replication: Lag in seconds|<p>Replication lag with master, in seconds.</p>|Zabbix agent|pgsql.replication.lag.sec["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|PostgreSQL: Replication: Recovery role|<p>Replication role: 1 — recovery is still in progress (standby mode), 0 — master mode.</p>|Zabbix agent|pgsql.replication.recovery_role["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|PostgreSQL: Replication: Status|<p>Replication status: 0 — streaming is down, 1 — streaming is up, 2 — master mode.</p>|Zabbix agent|pgsql.replication.status["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Transactions: Max active transaction time|<p>Current max active transaction time.</p>|Dependent item|pgsql.transactions.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p></li></ul>|
|Transactions: Max idle transaction time|<p>Current max idle transaction time.</p>|Dependent item|pgsql.transactions.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li></ul>|
|Transactions: Max prepared transaction time|<p>Current max prepared transaction time.</p>|Dependent item|pgsql.transactions.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prepared`</p></li></ul>|
|Transactions: Max waiting transaction time|<p>Current max waiting transaction time.</p>|Dependent item|pgsql.transactions.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting`</p></li></ul>|
|PostgreSQL: Get transactions|<p>Collect metrics by transaction execution time.</p>|Zabbix agent|pgsql.transactions["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|PostgreSQL: Uptime|<p>Time since the server started.</p>|Zabbix agent|pgsql.uptime["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|PostgreSQL: Version|<p>PostgreSQL version.</p>|Zabbix agent|pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|WAL: Segments count|<p>Number of WAL segments.</p>|Dependent item|pgsql.wal.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count`</p></li></ul>|
|PostgreSQL: Get WAL|<p>Collect write-ahead log (WAL) metrics.</p>|Zabbix agent|pgsql.wal.stat["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|WAL: Bytes written|<p>WAL write, in bytes.</p>|Dependent item|pgsql.wal.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write`</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Required checkpoints occur too frequently|<p>Checkpoints are points in the sequence of transactions at which it is guaranteed that the heap and index data files have been updated with all information written before that checkpoint. At checkpoint time, all dirty data pages are flushed to disk and a special checkpoint record is written to the log file.<br>https://www.postgresql.org/docs/current/wal-configuration.html</p>|`last(/PostgreSQL by Zabbix agent/pgsql.bgwriter.checkpoints_req.rate) > {$PG.CHECKPOINTS_REQ.MAX.WARN}`|Average||
|PostgreSQL: Failed to get items|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/PostgreSQL by Zabbix agent/pgsql.bgwriter["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],30m) = 1`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Service is down</li></ul>|
|PostgreSQL: Cache hit ratio too low|<p>Cache hit ratio is lower than {$PG.CACHE_HITRATIO.MIN.WARN} for 5m.</p>|`max(/PostgreSQL by Zabbix agent/pgsql.cache.hit["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],5m) < {$PG.CACHE_HITRATIO.MIN.WARN}`|Warning||
|PostgreSQL: Configuration has changed|<p>PostgreSQL configuration has changed.</p>|`last(/PostgreSQL by Zabbix agent/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],#1)<>last(/PostgreSQL by Zabbix agent/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],#2) and length(last(/PostgreSQL by Zabbix agent/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]))>0`|Info||
|PostgreSQL: Total number of connections is too high|<p>Total number of current connections exceeds the limit of {$PG.CONN_TOTAL_PCT.MAX.WARN}% out of the maximum number of concurrent connections to the database server (the "max_connections" setting).</p>|`min(/PostgreSQL by Zabbix agent/pgsql.connections.sum.total_pct,5m) > {$PG.CONN_TOTAL_PCT.MAX.WARN}`|Average||
|PostgreSQL: Response too long|<p>Response is taking too long (over {$PG.PING_TIME.MAX.WARN} for 5m).</p>|`min(/PostgreSQL by Zabbix agent/pgsql.ping.time["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],5m) > {$PG.PING_TIME.MAX.WARN}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Service is down</li></ul>|
|PostgreSQL: Service is down|<p>Last test of a connection was unsuccessful.</p>|`last(/PostgreSQL by Zabbix agent/pgsql.ping["{$PG.HOST}","{$PG.PORT}"]) = 0`|High||
|PostgreSQL: Streaming lag with master is too high|<p>Replication lag with master is higher than {$PG.REPL_LAG.MAX.WARN} for 5m.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.replication.lag.sec["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],5m) > {$PG.REPL_LAG.MAX.WARN}`|Average||
|PostgreSQL: Replication is down|<p>Replication is enabled and data streaming was down for 5m.</p>|`max(/PostgreSQL by Zabbix agent/pgsql.replication.status["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],5m)=0`|Average||
|PostgreSQL: Service has been restarted|<p>PostgreSQL uptime is less than 10 minutes.</p>|`last(/PostgreSQL by Zabbix agent/pgsql.uptime["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]) < 10m`|Average||
|PostgreSQL: Version has changed||`last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],#1)<>last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],#2) and length(last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]))>0`|Info||

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Discovers databases (DB) in the database management system (DBMS), except:</p><p>- templates;</p><p>- default "postgres" DB;</p><p>- DBs that do not allow connections.</p>|Zabbix agent|pgsql.discovery.db["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DB [{#DBNAME}]: Get dbstat|<p>Get dbstat metrics for database "{#DBNAME}".</p>|Dependent item|pgsql.dbstat.get_metrics["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Get queries|<p>Get queries metrics for database "{#DBNAME}".</p>|Dependent item|pgsql.queries.get_metrics["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Database size|<p>Database size.</p>|Zabbix agent|pgsql.db.size["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#DBNAME}"]|
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
|DB [{#DBNAME}]: Frozen XID before autovacuum, %|<p>Preventing Transaction ID Wraparound Failures:</p><p>https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND</p>|Dependent item|pgsql.frozenxid.prc_before_av["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prc_before_av`</p></li></ul>|
|DB [{#DBNAME}]: Frozen XID before stop, %|<p>Preventing Transaction ID Wraparound Failures:</p><p>https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND</p>|Dependent item|pgsql.frozenxid.prc_before_stop["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prc_before_stop`</p></li></ul>|
|DB [{#DBNAME}]: Get frozen XID||Zabbix agent|pgsql.frozenxid["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|
|DB [{#DBNAME}]: Num of locks total|<p>Total number of locks in this database.</p>|Dependent item|pgsql.locks.total["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}'].total`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow maintenance count|<p>Slow maintenance query count for this database.</p>|Dependent item|pgsql.queries.mro.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries max maintenance time|<p>Max maintenance query time for this database.</p>|Dependent item|pgsql.queries.mro.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum maintenance time|<p>Sum maintenance query time for this database.</p>|Dependent item|pgsql.queries.mro.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow query count|<p>Slow query count for this database.</p>|Dependent item|pgsql.queries.query.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries max query time|<p>Max query time for this database.</p>|Dependent item|pgsql.queries.query.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum query time|<p>Sum query time for this database.</p>|Dependent item|pgsql.queries.query.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow transaction count|<p>Slow transaction query count for this database.</p>|Dependent item|pgsql.queries.tx.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries max transaction time|<p>Max transaction query time for this database.</p>|Dependent item|pgsql.queries.tx.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum transaction time|<p>Sum transaction query time for this database.</p>|Dependent item|pgsql.queries.tx.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Index scans per second|<p>Number of index scans in the database per second.</p>|Dependent item|pgsql.scans.idx.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idx`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Sequential scans per second|<p>Number of sequential scans in this database per second.</p>|Dependent item|pgsql.scans.seq.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.seq`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Get scans|<p>Number of scans done for table/index in this database.</p>|Zabbix agent|pgsql.scans["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|

### Trigger prototypes for Database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|DB [{#DBNAME}]: Too many recovery conflicts|<p>The primary and standby servers are in many ways loosely connected. Actions on the primary will have an effect on the standby. As a result, there is potential for negative interactions or conflicts between them.<br>https://www.postgresql.org/docs/current/hot-standby.html#HOT-STANDBY-CONFLICT</p>|`min(/PostgreSQL by Zabbix agent/pgsql.dbstat.conflicts.rate["{#DBNAME}"],5m) > {$PG.CONFLICTS.MAX.WARN:"{#DBNAME}"}`|Average||
|DB [{#DBNAME}]: Deadlock occurred|<p>Number of deadlocks detected per second exceeds {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"} for 5m.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.dbstat.deadlocks.rate["{#DBNAME}"],5m) > {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}`|High||
|DB [{#DBNAME}]: VACUUM FREEZE is required to prevent wraparound|<p>Preventing Transaction ID Wraparound Failures:<br>https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND</p>|`last(/PostgreSQL by Zabbix agent/pgsql.frozenxid.prc_before_stop["{#DBNAME}"])<{$PG.FROZENXID_PCT_STOP.MIN.HIGH:"{#DBNAME}"}`|Average||
|DB [{#DBNAME}]: Number of locks is too high||`min(/PostgreSQL by Zabbix agent/pgsql.locks.total["{#DBNAME}"],5m)>{$PG.LOCKS.MAX.WARN:"{#DBNAME}"}`|Warning||
|DB [{#DBNAME}]: Too many slow queries|<p>The number of detected slow queries exceeds the limit of {$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.queries.query.slow_count["{#DBNAME}"],5m)>{$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

