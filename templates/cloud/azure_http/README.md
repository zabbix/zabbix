
# Azure by HTTP

## Overview

For Zabbix version: 6.2 and higher  
The template to monitor Microsoft Azure by HTTP.
It works without any external scripts and uses the script item.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via Azure CLI for your subscription.
  `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`
  https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli?toc=%2Fazure%2Fazure-resource-manager%2Ftoc.json&view=azure-cli-latest
2. Link template to the host.
3. Configure macros {$AZURE.APP_ID}, {$AZURE.PASSWORD}, {$AZURE.TENANT_ID} and {$AZURE.SUBSCRIPTION_ID}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP_ID} |<p>Microsoft Azure app ID.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>Response timeout for API.</p> |`15s` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.SUBSCRIPTION_ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT_ID} |<p>Microsoft Azure tenant ID.</p> |`` |
|{$AZURE.VM.LOCATION.MATCHES} |<p>This macro used in virtual machines discovery rule.</p> |`.*` |
|{$AZURE.VM.LOCATION.NOT_MATCHES} |<p>This macro used in virtual machines discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.VM.NAME.MATCHES} |<p>This macro used in virtual machines discovery rule.</p> |`.*` |
|{$AZURE.VM.NAME.NOT_MATCHES} |<p>This macro used in virtual machines discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.VM.RESOURCE_GROUP.MATCHES} |<p>This macro used in virtual machines discovery rule.</p> |`.*` |
|{$AZURE.VM.RESOURCE_GROUP.NOT_MATCHES} |<p>This macro used in virtual machines discovery rule.</p> |`CHANGE_IF_NEEDED` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Virtual machines discovery |<p>A list of the virtual machines in the subscription.</p> |DEPENDENT |azure.vm.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.resources.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `^Microsoft.Compute/virtualMachines$`</p><p>- {#NAME} MATCHES_REGEX `{$AZURE.VM.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$AZURE.VM.NAME.NOT_MATCHES}`</p><p>- {#LOCATION} MATCHES_REGEX `{$AZURE.VM.LOCATION.MATCHES}`</p><p>- {#LOCATION} NOT_MATCHES_REGEX `{$AZURE.VM.LOCATION.NOT_MATCHES}`</p><p>- {#GROUP} MATCHES_REGEX `{$AZURE.VM.RESOURCE_GROUP.MATCHES}`</p><p>- {#GROUP} NOT_MATCHES_REGEX `{$AZURE.VM.RESOURCE_GROUP.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure: Get resources |<p>The JSON with result of API requests.</p> |SCRIPT |azure.get.resources<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.get.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure: There are errors in requests to API |<p>Zabbix has received errors in requests to API.</p> |`length(last(/Azure by HTTP/azure.get.errors))>0` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure virtual machine by HTTP

## Overview

For Zabbix version: 6.2 and higher  
The template to monitor Microsoft Azure virtual machines by HTTP.
It works without any external scripts and uses the script item.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via Azure CLI for your subscription.
  `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`
  https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli
2. Link template to the host.
3. Configure macros {$AZURE.APP_ID}, {$AZURE.PASSWORD}, {$AZURE.TENANT_ID} and {$AZURE.SUBSCRIPTION_ID}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP_ID} |<p>Microsoft Azure app ID.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>Response timeout for API.</p> |`60s` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE_ID} |<p>Microsoft Azure virtual machine ID.</p> |`` |
|{$AZURE.SUBSCRIPTION_ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT_ID} |<p>Microsoft Azure tenant ID.</p> |`` |
|{$AZURE.VM.CPU.UTIL.CRIT} |<p>The critical threshold of the CPU utilization in %.</p> |`90` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure: Get data |<p>The JSON with result of API requests.</p> |SCRIPT |azure.vm.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.vm.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Availability state |<p>Availability status of the resource.</p> |DEPENDENT |azure.vm.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Availability status detailed |<p>Summary description of the availability status.</p> |DEPENDENT |azure.vm.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Percentage CPU |<p>The percentage of allocated compute units that are currently in use by the Virtual Machine(s).</p> |DEPENDENT |azure.vm.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PercentageCPU.average`</p> |
|Azure |Azure: Disk read rate |<p>Bytes read from disk during monitoring period (1 minute).</p> |DEPENDENT |azure.vm.disk.read.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskReadBytes.total`</p><p>- MULTIPLIER: `0.0167`</p> |
|Azure |Azure: Disk write rate |<p>Bytes written to disk during monitoring period (1 minute).</p> |DEPENDENT |azure.vm.disk.write.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskWriteBytes.total`</p><p>- MULTIPLIER: `0.0167`</p> |
|Azure |Azure: Disk read Operations/Sec |<p>Disk read IOPS.</p> |DEPENDENT |azure.vm.disk.read.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskReadOperationsSec.average`</p> |
|Azure |Azure: Disk write Operations/Sec |<p>Disk write IOPS.</p> |DEPENDENT |azure.vm.disk.write.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskWriteOperationsSec.average`</p> |
|Azure |Azure: CPU credits remaining |<p>Total number of credits available to burst. Only available on B-series burstable VMs.</p> |DEPENDENT |azure.vm.cpu.credits.remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.CPUCreditsRemaining.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: CPU credits consumed |<p>Total number of credits consumed by the Virtual Machine. Only available on B-series burstable VMs.</p> |DEPENDENT |azure.vm.cpu.credits.consumed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.CPUCreditsConsumed.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk read rate |<p>Bytes/Sec read from a single disk during monitoring period.</p> |DEPENDENT |azure.vm.data.disk.read.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskReadBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk write rate |<p>Bytes/Sec written to a single disk during monitoring period.</p> |DEPENDENT |azure.vm.data.disk.write.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskWriteBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk read operations/sec |<p>Read IOPS from a single disk during monitoring period.</p> |DEPENDENT |azure.vm.data.disk.read.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskReadOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk write operations/sec |<p>Write IOPS from a single disk during monitoring period.</p> |DEPENDENT |azure.vm.data.disk.write.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskWriteOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk queue depth |<p>Data Disk Queue Depth(or Queue Length).</p> |DEPENDENT |azure.vm.data.disk.queue.depth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskQueueDepth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk bandwidth consumed percentage |<p>Percentage of data disk bandwidth consumed per minute.</p> |DEPENDENT |azure.vm.data.disk.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskBandwidthConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk IOPS consumed percentage |<p>Percentage of data disk I/Os consumed per minute.</p> |DEPENDENT |azure.vm.data.disk.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskIOPSConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk target bandwidth |<p>Baseline bytes per second throughput Data Disk can achieve without bursting.</p> |DEPENDENT |azure.vm.data.disk.target.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskTargetBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk target IOPS |<p>Baseline IOPS Data Disk can achieve without bursting.</p> |DEPENDENT |azure.vm.data.disk.target.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskTargetIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk max burst bandwidth |<p>Maximum bytes per second throughput Data Disk can achieve with bursting.</p> |DEPENDENT |azure.vm.data.disk.max.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskMaxBurstBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk max burst IOPS |<p>Maximum IOPS Data Disk can achieve with bursting.</p> |DEPENDENT |azure.vm.data.disk.max.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskMaxBurstIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk used burst BPS credits percentage |<p>Percentage of Data Disk burst bandwidth credits used so far.</p> |DEPENDENT |azure.vm.data.disk.used.burst.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskUsedBurstBPSCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk used burst IO credits percentage |<p>Percentage of Data Disk burst I/O credits used so far.</p> |DEPENDENT |azure.vm.data.disk.used.burst.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskUsedBurstIOCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk read rate |<p>Bytes/Sec read from a single disk during monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.read.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskReadBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk write rate |<p>Bytes/Sec written to a single disk during monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.write.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskWriteBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk read operations/sec |<p>Read IOPS from a single disk during monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.read.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskReadOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk write operations/sec |<p>Write IOPS from a single disk during monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.write.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskWriteOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk queue depth |<p>OS Disk Queue Depth(or Queue Length).</p> |DEPENDENT |azure.vm.os.disk.queue.depth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskQueueDepth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk bandwidth consumed percentage |<p>Percentage of operating system disk bandwidth consumed per minute.</p> |DEPENDENT |azure.vm.os.disk.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskBandwidthConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk IOPS consumed percentage |<p>Percentage of operating system disk I/Os consumed per minute.</p> |DEPENDENT |azure.vm.os.disk.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskIOPSConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk target bandwidth |<p>Baseline bytes per second throughput OS Disk can achieve without bursting.</p> |DEPENDENT |azure.vm.os.disk.target.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskTargetBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk target IOPS |<p>Baseline IOPS OS Disk can achieve without bursting.</p> |DEPENDENT |azure.vm.os.disk.target.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskTargetIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk max burst bandwidth |<p>Maximum bytes per second throughput OS Disk can achieve with bursting.</p> |DEPENDENT |azure.vm.os.disk.max.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskMaxBurstBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk max burst IOPS |<p>Maximum IOPS OS Disk can achieve with bursting.</p> |DEPENDENT |azure.vm.os.disk.max.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskMaxBurstIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk used burst BPS credits percentage |<p>Percentage of OS Disk burst bandwidth credits used so far.</p> |DEPENDENT |azure.vm.os.disk.used.burst.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskUsedBurstBPSCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk used burst IO credits percentage |<p>Percentage of OS Disk burst I/O credits used so far.</p> |DEPENDENT |azure.vm.os.disk.used.burst.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskUsedBurstIOCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Inbound flows |<p>Inbound Flows are number of current flows in the inbound direction (traffic going into the VM).</p> |DEPENDENT |azure.vm.flows.inbound<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.InboundFlows.average`</p> |
|Azure |Azure: Outbound flows |<p>Outbound Flows are number of current flows in the outbound direction (traffic going out of the VM).</p> |DEPENDENT |azure.vm.flows.outbound<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OutboundFlows.average`</p> |
|Azure |Azure: Inbound flows max creation rate |<p>The maximum creation rate of inbound flows (traffic going into the VM).</p> |DEPENDENT |azure.vm.flows.inbound.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.InboundFlowsMaximumCreationRate.average`</p> |
|Azure |Azure: Outbound flows max creation rate |<p>The maximum creation rate of outbound flows (traffic going out of the VM).</p> |DEPENDENT |azure.vm.flows.outbound.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OutboundFlowsMaximumCreationRate.average`</p> |
|Azure |Azure: Premium data disk cache read hit |<p>Premium data disk cache read hit.</p> |DEPENDENT |azure.vm.premium.data.disk.cache.read.hit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumDataDiskCacheReadHit.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium data disk cache read miss |<p>Premium data disk cache read miss.</p> |DEPENDENT |azure.vm.premium.data.disk.cache.read.miss<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumDataDiskCacheReadMiss.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium OS disk cache read hit |<p>Premium OS disk cache read hit.</p> |DEPENDENT |azure.vm.premium.os.disk.cache.read.hit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumOSDiskCacheReadHit.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium OS disk cache read miss |<p>Premium OS disk cache read miss.</p> |DEPENDENT |azure.vm.premium.os.disk.cache.read.miss<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumOSDiskCacheReadMiss.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: VM cached bandwidth consumed percentage |<p>Percentage of cached disk bandwidth consumed by the VM.</p> |DEPENDENT |azure.vm.cached.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMCachedBandwidthConsumedPercentage.average`</p> |
|Azure |Azure: VM cached IOPS consumed percentage |<p>Percentage of cached disk IOPS consumed by the VM.</p> |DEPENDENT |azure.vm.cached.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMCachedIOPSConsumedPercentage.average`</p> |
|Azure |Azure: VM uncached bandwidth consumed percentage |<p>Percentage of uncached disk bandwidth consumed by the VM.</p> |DEPENDENT |azure.vm.uncached.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMUncachedBandwidthConsumedPercentage.average`</p> |
|Azure |Azure: VM uncached IOPS consumed percentage |<p>Percentage of uncached disk IOPS consumed by the VM.</p> |DEPENDENT |azure.vm.uncached.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMUncachedIOPSConsumedPercentage.average`</p> |
|Azure |Azure: Network in total |<p>The number of bytes received on all network interfaces by the Virtual Machine(s) (Incoming Traffic).</p> |DEPENDENT |azure.vm.network.in.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.NetworkInTotal.total`</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure: Network out total |<p>The number of bytes out on all network interfaces by the Virtual Machine(s) (Outgoing Traffic).</p> |DEPENDENT |azure.vm.network.out.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.NetworkOutTotal.total`</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure: Available memory |<p>Amount of physical memory, in bytes, immediately available for allocation to a process or for system use in the Virtual Machine.</p> |DEPENDENT |azure.vm.memory.available<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.AvailableMemoryBytes.average`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure: There are errors in requests to API |<p>Zabbix has received errors in requests to API.</p> |`length(last(/Azure virtual machine by HTTP/azure.vm.data.errors))>0` |AVERAGE | |
|Azure: Virtual machine is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure virtual machine by HTTP/azure.vm.availability.state)=2` |HIGH | |
|Azure: Virtual machine is degraded |<p>The resource is in degraded state.</p> |`last(/Azure virtual machine by HTTP/azure.vm.availability.state)=1` |AVERAGE | |
|Azure: Virtual machine is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure virtual machine by HTTP/azure.vm.availability.state)=3` |WARNING | |
|Azure: High CPU utilization |<p>CPU utilization is too high. the system might be slow to respond.</p> |`min(/Azure virtual machine by HTTP/azure.vm.cpu.percentage,5m)>{$AZURE.VM.CPU.UTIL.CRIT}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

