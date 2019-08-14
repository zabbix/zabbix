
# Template DB MySQL

## Overview

The template is developed for monitoring DBMS MySQL and its forks.
This template was tested with Zabbix 4.2.1 and MySQL 5.7, 8.0, Percona 8.0, MariaDB 10.4 on Linux and Windows.

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
|{$MYSQL.HOST}|Hostname or IP of MySQL host or container |localhost|
|{$MYSQL.PORT}|MySQL service port|3306|
|{$MYSQL.REPL_LAG.MAX.WARN}|The lag of slave from master for trigger expression.|30m|
|{$MYSQL.SLOW_QUERIES.MAX.WARN}|The number of slow queries for trigger expression.|3|
|{$MYSQL.ABORTED_CONN.MAX.WARN}|The number of failed attempts to connect to the MySQL server for trigger expression.|3|

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|
|----|-----------|----|
|Databases discovery|Scanning databases in DBMS.|Zabbix agent|
|Replication discovery|If 'show slave status' returns Master_Host, "Replication: *" items are created.|Zabbix agent|

## Items collected

|Name|Description|Type|
|----|-----------|----|
|Availability: Get status variables|The item gets server global status information.|Zabbix agent|
|Availability: MySQL status|The service status: 1 - MySQL server is up, 0 - MySQL server is down. |Zabbix agent|
|Connections: Aborted clients per second|The number of connections that were aborted because the client died without closing the connection properly.|Dependent item|
|Connections: Aborted connections per second|The number of failed attempts to connect to the MySQL server.|Dependent item|
|Connections: Connection errors accept per second|Number of errors that occurred during calls to accept() on the listening port.|Dependent item|
|Connections: Connection errors internal per second|Number of refused connections due to internal server errors, for example out of memory errors, or failed thread starts.|Dependent item|
|Connections: Connection errors max connections per second|Number of refused connections due to the max_connections limit being reached.|Dependent item|
|Connections: Connection errors peer address per second|Number of errors while searching for the connecting client IP address.|Dependent item|
|Connections: Connection errors select per second|Number of errors during calls to select() or poll() on the listening port. The client would not necessarily have been rejected in these cases.|Dependent item|
|Connections: Connection errors tcpwrap per second|Number of connections the libwrap library refused.|Dependent item|
|Connections: Connections per second|The number of connection attempts (successful or not) to the MySQL server.|Dependent item|
|Connections: Max used connections|The maximum number of connections that have been in use simultaneously since the server started.|Dependent item|
|Connections: Threads cached|The number of threads in the thread cache.|Dependent item|
|Connections: Threads connected|The number of currently open connections.|Dependent item|
|Connections: Threads created|The number of threads created to handle connections. If Threads_created is big, you may want to increase the thread_cache_size value. The cache miss rate can be calculated as Threads_created/Connections.|Dependent item|
|Connections: Threads running|The number of threads that are not sleeping.|Dependent item|
|Info: Size of database {#DBNAME}|The size of a discovered database.|Zabbix agent|
|Info: MySQL version||Zabbix agent|
|Info: Uptime|The number of seconds that the server has been up.|Dependent item|
|Performance: Buffer pool efficiency|The item shows how effectively the buffer pool is serving reads.|Calculated|
|Performance: Buffer pool utilization|Ratio of used to total pages in the buffer pool.|Calculated|
|Performance: Created tmp files on disk|How many temporary files mysqld has created.|Dependent item|
|Performance: Created tmp tables on disk|The number of internal on-disk temporary tables created by the server while executing statements.|Dependent item|
|Performance: Created tmp tables on memory|The number of internal temporary tables created by the server while executing statements.|Dependent item|
|Performance: InnoDB buffer pool pages free|The number of free pages in the InnoDB buffer pool.|Dependent item|
|Performance: InnoDB buffer pool pages total|The total size of the InnoDB buffer pool, in pages.|Dependent item|
|Performance: InnoDB buffer pool read requests|The number of logical read requests.|Dependent item|
|Performance: InnoDB buffer pool reads|The number of logical reads that InnoDB could not satisfy from the buffer pool, and had to read directly from disk.|Dependent item|
|Performance: InnoDB row lock time|The total time spent in acquiring row locks for InnoDB tables, in milliseconds.|Dependent item|
|Performance: InnoDB row lock time max|The maximum time to acquire a row lock for InnoDB tables, in milliseconds.|Dependent item|
|Performance: InnoDB row lock waits|The number of times operations on InnoDB tables had to wait for a row lock.|Dependent item|
|Performance: Slow queries per second|The number of queries that have taken more than long_query_time seconds. |Dependent item|
|Replication: Seconds Behind Master {#MASTERHOST}|The number of seconds that the slave SQL thread is behind processing the master binary log. A high number (or an increasing one) can indicate that the slave is unable to handle events from the master in a timely fashion.|Dependent item|
|Replication: Slave IO Running {#MASTERHOST}|Whether the I/O thread for reading the master's binary log is running. Normally, you want this to be Yes unless you have not yet started replication or have explicitly stopped it with STOP SLAVE.|Dependent item|
|Replication: Slave SQL Running {#MASTERHOST}|Whether the SQL thread for executing events in the relay log is running. As with the I/O thread, this should normally be Yes.|Dependent item|
|Replication: Slave status {#MASTERHOST}|The item gets status information on essential parameters of the slave threads.|Zabbix agent|
|Throughput: Bytes received|The number of bytes received from all clients.|Dependent item|
|Throughput: Bytes sent|The number of bytes sent to all clients.|Dependent item|
|Throughput: Command Delete per second|The Com_delete counter variable indicates the number of times the delete statement has been executed.|Dependent item|
|Throughput: Command Insert per second|The Com_insert counter variable indicates the number of times the insert statement has been executed.|Dependent item|
|Throughput: Command Select per second|The Com_select counter variable indicates the number of times the select statement has been executed.|Dependent item|
|Throughput: Command Update per second|The Com_update counter variable indicates the number of times the update statement has been executed.|Dependent item|
|Throughput: Queries per second|The number of statements executed by the server. This variable includes statements executed within stored programs, unlike the Questions variable.|Dependent item|
|Throughput: Questions per second|The number of statements executed by the server. This includes only statements sent to the server by clients and not statements executed within stored programs, unlike the Queries variable. |Dependent item|

## Triggers

|Name|Description|Expression|Severity|
|----|-----------|----|----|
|MySQL: Failed to get items (no data for 30m)|Zabbix has not received data for items for the last 30 minutes.|mysql.get_status_variables["{$MYSQL.HOST}","{$MYSQL.PORT}"].nodata(30m)=1|Warning|
|MySQL: Service is down||mysql.ping["{$MYSQL.HOST}","{$MYSQL.PORT}"].last()=0|High|
|MySQL: Server has aborted connections (over {$MYSQL.ABORTED_CONN.MAX.WARN} for 5m)|The number of failed attempts to connect to the MySQL server is more than {$MYSQL.ABORTED_CONN.MAX.WARN} in the last 5 minutes.|mysql.aborted_connects.min(5m)>{$MYSQL.ABORTED_CONN.MAX.WARN}|Average|
|MySQL: Refused connections (max_connections limit reached)|Number of refused connections due to the max_connections limit being reached|mysql.connection_errors_max_connections.last()>0|Average|
|MySQL: Version has changed (new version value received: {ITEM.VALUE})|MySQL version has changed. Ack to close.|mysql.version["{$MYSQL.HOST}","{$MYSQL.PORT}"].diff()=1 and mysql.version["{$MYSQL.HOST}","{$MYSQL.PORT}"].strlen()>0|Information|
|MySQL: Service has been restarted (uptime < 10m)|MySQL uptime is less than 10 minutes|mysql.uptime.last()<10m|Information|
|MySQL: Server has slow queries (over {$MYSQL.SLOW_QUERIES.MAX.WARN} for 5m)|The number of slow queries is more than {$MYSQL.SLOW_QUERIES.MAX.WARN} in the last 5 minutes.|mysql.slow_queries.min(5m)>{$MYSQL.SLOW_QUERIES.MAX.WARN}|Warning|
|MySQL: Replication lag is too high (over {$MYSQL.REPL_LAG.MAX.WARN} for 5m)||mysql.seconds_behind_master[{#MASTERHOST}].min(5m)>{$MYSQL.REPL_LAG.MAX.WARN}|Warning|
|MySQL: The slave I/O thread is not running|Whether the I/O thread for reading the master's binary log is running.|mysql.slave_io_running[{#MASTERHOST}].count(#1,"No",eq)}=1|Average|
|MySQL: The slave I/O thread is not connected to a replication master||mysql.slave_sql_running[{#MASTERHOST}].count(#1,"Yes",ne)=1|Warning|
|MySQL: The SQL thread is not running|Whether the SQL thread for executing events in the relay log is running.|mysql.slave_sql_running[{#MASTERHOST}].count(#1,"No",eq)=1|Warning|

## Feedback
Please report any issues with the template at https://support.zabbix.com 

You can also provide feedback, discuss the template or ask for help with it at
[ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384189-discussion-thread-for-official-zabbix-template-db-mysql).

## References
