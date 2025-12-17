
# Ciena 3906 by SNMP

## Overview

Ciena’s 3906 Platform is a compact, smart CPE that delivers gigabit Ethernet service capability with virtual network function integration.
Learn more about the Ciena 3906 Platform here: https://www.ciena.com/products/3906

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Ciena 3906

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CIENA.MEMORY.UTIL.MAX}|<p>Threshold of memory utilization expressed in %.</p>|`90`|
|{$CIENA.CPU.UTIL.CRIT}|<p>Threshold of CPU utilization expressed in %.</p>|`90`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|
|{$IFCONTROL}|<p>Link status trigger will be fired only for interfaces where the context macro equals "1".</p>|`1`|
|{$IF.UTIL.MAX}|<p>Used as a threshold in the interface utilization trigger.</p>|`90`|
|{$IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|
|{$NET.IF.IFNAME.MATCHES}|<p>Used for network interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filters out `loopbacks`, `nulls`, docker `veth` links and `docker0 bridge` by default.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}|<p>Used for network interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore `notPresent(6)`</p>|`^6$`|
|{$NET.IF.IFADMINSTATUS.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`^.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>Ignore `down(2)` administrative status</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}|<p>Used in network interface discovery rule filters.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}|<p>Used in network interface discovery rule filters.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFTYPE.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}|<p>Used in network interface discovery rule filters.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hardware model name|<p>MIB: WWP-LEOS-BLADE-MIB</p><p>Model name of the hardware.</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Hardware serial number|<p>MIB: WWP-LEOS-BLADE-MIB</p><p>The serial number of the product.</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Hardware version (revision)|<p>MIB: WWP-LEOS-BLADE-MIB</p><p>The hardware version of the product.</p>|SNMP agent|system.hw.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|CPU utilization|<p>MIB: WWP-LEOS-SYSTEM-CONFIG-MIB</p><p>CPU utilization over 60 seconds.</p>|SNMP agent|ciena.cpu.utilization|
|Memory available|<p>MIB: WWP-LEOS-SYSTEM-CONFIG-MIB</p><p>Available memory in bytes.</p>|SNMP agent|ciena.memory.available<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory used|<p>MIB: WWP-LEOS-SYSTEM-CONFIG-MIB</p><p>Used memory in bytes.</p>|SNMP agent|ciena.memory.used<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory utilization|<p>Memory utilization, in percent.</p>|Calculated|ciena.memory.utilization|
|SNMP walk temperature sensors|<p>MIB: WWP-LEOS-CHASSIS-MIB</p><p>Used for discovering system temperature sensors.</p>|SNMP agent|system.temperature.sensor.walk|
|SNMP walk fan|<p>MIB: WWP-LEOS-CHASSIS-MIB</p><p>Used for discovering system fans.</p>|SNMP agent|system.fan.walk|
|SNMP walk PSU|<p>MIB: WWP-LEOS-CHASSIS-MIB</p><p>Used for discovering the system power supply.</p>|SNMP agent|system.psu.walk|
|Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>Time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|System location|<p>MIB: SNMPv2-MIB</p><p>Physical location of the node (e.g., `equipment room`, `3rd floor`). If not provided, the value is a zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person. If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity. This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|ICMP ping|<p>The host accessibility by ICMP ping.</p><p></p><p>0 - ICMP ping fails;</p><p>1 - ICMP ping successful.</p>|Simple check|icmpping|
|ICMP loss|<p>The percentage of lost packets.</p>|Simple check|icmppingloss|
|ICMP response time|<p>The ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|
|SNMP walk network interfaces|<p>Used for discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.walk|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ciena: Device has been replaced|<p>The Ciena device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Ciena 3906 by SNMP/system.hw.serialnumber,#1)<>last(/Ciena 3906 by SNMP/system.hw.serialnumber,#2) and length(last(/Ciena 3906 by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Ciena: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Ciena 3906 by SNMP/ciena.cpu.utilization,5m)>{$CIENA.CPU.UTIL.CRIT}`|Warning||
|Ciena: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Ciena 3906 by SNMP/ciena.memory.utilization,5m)>{$CIENA.MEMORY.UTIL.MAX}`|Average||
|Ciena: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Ciena 3906 by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Ciena 3906 by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Ciena 3906 by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Ciena 3906 by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Ciena: No SNMP data collection</li></ul>|
|Ciena: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Ciena 3906 by SNMP/system.name,#1)<>last(/Ciena 3906 by SNMP/system.name,#2) and length(last(/Ciena 3906 by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Ciena: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Ciena 3906 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Ciena: Unavailable by ICMP ping</li></ul>|
|Ciena: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Ciena 3906 by SNMP/icmpping,#3)=0`|High||
|Ciena: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/Ciena 3906 by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Ciena 3906 by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Ciena: Unavailable by ICMP ping</li></ul>|
|Ciena: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Ciena 3906 by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Ciena: High ICMP ping loss</li><li>Ciena: Unavailable by ICMP ping</li></ul>|

