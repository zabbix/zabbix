
# Linux CPU by Zabbix agent

## Overview

This template is designed for the effortless deployment of Linux CPU monitoring by Zabbix via Zabbix agent and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on Linux OS following Zabbix [documentation](https://www.zabbix.com/documentation/6.0/manual/concepts/agent#agent-on-unix-like-systems).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}||`90`|
|{$LOAD_AVG_PER_CPU.MAX.WARN}|<p>The CPU load per core is considered sustainable. If necessary, it can be tuned.</p>|`1.5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Linux: Number of CPUs||Zabbix agent|system.cpu.num<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: Load average (1m avg)||Zabbix agent|system.cpu.load[all,avg1]|
|Linux: Load average (5m avg)||Zabbix agent|system.cpu.load[all,avg5]|
|Linux: Load average (15m avg)||Zabbix agent|system.cpu.load[all,avg15]|
|Linux: CPU utilization|<p>The CPU utilization expressed in %.</p>|Dependent item|system.cpu.util<p>**Preprocessing**</p><ul><li><p>JavaScript: `//Calculate utilization<br>return (100 - value)`</p></li></ul>|
|Linux: CPU idle time|<p>The time the CPU has spent doing nothing.</p>|Zabbix agent|system.cpu.util[,idle]|
|Linux: CPU system time|<p>The time the CPU has spent running the kernel and its processes.</p>|Zabbix agent|system.cpu.util[,system]|
|Linux: CPU user time|<p>The time the CPU has spent running users' processes that are not niced.</p>|Zabbix agent|system.cpu.util[,user]|
|Linux: CPU nice time|<p>The time the CPU has spent running users' processes that have been niced.</p>|Zabbix agent|system.cpu.util[,nice]|
|Linux: CPU iowait time|<p>The amount of time the CPU has been waiting for I/O to complete.</p>|Zabbix agent|system.cpu.util[,iowait]|
|Linux: CPU steal time|<p>The amount of "stolen" CPU from this virtual machine by the hypervisor for other tasks, such as running another virtual machine.</p>|Zabbix agent|system.cpu.util[,steal]|
|Linux: CPU interrupt time|<p>The amount of time the CPU has been servicing hardware interrupts.</p>|Zabbix agent|system.cpu.util[,interrupt]|
|Linux: CPU softirq time|<p>The amount of time the CPU has been servicing software interrupts.</p>|Zabbix agent|system.cpu.util[,softirq]|
|Linux: CPU guest time|<p>Guest time - the time spent on running a virtual CPU for a guest operating system.</p>|Zabbix agent|system.cpu.util[,guest]|
|Linux: CPU guest nice time|<p>The time spent on running a niced guest (a virtual CPU for guest operating systems under the control of the Linux kernel).</p>|Zabbix agent|system.cpu.util[,guest_nice]|
|Linux: Context switches per second||Zabbix agent|system.cpu.switches<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Linux: Interrupts per second||Zabbix agent|system.cpu.intr<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: Load average is too high|<p>The load average per CPU is too high. The system may be slow to respond.</p>|`min(/Linux CPU by Zabbix agent/system.cpu.load[all,avg1],5m)/last(/Linux CPU by Zabbix agent/system.cpu.num)>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/Linux CPU by Zabbix agent/system.cpu.load[all,avg5])>0 and last(/Linux CPU by Zabbix agent/system.cpu.load[all,avg15])>0`|Average||
|Linux: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Linux CPU by Zabbix agent/system.cpu.util,5m)>{$CPU.UTIL.CRIT}`|Warning|**Depends on**:<br><ul><li>Linux: Load average is too high</li></ul>|

# Linux filesystems by Zabbix agent

## Overview

This template is designed for the effortless deployment of Linux filesystems monitoring by Zabbix via Zabbix agent and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on Linux OS following Zabbix [documentation](https://www.zabbix.com/documentation/6.0/manual/concepts/agent#agent-on-unix-like-systems).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>This macro is used for discovery of the filesystems. It can be overridden on host level or its linked template level.</p>|`^(/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VFS.FS.FSNAME.MATCHES}|<p>This macro is used for discovery of the filesystems. It can be overridden on host level or its linked template level.</p>|`.+`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>This macro is used for discovery of the filesystems. It can be overridden on host level or its linked template level.</p>|`Macro too long. Please see the template.`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>This macro is used for discovery of the filesystems. It can be overridden on host level or its linked template level.</p>|`^\s$`|
|{$VFS.FS.INODE.PFREE.MIN.CRIT}|<p>The critical threshold of the filesystem metadata utilization.</p>|`10`|
|{$VFS.FS.INODE.PFREE.MIN.WARN}|<p>The warning threshold of the filesystem metadata utilization.</p>|`20`|
|{$VFS.FS.PUSED.MAX.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`90`|
|{$VFS.FS.PUSED.MAX.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`80`|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>The discovery of mounted filesystems with different types.</p>|Zabbix agent|vfs.fs.discovery|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FSNAME}: Used space|<p>Used storage in bytes</p>|Zabbix agent|vfs.fs.size[{#FSNAME},used]|
|{#FSNAME}: Total space|<p>Total space in bytes</p>|Zabbix agent|vfs.fs.size[{#FSNAME},total]|
|{#FSNAME}: Space utilization|<p>The space utilization expressed in % for {#FSNAME}.</p>|Zabbix agent|vfs.fs.size[{#FSNAME},pused]|
|{#FSNAME}: Free inodes in %||Zabbix agent|vfs.fs.inode[{#FSNAME},pfree]|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSNAME}: Disk space is critically low|<p>The volume's space usage exceeds the `{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}` limit.</p>|`last(/Linux filesystems by Zabbix agent/vfs.fs.size[{#FSNAME},pused])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`|Average|**Manual close**: Yes|
|{#FSNAME}: Disk space is low|<p>The volume's space usage exceeds the `{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}` limit.</p>|`last(/Linux filesystems by Zabbix agent/vfs.fs.size[{#FSNAME},pused])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#FSNAME}: Disk space is critically low</li></ul>|
|{#FSNAME}: Running out of free inodes|<p>It may become impossible to write to a disk if there are no index nodes left.<br>The following error messages may be returned as symptoms, even though the free space is available:<br>- 'No space left on device';<br>- 'Disk is full'.</p>|`min(/Linux filesystems by Zabbix agent/vfs.fs.inode[{#FSNAME},pfree],5m)<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}`|Average||
|{#FSNAME}: Running out of free inodes|<p>It may become impossible to write to a disk if there are no index nodes left.<br>The following error messages may be returned as symptoms, even though the free space is available:<br>- 'No space left on device';<br>- 'Disk is full'.</p>|`min(/Linux filesystems by Zabbix agent/vfs.fs.inode[{#FSNAME},pfree],5m)<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}`|Warning|**Depends on**:<br><ul><li>{#FSNAME}: Running out of free inodes</li></ul>|

# Linux memory by Zabbix agent

## Overview

This template is designed for the effortless deployment of Linux memory monitoring by Zabbix via Zabbix agent and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on Linux OS following Zabbix [documentation](https://www.zabbix.com/documentation/6.0/manual/concepts/agent#agent-on-unix-like-systems).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMORY.UTIL.MAX}|<p>This macro is used as a threshold in the memory utilization trigger.</p>|`90`|
|{$MEMORY.AVAILABLE.MIN}|<p>This macro is used as a threshold in the memory available trigger.</p>|`20M`|
|{$SWAP.PFREE.MIN.WARN}||`50`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Linux: Memory utilization|<p>The percentage of used memory is calculated as `100-pavailable`.</p>|Dependent item|vm.memory.utilization<p>**Preprocessing**</p><ul><li><p>JavaScript: `return (100-value);`</p></li></ul>|
|Linux: Available memory in %|<p>The available memory as percentage of the total. See also Appendixes in Zabbix Documentation about parameters of the `vm.memory.size` item.</p>|Zabbix agent|vm.memory.size[pavailable]|
|Linux: Total memory|<p>The total memory expressed in bytes.</p>|Zabbix agent|vm.memory.size[total]|
|Linux: Available memory|<p>The available memory:</p><p>- in Linux - available = free + buffers + cache;</p><p>- on other platforms calculation may vary.</p><p></p><p>See also Appendixes in Zabbix Documentation about parameters of the `vm.memory.size` item.</p>|Zabbix agent|vm.memory.size[available]|
|Linux: Total swap space|<p>The total space of the swap volume/file expressed in bytes.</p>|Zabbix agent|system.swap.size[,total]|
|Linux: Free swap space|<p>The free space of the swap volume/file expressed in bytes.</p>|Zabbix agent|system.swap.size[,free]|
|Linux: Free swap space in %|<p>The free space of the swap volume/file expressed in %.</p>|Zabbix agent|system.swap.size[,pfree]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Linux memory by Zabbix agent/vm.memory.utilization,5m)>{$MEMORY.UTIL.MAX}`|Average|**Depends on**:<br><ul><li>Linux: Lack of available memory</li></ul>|
|Linux: Lack of available memory||`max(/Linux memory by Zabbix agent/vm.memory.size[available],5m)<{$MEMORY.AVAILABLE.MIN} and last(/Linux memory by Zabbix agent/vm.memory.size[total])>0`|Average||
|Linux: High swap space usage|<p>If there is no swap configured, this trigger is ignored.</p>|`max(/Linux memory by Zabbix agent/system.swap.size[,pfree],5m)<{$SWAP.PFREE.MIN.WARN} and last(/Linux memory by Zabbix agent/system.swap.size[,total])>0`|Warning|**Depends on**:<br><ul><li>Linux: Lack of available memory</li><li>Linux: High memory utilization</li></ul>|

# Linux block devices by Zabbix agent

## Overview

This template is designed for the effortless deployment of Linux block devices monitoring by Zabbix via Zabbix agent and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on Linux OS following Zabbix [documentation](https://www.zabbix.com/documentation/6.0/manual/concepts/agent#agent-on-unix-like-systems).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.DEV.READ.AWAIT.WARN}|<p>The average response time (in ms) of disk read before the trigger would fire.</p>|`20`|
|{$VFS.DEV.WRITE.AWAIT.WARN}|<p>The average response time (in ms) of disk write before the trigger would fire.</p>|`20`|
|{$VFS.DEV.DEVNAME.NOT_MATCHES}|<p>This macro is used for a discovery of block devices. It can be overridden on host level or its linked template level.</p>|`Macro too long. Please see the template.`|
|{$VFS.DEV.DEVNAME.MATCHES}|<p>This macro is used for a discovery of block devices. It can be overridden on host level or its linked template level.</p>|`.+`|

### LLD rule Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Block devices discovery||Zabbix agent|vfs.dev.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DEVNAME}: Get stats|<p>The contents of get `/sys/block/{#DEVNAME}/stat` to get the disk statistics.</p>|Zabbix agent|vfs.file.contents[/sys/block/{#DEVNAME}/stat]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return JSON.stringify(value.trim().split(/ +/));`</p></li></ul>|
|{#DEVNAME}: Disk read rate|<p>r/s (read operations per second) - the number (after merges) of read requests completed per second for the device.</p>|Dependent item|vfs.dev.read.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0]`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk write rate|<p>w/s (write operations per second) - the number (after merges) of write requests completed per second for the device.</p>|Dependent item|vfs.dev.write.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[4]`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk read time (rate)|<p>The rate of total read time counter; used in `r_await` calculation.</p>|Dependent item|vfs.dev.read.time.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[3]`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#DEVNAME}: Disk write time (rate)|<p>The rate of total write time counter; used in `w_await` calculation.</p>|Dependent item|vfs.dev.write.time.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[7]`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#DEVNAME}: Disk read request avg waiting time (r_await)|<p>This formula contains two Boolean expressions that evaluate to 1 or 0 in order to set the calculated metric to zero and to avoid the exception - division by zero.</p>|Calculated|vfs.dev.read.await[{#DEVNAME}]|
|{#DEVNAME}: Disk write request avg waiting time (w_await)|<p>This formula contains two Boolean expressions that evaluate to 1 or 0 in order to set the calculated metric to zero and to avoid the exception - division by zero.</p>|Calculated|vfs.dev.write.await[{#DEVNAME}]|
|{#DEVNAME}: Disk average queue size (avgqu-sz)|<p>The current average disk queue; the number of requests outstanding on the disk while the performance data is being collected.</p>|Dependent item|vfs.dev.queue_size[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[10]`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#DEVNAME}: Disk utilization|<p>This item is the percentage of elapsed time during which the selected disk drive was busy while servicing read or write requests.</p>|Dependent item|vfs.dev.util[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[9]`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.1`</p></li></ul>|

### Trigger prototypes for Block devices discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#DEVNAME}: Disk read/write request responses are too high|<p>This trigger might indicate the disk `{#DEVNAME}` saturation.</p>|`min(/Linux block devices by Zabbix agent/vfs.dev.read.await[{#DEVNAME}],15m) > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} or min(/Linux block devices by Zabbix agent/vfs.dev.write.await[{#DEVNAME}],15m) > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}`|Warning|**Manual close**: Yes|

# Linux network interfaces by Zabbix agent

## Overview

This template is designed for the effortless deployment of Linux network interfaces monitoring by Zabbix via Zabbix agent and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on Linux OS following Zabbix [documentation](https://www.zabbix.com/documentation/6.0/manual/concepts/agent#agent-on-unix-like-systems).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IF.ERRORS.WARN}||`2`|
|{$IFCONTROL}||`1`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>It filters out `loopbacks`, `nulls`, `docker veth` links and `docker0 bridge` by default.</p>|`Macro too long. Please see the template.`|
|{$IF.UTIL.MAX}|<p>This macro is used as a threshold in the interface utilization trigger.</p>|`90`|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>The discovery of network interfaces.</p>|Zabbix agent|net.if.discovery|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}: Bits received||Zabbix agent|net.if.in["{#IFNAME}"]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}: Bits sent||Zabbix agent|net.if.out["{#IFNAME}"]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}: Outbound packets with errors||Zabbix agent|net.if.out["{#IFNAME}",errors]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}: Inbound packets with errors||Zabbix agent|net.if.in["{#IFNAME}",errors]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}: Outbound packets discarded||Zabbix agent|net.if.out["{#IFNAME}",dropped]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}: Inbound packets discarded||Zabbix agent|net.if.in["{#IFNAME}",dropped]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}: Operational status|<p>Reference: https://www.kernel.org/doc/Documentation/networking/operstates.txt</p>|Zabbix agent|vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Interface {#IFNAME}: Interface type|<p>It indicates the interface protocol type as a decimal value.</p><p>See `include/uapi/linux/if_arp.h` for all possible values.</p><p>Reference: https://www.kernel.org/doc/Documentation/ABI/testing/sysfs-class-net</p>|Zabbix agent|vfs.file.contents["/sys/class/net/{#IFNAME}/type"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}: Speed|<p>It indicates the latest or current speed value of the interface. The value is an integer representing the link speed expressed in bits/sec.</p><p>This attribute is only valid for the interfaces that implement the ethtool `get_link_ksettings` method (mostly Ethernet).</p><p></p><p>Reference: https://www.kernel.org/doc/Documentation/ABI/testing/sysfs-class-net</p>|Zabbix agent|vfs.file.contents["/sys/class/net/{#IFNAME}/speed"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}: High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Linux network interfaces by Zabbix agent/net.if.in["{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"]) or avg(/Linux network interfaces by Zabbix agent/net.if.out["{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])) and last(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}: Link down</li></ul>|
|Interface {#IFNAME}: High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Linux network interfaces by Zabbix agent/net.if.in["{#IFNAME}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Linux network interfaces by Zabbix agent/net.if.out["{#IFNAME}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}: Link down</li></ul>|
|Interface {#IFNAME}: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"])=2 and (last(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"],#1)<>last(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"],#2))`|Average|**Manual close**: Yes|
|Interface {#IFNAME}: Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])<0 and last(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])>0 and (last(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/type"])=6 or last(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/type"])=1) and (last(/Linux network interfaces by Zabbix agent/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}: Link down</li></ul>|

# Linux generic by Zabbix agent

## Overview

This template is designed for the effortless deployment of Linux generic monitoring by Zabbix via Zabbix agent and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on Linux OS following Zabbix [documentation](https://www.zabbix.com/documentation/6.0/manual/concepts/agent#agent-on-unix-like-systems).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SYSTEM.FUZZYTIME.MAX}||`60`|
|{$KERNEL.MAXPROC.MIN}||`1024`|
|{$KERNEL.MAXFILES.MIN}||`256`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Linux: System uptime|<p>The system uptime expressed in the following format: "N days, hh:mm:ss".</p>|Zabbix agent|system.uptime|
|Linux: System boot time||Zabbix agent|system.boottime<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Linux: System local time|<p>The local system time of the host.</p>|Zabbix agent|system.localtime|
|Linux: System name|<p>The host name of the system.</p>|Zabbix agent|system.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Linux: System description|<p>The information as normally returned by `uname -a`.</p>|Zabbix agent|system.uname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Linux: Number of logged in users|<p>The number of users who are currently logged in.</p>|Zabbix agent|system.users.num|
|Linux: Maximum number of open file descriptors|<p>It could be increased by using `sysctl` utility or modifying the file `/etc/sysctl.conf`.</p>|Zabbix agent|kernel.maxfiles<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: Maximum number of processes|<p>It could be increased by using `sysctl` utility or modifying the file `/etc/sysctl.conf`.</p>|Zabbix agent|kernel.maxproc<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: Number of processes||Zabbix agent|proc.num|
|Linux: Number of running processes||Zabbix agent|proc.num[,,run]|
|Linux: Checksum of /etc/passwd||Zabbix agent|vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Linux: Operating system||Zabbix agent|system.sw.os<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: Operating system architecture|<p>The architecture of the operating system.</p>|Zabbix agent|system.sw.arch<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: Software installed||Zabbix agent|system.sw.packages<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: {HOST.NAME} has been restarted|<p>The host uptime is less than 10 minutes.</p>|`last(/Linux generic by Zabbix agent/system.uptime)<10m`|Warning|**Manual close**: Yes|
|Linux: System time is out of sync|<p>The host's system time is different from Zabbix server time.</p>|`fuzzytime(/Linux generic by Zabbix agent/system.localtime,{$SYSTEM.FUZZYTIME.MAX})=0`|Warning|**Manual close**: Yes|
|Linux: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`change(/Linux generic by Zabbix agent/system.hostname) and length(last(/Linux generic by Zabbix agent/system.hostname))>0`|Info|**Manual close**: Yes|
|Linux: Configured max number of open filedescriptors is too low||`last(/Linux generic by Zabbix agent/kernel.maxfiles)<{$KERNEL.MAXFILES.MIN}`|Info||
|Linux: Configured max number of processes is too low||`last(/Linux generic by Zabbix agent/kernel.maxproc)<{$KERNEL.MAXPROC.MIN}`|Info|**Depends on**:<br><ul><li>Linux: Getting closer to process limit</li></ul>|
|Linux: Getting closer to process limit||`last(/Linux generic by Zabbix agent/proc.num)/last(/Linux generic by Zabbix agent/kernel.maxproc)*100>80`|Warning||
|Linux: /etc/passwd has been changed||`last(/Linux generic by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/Linux generic by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: System name has changed</li><li>Linux: Operating system description has changed</li></ul>|
|Linux: Operating system description has changed|<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`change(/Linux generic by Zabbix agent/system.sw.os) and length(last(/Linux generic by Zabbix agent/system.sw.os))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: System name has changed</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

