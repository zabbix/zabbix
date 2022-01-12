
# MikroTik CRS112-8P-4S-IN SNMP

## Overview

For Zabbix version: 6.0 and higher  
The template for monitoring Switch MikroTik CRS112-8P-4S-IN.

8x Gigabit Ethernet Smart Switch with PoE-out, 4x SFP cages, 400MHz CPU, 128MB RAM, desktop case, RouterOS L5

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/network_devices) for basic instructions.

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$IFNAME.LTEMODEM.MATCHES} |<p>This macro is used in LTE modem discovery. It can be overridden on the host.</p> |`^lte` |
|{$IFNAME.WIFI.MATCHES} |<p>This macro is used in CAPsMAN AP channel discovery. It can be overridden on the host level.</p> |`WIFI` |
|{$LTEMODEM.RSRP.MIN.WARN} |<p>The LTE modem RSRP minimum value for warning trigger expression.</p> |`-100` |
|{$LTEMODEM.RSRQ.MIN.WARN} |<p>The LTE modem RSRQ minimum value for warning trigger expression.</p> |`-20` |
|{$LTEMODEM.RSSI.MIN.WARN} |<p>The LTE modem RSSI minimum value for warning trigger expression.</p> |`-100` |
|{$LTEMODEM.SINR.MIN.WARN} |<p>The LTE modem SINR minimum value for warning trigger expression.</p> |`0` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$TEMP_CRIT:"CPU"} |<p>-</p> |`75` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_WARN:"CPU"} |<p>-</p> |`70` |
|{$TEMP_WARN} |<p>-</p> |`50` |
|{$VFS.FS.PUSED.MAX.CRIT} |<p>-</p> |`90` |
|{$VFS.FS.PUSED.MAX.WARN} |<p>-</p> |`80` |

## Template links

