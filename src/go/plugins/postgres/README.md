# PostgreSQL plugin

This plugin provides a native solution for monitoring PostgreSQL servers by Zabbix (in-memory data structure store).
The plugin can monitor several remote or local PostgreSQL instances simultaneously via Zabbix agent 2. The plugin keeps connections in the open state to reduce network congestion, latency, CPU, and memory usage. It can be used in conjunction with the official "Template DB PostgreSQL Agent 2" monitoring template (it is also possible to edit or extend the default template as needed or create a new one for your specific needs).

## Requirements

- Zabbix Agent 2
- Go >= 1.12 (required only to build from source)

## Installation

The plugin is supplied as part of the Zabbix Agent 2 and does not require any special installation steps. Once Zabbix Agent 2 is installed, the plugin is ready to work. Now you need to make sure that a PostgreSQL instance is available for connection and configure monitoring.

## Configuration

Open the Zabbix Agent configuration file (zabbix_agent2.conf or zabbix_agent2.win.conf) and set the required parameters.

**Plugins.Postgres.Database** — a database name to be used for PostgreSQL.
*Default value:* postgres

**Plugins.Postgres.KeepAlive** — inactive connection timeout (how long a connection can remain unused before it gets closed).  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Postgres.Timeout** — request execution timeout (how long to wait for a request to complete before shutting it down).  
*Default value:* equals the global 'Timeout' (configuration parameter set in zabbix_agent2.conf).  
*Limits:* 1-30

### Authentication

The plugin uses username and password set in the Agent's configuration file for PostgreSQL authentication. It is possible to monitor several PostgreSQL instances by creating named sessions in the configuration file and providing different usernames, passwords, ports and hosts for each session.

**Note:** For security reasons, it is forbidden to pass embedded credentials within the connString item key parameter (can be either a URI or a session name) — such credentials will be ignored.

- If  passing a URI as the connString and the connection requires authentication, you can use the username and password in item key parameters or the Plugins.Postgres.User and Plugins.Postgres.Password parameters (the 1st level password) in the configuration file. In other words, once defined, these parameters will be used for authenticating all connections where the host, port are represented by host.

- To use different usernames and passwords for different PostgreSQL instances, create named session in the config file for each instance and define a session-level username and password.

#### Named sessions

Named sessions allow you to define specific parameters for each PostgreSQL instance. Currently, only 5 parameters are supported: host, port, username, password and databasename.

*Example:*  
If you have two instances: "Postgres1" and "Postgres2", the following options have to be added to the agent configuration:

    Plugins.Postgres.Sessions.Postgres1.Host=127.0.0.1
    Plugins.Postgres.Sessions.Postgres1.Port=5433
    Plugins.Postgres.Sessions.Postgres1.User=<UsernameForPostgres1>
    Plugins.Postgres.Sessions.Postgres1.Password=<PasswordForPostgres1>
    Plugins.Postgres.Sessions.Postgres1.Database=<DatabaseForPostgres1>
    Plugins.Postgres.Sessions.Postgres2.Host=127.0.0.7
    Plugins.Postgres.Sessions.Postgres2.Port=5434
    Plugins.Postgres.Sessions.Postgres2.User=<UsernameForPostgres2>
    Plugins.Postgres.Sessions.Postgres2.Password=<PasswordForPostgres2>
    Plugins.Postgres.Sessions.Postgres2.Database=<DatabaseForPostgres2>

Now, these names can be used in keys instead of URIs:

    Postgres.ping[Postgres1]
    Postgres.ping[Postgres2]

### Parameters priority

There are 3 levels of parameters overwriting:

1. Hardcoded default values →
2. 1st level config params (Plugins.Postgres.\<parameter\>) →
3. Named sessions (Plugins.Postgres.Sessions.\<sessionName\>.\<parameter\>) →

## Supported keys

**pgsql.ping[uri,username,password,dbName]** — tests whether a connection is alive or not.
*Params:*
dbName — Database name. Optional.

*Returns:*

- "1" if the connection is alive.
- "0" if the connection is broken (returned if there was any error during the test, including AUTH and configuration issues).

