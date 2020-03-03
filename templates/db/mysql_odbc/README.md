
# Template DB MySQL by ODBC

## Overview

For Zabbix version: 4.4  
The template is developed for monitoring DBMS MySQL and its forks.


This template was tested on:

- MySQL, version 5.7, 8.0
- Percona, version 8.0
- MariaDB, version 10.4
- Zabbix, version 4.4.0

## Setup

1. Create MySQL user for monitoring (`<password>` at your discretion):

```text
CREATE USER 'zbx_monitor'@'%' IDENTIFIED BY '<password>';
GRANT USAGE,REPLICATION CLIENT,PROCESS,SHOW DATABASES,SHOW VIEW ON *.* TO 'zbx_monitor'@'%';
```

For more information please read the MYSQL documentation https://dev.mysql.com/doc/refman/8.0/en/grant.html

2. Set the user name and password in host macros ({$MYSQL.USER} and {$MYSQL.PASSWORD}).


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MYSQL.ABORTED_CONN.MAX.WARN}|<p>The number of failed attempts to connect to the MySQL server for trigger expression.</p>|`3`|
|{$MYSQL.BUFF_UTIL.MIN.WARN}|<p>The minimum buffer pool utilization in percent for trigger expression.</p>|`50`|
|{$MYSQL.DSN}|<p>System data source name.</p>|`<Put your DSN here>`|
|{$MYSQL.PASSWORD}|<p>MySQL user password.</p>|`<Put your password here>`|
|{$MYSQL.REPL_LAG.MAX.WARN}|<p>The lag of slave from master for trigger expression.</p>|`30m`|
|{$MYSQL.SLOW_QUERIES.MAX.WARN}|<p>The number of slow queries for trigger expression.</p>|`3`|
|{$MYSQL.USER}|<p>MySQL username.</p>|`<Put your username here>`|

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Databases discovery|<p>Scanning databases in DBMS.</p>|ODBC|db.odbc.discovery[databases,"{$MYSQL.DSN}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p><p>**Filter**:</p>AND_OR <p>- A: {#DATABASE} NOT_MATCHES_REGEX `information_schema`</p>|
|Replication discovery|<p>If "show slave status" returns Master_Host, "Replication: *" items are created.</p>|ODBC|db.odbc.discovery[replication,"{$MYSQL.DSN}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|MySQL|MySQL: Status||ODBC|db.odbc.select[ping,"{$MYSQL.DSN}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p><p>**Expression**:</p>`select "1"`|
|MySQL|MySQL: Version||ODBC|db.odbc.select[version,"{$MYSQL.DSN}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p><p>**Expression**:</p>`select version()`|
|MySQL|MySQL: Uptime|<p>The number of seconds that the server has been up.</p>|DEPENDENT|mysql.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Uptime')].Value.first()`</p>|
|MySQL|MySQL: Aborted clients per second|<p>The number of connections that were aborted because the client died without closing the connection properly.</p>|DEPENDENT|mysql.aborted_clients.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Aborted_clients')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Aborted connections per second|<p>The number of failed attempts to connect to the MySQL server.</p>|DEPENDENT|mysql.aborted_connects.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Aborted_connects')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors accept per second|<p>Number of errors that occurred during calls to accept() on the listening port.</p>|DEPENDENT|mysql.connection_errors_accept.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Connection_errors_accept')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors internal per second|<p>Number of refused connections due to internal server errors, for example out of memory errors, or failed thread starts.</p>|DEPENDENT|mysql.connection_errors_internal.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Connection_errors_internal')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors max connections per second|<p>Number of refused connections due to the max_connections limit being reached.</p>|DEPENDENT|mysql.connection_errors_max_connections.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Connection_errors_max_connections')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors peer address per second|<p>Number of errors while searching for the connecting client IP address.</p>|DEPENDENT|mysql.connection_errors_peer_address.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Connection_errors_peer_address')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors select per second|<p>Number of errors during calls to select() or poll() on the listening port. The client would not necessarily have been rejected in these cases.</p>|DEPENDENT|mysql.connection_errors_select.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Connection_errors_select')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors tcpwrap per second|<p>Number of connections the libwrap library refused.</p>|DEPENDENT|mysql.connection_errors_tcpwrap.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Connection_errors_tcpwrap')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connections per second|<p>The number of connection attempts (successful or not) to the MySQL server.</p>|DEPENDENT|mysql.connections.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Connections')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Max used connections|<p>The maximum number of connections that have been in use simultaneously since the server started.</p>|DEPENDENT|mysql.max_used_connections<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Max_used_connections')].Value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: Threads cached|<p>The number of threads in the thread cache.</p>|DEPENDENT|mysql.threads_cached<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Threads_cached')].Value.first()`</p>|
|MySQL|MySQL: Threads connected|<p>The number of currently open connections.</p>|DEPENDENT|mysql.threads_connected<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Threads_connected')].Value.first()`</p>|
|MySQL|MySQL: Threads created|<p>The number of threads created to handle connections. If Threads_created is big, you may want to increase the thread_cache_size value. The cache miss rate can be calculated as Threads_created/Connections.</p>|DEPENDENT|mysql.threads_created<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Threads_created')].Value.first()`</p>|
|MySQL|MySQL: Threads running|<p>The number of threads that are not sleeping.</p>|DEPENDENT|mysql.threads_running<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Threads_running')].Value.first()`</p>|
|MySQL|MySQL: Buffer pool efficiency|<p>The item shows how effectively the buffer pool is serving reads.</p>|CALCULATED|mysql.buffer_pool_efficiency<p>**Expression**:</p>`last(mysql.innodb_buffer_pool_reads) /  ( last(mysql.innodb_buffer_pool_read_requests) +  ( last(mysql.innodb_buffer_pool_read_requests) = 0 ) ) * 100 *  ( last(mysql.innodb_buffer_pool_read_requests) > 0 )`|
|MySQL|MySQL: Buffer pool utilization|<p>Ratio of used to total pages in the buffer pool.</p>|CALCULATED|mysql.buffer_pool_utilization<p>**Expression**:</p>`( last(mysql.innodb_buffer_pool_pages_total) -  last(mysql.innodb_buffer_pool_pages_free) ) /  ( last(mysql.innodb_buffer_pool_pages_total) +  ( last(mysql.innodb_buffer_pool_pages_total) = 0 ) ) * 100 *  ( last(mysql.innodb_buffer_pool_pages_total) > 0 )`|
|MySQL|MySQL: Created tmp files on disk|<p>How many temporary files mysqld has created.</p>|DEPENDENT|mysql.created_tmp_files<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Created_tmp_files')].Value.first()`</p>|
|MySQL|MySQL: Created tmp tables on disk|<p>The number of internal on-disk temporary tables created by the server while executing statements.</p>|DEPENDENT|mysql.created_tmp_disk_tables<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Created_tmp_disk_tables')].Value.first()`</p>|
|MySQL|MySQL: Created tmp tables on memory|<p>The number of internal temporary tables created by the server while executing statements.</p>|DEPENDENT|mysql.created_tmp_tables<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Created_tmp_tables')].Value.first()`</p>|
|MySQL|MySQL: InnoDB buffer pool pages free|<p>The total size of the InnoDB buffer pool, in pages.</p>|DEPENDENT|mysql.innodb_buffer_pool_pages_free<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Innodb_buffer_pool_pages_free')].Value.first()`</p>|
|MySQL|MySQL: InnoDB buffer pool pages total|<p>The total size of the InnoDB buffer pool, in pages.</p>|DEPENDENT|mysql.innodb_buffer_pool_pages_total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Innodb_buffer_pool_pages_total')].Value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: InnoDB buffer pool read requests per second|<p>The number of logical read requests per second.</p>|DEPENDENT|mysql.innodb_buffer_pool_read_requests.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Innodb_buffer_pool_read_requests')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: InnoDB buffer pool reads per second|<p>The number of logical reads per second that InnoDB could not satisfy from the buffer pool, and had to read directly from disk.</p>|DEPENDENT|mysql.innodb_buffer_pool_reads.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Innodb_buffer_pool_reads')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: InnoDB row lock time|<p>The total time spent in acquiring row locks for InnoDB tables, in milliseconds.</p>|DEPENDENT|mysql.innodb_row_lock_time<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Innodb_row_lock_time')].Value.first()`</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: InnoDB row lock time max|<p>The maximum time to acquire a row lock for InnoDB tables, in milliseconds.</p>|DEPENDENT|mysql.innodb_row_lock_time_max<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Innodb_row_lock_time_max')].Value.first()`</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: InnoDB row lock waits|<p>The number of times operations on InnoDB tables had to wait for a row lock.</p>|DEPENDENT|mysql.innodb_row_lock_waits<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Innodb_row_lock_waits')].Value.first()`</p>|
|MySQL|MySQL: Slow queries per second|<p>The number of queries that have taken more than long_query_time seconds.</p>|DEPENDENT|mysql.slow_queries.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Slow_queries')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Bytes received|<p>The number of bytes received from all clients.</p>|DEPENDENT|mysql.bytes_received.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Bytes_received')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Bytes sent|<p>The number of bytes sent to all clients.</p>|DEPENDENT|mysql.bytes_sent.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Bytes_sent')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Command Delete per second|<p>The Com_delete counter variable indicates the number of times the delete statement has been executed.</p>|DEPENDENT|mysql.com_delete.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Com_delete')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Command Insert per second|<p>The Com_insert counter variable indicates the number of times the insert statement has been executed.</p>|DEPENDENT|mysql.com_insert.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Com_insert')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Command Select per second|<p>The Com_select counter variable indicates the number of times the select statement has been executed.</p>|DEPENDENT|mysql.com_select.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Com_select')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Command Update per second|<p>The Com_update counter variable indicates the number of times the update statement has been executed.</p>|DEPENDENT|mysql.com_update.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Com_update')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Queries per second|<p>The number of statements executed by the server. This variable includes statements executed within stored programs, unlike the Questions variable.</p>|DEPENDENT|mysql.queries.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Queries')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Questions per second|<p>The number of statements executed by the server. This includes only statements sent to the server by clients and not statements executed within stored programs, unlike the Queries variable.</p>|DEPENDENT|mysql.questions.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Questions')].Value.first()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Size of database {#DATABASE}|<p>-</p>|ODBC|db.odbc.select[{#DATABASE}_size,"{$MYSQL.DSN}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Expression**:</p>`SELECT SUM(DATA_LENGTH + INDEX_LENGTH) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA="{#DATABASE}"`|
|MySQL|MySQL: Replication Seconds Behind Master {#MASTER_HOST}|<p>The number of seconds that the slave SQL thread is behind processing the master binary log.</p><p>A high number (or an increasing one) can indicate that the slave is unable to handle events</p><p>from the master in a timely fashion.</p>|DEPENDENT|mysql.seconds_behind_master["{#MASTER_HOST}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Master_Host=='{#MASTER_HOST}')]['Seconds_Behind_Master'].first()`</p><p>- MATCHES_REGEX: `\d+`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Replication is not performed.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: Replication Slave IO Running {#MASTER_HOST}|<p>Whether the I/O thread for reading the master's binary log is running. </p><p>Normally, you want this to be Yes unless you have not yet started replication or have </p><p>explicitly stopped it with STOP SLAVE.</p>|DEPENDENT|mysql.slave_io_running["{#MASTER_HOST}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Master_Host=='{#MASTER_HOST}')]['Slave_IO_Running'].first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: Replication Slave SQL Running {#MASTER_HOST}|<p>Whether the SQL thread for executing events in the relay log is running. </p><p>As with the I/O thread, this should normally be Yes.</p>|DEPENDENT|mysql.slave_sql_running["{#MASTER_HOST}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Master_Host=='{#MASTER_HOST}')]['Slave_SQL_Running'].first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|Zabbix_raw_items|MySQL: Get status variables|<p>The item gets server global status information.</p>|ODBC|db.odbc.get[get_status_variables,"{$MYSQL.DSN}"]<p>**Expression**:</p>`show global status`|
|Zabbix_raw_items|MySQL: InnoDB buffer pool read requests|<p>The number of logical read requests.</p>|DEPENDENT|mysql.innodb_buffer_pool_read_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Innodb_buffer_pool_read_requests')].Value.first()`</p>|
|Zabbix_raw_items|MySQL: InnoDB buffer pool reads|<p>The number of logical reads that InnoDB could not satisfy from the buffer pool, and had to read directly from disk.</p>|DEPENDENT|mysql.innodb_buffer_pool_reads<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Variable_name=='Innodb_buffer_pool_reads')].Value.first()`</p>|
|Zabbix_raw_items|MySQL: Replication Slave status {#MASTER_HOST}|<p>The item gets status information on essential parameters of the slave threads.</p>|ODBC|db.odbc.get["{#MASTER_HOST}","{$MYSQL.DSN}"]<p>**Expression**:</p>`show slave status`|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|MySQL: Service is down||`{TEMPLATE_NAME:db.odbc.select[ping,"{$MYSQL.DSN}"].last()}=0`|HIGH||
|MySQL: Version has changed (new version value received: {ITEM.VALUE})|<p>MySQL version has changed. Ack to close.</p>|`{TEMPLATE_NAME:db.odbc.select[version,"{$MYSQL.DSN}"].diff()}=1 and {TEMPLATE_NAME:db.odbc.select[version,"{$MYSQL.DSN}"].strlen()}>0`|INFO|<p>Manual close: YES</p>|
|MySQL: Service has been restarted (uptime < 10m)|<p>MySQL uptime is less than 10 minutes.</p>|`{TEMPLATE_NAME:mysql.uptime.last()}<10m`|INFO||
|MySQL: Server has aborted connections (over {$MYSQL.ABORTED_CONN.MAX.WARN} for 5m)|<p>The number of failed attempts to connect to the MySQL server is more than {$MYSQL.ABORTED_CONN.MAX.WARN} in the last 5 minutes.</p>|`{TEMPLATE_NAME:mysql.aborted_connects.rate.min(5m)}>{$MYSQL.ABORTED_CONN.MAX.WARN}`|AVERAGE|<p>**Depends on**:</p><p>- MySQL: Refused connections (max_connections limit reached)</p>|
|MySQL: Refused connections (max_connections limit reached)|<p>Number of refused connections due to the max_connections limit being reached.</p>|`{TEMPLATE_NAME:mysql.connection_errors_max_connections.rate.last()}>0`|AVERAGE||
|MySQL: Buffer pool utilization is too low (less {$MYSQL.BUFF_UTIL.MIN.WARN}% for 5m)|<p>The buffer pool utilization is less than {$MYSQL.BUFF_UTIL.MIN.WARN}% in the last 5 minutes. This means that there is a lot of unused RAM allocated for the buffer pool, which you can easily reallocate at the moment.</p>|`{TEMPLATE_NAME:mysql.buffer_pool_utilization.max(5m)}<{$MYSQL.BUFF_UTIL.MIN.WARN}`|WARNING||
|MySQL: Server has slow queries (over {$MYSQL.SLOW_QUERIES.MAX.WARN} for 5m)|<p>The number of slow queries is more than {$MYSQL.SLOW_QUERIES.MAX.WARN} in the last 5 minutes.</p>|`{TEMPLATE_NAME:mysql.slow_queries.rate.min(5m)}>{$MYSQL.SLOW_QUERIES.MAX.WARN}`|WARNING||
|MySQL: Replication lag is too high (over {$MYSQL.REPL_LAG.MAX.WARN} for 5m)|<p>-</p>|`{TEMPLATE_NAME:mysql.seconds_behind_master["{#MASTER_HOST}"].min(5m)}>{$MYSQL.REPL_LAG.MAX.WARN}`|WARNING||
|MySQL: The slave I/O thread is not running|<p>Whether the I/O thread for reading the master's binary log is running.</p>|`{TEMPLATE_NAME:mysql.slave_io_running["{#MASTER_HOST}"].count(#1,"No",eq)}=1`|AVERAGE||
|MySQL: The slave I/O thread is not connected to a replication master|<p>-</p>|`{TEMPLATE_NAME:mysql.slave_io_running["{#MASTER_HOST}"].count(#1,"Yes",ne)}=1`|WARNING|<p>**Depends on**:</p><p>- MySQL: The slave I/O thread is not running</p>|
|MySQL: The SQL thread is not running|<p>Whether the SQL thread for executing events in the relay log is running.</p>|`{TEMPLATE_NAME:mysql.slave_sql_running["{#MASTER_HOST}"].count(#1,"No",eq)}=1`|WARNING|<p>**Depends on**:</p><p>- MySQL: The slave I/O thread is not running</p>|
|MySQL: Failed to get items (no data for 30m)|<p>Zabbix has not received data for items for the last 30 minutes.</p>|`{TEMPLATE_NAME:db.odbc.get[get_status_variables,"{$MYSQL.DSN}"].nodata(30m)}=1`|WARNING|<p>**Depends on**:</p><p>- MySQL: Service is down</p>|

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at
[ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384189-discussion-thread-for-official-zabbix-template-db-mysql).

