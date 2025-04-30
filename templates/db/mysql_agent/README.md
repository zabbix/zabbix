
# MySQL by Zabbix agent

## Overview

This template is designed for the effortless deployment of MySQL monitoring by Zabbix via Zabbix agent and doesn't require any external scripts.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- MySQL 5.7, 8.0
- Percona 8.0
- MariaDB 10.4, 10.6.8

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Install Zabbix agent and MySQL client. If necessary, add the path to the `mysql` and `mysqladmin` utilities to the global environment variable PATH.
2. Copy the `template_db_mysql.conf` file with user parameters into folder with Zabbix agent configuration (/etc/zabbix/zabbix_agentd.d/ by default). Don't forget to restart Zabbix agent.
3. Create the MySQL user that will be used for monitoring (`<password>` at your discretion). For example:

```text
CREATE USER 'zbx_monitor'@'%' IDENTIFIED BY '<password>';
GRANT REPLICATION CLIENT,PROCESS,SHOW DATABASES,SHOW VIEW ON *.* TO 'zbx_monitor'@'%';
```

For more information, please see [`MySQL documentation`](https://dev.mysql.com/doc/refman/8.0/en/grant.html).

4. Create `.my.cnf` configuration file in the home directory of Zabbix agent for Linux distributions (/var/lib/zabbix by default) or `my.cnf` in c:\ for Windows. For example:

```text
[client]
protocol=tcp
user='zbx_monitor'
password='<password>'
```

For more information, please see [`MySQL documentation`](https://dev.mysql.com/doc/refman/8.0/en/option-files.html).

**NOTE:** In order to collect replication metrics, MariaDB Enterprise Server 10.5.8-5 and above and MariaDB Community Server 10.5.9 and above require the `SLAVE MONITOR` privilege to be set for the monitoring user:

```text
GRANT REPLICATION CLIENT,PROCESS,SHOW DATABASES,SHOW VIEW,SLAVE MONITOR ON *.* TO 'zbx_monitor'@'%';
```

For more information, please read the [`MariaDB documentation`](https://mariadb.com/docs/server/ref/mdb/privileges/SLAVE_MONITOR/).

NOTE: Linux distributions that use SELinux may require additional steps for access configuration.

For example, the following rule could be added to the SELinux policy:

```text
# cat <<EOF > zabbix_home.te
module zabbix_home 1.0;

require {
        type zabbix_agent_t;
        type zabbix_var_lib_t;
        type mysqld_etc_t;
        type mysqld_port_t;
        type mysqld_var_run_t;
        class file { open read };
        class tcp_socket name_connect;
        class sock_file write;
}

#============= zabbix_agent_t ==============

allow zabbix_agent_t zabbix_var_lib_t:file read;
allow zabbix_agent_t zabbix_var_lib_t:file open;
allow zabbix_agent_t mysqld_etc_t:file read;
allow zabbix_agent_t mysqld_port_t:tcp_socket name_connect;
allow zabbix_agent_t mysqld_var_run_t:sock_file write;
EOF
# checkmodule -M -m -o zabbix_home.mod zabbix_home.te
# semodule_package -o zabbix_home.pp -m zabbix_home.mod
# semodule -i zabbix_home.pp
# restorecon -R /var/lib/zabbix
```

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MYSQL.ABORTED_CONN.MAX.WARN}|<p>Number of failed attempts to connect to the MySQL server for trigger expressions.</p>|`3`|
|{$MYSQL.HOST}|<p>Hostname or IP of MySQL host or container.</p>|`127.0.0.1`|
|{$MYSQL.PORT}|<p>MySQL service port.</p>|`3306`|
|{$MYSQL.REPL_LAG.MAX.WARN}|<p>Amount of time the slave is behind the master for trigger expressions.</p>|`30m`|
|{$MYSQL.SLOW_QUERIES.MAX.WARN}|<p>Number of slow queries for trigger expressions.</p>|`3`|
|{$MYSQL.BUFF_UTIL.MIN.WARN}|<p>The minimum buffer pool utilization in percentage for trigger expressions.</p>|`50`|
|{$MYSQL.CREATED_TMP_TABLES.MAX.WARN}|<p>The maximum number of temporary tables created in memory per second for trigger expressions.</p>|`30`|
|{$MYSQL.CREATED_TMP_DISK_TABLES.MAX.WARN}|<p>The maximum number of temporary tables created on a disk per second for trigger expressions.</p>|`10`|
|{$MYSQL.CREATED_TMP_FILES.MAX.WARN}|<p>The maximum number of temporary files created on a disk per second for trigger expressions.</p>|`10`|
|{$MYSQL.INNODB_LOG_FILES}|<p>Number of physical files in the InnoDB redo log for calculating `innodb_log_file_size`.</p>|`2`|
|{$MYSQL.DBNAME.MATCHES}|<p>Filter of discoverable databases.</p>|`.+`|
|{$MYSQL.DBNAME.NOT_MATCHES}|<p>Filter to exclude discovered databases.</p>|`information_schema`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get status variables|<p>Gets server global status information.</p>|Zabbix agent|mysql.get_status_variables["{$MYSQL.HOST}","{$MYSQL.PORT}"]|
|Status|<p>MySQL server status.</p>|Zabbix agent|mysql.ping["{$MYSQL.HOST}","{$MYSQL.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return value.indexOf('is alive') !== -1 ? 1 : 0;`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Version|<p>MySQL server version.</p>|Zabbix agent|mysql.version["{$MYSQL.HOST}","{$MYSQL.PORT}"]<p>**Preprocessing**</p><ul><li><p>Regular expression: `(Server version)\s+(.+) \2`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Uptime|<p>Number of seconds that the server has been up.</p>|Dependent item|mysql.uptime<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Aborted clients per second|<p>Number of connections that were aborted because the client died without closing the connection properly.</p>|Dependent item|mysql.aborted_clients.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Aborted connections per second|<p>Number of failed attempts to connect to the MySQL server.</p>|Dependent item|mysql.aborted_connects.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors accept per second|<p>Number of errors that occurred during calls to `accept()` on the listening port.</p>|Dependent item|mysql.connection_errors_accept.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors internal per second|<p>Number of refused connections due to internal server errors, for example, out of memory errors, or failed thread starts.</p>|Dependent item|mysql.connection_errors_internal.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors max connections per second|<p>Number of refused connections due to the `max_connections` limit being reached.</p>|Dependent item|mysql.connection_errors_max_connections.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors peer address per second|<p>Number of errors while searching for the connecting client's IP address.</p>|Dependent item|mysql.connection_errors_peer_address.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors select per second|<p>Number of errors during calls to `select()` or `poll()` on the listening port. The client would not necessarily have been rejected in these cases.</p>|Dependent item|mysql.connection_errors_select.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors tcpwrap per second|<p>Number of connections the libwrap library has refused.</p>|Dependent item|mysql.connection_errors_tcpwrap.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connections per second|<p>Number of connection attempts (successful or not) to the MySQL server.</p>|Dependent item|mysql.connections.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Max used connections|<p>The maximum number of connections that have been in use simultaneously since the server start.</p>|Dependent item|mysql.max_used_connections<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Threads cached|<p>Number of threads in the thread cache.</p>|Dependent item|mysql.threads_cached<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Threads connected|<p>Number of currently open connections.</p>|Dependent item|mysql.threads_connected<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Threads created per second|<p>Number of threads created to handle connections. If the value of `Threads_created` is large, you may want to increase the `thread_cache_size` value. The cache miss rate can be calculated as `Threads_created`/`Connections`.</p>|Dependent item|mysql.threads_created.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Threads running|<p>Number of threads that are not sleeping.</p>|Dependent item|mysql.threads_running<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Buffer pool efficiency|<p>The item shows how effectively the buffer pool is serving reads.</p>|Calculated|mysql.buffer_pool_efficiency|
|Buffer pool utilization|<p>Ratio of used to total pages in the buffer pool.</p>|Calculated|mysql.buffer_pool_utilization|
|Created tmp files on disk per second|<p>How many temporary files `mysqld` has created.</p>|Dependent item|mysql.created_tmp_files.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Created tmp tables on disk per second|<p>Number of internal on-disk temporary tables created by the server while executing statements.</p>|Dependent item|mysql.created_tmp_disk_tables.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Created tmp tables on memory per second|<p>Number of internal temporary tables created by the server while executing statements.</p>|Dependent item|mysql.created_tmp_tables.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|InnoDB buffer pool pages free|<p>The total size of the InnoDB buffer pool, in pages.</p>|Dependent item|mysql.innodb_buffer_pool_pages_free<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB buffer pool pages total|<p>The total size of the InnoDB buffer pool, in pages.</p>|Dependent item|mysql.innodb_buffer_pool_pages_total<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB buffer pool read requests|<p>Number of logical read requests.</p>|Dependent item|mysql.innodb_buffer_pool_read_requests<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB buffer pool read requests per second|<p>Number of logical read requests per second.</p>|Dependent item|mysql.innodb_buffer_pool_read_requests.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|InnoDB buffer pool reads|<p>Number of logical reads that InnoDB could not satisfy from the buffer pool and had to read directly from the disk.</p>|Dependent item|mysql.innodb_buffer_pool_reads<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB buffer pool reads per second|<p>Number of logical reads per second that InnoDB could not satisfy from the buffer pool and had to read directly from the disk.</p>|Dependent item|mysql.innodb_buffer_pool_reads.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|InnoDB row lock time|<p>The total time spent in acquiring row locks for InnoDB tables, in milliseconds.</p>|Dependent item|mysql.innodb_row_lock_time<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB row lock time max|<p>The maximum time to acquire a row lock for InnoDB tables, in milliseconds.</p>|Dependent item|mysql.innodb_row_lock_time_max<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB row lock waits|<p>Number of times operations on InnoDB tables had to wait for a row lock.</p>|Dependent item|mysql.innodb_row_lock_waits<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Slow queries per second|<p>Number of queries that have taken more than `long_query_time` seconds.</p>|Dependent item|mysql.slow_queries.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Bytes received|<p>Number of bytes received from all clients.</p>|Dependent item|mysql.bytes_received.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Bytes sent|<p>Number of bytes sent to all clients.</p>|Dependent item|mysql.bytes_sent.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command Delete per second|<p>The `Com_delete` counter variable indicates the number of times the `DELETE` statement has been executed.</p>|Dependent item|mysql.com_delete.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command Insert per second|<p>The `Com_insert` counter variable indicates the number of times the `INSERT` statement has been executed.</p>|Dependent item|mysql.com_insert.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command Select per second|<p>The `Com_select` counter variable indicates the number of times the `SELECT` statement has been executed.</p>|Dependent item|mysql.com_select.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command Update per second|<p>The `Com_update` counter variable indicates the number of times the `UPDATE` statement has been executed.</p>|Dependent item|mysql.com_update.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Queries per second|<p>Number of statements executed by the server. This variable includes statements executed within stored programs, unlike the `Questions` variable.</p>|Dependent item|mysql.queries.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Questions per second|<p>Number of statements executed by the server. This includes only statements sent to the server by clients and not statements executed within stored programs, unlike the `Queries` variable.</p>|Dependent item|mysql.questions.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Binlog cache disk use|<p>Number of transactions that used a temporary disk cache because they could not fit in the regular binary log cache, being larger than `binlog_cache_size`.</p>|Dependent item|mysql.binlog_cache_disk_use<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Innodb buffer pool wait free|<p>Number of times InnoDB waited for a free page before reading or creating a page. Normally, writes to the InnoDB buffer pool happen in the background. When no clean pages are available, dirty pages are flushed first in order to free some up. This counts the numbers of wait for this operation to finish. If this value is not small, look at the increasing `innodb_buffer_pool_size`.</p>|Dependent item|mysql.innodb_buffer_pool_wait_free<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Innodb number open files|<p>Number of open files held by InnoDB. InnoDB only.</p>|Dependent item|mysql.innodb_num_open_files<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Open table definitions|<p>Number of cached table definitions.</p>|Dependent item|mysql.open_table_definitions<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Open tables|<p>Number of tables that are open.</p>|Dependent item|mysql.open_tables<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Innodb log written|<p>Number of bytes written to the InnoDB log.</p>|Dependent item|mysql.innodb_os_log_written<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Calculated value of innodb_log_file_size|<p>`Innodb_log_file_size` is calculated as: (`innodb_os_log_written`-`innodb_os_log_written`(time shift -1h))/`{$MYSQL.INNODB_LOG_FILES}`. `Innodb_log_file_size` is the size in bytes of the each InnoDB redo log file in the log group. The combined size can be no more than 512 GB. Larger values mean less disk I/O due to less flushing checkpoint activity, but also slower recovery from a crash.</p>|Calculated|mysql.innodb_log_file_size<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MySQL: Service is down|<p>MySQL is down.</p>|`last(/MySQL by Zabbix agent/mysql.ping["{$MYSQL.HOST}","{$MYSQL.PORT}"])=0`|High||
|MySQL: Version has changed|<p>The MySQL version has changed. Acknowledge to close the problem manually.</p>|`last(/MySQL by Zabbix agent/mysql.version["{$MYSQL.HOST}","{$MYSQL.PORT}"],#1)<>last(/MySQL by Zabbix agent/mysql.version["{$MYSQL.HOST}","{$MYSQL.PORT}"],#2) and length(last(/MySQL by Zabbix agent/mysql.version["{$MYSQL.HOST}","{$MYSQL.PORT}"]))>0`|Info|**Manual close**: Yes|
|MySQL: Service has been restarted|<p>MySQL uptime is less than 10 minutes.</p>|`last(/MySQL by Zabbix agent/mysql.uptime)<10m`|Info||
|MySQL: Failed to fetch info data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/MySQL by Zabbix agent/mysql.uptime,30m)=1`|Info|**Depends on**:<br><ul><li>MySQL: Service is down</li></ul>|
|MySQL: Server has aborted connections|<p>The number of failed attempts to connect to the MySQL server is more than `{$MYSQL.ABORTED_CONN.MAX.WARN}` in the last 5 minutes.</p>|`min(/MySQL by Zabbix agent/mysql.aborted_connects.rate,5m)>{$MYSQL.ABORTED_CONN.MAX.WARN}`|Average|**Depends on**:<br><ul><li>MySQL: Refused connections</li></ul>|
|MySQL: Refused connections|<p>Number of refused connections due to the `max_connections` limit being reached.</p>|`last(/MySQL by Zabbix agent/mysql.connection_errors_max_connections.rate)>0`|Average||
|MySQL: Buffer pool utilization is too low|<p>The buffer pool utilization is less than `{$MYSQL.BUFF_UTIL.MIN.WARN}`% in the last 5 minutes. This means that there is a lot of unused RAM allocated for the buffer pool, which you can easily reallocate at the moment.</p>|`max(/MySQL by Zabbix agent/mysql.buffer_pool_utilization,5m)<{$MYSQL.BUFF_UTIL.MIN.WARN}`|Warning||
|MySQL: Number of temporary files created per second is high|<p>The application using the database may be in need of query optimization.</p>|`min(/MySQL by Zabbix agent/mysql.created_tmp_files.rate,5m)>{$MYSQL.CREATED_TMP_FILES.MAX.WARN}`|Warning||
|MySQL: Number of on-disk temporary tables created per second is high|<p>The application using the database may be in need of query optimization.</p>|`min(/MySQL by Zabbix agent/mysql.created_tmp_disk_tables.rate,5m)>{$MYSQL.CREATED_TMP_DISK_TABLES.MAX.WARN}`|Warning||
|MySQL: Number of internal temporary tables created per second is high|<p>The application using the database may be in need of query optimization.</p>|`min(/MySQL by Zabbix agent/mysql.created_tmp_tables.rate,5m)>{$MYSQL.CREATED_TMP_TABLES.MAX.WARN}`|Warning||
|MySQL: Server has slow queries|<p>The number of slow queries is more than `{$MYSQL.SLOW_QUERIES.MAX.WARN}` in the last 5 minutes.</p>|`min(/MySQL by Zabbix agent/mysql.slow_queries.rate,5m)>{$MYSQL.SLOW_QUERIES.MAX.WARN}`|Warning||

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Scanning databases in DBMS.</p>|Zabbix agent|mysql.db.discovery["{$MYSQL.HOST}","{$MYSQL.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Size of database {#DBNAME}|<p>Database size.</p>|Zabbix agent|mysql.dbsize["{$MYSQL.HOST}","{$MYSQL.PORT}","{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication discovery|<p>If "show slave status" returns Master_Host, "Replication: *" items are created.</p>|Zabbix agent|mysql.replication.discovery["{$MYSQL.HOST}","{$MYSQL.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication Slave status {#MASTER_HOST}|<p>The item gets status information on the essential parameters of the slave threads.</p>|Zabbix agent|mysql.slave_status["{$MYSQL.HOST}","{$MYSQL.PORT}","{#MASTER_HOST}"]|
|Replication Slave SQL Running State {#MASTER_HOST}|<p>This shows the state of the SQL driver threads.</p>|Dependent item|mysql.slave_sql_running_state["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Replication Seconds Behind Master {#MASTER_HOST}|<p>The number of seconds that the slave SQL thread is behind processing the master binary log.</p><p>A high number (or an increasing one) can indicate that the slave is unable to handle events</p><p>from the master in a timely fashion.</p>|Dependent item|mysql.seconds_behind_master["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Seconds_Behind_Master']/text()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Does not match regular expression: `null`</p><p>⛔️Custom on fail: Set error to: `Replication is not performed.`</p></li></ul>|
|Replication Slave IO Running {#MASTER_HOST}|<p>Whether the I/O thread for reading the master's binary log is running.</p><p>Normally, you want this to be Yes unless you have not yet started replication or have</p><p>explicitly stopped it with STOP SLAVE.</p>|Dependent item|mysql.slave_io_running["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Slave_IO_Running']/text()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Replication Slave SQL Running {#MASTER_HOST}|<p>Whether the SQL thread for executing events in the relay log is running.</p><p>As with the I/O thread, this should normally be Yes.</p>|Dependent item|mysql.slave_sql_running["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Slave_SQL_Running']/text()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Replication discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MySQL: Replication lag is too high|<p>Replication delay is too long.</p>|`min(/MySQL by Zabbix agent/mysql.seconds_behind_master["{#MASTER_HOST}"],5m)>{$MYSQL.REPL_LAG.MAX.WARN}`|Warning||
|MySQL: The slave I/O thread is not running|<p>Whether the I/O thread for reading the master's binary log is running.</p>|`count(/MySQL by Zabbix agent/mysql.slave_io_running["{#MASTER_HOST}"],#1,"eq","No")=1`|Average||
|MySQL: The slave I/O thread is not connected to a replication master|<p>Whether the slave I/O thread is connected to the master.</p>|`count(/MySQL by Zabbix agent/mysql.slave_io_running["{#MASTER_HOST}"],#1,"ne","Yes")=1`|Warning|**Depends on**:<br><ul><li>MySQL: The slave I/O thread is not running</li></ul>|
|MySQL: The SQL thread is not running|<p>Whether the SQL thread for executing events in the relay log is running.</p>|`count(/MySQL by Zabbix agent/mysql.slave_sql_running["{#MASTER_HOST}"],#1,"eq","No")=1`|Warning|**Depends on**:<br><ul><li>MySQL: The slave I/O thread is not running</li></ul>|

### LLD rule MariaDB discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MariaDB discovery|<p>Used for additional metrics if MariaDB is used.</p>|Dependent item|mysql.extra_metric.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for MariaDB discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Binlog commits|<p>Total number of transactions committed to the binary log.</p>|Dependent item|mysql.binlog_commits[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Binlog group commits|<p>Total number of group commits done to the binary log.</p>|Dependent item|mysql.binlog_group_commits[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Master GTID wait count|<p>The number of times `MASTER_GTID_WAIT` called.</p>|Dependent item|mysql.master_gtid_wait_count[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Master GTID wait time|<p>Total number of time spent in `MASTER_GTID_WAIT`.</p>|Dependent item|mysql.master_gtid_wait_time[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Master GTID wait timeouts|<p>Number of timeouts occurring in `MASTER_GTID_WAIT`.</p>|Dependent item|mysql.master_gtid_wait_timeouts[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