**pgsql.db.discovery[uri,username,password,dbName]** — Databases discovery.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT json_build_object('data',json_agg(json_build_object('{#DBNAME}',d.datname)))
FROM pg_database
WHERE NOT datistemplate
AND datallowconn;
```

> SQL query in LLD JSON format.

**pgsql.db.size[uri,username,password,dbName]** — database size in bytes. Used in databases discovery.
*Params:*
dbName — Database name. Mandatory.

*Returns:* Result of the

```sql
SELECT pg_database_size(datname::text)
FROM pg_catalog.pg_database
WHERE datistemplate = false
AND datname = <dbName>;
```

> SQL query for specific database in bytes.

**pgsql.db.age[uri,username,password,dbName]** — age of the oldest xid for each database. Used in databases discovery.
*Params:*
dbName — Database name. Mandatory.

*Returns:* Result of the

```sql
SELECT age(datfrozenxid)
FROM pg_catalog.pg_database
WHERE datistemplate = false
AND datname = <dbName>
```

> SQL query for specific database in transactions.

**pgsql.db.bloating_tables[uri,username,password,dbName]** — number of bloating tables
per database. Used in databases discovery.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT count(*)
FROM pg_catalog.pg_stat_all_tables
WHERE (n_dead_tup/(n_live_tup+n_dead_tup)::float8) > 0.2
AND (n_live_tup+n_dead_tup) > 50;
```

> SQL query.

Result of this query differs depending on the database to which agent is now connected.

**pgsql.replication_lag.sec[uri,username,password]** — replication lag in seconds.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT
CASE
WHEN pg_last_wal_receive_lsn() = pg_last_wal_replay_lsn() THEN 0
ELSE
COALESCE(EXTRACT(EPOCH FROM now() - pg_last_xact_replay_timestamp())::integer, 0)
END as lag
```

> SQL query in seconds.

**pgsql.replication_lag.b[uri,username,password]** — replication lag in bytes.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT pg_catalog.pg_wal_lsn_diff (received_lsn, pg_last_wal_replay_lsn())
FROM pg_stat_wal_receiver;
```

> SQL query in bytes

**pgsql.replication.count[uri,username,password]** — number of standby servers.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT count(*) FROM pg_stat_replication
```

> SQL query.

**pgsql.replication.status[uri,username,password]** — status of replication.
*Params:*
dbName — Database name. Optional.

*Returns:*

- 0 — streaming is down
- 1 — streaming is up
- 2 — mastermode

**pgsql.replication.recovery_role[uri,username,password]** — recovery status.
(Params:)
dbName — Database name. Optional.

*Returns:*

- 1 — recovery is still in progress (standby mode)
- 0 — master mode.

**pgsql.cache.hit[uri,username,password,dbName]** — cache hit rate.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT round(sum(blks_hit)*100/sum(blks_hit+blks_read), 2)
FROM pg_catalog.pg_stat_database;
```

> SQL query in percentage.

**pgsql.connections[uri,username,password,dbName]** — connections by types.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT row_to_json(T)
FROM (
SELECT
sum(CASE WHEN state = 'active' THEN 1 ELSE 0 END) AS active,
sum(CASE WHEN state = 'idle' THEN 1 ELSE 0 END) AS idle,
sum(CASE WHEN state = 'idle in transaction' THEN 1 ELSE 0 END) AS idle_in_transaction,
sum(CASE WHEN state = 'idle in transaction (aborted)' THEN 1 ELSE 0 END) AS idle_in_transaction_aborted,
sum(CASE WHEN state = 'fastpath function call' THEN 1 ELSE 0 END) AS fastpath_function_call,
count(*) AS total,
count(*)*100/(SELECT current_setting('max_connections')::int) AS total_pct,
sum(CASE WHEN wait_event IS NOT NULL THEN 1 ELSE 0 END) AS waiting,
(SELECT count(*) FROM pg_prepared_xacts) AS prepared
FROM pg_stat_activity
WHERE datid is not NULL) T;
```

> SQL query JSON format.

Then JSON is proceeded by dependent items of pgsql.connections:

- pgsql.connections.active - the backend is executing a query.
- pgsql.connections.fastpath_function_call** -the backend is executing a fast-path function.
- pgsql.connections.idle - The backend is waiting for a new client command.
- pgsql.connections.idle_in_transaction - the backend is in a transaction, but is not currently executing a query.
- pgsql.connections.prepared - number of prepared connections
- pgsql.connections.total - total numer of connection
- pgsql.connections.total_pct - percantange of total connections in respect to ‘max_connections’ setting of PostgreSQL server.
- pgsql.connections.waiting - number of waiting connections.
- pgsql.connections.idle_in_transaction_aborted - This state is similar to idle in transaction, except one of the statements in the transaction caused an error.

**pgsql.archive[uri,username,password,dbName]** — returns info about archive files.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT row_to_json(T)
FROM (SELECT archived_count, failed_count from pg_stat_archiver) T
SELECT row_to_json(T)
FROM ( SELECT count(name) AS count_files ,
coalesce(sum((pg_stat_file('./pg_wal/' || rtrim(ready.name,'.ready'))).size),0) AS size_files
FROM ( SELECT name
FROM pg_ls_dir('./pg_wal/archive_status') name WHERE right( name,6)= '.ready' ) ready) T;
```

