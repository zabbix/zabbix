
# Ribbon SBC SWe core by HTTP

## Overview

Ribbon’s SBC Software Edition (SBC SWe), offers identical feature software to its award-winning SBC 5400 and SBC 7000 appliances – only without an appliance. This means enterprises have the flexibility to deploy in their SBCs multiple environments - their own data center, on their own private cloud, or utilize a public cloud.

This template is designed for the effortless deployment of Ribbon SBC SWe core monitoring and doesn't require any external scripts.

More details can be found in the official documentation:
  - [REST API Reference](https://publicdoc.rbbn.com/spaces/SBXCONFAPIDOC/pages/360972436/RESTCONF+API+Reference+Guide)
  - [REST API User's Guide](https://publicdoc.rbbn.com/spaces/SBXCONFAPIDOC/pages/444760643/RESTCONF+API+User+Guide)

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Ribbon SBC SWe core

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a new user according [REST API - Requirements](https://publicdoc.rbbn.com/spaces/SBXCONFAPIDOC/pages/444760644/RESTCONF+API+Requirements)
2. Create a new host
3. Link the template to the host created earlier
4. Set the host macros (on the host or template level) required for getting data:
```text
{$RIBBON.URL}
```
5. Set the host macros (on the host or template level) with the login and password of the user created earlier:
```text
{$RIBBON.USERNAME}
{$RIBBON.PASSWORD}
```

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$RIBBON.USERNAME}|<p>Ribbon SBC username.</p>||
|{$RIBBON.PASSWORD}|<p>Ribbon SBC user password.</p>||
|{$RIBBON.URL}|<p>Ribbon SBC API IP.</p>||
|{$RIBBON.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$RIBBON.CPU.UTIL.CRIT}|<p>The threshold of the CPU usage in percent.</p>|`90`|
|{$RIBBON.MEMORY.UTIL.CRIT}|<p>The threshold of the memory usage in percent.</p>|`90`|
|{$RIBBON.CE.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of call engine name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.CE.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of call engine name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.CE.DISCOVERY.ROLE.MATCHES}|<p>Sets the regex string of call engine role to be allowed in discovery.</p>|`.*`|
|{$RIBBON.CE.DISCOVERY.ROLE.NOT_MATCHES}|<p>Sets the regex string of call engine role to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.CE.DISCOVERY.HW.TYPE.MATCHES}|<p>Sets the regex string of call engine hardware type to be allowed in discovery.</p>|`.*`|
|{$RIBBON.CE.DISCOVERY.HW.TYPE.NOT_MATCHES}|<p>Sets the regex string of call engine hardware type to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.SYNC.MODULE.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of sync module name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.SYNC.MODULE.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of sync module name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>Gets the system information.</p>|Script|ribbon.system.data.get|
|System name|<p>Name of the system.</p>|Dependent item|ribbon.system.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.admin[0].actualSystemName`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Management mode|<p>Management mode of the system. Possible value:</p><p>  - haMode1to1</p><p>  - haModeNto1</p>|Dependent item|ribbon.system.haMode<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.admin[0].haMode`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System congestion level|<p>The current System congestion level.</p>|Dependent item|ribbon.system.congestion.level<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CongestionStatus[0].systemCongestionMCLevel`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|CPU congestion level|<p>The current CPU congestion level.</p>|Dependent item|ribbon.system.cpu.level<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CongestionStatus[0].systemCongestionCPULevel`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call rate congestion level|<p>The current Call rate congestion level.</p>|Dependent item|ribbon.system.call.rate.level<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CongestionStatus[0].systemCongestionCallRateLevel`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|IRTT congestion level|<p>The current IRTT congestion level.</p>|Dependent item|ribbon.system.irtt.rate.level<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CongestionStatus[0].systemCongestionIRTTLevel`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|DSP congestion level|<p>The current DSP congestion level.</p>|Dependent item|ribbon.system.dsp.rate.level<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CongestionStatus[0].systemCongestionDSPLevel`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Attempts|<p>Number of call attempts on this server.</p>|Dependent item|ribbon.system.call.attempts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].callAttempts`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Completions|<p>Total number of completed call attempts on this server.</p>|Dependent item|ribbon.system.call.completions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].callCompletions`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Active|<p>Current number of active managed calls on this server.</p>|Dependent item|ribbon.system.call.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].activeCalls`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Stable|<p>Current number of stable managed calls on this server.</p>|Dependent item|ribbon.system.call.stable<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].stableCalls`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Updates|<p>Number of call updates (modifications) on this server.</p>|Dependent item|ribbon.system.call.updates<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].callUpdates`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Total|<p>Total number of calls on this server.</p>|Dependent item|ribbon.system.call.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].totalCalls`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Signalling active|<p>Current number of active non-call associated signalling channels in the server.</p>|Dependent item|ribbon.system.non.call.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].activeCallsNonUser`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Signalling stable|<p>Current number of stable non-call associated signalling channels in the server.</p>|Dependent item|ribbon.system.non.call.stable<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].stableCallsNonUser`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Signalling total|<p>Total number of non-user calls on this server.</p>|Dependent item|ribbon.system.non.call.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].totalCallsNonUser`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Emergency establishing|<p>Number of establishing emergency calls (i.e. not yet stable).</p>|Dependent item|ribbon.system.emergency.call.establishing<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].totalCallsEmergEstablishing`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call status: Emergency stable|<p>Number of stable emergency calls.</p>|Dependent item|ribbon.system.emergency.call.stable<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountStatus[0].totalCallsEmergStable`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: Concurrent calls|<p>The current high water mark of total number of active calls.</p>|Dependent item|ribbon.system.call.concurrent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].callCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: Encrypt calls|<p>The current high water mark of total number of encrypt calls.</p>|Dependent item|ribbon.system.call.encrypt<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].callCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: SRTP calls|<p>The current high water mark of total number of active srtp calls.</p>|Dependent item|ribbon.system.call.srtp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].srtpCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: Video calls|<p>The current high water mark of total number of active enhanced video calls.</p>|Dependent item|ribbon.system.call.video<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].enhancedVideoCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: Transcoded calls|<p>The current high water mark of total number of transcoded sessions.</p>|Dependent item|ribbon.system.call.transcode.rec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].transcodeCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: NICE recorded calls|<p>The current high water mark of total number of nice recording calls.</p>|Dependent item|ribbon.system.call.nice.rec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].niceRecCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: SIP recorded calls|<p>The current high water mark of total number of sip recording calls.</p>|Dependent item|ribbon.system.call.sip.rec<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].sipRecCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: MRF sessions|<p>The current high water mark of total number of mrf calls.</p>|Dependent item|ribbon.system.session.mrf<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].mrfSessionsCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: SLB sessions|<p>The current high water mark of total number of SLB calls.</p>|Dependent item|ribbon.system.session.slb<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].slbSessionsCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: SOSBC sessions|<p>The current high water mark of total number of SOSBC calls.</p>|Dependent item|ribbon.system.session.sosbc<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].sosbcSessionsCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: WebSocket sessions|<p>The current high water mark of total number of web socket calls.</p>|Dependent item|ribbon.system.session.websocket<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].webSocketSessionsCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: HPC queue attempts|<p>The current number of HPC calls that were successfully placed on HPC Call Queuing queues.</p>|Dependent item|ribbon.system.hpc.queue.attempts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].hpcQueueAttempts`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: HPC queue overflows|<p>The current number of HPC calls for which HPC call queuing attempts were unsuccessful because queues were full.</p>|Dependent item|ribbon.system.hpc.queue.overflows<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].hpcQueueOverflows`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: HPC queue abandons|<p>The current number of HPC calls that were placed in HPC Call Queuing queues, but were subsequently abandoned before they could be completed.</p>|Dependent item|ribbon.system.hpc.queue.abandons<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].hpcQueueAbandons`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call statistics: HPC queue timeouts|<p>The current number of HPC calls removed from HPC Call Queuing queues due to expiration of the queue timeout timer.</p>|Dependent item|ribbon.system.hpc.queue.timeouts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.callCountCurrentStatistics[0].hpcQueueTimeouts`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### LLD rule Sync module discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sync module discovery|<p>Used for discovering of sync module.</p>|Dependent item|ribbon.sync.module.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Sync module discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Module [{#SYNC.MODULE}]: Sync status|<p>Indicates the inter-CE data synchronization state.</p>|Dependent item|ribbon.sync.module.status[{#SYNC.MODULE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Sync module discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Module [{#SYNC.MODULE}]: Sync status is not "Completed"|<p>The module sync status is not "Completed".</p>|`last(/Ribbon SBC SWe core by HTTP/ribbon.sync.module.status[{#SYNC.MODULE}])<>"syncCompleted"`|Average||

