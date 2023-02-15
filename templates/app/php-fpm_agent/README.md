
# PHP-FPM by Zabbix agent

## Overview

For Zabbix version: 6.4 and higher.
This template is developed to monitor the FastCGI Process Manager (PHP-FPM) by Zabbix that works without any external scripts.

Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template `PHP-FPM by Zabbix agent` - collects metrics by polling the PHP-FPM status-page locally with Zabbix agent.

Note that this template doesn't support HTTPS and redirects (limitations of `web.page.get`).

It also uses Zabbix agent to collect `PHP-FPM` Linux process statistics, such as CPU usage, memory usage, and whether the process is running or not.


This template was tested on:

- PHP, version 7

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

1. Open the `php-fpm configuration file` and enable the status page as shown.

    ```
    pm.status_path = /status
    ping.path = /ping
    ```
2. Validate the syntax to ensure it is correct, before you reload the service.

    ```
    $ php-fpm7 -t
    ```
3. Reload the `php-fpm service` to make the change active.

    ```
    $ systemctl reload php-fpm
    ```
4. Next, edit the configuration file of your Nginx server block (virtual host) and add the location block below it.

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
5. Check the syntax again.

  ```$ nginx -t```

6. Reload Nginx server.

  ```$ systemctl reload nginx```

7. Verify it with this command line.

  ```curl -L 127.0.0.1/status```

If you use another location of the status/ping page, don't forget to change the `{$PHP_FPM.STATUS.PAGE}/{$PHP_FPM.PING.PAGE}` macro.

