
# Alcatel Timetra TiMOS by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_CRIT}||`75`|
|{$TEMP_WARN}||`65`|
|{$PSU_CRIT_STATUS}||`4`|
|{$FAN_CRIT_STATUS}||`4`|
|{$MEMORY.UTIL.MAX}||`90`|
|{$CPU.UTIL.CRIT}||`90`|
|{$SNMP.TIMEOUT}||`5m`|
|{$ICMP_LOSS_WARN}||`20`|
|{$ICMP_RESPONSE_TIME_WARN}||`0.15`|
|{$IF.ERRORS.WARN}||`2`|
|{$IF.UTIL.MAX}||`90`|
|{$IFCONTROL}||`1`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}||`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore notPresent(6)</p>|`^6$`|
|{$NET.IF.IFADMINSTATUS.MATCHES}|<p>Ignore notPresent(6)</p>|`^.*`|
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
|Alcatel Timetra TiMOS: CPU utilization|<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The value of sgiCpuUsage indicates the current CPU utilization for the system.</p>|SNMP agent|system.cpu.util[sgiCpuUsage.0]|
|Alcatel Timetra TiMOS: Used memory|<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The value of sgiKbMemoryUsed indicates the total pre-allocated pool memory, in kilobytes, currently in use on the system.</p>|SNMP agent|vm.memory.used[sgiKbMemoryUsed.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Alcatel Timetra TiMOS: Available memory|<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The value of sgiKbMemoryAvailable indicates the amount of free memory, in kilobytes, in the overall system that is not allocated to memory pools, but is available in case a memory pool needs to grow.</p>|SNMP agent|vm.memory.available[sgiKbMemoryAvailable.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Alcatel Timetra TiMOS: Total memory|<p>The total memory expressed in bytes.</p>|Calculated|vm.memory.total[snmp]|
|Alcatel Timetra TiMOS: Memory utilization|<p>Memory utilization in %.</p>|Calculated|vm.memory.util[vm.memory.util.0]|
|Alcatel Timetra TiMOS: Hardware model name|<p>MIB: SNMPv2-MIB</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Regular expression: `^(\w|-|\.|/)+ (\w|-|\.|/)+ (.+) Copyright \3`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Alcatel Timetra TiMOS: Operating system|<p>MIB: SNMPv2-MIB</p>|SNMP agent|system.sw.os[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Regular expression: `^((\w|-|\.|/)+) \1`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Alcatel Timetra TiMOS: Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Alcatel Timetra TiMOS: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Alcatel Timetra TiMOS: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|Alcatel Timetra TiMOS: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alcatel Timetra TiMOS: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alcatel Timetra TiMOS: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alcatel Timetra TiMOS: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alcatel Timetra TiMOS: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Alcatel Timetra TiMOS: SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|Alcatel Timetra TiMOS: ICMP ping||Simple check|icmpping|
|Alcatel Timetra TiMOS: ICMP loss||Simple check|icmppingloss|
|Alcatel Timetra TiMOS: ICMP response time||Simple check|icmppingsec|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Alcatel Timetra TiMOS: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Alcatel Timetra TiMOS by SNMP/system.cpu.util[sgiCpuUsage.0],5m)>{$CPU.UTIL.CRIT}`|Warning||
|Alcatel Timetra TiMOS: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Alcatel Timetra TiMOS by SNMP/vm.memory.util[vm.memory.util.0],5m)>{$MEMORY.UTIL.MAX}`|Average||
|Alcatel Timetra TiMOS: Operating system description has changed|<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/Alcatel Timetra TiMOS by SNMP/system.sw.os[sysDescr.0],#1)<>last(/Alcatel Timetra TiMOS by SNMP/system.sw.os[sysDescr.0],#2) and length(last(/Alcatel Timetra TiMOS by SNMP/system.sw.os[sysDescr.0]))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Alcatel Timetra TiMOS: System name has changed</li></ul>|
|Alcatel Timetra TiMOS: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Alcatel Timetra TiMOS by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Alcatel Timetra TiMOS by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Alcatel Timetra TiMOS by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Alcatel Timetra TiMOS by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Alcatel Timetra TiMOS: No SNMP data collection</li></ul>|
|Alcatel Timetra TiMOS: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Alcatel Timetra TiMOS by SNMP/system.name,#1)<>last(/Alcatel Timetra TiMOS by SNMP/system.name,#2) and length(last(/Alcatel Timetra TiMOS by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Alcatel Timetra TiMOS: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Alcatel Timetra TiMOS by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Alcatel Timetra TiMOS: Unavailable by ICMP ping</li></ul>|
|Alcatel Timetra TiMOS: Unavailable by ICMP ping|<p>Last three attempts returned timeout.  Please check device connectivity.</p>|`max(/Alcatel Timetra TiMOS by SNMP/icmpping,#3)=0`|High||
|Alcatel Timetra TiMOS: High ICMP ping loss||`min(/Alcatel Timetra TiMOS by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Alcatel Timetra TiMOS by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Alcatel Timetra TiMOS: Unavailable by ICMP ping</li></ul>|
|Alcatel Timetra TiMOS: High ICMP ping response time||`avg(/Alcatel Timetra TiMOS by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Alcatel Timetra TiMOS: High ICMP ping loss</li><li>Alcatel Timetra TiMOS: Unavailable by ICMP ping</li></ul>|

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery||SNMP agent|temperature.discovery|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Temperature|<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The current temperature reading in degrees celsius from this hardware component's temperature sensor.  If this component does not contain a temperature sensor, then the value -1 is returned.</p>|SNMP agent|sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Alcatel Timetra TiMOS by SNMP/sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"}`|Warning|**Depends on**:<br><ul><li>{#SNMPVALUE}: Temperature is above critical threshold</li></ul>|
|{#SNMPVALUE}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Alcatel Timetra TiMOS by SNMP/sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"}`|High||
|{#SNMPVALUE}: Temperature is too low||`avg(/Alcatel Timetra TiMOS by SNMP/sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`|Average||

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery||SNMP agent|fan.discovery|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|#{#SNMPINDEX}: Fan status|<p>MIB: TIMETRA-SYSTEM-MIB</p><p>Current status of the Fan tray.</p>|SNMP agent|sensor.fan.status[tmnxChassisFanOperStatus.{#SNMPINDEX}]|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|#{#SNMPINDEX}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/Alcatel Timetra TiMOS by SNMP/sensor.fan.status[tmnxChassisFanOperStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1`|Average||

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery||SNMP agent|psu.discovery|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|#{#SNMPINDEX}: Power supply status|<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The overall status of an equipped power supply.</p><p>For AC multiple power supplies, this represents the overall status of the first power supply in the tray (or shelf).</p><p>For any other type, this represents the overall status of the power supply.</p><p>If tmnxChassisPowerSupply1Status is'deviceStateOk', then all monitored statuses are 'deviceStateOk'.</p><p>A value of 'deviceStateFailed' represents a condition where at least one monitored status is in a failed state.</p>|SNMP agent|sensor.psu.status[tmnxChassisPowerSupply1Status.{#SNMPINDEX}]|
|#{#SNMPINDEX}: Power supply status|<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The overall status of an equipped power supply.</p><p>For AC multiple power supplies, this represents the overall status of the second power supply in the tray (or shelf).</p><p>For any other type, this field is unused and set to 'deviceNotEquipped'.</p><p>If tmnxChassisPowerSupply2Status is 'deviceStateOk', then all monitored statuses are 'deviceStateOk'.</p><p>A value of 'deviceStateFailed' represents a condition where at least one monitored status is in a failed state.</p>|SNMP agent|sensor.psu.status[tmnxChassisPowerSupply2Status.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|#{#SNMPINDEX}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/Alcatel Timetra TiMOS by SNMP/sensor.psu.status[tmnxChassisPowerSupply1Status.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1`|Average||
|#{#SNMPINDEX}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/Alcatel Timetra TiMOS by SNMP/sensor.psu.status[tmnxChassisPowerSupply2Status.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1`|Average||

### LLD rule Entity Serial Numbers Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Entity Serial Numbers Discovery||SNMP agent|entity_sn.discovery|

### Item prototypes for Entity Serial Numbers Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ENT_NAME}: Hardware serial number|<p>MIB: TIMETRA-CHASSIS-MIB</p>|SNMP agent|system.hw.serialnumber[tmnxHwSerialNumber.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Entity Serial Numbers Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#ENT_NAME}: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Alcatel Timetra TiMOS by SNMP/system.hw.serialnumber[tmnxHwSerialNumber.{#SNMPINDEX}],#1)<>last(/Alcatel Timetra TiMOS by SNMP/system.hw.serialnumber[tmnxHwSerialNumber.{#SNMPINDEX}],#2) and length(last(/Alcatel Timetra TiMOS by SNMP/system.hw.serialnumber[tmnxHwSerialNumber.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

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
|Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Alcatel Timetra TiMOS by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Alcatel Timetra TiMOS by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Alcatel Timetra TiMOS by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Alcatel Timetra TiMOS by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Alcatel Timetra TiMOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Alcatel Timetra TiMOS by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Alcatel Timetra TiMOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Alcatel Timetra TiMOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Alcatel Timetra TiMOS by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Alcatel Timetra TiMOS by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Alcatel Timetra TiMOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Alcatel Timetra TiMOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Alcatel Timetra TiMOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Alcatel Timetra TiMOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Alcatel Timetra TiMOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Alcatel Timetra TiMOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Alcatel Timetra TiMOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Alcatel Timetra TiMOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Alcatel Timetra TiMOS by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

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
|Interface {#IFNAME}({#IFALIAS}): In half-duplex mode|<p>Please check autonegotiation settings and cabling</p>|`last(/Alcatel Timetra TiMOS by SNMP/net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}])=2`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

