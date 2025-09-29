
# Ribbon SBC 2000 by HTTP

## Overview

The Ribbon Session Border Controller 2000 (SBC 2000) is an ideal security and interoperability solution for medium-sized businesses and large branch offices.

This template is designed for the effortless deployment of Ribbon SBC 2000 monitoring and doesn't require any external scripts.

More details can be found in the official documentation:
  - on [REST API Reference](https://publicdoc.rbbn.com/spaces/UXAPIDOC/pages/17400598/Configuration+Resources)
  - on [REST API User's Guide](https://publicdoc.rbbn.com/spaces/UXAPIDOC/pages/387008766/REST+API+User+s+Guide)

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Ribbon SBC 2000

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a new user according [REST API - Requirements](https://publicdoc.rbbn.com/spaces/UXAPIDOC/pages/387008769/REST+API+-+Requirements)
2. Create a new host
3. Link the template to the host created earlier
4. Set the host macros (on the host or template level) required for getting data:
```text
{$RIBBON.URL}
```
5. Set the host macros (on the host or template level) with the login and password of the user created earlier:
```text
{$RIBBON.USERNAME}
{$RIBBON.PASSWORD}
```

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$RIBBON.USERNAME}|<p>Ribbon SBC username.</p>||
|{$RIBBON.PASSWORD}|<p>Ribbon SBC user password.</p>||
|{$RIBBON.URL}|<p>Ribbon SBC API IP.</p>||
|{$RIBBON.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$RIBBON.CPU.UTIL.CRIT}|<p>The threshold of the CPU usage in percent.</p>|`90`|
|{$RIBBON.MEMORY.UTIL.CRIT}|<p>The threshold of the memory usage in percent.</p>|`90`|
|{$RIBBON.INTERFACE.DISCOVERY.TYPE.MATCHES}|<p>Sets the regex string of interface type to be allowed in discovery.</p>|`.*`|
|{$RIBBON.INTERFACE.DISCOVERY.TYPE.NOT_MATCHES}|<p>Sets the regex string of interface type to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.INTERFACE.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of interface name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.INTERFACE.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of interface name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.DISK.DISCOVERY.TYPE.MATCHES}|<p>Sets the regex string of disk type to be allowed in discovery.</p>|`.*`|
|{$RIBBON.DISK.DISCOVERY.TYPE.NOT_MATCHES}|<p>Sets the regex string of disk type to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.DISK.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of disk name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.DISK.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of disk name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.DISK.USED.MAX}|<p>The threshold of the disk usage in percent.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get system stats|<p>Gets the system statistic.</p>|Script|ribbon.system.stats.get|
|Get interface|<p>Gets the system ethernet port statistic.</p>|Script|ribbon.net.if.get|
|Get fan|<p>Gets the Fan.</p>|Script|ribbon.fan.get|
|Get disk partition|<p>Gets the system disk partition.</p>|Script|ribbon.disk.get|
|Get power supply|<p>Gets the PSU.</p>|Script|ribbon.psu.get|
|Node name|<p>Sets the DNS host name for the system.</p>|Dependent item|ribbon.system.node.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.NodeName`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Serial number|<p>Sets the serial number of the system.</p>|Dependent item|ribbon.system.serial.number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.SerialNumber`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Status|<p>Indicates the hardware initialization state for this card.</p><p>Possible values:</p><p>- None</p><p>- Card Idle</p><p>- Card Detected</p><p>- Card Activating</p><p>- Card Activated</p><p>- Card RemoveRequested</p><p>- Card Removing</p><p>- Card Removed</p><p>- Card Downloading</p><p>- Card Failed</p><p>- Card DisabledLongLoop</p><p>- MAX</p>|Dependent item|ribbon.system.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.Status`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Memory total|<p>Indicates the amount of RAM available in the system.</p>|Dependent item|ribbon.system.total.memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.TotalSystemMemory`</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Software base version|<p>Base Software version.</p>|Dependent item|ribbon.system.software.base.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.rt_Software_Base_Version`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Software base build number|<p>Build Number identifies exact build machine, time and date. It should be reported whenever reporting any bug or crash related to current software.</p>|Dependent item|ribbon.system.software.base.build<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.rt_Software_Base_BuildNumber`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Number of call attempts|<p>Total number of call attempts system wide since system came up.</p>|Dependent item|ribbon.number.call.attempts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemcallstats.rt_NumCallAttempts`</p></li></ul>|
|Number of call failed|<p>Total number of failed calls system wide since system came up.</p>|Dependent item|ribbon.number.call.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemcallstats.rt_NumCallFailed`</p></li></ul>|
|Number of call succeeded|<p>Total number of successfull calls system wide since system came up.</p>|Dependent item|ribbon.number.call.succeeded<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemcallstats.rt_NumCallSucceeded`</p></li></ul>|
|Number of call currently up|<p>Number of currently connected calls system wide.</p>|Dependent item|ribbon.number.call.currently<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemcallstats.rt_NumCallCurrentlyUp`</p></li></ul>|
|CPU Load average 15m|<p>Average number of processes over the last fifteen minutes waiting to run because CPU is busy.</p>|Dependent item|ribbon.cpu.load.avg15<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_CPULoadAverage15m`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|CPU Load average 1m|<p>Average number of processes over the last one minute waiting to run because CPU is busy.</p>|Dependent item|ribbon.cpu.load.avg1<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_CPULoadAverage1m`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|CPU Load average 5m|<p>Average number of processes over the last five minutes waiting to run because CPU is busy.</p>|Dependent item|ribbon.cpu.load.avg5<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_CPULoadAverage5m`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|CPU usage|<p>Average percent usage of the CPU.</p>|Dependent item|ribbon.cpu.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_CPUUsage`</p></li></ul>|
|File descriptors usage|<p>Number of file descriptors used by the system.</p>|Dependent item|ribbon.fd.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_FDUsage`</p></li></ul>|
|Memory usage|<p>Average percent usage of system memory.</p>|Dependent item|ribbon.memory.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_MemoryUsage`</p></li></ul>|
|Temporary partition usage|<p>Percentage of the temporary partition used.</p>|Dependent item|ribbon.tmp.part.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_TmpPartUsage`</p></li></ul>|
|ASM Operating System License Type|<p>The version of ASM Operating System that is licensed by the factory.</p><p>Possible values:</p><p>- Unknown</p><p>- Win2008R2</p><p>- Win2012R2</p><p>- Win2019</p>|Dependent item|ribbon.chassis.asm.license.type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.AsmOsLicenseType`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Chassis Status|<p>Indicates the hardware initialization state for this card.</p><p>Possible values:</p><p>  - None</p><p>  - Card Idle</p><p>  - Card Detected</p><p>  - Card Activating</p><p>  - Card Activated</p><p>  - Card RemoveRequested</p><p>  - Card Removing</p><p>  - Card Removed</p><p>  - Card Downloading</p><p>  - Card Failed</p><p>  - Card DisabledLongLoop</p><p>  - MAX</p>|Dependent item|ribbon.chassis.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.rt_Status`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Chassis board bottom temp1|<p>Indicates the temperature on the bottom of the main board in degrees Celsius.</p>|Dependent item|ribbon.chassis.board.bottom.temp1<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.rt_Chassis_BoardBottom_Temp1`</p></li></ul>|
|Chassis board top temp2|<p>Indicates the temperature on the top of the main board in degrees Celsius.</p>|Dependent item|ribbon.chassis.board.top.temp2<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.rt_Chassis_BoardTop_Temp2`</p></li></ul>|
|Chassis core switch temp|<p>Indicates the core switch temperature in degrees Celsius.</p>|Dependent item|ribbon.chassis.core.switch.temp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.rt_Chassis_CoreSwitch_Temp`</p></li></ul>|
|Chassis type|<p>Indicates whether the device is an SBC1000 or an SBC2000.</p>|Dependent item|ribbon.chassis.type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.rt_Chassis_Type`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: System status - Card Failed|<p>The current system status - Card Failed.</p>|`last(/Ribbon SBC 2000 by HTTP/ribbon.system.status)=9`|Average||
|Ribbon: High CPU utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Ribbon SBC 2000 by HTTP/ribbon.cpu.usage,5m)>{$RIBBON.CPU.UTIL.CRIT}`|Average||
|Ribbon: High memory utilization|<p>Memory utilization is too high. The system might be slow to respond.</p>|`min(/Ribbon SBC 2000 by HTTP/ribbon.memory.usage,5m)>{$RIBBON.MEMORY.UTIL.CRIT}`|Average||

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Used for discovering system interfaces.</p>|Dependent item|ribbon.net.if.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#INTERFACE.NAME}]: Config interface state|<p>Specifies the Administrative State of the resource.</p><p>Possible values:</p><p>- Disabled</p><p>- Enabled</p>|Dependent item|ribbon.net.if.config.state[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#INTERFACE.ID}.ConfigIEState`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Operational status|<p>The operational status of the interface.</p><p>Possible values:</p><p>- Up</p><p>- Down</p>|Dependent item|ribbon.net.if.operator.state[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#INTERFACE.ID}.rt_ifOperatorStatus`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Networking mode|<p>Specifies if the port is in switched mode or routed mode.</p><p>Possible values:</p><p>- Switch</p><p>- Route</p>|Dependent item|ribbon.net.if.networking.mode[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#INTERFACE.ID}.ifNetworkingMode`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Interface type|<p>Specifies the interface type.</p><p>Possible values:</p><p>- Ethernet</p><p>- VLAN</p><p>- QINQ</p><p>- BONDED</p><p>- BRIDGE</p>|Dependent item|ribbon.net.if.type[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#INTERFACE.ID}.ifType`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Bits received|<p>Displays the number of received octets on this port.</p>|Dependent item|ribbon.net.if.in.octets[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#INTERFACE.ID}.rt_ifInOctets`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Bits sent|<p>Displays the number of transmitted octets on this port.</p>|Dependent item|ribbon.net.if.out.octets[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#INTERFACE.ID}.rt_ifOutOctets`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Speed|<p>An estimate of the interface's current bandwidth in bits per second.</p><p>Possible values:</p><p>- 10 Mbps</p><p>- 100 Mbps</p><p>- 1000 Mbps</p><p>- Auto</p>|Dependent item|ribbon.net.if.speed[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#INTERFACE.ID}.rt_ifSpeed`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Interface [{#INTERFACE.NAME}]: Operational status is DOWN|<p>The operational status of the interface is down.</p>|`last(/Ribbon SBC 2000 by HTTP/ribbon.net.if.operator.state[{#INTERFACE.ID}])=1 and last(/Ribbon SBC 2000 by HTTP/ribbon.net.if.config.state[{#INTERFACE.ID}])=1`|Average||

