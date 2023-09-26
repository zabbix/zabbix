
# APC UPS Galaxy 3500 by SNMP

## Overview

The template to monitor APC UPS Galaxy 3500 by Zabbix SNMP agent.
Note: please, use the latest version of the firmware for your NMC in order for the template to work correctly.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- APC UPS Galaxy 3500

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1\. Create a host for APC UPS Galaxy 3500 management IP as SNMPv2 interface.

2\. Link the template to the host.

3\. Customize macro values if needed.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$BATTERY.TEMP.MAX.WARN}|<p>Maximum battery temperature for trigger expression.</p>|`55`|
|{$BATTERY.CAPACITY.MIN.WARN}|<p>Minimum battery capacity percentage for trigger expression.</p>|`50`|
|{$UPS.OUTPUT.MAX.WARN}|<p>Maximum output load in % for trigger expression.</p>|`80`|
|{$UPS.INPUT_FREQ.MIN.WARN}|<p>Minimum input frequency for trigger expression.</p>|`49.7`|
|{$UPS.INPUT_FREQ.MAX.WARN}|<p>Maximum input frequency for trigger expression.</p>|`50.3`|
|{$UPS.INPUT_VOLT.MIN.WARN}|<p>Minimum input voltage for trigger expression.</p>|`197`|
|{$UPS.INPUT_VOLT.MAX.WARN}|<p>Maximum input voltage for trigger expression.</p>|`243`|
|{$TIME.PERIOD}|<p>Time period for trigger expression.</p>|`15m`|
|{$SNMP.TIMEOUT}|<p>The time interval for SNMP agent availability trigger expression.</p>|`5m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|APC UPS Galaxy 3500: Model|<p>MIB: PowerNet-MIB</p><p>The UPS model name (e.g. 'APC Smart-UPS 600').</p>|SNMP agent|system.model[upsBasicIdentModel]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Serial number|<p>MIB: PowerNet-MIB</p><p>An 8-character string identifying the serial number of</p><p> the UPS internal microprocessor.  This number is set at</p><p> the factory.  NOTE: This number does NOT correspond to</p><p> the serial number on the rear of the UPS.</p>|SNMP agent|system.sn[upsAdvIdentSerialNumber]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Battery status|<p>MIB: PowerNet-MIB</p><p>The status of the UPS batteries. A batteryLow(3) value</p><p> indicates the UPS will be unable to sustain the current</p><p> load, and its services will be lost if power is not restored.</p><p> The amount of run time in reserve at the time of low battery</p><p> can be configured by the upsAdvConfigLowBatteryRunTime.</p><p> A batteryInFaultCondition(4)value indicates that a battery</p><p> installed has an internal error condition.</p>|SNMP agent|battery.status[upsBasicBatteryStatus]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Battery capacity|<p>MIB: PowerNet-MIB</p><p>The remaining battery capacity expressed as</p><p> percentage of full capacity.</p>|SNMP agent|battery.capacity[upsHighPrecBatteryCapacity]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Battery runtime remaining|<p>MIB: PowerNet-MIB</p><p>The UPS battery run time remaining before battery</p><p> exhaustion.</p>|SNMP agent|battery.runtime_remaining[upsAdvBatteryRunTimeRemaining]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Battery voltage|<p>MIB: PowerNet-MIB</p><p>The actual battery bus voltage in Volts.</p>|SNMP agent|battery.voltage[upsHighPrecBatteryActualVoltage]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Battery last replace date|<p>MIB: PowerNet-MIB</p><p>The date when the UPS system's batteries were last replaced</p><p> in mm/dd/yy (or yyyy) format. For Smart-UPS models, this value</p><p> is originally set at the factory. When the UPS batteries</p><p> are replaced, this value should be reset by the administrator.</p><p> For Symmetra PX 250/500 this OID is read-only and is configurable in the local display only.</p>|SNMP agent|battery.last_replace_date[upsBasicBatteryLastReplaceDate]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Battery replace indicator|<p>MIB: PowerNet-MIB</p><p>Indicates whether the UPS batteries need replacement.</p>|SNMP agent|battery.replace_indicator[upsAdvBatteryReplaceIndicator]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: External battery packs count|<p>MIB: PowerNet-MIB</p><p>The number of external battery packs connected to the UPS. If</p><p> the UPS does not use smart cells then the agent reports</p><p> ERROR_NO_SUCH_NAME.</p>|SNMP agent|battery.external_packs_count[upsAdvBatteryNumOfBattPacks]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Battery temperature|<p>MIB: PowerNet-MIB</p><p>The current internal UPS temperature in Celsius.</p><p>Temperatures below zero read as 0.</p>|SNMP agent|battery.temperature[upsHighPrecBatteryTemperature]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Input voltage|<p>MIB: PowerNet-MIB</p><p>The current utility line voltage in VAC.</p>|SNMP agent|input.voltage[upsHighPrecInputLineVoltage]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Input frequency|<p>MIB: PowerNet-MIB</p><p>The current input frequency to the UPS system in Hz.</p>|SNMP agent|input.frequency[upsHighPrecInputFrequency]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Input fail cause|<p>MIB: PowerNet-MIB</p><p>The reason for the occurrence of the last transfer to UPS</p><p>battery power.  The variable is set to:</p><p>- noTransfer(1) -- if there is no transfer yet.</p><p>- highLineVoltage(2) -- if the transfer to battery is caused</p><p>by an over voltage greater than the high transfer voltage.</p><p>- brownout(3) -- if the duration of the outage is greater than</p><p>five seconds and the line voltage is between 40% of the</p><p>rated output voltage and the low transfer voltage.</p><p>- blackout(4) -- if the duration of the outage is greater than five</p><p>seconds and the line voltage is between 40% of the rated</p><p>output voltage and ground.</p><p>- smallMomentarySag(5) -- if the duration of the outage is less</p><p>than five seconds and the line voltage is between 40% of the</p><p>rated output voltage and the low transfer voltage.</p><p>- deepMomentarySag(6) -- if the duration of the outage is less</p><p>than five seconds and the line voltage is between 40% of the</p><p>rated output voltage and ground.  The variable is set to</p><p>- smallMomentarySpike(7) -- if the line failure is caused by a</p><p>rate of change of input voltage less than ten volts per cycle.</p><p>- largeMomentarySpike(8) -- if the line failure is caused by</p><p>a rate of change of input voltage greater than ten volts per cycle.</p><p>- selfTest(9) -- if the UPS was commanded to do a self test.</p><p>- rateOfVoltageChange(10) -- if the failure is due to the rate of change of</p><p>the line voltage.</p>|SNMP agent|input.fail[upsAdvInputLineFailCause]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Output voltage|<p>MIB: PowerNet-MIB</p><p>The output voltage of the UPS system in VAC.</p>|SNMP agent|output.voltage[upsHighPrecOutputVoltage]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Output load|<p>MIB: PowerNet-MIB</p><p>The current UPS load expressed as percentage</p><p>of rated capacity.</p>|SNMP agent|output.load[upsHighPrecOutputLoad]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Output current|<p>MIB: PowerNet-MIB</p><p>The current in amperes drawn by the load on the UPS.</p>|SNMP agent|output.current[upsHighPrecOutputCurrent]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Output status|<p>MIB: PowerNet-MIB</p><p>The current state of the UPS. If the UPS is unable to</p><p> determine the state of the UPS this variable is set</p><p> to unknown(1).</p><p>During self-test most UPSes report onBattery(3) but</p><p> some that support it will report onBatteryTest(15).</p><p> To determine self-test status across all UPSes, refer</p><p> to the upsBasicStateOutputState OID.</p>|SNMP agent|output.status[upsBasicOutputStatus]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|APC UPS Galaxy 3500: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|APC UPS Galaxy 3500: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|APC UPS Galaxy 3500: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed</p><p>node, together with information on how to contact this person.  If no contact</p><p>information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|APC UPS Galaxy 3500: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name[sysName.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|APC UPS Galaxy 3500: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|APC UPS Galaxy 3500: SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|APC UPS Galaxy 3500: Battery has an internal error condition|<p>A battery installed has an internal error condition.</p>|`last(/APC UPS Galaxy 3500 by SNMP/battery.status[upsBasicBatteryStatus])=4`|Average||
|APC UPS Galaxy 3500: Battery is Low|<p>The UPS will be unable to sustain the current load, and its services will be lost if power is not restored.</p>|`last(/APC UPS Galaxy 3500 by SNMP/battery.status[upsBasicBatteryStatus])=3`|Average||
|APC UPS Galaxy 3500: Battery has low capacity||`last(/APC UPS Galaxy 3500 by SNMP/battery.capacity[upsHighPrecBatteryCapacity]) < {$BATTERY.CAPACITY.MIN.WARN}`|High||
|APC UPS Galaxy 3500: Battery needs replacement|<p>A battery installed has an internal error condition.</p>|`last(/APC UPS Galaxy 3500 by SNMP/battery.replace_indicator[upsAdvBatteryReplaceIndicator])=2`|High||
|APC UPS Galaxy 3500: Battery has high temperature||`min(/APC UPS Galaxy 3500 by SNMP/battery.temperature[upsHighPrecBatteryTemperature],{$TIME.PERIOD}) > {$BATTERY.TEMP.MAX.WARN}`|High||
|APC UPS Galaxy 3500: Unacceptable input voltage||`min(/APC UPS Galaxy 3500 by SNMP/input.voltage[upsHighPrecInputLineVoltage],{$TIME.PERIOD}) > 0 and (min(/APC UPS Galaxy 3500 by SNMP/input.voltage[upsHighPrecInputLineVoltage],{$TIME.PERIOD}) > {$UPS.INPUT_VOLT.MAX.WARN} or max(/APC UPS Galaxy 3500 by SNMP/input.voltage[upsHighPrecInputLineVoltage],{$TIME.PERIOD}) < {$UPS.INPUT_VOLT.MIN.WARN})`|High||
|APC UPS Galaxy 3500: Unacceptable input frequency||`min(/APC UPS Galaxy 3500 by SNMP/input.frequency[upsHighPrecInputFrequency],{$TIME.PERIOD}) > 0 and (min(/APC UPS Galaxy 3500 by SNMP/input.frequency[upsHighPrecInputFrequency],{$TIME.PERIOD}) > {$UPS.INPUT_FREQ.MAX.WARN} or max(/APC UPS Galaxy 3500 by SNMP/input.frequency[upsHighPrecInputFrequency],{$TIME.PERIOD}) < {$UPS.INPUT_FREQ.MIN.WARN})`|High||
|APC UPS Galaxy 3500: Output load is high|<p>A battery installed has an internal error condition.</p>|`min(/APC UPS Galaxy 3500 by SNMP/output.load[upsHighPrecOutputLoad],{$TIME.PERIOD}) > {$UPS.OUTPUT.MAX.WARN}`|High||
|APC UPS Galaxy 3500: UPS is Timed Sleeping||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=5`|Average||
|APC UPS Galaxy 3500: UPS is Switched Bypass||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=9`|Average||
|APC UPS Galaxy 3500: UPS is Software Bypass||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=6`|Average||
|APC UPS Galaxy 3500: UPS is Sleeping Until Power Return||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=11`|Average||
|APC UPS Galaxy 3500: UPS is Rebooting||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=8`|Average||
|APC UPS Galaxy 3500: UPS is On Smart Trim||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=12`|Average||
|APC UPS Galaxy 3500: UPS is on Smart Boost||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=4`|Average||
|APC UPS Galaxy 3500: UPS is on battery||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=3`|Average||
|APC UPS Galaxy 3500: UPS is Off||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=7`|Average||
|APC UPS Galaxy 3500: UPS is Emergency Static Bypass||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=16`|Average||
|APC UPS Galaxy 3500: UPS is Hardware Failure Bypass||`last(/APC UPS Galaxy 3500 by SNMP/output.status[upsBasicOutputStatus])=10`|Average||
|APC UPS Galaxy 3500: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/APC UPS Galaxy 3500 by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/APC UPS Galaxy 3500 by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/APC UPS Galaxy 3500 by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/APC UPS Galaxy 3500 by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>APC UPS Galaxy 3500: No SNMP data collection</li></ul>|
|APC UPS Galaxy 3500: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/APC UPS Galaxy 3500 by SNMP/system.name[sysName.0],#1)<>last(/APC UPS Galaxy 3500 by SNMP/system.name[sysName.0],#2) and length(last(/APC UPS Galaxy 3500 by SNMP/system.name[sysName.0]))>0`|Info|**Manual close**: Yes|
|APC UPS Galaxy 3500: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/APC UPS Galaxy 3500 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning||

