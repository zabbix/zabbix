
# PostgreSQL by ODBC

## Overview

This template is designed for the effortless deployment of PostgreSQL monitoring by Zabbix via ODBC and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- PostgreSQL 11–18

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create the PostgreSQL user for monitoring (`<password>` at your discretion) and inherit permissions from the default role `pg_monitor`:

```sql
CREATE USER zbx_monitor WITH PASSWORD '<PASSWORD>' INHERIT;
GRANT pg_monitor TO zbx_monitor;
```

2. Edit the `pg_hba.conf` configuration file to allow TCP connections for the user `zbx_monitor`. For example, you could add one of the following rows to allow local connections from the same host:

```bash
  # TYPE    DATABASE    USER         ADDRESS       METHOD
  host      all         zbx_monitor  localhost     trust
  host      all         zbx_monitor  127.0.0.1/32  md5
  host      all         zbx_monitor  ::1/128       scram-sha-256
```

For more information, please consult [PostgreSQL documentation](https://www.postgresql.org/docs/current/auth-pg-hba-conf.html).

3. Install the PostgreSQL ODBC driver. Consult [Zabbix documentation](https://www.zabbix.com/documentation/8.0/manual/config/items/itemtypes/odbc_checks/) for details on ODBC checks and [recommended parameters](https://www.zabbix.com/documentation/8.0/manual/config/items/itemtypes/odbc_checks/unixodbc_postgresql).

4. Set up the connection string with the `{$PG.CONNSTRING.ODBC}` macro. The minimum required parameters are:

- `Driver=` - set the name of the driver which will be used for monitoring (from the `odbcinst.ini` file) or specify the path to the driver file (for example, `/usr/lib64/psqlodbcw.so`);
- `Servername=` - set the host name or IP address of the PostgreSQL instance;
- `Port=` - adjust the port number if needed.

**Note:** If you want to use SSL/TLS encryption to protect communications with the remote PostgreSQL instance, you can also specify encryption parameters here.

It is assumed that you set up the PostgreSQL instance to work in the desired encryption mode. Consult [PostgreSQL documentation](https://www.postgresql.org/docs/current/ssl-tcp.html) for details.

For example, to enable required encryption in transport mode without identity checks, the connection string could look like this (replace `<instanceip>` with the address of the PostgreSQL instance):

```
Servername=<instanceip>;Port=5432;Driver=/usr/lib64/psqlodbcw.so;SSLmode=require
```

5. Set the password that you specified in step 1 in the macro `{$PG.PASSWORD}`.

**IMPORTANT!**
- Ensure `pg_stat_statements` is installed. Add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.
- Ensure logical replication is enabled. Set `wal_level=logical`, `max_replication_slots` >= 1, `max_wal_senders` >= 1 in `postgresql.conf`.
- PostgreSQL 18 requires `track_io_timing = on` in `postgresql.conf` or via `ALTER SYSTEM`. Otherwise, this metric will always be `0`.
- Logical replication subscription metrics are supported for PostgreSQL 15–18 and are designed for subscriber databases with existing subscriptions. Unsupported or missing values will be discarded.
- The `PostgreSQL: Subscription` dashboard page is intended for PG subscriber servers.
- The `PostgreSQL: Replication` dashboard page is intended for PG publisher servers.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PG.AUTOVAC.ACTIVE.WORKERS.WARN}|<p>Warning threshold of active autovacuum workers for trigger expression.</p>|`5`|
|{$PG.AUTOVAC.IDLE.WORKERS.WARN}|<p>Warning threshold of idle autovacuum workers for trigger expression.</p>|`10`|
|{$PG.IO.CACHE.WARN}|<p>Warning threshold of I/O buffer hit ratio, in percent, for trigger expression.</p>|`98`|
|{$PG.IO.CACHE.AVG}|<p>Average threshold of I/O buffer hit ratio, in percent, for trigger expression.</p>|`97`|
|{$PG.IO.CACHE.HIGH}|<p>High threshold of I/O buffer hit ratio, in percent, for trigger expression.</p>|`96`|
|{$PG.SLRU.CACHE.WARN}|<p>Warning threshold of SLRU buffer hit ratio, in percent, for trigger expression.</p>|`98`|
|{$PG.SLRU.CACHE.AVG}|<p>Average threshold of SLRU buffer hit ratio, in percent, for trigger expression.</p>|`97`|
|{$PG.SLRU.CACHE.HIGH}|<p>High threshold of SLRU buffer hit ratio, in percent, for trigger expression.</p>|`95`|
|{$PG.HEALTH.CACHE.WARN}|<p>Warning threshold of [HEALTH] buffer hit ratio, in percent, for trigger expression.</p>|`96`|
|{$PG.HEALTH.CACHE.AVG}|<p>Average threshold of [HEALTH] buffer hit ratio, in percent, for trigger expression.</p>|`94`|
|{$PG.HEALTH.CACHE.HIGH}|<p>High threshold of [HEALTH] buffer hit ratio, in percent, for trigger expression.</p>|`92`|
|{$PG.CONNECTION.COUNT.WARN}|<p>Warning threshold of current connections, in percent, for trigger expression.</p>|`70`|
|{$PG.CONNECTION.COUNT.AVG}|<p>Average state of current connections, in percent, for trigger expression.</p>|`85`|
|{$PG.CONNECTION.COUNT.HIGH}|<p>High state of current connections, in percent, for trigger expression.</p>|`90`|
|{$PG.IDLE.WARN}|<p>Warning threshold of idle connections for trigger expression.</p>|`1`|
|{$PG.IDLE.AVG}|<p>Average state of idle connections for trigger expression.</p>|`2`|
|{$PG.IDLE.HIGH}|<p>High state of idle connections for trigger expression.</p>|`3`|
|{$PG.WAITING.WARN}|<p>Warning threshold of waiting connections for trigger expression.</p>|`1`|
|{$PG.WAITING.AVG}|<p>Average state of waiting connections for trigger expression.</p>|`2`|
|{$PG.WAITING.HIGH}|<p>High state of waiting connections for trigger expression.</p>|`3`|
|{$PG.IDLE.TRANSACTION.WARN}|<p>Warning threshold of idle in transaction connections for trigger expression.</p>|`1`|
|{$PG.IDLE.TRANSACTION.AVG}|<p>Average state of idle in transaction connections for trigger expression.</p>|`2`|
|{$PG.IDLE.TRANSACTION.HIGH}|<p>High state of idle in transaction connections for trigger expression.</p>|`3`|
|{$PG.IDLE.TRANSACTION.ABORTED.WARN}|<p>Warning threshold of idle in transaction (aborted) connections for trigger expression.</p>|`1`|
|{$PG.IDLE.TRANSACTION.ABORTED.AVG}|<p>Average state of idle in transaction (aborted) connections for trigger expression.</p>|`2`|
|{$PG.IDLE.TRANSACTION.ABORTED.HIGH}|<p>High state of idle in transaction (aborted) connections for trigger expression.</p>|`3`|
|{$PG.REPLICATION.SLOTS.RETAINING.AVG}|<p>Average threshold for slots retaining WAL affecting replication load.</p>|`1`|
|{$PG.REPLICATION.SLOTS.RETAINING.HIGH}|<p>High threshold for slots retaining WAL affecting replication load.</p>|`2`|
|{$PG.SUBSCRIPTION.TOTAL.ERROR.AVG}|<p>Average threshold of total subscription errors (sync + apply) per interval.</p>|`4`|
|{$PG.SUBSCRIPTION.TOTAL.ERROR.HIGH}|<p>High threshold of total subscription errors (sync + apply) per interval.</p>|`6`|
|{$PG.SUBSCRIPTION.APPLY.ERROR.AVG}|<p>Average threshold of apply errors in subscription per interval.</p>|`2`|
|{$PG.SUBSCRIPTION.APPLY.ERROR.HIGH}|<p>High threshold of apply errors in subscription per interval.</p>|`3`|
|{$PG.SUBSCRIPTION.SYNC.ERROR.AVG}|<p>Average threshold of sync errors in subscription per interval.</p>|`2`|
|{$PG.SUBSCRIPTION.SYNC.ERROR.HIGH}|<p>High threshold of sync errors in subscription per interval.</p>|`3`|
|{$PG.LRQ.TIME.AVG}|<p>Average threshold for longest running query time, in seconds, for trigger expression.</p>|`30`|
|{$PG.LRQ.TIME.HIGH}|<p>High threshold for longest running query time, in seconds, for trigger expression.</p>|`120`|
|{$PG.DEADLOCKS.MAX.WARN}|<p>Maximum number of detected deadlocks for trigger expression.</p>|`0`|
|{$PG.CONFLICTS.MAX.WARN}|<p>Maximum number of recovery conflicts for trigger expression.</p>|`0`|
|{$PG.QUERY_EXECUTION_TIME.MAX.WARN}|<p>Execution time limit for slow query count.</p>|`30`|
|{$PG.SLOW_QUERIES.MAX.WARN}|<p>Slow query count threshold for trigger expression.</p>|`5`|
|{$PG.DATABASE.SIZE.WARN}|<p>Warning threshold of database size, in bytes, for trigger expression.</p>|`5000000000`|
|{$PG.DATABASE.SIZE.AVG}|<p>Average threshold of database size, in bytes, for trigger expression.</p>|`10000000000`|
|{$PG.DATABASE.SIZE.HIGH}|<p>High threshold of database size, in bytes, for trigger expression.</p>|`15000000000`|
|{$PG.TABLESPACE.SIZE.WARN}|<p>Warning threshold of tablespace size, in bytes, for trigger expression.</p>|`5000000000`|
|{$PG.TABLESPACE.SIZE.AVG}|<p>Average state of tablespace size, in bytes, for trigger expression.</p>|`10000000000`|
|{$PG.TABLESPACE.SIZE.HIGH}|<p>High state of tablespace size, in bytes,  for trigger expression.</p>|`15000000000`|
|{$PG.XID_ACTIVE.WARN}|<p>Warning threshold for oldest active XID age to trigger alert (prevents long-running transactions from delaying cleanup).</p>|`5000`|
|{$PG.XID_ACTIVE.AVG}|<p>Average threshold for oldest active XID age to trigger alert (prevents long-running transactions from delaying cleanup).</p>|`15000`|
|{$PG.XID_ACTIVE.HIGH}|<p>High threshold for oldest active XID age to trigger alert (prevents long-running transactions from delaying cleanup).</p>|`30000`|
|{$PG.XID_MAX.WARN}|<p>Warning threshold for maximum XID age to trigger alert (prevents wraparound).</p>|`2000000`|
|{$PG.XID_MAX.AVG}|<p>Average threshold for maximum XID age to trigger alert (prevents wraparound).</p>|`3500000`|
|{$PG.XID_MAX.HIGH}|<p>High threshold for maximum XID age to trigger alert (prevents wraparound).</p>|`18000000`|
|{$PG.CONNSTRING.ODBC}|<p>Connection string for PostgreSQL instance.</p>|`Macro too long. Please see the template.`|
|{$PG.USER}|<p>PostgreSQL username.</p>|`zbx_monitor`|
|{$PG.PASSWORD}|<p>PostgreSQL user password.</p>||
|{$PG.DATABASE}|<p>Default PostgreSQL database for connection.</p>|`postgres`|
|{$PG.LLD.FILTER.DBNAME.MATCHES}|<p>Filter for PostgreSQL database discovery by name to include.</p>|`.*`|
|{$PG.LLD.FILTER.DBNAME.NOT_MATCHES}|<p>Filter for PostgreSQL database discovery by name to exclude.</p>|`^$`|
|{$PG.LLD.FILTER.APPLICATION.MATCHES}|<p>Filter for PostgreSQL application discovery by name to include.</p>|`.*`|
|{$PG.LLD.FILTER.APPLICATION.NOT_MATCHES}|<p>Filter for PostgreSQL application discovery by name to exclude.</p>|`^$`|
|{$PG.LLD.FILTER.TABLESPACE.DEFAULT.MATCHES}|<p>Regex filter applied to tablespace `is_default` value (true/false).</p>|`.*`|
|{$PG.LLD.FILTER.TABLESPACE.DEFAULT.NOT_MATCHES}|<p>Exclude regex filter applied to tablespace `is_default` value (true/false).</p>|`^$`|
|{$PG.LLD.FILTER.TABLESPACE.NAME.MATCHES}|<p>Filter for PostgreSQL tablespace discovery by name.</p>|`.*`|
|{$PG.LLD.FILTER.TABLESPACE.NAME.NOT_MATCHES}|<p>Exclude filter for PostgreSQL tablespace discovery by name.</p>|`^$`|
|{$PG.LLD.FILTER.SCHEMA.MATCHES}|<p>Filter for PostgreSQL schema discovery by name.</p>|`.*`|
|{$PG.LLD.FILTER.SCHEMA.NOT_MATCHES}|<p>Exclude filter for PostgreSQL schema discovery by name.</p>|`^$`|
|{$PG.LLD.FILTER.SUBSCRIPTION.MATCHES}|<p>Filter for PostgreSQL subscription discovery by name.</p>|`.*`|
|{$PG.LLD.FILTER.SUBSCRIPTION.NOT_MATCHES}|<p>Exclude filter for PostgreSQL subscription discovery by name.</p>|`^$`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get archive|<p>Collects archive status metrics.</p>|Database monitor|db.odbc.select[pgsql.archive.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Get dbstat inventory|<p>Collects all metrics from `pg_stat_database` per database:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p>|Database monitor|db.odbc.select[pgsql.dbstat.inventory.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Get dbstat sum|<p>Collects all metrics from `pg_stat_database` as sums for all databases:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW</p>|Database monitor|db.odbc.select[pgsql.dbstat.sum.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Get connections sum|<p>Collects all metrics from `pg_stat_activity`:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ACTIVITY-VIEW</p>|Database monitor|db.odbc.select[pgsql.connections.sum.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Get WAL|<p>Collects write-ahead log (WAL) metrics.</p>|Database monitor|db.odbc.select[pgsql.wal.stat.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Get locks inventory|<p>Collects all metrics from `pg_locks` per database:</p><p>https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-TABLES</p>|Database monitor|db.odbc.select[pgsql.locks.inventory.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Get replication inventory|<p>Collects metrics from `pg_stat_replication`, which contains information about the WAL sender process, showing statistics about replication to that sender's connected standby server.</p>|Database monitor|db.odbc.select[pgsql.replication.inventory.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get relation inventory|<p>Collects raw relation size information from the primary core database.</p>|Database monitor|db.odbc.select[pgsql.relation.inventory.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get queries inventory|<p>Collects all metrics by query execution time.</p>|Database monitor|db.odbc.select[pgsql.queries.inventory.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Build: Get data|<p>Retrieves PostgreSQL version and build details, including platform, compiler, and package information.</p>|Database monitor|db.odbc.select[pgsql.version.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Build: Version|<p>PostgreSQL version (major.minor).</p>|Dependent item|pgsql.version.major.minor<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pg_version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Build: Package info|<p>Distribution / package build information.</p>|Dependent item|pgsql.version.package<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.package_info`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Build: Platform|<p>Build platform / architecture.</p>|Dependent item|pgsql.version.platform<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.platform`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Build: Compiler|<p>Compiler used to build PostgreSQL.</p>|Dependent item|pgsql.version.compiler<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.compiler`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Build: Compiler package info|<p>Compiler package / distribution info.</p>|Dependent item|pgsql.version.compiler.pkg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.compiler_pkg`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Build: Compiler version|<p>Compiler version number.</p>|Dependent item|pgsql.version.compiler.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.compiler_version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Build: Word size|<p>PostgreSQL word size (32-bit / 64-bit).</p>|Dependent item|pgsql.version.wordsize<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.word_size`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Build: Version (numeric)|<p>Reports the version number of the server as an integer.</p>|Database monitor|db.odbc.select[pgsql.version.num,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Health: Get data|<p>Collects raw PostgreSQL health metrics.</p>|Database monitor|db.odbc.select[pgsql.health.snapshot.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Health: Max XID age|<p>Current age of the oldest transaction ID (XID) in the database as a numeric count, used to detect wraparound risk.</p>|Dependent item|pgsql.health.max.xid.age<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max_xid_age`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Health: Max XID, in %|<p>Max XID age as % of wraparound.</p>|Dependent item|pgsql.health.max.xid.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max_xid_percent`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Health: Waiting locks|<p>Number of sessions currently waiting on locks, indicating blocking or contention.</p>|Dependent item|pgsql.health.waiting.locks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting_locks`</p></li></ul>|
|Health: Active connections|<p>Number of connections currently executing queries.</p>|Dependent item|pgsql.health.active.connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_connections`</p></li></ul>|
|Health: Total connections (active/inactive)|<p>Total number of current client connections to the database.</p>|Dependent item|pgsql.health.total.connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_connections`</p></li></ul>|
|Health: Deadlocks|<p>Total number of deadlocks detected across all databases.</p>|Dependent item|pgsql.health.deadlocks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlocks`</p></li></ul>|
|Health: Buffer hit ratio|<p>Percentage of data served from memory cache. </p><p>Low values indicate more disk I/O and potential performance pressure.</p>|Dependent item|pgsql.health.cache.hit.ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cache_hit_ratio`</p></li></ul>|
|Health: Autovacuum active|<p>Number of autovacuum processes currently running in the database.</p>|Dependent item|pgsql.health.autovacuum.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.autovacuum.active`</p></li></ul>|
|Health: Autovacuum idle|<p>Number of autovacuum workers currently idle in the database.</p>|Dependent item|pgsql.health.autovacuum.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.autovacuum.idle`</p></li></ul>|
|Get subscription inventory|<p>Shows `apply errors`, `sync errors`, and `last reset time` for each subscription.</p><p>IMPORTANT!</p><p>Logical replication subscription metrics are supported for PostgreSQL 15–18 and are designed for subscriber databases with existing subscriptions. Unsupported or missing values will be discarded.</p>|Database monitor|db.odbc.select[pgsql.subscription.inventory.get{,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get tablespace inventory|<p>Collects raw tablespace inventory JSON.</p>|Database monitor|db.odbc.select[pgsql.tablespace.inventory.get{,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Build: Version validator|<p>Validates the PostgreSQL version and sets version macros for LLDs.</p>|Dependent item|pgsql.version.validator<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|WAL: Bytes written|<p>WAL write, in bytes.</p>|Dependent item|pgsql.wal.write<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.write`</p></li><li>Change per second</li></ul>|
|WAL: Bytes received|<p>WAL receive, in bytes.</p>|Dependent item|pgsql.wal.receive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.receive`</p></li><li>Change per second</li></ul>|
|WAL: Segments count|<p>Number of WAL segments.</p>|Dependent item|pgsql.wal.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count`</p></li></ul>|
|Archive: Count of archived files|<p>Count of archived files.</p>|Dependent item|pgsql.archive.count_archived_files<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.archived_count`</p></li></ul>|
|Archive: Count of failed attempts to archive files|<p>Count of failed attempts to archive files.</p>|Dependent item|pgsql.archive.failed_trying_to_archive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.failed_count`</p></li></ul>|
|Archive: Count of files in archive_status need to archive|<p>Count of files to archive.</p>|Dependent item|pgsql.archive.count_files_to_archive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count_files`</p></li></ul>|
|Archive: Size of files need to archive|<p>Size of files to archive.</p>|Dependent item|pgsql.archive.size_files_to_archive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.size_files`</p></li></ul>|
|Dbstat: Blocks read time|<p>Time spent reading data file blocks by backends.</p>|Dependent item|pgsql.dbstat.sum.blk_read_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_read_time`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Dbstat: Blocks write time|<p>Time spent writing data file blocks by backends.</p>|Dependent item|pgsql.dbstat.sum.blk_write_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Dbstat: Committed transactions per second|<p>Number of transactions that have been committed per second.</p>|Dependent item|pgsql.dbstat.sum.xact_commit.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_commit`</p></li><li>Change per second</li></ul>|
|Dbstat: Conflicts per second|<p>Number of queries canceled per second due to conflicts with recovery (conflicts occur only on standby servers; see `pg_stat_database_conflicts` for details).</p>|Dependent item|pgsql.dbstat.sum.conflicts.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conflicts`</p></li><li>Change per second</li></ul>|
|Dbstat: Deadlocks per second|<p>Number of deadlocks detected per second.</p>|Dependent item|pgsql.dbstat.sum.deadlocks.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlocks`</p></li><li>Change per second</li></ul>|
|Dbstat: Disk blocks read per second|<p>Number of disk blocks read per second.</p>|Dependent item|pgsql.dbstat.sum.blks_read.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_read`</p></li><li>Change per second</li></ul>|
|Dbstat: Hit blocks read per second|<p>Number of times per second disk blocks were found in the buffer cache.</p>|Dependent item|pgsql.dbstat.sum.blks_hit.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_hit`</p></li><li>Change per second</li></ul>|
|Dbstat: Number temp bytes per second|<p>Total amount of data written per second to temporary files by queries.</p>|Dependent item|pgsql.dbstat.sum.temp_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_bytes`</p></li><li>Change per second</li></ul>|
|Dbstat: Temp files created per second|<p>Number of temporary files created by queries per second.</p>|Dependent item|pgsql.dbstat.sum.temp_files.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_files`</p></li><li>Change per second</li></ul>|
|Dbstat: Rollbacks per second|<p>Number of transactions that have been rolled back per second.</p>|Dependent item|pgsql.dbstat.sum.xact_rollback.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_rollback`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows deleted per second|<p>Number of rows deleted by queries per second.</p>|Dependent item|pgsql.dbstat.sum.tup_deleted.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tup_deleted`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows fetched per second|<p>Number of rows fetched by queries per second.</p>|Dependent item|pgsql.dbstat.sum.tup_fetched.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tup_fetched`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows inserted per second|<p>Number of rows inserted by queries per second.</p>|Dependent item|pgsql.dbstat.sum.tup_inserted.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tup_inserted`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows returned per second|<p>Number of rows returned by queries per second.</p>|Dependent item|pgsql.dbstat.sum.tup_returned.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tup_returned`</p></li><li>Change per second</li></ul>|
|Dbstat: Rows updated per second|<p>Number of rows updated by queries per second.</p>|Dependent item|pgsql.dbstat.sum.tup_updated.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tup_updated`</p></li><li>Change per second</li></ul>|
|Dbstat: Backends connected|<p>Number of connected backends.</p>|Dependent item|pgsql.dbstat.sum.numbackends<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numbackends`</p></li></ul>|
|Connections sum: Active|<p>Total number of connections executing a query.</p>|Dependent item|pgsql.connections.sum.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p></li></ul>|
|Connections sum: Fastpath function call|<p>Total number of connections executing a fast-path function.</p>|Dependent item|pgsql.connections.sum.fastpath_function_call<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fastpath_function_call`</p></li></ul>|
|Connections sum: Idle|<p>Total number of connections waiting for a new client command.</p>|Dependent item|pgsql.connections.sum.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li></ul>|
|Connections sum: Idle in transaction|<p>Total number of connections in a transaction state but not executing a query.</p>|Dependent item|pgsql.connections.sum.idle_in_transaction<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction`</p></li></ul>|
|Connections sum: Prepared|<p>Total number of prepared transactions:</p><p>https://www.postgresql.org/docs/current/sql-prepare-transaction.html</p>|Dependent item|pgsql.connections.sum.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prepared`</p></li></ul>|
|Connections sum: Total|<p>Total number of connections.</p>|Dependent item|pgsql.connections.sum.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|Connections sum: Total, in %|<p>Total number of connections, in percent.</p>|Dependent item|pgsql.connections.sum.total_pct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_pct`</p></li></ul>|
|Connections sum: Waiting|<p>Total number of waiting connections:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#WAIT-EVENT-TABLE</p>|Dependent item|pgsql.connections.sum.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting`</p></li></ul>|
|Connections sum: Idle in transaction (aborted)|<p>Total number of connections in a transaction state but not executing a query, and where one of the statements in the transaction caused an error.</p>|Dependent item|pgsql.connections.sum.idle_in_transaction_aborted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction_aborted`</p></li></ul>|
|Connections sum: Disabled|<p>Total number of disabled connections.</p>|Dependent item|pgsql.connections.sum.disabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disabled`</p></li></ul>|
|Oldest active XID|<p>Age of the oldest active transaction (XID) among live backends.</p>|Database monitor|db.odbc.select[pgsql.oldest.xid,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Cache hit ratio, in %|<p>Cache hit ratio.</p>|Calculated|pgsql.cache.hit|
|Uptime|<p>Time since the server started.</p>|Database monitor|db.odbc.select[pgsql.uptime,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Replication: Lag in bytes|<p>Replication lag with master, in bytes.</p>|Database monitor|db.odbc.select[pgsql.replication.lag.b,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Replication: Lag in seconds|<p>Replication lag with master, in seconds.</p>|Database monitor|db.odbc.select[pgsql.replication.lag.sec,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Replication: Role|<p>Replication role:</p><p>1 - Standby mode</p><p>0 - Master mode</p>|Database monitor|db.odbc.select[pgsql.replication.recovery_role,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Replication: Standby|<p>Number of standby servers.</p>|Database monitor|db.odbc.select[pgsql.replication.count,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Replication: Status|<p>Shows replication streaming state:</p><p>0 - Standby offline</p><p>1 - Standby streaming</p><p>2 - Master node</p>|Database monitor|db.odbc.select[pgsql.replication.status,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|LRQ: Get data|<p>Returns data about the currently active LRQ (Longest Running Query) and its execution duration from `pg_stat_activity`.</p>|Database monitor|db.odbc.select[pgsql.longest.running.query.data.get,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|LRQ: PID|<p>PID of the current LRQ (Longest Running Query).</p>|Dependent item|pgsql.longest.running.query.pid<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pid`</p></li></ul>|
|LRQ: Duration|<p>Duration of the current LRQ (Longest Running Query).</p>|Dependent item|pgsql.longest.running.query.duration<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.duration`</p></li></ul>|
|Ping|<p>Used to test a connection to see if it is alive. Set to `0` if the query is unsuccessful.</p>|Database monitor|db.odbc.select[pgsql.ping,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Version info|<p>PostgreSQL version changed. Verify that the upgrade was intentional and applications remain compatible.</p>|`last(/PostgreSQL by ODBC/pgsql.version.major.minor,#1)<>last(/PostgreSQL by ODBC/pgsql.version.major.minor,#2) and length(last(/PostgreSQL by ODBC/pgsql.version.major.minor))>0`|Info||
|PostgreSQL: Word size info|<p>PostgreSQL word size has changed. Verify that the upgrade or server rebuild was intentional and applications remain compatible.</p>|`last(/PostgreSQL by ODBC/pgsql.version.wordsize,#1)<>last(/PostgreSQL by ODBC/pgsql.version.wordsize,#2) and length(last(/PostgreSQL by ODBC/pgsql.version.wordsize))>0`|Info||
|PostgreSQL: XID age warning|<p>Oldest XID age >= `{$PG.XID_MAX.WARN}`. Check autovacuum activity and monitor long-running transactions.</p>|`min(/PostgreSQL by ODBC/pgsql.health.max.xid.age,5m) >= {$PG.XID_MAX.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: XID age average</li></ul>|
|PostgreSQL: XID age average|<p>Oldest XID age >= `{$PG.XID_MAX.AVG}`. Ensure autovacuum is running properly; investigate long-running transactions.</p>|`min(/PostgreSQL by ODBC/pgsql.health.max.xid.age,5m) >= {$PG.XID_MAX.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: XID age high</li></ul>|
|PostgreSQL: XID age high|<p>Oldest XID age >= `{$PG.XID_MAX.HIGH}`. Immediate action required! Check autovacuum, terminate long-running transactions to prevent wraparound risk.</p>|`min(/PostgreSQL by ODBC/pgsql.health.max.xid.age,5m) >= {$PG.XID_MAX.HIGH}`|High||
|PostgreSQL: Buffer hit warning|<p>Buffer hit ratio <= `{$PG.HEALTH.CACHE.WARN}`%. Slightly below optimal. Monitor I/O and queries.</p>|`max(/PostgreSQL by ODBC/pgsql.health.cache.hit.ratio,5m) <= {$PG.HEALTH.CACHE.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Health: Buffer hit average</li></ul>|
|PostgreSQL: Health: Buffer hit average|<p>Buffer hit ratio <= `{$PG.HEALTH.CACHE.AVG}`%. Low cache efficiency, potential I/O pressure. Investigate frequently used queries.</p>|`max(/PostgreSQL by ODBC/pgsql.health.cache.hit.ratio,5m) <= {$PG.HEALTH.CACHE.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Health: Buffer hit high</li></ul>|
|PostgreSQL: Health: Buffer hit high|<p>Buffer hit ratio <= `{$PG.HEALTH.CACHE.HIGH}`%. Very low cache efficiency. High I/O load expected. Investigate hot queries and disk usage immediately.</p>|`max(/PostgreSQL by ODBC/pgsql.health.cache.hit.ratio,5m) <= {$PG.HEALTH.CACHE.HIGH}`|High||
|PostgreSQL: Autovacuum active workers warning|<p>Number of active autovacuum workers has exceeded the `{$PG.AUTOVAC.ACTIVE.WORKERS.WARN}` threshold for the last 5 minutes.</p>|`min(/PostgreSQL by ODBC/pgsql.health.autovacuum.active,5m) >= {$PG.AUTOVAC.ACTIVE.WORKERS.WARN}`|Warning||
|PostgreSQL: Autovacuum idle workers warning|<p>Number of idle autovacuum workers has exceeded the `{$PG.AUTOVAC.IDLE.WORKERS.WARN}` threshold for the last 5 minutes.</p>|`min(/PostgreSQL by ODBC/pgsql.health.autovacuum.idle,5m) >= {$PG.AUTOVAC.IDLE.WORKERS.WARN}`|Warning||
|PostgreSQL: Idle warning|<p>Number of idle connections >= `{$PG.IDLE.WARN}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.idle,5m) >= {$PG.IDLE.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Idle average</li></ul>|
|PostgreSQL: Idle average|<p>Number of idle connections >= `{$PG.IDLE.AVG}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.idle,5m) >= {$PG.IDLE.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Idle high</li></ul>|
|PostgreSQL: Idle high|<p>Number of idle connections >= `{$PG.IDLE.HIGH}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.idle,5m) >= {$PG.IDLE.HIGH}`|High||
|PostgreSQL: Idle in transaction warning|<p>Number of idle in transaction connections >= `{$PG.IDLE.TRANSACTION.WARN}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.idle_in_transaction,5m) >= {$PG.IDLE.TRANSACTION.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Idle in transaction average</li></ul>|
|PostgreSQL: Idle in transaction average|<p>Number of idle in transaction connections >= `{$PG.IDLE.TRANSACTION.AVG}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.idle_in_transaction,5m) >= {$PG.IDLE.TRANSACTION.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Idle in transaction high</li></ul>|
|PostgreSQL: Idle in transaction high|<p>Number of idle in transaction connections >= `{$PG.IDLE.TRANSACTION.HIGH}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.idle_in_transaction,5m) >= {$PG.IDLE.TRANSACTION.HIGH}`|High||
|PostgreSQL: Connection usage warning|<p>Total connections >= `{$PG.CONNECTION.COUNT.WARN}`%.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.total_pct,5m) >= {$PG.CONNECTION.COUNT.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Connection usage average</li></ul>|
|PostgreSQL: Connection usage average|<p>Total connections >= `{$PG.CONNECTION.COUNT.AVG}`%.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.total_pct,5m) >= {$PG.CONNECTION.COUNT.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Connection usage high</li></ul>|
|PostgreSQL: Connection usage high|<p>Total connections >= `{$PG.CONNECTION.COUNT.HIGH}`%.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.total_pct,5m) >= {$PG.CONNECTION.COUNT.HIGH}`|High||
|PostgreSQL: Waiting connections warning|<p>Number of connections waiting >= `{$PG.WAITING.WARN}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.waiting,5m) >= {$PG.WAITING.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Waiting connections average</li></ul>|
|PostgreSQL: Waiting connections average|<p>Number of connections waiting >= `{$PG.WAITING.AVG}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.waiting,5m) >= {$PG.WAITING.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Waiting connections high</li></ul>|
|PostgreSQL: Waiting connections high|<p>Number of connections waiting >= `{$PG.WAITING.HIGH}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.waiting,5m) >= {$PG.WAITING.HIGH}`|High||
|PostgreSQL: Idle in transaction (aborted) warning|<p>Number of idle in transaction (aborted) connections >= `{$PG.IDLE.TRANSACTION.ABORTED.WARN}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.idle_in_transaction_aborted,5m) >= {$PG.IDLE.TRANSACTION.ABORTED.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Idle in transaction (aborted) average</li></ul>|
|PostgreSQL: Idle in transaction (aborted) average|<p>Number of idle in transaction (aborted) connections >= `{$PG.IDLE.TRANSACTION.ABORTED.AVG}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.idle_in_transaction_aborted,5m) >= {$PG.IDLE.TRANSACTION.ABORTED.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Idle in transaction (aborted) high</li></ul>|
|PostgreSQL: Idle in transaction (aborted) high|<p>Number of idle in transaction (aborted) connections >= `{$PG.IDLE.TRANSACTION.ABORTED.HIGH}`.</p>|`min(/PostgreSQL by ODBC/pgsql.connections.sum.idle_in_transaction_aborted,5m) >= {$PG.IDLE.TRANSACTION.ABORTED.HIGH}`|High||
|PostgreSQL: Oldest active XID warning|<p>Oldest active XID >= `{$PG.XID_ACTIVE.WARN}`. Monitor long transactions.</p>|`min(/PostgreSQL by ODBC/db.odbc.select[pgsql.oldest.xid,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"],15m) >= {$PG.XID_ACTIVE.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Oldest active XID average</li></ul>|
|PostgreSQL: Oldest active XID average|<p>Oldest active XID >= `{$PG.XID_ACTIVE.AVG}`. Check for long-running transactions.</p>|`min(/PostgreSQL by ODBC/db.odbc.select[pgsql.oldest.xid,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"],15m) >= {$PG.XID_ACTIVE.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Oldest active XID high</li></ul>|
|PostgreSQL: Oldest active XID high|<p>Oldest active XID >= `{$PG.XID_ACTIVE.HIGH}`. Investigate immediately.</p>|`min(/PostgreSQL by ODBC/db.odbc.select[pgsql.oldest.xid,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"],15m) >= {$PG.XID_ACTIVE.HIGH}`|High||
|PostgreSQL: Service has been restarted|<p>PostgreSQL uptime is less than 10 minutes.</p>|`last(/PostgreSQL by ODBC/db.odbc.select[pgsql.uptime,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]) < 10m`|Average||
|PostgreSQL: LRQ duration average|<p>Query running longer than `{$PG.LRQ.TIME.AVG}` seconds; check `pg_stat_activity`, optimize or index if needed, and cancel if blocking critical operations.</p>|`last(/PostgreSQL by ODBC/pgsql.longest.running.query.duration) >= {$PG.LRQ.TIME.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: LRQ duration high</li></ul>|
|PostgreSQL: LRQ duration high|<p>Query running longer than `{$PG.LRQ.TIME.HIGH}` seconds; check `pg_stat_activity`, optimize or index if needed, and cancel if blocking critical operations.</p>|`last(/PostgreSQL by ODBC/pgsql.longest.running.query.duration) >= {$PG.LRQ.TIME.HIGH}`|High||
|PostgreSQL: Service is down|<p>Last test of a connection was unsuccessful.</p>|`last(/PostgreSQL by ODBC/db.odbc.select[pgsql.ping,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"])=0`|High||

### LLD rule Workload metrics discovery (v18+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload metrics discovery (v18+)|<p>Discovers `query` metrics in PostgreSQL 18 and above.</p>|Dependent item|pgsql.workload.stats.version.18.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Workload metrics discovery (v18+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload: Get data|<p>Collects top queries from `pg_stat_statements`.</p><p>IMPORTANT!</p><p>Ensure `pg_stat_statements` is installed, add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.</p>|Database monitor|db.odbc.select[pgsql.workload.snapshot.version.18.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workload: PGSS version|<p>Version of `pg_stat_statements` module.</p>|Dependent item|pgsql.workload.pgss.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.extension_version`</p></li></ul>|
|Workload: Average execution|<p>Average query execution time from `pg_stat_statements`.</p>|Dependent item|pgsql.workload.avg.exec.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.execution_time_ms.avg`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Max execution|<p>Slowest query execution time from `pg_stat_statements`.</p>|Dependent item|pgsql.workload.max_exec_time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.execution_time_ms.max`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Temporary blocks written|<p>Number of temporary blocks written to disk (spills).</p>|Dependent item|pgsql.workload.temp.blocks.written.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_io_blocks.written`</p></li></ul>|
|Workload: Temporary blocks read|<p>Number of temporary blocks read from disk (spills).</p>|Dependent item|pgsql.workload.temp.blocks.read.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_io_blocks.read`</p></li></ul>|
|Workload: Temporary blocks total|<p>Total number of temporary blocks read and written to disk.</p>|Calculated|pgsql.workload.temp.blocks.total.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workload: Total calls|<p>Total number of calls executed, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.total.calls.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.total_calls`</p></li></ul>|
|Workload: Rows inserted|<p>Total number of rows inserted, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.rows.inserted.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.inserted`</p></li></ul>|
|Workload: Rows updated|<p>Total number of rows updated, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.rows.updated.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.updated`</p></li></ul>|
|Workload: Rows deleted|<p>Total number of rows deleted, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.rows.deleted.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.deleted`</p></li></ul>|
|Workload: Plan count|<p>Time spent planning each individual query.</p>|Dependent item|pgsql.workload.total.plans.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.plans`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Total plan time|<p>Total time spent planning all queries combined.</p>|Dependent item|pgsql.workload.total.plan.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.total`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Plan time average|<p>Average time per plan execution.</p>|Dependent item|pgsql.workload.avg.plan.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.avg`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Distinct statements|<p>Number of distinct SQL statements tracked in `pg_stat_statements`.</p>|Dependent item|pgsql.workload.distinct.statements.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.distinct_statements`</p></li></ul>|
|Workload: Track level|<p>Track configuration in `pg_stat_statements` (none, top, all, nested).</p>|Dependent item|pgsql.workload.track.level.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.track`</p></li></ul>|
|Workload: Nested track|<p>Track nested configuration in `pg_stat_statements` (on/off).</p>|Dependent item|pgsql.workload.track.nested.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.track_nested`</p></li></ul>|
|Workload: Max statements|<p>Maximum number of statements tracked by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.max.statements.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.max`</p></li></ul>|
|Workload: Min plan time|<p>Minimum time spent planning queries.</p>|Dependent item|pgsql.workload.min.plan.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.min`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Max plan time|<p>Maximum time spent planning queries.</p>|Dependent item|pgsql.workload.max.plan.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.max`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Min execution|<p>Minimum query execution time.</p>|Dependent item|pgsql.workload.min.exec.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.execution_time_ms.min`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|

### LLD rule Workload metrics discovery (v17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload metrics discovery (v17)|<p>Discovers `query` metrics in PostgreSQL 17.</p>|Dependent item|pgsql.workload.stats.version.17.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Workload metrics discovery (v17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload: Get data|<p>Collects top queries from `pg_stat_statements`.</p><p>IMPORTANT!</p><p>Ensure `pg_stat_statements` is installed, add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.</p>|Database monitor|db.odbc.select[pgsql.workload.snapshot.version.17.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workload: PGSS version|<p>Version of `pg_stat_statements`.</p>|Dependent item|pgsql.workload.pgss.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.extension_version`</p></li></ul>|
|Workload: Average execution|<p>Average query execution time from `pg_stat_statements`.</p>|Dependent item|pgsql.workload.avg.exec.time.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.execution_time_ms.avg`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Max execution|<p>Slowest query execution time from `pg_stat_statements`.</p>|Dependent item|pgsql.workload.max.exec.time.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.execution_time_ms.max`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Temporary blocks written|<p>Number of temporary blocks written to disk (spills).</p>|Dependent item|pgsql.workload.temp.blocks.written.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_io_blocks.written`</p></li></ul>|
|Workload: Total calls|<p>Total number of calls executed, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.total.calls.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.total_calls`</p></li></ul>|
|Workload: Rows inserted|<p>Total number of rows inserted, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.rows.inserted.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.inserted`</p></li></ul>|
|Workload: Rows updated|<p>Total number of rows updated, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.rows.updated.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.updated`</p></li></ul>|
|Workload: Rows deleted|<p>Total number of rows deleted, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.rows.deleted.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.deleted`</p></li></ul>|
|Workload: Temporary blocks read|<p>Number of temporary blocks read from disk (spills).</p>|Dependent item|pgsql.workload.temp.blocks.read.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_io_blocks.read`</p></li></ul>|
|Workload: Temporary blocks total|<p>Total number of temporary blocks read and written to disk.</p>|Calculated|pgsql.workload.temp.blocks.total.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workload: Plan count|<p>Number of times each query plan was executed.</p>|Dependent item|pgsql.workload.total.plans.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.plans`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Total plan time|<p>Total time spent planning all queries combined.</p>|Dependent item|pgsql.workload.total.plan.time.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.total`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Plan time average|<p>Average time per plan execution.</p>|Dependent item|pgsql.workload.avg.plan.time.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.avg`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Distinct statements|<p>Number of distinct SQL statements tracked in `pg_stat_statements`.</p>|Dependent item|pgsql.workload.distinct.statements.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.distinct_statements`</p></li></ul>|
|Workload: Track level|<p>Track configuration in`pg_stat_statements` (none, top, all, nested).</p>|Dependent item|pgsql.workload.track.level.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.track`</p></li></ul>|
|Workload: Nested track|<p>Track nested configuration in `pg_stat_statements`(on/off).</p>|Dependent item|pgsql.workload.track.nested.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.track_nested`</p></li></ul>|
|Workload: Max statements|<p>Maximum number of statements tracked by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.max.statements.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.max`</p></li></ul>|

### LLD rule Workload metrics discovery (v15–16)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload metrics discovery (v15–16)|<p>Discovers `query` metrics in PostgreSQL 15 and 16.</p>|Dependent item|pgsql.workload.stats.above.15.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Workload metrics discovery (v15–16)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload: Get data|<p>Collects top queries from `pg_stat_statements`.</p><p>IMPORTANT!</p><p>Ensure `pg_stat_statements` is installed, add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.</p>|Database monitor|db.odbc.select[pgsql.workload.snapshot.above.15.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workload: PGSS version|<p>Version of `pg_stat_statements`.</p>|Dependent item|pgsql.workload.pgss.version.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pgss_version`</p></li></ul>|
|Workload: Average execution|<p>Average query execution time from `pg_stat_statements`.</p>|Dependent item|pgsql.workload.avg_exec_time.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.avg_exec_time_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Max execution|<p>Slowest query execution time from `pg_stat_statements`.</p>|Dependent item|pgsql.workload.max_exec_time.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.max_exec_time_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Temporary blocks written|<p>Number of temporary blocks written to disk (spills).</p>|Dependent item|pgsql.workload.temp.blocks.written.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_blks_written`</p></li></ul>|
|Workload: Total calls|<p>Total number of calls executed, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.total.calls.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.total_calls`</p></li></ul>|
|Workload: Rows inserted|<p>Total number of rows inserted, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.rows.inserted.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_inserted`</p></li></ul>|
|Workload: Rows updated|<p>Total number of rows updated, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.rows.updated.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_updated`</p></li></ul>|
|Workload: Rows deleted|<p>Total number of rows deleted, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.rows.deleted.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_deleted`</p></li></ul>|

### LLD rule Workload metrics discovery (v12)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload metrics discovery (v12)|<p>Discovers `query` metrics in PostgreSQL 12.</p>|Dependent item|pgsql.workload.stats.below.13.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Workload metrics discovery (v12)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload: Get data|<p>Collects top queries from `pg_stat_statements`.</p><p>IMPORTANT!</p><p>Ensure `pg_stat_statements` is installed, add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.</p>|Database monitor|db.odbc.select[pgsql.workload.snapshot.below.13.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workload: PGSS version|<p>Version of `pg_stat_statements`.</p>|Dependent item|pgsql.workload.pgss.version.below.13[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pgss_version`</p></li></ul>|

### LLD rule Workload metrics discovery (v13–14)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload metrics discovery (v13–14)|<p>Discovers `query` metrics in PostgreSQL 13 and 14.</p>|Dependent item|pgsql.workload.stats.below.15.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Workload metrics discovery (v13–14)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload: Get data|<p>Collects top queries from `pg_stat_statements`.</p><p>IMPORTANT!</p><p>Ensure `pg_stat_statements` is installed, add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.</p>|Database monitor|db.odbc.select[pgsql.workload.snapshot.below.15.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workload: PGSS version|<p>Version of `pg_stat_statements`.</p>|Dependent item|pgsql.workload.pgss.version.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pgss_version`</p></li></ul>|
|Workload: Average execution|<p>Average query execution time from `pg_stat_statements`.</p>|Dependent item|pgsql.workload.avg.exec.time.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.avg_exec_time_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Max execution|<p>Slowest query execution time from `pg_stat_statements`.</p>|Dependent item|pgsql.workload.max.exec.time.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.max_exec_time_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Temporary blocks written|<p>Number of temporary blocks written to disk (spills).</p>|Dependent item|pgsql.workload.temp.blocks.written.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_blks_written`</p></li></ul>|
|Workload: Total calls|<p>Total number of calls executed, as reported by `pg_stat_statements`.</p>|Dependent item|pgsql.workload.total.calls.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.total_calls`</p></li></ul>|

### LLD rule Relation schema discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Relation schema discovery|<p>Discovers all PostgreSQL schemas in the primary core database.</p>|Dependent item|pgsql.relation.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Relation schema discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Relation [{#SCHEMA.NAME}]: Size|<p>Total on-disk size of all relations in schema.</p>|Dependent item|pgsql.relation.size[{#SCHEMA.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="{#SCHEMA.NAME}")].total_bytes.first()`</p></li></ul>|
|Relation [{#SCHEMA.NAME}]: Count|<p>Number of tables in schema.</p>|Dependent item|pgsql.relation.tables[{#SCHEMA.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="{#SCHEMA.NAME}")].table_count.first()`</p></li></ul>|

### LLD rule Tablespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tablespace discovery|<p>Discovers tablespaces.</p>|Dependent item|pgsql.tablespace.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Tablespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tablespace [{#TABLESPACE.NAME}]: Get data|<p>Collects tablespace metrics.</p>|Dependent item|pgsql.tablespace.inventory.preprocessing.data.get[{#TABLESPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="{#TABLESPACE.NAME}")].first()`</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Size|<p>Size of the tablespace `{#TABLESPACE.NAME}` in bytes.</p>|Dependent item|pgsql.tablespace.size[{#TABLESPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.size_bytes`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Owner|<p>Owner of the tablespace `{#TABLESPACE.NAME}` (the PostgreSQL role that created it).</p>|Dependent item|pgsql.tablespace.owner[{#TABLESPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.owner`</p></li><li><p>Discard unchanged with heartbeat: `7d`</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Location|<p>Filesystem path for the tablespace `{#TABLESPACE.NAME}`. An empty string indicates the default PostgreSQL data directory.</p>|Dependent item|pgsql.tablespace.location[{#TABLESPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.location`</p></li><li><p>Discard unchanged with heartbeat: `7d`</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Default|<p>Indicates whether the tablespace `{#TABLESPACE.NAME}` is a default system tablespace.</p>|Dependent item|pgsql.tablespace.is_default[{#TABLESPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.is_default`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `7d`</p></li></ul>|

### Trigger prototypes for Tablespace discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size warning|<p>Tablespace `{#TABLESPACE.NAME}` size >= `{$PG.TABLESPACE.SIZE.WARN}` bytes.</p>|`min(/PostgreSQL by ODBC/pgsql.tablespace.size[{#TABLESPACE.NAME}],5m) >= {$PG.TABLESPACE.SIZE.WARN:"{#TABLESPACE.NAME}"}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size average</li></ul>|
|PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size average|<p>Tablespace `{#TABLESPACE.NAME}` size >= `{$PG.TABLESPACE.SIZE.AVG}` bytes.</p>|`min(/PostgreSQL by ODBC/pgsql.tablespace.size[{#TABLESPACE.NAME}],5m) >= {$PG.TABLESPACE.SIZE.AVG:"{#TABLESPACE.NAME}"}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size high</li></ul>|
|PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size high|<p>Tablespace `{#TABLESPACE.NAME}` size >= `{$PG.TABLESPACE.SIZE.HIGH}` bytes.</p>|`min(/PostgreSQL by ODBC/pgsql.tablespace.size[{#TABLESPACE.NAME}],5m) >= {$PG.TABLESPACE.SIZE.HIGH:"{#TABLESPACE.NAME}"}`|High||

### LLD rule Replication slots metrics discovery (v12–14)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication slots metrics discovery (v12–14)|<p>Discovers `replication slots` metrics in PostgreSQL 12 to 14.</p>|Dependent item|pgsql.replication.slots.below_15.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Replication slots metrics discovery (v12–14)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication slots: Get slots|<p>Collects raw replication slot information from `pg_replication_slots`, including activity state, WAL retention, and slot type.</p><p>IMPORTANT!</p><p>Ensure logical replication is enabled: set `wal_level=logical`, `max_replication_slots` >= 1, `max_wal_senders` >= 1 in `postgresql.conf`.</p>|Database monitor|db.odbc.select[pgsql.replication.slots.below.15.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Replication slots: Slots total|<p>Total number of replication slots.</p>|Dependent item|pgsql.replication.slots.total.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_total`</p></li></ul>|
|Replication slots: Slots active|<p>Number of active replication slots.</p>|Dependent item|pgsql.replication.slots.active.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_active`</p></li></ul>|
|Replication slots: Slots inactive|<p>Number of inactive replication slots.</p>|Dependent item|pgsql.replication.slots.inactive.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_inactive`</p></li></ul>|

### LLD rule Replication slots metrics discovery (v15+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication slots metrics discovery (v15+)|<p>Discovers `replication slots` metrics in PostgreSQL 15 and above.</p>|Dependent item|pgsql.replication.slots.above.15.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Replication slots metrics discovery (v15+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication slots: Get slots|<p>Collects raw replication slot information from `pg_replication_slots`, including activity state, WAL retention, and slot type.</p><p>IMPORTANT!</p><p>Ensure logical replication is enabled: set `wal_level=logical`, `max_replication_slots` >= 1, `max_wal_senders` >= 1 in `postgresql.conf`.</p>|Database monitor|db.odbc.select[pgsql.replication.slots.above.15.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Replication slots: Slots total|<p>Total number of replication slots.</p>|Dependent item|pgsql.replication.slots.total.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_total`</p></li></ul>|
|Replication slots: Slots active|<p>Number of active replication slots.</p>|Dependent item|pgsql.replication.slots.active.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_active`</p></li></ul>|
|Replication slots: Slots inactive|<p>Number of inactive replication slots.</p>|Dependent item|pgsql.replication.slots.inactive.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_inactive`</p></li></ul>|
|Replication slots: Max WAL|<p>Maximum amount of WAL retained by any replication slot.</p>|Dependent item|pgsql.replication.slots.max.safe.wal.size.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max_safe_wal_size`</p></li></ul>|
|Replication slots: Worst lag|<p>Maximum replication lag (in bytes) of the worst slot.</p>|Dependent item|pgsql.replication.slots.worst.lag.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.worst_slot_lag_bytes`</p></li></ul>|
|Replication slots: Slots retaining|<p>Number of inactive replication slots that are still retaining WAL.</p>|Dependent item|pgsql.replication.slots.inactive.retaining.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.inactive_retaining_slots`</p></li></ul>|

### Trigger prototypes for Replication slots metrics discovery (v15+)

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Replication slots: Retaining WAL average|<p>Slots retaining WAL >= `{$PG.REPLICATION.SLOTS.RETAINING.AVG}`. Some inactive slots are retaining WAL; monitor to prevent disk growth.</p>|`min(/PostgreSQL by ODBC/pgsql.replication.slots.inactive.retaining.above.15[{#SINGLETON}],5m) >= {$PG.REPLICATION.SLOTS.RETAINING.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Replication slots: Retaining WAL high</li></ul>|
|PostgreSQL: Replication slots: Retaining WAL high|<p>Slots retaining WAL >= `{$PG.REPLICATION.SLOTS.RETAINING.HIGH}`. Inactive slots retaining WAL may cause significant disk usage. Investigate and clean up unnecessary slots immediately.</p>|`min(/PostgreSQL by ODBC/pgsql.replication.slots.inactive.retaining.above.15[{#SINGLETON}],5m) >= {$PG.REPLICATION.SLOTS.RETAINING.HIGH}`|High||

### LLD rule WAL statistics metrics discovery (v14+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL statistics metrics discovery (v14+)|<p>Discovers `WAL statistics` metrics in PostgreSQL 14 and above.</p>|Dependent item|pgsql.wal.stats.above.14.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for WAL statistics metrics discovery (v14+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL statistics: Get data|<p>Collects WAL activity metrics from `pg_stat_wal`.</p>|Database monitor|db.odbc.select[pgsql.wal.stats.above.14.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal`</p></li></ul>|
|WAL statistics: Bytes generated total|<p>Total number of WAL bytes generated since last statistics reset.</p>|Dependent item|pgsql.wal.stats.bytes.total.above.14[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_bytes`</p></li></ul>|
|WAL statistics: Records total|<p>Total number of WAL records generated since last statistics reset.</p>|Dependent item|pgsql.wal.stats.records.above.14[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_records`</p></li></ul>|
|WAL statistics: Buffers full total|<p>Total number of times WAL buffers were full since last statistics reset.</p><p>A non-zero or increasing value indicates WAL buffer pressure and possible performance impact.</p>|Dependent item|pgsql.wal.stats.buffers.full.above.14[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_buffers_full`</p></li></ul>|
|WAL statistics: Reset time|<p>Timestamp of the last reset of WAL statistics.</p>|Dependent item|pgsql.wal.stats.reset.above.14[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats_reset`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `7d`</p></li></ul>|
|WAL statistics: Full page images total|<p>Total number of full page images written to WAL since last statistics reset.</p><p>Higher values increase WAL volume and may indicate frequent checkpoints or cold page writes.</p>|Dependent item|pgsql.wal.stats.fpi.above.14[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_fpi`</p></li></ul>|

### LLD rule SLRU metrics discovery (v13+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SLRU metrics discovery (v13+)|<p>Discovers `SLRU` metrics in PostgreSQL 13 and above.</p>|Dependent item|pgsql.slru.above_13.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SLRU metrics discovery (v13+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SLRU: Get data|<p>Collects SLRU (Simple Least Recently Used) cache statistics from `pg_stat_slru`.</p>|Database monitor|db.odbc.select[pgsql.stat.slru.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|SLRU: Blocks read total|<p>Total number of SLRU (Simple Least Recently Used) blocks read since last statistics reset.</p>|Dependent item|pgsql.slru.blks_read.total[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].blks_read`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SLRU: Blocks hit total|<p>Total number of SLRU (Simple Least Recently Used) blocks found in cache (hits) since last statistics reset.</p>|Dependent item|pgsql.slru.blks_hit.total[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].blks_hit`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SLRU: Blocks read per second|<p>Rate of SLRU (Simple Least Recently Used) blocks read per second.</p>|Dependent item|pgsql.slru.blks_read.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].blks_read`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|SLRU: Buffer hit ratio|<p>Percentage of SLRU (Simple Least Recently Used) blocks served from cache (hits / total blocks).</p><p>Higher means better performance.</p>|Dependent item|pgsql.slru.hit.ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for SLRU metrics discovery (v13+)

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: SLRU: Buffer hit warning|<p>SLRU buffer hit ratio <= `{$PG.SLRU.CACHE.WARN}`%. Slightly below optimal. Monitor I/O and queries.</p>|`max(/PostgreSQL by ODBC/pgsql.slru.hit.ratio[{#SINGLETON}],5m) <= {$PG.SLRU.CACHE.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: SLRU: Buffer hit average</li></ul>|
|PostgreSQL: SLRU: Buffer hit average|<p>SLRU buffer hit ratio <= `{$PG.SLRU.CACHE.AVG}`%. Moderate cache efficiency drop. Investigate I/O patterns and autovacuum activity.</p>|`max(/PostgreSQL by ODBC/pgsql.slru.hit.ratio[{#SINGLETON}],5m) <= {$PG.SLRU.CACHE.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: SLRU: Buffer hit high</li></ul>|
|PostgreSQL: SLRU: Buffer hit high|<p>SLRU buffer hit ratio <= `{$PG.SLRU.CACHE.HIGH}`%. Critical cache inefficiency. High I/O load expected. Investigate disk usage and hot transactions immediately.</p>|`max(/PostgreSQL by ODBC/pgsql.slru.hit.ratio[{#SINGLETON}],5m) <= {$PG.SLRU.CACHE.HIGH}`|High||

### LLD rule Subscription stats metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Subscription stats metrics discovery|<p>Logical replication subscription metrics discovery.</p>|Dependent item|pgsql.subscription.discover<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Subscription stats metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Subscription stats [{#SUBNAME}]: Get data|<p>Preprocessed data for each subscription object array.</p>|Dependent item|pgsql.subscription.data.preprocessing.data.get[{#SUBNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.subname=="{#SUBNAME}")].first()`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Subscription stats [{#SUBNAME}]: Apply errors|<p>Number of errors that occurred while applying changes for this logical replication subscription.</p>|Dependent item|pgsql.subscription.apply.error[{#SUBNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.apply_error_count`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Subscription stats [{#SUBNAME}]: Sync errors|<p>Number of errors that occurred during the initial synchronization phase of this logical replication subscription.</p>|Dependent item|pgsql.subscription.sync.error[{#SUBNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sync_error_count`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Subscription stats [{#SUBNAME}]: Total errors|<p>Total number of errors for this logical replication subscription, from both`apply` and `sync`.</p>|Calculated|pgsql.subscription.total.error.count[{#SUBNAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Subscription stats [{#SUBNAME}]: Reset time|<p>Unix timestamp of the last reset of subscription statistics.</p><p>The value `never` indicates that statistics have never been reset.</p>|Dependent item|pgsql.subscription.reset.time[{#SUBNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats_reset`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Subscription stats [{#SUBNAME}]: Time since last reset|<p>Time in seconds that has passed since the last reset of subscription statistics.</p><p>If the value is zero or negative, the subscription has never been reset.</p>|Calculated|pgsql.subscription.reset.time.since[{#SUBNAME}]|

### Trigger prototypes for Subscription stats metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Subscription stats [{#SUBNAME}]: Apply errors average|<p>Apply subscription errors >= `{$PG.SUBSCRIPTION.APPLY.ERROR.AVG}`. Investigate subscription apply issues or replication delays.</p>|`min(/PostgreSQL by ODBC/pgsql.subscription.apply.error[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.APPLY.ERROR.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Subscription stats [{#SUBNAME}]: Apply errors high</li></ul>|
|PostgreSQL: Subscription stats [{#SUBNAME}]: Apply errors high|<p>Apply subscription errors >= `{$PG.SUBSCRIPTION.APPLY.ERROR.HIGH}`. Check subscription apply processes and replication lag.</p>|`min(/PostgreSQL by ODBC/pgsql.subscription.apply.error[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.APPLY.ERROR.HIGH}`|High||
|PostgreSQL: Subscription stats [{#SUBNAME}]: Sync errors average|<p>Sync subscription errors >= `{$PG.SUBSCRIPTION.SYNC.ERROR.AVG}`. Investigate subscription sync issues or replication delays.</p>|`min(/PostgreSQL by ODBC/pgsql.subscription.sync.error[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.SYNC.ERROR.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Subscription stats [{#SUBNAME}]: Sync errors high</li></ul>|
|PostgreSQL: Subscription stats [{#SUBNAME}]: Sync errors high|<p>Sync subscription errors >= `{$PG.SUBSCRIPTION.SYNC.ERROR.HIGH}`. Check subscription sync processes and replication lag.</p>|`min(/PostgreSQL by ODBC/pgsql.subscription.sync.error[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.SYNC.ERROR.HIGH}`|High||
|PostgreSQL: Subscription stats [{#SUBNAME}]: Total errors average|<p>Total subscription errors (sync + apply) >= `{$PG.SUBSCRIPTION.TOTAL.ERROR.AVG}`. Investigate replication issues or subscription problems.</p>|`min(/PostgreSQL by ODBC/pgsql.subscription.total.error.count[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.TOTAL.ERROR.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Subscription stats [{#SUBNAME}]: Total errors high</li></ul>|
|PostgreSQL: Subscription stats [{#SUBNAME}]: Total errors high|<p>Total subscription errors (sync + apply) >= `{$PG.SUBSCRIPTION.TOTAL.ERROR.HIGH}`. Check subscription health, replication lag, or sync/apply errors.</p>|`min(/PostgreSQL by ODBC/pgsql.subscription.total.error.count[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.TOTAL.ERROR.HIGH}`|High||

### LLD rule I/O metrics discovery (v16–17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O metrics discovery (v16–17)|<p>Discovers `I/O` metrics in PostgreSQL 16 and 17.</p>|Dependent item|pgsql.io.discovery.version.below.18<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for I/O metrics discovery (v16–17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O: Get data|<p>Aggregated `pg_stat_io` metrics for PostgreSQL 16-17.</p>|Database monitor|db.odbc.select[pgsql.stat.io.version.below.18.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|I/O: Read bytes total|<p>Total number of bytes read by PostgreSQL.</p>|Dependent item|pgsql.io.read.bytes.below.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.reads`</p></li></ul>|
|I/O: Write bytes total|<p>Total number of bytes written by PostgreSQL.</p>|Dependent item|pgsql.io.write.bytes.version.below.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.writes`</p></li></ul>|

### LLD rule I/O metrics discovery (v18+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O metrics discovery (v18+)|<p>Discovers `I/O` metrics in PostgreSQL 18 and above.</p>|Dependent item|pgsql.io.discovery.version.above.18<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for I/O metrics discovery (v18+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O: Get data|<p>Aggregated `pg_stat_io metrics` for PostgreSQL 18 and above.</p>|Database monitor|db.odbc.select[pgsql.stat.io.version.above.18.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|I/O: Read bytes total|<p>Total number of bytes read by PostgreSQL.</p>|Dependent item|pgsql.io.read.bytes.version.above.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.read_bytes`</p></li></ul>|
|I/O: Write bytes total|<p>Total number of bytes written by PostgreSQL.</p>|Dependent item|pgsql.io.write.bytes.version.above.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write_bytes`</p></li></ul>|
|I/O: WAL write bytes total|<p>Total number of bytes written to WAL.</p>|Dependent item|pgsql.io.wal.write.bytes.version.above.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_write_bytes`</p></li></ul>|
|I/O: Time total|<p>Total time spent on I/O operations (read + write).</p><p>IMPORTANT!</p><p>Requires `track_io_timing = on` in `postgresql.conf` or via `ALTER SYSTEM`.</p><p>Otherwise, this metric will always be `0`.</p>|Dependent item|pgsql.io.time.ms.version.above.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.io_time_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|I/O: Buffer hit ratio|<p>Percentage of buffer cache hits.</p>|Dependent item|pgsql.io.hit.ratio.version.above.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for I/O metrics discovery (v18+)

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: I/O:Buffer hit warning|<p>I/O buffer hit ratio <= `{$PG.IO.CACHE.WARN}`%. Slightly below optimal. Monitor memory and disk activity.</p>|`max(/PostgreSQL by ODBC/pgsql.io.hit.ratio.version.above.18[{#SINGLETON}],5m) <= {$PG.IO.CACHE.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: I/O:Buffer hit average</li></ul>|
|PostgreSQL: I/O:Buffer hit average|<p>I/O buffer hit ratio <= `{$PG.IO.CACHE.AVG}`%. Moderate cache efficiency drop. Investigate queries and disk latency.</p>|`max(/PostgreSQL by ODBC/pgsql.io.hit.ratio.version.above.18[{#SINGLETON}],5m) <= {$PG.IO.CACHE.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: I/O:Buffer hit high</li></ul>|
|PostgreSQL: I/O:Buffer hit high|<p>I/O buffer hit ratio <= `{$PG.IO.CACHE.HIGH}`%. Critical cache inefficiency. High disk I/O expected. Investigate storage performance immediately.</p>|`max(/PostgreSQL by ODBC/pgsql.io.hit.ratio.version.above.18[{#SINGLETON}],5m) <= {$PG.IO.CACHE.HIGH}`|High||

### LLD rule Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication discovery|<p>Discovers replication lag metrics.</p>|Database monitor|db.odbc.select[pgsql.replication.process.discovery,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Application [{#APPLICATION_NAME}]: Get data|<p>Collects metrics from `pg_stat_replication` about the application `{#APPLICATION_NAME}` that is connected to this WAL sender, which contains information about the WAL sender process, showing statistics about replication to that sender's connected standby server.</p>|Dependent item|pgsql.replication.get["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#APPLICATION_NAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Application [{#APPLICATION_NAME}]: Replication flush lag|<p>Time elapsed between flushing recent WAL locally and receiving notification that this standby server has written and flushed it (but not yet applied it). This can be used to gauge the delay that `synchronous_commit` level incurred while committing if this server was configured as a synchronous standby.</p>|Dependent item|pgsql.replication.process.flush_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.flush_lag`</p></li></ul>|
|Application [{#APPLICATION_NAME}]: Replication replay lag|<p>Time elapsed between flushing recent WAL locally and receiving notification that this standby server has written, flushed, and applied it. This can be used to gauge the delay that `synchronous_commit` level `remote_apply` incurred while committing if this server was configured as a synchronous standby.</p>|Dependent item|pgsql.replication.process.replay_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replay_lag`</p></li></ul>|
|Application [{#APPLICATION_NAME}]: Replication write lag|<p>Time elapsed between flushing recent WAL locally and receiving notification that this standby server has written it (but not yet flushed it or applied it). This can be used to gauge the delay that `synchronous_commit` level `remote_write` incurred while committing if this server was configured as a synchronous standby.</p>|Dependent item|pgsql.replication.process.write_lag["{#APPLICATION_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write_lag`</p></li></ul>|

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Discovers databases (DB) in the database management system (DBMS), except:</p><p>- templates;</p><p>- default "postgres" DB;</p><p>- DBs that do not allow connections.</p>|Database monitor|db.odbc.select[pgsql.db.discovery,,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DB [{#DBNAME}]: Get data|<p>Get `dbstat` metrics for database `{#DBNAME}`.</p>|Dependent item|pgsql.dbstat.get["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Get data|<p>Get locks metrics for database `{#DBNAME}`.</p>|Dependent item|pgsql.locks.get["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Get data|<p>Get query metrics for database `{#DBNAME}`.</p>|Dependent item|pgsql.queries.get["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Database age|<p>Age of the database in transactions (XID distance from `datfrozenxid`).</p>|Database monitor|db.odbc.select[pgsql.db.age,,"Database={#DBNAME};{$PG.CONNSTRING.ODBC}"]|
|DB [{#DBNAME}]: Bloating tables|<p>Number of bloating tables.</p>|Database monitor|db.odbc.select[pgsql.db.bloating_tables,,"Database={#DBNAME};{$PG.CONNSTRING.ODBC}"]|
|DB [{#DBNAME}]: Database size|<p>Database size.</p>|Database monitor|db.odbc.select[pgsql.db.size,,"Database={#DBNAME};{$PG.CONNSTRING.ODBC}"]|
|DB [{#DBNAME}]: Blocks hit per second|<p>Total number of times per second disk blocks were found in the buffer cache, making a read unnecessary.</p>|Dependent item|pgsql.dbstat.blks_hit.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_hit`</p></li><li>Change per second</li></ul>|
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
|DB [{#DBNAME}]: Disk blocks read time per second|<p>Time spent reading data file blocks by backends per second.</p>|Dependent item|pgsql.dbstat.blk_read_time.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_read_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Disk blocks write time per second|<p>Time spent writing data file blocks by backends per second.</p>|Dependent item|pgsql.dbstat.blk_write_time.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Num of accessexclusive locks|<p>Number of `accessexclusive` locks for this database.</p>|Dependent item|pgsql.locks.accessexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.accessexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of accessshare locks|<p>Number of `accessshare` locks for this database.</p>|Dependent item|pgsql.locks.accessshare["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.accessshare`</p></li></ul>|
|DB [{#DBNAME}]: Num of exclusive locks|<p>Number of `exclusive` locks for this database.</p>|Dependent item|pgsql.locks.exclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.exclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of rowexclusive locks|<p>Number of `rowexclusive` locks for this database.</p>|Dependent item|pgsql.locks.rowexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rowexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of rowshare locks|<p>Number of `rowshare` locks for this database.</p>|Dependent item|pgsql.locks.rowshare["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rowshare`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Num of sharerowexclusive locks|<p>Number of total `sharerowexclusive` for this database.</p>|Dependent item|pgsql.locks.sharerowexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sharerowexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of shareupdateexclusive locks|<p>Number of `shareupdateexclusive` locks for this database.</p>|Dependent item|pgsql.locks.shareupdateexclusive["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.shareupdateexclusive`</p></li></ul>|
|DB [{#DBNAME}]: Num of share locks|<p>Number of `share` locks for this database.</p>|Dependent item|pgsql.locks.share["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.share`</p></li></ul>|
|DB [{#DBNAME}]: Num of locks total|<p>Total number of locks in this database.</p>|Dependent item|pgsql.locks.total["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|DB [{#DBNAME}]: Queries max maintenance time|<p>Max maintenance query time for this database.</p>|Dependent item|pgsql.queries.mro.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries max query time|<p>Max query time for this database.</p>|Dependent item|pgsql.queries.query.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries max transaction time|<p>Max transaction query time for this database.</p>|Dependent item|pgsql.queries.tx.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow maintenance count|<p>Slow maintenance query count for this database.</p>|Dependent item|pgsql.queries.mro.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow query count|<p>Slow query count for this database.</p>|Dependent item|pgsql.queries.query.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries slow transaction count|<p>Slow transaction query count for this database.</p>|Dependent item|pgsql.queries.tx.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum maintenance time|<p>Sum of maintenance query time for this database.</p>|Dependent item|pgsql.queries.mro.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum query time|<p>Sum of query time for this database.</p>|Dependent item|pgsql.queries.query.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Queries sum transaction time|<p>Sum of transaction query time for this database.</p>|Dependent item|pgsql.queries.tx.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_sum`</p></li></ul>|

### Trigger prototypes for Database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: DB [{#DBNAME}]: Size warning|<p>Database `{#DBNAME}` size >= `{$PG.DATABASE.SIZE.WARN}` bytes.</p>|`min(/PostgreSQL by ODBC/db.odbc.select[pgsql.db.size,,"Database={#DBNAME};{$PG.CONNSTRING.ODBC}"],5m) >= {$PG.DATABASE.SIZE.WARN:"{#DBNAME}"}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: DB [{#DBNAME}]: Size average</li></ul>|
|PostgreSQL: DB [{#DBNAME}]: Size average|<p>Database `{#DBNAME}` size >= `{$PG.DATABASE.SIZE.AVG}` bytes.</p>|`min(/PostgreSQL by ODBC/db.odbc.select[pgsql.db.size,,"Database={#DBNAME};{$PG.CONNSTRING.ODBC}"],5m) >= {$PG.DATABASE.SIZE.AVG:"{#DBNAME}"}`|Average|**Depends on**:<br><ul><li>PostgreSQL: DB [{#DBNAME}]: Size high</li></ul>|
|PostgreSQL: DB [{#DBNAME}]: Size high|<p>Database `{#DBNAME}` size >= `{$PG.DATABASE.SIZE.HIGH}` bytes.</p>|`min(/PostgreSQL by ODBC/db.odbc.select[pgsql.db.size,,"Database={#DBNAME};{$PG.CONNSTRING.ODBC}"],5m) >= {$PG.DATABASE.SIZE.HIGH:"{#DBNAME}"}`|High||
|PostgreSQL: DB [{#DBNAME}]: Too many recovery conflicts|<p>The primary and standby servers are in many ways loosely connected. Actions on the primary will have an effect on the standby. As a result, there is potential for negative interactions or conflicts between them.<br>https://www.postgresql.org/docs/current/hot-standby.html#HOT-STANDBY-CONFLICT</p>|`min(/PostgreSQL by ODBC/pgsql.dbstat.conflicts.rate["{#DBNAME}"],5m) > {$PG.CONFLICTS.MAX.WARN:"{#DBNAME}"}`|Average||
|PostgreSQL: DB [{#DBNAME}]: Deadlock occurred|<p>Number of deadlocks detected per second has exceeded `{$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}` for 5m.</p>|`min(/PostgreSQL by ODBC/pgsql.dbstat.deadlocks.rate["{#DBNAME}"],5m) > {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}`|High||
|PostgreSQL: DB [{#DBNAME}]: Too many slow queries|<p>The number of detected slow queries has exceeded the limit of `{$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}`.</p>|`min(/PostgreSQL by ODBC/pgsql.queries.query.slow_count["{#DBNAME}"],5m)>{$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}`|Warning||

### LLD rule Bgwriter metrics discovery (v<=17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Bgwriter metrics discovery (v<=17)|<p>Discovers `bgwriter` metrics in PostgreSQL 17 and below.</p>|Dependent item|pgsql.bgwriter.below_17.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Bgwriter metrics discovery (v<=17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get bgwriter|<p>Collect all metrics from pg_stat_bgwriter:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-BGWRITER-VIEW</p>|Database monitor|db.odbc.select[pgsql.bgwriter.below_17.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Bgwriter: Postmaster start time|<p>The time when the postmaster was last started.</p>|Dependent item|pgsql.bgwriter.postmaster_start_epoch.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.postmaster_start_time`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `7d`</p></li></ul>|
|Bgwriter: Buffers allocated per second|<p>Number of buffers allocated per second.</p>|Dependent item|pgsql.bgwriter.buffers_alloc.rate.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_alloc`</p></li><li>Change per second</li></ul>|
|Bgwriter: Number of bgwriter cleaning scan stopped per second|<p>Number of times the background writer stopped a cleaning scan because it had written too many buffers per second.</p>|Dependent item|pgsql.bgwriter.maxwritten_clean.rate.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxwritten_clean`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers written by the background writer per second|<p>Number of buffers written by the background writer per second.</p>|Dependent item|pgsql.bgwriter.buffers_clean.rate.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_clean`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers written during checkpoints per second|<p>Number of buffers written during checkpoints per second.</p>|Dependent item|pgsql.bgwriter.buffers_checkpoint.rate.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_checkpoint`</p></li><li>Change per second</li></ul>|
|Checkpoint: Scheduled per second|<p>Number of scheduled checkpoints that have been performed per second.</p>|Dependent item|pgsql.bgwriter.checkpoints_timed.rate.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_timed`</p></li><li>Change per second</li></ul>|
|Checkpoint: Requested per second|<p>Number of requested checkpoints that have been performed per second.</p>|Dependent item|pgsql.bgwriter.checkpoints_req.rate.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_req`</p></li><li>Change per second</li></ul>|
|Checkpoint: Checkpoint write per second|<p>Total amount of time per second that has been spent in the portion of checkpoint processing where files are written to disk.</p>|Dependent item|pgsql.bgwriter.checkpoint_write_time.rate.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Bgwriter: Checkpoint sync per second|<p>Total amount of time per second that has been spent in the portion of checkpoint processing where files are synchronized to disk.</p>|Dependent item|pgsql.bgwriter.checkpoint_sync_time.rate.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_sync_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Bgwriter: Buffers written directly by a backend per second|<p>Number of buffers written directly by a backend per second.</p>|Dependent item|pgsql.bgwriter.buffers_backend.rate.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend`</p></li><li>Change per second</li></ul>|
|Bgwriter: Times a backend executed its own fsync per second|<p>Number of times a backend had to execute its own fsync call per second (normally the background writer handles those even when the backend does its own write).</p>|Dependent item|pgsql.bgwriter.buffers_backend_fsync.rate.below_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_backend_fsync`</p></li><li>Change per second</li></ul>|

### LLD rule Bgwriter metrics discovery (v17+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Bgwriter metrics discovery (v17+)|<p>Discovers `bgwriter` metrics in PostgreSQL 17 and above.</p>|Dependent item|pgsql.bgwriter.above_17.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Bgwriter metrics discovery (v17+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get bgwriter|<p>Collect all metrics from pg_stat_bgwriter:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-BGWRITER-VIEW</p>|Database monitor|db.odbc.select[pgsql.bgwriter.above_17.get{#SINGLETON},,"Database={$PG.DATABASE};{$PG.CONNSTRING.ODBC}"]|
|Bgwriter: Postmaster start time|<p>The time when the postmaster was last started.</p>|Dependent item|pgsql.bgwriter.postmaster_start_epoch.above_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.postmaster_start_time`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `7d`</p></li></ul>|
|Bgwriter: Buffers allocated per second|<p>Number of buffers allocated per second.</p>|Dependent item|pgsql.bgwriter.buffers_alloc.rate.above_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_alloc`</p></li><li>Change per second</li></ul>|
|Bgwriter: Number of bgwriter cleaning scan stopped per second|<p>Number of times the background writer stopped a cleaning scan because it had written too many buffers per second.</p>|Dependent item|pgsql.bgwriter.maxwritten_clean.rate.above_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxwritten_clean`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers written by the background writer per second|<p>Number of buffers written by the background writer per second.</p>|Dependent item|pgsql.bgwriter.buffers_clean.rate.above_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_clean`</p></li><li>Change per second</li></ul>|
|Checkpoint: Buffers written during checkpoints per second|<p>Number of buffers written during checkpoints per second.</p>|Dependent item|pgsql.bgwriter.buffers_checkpoint.rate.above_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.buffers_checkpoint`</p></li><li>Change per second</li></ul>|
|Checkpoint: Scheduled per second|<p>Number of scheduled checkpoints that have been performed per second.</p>|Dependent item|pgsql.bgwriter.checkpoints_timed.rate.above_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_timed`</p></li><li>Change per second</li></ul>|
|Checkpoint: Requested per second|<p>Number of requested checkpoints that have been performed per second.</p>|Dependent item|pgsql.bgwriter.checkpoints_req.rate.above_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoints_req`</p></li><li>Change per second</li></ul>|
|Checkpoint: Checkpoint write per second|<p>Total amount of time per second that has been spent in the portion of checkpoint processing where files are written to disk.</p>|Dependent item|pgsql.bgwriter.checkpoint_write_time.rate.above_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Bgwriter: Checkpoint sync per second|<p>Total amount of time per second that has been spent in the portion of checkpoint processing where files are synchronized to disk.</p>|Dependent item|pgsql.bgwriter.checkpoint_sync_time.rate.above_17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_sync_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

