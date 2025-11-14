
# VMware FQDN

## Overview

This template set is designed for the effortless deployment of VMware vCenter and ESX hypervisor monitoring and doesn't require any external scripts.

- The template "VMware Guest" is used in discovery and normally should not be manually linked to a host.
- The template "VMware Hypervisor" can be used in discovery as well as manually linked to a host.

For additional information, please see [Zabbix documentation on VM monitoring](https://www.zabbix.com/documentation/8.0/manual/vm_monitoring).

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- VMware 6.0, 6.7, 7.0, 8.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Compile Zabbix server with the required options (`--with-libxml2` and `--with-libcurl`).
2. Set the `StartVMwareCollectors` option in the Zabbix server configuration file to "1" or more.
3. Create a new host.
4. If you want to use a separate user for monitoring, make sure that the user is a member of the `SystemConfiguration.ReadOnly` and `vStatsGroup` groups.
Set the host macros (on the host or template level) required for VMware authentication:
```text
{$VMWARE.URL}
{$VMWARE.USERNAME}
{$VMWARE.PASSWORD}
```
5. Link the template to the host created earlier.

Note: To enable discovery of hardware sensors of VMware hypervisors, set the macro `{$VMWARE.HV.SENSOR.DISCOVERY}` to the value `true` on the discovered host level.

Additional resources:
- How to [create a custom performance counter](https://www.zabbix.com/documentation/8.0/manual/vm_monitoring/vmware_keys#footnotes).
- How to get all supported counters and [generate a path for the custom performance counter](https://www.zabbix.com/documentation/8.0/manual/appendix/items/perf_counters).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VMWARE.URL}|<p>VMware service (vCenter or ESX hypervisor) SDK URL (https://servername/sdk).</p>||
|{$VMWARE.USERNAME}|<p>VMware service user name.</p>||
|{$VMWARE.PASSWORD}|<p>VMware service `{$USERNAME}` user password.</p>||
|{$VMWARE.PROXY}|<p>Sets the HTTP proxy for script items. If this parameter is empty, then no proxy is used.</p>||
|{$VMWARE.DATASTORE.SPACE.WARN}|<p>The warning threshold of the datastore free space.</p>|`20`|
|{$VMWARE.DATASTORE.SPACE.CRIT}|<p>The critical threshold of the datastore free space.</p>|`10`|
|{$VMWARE.HV.SENSOR.DISCOVERY}|<p>Set "true"/"false" to enable or disable monitoring of hardware sensors.</p>|`FALSE`|
|{$VMWARE.HV.SENSOR.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of hardware sensor names to be allowed in discovery.</p>|`.*`|
|{$VMWARE.HV.SENSOR.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of hardware sensor names to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$VMWARE.VM.POWERSTATE}|<p>Possibility to filter out VMs by power state.</p>|`poweredOn\|poweredOff\|suspended`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get alarms|<p>Get alarm status.</p>|Simple check|vmware.alarms.get[{$VMWARE.URL}]|
|Event log|<p>Collect VMware event log. See also: https://www.zabbix.com/documentation/8.0/manual/config/items/preprocessing/examples#filtering_vmware_event_log_records</p>|Simple check|vmware.eventlog[{$VMWARE.URL},skip]|
|Full name|<p>VMware service full name.</p>|Simple check|vmware.fullname[{$VMWARE.URL}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Version|<p>VMware service version.</p>|Simple check|vmware.version[{$VMWARE.URL}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Get Overall Health VC State|<p>Gets overall health of the system. This item works only with VMware vCenter versions above 6.5.</p>|Script|vmware.health.get|
|Overall Health VC State error check|<p>Data collection error check.</p>|Dependent item|vmware.health.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Overall Health VC State|<p>VMware Overall health of system. One of the following:</p><p>- Gray: No health data is available for this service.</p><p>- Green: Service is healthy.</p><p>- Yellow: The service is in a healthy state, but experiencing some level of problems.</p><p>- Orange: The service health is degraded. The service might have serious problems.</p><p>- Red: The service is unavailable, not functioning properly, or will stop functioning soon.</p><p>- Not available: The health status is unavailable (not supported on the vCenter or ESXi side).</p>|Dependent item|vmware.health.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware FQDN: Failed to get Overall Health VC State|<p>Failed to get data. Check debug log for more information.</p>|`length(last(/VMware FQDN/vmware.health.check))>0`|Warning||
|VMware FQDN: Overall Health VC State is not Green|<p>One or more components in the appliance might be in an unusable status and the appliance might soon become unresponsive.</p>|`last(/VMware FQDN/vmware.health.state)>0 and last(/VMware FQDN/vmware.health.state)<>6`|Average||

### LLD rule VMware alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware alarm discovery|<p>Discovery of alarms.</p>|Dependent item|vmware.alarms.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for VMware alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#VMWARE.ALARMS.NAME}|<p>VMware alarm status.</p>|Dependent item|vmware.alarms.status["{#VMWARE.ALARMS.KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.key == "{#VMWARE.ALARMS.KEY}")].key.first()`</p><p>⛔️Custom on fail: Set value to: `-1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for VMware alarm discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware FQDN: {#VMWARE.ALARMS.NAME}|<p>{#VMWARE.ALARMS.DESC}</p>|`last(/VMware FQDN/vmware.alarms.status["{#VMWARE.ALARMS.KEY}"])<>-1`|Not_classified||

### LLD rule VMware cluster discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware cluster discovery|<p>Discovery of clusters.</p>|Simple check|vmware.cluster.discovery[{$VMWARE.URL}]|

### Item prototypes for VMware cluster discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Status of [{#CLUSTER.NAME}] cluster|<p>VMware cluster status. One of the following:</p><p>- Gray: Unknown;</p><p>- Green: OK;</p><p>- Yellow: It might have a problem;</p><p>- Red: It has a problem.</p>|Simple check|vmware.cluster.status[{$VMWARE.URL},{#CLUSTER.NAME}]|

### Trigger prototypes for VMware cluster discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware FQDN: The [{#CLUSTER.NAME}] status is Red|<p>A cluster enabled for DRS becomes invalid (red) when the tree is no longer internally consistent, that is, when resource constraints are not observed. See also: https://docs.vmware.com/en/VMware-vSphere/8.0/vsphere-resource-management/GUID-C7417CAA-BD38-41D0-9529-9E7A5898BB12.html</p>|`last(/VMware FQDN/vmware.cluster.status[{$VMWARE.URL},{#CLUSTER.NAME}])=3`|High||
|VMware FQDN: The [{#CLUSTER.NAME}] status is Yellow|<p>A cluster becomes overcommitted (yellow) when the tree of resource pools and virtual machines is internally consistent but the cluster does not have the capacity to support all the resources reserved by the child resource pools. See also: https://docs.vmware.com/en/VMware-vSphere/8.0/vsphere-resource-management/GUID-ED8240A0-FB54-4A31-BD3D-F23FE740F10C.html</p>|`last(/VMware FQDN/vmware.cluster.status[{$VMWARE.URL},{#CLUSTER.NAME}])=2`|Average|**Depends on**:<br><ul><li>VMware FQDN: The [{#CLUSTER.NAME}] status is Red</li></ul>|

### LLD rule VMware datastore discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware datastore discovery|<p>Discovery of VMware datastores.</p>|Simple check|vmware.datastore.discovery[{$VMWARE.URL}]|

### Item prototypes for VMware datastore discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Average read IOPS of the datastore [{#DATASTORE}]|<p>IOPS for a read operation from the datastore (milliseconds).</p>|Simple check|vmware.datastore.read[{$VMWARE.URL},{#DATASTORE.UUID},rps]|
|Average write IOPS of the datastore [{#DATASTORE}]|<p>IOPS for a write operation to the datastore (milliseconds).</p>|Simple check|vmware.datastore.write[{$VMWARE.URL},{#DATASTORE.UUID},rps]|
|Average read latency of the datastore [{#DATASTORE}]|<p>Amount of time for a read operation from the datastore (milliseconds).</p>|Simple check|vmware.datastore.read[{$VMWARE.URL},{#DATASTORE.UUID},latency]|
|Free space on datastore [{#DATASTORE}] (percentage)|<p>VMware datastore free space (percentage from the total).</p>|Simple check|vmware.datastore.size[{$VMWARE.URL},{#DATASTORE.UUID},pfree]|
|Total size of datastore [{#DATASTORE}]|<p>VMware datastore space in bytes.</p>|Simple check|vmware.datastore.size[{$VMWARE.URL},{#DATASTORE.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Average write latency of the datastore [{#DATASTORE}]|<p>Amount of time for a write operation to the datastore (milliseconds).</p>|Simple check|vmware.datastore.write[{$VMWARE.URL},{#DATASTORE.UUID},latency]|

### Trigger prototypes for VMware datastore discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware FQDN: [{#DATASTORE}]: Free space is critically low|<p>Datastore free space has fallen below the critical threshold.</p>|`last(/VMware FQDN/vmware.datastore.size[{$VMWARE.URL},{#DATASTORE.UUID},pfree])<{$VMWARE.DATASTORE.SPACE.CRIT}`|High||
|VMware FQDN: [{#DATASTORE}]: Free space is low|<p>Datastore free space has fallen below the warning threshold.</p>|`last(/VMware FQDN/vmware.datastore.size[{$VMWARE.URL},{#DATASTORE.UUID},pfree])<{$VMWARE.DATASTORE.SPACE.WARN}`|Warning|**Depends on**:<br><ul><li>VMware FQDN: [{#DATASTORE}]: Free space is critically low</li></ul>|

### LLD rule VMware hypervisor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware hypervisor discovery|<p>Discovery of hypervisors.</p>|Simple check|vmware.hv.discovery[{$VMWARE.URL}]|

### LLD rule VMware VM FQDN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware VM FQDN discovery|<p>Discovery of guest virtual machines.</p>|Simple check|vmware.vm.discovery[{$VMWARE.URL}]|

# VMware Guest

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VMWARE.URL}|<p>VMware service (vCenter or ESX hypervisor) SDK URL (https://servername/sdk).</p>||
|{$VMWARE.USERNAME}|<p>VMware service user name.</p>||
|{$VMWARE.PASSWORD}|<p>VMware service `{$USERNAME}` user password.</p>||
|{$VMWARE.VM.FS.PFREE.MIN.WARN}|<p>VMware guest free space threshold for the warning trigger.</p>|`20`|
|{$VMWARE.VM.FS.PFREE.MIN.CRIT}|<p>VMware guest free space threshold for the critical trigger.</p>|`10`|
|{$VMWARE.VM.FS.TRIGGER.USED}|<p>VMware guest used free space trigger. Set to "1"/"0" to enable or disable the trigger.</p>|`0`|
|{$VMWARE.HYPERVISOR.MAINTENANCE}|<p>If the hypervisor is in maintenance mode, all other problems on the VM will be suppressed automatically. Set to "1"/"0" to enable or disable this feature.</p>|`1`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Snapshot consolidation needed|<p>Displays whether snapshot consolidation is needed or not. One of the following:</p><p>- True;</p><p>- False.</p>|Simple check|vmware.vm.consolidationneeded[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Snapshot count|<p>Snapshot count of the guest VM.</p>|Dependent item|vmware.vm.snapshot.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Get snapshots|<p>Snapshots of the guest VM.</p>|Simple check|vmware.vm.snapshot.get[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Snapshot latest date|<p>Latest snapshot date of the guest VM.</p>|Dependent item|vmware.vm.snapshot.latestdate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.latestdate`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VM state|<p>VMware virtual machine state. One of the following:</p><p>- Not running;</p><p>- Resetting;</p><p>- Running;</p><p>- Shutting down;</p><p>- Standby;</p><p>- Unknown.</p>|Simple check|vmware.vm.state[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VMware Tools status|<p>Monitoring of VMware Tools. One of the following:</p><p>- Guest tools executing scripts: VMware Tools is starting.</p><p>- Guest tools not running: VMware Tools is not running.</p><p>- Guest tools running: VMware Tools is running.</p>|Simple check|vmware.vm.tools[{$VMWARE.URL},{$VMWARE.VM.UUID},status]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VMware Tools version|<p>Monitoring of the VMware Tools version.</p>|Simple check|vmware.vm.tools[{$VMWARE.URL},{$VMWARE.VM.UUID},version]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Cluster name|<p>Cluster name of the guest VM.</p>|Simple check|vmware.vm.cluster.name[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Number of virtual CPUs|<p>Number of virtual CPUs assigned to the guest.</p>|Simple check|vmware.vm.cpu.num[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|CPU ready|<p>Time that the VM was ready, but unable to get scheduled to run on the physical CPU during the last measurement interval (VMware vCenter/ESXi Server performance counter sampling interval - 20 seconds).</p>|Simple check|vmware.vm.cpu.ready[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|CPU usage|<p>Current upper-bound on CPU usage. The upper-bound is based on the host the VM is current running on, as well as limits configured on the VM itself or any parent resource pool. Valid while the VM is running.</p>|Simple check|vmware.vm.cpu.usage[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Datacenter name|<p>Datacenter name of the guest VM.</p>|Simple check|vmware.vm.datacenter.name[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Hypervisor name|<p>Hypervisor name of the guest VM.</p>|Simple check|vmware.vm.hv.name[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Hypervisor maintenance mode|<p>Hypervisor maintenance mode. One of the following:</p><p>- Normal mode;</p><p>- Maintenance mode.</p>|Simple check|vmware.vm.hv.maintenance[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Ballooned memory|<p>The amount of guest physical memory that is currently reclaimed through the balloon driver.</p>|Simple check|vmware.vm.memory.size.ballooned[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Compressed memory|<p>The amount of memory currently in the compression cache for this VM.</p>|Simple check|vmware.vm.memory.size.compressed[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Private memory|<p>Amount of memory backed by host memory and not being shared.</p>|Simple check|vmware.vm.memory.size.private[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Shared memory|<p>The amount of guest physical memory shared through transparent page sharing.</p>|Simple check|vmware.vm.memory.size.shared[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Swapped memory|<p>The amount of guest physical memory swapped out to the VM's swap device by ESX.</p>|Simple check|vmware.vm.memory.size.swapped[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Guest memory usage|<p>The amount of guest physical memory that is being used by the VM.</p>|Simple check|vmware.vm.memory.size.usage.guest[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Host memory usage|<p>The amount of host physical memory allocated to the VM, accounting for the amount saved from memory sharing with other VMs.</p>|Simple check|vmware.vm.memory.size.usage.host[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Memory size|<p>Total size of configured memory.</p>|Simple check|vmware.vm.memory.size[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power state|<p>The current power state of the VM. One of the following:</p><p>- Powered off;</p><p>- Powered on;</p><p>- Suspended.</p>|Simple check|vmware.vm.powerstate[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Committed storage space|<p>Total storage space, in bytes, committed to this VM across all datastores.</p>|Simple check|vmware.vm.storage.committed[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Uncommitted storage space|<p>Additional storage space, in bytes, potentially used by this VM on all datastores.</p>|Simple check|vmware.vm.storage.uncommitted[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Unshared storage space|<p>Total storage space, in bytes, occupied by the VM across all datastores that is not shared with any other VM.</p>|Simple check|vmware.vm.storage.unshared[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Uptime|<p>System uptime.</p>|Simple check|vmware.vm.uptime[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Boot time|<p>System boot time.</p>|Simple check|vmware.vm.property[{$VMWARE.URL},{$VMWARE.VM.UUID},runtime.bootTime]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return Math.floor(Date.parse(value)/1000);`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Guest memory swapped|<p>Amount of guest physical memory that is swapped out to the swap space.</p>|Simple check|vmware.vm.guest.memory.size.swapped[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Host memory consumed|<p>Amount of host physical memory consumed for backing up guest physical memory pages.</p>|Simple check|vmware.vm.memory.size.consumed[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Host memory usage in percent|<p>Percentage of host physical memory that has been consumed.</p>|Simple check|vmware.vm.memory.usage[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|CPU usage in percent|<p>CPU usage as a percentage during the interval.</p>|Simple check|vmware.vm.cpu.usage.perf[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|CPU latency in percent|<p>Percentage of time the VM is unable to run because it is contending for access to the physical CPU(s).</p>|Simple check|vmware.vm.cpu.latency[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|CPU readiness latency in percent|<p>Percentage of time that the virtual machine was ready, but was unable to get scheduled to run on the physical CPU.</p>|Simple check|vmware.vm.cpu.readiness[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|CPU swap-in latency in percent|<p>Percentage of CPU time spent waiting for a swap-in.</p>|Simple check|vmware.vm.cpu.swapwait[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|Uptime of guest OS|<p>Total time elapsed since the last operating system boot-up (in seconds). Data is collected if Guest OS Add-ons (VMware Tools) are installed.</p>|Simple check|vmware.vm.guest.osuptime[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "Performance counter data is not available."`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware Guest: Snapshot consolidation needed|<p>Snapshot consolidation needed.</p>|`last(/VMware Guest/vmware.vm.consolidationneeded[{$VMWARE.URL},{$VMWARE.VM.UUID}])=0`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>VMware Guest: Hypervisor is in the maintenance mode</li></ul>|
|VMware Guest: VM is not running|<p>VMware virtual machine is not running.</p>|`last(/VMware Guest/vmware.vm.state[{$VMWARE.URL},{$VMWARE.VM.UUID}]) <> 2`|Average|**Depends on**:<br><ul><li>VMware Guest: Hypervisor is in the maintenance mode</li></ul>|
|VMware Guest: VMware Tools is not running|<p>VMware Tools is not running on the VM.</p>|`last(/VMware Guest/vmware.vm.tools[{$VMWARE.URL},{$VMWARE.VM.UUID},status]) = 1`|Warning|**Depends on**:<br><ul><li>VMware Guest: VM is not running</li><li>VMware Guest: Hypervisor is in the maintenance mode</li></ul>|
|VMware Guest: Hypervisor is in the maintenance mode|<p>Hypervisor is in the maintenance mode. All other problem on the host will be suppressed.</p>|`last(/VMware Guest/vmware.vm.hv.maintenance[{$VMWARE.URL},{$VMWARE.VM.UUID}])=1 and {$VMWARE.HYPERVISOR.MAINTENANCE}=1`|Info||
|VMware Guest: VM has been restarted|<p>Uptime is less than 10 minutes.</p>|`(now() - last(/VMware Guest/vmware.vm.property[{$VMWARE.URL},{$VMWARE.VM.UUID},runtime.bootTime]) < 10m) and last(/VMware Guest/vmware.vm.powerstate[{$VMWARE.URL},{$VMWARE.VM.UUID}]) = 1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>VMware Guest: Hypervisor is in the maintenance mode</li></ul>|

### LLD rule Network device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network device discovery|<p>Discovery of all network devices.</p>|Simple check|vmware.vm.net.if.discovery[{$VMWARE.URL},{$VMWARE.VM.UUID}]|

### Item prototypes for Network device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Number of bytes received on interface [{#IFBACKINGDEVICE}]/[{#IFDESC}]|<p>VMware virtual machine network interface input statistics (bytes per second).</p>|Simple check|vmware.vm.net.if.in[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},bps]|
|Number of packets received on interface [{#IFBACKINGDEVICE}]/[{#IFDESC}]|<p>VMware virtual machine network interface input statistics (packets per second).</p>|Simple check|vmware.vm.net.if.in[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},pps]|
|Number of bytes transmitted on interface [{#IFBACKINGDEVICE}]/[{#IFDESC}]|<p>VMware virtual machine network interface output statistics (bytes per second).</p>|Simple check|vmware.vm.net.if.out[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},bps]|
|Number of packets transmitted on interface [{#IFBACKINGDEVICE}]/[{#IFDESC}]|<p>VMware virtual machine network interface output statistics (packets per second).</p>|Simple check|vmware.vm.net.if.out[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},pps]|
|Network utilization on interface [{#IFBACKINGDEVICE}]/[{#IFDESC}]|<p>VMware virtual machine network utilization (combined transmit and receive rates) during the interval.</p>|Simple check|vmware.vm.net.if.usage[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME}]|

### LLD rule Disk device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk device discovery|<p>Discovery of all disk devices.</p>|Simple check|vmware.vm.vfs.dev.discovery[{$VMWARE.URL},{$VMWARE.VM.UUID}]|

### Item prototypes for Disk device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Average number of bytes read from the disk [{#DISKDESC}]|<p>VMware virtual machine disk device read statistics (bytes per second).</p>|Simple check|vmware.vm.vfs.dev.read[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},bps]|
|Average number of reads from the disk [{#DISKDESC}]|<p>VMware virtual machine disk device read statistics (operations per second).</p>|Simple check|vmware.vm.vfs.dev.read[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},ops]|
|Average number of bytes written to the disk [{#DISKDESC}]|<p>VMware virtual machine disk device write statistics (bytes per second).</p>|Simple check|vmware.vm.vfs.dev.write[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},bps]|
|Average number of writes to the disk [{#DISKDESC}]|<p>VMware virtual machine disk device write statistics (operations per second).</p>|Simple check|vmware.vm.vfs.dev.write[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},ops]|
|Average number of outstanding read requests to the disk [{#DISKDESC}]|<p>Average number of outstanding read requests to the virtual disk during the collection interval.</p>|Simple check|vmware.vm.storage.readoio[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}]|
|Average number of outstanding write requests to the disk [{#DISKDESC}]|<p>Average number of outstanding write requests to the virtual disk during the collection interval.</p>|Simple check|vmware.vm.storage.writeoio[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}]|
|Average write latency to the disk [{#DISKDESC}]|<p>The average time a write to the virtual disk takes.</p>|Simple check|vmware.vm.storage.totalwritelatency[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}]|
|Average read latency from the disk [{#DISKDESC}]|<p>The average time a read from the virtual disk takes.</p>|Simple check|vmware.vm.storage.totalreadlatency[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}]|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>Discovery of all guest file systems.</p>|Simple check|vmware.vm.vfs.fs.discovery[{$VMWARE.URL},{$VMWARE.VM.UUID}]|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Free disk space on [{#FSNAME}]|<p>VMware virtual machine file system statistics (bytes).</p>|Simple check|vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},free]|
|Free disk space on [{#FSNAME}] (percentage)|<p>VMware virtual machine file system statistics (percentage).</p>|Simple check|vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},pfree]|
|Total disk space on [{#FSNAME}]|<p>VMware virtual machine total disk space (bytes).</p>|Simple check|vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},total]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Used disk space on [{#FSNAME}]|<p>VMware virtual machine used disk space (bytes).</p>|Simple check|vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},used]|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware Guest: [{#FSNAME}]: Disk space is critically low|<p>The disk free space on [{#FSNAME}] has been less than `{$VMWARE.VM.FS.PFREE.MIN.CRIT:"{#FSNAME}"}`% for 5m.</p>|`max(/VMware Guest/vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},pfree],5m)<{$VMWARE.VM.FS.PFREE.MIN.CRIT:"{#FSNAME}"} and {$VMWARE.VM.FS.TRIGGER.USED:"{#FSNAME}"}=1`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>VMware Guest: Hypervisor is in the maintenance mode</li></ul>|
|VMware Guest: [{#FSNAME}]: Disk space is low|<p>The disk free space on [{#FSNAME}] has been less than `{$VMWARE.VM.FS.PFREE.MIN.WARN:"{#FSNAME}"}`% for 5m.</p>|`max(/VMware Guest/vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},pfree],5m)<{$VMWARE.VM.FS.PFREE.MIN.WARN:"{#FSNAME}"} and {$VMWARE.VM.FS.TRIGGER.USED:"{#FSNAME}"}=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>VMware Guest: [{#FSNAME}]: Disk space is critically low</li><li>VMware Guest: Hypervisor is in the maintenance mode</li></ul>|

# VMware Hypervisor

## Overview

This template is designed for the effortless deployment of VMware ESX hypervisor monitoring and doesn't require any external scripts.

This template can be used in discovery as well as manually linked to a host.

For additional information, please see [Zabbix documentation on VM monitoring](https://www.zabbix.com/documentation/8.0/manual/vm_monitoring).

To use this template as manually linked to a host, attach it to the host and manually set the value of the `{$VMWARE.HV.UUID}` macro.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- VMware 6.0, 6.7, 7.0, 8.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

To use this template as manually linked to a host:
  1. Compile Zabbix server with the required options (`--with-libxml2` and `--with-libcurl`).
  2. Set the `StartVMwareCollectors` option in the Zabbix server configuration file to "1" or more.
  3. Create a new host.
  4. Set the host macros (on the host or template level) required for VMware authentication:
  ```text
  {$VMWARE.URL}
  {$VMWARE.USERNAME}
  {$VMWARE.PASSWORD}
  ```
  5. To get the hypervisor UUID, enable access to the hypervisor via SSH and log in via SSH using a valid login and password.
  6. Run the following command and specify the UUID in the macro `{$VMWARE.HV.UUID}`:
  ```text
  vim-cmd hostsvc/hostsummary | grep uuid
  ```
  7. Add the agent interface on the host with the address (IP or DNS) of the VMware hypervisor.
  8. Link the template to the host created earlier.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VMWARE.URL}|<p>VMware service (vCenter or ESX hypervisor) SDK URL (https://servername/sdk).</p>||
|{$VMWARE.USERNAME}|<p>VMware service user name.</p>||
|{$VMWARE.PASSWORD}|<p>VMware service `{$USERNAME}` user password.</p>||
|{$VMWARE.HV.UUID}|<p>UUID of hypervisor.</p>||
|{$VMWARE.HV.DATASTORE.SPACE.WARN}|<p>The warning threshold of the datastore free space.</p>|`20`|
|{$VMWARE.HV.DATASTORE.SPACE.CRIT}|<p>The critical threshold of the datastore free space.</p>|`10`|
|{$VMWARE.HV.SENSOR.DISCOVERY}|<p>Set "true"/"false" to enable or disable the monitoring of hardware sensors.</p>|`FALSE`|
|{$VMWARE.HV.SENSOR.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of hardware sensor names to be allowed in discovery.</p>|`.*`|
|{$VMWARE.HV.SENSOR.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of hardware sensor names to be ignored in discovery.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Connection state|<p>VMware hypervisor connection state. One of the following:</p><p>- Connected;</p><p>- Disconnected;</p><p>- Not responding.</p>|Simple check|vmware.hv.connectionstate[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Number of errors received|<p>VMware hypervisor network input statistics (errors).</p>|Simple check|vmware.hv.network.in[{$VMWARE.URL},{$VMWARE.HV.UUID},errors]|
|Number of broadcasts received|<p>VMware hypervisor network input statistics (broadcasts).</p>|Simple check|vmware.hv.network.in[{$VMWARE.URL},{$VMWARE.HV.UUID},broadcast]|
|Number of dropped received packets|<p>VMware hypervisor network input statistics (packets dropped).</p>|Simple check|vmware.hv.network.in[{$VMWARE.URL},{$VMWARE.HV.UUID},dropped]|
|Number of broadcasts transmitted|<p>VMware hypervisor network output statistics (broadcasts).</p>|Simple check|vmware.hv.network.out[{$VMWARE.URL},{$VMWARE.HV.UUID},broadcast]|
|Number of dropped transmitted packets|<p>VMware hypervisor network output statistics (packets dropped).</p>|Simple check|vmware.hv.network.out[{$VMWARE.URL},{$VMWARE.HV.UUID},dropped]|
|Number of errors transmitted|<p>VMware hypervisor network output statistics (errors).</p>|Simple check|vmware.hv.network.out[{$VMWARE.URL},{$VMWARE.HV.UUID},errors]|
|Hypervisor ping|<p>Checks if the hypervisor is running and accepting ICMP pings. One of the following:</p><p>- Down;</p><p>- Up.</p>|Simple check|icmpping[]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Cluster name|<p>Cluster name of the guest VM.</p>|Simple check|vmware.hv.cluster.name[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|CPU usage|<p>Aggregated CPU usage across all cores on the host in Hz. This is only available if the host is connected.</p>|Simple check|vmware.hv.cpu.usage[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|CPU usage in percent|<p>CPU usage as a percentage during the interval.</p>|Simple check|vmware.hv.cpu.usage.perf[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|CPU utilization|<p>CPU utilization as a percentage during the interval depends on power management or hyper-threading.</p>|Simple check|vmware.hv.cpu.utilization[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Power usage|<p>Current power usage.</p>|Simple check|vmware.hv.power[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Power usage maximum allowed|<p>Maximum allowed power usage.</p>|Simple check|vmware.hv.power[{$VMWARE.URL},{$VMWARE.HV.UUID},max]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Datacenter name|<p>Datacenter name of the hypervisor.</p>|Simple check|vmware.hv.datacenter.name[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Full name|<p>The complete product name, including the version information.</p>|Simple check|vmware.hv.fullname[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|CPU frequency|<p>The speed of the CPU cores. This is an average value if there are multiple speeds. The product of CPU frequency and the number of cores is approximately equal to the sum of the MHz for all the individual cores on the host.</p>|Simple check|vmware.hv.hw.cpu.freq[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|CPU model|<p>The CPU model.</p>|Simple check|vmware.hv.hw.cpu.model[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|CPU cores|<p>Number of physical CPU cores on the host. Physical CPU cores are the processors contained by a CPU package.</p>|Simple check|vmware.hv.hw.cpu.num[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|CPU threads|<p>Number of physical CPU threads on the host.</p>|Simple check|vmware.hv.hw.cpu.threads[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Total memory|<p>The physical memory size.</p>|Simple check|vmware.hv.hw.memory[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Model|<p>The system model identification.</p>|Simple check|vmware.hv.hw.model[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Bios UUID|<p>The hardware BIOS identification.</p>|Simple check|vmware.hv.hw.uuid[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Vendor|<p>The hardware vendor identification.</p>|Simple check|vmware.hv.hw.vendor[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Ballooned memory|<p>The amount of guest physical memory that is currently reclaimed through the balloon driver. Sum of all guest VMs.</p>|Simple check|vmware.hv.memory.size.ballooned[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Used memory|<p>Physical memory usage on the host.</p>|Simple check|vmware.hv.memory.used[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Number of bytes received|<p>VMware hypervisor network input statistics (bytes per second).</p>|Simple check|vmware.hv.network.in[{$VMWARE.URL},{$VMWARE.HV.UUID},bps]|
|Number of bytes transmitted|<p>VMware hypervisor network output statistics (bytes per second).</p>|Simple check|vmware.hv.network.out[{$VMWARE.URL},{$VMWARE.HV.UUID},bps]|
|Overall status|<p>The overall alarm status of the host. One of the following:</p><p>- Gray: Unknown;</p><p>- Green: OK;</p><p>- Yellow: It might have a problem;</p><p>- Red: It has a problem.</p>|Simple check|vmware.hv.status[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Uptime|<p>System uptime.</p>|Simple check|vmware.hv.uptime[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Version|<p>Dot-separated version string.</p>|Simple check|vmware.hv.version[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Number of guest VMs|<p>Number of guest virtual machines.</p>|Simple check|vmware.hv.vm.num[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|Get sensors|<p>Master item for sensor data.</p>|Simple check|vmware.hv.sensors.get[{$VMWARE.URL},{$VMWARE.HV.UUID}]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware Hypervisor: Hypervisor is down|<p>The service is unavailable or is not accepting ICMP pings.</p>|`last(/VMware Hypervisor/icmpping[])=0`|Average|**Manual close**: Yes|
|VMware Hypervisor: The {$VMWARE.HV.UUID} health is Red|<p>One or more components in the appliance might be in an unusable status and the appliance might soon become unresponsive. Security patches might be available.</p>|`last(/VMware Hypervisor/vmware.hv.status[{$VMWARE.URL},{$VMWARE.HV.UUID}])=3`|High||
|VMware Hypervisor: The {$VMWARE.HV.UUID} health is Yellow|<p>One or more components in the appliance might soon become overloaded.</p>|`last(/VMware Hypervisor/vmware.hv.status[{$VMWARE.URL},{$VMWARE.HV.UUID}])=2`|Average|**Depends on**:<br><ul><li>VMware Hypervisor: The {$VMWARE.HV.UUID} health is Red</li></ul>|
|VMware Hypervisor: Hypervisor has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/VMware Hypervisor/vmware.hv.uptime[{$VMWARE.URL},{$VMWARE.HV.UUID}])<10m`|Warning|**Manual close**: Yes|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of VMware hypervisor network interfaces.</p>|Simple check|vmware.hv.net.if.discovery[{$VMWARE.URL},{$VMWARE.HV.UUID}]|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#IFNAME}] network interface speed|<p>VMware hypervisor network interface speed.</p>|Simple check|vmware.hv.network.linkspeed[{$VMWARE.URL},{$VMWARE.HV.UUID},{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li></ul>|

### LLD rule Datastore discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Datastore discovery|<p>Discovery of VMware datastores.</p>|Simple check|vmware.hv.datastore.discovery[{$VMWARE.URL},{$VMWARE.HV.UUID}]|

### Item prototypes for Datastore discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Average read IOPS of the datastore [{#DATASTORE}]|<p>Average IOPS for a read operation from the datastore.</p>|Simple check|vmware.hv.datastore.read[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID},rps]|
|Average write IOPS of the datastore [{#DATASTORE}]|<p>Average IOPS for a write operation to the datastore (milliseconds).</p>|Simple check|vmware.hv.datastore.write[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID},rps]|
|Average read latency of the datastore [{#DATASTORE}]|<p>Average amount of time for a read operation from the datastore (milliseconds).</p>|Simple check|vmware.hv.datastore.read[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID},latency]|
|Free space on datastore [{#DATASTORE}] (percentage)|<p>VMware datastore free space (percentage from the total).</p>|Simple check|vmware.hv.datastore.size[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID},pfree]|
|Total size of datastore [{#DATASTORE}]|<p>VMware datastore space in bytes.</p>|Simple check|vmware.hv.datastore.size[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Average write latency of the datastore [{#DATASTORE}]|<p>Average amount of time for a write operation to the datastore (milliseconds).</p>|Simple check|vmware.hv.datastore.write[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID},latency]|
|Multipath count for datastore [{#DATASTORE}]|<p>Number of available datastore paths.</p>|Simple check|vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID}]|

### Trigger prototypes for Datastore discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware Hypervisor: [{#DATASTORE}]: Free space is critically low|<p>Datastore free space has fallen below the critical threshold.</p>|`last(/VMware Hypervisor/vmware.hv.datastore.size[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID},pfree])<{$VMWARE.HV.DATASTORE.SPACE.CRIT}`|High||
|VMware Hypervisor: [{#DATASTORE}]: Free space is low|<p>Datastore free space has fallen below the warning threshold.</p>|`last(/VMware Hypervisor/vmware.hv.datastore.size[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID},pfree])<{$VMWARE.HV.DATASTORE.SPACE.WARN}`|Warning|**Depends on**:<br><ul><li>VMware Hypervisor: [{#DATASTORE}]: Free space is critically low</li></ul>|
|VMware Hypervisor: The multipath count has been changed|<p>The number of available datastore paths is less than registered (`{#MULTIPATH.COUNT}`).</p>|`last(/VMware Hypervisor/vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID}],#1)<>last(/VMware Hypervisor/vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID}],#2) and last(/VMware Hypervisor/vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE.UUID}])<{#MULTIPATH.COUNT}`|Average|**Manual close**: Yes|

### LLD rule Serial number discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Serial number discovery|<p>VMware hypervisor serial number discovery. This item works only with VMware hypervisor versions above 6.7.</p>|Dependent item|vmware.hv.serial.number.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Serial number discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Serial number|<p>VMware hypervisor serial number.</p>|Simple check|vmware.hv.hw.serialnumber[{$VMWARE.URL},{#VMWARE.HV.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### LLD rule Healthcheck discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Healthcheck discovery|<p>VMware Rollup Health State sensor discovery.</p>|Dependent item|vmware.hv.healthcheck.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Healthcheck discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Health state rollup|<p>The host's Rollup Health State sensor value. One of the following:</p><p>- Gray: Unknown;</p><p>- Green: OK;</p><p>- Yellow: It might have a problem;</p><p>- Red: It has a problem.</p>|Dependent item|vmware.hv.sensor.health.state[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Healthcheck discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware Hypervisor: The {$VMWARE.HV.UUID} health is Red|<p>One or more components in the appliance might be in an unusable status and the appliance might soon become unresponsive. Security patches might be available.</p>|`last(/VMware Hypervisor/vmware.hv.sensor.health.state[{#SINGLETON}])=3`|High|**Depends on**:<br><ul><li>VMware Hypervisor: The {$VMWARE.HV.UUID} health is Red</li></ul>|
|VMware Hypervisor: The {$VMWARE.HV.UUID} health is Yellow|<p>One or more components in the appliance might soon become overloaded.</p>|`last(/VMware Hypervisor/vmware.hv.sensor.health.state[{#SINGLETON}])=2`|Average|**Depends on**:<br><ul><li>VMware Hypervisor: The {$VMWARE.HV.UUID} health is Red</li><li>VMware Hypervisor: The {$VMWARE.HV.UUID} health is Yellow</li><li>VMware Hypervisor: The {$VMWARE.HV.UUID} health is Red</li></ul>|

### LLD rule Sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor discovery|<p>VMware hardware sensor discovery. The data is retrieved from numeric sensor probes and provides information about the health of the physical system.</p>|Dependent item|vmware.hv.sensors.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#NAME}] health state|<p>VMware hardware sensor health state. One of the following:</p><p>- Gray: Unknown;</p><p>- Green: OK;</p><p>- Yellow: It might have a problem;</p><p>- Red: It has a problem.</p>|Dependent item|vmware.hv.sensor.state["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Sensor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware Hypervisor: Sensor [{#NAME}] health state is Red|<p>One or more components in the appliance might be in an unusable status and the appliance might soon become unresponsive.</p>|`last(/VMware Hypervisor/vmware.hv.sensor.state["{#NAME}"])=3`|High|**Depends on**:<br><ul><li>VMware Hypervisor: The {$VMWARE.HV.UUID} health is Red</li></ul>|
|VMware Hypervisor: Sensor [{#NAME}] health state is Yellow|<p>One or more components in the appliance might soon become overloaded.</p>|`last(/VMware Hypervisor/vmware.hv.sensor.state["{#NAME}"])=2`|Average|**Depends on**:<br><ul><li>VMware Hypervisor: The {$VMWARE.HV.UUID} health is Red</li><li>VMware Hypervisor: The {$VMWARE.HV.UUID} health is Yellow</li><li>VMware Hypervisor: Sensor [{#NAME}] health state is Red</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

