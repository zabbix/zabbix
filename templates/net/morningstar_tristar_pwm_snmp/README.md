
# Template Net Morningstar TriStar PWM SNMP

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
|Charge mode discovery |<p>Discovery for device in charge mode</p> |DEPENDENT |controlmode.charge.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(parseInt(value) === 0 ? [{'{#SINGLETON}': ''}] : []);`</p> |
|Load mode discovery |<p>Discovery for device in load mode</p> |DEPENDENT |controlmode.load.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(parseInt(value) === 1 ? [{'{#SINGLETON}': ''}] : []);`</p> |
|Diversion mode discovery |<p>Discovery for device in diversion mode</p> |DEPENDENT |controlmode.diversion.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(parseInt(value) === 2 ? [{'{#SINGLETON}': ''}] : []);`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Array |Array: Array Voltage{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Array/Load Voltage</p><p>Scaling Factor:0.00424652099609375</p><p>Units:V</p><p>Range:[0, 80]</p><p>Modbus address:0x000A</p> |SNMP |array.voltage[arrayloadVoltage.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.004246520996`</p> |
|Battery |Battery: Battery Voltage{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Battery voltage</p><p>Scaling Factor:0.002950042724609375</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0008</p> |SNMP |battery.voltage[batteryVoltage.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.002950042725`</p> |
|Battery |Battery: Target Voltage{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Target Regulation Voltage</p><p>Scaling Factor:0.002950042724609375</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0010</p> |SNMP |target.voltage[targetVoltage.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.002950042725`</p> |
|Battery |Battery: Charge Current{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Charge Current</p><p>Scaling Factor:0.002034515380859375</p><p>Units:A</p><p>Range:[0, 60]</p><p>Modbus address:0x000B</p> |SNMP |charge.current[chargeCurrent.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.002034515381`</p> |
|Battery |Battery: Charge State{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Control State</p><p>Modbus address:0x001B</p> |SNMP |charge.state[controlState.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Battery |Battery: Charge State{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Control State</p><p>Modbus address:0x001B</p> |SNMP |diversion.charge.state[controlState.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Battery |Battery: Target Voltage{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Target Regulation Voltage</p><p>Scaling Factor:0.002950042724609375</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0010</p> |SNMP |diversion.target.voltage[targetVoltage.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.002950042725`</p> |
|Counter |Counter: Charge Amp-hours{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Ah (Resettable)</p><p>Scaling Factor:0.1</p><p>Units:Ah</p><p>Range:[0.0, 50000.0]</p><p>Modbus addresses:H=0x0011 L=0x0012</p> |SNMP |counter.charge_amp_hours[ahResettable.0{#SINGLETON}] |
|Counter |Counter: Charge KW-hours{#SINGLETON} |<p>MIB: TRISTAR</p> |SNMP |counter.charge_kw_hours[kilowattHours.0{#SINGLETON}] |
|Counter |Counter: Load Amp-hours{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Ah (Resettable)</p><p>Scaling Factor:0.1</p><p>Units:Ah</p><p>Range:[0.0, 50000.0]</p><p>Modbus addresses:H=0x0011 L=0x0012</p> |SNMP |counter.load_amp_hours[ahLoadResettable.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Counter |Counter: Load KW-hours{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Kilowatt Hours</p><p>Scaling Factor:1.0</p><p>Units:kWh</p><p>Range:[0.0, 5000.0]</p><p>Modbus address:0x001E</p> |SNMP |counter.load_kw_hours[kilowattHours.0{#SINGLETON}] |
|Counter |Counter: Diversion Amp-hours{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Ah (Resettable)</p><p>Scaling Factor:0.1</p><p>Units:Ah</p><p>Range:[0.0, 50000.0]</p><p>Modbus addresses:H=0x0011 L=0x0012</p> |SNMP |counter.diversion_amp_hours[ahResettable.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Counter |Counter: Diversion KW-hours{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Kilowatt Hours</p><p>Scaling Factor:1.0</p><p>Units:kWh</p><p>Range:[0.0, 5000.0]</p><p>Modbus address:0x001E</p> |SNMP |counter.diversion_kw_hours[kilowattHours.0{#SINGLETON}] |
|Load |Load: State{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Load State</p><p>Modbus address:0x001B</p><p>0: Start</p><p>1: Normal</p><p>2: LvdWarning</p><p>3: Lvd</p><p>4: Fault</p><p>5: Disconnect</p><p>6: LvdWarning1</p><p>7: OverrideLvd</p><p>8: Equalize</p> |SNMP |load.state[loadState.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Load |Load: Voltage{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Array/Load Voltage</p><p>Scaling Factor:0.00424652099609375</p><p>Units:V</p><p>Range:[0, 80]</p><p>Modbus address:0x000A</p> |SNMP |load.voltage[arrayloadVoltage.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.004246520996`</p> |
|Load |Load: Current{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Load Current</p><p>Scaling Factor:0.00966400146484375</p><p>Units:A</p><p>Range:[0, 60]</p><p>Modbus address:0x000C</p> |SNMP |load.current[loadCurrent.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.009664001465`</p> |
|Load |Load: Voltage{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Array/Load Voltage</p><p>Scaling Factor:0.00424652099609375</p><p>Units:V</p><p>Range:[0, 80]</p><p>Modbus address:0x000A</p> |SNMP |diversion.load.voltage[arrayloadVoltage.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.004246520996`</p> |
|Load |Load: Current{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:Load Current</p><p>Scaling Factor:0.00966400146484375</p><p>Units:A</p><p>Range:[0, 60]</p><p>Modbus address:0x000C</p> |SNMP |diversion.load.current[loadCurrent.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.009664001465`</p> |
|Load |Load: PWM Duty Cycle{#SINGLETON} |<p>MIB: TRISTAR</p><p>Description:PWM Duty Cycle</p><p>Scaling Factor:0.392156862745098</p><p>Units:%</p><p>Range:[0.0, 100.0]</p><p>Modbus address:0x001C</p> |SNMP |diversion.pwm_duty_cycle[pwmDutyCycle.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.3921568627`</p> |
|Status |Status: Control Mode |<p>MIB: TRISTAR</p><p>Description:Control Mode</p><p>Modbus address:0x001A</p><p>0: charge</p><p>1: loadControl</p><p>2: diversion</p><p>3: lighting</p> |SNMP |control.mode[controlMode.0] |
|Status |Status: Faults |<p>MIB: TRISTAR</p><p>Description:Battery voltage</p><p>Scaling Factor:0.002950042724609375</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0008</p> |SNMP |counter.faults[faults.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |Status: Alarms |<p>MIB: TRISTAR</p><p>Description:Battery voltage</p><p>Scaling Factor:0.002950042724609375</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0008</p> |SNMP |counter.alarms[alarms.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Temperature |Temperature: Battery |<p>MIB: TRISTAR</p><p>Description:Battery Temperature</p><p>Scaling Factor:1.0</p><p>Units:deg C</p><p>Range:[-40, 120]</p><p>Modbus address:0x000F</p> |SNMP |temp.battery[batteryTemperature.0] |
|Temperature |Temperature: Heatsink |<p>MIB: TRISTAR</p><p>Description:Heatsink Temperature</p><p>Scaling Factor:1.0</p><p>Units:deg C</p><p>Range:[-40, 120]</p><p>Modbus address:0x000E</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |
|Zabbix_raw_items |Battery: Battery Voltage discovery |<p>MIB: TRISTAR</p><p>Description:Battery voltage</p><p>Scaling Factor:0.002950042724609375</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0008</p> |SNMP |battery.voltage.discovery[batteryVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.002950042725`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Low battery voltage (below {#VOLTAGE.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].max(5m)}<{#VOLTAGE.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically low battery voltage (below {#VOLTAGE.MIN.CRIT}C for 5m)</p> |
|Battery: Critically low battery voltage (below {#VOLTAGE.MIN.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].max(5m)}<{#VOLTAGE.MIN.CRIT}` |HIGH | |
|Battery: High battery voltage (over {#VOLTAGE.MAX.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].min(5m)}>{#VOLTAGE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically high battery voltage (over {#VOLTAGE.MAX.CRIT}C for 5m)</p> |
|Battery: Critically high battery voltage (over {#VOLTAGE.MAX.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].min(5m)}>{#VOLTAGE.MAX.CRIT}` |HIGH | |
|Battery: Device charge in warning state |<p>-</p> |`{TEMPLATE_NAME:charge.state[controlState.0{#SINGLETON}].last()}={$CHARGE.STATE.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Device charge in critical state</p> |
|Battery: Device charge in critical state |<p>-</p> |`{TEMPLATE_NAME:charge.state[controlState.0{#SINGLETON}].last()}={$CHARGE.STATE.CRIT}` |HIGH | |
|Battery: Device charge in warning state |<p>-</p> |`{TEMPLATE_NAME:diversion.charge.state[controlState.0{#SINGLETON}].last()}={$CHARGE.STATE.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Device charge in critical state</p> |
|Battery: Device charge in critical state |<p>-</p> |`{TEMPLATE_NAME:diversion.charge.state[controlState.0{#SINGLETON}].last()}={$CHARGE.STATE.CRIT}` |HIGH | |
|Load: Device load in warning state |<p>-</p> |`{TEMPLATE_NAME:load.state[loadState.0{#SINGLETON}].last()}={$LOAD.STATE.WARN:"lvdWarning"}  or {TEMPLATE_NAME:load.state[loadState.0{#SINGLETON}].last()}={$LOAD.STATE.WARN:"override"}` |WARNING |<p>**Depends on**:</p><p>- Load: Device load in critical state</p> |
|Load: Device load in critical state |<p>-</p> |`{TEMPLATE_NAME:load.state[loadState.0{#SINGLETON}].last()}={$LOAD.STATE.CRIT:"lvd"} or {TEMPLATE_NAME:load.state[loadState.0{#SINGLETON}].last()}={$LOAD.STATE.CRIT:"fault"}` |HIGH | |
|Status: Device has alarm flags |<p>-</p> |`{TEMPLATE_NAME:counter.alarms[alarms.0].last()}>1` |WARNING | |
|Temperature: Low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m)</p> |
|Temperature: Critically low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.CRIT}` |HIGH | |
|Temperature: High battery temperature (over {$BATTERY.TEMP.MAX.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically high battery temperature (over {$BATTERY.TEMP.MAX.CRIT}C for 5m)</p> |
|Temperature: Critically high battery temperature (over {$BATTERY.TEMP.MAX.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.CRIT}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