### LLD rule Call engine discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Call engine discovery|<p>Used for discovering of call engine.</p>|Dependent item|ribbon.ce.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Call engine discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Call engine [{#CE.NAME}]: Name|<p>Instance/Ce Name given by user as userdata.</p>|Dependent item|ribbon.ce.name[{#CE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call engine [{#CE.NAME}]: Role|<p>When set to primary, role designates a server for internal processing.</p>|Dependent item|ribbon.ce.role[{#CE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverAdmin.[?(@.name == "{#CE.NAME}")].role.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call engine [{#CE.NAME}]: HW type|<p>HW type of the server.</p>|Dependent item|ribbon.ce.hwType[{#CE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverAdmin.[?(@.name == "{#CE.NAME}")].hwType.first()`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Call engine [{#CE.NAME}]: HW sub-type|<p>HW sub-type of the server.</p>|Dependent item|ribbon.ce.hw.sub.type[{#CE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverAdmin.[?(@.name == "{#CE.NAME}")].hwSubType.first()`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

# Ribbon SBC SWe CE by HTTP

## Overview

Ribbon’s SBC Software Edition (SBC SWe), offers identical feature software to its award-winning SBC 5400 and SBC 7000 appliances – only without an appliance. This means enterprises have the flexibility to deploy in their SBCs multiple environments - their own data center, on their own private cloud, or utilize a public cloud.

