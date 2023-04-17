
# Nginx by Zabbix agent

## Overview

The template to monitor Nginx by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Nginx by Zabbix agent` collects metrics by polling [ngx_http_stub_status_module](https://nginx.ru/en/docs/http/ngx_http_stub_status_module.html) locally with Zabbix agent:

```text
Active connections: 291
server accepts handled requests
16630948 16630948 31070465
Reading: 6 Writing: 179 Waiting: 106
```

Note that this template doesn't support https and redirects (limitations of web.page.get).

It also uses Zabbix agent to collect `nginx` Linux process stats like CPU usage, memory usage and whether process is running or not.


## Requirements

Zabbix version: 6.4 and higher.

## Tested versions

This template has been tested on:
- Nginx 1.17.2 

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box) section.

## Setup

Setup [ngx_http_stub_status_module](https://nginx.ru/en/docs/http/ngx_http_stub_status_module.html).
Test availability of http_stub_status module with `nginx -V 2>&1 | grep -o with-http_stub_status_module`.

Example configuration of Nginx:
```text
location = /basic_status {
    stub_status;
    allow 127.0.0.1;
    allow ::1;
    deny all;
}
```

If you use another location, then don't forget to change {$NGINX.STUB_STATUS.PATH} macro.

Example answer from Nginx:
```text
Active connections: 291
server accepts handled requests
16630948 16630948 31070465
Reading: 6 Writing: 179 Waiting: 106
```

Note that this template doesn't support https and redirects (limitations of web.page.get).

Install and setup [Zabbix agent](https://www.zabbix.com/documentation/6.4/manual/installation/install_from_packages).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NGINX.STUB_STATUS.HOST}|<p>Hostname or IP of Nginx stub_status host or container.</p>|`localhost`|
|{$NGINX.STUB_STATUS.PATH}|<p>The path of Nginx stub_status page.</p>|`basic_status`|
|{$NGINX.STUB_STATUS.PORT}|<p>The port of Nginx stub_status host or container.</p>|`80`|
|{$NGINX.RESPONSE_TIME.MAX.WARN}|<p>The Nginx maximum response time in seconds for trigger expression.</p>|`10`|
|{$NGINX.DROP_RATE.MAX.WARN}|<p>The critical rate of the dropped connections for trigger expression.</p>|`1`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nginx: Get stub status page|<p>The following status information is provided:</p><p>Active connections - the current number of active client connections including Waiting connections.</p><p>Accepts - the total number of accepted client connections.</p><p>Handled - the total number of handled connections. Generally, the parameter value is the same as accepts unless some resource limits have been reached (for example, the worker_connections limit).</p><p>Requests - the total number of client requests.</p><p>Reading - the current number of connections where nginx is reading the request header.</p><p>Writing - the current number of connections where nginx is writing the response back to the client.</p><p>Waiting - the current number of idle client connections waiting for a request.</p><p>https://nginx.org/en/docs/http/ngx_http_stub_status_module.html</p>|Zabbix agent|web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"]|
|Nginx: Service status| |Zabbix agent|net.tcp.service[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"]<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `10m`</li></ul>|
|Nginx: Service response time| |Zabbix agent|net.tcp.service.perf[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"]|
|Nginx: Requests total|<p>The total number of client requests.</p>|Dependent item|nginx.requests.total<p>**Preprocessing**</p><ul><li>Regular expression: `The text is too long. Please see the template.`</li></ul>|
|Nginx: Requests per second|<p>The total number of client requests.</p>|Dependent item|nginx.requests.total.rate<p>**Preprocessing**</p><ul><li>Regular expression: `The text is too long. Please see the template.`</li><li>Change per second</li></ul>|
|Nginx: Connections accepted per second|<p>The total number of accepted client connections.</p>|Dependent item|nginx.connections.accepted.rate<p>**Preprocessing**</p><ul><li>Regular expression: `The text is too long. Please see the template.`</li><li>Change per second</li></ul>|
|Nginx: Connections dropped per second|<p>The total number of dropped client connections.</p>|Dependent item|nginx.connections.dropped.rate<p>**Preprocessing**</p><ul><li>JavaScript: `The text is too long. Please see the template.`</li><li>Change per second</li></ul>|
|Nginx: Connections handled per second|<p>The total number of handled connections. Generally, the parameter value is the same as accepts unless some resource limits have been reached (for example, the worker_connections limit).</p>|Dependent item|nginx.connections.handled.rate<p>**Preprocessing**</p><ul><li>Regular expression: `The text is too long. Please see the template.`</li><li>Change per second</li></ul>|
|Nginx: Connections active|<p>The current number of active client connections including Waiting connections.</p>|Dependent item|nginx.connections.active<p>**Preprocessing**</p><ul><li>Regular expression: `Active connections: ([0-9]+) \1`</li></ul>|
|Nginx: Connections reading|<p>The current number of connections where nginx is reading the request header.</p>|Dependent item|nginx.connections.reading<p>**Preprocessing**</p><ul><li>Regular expression: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \1`</li></ul>|
|Nginx: Connections waiting|<p>The current number of idle client connections waiting for a request.</p>|Dependent item|nginx.connections.waiting<p>**Preprocessing**</p><ul><li>Regular expression: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \3`</li></ul>|
|Nginx: Connections writing|<p>The current number of connections where nginx is writing the response back to the client.</p>|Dependent item|nginx.connections.writing<p>**Preprocessing**</p><ul><li>Regular expression: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \2`</li></ul>|
|Nginx: Number of processes running|<p>Number of the Nginx processes running.</p>|Zabbix agent|proc.num[nginx]|
|Nginx: Memory usage (vsize)|<p>Virtual memory size used by process in bytes.</p>|Zabbix agent|proc.mem[nginx,,,,vsize]|
|Nginx: Memory usage (rss)|<p>Resident set size memory used by process in bytes.</p>|Zabbix agent|proc.mem[nginx,,,,rss]|
|Nginx: CPU utilization|<p>Process CPU utilization percentage.</p>|Zabbix agent|proc.cpu.util[nginx]|
|Nginx: Version| |Dependent item|nginx.version<p>**Preprocessing**</p><ul><li>Regular expression: `Server: nginx\/(.+(?<!\r)) \1`</li><li>Discard unchanged with heartbeat: `1d`</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nginx: Failed to fetch stub status page|<p>Zabbix has not received data for items for the last 30 minutes.</p>|`find(/Nginx by Zabbix agent/web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"],,"like","HTTP/1.1 200")=0 or nodata(/Nginx by Zabbix agent/web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"],30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Nginx: Process is not running</li><li>Nginx: Service is down</li></ul>|
|Nginx: Service is down||`last(/Nginx by Zabbix agent/net.tcp.service[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"])=0`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Nginx: Process is not running</li></ul>|
|Nginx: Service response time is too high||`min(/Nginx by Zabbix agent/net.tcp.service.perf[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"],5m)>{$NGINX.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Nginx: Process is not running</li><li>Nginx: Service is down</li></ul>|
|Nginx: High connections drop rate|<p>The dropping rate connections is greater than {$NGINX.DROP_RATE.MAX.WARN} for the last 5 minutes.</p>|`min(/Nginx by Zabbix agent/nginx.connections.dropped.rate,5m) > {$NGINX.DROP_RATE.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Nginx: Process is not running</li><li>Nginx: Service is down</li></ul>|
|Nginx: Process is not running||`last(/Nginx by Zabbix agent/proc.num[nginx])=0`|High||
|Nginx: Version has changed|<p>The Nginx version has changed. Acknowledge to close manually.</p>|`last(/Nginx by Zabbix agent/nginx.version,#1)<>last(/Nginx by Zabbix agent/nginx.version,#2) and length(last(/Nginx by Zabbix agent/nginx.version))>0`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at `https://support.zabbix.com`.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
