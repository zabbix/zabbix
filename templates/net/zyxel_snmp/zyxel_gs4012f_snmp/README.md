
# ZYXEL GS-4012F by SNMP

## Overview

For Zabbix version: 6.2 and higher.
https://service-provider.zyxel.com/global/en/products/carrier-and-access-switches/access-switches/mgs-3712f

This template was tested on:

- ZYXEL GS-4012F, version V3.90(BBB.5)_2019.9.23

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/network_devices) for basic instructions.

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$SNMP.TIMEOUT} |<p>The time interval for SNMP agent availability trigger expression.</p> |`5m` |
|{$ZYXEL.LLD.FILTER.IF.CONTROL.MATCHES} |<p>Triggers will be created only for interfaces whose description contains the value of this macro</p> |`CHANGE_IF_NEEDED` |
|{$ZYXEL.LLD.FILTER.IF.LINKUPTYPE.MATCHES} |<p>Filter of discoverable link types.</p><p>0 - Down link</p><p>1 - Cooper link</p><p>2 - Fiber link</p> |`1|2` |
|{$ZYXEL.LLD.FILTER.IF.LINKUPTYPE.NOT_MATCHES} |<p>Filter to exclude discovered by link types.</p> |`CHANGE_IF_NEEDED` |
|{$ZYXEL.LLD.FILTER.IF.NAME.MATCHES} |<p>Filter by discoverable interface names.</p> |`.*` |
|{$ZYXEL.LLD.FILTER.IF.NAME.NOT_MATCHES} |<p>Filter to exclude discovered interfaces by name.</p> |`CHANGE_IF_NEEDED` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Fan discovery |<p>An entry in fanRpmTable.</p> |SNMP |zyxel.4012f.fan.discovery |
|Interface discovery |<p>-</p> |SNMP |zyxel.4012f.net.if.discovery<p>**Filter**:</p>AND <p>- {#ZYXEL.IF.NAME} MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.NAME.MATCHES}`</p><p>- {#ZYXEL.IF.NAME} NOT_MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.NAME.NOT_MATCHES}`</p><p>- {#ZYXEL.IF.LINKUPTYPE} MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.LINKUPTYPE.MATCHES}`</p><p>- {#ZYXEL.IF.LINKUPTYPE} NOT_MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.LINKUPTYPE.NOT_MATCHES}`</p><p>**Overrides:**</p><p>Don't create triggers for matching interface<br> - {#ZYXEL.IF.NAME} NOT_MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.CONTROL.MATCHES}`<br>  - TRIGGER_PROTOTYPE REGEXP `.*`<br>  - NO_DISCOVER</p> |
|Temperature discovery |<p>An entry in tempTable.</p><p>Index of temperature unit. 1:MAC, 2:CPU, 3:PHY</p> |SNMP |zyxel.4012f.temp.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Voltage discovery |<p>An entry in voltageTable.</p> |SNMP |zyxel.4012f.volt.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |ZYXEL GS-4012F: CPU utilization |<p>MIB: ZYXEL-GS4012F-MIB</p><p>Show device CPU load in %, it's the snapshot of CPU load when</p><p>getting the values.</p> |SNMP |zyxel.4012f.cpuusage |
|Fans |ZYXEL GS-4012F: Fan #{#SNMPINDEX} |<p>MIB: ZYXEL-GS4012F-MIB</p><p>Current speed in Revolutions Per Minute (RPM) on the fan.</p> |SNMP |zyxel.4012f.fan[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Inventory |ZYXEL GS-4012F: Hardware model name |<p>MIB: RFC1213-MIB</p><p>A textual description of the entity.  This value</p><p>should include the full name and version</p><p>identification of the system's hardware type,</p><p>software operating-system, and networking</p><p>software.  It is mandatory that this only contain</p><p>printable ASCII characters.</p> |SNMP |zyxel.4012f.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL GS-4012F: Contact |<p>MIB: RFC1213-MIB</p><p>The textual identification of the contact person</p><p>for this managed node, together with information</p><p>on how to contact this person.</p> |SNMP |zyxel.4012f.contact<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL GS-4012F: Host name |<p>MIB: RFC1213-MIB</p><p>An administratively-assigned name for this</p><p>managed node.  By convention, this is the node's</p><p>fully-qualified domain name.</p> |SNMP |zyxel.4012f.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL GS-4012F: Location |<p>MIB: RFC1213-MIB</p><p>The physical location of this node (e.g.,</p><p>`telephone closet, 3rd floor').</p> |SNMP |zyxel.4012f.location<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL GS-4012F: MAC address |<p>MIB: IF-MIB</p><p>The interface's address at the protocol layer</p><p>immediately `below' the network layer in the</p><p>protocol stack.  For interfaces which do not have</p><p>such an address (e.g., a serial line), this object</p><p>should contain an octet string of zero length.</p> |SNMP |zyxel.4012f.mac<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL GS-4012F: ZyNOS F/W Version |<p>MIB: ZYXEL-GS4012F-MIB</p> |SNMP |zyxel.4012f.fwversion<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |ZYXEL GS-4012F: Hardware serial number |<p>MIB: ZYXEL-GS4012F-MIB</p><p>Serial number</p> |SNMP |zyxel.4012f.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Speed Duplex |<p>MIB: ZYXEL-GS4012F-MIB</p><p>Transmission mode</p> |SNMP |zyxel.4012f.net.if.speed_duplex[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Interface description |<p>MIB: ZYXEL-GS4012F-MIB</p><p>A textual string containing information about the interface</p> |SNMP |zyxel.4012f.net.if.name[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Link type |<p>MIB: ZYXEL-GS4012F-MIB</p><p>Physical connection type</p> |SNMP |zyxel.4012f.net.if.link_type[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Interface name |<p>MIB: IF-MIB</p><p>A textual string containing information about the interface</p> |SNMP |zyxel.4012f.net.if.descr[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>The testing(3) state indicates that no operational</p><p>packets can be passed.</p> |SNMP |zyxel.4012f.net.if.operstatus[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Administrative status |<p>MIB: IF-MIB</p><p>The desired state of the interface.  The</p><p>testing(3) state indicates that no operational</p><p>packets can be passed.</p> |SNMP |zyxel.4012f.net.if.adminstatus[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Incoming traffic |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface,</p><p>including framing characters.</p> |SNMP |zyxel.4012f.net.if.in.traffic[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Incoming unicast packages |<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were not addressed to a multicast</p><p>or broadcast address at this sub-layer</p> |SNMP |zyxel.4012f.net.if.in.ucastpkts[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Incoming multicast packages |<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were addressed to a multicast</p><p>address at this sub-layer.  For a MAC layer protocol, this</p><p>includes both Group and Functional addresses.</p> |SNMP |zyxel.4012f.net.if.in.multicastpkts[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Incoming broadcast packages |<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were addressed to a broadcast</p><p>address at this sub-layer.</p> |SNMP |zyxel.4012f.net.if.in.broadcastpkts[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Outgoing traffic |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the</p><p>interface, including framing characters.  This object is a</p><p>64-bit version of ifOutOctets.</p> |SNMP |zyxel.4012f.net.if.out.traffic[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Outgoing unicast packages |<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were not addressed to a</p><p>multicast or broadcast address at this sub-layer, including</p><p>those that were discarded or not sent.</p> |SNMP |zyxel.4012f.net.if.out.ucastpkts[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Outgoing multicast packages |<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were addressed to a</p><p>multicast address at this sub-layer, including those that</p><p>were discarded or not sent.  For a MAC layer protocol, this</p><p>includes both Group and Functional addresses.</p> |SNMP |zyxel.4012f.net.if.out.multicastpkts[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Outgoing broadcast packages |<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were addressed to a</p><p>broadcast address at this sub-layer, including those that</p><p>were discarded or not sent.</p> |SNMP |zyxel.4012f.net.if.out.broadcastpkts[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Link speed |<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in bits per second</p> |SNMP |zyxel.4012f.net.if.highspeed[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Incoming utilization |<p>Interface utilization percentage</p> |CALCULATED |zyxel.4012f.net.if.in.util[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- IN_RANGE: `0 100`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return +parseFloat(value).toFixed(0); `</p><p>**Expression**:</p>`last(//zyxel.4012f.net.if.in.traffic[{#SNMPINDEX}]) * (last(//zyxel.4012f.net.if.highspeed[{#SNMPINDEX}]) <> 0) / ( last(//zyxel.4012f.net.if.highspeed[{#SNMPINDEX}]) + (last(//zyxel.4012f.net.if.highspeed[{#SNMPINDEX}]) = 0) ) * 100` |
|Network interfaces |ZYXEL GS-4012F: Port {#SNMPINDEX}: Outgoing utilization |<p>Interface utilization percentage</p> |CALCULATED |zyxel.4012f.net.if.out.util[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- IN_RANGE: `0 100`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return +parseFloat(value).toFixed(0); `</p><p>**Expression**:</p>`last(//zyxel.4012f.net.if.out.traffic[{#SNMPINDEX}]) * (last(//zyxel.4012f.net.if.highspeed[{#SNMPINDEX}]) <> 0) / ( last(//zyxel.4012f.net.if.highspeed[{#SNMPINDEX}]) + (last(//zyxel.4012f.net.if.highspeed[{#SNMPINDEX}]) = 0) ) * 100` |
|Power supply |ZYXEL GS-4012F: Nominal "{#ZYXEL.VOLT.NOMINAL}" |<p>MIB: ZYXEL-GS4012F-MIB</p><p>The current voltage reading.</p> |SNMP |zyxel.4012f.volt[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |ZYXEL GS-4012F: SNMP agent availability |<p>-</p> |INTERNAL |zabbix[host,snmp,available]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |ZYXEL GS-4012F: Uptime (network) |<p>MIB: RFC1213-MIB</p><p>The time (in hundredths of a second) since the</p><p>network management portion of the system was last</p><p>re-initialized.</p> |SNMP |zyxel.4012f.net.uptime<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |ZYXEL GS-4012F: Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized.</p><p>Note that this is different from sysUpTime in the SNMPv2-MIB</p><p>[RFC1907] because sysUpTime is the uptime of the</p><p>network management portion of the system.</p> |SNMP |zyxel.4012f.hw.uptime<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Temperature |ZYXEL GS-4012F: Temperature "{#ZYXEL.TEMP.ID}" |<p>MIB: ZYXEL-GS4012F-MIB</p><p>The current temperature measured at this sensor</p> |SNMP |zyxel.4012f.temp[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|ZYXEL GS-4012F: High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/ZYXEL GS-4012F by SNMP/zyxel.4012f.cpuusage,5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|ZYXEL GS-4012F: FAN{#SNMPINDEX} is in critical state |<p>Please check the fan unit</p> |`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.fan[{#SNMPINDEX}])<{#ZYXEL.FANRPM.THRESH.LOW}` |AVERAGE | |
|ZYXEL GS-4012F: Template does not match hardware |<p>This template is for Zyxel GS-4012F, but connected to {ITEM.VALUE}</p> |`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.model)<>"GS-4012F"` |INFO | |
|ZYXEL GS-4012F: Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.fwversion,#1)<>last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.fwversion,#2) and length(last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.fwversion))>0` |INFO |<p>Manual close: YES</p> |
|ZYXEL GS-4012F: Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.serialnumber,#1)<>last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.serialnumber,#2) and length(last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.net.if.operstatus[{#SNMPINDEX}])=2 and last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.net.if.operstatus[{#SNMPINDEX}],#1)<>last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.net.if.operstatus[{#SNMPINDEX}],#2)`<p>Recovery expression:</p>`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.net.if.operstatus[{#SNMPINDEX}])<>2` |AVERAGE |<p>Manual close: YES</p> |
|ZYXEL GS-4012F: Voltage {#ZYXEL.VOLT.NOMINAL} is in critical state |<p>Please check the power supply</p> |`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.volt[{#SNMPINDEX}])<{#ZYXEL.VOLT.THRESH.LOW}` |AVERAGE | |
|ZYXEL GS-4012F: No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/ZYXEL GS-4012F by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING | |
|ZYXEL GS-4012F: Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.hw.uptime)>0 and last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.hw.uptime)<10m) or (last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.hw.uptime)=0 and last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.net.uptime)<10m)` |INFO |<p>Manual close: YES</p> |
|ZYXEL GS-4012F: Temperature {#ZYXEL.TEMP.ID} is in critical state |<p>Please check the temperature</p> |`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.temp[{#SNMPINDEX}])>{#ZYXEL.TEMP.THRESH.HIGH}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/422668-discussion-thread-for-official-zabbix-templates-for-zyxel).

## Known Issues

- Description: Incorrect handling of SNMP bulk requests. Disable the use of bulk requests in the SNMP interface settings.
  - Version: all versions firmware
  - Device: ZYXEL GS-4012F

