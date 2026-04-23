
# PostgreSQL by Zabbix agent active

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

**Note:** if you want to use SSL/TLS encryption to protect communications with the remote PostgreSQL instance, you can modify the connection string in user parameters. For example, to enable required encryption in transport mode without identity checks you could append `?sslmode=required` to the end of the connection string for all keys that use `psql`:

```bash
UserParameter=pgsql.bgwriter.above.17.get[*], psql -qtAX postgresql://"$3":"$4"@"$1":"$2"/"$5"?sslmode=required -f "/var/lib/zabbix/postgresql/pgsql.bgwriter.above.17.get.sql"
```

Consult the PostgreSQL documentation about [`protection modes`](https://www.postgresql.org/docs/current/libpq-ssl.html#LIBPQ-SSL-PROTECTION) and [`client connection parameters`](https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-CONNECT-SSLMODE).

Also, it is assumed that you set up the PostgreSQL instance to work in the desired encryption mode. Check the [`PostgreSQL documentation`](https://www.postgresql.org/docs/current/ssl-tcp.html) for details.

4. Edit the `pg_hba.conf` configuration file to allow connections for the user `zbx_monitor`. For example, you could add one of the following rows to allow local TCP connections from the same host:

```bash
  # TYPE  DATABASE    USER          ADDRESS        METHOD
  host    all         zbx_monitor   localhost      trust
  host    all         zbx_monitor   127.0.0.1/32   md5
  host    all         zbx_monitor   ::1/128        scram-sha-256
```

For more information please read the PostgreSQL documentation `https://www.postgresql.org/docs/current/auth-pg-hba-conf.html`.

5. Specify the host name or IP address in the `{$PG.HOST}` macro. Adjust the port number with `{$PG.PORT}` macro if needed.

6. Set the password that you specified in step 1 in the macro `{$PG.PASSWORD}`.

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
|{$PG.LOCKS.MAX.WARN}|<p>Maximum number of locks for trigger expression.</p>|`100`|
|{$PG.PING_TIME.MAX.WARN}|<p>Maximum time of connection response for trigger expression.</p>|`1s`|
|{$PG.REPLICATION_LAG.MAX.WARN}|<p>Maximum replication lag time for trigger expression.</p>|`10m`|
|{$PG.CACHE_HIT_RATIO.MIN.WARN}|<p>Minimum cache hit ratio percentage for trigger expression.</p>|`90`|
|{$PG.CHECKPOINTS_REQ.MAX.WARN}|<p>Maximum required checkpoint occurrences for trigger expression.</p>|`5`|
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
|{$PG.FROZEN.XID.AUTOVACUUM.AVG}|<p>Average frozen XID before autovacuum percentage for trigger expression.</p>|`20`|
|{$PG.FROZEN.XID.AUTOVACUUM.HIGH}|<p>High frozen XID before autovacuum percentage for trigger expression.</p>|`40`|
|{$PG.FROZEN.XID.STOP.AVG}|<p>Average frozen XID before stop percentage for trigger expression.</p>|`50`|
|{$PG.FROZEN.XID.STOP.HIGH}|<p>High frozen XID before stop percentage for trigger expression.</p>|`70`|
|{$PG.HOST}|<p>Hostname or IP of PostgreSQL host.</p>|`localhost`|
|{$PG.PORT}|<p>PostgreSQL service port.</p>|`5432`|
|{$PG.USER}|<p>PostgreSQL username.</p>|`zbx_monitor`|
|{$PG.PASSWORD}|<p>PostgreSQL user password.</p>||
|{$PG.DATABASE}|<p>Default PostgreSQL database for the connection.</p>|`postgres`|
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
|{$PG.LLD.FILTER.SLRU.MATCHES}|<p>Filter for PostgreSQL SLRU discovery by name.</p>|`.*`|
|{$PG.LLD.FILTER.SLRU.NOT_MATCHES}|<p>Exclude filter for PostgreSQL SLRU discovery by name.</p>|`^$`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Oldest active XID|<p>Age of the oldest active transaction (XID) among live backends.</p>|Zabbix agent (active)|pgsql.oldest.active.xid["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Get archive inventory|<p>Collects archive metrics from `pg_stat_archiver`.</p>|Zabbix agent (active)|pgsql.archive.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Archiver: Archived total|<p>Count of archived files.</p>|Dependent item|pgsql.archive.count.archived.files<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.archived_count`</p></li></ul>|
|Archiver: Failed attempts|<p>Count of failed attempts to archive files.</p>|Dependent item|pgsql.archive.failed.trying.to.archive<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.failed_count`</p></li></ul>|
|Archiver: Files to archive|<p>Count of files to archive.</p>|Dependent item|pgsql.archive.count.files.to.archive<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.count_files`</p></li></ul>|
|Archiver: Pending size|<p>Size of files to archive.</p>|Dependent item|pgsql.archive.size.files.to.archive<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.size_files`</p></li></ul>|
|Archiver: Last archived time|<p>Timestamp of last archived file in epoch seconds, 0 if never archived.</p>|Dependent item|pgsql.archive.last.archived.time<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.last_archived_time`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Archiver: Last failed time|<p>Timestamp of last failed archive attempt in epoch seconds, 0 if never failed.</p>|Dependent item|pgsql.archive.last.failed.time<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.last_failed_time`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get runtime inventory|<p>Collects `runtime` metrics.</p>|Zabbix agent (active)|pgsql.runtime.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Runtime: Network: Get data|<p>Extracts `network` metrics from `runtime` metrics.</p>|Dependent item|pgsql.runtime.network.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.network`</p></li></ul>|
|Runtime: Network: TCP timeout|<p>Time before unacknowledged data causes connection close:</p><p>0 - Operating system default value</p><p>> 0 - custom value, in ms</p>|Dependent item|pgsql.runtime.network.tcp.user.timeout<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tcp_user_timeout`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Runtime: Network: TCP keepalives idle|<p>Idle time before sending a TCP keepalive signal.</p>|Dependent item|pgsql.runtime.network.tcp.keepalives.idle<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tcp_keepalives_idle`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Runtime: Network: TCP keepalives count|<p>Number of keepalive probes sent before connection failure:</p><p>0 - disabled (no keepalive probes)</p><p>> 0 - probes before connection is considered lost</p>|Dependent item|pgsql.runtime.network.tcp.keepalives.count<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tcp_keepalives_count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Runtime: Network: TCP keepalives interval|<p>Interval between keepalive probes.</p>|Dependent item|pgsql.runtime.network.tcp.keepalives.interval<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tcp_keepalives_interval`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Runtime: Network: TCP keepalive total time|<p>Total time before a TCP connection is considered dead based on keepalive settings.</p>|Calculated|pgsql.runtime.network.tcp.keepalive.total<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Runtime: Network: Connection check interval|<p>Connection check interval during execution:</p><p>0 - disabled</p><p>> 0 = interval, in ms</p>|Dependent item|pgsql.runtime.network.connection.check.interval<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.client_connection_check_interval`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Runtime: SSL: Get data|<p>Extracts `ssl` metrics from `runtime` metrics.</p>|Dependent item|pgsql.runtime.ssl.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.ssl`</p></li></ul>|
|Runtime: SSL: Min version|<p>Minimum TLS protocol version accepted for secure connections:</p><p>- TLSv1</p><p>- TLSv1.1</p><p>- TLSv1.2</p><p>- TLSv1.3</p>|Dependent item|pgsql.runtime.ssl.min.protocol.version<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.ssl_min_protocol_version`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Runtime: SSL: Server ciphers|<p>Use server's SSL cipher preference order instead of client's:</p><p>on - server decides cipher order, by default</p><p>off - client decides cipher order</p>|Dependent item|pgsql.runtime.ssl.prefer.server.ciphers<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.ssl_prefer_server_ciphers`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Runtime: Unix socket: Get data|<p>Extracts `unix_socket` metrics from `runtime` metrics.</p>|Dependent item|pgsql.runtime.unix.socket.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.unix_socket`</p></li></ul>|
|Runtime: Unix socket: Directory|<p>Directory where Unix-domain sockets are created for client connections:</p><p>- empty - no Unix sockets (TCP only)</p><p>- path - use specified directory</p><p>- multiple - comma-separated directories</p><p>- @prefix - abstract socket (Linux only)</p>|Dependent item|pgsql.runtime.unix.socket.directory<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.unix_socket_directories`</p></li></ul>|
|Runtime: Unix socket: Permissions|<p>Permissions for Unix-domain sockets:</p><p>- 0777 - allow all connections, by default</p><p>- 0770 - owner and group only</p><p>- 0700 - owner only</p><p></p><p>Note:</p><p>4 - read</p><p>2 - write</p><p>1 - execute</p><p>rwx = 4 + 2 + 1 = 7</p>|Dependent item|pgsql.runtime.unix.socket.permissions<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.unix_socket_permissions`</p></li></ul>|
|Get WAL lifecycle inventory|<p>Collects WAL lifecycle metrics covering WAL `creation`, `replication delivery`, and `retention/storage` behavior.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Zabbix agent (active)|pgsql.wal.lifecycle.above.14.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get relation inventory|<p>Collects `relation size` metrics from the primary core database.</p>|Zabbix agent (active)|pgsql.relation.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Cache hit ratio, %|<p>Cache hit ratio.</p>|Zabbix agent (active)|pgsql.cache.hit["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Security: Get data|<p>Returns PostgreSQL instance security-related access configuration as a JSON object derived from key server settings.</p><p>Each field indicates the presence of a specific access-related condition:</p><p>0 - The condition is not present</p><p>1 - The condition is present</p>|Zabbix agent (active)|pgsql.security.access.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.security`</p></li></ul>|
|Security: Authentication|<p>Indicates authentication methods based on current PostgreSQL instance settings.</p><p>Conditions:</p><p>0 - SCRAM authentication is configured</p><p>1 - Other authentication methods (md5 or password) are configured</p>|Dependent item|pgsql.security.access.authentication<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.auth_non_scram_access`</p></li></ul>|
|Security: Listening|<p>Indicates listening status on all network interfaces based on current PostgreSQL instance settings.</p><p>Conditions:</p><p>0 - Listening is restricted to specific interfaces</p><p>1 - Listening is set to all interfaces (*)</p>|Dependent item|pgsql.security.access.listening<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.host_all_access`</p></li></ul>|
|Security: SSL|<p>Indicates SSL connection status based on current PostgreSQL instance settings.</p><p>Conditions:</p><p>0 - SSL not active</p><p>1 - SSL is active</p>|Dependent item|pgsql.security.access.ssl<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.ssl_required_access`</p></li></ul>|
|Config hash|<p>PostgreSQL configuration hash.</p>|Zabbix agent (active)|pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get connections inventory|<p>Collects all `connection` metrics from `pg_stat_activity`.</p>|Zabbix agent (active)|pgsql.connections.sum.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Connections: Active|<p>Total number of connections executing a query.</p>|Dependent item|pgsql.connections.sum.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p></li></ul>|
|Connections: Idle|<p>Total number of connections waiting for a new client command.</p>|Dependent item|pgsql.connections.sum.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li></ul>|
|Connections: Idle in transaction|<p>Total number of connections in a transaction state but not executing a query.</p>|Dependent item|pgsql.connections.sum.idle_in_transaction<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction`</p></li></ul>|
|Connections: Prepared|<p>Total number of prepared transactions:</p><p>https://www.postgresql.org/docs/current/sql-prepare-transaction.html</p>|Dependent item|pgsql.connections.sum.prepared<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.prepared`</p></li></ul>|
|Connections: Total|<p>Total number of connections.</p>|Dependent item|pgsql.connections.sum.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li></ul>|
|Connections: Total, in %|<p>Total number of connections, in percent.</p>|Dependent item|pgsql.connections.sum.total_pct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_pct`</p></li></ul>|
|Connections: Waiting|<p>Total number of waiting connections:</p><p>https://www.postgresql.org/docs/current/monitoring-stats.html#WAIT-EVENT-TABLE</p>|Dependent item|pgsql.connections.sum.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting`</p></li></ul>|
|Connections: Idle in transaction (aborted)|<p>Total number of connections in a transaction state but not executing a query, and where one of the statements in the transaction caused an error.</p>|Dependent item|pgsql.connections.sum.idle_in_transaction_aborted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle_in_transaction_aborted`</p></li></ul>|
|Connections: Disabled|<p>Total number of disabled connections.</p>|Dependent item|pgsql.connections.sum.disabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disabled`</p></li></ul>|
|Get dbstat sum inventory|<p>Collects aggregated metrics from `pg_stat_database` across all databases.</p>|Zabbix agent (active)|pgsql.dbstat.sum.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Dbstat sum: Blocks read time|<p>Total time spent reading data file blocks by backends.</p>|Dependent item|pgsql.dbstat.sum.blocks.read.time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_read_time`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Dbstat sum: Blocks write time|<p>Total time spent writing data file blocks by backends.</p>|Dependent item|pgsql.dbstat.sum.blocks.write.time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blk_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Dbstat sum: Transactions committed|<p>Number of commits in total.</p>|Dependent item|pgsql.dbstat.sum.transactions.committed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_commit`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Transactions rolled back|<p>Number of rolled back transactions.</p>|Dependent item|pgsql.dbstat.sum.transactions.rolled.back<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_rollback`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Deadlocks|<p>Number of deadlocks detected.</p>|Dependent item|pgsql.dbstat.sum.deadlocks.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlocks`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Replication conflicts|<p>Number of recovery conflicts (standby only).</p>|Dependent item|pgsql.dbstat.sum.replication.conflicts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conflicts`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Blocks read|<p>Number of disk blocks read.</p>|Dependent item|pgsql.dbstat.sum.blocks.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_read`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Buffer cache hits|<p>Number of buffer cache hits per second.</p>|Dependent item|pgsql.dbstat.sum.blocks.hit.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_hit`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Temporary files files|<p>Number of temporary files created.</p>|Dependent item|pgsql.dbstat.sum.temporary.files<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_files`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Temporary bytes|<p>Amount of data written to temporary files.</p>|Dependent item|pgsql.dbstat.sum.temporary.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_bytes`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Rows returned|<p>Number of rows returned by queries.</p>|Dependent item|pgsql.dbstat.sum.rows.returned<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tup_returned`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Rows fetched|<p>Number of rows fetched by queries.</p>|Dependent item|pgsql.dbstat.sum.rows.fetched<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tup_fetched`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Rows inserted|<p>Number of rows inserted by queries.</p>|Dependent item|pgsql.dbstat.sum.rows.inserted<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tup_inserted`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Rows updated|<p>Number of rows updated by queries.</p>|Dependent item|pgsql.dbstat.sum.rows.updated<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tup_updated`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Rows deleted|<p>Number of rows deleted by queries.</p>|Dependent item|pgsql.dbstat.sum.rows.deleted<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.tup_deleted`</p></li><li>Change per second</li></ul>|
|Dbstat sum: Active connections|<p>Number of active backend connections.</p>|Dependent item|pgsql.dbstat.sum.backends.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numbackends`</p></li></ul>|
|Get dbstat inventory|<p>Collects all metrics from `pg_stat_database` per database.</p>|Zabbix agent (active)|pgsql.dbstat.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get SLRU inventory|<p>Collects SLRU (Simple Least Recently Used) cache statistics from `pg_stat_slru`.</p>|Zabbix agent (active)|pgsql.slru.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get locks inventory|<p>Collects all metrics from `pg_locks` per database.</p>|Zabbix agent (active)|pgsql.locks.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get replication process inventory|<p>Collects WAL sender replication metrics from `pg_stat_replication`.</p>|Zabbix agent (active)|pgsql.replication.process.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Ping time|<p>Used to get the `SELECT 1` query execution time.</p>|Zabbix agent (active)|pgsql.ping.time["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Regular expression: `Time:\s+(\d+\.\d+)\s+ms \1`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Ping|<p>PostgreSQL server availability check. Returns `0` if the query fails.</p>|Zabbix agent (active)|pgsql.ping["{$PG.HOST}","{$PG.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Replication overview: Get data|<p>Collects PostgreSQL replication overview metrics.</p>|Zabbix agent (active)|pgsql.replication.overview.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{$PG.QUERY_EXECUTION_TIME.MAX.WARN}"]|
|Replication overview: Replica count|<p>Number of replica servers.</p>|Dependent item|pgsql.replication.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replication_count`</p></li></ul>|
|Replication overview: Recovery role|<p>Replication role:</p><p>0 - Primary (master server, sends data)</p><p>1 - Standby (replica server, receives data)</p>|Dependent item|pgsql.replication.recovery.role<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.recovery_role`</p></li></ul>|
|Replication overview: Status|<p>Shows replication streaming state:</p><p>0 - Disabled (replication is disabled)</p><p>1 - Enabled (replication is enabled)</p><p>2 - Standalone (primary with no replicas)</p>|Dependent item|pgsql.replication.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replication_status`</p></li></ul>|
|Replication overview: Lag seconds|<p>Replication lag with master, in seconds.</p>|Dependent item|pgsql.replication.lag.sec<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.replication_lag_sec`</p></li></ul>|
|Replication overview: Lag in bytes|<p>Replication lag with master, in bytes.</p>|Dependent item|pgsql.replication.lag.bytes<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.replication_lag_bytes`</p></li></ul>|
|Get latency inventory|<p>Collects `longest running query`, `transaction`, `maintenance operation`, and `slow query` metrics.</p>|Zabbix agent (active)|pgsql.latency.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{$PG.QUERY_EXECUTION_TIME.MAX.WARN}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Uptime|<p>Time since the server started.</p>|Zabbix agent (active)|pgsql.uptime["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Build: Get workload status|<p>Extracts `pg_stat_statements` status (`workload` availability) and PostgreSQL version.</p>|Zabbix agent (active)|pgsql.workload.status.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Build: Get version (numeric)|<p>Reports the version number of the server as an integer.</p>|Zabbix agent (active)|pgsql.version.numeric["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Build: Version validator|<p>Validates the PostgreSQL version and sets version macros for LLDs.</p>|Dependent item|pgsql.version.validator<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Build: Get data|<p>Retrieves PostgreSQL version and build details, including platform, compiler, and package information.</p>|Zabbix agent (active)|pgsql.version.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Build: Version|<p>PostgreSQL version (major.minor).</p>|Dependent item|pgsql.version.major.minor<p>**Preprocessing**</p><ul><li><p>Regular expression: `PostgreSQL ([0-9.]+) \1`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Build: Package info|<p>Distribution / package build information.</p>|Dependent item|pgsql.version.package<p>**Preprocessing**</p><ul><li><p>Regular expression: `\(([^)]+)\) \1`</p><p>⛔️Custom on fail: Set value to: `unknown package`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Build: Platform|<p>Build platform / architecture.</p>|Dependent item|pgsql.version.platform<p>**Preprocessing**</p><ul><li><p>Regular expression: `on ([^,]+) \1`</p><p>⛔️Custom on fail: Set value to: `unknown platform`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Build: Compiler|<p>Compiler used to build PostgreSQL.</p>|Dependent item|pgsql.version.compiler<p>**Preprocessing**</p><ul><li><p>Regular expression: `compiled by ([^ (]+) \1`</p><p>⛔️Custom on fail: Set value to: `unknown compiler`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Build: Compiler package info|<p>Compiler package / distribution info.</p>|Dependent item|pgsql.version.compiler.pkg<p>**Preprocessing**</p><ul><li><p>Regular expression: `compiled by [^(]+\(([^)]+)\) \1`</p><p>⛔️Custom on fail: Set value to: `unknown compiler package`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Build: Compiler version|<p>Compiler version number.</p>|Dependent item|pgsql.version.compiler.version<p>**Preprocessing**</p><ul><li><p>Regular expression: `\) ([0-9.]+), \1`</p><p>⛔️Custom on fail: Set value to: `unknown compiler version`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Build: Word size|<p>PostgreSQL word size (32-bit / 64-bit).</p>|Dependent item|pgsql.version.wordsize<p>**Preprocessing**</p><ul><li><p>Regular expression: `,\s*([0-9]+)\s*[- ]?bit \1-bit`</p><p>⛔️Custom on fail: Set value to: `unknown architecture`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|LRQ: Get data|<p>Returns data about the currently active LRQ (Longest Running Query) and its execution duration from `pg_stat_activity`.</p>|Zabbix agent (active)|pgsql.longest.running.query.data.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|LRQ: Query name|<p>Query name of the current LRQ (Longest Running Query).</p><p>The value represents a shortened form of the active query to improve readability in the UI.</p><p>Note:</p><p>Query text is normalized (whitespace collapsed) and shortened to the first two words followed by "...".</p><p>Returns `no query` if no usable query is available.</p>|Dependent item|pgsql.longest.running.query.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query`</p></li><li><p>Regular expression: `^\s*([^\s]+)\s+([^\s]+).* \1 \2...`</p><p>⛔️Custom on fail: Set value to: `no query`</p></li></ul>|
|LRQ: PID|<p>PID of the current LRQ (Longest Running Query).</p>|Dependent item|pgsql.longest.running.query.pid<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pid`</p></li></ul>|
|LRQ: Duration|<p>Duration of the current LRQ (Longest Running Query).</p>|Dependent item|pgsql.longest.running.query.duration<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.duration`</p></li><li><p>Check for error using a regular expression: `^-\d+(\.\d+)?$<br>\0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get WAL overview inventory|<p>Collects WAL metrics for `write`, `receive`, and `count` metrics.</p>|Zabbix agent (active)|pgsql.wal.overview.inventory.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|WAL: Bytes written|<p>WAL write, in bytes.</p>|Dependent item|pgsql.wal.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.write`</p></li><li>Change per second</li></ul>|
|WAL: Segments count|<p>Number of WAL segments.</p>|Dependent item|pgsql.wal.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count`</p></li></ul>|
|WAL: Bytes received|<p>WAL receive, in bytes.</p>|Dependent item|pgsql.wal.receive<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.receive`</p></li><li>Change per second</li></ul>|
|Health: Get data|<p>Collects raw PostgreSQL health metrics.</p>|Zabbix agent (active)|pgsql.health.snapshot.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|
|Health: Max XID age|<p>Current age of the oldest transaction ID (XID) in the database as a numeric count, used to detect wraparound risk.</p>|Dependent item|pgsql.health.max.xid.age<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max_xid_age`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Health: Max XID, in %|<p>Max XID age as % of wraparound.</p>|Dependent item|pgsql.health.max.xid.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max_xid_percent`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Health: Waiting locks|<p>Number of sessions currently waiting on locks, indicating blocking or contention.</p>|Dependent item|pgsql.health.waiting.locks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.waiting_locks`</p></li></ul>|
|Health: Active connections|<p>Number of connections currently executing queries.</p>|Dependent item|pgsql.health.active.connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_connections`</p></li></ul>|
|Health: Total connections|<p>Total number of current client connections (`active`/`inactive`) to the database.</p>|Dependent item|pgsql.health.total.connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_connections`</p></li></ul>|
|Health: Deadlocks|<p>Total number of deadlocks detected across all databases.</p>|Dependent item|pgsql.health.deadlocks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlocks`</p></li></ul>|
|Health: Buffer hit ratio|<p>Percentage of data served from memory cache. </p><p>Low values indicate more disk I/O and potential performance pressure.</p>|Dependent item|pgsql.health.cache.hit.ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cache_hit_ratio`</p></li></ul>|
|Health: Autovacuum active|<p>Number of autovacuum processes currently running in the database.</p>|Dependent item|pgsql.health.autovacuum.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.autovacuum.active`</p></li></ul>|
|Health: Autovacuum idle|<p>Number of autovacuum workers currently idle in the database.</p>|Dependent item|pgsql.health.autovacuum.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.autovacuum.idle`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Oldest active XID warning|<p>Oldest active XID >= `{$PG.XID_ACTIVE.WARN}`. Monitor long transactions.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.oldest.active.xid["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],15m) >= {$PG.XID_ACTIVE.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Oldest active XID average</li></ul>|
|PostgreSQL: Oldest active XID average|<p>Oldest active XID >= `{$PG.XID_ACTIVE.AVG}`. Check for long-running transactions.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.oldest.active.xid["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],15m) >= {$PG.XID_ACTIVE.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Oldest active XID high</li></ul>|
|PostgreSQL: Oldest active XID high|<p>Oldest active XID >= `{$PG.XID_ACTIVE.HIGH}`. Investigate immediately.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.oldest.active.xid["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],15m) >= {$PG.XID_ACTIVE.HIGH}`|High||
|PostgreSQL: Cache hit ratio too low|<p>Cache hit ratio is lower than {$PG.CACHE_HIT_RATIO.MIN.WARN} for 5m.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.cache.hit["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],5m) < {$PG.CACHE_HIT_RATIO.MIN.WARN}`|Warning||
|PostgreSQL: Other authentication|<p>PostgreSQL allows md5 or password authentication methods.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.security.access.authentication)=1`|Info||
|PostgreSQL: Listening all interfaces|<p>PostgreSQL is configured to listen on all network interfaces.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.security.access.listening)=1`|Info||
|PostgreSQL: SSL is not active|<p>SSL is not configured for client connections in this PostgreSQL instance.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.security.access.ssl)=0`|Info||
|PostgreSQL: Configuration has changed|<p>PostgreSQL configuration has changed.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],#1)<>last(/PostgreSQL by Zabbix agent active/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],#2) and length(last(/PostgreSQL by Zabbix agent active/pgsql.config.hash["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]))>0`|Info||
|PostgreSQL: Idle warning|<p>Number of idle connections >= `{$PG.IDLE.WARN}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.idle,5m) >= {$PG.IDLE.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Idle average</li></ul>|
|PostgreSQL: Idle average|<p>Number of idle connections >= `{$PG.IDLE.AVG}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.idle,5m) >= {$PG.IDLE.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Idle high</li></ul>|
|PostgreSQL: Idle high|<p>Number of idle connections >= `{$PG.IDLE.HIGH}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.idle,5m) >= {$PG.IDLE.HIGH}`|High||
|PostgreSQL: Idle in transaction warning|<p>Number of idle in transaction connections >= `{$PG.IDLE.TRANSACTION.WARN}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.idle_in_transaction,5m) >= {$PG.IDLE.TRANSACTION.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Idle in transaction average</li></ul>|
|PostgreSQL: Idle in transaction average|<p>Number of idle in transaction connections >= `{$PG.IDLE.TRANSACTION.AVG}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.idle_in_transaction,5m) >= {$PG.IDLE.TRANSACTION.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Idle in transaction high</li></ul>|
|PostgreSQL: Idle in transaction high|<p>Number of idle in transaction connections >= `{$PG.IDLE.TRANSACTION.HIGH}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.idle_in_transaction,5m) >= {$PG.IDLE.TRANSACTION.HIGH}`|High||
|PostgreSQL: Connection usage warning|<p>Total connections >= `{$PG.CONNECTION.COUNT.WARN}`%.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.total_pct,5m) >= {$PG.CONNECTION.COUNT.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Connection usage average</li></ul>|
|PostgreSQL: Connection usage average|<p>Total connections >= `{$PG.CONNECTION.COUNT.AVG}`%.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.total_pct,5m) >= {$PG.CONNECTION.COUNT.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Connection usage high</li></ul>|
|PostgreSQL: Connection usage high|<p>Total connections >= `{$PG.CONNECTION.COUNT.HIGH}`%.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.total_pct,5m) >= {$PG.CONNECTION.COUNT.HIGH}`|High||
|PostgreSQL: Waiting connections warning|<p>Number of connections waiting >= `{$PG.WAITING.WARN}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.waiting,5m) >= {$PG.WAITING.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Waiting connections average</li></ul>|
|PostgreSQL: Waiting connections average|<p>Number of connections waiting >= `{$PG.WAITING.AVG}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.waiting,5m) >= {$PG.WAITING.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Waiting connections high</li></ul>|
|PostgreSQL: Waiting connections high|<p>Number of connections waiting >= `{$PG.WAITING.HIGH}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.waiting,5m) >= {$PG.WAITING.HIGH}`|High||
|PostgreSQL: Idle in transaction (aborted) warning|<p>Number of idle in transaction (aborted) connections >= `{$PG.IDLE.TRANSACTION.ABORTED.WARN}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.idle_in_transaction_aborted,5m) >= {$PG.IDLE.TRANSACTION.ABORTED.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Idle in transaction (aborted) average</li></ul>|
|PostgreSQL: Idle in transaction (aborted) average|<p>Number of idle in transaction (aborted) connections >= `{$PG.IDLE.TRANSACTION.ABORTED.AVG}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.idle_in_transaction_aborted,5m) >= {$PG.IDLE.TRANSACTION.ABORTED.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Idle in transaction (aborted) high</li></ul>|
|PostgreSQL: Idle in transaction (aborted) high|<p>Number of idle in transaction (aborted) connections >= `{$PG.IDLE.TRANSACTION.ABORTED.HIGH}`.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.connections.sum.idle_in_transaction_aborted,5m) >= {$PG.IDLE.TRANSACTION.ABORTED.HIGH}`|High||
|PostgreSQL: Response too long|<p>Response is taking too long (over {$PG.PING_TIME.MAX.WARN} for 5m).</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.ping.time["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"],5m) > {$PG.PING_TIME.MAX.WARN}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Server is down</li></ul>|
|PostgreSQL: Server is down|<p>Last test of a connection was unsuccessful.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.ping["{$PG.HOST}","{$PG.PORT}"]) = 0`|High||
|PostgreSQL: Replication is down|<p>Replication is enabled and data streaming was down for 5m.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.replication.status,5m)=0`|Average||
|PostgreSQL: Streaming lag with master is too high|<p>Replication lag with master is higher than {$PG.REPLICATION_LAG.MAX.WARN} for 5m.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.replication.lag.sec,5m) > {$PG.REPLICATION_LAG.MAX.WARN}`|Average||
|PostgreSQL: Service has been restarted|<p>PostgreSQL uptime is less than 10 minutes.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.uptime["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]) < 10m`|Average||
|PostgreSQL: Version info|<p>PostgreSQL version changed. Verify that the upgrade was intentional and applications remain compatible.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.version.major.minor,#1)<>last(/PostgreSQL by Zabbix agent active/pgsql.version.major.minor,#2) and length(last(/PostgreSQL by Zabbix agent active/pgsql.version.major.minor))>0`|Info||
|PostgreSQL: Word size info|<p>PostgreSQL word size has changed. Verify that the upgrade or server rebuild was intentional and applications remain compatible.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.version.wordsize,#1)<>last(/PostgreSQL by Zabbix agent active/pgsql.version.wordsize,#2) and length(last(/PostgreSQL by Zabbix agent active/pgsql.version.wordsize))>0`|Info||
|PostgreSQL: LRQ duration average|<p>Query running longer than `{$PG.LRQ.TIME.AVG}` seconds; check `pg_stat_activity`, optimize or index if needed, and cancel if blocking critical operations.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.longest.running.query.duration) >= {$PG.LRQ.TIME.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: LRQ duration high</li></ul>|
|PostgreSQL: LRQ duration high|<p>Query running longer than `{$PG.LRQ.TIME.HIGH}` seconds; check `pg_stat_activity`, optimize or index if needed, and cancel if blocking critical operations.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.longest.running.query.duration) >= {$PG.LRQ.TIME.HIGH}`|High||
|PostgreSQL: XID age warning|<p>Oldest XID age >= `{$PG.XID_MAX.WARN}`. Check autovacuum activity and monitor long-running transactions.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.health.max.xid.age,5m) >= {$PG.XID_MAX.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: XID age average</li></ul>|
|PostgreSQL: XID age average|<p>Oldest XID age >= `{$PG.XID_MAX.AVG}`. Ensure autovacuum is running properly; investigate long-running transactions.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.health.max.xid.age,5m) >= {$PG.XID_MAX.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: XID age high</li></ul>|
|PostgreSQL: XID age high|<p>Oldest XID age >= `{$PG.XID_MAX.HIGH}`. Immediate action required! Check autovacuum, terminate long-running transactions to prevent wraparound risk.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.health.max.xid.age,5m) >= {$PG.XID_MAX.HIGH}`|High||
|PostgreSQL: Health: Buffer hit warning|<p>Buffer hit ratio <= `{$PG.HEALTH.CACHE.WARN}`%. Slightly below optimal. Monitor I/O and queries.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.health.cache.hit.ratio,5m) <= {$PG.HEALTH.CACHE.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Health: Buffer hit average</li></ul>|
|PostgreSQL: Health: Buffer hit average|<p>Buffer hit ratio <= `{$PG.HEALTH.CACHE.AVG}`%. Low cache efficiency, potential I/O pressure. Investigate frequently used queries.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.health.cache.hit.ratio,5m) <= {$PG.HEALTH.CACHE.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Health: Buffer hit high</li></ul>|
|PostgreSQL: Health: Buffer hit high|<p>Buffer hit ratio <= `{$PG.HEALTH.CACHE.HIGH}`%. Very low cache efficiency. High I/O load expected. Investigate hot queries and disk usage immediately.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.health.cache.hit.ratio,5m) <= {$PG.HEALTH.CACHE.HIGH}`|High||
|PostgreSQL: Autovacuum active workers warning|<p>Number of active autovacuum workers has exceeded the `{$PG.AUTOVAC.ACTIVE.WORKERS.WARN}` threshold for the last 5 minutes.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.health.autovacuum.active,5m) >= {$PG.AUTOVAC.ACTIVE.WORKERS.WARN}`|Warning||
|PostgreSQL: Autovacuum idle workers warning|<p>Number of idle autovacuum workers has exceeded the `{$PG.AUTOVAC.IDLE.WORKERS.WARN}` threshold for the last 5 minutes.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.health.autovacuum.idle,5m) >= {$PG.AUTOVAC.IDLE.WORKERS.WARN}`|Warning||

### LLD rule Workload metrics discovery (v18+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload metrics discovery (v18+)|<p>Discovers `query` metrics in PostgreSQL 18 and above.</p>|Dependent item|pgsql.workload.above.18.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Workload metrics discovery (v18+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload: Get data|<p>Collects top queries from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p><p>IMPORTANT!</p><p>Ensure `pg_stat_statements` is installed, add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.</p>|Zabbix agent (active)|pgsql.workload.above.18.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Workload: PGSS version|<p>Version of `pg_stat_statements` module.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.pgss.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.extension_version`</p></li></ul>|
|Workload: Average execution|<p>Average query execution time from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.avg.exec.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.execution_time_ms.avg`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Max execution|<p>Slowest query execution time from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.max_exec_time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.execution_time_ms.max`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Temporary blocks written|<p>Number of temporary blocks written to disk (spills).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.temp.blocks.written.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_io_blocks.written`</p></li></ul>|
|Workload: Temporary blocks read|<p>Number of temporary blocks read from disk (spills).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.temp.blocks.read.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_io_blocks.read`</p></li></ul>|
|Workload: Temporary blocks total|<p>Total number of temporary blocks read and written to disk.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Calculated|pgsql.workload.temp.blocks.total.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workload: Total calls|<p>Total number of calls executed, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.total.calls.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.total_calls`</p></li></ul>|
|Workload: Rows inserted|<p>Total number of rows inserted, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.rows.inserted.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.inserted`</p></li></ul>|
|Workload: Rows updated|<p>Total number of rows updated, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.rows.updated.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.updated`</p></li></ul>|
|Workload: Rows deleted|<p>Total number of rows deleted, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.rows.deleted.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.deleted`</p></li></ul>|
|Workload: Plan count|<p>Time spent planning each individual query.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.total.plans.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.plans`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Total plan time|<p>Total time spent planning all queries combined.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.total.plan.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.total`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Plan time average|<p>Average time per plan execution.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.avg.plan.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.avg`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Distinct statements|<p>Number of distinct SQL statements tracked in `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.distinct.statements.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.distinct_statements`</p></li></ul>|
|Workload: Track level|<p>Track configuration in `pg_stat_statements` (none, top, all, nested).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.track.level.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.track`</p></li></ul>|
|Workload: Nested track|<p>Track nested configuration in `pg_stat_statements` (on/off).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.track.nested.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.track_nested`</p></li></ul>|
|Workload: Max statements|<p>Maximum number of statements tracked by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.max.statements.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.max`</p></li></ul>|
|Workload: Min plan time|<p>Minimum time spent planning queries.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.min.plan.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.min`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Max plan time|<p>Maximum time spent planning queries.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.max.plan.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.max`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Min execution|<p>Minimum query execution time.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.min.exec.time.version.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.execution_time_ms.min`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|

### LLD rule Workload metrics discovery (v17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload metrics discovery (v17)|<p>Discovers `query` metrics in PostgreSQL 17.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.version.17.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Workload metrics discovery (v17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload: Get data|<p>Collects top queries from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p><p>IMPORTANT!</p><p>Ensure `pg_stat_statements` is installed, add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.</p>|Zabbix agent (active)|pgsql.workload.version.17.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Workload: PGSS version|<p>Version of `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.pgss.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.extension_version`</p></li></ul>|
|Workload: Average execution|<p>Average query execution time from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.avg.exec.time.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.execution_time_ms.avg`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Max execution|<p>Slowest query execution time from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.max.exec.time.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.execution_time_ms.max`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Temporary blocks written|<p>Number of temporary blocks written to disk (spills).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.temp.blocks.written.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_io_blocks.written`</p></li></ul>|
|Workload: Total calls|<p>Total number of calls executed, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.total.calls.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.total_calls`</p></li></ul>|
|Workload: Rows inserted|<p>Total number of rows inserted, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.rows.inserted.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.inserted`</p></li></ul>|
|Workload: Rows updated|<p>Total number of rows updated, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.rows.updated.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.updated`</p></li></ul>|
|Workload: Rows deleted|<p>Total number of rows deleted, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.rows.deleted.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_affected.deleted`</p></li></ul>|
|Workload: Temporary blocks read|<p>Number of temporary blocks read from disk (spills).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.temp.blocks.read.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_io_blocks.read`</p></li></ul>|
|Workload: Temporary blocks total|<p>Total number of temporary blocks read and written to disk.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Calculated|pgsql.workload.temp.blocks.total.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workload: Plan count|<p>Number of times each query plan was executed.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.total.plans.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.plans`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Total plan time|<p>Total time spent planning all queries combined.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.total.plan.time.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.total`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Plan time average|<p>Average time per plan execution.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.avg.plan.time.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.plan_time_ms.avg`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Distinct statements|<p>Number of distinct SQL statements tracked in `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.distinct.statements.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.distinct_statements`</p></li></ul>|
|Workload: Track level|<p>Track configuration in `pg_stat_statements` (none, top, all, nested).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.track.level.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.track`</p></li></ul>|
|Workload: Nested track|<p>Track nested configuration in `pg_stat_statements`(on/off).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.track.nested.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.track_nested`</p></li></ul>|
|Workload: Max statements|<p>Maximum number of statements tracked by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.max.statements.version.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pg_stat_statements.configuration.max`</p></li></ul>|

### LLD rule Workload metrics discovery (v15–16)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload metrics discovery (v15–16)|<p>Discovers `query` metrics in PostgreSQL 15 and 16.</p>|Dependent item|pgsql.workload.above.15.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Workload metrics discovery (v15–16)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload: Get data|<p>Collects top queries from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p><p>IMPORTANT!</p><p>Ensure `pg_stat_statements` is installed, add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.</p>|Zabbix agent (active)|pgsql.workload.above.15.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Workload: PGSS version|<p>Version of `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.pgss.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pgss_version`</p></li></ul>|
|Workload: Average execution|<p>Average query execution time from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.avg_exec_time.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.avg_exec_time_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Max execution|<p>Slowest query execution time from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.max_exec_time.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.max_exec_time_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Temporary blocks written|<p>Number of temporary blocks written to disk (spills).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.temp.blocks.written.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_blks_written`</p></li></ul>|
|Workload: Total calls|<p>Total number of calls executed, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.total.calls.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.total_calls`</p></li></ul>|
|Workload: Rows inserted|<p>Total number of rows inserted, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.rows.inserted.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_inserted`</p></li></ul>|
|Workload: Rows updated|<p>Total number of rows updated, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.rows.updated.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_updated`</p></li></ul>|
|Workload: Rows deleted|<p>Total number of rows deleted, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.rows.deleted.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.rows_deleted`</p></li></ul>|

