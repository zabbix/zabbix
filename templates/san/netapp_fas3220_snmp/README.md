
# NetApp FAS3220 by SNMP

## Overview

The template to monitor SAN NetApp FAS3220 cluster by Zabbix SNMP agent.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- NetApp FAS3220, firmware version: 5.3.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a host for FAS3220 with cluster management IP as SNMPv2 interface.

2. Link the template to the host.

3. Customize macro values if needed.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IF.UTIL.MAX}||`95`|
|{$IF.ERRORS.WARN}|||
|{$CPU.UTIL.CRIT}|<p>The critical threshold of the CPU utilization expressed in %.</p>|`90`|
|{$FAS3220.FS.NAME.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$FAS3220.FS.NAME.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`snapshot`|
|{$FAS3220.FS.TYPE.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p><p>Value should be integer:</p><p>  2 - flexibleVolume,</p><p>  3 - aggregate,</p><p>  4 - stripedAggregate,</p><p>  5 - stripedVolume.</p>|`.*`|
|{$FAS3220.FS.TYPE.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p><p>Value should be integer:</p><p>  2 - flexibleVolume,</p><p>  3 - aggregate,</p><p>  4 - stripedAggregate,</p><p>  5 - stripedVolume.</p>|`CHANGE_IF_NEEDED`|
|{$FAS3220.NET.PORT.TYPE.MATCHES}|<p>This macro is used in net ports discovery. Can be overridden on the host or linked template level.</p><p>{#TYPE} is integer. Possible values: physical, if-group, vlan, undef.</p>|`.*`|
|{$FAS3220.NET.PORT.TYPE.NOT_MATCHES}|<p>This macro is used in net ports discovery. Can be overridden on the host or linked template level.</p><p>{#TYPE} is integer. Possible values: physical, if-group, vlan, undef.</p>|`CHANGE_IF_NEEDED`|
|{$FAS3220.NET.PORT.ROLE.MATCHES}|<p>This macro is used in net ports discovery. Can be overridden on the host or linked template level.</p><p>{#ROLE} is integer. Possible values:</p><p>  0 - undef</p><p>  1 - cluster</p><p>  2 - data</p><p>  3 - node-mgmt</p><p>  4 - intercluster</p><p>  5 - cluster-mgmt</p>|`.*`|
|{$FAS3220.NET.PORT.ROLE.NOT_MATCHES}|<p>This macro is used in net ports discovery. Can be overridden on the host or linked template level.</p><p>{#ROLE} is integer. Possible values:</p><p>  0 - undef</p><p>  1 - cluster</p><p>  2 - data</p><p>  3 - node-mgmt</p><p>  4 - intercluster</p><p>  5 - cluster-mgmt</p>|`CHANGE_IF_NEEDED`|
|{$FAS3220.NET.PORT.NAME.MATCHES}|<p>This macro is used in net ports discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$FAS3220.NET.PORT.NAME.NOT_MATCHES}|<p>This macro is used in net ports discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$FAS3220.FS.PUSED.MAX.CRIT}|<p>Maximum percentage of disk used. Can be used with {#FSNAME} as context.</p>|`90`|
|{$FAS3220.FS.AVAIL.MIN.CRIT}|<p>Minimum available space on the disk. Can be used with {#FSNAME} as context.</p>|`10G`|
|{$FAS3220.FS.TIME}|<p>The time during which disk usage may exceed the threshold. Can be used with {#FSNAME} as context.</p>|`10m`|
|{$FAS3220.FS.USE.PCT}|<p>Macro define what threshold will be used for disk space trigger:</p><p>  0 - use Bytes ({$FAS3220.FS.AVAIL.MIN.CRIT})</p><p>  1 - use percents ({$FAS3220.FS.PUSED.MAX.CRIT})</p><p>Can be used with {#FSNAME} as context.</p>|`1`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Product version|<p>MIB: NETAPP-MIB</p><p>Version string for the software running on this platform.</p>|SNMP agent|fas3220.inventory[productVersion]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Product firmware version|<p>Version string for the firmware running on this platform.</p>|SNMP agent|fas3220.inventory[productFirmwareVersion]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Failed disks count|<p>The number of disks that are currently broken.</p>|SNMP agent|fas3220.disk[diskFailedCount]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Failed disks message|<p>If diskFailedCount is non-zero, this is a string describing the failed disk or disks. Each failed disk is described.</p>|SNMP agent|fas3220.disk[diskFailedMessage]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>Time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|System location|<p>MIB: SNMPv2-MIB</p><p>Physical location of the node (e.g., `equipment room`, `3rd floor`). If not provided, the value is a zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person. If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity. This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|ICMP ping|<p>The host accessibility by ICMP ping.</p><p></p><p>0 - ICMP ping fails;</p><p>1 - ICMP ping successful.</p>|Simple check|icmpping|
|ICMP loss|<p>The percentage of lost packets.</p>|Simple check|icmppingloss|
|ICMP response time|<p>The ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp FAS3220: Number of failed disks has changed|<p>{{ITEM.LASTVALUE2}.regsub("(.*)", \1)}</p>|`last(/NetApp FAS3220 by SNMP/fas3220.disk[diskFailedCount])>0 and last(/NetApp FAS3220 by SNMP/fas3220.disk[diskFailedMessage],#1)<>last(/NetApp FAS3220 by SNMP/fas3220.disk[diskFailedMessage],#2)`|Warning||
|NetApp FAS3220: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/NetApp FAS3220 by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/NetApp FAS3220 by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/NetApp FAS3220 by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/NetApp FAS3220 by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>NetApp FAS3220: No SNMP data collection</li></ul>|
|NetApp FAS3220: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/NetApp FAS3220 by SNMP/system.name,#1)<>last(/NetApp FAS3220 by SNMP/system.name,#2) and length(last(/NetApp FAS3220 by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|NetApp FAS3220: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/NetApp FAS3220 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>NetApp FAS3220: Unavailable by ICMP ping</li></ul>|
|NetApp FAS3220: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/NetApp FAS3220 by SNMP/icmpping,#3)=0`|High||
|NetApp FAS3220: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/NetApp FAS3220 by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/NetApp FAS3220 by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>NetApp FAS3220: Unavailable by ICMP ping</li></ul>|
|NetApp FAS3220: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/NetApp FAS3220 by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>NetApp FAS3220: High ICMP ping loss</li><li>NetApp FAS3220: Unavailable by ICMP ping</li></ul>|

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>Discovery of CPU metrics per node</p>|SNMP agent|fas3220.cpu.discovery|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node {#NODE.NAME}: CPU utilization|<p>The average, over the last minute, of the percentage of time that this processor was not idle.</p>|SNMP agent|fas3220.cpu[cDOTCpuBusyTimePerCent, "{#NODE.NAME}"]|

### Trigger prototypes for CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp FAS3220: Node {#NODE.NAME}: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/NetApp FAS3220 by SNMP/fas3220.cpu[cDOTCpuBusyTimePerCent, "{#NODE.NAME}"],5m)>{$CPU.UTIL.CRIT}`|Warning||

### LLD rule Cluster metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster metrics discovery|<p>Discovery of Cluster metrics per node</p>|SNMP agent|fas3220.cluster.discovery|

### Item prototypes for Cluster metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node {#NODE.NAME}: Location|<p>Node Location. Same as sysLocation for a specific node.</p>|SNMP agent|fas3220.cluster[nodeLocation, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE.NAME}: Model|<p>Node Model. Same as productModel for a specific node.</p>|SNMP agent|fas3220.cluster[nodeModel, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE.NAME}: Serial number|<p>Node Serial Number. Same as productSerialNum for a specific node.</p>|SNMP agent|fas3220.cluster[nodeSerialNumber, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE.NAME}: Uptime|<p>Node uptime. Same as sysUpTime for a specific node.</p>|SNMP agent|fas3220.cluster[nodeUptime, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Node {#NODE.NAME}: Health|<p>Whether or not the node can communicate with the cluster.</p>|SNMP agent|fas3220.cluster[nodeHealth, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE.NAME}: NVRAM battery status|<p>An indication of the current status of the NVRAM battery or batteries.</p><p>Batteries which are fully or partially discharged may not fully protect the system during a crash. The end-of-life status values are based on the manufacturer's recommended life for the batteries.</p><p>Possible values:</p><p>ok(1),</p><p>partiallyDischarged(2),</p><p>fullyDischarged(3),</p><p>notPresent(4),</p><p>nearEndOfLife(5),</p><p>atEndOfLife(6),</p><p>unknown(7),</p><p>overCharged(8),</p><p>fullyCharged(9).</p>|SNMP agent|fas3220.cluster[nodeNvramBatteryStatus, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE.NAME}: Over-temperature|<p>An indication of whether the hardware is currently operating outside of its recommended temperature range. The hardware will shutdown if the temperature exceeds critical thresholds.</p>|SNMP agent|fas3220.cluster[nodeEnvOverTemperature, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE.NAME}: Failed FAN count|<p>Count of the number of chassis fans that are not operating within the recommended RPM range.</p>|SNMP agent|fas3220.cluster[nodeEnvFailedFanCount, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE.NAME}: Failed FAN message|<p>Text message describing current condition of chassis fans. This is useful only if envFailedFanCount is not zero.</p>|SNMP agent|fas3220.cluster[nodeEnvFailedFanMessage, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE.NAME}: Degraded power supplies count|<p>Count of the number of power supplies that are in degraded mode.</p>|SNMP agent|fas3220.cluster[nodeEnvFailedPowerSupplyCount, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE.NAME}: Degraded power supplies message|<p>Text message describing the state of any power supplies that are currently degraded. This is useful only if envFailedPowerSupplyCount is not zero.</p>|SNMP agent|fas3220.cluster[nodeEnvFailedPowerSupplyMessage, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Cluster metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp FAS3220: Node {#NODE.NAME}: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/NetApp FAS3220 by SNMP/fas3220.cluster[nodeUptime, "{#NODE.NAME}"])<10m`|Info|**Manual close**: Yes|
|NetApp FAS3220: Node {#NODE.NAME}: Node can not communicate with the cluster||`last(/NetApp FAS3220 by SNMP/fas3220.cluster[nodeHealth, "{#NODE.NAME}"])=0`|High|**Manual close**: Yes|
|NetApp FAS3220: Node {#NODE.NAME}: NVRAM battery status is not OK||`last(/NetApp FAS3220 by SNMP/fas3220.cluster[nodeNvramBatteryStatus, "{#NODE.NAME}"])<>1`|Average|**Manual close**: Yes|
|NetApp FAS3220: Node {#NODE.NAME}: Temperature is over than recommended|<p>The hardware will shutdown if the temperature exceeds critical thresholds.</p>|`last(/NetApp FAS3220 by SNMP/fas3220.cluster[nodeEnvOverTemperature, "{#NODE.NAME}"])=2`|High||
|NetApp FAS3220: Node {#NODE.NAME}: Failed FAN count is over than zero|<p>{{ITEM.VALUE2}.regsub("(.*)", \1)}</p>|`last(/NetApp FAS3220 by SNMP/fas3220.cluster[nodeEnvFailedFanCount, "{#NODE.NAME}"])>0 and last(/NetApp FAS3220 by SNMP/fas3220.cluster[nodeEnvFailedFanMessage, "{#NODE.NAME}"])=last(/NetApp FAS3220 by SNMP/fas3220.cluster[nodeEnvFailedFanMessage, "{#NODE.NAME}"])`|High||
|NetApp FAS3220: Node {#NODE.NAME}: Degraded power supplies count is more than zero|<p>{{ITEM.VALUE2}.regsub("(.*)", \1)}</p>|`last(/NetApp FAS3220 by SNMP/fas3220.cluster[nodeEnvFailedPowerSupplyCount, "{#NODE.NAME}"])>0 and last(/NetApp FAS3220 by SNMP/fas3220.cluster[nodeEnvFailedPowerSupplyMessage, "{#NODE.NAME}"])=last(/NetApp FAS3220 by SNMP/fas3220.cluster[nodeEnvFailedPowerSupplyMessage, "{#NODE.NAME}"])`|Average||

### LLD rule HA discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HA discovery|<p>Discovery of high availability metrics per node</p>|SNMP agent|fas3220.ha.discovery|

### Item prototypes for HA discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node {#NODE.NAME}: Cannot takeover cause|<p>The reason node cannot take over it's HA partner {#PARTNER.NAME}.</p><p>Possible states:</p><p>  ok(1),</p><p>  unknownReason(2),</p><p>  disabledByOperator(3),</p><p>  interconnectOffline(4),</p><p>  disabledByPartner(5),</p><p>  takeoverFailed(6),</p><p>  mailboxIsInDegradedState(7),</p><p>  partnermailboxIsInUninitialisedState(8),</p><p>  mailboxVersionMismatch(9),</p><p>  nvramSizeMismatch(10),</p><p>  kernelVersionMismatch(11),</p><p>  partnerIsInBootingStage(12),</p><p>  diskshelfIsTooHot(13),</p><p>  partnerIsPerformingRevert(14),</p><p>  nodeIsPerformingRevert(15),</p><p>  sametimePartnerIsAlsoTryingToTakeUsOver(16),</p><p>  alreadyInTakenoverMode(17),</p><p>  nvramLogUnsynchronized(18),</p><p>  stateofBackupMailboxIsDoubtful(19).</p>|SNMP agent|fas3220.ha[haCannotTakeoverCause, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE.NAME}: HA settings|<p>High Availability configuration settings. The value notConfigured(1) indicates that the HA is not licensed. The thisNodeDead(5) setting indicates that this node has been takenover.</p>|SNMP agent|fas3220.ha[haSettings, "{#NODE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for HA discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp FAS3220: Node {#NODE.NAME}: Node cannot takeover it's HA partner {#PARTNER.NAME}. Reason: {ITEM.VALUE}|<p>Possible reasons:<br>  unknownReason(2),<br>  disabledByOperator(3),<br>  interconnectOffline(4),<br>  disabledByPartner(5),<br>  takeoverFailed(6),<br>  mailboxIsInDegradedState(7),<br>  partnermailboxIsInUninitialisedState(8),<br>  mailboxVersionMismatch(9),<br>  nvramSizeMismatch(10),<br>  kernelVersionMismatch(11),<br>  partnerIsInBootingStage(12),<br>  diskshelfIsTooHot(13),<br>  partnerIsPerformingRevert(14),<br>  nodeIsPerformingRevert(15),<br>  sametimePartnerIsAlsoTryingToTakeUsOver(16),<br>  alreadyInTakenoverMode(17),<br>  nvramLogUnsynchronized(18),<br>  stateofBackupMailboxIsDoubtful(19).</p>|`last(/NetApp FAS3220 by SNMP/fas3220.ha[haCannotTakeoverCause, "{#NODE.NAME}"])<>1`|High||
|NetApp FAS3220: Node {#NODE.NAME}: Node has been taken over|<p>The thisNodeDead(5) setting indicates that this node has been takenover.</p>|`last(/NetApp FAS3220 by SNMP/fas3220.ha[haSettings, "{#NODE.NAME}"])=5`|High||
|NetApp FAS3220: Node {#NODE.NAME}: HA is not licensed|<p>The value notConfigured(1) indicates that the HA is not licensed.</p>|`last(/NetApp FAS3220 by SNMP/fas3220.ha[haSettings, "{#NODE.NAME}"])=1`|Average||

### LLD rule Filesystems discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Filesystems discovery|<p>Filesystems discovery with filter.</p>|SNMP agent|fas3220.fs.discovery|

### Item prototypes for Filesystems discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#VSERVER}{#FSNAME}: Total space used|<p>The total disk space that is in use on {#FSNAME}.</p>|SNMP agent|fas3220.fs[df64UsedKBytes, "{#VSERVER}{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|{#VSERVER}{#FSNAME}: Total space available|<p>The total disk space that is free for use on {#FSNAME}.</p>|SNMP agent|fas3220.fs[df64AvailKBytes, "{#VSERVER}{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|{#VSERVER}{#FSNAME}: Total space|<p>The total capacity in bytes for {#FSNAME}.</p>|SNMP agent|fas3220.fs[df64TotalKBytes, "{#VSERVER}{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|{#VSERVER}{#FSNAME}: Used space percents|<p>The percentage of disk space currently in use on {#FSNAME}.</p>|SNMP agent|fas3220.fs[dfPerCentKBytesCapacity, "{#VSERVER}{#FSNAME}"]|
|{#VSERVER}{#FSNAME}: Saved by compression percents|<p>Provides the percentage of compression savings in a volume, which is ((compr_saved/used)) * 10(compr_saved + 0). This is only returned for volumes.</p>|SNMP agent|fas3220.fs[dfCompressSavedPercent, "{#VSERVER}{#FSNAME}"]|
|{#VSERVER}{#FSNAME}: Saved by deduplication percents|<p>Provides the percentage of deduplication savings in a volume, which is ((dedup_saved/(dedup_saved + used)) * 100). This is only returned for volumes.</p>|SNMP agent|fas3220.fs[dfDedupeSavedPercent, "{#VSERVER}{#FSNAME}"]|

### Trigger prototypes for Filesystems discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp FAS3220: {#VSERVER}{#FSNAME}: Disk space is too low||`min(/NetApp FAS3220 by SNMP/fas3220.fs[df64AvailKBytes, "{#VSERVER}{#FSNAME}"],{$FAS3220.FS.TIME:"{#FSNAME}"})<{$FAS3220.FS.AVAIL.MIN.CRIT:"{#FSNAME}"} and {$FAS3220.FS.USE.PCT:"{#FSNAME}"}=0`|High||
|NetApp FAS3220: {#VSERVER}{#FSNAME}: Disk space is too low||`max(/NetApp FAS3220 by SNMP/fas3220.fs[dfPerCentKBytesCapacity, "{#VSERVER}{#FSNAME}"],{$FAS3220.FS.TIME:"{#FSNAME}"})>{$FAS3220.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and {$FAS3220.FS.USE.PCT:"{#FSNAME}"}=1`|High||

### LLD rule Network ports discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network ports discovery|<p>Network interfaces discovery with filter.</p>|SNMP agent|fas3220.net.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Network ports discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Up by an administrator|<p>Indicates whether the port status is set 'UP' by an administrator.</p>|SNMP agent|fas3220.net.port[netportUpAdmin, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Role|<p>Role of the port. A port must have one of the following roles: cluster(1), data(2), mgmt(3), intercluster(4), cluster-mgmt(5) or undef(0). The cluster port is used to communicate to other node(s) in the cluster. The data port services clients' requests. It is where all the file requests come in. The management port is used by administrator to manage resources within a node. The intercluster port is used to communicate to other cluster. The cluster-mgmt port is used to manage resources within the cluster. The undef role is for the port that has not yet been assigned a role.</p>|SNMP agent|fas3220.net.port[netportRole, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Speed|<p>The speed appears on the port. It can be either undef(0), auto(1), ten Mb/s(2), hundred Mb/s(3), one Gb/s(4), or ten Gb/s(5).</p>|SNMP agent|fas3220.net.port[netportSpeedOper, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Bits received|<p>The total number of octets received on the interface, including framing characters.</p>|SNMP agent|fas3220.net.if[if64InOctets, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Bits sent|<p>The total number of octets transmitted out of the interface, including framing characters.</p>|SNMP agent|fas3220.net.if[if64OutOctets, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>The number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p>|SNMP agent|fas3220.net.if[if64InErrors, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>The number of outbound packets that could not be transmitted because of errors.</p>|SNMP agent|fas3220.net.if[if64OutErrors, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets that were chosen to be discarded even though no errors had been detected to prevent their being deliverable to a higher-layer protocol. One possible reason for discarding such a packet could be to free up buffer space.</p>|SNMP agent|fas3220.net.if[if64InDiscards, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets that were chosen to be discarded even though no errors had been detected to prevent their being transmitted. One possible reason for discarding such a packet could be to free up buffer space.</p>|SNMP agent|fas3220.net.if[if64OutDiscards, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): State|<p>The link-state of the port. Normally it is either UP(2) or DOWN(3).</p>|SNMP agent|fas3220.net.port[netportLinkState, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Health|<p>The health status of the port.</p>|SNMP agent|fas3220.net.port[netportHealthStatus, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Node {#NODE}: port {#IFNAME} ({#TYPE}): Health degraded reason|<p>The list of reasons why the port is marked as degraded.</p>|SNMP agent|fas3220.net.port[netportDegradedReason, "{#NODE}", "{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Network ports discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NetApp FAS3220: Node {#NODE}: port {#IFNAME} ({#TYPE}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/NetApp FAS3220 by SNMP/fas3220.net.if[if64InErrors, "{#NODE}", "{#IFNAME}"],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/NetApp FAS3220 by SNMP/fas3220.net.if[if64OutErrors, "{#NODE}", "{#IFNAME}"],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes|
|NetApp FAS3220: Node {#NODE}: port {#IFNAME} ({#TYPE}): Link down|<p>Link state is not UP and the port status is set 'UP' by an administrator.</p>|`last(/NetApp FAS3220 by SNMP/fas3220.net.port[netportLinkState, "{#NODE}", "{#IFNAME}"])<>2 and last(/NetApp FAS3220 by SNMP/fas3220.net.port[netportUpAdmin, "{#NODE}", "{#IFNAME}"])=1`|Average|**Manual close**: Yes|
|NetApp FAS3220: Node {#NODE}: port {#IFNAME} ({#TYPE}): Port is not healthy|<p>{{ITEM.LASTVALUE2}.regsub("(.*)", \1)}</p>|`last(/NetApp FAS3220 by SNMP/fas3220.net.port[netportHealthStatus, "{#NODE}", "{#IFNAME}"])<>0 and length(last(/NetApp FAS3220 by SNMP/fas3220.net.port[netportDegradedReason, "{#NODE}", "{#IFNAME}"]))>0`|Info||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

