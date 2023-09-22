
# Apache by HTTP

## Overview

This template is designed for the effortless deployment of Apache monitoring by Zabbix via HTTP and doesn't require any external scripts.
The template `Apache by HTTP` - collects metrics by polling [mod_status](https://httpd.apache.org/docs/current/mod/mod_status.html) with HTTP agent remotely:  

```text
127.0.0.1
ServerVersion: Apache/2.4.41 (Unix)
ServerMPM: event
Server Built: Aug 14 2019 00:35:10
CurrentTime: Friday, 16-Aug-2019 12:38:40 UTC
RestartTime: Wednesday, 14-Aug-2019 07:58:26 UTC
ParentServerConfigGeneration: 1
ParentServerMPMGeneration: 0
ServerUptimeSeconds: 189613
ServerUptime: 2 days 4 hours 40 minutes 13 seconds
Load1: 4.60
Load5: 1.20
Load15: 0.47
Total Accesses: 27860
Total kBytes: 33011
Total Duration: 54118
CPUUser: 18.02
CPUSystem: 31.76
CPUChildrenUser: 0
CPUChildrenSystem: 0
CPULoad: .0262535
Uptime: 189613
ReqPerSec: .146931
BytesPerSec: 178.275
BytesPerReq: 1213.33
DurationPerReq: 1.9425
BusyWorkers: 7
IdleWorkers: 93
Processes: 4
Stopping: 0
BusyWorkers: 7
IdleWorkers: 93
ConnsTotal: 13
ConnsAsyncWriting: 0
ConnsAsyncKeepAlive: 5
ConnsAsyncClosing: 0
Scoreboard: ...

```

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Apache 2.4.41

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

See the setup instructions for [mod_status](https://httpd.apache.org/docs/current/mod/mod_status.html).

Check the availability of the module with this command line: `httpd -M 2>/dev/null | grep status_module`

This is an example configuration of the Apache web server:

```text
<Location "/server-status">
  SetHandler server-status
  Require host example.com
</Location>
```

If you use another path, then do not forget to change the `{$APACHE.STATUS.PATH}` macro.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$APACHE.STATUS.PORT}|<p>The port of the Apache status page.</p>|`80`|
|{$APACHE.STATUS.PATH}|<p>The URL path.</p>|`server-status?auto`|
|{$APACHE.STATUS.SCHEME}|<p>The request scheme, which may be either HTTP or HTTPS.</p>|`http`|
|{$APACHE.RESPONSE_TIME.MAX.WARN}|<p>The maximum Apache response time expressed in seconds for a trigger expression.</p>|`10`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Apache: Get status|<p>Getting data from a machine-readable version of the Apache status page.</p><p>For more information see Apache Module [mod_status](https://httpd.apache.org/docs/current/mod/mod_status.html).</p>|HTTP agent|apache.get_status<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Apache: Service ping||Simple check|net.tcp.service[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Apache: Service response time||Simple check|net.tcp.service.perf[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"]|
|Apache: Total bytes|<p>The total bytes served.</p>|Dependent item|apache.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["Total kBytes"]`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Apache: Bytes per second|<p>It is calculated as a rate of change for total bytes statistics.</p><p>`BytesPerSec` is not used, as it counts the average since the last Apache server start.</p>|Dependent item|apache.bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["Total kBytes"]`</p></li><li><p>Custom multiplier: `1024`</p></li><li>Change per second</li></ul>|
|Apache: Requests per second|<p>It is calculated as a rate of change for the "Total requests" statistics.</p><p>`ReqPerSec` is not used, as it counts the average since the last Apache server start.</p>|Dependent item|apache.requests.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["Total Accesses"]`</p></li><li>Change per second</li></ul>|
|Apache: Total requests|<p>The total number of the Apache server accesses.</p>|Dependent item|apache.requests<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["Total Accesses"]`</p></li></ul>|
|Apache: Uptime|<p>The service uptime expressed in seconds.</p>|Dependent item|apache.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ServerUptimeSeconds`</p></li></ul>|
|Apache: Version|<p>The Apache service version.</p>|Dependent item|apache.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ServerVersion`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Apache: Total workers busy|<p>The total number of busy worker threads/processes.</p>|Dependent item|apache.workers_total.busy<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.BusyWorkers`</p></li></ul>|
|Apache: Total workers idle|<p>The total number of idle worker threads/processes.</p>|Dependent item|apache.workers_total.idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.IdleWorkers`</p></li></ul>|
|Apache: Workers closing connection|<p>The number of workers in closing state.</p>|Dependent item|apache.workers.closing<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.closing`</p></li></ul>|
|Apache: Workers DNS lookup|<p>The number of workers in `dnslookup` state.</p>|Dependent item|apache.workers.dnslookup<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.dnslookup`</p></li></ul>|
|Apache: Workers finishing|<p>The number of workers in finishing state.</p>|Dependent item|apache.workers.finishing<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.finishing`</p></li></ul>|
|Apache: Workers idle cleanup|<p>The number of workers in cleanup state.</p>|Dependent item|apache.workers.cleanup<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.cleanup`</p></li></ul>|
|Apache: Workers keepalive (read)|<p>The number of workers in `keepalive` state.</p>|Dependent item|apache.workers.keepalive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.keepalive`</p></li></ul>|
|Apache: Workers logging|<p>The number of workers in logging state.</p>|Dependent item|apache.workers.logging<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.logging`</p></li></ul>|
|Apache: Workers reading request|<p>The number of workers in reading state.</p>|Dependent item|apache.workers.reading<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.reading`</p></li></ul>|
|Apache: Workers sending reply|<p>The number of workers in sending state.</p>|Dependent item|apache.workers.sending<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.sending`</p></li></ul>|
|Apache: Workers slot with no current process|<p>The number of slots with no current process.</p>|Dependent item|apache.workers.slot<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.slot`</p></li></ul>|
|Apache: Workers starting up|<p>The number of workers in starting state.</p>|Dependent item|apache.workers.starting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.starting`</p></li></ul>|
|Apache: Workers waiting for connection|<p>The number of workers in waiting state.</p>|Dependent item|apache.workers.waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Workers.waiting`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Apache: Failed to fetch status page|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Apache by HTTP/apache.get_status,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Apache: Service is down</li></ul>|
|Apache: Service is down||`last(/Apache by HTTP/net.tcp.service[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"])=0`|Average|**Manual close**: Yes|
|Apache: Service response time is too high||`min(/Apache by HTTP/net.tcp.service.perf[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"],5m)>{$APACHE.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Apache: Service is down</li></ul>|
|Apache: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Apache by HTTP/apache.uptime)<10m`|Info|**Manual close**: Yes|
|Apache: Version has changed|<p>Apache version has changed. Acknowledge to close the problem manually.</p>|`last(/Apache by HTTP/apache.version,#1)<>last(/Apache by HTTP/apache.version,#2) and length(last(/Apache by HTTP/apache.version))>0`|Info|**Manual close**: Yes|

### LLD rule Event MPM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Event MPM discovery|<p>The discovery of additional metrics if the event Multi-Processing Module (MPM) is used.</p><p>For more details see [Apache MPM event](https://httpd.apache.org/docs/current/mod/event.html).</p>|Dependent item|apache.mpm.event.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Event MPM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Apache: Connections async closing|<p>The number of asynchronous connections in closing state (applicable only to the event MPM).</p>|Dependent item|apache.connections[async_closing{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ConnsAsyncClosing`</p></li></ul>|
|Apache: Connections async keepalive|<p>The number of asynchronous connections in keepalive state (applicable only to the event MPM).</p>|Dependent item|apache.connections[async_keep_alive{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ConnsAsyncKeepAlive`</p></li></ul>|
|Apache: Connections async writing|<p>The number of asynchronous connections in writing state (applicable only to the event MPM).</p>|Dependent item|apache.connections[async_writing{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ConnsAsyncWriting`</p></li></ul>|
|Apache: Connections total|<p>The number of total connections.</p>|Dependent item|apache.connections[total{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ConnsTotal`</p></li></ul>|
|Apache: Bytes per request|<p>The average number of client requests per second.</p>|Dependent item|apache.bytes[per_request{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.BytesPerReq`</p></li></ul>|
|Apache: Number of async processes|<p>The number of asynchronous processes.</p>|Dependent item|apache.process[num{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Processes`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

