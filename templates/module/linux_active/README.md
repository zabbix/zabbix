
# Linux CPU by Zabbix agent active

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Install Zabbix agent on Linux OS according to Zabbix documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$LOAD_AVG_PER_CPU.MAX.WARN} |<p>CPU load per core is considered sustainable. If necessary, it can be tuned.</p> |`1.5` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |Number of CPUs |<p>-</p> |ZABBIX_ACTIVE |system.cpu.num<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|CPU |Load average (1m avg) |<p>-</p> |ZABBIX_ACTIVE |system.cpu.load[all,avg1] |
|CPU |Load average (5m avg) |<p>-</p> |ZABBIX_ACTIVE |system.cpu.load[all,avg5] |
|CPU |Load average (15m avg) |<p>-</p> |ZABBIX_ACTIVE |system.cpu.load[all,avg15] |
|CPU |CPU utilization |<p>The CPU utilization expressed in %.</p> |DEPENDENT |system.cpu.util<p>**Preprocessing**:</p><p>- JAVASCRIPT: `//Calculate utilization return (100 - value)`</p> |
|CPU |CPU idle time |<p>The time the CPU has spent doing nothing.</p> |ZABBIX_ACTIVE |system.cpu.util[,idle] |
|CPU |CPU system time |<p>The time the CPU has spent running the kernel and its processes.</p> |ZABBIX_ACTIVE |system.cpu.util[,system] |
|CPU |CPU user time |<p>The time the CPU has spent running users' processes that are not niced.</p> |ZABBIX_ACTIVE |system.cpu.util[,user] |
|CPU |CPU nice time |<p>The time the CPU has spent running users' processes that have been niced.</p> |ZABBIX_ACTIVE |system.cpu.util[,nice] |
|CPU |CPU iowait time |<p>The amount of time the CPU has been waiting for I/O to complete.</p> |ZABBIX_ACTIVE |system.cpu.util[,iowait] |
|CPU |CPU steal time |<p>The amount of 'stolen' CPU from this virtual machine by the hypervisor for other tasks, such as running another virtual machine.</p> |ZABBIX_ACTIVE |system.cpu.util[,steal] |
|CPU |CPU interrupt time |<p>The amount of time the CPU has been servicing hardware interrupts.</p> |ZABBIX_ACTIVE |system.cpu.util[,interrupt] |
|CPU |CPU softirq time |<p>The amount of time the CPU has been servicing software interrupts.</p> |ZABBIX_ACTIVE |system.cpu.util[,softirq] |
|CPU |CPU guest time |<p>Guest time - the time spent on running a virtual CPU for a guest operating system.</p> |ZABBIX_ACTIVE |system.cpu.util[,guest] |
|CPU |CPU guest nice time |<p>The time spent on running a niced guest (a virtual CPU for guest operating systems under the control of the Linux kernel).</p> |ZABBIX_ACTIVE |system.cpu.util[,guest_nice] |
|CPU |Context switches per second |<p>-</p> |ZABBIX_ACTIVE |system.cpu.switches<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|CPU |Interrupts per second |<p>-</p> |ZABBIX_ACTIVE |system.cpu.intr<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Load average is too high |<p>The load average per CPU is too high. The system may be slow to respond.</p> |`min(/Linux CPU by Zabbix agent active/system.cpu.load[all,avg1],5m)/last(/Linux CPU by Zabbix agent active/system.cpu.num)>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/Linux CPU by Zabbix agent active/system.cpu.load[all,avg5])>0 and last(/Linux CPU by Zabbix agent active/system.cpu.load[all,avg15])>0` |AVERAGE | |
|High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Linux CPU by Zabbix agent active/system.cpu.util,5m)>{$CPU.UTIL.CRIT}` |WARNING |<p>**Depends on**:</p><p>- Load average is too high</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

# Linux filesystems by Zabbix agent active

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Install Zabbix agent on Linux OS according to Zabbix documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.FS.FREE.MIN.CRIT} |<p>The critical threshold for utilization of the filesystem.</p> |`5G` |
|{$VFS.FS.FREE.MIN.WARN} |<p>The critical threshold for utilization of the filesystem.</p> |`10G` |
|{$VFS.FS.FSNAME.MATCHES} |<p>This macro is used for discovery of the filesystems. It can be overridden on host level or its linked template level.</p> |`.+` |
|{$VFS.FS.FSNAME.NOT_MATCHES} |<p>This macro is used for discovery of the filesystems. It can be overridden on host level or its linked template level.</p> |`^(/dev|/sys|/run|/proc|.+/shm$)` |
|{$VFS.FS.FSTYPE.MATCHES} |<p>This macro is used for discovery of the filesystems. It can be overridden on host level or its linked template level.</p> |`^(btrfs|ext2|ext3|ext4|reiser|xfs|ffs|ufs|jfs|jfs2|vxfs|hfs|apfs|refs|ntfs|fat32|zfs)$` |
|{$VFS.FS.FSTYPE.NOT_MATCHES} |<p>This macro is used for discovery of the filesystems. It can be overridden on host level or its linked template level.</p> |`^\s$` |
|{$VFS.FS.INODE.PFREE.MIN.CRIT} |<p>-</p> |`10` |
|{$VFS.FS.INODE.PFREE.MIN.WARN} |<p>-</p> |`20` |
|{$VFS.FS.PUSED.MAX.CRIT} |<p>-</p> |`90` |
|{$VFS.FS.PUSED.MAX.WARN} |<p>-</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Mounted filesystem discovery |<p>Discovery of file systems of different types.</p> |ZABBIX_ACTIVE |vfs.fs.discovery<p>**Filter**:</p>AND <p>- {#FSTYPE} MATCHES_REGEX `{$VFS.FS.FSTYPE.MATCHES}`</p><p>- {#FSTYPE} NOT_MATCHES_REGEX `{$VFS.FS.FSTYPE.NOT_MATCHES}`</p><p>- {#FSNAME} MATCHES_REGEX `{$VFS.FS.FSNAME.MATCHES}`</p><p>- {#FSNAME} NOT_MATCHES_REGEX `{$VFS.FS.FSNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Filesystems |{#FSNAME}: Used space |<p>Used storage expressed in Bytes</p> |ZABBIX_ACTIVE |vfs.fs.size[{#FSNAME},used] |
|Filesystems |{#FSNAME}: Total space |<p>The total space expressed in Bytes.</p> |ZABBIX_ACTIVE |vfs.fs.size[{#FSNAME},total] |
|Filesystems |{#FSNAME}: Space utilization |<p>The space utilization expressed in % for {#FSNAME}.</p> |ZABBIX_ACTIVE |vfs.fs.size[{#FSNAME},pused] |
|Filesystems |{#FSNAME}: Free inodes in % |<p>-</p> |ZABBIX_ACTIVE |vfs.fs.inode[{#FSNAME},pfree] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#FSNAME}: Disk space is critically low |<p>Two conditions should match:</p><p> 1. The first condition - utilization of space should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.</p><p> 2. The second condition should be one of the following:</p><p>  - the disk free space is less than {$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"};</p><p>  - the disk will be full in less than 24 hours.</p> |`last(/Linux filesystems by Zabbix agent active/vfs.fs.size[{#FSNAME},pused])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/Linux filesystems by Zabbix agent active/vfs.fs.size[{#FSNAME},total])-last(/Linux filesystems by Zabbix agent active/vfs.fs.size[{#FSNAME},used]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/Linux filesystems by Zabbix agent active/vfs.fs.size[{#FSNAME},pused],1h,100)<1d) ` |AVERAGE |<p>Manual close: YES</p> |
|{#FSNAME}: Disk space is low |<p>Two conditions should match:</p><p> 1. The first condition - utilization of space should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.</p><p> 2. The second condition should be one of the following:</p><p>  - the disk free space is less than {$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"};</p><p>  - the disk will be full in less than 24 hours.</p> |`last(/Linux filesystems by Zabbix agent active/vfs.fs.size[{#FSNAME},pused])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/Linux filesystems by Zabbix agent active/vfs.fs.size[{#FSNAME},total])-last(/Linux filesystems by Zabbix agent active/vfs.fs.size[{#FSNAME},used]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/Linux filesystems by Zabbix agent active/vfs.fs.size[{#FSNAME},pused],1h,100)<1d) ` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#FSNAME}: Disk space is critically low</p> |
|{#FSNAME}: Running out of free inodes |<p>It may become impossible to write to a disk if there are no index nodes left.</p><p>Following error messages may be returned as symptoms, even though the free space is available:</p><p> - 'No space left on device';</p><p> - 'Disk is full'.</p> |`min(/Linux filesystems by Zabbix agent active/vfs.fs.inode[{#FSNAME},pfree],5m)<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}` |AVERAGE | |
|{#FSNAME}: Running out of free inodes |<p>It may become impossible to write to a disk if there are no index nodes left.</p><p>Following error messages may be returned as symptoms, even though the free space is available:</p><p> - 'No space left on device';</p><p> - 'Disk is full'.</p> |`min(/Linux filesystems by Zabbix agent active/vfs.fs.inode[{#FSNAME},pfree],5m)<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}` |WARNING |<p>**Depends on**:</p><p>- {#FSNAME}: Running out of free inodes</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

# Linux memory by Zabbix agent active

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Install Zabbix agent on Linux OS according to Zabbix documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMORY.AVAILABLE.MIN} |<p>This macro is used as a threshold in the memory available trigger.</p> |`20M` |
|{$MEMORY.UTIL.MAX} |<p>This macro is used as a threshold in the memory utilization trigger.</p> |`90` |
|{$SWAP.PFREE.MIN.WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Memory |Memory utilization |<p>The percentage of used memory is calculated as 100-pavailable.</p> |DEPENDENT |vm.memory.utilization<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return (100-value);`</p> |
|Memory |Available memory in % |<p>The available memory as percentage of the total. See also Appendixes in Zabbix Documentation about parameters of the vm.memory.size item.</p> |ZABBIX_ACTIVE |vm.memory.size[pavailable] |
|Memory |Total memory |<p>The total memory expressed in Bytes.</p> |ZABBIX_ACTIVE |vm.memory.size[total] |
|Memory |Available memory |<p>The available memory:</p><p> - in Linux - available = free + buffers + cache;</p><p> - on other platforms calculation may vary.</p><p>See also Appendixes in Zabbix Documentation about parameters of the vm.memory.size item.</p> |ZABBIX_ACTIVE |vm.memory.size[available] |
|Memory |Total swap space |<p>The total space of the swap volume/file expressed in bytes.</p> |ZABBIX_ACTIVE |system.swap.size[,total] |
|Memory |Free swap space |<p>The free space of the swap volume/file expressed in bytes.</p> |ZABBIX_ACTIVE |system.swap.size[,free] |
|Memory |Free swap space in % |<p>The free space of the swap volume/file expressed in %.</p> |ZABBIX_ACTIVE |system.swap.size[,pfree] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High memory utilization |<p>The system is running out of free memory.</p> |`min(/Linux memory by Zabbix agent active/vm.memory.utilization,5m)>{$MEMORY.UTIL.MAX}` |AVERAGE |<p>**Depends on**:</p><p>- Lack of available memory</p> |
|Lack of available memory |<p>-</p> |`max(/Linux memory by Zabbix agent active/vm.memory.size[available],5m)<{$MEMORY.AVAILABLE.MIN} and last(/Linux memory by Zabbix agent active/vm.memory.size[total])>0` |AVERAGE | |
|High swap space usage |<p>If there is no swap configured, this trigger is ignored.</p> |`max(/Linux memory by Zabbix agent active/system.swap.size[,pfree],5m)<{$SWAP.PFREE.MIN.WARN} and last(/Linux memory by Zabbix agent active/system.swap.size[,total])>0` |WARNING |<p>**Depends on**:</p><p>- High memory utilization</p><p>- Lack of available memory</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

# Linux block devices by Zabbix agent active

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Install Zabbix agent on Linux OS according to Zabbix documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.DEV.DEVNAME.MATCHES} |<p>This macro is used for a discovery of block devices. It can be overridden on host level or its linked template level.</p> |`.+` |
|{$VFS.DEV.DEVNAME.NOT_MATCHES} |<p>This macro is used for a discovery of block devices. It can be overridden on host level or its linked template level.</p> |`^(loop[0-9]*|sd[a-z][0-9]+|nbd[0-9]+|sr[0-9]+|fd[0-9]+|dm-[0-9]+|ram[0-9]+|ploop[a-z0-9]+|md[0-9]*|hcp[0-9]*|zram[0-9]*)` |
|{$VFS.DEV.READ.AWAIT.WARN} |<p>The average response time (in ms) of disk read before the trigger would fire.</p> |`20` |
|{$VFS.DEV.WRITE.AWAIT.WARN} |<p>The average response time (in ms) of disk write before the trigger would fire.</p> |`20` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Block devices discovery |<p>-</p> |ZABBIX_ACTIVE |vfs.dev.discovery<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- {#DEVTYPE} MATCHES_REGEX `disk`</p><p>- {#DEVNAME} MATCHES_REGEX `{$VFS.DEV.DEVNAME.MATCHES}`</p><p>- {#DEVNAME} NOT_MATCHES_REGEX `{$VFS.DEV.DEVNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Storage |{#DEVNAME}: Disk read rate |<p>r/s (read operations per second) - the number (after merges) of read requests completed per second for the device.</p> |DEPENDENT |vfs.dev.read.rate[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[0]`</p><p>- CHANGE_PER_SECOND</p> |
|Storage |{#DEVNAME}: Disk write rate |<p>w/s (write operations per second) - the number (after merges) of write requests completed per second for the device.</p> |DEPENDENT |vfs.dev.write.rate[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[4]`</p><p>- CHANGE_PER_SECOND</p> |
|Storage |{#DEVNAME}: Disk read request avg waiting time (r_await) |<p>This formula contains two boolean expressions that evaluate to 1 or 0 in order to set the calculated metric to zero and to avoid the exception - division by zero.</p> |CALCULATED |vfs.dev.read.await[{#DEVNAME}]<p>**Expression**:</p>`(last(//vfs.dev.read.time.rate[{#DEVNAME}])/(last(//vfs.dev.read.rate[{#DEVNAME}])+(last(//vfs.dev.read.rate[{#DEVNAME}])=0)))*1000*(last(//vfs.dev.read.rate[{#DEVNAME}]) > 0)` |
|Storage |{#DEVNAME}: Disk write request avg waiting time (w_await) |<p>This formula contains two boolean expressions that evaluate to 1 or 0 in order to set the calculated metric to zero and to avoid the exception - division by zero.</p> |CALCULATED |vfs.dev.write.await[{#DEVNAME}]<p>**Expression**:</p>`(last(//vfs.dev.write.time.rate[{#DEVNAME}])/(last(//vfs.dev.write.rate[{#DEVNAME}])+(last(//vfs.dev.write.rate[{#DEVNAME}])=0)))*1000*(last(//vfs.dev.write.rate[{#DEVNAME}]) > 0)` |
|Storage |{#DEVNAME}: Disk average queue size (avgqu-sz) |<p>The current average disk queue; the number of requests outstanding on the disk while the performance data is being collected.</p> |DEPENDENT |vfs.dev.queue_size[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[10]`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `0.001`</p> |
|Storage |{#DEVNAME}: Disk utilization |<p>This item is the percentage of elapsed time during which the selected disk drive was busy while servicing read or write requests.</p> |DEPENDENT |vfs.dev.util[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[9]`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `0.1`</p> |
|Zabbix raw items |{#DEVNAME}: Get stats |<p>The contents of get /sys/block/{#DEVNAME}/stat to get the disk statistics.</p> |ZABBIX_ACTIVE |vfs.file.contents[/sys/block/{#DEVNAME}/stat]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(value.trim().split(/ +/));`</p> |
|Zabbix raw items |{#DEVNAME}: Disk read time (rate) |<p>The rate of total read time counter; used in r_await calculation.</p> |DEPENDENT |vfs.dev.read.time.rate[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[3]`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `0.001`</p> |
|Zabbix raw items |{#DEVNAME}: Disk write time (rate) |<p>The rate of total write time counter; used in w_await calculation.</p> |DEPENDENT |vfs.dev.write.time.rate[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[7]`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `0.001`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#DEVNAME}: Disk read/write request responses are too high |<p>This trigger might indicate the disk {#DEVNAME} saturation.</p> |`min(/Linux block devices by Zabbix agent active/vfs.dev.read.await[{#DEVNAME}],15m) > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} or min(/Linux block devices by Zabbix agent active/vfs.dev.write.await[{#DEVNAME}],15m) > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

# Linux network interfaces by Zabbix agent active

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Install Zabbix agent on Linux OS according to Zabbix documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IF.ERRORS.WARN} |<p>-</p> |`2` |
|{$IF.UTIL.MAX} |<p>This macro is used as a threshold in the interface utilization trigger.</p> |`90` |
|{$IFCONTROL} |<p>-</p> |`1` |
|{$NET.IF.IFNAME.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>It filters out loopbacks, nulls, docker veth links and docker0 bridge by default.</p> |`(^Software Loopback Interface|^NULL[0-9.]*$|^[Ll]o[0-9.]*$|^[Ss]ystem$|^Nu[0-9.]*$|^veth[0-9A-z]+$|docker[0-9]+|br-[a-z0-9]{12})` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Network interface discovery |<p>The discovery of network interfaces.</p> |ZABBIX_ACTIVE |net.if.discovery<p>**Filter**:</p>AND <p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Network interfaces |Interface {#IFNAME}: Bits received |<p>-</p> |ZABBIX_ACTIVE |net.if.in["{#IFNAME}"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}: Bits sent |<p>-</p> |ZABBIX_ACTIVE |net.if.out["{#IFNAME}"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}: Outbound packets with errors |<p>-</p> |ZABBIX_ACTIVE |net.if.out["{#IFNAME}",errors]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}: Inbound packets with errors |<p>-</p> |ZABBIX_ACTIVE |net.if.in["{#IFNAME}",errors]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}: Outbound packets discarded |<p>-</p> |ZABBIX_ACTIVE |net.if.out["{#IFNAME}",dropped]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}: Inbound packets discarded |<p>-</p> |ZABBIX_ACTIVE |net.if.in["{#IFNAME}",dropped]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}: Operational status |<p>Reference: https://www.kernel.org/doc/Documentation/networking/operstates.txt</p> |ZABBIX_ACTIVE |vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Network interfaces |Interface {#IFNAME}: Interface type |<p>It indicates the interface protocol type as a decimal value.</p><p>See include/uapi/linux/if_arp.h for all possible values.</p><p>Reference: https://www.kernel.org/doc/Documentation/ABI/testing/sysfs-class-net</p> |ZABBIX_ACTIVE |vfs.file.contents["/sys/class/net/{#IFNAME}/type"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network interfaces |Interface {#IFNAME}: Speed |<p>It indicates the latest or current speed value of the interface. The value is an integer representing the link speed expressed in bits/sec.</p><p>This attribute is only valid for the interfaces that implement the ethtool get_link_ksettings method (mostly Ethernet).</p><p>Reference: https://www.kernel.org/doc/Documentation/ABI/testing/sysfs-class-net</p> |ZABBIX_ACTIVE |vfs.file.contents["/sys/class/net/{#IFNAME}/speed"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Interface {#IFNAME}: High bandwidth usage |<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p> |`(avg(/Linux network interfaces by Zabbix agent active/net.if.in["{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"]) or avg(/Linux network interfaces by Zabbix agent active/net.if.out["{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])) and last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])>0`<p>Recovery expression:</p>`avg(/Linux network interfaces by Zabbix agent active/net.if.in["{#IFNAME}"],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"]) and avg(/Linux network interfaces by Zabbix agent active/net.if.out["{#IFNAME}"],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}: Link down</p> |
|Interface {#IFNAME}: High error rate |<p>It recovers when it is below 80% of the {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`min(/Linux network interfaces by Zabbix agent active/net.if.in["{#IFNAME}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Linux network interfaces by Zabbix agent active/net.if.out["{#IFNAME}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/Linux network interfaces by Zabbix agent active/net.if.in["{#IFNAME}",errors],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and max(/Linux network interfaces by Zabbix agent active/net.if.out["{#IFNAME}",errors],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}: Link down</p> |
|Interface {#IFNAME}: Link down |<p>This trigger expression works as follows:</p><p>1. It can be triggered if the operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"])=2 and (last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"],#1)<>last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"],#2))`<p>Recovery expression:</p>`last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"])<>2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|Interface {#IFNAME}: Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge (Ack) to close the problem manually.</p> |`change(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])<0 and last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])>0 and (last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/type"])=6 or last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/type"])=1) and (last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"])<>2) `<p>Recovery expression:</p>`(change(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])>0 and last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"],#2)>0) or (last(/Linux network interfaces by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"])=2) ` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}: Link down</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

# Linux generic by Zabbix agent active

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Install Zabbix agent on Linux OS according to Zabbix documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KERNEL.MAXFILES.MIN} |<p>-</p> |`256` |
|{$KERNEL.MAXPROC.MIN} |<p>-</p> |`1024` |
|{$SYSTEM.FUZZYTIME.MAX} |<p>-</p> |`60` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|General |System boot time |<p>-</p> |ZABBIX_ACTIVE |system.boottime<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|General |System local time |<p>The local system time of the host.</p> |ZABBIX_ACTIVE |system.localtime |
|General |System name |<p>The host name of the system.</p> |ZABBIX_ACTIVE |system.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>The information as normally returned by 'uname -a'.</p> |ZABBIX_ACTIVE |system.uname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |Number of logged in users |<p>The number of users who are currently logged in.</p> |ZABBIX_ACTIVE |system.users.num |
|General |Maximum number of open file descriptors |<p>It could be increased by using sysctl utility or modifying the file /etc/sysctl.conf.</p> |ZABBIX_ACTIVE |kernel.maxfiles<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |Maximum number of processes |<p>It could be increased by using sysctl utility or modifying the file /etc/sysctl.conf.</p> |ZABBIX_ACTIVE |kernel.maxproc<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |Number of processes |<p>-</p> |ZABBIX_ACTIVE |proc.num |
|General |Number of running processes |<p>-</p> |ZABBIX_ACTIVE |proc.num[,,run] |
|Inventory |Operating system |<p>-</p> |ZABBIX_ACTIVE |system.sw.os<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system architecture |<p>The architecture of the host's operating system.</p> |ZABBIX_ACTIVE |system.sw.arch<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Software installed |<p>-</p> |ZABBIX_ACTIVE |system.sw.packages<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Security |Checksum of /etc/passwd |<p>-</p> |ZABBIX_ACTIVE |vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |System uptime |<p>The system uptime expressed in the following format:'N days, hh:mm:ss'.</p> |ZABBIX_ACTIVE |system.uptime |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|System time is out of sync |<p>The host's system time is different from Zabbix server time.</p> |`fuzzytime(/Linux generic by Zabbix agent active/system.localtime,{$SYSTEM.FUZZYTIME.MAX})=0` |WARNING |<p>Manual close: YES</p> |
|System name has changed |<p>The name of the system has changed. Ack to close the problem manually.</p> |`last(/Linux generic by Zabbix agent active/system.hostname,#1)<>last(/Linux generic by Zabbix agent active/system.hostname,#2) and length(last(/Linux generic by Zabbix agent active/system.hostname))>0` |INFO |<p>Manual close: YES</p> |
|Configured max number of open filedescriptors is too low |<p>-</p> |`last(/Linux generic by Zabbix agent active/kernel.maxfiles)<{$KERNEL.MAXFILES.MIN}` |INFO | |
|Configured max number of processes is too low |<p>-</p> |`last(/Linux generic by Zabbix agent active/kernel.maxproc)<{$KERNEL.MAXPROC.MIN}` |INFO |<p>**Depends on**:</p><p>- Getting closer to process limit</p> |
|Getting closer to process limit |<p>-</p> |`last(/Linux generic by Zabbix agent active/proc.num)/last(/Linux generic by Zabbix agent active/kernel.maxproc)*100>80` |WARNING | |
|Operating system description has changed |<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Ack to close the problem manually.</p> |`last(/Linux generic by Zabbix agent active/system.sw.os,#1)<>last(/Linux generic by Zabbix agent active/system.sw.os,#2) and length(last(/Linux generic by Zabbix agent active/system.sw.os))>0` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- System name has changed</p> |
|/etc/passwd has been changed |<p>-</p> |`last(/Linux generic by Zabbix agent active/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/Linux generic by Zabbix agent active/vfs.file.cksum[/etc/passwd,sha256],#2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Operating system description has changed</p><p>- System name has changed</p> |
|has been restarted |<p>The host uptime is less than 10 minutes</p> |`last(/Linux generic by Zabbix agent active/system.uptime)<10m` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

