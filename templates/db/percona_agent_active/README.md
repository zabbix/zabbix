
# Percona by Zabbix agent active

## Overview

This template is designed for the effortless deployment of Percona monitoring by Zabbix via Zabbix agent active and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Percona 8.0, 8.4, 8.4.7-7

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Install Zabbix agent and MySQL client. If necessary, add the path to the `mysql` and `mysqladmin` utilities to the global environment variable PATH.
2. Copy the `template_db_percona.conf` file with user parameters into folder with Zabbix agent configuration (/etc/zabbix/zabbix_agentd.d/ by default). Don't forget to restart Zabbix agent.
3. Create the Percona user that will be used for monitoring (`<password>` at your discretion). For example:

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
- The discovery of tables is disabled by default. To enable table discovery, change the value of the macro `{$PERCONA.TABLE.NAMES.MATCHES}` to the name of a single table, or use a regex string to include multiple tables.
- You can also add an additional context macro `{$PERCONA.TABLE.NAMES.MATCHES:<dbname>}` for specific databases to precisely control which tables should be discovered.
- To exclude certain tables from discovery, change the value of the macro `{$PERCONA.TABLE.NAMES.NOT_MATCHES}` to the name of a single table, or use a regex string to exclude multiple tables.
- You can also add an additional context macro `{$PERCONA.TABLE.NAMES.NOT_MATCHES:<dbname>}` for specific databases to precisely control which tables should be excluded.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PERCONA.HOST}|<p>Hostname or IP of Percona host or container.</p>|`127.0.0.1`|
|{$PERCONA.PORT}|<p>Percona service port.</p>|`3306`|
|{$PERCONA.ABORTED_CONN.MAX.WARN}|<p>Number of failed attempts to connect to the Percona server for trigger expressions.</p>|`3`|
|{$PERCONA.REPL_LAG.MAX.WARN}|<p>Amount of time the replica is behind the source for trigger expressions.</p>|`30m`|
|{$PERCONA.SLOW_QUERIES.MAX.WARN}|<p>Number of slow queries for trigger expressions.</p>|`3`|
|{$PERCONA.FULLSCAN.RATIO.WARN}|<p>Warning threshold for full table scan ratio.</p>|`0.1`|
|{$PERCONA.FULLSCAN.SELECT_RATE.MIN.WARN}|<p>The minimum `SELECT` statement rate (per second) for trigger expressions.</p>|`5`|
|{$PERCONA.BUFF_UTIL.MIN.WARN}|<p>The minimum buffer pool utilization in percent for trigger expressions.</p>|`50`|
|{$PERCONA.CREATED_TMP_TABLES.MAX.WARN}|<p>The maximum number of temporary tables created in memory per second for trigger expressions.</p>|`30`|
|{$PERCONA.CREATED_TMP_DISK_TABLES.MAX.WARN}|<p>The maximum number of temporary tables created on a disk per second for trigger expressions.</p>|`10`|
|{$PERCONA.CREATED_TMP_FILES.MAX.WARN}|<p>The maximum number of temporary files created on a disk per second for trigger expressions.</p>|`10`|
|{$PERCONA.INNODB_LOG_FILES}|<p>Number of physical files in the InnoDB redo log for calculating `innodb_log_file_size`.</p>|`2`|
|{$PERCONA.INNODB_SCANS.MIN.WARN}|<p>The minimum total InnoDB scans for warning trigger expressions.</p>|`0`|
|{$PERCONA.INNODB_SCANS.MIN.CRIT}|<p>The minimum total InnoDB scans for critical trigger expressions.</p>|`1`|
|{$PERCONA.INNODB_EFFICIENCY.MIN.WARN}|<p>The minimum InnoDB scan efficiency in percent for warning trigger expressions.</p>|`80`|
|{$PERCONA.INNODB_EFFICIENCY.MIN.CRIT}|<p>The minimum InnoDB scan efficiency in percent for critical trigger expressions.</p>|`50`|
|{$PERCONA.DBNAME.MATCHES}|<p>Filter of discoverable databases.</p>|`.+`|
|{$PERCONA.DBNAME.NOT_MATCHES}|<p>Filter to exclude discovered databases.</p>|`information_schema\|performance_schema\|percona\|sys\|mysql`|
|{$PERCONA.TABLE.NAMES.MATCHES}|<p>Filter of discoverable tables. Use a context to change the filter of discoverable tables for a specific database.</p>|`CHANGE_IF_NEEDED`|
|{$PERCONA.TABLE.NAMES.NOT_MATCHES}|<p>Filter to exclude discovered tables. Use a context to change the filter to exclude discovered tables for a specific database.</p>|`CHANGE_IF_NEEDED`|
|{$PERCONA.TOTALSIZE.GROWTH.MAX.WARN}|<p>The warning threshold of the total table size growth for trigger expressions.</p>|`300M`|
|{$PERCONA.TOTALSIZE.GROWTH.MAX.CRIT}|<p>The critical threshold of the total table size growth for trigger expressions.</p>|`1G`|
|{$PERCONA.FRAGMENTATION.WARN}|<p>The free ratio threshold for warning fragmentation trigger expressions.</p>|`0.3`|
|{$PERCONA.FRAGMENTATION.CRIT}|<p>The free ratio threshold for critical fragmentation trigger expressions.</p>|`0.6`|
|{$PERCONA.DATA_FREE.MIN.WARN}|<p>The minimum `Data_free` value for warning fragmentation trigger expressions.</p>|`1G`|
|{$PERCONA.DATA_FREE.MIN.CRIT}|<p>The minimum `Data_free` value for critical fragmentation trigger expressions.</p>|`5G`|
|{$PERCONA.TABLE_SIZE.MIN.WARN}|<p>The minimum table size for warning fragmentation trigger expressions.</p>|`3G`|
|{$PERCONA.TABLE_SIZE.MIN.CRIT}|<p>The minimum table size for critical fragmentation trigger expressions.</p>|`10G`|
|{$PERCONA.THREADPOOL.UTILIZATION.MAX.WARN}|<p>The maximum thread pool utilization in percent for trigger expressions.</p>|`85`|
|{$PERCONA.THREADPOOL.IDLE_RATIO.MIN.WARN}|<p>The minimum thread pool idle ratio in percent for trigger expressions.</p>|`20`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get status variables|<p>Gets server global status information.</p>|Zabbix agent (active)|percona.get_status_variables["{$PERCONA.HOST}","{$PERCONA.PORT}"]|
|Get global variables|<p>Gets server global variables for thread pool configuration.</p>|Zabbix agent (active)|percona.get_global_variables["{$PERCONA.HOST}","{$PERCONA.PORT}"]|
|Status|<p>Percona server status.</p>|Zabbix agent (active)|percona.ping["{$PERCONA.HOST}","{$PERCONA.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return value.indexOf('is alive') !== -1 ? 1 : 0;`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Version|<p>Percona server version.</p>|Zabbix agent (active)|percona.version["{$PERCONA.HOST}","{$PERCONA.PORT}"]<p>**Preprocessing**</p><ul><li><p>Regular expression: `(Server version)\s+(.+) \2`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Uptime|<p>Number of seconds that the server has been up.</p>|Dependent item|percona.uptime<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Aborted clients per second|<p>Number of connections that were aborted because the client died without closing the connection properly.</p>|Dependent item|percona.aborted_clients.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Aborted connections per second|<p>Number of failed attempts to connect to the Percona server.</p>|Dependent item|percona.aborted_connects.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors accept per second|<p>Number of errors that occurred during calls to `accept()` on the listening port.</p>|Dependent item|percona.connection_errors_accept.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors internal per second|<p>Number of refused connections due to internal server errors, for example, out of memory errors, or failed thread starts.</p>|Dependent item|percona.connection_errors_internal.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors max connections per second|<p>Number of refused connections due to the `max_connections` limit being reached.</p>|Dependent item|percona.connection_errors_max_connections.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors peer address per second|<p>Number of errors while searching for the connecting client's IP address.</p>|Dependent item|percona.connection_errors_peer_address.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors select per second|<p>Number of errors during calls to `select()` or `poll()` on the listening port. The client would not necessarily have been rejected in these cases.</p>|Dependent item|percona.connection_errors_select.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connection errors tcpwrap per second|<p>Number of connections the libwrap library has refused.</p>|Dependent item|percona.connection_errors_tcpwrap.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Connections per second|<p>Number of connection attempts (successful or not) to the Percona server.</p>|Dependent item|percona.connections.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Max used connections|<p>The maximum number of connections that have been in use simultaneously since the server start.</p>|Dependent item|percona.max_used_connections<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Threads cached|<p>Number of threads in the thread cache.</p>|Dependent item|percona.threads_cached<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Threads connected|<p>Number of currently open connections.</p>|Dependent item|percona.threads_connected<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Threads created per second|<p>Number of threads created to handle connections. If the value of `Threads_created` is large, you may want to increase the `thread_cache_size` value. The cache miss rate can be calculated as `Threads_created`/`Connections`.</p>|Dependent item|percona.threads_created.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Threads running|<p>Number of threads that are not sleeping.</p>|Dependent item|percona.threads_running<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|Buffer pool efficiency|<p>The item shows how effectively the buffer pool is serving reads.</p>|Calculated|percona.buffer_pool_efficiency|
|Buffer pool utilization|<p>Ratio of used to total pages in the buffer pool.</p>|Calculated|percona.buffer_pool_utilization|
|Created tmp files on disk per second|<p>How many temporary files `mysqld` has created.</p>|Dependent item|percona.created_tmp_files.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Created tmp tables on disk per second|<p>Number of internal on-disk temporary tables created by the server while executing statements.</p>|Dependent item|percona.created_tmp_disk_tables.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Created tmp tables in memory per second|<p>Number of internal temporary tables created by the server while executing statements.</p>|Dependent item|percona.created_tmp_tables.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|InnoDB buffer pool pages free|<p>The number of free pages in the InnoDB buffer pool.</p>|Dependent item|percona.innodb_buffer_pool_pages_free<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB buffer pool pages total|<p>The total size of the InnoDB buffer pool, in pages.</p>|Dependent item|percona.innodb_buffer_pool_pages_total<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB buffer pool read requests|<p>Number of logical read requests.</p>|Dependent item|percona.innodb_buffer_pool_read_requests<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB buffer pool read requests per second|<p>Number of logical read requests per second.</p>|Dependent item|percona.innodb_buffer_pool_read_requests.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|InnoDB buffer pool reads|<p>Number of logical reads that InnoDB could not satisfy from the buffer pool and had to read directly from the disk.</p>|Dependent item|percona.innodb_buffer_pool_reads<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB buffer pool reads per second|<p>Number of logical reads per second that InnoDB could not satisfy from the buffer pool and had to read directly from the disk.</p>|Dependent item|percona.innodb_buffer_pool_reads.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|InnoDB log waits per second|<p>Number of InnoDB log waits per second. An increase in this per-second value</p><p>indicates that threads are increasingly waiting for redo log writes, which typically</p><p>points to disk subsystem performance issues or an `innodb_log_file_size` that is too small.</p>|Dependent item|percona.innodb_log_waits.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB row lock time|<p>The total time spent in acquiring row locks for InnoDB tables, in milliseconds.</p>|Dependent item|percona.innodb_row_lock_time<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB row lock wait time avg|<p>Average time spent waiting for row locks in InnoDB, in milliseconds.</p>|Dependent item|percona.innodb_row_lock_time_avg<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB row lock time max|<p>The maximum time to acquire a row lock for InnoDB tables, in milliseconds.</p>|Dependent item|percona.innodb_row_lock_time_max<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB row lock waits|<p>Number of times operations on InnoDB tables had to wait for a row lock.</p>|Dependent item|percona.innodb_row_lock_waits<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB row lock current waits|<p>Current number of active row lock waits in InnoDB.</p>|Dependent item|percona.innodb_row_lock_current_waits<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li></ul>|
|InnoDB rows deleted per second|<p>Number of rows deleted from InnoDB tables per second.</p>|Dependent item|percona.innodb_rows_deleted.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB rows inserted per second|<p>Number of rows inserted into InnoDB tables per second.</p>|Dependent item|percona.innodb_rows_inserted.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB rows read per second|<p>Number of rows read from InnoDB tables per second.</p>|Dependent item|percona.innodb_rows_read.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB rows updated per second|<p>Number of rows updated in InnoDB tables per second.</p>|Dependent item|percona.innodb_rows_updated.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB buffered aio submitted per second|<p>Number of submitted buffered asynchronous I/O requests per second.</p>|Dependent item|percona.innodb_buffered_aio_submitted.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB scan pages contiguous per second|<p>Number of contiguous page reads inside a query per second.</p>|Dependent item|percona.innodb_scan_pages_contiguous.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB scan pages disjointed per second|<p>Number of disjointed page reads inside a query per second.</p>|Dependent item|percona.innodb_scan_pages_disjointed.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB scan data size|<p>The size of data in all InnoDB pages read inside a query in bytes.</p>|Dependent item|percona.innodb_scan_data_size<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB total scan pages per second|<p>Total InnoDB table/index scan pages per second.</p>|Calculated|percona.innodb_total_scans.rate<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB scan efficiency|<p>Percentage of efficiently scanned pages, where lower values may indicate increased InnoDB fragmentation.</p>|Calculated|percona.innodb_scan.efficiency<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Slow queries per second|<p>Number of queries that have taken more than `long_query_time` seconds.</p>|Dependent item|percona.slow_queries.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Bytes received|<p>Number of bytes received from all clients.</p>|Dependent item|percona.bytes_received.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Bytes sent|<p>Number of bytes sent to all clients.</p>|Dependent item|percona.bytes_sent.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command delete per second|<p>The `Com_delete` counter variable indicates the number of times the `DELETE` statement has been executed.</p>|Dependent item|percona.com_delete.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command insert per second|<p>The `Com_insert` counter variable indicates the number of times the `INSERT` statement has been executed.</p>|Dependent item|percona.com_insert.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command select per second|<p>The `Com_select` counter variable indicates the number of times the `SELECT` statement has been executed.</p>|Dependent item|percona.com_select.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Command update per second|<p>The `Com_update` counter variable indicates the number of times the `UPDATE` statement has been executed.</p>|Dependent item|percona.com_update.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Queries per second|<p>Number of statements executed by the server. This variable includes statements executed within stored programs, unlike the `Questions` variable.</p>|Dependent item|percona.queries.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Questions per second|<p>Number of statements executed by the server. This includes only statements sent to the server by clients and not statements executed within stored programs, unlike the `Queries` variable.</p>|Dependent item|percona.questions.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Binlog cache disk use|<p>Number of transactions that used a temporary disk cache because they could not fit in the regular binary log cache, being larger than `binlog_cache_size`.</p>|Dependent item|percona.binlog_cache_disk_use<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Select scan per second|<p>The number of `SELECT` queries that required a full table scan. A high value indicates a lack of or improper use of indexes.</p>|Dependent item|percona.select_scan.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Full table scan ratio|<p>The ratio of `SELECT` statements executed as full table scans to all `SELECT` statements.</p>|Calculated|percona.full_scans.ratio<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|InnoDB buffer pool wait free|<p>Number of times InnoDB waited for a free page before reading or creating a page.</p><p>Normally, writes to the InnoDB buffer pool happen in the background.</p><p>When no clean pages are available, dirty pages are flushed first</p><p>in order to free some up. This counts the number of waits for this operation</p><p>to finish. If this value is not small, look at the increasing `innodb_buffer_pool_size`.</p>|Dependent item|percona.innodb_buffer_pool_wait_free<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|InnoDB buffer pool wait free per second|<p>Number of times per second InnoDB waited for a free page before reading or creating a page.</p><p>Non-zero values indicate the buffer pool is too small; increase `innodb_buffer_pool_size`.</p>|Dependent item|percona.innodb_buffer_pool_wait_free.rate<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|InnoDB number open files|<p>Number of open files held by InnoDB. InnoDB only.</p>|Dependent item|percona.innodb_num_open_files<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Open table definitions|<p>Number of cached table definitions.</p>|Dependent item|percona.open_table_definitions<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Open tables|<p>Number of tables that are open.</p>|Dependent item|percona.open_tables<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|InnoDB log written|<p>Number of bytes written to the InnoDB log.</p>|Dependent item|percona.innodb_os_log_written<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Calculated value of innodb_log_file_size|<p>`Innodb_log_file_size` is calculated as: (`innodb_os_log_written`-`innodb_os_log_written`(time shift -1h))/`{$PERCONA.INNODB_LOG_FILES}`. `Innodb_log_file_size` is the size in bytes of each InnoDB redo log file in the log group. The combined size can be no more than 512 GB. Larger values mean less disk I/O due to less flushing checkpoint activity, but also slower recovery from a crash.</p>|Calculated|percona.innodb_log_file_size<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Percona: Service is unreachable|<p>Percona is unreachable.</p>|`last(/Percona by Zabbix agent active/percona.ping["{$PERCONA.HOST}","{$PERCONA.PORT}"])=0`|High||
|Percona: Version has changed|<p>The Percona version has changed. Acknowledge to close the problem manually.</p>|`last(/Percona by Zabbix agent active/percona.version["{$PERCONA.HOST}","{$PERCONA.PORT}"],#1)<>last(/Percona by Zabbix agent active/percona.version["{$PERCONA.HOST}","{$PERCONA.PORT}"],#2) and length(last(/Percona by Zabbix agent active/percona.version["{$PERCONA.HOST}","{$PERCONA.PORT}"]))>0`|Info|**Manual close**: Yes|
|Percona: Service has been restarted|<p>Percona uptime is less than 10 minutes.</p>|`last(/Percona by Zabbix agent active/percona.uptime)<10m`|Info||
|Percona: Failed to fetch data|<p>Zabbix has not received any data for the last 30 minutes.</p>|`nodata(/Percona by Zabbix agent active/percona.uptime,30m)=1`|Info|**Depends on**:<br><ul><li>Percona: Service is unreachable</li></ul>|
|Percona: Server has aborted connections|<p>The number of failed attempts to connect to the Percona server has been more than `{$PERCONA.ABORTED_CONN.MAX.WARN}` in the last 5 minutes.</p>|`min(/Percona by Zabbix agent active/percona.aborted_connects.rate,5m)>{$PERCONA.ABORTED_CONN.MAX.WARN}`|Average|**Depends on**:<br><ul><li>Percona: Refused connections</li></ul>|
|Percona: Refused connections|<p>Number of refused connections due to the `max_connections` limit being reached.</p>|`last(/Percona by Zabbix agent active/percona.connection_errors_max_connections.rate)>0`|Average||
|Percona: Buffer pool utilization is too low|<p>The buffer pool utilization has been less than `{$PERCONA.BUFF_UTIL.MIN.WARN}`% in the last 5 minutes with server uptime over 1 hour. This means that there is a lot of unused RAM allocated for the buffer pool, which you can easily reallocate at the moment.</p>|`max(/Percona by Zabbix agent active/percona.buffer_pool_utilization,5m)<{$PERCONA.BUFF_UTIL.MIN.WARN} and last(/Percona by Zabbix agent active/percona.uptime)>3600`|Warning||
|Percona: Number of temporary files created per second is high|<p>The application using the database may be in need of query optimization.</p>|`min(/Percona by Zabbix agent active/percona.created_tmp_files.rate,5m)>{$PERCONA.CREATED_TMP_FILES.MAX.WARN}`|Warning||
|Percona: Number of on-disk temporary tables created per second is high|<p>The application using the database may be in need of query optimization.</p>|`min(/Percona by Zabbix agent active/percona.created_tmp_disk_tables.rate,5m)>{$PERCONA.CREATED_TMP_DISK_TABLES.MAX.WARN}`|Warning||
|Percona: Number of internal temporary tables created per second is high|<p>The application using the database may be in need of query optimization.</p>|`min(/Percona by Zabbix agent active/percona.created_tmp_tables.rate,5m)>{$PERCONA.CREATED_TMP_TABLES.MAX.WARN}`|Warning||
|Percona: InnoDB efficiency is too low|<p>The InnoDB efficiency has been less than `{$PERCONA.INNODB_EFFICIENCY.MIN.CRIT}`% in the last 5 minutes.</p>|`max(/Percona by Zabbix agent active/percona.innodb_scan.efficiency,5m)<{$PERCONA.INNODB_EFFICIENCY.MIN.CRIT} and last(/Percona by Zabbix agent active/percona.innodb_total_scans.rate)>{$PERCONA.INNODB_SCANS.MIN.CRIT}`|High||
|Percona: InnoDB efficiency is low|<p>The InnoDB efficiency has been less than `{$PERCONA.INNODB_EFFICIENCY.MIN.WARN}`% in the last 5 minutes.</p>|`max(/Percona by Zabbix agent active/percona.innodb_scan.efficiency,5m)<{$PERCONA.INNODB_EFFICIENCY.MIN.WARN} and last(/Percona by Zabbix agent active/percona.innodb_total_scans.rate)>{$PERCONA.INNODB_SCANS.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>Percona: InnoDB efficiency is too low</li></ul>|
|Percona: Server has slow queries|<p>The number of slow queries has been more than `{$PERCONA.SLOW_QUERIES.MAX.WARN}` in the last 5 minutes.</p>|`min(/Percona by Zabbix agent active/percona.slow_queries.rate,5m)>{$PERCONA.SLOW_QUERIES.MAX.WARN}`|Warning||
|Percona: High rate of full table scans|<p>The rate of `SELECT` statements exceeds `{$PERCONA.FULLSCAN.SELECT_RATE.MIN.WARN}` per second and the full table scan ratio has been above `{$PERCONA.FULLSCAN.RATIO.WARN}` for the last 5 minutes.</p>|`avg(/Percona by Zabbix agent active/percona.full_scans.ratio,5m)>{$PERCONA.FULLSCAN.RATIO.WARN} and avg(/Percona by Zabbix agent active/percona.com_select.rate,5m)>{$PERCONA.FULLSCAN.SELECT_RATE.MIN.WARN}`|Warning||

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Used for the discovery of databases.</p>|Zabbix agent (active)|percona.db.discovery["{$PERCONA.HOST}","{$PERCONA.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database [{#DBNAME}]: Size|<p>Database size.</p>|Zabbix agent (active)|percona.dbsize["{$PERCONA.HOST}","{$PERCONA.PORT}","{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Database [{#DBNAME}]: Table discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database [{#DBNAME}]: Table discovery|<p>Used for the discovery of tables of the databases.</p>|Zabbix agent (active)|percona.table.discovery["{$PERCONA.HOST}","{$PERCONA.PORT}","{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Database [{#DBNAME}]: Table discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Table [{#DBNAME}/{#TABLE_NAME}]: Get data|<p>Database table data.</p>|Zabbix agent (active)|percona.tabledata["{$PERCONA.HOST}","{$PERCONA.PORT}","{#DBNAME}","{#TABLE_NAME}"]|
|Table [{#DBNAME}/{#TABLE_NAME}]: Data length|<p>The size of the data in the table.</p>|Dependent item|percona.table.data_length["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='DATA_LENGTH']/text()`</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Table [{#DBNAME}/{#TABLE_NAME}]: Index length|<p>The size occupied by the table's indexes.</p>|Dependent item|percona.table.index_length["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='INDEX_LENGTH']/text()`</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Table [{#DBNAME}/{#TABLE_NAME}]: Total size|<p>The sum of `DATA_LENGTH` and `INDEX_LENGTH` represents the total size of the table.</p>|Calculated|percona.table.total_size["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Table [{#DBNAME}/{#TABLE_NAME}]: Data free|<p>The amount of unused but allocated space.</p>|Dependent item|percona.table.data_free["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='DATA_FREE']/text()`</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Table [{#DBNAME}/{#TABLE_NAME}]: Free ratio|<p>The fraction of allocated table space that is currently unused (`DATA_FREE` / (`DATA_LENGTH` + `INDEX_LENGTH`)).</p><p>High values indicate potential table fragmentation or inefficient space usage.</p>|Calculated|percona.table.free_ratio["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Table [{#DBNAME}/{#TABLE_NAME}]: Rows|<p>The approximate number of rows in the table. For InnoDB, the `TABLE_ROWS` value is approximate and may differ from the actual count.</p>|Dependent item|percona.table.rows["{#DBNAME}","{#TABLE_NAME}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='TABLE_ROWS']/text()`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Database [{#DBNAME}]: Table discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Percona: Table [{#DBNAME}/{#TABLE_NAME}]: Total size growth is too high|<p>Total table size growth is too high.</p>|`(last(/Percona by Zabbix agent active/percona.table.total_size["{#DBNAME}","{#TABLE_NAME}"])-min(/Percona by Zabbix agent active/percona.table.total_size["{#DBNAME}","{#TABLE_NAME}"],5m))>{$PERCONA.TOTALSIZE.GROWTH.MAX.CRIT}`|High||
|Percona: Table [{#DBNAME}/{#TABLE_NAME}]: Total size growth is high|<p>Total table size growth is high.</p>|`(last(/Percona by Zabbix agent active/percona.table.total_size["{#DBNAME}","{#TABLE_NAME}"])-min(/Percona by Zabbix agent active/percona.table.total_size["{#DBNAME}","{#TABLE_NAME}"],5m))>{$PERCONA.TOTALSIZE.GROWTH.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Percona: Table [{#DBNAME}/{#TABLE_NAME}]: Total size growth is too high</li></ul>|
|Percona: Table [{#DBNAME}/{#TABLE_NAME}]: Free space very high|<p>Free table space is very high (fragmentation critical).</p>|`last(/Percona by Zabbix agent active/percona.table.free_ratio["{#DBNAME}","{#TABLE_NAME}"]) > {$PERCONA.FRAGMENTATION.CRIT} and last(/Percona by Zabbix agent active/percona.table.data_free["{#DBNAME}","{#TABLE_NAME}"]) > {$PERCONA.DATA_FREE.MIN.CRIT} and last(/Percona by Zabbix agent active/percona.table.total_size["{#DBNAME}","{#TABLE_NAME}"]) > {$PERCONA.TABLE_SIZE.MIN.CRIT}`|High||
|Percona: Table [{#DBNAME}/{#TABLE_NAME}]: Free space high|<p>Free table space is high (fragmentation warning).</p>|`last(/Percona by Zabbix agent active/percona.table.free_ratio["{#DBNAME}","{#TABLE_NAME}"]) > {$PERCONA.FRAGMENTATION.WARN} and last(/Percona by Zabbix agent active/percona.table.data_free["{#DBNAME}","{#TABLE_NAME}"]) > {$PERCONA.DATA_FREE.MIN.WARN} and last(/Percona by Zabbix agent active/percona.table.total_size["{#DBNAME}","{#TABLE_NAME}"]) > {$PERCONA.TABLE_SIZE.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>Percona: Table [{#DBNAME}/{#TABLE_NAME}]: Free space very high</li></ul>|

### LLD rule Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication discovery|<p>Discovery of the replication.</p>|Zabbix agent (active)|percona.replication.discovery["{$PERCONA.HOST}","{$PERCONA.PORT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication: Replica status {#MASTER_HOST}|<p>Gets status information on the essential parameters of the replica threads.</p>|Zabbix agent (active)|percona.slave_status["{$PERCONA.HOST}","{$PERCONA.PORT}","{#SQL_STATEMENT}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Replication: Replica SQL running state {#MASTER_HOST}|<p>Shows the state of the SQL driver threads.</p>|Dependent item|percona.slave_sql_running_state["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Slave_SQL_Running_State']/text()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Replication: Seconds behind source {#MASTER_HOST}|<p>The number of seconds the replica SQL thread has been behind processing the source binary log.</p><p>A high number (or an increasing one) can indicate that the replica is unable to handle events</p><p>from the source in a timely fashion.</p>|Dependent item|percona.seconds_behind_master["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Seconds_Behind_Master']/text()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Matches regular expression: `\d+`</p><p>⛔️Custom on fail: Set error to: `Replication is not performed.`</p></li></ul>|
|Replication: Replica IO running {#MASTER_HOST}|<p>Indicates whether the I/O thread for reading the source's binary log is running.</p><p>Normally, you want this to be `Yes` unless you have not yet started a replication or have</p><p>explicitly stopped it with `STOP REPLICA`.</p>|Dependent item|percona.slave_io_running["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Slave_IO_Running']/text()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Replication: Replica SQL running {#MASTER_HOST}|<p>Indicates whether the SQL thread for executing events in the relay log is running.</p><p>As with the I/O thread, this should normally be `Yes`.</p>|Dependent item|percona.slave_sql_running["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Slave_SQL_Running']/text()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Replication: Last IO error {#MASTER_HOST}|<p>Describes the last I/O error message reported by the replica I/O thread that caused replication to stop or behave incorrectly.</p>|Dependent item|percona.slave_io_error["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Last_IO_Error']/text()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Replication: Last SQL error {#MASTER_HOST}|<p>Describes the last SQL error message reported by the replica SQL thread that caused replication to stop or behave incorrectly.</p>|Dependent item|percona.slave_sql_error["{#MASTER_HOST}"]<p>**Preprocessing**</p><ul><li><p>XML XPath: `/resultset/row/field[@name='Last_SQL_Error']/text()`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Replication discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Percona: Replication lag is too high|<p>Replication delay is too long.</p>|`min(/Percona by Zabbix agent active/percona.seconds_behind_master["{#MASTER_HOST}"],5m)>{$PERCONA.REPL_LAG.MAX.WARN}`|Warning||
|Percona: The replica I/O thread is not running|<p>Indicates whether the I/O thread for reading the source's binary log is running.</p>|`last(/Percona by Zabbix agent active/percona.slave_io_running["{#MASTER_HOST}"])=2`|Average||
|Percona: The replica I/O thread is not connected to a replication source|<p>Indicates whether the replica I/O thread is connected to the source.</p>|`last(/Percona by Zabbix agent active/percona.slave_io_running["{#MASTER_HOST}"])<>1`|Warning|**Depends on**:<br><ul><li>Percona: The replica I/O thread is not running</li></ul>|
|Percona: The SQL thread is not running|<p>Indicates whether the SQL thread for executing events in the relay log is running.</p>|`last(/Percona by Zabbix agent active/percona.slave_sql_running["{#MASTER_HOST}"])=2`|Warning|**Depends on**:<br><ul><li>Percona: The replica I/O thread is not running</li></ul>|

### LLD rule Thread pool metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Thread pool metric discovery|<p>The discovery of additional thread pool metrics when `thread_handling='pool-of-threads'`.</p><p>[Learn more](https://docs.percona.com/percona-server/8.4/threadpool.html).</p>|Dependent item|percona.threadpool.enable.discovery<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Thread pool metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Thread pool max threads|<p>The maximum number of threads in the pool.</p>|Dependent item|percona.thread_pool_max_threads[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Thread pool idle threads|<p>Number of idle threads in the pool.</p>|Dependent item|percona.threadpool_idle_threads[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Thread pool threads|<p>Number of threads in the pool.</p>|Dependent item|percona.threadpool_threads[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Thread pool stall limit|<p>Amount of time before a running thread is considered stalled.</p>|Dependent item|percona.thread_pool_stall_limit[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>XML XPath: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Thread pool utilization|<p>Percentage of the configured thread pool capacity currently in use.</p>|Calculated|percona.thread_pool_utilization[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Thread pool idle ratio|<p>Percentage of thread pool worker threads that are currently idle.</p>|Calculated|percona.thread_pool_idle_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Thread pool metric discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Percona: Thread pool max threads reached|<p>Thread pool has reached the configured max thread limit.</p>|`last(/Percona by Zabbix agent active/percona.threadpool_threads[{#SINGLETON}])>=last(/Percona by Zabbix agent active/percona.thread_pool_max_threads[{#SINGLETON}]) and last(/Percona by Zabbix agent active/percona.thread_pool_max_threads[{#SINGLETON}])>0`|High||
|Percona: Thread pool utilization is too high|<p>Thread pool utilization has been above `{$PERCONA.THREADPOOL.UTILIZATION.MAX.WARN}`% in the last 5 minutes.</p>|`min(/Percona by Zabbix agent active/percona.thread_pool_utilization[{#SINGLETON}],5m)>{$PERCONA.THREADPOOL.UTILIZATION.MAX.WARN} and last(/Percona by Zabbix agent active/percona.thread_pool_max_threads[{#SINGLETON}])>0`|Warning||
|Percona: Thread pool idle ratio is too low|<p>Thread pool idle ratio has been less than `{$PERCONA.THREADPOOL.IDLE_RATIO.MIN.WARN}`% in the last 5 minutes.</p>|`max(/Percona by Zabbix agent active/percona.thread_pool_idle_ratio[{#SINGLETON}],5m)<{$PERCONA.THREADPOOL.IDLE_RATIO.MIN.WARN} and last(/Percona by Zabbix agent active/percona.threadpool_threads[{#SINGLETON}])>0`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

