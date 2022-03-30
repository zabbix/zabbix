# PostgreSQL plugin
Provides native Zabbix solution for monitoring PostgreSQL (object-relational database system). 
It can monitor several PostgreSQL instances simultaneously, remote or local to the Zabbix Agent.
Native connection encryption is supported. The plugin keeps connections in the open state to reduce network 
congestion, latency, CPU and memory usage. Best for use in conjunction with the official 
[PostgreSQL template.](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/db/postgresql_agent2) 
You can extend it or create your template for your specific needs.

## Requirements
- Zabbix Agent 2
- Go >= 1.13 (required only to build from source)

## Supported versions
PostgreSQL, version 10, 11, 12

## Installation
The plugin is supplied as part of the Zabbix Agent 2 and does not require any special installation steps. 
Once Zabbix Agent 2 is installed, the plugin is ready to work. You only need to make sure a PostgreSQL instance is 
available for connection, and you have necessary access rights.

## Configuration
The Zabbix Agent's configuration file is used to configure plugins.

**Plugins.Postgres.CallTimeout** — The maximum time in seconds for waiting when a request has to be done.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

**Plugins.Postgres.Timeout** — The maximum time in seconds for waiting when a connection has to be established.  
*Default value:* equals the global Timeout configuration parameter.  
*Limits:* 1-30

**Plugins.Postgres.CustomQueriesPath** — Full pathname of a directory containing *.sql* files with custom queries.  
*Default value:* — (the feature is disabled by default)

**Plugins.Postgres.KeepAlive** — Sets a time for waiting before unused connections will be closed.  
*Default value:* 300 sec.  
*Limits:* 60-900

**Plugins.Postgres.Sessions.<session_name>.TLSConnect** — Encryption type for postgres connection. "*" should be replaced with a session name.
*Default value:* 
*Accepted values:*  required, verify_ca, verify_full

**Plugins.Postgres.Sessions.<session_name>.TLSCAFile** — Full pathname of a file containing the top-level CA(s) certificates for postgres
*Default value:* 

**Plugins.Postgres.Sessions.<session_name>.TLSCertFile** — Full pathname of a file containing the postgres certificate or certificate chain.
*Default value:* 

**Plugins.Postgres.Sessions.*.TLSKeyFile** — Full pathname of a file containing the postgres private key.
*Default value:* 

### Configuring connection
A connection can be configured using either keys' parameters or named sessions.     

