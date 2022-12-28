
# Nginx by Zabbix agent

## Overview

For Zabbix version: 6.2 and higher.
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


This template was tested on:

- Nginx, version 1.17.2

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

See the setup instructions for [ngx_http_stub_status_module](https://nginx.ru/en/docs/http/ngx_http_stub_status_module.html).
Test the availability of the `http_stub_status_module` with `nginx -V 2>&1 | grep -o with-http_stub_status_module`.

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
Install and setup [Zabbix agent](https://www.zabbix.com/documentation/6.2/manual/installation/install_from_packages).


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NGINX.DROP_RATE.MAX.WARN} |<p>The critical rate of the dropped connections for a trigger expression.</p> |`1` |
|{$NGINX.PROCESS_NAME} |<p>The process name of the Nginx server.</p> |`nginx` |
|{$NGINX.RESPONSE_TIME.MAX.WARN} |<p>The maximum response time of Nginx expressed in seconds for a trigger expression.</p> |`10` |
|{$NGINX.STUB_STATUS.HOST} |<p>The Hostname or an IP addess of the Nginx host or Nginx container of `astub_status`.</p> |`localhost` |
|{$NGINX.STUB_STATUS.PATH} |<p>The path of the `Nginx stub_status` page.</p> |`basic_status` |
|{$NGINX.STUB_STATUS.PORT} |<p>The port of the `Nginx stub_status` host or container.</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Nginx process discovery |<p>The discovery of Nginx process summary.</p> |DEPENDENT |nginx.proc.discovery<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$NGINX.PROCESS_NAME}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Nginx |Nginx: Service status |<p>-</p> |ZABBIX_PASSIVE |net.tcp.service[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Nginx |Nginx: Service response time |<p>-</p> |ZABBIX_PASSIVE |net.tcp.service.perf[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"] |
|Nginx |Nginx: Requests total |<p>The total number of client requests.</p> |DEPENDENT |nginx.requests.total<p>**Preprocessing**:</p><p>- REGEX: `server accepts handled requests\s+([0-9]+) ([0-9]+) ([0-9]+) \3`</p> |
|Nginx |Nginx: Requests per second |<p>The total number of client requests.</p> |DEPENDENT |nginx.requests.total.rate<p>**Preprocessing**:</p><p>- REGEX: `server accepts handled requests\s+([0-9]+) ([0-9]+) ([0-9]+) \3`</p><p>- CHANGE_PER_SECOND</p> |
|Nginx |Nginx: Connections accepted per second |<p>The total number of accepted client connections.</p> |DEPENDENT |nginx.connections.accepted.rate<p>**Preprocessing**:</p><p>- REGEX: `server accepts handled requests\s+([0-9]+) ([0-9]+) ([0-9]+) \1`</p><p>- CHANGE_PER_SECOND</p> |
|Nginx |Nginx: Connections dropped per second |<p>The total number of dropped client connections.</p> |DEPENDENT |nginx.connections.dropped.rate<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Nginx |Nginx: Connections handled per second |<p>The total number of handled connections. Generally, the parameter value is the same as for the accepted connections, unless some resource limits have been reached (for example, the `worker_connections limit`).</p> |DEPENDENT |nginx.connections.handled.rate<p>**Preprocessing**:</p><p>- REGEX: `server accepts handled requests\s+([0-9]+) ([0-9]+) ([0-9]+) \2`</p><p>- CHANGE_PER_SECOND</p> |
|Nginx |Nginx: Connections active |<p>The current number of active client connections including waiting connections.</p> |DEPENDENT |nginx.connections.active<p>**Preprocessing**:</p><p>- REGEX: `Active connections: ([0-9]+) \1`</p> |
|Nginx |Nginx: Connections reading |<p>The current number of connections where Nginx is reading the request header.</p> |DEPENDENT |nginx.connections.reading<p>**Preprocessing**:</p><p>- REGEX: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \1`</p> |
|Nginx |Nginx: Connections waiting |<p>The current number of idle client connections waiting for a request.</p> |DEPENDENT |nginx.connections.waiting<p>**Preprocessing**:</p><p>- REGEX: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \3`</p> |
|Nginx |Nginx: Connections writing |<p>The current number of connections where Nginx is writing a response back to the client.</p> |DEPENDENT |nginx.connections.writing<p>**Preprocessing**:</p><p>- REGEX: `Reading: ([0-9]+) Writing: ([0-9]+) Waiting: ([0-9]+) \2`</p> |
|Nginx |Nginx: Get processes summary |<p>The aggregated data of summary metrics for all processes.</p> |ZABBIX_PASSIVE |proc.get[,,,summary] |
|Nginx |Nginx: Version |<p>-</p> |DEPENDENT |nginx.version<p>**Preprocessing**:</p><p>- REGEX: `Server: nginx\/(.+(?<!\r)) \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Nginx |Nginx: CPU utilization |<p>The percentage of the CPU utilization by a process {#NAME}.</p> |ZABBIX_PASSIVE |proc.cpu.util[{#NAME}] |
|Nginx |Nginx: Get process data |<p>The summary metrics aggregated by a process {#NAME}.</p> |DEPENDENT |nginx.proc.get[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@["name"]=="{#NAME}")].first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> Failed to retrieve process {#NAME} data`</p> |
|Nginx |Nginx: Memory usage (vsize) |<p>The summary of virtual memory used by a process {#NAME} expressed in bytes.</p> |DEPENDENT |nginx.proc.vmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.vsize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Nginx |Nginx: Memory usage (rss) |<p>The summary of resident set size memory used by a process {#NAME} expressed in bytes.</p> |DEPENDENT |nginx.proc.rss[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.rss`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Nginx |Nginx: Memory usage, % |<p>The percentage of real memory used by a process {#NAME}.</p> |DEPENDENT |nginx.proc.pmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pmem`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Nginx |Nginx: Number of running processes |<p>The number of running processes {#NAME}.</p> |DEPENDENT |nginx.proc.num[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.processes`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |Nginx: Get stub status page |<p>The following status information is provided:</p><p> `Active connections` - the current number of active client connections including waiting connections.</p><p> `Accepted` - the total number of accepted client connections.</p><p> `Handled` - the total number of handled connections. Generally, the parameter value is the same as for the accepted connections, unless some resource limits have been reached (for example, the `worker_connections` limit).</p><p> `Requests` - the total number of client requests.</p><p> `Reading` - the current number of connections where Nginx is reading the request header.</p><p> `Writing` - the current number of connections where Nginx is writing a response back to the client.</p><p> `Waiting` - the current number of idle client connections waiting for a request.</p><p> See also [Module ngx_http_stub_status_module](https://nginx.org/en/docs/http/ngx_http_stub_status_module.html).</p> |ZABBIX_PASSIVE |web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Nginx: Version has changed |<p>The Nginx version has changed. Acknowledge (Ack) to close manually.</p> |`last(/Nginx by Zabbix agent/nginx.version,#1)<>last(/Nginx by Zabbix agent/nginx.version,#2) and length(last(/Nginx by Zabbix agent/nginx.version))>0` |INFO |<p>Manual close: YES</p> |
|Nginx: Process is not running |<p>-</p> |`last(/Nginx by Zabbix agent/nginx.proc.num[{#NAME}])=0` |HIGH | |
|Nginx: Service is down |<p>-</p> |`last(/Nginx by Zabbix agent/net.tcp.service[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"])=0 and last(/Nginx by Zabbix agent/nginx.proc.num[{#NAME}])>0` |AVERAGE |<p>Manual close: YES</p> |
|Nginx: High connections drop rate |<p>The rate of dropping connections has been greater than {$NGINX.DROP_RATE.MAX.WARN} for the last 5 minutes.</p> |`min(/Nginx by Zabbix agent/nginx.connections.dropped.rate,5m) > {$NGINX.DROP_RATE.MAX.WARN} and last(/Nginx by Zabbix agent/nginx.proc.num[{#NAME}])>0` |WARNING |<p>**Depends on**:</p><p>- Nginx: Service is down</p> |
|Nginx: Service response time is too high |<p>-</p> |`min(/Nginx by Zabbix agent/net.tcp.service.perf[http,"{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PORT}"],5m)>{$NGINX.RESPONSE_TIME.MAX.WARN} and last(/Nginx by Zabbix agent/nginx.proc.num[{#NAME}])>0` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Nginx: Service is down</p> |
|Nginx: Failed to fetch stub status page |<p>Zabbix has not received any data for items for the last 30 minutes.</p> |`(find(/Nginx by Zabbix agent/web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"],,"like","HTTP/1.1 200")=0 or nodata(/Nginx by Zabbix agent/web.page.get["{$NGINX.STUB_STATUS.HOST}","{$NGINX.STUB_STATUS.PATH}","{$NGINX.STUB_STATUS.PORT}"],30m)) and last(/Nginx by Zabbix agent/nginx.proc.num[{#NAME}])>0               ` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Nginx: Service is down</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384765-discussion-thread-for-official-zabbix-template-nginx).

