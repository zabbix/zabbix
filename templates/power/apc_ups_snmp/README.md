
# APC UPS SNMP

## Overview

For Zabbix version: 5.0 and higher  
The template to monitor APC UPS Symmetra LX by Zabbix SNMP agent.



This template was tested on:

- APC UPS Symmetra LX

## Setup

1\. Create a host for APC UPS Symmetra LX management IP as SNMPv2 interface.

2\. Link the template to the host.

3\. Customize macro values if needed.



## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$BATTERY.TEMP.MAX.WARN} |<p>Maximum battery temperature for trigger expression.</p> |`55` |
|{$TIME.PERIOD} |<p>Time period for trigger expression.</p> |`15m` |
|{$UPS.INPUT_FREQ.MAX.WARN} |<p>Maximum input frequency for trigger expression.</p> |`50.3` |
|{$UPS.INPUT_FREQ.MIN.WARN} |<p>Minimum input frequency for trigger expression.</p> |`49.7` |
|{$UPS.INPUT_VOLT.MAX.WARN} |<p>Maximum input voltage for trigger expression.</p> |`243` |
|{$UPS.INPUT_VOLT.MIN.WARN} |<p>Minimum input voltage for trigger expression.</p> |`197` |
|{$UPS.OUTPUT.MAX.WARN} |<p>Maximum output load in % for trigger expression.</p> |`80` |

## Template links