### LLD rule Input phases discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Input phases discovery|<p>The input phase identifier. OID upsPhaseInputPhaseIndex.1.1</p>|SNMP agent|input.phases.discovery|

### Item prototypes for Input phases discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#PHASEINDEX}: Phase input voltage|<p>MIB: PowerNet-MIB</p><p>The input voltage in VAC, or -1 if it's unsupported</p><p> by this UPS.</p>|SNMP agent|phase.input.voltage[upsPhaseInputVoltage.1.1.{#PHASEINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#PHASEINDEX}: Phase input current|<p>MIB: PowerNet-MIB</p><p>The input current in 0.1 amperes, or -0.1 if it's</p><p> unsupported by this UPS.</p>|SNMP agent|phase.input.current[upsPhaseInputCurrent.1.1.{#PHASEINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Input phases discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#PHASEINDEX}: Unacceptable phase {#PHASEINDEX} input voltage||`min(/APC UPS Galaxy 3500 by SNMP/phase.input.voltage[upsPhaseInputVoltage.1.1.{#PHASEINDEX}],{$TIME.PERIOD}) > {$UPS.INPUT_VOLT.MAX.WARN} or max(/APC UPS Galaxy 3500 by SNMP/phase.input.voltage[upsPhaseInputVoltage.1.1.{#PHASEINDEX}],{$TIME.PERIOD}) < {$UPS.INPUT_VOLT.MIN.WARN}`|High||

