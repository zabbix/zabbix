
# PHP-FPM by Zabbix agent

## Overview

This template is developed to monitor the FastCGI Process Manager (PHP-FPM) by Zabbix agent that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template `PHP-FPM by Zabbix agent` - collects metrics by polling the PHP-FPM status-page locally with Zabbix agent.

Note that this template doesn't support HTTPS and redirects (limitations of `web.page.get`).

It also uses Zabbix agent to collect `php-fpm` Linux process statistics, such as CPU usage, memory usage, and whether the process is running or not.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- PHP 7
- PHP 8

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Note that depending on your OS distribution, the PHP-FPM executable/service name can vary. RHEL-like distributions usually name both process and service as `php-fpm`, while for Debian/Ubuntu based distributions it may include the version, for example: executable name - `php-fpm8.2`, systemd service name - `php8.2-fpm`. Adjust the following instructions accordingly if needed.

1. Open the PHP-FPM configuration file and enable the status page as shown.
  ```
  pm.status_path = /status
  ping.path = /ping
  ```

2. Validate the syntax to ensure it is correct before you reload the service. Replace the `<version>` in the command if needed.
  ```
  $ php-fpm -t
  ```
  or
  ```
  $ php-fpm<version> -t
  ```

3. Reload the `php-fpm` service to make the change active. Replace the `<version>` in the command if needed.
  ```
  $ systemctl reload php-fpm
  ```
  or
  ```
  $ systemctl reload php<version>-fpm
  ```

4. Next, edit the configuration of your web server.

If you use Nginx, edit the configuration file of your Nginx server block (virtual host) and add the location block below it.
  ```
  # Enable php-fpm status page
  location ~ ^/(status|ping)$ {
  ## disable access logging for request if you prefer
  access_log off;

  ## Only allow trusted IPs for security, deny everyone else
  # allow 127.0.0.1;
  # allow 1.2.3.4;    # your IP here
  # deny all;

  fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  fastcgi_index index.php;
  include fastcgi_params;
  ## Now the port or socket of the php-fpm pool we want the status of
  fastcgi_pass 127.0.0.1:9000;
  # fastcgi_pass unix:/run/php-fpm/your_socket.sock;
  }
  ```
If you use Apache, edit the configuration file of the virtual host and add the following location blocks.
  ```
  <LocationMatch "/status">
      Require ip 127.0.0.1
      # Require ip 1.2.3.4    # Your IP here
      # Adjust the path to the socket if needed
      ProxyPass "unix:/run/php-fpm/www.sock|fcgi://localhost/status"
  </LocationMatch>

  <LocationMatch "/ping">
      Require ip 127.0.0.1
      # Require ip 1.2.3.4    # Your IP here
      # Adjust the path to the socket if needed
      ProxyPass "unix:/run/php-fpm/www.sock|fcgi://localhost/ping"
  </LocationMatch>
  ```
5. Check the web server configuration syntax. The command may vary depending on the OS distribution and web server.
  ```
  $ nginx -t
  ```
  or
  ```
  $ httpd -t
  ```
  or
  ```
  $ apachectl configtest
  ```

6. Reload the web server configuration. The command may vary depending on the OS distribution and web server.
  ```
  $ systemctl reload nginx
  ```
  or
  ```
  $ systemctl reload httpd
  ```
  or
  ```
  $ systemctl reload apache2
  ```

7. Verify that the pages are available with these commands.
  ```
  curl -L 127.0.0.1/status
  curl -L 127.0.0.1/ping
  ```

