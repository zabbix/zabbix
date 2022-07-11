
# MySQL by Zabbix agent 2

## Overview

For Zabbix version: 6.0 and higher  
The template is developed for monitoring DBMS MySQL and its forks.

This template was tested on:

- MySQL, version 5.7, 8.0
- Percona, version 8.0
- MariaDB, version 10.4

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

1. Create MySQL user for monitoring (`<password>` at your discretion):

```text
CREATE USER 'zbx_monitor'@'%' IDENTIFIED BY '<password>';
GRANT REPLICATION CLIENT,PROCESS,SHOW DATABASES,SHOW VIEW ON *.* TO 'zbx_monitor'@'%';
```

For more information, please see MySQL documentation https://dev.mysql.com/doc/refman/8.0/en/grant.html

2. Set in the {$MYSQL.DSN} macro the data source name of the MySQL instance either session name from Zabbix agent 2 configuration file or URI.
**Examples:** MySQL1, tcp://localhost:3306, tcp://172.16.0.10, unix:/var/run/mysql.sock
For more information about MySQL Unix socket file, see the MySQL documentation https://dev.mysql.com/doc/refman/8.0/en/problems-with-mysql-sock.html.

3. If you had set URI in the {$MYSQL.DSN}, define the user name and password in host macros ({$MYSQL.USER} and {$MYSQL.PASSWORD}).
Leave macros {$MYSQL.USER} and {$MYSQL.PASSWORD} empty if you use a session name. Set the user name and password in the Plugins.Mysql.<...> section of your Zabbix agent 2 configuration file.
For more information about configuring the Zabbix MySQL plugin, see the documentation https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/src/go/plugins/mysql/README.md.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MYSQL.ABORTED_CONN.MAX.WARN} |<p>Number of failed attempts to connect to the MySQL server for trigger expression.</p> |`3` |
|{$MYSQL.BUFF_UTIL.MIN.WARN} |<p>The minimum buffer pool utilization percentage for trigger expression.</p> |`50` |
|{$MYSQL.CREATED_TMP_DISK_TABLES.MAX.WARN} |<p>The maximum number of created tmp tables on a disk per second for trigger expressions.</p> |`10` |
|{$MYSQL.CREATED_TMP_FILES.MAX.WARN} |<p>The maximum number of created tmp files on a disk per second for trigger expressions.</p> |`10` |
|{$MYSQL.CREATED_TMP_TABLES.MAX.WARN} |<p>The maximum number of created tmp tables in memory per second for trigger expressions.</p> |`30` |
|{$MYSQL.DSN} |<p>System data source name such as <tcp://host:port or unix:/path/to/socket)/>.</p> |`<Put your DSN>` |
|{$MYSQL.INNODB_LOG_FILES} |<p>Number of physical files in the InnoDB redo log for calculating innodb_log_file_size.</p> |`2` |
|{$MYSQL.PASSWORD} |<p>MySQL user password.</p> |`` |
|{$MYSQL.REPL_LAG.MAX.WARN} |<p>The lag of slave from master for trigger expression.</p> |`30m` |
|{$MYSQL.SLOW_QUERIES.MAX.WARN} |<p>The number of slow queries for trigger expression.</p> |`3` |
|{$MYSQL.USER} |<p>MySQL user name.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Database discovery |<p>Scanning databases in DBMS.</p> |ZABBIX_PASSIVE |mysql.db.discovery["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p><p>**Filter**:</p>AND_OR <p>- {#DATABASE} NOT_MATCHES_REGEX `information_schema`</p> |
|MariaDB discovery |<p>Additional metrics if MariaDB is used.</p> |DEPENDENT |mysql.extra_metric.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(value.search('MariaDB')>-1 ? [{'{#SINGLETON}': ''}] : []);`</p> |
|Replication discovery |<p>If "show slave status" returns Master_Host, "Replication: *" items are created.</p> |ZABBIX_PASSIVE |mysql.replication.discovery["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|MySQL |MySQL: Status | |ZABBIX_PASSIVE |mysql.ping["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|MySQL |MySQL: Version | |ZABBIX_PASSIVE |mysql.version["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|MySQL |MySQL: Uptime |<p>The amount of seconds that the server has been up.</p> |DEPENDENT |mysql.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.Uptime`</p> |
|MySQL |MySQL: Aborted clients per second |<p>Number of connections that were aborted because the client died without closing the connection properly.</p> |DEPENDENT |mysql.aborted_clients.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Aborted_clients`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Aborted connections per second |<p>Number of failed attempts to connect to the MySQL server.</p> |DEPENDENT |mysql.aborted_connects.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Aborted_connects`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Connection errors accept per second |<p>Number of errors that occurred during calls to accept() on the listening port.</p> |DEPENDENT |mysql.connection_errors_accept.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Connection_errors_accept`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Connection errors internal per second |<p>Number of refused connections due to internal server errors, for example, out of memory errors, or failed thread starts.</p> |DEPENDENT |mysql.connection_errors_internal.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Connection_errors_internal`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Connection errors max connections per second |<p>Number of refused connections due to the max_connections limit being reached.</p> |DEPENDENT |mysql.connection_errors_max_connections.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Connection_errors_max_connections`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Connection errors peer address per second |<p>Number of errors while searching for the connecting client IP address.</p> |DEPENDENT |mysql.connection_errors_peer_address.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Connection_errors_peer_address`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Connection errors select per second |<p>Number of errors during calls to select() or poll() on the listening port. The client would not necessarily have been rejected in these cases.</p> |DEPENDENT |mysql.connection_errors_select.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Connection_errors_select`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Connection errors tcpwrap per second |<p>Number of connections the libwrap library has refused.</p> |DEPENDENT |mysql.connection_errors_tcpwrap.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Connection_errors_tcpwrap`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Connections per second |<p>Number of connection attempts (successful or not) to the MySQL server.</p> |DEPENDENT |mysql.connections.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Connections`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Max used connections |<p>The maximum number of connections that have been in use simultaneously since the server start.</p> |DEPENDENT |mysql.max_used_connections<p>**Preprocessing**:</p><p>- JSONPATH: `$.Max_used_connections`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|MySQL |MySQL: Threads cached |<p>Number of threads in the thread cache.</p> |DEPENDENT |mysql.threads_cached<p>**Preprocessing**:</p><p>- JSONPATH: `$.Threads_cached`</p> |
|MySQL |MySQL: Threads connected |<p>Number of currently open connections.</p> |DEPENDENT |mysql.threads_connected<p>**Preprocessing**:</p><p>- JSONPATH: `$.Threads_connected`</p> |
|MySQL |MySQL: Threads created per second |<p>Number of threads created to handle connections. If Threads_created is big, you may want to increase the thread_cache_size value. The cache miss rate can be calculated as Threads_created/Connections.</p> |DEPENDENT |mysql.threads_created.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Threads_created`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Threads running |<p>Number of threads which are not sleeping.</p> |DEPENDENT |mysql.threads_running<p>**Preprocessing**:</p><p>- JSONPATH: `$.Threads_running`</p> |
|MySQL |MySQL: Buffer pool efficiency |<p>The item shows how effectively the buffer pool is serving reads.</p> |CALCULATED |mysql.buffer_pool_efficiency<p>**Expression**:</p>`last(//mysql.innodb_buffer_pool_reads) /  ( last(//mysql.innodb_buffer_pool_read_requests) +  ( last(//mysql.innodb_buffer_pool_read_requests) = 0 ) ) * 100 *  ( last(//mysql.innodb_buffer_pool_read_requests) > 0 ) ` |
|MySQL |MySQL: Buffer pool utilization |<p>Ratio of used to total pages in the buffer pool.</p> |CALCULATED |mysql.buffer_pool_utilization<p>**Expression**:</p>`( last(//mysql.innodb_buffer_pool_pages_total) -  last(//mysql.innodb_buffer_pool_pages_free) ) /  ( last(//mysql.innodb_buffer_pool_pages_total) +  ( last(//mysql.innodb_buffer_pool_pages_total) = 0 ) ) * 100 *  ( last(//mysql.innodb_buffer_pool_pages_total) > 0 ) ` |
|MySQL |MySQL: Created tmp files on disk per second |<p>How many temporary files mysqld has created.</p> |DEPENDENT |mysql.created_tmp_files.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Created_tmp_files`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Created tmp tables on disk per second |<p>Number of internal on-disk temporary tables created by the server while executing statements.</p> |DEPENDENT |mysql.created_tmp_disk_tables.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Created_tmp_disk_tables`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Created tmp tables on memory per second |<p>Number of internal temporary tables created by the server while executing statements.</p> |DEPENDENT |mysql.created_tmp_tables.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Created_tmp_tables`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: InnoDB buffer pool pages free |<p>The total size of the InnoDB buffer pool, in pages.</p> |DEPENDENT |mysql.innodb_buffer_pool_pages_free<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_buffer_pool_pages_free`</p> |
|MySQL |MySQL: InnoDB buffer pool pages total |<p>The total size of the InnoDB buffer pool, in pages.</p> |DEPENDENT |mysql.innodb_buffer_pool_pages_total<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_buffer_pool_pages_total`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|MySQL |MySQL: InnoDB buffer pool read requests per second |<p>Number of logical read requests per second.</p> |DEPENDENT |mysql.innodb_buffer_pool_read_requests.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_buffer_pool_read_requests`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: InnoDB buffer pool reads per second |<p>Number of logical reads per second that InnoDB could not satisfy from the buffer pool, and had to read directly from the disk.</p> |DEPENDENT |mysql.innodb_buffer_pool_reads.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_buffer_pool_reads`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: InnoDB row lock time |<p>The total time spent in acquiring row locks for InnoDB tables, in milliseconds.</p> |DEPENDENT |mysql.innodb_row_lock_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_row_lock_time`</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|MySQL |MySQL: InnoDB row lock time max |<p>The maximum time to acquire a row lock for InnoDB tables, in milliseconds.</p> |DEPENDENT |mysql.innodb_row_lock_time_max<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_row_lock_time_max`</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|MySQL |MySQL: InnoDB row lock waits |<p>Number of times operations on InnoDB tables had to wait for a row lock.</p> |DEPENDENT |mysql.innodb_row_lock_waits<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_row_lock_waits`</p> |
|MySQL |MySQL: Slow queries per second |<p>Number of queries that have taken more than long_query_time seconds.</p> |DEPENDENT |mysql.slow_queries.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Slow_queries`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Bytes received |<p>Number of bytes received from all clients.</p> |DEPENDENT |mysql.bytes_received.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Bytes_received`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Bytes sent |<p>Number of bytes sent to all clients.</p> |DEPENDENT |mysql.bytes_sent.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Bytes_sent`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Command Delete per second |<p>The Com_delete counter variable indicates the number of times the delete statement has been executed.</p> |DEPENDENT |mysql.com_delete.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Com_delete`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Command Insert per second |<p>The Com_insert counter variable indicates the number of times the insert statement has been executed.</p> |DEPENDENT |mysql.com_insert.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Com_insert`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Command Select per second |<p>The Com_select counter variable indicates the number of times the select statement has been executed.</p> |DEPENDENT |mysql.com_select.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Com_select`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Command Update per second |<p>The Com_update counter variable indicates the number of times the update statement has been executed.</p> |DEPENDENT |mysql.com_update.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Com_update`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Queries per second |<p>Number of statements executed by the server. This variable includes statements executed within stored programs, unlike the Questions variable.</p> |DEPENDENT |mysql.queries.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Queries`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Questions per second |<p>Number of statements executed by the server. This includes only statements sent to the server by clients and not statements executed within stored programs, unlike the Queries variable.</p> |DEPENDENT |mysql.questions.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.Questions`</p><p>- CHANGE_PER_SECOND</p> |
|MySQL |MySQL: Binlog cache disk use |<p>Number of transactions that used a temporary disk cache because they could not fit in the regular binary log cache, being larger than binlog_cache_size.</p> |DEPENDENT |mysql.binlog_cache_disk_use<p>**Preprocessing**:</p><p>- JSONPATH: `$.Binlog_cache_disk_use`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|MySQL |MySQL: Innodb buffer pool wait free |<p>Number of times InnoDB waited for a free page before reading or creating a page. Normally, writes to the InnoDB buffer pool happen in the background. When no clean pages are available, dirty pages are flushed first in order to free some up. This counts the numbers of wait for this operation to finish. If this value is not small, look at the increasing innodb_buffer_pool_size.</p> |DEPENDENT |mysql.innodb_buffer_pool_wait_free<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_buffer_pool_wait_free`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|MySQL |MySQL: Innodb number open files |<p>Number of open files held by InnoDB. InnoDB only.</p> |DEPENDENT |mysql.innodb_num_open_files<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_num_open_files`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|MySQL |MySQL: Open table definitions |<p>Number of cached table definitions.</p> |DEPENDENT |mysql.open_table_definitions<p>**Preprocessing**:</p><p>- JSONPATH: `$.Open_table_definitions`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|MySQL |MySQL: Open tables |<p>Number of tables that are open.</p> |DEPENDENT |mysql.open_tables<p>**Preprocessing**:</p><p>- JSONPATH: `$.Open_tables`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|MySQL |MySQL: Innodb log written |<p>Number of bytes written to the InnoDB log.</p> |DEPENDENT |mysql.innodb_os_log_written<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_os_log_written`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|MySQL |MySQL: Calculated value of innodb_log_file_size |<p>Calculated by (innodb_os_log_written-innodb_os_log_written(time shift -1h))/{$MYSQL.INNODB_LOG_FILES} value of the innodb_log_file_size. Innodb_log_file_size is the size in bytes of the each InnoDB redo log file in the log group. The combined size can be no more than 512GB. Larger values mean less disk I/O due to less flushing checkpoint activity, but also slower recovery from a crash.</p> |CALCULATED |mysql.innodb_log_file_size<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Expression**:</p>`(last(//mysql.innodb_os_log_written) - last(//mysql.innodb_os_log_written,#1:now-1h)) / {$MYSQL.INNODB_LOG_FILES}` |
|MySQL |MySQL: Size of database {#DATABASE} |<p>-</p> |ZABBIX_PASSIVE |mysql.db.size["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}","{#DATABASE}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|MySQL |MySQL: Replication Slave SQL Running State {#MASTER_HOST} |<p>This shows the state of the SQL driver threads.</p> |DEPENDENT |mysql.replication.slave_sql_running_state["{#MASTER_HOST}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Slave_SQL_Running_State`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|MySQL |MySQL: Replication Seconds Behind Master {#MASTER_HOST} |<p>Number of seconds that the slave SQL thread is behind processing the master binary log.</p><p>A high number (or an increasing one) can indicate that the slave is unable to handle events</p><p>from the master in a timely fashion.</p> |DEPENDENT |mysql.replication.seconds_behind_master["{#MASTER_HOST}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Seconds_Behind_Master`</p><p>- MATCHES_REGEX: `\d+`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Replication is not performed.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|MySQL |MySQL: Replication Slave IO Running {#MASTER_HOST} |<p>Whether the I/O thread for reading the master's binary log is running.</p><p>Normally, you want this to be Yes unless you have not yet started a replication or have</p><p>explicitly stopped it with STOP SLAVE.</p> |DEPENDENT |mysql.replication.slave_io_running["{#MASTER_HOST}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Slave_IO_Running`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|MySQL |MySQL: Replication Slave SQL Running {#MASTER_HOST} |<p>Whether the SQL thread for executing events in the relay log is running.</p><p>As with the I/O thread, this should normally be Yes.</p> |DEPENDENT |mysql.replication.slave_sql_running["{#MASTER_HOST}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Slave_SQL_Running`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|MySQL |MySQL: Binlog commits |<p>Total number of transactions committed to the binary log.</p> |DEPENDENT |mysql.binlog_commits[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Binlog_commits`</p> |
|MySQL |MySQL: Binlog group commits |<p>Total number of group commits done to the binary log.</p> |DEPENDENT |mysql.binlog_group_commits[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Binlog_group_commits`</p> |
|MySQL |MySQL: Master GTID wait count |<p>The number of times MASTER_GTID_WAIT called.</p> |DEPENDENT |mysql.master_gtid_wait_count[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Master_gtid_wait_count`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|MySQL |MySQL: Master GTID wait time |<p>Total number of time spent in MASTER_GTID_WAIT.</p> |DEPENDENT |mysql.master_gtid_wait_time[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Master_gtid_wait_time`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|MySQL |MySQL: Master GTID wait timeouts |<p>Number of timeouts occurring in MASTER_GTID_WAIT.</p> |DEPENDENT |mysql.master_gtid_wait_timeouts[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Master_gtid_wait_timeouts`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |MySQL: Get status variables |<p>The item gets server global status information.</p> |ZABBIX_PASSIVE |mysql.get_status_variables["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}"] |
|Zabbix raw items |MySQL: InnoDB buffer pool read requests |<p>Number of logical read requests.</p> |DEPENDENT |mysql.innodb_buffer_pool_read_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_buffer_pool_read_requests`</p> |
|Zabbix raw items |MySQL: InnoDB buffer pool reads |<p>Number of logical reads that InnoDB could not satisfy from the buffer pool, and had to read directly from the disk.</p> |DEPENDENT |mysql.innodb_buffer_pool_reads<p>**Preprocessing**:</p><p>- JSONPATH: `$.Innodb_buffer_pool_reads`</p> |
|Zabbix raw items |MySQL: Replication Slave status {#MASTER_HOST} |<p>The item gets status information on the essential parameters of the slave threads.</p> |ZABBIX_PASSIVE |mysql.replication.get_slave_status["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}","{#MASTER_HOST}"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|MySQL: Service is down |<p>-</p> |`last(/MySQL by Zabbix agent 2/mysql.ping["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}"])=0` |HIGH | |
|MySQL: Version has changed |<p>MySQL version has changed. Ack to close.</p> |`last(/MySQL by Zabbix agent 2/mysql.version["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}"],#1)<>last(/MySQL by Zabbix agent 2/mysql.version["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}"],#2) and length(last(/MySQL by Zabbix agent 2/mysql.version["{$MYSQL.DSN}","{$MYSQL.USER}","{$MYSQL.PASSWORD}"]))>0` |INFO |<p>Manual close: YES</p> |
|MySQL: Service has been restarted |<p>MySQL uptime is less than 10 minutes.</p> |`last(/MySQL by Zabbix agent 2/mysql.uptime)<10m` |INFO | |
|MySQL: Failed to fetch info data |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`nodata(/MySQL by Zabbix agent 2/mysql.uptime,30m)=1` |INFO |<p>**Depends on**:</p><p>- MySQL: Service is down</p> |
|MySQL: Server has aborted connections |<p>The number of failed attempts to connect to the MySQL server is more than {$MYSQL.ABORTED_CONN.MAX.WARN} in the last 5 minutes.</p> |`min(/MySQL by Zabbix agent 2/mysql.aborted_connects.rate,5m)>{$MYSQL.ABORTED_CONN.MAX.WARN}` |AVERAGE |<p>**Depends on**:</p><p>- MySQL: Refused connections</p> |
|MySQL: Refused connections |<p>Number of refused connections due to the max_connections limit being reached.</p> |`last(/MySQL by Zabbix agent 2/mysql.connection_errors_max_connections.rate)>0` |AVERAGE | |
|MySQL: Buffer pool utilization is too low |<p>The buffer pool utilization is less than {$MYSQL.BUFF_UTIL.MIN.WARN}% in the last 5 minutes. This means that there is a lot of unused RAM allocated for the buffer pool, which you can easily reallocate at the moment.</p> |`max(/MySQL by Zabbix agent 2/mysql.buffer_pool_utilization,5m)<{$MYSQL.BUFF_UTIL.MIN.WARN}` |WARNING | |
|MySQL: Number of temporary files created per second is high |<p>Possibly the application using the database is in need of query optimization.</p> |`min(/MySQL by Zabbix agent 2/mysql.created_tmp_files.rate,5m)>{$MYSQL.CREATED_TMP_FILES.MAX.WARN}` |WARNING | |
|MySQL: Number of on-disk temporary tables created per second is high |<p>Possibly the application using the database is in need of query optimization.</p> |`min(/MySQL by Zabbix agent 2/mysql.created_tmp_disk_tables.rate,5m)>{$MYSQL.CREATED_TMP_DISK_TABLES.MAX.WARN}` |WARNING | |
|MySQL: Number of internal temporary tables created per second is high |<p>Possibly the application using the database is in need of query optimization.</p> |`min(/MySQL by Zabbix agent 2/mysql.created_tmp_tables.rate,5m)>{$MYSQL.CREATED_TMP_TABLES.MAX.WARN}` |WARNING | |
|MySQL: Server has slow queries |<p>The number of slow queries is more than {$MYSQL.SLOW_QUERIES.MAX.WARN} in the last 5 minutes.</p> |`min(/MySQL by Zabbix agent 2/mysql.slow_queries.rate,5m)>{$MYSQL.SLOW_QUERIES.MAX.WARN}` |WARNING | |
|MySQL: Replication lag is too high |<p>-</p> |`min(/MySQL by Zabbix agent 2/mysql.replication.seconds_behind_master["{#MASTER_HOST}"],5m)>{$MYSQL.REPL_LAG.MAX.WARN}` |WARNING | |
|MySQL: The slave I/O thread is not running |<p>Whether the I/O thread for reading the master's binary log is running.</p> |`count(/MySQL by Zabbix agent 2/mysql.replication.slave_io_running["{#MASTER_HOST}"],#1,"eq","No")=1` |AVERAGE | |
|MySQL: The slave I/O thread is not connected to a replication master |<p>-</p> |`count(/MySQL by Zabbix agent 2/mysql.replication.slave_io_running["{#MASTER_HOST}"],#1,"ne","Yes")=1` |WARNING |<p>**Depends on**:</p><p>- MySQL: The slave I/O thread is not running</p> |
|MySQL: The SQL thread is not running |<p>Whether the SQL thread for executing events in the relay log is running.</p> |`count(/MySQL by Zabbix agent 2/mysql.replication.slave_sql_running["{#MASTER_HOST}"],#1,"eq","No")=1` |WARNING |<p>**Depends on**:</p><p>- MySQL: The slave I/O thread is not running</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384189-discussion-thread-for-official-zabbix-template-db-mysql).

