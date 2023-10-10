
# ZYXEL IES-500x by SNMP

## Overview

https://service-provider.zyxel.com/global/en/products/msansdslams/central-msans/chassis-msans/ies-5000-series

### Known Issues

Description: Incorrect handling of SNMP bulk requests. Disable the use of bulk requests in the SNMP interface settings.
Version: all versions firmware
Device: ZYXEL IES-500x

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- ZYXEL IES-500x

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ZYXEL.LLD.FILTER.IF.CONTROL.MATCHES}|<p>Triggers will be created only for interfaces whose description contains the value of this macro</p>|`CHANGE_IF_NEEDED`|
|{$SNMP.TIMEOUT}|<p>The time interval for SNMP agent availability trigger expression.</p>|`5m`|
|{$ZYXEL.ADSL.SNR.MIN}|<p>Type the minimum signal to noise margin (0-31 dB)</p>|`8`|
|{$ZYXEL.ADSL.ATN.MAX}|<p>Type the maximum signal attenuation</p>|`40`|
|{$ZYXEL.LLD.FILTER.IF.DESC.MATCHES}|<p>Filter by discoverable interface names.</p>|`.*`|
|{$ZYXEL.LLD.FILTER.IF.DESC.NOT_MATCHES}|<p>Filter to exclude discovered interfaces by name.</p>|`CHANGE_IF_NEEDED`|
|{$ZYXEL.LLD.FILTER.SLOT.STATUS.MATCHES}|<p>Filter by discoverable slot status.</p>|`.*`|
|{$ZYXEL.LLD.FILTER.SLOT.STATUS.NOT_MATCHES}|<p>Filter to exclude discovered slots by status.</p>|`1`|
|{$ZYXEL.LLD.FILTER.IF.LINKSTATUS.MATCHES}|<p>Filter of discoverable link types.</p>|`.*`|
|{$ZYXEL.LLD.FILTER.IF.LINKSTATUS.NOT_MATCHES}|<p>Filter to exclude discovered by link types.</p>|`2`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES-500x: SNMP agent availability||Zabbix internal|zabbix[host,snmp,available]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES-500x: Hardware model name|<p>MIB: RFC1213-MIB</p><p>A textual description of the entity.  This value</p><p>should include the full name and version</p><p>identification of the system's hardware type,</p><p>software operating-system, and networking</p><p>software.  It is mandatory that this only contain</p><p>printable ASCII characters.</p>|SNMP agent|zyxel.ies500x.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Contact|<p>MIB: RFC1213-MIB</p><p>The textual identification of the contact person</p><p>for this managed node, together with information</p><p>on how to contact this person.</p>|SNMP agent|zyxel.ies500x.contact<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Host name|<p>MIB: RFC1213-MIB</p><p>An administratively-assigned name for this</p><p>managed node.  By convention, this is the node's</p><p>fully-qualified domain name.</p>|SNMP agent|zyxel.ies500x.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Location|<p>MIB: RFC1213-MIB</p><p>The physical location of this node (e.g.,</p><p>`telephone closet, 3rd floor').</p>|SNMP agent|zyxel.ies500x.location<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: MAC address|<p>MIB: IF-MIB</p><p>The interface's address at the protocol layer</p><p>immediately `below' the network layer in the</p><p>protocol stack.  For interfaces which do not have</p><p>such an address (e.g., a serial line), this object</p><p>should contain an octet string of zero length.</p>|SNMP agent|zyxel.ies500x.mac<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Uptime (network)|<p>MIB: RFC1213-MIB</p><p>The time (in hundredths of a second) since the</p><p>network management portion of the system was last</p><p>re-initialized.</p>|SNMP agent|zyxel.ies500x.net.uptime<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|ZYXEL IES-500x: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized.</p><p>Note that this is different from sysUpTime in the SNMPv2-MIB</p><p>[RFC1907] because sysUpTime is the uptime of the</p><p>network management portion of the system.</p>|SNMP agent|zyxel.ies500x.hw.uptime<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES-500x: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/ZYXEL IES-500x by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning||
|ZYXEL IES-500x: Template does not match hardware|<p>This template is for Zyxel IES-500x, but connected to {ITEM.VALUE}</p>|`not(last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.model)="IES-5000" or last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.model)="IES-5005")`|Info|**Manual close**: Yes|
|ZYXEL IES-500x: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.hw.uptime)>0 and last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.hw.uptime)<10m) or (last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.hw.uptime)=0 and last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.uptime)<10m)`|Info|**Manual close**: Yes|

### LLD rule Slot discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Slot discovery|<p>The table which contains the slot information in a chassis.</p>|SNMP agent|zyxel.ies500x.slot.discovery|

### Item prototypes for Slot discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Type|<p>MIB: ZYXEL-IES5000-MIB</p><p>Card type of the plug-in card.</p>|SNMP agent|zyxel.ies500x.slot.type[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Description|<p>MIB: ZYXEL-IES5000-MIB</p><p>The descriptions of the plug-in card.</p>|SNMP agent|zyxel.ies500x.slot.desc[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Firmware version|<p>MIB: ZYXEL-IES5000-MIB</p><p>The firmware version of the plug-in card.</p>|SNMP agent|zyxel.ies500x.slot.fw.ver[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Driver version|<p>MIB: ZYXEL-IES5000-MIB</p><p>The DSL driver of the plug-in card.</p>|SNMP agent|zyxel.ies500x.slot.dv.ver[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: DSL modem code version|<p>MIB: ZYXEL-IES5000-MIB</p><p>The DSL modem code version of the plug-in card.</p>|SNMP agent|zyxel.ies500x.slot.cv.ver[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Status|<p>MIB: ZYXEL-IES5000-MIB</p><p>The module state of the plug-in card.</p>|SNMP agent|zyxel.ies500x.slot.status[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Alarm status|<p>MIB: ZYXEL-IES5000-MIB</p><p>This variable indicates the alarm status of the module.</p><p>It is a bit map represented a sum, therefore, it can represent</p><p>multiple defects simultaneously. The moduleNoDefect should be set</p><p>if and only if no other flag is set.</p><p>The various bit positions are:</p><p>1   moduleNoDefect</p><p>2   moduleOverHeat</p><p>3   moduleFanRpmLow</p><p>4   moduleVoltageLow</p><p>5   moduleThermalSensorFailure</p><p>6   modulePullOut</p><p>7   powerDC48VAFailure</p><p>8   powerDC48VBFailure</p><p>9   extAlarmInputTrigger</p><p>10   moduleDown</p><p>11   mscSwitchOverOK</p><p>12   networkTopologyChange</p><p>13   macSpoof</p><p>14   cpuHigh</p><p>15   memoryUsageHigh</p><p>16   packetBufferUsageHigh</p><p>17   loopguardOccurence</p>|SNMP agent|zyxel.ies500x.slot.alarm[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Hardware version|<p>MIB: ZYXEL-IES5000-MIB</p><p>The hardware version of the plug-in card.</p>|SNMP agent|zyxel.ies500x.slot.hw.ver[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Serial number|<p>MIB: ZYXEL-IES5000-MIB</p><p>The serial number of the plug-in card.</p>|SNMP agent|zyxel.ies500x.slot.serial[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Uptime|<p>MIB: ZYXEL-IES5000-MIB</p><p>The time (in seconds) since the plug-in card was last re-initialized.</p>|SNMP agent|zyxel.ies500x.slot.uptime[{#SNMPINDEX}]|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: MAC address 1|<p>MIB: ZYXEL-IES5000-MIB</p><p>The MAC Address of the plug-in card.</p>|SNMP agent|zyxel.ies500x.slot.mac1[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: MAC address 2|<p>MIB: ZYXEL-IES5000-MIB</p><p>The MAC Address of the plug-in card.</p>|SNMP agent|zyxel.ies500x.slot.mac2[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Slot discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.fw.ver[{#SNMPINDEX}],#1)<>last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.fw.ver[{#SNMPINDEX}],#2) and length(last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.fw.ver[{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Driver has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.dv.ver[{#SNMPINDEX}],#1)<>last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.dv.ver[{#SNMPINDEX}],#2) and length(last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.dv.ver[{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: DSL modem code has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.cv.ver[{#SNMPINDEX}],#1)<>last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.cv.ver[{#SNMPINDEX}],#2) and length(last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.cv.ver[{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} alarm|<p>The slot reported an error.</p>|`find(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.alarm[{#SNMPINDEX}],,"like","moduleNoDefect")=0`|Average|**Manual close**: Yes|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Hardware version has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.hw.ver[{#SNMPINDEX}],#1)<>last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.hw.ver[{#SNMPINDEX}],#2) and length(last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.hw.ver[{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} has been replaced|<p>Slot {#ZYXEL.SLOT.ID} serial number has changed. Acknowledge to close the problem manually.</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.serial[{#SNMPINDEX}],#1)<>last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.serial[{#SNMPINDEX}],#2) and length(last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.serial[{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.slot.uptime[{#SNMPINDEX}])<10m`|Info|**Manual close**: Yes|

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>An entry in fanRpmTable.</p>|SNMP agent|zyxel.ies500x.fan.discovery|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES-500x: Fan #{#SNMPINDEX}|<p>MIB: ZYXEL-IES5000-MIB</p><p>Current speed in Revolutions Per Minute (RPM) on the fan.</p>|SNMP agent|zyxel.ies500x.fan[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Fan discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES-500x: FAN{#SNMPINDEX} is in critical state|<p>Please check the fan unit</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.fan[{#SNMPINDEX}])<{#ZYXEL.FANRPM.THRESH.LOW} or last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.fan[{#SNMPINDEX}])>{#ZYXEL.FANRPM.THRESH.HIGH}`|Average||

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>An entry in tempTable.</p>|SNMP agent|zyxel.ies500x.temp.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Temperature "{#ZYXEL.TEMP.ID}"|<p>MIB: ZYXEL-IES5000-MIB</p><p>The current temperature measured at this sensor</p>|SNMP agent|zyxel.ies500x.temp[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES-500x: Temperature Slot {#ZYXEL.SLOT.ID} Sensor: {#ZYXEL.TEMP.ID} is in critical state|<p>Please check the temperature</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.temp[{#SNMPINDEX}])>{#ZYXEL.TEMP.THRESH.HIGH} or last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.temp[{#SNMPINDEX}])<{#ZYXEL.TEMP.THRESH.LOW}`|Average||

### LLD rule Voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Voltage discovery|<p>An entry in voltageTable.</p>|SNMP agent|zyxel.ies500x.volt.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Nominal "{#ZYXEL.VOLT.NOMINAL}"|<p>MIB: ZYXEL-IES5000-MIB</p><p>The current voltage reading.</p>|SNMP agent|zyxel.ies500x.volt[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Voltage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES-500x: Voltage Slot {#ZYXEL.SLOT.ID} {#ZYXEL.VOLT.NOMINAL} is in critical state|<p>Please check the power supply</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.volt[{#SNMPINDEX}])<{#ZYXEL.VOLT.THRESH.LOW} or last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.volt[{#SNMPINDEX}])>{#ZYXEL.VOLT.THRESH.HIGH}`|Average||

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>A table that contains CPU utilization information.</p><p>This table is supported by R1.03 and later versions.</p>|SNMP agent|zyxel.ies500x.cpu.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: CPU utilization|<p>MIB: ZYXEL-IES5000-MIB</p><p>The CPU utilization in the past 60 seconds.</p>|SNMP agent|zyxel.ies500x.cpu[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} high CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/ZYXEL IES-500x by SNMP/zyxel.ies500x.cpu[{#SNMPINDEX}],5m)>{#ZYXEL.CPU.THRESH.HIGH}`|Warning||

### LLD rule Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory discovery|<p>A table that contains memory usage information.</p>|SNMP agent|zyxel.ies500x.memory.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Memory utilization|<p>MIB: ZYXEL-IES5000-MIB</p><p>The memory usage in the past 60 seconds.</p>|SNMP agent|zyxel.ies500x.memory[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Memory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES-500x: High memory utilization in Slot {#ZYXEL.SLOT.ID} pool|<p>The system is running out of free memory.</p>|`min(/ZYXEL IES-500x by SNMP/zyxel.ies500x.memory[{#SNMPINDEX}],5m)>{#ZYXEL.MEMORYHIGHTHRESH}`|Average||

### LLD rule Packet buffer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Packet buffer discovery|<p>A table that contains packet buffer usage information.</p>|SNMP agent|zyxel.ies500x.buffer.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Packet buffer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID}: Packet buffer utilization|<p>MIB: ZYXEL-IES5000-MIB</p><p>The packet buffer usage in the past 60 seconds.</p>|SNMP agent|zyxel.ies500x.buffer[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Packet buffer discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES-500x: High Packet buffer utilization in Slot {#ZYXEL.SLOT.ID}|<p>The system is running out of free buffer.</p>|`min(/ZYXEL IES-500x by SNMP/zyxel.ies500x.buffer[{#SNMPINDEX}],5m)>{#ZYXEL.BUFFERHIGHTHRESH}`|Average||

### LLD rule Ethernet interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ethernet interface discovery||SNMP agent|zyxel.ies500x.net.if.discovery|

### Item prototypes for Ethernet interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Interface description|<p>MIB: IF-MIB</p><p>A textual string containing information about the interface</p>|SNMP agent|zyxel.ies500x.net.if.descr[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Interface name|<p>MIB: IF-MIB</p><p>A textual string containing information about the interface</p>|SNMP agent|zyxel.ies500x.net.if.name[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>The testing(3) state indicates that no operational</p><p>packets can be passed.</p>|SNMP agent|zyxel.ies500x.net.if.operstatus[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Administrative status|<p>MIB: IF-MIB</p><p>The desired state of the interface.  The</p><p>testing(3) state indicates that no operational</p><p>packets can be passed.</p>|SNMP agent|zyxel.ies500x.net.if.adminstatus[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Incoming traffic|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface,</p><p>including framing characters.</p>|SNMP agent|zyxel.ies500x.net.if.in.traffic[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Incoming unicast packages|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were not addressed to a multicast</p><p>or broadcast address at this sub-layer</p>|SNMP agent|zyxel.ies500x.net.if.in.ucastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Incoming multicast packages|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were addressed to a multicast</p><p>address at this sub-layer.  For a MAC layer protocol, this</p><p>includes both Group and Functional addresses.</p>|SNMP agent|zyxel.ies500x.net.if.in.multicastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Incoming broadcast packages|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were addressed to a broadcast</p><p>address at this sub-layer.</p>|SNMP agent|zyxel.ies500x.net.if.in.broadcastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Outgoing traffic|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the</p><p>interface, including framing characters.  This object is a</p><p>64-bit version of ifOutOctets.</p>|SNMP agent|zyxel.ies500x.net.if.out.traffic[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Outgoing unicast packages|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were not addressed to a</p><p>multicast or broadcast address at this sub-layer, including</p><p>those that were discarded or not sent.</p>|SNMP agent|zyxel.ies500x.net.if.out.ucastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Outgoing multicast packages|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were addressed to a</p><p>multicast address at this sub-layer, including those that</p><p>were discarded or not sent.  For a MAC layer protocol, this</p><p>includes both Group and Functional addresses.</p>|SNMP agent|zyxel.ies500x.net.if.out.multicastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Outgoing broadcast packages|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were addressed to a</p><p>broadcast address at this sub-layer, including those that</p><p>were discarded or not sent.</p>|SNMP agent|zyxel.ies500x.net.if.out.broadcastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Link speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in bits per second</p>|SNMP agent|zyxel.ies500x.net.if.highspeed[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Incoming utilization|<p>Interface utilization percentage</p>|Calculated|zyxel.ies500x.net.if.in.util[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>In range: `0 -> 100`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Outgoing utilization|<p>Interface utilization percentage</p>|Calculated|zyxel.ies500x.net.if.out.util[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>In range: `0 -> 100`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Ethernet interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES-500x: Port {#SNMPINDEX}: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.if.operstatus[{#SNMPINDEX}])=2 and last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.if.operstatus[{#SNMPINDEX}],#1)<>last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.if.operstatus[{#SNMPINDEX}],#2)`|Average|**Manual close**: Yes|

### LLD rule ADSL interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ADSL interface discovery||SNMP agent|zyxel.ies500x.net.adsl.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for ADSL interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Interface description|<p>MIB: IF-MIB</p><p>A textual string containing information about the interface</p>|SNMP agent|zyxel.ies500x.net.adsl.descr[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Interface name|<p>MIB: IF-MIB</p><p>A textual string containing information about the interface</p>|SNMP agent|zyxel.ies500x.net.adsl.name[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>The testing(3) state indicates that no operational</p><p>packets can be passed.</p>|SNMP agent|zyxel.ies500x.net.adsl.operstatus[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Administrative status|<p>MIB: IF-MIB</p><p>The desired state of the interface.  The</p><p>testing(3) state indicates that no operational</p><p>packets can be passed.</p>|SNMP agent|zyxel.ies500x.net.adsl.adminstatus[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Incoming traffic|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface,</p><p>including framing characters.</p>|SNMP agent|zyxel.ies500x.net.adsl.in.traffic[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Incoming unicast packages|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were not addressed to a multicast</p><p>or broadcast address at this sub-layer</p>|SNMP agent|zyxel.ies500x.net.adsl.in.ucastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Incoming multicast packages|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were addressed to a multicast</p><p>address at this sub-layer.  For a MAC layer protocol, this</p><p>includes both Group and Functional addresses.</p>|SNMP agent|zyxel.ies500x.net.adsl.in.multicastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Incoming broadcast packages|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were addressed to a broadcast</p><p>address at this sub-layer.</p>|SNMP agent|zyxel.ies500x.net.adsl.in.broadcastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Outgoing traffic|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the</p><p>interface, including framing characters.  This object is a</p><p>64-bit version of ifOutOctets.</p>|SNMP agent|zyxel.ies500x.net.adsl.out.traffic[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Outgoing unicast packages|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were not addressed to a</p><p>multicast or broadcast address at this sub-layer, including</p><p>those that were discarded or not sent.</p>|SNMP agent|zyxel.ies500x.net.adsl.out.ucastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Outgoing multicast packages|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were addressed to a</p><p>multicast address at this sub-layer, including those that</p><p>were discarded or not sent.  For a MAC layer protocol, this</p><p>includes both Group and Functional addresses.</p>|SNMP agent|zyxel.ies500x.net.adsl.out.multicastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Outgoing broadcast packages|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were addressed to a</p><p>broadcast address at this sub-layer, including those that</p><p>were discarded or not sent.</p>|SNMP agent|zyxel.ies500x.net.adsl.out.broadcastpkts[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: ATUC noise margin|<p>MIB: ADSL-LINE-MIB</p><p>Noise Margin as seen by this ATU with respect to its</p><p>received signal in tenth dB.</p><p>The Info Atuc fields show data acquired from the ATUC (ADSL Termination Unit - Central), in this case ZYXEL IES-500x, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies500x.net.adsl.atuc.snrmgn[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: ATUC attenuation|<p>MIB: ADSL-LINE-MIB</p><p>Measured difference in the total power transmitted by</p><p>the peer ATU and the total power received by this ATU.</p><p>The Info Atuc fields show data acquired from the ATUC (ADSL Termination Unit - Central), in this case ZYXEL IES-500x, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies500x.net.adsl.atuc.atn[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: ATUC output power|<p>MIB: ADSL-LINE-MIB</p><p>Measured total output power transmitted by this ATU.</p><p>The Info Atuc fields show data acquired from the ATUC (ADSL Termination Unit - Central), in this case ZYXEL IES-500x, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies500x.net.adsl.atuc.outpwr[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: ATUR noise margin|<p>MIB: ADSL-LINE-MIB</p><p>Noise Margin as seen by this ATU with respect to its</p><p>received signal in tenth dB.</p><p>The Info Atur fields show data acquired from the ATUR (ADSL Termination Unit - Remote), in this case the subscriber's ADSL modem or router, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies500x.net.adsl.atur.snrmgn[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: ATUR attenuation|<p>MIB: ADSL-LINE-MIB</p><p>Measured difference in the total power transmitted by</p><p>the peer ATU and the total power received by this ATU.</p><p>The Info Atur fields show data acquired from the ATUR (ADSL Termination Unit - Remote), in this case the subscriber's ADSL modem or router, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies500x.net.adsl.atur.atn[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: ATUR output power|<p>MIB: ADSL-LINE-MIB</p><p>Measured total output power transmitted by this ATU.</p><p>The Info Atur fields show data acquired from the ATUR (ADSL Termination Unit - Remote), in this case the subscriber's ADSL modem or router, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies500x.net.adsl.atur.outpwr[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for ADSL interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES-500x: Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.adsl.operstatus[{#SNMPINDEX}])=2 and last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.adsl.operstatus[{#SNMPINDEX}],#1)<>last(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.adsl.operstatus[{#SNMPINDEX}],#2)`|Average|**Manual close**: Yes|
|ZYXEL IES-500x: Low the DSL line noise margins in Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}|<p>Signal-to-noise margin (SNR Margin) which is the difference between the actual SNR and the SNR required to sync at a specific speed</p>|`min(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.adsl.atuc.snrmgn[{#SNMPINDEX}],5m)<{$ZYXEL.ADSL.SNR.MIN}`|Warning||
|ZYXEL IES-500x: High the DSL line attenuation in Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}|<p>The reductions in amplitude of the downstream and upstream DSL signals.</p>|`min(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.adsl.atuc.atn[{#SNMPINDEX}],5m)>{$ZYXEL.ADSL.ATN.MAX}`|Warning||
|ZYXEL IES-500x: Low the DSL line noise margins in Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}|<p>Signal-to-noise margin (SNR Margin) which is the difference between the actual SNR and the SNR required to sync at a specific speed</p>|`min(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.adsl.atur.snrmgn[{#SNMPINDEX}],5m)<{$ZYXEL.ADSL.SNR.MIN}`|Warning||
|ZYXEL IES-500x: High the DSL line attenuation in Slot {#ZYXEL.SLOT.ID} Port {#ZYXEL.PORTID}|<p>The reductions in amplitude of the downstream and upstream DSL signals.</p>|`min(/ZYXEL IES-500x by SNMP/zyxel.ies500x.net.adsl.atur.atn[{#SNMPINDEX}],5m)>{$ZYXEL.ADSL.ATN.MAX}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

