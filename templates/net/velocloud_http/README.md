
# VMWare SD-WAN VeloCloud by HTTP

## Overview

This template is designed for the effortless deployment of VMWare SD-WAN VeloCloud monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- VMware SD-WAN Orchestrator 4.0.2 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

You must set {$VELOCLOUD.TOKEN} and {$VELOCLOUD.URL} macros. 

You have to create API token in Orchestrator and use it in {$VELOCLOUD.TOKEN} macros. Read detailed instructions how to create token in VMWare documentation [documentation](https://docs.vmware.com/en/VMware-SD-WAN/4.0/vmware-sd-wan-operator-guide/GUID-C150D536-A75F-47C1-8AFF-17C417F40C1D.html)

Set Orchestrator URl for {$VELOCLOUD.URL}. e.g. example.com (where you replace example.com with the actual url VMWare SD-WAN Orchestrator is running on)


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VELOCLOUD.TOKEN}|<p>VMware SD-WAN Orchestrator API Token.</p>||
|{$VELOCLOUD.URL}|<p>VMware SD-WAN Orchestrator URL. e.g vco.velocloud.net.</p>||
|{$VELOCLOUD.LLD.EDGES.FILTER.MATCHES}|<p>Filter for discoverable edges.</p>|`.*`|
|{$VELOCLOUD.LLD.EDGES.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered edges.</p>|`CHANGE_IF_NEEDED`|
|{$VELOCLOUD.LLD.GATEWAYS.FILTER.MATCHES}|<p>Filter for discoverable gateways.</p>|`.*`|
|{$VELOCLOUD.LLD.GATEWAYS.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered gateways.</p>|`CHANGE_IF_NEEDED`|
|{$VELOCLOUD.LLD.LINKS.FILTER.MATCHES}|<p>Filter for discoverable links.</p>|`.*`|
|{$VELOCLOUD.LLD.LINKS.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered links.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Velocloud: Get data|<p>The JSON with result of Velocloud API requests.</p>|Script|velocloud.get|
|Velocloud: Clear data|<p>Clear metrics for data without errors.</p>|Dependent item|velocloud.get.clear_metrics<p>**Preprocessing**</p><ul><li><p>Check for error in JSON: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Velocloud: Orchestrator API version|<p>Version of VMware SD-WAN Orchestrator API.</p>|Dependent item|velocloud.orchestrator.api_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.info.apiVersion`</p></li></ul>|
|Velocloud: Orchestrator build|<p>Build of VMware SD-WAN Orchestrator API.</p>|Dependent item|velocloud.orchestrator.build<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.info.build`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Velocloud: Orchestrator version|<p>Version of VMware SD-WAN Orchestrator API.</p>|Dependent item|velocloud.orchestrator.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.info.version`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Velocloud: Get data collection errors|<p>Errors of aggregate script item.</p>|Dependent item|velocloud.get.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Velocloud: System properties|<p>System properties of VMware SD-WAN.</p>|HTTP agent|velocloud.system.properties<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Velocloud: Failed to fetch aggregate data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/VMWare SD-WAN VeloCloud by HTTP/velocloud.orchestrator.api_version,30m)=1`|Average|**Manual close**: Yes|
|Velocloud: Orchestrator build has been changed|<p>Velocloud Orchestrator build has been changed.</p>|`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.orchestrator.build,#1)<>last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.orchestrator.build,#2) and length(last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.orchestrator.build))>0`|Info|**Manual close**: Yes|
|Velocloud: Orchestrator version has been changed|<p>Velocloud Orchestrator version has been changed.</p>|`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.orchestrator.version,#1)<>last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.orchestrator.version,#2) and length(last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.orchestrator.version))>0`|Info|**Manual close**: Yes|
|Velocloud: There are errors in aggregate script item|<p>There are errors in aggregate script item.</p>|`length(last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.get.error))>0`|Warning||
|Velocloud: System properties have changed|<p>System properties have changed.</p>|`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.system.properties,#1)<>last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.system.properties,#2)`|Info|**Manual close**: Yes|

### LLD rule Edges metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Edges metrics discovery|<p>Metrics for edges statistics.</p>|Dependent item|velocloud.edges.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.edges`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Edges metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Edge [{#NAME}]: Raw data|<p>Raw data for velocloud edge.</p>|Dependent item|velocloud.get.edge[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.edges[?(@.id=='{#ID}')].first()`</p></li></ul>|
|Edge [{#NAME}]: Activation state|<p>Edge activation state.</p>|Dependent item|velocloud.edge.activation[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.activationState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Edge [{#NAME}]: Description|<p>Edge description.</p>|Dependent item|velocloud.edge.description[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.description`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Edge [{#NAME}]: HA state|<p>Edge high availability state.</p>|Dependent item|velocloud.edge.ha_state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.haState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Edge [{#NAME}]: Model number|<p>Edge model number.</p>|Dependent item|velocloud.edge.model[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.modelNumber`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Edge [{#NAME}]: Service uptime|<p>Edge service uptime.</p>|Dependent item|velocloud.edge.service_uptime[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serviceUpSince`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Edge [{#NAME}]: Software version|<p>Edge software version.</p>|Dependent item|velocloud.edge.software_version[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.softwareVersion`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Edge [{#NAME}]: State|<p>Edge state.</p>|Dependent item|velocloud.edge.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.edgeState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Edge [{#NAME}]: System uptime|<p>Edge system uptime.</p>|Dependent item|velocloud.edge.system_uptime[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemUpSince`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Edges metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Edge [{#NAME}]: HA state is in "FAILED" state|<p>High availability state is "FAILED".</p>|`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.edge.ha_state[{#ID}])=3`|Warning||
|Edge [{#NAME}]: Edge is in "OFFLINE" state|<p>Edge state is "OFFLINE".</p>|`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.edge.state[{#ID}])=0`|Warning||
|Edge [{#NAME}]: Edge has been restarted|<p>Edge was restarted.</p>|`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.edge.system_uptime[{#ID}])>0 and last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.edge.system_uptime[{#ID}])<600`|Warning||

### LLD rule Gateways metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Gateways metrics discovery|<p>Metrics for gateways statistics.</p>|Dependent item|velocloud.gateways.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.gateways`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Gateways metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Gateway [{#NAME}]: Raw data|<p>Raw data for velocloud gateway.</p>|Dependent item|velocloud.get.gateway[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.gateways[?(@.id=='{#ID}')].first()`</p></li></ul>|
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
|Gateway [{#NAME}]: The number of connected edges is changed|<p>The number of connected edges is changed.</p>|`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.gateway.connected_edges[{#ID}],#1)<>last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.gateway.connected_edges[{#ID}],#2)`|Warning|**Manual close**: Yes|
|Gateway [{#NAME}]: Gateway has been restarted|<p>Gateway was restarted.</p>|`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.gateway.system_uptime[{#ID}])>0 and last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.gateway.system_uptime[{#ID}])<600`|Warning||

### LLD rule Links metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Links metrics discovery|<p>Metrics for links statistics.</p>|Dependent item|velocloud.links.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.links`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Links metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Link [{#NAME}]:[{#IP}]: Raw data|<p>Raw data for velocloud link.</p>|Dependent item|velocloud.get.link[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.links[?(@.linkId=='{#ID}')].first()`</p></li></ul>|
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

### Trigger prototypes for Links metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Link [{#NAME}]:[{#IP}]: Link state is not "STABLE"|<p>Link state is not "STABLE".</p>|`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.link.state[{#ID}])<>1`|Warning||

### LLD rule SDWAN peers metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SDWAN peers metrics discovery|<p>Metrics for SDWAN peers.</p>|Dependent item|velocloud.sdwanpeers.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.edgeSDWan`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SDWAN peers metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SDWAN Peer [{#NAME}]:[{#TYPE}]: Raw data|<p>Raw data for velocloud sdwan peer.</p>|Dependent item|velocloud.get.sdwan_peer[{#EDGE.ID}/{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
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
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Raw data|<p>Raw data for velocloud sdwan peer path.</p>|Dependent item|velocloud.get.sdwan_path[{{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Bytes in|<p>Bytes received of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.bytes_rx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.bytesRx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Bytes out|<p>Bytes transmitted of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.bytes_tx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.bytesTx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Bytes total|<p>Total bytes of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.total_bytes[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.totalBytes`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packets in|<p>Packets received of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.packets_rx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetsRx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packets out|<p>Packets transmitted of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.packets_tx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetsTx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Total packets|<p>Total packets of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.total_packets[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.totalPackets`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packet Loss in|<p>Received packet loss of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.packet_loss_rx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetLossRx`</p></li></ul>|
|Path [{#NAME}]:[{#SOURCE} => {#DESTINATION}]: Packet Loss out|<p>Transmitted packet loss of SDWAN peer path.</p>|Dependent item|velocloud.sdwanpath.packet_loss_tx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.packetLossTx`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

