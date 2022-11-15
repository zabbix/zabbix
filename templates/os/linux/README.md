
# Template Module Linux CPU by Zabbix agent

## Overview

For Zabbix version: 5.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$LOAD_AVG_PER_CPU.MAX.WARN} |<p>Load per CPU considered sustainable. Tune if needed.</p> |`1.5` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |Number of CPUs |<p>-</p> |ZABBIX_PASSIVE |system.cpu.num<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|CPU |Load average (1m avg) |<p>-</p> |ZABBIX_PASSIVE |system.cpu.load[all,avg1] |
|CPU |Load average (5m avg) |<p>-</p> |ZABBIX_PASSIVE |system.cpu.load[all,avg5] |
|CPU |Load average (15m avg) |<p>-</p> |ZABBIX_PASSIVE |system.cpu.load[all,avg15] |
|CPU |CPU utilization |<p>CPU utilization in %.</p> |DEPENDENT |system.cpu.util<p>**Preprocessing**:</p><p>- JAVASCRIPT: `//Calculate utilization return (100 - value)`</p> |
|CPU |CPU idle time |<p>The time the CPU has spent doing nothing.</p> |ZABBIX_PASSIVE |system.cpu.util[,idle] |
|CPU |CPU system time |<p>The time the CPU has spent running the kernel and its processes.</p> |ZABBIX_PASSIVE |system.cpu.util[,system] |
|CPU |CPU user time |<p>The time the CPU has spent running users' processes that are not niced.</p> |ZABBIX_PASSIVE |system.cpu.util[,user] |
|CPU |CPU nice time |<p>The time the CPU has spent running users' processes that have been niced.</p> |ZABBIX_PASSIVE |system.cpu.util[,nice] |
|CPU |CPU iowait time |<p>Amount of time the CPU has been waiting for I/O to complete.</p> |ZABBIX_PASSIVE |system.cpu.util[,iowait] |
|CPU |CPU steal time |<p>The amount of CPU 'stolen' from this virtual machine by the hypervisor for other tasks (such as running another virtual machine).</p> |ZABBIX_PASSIVE |system.cpu.util[,steal] |
|CPU |CPU interrupt time |<p>The amount of time the CPU has been servicing hardware interrupts.</p> |ZABBIX_PASSIVE |system.cpu.util[,interrupt] |
|CPU |CPU softirq time |<p>The amount of time the CPU has been servicing software interrupts.</p> |ZABBIX_PASSIVE |system.cpu.util[,softirq] |
|CPU |CPU guest time |<p>Guest  time (time  spent  running  a  virtual  CPU  for  a  guest  operating  system).</p> |ZABBIX_PASSIVE |system.cpu.util[,guest] |
|CPU |CPU guest nice time |<p>Time spent running a niced guest (virtual CPU for guest operating systems under the control of the Linux kernel).</p> |ZABBIX_PASSIVE |system.cpu.util[,guest_nice] |
|CPU |Context switches per second |<p>-</p> |ZABBIX_PASSIVE |system.cpu.switches<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|CPU |Interrupts per second |<p>-</p> |ZABBIX_PASSIVE |system.cpu.intr<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Load average is too high (per CPU load over {$LOAD_AVG_PER_CPU.MAX.WARN} for 5m) |<p>Per CPU load average is too high. Your system may be slow to respond.</p> |`{TEMPLATE_NAME:system.cpu.load[all,avg1].min(5m)}/{TEMPLATE_NAME:system.cpu.num.last()}>{$LOAD_AVG_PER_CPU.MAX.WARN} and {TEMPLATE_NAME:system.cpu.load[all,avg5].last()}>0 and {TEMPLATE_NAME:system.cpu.load[all,avg15].last()}>0` |AVERAGE | |
|High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`{TEMPLATE_NAME:system.cpu.util.min(5m)}>{$CPU.UTIL.CRIT}` |WARNING |<p>**Depends on**:</p><p>- Load average is too high (per CPU load over {$LOAD_AVG_PER_CPU.MAX.WARN} for 5m)</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Template Module Linux filesystems by Zabbix agent

## Overview

