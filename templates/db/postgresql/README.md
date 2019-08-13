
# Template DB PostgreSQL

## Overview

Templates to monitor PostgreSQL by Zabbix.\
This template was tested on Zabbix 4.2.1 and PostgreSQL vesions 9.6, 10 and 11 on Linux and Windows.

## Setup

1. Install Zabbix agent and create a read-only `zbx_monitor` user with proper access to your PostgreSQL server.

    For PostgreSQL version 10 and above:

    ```sql
    CREATE USER zbx_monitor WITH PASSWORD '<PASSWORD>' INHERIT;
    GRANT pg_monitor TO zbx_monitor;
    ```

    For older PostgreSQL versions:

    ```sql
    CREATE USER zbx_monitor WITH PASSWORD '<PASSWORD>';
    GRANT SELECT ON pg_stat_database TO zbx_monitor;
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

5. If you need to monitor the remote server then create `.pgpass` file in Zabbix agent home directory `/var/lib/zabbix/` and add the connection details with the instance, port, database, user and password information in the below format https://www.postgresql.org/docs/current/libpq-pgpass.html.

    Example 1:

    ```bash
    <REMOTE_HOST1>:5432:postgres:zbx_monitor:<PASSWORD>

    <REMOTE_HOST2>:5432:postgres:zbx_monitor:<PASSWORD>
    ...
    <REMOTE_HOSTN>:5432:postgres:zbx_monitor:<PASSWORD>
    ```

    Example 2:

    ```bash
    *:5432:postgres:zbx_monitor:<PASSWORD>
    ```

6. Import `template_db_postgresql.xml` to Zabbix and link it to the target host

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

| Macro                             | Description                                                                | Default     |
|-----------------------------------|----------------------------------------------------------------------------|-------------|
| {$PG.HOST}                        | Database server host or socket directory                                   | 127.0.0.1   |
| {$PG.PORT}                        | Database server port                                                       | 5432        |
| {$PG.USER}                        | Database user name                                                         | zbx_monitor |
| {$PG.DB}                          | Database name to connect to the server                                     | postgres    |
| {$PG.LLD.FILTER.DBNAME}           | Regular expression for filtering names of discovered databases             | (.*)        |
| {$PG.CHECKPOINTS_REQ.MAX.WARN}    | Requested checkpoints threshold for trigger expression                     | 5           |
| {$PG.PING_TIME.MAX.WARN}          | Maximum ping time for trigger expression                                   | 1s          |
| {$PG.CACHE_HITRATIO.MIN.WARN}     | Minimum cache hit ratio for trigger expression                             | 90          |
| {$PG.CONN_TOTAL_PCT.MAX.WARN}     | Maximum number of open connections for trigger expression                  | 90          |
| {$PG.CONN_WAIT.MAX.WARN}          | Maximum number of waiting connections for trigger expression               | 0           |
| {$PG.CONN_IDLE_IN_TRANS.MAX.WARN} | Maximum number of 'idle in transaction' connections for trigger expression | 5           |
| {$PG.DEADLOCKS.MAX.WARN}          | Maximum number of deadlocks for trigger expression                         | 0           |
| {$PG.CONFLICTS.MAX.WARN}          | Maximum number of recovery conflicts for trigger expression                | 0           |
| {$PG.REPL_LAG.MAX.WARN}           | Maximum replication lag for trigger expression                             | 10m         |
| {$PG.TRANS_ACTIVE.MAX.WARN}       | Maximum active transaction time for trigger expression                     | 30s         |
| {$PG.TRANS_IDLE.MAX.WARN}         | Maximum 'idle in transaction' connection time for trigger expression       | 30s         |
| {$PG.TRANS_WAIT.MAX.WARN}         | Maximum waiting transaction time for trigger expression                    | 30s         |
| {$PG.LOCKS.MAX.WARN}              | Maximum number of locks for trigger expression                             | 100         |
| {$PG.QUERY_ETIME.MAX.WARN}        | Maximum query execution time in seconds                                    | 30          |
| {$PG.SLOW_QUERIES.MAX.WARN}       | Maximum number of slow queries for trigger expression                      | 5           |
| {$PG.FROZENXID_PCT_STOP.MIN.HIGH} | Minimum percentage of frozen XID                                           | 75          |

## Template links

There are no template links in this template.

## Discovery rules

| Name                | Description                                                              | Type         |
|---------------------|--------------------------------------------------------------------------|--------------|
| Databases discovery | Use the macro {$PG.LLD.FILTER.DBNAME} to filter the discovered databases | Zabbix agent |

## Items collected

| Name                                               | Description                                                                                                                                         | Type           |
|----------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|----------------|
| Bgwriter                                           | Statistics about the background writer process's activity                                                                                           | Zabbix agent   |
| Bgwriter: Checkpoint write time                    | Total amount of time that has been spent in the portion of checkpoint processing where files are written to disk, in milliseconds                   | Dependent item |
| Bgwriter: Buffers backend fsync                    | Number of times a backend had to execute its own fsync call (normally the background writer handles those even when the backend does its own write) | Dependent item |
| Bgwriter: Checkpoint sync time                     | Total amount of time that has been spent in the portion of checkpoint processing where files are synchronized to disk                               | Dependent item |
| Bgwriter: Requested checkpoints                    | Number of requested checkpoints that have been performed                                                                                            | Dependent item |
| Bgwriter: Max written                              | Number of times the background writer stopped a cleaning scan because it had written too many buffers                                               | Dependent item |
| Bgwriter: Scheduled checkpoints                    | Number of scheduled checkpoints that have been performed                                                                                            | Dependent item |
| Bgwriter: Buffers written during checkpoints       | Number of buffers written during checkpoints                                                                                                        | Dependent item |
| Bgwriter: Buffers written directly by a backend    | Number of buffers written directly by a backend                                                                                                     | Dependent item |
| Bgwriter: Buffers written by the background writer | Number of buffers written by the background writer                                                                                                  | Dependent item |
| Bgwriter: Buffers allocated                        | Number of buffers allocated                                                                                                                         | Dependent item |
| Status: Version                                    |                                                                                                                                                     | Zabbix agent   |
| Status: Ping                                       |                                                                                                                                                     | Zabbix agent   |
| Status: Ping time                                  |                                                                                                                                                     | Zabbix agent   |
| Status: Uptime                                     |                                                                                                                                                     | Zabbix agent   |
| Status: Cache hit ratio %                          | Cache hit ratio                                                                                                                                     | Zabbix agent   |
| Status: DB {#DBNAME}: Size                         | Database size                                                                                                                                       | Zabbix agent   |
| Connections sum                                    | Collect all metrics from pg_stat_activity https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-ACTIVITY-VIEW                        | Zabbix agent   |
| Connections sum: Total %                           | Total number of connections in percentage                                                                                                           | Dependent item |
| Connections sum: Total                             | Total number of connections                                                                                                                         | Dependent item |
| Connections sum: Waiting                           | Total number of waiting connections https://www.postgresql.org/docs/current/monitoring-stats.html#WAIT-EVENT-TABLE                                   | Dependent item |
| Connections sum: Idle in transaction               | Total number of connections in a transaction state, but not executing a query                                                                       | Dependent item |
| Connections sum: Idle                              | Total number of connections waiting for a new client command                                                                                        | Dependent item |
| Connections sum: Active                            | Total number of connections executing a query                                                                                                       | Dependent item |
| Connections sum: Prepared                          | Total number of prepared transactions https://www.postgresql.org/docs/current/sql-prepare-transaction.html                                           | Dependent item |
| Dbstat                                             | Collect all metrics from pg_stat_database per database https://www.postgresql.org/docs/current/monitoring-stats.html#PG-STAT-DATABASE-VIEW           | Zabbix agent   |
| DB {#DBNAME}: Commits per second                   | Number of transactions in this database that have been committed                                                                                    | Dependent item |
| DB {#DBNAME}: Tuples updated per second            | Total number of rows updated by queries in this database                                                                                            | Dependent item |
| DB {#DBNAME}: Tuples returned per second           | Total number of rows updated by queries in this database                                                                                            | Dependent item |
| DB {#DBNAME}: Tuples inserted per second           | Total number of rows inserted by queries in this database                                                                                           | Dependent item |
| DB {#DBNAME}: Tuples fetched per second            | Total number of rows fetched by queries in this database                                                                                            | Dependent item |
| DB {#DBNAME}: Tuples deleted per second            | Total number of rows deleted by queries in this database                                                                                            | Dependent item |
| DB {#DBNAME}: Temp_files created per second        | Total number of temporary files created by queries in this database                                                                                 | Dependent item |
| DB {#DBNAME}: Temp_bytes written per second        | Total amount of data written to temporary files by queries in this database                                                                         | Dependent item |
| DB {#DBNAME}: Rollbacks per second                 | Total number of transactions in this database that have been rolled back                                                                            | Dependent item |
| DB {#DBNAME}: Detected deadlocks per second        | Total number of detected deadlocks in this database                                                                                                 | Dependent item |
| DB {#DBNAME}: Detected conflicts per second        | Total number of queries canceled due to conflicts with recovery in this database                                                                    | Dependent item |
| DB {#DBNAME}: Disk blocks read per second          | Total number of disk blocks read in this database                                                                                                   | Dependent item |
| DB {#DBNAME}: Blocks hit per second                | Total number of times disk blocks were found already in the buffer cache, so that a read was not necessary                                          | Dependent item |
| Replication: standby count                         | Number of standby servers                                                                                                                           | Zabbix agent   |
| Replication: recovery role                         | Replication role: 1 — recovery is still in progress (standby mode), 0 — master mode.                                                                | Zabbix agent   |
| Replication: status                                | Replication status: 0 — streaming is down, 1 — streaming is up, 2 — master mode                                                                     | Zabbix agent   |
| Replication: lag in seconds                        | Replication lag with Master in seconds                                                                                                              | Zabbix agent   |
| Transactions                                       | Collect metrics by transaction execution time                                                                                                       | Zabbix agent   |
| Transactions: Max active transaction time          | Current max active transaction time                                                                                                                 | Dependent item |
| Transactions: Max idle transaction time            | Current max idle transaction time                                                                                                                   | Dependent item |
| Transactions: Max prepared transaction time        | Current max prepared transaction time                                                                                                               | Dependent item |
| Transactions: Max waiting transaction time         | Current max waiting transaction time                                                                                                                | Dependent item |
| Status: Config hash                                | PostgreSQL configuration hash                                                                                                                       | Zabbix agent   |
| WAL                                                | Master item to collect WAL metrics                                                                                                                  | Zabbix agent   |
| WAL: Bytes written per second                      | WAL write in bytes                                                                                                                                  | Dependent item |
| WAL: Segments count                                | Number of WAL segments                                                                                                                              | Dependent item |
| Locks                                              | Collect all metrics from pg_locks per databasehttps://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-TABLES                          | Zabbix agent   |
| DB {#DBNAME} locks: Total                          | Total number of locks in the database                                                                                                               | Dependent item |
| DB {#DBNAME} scans                                 | Number of scans done for table/index in the database                                                                                                | Zabbix agent   |
| DB {#DBNAME} scans: Index                          | Number of index scans in the database                                                                                                               | Dependent item |
| DB {#DBNAME} scans: Sequential                     | Number of sequential scans in the database                                                                                                          | Dependent item |
| Queries                                            | Collect all metrics by query execution time                                                                                                         | Zabbix agent   |
| DB {#DBNAME} queries: Max maintenance time         | Max maintenance query time                                                                                                                          | Dependent item |
| DB {#DBNAME} queries: Max query time               | Max query time                                                                                                                                      | Dependent item |
| DB {#DBNAME} queries: Max transaction time         | Max transaction query time                                                                                                                          | Dependent item |
| DB {#DBNAME} queries: Sum maintenance time         | Sum maintenance query time                                                                                                                          | Dependent item |
| DB {#DBNAME} queries: Sum query time               | Sum query time                                                                                                                                      | Dependent item |
| DB {#DBNAME} queries: Sum transaction time         | Sum transaction query time                                                                                                                          | Dependent item |
| DB {#DBNAME} queries: Slow maintenance count       | Slow maintenance query count                                                                                                                        | Dependent item |
| DB {#DBNAME} queries: Slow query count             | Slow query query count                                                                                                                              | Dependent item |
| DB {#DBNAME} queries: Slow transaction count       | Slow transaction query count                                                                                                                        | Dependent item |
| DB {#DBNAME} frozen XID                            |                                                                                                                                                     | Zabbix agent   |
| DB {#DBNAME} frozen XID: before stop %             | Preventing Transaction ID Wraparound Failures https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND                   | Dependent item |
| DB {#DBNAME} frozen XID: before avtovacuum %       | Preventing Transaction ID Wraparound Failures https://www.postgresql.org/docs/current/routine-vacuuming.html#VACUUM-FOR-WRAPAROUND                   | Dependent item |

## Triggers

| Name                                                                                                                                       | Severity | Expression                                                                                             | Description                                                                                                                                                                                                                                                                                                                                                                       | Dependencies                |
|--------------------------------------------------------------------------------------------------------------------------------------------|----------|--------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------|
| PostgreSQL: Required checkpoints occurs too frequently (over {$PG.CHECKPOINTS_REQ.MAX.WARN})                                               | Average  | pgsql.bgwriter.checkpoints_req.last() > {$PG.CHECKPOINTS_REQ.MAX.WARN}                                 | Checkpoints are points in the sequence of transactions at which it is guaranteed that the heap and index data files have been updated with all information written before that checkpoint. At checkpoint time, all dirty data pages are flushed to disk and a special checkpoint record is written to the log file. https://www.postgresql.org/docs/current/wal-configuration.html |                             |
| PostgreSQL: Response too long (over {$PG.PING_TIME.MAX.WARN})                                                                              | Average  | pgsql.ping.time.min(5m) > {$PG.PING_TIME.MAX.WARN}                                                     |                                                                                                                                                                                                                                                                                                                                                                                   | PostgreSQL: Service is down |
| PostgreSQL: Service is down                                                                                                                | High     | pgsql.ping.last() = 0                                                                                  |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
| PostgreSQL: Failed to get items (no data for 30m)                                                                                          | Warning  | pgsql.bgwriter.nodata(30m) = 1                                                                         | Zabbix has not received data for items for the last 30 minutes                                                                                                                                                                                                                                                                                                                    | PostgreSQL: Service is down |
| PostgreSQL: Service has been restarted (uptime < 10m)                                                                                      | Info     | pgsql.uptime.last() < 10m                                                                              | PostgreSQL uptime is less than 10 minutes                                                                                                                                                                                                                                                                                                                                         |                             |
| PostgreSQL: Cache hit ratio too low (under {$PG.CACHE_HITRATIO.MIN.WARN} in 5m)                                                            | Warning  | pgsql.cache.hit.max(5m) < {$PG.CACHE_HITRATIO.MIN.WARN}                                                |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
| PostgreSQL: Total number of connections is too high (over {$PG.CONN_TOTAL_PCT.MAX.WARN} in 5m)                                             | Average  | pgsql.connections.sum.total_pct.min(5m) > {$PG.CONN_TOTAL_PCT.MAX.WARN}                                |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
| Database {#DBNAME}: Deadlock occured (over {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"} in 5m)                                                     | High     | pgsql.dbstat.deadlocks["{#DBNAME}"].min(5m) > {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}                     |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
| PostgreSQL: Too many recovery conflicts (over {$PG.CONFLICTS.MAX.WARN} in 5m)                                                              | Average  | pgsql.dbstat.sum.conflicts.min(5m) > {$PG.CONFLICTS.MAX.WARN}                                          | The primary and standby servers are in many ways loosely connected. Actions on the primary will have an effect on the standby. As a result, there is potential for negative interactions or conflicts between them. https://www.postgresql.org/docs/current/hot-standby.html#HOT-STANDBY-CONFLICT                                                                                  |                             |
| PostgreSQL: Streaming lag is too high (over {$PG.REPL_LAG.MAX.WARN} in 5m)                                                                 | Average  | pgsql.streaming.lag.sec.min(5m) > {$PG.REPL_LAG.MAX.WARN}                                              |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
| PostgreSQL: Configuration has changed                                                                                                      | Info     | pgsql.config.diff() = 1 and pgsql.config.strlen() > 0                                                  |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
| PostgreSQL: Version has changed (new version value received: {ITEM.VALUE})                                                                 | Info     | pgsql.version.diff() = 1 and pgsql.version.strlen() > 0                                                |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
| Database {#DBNAME}: Number of locks is too high (over {$PG.LOCKS.MAX.WARN:"{#DBNAME}"} in 5m)                                              | Warning  | pgsql.locks.total["{#DBNAME}"].min(5m) > {$PG.LOCKS.MAX.WARN:"{#DBNAME}"}                              |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
| Database {#DBNAME}: Too many slow queries (over {$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"} in 5m)                                             | Warning  | pgsql.queries.query.slow_count["{#DBNAME}"].min(5m) > {$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}          |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
| Database {#DBNAME}: VACUUM FREEZE is required to prevent wraparound (frozen XID less then {$PG.FROZENXID_PCT_STOP.MIN.HIGH:"{#DBNAME}"} %) | Average  | pgsql.db.frozenxid_prc.before_stop["{#DBNAME}"].last() < {$PG.FROZENXID_PCT_STOP.MIN.HIGH:"{#DBNAME}"} |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
| PostgreSQL: Replication is down                                                                                                            | Average  | pgsql.replication.status.max(5m) = 0                                                                   |                                                                                                                                                                                                                                                                                                                                                                                   |                             |
## Feedback
Please report any issues with the template at https://support.zabbix.com 

You can also provide feedback, discuss the template or ask for help with it at
[ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384190-%C2%A0discussion-thread-for-official-zabbix-template-db-postgresql).

## References
