
# ZYXEL GS-4012F by SNMP

## Overview

https://service-provider.zyxel.com/global/en/products/carrier-and-access-switches/access-switches/mgs-3712f

### Known Issues

Description: Incorrect handling of SNMP bulk requests. Disable the use of bulk requests in the SNMP interface settings.
Version: all versions firmware
Device: ZYXEL GS-4012F

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- ZYXEL GS-4012F V3.90(BBB.5)_2019.9.23

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ZYXEL.LLD.FILTER.IF.CONTROL.MATCHES}|<p>Triggers will be created only for interfaces whose description contains the value of this macro</p>|`CHANGE_IF_NEEDED`|
|{$SNMP.TIMEOUT}|<p>The time interval for SNMP agent availability trigger expression.</p>|`5m`|
|{$CPU.UTIL.CRIT}||`90`|
|{$ZYXEL.LLD.FILTER.IF.NAME.MATCHES}|<p>Filter by discoverable interface names.</p>|`.*`|
|{$ZYXEL.LLD.FILTER.IF.NAME.NOT_MATCHES}|<p>Filter to exclude discovered interfaces by name.</p>|`CHANGE_IF_NEEDED`|
|{$ZYXEL.LLD.FILTER.IF.LINKUPTYPE.MATCHES}|<p>Filter of discoverable link types.</p><p>0 - Down link</p><p>1 - Cooper link</p><p>2 - Fiber link</p>|`1\|2`|
|{$ZYXEL.LLD.FILTER.IF.LINKUPTYPE.NOT_MATCHES}|<p>Filter to exclude discovered by link types.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL GS-4012F: SNMP agent availability||Zabbix internal|zabbix[host,snmp,available]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL GS-4012F: Hardware model name|<p>MIB: RFC1213-MIB</p><p>A textual description of the entity.  This value</p><p>should include the full name and version</p><p>identification of the system's hardware type,</p><p>software operating-system, and networking</p><p>software.  It is mandatory that this only contain</p><p>printable ASCII characters.</p>|SNMP agent|zyxel.4012f.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Contact|<p>MIB: RFC1213-MIB</p><p>The textual identification of the contact person</p><p>for this managed node, together with information</p><p>on how to contact this person.</p>|SNMP agent|zyxel.4012f.contact<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Host name|<p>MIB: RFC1213-MIB</p><p>An administratively-assigned name for this</p><p>managed node.  By convention, this is the node's</p><p>fully-qualified domain name.</p>|SNMP agent|zyxel.4012f.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Location|<p>MIB: RFC1213-MIB</p><p>The physical location of this node (e.g.,</p><p>`telephone closet, 3rd floor').</p>|SNMP agent|zyxel.4012f.location<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: MAC address|<p>MIB: IF-MIB</p><p>The interface's address at the protocol layer</p><p>immediately `below' the network layer in the</p><p>protocol stack.  For interfaces which do not have</p><p>such an address (e.g., a serial line), this object</p><p>should contain an octet string of zero length.</p>|SNMP agent|zyxel.4012f.mac<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Uptime (network)|<p>MIB: RFC1213-MIB</p><p>The time (in hundredths of a second) since the</p><p>network management portion of the system was last</p><p>re-initialized.</p>|SNMP agent|zyxel.4012f.net.uptime<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|ZYXEL GS-4012F: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized.</p><p>Note that this is different from sysUpTime in the SNMPv2-MIB</p><p>[RFC1907] because sysUpTime is the uptime of the</p><p>network management portion of the system.</p>|SNMP agent|zyxel.4012f.hw.uptime<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|ZYXEL GS-4012F: ZyNOS F/W Version|<p>MIB: ZYXEL-GS4012F-MIB</p>|SNMP agent|zyxel.4012f.fwversion<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|ZYXEL GS-4012F: Hardware serial number|<p>MIB: ZYXEL-GS4012F-MIB</p><p>Serial number</p>|SNMP agent|zyxel.4012f.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: CPU utilization|<p>MIB: ZYXEL-GS4012F-MIB</p><p>Show device CPU load in %, it's the snapshot of CPU load when</p><p>getting the values.</p>|SNMP agent|zyxel.4012f.cpuusage|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL GS-4012F: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/ZYXEL GS-4012F by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning||
|ZYXEL GS-4012F: Template does not match hardware|<p>This template is for Zyxel GS-4012F, but connected to {ITEM.VALUE}</p>|`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.model)<>"GS-4012F"`|Info|**Manual close**: Yes|
|ZYXEL GS-4012F: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.hw.uptime)>0 and last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.hw.uptime)<10m) or (last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.hw.uptime)=0 and last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.net.uptime)<10m)`|Info|**Manual close**: Yes|
|ZYXEL GS-4012F: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.fwversion,#1)<>last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.fwversion,#2) and length(last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.fwversion))>0`|Info|**Manual close**: Yes|
|ZYXEL GS-4012F: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.serialnumber,#1)<>last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.serialnumber,#2) and length(last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.serialnumber))>0`|Info|**Manual close**: Yes|
|ZYXEL GS-4012F: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/ZYXEL GS-4012F by SNMP/zyxel.4012f.cpuusage,5m)>{$CPU.UTIL.CRIT}`|Warning||

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>An entry in fanRpmTable.</p>|SNMP agent|zyxel.4012f.fan.discovery|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL GS-4012F: Fan #{#SNMPINDEX}|<p>MIB: ZYXEL-GS4012F-MIB</p><p>Current speed in Revolutions Per Minute (RPM) on the fan.</p>|SNMP agent|zyxel.4012f.fan[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Fan discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL GS-4012F: FAN{#SNMPINDEX} is in critical state|<p>Please check the fan unit</p>|`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.fan[{#SNMPINDEX}])<{#ZYXEL.FANRPM.THRESH.LOW}`|Average||

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>An entry in tempTable.</p><p>Index of temperature unit. 1:MAC, 2:CPU, 3:PHY</p>|SNMP agent|zyxel.4012f.temp.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL GS-4012F: Temperature "{#ZYXEL.TEMP.ID}"|<p>MIB: ZYXEL-GS4012F-MIB</p><p>The current temperature measured at this sensor</p>|SNMP agent|zyxel.4012f.temp[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL GS-4012F: Temperature {#ZYXEL.TEMP.ID} is in critical state|<p>Please check the temperature</p>|`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.temp[{#SNMPINDEX}])>{#ZYXEL.TEMP.THRESH.HIGH}`|Average||

### LLD rule Voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Voltage discovery|<p>An entry in voltageTable.</p>|SNMP agent|zyxel.4012f.volt.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL GS-4012F: Nominal "{#ZYXEL.VOLT.NOMINAL}"|<p>MIB: ZYXEL-GS4012F-MIB</p><p>The current voltage reading.</p>|SNMP agent|zyxel.4012f.volt[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Voltage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL GS-4012F: Voltage {#ZYXEL.VOLT.NOMINAL} is in critical state|<p>Please check the power supply</p>|`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.volt[{#SNMPINDEX}])<{#ZYXEL.VOLT.THRESH.LOW}`|Average||

### LLD rule Interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface discovery||SNMP agent|zyxel.4012f.net.if.discovery|

### Item prototypes for Interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Speed Duplex|<p>MIB: ZYXEL-GS4012F-MIB</p><p>Transmission mode</p>|SNMP agent|zyxel.4012f.net.if.speed_duplex[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Interface description|<p>MIB: ZYXEL-GS4012F-MIB</p><p>A textual string containing information about the interface</p>|SNMP agent|zyxel.4012f.net.if.name[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Link type|<p>MIB: ZYXEL-GS4012F-MIB</p><p>Physical connection type</p>|SNMP agent|zyxel.4012f.net.if.link_type[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Interface name|<p>MIB: IF-MIB</p><p>A textual string containing information about the interface</p>|SNMP agent|zyxel.4012f.net.if.descr[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>The testing(3) state indicates that no operational</p><p>packets can be passed.</p>|SNMP agent|zyxel.4012f.net.if.operstatus[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Administrative status|<p>MIB: IF-MIB</p><p>The desired state of the interface.  The</p><p>testing(3) state indicates that no operational</p><p>packets can be passed.</p>|SNMP agent|zyxel.4012f.net.if.adminstatus[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Incoming traffic|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface,</p><p>including framing characters.</p>|SNMP agent|zyxel.4012f.net.if.in.traffic[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Incoming unicast packages|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were not addressed to a multicast</p><p>or broadcast address at this sub-layer</p>|SNMP agent|zyxel.4012f.net.if.in.ucastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Incoming multicast packages|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were addressed to a multicast</p><p>address at this sub-layer.  For a MAC layer protocol, this</p><p>includes both Group and Functional addresses.</p>|SNMP agent|zyxel.4012f.net.if.in.multicastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Incoming broadcast packages|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were addressed to a broadcast</p><p>address at this sub-layer.</p>|SNMP agent|zyxel.4012f.net.if.in.broadcastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Outgoing traffic|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the</p><p>interface, including framing characters.  This object is a</p><p>64-bit version of ifOutOctets.</p>|SNMP agent|zyxel.4012f.net.if.out.traffic[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Outgoing unicast packages|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were not addressed to a</p><p>multicast or broadcast address at this sub-layer, including</p><p>those that were discarded or not sent.</p>|SNMP agent|zyxel.4012f.net.if.out.ucastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Outgoing multicast packages|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were addressed to a</p><p>multicast address at this sub-layer, including those that</p><p>were discarded or not sent.  For a MAC layer protocol, this</p><p>includes both Group and Functional addresses.</p>|SNMP agent|zyxel.4012f.net.if.out.multicastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Outgoing broadcast packages|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were addressed to a</p><p>broadcast address at this sub-layer, including those that</p><p>were discarded or not sent.</p>|SNMP agent|zyxel.4012f.net.if.out.broadcastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Link speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in bits per second</p>|SNMP agent|zyxel.4012f.net.if.highspeed[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Incoming utilization|<p>Interface utilization percentage</p>|Calculated|zyxel.4012f.net.if.in.util[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>In range: `0 -> 100`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Outgoing utilization|<p>Interface utilization percentage</p>|Calculated|zyxel.4012f.net.if.out.util[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>In range: `0 -> 100`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL GS-4012F: Port {#SNMPINDEX}: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.net.if.operstatus[{#SNMPINDEX}])=2 and last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.net.if.operstatus[{#SNMPINDEX}],#1)<>last(/ZYXEL GS-4012F by SNMP/zyxel.4012f.net.if.operstatus[{#SNMPINDEX}],#2)`|Average|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

