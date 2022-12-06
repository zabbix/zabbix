
# Azure by HTTP

## Overview

For Zabbix version: 6.2 and higher.
This template is designed to monitor Microsoft Azure by HTTP.
It works without any external scripts and uses the script item.
Currently the template supports discovery of virtual machines (VMs), MySQL and PosgtreSQL servers.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

      See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure macros {$AZURE.APP_ID}, {$AZURE.PASSWORD}, {$AZURE.TENANT_ID}, and {$AZURE.SUBSCRIPTION_ID}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP_ID} |<p>Microsoft Azure app ID.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for API.</p> |`15s` |
|{$AZURE.MYSQL.DB.LOCATION.MATCHES} |<p>This macro is used in MySQL servers discovery rule.</p> |`.*` |
|{$AZURE.MYSQL.DB.LOCATION.NOT_MATCHES} |<p>This macro is used in MySQL servers discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.MYSQL.DB.NAME.MATCHES} |<p>This macro is used in MySQL servers discovery rule.</p> |`.*` |
|{$AZURE.MYSQL.DB.NAME.NOT_MATCHES} |<p>This macro is used in MySQL servers discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.PGSQL.DB.LOCATION.MATCHES} |<p>This macro is used in PostgreSQL servers discovery rule.</p> |`.*` |
|{$AZURE.PGSQL.DB.LOCATION.NOT_MATCHES} |<p>This macro is used in PostgreSQL servers discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.PGSQL.DB.NAME.MATCHES} |<p>This macro is used in PostgreSQL servers discovery rule.</p> |`.*` |
|{$AZURE.PGSQL.DB.NAME.NOT_MATCHES} |<p>This macro is used in PostgreSQL servers discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.RESOURCE_GROUP.MATCHES} |<p>This macro is used in discovery rules.</p> |`.*` |
|{$AZURE.RESOURCE_GROUP.NOT_MATCHES} |<p>This macro is used in discovery rules.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.SUBSCRIPTION_ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT_ID} |<p>Microsoft Azure tenant ID.</p> |`` |
|{$AZURE.VM.LOCATION.MATCHES} |<p>This macro is used in virtual machines discovery rule.</p> |`.*` |
|{$AZURE.VM.LOCATION.NOT_MATCHES} |<p>This macro is used in virtual machines discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.VM.NAME.MATCHES} |<p>This macro is used in virtual machines discovery rule.</p> |`.*` |
|{$AZURE.VM.NAME.NOT_MATCHES} |<p>This macro is used in virtual machines discovery rule.</p> |`CHANGE_IF_NEEDED` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|MySQL servers discovery |<p>The list of the MySQL servers is provided by the subscription.</p> |DEPENDENT |azure.mysql.servers.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.resources.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `^Microsoft.DBforMySQL`</p><p>- {#NAME} MATCHES_REGEX `{$AZURE.MYSQL.DB.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$AZURE.MYSQL.DB.NAME.NOT_MATCHES}`</p><p>- {#LOCATION} MATCHES_REGEX `{$AZURE.MYSQL.DB.LOCATION.MATCHES}`</p><p>- {#LOCATION} NOT_MATCHES_REGEX `{$AZURE.MYSQL.DB.LOCATION.NOT_MATCHES}`</p><p>- {#GROUP} MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.MATCHES}`</p><p>- {#GROUP} NOT_MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.NOT_MATCHES}`</p><p>**Overrides:**</p><p>Flexible server<br> - {#TYPE} MATCHES_REGEX `Microsoft.DBforMySQL/flexibleServers`<br>  - HOST_PROTOTYPE REGEXP ``</p><p>Single server<br> - {#TYPE} MATCHES_REGEX `Microsoft.DBforMySQL/servers`<br>  - HOST_PROTOTYPE REGEXP ``</p> |
|PostgreSQL servers discovery |<p>The list of the PostgreSQL servers is provided by the subscription.</p> |DEPENDENT |azure.pgsql.servers.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.resources.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `^Microsoft.DBforPostgreSQL`</p><p>- {#NAME} MATCHES_REGEX `{$AZURE.PGSQL.DB.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$AZURE.PGSQL.DB.NAME.NOT_MATCHES}`</p><p>- {#LOCATION} MATCHES_REGEX `{$AZURE.PGSQL.DB.LOCATION.MATCHES}`</p><p>- {#LOCATION} NOT_MATCHES_REGEX `{$AZURE.PGSQL.DB.LOCATION.NOT_MATCHES}`</p><p>- {#GROUP} MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.MATCHES}`</p><p>- {#GROUP} NOT_MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.NOT_MATCHES}`</p><p>**Overrides:**</p><p>Flexible server<br> - {#TYPE} MATCHES_REGEX `Microsoft.DBforPostgreSQL/flexibleServers`<br>  - HOST_PROTOTYPE REGEXP ``</p><p>Single server<br> - {#TYPE} MATCHES_REGEX `Microsoft.DBforPostgreSQL/servers`<br>  - HOST_PROTOTYPE REGEXP ``</p> |
|Virtual machines discovery |<p>The list of the virtual machines is provided by the subscription.</p> |DEPENDENT |azure.vm.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.resources.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `^Microsoft.Compute/virtualMachines$`</p><p>- {#NAME} MATCHES_REGEX `{$AZURE.VM.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$AZURE.VM.NAME.NOT_MATCHES}`</p><p>- {#LOCATION} MATCHES_REGEX `{$AZURE.VM.LOCATION.MATCHES}`</p><p>- {#LOCATION} NOT_MATCHES_REGEX `{$AZURE.VM.LOCATION.NOT_MATCHES}`</p><p>- {#GROUP} MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.MATCHES}`</p><p>- {#GROUP} NOT_MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure: Get resources |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.get.resources<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.get.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure by HTTP/azure.get.errors))>0` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure virtual machine by HTTP

## Overview

For Zabbix version: 6.2 and higher.
This template is designed to monitor Microsoft Azure virtual machines by HTTP.
It works without any external scripts and uses the script item.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

      See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure macros {$AZURE.APP_ID}, {$AZURE.PASSWORD}, {$AZURE.TENANT_ID}, {$AZURE.SUBSCRIPTION_ID}, and {$AZURE.RESOURCE_ID}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP_ID} |<p>Microsoft Azure app ID.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for API.</p> |`60s` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE_ID} |<p>Microsoft Azure virtual machine ID.</p> |`` |
|{$AZURE.SUBSCRIPTION_ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT_ID} |<p>Microsoft Azure tenant ID.</p> |`` |
|{$AZURE.VM.CPU.UTIL.CRIT} |<p>The critical threshold of the CPU utilization expressed in %.</p> |`90` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.vm.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.vm.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.vm.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.vm.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Percentage CPU |<p>The percentage of allocated compute units that are currently in use by the Virtual Machine(s).</p> |DEPENDENT |azure.vm.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PercentageCPU.average`</p> |
|Azure |Azure: Disk read rate |<p>Bytes read from the disk during the monitoring period (1 minute).</p> |DEPENDENT |azure.vm.disk.read.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskReadBytes.total`</p><p>- MULTIPLIER: `0.0167`</p> |
|Azure |Azure: Disk write rate |<p>Bytes written to the disk during the monitoring period (1 minute).</p> |DEPENDENT |azure.vm.disk.write.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskWriteBytes.total`</p><p>- MULTIPLIER: `0.0167`</p> |
|Azure |Azure: Disk read Operations/Sec |<p>The count of read operations from the disk per second.</p> |DEPENDENT |azure.vm.disk.read.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskReadOperationsSec.average`</p> |
|Azure |Azure: Disk write Operations/Sec |<p>The count of write operations to the disk per second.</p> |DEPENDENT |azure.vm.disk.write.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskWriteOperationsSec.average`</p> |
|Azure |Azure: CPU credits remaining |<p>The total number of credits available to burst. Only available on B-series burstable VMs.</p> |DEPENDENT |azure.vm.cpu.credits.remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.CPUCreditsRemaining.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: CPU credits consumed |<p>The total number of credits consumed by the Virtual Machine. Only available on B-series burstable VMs.</p> |DEPENDENT |azure.vm.cpu.credits.consumed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.CPUCreditsConsumed.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk read rate |<p>Bytes/Sec read from a single disk during the monitoring period.</p> |DEPENDENT |azure.vm.data.disk.read.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskReadBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk write rate |<p>Bytes/Sec written to a single disk during the monitoring period.</p> |DEPENDENT |azure.vm.data.disk.write.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskWriteBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk read operations/sec |<p>The read IOPS from a single disk during the monitoring period.</p> |DEPENDENT |azure.vm.data.disk.read.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskReadOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk write operations/sec |<p>The write IOPS from a single disk during the monitoring period.</p> |DEPENDENT |azure.vm.data.disk.write.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskWriteOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk queue depth |<p>The queue depth (or queue length) of the Data Disk.</p> |DEPENDENT |azure.vm.data.disk.queue.depth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskQueueDepth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk bandwidth consumed percentage |<p>The percentage of the Data Disk bandwidth consumed per minute.</p> |DEPENDENT |azure.vm.data.disk.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskBandwidthConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk IOPS consumed percentage |<p>The percentage of the Data Disk I/Os consumed per minute.</p> |DEPENDENT |azure.vm.data.disk.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskIOPSConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk target bandwidth |<p>Baseline bytes per second throughput Data Disk can achieve without bursting.</p> |DEPENDENT |azure.vm.data.disk.target.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskTargetBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk target IOPS |<p>Baseline IOPS Data Disk can achieve without bursting.</p> |DEPENDENT |azure.vm.data.disk.target.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskTargetIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk max burst bandwidth |<p>Maximum bytes per second throughput Data Disk can achieve with bursting.</p> |DEPENDENT |azure.vm.data.disk.max.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskMaxBurstBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk max burst IOPS |<p>Maximum IOPS Data Disk can achieve with bursting.</p> |DEPENDENT |azure.vm.data.disk.max.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskMaxBurstIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk used burst BPS credits percentage |<p>The percentage of the Data Disk burst bandwidth credits used so far.</p> |DEPENDENT |azure.vm.data.disk.used.burst.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskUsedBurstBPSCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk used burst IO credits percentage |<p>The percentage of the Data Disk burst I/O credits used so far.</p> |DEPENDENT |azure.vm.data.disk.used.burst.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskUsedBurstIOCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk read rate |<p>Bytes/Sec read from a single disk during the monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.read.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskReadBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk write rate |<p>Bytes/Sec written to a single disk during the monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.write.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskWriteBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk read operations/sec |<p>The read IOPS from a single disk during the monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.read.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskReadOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk write operations/sec |<p>The write IOPS from a single disk during the monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.write.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskWriteOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk queue depth |<p>The OS disk queue depth (or queue length).</p> |DEPENDENT |azure.vm.os.disk.queue.depth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskQueueDepth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk bandwidth consumed percentage |<p>The percentage of the operating system disk bandwidth consumed per minute.</p> |DEPENDENT |azure.vm.os.disk.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskBandwidthConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk IOPS consumed percentage |<p>The percentage of the operating system disk I/Os consumed per minute.</p> |DEPENDENT |azure.vm.os.disk.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskIOPSConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk target bandwidth |<p>Baseline bytes per second throughput OS Disk can achieve without bursting.</p> |DEPENDENT |azure.vm.os.disk.target.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskTargetBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk target IOPS |<p>Baseline IOPS OS Disk can achieve without bursting.</p> |DEPENDENT |azure.vm.os.disk.target.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskTargetIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk max burst bandwidth |<p>Maximum bytes per second throughput OS Disk can achieve with bursting.</p> |DEPENDENT |azure.vm.os.disk.max.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskMaxBurstBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk max burst IOPS |<p>Maximum IOPS OS Disk can achieve with bursting.</p> |DEPENDENT |azure.vm.os.disk.max.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskMaxBurstIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk used burst BPS credits percentage |<p>The percentage of the OS Disk burst bandwidth credits used so far.</p> |DEPENDENT |azure.vm.os.disk.used.burst.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskUsedBurstBPSCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk used burst IO credits percentage |<p>Percentage of OS Disk burst I/O credits used so far.</p> |DEPENDENT |azure.vm.os.disk.used.burst.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskUsedBurstIOCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Inbound flows |<p>Inbound Flows are number of the current flows in the inbound direction (traffic going into the VM).</p> |DEPENDENT |azure.vm.flows.inbound<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.InboundFlows.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Outbound flows |<p>Outbound Flows are number of the current flows in the outbound direction (traffic going out of the VM).</p> |DEPENDENT |azure.vm.flows.outbound<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OutboundFlows.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Inbound flows max creation rate |<p>The maximum creation rate of the inbound flows (traffic going into the VM).</p> |DEPENDENT |azure.vm.flows.inbound.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.InboundFlowsMaximumCreationRate.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Outbound flows max creation rate |<p>The maximum creation rate of the outbound flows (traffic going out of the VM).</p> |DEPENDENT |azure.vm.flows.outbound.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OutboundFlowsMaximumCreationRate.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium data disk cache read hit |<p>Premium Data Disk cache read hit.</p> |DEPENDENT |azure.vm.premium.data.disk.cache.read.hit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumDataDiskCacheReadHit.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium data disk cache read miss |<p>Premium Data Disk cache read miss.</p> |DEPENDENT |azure.vm.premium.data.disk.cache.read.miss<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumDataDiskCacheReadMiss.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium OS disk cache read hit |<p>Premium OS disk cache read hit.</p> |DEPENDENT |azure.vm.premium.os.disk.cache.read.hit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumOSDiskCacheReadHit.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium OS disk cache read miss |<p>Premium OS disk cache read miss.</p> |DEPENDENT |azure.vm.premium.os.disk.cache.read.miss<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumOSDiskCacheReadMiss.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: VM cached bandwidth consumed percentage |<p>Percentage of cached disk bandwidth consumed by the VM.</p> |DEPENDENT |azure.vm.cached.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMCachedBandwidthConsumedPercentage.average`</p> |
|Azure |Azure: VM cached IOPS consumed percentage |<p>Percentage of cached disk IOPS consumed by the VM.</p> |DEPENDENT |azure.vm.cached.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMCachedIOPSConsumedPercentage.average`</p> |
|Azure |Azure: VM uncached bandwidth consumed percentage |<p>The percentage of the uncached disk bandwidth consumed by the VM.</p> |DEPENDENT |azure.vm.uncached.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMUncachedBandwidthConsumedPercentage.average`</p> |
|Azure |Azure: VM uncached IOPS consumed percentage |<p>The percentage of the uncached disk IOPS consumed by the VM.</p> |DEPENDENT |azure.vm.uncached.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMUncachedIOPSConsumedPercentage.average`</p> |
|Azure |Azure: Network in total |<p>The number of bytes received on all network interfaces by the Virtual Machine(s) (Incoming Traffic).</p> |DEPENDENT |azure.vm.network.in.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.NetworkInTotal.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure: Network out total |<p>The number of bytes out on all network interfaces by the Virtual Machine(s) (Outgoing Traffic).</p> |DEPENDENT |azure.vm.network.out.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.NetworkOutTotal.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure: Available memory |<p>The amount of physical memory, in bytes, immediately available for allocation to a process or for system use in the Virtual Machine.</p> |DEPENDENT |azure.vm.memory.available<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.AvailableMemoryBytes.average`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure virtual machine by HTTP/azure.vm.data.errors))>0` |AVERAGE | |
|Azure: Virtual machine is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure virtual machine by HTTP/azure.vm.availability.state)=2` |HIGH | |
|Azure: Virtual machine is degraded |<p>The resource is in degraded state.</p> |`last(/Azure virtual machine by HTTP/azure.vm.availability.state)=1` |AVERAGE | |
|Azure: Virtual machine is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure virtual machine by HTTP/azure.vm.availability.state)=3` |WARNING | |
|Azure: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure virtual machine by HTTP/azure.vm.cpu.percentage,5m)>{$AZURE.VM.CPU.UTIL.CRIT}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure MySQL flexible server by HTTP

## Overview

For Zabbix version: 6.2 and higher.
This template is designed to monitor Microsoft Azure MySQL flexible servers by HTTP.
It works without any external scripts and uses the script item.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

      See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure macros {$AZURE.APP_ID}, {$AZURE.PASSWORD}, {$AZURE.TENANT_ID}, {$AZURE.SUBSCRIPTION_ID}, and {$AZURE.RESOURCE_ID}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP_ID} |<p>Microsoft Azure app ID.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for API.</p> |`60s` |
|{$AZURE.DB.ABORTED_CONN.MAX.WARN} |<p>The number of failed attempts to connect to the MySQL server for trigger expression.</p> |`25` |
|{$AZURE.DB.CPU.UTIL.CRIT} |<p>The critical threshold of the CPU utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.CRIT} |<p>The critical threshold of the storage utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.WARN} |<p>The warning threshold of the storage utilization expressed in %.</p> |`80` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE_ID} |<p>Microsoft Azure virtual machine ID.</p> |`` |
|{$AZURE.SUBSCRIPTION_ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT_ID} |<p>Microsoft Azure tenant ID.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure MySQL: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.db.mysql.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure MySQL: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.db.mysql.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.db.mysql.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.db.mysql.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Percentage CPU |<p>The CPU percent of a host.</p> |DEPENDENT |azure.db.mysql.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_percent.maximum`</p> |
|Azure |Azure MySQL: Memory utilization |<p>The memory percent of a host.</p> |DEPENDENT |azure.db.mysql.memory.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.memory_percent.maximum`</p> |
|Azure |Azure MySQL: Network out |<p>Network egress of a host in bytes.</p> |DEPENDENT |azure.db.mysql.network.egress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_egress.total`</p><p>- MULTIPLIER: `0.0088`</p> |
|Azure |Azure MySQL: Network in |<p>Network ingress of a host in bytes.</p> |DEPENDENT |azure.db.mysql.network.ingress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_ingress.total`</p><p>- MULTIPLIER: `0.0088`</p> |
|Azure |Azure MySQL: Connections active |<p>The count of active connections.</p> |DEPENDENT |azure.db.mysql.connections.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.active_connections.maximum`</p> |
|Azure |Azure MySQL: Connections total |<p>The count of total connections.</p> |DEPENDENT |azure.db.mysql.connections.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.total_connections.total`</p> |
|Azure |Azure MySQL: Connections aborted |<p>The count of aborted connections.</p> |DEPENDENT |azure.db.mysql.connections.aborted<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.aborted_connections.total`</p> |
|Azure |Azure MySQL: Queries |<p>The count of queries.</p> |DEPENDENT |azure.db.mysql.queries<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.Queries.total`</p> |
|Azure |Azure MySQL: IO consumption percent |<p>The IO percent.</p> |DEPENDENT |azure.db.mysql.io.consumption.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.io_consumption_percent.maximum`</p> |
|Azure |Azure MySQL: Storage percent |<p>The storage utilization expressed in %.</p> |DEPENDENT |azure.db.mysql.storage.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_percent.maximum`</p> |
|Azure |Azure MySQL: Storage used |<p>Used storage space expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_used.maximum`</p> |
|Azure |Azure MySQL: Storage limit |<p>The storage limit expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_limit.maximum`</p> |
|Azure |Azure MySQL: Backup storage used |<p>Used backup storage expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.backup.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.backup_storage_used.maximum`</p> |
|Azure |Azure MySQL: Replication lag |<p>The replication lag expressed in seconds.</p> |DEPENDENT |azure.db.mysql.replication.lag<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.replication_lag.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure MySQL: CPU credits remaining |<p>Remaining CPU credits.</p> |DEPENDENT |azure.db.mysql.cpu.credits.remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_credits_remaining.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure MySQL: CPU credits consumed |<p>Consumed CPU credits.</p> |DEPENDENT |azure.db.mysql.cpu.credits.consumed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_credits_consumed.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure MySQL: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure MySQL flexible server by HTTP/azure.db.mysql.data.errors))>0` |AVERAGE | |
|Azure MySQL: MySQL server is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure MySQL flexible server by HTTP/azure.db.mysql.availability.state)=2` |HIGH | |
|Azure MySQL: MySQL server is degraded |<p>The resource is in degraded state.</p> |`last(/Azure MySQL flexible server by HTTP/azure.db.mysql.availability.state)=1` |AVERAGE | |
|Azure MySQL: MySQL server is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure MySQL flexible server by HTTP/azure.db.mysql.availability.state)=3` |WARNING | |
|Azure MySQL: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure MySQL flexible server by HTTP/azure.db.mysql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}` |HIGH | |
|Azure MySQL: Server has aborted connections |<p>The number of failed attempts to connect to the MySQL server is more than {$AZURE.DB.ABORTED_CONN.MAX.WARN}.</p> |`min(/Azure MySQL flexible server by HTTP/azure.db.mysql.connections.aborted,5m)>{$AZURE.DB.ABORTED_CONN.MAX.WARN}` |AVERAGE | |
|Azure MySQL: Storage space is critically low |<p>Critical utilization of the storage space.</p> |`last(/Azure MySQL flexible server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}` |AVERAGE | |
|Azure MySQL: Storage space is low |<p>High utilization of the storage space.</p> |`last(/Azure MySQL flexible server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure MySQL single server by HTTP

## Overview

For Zabbix version: 6.2 and higher.
This template is designed to monitor Microsoft Azure MySQL single servers by HTTP.
It works without any external scripts and uses the script item.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

      See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure macros {$AZURE.APP_ID}, {$AZURE.PASSWORD}, {$AZURE.TENANT_ID}, {$AZURE.SUBSCRIPTION_ID}, and {$AZURE.RESOURCE_ID}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP_ID} |<p>Microsoft Azure app ID.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for API.</p> |`60s` |
|{$AZURE.DB.CPU.UTIL.CRIT} |<p>The critical threshold of the CPU utilization expressed in %.</p> |`90` |
|{$AZURE.DB.FAILED_CONN.MAX.WARN} |<p>The number of failed attempts to connect to the MySQL server for trigger expression.</p> |`25` |
|{$AZURE.DB.MEMORY.UTIL.CRIT} |<p>The critical threshold of the memory utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.CRIT} |<p>The critical threshold of the storage utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.WARN} |<p>The warning threshold of the storage utilization expressed in %.</p> |`80` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE_ID} |<p>Microsoft Azure virtual machine ID.</p> |`` |
|{$AZURE.SUBSCRIPTION_ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT_ID} |<p>Microsoft Azure tenant ID.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure MySQL: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.db.mysql.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure MySQL: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.db.mysql.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.db.mysql.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.db.mysql.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Percentage CPU |<p>The CPU percent of a host.</p> |DEPENDENT |azure.db.mysql.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_percent.average`</p> |
|Azure |Azure MySQL: Memory utilization |<p>The memory percent of a host.</p> |DEPENDENT |azure.db.mysql.memory.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.memory_percent.average`</p> |
|Azure |Azure MySQL: Network out |<p>Network outbound traffic across the active connections.</p> |DEPENDENT |azure.db.mysql.network.egress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_egress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.0088`</p> |
|Azure |Azure MySQL: Network in |<p>Network inbound traffic across the active connections.</p> |DEPENDENT |azure.db.mysql.network.ingress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_ingress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.0088`</p> |
|Azure |Azure MySQL: Connections active |<p>The count of active connections.</p> |DEPENDENT |azure.db.mysql.connections.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.active_connections.average`</p> |
|Azure |Azure MySQL: Connections failed |<p>The count of failed connections.</p> |DEPENDENT |azure.db.mysql.connections.failed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connections_failed.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure MySQL: IO consumption percent |<p>The IO percent.</p> |DEPENDENT |azure.db.mysql.io.consumption.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.io_consumption_percent.average`</p> |
|Azure |Azure MySQL: Storage percent |<p>The storage utilization expressed in %.</p> |DEPENDENT |azure.db.mysql.storage.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_percent.average`</p> |
|Azure |Azure MySQL: Storage used |<p>Used storage space expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_used.average`</p> |
|Azure |Azure MySQL: Storage limit |<p>The storage limit expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_limit.maximum`</p> |
|Azure |Azure MySQL: Backup storage used |<p>Used backup storage expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.backup.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.backup_storage_used.average`</p> |
|Azure |Azure MySQL: Replication lag |<p>The replication lag expressed in seconds.</p> |DEPENDENT |azure.db.mysql.replication.lag<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.seconds_behind_master.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure MySQL: Server log storage percent |<p>The storage utilization by a server log expressed in %.</p> |DEPENDENT |azure.db.mysql.storage.server.log.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_percent.average`</p> |
|Azure |Azure MySQL: Server log storage used |<p>The storage space used by a server log expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.server.log.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_usage.average`</p> |
|Azure |Azure MySQL: Server log storage limit |<p>The storage limit of a server log expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.server.log.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_limit.maximum`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure MySQL: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure MySQL single server by HTTP/azure.db.mysql.data.errors))>0` |AVERAGE | |
|Azure MySQL: MySQL server is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure MySQL single server by HTTP/azure.db.mysql.availability.state)=2` |HIGH | |
|Azure MySQL: MySQL server is degraded |<p>The resource is in degraded state.</p> |`last(/Azure MySQL single server by HTTP/azure.db.mysql.availability.state)=1` |AVERAGE | |
|Azure MySQL: MySQL server is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure MySQL single server by HTTP/azure.db.mysql.availability.state)=3` |WARNING | |
|Azure MySQL: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure MySQL single server by HTTP/azure.db.mysql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}` |HIGH | |
|Azure MySQL: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Azure MySQL single server by HTTP/azure.db.mysql.memory.percentage,5m)>{$AZURE.DB.MEMORY.UTIL.CRIT}` |AVERAGE | |
|Azure MySQL: Server has failed connections |<p>The number of failed attempts to connect to the MySQL server is more than {$AZURE.DB.FAILED_CONN.MAX.WARN}.</p> |`min(/Azure MySQL single server by HTTP/azure.db.mysql.connections.failed,5m)>{$AZURE.DB.FAILED_CONN.MAX.WARN}` |AVERAGE | |
|Azure MySQL: Storage space is critically low |<p>Critical utilization of the storage space.</p> |`last(/Azure MySQL single server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}` |AVERAGE | |
|Azure MySQL: Storage space is low |<p>High utilization of the storage space.</p> |`last(/Azure MySQL single server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure PostgreSQL flexible server by HTTP

## Overview

For Zabbix version: 6.2 and higher.
This template is designed to monitor Microsoft Azure PostgreSQL flexible servers by HTTP.
It works without any external scripts and uses the script item.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

      See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure macros {$AZURE.APP_ID}, {$AZURE.PASSWORD}, {$AZURE.TENANT_ID}, {$AZURE.SUBSCRIPTION_ID}, and {$AZURE.RESOURCE_ID}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP_ID} |<p>Microsoft Azure app ID.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for API.</p> |`60s` |
