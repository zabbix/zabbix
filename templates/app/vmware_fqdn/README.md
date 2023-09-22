
# VMware FQDN

## Overview

This template is designed for the effortless deployment of both VMware vCenter and ESX hypervisor monitoring and doesn't require any external scripts.

The "VMware Hypervisor" and "VMware Guest" templates are used by discovery and normally should not be manually linked to a host.
For additional information please check https://www.zabbix.com/documentation/7.0/manual/vm_monitoring

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- VMWare 6.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Compile zabbix server with required options (--with-libxml2 and --with-libcurl)
2. Set the StartVMwareCollectors option in Zabbix server configuration file to 1 or more
3. Create a new host
4. Set the host macros (on host or template level) required for VMware authentication:
```text
{$VMWARE.URL}
{$VMWARE.USERNAME}
{$VMWARE.PASSWORD}
```
5. Link the template to host created early

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VMWARE.URL}|<p>VMware service (vCenter or ESX hypervisor) SDK URL (https://servername/sdk)</p>||
|{$VMWARE.USERNAME}|<p>VMware service user name</p>||
|{$VMWARE.PASSWORD}|<p>VMware service {$USERNAME} user password</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware: Event log|<p>Collect VMware event log. See also: https://www.zabbix.com/documentation/7.0/manual/config/items/preprocessing/examples#filtering_vmware_event_log_records</p>|Simple check|vmware.eventlog[{$VMWARE.URL},skip]|
|VMware: Full name|<p>VMware service full name.</p>|Simple check|vmware.fullname[{$VMWARE.URL}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: Version|<p>VMware service version.</p>|Simple check|vmware.version[{$VMWARE.URL}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### LLD rule Discover VMware clusters

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Discover VMware clusters|<p>Discovery of clusters</p>|Simple check|vmware.cluster.discovery[{$VMWARE.URL}]|

### Item prototypes for Discover VMware clusters

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware: Status of "{#CLUSTER.NAME}" cluster|<p>VMware cluster status.</p>|Simple check|vmware.cluster.status[{$VMWARE.URL},{#CLUSTER.NAME}]|

### LLD rule Discover VMware datastores

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Discover VMware datastores||Simple check|vmware.datastore.discovery[{$VMWARE.URL}]|

### Item prototypes for Discover VMware datastores

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware: Average read latency of the datastore {#DATASTORE}|<p>Amount of time for a read operation from the datastore (milliseconds).</p>|Simple check|vmware.datastore.read[{$VMWARE.URL},{#DATASTORE},latency]|
|VMware: Free space on datastore {#DATASTORE} (percentage)|<p>VMware datastore space in percentage from total.</p>|Simple check|vmware.datastore.size[{$VMWARE.URL},{#DATASTORE},pfree]|
|VMware: Total size of datastore {#DATASTORE}|<p>VMware datastore space in bytes.</p>|Simple check|vmware.datastore.size[{$VMWARE.URL},{#DATASTORE}]|
|VMware: Average write latency of the datastore {#DATASTORE}|<p>Amount of time for a write operation to the datastore (milliseconds).</p>|Simple check|vmware.datastore.write[{$VMWARE.URL},{#DATASTORE},latency]|

### LLD rule Discover VMware hypervisors

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Discover VMware hypervisors|<p>Discovery of hypervisors.</p>|Simple check|vmware.hv.discovery[{$VMWARE.URL}]|

### LLD rule Discover VMware VMs FQDN

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Discover VMware VMs FQDN|<p>Discovery of guest virtual machines.</p>|Simple check|vmware.vm.discovery[{$VMWARE.URL}]|

# VMware Guest

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VMWARE.URL}|<p>VMware service (vCenter or ESX hypervisor) SDK URL (https://servername/sdk)</p>||
|{$VMWARE.USERNAME}|<p>VMware service user name</p>||
|{$VMWARE.PASSWORD}|<p>VMware service {$USERNAME} user password</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware: Cluster name|<p>Cluster name of the guest VM.</p>|Simple check|vmware.vm.cluster.name[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: Number of virtual CPUs|<p>Number of virtual CPUs assigned to the guest.</p>|Simple check|vmware.vm.cpu.num[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: CPU ready|<p>Time that the virtual machine was ready, but could not get scheduled to run on the physical CPU during last measurement interval (VMware vCenter/ESXi Server performance counter sampling interval - 20 seconds)</p>|Simple check|vmware.vm.cpu.ready[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: CPU usage|<p>Current upper-bound on CPU usage. The upper-bound is based on the host the virtual machine is current running on, as well as limits configured on the virtual machine itself or any parent resource pool. Valid while the virtual machine is running.</p>|Simple check|vmware.vm.cpu.usage[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Datacenter name|<p>Datacenter name of the guest VM.</p>|Simple check|vmware.vm.datacenter.name[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: Hypervisor name|<p>Hypervisor name of the guest VM.</p>|Simple check|vmware.vm.hv.name[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: Ballooned memory|<p>The amount of guest physical memory that is currently reclaimed through the balloon driver.</p>|Simple check|vmware.vm.memory.size.ballooned[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Compressed memory|<p>The amount of memory currently in the compression cache for this VM.</p>|Simple check|vmware.vm.memory.size.compressed[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Private memory|<p>Amount of memory backed by host memory and not being shared.</p>|Simple check|vmware.vm.memory.size.private[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Shared memory|<p>The amount of guest physical memory shared through transparent page sharing.</p>|Simple check|vmware.vm.memory.size.shared[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Swapped memory|<p>The amount of guest physical memory swapped out to the VM's swap device by ESX.</p>|Simple check|vmware.vm.memory.size.swapped[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Guest memory usage|<p>The amount of guest physical memory that is being used by the VM.</p>|Simple check|vmware.vm.memory.size.usage.guest[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Host memory usage|<p>The amount of host physical memory allocated to the VM, accounting for saving from memory sharing with other VMs.</p>|Simple check|vmware.vm.memory.size.usage.host[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Memory size|<p>Total size of configured memory.</p>|Simple check|vmware.vm.memory.size[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: Power state|<p>The current power state of the virtual machine.</p>|Simple check|vmware.vm.powerstate[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VMware: Committed storage space|<p>Total storage space, in bytes, committed to this virtual machine across all datastores.</p>|Simple check|vmware.vm.storage.committed[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Uncommitted storage space|<p>Additional storage space, in bytes, potentially used by this virtual machine on all datastores.</p>|Simple check|vmware.vm.storage.uncommitted[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Unshared storage space|<p>Total storage space, in bytes, occupied by the virtual machine across all datastores, that is not shared with any other virtual machine.</p>|Simple check|vmware.vm.storage.unshared[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Uptime|<p>System uptime.</p>|Simple check|vmware.vm.uptime[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Guest memory swapped|<p>Amount of guest physical memory that is swapped out to the swap space.</p>|Simple check|vmware.vm.guest.memory.size.swapped[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Host memory consumed|<p>Amount of host physical memory consumed for backing up guest physical memory pages.</p>|Simple check|vmware.vm.memory.size.consumed[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Host memory usage in percents|<p>Percentage of host physical memory that has been consumed.</p>|Simple check|vmware.vm.memory.usage[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: CPU usage in percents|<p>CPU usage as a percentage during the interval.</p>|Simple check|vmware.vm.cpu.usage.perf[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: CPU latency in percents|<p>Percentage of time the virtual machine is unable to run because it is contending for access to the physical CPU(s).</p>|Simple check|vmware.vm.cpu.latency[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: CPU readiness latency in percents|<p>Percentage of time that the virtual machine was ready, but could not get scheduled to run on the physical CPU.</p>|Simple check|vmware.vm.cpu.readiness[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: CPU swap-in latency in percents|<p>Percentage of CPU time spent waiting for swap-in.</p>|Simple check|vmware.vm.cpu.swapwait[{$VMWARE.URL},{$VMWARE.VM.UUID}]|
|VMware: Uptime of guest OS|<p>Total time elapsed since the last operating system boot-up (in seconds).</p>|Simple check|vmware.vm.guest.osuptime[{$VMWARE.URL},{$VMWARE.VM.UUID}]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware: VM has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/VMware Guest/vmware.vm.guest.osuptime[{$VMWARE.URL},{$VMWARE.VM.UUID}])<10m`|Warning|**Manual close**: Yes|

### LLD rule Network device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network device discovery|<p>Discovery of all network devices.</p>|Simple check|vmware.vm.net.if.discovery[{$VMWARE.URL},{$VMWARE.VM.UUID}]|

### Item prototypes for Network device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware: Number of bytes received on interface {#IFDESC}|<p>VMware virtual machine network interface input statistics (bytes per second).</p>|Simple check|vmware.vm.net.if.in[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},bps]|
|VMware: Number of packets received on interface {#IFDESC}|<p>VMware virtual machine network interface input statistics (packets per second).</p>|Simple check|vmware.vm.net.if.in[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},pps]|
|VMware: Number of bytes transmitted on interface {#IFDESC}|<p>VMware virtual machine network interface output statistics (bytes per second).</p>|Simple check|vmware.vm.net.if.out[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},bps]|
|VMware: Number of packets transmitted on interface {#IFDESC}|<p>VMware virtual machine network interface output statistics (packets per second).</p>|Simple check|vmware.vm.net.if.out[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},pps]|
|VMware: Network utilization on interface {#IFDESC}|<p>VMware virtual machine network utilization (combined transmit-rates and receive-rates) during the interval.</p>|Simple check|vmware.vm.net.if.usage[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|

### LLD rule Disk device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk device discovery|<p>Discovery of all disk devices.</p>|Simple check|vmware.vm.vfs.dev.discovery[{$VMWARE.URL},{$VMWARE.VM.UUID}]|

### Item prototypes for Disk device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware: Average number of bytes read from the disk {#DISKDESC}|<p>VMware virtual machine disk device read statistics (bytes per second).</p>|Simple check|vmware.vm.vfs.dev.read[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},bps]|
|VMware: Average number of reads from the disk {#DISKDESC}|<p>VMware virtual machine disk device read statistics (operations per second).</p>|Simple check|vmware.vm.vfs.dev.read[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},ops]|
|VMware: Average number of bytes written to the disk {#DISKDESC}|<p>VMware virtual machine disk device write statistics (bytes per second).</p>|Simple check|vmware.vm.vfs.dev.write[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},bps]|
|VMware: Average number of writes to the disk {#DISKDESC}|<p>VMware virtual machine disk device write statistics (operations per second).</p>|Simple check|vmware.vm.vfs.dev.write[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},ops]|
|VMware: Average number of outstanding read requests to the disk {#DISKDESC}|<p>Average number of outstanding read requests to the virtual disk during the collection interval.</p>|Simple check|vmware.vm.storage.readoio[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}]|
|VMware: Average number of outstanding write requests to the disk {#DISKDESC}|<p>Average number of outstanding write requests to the virtual disk during the collection interval.</p>|Simple check|vmware.vm.storage.writeoio[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}]|
|VMware: Average write latency to the disk {#DISKDESC}|<p>The average time a write to the virtual disk takes.</p>|Simple check|vmware.vm.storage.totalwritelatency[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}]|
|VMware: Average read latency to the disk {#DISKDESC}|<p>The average time a read from the virtual disk takes.</p>|Simple check|vmware.vm.storage.totalreadlatency[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}]|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>Discovery of all guest file systems.</p>|Simple check|vmware.vm.vfs.fs.discovery[{$VMWARE.URL},{$VMWARE.VM.UUID}]|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware: Free disk space on {#FSNAME}|<p>VMware virtual machine file system statistics (bytes).</p>|Simple check|vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},free]|
|VMware: Free disk space on {#FSNAME} (percentage)|<p>VMware virtual machine file system statistics (percentages).</p>|Simple check|vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},pfree]|
|VMware: Total disk space on {#FSNAME}|<p>VMware virtual machine total disk space (bytes).</p>|Simple check|vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},total]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: Used disk space on {#FSNAME}|<p>VMware virtual machine used disk space (bytes).</p>|Simple check|vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},used]|

# VMware Hypervisor

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VMWARE.URL}|<p>VMware service (vCenter or ESX hypervisor) SDK URL (https://servername/sdk)</p>||
|{$VMWARE.USERNAME}|<p>VMware service user name</p>||
|{$VMWARE.PASSWORD}|<p>VMware service {$USERNAME} user password</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware: Hypervisor ping|<p>Checks if the hypervisor is running and accepting ICMP pings.</p>|Simple check|icmpping[]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|VMware: Cluster name|<p>Cluster name of the guest VM.</p>|Simple check|vmware.hv.cluster.name[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: CPU usage|<p>Aggregated CPU usage across all cores on the host in Hz. This is only available if the host is connected.</p>|Simple check|vmware.hv.cpu.usage[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: CPU usage in percents|<p>CPU usage as a percentage during the interval.</p>|Simple check|vmware.hv.cpu.usage.perf[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: CPU utilization|<p>CPU usage as a percentage during the interval depends on power management or HT.</p>|Simple check|vmware.hv.cpu.utilization[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Power usage|<p>Current power usage.</p>|Simple check|vmware.hv.power[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Power usage maximum allowed|<p>Maximum allowed power usage.</p>|Simple check|vmware.hv.power[{$VMWARE.URL},{$VMWARE.HV.UUID},max]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|VMware: Datacenter name|<p>Datacenter name of the hypervisor.</p>|Simple check|vmware.hv.datacenter.name[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: Full name|<p>The complete product name, including the version information.</p>|Simple check|vmware.hv.fullname[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: CPU frequency|<p>The speed of the CPU cores. This is an average value if there are multiple speeds. The product of CPU frequency and number of cores is approximately equal to the sum of the MHz for all the individual cores on the host.</p>|Simple check|vmware.hv.hw.cpu.freq[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: CPU model|<p>The CPU model.</p>|Simple check|vmware.hv.hw.cpu.model[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: CPU cores|<p>Number of physical CPU cores on the host. Physical CPU cores are the processors contained by a CPU package.</p>|Simple check|vmware.hv.hw.cpu.num[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|VMware: CPU threads|<p>Number of physical CPU threads on the host.</p>|Simple check|vmware.hv.hw.cpu.threads[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Total memory|<p>The physical memory size.</p>|Simple check|vmware.hv.hw.memory[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Model|<p>The system model identification.</p>|Simple check|vmware.hv.hw.model[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Bios UUID|<p>The hardware BIOS identification.</p>|Simple check|vmware.hv.hw.uuid[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Vendor|<p>The hardware vendor identification.</p>|Simple check|vmware.hv.hw.vendor[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Ballooned memory|<p>The amount of guest physical memory that is currently reclaimed through the balloon driver. Sum of all guest VMs.</p>|Simple check|vmware.hv.memory.size.ballooned[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Used memory|<p>Physical memory usage on the host.</p>|Simple check|vmware.hv.memory.used[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Number of bytes received|<p>VMware hypervisor network input statistics (bytes per second).</p>|Simple check|vmware.hv.network.in[{$VMWARE.URL},{$VMWARE.HV.UUID},bps]|
|VMware: Number of bytes transmitted|<p>VMware hypervisor network output statistics (bytes per second).</p>|Simple check|vmware.hv.network.out[{$VMWARE.URL},{$VMWARE.HV.UUID},bps]|
|VMware: Overall status|<p>The overall alarm status of the host: gray - unknown, green - ok, red - it has a problem, yellow - it might have a problem.</p>|Simple check|vmware.hv.status[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Uptime|<p>System uptime.</p>|Simple check|vmware.hv.uptime[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Version|<p>Dot-separated version string.</p>|Simple check|vmware.hv.version[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Number of guest VMs|<p>Number of guest virtual machines.</p>|Simple check|vmware.hv.vm.num[{$VMWARE.URL},{$VMWARE.HV.UUID}]|
|VMware: Get sensors|<p>Master item for sensors data.</p>|Simple check|vmware.hv.sensors.get[{$VMWARE.URL},{$VMWARE.HV.UUID}]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware: Hypervisor is down|<p>The service is unavailable or does not accept ICMP ping.</p>|`last(/VMware Hypervisor/icmpping[])=0`|Average|**Manual close**: Yes|
|VMware: The {$VMWARE.HV.UUID} health is Red|<p>One or more components in the appliance might be in an unusable status and the appliance might become unresponsive soon. Security patches might be available.</p>|`last(/VMware Hypervisor/vmware.hv.status[{$VMWARE.URL},{$VMWARE.HV.UUID}])=3`|High||
|VMware: The {$VMWARE.HV.UUID} health is Yellow|<p>One or more components in the appliance might become overloaded soon.</p>|`last(/VMware Hypervisor/vmware.hv.status[{$VMWARE.URL},{$VMWARE.HV.UUID}])=2`|Average|**Depends on**:<br><ul><li>VMware: The {$VMWARE.HV.UUID} health is Red</li></ul>|
|VMware: Hypervisor has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/VMware Hypervisor/vmware.hv.uptime[{$VMWARE.URL},{$VMWARE.HV.UUID}])<10m`|Warning|**Manual close**: Yes|

### LLD rule Datastore discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Datastore discovery||Simple check|vmware.hv.datastore.discovery[{$VMWARE.URL},{$VMWARE.HV.UUID}]|

### Item prototypes for Datastore discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware: Average read latency of the datastore {#DATASTORE}|<p>Average amount of time for a read operation from the datastore (milliseconds).</p>|Simple check|vmware.hv.datastore.read[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE},latency]|
|VMware: Free space on datastore {#DATASTORE} (percentage)|<p>VMware datastore space in percentage from total.</p>|Simple check|vmware.hv.datastore.size[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE},pfree]|
|VMware: Total size of datastore {#DATASTORE}|<p>VMware datastore space in bytes.</p>|Simple check|vmware.hv.datastore.size[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE}]|
|VMware: Average write latency of the datastore {#DATASTORE}|<p>Average amount of time for a write operation to the datastore (milliseconds).</p>|Simple check|vmware.hv.datastore.write[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE},latency]|
|VMware: Multipath count for datastore {#DATASTORE}|<p>Number of available datastore paths.</p>|Simple check|vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE}]|

### Trigger prototypes for Datastore discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware: The multipath count has been changed|<p>The number of available datastore paths less than registered ({#MULTIPATH.COUNT}).</p>|`last(/VMware Hypervisor/vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE}],#1)<>last(/VMware Hypervisor/vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE}],#2) and last(/VMware Hypervisor/vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE}])<{#MULTIPATH.COUNT}`|Average|**Manual close**: Yes|

### LLD rule Healthcheck discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Healthcheck discovery|<p>VMware Rollup Health State sensor discovery</p>|Dependent item|vmware.hv.healthcheck.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Healthcheck discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VMware: Health state rollup|<p>The host health state rollup sensor value: gray - unknown, green - ok, red - it has a problem, yellow - it might have a problem.</p>|Dependent item|vmware.hv.sensor.health.state[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Healthcheck discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|VMware: The {$VMWARE.HV.UUID} health is Red|<p>One or more components in the appliance might be in an unusable status and the appliance might become unresponsive soon. Security patches might be available.</p>|`last(/VMware Hypervisor/vmware.hv.sensor.health.state[{#SINGLETON}])="Red"`|High|**Depends on**:<br><ul><li>VMware: The {$VMWARE.HV.UUID} health is Red</li></ul>|
|VMware: The {$VMWARE.HV.UUID} health is Yellow|<p>One or more components in the appliance might become overloaded soon.</p>|`last(/VMware Hypervisor/vmware.hv.sensor.health.state[{#SINGLETON}])="Yellow"`|Average|**Depends on**:<br><ul><li>VMware: The {$VMWARE.HV.UUID} health is Red</li><li>VMware: The {$VMWARE.HV.UUID} health is Yellow</li><li>VMware: The {$VMWARE.HV.UUID} health is Red</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

