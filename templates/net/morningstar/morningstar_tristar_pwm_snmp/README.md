
# Morningstar TriStar PWM by SNMP

## Overview

This template is designed for the effortless deployment of Morningstar TriStar PWM monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Morningstar TriStar PWM

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
|Status: Control Mode|<p>MIB: TRISTAR</p><p>Description:Control Mode</p><p>Modbus address:0x001A</p><p></p><p>0: charge</p><p>1: loadControl</p><p>2: diversion</p><p>3: lighting</p>|SNMP agent|control.mode[controlMode.0]|
|Counter: KW-hours|<p>MIB: TRISTAR</p><p>Description:Kilowatt Hours</p><p>Scaling Factor:1.0</p><p>Units:kWh</p><p>Range:[0.0, 5000.0]</p><p>Modbus address:0x001E</p>|SNMP agent|counter.charge_kw_hours[kilowattHours.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|
|Counter: Amp-hours|<p>MIB: TRISTAR</p><p>Description:Ah (Resettable)</p><p>Scaling Factor:0.1</p><p>Units:Ah</p><p>Range:[0.0, 50000.0]</p><p>Modbus addresses:H=0x0011 L=0x0012</p>|SNMP agent|counter.charge_amp_hours[ahResettable.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li></ul>|
|Battery: Battery Voltage discovery|<p>MIB: TRISTAR</p><p>Description:Battery voltage</p><p>Scaling Factor:0.002950042724609375</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0008</p>|SNMP agent|battery.voltage.discovery[batteryVoltage.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.002950042725`</p></li></ul>|
|Temperature: Battery|<p>MIB: TRISTAR</p><p>Description:Battery Temperature</p><p>Scaling Factor:1.0</p><p>Units:deg C</p><p>Range:[-40, 120]</p><p>Modbus address:0x000F</p>|SNMP agent|temp.battery[batteryTemperature.0]|
|Temperature: Heatsink|<p>MIB: TRISTAR</p><p>Description:Heatsink Temperature</p><p>Scaling Factor:1.0</p><p>Units:deg C</p><p>Range:[-40, 120]</p><p>Modbus address:0x000E</p>|SNMP agent|temp.heatsink[heatsinkTemperature.0]|
|Status: Faults|<p>MIB: TRISTAR</p><p>Description:Battery voltage</p><p>Scaling Factor:0.002950042724609375</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0008</p>|SNMP agent|status.faults[faults.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Status: Alarms|<p>MIB: TRISTAR</p><p>Description:Alarms</p><p>Modbus addresses:H=0x001D L=0x0017</p>|SNMP agent|status.alarms[alarms.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Status: Device has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Morningstar TriStar PWM by SNMP/status.hw.uptime)>0 and last(/Morningstar TriStar PWM by SNMP/status.hw.uptime)<10m) or (last(/Morningstar TriStar PWM by SNMP/status.hw.uptime)=0 and last(/Morningstar TriStar PWM by SNMP/status.net.uptime)<10m)`|Info|**Manual close**: Yes|
|Status: Failed to fetch data|<p>Zabbix has not received data for items for the last 5 minutes.</p>|`nodata(/Morningstar TriStar PWM by SNMP/status.net.uptime,5m)=1`|Warning|**Manual close**: Yes|
|Temperature: Low battery temperature||`max(/Morningstar TriStar PWM by SNMP/temp.battery[batteryTemperature.0],5m)<{$BATTERY.TEMP.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>Temperature: Critically low battery temperature</li></ul>|
|Temperature: Critically low battery temperature||`max(/Morningstar TriStar PWM by SNMP/temp.battery[batteryTemperature.0],5m)<{$BATTERY.TEMP.MIN.CRIT}`|High||
|Temperature: High battery temperature||`min(/Morningstar TriStar PWM by SNMP/temp.battery[batteryTemperature.0],5m)>{$BATTERY.TEMP.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Temperature: Critically high battery temperature</li></ul>|
|Temperature: Critically high battery temperature||`min(/Morningstar TriStar PWM by SNMP/temp.battery[batteryTemperature.0],5m)>{$BATTERY.TEMP.MAX.CRIT}`|High||
|Status: Device has "externalShort" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","externalShort")=2`|High||
|Status: Device has "overcurrent" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","overcurrent")=2`|High||
|Status: Device has "mosfetSShorted" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","mosfetSShorted")=2`|High||
|Status: Device has "softwareFault" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","softwareFault")=2`|High||
|Status: Device has "highVoltageDisconnect" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","highVoltageDisconnect")=2`|High||
|Status: Device has "tristarHot" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","tristarHot")=2`|High||
|Status: Device has "dipSwitchChange" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","dipSwitchChange")=2`|High||
|Status: Device has "customSettingsEdit" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","customSettingsEdit")=2`|High||
|Status: Device has "reset" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","reset")=2`|High||
|Status: Device has "systemMiswire" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","systemMiswire")=2`|High||
|Status: Device has "rtsShorted" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","rtsShorted")=2`|High||
|Status: Device has "rtsDisconnected" faults flag||`count(/Morningstar TriStar PWM by SNMP/status.faults[faults.0],#3,"like","rtsDisconnected")=2`|High||
|Status: Device has "rtsShorted" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","rtsShorted")=2`|Warning||
|Status: Device has "rtsDisconnected" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","rtsDisconnected")=2`|Warning||
|Status: Device has "heatsinkTempSensorOpen" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","heatsinkTempSensorOpen")=2`|Warning||
|Status: Device has "heatsinkTempSensorShorted" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","heatsinkTempSensorShorted")=2`|Warning||
|Status: Device has "tristarHot" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","tristarHot")=2`|Warning||
|Status: Device has "currentLimit" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","currentLimit")=2`|Warning||
|Status: Device has "currentOffset" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","currentOffset")=2`|Warning||
|Status: Device has "batterySense" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","batterySense")=2`|Warning||
|Status: Device has "batterySenseDisconnected" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","batterySenseDisconnected")=2`|Warning||
|Status: Device has "uncalibrated" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","uncalibrated")=2`|Warning||
|Status: Device has "rtsMiswire" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","rtsMiswire")=2`|Warning||
|Status: Device has "highVoltageDisconnect" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","highVoltageDisconnect")=2`|Warning||
|Status: Device has "diversionLoadNearMax" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","diversionLoadNearMax")=2`|Warning||
|Status: Device has "systemMiswire" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","systemMiswire")=2`|Warning||
|Status: Device has "mosfetSOpen" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","mosfetSOpen")=2`|Warning||
|Status: Device has "p12VoltageReferenceOff" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","p12VoltageReferenceOff")=2`|Warning||
|Status: Device has "loadDisconnectState" alarm flag||`count(/Morningstar TriStar PWM by SNMP/status.alarms[alarms.0],#3,"like","loadDisconnectState")=2`|Warning||

### LLD rule Battery voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery voltage discovery|<p>Discovery for battery voltage triggers</p>|Dependent item|battery.voltage.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Battery voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery: Voltage{#SINGLETON}|<p>MIB: TRISTAR</p><p>Description:Battery voltage</p><p>Scaling Factor:0.002950042724609375</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0008</p>|SNMP agent|battery.voltage[batteryVoltage.0{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.002950042725`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|

### Trigger prototypes for Battery voltage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Battery: Low battery voltage||`max(/Morningstar TriStar PWM by SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)<{#VOLTAGE.MIN.WARN}`|Warning|**Depends on**:<br><ul><li>Battery: Critically low battery voltage</li></ul>|
|Battery: Critically low battery voltage||`max(/Morningstar TriStar PWM by SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)<{#VOLTAGE.MIN.CRIT}`|High||
|Battery: High battery voltage||`min(/Morningstar TriStar PWM by SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)>{#VOLTAGE.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Battery: Critically high battery voltage</li></ul>|
|Battery: Critically high battery voltage||`min(/Morningstar TriStar PWM by SNMP/battery.voltage[batteryVoltage.0{#SINGLETON}],5m)>{#VOLTAGE.MAX.CRIT}`|High||

### LLD rule Charge mode discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Charge mode discovery|<p>Discovery for device in charge mode</p>|Dependent item|controlmode.charge.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Charge mode discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array: Voltage{#SINGLETON}|<p>MIB: TRISTAR</p><p>Description:Array/Load Voltage</p><p>Scaling Factor:0.00424652099609375</p><p>Units:V</p><p>Range:[0, 80]</p><p>Modbus address:0x000A</p>|SNMP agent|array.voltage[arrayloadVoltage.0{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.004246520996`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|
|Battery: Charge Current{#SINGLETON}|<p>MIB: TRISTAR</p><p>Description:Charge Current</p><p>Scaling Factor:0.002034515380859375</p><p>Units:A</p><p>Range:[0, 60]</p><p>Modbus address:0x000B</p>|SNMP agent|charge.current[chargeCurrent.0{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.002034515381`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|

### LLD rule Load mode discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Load mode discovery|<p>Discovery for device in load mode</p>|Dependent item|controlmode.load.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Load mode discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Load: State{#SINGLETON}|<p>MIB: TRISTAR</p><p>Description:Load State</p><p>Modbus address:0x001B</p><p></p><p>0: Start</p><p>1: Normal</p><p>2: LvdWarning</p><p>3: Lvd</p><p>4: Fault</p><p>5: Disconnect</p><p>6: LvdWarning1</p><p>7: OverrideLvd</p><p>8: Equalize</p>|SNMP agent|load.state[loadState.0{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Load mode discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Load: Device load in warning state||`last(/Morningstar TriStar PWM by SNMP/load.state[loadState.0{#SINGLETON}])={$LOAD.STATE.WARN:"lvdWarning"}  or last(/Morningstar TriStar PWM by SNMP/load.state[loadState.0{#SINGLETON}])={$LOAD.STATE.WARN:"override"}`|Warning|**Depends on**:<br><ul><li>Load: Device load in critical state</li></ul>|
|Load: Device load in critical state||`last(/Morningstar TriStar PWM by SNMP/load.state[loadState.0{#SINGLETON}])={$LOAD.STATE.CRIT:"lvd"} or last(/Morningstar TriStar PWM by SNMP/load.state[loadState.0{#SINGLETON}])={$LOAD.STATE.CRIT:"fault"}`|High||

### LLD rule Diversion mode discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Diversion mode discovery|<p>Discovery for device in diversion mode</p>|Dependent item|controlmode.diversion.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Diversion mode discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Load: PWM Duty Cycle{#SINGLETON}|<p>MIB: TRISTAR</p><p>Description:PWM Duty Cycle</p><p>Scaling Factor:0.392156862745098</p><p>Units:%</p><p>Range:[0.0, 100.0]</p><p>Modbus address:0x001C</p>|SNMP agent|diversion.pwm_duty_cycle[pwmDutyCycle.0{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.3921568627`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|

### LLD rule Charge + Diversion mode discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Charge + Diversion mode discovery|<p>Discovery for device in charge and diversion modes</p>|Dependent item|controlmode.charge_diversion.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Charge + Diversion mode discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery: Charge State{#SINGLETON}|<p>MIB: TRISTAR</p><p>Description:Control State</p><p>Modbus address:0x001B</p>|SNMP agent|charge.state[controlState.0{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Battery: Target Voltage{#SINGLETON}|<p>MIB: TRISTAR</p><p>Description:Target Regulation Voltage</p><p>Scaling Factor:0.002950042724609375</p><p>Units:V</p><p>Range:[0.0, 80.0]</p><p>Modbus address:0x0010</p>|SNMP agent|target.voltage[targetVoltage.0{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.002950042725`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|

### Trigger prototypes for Charge + Diversion mode discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Battery: Device charge in warning state||`last(/Morningstar TriStar PWM by SNMP/charge.state[controlState.0{#SINGLETON}])={$CHARGE.STATE.WARN}`|Warning|**Depends on**:<br><ul><li>Battery: Device charge in critical state</li></ul>|
|Battery: Device charge in critical state||`last(/Morningstar TriStar PWM by SNMP/charge.state[controlState.0{#SINGLETON}])={$CHARGE.STATE.CRIT}`|High||

### LLD rule Load + Diversion mode discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Load + Diversion mode discovery|<p>Discovery for device in load and diversion modes</p>|Dependent item|controlmode.load_diversion.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Load + Diversion mode discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Load: Current{#SINGLETON}|<p>MIB: TRISTAR</p><p>Description:Load Current</p><p>Scaling Factor:0.00966400146484375</p><p>Units:A</p><p>Range:[0, 60]</p><p>Modbus address:0x000C</p>|SNMP agent|load.current[loadCurrent.0{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.009664001465`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|
|Load: Voltage{#SINGLETON}|<p>MIB: TRISTAR</p><p>Description:Array/Load Voltage</p><p>Scaling Factor:0.00424652099609375</p><p>Units:V</p><p>Range:[0, 80]</p><p>Modbus address:0x000A</p>|SNMP agent|load.voltage[arrayloadVoltage.0{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.004246520996`</p></li><li><p>Regular expression: `^(\d+)(\.\d{1,2})? \1\2`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

