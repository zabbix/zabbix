
# Template Net Morningstar ProStar PWM SNMP

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
|{$BATTERY.VOLTAGE.MAX.CRIT} |<p>-</p> |`31` |
|{$BATTERY.VOLTAGE.MAX.WARN} |<p>-</p> |`30` |
|{$BATTERY.VOLTAGE.MIN.CRIT} |<p>-</p> |`23` |
|{$BATTERY.VOLTAGE.MIN.WARN} |<p>-</p> |`24` |
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


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Array |Array: Array Voltage |<p>MIB: PROSTAR-PWM</p> |SNMP |array.voltage[arrayVoltage.0] |
|Battery |Battery: Charge State |<p>MIB: PROSTAR-PWM</p> |SNMP |charge.state[chargeState.0] |
|Battery |Battery: Battery Voltage |<p>MIB: PROSTAR-PWM</p> |SNMP |battery.voltage[batteryTerminalVoltage.0] |
|Battery |Battery: Target Voltage |<p>MIB: PROSTAR-PWM</p> |SNMP |target.voltage[targetVoltage.0] |
|Battery |Battery: Charge Current |<p>MIB: PROSTAR-PWM</p> |SNMP |charge.current[chargeCurrent.0] |
|Counter |Counter: Charge Amp-hours |<p>MIB: PROSTAR-PWM</p> |SNMP |counter.charge_amp_hours[ahChargeResettable.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Counter |Counter: Charge Amp-hours |<p>MIB: PROSTAR-PWM</p> |SNMP |counter.charge_kw_hours[kwhChargeResettable.0] |
|Counter |Counter: Load Amp-hours |<p>MIB: PROSTAR-PWM</p> |SNMP |counter.load_amp_hours[ahLoadResettable.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Counter |Counter: Array Faults |<p>MIB: PROSTAR-PWM</p> |SNMP |counter.array_faults[arrayFaults.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p> |
|Counter |Counter: Load Faults |<p>MIB: PROSTAR-PWM</p> |SNMP |counter.load_faults[loadFaults.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p> |
|Counter |Counter: Alarms |<p>MIB: PROSTAR-PWM</p> |SNMP |counter.alarms[alarms.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p> |
|Load |Load: State |<p>MIB: PROSTAR-PWM</p> |SNMP |load.state[loadState.0] |
|Load |Load: Voltage |<p>MIB: PROSTAR-PWM</p> |SNMP |load.voltage[loadVoltage.0] |
|Load |Load: Current |<p>MIB: PROSTAR-PWM</p> |SNMP |load.current[loadCurrent.0] |
|Temperature |Temperature: Ambient |<p>MIB: PROSTAR-PWM</p> |SNMP |temp.ambient[ambientTemperature.0] |
|Temperature |Temperature: Battery |<p>MIB: PROSTAR-PWM</p> |SNMP |temp.battery[batteryTemperature.0] |
|Temperature |Temperature: Heatsink |<p>MIB: PROSTAR-PWM</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Device charge in warning state |<p>-</p> |`{TEMPLATE_NAME:charge.state[chargeState.0].last()}={$CHARGE.STATE.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Device charge in critical state</p> |
|Battery: Device charge in critical state |<p>-</p> |`{TEMPLATE_NAME:charge.state[chargeState.0].last()}={$CHARGE.STATE.CRIT}` |HIGH | |
|Battery: Low battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryTerminalVoltage.0].max(5m)}<{$BATTERY.VOLTAGE.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically low battery voltage</p> |
|Battery: Critically low battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryTerminalVoltage.0].max(5m)}<{$BATTERY.VOLTAGE.MIN.CRIT}` |HIGH | |
|Battery: High battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryTerminalVoltage.0].min(5m)}>{$BATTERY.VOLTAGE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically high battery voltage</p> |
|Battery: Critically high battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryTerminalVoltage.0].min(5m)}>{$BATTERY.VOLTAGE.MAX.CRIT}` |HIGH | |
|Counter: Device has fault flags |<p>-</p> |`{TEMPLATE_NAME:counter.array_faults[arrayFaults.0].last()}>0 or {Template Net Morningstar ProStar PWM SNMP:counter.load_faults[loadFaults.0].last()}>0` |HIGH | |
|Counter: Device has alarm flags |<p>-</p> |`{TEMPLATE_NAME:counter.alarms[alarms.0].last()}>1` |WARNING | |
|Load: Device load in warning state |<p>-</p> |`{TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.WARN:"lvdWarning"}  or {TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.WARN:"override"}` |WARNING |<p>**Depends on**:</p><p>- Load: Device load in critical state</p> |
|Load: Device load in critical state |<p>-</p> |`{TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.CRIT:"lvd"} or {TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.CRIT:"fault"}` |HIGH | |
|Temperature: Low battery temperature |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically low battery temperature</p> |
|Temperature: Critically low battery temperature |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.CRIT}` |HIGH | |
|Temperature: High battery temperature |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically high battery temparature</p> |
|Temperature: Critically high battery temparature |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.CRIT}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

