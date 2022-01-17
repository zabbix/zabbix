
# D-Link DES_DGS Switch SNMP

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$FAN_CRIT_STATUS} |<p>-</p> |`2` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$PSU_CRIT_STATUS} |<p>-</p> |`4` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`75` |
|{$TEMP_WARN} |<p>-</p> |`65` |

## Template links

|Name|
|----|
|EtherLike-MIB SNMP |
|Generic SNMP |
|Interfaces SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Memory Discovery |<p>-</p> |SNMP |memory.discovery |
|Temperature Discovery |<p>-</p> |SNMP |temperature.discovery |
|PSU Discovery |<p>swPowerID of EQUIPMENT-MIB::swPowerTable</p> |SNMP |psu.discovery<p>**Filter**:</p>AND_OR <p>- {#STATUS} MATCHES_REGEX `[^0]`</p> |
|FAN Discovery |<p>swFanID of EQUIPMENT-MIB::swFanTable</p> |SNMP |fan.discovery<p>**Filter**:</p>AND_OR <p>- {#STATUS} MATCHES_REGEX `[^0]`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: DLINK-AGENT-MIB</p><p>The unit of time is 1 minute. The value will be between 0% (idle) and 100%(very busy).</p> |SNMP |system.cpu.util[agentCPUutilizationIn1min.0] |
|Fans |#{#SNMPVALUE}: Fan status |<p>MIB: EQUIPMENT-MIB</p><p>Indicates the current fan status.</p><p>speed-0     : If the fan function is normal and the fan does not spin            due to the temperature not  reaching the threshold, the status of the fan is speed 0.</p><p>speed-low   : Fan spin using the lowest speed.</p><p>speed-middle: Fan spin using the middle speed.</p><p>speed-high  : Fan spin using the highest speed.</p> |SNMP |sensor.fan.status[swFanStatus.{#SNMPINDEX}] |
|Inventory |Hardware model name |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity.  This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: DLINK-AGENT-MIB</p><p>A text string containing the serial number of this device.</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware version(revision) |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.version<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |#{#SNMPVALUE}: Memory utilization |<p>MIB: DLINK-AGENT-MIB</p><p>The percentage of used DRAM memory of the total DRAM memory available.The value will be between 0%(idle) and 100%(very busy)</p> |SNMP |vm.memory.util[agentDRAMutilization.{#SNMPINDEX}] |
|Power_supply |#{#SNMPVALUE}: Power supply status |<p>MIB: EQUIPMENT-MIB</p><p>Indicates the current power status.</p><p>lowVoltage : The voltage of the power unit is too low.</p><p>overCurrent: The current of the power unit is too high.</p><p>working    : The power unit is working normally.</p><p>fail       : The power unit has failed.</p><p>connect    : The power unit is connected but not powered on.</p><p>disconnect : The power unit is not connected.</p> |SNMP |sensor.psu.status[swPowerStatus.{#SNMPINDEX}] |
|Temperature |#{#SNMPVALUE}: Temperature |<p>MIB: EQUIPMENT-MIB</p><p>The shelf current temperature.</p> |SNMP |sensor.temp.value[swTemperatureCurrent.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/D-Link DES_DGS Switch SNMP/system.cpu.util[agentCPUutilizationIn1min.0],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|#{#SNMPVALUE}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/D-Link DES_DGS Switch SNMP/sensor.fan.status[swFanStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1` |AVERAGE | |
|Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/D-Link DES_DGS Switch SNMP/system.hw.serialnumber,#1)<>last(/D-Link DES_DGS Switch SNMP/system.hw.serialnumber,#2) and length(last(/D-Link DES_DGS Switch SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/D-Link DES_DGS Switch SNMP/system.hw.firmware,#1)<>last(/D-Link DES_DGS Switch SNMP/system.hw.firmware,#2) and length(last(/D-Link DES_DGS Switch SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|#{#SNMPVALUE}: High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`min(/D-Link DES_DGS Switch SNMP/vm.memory.util[agentDRAMutilization.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|#{#SNMPVALUE}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/D-Link DES_DGS Switch SNMP/sensor.psu.status[swPowerStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1` |AVERAGE | |
|#{#SNMPVALUE}: Temperature is above warning threshold: >{$TEMP_WARN:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/D-Link DES_DGS Switch SNMP/sensor.temp.value[swTemperatureCurrent.{#SNMPINDEX}],5m)>{$TEMP_WARN:""}`<p>Recovery expression:</p>`max(/D-Link DES_DGS Switch SNMP/sensor.temp.value[swTemperatureCurrent.{#SNMPINDEX}],5m)<{$TEMP_WARN:""}-3` |WARNING |<p>**Depends on**:</p><p>- #{#SNMPVALUE}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p> |
|#{#SNMPVALUE}: Temperature is above critical threshold: >{$TEMP_CRIT:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/D-Link DES_DGS Switch SNMP/sensor.temp.value[swTemperatureCurrent.{#SNMPINDEX}],5m)>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`max(/D-Link DES_DGS Switch SNMP/sensor.temp.value[swTemperatureCurrent.{#SNMPINDEX}],5m)<{$TEMP_CRIT:""}-3` |HIGH | |
|#{#SNMPVALUE}: Temperature is too low: <{$TEMP_CRIT_LOW:""} |<p>-</p> |`avg(/D-Link DES_DGS Switch SNMP/sensor.temp.value[swTemperatureCurrent.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`min(/D-Link DES_DGS Switch SNMP/sensor.temp.value[swTemperatureCurrent.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:""}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

## Known Issues

- Description: D-Link reports missing PSU as fail(4)
  - Version: Firmware: 1.73R008,hardware revision: B1
  - Device: DGS-3420-26SC Gigabit Ethernet Switch

