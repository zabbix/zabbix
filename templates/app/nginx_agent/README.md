
# Nginx by Zabbix agent

## Overview

This template is developed to monitor Nginx by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template `Nginx by Zabbix agent` - collects metrics by polling the [Module ngx_http_stub_status_module](https://nginx.ru/en/docs/http/ngx_http_stub_status_module.html) locally with Zabbix agent:

```text
Active connections: 291
server accepts handled requests
16630948 16630948 31070465
Reading: 6 Writing: 179 Waiting: 106
```

Note that this template doesn't support HTTPS and redirects (limitations of `web.page.get`).

It also uses Zabbix agent to collect `Nginx` Linux process statistics, such as CPU usage, memory usage and whether the process is running or not.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Nginx 1.17.2 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

See the setup instructions for [ngx_http_stub_status_module](https://nginx.ru/en/docs/http/ngx_http_stub_status_module.html).
Test the availability of the `http_stub_status_module` `nginx -V 2>&1 | grep -o with-http_stub_status_module`.

Example configuration of Nginx:
```text
location = /basic_status {
    stub_status;
    allow 127.0.0.1;
    allow ::1;
    deny all;
}
```

If you use another location, then don't forget to change the {$NGINX.STUB_STATUS.PATH} macro.

Example answer from Nginx:
```text
Active connections: 291
server accepts handled requests
16630948 16630948 31070465
Reading: 6 Writing: 179 Waiting: 106
```

Note that this template doesn't support https and redirects (limitations of web.page.get).

Install and setup [Zabbix agent](https://www.zabbix.com/documentation/7.0/manual/installation/install_from_packages).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NGINX.STUB_STATUS.HOST}|<p>The hostname or IP address of the Nginx host or Nginx container of `astub_status`.</p>|`localhost`|
|{$NGINX.STUB_STATUS.PATH}|<p>The path of the `Nginx stub_status` page.</p>|`basic_status`|
|{$NGINX.STUB_STATUS.PORT}|<p>The port of the `Nginx stub_status` host or container.</p>|`80`|
|{$NGINX.RESPONSE_TIME.MAX.WARN}|<p>The maximum response time of Nginx expressed in seconds for a trigger expression.</p>|`10`|
|{$NGINX.DROP_RATE.MAX.WARN}|<p>The critical rate of the dropped connections for a trigger expression.</p>|`1`|
|{$NGINX.PROCESS_NAME}|<p>The process name filter for the Nginx process discovery.</p>|`nginx`|
|{$NGINX.PROCESS.NAME.PARAMETER}|<p>The process name of the Nginx server used in the item key `proc.get`. It could be specified if the correct process name is known.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nginx: Get stub status page|<p>The following status information is provided:</p><p>`Active connections` - the current number of active client connections including waiting connections.</p><p>`Accepted` - the total number of accepted client connections.</p><p>`Handled` - the total number of handled connections. Generally, the parameter value is the same as for the accepted connections, unless some resource limits have been reached (for example, the `worker_connections` limit).</p><p>`Requests` - the total number of client requests.</p><p>`Reading` - the current number of connections where Nginx is reading the request header.</p><p>`Writing` - the current number of connections where Nginx is writing a response back to the client.</p><p>`Waiting` - the current number of idle client connections waiting for a request.</p><p></p><p>See also [Module ngx_http_stub_status_module](https://nginx.org/en/docs/http/ngx_http_stub_status_module.html).</p>|Zabbix agent|web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"]|
|Nginx: Service status||Zabbix agent|net.tcp.service[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Nginx: Service response time||Zabbix agent|net.tcp.service.perf[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"]|
|Nginx: Requests total|<p>The total number of client requests.</p>|Dependent item|nginx.requests.total<p>**Preprocessing**</p><ul><li><p>Regular expression: `The text is too long. Please see the template.`</p></li></ul>|
|Nginx: Requests per second|<p>The total number of client requests.</p>|Dependent item|nginx.requests.total.rate<p>**Preprocessing**</p><ul><li><p>Regular expression: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Nginx: Connections accepted per second|<p>The total number of accepted client connections.</p>|Dependent item|nginx.connections.accepted.rate<p>**Preprocessing**</p><ul><li><p>Regular expression: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Nginx: Connections dropped per second|<p>The total number of dropped client connections.</p>|Dependent item|nginx.connections.dropped.rate<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Nginx: Connections handled per second|<p>The total number of handled connections. Generally, the parameter value is the same as for the accepted connections, unless some resource limits have been reached (for example, the `worker_connections limit`).</p>|Dependent item|nginx.connections.handled.rate<p>**Preprocessing**</p><ul><li><p>Regular expression: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Nginx: Connections active|<p>The current number of active client connections including waiting connections.</p>|Dependent item|nginx.connections.active<p>**Preprocessing**</p><ul><li><p>Regular expression: `Active connections: ([0-9]+) \1`</p></li></ul>|
|Nginx: Connections reading|<p>The current number of connections where Nginx is reading the request header.</p>|Dependent item|nginx.connections.reading<p>**Preprocessing**</p><ul><li><p>Regular expression: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \1`</p></li></ul>|
|Nginx: Connections waiting|<p>The current number of idle client connections waiting for a request.</p>|Dependent item|nginx.connections.waiting<p>**Preprocessing**</p><ul><li><p>Regular expression: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \3`</p></li></ul>|
|Nginx: Connections writing|<p>The current number of connections where Nginx is writing a response back to the client.</p>|Dependent item|nginx.connections.writing<p>**Preprocessing**</p><ul><li><p>Regular expression: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \2`</p></li></ul>|
|Nginx: Version||Dependent item|nginx.version<p>**Preprocessing**</p><ul><li><p>Regular expression: `Server: nginx\/(.+(?<!\r)) \1`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Nginx: Get processes summary|<p>The aggregated data of summary metrics for all processes.</p>|Zabbix agent|proc.get[{$NGINX.PROCESS.NAME.PARAMETER},,,summary]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nginx: Version has changed|<p>The Nginx version has changed. Acknowledge to close the problem manually.</p>|`last(/Nginx by Zabbix agent/nginx.version,#1)<>last(/Nginx by Zabbix agent/nginx.version,#2) and length(last(/Nginx by Zabbix agent/nginx.version))>0`|Info|**Manual close**: Yes|

### LLD rule Nginx process discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nginx process discovery|<p>The discovery of Nginx process summary.</p>|Dependent item|nginx.proc.discovery|

### Item prototypes for Nginx process discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nginx: CPU utilization|<p>The percentage of the CPU utilization by a process {#NGINX.NAME}.</p>|Zabbix agent|proc.cpu.util[{#NGINX.NAME}]|
|Nginx: Get process data|<p>The summary metrics aggregated by a process {#NGINX.NAME}.</p>|Dependent item|nginx.proc.get[{#NGINX.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@["name"]=="{#NGINX.NAME}")].first()`</p><p>⛔️Custom on fail: Set value to: `Failed to retrieve process {#NGINX.NAME} data`</p></li></ul>|
|Nginx: Memory usage (vsize)|<p>The summary of virtual memory used by a process {#NGINX.NAME} expressed in bytes.</p>|Dependent item|nginx.proc.vmem[{#NGINX.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.vsize`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Nginx: Memory usage (rss)|<p>The summary of resident set size memory used by a process {#NGINX.NAME} expressed in bytes.</p>|Dependent item|nginx.proc.rss[{#NGINX.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rss`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Nginx: Memory usage, %|<p>The percentage of real memory used by a process {#NGINX.NAME}.</p>|Dependent item|nginx.proc.pmem[{#NGINX.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pmem`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Nginx: Number of running processes|<p>The number of running processes {#NGINX.NAME}.</p>|Dependent item|nginx.proc.num[{#NGINX.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.processes`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Nginx process discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nginx: Process is not running||`last(/Nginx by Zabbix agent/nginx.proc.num[{#NGINX.NAME}])=0`|High||
|Nginx: Service is down||`last(/Nginx by Zabbix agent/net.tcp.service[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"])=0 and last(/Nginx by Zabbix agent/nginx.proc.num[{#NGINX.NAME}])>0`|Average|**Manual close**: Yes|
|Nginx: High connections drop rate|<p>The rate of dropping connections has been greater than {$NGINX.DROP_RATE.MAX.WARN} for the last 5 minutes.</p>|`min(/Nginx by Zabbix agent/nginx.connections.dropped.rate,5m) > {$NGINX.DROP_RATE.MAX.WARN} and last(/Nginx by Zabbix agent/nginx.proc.num[{#NGINX.NAME}])>0`|Warning|**Depends on**:<br><ul><li>Nginx: Service is down</li></ul>|
|Nginx: Service response time is too high||`min(/Nginx by Zabbix agent/net.tcp.service.perf[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"],5m)>{$NGINX.RESPONSE_TIME.MAX.WARN} and last(/Nginx by Zabbix agent/nginx.proc.num[{#NGINX.NAME}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Nginx: Service is down</li></ul>|
|Nginx: Failed to fetch stub status page|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`(find(/Nginx by Zabbix agent/web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"],,"like","HTTP/1.1 200")=0 or nodata(/Nginx by Zabbix agent/web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"],30m)) and last(/Nginx by Zabbix agent/nginx.proc.num[{#NGINX.NAME}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Nginx: Service is down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

