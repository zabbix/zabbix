
# Template Net Morningstar SureSine SNMP

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
|{$BATTERY.VOLTAGE.MAX.CRIT} |<p>-</p> |`15.5` |
|{$BATTERY.VOLTAGE.MAX.WARN} |<p>-</p> |`15` |
|{$BATTERY.VOLTAGE.MIN.CRIT} |<p>-</p> |`11.5` |
|{$BATTERY.VOLTAGE.MIN.WARN} |<p>-</p> |`12` |
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
|Battery |Battery: Battery Voltage |<p>MIB: SURESINE</p> |SNMP |battery.voltage[batteryVoltageSlow.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `2.581787109375E-4`</p> |
|Counter |Counter: Faults |<p>MIB: SURESINE</p> |SNMP |counter.faults[faults.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p> |
|Counter |Counter: Alarms |<p>MIB: SURESINE</p> |SNMP |counter.alarms[alarms.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p> |
|Load |Load: State |<p>MIB: SURESINE</p> |SNMP |load.state[loadState.0] |
|Load |Load: A/C Current |<p>MIB: SURESINE</p> |SNMP |load.ac_current[acCurrent.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1.953125E-4`</p> |
|Temperature |Temperature: Heatsink |<p>MIB: SURESINE</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Low battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltageSlow.0].max(5m)}<{$BATTERY.VOLTAGE.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically low battery voltage</p> |
|Battery: Critically low battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltageSlow.0].max(5m)}<{$BATTERY.VOLTAGE.MIN.CRIT}` |HIGH | |
|Battery: High battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltageSlow.0].min(5m)}>{$BATTERY.VOLTAGE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically high battery voltage</p> |
|Battery: Critically high battery voltage |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltageSlow.0].min(5m)}>{$BATTERY.VOLTAGE.MAX.CRIT}` |HIGH | |
|Counter: Device has alarm flags |<p>-</p> |`{TEMPLATE_NAME:counter.alarms[alarms.0].last()}>1` |WARNING | |
|Load: Device load in warning state |<p>-</p> |`{TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.WARN:"lvdWarning"}  or {TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.WARN:"override"}` |WARNING |<p>**Depends on**:</p><p>- Load: Device load in critical state</p> |
|Load: Device load in critical state |<p>-</p> |`{TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.CRIT:"lvd"} or {TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.CRIT:"fault"}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