|Name|
|----|
|Generic SNMP |
|Interfaces SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|CPU discovery |<p>HOST-RESOURCES-MIB::hrProcessorTable discovery</p> |SNMP |hrProcessorLoad.discovery |
|Temperature CPU discovery |<p>MIKROTIK-MIB::mtxrHlProcessorTemperature</p><p>Since temperature of CPU is not available on all Mikrotik hardware, this is done to avoid unsupported items.</p> |SNMP |mtxrHlProcessorTemperature.discovery |
|Temperature sensor discovery |<p>MIKROTIK-MIB::mtxrHlTemperature</p><p>Since temperature sensor is not available on all Mikrotik hardware,</p><p>this is done to avoid unsupported items.</p> |SNMP |mtxrHlTemperature.discovery |
|LTE modem discovery |<p>MIKROTIK-MIB::mtxrLTEModemInterfaceIndex</p> |SNMP |mtxrLTEModem.discovery<p>**Filter**:</p>AND <p>- {#IFTYPE} MATCHES_REGEX `^1$`</p><p>- {#IFNAME} MATCHES_REGEX `{$IFNAME.LTEMODEM.MATCHES}`</p> |
|AP channel discovery |<p>MIKROTIK-MIB::mtxrWlAp</p> |SNMP |mtxrWlAp.discovery<p>**Filter**:</p>AND <p>- {#IFTYPE} MATCHES_REGEX `^71$`</p><p>- {#IFADMINSTATUS} MATCHES_REGEX `^1$`</p> |
|CAPsMAN AP channel discovery |<p>MIKROTIK-MIB::mtxrWlCMChannel</p> |SNMP |mtxrWlCMChannel.discovery<p>**Filter**:</p>AND <p>- {#IFTYPE} MATCHES_REGEX `^1$`</p><p>- {#IFNAME} MATCHES_REGEX `{$IFNAME.WIFI.MATCHES}`</p> |
|Storage discovery |<p>HOST-RESOURCES-MIB::hrStorage discovery with storage filter</p> |SNMP |storage.discovery<p>**Filter**:</p>OR <p>- {#STORAGE_TYPE} MATCHES_REGEX `.+4$`</p><p>- {#STORAGE_TYPE} MATCHES_REGEX `.+hrStorageFixedDisk`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |#{#SNMPINDEX}: CPU utilization |<p>MIB: HOST-RESOURCES-MIB</p><p>The average, over the last minute, of the percentage of time that this processor was not idle. Implementations may approximate this one minute smoothing period if necessary.</p> |SNMP |system.cpu.util[hrProcessorLoad.{#SNMPINDEX}] |
|Inventory |Operating system |<p>MIB: MIKROTIK-MIB</p><p>Software version.</p> |SNMP |system.sw.os[mtxrLicVersion.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware model name |<p>-</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: MIKROTIK-MIB</p><p>RouterBOARD serial number.</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: MIKROTIK-MIB</p><p>Current firmware version.</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |Used memory |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p> |SNMP |vm.memory.used[hrStorageUsed.Memory]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Total memory |<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in</p><p>units of hrStorageAllocationUnits. This object is</p><p>writable to allow remote configuration of the size of</p><p>the storage area in those cases where such an</p><p>operation makes sense and is possible on the</p><p>underlying system. For example, the amount of main</p><p>memory allocated to a buffer pool might be modified or</p><p>the amount of disk space allocated to virtual memory</p><p>might be modified.</p> |SNMP |vm.memory.total[hrStorageSize.Memory]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Memory utilization |<p>Memory utilization in %</p> |CALCULATED |vm.memory.util[memoryUsedPercentage.Memory]<p>**Expression**:</p>`last(//vm.memory.used[hrStorageUsed.Memory])/last(//vm.memory.total[hrStorageSize.Memory])*100` |
|Storage |Disk-{#SNMPINDEX}: Used space |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p> |SNMP |vfs.fs.used[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Storage |Disk-{#SNMPINDEX}: Total space |<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in</p><p>units of hrStorageAllocationUnits. This object is</p><p>writable to allow remote configuration of the size of</p><p>the storage area in those cases where such an</p><p>operation makes sense and is possible on the</p><p>underlying system. For example, the amount of main</p><p>memory allocated to a buffer pool might be modified or</p><p>the amount of disk space allocated to virtual memory</p><p>might be modified.</p> |SNMP |vfs.fs.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Storage |Disk-{#SNMPINDEX}: Space utilization |<p>Space utilization in % for Disk-{#SNMPINDEX}</p> |CALCULATED |vfs.fs.pused[hrStorageSize.{#SNMPINDEX}]<p>**Expression**:</p>`(last(//vfs.fs.used[hrStorageSize.{#SNMPINDEX}])/last(//vfs.fs.total[hrStorageSize.{#SNMPINDEX}]))*100` |
|Temperature |CPU: Temperature |<p>MIB: MIKROTIK-MIB</p><p>mtxrHlProcessorTemperature Processor temperature in Celsius (degrees C).</p><p>Might be missing in entry models (RB750, RB450G..).</p> |SNMP |sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Temperature |Device: Temperature |<p>MIB: MIKROTIK-MIB</p><p>mtxrHlTemperature Device temperature in Celsius (degrees C).</p><p>Might be missing in entry models (RB750, RB450G..).</p><p>Reference: http://wiki.mikrotik.com/wiki/Manual:SNMP</p> |SNMP |sensor.temp.value[mtxrHlTemperature.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Wireless |Interface {#IFNAME}({#IFALIAS}): LTE modem RSSI |<p>MIB: MIKROTIK-MIB</p><p>mtxrLTEModemSignalRSSI Received Signal Strength Indicator.</p> |SNMP |lte.modem.rssi[mtxrLTEModemSignalRSSI.{#SNMPINDEX}] |
|Wireless |Interface {#IFNAME}({#IFALIAS}): LTE modem RSRP |<p>MIB: MIKROTIK-MIB</p><p>mtxrLTEModemSignalRSRP Reference Signal Received Power.</p> |SNMP |lte.modem.rsrp[mtxrLTEModemSignalRSRP.{#SNMPINDEX}] |
|Wireless |Interface {#IFNAME}({#IFALIAS}): LTE modem RSRQ |<p>MIB: MIKROTIK-MIB</p><p>mtxrLTEModemSignalRSRQ Reference Signal Received Quality.</p> |SNMP |lte.modem.rsrq[mtxrLTEModemSignalRSRQ.{#SNMPINDEX}] |
|Wireless |Interface {#IFNAME}({#IFALIAS}): LTE modem SINR |<p>MIB: MIKROTIK-MIB</p><p>mtxrLTEModemSignalSINR Signal to Interference & Noise Ratio.</p> |SNMP |lte.modem.sinr[mtxrLTEModemSignalSINR.{#SNMPINDEX}] |
|Wireless |Interface {#IFNAME}({#IFALIAS}): SSID |<p>MIB: MIKROTIK-MIB</p><p>mtxrWlApSsid Service Set Identifier.</p> |SNMP |ssid.name[mtxrWlApSsid.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Wireless |Interface {#IFNAME}({#IFALIAS}): AP band |<p>MIB: MIKROTIK-MIB</p><p>mtxrWlApBand</p> |SNMP |ssid.band[mtxrWlApBand.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Wireless |Interface {#IFNAME}({#IFALIAS}): AP noise floor |<p>MIB: MIKROTIK-MIB</p><p>mtxrWlApNoiseFloor</p> |SNMP |ssid.noise[mtxrWlApNoiseFloor.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
|Wireless |Interface {#IFNAME}({#IFALIAS}): AP registered clients |<p>MIB: MIKROTIK-MIB</p><p>mtxrWlApClientCount Client established connection to AP, but didn't finish all authentication procedures for full connection.</p> |SNMP |ssid.regclient[mtxrWlApClientCount.{#SNMPINDEX}] |
|Wireless |Interface {#IFNAME}({#IFALIAS}): AP authenticated clients |<p>MIB: MIKROTIK-MIB</p><p>mtxrWlApAuthClientCount Number of authentication clients.</p> |SNMP |ssid.authclient[mtxrWlApAuthClientCount.{#SNMPINDEX}] |
|Wireless |Interface {#IFNAME}({#IFALIAS}): AP channel |<p>MIB: MIKROTIK-MIB</p><p>mtxrWlCMChannel</p> |SNMP |ssid.channel[mtxrWlCMChannel.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Wireless |Interface {#IFNAME}({#IFALIAS}): AP state |<p>MIB: MIKROTIK-MIB</p><p>mtxrWlCMState Wireless interface state.</p> |SNMP |ssid.state[mtxrWlCMState.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Wireless |Interface {#IFNAME}({#IFALIAS}): AP registered clients |<p>MIB: MIKROTIK-MIB</p><p>mtxrWlCMRegClientCount Client established connection to AP, but didn't finish all authentication procedures for full connection.</p> |SNMP |ssid.regclient[mtxrWlCMRegClientCount.{#SNMPINDEX}] |
|Wireless |Interface {#IFNAME}({#IFALIAS}): AP authenticated clients |<p>MIB: MIKROTIK-MIB</p><p>mtxrWlCMAuthClientCount Number of authentication clients.</p> |SNMP |ssid.authclient[mtxrWlCMAuthClientCount.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|#{#SNMPINDEX}: High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/MikroTik CRS112-8P-4S-IN SNMP/system.cpu.util[hrProcessorLoad.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`last(/MikroTik CRS112-8P-4S-IN SNMP/system.sw.os[mtxrLicVersion.0],#1)<>last(/MikroTik CRS112-8P-4S-IN SNMP/system.sw.os[mtxrLicVersion.0],#2) and length(last(/MikroTik CRS112-8P-4S-IN SNMP/system.sw.os[mtxrLicVersion.0]))>0` |INFO |<p>Manual close: YES</p> |
|Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/MikroTik CRS112-8P-4S-IN SNMP/system.hw.serialnumber,#1)<>last(/MikroTik CRS112-8P-4S-IN SNMP/system.hw.serialnumber,#2) and length(last(/MikroTik CRS112-8P-4S-IN SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/MikroTik CRS112-8P-4S-IN SNMP/system.hw.firmware,#1)<>last(/MikroTik CRS112-8P-4S-IN SNMP/system.hw.firmware,#2) and length(last(/MikroTik CRS112-8P-4S-IN SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`min(/MikroTik CRS112-8P-4S-IN SNMP/vm.memory.util[memoryUsedPercentage.Memory],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|Disk-{#SNMPINDEX}: Disk space is critically low (used > {$VFS.FS.PUSED.MAX.CRIT:"Disk-{#SNMPINDEX}"}%) |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.CRIT:"Disk-{#SNMPINDEX}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than 5G.</p><p> - The disk will be full in less than 24 hours.</p> |`last(/MikroTik CRS112-8P-4S-IN SNMP/vfs.fs.pused[hrStorageSize.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.CRIT:"Disk-{#SNMPINDEX}"} and ((last(/MikroTik CRS112-8P-4S-IN SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/MikroTik CRS112-8P-4S-IN SNMP/vfs.fs.used[hrStorageSize.{#SNMPINDEX}]))<5G or timeleft(/MikroTik CRS112-8P-4S-IN SNMP/vfs.fs.pused[hrStorageSize.{#SNMPINDEX}],1h,100)<1d) ` |AVERAGE |<p>Manual close: YES</p> |
|Disk-{#SNMPINDEX}: Disk space is low (used > {$VFS.FS.PUSED.MAX.WARN:"Disk-{#SNMPINDEX}"}%) |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.WARN:"Disk-{#SNMPINDEX}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than 10G.</p><p> - The disk will be full in less than 24 hours.</p> |`last(/MikroTik CRS112-8P-4S-IN SNMP/vfs.fs.pused[hrStorageSize.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.WARN:"Disk-{#SNMPINDEX}"} and ((last(/MikroTik CRS112-8P-4S-IN SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/MikroTik CRS112-8P-4S-IN SNMP/vfs.fs.used[hrStorageSize.{#SNMPINDEX}]))<10G or timeleft(/MikroTik CRS112-8P-4S-IN SNMP/vfs.fs.pused[hrStorageSize.{#SNMPINDEX}],1h,100)<1d) ` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Disk-{#SNMPINDEX}: Disk space is critically low (used > {$VFS.FS.PUSED.MAX.CRIT:"Disk-{#SNMPINDEX}"}%)</p> |
|CPU: Temperature is above warning threshold: >{$TEMP_WARN:"CPU"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"CPU"}`<p>Recovery expression:</p>`max(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}],5m)<{$TEMP_WARN:"CPU"}-3` |WARNING |<p>**Depends on**:</p><p>- CPU: Temperature is above critical threshold: >{$TEMP_CRIT:"CPU"}</p> |
|CPU: Temperature is above critical threshold: >{$TEMP_CRIT:"CPU"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"CPU"}`<p>Recovery expression:</p>`max(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"CPU"}-3` |HIGH | |
|CPU: Temperature is too low: <{$TEMP_CRIT_LOW:"CPU"} |<p>-</p> |`avg(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"CPU"}`<p>Recovery expression:</p>`min(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"CPU"}+3` |AVERAGE | |
|Device: Temperature is above warning threshold: >{$TEMP_WARN:"Device"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Device"}`<p>Recovery expression:</p>`max(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlTemperature.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Device"}-3` |WARNING |<p>**Depends on**:</p><p>- Device: Temperature is above critical threshold: >{$TEMP_CRIT:"Device"}</p> |
|Device: Temperature is above critical threshold: >{$TEMP_CRIT:"Device"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Device"}`<p>Recovery expression:</p>`max(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Device"}-3` |HIGH | |
|Device: Temperature is too low: <{$TEMP_CRIT_LOW:"Device"} |<p>-</p> |`avg(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Device"}`<p>Recovery expression:</p>`min(/MikroTik CRS112-8P-4S-IN SNMP/sensor.temp.value[mtxrHlTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Device"}+3` |AVERAGE | |
|Interface {#IFNAME}({#IFALIAS}): LTE modem RSSI is low (below {$LTEMODEM.RSSI.MIN.WARN}dbm for 5m) |<p>-</p> |`max(/MikroTik CRS112-8P-4S-IN SNMP/lte.modem.rssi[mtxrLTEModemSignalRSSI.{#SNMPINDEX}],5m) < {$LTEMODEM.RSSI.MIN.WARN}` |WARNING | |
|Interface {#IFNAME}({#IFALIAS}): LTE modem RSRP is low (below {$LTEMODEM.RSRP.MIN.WARN}dbm for 5m) |<p>-</p> |`max(/MikroTik CRS112-8P-4S-IN SNMP/lte.modem.rsrp[mtxrLTEModemSignalRSRP.{#SNMPINDEX}],5m) < {$LTEMODEM.RSRP.MIN.WARN}` |WARNING | |
|Interface {#IFNAME}({#IFALIAS}): LTE modem RSRQ is low (below {$LTEMODEM.RSRQ.MIN.WARN}db for 5m) |<p>-</p> |`max(/MikroTik CRS112-8P-4S-IN SNMP/lte.modem.rsrq[mtxrLTEModemSignalRSRQ.{#SNMPINDEX}],5m) < {$LTEMODEM.RSRQ.MIN.WARN}` |WARNING | |
|Interface {#IFNAME}({#IFALIAS}): LTE modem SINR is low (below {$LTEMODEM.SINR.MIN.WARN}db for 5m) |<p>-</p> |`max(/MikroTik CRS112-8P-4S-IN SNMP/lte.modem.sinr[mtxrLTEModemSignalSINR.{#SNMPINDEX}],5m) < {$LTEMODEM.SINR.MIN.WARN}` |WARNING | |
|Interface {#IFNAME}({#IFALIAS}): AP interface {#IFNAME}({#IFALIAS}) is not running |<p>Access point interface can be not running by different reasons - disabled interface, power off, network link down.</p> |`last(/MikroTik CRS112-8P-4S-IN SNMP/ssid.state[mtxrWlCMState.{#SNMPINDEX}])<>"running-ap"` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com


## References

https://mikrotik.com/product/crs112_8p_4s_in
