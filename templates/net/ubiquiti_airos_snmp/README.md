
# Ubiquiti AirOS SNMP

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |

## Template links

|Name|
|----|
|Generic SNMP |
|Interfaces Simple SNMP |

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: FROGFOOT-RESOURCES-MIB</p><p>5 minute load average of processor load.</p> |SNMP |system.cpu.util[loadValue.2] |
|Inventory |Hardware model name |<p>MIB: IEEE802dot11-MIB</p><p>A printable string used to identify the manufacturer's product name of the resource. Maximum string length is 128 octets.</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: IEEE802dot11-MIB</p><p>Printable string used to identify the manufacturer's product version of the resource. Maximum string length is 128 octets.</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |Free memory |<p>MIB: FROGFOOT-RESOURCES-MIB</p> |SNMP |vm.memory.free[memFree.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Total memory |<p>MIB: FROGFOOT-RESOURCES-MIB</p><p>Total memory in Bytes</p> |SNMP |vm.memory.total[memTotal.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Memory (buffers) |<p>MIB: FROGFOOT-RESOURCES-MIB</p><p>Memory used by kernel buffers (Buffers in /proc/meminfo)</p> |SNMP |vm.memory.buffers[memBuffer.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Memory (cached) |<p>MIB: FROGFOOT-RESOURCES-MIB</p><p>Memory used by the page cache and slabs (Cached and Slab in /proc/meminfo)</p> |SNMP |vm.memory.cached[memCache.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Memory utilization |<p>Memory utilization in %</p> |CALCULATED |vm.memory.util[memoryUsedPercentage]<p>**Expression**:</p>`(last(//vm.memory.total[memTotal.0])-(last(//vm.memory.free[memFree.0])+last(//vm.memory.buffers[memBuffer.0])+last(//vm.memory.cached[memCache.0])))/last(//vm.memory.total[memTotal.0])*100` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Ubiquiti AirOS SNMP/system.cpu.util[loadValue.2],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/Ubiquiti AirOS SNMP/system.hw.firmware,#1)<>last(/Ubiquiti AirOS SNMP/system.hw.firmware,#2) and length(last(/Ubiquiti AirOS SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`min(/Ubiquiti AirOS SNMP/vm.memory.util[memoryUsedPercentage],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

## Known Issues

- Description: UBNT unifi reports speed: like IF-MIB::ifSpeed.1 = Gauge32: 4294967295 for all interfaces
  - Version: Firmware: BZ.ar7240.v3.7.51.6230.170322.1513
  - Device: UBNT UAP-LR

- Description: UBNT AirMax(NanoStation, NanoBridge etc) reports ifSpeed: as 0 for VLAN and wireless(ath0) interfaces
  - Version: Firmware: XW.ar934x.v5.6-beta4.22359.140521.1836
  - Device: NanoStation M5

- Description: UBNT AirMax(NanoStation, NanoBridge etc) reports always return ifType: as ethernet(6) even for wifi,vlans and other types
  - Version: Firmware: XW.ar934x.v5.6-beta4.22359.140521.1836
  - Device: NanoStation M5

- Description: ifXTable is not provided in IF-MIB. So Interfaces Simple Template is used instead
  - Version: all above
  - Device: NanoStation, UAP-LR

