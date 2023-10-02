
# Morningstar TriStar MPPT 600V by SNMP

## Overview

This template is designed for the effortless deployment of Morningstar TriStar MPPT 600V monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Morningstar TriStar MPPT 600V

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$BATTERY.TEMP.MIN.WARN}|<p>Battery low temperature warning value</p>|`0`|
|{$BATTERY.TEMP.MAX.WARN}|<p>Battery high temperature warning value</p>|`45`|
|{$BATTERY.TEMP.MIN.CRIT}|<p>Battery low temperature critical value</p>|`-20`|
|{$BATTERY.TEMP.MAX.CRIT}|<p>Battery high temperature critical value</p>|`60`|
|{$VOLTAGE.MIN.WARN}|||
|{$VOLTAGE.MAX.WARN}|||
|{$VOLTAGE.MIN.CRIT}|||
|{$VOLTAGE.MAX.CRIT}|||
|{$CHARGE.STATE.WARN}|<p>disconnect</p>|`2`|
|{$CHARGE.STATE.CRIT}|<p>fault</p>|`4`|
|{$LOAD.STATE.WARN:"lvdWarning"}|<p>lvdWarning</p>|`2`|
|{$LOAD.STATE.WARN:"disconnect"}|<p>disconnect</p>|`5`|
|{$LOAD.STATE.WARN:"override"}|<p>override</p>|`7`|
|{$LOAD.STATE.CRIT:"lvd"}|<p>lvd</p>|`3`|
|{$LOAD.STATE.CRIT:"fault"}|<p>fault</p>|`4`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Status: Uptime (network)|<p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|status.net.uptime<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Status: Uptime (hardware)|<p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|status.hw.uptime<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Array: Voltage|<p>MIB: TRISTAR-MPPT</p><p>Description:Array Voltage</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 650]</p><p>Modbus address:0x001b</p>|SNMP agent|array.voltage[arrayVoltage.0]|
|Array: Array Current|<p>MIB: TRISTAR-MPPT</p><p>Description:Array Current</p><p>Scaling Factor:1.0</p><p>Units:A</p><p>Range:[-10, 80]</p><p>Modbus address:0x001d</p>|SNMP agent|array.current[arrayCurrent.0]|
|Array: Sweep Vmp|<p>MIB: TRISTAR-MPPT</p><p>Description:Vmp (last sweep)</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 650.0]</p><p>Modbus address:0x003d</p>|SNMP agent|array.sweep_vmp[arrayVmpLastSweep.0]|
|Array: Sweep Voc|<p>MIB: TRISTAR-MPPT</p><p>Description:Voc (last sweep)</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 650.0]</p><p>Modbus address:0x003e</p>|SNMP agent|array.sweep_voc[arrayVocLastSweep.0]|
|Array: Sweep Pmax|<p>MIB: TRISTAR-MPPT</p><p>Description:Pmax (last sweep)</p><p>Scaling Factor:1.0</p><p>Units:W</p><p>Range:[-10, 5000]</p><p>Modbus address:0x003c</p>|SNMP agent|array.sweep_pmax[arrayPmaxLastSweep.0]|
|Battery: Charge State|<p>MIB: TRISTAR-MPPT</p><p>Description:Charge State</p><p>Modbus address:0x0032</p><p></p><p>0: Start</p><p>1: NightCheck</p><p>2: Disconnect</p><p>3: Night</p><p>4: Fault</p><p>5: Mppt</p><p>6: Absorption</p><p>7: Float</p><p>8: Equalize</p><p>9: Slave</p><p>10: Fixed</p>|SNMP agent|charge.state[chargeState.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Battery: Battery Voltage discovery|<p>MIB: TRISTAR-MPPT</p><p>Description:Battery voltage</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 80]</p><p>Modbus address:0x0018</p>|SNMP agent|battery.voltage.discovery[batteryVoltage.0]|
|Battery: Target Voltage|<p>MIB: TRISTAR-MPPT</p><p>Description:Target Voltage</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 650.0]</p><p>Modbus address:0x0033</p>|SNMP agent|target.voltage[targetRegulationVoltage.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Battery: Charge Current|<p>MIB: TRISTAR-MPPT</p><p>Description:Battery Current</p><p>Scaling Factor:1.0</p><p>Units:A</p><p>Range:[-10, 80]</p><p>Modbus address:0x001c</p>|SNMP agent|charge.current[batteryCurrent.0]|
|Battery: Output Power|<p>MIB: TRISTAR-MPPT</p><p>Description:Output Power</p><p>Scaling Factor:1.0</p><p>Units:W</p><p>Range:[-10, 4000]</p><p>Modbus address:0x003a</p>|SNMP agent|charge.output_power[ outputPower.0]|
|Temperature: Battery|<p>MIB: TRISTAR-MPPT</p><p>Description:Batt. Temp</p><p>Scaling Factor:1.0</p><p>Units:C</p><p>Range:[-40, 80]</p><p>Modbus address:0x0025</p>|SNMP agent|temp.battery[batteryTemperature.0]|
|Temperature: Heatsink|<p>MIB: TRISTAR-MPPT</p><p>Description:HS Temp</p><p>Scaling Factor:1.0</p><p>Units:C</p><p>Range:[-40, 80]</p><p>Modbus address:0x0023</p>|SNMP agent|temp.heatsink[heatsinkTemperature.0]|
|Counter: Charge Amp-hours|<p>MIB: TRISTAR-MPPT</p><p>Description:Ah Charge Resettable</p><p>Scaling Factor:1.0</p><p>Units:Ah</p><p>Range:[0.0, 5000]</p><p>Modbus addresses:H=0x0034 L=0x0035</p>|SNMP agent|counter.charge_amp_hours[ahChargeResetable.0]|
|Counter: Charge KW-hours|<p>MIB: TRISTAR-MPPT</p><p>Description:kWh Charge Resettable</p><p>Scaling Factor:1.0</p><p>Units:kWh</p><p>Range:[0.0, 65535.0]</p><p>Modbus address:0x0038</p>|SNMP agent|counter.charge_kw_hours[kwhChargeResetable.0]|
|Status: Faults|<p>MIB: TRISTAR-MPPT</p><p>Description:Faults</p><p>Modbus addresses:H=0x002c L=0x002d</p>|SNMP agent|status.faults[faults.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Status: Alarms|<p>MIB: TRISTAR-MPPT</p><p>Description:Alarms</p><p>Modbus addresses:H=0x002e L=0x002f</p>|SNMP agent|status.alarms[alarms.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Status: Device has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Morningstar TriStar MPPT 600V by SNMP/status.hw.uptime)>0 and last(/Morningstar TriStar MPPT 600V by SNMP/status.hw.uptime)<10m) or (last(/Morningstar TriStar MPPT 600V by SNMP/status.hw.uptime)=0 and last(/Morningstar TriStar MPPT 600V by SNMP/status.net.uptime)<10m)`|Info|**Manual close**: Yes|
|Status: Failed to fetch data|<p>Zabbix has not received data for items for the last 5 minutes.</p>|`nodata(/Morningstar TriStar MPPT 600V by SNMP/status.net.uptime,5m)=1`|Warning|**Manual close**: Yes|
|Battery: Device charge in warning state||`last(/Morningstar TriStar MPPT 600V by SNMP/charge.state[chargeState.0])={$CHARGE.STATE.WARN}`|Warning|**Depends on**:<br><ul><li>Battery: Device charge in critical state</li></ul>|
|Battery: Device charge in critical state||`last(/Morningstar TriStar MPPT 600V by SNMP/charge.state[chargeState.0])={$CHARGE.STATE.CRIT}`|High||
|Temperature: Low battery temperature||`max(/Morningstar TriStar MPPT 600V by SNMP/temp.battery[batteryTemperature.0],5m)<{$BATTERY.TEMP.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>Temperature: Critically low battery temperature</li></ul>|
|Temperature: Critically low battery temperature||`max(/Morningstar TriStar MPPT 600V by SNMP/temp.battery[batteryTemperature.0],5m)<{$BATTERY.TEMP.MIN.CRIT}`|High||
|Temperature: High battery temperature||`min(/Morningstar TriStar MPPT 600V by SNMP/temp.battery[batteryTemperature.0],5m)>{$BATTERY.TEMP.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Temperature: Critically high battery temperature</li></ul>|
|Temperature: Critically high battery temperature||`min(/Morningstar TriStar MPPT 600V by SNMP/temp.battery[batteryTemperature.0],5m)>{$BATTERY.TEMP.MAX.CRIT}`|High||
|Status: Device has "overcurrent" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","overcurrent")=2`|High||
|Status: Device has "fetShort" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","fetShort")=2`|High||
|Status: Device has "softwareFault" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","softwareFault")=2`|High||
|Status: Device has "batteryHvd" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","batteryHvd")=2`|High||
|Status: Device has "arrayHvd" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","arrayHvd")=2`|High||
|Status: Device has "dipSwitchChange" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","dipSwitchChange")=2`|High||
|Status: Device has "customSettingsEdit" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","customSettingsEdit")=2`|High||
|Status: Device has "rtsShorted" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","rtsShorted")=2`|High||
|Status: Device has "rtsDisconnected" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","rtsDisconnected")=2`|High||
|Status: Device has "eepromRetryLimit" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","eepromRetryLimit")=2`|High||
|Status: Device has "controllerWasReset" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","controllerWasReset")=2`|High||
|Status: Device has "chargeSlaveControlTimeout" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","chargeSlaveControlTimeout")=2`|High||
|Status: Device has "rs232SerialToMeterBridge" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","rs232SerialToMeterBridge")=2`|High||
|Status: Device has "batteryLvd" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","batteryLvd")=2`|High||
|Status: Device has "powerboardCommunicationFault" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","powerboardCommunicationFault")=2`|High||
|Status: Device has "fault16Software" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","fault16Software")=2`|High||
|Status: Device has "fault17Software" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","fault17Software")=2`|High||
|Status: Device has "fault18Software" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","fault18Software")=2`|High||
|Status: Device has "fault19Software" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","fault19Software")=2`|High||
|Status: Device has "fault20Software" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","fault20Software")=2`|High||
|Status: Device has "fault21Software" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","fault21Software")=2`|High||
|Status: Device has "fpgaVersion" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","fpgaVersion")=2`|High||
|Status: Device has "currentSensorReferenceOutOfRange" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","currentSensorReferenceOutOfRange")=2`|High||
|Status: Device has "ia-refSlaveModeTimeout" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","ia-refSlaveModeTimeout")=2`|High||
|Status: Device has "blockbusBoot" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","blockbusBoot")=2`|High||
|Status: Device has "hscommMaster" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","hscommMaster")=2`|High||
|Status: Device has "hscomm" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","hscomm")=2`|High||
|Status: Device has "slave" faults flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.faults[faults.0],#3,"like","slave")=2`|High||
|Status: Device has "rtsShorted" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","rtsShorted")=2`|Warning||
|Status: Device has "rtsDisconnected" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","rtsDisconnected")=2`|Warning||
|Status: Device has "heatsinkTempSensorOpen" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","heatsinkTempSensorOpen")=2`|Warning||
|Status: Device has "heatsinkTempSensorShorted" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","heatsinkTempSensorShorted")=2`|Warning||
|Status: Device has "highTemperatureCurrentLimit" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","highTemperatureCurrentLimit")=2`|Warning||
|Status: Device has "currentLimit" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","currentLimit")=2`|Warning||
|Status: Device has "currentOffset" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","currentOffset")=2`|Warning||
|Status: Device has "batterySense" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","batterySense")=2`|Warning||
|Status: Device has "batterySenseDisconnected" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","batterySenseDisconnected")=2`|Warning||
|Status: Device has "uncalibrated" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","uncalibrated")=2`|Warning||
|Status: Device has "rtsMiswire" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","rtsMiswire")=2`|Warning||
|Status: Device has "highVoltageDisconnect" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","highVoltageDisconnect")=2`|Warning||
|Status: Device has "systemMiswire" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","systemMiswire")=2`|Warning||
|Status: Device has "mosfetSOpen" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","mosfetSOpen")=2`|Warning||
|Status: Device has "p12VoltageOutOfRange" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","p12VoltageOutOfRange")=2`|Warning||
|Status: Device has "highArrayVCurrentLimit" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","highArrayVCurrentLimit")=2`|Warning||
|Status: Device has "maxAdcValueReached" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","maxAdcValueReached")=2`|Warning||
|Status: Device has "controllerWasReset" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","controllerWasReset")=2`|Warning||
|Status: Device has "alarm21Internal" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","alarm21Internal")=2`|Warning||
|Status: Device has "p3VoltageOutOfRange" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","p3VoltageOutOfRange")=2`|Warning||
|Status: Device has "derateLimit" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","derateLimit")=2`|Warning||
|Status: Device has "arrayCurrentOffset" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","arrayCurrentOffset")=2`|Warning||
|Status: Device has "ee-i2cRetryLimit" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","ee-i2cRetryLimit")=2`|Warning||
|Status: Device has "ethernetAlarm" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","ethernetAlarm")=2`|Warning||
|Status: Device has "lvd" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","lvd")=2`|Warning||
|Status: Device has "software" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","software")=2`|Warning||
|Status: Device has "fp12VoltageOutOfRange" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","fp12VoltageOutOfRange")=2`|Warning||
|Status: Device has "extflashFault" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","extflashFault")=2`|Warning||
|Status: Device has "slaveControlFault" alarm flag||`count(/Morningstar TriStar MPPT 600V by SNMP/status.alarms[alarms.0],#3,"like","slaveControlFault")=2`|Warning||

### LLD rule Battery voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery voltage discovery|<p>Discovery for battery voltage triggers</p>|Dependent item|battery.voltage.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Battery voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery: Voltage{#SINGLETON}|<p>MIB: TRISTAR-MPPT</p><p>Description:Battery voltage</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 80]</p><p>Modbus address:0x0018</p>|SNMP agent|battery.voltage[batteryVoltage.0{#SINGLETON}]|

### Trigger prototypes for Battery voltage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Battery: Low battery voltage||`max(/Morningstar TriStar MPPT 600V by SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)<{#VOLTAGE.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>Battery: Critically low battery voltage</li></ul>|
|Battery: Critically low battery voltage||`max(/Morningstar TriStar MPPT 600V by SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)<{#VOLTAGE.MIN.CRIT}`|High||
|Battery: High battery voltage||`min(/Morningstar TriStar MPPT 600V by SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)>{#VOLTAGE.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Battery: Critically high battery voltage</li></ul>|
|Battery: Critically high battery voltage||`min(/Morningstar TriStar MPPT 600V by SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)>{#VOLTAGE.MAX.CRIT}`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

