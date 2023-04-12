
# Apache by HTTP

## Overview

This template is designed for the effortless deployment of Apache monitoring by Zabbix via HTTP and doesn't require any external scripts.
Template `Apache by HTTP` - collects metrics by polling [mod_status](https://httpd.apache.org/docs/current/mod/mod_status.html) with HTTP agent remotely:  

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
  Scoreboard: __________________________________________W_____________W___________________LW_____W______W_W_______............................................................................................................................................................................................................................................................................................................

```

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Apache 2.4.41

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Setup [mod_status](https://httpd.apache.org/docs/current/mod/mod_status.html)

Check module availability: `httpd -M 2>/dev/null | grep status_module`

Example configuration of Apache:

```text
<Location "/server-status">
  SetHandler server-status
  Require host example.com
</Location>
```

If you use another path, then don't forget to change `{$APACHE.STATUS.PATH}` macro.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$APACHE.STATUS.PORT}|<p>The port of Apache status page</p>|`80`|
|{$APACHE.STATUS.PATH}|<p>The URL path</p>|`server-status?auto`|
|{$APACHE.STATUS.SCHEME}|<p>Request scheme which may be http or https</p>|`http`|
|{$APACHE.RESPONSE_TIME.MAX.WARN}|<p>Maximum Apache response time in seconds for trigger expression</p>|`10`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Apache: Get status|<p>Getting data from a machine-readable version of the Apache status page.</p><p>https://httpd.apache.org/docs/current/mod/mod_status.html</p>|HTTP agent|apache.get_status<p>**Preprocessing**</p><ul><li>JavaScript: `The text is too long. Please see the template.`</li></ul>|
|Apache: Service ping| |Simple check|net.tcp.service[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"]<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `10m`</li></ul>|
|Apache: Service response time| |Simple check|net.tcp.service.perf[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"]|
|Apache: Total bytes|<p>Total bytes served</p>|Dependent item|apache.bytes<p>**Preprocessing**</p><ul><li>JSON Path: `$["Total kBytes"]`</li><li>Custom multiplier: `1024`</li></ul>|
|Apache: Bytes per second|<p>Calculated as change rate for 'Total bytes' stat.</p><p>BytesPerSec is not used, as it counts average since last Apache server start.</p>|Dependent item|apache.bytes.rate<p>**Preprocessing**</p><ul><li>JSON Path: `$["Total kBytes"]`</li><li>Custom multiplier: `1024`</li><li>Change per second</li></ul>|
|Apache: Requests per second|<p>Calculated as change rate for 'Total requests' stat.</p><p>ReqPerSec is not used, as it counts average since last Apache server start.</p>|Dependent item|apache.requests.rate<p>**Preprocessing**</p><ul><li>JSON Path: `$["Total Accesses"]`</li><li>Change per second</li></ul>|
|Apache: Total requests|<p>A total number of accesses</p>|Dependent item|apache.requests<p>**Preprocessing**</p><ul><li>JSON Path: `$["Total Accesses"]`</li></ul>|
|Apache: Uptime|<p>Service uptime in seconds</p>|Dependent item|apache.uptime<p>**Preprocessing**</p><ul><li>JSON Path: `$.ServerUptimeSeconds`</li></ul>|
|Apache: Version|<p>Service version</p>|Dependent item|apache.version<p>**Preprocessing**</p><ul><li>JSON Path: `$.ServerVersion`</li><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Apache: Total workers busy|<p>Total number of busy worker threads/processes</p>|Dependent item|apache.workers_total.busy<p>**Preprocessing**</p><ul><li>JSON Path: `$.BusyWorkers`</li></ul>|
|Apache: Total workers idle|<p>Total number of idle worker threads/processes</p>|Dependent item|apache.workers_total.idle<p>**Preprocessing**</p><ul><li>JSON Path: `$.IdleWorkers`</li></ul>|
|Apache: Workers closing connection|<p>Number of workers in closing state</p>|Dependent item|apache.workers.closing<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.closing`</li></ul>|
|Apache: Workers DNS lookup|<p>Number of workers in dnslookup state</p>|Dependent item|apache.workers.dnslookup<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.dnslookup`</li></ul>|
|Apache: Workers finishing|<p>Number of workers in finishing state</p>|Dependent item|apache.workers.finishing<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.finishing`</li></ul>|
|Apache: Workers idle cleanup|<p>Number of workers in cleanup state</p>|Dependent item|apache.workers.cleanup<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.cleanup`</li></ul>|
|Apache: Workers keepalive (read)|<p>Number of workers in keepalive state</p>|Dependent item|apache.workers.keepalive<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.keepalive`</li></ul>|
|Apache: Workers logging|<p>Number of workers in logging state</p>|Dependent item|apache.workers.logging<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.logging`</li></ul>|
|Apache: Workers reading request|<p>Number of workers in reading state</p>|Dependent item|apache.workers.reading<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.reading`</li></ul>|
|Apache: Workers sending reply|<p>Number of workers in sending state</p>|Dependent item|apache.workers.sending<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.sending`</li></ul>|
|Apache: Workers slot with no current process|<p>Number of slots with no current process</p>|Dependent item|apache.workers.slot<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.slot`</li></ul>|
|Apache: Workers starting up|<p>Number of workers in starting state</p>|Dependent item|apache.workers.starting<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.starting`</li></ul>|
|Apache: Workers waiting for connection|<p>Number of workers in waiting state</p>|Dependent item|apache.workers.waiting<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.waiting`</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Apache: Failed to fetch status page|<p>Zabbix has not received data for items for the last 30 minutes.</p>|`nodata(/Apache by HTTP/apache.get_status,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Apache: Service is down</li></ul>|
|Apache: Service is down||`last(/Apache by HTTP/net.tcp.service[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"])=0`|Average|**Manual close**: Yes|
|Apache: Service response time is too high||`min(/Apache by HTTP/net.tcp.service.perf[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"],5m)>{$APACHE.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Apache: Service is down</li></ul>|
|Apache: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Apache by HTTP/apache.uptime)<10m`|Info|**Manual close**: Yes|
|Apache: Version has changed|<p>Apache version has changed. Acknowledge to close manually.</p>|`last(/Apache by HTTP/apache.version,#1)<>last(/Apache by HTTP/apache.version,#2) and length(last(/Apache by HTTP/apache.version))>0`|Info|**Manual close**: Yes|

### LLD rule Event MPM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Event MPM discovery|<p>Additional metrics if event MPM is used</p><p>https://httpd.apache.org/docs/current/mod/event.html</p>|Dependent item|apache.mpm.event.discovery<p>**Preprocessing**</p><ul><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `3h`</li></ul>|

### Item prototypes for Event MPM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Apache: Connections async closing|<p>Number of async connections in closing state (only applicable to event MPM)</p>|Dependent item|apache.connections[async_closing{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.ConnsAsyncClosing`</li></ul>|
|Apache: Connections async keep alive|<p>Number of async connections in keep-alive state (only applicable to event MPM)</p>|Dependent item|apache.connections[async_keep_alive{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.ConnsAsyncKeepAlive`</li></ul>|
|Apache: Connections async writing|<p>Number of async connections in writing state (only applicable to event MPM)</p>|Dependent item|apache.connections[async_writing{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.ConnsAsyncWriting`</li></ul>|
|Apache: Connections total|<p>Number of total connections</p>|Dependent item|apache.connections[total{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.ConnsTotal`</li></ul>|
|Apache: Bytes per request|<p>Average number of client requests per second</p>|Dependent item|apache.bytes[per_request{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.BytesPerReq`</li></ul>|
|Apache: Number of async processes|<p>Number of async processes</p>|Dependent item|apache.process[num{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.Processes`</li></ul>|

## Feedback

Please report any issues with the template at `https://support.zabbix.com`.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
