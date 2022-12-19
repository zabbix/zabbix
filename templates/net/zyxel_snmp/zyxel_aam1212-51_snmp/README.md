
# ZYXEL AAM1212-51 IES-612 by SNMP

## Overview

For Zabbix version: 6.0 and higher.  
http://origin-eu.zyxel.com/products_services/ies_1248_51v.shtml?t=p

This template was tested on:

- ZYXEL AAM1212-51 / IES-612

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/network_devices) for basic instructions.

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SNMP.TIMEOUT} |<p>The time interval for SNMP agent availability trigger expression.</p> |`5m` |
|{$ZYXEL.ADSL.ATN.MAX} |<p>Type the maximum signal attenuation</p> |`40` |
|{$ZYXEL.ADSL.SNR.MIN} |<p>Type the minimum signal to noise margin (0-31 dB)</p> |`8` |
|{$ZYXEL.LLD.FILTER.IF.CONTROL.MATCHES} |<p>Triggers will be created only for interfaces whose description contains the value of this macro</p> |`CHANGE_IF_NEEDED` |
|{$ZYXEL.LLD.FILTER.IF.LINKSTATUS.MATCHES} |<p>Filter of discoverable link types.</p> |`.*` |
|{$ZYXEL.LLD.FILTER.IF.LINKSTATUS.NOT_MATCHES} |<p>Filter to exclude discovered by link types.</p> |`2` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|ADSL interface discovery |<p>-</p> |SNMP |zyxel.aam1212.net.adsl.discovery<p>**Filter**:</p>AND <p>- {#ZYXEL.IF.LINKSTATUS} MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.LINKSTATUS.MATCHES}`</p><p>- {#ZYXEL.IF.LINKSTATUS} NOT_MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.LINKSTATUS.NOT_MATCHES}`</p><p>- {#ZYXEL.IF.NAME} MATCHES_REGEX `adsl`</p><p>**Overrides:**</p><p>Don't create triggers for matching interface<br> - {#ZYXEL.IF.NAME} NOT_MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.CONTROL.MATCHES}`<br>  - TRIGGER_PROTOTYPE REGEXP `.*`<br>  - NO_DISCOVER</p> |
|Ethernet interface discovery |<p>-</p> |SNMP |zyxel.aam1212.net.if.discovery<p>**Filter**:</p>AND <p>- {#ZYXEL.IF.LINKSTATUS} MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.LINKSTATUS.MATCHES}`</p><p>- {#ZYXEL.IF.LINKSTATUS} NOT_MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.LINKSTATUS.NOT_MATCHES}`</p><p>- {#ZYXEL.IF.NAME} MATCHES_REGEX `enet`</p><p>**Overrides:**</p><p>Don't create triggers for matching interface<br> - {#ZYXEL.IF.NAME} NOT_MATCHES_REGEX `{$ZYXEL.LLD.FILTER.IF.CONTROL.MATCHES}`<br>  - TRIGGER_PROTOTYPE REGEXP `.*`<br>  - NO_DISCOVER</p> |
|Temperature discovery |<p>An entry in tempTable.</p> |SNMP |zyxel.aam1212.temp.discovery |
|Voltage discovery |<p>An entry in voltageTable.</p> |SNMP |zyxel.aam1212.volt.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Inventory |ZYXEL AAM1212-51 / IES-612: Hardware model name |<p>MIB: RFC1213-MIB</p><p>A textual description of the entity.  This value</p><p>should include the full name and version</p><p>identification of the system's hardware type,</p><p>software operating-system, and networking</p><p>software.  It is mandatory that this only contain</p><p>printable ASCII characters.</p> |SNMP |zyxel.aam1212.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL AAM1212-51 / IES-612: Contact |<p>MIB: RFC1213-MIB</p><p>The textual identification of the contact person</p><p>for this managed node, together with information</p><p>on how to contact this person.</p> |SNMP |zyxel.aam1212.contact<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL AAM1212-51 / IES-612: Host name |<p>MIB: RFC1213-MIB</p><p>An administratively-assigned name for this</p><p>managed node.  By convention, this is the node's</p><p>fully-qualified domain name.</p> |SNMP |zyxel.aam1212.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL AAM1212-51 / IES-612: Location |<p>MIB: RFC1213-MIB</p><p>The physical location of this node (e.g.,</p><p>`telephone closet, 3rd floor').</p> |SNMP |zyxel.aam1212.location<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL AAM1212-51 / IES-612: MAC address |<p>MIB: IF-MIB</p><p>The interface's address at the protocol layer</p><p>immediately `below' the network layer in the</p><p>protocol stack.  For interfaces which do not have</p><p>such an address (e.g., a serial line), this object</p><p>should contain an octet string of zero length.</p> |SNMP |zyxel.aam1212.mac<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL AAM1212-51 / IES-612: ZyNOS F/W Version |<p>MIB: ZYXEL-IESCOMMON-MIB</p> |SNMP |zyxel.aam1212.fwversion<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |ZYXEL AAM1212-51 / IES-612: Hardware serial number |<p>MIB: ZYXEL-IESCOMMON-MIB</p><p>Serial number</p> |SNMP |zyxel.aam1212.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#ZYXEL.IF.NAME}: Interface name |<p>MIB: IF-MIB</p><p>A textual string containing information about the interface</p> |SNMP |zyxel.aam1212.net.if.name[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#ZYXEL.IF.NAME}: Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>The testing(3) state indicates that no operational</p><p>packets can be passed.</p> |SNMP |zyxel.aam1212.net.if.operstatus[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#ZYXEL.IF.NAME}: Administrative status |<p>MIB: IF-MIB</p><p>The desired state of the interface.  The</p><p>testing(3) state indicates that no operational</p><p>packets can be passed.</p> |SNMP |zyxel.aam1212.net.if.adminstatus[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#ZYXEL.IF.NAME}: Incoming traffic |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface,</p><p>including framing characters.</p> |SNMP |zyxel.aam1212.net.if.in.traffic[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#ZYXEL.IF.NAME}: Outgoing traffic |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the</p><p>interface, including framing characters.  This object is a</p><p>64-bit version of ifOutOctets.</p> |SNMP |zyxel.aam1212.net.if.out.traffic[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: Interface name |<p>MIB: IF-MIB</p><p>A textual string containing information about the interface</p> |SNMP |zyxel.aam1212.net.adsl.name[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>The testing(3) state indicates that no operational</p><p>packets can be passed.</p> |SNMP |zyxel.aam1212.net.adsl.operstatus[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: Administrative status |<p>MIB: IF-MIB</p><p>The desired state of the interface.  The</p><p>testing(3) state indicates that no operational</p><p>packets can be passed.</p> |SNMP |zyxel.aam1212.net.adsl.adminstatus[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: Incoming traffic |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface,</p><p>including framing characters.</p> |SNMP |zyxel.aam1212.net.adsl.in.traffic[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: Outgoing traffic |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the</p><p>interface, including framing characters.  This object is a</p><p>64-bit version of ifOutOctets.</p> |SNMP |zyxel.aam1212.net.adsl.out.traffic[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: ATUC noise margin |<p>MIB: ADSL-LINE-MIB</p><p>Noise Margin as seen by this ATU with respect to its</p><p>received signal in tenth dB.</p><p>The Info Atuc fields show data acquired from the ATUC (ADSL Termination Unit – Central), in this case ZYXEL AAM1212-51 / IES-612, during negotiation/provisioning message interchanges.</p> |SNMP |zyxel.aam1212.net.adsl.atuc.snrmgn[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: ATUC attenuation |<p>MIB: ADSL-LINE-MIB</p><p>Measured difference in the total power transmitted by</p><p>the peer ATU and the total power received by this ATU.</p><p>The Info Atuc fields show data acquired from the ATUC (ADSL Termination Unit – Central), in this case ZYXEL AAM1212-51 / IES-612, during negotiation/provisioning message interchanges.</p> |SNMP |zyxel.aam1212.net.adsl.atuc.atn[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: ATUC output power |<p>MIB: ADSL-LINE-MIB</p><p>Measured total output power transmitted by this ATU.</p><p>The Info Atuc fields show data acquired from the ATUC (ADSL Termination Unit – Central), in this case ZYXEL AAM1212-51 / IES-612, during negotiation/provisioning message interchanges.</p> |SNMP |zyxel.aam1212.net.adsl.atuc.outpwr[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: ATUR noise margin |<p>MIB: ADSL-LINE-MIB</p><p>Noise Margin as seen by this ATU with respect to its</p><p>received signal in tenth dB.</p><p>The Info Atur fields show data acquired from the ATUR (ADSL Termination Unit – Remote), in this case the subscriber's ADSL modem or router, during negotiation/provisioning message interchanges.</p> |SNMP |zyxel.aam1212.net.adsl.atur.snrmgn[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: ATUR attenuation |<p>MIB: ADSL-LINE-MIB</p><p>Measured difference in the total power transmitted by</p><p>the peer ATU and the total power received by this ATU.</p><p>The Info Atur fields show data acquired from the ATUR (ADSL Termination Unit – Remote), in this case the subscriber's ADSL modem or router, during negotiation/provisioning message interchanges.</p> |SNMP |zyxel.aam1212.net.adsl.atur.atn[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Network interfaces |ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: ATUR output power |<p>MIB: ADSL-LINE-MIB</p><p>Measured total output power transmitted by this ATU.</p><p>The Info Atur fields show data acquired from the ATUR (ADSL Termination Unit – Remote), in this case the subscriber's ADSL modem or router, during negotiation/provisioning message interchanges.</p> |SNMP |zyxel.aam1212.net.adsl.atur.outpwr[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Power supply |ZYXEL AAM1212-51 / IES-612: Nominal "{#ZYXEL.VOLT.NOMINAL}" |<p>MIB: ZYXEL-IESCOMMON-MIB</p><p>The current voltage reading.</p> |SNMP |zyxel.aam1212.volt[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |ZYXEL AAM1212-51 / IES-612: SNMP agent availability |<p>-</p> |INTERNAL |zabbix[host,snmp,available]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |ZYXEL AAM1212-51 / IES-612: Uptime (network) |<p>MIB: RFC1213-MIB</p><p>The time (in hundredths of a second) since the</p><p>network management portion of the system was last</p><p>re-initialized.</p> |SNMP |zyxel.aam1212.net.uptime<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |ZYXEL AAM1212-51 / IES-612: Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized.</p><p>Note that this is different from sysUpTime in the SNMPv2-MIB</p><p>[RFC1907] because sysUpTime is the uptime of the</p><p>network management portion of the system.</p> |SNMP |zyxel.aam1212.hw.uptime<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |ZYXEL AAM1212-51 / IES-612: Alarm status |<p>MIB: ZYXEL-IESCOMMON-MIB</p><p>This variable indicates the alarm status of the module.</p><p>It is a bit map represented a sum, therefore, it can represent</p><p>multiple defects simultaneously. The moduleNoDefect should be set</p><p>if and only if no other flag is set.</p><p>The various bit positions are:</p><p>1   moduleNoDefect</p><p>2   moduleOverHeat</p><p>3   moduleFanRpmLow</p><p>4   moduleVoltageLow</p><p>5   moduleThermalSensorFailure</p><p>6   modulePullOut</p><p>7   powerDC48VAFailure</p><p>8   powerDC48VBFailure</p><p>9   extAlarmInputTrigger</p><p>10   moduleDown</p><p>11   mscSwitchOverOK</p><p>12   networkTopologyChange</p><p>13   macSpoof</p><p>14   cpuHigh</p><p>15   memoryUsageHigh</p><p>16   packetBufferUsageHigh</p><p>17   loopguardOccurence</p> |SNMP |zyxel.aam1212.slot.alarm<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Temperature |ZYXEL AAM1212-51 / IES-612: Temperature "{#ZYXEL.TEMP.ID}" |<p>MIB: ZYXEL-IESCOMMON-MIB</p><p>The current temperature measured at this sensor</p> |SNMP |zyxel.aam1212.temp[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|ZYXEL AAM1212-51 / IES-612: Template does not match hardware |<p>This template is for Zyxel AAM1212-51 / IES-612, but connected to {ITEM.VALUE}</p> |`last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.model)<>"AAM1212-51 / IES-612"` |INFO | |
|ZYXEL AAM1212-51 / IES-612: Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.fwversion,#1)<>last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.fwversion,#2) and length(last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.fwversion))>0` |INFO |<p>Manual close: YES</p> |
|ZYXEL AAM1212-51 / IES-612: Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.serialnumber,#1)<>last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.serialnumber,#2) and length(last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|ZYXEL AAM1212-51 / IES-612: Port {#ZYXEL.IF.NAME}: Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.if.operstatus[{#SNMPINDEX}])=2 and last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.if.operstatus[{#SNMPINDEX}],#1)<>last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.if.operstatus[{#SNMPINDEX}],#2)`<p>Recovery expression:</p>`last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.if.operstatus[{#SNMPINDEX}])<>2` |AVERAGE |<p>Manual close: YES</p> |
|ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX}: Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.adsl.operstatus[{#SNMPINDEX}])=2 and last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.adsl.operstatus[{#SNMPINDEX}],#1)<>last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.adsl.operstatus[{#SNMPINDEX}],#2)`<p>Recovery expression:</p>`last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.adsl.operstatus[{#SNMPINDEX}])<>2` |AVERAGE |<p>Manual close: YES</p> |
|ZYXEL AAM1212-51 / IES-612: Low the DSL line noise margins in Port {#SNMPINDEX} |<p>Signal-to-noise margin (SNR Margin) which is the difference between the actual SNR and the SNR required to sync at a specific speed</p> |`min(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.adsl.atuc.snrmgn[{#SNMPINDEX}],5m)<{$ZYXEL.ADSL.SNR.MIN}` |WARNING | |
|ZYXEL AAM1212-51 / IES-612: High the DSL line attenuation in Port {#SNMPINDEX} |<p>The reductions in amplitude of the downstream and upstream DSL signals.</p> |`min(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.adsl.atuc.atn[{#SNMPINDEX}],5m)>{$ZYXEL.ADSL.ATN.MAX}` |WARNING | |
|ZYXEL AAM1212-51 / IES-612: Low the DSL line noise margins in Port {#SNMPINDEX} |<p>Signal-to-noise margin (SNR Margin) which is the difference between the actual SNR and the SNR required to sync at a specific speed</p> |`min(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.adsl.atur.snrmgn[{#SNMPINDEX}],5m)<{$ZYXEL.ADSL.SNR.MIN}` |WARNING | |
|ZYXEL AAM1212-51 / IES-612: High the DSL line attenuation in Port {#SNMPINDEX} |<p>The reductions in amplitude of the downstream and upstream DSL signals.</p> |`min(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.adsl.atur.atn[{#SNMPINDEX}],5m)>{$ZYXEL.ADSL.ATN.MAX}` |WARNING | |
|ZYXEL AAM1212-51 / IES-612: Voltage {#ZYXEL.VOLT.NOMINAL} is in critical state |<p>Please check the power supply</p> |`last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.volt[{#SNMPINDEX}])<{#ZYXEL.VOLT.THRESH.LOW}` |AVERAGE | |
|ZYXEL AAM1212-51 / IES-612: No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/ZYXEL AAM1212-51 IES-612 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING | |
|ZYXEL AAM1212-51 / IES-612: Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.hw.uptime)>0 and last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.hw.uptime)<10m) or (last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.hw.uptime)=0 and last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.net.uptime)<10m)` |INFO |<p>Manual close: YES</p> |
|ZYXEL AAM1212-51 / IES-612: Port {#SNMPINDEX} alarm |<p>The slot reported an error.</p> |`find(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.slot.alarm,,"like","moduleNoDefect")=0` |AVERAGE | |
|ZYXEL AAM1212-51 / IES-612: Temperature {#ZYXEL.TEMP.ID} is in critical state |<p>Please check the temperature</p> |`last(/ZYXEL AAM1212-51 IES-612 by SNMP/zyxel.aam1212.temp[{#SNMPINDEX}])>{#ZYXEL.TEMP.THRESH.HIGH}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/422668-discussion-thread-for-official-zabbix-templates-for-zyxel).

## Known Issues

- Description: Incorrect handling of SNMP bulk requests. Disable the use of bulk requests in the SNMP interface settings.
  - Version: all versions firmware
  - Device: ZYXEL AAM1212-51 / IES-612

