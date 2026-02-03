
# Ribbon SBC Edge by HTTP

## Overview

The Ribbon Session Border Controller Edge (SBC Edge) is a security and interoperability solution for medium-sized businesses and large branch offices.

This template is designed for the effortless deployment of Ribbon SBC Edge monitoring and doesn't require any external scripts.

More details can be found in the official Ribbon documentation:
- [REST API Reference](https://publicdoc.rbbn.com/spaces/UXAPIDOC/pages/17400598/Configuration+Resources)
- [REST API User's Guide](https://publicdoc.rbbn.com/spaces/UXAPIDOC/pages/387008766/REST+API+User+s+Guide)

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Ribbon SBC 2000

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a new user according to [REST API requirements](https://publicdoc.rbbn.com/spaces/UXAPIDOC/pages/387008769/REST+API+-+Requirements).
2. Create a new host.
3. Link the template to the host created earlier.
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
|{$RIBBON.INTERVAL}|<p>The update interval for the script items that retrieve data from the API. Can be used with context if needed (check the context values in relevant items).</p>|`1m`|
|{$RIBBON.INTERVAL:"get_system_stats"}|<p>The update interval for the script item that retrieve the system stats.</p>|`1m`|
|{$RIBBON.INTERVAL:"get_interface"}|<p>The update interval for the script item that retrieve the interface data.</p>|`1m`|
|{$RIBBON.INTERVAL:"get_fan"}|<p>The update interval for the script item that retrieve the fan data.</p>|`15m`|
|{$RIBBON.INTERVAL:"get_disk"}|<p>The update interval for the script item that retrieve the disk partition data.</p>|`5m`|
|{$RIBBON.INTERVAL:"get_psu"}|<p>The update interval for the script item that retrieve the power supply data.</p>|`5m`|
|{$RIBBON.INTERVAL:"get_dsp_card"}|<p>The update interval for the script item that retrieve the DSP card data.</p>|`5m`|
|{$RIBBON.INTERVAL:"get_sip_server"}|<p>The update interval for the script item that retrieve the SIP server data.</p>|`5m`|
|{$RIBBON.INTERVAL:"get_certificate"}|<p>The update interval for the script item that retrieve the certificate info.</p>|`5m`|
|{$RIBBON.INTERVAL:"get_alarm"}|<p>The update interval for the script item that retrieve the active alarms.</p>|`5m`|
|{$RIBBON.TIMEOUT}|<p>The timeout threshold for the script items that retrieve data from the API.</p>|`60s`|
|{$RIBBON.CPU.UTIL.CRIT}|<p>The threshold of CPU usage in percent.</p>|`90`|
|{$RIBBON.MEMORY.UTIL.CRIT}|<p>The threshold of memory usage in percent.</p>|`90`|
|{$RIBBON.DSP.CARD.CPU.USAGE.CRIT}|<p>The threshold of DSP card CPU usage in percent.</p>|`90`|
|{$RIBBON.TEMP.BOTTOM.MAIN.BOARD.CRIT}|<p>The threshold of temperature on the bottom of the main board in degrees Celsius.</p>|`80`|
|{$RIBBON.TEMP.TOP.MAIN.BOARD.CRIT}|<p>The threshold of temperature on the top of the main board in degrees Celsius.</p>|`80`|
|{$RIBBON.TEMP.CORE.CRIT}|<p>The threshold of core switch temperature in degrees Celsius.</p>|`80`|
|{$RIBBON.TEMP.PSU.CRIT}|<p>The threshold of power supply temperature in degrees Celsius.</p>|`80`|
|{$RIBBON.INTERFACE.DISCOVERY.TYPE.MATCHES}|<p>Sets the regex string of the interface type to be allowed in discovery.</p>|`.*`|
|{$RIBBON.INTERFACE.DISCOVERY.TYPE.NOT_MATCHES}|<p>Sets the regex string of the interface type to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.INTERFACE.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of the interface name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.INTERFACE.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of the interface name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.DISK.DISCOVERY.TYPE.MATCHES}|<p>Sets the regex string of the disk type to be allowed in discovery.</p>|`.*`|
|{$RIBBON.DISK.DISCOVERY.TYPE.NOT_MATCHES}|<p>Sets the regex string of the disk type to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.DISK.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of the disk name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.DISK.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of the disk name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.SIP.SERVER.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of the SIP server name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.SIP.SERVER.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of the SIP server name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.SIP.SIGNAL.GROUP.DISCOVERY.DESCR.MATCHES}|<p>Sets the regex string of the SIP signal group description to be allowed in discovery.</p>|`.*`|
|{$RIBBON.SIP.SIGNAL.GROUP.DISCOVERY.DESCR.NOT_MATCHES}|<p>Sets the regex string of the SIP signal group description to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.CERTIFICATE.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of the certificate name to be allowed in discovery.</p>|`.*`|
|{$RIBBON.CERTIFICATE.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of the certificate name to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.ALARM.DISCOVERY.STATE.MATCHES}|<p>Sets of the alarm state to be allowed in discovery.</p>|`.*`|
|{$RIBBON.ALARM.DISCOVERY.STATE.NOT_MATCHES}|<p>Sets of the alarm state to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.ALARM.DISCOVERY.EVENT.ID.MATCHES}|<p>Sets of the alarm event ID to be allowed in discovery. See the https://publicdoc.rbbn.com/spaces/UXDOC70/pages/122292177/Alarms+and+Events+Reference for possible values.</p>|`.*`|
|{$RIBBON.ALARM.DISCOVERY.EVENT.ID.NOT_MATCHES}|<p>Sets of the alarm event ID to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$RIBBON.DISK.USED.MAX}|<p>The threshold of the disk usage in percent.</p>|`90`|
|{$RIBBON.SIP.SERVER.TRANSACTIONS.FAILED.MAX}|<p>The threshold of the SIP server failed transactions.</p>|`100`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get system stats|<p>Gets the system statistics.</p>|Script|ribbon.system.stats.get|
|Get interface|<p>Gets the system ethernet port statistics.</p>|Script|ribbon.net.if.get|
|Get fan|<p>Gets fan data.</p>|Script|ribbon.fan.get|
|Get disk partition|<p>Gets the system disk partition.</p>|Script|ribbon.disk.get|
|Get power supply|<p>Gets PSU data.</p>|Script|ribbon.psu.get|
|Get DSP cards|<p>Gets DSP card data.</p>|Script|ribbon.dsp.card.get|
|Get SIP servers|<p>Gets SIP server data.</p>|Script|ribbon.sip.server.get|
|Get SIP Signaling Group|<p>Gets SIP Signaling Group data.</p>|Script|ribbon.sip.signal.group.get|
|Get certificate info|<p>Gets certificate information.</p>|Script|ribbon.cert.get|
|Get active alarms|<p>Gets the active alarms.</p>|Script|ribbon.alarm.get|
|Node name|<p>Sets the DNS host name for the system.</p>|Dependent item|ribbon.system.node.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.NodeName`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Serial number|<p>Sets the serial number of the system.</p>|Dependent item|ribbon.system.serial.number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.SerialNumber`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Status|<p>Indicates the hardware initialization state for this card.</p><p>Possible values:</p><p>- None</p><p>- Card Idle</p><p>- Card Detected</p><p>- Card Activating</p><p>- Card Activated</p><p>- Card Remove Requested</p><p>- Card Removing</p><p>- Card Removed</p><p>- Card Downloading</p><p>- Card Failed</p><p>- Card Disabled Long Loop</p><p>- MAX</p>|Dependent item|ribbon.system.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.Status`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Memory total|<p>Indicates the amount of RAM available in the system.</p>|Dependent item|ribbon.system.total.memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.TotalSystemMemory`</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Software base version|<p>Base software version.</p>|Dependent item|ribbon.system.software.base.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.rt_Software_Base_Version`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Software base build number|<p>The exact build: machine, time, and date. It should be reported whenever reporting any bug or crash related to the current software.</p>|Dependent item|ribbon.system.software.base.build<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.rt_Software_Base_BuildNumber`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Number of call attempts|<p>Total number of call attempts system-wide since the system came up.</p>|Dependent item|ribbon.number.call.attempts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemcallstats.rt_NumCallAttempts`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Number of call failed|<p>Total number of failed calls system-wide since the system came up.</p>|Dependent item|ribbon.number.call.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemcallstats.rt_NumCallFailed`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Number of call succeeded|<p>Total number of successful calls system-wide since the system came up.</p>|Dependent item|ribbon.number.call.succeeded<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemcallstats.rt_NumCallSucceeded`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Number of call currently up|<p>Number of currently connected calls system-wide.</p>|Dependent item|ribbon.number.call.currently<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemcallstats.rt_NumCallCurrentlyUp`</p></li></ul>|
|CPU Load average 15m|<p>Average number of processes over the last fifteen minutes waiting to run because the CPU is busy.</p>|Dependent item|ribbon.cpu.load.avg15<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_CPULoadAverage15m`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|CPU Load average 1m|<p>Average number of processes over the last minute waiting to run because the CPU is busy.</p>|Dependent item|ribbon.cpu.load.avg1<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_CPULoadAverage1m`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|CPU Load average 5m|<p>Average number of processes over the last five minutes waiting to run because the CPU is busy.</p>|Dependent item|ribbon.cpu.load.avg5<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_CPULoadAverage5m`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|CPU usage|<p>Average CPU usage in percent.</p>|Dependent item|ribbon.cpu.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_CPUUsage`</p></li></ul>|
|File descriptor usage|<p>Number of file descriptors used by the system.</p>|Dependent item|ribbon.fd.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_FDUsage`</p></li></ul>|
|Memory usage|<p>Average usage of system memory in percent.</p>|Dependent item|ribbon.memory.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_MemoryUsage`</p></li></ul>|
|Temporary partition usage|<p>Percentage of the temporary partition used.</p>|Dependent item|ribbon.tmp.part.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.historicalstatistics.rt_TmpPartUsage`</p></li></ul>|
|ASM Operating System License Type|<p>The ASM Operating System version that is licensed by the factory.</p><p>Possible values:</p><p>- Unknown</p><p>- Win2008R2</p><p>- Win2012R2</p><p>- Win2019</p>|Dependent item|ribbon.chassis.asm.license.type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.AsmOsLicenseType`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Chassis Status|<p>Indicates the hardware initialization state for this card.</p><p>Possible values:</p><p>  - None</p><p>  - Card Idle</p><p>  - Card Detected</p><p>  - Card Activating</p><p>  - Card Activated</p><p>  - Card Remove Requested</p><p>  - Card Removing</p><p>  - Card Removed</p><p>  - Card Downloading</p><p>  - Card Failed</p><p>  - Card Disabled Long Loop</p><p>  - MAX</p>|Dependent item|ribbon.chassis.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.rt_Status`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Chassis board bottom temperature|<p>Indicates the temperature on the bottom of the main board in degrees Celsius.</p>|Dependent item|ribbon.chassis.board.bottom.temp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.rt_Chassis_BoardBottom_Temp1`</p></li></ul>|
|Chassis board top temperature|<p>Indicates the temperature on the top of the main board in degrees Celsius.</p>|Dependent item|ribbon.chassis.board.top.temp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.rt_Chassis_BoardTop_Temp2`</p></li></ul>|
|Chassis core switch temperature|<p>Indicates the core switch temperature in degrees Celsius.</p>|Dependent item|ribbon.chassis.core.switch.temp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.rt_Chassis_CoreSwitch_Temp`</p></li></ul>|
|Chassis type|<p>Indicates the hardware or software platform type of the SBC system.</p><p>For hardware appliances, identifies whether the device is an SBC1000 or SBC2000.</p><p>For software-based deployments (SWe), indicates the virtual or software chassis type.</p>|Dependent item|ribbon.chassis.type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chassis.rt_Chassis_Type`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|DNS server 1|<p>Primary DNS server currently in use on the system.</p>|Dependent item|ribbon.system.dns.server1<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.rt_DNSServer1IP`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|DNS server 2|<p>Secondary DNS server currently in use on the system.</p>|Dependent item|ribbon.system.dns.server2<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system.rt_DNSServer2IP`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|License type|<p>Shows the type of license.</p><p>  0 - No license installed.</p><p>  1 - Node license is installed.</p><p>  2 - Base license is installed.</p>|Dependent item|ribbon.license.type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.LicenseType`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|License class|<p>Indicates the version of license format.</p><p>  0 - This is a v1 or legacy SBC 1000/2000 license.</p><p>  1 - This is a v2 or legacy SWe Edge license.</p><p>  2 - This is a v3 or new SWe Edge license.</p>|Dependent item|ribbon.license.class<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.LicenseClass`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SIP Channels licensed|<p>Displays the total number of SIP channel licenses purchased for the system.</p>|Dependent item|ribbon.license.sip.channels<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.SIPChannels`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Available SIP Channels licensed|<p>Displays the total number of SIP Channel licenses currently available for use on the system.</p>|Dependent item|ribbon.license.sip.channels.available<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.AvailableSIPCh`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SIP Registrations licensed|<p>Displays the total number of SIP registration licenses purchased for the system. Deprecated on SWe Edge since SIP registration feature is now free.</p>|Dependent item|ribbon.license.sip.registrations<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.SIPRegistrations`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SIP Registrations available|<p>Displays the total number of SIP registration licenses currently available for use on the system. Deprecated on SWe Edge since SIP registration feature is now free.</p>|Dependent item|ribbon.license.sip.registrations.available<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.AvailableSIPReg`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|License expiration date|<p>Shows when the license is due to expire. This attribute is only applicable to the SBC 1000 and 2000.</p>|Dependent item|ribbon.license.expiration.date<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.ExpirationDate`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: System status - Card Failed|<p>The current system status - Card Failed.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.system.status)=9`|Average||
|Ribbon: High CPU utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Ribbon SBC Edge by HTTP/ribbon.cpu.usage,5m)>{$RIBBON.CPU.UTIL.CRIT}`|Average||
|Ribbon: High memory utilization|<p>Memory utilization is too high. The system might be slow to respond.</p>|`min(/Ribbon SBC Edge by HTTP/ribbon.memory.usage,5m)>{$RIBBON.MEMORY.UTIL.CRIT}`|Average||
|Ribbon: Chassis status - Card Failed|<p>The current chassis status - Card Failed.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.chassis.status)=9`|Average||
|Ribbon: Temperature on the bottom of the main board is above critical threshold|<p>This trigger uses temperature the bottom of the main board value.</p>|`avg(/Ribbon SBC Edge by HTTP/ribbon.chassis.board.bottom.temp,5m)>{$RIBBON.TEMP.BOTTOM.MAIN.BOARD.CRIT}`|High||
|Ribbon: Temperature on the top of the main board is above critical threshold|<p>This trigger uses temperature of on the top of the main board value.</p>|`avg(/Ribbon SBC Edge by HTTP/ribbon.chassis.board.top.temp,5m)>{$RIBBON.TEMP.TOP.MAIN.BOARD.CRIT}`|High||
|Ribbon: Temperature on chassis core switch is above critical threshold|<p>This trigger uses temperature of chassis core switch value.</p>|`avg(/Ribbon SBC Edge by HTTP/ribbon.chassis.core.switch.temp,5m)>{$RIBBON.TEMP.CORE.CRIT}`|High||
|Ribbon: DNS server 1 has been changed|<p>The DNS server 1 has been changed. Acknowledge to close the problem manually.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.system.dns.server1,#1)<>last(/Ribbon SBC Edge by HTTP/ribbon.system.dns.server1,#2) and length(last(/Ribbon SBC Edge by HTTP/ribbon.system.dns.server1))>0`|Warning|**Manual close**: Yes|
|Ribbon: DNS server 2 has been changed|<p>The DNS server 2 has been changed. Acknowledge to close the problem manually.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.system.dns.server2,#1)<>last(/Ribbon SBC Edge by HTTP/ribbon.system.dns.server2,#2) and length(last(/Ribbon SBC Edge by HTTP/ribbon.system.dns.server2))>0`|Warning|**Manual close**: Yes|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Used for discovering system interfaces.</p>|Dependent item|ribbon.net.if.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#INTERFACE.NAME}]: Raw data|<p>Raw data of the interface.</p>|Dependent item|ribbon.net.if.raw.data[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#INTERFACE.ID}`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Config interface state|<p>Specifies the Administrative State of the resource.</p><p>Possible values:</p><p>- Disabled</p><p>- Enabled</p>|Dependent item|ribbon.net.if.config.state[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ConfigIEState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Operational status|<p>The operational status of the interface.</p><p>Possible values:</p><p>- Up</p><p>- Down</p>|Dependent item|ribbon.net.if.operator.state[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_ifOperatorStatus`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Networking mode|<p>Specifies if the port is in switched mode or routed mode.</p><p>Possible values:</p><p>- Switch</p><p>- Route</p>|Dependent item|ribbon.net.if.networking.mode[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ifNetworkingMode`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Interface type|<p>Specifies the interface type.</p><p>Possible values:</p><p>- Ethernet</p><p>- VLAN</p><p>- QINQ</p><p>- BONDED</p><p>- BRIDGE</p>|Dependent item|ribbon.net.if.type[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ifType`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Bits received|<p>Displays the number of received bits on this port.</p>|Dependent item|ribbon.net.if.in.octets[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_ifInOctets`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Bits sent|<p>Displays the number of transmitted bits on this port.</p>|Dependent item|ribbon.net.if.out.octets[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_ifOutOctets`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#INTERFACE.NAME}]: Speed|<p>An estimate of the interface's current bandwidth in bits per second.</p><p>Possible values:</p><p>- 10 Mbps</p><p>- 100 Mbps</p><p>- 1000 Mbps</p><p>- Auto</p>|Dependent item|ribbon.net.if.speed[{#INTERFACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_ifSpeed`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Interface [{#INTERFACE.NAME}]: Operational status is DOWN|<p>The operational status of the interface is down.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.net.if.operator.state[{#INTERFACE.ID}])=1 and last(/Ribbon SBC Edge by HTTP/ribbon.net.if.config.state[{#INTERFACE.ID}])=1`|Average||

