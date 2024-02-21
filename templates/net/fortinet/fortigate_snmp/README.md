
# FortiGate by SNMP

## Overview

This template is designed for the effortless deployment of FortiGate monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- FortiGate v7.2.5

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>Threshold of CPU utilization for warning trigger in %.</p>|`90`|
|{$ICMP_LOSS_WARN}|<p>Threshold of ICMP packets loss for warning trigger in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Threshold of average ICMP response time for warning trigger in seconds.</p>|`0.15`|
|{$SNMP.TIMEOUT}|<p>The time interval for SNMP availability trigger.</p>|`5m`|
|{$MEMORY.UTIL.MAX}|<p>Threshold of memory utilization for trigger in %.</p>|`90`|
|{$DISK.FREE.WARN}|<p>Threshold of disk free space for warning trigger in %.</p>|`20`|
|{$DISK.FREE.CRIT}|<p>Threshold of disk free space for critical trigger in %.</p>|`10`|
|{$VPN.NAME.MATCHES}|<p>This macro is used in VPN tunnel discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$VPN.NAME.NOT_MATCHES}|<p>This macro is used in VPN tunnel discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$VPN.STATE.CONTROL}|<p>This macro is used in "Tunnel down" trigger. Can be used with interface name as context.</p>|`1`|
|{$HA.MEMBER.SN.MATCHES}|<p>This macro is used in HA members discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$HA.MEMBER.SN.NOT_MATCHES}|<p>This macro is used in HA members discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$IF.ERRORS.WARN}|<p>Threshold of error packets rate for warning trigger. Can be used with interface name as context.</p>|`2`|
|{$IF.UTIL.MAX}|<p>Threshold of interface bandwidth utilization for warning trigger in %. Can be used with interface name as context.</p>|`95`|
|{$IFCONTROL}|<p>Macro for operational state of the interface for "Link down" trigger. Can be used with interface name as context.</p>|`1`|
|{$NET.IF.IFADMINSTATUS.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFNAME.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`^6$`|
|{$NET.IF.IFTYPE.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.HEALTH.NAME.MATCHES}|<p>This macro is used in SD-WAN health-checks discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SDWAN.HEALTH.NAME.NOT_MATCHES}|<p>This macro is used in SD-WAN health-checks discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.HEALTH.IF.CONTROL}|<p>This macro is used in "Health check state is dead" trigger. Can be used with health check name as context.</p>|`1`|
|{$SDWAN.HEALTH.IF.LOSS.WARN}|<p>Threshold of packets loss for warning trigger in %. Can be used with health check name as context.</p>|`20`|
|{$WC.NAME.MATCHES}|<p>This macro is used in Wireless discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$WC.NAME.NOT_MATCHES}|<p>This macro is used in Wireless discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$WC.STATE.CONTROL}|<p>This macro is used in "Connection down" trigger. Can be used with interface name as context.</p>|`1`|
|{$WC.UPDATE.CONTROL}|<p>This macro is used in "Receiving firmware update" trigger. Can be used with interface name as context.</p>|`1`|
|{$WC.CPU.UTIL.CRIT}|<p>Threshold of WTP CPU utilization for warning trigger in %. Can be used with interface name as context.</p>|`90`|
|{$WC.MEMORY.UTIL.MAX}|<p>Threshold of WTP memory utilization for trigger in %. Can be used with interface name as context.</p>|`90`|
|{$VDOM.NAME.MATCHES}|<p>This macro is used in Virtual domain discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$VDOM.NAME.NOT_MATCHES}|<p>This macro is used in Virtual domain discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FortiGate: Firmware version|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Firmware version of the device.</p>|SNMP agent|system.hw.firmware<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: Hardware model name|<p>MIB: ENTITY-MIB</p><p>Model of the device.</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: Hardware serial number|<p>MIB: ENTITY-MIB</p><p>Serial number of the device.</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person. If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FortiGate: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should include the full name and version identification of the system's hardware type, software operating system, and networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FortiGate: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet`, `3rd floor`). If the location is unknown, the value is the zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FortiGate: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node. By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FortiGate: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity. This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining 'what kind of box' is being managed. For example, if vendor 'Flintstones, Inc.' was assigned the subtree 1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its 'Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FortiGate: System uptime|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Time since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.uptime[fgSysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|FortiGate: Number of CPUs|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of processors.</p>|SNMP agent|system.cpu.num<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: CPU utilization|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>CPU utilization in %.</p>|SNMP agent|system.cpu.util[fgSysCpuUsage.0]|
|FortiGate: ICMP ping|<p>Host accessibility by ICMP.</p><p>0 - ICMP ping fails.</p><p>1 - ICMP ping successful.</p>|Simple check|icmpping|
|FortiGate: ICMP loss|<p>Percentage of lost packets.</p>|Simple check|icmppingloss|
|FortiGate: ICMP response time|<p>ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|
|FortiGate: SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|FortiGate: Total memory|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The total memory allocated for the tasks.</p>|SNMP agent|vm.memory.total[fgSysMemCapacity.0]|
|FortiGate: Used memory|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Current memory utilization (percentage).</p>|SNMP agent|vm.memory.used[fgSysMemUsage.0]|
|FortiGate: Available memory|<p>The total memory freed for utilization.</p>|Calculated|vm.memory.available[fgSysMemFree.0]|
|FortiGate: Memory utilization|<p>Memory utilization in %.</p>|Calculated|vm.memory.util[memoryUsedPercentage.0]|
|FortiGate: IPv4 Active sessions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of active sessions on the device.</p>|SNMP agent|net.ipv4.sessions[fgSysSesCount.0]|
|FortiGate: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items.</p>|SNMP trap|snmptrap.fallback|
|FortiGate: Total disk space|<p>Total hard disk capacity.</p>|SNMP agent|vfs.fs.total[fgSysDiskCapacity.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: Used disk space|<p>Current hard disk usage.</p>|SNMP agent|vfs.fs.used[fgSysDiskUsage.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|FortiGate: Free disk space|<p>Free hard disk capacity.</p>|Calculated|vfs.fs.free|
|FortiGate: Free disk percentage|<p>Free disk space, in %.</p>|Calculated|vfs.fs.pfree|
|FortiGate: Active IPsec VPN tunnels|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The number of IPsec VPN tunnels with at least one SA.</p>|SNMP agent|vpn.tunnel.active[fgVpnTunnelUpCount.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|FortiGate: Active SSL VPN users|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The current number of users logged in through SSL-VPN tunnels in the virtual domain.</p>|SNMP agent|vpn.users.count[fgVpnSslStatsLoginUsers.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|FortiGate: SSL VPN state|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Whether SSL-VPN is enabled on this virtual domain.</p>|SNMP agent|vpn.ssl.state[fgVpnSslState.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|FortiGate: Blocked intrusions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of intrusions blocked per second.</p>|SNMP agent|ips.blocked[fgIpsIntrusionsBlocked.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|FortiGate: Total detected intrusions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Total number of intrusions detected per second.</p>|SNMP agent|ips.detected.total[fgIpsIntrusionsDetected.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|FortiGate: Detected critical intrusions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of critical severity intrusions detected per second.</p>|SNMP agent|ips.detected.crit[fgIpsCritSevDetections.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|FortiGate: Detected high intrusions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of high severity intrusions detected per second.</p>|SNMP agent|ips.detected.high[fgIpsHighSevDetections.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|FortiGate: Detected medium intrusions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of medium severity intrusions detected per second.</p>|SNMP agent|ips.detected.med[fgIpsMedSevDetections.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|FortiGate: Detected low intrusions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of low severity intrusions detected per second.</p>|SNMP agent|ips.detected.low[fgIpsLowSevDetections.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|FortiGate: Detected info intrusions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of info severity intrusions detected per second.</p>|SNMP agent|ips.detected.info[fgIpsInfoSevDetections.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|FortiGate: Detected anomaly based intrusions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of intrusions DECed as anomalies per second.</p>|SNMP agent|ips.detected.anomaly[fgIpsAnomalyDetections.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|FortiGate: Detected signature based intrusions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of intrusions detected by signature per second.</p>|SNMP agent|ips.detected.sign[fgIpsSignatureDetections.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|FortiGate: IPS database version|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>IPS signature database version installed on the device.</p>|SNMP agent|ips.database.version[fgSysVersionIps.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|FortiGate: HA mode|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>High-availability mode (Standalone, A-A or A-P).</p>|SNMP agent|ha.mode[fgHaSystemMode.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: HA cluster group ID|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>HA cluster group ID device is configured for.</p>|SNMP agent|ha.cluster.group_id[fgHaGroupId.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: HA cluster group name|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>HA cluster group name.</p>|SNMP agent|ha.cluster.group_name[fgHaGroupName.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: HA cluster priority|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>HA clustering priority of the device (default = 128).</p>|SNMP agent|ha.cluster.priority[fgHaPriority.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: HA cluster primary override|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Status of a primary override flag.</p>|SNMP agent|ha.cluster.override[fgHaOverride.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: HA config sync|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Configuration of an automatic configuration synchronization (enabled or disabled).</p>|SNMP agent|ha.auto.sync[fgHaAutoSync.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FortiGate: HA load-balancing schedule|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Load-balancing schedule of cluster (in A-A mode).</p>|SNMP agent|ha.schedule[fgHaSchedule.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|FortiGate: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/FortiGate by SNMP/system.hw.serialnumber,#1)<>last(/FortiGate by SNMP/system.hw.serialnumber,#2) and length(last(/FortiGate by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|FortiGate: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/FortiGate by SNMP/system.name,#1)<>last(/FortiGate by SNMP/system.name,#2) and length(last(/FortiGate by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|FortiGate: Device has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/FortiGate by SNMP/system.uptime[fgSysUpTime.0])<10m`|Info|**Manual close**: Yes|
|FortiGate: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/FortiGate by SNMP/system.cpu.util[fgSysCpuUsage.0],5m)>{$CPU.UTIL.CRIT}`|Warning||
|FortiGate: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/FortiGate by SNMP/icmpping,#3)=0`|High||
|FortiGate: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/FortiGate by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/FortiGate by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>FortiGate: Unavailable by ICMP ping</li></ul>|
|FortiGate: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/FortiGate by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>FortiGate: Unavailable by ICMP ping</li><li>FortiGate: High ICMP ping loss</li></ul>|
|FortiGate: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/FortiGate by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>FortiGate: Unavailable by ICMP ping</li></ul>|
|FortiGate: High memory utilization|<p>The system is running out of free memory.</p>|`min(/FortiGate by SNMP/vm.memory.util[memoryUsedPercentage.0],5m)>{$MEMORY.UTIL.MAX}`|Average||
|FortiGate: Free disk space is too low|<p>Left disk space is too low.</p>|`last(/FortiGate by SNMP/vfs.fs.pfree)<{$DISK.FREE.CRIT}`|High||
|FortiGate: Free disk space is low|<p>Left disk space is not enough.</p>|`last(/FortiGate by SNMP/vfs.fs.pfree)<{$DISK.FREE.WARN}`|Warning|**Depends on**:<br><ul><li>FortiGate: Free disk space is too low</li></ul>|

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>Discovering CPUs from FORTINET-FORTIGATE-MIB.</p>|SNMP agent|cpu.discovery|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU Core {#CPU.ID}: Average usage over 1min|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The processor's CPU usage (percentage), which is an average calculated over the last minute.</p><p>(only valid for processors types that support this statistic).</p>|SNMP agent|system.cpu.usage[fgProcessorUsage.{#SNMPINDEX}]|
|CPU Core {#CPU.ID}: Average user usage over 1min|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The processor's CPU user space usage, which is an average calculated over the last minute.</p><p>(only valid for processors types that support this statistic).</p>|SNMP agent|system.cpu.usage[fgProcessorUserUsage.{#SNMPINDEX}]|
|CPU Core {#CPU.ID}: Average system usage over 1min|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The processor's CPU system space usage, which is an average calculated over the last minute.</p><p>(only valid for processors types that support this statistic).</p>|SNMP agent|system.cpu.usage[fgProcessorSysUsage.{#SNMPINDEX}]|

### LLD rule VPN tunnel discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VPN tunnel discovery|<p>Discovering VPN tunnels from FORTINET-FORTIGATE-MIB.</p>|SNMP agent|vpn.tunnel.discovery|

### Item prototypes for VPN tunnel discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VPN {#VPN.NAME}: Tunnel Status|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Current status of tunnel (up or down).</p>|SNMP agent|vpn.tunnel.status[fgVpnTunEntStatus.{#SNMPINDEX}]|

### Trigger prototypes for VPN tunnel discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VPN {#VPN.NAME}: Tunnel down|<p>This trigger expression works as follows:<br>1. It can be triggered if the current tunnel state is down.<br>2. `{&#36;VPN.STATE.CONTROL}=1` - a user can redefine context macro to value - 0. That marks this notification as not important. No new trigger will be fired if this tunnel is down.</p>|`{$VPN.STATE.CONTROL:"{#VPN.NAME}"}=1 and last(/FortiGate by SNMP/vpn.tunnel.status[fgVpnTunEntStatus.{#SNMPINDEX}])=1 and (last(/FortiGate by SNMP/vpn.tunnel.status[fgVpnTunEntStatus.{#SNMPINDEX}],#1)<>last(/FortiGate by SNMP/vpn.tunnel.status[fgVpnTunEntStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packets can be passed</p><p>- If ifAdminStatus is down(2), then ifOperStatus should be down(2).</p><p>- If ifAdminStatus is changed to up(1), then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic.</p><p>- It should change to dormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection).</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state.</p><p>- It should remain in the notPresent(6) state if the interface has missing (typically, hardware) components.</p>|SNMP agent|net.if.status[ifOperStatus.{#SNMPINDEX}]|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system </p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol. For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system </p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol. For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system </p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA) through updating the syntax of the IANAifType textual convention.</p>|SNMP agent|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second.</p><p>If this object reports a value of `n` then the speed of the interface is somewhere in the range of `n-500,000` to `n+499,999`.</p><p>For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth.</p><p>For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|SNMP agent|net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for the 'eternal off' interfaces).<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of `.diff`.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/FortiGate by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/FortiGate by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/FortiGate by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/FortiGate by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/FortiGate by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/FortiGate by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/FortiGate by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/FortiGate by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/FortiGate by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/FortiGate by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/FortiGate by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/FortiGate by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/FortiGate by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/FortiGate by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

### LLD rule HA members discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HA members discovery|<p>Discovering HA members from FORTINET-FORTIGATE-MIB.</p>|SNMP agent|ha.discovery|

### Item prototypes for HA members discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HA {#HA.ID}: Serial number|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Serial number of the HA cluster member.</p>|SNMP agent|ha.serialnumber[fgHaStatsSerial.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|HA {#HA.ID}: CPU usage|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>CPU usage of the specified cluster member (percentage).</p>|SNMP agent|ha.cpu.usage[fgHaStatsCpuUsage.{#SNMPINDEX}]|
|HA {#HA.ID}: Memory usage|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Memory usage of the specified cluster member (percentage).</p>|SNMP agent|ha.mem.usage[fgHaStatsMemUsage.{#SNMPINDEX}]|
|HA {#HA.ID}: Network bandwidth usage|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Network bandwidth usage of specified cluster member (bps).</p>|SNMP agent|ha.net.usage[fgHaStatsNetUsage.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|HA {#HA.ID}: Session count|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Current session count of specified cluster member.</p>|SNMP agent|ha.session.count[fgHaStatsSesCount.{#SNMPINDEX}]|
|HA {#HA.ID}: Packets processed|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of packets processed by the specified cluster member per second.</p>|SNMP agent|ha.packets.rate[fgHaStatsPktCount.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|HA {#HA.ID}: Bytes processed|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of bytes processed by the specified cluster member per second.</p>|SNMP agent|ha.bytes.rate[fgHaStatsByteCount.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|HA {#HA.ID}: IPS events|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of IDS/IPS events triggered on the specified cluster member per second.</p>|SNMP agent|ha.ips.events[fgHaStatsIdsCount.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|HA {#HA.ID}: Anti-virus events|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of anti-virus events triggered on the specified cluster member per second.</p>|SNMP agent|ha.av.events[fgHaStatsAvCount.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|HA {#HA.ID}: Hostname|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Host name of the specified cluster member.</p>|SNMP agent|ha.hostname[fgHaStatsHostname.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|HA {#HA.ID}: Sync status|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Current HA Sync status.</p>|SNMP agent|ha.sync.status[fgHaStatsSyncStatus.{#SNMPINDEX}]|
|HA {#HA.ID}: Global checksum|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Current HA global checksum value.</p>|SNMP agent|ha.checksum.global[fgHaStatsGlobalChecksum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|HA {#HA.ID}: Primary serial number|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Serial number of the primary during the last synch attempt (successful or not).</p>|SNMP agent|ha.primary.serialnumber[fgHaStatsMasterSerial.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### LLD rule Hardware sensors discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hardware sensors discovery|<p>Discovering hardware sensors from FORTINET-FORTIGATE-MIB.</p>|SNMP agent|hw.sensor.discovery|

### Item prototypes for Hardware sensors discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor {#SENSOR.NAME}: Value|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>A string representation of the value of the sensor. Because sensors can present data in different formats, string representation is most general format. Interpretation of the value (units of measure, for example) is dependent on the individual sensor.</p>|SNMP agent|hw.sensor.value[fgHwSensorEntValue.{#SENSOR.ID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Sensor {#SENSOR.NAME}: Alarm status|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>If the sensor has an alarm threshold and has exceeded it, this will indicate its status. Not all sensors have alarms. </p>|SNMP agent|hw.sensor.status[fgHwSensorEntAlarmStatus.{#SENSOR.ID}]|

### LLD rule SoC3 discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SoC3 discovery|<p>Discovering SoC3 NP6Lite processors from FORTINET-FORTIGATE-MIB.</p>|SNMP agent|soc3.discovery|

### Item prototypes for SoC3 discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SoC3 {#CPU.ID}: Packets dropped|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The total number of packets dropped by this processor</p><p>(only valid for processors types that support this statistic).</p>|SNMP agent|soc3.np6lite.pkt.dropped[fgProcessorPktDroppedCount.{#CPU.ID}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|SoC3 {#CPU.ID}: Packets received|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The total number of packets received by this processor</p><p>(only valid for processors types that support this statistic).</p>|SNMP agent|soc3.np6lite.pkt.received[fgProcessorPktRxCount.{#CPU.ID}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|SoC3 {#CPU.ID}: Packets transmitted|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The total number of packets transmitted by this processor</p><p>(only valid for processors types that support this statistic).</p>|SNMP agent|soc3.np6lite.pkt.transmitted[fgProcessorPktRxCount.{#CPU.ID}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### LLD rule SD-WAN health-checks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN health-checks discovery|<p>Discovering SD-WAN health-checks from FORTINET-FORTIGATE-MIB.</p>|SNMP agent|sdwan_health.discovery|

### Item prototypes for SD-WAN health-checks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN [{#HNAME}]:[{#IFNAME}]: Health check state|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Health check state on a specific member link.</p>|SNMP agent|sdwan_health.state[fgVWLHealthCheckLinkState.{#SNMPINDEX}]|
|SD-WAN [{#HNAME}]:[{#IFNAME}]: Latency|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The average latency of a health check on a specific member link in float number within last 30 probes.</p>|SNMP agent|sdwan_health.latency[fgVWLHealthCheckLinkLatency.{#SNMPINDEX}]|
|SD-WAN [{#HNAME}]:[{#IFNAME}]: Jitter|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The average jitter of a health check on a specific member link in float number within last 30 probes.</p>|SNMP agent|sdwan_health.jitter[fgVWLHealthCheckLinkJitter.{#SNMPINDEX}]|
|SD-WAN [{#HNAME}]:[{#IFNAME}]: Packets loss|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The packet loss percentage of a health check on a specific member link in float number within last 30 probes.</p>|SNMP agent|sdwan_health.loss[fgVWLHealthCheckLinkPacketLoss.{#SNMPINDEX}]|
|SD-WAN [{#HNAME}]:[{#IFNAME}]: Packets sent per second|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of packets sent by a health check on a specific member link per second.</p>|SNMP agent|sdwan_health.sent[fgVWLHealthCheckLinkPacketSend.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|SD-WAN [{#HNAME}]:[{#IFNAME}]: Packets received per second|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of packets received by a health check on a specific member link per second.</p>|SNMP agent|sdwan_health.received[fgVWLHealthCheckLinkPacketRecv.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### Trigger prototypes for SD-WAN health-checks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|SD-WAN [{#HNAME}]:[{#IFNAME}]: Health check state is dead|<p>This trigger expression works as follows:<br>1. It can be triggered if the health check state is dead.<br>2. `{&#36;SDWAN.HEALTH.IF.CONTROL:"{#HNAME}"}=1` - a user can redefine context macro to value - 0. That marks this health check as not important. No new trigger will be fired if this health check is dead.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the health check state was up to (1) sometime before.<br><br>WARNING: If closed manually, it will not fire again on the next poll because of `diff`.</p>|`{$SDWAN.HEALTH.IF.CONTROL:"{#HNAME}"}=1 and last(/FortiGate by SNMP/sdwan_health.state[fgVWLHealthCheckLinkState.{#SNMPINDEX}])=1 and (last(/FortiGate by SNMP/sdwan_health.state[fgVWLHealthCheckLinkState.{#SNMPINDEX}],#1)<>last(/FortiGate by SNMP/sdwan_health.state[fgVWLHealthCheckLinkState.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|SD-WAN [{#HNAME}]:[{#IFNAME}]: High packets loss|<p>High level of packets loss detected.</p>|`min(/FortiGate by SNMP/sdwan_health.loss[fgVWLHealthCheckLinkPacketLoss.{#SNMPINDEX}],5m)>{$SDWAN.HEALTH.IF.LOSS.WARN:"{#HNAME}"}`|Warning||

### LLD rule Wireless discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Wireless discovery|<p>Discovering wireless access points from FORTINET-FORTIGATE-MIB.</p>|SNMP agent|wireless.discovery|

### Item prototypes for Wireless discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WTP {#WC.NAME}: Administrative status|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the administrative status of this WTP.</p><p></p><p>The following enumerated values are supported:</p><p>discovered(1) - This WTP was discovered though discovery or join request messages.</p><p>disable(2) - Controller is configured to not provide service to this WTP.</p><p>enable(3) - Controller is configured to provide service to this WTP.</p><p>other(0) - The administration state of the WTP is unknown.</p>|SNMP agent|wc.admin.status[fgWcWtpConfigWtpAdmin.{#SNMPINDEX}]|
|WTP {#WC.NAME}: Location|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the location of this WTP.</p>|SNMP agent|wc.location[fgWcWtpConfigWtpLocation.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Profile name|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the profile configured for this WTP.</p>|SNMP agent|wc.profile[fgWcWtpConfigWtpProfile.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Radio enabled|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Whether radio is enabled for this WTP.</p>|SNMP agent|wc.radio.enabled[fgWcWtpConfigRadioEnable.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Radio ATPC status|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Whether radio automatic TX power control is enabled on this WTP.</p>|SNMP agent|wc.radio.atpc.status[fgWcWtpConfigRadioAutoTxPowerControl.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Radio ATPC low limit|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the low limit of radio automatic TX power control configured for this WTP, in dBm.</p>|SNMP agent|wc.radio.atpc.low_limit[fgWcWtpConfigRadioAutoTxPowerLow.{#SNMPINDEX}]|
|WTP {#WC.NAME}: Radio ATPC high limit|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the high limit of radio automatic TX power control configured for this WTP, in dBm.</p>|SNMP agent|wc.radio.atpc.high_limit[fgWcWtpConfigRadioAutoTxPowerHigh.{#SNMPINDEX}]|
|WTP {#WC.NAME}: Radio TX power level|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the radio TX power setting configured for this WTP, in percentage.</p>|SNMP agent|wc.radio.power_level[fgWcWtpConfigRadioTxPowerLevel.{#SNMPINDEX}]|
|WTP {#WC.NAME}: Radio band|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the radio band configured for this WTP.</p>|SNMP agent|wc.radio.band[fgWcWtpConfigRadioBand.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Background scan|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Whether background scan is enabled on this WTP.</p>|SNMP agent|wc.background.scan[fgWcWtpConfigRadioApScan.{#SNMPINDEX}]|
|WTP {#WC.NAME}: All VAPs selected|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Whether all Wireless Virtual Access Point (VAP) are selected for this WTP.</p>|SNMP agent|wc.vaps.all[fgWcWtpConfigVapAll.{#SNMPINDEX}]|
|WTP {#WC.NAME}: VAPs list|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents a list of Wireless Virtual Access Points (VAP) configured for this WTP.</p>|SNMP agent|wc.vaps.list[fgWcWtpConfigVaps.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: IP Address type|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the IP address type of a WTP.</p>|SNMP agent|wc.ip.type[fgWcWtpSessionWtpIpAddressType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: IP Address|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the IP address of a WTP that corresponds to the IP address in the IP packet header.</p>|SNMP agent|wc.ip.addr[fgWcWtpSessionWtpIpAddress.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Local IP Address type|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the local IP address type of a WTP.</p>|SNMP agent|wc.local_ip.type[fgWcWtpSessionWtpLocalIpAddressType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Local IP Address|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the local IP address of a WTP and models the CAPWAP Local IPv4 Address or CAPWAP Local IPv6 Address fields [RFC5415].</p><p>If a Network Address Translation (NAT) device is present between WTP and AC, the value of fgWcWtpWtpLocalIpAddress will be different from the value of fgWcWtpWtpIpAddress.</p>|SNMP agent|wc.local_ip.addr[fgWcWtpSessionWtpLocalIpAddress.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Base MAC Address|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the WTP's Base MAC Address, which MAY be assigned to the primary Ethernet interface.</p><p>The instance of the object corresponds to the Base MAC Address sub-element in the CAPWAP protocol [RFC5415].</p>|SNMP agent|wc.base.mac[fgWcWtpSessionWtpBaseMacAddress.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Connection status|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the connection status of a WTP to AC.</p><p></p><p>The following enumerated values are supported:</p><p>offLine(1) - The WTP is not connected.</p><p>onLine(2) - The WTP is connected.</p><p>downloadingImage(3) - The WTP is downloading software image from the AC on joining.</p><p>connectedImage(4) - The AC is pushing software image to the connected WTP.</p><p>other(0) - The WTP connection status is unknown.</p>|SNMP agent|wc.conn.status[fgWcWtpSessionConnectionState.{#SNMPINDEX}]|
|WTP {#WC.NAME}: Uptime|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the time since the WTP boots.</p>|SNMP agent|wc.uptime[fgWcWtpSessionWtpUpTime.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|WTP {#WC.NAME}: Daemon uptime|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the time since the WTP daemon has been started.</p>|SNMP agent|wc.daemon.uptime[fgWcWtpSessionWtpDaemonUpTime.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|WTP {#WC.NAME}: Session uptime|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the time since the WTP connects to the AC.</p>|SNMP agent|wc.session.uptime[fgWcWtpSessionWtpSessionUpTime.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|WTP {#WC.NAME}: Model number|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the model number of a WTP.</p>|SNMP agent|wc.model[fgWcWtpSessionWtpModelNumber.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Hardware version|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the hardware version of a WTP.</p>|SNMP agent|wc.hardware.version[fgWcWtpSessionWtpHwVersion.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|WTP {#WC.NAME}: Software version|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the software version of a WTP.</p>|SNMP agent|wc.software.version[fgWcWtpSessionWtpSwVersion.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|WTP {#WC.NAME}: Bootloader version|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the boot loader version of a WTP.</p>|SNMP agent|wc.boot.version[fgWcWtpSessionWtpBootVersion.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|WTP {#WC.NAME}: Region code|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the region code programmed for this WTP.</p>|SNMP agent|wc.region_code[fgWcWtpSessionWtpRegionCode.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|WTP {#WC.NAME}: Connected clients|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the number of clients currently connected to this WTP.</p>|SNMP agent|wc.clients.num[fgWcWtpSessionWtpStationCount.{#SNMPINDEX}]|
|WTP {#WC.NAME}: Bits received|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the number of bits received by this WTP per second.</p>|SNMP agent|wc.rate.in[fgWcWtpSessionWtpByteRxCount.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|WTP {#WC.NAME}: Bits sent|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the number of bits transmitted by this WTP per second.</p>|SNMP agent|wc.rate.out[fgWcWtpSessionWtpByteTxCount.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|WTP {#WC.NAME}: CPU usage|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the current CPU usage of a WTP (percentage).</p>|SNMP agent|wc.cpu.usage[fgWcWtpSessionWtpCpuUsage.{#SNMPINDEX}]|
|WTP {#WC.NAME}: Memory usage|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the current memory usage of a WTP (percentage).</p>|SNMP agent|wc.mem.usage[fgWcWtpSessionWtpMemoryUsage.{#SNMPINDEX}]|
|WTP {#WC.NAME}: Memory capacity|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Represents the total physical memory (RAM) installed.</p>|SNMP agent|wc.mem.size[fgWcWtpSessionWtpMemoryCapacity.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|

### Trigger prototypes for Wireless discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|WTP {#WC.NAME}: Connection is down|<p>This trigger expression works as follows:<br>1. It can be triggered if the current connection status is offLine.<br>2. `{&#36;WC.STATE.CONTROL}=1` - a user can redefine context macro to value - 0. That marks this notification as not important. No new trigger will be fired if this connection is down.</p>|`{$WC.STATE.CONTROL:"{#WC.NAME}"}=1 and last(/FortiGate by SNMP/wc.conn.status[fgWcWtpSessionConnectionState.{#SNMPINDEX}])=1 and (last(/FortiGate by SNMP/wc.conn.status[fgWcWtpSessionConnectionState.{#SNMPINDEX}],#1)<>last(/FortiGate by SNMP/wc.conn.status[fgWcWtpSessionConnectionState.{#SNMPINDEX}],#2))`|High|**Manual close**: Yes|
|WTP {#WC.NAME}: Receiving firmware update|<p>This trigger expression works as follows:<br>1. It can be triggered if the current connection status is downloadingImage.<br>2. `{&#36;WC.UPDATE.CONTROL}=1` - a user can redefine context macro to value - 0. That marks this notification as not important. No new trigger will be fired if this it is downloadingImage.</p>|`{$WC.UPDATE.CONTROL:"{#WC.NAME}"}=1 and last(/FortiGate by SNMP/wc.conn.status[fgWcWtpSessionConnectionState.{#SNMPINDEX}])=3 and (last(/FortiGate by SNMP/wc.conn.status[fgWcWtpSessionConnectionState.{#SNMPINDEX}],#1)<>last(/FortiGate by SNMP/wc.conn.status[fgWcWtpSessionConnectionState.{#SNMPINDEX}],#2))`|Info|**Manual close**: Yes|
|WTP {#WC.NAME}: Sending firmware update|<p>This trigger expression works as follows:<br>1. It can be triggered if the current connection status is connectedImage.<br>2. `{&#36;WC.UPDATE.CONTROL}=1` - a user can redefine context macro to value - 0. That marks this notification as not important. No new trigger will be fired if this it is connectedImage.</p>|`{$WC.UPDATE.CONTROL:"{#WC.NAME}"}=1 and last(/FortiGate by SNMP/wc.conn.status[fgWcWtpSessionConnectionState.{#SNMPINDEX}])=4 and (last(/FortiGate by SNMP/wc.conn.status[fgWcWtpSessionConnectionState.{#SNMPINDEX}],#1)<>last(/FortiGate by SNMP/wc.conn.status[fgWcWtpSessionConnectionState.{#SNMPINDEX}],#2))`|Info|**Manual close**: Yes|
|WTP {#WC.NAME}: Session has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/FortiGate by SNMP/wc.session.uptime[fgWcWtpSessionWtpSessionUpTime.{#SNMPINDEX}])<10m`|Info|**Manual close**: Yes|
|WTP {#WC.NAME}: High CPU utilization|<p>The CPU utilization is too high.</p>|`min(/FortiGate by SNMP/wc.cpu.usage[fgWcWtpSessionWtpCpuUsage.{#SNMPINDEX}],5m)>{$WC.CPU.UTIL.CRIT:"{#WC.NAME}"}`|Warning||
|WTP {#WC.NAME}: High memory utilization|<p>The WTP is running out of free memory.</p>|`min(/FortiGate by SNMP/wc.mem.usage[fgWcWtpSessionWtpMemoryUsage.{#SNMPINDEX}],5m)>{$WC.MEMORY.UTIL.MAX:"{#WC.NAME}"}`|Warning||

### LLD rule Virtual domain discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual domain discovery|<p>Discovering virtual domains from FORTINET-FORTIGATE-MIB.</p>|SNMP agent|vdom.discovery|

### Item prototypes for Virtual domain discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VDOM {#VDOM.NAME}: Operation mode|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Operation mode of the virtual domain (NAT or Transparent).</p>|SNMP agent|vdom.op_mode[fgVdEntOpMode.{#SNMPINDEX}]|
|VDOM {#VDOM.NAME}: HA member state|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>HA cluster member state of the virtual domain on this device.</p>|SNMP agent|vdom.ha.state[fgVdEntHaState.{#SNMPINDEX}]|
|VDOM {#VDOM.NAME}: CPU usage|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>CPU usage of the virtual domain (percentage).</p>|SNMP agent|vdom.cpu.usage[fgVdEntCpuUsage.{#SNMPINDEX}]|
|VDOM {#VDOM.NAME}: Memory usage|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Memory usage of the virtual domain (percentage).</p>|SNMP agent|vdom.mem.usage[fgVdEntCpuUsage.{#SNMPINDEX}]|
|VDOM {#VDOM.NAME}: Active sessions|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>Number of active sessions on the virtual domain.</p>|SNMP agent|vdom.sessions[fgVdEntSesCount.{#SNMPINDEX}]|
|VDOM {#VDOM.NAME}: Sessions rate|<p>MIB: FORTINET-FORTIGATE-MIB</p><p>The session setup rate on the virtual domain per seconds.</p>|SNMP agent|vdom.sessions.rate[fgVdEntSesRate.{#SNMPINDEX}]|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

