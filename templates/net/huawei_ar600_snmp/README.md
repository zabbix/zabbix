
# Huawei AR600 by SNMP

## Overview

Template Huawei AR600 Series by SNMP

This template is intended for monitoring Huawei AR600 Series routers via SNMP.

It provides monitoring for:
  - CPU, memory, and temperature sensors
  - Hardware inventory
  - Network Quality Analysis (NQA): delay, jitter, and packet loss
  - QoS queues using HUAWEI-CBQOS-MIB
  - Network interfaces and traffic statistics

MIBs used:
  - HOST-RESOURCES-MIB
  - EtherLike-MIB
  - SNMPv2-MIB
  - IF-MIB
  - NQA-MIB
  - HUAWEI-CBQOS-MIB
  - HUAWEI-ENTITY-EXTENT-MIB

You can discuss this template or leave feedback on our forum https://www.zabbix.com/forum/zabbix-suggestions-and-feedback.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Huawei AR611

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

> Refer to the [vendor documentation](https://support.huawei.com/enterprise/en/routers/ar600-6100-6200-6300-pid-256863201).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>Threshold of CPU utilization for warning trigger in %.</p>|`90`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of average ICMP response time in seconds.</p>|`0.15`|
|{$SNMP.TIMEOUT}|<p>Time interval for SNMP availability trigger.</p>|`5m`|
|{$IFCONTROL}|<p>Macro for operational state of interface for link down trigger. Can be used with interface name as context.</p>|`1`|
|{$IF.UTIL.MAX}|<p>Maximum threshold of interface bandwidth utilization in %. Can be used with interface name as context.</p>|`95`|
|{$NET.IF.IFNAME.MATCHES}|<p>This macro is used to include network interfaces by their name.</p>|`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links, and docker0 bridge by default.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}|<p>This macro is used to include network interfaces by their operational status.</p>|`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>This macro is used to exclude network interfaces by their operational status.</p>|`^6$`|
|{$NET.IF.IFADMINSTATUS.MATCHES}|<p>This macro is used to include network interfaces by their administrative status.</p>|`^.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>This macro is used to exclude network interfaces by their administrative status.</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}|<p>This macro is used to include network interfaces by their description.</p>|`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}|<p>This macro is used to exclude network interfaces by their description.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFTYPE.MATCHES}|<p>This macro is used to include network interfaces by their type.</p>|`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}|<p>This macro is used to exclude network interfaces by their type.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}|<p>This macro is used to include network interfaces by their alias.</p>|`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}|<p>This macro is used to exclude network interfaces by their alias.</p>|`CHANGE_IF_NEEDED`|
|{$IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|
|{$HUAWEI.AR600.JITTER.CRIT}|<p>Threshold of jitter values from destination to source for critical trigger in ms.</p>|`60`|
|{$HUAWEI.AR600.PACKET.LOSS.CRIT}|<p>Threshold of packet loss ratio in %.</p>|`90`|
|{$HUAWEI.AR600.RTT.AVG.CRIT}|<p>Threshold of average RTT in ms.</p>|`200`|
|{$HUAWEI.AR600.NQA.ADMIN.MATCHES}|<p>Used to include NQA metrics by admin name regex.</p>|`.*`|
|{$HUAWEI.AR600.NQA.ADMIN.NOT_MATCHES}|<p>Used to exclude NQA metrics by admin name regex.</p>|`CHANGE_IF_NEEDED`|
|{$HUAWEI.AR600.NQA.TEST.MATCHES}|<p>Used to include NQA metrics by test class name regex.</p>|`.*`|
|{$HUAWEI.AR600.NQA.TEST.NOT_MATCHES}|<p>Used to exclude NQA metrics by test class name regex.</p>|`CHANGE_IF_NEEDED`|
|{$HUAWEI.AR600.COS.DISCARDED.BPS.WARN}|<p>Warning threshold for discarded byte rate (bps).</p>|`100000`|
|{$HUAWEI.AR600.COS.DIRECTION.MATCHES}|<p>Used to include CoS metrics by queue direction: `IN`, `OUT`, or regex (default: `OUT` only).</p>|`^OUT$`|
|{$HUAWEI.AR600.COS.DIRECTION.NOT_MATCHES}|<p>Used to exclude CoS metrics by queue direction: `IN`, `OUT`, or regex (default: `OUT` only).</p>|`CHANGE_IF_NEEDED`|
|{$HUAWEI.AR600.COS.IFNAME.MATCHES}|<p>Used to include interfaces by name regex (e.g., `^GigabitEthernet0/0/3$`).</p>|`.*`|
|{$HUAWEI.AR600.COS.IFNAME.NOT_MATCHES}|<p>Used to exclude interfaces by name regex (e.g., `^GigabitEthernet0/0/3$`).</p>|`CHANGE_IF_NEEDED`|
|{$HUAWEI.AR600.COS.QUEUE.MATCHES}|<p>Used to include CoS metrics by queue number regex (e.g., `^(1\\|2\\|3)$`).</p>|`.*`|
|{$HUAWEI.AR600.COS.QUEUE.NOT_MATCHES}|<p>Used to exclude CoS metrics by queue number regex (e.g., `^(1\\|2\\|3)$`).</p>|`CHANGE_IF_NEEDED`|
|{$POWER.USAGE.WARN}|<p>Warning threshold for device power usage in %.</p>|`80`|
|{$MEMORY.UTIL.MAX}|<p>Threshold of memory utilization for trigger in %.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Total power|<p>MIB: HUAWEI-ENTITY-EXTENT-MIB</p><p>Object: hwDevicePowerInfoTotalPower</p><p></p><p>Indicates the total available power of the device.</p>|SNMP agent|huawei.ar600.device.power.total<p>**Preprocessing**</p><ul><li><p>Does not match regular expression: `^0$`</p><p>⛔️Custom on fail: Set error to: `The device does not support power information retrieval via SNMP.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Used power|<p>MIB: HUAWEI-ENTITY-EXTENT-MIB</p><p>Object: hwDevicePowerInfoUsedPower</p><p></p><p>Indicates the current power consumption of the device.</p>|SNMP agent|huawei.ar600.device.power.used<p>**Preprocessing**</p><ul><li><p>Does not match regular expression: `^0$`</p><p>⛔️Custom on fail: Set error to: `The device does not support power information retrieval via SNMP.`</p></li></ul>|
|Huawei AR600 Series: SNMP walk EtherLike-MIB interfaces|<p>Discovery of interfaces from IF-MIB and EtherLike-MIB. Interfaces with the `up(1)` operational status are discovered.</p>|SNMP agent|huawei.ar600.net.if.duplex.walk|
|Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>Time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from `sysUpTime` in the SNMPv2-MIB [RFC1907] because `sysUpTime` is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|System location|<p>MIB: SNMPv2-MIB</p><p>Physical location of the node (e.g., `equipment room`, `3rd floor`). If not provided, the value is a zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person. If no contact information is known, the value is a zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the entity as part of the vendor's SMI enterprises subtree with the prefix 1.3.6.1.4.1 (e.g., a vendor with the identifier 1.3.6.1.4.1.4242 might assign a system object with the OID 1.3.6.1.4.1.4242.1.1).</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for the node (the node's fully-qualified domain name). If not provided, the value is a zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should include the full name and version identification of the system's hardware type, software operating system, and networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|ICMP ping|<p>The host accessibility via ICMP ping.</p><p></p><p>0 - ICMP ping failed</p><p>1 - ICMP ping successful</p>|Simple check|icmpping|
|ICMP loss|<p>The percentage of lost packets.</p>|Simple check|icmppingloss|
|ICMP response time|<p>The ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|
|Huawei AR600 Series: SNMP walk network interfaces|<p>Discovery of interfaces from IF-MIB.</p>|SNMP agent|huawei.ar600.net.if.walk|
|NQA walk|<p>Collects raw Network Quality Analysis (NQA) statistics from the device using Huawei NQA MIB. This item performs an SNMP walk of RTT, packet loss, and jitter metrics. The output is used as a master item for NQA low-level discovery and dependent items, allowing per-test monitoring of latency, jitter, and packet loss.</p>|SNMP agent|huawei.ar600.nqa.walk<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CoS walk|<p>Raw SNMP walk of CBQoS-related tables (`ifIndex`, `ifName`, `cbqosMatched`, `cbqosEnqueued`, `cbqosDiscarded`).</p><p>Used as the master item for CBQoS LLD and dependent items that extract per-interface/per-queue metrics.</p>|SNMP agent|huawei.ar600.cos.walk<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|MPU walk|<p>Collects physical entity information for MPU (Main Processing Unit) components using ENTITY-MIB. This item performs an SNMP walk of `entPhysicalDescr` and `entPhysicalName`. The collected data is used as a master item for MPU low-level discovery (LLD), enabling identification and monitoring of hardware processing units present in the device.</p>|SNMP agent|huawei.ar600.mpu.walk<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei AR600: High power utilization|<p>Device power consumption is high.<br>Trigger threshold: `{$POWER.USAGE.WARN}`%</p>|`last(/Huawei AR600 by SNMP/huawei.ar600.device.power.used) / last(/Huawei AR600 by SNMP/huawei.ar600.device.power.total) * 100 > {$POWER.USAGE.WARN}`|Warning||
|Huawei AR600: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Huawei AR600 by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Huawei AR600 by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Huawei AR600 by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Huawei AR600 by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Huawei AR600: No SNMP data collection</li></ul>|
|Huawei AR600: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Huawei AR600 by SNMP/system.name,#1)<>last(/Huawei AR600 by SNMP/system.name,#2) and length(last(/Huawei AR600 by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Huawei AR600: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Huawei AR600 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Huawei AR600: Unavailable by ICMP ping</li></ul>|
|Huawei AR600: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Huawei AR600 by SNMP/icmpping,#3)=0`|High||
|Huawei AR600: High ICMP ping loss|<p>ICMP packet loss detected.</p>|`min(/Huawei AR600 by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Huawei AR600 by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Huawei AR600: Unavailable by ICMP ping</li></ul>|
|Huawei AR600: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Huawei AR600 by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Huawei AR600: High ICMP ping loss</li><li>Huawei AR600: Unavailable by ICMP ping</li></ul>|

### LLD rule EtherLike-MIB discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|EtherLike-MIB discovery|<p>Discovery of interfaces from IF-MIB and EtherLike-MIB. Interfaces with `up(1)` operational status are discovered.</p>|Dependent item|net.if.duplex.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for EtherLike-MIB discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Duplex status|<p>MIB: EtherLike-MIB</p><p>The current mode of operation of the MAC entity. `unknown` indicates that the current duplex mode could not be determined.</p><p>Management control of the duplex mode is accomplished through the MAU MIB. When an interface does not support autonegotiation or when autonegotiation is not enabled, the duplex mode is controlled using `ifMauDefaultType`. When autonegotiation is supported and enabled, duplex mode is controlled using `ifMauAutoNegAdvertisedBits`. In either case, the currently operating duplex mode in reflected both in this object and in `ifMauType`.</p><p>Note that this object provides redundant information with `ifMauType`. Normally, redundant objects are discouraged. However, in this instance, it allows a management application to determine the duplex status of an interface without having to know every possible value of `ifMauType`. This was felt to be sufficiently valuable to justify the redundancy.</p><p>Reference: [IEEE 802.3 Std.], 30.3.1.1.32,aDuplexStatus.</p>|Dependent item|net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.10.7.2.1.19.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for EtherLike-MIB discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei AR600: Interface {#IFNAME}({#IFALIAS}): In half-duplex mode|<p>Please check autonegotiation settings and cabling.</p>|`last(/Huawei AR600 by SNMP/net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}])=2`|Warning|**Manual close**: Yes|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of interfaces from IF-MIB.</p>|Dependent item|net.if.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IFNAME}]: Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The `testing(3)` state indicates that no operational packet scan be passed;</p><p>- If `ifAdminStatus` is `down(2)`, then `ifOperStatus` should be `down(2)`;</p><p>- If `ifAdminStatus` is changed to `up(1)`, then `ifOperStatus` should change to `up(1)` if the interface is ready to transmit and receive network traffic;</p><p>- It should change to `dormant(5)` if the interface is waiting for external actions (such as a serial line waiting for an incoming connection);</p><p>- It should remain in the `down(2)` state if and only if there is a fault that prevents it from going to the `up(1)` state;</p><p>- It should remain in the `notPresent(6)` state if the interface has missing (typically, hardware) components.</p>|Dependent item|net.if.status[ifOperStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.8.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IFNAME}]: Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.in[ifInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.10.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}]: Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.out[ifOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.16.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}]: Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors</p><p>preventing them from being deliverable to a higher-layer protocol.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.14.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}]: Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors</p><p>preventing them from being deliverable to a higher-layer protocol.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.20.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}]: Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.19.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}]: Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.13.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}]: Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for `ifType` are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p>|Dependent item|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface [{#IFNAME}]: Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in bits per second.</p><p>For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth.</p><p>If the bandwidth of the interface is greater than the maximum value reportable by this object</p><p>then this object should report its maximum value (4,294,967,295) and `ifHighSpeed` must be used to report the interface's speed.</p><p>For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|Dependent item|net.if.speed[ifSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.5.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `5m`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei AR600: Interface [{#IFNAME}]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to `0`, marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to `(1)` sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of `.diff`.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Huawei AR600 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Huawei AR600 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Huawei AR600 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Huawei AR600: Interface [{#IFNAME}]: High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Huawei AR600 by SNMP/net.if.in[ifInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Huawei AR600 by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}]) or avg(/Huawei AR600 by SNMP/net.if.out[ifOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Huawei AR600 by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])) and last(/Huawei AR600 by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Huawei AR600: Interface [{#IFNAME}]: Link down</li></ul>|
|Huawei AR600: Interface [{#IFNAME}]: High error rate|<p>Recovers when below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Huawei AR600 by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Huawei AR600 by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Huawei AR600: Interface [{#IFNAME}]: Link down</li></ul>|
|Huawei AR600: Interface [{#IFNAME}]: Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Huawei AR600 by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])<0 and last(/Huawei AR600 by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])>0 and ( last(/Huawei AR600 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Huawei AR600 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Huawei AR600 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Huawei AR600 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Huawei AR600 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Huawei AR600 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Huawei AR600 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Huawei AR600: Interface [{#IFNAME}]: Link down</li></ul>|

### LLD rule NQA discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|NQA discovery|<p>Discovers NQA tests based on data collected by the NQA walk item.</p><p></p><p>Discovered NQA entities are filtered using user-defined macros and used to create per-test monitoring items and triggers.</p>|Dependent item|huawei.ar600.nqa.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for NQA discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|NQA [{#NQA.ADMIN}/{#NQA.TEST}]: RTT avg|<p>Average round-trip time (RTT) measured by the NQA test.</p><p></p><p>Represents the mean latency between the source and destination over the evaluation interval.</p>|Dependent item|huawei.ar600.nqa.rtt.avg[{#NQA.ADMIN},{#NQA.TEST}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["{#NQA.ADMIN}|{#NQA.TEST}"].rttAvg`</p></li></ul>|
|NQA [{#NQA.ADMIN}/{#NQA.TEST}]: RTT min|<p>Minimum round-trip time (RTT) observed during the NQA test interval.</p><p></p><p>Useful for identifying baseline latency under optimal conditions.</p>|Dependent item|huawei.ar600.nqa.rtt.min[{#NQA.ADMIN},{#NQA.TEST}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["{#NQA.ADMIN}|{#NQA.TEST}"].rttMin`</p></li></ul>|
|NQA [{#NQA.ADMIN}/{#NQA.TEST}]: RTT max|<p>Maximum round-trip time (RTT) observed during the NQA test interval.</p><p></p><p>Indicates latency spikes that may affect application performance.</p>|Dependent item|huawei.ar600.nqa.rtt.max[{#NQA.ADMIN},{#NQA.TEST}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["{#NQA.ADMIN}|{#NQA.TEST}"].rttAvg`</p></li></ul>|
|NQA [{#NQA.ADMIN}/{#NQA.TEST}]: Packet loss|<p>Packet loss ratio reported by the NQA test.</p><p></p><p>Represents the percentage of packets lost during transmission between the source and destination.</p>|Dependent item|huawei.ar600.nqa.packetloss[{#NQA.ADMIN},{#NQA.TEST}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["{#NQA.ADMIN}|{#NQA.TEST}"].packetLoss`</p></li></ul>|
|NQA [{#NQA.ADMIN}/{#NQA.TEST}]: Jitter avg|<p>Average jitter measured by the NQA test.</p><p></p><p>Jitter represents variation in packet delay and is critical for real-time applications such as voice and video.</p>|Dependent item|huawei.ar600.nqa.jitter[{#NQA.ADMIN},{#NQA.TEST}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["{#NQA.ADMIN}|{#NQA.TEST}"].jitter`</p></li></ul>|

### Trigger prototypes for NQA discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei AR600: NQA [{#NQA.ADMIN}/{#NQA.TEST}]: RTT average too high|<p>Average RTT exceeds critical threshold.</p>|`avg(/Huawei AR600 by SNMP/huawei.ar600.nqa.rtt.avg[{#NQA.ADMIN},{#NQA.TEST}],5m) > {$HUAWEI.AR600.RTT.AVG.CRIT}`|Average||
|Huawei AR600: NQA [{#NQA.ADMIN}/{#NQA.TEST}]: Packet loss detected|<p>Packet loss exceeds critical threshold.</p>|`avg(/Huawei AR600 by SNMP/huawei.ar600.nqa.packetloss[{#NQA.ADMIN},{#NQA.TEST}],5m) > {$HUAWEI.AR600.PACKET.LOSS.CRIT}`|Average||
|Huawei AR600: NQA [{#NQA.ADMIN}/{#NQA.TEST}]: Jitter too high|<p>NQA jitter exceeds critical threshold.</p>|`avg(/Huawei AR600 by SNMP/huawei.ar600.nqa.jitter[{#NQA.ADMIN},{#NQA.TEST}],5m) > {$HUAWEI.AR600.JITTER.CRIT}`|Average||

### LLD rule CoS queue discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CoS queue discovery|<p>Discovers Class of Service (CoS) queues on network interfaces.</p><p></p><p>Discovered queues are filtered using user-defined macros and are used to create dependent items for traffic statistics.</p>|Dependent item|huawei.ar600.cos.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for CoS queue discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#IFNAME} {#DIRECTION} queue {#QUEUE}: Discarded bytes rate|<p>Rate of bytes discarded by the CoS queue.</p><p></p><p>Calculated from cumulative SNMP counters and converted to bytes per second.</p><p>High values may indicate congestion or insufficient queue capacity.</p>|Dependent item|huawei.ar600.cbqos.discarded.rate[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.snmpIndex=="{#SNMPINDEX}")].cbqosDiscarded.first()`</p></li><li>Change per second</li></ul>|
|{#IFNAME} {#DIRECTION} queue {#QUEUE}: Enqueued bytes rate|<p>Rate of bytes enqueued into the CoS queue.</p><p></p><p>Represents traffic accepted by the queue and scheduled for transmission.</p>|Dependent item|huawei.ar600.cbqos.enqueued.rate[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.snmpIndex=="{#SNMPINDEX}")].cbqosEnqueued.first()`</p></li><li>Change per second</li></ul>|
|{#IFNAME} {#DIRECTION} queue {#QUEUE}: Matched bytes rate|<p>Rate of bytes matched to this CoS queue based on classification rules.</p><p></p><p>Reflects traffic classified into the queue before scheduling or dropping decisions are applied.</p>|Dependent item|huawei.ar600.cbqos.matched.rate[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.snmpIndex=="{#SNMPINDEX}")].cbqosMatched.first()`</p></li></ul>|

### Trigger prototypes for CoS queue discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei AR600: COS [{#IFNAME} {#DIRECTION} queue {#QUEUE}]: Discarded traffic (warning)|<p>Discarded byte rate exceeds warning threshold.</p>|`avg(/Huawei AR600 by SNMP/huawei.ar600.cbqos.discarded.rate[{#SNMPINDEX}],5m) > {$HUAWEI.AR600.COS.DISCARDED.BPS.WARN}`|Average||

### LLD rule MPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MPU Discovery|<p>Discovers MPU (Main Processing Unit) components using data collected from the MPU walk item.</p><p></p><p>Physical entity name and description are used to identify processing modules relevant for monitoring.</p><p></p><p>Discovered entities can be used for further hardware health and performance monitoring.</p>|Dependent item|huawei.ar600.mpu.discovery|

### Item prototypes for MPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ENT_NAME}: CPU utilization|<p>MIB: HUAWEI-ENTITY-EXTENT-MIB</p><p>The CPU usage for this entity. This metric represents the overall CPU utilization of the entity and does not account for the number of CPUs it has. [Reference](http://support.huawei.com/enterprise/KnowledgebaseReadAction.action?contentId=KB1000090234)</p>|SNMP agent|system.cpu.util[{#SNMPINDEX}]|
|{#ENT_NAME}: Memory utilization|<p>MIB: HUAWEI-ENTITY-EXTENT-MIB</p><p>The memory usage for the entity. This object indicates what percent of memory is used. [Reference](http://support.huawei.com/enterprise/KnowledgebaseReadAction.action?contentId=KB1000090234)</p>|SNMP agent|vm.memory.util[{#SNMPINDEX}]|
|{#ENT_NAME}: Temperature|<p>MIB: HUAWEI-ENTITY-EXTENT-MIB</p><p>The temperature for `{#SNMPVALUE}`.</p>|SNMP agent|sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}]|
|{#ENT_NAME}: Hardware serial number|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#ENT_NAME}: Hardware version(revision)|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.version[entPhysicalHardwareRev.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#ENT_NAME}: Operating system|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for MPU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei AR600: {#ENT_NAME}: High CPU utilization||`min(/Huawei AR600 by SNMP/system.cpu.util[{#SNMPINDEX}],5m) > {$CPU.UTIL.CRIT}`|Warning||
|Huawei AR600: {#ENT_NAME}: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Huawei AR600 by SNMP/vm.memory.util[{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}`|Average||
|Huawei AR600: {#ENT_NAME}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as the temperature sensor status if available.</p>|`avg(/Huawei AR600 by SNMP/sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#ENT_NAME}"}`|Warning|**Depends on**:<br><ul><li>Huawei AR600: {#ENT_NAME}: Temperature is above critical threshold</li></ul>|
|Huawei AR600: {#ENT_NAME}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as the temperature sensor status if available.</p>|`avg(/Huawei AR600 by SNMP/sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#ENT_NAME}"}`|High||
|Huawei AR600: {#ENT_NAME}: Temperature is too low||`avg(/Huawei AR600 by SNMP/sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#ENT_NAME}"}`|Average||
|Huawei AR600: {#ENT_NAME}: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Huawei AR600 by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#1)<>last(/Huawei AR600 by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#2) and length(last(/Huawei AR600 by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|
|Huawei AR600: {#ENT_NAME}: Operating system description has changed|<p>Operating system description has changed. Possible the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/Huawei AR600 by SNMP/system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}],#1)<>last(/Huawei AR600 by SNMP/system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}],#2) and length(last(/Huawei AR600 by SNMP/system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Huawei AR600: System name has changed</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