|{$AZURE.DB.CPU.UTIL.CRIT} |<p>The critical threshold of the CPU utilization expressed in %.</p> |`90` |
|{$AZURE.DB.MEMORY.UTIL.CRIT} |<p>The critical threshold of the memory utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.CRIT} |<p>The critical threshold of the storage utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.WARN} |<p>The warning threshold of the storage utilization expressed in %.</p> |`80` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE_ID} |<p>Microsoft Azure virtual machine ID.</p> |`` |
|{$AZURE.SUBSCRIPTION_ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT_ID} |<p>Microsoft Azure tenant ID.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure PostgreSQL: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.db.pgsql.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure PostgreSQL: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.db.pgsql.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.db.pgsql.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.db.pgsql.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Percentage CPU |<p>The CPU percent of a host.</p> |DEPENDENT |azure.db.pgsql.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_percent.average`</p> |
|Azure |Azure PostgreSQL: Memory utilization |<p>The memory percent of a host.</p> |DEPENDENT |azure.db.pgsql.memory.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.memory_percent.average`</p> |
|Azure |Azure PostgreSQL: Network out |<p>Network outbound traffic across the active connections.</p> |DEPENDENT |azure.db.pgsql.network.egress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_egress.total`</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure PostgreSQL: Network in |<p>Network inbound traffic across the active connections.</p> |DEPENDENT |azure.db.pgsql.network.ingress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_ingress.total`</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure PostgreSQL: Connections active |<p>The count of active connections.</p> |DEPENDENT |azure.db.pgsql.connections.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.active_connections.average`</p> |
|Azure |Azure PostgreSQL: Connections succeeded |<p>The count of succeeded connections.</p> |DEPENDENT |azure.db.pgsql.connections.succeeded<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connections_succeeded.total`</p> |
|Azure |Azure PostgreSQL: Connections failed |<p>The count of failed connections.</p> |DEPENDENT |azure.db.pgsql.connections.failed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connections_failed.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Storage percent |<p>The storage utilization expressed in %.</p> |DEPENDENT |azure.db.pgsql.storage.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_percent.average`</p> |
|Azure |Azure PostgreSQL: Storage used |<p>Used storage space expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_used.average`</p> |
|Azure |Azure PostgreSQL: Storage free |<p>Free storage space expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.free<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_free.average`</p> |
|Azure |Azure PostgreSQL: Backup storage used |<p>Used backup storage expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.backup.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.backup_storage_used.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: CPU credits remaining |<p>The total number of credits available to burst.</p> |DEPENDENT |azure.db.pgsql.cpu.credits.remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_credits_remaining.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: CPU credits consumed |<p>The total number of credits consumed by the database server.</p> |DEPENDENT |azure.db.pgsql.cpu.credits.consumed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_credits_consumed.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Data disk queue depth |<p>The number of outstanding I/O operations to the data disk.</p> |DEPENDENT |azure.db.pgsql.disk.queue.depth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.disk_queue_depth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Data disk IOPS |<p>I/O Operations per second.</p> |DEPENDENT |azure.db.pgsql.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.iops.average`</p> |
|Azure |Azure PostgreSQL: Data disk read IOPS |<p>The number of the data disk I/O read operations per second.</p> |DEPENDENT |azure.db.pgsql.iops.read<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.read_iops.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Data disk write IOPS |<p>The number of the data disk I/O write operations per second.</p> |DEPENDENT |azure.db.pgsql.iops.write<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.write_iops.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Data disk read Bps |<p>Bytes read per second from the data disk during the monitoring period.</p> |DEPENDENT |azure.db.pgsql.disk.bps.read<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.read_throughput.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Data disk write Bps |<p>Bytes written per second to the data disk during the monitoring period.</p> |DEPENDENT |azure.db.pgsql.disk.bps.write<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.write_throughput.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Transaction log storage used |<p>The storage space used by a transaction log expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.txlogs.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.txlogs_storage_used.average`</p> |
|Azure |Azure PostgreSQL: Maximum used transaction IDs |<p>The maximum number of used transaction IDs.</p> |DEPENDENT |azure.db.pgsql.txid.used.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.maximum_used_transactionIDs.average`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure PostgreSQL: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure PostgreSQL flexible server by HTTP/azure.db.pgsql.data.errors))>0` |AVERAGE | |
|Azure PostgreSQL: PostgreSQL server is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure PostgreSQL flexible server by HTTP/azure.db.pgsql.availability.state)=2` |HIGH | |
|Azure PostgreSQL: PostgreSQL server is degraded |<p>The resource is in degraded state.</p> |`last(/Azure PostgreSQL flexible server by HTTP/azure.db.pgsql.availability.state)=1` |AVERAGE | |
|Azure PostgreSQL: PostgreSQL server is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure PostgreSQL flexible server by HTTP/azure.db.pgsql.availability.state)=3` |WARNING | |
|Azure PostgreSQL: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure PostgreSQL flexible server by HTTP/azure.db.pgsql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}` |HIGH | |
|Azure PostgreSQL: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Azure PostgreSQL flexible server by HTTP/azure.db.pgsql.memory.percentage,5m)>{$AZURE.DB.MEMORY.UTIL.CRIT}` |AVERAGE | |
|Azure PostgreSQL: Storage space is critically low |<p>Critical utilization of the storage space.</p> |`last(/Azure PostgreSQL flexible server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}` |AVERAGE | |
|Azure PostgreSQL: Storage space is low |<p>High utilization of the storage space.</p> |`last(/Azure PostgreSQL flexible server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure PostgreSQL single server by HTTP

## Overview

For Zabbix version: 6.2 and higher.
This template is designed to monitor Microsoft Azure PostgreSQL servers by HTTP.
It works without any external scripts and uses the script item.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

      See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure macros {$AZURE.APP_ID}, {$AZURE.PASSWORD}, {$AZURE.TENANT_ID}, {$AZURE.SUBSCRIPTION_ID}, and {$AZURE.RESOURCE_ID}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP_ID} |<p>Microsoft Azure app ID.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for API.</p> |`60s` |