### LLD rule Temperature sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature sensor discovery|<p>Used for discovering temperature sensors from WWP-LEOS-CHASSIS-MIB.</p>|Dependent item|ciena.temperature.sensor.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Temperature sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#SNMPVALUE}]: Temperature|<p>MIB: WWP-LEOS-CHASSIS-MIB</p><p>The value of temperature measured by the sensor inside the device in degrees Celsius.</p>|Dependent item|ciena.temperature.sensors.temp[ChassisTempSensorValue.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.6141.2.60.11.1.1.5.1.1.2.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Temperature sensor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ciena: Sensor [{#SNMPVALUE}]: High chassis temperature|<p>The current temperature is higher than the threshold state.</p>|`min(/Ciena 3906 by SNMP/ciena.temperature.sensors.temp[ChassisTempSensorValue.{#SNMPINDEX}],5m)>{#CIENA.TEMPERATURE.HIGH}`|Average||
|Ciena: Sensor [{#SNMPVALUE}]: Low chassis temperature|<p>The current temperature is lower than the threshold state.</p>|`max(/Ciena 3906 by SNMP/ciena.temperature.sensors.temp[ChassisTempSensorValue.{#SNMPINDEX}],5m)<{#CIENA.TEMPERATURE.LOW}`|Average||

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>Used for discovering fans from WWP-LEOS-CHASSIS-MIB.</p>|Dependent item|ciena.fan.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#SNMPVALUE}]: Status|<p>MIB: WWP-LEOS-CHASSIS-MIB</p><p>Denotes the fan module status.</p><p>Possible values:</p><p>1 - "ok"; means fan is operational;</p><p>2 - "pending"; means fan is installed but statistics are not yet available;</p><p>3 - "failure"; means fan is not working.</p>|Dependent item|ciena.fan.status[ChassisFanModuleStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.6141.2.60.11.1.1.4.1.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Fan [{#SNMPVALUE}]: Speed|<p>MIB: WWP-LEOS-CHASSIS-MIB</p><p>The current speed of the fan in RPM.</p>|Dependent item|ciena.fan.speed[ChassisFanCurrentSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.6141.2.60.11.1.1.4.1.1.5.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Fan discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ciena: Fan [{#SNMPVALUE}]: Fan status is Failure|<p>The fan status is "failure".</p>|`last(/Ciena 3906 by SNMP/ciena.fan.status[ChassisFanModuleStatus.{#SNMPINDEX}])=3`|Average||

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>Used for discovering PSU from WWP-LEOS-CHASSIS-MIB.</p>|Dependent item|ciena.psu.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU [{#SNMPVALUE}]: Status|<p>MIB: WWP-LEOS-CHASSIS-MIB</p><p>Denotes the PSU module status.</p><p>Possible values:</p><p>1 - online;</p><p>2 - offline;</p><p>3 - faulted.</p>|Dependent item|ciena.psu.status[ChassisPowerSupplyState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.6141.2.60.11.1.1.3.1.1.2.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ciena: PSU [{#SNMPVALUE}]: Status is Faulted|<p>The PSU status is "faulted".</p>|`last(/Ciena 3906 by SNMP/ciena.psu.status[ChassisPowerSupplyState.{#SNMPINDEX}])=3`|Average||

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering interfaces from IF-MIB.</p>|Dependent item|net.if.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p>|Dependent item|net.if.status[ifOperStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.8.{#SNMPINDEX}`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.6.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.10.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.14.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.20.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.19.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.13.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p>|Dependent item|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n`, then the speed of the interface is somewhere in the range of `n-500,000` to `n+499,999`.</p><p>For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth.</p><p>For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|Dependent item|net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.15.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ciena: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Ciena 3906 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Ciena 3906 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Ciena 3906 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Ciena: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Ciena 3906 by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Ciena 3906 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Ciena 3906 by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Ciena 3906 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Ciena 3906 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Ciena: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Ciena: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Ciena 3906 by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Ciena 3906 by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Ciena: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Ciena: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Ciena 3906 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Ciena 3906 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Ciena 3906 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Ciena 3906 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Ciena 3906 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Ciena 3906 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Ciena 3906 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Ciena 3906 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Ciena 3906 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Ciena: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

