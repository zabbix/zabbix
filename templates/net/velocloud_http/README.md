
# VeloCloud SD-WAN Edge by HTTP

## Overview

This template is designed for the effortless deployment of VeloCloud SD-WAN Edge monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- VeloCloud SD-WAN Orchestrator 6.4.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup


- Set the `{$VELOCLOUD.TOKEN}` and `{$VELOCLOUD.URL}` macros.

- Create an API token in the VeloCloud SD-WAN Orchestrator and use it in the `{$VELOCLOUD.TOKEN}` macros. See [Arista documentation](https://www.arista.com/en/global-settings-guide-vc-6-4/sase-6-4-user-management) for details.

- Set the Orchestrator URL for `{$VELOCLOUD.URL}`, e.g., `example.com`, where you replace "example.com" with the actual URL the Orchestrator is running on.

- Set the `{$VELOCLOUD.EDGE.FREQUENCY}` macro to define how often data should be collected from the VeloCloud Edge device (default is 15m). See [Arista API Rate Limiting and Throttling documentation](https://arista.my.site.com/AristaCommunity/s/article/API-Fair-Usage-Policy-for-Arista-VeloCloud-Orchestrator) for details on adjusting frequency.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VELOCLOUD.TOKEN}|<p>VeloCloud SD-WAN Orchestrator API token.</p>||
|{$VELOCLOUD.URL}|<p>VeloCloud SD-WAN Orchestrator URL, e.g., `vco.velocloud.net`.</p>||
|{$VELOCLOUD.ENTERPRISE.ID}|<p>VeloCloud SD-WAN Enterprise ID. Specify a single Enterprise ID (requires READ EDGE privileges). By default parameter is empty, data will be retrieved for all Enterprises (privileges required: READ ENTERPRISE and READ EDGE).</p>||
|{$VELOCLOUD.EDGE.ID}|<p>SD-WAN Edge ID.</p>||
|{$VELOCLOUD.EDGE.MEMORY.UTIL.WARN}|<p>Warning threshold of cluster memory utilization expressed in percent.</p>|`70`|
|{$VELOCLOUD.EDGE.CPU.UTIL.WARN}|<p>Warning threshold of cluster service CPU utilization expressed in percent.</p>|`80`|
|{$VELOCLOUD.EDGE.FREQUENCY}|<p>Update interval for raw item collection.</p>|`15m`|
|{$VELOCLOUD.EDGE.DATA.TIMEOUT}|<p>Response timeout for API.</p>|`15s`|
|{$VELOCLOUD.LLD.LINKS.NAME.FILTER.MATCHES}|<p>Filter for discoverable links.</p>|`.*`|
|{$VELOCLOUD.LLD.LINKS.NAME.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered links.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get edge peer data|<p>Edge peer metric data in JSON format.</p>|Script|velocloud.edge.get.peer.data|
|Get link metric data|<p>Link metric data in JSON format.</p>|HTTP agent|velocloud.edge.link.get.data|
|Get edge data|<p>Edge data in JSON format.</p>|HTTP agent|velocloud.edge.get.data|
|Get edge data collection errors|<p>Verify that data collection completed without errors.</p>|Dependent item|velocloud.edge.get.data.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Activation state|<p>Edge activation state.</p>|Dependent item|velocloud.edge.activation<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..activationState.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Description|<p>Edge description.</p>|Dependent item|velocloud.edge.description<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..description.first()`</p></li><li><p>Replace: `null -> `</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|HA state|<p>Edge high availability state.</p>|Dependent item|velocloud.edge.ha_state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..haState.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Model number|<p>Edge model number.</p>|Dependent item|velocloud.edge.model<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..modelNumber.first()`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Service uptime|<p>Edge service uptime.</p>|Dependent item|velocloud.edge.service_uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..serviceUpSince.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Software version|<p>Edge software version.</p>|Dependent item|velocloud.edge.software_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..softwareVersion.first()`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|State|<p>Edge state.</p>|Dependent item|velocloud.edge.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..edgeState.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|System uptime|<p>Edge system uptime.</p>|Dependent item|velocloud.edge.system_uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..systemUpSince.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get edge status metrics|<p>Edge status metrics in JSON format.</p>|HTTP agent|velocloud.edge.status.metric.get|
|Tunnel count|<p>Total number of active tunnels.</p>|Dependent item|velocloud.edge.tunnel.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tunnelCount.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory usage in percent|<p>Percentage of memory usage.</p>|Dependent item|velocloud.edge.memory.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memoryPct.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Flow count|<p>Number of flows.</p>|Dependent item|velocloud.edge.flow.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.flowCount.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU usage in percent|<p>CPU usage as a percentage.</p>|Dependent item|velocloud.edge.cpu.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpuPct.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU core temperature|<p>Temperature of the CPU core.</p>|Dependent item|velocloud.edge.cpu.core.temp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpuCoreTemp.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Handoff queue drops|<p>Packets dropped from the handoff queue.</p>|Dependent item|velocloud.edge.handoff.queue.drops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.handoffQueueDrops.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Tunnel count V6|<p>Total number of IPv6 tunnels.</p>|Dependent item|velocloud.edge.tunnel.count.v6<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tunnelCountV6.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Site country|<p>Edge site country.</p>|Script|velocloud.edge.site.country<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Site city|<p>Edge site city.</p>|Script|velocloud.edge.site.city<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Site state|<p>Edge site state.</p>|Script|velocloud.edge.site.state<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Site contact name|<p>Edge site contact name.</p>|Script|velocloud.edge.site.contact.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Site contact email|<p>Edge site contact email.</p>|Script|velocloud.edge.site.contact.email<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Site latitude|<p>Edge site location latitude.</p>|Script|velocloud.edge.site.latitude<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Site longitude|<p>Edge site location longitude.</p>|Script|velocloud.edge.site.longitude<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VeloCloud Edge: Failed to get metric data|<p>Failed to get API metrics for Edge.</p>|`length(last(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.get.data.error))>0`|Warning||
|VeloCloud Edge: HA state is in "FAILED" state|<p>High availability state is "FAILED".</p>|`last(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.ha_state)=3`|Warning||
|VeloCloud Edge: Edge is in "OFFLINE" state|<p>Edge state is "OFFLINE".</p>|`last(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.state)=0`|Warning||
|VeloCloud Edge: Edge has been restarted|<p>Edge was restarted.</p>|`last(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.system_uptime)>0 and last(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.system_uptime)<600`|Warning||
|VeloCloud Edge: High memory utilization|<p>The system is running out of free memory.</p>|`min(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.memory.usage,15m)>{$VELOCLOUD.EDGE.MEMORY.UTIL.WARN}`|Warning||
|VeloCloud Edge: High CPU utilization|<p>The system is experiencing high CPU usage.</p>|`min(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.cpu.usage,15m)>{$VELOCLOUD.EDGE.CPU.UTIL.WARN}`|Warning||

### LLD rule Link metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Link metric discovery|<p>Metrics for link statistics.</p>|Dependent item|velocloud.link.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Link metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Link [{#NAME}]:[{#IP}]: Raw data|<p>Raw data for this VeloCloud link.</p>|Dependent item|velocloud.get.link[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.linkId=='{#ID}')].first()`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Best latency rx, ms|<p>Best receive latency in milliseconds.</p>|Dependent item|velocloud.link.best_latency_rx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bestLatencyMsRx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Best latency tx, ms|<p>Best transmit latency in milliseconds.</p>|Dependent item|velocloud.link.best_latency_tx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bestLatencyMsTx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Best loss rx, %|<p>Best receive loss in percent.</p>|Dependent item|velocloud.link.best_loss_rx.pct[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bestLossPctRx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Best loss tx, %|<p>Best transmit loss in percent.</p>|Dependent item|velocloud.link.best_loss_tx.pct[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bestLossPctTx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Bytes in|<p>Received bytes for link.</p>|Dependent item|velocloud.link.bytes_rx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytesRx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Bytes out|<p>Transmitted bytes for link.</p>|Dependent item|velocloud.link.bytes_tx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytesTx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Last active|<p>Time since last activity for link, in seconds.</p>|Dependent item|velocloud.link.last_active[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.link.linkLastActive`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Packets in|<p>Received packets for link.</p>|Dependent item|velocloud.link.packets_rx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packetsRx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Packets out|<p>Transmitted packets for link.</p>|Dependent item|velocloud.link.packets_tx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packetsTx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: State|<p>Link state.</p>|Dependent item|velocloud.link.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.link.linkState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Total bytes|<p>Total bytes for link.</p>|Dependent item|velocloud.link.total_bytes[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalBytes`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Total packets|<p>Total packets for link.</p>|Dependent item|velocloud.link.total_packets[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalPackets`</p></li></ul>|

### Trigger prototypes for Link metric discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VeloCloud Edge: Link [{#NAME}]:[{#IP}]: Link state is not "STABLE"|<p>Link state is not "STABLE".</p>|`last(/VeloCloud SD-WAN Edge by HTTP/velocloud.link.state[{#ID}])<>1`|Warning||

### LLD rule SD-WAN peer metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN peer metric discovery|<p>Metrics for SD-WAN peers.</p>|Dependent item|velocloud.sdwan.peer.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.edgeSDWan`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SD-WAN peer metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN Peer [{#NAME}]:[{#TYPE}]: Raw data|<p>Raw data for VeloCloud SD-WAN peer.</p>|Dependent item|velocloud.get.sdwan.peer[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|SD-WAN Peer [{#NAME}]:[{#TYPE}]: Description|<p>Description of SD-WAN peer.</p>|Dependent item|velocloud.sdwan.peer.description[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.description`</p></li><li><p>Replace: `null -> `</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SD-WAN Peer [{#NAME}]:[{#TYPE}]: Stable path|<p>Number of stable paths for SD-WAN peer.</p>|Dependent item|velocloud.sdwan.peer.stable_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.stable`</p></li></ul>|
|SD-WAN Peer [{#NAME}]:[{#TYPE}]: Unstable path|<p>Number of unstable paths for SD-WAN peer.</p>|Dependent item|velocloud.sdwan.peer.unstable_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.unstable`</p></li></ul>|
|SD-WAN Peer [{#NAME}]:[{#TYPE}]: Standby path|<p>Number of standby paths for SD-WAN peer.</p>|Dependent item|velocloud.sdwan.peer.standby_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.standby`</p></li></ul>|
|SD-WAN Peer [{#NAME}]:[{#TYPE}]: Dead path|<p>Number of dead paths for SD-WAN peer.</p>|Dependent item|velocloud.sdwan.peer.dead_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.dead`</p></li></ul>|
|SD-WAN Peer [{#NAME}]:[{#TYPE}]: Unknown path|<p>Number of unknown paths for SD-WAN peer.</p>|Dependent item|velocloud.sdwan.peer.unknown_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.unknown`</p></li></ul>|
|SD-WAN Peer [{#NAME}]:[{#TYPE}]: Total path|<p>Number of total paths for SD-WAN peer.</p>|Dependent item|velocloud.sdwan.peer.total_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.total`</p></li></ul>|

### LLD rule SD-WAN peer path metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN peer path metric discovery|<p>Metrics for SD-WAN peer paths.</p>|Dependent item|velocloud.sdwan.path.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.edgeSDWanPath`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SD-WAN peer path metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Raw data|<p>Raw data for VeloCloud SD-WAN peer path.</p>|Dependent item|velocloud.get.sdwan_path[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Bytes in|<p>Bytes received for SD-WAN peer path.</p>|Dependent item|velocloud.sdwan.path.bytes_rx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.bytesRx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Bytes out|<p>Bytes transmitted for SD-WAN peer path.</p>|Dependent item|velocloud.sdwan.path.bytes_tx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.bytesTx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Bytes total|<p>Total bytes for SD-WAN peer path.</p>|Dependent item|velocloud.sdwan.path.total_bytes[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.totalBytes`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packets in|<p>Packets received for SD-WAN peer path.</p>|Dependent item|velocloud.sdwan.path.packets_rx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetsRx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packets out|<p>Packets transmitted for SD-WAN peer path.</p>|Dependent item|velocloud.sdwan.path.packets_tx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetsTx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Total packets|<p>Total packets for SD-WAN peer path.</p>|Dependent item|velocloud.sdwan.path.total_packets[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.totalPackets`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packet loss in|<p>Received packet loss for SD-WAN peer path.</p>|Dependent item|velocloud.sdwan.path.packet_loss_rx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetLossRx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packet loss out|<p>Transmitted packet loss for SD-WAN peer path.</p>|Dependent item|velocloud.sdwan.path.packet_loss_tx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetLossTx`</p></li></ul>|

# VeloCloud SD-WAN by HTTP

## Overview

This template is designed for the effortless deployment of VeloCloud SD-WAN monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- VeloCloud SD-WAN Orchestrator 6.4.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

- Set the `{$VELOCLOUD.TOKEN}` and `{$VELOCLOUD.URL}` macros.

- Create an API token in the VeloCloud SD-WAN Orchestrator and use it in the `{$VELOCLOUD.TOKEN}` macros. See [Arista documentation](https://www.arista.com/en/global-settings-guide-vc-6-4/sase-6-4-user-management) for details.

- Set the Orchestrator URL for `{$VELOCLOUD.URL}`, e.g., `example.com`, where you replace "example.com" with the actual URL the Orchestrator is running on.

- Set the `{$VELOCLOUD.SDWAN.FREQUENCY}` macro to define how often data should be collected from the VeloCloud Edge device (default is 15m). See [Arista API Rate Limiting and Throttling documentation](https://arista.my.site.com/AristaCommunity/s/article/API-Fair-Usage-Policy-for-Arista-VeloCloud-Orchestrator) for details on adjusting frequency.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VELOCLOUD.TOKEN}|<p>VeloCloud SD-WAN Orchestrator API token.</p>||
|{$VELOCLOUD.URL}|<p>VeloCloud SD-WAN Orchestrator URL, e.g., `vco.velocloud.net`.</p>||
|{$VELOCLOUD.ENTERPRISE.ID}|<p>VeloCloud SD-WAN Enterprise ID. Specify a single Enterprise ID (requires READ EDGE privileges). By default parameter is empty, data will be retrieved for all Enterprises (privileges required: READ ENTERPRISE and READ EDGE).</p>||
|{$VELOCLOUD.SDWAN.FREQUENCY}|<p>Update interval for raw item, expressed in hours.</p>|`1h`|
|{$VELOCLOUD.SDWAN.DATA.TIMEOUT}|<p>Response timeout for API.</p>|`15s`|
|{$VELOCLOUD.HTTP_PROXY}|<p>The HTTP proxy for script items (set if needed). If the macro is empty, then no proxy is used.</p>||
|{$VELOCLOUD.LLD.EDGES.NAME.FILTER.MATCHES}|<p>Filter for discoverable Edges by name.</p>|`.*`|
|{$VELOCLOUD.LLD.EDGES.NAME.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered Edges by name.</p>|`CHANGE_IF_NEEDED`|
|{$VELOCLOUD.LLD.EDGES.STATE.FILTER.MATCHES}|<p>Filter for discoverable Edges by state.</p>|`.*`|
|{$VELOCLOUD.LLD.EDGES.STATE.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered Edges by state.</p>|`CHANGE_IF_NEEDED`|
|{$VELOCLOUD.LLD.GATEWAYS.FILTER.MATCHES}|<p>Filter for discoverable gateways.</p>|`.*`|
|{$VELOCLOUD.LLD.GATEWAYS.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered gateways.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>JSON result from VeloCloud API requests.</p>|Script|velocloud.get|
|Get network gateways|<p>Gets network gateway information.</p>|HTTP agent|velocloud.network.gateway.get<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get version info data|<p>Gets system version information.</p>|HTTP agent|velocloud.version.info.get<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get version info errors|<p>Errors from version info response.</p>|Dependent item|velocloud.info.get.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Orchestrator API version|<p>Version of VeloCloud SD-WAN Orchestrator API.</p>|Dependent item|velocloud.orchestrator.api_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.apiVersion`</p></li></ul>|
|Orchestrator build|<p>Build of VeloCloud SD-WAN Orchestrator API.</p>|Dependent item|velocloud.orchestrator.build<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.build`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Orchestrator version|<p>Version of VMware SD-WAN Orchestrator API.</p>|Dependent item|velocloud.orchestrator.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get data collection errors|<p>Errors from aggregate data collection.</p>|Dependent item|velocloud.get.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get network gateway data collection errors|<p>Errors from aggregate data collection for network gateways.</p>|Dependent item|velocloud.get.edges.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|System properties|<p>System properties of VMware SD-WAN.</p>|HTTP agent|velocloud.system.properties<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VeloCloud: There are errors in version info item|<p>There are errors in the version info item.</p>|`length(last(/VeloCloud SD-WAN by HTTP/velocloud.info.get.error))>0`|Warning||
|VeloCloud: Orchestrator API version has changed|<p>The VeloCloud Orchestrator API version has changed.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.api_version,#1)<>last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.api_version,#2) and length(last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.api_version))>0`|Average|**Manual close**: Yes|
|VeloCloud: Orchestrator build has changed|<p>The VeloCloud Orchestrator build has changed.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.build,#1)<>last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.build,#2) and length(last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.build))>0`|Info|**Manual close**: Yes|
|VeloCloud: Orchestrator version has changed|<p>The VeloCloud Orchestrator version has changed.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.version,#1)<>last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.version,#2) and length(last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.version))>0`|Info|**Manual close**: Yes|
|VeloCloud: There are errors in aggregate script item|<p>There are errors in the aggregate script item.</p>|`length(last(/VeloCloud SD-WAN by HTTP/velocloud.get.error))>0`|Warning||
|VeloCloud: There are errors in network gateways item|<p>There are errors in the aggregate script item for network gateways.</p>|`length(last(/VeloCloud SD-WAN by HTTP/velocloud.get.edges.error))>0`|Warning||
|VeloCloud: System properties have changed|<p>The system properties have changed.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.system.properties,#1)<>last(/VeloCloud SD-WAN by HTTP/velocloud.system.properties,#2)`|Info|**Manual close**: Yes|

### LLD rule Edge discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Edge discovery|<p>Get Edge instances.</p>|Dependent item|velocloud.edge.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.edges`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Gateway metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Gateway metric discovery|<p>Metrics for gateway statistics.</p>|Dependent item|velocloud.gateway.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Gateway metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Gateway [{#NAME}]: Raw data|<p>Raw data for VeloCloud gateway.</p>|Dependent item|velocloud.get.gateway[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id=='{#ID}')].first()`</p></li></ul>|
|Gateway [{#NAME}]: Connected edges|<p>Edges connected to gateway.</p>|Dependent item|velocloud.gateway.connected_edges[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connectedEdges`</p></li></ul>|
|Gateway [{#NAME}]: Description|<p>Gateway description.</p>|Dependent item|velocloud.gateway.description[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.description`</p></li><li><p>Replace: `null -> `</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Gateway [{#NAME}]: IP address|<p>Gateway IP address.</p>|Dependent item|velocloud.gateway.ip_address[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ipAddress`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Gateway [{#NAME}]: Service uptime|<p>Gateway service uptime.</p>|Dependent item|velocloud.gateway.service_uptime[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serviceUpSince`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Gateway [{#NAME}]: State|<p>Gateway state.</p>|Dependent item|velocloud.gateway.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.gatewayState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Gateway [{#NAME}]: System uptime|<p>Gateway system uptime.</p>|Dependent item|velocloud.gateway.system_uptime[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemUpSince`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Gateway [{#NAME}]: Utilization CPU|<p>Gateway CPU utilization.</p>|Dependent item|velocloud.gateway.utilization.cpu[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.utilizationDetail.cpu`</p></li></ul>|
|Gateway [{#NAME}]: Utilization load|<p>Gateway load.</p>|Dependent item|velocloud.gateway.utilization.load[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.utilizationDetail.load`</p></li></ul>|
|Gateway [{#NAME}]: Utilization memory|<p>Gateway memory utilization.</p>|Dependent item|velocloud.gateway.utilization.memory[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.utilizationDetail.memory`</p></li></ul>|
|Gateway [{#NAME}]: Utilization overall|<p>Gateway overall utilization.</p>|Dependent item|velocloud.gateway.utilization.overall[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.utilizationDetail.overall`</p></li></ul>|

### Trigger prototypes for Gateway metric discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VeloCloud: Gateway [{#NAME}]: The number of connected edges has changed|<p>The number of connected Edges has changed.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.gateway.connected_edges[{#ID}],#1)<>last(/VeloCloud SD-WAN by HTTP/velocloud.gateway.connected_edges[{#ID}],#2)`|Warning|**Manual close**: Yes|
|VeloCloud: Gateway [{#NAME}]: Gateway has been restarted|<p>Gateway was restarted.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.gateway.system_uptime[{#ID}])>0 and last(/VeloCloud SD-WAN by HTTP/velocloud.gateway.system_uptime[{#ID}])<600`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