|{$AZURE.DB.CPU.UTIL.CRIT} |<p>The critical threshold of the CPU utilization expressed in %.</p> |`90` |
|{$AZURE.DB.MEMORY.UTIL.CRIT} |<p>The critical threshold of the memory utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.CRIT} |<p>The critical threshold of the storage utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.WARN} |<p>The warning threshold of the storage utilization expressed in %.</p> |`80` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE_ID} |<p>Microsoft Azure virtual machine ID.</p> |`` |
|{$AZURE.SUBSCRIPTION_ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT_ID} |<p>Microsoft Azure tenant ID.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure PostgreSQL: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.db.pgsql.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure PostgreSQL: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.db.pgsql.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.db.pgsql.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.db.pgsql.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Percentage CPU |<p>The CPU percent of a host.</p> |DEPENDENT |azure.db.pgsql.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_percent.average`</p> |
|Azure |Azure PsotgreSQL: Memory utilization |<p>The memory percent of a host.</p> |DEPENDENT |azure.db.pgsql.memory.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.memory_percent.average`</p> |
|Azure |Azure PostgreSQL: Network out |<p>Network outbound traffic across the active connections.</p> |DEPENDENT |azure.db.pgsql.network.egress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_egress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure PosgtreSQL: Network in |<p>Network inbound traffic across the active connections.</p> |DEPENDENT |azure.db.pgsql.network.ingress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_ingress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure PostgreSQL: Connections active |<p>The count of active connections.</p> |DEPENDENT |azure.db.pgsql.connections.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.active_connections.average`</p> |
|Azure |Azure PostgreSQL: Connections failed |<p>The count of failed connections.</p> |DEPENDENT |azure.db.pgsql.connections.failed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connections_failed.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: IO consumption percent |<p>The IO Percent.</p> |DEPENDENT |azure.db.pgsql.io.consumption.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.io_consumption_percent.average`</p> |
|Azure |Azure PostgreSQL: Storage percent |<p>The storage utilization expressed in %.</p> |DEPENDENT |azure.db.pgsql.storage.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_percent.average`</p> |
|Azure |Azure PostgreSQL: Storage used |<p>Used storage space expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_used.average`</p> |
|Azure |Azure PostgreSQL: Storage limit |<p>The storage limit expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_limit.maximum`</p> |
|Azure |Azure PostgreSQL: Backup storage used |<p>Used backup storage expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.backup.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.backup_storage_used.average`</p> |
|Azure |Azure PostgreSQL: Replication lag |<p>The replication lag expressed in seconds.</p> |DEPENDENT |azure.db.pgsql.replica.log.delay<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.pg_replica_log_delay_in_seconds.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Max lag across replicas in bytes |<p>Lag expressed in bytes for the most lagging replica.</p> |DEPENDENT |azure.db.pgsql.replica.log.delay.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.pg_replica_log_delay_in_bytes.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Server log storage percent |<p>The storage utilization by a server log expressed in %.</p> |DEPENDENT |azure.db.pgsql.storage.server.log.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_percent.average`</p> |
|Azure |Azure PostgreSQL: Server log storage used |<p>The storage space used by a server log expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.server.log.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_usage.average`</p> |
|Azure |Azure PostgreSQL: Server log storage limit |<p>The storage limit of a server log expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.server.log.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_limit.maximum`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure PostgreSQL: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure PostgreSQL single server by HTTP/azure.db.pgsql.data.errors))>0` |AVERAGE | |
|Azure PostgreSQL: PostgreSQL server is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure PostgreSQL single server by HTTP/azure.db.pgsql.availability.state)=2` |HIGH | |
|Azure PostgreSQL: PostgreSQL server is degraded |<p>The resource is in degraded state.</p> |`last(/Azure PostgreSQL single server by HTTP/azure.db.pgsql.availability.state)=1` |AVERAGE | |
|Azure PostgreSQL: PostgreSQL server is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure PostgreSQL single server by HTTP/azure.db.pgsql.availability.state)=3` |WARNING | |
|Azure PostgreSQL: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure PostgreSQL single server by HTTP/azure.db.pgsql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}` |HIGH | |
|Azure PsotgreSQL: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Azure PostgreSQL single server by HTTP/azure.db.pgsql.memory.percentage,5m)>{$AZURE.DB.MEMORY.UTIL.CRIT}` |AVERAGE | |
|Azure PostgreSQL: Storage space is critically low |<p>Critical utilization of the storage space.</p> |`last(/Azure PostgreSQL single server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}` |AVERAGE | |
|Azure PostgreSQL: Storage space is low |<p>High utilization of the storage space.</p> |`last(/Azure PostgreSQL single server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