### LLD rule Disk partition discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk partition discovery|<p>Used for discovering system disk partition.</p>|Dependent item|ribbon.disk.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Disk partition discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk [{#DISK.NAME}]: Type|<p>Identifies the user-friendly physical device holding the partition.</p><p>Possible values:</p><p>- Configuration</p><p>- Logs</p><p>- Temp</p><p>- Core File</p><p>- ASM Module</p><p>- Software Update</p><p>- Internal Logs</p><p>- System</p><p>- Others</p><p>- Packet Capture Logs</p>|Dependent item|ribbon.disk.type[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#DISK.ID}.rt_PartitionType`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Disk [{#DISK.NAME}]: Utilization|<p>Amount of memory used by this partition, expressed as percentage.</p>|Dependent item|ribbon.disk.usage.percent[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#DISK.ID}.rt_CurrentUsage`</p></li></ul>|
|Disk [{#DISK.NAME}]: Size|<p>Specifies the maximum amount of memory, in bytes available in this partition.</p>|Dependent item|ribbon.disk.size.max[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#DISK.ID}.rt_MaximumSize`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Disk [{#DISK.NAME}]: Available|<p>Amount of memory in bytes, available for use in the filesystem.</p>|Dependent item|ribbon.disk.size.available[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#DISK.ID}.rt_MemoryAvailable`</p></li></ul>|
|Disk [{#DISK.NAME}]: Used|<p>Amount of memory in bytes, used by the existing files in the filesystem.</p>|Dependent item|ribbon.disk.size.used[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#DISK.ID}.rt_MemoryUsed`</p></li></ul>|

### Trigger prototypes for Disk partition discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Disk [{#DISK.NAME}]: Disk space usage is high|<p>Disk space usage is bigger then the threshold.</p>|`min(/Ribbon SBC 2000 by HTTP/ribbon.disk.usage.percent[{#DISK.ID}],5m)>{$RIBBON.DISK.USED.MAX:"{#DISK.NAME}"}`|Average||

### LLD rule Power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply discovery|<p>Used for discovering power supply.</p>|Dependent item|ribbon.psu.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU [{#PSU.ID}]: AC input good|<p>Indicates whether the AC power input for this power supply is in a good state.</p><p>Possible values:</p><p>- False</p><p>- True</p>|Dependent item|ribbon.psu.ac.input.good[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}.ACInputGood`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|PSU [{#PSU.ID}]: Power in|<p>Input power of this power supply in watts.</p>|Dependent item|ribbon.psu.power.in[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}.PowerIn`</p></li></ul>|
|PSU [{#PSU.ID}]: Power out|<p>Output power of this power supply in watts.</p>|Dependent item|ribbon.psu.power.out[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}.PowerOut`</p></li></ul>|
|PSU [{#PSU.ID}]: Voltage in|<p>Input voltage of this power supply.</p>|Dependent item|ribbon.psu.voltage.in[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}.VoltageIn`</p></li></ul>|
|PSU [{#PSU.ID}]: Voltage out|<p>Output voltage of this power supply.</p>|Dependent item|ribbon.psu.voltage.out[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}.VoltageOut`</p></li></ul>|
|PSU [{#PSU.ID}]: Current in|<p>Input current of this power supply in amperes.</p>|Dependent item|ribbon.psu.current.in[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}.CurrentIn`</p></li></ul>|
|PSU [{#PSU.ID}]: Current out|<p>Output current of this power supply in amperes.</p>|Dependent item|ribbon.psu.current.out[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}.CurrentOut`</p></li></ul>|
|PSU [{#PSU.ID}]: Temperature|<p>Temperature of this power supply in degrees Celsius.</p>|Dependent item|ribbon.psu.temp[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}.Temp`</p></li></ul>|
|PSU [{#PSU.ID}]: Fan1 speed|<p>The speed of the first fan on this power supply in RPM.</p>|Dependent item|ribbon.psu.fan1.speed[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}.Fan1_Speed`</p></li></ul>|
|PSU [{#PSU.ID}]: Fan2 speed|<p>The speed of the second fan on this power supply in RPM.</p>|Dependent item|ribbon.psu.fan2.speed[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}.Fan2_Speed`</p></li></ul>|

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>Used for discovering fan.</p>|Dependent item|ribbon.fan.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#FAN.ID}]: Speed|<p>Indicates the speed of the fan in RPM.</p>|Dependent item|ribbon.fan.speed[{#FAN.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#FAN.ID}.Fan_Speed`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

