
# HP Comware HH3C by SNMP

## Overview

http://certifiedgeek.weebly.com/blog/hp-comware-snmp-mib-for-cpu-memory-and-temperature
http://www.h3c.com.hk/products___solutions/technology/system_management/configuration_example/200912/656451_57_0.htm

### Known Issues

Description: No temperature sensors. All entities of them return 0 for HH3C-ENTITY-EXT-MIB::hh3cEntityExtTemperature
- Version: 1910-48 Switch Software Version 5.20.99, Release 1116 Copyright(c)2010-2016 Hewlett Packard Enterprise Development LP
- Device: HP 1910-48

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- HP 1910-48 1910-48 Switch Software Version 5.20.99, Release 1116 Copyright(c)2010-2016 Hewlett Packard Enterprise Development LP
- HP A5500-24G-4SFP HP Comware Platform Software, Software Version 5.20.99 Release 5501P21 HP A5500-24G-4SFP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}||`90`|
|{$TEMP_CRIT}||`60`|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_WARN}||`50`|
|{$FAN_CRIT_STATUS:"fanError"}||`41`|
|{$FAN_CRIT_STATUS:"hardwareFaulty"}||`91`|
|{$PSU_CRIT_STATUS:"psuError"}||`51`|
|{$PSU_CRIT_STATUS:"rpsError"}||`61`|
|{$PSU_CRIT_STATUS:"hardwareFaulty"}||`91`|
|{$MEMORY.UTIL.MAX}||`90`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|
|{$IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|
|{$IF.UTIL.MAX}||`90`|
|{$IFCONTROL}||`1`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}||`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore notPresent(6)</p>|`^6$`|
|{$NET.IF.IFADMINSTATUS.MATCHES}||`^.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>Ignore down(2) administrative status</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}||`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}||`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFTYPE.MATCHES}||`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}||`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
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

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP Comware HH3C: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/HP Comware HH3C by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/HP Comware HH3C by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/HP Comware HH3C by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/HP Comware HH3C by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HP Comware HH3C: No SNMP data collection</li></ul>|
|HP Comware HH3C: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/HP Comware HH3C by SNMP/system.name,#1)<>last(/HP Comware HH3C by SNMP/system.name,#2) and length(last(/HP Comware HH3C by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|HP Comware HH3C: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/HP Comware HH3C by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>HP Comware HH3C: Unavailable by ICMP ping</li></ul>|
|HP Comware HH3C: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/HP Comware HH3C by SNMP/icmpping,#3)=0`|High||
|HP Comware HH3C: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/HP Comware HH3C by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/HP Comware HH3C by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>HP Comware HH3C: Unavailable by ICMP ping</li></ul>|
|HP Comware HH3C: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/HP Comware HH3C by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>HP Comware HH3C: High ICMP ping loss</li><li>HP Comware HH3C: Unavailable by ICMP ping</li></ul>|

### LLD rule Module Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Module Discovery|<p>Filter limits results to 'Module level1' or Fabric Modules</p>|SNMP agent|module.discovery|

