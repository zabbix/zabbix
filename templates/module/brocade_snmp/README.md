
# Brocade_Foundry Performance by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}||`90`|
|{$MEMORY.UTIL.MAX}||`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Brocade Foundry Performance: CPU utilization|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The statistics collection of 1 minute CPU utilization.</p>|SNMP agent|system.cpu.util[snAgGblCpuUtil1MinAvg.0]|
|Brocade Foundry Performance: Memory utilization|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The system dynamic memory utilization, in unit of percentage.</p><p>Deprecated: Refer to snAgSystemDRAMUtil.</p><p>For NI platforms, refer to snAgentBrdMemoryUtil100thPercent.</p>|SNMP agent|vm.memory.util[snAgGblDynMemUtil.0]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Foundry Performance: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Brocade_Foundry Performance by SNMP/system.cpu.util[snAgGblCpuUtil1MinAvg.0],5m)>{$CPU.UTIL.CRIT}`|Warning||
|Brocade Foundry Performance: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Brocade_Foundry Performance by SNMP/vm.memory.util[snAgGblDynMemUtil.0],5m)>{$MEMORY.UTIL.MAX}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

