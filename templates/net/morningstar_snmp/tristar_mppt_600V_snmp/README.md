
# Morningstar TriStar MPPT 600V SNMP

## Overview

For Zabbix version: 5.0 and higher  

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/5.0/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

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
|{$VOLTAGE.MAX.CRIT} | |`` |
|{$VOLTAGE.MAX.WARN} | |`` |
|{$VOLTAGE.MIN.CRIT} | |`` |
|{$VOLTAGE.MIN.WARN} | |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Battery voltage discovery |<p>Discovery for battery voltage triggers</p> |DEPENDENT |battery.voltage.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `Text is too long. Please see the template.`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Array |Array: Voltage |<p>MIB: TRISTAR-MPPT</p><p>Description:Array Voltage</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 650]</p><p>Modbus address:0x001b</p> |SNMP |array.voltage[arrayVoltage.0] |
|Array |Array: Array Current |<p>MIB: TRISTAR-MPPT</p><p>Description:Array Current</p><p>Scaling Factor:1.0</p><p>Units:A</p><p>Range:[-10, 80]</p><p>Modbus address:0x001d</p> |SNMP |array.current[arrayCurrent.0] |
|Array |Array: Sweep Vmp |<p>MIB: TRISTAR-MPPT</p><p>Description:Vmp (last sweep)</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 650.0]</p><p>Modbus address:0x003d</p> |SNMP |array.sweep_vmp[arrayVmpLastSweep.0] |
|Array |Array: Sweep Voc |<p>MIB: TRISTAR-MPPT</p><p>Description:Voc (last sweep)</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 650.0]</p><p>Modbus address:0x003e</p> |SNMP |array.sweep_voc[arrayVocLastSweep.0] |
|Array |Array: Sweep Pmax |<p>MIB: TRISTAR-MPPT</p><p>Description:Pmax (last sweep)</p><p>Scaling Factor:1.0</p><p>Units:W</p><p>Range:[-10, 5000]</p><p>Modbus address:0x003c</p> |SNMP |array.sweep_pmax[arrayPmaxLastSweep.0] |
|Battery |Battery: Charge State |<p>MIB: TRISTAR-MPPT</p><p>Description:Charge State</p><p>Modbus address:0x0032</p><p>0: Start</p><p>1: NightCheck</p><p>2: Disconnect</p><p>3: Night</p><p>4: Fault</p><p>5: Mppt</p><p>6: Absorption</p><p>7: Float</p><p>8: Equalize</p><p>9: Slave</p><p>10: Fixed</p> |SNMP |charge.state[chargeState.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Battery |Battery: Target Voltage |<p>MIB: TRISTAR-MPPT</p><p>Description:Target Voltage</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 650.0]</p><p>Modbus address:0x0033</p> |SNMP |target.voltage[targetRegulationVoltage.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Battery |Battery: Charge Current |<p>MIB: TRISTAR-MPPT</p><p>Description:Battery Current</p><p>Scaling Factor:1.0</p><p>Units:A</p><p>Range:[-10, 80]</p><p>Modbus address:0x001c</p> |SNMP |charge.current[batteryCurrent.0] |
|Battery |Battery: Output Power |<p>MIB: TRISTAR-MPPT</p><p>Description:Output Power</p><p>Scaling Factor:1.0</p><p>Units:W</p><p>Range:[-10, 4000]</p><p>Modbus address:0x003a</p> |SNMP |charge.output_power[ outputPower.0] |
|Battery |Battery: Voltage{#SINGLETON} |<p>MIB: TRISTAR-MPPT</p><p>Description:Battery voltage</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 80]</p><p>Modbus address:0x0018</p> |SNMP |battery.voltage[batteryVoltage.0{#SINGLETON}] |
|Counter |Counter: Charge Amp-hours |<p>MIB: TRISTAR-MPPT</p><p>Description:Ah Charge Resettable</p><p>Scaling Factor:1.0</p><p>Units:Ah</p><p>Range:[0.0, 5000]</p><p>Modbus addresses:H=0x0034 L=0x0035</p> |SNMP |counter.charge_amp_hours[ahChargeResetable.0] |
|Counter |Counter: Charge KW-hours |<p>MIB: TRISTAR-MPPT</p><p>Description:kWh Charge Resettable</p><p>Scaling Factor:1.0</p><p>Units:kWh</p><p>Range:[0.0, 65535.0]</p><p>Modbus address:0x0038</p> |SNMP |counter.charge_kw_hours[kwhChargeResetable.0] |
|Status |Status: Uptime |<p>Device uptime in seconds</p> |SNMP |status.uptime<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Status: Faults |<p>MIB: TRISTAR-MPPT</p><p>Description:Faults</p><p>Modbus addresses:H=0x002c L=0x002d</p> |SNMP |status.faults[faults.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- JAVASCRIPT: `Text is too long. Please see the template.`</p> |
|Status |Status: Alarms |<p>MIB: TRISTAR-MPPT</p><p>Description:Alarms</p><p>Modbus addresses:H=0x002e L=0x002f</p> |SNMP |status.alarms[alarms.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- JAVASCRIPT: `Text is too long. Please see the template.`</p> |
|Temperature |Temperature: Battery |<p>MIB: TRISTAR-MPPT</p><p>Description:Batt. Temp</p><p>Scaling Factor:1.0</p><p>Units:C</p><p>Range:[-40, 80]</p><p>Modbus address:0x0025</p> |SNMP |temp.battery[batteryTemperature.0] |
|Temperature |Temperature: Heatsink |<p>MIB: TRISTAR-MPPT</p><p>Description:HS Temp</p><p>Scaling Factor:1.0</p><p>Units:C</p><p>Range:[-40, 80]</p><p>Modbus address:0x0023</p> |SNMP |temp.heatsink[heatsinkTemperature.0] |
|Zabbix_raw_items |Battery: Battery Voltage discovery |<p>MIB: TRISTAR-MPPT</p><p>Description:Battery voltage</p><p>Scaling Factor:1.0</p><p>Units:V</p><p>Range:[-10, 80]</p><p>Modbus address:0x0018</p> |SNMP |battery.voltage.discovery[batteryVoltage.0] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery: Device charge in warning state |<p>-</p> |`{TEMPLATE_NAME:charge.state[chargeState.0].last()}={$CHARGE.STATE.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Device charge in critical state</p> |
|Battery: Device charge in critical state |<p>-</p> |`{TEMPLATE_NAME:charge.state[chargeState.0].last()}={$CHARGE.STATE.CRIT}` |HIGH | |
|Battery: Low battery voltage (below {#VOLTAGE.MIN.WARN}V for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].max(5m)}<{#VOLTAGE.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically low battery voltage (below {#VOLTAGE.MIN.CRIT}V for 5m)</p> |
|Battery: Critically low battery voltage (below {#VOLTAGE.MIN.CRIT}V for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].max(5m)}<{#VOLTAGE.MIN.CRIT}` |HIGH | |
|Battery: High battery voltage (over {#VOLTAGE.MAX.WARN}V for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].min(5m)}>{#VOLTAGE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Battery: Critically high battery voltage (over {#VOLTAGE.MAX.CRIT}V for 5m)</p> |
|Battery: Critically high battery voltage (over {#VOLTAGE.MAX.CRIT}V for 5m) |<p>-</p> |`{TEMPLATE_NAME:battery.voltage[batteryVoltage.0{#SINGLETON}].min(5m)}>{#VOLTAGE.MAX.CRIT}` |HIGH | |
|Status: Device has been restarted (uptime < 10m) |<p>Uptime is less than 10 minutes</p> |`{TEMPLATE_NAME:status.uptime.last()}<10m` |INFO |<p>Manual close: YES</p> |
|Status: Failed to fetch data (or no data for 5m) |<p>Zabbix has not received data for items for the last 5 minutes</p> |`{TEMPLATE_NAME:status.uptime.nodata(5m)}=1` |WARNING |<p>Manual close: YES</p> |
|Status: Device has "overcurrent" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"overcurrent","like")}=2` |HIGH | |
|Status: Device has "fetShort" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"fetShort","like")}=2` |HIGH | |
|Status: Device has "softwareFault" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"softwareFault","like")}=2` |HIGH | |
|Status: Device has "batteryHvd" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"batteryHvd","like")}=2` |HIGH | |
|Status: Device has "arrayHvd" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"arrayHvd","like")}=2` |HIGH | |
|Status: Device has "dipSwitchChange" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"dipSwitchChange","like")}=2` |HIGH | |
|Status: Device has "customSettingsEdit" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"customSettingsEdit","like")}=2` |HIGH | |
|Status: Device has "rtsShorted" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"rtsShorted","like")}=2` |HIGH | |
|Status: Device has "rtsDisconnected" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"rtsDisconnected","like")}=2` |HIGH | |
|Status: Device has "eepromRetryLimit" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"eepromRetryLimit","like")}=2` |HIGH | |
|Status: Device has "controllerWasReset" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"controllerWasReset","like")}=2` |HIGH | |
|Status: Device has "chargeSlaveControlTimeout" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"chargeSlaveControlTimeout","like")}=2` |HIGH | |
|Status: Device has "rs232SerialToMeterBridge" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"rs232SerialToMeterBridge","like")}=2` |HIGH | |
|Status: Device has "batteryLvd" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"batteryLvd","like")}=2` |HIGH | |
|Status: Device has "powerboardCommunicationFault" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"powerboardCommunicationFault","like")}=2` |HIGH | |
|Status: Device has "fault16Software" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"fault16Software","like")}=2` |HIGH | |
|Status: Device has "fault17Software" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"fault17Software","like")}=2` |HIGH | |
|Status: Device has "fault18Software" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"fault18Software","like")}=2` |HIGH | |
|Status: Device has "fault19Software" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"fault19Software","like")}=2` |HIGH | |
|Status: Device has "fault20Software" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"fault20Software","like")}=2` |HIGH | |
|Status: Device has "fault21Software" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"fault21Software","like")}=2` |HIGH | |
|Status: Device has "fpgaVersion" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"fpgaVersion","like")}=2` |HIGH | |
|Status: Device has "currentSensorReferenceOutOfRange" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"currentSensorReferenceOutOfRange","like")}=2` |HIGH | |
|Status: Device has "ia-refSlaveModeTimeout" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"ia-refSlaveModeTimeout","like")}=2` |HIGH | |
|Status: Device has "blockbusBoot" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"blockbusBoot","like")}=2` |HIGH | |
|Status: Device has "hscommMaster" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"hscommMaster","like")}=2` |HIGH | |
|Status: Device has "hscomm" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"hscomm","like")}=2` |HIGH | |
|Status: Device has "slave" faults flag |<p>-</p> |`{TEMPLATE_NAME:status.faults[faults.0].count(#3,"slave","like")}=2` |HIGH | |
|Status: Device has "rtsShorted" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"rtsShorted","like")}=2` |WARNING | |
|Status: Device has "rtsDisconnected" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"rtsDisconnected","like")}=2` |WARNING | |
|Status: Device has "heatsinkTempSensorOpen" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"heatsinkTempSensorOpen","like")}=2` |WARNING | |
|Status: Device has "heatsinkTempSensorShorted" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"heatsinkTempSensorShorted","like")}=2` |WARNING | |
|Status: Device has "highTemperatureCurrentLimit" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"highTemperatureCurrentLimit","like")}=2` |WARNING | |
|Status: Device has "currentLimit" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"currentLimit","like")}=2` |WARNING | |
|Status: Device has "currentOffset" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"currentOffset","like")}=2` |WARNING | |
|Status: Device has "batterySense" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"batterySense","like")}=2` |WARNING | |
|Status: Device has "batterySenseDisconnected" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"batterySenseDisconnected","like")}=2` |WARNING | |
|Status: Device has "uncalibrated" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"uncalibrated","like")}=2` |WARNING | |
|Status: Device has "rtsMiswire" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"rtsMiswire","like")}=2` |WARNING | |
|Status: Device has "highVoltageDisconnect" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"highVoltageDisconnect","like")}=2` |WARNING | |
|Status: Device has "systemMiswire" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"systemMiswire","like")}=2` |WARNING | |
|Status: Device has "mosfetSOpen" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"mosfetSOpen","like")}=2` |WARNING | |
|Status: Device has "p12VoltageOutOfRange" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"p12VoltageOutOfRange","like")}=2` |WARNING | |
|Status: Device has "highArrayVCurrentLimit" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"highArrayVCurrentLimit","like")}=2` |WARNING | |
|Status: Device has "maxAdcValueReached" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"maxAdcValueReached","like")}=2` |WARNING | |
|Status: Device has "controllerWasReset" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"controllerWasReset","like")}=2` |WARNING | |
|Status: Device has "alarm21Internal" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"alarm21Internal","like")}=2` |WARNING | |
|Status: Device has "p3VoltageOutOfRange" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"p3VoltageOutOfRange","like")}=2` |WARNING | |
|Status: Device has "derateLimit" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"derateLimit","like")}=2` |WARNING | |
|Status: Device has "arrayCurrentOffset" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"arrayCurrentOffset","like")}=2` |WARNING | |
|Status: Device has "ee-i2cRetryLimit" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"ee-i2cRetryLimit","like")}=2` |WARNING | |
|Status: Device has "ethernetAlarm" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"ethernetAlarm","like")}=2` |WARNING | |
|Status: Device has "lvd" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"lvd","like")}=2` |WARNING | |
|Status: Device has "software" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"software","like")}=2` |WARNING | |
|Status: Device has "fp12VoltageOutOfRange" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"fp12VoltageOutOfRange","like")}=2` |WARNING | |
|Status: Device has "extflashFault" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"extflashFault","like")}=2` |WARNING | |
|Status: Device has "slaveControlFault" alarm flag |<p>-</p> |`{TEMPLATE_NAME:status.alarms[alarms.0].count(#3,"slaveControlFault","like")}=2` |WARNING | |
|Temperature: Low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m)</p> |
|Temperature: Critically low battery temperature (below {$BATTERY.TEMP.MIN.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].max(5m)}<{$BATTERY.TEMP.MIN.CRIT}` |HIGH | |
|Temperature: High battery temperature (over {$BATTERY.TEMP.MAX.WARN}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- Temperature: Critically high battery temperature (over {$BATTERY.TEMP.MAX.CRIT}C for 5m)</p> |
|Temperature: Critically high battery temperature (over {$BATTERY.TEMP.MAX.CRIT}C for 5m) |<p>-</p> |`{TEMPLATE_NAME:temp.battery[batteryTemperature.0].min(5m)}>{$BATTERY.TEMP.MAX.CRIT}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