### LLD rule Output phases discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Output phases discovery|<p>The output phase identifier. OID upsPhaseOutputPhaseIndex.1.1</p>|SNMP agent|output.phases.discovery|

### Item prototypes for Output phases discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#PHASEINDEX}: Phase output voltage|<p>MIB: PowerNet-MIB</p><p>The output voltage in VAC, or -1 if it's unsupported</p><p>by this UPS.</p>|SNMP agent|phase.output.voltage[upsPhaseOutputVoltage.1.1.{#PHASEINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#PHASEINDEX}: Phase output current|<p>MIB: PowerNet-MIB</p><p>The output current in 0.1 amperes drawn</p><p> by the load on the UPS, or -1 if it's unsupported</p><p> by this UPS.</p>|SNMP agent|phase.output.current[upsPhaseOutputCurrent.1.1.{#PHASEINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#PHASEINDEX}: Phase output load, %|<p>MIB: PowerNet-MIB</p><p>The percentage of the UPS load capacity in VA at</p><p> redundancy @ (n + x) presently being used on this</p><p> output phase, or -1 if it's unsupported by this UPS.</p>|SNMP agent|phase.output.load.percent[upsPhaseOutputPercentLoad.1.1.{#PHASEINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Output phases discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#PHASEINDEX}: Unacceptable phase {#PHASEINDEX} output voltage||`min(/APC UPS Galaxy 3500 by SNMP/phase.output.voltage[upsPhaseOutputVoltage.1.1.{#PHASEINDEX}],{$TIME.PERIOD}) > {$UPS.INPUT_VOLT.MAX.WARN} or max(/APC UPS Galaxy 3500 by SNMP/phase.output.voltage[upsPhaseOutputVoltage.1.1.{#PHASEINDEX}],{$TIME.PERIOD}) < {$UPS.INPUT_VOLT.MIN.WARN}`|High||