If you use an atypical location for the PHP-FPM status-page, don't forget to change the macro `{$PHP_FPM.PORT}`.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PHP_FPM.HOST} |<p>The Hostname or an IP address of the PHP-FPM status for a host or container.</p> |`localhost` |
|{$PHP_FPM.PING.PAGE} |<p>The path of the PHP-FPM ping page.</p> |`ping` |
|{$PHP_FPM.PING.REPLY} |<p>The expected reply to the ping.</p> |`pong` |
|{$PHP_FPM.PORT} |<p>The port of the PHP-FPM status host or container.</p> |`80` |
|{$PHP_FPM.PROCESS_NAME} |<p>The name of the PHP-FPM process.</p> |`php-fpm` |
|{$PHP_FPM.QUEUE.WARN.MAX} |<p>The maximum percent of the PHP-FPM queue usage for a trigger expression.</p> |`80` |
|{$PHP_FPM.STATUS.PAGE} |<p>The path of the PHP-FPM status page.</p> |`status` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|PHP-FPM process discovery |<p>The discovery of the PHP-FPM summary processes.</p> |DEPENDENT |php-fpm.proc.discovery<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$PHP_FPM.PROCESS_NAME}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|PHP-FPM |PHP-FPM: Get processes summary |<p>The aggregated data of summary metrics for all processes.</p> |ZABBIX_PASSIVE |proc.get[,,,summary] |
|PHP-FPM |PHP-FPM: Ping |<p>-</p> |DEPENDENT |php-fpm.ping<p>**Preprocessing**:</p><p>- REGEX: `{$PHP_FPM.PING.REPLY}($|\r?\n) 1`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|PHP-FPM |PHP-FPM: Processes, active |<p>The total number of active processes.</p> |DEPENDENT |php-fpm.processes_active<p>**Preprocessing**:</p><p>- JSONPATH: `$.['active processes']`</p> |
|PHP-FPM |PHP-FPM: Version |<p>The current version of the PHP. You can get it from the HTTP-Header "X-Powered-By"; it may not work if you have changed the default HTTP-headers.</p> |DEPENDENT |php-fpm.version<p>**Preprocessing**:</p><p>- REGEX: `^[.\s\S]*X-Powered-By: PHP/([.\d]{1,}) \1`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|PHP-FPM |PHP-FPM: Pool name |<p>The name of the current pool.</p> |DEPENDENT |php-fpm.name<p>**Preprocessing**:</p><p>- JSONPATH: `$.pool`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|PHP-FPM |PHP-FPM: Uptime |<p>It indicates how long has this pool been running.</p> |DEPENDENT |php-fpm.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.['start since']`</p> |
|PHP-FPM |PHP-FPM: Start time |<p>The time when this pool was started.</p> |DEPENDENT |php-fpm.start_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.['start time']`</p> |
|PHP-FPM |PHP-FPM: Processes, total |<p>The total number of server processes running currently.</p> |DEPENDENT |php-fpm.processes_total<p>**Preprocessing**:</p><p>- JSONPATH: `$.['total processes']`</p> |
|PHP-FPM |PHP-FPM: Processes, idle |<p>The total number of idle processes.</p> |DEPENDENT |php-fpm.processes_idle<p>**Preprocessing**:</p><p>- JSONPATH: `$.['idle processes']`</p> |
|PHP-FPM |PHP-FPM: Queue usage |<p>The utilization of the queue.</p> |CALCULATED |php-fpm.listen_queue_usage<p>**Expression**:</p>`last(//php-fpm.listen_queue)/(last(//php-fpm.listen_queue_len)+(last(//php-fpm.listen_queue_len)=0))*100` |
|PHP-FPM |PHP-FPM: Process manager |<p>The method used by the process manager to control the number of child processes for this pool.</p> |DEPENDENT |php-fpm.process_manager<p>**Preprocessing**:</p><p>- JSONPATH: `$.['process manager']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|PHP-FPM |PHP-FPM: Processes, max active |<p>The highest value of "active processes" since the PHP-FPM server was started.</p> |DEPENDENT |php-fpm.processes_max_active<p>**Preprocessing**:</p><p>- JSONPATH: `$.['max active processes']`</p> |
|PHP-FPM |PHP-FPM: Accepted connections per second |<p>The number of accepted requests per second.</p> |DEPENDENT |php-fpm.conn_accepted.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['accepted conn']`</p><p>- CHANGE_PER_SECOND</p> |
|PHP-FPM |PHP-FPM: Slow requests |<p>The number of requests that has exceeded your `request_slowlog_timeout` value.</p> |DEPENDENT |php-fpm.slow_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.['slow requests']`</p><p>- SIMPLE_CHANGE</p> |
|PHP-FPM |PHP-FPM: Listen queue |<p>The current number of connections that have been initiated but not yet accepted.</p> |DEPENDENT |php-fpm.listen_queue<p>**Preprocessing**:</p><p>- JSONPATH: `$.['listen queue']`</p> |
|PHP-FPM |PHP-FPM: Listen queue, max |<p>The maximum number of requests in the queue of pending connections since this FPM pool was started.</p> |DEPENDENT |php-fpm.listen_queue_max<p>**Preprocessing**:</p><p>- JSONPATH: `$.['max listen queue']`</p> |
|PHP-FPM |PHP-FPM: Listen queue, len |<p>The size of the socket queue of pending connections.</p> |DEPENDENT |php-fpm.listen_queue_len<p>**Preprocessing**:</p><p>- JSONPATH: `$.['listen queue len']`</p> |
|PHP-FPM |PHP-FPM: Max children reached |<p>The number of times that `pm.max_children` has been reached since the PHP-FPM pool was started.</p> |DEPENDENT |php-fpm.max_children<p>**Preprocessing**:</p><p>- JSONPATH: `$.['max children reached']`</p><p>- SIMPLE_CHANGE</p> |
|PHP-FPM |PHP-FPM: Get process data |<p>The summary metrics aggregated by a process `{#NAME}`.</p> |DEPENDENT |php-fpm.proc.get[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@["name"]=="{#NAME}")].first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> Failed to retrieve process {#NAME} data`</p> |
|PHP-FPM |PHP-FPM: Memory usage (rss) |<p>The summary of resident set size memory used by a process `{#NAME}` expressed in bytes.</p> |DEPENDENT |php-fpm.proc.rss[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.rss`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|PHP-FPM |PHP-FPM: Memory usage (vsize) |<p>The summary of virtual memory used by a process `{#NAME}` expressed in bytes.</p> |DEPENDENT |php-fpm.proc.vmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.vsize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|PHP-FPM |PHP-FPM: Memory usage, % |<p>The percentage of real memory used by a process `{#NAME}`.</p> |DEPENDENT |php-fpm.proc.pmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pmem`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|PHP-FPM |PHP-FPM: Number of running processes |<p>The number of running processes `{#NAME}`.</p> |DEPENDENT |php-fpm.proc.num[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.processes`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|PHP-FPM |PHP-FPM: CPU utilization |<p>The percentage of the CPU utilization by a process `{#NAME}`.</p> |ZABBIX_PASSIVE |proc.cpu.util[{#NAME}] |
|Zabbix raw items |PHP-FPM: php-fpm_ping |<p>-</p> |ZABBIX_PASSIVE |web.page.get["{$PHP_FPM.HOST}","{$PHP_FPM.PING.PAGE}","{$PHP_FPM.PORT}"] |
|Zabbix raw items |PHP-FPM: Get status page |<p>-</p> |ZABBIX_PASSIVE |web.page.get["{$PHP_FPM.HOST}","{$PHP_FPM.STATUS.PAGE}?json","{$PHP_FPM.PORT}"]<p>**Preprocessing**:</p><p>- REGEX: `^[.\s\S]*({.+}) \1`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|PHP-FPM: Version has changed |<p>The PHP-FPM version has changed. Acknowledge (Ack) to close manually.</p> |`last(/PHP-FPM by Zabbix agent/php-fpm.version,#1)<>last(/PHP-FPM by Zabbix agent/php-fpm.version,#2) and length(last(/PHP-FPM by Zabbix agent/php-fpm.version))>0` |INFO |<p>Manual close: YES</p> |
|PHP-FPM: Pool has been restarted |<p>Uptime is less than 10 minutes.</p> |`last(/PHP-FPM by Zabbix agent/php-fpm.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|PHP-FPM: Queue utilization is high |<p>The queue for this pool has reached `{$PHP_FPM.QUEUE.WARN.MAX}%` of its maximum capacity. Items in the queue represent the current number of connections that have been initiated on this pool but not yet accepted.</p> |`min(/PHP-FPM by Zabbix agent/php-fpm.listen_queue_usage,15m) > {$PHP_FPM.QUEUE.WARN.MAX}` |WARNING | |
|PHP-FPM: Manager  changed |<p>The PHP-FPM manager has changed. `Ack` to close manually.</p> |`last(/PHP-FPM by Zabbix agent/php-fpm.process_manager,#1)<>last(/PHP-FPM by Zabbix agent/php-fpm.process_manager,#2)` |INFO |<p>Manual close: YES</p> |
|PHP-FPM: Detected slow requests |<p>The PHP-FPM has detected a slow request. The slow request means that it took more time to execute than expected (defined in the configuration of your pool).</p> |`min(/PHP-FPM by Zabbix agent/php-fpm.slow_requests,#3)>0` |WARNING | |
|PHP-FPM: Process is not running |<p>-</p> |`last(/PHP-FPM by Zabbix agent/php-fpm.proc.num[{#NAME}])=0` |HIGH | |
|PHP-FPM: Failed to fetch info data |<p>Zabbix has not received any data for items for the last 30 minutes.</p> |`nodata(/PHP-FPM by Zabbix agent/php-fpm.uptime,30m)=1 and last(/PHP-FPM by Zabbix agent/php-fpm.proc.num[{#NAME}])>0` |INFO |<p>Manual close: YES</p> |
|PHP-FPM: Service is down |<p>-</p> |`(last(/PHP-FPM by Zabbix agent/php-fpm.ping)=0 or nodata(/PHP-FPM by Zabbix agent/php-fpm.ping,3m)=1) and last(/PHP-FPM by Zabbix agent/php-fpm.proc.num[{#NAME}])>0` |HIGH |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

