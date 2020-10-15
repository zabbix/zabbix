
# VMware macros

## Overview

For Zabbix version: 5.2 and higher  

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


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com

# VMware

## Overview

For Zabbix version: 5.2 and higher  
The template to monitor VMware vCenter and ESX hypervisor.
The "Template VM VMware Hypervisor" and "Template VM VMware Guest" templates are used by discovery and normally should not be manually linked to a host.
For additional information please check https://www.zabbix.com/documentation/current/manual/vm_monitoring


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


## Template links

|Name|
|----|
|VMware macros |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Discover VMware clusters |<p>Discovery of clusters</p> |SIMPLE |vmware.cluster.discovery[{$VMWARE.URL}] |
|Discover VMware datastores |<p>-</p> |SIMPLE |vmware.datastore.discovery[{$VMWARE.URL}] |
|Discover VMware hypervisors |<p>Discovery of hypervisors.</p> |SIMPLE |vmware.hv.discovery[{$VMWARE.URL}] |
|Discover VMware VMs |<p>Discovery of guest virtual machines.</p> |SIMPLE |vmware.vm.discovery[{$VMWARE.URL}] |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|VMware |VMware: Event log |<p>Collect VMware event log. See also: https://www.zabbix.com/documentation/current/manual/config/items/preprocessing/examples#filtering_vmware_event_log_records</p> |SIMPLE |vmware.eventlog[{$VMWARE.URL},skip] |
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

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# VMware Guest

## Overview

For Zabbix version: 5.2 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.


## Template links

