
# FortiGate by SNMP

## Overview

This template is designed for the effortless deployment of FortiGate monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- FortiGate v7.2.5

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>Threshold of CPU utilization for warning trigger in %.</p>|`90`|
|{$ICMP_LOSS_WARN}|<p>Threshold of ICMP packets loss for warning trigger in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Threshold of average ICMP response time for warning trigger in seconds.</p>|`0.15`|
|{$SNMP.TIMEOUT}|<p>The time interval for SNMP availability trigger.</p>|`5m`|
|{$MEMORY.UTIL.MAX}|<p>Threshold of memory utilization for trigger in %.</p>|`90`|
|{$DISK.FREE.MIN}|<p>Threshold of disk free space for trigger in %.</p>|`20`|
|{$IF.ERRORS.WARN}|<p>Threshold of error packets rate for warning trigger. Can be used with interface name as context.</p>|`2`|
|{$IF.UTIL.MAX}|<p>Threshold of interface bandwidth utilization for warning trigger in %. Can be used with interface name as context.</p>|`95`|
|{$IFCONTROL}|<p>Macro for operational state of the interface for "Link down" trigger. Can be used with interface name as context.</p>|`1`|
|{$NET.IF.IFADMINSTATUS.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFNAME.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`^6$`|
|{$NET.IF.IFTYPE.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FortiGate: Firmware version|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Firmware version of the device.</p>|SNMP agent|system.hw.firmware<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: Hardware model name|<p>MIB: ENTITY-MIB</p><p>Model of the device.</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: Hardware serial number|<p>MIB: ENTITY-MIB</p><p>Serial number of the device.</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person. If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FortiGate: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should include the full name and version identification of the system's hardware type, software operating system, and networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FortiGate: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet`, `3rd floor`). If the location is unknown, the value is the zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FortiGate: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node. By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FortiGate: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity. This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining 'what kind of box' is being managed. For example, if vendor 'Flintstones, Inc.' was assigned the subtree 1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its 'Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FortiGate: System uptime|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Time since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.uptime[fgSysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|FortiGate: Number of CPUs|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of processors.</p>|SNMP agent|system.cpu.num<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: CPU utilization|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>CPU utilization in %.</p>|SNMP agent|system.cpu.util[fgSysCpuUsage.0]|
|FortiGate: ICMP ping|<p>Host accessibility by ICMP.</p><p>0 - ICMP ping fails.</p><p>1 - ICMP ping successful.</p>|Simple check|icmpping|
|FortiGate: ICMP loss|<p>Percentage of lost packets.</p>|Simple check|icmppingloss|
|FortiGate: ICMP response time|<p>ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|
|FortiGate: SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|FortiGate: SNMP walk network interfaces|<p>Discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.walk|
|FortiGate: Total memory|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The total memory allocated for the tasks.</p>|SNMP agent|vm.memory.total[fgSysMemCapacity.0]|
|FortiGate: Used memory|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Current memory utilization (percentage).</p>|SNMP agent|vm.memory.used[fgSysMemUsage.0]|
|FortiGate: Available memory|<p>The total memory freed for utilization.</p>|Calculated|vm.memory.available[fgSysMemFree.0]|
|FortiGate: Memory utilization|<p>Memory utilization in %.</p>|Calculated|vm.memory.util[memoryUsedPercentage.0]|
|FortiGate: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items.</p>|SNMP trap|snmptrap.fallback|
|FortiGate: Total disk space|<p>Total hard disk capacity.</p>|SNMP agent|vfs.fs.total[fgSysDiskCapacity.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: Used disk space|<p>Current hard disk usage.</p>|SNMP agent|vfs.fs.used[fgSysDiskUsage.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|FortiGate: Free disk space|<p>Free hard disk capacity.</p>|Calculated|vfs.fs.free|
|FortiGate: Free disk space, %|<p>Free disk space, in %.</p>|Calculated|vfs.fs.pfree|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|FortiGate: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/FortiGate by SNMP/system.hw.serialnumber,#1)<>last(/FortiGate by SNMP/system.hw.serialnumber,#2) and length(last(/FortiGate by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|FortiGate: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/FortiGate by SNMP/system.name,#1)<>last(/FortiGate by SNMP/system.name,#2) and length(last(/FortiGate by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|FortiGate: Device has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/FortiGate by SNMP/system.uptime[fgSysUpTime.0])<10m`|Info|**Manual close**: Yes|
|FortiGate: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/FortiGate by SNMP/system.cpu.util[fgSysCpuUsage.0],5m)>{$CPU.UTIL.CRIT}`|Warning||
|FortiGate: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/FortiGate by SNMP/icmpping,#3)=0`|High||
|FortiGate: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/FortiGate by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/FortiGate by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>FortiGate: Unavailable by ICMP ping</li></ul>|
|FortiGate: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/FortiGate by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>FortiGate: Unavailable by ICMP ping</li><li>FortiGate: High ICMP ping loss</li></ul>|
|FortiGate: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/FortiGate by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>FortiGate: Unavailable by ICMP ping</li></ul>|
|FortiGate: High memory utilization|<p>The system is running out of free memory.</p>|`min(/FortiGate by SNMP/vm.memory.util[memoryUsedPercentage.0],5m)>{$MEMORY.UTIL.MAX}`|Average||
|FortiGate: Free disk space is less than {$DISK.FREE.MIN}%|<p>Left disk space is not enough.</p>|`last(/FortiGate by SNMP/vfs.fs.pfree)<{$DISK.FREE.MIN}`|Warning||

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering interfaces from IF-MIB.</p>|Dependent item|net.if.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packets can be passed.</p><p>- If ifAdminStatus is down(2), then ifOperStatus should be down(2).</p><p>- If ifAdminStatus is changed to up(1), then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic.</p><p>- It should change to dormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection).</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state.</p><p>- It should remain in the notPresent(6) state if the interface has missing (typically, hardware) components.</p>|Dependent item|net.if.status[ifOperStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.8.{#SNMPINDEX}`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in[ifInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.6.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out[ifOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.10.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol. For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.14.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol. For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.20.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.19.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.13.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA) through updating the syntax of the IANAifType textual convention.</p>|Dependent item|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n`, then the speed of the interface is somewhere in the range of `n-500,000` to `n+499,999`. For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|Dependent item|net.if.speed[ifSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.15.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for the 'eternal off' interfaces).<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/FortiGate by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/FortiGate by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/FortiGate by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/FortiGate by SNMP/net.if.in[ifInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/FortiGate by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}]) or avg(/FortiGate by SNMP/net.if.out[ifOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/FortiGate by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])) and last(/FortiGate by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/FortiGate by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/FortiGate by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/FortiGate by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])<0 and last(/FortiGate by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])>0 and ( last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/FortiGate by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

