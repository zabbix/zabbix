
# Template Net Morningstar TriStar MPPT 600V SNMP

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
|{$BATTERY.VOLTAGE.MAX.CRIT} |<p>-</p> |`62` |
|{$BATTERY.VOLTAGE.MAX.WARN} |<p>-</p> |`60` |
|{$BATTERY.VOLTAGE.MIN.CRIT} |<p>-</p> |`46` |
|{$BATTERY.VOLTAGE.MIN.WARN} |<p>-</p> |`48` |
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
|Array |Array: Array Voltage |<p>MIB: TRISTAR-MPPT</p> |SNMP |array.voltage[arrayVoltage.0] |
|Array |Array: Array Current |<p>MIB: TRISTAR-MPPT</p> |SNMP |array.current[arrayCurrent.0] |
|Array |Array: Sweep Vmp |<p>MIB: TRISTAR-MPPT</p> |SNMP |array.sweep_vmp[arrayVmpLastSweep.0] |
|Array |Array: Sweep Voc |<p>MIB: TRISTAR-MPPT</p> |SNMP |array.sweep_voc[arrayVocLastSweep.0] |
|Array |Array: Sweep Pmax |<p>MIB: TRISTAR-MPPT</p> |SNMP |array.sweep_pmax[arrayPmaxLastSweep.0] |
|Battery |Battery: Charge State |<p>MIB: TRISTAR-MPPT</p> |SNMP |charge.state[chargeState.0] |
|Battery |Battery: Battery Voltage |<p>MIB: TRISTAR-MPPT</p> |SNMP |battery.voltage[batteryVoltage.0] |
|Battery |Battery: Target Voltage |<p>MIB: TRISTAR-MPPT</p> |SNMP |target.voltage[targetRegulationVoltage.0] |
|Battery |Battery: Charge Current |<p>MIB: TRISTAR-MPPT</p> |SNMP |charge.current[batteryCurrent.0] |
|Battery |Battery: Output Power |<p>MIB: TRISTAR-MPPT</p> |SNMP |charge.output_power[ outputPower.0] |
|Counter |Counter: Charge Amp-hours |<p>MIB: TRISTAR-MPPT</p> |SNMP |counter.charge_amp_hours[ahChargeResetable.0] |
|Counter |Counter: Charge Amp-hours |<p>MIB: TRISTAR-MPPT</p> |SNMP |counter.charge_kw_hours[kwhChargeResetable.0] |
|Counter |Counter: Faults |<p>MIB: TRISTAR-MPPT</p> |SNMP |counter.faults[faults.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p> |
|Counter |Counter: Alarms |<p>MIB: TRISTAR-MPPT</p> |SNMP |counter.alarms[alarms.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p> |
|Temperature |Temperature: Battery |<p>MIB: TRISTAR-MPPT</p> |SNMP |temp.battery[batteryTemperature.0] |
|Temperature |Temperature: Heatsink |<p>MIB: TRISTAR-MPPT</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Device charge in warning state |<p>-</p> |`{TEMPLATE_NAME:charge.state[chargeState.0].last()}={$CHARGE.STATE.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Device charge in critical state</p> |
|Battery: Device charge in critical state |<p>-</p> |`{TEMPLATE_NAME:charge.state[chargeState.0].last()}={$CHARGE.STATE.CRIT}` |HIGH | |
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