This template is designed for the effortless deployment of Ribbon SBC SWe core monitoring and doesn't require any external scripts.
This template can be used in discovery as well as manually linked to a host. To use this template as manually linked to a host, attach it to the host and set manually the value of '{$RIBBON.CE.NAME}' macro.

More details can be found in the official documentation:
  - [REST API Reference](https://publicdoc.rbbn.com/spaces/SBXCONFAPIDOC/pages/360972436/RESTCONF+API+Reference+Guide)
  - [REST API User's Guide](https://publicdoc.rbbn.com/spaces/SBXCONFAPIDOC/pages/444760643/RESTCONF+API+User+Guide)

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Ribbon SBC SWe core

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a new user according [REST API - Requirements](https://publicdoc.rbbn.com/spaces/SBXCONFAPIDOC/pages/444760644/RESTCONF+API+Requirements)
2. Create a new host
3. Link the template to the host created earlier
4. Set the host macros (on the host or template level) required for getting data:
```text
{$RIBBON.URL}
```
5. Set the host macros (on the host or template level) with the login and password of the user created earlier:
```text
{$RIBBON.USERNAME}
{$RIBBON.PASSWORD}
```
6. Set the host macros (on the host level) with the name of CE.
```text
{$RIBBON.CE.NAME}
```

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$RIBBON.USERNAME}|<p>Ribbon SBC username.</p>||
|{$RIBBON.PASSWORD}|<p>Ribbon SBC user password.</p>||
|{$RIBBON.URL}|<p>Ribbon SBC API IP.</p>||
|{$RIBBON.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$RIBBON.CE.NAME}|<p>Ribbon SBC CE name.</p>||
|{$RIBBON.CPU.UTIL.CRIT}|<p>The threshold of the CPU usage in percent.</p>|`90`|
|{$RIBBON.MEMORY.UTIL.CRIT}|<p>The threshold of the memory usage in percent.</p>|`90`|
|{$RIBBON.SWAP.UTIL.CRIT}|<p>The threshold of the swap usage in percent.</p>|`90`|
|{$RIBBON.DISK.UTIL.CRIT}|<p>The threshold of the disk usage in percent.</p>|`90`|
|{$RIBBON.PORT.UTIL.CRIT}|<p>The threshold of the packet port usage in percent.</p>|`90`|
|{$RIBBON.FS.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of file system name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.FS.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of file system name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.PACKET.PORT.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of packet port name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.PACKET.PORT.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of packet port name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.MANAGEMENT.PORT.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of management port name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.MANAGEMENT.PORT.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of management port name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.REDUNDANCY.PORT.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of port redundancy group name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.REDUNDANCY.PORT.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of port redundancy group name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.MONITOR.PORT.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of monitor port name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.MONITOR.PORT.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of monitor port name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>Gets the server information.</p>|Script|ribbon.server.data.get|
|Server name|<p>Identifies the server.</p>|Dependent item|ribbon.server.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverStatus[0].name`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Server hardware type|<p>Identifies the type of server module the indexed slot has been configured to accept. Server modules other than this type are rejected by the System Manager.</p>|Dependent item|ribbon.server.hw.type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverStatus[0].hwType`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Server hardware sub type|<p>Identifies the type of server module the indexed slot has been configured to accept. Server modules other than this type are rejected by the System Manager.</p>|Dependent item|ribbon.server.hw.sub.type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverStatus[0].hwSubType`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Serial number|<p>Identifies the serial number of the server module. This is the serial number assigned to the server module at manufacture.</p>|Dependent item|ribbon.server.serial.number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverStatus[0].serialNum`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Platform version|<p>Indicates the platform version currently running on server.</p>|Dependent item|ribbon.server.platform.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverStatus[0].platformVersion`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Application version|<p>Indicates the application version currently running on server.</p>|Dependent item|ribbon.server.application.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverStatus[0].applicationVersion`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Role|<p>Indicates the redundancy role of the server (for management entities).</p>|Dependent item|ribbon.server.role<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverStatus[0].mgmtRedundancyRole`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Uptime|<p>Indicates the server module uptime in days/hours/minutes/sec.</p>|Dependent item|ribbon.server.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverStatus[0].upTime`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Application uptime|<p>Indicates the application uptime on the server in days/hours/minutes/sec.</p>|Dependent item|ribbon.server.application.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverStatus[0].applicationUpTime`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Sync status|<p>Indicates the inter-CE data synchronization state.</p>|Dependent item|ribbon.server.sync.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serverStatus[0].syncStatus`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Memory utilization|<p>The average memory % utilization for this interval.</p>|Dependent item|ribbon.server.memory.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memoryUtilCurrentStatistics[0].average`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Swap utilization|<p>The average % of swap utilization for this interval.</p>|Dependent item|ribbon.server.swap.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memoryUtilCurrentStatistics[0].averageSwap`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|CPU utilization|<p>The average % of cpu utilization for this interval.</p>|Dependent item|ribbon.server.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpuUtilCurrentStatistics`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Role has been changed||`last(/Ribbon SBC SWe CE by HTTP/ribbon.server.role)<>last(/Ribbon SBC SWe CE by HTTP/ribbon.server.role,#2)`|Info|**Manual close**: Yes|
|Ribbon: Application has been restarted||`last(/Ribbon SBC SWe CE by HTTP/ribbon.server.application.uptime)<10m`|Info|**Manual close**: Yes|
|Ribbon: Sync status is not "Completed"||`last(/Ribbon SBC SWe CE by HTTP/ribbon.server.sync.status)<>"syncCompleted"`|Warning|**Manual close**: Yes|
|Ribbon: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Ribbon SBC SWe CE by HTTP/ribbon.server.memory.util,5m) > {$RIBBON.MEMORY.UTIL.CRIT}`|Average||
|Ribbon: High swap utilization|<p>The system is running out of free memory.</p>|`min(/Ribbon SBC SWe CE by HTTP/ribbon.server.swap.util,5m) > {$RIBBON.SWAP.UTIL.CRIT}`|Average||
|Ribbon: High cpu utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Ribbon SBC SWe CE by HTTP/ribbon.server.cpu.util,5m) > {$RIBBON.CPU.UTIL.CRIT}`|Average||

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>Used for discovering of cpu.</p>|Dependent item|ribbon.cpu.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU [{#CPU.NUM}]: Utilization|<p>The average cpu % utilization for this interval.</p>|Dependent item|ribbon.sync.cpu.util[{#CPU.NUM}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpuUtilCurrentStatistics.["{#CPU.NUM}"].average`</p></li></ul>|

### LLD rule FS discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FS discovery|<p>Used for discovering of disk.</p>|Dependent item|ribbon.fs.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for FS discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FS [{#FS}]: Total size|<p>Capacity of the disk.</p>|Dependent item|ribbon.fs.total[{#FS}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardDiskUsage.["{#FS}"].totalDiskSpace`</p></li><li><p>Trim: `KBytes`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FS [{#FS}]: Free|<p>Indicates free hard disk space.</p>|Dependent item|ribbon.fs.free[{#FS}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardDiskUsage.["{#FS}"].freeDiskSpace`</p></li><li><p>Trim: `KBytes`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FS [{#FS}]: Utilization|<p>Indicates used hard disk.</p>|Dependent item|ribbon.fs.util[{#FS}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardDiskUsage.["{#FS}"].usedDiskSpace`</p></li><li><p>Trim: `%`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FS [{#FS}]: Role|<p>Role of the server for the indicated partition.</p>|Dependent item|ribbon.fs.role[{#FS}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardDiskUsage.["{#FS}"].role`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FS [{#FS}]: Sync status|<p>Partition's synchronization status with peer server.</p>|Dependent item|ribbon.fs.sync.status[{#FS}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardDiskUsage.["{#FS}"].syncStatus`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for FS discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: FS [{#FS}]: High disk space utilization|<p>The disk used space is more than threshold.</p>|`min(/Ribbon SBC SWe CE by HTTP/ribbon.fs.util[{#FS}],5m) > {$RIBBON.DISK.UTIL.CRIT}`|Average||

### LLD rule Packet port discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Packet port discovery|<p>Used for discovering of packet port.</p>|Dependent item|ribbon.packet.port.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Packet port discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Packet port [{#PORT}]: Negotiated speed|<p>The interface speed. Packet port speed is not negotiable. When statistics are generated for EMS, negotiatedSpeed will be displayed as integer value. </p><p>Possible values:</p><p>0 - speed10Mbps</p><p>1 - speed100Mbps</p><p>2 - speed1000Mbps</p><p>3 - speed10000Mbps</p><p>4 - unknown</p>|Dependent item|ribbon.packet.port.speed[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packetPortStatus.["{#PORT}"].negotiatedSpeed`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Packet port [{#PORT}]: Bits received|<p>Actual Rx bandwidth in use on this port, bytes/sec.</p>|Dependent item|ribbon.packet.port.in[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packetPortStatus.["{#PORT}"].rxActualBandwidth`</p></li></ul>|
|Packet port [{#PORT}]: Bits sent|<p>Actual Tx bandwidth in use on this port, bytes/sec.</p>|Dependent item|ribbon.packet.port.out[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packetPortStatus.["{#PORT}"].txActualBandwidth`</p></li></ul>|
|Packet port [{#PORT}]: Utilization|<p>Percentage of maximum bandwidth allocated on this port.</p>|Dependent item|ribbon.packet.port.util[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packetPortStatus.["{#PORT}"].bandwidthUsage`</p></li></ul>|
|Packet port [{#PORT}]: Link state|<p>The state of the interface. When statistics are generated for EMS, linkState will be displayed as integer value. </p><p>Possible values:</p><p>0 - null</p><p>1 - admnDisabled</p><p>2 - admnEnabledPortDown</p><p>3 - admnEnabledPortUp</p><p>4 - admnDisabledNoLicense</p><p>5 - admnEnabledPortDownInvalidSfpWrongSpeed</p><p>6 - admnEnabledPortDownInvalidSfpNonSonus</p>|Dependent item|ribbon.packet.port.link.state[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packetPortStatus.["{#PORT}"].linkState`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Packet port discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Packet port [{#PORT}]: High port utilization|<p>The packet port usage is more than threshold.</p>|`min(/Ribbon SBC SWe CE by HTTP/ribbon.packet.port.util[{#PORT}],5m) > {$RIBBON.PORT.UTIL.CRIT}`|Average||
|Ribbon: Packet port [{#PORT}]: Link state is down|<p>The packet port link state is down.</p>|`last(/Ribbon SBC SWe CE by HTTP/ribbon.packet.port.link.state[{#PORT}])=2 or last(/Ribbon SBC SWe CE by HTTP/ribbon.packet.port.link.state[{#PORT}])=5 or last(/Ribbon SBC SWe CE by HTTP/ribbon.packet.port.link.state[{#PORT}])=6`|Average||

### LLD rule Management port discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Management port discovery|<p>Used for discovering of management port.</p>|Dependent item|ribbon.mgmt.port.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Management port discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Management port [{#PORT}]: Negotiated speed|<p>The negotiated interface speed. When statistics are generated for EMS, negotiatedSpeed will be displayed as integer value. </p><p>Possible values:</p><p>0 - speed10Mbps</p><p>1 - speed100Mbps</p><p>2 - speed1000Mbps</p><p>3 - speed10000Mbps</p><p>4 - unknown</p>|Dependent item|ribbon.mgmt.port.speed[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mgmtPortStatus.["{#PORT}"].negotiatedSpeed`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Management port [{#PORT}]: Link state|<p>The state of the interface.</p><p>Possible values:</p><p>0 - null</p><p>1 - admnDisabled</p><p>2 - admnEnabledPortDown</p><p>3 - admnEnabledPortUp</p><p>4 - admnDisabledNoLicense</p><p>5 - admnEnabledPortDownInvalidSfpWrongSpeed</p><p>6 - admnEnabledPortDownInvalidSfpNonSonus</p>|Dependent item|ribbon.mgmt.port.link.state[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mgmtPortStatus.["{#PORT}"].linkState`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Management port [{#PORT}]: Duplex mode|<p>The negotiated interface duplex mode.</p>|Dependent item|ribbon.mgmt.port.duplex.mode[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mgmtPortStatus.["{#PORT}"].duplexMode`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Management port [{#PORT}]: Bits received|<p>Actual Rx bandwidth in use on this port, bytes/sec.</p>|Dependent item|ribbon.mgmt.port.in[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mgmtPortStatus.["{#PORT}"].rxActualBandwidth`</p></li></ul>|
|Management port [{#PORT}]: Bits sent|<p>Actual Tx bandwidth in use on this port, bytes/sec.</p>|Dependent item|ribbon.mgmt.port.out[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mgmtPortStatus.["{#PORT}"].txActualBandwidth`</p></li></ul>|

### Trigger prototypes for Management port discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Management port [{#PORT}]: Link state is down|<p>The management port link state is down.</p>|`last(/Ribbon SBC SWe CE by HTTP/ribbon.mgmt.port.link.state[{#PORT}])=2 or last(/Ribbon SBC SWe CE by HTTP/ribbon.mgmt.port.link.state[{#PORT}])=5 or last(/Ribbon SBC SWe CE by HTTP/ribbon.mgmt.port.link.state[{#PORT}])=6`|Average||

### LLD rule Port redundancy group discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Port redundancy group discovery|<p>Used for discovering of port redundancy group.</p>|Dependent item|ribbon.redundancy.port.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Port redundancy group discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Port redundancy group [{#PORT}]: Group name|<p>The name of this port redundancy group.</p>|Dependent item|ribbon.redundancy.port.group.name[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portRedundancyGroupStatus.["{#PORT}"].prgName`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port redundancy group [{#PORT}]: Port name|<p>The name of physical/logical port.</p>|Dependent item|ribbon.redundancy.port.name[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portRedundancyGroupStatus.["{#PORT}"].portName`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port redundancy group [{#PORT}]: Type|<p>Interface Type of this port redundancy group.</p>|Dependent item|ribbon.redundancy.port.type[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portRedundancyGroupStatus.["{#PORT}"].interfaceType`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port redundancy group [{#PORT}]: State|<p>The state of the logical port.</p>|Dependent item|ribbon.redundancy.port.state[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portRedundancyGroupStatus.["{#PORT}"].state`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port redundancy group [{#PORT}]: Redundancy mode|<p>The VM redundancy mode of the CE.</p>|Dependent item|ribbon.redundancy.port.mode[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portRedundancyGroupStatus.["{#PORT}"].vmRedundancyMode`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port redundancy group [{#PORT}]: Failure count|<p>The current number of port monitors within this port redundancy group that have declared themselves failed.</p>|Dependent item|ribbon.redundancy.port.failure.count[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portRedundancyGroupStatus.["{#PORT}"].failureCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Port redundancy group discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Port redundancy group [{#PORT}]: State is not "up"|<p>The port redundancy group state is not up.</p>|`last(/Ribbon SBC SWe CE by HTTP/ribbon.redundancy.port.state[{#PORT}])<>"up"`|Average||

### LLD rule Port monitor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Port monitor discovery|<p>Used for discovering of port monitor.</p>|Dependent item|ribbon.monitor.port.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Port monitor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Port monitor [{#PORT}]: Group name|<p>The name of this port group.</p>|Dependent item|ribbon.monitor.port.group.name[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portMonitorStatus.["{#PORT}"].prgName`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port monitor [{#PORT}]: Name|<p>The name of this port monitor.</p>|Dependent item|ribbon.monitor.port.name[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portMonitorStatus.["{#PORT}"].pmName`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port monitor [{#PORT}]: Physical name|<p>The name of physical port.</p>|Dependent item|ribbon.monitor.port.physical.name[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portMonitorStatus.["{#PORT}"].portName`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port monitor [{#PORT}]: Role|<p>The role of the physical port.</p>|Dependent item|ribbon.monitor.port.role[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portMonitorStatus.["{#PORT}"].role`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port monitor [{#PORT}]: Fault state|<p>The fault state of the physical port.</p>|Dependent item|ribbon.monitor.port.fault.state[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portMonitorStatus.["{#PORT}"].faultState`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port monitor [{#PORT}]: State|<p>The link state of the physical port.</p>|Dependent item|ribbon.monitor.port.state[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portMonitorStatus.["{#PORT}"].state`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port monitor [{#PORT}]: Failures|<p>The current number failures of this port monitor.</p>|Dependent item|ribbon.monitor.port.failures[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portMonitorStatus.["{#PORT}"].failures`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Port monitor [{#PORT}]: Link failures|<p>The current number of link failures of this port monitor.</p>|Dependent item|ribbon.monitor.port.link.failures[{#PORT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portMonitorStatus.["{#PORT}"].linkFailures`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Port monitor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Port monitor [{#PORT}]: State is not "up"|<p>The port monitor state is not up.</p>|`last(/Ribbon SBC SWe CE by HTTP/ribbon.monitor.port.state[{#PORT}])<>"up"`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