|Name|
|----|
|VMware macros |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Network device discovery |<p>Discovery of all network devices.</p> |SIMPLE |vmware.vm.net.if.discovery[{$VMWARE.URL},{HOST.HOST}] |
|Disk device discovery |<p>Discovery of all disk devices.</p> |SIMPLE |vmware.vm.vfs.dev.discovery[{$VMWARE.URL},{HOST.HOST}] |
|Mounted filesystem discovery |<p>Discovery of all guest file systems.</p> |SIMPLE |vmware.vm.vfs.fs.discovery[{$VMWARE.URL},{HOST.HOST}] |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|VMware |VMware: Cluster name |<p>Cluster name of the guest VM.</p> |SIMPLE |vmware.vm.cluster.name[{$VMWARE.URL},{HOST.HOST}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Number of virtual CPUs |<p>Number of virtual CPUs assigned to the guest.</p> |SIMPLE |vmware.vm.cpu.num[{$VMWARE.URL},{HOST.HOST}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: CPU ready |<p>Time that the virtual machine was ready, but could not get scheduled to run on the physical CPU during last measurement interval (VMware vCenter/ESXi Server performance counter sampling interval - 20 seconds)</p> |SIMPLE |vmware.vm.cpu.ready[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: CPU usage |<p>Current upper-bound on CPU usage. The upper-bound is based on the host the virtual machine is current running on, as well as limits configured on the virtual machine itself or any parent resource pool. Valid while the virtual machine is running.</p> |SIMPLE |vmware.vm.cpu.usage[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Datacenter name |<p>Datacenter name of the guest VM.</p> |SIMPLE |vmware.vm.datacenter.name[{$VMWARE.URL},{HOST.HOST}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Hypervisor name |<p>Hypervisor name of the guest VM.</p> |SIMPLE |vmware.vm.hv.name[{$VMWARE.URL},{HOST.HOST}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Ballooned memory |<p>The amount of guest physical memory that is currently reclaimed through the balloon driver.</p> |SIMPLE |vmware.vm.memory.size.ballooned[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Compressed memory |<p>The amount of memory currently in the compression cache for this VM.</p> |SIMPLE |vmware.vm.memory.size.compressed[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Private memory |<p>Amount of memory backed by host memory and not being shared.</p> |SIMPLE |vmware.vm.memory.size.private[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Shared memory |<p>The amount of guest physical memory shared through transparent page sharing.</p> |SIMPLE |vmware.vm.memory.size.shared[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Swapped memory |<p>The amount of guest physical memory swapped out to the VM's swap device by ESX.</p> |SIMPLE |vmware.vm.memory.size.swapped[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Guest memory usage |<p>The amount of guest physical memory that is being used by the VM.</p> |SIMPLE |vmware.vm.memory.size.usage.guest[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Host memory usage |<p>The amount of host physical memory allocated to the VM, accounting for saving from memory sharing with other VMs.</p> |SIMPLE |vmware.vm.memory.size.usage.host[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Memory size |<p>Total size of configured memory.</p> |SIMPLE |vmware.vm.memory.size[{$VMWARE.URL},{HOST.HOST}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Power state |<p>The current power state of the virtual machine.</p> |SIMPLE |vmware.vm.powerstate[{$VMWARE.URL},{HOST.HOST}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|VMware |VMware: Committed storage space |<p>Total storage space, in bytes, committed to this virtual machine across all datastores.</p> |SIMPLE |vmware.vm.storage.committed[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Uncommitted storage space |<p>Additional storage space, in bytes, potentially used by this virtual machine on all datastores.</p> |SIMPLE |vmware.vm.storage.uncommitted[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Unshared storage space |<p>Total storage space, in bytes, occupied by the virtual machine across all datastores, that is not shared with any other virtual machine.</p> |SIMPLE |vmware.vm.storage.unshared[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Uptime |<p>System uptime.</p> |SIMPLE |vmware.vm.uptime[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Number of bytes received on interface {#IFDESC} |<p>VMware virtual machine network interface input statistics (bytes per second).</p> |SIMPLE |vmware.vm.net.if.in[{$VMWARE.URL},{HOST.HOST},{#IFNAME},bps] |
|VMware |VMware: Number of packets received on interface {#IFDESC} |<p>VMware virtual machine network interface input statistics (packets per second).</p> |SIMPLE |vmware.vm.net.if.in[{$VMWARE.URL},{HOST.HOST},{#IFNAME},pps] |
|VMware |VMware: Number of bytes transmitted on interface {#IFDESC} |<p>VMware virtual machine network interface output statistics (bytes per second).</p> |SIMPLE |vmware.vm.net.if.out[{$VMWARE.URL},{HOST.HOST},{#IFNAME},bps] |
|VMware |VMware: Number of packets transmitted on interface {#IFDESC} |<p>VMware virtual machine network interface output statistics (packets per second).</p> |SIMPLE |vmware.vm.net.if.out[{$VMWARE.URL},{HOST.HOST},{#IFNAME},pps] |
|VMware |VMware: Average number of bytes read from the disk {#DISKDESC} |<p>VMware virtual machine disk device read statistics (bytes per second).</p> |SIMPLE |vmware.vm.vfs.dev.read[{$VMWARE.URL},{HOST.HOST},{#DISKNAME},bps] |
|VMware |VMware: Average number of reads from the disk {#DISKDESC} |<p>VMware virtual machine disk device read statistics (operations per second).</p> |SIMPLE |vmware.vm.vfs.dev.read[{$VMWARE.URL},{HOST.HOST},{#DISKNAME},ops] |
|VMware |VMware: Average number of bytes written to the disk {#DISKDESC} |<p>VMware virtual machine disk device write statistics (bytes per second).</p> |SIMPLE |vmware.vm.vfs.dev.write[{$VMWARE.URL},{HOST.HOST},{#DISKNAME},bps] |
|VMware |VMware: Average number of writes to the disk {#DISKDESC} |<p>VMware virtual machine disk device write statistics (operations per second).</p> |SIMPLE |vmware.vm.vfs.dev.write[{$VMWARE.URL},{HOST.HOST},{#DISKNAME},ops] |
|VMware |VMware: Free disk space on {#FSNAME} |<p>VMware virtual machine file system statistics (bytes).</p> |SIMPLE |vmware.vm.vfs.fs.size[{$VMWARE.URL},{HOST.HOST},{#FSNAME},free] |
|VMware |VMware: Free disk space on {#FSNAME} (percentage) |<p>VMware virtual machine file system statistics (percentages).</p> |SIMPLE |vmware.vm.vfs.fs.size[{$VMWARE.URL},{HOST.HOST},{#FSNAME},pfree] |
|VMware |VMware: Total disk space on {#FSNAME} |<p>VMware virtual machine total disk space (bytes).</p> |SIMPLE |vmware.vm.vfs.fs.size[{$VMWARE.URL},{HOST.HOST},{#FSNAME},total]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Used disk space on {#FSNAME} |<p>VMware virtual machine used disk space (bytes).</p> |SIMPLE |vmware.vm.vfs.fs.size[{$VMWARE.URL},{HOST.HOST},{#FSNAME},used] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|VMware: {HOST.HOST} has been restarted (uptime < 10m) |<p>Uptime is less than 10 minutes</p> |`{TEMPLATE_NAME:vmware.vm.uptime[{$VMWARE.URL},{HOST.HOST}].last()}<10m` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# VMware Hypervisor

## Overview

For Zabbix version: 5.2 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.


## Template links

|Name|
|----|
|VMware macros |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Datastore discovery |<p>-</p> |SIMPLE |vmware.hv.datastore.discovery[{$VMWARE.URL},{HOST.HOST}] |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|VMware |VMware: Cluster name |<p>Cluster name of the guest VM.</p> |SIMPLE |vmware.hv.cluster.name[{$VMWARE.URL},{HOST.HOST}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: CPU usage |<p>Aggregated CPU usage across all cores on the host in Hz. This is only available if the host is connected.</p> |SIMPLE |vmware.hv.cpu.usage[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Datacenter name |<p>Datacenter name of the hypervisor.</p> |SIMPLE |vmware.hv.datacenter.name[{$VMWARE.URL},{HOST.HOST}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: Full name |<p>The complete product name, including the version information.</p> |SIMPLE |vmware.hv.fullname[{$VMWARE.URL},{HOST.HOST}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: CPU frequency |<p>The speed of the CPU cores. This is an average value if there are multiple speeds. The product of CPU frequency and number of cores is approximately equal to the sum of the MHz for all the individual cores on the host.</p> |SIMPLE |vmware.hv.hw.cpu.freq[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: CPU model |<p>The CPU model.</p> |SIMPLE |vmware.hv.hw.cpu.model[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: CPU cores |<p>Number of physical CPU cores on the host. Physical CPU cores are the processors contained by a CPU package.</p> |SIMPLE |vmware.hv.hw.cpu.num[{$VMWARE.URL},{HOST.HOST}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|VMware |VMware: CPU threads |<p>Number of physical CPU threads on the host.</p> |SIMPLE |vmware.hv.hw.cpu.threads[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Total memory |<p>The physical memory size.</p> |SIMPLE |vmware.hv.hw.memory[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Model |<p>The system model identification.</p> |SIMPLE |vmware.hv.hw.model[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Bios UUID |<p>The hardware BIOS identification.</p> |SIMPLE |vmware.hv.hw.uuid[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Vendor |<p>The hardware vendor identification.</p> |SIMPLE |vmware.hv.hw.vendor[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Ballooned memory |<p>The amount of guest physical memory that is currently reclaimed through the balloon driver. Sum of all guest VMs.</p> |SIMPLE |vmware.hv.memory.size.ballooned[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Used memory |<p>Physical memory usage on the host.</p> |SIMPLE |vmware.hv.memory.used[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Number of bytes received |<p>VMware hypervisor network input statistics (bytes per second).</p> |SIMPLE |vmware.hv.network.in[{$VMWARE.URL},{HOST.HOST},bps] |
|VMware |VMware: Number of bytes transmitted |<p>VMware hypervisor network output statistics (bytes per second).</p> |SIMPLE |vmware.hv.network.out[{$VMWARE.URL},{HOST.HOST},bps] |
|VMware |VMware: Health state rollup |<p>The host health state rollup sensor value: gray - unknown, green - ok, red - it has a problem, yellow - it might have a problem.</p> |SIMPLE |vmware.hv.sensor.health.state[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Overall status |<p>The overall alarm status of the host: gray - unknown, green - ok, red - it has a problem, yellow - it might have a problem.</p> |SIMPLE |vmware.hv.status[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Uptime |<p>System uptime.</p> |SIMPLE |vmware.hv.uptime[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Version |<p>Dot-separated version string.</p> |SIMPLE |vmware.hv.version[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Number of guest VMs |<p>Number of guest virtual machines.</p> |SIMPLE |vmware.hv.vm.num[{$VMWARE.URL},{HOST.HOST}] |
|VMware |VMware: Average read latency of the datastore {#DATASTORE} |<p>Average amount of time for a read operation from the datastore (milliseconds).</p> |SIMPLE |vmware.hv.datastore.read[{$VMWARE.URL},{HOST.HOST},{#DATASTORE},latency] |
|VMware |VMware: Free space on datastore {#DATASTORE} (percentage) |<p>VMware datastore space in percentage from total.</p> |SIMPLE |vmware.hv.datastore.size[{$VMWARE.URL},{HOST.HOST},{#DATASTORE},pfree] |
|VMware |VMware: Total size of datastore {#DATASTORE} |<p>VMware datastore space in bytes.</p> |SIMPLE |vmware.hv.datastore.size[{$VMWARE.URL},{HOST.HOST},{#DATASTORE}] |
|VMware |VMware: Average write latency of the datastore {#DATASTORE} |<p>Average amount of time for a write operation to the datastore (milliseconds).</p> |SIMPLE |vmware.hv.datastore.write[{$VMWARE.URL},{HOST.HOST},{#DATASTORE},latency] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|VMware: The {HOST.HOST} health is Red |<p>One or more components in the appliance might be in an unusable status and the appliance might become unresponsive soon. Security patches might be available.</p> |`{TEMPLATE_NAME:vmware.hv.sensor.health.state[{$VMWARE.URL},{HOST.HOST}].last()}=3` |HIGH |<p>**Depends on**:</p><p>- VMware: The {HOST.HOST} health is Red</p> |
|VMware: The {HOST.HOST} health is Yellow |<p>One or more components in the appliance might become overloaded soon.</p> |`{TEMPLATE_NAME:vmware.hv.sensor.health.state[{$VMWARE.URL},{HOST.HOST}].last()}=2` |AVERAGE |<p>**Depends on**:</p><p>- VMware: The {HOST.HOST} health is Red</p><p>- VMware: The {HOST.HOST} health is Red</p><p>- VMware: The {HOST.HOST} health is Yellow</p> |
|VMware: The {HOST.HOST} health is Red |<p>One or more components in the appliance might be in an unusable status and the appliance might become unresponsive soon. Security patches might be available.</p> |`{TEMPLATE_NAME:vmware.hv.status[{$VMWARE.URL},{HOST.HOST}].last()}=3` |HIGH | |
|VMware: The {HOST.HOST} health is Yellow |<p>One or more components in the appliance might become overloaded soon.</p> |`{TEMPLATE_NAME:vmware.hv.status[{$VMWARE.URL},{HOST.HOST}].last()}=2` |AVERAGE |<p>**Depends on**:</p><p>- VMware: The {HOST.HOST} health is Red</p> |
|VMware: {HOST.HOST} has been restarted (uptime < 10m) |<p>Uptime is less than 10 minutes</p> |`{TEMPLATE_NAME:vmware.hv.uptime[{$VMWARE.URL},{HOST.HOST}].last()}<10m` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

