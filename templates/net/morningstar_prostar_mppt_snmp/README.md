
# Template Net Morningstar ProStar MPPT SNMP

## Overview

For Zabbix version: 5.0  

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/current/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$BATTERY.TEMP.MAX.CRIT} |<p>-</p> |`60` |
|{$BATTERY.TEMP.MAX.WARN} |<p>-</p> |`45` |
|{$BATTERY.TEMP.MIN.CRIT} |<p>-</p> |`-20` |
|{$BATTERY.TEMP.MIN.WARN} |<p>-</p> |`0` |
|{$CHARGE.STATE.CRIT} |<p>fault</p> |`4` |
|{$CHARGE.STATE.WARN} |<p>disconnect</p> |`2` |
|{$LOAD.STATE.CRIT:"fault"} |<p>fault</p> |`4` |
|{$LOAD.STATE.CRIT:"lvd"} |<p>lvd</p> |`3` |
|{$LOAD.STATE.WARN:"disconnect"} |<p>disconnect</p> |`5` |
|{$LOAD.STATE.WARN:"lvdWarning"} |<p>lvdWarning</p> |`2` |
|{$LOAD.STATE.WARN:"override"} |<p>override</p> |`7` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Battery voltage discovery |<p>Discovery for battery voltage triggers</p> |DEPENDENT |battery.voltage.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var v_range = [     [[6, 18], [12, 15, 11.5, 15.5]],     [[20, 36], [24, 30, 23, 31]],     [[39, 55], [48, 60, 46, 62]], ], result = []; for (var idx in v_range) {     if (v_range[idx][0][0] < value && value < v_range[idx][0][1]) {       result = [{             '{#VOLTAGE.MIN.WARN}': v_range[idx][1][0],             '{#VOLTAGE.MAX.WARN}': v_range[idx][1][1],             '{#VOLTAGE.MIN.CRIT}': v_range[idx][1][2],             '{#VOLTAGE.MAX.CRIT}': v_range[idx][1][3],             '{#SINGLETON}': ''         }];         break;     } } return JSON.stringify(result);`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Array |Array: Array Voltage |<p>MIB: PROSTAR-MPPT</p><p>Array Voltage</p><p>  Description:Array Voltage</p><p>  Scaling Factor:1.0</p><p>  Units:V</p><p>  Range:[0, 80]</p><p>  Modbus address:0x0013</p> |SNMP |array.voltage[arrayVoltage.0] |
|Array |Array: Sweep Vmp |<p>MIB: PROSTAR-MPPT</p><p>Array Vmp</p><p>  Description:Array Max. Power Point Voltage</p><p>  Scaling Factor:1.0</p><p>  Units:V</p><p>  Range:[0.0, 5000.0]</p><p>  Modbus address:0x003D</p> |SNMP |array.sweep_vmp[arrayVmp.0] |
|Array |Array: Sweep Voc |<p>MIB: PROSTAR-MPPT</p><p>Array Voc</p><p> Description:Array Open Circuit Voltage</p><p> Scaling Factor:1.0</p><p> Units:V</p><p> Range:[0.0, 80.0]</p><p> Modbus address:0x003F</p> |SNMP |array.sweep_voc[arrayVoc.0] |
|Array |Array: Sweep Pmax |<p>MIB: PROSTAR-MPPT</p><p>Array Max. Power (sweep)</p><p> Description:Array Max. Power (last sweep)</p><p> Scaling Factor:1.0</p><p> Units:W</p><p> Range:[0.0, 500]</p><p> Modbus address:0x003E</p> |SNMP |array.sweep_pmax[arrayMaxPowerSweep.0] |
|Battery |Battery: Charge State |<p>MIB: PROSTAR-MPPT</p><p>Charge State</p><p>  Description:Control State</p><p>  Modbus address:0x0021</p><p>  0: Start</p><p>  1: NightCheck</p><p>  2: Disconnect</p><p>  3: Night</p><p>  4: Fault</p><p>  5: BulkMppt</p><p>  6: Absorption</p><p>  7: Float</p><p>  8: Equalize</p><p>  9: Slave</p><p>  10: Fixed</p> |SNMP |charge.state[chargeState.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Battery |Battery: Target Voltage |<p>MIB: PROSTAR-MPPT</p><p>Target Voltage</p><p> Description:Target Regulation Voltage</p><p> Scaling Factor:1.0</p><p> Units:V</p><p> Range:[0.0, 80.0]</p><p> Modbus address:0x0024</p> |SNMP |target.voltage[targetVoltage.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Battery |Battery: Charge Current |<p>MIB: PROSTAR-MPPT</p><p>Charge Current</p><p>  Description:Charge Current</p><p>  Scaling Factor:1.0</p><p>  Units:A</p><p>  Range:[0, 40]</p><p>  Modbus address:0x0010</p> |SNMP |charge.current[chargeCurrent.0] |
|Battery |Battery: Battery Voltage{#SINGLETON} |<p>MIB: PROSTAR-MPPT</p><p>Battery Terminal Voltage</p><p>Description:Battery  Terminal Voltage</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0012</p> |SNMP |battery.voltage[batteryTerminalVoltage.0{#SINGLETON}] |
|Counter |Counter: Charge Amp-hours |<p>MIB: PROSTAR-MPPT</p><p>Ah Charge (Resettable)</p><p> Description:Ah Charge (Resettable)</p><p> Scaling Factor:0.1</p><p> Units:Ah</p><p> Range:[0.0, 4294967294]</p><p> Modbus addresses:H=0x0026 L=0x0027</p> |SNMP |counter.charge_amp_hours[ahChargeResettable.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Counter |Counter: Charge KW-hours |<p>MIB: PROSTAR-MPPT</p><p>kWh Charge (Resettable)</p><p>Description:Kilowatt Hours Charge (Resettable)</p><p>Scaling Factor:1.0</p><p>Units:kWh</p><p>Range:[0.0, 65535]</p><p>Modbus address:0x002A</p> |SNMP |counter.charge_kw_hours[kwhChargeResettable.0] |
|Counter |Counter: Load Amp-hours |<p>MIB: PROSTAR-MPPT</p><p>Description:Ah Load (Resettable)</p><p>Scaling Factor:0.1</p><p>Units:Ah</p><p>Range:[0.0, 4294967294]</p><p>Modbus addresses:H=0x0032 L=0x0033</p> |SNMP |counter.load_amp_hours[ahLoadResettable.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Load |Load: State |<p>MIB: PROSTAR-MPPT</p><p>Load State</p><p> Description:Load State</p><p> Modbus address:0x002E</p><p> 0: Start</p><p>1: Normal</p><p>2: LvdWarning</p><p>3: Lvd</p><p>4: Fault</p><p>5: Disconnect</p><p>6: NormalOff</p><p>7: Override</p><p>8: NotUsed</p> |SNMP |load.state[loadState.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Load |Load: Voltage |<p>MIB: PROSTAR-MPPT</p><p>Load Voltage</p><p> Description:Load Voltage</p><p> Scaling Factor:1.0</p><p> Units:V</p><p> Range:[0, 80]</p><p> Modbus address:0x0014</p> |SNMP |load.voltage[loadVoltage.0] |
|Load |Load: Current |<p>MIB: PROSTAR-MPPT</p><p>Load Current</p><p> Description:Load Current</p><p> Scaling Factor:1.0</p><p> Units:A</p><p> Range:[0, 60]</p><p> Modbus address:0x0016</p> |SNMP |load.current[loadCurrent.0] |
|Status |Status: Array Faults |<p>MIB: PROSTAR-MPPT</p><p>Description:Array Faults</p><p>Modbus address:0x0022</p> |SNMP |counter.array_faults[arrayFaults.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |Status: Load Faults |<p>MIB: PROSTAR-MPPT</p><p>Description:Array Faults</p><p>Modbus address:0x0022</p> |SNMP |counter.load_faults[loadFaults.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p> |
|Status |Status: Alarms |<p>MIB: PROSTAR-MPPT</p><p>Description:Alarms</p><p>Modbus addresses:H=0x0038 L=0x0039</p> |SNMP |counter.alarms[alarms.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Temperature |Temperature: Ambient |<p>MIB: PROSTAR-MPPT</p><p>Ambient Temperature</p><p> Description:Ambient Temperature</p><p> Scaling Factor:1.0</p><p> Units:deg C</p><p> Range:[-128, 127]</p><p> Modbus address:0x001C</p> |SNMP |temp.ambient[ambientTemperature.0] |
|Temperature |Temperature: Battery |<p>MIB: PROSTAR-MPPT</p><p>Battery Temperature</p><p>  Description:Battery Temperature</p><p>  Scaling Factor:1.0</p><p>  Units:deg C</p><p>  Range:[-128, 127]</p><p>  Modbus address:0x001B</p> |SNMP |temp.battery[batteryTemperature.0] |
|Temperature |Temperature: Heatsink |<p>MIB: PROSTAR-MPPT</p><p>Heatsink Temperature</p><p> Description:Heatsink Temperature</p><p> Scaling Factor:1.0</p><p> Units:deg C</p><p> Range:[-128, 127]</p><p> Modbus address:0x001A</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |
|Zabbix_raw_items |Battery: Battery Voltage discovery |<p>MIB: PROSTAR-MPPT</p> |SNMP |battery.voltage.discovery[batteryTerminalVoltage.0] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Device charge in warning state |<p>-</p> |`{TEMPLATE_NAME:charge.state[chargeState.0].last()}={$CHARGE.STATE.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Device charge in critical state</p> |
|Battery: Device charge in critical state |<p>-</p> |`{TEMPLATE_NAME:charge.state[chargeState.0].last()}={$CHARGE.STATE.CRIT}` |HIGH | |
|Battery: Low battery voltage (below {#VOLTAGE.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryTerminalVoltage.0{#SINGLETON}].max(5m)}<{#VOLTAGE.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically low battery voltage (below {#VOLTAGE.MIN.CRIT}C for 5m)</p> |
|Battery: Critically low battery voltage (below {#VOLTAGE.MIN.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryTerminalVoltage.0{#SINGLETON}].max(5m)}<{#VOLTAGE.MIN.CRIT}` |HIGH | |
|Battery: High battery voltage (over {#VOLTAGE.MAX.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryTerminalVoltage.0{#SINGLETON}].min(5m)}>{#VOLTAGE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically high battery voltage (over {#VOLTAGE.MAX.CRIT}C for 5m)</p> |
|Battery: Critically high battery voltage (over {#VOLTAGE.MAX.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryTerminalVoltage.0{#SINGLETON}].min(5m)}>{#VOLTAGE.MAX.CRIT}` |HIGH | |
|Load: Device load in warning state |<p>-</p> |`{TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.WARN:"lvdWarning"}  or {TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.WARN:"override"}` |WARNING |<p>**Depends on**:</p><p>- Load: Device load in critical state</p> |
|Load: Device load in critical state |<p>-</p> |`{TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.CRIT:"lvd"} or {TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.CRIT:"fault"}` |HIGH | |
|Status: Device has fault flags |<p>-</p> |`{TEMPLATE_NAME:counter.array_faults[arrayFaults.0].last()}>0 or {Template Net Morningstar ProStar MPPT SNMP:counter.load_faults[loadFaults.0].last()}>0` |HIGH | |
|Status: Device has alarm flags |<p>-</p> |`{TEMPLATE_NAME:counter.alarms[alarms.0].last()}>1` |WARNING | |
|Temperature: Low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m)</p> |
|Temperature: Critically low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.CRIT}` |HIGH | |
|Temperature: High battery temperature (over {$BATTERY.TEMP.MAX.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically high battery temperature (over {$BATTERY.TEMP.MAX.CRIT}C for 5m)</p> |
|Temperature: Critically high battery temperature (over {$BATTERY.TEMP.MAX.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.CRIT}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

