
# Template Net Morningstar TriStar MPPT SNMP

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
|Array |Array: Array Voltage |<p>MIB: TRISTAR-MPPT</p><p>Description:Array Voltage</p><p>Scaling Factor:0.0054931640625</p><p>Units:V</p><p>Range:[-10, 180]</p><p>Modbus address:0x001b</p> |SNMP |array.voltage[arrayVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.005493164063`</p> |
|Array |Array: Array Current |<p>MIB: TRISTAR-MPPT</p><p>Description:Array Current</p><p>Scaling Factor:0.00244140625</p><p>Units:A</p><p>Range:[-10, 80]</p><p>Modbus address:0x001d</p> |SNMP |array.current[arrayCurrent.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.00244140625`</p> |
|Array |Array: Sweep Vmp |<p>MIB: TRISTAR-MPPT</p><p>Description:Vmp (last sweep)</p><p>Scaling Factor:0.0054931640625</p><p>Units:V</p><p>Range:[-10, 180.0]</p><p>Modbus address:0x003d</p> |SNMP |array.sweep_vmp[arrayVmpLastSweep.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.005493164063`</p> |
|Array |Array: Sweep Voc |<p>MIB: TRISTAR-MPPT</p><p>Description:Voc (last sweep)</p><p>Scaling Factor:0.0054931640625</p><p>Units:V</p><p>Range:[-10, 180.0]</p><p>Modbus address:0x003e</p> |SNMP |array.sweep_voc[arrayVocLastSweep.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.005493164063`</p> |
|Array |Array: Sweep Pmax |<p>MIB: TRISTAR-MPPT</p><p>Description:Pmax (last sweep)</p><p>Scaling Factor:0.10986328125</p><p>Units:W</p><p>Range:[-10, 5000]</p><p>Modbus address:0x003c</p> |SNMP |array.sweep_pmax[arrayPmaxLastSweep.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1098632813`</p> |
|Battery |Battery: Charge State |<p>MIB: TRISTAR-MPPT</p><p>Description:Charge State</p><p>Modbus address:0x0032</p><p>0: Start</p><p>1: NightCheck</p><p>2: Disconnect</p><p>3: Night</p><p>4: Fault</p><p>5: Mppt</p><p>6: Absorption</p><p>7: Float</p><p>8: Equalize</p><p>9: Slave</p> |SNMP |charge.state[chargeState.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Battery |Battery: Target Voltage |<p>MIB: TRISTAR-MPPT</p><p>Description:Target Voltage</p><p>Scaling Factor:0.0054931640625</p><p>Units:V</p><p>Range:[-10, 180.0]</p><p>Modbus address:0x0033</p> |SNMP |target.voltage[targetRegulationVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.005493164063`</p> |
|Battery |Battery: Charge Current |<p>MIB: TRISTAR-MPPT</p><p>Description:Battery Current</p><p>Scaling Factor:0.00244140625</p><p>Units:A</p><p>Range:[-10, 80]</p><p>Modbus address:0x001c</p> |SNMP |charge.current[batteryCurrent.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.00244140625`</p> |
|Battery |Battery: Output Power |<p>MIB: TRISTAR-MPPT</p><p>Description:Output Power</p><p>Scaling Factor:0.10986328125</p><p>Units:W</p><p>Range:[-10, 5000]</p><p>Modbus address:0x003a</p> |SNMP |charge.output_power[ outputPower.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1098632813`</p> |
|Battery |Battery: Battery Voltage{#SINGLETON} |<p>MIB: TRISTAR-MPPT</p><p>Description:Battery voltage</p><p>Scaling Factor:0.0054931640625</p><p>Units:V</p><p>Range:[-10, 180.0]</p><p>Modbus address:0x0018</p> |SNMP |battery.voltage[batteryVoltage.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.005493164063`</p> |
|Counter |Counter: Charge Amp-hours |<p>MIB: TRISTAR-MPPT</p><p>Description:Ah Charge Resetable</p><p>Scaling Factor:0.1</p><p>Units:Ah</p><p>Range:[0.0, 5000]</p><p>Modbus addresses:H=0x0034 L=0x0035</p> |SNMP |counter.charge_amp_hours[ahChargeResetable.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Counter |Counter: Charge KW-hours |<p>MIB: TRISTAR-MPPT</p><p>Description:kWh Charge Resetable</p><p>Scaling Factor:0.1</p><p>Units:kWh</p><p>Range:[0.0, 65535.0]</p><p>Modbus address:0x0038</p> |SNMP |counter.charge_kw_hours[kwhChargeResetable.0] |
|Status |Status: Faults |<p>MIB: TRISTAR-MPPT</p><p>Description:Faults</p><p>Modbus address:0x002c</p> |SNMP |counter.faults[faults.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |Status: Alarms |<p>MIB: TRISTAR-MPPT</p><p>Description:Faults</p><p>Modbus address:0x002c</p> |SNMP |counter.alarms[alarms.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Temperature |Temperature: Battery |<p>MIB: TRISTAR-MPPT</p><p>Description:Batt. Temp</p><p>Scaling Factor:1.0</p><p>Units:C</p><p>Range:[-40, 80]</p><p>Modbus address:0x0025</p> |SNMP |temp.battery[batteryTemperature.0] |
|Temperature |Temperature: Heatsink |<p>MIB: TRISTAR-MPPT</p><p>Description:HS Temp</p><p>Scaling Factor:1.0</p><p>Units:C</p><p>Range:[-40, 80]</p><p>Modbus address:0x0023</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |
|Zabbix_raw_items |Battery: Battery Voltage discovery |<p>MIB: TRISTAR-MPPT</p> |SNMP |battery.voltage.discovery[batteryVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.005493164063`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Device charge in warning state |<p>-</p> |`{TEMPLATE_NAME:charge.state[chargeState.0].last()}={$CHARGE.STATE.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Device charge in critical state</p> |
|Battery: Device charge in critical state |<p>-</p> |`{TEMPLATE_NAME:charge.state[chargeState.0].last()}={$CHARGE.STATE.CRIT}` |HIGH | |
|Battery: Low battery voltage (below {#VOLTAGE.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].max(5m)}<{#VOLTAGE.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically low battery voltage (below {#VOLTAGE.MIN.CRIT}C for 5m)</p> |
|Battery: Critically low battery voltage (below {#VOLTAGE.MIN.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].max(5m)}<{#VOLTAGE.MIN.CRIT}` |HIGH | |
|Battery: High battery voltage (over {#VOLTAGE.MAX.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].min(5m)}>{#VOLTAGE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically high battery voltage (over {#VOLTAGE.MAX.CRIT}C for 5m)</p> |
|Battery: Critically high battery voltage (over {#VOLTAGE.MAX.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].min(5m)}>{#VOLTAGE.MAX.CRIT}` |HIGH | |
|Status: Device has alarm flags |<p>-</p> |`{TEMPLATE_NAME:counter.alarms[alarms.0].last()}>1` |WARNING | |
|Temperature: Low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m)</p> |
|Temperature: Critically low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.CRIT}` |HIGH | |
|Temperature: High battery temperature (over {$BATTERY.TEMP.MAX.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically high battery temperature (over {$BATTERY.TEMP.MAX.CRIT}C for 5m)</p> |
|Temperature: Critically high battery temperature (over {$BATTERY.TEMP.MAX.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.CRIT}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

