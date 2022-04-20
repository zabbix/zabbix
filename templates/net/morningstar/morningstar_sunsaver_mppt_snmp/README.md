
# Morningstar SunSaver MPPT SNMP

## Overview

For Zabbix version: 6.0 and higher  

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$BATTERY.TEMP.MAX.CRIT} |<p>Battery high temperature critical value</p> |`60` |
|{$BATTERY.TEMP.MAX.WARN} |<p>Battery high temperature warning value</p> |`45` |
|{$BATTERY.TEMP.MIN.CRIT} |<p>Battery low temperature critical value</p> |`-20` |
|{$BATTERY.TEMP.MIN.WARN} |<p>Battery low temperature warning value</p> |`0` |
|{$CHARGE.STATE.CRIT} |<p>fault</p> |`4` |
|{$CHARGE.STATE.WARN} |<p>disconnect</p> |`2` |
|{$LOAD.STATE.CRIT:"fault"} |<p>fault</p> |`4` |
|{$LOAD.STATE.CRIT:"lvd"} |<p>lvd</p> |`3` |
|{$LOAD.STATE.WARN:"disconnect"} |<p>disconnect</p> |`5` |
|{$LOAD.STATE.WARN:"lvdWarning"} |<p>lvdWarning</p> |`2` |
|{$LOAD.STATE.WARN:"override"} |<p>override</p> |`7` |
|{$VOLTAGE.MAX.CRIT} |<p>-</p> |`` |
|{$VOLTAGE.MAX.WARN} |<p>-</p> |`` |
|{$VOLTAGE.MIN.CRIT} |<p>-</p> |`` |
|{$VOLTAGE.MIN.WARN} |<p>-</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Battery voltage discovery |<p>Discovery for battery voltage triggers</p> |DEPENDENT |battery.voltage.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Array |Array: Voltage |<p>MIB: SUNSAVER-MPPT</p><p>Description:Array Voltage</p><p>Scaling Factor:0.0030517578125</p><p>Units:V</p><p>Range:[0, 80]</p><p>Modbus address:0x0009</p> |SNMP |array.voltage[arrayVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.003051757813`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Array |Array: Sweep Vmp |<p>MIB: SUNSAVER-MPPT</p><p>Description:Array Max. Power Point Voltage</p><p>Scaling Factor:0.0030517578125</p><p>Units:V</p><p>Range:[0.0, 5000.0]</p><p>Modbus address:0x0028</p> |SNMP |array.sweep_vmp[arrayVmp.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.003051757813`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Array |Array: Sweep Voc |<p>MIB: SUNSAVER-MPPT</p><p>Description:Array Open Circuit Voltage</p><p>Scaling Factor:0.0030517578125</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x002A</p> |SNMP |array.sweep_voc[arrayVoc.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.003051757813`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Array |Array: Sweep Pmax |<p>MIB: SUNSAVER-MPPT</p><p>Description:Array Open Circuit Voltage</p><p>Scaling Factor:0.0030517578125</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x002A</p> |SNMP |array.sweep_pmax[arrayMaxPowerSweep.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01509857178`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Battery |Battery: Charge State |<p>MIB: SUNSAVER-MPPT</p><p>Description:Control State</p><p>Modbus address:0x0011</p><p>0: Start</p><p>1: NightCheck</p><p>2: Disconnect</p><p>3: Night</p><p>4: Fault</p><p>5: BulkMppt</p><p>6: Pwm</p><p>7: Float</p><p>8: Equalize</p> |SNMP |charge.state[chargeState.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Battery |Battery: Target Voltage |<p>MIB: SUNSAVER-MPPT</p><p>Description:Target Regulation Voltage</p><p>Scaling Factor:0.0030517578125</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0014</p> |SNMP |target.voltage[targetVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.003051757813`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Battery |Battery: Charge Current |<p>MIB: SUNSAVER-MPPT</p><p>Description:Target Regulation Voltage</p><p>Scaling Factor:0.0030517578125</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0014</p> |SNMP |charge.current[chargeCurrent.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.002415771484`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Battery |Battery: Voltage{#SINGLETON} |<p>MIB: SUNSAVER-MPPT</p><p>Description:Control State</p><p>Modbus address:0x0011</p> |SNMP |battery.voltage[batteryVoltage.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.003051757813`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Counter |Counter: Charge Amp-hours |<p>MIB: SUNSAVER-MPPT</p><p>Description:Ah Charge(Resettable)</p><p>Scaling Factor:0.1</p><p>Units:Ah</p><p>Range:[0.0, 4294967294]</p><p>Modbus addresses:H=0x0015 L=0x0016</p> |SNMP |counter.charge_amp_hours[ahChargeResettable.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Counter |Counter: Charge KW-hours |<p>MIB: SUNSAVER-MPPT</p> |SNMP |counter.charge_kw_hours[kwhCharge.0] |
|Counter |Counter: Load Amp-hours |<p>MIB: SUNSAVER-MPPT</p><p>Description:Ah Load(Resettable)</p><p>Scaling Factor:0.1</p><p>Units:Ah</p><p>Range:[0.0, 4294967294]</p><p>Modbus addresses:H=0x001D L=0x001E</p> |SNMP |counter.load_amp_hours[ahLoadResettable.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Load |Load: State |<p>MIB: SUNSAVER-MPPT</p><p>Description:Load State</p><p>Modbus address:0x001A</p><p>0: Start</p><p>1: Normal</p><p>2: LvdWarning</p><p>3: Lvd</p><p>4: Fault</p><p>5: Disconnect</p><p>6: NormalOff</p><p>7: Override</p><p>8: NotUsed</p> |SNMP |load.state[loadState.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Load |Load: Voltage |<p>MIB: SUNSAVER-MPPT</p><p>Description:Load Voltage</p><p>Scaling Factor:0.0030517578125</p><p>Units:V</p><p>Range:[0, 80]</p><p>Modbus address:0x000A</p> |SNMP |load.voltage[loadVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.003051757813`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Load |Load: Current |<p>MIB: SUNSAVER-MPPT</p><p>Description:Load Current</p><p>Scaling Factor:0.002415771484375</p><p>Units:A</p><p>Range:[0, 60]</p><p>Modbus address:0x000C</p> |SNMP |load.current[loadCurrent.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.002415771484`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Status |Status: Uptime |<p>Device uptime in seconds</p> |SNMP |status.uptime<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Status: Array Faults |<p>MIB: SUNSAVER-MPPT</p><p>Description:Array Faults</p><p>Modbus address:0x0012</p> |SNMP |status.array_faults[arrayFaults.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Status |Status: Load Faults |<p>MIB: SUNSAVER-MPPT</p><p>Description:Array Faults</p><p>Modbus address:0x0012</p> |SNMP |status.load_faults[loadFaults.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Status |Status: Alarms |<p>MIB: SUNSAVER-MPPT</p><p>Description:Alarms</p><p>Modbus addresses:H=0x0023 L=0x0024</p> |SNMP |status.alarms[alarms.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Temperature |Temperature: Ambient |<p>MIB: SUNSAVER-MPPT</p><p>Description:Ambient Temperature</p><p>Scaling Factor:1.0</p><p>Units:deg C</p><p>Range:[-128, 127]</p><p>Modbus address:0x000F</p> |SNMP |temp.ambient[ambientTemperature.0] |
|Temperature |Temperature: Battery |<p>MIB: SUNSAVER-MPPT</p><p>Description:Heatsink Temperature</p><p>Scaling Factor:1.0</p><p>Units:deg C</p><p>Range:[-128, 127]</p><p>Modbus address:0x000D</p> |SNMP |temp.battery[batteryTemperature.0] |
|Temperature |Temperature: Heatsink |<p>MIB: SUNSAVER-MPPT</p><p>Description:Battery Temperature</p><p>Scaling Factor:1.0</p><p>Units:deg C</p><p>Range:[-128, 127]</p><p>Modbus address:0x000E</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |
|Zabbix raw items |Battery: Battery Voltage discovery |<p>MIB: SUNSAVER-MPPT</p> |SNMP |battery.voltage.discovery[batteryVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.003051757813`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Device charge in warning state |<p>-</p> |`last(/Morningstar SunSaver MPPT SNMP/charge.state[chargeState.0])={$CHARGE.STATE.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Device charge in critical state</p> |
|Battery: Device charge in critical state |<p>-</p> |`last(/Morningstar SunSaver MPPT SNMP/charge.state[chargeState.0])={$CHARGE.STATE.CRIT}` |HIGH | |
|Battery: Low battery voltage |<p>-</p> |`max(/Morningstar SunSaver MPPT SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)<{#VOLTAGE.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically low battery voltage</p> |
|Battery: Critically low battery voltage |<p>-</p> |`max(/Morningstar SunSaver MPPT SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)<{#VOLTAGE.MIN.CRIT}` |HIGH | |
|Battery: High battery voltage |<p>-</p> |`min(/Morningstar SunSaver MPPT SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)>{#VOLTAGE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically high battery voltage</p> |
|Battery: Critically high battery voltage |<p>-</p> |`min(/Morningstar SunSaver MPPT SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)>{#VOLTAGE.MAX.CRIT}` |HIGH | |
|Load: Device load in warning state |<p>-</p> |`last(/Morningstar SunSaver MPPT SNMP/load.state[loadState.0])={$LOAD.STATE.WARN:"lvdWarning"}  or last(/Morningstar SunSaver MPPT SNMP/load.state[loadState.0])={$LOAD.STATE.WARN:"override"}` |WARNING |<p>**Depends on**:</p><p>- Load: Device load in critical state</p> |
|Load: Device load in critical state |<p>-</p> |`last(/Morningstar SunSaver MPPT SNMP/load.state[loadState.0])={$LOAD.STATE.CRIT:"lvd"} or last(/Morningstar SunSaver MPPT SNMP/load.state[loadState.0])={$LOAD.STATE.CRIT:"fault"}` |HIGH | |
|Status: Device has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Morningstar SunSaver MPPT SNMP/status.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Status: Failed to fetch data |<p>Zabbix has not received data for items for the last 5 minutes</p> |`nodata(/Morningstar SunSaver MPPT SNMP/status.uptime,5m)=1` |WARNING |<p>Manual close: YES</p> |
|Status: Device has "overcurrent" array faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.array_faults[arrayFaults.0],#3,"like","overcurrent")=2` |HIGH | |
|Status: Device has "mosfetSShorted" array faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.array_faults[arrayFaults.0],#3,"like","mosfetSShorted")=2` |HIGH | |
|Status: Device has "softwareFault" array faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.array_faults[arrayFaults.0],#3,"like","softwareFault")=2` |HIGH | |
|Status: Device has "batteryHvd" array faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.array_faults[arrayFaults.0],#3,"like","batteryHvd")=2` |HIGH | |
|Status: Device has "arrayHvd" array faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.array_faults[arrayFaults.0],#3,"like","arrayHvd")=2` |HIGH | |
|Status: Device has "customSettingsEdit" array faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.array_faults[arrayFaults.0],#3,"like","customSettingsEdit")=2` |HIGH | |
|Status: Device has "rtsShorted" array faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.array_faults[arrayFaults.0],#3,"like","rtsShorted")=2` |HIGH | |
|Status: Device has "rtsNoLongerValid" array faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.array_faults[arrayFaults.0],#3,"like","rtsNoLongerValid")=2` |HIGH | |
|Status: Device has "localTempSensorDamaged" array faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.array_faults[arrayFaults.0],#3,"like","localTempSensorDamaged")=2` |HIGH | |
|Status: Device has "externalShortCircuit" load faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.load_faults[loadFaults.0],#3,"like","externalShortCircuit")=2` |HIGH | |
|Status: Device has "overcurrent" load faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.load_faults[loadFaults.0],#3,"like","overcurrent")=2` |HIGH | |
|Status: Device has "mosfetShorted" load faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.load_faults[loadFaults.0],#3,"like","mosfetShorted")=2` |HIGH | |
|Status: Device has "software" load faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.load_faults[loadFaults.0],#3,"like","software")=2` |HIGH | |
|Status: Device has "loadHvd" load faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.load_faults[loadFaults.0],#3,"like","loadHvd")=2` |HIGH | |
|Status: Device has "highTempDisconnect" load faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.load_faults[loadFaults.0],#3,"like","highTempDisconnect")=2` |HIGH | |
|Status: Device has "customSettingsEdit" load faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.load_faults[loadFaults.0],#3,"like","customSettingsEdit")=2` |HIGH | |
|Status: Device has "unknownLoadFault" load faults flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.load_faults[loadFaults.0],#3,"like","unknownLoadFault")=2` |HIGH | |
|Status: Device has "rtsShorted" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","rtsShorted")=2` |WARNING | |
|Status: Device has "rtsDisconnected" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","rtsDisconnected")=2` |WARNING | |
|Status: Device has "heatsinkTempSensorOpen" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","heatsinkTempSensorOpen")=2` |WARNING | |
|Status: Device has "heatsinkTempSensorShorted" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","heatsinkTempSensorShorted")=2` |WARNING | |
|Status: Device has "sspptHot" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","sspptHot")=2` |WARNING | |
|Status: Device has "currentLimit" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","currentLimit")=2` |WARNING | |
|Status: Device has "currentOffset" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","currentOffset")=2` |WARNING | |
|Status: Device has "uncalibrated" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","uncalibrated")=2` |WARNING | |
|Status: Device has "rtsMiswire" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","rtsMiswire")=2` |WARNING | |
|Status: Device has "systemMiswire" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","systemMiswire")=2` |WARNING | |
|Status: Device has "mosfetSOpen" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","mosfetSOpen")=2` |WARNING | |
|Status: Device has "p12VoltageReferenceOff" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","p12VoltageReferenceOff")=2` |WARNING | |
|Status: Device has "highVaCurrentLimit" alarm flag |<p>-</p> |`count(/Morningstar SunSaver MPPT SNMP/status.alarms[alarms.0],#3,"like","highVaCurrentLimit")=2` |WARNING | |
|Temperature: Low battery temperature |<p>-</p> |`max(/Morningstar SunSaver MPPT SNMP/temp.battery[batteryTemperature.0],5m)<{$BATTERY.TEMP.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically low battery temperature</p> |
|Temperature: Critically low battery temperature |<p>-</p> |`max(/Morningstar SunSaver MPPT SNMP/temp.battery[batteryTemperature.0],5m)<{$BATTERY.TEMP.MIN.CRIT}` |HIGH | |
|Temperature: High battery temperature |<p>-</p> |`min(/Morningstar SunSaver MPPT SNMP/temp.battery[batteryTemperature.0],5m)>{$BATTERY.TEMP.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically high battery temperature</p> |
|Temperature: Critically high battery temperature |<p>-</p> |`min(/Morningstar SunSaver MPPT SNMP/temp.battery[batteryTemperature.0],5m)>{$BATTERY.TEMP.MAX.CRIT}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