### LLD rule Disk partition discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk partition discovery|<p>Used for discovering system disk partition.</p>|Dependent item|ribbon.disk.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Disk partition discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk [{#DISK.NAME}]: Raw data|<p>Raw data of the disk partition.</p>|Dependent item|ribbon.disk.raw.data[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#DISK.ID}`</p></li></ul>|
|Disk [{#DISK.NAME}]: Type|<p>Identifies the user-friendly physical device holding the partition.</p><p>Possible values:</p><p>- Configuration</p><p>- Logs</p><p>- Temp</p><p>- Core File</p><p>- ASM Module</p><p>- Software Update</p><p>- Internal Logs</p><p>- System</p><p>- Others</p><p>- Packet Capture Logs</p>|Dependent item|ribbon.disk.type[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_PartitionType`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Disk [{#DISK.NAME}]: Utilization|<p>Amount of memory used by this partition, expressed as a percentage.</p>|Dependent item|ribbon.disk.usage.percent[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_CurrentUsage`</p></li></ul>|
|Disk [{#DISK.NAME}]: Size|<p>Specifies the maximum amount of memory, in bytes available in this partition.</p>|Dependent item|ribbon.disk.size.max[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_MaximumSize`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Disk [{#DISK.NAME}]: Available|<p>Amount of memory in bytes, available for use in the file system.</p>|Dependent item|ribbon.disk.size.available[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_MemoryAvailable`</p></li></ul>|
|Disk [{#DISK.NAME}]: Used|<p>Amount of memory in bytes, used by the existing files in the file system.</p>|Dependent item|ribbon.disk.size.used[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_MemoryUsed`</p></li></ul>|

### Trigger prototypes for Disk partition discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Disk [{#DISK.NAME}]: Disk space usage is high|<p>Disk space usage is larger than the threshold.</p>|`min(/Ribbon SBC Edge by HTTP/ribbon.disk.usage.percent[{#DISK.ID}],5m)>{$RIBBON.DISK.USED.MAX:"{#DISK.NAME}"}`|Average||

### LLD rule Power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply discovery|<p>Used for discovering the power supply.</p>|Dependent item|ribbon.psu.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU [{#PSU.ID}]: Raw data|<p>Raw data for this power supply.</p>|Dependent item|ribbon.psu.raw.data[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#PSU.ID}`</p></li></ul>|
|PSU [{#PSU.ID}]: AC input good|<p>Indicates whether the AC power input for this power supply is in a good state.</p><p>Possible values:</p><p>- False</p><p>- True</p>|Dependent item|ribbon.psu.ac.input.good[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ACInputGood`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|PSU [{#PSU.ID}]: Power in|<p>Input power of this power supply in watts.</p>|Dependent item|ribbon.psu.power.in[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.PowerIn`</p></li></ul>|
|PSU [{#PSU.ID}]: Power out|<p>Output power of this power supply in watts.</p>|Dependent item|ribbon.psu.power.out[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.PowerOut`</p></li></ul>|
|PSU [{#PSU.ID}]: Voltage in|<p>Input voltage of this power supply.</p>|Dependent item|ribbon.psu.voltage.in[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VoltageIn`</p></li></ul>|
|PSU [{#PSU.ID}]: Voltage out|<p>Output voltage of this power supply.</p>|Dependent item|ribbon.psu.voltage.out[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VoltageOut`</p></li></ul>|
|PSU [{#PSU.ID}]: Current in|<p>Input current of this power supply in amperes.</p>|Dependent item|ribbon.psu.current.in[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CurrentIn`</p></li></ul>|
|PSU [{#PSU.ID}]: Current out|<p>Output current of this power supply in amperes.</p>|Dependent item|ribbon.psu.current.out[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CurrentOut`</p></li></ul>|
|PSU [{#PSU.ID}]: Temperature|<p>Temperature of this power supply in degrees Celsius.</p>|Dependent item|ribbon.psu.temp[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Temp`</p></li></ul>|
|PSU [{#PSU.ID}]: Fan1 speed|<p>The speed of the first fan on this power supply in RPM.</p>|Dependent item|ribbon.psu.fan1.speed[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Fan1_Speed`</p></li></ul>|
|PSU [{#PSU.ID}]: Fan2 speed|<p>The speed of the second fan on this power supply in RPM.</p>|Dependent item|ribbon.psu.fan2.speed[{#PSU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Fan2_Speed`</p></li></ul>|

### Trigger prototypes for Power supply discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: PSU [{#PSU.ID}]: AC input is not OK|<p>The AC power input of the power supply reports a bad state.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.psu.ac.input.good[{#PSU.ID}]) = 0`|Warning|**Manual close**: Yes|
|Ribbon: PSU [{#PSU.ID}]: Temperature on power supply is above critical threshold|<p>This trigger uses temperature of power supply value.</p>|`avg(/Ribbon SBC Edge by HTTP/ribbon.psu.temp[{#PSU.ID}],5m)>{$RIBBON.TEMP.PSU.CRIT}`|High||

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>Used for discovering fans.</p>|Dependent item|ribbon.fan.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#FAN.ID}]: Speed|<p>Indicates the speed of the fan in RPM.</p>|Dependent item|ribbon.fan.speed[{#FAN.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#FAN.ID}.Fan_Speed`</p></li></ul>|

### LLD rule DSP card discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DSP card discovery|<p>Used for discovering DSP cards.</p>|Dependent item|ribbon.dsp.card.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for DSP card discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DSP Card [{#DSP.CARD.ID}]: Raw data|<p>Raw data for `{#DSP.CARD.ID}` DSP card.</p>|Dependent item|ribbon.dsp.card.raw.data[{#DSP.CARD.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#DSP.CARD.ID}`</p></li></ul>|
|DSP Card [{#DSP.CARD.ID}]: Status|<p>Indicates the hardware initialization state for this DSP card.</p><p>Possible values:</p><p>  - None</p><p>  - Card Idle</p><p>  - Card Detected</p><p>  - Card Activating</p><p>  - Card Activated</p><p>  - Card Remove Requested</p><p>  - Card Removing</p><p>  - Card Removed</p><p>  - Card Downloading</p><p>  - Card Failed</p><p>  - Card Disabled Long Loop</p><p>  - MAX</p>|Dependent item|ribbon.dsp.card.status[{#DSP.CARD.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_Status`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|DSP Card [{#DSP.CARD.ID}]: Location|<p>The hardware module's location within the SBC.</p><p>Possible values:</p><p>  - Unknown</p><p>  - LineCard1</p><p>  - LineCard2</p><p>  - DSPCard1</p><p>  - DSPCard2</p><p>  - DSPCard3</p><p>  - DSPCard4</p><p>  - DSPCard5</p><p>  - DSPCard6</p><p>  - BITS_WAN</p><p>  - SFP</p><p>  - COMExpress</p><p>  - Mainboard</p><p>  - PSU1</p><p>  - PSU2</p><p>  - UX1000_Telco_1</p><p>  - UX1000_Telco_2</p><p>  - UX1000_Telco_3</p><p>  - UX1000_Telco_4</p><p>  - UX1000_Telco_5</p><p>  - UX1000_Telco_6</p><p>  - UX1000_DS1_Telco</p><p>  - UX1000_DS1_WAN</p><p>  - MAX</p>|Dependent item|ribbon.dsp.card.location[{#DSP.CARD.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_Location`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|DSP Card [{#DSP.CARD.ID}]: Available|<p>Identifies whether a DSP Module is installed in this slot.</p><p>Possible values:</p><p>  - False</p><p>  - True</p>|Dependent item|ribbon.dsp.card.available[{#DSP.CARD.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_Available`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|DSP Card [{#DSP.CARD.ID}]: Service status|<p>Indicates the status of the DSP Module</p><p>`Detected` - The module is detected by the SBC software but is not initialized or available for signal processing tasks.</p><p>`Available` - The module is ready for signal processing tasks.</p>|Dependent item|ribbon.dsp.card.service.status[{#DSP.CARD.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_ServiceStatus`</p></li></ul>|
|DSP Card [{#DSP.CARD.ID}]: CPU usage|<p>Indicates the current CPU level for this DSP. Only applicable if the DSP is available.</p>|Dependent item|ribbon.dsp.card.cpu.usage.percent[{#DSP.CARD.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_CPUUsage`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DSP Card [{#DSP.CARD.ID}]: Channels in use|<p>Indicates the number of channels currently in use on the DSP Module.</p>|Dependent item|ribbon.dsp.card.channels.in.use[{#DSP.CARD.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_ChannelsInUse`</p></li></ul>|
|DSP Card [{#DSP.CARD.ID}]: Updated time|<p>Indicates the time, when this DSP reported it's CPU usage.</p>|Dependent item|ribbon.dsp.card.updated.time[{#DSP.CARD.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_UpdatedTime`</p></li></ul>|

### Trigger prototypes for DSP card discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: DSP Card [{#DSP.CARD.ID}]: Status is FAILED|<p>The DSP card status is failed.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.dsp.card.status[{#DSP.CARD.ID}])=9`|High||
|Ribbon: DSP Card [{#DSP.CARD.ID}]: Service status is Detected|<p>The DSP card service status is Detected.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.dsp.card.service.status[{#DSP.CARD.ID}])=0`|Warning||
|Ribbon: DSP Card [{#DSP.CARD.ID}]: CPU usage is high|<p>The DSP card CPU usage is higher than the threshold.</p>|`avg(/Ribbon SBC Edge by HTTP/ribbon.dsp.card.cpu.usage.percent[{#DSP.CARD.ID}],15m)>{$RIBBON.DSP.CARD.CPU.USAGE.CRIT:"{#DSP.CARD.ID}"}`|Average||

### LLD rule SIP server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SIP server discovery|<p>Used for discovering SIP servers.</p>|Dependent item|ribbon.sip.server.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SIP server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SIP Server [{#SIP.SERVER.NAME}]:[{#SIP.SERVER.DESCR}]: Raw data|<p>Raw data for this SIP server.</p>|Dependent item|ribbon.sip.server.raw.data[{#SIP.SERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#SIP.SERVER.ID}.metrics`</p></li></ul>|
|SIP Server [{#SIP.SERVER.NAME}]:[{#SIP.SERVER.DESCR}]: Status|<p>Indicates the status of the SIP server.</p>|Dependent item|ribbon.sip.server.status[{#SIP.SERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_ServerStatus`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SIP Server [{#SIP.SERVER.NAME}]:[{#SIP.SERVER.DESCR}]: Uptime|<p>Displays the time for which the server was functioning and responding to SIP Options.</p>|Dependent item|ribbon.sip.server.uptime[{#SIP.SERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_UpTime`</p></li></ul>|
|SIP Server [{#SIP.SERVER.NAME}]:[{#SIP.SERVER.DESCR}]: Downtime|<p>Displays the time for which server was down due to not responding to options request or dns failures.</p>|Dependent item|ribbon.sip.server.downtime[{#SIP.SERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_DownTime`</p></li></ul>|
|SIP Server [{#SIP.SERVER.NAME}]:[{#SIP.SERVER.DESCR}]: Host|<p>Specifies the IP address or FQDN where this Signaling Group sends SIP messages. If an FQDN is configured all the associated servers are included and used according to the server selection configuration element.</p>|Dependent item|ribbon.sip.server.host[{#SIP.SERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Host`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|SIP Server [{#SIP.SERVER.NAME}]:[{#SIP.SERVER.DESCR}]: Port|<p>Specifies the port number to send SIP messages.</p>|Dependent item|ribbon.sip.server.port[{#SIP.SERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Port`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|SIP Server [{#SIP.SERVER.NAME}]:[{#SIP.SERVER.DESCR}]: Type|<p>Specifies the method to use to lookup SIP servers</p><p>`eConventionalSrvr` - A configured server entry defined by IP or FQDN.</p><p>`eSrvRecordTemplateSrvr` - This is not an actual server but a template which will populate the server(s) once the SRV Query gets a response from DNS.</p><p>`eSrvRecordSrvr`- The actual server created after SRV Query."</p>|Dependent item|ribbon.sip.server.type[{#SIP.SERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ServerType`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|SIP Server [{#SIP.SERVER.NAME}]:[{#SIP.SERVER.DESCR}]: Transactions|<p>Displays the number of SBC client transactions with the server.</p>|Dependent item|ribbon.sip.server.transactions[{#SIP.SERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_Transactions`</p></li></ul>|
|SIP Server [{#SIP.SERVER.NAME}]:[{#SIP.SERVER.DESCR}]: Transactions failed|<p>Displays the number of failed SBC client transactions with the server.</p>|Dependent item|ribbon.sip.server.transactions.failed[{#SIP.SERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_TransactionFalures`</p></li></ul>|

### Trigger prototypes for SIP server discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: SIP Server [{#SIP.SERVER.NAME}]: Failed transactions are high|<p>The number of failed transactions is higher than the threshold.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.sip.server.transactions.failed[{#SIP.SERVER.NAME}])>{$RIBBON.SIP.SERVER.TRANSACTIONS.FAILED.MAX:"{#SIP.SERVER.NAME}"}`|Average||

### LLD rule SIP signal group discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SIP signal group discovery|<p>Used for discovering SIP signal groups.</p>|Dependent item|ribbon.sip.signal.group.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SIP signal group discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SIP Signal Group [{#SIP.SIGNAL.GROUP.DESCR}]: Raw data|<p>Raw data for this SIP signal group.</p>|Dependent item|ribbon.sip.signal.group.raw.data[{#SIP.SIGNAL.GROUP.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#SIP.SIGNAL.GROUP.ID}`</p></li></ul>|
|SIP Signal Group [{#SIP.SIGNAL.GROUP.DESCR}]: Type|<p>Provides the signaling type of the signaling group.</p><p>Possible values:</p><p>- sgISDN</p><p>- sgSIP</p><p>- sgCAS</p>|Dependent item|ribbon.sip.signal.group.type[{#SIP.SIGNAL.GROUP.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_Type`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SIP Signal Group [{#SIP.SIGNAL.GROUP.DESCR}]: Status|<p>Provides the runtime operational status of the signaling group.</p><p>Possible values:</p><p>- sgsUP</p><p>- sgsDOWN</p><p>- sgsUpDraining</p><p>- sgsUpDrained</p><p>- sgsUpPeersDown</p><p>- sgsNONE_EXISTS</p>|Dependent item|ribbon.sip.signal.group.status[{#SIP.SIGNAL.GROUP.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_Status`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SIP Signal Group [{#SIP.SIGNAL.GROUP.DESCR}]: Number of channels|<p>Provides the number of channels currently provisioned on this signaling group.</p>|Dependent item|ribbon.sip.signal.group.number.of.channels[{#SIP.SIGNAL.GROUP.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rt_NumberOfChannels`</p></li></ul>|

### Trigger prototypes for SIP signal group discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: SIP Signal Group [{#SIP.SIGNAL.GROUP.DESCR}]: Status is DOWN|<p>The SIP signaling group status is down.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.sip.signal.group.status[{#SIP.SIGNAL.GROUP.ID}])=1`|Warning||

### LLD rule Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Certificate discovery|<p>Used for discovering certificates.</p>|Dependent item|ribbon.cert.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Certificate [{#CERT.NAME}]: Raw data|<p>Raw data for "{#CERT.NAME}" certificate.</p>|Dependent item|ribbon.cert.status.raw.data[{#CERT.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#CERT.ID}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Certificate [{#CERT.NAME}]: Valid until|<p>Indicates the expiration date of this certificate.</p>|Dependent item|ribbon.cert.status.valid.until[{#CERT.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CertEndDateEpoch`</p></li></ul>|
|Certificate [{#CERT.NAME}]: Status|<p>Displays the status of the certificate validation and verification against the trusted CA.</p>|Dependent item|ribbon.cert.status[{#CERT.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CertVerifyStatus`</p></li></ul>|

### Trigger prototypes for Certificate discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Certificate [{#CERT.NAME}]: Status in not OK|<p>The certificate status is not OK.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.cert.status[{#CERT.ID}])<>"OK"`|High||

### LLD rule Alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Alarm discovery|<p>Used for discovering active alarms.</p>|Dependent item|ribbon.alarm.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Alarm [{#ALARM.EVENT.ID}.{#ALARM.EVENT.SUB.ID}]: Source: [{#ALARM.SOURCE}]: Severity|<p>Severity of `{#ALARM.CONDITION}` event.</p>|Dependent item|ribbon.alarm.severity[{#ALARM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#ALARM.ID}.aSeverity`</p></li></ul>|

### Trigger prototypes for Alarm discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ribbon: Alarm [{#ALARM.EVENT.ID}.{#ALARM.EVENT.SUB.ID}]: Source: [{#ALARM.SOURCE}]: Severity is critical|<p>The alarm `{#ALARM.CONDITION}` severity is critical.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.alarm.severity[{#ALARM.ID}])=4`|High|**Manual close**: Yes|
|Ribbon: Alarm [{#ALARM.EVENT.ID}.{#ALARM.EVENT.SUB.ID}]: Source: [{#ALARM.SOURCE}]: Severity is major|<p>The alarm `{#ALARM.CONDITION}` severity is major.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.alarm.severity[{#ALARM.ID}])=3`|Average|**Manual close**: Yes|
|Ribbon: Alarm [{#ALARM.EVENT.ID}.{#ALARM.EVENT.SUB.ID}]: Source: [{#ALARM.SOURCE}]: Severity is minor|<p>The alarm `{#ALARM.CONDITION}` severity is minor.</p>|`last(/Ribbon SBC Edge by HTTP/ribbon.alarm.severity[{#ALARM.ID}])=2`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

