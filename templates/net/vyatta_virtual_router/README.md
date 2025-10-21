
# Vyatta Virtual Router by SNMP

## Overview

Template for Vyatta Virtual Router 1908e

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Vyatta Virtual Router 1908e

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VYATTA.SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$VYATTA.ICMP.LOSS.WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$VYATTA.ICMP.RESPONSE.TIME.WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|
|{$VYATTA.MEMORY.USED.WARN}|<p>Warning threshold of memory utilization.</p>|`80`|
|{$VYATTA.MEMORY.USED.HIGH}|<p>High severity threshold of memory utilization.</p>|`95`|
|{$VYATTA.STORAGE.USED.WARN}|<p>Warning threshold of storage utilization.</p>|`80`|
|{$VYATTA.STORAGE.USED.HIGH}|<p>High severity threshold of storage utilization.</p>|`95`|
|{$VYATTA.CPU.USED.WARN}|<p>Warning threshold of CPU utilization.</p>|`80`|
|{$VYATTA.CPU.USED.HIGH}|<p>High severity threshold of CPU utilization.</p>|`95`|
|{$VYATTA.IFCONTROL}|<p>Macro for the operational state of the interface for the link down trigger. Can be used with the interface name as context.</p>|`1`|
|{$VYATTA.DISCOVERY.STORAGE.NAME.MATCHES}|<p>Sets the name regex filter to use in storage discovery for including.</p>|`.*`|
|{$VYATTA.DISCOVERY.STORAGE.NAME.NOT_MATCHES}|<p>Sets the name regex filter to use in storage discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$VYATTA.DISCOVERY.STORAGE.TYPE.MATCHES}|<p>Sets the type regex filter to use in storage discovery for including.</p>|`.*(\.4\|hrStorageFixedDisk)$`|
|{$VYATTA.DISCOVERY.STORAGE.TYPE.NOT_MATCHES}|<p>Sets the type regex filter to use in storage discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$VYATTA.DISCOVERY.CPU.MATCHES}|<p>Sets the OID regex filter to use in CPU discovery for including. Matches `hrDeviceProcessor` from MIB: HOST-RESOURCES-TYPES - the device type identifier used for a CPU.</p>|`^.+\.3$`|
|{$VYATTA.DISCOVERY.CPU.NOT_MATCHES}|<p>Sets the OID regex filter to use in CPU discovery for excluding. Matches `hrDeviceProcessor` from MIB: HOST-RESOURCES-TYPES - the device type identifier used for a CPU.</p>|`CHANGE_IF_NEEDED`|
|{$VYATTA.DISCOVERY.IFACE.ADMINSTATUS.MATCHES}|<p>Sets the administrative status regex filter to use in network interface discovery for including.</p>|`.*`|
|{$VYATTA.DISCOVERY.IFACE.ADMINSTATUS.NOT_MATCHES}|<p>Sets the administrative status regex filter to use in network interface discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$VYATTA.DISCOVERY.IFACE.IFOPERSTATUS.MATCHES}|<p>Sets the operative status regex filter to use in network interface discovery for including.</p>|`^.*$`|
|{$VYATTA.DISCOVERY.IFACE.IFOPERSTATUS.NOT_MATCHES}|<p>Sets the operative status regex filter to use in network interface discovery for excluding. Ignore `notPresent(6)`.</p>|`^6$`|
|{$VYATTA.DISCOVERY.IFACE.IFNAME.MATCHES}|<p>Sets the name regex filter to use in network interface discovery for including.</p>|`^.*$`|
|{$VYATTA.DISCOVERY.IFACE.IFNAME.NOT_MATCHES}|<p>Sets the name regex filter to use in network interface discovery for excluding. Filters out `loopbacks`, `nulls`, docker `veth` links, and `docker0 bridge` by default.</p>|`Macro too long. Please see the template.`|
|{$VYATTA.DISCOVERY.IFACE.IFALIAS.MATCHES}|<p>Sets the alias regex filter to use in network interface discovery for including.</p>|`.*`|
|{$VYATTA.DISCOVERY.IFACE.IFALIAS.NOT_MATCHES}|<p>Sets the alias regex filter to use in network interface discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$VYATTA.DISCOVERY.IFACE.IFDESCR.MATCHES}|<p>Sets the description regex filter to use in network interface discovery for including.</p>|`.*`|
|{$VYATTA.DISCOVERY.IFACE.IFDESCR.NOT_MATCHES}|<p>Sets the description regex filter to use in network interface discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$VYATTA.DISCOVERY.IFACE.IFTYPE.MATCHES}|<p>Sets the type regex filter to use in network interface discovery for including.</p>|`.*`|
|{$VYATTA.DISCOVERY.IFACE.IFTYPE.NOT_MATCHES}|<p>Sets the type regex filter to use in network interface discovery for excluding.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other `snmptrap` items.</p>|SNMP trap|snmptrap.fallback|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|ICMP ping||Simple check|icmpping|
|ICMP loss||Simple check|icmppingloss|
|ICMP response time||Simple check|icmppingsec|
|System name|<p>MIB: SNMPv2-MIB</p><p>System name.</p>|SNMP agent|system.name[sysName.0]|
|System description|<p>MIB: SNMPv2-MIB</p><p>System description.</p>|SNMP agent|system.descr[sysDescr.0]|
|System contact|<p>MIB: SNMPv2-MIB</p><p>System contact details.</p>|SNMP agent|system.contact[sysContact.0]|
|System location|<p>MIB: SNMPv2-MIB</p><p>System contact details.</p>|SNMP agent|system.location[sysLocation.0]|
|System object ID|<p>MIB: SNMPv2-MIB</p><p>System object ID.</p>|SNMP agent|system.objectid[sysObjectID.0]|
|Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from `sysUpTime` in the SNMPv2-MIB [RFC1907] because `sysUpTime` is the uptime of the network management portion of the system.</p>|SNMP agent|system.uptime.hardware[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Uptime (network)|<p>MIB: DISMAN-EVENT-MIB</p><p>Time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.uptime.network[sysUpTimeInstance]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Total memory|<p>MIB: UCD-SNMP-MIB</p><p>The total amount of real/physical memory installed on this host.</p>|SNMP agent|system.memory.total[memTotalReal.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000`</p></li></ul>|
|Free memory|<p>MIB: UCD-SNMP-MIB</p><p>The amount of real/physical memory currently unused or available.</p>|SNMP agent|system.memory.free[memAvailReal.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000`</p></li></ul>|
|Memory (buffers)|<p>MIB: UCD-SNMP-MIB</p><p>The total amount of real or virtual memory currently allocated for use as memory buffers.</p>|SNMP agent|system.memory.buffers[memBuffer.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000`</p></li></ul>|
|Memory (cached)|<p>MIB: UCD-SNMP-MIB</p><p>The total amount of real or virtual memory currently allocated for use as cached memory.</p>|SNMP agent|system.memory.cached[memCached.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000`</p></li></ul>|
|Memory utilization, %||Calculated|system.memory.util<p>**Preprocessing**</p><ul><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li></ul>|
|SNMP walk storage|<p>MIB: HOST-RESOURCES-MIB</p><p>Scanning `HOST-RESOURCES-MIB::hrStorageTable`.</p>|SNMP agent|storage.walk|
|SNMP walk host devices|<p>MIB: HOST-RESOURCES-MIB</p><p>Scanning `HOST-RESOURCES-MIB::hrDeviceTable`.</p>|SNMP agent|devices.walk|
|SNMP walk network interfaces|<p>MIB: IF-MIB</p><p>Scanning `IF-MIB::ifTable` and `IF-MIB::ifXTable`.</p>|SNMP agent|net.if.walk|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Vyatta: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Vyatta Virtual Router by SNMP/zabbix[host,snmp,available],{$VYATTA.SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Vyatta: Unavailable by ICMP ping</li></ul>|
|Vyatta: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Vyatta Virtual Router by SNMP/icmpping,#3)=0`|High||
|Vyatta: High ICMP ping loss|<p>ICMP packet loss detected.</p>|`min(/Vyatta Virtual Router by SNMP/icmppingloss,5m)>{$VYATTA.ICMP.LOSS.WARN} and min(/Vyatta Virtual Router by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Vyatta: Unavailable by ICMP ping</li></ul>|
|Vyatta: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Vyatta Virtual Router by SNMP/icmppingsec,5m)>{$VYATTA.ICMP.RESPONSE.TIME.WARN}`|Warning|**Depends on**:<br><ul><li>Vyatta: High ICMP ping loss</li><li>Vyatta: Unavailable by ICMP ping</li></ul>|
|Vyatta: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Vyatta Virtual Router by SNMP/system.uptime.hardware[hrSystemUptime.0])>0 and last(/Vyatta Virtual Router by SNMP/system.uptime.hardware[hrSystemUptime.0])<10m) or (last(/Vyatta Virtual Router by SNMP/system.uptime.hardware[hrSystemUptime.0])=0 and last(/Vyatta Virtual Router by SNMP/system.uptime.network[sysUpTimeInstance])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Vyatta: No SNMP data collection</li></ul>|
|Vyatta: Memory utilization is high|<p>Memory utilization is high.</p>|`min(/Vyatta Virtual Router by SNMP/system.memory.util, 5m) > {$VYATTA.MEMORY.USED.WARN}`|Warning|**Depends on**:<br><ul><li>Vyatta: Memory utilization is too high</li></ul>|
|Vyatta: Memory utilization is too high|<p>Memory utilization is too high.</p>|`min(/Vyatta Virtual Router by SNMP/system.memory.util, 5m) > {$VYATTA.MEMORY.USED.HIGH}`|High||

### LLD rule Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage discovery|<p>HOST-RESOURCES-MIB::hrStorage discovery with storage filter.</p>|Dependent item|storage.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage [{#STORAGE_NAME}]: Total size||Dependent item|storage.size[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.25.2.3.1.5.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Storage [{#STORAGE_NAME}]: Used size||Dependent item|storage.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.25.2.3.1.6.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Storage [{#STORAGE_NAME}]: Storage utilization, %||Calculated|storage.size.percent[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li></ul>|

### Trigger prototypes for Storage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Vyatta: Storage [{#STORAGE_NAME}]: Storage utilization is high|<p>Storage utilization is high.</p>|`min(/Vyatta Virtual Router by SNMP/storage.size.percent[hrStorageUsed.{#SNMPINDEX}], 5m) > {$VYATTA.STORAGE.USED.WARN}`|Warning|**Depends on**:<br><ul><li>Vyatta: Storage [{#STORAGE_NAME}]: Storage utilization is too high</li></ul>|
|Vyatta: Storage [{#STORAGE_NAME}]: Storage utilization is too high|<p>Storage utilization is too high.</p>|`min(/Vyatta Virtual Router by SNMP/storage.size.percent[hrStorageUsed.{#SNMPINDEX}], 5m) > {$VYATTA.STORAGE.USED.HIGH}`|High||

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery||Dependent item|cpu.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU [{#DESCRIPTION}][{#SNMPINDEX}]: Status|<p>MIB: HOST-RESOURCES-MIB</p><p>The current operational state of the device described by this row of the table.</p><p>* `unknown(1)` indicates that the current state of the device is unknown.</p><p>* `running(2)` indicates that the device is up and running and that no unusual error conditions are known.</p><p>* `warning(3)` indicates that the agent has been informed of an unusual error condition by the operational software (e.g., a disk device driver) but that the device is still operational.</p><p>* `testing(4)` indicates that the device is not available for use because it is in the testing state.</p><p>* `down(5)` is used only when the agent has been informed that the device is not available for any use.</p>|Dependent item|cpu.status[hrDeviceStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.25.3.2.1.5.{#SNMPINDEX}`</p></li></ul>|
|CPU [{#DESCRIPTION}][{#SNMPINDEX}]: Utilization, %|<p>MIB: HOST-RESOURCES-MIB</p><p>The average, over the last minute, of the percentage of time that this processor was not idle.</p>|Dependent item|cpu.utilization[hrProcessorLoad.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.25.3.3.1.2.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Vyatta: CPU [{#DESCRIPTION}][{#SNMPINDEX}]: CPU abnormal state|<p>CPU utilization is too high.</p>|`last(/Vyatta Virtual Router by SNMP/cpu.status[hrDeviceStatus.{#SNMPINDEX}]) <> 2`|High||
|Vyatta: CPU [{#DESCRIPTION}][{#SNMPINDEX}]: CPU utilization is high|<p>CPU utilization is high.</p>|`min(/Vyatta Virtual Router by SNMP/cpu.utilization[hrProcessorLoad.{#SNMPINDEX}], 5m) > {$VYATTA.CPU.USED.WARN}`|Warning|**Depends on**:<br><ul><li>Vyatta: CPU [{#DESCRIPTION}][{#SNMPINDEX}]: CPU utilization is too high</li></ul>|
|Vyatta: CPU [{#DESCRIPTION}][{#SNMPINDEX}]: CPU utilization is too high|<p>CPU utilization is too high.</p>|`min(/Vyatta Virtual Router by SNMP/cpu.utilization[hrProcessorLoad.{#SNMPINDEX}], 5m) > {$VYATTA.CPU.USED.HIGH}`|High||

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery||Dependent item|net.if.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IFNAME}][{#IFALIAS}]: Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The `testing(3)` state indicates that no operational packet scan be passed</p><p>- If `ifAdminStatus` is `down(2)`, then `ifOperStatus` should be `down(2)`</p><p>- If `ifAdminStatus` is changed to `up(1)`, then `ifOperStatus` should change to `up(1)` if the interface is ready to transmit and receive network traffic</p><p>- It should change to `dormant(5)` if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the `down(2)` state if and only if there is a fault that prevents it from going to the `up(1)` state</p><p>- It should remain in the `notPresent(6)` state if the interface has missing (typically, hardware) components</p>|Dependent item|net.if.status[ifOperStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.8.{#SNMPINDEX}`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of `ifInOctets`. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.6.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of `ifOutOctets`. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.10.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.14.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.out.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.20.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.13.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.19.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n`, then the speed of the interface is somewhere in the range of `n-500,000` to `n+499,999`.</p><p>For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth.</p><p>For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|Dependent item|net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.15.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IFNAME}][{#IFALIAS}]: Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for `ifType` are assigned by the Internet Assigned Numbers Authority (IANA) through updating the syntax of the `IANAifType` textual convention.</p>|Dependent item|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Vyatta: Interface [{#IFNAME}][{#IFALIAS}]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$VYATTA.IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces).<br><br>WARNING: if closed manually - it will not fire again on the next poll because of `.diff`.</p>|`{$VYATTA.IFCONTROL:"{#IFNAME}"}=1 and last(/Vyatta Virtual Router by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Vyatta Virtual Router by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Vyatta Virtual Router by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Vyatta: Interface [{#IFNAME}][{#IFALIAS}]: High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Vyatta Virtual Router by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Vyatta Virtual Router by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Vyatta Virtual Router by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Vyatta Virtual Router by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Vyatta Virtual Router by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Vyatta: Interface [{#IFNAME}][{#IFALIAS}]: Link down</li></ul>|
|Vyatta: Interface [{#IFNAME}][{#IFALIAS}]: High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Vyatta Virtual Router by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Vyatta Virtual Router by SNMP/net.if.out.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Vyatta: Interface [{#IFNAME}][{#IFALIAS}]: Link down</li></ul>|
|Vyatta: Interface [{#IFNAME}][{#IFALIAS}]: Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Vyatta Virtual Router by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Vyatta Virtual Router by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Vyatta Virtual Router by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Vyatta Virtual Router by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Vyatta Virtual Router by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Vyatta Virtual Router by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Vyatta Virtual Router by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Vyatta Virtual Router by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Vyatta Virtual Router by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Vyatta: Interface [{#IFNAME}][{#IFALIAS}]: Link down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