*Notes*:  
* It is not possible to mix configuration using named sessions and keys' parameters simultaneously.
* You can leave any connection parameter empty, a default hard-coded value will be used in the such case.
* TLS information can be passed only with sessions.
* Embedded URI credentials (userinfo) are forbidden and will be ignored. So, you can't pass the credentials by this:   
  
      pgsql.ping[tcp://user:password@127.0.0.1/postgres] — WRONG  
  
  The correct way is:
    
      pgsql.ping[tcp://127.0.0.1,user,password,postgres]
      
* The only supported network schema for a URI are "tcp" and "unix".  
Examples of valid URIs:
    - tcp://127.0.0.1:5432
    - tcp://localhost
    - localhost
    - unix:/var/run/postgresql/.s.PGSQL.5432 (**Note:** a full socket file path expected, not a socket directory)
    - /var/run/postgresql/.s.PGSQL.5432
      
#### Using keys' parameters
The common parameters for all keys are: [ConnString][,User][,Password][,Database] 
Where ConnString can be either a URI or a session name.   
ConnString will be treated as a URI if no session with the given name is found.  
If you use ConnString as a session name, just skip the rest of the connection parameters.  
 
#### Using named sessions
Named sessions allow you to define specific parameters for each PostgreSQL instance. Currently, these are the
supported parameters: Uri, User, Password, Service, TLSConnect, TLSCAFile, TLSCertFile and TLSKeyFile. 
It's a bit more secure way to store credentials compared to item keys or macros.  

E.g: suppose you have two PostgreSQL instances: "Prod" and "Test". 
You should add the following options to the agent configuration file:   
       
    Plugins.Postgres.Sessions.Prod.Uri=tcp://192.168.1.1:5432
    Plugins.Postgres.Sessions.Prod.User=<UserForProd>
    Plugins.Postgres.Sessions.Prod.Password=<PasswordForProd>
    Plugins.Postgres.Sessions.Prod.Database=proddb
    Plugins.Postgres.Sessions.Prod.TLSConnect=verify_full
    Plugins.Postgres.Sessions.Prod.TLSCAFile=/path/to/ca_file
    Plugins.Postgres.Sessions.Prod.TLSCertFile=/path/to/cert_file
    Plugins.Postgres.Sessions.Prod.TLSKeyFile=/path/to/key_file
    
    Plugins.Postgres.Sessions.Test.Uri=tcp://192.168.0.1:5432
    Plugins.Postgres.Sessions.Test.User=<UserForTest>
    Plugins.Postgres.Sessions.Test.Password=<PasswordForTest>
    Plugins.Postgres.Sessions.Test.Service=testdb
    Plugins.Postgres.Sessions.Test.TLSConnect=verify_ca
    Plugins.Postgres.Sessions.Test.TLSCAFile=/path/to/test/ca_file
    Plugins.Postgres.Sessions.Test.TLSCertFile=/path/to/test/cert_file
    Plugins.Postgres.Sessions.Test.TLSKeyFile=/path/to/test/key_file
        
Then you will be able to use these names as the 1st parameter (ConnString) in keys instead of URIs, e.g:

    pgsql.ping[Prod]
    pgsql.ping[Test]

*Note*: sessions names are case-sensitive, the first letter of a name must be upper-cased.

## Supported keys
**pgsql.archive[\<commonParams\>]** — returns info about archive files.  
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
- pgsql.archive.count_archived_files — number of WAL files that have been successfully archived.
- pgsql.archive.failed_trying_to_archive — number of failed attempts for archiving WAL files.
- pgsql.archive.count_files_to_archive — number of files to archive.
- pgsql.archive.size_files_to_archive — size of files to archive.

**pgsql.autovacum.count[\<commonParams\>]** — number of autovacuum workers.    
*Returns:* Result of the
```sql
SELECT count(*)
FROM pg_catalog.pg_stat_activity
WHERE query like '%%autovacuum%%'
AND state <> 'idle'
AND pid <> pg_catalog.pg_backend_pid()
```
> SQL query.

**pgsql.bgwriter[\<commonParams\>]** — statistics about the background writer process's activity.  
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
- pgsql.bgwriter.buffers_alloc — number of buffers allocated.
- pgsql.bgwriter.buffers_backend — number of buffers written directly by a backend.
- pgsql.bgwriter.maxwritten_clean — number of times the background writer stopped a cleaning scan because it had written
 too many buffers.
- pgsql.bgwriter.buffers_backend_fsync — number of times a backend had to execute its own fsync call (normally the 
background writer handles those even when the backend does its own write).
- pgsql.bgwriter.buffers_clean — number of buffers written by the background writer.
- pgsql.bgwriter.buffers_checkpoint — number of buffers written during checkpoints.
- pgsql.bgwriter.checkpoints_timed — number of scheduled checkpoints that have been performed.
- pgsql.bgwriter.checkpoints_req — number of requested checkpoints that have been performed.
- pgsql.bgwriter.checkpoint_write_time — total amount of time has been spent in the portion of checkpoint processing 
where files are written to disk, in milliseconds.
- pgsql.bgwriter.sync_time — total amount of time has been spent in the portion of checkpoint processing where files
are synchronized to disk.

**pgsql.cache.hit[\<commonParams\>]** — cache hit rate.  
*Returns:* Result of the
```sql
SELECT round(sum(blks_hit)*100/sum(blks_hit+blks_read), 2)
FROM pg_catalog.pg_stat_database;
```
> SQL query in percentage.

**pgsql.connections[\<commonParams\>]** — connections by types.  
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
- pgsql.connections.active — the backend is executing a query.
- pgsql.connections.fastpath_function_call — the backend is executing a fast-path function.
- pgsql.connections.idle — the backend is waiting for a new client command.
- pgsql.connections.idle_in_transaction — the backend is in a transaction, but is not currently executing a query.
- pgsql.connections.prepared — number of prepared connections.
- pgsql.connections.total — total number of connection.
- pgsql.connections.total_pct — percentage of total connections in respect to ‘max_connections’ setting of PostgreSQL 
server.
- pgsql.connections.waiting — number of waiting connections.
- pgsql.connections.idle_in_transaction_aborted — This state is similar to idle in transaction, except one of the 
statements in the transaction caused an error.

**pgsql.custom.query[\<commonParams\>,queryName[,args...]]** — Returns result of a custom query.  
*Parameters:*  
queryName (required) — name of a custom query (must be equal to a name of a sql file without an extension).  
args (optional) — one or more arguments to pass to a query.

**pgsql.dbstat[\<commonParams\>]** — statistics per database. Used in databases discovery.      
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

Then JSON is proceeded by dependent items of:
- pgsql.dbstat.numbackends["{#DBNAME}"] — number of backends currently connected to this database.
- pgsql.dbstat.sum.blk_read_time["{#DBNAME}"] — time spent reading data file blocks by backends in this database, 
in milliseconds. 
- pgsql.dbstat.sum.blk_write_time["{#DBNAME}"] — time spent writing data file blocks by backends in this database, 
in milliseconds. 
- pgsql.dbstat.sum.checksum_failures["{#DBNAME}"] — number of data page checksum failures detected (or on a shared 
object), or NULL if data checksums are not enabled (PostgreSQL version 12 only). 
- pgsql.dbstat.blks_read.rate["{#DBNAME}"] — number of disk blocks read in this database. 
- pgsql.dbstat.deadlocks.rate["{#DBNAME}"] — number of deadlocks detected in this database. 
- pgsql.dbstat.blks_hit.rate["{#DBNAME}"] — number of times disk blocks were found already in the buffer cache, so that 
a read was not necessary (this only includes hits in the PostgreSQL Pro buffer cache, not the operating system's file 
system cache).
- pgsql.dbstat.xact_rollback.rate["{#DBNAME}"] — number of transactions in this database that have been rolled back. 
- pgsql.dbstat.xact_commit.rate["{#DBNAME}"] — number of transactions in this database that have been committed. 
- pgsql.dbstat.tup_updated.rate["{#DBNAME}"] — number of rows updated by queries in this database. 
- pgsql.dbstat.tup_returned.rate["{#DBNAME}"] — number of rows returned by queries in this database. 
- pgsql.dbstat.tup_inserted.rate["{#DBNAME}"] — number of rows inserted by queries in this database. 
- pgsql.dbstat.tup_fetched.rate["{#DBNAME}"] — number of rows fetched by queries in this database. 
- pgsql.dbstat.tup_deleted.rate["{#DBNAME}"] — number of rows deleted by queries in this database. 
- pgsql.dbstat.conflicts.rate["{#DBNAME}"] — number of queries canceled due to conflicts with recovery in this database.
Conflicts occur only on standby servers; see pg_stat_database_conflicts for details.
- pgsql.dbstat.temp_files.rate["{#DBNAME}"] — number of temporary files created by queries in this database. 
All temporary files are counted, regardless of why the temporary file was created (e.g., sorting or hashing), and 
regardless of the log_temp_files setting.
- pgsql.dbstat.temp_bytes.rate["{#DBNAME}"] — total amount of data written to temporary files by queries in this 
database. All temporary files are counted, regardless of why the temporary file was created, and regardless of the 
log_temp_files setting.

**pgsql.dbstat.sum[\<commonParams\>]** — statistics for all databases combined.      
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
- pgsql.dbstat.numbackends — number of backends currently connected to this database.
- pgsql.dbstat.sum.blk_read_time — time spent reading data file blocks by backends in this database, in milliseconds.
- pgsql.dbstat.sum.blk_write_time — time spent writing data file blocks by backends in this database, in milliseconds.
- pgsql.dbstat.sum.checksum_failures — number of data page checksum failures detected (or on a shared object), or NULL 
if data checksums are not enabled (PostgreSQL version 12 only).
- pgsql.dbstat.sum.xact_commit — number of transactions in this database that have been committed.
- pgsql.dbstat.sum.conflicts — number of queries canceled due to conflicts with recovery in this database. 
Conflicts occur only on standby servers; see pg_stat_database_conflicts for details.
- pgsql.dbstat.sum.deadlocks — number of deadlocks detected in this database.
- pgsql.dbstat.sum.blks_read — number of disk blocks read in this database.
- pgsql.dbstat.sum.blks_hit — number of times disk blocks were found already in the buffer cache, so that a read was not
necessary (this only includes hits in the PostgreSQL Pro buffer cache, not the operating system's file system cache).
- pgsql.dbstat.sum.temp_bytes — total amount of data written to temporary files by queries in this database. All 
temporary files are counted, regardless of why the temporary file was created, and regardless of the log_temp_files 
setting.
- pgsql.dbstat.sum.temp_files — number of temporary files created by queries in this database. All temporary files are 
counted, regardless of why the temporary file was created (e.g., sorting or hashing), and regardless of the 
log_temp_files setting.
- pgsql.dbstat.sum.xact_rollback — number of transactions in this database that have been rolled back.
- pgsql.dbstat.sum.tup_deleted — number of rows deleted by queries in this database.
- pgsql.dbstat.sum.tup_fetched — number of rows fetched by queries in this database.
- pgsql.dbstat.sum.tup_inserted — number of rows inserted by queries in this database.
- pgsql.dbstat.sum.tup_returned — number of rows returned by queries in this database.
- pgsql.dbstat.sum.tup_updated — number of rows updated by queries in this database.

**pgsql.db.age[\<commonParams\>]** — age of the oldest xid for the specific database. Used in databases discovery.  
*Returns:* Result of the
```sql
SELECT age(datfrozenxid)
FROM pg_catalog.pg_database
WHERE datistemplate = false
AND datname = <dbName>
```
> SQL query for specific database in transactions.

**pgsql.db.bloating_tables[\<commonParams\>]** — number of bloating tables per database. Used in databases discovery.  
*Returns:* Result of the
```sql
SELECT count(*)
FROM pg_catalog.pg_stat_all_tables
WHERE (n_dead_tup/(n_live_tup+n_dead_tup)::float8) > 0.2
AND (n_live_tup+n_dead_tup) > 50;
```
> SQL query.

Result of this query differs depending on the database to which agent is currently connected.

**pgsql.db.discovery[\<commonParams\>]** — Databases discovery.  
*Returns:* Result of the
```sql
SELECT json_build_object('data',json_agg(json_build_object('{#DBNAME}',d.datname)))
FROM pg_database
WHERE NOT datistemplate
AND datallowconn;
```
> SQL query in LLD JSON format.

**pgsql.db.size[\<commonParams\>]** — database size in bytes. Used in databases discovery.  
*Returns:* Result of the
```sql
SELECT pg_database_size(datname::text)
FROM pg_catalog.pg_database
WHERE datistemplate = false
AND datname = <dbName>;
```
> SQL query for specific database in bytes.

**pgsql.locks[\<commonParams\>]** — locks statistics per database. Used in databases discovery.  
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
- pgsql.locks.shareupdateexclusive["{#DBNAME}"] — number of share update exclusive locks.
- pgsql.locks.accessexclusive["{#DBNAME}"] — number of access exclusive locks.
- pgsql.locks.accessshare["{#DBNAME}"] — number of access share locks.
- pgsql.locks.exclusive["{#DBNAME}"] — number of exclusive locks.
- pgsql.locks.rowexclusive["{#DBNAME}"] — number of row exclusive locks.
- pgsql.locks.rowshare["{#DBNAME}"] — number of row share locks.
- pgsql.locks.share["{#DBNAME}"] — number of share locks.
- pgsql.locks.sharerowexclusive["{#DBNAME}"] — number of share row exclusive locks.

**pgsql.pgsql.oldest.xid[\<commonParams\>]** — PostgreSQL age of the oldest XID.  
*Returns:* Result of the
```sql
SELECT greatest(max(age(backend_xmin)), max(age(backend_xid)))
FROM pg_catalog.pg_stat_activity" SQL query.
```

**pgsql.ping[\<commonParams\>]** — tests whether a connection is alive or not.  
*Returns:*
- "1" if the connection is alive.
- "0" if the connection is broken (returned if there was any error during the test, including AUTH and configuration issues).

**pgsql.queries[\<commonParams\>,TimePeriod]** - queries metrics by execution time.
*Parameters:*  
TimePeriod (required) — execution time limit for count of slow queries. (must be an integer, must be greater than 0).

*Returns:* Result of the
```sql
WITH T AS
(SELECT db.datname,
coalesce(T.query_time_max, 0) query_time_max,
coalesce(T.tx_time_max, 0) tx_time_max,
coalesce(T.mro_time_max, 0) mro_time_max,
coalesce(T.query_time_sum, 0) query_time_sum,
coalesce(T.tx_time_sum, 0) tx_time_sum,
coalesce(T.mro_time_sum, 0) mro_time_sum,
coalesce(T.query_slow_count, 0) query_slow_count,
coalesce(T.tx_slow_count, 0) tx_slow_count,
coalesce(T.mro_slow_count, 0) mro_slow_count
FROM pg_database db NATURAL
LEFT JOIN (
SELECT datname,
extract(epoch FROM now())::integer ts,
coalesce(max(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle', 'idle in transaction', 'idle in transaction (aborted)') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) query_time_max,
coalesce(max(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) tx_time_max,
coalesce(max(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle') AND query ~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) mro_time_max,
coalesce(sum(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle', 'idle in transaction', 'idle in transaction (aborted)') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) query_time_sum,
coalesce(sum(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) tx_time_sum,
coalesce(sum(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle') AND query ~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) mro_time_sum,
coalesce(sum((extract('epoch' FROM (clock_timestamp() - query_start)) > %d)::integer * (state NOT IN ('idle', 'idle in transaction', 'idle in transaction (aborted)') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) query_slow_count,
coalesce(sum((extract('epoch' FROM (clock_timestamp() - query_start)) > %d)::integer * (state NOT IN ('idle') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) tx_slow_count,
coalesce(sum((extract('epoch' FROM (clock_timestamp() - query_start)) > %d)::integer * (state NOT IN ('idle') AND query ~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) mro_slow_count
FROM pg_stat_activity
WHERE pid <> pg_backend_pid()
GROUP BY 1) T
WHERE NOT db.datistemplate )
SELECT json_object_agg(datname, row_to_json(T))
FROM T
```
> SQL query JSON format.

Then JSON is proceeded by dependent items of:
- pgsql.queries.mro.time_max["{#DBNAME}"] - max maintenance query time.
- pgsql.queries.query.time_max["{#DBNAME}"] - max query time.
- pgsql.queries.tx.time_max["{#DBNAME}"] - max transaction query time.
- pgsql.queries.mro.slow_count["{#DBNAME}"] - slow maintenance query count.
- pgsql.queries.query.slow_count["{#DBNAME}"] - slow query count.
- pgsql.queries.tx.slow_count["{#DBNAME}"] - slow transaction query count.
- pgsql.queries.mro.time_sum["{#DBNAME}"]  - sum maintenance query time.
- pgsql.queries.query.time_sum["{#DBNAME}"] - sum query time.
- pgsql.queries.tx.time_sum["{#DBNAME}"] - sum transaction query time.

**pgsql.replication.count[uri,username,password]** — number of standby servers.  
*Returns:* Result of the
```sql
SELECT count(*) FROM pg_stat_replication
```
> SQL query.

**pgsql.replication_lag.b[uri,username,password]** — replication lag in bytes.  
*Returns:* Result of the
```sql
SELECT pg_catalog.pg_wal_lsn_diff (received_lsn, pg_last_wal_replay_lsn())
FROM pg_stat_wal_receiver;
```
> SQL query in bytes

**pgsql.replication_lag.sec[uri,username,password]** — replication lag in seconds.  
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

**pgsql.replication.recovery_role[uri,username,password]** — recovery status.    
*Returns:*
- 1 — recovery is still in progress (standby mode)
- 0 — master mode.

**pgsql.replication.status[uri,username,password]** — status of replication.  
*Returns:*
- 0 — streaming is down
- 1 — streaming is up
- 2 — mastermode

**pgsql.replication.process[uri,username,password]** — flush lag, write lag and replay lag per each sender process.
*Returns:* Result of the
```sql
SELECT json_object_agg(application_name, row_to_json(T))
FROM (
	SELECT
		CONCAT(application_name, ' ', pid) AS application_name,
		EXTRACT(epoch FROM COALESCE(flush_lag,'0'::interval)) as flush_lag, 
		EXTRACT(epoch FROM COALESCE(replay_lag,'0'::interval)) as replay_lag,
		EXTRACT(epoch FROM COALESCE(write_lag, '0'::interval)) as write_lag
		FROM pg_stat_replication
	) T; 
```

**pgsql.replication.process.discovery[uri,username,password]** - replication procces name discovery. 
*Returns:* Result of the
```sql
SELECT 
json_build_object('data',
json_agg(json_build_object('{#APPLICATION_NAME}',
CONCAT(application_name, ' ', pid))))		
FROM 
pg_stat_replication
```

**pgsql.uptime[\<commonParams\>]** — PostgreSQL uptime, in milliseconds.  
*Returns:* Result of the
```sql
SELECT date_part('epoch', now() - pg_postmaster_start_time());
```
> SQL query in ms.

**pgsql.wal.stat[\<commonParams\>]** — returns WAL statistics.  
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

Then JSON is proceeded by dependent items of:
- pgsql.wal.count — number of wal files.
- pgsql.wal.write — wal lsn used, in bytes.

## Custom queries
It's possible to extend functionality of the plugin using user-defined queries. To do that you should place all your
queries in a directory specified in Plugins.Postgres.CustomQueriesPath (there is no default path) as *.sql files.
For example, you have a tree:

    /etc/zabbix/postgres/sql/  
    ├── long_tx.sql
    ├── payment.sql    
    └── top_proc.sql
     
You should set Plugins.Postgres.CustomQueriesPath=/etc/zabbix/postgres/sql     
     
So, when the queries are in place, you can execute them:
  
    pgsql.custom.query[<commonParams>,top_proc]  
    pgsql.custom.query[<commonParams>,long_tx,600]
          
You can pass as many parameters to a query as you need.   
The syntax for placeholder parameters uses "$#", where "#" is an index number of a parameter.   
E.g: 
```
/* payment.sql */

SELECT 
    amount 
FROM 
    payment 
WHERE
    user = $1
    AND service_id = $2
    AND date = $3
``` 

    pgsql.custom.query[<commonParams>,payment,"John Doe",1,"10/25/2020"]

## Troubleshooting
The plugin uses Zabbix agent's logs. You can increase debugging level of Zabbix Agent if you need more details about 
what is happening.
