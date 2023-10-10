
# Mellanox by SNMP

## Overview

This template is designed for the effortless deployment of Mellanox monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Mellanox

## Configuration

The template uses context macros for the temperature trigger
expression. By default, it uses a macro value like {$TEMP.MAX.CRIT}. To adjust
the threshold for a certain sensor you can define context macros on the host
level, with a value corresponding to your device specifications, for example:
{$TEMP.MAX.CRIT:"MGMT/BOARD_MONITOR"}. Please, read https://www.zabbix.com/documentation/7.0/manual/config/macros/user_macros_context
for more detailed info on user context macros.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IFCONTROL}||`1`|
|{$PSU.STATUS.CRIT}|<p>The critical value of the PSU sensor for trigger expression.</p>|`2`|
|{$ICMP_LOSS_WARN}||`20`|
|{$ICMP_RESPONSE_TIME_WARN}||`0.15`|
|{$FAN_CRIT_STATUS}|<p>The critical value of the FAN sensor for trigger expression.</p>|`3`|
|{$TEMP.STATUS.WARN}|<p>The critical value of the TEMP sensor for trigger expression.</p>|`3`|
|{$TEMP.MAX.CRIT}|<p>The temperature maximum critical value for trigger expression.</p>|`60`|
|{$TEMP.MAX.WARN}|<p>The temperature maximum warning value for trigger expression.</p>|`50`|
|{$TEMP.MIN.CRIT}|<p>The temperature minimum critical value for trigger expression.</p>|`5`|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host level.</p>|`^(/dev\|/sys\|/$\|/run\|/proc\|.+/shm$)`|
|{$VFS.FS.FSNAME.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host level.</p>|`.+`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host level.</p>|`CHANGE_IF_NEEDED`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host level.</p>|`.*(\.4\|\.9\|hrStorageFixedDisk\|hrStorageFlashMemory)$`|
|{$MEMORY.UTIL.MAX}|<p>The warning threshold of the "Physical memory: Memory utilization" item.</p>|`90`|
|{$MEMORY.TYPE.NOT_MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host level if you need to filter out results.</p>|`CHANGE_IF_NEEDED`|
|{$MEMORY.TYPE.MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host level.</p>|`.*(\.2\|hrStorageRam)$`|
|{$MEMORY.NAME.MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host level.</p>|`.*`|
|{$MEMORY.NAME.NOT_MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host level if you need to filter out results.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}||`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore notPresent(6).</p>|`^6$`|
|{$NET.IF.IFADMINSTATUS.MATCHES}|<p>Ignore notPresent(6)</p>|`^.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>Ignore down(2) administrative status.</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}||`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}||`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFTYPE.MATCHES}||`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$SNMP.TIMEOUT}|<p>The time interval for SNMP agent availability trigger expression.</p>|`5m`|
|{$ICMP.LOSS.WARN}||`20`|
|{$ICMP.RESPONSE_TIME.WARN}||`0.15`|
|{$VFS.FS.FREE.MIN.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`5G`|
|{$VFS.FS.FREE.MIN.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`10G`|
|{$CPU.UTIL.CRIT}||`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mellanox: CPU utilization|<p>MIB: HOST-RESOURCES-MIB</p><p>The average, over the last minute, of the percentage of time that processors was not idle.</p><p>Implementations may approximate this one minute smoothing period if necessary.</p>|SNMP agent|system.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#CPU.UTIL}'].avg()`</p></li></ul>|
|Mellanox: Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Mellanox: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Mellanox: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|Mellanox: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Mellanox: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Mellanox: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Mellanox: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Mellanox: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Mellanox: SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|Mellanox: ICMP ping||Simple check|icmpping|
|Mellanox: ICMP loss||Simple check|icmppingloss|
|Mellanox: ICMP response time||Simple check|icmppingsec|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Mellanox: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Mellanox by SNMP/system.cpu.util,5m)>{$CPU.UTIL.CRIT}`|Warning||
|Mellanox: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Mellanox by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Mellanox by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Mellanox by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Mellanox by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Mellanox: No SNMP data collection</li></ul>|
|Mellanox: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Mellanox by SNMP/system.name,#1)<>last(/Mellanox by SNMP/system.name,#2) and length(last(/Mellanox by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Mellanox: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Mellanox by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Mellanox: Unavailable by ICMP ping</li></ul>|
|Mellanox: Unavailable by ICMP ping|<p>Last three attempts returned timeout.  Please check device connectivity.</p>|`max(/Mellanox by SNMP/icmpping,#3)=0`|High||
|Mellanox: High ICMP ping loss||`min(/Mellanox by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Mellanox by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Mellanox: Unavailable by ICMP ping</li></ul>|
|Mellanox: High ICMP ping response time||`avg(/Mellanox by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Mellanox: High ICMP ping loss</li><li>Mellanox: Unavailable by ICMP ping</li></ul>|

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery|<p>ENTITY-SENSORS-MIB::EntitySensorDataType discovery with temperature filter</p>|SNMP agent|temp.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_INFO}: Temperature|<p>MIB: ENTITY-SENSORS-MIB</p><p>The most recent measurement obtained by the agent for this sensor.</p><p>To correctly interpret the value of this object, the associated entPhySensorType,</p><p>entPhySensorScale, and entPhySensorPrecision objects must also be examined.</p>|SNMP agent|sensor.temp.value[entPhySensorValue.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li></ul>|
|{#SENSOR_INFO}: Temperature status|<p>MIB: ENTITY-SENSORS-MIB</p><p>The operational status of the sensor {#SENSOR_INFO}. Possible values:</p><p>- ok(1) indicates that the agent can obtain the sensor value.</p><p>- unavailable(2) indicates that the agent presently cannot obtain the sensor value.</p><p>- nonoperational(3) indicates that the agent believes the sensor is broken. The sensor could have a hard failure (disconnected wire), or a soft failure such as out-of-range, jittery, or wildly fluctuating readings.</p>|SNMP agent|sensor.temp.status[entPhySensorOperStatus.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SENSOR_INFO}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Mellanox by SNMP/sensor.temp.value[entPhySensorValue.{#SNMPINDEX}],5m)>{$TEMP.MAX.WARN:"{#SENSOR_INFO}"} or last(/Mellanox by SNMP/sensor.temp.status[entPhySensorOperStatus.{#SNMPINDEX}])={$TEMP.STATUS.WARN}`|Warning|**Depends on**:<br><ul><li>{#SENSOR_INFO}: Temperature is above critical threshold</li></ul>|
|{#SENSOR_INFO}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Mellanox by SNMP/sensor.temp.value[entPhySensorValue.{#SNMPINDEX}],5m)>{$TEMP.MAX.CRIT:"{#SENSOR_INFO}"}`|High||
|{#SENSOR_INFO}: Temperature is too low||`avg(/Mellanox by SNMP/sensor.temp.value[entPhySensorValue.{#SNMPINDEX}],5m)<{$TEMP.MIN.CRIT:"{#SENSOR_INFO}"}`|Average||

### LLD rule Fan Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan Discovery|<p>ENTITY-SENSORS-MIB::EntitySensorDataType discovery with rpm filter</p>|SNMP agent|fan.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Fan Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_INFO}: Fan speed|<p>MIB: ENTITY-SENSORS-MIB</p><p>The most recent measurement obtained by the agent for this sensor.</p><p>To correctly interpret the value of this object, the associated entPhySensorType,</p><p>entPhySensorScale, and entPhySensorPrecision objects must also be examined.</p>|SNMP agent|sensor.fan.speed[entPhySensorValue.{#SNMPINDEX}]|
|{#SENSOR_INFO}: Fan status|<p>MIB: ENTITY-SENSORS-MIB</p><p>The operational status of the sensor {#SENSOR_INFO}</p>|SNMP agent|sensor.fan.status[entPhySensorOperStatus.{#SNMPINDEX}]|

### Trigger prototypes for Fan Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SENSOR_INFO}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/Mellanox by SNMP/sensor.fan.status[entPhySensorOperStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1`|Average||

### LLD rule Entity Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Entity Discovery||SNMP agent|entity.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Entity Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ENT_NAME}: Hardware model name|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.model[entPhysicalModelName.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#ENT_NAME}: Hardware serial number|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Entity Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#ENT_NAME}: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Mellanox by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#1)<>last(/Mellanox by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#2) and length(last(/Mellanox by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery||SNMP agent|psu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ENT_NAME}: Power supply status|<p>MIB: ENTITY-STATE-MIB</p>|SNMP agent|sensor.psu.status[entStateOper.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#ENT_NAME}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/Mellanox by SNMP/sensor.psu.status[entStateOper.{#SNMPINDEX}],#1,"eq","{$PSU.STATUS.CRIT}")=1`|Average||

### LLD rule Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage discovery|<p>HOST-RESOURCES-MIB::hrStorage discovery with storage filter.</p>|SNMP agent|vfs.fs.discovery[snmp]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FSNAME}: Used space|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p>|SNMP agent|vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#FSNAME}: Total space|<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main storage allocated to a buffer pool might be modified or the amount of disk space allocated to virtual storage might be modified.</p>|SNMP agent|vfs.fs.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#FSNAME}: Space utilization|<p>The space utilization expressed in % for {#FSNAME}.</p>|Calculated|vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}]|

### Trigger prototypes for Storage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSNAME}: Disk space is critically low|<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.<br> Second condition should be one of the following:<br> - The disk free space is less than {$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}.<br> - The disk will be full in less than 24 hours.</p>|`last(/Mellanox by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/Mellanox by SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/Mellanox by SNMP/vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/Mellanox by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}],1h,100)<1d)`|Average|**Manual close**: Yes|
|{#FSNAME}: Disk space is low|<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}.<br> Second condition should be one of the following:<br> - The disk free space is less than {$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}.<br> - The disk will be full in less than 24 hours.</p>|`last(/Mellanox by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/Mellanox by SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/Mellanox by SNMP/vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/Mellanox by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}],1h,100)<1d)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#FSNAME}: Disk space is critically low</li></ul>|

### LLD rule Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory discovery|<p>HOST-RESOURCES-MIB::hrStorage discovery with memory filter</p>|SNMP agent|vm.memory.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#MEMNAME}: Used memory|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p>|SNMP agent|vm.memory.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#MEMNAME}: Total memory|<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main memory allocated to a buffer pool might be modified or the amount of disk space allocated to virtual memory might be modified.</p>|SNMP agent|vm.memory.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#MEMNAME}: Memory utilization|<p>Memory utilization in %.</p>|Calculated|vm.memory.util[memoryUsedPercentage.{#SNMPINDEX}]|

### Trigger prototypes for Memory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#MEMNAME}: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Mellanox by SNMP/vm.memory.util[memoryUsedPercentage.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}`|Average||

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

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
|Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Mellanox by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Mellanox by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Mellanox by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Mellanox by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Mellanox by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Mellanox by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Mellanox by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Mellanox by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Mellanox by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Mellanox by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Mellanox by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Mellanox by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Mellanox by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Mellanox by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Mellanox by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Mellanox by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Mellanox by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Mellanox by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Mellanox by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

