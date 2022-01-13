
# TP-LINK SNMP

## Overview

For Zabbix version: 6.0 and higher  
Link to MIBs: https://www.tp-link.com/en/support/download/t2600g-28ts/#MIBs_Files
Sample device overview page: https://www.tp-link.com/en/business-networking/managed-switch/t2600g-28ts/#overview
Emulation page (web): https://emulator.tp-link.com/T2600G-28TS(UN)_1.0/Index.htm


This template was tested on:

- T2600G-28TS revision 2.0, version 2.0.0 Build 20170628 Rel.55184 (Beta)

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

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|CPU Discovery |<p>Discovering TPLINK-SYSMONITOR-MIB::tpSysMonitorCpuTable, displays the CPU utilization of all UNITs.</p> |SNMP |cpu.discovery |
|Memory Discovery |<p>Discovering TPLINK-SYSMONITOR-MIB::tpSysMonitorMemoryTable, displays the memory utilization of all UNITs.</p> |SNMP |memory.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |#{#SNMPVALUE}: CPU utilization |<p>MIB: TPLINK-SYSMONITOR-MIB</p><p>Displays the CPU utilization in 1 minute.</p><p>Reference: http://www.tp-link.com/faq-1330.html</p> |SNMP |system.cpu.util[tpSysMonitorCpu1Minute.{#SNMPINDEX}] |
|Inventory |Hardware model name |<p>MIB: TPLINK-SYSINFO-MIB</p><p>The hardware version of the product.</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: TPLINK-SYSINFO-MIB</p><p>The Serial number of the product.</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: TPLINK-SYSINFO-MIB</p><p>The software version of the product.</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware version(revision) |<p>MIB: TPLINK-SYSINFO-MIB</p><p>The hardware version of the product.</p> |SNMP |system.hw.version<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |#{#SNMPVALUE}: Memory utilization |<p>MIB: TPLINK-SYSMONITOR-MIB</p><p>Displays the memory utilization.</p><p>Reference: http://www.tp-link.com/faq-1330.html</p> |SNMP |vm.memory.util[tpSysMonitorMemoryUtilization.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|#{#SNMPVALUE}: High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/TP-LINK SNMP/system.cpu.util[tpSysMonitorCpu1Minute.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/TP-LINK SNMP/system.hw.serialnumber,#1)<>last(/TP-LINK SNMP/system.hw.serialnumber,#2) and length(last(/TP-LINK SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/TP-LINK SNMP/system.hw.firmware,#1)<>last(/TP-LINK SNMP/system.hw.firmware,#2) and length(last(/TP-LINK SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|#{#SNMPVALUE}: High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`min(/TP-LINK SNMP/vm.memory.util[tpSysMonitorMemoryUtilization.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

## Known Issues

- Description: Default sysLocation, sysName and sysContact is not filled with proper data. Real hostname and location can be found only in private branch (TPLINK-SYSINFO-MIB). Please check whether this problem exists in the latest firmware: https://www.tp-link.com/en/support/download/t2600g-28ts/#Firmware
  - Version: 2.0.0 Build 20170628 Rel.55184 (Beta)
  - Device: T2600G-28TS 2.0

- Description: The Serial number of the product (tpSysInfoSerialNum) is missing in HW versions prior to V2_170323
  - Version: Prior to version V2_170323
  - Device: T2600G-28TS 2.0

