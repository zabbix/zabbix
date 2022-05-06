
# PHP-FPM by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor PHP-FPM by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `PHP-FPM by Zabbix agent` collects metrics by polling PHP-FPM status-page locally with Zabbix agent:

Note that this template doesn't support https and redirects (limitations of web.page.get).

It also uses Zabbix agent to collect `php-fpm` Linux process stats like CPU usage, memory usage and whether process is running or not.




This template was tested on:

- PHP, version 7

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

1. Open the php-fpm configuration file and enable the status page as shown.
    ```
    pm.status_path = /status
    ping.path = /ping
    ```
2. Validate the syntax is fine before we reload the service
    ```
    $ php-fpm7 -t
    ```
3. Reload the php-fpm service to make the change active
    ```
    $ systemctl reload php-fpm
    ```
4. Next, edit your Nginx server block (virtual host) configuration file and add the location block below in it.
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
5. Check the syntax
  ```$ nginx -t```

6. Reload Nginx
  ```$ systemctl reload nginx```

7. Verify
  ```curl -L 127.0.0.1/status```

If you use another location of status/ping page, don't forget to change {$PHP_FPM.STATUS.PAGE}/{$PHP_FPM.PING.PAGE} macro.

If you use an atypical location for PHP-FPM status-page don't forget to change the macros {$PHP_FPM.PORT}.





## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PHP_FPM.HOST} |<p>Hostname or IP of PHP-FPM status host or container.</p> |`localhost` |
|{$PHP_FPM.PING.PAGE} |<p>The path of PHP-FPM ping page.</p> |`ping` |
|{$PHP_FPM.PING.REPLY} |<p>Expected reply to the ping.</p> |`pong` |
|{$PHP_FPM.PORT} |<p>The port of PHP-FPM status host or container.</p> |`80` |
|{$PHP_FPM.PROCESS_NAME} |<p>PHP-FPM process name</p> |`php-fpm` |
|{$PHP_FPM.QUEUE.WARN.MAX} |<p>The maximum PHP-FPM queue usage percent for trigger expression.</p> |`80` |
|{$PHP_FPM.STATUS.PAGE} |<p>The path of PHP-FPM status page.</p> |`status` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|PHP-FPM |PHP-FPM: Memory usage (rss) |<p>Resident set size memory used by process in bytes.</p> |ZABBIX_PASSIVE |proc.mem["{$PHP_FPM.PROCESS_NAME}",,,,rss] |
|PHP-FPM |PHP-FPM: Memory usage, % |<p>Memory used percentage relative to total memory available.</p> |ZABBIX_PASSIVE |proc.mem["{$PHP_FPM.PROCESS_NAME}",,,,pmem] |
|PHP-FPM |PHP-FPM: CPU utilization |<p>Process CPU utilization percentage.</p> |ZABBIX_PASSIVE |proc.cpu.util["{$PHP_FPM.PROCESS_NAME}"] |
|PHP-FPM |PHP-FPM: Number of processes running |<p>-</p> |ZABBIX_PASSIVE |proc.num["{$PHP_FPM.PROCESS_NAME}"] |
|PHP-FPM |PHP-FPM: Ping |<p>-</p> |DEPENDENT |php-fpm.ping<p>**Preprocessing**:</p><p>- REGEX: `{$PHP_FPM.PING.REPLY}($|\n) 1`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|PHP-FPM |PHP-FPM: Processes, active |<p>The total number of active processes.</p> |DEPENDENT |php-fpm.processes_active<p>**Preprocessing**:</p><p>- JSONPATH: `$.['active processes']`</p> |
|PHP-FPM |PHP-FPM: Version |<p>Current version PHP. Get from HTTP-Header "X-Powered-By" and may not work if you change default HTTP-headers.</p> |DEPENDENT |php-fpm.version<p>**Preprocessing**:</p><p>- REGEX: `^[.\s\S]*X-Powered-By: PHP/([.\d]{1,}) \1`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|PHP-FPM |PHP-FPM: Pool name |<p>The name of current pool.</p> |DEPENDENT |php-fpm.name<p>**Preprocessing**:</p><p>- JSONPATH: `$.pool`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|PHP-FPM |PHP-FPM: Uptime |<p>How long has this pool been running.</p> |DEPENDENT |php-fpm.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.['start since']`</p> |
|PHP-FPM |PHP-FPM: Start time |<p>The time when this pool was started.</p> |DEPENDENT |php-fpm.start_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.['start time']`</p> |
|PHP-FPM |PHP-FPM: Processes, total |<p>The total number of server processes currently running.</p> |DEPENDENT |php-fpm.processes_total<p>**Preprocessing**:</p><p>- JSONPATH: `$.['total processes']`</p> |
|PHP-FPM |PHP-FPM: Processes, idle |<p>The total number of idle processes.</p> |DEPENDENT |php-fpm.processes_idle<p>**Preprocessing**:</p><p>- JSONPATH: `$.['idle processes']`</p> |
|PHP-FPM |PHP-FPM: Queue usage |<p>Queue utilization</p> |CALCULATED |php-fpm.listen_queue_usage<p>**Expression**:</p>`last(//php-fpm.listen_queue)/(last(//php-fpm.listen_queue_len)+(last(//php-fpm.listen_queue_len)=0))*100` |
|PHP-FPM |PHP-FPM: Process manager |<p>The method used by the process manager to control the number of child processes for this pool.</p> |DEPENDENT |php-fpm.process_manager<p>**Preprocessing**:</p><p>- JSONPATH: `$.['process manager']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|PHP-FPM |PHP-FPM: Processes, max active |<p>The highest value that 'active processes' has reached since the php-fpm server started.</p> |DEPENDENT |php-fpm.processes_max_active<p>**Preprocessing**:</p><p>- JSONPATH: `$.['max active processes']`</p> |
|PHP-FPM |PHP-FPM: Accepted connections per second |<p>The number of accepted requests per second.</p> |DEPENDENT |php-fpm.conn_accepted.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.['accepted conn']`</p><p>- CHANGE_PER_SECOND</p> |
|PHP-FPM |PHP-FPM: Slow requests |<p>The number of requests that exceeded your request_slowlog_timeout value.</p> |DEPENDENT |php-fpm.slow_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.['slow requests']`</p><p>- SIMPLE_CHANGE</p> |
|PHP-FPM |PHP-FPM: Listen queue |<p>The current number of connections that have been initiated, but not yet accepted.</p> |DEPENDENT |php-fpm.listen_queue<p>**Preprocessing**:</p><p>- JSONPATH: `$.['listen queue']`</p> |
|PHP-FPM |PHP-FPM: Listen queue, max |<p>The maximum number of requests in the queue of pending connections since this FPM pool has started.</p> |DEPENDENT |php-fpm.listen_queue_max<p>**Preprocessing**:</p><p>- JSONPATH: `$.['max listen queue']`</p> |
|PHP-FPM |PHP-FPM: Listen queue, len |<p>Size of the socket queue of pending connections.</p> |DEPENDENT |php-fpm.listen_queue_len<p>**Preprocessing**:</p><p>- JSONPATH: `$.['listen queue len']`</p> |
|PHP-FPM |PHP-FPM: Max children reached |<p>The number of times that pm.max_children has been reached since the php-fpm pool started </p> |DEPENDENT |php-fpm.max_children<p>**Preprocessing**:</p><p>- JSONPATH: `$.['max children reached']`</p><p>- SIMPLE_CHANGE</p> |
|Zabbix raw items |PHP-FPM: php-fpm_ping |<p>-</p> |ZABBIX_PASSIVE |web.page.get["{$PHP_FPM.HOST}","{$PHP_FPM.PING.PAGE}","{$PHP_FPM.PORT}"] |
|Zabbix raw items |PHP-FPM: Get status page |<p>-</p> |ZABBIX_PASSIVE |web.page.get["{$PHP_FPM.HOST}","{$PHP_FPM.STATUS.PAGE}?json","{$PHP_FPM.PORT}"]<p>**Preprocessing**:</p><p>- REGEX: `^[.\s\S]*({.+}) \1`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|PHP-FPM: Process is not running |<p>-</p> |`last(/PHP-FPM by Zabbix agent/proc.num["{$PHP_FPM.PROCESS_NAME}"])=0` |HIGH | |
|PHP-FPM: Service is down |<p>-</p> |`last(/PHP-FPM by Zabbix agent/php-fpm.ping)=0 or nodata(/PHP-FPM by Zabbix agent/php-fpm.ping,3m)=1` |HIGH |<p>Manual close: YES</p><p>**Depends on**:</p><p>- PHP-FPM: Process is not running</p> |
|PHP-FPM: Version has changed |<p>PHP-FPM version has changed. Ack to close.</p> |`last(/PHP-FPM by Zabbix agent/php-fpm.version,#1)<>last(/PHP-FPM by Zabbix agent/php-fpm.version,#2) and length(last(/PHP-FPM by Zabbix agent/php-fpm.version))>0` |INFO |<p>Manual close: YES</p> |
|PHP-FPM: Failed to fetch info data |<p>Zabbix has not received data for items for the last 30 minutes</p> |`nodata(/PHP-FPM by Zabbix agent/php-fpm.uptime,30m)=1` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- PHP-FPM: Process is not running</p> |
|PHP-FPM: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/PHP-FPM by Zabbix agent/php-fpm.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|PHP-FPM: Queue utilization is high |<p>The queue for this pool reached {$PHP_FPM.QUEUE.WARN.MAX}% of its maximum capacity. Items in queue represent the current number of connections that have been initiated on this pool, but not yet accepted.</p> |`min(/PHP-FPM by Zabbix agent/php-fpm.listen_queue_usage,15m) > {$PHP_FPM.QUEUE.WARN.MAX}` |WARNING | |
|PHP-FPM: Manager  changed |<p>PHP-FPM manager changed. Ack to close.</p> |`last(/PHP-FPM by Zabbix agent/php-fpm.process_manager,#1)<>last(/PHP-FPM by Zabbix agent/php-fpm.process_manager,#2)` |INFO |<p>Manual close: YES</p> |
|PHP-FPM: Detected slow requests |<p>PHP-FPM detected slow request. A slow request means that it took more time to execute than expected (defined in the configuration of your pool).</p> |`min(/PHP-FPM by Zabbix agent/php-fpm.slow_requests,#3)>0` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

