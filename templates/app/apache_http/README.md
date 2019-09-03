
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

Check module availability: `httpd -M 2>/dev/null | grep status_module`

Example configuration of Apache:

```text
<Location "/server-status">
  SetHandler server-status
  Require host example.com
</Location>
```

If you use another path, then don't forget to change `{$APACHE.STATUS.PATH}` macro.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$APACHE.RESPONSE_TIME.MAX.WARN}|<p>Maximum Apache response time in seconds for trigger expression</p>|10|
|{$APACHE.STATUS.PATH}|<p>The URL path</p>|server-status?auto|
|{$APACHE.STATUS.PORT}|<p>The port of Apache status page</p>|80|
|{$APACHE.STATUS.SCHEME}|<p>Request scheme which may be http or https</p>|http|

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Event MPM discovery|<p>Additional metrics if event MPM is used</p><p>https://httpd.apache.org/docs/current/mod/event.html</p>|DEPENDENT|apache.mpm.event.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.ServerMPM`</p><p>- JAVASCRIPT: `return JSON.stringify(value === 'event' ? [{'{#SINGLETON}': ''}] : []);`</p>|

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Apache|Apache: Service ping|<p>-</p>|SIMPLE|net.tcp.service[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p>|
|Apache|Apache: Service response time|<p>-</p>|SIMPLE|net.tcp.service.perf[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"]|
|Apache|Apache: Total bytes|<p>Total bytes served</p>|DEPENDENT|apache.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$["Total kBytes"]`</p><p>- MULTIPLIER: `1024`</p>|
|Apache|Apache: Bytes per second||DEPENDENT|apache.bytes.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$["Total kBytes"]`</p><p>- MULTIPLIER: `1024`</p><p>- CHANGE_PER_SECOND|
|Apache|Apache: Requests per second|<p>Calculated as change rate for 'Total requests' stat.</p><p>ReqPerSec is not used, as it counts average since last Apache server start.</p>|DEPENDENT|apache.requests.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$["Total Accesses"]`</p><p>- CHANGE_PER_SECOND|
|Apache|Apache: Total requests|<p>A total number of accesses</p>|DEPENDENT|apache.requests<p>**Preprocessing**:</p><p>- JSONPATH: `$["Total Accesses"]`</p>|
|Apache|Apache: Uptime|<p>Service uptime in seconds</p>|DEPENDENT|apache.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.ServerUptimeSeconds`</p>|
|Apache|Apache: Version|<p>Service version</p>|DEPENDENT|apache.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.ServerVersion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Apache|Apache: Total workers busy|<p>Total number of busy worker threads/processes</p>|DEPENDENT|apache.workers_total.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.BusyWorkers`</p>|
|Apache|Apache: Total workers idle|<p>Total number of idle worker threads/processes</p>|DEPENDENT|apache.workers_total.idle<p>**Preprocessing**:</p><p>- JSONPATH: `$.IdleWorkers`</p>|
|Apache|Apache: Workers closing connection|<p>Number of workers in closing state</p>|DEPENDENT|apache.workers.closing<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.closing`</p>|
|Apache|Apache: Workers DNS lookup|<p>Number of workers in dnslookup state</p>|DEPENDENT|apache.workers.dnslookup<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.dnslookup`</p>|
|Apache|Apache: Workers finishing|<p>Number of workers in finishing state</p>|DEPENDENT|apache.workers.finishing<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.finishing`</p>|
|Apache|Apache: Workers idle cleanup|<p>Number of workers in cleanup state</p>|DEPENDENT|apache.workers.cleanup<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.cleanup`</p>|
|Apache|Apache: Workers keepalive (read)|<p>Number of workers in keepalive state</p>|DEPENDENT|apache.workers.keepalive<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.keepalive`</p>|
|Apache|Apache: Workers logging|<p>Number of workers in logging state</p>|DEPENDENT|apache.workers.logging<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.logging`</p>|
|Apache|Apache: Workers reading request|<p>Number of workers in reading state</p>|DEPENDENT|apache.workers.reading<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.reading`</p>|
|Apache|Apache: Workers sending reply|<p>Number of workers in sending state</p>|DEPENDENT|apache.workers.sending<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.sending`</p>|
|Apache|Apache: Workers slot with no current process|<p>Number of slots with no current process</p>|DEPENDENT|apache.workers.slot<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.slot`</p>|
|Apache|Apache: Workers starting up|<p>Number of workers in starting state</p>|DEPENDENT|apache.workers.starting<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.starting`</p>|
|Apache|Apache: Workers waiting for connection|<p>Number of workers in waiting state</p>|DEPENDENT|apache.workers.waiting<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.waiting`</p>|
|Apache|Apache: Connections async closing|<p>Number of async connections in closing state (only applicable to event MPM)</p>|DEPENDENT|apache.connections[async_closing{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ConnsAsyncClosing`</p>|
|Apache|Apache: Connections async keep alive|<p>Number of async connections in keep-alive state (only applicable to event MPM)</p>|DEPENDENT|apache.connections[async_keep_alive{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ConnsAsyncKeepAlive`</p>|
|Apache|Apache: Connections async writing|<p>Number of async connections in writing state (only applicable to event MPM)</p>|DEPENDENT|apache.connections[async_writing{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ConnsAsyncWriting`</p>|
|Apache|Apache: Connections total|<p>Number of total connections</p>|DEPENDENT|apache.connections[total{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ConnsTotal`</p>|
|Apache|Apache: Bytes per request|<p>Average number of client requests per second</p>|DEPENDENT|apache.bytes[per_request{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.BytesPerReq`</p>|
|Apache|Apache: Number of async processes|<p>Number of async processes</p>|DEPENDENT|apache.process[num{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Processes`</p>|
|Zabbix_raw_items|Apache: Get status|<p>Getting data from a machine-readable version of the Apache status page</p>|HTTP_AGENT|apache.get_status<p>**Preprocessing**:</p><p>- JAVASCRIPT: `// Convert Apache status to JSON var lines = value.split("\n"); var fields = {},     output = {},     workers = {         "_": 0, "S": 0, "R": 0,         "W": 0, "K": 0, "D": 0,         "C": 0, "L": 0, "G": 0,         "I": 0, ".": 0     }; // Get all "Key: Value" pairs as an object for (var i = 0; i < lines.length; i++) {     var line = lines[i].match(/([A-z0-9 ]+): (.*)/);     if (line !== null) {         output[line[1]] = isNaN(line[2]) ? line[2] : Number(line[2]);     } } // For versions without "ServerUptimeSeconds" metric output.ServerUptimeSeconds = output.ServerUptimeSeconds || output.Uptime   // Parse "Scoreboard" to get worker count. if (typeof output.Scoreboard === 'string') {     for (var i = 0; i < output.Scoreboard.length; i++) {         var char = output.Scoreboard[i];         workers[char]++;     } }   // Add worker data to the output output.Workers = {     waiting: workers["_"], starting: workers["S"], reading: workers["R"],     sending: workers["W"], keepalive: workers["K"], dnslookup: workers["D"],     closing: workers["C"], logging: workers["L"], finishing: workers["G"],     cleanup: workers["I"], slot: workers["."] };   // Return JSON string return JSON.stringify(output);`</p>|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Apache: Service is down|<p>Last value: {ITEM.LASTVALUE1}.</p>|`{TEMPLATE_NAME:net.tcp.service[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"].last()}=0`|AVERAGE|<p>Manual close: YES</p>|
|Apache: Service response time is too high (over {$APACHE.RESPONSE_TIME.MAX.WARN}s for 5m)|<p>Last value: {ITEM.LASTVALUE1}.</p>|`{TEMPLATE_NAME:net.tcp.service.perf[http,"{HOST.CONN}","{$APACHE.STATUS.PORT}"].min(5m)}>{$APACHE.RESPONSE_TIME.MAX.WARN}`|WARNING|<p>Manual close: YES</p><p>**Depends on**:</p><p>- Apache: Service is down</p>|
|Apache: has been restarted (uptime < 10m)|<p>Last value: {ITEM.LASTVALUE1}.</p><p>The Apache uptime is less than 10 minutes</p>|`{TEMPLATE_NAME:apache.uptime.last()}<10m`|INFO|<p>Manual close: YES</p>|
|Apache: Version has changed (new version: {ITEM.VALUE})|<p>Last value: {ITEM.LASTVALUE1}.</p><p>Apache version has changed. Ack to close.</p>|`{TEMPLATE_NAME:apache.version.diff()}=1 and {TEMPLATE_NAME:apache.version.strlen()}>0`|INFO|<p>Manual close: YES</p>|
|Apache: Failed to fetch status page (or no data for 30m)|<p>Last value: {ITEM.LASTVALUE1}.</p><p>Zabbix has not received data for items for the last 30 minutes.</p>|`{TEMPLATE_NAME:apache.get_status.nodata(30m)}=1`|WARNING|<p>Manual close: YES</p><p>**Depends on**:</p><p>- Apache: Service is down</p>|

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at
[ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384764-discussion-thread-for-official-zabbix-template-apache).