### Item prototypes for Module Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#MODULE_NAME}: CPU utilization|<p>MIB: HH3C-ENTITY-EXT-MIB</p><p>The CPU usage for this entity. Generally, the CPU usage</p><p>will calculate the overall CPU usage on the entity, and it</p><p>is not sensible with the number of CPU on the entity</p>|SNMP agent|system.cpu.util[hh3cEntityExtCpuUsage.{#SNMPINDEX}]|
|{#MODULE_NAME}: Memory utilization|<p>MIB: HH3C-ENTITY-EXT-MIB</p><p>The memory usage for the entity. This object indicates what</p><p>percent of memory are used.</p>|SNMP agent|vm.memory.util[hh3cEntityExtMemUsage.{#SNMPINDEX}]|

### Trigger prototypes for Module Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP Comware HH3C: {#MODULE_NAME}: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/HP Comware HH3C by SNMP/system.cpu.util[hh3cEntityExtCpuUsage.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}`|Warning||
|HP Comware HH3C: {#MODULE_NAME}: High memory utilization|<p>The system is running out of free memory.</p>|`min(/HP Comware HH3C by SNMP/vm.memory.util[hh3cEntityExtMemUsage.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}`|Average||

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery|<p>Discovering modules temperature (same filter as in Module Discovery) plus and temperature sensors</p>|SNMP agent|temp.discovery|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Temperature|<p>MIB: HH3C-ENTITY-EXT-MIB</p><p>The temperature for the {#SNMPVALUE}.</p>|SNMP agent|sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP Comware HH3C: {#SNMPVALUE}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/HP Comware HH3C by SNMP/sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"}`|Warning|**Depends on**:<br><ul><li>HP Comware HH3C: {#SNMPVALUE}: Temperature is above critical threshold</li></ul>|
|HP Comware HH3C: {#SNMPVALUE}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/HP Comware HH3C by SNMP/sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"}`|High||
|HP Comware HH3C: {#SNMPVALUE}: Temperature is too low||`avg(/HP Comware HH3C by SNMP/sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`|Average||

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery|<p>Discovering all entities of PhysicalClass - 7: fan(7)</p>|SNMP agent|fan.discovery|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ENT_NAME}: Fan status|<p>MIB: HH3C-ENTITY-EXT-MIB</p><p>Indicate the error state of this entity object.</p><p>fanError(41) means that the fan stops working.</p>|SNMP agent|sensor.fan.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}]|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP Comware HH3C: {#ENT_NAME}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/HP Comware HH3C by SNMP/sensor.fan.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"fanError\"}")=1 or count(/HP Comware HH3C by SNMP/sensor.fan.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"hardwareFaulty\"}")=1`|Average||

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery|<p>Discovering all entities of PhysicalClass - 6: powerSupply(6)</p>|SNMP agent|psu.discovery|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ENT_NAME}: Power supply status|<p>MIB: HH3C-ENTITY-EXT-MIB</p><p>Indicate the error state of this entity object.</p><p>psuError(51) means that the Power Supply Unit is in the state of fault.</p><p>rpsError(61) means the Redundant Power Supply is in the state of fault.</p>|SNMP agent|sensor.psu.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP Comware HH3C: {#ENT_NAME}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/HP Comware HH3C by SNMP/sensor.psu.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"psuError\"}")=1 or count(/HP Comware HH3C by SNMP/sensor.psu.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"rpsError\"}")=1 or count(/HP Comware HH3C by SNMP/sensor.psu.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"hardwareFaulty\"}")=1`|Average||

### LLD rule Entity Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Entity Discovery||SNMP agent|entity.discovery|

### Item prototypes for Entity Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ENT_NAME}: Hardware model name|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.model[entPhysicalDescr.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#ENT_NAME}: Hardware serial number|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#ENT_NAME}: Firmware version|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.firmware[entPhysicalFirmwareRev.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#ENT_NAME}: Hardware version(revision)|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.version[entPhysicalHardwareRev.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#ENT_NAME}: Operating system|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Entity Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP Comware HH3C: {#ENT_NAME}: Device has been replaced|<p>The device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/HP Comware HH3C by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#1)<>last(/HP Comware HH3C by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#2) and length(last(/HP Comware HH3C by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|
|HP Comware HH3C: {#ENT_NAME}: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/HP Comware HH3C by SNMP/system.hw.firmware[entPhysicalFirmwareRev.{#SNMPINDEX}],#1)<>last(/HP Comware HH3C by SNMP/system.hw.firmware[entPhysicalFirmwareRev.{#SNMPINDEX}],#2) and length(last(/HP Comware HH3C by SNMP/system.hw.firmware[entPhysicalFirmwareRev.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|
|HP Comware HH3C: {#ENT_NAME}: Operating system description has changed|<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/HP Comware HH3C by SNMP/system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}],#1)<>last(/HP Comware HH3C by SNMP/system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}],#2) and length(last(/HP Comware HH3C by SNMP/system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HP Comware HH3C: System name has changed</li></ul>|

### LLD rule EtherLike-MIB Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|EtherLike-MIB Discovery|<p>Discovering interfaces from IF-MIB and EtherLike-MIB. Interfaces with up(1) Operational Status are discovered.</p>|SNMP agent|net.if.duplex.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for EtherLike-MIB Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Duplex status|<p>MIB: EtherLike-MIB</p><p>The current mode of operation of the MAC</p><p>entity.  'unknown' indicates that the current</p><p>duplex mode could not be determined.</p><p></p><p>Management control of the duplex mode is</p><p>accomplished through the MAU MIB.  When</p><p>an interface does not support autonegotiation,</p><p>or when autonegotiation is not enabled, the</p><p>duplex mode is controlled using</p><p>ifMauDefaultType.  When autonegotiation is</p><p>supported and enabled, duplex mode is controlled</p><p>using ifMauAutoNegAdvertisedBits.  In either</p><p>case, the currently operating duplex mode is</p><p>reflected both in this object and in ifMauType.</p><p></p><p>Note that this object provides redundant</p><p>information with ifMauType.  Normally, redundant</p><p>objects are discouraged.  However, in this</p><p>instance, it allows a management application to</p><p>determine the duplex status of an interface</p><p>without having to know every possible value of</p><p>ifMauType.  This was felt to be sufficiently</p><p>valuable to justify the redundancy.</p><p>Reference: [IEEE 802.3 Std.], 30.3.1.1.32,aDuplexStatus.</p>|SNMP agent|net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}]|

### Trigger prototypes for EtherLike-MIB Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP Comware HH3C: Interface {#IFNAME}({#IFALIAS}): In half-duplex mode|<p>Please check autonegotiation settings and cabling.</p>|`last(/HP Comware HH3C by SNMP/net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}])=2`|Warning|**Manual close**: Yes|

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.discovery|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p>|SNMP agent|net.if.status[ifOperStatus.{#SNMPINDEX}]|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p>|SNMP agent|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|SNMP agent|net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP Comware HH3C: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/HP Comware HH3C by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/HP Comware HH3C by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/HP Comware HH3C by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|HP Comware HH3C: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/HP Comware HH3C by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/HP Comware HH3C by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/HP Comware HH3C by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/HP Comware HH3C by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/HP Comware HH3C by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HP Comware HH3C: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|HP Comware HH3C: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/HP Comware HH3C by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/HP Comware HH3C by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HP Comware HH3C: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|HP Comware HH3C: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/HP Comware HH3C by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/HP Comware HH3C by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/HP Comware HH3C by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/HP Comware HH3C by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/HP Comware HH3C by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/HP Comware HH3C by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/HP Comware HH3C by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/HP Comware HH3C by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/HP Comware HH3C by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HP Comware HH3C: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

