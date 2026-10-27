
# PostgreSQL by Zabbix agent

## Overview

This template is designed for the deployment of PostgreSQL monitoring by Zabbix via Zabbix agent and uses user parameters to run SQL queries with the `psql` command-line tool.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- PostgreSQL 10-18

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

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

**Note:** the template and these files are shipped together and have to be updated together. The `postgresql/` directory and `template_db_postgresql.conf` carry a revision number, reported by the *User parameters revision* item. If the deployed files are older than the template requires, the trigger *User parameters are outdated* is raised - copy both the directory and the configuration file of the current template version to the host and restart Zabbix agent. Newer files always work with an older template, so the agent side can be updated first.

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
|{$PG.HOST}|<p>Hostname or IP of PostgreSQL host.</p>|`localhost`|
|{$PG.PORT}|<p>PostgreSQL service port.</p>|`5432`|
|{$PG.USER}|<p>PostgreSQL username.</p>|`zbx_monitor`|
|{$PG.PASSWORD}|<p>PostgreSQL user password.</p>||
|{$PG.DATABASE}|<p>Default PostgreSQL database for the connection.</p>|`postgres`|
|{$PG.LLD.FILTER.DBNAME}|<p>Filter of discoverable databases.</p>|`.+`|
|{$PG.LLD.FILTER.SCHEMA}|<p>Filter of discoverable schemas.</p>|`.+`|
|{$PG.LLD.FILTER.SUBSCRIPTION}|<p>Filter of discoverable logical replication subscriptions.</p>|`.+`|
|{$PG.LLD.FILTER.TABLESPACE}|<p>Filter of discoverable tablespaces.</p>|`.+`|
|{$PG.CACHE_HITRATIO.MIN.WARN}|<p>Minimum cache hit ratio percentage for trigger expression.</p>|`90`|
|{$PG.CHECKPOINTS_REQ.MAX.WARN}|<p>Maximum required checkpoint occurrences for trigger expression.</p>|`5`|
|{$PG.CONFLICTS.MAX.WARN}|<p>Maximum number of recovery conflicts for trigger expression.</p>|`0`|
|{$PG.CONN_TOTAL_PCT.MAX.WARN}|<p>Maximum percentage of current connections for trigger expression.</p>|`90`|
|{$PG.DEADLOCKS.MAX.WARN}|<p>Maximum number of detected deadlocks for trigger expression.</p>|`0`|
|{$PG.FROZENXID_PCT_STOP.MIN.HIGH}|<p>Minimum frozen XID before stop percentage for trigger expression.</p>|`75`|
|{$PG.IO.CACHE.MIN.WARN}|<p>Minimum shared buffers hit ratio percentage for trigger expression.</p>|`90`|
|{$PG.LOCKS.MAX.WARN}|<p>Maximum number of locks for trigger expression.</p>|`100`|
|{$PG.PING_TIME.MAX.WARN}|<p>Maximum time of connection response for trigger expression.</p>|`1s`|
|{$PG.QUERY_EXECUTION_TIME.MAX.WARN}|<p>Execution time limit for count of slow queries.</p>|`30`|
|{$PG.REPLICATION.SLOTS.RETAINING.MAX.HIGH}|<p>Critical number of inactive replication slots retaining WAL for trigger expression.</p>|`2`|
|{$PG.REPLICATION.SLOTS.RETAINING.MAX.WARN}|<p>Maximum number of inactive replication slots retaining WAL for trigger expression.</p>|`1`|
|{$PG.REPL_LAG.MAX.WARN}|<p>Maximum replication lag time for trigger expression.</p>|`10m`|
|{$PG.SLOW_QUERIES.MAX.WARN}|<p>Slow queries count threshold for a trigger.</p>|`5`|
|{$PG.SLRU.CACHE.MIN.HIGH}|<p>Critical SLRU cache hit ratio percentage for trigger expression.</p>|`95`|
|{$PG.SLRU.CACHE.MIN.WARN}|<p>Minimum SLRU cache hit ratio percentage for trigger expression.</p>|`98`|
|{$PG.SUBSCRIPTION.ERRORS.MAX.WARN}|<p>Maximum number of logical replication subscription errors for trigger expression.</p>|`2`|
|{$PG.TABLESPACE.SIZE.MAX.HIGH}|<p>Critical tablespace size for trigger expression.</p>|`15G`|
|{$PG.TABLESPACE.SIZE.MAX.WARN}|<p>Maximum tablespace size for trigger expression.</p>|`5G`|
|{$PG.WAL.BUFFERS_FULL.MAX.WARN}|<p>Maximum number of WAL buffer exhaustions per second for trigger expression.</p>|`10`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Bgwriter: Buffers allocated per second|<p>Number of buffers allocated per second.</p>|Dependent item|pgsql.bgwriter.buffers_alloc.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_alloc`</p></li><li>Change per second</li></ul>|
|Bgwriter: Buffers written directly by a backend per second|<p>Number of buffers written directly by a backend per second. Available in PostgreSQL versions prior to 17.</p>|Dependent item|pgsql.bgwriter.buffers_backend.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Bgwriter: Times a backend executed its own fsync per second|<p>Number of times a backend had to execute its own fsync call per second (normally the background writer handles those even when the backend does its own write). Available in PostgreSQL versions prior to 17.</p>|Dependent item|pgsql.bgwriter.buffers_backend_fsync.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend_fsync`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers written during checkpoints per second|<p>Number of buffers written during checkpoints per second.</p>|Dependent item|pgsql.bgwriter.buffers_checkpoint.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_checkpoint`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers written by the background writer per second|<p>Number of buffers written by the background writer per second.</p>|Dependent item|pgsql.bgwriter.buffers_clean.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_clean`</p></li><li>Change per second</li></ul>|
|Checkpoint: Requested per second|<p>Number of requested checkpoints that have been performed per second.</p>|Dependent item|pgsql.bgwriter.checkpoints_req.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_req`</p></li><li>Change per second</li></ul>|
|Checkpoint: Scheduled per second|<p>Number of scheduled checkpoints that have been performed per second.</p>|Dependent item|pgsql.bgwriter.checkpoints_timed.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_timed`</p></li><li>Change per second</li></ul>|
|Checkpoint: Checkpoint sync time per second|<p>Total amount of time per second that has been spent in the portion of checkpoint processing where files are synchronized to disk.</p>|Dependent item|pgsql.bgwriter.checkpoint_sync_time.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_sync_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Checkpoint: Checkpoint write time per second|<p>Total amount of time per second that has been spent in the portion of checkpoint processing where files are written to disk.</p>|Dependent item|pgsql.bgwriter.checkpoint_write_time.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Bgwriter: Number of bgwriter cleaning scan stopped per second|<p>Number of times the background writer stopped a cleaning scan because it had written too many buffers per second.</p>|Dependent item|pgsql.bgwriter.maxwritten_clean.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxwritten_clean`</p></li><li>Change per second</li></ul>|
|Get bgwriter|<p>Collect all metrics from pg_stat_bgwriter:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-BGWRITER-VIEW</p>|Zabbix agent|pgsql.bgwriter["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Cache hit ratio, %|<p>Cache hit ratio.</p>|Zabbix agent|pgsql.cache.hit["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Config hash|<p>PostgreSQL configuration hash.</p>|Zabbix agent|pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Connections sum: Active|<p>Total number of connections executing a query.</p>|Dependent item|pgsql.connections.sum.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p></li></ul>|
|Connections sum: Idle|<p>Total number of connections waiting for a new client command.</p>|Dependent item|pgsql.connections.sum.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li></ul>|
|Connections sum: Idle in transaction|<p>Total number of connections in a transaction state but not executing a query.</p>|Dependent item|pgsql.connections.sum.idle_in_transaction<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction`</p></li></ul>|
|Connections sum: Prepared|<p>Total number of prepared transactions:</p><p>https://www.postgresql.org/docs/current/sql-prepare-transaction.html</p>|Dependent item|pgsql.connections.sum.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prepared`</p></li></ul>|
|Connections sum: Total|<p>Total number of connections.</p>|Dependent item|pgsql.connections.sum.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|Connections sum: Total, %|<p>Total number of connections, in percentage.</p>|Dependent item|pgsql.connections.sum.total_pct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_pct`</p></li></ul>|
|Connections sum: Waiting|<p>Total number of waiting connections:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#WAIT-EVENT-TABLE</p>|Dependent item|pgsql.connections.sum.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting`</p></li></ul>|
|Get connections sum|<p>Collect all metrics from pg_stat_activity:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ACTIVITY-VIEW</p>|Zabbix agent|pgsql.connections.sum["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Get dbstat|<p>Collect all metrics from pg_stat_database per database:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p>|Zabbix agent|pgsql.dbstat["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Get locks|<p>Collect all metrics from pg_locks per database:</p><p>https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-TABLES</p>|Zabbix agent|pgsql.locks["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Ping time|<p>Used to get the `SELECT 1` query execution time.</p>|Zabbix agent|pgsql.ping.time["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Regular expression: `Time:\s+(\d+\.\d+)\s+ms \1`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Ping|<p>Used to test a connection to see if it is alive. It is set to 0 if the instance doesn't accept the connections.</p>|Zabbix agent|pgsql.ping["{$PG.HOST}","{$PG.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return value.search(/accepting connections/)>0 ? 1 : 0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get queries|<p>Collect all metrics by query execution time.</p>|Zabbix agent|pgsql.queries["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{$PG.QUERY_EXECUTION_TIME.MAX.WARN}"]|
|Get relation sizes|<p>Collects the on-disk size of the tables, their indexes and TOAST data, summed per schema of the "{$PG.DATABASE}" database.</p><p>Reads the size metadata of every relation, therefore it is polled less frequently than the other metrics.</p>|Zabbix agent|pgsql.relation.size["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Replication: Standby count|<p>Number of standby servers.</p>|Zabbix agent|pgsql.replication.count["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Replication: Lag in seconds|<p>Replication lag with master, in seconds.</p>|Zabbix agent|pgsql.replication.lag.sec["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Replication: Recovery role|<p>Replication role: 1 — recovery is still in progress (standby mode), 0 — master mode.</p>|Zabbix agent|pgsql.replication.recovery_role["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Replication: Status|<p>Replication status: 0 — streaming is down, 1 — streaming is up, 2 — master mode.</p>|Zabbix agent|pgsql.replication.status["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Get subscription stats|<p>Collects the error counters of the logical replication subscriptions from `pg_stat_subscription_stats`:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#MONITORING-PG-STAT-SUBSCRIPTION-STATS</p>|Zabbix agent|pgsql.subscription.stats["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Get tablespace sizes|<p>Collects the size, location and owner of every tablespace of the instance.</p><p>Scans the tablespace directories, therefore it is polled less frequently than the other metrics.</p>|Zabbix agent|pgsql.tablespace.size["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Transactions: Max active transaction time|<p>Current max active transaction time.</p>|Dependent item|pgsql.transactions.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p></li></ul>|
|Transactions: Max idle transaction time|<p>Current max idle transaction time.</p>|Dependent item|pgsql.transactions.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li></ul>|
|Transactions: Max prepared transaction time|<p>Current max prepared transaction time.</p>|Dependent item|pgsql.transactions.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prepared`</p></li></ul>|
|Transactions: Max waiting transaction time|<p>Current max waiting transaction time.</p>|Dependent item|pgsql.transactions.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting`</p></li></ul>|
|Get transactions|<p>Collect metrics by transaction execution time.</p>|Zabbix agent|pgsql.transactions["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Uptime|<p>Time since the server started.</p>|Zabbix agent|pgsql.uptime["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|User parameters revision|<p>Revision of the user parameter files deployed on the host with Zabbix agent.</p><p>Reported by the `pgsql.userparam.revision` user parameter; 0 means the deployed files are older than the revision mechanism itself.</p>|Zabbix agent|pgsql.userparam.revision<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Version|<p>PostgreSQL version.</p>|Zabbix agent|pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Version (numeric)|<p>The PostgreSQL version as an integer, the same way `server_version_num` reports it - 18.4 becomes 180004.</p>|Dependent item|pgsql.version.num<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Version validator|<p>Converts the PostgreSQL version into low-level discovery data: one entry with a `{#BELOW_V<major>}` macro per release that introduced monitored metrics.</p><p>Discovery rules filter on those macros, which is how version-specific metrics are created only where PostgreSQL provides them.</p>|Dependent item|pgsql.version.validator<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|WAL: Segments count|<p>Number of WAL segments.</p>|Dependent item|pgsql.wal.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count`</p></li></ul>|
|Get WAL|<p>Collect write-ahead log (WAL) metrics.</p>|Zabbix agent|pgsql.wal.stat["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
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
|PostgreSQL: User parameters are outdated|<p>The user parameter files deployed on the host with Zabbix agent are older than this template version, so some items cannot collect data.<br>Copy the `postgresql/` directory and the `template_db_postgresql.conf` file of this template version to the host with Zabbix agent and restart the agent.</p>|`last(/PostgreSQL by Zabbix agent/pgsql.userparam.revision) < 2`|Warning||
|PostgreSQL: Version has changed|<p>The PostgreSQL version has changed. Check whether the update was planned, since a major version change can affect the availability of the collected metrics.</p>|`last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],#1)<>last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],#2) and length(last(/PostgreSQL by Zabbix agent/pgsql.version["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]))>0`|Info||

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
|PostgreSQL: DB [{#DBNAME}]: Too many recovery conflicts|<p>The primary and standby servers are in many ways loosely connected. Actions on the primary will have an effect on the standby. As a result, there is potential for negative interactions or conflicts between them.<br>https://www.postgresql.org/docs/current/hot-standby.html#HOT-STANDBY-CONFLICT</p>|`min(/PostgreSQL by Zabbix agent/pgsql.dbstat.conflicts.rate["{#DBNAME}"],5m) > {$PG.CONFLICTS.MAX.WARN:"{#DBNAME}"}`|Average||
|PostgreSQL: DB [{#DBNAME}]: Deadlock occurred|<p>Number of deadlocks detected per second exceeds {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"} for 5m.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.dbstat.deadlocks.rate["{#DBNAME}"],5m) > {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}`|High||
|PostgreSQL: DB [{#DBNAME}]: VACUUM FREEZE is required to prevent wraparound|<p>Preventing Transaction ID Wraparound Failures:<br>https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND</p>|`last(/PostgreSQL by Zabbix agent/pgsql.frozenxid.prc_before_stop["{#DBNAME}"])<{$PG.FROZENXID_PCT_STOP.MIN.HIGH:"{#DBNAME}"}`|Average||
|PostgreSQL: DB [{#DBNAME}]: Number of locks is too high|<p>The number of locks in the database "{#DBNAME}" has exceeded {$PG.LOCKS.MAX.WARN:"{#DBNAME}"} for 5m. A growing number of locks indicates contention between transactions and can lead to queries waiting instead of being executed.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.locks.total["{#DBNAME}"],5m)>{$PG.LOCKS.MAX.WARN:"{#DBNAME}"}`|Warning||
|PostgreSQL: DB [{#DBNAME}]: Too many slow queries|<p>The number of detected slow queries exceeds the limit of {$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.queries.query.slow_count["{#DBNAME}"],5m)>{$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}`|Warning||

### LLD rule WAL statistics metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL statistics metrics discovery|<p>Discovers the `pg_stat_wal` metrics available in PostgreSQL 14 and newer.</p>|Dependent item|pgsql.stat.wal.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for WAL statistics metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL statistics: Get data|<p>Collects write-ahead log activity from `pg_stat_wal`:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#MONITORING-PG-STAT-WAL-VIEW</p>|Zabbix agent|pgsql.stat.wal["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]|
|WAL statistics: Bytes generated total|<p>Total amount of WAL generated since the last statistics reset.</p>|Dependent item|pgsql.stat.wal.bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_bytes`</p></li></ul>|
|WAL statistics: Records total|<p>Total number of WAL records generated since the last statistics reset.</p>|Dependent item|pgsql.stat.wal.records[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_records`</p></li></ul>|
|WAL statistics: Full page images total|<p>Total number of full page images written to WAL since the last statistics reset.</p><p>Full page images increase the WAL volume and are usually caused by frequent checkpoints.</p>|Dependent item|pgsql.stat.wal.fpi[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_fpi`</p></li></ul>|
|WAL statistics: Buffers full total|<p>Total number of times the WAL buffers were full and WAL had to be written out before the transaction could continue, since the last statistics reset.</p><p>A growing value indicates WAL buffer pressure - consider increasing `wal_buffers`.</p>|Dependent item|pgsql.stat.wal.buffers_full[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_buffers_full`</p></li></ul>|
|WAL statistics: Buffers full per second|<p>Number of times per second the WAL buffers were full and had to be written out.</p>|Dependent item|pgsql.stat.wal.buffers_full.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_buffers_full`</p></li><li>Change per second</li></ul>|
|WAL statistics: Statistics reset time|<p>Time of the last reset of the WAL statistics. The totals above are counted from this moment.</p>|Dependent item|pgsql.stat.wal.reset_time[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats_reset`</p></li><li><p>Discard unchanged with heartbeat: `7d`</p></li></ul>|

### Trigger prototypes for WAL statistics metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: WAL statistics: WAL buffers are full too often|<p>The WAL buffers are exhausted more than {$PG.WAL.BUFFERS_FULL.MAX.WARN} times per second, so backends have to flush WAL themselves instead of doing useful work.<br>Increasing `wal_buffers` usually removes the bottleneck.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.stat.wal.buffers_full.rate[{#SINGLETON}],15m) > {$PG.WAL.BUFFERS_FULL.MAX.WARN}`|Warning||

### LLD rule SLRU metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SLRU metrics discovery|<p>Discovers the `pg_stat_slru` metrics available in PostgreSQL 13 and newer.</p>|Dependent item|pgsql.stat.slru.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SLRU metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SLRU: Get data|<p>Collects the SLRU cache statistics from `pg_stat_slru`:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#MONITORING-PG-STAT-SLRU-VIEW</p>|Zabbix agent|pgsql.stat.slru["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]|
|SLRU: Blocks read total|<p>Total number of blocks read from disk into the SLRU caches since the last statistics reset.</p>|Dependent item|pgsql.stat.slru.blks_read[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].blks_read`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SLRU: Blocks read per second|<p>Number of blocks per second read from disk into the SLRU caches.</p>|Dependent item|pgsql.stat.slru.blks_read.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].blks_read`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|SLRU: Blocks hit total|<p>Total number of blocks found in the SLRU caches since the last statistics reset.</p>|Dependent item|pgsql.stat.slru.blks_hit[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].blks_hit`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SLRU: Blocks written total|<p>Total number of dirty SLRU blocks written to disk since the last statistics reset.</p>|Dependent item|pgsql.stat.slru.blks_written[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].blks_written`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SLRU: Cache hit ratio|<p>Percentage of the SLRU cache lookups served from memory, over all caches.</p><p>A dropping ratio means the transaction status and multixact caches no longer fit, which shows up as extra disk reads under high concurrency.</p>|Dependent item|pgsql.stat.slru.hit_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for SLRU metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: SLRU: Cache hit ratio too low|<p>The SLRU caches serve less than {$PG.SLRU.CACHE.MIN.WARN}% of their lookups from memory. Check the disk load and the concurrency of transactions using multixacts (`SELECT ... FOR SHARE`, subtransactions).</p>|`max(/PostgreSQL by Zabbix agent/pgsql.stat.slru.hit_ratio[{#SINGLETON}],5m) < {$PG.SLRU.CACHE.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: SLRU: Cache hit ratio is critically low</li></ul>|
|PostgreSQL: SLRU: Cache hit ratio is critically low|<p>The SLRU caches serve less than {$PG.SLRU.CACHE.MIN.HIGH}% of their lookups from memory, so the instance is doing significant extra I/O for transaction status lookups.</p>|`max(/PostgreSQL by Zabbix agent/pgsql.stat.slru.hit_ratio[{#SINGLETON}],5m) < {$PG.SLRU.CACHE.MIN.HIGH}`|High||

### LLD rule Replication slots metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication slots metrics discovery|<p>Discovers the `pg_replication_slots` metrics available in PostgreSQL 14 and newer.</p>|Dependent item|pgsql.replication.slots.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Replication slots metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication slots: Get data|<p>Collects the state of the replication slots from `pg_replication_slots`:</p><p>https://www.postgresql.org/docs/current/view-pg-replication-slots.html</p>|Zabbix agent|pgsql.replication.slots["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]|
|Replication slots: Total|<p>Total number of replication slots.</p>|Dependent item|pgsql.replication.slots.total[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_total`</p></li></ul>|
|Replication slots: Active|<p>Number of replication slots currently in use by a connected consumer.</p>|Dependent item|pgsql.replication.slots.active[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_active`</p></li></ul>|
|Replication slots: Inactive|<p>Number of replication slots without a connected consumer.</p>|Dependent item|pgsql.replication.slots.inactive[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_inactive`</p></li></ul>|
|Replication slots: Worst slot lag|<p>Amount of WAL the most lagging slot has not confirmed yet. Reported as 0 on a standby, where the current WAL position is not defined.</p>|Dependent item|pgsql.replication.slots.worst_lag[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.worst_slot_lag_bytes`</p></li></ul>|
|Replication slots: Min safe WAL size|<p>Amount of WAL that can still be written before the slot closest to the limit starts losing the data it needs.</p><p>Turns negative once that slot is past its safety margin: the WAL it needs is only kept until the next checkpoint.</p><p>Always 0 when `max_slot_wal_keep_size` is not limited.</p>|Dependent item|pgsql.replication.slots.min_safe_wal_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.min_safe_wal_size`</p></li></ul>|
|Replication slots: Inactive retaining WAL|<p>Number of inactive replication slots that still hold WAL segments.</p><p>Such slots prevent WAL cleanup and are the usual cause of a slowly filling disk on the primary.</p>|Dependent item|pgsql.replication.slots.inactive_retaining[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.inactive_retaining_slots`</p></li></ul>|

### Trigger prototypes for Replication slots metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Replication slots: Inactive slots are retaining WAL|<p>Inactive replication slots are holding WAL segments. Check whether the consumers are gone for good and drop the slots that are no longer needed.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.replication.slots.inactive_retaining[{#SINGLETON}],5m) > {$PG.REPLICATION.SLOTS.RETAINING.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Replication slots: Too many inactive slots are retaining WAL</li></ul>|
|PostgreSQL: Replication slots: Too many inactive slots are retaining WAL|<p>Several inactive replication slots are holding WAL segments, which can fill the disk and stop the instance from accepting writes. Drop the slots that are no longer needed.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.replication.slots.inactive_retaining[{#SINGLETON}],5m) > {$PG.REPLICATION.SLOTS.RETAINING.MAX.HIGH}`|High||

### LLD rule Subscription discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Subscription discovery|<p>Discovers the logical replication subscriptions of the instance.</p>|Dependent item|pgsql.subscription.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Subscription discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Subscription [{#SUBNAME}]: Get subscription stats|<p>Subscription stats of the "{#SUBNAME}" subscription.</p>|Dependent item|pgsql.subscription.get_metrics["{#SUBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.subname=="{#SUBNAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Subscription [{#SUBNAME}]: Apply errors|<p>Number of errors that occurred while applying the changes received from the publisher, since the last statistics reset.</p>|Dependent item|pgsql.subscription.apply_errors["{#SUBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.apply_error_count`</p></li></ul>|
|Subscription [{#SUBNAME}]: Sync errors|<p>Number of errors that occurred during the initial data copy of the subscribed tables, since the last statistics reset.</p>|Dependent item|pgsql.subscription.sync_errors["{#SUBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sync_error_count`</p></li></ul>|
|Subscription [{#SUBNAME}]: Statistics reset time|<p>Time of the last reset of the subscription statistics. The error counters above are counted from this moment.</p>|Dependent item|pgsql.subscription.reset_time["{#SUBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats_reset`</p></li><li><p>Discard unchanged with heartbeat: `7d`</p></li></ul>|

### Trigger prototypes for Subscription discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Subscription [{#SUBNAME}]: Errors while applying changes|<p>The subscription "{#SUBNAME}" fails to apply the changes it receives, so the subscriber data is drifting away from the publisher. Check the subscription worker errors in the PostgreSQL log.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.subscription.apply_errors["{#SUBNAME}"],5m) > {$PG.SUBSCRIPTION.ERRORS.MAX.WARN:"{#SUBNAME}"}`|Warning||
|PostgreSQL: Subscription [{#SUBNAME}]: Errors while synchronizing tables|<p>The initial table synchronization of the subscription "{#SUBNAME}" keeps failing, so the subscribed tables never reach a consistent state. Check the table sync worker errors in the PostgreSQL log.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.subscription.sync_errors["{#SUBNAME}"],5m) > {$PG.SUBSCRIPTION.ERRORS.MAX.WARN:"{#SUBNAME}"}`|Warning||

### LLD rule I/O metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O metrics discovery|<p>Discovers the `pg_stat_io` metrics available in PostgreSQL 16 and newer.</p>|Dependent item|pgsql.stat.io.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for I/O metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O: Get data|<p>Collects the I/O activity of the instance from `pg_stat_io`:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#MONITORING-PG-STAT-IO-VIEW</p>|Zabbix agent|pgsql.stat.io["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]|
|I/O: Reads per second|<p>Number of read operations per second the instance issues to the storage. Reads mean the data was not found in the shared buffers.</p>|Dependent item|pgsql.stat.io.reads.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.reads`</p></li><li>Change per second</li></ul>|
|I/O: Writes per second|<p>Number of write operations per second the instance issues to the storage.</p>|Dependent item|pgsql.stat.io.writes.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.writes`</p></li><li>Change per second</li></ul>|
|I/O: Read per second|<p>Amount of data per second the instance reads from the storage.</p>|Dependent item|pgsql.stat.io.read_bytes.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.read_bytes`</p></li><li>Change per second</li></ul>|
|I/O: Written per second|<p>Amount of data per second the instance writes to the storage.</p>|Dependent item|pgsql.stat.io.write_bytes.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write_bytes`</p></li><li>Change per second</li></ul>|
|I/O: Fsyncs per second|<p>Number of `fsync` calls per second issued by the instance.</p><p>Most of them are normally done by the checkpointer, so a high rate from other backends points at a checkpoint or WAL configuration problem.</p>|Dependent item|pgsql.stat.io.fsyncs.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fsyncs`</p></li><li>Change per second</li></ul>|
|I/O: Time spent per second|<p>Time per second the instance spends waiting for reads and writes to complete.</p><p>Requires `track_io_timing` to be enabled, otherwise it stays at zero.</p>|Dependent item|pgsql.stat.io.time.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.io_time_ms`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|I/O: Buffer hit ratio|<p>Percentage of the block requests served from the shared buffers instead of the storage, since the last statistics reset.</p><p>A low value means the working set does not fit into `shared_buffers`.</p>|Dependent item|pgsql.stat.io.hit_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for I/O metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: I/O: Buffer hit ratio too low|<p>Less than {$PG.IO.CACHE.MIN.WARN}% of the block requests are served from the shared buffers, so the instance keeps going to the storage for data. Consider increasing `shared_buffers` or reviewing the queries that read the most data.</p>|`max(/PostgreSQL by Zabbix agent/pgsql.stat.io.hit_ratio[{#SINGLETON}],15m) < {$PG.IO.CACHE.MIN.WARN}`|Warning||

### LLD rule Schema discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Schema discovery|<p>Discovers the schemas of the "{$PG.DATABASE}" database, except the system ones.</p>|Dependent item|pgsql.relation.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Schema discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Schema [{#SCHEMA.NAME}]: Size|<p>Total on-disk size of the tables of the schema "{#SCHEMA.NAME}", including their indexes and TOAST data.</p>|Dependent item|pgsql.relation.size["{#SCHEMA.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="{#SCHEMA.NAME}")].total_bytes.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Schema [{#SCHEMA.NAME}]: Number of tables|<p>Number of tables in the schema "{#SCHEMA.NAME}", partitioned tables included.</p>|Dependent item|pgsql.relation.tables["{#SCHEMA.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="{#SCHEMA.NAME}")].table_count.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### LLD rule Tablespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tablespace discovery|<p>Discovers the tablespaces of the instance, including the built-in `pg_default` and `pg_global`.</p>|Dependent item|pgsql.tablespace.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Tablespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tablespace [{#TABLESPACE.NAME}]: Get tablespace data|<p>Data of the tablespace "{#TABLESPACE.NAME}".</p>|Dependent item|pgsql.tablespace.get_metrics["{#TABLESPACE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="{#TABLESPACE.NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Size|<p>Total size of the files stored in the tablespace "{#TABLESPACE.NAME}".</p>|Dependent item|pgsql.tablespace.size["{#TABLESPACE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.size_bytes`</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Location|<p>Directory the tablespace "{#TABLESPACE.NAME}" is stored in. Empty for the built-in `pg_default` and `pg_global` tablespaces, which live in the data directory.</p>|Dependent item|pgsql.tablespace.location["{#TABLESPACE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.location`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Owner|<p>Owner of the tablespace "{#TABLESPACE.NAME}".</p>|Dependent item|pgsql.tablespace.owner["{#TABLESPACE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.owner`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Tablespace discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size is too big|<p>The tablespace "{#TABLESPACE.NAME}" has grown beyond the expected size. Check the storage capacity and the growth rate before it becomes a problem.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.tablespace.size["{#TABLESPACE.NAME}"],5m) > {$PG.TABLESPACE.SIZE.MAX.WARN:"{#TABLESPACE.NAME}"}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size is critically big</li></ul>|
|PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size is critically big|<p>The tablespace "{#TABLESPACE.NAME}" is close to exhausting its storage. Free up space or move relations to another tablespace - a full disk stops the instance from accepting writes.</p>|`min(/PostgreSQL by Zabbix agent/pgsql.tablespace.size["{#TABLESPACE.NAME}"],5m) > {$PG.TABLESPACE.SIZE.MAX.HIGH:"{#TABLESPACE.NAME}"}`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

