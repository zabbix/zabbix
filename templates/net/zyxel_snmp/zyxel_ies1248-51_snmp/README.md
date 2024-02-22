
# ZYXEL IES1248-51 by SNMP

## Overview

http://origin-eu.zyxel.com/products_services/ies_1248_51v.shtml?t=p

### Known Issues

Description: Incorrect handling of SNMP bulk requests. Disable the use of bulk requests in the SNMP interface settings.
Version: all versions firmware
Device: ZYXEL IES1248-51

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- ZYXEL IES1248-51

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
|{$ZYXEL.LLD.FILTER.IF.LINKSTATUS.MATCHES}|<p>Filter of discoverable link types.</p>|`.*`|
|{$ZYXEL.LLD.FILTER.IF.LINKSTATUS.NOT_MATCHES}|<p>Filter to exclude discovered by link types.</p>|`2`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES1248-51: SNMP agent availability||Zabbix internal|zabbix[host,snmp,available]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES1248-51: Hardware model name|<p>MIB: RFC1213-MIB</p><p>A textual description of the entity.  This value</p><p>should include the full name and version</p><p>identification of the system's hardware type,</p><p>software operating-system, and networking</p><p>software.  It is mandatory that this only contain</p><p>printable ASCII characters.</p>|SNMP agent|zyxel.ies1248.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Contact|<p>MIB: RFC1213-MIB</p><p>The textual identification of the contact person</p><p>for this managed node, together with information</p><p>on how to contact this person.</p>|SNMP agent|zyxel.ies1248.contact<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Host name|<p>MIB: RFC1213-MIB</p><p>An administratively-assigned name for this</p><p>managed node.  By convention, this is the node's</p><p>fully-qualified domain name.</p>|SNMP agent|zyxel.ies1248.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Location|<p>MIB: RFC1213-MIB</p><p>The physical location of this node (e.g.,</p><p>`telephone closet, 3rd floor').</p>|SNMP agent|zyxel.ies1248.location<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: MAC address|<p>MIB: IF-MIB</p><p>The interface's address at the protocol layer</p><p>immediately `below' the network layer in the</p><p>protocol stack.  For interfaces which do not have</p><p>such an address (e.g., a serial line), this object</p><p>should contain an octet string of zero length.</p>|SNMP agent|zyxel.ies1248.mac<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Uptime (network)|<p>MIB: RFC1213-MIB</p><p>The time (in hundredths of a second) since the</p><p>network management portion of the system was last</p><p>re-initialized.</p>|SNMP agent|zyxel.ies1248.net.uptime<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|ZYXEL IES1248-51: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized.</p><p>Note that this is different from sysUpTime in the SNMPv2-MIB</p><p>[RFC1907] because sysUpTime is the uptime of the</p><p>network management portion of the system.</p>|SNMP agent|zyxel.ies1248.hw.uptime<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|ZYXEL IES1248-51: ZyNOS F/W Version|<p>MIB: ZYXEL-IESCOMMON-MIB</p>|SNMP agent|zyxel.ies1248.fwversion<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Hardware serial number|<p>MIB: ZYXEL-IESCOMMON-MIB</p><p>Serial number</p>|SNMP agent|zyxel.ies1248.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Alarm status|<p>MIB: ZYXEL-IESCOMMON-MIB</p><p>This variable indicates the alarm status of the module.</p><p>It is a bit map represented a sum, therefore, it can represent</p><p>multiple defects simultaneously. The moduleNoDefect should be set</p><p>if and only if no other flag is set.</p><p>The various bit positions are:</p><p>1   moduleNoDefect</p><p>2   moduleOverHeat</p><p>3   moduleFanRpmLow</p><p>4   moduleVoltageLow</p><p>5   moduleThermalSensorFailure</p><p>6   modulePullOut</p><p>7   powerDC48VAFailure</p><p>8   powerDC48VBFailure</p><p>9   extAlarmInputTrigger</p><p>10   moduleDown</p><p>11   mscSwitchOverOK</p><p>12   networkTopologyChange</p><p>13   macSpoof</p><p>14   cpuHigh</p><p>15   memoryUsageHigh</p><p>16   packetBufferUsageHigh</p><p>17   loopguardOccurence</p>|SNMP agent|zyxel.ies1248.slot.alarm<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES1248-51: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/ZYXEL IES1248-51 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning||
|ZYXEL IES1248-51: Template does not match hardware|<p>This template is for Zyxel IES1248-51, but connected to {ITEM.VALUE}</p>|`last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.model)<>"IES1248-51"`|Info|**Manual close**: Yes|
|ZYXEL IES1248-51: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.hw.uptime)>0 and last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.hw.uptime)<10m) or (last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.hw.uptime)=0 and last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.uptime)<10m)`|Info|**Manual close**: Yes|
|ZYXEL IES1248-51: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.fwversion,#1)<>last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.fwversion,#2) and length(last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.fwversion))>0`|Info|**Manual close**: Yes|
|ZYXEL IES1248-51: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.serialnumber,#1)<>last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.serialnumber,#2) and length(last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.serialnumber))>0`|Info|**Manual close**: Yes|
|ZYXEL IES1248-51: Port {#SNMPINDEX} alarm|<p>The slot reported an error.</p>|`find(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.slot.alarm,,"like","moduleNoDefect")=0`|Average|**Manual close**: Yes|

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>An entry in fanRpmTable.</p>|SNMP agent|zyxel.ies1248.fan.discovery|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES1248-51: Fan #{#SNMPINDEX}|<p>MIB: ZYXEL-IESCOMMON-MIB</p><p>Current speed in Revolutions Per Minute (RPM) on the fan.</p>|SNMP agent|zyxel.ies1248.fan[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Fan discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES1248-51: FAN{#SNMPINDEX} is in critical state|<p>Please check the fan unit</p>|`last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.fan[{#SNMPINDEX}])<{#ZYXEL.FANRPM.THRESH.LOW}`|Average||

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>An entry in tempTable.</p>|SNMP agent|zyxel.ies1248.temp.discovery|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES1248-51: Temperature "{#ZYXEL.TEMP.ID}"|<p>MIB: ZYXEL-IESCOMMON-MIB</p><p>The current temperature measured at this sensor</p>|SNMP agent|zyxel.ies1248.temp[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES1248-51: Temperature {#ZYXEL.TEMP.ID} is in critical state|<p>Please check the temperature</p>|`last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.temp[{#SNMPINDEX}])>{#ZYXEL.TEMP.THRESH.HIGH}`|Average||

### LLD rule Voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Voltage discovery|<p>An entry in voltageTable.</p>|SNMP agent|zyxel.ies1248.volt.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES1248-51: Nominal "{#ZYXEL.VOLT.NOMINAL}"|<p>MIB: ZYXEL-IESCOMMON-MIB</p><p>The current voltage reading.</p>|SNMP agent|zyxel.ies1248.volt[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Voltage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES1248-51: Voltage {#ZYXEL.VOLT.NOMINAL} is in critical state|<p>Please check the power supply</p>|`last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.volt[{#SNMPINDEX}])<{#ZYXEL.VOLT.THRESH.LOW}`|Average||

### LLD rule Ethernet interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ethernet interface discovery||SNMP agent|zyxel.ies1248.net.if.discovery|

### Item prototypes for Ethernet interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: Interface name|<p>MIB: IF-MIB</p><p>A textual string containing information about the interface</p>|SNMP agent|zyxel.ies1248.net.if.name[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#ZYXEL.IF.NAME}: Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>The testing(3) state indicates that no operational</p><p>packets can be passed.</p>|SNMP agent|zyxel.ies1248.net.if.operstatus[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#ZYXEL.IF.NAME}: Administrative status|<p>MIB: IF-MIB</p><p>The desired state of the interface.  The</p><p>testing(3) state indicates that no operational</p><p>packets can be passed.</p>|SNMP agent|zyxel.ies1248.net.if.adminstatus[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#ZYXEL.IF.NAME}: Incoming traffic|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface,</p><p>including framing characters.</p>|SNMP agent|zyxel.ies1248.net.if.in.traffic[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|ZYXEL IES1248-51: Port {#ZYXEL.IF.NAME}: Outgoing traffic|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the</p><p>interface, including framing characters.  This object is a</p><p>64-bit version of ifOutOctets.</p>|SNMP agent|zyxel.ies1248.net.if.out.traffic[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Ethernet interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES1248-51: Port {#ZYXEL.IF.NAME}: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.if.operstatus[{#SNMPINDEX}])=2 and last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.if.operstatus[{#SNMPINDEX}],#1)<>last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.if.operstatus[{#SNMPINDEX}],#2)`|Average|**Manual close**: Yes|

### LLD rule ADSL interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ADSL interface discovery||SNMP agent|zyxel.ies1248.net.adsl.discovery|

### Item prototypes for ADSL interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: Interface name|<p>MIB: IF-MIB</p><p>A textual string containing information about the interface</p>|SNMP agent|zyxel.ies1248.net.adsl.name[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>The testing(3) state indicates that no operational</p><p>packets can be passed.</p>|SNMP agent|zyxel.ies1248.net.adsl.operstatus[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: Administrative status|<p>MIB: IF-MIB</p><p>The desired state of the interface.  The</p><p>testing(3) state indicates that no operational</p><p>packets can be passed.</p>|SNMP agent|zyxel.ies1248.net.adsl.adminstatus[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: Incoming traffic|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface,</p><p>including framing characters.</p>|SNMP agent|zyxel.ies1248.net.adsl.in.traffic[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: Outgoing traffic|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the</p><p>interface, including framing characters.  This object is a</p><p>64-bit version of ifOutOctets.</p>|SNMP agent|zyxel.ies1248.net.adsl.out.traffic[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: ATUC noise margin|<p>MIB: ADSL-LINE-MIB</p><p>Noise Margin as seen by this ATU with respect to its</p><p>received signal in tenth dB.</p><p>The Info Atuc fields show data acquired from the ATUC (ADSL Termination Unit - Central), in this case ZYXEL IES1248-51, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies1248.net.adsl.atuc.snrmgn[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: ATUC attenuation|<p>MIB: ADSL-LINE-MIB</p><p>Measured difference in the total power transmitted by</p><p>the peer ATU and the total power received by this ATU.</p><p>The Info Atuc fields show data acquired from the ATUC (ADSL Termination Unit - Central), in this case ZYXEL IES1248-51, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies1248.net.adsl.atuc.atn[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: ATUC output power|<p>MIB: ADSL-LINE-MIB</p><p>Measured total output power transmitted by this ATU.</p><p>The Info Atuc fields show data acquired from the ATUC (ADSL Termination Unit - Central), in this case ZYXEL IES1248-51, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies1248.net.adsl.atuc.outpwr[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: ATUR noise margin|<p>MIB: ADSL-LINE-MIB</p><p>Noise Margin as seen by this ATU with respect to its</p><p>received signal in tenth dB.</p><p>The Info Atur fields show data acquired from the ATUR (ADSL Termination Unit - Remote), in this case the subscriber's ADSL modem or router, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies1248.net.adsl.atur.snrmgn[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: ATUR attenuation|<p>MIB: ADSL-LINE-MIB</p><p>Measured difference in the total power transmitted by</p><p>the peer ATU and the total power received by this ATU.</p><p>The Info Atur fields show data acquired from the ATUR (ADSL Termination Unit - Remote), in this case the subscriber's ADSL modem or router, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies1248.net.adsl.atur.atn[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: ATUR output power|<p>MIB: ADSL-LINE-MIB</p><p>Measured total output power transmitted by this ATU.</p><p>The Info Atur fields show data acquired from the ATUR (ADSL Termination Unit - Remote), in this case the subscriber's ADSL modem or router, during negotiation/provisioning message interchanges.</p>|SNMP agent|zyxel.ies1248.net.adsl.atur.outpwr[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for ADSL interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ZYXEL IES1248-51: Port {#SNMPINDEX}: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.adsl.operstatus[{#SNMPINDEX}])=2 and last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.adsl.operstatus[{#SNMPINDEX}],#1)<>last(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.adsl.operstatus[{#SNMPINDEX}],#2)`|Average|**Manual close**: Yes|
|ZYXEL IES1248-51: Low the DSL line noise margins in Port {#SNMPINDEX}|<p>Signal-to-noise margin (SNR Margin) which is the difference between the actual SNR and the SNR required to sync at a specific speed</p>|`min(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.adsl.atuc.snrmgn[{#SNMPINDEX}],5m)<{$ZYXEL.ADSL.SNR.MIN}`|Warning||
|ZYXEL IES1248-51: High the DSL line attenuation in Port {#SNMPINDEX}|<p>The reductions in amplitude of the downstream and upstream DSL signals.</p>|`min(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.adsl.atuc.atn[{#SNMPINDEX}],5m)>{$ZYXEL.ADSL.ATN.MAX}`|Warning||
|ZYXEL IES1248-51: Low the DSL line noise margins in Port {#SNMPINDEX}|<p>Signal-to-noise margin (SNR Margin) which is the difference between the actual SNR and the SNR required to sync at a specific speed</p>|`min(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.adsl.atur.snrmgn[{#SNMPINDEX}],5m)<{$ZYXEL.ADSL.SNR.MIN}`|Warning||
|ZYXEL IES1248-51: High the DSL line attenuation in Port {#SNMPINDEX}|<p>The reductions in amplitude of the downstream and upstream DSL signals.</p>|`min(/ZYXEL IES1248-51 by SNMP/zyxel.ies1248.net.adsl.atur.atn[{#SNMPINDEX}],5m)>{$ZYXEL.ADSL.ATN.MAX}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