For Zabbix version: 5.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.FS.FREE.MIN.CRIT} |<p>The critical threshold of the filesystem utilization.</p> |`5G` |
|{$VFS.FS.FREE.MIN.WARN} |<p>The warning threshold of the filesystem utilization.</p> |`10G` |
|{$VFS.FS.FSNAME.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p> |`.+` |
|{$VFS.FS.FSNAME.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p> |`^(/dev|/sys|/run|/proc|.+/shm$)` |
|{$VFS.FS.FSTYPE.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p> |`^(btrfs|ext2|ext3|ext4|reiser|xfs|ffs|ufs|jfs|jfs2|vxfs|hfs|apfs|refs|ntfs|fat32|zfs)$` |
|{$VFS.FS.FSTYPE.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p> |`^\s$` |
|{$VFS.FS.INODE.PFREE.MIN.CRIT} |<p>-</p> |`10` |
|{$VFS.FS.INODE.PFREE.MIN.WARN} |<p>-</p> |`20` |
|{$VFS.FS.PUSED.MAX.CRIT} |<p>-</p> |`90` |
|{$VFS.FS.PUSED.MAX.WARN} |<p>-</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Mounted filesystem discovery |<p>Discovery of file systems of different types.</p> |ZABBIX_PASSIVE |vfs.fs.discovery<p>**Filter**:</p>AND <p>- A: {#FSTYPE} MATCHES_REGEX `{$VFS.FS.FSTYPE.MATCHES}`</p><p>- B: {#FSTYPE} NOT_MATCHES_REGEX `{$VFS.FS.FSTYPE.NOT_MATCHES}`</p><p>- C: {#FSNAME} MATCHES_REGEX `{$VFS.FS.FSNAME.MATCHES}`</p><p>- D: {#FSNAME} NOT_MATCHES_REGEX `{$VFS.FS.FSNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Filesystems |{#FSNAME}: Used space |<p>Used storage in Bytes</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},used] |
|Filesystems |{#FSNAME}: Total space |<p>Total space in Bytes</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},total] |
|Filesystems |{#FSNAME}: Space utilization |<p>Space utilization in % for {#FSNAME}</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},pused] |
|Filesystems |{#FSNAME}: Free inodes in % |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.inode[{#FSNAME},pfree] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#FSNAME}: Disk space is critically low (used > {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}%) |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`{TEMPLATE_NAME:vfs.fs.size[{#FSNAME},pused].last()}>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and (({TEMPLATE_NAME:vfs.fs.size[{#FSNAME},total].last()}-{TEMPLATE_NAME:vfs.fs.size[{#FSNAME},used].last()})<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or {TEMPLATE_NAME:vfs.fs.size[{#FSNAME},pused].timeleft(1h,,100)}<1d)` |AVERAGE |<p>Manual close: YES</p> |
|{#FSNAME}: Disk space is low (used > {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}%) |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`{TEMPLATE_NAME:vfs.fs.size[{#FSNAME},pused].last()}>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and (({TEMPLATE_NAME:vfs.fs.size[{#FSNAME},total].last()}-{TEMPLATE_NAME:vfs.fs.size[{#FSNAME},used].last()})<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or {TEMPLATE_NAME:vfs.fs.size[{#FSNAME},pused].timeleft(1h,,100)}<1d)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#FSNAME}: Disk space is critically low (used > {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}%)</p> |
|{#FSNAME}: Running out of free inodes (free < {$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}%) |<p>It may become impossible to write to disk if there are no index nodes left.</p><p>As symptoms, 'No space left on device' or 'Disk is full' errors may be seen even though free space is available.</p> |`{TEMPLATE_NAME:vfs.fs.inode[{#FSNAME},pfree].min(5m)}<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}` |AVERAGE | |
|{#FSNAME}: Running out of free inodes (free < {$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}%) |<p>It may become impossible to write to disk if there are no index nodes left.</p><p>As symptoms, 'No space left on device' or 'Disk is full' errors may be seen even though free space is available.</p> |`{TEMPLATE_NAME:vfs.fs.inode[{#FSNAME},pfree].min(5m)}<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}` |WARNING |<p>**Depends on**:</p><p>- {#FSNAME}: Running out of free inodes (free < {$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}%)</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Template Module Linux memory by Zabbix agent

## Overview

For Zabbix version: 5.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMORY.AVAILABLE.MIN} |<p>This macro is used as a threshold in memory available trigger.</p> |`20M` |
|{$MEMORY.UTIL.MAX} |<p>This macro is used as a threshold in memory utilization trigger.</p> |`90` |
|{$SWAP.PFREE.MIN.WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Memory |Memory utilization |<p>Memory used percentage is calculated as (100-pavailable)</p> |DEPENDENT |vm.memory.utilization<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return (100-value);`</p> |
|Memory |Available memory in % |<p>Available memory as percentage of total. See also Appendixes in Zabbix Documentation about parameters of the vm.memory.size item.</p> |ZABBIX_PASSIVE |vm.memory.size[pavailable] |
|Memory |Total memory |<p>Total memory in Bytes.</p> |ZABBIX_PASSIVE |vm.memory.size[total] |
|Memory |Available memory |<p>Available memory, in Linux, available = free + buffers + cache. On other platforms calculation may vary. See also Appendixes in Zabbix Documentation about parameters of the vm.memory.size item.</p> |ZABBIX_PASSIVE |vm.memory.size[available] |
|Memory |Total swap space |<p>The total space of swap volume/file in bytes.</p> |ZABBIX_PASSIVE |system.swap.size[,total] |
|Memory |Free swap space |<p>The free space of swap volume/file in bytes.</p> |ZABBIX_PASSIVE |system.swap.size[,free] |
|Memory |Free swap space in % |<p>The free space of swap volume/file in percent.</p> |ZABBIX_PASSIVE |system.swap.size[,pfree] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`{TEMPLATE_NAME:vm.memory.utilization.min(5m)}>{$MEMORY.UTIL.MAX}` |AVERAGE |<p>**Depends on**:</p><p>- Lack of available memory (<{$MEMORY.AVAILABLE.MIN} of {ITEM.VALUE2})</p> |
|Lack of available memory (<{$MEMORY.AVAILABLE.MIN} of {ITEM.VALUE2}) |<p>-</p> |`{TEMPLATE_NAME:vm.memory.size[available].max(5m)}<{$MEMORY.AVAILABLE.MIN} and {TEMPLATE_NAME:vm.memory.size[total].last()}>0` |AVERAGE | |
|High swap space usage (less than {$SWAP.PFREE.MIN.WARN}% free) |<p>This trigger is ignored, if there is no swap configured.</p> |`{TEMPLATE_NAME:system.swap.size[,pfree].max(5m)}<{$SWAP.PFREE.MIN.WARN} and {TEMPLATE_NAME:system.swap.size[,total].last()}>0` |WARNING |<p>**Depends on**:</p><p>- High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m)</p><p>- Lack of available memory (<{$MEMORY.AVAILABLE.MIN} of {ITEM.VALUE2})</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Template Module Linux block devices by Zabbix agent

## Overview

For Zabbix version: 5.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.DEV.DEVNAME.MATCHES} |<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p> |`.+` |
|{$VFS.DEV.DEVNAME.NOT_MATCHES} |<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p> |`^(loop[0-9]*|sd[a-z][0-9]+|nbd[0-9]+|sr[0-9]+|fd[0-9]+|dm-[0-9]+|ram[0-9]+|ploop[a-z0-9]+|md[0-9]*|hcp[0-9]*|zram[0-9]*)` |
|{$VFS.DEV.READ.AWAIT.WARN} |<p>Disk read average response time (in ms) before the trigger would fire</p> |`20` |
|{$VFS.DEV.WRITE.AWAIT.WARN} |<p>Disk write average response time (in ms) before the trigger would fire</p> |`20` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Block devices discovery |<p>-</p> |ZABBIX_PASSIVE |vfs.dev.discovery<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- A: {#DEVTYPE} MATCHES_REGEX `disk`</p><p>- B: {#DEVNAME} MATCHES_REGEX `{$VFS.DEV.DEVNAME.MATCHES}`</p><p>- C: {#DEVNAME} NOT_MATCHES_REGEX `{$VFS.DEV.DEVNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Storage |{#DEVNAME}: Disk read rate |<p>r/s. The number (after merges) of read requests completed per second for the device.</p> |DEPENDENT |vfs.dev.read.rate[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[0]`</p><p>- CHANGE_PER_SECOND |
|Storage |{#DEVNAME}: Disk write rate |<p>w/s. The number (after merges) of write requests completed per second for the device.</p> |DEPENDENT |vfs.dev.write.rate[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[4]`</p><p>- CHANGE_PER_SECOND |
|Storage |{#DEVNAME}: Disk read request avg waiting time (r_await) |<p>This formula contains two boolean expressions that evaluates to 1 or 0 in order to set calculated metric to zero and to avoid division by zero exception.</p> |CALCULATED |vfs.dev.read.await[{#DEVNAME}]<p>**Expression**:</p>`(last("vfs.dev.read.time.rate[{#DEVNAME}]")/(last("vfs.dev.read.rate[{#DEVNAME}]")+(last("vfs.dev.read.rate[{#DEVNAME}]")=0)))*1000*(last("vfs.dev.read.rate[{#DEVNAME}]") > 0)` |
|Storage |{#DEVNAME}: Disk write request avg waiting time (w_await) |<p>This formula contains two boolean expressions that evaluates to 1 or 0 in order to set calculated metric to zero and to avoid division by zero exception.</p> |CALCULATED |vfs.dev.write.await[{#DEVNAME}]<p>**Expression**:</p>`(last("vfs.dev.write.time.rate[{#DEVNAME}]")/(last("vfs.dev.write.rate[{#DEVNAME}]")+(last("vfs.dev.write.rate[{#DEVNAME}]")=0)))*1000*(last("vfs.dev.write.rate[{#DEVNAME}]") > 0)` |
|Storage |{#DEVNAME}: Disk average queue size (avgqu-sz) |<p>Current average disk queue, the number of requests outstanding on the disk at the time the performance data is collected.</p> |DEPENDENT |vfs.dev.queue_size[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[10]`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `0.001`</p> |
|Storage |{#DEVNAME}: Disk utilization |<p>This item is the percentage of elapsed time that the selected disk drive was busy servicing read or writes requests.</p> |DEPENDENT |vfs.dev.util[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[9]`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `0.1`</p> |
|Zabbix_raw_items |{#DEVNAME}: Get stats |<p>Get contents of /sys/block/{#DEVNAME}/stat for disk stats.</p> |ZABBIX_PASSIVE |vfs.file.contents[/sys/block/{#DEVNAME}/stat]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(value.trim().split(/ +/));`</p> |
|Zabbix_raw_items |{#DEVNAME}: Disk read time (rate) |<p>Rate of total read time counter. Used in r_await calculation</p> |DEPENDENT |vfs.dev.read.time.rate[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[3]`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `0.001`</p> |
|Zabbix_raw_items |{#DEVNAME}: Disk write time (rate) |<p>Rate of total write time counter. Used in w_await calculation</p> |DEPENDENT |vfs.dev.write.time.rate[{#DEVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[7]`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `0.001`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#DEVNAME}: Disk read/write request responses are too high (read > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} ms for 15m or write > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"} ms for 15m) |<p>This trigger might indicate disk {#DEVNAME} saturation.</p> |`{TEMPLATE_NAME:vfs.dev.read.await[{#DEVNAME}].min(15m)} > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} or {TEMPLATE_NAME:vfs.dev.write.await[{#DEVNAME}].min(15m)} > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Template Module Linux network interfaces by Zabbix agent

## Overview

For Zabbix version: 5.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IF.ERRORS.WARN} |<p>-</p> |`2` |
|{$IF.UTIL.MAX} |<p>This macro is used as a threshold in interface utilization trigger.</p> |`90` |
|{$IFCONTROL} |<p>-</p> |`1` |
|{$NET.IF.IFNAME.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p> |`(^Software Loopback Interface|^NULL[0-9.]*$|^[Ll]o[0-9.]*$|^[Ss]ystem$|^Nu[0-9.]*$|^veth[0-9A-z]+$|docker[0-9]+|br-[a-z0-9]{12})` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Network interface discovery |<p>Discovery of network interfaces.</p> |ZABBIX_PASSIVE |net.if.discovery<p>**Filter**:</p>AND <p>- A: {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- B: {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Network_interfaces |Interface {#IFNAME}: Bits received | |ZABBIX_PASSIVE |net.if.in["{#IFNAME}"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `8`</p> |
|Network_interfaces |Interface {#IFNAME}: Bits sent | |ZABBIX_PASSIVE |net.if.out["{#IFNAME}"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `8`</p> |
|Network_interfaces |Interface {#IFNAME}: Outbound packets with errors | |ZABBIX_PASSIVE |net.if.out["{#IFNAME}",errors]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Network_interfaces |Interface {#IFNAME}: Inbound packets with errors | |ZABBIX_PASSIVE |net.if.in["{#IFNAME}",errors]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Network_interfaces |Interface {#IFNAME}: Outbound packets discarded | |ZABBIX_PASSIVE |net.if.out["{#IFNAME}",dropped]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Network_interfaces |Interface {#IFNAME}: Inbound packets discarded | |ZABBIX_PASSIVE |net.if.in["{#IFNAME}",dropped]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Network_interfaces |Interface {#IFNAME}: Operational status |<p>Reference: https://www.kernel.org/doc/Documentation/networking/operstates.txt</p> |ZABBIX_PASSIVE |vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Network_interfaces |Interface {#IFNAME}: Interface type |<p>Indicates the interface protocol type as a decimal value.</p><p>See include/uapi/linux/if_arp.h for all possible values.</p><p>Reference: https://www.kernel.org/doc/Documentation/ABI/testing/sysfs-class-net</p> |ZABBIX_PASSIVE |vfs.file.contents["/sys/class/net/{#IFNAME}/type"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network_interfaces |Interface {#IFNAME}: Speed |<p>Indicates the interface latest or current speed value. Value is an integer representing the link speed in bits/sec.</p><p>This attribute is only valid for interfaces that implement the ethtool get_link_ksettings method (mostly Ethernet).</p><p>Reference: https://www.kernel.org/doc/Documentation/ABI/testing/sysfs-class-net</p> |ZABBIX_PASSIVE |vfs.file.contents["/sys/class/net/{#IFNAME}/speed"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Interface {#IFNAME}: High bandwidth usage (>{$IF.UTIL.MAX:"{#IFNAME}"}%) |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`({TEMPLATE_NAME:net.if.in["{#IFNAME}"].avg(15m)}>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*{TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/speed"].last()} or {TEMPLATE_NAME:net.if.out["{#IFNAME}"].avg(15m)}>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*{TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/speed"].last()}) and {TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/speed"].last()}>0`<p>Recovery expression:</p>`{TEMPLATE_NAME:net.if.in["{#IFNAME}"].avg(15m)}<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*{TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/speed"].last()} and {TEMPLATE_NAME:net.if.out["{#IFNAME}"].avg(15m)}<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*{TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/speed"].last()}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}: Link down</p> |
|Interface {#IFNAME}: High error rate (>{$IF.ERRORS.WARN:"{#IFNAME}"} for 5m) |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`{TEMPLATE_NAME:net.if.in["{#IFNAME}",errors].min(5m)}>{$IF.ERRORS.WARN:"{#IFNAME}"} or {TEMPLATE_NAME:net.if.out["{#IFNAME}",errors].min(5m)}>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:net.if.in["{#IFNAME}",errors].max(5m)}<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and {TEMPLATE_NAME:net.if.out["{#IFNAME}",errors].max(5m)}<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}: Link down</p> |
|Interface {#IFNAME}: Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and ({TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"].last()}=2 and {TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"].diff()}=1)`<p>Recovery expression:</p>`{TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"].last()}<>2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|Interface {#IFNAME}: Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`{TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/speed"].change()}<0 and {TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/speed"].last()}>0 and ({TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/type"].last()}=6 or {TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/type"].last()}=1) and ({TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"].last()}<>2)`<p>Recovery expression:</p>`({TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/speed"].change()}>0 and {TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/speed"].prev()}>0) or ({TEMPLATE_NAME:vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"].last()}=2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}: Link down</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Template Module Linux generic by Zabbix agent

## Overview

For Zabbix version: 5.0 and higher  

## Setup

Refer to the vendor documentation.

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
|General |System boot time |<p>-</p> |ZABBIX_PASSIVE |system.boottime<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|General |System local time |<p>System local time of the host.</p> |ZABBIX_PASSIVE |system.localtime |
|General |System name |<p>System host name.</p> |ZABBIX_PASSIVE |system.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>The information as normally returned by 'uname -a'.</p> |ZABBIX_PASSIVE |system.uname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |Number of logged in users |<p>Number of users who are currently logged in.</p> |ZABBIX_PASSIVE |system.users.num |
|General |Maximum number of open file descriptors |<p>It could be increased by using sysctl utility or modifying file /etc/sysctl.conf.</p> |ZABBIX_PASSIVE |kernel.maxfiles<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |Maximum number of processes |<p>It could be increased by using sysctl utility or modifying file /etc/sysctl.conf.</p> |ZABBIX_PASSIVE |kernel.maxproc<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |Number of processes |<p>-</p> |ZABBIX_PASSIVE |proc.num |
|General |Number of running processes |<p>-</p> |ZABBIX_PASSIVE |proc.num[,,run] |
|Inventory |Operating system |<p>-</p> |ZABBIX_PASSIVE |system.sw.os<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system architecture |<p>Operating system architecture of the host.</p> |ZABBIX_PASSIVE |system.sw.arch<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Software installed |<p>-</p> |ZABBIX_PASSIVE |system.sw.packages<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Security |Checksum of /etc/passwd |<p>-</p> |ZABBIX_PASSIVE |vfs.file.cksum[/etc/passwd]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |System uptime |<p>System uptime in 'N days, hh:mm:ss' format.</p> |ZABBIX_PASSIVE |system.uptime |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|System time is out of sync (diff with Zabbix server > {$SYSTEM.FUZZYTIME.MAX}s) |<p>The host system time is different from the Zabbix server time.</p> |`{TEMPLATE_NAME:system.localtime.fuzzytime({$SYSTEM.FUZZYTIME.MAX})}=0` |WARNING |<p>Manual close: YES</p> |
|System name has changed (new name: {ITEM.VALUE}) |<p>System name has changed. Ack to close.</p> |`{TEMPLATE_NAME:system.hostname.diff()}=1 and {TEMPLATE_NAME:system.hostname.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Configured max number of open filedescriptors is too low (< {$KERNEL.MAXFILES.MIN}) |<p>-</p> |`{TEMPLATE_NAME:kernel.maxfiles.last()}<{$KERNEL.MAXFILES.MIN}` |INFO | |
|Configured max number of processes is too low (< {$KERNEL.MAXPROC.MIN}) |<p>-</p> |`{TEMPLATE_NAME:kernel.maxproc.last()}<{$KERNEL.MAXPROC.MIN}` |INFO |<p>**Depends on**:</p><p>- Getting closer to process limit (over 80% used)</p> |
|Getting closer to process limit (over 80% used) |<p>-</p> |`{TEMPLATE_NAME:proc.num.last()}/{TEMPLATE_NAME:kernel.maxproc.last()}*100>80` |WARNING | |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`{TEMPLATE_NAME:system.sw.os.diff()}=1 and {TEMPLATE_NAME:system.sw.os.strlen()}>0` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- System name has changed (new name: {ITEM.VALUE})</p> |
|/etc/passwd has been changed |<p>-</p> |`{TEMPLATE_NAME:vfs.file.cksum[/etc/passwd].diff()}>0` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Operating system description has changed</p><p>- System name has changed (new name: {ITEM.VALUE})</p> |
|{HOST.NAME} has been restarted (uptime < 10m) |<p>The host uptime is less than 10 minutes</p> |`{TEMPLATE_NAME:system.uptime.last()}<10m` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Template OS Linux by Zabbix agent

## Overview

For Zabbix version: 5.0 and higher  
New official Linux template. Requires agent of Zabbix 3.0.14, 3.4.5 and 4.0.0 or newer.

## Setup

Install Zabbix agent on Linux OS according to Zabbix documentation.

## Zabbix configuration

No specific Zabbix configuration is required.


## Template links

|Name|
|----|
|Linux CPU by Zabbix agent |
|Linux block devices by Zabbix agent |
|Linux filesystems by Zabbix agent |
|Linux generic by Zabbix agent |
|Linux memory by Zabbix agent |
|Linux network interfaces by Zabbix agent |
|Zabbix agent |

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/387225-discussion-thread-for-official-zabbix-template-for-linux).

## Known Issues

- Description: Network discovery. Zabbix agent as of 4.2 doesn't support items such as net.if.status, net.if.speed.

