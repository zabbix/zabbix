
# Juniper MX by NETCONF

## Overview

This template is for monitoring Juniper MX Series by NETCONF via Zabbix and works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

*NOTE*
This template uses SSH checks with a new `subsystem` parameter in the item key, available in Zabbix 7.2 and later.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Juniper MX204 Edge Router, JUNOS 24.2R1-S1.10

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

For this template to work, you must enable the NETCONF sessions over the SSH service.

1. Enable the NETCONF service on either the default NETCONF port (830) or a user-defined port.
   To use the default NETCONF port (830), include the `netconf ssh` statement at the `[edit system services]` hierarchy level:

      ```
      [edit system services]
      user@host# set netconf ssh
      ```
2. Create a local user account:

      ```
      [edit system login]
      user@host# set user zabbix class read-only
      ```
3. Create a text-based password:

      ```
      [edit system login user zabbix authentication]
      user@host# set plain-text-password
      New password: password
      Retype new password: password

4. Commit the configuration:

      ```
      [edit]
      user@host# commit
      ```

Set the macros: `{$JUNIPER.MX.NETCONF.USERNAME}`, `{$JUNIPER.MX.NETCONF.PASSWORD}`.

For more details, please see: [Enable NETCONF Service over SSH](https://www.juniper.net/documentation/us/en/software/junos/netconf/topics/topic-map/netconf-ssh-connection.html).


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$JUNIPER.MX.NETCONF.USERNAME}|<p>Juniper NETCONF username.</p>||
|{$JUNIPER.MX.NETCONF.PASSWORD}|<p>Juniper NETCONF password.</p>||
|{$JUNIPER.MX.NETCONF.IP}|<p>The IP address of the Juniper MX device.</p>||
|{$JUNIPER.MX.NETCONF.PORT}|<p>The NETCONF port of the Juniper MX device.</p>|`830`|
|{$JUNIPER.MX.NETCONF.TIMEOUT}|<p>SSH response timeout.</p>|`15s`|
|{$JUNIPER.MX.NETCONF.RESPONSE_TIME.MAX.WARN}|<p>The maximum Juniper NETCONF response time expressed in seconds, for a trigger expression.</p>|`10`|
|{$JUNIPER.MX.NET.IF.NAME.MATCHES}|<p>Used for discovering network physical interfaces. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$JUNIPER.MX.NET.IF.NAME.NOT_MATCHES}|<p>Filters out `loopbacks`, `nulls`, docker `veth` links, and the `docker0` bridge by default.</p>|`Macro too long. Please see the template.`|
|{$JUNIPER.MX.NET.IF.IFOPERSTATUS.MATCHES}|<p>Used for network physical interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$JUNIPER.MX.NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Used for network physical interface discovery. Can be overridden on the host or linked template level.</p>|`<CHANGE_IF_NEEDED>`|
|{$JUNIPER.MX.NET.IF.ADMINSTATUS.MATCHES}||`^.*$`|
|{$JUNIPER.MX.NET.IF.ADMINSTATUS.NOT_MATCHES}|<p>Ignore the `down` administrative status</p>|`^down$`|
|{$JUNIPER.MX.NET.IF.CONTROL}|<p>The operational state of the interface for the `link down` trigger. Can be used with the interface name as context.</p>|`1`|
|{$JUNIPER.MX.NET.IF.UTIL.MAX}|<p>The threshold in the hardware interface utilization triggers.</p>|`90`|
|{$JUNIPER.MX.NET.IF.SFP.NAME.MATCHES}|<p>Used for SFP interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$JUNIPER.MX.NET.IF.SFP.NAME.NOT_MATCHES}|<p>Used for SFP interface discovery. Can be overridden on the host or linked template level.</p>|`<CHANGE_IF_NEEDED>`|
|{$JUNIPER.MX.FS.FSNAME.NOT_MATCHES}|<p>Used for filesystem discovery. Can be overridden on the host or linked template level.</p>|`<CHANGE_IF_NEEDED>`|
|{$JUNIPER.MX.FS.FSNAME.MATCHES}|<p>Used for filesystem discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$JUNIPER.MX.FS.PUSED.MAX.CRIT}|<p>The critical threshold of filesystem utilization.</p>|`90`|
|{$JUNIPER.MX.FS.PUSED.MAX.WARN}|<p>The warning threshold of filesystem utilization.</p>|`80`|
|{$JUNIPER.MX.FS.FREE.MIN.CRIT}|<p>The critical threshold of filesystem utilization.</p>|`5G`|
|{$JUNIPER.MX.FS.FREE.MIN.WARN}|<p>The warning threshold of filesystem utilization.</p>|`10G`|
|{$JUNIPER.MX.CPU.UTIL.MIN}|<p>Threshold of Routing Engine CPU utilization for a trigger in %.</p>|`90`|
|{$JUNIPER.MX.MEMORY.UTIL.MAX}|<p>Threshold of memory utilization for a trigger in %.</p>|`90`|
|{$JUNIPER.MX.FPC.CPU.UTIL.MIN}|<p>Threshold of CPU being used by the FPC's processor utilization for a trigger in %.</p>|`90`|
|{$JUNIPER.MX.FPC.HEAP.MEMORY.UTIL.MAX}|<p>Threshold of heap space (dynamic memory) being used by the FPC's processor utilization for a trigger in %.</p>|`70`|
|{$JUNIPER.MX.FPC.BUFFER.MEMORY.UTIL.MAX}|<p>Threshold of buffer space being used by the FPC's processor utilization for a trigger in %.</p>|`80`|
|{$JUNIPER.MX.BGP.PEER.STATE}|<p>BGP peer state for a trigger.</p>|`^(6\|1)$`|
|{$JUNIPER.MX.BGP.ROUTER.NAME.MATCHES}|<p>Used for BGP discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$JUNIPER.MX.BGP.ROUTER.NAME.NOT_MATCHES}|<p>Used for BGP discovery. Can be overridden on the host or linked template level.</p>|`<CHANGE_IF_NEEDED>`|
|{$JUNIPER.MX.BGP.PEER.REMOTE.ADDR.MATCHES}|<p>Used for BGP discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$JUNIPER.MX.BGP.PEER.REMOTE.ADDR.NOT_MATCHES}|<p>Used for BGP discovery. Can be overridden on the host or linked template level.</p>|`<CHANGE_IF_NEEDED>`|
|{$JUNIPER.MX.ALARM.NAME.MATCHES}|<p>Used for alarm discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$JUNIPER.MX.ALARM.NAME.NOT_MATCHES}|<p>Used for alarm discovery. Can be overridden on the host or linked template level.</p>|`<CHANGE_IF_NEEDED>`|
|{$JUNIPER.MX.ALARM.CLASS.MATCHES}|<p>Used for alarm discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$JUNIPER.MX.ALARM.CLASS.NOT_MATCHES}|<p>Used for alarm discovery. Can be overridden on the host or linked template level.</p>|`<CHANGE_IF_NEEDED>`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|NETCONF: Service status|<p>Checks if a service is running and accepting NETCONF connections.</p><p>Possible values: 0 - the service is down; 1 - the service is running.</p>|Simple check|net.tcp.service[ssh,"{$JUNIPER.MX.NETCONF.IP}","{$JUNIPER.MX.NETCONF.PORT}"]|
|NETCONF: Service response time|<p>Checks the performance of a TCP service.</p><p>Possible values: a float representing the response time in seconds, or `0.000000` indicating the service is down.</p>|Simple check|net.tcp.service.perf[ssh,"{$JUNIPER.MX.NETCONF.IP}","{$JUNIPER.MX.NETCONF.PORT}"]|
|Alarm: Get data|<p>Gets the alarm raw data.</p>|Dependent item|juniper.mx.alarm.data.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.results..["rpc-reply"]["alarm-information"]`</p></li></ul>|
|DOM: Get data|<p>Gets interface optics diagnostics information using the RPC request NETCONF server.</p>|SSH agent|ssh.run[JuniperMxDom,{$JUNIPER.MX.NETCONF.IP},{$JUNIPER.MX.NETCONF.PORT},,,netconf]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|DOM: Get rpc error|<p>Checks that the remote procedure call metrics and data have been received correctly.</p>|Dependent item|juniper.mx.dom.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Resource: Get data|<p>Gets resource information data using the RPC request NETCONF server.</p>|SSH agent|ssh.run[JuniperMxResource,{$JUNIPER.MX.NETCONF.IP},{$JUNIPER.MX.NETCONF.PORT},,,netconf]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Resource: Get rpc error|<p>Checks that the remote procedure call metrics and data have been received correctly.</p>|Dependent item|juniper.mx.resource.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.results..content.first()`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Routing protocols: Get data|<p>Gets routing protocol information data using the RPC request NETCONF server.</p>|SSH agent|ssh.run[JuniperMxBgpOspf,{$JUNIPER.MX.NETCONF.IP},{$JUNIPER.MX.NETCONF.PORT},,,netconf]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Routing protocols: Get rpc error|<p>Checks that the remote procedure call metrics and data have been received correctly.</p>|Dependent item|juniper.mx.bgp.ospf.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.results..content.first()`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|BGP: Get data|<p>Gets BGP raw data.</p>|Dependent item|juniper.mx.bgp.data.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|OSPF: Get data|<p>Gets OSPF raw data.</p>|Dependent item|juniper.mx.ospf.data.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Storage: Get data|<p>Gets storage raw data.</p>|Dependent item|juniper.mx.storage.data.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|PEM: Get data|<p>Gets PEM raw data.</p>|Dependent item|juniper.mx.pem.data.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|FAN: Get data|<p>Gets FAN raw data.</p>|Dependent item|juniper.mx.fan.data.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Temperature: Get data|<p>Gets temperature raw data.</p>|Dependent item|juniper.mx.temperature.data.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|FPC: Get data|<p>Gets raw data information for Packet Forwarding Engines (FPC).</p>|Dependent item|juniper.mx.fpc.data.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.results..["rpc-reply"]["fpc-information"]["fpc"]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Routing Engine: Get data|<p>Gets Routing Engine information raw data.</p>|Dependent item|juniper.mx.routing.engine.data.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Interface information: Get data|<p>Gets interface information using the RPC request NETCONF server.</p>|SSH agent|ssh.run[JuniperMxInterface,{$JUNIPER.MX.NETCONF.IP},{$JUNIPER.MX.NETCONF.PORT},,,netconf]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Interface information: Get rpc error|<p>Checks that the remote procedure call metrics and data have been received correctly.</p>|Dependent item|juniper.mx.interface.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.results..content.first()`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: NETCONF is not available|<p>NETCONF is unavailable on the specified TCP port. Possible causes include service downtime, port blockage, or network issues.</p>|`last(/Juniper MX by NETCONF/net.tcp.service[ssh,"{$JUNIPER.MX.NETCONF.IP}","{$JUNIPER.MX.NETCONF.PORT}"])=0`|Average|**Manual close**: Yes|
|Juniper MX: NETCONF response time is too high||`min(/Juniper MX by NETCONF/net.tcp.service.perf[ssh,"{$JUNIPER.MX.NETCONF.IP}","{$JUNIPER.MX.NETCONF.PORT}"],5m)>{$JUNIPER.MX.NETCONF.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Juniper MX: NETCONF is not available</li></ul>|
|Juniper MX: Failed to get DOM data|<p>Failed to get metrics for interface optics diagnostics information.</p>|`length(last(/Juniper MX by NETCONF/juniper.mx.dom.error))>0`|Warning||
|Juniper MX: Failed to get resource data|<p>Failed to get metrics for the resource.</p>|`length(last(/Juniper MX by NETCONF/juniper.mx.resource.error))>0`|Warning||
|Juniper MX: Failed to get routing protocol data|<p>Failed to get metrics for the routing protocol.</p>|`length(last(/Juniper MX by NETCONF/juniper.mx.bgp.ospf.error))>0`|Warning||
|Juniper MX: Failed to get interface information data|<p>Failed to get metrics for interface information.</p>|`length(last(/Juniper MX by NETCONF/juniper.mx.interface.error))>0`|Warning||

### LLD rule Routing Engine discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Routing Engine discovery|<p>Scanning `show chassis routing-engine` for the Routing Engine.</p>|Dependent item|juniper.mx.routing.engine.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Routing Engine discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Routing Engine Slot [{#SLOT}]: Get metrics data|<p>Gets data for FPC Slot '[{#SLOT}]'.</p>|Dependent item|juniper.mx.routing.engine.get["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.slot == "{#SLOT}")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: Model|<p>Routing Engine model.</p>|Dependent item|juniper.mx.routing.engine.model["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..model.first()`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: Last reboot reason|<p>Routing Engine last reboot reason.</p>|Dependent item|juniper.mx.routing.engine.reboot["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["last-reboot-reason"].first()`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: Status|<p>Routing Engine status.</p>|Dependent item|juniper.mx.routing.engine.status["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..status.first()`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: CPU temperature|<p>Temperature of the CPU Routing Engine.</p>|Dependent item|juniper.mx.routing.engine.cpu.temperature["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["cpu-temperature"]["@celsius"].first()`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: Uptime|<p>How long the Routing Engine has been running.</p>|Dependent item|juniper.mx.routing.engine.uptime["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["up-time"]["@seconds"].first()`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: Start time|<p>Time when the Routing Engine started running.</p>|Dependent item|juniper.mx.routing.engine.start.time["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["start-time"]["@seconds"].first()`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: Memory utilization|<p>Percentage of Routing Engine memory being used.</p>|Dependent item|juniper.mx.routing.engine.mem.util["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["memory-buffer-utilization"].first()`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: DRAM available|<p>Total DRAM available to the Routing Engine's processor.</p>|Dependent item|juniper.mx.routing.engine.dram["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["memory-dram-size"].first()`</p></li><li><p>Right trim: `MB`</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: CPU total utilization|<p>Percentage of total CPU utilization.</p>|Calculated|juniper.mx.routing.engine.cpu.total.util["{#SLOT}"]|
|Routing Engine Slot [{#SLOT}]: CPU user utilization|<p>Percentage of CPU time being used by user processes.</p>|Dependent item|juniper.mx.routing.engine.cpu.user.util["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["cpu-user"].first()`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: CPU system utilization|<p>Percentage of CPU time being used by system processes.</p>|Dependent item|juniper.mx.routing.engine.cpu.system.util["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["cpu-system"].first()`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: CPU interrupt utilization|<p>Percentage of CPU time being used by interrupts.</p>|Dependent item|juniper.mx.routing.engine.cpu.interrupt.util["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["cpu-interrupt"].first()`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: CPU background utilization|<p>Percentage of CPU time being used by background processes.</p>|Dependent item|juniper.mx.routing.engine.cpu.background.util["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["cpu-background"].first()`</p></li></ul>|
|Routing Engine Slot [{#SLOT}]: CPU idle|<p>Percentage of CPU time that is idle.</p>|Dependent item|juniper.mx.routing.engine.cpu.idle["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["cpu-idle"].first()`</p></li></ul>|

### Trigger prototypes for Routing Engine discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: Routing Engine Slot [{#SLOT}]: Status not "OK"|<p>Check the Routing Engine errors.</p>|`last(/Juniper MX by NETCONF/juniper.mx.routing.engine.status["{#SLOT}"])<>"OK"`|High||
|Juniper MX: High Routing Engine memory utilization|<p>The system is running out of free memory.</p>|`min(/Juniper MX by NETCONF/juniper.mx.routing.engine.mem.util["{#SLOT}"],5m)>{$JUNIPER.MX.MEMORY.UTIL.MAX}`|Average||
|Juniper MX: High Routing Engine CPU utilization|<p>Routing Engine CPU utilization is too high.</p>|`min(/Juniper MX by NETCONF/juniper.mx.routing.engine.cpu.total.util["{#SLOT}"], 10m) >= {$JUNIPER.MX.CPU.UTIL.MIN}`|Average||

### LLD rule FPC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FPC discovery|<p>Scanning `show chassis fpc` and `show chassis fpc detail` for FPCs.</p>|Dependent item|juniper.mx.fpc.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for FPC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FPC Slot [{#SLOT}]: Get metrics data|<p>Gets data for FPC Slot '[{#SLOT}]'.</p>|Dependent item|juniper.mx.fpc.get["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.slot == "{#SLOT}")]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|FPC Slot [{#SLOT}]: Uptime|<p>How long the Routing Engine has been connected to the FPC (how long the FPC has been up and running).</p>|Dependent item|juniper.mx.fpc.uptime["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["up-time"]["@seconds"].first()`</p></li></ul>|
|FPC Slot [{#SLOT}]: Start time|<p>Time when the Routing Engine detected that the FPC was running.</p>|Dependent item|juniper.mx.fpc.start-time["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["start-time"]["@seconds"].first()`</p></li></ul>|
|FPC Slot [{#SLOT}]: CPU total utilization|<p>Total percentage of CPU being used by the FPC processor.</p>|Dependent item|juniper.mx.fpc.cpu.total["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["cpu-total"].first()`</p></li></ul>|
|FPC Slot [{#SLOT}]: Heap utilization|<p>Percentage of heap space (dynamic memory) being used by the FPC's processor. If this number exceeds 80%, there may be a software problem (memory leak).</p><p>NOTE: On MX Series routers and EX Series switches in a broadband edge environment, heap utilization levels higher than 70% can affect unified ISSU, router stability, or scaling capability.</p>|Dependent item|juniper.mx.fpc.mem.heap.util["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["memory-heap-utilization"].first()`</p></li></ul>|
|FPC Slot [{#SLOT}]: Buffer utilization|<p>Percentage of buffer space being used by the FPC's processor for buffering internal messages.</p>|Dependent item|juniper.mx.fpc.mem.buffer.util["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..["memory-buffer-utilization"].first()`</p></li></ul>|
|FPC Slot [{#SLOT}]: State|<p>The state can be one of the following:</p><p>* Dead — Held in reset because of errors.</p><p>* Diag — Slot is being ignored while the FPC is running diagnostics.</p><p>* Dormant — Held in reset.</p><p>* Empty — No FPC is present.</p><p>* Offline — (PTX Series Packet Transport Routers only) One of the following two states is displayed:</p><p>`FPC offlined due to unreachable destinations`</p><p>`FPC Offlined due to degraded FPC action`</p><p>* Online — FPC is online and running.</p><p>* Present — FPC is detected by the chassis daemon, but is either not supported by the current version of Junos OS, or is inserted in the wrong slot. The output also states either `Hardware Not Supported` or `Hardware Not In Right Slot`. The FPC is coming up, but not yet online.</p><p>* Probed — Probe is complete; awaiting restart of the Packet Forwarding Engine.</p><p>* Probe-wait — Waiting to be probed.</p><p>* Unknown — FPC is present, but the state is unknown.</p><p>* Onlining — FPC is in the process of going online. ASIC and the rest of the hardware is initializing.</p><p>* Offlining — FPC is in the process of going offline. ASIC and the rest of the hardware is being shutdown to take the offline gracefully.</p><p>* Fault — FPC is in an alarm state in which none of the PICs are operational.</p><p>* Fault-off — FPC is powered off due to a fault.</p><p>* Spare — FPC is redundant and will move to active state if one of the working FPCs fails to pass traffic.</p>|Dependent item|juniper.mx.fpc.state["{#SLOT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..state.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for FPC discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: FPC Slot [{#SLOT}]: High CPU utilization|<p>FPC CPU utilization is too high.</p>|`min(/Juniper MX by NETCONF/juniper.mx.fpc.cpu.total["{#SLOT}"], 10m) >= {$JUNIPER.MX.FPC.CPU.UTIL.MIN}`|Average||
|Juniper MX: FPC Slot [{#SLOT}]: High heap memory utilization|<p>The system is running out of free memory.</p>|`min(/Juniper MX by NETCONF/juniper.mx.fpc.mem.heap.util["{#SLOT}"],5m)>{$JUNIPER.MX.FPC.HEAP.MEMORY.UTIL.MAX}`|Average||
|Juniper MX: FPC Slot [{#SLOT}]: High buffer memory utilization|<p>The system is running out of free memory.</p>|`min(/Juniper MX by NETCONF/juniper.mx.fpc.mem.buffer.util["{#SLOT}"],5m)>{$JUNIPER.MX.FPC.BUFFER.MEMORY.UTIL.MAX}`|Average||
|Juniper MX: FPC Slot [{#SLOT}]: Status not "Online"|<p>Check the FPC's errors.</p>|`last(/Juniper MX by NETCONF/juniper.mx.fpc.state["{#SLOT}"])<>6`|Warning||

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of interfaces from the Juniper device.</p>|Dependent item|juniper.mx.net.if.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IFNAME}][{#IFDESCR}]: Get metrics data|<p>Gets data from the physical interface '[{#IFNAME}]'.</p>|Dependent item|juniper.mx.interface.get["{#IFNAME}","{#IFDESCR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Interface [{#IFNAME}][{#IFDESCR}]: Operational status|<p>Gets the operational status of the physical interface '[{#IFNAME}]'.</p>|Dependent item|juniper.mx.net.if.oper.status["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["oper-status"]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IFNAME}][{#IFDESCR}]: Speed|<p>Gets the speed of the interface '[{#IFNAME}]'.</p>|Dependent item|juniper.mx.net.if.speed["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["speed"]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Interface [{#IFNAME}][{#IFDESCR}]: Link level type|<p>Gets the link level type of the interface '[{#IFNAME}]'.</p>|Dependent item|juniper.mx.net.link.level.type["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["link-level-type"]`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Interface [{#IFNAME}][{#IFDESCR}]: Output bits|<p>Number of output bytes; current throughput rate in bits per second (bps).</p>|Dependent item|juniper.mx.net.output.bits.rate["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["traffic-statistics"]["output-bytes"]`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}][{#IFDESCR}]: Input bits|<p>Number of input bytes; current throughput rate in bits per second (bps).</p>|Dependent item|juniper.mx.net.input.bits.rate["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["traffic-statistics"]["input-bytes"]`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}][{#IFDESCR}]: Output packets|<p>Number of output packets; current throughput rate in packets per second (pps).</p>|Dependent item|juniper.mx.net.output.packets.rate["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["traffic-statistics"]["output-packets"]`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Interface [{#IFNAME}][{#IFDESCR}]: Input packets|<p>Number of input packets; current throughput rate in packets per second (pps).</p>|Dependent item|juniper.mx.net.input.packets.rate["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["traffic-statistics"]["input-packets"]`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Interface [{#IFNAME}][{#IFDESCR}]: Input errors|<p>Input errors on the interface.</p>|Dependent item|juniper.mx.net.input.errors["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["input-error-list"]["input-errors"]`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Interface [{#IFNAME}][{#IFDESCR}]: Output errors|<p>Output errors on the interface.</p>|Dependent item|juniper.mx.net.output.errors["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["output-error-list"]["output-errors"]`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: Interface [{#IFNAME}][{#IFDESCR}]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operational status is down.<br>2. `{$JUNIPER.MX.NET.IF.CONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status has changed to "down" from some other state (so, does not fire for "eternal off" interfaces).<br><br>WARNING: if closed manually - it will not fire again on the next poll because of `last(/TEMPLATE_NAME/METRIC)<>last(/TEMPLATE_NAME/METRIC,#2)`.</p>|`{$JUNIPER.MX.NET.IF.CONTROL:"{#IFNAME}"}=1 and last(/Juniper MX by NETCONF/juniper.mx.net.if.oper.status["{#IFNAME}"])=2 and (last(/Juniper MX by NETCONF/juniper.mx.net.if.oper.status["{#IFNAME}"])<>last(/Juniper MX by NETCONF/juniper.mx.net.if.oper.status["{#IFNAME}"],#2))`|Average|**Manual close**: Yes|
|Juniper MX: Interface [{#IFNAME}][{#IFDESCR}]: High outbound bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Juniper MX by NETCONF/juniper.mx.net.output.bits.rate["{#IFNAME}"],15m)>({$JUNIPER.MX.NET.IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Juniper MX by NETCONF/juniper.mx.net.if.speed["{#IFNAME}"])) and last(/Juniper MX by NETCONF/juniper.mx.net.if.speed["{#IFNAME}"])>0`|Warning|**Depends on**:<br><ul><li>Juniper MX: Interface [{#IFNAME}][{#IFDESCR}]: Link down</li></ul>|
|Juniper MX: Interface [{#IFNAME}][{#IFDESCR}]: High inbound bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Juniper MX by NETCONF/juniper.mx.net.input.bits.rate["{#IFNAME}"],15m)>({$JUNIPER.MX.NET.IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Juniper MX by NETCONF/juniper.mx.net.if.speed["{#IFNAME}"])) and last(/Juniper MX by NETCONF/juniper.mx.net.if.speed["{#IFNAME}"])>0`|Warning|**Depends on**:<br><ul><li>Juniper MX: Interface [{#IFNAME}][{#IFDESCR}]: Link down</li></ul>|

### LLD rule Multi-lane DOM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Multi-lane DOM discovery|<p>Used for retrieving information about the Digital Optical Monitoring lane SFF optical Module from NETCONF.</p>|Dependent item|juniper.mx.dom.lane.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Multi-lane DOM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Get metrics data|<p>Get metrics data\|<p>Gets module data for physical interface '[{#SFPIFNAME}]' and lane '[{#LANEID}]'.</p>|Dependent item|juniper.mx.dom.get["{#SFPIFNAME}","{#LANEID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Rx optical power|<p>Received optical power for physical interface '[{#SFPIFNAME}]', line '[{#LANEID}]'.</p>|Dependent item|juniper.mx.dom.rx.lane.laser["{#SFPIFNAME}","{#LANEID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rx_power`</p></li></ul>|
|SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Tx optical power|<p>Transmitted optical power for physical interface '[{#SFPIFNAME}]', line '[{#LANEID}]'.</p>|Dependent item|juniper.mx.dom.tx.lane.laser["{#SFPIFNAME}","{#LANEID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_power`</p></li></ul>|
|SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Module alarms|<p>Gets module alarms for physical interface '[{#SFPIFNAME}]', line '[{#LANEID}]'.</p>|Dependent item|juniper.dom.alarms.get["{#SFPIFNAME}","{#LANEID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Multi-lane DOM discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Rx power high|<p>Receiver laser power: high alarm threshold.</p>|`jsonpath(last(/Juniper MX by NETCONF/juniper.dom.alarms.get["{#SFPIFNAME}","{#LANEID}"]),"$.rx_high")="on"`|Average|**Manual close**: Yes|
|Juniper MX: SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Rx power low|<p>Receiver laser power: low alarm threshold.</p>|`jsonpath(last(/Juniper MX by NETCONF/juniper.dom.alarms.get["{#SFPIFNAME}","{#LANEID}"]),"$.rx_low")="on"`|Average|**Manual close**: Yes|
|Juniper MX: SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Laser bias high|<p>Transmitter laser bias current: high alarm threshold.</p>|`jsonpath(last(/Juniper MX by NETCONF/juniper.dom.alarms.get["{#SFPIFNAME}","{#LANEID}"]),"$.bias_high")="on"`|Average|**Manual close**: Yes|
|Juniper MX: SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Laser bias low|<p>Transmitter laser bias current: low alarm threshold.</p>|`jsonpath(last(/Juniper MX by NETCONF/juniper.dom.alarms.get["{#SFPIFNAME}","{#LANEID}"]),"$.bias_low")="on"`|Average|**Manual close**: Yes|
|Juniper MX: SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Tx power high|<p>Transmitter laser power: high alarm threshold.</p>|`jsonpath(last(/Juniper MX by NETCONF/juniper.dom.alarms.get["{#SFPIFNAME}","{#LANEID}"]),"$.tx_high")="on"`|Warning|**Manual close**: Yes|
|Juniper MX: SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Tx power low|<p>Transmitter laser power: low alarm threshold.</p>|`jsonpath(last(/Juniper MX by NETCONF/juniper.dom.alarms.get["{#SFPIFNAME}","{#LANEID}"]),"$.tx_low")="on"`|Warning|**Manual close**: Yes|
|Juniper MX: SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Tx laser disabled|<p>Transmitter laser: disabled alarm.</p>|`jsonpath(last(/Juniper MX by NETCONF/juniper.dom.alarms.get["{#SFPIFNAME}","{#LANEID}"]),"$.tx_laser_disabled")="on"`|Warning|**Manual close**: Yes|
|Juniper MX: SFP [{#SFPIFNAME}] Lane [{#LANEID}]: Tx loss of signal functionality|<p>Transmitter laser: loss of signal functionality alarm.</p>|`jsonpath(last(/Juniper MX by NETCONF/juniper.dom.alarms.get["{#SFPIFNAME}","{#LANEID}"]),"$.tx_loss_signal")="on"`|Warning|**Manual close**: Yes|

### LLD rule BGP Router discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP Router discovery|<p>BGP router discovery and information retrieval.</p>|Dependent item|juniper.mx.bgp.router.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for BGP Router discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP Router [{#BGP_ROUTER_NAME}]: Down peers count|<p>Gets the number of down peers on router '[{#BGP_ROUTER_NAME}]'.</p>|Dependent item|juniper.mx.bgp.router.peer.down["{#BGP_ROUTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for BGP Router discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}]: Down peers is equal to peers|<p>The number of down peers is equal to the number of peers on the router '[{#BGP_ROUTER_NAME}]'. For information on checking BGP configuration, see: https://www.juniper.net/documentation/us/en/software/junos/bgp/topics/topic-map/troubleshooting-bgp-sessions.html.</p>|`last(/Juniper MX by NETCONF/juniper.mx.bgp.router.peer.down["{#BGP_ROUTER_NAME}"]) = {#BGP_PEER_COUNT}`|High||

### LLD rule BGP Prefix counter discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP Prefix counter discovery|<p>BGP RIB router discovery.</p>|Dependent item|juniper.mx.bgp.prefix.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for BGP Prefix counter discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}] RIB [{#BGP_RIB_NAME}]: Get metrics data|<p>Gets data for RIB '[{#BGP_RIB_NAME}]'.</p>|Dependent item|juniper.mx.bgp.prefix.get["{#BGP_ROUTER_NAME}","{#BGP_PEER_REMOTE_ADDR}","{#BGP_PEER_REMOTE_AS}","{#BGP_RIB_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}] RIB [{#BGP_RIB_NAME}]: Suppressed prefixes|<p>The number of suppressed prefixes for a peer.</p>|Dependent item|juniper.mx.bgp.prefix.suppressed["{#BGP_ROUTER_NAME}","{#BGP_PEER_REMOTE_ADDR}","{#BGP_PEER_REMOTE_AS}","{#BGP_RIB_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.accepted`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}] RIB [{#BGP_RIB_NAME}]: Accepted prefixes|<p>The number of prefixes for a peer that are installed in the Adj-RIBs-In and are eligible to become active in the Loc-RIB.</p>|Dependent item|juniper.mx.bgp.prefix.accepted["{#BGP_ROUTER_NAME}","{#BGP_PEER_REMOTE_ADDR}","{#BGP_PEER_REMOTE_AS}","{#BGP_RIB_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.accepted`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}] RIB [{#BGP_RIB_NAME}]: Received prefixes|<p>The number of prefixes received from a peer and stored in the Adj-RIBs-In for that peer.</p>|Dependent item|juniper.mx.bgp.prefix.received["{#BGP_ROUTER_NAME}","{#BGP_PEER_REMOTE_ADDR}","{#BGP_PEER_REMOTE_AS}","{#BGP_RIB_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.received`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}] RIB [{#BGP_RIB_NAME}]: Active prefixes|<p>The number of prefixes active from a peer.</p>|Dependent item|juniper.mx.bgp.prefix.active["{#BGP_ROUTER_NAME}","{#BGP_PEER_REMOTE_ADDR}","{#BGP_PEER_REMOTE_AS}","{#BGP_RIB_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule BGP Peer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP Peer discovery|<p>BGP peer discovery.</p>|Dependent item|juniper.mx.bgp.peer.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for BGP Peer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}]: Get metrics data|<p>Gets BGP raw data for router '[{#BGP_ROUTER_NAME}]'.</p>|Dependent item|juniper.mx.bgp.get["{#BGP_ROUTER_NAME}","{#BGP_PEER_REMOTE_ADDR}","{#BGP_PEER_REMOTE_AS}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}]: State|<p>The remote BGP peer's FSM state.</p>|Dependent item|juniper.mx.bgp.state["{#BGP_ROUTER_NAME}","{#BGP_PEER_REMOTE_ADDR}","{#BGP_PEER_REMOTE_AS}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.peer_state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}]: Established time|<p>This timer indicates how long (in seconds) this peer has been in the Established state or how long since this peer was last in the Established state. It is set to zero when a new peer is configured or the router is booted.</p>|Dependent item|juniper.mx.bgp.elapsed.time["{#BGP_ROUTER_NAME}","{#BGP_PEER_REMOTE_ADDR}","{#BGP_PEER_REMOTE_AS}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.elapsed_time`</p></li></ul>|
|BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}]: Flap count|<p>Flap count is the total number of BGP session flaps from a router.</p>|Dependent item|juniper.mx.bgp.flap.count["{#BGP_ROUTER_NAME}","{#BGP_PEER_REMOTE_ADDR}","{#BGP_PEER_REMOTE_AS}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.flap_count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for BGP Peer discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: BGP Router [{#BGP_ROUTER_NAME}] AS [{#BGP_PEER_REMOTE_AS}] Peer [{#BGP_PEER_REMOTE_ADDR}]: Is down|<p>Session BGP Router '[{#BGP_ROUTER_NAME}]', AS '[{#BGP_PEER_REMOTE_AS}]', peer '[{#BGP_PEER_REMOTE_ADDR}]' is down, check BGP configuration.<br>For information on checking BGP configuration, see: https://www.juniper.net/documentation/us/en/software/junos/bgp/topics/topic-map/troubleshooting-bgp-sessions.html.</p>|`count(/Juniper MX by NETCONF/juniper.mx.bgp.state["{#BGP_ROUTER_NAME}","{#BGP_PEER_REMOTE_ADDR}","{#BGP_PEER_REMOTE_AS}"],#3,"regexp","{$JUNIPER.MX.BGP.PEER.STATE}")=0`|High||

### LLD rule OSPF Neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF Neighbor discovery|<p>OSPF neighbor discovery.</p>|Dependent item|juniper.mx.ospf.neighbor.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for OSPF Neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF Neighbor [{#OSPF_NEIGHBOR_ADDR}]: Get metrics data|<p>Gets OSPF raw data for neighbor '[{#OSPF_NEIGHBOR_ADDR}]'.</p>|Dependent item|juniper.mx.ospf.get["{#OSPF_ROUTER_NAME}","{#OSPF_NEIGHBOR_ADDR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OSPF Neighbor [{#OSPF_NEIGHBOR_ADDR}]: State|<p>The state of the relationship with this neighbor.</p>|Dependent item|juniper.mx.ospf.state["{#OSPF_ROUTER_NAME}","{#OSPF_NEIGHBOR_ADDR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.neighbor_state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|OSPF Neighbor [{#OSPF_NEIGHBOR_ADDR}]: Interface|<p>The OSPF interface.</p>|Dependent item|juniper.mx.ospf.interface["{#OSPF_ROUTER_NAME}","{#OSPF_NEIGHBOR_ADDR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.interface_name`</p></li></ul>|
|OSPF Neighbor [{#OSPF_NEIGHBOR_ADDR}]: Uptime|<p>The OSPF uptime.</p>|Dependent item|juniper.mx.ospf.uptime["{#OSPF_ROUTER_NAME}","{#OSPF_NEIGHBOR_ADDR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p></li></ul>|

### Trigger prototypes for OSPF Neighbor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: OSPF Neighbor [{#OSPF_NEIGHBOR_ADDR}]: State down|<p>OSPF neighbor '[{#OSPF_NEIGHBOR_ADDR}]' is in operational state `down`.</p>|`last(/Juniper MX by NETCONF/juniper.mx.ospf.state["{#OSPF_ROUTER_NAME}","{#OSPF_NEIGHBOR_ADDR}"]) = 2`|Average||
|Juniper MX: OSPF Neighbor [{#OSPF_NEIGHBOR_ADDR}]: State init|<p>OSPF neighbor '[{#OSPF_NEIGHBOR_ADDR}]' is in operational state `init`.</p>|`last(/Juniper MX by NETCONF/juniper.mx.ospf.state["{#OSPF_ROUTER_NAME}","{#OSPF_NEIGHBOR_ADDR}"]) = 6`|Average||
|Juniper MX: OSPF has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Juniper MX by NETCONF/juniper.mx.ospf.uptime["{#OSPF_ROUTER_NAME}","{#OSPF_NEIGHBOR_ADDR}"])<10m`|Warning||

### LLD rule OSPFv3 Neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPFv3 Neighbor discovery|<p>OSPFv3 neighbor discovery.</p>|Dependent item|juniper.mx.ospf3.neighbor.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for OSPFv3 Neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPFv3 Neighbor [{#OSPFV3_NEIGHBOR_ADDR}]: Get metrics data|<p>Gets OSPFv3 raw data for neighbor '[{#OSPFV3_NEIGHBOR_ADDR}]'.</p>|Dependent item|juniper.mx.ospf3.get["{#OSPFV3_ROUTER_NAME}","{#OSPFV3_NEIGHBOR_ADDR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OSPFv3 Neighbor [{#OSPFV3_NEIGHBOR_ADDR}]: State|<p>The state of the relationship with this neighbor.</p>|Dependent item|juniper.mx.ospf3.state["{#OSPFV3_ROUTER_NAME}","{#OSPFV3_NEIGHBOR_ADDR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.neighbor_state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|OSPFv3 Neighbor [{#OSPFV3_NEIGHBOR_ADDR}]: Interface|<p>The OSPFv3 interface.</p>|Dependent item|juniper.mx.ospf3.interface["{#OSPFV3_ROUTER_NAME}","{#OSPFV3_NEIGHBOR_ADDR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.interface_name`</p></li></ul>|
|OSPFv3 Neighbor [{#OSPFV3_NEIGHBOR_ADDR}]: Up time|<p>The OSPFv3 uptime.</p>|Dependent item|juniper.mx.ospf3.uptime["{#OSPFV3_ROUTER_NAME}","{#OSPFV3_NEIGHBOR_ADDR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p></li></ul>|

### Trigger prototypes for OSPFv3 Neighbor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: OSPFv3 Neighbor [{#OSPFV3_NEIGHBOR_ADDR}]: State down|<p>OSPFv3 neighbor '[{#OSPFV3_NEIGHBOR_ADDR}]' is in operational state `down`.</p>|`last(/Juniper MX by NETCONF/juniper.mx.ospf3.state["{#OSPFV3_ROUTER_NAME}","{#OSPFV3_NEIGHBOR_ADDR}"]) = 2`|Average||
|Juniper MX: OSPFv3 Neighbor [{#OSPFV3_NEIGHBOR_ADDR}]: State init|<p>OSPFv3 neighbor '[{#OSPFV3_NEIGHBOR_ADDR}]' is in operational state `init`.</p>|`last(/Juniper MX by NETCONF/juniper.mx.ospf3.state["{#OSPFV3_ROUTER_NAME}","{#OSPFV3_NEIGHBOR_ADDR}"]) = 6`|Average||
|Juniper MX: OSPFv3 has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Juniper MX by NETCONF/juniper.mx.ospf3.uptime["{#OSPFV3_ROUTER_NAME}","{#OSPFV3_NEIGHBOR_ADDR}"])<10m`|Warning||

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>The discovery of mounted filesystems with different types.</p>|Dependent item|juniper.mx.fs.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FS [{#FSNAME}] Mounted [{#MOUNT}]: Get data|<p>Intermediate data of filesystem '[{#FSNAME}]' filesystem. Mounted on '[{#MOUNT}]'.</p>|Dependent item|juniper.mx.fs.get["{#FSNAME}","{#MOUNT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}] Mounted [{#MOUNT}]: Space: Total|<p>Total space expressed in bytes.</p>|Dependent item|juniper.mx.fs.size["{#FSNAME}","{#MOUNT}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["total-blocks"]["#text"]`</p></li><li><p>Custom multiplier: `512`</p></li></ul>|
|FS [{#FSNAME}] Mounted [{#MOUNT}]: Space: Available|<p>Available storage space expressed in bytes.</p>|Dependent item|juniper.mx.fs.size["{#FSNAME}","{#MOUNT}",free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["available-blocks"]["#text"]`</p></li><li><p>Custom multiplier: `512`</p></li></ul>|
|FS [{#FSNAME}] Mounted [{#MOUNT}]: Space: Used|<p>Used storage space expressed in bytes.</p>|Dependent item|juniper.mx.fs.size["{#FSNAME}","{#MOUNT}",used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["used-blocks"]["#text"]`</p></li><li><p>Custom multiplier: `512`</p></li></ul>|
|FS [{#FSNAME}] Mounted [{#MOUNT}]: Space: Used, in %|<p>Used storage space expressed in percent.</p>|Dependent item|juniper.mx.fs.size["{#FSNAME}","{#MOUNT}",pused]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["used-percent"]`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: FS [{#FSNAME}] Mounted [{#MOUNT}]: Disk space is critically low|<p>The volume's space usage exceeds the '{$JUNIPER.MX.FS.FREE.MIN.CRIT:"{#FSNAME}"}%' limit;<br>The trigger expression is based on the current used and maximum available spaces.<br>The event name represents the total volume space, which can differ from the maximum available space, depending on the filesystem type.</p>|`min(/Juniper MX by NETCONF/juniper.mx.fs.size["{#FSNAME}","{#MOUNT}",pused],5m)>{$JUNIPER.MX.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`|Average|**Manual close**: Yes|
|Juniper MX: FS [{#FSNAME}] Mounted [{#MOUNT}]: Disk space is low|<p>The storage space usage exceeds the '{$JUNIPER.MX.FS.PUSED.MAX.WARN:"{#FSNAME}"}%' limit.<br>The trigger expression is based on the current used and maximum available spaces.<br>The event name represents the total volume space, which can differ from the maximum available space, depending on the filesystem type.</p>|`min(/Juniper MX by NETCONF/juniper.mx.fs.size["{#FSNAME}","{#MOUNT}",pused],5m)>{$JUNIPER.MX.FS.PUSED.MAX.WARN:"{#FSNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Juniper MX: FS [{#FSNAME}] Mounted [{#MOUNT}]: Disk space is critically low</li></ul>|

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>Scanning `show chassis fan` to detect fans.</p>|Dependent item|juniper.mx.fan.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Module [{#NAME}]: Get data|<p>Intermediate data of power entry module '[{#NAME}]'.</p>|Dependent item|juniper.mx.fan.get["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.name=='{#NAME}')].first()`</p></li></ul>|
|Module [{#NAME}]: Status|<p>Current status of fan tray '[{#NAME}]'.</p>|Dependent item|juniper.mx.fan.status["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Module [{#NAME}]: Percentage speed|<p>Current percentage of the '[{#NAME}]' speed being used.</p>|Dependent item|juniper.mx.fan.rpm.percent["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["rpm-percent"]`</p></li><li><p>Right trim: `%`</p></li></ul>|
|Module [{#NAME}]: Fan speed|<p>Fan speed in revolutions per minute (RPM).</p>|Dependent item|juniper.mx.fan.rpm["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["comment"]`</p></li><li><p>Right trim: `RPM`</p></li></ul>|

### LLD rule PEM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PEM discovery|<p>Scanning `show chassis environment pem` to detect power entry modules.</p>|Dependent item|juniper.mx.pem.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for PEM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Module [{#NAME}]: Get data|<p>Intermediate data of power entry module '[{#NAME}]'.</p>|Dependent item|juniper.mx.pem.get["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.name=='{#NAME}')].first()`</p></li></ul>|
|Module [{#NAME}]: State|<p>Status of power entry module '[{#NAME}]'.</p>|Dependent item|juniper.mx.pem.state["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Module [{#NAME}]: Voltage|<p>Information about voltage supplied to the PEM.</p>|Dependent item|juniper.mx.pem.voltage["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["dc-information"]["dc-detail"]["str3-dc-voltage"]`</p></li></ul>|
|Module [{#NAME}]: Load|<p>Information about the load on the power supply; expressed as a percentage of the rated current being used.</p>|Dependent item|juniper.mx.pem.load["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["dc-information"]["dc-detail"]["dc-load"]`</p></li></ul>|
|Module [{#NAME}]: Current|<p>Information about the PEM current.</p>|Dependent item|juniper.mx.pem.current["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["dc-information"]["dc-detail"]["str3-dc-current"]`</p></li></ul>|
|Module [{#NAME}]: Power|<p>Information about the PEM power.</p>|Dependent item|juniper.mx.pem.power["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["dc-information"]["dc-detail"]["dc-power"]`</p></li></ul>|

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>Scanning `show chassis environment` for temperature.</p>|Dependent item|juniper.mx.temperature.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#NAME}]: Temperature|<p>Temperature of air flowing in degrees Celsius (C).</p>|Dependent item|juniper.mx.temperature["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.name=='{#NAME}')].temperature["@celsius"].first()`</p></li></ul>|

### LLD rule Alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Alarm discovery|<p>Scanning `show system alarms` for alarms.</p>|Dependent item|juniper.mx.alarm.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Alarm [{#ALARM_NAME}]: Get data|<p>Gets system alarm data about the state and the alarm reason.</p>|Dependent item|juniper.mx.alarm.get.data["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Alarm [{#ALARM_NAME}]: Severity|<p>Alarms can be categorized in one of four severities: critical, major, minor, and info.</p><p>Alarm description: '[{#ALARM_DESCR}]'</p>|Dependent item|juniper.mx.alarm.severity["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["alarm-detail"]["alarm-class"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Alarm discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: [{#ALARM_NAME}] has 'Major' state|<p>Alarm '[{#ALARM_NAME}]' is of the severity `Major`.<br>Reason: '[{#ALARM_DESCR}]'</p>|`last(/Juniper MX by NETCONF/juniper.mx.alarm.severity["{#ALARM_NAME}"])=3`|Average||
|Juniper MX: [{#ALARM_NAME}] has 'Critical' state|<p>Alarm '[{#ALARM_NAME}]' is of the severity `Critical`.<br>Reason: '[{#ALARM_DESCR}]'</p>|`last(/Juniper MX by NETCONF/juniper.mx.alarm.severity["{#ALARM_NAME}"])=4`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