> SQL query JSON format.

Then JSON is proceeded by dependent items of:

- pgsql.archive.count_archived_files - number of WAL files that have been successfully archived.
- pgsql.archive.failed_trying_to_archive - number of failed attempts for archiving WAL files.
- pgsql.archive.count_files_to_archive - number of files to archive.
- pgsql.archive.size_files_to_archive - size of files to archive.

**pgsql.bgwriter[uri,username,password,dbName]** — statistics about the background writer process's activity.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT row_to_json (T)
FROM (
SELECT
checkpoints_timed
, checkpoints_req
, checkpoint_write_time
, checkpoint_sync_time
, buffers_checkpoint
, buffers_clean
, maxwritten_clean
, buffers_backend
, buffers_backend_fsync
, buffers_alloc
FROM pg_catalog.pg_stat_bgwriter
) T
```

> SQL query JSON format.

Then JSON is proceeded by dependent items of:

- pgsql.bgwriter.buffers_alloc - number of buffers allocated.
- pgsql.bgwriter.buffers_backend - number of buffers written directly by a backend.
- pgsql.bgwriter.maxwritten_clean - number of times the background writer stopped a cleaning scan because it had written too many buffers.
- pgsql.bgwriter.buffers_backend_fsync - number of times a backend had to execute its own fsync call (normally the background writer handles those even when the backend does its own write).
- pgsql.bgwriter.buffers_clean - number of buffers written by the background writer.
- pgsql.bgwriter.buffers_checkpoint - number of buffers written during checkpoints.
- pgsql.bgwriter.checkpoints_timed - number of scheduled checkpoints that have been performed.
- pgsql.bgwriter.checkpoints_req - number of requested checkpoints that have been performed.
- pgsql.bgwriter.checkpoint_write_time - total amount of time that has been spent in the portion of checkpoint processing where files are written to disk, in milliseconds.
- pgsql.bgwriter.sync_time - total amount of time that has been spent in the portion of checkpoint processing where files are synchronized to disk.

**pgsql.autovacum.count[uri,username,password,dbName]** — number of autovacuum workers.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT count(*)
FROM pg_catalog.pg_stat_activity
WHERE query like '%%autovacuum%%'
AND state <> 'idle'
AND pid <> pg_catalog.pg_backend_pid()
```
> SQL query .