Depending on your OS distribution, the PHP-FPM process name may vary as well. Please check the actual name in the line "Name" from /proc/\<pid\>/status file (https://www.zabbix.com/documentation/7.0/manual/appendix/items/proc_mem_num_notes) and change the {$PHP_FPM.PROCESS.NAME.PARAMETER} macro if needed.

If you use another location of the status/ping pages, don't forget to change the `{$PHP_FPM.STATUS.PAGE}/{$PHP_FPM.PING.PAGE}` macro.

If you use another web server port for the location of the PHP-FPM status/ping pages, don't forget to change the macro `{$PHP_FPM.PORT}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PHP_FPM.PORT}|<p>The port of the PHP-FPM status host or container.</p>|`80`|
|{$PHP_FPM.HOST}|<p>The hostname or IP address of the PHP-FPM status for a host or container.</p>|`localhost`|
|{$PHP_FPM.STATUS.PAGE}|<p>The path of the PHP-FPM status page.</p>|`status`|
|{$PHP_FPM.PING.PAGE}|<p>The path of the PHP-FPM ping page.</p>|`ping`|
|{$PHP_FPM.PING.REPLY}|<p>The expected reply to the ping.</p>|`pong`|
|{$PHP_FPM.QUEUE.WARN.MAX}|<p>The maximum percent of the PHP-FPM queue usage for a trigger expression.</p>|`80`|
|{$PHP_FPM.PROCESS_NAME}|<p>The process name filter for the PHP-FPM process discovery. May vary depending on your OS distribution.</p>|`php-fpm`|
|{$PHP_FPM.PROCESS.NAME.PARAMETER}|<p>The process name of the PHP-FPM used in the item key `proc.get`. It could be specified if the correct process name is known.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PHP-FPM: Get processes summary|<p>The aggregated data of summary metrics for all processes.</p>|Zabbix agent|proc.get[{$PHP_FPM.PROCESS.NAME.PARAMETER},,,summary]|
|PHP-FPM: php-fpm_ping||Zabbix agent|web.page.get["{$PHP_FPM.HOST}","{$PHP_FPM.PING.PAGE}","{$PHP_FPM.PORT}"]|
|PHP-FPM: Get status page||Zabbix agent|web.page.get["{$PHP_FPM.HOST}","{$PHP_FPM.STATUS.PAGE}?json","{$PHP_FPM.PORT}"]<p>**Preprocessing**</p><ul><li><p>Regular expression: `^[.\s\S]*({.+}) \1`</p></li></ul>|
|PHP-FPM: Ping||Dependent item|php-fpm.ping<p>**Preprocessing**</p><ul><li><p>Regular expression: `{$PHP_FPM.PING.REPLY}($|\r?\n) 1`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|PHP-FPM: Processes, active|<p>The total number of active processes.</p>|Dependent item|php-fpm.processes_active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['active processes']`</p></li></ul>|
|PHP-FPM: Version|<p>The current version of the PHP. You can get it from the HTTP-Header "X-Powered-By"; it may not work if you have changed the default HTTP-headers.</p>|Dependent item|php-fpm.version<p>**Preprocessing**</p><ul><li><p>Regular expression: `^[.\s\S]*X-Powered-By: PHP/([.\d]{1,}) \1`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|PHP-FPM: Pool name|<p>The name of the current pool.</p>|Dependent item|php-fpm.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pool`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|PHP-FPM: Uptime|<p>It indicates how long has this pool been running.</p>|Dependent item|php-fpm.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['start since']`</p></li></ul>|
|PHP-FPM: Start time|<p>The time when this pool was started.</p>|Dependent item|php-fpm.start_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['start time']`</p></li></ul>|
|PHP-FPM: Processes, total|<p>The total number of server processes currently running.</p>|Dependent item|php-fpm.processes_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['total processes']`</p></li></ul>|
|PHP-FPM: Processes, idle|<p>The total number of idle processes.</p>|Dependent item|php-fpm.processes_idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['idle processes']`</p></li></ul>|
|PHP-FPM: Queue usage|<p>The utilization of the queue.</p>|Calculated|php-fpm.listen_queue_usage|
|PHP-FPM: Process manager|<p>The method used by the process manager to control the number of child processes for this pool.</p>|Dependent item|php-fpm.process_manager<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['process manager']`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|PHP-FPM: Processes, max active|<p>The highest value of "active processes" since the PHP-FPM server was started.</p>|Dependent item|php-fpm.processes_max_active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['max active processes']`</p></li></ul>|
|PHP-FPM: Accepted connections per second|<p>The number of accepted requests per second.</p>|Dependent item|php-fpm.conn_accepted.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['accepted conn']`</p></li><li>Change per second</li></ul>|
|PHP-FPM: Slow requests|<p>The number of requests that has exceeded your `request_slowlog_timeout` value.</p>|Dependent item|php-fpm.slow_requests<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['slow requests']`</p></li><li>Simple change</li></ul>|
|PHP-FPM: Listen queue|<p>The current number of connections that have been initiated but not yet accepted.</p>|Dependent item|php-fpm.listen_queue<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['listen queue']`</p></li></ul>|
|PHP-FPM: Listen queue, max|<p>The maximum number of requests in the queue of pending connections since this FPM pool was started.</p>|Dependent item|php-fpm.listen_queue_max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['max listen queue']`</p></li></ul>|
|PHP-FPM: Listen queue, len|<p>The size of the socket queue of pending connections.</p>|Dependent item|php-fpm.listen_queue_len<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['listen queue len']`</p></li></ul>|
|PHP-FPM: Max children reached|<p>The number of times that `pm.max_children` has been reached since the PHP-FPM pool was started.</p>|Dependent item|php-fpm.max_children<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['max children reached']`</p></li><li>Simple change</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PHP-FPM: Version has changed|<p>The PHP-FPM version has changed. Acknowledge to close the problem manually.</p>|`last(/PHP-FPM by Zabbix agent/php-fpm.version,#1)<>last(/PHP-FPM by Zabbix agent/php-fpm.version,#2) and length(last(/PHP-FPM by Zabbix agent/php-fpm.version))>0`|Info|**Manual close**: Yes|
|PHP-FPM: Pool has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/PHP-FPM by Zabbix agent/php-fpm.uptime)<10m`|Info|**Manual close**: Yes|
|PHP-FPM: Queue utilization is high|<p>The queue for this pool has reached `{$PHP_FPM.QUEUE.WARN.MAX}%` of its maximum capacity. <br>Items in the queue represent the current number of connections that have been initiated on this pool but not yet accepted.</p>|`min(/PHP-FPM by Zabbix agent/php-fpm.listen_queue_usage,15m) > {$PHP_FPM.QUEUE.WARN.MAX}`|Warning||
|PHP-FPM: Manager changed|<p>The PHP-FPM manager has changed. Acknowledge to close the problem manually.</p>|`last(/PHP-FPM by Zabbix agent/php-fpm.process_manager,#1)<>last(/PHP-FPM by Zabbix agent/php-fpm.process_manager,#2)`|Info|**Manual close**: Yes|
|PHP-FPM: Detected slow requests|<p>The PHP-FPM has detected a slow request. <br>The slow request means that it took more time to execute than expected (defined in the configuration of your pool).</p>|`min(/PHP-FPM by Zabbix agent/php-fpm.slow_requests,#3)>0`|Warning||

### LLD rule PHP-FPM process discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PHP-FPM process discovery|<p>The discovery of the PHP-FPM summary processes.</p>|Dependent item|php-fpm.proc.discovery|

### Item prototypes for PHP-FPM process discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PHP-FPM: Get process data|<p>The summary metrics aggregated by a process `{#PHP_FPM.NAME}`.</p>|Dependent item|php-fpm.proc.get[{#PHP_FPM.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@["name"]=="{#PHP_FPM.NAME}")].first()`</p><p>⛔️Custom on fail: Set value to: `Failed to retrieve process {#PHP_FPM.NAME} data`</p></li></ul>|
|PHP-FPM: Memory usage (rss)|<p>The summary of resident set size memory used by a process `{#PHP_FPM.NAME}` expressed in bytes.</p>|Dependent item|php-fpm.proc.rss[{#PHP_FPM.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rss`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PHP-FPM: Memory usage (vsize)|<p>The summary of virtual memory used by a process `{#PHP_FPM.NAME}` expressed in bytes.</p>|Dependent item|php-fpm.proc.vmem[{#PHP_FPM.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.vsize`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PHP-FPM: Memory usage, %|<p>The percentage of real memory used by a process `{#PHP_FPM.NAME}`.</p>|Dependent item|php-fpm.proc.pmem[{#PHP_FPM.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pmem`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PHP-FPM: Number of running processes|<p>The number of running processes `{#PHP_FPM.NAME}`.</p>|Dependent item|php-fpm.proc.num[{#PHP_FPM.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.processes`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PHP-FPM: CPU utilization|<p>The percentage of the CPU utilization by a process `{#PHP_FPM.NAME}`.</p>|Zabbix agent|proc.cpu.util[{#PHP_FPM.NAME}]|

### Trigger prototypes for PHP-FPM process discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PHP-FPM: Process is not running||`last(/PHP-FPM by Zabbix agent/php-fpm.proc.num[{#PHP_FPM.NAME}])=0`|High||
|PHP-FPM: Failed to fetch info data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/PHP-FPM by Zabbix agent/php-fpm.uptime,30m)=1 and last(/PHP-FPM by Zabbix agent/php-fpm.proc.num[{#PHP_FPM.NAME}])>0`|Info|**Manual close**: Yes|
|PHP-FPM: Service is down||`(last(/PHP-FPM by Zabbix agent/php-fpm.ping)=0 or nodata(/PHP-FPM by Zabbix agent/php-fpm.ping,3m)=1) and last(/PHP-FPM by Zabbix agent/php-fpm.proc.num[{#PHP_FPM.NAME}])>0`|High|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

