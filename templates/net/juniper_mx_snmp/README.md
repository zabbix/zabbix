
# Juniper MX by SNMP

## Overview

This template is designed for the effortless deployment of Juniper MX monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Juniper MX204 Edge Router, JUNOS 24.2R1-S1.10

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$JUNIPER.MX.IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|
|{$JUNIPER.MX.IF.UTIL.MAX}|<p>Used as a threshold in the interface utilization trigger.</p>|`90`|
|{$JUNIPER.MX.IFCONTROL}|<p>Link status trigger will be fired only for interfaces where the context macro equals "1".</p>|`1`|
|{$JUNIPER.MX.NET.IF.IFNAME.MATCHES}|<p>Used for network interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$JUNIPER.MX.NET.IF.IFNAME.NOT_MATCHES}|<p>Filters out loopbacks, nulls, docker `veth` links, and `docker0 bridge` by default.</p>|`Macro too long. Please see the template.`|
|{$JUNIPER.MX.NET.IF.IFOPERSTATUS.MATCHES}|<p>Used for network interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$JUNIPER.MX.NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignores `notPresent(6)`</p>|`^6$`|
|{$JUNIPER.MX.NET.IF.IFADMINSTATUS.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`^.*`|
|{$JUNIPER.MX.NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>Ignores `down(2)` administrative status</p>|`^2$`|
|{$JUNIPER.MX.NET.IF.IFDESCR.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`.*`|
|{$JUNIPER.MX.NET.IF.IFDESCR.NOT_MATCHES}|<p>Used in network interface discovery rule filters.</p>|`CHANGE_IF_NEEDED`|
|{$JUNIPER.MX.NET.IF.IFALIAS.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`.*`|
|{$JUNIPER.MX.NET.IF.IFALIAS.NOT_MATCHES}|<p>Used in network interface discovery rule filters.</p>|`CHANGE_IF_NEEDED`|
|{$JUNIPER.MX.NET.IF.IFTYPE.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`.*`|
|{$JUNIPER.MX.NET.IF.IFTYPE.NOT_MATCHES}|<p>Used in network interface discovery rule filters.</p>|`CHANGE_IF_NEEDED`|
|{$JUNIPER.MX.TEMP_CRIT}|<p>Threshold of temperature sensor for trigger. Can be used with interface name as context.</p>|`60`|
|{$JUNIPER.MX.TEMP_CRIT_LOW}|<p>Threshold of temperature sensor for trigger. Can be used with interface name as context.</p>|`5`|
|{$JUNIPER.MX.TEMP_WARN}|<p>Threshold of temperature sensor for trigger. Can be used with interface name as context.</p>|`50`|
|{$JUNIPER.MX.TEMP_CRIT:"Routing Engine"}|<p>Threshold of temperature sensor for trigger. Used for Routing Engine.</p>|`80`|
|{$JUNIPER.MX.TEMP_WARN:"Routing Engine"}|<p>Threshold of temperature sensor for trigger. Used for Routing Engine.</p>|`70`|
|{$JUNIPER.MX.FAN_CRIT_STATUS}|<p>Threshold of status sensor for trigger. All statuses defined in valuemap `JUNIPER-MIB::jnxOperatingState`.</p>|`6`|
|{$JUNIPER.MX.PSU_CRIT_STATUS}|<p>Threshold of status sensor for trigger. All statuses defined in valuemap `JUNIPER-MIB::jnxOperatingState`.</p>|`6`|
|{$JUNIPER.MX.MEMORY.UTIL.MAX}|<p>Threshold of memory utilization for trigger in %. Can be used with interface name as context.</p>|`90`|
|{$JUNIPER.MX.CPU.UTIL.CRIT}|<p>Threshold of CPU utilization for trigger in %. Can be used with interface name as context.</p>|`90`|
|{$JUNIPER.MX.SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$JUNIPER.MX.ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$JUNIPER.MX.ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ICMP ping||Simple check|icmpping|
|ICMP loss||Simple check|icmppingloss|
|ICMP response time||Simple check|icmppingsec|
|Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>Time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|juniper.mx.system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from `sysUpTime` in the SNMPv2-MIB [RFC1907] because `sysUpTime` is the uptime of the network management portion of the system.</p>|SNMP agent|juniper.mx.system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other `snmptrap` items.</p>|SNMP trap|snmptrap.fallback|
|System location|<p>MIB: SNMPv2-MIB</p><p>Physical location of the node (e.g., `equipment room`, `3rd floor`). If not provided, the value is a zero-length string.</p>|SNMP agent|juniper.mx.system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System contact details|<p>MIB: SNMPv2-MIB</p><p>Name and contact information of the contact person for the node. If not provided, the value is a zero-length string.</p>|SNMP agent|juniper.mx.system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the entity as part of the vendor's SMI enterprises subtree with the prefix 1.3.6.1.4.1 (e.g., a vendor with the identifier 1.3.6.1.4.1.4242 might assign a system object with the OID 1.3.6.1.4.1.4242.1.1).</p>|SNMP agent|juniper.mx.system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node. By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is a zero-length string.</p>|SNMP agent|juniper.mx.system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should include the full name and version identification of the system's hardware type, software operating system, and networking software.</p>|SNMP agent|juniper.mx.system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|Hardware serial number|<p>MIB: JUNIPER-MIB</p><p>The serial number of this subject, blank if unknown or unavailable.</p>|SNMP agent|juniper.mx.system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Hardware model name|<p>MIB: JUNIPER-MIB</p><p>The name, model, or detailed description of the device, indicating which product it represents, for example, `M40`.</p>|SNMP agent|juniper.mx.system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Operating system|<p>MIB: SNMPv2-MIB</p>|SNMP agent|juniper.mx.system.sw.os[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Regular expression: `kernel (JUNOS [0-9a-zA-Z\.\-]+) \1`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|SNMP walk Operating Table|<p>Scanning `JUNIPER-MIB::jnxOperatingTable` for CPU, Memory, Temperature, and Fans.</p>|SNMP agent|juniper.mx.operating.snmp.walk|
|SNMP walk Redundancy Table|<p>Scanning `JUNIPER-MIB::jnxRedundancyTable` for Router Redundancy.</p>|SNMP agent|juniper.mx.redundancy.table.snmp.walk|
|SNMP walk EtherLike-MIB interfaces|<p>Discovery of interfaces from IF-MIB and EtherLike-MIB. Interfaces with operational status `up(1)` are discovered.</p>|SNMP agent|juniper.mx.net.if.duplex.snmp.walk|
|SNMP walk Multi-lane digital optical monitoring|<p>Scanning `JUNIPER-DOM-MIB::jnxDomModuleLaneTable` for multi-lane digital optical monitoring.</p>|SNMP agent|juniper.mx.dom.snmp.walk|
|SNMP walk Network interfaces|<p>Discovery of interfaces from IF-MIB.</p>|SNMP agent|juniper.mx.net.if.snmp.walk|
|SNMP walk BGP Peer|<p>Scanning `BGP4-V2-MIB-JUNIPER::jnxBgpM2PeerData` for BGP Peer Data.</p>|SNMP agent|juniper.mx.bgp.peer.data.snmp.walk|
|SNMP walk BGP Prefix Counters|<p>Scanning `BGP4-V2-MIB-JUNIPER::jnxBgpM2PrefixCountersTable` for Prefix Counters.</p>|SNMP agent|juniper.mx.bgp.prefix.counters.snmp.walk|
|SNMP walk OSPF Neighbors|<p>Scanning `OSPF-MIB::ospfNbrTable` for OSPF Neighbors.</p>|SNMP agent|juniper.mx.ospf.nbr.snmp.walk|
|SNMP walk OSPFv3 Neighbors|<p>Scanning `OSPFV3-MIB-JUNIPER::jnxOspfv3NbrTable` for OSPFv3 Neighbors.</p>|SNMP agent|juniper.mx.ospfv3.nbr.snmp.walk|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Juniper MX by SNMP/icmpping,#3)=0`|High||
|Juniper MX: High ICMP ping loss|<p>ICMP packet loss detected.</p>|`min(/Juniper MX by SNMP/icmppingloss,5m)>{$JUNIPER.MX.ICMP_LOSS_WARN} and min(/Juniper MX by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Juniper MX: Unavailable by ICMP ping</li></ul>|
|Juniper MX: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Juniper MX by SNMP/icmppingsec,5m)>{$JUNIPER.MX.ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Juniper MX: High ICMP ping loss</li><li>Juniper MX: Unavailable by ICMP ping</li></ul>|
|Juniper MX: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Juniper MX by SNMP/juniper.mx.system.hw.uptime[hrSystemUptime.0])>0 and last(/Juniper MX by SNMP/juniper.mx.system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Juniper MX by SNMP/juniper.mx.system.hw.uptime[hrSystemUptime.0])=0 and last(/Juniper MX by SNMP/juniper.mx.system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Juniper MX: No SNMP data collection</li></ul>|
|Juniper MX: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Juniper MX by SNMP/juniper.mx.system.name,#1)<>last(/Juniper MX by SNMP/juniper.mx.system.name,#2) and length(last(/Juniper MX by SNMP/juniper.mx.system.name))>0`|Info|**Manual close**: Yes|
|Juniper MX: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Juniper MX by SNMP/zabbix[host,snmp,available],{$JUNIPER.MX.SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Juniper MX: Unavailable by ICMP ping</li></ul>|
|Juniper MX: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Juniper MX by SNMP/juniper.mx.system.hw.serialnumber,#1)<>last(/Juniper MX by SNMP/juniper.mx.system.hw.serialnumber,#2) and length(last(/Juniper MX by SNMP/juniper.mx.system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Juniper MX: Operating system description has changed|<p>Operating system description has changed. Possible reasons - system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/Juniper MX by SNMP/juniper.mx.system.sw.os[sysDescr.0],#1)<>last(/Juniper MX by SNMP/juniper.mx.system.sw.os[sysDescr.0],#2) and length(last(/Juniper MX by SNMP/juniper.mx.system.sw.os[sysDescr.0]))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Juniper MX: System name has changed</li></ul>|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of interfaces from IF-MIB.</p>|Dependent item|juniper.mx.net.if.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IFNAME}][{#IFALIAS}]: Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The `testing(3)` state indicates that no operational packet scan can be passed.</p><p>- If `ifAdminStatus` is `down(2)`, then `ifOperStatus` should be `down(2)`.</p><p>- If `ifAdminStatus` is changed to `up(1)`, then `ifOperStatus` should change to `up(1)` if the interface is ready to transmit and receive network traffic.</p><p>- It should change to `dormant(5)` if the interface is waiting for external actions (such as a serial line waiting for an incoming connection).</p><p>- It should remain in the `down(2)` state if and only if there is a fault that prevents it from going to the `up(1)` state.</p><p>- It should remain in the `notPresent(6)` state if the interface has missing (typically, hardware) components.</p>|Dependent item|juniper.mx.net.if.status[ifOperStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.8.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of `ifInOctets`. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|juniper.mx.net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.6.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of `ifOutOctets`. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|juniper.mx.net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.10.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|juniper.mx.net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.14.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|juniper.mx.net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.20.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|juniper.mx.net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.19.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|juniper.mx.net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.13.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for `ifType` are assigned by the Internet Assigned Numbers Authority (IANA), through updating the syntax of the IANAifType textual convention.</p>|Dependent item|juniper.mx.net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n`, then the speed of the interface is somewhere in the range of `n-500,000` to `n+499,999`.</p><p>For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth.</p><p>For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|Dependent item|juniper.mx.net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.15.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: Interface [{#IFNAME}][{#IFALIAS}]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$JUNIPER.MX.IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of `.diff`.</p>|`{$JUNIPER.MX.IFCONTROL:"{#IFNAME}"}=1 and last(/Juniper MX by SNMP/juniper.mx.net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Juniper MX by SNMP/juniper.mx.net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Juniper MX by SNMP/juniper.mx.net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Juniper MX: Interface [{#IFNAME}][{#IFALIAS}]: High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Juniper MX by SNMP/juniper.mx.net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$JUNIPER.MX.IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Juniper MX by SNMP/juniper.mx.net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Juniper MX by SNMP/juniper.mx.net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$JUNIPER.MX.IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Juniper MX by SNMP/juniper.mx.net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Juniper MX by SNMP/juniper.mx.net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Juniper MX: Interface [{#IFNAME}][{#IFALIAS}]: Link down</li></ul>|
|Juniper MX: Interface [{#IFNAME}][{#IFALIAS}]: High error rate|<p>It recovers when it is below 80% of the `{$JUNIPER.MX.IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Juniper MX by SNMP/juniper.mx.net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$JUNIPER.MX.IF.ERRORS.WARN:"{#IFNAME}"} or min(/Juniper MX by SNMP/juniper.mx.net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$JUNIPER.MX.IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Juniper MX: Interface [{#IFNAME}][{#IFALIAS}]: Link down</li></ul>|
|Juniper MX: Interface [{#IFNAME}][{#IFALIAS}]: Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Juniper MX by SNMP/juniper.mx.net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Juniper MX by SNMP/juniper.mx.net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Juniper MX by SNMP/juniper.mx.net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Juniper MX by SNMP/juniper.mx.net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Juniper MX by SNMP/juniper.mx.net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Juniper MX by SNMP/juniper.mx.net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Juniper MX by SNMP/juniper.mx.net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Juniper MX by SNMP/juniper.mx.net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Juniper MX by SNMP/juniper.mx.net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Juniper MX: Interface [{#IFNAME}][{#IFALIAS}]: Link down</li></ul>|

### LLD rule CPU and Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU and Memory discovery|<p>Scanning `JUNIPER-MIB::jnxOperatingTable` for CPU and Memory.</p><p>http://kb.juniper.net/InfoCenter/index?page=content&id=KB17526&actp=search. Filter limits results to Routing Engines.</p>|Dependent item|juniper.mx.cpu.mem.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for CPU and Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|System [{#SNMPVALUE}]: CPU utilization|<p>MIB: JUNIPER-MIB</p><p>The CPU utilization, in percent, of this subject. Zero if unavailable or inapplicable.</p><p>Reference: http://kb.juniper.net/library/CUSTOMERSERVICE/GLOBAL_JTAC/BK26199/SRX%20SNMP%20Monitoring%20Guide_v1.1.pdf</p>|Dependent item|juniper.mx.cpu.util[jnxOperatingCPU.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.3.1.13.1.8.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|System [{#SNMPVALUE}]: Memory utilization|<p>MIB: JUNIPER-MIB</p><p>The buffer pool utilization, in percent, of this subject. Zero if unavailable or inapplicable.</p><p>Reference: http://kb.juniper.net/library/CUSTOMERSERVICE/GLOBAL_JTAC/BK26199/SRX%20SNMP%20Monitoring%20Guide_v1.1.pdf</p>|Dependent item|juniper.mx.memory.util[jnxOperatingBuffer.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.3.1.13.1.11.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|

### Trigger prototypes for CPU and Memory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: System [{#SNMPVALUE}]: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Juniper MX by SNMP/juniper.mx.cpu.util[jnxOperatingCPU.{#SNMPINDEX}],5m)>{$JUNIPER.MX.CPU.UTIL.CRIT}`|Average||
|Juniper MX: System [{#SNMPVALUE}]: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Juniper MX by SNMP/juniper.mx.memory.util[jnxOperatingBuffer.{#SNMPINDEX}],5m)>{$JUNIPER.MX.MEMORY.UTIL.MAX}`|Average||

### LLD rule Redundancy discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redundancy discovery|<p>Scanning `JUNIPER-MIB::jnxRedundancyTable`.</p>|Dependent item|juniper.mx.redundancy.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Redundancy discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Redundancy [{#SNMPVALUE}][{#CHASSISDESCR}]: Current running state|<p>MIB: JUNIPER-MIB</p><p>The current running state for the `Redundancy [{#SNMPVALUE}][{#CHASSISDESCR}]` subject.</p>|Dependent item|juniper.mx.redundancy.state["{#SNMPINDEX}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.3.1.14.1.7.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Redundancy [{#SNMPVALUE}][{#CHASSISDESCR}]: Reason of the last switchover|<p>MIB: JUNIPER-MIB</p><p>The reason of the last switchover for the `Redundancy [{#SNMPVALUE}][{#CHASSISDESCR}]` subject.</p>|Dependent item|juniper.mx.redundancy.switchover.reason["{#SNMPINDEX}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.3.1.14.1.10.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>Scanning `JUNIPER-MIB::jnxOperatingTable` for Temperature.</p>|Dependent item|juniper.mx.temperature.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#SENSOR_INFO}]: Temperature|<p>MIB: JUNIPER-MIB</p><p>The temperature in Celsius of [{#SENSOR_INFO}].</p>|Dependent item|sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.3.1.13.1.7.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: Sensor [{#SENSOR_INFO}]: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as the temperature sensor status if available.</p>|`avg(/Juniper MX by SNMP/sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}],5m)>{$JUNIPER.MX.TEMP_WARN:"{#SENSOR_INFO}"}`|Warning|**Depends on**:<br><ul><li>Juniper MX: Sensor [{#SENSOR_INFO}]: Temperature is above critical threshold</li></ul>|
|Juniper MX: Sensor [{#SENSOR_INFO}]: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as the temperature sensor status if available.</p>|`avg(/Juniper MX by SNMP/sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}],5m)>{$JUNIPER.MX.TEMP_CRIT:"{#SENSOR_INFO}"}`|High||
|Juniper MX: Sensor [{#SENSOR_INFO}]: Temperature is too low|<p>This trigger uses temperature sensor values as well as the temperature sensor status if available.</p>|`avg(/Juniper MX by SNMP/sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}],5m)<{$JUNIPER.MX.TEMP_CRIT_LOW:"{#SENSOR_INFO}"}`|Average||

### LLD rule FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN discovery|<p>Scanning `JUNIPER-MIB::jnxOperatingTable` for Fans.</p>|Dependent item|juniper.mx.fans.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#SNMPVALUE}]: Fan status|<p>MIB: JUNIPER-MIB</p><p>Current status of the Fan tray.</p>|Dependent item|juniper.mx.sensor.fan.status[jnxOperatingState.4.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.3.1.13.1.6.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|

### Trigger prototypes for FAN discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: Sensor [{#SNMPVALUE}]: Fan is in critical state|<p>Please check the Fan unit.</p>|`count(/Juniper MX by SNMP/juniper.mx.sensor.fan.status[jnxOperatingState.4.{#SNMPINDEX}],#1,"eq","{$JUNIPER.MX.FAN_CRIT_STATUS}")=1`|Average||

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>Scanning `JUNIPER-MIB::jnxOperatingTable` for Power Supplies.</p>|Dependent item|juniper.mx.psu.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#SNMPVALUE}]: Power supply status|<p>MIB: JUNIPER-MIB</p><p>If are using DC power supplies, there is a known issue on PR 1064039 where the fans do not detect the temperature correctly and fail to cool the power supply causing shutdown to occur.</p><p>This is fixed in Junos 13.3R7 https://forums.juniper.net/t5/Routing/PEM-0-not-OK-MX104/m-p/289644#M14122</p>|Dependent item|juniper.mx.sensor.psu.status["{#SNMPINDEX}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.3.1.13.1.6.2.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: Sensor [{#SNMPVALUE}]: Power supply is in critical state|<p>Please check the power supply unit for errors.</p>|`count(/Juniper MX by SNMP/juniper.mx.sensor.psu.status["{#SNMPINDEX}"],#1,"eq","{$JUNIPER.MX.PSU_CRIT_STATUS}")=1`|Average||

### LLD rule EtherLike-MIB discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|EtherLike-MIB discovery|<p>Discovery of interfaces from IF-MIB and EtherLike-MIB. Interfaces with the `up(1)` operational status are discovered.</p>|Dependent item|juniper.mx.net.if.duplex.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for EtherLike-MIB discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IFNAME}][{#IFALIAS}]: Duplex status|<p>MIB: EtherLike-MIB</p><p>The current mode of operation of the MAC entity. `unknown` indicates that the current duplex mode could not be determined.</p><p>Management control of the duplex mode is accomplished through the MAU MIB. When an interface does not support autonegotiation or when autonegotiation is not enabled, the duplex mode is controlled using `ifMauDefaultType`. When autonegotiation is supported and enabled, duplex mode is controlled using `ifMauAutoNegAdvertisedBits`. In either case, the currently operating duplex mode in reflected both in this object and in `ifMauType`.</p><p>Note that this object provides redundant information with `ifMauType`. Normally, redundant objects are discouraged. However, in this instance, it allows a management application to determine the duplex status of an interface without having to know every possible value of `ifMauType`. This was felt to be sufficiently valuable to justify the redundancy.</p><p>Reference: [IEEE 802.3 Std.], 30.3.1.1.32,aDuplexStatus.</p>|Dependent item|juniper.mx.net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.10.7.2.1.19.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for EtherLike-MIB discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: Interface [{#IFNAME}][{#IFALIAS}]: In half-duplex mode|<p>Please check autonegotiation settings and cabling.</p>|`last(/Juniper MX by SNMP/juniper.mx.net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}])=2`|Warning|**Manual close**: Yes|

### LLD rule Multi-lane DOM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Multi-lane DOM discovery|<p>Used for information about Digital Optical Monitoring for a Lane of an SFF optical module, as defined in JUNIPER-DOM-MIB.</p>|Dependent item|juniper.mx.dom.lane.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Multi-lane DOM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SFP [{#IFNAME}][{#IFALIAS}]: Rx optical power lane [{#LANEINDEX}]|<p>Receiver laser power on a particular Lane of an SFF physical interface.</p>|Dependent item|juniper.mx.dom.rx.lane.laser[jnxDomCurrentLaneRxLaserPower.{#SNMPINDEX}.{#LANEINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.3.60.1.2.1.1.6.{#PORTINDEX}.{#LANEINDEX}`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|SFP [{#IFNAME}][{#IFALIAS}]: Tx optical power lane [{#LANEINDEX}]|<p>Transmitter laser power on a particular Lane of an SFF physical interface.</p>|Dependent item|juniper.mx.dom.tx.lane.laser[jnxDomCurrentLaneTxLaserOutputPower.{#SNMPINDEX}.{#LANEINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.3.60.1.2.1.1.8.{#PORTINDEX}.{#LANEINDEX}`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|SFP [{#IFNAME}][{#IFALIAS}]: Module lane [{#LANEINDEX}] alarms|<p>This item identifies all the active DOM alarms on a particular Lane of an SFF physical interface.</p>|Dependent item|juniper.mx.dom.alarms.lane.laser[jnxDomCurrentLaneAlarms.{#SNMPINDEX}.{#LANEINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.3.60.1.2.1.1.2.{#PORTINDEX}.{#LANEINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Multi-lane DOM discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: SFP [{#IFNAME}][{#IFALIAS}]: Rx power high|<p>Receiver laser power - high alarm threshold.</p>|`jsonpath(last(/Juniper MX by SNMP/juniper.mx.dom.alarms.lane.laser[jnxDomCurrentLaneAlarms.{#SNMPINDEX}.{#LANEINDEX}]),"$.domLaneRxLaserPowerHighAlarm")="true"`|Warning||
|Juniper MX: SFP [{#IFNAME}][{#IFALIAS}]: Rx power low|<p>Receiver laser power - low alarm threshold.</p>|`jsonpath(last(/Juniper MX by SNMP/juniper.mx.dom.alarms.lane.laser[jnxDomCurrentLaneAlarms.{#SNMPINDEX}.{#LANEINDEX}]),"$.domLaneRxLaserPowerLowAlarm")="true"`|Warning||
|Juniper MX: SFP [{#IFNAME}][{#IFALIAS}]: Tx bias high|<p>Transmitter laser bias current - high alarm threshold.</p>|`jsonpath(last(/Juniper MX by SNMP/juniper.mx.dom.alarms.lane.laser[jnxDomCurrentLaneAlarms.{#SNMPINDEX}.{#LANEINDEX}]),"$.domLaneTxLaserBiasCurrentHighAlarm")="true"`|Warning||
|Juniper MX: SFP [{#IFNAME}][{#IFALIAS}]: Tx bias low|<p>Transmitter laser bias current - low alarm threshold.</p>|`jsonpath(last(/Juniper MX by SNMP/juniper.mx.dom.alarms.lane.laser[jnxDomCurrentLaneAlarms.{#SNMPINDEX}.{#LANEINDEX}]),"$.domLaneTxLaserBiasCurrentLowAlarm")="true"`|Warning||
|Juniper MX: SFP [{#IFNAME}][{#IFALIAS}]: Tx power high|<p>Transmitter laser power - high alarm threshold.</p>|`jsonpath(last(/Juniper MX by SNMP/juniper.mx.dom.alarms.lane.laser[jnxDomCurrentLaneAlarms.{#SNMPINDEX}.{#LANEINDEX}]),"$.domLaneTxLaserOutputPowerHighAlarm")="true"`|Warning||
|Juniper MX: SFP [{#IFNAME}][{#IFALIAS}]: Tx power low|<p>Transmitter laser power - low alarm threshold.</p>|`jsonpath(last(/Juniper MX by SNMP/juniper.mx.dom.alarms.lane.laser[jnxDomCurrentLaneAlarms.{#SNMPINDEX}.{#LANEINDEX}]),"$.domLaneTxLaserOutputPowerLowAlarm")="true"`|Warning||
|Juniper MX: SFP [{#IFNAME}][{#IFALIAS}]: Temperature High|<p>Module temperature - high alarm threshold.</p>|`jsonpath(last(/Juniper MX by SNMP/juniper.mx.dom.alarms.lane.laser[jnxDomCurrentLaneAlarms.{#SNMPINDEX}.{#LANEINDEX}]),"$.domLaneLaserTemperatureHighAlarm")="true"`|Warning||
|Juniper MX: SFP [{#IFNAME}][{#IFALIAS}]: Temperature Low|<p>Module temperature - low alarm threshold.</p>|`jsonpath(last(/Juniper MX by SNMP/juniper.mx.dom.alarms.lane.laser[jnxDomCurrentLaneAlarms.{#SNMPINDEX}.{#LANEINDEX}]),"$.domLaneLaserTemperatureLowAlarm")="true"`|Warning||

### LLD rule BGP Prefix counter discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP Prefix counter discovery|<p>Scanning `BGP4-V2-MIB-JUNIPER::jnxBgpM2PrefixCountersTable` for Prefix Counters.</p>|Dependent item|juniper.mx.bgp.prefix.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Set error to: `BGP instance is not running`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for BGP Prefix counter discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP [{#BGPM2_AFI_SAFI}]: Accepted prefixes|<p>The number of prefixes for a peer that are installed in the Adj-Ribs-In and are eligible to become active in the Loc-Rib.</p>|Dependent item|juniper.mx.bgp.prefix.accepted[jnxBgpM2PrefixInPrefixesAccepted.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.1.1.2.6.2.1.8.{#SNMPINDEX}`</p></li></ul>|
|BGP [{#BGPM2_AFI_SAFI}]: Advertised prefixes|<p>The number of prefixes for a peer that are installed in the peer's Adj-Ribs-Out.</p>|Dependent item|juniper.mx.bgp.prefix.advertised[jnxBgpM2PrefixOutPrefixes.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.1.1.2.6.2.1.10.{#SNMPINDEX}`</p></li></ul>|
|BGP [{#BGPM2_AFI_SAFI}]: Received prefixes|<p>The number of prefixes received from a peer and stored in the Adj-Ribs-In for that peer.</p>|Dependent item|juniper.mx.bgp.prefix.received[jnxBgpM2PrefixInPrefixes.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.1.1.2.6.2.1.7.{#SNMPINDEX}`</p></li></ul>|
|BGP [{#BGPM2_AFI_SAFI}]: Rejected prefixes|<p>The number of prefixes for a peer that are installed in the Adj-Ribs-In and are NOT eligible to become active in the Loc-Rib.</p>|Dependent item|juniper.mx.bgp.prefix.rejected[jnxBgpM2PrefixInPrefixesRejected.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.1.1.2.6.2.1.9.{#SNMPINDEX}`</p></li></ul>|
|BGP [{#BGPM2_AFI_SAFI}]: Active prefixes|<p>The number of prefixes for a peer that are installed in the Adj-Ribs-In and are the active route in the Loc-Rib.</p>|Dependent item|juniper.mx.bgp.prefix.active[jnxBgpM2PrefixInPrefixesActive.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.1.1.2.6.2.1.11.{#SNMPINDEX}`</p></li></ul>|

### LLD rule BGP Peer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP Peer discovery|<p>Scanning `BGP4-V2-MIB-JUNIPER::jnxBgpM2PeerData` for BGP Peer.</p>|Dependent item|juniper.mx.bgp.peer.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Set error to: `BGP instance is not running`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for BGP Peer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP AS [{#BGPM2_PEER_REMOTE_AS}] Peer [{#BGPM2_PEER_REMOTE_ADDR}]: State|<p>The remote BGP peer's FSM state.</p>|Dependent item|juniper.mx.bgp.state[jnxBgpM2PeerState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.1.1.2.1.1.1.2.{#SNMPINDEX}`</p></li></ul>|
|BGP AS [{#BGPM2_PEER_REMOTE_AS}] Peer [{#BGPM2_PEER_REMOTE_ADDR}]: Status|<p>Whether or not the BGP FSM for this remote peer is halted or running. The BGP FSM for a remote peer is halted after processing a Stop event. Likewise, it is in the running state after a Start event. The `jnxBgpM2PeerState` will generally be in the idle state when the FSM is halted, although some extensions such as Graceful Restart will leave the peer in the Idle state but with the FSM running.</p>|Dependent item|juniper.mx.bgp.status[jnxBgpM2PeerStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.1.1.2.1.1.1.3.{#SNMPINDEX}`</p></li></ul>|
|BGP AS [{#BGPM2_PEER_REMOTE_AS}] Peer [{#BGPM2_PEER_REMOTE_ADDR}]: Established time|<p>This timer indicates how long (in seconds) this peer has been in the Established state or how long since this peer was last in the Established state. It is set to zero when a new peer is configured or the router is booted.</p>|Dependent item|juniper.mx.bgp.established.time[jnxBgpM2PeerFsmEstablishedTime.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.1.1.2.4.1.1.1.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for BGP Peer discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: BGP AS [{#BGPM2_PEER_REMOTE_AS}] Peer [{#BGPM2_PEER_REMOTE_ADDR}]: is down|<p>Session [BGP AS [{#BGPM2_PEER_REMOTE_AS}] Peer [{#BGPM2_PEER_REMOTE_ADDR}]] is down, check the BGP configuration. For information on checking the BGP configuration, see https://www.juniper.net/documentation/us/en/software/junos/bgp/topics/topic-map/troubleshooting-bgp-sessions.html.</p>|`last(/Juniper MX by SNMP/juniper.mx.bgp.state[jnxBgpM2PeerState.{#SNMPINDEX}],#3)<>6 and last(/Juniper MX by SNMP/juniper.mx.bgp.status[jnxBgpM2PeerStatus.{#SNMPINDEX}])=2`|High||

### LLD rule OSPF Neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF Neighbor discovery|<p>Scanning `OSPF-MIB::ospfNbrTable` for OSPF Neighbors.</p>|Dependent item|juniper.mx.ospf.neighbor.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Set error to: `OSPF instance is not running`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for OSPF Neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF Neighbor [{#OSPF_IP_ADDR}]: State|<p>The state of the relationship with this neighbor.</p>|Dependent item|juniper.mx.ospf.state[ospfNbrState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.6.{#SNMPINDEX}`</p></li></ul>|
|OSPF Neighbor [{#OSPF_IP_ADDR}]: Hello suppressed|<p>Indicates whether Hellos are being suppressed to the neighbor.</p>|Dependent item|juniper.mx.ospf.hello.suppressed[ospfNbrHelloSuppressed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.11.{#SNMPINDEX}`</p></li></ul>|
|OSPF Neighbor [{#OSPF_IP_ADDR}]: Router Id|<p>A 32-bit integer (represented as a type `IpAddress`) uniquely identifying the neighboring router in the Autonomous System.</p>|Dependent item|juniper.mx.ospf.rtr.id[ospfNbrRtrId.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.3.{#SNMPINDEX}`</p></li></ul>|
|OSPF Neighbor [{#OSPF_IP_ADDR}]: Events|<p>The number of times this neighbor relationship has changed state, or an error has occurred.</p>|Dependent item|juniper.mx.ospf.events[ospfNbrEvents.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.7.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for OSPF Neighbor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: OSPF Neighbor [{#OSPF_IP_ADDR}]: State down|<p>OSPF neighbor [{#OSPF_IP_ADDR}] in operational state `down`.</p>|`last(/Juniper MX by SNMP/juniper.mx.ospf.state[ospfNbrState.{#SNMPINDEX}]) = 1`|Average||
|Juniper MX: OSPF Neighbor [{#OSPF_IP_ADDR}]: State init|<p>OSPF neighbor [{#OSPF_IP_ADDR}] in operational state `init`.</p>|`last(/Juniper MX by SNMP/juniper.mx.ospf.state[ospfNbrState.{#SNMPINDEX}]) = 3`|Average||
|Juniper MX: OSPF Neighbor [{#OSPF_IP_ADDR}]: Number of relationship has changed|<p>The number of times the [{#OSPF_IP_ADDR}] neighbor relationship has changed.</p>|`last(/Juniper MX by SNMP/juniper.mx.ospf.events[ospfNbrEvents.{#SNMPINDEX}],#1)<>last(/Juniper MX by SNMP/juniper.mx.ospf.events[ospfNbrEvents.{#SNMPINDEX}],#2)`|Warning||

### LLD rule OSPFv3 Neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPFv3 Neighbor discovery|<p>Scanning `OSPFV3-MIB-JUNIPER::jnxOspfv3NbrTable` for OSPFv3 Neighbors.</p>|Dependent item|juniper.mx.ospfv3.neighbor.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Set error to: `OSPF instance is not running`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for OSPFv3 Neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPFv3 Neighbor [{#OSPFV3_IP_ADDR}]: State|<p>The state of the relationship with this neighbor.</p>|Dependent item|juniper.mx.ospfv3.state[jnxOspfv3NbrState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.4.1.1.9.1.8.{#SNMPINDEX}`</p></li></ul>|
|OSPFv3 Neighbor [{#OSPFV3_IP_ADDR}]: Hello suppressed|<p>Indicates whether Hellos are being suppressed to the neighbor.</p>|Dependent item|juniper.mx.ospfv3.hello.suppressed[jnxOspfv3NbrHelloSuppressed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.4.1.1.9.1.11.{#SNMPINDEX}`</p></li></ul>|
|OSPFv3 Neighbor [{#OSPFV3_IP_ADDR}]: Priority|<p>The priority of this neighbor in the designated router election algorithm. The value `0` signifies that the neighbor is not eligible to become the designated router on this particular network.</p>|Dependent item|juniper.mx.ospfv3.priority[jnxOspfv3NbrPriority.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.4.1.1.9.1.7.{#SNMPINDEX}`</p></li></ul>|
|OSPFv3 Neighbor [{#OSPFV3_IP_ADDR}]: Events|<p>The number of times the [{#OSPFV3_IP_ADDR}] neighbor relationship has changed state, or an error has occurred.</p>|Dependent item|juniper.mx.ospfv3.events[jnxOspfv3NbrEvents.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2636.5.4.1.1.9.1.9.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for OSPFv3 Neighbor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper MX: OSPFv3 Neighbor [{#OSPFV3_IP_ADDR}]: State down|<p>OSPF neighbor [{#OSPFV3_IP_ADDR}] in operational state `down`.</p>|`last(/Juniper MX by SNMP/juniper.mx.ospfv3.state[jnxOspfv3NbrState.{#SNMPINDEX}]) = 1`|Average||
|Juniper MX: OSPFv3 Neighbor [{#OSPFV3_IP_ADDR}]: State init|<p>OSPFv3 neighbor [{#OSPFV3_IP_ADDR}] in operational state `init`.</p>|`last(/Juniper MX by SNMP/juniper.mx.ospfv3.state[jnxOspfv3NbrState.{#SNMPINDEX}]) = 3`|Average||
|Juniper MX: OSPFv3 Neighbor [{#OSPFV3_IP_ADDR}]: relationship has changed|<p>The number of times the [{#OSPFV3_IP_ADDR}] neighbor relationship has changed.</p>|`last(/Juniper MX by SNMP/juniper.mx.ospfv3.events[jnxOspfv3NbrEvents.{#SNMPINDEX}],#1)<>last(/Juniper MX by SNMP/juniper.mx.ospfv3.events[jnxOspfv3NbrEvents.{#SNMPINDEX}],#2)`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

