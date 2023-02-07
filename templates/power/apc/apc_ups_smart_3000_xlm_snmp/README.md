
# APC Smart-UPS 3000 XLM by SNMP

## Overview

For Zabbix version: 6.0 and higher.  
The template to monitor APC Smart-UPS 3000 XLM by Zabbix SNMP agent.
Note: please, use the latest version of the firmware for your NMC in order for the template to work correctly.



This template was tested on:

- APC Smart-UPS 3000 XLM

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/network_devices) for basic instructions.

1\. Create a host for APC Smart-UPS 3000 XLM management IP as SNMPv2 interface.

2\. Link the template to the host.

3\. Customize macro values if needed.



## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$BATTERY.CAPACITY.MIN.WARN} |<p>Minimum battery capacity percentage for trigger expression.</p> |`50` |
|{$BATTERY.TEMP.MAX.WARN} |<p>Maximum battery temperature for trigger expression.</p> |`55` |
|{$SNMP.TIMEOUT} |<p>The time interval for SNMP agent availability trigger expression.</p> |`5m` |
|{$TIME.PERIOD} |<p>Time period for trigger expression.</p> |`15m` |
|{$UPS.INPUT_FREQ.MAX.WARN} |<p>Maximum input frequency for trigger expression.</p> |`50.3` |
|{$UPS.INPUT_FREQ.MIN.WARN} |<p>Minimum input frequency for trigger expression.</p> |`49.7` |
|{$UPS.INPUT_VOLT.MAX.WARN} |<p>Maximum input voltage for trigger expression.</p> |`243` |
|{$UPS.INPUT_VOLT.MIN.WARN} |<p>Minimum input voltage for trigger expression.</p> |`197` |
|{$UPS.OUTPUT.MAX.WARN} |<p>Maximum output load in % for trigger expression.</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|External bad battery packs discovery |<p>Discovery of the number of external defective battery packs.</p> |SNMP |battery.packs.bad.discovery |
|External battery packs discovery |<p>-</p> |SNMP |battery.packs.discovery<p>**Filter**:</p>AND <p>- {#CARTRIDGE_STATUS} NOT_MATCHES_REGEX `^$`</p> |
|External sensor port 1 discovery |<p>uioSensorStatusTable</p> |SNMP |external.sensor1.discovery<p>**Overrides:**</p><p>Temp<br> - {#EXTERNAL_SENSOR1_NAME} MATCHES_REGEX `Temp`<br>  - ITEM_PROTOTYPE LIKE `Humidity`<br>  - NO_DISCOVER</p> |
|External sensor port 2 discovery |<p>uioSensorStatusTable</p> |SNMP |external.sensor2.discovery<p>**Overrides:**</p><p>Temp<br> - {#EXTERNAL_SENSOR2_NAME} MATCHES_REGEX `Temp`<br>  - ITEM_PROTOTYPE LIKE `Humidity`<br>  - NO_DISCOVER</p> |
|Input phases discovery |<p>The input phase identifier. OID upsPhaseInputPhaseIndex.1.1</p> |SNMP |input.phases.discovery |
|Output phases discovery |<p>The output phase identifier. OID upsPhaseOutputPhaseIndex.1.1</p> |SNMP |output.phases.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|General |Model |<p>MIB: PowerNet-MIB</p><p>The UPS model name (e.g. 'APC Smart-UPS 600').</p> |SNMP |system.model[upsBasicIdentModel]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |Serial number |<p>MIB: PowerNet-MIB</p><p>An 8-character string identifying the serial number of</p><p> the UPS internal microprocessor.  This number is set at</p><p> the factory.  NOTE: This number does NOT correspond to</p><p> the serial number on the rear of the UPS.</p> |SNMP |system.sn[upsAdvIdentSerialNumber]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet,</p><p>3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed</p><p>node, together with information on how to contact this person.  If no contact</p><p>information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management</p><p>subsystem contained in the entity.  This value is allocated within the SMI enterprises</p><p>subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what</p><p>kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was</p><p>assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1</p><p>to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By</p><p>convention, this is the node's fully-qualified domain name.  If the name is unknown,</p><p>the value is the zero-length string.</p> |SNMP |system.name[sysName.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Serial number |<p>MIB: PowerNet-MIB</p><p>The battery pack serial number.</p> |SNMP |system.sn[upsHighPrecBatteryPackSerialNumber.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Battery status |<p>MIB: PowerNet-MIB</p><p>The status of the UPS batteries. A batteryLow(3) value</p><p> indicates the UPS will be unable to sustain the current</p><p> load, and its services will be lost if power is not restored.</p><p> The amount of run time in reserve at the time of low battery</p><p> can be configured by the upsAdvConfigLowBatteryRunTime.</p><p> A batteryInFaultCondition(4)value indicates that a battery</p><p> installed has an internal error condition.</p> |SNMP |battery.status[upsBasicBatteryStatus]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Battery capacity |<p>MIB: PowerNet-MIB</p><p>The remaining battery capacity expressed as</p><p> percentage of full capacity.</p> |SNMP |battery.capacity[upsHighPrecBatteryCapacity]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Battery runtime remaining |<p>MIB: PowerNet-MIB</p><p>The UPS battery run time remaining before battery</p><p> exhaustion.</p> |SNMP |battery.runtime_remaining[upsAdvBatteryRunTimeRemaining]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Battery voltage |<p>MIB: PowerNet-MIB</p><p>The actual battery bus voltage in Volts.</p> |SNMP |battery.voltage[upsHighPrecBatteryActualVoltage]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Battery last replace date |<p>MIB: PowerNet-MIB</p><p>The date when the UPS system's batteries were last replaced</p><p> in mm/dd/yy (or yyyy) format. For Smart-UPS models, this value</p><p> is originally set at the factory. When the UPS batteries</p><p> are replaced, this value should be reset by the administrator.</p><p> For Symmetra PX 250/500 this OID is read-only and is configurable in the local display only.</p> |SNMP |battery.last_replace_date[upsBasicBatteryLastReplaceDate]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Battery replace indicator |<p>MIB: PowerNet-MIB</p><p>Indicates whether the UPS batteries need replacement.</p> |SNMP |battery.replace_indicator[upsAdvBatteryReplaceIndicator]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |External battery packs count |<p>MIB: PowerNet-MIB</p><p>The number of external battery packs connected to the UPS. If</p><p> the UPS does not use smart cells then the agent reports</p><p> ERROR_NO_SUCH_NAME.</p> |SNMP |battery.external_packs_count[upsAdvBatteryNumOfBattPacks]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Battery temperature |<p>MIB: PowerNet-MIB</p><p>The current internal UPS temperature in Celsius.</p><p> Temperatures below zero read as 0.</p> |SNMP |battery.temperature[upsHighPrecBatteryTemperature]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Input voltage |<p>MIB: PowerNet-MIB</p><p>The current utility line voltage in VAC.</p> |SNMP |input.voltage[upsHighPrecInputLineVoltage]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Input frequency |<p>MIB: PowerNet-MIB</p><p>The current input frequency to the UPS system in Hz.</p> |SNMP |input.frequency[upsHighPrecInputFrequency]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Input fail cause |<p>MIB: PowerNet-MIB</p><p>The reason for the occurrence of the last transfer to UPS</p><p>battery power.  The variable is set to:</p><p>- noTransfer(1) -- if there is no transfer yet.</p><p>- highLineVoltage(2) -- if the transfer to battery is caused</p><p>by an over voltage greater than the high transfer voltage.</p><p>- brownout(3) -- if the duration of the outage is greater than</p><p>five seconds and the line voltage is between 40% of the</p><p>rated output voltage and the low transfer voltage.</p><p>- blackout(4) -- if the duration of the outage is greater than five</p><p>seconds and the line voltage is between 40% of the rated</p><p>output voltage and ground.</p><p>- smallMomentarySag(5) -- if the duration of the outage is less</p><p>than five seconds and the line voltage is between 40% of the</p><p>rated output voltage and the low transfer voltage.</p><p>- deepMomentarySag(6) -- if the duration of the outage is less</p><p>than five seconds and the line voltage is between 40% of the</p><p>rated output voltage and ground.  The variable is set to</p><p>- smallMomentarySpike(7) -- if the line failure is caused by a</p><p>rate of change of input voltage less than ten volts per cycle.</p><p>- largeMomentarySpike(8) -- if the line failure is caused by</p><p>a rate of change of input voltage greater than ten volts per cycle.</p><p>- selfTest(9) -- if the UPS was commanded to do a self test.</p><p>- rateOfVoltageChange(10) -- if the failure is due to the rate of change of</p><p>the line voltage.</p> |SNMP |input.fail[upsAdvInputLineFailCause]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Output voltage |<p>MIB: PowerNet-MIB</p><p>The output voltage of the UPS system in VAC.</p> |SNMP |output.voltage[upsHighPrecOutputVoltage]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Output load |<p>MIB: PowerNet-MIB</p><p>The current UPS load expressed as percentage</p><p>of rated capacity.</p> |SNMP |output.load[upsHighPrecOutputLoad]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Output current |<p>MIB: PowerNet-MIB</p><p>The current in amperes drawn by the load on the UPS.</p> |SNMP |output.current[upsHighPrecOutputCurrent]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Output status |<p>MIB: PowerNet-MIB</p><p>The current state of the UPS. If the UPS is unable to</p><p> determine the state of the UPS this variable is set</p><p> to unknown(1).</p><p>During self-test most UPSes report onBattery(3) but</p><p> some that support it will report onBatteryTest(15).</p><p> To determine self-test status across all UPSes, refer</p><p> to the upsBasicStateOutputState OID.</p> |SNMP |output.status[upsBasicOutputStatus]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Uptime (network) |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management</p><p>portion of the system was last re-initialized.</p> |SNMP |system.net.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized.</p><p>Note that this is different from sysUpTime in the SNMPv2-MIB</p><p>[RFC1907] because sysUpTime is the uptime of the</p><p>network management portion of the system.</p> |SNMP |system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#PHASEINDEX}: Phase input voltage |<p>MIB: PowerNet-MIB</p><p>The input voltage in VAC, or -1 if it's unsupported</p><p> by this UPS.</p> |SNMP |phase.input.voltage[upsPhaseInputVoltage.1.1.{#PHASEINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#PHASEINDEX}: Phase input current |<p>MIB: PowerNet-MIB</p><p>The input current in 0.1 amperes, or -0.1 if it's</p><p> unsupported by this UPS.</p> |SNMP |phase.input.current[upsPhaseInputCurrent.1.1.{#PHASEINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#PHASEINDEX}: Phase output voltage |<p>MIB: PowerNet-MIB</p><p>The output voltage in VAC, or -1 if it's unsupported</p><p> by this UPS.</p> |SNMP |phase.output.voltage[upsPhaseOutputVoltage.1.1.{#PHASEINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#PHASEINDEX}: Phase output current |<p>MIB: PowerNet-MIB</p><p>The output current in 0.1 amperes drawn</p><p> by the load on the UPS, or -1 if it's unsupported</p><p> by this UPS.</p> |SNMP |phase.output.current[upsPhaseOutputCurrent.1.1.{#PHASEINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#PHASEINDEX}: Phase output load, % |<p>MIB: PowerNet-MIB</p><p>The percentage of the UPS load capacity in VA at</p><p> redundancy @ (n + x) presently being used on this</p><p> output phase, or -1 if it's unsupported by this UPS.</p> |SNMP |phase.output.load.percent[upsPhaseOutputPercentLoad.1.1.{#PHASEINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery status |<p>MIB: PowerNet-MIB</p><p>The battery cartridge status.</p><p>bit 0 Disconnected</p><p>bit 1 Overvoltage</p><p>bit 2 NeedsReplacement</p><p>bit 3 OvertemperatureCritical</p><p>bit 4 Charger</p><p>bit 5 TemperatureSensor</p><p>bit 6 BusSoftStart</p><p>bit 7 OvertemperatureWarning</p><p>bit 8 GeneralError</p><p>bit 9 Communication</p><p>bit 10 DisconnectedFrame</p><p>bit 11 FirmwareMismatch</p> |SNMP |battery.pack.status[upsHighPrecBatteryPackCartridgeStatus.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery temperature |<p>MIB: PowerNet-MIB</p><p>The battery pack temperature in Celsius.</p> |SNMP |battery.temperature[upsHighPrecBatteryPackTemperature.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery estimated replace date |<p>MIB: PowerNet-MIB</p><p>The battery cartridge estimated battery replace date.</p> |SNMP |battery.estimated_replace_date[upsHighPrecBatteryPackCartridgeReplaceDate.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery pack cartridge health |<p>MIB: PowerNet-MIB</p><p>The battery cartridge health.</p><p>  bit 0 Battery lifetime okay</p><p>  bit 1 Battery lifetime near end, order replacement cartridge</p><p>  bit 2 Battery lifetime exceeded, replace battery</p><p>  bit 3 Battery lifetime near end acknowledged, order replacement cartridge</p><p>  bit 4 Battery lifetime exceeded acknowledged, replace battery</p><p>  bit 5 Battery measured lifetime near end, order replacement cartridge</p><p>  bit 6 Battery measured lifetime near end acknowledged, order replacement cartridge</p> |SNMP |battery.pack.cartridge_health[upsHighPrecBatteryPackCartridgeHealth.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#SNMPINDEX}: External battery packs bad |<p>MIB: PowerNet-MIB</p><p>The number of external battery packs connected to the UPS that</p><p>are defective. If the UPS does not use smart cells then the</p><p>agent reports ERROR_NO_SUCH_NAME.</p> |SNMP |battery.external_packs_bad[upsAdvBatteryNumOfBadBattPacks.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#EXTERNAL_SENSOR1_NAME}: Temperature sensor |<p>MIB: PowerNet-MIB</p><p>The sensor's current temperature reading in Celsius.</p><p> -1 indicates an invalid reading due to lost communications.</p> |SNMP |external.sensor.temperature[uioSensorStatusTemperatureDegC.1.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#EXTERNAL_SENSOR1_NAME}: Humidity sensor |<p>MIB: PowerNet-MIB</p><p>The sensor's current humidity reading - a relative humidity</p><p> percentage. -1 indicates an invalid reading due to either a</p><p> sensor that doesn't read humidity or lost communications.</p> |SNMP |external.sensor.humidity[uioSensorStatusHumidity.1.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#EXTERNAL_SENSOR1_NAME}: Sensor alarm status |<p>MIB: PowerNet-MIB</p><p>The alarm status of the sensor. Possible values:</p><p>uioNormal                 (1),</p><p>uioWarning                (2),</p><p>uioCritical               (3),</p><p>sensorStatusNotApplicable (4)</p> |SNMP |external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#EXTERNAL_SENSOR2_NAME}: Temperature sensor |<p>MIB: PowerNet-MIB</p><p>The sensor's current temperature reading in Celsius.</p><p> -1 indicates an invalid reading due to lost communications.</p> |SNMP |external.sensor.temperature[uioSensorStatusTemperatureDegC.2.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#EXTERNAL_SENSOR2_NAME}: Humidity sensor |<p>MIB: PowerNet-MIB</p><p>The sensor's current humidity reading - a relative humidity</p><p> percentage. -1 indicates an invalid reading due to either a</p><p> sensor that doesn't read humidity or lost communications.</p> |SNMP |external.sensor.humidity[uioSensorStatusHumidity.2.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |{#EXTERNAL_SENSOR2_NAME}: Sensor alarm status |<p>MIB: PowerNet-MIB</p><p>The alarm status of the sensor. Possible values:</p><p>uioNormal                 (1),</p><p>uioWarning                (2),</p><p>uioCritical               (3),</p><p>sensorStatusNotApplicable (4)</p> |SNMP |external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/system.name[sysName.0],#1)<>last(/APC Smart-UPS 3000 XLM by SNMP/system.name[sysName.0],#2) and length(last(/APC Smart-UPS 3000 XLM by SNMP/system.name[sysName.0]))>0` |INFO |<p>Manual close: YES</p> |
|Battery has an internal error condition |<p>A battery installed has an internal error condition.</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/battery.status[upsBasicBatteryStatus])=4` |AVERAGE | |
|Battery is Low |<p>The UPS will be unable to sustain the current load, and its services will be lost if power is not restored.</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/battery.status[upsBasicBatteryStatus])=3` |AVERAGE | |
|Battery has low capacity |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/battery.capacity[upsHighPrecBatteryCapacity]) < {$BATTERY.CAPACITY.MIN.WARN}` |HIGH | |
|Battery needs replacement |<p>A battery installed has an internal error condition.</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/battery.replace_indicator[upsAdvBatteryReplaceIndicator])=2` |HIGH | |
|Battery has high temperature |<p>-</p> |`min(/APC Smart-UPS 3000 XLM by SNMP/battery.temperature[upsHighPrecBatteryTemperature],{$TIME.PERIOD}) > {$BATTERY.TEMP.MAX.WARN}` |HIGH | |
|Unacceptable input voltage |<p>-</p> |`min(/APC Smart-UPS 3000 XLM by SNMP/input.voltage[upsHighPrecInputLineVoltage],{$TIME.PERIOD}) > 0 and (min(/APC Smart-UPS 3000 XLM by SNMP/input.voltage[upsHighPrecInputLineVoltage],{$TIME.PERIOD}) > {$UPS.INPUT_VOLT.MAX.WARN} or max(/APC Smart-UPS 3000 XLM by SNMP/input.voltage[upsHighPrecInputLineVoltage],{$TIME.PERIOD}) < {$UPS.INPUT_VOLT.MIN.WARN})` |HIGH | |
|Unacceptable input frequency |<p>-</p> |`min(/APC Smart-UPS 3000 XLM by SNMP/input.frequency[upsHighPrecInputFrequency],{$TIME.PERIOD}) > 0 and (min(/APC Smart-UPS 3000 XLM by SNMP/input.frequency[upsHighPrecInputFrequency],{$TIME.PERIOD}) > {$UPS.INPUT_FREQ.MAX.WARN} or max(/APC Smart-UPS 3000 XLM by SNMP/input.frequency[upsHighPrecInputFrequency],{$TIME.PERIOD}) < {$UPS.INPUT_FREQ.MIN.WARN})` |HIGH | |
|Output load is high |<p>A battery installed has an internal error condition.</p> |`min(/APC Smart-UPS 3000 XLM by SNMP/output.load[upsHighPrecOutputLoad],{$TIME.PERIOD}) > {$UPS.OUTPUT.MAX.WARN}` |HIGH | |
|UPS is Timed Sleeping |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=5` |AVERAGE | |
|UPS is Switched Bypass |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=9` |AVERAGE | |
|UPS is Software Bypass |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=6` |AVERAGE | |
|UPS is Sleeping Until Power Return |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=11` |AVERAGE | |
|UPS is Rebooting |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=8` |AVERAGE | |
|UPS is On Smart Trim |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=12` |AVERAGE | |
|UPS is on Smart Boost |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=4` |AVERAGE | |
|UPS is on battery |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=3` |AVERAGE | |
|UPS is Off |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=7` |AVERAGE | |
|UPS is Emergency Static Bypass |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=16` |AVERAGE | |
|UPS is Hardware Failure Bypass |<p>-</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/output.status[upsBasicOutputStatus])=10` |AVERAGE | |
|Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/APC Smart-UPS 3000 XLM by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/APC Smart-UPS 3000 XLM by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/APC Smart-UPS 3000 XLM by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/APC Smart-UPS 3000 XLM by SNMP/system.net.uptime[sysUpTime.0])<10m)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/APC Smart-UPS 3000 XLM by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING | |
|{#PHASEINDEX}: Unacceptable phase {#PHASEINDEX} input voltage |<p>-</p> |`min(/APC Smart-UPS 3000 XLM by SNMP/phase.input.voltage[upsPhaseInputVoltage.1.1.{#PHASEINDEX}],{$TIME.PERIOD}) > {$UPS.INPUT_VOLT.MAX.WARN} or max(/APC Smart-UPS 3000 XLM by SNMP/phase.input.voltage[upsPhaseInputVoltage.1.1.{#PHASEINDEX}],{$TIME.PERIOD}) < {$UPS.INPUT_VOLT.MIN.WARN}` |HIGH | |
|{#PHASEINDEX}: Unacceptable phase {#PHASEINDEX} output voltage |<p>-</p> |`min(/APC Smart-UPS 3000 XLM by SNMP/phase.output.voltage[upsPhaseOutputVoltage.1.1.{#PHASEINDEX}],{$TIME.PERIOD}) > {$UPS.INPUT_VOLT.MAX.WARN} or max(/APC Smart-UPS 3000 XLM by SNMP/phase.output.voltage[upsPhaseOutputVoltage.1.1.{#PHASEINDEX}],{$TIME.PERIOD}) < {$UPS.INPUT_VOLT.MIN.WARN}` |HIGH | |
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery status is not okay |<p>The battery cartridge status:</p><p>bit 0 Disconnected</p><p>bit 1 Overvoltage</p><p>bit 2 NeedsReplacement</p><p>bit 3 OvertemperatureCritical</p><p>bit 4 Charger</p><p>bit 5 TemperatureSensor</p><p>bit 6 BusSoftStart</p><p>bit 7 OvertemperatureWarning</p><p>bit 8 GeneralError</p><p>bit 9 Communication</p><p>bit 10 DisconnectedFrame</p><p>bit 11 FirmwareMismatch</p> |`find(/APC Smart-UPS 3000 XLM by SNMP/battery.pack.status[upsHighPrecBatteryPackCartridgeStatus.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}],,"regexp","^(0{16})$")=0` |WARNING | |
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery has high temperature |<p>-</p> |`min(/APC Smart-UPS 3000 XLM by SNMP/battery.temperature[upsHighPrecBatteryPackTemperature.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}],{$TIME.PERIOD}) > {$BATTERY.TEMP.MAX.WARN}` |HIGH | |
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery lifetime is not okay |<p>The battery cartridge health.</p><p>  bit 0 Battery lifetime okay</p><p>  bit 1 Battery lifetime near end, order replacement cartridge</p><p>  bit 2 Battery lifetime exceeded, replace battery</p><p>  bit 3 Battery lifetime near end acknowledged, order replacement cartridge</p><p>  bit 4 Battery lifetime exceeded acknowledged, replace battery</p><p>  bit 5 Battery measured lifetime near end, order replacement cartridge</p><p>  bit 6 Battery measured lifetime near end acknowledged, order replacement cartridge</p> |`find(/APC Smart-UPS 3000 XLM by SNMP/battery.pack.cartridge_health[upsHighPrecBatteryPackCartridgeHealth.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}],,"regexp","^(0)[0|1]{15}$")=1` |WARNING | |
|{#EXTERNAL_SENSOR1_NAME}: Sensor has status Not Applicable |<p>The external sensor does not work or is not connected.</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}])=4` |INFO | |
|{#EXTERNAL_SENSOR1_NAME}: Sensor has status Warning |<p>The external sensor has returned a value greater than the warning threshold.</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}])=2` |AVERAGE | |
|{#EXTERNAL_SENSOR1_NAME}: Sensor has status Critical |<p>The external sensor has returned a value greater than the critical threshold.</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}])=3` |HIGH | |
|{#EXTERNAL_SENSOR2_NAME}: Sensor has status Not Applicable |<p>The external sensor does not work or is not connected.</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}])=4` |INFO | |
|{#EXTERNAL_SENSOR2_NAME}: Sensor has status Warning |<p>The external sensor has returned a value greater than the warning threshold.</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}])=2` |AVERAGE | |
|{#EXTERNAL_SENSOR2_NAME}: Sensor has status Critical |<p>The external sensor has returned a value greater than the critical threshold.</p> |`last(/APC Smart-UPS 3000 XLM by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}])=3` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/420730-discussion-thread-for-official-zabbix-template-apc-ups).

