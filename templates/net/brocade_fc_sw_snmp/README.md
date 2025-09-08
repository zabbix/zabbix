
# Brocade FC by SNMP

## Overview

https://community.brocade.com/dtscp75322/attachments/dtscp75322/fibre/25235/1/FOS_MIB_Reference_v740.pdf

### Known Issues
- Description: no IF-MIB::ifAlias is available
   - version: v6.3.1c, v7.0.0c,  v7.4.1c

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Brocade 6520 v7.4.1c
- Brocade 300 v7.0.0c
- Brocade BL 5480 v6.3.1c

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_CRIT}||`75`|
|{$TEMP_WARN}||`65`|
|{$HEALTH_CRIT_STATUS}||`4`|
|{$HEALTH_WARN_STATUS:"offline"}||`2`|
|{$HEALTH_WARN_STATUS:"testing"}||`3`|
|{$TEMP_WARN_STATUS}||`5`|
|{$PSU_CRIT_STATUS}||`2`|
|{$FAN_CRIT_STATUS}||`2`|
|{$PSU_OK_STATUS}||`4`|
|{$FAN_OK_STATUS}||`4`|
|{$MEMORY.UTIL.MAX}||`90`|
|{$CPU.UTIL.CRIT}||`90`|
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
|CPU utilization|<p>MIB: SW-MIB</p><p>System's CPU usage.</p>|SNMP agent|system.cpu.util[swCpuUsage.0]|
|Memory utilization|<p>MIB: SW-MIB</p><p>Memory utilization in %.</p>|SNMP agent|vm.memory.util[swMemUsage.0]|
|Overall system health status|<p>MIB: SW-MIB</p><p>The current operational status of the switch.The states are as follow:</p><p>online(1) means the switch is accessible by an external Fibre Channel port</p><p>offline(2) means the switch is not accessible</p><p>testing(3) means the switch is in a built-in test mode and is not accessible by an external Fibre Channel port</p><p>faulty(4) means the switch is not operational.</p>|SNMP agent|system.status[swOperStatus.0]|
|Hardware serial number|<p>MIB: SW-MIB</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Firmware version|<p>MIB: SW-MIB</p>|SNMP agent|system.hw.firmware<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
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
|Brocade FC: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Brocade FC by SNMP/system.cpu.util[swCpuUsage.0],5m)>{$CPU.UTIL.CRIT}`|Warning||
|Brocade FC: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Brocade FC by SNMP/vm.memory.util[swMemUsage.0],5m)>{$MEMORY.UTIL.MAX}`|Average||
|Brocade FC: System status is in critical state|<p>Please check the device for errors</p>|`count(/Brocade FC by SNMP/system.status[swOperStatus.0],#1,"eq","{$HEALTH_CRIT_STATUS}")=1`|High||
|Brocade FC: System status is in warning state|<p>Please check the device for warnings</p>|`count(/Brocade FC by SNMP/system.status[swOperStatus.0],#1,"eq","{$HEALTH_WARN_STATUS:\"offline\"}")=1 or count(/Brocade FC by SNMP/system.status[swOperStatus.0],#1,"eq","{$HEALTH_WARN_STATUS:\"testing\"}")=1`|Warning|**Depends on**:<br><ul><li>Brocade FC: System status is in critical state</li></ul>|
|Brocade FC: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Brocade FC by SNMP/system.hw.serialnumber,#1)<>last(/Brocade FC by SNMP/system.hw.serialnumber,#2) and length(last(/Brocade FC by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Brocade FC: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/Brocade FC by SNMP/system.hw.firmware,#1)<>last(/Brocade FC by SNMP/system.hw.firmware,#2) and length(last(/Brocade FC by SNMP/system.hw.firmware))>0`|Info|**Manual close**: Yes|
|Brocade FC: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Brocade FC by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Brocade FC by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Brocade FC by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Brocade FC by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade FC: No SNMP data collection</li></ul>|
|Brocade FC: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Brocade FC by SNMP/system.name,#1)<>last(/Brocade FC by SNMP/system.name,#2) and length(last(/Brocade FC by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Brocade FC: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Brocade FC by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Brocade FC: Unavailable by ICMP ping</li></ul>|
|Brocade FC: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Brocade FC by SNMP/icmpping,#3)=0`|High||
|Brocade FC: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/Brocade FC by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Brocade FC by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Brocade FC: Unavailable by ICMP ping</li></ul>|
|Brocade FC: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Brocade FC by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Brocade FC: High ICMP ping loss</li><li>Brocade FC: Unavailable by ICMP ping</li></ul>|

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery||SNMP agent|temperature.discovery|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_INFO}: Temperature|<p>MIB: SW-MIB</p><p>Temperature readings of testpoint: {#SENSOR_INFO}</p>|SNMP agent|sensor.temp.value[swSensorValue.{#SNMPINDEX}]|
|{#SENSOR_INFO}: Temperature status|<p>MIB: SW-MIB</p><p>Temperature status of testpoint: {#SENSOR_INFO}</p>|SNMP agent|sensor.temp.status[swSensorStatus.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade FC: {#SENSOR_INFO}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Brocade FC by SNMP/sensor.temp.value[swSensorValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SENSOR_INFO}"} or last(/Brocade FC by SNMP/sensor.temp.status[swSensorStatus.{#SNMPINDEX}])={$TEMP_WARN_STATUS}`|Warning|**Depends on**:<br><ul><li>Brocade FC: {#SENSOR_INFO}: Temperature is above critical threshold</li></ul>|
|Brocade FC: {#SENSOR_INFO}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Brocade FC by SNMP/sensor.temp.value[swSensorValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SENSOR_INFO}"}`|High||
|Brocade FC: {#SENSOR_INFO}: Temperature is too low||`avg(/Brocade FC by SNMP/sensor.temp.value[swSensorValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SENSOR_INFO}"}`|Average||

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery||SNMP agent|psu.discovery|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_INFO}: Power supply status|<p>MIB: SW-MIB</p>|SNMP agent|sensor.psu.status[swSensorStatus.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade FC: {#SENSOR_INFO}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/Brocade FC by SNMP/sensor.psu.status[swSensorStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1`|Average||
|Brocade FC: {#SENSOR_INFO}: Power supply is not in normal state|<p>Please check the power supply unit for errors</p>|`count(/Brocade FC by SNMP/sensor.psu.status[swSensorStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1`|Info|**Depends on**:<br><ul><li>Brocade FC: {#SENSOR_INFO}: Power supply is in critical state</li></ul>|

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery||SNMP agent|fan.discovery|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_INFO}: Fan status|<p>MIB: SW-MIB</p>|SNMP agent|sensor.fan.status[swSensorStatus.{#SNMPINDEX}]|
|{#SENSOR_INFO}: Fan speed|<p>MIB: SW-MIB</p><p>The current value (reading) of the sensor.</p><p>The value, -2147483648, represents an unknown quantity.</p><p>The fan value will be in RPM(revolution per minute)</p>|SNMP agent|sensor.fan.speed[swSensorValue.{#SNMPINDEX}]|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade FC: {#SENSOR_INFO}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/Brocade FC by SNMP/sensor.fan.status[swSensorStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1`|Average||
|Brocade FC: {#SENSOR_INFO}: Fan is not in normal state|<p>Please check the fan unit</p>|`count(/Brocade FC by SNMP/sensor.fan.status[swSensorStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1`|Info|**Depends on**:<br><ul><li>Brocade FC: {#SENSOR_INFO}: Fan is in critical state</li></ul>|

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
|Brocade FC: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Brocade FC by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Brocade FC by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Brocade FC by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Brocade FC: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Brocade FC by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Brocade FC by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Brocade FC by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Brocade FC by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Brocade FC by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade FC: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Brocade FC: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Brocade FC by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Brocade FC by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade FC: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Brocade FC: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Brocade FC by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Brocade FC by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Brocade FC by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Brocade FC by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Brocade FC by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Brocade FC by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Brocade FC by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Brocade FC by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Brocade FC by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade FC: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

