
# Nginx by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  
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


This template was tested on:

- Nginx, version 1.17.2

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

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
Install and setup [Zabbix agent](https://www.zabbix.com/documentation/6.0/manual/installation/install_from_packages).


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NGINX.DROP_RATE.MAX.WARN} |<p>The critical rate of the dropped connections for trigger expression.</p> |`1` |
|{$NGINX.RESPONSE_TIME.MAX.WARN} |<p>The Nginx maximum response time in seconds for trigger expression.</p> |`10` |
|{$NGINX.STUB_STATUS.HOST} |<p>Hostname or IP of Nginx stub_status host or container.</p> |`localhost` |
|{$NGINX.STUB_STATUS.PATH} |<p>The path of Nginx stub_status page.</p> |`basic_status` |
|{$NGINX.STUB_STATUS.PORT} |<p>The port of Nginx stub_status host or container.</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Nginx |Nginx: Service status |<p>-</p> |ZABBIX_PASSIVE |net.tcp.service[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Nginx |Nginx: Service response time |<p>-</p> |ZABBIX_PASSIVE |net.tcp.service.perf[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"] |
|Nginx |Nginx: Requests total |<p>The total number of client requests.</p> |DEPENDENT |nginx.requests.total<p>**Preprocessing**:</p><p>- REGEX: `server accepts handled requests\s+([0-9]+) ([0-9]+) ([0-9]+) \3`</p> |
|Nginx |Nginx: Requests per second |<p>The total number of client requests.</p> |DEPENDENT |nginx.requests.total.rate<p>**Preprocessing**:</p><p>- REGEX: `server accepts handled requests\s+([0-9]+) ([0-9]+) ([0-9]+) \3`</p><p>- CHANGE_PER_SECOND</p> |
|Nginx |Nginx: Connections accepted per second |<p>The total number of accepted client connections.</p> |DEPENDENT |nginx.connections.accepted.rate<p>**Preprocessing**:</p><p>- REGEX: `server accepts handled requests\s+([0-9]+) ([0-9]+) ([0-9]+) \1`</p><p>- CHANGE_PER_SECOND</p> |
|Nginx |Nginx: Connections dropped per second |<p>The total number of dropped client connections.</p> |DEPENDENT |nginx.connections.dropped.rate<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Nginx |Nginx: Connections handled per second |<p>The total number of handled connections. Generally, the parameter value is the same as accepts unless some resource limits have been reached (for example, the worker_connections limit).</p> |DEPENDENT |nginx.connections.handled.rate<p>**Preprocessing**:</p><p>- REGEX: `server accepts handled requests\s+([0-9]+) ([0-9]+) ([0-9]+) \2`</p><p>- CHANGE_PER_SECOND</p> |
|Nginx |Nginx: Connections active |<p>The current number of active client connections including Waiting connections.</p> |DEPENDENT |nginx.connections.active<p>**Preprocessing**:</p><p>- REGEX: `Active connections: ([0-9]+) \1`</p> |
|Nginx |Nginx: Connections reading |<p>The current number of connections where nginx is reading the request header.</p> |DEPENDENT |nginx.connections.reading<p>**Preprocessing**:</p><p>- REGEX: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \1`</p> |
|Nginx |Nginx: Connections waiting |<p>The current number of idle client connections waiting for a request.</p> |DEPENDENT |nginx.connections.waiting<p>**Preprocessing**:</p><p>- REGEX: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \3`</p> |
|Nginx |Nginx: Connections writing |<p>The current number of connections where nginx is writing the response back to the client.</p> |DEPENDENT |nginx.connections.writing<p>**Preprocessing**:</p><p>- REGEX: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \2`</p> |
|Nginx |Nginx: Number of processes running |<p>Number of the Nginx processes running.</p> |ZABBIX_PASSIVE |proc.num[nginx] |
|Nginx |Nginx: Memory usage (vsize) |<p>Virtual memory size used by process in bytes.</p> |ZABBIX_PASSIVE |proc.mem[nginx,,,,vsize] |
|Nginx |Nginx: Memory usage (rss) |<p>Resident set size memory used by process in bytes.</p> |ZABBIX_PASSIVE |proc.mem[nginx,,,,rss] |
|Nginx |Nginx: CPU utilization |<p>Process CPU utilization percentage.</p> |ZABBIX_PASSIVE |proc.cpu.util[nginx] |
|Nginx |Nginx: Version |<p>-</p> |DEPENDENT |nginx.version<p>**Preprocessing**:</p><p>- REGEX: `Server: nginx\/(.+(?<!\r)) \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Zabbix raw items |Nginx: Get stub status page |<p>The following status information is provided:</p><p>Active connections - the current number of active client connections including Waiting connections.</p><p>Accepts - the total number of accepted client connections.</p><p>Handled - the total number of handled connections. Generally, the parameter value is the same as accepts unless some resource limits have been reached (for example, the worker_connections limit).</p><p>Requests - the total number of client requests.</p><p>Reading - the current number of connections where nginx is reading the request header.</p><p>Writing - the current number of connections where nginx is writing the response back to the client.</p><p>Waiting - the current number of idle client connections waiting for a request.</p><p>https://nginx.org/en/docs/http/ngx_http_stub_status_module.html</p> |ZABBIX_PASSIVE |web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Nginx: Service is down |<p>-</p> |`last(/Nginx by Zabbix agent/net.tcp.service[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"])=0` |AVERAGE |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Nginx: Process is not running</p> |
|Nginx: Service response time is too high |<p>-</p> |`min(/Nginx by Zabbix agent/net.tcp.service.perf[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"],5m)>{$NGINX.RESPONSE_TIME.MAX.WARN}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Nginx: Process is not running</p><p>- Nginx: Service is down</p> |
|Nginx: High connections drop rate |<p>The dropping rate connections is greater than {$NGINX.DROP_RATE.MAX.WARN} for the last 5 minutes.</p> |`min(/Nginx by Zabbix agent/nginx.connections.dropped.rate,5m) > {$NGINX.DROP_RATE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Nginx: Process is not running</p><p>- Nginx: Service is down</p> |
|Nginx: Process is not running |<p>-</p> |`last(/Nginx by Zabbix agent/proc.num[nginx])=0` |HIGH | |
|Nginx: Version has changed |<p>Nginx version has changed. Ack to close.</p> |`last(/Nginx by Zabbix agent/nginx.version,#1)<>last(/Nginx by Zabbix agent/nginx.version,#2) and length(last(/Nginx by Zabbix agent/nginx.version))>0` |INFO |<p>Manual close: YES</p> |
|Nginx: Failed to fetch stub status page |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`find(/Nginx by Zabbix agent/web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"],,"like","HTTP/1.1 200")=0 or  nodata(/Nginx by Zabbix agent/web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"],30m)=1 ` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Nginx: Process is not running</p><p>- Nginx: Service is down</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384765-discussion-thread-for-official-zabbix-template-nginx).

