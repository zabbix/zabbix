
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
|Battery |Battery: Battery Voltage{#SINGLETON} |<p>MIB: SURESINE</p><p>Description:Battery Voltage(slow)</p><p>Scaling Factor:0.0002581787109375</p><p>Units:V</p><p>Range:[0.0, 17.0]</p><p>Modbus address:0x0004</p> |SNMP |battery.voltage[batteryVoltageSlow.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `2.581787109375E-4`</p> |
|Load |Load: State |<p>MIB: SURESINE</p><p>Description:Load State</p><p>Modbus address:0x000B</p><p> 0: Start</p><p>1: LoadOn</p><p>2: LvdWarning</p><p>3: LowVoltageDisconnect</p><p>4: Fault</p><p>5: Disconnect</p><p>6: NormalOff</p><p>7: UnknownState</p><p>8: Standby</p> |SNMP |load.state[loadState.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Load |Load: A/C Current |<p>MIB: SURESINE</p><p>Description:AC Output Current</p><p>Scaling Factor:0.0001953125</p><p>Units:A</p><p>Range:[0.0, 17]</p><p>Modbus address:0x0005</p> |SNMP |load.ac_current[acCurrent.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1.953125E-4`</p> |
|Status |Status: Faults |<p>MIB: SURESINE</p><p>Description:Faults</p><p>Modbus address:0x0007</p> |SNMP |counter.faults[faults.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |Status: Alarms |<p>MIB: SURESINE</p><p>Description:Faults</p><p>Modbus address:0x0007</p> |SNMP |counter.alarms[alarms.0]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return parseInt(value.replace(/\x20/g, ''), 16);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Temperature |Temperature: Heatsink |<p>MIB: SURESINE</p><p>Description:AC Output Current</p><p>Scaling Factor:0.0001953125</p><p>Units:A</p><p>Range:[0.0, 17]</p><p>Modbus address:0x0005</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |
|Zabbix_raw_items |Battery: Battery Voltage discovery |<p>MIB: SURESINE</p> |SNMP |battery.voltage.discovery[batteryVoltageSlow.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `2.581787109375E-4`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Low battery voltage (below {#VOLTAGE.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltageSlow.0{#SINGLETON}].max(5m)}<{#VOLTAGE.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically low battery voltage (below {#VOLTAGE.MIN.CRIT}C for 5m)</p> |
|Battery: Critically low battery voltage (below {#VOLTAGE.MIN.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltageSlow.0{#SINGLETON}].max(5m)}<{#VOLTAGE.MIN.CRIT}` |HIGH | |
|Battery: High battery voltage (over {#VOLTAGE.MAX.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltageSlow.0{#SINGLETON}].min(5m)}>{#VOLTAGE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically high battery voltage (over {#VOLTAGE.MAX.CRIT}C for 5m)</p> |
|Battery: Critically high battery voltage (over {#VOLTAGE.MAX.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltageSlow.0{#SINGLETON}].min(5m)}>{#VOLTAGE.MAX.CRIT}` |HIGH | |
|Load: Device load in warning state |<p>-</p> |`{TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.WARN:"lvdWarning"}  or {TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.WARN:"override"}` |WARNING |<p>**Depends on**:</p><p>- Load: Device load in critical state</p> |
|Load: Device load in critical state |<p>-</p> |`{TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.CRIT:"lvd"} or {TEMPLATE_NAME:load.state[loadState.0].last()}={$LOAD.STATE.CRIT:"fault"}` |HIGH | |
|Status: Device has alarm flags |<p>-</p> |`{TEMPLATE_NAME:counter.alarms[alarms.0].last()}>1` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

