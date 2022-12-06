
# Dell iDRAC by SNMP

## Overview

For Zabbix version: 6.2 and higher.
for Dell servers with iDRAC controllers
http://www.dell.com/support/manuals/us/en/19/dell-openmanage-server-administrator-v8.3/snmp_idrac8/idrac-mib?guid=guid-e686536d-bc8e-4e09-8e8b-de8eb052efee
Supported systems: http://www.dell.com/support/manuals/us/en/04/dell-openmanage-server-administrator-v8.3/snmp_idrac8/supported-systems?guid=guid-f72b75ba-e686-4e8a-b8c5-ca11c7c21381

This template was tested on:

- iDRAC7, PowerEdge R620
- iDRAC8, PowerEdge R730xd
- iDRAC8, PowerEdge R720

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS} |<p>-</p> |`3` |
|{$DISK_ARRAY_CACHE_BATTERY_OK_STATUS} |<p>-</p> |`2` |
|{$DISK_ARRAY_CACHE_BATTERY_WARN_STATUS} |<p>-</p> |`4` |
|{$DISK_ARRAY_CRIT_STATUS:"critical"} |<p>-</p> |`5` |
|{$DISK_ARRAY_FAIL_STATUS:"nonRecoverable"} |<p>-</p> |`6` |
|{$DISK_ARRAY_WARN_STATUS:"nonCritical"} |<p>-</p> |`4` |
|{$DISK_FAIL_STATUS:"critical"} |<p>-</p> |`5` |
|{$DISK_FAIL_STATUS:"nonRecoverable"} |<p>-</p> |`6` |
|{$DISK_SMART_FAIL_STATUS} |<p>-</p> |`1` |
|{$DISK_WARN_STATUS:"nonCritical"} |<p>-</p> |`4` |
|{$FAN_CRIT_STATUS:"criticalLower"} |<p>-</p> |`8` |
|{$FAN_CRIT_STATUS:"criticalUpper"} |<p>-</p> |`5` |
|{$FAN_CRIT_STATUS:"failed"} |<p>-</p> |`10` |
|{$FAN_CRIT_STATUS:"nonRecoverableLower"} |<p>-</p> |`9` |
|{$FAN_CRIT_STATUS:"nonRecoverableUpper"} |<p>-</p> |`6` |
|{$FAN_WARN_STATUS:"nonCriticalLower"} |<p>-</p> |`7` |
|{$FAN_WARN_STATUS:"nonCriticalUpper"} |<p>-</p> |`4` |
|{$HEALTH_CRIT_STATUS} |<p>-</p> |`5` |
|{$HEALTH_DISASTER_STATUS} |<p>-</p> |`6` |
|{$HEALTH_WARN_STATUS} |<p>-</p> |`4` |
|{$ICMP_LOSS_WARN} |<p>-</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>-</p> |`0.15` |
|{$PSU_CRIT_STATUS:"critical"} |<p>-</p> |`5` |
|{$PSU_CRIT_STATUS:"nonRecoverable"} |<p>-</p> |`6` |
|{$PSU_WARN_STATUS:"nonCritical"} |<p>-</p> |`4` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT:"Ambient"} |<p>-</p> |`35` |
|{$TEMP_CRIT:"CPU"} |<p>-</p> |`75` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT_STATUS} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_DISASTER_STATUS} |<p>-</p> |`6` |
|{$TEMP_WARN:"Ambient"} |<p>-</p> |`30` |
|{$TEMP_WARN:"CPU"} |<p>-</p> |`70` |
|{$TEMP_WARN_STATUS} |<p>-</p> |`4` |
|{$TEMP_WARN} |<p>-</p> |`50` |
|{$VDISK_CRIT_STATUS:"failed"} |<p>-</p> |`3` |
|{$VDISK_WARN_STATUS:"degraded"} |<p>-</p> |`4` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Array Controller Cache Discovery |<p>IDRAC-MIB-SMIv2::batteryTable</p> |SNMP |array.cache.discovery |
|Array Controller Discovery |<p>IDRAC-MIB-SMIv2::controllerTable</p> |SNMP |physicaldisk.arr.discovery |
|FAN Discovery |<p>IDRAC-MIB-SMIv2::coolingDeviceTable</p> |SNMP |fan.discovery<p>**Filter**:</p>AND_OR <p>- {#TYPE} MATCHES_REGEX `3`</p> |
|Physical Disk Discovery |<p>IDRAC-MIB-SMIv2::physicalDiskTable</p> |SNMP |physicaldisk.discovery |
|PSU Discovery |<p>IDRAC-MIB-SMIv2::powerSupplyTable</p> |SNMP |psu.discovery |
|Temperature Ambient Discovery |<p>Scanning table of Temperature Probe Table IDRAC-MIB-SMIv2::temperatureProbeTable</p> |SNMP |temp.ambient.discovery<p>**Filter**:</p>AND_OR <p>- {#SENSOR_LOCALE} MATCHES_REGEX `.*Inlet Temp.*`</p> |
|Temperature CPU Discovery |<p>Scanning table of Temperature Probe Table IDRAC-MIB-SMIv2::temperatureProbeTable</p> |SNMP |temp.cpu.discovery<p>**Filter**:</p>AND_OR <p>- {#SENSOR_LOCALE} MATCHES_REGEX `.*CPU.*`</p> |
|Virtual Disk Discovery |<p>IDRAC-MIB-SMIv2::virtualDiskTable</p> |SNMP |virtualdisk.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Disk arrays |{#CNTLR_NAME}: Disk array controller status |<p>MIB: IDRAC-MIB-SMIv2</p><p>The status of the controller itself without the propagation of any contained component status.</p><p>Possible values:</p><p>1: Other</p><p>2: Unknown</p><p>3: OK</p><p>4: Non-critical</p><p>5: Critical</p><p>6: Non-recoverable</p><p>                </p> |SNMP |system.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}] |
|Disk arrays |{#CNTLR_NAME}: Disk array controller model |<p>MIB: IDRAC-MIB-SMIv2</p><p>The controller's name as represented in Storage Management.</p> |SNMP |system.hw.diskarray.model[controllerName.{#SNMPINDEX}] |
|Disk arrays |Battery {#BATTERY_NUM}: Disk array cache controller battery status |<p>MIB: IDRAC-MIB-SMIv2</p><p>Current state of battery.</p><p>Possible values:</p><p>1: The current state could not be determined.</p><p>2: The battery is operating normally.</p><p>3: The battery has failed and needs to be replaced.</p><p>4: The battery temperature is high or charge level is depleting.</p><p>5: The battery is missing or not detected.</p><p>6: The battery is undergoing the re-charge phase.</p><p>7: The battery voltage or charge level is below the threshold.</p><p>                </p> |SNMP |system.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}] |
|Fans |{#FAN_DESCR}: Fan status |<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0012.0001.0005 This attribute defines the probe status of the cooling device.</p> |SNMP |sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}] |
|Fans |{#FAN_DESCR}: Fan speed |<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0012.0001.0006 This attribute defines the reading for a cooling device</p><p>of subtype other than coolingDeviceSubTypeIsDiscrete.  When the value</p><p>for coolingDeviceSubType is other than coolingDeviceSubTypeIsDiscrete, the</p><p>value returned for this attribute is the speed in RPM or the OFF/ON value</p><p>of the cooling device.  When the value for coolingDeviceSubType is</p><p>coolingDeviceSubTypeIsDiscrete, a value is not returned for this attribute.</p> |SNMP |sensor.fan.speed[coolingDeviceReading.{#SNMPINDEX}] |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Inventory |Hardware model name |<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the model name of the system.</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system |<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the name of the operating system that the hostis running.</p> |SNMP |system.sw.os[systemOSName]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the service tag of the system.</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the firmware version of a remote access card.</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Physical disks |{#DISK_NAME}: Physical disk status |<p>MIB: IDRAC-MIB-SMIv2</p><p>The status of the physical disk itself without the propagation of any contained component status.</p><p>Possible values:</p><p>1: Other</p><p>2: Unknown</p><p>3: OK</p><p>4: Non-critical</p><p>5: Critical</p><p>6: Non-recoverable</p> |SNMP |system.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}] |
|Physical disks |{#DISK_NAME}: Physical disk serial number |<p>MIB: IDRAC-MIB-SMIv2</p><p>The physical disk's unique identification number from the manufacturer.</p> |SNMP |system.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}] |
|Physical disks |{#DISK_NAME}: Physical disk S.M.A.R.T. status |<p>MIB: IDRAC-MIB-SMIv2</p><p>Indicates whether the physical disk has received a predictive failure alert.</p> |SNMP |system.hw.physicaldisk.smart_status[physicalDiskSmartAlertIndication.{#SNMPINDEX}] |
|Physical disks |{#DISK_NAME}: Physical disk model name |<p>MIB: IDRAC-MIB-SMIv2</p><p>The model number of the physical disk.</p> |SNMP |system.hw.physicaldisk.model[physicalDiskProductID.{#SNMPINDEX}] |
|Physical disks |{#DISK_NAME}: Physical disk part number |<p>MIB: IDRAC-MIB-SMIv2</p><p>The part number of the disk.</p> |SNMP |system.hw.physicaldisk.part_number[physicalDiskPartNumber.{#SNMPINDEX}] |
|Physical disks |{#DISK_NAME}: Physical disk media type |<p>MIB: IDRAC-MIB-SMIv2</p><p>The media type of the physical disk. Possible Values:</p><p>1: The media type could not be determined.</p><p>2: Hard Disk Drive (HDD).</p><p>3: Solid State Drive (SSD).</p> |SNMP |system.hw.physicaldisk.media_type[physicalDiskMediaType.{#SNMPINDEX}] |
|Physical disks |{#DISK_NAME}: Disk size |<p>MIB: IDRAC-MIB-SMIv2</p><p>The size of the physical disk in megabytes.</p> |SNMP |system.hw.physicaldisk.size[physicalDiskCapacityInMB.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Power supply |{#PSU_DESCR}: Power supply status |<p>MIB: IDRAC-MIB-SMIv2</p><p>0600.0012.0001.0005 This attribute defines the status of the power supply.</p> |SNMP |sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}] |
|Status |Overall system health status |<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the overall rollup status of all components in the system being monitored by the remote access card. Includes system, storage, IO devices, iDRAC, CPU, memory, etc.</p> |SNMP |system.status[globalSystemStatus.0] |
|Status |Uptime (network) |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.net.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p> |SNMP |system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |
|Temperature |{#SENSOR_LOCALE}: Temperature |<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0020.0001.0006 This attribute defines the reading for a temperature probe of type other than temperatureProbeTypeIsDiscrete.  When the value for temperatureProbeType is other than temperatureProbeTypeIsDiscrete,the value returned for this attribute is the temperature that the probeis reading in tenths of degrees Centigrade. When the value for temperatureProbeType is temperatureProbeTypeIsDiscrete, a value is not returned for this attribute.</p> |SNMP |sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Temperature |{#SENSOR_LOCALE}: Temperature status |<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0020.0001.0005 This attribute defines the probe status of the temperature probe.</p> |SNMP |sensor.temp.status[temperatureProbeStatus.CPU.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCALE}: Temperature |<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0020.0001.0006 This attribute defines the reading for a temperature probe of type other than temperatureProbeTypeIsDiscrete.  When the value for temperatureProbeType is other than temperatureProbeTypeIsDiscrete,the value returned for this attribute is the temperature that the probeis reading in tenths of degrees Centigrade. When the value for temperatureProbeType is temperatureProbeTypeIsDiscrete, a value is not returned for this attribute.</p> |SNMP |sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Temperature |{#SENSOR_LOCALE}: Temperature status |<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0020.0001.0005 This attribute defines the probe status of the temperature probe.</p> |SNMP |sensor.temp.status[temperatureProbeStatus.Ambient.{#SNMPINDEX}] |
|Virtual disks |Disk {#SNMPVALUE}({#DISK_NAME}): Layout type  |<p>MIB: IDRAC-MIB-SMIv2</p><p>The virtual disk's RAID type.</p><p>Possible values:</p><p>1: Not one of the following</p><p>2: RAID-0</p><p>3: RAID-1</p><p>4: RAID-5</p><p>5: RAID-6</p><p>6: RAID-10</p><p>7: RAID-50</p><p>8: RAID-60</p><p>9: Concatenated RAID 1</p><p>10: Concatenated RAID 5</p> |SNMP |system.hw.virtualdisk.layout[virtualDiskLayout.{#SNMPINDEX}] |
|Virtual disks |Disk {#SNMPVALUE}({#DISK_NAME}): Current state |<p>MIB: IDRAC-MIB-SMIv2</p><p>The state of the virtual disk when there are progressive operations ongoing.</p><p>Possible values:</p><p>1: There is no active operation running.</p><p>2: The virtual disk configuration has changed. The physical disks included in the virtual disk are being modified to support the new configuration.</p><p>3: A Consistency Check (CC) is being performed on the virtual disk.</p><p>4: The virtual disk is being initialized.</p><p>5: BackGround Initialization (BGI) is being performed on the virtual disk.</p> |SNMP |system.hw.virtualdisk.state[virtualDiskOperationalState.{#SNMPINDEX}] |
|Virtual disks |Disk {#SNMPVALUE}({#DISK_NAME}): Read policy |<p>MIB: IDRAC-MIB-SMIv2</p><p>The read policy used by the controller for read operations on this virtual disk.</p><p>Possible values:</p><p>1: No Read Ahead.</p><p>2: Read Ahead.</p><p>3: Adaptive Read Ahead.</p> |SNMP |system.hw.virtualdisk.readpolicy[virtualDiskReadPolicy.{#SNMPINDEX}] |
|Virtual disks |Disk {#SNMPVALUE}({#DISK_NAME}): Write policy |<p>MIB: IDRAC-MIB-SMIv2</p><p>The write policy used by the controller for write operations on this virtual disk.</p><p>Possible values:</p><p>1: Write Through.</p><p>2: Write Back.</p><p>3: Force Write Back.</p> |SNMP |system.hw.virtualdisk.writepolicy[virtualDiskWritePolicy.{#SNMPINDEX}] |
|Virtual disks |Disk {#SNMPVALUE}({#DISK_NAME}): Disk size |<p>MIB: IDRAC-MIB-SMIv2</p><p>The size of the virtual disk in megabytes.</p> |SNMP |system.hw.virtualdisk.size[virtualDiskSizeInMB.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Virtual disks |Disk {#SNMPVALUE}({#DISK_NAME}): Status |<p>MIB: IDRAC-MIB-SMIv2</p><p>The current state of this virtual disk (which includes any member physical disks.)</p><p>Possible states:</p><p>1: The current state could not be determined.</p><p>2: The virtual disk is operating normally or optimally.</p><p>3: The virtual disk has encountered a failure. The data on disk is lost or is about to be lost.</p><p>4: The virtual disk encountered a failure with one or all of the constituent redundant physical disks.</p><p>The data on the virtual disk might no longer be fault tolerant.</p> |SNMP |system.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#CNTLR_NAME}: Disk array controller is in unrecoverable state! |<p>Please check the device for faults</p> |`count(/Dell iDRAC by SNMP/system.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_FAIL_STATUS:\"nonRecoverable\"}")=1` |DISASTER | |
|{#CNTLR_NAME}: Disk array controller is in critical state |<p>Please check the device for faults</p> |`count(/Dell iDRAC by SNMP/system.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CRIT_STATUS:\"critical\"}")=1` |HIGH |<p>**Depends on**:</p><p>- {#CNTLR_NAME}: Disk array controller is in unrecoverable state!</p> |
|{#CNTLR_NAME}: Disk array controller is in warning state |<p>Please check the device for faults</p> |`count(/Dell iDRAC by SNMP/system.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_WARN_STATUS:\"nonCritical\"}")=1` |AVERAGE |<p>**Depends on**:</p><p>- {#CNTLR_NAME}: Disk array controller is in critical state</p><p>- {#CNTLR_NAME}: Disk array controller is in unrecoverable state!</p> |
|Battery {#BATTERY_NUM}: Disk array cache controller battery is in warning state |<p>Please check the device for faults</p> |`count(/Dell iDRAC by SNMP/system.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_BATTERY_WARN_STATUS}")=1` |WARNING |<p>**Depends on**:</p><p>- Battery {#BATTERY_NUM}: Disk array cache controller battery is in critical state!</p> |
|Battery {#BATTERY_NUM}: Disk array cache controller battery is not in optimal state |<p>Please check the device for faults</p> |`count(/Dell iDRAC by SNMP/system.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}],#1,"ne","{$DISK_ARRAY_CACHE_BATTERY_OK_STATUS}")=1` |WARNING |<p>**Depends on**:</p><p>- Battery {#BATTERY_NUM}: Disk array cache controller battery is in critical state!</p><p>- Battery {#BATTERY_NUM}: Disk array cache controller battery is in warning state</p> |
|Battery {#BATTERY_NUM}: Disk array cache controller battery is in critical state! |<p>Please check the device for faults</p> |`count(/Dell iDRAC by SNMP/system.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS}")=1` |AVERAGE | |
|{#FAN_DESCR}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"criticalUpper\"}")=1 or count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"nonRecoverableUpper\"}")=1 or count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"criticalLower\"}")=1 or count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"nonRecoverableLower\"}")=1 or count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"failed\"}")=1` |AVERAGE | |
|{#FAN_DESCR}: Fan is in warning state |<p>Please check the fan unit</p> |`count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"nonCriticalUpper\"}")=1 or count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"nonCriticalLower\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#FAN_DESCR}: Fan is in critical state</p> |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Dell iDRAC by SNMP/system.name,#1)<>last(/Dell iDRAC by SNMP/system.name,#2) and length(last(/Dell iDRAC by SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`last(/Dell iDRAC by SNMP/system.sw.os[systemOSName],#1)<>last(/Dell iDRAC by SNMP/system.sw.os[systemOSName],#2) and length(last(/Dell iDRAC by SNMP/system.sw.os[systemOSName]))>0` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- System name has changed</p> |
|Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Dell iDRAC by SNMP/system.hw.serialnumber,#1)<>last(/Dell iDRAC by SNMP/system.hw.serialnumber,#2) and length(last(/Dell iDRAC by SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/Dell iDRAC by SNMP/system.hw.firmware,#1)<>last(/Dell iDRAC by SNMP/system.hw.firmware,#2) and length(last(/Dell iDRAC by SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|{#DISK_NAME}: Physical disk failed |<p>Please check physical disk for warnings or errors</p> |`count(/Dell iDRAC by SNMP/system.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_FAIL_STATUS:\"critical\"}")=1 or count(/Dell iDRAC by SNMP/system.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_FAIL_STATUS:\"nonRecoverable\"}")=1` |HIGH | |
|{#DISK_NAME}: Physical disk is in warning state |<p>Please check physical disk for warnings or errors</p> |`count(/Dell iDRAC by SNMP/system.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_WARN_STATUS:\"nonCritical\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#DISK_NAME}: Physical disk failed</p> |
|{#DISK_NAME}: Disk has been replaced |<p>Disk serial number has changed. Ack to close</p> |`last(/Dell iDRAC by SNMP/system.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}],#1)<>last(/Dell iDRAC by SNMP/system.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}],#2) and length(last(/Dell iDRAC by SNMP/system.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|{#DISK_NAME}: Physical disk S.M.A.R.T. failed |<p>Disk probably requires replacement.</p> |`count(/Dell iDRAC by SNMP/system.hw.physicaldisk.smart_status[physicalDiskSmartAlertIndication.{#SNMPINDEX}],#1,"eq","{$DISK_SMART_FAIL_STATUS}")=1` |HIGH |<p>**Depends on**:</p><p>- {#DISK_NAME}: Physical disk failed</p> |
|{#PSU_DESCR}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Dell iDRAC by SNMP/sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"critical\"}")=1 or count(/Dell iDRAC by SNMP/sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"nonRecoverable\"}")=1` |AVERAGE | |
|{#PSU_DESCR}: Power supply is in warning state |<p>Please check the power supply unit for errors</p> |`count(/Dell iDRAC by SNMP/sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"nonCritical\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#PSU_DESCR}: Power supply is in critical state</p> |
|System is in unrecoverable state! |<p>Please check the device for faults</p> |`count(/Dell iDRAC by SNMP/system.status[globalSystemStatus.0],#1,"eq","{$HEALTH_DISASTER_STATUS}")=1` |HIGH | |
|System status is in critical state |<p>Please check the device for errors</p> |`count(/Dell iDRAC by SNMP/system.status[globalSystemStatus.0],#1,"eq","{$HEALTH_CRIT_STATUS}")=1` |HIGH |<p>**Depends on**:</p><p>- System is in unrecoverable state!</p> |
|System status is in warning state |<p>Please check the device for warnings</p> |`count(/Dell iDRAC by SNMP/system.status[globalSystemStatus.0],#1,"eq","{$HEALTH_WARN_STATUS}")=1` |WARNING |<p>**Depends on**:</p><p>- System is in unrecoverable state!</p><p>- System status is in critical state</p> |
|Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/Dell iDRAC by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Dell iDRAC by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Dell iDRAC by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Dell iDRAC by SNMP/system.net.uptime[sysUpTime.0])<10m)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/Dell iDRAC by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/Dell iDRAC by SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/Dell iDRAC by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Dell iDRAC by SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/Dell iDRAC by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{#SENSOR_LOCALE}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_WARN:"CPU"} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.CPU.{#SNMPINDEX}])={$TEMP_WARN_STATUS} `<p>Recovery expression:</p>`max(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_WARN:"CPU"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCALE}: Temperature is above critical threshold</p> |
|{#SENSOR_LOCALE}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"CPU"} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.CPU.{#SNMPINDEX}])={$TEMP_CRIT_STATUS} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.CPU.{#SNMPINDEX}])={$TEMP_DISASTER_STATUS} `<p>Recovery expression:</p>`max(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"CPU"}-3` |HIGH | |
|{#SENSOR_LOCALE}: Temperature is too low |<p>-</p> |`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"CPU"}`<p>Recovery expression:</p>`min(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"CPU"}+3` |AVERAGE | |
|{#SENSOR_LOCALE}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.Ambient.{#SNMPINDEX}])={$TEMP_WARN_STATUS} `<p>Recovery expression:</p>`max(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCALE}: Temperature is above critical threshold</p> |
|{#SENSOR_LOCALE}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.Ambient.{#SNMPINDEX}])={$TEMP_CRIT_STATUS} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.Ambient.{#SNMPINDEX}])={$TEMP_DISASTER_STATUS} `<p>Recovery expression:</p>`max(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|{#SENSOR_LOCALE}: Temperature is too low |<p>-</p> |`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|Disk {#SNMPVALUE}({#DISK_NAME}): Virtual disk failed |<p>Please check virtual disk for warnings or errors</p> |`count(/Dell iDRAC by SNMP/system.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}],#1,"eq","{$VDISK_CRIT_STATUS:\"failed\"}")=1` |HIGH | |
|Disk {#SNMPVALUE}({#DISK_NAME}): Virtual disk is in warning state |<p>Please check virtual disk for warnings or errors</p> |`count(/Dell iDRAC by SNMP/system.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}],#1,"eq","{$VDISK_WARN_STATUS:\"degraded\"}")=1` |AVERAGE |<p>**Depends on**:</p><p>- Disk {#SNMPVALUE}({#DISK_NAME}): Virtual disk failed</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

