
# MariaDB by Zabbix agent active

## Overview

This template is designed for the effortless deployment of MariaDB monitoring by Zabbix via Zabbix agent active and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- MariaDB 10.6, 11.8, 12.1.2, 12.3.2

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Install Zabbix agent and MariaDB client. If necessary, add the path to the 'mariadb' and 'mariadb-admin' utilities to the global environment variable PATH.
2. Copy the `template_db_mariadb.conf` file with user parameters into folder with Zabbix agent configuration (/etc/zabbix/zabbix_agentd.d/ by default). Don't forget to restart Zabbix agent.
3. Create the MariaDB user that will be used for monitoring (`<password>` at your discretion). For example:

```text
CREATE USER 'zbx_monitor'@'%' IDENTIFIED BY '<password>';
GRANT SLAVE MONITOR, PROCESS, SHOW DATABASES, SHOW VIEW ON *.* TO 'zbx_monitor'@'%';
```

For more information, please see [`MariaDB documentation`](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant).

4. Create `.my.cnf` configuration file in the home directory of Zabbix agent for Linux distributions (/var/lib/zabbix by default) or `my.cnf` in c:\ for Windows. For example:

```text
[client]
protocol=tcp
user='zbx_monitor'
password='<password>'
```

For more information, please see [`MariaDB documentation`](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/configuring-mariadb/configuring-mariadb-with-option-files).

**NOTE:** Linux distributions that use SELinux may require additional steps for access configuration.

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

