
# VMWare SD-WAN VeloCloud by HTTP

## Overview

For Zabbix version: 5.4 and higher  
The template to monitor VMWare SD-WAN VeloCloud by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.  



This template was tested on:

- VMware SD-WAN Orchestrator, version 4.0.2

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/5.4/manual/config/templates_out_of_the_box/http) for basic instructions.

You must set {$VELOCLOUD.TOKEN} and {$VELOCLOUD.URL} macros. 

You have to create API token in Orcestrator and use it in {$VELOCLOUD.TOKEN} macros. Read detailed instructions how to create token in VMWare documentation [documentation](https://docs.vmware.com/en/VMware-SD-WAN/4.0/vmware-sd-wan-operator-guide/GUID-C150D536-A75F-47C1-8AFF-17C417F40C1D.html)

Set Orchestrator URl for {$VELOCLOUD.URL}. e.g. example.com (where you replace example.com with the actual url VMWare SD-WAN Orchestrator is running on)


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VELOCLOUD.APPS.GATHER.INTERVAL} |<p>Interval of gathering apps data in seconds.</p> |`600` |
|{$VELOCLOUD.LLD.APP.LINKS.FILTER.MATCHES} |<p>Filter for discoverable app links.</p> |`.*` |
|{$VELOCLOUD.LLD.APP.LINKS.FILTER.NOT_MATCHES} |<p>Filter to exclude discovered app links</p> |`-1` |
|{$VELOCLOUD.LLD.EDGES.FILTER.MATCHES} |<p>Filter for discoverable edges.</p> |`.*` |
|{$VELOCLOUD.LLD.EDGES.FILTER.NOT_MATCHES} |<p>Filter to exclude discovered edges.</p> |`CHANGE_IF_NEEDED` |
|{$VELOCLOUD.LLD.ENTERPRISES.FILTER.MATCHES} |<p>Filter for discoverable enterprises.</p> |`.*` |
|{$VELOCLOUD.LLD.ENTERPRISES.FILTER.NOT_MATCHES} |<p>Filter to exclude discovered enterprises.</p> |`CHANGE_IF_NEEDED` |
|{$VELOCLOUD.LLD.GATEWAYS.FILTER.MATCHES} |<p>Filter for discoverable gateways.</p> |`.*` |
|{$VELOCLOUD.LLD.GATEWAYS.FILTER.NOT_MATCHES} |<p>Filter to exclude discovered gateways.</p> |`CHANGE_IF_NEEDED` |
|{$VELOCLOUD.LLD.LINKS.FILTER.MATCHES} |<p>Filter for discoverable links.</p> |`.*` |
|{$VELOCLOUD.LLD.LINKS.FILTER.NOT_MATCHES} |<p>Filter to exclude discovered links.</p> |`CHANGE_IF_NEEDED` |
|{$VELOCLOUD.LOGS.GATHER.INTERVAL} |<p>Interval of gathering log and events data in seconds.</p> |`1200` |
|{$VELOCLOUD.TOKEN} |<p>VMware SD-WAN Orchestrator API Token.</p> |`` |
|{$VELOCLOUD.URL} |<p>VMware SD-WAN Orchestrator URL. e.g vco.velocloud.net.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Edges metrics discovery |<p>Metrics for edges statistics.</p> |DEPENDENT |velocloud.edges.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.edges`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- A: {#NAME} NOT_MATCHES_REGEX `{$VELOCLOUD.LLD.EDGES.FILTER.NOT_MATCHES}`</p><p>- B: {#NAME} MATCHES_REGEX `{$VELOCLOUD.LLD.EDGES.FILTER.MATCHES}`</p> |
|Gateways metrics discovery |<p>Metrics for gateways statistics.</p> |DEPENDENT |velocloud.gateways.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- A: {#NAME} NOT_MATCHES_REGEX `{$VELOCLOUD.LLD.GATEWAYS.FILTER.NOT_MATCHES}`</p><p>- B: {#NAME} MATCHES_REGEX `{$VELOCLOUD.LLD.GATEWAYS.FILTER.MATCHES}`</p> |
|Links metrics discovery |<p>Metrics for links statistics.</p> |DEPENDENT |velocloud.links.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.links`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- A: {#ID} NOT_MATCHES_REGEX `{$VELOCLOUD.LLD.LINKS.FILTER.NOT_MATCHES}`</p><p>- B: {#ID} MATCHES_REGEX `{$VELOCLOUD.LLD.LINKS.FILTER.MATCHES}`</p> |
|App metrics discovery |<p>Metrics for app statistics.</p> |DEPENDENT |velocloud.app.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.apps`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|App links metrics discovery |<p>Metrics for app links statistics.</p> |DEPENDENT |velocloud.app_links.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.appsLinks`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- A: {#ID} NOT_MATCHES_REGEX `{$VELOCLOUD.LLD.APP.LINKS.FILTER.NOT_MATCHES}`</p><p>- B: {#ID} MATCHES_REGEX `{$VELOCLOUD.LLD.APP.LINKS.FILTER.MATCHES}`</p> |
|Enterprises metrics discovery |<p>Metrics for enterprises.</p> |DEPENDENT |velocloud.enterprises.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.enterprises`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- A: {#NAME} NOT_MATCHES_REGEX `{$VELOCLOUD.LLD.ENTERPRISES.FILTER.NOT_MATCHES}`</p><p>- B: {#NAME} MATCHES_REGEX `{$VELOCLOUD.LLD.ENTERPRISES.FILTER.MATCHES}`</p> |
|SDWAN peers metrics discovery |<p>Metrics for SDWAN peers.</p> |DEPENDENT |velocloud.sdwanpeers.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWan`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|SDWAN peers path metrics discovery |<p>Metrics for SDWAN peers path.</p> |DEPENDENT |velocloud.sdwanpath.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWanPath`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Velocloud |Velocloud: Orchestrator API version |<p>Version of VMware SD-WAN Orchestrator API.</p> |DEPENDENT |velocloud.orchestrator.api_version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version.apiVersion`</p> |
|Velocloud |Velocloud: Orchestrator build |<p>Build of VMware SD-WAN Orchestrator API.</p> |DEPENDENT |velocloud.orchestrator.build<p>**Preprocessing**:</p><p>- JSONPATH: `$.version.build`</p> |
|Velocloud |Velocloud: Orchestrator version |<p>Version of VMware SD-WAN Orchestrator API.</p> |DEPENDENT |velocloud.orchestrator.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version.version`</p> |
|Velocloud |Velocloud: Script item errors |<p>Errors of script item.</p> |DEPENDENT |velocloud.get.error<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Velocloud |Velocloud: System properties |<p>System properties of VMware SD-WAN.</p> |HTTP_AGENT |velocloud.system.properties<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Velocloud |Edge {#NAME}: Activation state |<p>Edge activation state.</p> |DEPENDENT |velocloud.edge.activation[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edges[?(@.id=='{#ID}')].activationState.first()`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Velocloud |Edge {#NAME}: Description |<p>Edge description.</p> |DEPENDENT |velocloud.edge.description[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edges[?(@.id=='{#ID}')].description.first()`</p> |
|Velocloud |Edge {#NAME}: HA state |<p>Edge high availability state.</p> |DEPENDENT |velocloud.edge.ha_state[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edges[?(@.id=='{#ID}')].haState.first()`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Velocloud |Edge {#NAME}: Model number |<p>Edge model number.</p> |DEPENDENT |velocloud.edge.model[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edges[?(@.id=='{#ID}')].modelNumber.first()`</p> |
|Velocloud |Edge {#NAME}: Service uptime |<p>Edge service uptime.</p> |DEPENDENT |velocloud.edge.service_uptime[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edges[?(@.id=='{#ID}')].serviceUpSince.first()`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Velocloud |Edge {#NAME}: Software version |<p>Edge software version.</p> |DEPENDENT |velocloud.edge.software_version[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edges[?(@.id=='{#ID}')].softwareVersion.first()`</p> |
|Velocloud |Edge {#NAME}: State |<p>Edge state.</p> |DEPENDENT |velocloud.edge.state[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edges[?(@.id=='{#ID}')].edgeState.first()`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Velocloud |Edge {#NAME}: System uptime |<p>Edge system uptime.</p> |DEPENDENT |velocloud.edge.system_uptime[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edges[?(@.id=='{#ID}')].systemUpSince.first()`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Velocloud |Gateway {#NAME}: Connected edges |<p>Gateway connected edges.</p> |DEPENDENT |velocloud.gateway.connected_edges[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways[?(@.id=='{#ID}')].connectedEdges.first()`</p> |
|Velocloud |Gateway {#NAME}: Description |<p>Gateway description.</p> |DEPENDENT |velocloud.gateway.description[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways[?(@.id=='{#ID}')].description.first()`</p> |
|Velocloud |Gateway {#NAME}: IP address |<p>Gateway ip address.</p> |DEPENDENT |velocloud.gateway.ip_address[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways[?(@.id=='{#ID}')].ipAddress.first()`</p> |
|Velocloud |Gateway {#NAME}: Service uptime |<p>Gateway service uptime.</p> |DEPENDENT |velocloud.gateway.service_uptime[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways[?(@.id=='{#ID}')].serviceUpSince.first()`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Velocloud |Gateway {#NAME}: State |<p>Gateway state.</p> |DEPENDENT |velocloud.gateway.state[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways[?(@.id=='{#ID}')].gatewayState.first()`</p> |
|Velocloud |Gateway {#NAME}: System uptime |<p>Gateway system uptime.</p> |DEPENDENT |velocloud.gateway.system_uptime[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways[?(@.id=='{#ID}')].systemUpSince.first()`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Velocloud |Gateway {#NAME}: Utilization CPU |<p>Gateway CPU utilization.</p> |DEPENDENT |velocloud.gateway.utilization.cpu[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways[?(@.id=='{#ID}')].utilizationDetail.cpu.first()`</p> |
|Velocloud |Gateway {#NAME}: Utilization load |<p>Gateway load.</p> |DEPENDENT |velocloud.gateway.utilization.load[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways[?(@.id=='{#ID}')].utilizationDetail.load.first()`</p> |
|Velocloud |Gateway {#NAME}: Utilization memory |<p>Gateway memory utilization.</p> |DEPENDENT |velocloud.gateway.utilization.memory[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways[?(@.id=='{#ID}')].utilizationDetail.memory.first()`</p> |
|Velocloud |Gateway {#NAME}: Utilization overall |<p>Gateway overall utilization.</p> |DEPENDENT |velocloud.gateway.utilization.overall[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.gateways[?(@.id=='{#ID}')].utilizationDetail.overall.first()`</p> |
|Velocloud |Link {#IP}({#NAME}): Best loss rx, % |<p>Link receive best loss.</p> |DEPENDENT |velocloud.link.best_loss_rx.pct[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.links[?(@.linkId=='{#ID}')].bestLossPctRx.first()`</p> |
|Velocloud |Link {#IP}({#NAME}): Best loss tx, % |<p>Link transmit best loss.</p> |DEPENDENT |velocloud.link.best_loss_tx.pct[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.links[?(@.linkId=='{#ID}')].bestLossPctTx.first()`</p> |
|Velocloud |Link {#IP}({#NAME}): Bytes Rx |<p>Link received bytes.</p> |DEPENDENT |velocloud.link.bytes_rx[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.links[?(@.linkId=='{#ID}')].bytesRx.first()`</p> |
|Velocloud |Link {#IP}({#NAME}): Bytes Tx |<p>Link transmitted bytes.</p> |DEPENDENT |velocloud.link.bytes_tx[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.links[?(@.linkId=='{#ID}')].bytesTx.first()`</p> |
|Velocloud |Link {#IP}({#NAME}): Last active |<p>Link last active in seconds ago.</p> |DEPENDENT |velocloud.link.last_active[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.links[?(@.linkId=='{#ID}')].link.linkLastActive.first()`</p><p>- JAVASCRIPT: `return Math.round((Date.now() - new Date(value).valueOf()) / 1000)`</p> |
|Velocloud |Link {#IP}({#NAME}): Packets Rx |<p>Link received packets.</p> |DEPENDENT |velocloud.link.packets_rx[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.links[?(@.linkId== '{#ID}')].packetsRx.first()`</p> |
|Velocloud |Link {#IP}({#NAME}): Packets Tx |<p>Link transmitted packets.</p> |DEPENDENT |velocloud.link.packets_tx[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.links[?(@.linkId=='{#ID}')].packetsTx.first()`</p> |
|Velocloud |Link {#IP}({#NAME}): State |<p>Link state.</p> |DEPENDENT |velocloud.link.state[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.links[?(@.linkId=='{#ID}')].link.linkState.first()`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Velocloud |Link {#IP}({#NAME}): Total bytes |<p>Link Total bytes.</p> |DEPENDENT |velocloud.link.total_bytes[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.links[?(@.linkId=='{#ID}')].totalBytes.first()`</p> |
|Velocloud |Link {#IP}({#NAME}): Total packets |<p>Link total packets.</p> |DEPENDENT |velocloud.link.total_packets[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.links[?(@.linkId=='{#ID}')].totalPackets.first()`</p> |
|Velocloud |App {#EDGE}:{#NAME}:{#LINK.ID}: Bytes Rx |<p>App received bytes.</p> |DEPENDENT |velocloud.app.bytes_rx[{#EDGE.ID}/{#LINK.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.apps[?(@.application=='{#ID}' && @.edgeId=='{#EDGE.ID}' && @.linkId=='{#LINK.ID}')].bytesRx.first()`</p> |
|Velocloud |App {#EDGE}:{#NAME}:{#LINK.ID}: Bytes Tx |<p>App transmitted bytes.</p> |DEPENDENT |velocloud.app.bytes_tx[{#EDGE.ID}/{#LINK.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.apps[?(@.application=='{#ID}' && @.edgeId=='{#EDGE.ID}' && @.linkId=='{#LINK.ID}')].bytesTx.first()`</p> |
|Velocloud |App {#EDGE}:{#NAME}:{#LINK.ID}: Packets Rx |<p>App received packets.</p> |DEPENDENT |velocloud.app.packets_rx[{#EDGE.ID}/{#LINK.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.apps[?(@.application=='{#ID}' && @.edgeId=='{#EDGE.ID}' && @.linkId=='{#LINK.ID}')].packetsRx.first()`</p> |
|Velocloud |App {#EDGE}:{#NAME}:{#LINK.ID}: Packets Tx |<p>App transmitted packets.</p> |DEPENDENT |velocloud.app.packets_tx[{#EDGE.ID}/{#LINK.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.apps[?(@.application=='{#ID}' && @.edgeId=='{#EDGE.ID}' && @.linkId=='{#LINK.ID}')].packetsTx.first()`</p> |
|Velocloud |App {#EDGE}:{#NAME}:{#LINK.ID}: Total bytes |<p>App total bytes.</p> |DEPENDENT |velocloud.app.total_bytes[{#EDGE.ID}/{#LINK.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.apps[?(@.application=='{#ID}' && @.edgeId=='{#EDGE.ID}' && @.linkId=='{#LINK.ID}')].totalBytes.first()`</p> |
|Velocloud |App {#EDGE}:{#NAME}:{#LINK.ID}: Total packets |<p>App total packets.</p> |DEPENDENT |velocloud.app.total_packets[{#EDGE.ID}/{#LINK.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.apps[?(@.application=='{#ID}' && @.edgeId=='{#EDGE.ID}' && @.linkId=='{#LINK.ID}')].totalPackets.first()`</p> |
|Velocloud |App {#EDGE}:{#NAME}:{#LINK.ID}: Flow count |<p>App flow count.</p> |DEPENDENT |velocloud.app.flow_count[{#EDGE.ID}/{#LINK.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.apps[?(@.application=='{#ID}' && @.edgeId=='{#EDGE.ID}' && @.linkId=='{#LINK.ID}')].flowCount.first()`</p> |
|Velocloud |App link {#EDGE}:{#NAME}:{#LINK.ID}: Bytes Rx |<p>App link received bytes.</p> |DEPENDENT |velocloud.app_link.bytes_rx[{{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.appsLinks[?(@.linkId=='{#ID}')].bytesRx.first()`</p> |
|Velocloud |App link {#EDGE}:{#NAME}:{#LINK.ID}: Bytes Tx |<p>App link transmit bytes.</p> |DEPENDENT |velocloud.app_link.bytes_tx[{{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.appsLinks[?(@.linkId=='{#ID}')].bytesTx.first()`</p> |
|Velocloud |App link {#EDGE}:{#NAME}:{#LINK.ID}: Packets Rx |<p>App link received packets.</p> |DEPENDENT |velocloud.app_link.packets_rx[{{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.appsLinks[?(@.linkId=='{#ID}')].packetsRx.first()`</p> |
|Velocloud |App link {#EDGE}:{#NAME}:{#LINK.ID}: Packets Tx |<p>App link transmitted Packets.</p> |DEPENDENT |velocloud.app_link.packets_tx[{{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.appsLinks[?(@.linkId=='{#ID}')].packetsTx.first()`</p> |
|Velocloud |App link {#EDGE}:{#NAME}:{#LINK.ID}: Total bytes |<p>App link total bytes.</p> |DEPENDENT |velocloud.app_link.total_bytes[{{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.appsLinks[?(@.linkId=='{#ID}')].totalBytes.first()`</p> |
|Velocloud |App link {#EDGE}:{#NAME}:{#LINK.ID}: Total packets |<p>App link total packets.</p> |DEPENDENT |velocloud.app_link.total_packets[{{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.appsLinks[?(@.linkId=='{#ID}')].totalPackets.first()`</p> |
|Velocloud |App link {#EDGE}:{#NAME}:{#LINK.ID}: Flow count |<p>App link flow count.</p> |DEPENDENT |velocloud.app_link.flow_count[{{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.appsLinks[?(@.linkId=='{#ID}')].flowCount.first()`</p> |
|Velocloud |Enterprise {#NAME}: Enterprise events |<p>Events of enterprise.</p> |DEPENDENT |velocloud.enterprise.events[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.enterpriseEvents.{#ID}`</p><p>- JAVASCRIPT: `return JSON.parse(value).length ? value : ""`</p> |
|Velocloud |Enterprise {#NAME}: Operator events |<p>Operator events of enterprise.</p> |DEPENDENT |velocloud.enterprise.operator_events[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.operatorEvents.{#ID}`</p><p>- JAVASCRIPT: `return JSON.parse(value).length ? value : ""`</p> |
|Velocloud |Enterprise {#NAME}: Firewall logs |<p>Firewall logs of enterprise.</p> |DEPENDENT |velocloud.enterprise.firewall_logs[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.firewallLogs.{#ID}`</p><p>- JAVASCRIPT: `return JSON.parse(value).length ? value : ""`</p> |
|Velocloud |SDWAN Peer {#NAME}({#TYPE}): Description |<p>Description of SDWAN peer.</p> |DEPENDENT |velocloud.sdwanpeer.description[{#EDGE.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWan[?(@.deviceLogicalId=='{#ID}' && @.edgeId=='{#EDGE.ID}')].description.first()`</p> |
|Velocloud |SDWAN Peer {#NAME}({#TYPE}): Stable path |<p>Count of stable path of SDWAN peer.</p> |DEPENDENT |velocloud.sdwanpeer.stable_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWan[?(@.deviceLogicalId=='{#ID}' && @.edgeId=='{#EDGE.ID}')].pathStatusCount.stable.first()`</p> |
|Velocloud |SDWAN Peer {#NAME}({#TYPE}): Unstable path |<p>Count of unstable path of SDWAN peer.</p> |DEPENDENT |velocloud.sdwanpeer.unstable_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWan[?(@.deviceLogicalId=='{#ID}' && @.edgeId=='{#EDGE.ID}')].pathStatusCount.unstable.first()`</p> |
|Velocloud |SDWAN Peer {#NAME}({#TYPE}): Standby path |<p>Count of standby path of SDWAN peer.</p> |DEPENDENT |velocloud.sdwanpeer.standby_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWan[?(@.deviceLogicalId=='{#ID}' && @.edgeId=='{#EDGE.ID}')].pathStatusCount.standby.first()`</p> |
|Velocloud |SDWAN Peer {#NAME}({#TYPE}): Dead path |<p>Count of dead path of SDWAN peer.</p> |DEPENDENT |velocloud.sdwanpeer.dead_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWan[?(@.deviceLogicalId=='{#ID}' && @.edgeId=='{#EDGE.ID}')].pathStatusCount.dead.first()`</p> |
|Velocloud |SDWAN Peer {#NAME}({#TYPE}): Unknown path |<p>Count of unknown path of SDWAN peer.</p> |DEPENDENT |velocloud.sdwanpeer.unknown_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWan[?(@.deviceLogicalId=='{#ID}' && @.edgeId=='{#EDGE.ID}')].pathStatusCount.unknown.first()`</p> |
|Velocloud |SDWAN Peer {#NAME}({#TYPE}): Total path |<p>Count of total path of SDWAN peer.</p> |DEPENDENT |velocloud.sdwanpeer.total_path[{#EDGE.ID}/{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWan[?(@.deviceLogicalId=='{#ID}' && @.edgeId=='{#EDGE.ID}')].pathStatusCount.total.first()`</p> |
|Velocloud |Path {#SOURCE} => {#DESTINATION}({#NAME}): Bytes Rx |<p>Bytes received of SDWAN peer path.</p> |DEPENDENT |velocloud.sdwanpath.bytes_rx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWanPath[?(@.source.linkName=='{#NAME}' && @.source.deviceName=='{#SOURCE}' && @.destination.deviceName=='{#DESTINATION}')].metrics.bytesRx.first()`</p> |
|Velocloud |Path {#SOURCE} => {#DESTINATION}({#NAME}): Bytes Tx |<p>Bytes transmitted of SDWAN peer path.</p> |DEPENDENT |velocloud.sdwanpath.bytes_tx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWanPath[?(@.source.linkName=='{#NAME}' && @.source.deviceName=='{#SOURCE}' && @.destination.deviceName=='{#DESTINATION}')].metrics.bytesTx.first()`</p> |
|Velocloud |Path {#SOURCE} => {#DESTINATION}({#NAME}): Bytes total |<p>Total bytes of SDWAN peer path.</p> |DEPENDENT |velocloud.sdwanpath.total_bytes[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWanPath[?(@.source.linkName=='{#NAME}' && @.source.deviceName=='{#SOURCE}' && @.destination.deviceName=='{#DESTINATION}')].metrics.totalBytes.first()`</p> |
|Velocloud |Path {#SOURCE} => {#DESTINATION}({#NAME}): Packets Rx |<p>Packets received of SDWAN peer path.</p> |DEPENDENT |velocloud.sdwanpath.packets_rx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWanPath[?(@.source.linkName=='{#NAME}' && @.source.deviceName=='{#SOURCE}' && @.destination.deviceName=='{#DESTINATION}')].metrics.packetsRx.first()`</p> |
|Velocloud |Path {#SOURCE} => {#DESTINATION}({#NAME}): Packets Tx |<p>Packets transmitted of SDWAN peer path.</p> |DEPENDENT |velocloud.sdwanpath.packets_tx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWanPath[?(@.source.linkName=='{#NAME}' && @.source.deviceName=='{#SOURCE}' && @.destination.deviceName=='{#DESTINATION}')].metrics.packetsTx.first()`</p> |
|Velocloud |Path {#SOURCE} => {#DESTINATION}({#NAME}): Total packets |<p>Total packets of SDWAN peer path.</p> |DEPENDENT |velocloud.sdwanpath.total_packets[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWanPath[?(@.source.linkName=='{#NAME}' && @.source.deviceName=='{#SOURCE}' && @.destination.deviceName=='{#DESTINATION}')].metrics.totalPackets.first()`</p> |
|Velocloud |Path {#SOURCE} => {#DESTINATION}({#NAME}): Packet Loss Rx |<p>Received packet loss of SDWAN peer path.</p> |DEPENDENT |velocloud.sdwanpath.packet_loss_rx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWanPath[?(@.source.linkName=='{#NAME}' && @.source.deviceName=='{#SOURCE}' && @.destination.deviceName=='{#DESTINATION}')].metrics.packetLossRx.first()`</p> |
|Velocloud |Path {#SOURCE} => {#DESTINATION}({#NAME}): Packet Loss Tx |<p>Transmitted packet loss of SDWAN peer path.</p> |DEPENDENT |velocloud.sdwanpath.packet_loss_tx[{#NAME}/{#SOURCE}/{#DESTINATION}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.edgeSDWanPath[?(@.source.linkName=='{#NAME}' && @.source.deviceName=='{#SOURCE}' && @.destination.deviceName=='{#DESTINATION}')].metrics.packetLossTx.first()`</p> |
|Zabbix_raw_items |Velocloud: Get aggregate data |<p>The JSON with result of Velocloud API requests.</p> |SCRIPT |velocloud.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix_raw_items |Velocloud: Get logs data |<p>The JSON with result of Velocloud API request for logs and events.</p> |SCRIPT |velocloud.get_logs<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix_raw_items |Velocloud: Get apps data |<p>The JSON with result of Velocloud API request for apps data.</p> |SCRIPT |velocloud.get_apps<p>**Expression**:</p>`The text is too long. Please see the template.` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Velocloud: Failed to fetch aggregate data (or no data for 30m) |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`nodata(/VMWare SD-WAN VeloCloud by HTTP/velocloud.orchestrator.api_version,30m)=1` |AVERAGE |<p>Manual close: YES</p> |
|Velocloud: There are errors in script item requests |<p>There are errors in script item requests.</p> |`length(last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.get.error))>0` |WARNING | |
|Velocloud: System properties have changed |<p>System properties have changed.</p> |`change(/VMWare SD-WAN VeloCloud by HTTP/velocloud.system.properties)=1` |INFO |<p>Manual close: YES</p> |
|Edge {#NAME}: HA state is in "FAILED" state |<p>High availability state is "FAILED".</p> |`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.edge.ha_state[{#ID}])=3` |WARNING | |
|Edge {#NAME}: Edge is in "OFFLINE" state |<p>Edge state is "OFFLINE".</p> |`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.edge.state[{#ID}])=0` |AVERAGE | |
|Edge {#NAME}: Edge uptime is less than 10m |<p>Edge was restarted.</p> |`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.edge.system_uptime[{#ID}])>0 and last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.edge.system_uptime[{#ID}])<600` |WARNING | |
|Gateway {#NAME}: The number of connected edges is changed |<p>The number of connected edges is changed.</p> |`change(/VMWare SD-WAN VeloCloud by HTTP/velocloud.gateway.connected_edges[{#ID}])=1` |WARNING |<p>Manual close: YES</p> |
|Gateway {#NAME}: Gateway uptime is less that 10m |<p>Gateway was restarted.</p> |`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.gateway.system_uptime[{#ID}])>0 and last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.gateway.system_uptime[{#ID}])<600` |WARNING | |
|Link {#IP}({#NAME}): Link state is not "STABLE" |<p>Link state is not "STABLE".</p> |`last(/VMWare SD-WAN VeloCloud by HTTP/velocloud.link.state[{#ID}])<>1` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