### LLD rule Workload metrics discovery (v13–14)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload metrics discovery (v13–14)|<p>Discovers `query` metrics in PostgreSQL 13 and 14.</p>|Dependent item|pgsql.workload.below.15.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Workload metrics discovery (v13–14)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload: Get data|<p>Collects top queries from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p><p>IMPORTANT!</p><p>Ensure `pg_stat_statements` is installed, add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.</p>|Zabbix agent (active)|pgsql.workload.below.15.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Workload: PGSS version|<p>Version of `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.pgss.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pgss_version`</p></li></ul>|
|Workload: Average execution|<p>Average query execution time from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.avg.exec.time.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.avg_exec_time_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Max execution|<p>Slowest query execution time from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.max.exec.time.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.max_exec_time_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Workload: Temporary blocks written|<p>Number of temporary blocks written to disk (spills).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.temp.blocks.written.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.temp_blks_written`</p></li></ul>|
|Workload: Total calls|<p>Total number of calls executed, as reported by `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.total.calls.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.metrics.total_calls`</p></li></ul>|

### LLD rule Workload metrics discovery (v12)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload metrics discovery (v12)|<p>Discovers `query` metrics in PostgreSQL 12.</p>|Dependent item|pgsql.workload.version.12.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Workload metrics discovery (v12)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workload: Get data|<p>Collects top queries from `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p><p>IMPORTANT!</p><p>Ensure `pg_stat_statements` is installed, add it to `shared_preload_libraries`, turn on `compute_query_id`, and set `pg_stat_statements.track` to `all`, so all queries are tracked.</p>|Zabbix agent (active)|pgsql.workload.version.12.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Workload: PGSS version|<p>Version of `pg_stat_statements`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.workload.pgss.version.12[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.pgss_version`</p></li></ul>|

### LLD rule Replication slots metrics discovery (v15+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication slots metrics discovery (v15+)|<p>Discovers `replication slots` metrics in PostgreSQL 15 and above.</p>|Dependent item|pgsql.replication.slots.above.15.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Replication slots metrics discovery (v15+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication slots: Get data|<p>Collects raw replication slot information from `pg_replication_slots`, including activity state, WAL retention, and slot type.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p><p>IMPORTANT!</p><p>Ensure logical replication is enabled: set `wal_level=logical`, `max_replication_slots` >= 1, `max_wal_senders` >= 1 in `postgresql.conf`.</p>|Zabbix agent (active)|pgsql.replication.slots.above.15.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Replication slots: Total|<p>Total number of replication slots (`active` and `inactive`) in PostgreSQL across the instance.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.slots.total.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_total`</p></li></ul>|
|Replication slots: Active|<p>Number of active replication slots in PostgreSQL across the instance.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.slots.active.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_active`</p></li></ul>|
|Replication slots: Inactive|<p>Number of inactive replication slots in PostgreSQL across the instance.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.slots.inactive.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_inactive`</p></li></ul>|
|Replication slots: Retaining|<p>Number of inactive replication slots retaining WAL in PostgreSQL across the instance.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.slots.inactive.retaining.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.inactive_retaining_slots`</p></li></ul>|
|Replication slots: WAL limit|<p>Maximum amount of WAL retained by any replication slot.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.slots.max.safe.wal.size.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max_safe_wal_size`</p></li></ul>|
|Replication slots: Worst lag|<p>Maximum replication lag (in bytes) of the worst slot.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.slots.worst.lag.above.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.worst_slot_lag_bytes`</p></li></ul>|

### Trigger prototypes for Replication slots metrics discovery (v15+)

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Replication slots: Retaining WAL average|<p>Slots retaining WAL >= `{$PG.REPLICATION.SLOTS.RETAINING.AVG}`. Some inactive slots are retaining WAL; monitor to prevent disk growth.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.replication.slots.inactive.retaining.above.15[{#SINGLETON}],5m) >= {$PG.REPLICATION.SLOTS.RETAINING.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Replication slots: Retaining WAL high</li></ul>|
|PostgreSQL: Replication slots: Retaining WAL high|<p>Slots retaining WAL >= `{$PG.REPLICATION.SLOTS.RETAINING.HIGH}`. Inactive slots retaining WAL may cause significant disk usage. Investigate and clean up unnecessary slots immediately.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.replication.slots.inactive.retaining.above.15[{#SINGLETON}],5m) >= {$PG.REPLICATION.SLOTS.RETAINING.HIGH}`|High||

### LLD rule Replication slots metrics discovery (v12–14)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication slots metrics discovery (v12–14)|<p>Discovers `replication slots` metrics in PostgreSQL 12–14.</p>|Dependent item|pgsql.replication.slots.below.15.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Replication slots metrics discovery (v12–14)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication slots: Get data|<p>Collects raw replication slot information from `pg_replication_slots`, including activity state, WAL retention, and slot type.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p><p>IMPORTANT!</p><p>Ensure logical replication is enabled: set `wal_level=logical`, `max_replication_slots` >= 1, `max_wal_senders` >= 1 in `postgresql.conf`.</p>|Zabbix agent (active)|pgsql.replication.slots.below.15.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Replication slots: Total|<p>Total number of replication slots (`active` and `inactive`) in PostgreSQL across the instance.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.slots.total.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_total`</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Replication slots: Active|<p>Number of active replication slots in PostgreSQL across the instance.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.slots.active.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_active`</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Replication slots: Inactive|<p>Number of inactive replication slots in PostgreSQL across the instance.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.slots.inactive.below.15[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots_inactive`</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### LLD rule Relation schema discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Relation schema discovery|<p>Discovers all PostgreSQL schemas in the primary core database.</p>|Dependent item|pgsql.relation.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Relation schema discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Relation [{#SCHEMA.NAME}] [{#DBNAME}]: Get data|<p>Collects `{#SCHEMA.NAME}` schema metrics from primary `{#DBNAME}` database.</p>|Dependent item|pgsql.relation.get["{#SCHEMA.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="{#SCHEMA.NAME}")].first()`</p></li></ul>|
|Relation [{#SCHEMA.NAME}] [{#DBNAME}]: Schema size|<p>Collects `{#SCHEMA.NAME}` schema size from `{#DBNAME}` database.</p>|Dependent item|pgsql.relation.size["{#SCHEMA.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_bytes`</p></li></ul>|
|Relation [{#SCHEMA.NAME}] [{#DBNAME}]: Tables count|<p>Collects number of tables in `{#SCHEMA.NAME}` schema from `{#DBNAME}` database.</p>|Dependent item|pgsql.relation.tables["{#SCHEMA.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.table_count`</p></li></ul>|

### LLD rule Tablespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tablespace discovery|<p>Discovers tablespaces.</p>|Zabbix agent (active)|pgsql.tablespace.discovery["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#TABLESPACE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Tablespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tablespace [{#TABLESPACE.NAME}]: Get data|<p>Collects `tablespace` metrics.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Zabbix agent (active)|pgsql.tablespace.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#TABLESPACE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.data`</p></li><li><p>JSON Path: `$[?(@.name=="{#TABLESPACE.NAME}")].first()`</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Size|<p>Size of the tablespace `{#TABLESPACE.NAME}` in bytes.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.tablespace.size[{#TABLESPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.size_bytes`</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Owner|<p>Owner of the tablespace `{#TABLESPACE.NAME}` (the PostgreSQL role that created it).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.tablespace.owner[{#TABLESPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.owner`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Location|<p>Filesystem path for the tablespace `{#TABLESPACE.NAME}`. An empty string indicates the default PostgreSQL data directory.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.tablespace.location[{#TABLESPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.location`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Tablespace [{#TABLESPACE.NAME}]: Default|<p>Indicates whether the tablespace `{#TABLESPACE.NAME}` is a default system tablespace.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.tablespace.is_default[{#TABLESPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.is_default`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Tablespace discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size warning|<p>Tablespace `{#TABLESPACE.NAME}` size >= `{$PG.TABLESPACE.SIZE.WARN:"{#TABLESPACE.NAME}"}` bytes.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.tablespace.size[{#TABLESPACE.NAME}],5m) >= {$PG.TABLESPACE.SIZE.WARN:"{#TABLESPACE.NAME}"}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size average</li></ul>|
|PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size average|<p>Tablespace `{#TABLESPACE.NAME}` size >= `{$PG.TABLESPACE.SIZE.AVG:"{#TABLESPACE.NAME}` bytes.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.tablespace.size[{#TABLESPACE.NAME}],5m) >= {$PG.TABLESPACE.SIZE.AVG:"{#TABLESPACE.NAME}"}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size high</li></ul>|
|PostgreSQL: Tablespace [{#TABLESPACE.NAME}]: Size high|<p>Tablespace `{#TABLESPACE.NAME}` size >= `{$PG.TABLESPACE.SIZE.HIGH:"{#TABLESPACE.NAME}"}` bytes.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.tablespace.size[{#TABLESPACE.NAME}],5m) >= {$PG.TABLESPACE.SIZE.HIGH:"{#TABLESPACE.NAME}"}`|High||

### LLD rule I/O metrics discovery (v18+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O metrics discovery (v18+)|<p>Discovers `I/O` metrics in PostgreSQL 18 and above.</p>|Dependent item|pgsql.io.above.18.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for I/O metrics discovery (v18+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O: Get data|<p>Aggregated `pg_stat_io metrics` in PostgreSQL 18 and above.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Zabbix agent (active)|pgsql.io.above.18.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]|
|I/O: Read bytes total|<p>Total number of bytes read by PostgreSQL.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.io.read.bytes.above.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.read_bytes`</p></li></ul>|
|I/O: Write bytes total|<p>Total number of bytes written by PostgreSQL.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.io.write.bytes.above.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.write_bytes`</p></li></ul>|
|I/O: WAL write bytes total|<p>Total number of bytes written to WAL.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.io.wal.write.bytes.above.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.wal_write_bytes`</p></li></ul>|
|I/O: Time total|<p>Total time spent on I/O operations (read + write).</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p><p>IMPORTANT!</p><p>Requires `track_io_timing = on` in `postgresql.conf` or via `ALTER SYSTEM`.</p><p>Otherwise, this metric will always be `0`.</p>|Dependent item|pgsql.io.time.ms.above.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.io_time_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|I/O: Buffer hit ratio|<p>Percentage of buffer cache hits.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.io.hit.ratio.above.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for I/O metrics discovery (v18+)

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: I/O: Buffer hit warning|<p>I/O buffer hit ratio <= `{$PG.IO.CACHE.WARN}`%. Slightly below optimal. Monitor memory and disk activity.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.io.hit.ratio.above.18[{#SINGLETON}],5m) <= {$PG.IO.CACHE.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: I/O: Buffer hit average</li></ul>|
|PostgreSQL: I/O: Buffer hit average|<p>I/O buffer hit ratio <= `{$PG.IO.CACHE.AVG}`%. Moderate cache efficiency drop. Investigate queries and disk latency.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.io.hit.ratio.above.18[{#SINGLETON}],5m) <= {$PG.IO.CACHE.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: I/O: Buffer hit high</li></ul>|
|PostgreSQL: I/O: Buffer hit high|<p>I/O buffer hit ratio <= `{$PG.IO.CACHE.HIGH}`%. Critical cache inefficiency. High disk I/O expected. Investigate storage performance immediately.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.io.hit.ratio.above.18[{#SINGLETON}],5m) <= {$PG.IO.CACHE.HIGH}`|High||

### LLD rule I/O metrics discovery (v16–17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O metrics discovery (v16–17)|<p>Discovers `I/O` metrics in PostgreSQL 16 and 17.</p>|Dependent item|pgsql.io.below.18.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for I/O metrics discovery (v16–17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O: Get data|<p>Collects `pg_stat_io` metrics in PostgreSQL 16-17.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Zabbix agent (active)|pgsql.io.below.18.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|I/O: Read bytes total|<p>Total number of bytes read by PostgreSQL.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.io.read.bytes.below.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.reads`</p></li></ul>|
|I/O: Write bytes total|<p>Total number of bytes written by PostgreSQL.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.io.write.bytes.below.18[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.writes`</p></li></ul>|

### LLD rule Subscription stats metrics discovery (v15+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Subscription stats metrics discovery (v15+)|<p>Discovers `subscription` metrics in PostgreSQL 15 and above.</p>|Zabbix agent (active)|pgsql.subscription.above.15.discovery["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SUBNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Subscription stats metrics discovery (v15+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Subscription [{#SUBNAME}]: Get data|<p>Preprocessed data for each subscription object array.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Zabbix agent (active)|pgsql.subscription.above.15.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SUBNAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$[?(@.subname=="{#SUBNAME}")].first()`</p></li></ul>|
|Subscription [{#SUBNAME}]: Apply errors|<p>Number of errors that occurred while applying changes for this logical replication subscription.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.subscription.apply.error.above.15[{#SUBNAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.apply_error_count`</p></li></ul>|
|Subscription [{#SUBNAME}]: Sync errors|<p>Number of errors that occurred during the initial synchronization phase of this logical replication subscription.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.subscription.sync.error.above.15[{#SUBNAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.sync_error_count`</p></li></ul>|
|Subscription [{#SUBNAME}]: Total errors|<p>Total number of errors for this logical replication subscription, from both `apply` and `sync`.</p>|Calculated|pgsql.subscription.total.error.count.above.15[{#SUBNAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Subscription [{#SUBNAME}]: Reset time|<p>Unix timestamp of the last reset of subscription statistics.</p><p>The value `never` indicates that statistics have never been reset.</p>|Dependent item|pgsql.subscription.reset.time.above.15[{#SUBNAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.stats_reset`</p></li></ul>|
|Subscription [{#SUBNAME}]: Time since last reset|<p>Time in seconds that has passed since the last reset of subscription statistics.</p><p>If the value is zero or negative, the subscription has never been reset.</p>|Calculated|pgsql.subscription.reset.time.since[{#SUBNAME}]|

### Trigger prototypes for Subscription stats metrics discovery (v15+)

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: Subscription [{#SUBNAME}]: Apply errors average|<p>Apply subscription errors >= `{$PG.SUBSCRIPTION.APPLY.ERROR.AVG}`. Investigate subscription apply issues or replication delays.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.subscription.apply.error.above.15[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.APPLY.ERROR.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Subscription [{#SUBNAME}]: Apply errors high</li></ul>|
|PostgreSQL: Subscription [{#SUBNAME}]: Apply errors high|<p>Apply subscription errors >= `{$PG.SUBSCRIPTION.APPLY.ERROR.HIGH}`. Check subscription apply processes and replication lag.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.subscription.apply.error.above.15[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.APPLY.ERROR.HIGH}`|High||
|PostgreSQL: Subscription [{#SUBNAME}]: Sync errors average|<p>Sync subscription errors >= `{$PG.SUBSCRIPTION.SYNC.ERROR.AVG}`. Investigate subscription sync issues or replication delays.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.subscription.sync.error.above.15[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.SYNC.ERROR.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Subscription [{#SUBNAME}]: Sync errors high</li></ul>|
|PostgreSQL: Subscription [{#SUBNAME}]: Sync errors high|<p>Sync subscription errors >= `{$PG.SUBSCRIPTION.SYNC.ERROR.HIGH}`. Check subscription sync processes and replication lag.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.subscription.sync.error.above.15[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.SYNC.ERROR.HIGH}`|High||
|PostgreSQL: Subscription [{#SUBNAME}]: Total errors average|<p>Total subscription errors (sync + apply) >= `{$PG.SUBSCRIPTION.TOTAL.ERROR.AVG}`. Investigate replication issues or subscription problems.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.subscription.total.error.count.above.15[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.TOTAL.ERROR.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: Subscription [{#SUBNAME}]: Total errors high</li></ul>|
|PostgreSQL: Subscription [{#SUBNAME}]: Total errors high|<p>Total subscription errors (sync + apply) >= `{$PG.SUBSCRIPTION.TOTAL.ERROR.HIGH}`. Check subscription health, replication lag, or sync/apply errors.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.subscription.total.error.count.above.15[{#SUBNAME}],5m) >= {$PG.SUBSCRIPTION.TOTAL.ERROR.HIGH}`|High||

### LLD rule SLRU metrics discovery (v13+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SLRU metrics discovery (v13+)|<p>Discovers `SLRU` metrics in PostgreSQL 13 and above.</p>|Zabbix agent (active)|pgsql.slru.above.13.discovery["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for SLRU metrics discovery (v13+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SLRU [{#SLRU.NAME}]: Get data|<p>Collects SLRU (Simple Least Recently Used) cache statistics from `pg_stat_slru`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.slru.above.13.get[{#SLRU.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$[?(@.name=="{#SLRU.NAME}")].first()`</p></li></ul>|
|SLRU [{#SLRU.NAME}]: Blocks read|<p>Total number of SLRU (Simple Least Recently Used) blocks read since last statistics reset.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.slru.blocks.read[{#SLRU.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.blks_read`</p></li></ul>|
|SLRU [{#SLRU.NAME}]: Blocks hit|<p>Total number of SLRU (Simple Least Recently Used) blocks found in cache (hits) since last statistics reset.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.slru.blocks.hit[{#SLRU.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.blks_hit`</p></li></ul>|
|SLRU [{#SLRU.NAME}]: Buffer hit ratio|<p>Percentage of SLRU (Simple Least Recently Used) blocks served from cache (hits / total blocks). Higher means better performance.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Calculated|pgsql.slru.buffer.hit.ratio[{#SLRU.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SLRU [{#SLRU.NAME}]: Reset time|<p>The timestamp when the SLRU (Simple Least Recently Used) statistics were last reset to zero.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.slru.stats.reset[{#SLRU.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.stats_reset`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for SLRU metrics discovery (v13+)

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: SLRU [{#SLRU.NAME}]: Buffer hit warning|<p>SLRU buffer hit ratio <= `{$PG.SLRU.CACHE.WARN}`%. Slightly below optimal. Monitor I/O and queries.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.slru.buffer.hit.ratio[{#SLRU.NAME}],5m) <= {$PG.SLRU.CACHE.WARN}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: SLRU [{#SLRU.NAME}]: Buffer hit average</li></ul>|
|PostgreSQL: SLRU [{#SLRU.NAME}]: Buffer hit average|<p>SLRU buffer hit ratio <= `{$PG.SLRU.CACHE.AVG}`%. Moderate cache efficiency drop. Investigate I/O patterns and autovacuum activity.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.slru.buffer.hit.ratio[{#SLRU.NAME}],5m) <= {$PG.SLRU.CACHE.AVG}`|Average|**Depends on**:<br><ul><li>PostgreSQL: SLRU [{#SLRU.NAME}]: Buffer hit high</li></ul>|
|PostgreSQL: SLRU [{#SLRU.NAME}]: Buffer hit high|<p>SLRU buffer hit ratio <= `{$PG.SLRU.CACHE.HIGH}`%. Critical cache inefficiency. High I/O load expected. Investigate disk usage and hot transactions immediately.</p>|`max(/PostgreSQL by Zabbix agent active/pgsql.slru.buffer.hit.ratio[{#SLRU.NAME}],5m) <= {$PG.SLRU.CACHE.HIGH}`|High||

### LLD rule WAL lifecycle metrics discovery (v14+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL lifecycle metrics discovery (v14+)|<p>Discovers `WAL lifecycle` metrics in PostgreSQL 14 and above.</p>|Dependent item|pgsql.wal.lifecycle.above.14.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for WAL lifecycle metrics discovery (v14+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL lifecycle: Creation: Get data|<p>Collects `WAL creation` metrics.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.wal.creation.above.14.get[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal`</p></li></ul>|
|WAL lifecycle: Creation: Bytes total|<p>Total number of WAL bytes generated since last statistics reset.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.wal.creation.bytes.total[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.wal_bytes`</p></li></ul>|
|WAL lifecycle: Creation: Records|<p>Total number of WAL records generated since last statistics reset.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.wal.creation.records[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.wal_records`</p></li></ul>|
|WAL lifecycle: Creation: Buffers full|<p>Total number of times WAL buffers were full since last statistics reset.</p><p>A non-zero or increasing value indicates WAL buffer pressure and possible performance impact.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.wal.creation.buffers.full[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.wal_buffers_full`</p></li></ul>|
|WAL lifecycle: Creation: Reset time|<p>Timestamp of the last reset of WAL statistics.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.wal.creation.reset[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.stats_reset`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|WAL lifecycle: Creation: FPI|<p>Total number of full page images written to WAL since last statistics reset.</p><p>Higher values increase WAL volume and may indicate frequent checkpoints or cold page writes.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.wal.creation.fpi[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.wal_fpi`</p></li></ul>|

### LLD rule WAL lifecycle: Replication delivery discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL lifecycle: Replication delivery discovery|<p>Discovers `WAL lifecycle` metrics in PostgreSQL 14 and above.</p>|Dependent item|pgsql.wal.lifecycle.replication.delivery.discovery[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.replication`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for WAL lifecycle: Replication delivery discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL lifecycle: Delivery [{#APPLICATION.NAME}]: Get data|<p>Collects WAL `replication delivery` metrics.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.delivery.get[{#SINGLETON}/{#APPLICATION.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|WAL lifecycle: Delivery [{#APPLICATION.NAME}]: State|<p>State for replication stream.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.delivery.state[{#SINGLETON}/{#APPLICATION.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.state`</p></li></ul>|
|WAL lifecycle: Delivery [{#APPLICATION.NAME}]: Sent LSN|<p>Last WAL location sent to replica.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.delivery.sent.lsn[{#SINGLETON}/{#APPLICATION.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.sent_lsn`</p></li></ul>|
|WAL lifecycle: Delivery [{#APPLICATION.NAME}]: Replay LSN|<p>Last WAL location replayed on replica.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.replication.delivery.replay.lsn[{#SINGLETON}/{#APPLICATION.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.replay_lsn`</p></li></ul>|

### LLD rule WAL lifecycle: Replication slots discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL lifecycle: Replication slots discovery|<p>Discovers `WAL lifecycle` metrics in PostgreSQL 14 and above.</p>|Dependent item|pgsql.wal.lifecycle.retention.discovery[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for WAL lifecycle: Replication slots discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WAL lifecycle: Slots [{#SLOT.NAME}]: Get data|<p>Collects WAL `retention` metrics.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.retention.get[{#SINGLETON}/{#SLOT.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slots[?(@.slot_name=="{#SLOT.NAME}")].first()`</p></li></ul>|
|WAL lifecycle: Slots [{#SLOT.NAME}]: Active|<p>Indicates whether the replication slot is currently active.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.retention.active[{#SINGLETON}/{#SLOT.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.active`</p></li><li>Boolean to decimal</li></ul>|
|WAL lifecycle: Slots [{#SLOT.NAME}]: Restart LSN|<p>WAL restart position required to retain data for this replication slot.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.retention.restart.lsn[{#SINGLETON}/{#SLOT.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.restart_lsn`</p></li></ul>|
|WAL lifecycle: Slots [{#SLOT.NAME}]: Confirmed flush LSN|<p>WAL position confirmed as safely flushed by the replication slot.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.retention.confirmed.flush.lsn[{#SINGLETON}/{#SLOT.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.confirmed_flush_lsn`</p></li></ul>|

### LLD rule Bgwriter metrics discovery (v<=17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Bgwriter metrics discovery (v<=17)|<p>Discovers `bgwriter` metrics in PostgreSQL 17 and below.</p>|Dependent item|pgsql.bgwriter.below.17.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Bgwriter metrics discovery (v<=17)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Bgwriter: Get data|<p>Collects all metrics from `pg_stat_bgwriter` in PostgreSQL 17 and below.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Zabbix agent (active)|pgsql.bgwriter.below.17.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Bgwriter: Postmaster start time|<p>The time when the postmaster was last started.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.postmaster.time.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.postmaster_start_time`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Bgwriter: Buffers allocated|<p>Number of buffers allocated.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.buffers.allocated.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.buffers_alloc`</p></li><li>Change per second</li></ul>|
|Bgwriter: Writer stopped|<p>Extracts the number of times bgwriter stopped.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.maxwritten.clean.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.maxwritten_clean`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bgwriter: Buffers clean|<p>Extracts buffers written by background writer.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.buffers.clean.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.buffers_clean`</p></li><li>Change per second</li></ul>|
|Bgwriter: Checkpoint buffers|<p>Extracts buffers written during checkpoints.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.checkpoint.buffers.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.buffers_checkpoint`</p></li><li>Change per second</li></ul>|
|Bgwriter: Checkpoints timed|<p>Extracts scheduled checkpoints.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.checkpoints.timed.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.checkpoints_timed`</p></li><li>Change per second</li></ul>|
|Bgwriter: Checkpoints requested|<p>Extracts requested checkpoints.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.checkpoints.requested.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.checkpoints_req`</p></li><li>Change per second</li></ul>|
|Bgwriter: Writing checkpoints time|<p>Extracts a time spent for writing checkpoints.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.checkpoints.writing.time.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.checkpoint_write_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Bgwriter: Syncing checkpoints time|<p>Extracts a time spent for syncing checkpoints.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.checkpoints.syncing.time.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.checkpoint_sync_time`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Change per second</li></ul>|
|Bgwriter: Backend buffers written|<p>Extracts the number of buffers written by backend processes.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.buffers.backend.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.buffers_backend`</p></li><li>Change per second</li></ul>|
|Bgwriter: Backend fsync count|<p>Extracts the number of `fsync` operations performed by backend processes.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.fsync.operations.below.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.buffers_backend_fsync`</p></li><li>Change per second</li></ul>|

### LLD rule Bgwriter discovery (v17+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Bgwriter discovery (v17+)|<p>Discovers `bgwriter` metrics in PostgreSQL 17 and above.</p>|Dependent item|pgsql.bgwriter.above.17.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Bgwriter discovery (v17+)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Bgwriter: Get data|<p>Collects all metrics from `pg_stat_bgwriter`.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Zabbix agent (active)|pgsql.bgwriter.above.17.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#SINGLETON}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Bgwriter: Postmaster start time|<p>Extracts the postmaster start time.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.postmaster.time.above.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.postmaster_start_time`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Bgwriter: Checkpoints timed|<p>Extracts scheduled checkpoints.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.checkpoints.timed.above.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.checkpoints_timed`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bgwriter: Checkpoints requested|<p>Extracts requested checkpoints.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.checkpoints.requested.above.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.checkpoints_req`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bgwriter: Writing checkpoints time|<p>Extracts a time spent for writing checkpoints.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.checkpoints.writing.time.above.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.checkpoint_write_time`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bgwriter: Syncing checkpoints time|<p>Extracts a time spent for syncing checkpoints.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.checkpoints.syncing.time.above.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.checkpoint_sync_time`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bgwriter: Checkpoint buffers|<p>Extracts buffers written during checkpoints.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.checkpoint.buffers.above.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.buffers_checkpoint`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bgwriter: Buffers clean|<p>Extracts buffers written by background writer.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.buffers.clean.above.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.buffers_clean`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bgwriter: Writer stopped|<p>Extracts the number of times bgwriter stopped.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.maxwritten.clean.above.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.maxwritten_clean`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bgwriter: Buffers allocated|<p>Extracts allocated buffers.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.bgwriter.buffers.allocated.above.17[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.buffers_alloc`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication discovery|<p>Discovers replication lag metrics.</p>|Dependent item|pgsql.replication.process.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication client [{#APPLICATION.NAME}]: Get data|<p>Collects metrics for the "{#APPLICATION.NAME}" application.</p>|Dependent item|pgsql.replication.get[{#APPLICATION.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$[?(@.application_name=="{#APPLICATION.NAME}")].first()`</p></li></ul>|
|Replication client [{#APPLICATION.NAME}]: Flush lag|<p>Collects the delay from local WAL flush to standby acknowledgment, showing `synchronous_commit` impact.</p>|Dependent item|pgsql.replication.process.flush_lag[{#APPLICATION.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.flush_lag`</p></li></ul>|
|Replication client [{#APPLICATION.NAME}]: Replay lag|<p>Collects the delay from local WAL flush to standby applying it, indicating `synchronous_commit` and `remote_apply` impact.</p>|Dependent item|pgsql.replication.process.replay_lag[{#APPLICATION.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.replay_lag`</p></li></ul>|
|Replication client [{#APPLICATION.NAME}]: Write lag|<p>Collects the delay from local WAL flush to standby writing it, indicating `synchronous_commit` and ` remote_write` impact.</p>|Dependent item|pgsql.replication.process.write_lag[{#APPLICATION.NAME}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.write_lag`</p></li></ul>|

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Discovers all databases (DB) in the DBMS except:</p><p>- Template DBs (template0, template1);</p><p>- DBs that do not allow connections.</p>|Zabbix agent (active)|pgsql.db.discovery["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}"]|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DB [{#DBNAME}]: Dbstat: Get data|<p>Get dbstat metrics for "{#DBNAME}" database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.get["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Latency: Get data|<p>Collects latency metrics for "{#DBNAME}" database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.latency.get["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Database size|<p>Database `{#DBNAME}` size.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Zabbix agent (active)|pgsql.db.size["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#DBNAME}"]|
|DB [{#DBNAME}]: Dbstat: Blocks hit per second|<p>Total number of times per second disk blocks were found already in the buffer cache, so that a read was not necessary.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.blks_hit.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_hit`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Blocks read per second|<p>Total number of disk blocks read per second in this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.blks_read.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blks_read`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Detected conflicts per second|<p>Total number of queries canceled due to conflicts with recovery in this database per second.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.conflicts.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conflicts`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Detected deadlocks per second|<p>Total number of detected deadlocks in this database per second.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.deadlocks.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deadlocks`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Temporary bytes written per second|<p>Total amount of data written to temporary files by queries in this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.temp_bytes.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_bytes`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Temporary files created per second|<p>Total number of temporary files created by queries in this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.temp_files.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temp_files`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Tuples deleted per second|<p>Total number of rows deleted by queries in this database per second.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.tup_deleted.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_deleted`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Tuples fetched per second|<p>Total number of rows fetched by queries in this database per second.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.tup_fetched.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_fetched`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Tuples inserted per second|<p>Total number of rows inserted by queries in this database per second.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.tup_inserted.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_inserted`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Tuples returned per second|<p>Number of rows returned by queries in this database per second.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.tup_returned.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_returned`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Tuples updated per second|<p>Total number of rows updated by queries in this database per second.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.tup_updated.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tup_updated`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Commits per second|<p>Number of transactions in this database that have been committed per second.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.xact_commit.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_commit`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Dbstat: Rollbacks per second|<p>Total number of transactions in this database that have been rolled back.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.dbstat.xact_rollback.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.xact_rollback`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Frozen XID: Get data|<p>Extracts raw frozen XID progress data for {#DBNAME} database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Zabbix agent (active)|pgsql.frozen.xid.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DB [{#DBNAME}]: Frozen XID: Before autovacuum, in %|<p>Collects frozen XID before PostgreSQL autovacuum runs.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.frozen.xid.percent.before.autovacuum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.prc_before_av`</p></li></ul>|
|DB [{#DBNAME}]: Frozen XID: Before stop, in %|<p>Collects frozen XID before PostgreSQL stops.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.frozen.xid.percent.before.stop["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.prc_before_stop`</p></li></ul>|
|DB [{#DBNAME}]: Num of locks total|<p>Total number of locks in this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.locks.total["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['{#DBNAME}'].total`</p></li></ul>|
|DB [{#DBNAME}]: Latency: Slow maintenance total|<p>Slow maintenance query count for this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.queries.mro.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Latency: Max maintenance time|<p>Max maintenance query time for this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.queries.mro.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Latency: Sum maintenance time|<p>Sum maintenance query time for this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.queries.mro.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mro_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Latency: Slow query count|<p>Slow query count for this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.queries.query.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Latency: Max query time|<p>Max query time for this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.queries.query.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Latency: Sum query time|<p>Sum query time for this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.queries.query.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.query_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Latency: Slow transaction count|<p>Slow transaction query count for this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.queries.tx.slow_count["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_slow_count`</p></li></ul>|
|DB [{#DBNAME}]: Latency: Max transaction time|<p>Max transaction query time for this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.queries.tx.time_max["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_max`</p></li></ul>|
|DB [{#DBNAME}]: Latency: Sum transaction time|<p>Sum transaction query time for this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.queries.tx.time_sum["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_time_sum`</p></li></ul>|
|DB [{#DBNAME}]: Scans: Index per second|<p>Number of index scans in the database per second.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.scans.idx.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idx`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Scans: Sequential per second|<p>Number of sequential scans in this database per second.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Dependent item|pgsql.scans.seq.rate["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.seq`</p></li><li>Change per second</li></ul>|
|DB [{#DBNAME}]: Scans: Get data|<p>Number of scans done for table/index in this database.</p><p>Current major version of PostgreSQL: `{#PG_MAJOR}`.</p>|Zabbix agent (active)|pgsql.scans.get["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{#DBNAME}"]|

### Trigger prototypes for Database discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PostgreSQL: DB [{#DBNAME}]: Size warning|<p>Database "{#DBNAME}" size >= `{$PG.DATABASE.SIZE.WARN:"{#DBNAME}"}` bytes.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.db.size["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#DBNAME}"],5m) >= {$PG.DATABASE.SIZE.WARN:"{#DBNAME}"}`|Warning|**Depends on**:<br><ul><li>PostgreSQL: DB [{#DBNAME}]: Size average</li></ul>|
|PostgreSQL: DB [{#DBNAME}]: Size average|<p>Database "{#DBNAME}" size >= {$PG.DATABASE.SIZE.AVG:"{#DBNAME}"} bytes.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.db.size["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#DBNAME}"],5m) >= {$PG.DATABASE.SIZE.AVG:"{#DBNAME}"}`|Average|**Depends on**:<br><ul><li>PostgreSQL: DB [{#DBNAME}]: Size high</li></ul>|
|PostgreSQL: DB [{#DBNAME}]: Size high|<p>Database "{#DBNAME}" size >= {$PG.DATABASE.SIZE.HIGH:"{#DBNAME}"} bytes.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.db.size["{$PG.HOST}","{$PG.PORT}","{$PG.USER}","{$PG.PASSWORD}","{$PG.DATABASE}","{#DBNAME}"],5m) >= {$PG.DATABASE.SIZE.HIGH:"{#DBNAME}"}`|High||
|PostgreSQL: DB [{#DBNAME}]: Dbstat: Too many recovery conflicts|<p>The primary and standby servers are in many ways loosely connected. Actions on the primary will have an effect on the standby. As a result, there is potential for negative interactions or conflicts between them.<br>https://www.postgresql.org/docs/current/hot-standby.html#HOT-STANDBY-CONFLICT</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.dbstat.conflicts.rate["{#DBNAME}"],5m) > {$PG.CONFLICTS.MAX.WARN:"{#DBNAME}"}`|Average||
|PostgreSQL: DB [{#DBNAME}]: Dbstat: Deadlock occurred|<p>Number of deadlocks detected per second exceeds {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"} for 5m.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.dbstat.deadlocks.rate["{#DBNAME}"],5m) > {$PG.DEADLOCKS.MAX.WARN:"{#DBNAME}"}`|High||
|PostgreSQL: DB [{#DBNAME}]: Frozen XID: Frozen autovacuum average|<p>Database `{#DBNAME}` has reached the autovacuum warning threshold (`{$PG.FROZEN.XID.AUTOVACUUM.AVG}`%).</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.frozen.xid.percent.before.autovacuum["{#DBNAME}"],5m) >= {$PG.FROZEN.XID.AUTOVACUUM.AVG:"{#DBNAME}"}`|Average|**Depends on**:<br><ul><li>PostgreSQL: DB [{#DBNAME}]: Frozen XID: Frozen autovacuum high</li></ul>|
|PostgreSQL: DB [{#DBNAME}]: Frozen XID: Frozen autovacuum high|<p>Database `{#DBNAME}` has reached the high autovacuum threshold (`{$PG.FROZEN.XID.AUTOVACUUM.HIGH}`%).</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.frozen.xid.percent.before.autovacuum["{#DBNAME}"],5m) >= {$PG.FROZEN.XID.AUTOVACUUM.HIGH:"{#DBNAME}"}`|High||
|PostgreSQL: DB [{#DBNAME}]: Frozen XID: Frozen stop average|<p>Database `{#DBNAME}` has reached the wraparound warning threshold (`{$PG.FROZEN.XID.STOP.AVG}`%).</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.frozen.xid.percent.before.stop["{#DBNAME}"]) >= {$PG.FROZEN.XID.STOP.AVG:"{#DBNAME}"}`|Average|**Depends on**:<br><ul><li>PostgreSQL: DB [{#DBNAME}]: Frozen XID: Frozen stop high</li></ul>|
|PostgreSQL: DB [{#DBNAME}]: Frozen XID: Frozen stop high|<p>Database `{#DBNAME}` has reached the wraparound prevention threshold (`{$PG.FROZEN.XID.STOP.HIGH}`%). VACUUM FREEZE is required.</p>|`last(/PostgreSQL by Zabbix agent active/pgsql.frozen.xid.percent.before.stop["{#DBNAME}"]) >= {$PG.FROZEN.XID.STOP.HIGH:"{#DBNAME}"}`|High||
|PostgreSQL: DB [{#DBNAME}]: Number of locks is too high||`min(/PostgreSQL by Zabbix agent active/pgsql.locks.total["{#DBNAME}"],5m)>{$PG.LOCKS.MAX.WARN:"{#DBNAME}"}`|Warning||
|PostgreSQL: DB [{#DBNAME}]: Too many slow queries|<p>The number of detected slow queries exceeds the limit of {$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}.</p>|`min(/PostgreSQL by Zabbix agent active/pgsql.queries.query.slow_count["{#DBNAME}"],5m)>{$PG.SLOW_QUERIES.MAX.WARN:"{#DBNAME}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

