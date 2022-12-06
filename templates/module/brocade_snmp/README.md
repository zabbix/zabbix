
# Brocade_Foundry Performance by SNMP

## Overview

For Zabbix version: 6.2 and higher.

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

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The statistics collection of 1 minute CPU utilization.</p> |SNMP |system.cpu.util[snAgGblCpuUtil1MinAvg.0] |
|Memory |Memory utilization |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The system dynamic memory utilization, in unit of percentage.</p><p>Deprecated: Refer to snAgSystemDRAMUtil.</p><p>For NI platforms, refer to snAgentBrdMemoryUtil100thPercent.</p> |SNMP |vm.memory.util[snAgGblDynMemUtil.0] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Brocade_Foundry Performance by SNMP/system.cpu.util[snAgGblCpuUtil1MinAvg.0],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|High memory utilization |<p>The system is running out of free memory.</p> |`min(/Brocade_Foundry Performance by SNMP/vm.memory.util[snAgGblDynMemUtil.0],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