**pgsql.dbstat.sum[uri,username,password,dbName]** - statistics for all databases combined
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT row_to_json (T)
FROM (
SELECT
sum(numbackends) as numbackends
, sum(xact_commit) as xact_commit
, sum(xact_rollback) as xact_rollback
, sum(blks_read) as blks_read
, sum(blks_hit) as blks_hit
, sum(tup_returned) as tup_returned
, sum(tup_fetched) as tup_fetched
, sum(tup_inserted) as tup_inserted
, sum(tup_updated) as tup_updated
, sum(tup_deleted) as tup_deleted
, sum(conflicts) as conflicts
, sum(temp_files) as temp_files
, sum(temp_bytes) as temp_bytes
, sum(deadlocks) as deadlocks
, sum(checksum_failures) as checksum_failures
, sum(blk_read_time) as blk_read_time
, sum(blk_write_time) as blk_write_time
FROM pg_catalog.pg_stat_database
) T
```

> SQL query JSON format.

Then JSON is proceeded by dependent items of:

- pgsql.dbstat.numbackends - Number of backends currently connected to this database.
- pgsql.dbstat.sum.blk_read_time - Time spent reading data file blocks by backends in this database, in milliseconds.
- pgsql.dbstat.sum.blk_write_time - Time spent writing data file blocks by backends in this database, in milliseconds.
- pgsql.dbstat.sum.checksum_failures - Number of data page checksum failures detected (or on a shared object), or NULL if data checksums are not enabled.(PostgreSQL version 12 only).
- pgsql.dbstat.sum.xact_commit - Number of transactions in this database that have been committed.
- pgsql.dbstat.sum.conflicts - Number of queries canceled due to conflicts with recovery in this database. (Conflicts occur only on standby servers; see pg_stat_database_conflicts for details.)
- pgsql.dbstat.sum.deadlocks - Number of deadlocks detected in this database.
- pgsql.dbstat.sum.blks_read - Number of disk blocks read in this database.
- pgsql.dbstat.sum.blks_hit - Number of times disk blocks were found already in the buffer cache, so that a read was not necessary (this only includes hits in the PostgreSQL Pro buffer cache, not the operating system's file system cache).
- pgsql.dbstat.sum.temp_bytes - Total amount of data written to temporary files by queries in this database. All temporary files are counted, regardless of why the temporary file was created, and regardless of the log_temp_files setting.
- pgsql.dbstat.sum.temp_files - Number of temporary files created by queries in this database. All temporary files are counted, regardless of why the temporary file was created (e.g., sorting or hashing), and regardless of the log_temp_files setting.
- pgsql.dbstat.sum.xact_rollback - Number of transactions in this database that have been rolled back.
- pgsql.dbstat.sum.tup_deleted - Number of rows deleted by queries in this database.
- pgsql.dbstat.sum.tup_fetched - Number of rows fetched by queries in this database.
- pgsql.dbstat.sum.tup_inserted - Number of rows inserted by queries in this database.
- pgsql.dbstat.sum.tup_returned - Number of rows returned by queries in this database.
    pgsql.dbstat.sum.tup_updated - Number of rows updated by queries in this database.

**pgsql.dbstat[uri,username,password,dbName]** - statistics per database . Used in databases discovery.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT
json_object_agg(coalesce (datname,'null'), row_to_json(T))
FROM (
SELECT
datname
, numbackends as numbackends
, xact_commit as xact_commit
, xact_rollback as xact_rollback
, blks_read as blks_read
, blks_hit as blks_hit
, tup_returned as tup_returned
, tup_fetched as tup_fetched
, tup_inserted as tup_inserted
, tup_updated as tup_updated
, tup_deleted as tup_deleted
, conflicts as conflicts
, temp_files as temp_files
, temp_bytes as temp_bytes
, deadlocks as deadlocks
, %s as checksum_failures
, blk_read_time as blk_read_time
, blk_write_time as blk_write_time
FROM pg_catalog.pg_stat_database
) T;
```

> SQL query JSON format.

Then JSON is proceeded by dependent items of :

