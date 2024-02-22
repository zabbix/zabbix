
# Morningstar SureSine by SNMP

## Overview

This template is designed for the effortless deployment of Morningstar SureSine monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Morningstar SureSine

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$BATTERY.TEMP.MIN.WARN}|<p>Battery low temperature warning value</p>|`0`|
|{$BATTERY.TEMP.MAX.WARN}|<p>Battery high temperature warning value</p>|`45`|
|{$BATTERY.TEMP.MIN.CRIT}|<p>Battery low temperature critical value</p>|`-20`|
|{$BATTERY.TEMP.MAX.CRIT}|<p>Battery high temperature critical value</p>|`60`|
|{$VOLTAGE.MIN.WARN}|||
|{$VOLTAGE.MAX.WARN}|||
|{$VOLTAGE.MIN.CRIT}|||
|{$VOLTAGE.MAX.CRIT}|||
|{$CHARGE.STATE.WARN}|<p>disconnect</p>|`2`|
|{$CHARGE.STATE.CRIT}|<p>fault</p>|`4`|
|{$LOAD.STATE.WARN:"lvdWarning"}|<p>lvdWarning</p>|`2`|
|{$LOAD.STATE.WARN:"disconnect"}|<p>disconnect</p>|`5`|
|{$LOAD.STATE.WARN:"override"}|<p>override</p>|`7`|
|{$LOAD.STATE.CRIT:"lvd"}|<p>lvd</p>|`3`|
|{$LOAD.STATE.CRIT:"fault"}|<p>fault</p>|`4`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Status: Uptime (network)|<p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|status.net.uptime<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Status: Uptime (hardware)|<p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|status.hw.uptime<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Battery: Battery Voltage discovery|<p>MIB: SURESINE</p>|SNMP agent|battery.voltage.discovery[batteryVoltageSlow.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `2.581787109375E-4`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|
|Load: State|<p>MIB: SURESINE</p><p>Description:Load State</p><p>Modbus address:0x000B</p><p></p><p> 0: Start</p><p>1: LoadOn</p><p>2: LvdWarning</p><p>3: LowVoltageDisconnect</p><p>4: Fault</p><p>5: Disconnect</p><p>6: NormalOff</p><p>7: UnknownState</p><p>8: Standby</p>|SNMP agent|load.state[loadState.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Load: A/C Current|<p>MIB: SURESINE</p><p>Description:AC Output Current</p><p>Scaling Factor:0.0001953125</p><p>Units:A</p><p>Range:[0.0, 17]</p><p>Modbus address:0x0005</p>|SNMP agent|load.ac_current[acCurrent.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1.953125E-4`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|
|Temperature: Heatsink|<p>MIB: SURESINE</p><p>Description:Heatsink Temperature</p><p>Scaling Factor:1</p><p>Units:C</p><p>Range:[-128, 127]</p><p>Modbus address:0x0006</p>|SNMP agent|temp.heatsink[heatsinkTemperature.0]|
|Status: Faults|<p>MIB: SURESINE</p><p>Description:Faults</p><p>Modbus address:0x0007</p>|SNMP agent|status.faults[faults.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Status: Alarms|<p>MIB: SURESINE</p><p>Description:Faults</p><p>Modbus address:0x0007</p>|SNMP agent|status.alarms[alarms.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Status: Device has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Morningstar SureSine by SNMP/status.hw.uptime)>0 and last(/Morningstar SureSine by SNMP/status.hw.uptime)<10m) or (last(/Morningstar SureSine by SNMP/status.hw.uptime)=0 and last(/Morningstar SureSine by SNMP/status.net.uptime)<10m)`|Info|**Manual close**: Yes|
|Status: Failed to fetch data|<p>Zabbix has not received data for items for the last 5 minutes.</p>|`nodata(/Morningstar SureSine by SNMP/status.net.uptime,5m)=1`|Warning|**Manual close**: Yes|
|Load: Device load in warning state||`last(/Morningstar SureSine by SNMP/load.state[loadState.0])={$LOAD.STATE.WARN:"lvdWarning"}  or last(/Morningstar SureSine by SNMP/load.state[loadState.0])={$LOAD.STATE.WARN:"override"}`|Warning|**Depends on**:<br><ul><li>Load: Device load in critical state</li></ul>|
|Load: Device load in critical state||`last(/Morningstar SureSine by SNMP/load.state[loadState.0])={$LOAD.STATE.CRIT:"lvd"} or last(/Morningstar SureSine by SNMP/load.state[loadState.0])={$LOAD.STATE.CRIT:"fault"}`|High||
|Status: Device has "reset" faults flag||`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","reset")=2`|High||
|Status: Device has "overcurrent" faults flag||`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","overcurrent")=2`|High||
|Status: Device has "unknownFault" faults flag||`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","unknownFault")=2`|High||
|Status: Device has "software" faults flag||`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","software")=2`|High||
|Status: Device has "highVoltageDisconnect" faults flag||`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","highVoltageDisconnect")=2`|High||
|Status: Device has "suresineHot" faults flag||`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","suresineHot")=2`|High||
|Status: Device has "dipSwitchChanged" faults flag||`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","dipSwitchChanged")=2`|High||
|Status: Device has "customSettingsEdit" faults flag||`count(/Morningstar SureSine by SNMP/status.faults[faults.0],#3,"like","customSettingsEdit")=2`|High||
|Status: Device has "heatsinkTempSensorOpen" alarm flag||`count(/Morningstar SureSine by SNMP/status.alarms[alarms.0],#3,"like","heatsinkTempSensorOpen")=2`|Warning||
|Status: Device has "heatsinkTempSensorShort" alarm flag||`count(/Morningstar SureSine by SNMP/status.alarms[alarms.0],#3,"like","heatsinkTempSensorShort")=2`|Warning||
|Status: Device has "unknownAlarm" alarm flag||`count(/Morningstar SureSine by SNMP/status.alarms[alarms.0],#3,"like","unknownAlarm")=2`|Warning||
|Status: Device has "suresineHot" alarm flag||`count(/Morningstar SureSine by SNMP/status.alarms[alarms.0],#3,"like","suresineHot")=2`|Warning||

### LLD rule Battery voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery voltage discovery|<p>Discovery for battery voltage triggers</p>|Dependent item|battery.voltage.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Battery voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery: Voltage{#SINGLETON}|<p>MIB: SURESINE</p><p>Description:Battery Voltage(slow)</p><p>Scaling Factor:0.0002581787109375</p><p>Units:V</p><p>Range:[0.0, 17.0]</p><p>Modbus address:0x0004</p>|SNMP agent|battery.voltage[batteryVoltageSlow.0{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `2.581787109375E-4`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|

### Trigger prototypes for Battery voltage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Battery: Low battery voltage||`max(/Morningstar SureSine by SNMP/battery.voltage[batteryVoltageSlow.0{#SINGLETON}],5m)<{#VOLTAGE.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>Battery: Critically low battery voltage</li></ul>|
|Battery: Critically low battery voltage||`max(/Morningstar SureSine by SNMP/battery.voltage[batteryVoltageSlow.0{#SINGLETON}],5m)<{#VOLTAGE.MIN.CRIT}`|High||
|Battery: High battery voltage||`min(/Morningstar SureSine by SNMP/battery.voltage[batteryVoltageSlow.0{#SINGLETON}],5m)>{#VOLTAGE.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Battery: Critically high battery voltage</li></ul>|
|Battery: Critically high battery voltage||`min(/Morningstar SureSine by SNMP/battery.voltage[batteryVoltageSlow.0{#SINGLETON}],5m)>{#VOLTAGE.MAX.CRIT}`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

