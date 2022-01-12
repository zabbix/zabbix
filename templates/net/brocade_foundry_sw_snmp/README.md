
# Brocade_Foundry Performance SNMP

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
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The statistics collection of 1 minute CPU utilization.</p> |SNMP |system.cpu.util[snAgGblCpuUtil1MinAvg.0] |
|Memory |Memory utilization |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The system dynamic memory utilization, in unit of percentage.</p><p>Deprecated: Refer to snAgSystemDRAMUtil.</p><p>For NI platforms, refer to snAgentBrdMemoryUtil100thPercent.</p> |SNMP |vm.memory.util[snAgGblDynMemUtil.0] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Brocade_Foundry Performance SNMP/system.cpu.util[snAgGblCpuUtil1MinAvg.0],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`min(/Brocade_Foundry Performance SNMP/vm.memory.util[snAgGblDynMemUtil.0],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Brocade_Foundry Nonstackable SNMP

## Overview

For Zabbix version: 6.0 and higher  
For devices(old Foundry devices, MLXe and so on) that doesn't support Stackable SNMP Tables: snChasFan2Table, snChasPwrSupply2Table,snAgentTemp2Table -
FOUNDRY-SN-AGENT-MIB::snChasFanTable, snChasPwrSupplyTable,snAgentTempTable are used instead.
For example:
The objects in table snChasPwrSupply2Table is not supported on the NetIron and the FastIron SX devices.
snChasFan2Table is not supported on  on the NetIron devices.
snAgentTemp2Table is not supported on old versions of MLXe.

This template was tested on:

- Brocade MLXe, version (System Mode: MLX), IronWare Version V5.4.0eT163 Compiled on Oct 30 2013 at 16:40:24 labeled as V5.4.00e
- Foundry FLS648, version Foundry Networks, Inc. FLS648, IronWare Version 04.1.00bT7e1 Compiled on Feb 29 2008 at 21:35:28 labeled as FGS04100b
- Foundry FWSX424, version Foundry Networks, Inc. FWSX424, IronWare Version 02.0.00aT1e0 Compiled on Dec 10 2004 at 14:40:19 labeled as FWXS02000a

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$FAN_CRIT_STATUS} |<p>-</p> |`3` |
|{$FAN_OK_STATUS} |<p>-</p> |`2` |
|{$PSU_CRIT_STATUS} |<p>-</p> |`3` |
|{$PSU_OK_STATUS} |<p>-</p> |`2` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`75` |
|{$TEMP_WARN} |<p>-</p> |`65` |

## Template links

|Name|
|----|
|Brocade_Foundry Performance SNMP |
|Generic SNMP |
|Interfaces SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|PSU Discovery |<p>snChasPwrSupplyTable: A table of each power supply information. Only installed power supply appears in a table row.</p> |SNMP |psu.discovery |
|FAN Discovery |<p>snChasFanTable: A table of each fan information. Only installed fan appears in a table row.</p> |SNMP |fan.discovery |
|Temperature Discovery |<p>snAgentTempTable:Table to list temperatures of the modules in the device. This table is applicable to only those modules with temperature sensors.</p> |SNMP |temp.discovery |
|Temperature Discovery Chassis |<p>Since temperature of the chassis is not available on all Brocade/Foundry hardware, this LLD is here to avoid unsupported items.</p> |SNMP |temp.chassis.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |Fan {#FAN_INDEX}: Fan status |<p>MIB: FOUNDRY-SN-AGENT-MIB</p> |SNMP |sensor.fan.status[snChasFanOperStatus.{#SNMPINDEX}] |
|Inventory |Hardware serial number |<p>MIB: FOUNDRY-SN-AGENT-MIB</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The version of the running software in the form'major.minor.maintenance[letters]'</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Power_supply |PSU {#PSU_INDEX}: Power supply status |<p>MIB: FOUNDRY-SN-AGENT-MIB</p> |SNMP |sensor.psu.status[snChasPwrSupplyOperStatus.{#SNMPINDEX}] |
|Temperature |{#SENSOR_DESCR}: Temperature |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>Temperature of the sensor represented by this row. Each unit is 0.5 degrees Celsius.</p> |SNMP |sensor.temp.value[snAgentTempValue.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.5`</p> |
|Temperature |Chassis #{#SNMPINDEX}: Temperature |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>Temperature of the chassis. Each unit is 0.5 degrees Celsius.</p><p>Only management module built with temperature sensor hardware is applicable.</p><p>For those non-applicable management module, it returns no-such-name.</p> |SNMP |sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.5`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Fan {#FAN_INDEX}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Brocade_Foundry Nonstackable SNMP/sensor.fan.status[snChasFanOperStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1` |AVERAGE | |
|Fan {#FAN_INDEX}: Fan is not in normal state |<p>Please check the fan unit</p> |`count(/Brocade_Foundry Nonstackable SNMP/sensor.fan.status[snChasFanOperStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1` |INFO |<p>**Depends on**:</p><p>- Fan {#FAN_INDEX}: Fan is in critical state</p> |
|Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/Brocade_Foundry Nonstackable SNMP/system.hw.serialnumber,#1)<>last(/Brocade_Foundry Nonstackable SNMP/system.hw.serialnumber,#2) and length(last(/Brocade_Foundry Nonstackable SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/Brocade_Foundry Nonstackable SNMP/system.hw.firmware,#1)<>last(/Brocade_Foundry Nonstackable SNMP/system.hw.firmware,#2) and length(last(/Brocade_Foundry Nonstackable SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|PSU {#PSU_INDEX}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Brocade_Foundry Nonstackable SNMP/sensor.psu.status[snChasPwrSupplyOperStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1` |AVERAGE | |
|PSU {#PSU_INDEX}: Power supply is not in normal state |<p>Please check the power supply unit for errors</p> |`count(/Brocade_Foundry Nonstackable SNMP/sensor.psu.status[snChasPwrSupplyOperStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1` |INFO |<p>**Depends on**:</p><p>- PSU {#PSU_INDEX}: Power supply is in critical state</p> |
|{#SENSOR_DESCR}: Temperature is above warning threshold: >{$TEMP_WARN:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:""}`<p>Recovery expression:</p>`max(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)<{$TEMP_WARN:""}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_DESCR}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p> |
|{#SENSOR_DESCR}: Temperature is above critical threshold: >{$TEMP_CRIT:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`max(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT:""}-3` |HIGH | |
|{#SENSOR_DESCR}: Temperature is too low: <{$TEMP_CRIT_LOW:""} |<p>-</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`min(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:""}+3` |AVERAGE | |
|Chassis #{#SNMPINDEX}: Temperature is above warning threshold: >{$TEMP_WARN:"Chassis"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Chassis"}`<p>Recovery expression:</p>`max(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Chassis"}-3` |WARNING |<p>**Depends on**:</p><p>- Chassis #{#SNMPINDEX}: Temperature is above critical threshold: >{$TEMP_CRIT:"Chassis"}</p> |
|Chassis #{#SNMPINDEX}: Temperature is above critical threshold: >{$TEMP_CRIT:"Chassis"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Chassis"}`<p>Recovery expression:</p>`max(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Chassis"}-3` |HIGH | |
|Chassis #{#SNMPINDEX}: Temperature is too low: <{$TEMP_CRIT_LOW:"Chassis"} |<p>-</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Chassis"}`<p>Recovery expression:</p>`min(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Chassis"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Brocade_Foundry Stackable SNMP

## Overview

For Zabbix version: 6.0 and higher  
For devices(most of the IronWare Brocade devices) that support Stackable SNMP Tables in FOUNDRY-SN-AGENT-MIB: snChasFan2Table, snChasPwrSupply2Table,snAgentTemp2Table - so objects from all Stack members are provided.

This template was tested on:

- Brocade ICX7250-48, version ICX7250-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k
- Brocade ICX7250-48(Stacked), version Stacking System ICX7250-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k
- Brocade ICX7450-48(Stacked), version Stacking System ICX7450-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k"
- Brocade ICX7250-48(Stacked), version Stacking System ICX7250-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k
- Brocade ICX7450-48F(Stacked), version Stacking System ICX7750-48F, IronWare Version 08.0.40bT203 Compiled on Oct 20 2016 at 23:48:43 labeled as SWR08040b
- Brocade ICX 6600, version 

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$FAN_CRIT_STATUS} |<p>-</p> |`3` |
|{$FAN_OK_STATUS} |<p>-</p> |`2` |
|{$PSU_CRIT_STATUS} |<p>-</p> |`3` |
|{$PSU_OK_STATUS} |<p>-</p> |`2` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`75` |
|{$TEMP_WARN} |<p>-</p> |`65` |

## Template links

|Name|
|----|
|Brocade_Foundry Performance SNMP |
|Generic SNMP |
|Interfaces SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|PSU Discovery |<p>snChasPwrSupply2Table: A table of each power supply information for each unit. Only installed power supply appears in a table row.</p> |SNMP |psu.discovery |
|FAN Discovery |<p>snChasFan2Table: A table of each fan information for each unit. Only installed fan appears in a table row.</p> |SNMP |fan.discovery |
|Temperature Discovery |<p>snAgentTemp2Table:Table to list temperatures of the modules in the device for each unit. This table is applicable to only those modules with temperature sensors.</p> |SNMP |temp.discovery |
|Stack Discovery |<p>Discovering snStackingConfigUnitTable for Model names</p> |SNMP |stack.discovery |
|Chassis Discovery |<p>snChasUnitIndex: The index to chassis table.</p> |SNMP |chassis.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan status |<p>MIB: FOUNDRY-SN-AGENT-MIB</p> |SNMP |sensor.fan.status[snChasFan2OperStatus.{#SNMPINDEX}] |
|Inventory |Firmware version |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The version of the running software in the form 'major.minor.maintenance[letters]'</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Unit {#SNMPINDEX}: Hardware model name |<p>MIB: FOUNDRY-SN-STACKING-MIB</p><p>A description of the configured/active system type for each unit.</p> |SNMP |system.hw.model[snStackingConfigUnitType.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Unit {#SNMPVALUE}: Hardware serial number |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The serial number of the chassis for each unit. If the serial number is unknown or unavailable then the value should be a zero length string.</p> |SNMP |system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Power_supply |Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply status |<p>MIB: FOUNDRY-SN-AGENT-MIB</p> |SNMP |sensor.psu.status[snChasPwrSupply2OperStatus.{#SNMPINDEX}] |
|Temperature |{#SENSOR_DESCR}: Temperature |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>Temperature of the sensor represented by this row. Each unit is 0.5 degrees Celsius.</p> |SNMP |sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.5`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Brocade_Foundry Stackable SNMP/sensor.fan.status[snChasFan2OperStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1` |AVERAGE | |
|Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan is not in normal state |<p>Please check the fan unit</p> |`count(/Brocade_Foundry Stackable SNMP/sensor.fan.status[snChasFan2OperStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1` |INFO |<p>**Depends on**:</p><p>- Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan is in critical state</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/Brocade_Foundry Stackable SNMP/system.hw.firmware,#1)<>last(/Brocade_Foundry Stackable SNMP/system.hw.firmware,#2) and length(last(/Brocade_Foundry Stackable SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|Unit {#SNMPVALUE}: Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/Brocade_Foundry Stackable SNMP/system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}],#1)<>last(/Brocade_Foundry Stackable SNMP/system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}],#2) and length(last(/Brocade_Foundry Stackable SNMP/system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Brocade_Foundry Stackable SNMP/sensor.psu.status[snChasPwrSupply2OperStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1` |AVERAGE | |
|Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply is not in normal state |<p>Please check the power supply unit for errors</p> |`count(/Brocade_Foundry Stackable SNMP/sensor.psu.status[snChasPwrSupply2OperStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1` |INFO |<p>**Depends on**:</p><p>- Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply is in critical state</p> |
|{#SENSOR_DESCR}: Temperature is above warning threshold: >{$TEMP_WARN:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)>{$TEMP_WARN:""}`<p>Recovery expression:</p>`max(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)<{$TEMP_WARN:""}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_DESCR}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p> |
|{#SENSOR_DESCR}: Temperature is above critical threshold: >{$TEMP_CRIT:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`max(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)<{$TEMP_CRIT:""}-3` |HIGH | |
|{#SENSOR_DESCR}: Temperature is too low: <{$TEMP_CRIT_LOW:""} |<p>-</p> |`avg(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`min(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:""}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

## Known Issues

- Description: Correct fan(returns fan status as 'other(1)' and temperature (returns 0) for the non-master Switches are not available in SNMP
  - Version: Version 08.0.40b and above
  - Device: ICX 7750 in stack

