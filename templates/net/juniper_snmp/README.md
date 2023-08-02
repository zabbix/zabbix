
# Juniper by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TEMP_CRIT}||`60`|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_WARN}||`50`|
|{$TEMP_CRIT:"Routing Engine"}||`80`|
|{$TEMP_WARN:"Routing Engine"}||`70`|
|{$HEALTH_CRIT_STATUS}||`3`|
|{$FAN_CRIT_STATUS}||`6`|
|{$PSU_CRIT_STATUS}||`6`|
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
|Juniper: Overall system health status|<p>MIB: JUNIPER-ALARM-MIB</p><p>The red alarm indication on the craft interface panel.</p><p>The red alarm is on when there is some system</p><p>failure or power supply failure or the system</p><p>is experiencing a hardware malfunction or some</p><p>threshold is being exceeded.</p><p></p><p>This red alarm state could be turned off by the</p><p>ACO/LT (Alarm Cut Off / Lamp Test) button on the</p><p>front panel module.</p>|SNMP agent|system.status[jnxRedAlarmState.0]|
|Juniper: Hardware serial number|<p>MIB: JUNIPER-MIB</p><p>The serial number of this subject, blank if unknown or unavailable.</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Juniper: Hardware model name|<p>MIB: JUNIPER-MIB</p><p>The name, model, or detailed description of the box,indicating which product the box is about, for example 'M40'.</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Juniper: Operating system|<p>MIB: SNMPv2-MIB</p>|SNMP agent|system.sw.os[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Regular expression: `kernel (JUNOS [0-9a-zA-Z\.\-]+) \1`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Juniper: Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Juniper: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Juniper: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|Juniper: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Juniper: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Juniper: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Juniper: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Juniper: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Juniper: SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|Juniper: ICMP ping||Simple check|icmpping|
|Juniper: ICMP loss||Simple check|icmppingloss|
|Juniper: ICMP response time||Simple check|icmppingsec|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Juniper: System status is in critical state|<p>Please check the device for errors</p>|`count(/Juniper by SNMP/system.status[jnxRedAlarmState.0],#1,"eq","{$HEALTH_CRIT_STATUS}")=1`|High||
|Juniper: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Juniper by SNMP/system.hw.serialnumber,#1)<>last(/Juniper by SNMP/system.hw.serialnumber,#2) and length(last(/Juniper by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Juniper: Operating system description has changed|<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/Juniper by SNMP/system.sw.os[sysDescr.0],#1)<>last(/Juniper by SNMP/system.sw.os[sysDescr.0],#2) and length(last(/Juniper by SNMP/system.sw.os[sysDescr.0]))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Juniper: System name has changed</li></ul>|
|Juniper: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Juniper by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Juniper by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Juniper by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Juniper by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Juniper: No SNMP data collection</li></ul>|
|Juniper: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Juniper by SNMP/system.name,#1)<>last(/Juniper by SNMP/system.name,#2) and length(last(/Juniper by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Juniper: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Juniper by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Juniper: Unavailable by ICMP ping</li></ul>|
|Juniper: Unavailable by ICMP ping|<p>Last three attempts returned timeout.  Please check device connectivity.</p>|`max(/Juniper by SNMP/icmpping,#3)=0`|High||
|Juniper: High ICMP ping loss||`min(/Juniper by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Juniper by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Juniper: Unavailable by ICMP ping</li></ul>|
|Juniper: High ICMP ping response time||`avg(/Juniper by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Juniper: High ICMP ping loss</li><li>Juniper: Unavailable by ICMP ping</li></ul>|

### LLD rule CPU and Memory Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU and Memory Discovery|<p>Scanning JUNIPER-MIB::jnxOperatingTable for CPU and Memory</p><p>http://kb.juniper.net/InfoCenter/index?page=content&id=KB17526&actp=search. Filter limits results to Routing Engines</p>|SNMP agent|jnxOperatingTable.discovery|

### Item prototypes for CPU and Memory Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: CPU utilization|<p>MIB: JUNIPER-MIB</p><p>The CPU utilization in percentage of this subject. Zero if unavailable or inapplicable.</p><p>Reference: http://kb.juniper.net/library/CUSTOMERSERVICE/GLOBAL_JTAC/BK26199/SRX%20SNMP%20Monitoring%20Guide_v1.1.pdf</p>|SNMP agent|system.cpu.util[jnxOperatingCPU.{#SNMPINDEX}]|
|{#SNMPVALUE}: Memory utilization|<p>MIB: JUNIPER-MIB</p><p>The buffer pool utilization in percentage of this subject.  Zero if unavailable or inapplicable.</p><p>Reference: http://kb.juniper.net/library/CUSTOMERSERVICE/GLOBAL_JTAC/BK26199/SRX%20SNMP%20Monitoring%20Guide_v1.1.pdf</p>|SNMP agent|vm.memory.util[jnxOperatingBuffer.{#SNMPINDEX}]|

### Trigger prototypes for CPU and Memory Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Juniper by SNMP/system.cpu.util[jnxOperatingCPU.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}`|Warning||
|{#SNMPVALUE}: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Juniper by SNMP/vm.memory.util[jnxOperatingBuffer.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}`|Average||

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>Scanning JUNIPER-MIB::jnxOperatingTable for Temperature</p><p>http://kb.juniper.net/InfoCenter/index?page=content&id=KB17526&actp=search. Filter limits results to Routing Engines</p>|SNMP agent|jnxOperatingTable.discovery.temp|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_INFO}: Temperature|<p>MIB: JUNIPER-MIB</p><p>The temperature in Celsius (degrees C) of {#SENSOR_INFO}</p>|SNMP agent|sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}]|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SENSOR_INFO}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Juniper by SNMP/sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SENSOR_INFO}"}`|Warning|**Depends on**:<br><ul><li>{#SENSOR_INFO}: Temperature is above critical threshold</li></ul>|
|{#SENSOR_INFO}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Juniper by SNMP/sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SENSOR_INFO}"}`|High||
|{#SENSOR_INFO}: Temperature is too low||`avg(/Juniper by SNMP/sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SENSOR_INFO}"}`|Average||

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery|<p>Scanning JUNIPER-MIB::jnxOperatingTable for Fans</p>|SNMP agent|jnxOperatingTable.discovery.fans|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Fan status|<p>MIB: JUNIPER-MIB</p>|SNMP agent|sensor.fan.status[jnxOperatingState.4.{#SNMPINDEX}]|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/Juniper by SNMP/sensor.fan.status[jnxOperatingState.4.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1`|Average||

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery|<p>Scanning JUNIPER-MIB::jnxOperatingTable for Power Supplies</p>|SNMP agent|jnxOperatingTable.discovery.psu|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Power supply status|<p>MIB: JUNIPER-MIB</p><p>If they are using DC power supplies there is a known issue on PR 1064039 where the fans do not detect the temperature correctly and fail to cool the power supply causing the shutdown to occur.</p><p>This is fixed in Junos 13.3R7 https://forums.juniper.net/t5/Routing/PEM-0-not-OK-MX104/m-p/289644#M14122</p>|SNMP agent|sensor.psu.status[jnxOperatingState.2.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/Juniper by SNMP/sensor.psu.status[jnxOperatingState.2.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1`|Average||

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
|Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Juniper by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Juniper by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Juniper by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Juniper by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Juniper by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Juniper by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Juniper by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Juniper by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Juniper by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Juniper by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Juniper by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Juniper by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Juniper by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Juniper by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Juniper by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Juniper by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Juniper by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Juniper by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Juniper by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

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
|Interface {#IFNAME}({#IFALIAS}): In half-duplex mode|<p>Please check autonegotiation settings and cabling</p>|`last(/Juniper by SNMP/net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}])=2`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

