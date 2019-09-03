
# Template App Apache by HTTP

## Overview

For Zabbix version: 4.2  
The template to monitor Apache HTTPD by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.  
`Template App Apache by HTTP` - (Zabbix version >= 4.2) - collects metrics by polling [mod_status](https://httpd.apache.org/docs/current/mod/mod_status.html) with HTTP agent remotely:  

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


This template was tested on:

- Apache, version 2.4.41

## Setup

Setup [mod_status](https://httpd.apache.org/docs/current/mod/mod_status.html)
Check module availability: httpd -M 2>/dev/null | grep status_module

Example configuration of Apache:

```text
<Location "/server-status">
  SetHandler server-status
  Require host example.com
</Location>
```

If you use another location, don't forget to change {$APACHE.STATUS.PATH} macro.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$APACHE.RESPONSE_TIME.MAX.WARN}|Maximum Apache response time in seconds for trigger expression|10|
|{$APACHE.STATUS.PATH}|The URL-path to the Apache status page|server-status?auto|
|{$APACHE.STATUS.PORT}|The port of Apache status page|80|
|{$APACHE.STATUS.SCHEME}|Request scheme which may be http or https|http|

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Event MPM discovery|Additional metrics if event MPM is used</br>https://httpd.apache.org/docs/current/mod/event.html</br>|DEPENDENT|apache.mpm.event.discovery</br>**Preprocessing**:</br> - JSONPATH: `$.ServerMPM`</br> - JAVASCRIPT: `return JSON.stringify(value === 'event' ? [{'{#SINGLETON}': ''}] : []);`|

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Apache|Apache: Service ping|-|SIMPLE|net.tcp.service[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"]</br>**Preprocessing**:</br> - DISCARD_UNCHANGED_HEARTBEAT: `10m`|
|Apache|Apache: Service response time|-|SIMPLE|net.tcp.service.perf[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"]|
|Apache|Apache: Total bytes|Total bytes served|DEPENDENT|apache.bytes</br>**Preprocessing**:</br> - JSONPATH: `$["Total kBytes"]`</br> - MULTIPLIER: `1024`|
|Apache|Apache: Bytes per second||DEPENDENT|apache.bytes.rate</br>**Preprocessing**:</br> - JSONPATH: `$["Total kBytes"]`</br> - MULTIPLIER: `1024`</br> - CHANGE_PER_SECOND|
|Apache|Apache: Requests per second|Calculated as change rate for 'Total requests' stat.</br>ReqPerSec is not used, as it counts average since last Apache server start.|DEPENDENT|apache.requests.rate</br>**Preprocessing**:</br> - JSONPATH: `$["Total Accesses"]`</br> - CHANGE_PER_SECOND|
|Apache|Apache: Total requests|A total number of accesses|DEPENDENT|apache.requests</br>**Preprocessing**:</br> - JSONPATH: `$["Total Accesses"]`|
|Apache|Apache: Uptime|Service uptime in seconds|DEPENDENT|apache.uptime</br>**Preprocessing**:</br> - JSONPATH: `$.ServerUptimeSeconds`|
|Apache|Apache: Version|Service version|DEPENDENT|apache.version</br>**Preprocessing**:</br> - JSONPATH: `$.ServerVersion`</br> - DISCARD_UNCHANGED_HEARTBEAT: `1d`|
|Apache|Apache: Total workers busy|Total number of busy worker threads/processes|DEPENDENT|apache.workers_total.busy</br>**Preprocessing**:</br> - JSONPATH: `$.BusyWorkers`|
|Apache|Apache: Total workers idle|Total number of idle worker threads/processes|DEPENDENT|apache.workers_total.idle</br>**Preprocessing**:</br> - JSONPATH: `$.IdleWorkers`|
|Apache|Apache: Workers closing connection|Number of workers in closing state|DEPENDENT|apache.workers.closing</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.closing`|
|Apache|Apache: Workers DNS lookup|Number of workers in dnslookup state|DEPENDENT|apache.workers.dnslookup</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.dnslookup`|
|Apache|Apache: Workers finishing|Number of workers in finishing state|DEPENDENT|apache.workers.finishing</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.finishing`|
|Apache|Apache: Workers idle cleanup|Number of workers in cleanup state|DEPENDENT|apache.workers.cleanup</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.cleanup`|
|Apache|Apache: Workers keepalive (read)|Number of workers in keepalive state|DEPENDENT|apache.workers.keepalive</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.keepalive`|
|Apache|Apache: Workers logging|Number of workers in logging state|DEPENDENT|apache.workers.logging</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.logging`|
|Apache|Apache: Workers reading request|Number of workers in reading state|DEPENDENT|apache.workers.reading</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.reading`|
|Apache|Apache: Workers sending reply|Number of workers in sending state|DEPENDENT|apache.workers.sending</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.sending`|
|Apache|Apache: Workers slot with no current process|Number of slots with no current process|DEPENDENT|apache.workers.slot</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.slot`|
|Apache|Apache: Workers starting up|Number of workers in starting state|DEPENDENT|apache.workers.starting</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.starting`|
|Apache|Apache: Workers waiting for connection|Number of workers in waiting state|DEPENDENT|apache.workers.waiting</br>**Preprocessing**:</br> - JSONPATH: `$.Workers.waiting`|
|Apache|Apache: Connections async closing|Number of async connections in closing state (only applicable to event MPM)|DEPENDENT|apache.connections[async_closing{#SINGLETON}]</br>**Preprocessing**:</br> - JSONPATH: `$.ConnsAsyncClosing`|
|Apache|Apache: Connections async keep alive|Number of async connections in keep-alive state (only applicable to event MPM)|DEPENDENT|apache.connections[async_keep_alive{#SINGLETON}]</br>**Preprocessing**:</br> - JSONPATH: `$.ConnsAsyncKeepAlive`|
|Apache|Apache: Connections async writing|Number of async connections in writing state (only applicable to event MPM)|DEPENDENT|apache.connections[async_writing{#SINGLETON}]</br>**Preprocessing**:</br> - JSONPATH: `$.ConnsAsyncWriting`|
|Apache|Apache: Connections total|Number of total connections|DEPENDENT|apache.connections[total{#SINGLETON}]</br>**Preprocessing**:</br> - JSONPATH: `$.ConnsTotal`|
|Apache|Apache: Bytes per request|Average number of client requests per second|DEPENDENT|apache.bytes[per_request{#SINGLETON}]</br>**Preprocessing**:</br> - JSONPATH: `$.BytesPerReq`|
|Apache|Apache: Number of async processes|Number of async processes|DEPENDENT|apache.process[num{#SINGLETON}]</br>**Preprocessing**:</br> - JSONPATH: `$.Processes`|
|Zabbix_raw_items|Apache: Get status|Getting data from a machine-readable version of the Apache status page|HTTP_AGENT|apache.get_status</br>**Preprocessing**:</br> - JAVASCRIPT: `// Convert Apache status to JSON. var lines = value.split("\n"); var fields = {},     output = {},     workers = {         "_": 0, "S": 0, "R": 0,         "W": 0, "K": 0, "D": 0,         "C": 0, "L": 0, "G": 0,         "I": 0, ".": 0     }; // Get all "Key: Value" pairs as an object. for (var i = 0; i < lines.length; i++) {     var line = lines[i].match(/([A-z0-9 ]+): (.*)/);     if (line !== null) {         output[line[1]] = isNaN(line[2]) ? line[2] : Number(line[2]);     } }   // Parse "Scoreboard" to get worker count. if (typeof output.Scoreboard === 'string') {     for (var i = 0; i < output.Scoreboard.length; i++) {         var char = output.Scoreboard[i];         workers[char]++;     } }   // Add worker data to the output. output.Workers = {     waiting: workers["_"], starting: workers["S"], reading: workers["R"],     sending: workers["W"], keepalive: workers["K"], dnslookup: workers["D"],     closing: workers["C"], logging: workers["L"], finishing: workers["G"],     cleanup: workers["I"], slot: workers["."] };   // Return JSON string. return JSON.stringify(output);`|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Apache: Service is down|Last value: {ITEM.LASTVALUE1}.|`{TEMPLATE_NAME:net.tcp.service[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"].last()}=0`|AVERAGE|Manual close: YES</br>|
|Apache: Service response time is too high (over {$APACHE.RESPONSE_TIME.MAX.WARN}s for 5m)|Last value: {ITEM.LASTVALUE1}.|`{TEMPLATE_NAME:net.tcp.service.perf[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"].min(5m)}>{$APACHE.RESPONSE_TIME.MAX.WARN}`|WARNING|Manual close: YES</br>**Depends on**:</br> - Apache: Service is down</br>|
|Apache: has been restarted (uptime < 10m)|Last value: {ITEM.LASTVALUE1}.</br>The Apache uptime is less than 10 minutes|`{TEMPLATE_NAME:apache.uptime.last()}<10m`|INFO|Manual close: YES</br>|
|Apache: Version has changed (new version: {ITEM.VALUE})|Last value: {ITEM.LASTVALUE1}.</br>Apache version has changed. Ack to close.|`{TEMPLATE_NAME:apache.version.diff()}=1 and {TEMPLATE_NAME:apache.version.strlen()}>0`|INFO|Manual close: YES</br>|
|Apache: Failed to fetch status page (or no data for 30m)|Last value: {ITEM.LASTVALUE1}.</br>Zabbix has not received data for items for the last 30 minutes.|`{TEMPLATE_NAME:apache.get_status.nodata(30m)}=1`|WARNING|Manual close: YES</br>**Depends on**:</br> - Apache: Service is down</br>|

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at
[ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384764-discussion-thread-for-official-zabbix-template-apache).

