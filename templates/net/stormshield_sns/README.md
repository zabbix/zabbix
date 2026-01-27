
# Stormshield SNS by SNMP

## Overview

This template is designed for the effortless deployment of Stormshield SNS monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Stormshield SNS v5.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SNS.CPU.UTIL.CRIT}|<p>Threshold of CPU utilization for Critical trigger in %.</p>|`95`|
|{$SNS.CPU.UTIL.WARN}|<p>Threshold of CPU utilization for Warning trigger in %.</p>|`85`|
|{$SNS.DISK.FREE.CRIT}|<p>Threshold of free disk space for Critical trigger in %.</p>|`10`|
|{$SNS.DISK.FREE.WARN}|<p>Threshold of free disk space for Warning trigger in %.</p>|`20`|
|{$SNS.ICMP.LOSS.WARN}|<p>Threshold of ICMP packet loss for Warning trigger in %.</p>|`20`|
|{$SNS.ICMP.RESPONSE.TIME.WARN}|<p>Threshold of average ICMP response time for Warning trigger in seconds.</p>|`0.15`|
|{$SNS.MEMORY.UTIL.MAX}|<p>Threshold for memory utilization trigger in %.</p>|`90`|
|{$SNS.NET.IF.IFNAME.MATCHES}|<p>Sets regex string of network interface names to allow in discovery.</p>|`.*`|
|{$SNS.NET.IF.IFNAME.NOT_MATCHES}|<p>Sets regex string of network interface names to ignore in discovery.</p>|`^(sslvpn\|ipsec)`|
|{$SNS.STORAGE.TYPE.MATCHES}|<p>Sets regex string of storage types to allow in discovery.</p>|`.1.3.6.1.2.1.25.2.1.4`|
|{$SNS.STORAGE.TYPE.NOT_MATCHES}|<p>Sets regex string of storage types to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$SNS.STORAGE.DESCR.MATCHES}|<p>Sets regex string of storage descriptions to allow in discovery.</p>|`^(/data\|/log)$`|
|{$SNS.STORAGE.DESCR.NOT_MATCHES}|<p>Sets regex string of storage descriptions to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$SNS.SNMP.TIMEOUT}|<p>Time interval for SNMP availability trigger.</p>|`5m`|
|{$SNS.SNMP.INTERVAL}|<p>Time interval for SNMP agent items.</p>|`1m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SNMP walk HA members|<p>Used for discovering HA members from the Stormshield MIB.</p>|SNMP agent|sns.ha.members.walk|
|SNMP walk health|<p>Used for discovering health status from the Stormshield MIB.</p>|SNMP agent|sns.health.walk|
|SNMP walk autoupdate|<p>Used for discovering updates from the Stormshield MIB.</p>|SNMP agent|sns.update.auto.walk|
|SNMP walk CPU temperature|<p>Used for discovering CPU temperature from the Stormshield MIB.</p>|SNMP agent|sns.cpu.temp.walk|
|SNMP walk CPU usage|<p>Used for discovering CPU usage from the HOST-RESOURCES-MIB.</p>|SNMP agent|sns.cpu.usage.walk|
|SNMP walk network interfaces|<p>Used for discovering network interfaces from the Stormshield MIB.</p>|SNMP agent|sns.net.if.walk|
|SNMP walk disk|<p>Used for discovering disks from the Stormshield MIB.</p>|SNMP agent|sns.disk.walk|
|SNMP walk fan|<p>Used for discovering fans from the Stormshield MIB.</p>|SNMP agent|sns.fan.walk|
|SNMP walk power supply|<p>Used for discovering the power supply from the Stormshield MIB.</p>|SNMP agent|sns.psu.walk|
|SNMP walk storage|<p>Used for discovering storage from the HOST-RESOURCES-MIB.</p>|SNMP agent|sns.storage.walk|
|SNMP walk memory|<p>Used for discovering system memory utilization from the STORMSHIELD-SYSTEM-MONITOR-MIB.</p>|SNMP agent|sns.memory.util.walk|
|ASQ TCP connection count|<p>MIB: STORMSHIELD-ASQ-STATS-MIB</p><p>ASQ stateful TCP connection count.</p>|SNMP agent|asq.alarm.stateful.tcp[snsASQStatsStatefulTcpConn.0]|
|ASQ UDP connection count|<p>MIB: STORMSHIELD-ASQ-STATS-MIB</p><p>ASQ stateful UDP connection count.</p>|SNMP agent|asq.alarm.stateful.udp[snsASQStatsStatefulUdpConn.0]|
|ASQ major alarm count|<p>MIB: STORMSHIELD-ASQ-STATS-MIB</p><p>ASQ major alarm count.</p>|SNMP agent|asq.alarm.stateful[snsASQStatsStatefulMajorAlarm.0]|
|ASQ minor alarm count|<p>MIB: STORMSHIELD-ASQ-STATS-MIB</p><p>ASQ minor alarm count.</p>|SNMP agent|asq.alarm.stateful[snsASQStatsStatefulMinorAlarm.0]|
|HA: Faulty HA links|<p>MIB: STORMSHIELD-HA-MIB</p><p>Number of faulty HA links.</p>|SNMP agent|ha.links.faulty[snsNbFaultyHALinks.0]|
|HA: Active firewalls|<p>MIB: STORMSHIELD-HA-MIB</p><p>Number of active firewalls.</p>|SNMP agent|ha.node.active[snsNbActiveNode.0]|
|HA: Firewalls in the cluster|<p>MIB: STORMSHIELD-HA-MIB</p><p>Number of firewalls in the HA cluster.</p>|SNMP agent|ha.node.count[snsNbNode.0]|
|HA: Firewalls not replying|<p>MIB: STORMSHIELD-HA-MIB</p><p>Number of firewalls registered in the HA cluster but not replying.</p>|SNMP agent|ha.node.dead[snsNbDeadNode.0]|
|HA: Synchronization status|<p>MIB: STORMSHIELD-HA-MIB</p><p>Firewall configuration synchronization status:</p><p>  1: Synced, </p><p>  0: Not synced, </p><p>  -1: Unknown/Error.</p>|SNMP agent|ha.sync.status[snsHASyncStatus.0]|
|VPN: Number of dead VPN tunnels|<p>MIB: STORMSHIELD-IPSEC-STATS-MIB</p><p>Number of dead security associations.</p>|SNMP agent|vpn.tunnel.dead[snsIPSECStatsSADDead.0]|
|VPN: Number of dying VPN tunnels|<p>MIB: STORMSHIELD-IPSEC-STATS-MIB</p><p>Number of security associations at end of life.</p>|SNMP agent|vpn.tunnel.dying[snsIPSECStatsSADDying.0]|
|VPN: Number of mature VPN tunnels|<p>MIB: STORMSHIELD-IPSEC-STATS-MIB</p><p>Number of established security associations.</p>|SNMP agent|vpn.tunnel.mature[snsIPSECStatsSADMature.0]|
|VPN: Incoming policies|<p>MIB: STORMSHIELD-IPSEC-STATS-MIB</p><p>Number of incoming security policies.</p>|SNMP agent|ipsec.policies.in[snsIPSECStatsSPDIn.0]|
|VPN: Outgoing policies|<p>MIB: STORMSHIELD-IPSEC-STATS-MIB</p><p>Number of outgoing security policies.</p>|SNMP agent|ipsec.policies.out[snsIPSECStatsSPDOut.0]|
|Buffer memory|<p>MIB: UCD-SNMP-MIB</p><p>Buffer memory in bytes.</p>|SNMP agent|host.memory.buffer[memBuffer.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000`</p></li></ul>|
|Cached memory|<p>MIB: UCD-SNMP-MIB</p><p>Cached memory in bytes.</p>|SNMP agent|host.memory.cached[memCached.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000`</p></li></ul>|
|Free memory|<p>MIB: UCD-SNMP-MIB</p><p>Free memory in bytes.</p>|SNMP agent|host.memory.free[memAvailReal.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000`</p></li></ul>|
|Total memory|<p>MIB: UCD-SNMP-MIB</p><p>Total physical memory (RAM) installed in bytes.</p>|SNMP agent|host.memory.total[memTotalReal.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Available memory|<p>Available memory in bytes</p><p>(Available = Free + Cached + Buffer).</p>|Calculated|host.memory.available|
|Used memory|<p>Used memory in bytes.</p>|Calculated|host.memory.used|
|Memory utilization|<p>Memory utilization in %.</p>|Calculated|host.memory.utilization|
|Active IPsec policy name|<p>MIB: STORMSHIELD-POLICY-MIB</p><p>Active IPsec policy name.</p>|SNMP agent|policy.ipsec[snsPolicySlotNameIPsec]<p>**Preprocessing**</p><ul><li><p>Does not match regular expression: `^$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Active filtering policy name|<p>MIB: STORMSHIELD-POLICY-MIB</p><p>Active filtering policy name.</p>|SNMP agent|policy.filter[snsPolicySlotName]<p>**Preprocessing**</p><ul><li><p>Does not match regular expression: `^$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Model|<p>MIB: STORMSHIELD-PROPERTY-MIB</p><p>Firewall model.</p>|SNMP agent|property.hardware.model[snsModel.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|System name|<p>MIB: STORMSHIELD-PROPERTY-MIB</p><p>Stormshield Firewall system name.</p>|SNMP agent|property.hardware.name[snsSystemName.0]|
|System node name|<p>MIB: STORMSHIELD-PROPERTY-MIB</p><p>Stormshield Firewall system node name.</p>|SNMP agent|property.hardware.node_name[snsSystemNodeName.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Serial number|<p>MIB: STORMSHIELD-PROPERTY-MIB</p><p>Stormshield Firewall serial number.</p>|SNMP agent|property.hardware.serial[snsSerialNumber.0]|
|Version|<p>MIB: STORMSHIELD-PROPERTY-MIB</p><p>Stormshield Firewall version.</p>|SNMP agent|property.hardware.version[snsVersion.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Date|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Stormshield Firewall current date (%Y-%m-%d %T).</p>|SNMP agent|system.date[snsDate.0]|
|Uptime|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Stormshield Firewall uptime.</p>|SNMP agent|system.hardware.uptime[snsUptime.0]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to the availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown.</p>|Zabbix internal|zabbix[host,snmp,available]|
|SNMP traps (fallback)|<p>Used for collecting all SNMP traps unmatched by other `snmptrap` items.</p>|SNMP trap|snmptrap.fallback|
|ICMP ping|<p>Host accessibility by ICMP.</p><p>0 - ICMP ping failed.</p><p>1 - ICMP ping successful.</p>|Simple check|icmpping|
|ICMP loss|<p>Percentage of lost packets.</p>|Simple check|icmppingloss|
|ICMP response time|<p>ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|
|Protected host memory utilization|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Protected host memory utilization percentage.</p><p></p><p>Warning: This OID might not be functional for SNS versions lower than 5.0.</p>|Dependent item|system.memory.host[snsMemHost.0]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.10.1.2.0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Fragment memory utilization|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Fragment memory utilization percentage.</p><p></p><p>Warning: This OID might not be functional for SNS versions lower than 5.0.</p>|Dependent item|system.memory.frag[snsMemFrag.0]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.10.1.3.0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ICMP memory utilization|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>ICMP memory utilization percentage.</p><p></p><p>Warning: This OID might not be functional for SNS versions lower than 5.0.</p>|Dependent item|system.memory.icmp[snsMemIcmp.0]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.10.1.4.0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ASQ connection memory utilization|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Utilization percentage of ASQ connection memory.</p><p></p><p>Warning: This OID might not be functional for SNS versions lower than 5.0.</p>|Dependent item|system.memory.conn[snsMemConn.0]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.10.1.5.0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Etherstate connection memory utilization|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Utilization percentage of etherstate connection memory.</p><p></p><p>Warning: This OID might not be functional for SNS versions lower than 5.0.</p>|Dependent item|system.memory.ether[snsMemEther.0]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.10.1.6.0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data tracking memory utilization|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Utilization percentage of data tracking memory.</p><p></p><p>Warning: This OID might not be functional for SNS versions lower than 5.0.</p>|Dependent item|system.memory.data_track[snsMemDataTrack.0]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.10.1.7.0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|System memory utilization|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Current memory utilization percentage.</p><p></p><p>Warning: This OID might not be functional for SNS versions lower than 5.0.</p>|Dependent item|system.memory.system[snsMemSystem.0]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.10.1.8.0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|User memory utilization|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>User-space memory utilization percentage.</p><p></p><p>Warning: This OID might not be functional for SNS versions lower than 5.0.</p>|Dependent item|system.memory.user[snsMemUser.0]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.10.1.9.0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Socket memory utilization|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Socket memory utilization percentage.</p><p></p><p>Warning: This OID might not be functional for SNS versions lower than 5.0.</p>|Dependent item|system.memory.mbuf[snsMemMbuf.0]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.10.1.10.0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|SNS: Faulty HA link|<p>There is at least one faulty HA link.</p>|`last(/Stormshield SNS by SNMP/ha.links.faulty[snsNbFaultyHALinks.0],#1)>0`|Average||
|SNS: HA synchronization error|<p>The cluster HA is not synchronized properly.</p>|`last(/Stormshield SNS by SNMP/ha.sync.status[snsHASyncStatus.0],#1)<0`|Average||
|SNS: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Stormshield SNS by SNMP/host.memory.utilization,5m)>{$SNS.MEMORY.UTIL.MAX}`|Average||
|SNS: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Stormshield SNS by SNMP/property.hardware.name[snsSystemName.0],#1)<>last(/Stormshield SNS by SNMP/property.hardware.name[snsSystemName.0],#2) and length(last(/Stormshield SNS by SNMP/property.hardware.name[snsSystemName.0]))>0`|Info|**Manual close**: Yes|
|SNS: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Stormshield SNS by SNMP/property.hardware.serial[snsSerialNumber.0],#1)<>last(/Stormshield SNS by SNMP/property.hardware.serial[snsSerialNumber.0],#2) and length(last(/Stormshield SNS by SNMP/property.hardware.serial[snsSerialNumber.0]))>0`|Info|**Manual close**: Yes|
|SNS: Device has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Stormshield SNS by SNMP/system.hardware.uptime[snsUptime.0])<10m`|Info||
|SNS: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Stormshield SNS by SNMP/zabbix[host,snmp,available],{$SNS.SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>SNS: Unavailable by ICMP ping</li></ul>|
|SNS: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Stormshield SNS by SNMP/icmpping,#3)=0`|High||
|SNS: High ICMP ping loss|<p>ICMP ping loss detected.</p>|`min(/Stormshield SNS by SNMP/icmppingloss,5m)>{$SNS.ICMP.LOSS.WARN} and min(/Stormshield SNS by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>SNS: Unavailable by ICMP ping</li></ul>|
|SNS: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Stormshield SNS by SNMP/icmppingsec,5m)>{$SNS.ICMP.RESPONSE.TIME.WARN}`|Warning|**Depends on**:<br><ul><li>SNS: High ICMP ping loss</li><li>SNS: Unavailable by ICMP ping</li></ul>|

### LLD rule Autoupdate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Autoupdate discovery|<p>Used for discovering updates from the Stormshield MIB.</p>|Dependent item|update.auto.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Autoupdate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Autoupdate [{#UPDATE_NAME}]: Last update date|<p>MIB: STORMSHIELD-AUTOUPDATE-MIB</p><p>Date of the last update of a subsystem.</p>|Dependent item|system.update.last[snsAutoupdateLast.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.9.1.1.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Autoupdate [{#UPDATE_NAME}]: Update state|<p>MIB: STORMSHIELD-AUTOUPDATE-MIB</p><p>State of the update of a subsystem.</p>|Dependent item|system.update.state[snsAutoupdateState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.9.1.1.3.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Autoupdate discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|SNS: Autoupdate [{#UPDATE_NAME}]: Not up to date|<p>The autoupdate is not up to date (never started or started more than a year ago).</p>|`(last(/Stormshield SNS by SNMP/system.update.state[snsAutoupdateState.{#SNMPINDEX}])=4) or (last(/Stormshield SNS by SNMP/system.update.state[snsAutoupdateState.{#SNMPINDEX}])=5)`|Info||

### LLD rule CPU temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU temperature discovery|<p>Used for discovering the CPU temperature from the Stormshield MIB.</p>|Dependent item|cpu.temperature.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for CPU temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU [{#CPU_ID}]: Temperature|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Temperature in degrees Celsius.</p>|Dependent item|system.cpu.temperature[snsCpuTemp.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.7.1.2.{#SNMPINDEX}`</p></li></ul>|

### LLD rule CPU usage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU usage discovery|<p>Used for discovering the CPU usage from the Stormshield MIB.</p>|Dependent item|cpu.usage.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for CPU usage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU [{#SNMPINDEX}]: Usage|<p>MIB: HOST-RESOURCES-MIB</p><p>The average percentage of time that this processor was not idle over the last minute.</p><p>Implementations may approximate this one minute smoothing period if necessary.</p><p>Note that cpu 196608 = cpu 0, 196609 = 1, ...</p>|Dependent item|system.cpu.usage[hrProcessorLoad.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.25.3.3.1.2.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for CPU usage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|SNS: CPU [{#SNMPINDEX}]: CPU utilization too high|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Stormshield SNS by SNMP/system.cpu.usage[hrProcessorLoad.{#SNMPINDEX}],5m)>{$SNS.CPU.UTIL.CRIT}`|High||
|SNS: CPU [{#SNMPINDEX}]: High CPU utilization|<p>The CPU utilization is high. The system might be slow to respond.</p>|`min(/Stormshield SNS by SNMP/system.cpu.usage[hrProcessorLoad.{#SNMPINDEX}],5m)>{$SNS.CPU.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>SNS: CPU [{#SNMPINDEX}]: CPU utilization too high</li></ul>|

### LLD rule Disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk discovery|<p>Used for discovering disks from the Stormshield MIB.</p>|Dependent item|disk.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk [{#DISK_ID}]: Disk name|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Name of the disk.</p>|Dependent item|system.disk.name[snsDiskEntryDiskName.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.5.1.2.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Disk [{#DISK_ID}]: Member of a RAID array|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Indicates whether the disk is part of a RAID array.</p>|Dependent item|system.disk.RAID[snsDiskEntryIsRaid.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.5.1.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk [{#DISK_ID}]: RAID status|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>RAID status.</p>|Dependent item|system.disk.status[snsDiskEntryRaidStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.5.1.5.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Disk [{#DISK_ID}]: SMART info test result|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Result of the SMART diagnostic tests.</p>|Dependent item|system.disk.result[snsDiskEntrySmartResult.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.5.1.3.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>Used for discovering the fan from the Stormshield MIB.</p>|Dependent item|fan.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#FAN_ID}]: Fan name|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Name of the fan.</p>|Dependent item|system.fan.name[snsFanName.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.9.1.2.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Fan [{#FAN_ID}]: Fan status|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Status of the fan.</p>|Dependent item|system.fan.status[snsFanStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.9.1.3.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Fan [{#FAN_ID}]: Fan speed|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Speed of the fan.</p>|Dependent item|system.fan.speed[snsFanRpm.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.9.1.4.{#SNMPINDEX}`</p></li></ul>|

### LLD rule HA members discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HA members discovery|<p>Used for discovering HA members from the Stormshield MIB.</p>|Dependent item|ha.members.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for HA members discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HA members [{#HA_ID}]: Firewall active/passive|<p>MIB: STORMSHIELD-HA-MIB</p><p>Indicates whether the firewall is active.</p>|Dependent item|ha.active[snsHAActive.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.11.7.1.10.{#SNMPINDEX}`</p></li></ul>|
|HA members [{#HA_ID}]: HA licence|<p>MIB: STORMSHIELD-HA-MIB</p><p>HA licence.</p>|Dependent item|ha.license[snsHALicence.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.11.7.1.6.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|HA members [{#HA_ID}]: Firewall model|<p>MIB: STORMSHIELD-HA-MIB</p><p>Firewall model.</p>|Dependent item|ha.model[snsModel.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.11.7.1.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HA members [{#HA_ID}]: Is online|<p>MIB: STORMSHIELD-HA-MIB</p><p>Firewall is online.</p>|Dependent item|ha.online[snsOnline.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.11.7.1.3.{#SNMPINDEX}`</p></li></ul>|
|HA members [{#HA_ID}]: HA priority|<p>MIB: STORMSHIELD-HA-MIB</p><p>HA priority.</p>|Dependent item|ha.priority[snsHAPriority.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.11.7.1.8.{#SNMPINDEX}`</p></li></ul>|
|HA members [{#HA_ID}]: HA quality|<p>MIB: STORMSHIELD-HA-MIB</p><p>HA quality.</p>|Dependent item|ha.quality[snsHAQuality.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.11.7.1.7.{#SNMPINDEX}`</p></li></ul>|
|HA members [{#HA_ID}]: Firewall serial|<p>MIB: STORMSHIELD-HA-MIB</p><p>Firewall serial number.</p>|Dependent item|ha.serial[snsFwSerial.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.11.7.1.2.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HA members [{#HA_ID}]: Firewall status|<p>MIB: STORMSHIELD-HA-MIB</p><p>HA status forced:</p><p>  -2: Unknown forced status</p><p>  -1: No peer found</p><p>  0: No forced status</p><p>  1: Forced active</p><p>  2: Forced passive</p>|Dependent item|ha.status[snsHAStatusForced.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.11.7.1.9.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|HA members [{#HA_ID}]: Firewall uptime|<p>MIB: STORMSHIELD-HA-MIB</p><p>Firewall uptime.</p>|Dependent item|ha.uptime[snsHAUptime.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.11.7.1.11.{#SNMPINDEX}`</p></li></ul>|

### LLD rule Health status discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Health status discovery|<p>Used for discovering the health status from the Stormshield MIB.</p>|Dependent item|health.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Health status discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Health status firewall [{#HEALTH_ID}]: Certificates|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield Firewall.</p>|Dependent item|health.certificates.status[snsCertHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.11.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: CPU|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield Firewall CPU:</p><p>  - `Good` if CPU load is 90% or lower</p><p>  - `Minor` if CPU load is above 90% for less than 5 minutes</p><p>  - `Major` if CPU load is above 90% for more than 5 minutes</p>|Dependent item|health.cpu.status[snsCpuHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.7.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: CPU temperature|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield Firewall CPU temperature:</p><p>  - `Good`; at least 20°C below max temperature</p><p>  - `Minor`; less than 20°C below max temperature</p><p>  - `Major`; 5°C below max temperature</p>|Dependent item|health.cpu.temperature[snsCpuTempHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.15.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: CRLs|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield firewall CRLs.</p>|Dependent item|health.crl.status[snsCRLHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.12.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: Disk|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield Firewall disk:</p><p>  - `Good` if the disks are working correctly</p><p>  - `Minor` if the disks are not working correctly</p><p>  - `Major` if the disks are not working correctly and have raised an alarm</p>|Dependent item|health.disk.status[snsDiskHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.9.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: Fans|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield Firewall fans:</p><p>  - `Good` if the fans are working correctly</p><p>  - `Minor` if the fans are not working correctly</p><p>  - `Major` if the fans are not working correctly and have raised an alarm</p>|Dependent item|health.fan.status[snsFanHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.6.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: HA link|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield Firewall HA link:</p><p>  - `Good` if the HA link is working correctly</p><p>  - `Minor` if the HA link is not working correctly (may be down)</p><p>  - `Major` if the HA link is not working (is down)</p>|Dependent item|health.ha.link.status[snsHaLinkHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.4.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: HA mode|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current status of Stormshield Firewall HA mode:</p><p>  - `None` if HA is not active</p><p>  - `Active` if the firewall is the active status</p><p>  - `Passive` if the firewall is the passive status</p>|Dependent item|health.ha.mode.status[snsHaModeHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.3.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: Memory|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield Firewall memory:</p><p>  - `Good` if memory load is 80% or lower</p><p>  - `Minor` if memory load is above 80% for less than 15 minutes</p><p>  - `Major` if memory load is above 80% for more than 15 minutes</p>|Dependent item|health.memory.status[snsMemHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.8.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: Admin password|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield Firewall Admin Password:</p><p>  - `Good` if the date when the admin password was last changed is less than a year ago</p><p>  - `Minor` if the date when the admin password was last changed is more than a year ago</p><p>  - `Major` if the admin password is the default password</p>|Dependent item|health.password.status[snsPasswdHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.14.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: Power supply|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield Firewall power supply:</p><p>  - `Good` if the power supply is working correctly</p><p>  - `Minor` if the power supply is not working correctly</p><p>  - `Major` if the power supply is not working correctly and has raised an alarm.</p>|Dependent item|health.power.status[snsPowerSupplyHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.5.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Health status firewall [{#HEALTH_ID}]: RAID|<p>MIB: STORMSHIELD-HEALTH-MONITOR-MIB</p><p>Current health status of Stormshield Firewall RAID:</p><p>  - `Good` if the RAID is working in optimal mode</p><p>  - `Minor` if the RAID is not working in optimal mode</p><p>  - `Major` if the RAID is not working in optimal mode and has raised an alarm</p>|Dependent item|health.raid.status[snsRaidHealth.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.16.2.1.10.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Health status discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|SNS: Health status firewall [{#HEALTH_ID}]: CPU is overheating|<p>The CPU is not working correctly and has raised an alarm.</p>|`last(/Stormshield SNS by SNMP/health.cpu.temperature[snsCpuTempHealth.{#SNMPINDEX}])=5`|High||
|SNS: Health status firewall [{#HEALTH_ID}]: The disk is not working correctly|<p>The disks are not working correctly and have raised an alarm.</p>|`last(/Stormshield SNS by SNMP/health.disk.status[snsDiskHealth.{#SNMPINDEX}])=5`|High||
|SNS: Health status firewall [{#HEALTH_ID}]: The fan is not working correctly|<p>The fans are not working correctly and have raised an alarm.</p>|`last(/Stormshield SNS by SNMP/health.fan.status[snsFanHealth.{#SNMPINDEX}])=5`|Average||
|SNS: Health status firewall [{#HEALTH_ID}]: HA link is down|<p>The HA link is not working (is down).</p>|`last(/Stormshield SNS by SNMP/health.ha.link.status[snsHaLinkHealth.{#SNMPINDEX}])=5`|High||
|SNS: Health status firewall [{#HEALTH_ID}]: Admin password is not secured|<p>The admin password is the default password; please change it.</p>|`last(/Stormshield SNS by SNMP/health.password.status[snsPasswdHealth.{#SNMPINDEX}])=5`|High||
|SNS: Health status firewall [{#HEALTH_ID}]: The power supply is not working correctly|<p>The power supply is not working correctly and has raised an alarm.</p>|`last(/Stormshield SNS by SNMP/health.power.status[snsPowerSupplyHealth.{#SNMPINDEX}])=5`|High||
|SNS: Health status firewall [{#HEALTH_ID}]: The RAID is not working correctly|<p>The RAID is not working in optimal mode and has raised an alarm.</p>|`last(/Stormshield SNS by SNMP/health.raid.status[snsRaidHealth.{#SNMPINDEX}])=5`|Average||

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Used for discovering network interfaces from the Stormshield MIB.</p>|Dependent item|network.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IF_NAME}]: System interface name|<p>MIB: STORMSHIELD-IF-MIB</p><p>System interface name.</p>|Dependent item|net.if.name[snsifName.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Interface [{#IF_NAME}]: Interface protected|<p>MIB: STORMSHIELD-IF-MIB</p><p>Indicates whether the interface is protected.</p>|Dependent item|net.if.protected[snsifProtected.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.37.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Accepted packets|<p>MIB: STORMSHIELD-IF-MIB</p><p>Number of accepted packets.</p>|Dependent item|net.if.accepted[snsifPktAccepted.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.11.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Blocked packets|<p>MIB: STORMSHIELD-IF-MIB</p><p>Number of packets that have been blocked.</p>|Dependent item|net.if.blocked[snsifPktBlocked.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.12.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: TCP connection established|<p>MIB: STORMSHIELD-IF-MIB</p><p>TCP connection established.</p>|Dependent item|net.if.established[snsifTcpConn.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.21.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: UDP connection established|<p>MIB: STORMSHIELD-IF-MIB</p><p>UDP connection established.</p>|Dependent item|net.if.established[snsifUdpConn.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.22.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Incoming current throughput|<p>MIB: STORMSHIELD-IF-MIB</p><p>Current incoming throughput in B/s.</p>|Dependent item|net.if.in[snsifInCurThroughput.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.25.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Outgoing current throughput|<p>MIB: STORMSHIELD-IF-MIB</p><p>Current outgoing throughput in B/s.</p>|Dependent item|net.if.out[snsifOutCurThroughput.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.26.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Incoming data bytes|<p>MIB: STORMSHIELD-IF-MIB</p><p>Incoming data bytes.</p>|Dependent item|net.if.in[snsifInTotalBytes.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.29.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Outgoing data bytes|<p>MIB: STORMSHIELD-IF-MIB</p><p>Outgoing data bytes.</p>|Dependent item|net.if.out[snsifOutTotalBytes.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.30.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Incoming TCP data bytes|<p>MIB: STORMSHIELD-IF-MIB</p><p>Incoming TCP data bytes.</p>|Dependent item|net.if.in[snsifInTcpBytes.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.31.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Outgoing TCP data bytes|<p>MIB: STORMSHIELD-IF-MIB</p><p>Outgoing TCP data bytes.</p>|Dependent item|net.if.out[snsifOutTcpBytes.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.32.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Incoming UDP data bytes|<p>MIB: STORMSHIELD-IF-MIB</p><p>Incoming UDP data bytes.</p>|Dependent item|net.if.in[snsifInUdpBytes.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.33.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Outgoing UDP data bytes|<p>MIB: STORMSHIELD-IF-MIB</p><p>Outgoing UDP data bytes.</p>|Dependent item|net.if.out[snsifOutUdpBytes.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.34.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Max input flow rate|<p>MIB: STORMSHIELD-IF-MIB</p><p>Maximum incoming throughput in B/s.</p>|Dependent item|net.if.in.max[snsifInMaxThroughput.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.27.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Max output flow rate|<p>MIB: STORMSHIELD-IF-MIB</p><p>Maximum outgoing throughput in B/s.</p>|Dependent item|net.if.max_out[snsifOutMaxThroughput.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.28.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Current TCP connection count|<p>MIB: STORMSHIELD-IF-MIB</p><p>Current TCP connection count.</p>|Dependent item|net.if.TCP_count[snsifTcpConnCount.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.23.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IF_NAME}]: Current UDP connection count|<p>MIB: STORMSHIELD-IF-MIB</p><p>Current UDP connection count.</p>|Dependent item|net.if.UDP_count[snsifUdpConnCount.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.4.1.1.24.{#SNMPINDEX}`</p></li></ul>|

### LLD rule Power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply discovery|<p>Used for discovering power supplies from the Stormshield MIB.</p>|Dependent item|psu.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU [{#POWER_ID}]: Power status|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Indicates whether the power supply is powered by electricity.</p>|Dependent item|system.psu.power[snsPowerSupplyPowered.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.6.1.2.{#SNMPINDEX}`</p></li></ul>|
|PSU [{#POWER_ID}]: Status|<p>MIB: STORMSHIELD-SYSTEM-MONITOR-MIB</p><p>Indicates the status of the power supply.</p>|Dependent item|system.psu.status[snsPowerSupplyStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.11256.1.10.6.1.3.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage discovery|<p>Used for discovering storage from the Stormshield MIB.</p>|Dependent item|storage.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage [{#STORAGE_DESCR}]: Storage size|<p>MIB: HOST-RESOURCES-MIB</p><p>Total memory in the data file in bytes.</p>|Dependent item|host.storage.size[hrStorageSizedata.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.25.2.3.1.5.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `4096`</p></li></ul>|
|Storage [{#STORAGE_DESCR}]: Used storage|<p>MIB: HOST-RESOURCES-MIB</p><p>Used memory in the data file in bytes.</p>|Dependent item|host.storage.used[hrStorageUseddata.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.25.2.3.1.6.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `4096`</p></li></ul>|
|Storage [{#STORAGE_DESCR}]: Utilization|<p>Memory utilization in %.</p>|Calculated|host.storage.utilization[.{#SNMPINDEX}]|

### Trigger prototypes for Storage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|SNS: Storage [{#STORAGE_DESCR}]: Utilization is high|<p>The data file is running out of free memory.</p>|`min(/Stormshield SNS by SNMP/host.storage.utilization[.{#SNMPINDEX}],5m)>{$SNS.MEMORY.UTIL.MAX}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

