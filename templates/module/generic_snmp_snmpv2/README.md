
# Template Module Generic SNMPv2

## Overview

For Zabbix version: 4.4  

## Setup


## Zabbix configuration


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SNMP.TIMEOUT}|<p>-</p>|`5m`|

## Template links

|Name|
|----|
|Template Module ICMP Ping|

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|General|SNMP traps (fallback)|<p>Item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP_TRAP|snmptrap.fallback|
|General|System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p>|SNMP|system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|General|System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p>|SNMP|system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|General|System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP|system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|General|System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p>|SNMP|system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|
|General|System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP|system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Status|Uptime|<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP|system.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p>|
|Status|SNMP agent availability|<p>-</p>|INTERNAL|zabbix[host,snmp,available]|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|System name has changed (new name: {ITEM.VALUE})|<p>System name has changed. Ack to close.</p>|`{TEMPLATE_NAME:system.name.diff()}=1 and {TEMPLATE_NAME:system.name.strlen()}>0`|INFO|<p>Manual close: YES</p>|
|{HOST.NAME} has been restarted (uptime < 10m)|<p>Uptime is less than 10 minutes</p>|`{TEMPLATE_NAME:system.uptime[sysUpTime.0].last()}<10m`|WARNING|<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p>|
|No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`{TEMPLATE_NAME:zabbix[host,snmp,available].max({$SNMP.TIMEOUT})}=0`|WARNING|<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p>|

## Feedback

Please report any issues with the template at https://support.zabbix.com