|Name|
|----|
|Generic SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Input phases discovery |<p>The input phase identifier. OID upsPhaseInputPhaseIndex.1.1</p> |SNMP |input.phases.discovery |
|External battery packs discovery | |SNMP |battery.packs.discovery<p>**Filter**:</p>AND <p>- A: {#CARTRIDGE_STATUS} NOT_MATCHES_REGEX `^$`</p> |
|External bad battery packs discovery |<p>Discovery of the number of external defective battery packs.</p> |SNMP |battery.packs.bad.discovery<p>**Filter**:</p>AND <p>- A: {#EXTERNAL_PACKS} NOT_MATCHES_REGEX `0`</p> |
|External sensor port 1 discovery |<p>uioSensorStatusTable</p> |SNMP |external.sensor1.discovery |
|External sensor port 2 discovery |<p>uioSensorStatusTable</p> |SNMP |external.sensor2.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|General |Model |<p>MIB: PowerNet-MIB</p><p>The UPS model name (e.g. 'APC Smart-UPS 600').</p> |SNMP |system.model[upsBasicIdentModel]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Serial number |<p>MIB: PowerNet-MIB</p><p>An 8-character string identifying the serial number of</p><p> the UPS internal microprocessor.  This number is set at</p><p> the factory.  NOTE: This number does NOT correspond to</p><p> the serial number on the rear of the UPS.</p> |SNMP |system.sn[upsAdvIdentSerialNumber]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Battery status |<p>MIB: PowerNet-MIB</p><p>The status of the UPS batteries. A batteryLow(3) value</p><p> indicates the UPS will be unable to sustain the current</p><p> load, and its services will be lost if power is not restored.</p><p> The amount of run time in reserve at the time of low battery</p><p> can be configured by the upsAdvConfigLowBatteryRunTime.</p><p> A batteryInFaultCondition(4)value indicates that a battery</p><p> installed has an internal error condition.</p> |SNMP |battery.status[upsBasicBatteryStatus]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Battery capacity |<p>MIB: PowerNet-MIB</p><p>The remaining battery capacity expressed in</p><p> percent of full capacity.</p> |SNMP |battery.capacity[upsAdvBatteryCapacity]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Battery runtime remaining |<p>MIB: PowerNet-MIB</p><p>The UPS battery run time remaining before battery</p><p> exhaustion.</p> |SNMP |battery.runtime_remaining[upsAdvBatteryRunTimeRemaining]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Battery voltage |<p>MIB: PowerNet-MIB</p><p>The actual battery bus voltage in Volts.</p> |SNMP |battery.voltage[upsHighPrecBatteryActualVoltage]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Battery last replace date |<p>MIB: PowerNet-MIB</p><p>The date when the UPS system's batteries were last replaced</p><p> in mm/dd/yy (or yyyy) format. For Smart-UPS models, this value</p><p> is originally set in the factory. When the UPS batteries</p><p> are replaced, this value should be reset by the administrator.</p><p> For Symmetra PX 250/500 this OID is read only and is configurable in the local display only.</p> |SNMP |battery.last_replace_date[upsBasicBatteryLastReplaceDate]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Battery replace indicator |<p>MIB: PowerNet-MIB</p><p>Indicates whether the UPS batteries need replacing.</p> |SNMP |battery.replace_indicator[upsAdvBatteryReplaceIndicator]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |External battery packs count |<p>MIB: PowerNet-MIB</p><p>The number of external battery packs connected to the UPS. If</p><p> the UPS does not use smart cells then the agent reports</p><p> ERROR_NO_SUCH_NAME.</p> |SNMP |battery.external_packs_count[upsAdvBatteryNumOfBattPacks]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Battery temperature |<p>MIB: PowerNet-MIB</p><p>The current internal UPS temperature expressed in</p><p> Celsius. Temperatures below zero read as 0.</p> |SNMP |battery.temperature[upsHighPrecBatteryTemperature]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Input voltage |<p>MIB: PowerNet-MIB</p><p>The current utility line voltage in VAC.</p> |SNMP |input.voltage[upsHighPrecInputLineVoltage]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Input frequency |<p>MIB: PowerNet-MIB</p><p>The current input frequency to the UPS system in Hz.</p> |SNMP |input.frequency[upsHighPrecInputFrequency]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Input fail cause |<p>MIB: PowerNet-MIB</p><p>The reason for the occurrence of the last transfer to UPS</p><p>battery power.  The variable is set to:</p><p>- noTransfer(1) -- if there is no transfer yet.</p><p>- highLineVoltage(2) -- if the transfer to battery is caused</p><p>by an over voltage greater than the high transfer voltage.</p><p>- brownout(3) -- if the duration of the outage is greater than</p><p>five seconds and the line voltage is between 40% of the</p><p>rated output voltage and the low transfer voltage.</p><p>- blackout(4) -- if the duration of the outage is greater than five</p><p>seconds and the line voltage is between 40% of the rated</p><p>output voltage and ground.</p><p>- smallMomentarySag(5) -- if the duration of the outage is less</p><p>than five seconds and the line voltage is between 40% of the</p><p>rated output voltage and the low transfer voltage.</p><p>- deepMomentarySag(6) -- if the duration of the outage is less</p><p>than five seconds and the line voltage is between 40% of the</p><p>rated output voltage and ground.  The variable is set to</p><p>- smallMomentarySpike(7) -- if the line failure is caused by a</p><p>rate of change of input voltage less than ten volts per cycle.</p><p>- largeMomentarySpike(8) -- if the line failure is caused by</p><p>a rate of change of input voltage greater than ten volts per cycle.</p><p>- selfTest(9) -- if the UPS was commanded to do a self test.</p><p>- rateOfVoltageChange(10) -- if the failure is due to the rate of change of</p><p>the line voltage.</p> |SNMP |input.fail[upsAdvInputLineFailCause]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Output voltage |<p>MIB: PowerNet-MIB</p><p>The output voltage of the UPS system in VAC.</p> |SNMP |output.voltage[upsHighPrecOutputVoltage]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Output load |<p>MIB: PowerNet-MIB</p><p>The current UPS load expressed in percent</p><p>of rated capacity.</p> |SNMP |output.load[upsHighPrecOutputLoad]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Output current |<p>MIB: PowerNet-MIB</p><p>The current in amperes drawn by the load on the UPS.</p> |SNMP |output.current[upsHighPrecOutputCurrent]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Output status |<p>MIB: PowerNet-MIB</p><p>The current state of the UPS. If the UPS is unable to</p><p> determine the state of the UPS this variable is set</p><p> to unknown(1).</p><p>During self-test most UPSes report onBattery(3) but</p><p> some that support it will report onBatteryTest(15).</p><p> To determine self-test status across all UPSes, refer</p><p> to the upsBasicStateOutputState OID.</p> |SNMP |output.status[upsBasicOutputStatus]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#PHASEINDEX}: Phase input voltage |<p>MIB: PowerNet-MIB</p><p>The input voltage in VAC, or -1 if it's unsupported</p><p> by this UPS.</p> |SNMP |phase.input.voltage[upsPhaseInputVoltage.1.1.{#PHASEINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#PHASEINDEX}: Phase input current |<p>MIB: PowerNet-MIB</p><p>The input current in 0.1 amperes, or -1 if it's</p><p> unsupported by this UPS.</p> |SNMP |phase.input.current[upsPhaseInputCurrent.1.1.{#PHASEINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery status |<p>MIB: PowerNet-MIB</p><p>The battery cartridge status.</p><p>bit 0 Disconnected</p><p>bit 1 Overvoltage</p><p>bit 2 NeedsReplacement</p><p>bit 3 OvertemperatureCritical</p><p>bit 4 Charger</p><p>bit 5 TemperatureSensor</p><p>bit 6 BusSoftStart</p><p>bit 7 OvertemperatureWarning</p><p>bit 8 GeneralError</p><p>bit 9 Communication</p><p>bit 10 DisconnectedFrame</p><p>bit 11 FirmwareMismatch</p> |SNMP |battery.pack.status[upsHighPrecBatteryPackCartridgeStatus.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery temperature |<p>MIB: PowerNet-MIB</p><p>The battery pack temperature measured in Celsius.</p> |SNMP |battery.temperature[upsHighPrecBatteryPackTemperature.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Serial number |<p>MIB: PowerNet-MIB</p><p>The battery pack serial number.</p> |SNMP |system.sn[upsHighPrecBatteryPackSerialNumber.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery estimated replace date |<p>MIB: PowerNet-MIB</p><p>The battery cartridge estimated battery replace date.</p> |SNMP |battery.estimated_replace_date[upsHighPrecBatteryPackCartridgeReplaceDate.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery pack cartridge health |<p>MIB: PowerNet-MIB</p><p>The battery cartridge health.</p><p>  bit 0 Battery lifetime okay</p><p>  bit 1 Battery lifetime near end, order replacement cartridge</p><p>  bit 2 Battery lifetime exceeded, replace battery</p><p>  bit 3 Battery lifetime near end acknowledged, order replacement cartridge</p><p>  bit 4 Battery lifetime exceeded acknowledged, replace battery</p><p>  bit 5 Battery measured lifetime near end, order replacement cartridge</p><p>  bit 6 Battery measured lifetime near end acknowledged, order replacement cartridge</p> |SNMP |battery.pack.cartridge_health[upsHighPrecBatteryPackCartridgeHealth.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#SNMPINDEX}: External battery packs bad |<p>MIB: PowerNet-MIB</p><p>The number of external battery packs connected to the UPS that</p><p>are defective. If the UPS does not use smart cells then the</p><p>agent reports ERROR_NO_SUCH_NAME.</p> |SNMP |battery.external_packs_bad[upsAdvBatteryNumOfBadBattPacks.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#EXTERNAL_SENSOR1_NAME}: Temperature sensor |<p>MIB: PowerNet-MIB</p><p>The sensor's current temperature reading in degrees Celsius.</p><p> -1 indicates an invalid reading due to lost communications.</p> |SNMP |external.sensor.temperature[uioSensorStatusTemperatureDegC.1.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#EXTERNAL_SENSOR1_NAME}: Humidity sensor |<p>MIB: PowerNet-MIB</p><p>The sensor's current humidity reading in percent relative</p><p> humidity. -1 indicates an invalid reading due to either a</p><p> sensor that doesn't read humidity or lost communications.</p> |SNMP |external.sensor.humidity[uioSensorStatusHumidity.1.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#EXTERNAL_SENSOR1_NAME}: Sensor alarm status |<p>MIB: PowerNet-MIB</p><p>The alarm status of the sensor. Possible values:</p><p>uioNormal                 (1),</p><p>uioWarning                (2),</p><p>uioCritical               (3),</p><p>sensorStatusNotApplicable (4)</p> |SNMP |external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#EXTERNAL_SENSOR2_NAME}: Temperature sensor |<p>MIB: PowerNet-MIB</p><p>The sensor's current temperature reading in degrees Celsius.</p><p> -1 indicates an invalid reading due to lost communications.</p> |SNMP |external.sensor.temperature[uioSensorStatusTemperatureDegC.2.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#EXTERNAL_SENSOR2_NAME}: Humidity sensor |<p>MIB: PowerNet-MIB</p><p>The sensor's current humidity reading in percent relative</p><p> humidity. -1 indicates an invalid reading due to either a</p><p> sensor that doesn't read humidity or lost communications.</p> |SNMP |external.sensor.humidity[uioSensorStatusHumidity.2.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |{#EXTERNAL_SENSOR2_NAME}: Sensor alarm status |<p>MIB: PowerNet-MIB</p><p>The alarm status of the sensor. Possible values:</p><p>uioNormal                 (1),</p><p>uioWarning                (2),</p><p>uioCritical               (3),</p><p>sensorStatusNotApplicable (4)</p> |SNMP |external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Battery has an internal error condition |<p>A battery installed has an internal error condition.</p> |`{TEMPLATE_NAME:battery.status[upsBasicBatteryStatus].last()}=4` |AVERAGE | |
|Battery is Low |<p>The UPS will be unable to sustain the current load, and its services will be lost if power is not restored.</p> |`{TEMPLATE_NAME:battery.status[upsBasicBatteryStatus].last()}=3` |AVERAGE | |
|Battery needs replacement |<p>A battery installed has an internal error condition.</p> |`{TEMPLATE_NAME:battery.replace_indicator[upsAdvBatteryReplaceIndicator].last()}=2` |HIGH | |
|Battery has high temperature (over {$BATTERY.TEMP.MAX.WARN}℃ for {$TIME.PERIOD}) | |`{TEMPLATE_NAME:battery.temperature[upsHighPrecBatteryTemperature].min({$TIME.PERIOD})} > {$BATTERY.TEMP.MAX.WARN}` |HIGH | |
|Unacceptable input voltage (out of range {$UPS.INPUT_VOLT.MIN.WARN}-{$UPS.INPUT_VOLT.MAX.WARN}V for {$TIME.PERIOD}) | |`{TEMPLATE_NAME:input.voltage[upsHighPrecInputLineVoltage].min({$TIME.PERIOD})} > 0 and ({TEMPLATE_NAME:input.voltage[upsHighPrecInputLineVoltage].min({$TIME.PERIOD})} > {$UPS.INPUT_VOLT.MAX.WARN} or {TEMPLATE_NAME:input.voltage[upsHighPrecInputLineVoltage].max({$TIME.PERIOD})} < {$UPS.INPUT_VOLT.MIN.WARN})` |HIGH | |
|Unacceptable input frequency (out of range {$UPS.INPUT_FREQ.MIN.WARN}-{$UPS.INPUT_FREQ.MAX.WARN}Hz for {$TIME.PERIOD}) | |`{TEMPLATE_NAME:input.frequency[upsHighPrecInputFrequency].min({$TIME.PERIOD})} > 0 and ({TEMPLATE_NAME:input.frequency[upsHighPrecInputFrequency].min({$TIME.PERIOD})} > {$UPS.INPUT_FREQ.MAX.WARN} or {TEMPLATE_NAME:input.frequency[upsHighPrecInputFrequency].max({$TIME.PERIOD})} < {$UPS.INPUT_FREQ.MIN.WARN})` |HIGH | |
|Output load is high (over {$UPS.OUTPUT.MAX.WARN}% for {$TIME.PERIOD}) |<p>A battery installed has an internal error condition.</p> |`{TEMPLATE_NAME:output.load[upsHighPrecOutputLoad].min({$TIME.PERIOD})} > {$UPS.OUTPUT.MAX.WARN}` |HIGH | |
|UPS is Timed Sleeping | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=5` |AVERAGE | |
|UPS is Switched Bypass | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=9` |AVERAGE | |
|UPS is Software Bypass | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=6` |AVERAGE | |
|UPS is Sleeping Until Power Return | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=11` |AVERAGE | |
|UPS is Rebooting | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=8` |AVERAGE | |
|UPS is On Smart Trim | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=12` |AVERAGE | |
|UPS is on Smart Boost | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=4` |AVERAGE | |
|UPS is on battery | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=3` |AVERAGE | |
|UPS is Off | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=7` |AVERAGE | |
|UPS is Emergency Static Bypass | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=16` |AVERAGE | |
|UPS is Hardware Failure Bypass | |`{TEMPLATE_NAME:output.status[upsBasicOutputStatus].last()}=10` |AVERAGE | |
|{#PHASEINDEX}: Unacceptable phase {#PHASEINDEX} input voltage (out of range {$UPS.INPUT_VOLT.MIN.WARN}-{$UPS.INPUT_VOLT.MAX.WARN}V for {$TIME.PERIOD}) | |`{TEMPLATE_NAME:phase.input.voltage[upsPhaseInputVoltage.1.1.{#PHASEINDEX}].min({$TIME.PERIOD})} > {$UPS.INPUT_VOLT.MAX.WARN} or {TEMPLATE_NAME:phase.input.voltage[upsPhaseInputVoltage.1.1.{#PHASEINDEX}].max({$TIME.PERIOD})} < {$UPS.INPUT_VOLT.MIN.WARN}` |HIGH | |
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery status is not okay |<p>The battery cartridge status:</p><p>bit 0 Disconnected</p><p>bit 1 Overvoltage</p><p>bit 2 NeedsReplacement</p><p>bit 3 OvertemperatureCritical</p><p>bit 4 Charger</p><p>bit 5 TemperatureSensor</p><p>bit 6 BusSoftStart</p><p>bit 7 OvertemperatureWarning</p><p>bit 8 GeneralError</p><p>bit 9 Communication</p><p>bit 10 DisconnectedFrame</p><p>bit 11 FirmwareMismatch</p> |`{TEMPLATE_NAME:battery.pack.status[upsHighPrecBatteryPackCartridgeStatus.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}].regexp("^(0{16})$")}=1` |WARNING | |
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery has high temperature (over {$BATTERY.TEMP.MAX.WARN}℃ for {$TIME.PERIOD}) | |`{TEMPLATE_NAME:battery.temperature[upsHighPrecBatteryPackTemperature.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}].min({$TIME.PERIOD})} > {$BATTERY.TEMP.MAX.WARN}` |HIGH | |
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery lifetime is not okay |<p>The battery cartridge health.</p><p>  bit 0 Battery lifetime okay</p><p>  bit 1 Battery lifetime near end, order replacement cartridge</p><p>  bit 2 Battery lifetime exceeded, replace battery</p><p>  bit 3 Battery lifetime near end acknowledged, order replacement cartridge</p><p>  bit 4 Battery lifetime exceeded acknowledged, replace battery</p><p>  bit 5 Battery measured lifetime near end, order replacement cartridge</p><p>  bit 6 Battery measured lifetime near end acknowledged, order replacement cartridge</p> |`{TEMPLATE_NAME:battery.pack.cartridge_health[upsHighPrecBatteryPackCartridgeHealth.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}].regexp("^(0)[0|1]{15}$")}=1` |WARNING | |
|{#EXTERNAL_SENSOR1_NAME}: Sensor has status Not Applicable |<p>The external sensor is not work or not connected.</p> |`{TEMPLATE_NAME:external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}].last()}=4` |INFO | |
|{#EXTERNAL_SENSOR1_NAME}: Sensor has status Warning |<p>The external sensor has returned a value greater than the warning threshold.</p> |`{TEMPLATE_NAME:external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}].last()}=2` |AVERAGE | |
|{#EXTERNAL_SENSOR1_NAME}: Sensor has status Critical |<p>The external sensor has returned a value greater than the critical threshold.</p> |`{TEMPLATE_NAME:external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}].last()}=3` |HIGH | |
|{#EXTERNAL_SENSOR2_NAME}: Sensor has status Not Applicable |<p>The external sensor is not work or not connected.</p> |`{TEMPLATE_NAME:external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}].last()}=4` |INFO | |
|{#EXTERNAL_SENSOR2_NAME}: Sensor has status Warning |<p>The external sensor has returned a value greater than the warning threshold.</p> |`{TEMPLATE_NAME:external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}].last()}=2` |AVERAGE | |
|{#EXTERNAL_SENSOR2_NAME}: Sensor has status Critical |<p>The external sensor has returned a value greater than the critical threshold.</p> |`{TEMPLATE_NAME:external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}].last()}=3` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

