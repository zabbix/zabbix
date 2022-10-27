
# Morningstar SureSine by SNMP

## Overview

For Zabbix version: 6.2 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

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
|Battery |Battery: Voltage{#SINGLETON} |<p>MIB: SURESINE</p><p>Description:Battery Voltage(slow)</p><p>Scaling Factor:0.0002581787109375</p><p>Units:V</p><p>Range:[0.0, 17.0]</p><p>Modbus address:0x0004</p> |SNMP |battery.voltage[batteryVoltageSlow.0{#SINGLETON}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `2.581787109375E-4`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Load |Load: State |<p>MIB: SURESINE</p><p>Description:Load State</p><p>Modbus address:0x000B</p><p> 0: Start</p><p>1: LoadOn</p><p>2: LvdWarning</p><p>3: LowVoltageDisconnect</p><p>4: Fault</p><p>5: Disconnect</p><p>6: NormalOff</p><p>7: UnknownState</p><p>8: Standby</p> |SNMP |load.state[loadState.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Load |Load: A/C Current |<p>MIB: SURESINE</p><p>Description:AC Output Current</p><p>Scaling Factor:0.0001953125</p><p>Units:A</p><p>Range:[0.0, 17]</p><p>Modbus address:0x0005</p> |SNMP |load.ac_current[acCurrent.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1.953125E-4`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |
|Status |Status: Uptime (network) |<p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |status.net.uptime<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Status: Uptime (hardware) |<p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p> |SNMP |status.hw.uptime<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Status: Faults |<p>MIB: SURESINE</p><p>Description:Faults</p><p>Modbus address:0x0007</p> |SNMP |status.faults[faults.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Status |Status: Alarms |<p>MIB: SURESINE</p><p>Description:Faults</p><p>Modbus address:0x0007</p> |SNMP |status.alarms[alarms.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Temperature |Temperature: Heatsink |<p>MIB: SURESINE</p><p>Description:Heatsink Temperature</p><p>Scaling Factor:1</p><p>Units:C</p><p>Range:[-128, 127]</p><p>Modbus address:0x0006</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |
|Zabbix raw items |Battery: Battery Voltage discovery |<p>MIB: SURESINE</p> |SNMP |battery.voltage.discovery[batteryVoltageSlow.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `2.581787109375E-4`</p><p>- REGEX: `^(\d+)(\.\d{1,2})? \1\2`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Low battery voltage |<p>-</p> |`max(/Morningstar SureSine by SNMP/battery.voltage[batteryVoltageSlow.0{#SINGLETON}],5m)<{#VOLTAGE.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically low battery voltage</p> |
|Battery: Critically low battery voltage |<p>-</p> |`max(/Morningstar SureSine by SNMP/battery.voltage[batteryVoltageSlow.0{#SINGLETON}],5m)<{#VOLTAGE.MIN.CRIT}` |HIGH | |
|Battery: High battery voltage |<p>-</p> |`min(/Morningstar SureSine by SNMP/battery.voltage[batteryVoltageSlow.0{#SINGLETON}],5m)>{#VOLTAGE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically high battery voltage</p> |
|Battery: Critically high battery voltage |<p>-</p> |`min(/Morningstar SureSine by SNMP/battery.voltage[batteryVoltageSlow.0{#SINGLETON}],5m)>{#VOLTAGE.MAX.CRIT}` |HIGH | |
|Load: Device load in warning state |<p>-</p> |`last(/Morningstar SureSine by SNMP/load.state[loadState.0])={$LOAD.STATE.WARN:"lvdWarning"}  or last(/Morningstar SureSine by SNMP/load.state[loadState.0])={$LOAD.STATE.WARN:"override"}` |WARNING |<p>**Depends on**:</p><p>- Load: Device load in critical state</p> |
|Load: Device load in critical state |<p>-</p> |`last(/Morningstar SureSine by SNMP/load.state[loadState.0])={$LOAD.STATE.CRIT:"lvd"} or last(/Morningstar SureSine by SNMP/load.state[loadState.0])={$LOAD.STATE.CRIT:"fault"}` |HIGH | |
|Status: Device has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/Morningstar SureSine by SNMP/status.hw.uptime)>0 and last(/Morningstar SureSine by SNMP/status.hw.uptime)<10m) or (last(/Morningstar SureSine by SNMP/status.hw.uptime)=0 and last(/Morningstar SureSine by SNMP/status.net.uptime)<10m)` |INFO |<p>Manual close: YES</p> |
|Status: Failed to fetch data |<p>Zabbix has not received data for items for the last 5 minutes.</p> |`nodata(/Morningstar SureSine by SNMP/status.net.uptime,5m)=1` |WARNING |<p>Manual close: YES</p> |
|Status: Device has "reset" faults flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","reset")=2` |HIGH | |
|Status: Device has "overcurrent" faults flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","overcurrent")=2` |HIGH | |
|Status: Device has "unknownFault" faults flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","unknownFault")=2` |HIGH | |
|Status: Device has "software" faults flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","software")=2` |HIGH | |
|Status: Device has "highVoltageDisconnect" faults flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","highVoltageDisconnect")=2` |HIGH | |
|Status: Device has "suresineHot" faults flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","suresineHot")=2` |HIGH | |
|Status: Device has "dipSwitchChanged" faults flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","dipSwitchChanged")=2` |HIGH | |
|Status: Device has "customSettingsEdit" faults flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","customSettingsEdit")=2` |HIGH | |
|Status: Device has "heatsinkTempSensorOpen" alarm flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.alarms[alarms.0],#3,"like","heatsinkTempSensorOpen")=2` |WARNING | |
|Status: Device has "heatsinkTempSensorShort" alarm flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.alarms[alarms.0],#3,"like","heatsinkTempSensorShort")=2` |WARNING | |
|Status: Device has "unknownAlarm" alarm flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.alarms[alarms.0],#3,"like","unknownAlarm")=2` |WARNING | |
|Status: Device has "suresineHot" alarm flag |<p>-</p> |`count(/Morningstar SureSine by SNMP/status.alarms[alarms.0],#3,"like","suresineHot")=2` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