### LLD rule External battery packs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|External battery packs discovery||SNMP agent|battery.packs.discovery|

### Item prototypes for External battery packs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery status|<p>MIB: PowerNet-MIB</p><p>The status of the UPS batteries. A batteryLow(3) value</p><p> indicates the UPS will be unable to sustain the current</p><p> load, and its services will be lost if power is not restored.</p><p> The amount of run time in reserve at the time of low battery</p><p> can be configured by the upsAdvConfigLowBatteryRunTime.</p><p> A batteryInFaultCondition(4)value indicates that a battery</p><p> installed has an internal error condition.</p>|SNMP agent|battery.pack.status[upsHighPrecBatteryPackCartridgeStatus.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery temperature|<p>MIB: PowerNet-MIB</p><p>The current internal UPS temperature in Celsius.</p><p>Temperatures below zero read as 0.</p>|SNMP agent|battery.temperature[upsHighPrecBatteryPackTemperature.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Serial number|<p>MIB: PowerNet-MIB</p><p>An 8-character string identifying the serial number of</p><p> the UPS internal microprocessor.  This number is set at</p><p> the factory.  NOTE: This number does NOT correspond to</p><p> the serial number on the rear of the UPS.</p>|SNMP agent|system.sn[upsHighPrecBatteryPackSerialNumber.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery estimated replace date|<p>MIB: PowerNet-MIB</p><p>The battery cartridge estimated battery replace date.</p>|SNMP agent|battery.estimated_replace_date[upsHighPrecBatteryPackCartridgeReplaceDate.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery pack cartridge health|<p>MIB: PowerNet-MIB</p><p>The battery cartridge health.</p><p>  bit 0 Battery lifetime okay</p><p>  bit 1 Battery lifetime near end, order replacement cartridge</p><p>  bit 2 Battery lifetime exceeded, replace battery</p><p>  bit 3 Battery lifetime near end acknowledged, order replacement cartridge</p><p>  bit 4 Battery lifetime exceeded acknowledged, replace battery</p><p>  bit 5 Battery measured lifetime near end, order replacement cartridge</p><p>  bit 6 Battery measured lifetime near end acknowledged, order replacement cartridge</p>|SNMP agent|battery.pack.cartridge_health[upsHighPrecBatteryPackCartridgeHealth.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for External battery packs discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery status is not okay|<p>The battery cartridge status:<br>bit 0 Disconnected<br>bit 1 Overvoltage<br>bit 2 NeedsReplacement<br>bit 3 OvertemperatureCritical<br>bit 4 Charger<br>bit 5 TemperatureSensor<br>bit 6 BusSoftStart<br>bit 7 OvertemperatureWarning<br>bit 8 GeneralError<br>bit 9 Communication<br>bit 10 DisconnectedFrame<br>bit 11 FirmwareMismatch</p>|`find(/APC UPS Galaxy 3500 by SNMP/battery.pack.status[upsHighPrecBatteryPackCartridgeStatus.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}],,"regexp","^(0{16})$")=0`|Warning||
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery has high temperature||`min(/APC UPS Galaxy 3500 by SNMP/battery.temperature[upsHighPrecBatteryPackTemperature.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}],{$TIME.PERIOD}) > {$BATTERY.TEMP.MAX.WARN}`|High||
|{#BATTERY_PACK}.{#CARTRIDGE_INDEX}: Battery lifetime is not okay|<p>The battery cartridge health.<br>  bit 0 Battery lifetime okay<br>  bit 1 Battery lifetime near end, order replacement cartridge<br>  bit 2 Battery lifetime exceeded, replace battery<br>  bit 3 Battery lifetime near end acknowledged, order replacement cartridge<br>  bit 4 Battery lifetime exceeded acknowledged, replace battery<br>  bit 5 Battery measured lifetime near end, order replacement cartridge<br>  bit 6 Battery measured lifetime near end acknowledged, order replacement cartridge</p>|`find(/APC UPS Galaxy 3500 by SNMP/battery.pack.cartridge_health[upsHighPrecBatteryPackCartridgeHealth.{#BATTERY_PACK}.{#CARTRIDGE_INDEX}],,"regexp","^(0)[0\|1]{15}$")=1`|Warning||

### LLD rule External bad battery packs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|External bad battery packs discovery|<p>Discovery of the number of external defective battery packs.</p>|SNMP agent|battery.packs.bad.discovery|

### Item prototypes for External bad battery packs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPINDEX}: External battery packs bad|<p>MIB: PowerNet-MIB</p><p>The number of external battery packs connected to the UPS that</p><p>are defective. If the UPS does not use smart cells then the</p><p>agent reports ERROR_NO_SUCH_NAME.</p>|SNMP agent|battery.external_packs_bad[upsAdvBatteryNumOfBadBattPacks.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### LLD rule External sensor port 1 discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|External sensor port 1 discovery|<p>uioSensorStatusTable</p>|SNMP agent|external.sensor1.discovery|

### Item prototypes for External sensor port 1 discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#EXTERNAL_SENSOR1_NAME}: Temperature sensor|<p>MIB: PowerNet-MIB</p><p>The sensor's current temperature reading in Celsius.</p><p> -1 indicates an invalid reading due to lost communications.</p>|SNMP agent|external.sensor.temperature[uioSensorStatusTemperatureDegC.1.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#EXTERNAL_SENSOR1_NAME}: Humidity sensor|<p>MIB: PowerNet-MIB</p><p>The sensor's current humidity reading - a relative humidity</p><p> percentage. -1 indicates an invalid reading due to either a</p><p> sensor that doesn't read humidity or lost communications.</p>|SNMP agent|external.sensor.humidity[uioSensorStatusHumidity.1.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#EXTERNAL_SENSOR1_NAME}: Sensor alarm status|<p>MIB: PowerNet-MIB</p><p>The alarm status of the sensor. Possible values:</p><p>uioNormal                 (1),</p><p>uioWarning                (2),</p><p>uioCritical               (3),</p><p>sensorStatusNotApplicable (4)</p>|SNMP agent|external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for External sensor port 1 discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#EXTERNAL_SENSOR1_NAME}: Sensor has status Not Applicable|<p>The external sensor does not work or is not connected.</p>|`last(/APC UPS Galaxy 3500 by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}])=4`|Info||
|{#EXTERNAL_SENSOR1_NAME}: Sensor has status Warning|<p>The external sensor has returned a value greater than the warning threshold.</p>|`last(/APC UPS Galaxy 3500 by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}])=2`|Average||
|{#EXTERNAL_SENSOR1_NAME}: Sensor has status Critical|<p>The external sensor has returned a value greater than the critical threshold.</p>|`last(/APC UPS Galaxy 3500 by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.1.{#SNMPINDEX}])=3`|High||

### LLD rule External sensor port 2 discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|External sensor port 2 discovery|<p>uioSensorStatusTable</p>|SNMP agent|external.sensor2.discovery|

### Item prototypes for External sensor port 2 discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#EXTERNAL_SENSOR2_NAME}: Temperature sensor|<p>MIB: PowerNet-MIB</p><p>The sensor's current temperature reading in Celsius.</p><p> -1 indicates an invalid reading due to lost communications.</p>|SNMP agent|external.sensor.temperature[uioSensorStatusTemperatureDegC.2.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#EXTERNAL_SENSOR2_NAME}: Humidity sensor|<p>MIB: PowerNet-MIB</p><p>The sensor's current humidity reading - a relative humidity</p><p> percentage. -1 indicates an invalid reading due to either a</p><p> sensor that doesn't read humidity or lost communications.</p>|SNMP agent|external.sensor.humidity[uioSensorStatusHumidity.2.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#EXTERNAL_SENSOR2_NAME}: Sensor alarm status|<p>MIB: PowerNet-MIB</p><p>The alarm status of the sensor. Possible values:</p><p>uioNormal                 (1),</p><p>uioWarning                (2),</p><p>uioCritical               (3),</p><p>sensorStatusNotApplicable (4)</p>|SNMP agent|external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for External sensor port 2 discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#EXTERNAL_SENSOR2_NAME}: Sensor has status Not Applicable|<p>The external sensor does not work or is not connected.</p>|`last(/APC UPS Galaxy 3500 by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}])=4`|Info||
|{#EXTERNAL_SENSOR2_NAME}: Sensor has status Warning|<p>The external sensor has returned a value greater than the warning threshold.</p>|`last(/APC UPS Galaxy 3500 by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}])=2`|Average||
|{#EXTERNAL_SENSOR2_NAME}: Sensor has status Critical|<p>The external sensor has returned a value greater than the critical threshold.</p>|`last(/APC UPS Galaxy 3500 by SNMP/external.sensor.status[uioSensorStatusAlarmStatus.2.{#SNMPINDEX}])=3`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

