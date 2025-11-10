
# VeloCloud SD-WAN Edge by HTTP

## Overview

This template is designed for the effortless deployment of VeloCloud SD-WAN Edge monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- VeloCloud Orchestrator 6.4.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

You must set {$VELOCLOUD.TOKEN} and {$VELOCLOUD.URL} macros.

You have to create API token in Orchestrator and use it in {$VELOCLOUD.TOKEN} macros. Read detailed instructions how to create token in Arista documentation [documentation](https://www.arista.com/en/global-settings-guide-vc-6-4/sase-6-4-user-management)

Set Orchestrator URl for {$VELOCLOUD.URL}. e.g. example.com (where you replace example.com with the actual url VeloCloud SD-WAN Orchestrator is running on)

Set {$VELOCLOUD.EDGE.FREQUENCY}' macro to define how often data should be collected from the edge. Default is 15m. Read API Rate Limiting and Throttling documentation from Arista to adjust frequency if needed: [documentation](https://arista.my.site.com/AristaCommunity/s/article/API-Fair-Usage-Policy-for-Arista-VeloCloud-Orchestrator)


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VELOCLOUD.TOKEN}|<p>API token for VeloCloud Orchestrator.</p>||
|{$VELOCLOUD.URL}|<p>URL for VeloCloud Orchestrator. e.g. example.com (where you replace example.com with the actual url VeloCloud Orchestrator is running on)</p>||
|{$VELOCLOUD.TIMEOUT}|<p>Timeout for API requests.</p>|`30s`|
|{$VELOCLOUD.ENTERPRISE.ID}|<p>SD-WAN Enterprise id.</p>||
|{$VELOCLOUD.EDGE.ID}|<p>SD-WAN Edge id.</p>||
|{$VELOCLOUD.EDGE.MEMORY.UTIL.WARN}|<p>The warning threshold of the cluster memory utilization expressed in %.</p>|`70`|
|{$VELOCLOUD.EDGE.CPU.UTIL.WARN}|<p>The warning threshold of the cluster service CPU utilization expressed in %.</p>|`80`|
|{$VELOCLOUD.EDGE.FREQUENCY}|<p>Update interval for the raw item.</p>|`15m`|
|{$VELOCLOUD.EDGE.DATA.TIMEOUT}|<p>Response timeout for an API.</p>|`15s`|
|{$VELOCLOUD.LLD.LINKS.FILTER.MATCHES}|<p>Filter for discoverable links.</p>|`.*`|
|{$VELOCLOUD.LLD.LINKS.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered links.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get edge sdwan data|<p>The JSON with result of Velocloud API requests.</p>|Script|velocloud.edge.get.sdwan.data|
|Get links metric data|<p>Links metrics data in JSON format.</p>|HTTP agent|velocloud.edge.link.get.data|
|Get edge data|<p>Edge data in JSON format.</p>|HTTP agent|velocloud.edge.get.data|
|Get edges data collection errors|<p>Check result of the edge metric data has been got correctly.</p>|Dependent item|velocloud.edge.get.data.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Activation state|<p>Edge activation state.</p>|Dependent item|velocloud.edge.activation<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..activationState.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Description|<p>Edge description.</p>|Dependent item|velocloud.edge.description<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..description.first()`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|HA state|<p>Edge high availability state.</p>|Dependent item|velocloud.edge.ha_state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..haState.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Model number|<p>Edge model number.</p>|Dependent item|velocloud.edge.model<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..modelNumber.first()`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Service uptime|<p>Edge service uptime.</p>|Dependent item|velocloud.edge.service_uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..serviceUpSince.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Software version|<p>Edge software version.</p>|Dependent item|velocloud.edge.software_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..softwareVersion.first()`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|State|<p>Edge state.</p>|Dependent item|velocloud.edge.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..edgeState.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|System uptime|<p>Edge system uptime.</p>|Dependent item|velocloud.edge.system_uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..systemUpSince.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get edge status metrics|<p>Edge status metrics in JSON format.</p>|HTTP agent|velocloud.edge.status.metric.get|
|Tunnel count|<p>Total number of active tunnels.</p>|Dependent item|velocloud.edge.tunnel.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tunnelCount.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory usage in percents|<p>Percentage of memory usage.</p>|Dependent item|velocloud.edge.memory.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memoryPct.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Flow count|<p>Count of flows.</p>|Dependent item|velocloud.edge.flow.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.flowCount.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU usage in percentage|<p>CPU usage as a percentage.</p>|Dependent item|velocloud.edge.cpu.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpuPct.max`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
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
|VeloCloud Edge: Failed to get metrics data|<p>Failed to get API metrics for edge.</p>|`length(last(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.get.data.error))>0`|Warning||
|VeloCloud Edge: HA state is in "FAILED" state|<p>High availability state is "FAILED".</p>|`last(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.ha_state)=3`|Warning||
|VeloCloud Edge: Edge is in "OFFLINE" state|<p>Edge state is "OFFLINE".</p>|`last(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.state)=0`|Warning||
|VeloCloud Edge: Edge has been restarted|<p>Edge was restarted.</p>|`last(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.system_uptime)>0 and last(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.system_uptime)<600`|Warning||
|VeloCloud Edge: High memory utilization|<p>The system is running out of free memory.</p>|`min(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.memory.usage,15m)>{$VELOCLOUD.EDGE.MEMORY.UTIL.WARN}`|Warning||
|VeloCloud Edge: High CPU utilization|<p>The system is experiencing high CPU usage.</p>|`min(/VeloCloud SD-WAN Edge by HTTP/velocloud.edge.cpu.usage,15m)>{$VELOCLOUD.EDGE.CPU.UTIL.WARN}`|Warning||

### LLD rule Link metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Link metric discovery|<p>Metrics for links statistics.</p>|Dependent item|velocloud.link.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Link metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Link [{#NAME}]:[{#IP}]: Raw data|<p>Raw data for velocloud link.</p>|Dependent item|velocloud.get.link[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.linkId=='{#ID}')].first()`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Best latency rx, ms|<p>Link receive best latency.</p>|Dependent item|velocloud.link.best_latency_rx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bestLatencyMsRx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Best latency tx, ms|<p>Link transmit best loss.</p>|Dependent item|velocloud.link.best_latency_tx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bestLatencyMsTx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Best loss rx, %|<p>Link receive best loss.</p>|Dependent item|velocloud.link.best_loss_rx.pct[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bestLossPctRx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Best loss tx, %|<p>Link transmit best loss.</p>|Dependent item|velocloud.link.best_loss_tx.pct[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bestLossPctTx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Bytes in|<p>Link received bytes.</p>|Dependent item|velocloud.link.bytes_rx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytesRx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Bytes out|<p>Link transmitted bytes.</p>|Dependent item|velocloud.link.bytes_tx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytesTx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Last active|<p>Link last active in seconds ago.</p>|Dependent item|velocloud.link.last_active[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.link.linkLastActive`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Packets in|<p>Link received packets.</p>|Dependent item|velocloud.link.packets_rx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packetsRx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Packets out|<p>Link transmitted packets.</p>|Dependent item|velocloud.link.packets_tx[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packetsTx`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: State|<p>Link state.</p>|Dependent item|velocloud.link.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.link.linkState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Total bytes|<p>Link Total bytes.</p>|Dependent item|velocloud.link.total_bytes[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalBytes`</p></li></ul>|
|Link [{#NAME}]:[{#IP}]: Total packets|<p>Link total packets.</p>|Dependent item|velocloud.link.total_packets[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalPackets`</p></li></ul>|

### Trigger prototypes for Link metric discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VeloCloud Edge: Link [{#NAME}]:[{#IP}]: Link state is not "STABLE"|<p>Link state is not "STABLE".</p>|`last(/VeloCloud SD-WAN Edge by HTTP/velocloud.link.state[{#ID}])<>1`|Warning||

### LLD rule SDWAN peers metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SDWAN peers metrics discovery|<p>Metrics for SDWAN peers.</p>|Dependent item|velocloud.sdwanpeer.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.edgeSDWan`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SDWAN peers metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SDWAN Peer [{#NAME}]:[{#TYPE}]: Raw data|<p>Raw data for velocloud sdwan peer.</p>|Dependent item|velocloud.get.sdwanpeer[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|SDWAN Peer [{#NAME}]:[{#TYPE}]: Description|<p>Description of SDWAN peer.</p>|Dependent item|velocloud.sdwanpeer.description[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.description`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SDWAN Peer [{#NAME}]:[{#TYPE}]: Stable path|<p>Count of stable path of SDWAN peer.</p>|Dependent item|velocloud.sdwanpeer.stable_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.stable`</p></li></ul>|
|SDWAN Peer [{#NAME}]:[{#TYPE}]: Unstable path|<p>Count of unstable path of SDWAN peer.</p>|Dependent item|velocloud.sdwanpeer.unstable_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.unstable`</p></li></ul>|
|SDWAN Peer [{#NAME}]:[{#TYPE}]: Standby path|<p>Count of standby path of SDWAN peer.</p>|Dependent item|velocloud.sdwanpeer.standby_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.standby`</p></li></ul>|
|SDWAN Peer [{#NAME}]:[{#TYPE}]: Dead path|<p>Count of dead path of SDWAN peer.</p>|Dependent item|velocloud.sdwanpeer.dead_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.dead`</p></li></ul>|
|SDWAN Peer [{#NAME}]:[{#TYPE}]: Unknown path|<p>Count of unknown path of SDWAN peer.</p>|Dependent item|velocloud.sdwanpeer.unknown_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.unknown`</p></li></ul>|
|SDWAN Peer [{#NAME}]:[{#TYPE}]: Total path|<p>Count of total path of SDWAN peer.</p>|Dependent item|velocloud.sdwanpeer.total_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pathStatusCount.total`</p></li></ul>|

### LLD rule SDWAN peers path metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SDWAN peers path metrics discovery|<p>Metrics for SDWAN peers path.</p>|Dependent item|velocloud.sdwanpath.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.edgeSDWanPath`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SDWAN peers path metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Raw data|<p>Raw data for velocloud sdwan peer path.</p>|Dependent item|velocloud.get.sdwan_path[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Bytes in|<p>Bytes received of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.bytes_rx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.bytesRx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Bytes out|<p>Bytes transmitted of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.bytes_tx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.bytesTx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Bytes total|<p>Total bytes of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.total_bytes[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.totalBytes`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packets in|<p>Packets received of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.packets_rx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetsRx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packets out|<p>Packets transmitted of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.packets_tx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetsTx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Total packets|<p>Total packets of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.total_packets[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.totalPackets`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packet loss in|<p>Received packet loss of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.packet_loss_rx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetLossRx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packet loss out|<p>Transmitted packet loss of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.packet_loss_tx[{#NAME}/{#SOURCE}/{#DESTINATION}/{#LINK.LOGICAL.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetLossTx`</p></li></ul>|

# VeloCloud SD-WAN by HTTP

## Overview

This template is designed for the effortless deployment of VeloCloud SD-WAN monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- VeloCloud Orchestrator 6.4.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

You must set {$VELOCLOUD.TOKEN} and {$VELOCLOUD.URL} macros.

You have to create API token in Orchestrator and use it in {$VELOCLOUD.TOKEN} macros. Read detailed instructions how to create token in Arista documentation [documentation](https://www.arista.com/en/global-settings-guide-vc-6-4/sase-6-4-user-management)

Set Orchestrator URl for {$VELOCLOUD.URL}. e.g. example.com (where you replace example.com with the actual url VeloCloud SD-WAN Orchestrator is running on)

Change {$VELOCLOUD.EDGE.FREQUENCY}' macro to define how often data should be collected from the API. Default is 1h.

Read API Rate Limiting and Throttling documentation from Arista to adjust frequency if needed: [documentation](https://arista.my.site.com/AristaCommunity/s/article/API-Fair-Usage-Policy-for-Arista-VeloCloud-Orchestrator)


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VELOCLOUD.TOKEN}|<p>VeloCloud SD-WAN Orchestrator API Token.</p>||
|{$VELOCLOUD.URL}|<p>VeloCloud SD-WAN Orchestrator URL. e.g vco.velocloud.net.</p>||
|{$VELOCLOUD.ENTERPRISE.ID}|<p>VeloCloud SD-WAN Enterprise id.</p>||
|{$VELOCLOUD.SDWAN.FREQUENCY}|<p>Update interval for the raw item, expressed in hours.</p>|`1h`|
|{$VELOCLOUD.SDWAN.DATA.TIMEOUT}|<p>Response timeout for an API.</p>|`15s`|
|{$VELOCLOUD.HTTP_PROXY}|<p>The HTTP proxy for script items (set if needed). If the macro is empty, then no proxy is used.</p>||
|{$VELOCLOUD.LLD.EDGES.NAME.FILTER.MATCHES}|<p>Filter for discoverable edges by name.</p>|`.*`|
|{$VELOCLOUD.LLD.EDGES.NAME.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered edges by name.</p>|`CHANGE_IF_NEEDED`|
|{$VELOCLOUD.LLD.EDGES.STATE.FILTER.MATCHES}|<p>Filter for discoverable edges by state.</p>|`.*`|
|{$VELOCLOUD.LLD.EDGES.STATE.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered edges by state.</p>|`CHANGE_IF_NEEDED`|
|{$VELOCLOUD.LLD.EDGES.FILTER.MATCHES}|<p>Filter for discoverable edges.</p>|`.*`|
|{$VELOCLOUD.LLD.EDGES.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered edges.</p>|`CHANGE_IF_NEEDED`|
|{$VELOCLOUD.LLD.GATEWAYS.FILTER.MATCHES}|<p>Filter for discoverable gateways.</p>|`.*`|
|{$VELOCLOUD.LLD.GATEWAYS.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered gateways.</p>|`CHANGE_IF_NEEDED`|
|{$VELOCLOUD.LLD.LINKS.FILTER.MATCHES}|<p>Filter for discoverable links.</p>|`.*`|
|{$VELOCLOUD.LLD.LINKS.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered links.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The JSON with result of Velocloud API requests.</p>|Script|velocloud.get|
|Get network gateways|<p>Gets network gateways information.</p>|HTTP agent|velocloud.network.gateways.get<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get version info data|<p>Gets system version information.</p>|HTTP agent|velocloud.version.info.get<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get version info errors|<p>Errors of response in version info item.</p>|Dependent item|velocloud.info.get.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Orchestrator API version|<p>Version of VMware SD-WAN Orchestrator API.</p>|Dependent item|velocloud.orchestrator.api_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.apiVersion`</p></li></ul>|
|Orchestrator build|<p>Build of VMware SD-WAN Orchestrator API.</p>|Dependent item|velocloud.orchestrator.build<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.build`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Orchestrator version|<p>Version of VMware SD-WAN Orchestrator API.</p>|Dependent item|velocloud.orchestrator.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get data collection errors|<p>Errors of aggregate script item.</p>|Dependent item|velocloud.get.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get network gateways data collection errors|<p>Errors of aggregate script item.</p>|Dependent item|velocloud.get.edges.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|System properties|<p>System properties of VMware SD-WAN.</p>|HTTP agent|velocloud.system.properties<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VeloCloud: There are errors in version info item|<p>There are errors in version info item.</p>|`length(last(/VeloCloud SD-WAN by HTTP/velocloud.info.get.error))>0`|Warning||
|VeloCloud: Orchestrator API version has been changed|<p>Velocloud Orchestrator API version has been changed.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.api_version,#1)<>last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.api_version,#2) and length(last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.api_version))>0`|Average|**Manual close**: Yes|
|VeloCloud: Orchestrator build has been changed|<p>Velocloud Orchestrator build has been changed.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.build,#1)<>last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.build,#2) and length(last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.build))>0`|Info|**Manual close**: Yes|
|VeloCloud: Orchestrator version has been changed|<p>Velocloud Orchestrator version has been changed.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.version,#1)<>last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.version,#2) and length(last(/VeloCloud SD-WAN by HTTP/velocloud.orchestrator.version))>0`|Info|**Manual close**: Yes|
|VeloCloud: There are errors in aggregate script item|<p>There are errors in aggregate script item.</p>|`length(last(/VeloCloud SD-WAN by HTTP/velocloud.get.error))>0`|Warning||
|VeloCloud: There are error in network gateways item|<p>There are errors in aggregate script item.</p>|`length(last(/VeloCloud SD-WAN by HTTP/velocloud.get.edges.error))>0`|Warning||
|VeloCloud: System properties have changed|<p>System properties have changed.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.system.properties,#1)<>last(/VeloCloud SD-WAN by HTTP/velocloud.system.properties,#2)`|Info|**Manual close**: Yes|

### LLD rule Edges discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Edges discovery|<p>Get edges instances.</p>|Dependent item|velocloud.edges.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.edges`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Gateways metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Gateways metrics discovery|<p>Metrics for gateways statistics.</p>|Dependent item|velocloud.gateways.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Gateways metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Gateway [{#NAME}]: Raw data|<p>Raw data for velocloud gateway.</p>|Dependent item|velocloud.get.gateway[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id=='{#ID}')].first()`</p></li></ul>|
|Gateway [{#NAME}]: Connected edges|<p>Gateway connected edges.</p>|Dependent item|velocloud.gateway.connected_edges[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connectedEdges`</p></li></ul>|
|Gateway [{#NAME}]: Description|<p>Gateway description.</p>|Dependent item|velocloud.gateway.description[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.description`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Gateway [{#NAME}]: IP address|<p>Gateway ip address.</p>|Dependent item|velocloud.gateway.ip_address[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ipAddress`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Gateway [{#NAME}]: Service uptime|<p>Gateway service uptime.</p>|Dependent item|velocloud.gateway.service_uptime[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serviceUpSince`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Gateway [{#NAME}]: State|<p>Gateway state.</p>|Dependent item|velocloud.gateway.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.gatewayState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Gateway [{#NAME}]: System uptime|<p>Gateway system uptime.</p>|Dependent item|velocloud.gateway.system_uptime[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemUpSince`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Gateway [{#NAME}]: Utilization CPU|<p>Gateway CPU utilization.</p>|Dependent item|velocloud.gateway.utilization.cpu[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.utilizationDetail.cpu`</p></li></ul>|
|Gateway [{#NAME}]: Utilization load|<p>Gateway load.</p>|Dependent item|velocloud.gateway.utilization.load[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.utilizationDetail.load`</p></li></ul>|
|Gateway [{#NAME}]: Utilization memory|<p>Gateway memory utilization.</p>|Dependent item|velocloud.gateway.utilization.memory[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.utilizationDetail.memory`</p></li></ul>|
|Gateway [{#NAME}]: Utilization overall|<p>Gateway overall utilization.</p>|Dependent item|velocloud.gateway.utilization.overall[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.utilizationDetail.overall`</p></li></ul>|

### Trigger prototypes for Gateways metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VeloCloud: Gateway [{#NAME}]: The number of connected edges is changed|<p>The number of connected edges is changed.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.gateway.connected_edges[{#ID}],#1)<>last(/VeloCloud SD-WAN by HTTP/velocloud.gateway.connected_edges[{#ID}],#2)`|Warning|**Manual close**: Yes|
|VeloCloud: Gateway [{#NAME}]: Gateway has been restarted|<p>Gateway was restarted.</p>|`last(/VeloCloud SD-WAN by HTTP/velocloud.gateway.system_uptime[{#ID}])>0 and last(/VeloCloud SD-WAN by HTTP/velocloud.gateway.system_uptime[{#ID}])<600`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