Optionally, it is possible to customize the template:
- The discovery of tables is disabled by default. To enable table discovery, change the value of the macro `{$MARIADB.TABLE.NAMES.MATCHES}` to the name of a single table, or use a regex string to include multiple tables.
- You can also add an additional context macro `{$MARIADB.TABLE.NAMES.MATCHES:<dbname>}` for specific databases to precisely control which tables should be discovered.
- To exclude certain tables from discovery, change the value of the macro `{$MARIADB.TABLE.NAMES.NOT_MATCHES}` to the name of a single table, or use a regex string to exclude multiple tables.
- You can also add an additional context macro `{$MARIADB.TABLE.NAMES.NOT_MATCHES:<dbname>}` for specific databases to precisely control which tables should be excluded.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MARIADB.HOST}|<p>Hostname or IP of MariaDB host or container.</p>|`127.0.0.1`|
|{$MARIADB.PORT}|<p>MariaDB service port.</p>|`3306`|
|{$MARIADB.ABORTED_CONN.MAX.WARN}|<p>Number of failed attempts to connect to the MariaDB server for trigger expressions.</p>|`3`|
|{$MARIADB.REPL_LAG.MAX.WARN}|<p>Amount of time the replica is behind the source for trigger expressions.</p>|`30m`|
|{$MARIADB.SLOW_QUERIES.MAX.WARN}|<p>Number of slow queries for trigger expressions.</p>|`3`|
|{$MARIADB.FULLSCAN.RATIO.WARN}|<p>Warning threshold for full table scan ratio.</p>|`0.1`|
|{$MARIADB.FULLSCAN.SELECT_RATE.MIN.WARN}|<p>The minimum `SELECT` statement rate (per second) for trigger expressions.</p>|`5`|
|{$MARIADB.BUFF_UTIL.MIN.WARN}|<p>The minimum buffer pool utilization in percent for trigger expressions.</p>|`50`|
|{$MARIADB.CREATED_TMP_TABLES.MAX.WARN}|<p>The maximum number of temporary tables created in memory per second for trigger expressions.</p>|`30`|
|{$MARIADB.CREATED_TMP_DISK_TABLES.MAX.WARN}|<p>The maximum number of temporary tables created on a disk per second for trigger expressions.</p>|`10`|
|{$MARIADB.CREATED_TMP_FILES.MAX.WARN}|<p>The maximum number of temporary files created on a disk per second for trigger expressions.</p>|`10`|
|{$MARIADB.INNODB_LOG_FILES}|<p>Number of physical files in the InnoDB redo log for calculating `innodb_log_file_size`.</p>|`2`|
|{$MARIADB.DBNAME.MATCHES}|<p>Filter of discoverable databases.</p>|`.+`|
|{$MARIADB.DBNAME.NOT_MATCHES}|<p>Filter to exclude discovered databases.</p>|`information_schema\|performance_schema\|mariadb\|sys`|
|{$MARIADB.TABLE.NAMES.MATCHES}|<p>Filter of discoverable tables. Use a context to change the filter of discoverable tables for a specific database.</p>|`CHANGE_IF_NEEDED`|
|{$MARIADB.TABLE.NAMES.NOT_MATCHES}|<p>Filter to exclude discovered tables. Use a context to change the filter to exclude discovered tables for a specific database.</p>|`CHANGE_IF_NEEDED`|
|{$MARIADB.TOTALSIZE.GROWTH.MAX.WARN}|<p>The warning threshold of the total table size growth for trigger expressions.</p>|`300M`|
|{$MARIADB.TOTALSIZE.GROWTH.MAX.CRIT}|<p>The critical threshold of the total table size growth for trigger expressions.</p>|`1G`|
|{$MARIADB.FRAGMENTATION.WARN}|<p>The free ratio threshold for warning fragmentation trigger expressions.</p>|`0.3`|
|{$MARIADB.FRAGMENTATION.CRIT}|<p>The free ratio threshold for critical fragmentation trigger expressions.</p>|`0.6`|
|{$MARIADB.DATA_FREE.MIN.WARN}|<p>The minimum `Data_free` value for warning fragmentation trigger expressions.</p>|`1G`|
|{$MARIADB.DATA_FREE.MIN.CRIT}|<p>The minimum `Data_free` value for critical fragmentation trigger expressions.</p>|`5G`|
|{$MARIADB.TABLE_SIZE.MIN.WARN}|<p>The minimum table size for warning fragmentation trigger expressions.</p>|`3G`|
|{$MARIADB.TABLE_SIZE.MIN.CRIT}|<p>The minimum table size for critical fragmentation trigger expressions.</p>|`10G`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get status variables|<p>Gets server global status information.</p>|Zabbix agent (active)|mariadb.get_status_variables["{$MARIADB.HOST}","{$MARIADB.PORT}"]|
|Status|<p>MariaDB server status.</p>|Zabbix agent (active)|mariadb.ping["{$MARIADB.HOST}","{$MARIADB.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return value.indexOf('is alive') !== -1 ? 1 : 0;`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Version|<p>MariaDB server version.</p>|Zabbix agent (active)|mariadb.version["{$MARIADB.HOST}","{$MARIADB.PORT}"]<p>**Preprocessing**</p><ul><li><p>Regular expression: `(Server version)\s+(.+) \2`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Uptime|<p>Number of seconds that the server has been up.</p>|Dependent item|mariadb.uptime<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Aborted clients per second|<p>Number of connections that were aborted because the client died without closing the connection properly.</p>|Dependent item|mariadb.aborted_clients.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Aborted connections per second|<p>Number of failed attempts to connect to the MariaDB server.</p>|Dependent item|mariadb.aborted_connects.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors accept per second|<p>Number of errors that occurred during calls to `accept()` on the listening port.</p>|Dependent item|mariadb.connection_errors_accept.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors internal per second|<p>Number of refused connections due to internal server errors, for example, out of memory errors, or failed thread starts.</p>|Dependent item|mariadb.connection_errors_internal.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors max connections per second|<p>Number of refused connections due to the `max_connections` limit being reached.</p>|Dependent item|mariadb.connection_errors_max_connections.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors peer address per second|<p>Number of errors while searching for the connecting client's IP address.</p>|Dependent item|mariadb.connection_errors_peer_address.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors select per second|<p>Number of errors during calls to `select()` or `poll()` on the listening port. The client would not necessarily have been rejected in these cases.</p>|Dependent item|mariadb.connection_errors_select.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors tcpwrap per second|<p>Number of connections the libwrap library has refused.</p>|Dependent item|mariadb.connection_errors_tcpwrap.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connections per second|<p>Number of connection attempts (successful or not) to the MariaDB server.</p>|Dependent item|mariadb.connections.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Max used connections|<p>The maximum number of connections that have been in use simultaneously since the server start.</p>|Dependent item|mariadb.max_used_connections<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Threads cached|<p>Number of threads in the thread cache.</p>|Dependent item|mariadb.threads_cached<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Threads connected|<p>Number of currently open connections.</p>|Dependent item|mariadb.threads_connected<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Threads created per second|<p>Number of threads created to handle connections. If the value of `Threads_created` is large, you may want to increase the `thread_cache_size` value. The cache miss rate can be calculated as `Threads_created`/`Connections`.</p>|Dependent item|mariadb.threads_created.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Threads running|<p>Number of threads that are not sleeping.</p>|Dependent item|mariadb.threads_running<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Buffer pool efficiency|<p>The item shows how effectively the buffer pool is serving reads.</p>|Calculated|mariadb.buffer_pool_efficiency|
|Buffer pool utilization|<p>Ratio of used to total pages in the buffer pool.</p>|Calculated|mariadb.buffer_pool_utilization|
|Created tmp files on disk per second|<p>How many temporary files `mariadbd` has created.</p>|Dependent item|mariadb.created_tmp_files.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Created tmp tables on disk per second|<p>Number of internal on-disk temporary tables created by the server while executing statements.</p>|Dependent item|mariadb.created_tmp_disk_tables.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Created tmp tables in memory per second|<p>Number of internal temporary tables created by the server while executing statements.</p>|Dependent item|mariadb.created_tmp_tables.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|InnoDB buffer pool pages free|<p>The number of free pages in the InnoDB buffer pool.</p>|Dependent item|mariadb.innodb_buffer_pool_pages_free<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB buffer pool pages total|<p>The total size of the InnoDB buffer pool, in pages.</p>|Dependent item|mariadb.innodb_buffer_pool_pages_total<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB buffer pool read requests|<p>Number of logical read requests.</p>|Dependent item|mariadb.innodb_buffer_pool_read_requests<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB buffer pool read requests per second|<p>Number of logical read requests per second.</p>|Dependent item|mariadb.innodb_buffer_pool_read_requests.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|InnoDB buffer pool reads|<p>Number of logical reads that InnoDB could not satisfy from the buffer pool and had to read directly from the disk.</p>|Dependent item|mariadb.innodb_buffer_pool_reads<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB buffer pool reads per second|<p>Number of logical reads per second that InnoDB could not satisfy from the buffer pool and had to read directly from the disk.</p>|Dependent item|mariadb.innodb_buffer_pool_reads.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|InnoDB log waits per second|<p>Number of InnoDB log waits per second. An increase in this per-second value</p><p>indicates that threads are increasingly waiting for redo log writes, which typically</p><p>points to disk subsystem performance issues or an `innodb_log_file_size` that is too small.</p>|Dependent item|mariadb.innodb_log_waits.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB row lock time|<p>The total time spent in acquiring row locks for InnoDB tables, in milliseconds.</p>|Dependent item|mariadb.innodb_row_lock_time<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB row lock wait time avg|<p>Average time spent waiting for row locks in InnoDB, in milliseconds.</p>|Dependent item|mariadb.innodb_row_lock_time_avg<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB row lock time max|<p>The maximum time to acquire a row lock for InnoDB tables, in milliseconds.</p>|Dependent item|mariadb.innodb_row_lock_time_max<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB row lock waits|<p>Number of times operations on InnoDB tables had to wait for a row lock.</p>|Dependent item|mariadb.innodb_row_lock_waits<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB row lock current waits|<p>Current number of active row lock waits in InnoDB.</p>|Dependent item|mariadb.innodb_row_lock_current_waits<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Slow queries per second|<p>Number of queries that have taken more than `long_query_time` seconds.</p>|Dependent item|mariadb.slow_queries.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Bytes received|<p>Number of bytes received from all clients.</p>|Dependent item|mariadb.bytes_received.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Bytes sent|<p>Number of bytes sent to all clients.</p>|Dependent item|mariadb.bytes_sent.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command delete per second|<p>The `Com_delete` counter variable indicates the number of times the `DELETE` statement has been executed.</p>|Dependent item|mariadb.com_delete.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command insert per second|<p>The `Com_insert` counter variable indicates the number of times the `INSERT` statement has been executed.</p>|Dependent item|mariadb.com_insert.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command select per second|<p>The `Com_select` counter variable indicates the number of times the `SELECT` statement has been executed.</p>|Dependent item|mariadb.com_select.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command update per second|<p>The `Com_update` counter variable indicates the number of times the `UPDATE` statement has been executed.</p>|Dependent item|mariadb.com_update.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Queries per second|<p>Number of statements executed by the server. This variable includes statements executed within stored programs, unlike the `Questions` variable.</p>|Dependent item|mariadb.queries.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Questions per second|<p>Number of statements executed by the server. This includes only statements sent to the server by clients and not statements executed within stored programs, unlike the `Queries` variable.</p>|Dependent item|mariadb.questions.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Binlog cache disk use|<p>Number of transactions that used a temporary disk cache because they could not fit in the regular binary log cache, being larger than `binlog_cache_size`.</p>|Dependent item|mariadb.binlog_cache_disk_use<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Select scan per second|<p>The number of `SELECT` queries that required a full table scan. A high value indicates a lack of or improper use of indexes.</p>|Dependent item|mariadb.select_scan.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Full table scan ratio|<p>The ratio of `SELECT` statements executed as full table scans to all `SELECT` statements.</p>|Calculated|mariadb.full_scans.ratio<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB buffer pool wait free|<p>Number of times InnoDB waited for a free page before reading or creating a page. Normally, writes to the InnoDB buffer pool happen in the background. When no clean pages are available, dirty pages are flushed first in order to free some up. This counts the number of waits for this operation to finish. If this value is not small, look at the increasing `innodb_buffer_pool_size`.</p>|Dependent item|mariadb.innodb_buffer_pool_wait_free<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|InnoDB number open files|<p>Number of open files held by InnoDB. InnoDB only.</p>|Dependent item|mariadb.innodb_num_open_files<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Open table definitions|<p>Number of cached table definitions.</p>|Dependent item|mariadb.open_table_definitions<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Open tables|<p>Number of tables that are open.</p>|Dependent item|mariadb.open_tables<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|InnoDB log written|<p>Number of bytes written to the InnoDB log.</p>|Dependent item|mariadb.innodb_os_log_written<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Calculated value of innodb_log_file_size|<p>`Innodb_log_file_size` is calculated as: (`innodb_os_log_written`-`innodb_os_log_written`(time shift -1h))/`{$MARIADB.INNODB_LOG_FILES}`. `Innodb_log_file_size` is the size in bytes of each InnoDB redo log file in the log group. The combined size can be no more than 512 GB. Larger values mean less disk I/O due to less flushing checkpoint activity, but also slower recovery from a crash.</p>|Calculated|mariadb.innodb_log_file_size<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Binlog commits|<p>Total number of transactions committed to the binary log.</p>|Dependent item|mariadb.binlog_commits<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Binlog group commits|<p>Total number of group commits done to the binary log.</p>|Dependent item|mariadb.binlog_group_commits<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Master GTID wait count|<p>The number of times `MASTER_GTID_WAIT` was called.</p>|Dependent item|mariadb.master_gtid_wait_count<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Master GTID wait time|<p>Total time spent in `MASTER_GTID_WAIT`.</p>|Dependent item|mariadb.master_gtid_wait_time<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Master GTID wait timeouts|<p>Number of timeouts occurring in `MASTER_GTID_WAIT`.</p>|Dependent item|mariadb.master_gtid_wait_timeouts<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Aria pagecache reads|<p>Number of physical reads from Aria pagecache to disk.</p>|Dependent item|mariadb.aria_pagecache_reads<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Aria pagecache reads per second|<p>Number of physical reads from Aria pagecache to disk per second.</p>|Dependent item|mariadb.aria_pagecache_reads.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Aria pagecache read requests|<p>Number of read requests issued to the Aria pagecache.</p>|Dependent item|mariadb.aria_pagecache_read_requests<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Aria pagecache read requests per second|<p>Number of read requests issued to the Aria pagecache per second.</p>|Dependent item|mariadb.aria_pagecache_read_requests.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Aria pagecache writes per second|<p>Number of physical writes from Aria pagecache to disk per second.</p>|Dependent item|mariadb.aria_pagecache_writes.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Aria pagecache write requests per second|<p>Number of write requests issued to the Aria pagecache per second.</p>|Dependent item|mariadb.aria_pagecache_write_requests.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Aria transaction log syncs per second|<p>Number of Aria transaction log fsync operations per second.</p>|Dependent item|mariadb.aria_transaction_log_syncs.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Aria pagecache blocks not flushed|<p>Number of Aria pagecache blocks that contain modified data and have not yet been flushed to disk.</p>|Dependent item|mariadb.aria_pagecache_blocks_not_flushed<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Aria pagecache blocks unused|<p>Number of currently free blocks in the Aria pagecache buffer.</p>|Dependent item|mariadb.aria_pagecache_blocks_unused<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Aria pagecache blocks used|<p>Number of blocks currently in use in the Aria pagecache buffer.</p>|Dependent item|mariadb.aria_pagecache_blocks_used<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Aria pagecache hit rate|<p>Percentage of Aria pagecache read requests served from cache (higher means fewer disk reads).</p>|Calculated|mariadb.aria_pagecache_hit_rate|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MariaDB: Service is unreachable|<p>MariaDB is unreachable.</p>|`last(/MariaDB by Zabbix agent active/mariadb.ping["{$MARIADB.HOST}","{$MARIADB.PORT}"])=0`|High||
|MariaDB: Version has changed|<p>The MariaDB version has changed. Acknowledge to close the problem manually.</p>|`last(/MariaDB by Zabbix agent active/mariadb.version["{$MARIADB.HOST}","{$MARIADB.PORT}"],#1)<>last(/MariaDB by Zabbix agent active/mariadb.version["{$MARIADB.HOST}","{$MARIADB.PORT}"],#2) and length(last(/MariaDB by Zabbix agent active/mariadb.version["{$MARIADB.HOST}","{$MARIADB.PORT}"]))>0`|Info|**Manual close**: Yes|
|MariaDB: Service has been restarted|<p>MariaDB uptime is less than 10 minutes.</p>|`last(/MariaDB by Zabbix agent active/mariadb.uptime)<10m`|Info||
|MariaDB: Failed to fetch info data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/MariaDB by Zabbix agent active/mariadb.uptime,30m)=1`|Info|**Depends on**:<br><ul><li>MariaDB: Service is unreachable</li></ul>|
|MariaDB: Server has aborted connections|<p>The number of failed attempts to connect to the MariaDB server has been more than `{$MARIADB.ABORTED_CONN.MAX.WARN}` in the last 5 minutes.</p>|`min(/MariaDB by Zabbix agent active/mariadb.aborted_connects.rate,5m)>{$MARIADB.ABORTED_CONN.MAX.WARN}`|Average|**Depends on**:<br><ul><li>MariaDB: Refused connections</li></ul>|
|MariaDB: Refused connections|<p>Number of refused connections due to the `max_connections` limit being reached.</p>|`last(/MariaDB by Zabbix agent active/mariadb.connection_errors_max_connections.rate)>0`|Average||
|MariaDB: Buffer pool utilization is too low|<p>The buffer pool utilization has been less than `{$MARIADB.BUFF_UTIL.MIN.WARN}`% in the last 5 minutes with server uptime over 1 hour. This means that there is a lot of unused RAM allocated for the buffer pool, which you can easily reallocate at the moment.</p>|`max(/MariaDB by Zabbix agent active/mariadb.buffer_pool_utilization,5m)<{$MARIADB.BUFF_UTIL.MIN.WARN} and last(/MariaDB by Zabbix agent active/mariadb.uptime)>3600`|Warning||
|MariaDB: Number of temporary files created per second is high|<p>The application using the database may be in need of query optimization.</p>|`min(/MariaDB by Zabbix agent active/mariadb.created_tmp_files.rate,5m)>{$MARIADB.CREATED_TMP_FILES.MAX.WARN}`|Warning||
|MariaDB: Number of on-disk temporary tables created per second is high|<p>The application using the database may be in need of query optimization.</p>|`min(/MariaDB by Zabbix agent active/mariadb.created_tmp_disk_tables.rate,5m)>{$MARIADB.CREATED_TMP_DISK_TABLES.MAX.WARN}`|Warning||
|MariaDB: Number of internal temporary tables created per second is high|<p>The application using the database may be in need of query optimization.</p>|`min(/MariaDB by Zabbix agent active/mariadb.created_tmp_tables.rate,5m)>{$MARIADB.CREATED_TMP_TABLES.MAX.WARN}`|Warning||
|MariaDB: Server has slow queries|<p>The number of slow queries has been more than `{$MARIADB.SLOW_QUERIES.MAX.WARN}` in the last 5 minutes.</p>|`min(/MariaDB by Zabbix agent active/mariadb.slow_queries.rate,5m)>{$MARIADB.SLOW_QUERIES.MAX.WARN}`|Warning||
|MariaDB: High rate of full table scans|<p>The rate of `SELECT` statements exceeds `{$MARIADB.FULLSCAN.SELECT_RATE.MIN.WARN}` per second and the full table scan ratio has been above `{$MARIADB.FULLSCAN.RATIO.WARN}` for the last 5 minutes.</p>|`avg(/MariaDB by Zabbix agent active/mariadb.full_scans.ratio,5m)>{$MARIADB.FULLSCAN.RATIO.WARN} and avg(/MariaDB by Zabbix agent active/mariadb.com_select.rate,5m)>{$MARIADB.FULLSCAN.SELECT_RATE.MIN.WARN}`|Warning||

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Used for the discovery of databases.</p>|Zabbix agent (active)|mariadb.db.discovery["{$MARIADB.HOST}","{$MARIADB.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database [{#DBNAME}]: Size|<p>Database size.</p>|Zabbix agent (active)|mariadb.dbsize["{$MARIADB.HOST}","{$MARIADB.PORT}","{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Database [{#DBNAME}]: Table discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database [{#DBNAME}]: Table discovery|<p>Used for the discovery of tables of the databases.</p>|Zabbix agent (active)|mariadb.table.discovery["{$MARIADB.HOST}","{$MARIADB.PORT}","{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Database [{#DBNAME}]: Table discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Table [{#DBNAME}/{#TABLE_NAME}]: Get data|<p>Database table data.</p>|Zabbix agent (active)|mariadb.tabledata["{$MARIADB.HOST}","{$MARIADB.PORT}","{#DBNAME}","{#TABLE_NAME}"]|
|Table [{#DBNAME}/{#TABLE_NAME}]: Data length|<p>The size of the data in the table.</p>|Dependent item|mariadb.table.data_length["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='DATA_LENGTH']/text()`</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Table [{#DBNAME}/{#TABLE_NAME}]: Index length|<p>The size occupied by the table's indexes.</p>|Dependent item|mariadb.table.index_length["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='INDEX_LENGTH']/text()`</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Table [{#DBNAME}/{#TABLE_NAME}]: Total size|<p>The sum of `DATA_LENGTH` and `INDEX_LENGTH` represents the total size of the table.</p>|Calculated|mariadb.table.total_size["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Table [{#DBNAME}/{#TABLE_NAME}]: Data free|<p>The amount of unused but allocated space.</p>|Dependent item|mariadb.table.data_free["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='DATA_FREE']/text()`</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Table [{#DBNAME}/{#TABLE_NAME}]: Free ratio|<p>The fraction of allocated table space that is currently unused (`DATA_FREE` / (`DATA_LENGTH` + `INDEX_LENGTH`)).</p><p>High values indicate potential table fragmentation or inefficient space usage.</p>|Calculated|mariadb.table.free_ratio["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Table [{#DBNAME}/{#TABLE_NAME}]: Rows|<p>The approximate number of rows in the table. For InnoDB, the `TABLE_ROWS` value is approximate and may differ from the actual count.</p>|Dependent item|mariadb.table.rows["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='TABLE_ROWS']/text()`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Database [{#DBNAME}]: Table discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MariaDB: Table [{#DBNAME}/{#TABLE_NAME}]: Total size growth is too high|<p>Total table size growth is too high.</p>|`(last(/MariaDB by Zabbix agent active/mariadb.table.total_size["{#DBNAME}","{#TABLE_NAME}"])-min(/MariaDB by Zabbix agent active/mariadb.table.total_size["{#DBNAME}","{#TABLE_NAME}"],5m))>{$MARIADB.TOTALSIZE.GROWTH.MAX.CRIT}`|High||
|MariaDB: Table [{#DBNAME}/{#TABLE_NAME}]: Total size growth is high|<p>Total table size growth is high.</p>|`(last(/MariaDB by Zabbix agent active/mariadb.table.total_size["{#DBNAME}","{#TABLE_NAME}"])-min(/MariaDB by Zabbix agent active/mariadb.table.total_size["{#DBNAME}","{#TABLE_NAME}"],5m))>{$MARIADB.TOTALSIZE.GROWTH.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>MariaDB: Table [{#DBNAME}/{#TABLE_NAME}]: Total size growth is too high</li></ul>|
|MariaDB: Table [{#DBNAME}/{#TABLE_NAME}]: Free space very high|<p>Free table space is very high (fragmentation critical).</p>|`last(/MariaDB by Zabbix agent active/mariadb.table.free_ratio["{#DBNAME}","{#TABLE_NAME}"]) > {$MARIADB.FRAGMENTATION.CRIT} and last(/MariaDB by Zabbix agent active/mariadb.table.data_free["{#DBNAME}","{#TABLE_NAME}"]) > {$MARIADB.DATA_FREE.MIN.CRIT} and last(/MariaDB by Zabbix agent active/mariadb.table.total_size["{#DBNAME}","{#TABLE_NAME}"]) > {$MARIADB.TABLE_SIZE.MIN.CRIT}`|High||
|MariaDB: Table [{#DBNAME}/{#TABLE_NAME}]: Free space high|<p>Free table space is high (fragmentation warning).</p>|`last(/MariaDB by Zabbix agent active/mariadb.table.free_ratio["{#DBNAME}","{#TABLE_NAME}"]) > {$MARIADB.FRAGMENTATION.WARN} and last(/MariaDB by Zabbix agent active/mariadb.table.data_free["{#DBNAME}","{#TABLE_NAME}"]) > {$MARIADB.DATA_FREE.MIN.WARN} and last(/MariaDB by Zabbix agent active/mariadb.table.total_size["{#DBNAME}","{#TABLE_NAME}"]) > {$MARIADB.TABLE_SIZE.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>MariaDB: Table [{#DBNAME}/{#TABLE_NAME}]: Free space very high</li></ul>|

### LLD rule Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication discovery|<p>Discovery of the replication.</p>|Zabbix agent (active)|mariadb.replication.discovery["{$MARIADB.HOST}","{$MARIADB.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication: Replica status {#MASTER_HOST}|<p>Gets status information on the essential parameters of the replica threads.</p>|Zabbix agent (active)|mariadb.slave_status["{$MARIADB.HOST}","{$MARIADB.PORT}","{#SQL_STATEMENT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Replication: Replica SQL running state {#MASTER_HOST}|<p>Shows the state of the SQL driver threads.</p>|Dependent item|mariadb.slave_sql_running_state["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Slave_SQL_Running_State']/text()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Replication: Seconds behind source {#MASTER_HOST}|<p>The number of seconds the replica SQL thread has been behind processing the source binary log.</p><p>A high number (or an increasing one) can indicate that the replica is unable to handle events</p><p>from the source in a timely fashion.</p>|Dependent item|mariadb.seconds_behind_master["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Seconds_Behind_Master']/text()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Matches regular expression: `\d+`</p><p>⛔️Custom on fail: Set error to: `Replication is not performed.`</p></li></ul>|
|Replication: Replica IO running {#MASTER_HOST}|<p>Indicates whether the I/O thread for reading the source's binary log is running.</p><p>Normally, you want this to be `Yes` unless you have not yet started a replication or have</p><p>explicitly stopped it with `STOP REPLICA`.</p>|Dependent item|mariadb.slave_io_running["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Slave_IO_Running']/text()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Replication: Replica SQL running {#MASTER_HOST}|<p>Indicates whether the SQL thread for executing events in the relay log is running.</p><p>As with the I/O thread, this should normally be `Yes`.</p>|Dependent item|mariadb.slave_sql_running["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Slave_SQL_Running']/text()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Replication: Last IO error {#MASTER_HOST}|<p>Describes the last I/O error message reported by the replica I/O thread that caused replication to stop or behave incorrectly.</p>|Dependent item|mariadb.slave_io_error["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Last_IO_Error']/text()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Replication: Last SQL error {#MASTER_HOST}|<p>Describes the last SQL error message reported by the replica SQL thread that caused replication to stop or behave incorrectly.</p>|Dependent item|mariadb.slave_sql_error["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Last_SQL_Error']/text()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Replication discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MariaDB: Replication lag is too high|<p>Replication delay is too long.</p>|`min(/MariaDB by Zabbix agent active/mariadb.seconds_behind_master["{#MASTER_HOST}"],5m)>{$MARIADB.REPL_LAG.MAX.WARN}`|Warning||
|MariaDB: The replica I/O thread is not running|<p>Indicates whether the I/O thread for reading the source's binary log is running.</p>|`last(/MariaDB by Zabbix agent active/mariadb.slave_io_running["{#MASTER_HOST}"])=2`|Average||
|MariaDB: The replica I/O thread is not connected to a replication source|<p>Indicates whether the replica I/O thread is connected to the source.</p>|`last(/MariaDB by Zabbix agent active/mariadb.slave_io_running["{#MASTER_HOST}"])<>1`|Warning|**Depends on**:<br><ul><li>MariaDB: The replica I/O thread is not running</li></ul>|
|MariaDB: The SQL thread is not running|<p>Indicates whether the SQL thread for executing events in the relay log is running.</p>|`last(/MariaDB by Zabbix agent active/mariadb.slave_sql_running["{#MASTER_HOST}"])=2`|Warning|**Depends on**:<br><ul><li>MariaDB: The replica I/O thread is not running</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

