
# Template DB MySQL by Zabbix agent

## Overview

For Zabbix version: 4.4  
The template is developed for monitoring DBMS MySQL and its forks.

This template was tested on:

- MySQL, version 5.7, 8.0
- Percona, version 8.0
- MariaDB, version 10.4
- Zabbix, version 4.2.1

## Setup

1. Install Zabbix agent and MySQL client. If necessary, add the path to the `mysql` and `mysqladmin` utilities to the global environment variable PATH.
2. Copy `template_db_mysql.conf` into folder with Zabbix agent configuration (`/etc/zabbix/zabbix_agentd.d/` by default). Don't forget to restart zabbix-agent.
3. Create MySQL user for monitoring (`<password>` at your discretion):

```text
CREATE USER 'zbx_monitor'@'%' IDENTIFIED BY '<password>';
GRANT USAGE,REPLICATION CLIENT,PROCESS,SHOW DATABASES,SHOW VIEW ON *.* TO 'zbx_monitor'@'%';
```

For more information please read the MYSQL documentation https://dev.mysql.com/doc/refman/8.0/en/grant.html

4. Create `.my.cnf` in home directory of Zabbix agent for Linux (`/var/lib/zabbix` by default ) or `my.cnf` in c:\ for Windows. The file must have three strings:

```text
[client]
user=zbx_monitor
password=<password>
```

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MYSQL.ABORTED_CONN.MAX.WARN}|<p>The number of failed attempts to connect to the MySQL server for trigger expression.</p>|`3`|
|{$MYSQL.BUFF_UTIL.MIN.WARN}|<p>The minimum buffer pool utilization in percent for trigger expression.</p>|`50`|
|{$MYSQL.HOST}|<p>Hostname or IP of MySQL host or container.</p>|`localhost`|
|{$MYSQL.PORT}|<p>MySQL service port.</p>|`3306`|
|{$MYSQL.REPL_LAG.MAX.WARN}|<p>The lag of slave from master for trigger expression.</p>|`30m`|
|{$MYSQL.SLOW_QUERIES.MAX.WARN}|<p>The number of slow queries for trigger expression.</p>|`3`|

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Databases discovery|<p>Scanning databases in DBMS.</p>|ZABBIX_PASSIVE|mysql.db.discovery["{$MYSQL.HOST}","{$MYSQL.PORT}"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(value.split("\n").map(function (name) {     return ({"{#DBNAME}": name}); }));`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p><p>**Filter**:</p>AND_OR <p>- A: {#DBNAME} NOT_MATCHES_REGEX `information_schema`</p>|
|Replication discovery|<p>If "show slave status" returns Master_Host, "Replication: *" items are created.</p>|ZABBIX_PASSIVE|mysql.replication.discovery["{$MYSQL.HOST}","{$MYSQL.PORT}"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var matches = value.match(/Master_Host.*>(.*)<.*/); if (matches) {     return JSON.stringify([{"{#MASTERHOST}": matches[1]}]); } return '[]';`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|MySQL|MySQL: Status||ZABBIX_PASSIVE|mysql.ping["{$MYSQL.HOST}","{$MYSQL.PORT}"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return value.indexOf('is alive') !== -1 ? 1 : 0;`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p>|
|MySQL|MySQL: Version||ZABBIX_PASSIVE|mysql.version["{$MYSQL.HOST}","{$MYSQL.PORT}"]<p>**Preprocessing**:</p><p>- REGEX: `(Server version)\s+(.+) \2`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|MySQL|MySQL: Uptime|<p>The number of seconds that the server has been up.</p>|DEPENDENT|mysql.uptime<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Uptime']/field[@name='Value']/text()`</p>|
|MySQL|MySQL: Aborted clients per second|<p>The number of connections that were aborted because the client died without closing the connection properly.</p>|DEPENDENT|mysql.aborted_clients.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Aborted_clients']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Aborted connections per second|<p>The number of failed attempts to connect to the MySQL server.</p>|DEPENDENT|mysql.aborted_connects.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Aborted_connects']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors accept per second|<p>Number of errors that occurred during calls to accept() on the listening port.</p>|DEPENDENT|mysql.connection_errors_accept.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Connection_errors_accept']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors internal per second|<p>Number of refused connections due to internal server errors, for example out of memory errors, or failed thread starts.</p>|DEPENDENT|mysql.connection_errors_internal.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Connection_errors_internal']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors max connections per second|<p>Number of refused connections due to the max_connections limit being reached.</p>|DEPENDENT|mysql.connection_errors_max_connections.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Connection_errors_max_connections']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors peer address per second|<p>Number of errors while searching for the connecting client IP address.</p>|DEPENDENT|mysql.connection_errors_peer_address.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Connection_errors_peer_address']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors select per second|<p>Number of errors during calls to select() or poll() on the listening port. The client would not necessarily have been rejected in these cases.</p>|DEPENDENT|mysql.connection_errors_select.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Connection_errors_select']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connection errors tcpwrap per second|<p>Number of connections the libwrap library refused.</p>|DEPENDENT|mysql.connection_errors_tcpwrap.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Connection_errors_tcpwrap']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Connections per second|<p>The number of connection attempts (successful or not) to the MySQL server.</p>|DEPENDENT|mysql.connections.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Connections']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Max used connections|<p>The maximum number of connections that have been in use simultaneously since the server started.</p>|DEPENDENT|mysql.max_used_connections<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Max_used_connections']/field[@name='Value']/text()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: Threads cached|<p>The number of threads in the thread cache.</p>|DEPENDENT|mysql.threads_cached<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Threads_cached']/field[@name='Value']/text()`</p>|
|MySQL|MySQL: Threads connected|<p>The number of currently open connections.</p>|DEPENDENT|mysql.threads_connected<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Threads_connected']/field[@name='Value']/text()`</p>|
|MySQL|MySQL: Threads created|<p>The number of threads created to handle connections. If Threads_created is big, you may want to increase the thread_cache_size value. The cache miss rate can be calculated as Threads_created/Connections.</p>|DEPENDENT|mysql.threads_created<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Threads_created']/field[@name='Value']/text()`</p>|
|MySQL|MySQL: Threads running|<p>The number of threads that are not sleeping.</p>|DEPENDENT|mysql.threads_running<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Threads_running']/field[@name='Value']/text()`</p>|
|MySQL|MySQL: Buffer pool efficiency|<p>The item shows how effectively the buffer pool is serving reads.</p>|CALCULATED|mysql.buffer_pool_efficiency<p>**Expression**:</p>`last(mysql.innodb_buffer_pool_reads) /  ( last(mysql.innodb_buffer_pool_read_requests) +  ( last(mysql.innodb_buffer_pool_read_requests) = 0 ) ) * 100 *  ( last(mysql.innodb_buffer_pool_read_requests) > 0 )`|
|MySQL|MySQL: Buffer pool utilization|<p>Ratio of used to total pages in the buffer pool.</p>|CALCULATED|mysql.buffer_pool_utilization<p>**Expression**:</p>`( last(mysql.innodb_buffer_pool_pages_total) -  last(mysql.innodb_buffer_pool_pages_free) ) /  ( last(mysql.innodb_buffer_pool_pages_total) +  ( last(mysql.innodb_buffer_pool_pages_total) = 0 ) ) * 100 *  ( last(mysql.innodb_buffer_pool_pages_total) > 0 )`|
|MySQL|MySQL: Created tmp files on disk|<p>How many temporary files mysqld has created.</p>|DEPENDENT|mysql.created_tmp_files<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Created_tmp_files']/field[@name='Value']/text()`</p>|
|MySQL|MySQL: Created tmp tables on disk|<p>The number of internal on-disk temporary tables created by the server while executing statements.</p>|DEPENDENT|mysql.created_tmp_disk_tables<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Created_tmp_disk_tables']/field[@name='Value']/text()`</p>|
|MySQL|MySQL: Created tmp tables on memory|<p>The number of internal temporary tables created by the server while executing statements.</p>|DEPENDENT|mysql.created_tmp_tables<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Created_tmp_tables']/field[@name='Value']/text()`</p>|
|MySQL|MySQL: InnoDB buffer pool pages free|<p>The total size of the InnoDB buffer pool, in pages.</p>|DEPENDENT|mysql.innodb_buffer_pool_pages_free<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Innodb_buffer_pool_pages_free']/field[@name='Value']/text()`</p>|
|MySQL|MySQL: InnoDB buffer pool pages total|<p>The total size of the InnoDB buffer pool, in pages.</p>|DEPENDENT|mysql.innodb_buffer_pool_pages_total<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Innodb_buffer_pool_pages_total']/field[@name='Value']/text()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: InnoDB buffer pool read requests per second|<p>The number of logical read requests per second.</p>|DEPENDENT|mysql.innodb_buffer_pool_read_requests.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Innodb_buffer_pool_read_requests']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: InnoDB buffer pool reads per second|<p>The number of logical reads per second that InnoDB could not satisfy from the buffer pool, and had to read directly from disk.</p>|DEPENDENT|mysql.innodb_buffer_pool_reads.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Innodb_buffer_pool_reads']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: InnoDB row lock time|<p>The total time spent in acquiring row locks for InnoDB tables, in milliseconds.</p>|DEPENDENT|mysql.innodb_row_lock_time<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Innodb_row_lock_time']/field[@name='Value']/text()`</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: InnoDB row lock time max|<p>The maximum time to acquire a row lock for InnoDB tables, in milliseconds.</p>|DEPENDENT|mysql.innodb_row_lock_time_max<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Innodb_row_lock_time_max']/field[@name='Value']/text()`</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: InnoDB row lock waits|<p>The number of times operations on InnoDB tables had to wait for a row lock.</p>|DEPENDENT|mysql.innodb_row_lock_waits<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Innodb_row_lock_waits']/field[@name='Value']/text()`</p>|
|MySQL|MySQL: Slow queries per second|<p>The number of queries that have taken more than long_query_time seconds.</p>|DEPENDENT|mysql.slow_queries.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Slow_queries']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Bytes received|<p>The number of bytes received from all clients.</p>|DEPENDENT|mysql.bytes_received.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Bytes_received']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Bytes sent|<p>The number of bytes sent to all clients.</p>|DEPENDENT|mysql.bytes_sent.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Bytes_sent']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Command Delete per second|<p>The Com_delete counter variable indicates the number of times the delete statement has been executed.</p>|DEPENDENT|mysql.com_delete.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Com_delete']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Command Insert per second|<p>The Com_insert counter variable indicates the number of times the insert statement has been executed.</p>|DEPENDENT|mysql.com_insert.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Com_insert']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Command Select per second|<p>The Com_select counter variable indicates the number of times the select statement has been executed.</p>|DEPENDENT|mysql.com_select.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Com_select']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Command Update per second|<p>The Com_update counter variable indicates the number of times the update statement has been executed.</p>|DEPENDENT|mysql.com_update.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Com_update']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Queries per second|<p>The number of statements executed by the server. This variable includes statements executed within stored programs, unlike the Questions variable.</p>|DEPENDENT|mysql.queries.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Queries']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Questions per second|<p>The number of statements executed by the server. This includes only statements sent to the server by clients and not statements executed within stored programs, unlike the Queries variable.</p>|DEPENDENT|mysql.questions.rate<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Questions']/field[@name='Value']/text()`</p><p>- CHANGE_PER_SECOND|
|MySQL|MySQL: Size of database {#DBNAME}|<p>-</p>|ZABBIX_PASSIVE|mysql.dbsize["{$MYSQL.HOST}","{$MYSQL.PORT}","{#DBNAME}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: Replication Seconds Behind Master {#MASTERHOST}|<p>The number of seconds that the slave SQL thread is behind processing the master binary log.</p><p>A high number (or an increasing one) can indicate that the slave is unable to handle events</p><p>from the master in a timely fashion.</p>|DEPENDENT|mysql.seconds_behind_master["{#MASTERHOST}"]<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row/field[@name='Seconds_Behind_Master']/text()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- NOT_MATCHES_REGEX: `null`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Replication is not performed.`</p>|
|MySQL|MySQL: Replication Slave IO Running {#MASTERHOST}|<p>Whether the I/O thread for reading the master's binary log is running. </p><p>Normally, you want this to be Yes unless you have not yet started replication or have </p><p>explicitly stopped it with STOP SLAVE.</p>|DEPENDENT|mysql.slave_io_running["{#MASTERHOST}"]<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row/field[@name='Slave_IO_Running']/text()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|MySQL|MySQL: Replication Slave SQL Running {#MASTERHOST}|<p>Whether the SQL thread for executing events in the relay log is running. </p><p>As with the I/O thread, this should normally be Yes.</p>|DEPENDENT|mysql.slave_sql_running["{#MASTERHOST}"]<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row/field[@name='Slave_SQL_Running']/text()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|Zabbix_raw_items|MySQL: Get status variables|<p>The item gets server global status information.</p>|ZABBIX_PASSIVE|mysql.get_status_variables["{$MYSQL.HOST}","{$MYSQL.PORT}"]|
|Zabbix_raw_items|MySQL: InnoDB buffer pool read requests|<p>The number of logical read requests.</p>|DEPENDENT|mysql.innodb_buffer_pool_read_requests<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Innodb_buffer_pool_read_requests']/field[@name='Value']/text()`</p>|
|Zabbix_raw_items|MySQL: InnoDB buffer pool reads|<p>The number of logical reads that InnoDB could not satisfy from the buffer pool, and had to read directly from disk.</p>|DEPENDENT|mysql.innodb_buffer_pool_reads<p>**Preprocessing**:</p><p>- XMLPATH: `/resultset/row[field/text()='Innodb_buffer_pool_reads']/field[@name='Value']/text()`</p>|
|Zabbix_raw_items|MySQL: Replication Slave status {#MASTERHOST}|<p>The item gets status information on essential parameters of the slave threads.</p>|ZABBIX_PASSIVE|mysql.slave_status["{$MYSQL.HOST}","{$MYSQL.PORT}","{#MASTERHOST}"]|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|MySQL: Service is down||`{TEMPLATE_NAME:mysql.ping["{$MYSQL.HOST}","{$MYSQL.PORT}"].last()}=0`|HIGH||
|MySQL: Version has changed (new version value received: {ITEM.VALUE})|<p>MySQL version has changed. Ack to close.</p>|`{TEMPLATE_NAME:mysql.version["{$MYSQL.HOST}","{$MYSQL.PORT}"].diff()}=1 and {TEMPLATE_NAME:mysql.version["{$MYSQL.HOST}","{$MYSQL.PORT}"].strlen()}>0`|INFO|<p>Manual close: YES</p>|
|MySQL: Service has been restarted (uptime < 10m)|<p>MySQL uptime is less than 10 minutes.</p>|`{TEMPLATE_NAME:mysql.uptime.last()}<10m`|INFO||
|MySQL: Server has aborted connections (over {$MYSQL.ABORTED_CONN.MAX.WARN} for 5m)|<p>The number of failed attempts to connect to the MySQL server is more than {$MYSQL.ABORTED_CONN.MAX.WARN} in the last 5 minutes.</p>|`{TEMPLATE_NAME:mysql.aborted_connects.rate.min(5m)}>{$MYSQL.ABORTED_CONN.MAX.WARN}`|AVERAGE|<p>**Depends on**:</p><p>- MySQL: Refused connections (max_connections limit reached)</p>|
|MySQL: Refused connections (max_connections limit reached)|<p>Number of refused connections due to the max_connections limit being reached.</p>|`{TEMPLATE_NAME:mysql.connection_errors_max_connections.rate.last()}>0`|AVERAGE||
|MySQL: Buffer pool utilization is too low (less {$MYSQL.BUFF_UTIL.MIN.WARN}% for 5m)|<p>The buffer pool utilization is less than {$MYSQL.BUFF_UTIL.MIN.WARN}% in the last 5 minutes. This means that there is a lot of unused RAM allocated for the buffer pool, which you can easily reallocate at the moment.</p>|`{TEMPLATE_NAME:mysql.buffer_pool_utilization.max(5m)}<{$MYSQL.BUFF_UTIL.MIN.WARN}`|WARNING||
|MySQL: Server has slow queries (over {$MYSQL.SLOW_QUERIES.MAX.WARN} for 5m)|<p>The number of slow queries is more than {$MYSQL.SLOW_QUERIES.MAX.WARN} in the last 5 minutes.</p>|`{TEMPLATE_NAME:mysql.slow_queries.rate.min(5m)}>{$MYSQL.SLOW_QUERIES.MAX.WARN}`|WARNING||
|MySQL: Replication lag is too high (over {$MYSQL.REPL_LAG.MAX.WARN} for 5m)|<p>-</p>|`{TEMPLATE_NAME:mysql.seconds_behind_master["{#MASTERHOST}"].min(5m)}>{$MYSQL.REPL_LAG.MAX.WARN}`|WARNING||
|MySQL: The slave I/O thread is not running|<p>Whether the I/O thread for reading the master's binary log is running.</p>|`{TEMPLATE_NAME:mysql.slave_io_running["{#MASTERHOST}"].count(#1,"No",eq)}=1`|AVERAGE||
|MySQL: The slave I/O thread is not connected to a replication master|<p>-</p>|`{TEMPLATE_NAME:mysql.slave_io_running["{#MASTERHOST}"].count(#1,"Yes",ne)}=1`|WARNING|<p>**Depends on**:</p><p>- MySQL: The slave I/O thread is not running</p>|
|MySQL: The SQL thread is not running|<p>Whether the SQL thread for executing events in the relay log is running.</p>|`{TEMPLATE_NAME:mysql.slave_sql_running["{#MASTERHOST}"].count(#1,"No",eq)}=1`|WARNING|<p>**Depends on**:</p><p>- MySQL: The slave I/O thread is not running</p>|
|MySQL: Failed to get items (no data for 30m)|<p>Zabbix has not received data for items for the last 30 minutes.</p>|`{TEMPLATE_NAME:mysql.get_status_variables["{$MYSQL.HOST}","{$MYSQL.PORT}"].nodata(30m)}=1`|WARNING|<p>**Depends on**:</p><p>- MySQL: Service is down</p>|

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at
[ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384189-discussion-thread-for-official-zabbix-template-db-mysql).

