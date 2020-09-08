
# Template Net Morningstar TriStar PWM diversion SNMP

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
|Battery |Battery: Charge State |<p>MIB: TRISTAR</p> |SNMP |charge.state[controlState.0] |
|Battery |Battery: Battery Voltage |<p>MIB: TRISTAR</p> |SNMP |battery.voltage[batteryVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.002950042725`</p> |
|Battery |Battery: Target Voltage |<p>MIB: TRISTAR</p> |SNMP |target.voltage[targetVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.002950042725`</p> |
|Counter |Counter: Charge Amp-hours |<p>MIB: TRISTAR</p> |SNMP |counter.charge_amp_hours[ahResettable.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Counter |Counter: Charge Amp-hours |<p>MIB: TRISTAR</p> |SNMP |counter.charge_kw_hours[kilowattHours.0] |
|Counter |Counter: Faults |<p>MIB: TRISTAR</p> |SNMP |counter.faults[faults.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p> |
|Counter |Counter: Alarms |<p>MIB: TRISTAR</p> |SNMP |counter.alarms[alarms.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p> |
|Diversion |Diversion: Load Voltage |<p>MIB: TRISTAR</p> |SNMP |diversion.load_voltage[arrayloadVoltage.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.004246520996`</p> |
|Diversion |Diversion: Current |<p>MIB: TRISTAR</p> |SNMP |diversion.current[loadCurrent.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.009664001465`</p> |
|Diversion |Diversion: PWM Duty Cycle |<p>MIB: TRISTAR</p> |SNMP |diversion.pwm_duty_cycle[pwmDutyCycle.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.3921568627`</p> |
|Temperature |Temperature: Battery |<p>MIB: TRISTAR</p> |SNMP |temp.battery[batteryTemperature.0] |
|Temperature |Temperature: Heatsink |<p>MIB: TRISTAR</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Device charge in warning state |<p>-</p> |`{TEMPLATE_NAME:charge.state[controlState.0].last()}={$CHARGE.STATE.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Device charge in critical state</p> |
|Battery: Device charge in critical state |<p>-</p> |`{TEMPLATE_NAME:charge.state[controlState.0].last()}={$CHARGE.STATE.CRIT}` |HIGH | |
|Battery: Low battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0].max(5m)}<{$BATTERY.VOLTAGE.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically low battery voltage</p> |
|Battery: Critically low battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0].max(5m)}<{$BATTERY.VOLTAGE.MIN.CRIT}` |HIGH | |
|Battery: High battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0].min(5m)}>{$BATTERY.VOLTAGE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically high battery voltage</p> |
|Battery: Critically high battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0].min(5m)}>{$BATTERY.VOLTAGE.MAX.CRIT}` |HIGH | |
|Counter: Device has alarm flags |<p>-</p> |`{TEMPLATE_NAME:counter.alarms[alarms.0].last()}>1` |WARNING | |
|Temperature: Low battery temperature |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically low battery temperature</p> |
|Temperature: Critically low battery temperature |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.CRIT}` |HIGH | |
|Temperature: High battery temperature |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically high battery temparature</p> |
|Temperature: Critically high battery temparature |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.CRIT}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

