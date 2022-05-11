
# VMware FQDN

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor VMware vCenter and ESX hypervisor.
The "VMware Hypervisor" and "VMware Guest" templates are used by discovery and normally should not be manually linked to a host.
For additional information please check https://www.zabbix.com/documentation/6.0/manual/vm_monitoring


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


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VMWARE.PASSWORD} |<p>VMware service {$USERNAME} user password</p> |`` |
|{$VMWARE.URL} |<p>VMware service (vCenter or ESX hypervisor) SDK URL (https://servername/sdk)</p> |`` |
|{$VMWARE.USERNAME} |<p>VMware service user name</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Discover VMware clusters |<p>Discovery of clusters</p> |SIMPLE |vmware.cluster.discovery[{$VMWARE.URL}] |
|Discover VMware datastores |<p>-</p> |SIMPLE |vmware.datastore.discovery[{$VMWARE.URL}] |
|Discover VMware hypervisors |<p>Discovery of hypervisors.</p> |SIMPLE |vmware.hv.discovery[{$VMWARE.URL}] |
|Discover VMware VMs FQDN |<p>Discovery of guest virtual machines.</p> |SIMPLE |vmware.vm.discovery[{$VMWARE.URL}]<p>**Filter**:</p>AND <p>- {#VM.DNS} NOT_MATCHES_REGEX `^$`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|VMware |VMware: Event log |<p>Collect VMware event log. See also: https://www.zabbix.com/documentation/6.0/manual/config/items/preprocessing/examples#filtering_vmware_event_log_records</p> |SIMPLE |vmware.eventlog[{$VMWARE.URL},skip] |
|VMware |VMware: Full name |<p>VMware service full name.</p> |SIMPLE |vmware.fullname[{$VMWARE.URL}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Version |<p>VMware service version.</p> |SIMPLE |vmware.version[{$VMWARE.URL}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Status of "{#CLUSTER.NAME}" cluster |<p>VMware cluster status.</p> |SIMPLE |vmware.cluster.status[{$VMWARE.URL},{#CLUSTER.NAME}] |
|VMware |VMware: Average read latency of the datastore {#DATASTORE} |<p>Amount of time for a read operation from the datastore (milliseconds).</p> |SIMPLE |vmware.datastore.read[{$VMWARE.URL},{#DATASTORE},latency] |
|VMware |VMware: Free space on datastore {#DATASTORE} (percentage) |<p>VMware datastore space in percentage from total.</p> |SIMPLE |vmware.datastore.size[{$VMWARE.URL},{#DATASTORE},pfree] |
|VMware |VMware: Total size of datastore {#DATASTORE} |<p>VMware datastore space in bytes.</p> |SIMPLE |vmware.datastore.size[{$VMWARE.URL},{#DATASTORE}] |
|VMware |VMware: Average write latency of the datastore {#DATASTORE} |<p>Amount of time for a write operation to the datastore (milliseconds).</p> |SIMPLE |vmware.datastore.write[{$VMWARE.URL},{#DATASTORE},latency] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# VMware Guest

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VMWARE.PASSWORD} |<p>VMware service {$USERNAME} user password</p> |`` |
|{$VMWARE.URL} |<p>VMware service (vCenter or ESX hypervisor) SDK URL (https://servername/sdk)</p> |`` |
|{$VMWARE.USERNAME} |<p>VMware service user name</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Disk device discovery |<p>Discovery of all disk devices.</p> |SIMPLE |vmware.vm.vfs.dev.discovery[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|Mounted filesystem discovery |<p>Discovery of all guest file systems.</p> |SIMPLE |vmware.vm.vfs.fs.discovery[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|Network device discovery |<p>Discovery of all network devices.</p> |SIMPLE |vmware.vm.net.if.discovery[{$VMWARE.URL},{$VMWARE.VM.UUID}] |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|VMware |VMware: Cluster name |<p>Cluster name of the guest VM.</p> |SIMPLE |vmware.vm.cluster.name[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Number of virtual CPUs |<p>Number of virtual CPUs assigned to the guest.</p> |SIMPLE |vmware.vm.cpu.num[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: CPU ready |<p>Time that the virtual machine was ready, but could not get scheduled to run on the physical CPU during last measurement interval (VMware vCenter/ESXi Server performance counter sampling interval - 20 seconds)</p> |SIMPLE |vmware.vm.cpu.ready[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: CPU usage |<p>Current upper-bound on CPU usage. The upper-bound is based on the host the virtual machine is current running on, as well as limits configured on the virtual machine itself or any parent resource pool. Valid while the virtual machine is running.</p> |SIMPLE |vmware.vm.cpu.usage[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Datacenter name |<p>Datacenter name of the guest VM.</p> |SIMPLE |vmware.vm.datacenter.name[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Hypervisor name |<p>Hypervisor name of the guest VM.</p> |SIMPLE |vmware.vm.hv.name[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Ballooned memory |<p>The amount of guest physical memory that is currently reclaimed through the balloon driver.</p> |SIMPLE |vmware.vm.memory.size.ballooned[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Compressed memory |<p>The amount of memory currently in the compression cache for this VM.</p> |SIMPLE |vmware.vm.memory.size.compressed[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Private memory |<p>Amount of memory backed by host memory and not being shared.</p> |SIMPLE |vmware.vm.memory.size.private[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Shared memory |<p>The amount of guest physical memory shared through transparent page sharing.</p> |SIMPLE |vmware.vm.memory.size.shared[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Swapped memory |<p>The amount of guest physical memory swapped out to the VM's swap device by ESX.</p> |SIMPLE |vmware.vm.memory.size.swapped[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Guest memory usage |<p>The amount of guest physical memory that is being used by the VM.</p> |SIMPLE |vmware.vm.memory.size.usage.guest[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Host memory usage |<p>The amount of host physical memory allocated to the VM, accounting for saving from memory sharing with other VMs.</p> |SIMPLE |vmware.vm.memory.size.usage.host[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Memory size |<p>Total size of configured memory.</p> |SIMPLE |vmware.vm.memory.size[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Power state |<p>The current power state of the virtual machine.</p> |SIMPLE |vmware.vm.powerstate[{$VMWARE.URL},{$VMWARE.VM.UUID}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|VMware |VMware: Committed storage space |<p>Total storage space, in bytes, committed to this virtual machine across all datastores.</p> |SIMPLE |vmware.vm.storage.committed[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Uncommitted storage space |<p>Additional storage space, in bytes, potentially used by this virtual machine on all datastores.</p> |SIMPLE |vmware.vm.storage.uncommitted[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Unshared storage space |<p>Total storage space, in bytes, occupied by the virtual machine across all datastores, that is not shared with any other virtual machine.</p> |SIMPLE |vmware.vm.storage.unshared[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Uptime |<p>System uptime.</p> |SIMPLE |vmware.vm.uptime[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Guest memory swapped |<p>Amount of guest physical memory that is swapped out to the swap space.</p> |SIMPLE |vmware.vm.guest.memory.size.swapped[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Host memory consumed |<p>Amount of host physical memory consumed for backing up guest physical memory pages.</p> |SIMPLE |vmware.vm.memory.size.consumed[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Host memory usage in percents |<p>Percentage of host physical memory that has been consumed.</p> |SIMPLE |vmware.vm.memory.usage[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: CPU usage in percents |<p>CPU usage as a percentage during the interval.</p> |SIMPLE |vmware.vm.cpu.usage.perf[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: CPU latency in percents |<p>Percentage of time the virtual machine is unable to run because it is contending for access to the physical CPU(s).</p> |SIMPLE |vmware.vm.cpu.latency[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: CPU readiness latency in percents |<p>Percentage of time that the virtual machine was ready, but could not get scheduled to run on the physical CPU.</p> |SIMPLE |vmware.vm.cpu.readiness[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: CPU swap-in latency in percents |<p>Percentage of CPU time spent waiting for swap-in.</p> |SIMPLE |vmware.vm.cpu.swapwait[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Uptime of guest OS |<p>Total time elapsed since the last operating system boot-up (in seconds).</p> |SIMPLE |vmware.vm.guest.osuptime[{$VMWARE.URL},{$VMWARE.VM.UUID}] |
|VMware |VMware: Number of bytes received on interface {#IFDESC} |<p>VMware virtual machine network interface input statistics (bytes per second).</p> |SIMPLE |vmware.vm.net.if.in[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},bps] |
|VMware |VMware: Number of packets received on interface {#IFDESC} |<p>VMware virtual machine network interface input statistics (packets per second).</p> |SIMPLE |vmware.vm.net.if.in[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},pps] |
|VMware |VMware: Number of bytes transmitted on interface {#IFDESC} |<p>VMware virtual machine network interface output statistics (bytes per second).</p> |SIMPLE |vmware.vm.net.if.out[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},bps] |
|VMware |VMware: Number of packets transmitted on interface {#IFDESC} |<p>VMware virtual machine network interface output statistics (packets per second).</p> |SIMPLE |vmware.vm.net.if.out[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME},pps] |
|VMware |VMware: Network utilization on interface {#IFDESC} |<p>VMware virtual machine network utilization (combined transmit-rates and receive-rates) during the interval.</p> |SIMPLE |vmware.vm.net.if.usage[{$VMWARE.URL},{$VMWARE.VM.UUID},{#IFNAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|VMware |VMware: Average number of bytes read from the disk {#DISKDESC} |<p>VMware virtual machine disk device read statistics (bytes per second).</p> |SIMPLE |vmware.vm.vfs.dev.read[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},bps] |
|VMware |VMware: Average number of reads from the disk {#DISKDESC} |<p>VMware virtual machine disk device read statistics (operations per second).</p> |SIMPLE |vmware.vm.vfs.dev.read[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},ops] |
|VMware |VMware: Average number of bytes written to the disk {#DISKDESC} |<p>VMware virtual machine disk device write statistics (bytes per second).</p> |SIMPLE |vmware.vm.vfs.dev.write[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},bps] |
|VMware |VMware: Average number of writes to the disk {#DISKDESC} |<p>VMware virtual machine disk device write statistics (operations per second).</p> |SIMPLE |vmware.vm.vfs.dev.write[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME},ops] |
|VMware |VMware: Average number of outstanding read requests to the disk {#DISKDESC} |<p>Average number of outstanding read requests to the virtual disk during the collection interval.</p> |SIMPLE |vmware.vm.storage.readoio[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}] |
|VMware |VMware: Average number of outstanding write requests to the disk {#DISKDESC} |<p>Average number of outstanding write requests to the virtual disk during the collection interval.</p> |SIMPLE |vmware.vm.storage.writeoio[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}] |
|VMware |VMware: Average write latency to the disk {#DISKDESC} |<p>The average time a write to the virtual disk takes.</p> |SIMPLE |vmware.vm.storage.totalwritelatency[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}] |
|VMware |VMware: Average read latency to the disk {#DISKDESC} |<p>The average time a read from the virtual disk takes.</p> |SIMPLE |vmware.vm.storage.totalreadlatency[{$VMWARE.URL},{$VMWARE.VM.UUID},{#DISKNAME}] |
|VMware |VMware: Free disk space on {#FSNAME} |<p>VMware virtual machine file system statistics (bytes).</p> |SIMPLE |vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},free] |
|VMware |VMware: Free disk space on {#FSNAME} (percentage) |<p>VMware virtual machine file system statistics (percentages).</p> |SIMPLE |vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},pfree] |
|VMware |VMware: Total disk space on {#FSNAME} |<p>VMware virtual machine total disk space (bytes).</p> |SIMPLE |vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},total]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Used disk space on {#FSNAME} |<p>VMware virtual machine used disk space (bytes).</p> |SIMPLE |vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},{#FSNAME},used] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|VMware: VM has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/VMware Guest/vmware.vm.guest.osuptime[{$VMWARE.URL},{$VMWARE.VM.UUID}])<10m` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# VMware Hypervisor

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VMWARE.PASSWORD} |<p>VMware service {$USERNAME} user password</p> |`` |
|{$VMWARE.URL} |<p>VMware service (vCenter or ESX hypervisor) SDK URL (https://servername/sdk)</p> |`` |
|{$VMWARE.USERNAME} |<p>VMware service user name</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Datastore discovery |<p>-</p> |SIMPLE |vmware.hv.datastore.discovery[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|Healthcheck discovery |<p>VMware Rollup Health State sensor discovery</p> |DEPENDENT |vmware.hv.healthcheck.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$..HostNumericSensorInfo[?(@.name=="VMware Rollup Health State")]`</p><p>- JAVASCRIPT: `return JSON.stringify(value != "[]" ? [{'{#SINGLETON}': ''}] : []);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|VMware |VMware: Hypervisor ping |<p>Checks if the hypervisor is running and accepting ICMP pings.</p> |SIMPLE |icmpping[]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|VMware |VMware: Cluster name |<p>Cluster name of the guest VM.</p> |SIMPLE |vmware.hv.cluster.name[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: CPU usage |<p>Aggregated CPU usage across all cores on the host in Hz. This is only available if the host is connected.</p> |SIMPLE |vmware.hv.cpu.usage[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: CPU usage in percents |<p>CPU usage as a percentage during the interval.</p> |SIMPLE |vmware.hv.cpu.usage.perf[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: CPU utilization |<p>CPU usage as a percentage during the interval depends on power management or HT.</p> |SIMPLE |vmware.hv.cpu.utilization[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Power usage |<p>Current power usage.</p> |SIMPLE |vmware.hv.power[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Power usage maximum allowed |<p>Maximum allowed power usage.</p> |SIMPLE |vmware.hv.power[{$VMWARE.URL},{$VMWARE.HV.UUID},max]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|VMware |VMware: Datacenter name |<p>Datacenter name of the hypervisor.</p> |SIMPLE |vmware.hv.datacenter.name[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Full name |<p>The complete product name, including the version information.</p> |SIMPLE |vmware.hv.fullname[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: CPU frequency |<p>The speed of the CPU cores. This is an average value if there are multiple speeds. The product of CPU frequency and number of cores is approximately equal to the sum of the MHz for all the individual cores on the host.</p> |SIMPLE |vmware.hv.hw.cpu.freq[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: CPU model |<p>The CPU model.</p> |SIMPLE |vmware.hv.hw.cpu.model[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: CPU cores |<p>Number of physical CPU cores on the host. Physical CPU cores are the processors contained by a CPU package.</p> |SIMPLE |vmware.hv.hw.cpu.num[{$VMWARE.URL},{$VMWARE.HV.UUID}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: CPU threads |<p>Number of physical CPU threads on the host.</p> |SIMPLE |vmware.hv.hw.cpu.threads[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Total memory |<p>The physical memory size.</p> |SIMPLE |vmware.hv.hw.memory[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Model |<p>The system model identification.</p> |SIMPLE |vmware.hv.hw.model[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Bios UUID |<p>The hardware BIOS identification.</p> |SIMPLE |vmware.hv.hw.uuid[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Vendor |<p>The hardware vendor identification.</p> |SIMPLE |vmware.hv.hw.vendor[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Ballooned memory |<p>The amount of guest physical memory that is currently reclaimed through the balloon driver. Sum of all guest VMs.</p> |SIMPLE |vmware.hv.memory.size.ballooned[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Used memory |<p>Physical memory usage on the host.</p> |SIMPLE |vmware.hv.memory.used[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Number of bytes received |<p>VMware hypervisor network input statistics (bytes per second).</p> |SIMPLE |vmware.hv.network.in[{$VMWARE.URL},{$VMWARE.HV.UUID},bps] |
|VMware |VMware: Number of bytes transmitted |<p>VMware hypervisor network output statistics (bytes per second).</p> |SIMPLE |vmware.hv.network.out[{$VMWARE.URL},{$VMWARE.HV.UUID},bps] |
|VMware |VMware: Overall status |<p>The overall alarm status of the host: gray - unknown, green - ok, red - it has a problem, yellow - it might have a problem.</p> |SIMPLE |vmware.hv.status[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Uptime |<p>System uptime.</p> |SIMPLE |vmware.hv.uptime[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Version |<p>Dot-separated version string.</p> |SIMPLE |vmware.hv.version[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Number of guest VMs |<p>Number of guest virtual machines.</p> |SIMPLE |vmware.hv.vm.num[{$VMWARE.URL},{$VMWARE.HV.UUID}] |
|VMware |VMware: Average read latency of the datastore {#DATASTORE} |<p>Average amount of time for a read operation from the datastore (milliseconds).</p> |SIMPLE |vmware.hv.datastore.read[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE},latency] |
|VMware |VMware: Free space on datastore {#DATASTORE} (percentage) |<p>VMware datastore space in percentage from total.</p> |SIMPLE |vmware.hv.datastore.size[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE},pfree] |
|VMware |VMware: Total size of datastore {#DATASTORE} |<p>VMware datastore space in bytes.</p> |SIMPLE |vmware.hv.datastore.size[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE}] |
|VMware |VMware: Average write latency of the datastore {#DATASTORE} |<p>Average amount of time for a write operation to the datastore (milliseconds).</p> |SIMPLE |vmware.hv.datastore.write[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE},latency] |
|VMware |VMware: Multipath count for datastore {#DATASTORE} |<p>Number of available datastore paths.</p> |SIMPLE |vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE}] |
|VMware |VMware: Health state rollup |<p>The host health state rollup sensor value: gray - unknown, green - ok, red - it has a problem, yellow - it might have a problem.</p> |DEPENDENT |vmware.hv.sensor.health.state[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..HostNumericSensorInfo[?(@.name=="VMware Rollup Health State")].healthState.label.first()`</p> |
|Zabbix raw items |VMware: Get sensors |<p>Master item for sensors data.</p> |SIMPLE |vmware.hv.sensors.get[{$VMWARE.URL},{$VMWARE.HV.UUID}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|VMware: Hypervisor is down |<p>The service is unavailable or does not accept ICMP ping.</p> |`last(/VMware Hypervisor/icmpping[])=0` |AVERAGE |<p>Manual close: YES</p> |
|VMware: The {$VMWARE.HV.UUID} health is Red |<p>One or more components in the appliance might be in an unusable status and the appliance might become unresponsive soon. Security patches might be available.</p> |`last(/VMware Hypervisor/vmware.hv.status[{$VMWARE.URL},{$VMWARE.HV.UUID}])=3` |HIGH | |
|VMware: The {$VMWARE.HV.UUID} health is Yellow |<p>One or more components in the appliance might become overloaded soon.</p> |`last(/VMware Hypervisor/vmware.hv.status[{$VMWARE.URL},{$VMWARE.HV.UUID}])=2` |AVERAGE |<p>**Depends on**:</p><p>- VMware: The {$VMWARE.HV.UUID} health is Red</p> |
|VMware: Hypervisor has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/VMware Hypervisor/vmware.hv.uptime[{$VMWARE.URL},{$VMWARE.HV.UUID}])<10m` |WARNING |<p>Manual close: YES</p> |
|VMware: The multipath count has been changed |<p>The number of available datastore paths less than registered ({#MULTIPATH.COUNT}).</p> |`last(/VMware Hypervisor/vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE}],#1)<>last(/VMware Hypervisor/vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE}],#2) and last(/VMware Hypervisor/vmware.hv.datastore.multipath[{$VMWARE.URL},{$VMWARE.HV.UUID},{#DATASTORE}])<{#MULTIPATH.COUNT}` |AVERAGE |<p>Manual close: YES</p> |
|VMware: The {$VMWARE.HV.UUID} health is Red |<p>One or more components in the appliance might be in an unusable status and the appliance might become unresponsive soon. Security patches might be available.</p> |`last(/VMware Hypervisor/vmware.hv.sensor.health.state[{#SINGLETON}])="Red"` |HIGH |<p>**Depends on**:</p><p>- VMware: The {$VMWARE.HV.UUID} health is Red</p> |
|VMware: The {$VMWARE.HV.UUID} health is Yellow |<p>One or more components in the appliance might become overloaded soon.</p> |`last(/VMware Hypervisor/vmware.hv.sensor.health.state[{#SINGLETON}])="Yellow"` |AVERAGE |<p>**Depends on**:</p><p>- VMware: The {$VMWARE.HV.UUID} health is Red</p><p>- VMware: The {$VMWARE.HV.UUID} health is Red</p><p>- VMware: The {$VMWARE.HV.UUID} health is Yellow</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