- pgsql.dbstat.numbackends["{#DBNAME}"] - Number of backends currently connected to this database.
- pgsql.dbstat.sum.blk_read_time["{#DBNAME}"] - Time spent reading data file blocks by backends in this database, in milliseconds. 
- pgsql.dbstat.sum.blk_write_time["{#DBNAME}"] - Time spent writing data file blocks by backends in this database, in milliseconds. 
- pgsql.dbstat.sum.checksum_failures["{#DBNAME}"] - Number of data page checksum failures detected (or on a shared object), or NULL if data checksums are not enabled.(PostgreSQL version 12 only) 
- pgsql.dbstat.blks_read.rate["{#DBNAME}"] - Number of disk blocks read in this database. 
- pgsql.dbstat.deadlocks.rate["{#DBNAME}"] - Number of deadlocks detected in this database. 
- pgsql.dbstat.blks_hit.rate["{#DBNAME}"] - Number of times disk blocks were found already in the buffer cache, so that a read was not necessary (this only includes hits in the PostgreSQL Pro buffer cache, not the operating system's file system cache).
- pgsql.dbstat.xact_rollback.rate["{#DBNAME}"] - Number of transactions in this database that have been rolled back. 
- pgsql.dbstat.xact_commit.rate["{#DBNAME}"] - Number of transactions in this database that have been committed. 
- pgsql.dbstat.tup_updated.rate["{#DBNAME}"] - Number of rows updated by queries in this database. 
- pgsql.dbstat.tup_returned.rate["{#DBNAME}"] - Number of rows returned by queries in this database. 
- pgsql.dbstat.tup_inserted.rate["{#DBNAME}"] - Number of rows inserted by queries in this database. 
- pgsql.dbstat.tup_fetched.rate["{#DBNAME}"] - Number of rows fetched by queries in this database. 
- pgsql.dbstat.tup_deleted.rate["{#DBNAME}"] - Number of rows deleted by queries in this database. 
- pgsql.dbstat.conflicts.rate["{#DBNAME}"] - Number of queries canceled due to conflicts with recovery in this database. (Conflicts occur only on standby servers; see pg_stat_database_conflicts for details.)
- pgsql.dbstat.temp_files.rate["{#DBNAME}"] - Number of temporary files created by queries in this database. All temporary files are counted, regardless of why the temporary file was created (e.g., sorting or hashing), and regardless of the log_temp_files setting.
- pgsql.dbstat.temp_bytes.rate["{#DBNAME}"] - Total amount of data written to temporary files by queries in this database. All temporary files are counted, regardless of why the temporary file was created, and regardless of the log_temp_files setting.

**pgsql.wal.stat[uri,username,password,dbName]** — returns WAL statistics.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
SELECT row_to_json(T)
FROM (
SELECT
pg_wal_lsn_diff(pg_current_wal_lsn(),'0/00000000') AS WRITE,
count(*)
FROM pg_ls_waldir() AS COUNT
) T;
```

> SQL query JSON format.

Then JSON is proceeded by dependent items of :
    - pgsql.wal.count — number of wal files.
    - pgsql.wal.write - wal lsn used (in bytes).

**pgsql.locks[uri,username,password,dbName]** — locks statistics per database. Used in databases discovery.
*Params:*
dbName — Database name. Optional.

*Returns:* Result of the

```sql
WITH T AS
(SELECT
db.datname dbname,
lower(replace(Q.mode, 'Lock', '')) AS MODE,
coalesce(T.qty, 0) val
FROM pg_database db
JOIN (
VALUES ('AccessShareLock') ,('RowShareLock') ,('RowExclusiveLock') ,('ShareUpdateExclusiveLock') ,('ShareLock') ,('ShareRowExclusiveLock') ,('ExclusiveLock') ,('AccessExclusiveLock')) Q(MODE) ON TRUE NATURAL
LEFT JOIN
(SELECT datname,
MODE,
count(MODE) qty
FROM pg_locks lc
RIGHT JOIN pg_database db ON db.oid = lc.database
GROUP BY 1, 2) T
WHERE NOT db.datistemplate
ORDER BY 1, 2)
SELECT json_object_agg(dbname, row_to_json(T2))
FROM
(SELECT dbname,
sum(val) AS total,
sum(CASE
WHEN MODE = 'accessexclusive' THEN val
END) AS accessexclusive,
sum (CASE
WHEN MODE = 'accessshare' THEN val
END) AS accessshare,
sum(CASE
WHEN MODE = 'exclusive' THEN val
END) AS EXCLUSIVE,
sum(CASE
WHEN MODE = 'rowexclusive' THEN val
END) AS rowexclusive,
sum(CASE
WHEN MODE = 'rowshare' THEN val
END) AS rowshare,
sum(CASE
WHEN MODE = 'share' THEN val
END) AS SHARE,
sum(CASE
WHEN MODE = 'sharerowexclusive' THEN val
END) AS sharerowexclusive,
sum(CASE
WHEN MODE = 'shareupdateexclusive' THEN val
END) AS shareupdateexclusive
FROM T
GROUP BY dbname) T2;
```

> SQL query JSON format.

Then JSON is proceeded by dependent items of:

- pgsql.locks.shareupdateexclusive["{#DBNAME}"]  - number of share update exclusive locks.
- pgsql.locks.accessexclusive["{#DBNAME}"]  - number of access exclusive locks.
- pgsql.locks.accessshare["{#DBNAME}"] - number of access share locks.
- pgsql.locks.exclusive["{#DBNAME}"]  - number of exclusive locks.
- pgsql.locks.rowexclusive["{#DBNAME}"]  - number of row exclusive locks.
- pgsql.locks.rowshare["{#DBNAME}"]  - number of row share locks.
- pgsql.locks.share["{#DBNAME}"]  - number of share locks.
- pgsql.locks.sharerowexclusive["{#DBNAME}"]  - number of share row exclusive locks.

**pgsql.pgsql.oldest.xid[uri,username,password,dbName]** — PostgreSQL age of the oldest XID.
*Params:*
dbName — Database name. Non-mandatory

*Returns:* Result of the

```sql
SELECT greatest(max(age(backend_xmin)), max(age(backend_xid)))
FROM pg_catalog.pg_stat_activity" SQL query.
```

**pgsql.uptime[uri,username,password,dbName]** — PostgreSQL uptime in ms.
*Params:*
dbName — Database name. Non-mandatory

*Returns:* Result of the

```sql
SELECT date_part('epoch', now() - pg_postmaster_start_time());
```

> SQL query in ms.

## Troubleshooting

The plugin uses Zabbix agent logs. To receive more detailed information about logged events, consider increasing a debug level of Zabbix agent.
