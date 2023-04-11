
# Linux by Zabbix agent active

## Overview

New official Linux template. Requires agent of Zabbix 3.0.14, 3.4.5 and 4.0.0 or newer.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on Linux OS according to Zabbix documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.NODATA_TIMEOUT}|<p>No data timeout for active agents. Consider to keep it relatively high.</p>|`30m`|
|{$CPU.UTIL.CRIT}||`90`|
|{$LOAD_AVG_PER_CPU.MAX.WARN}|<p>Load per CPU considered sustainable. Tune if needed.</p>|`1.5`|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p>|`^(/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VFS.FS.FSNAME.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p>|`.+`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p>|`Macro too long. Please see the template.`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p>|`^\s$`|
|{$VFS.FS.FREE.MIN.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`5G`|
|{$VFS.FS.FREE.MIN.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`10G`|
|{$VFS.FS.INODE.PFREE.MIN.CRIT}||`10`|
|{$VFS.FS.INODE.PFREE.MIN.WARN}||`20`|
|{$VFS.FS.PUSED.MAX.CRIT}||`90`|
|{$VFS.FS.PUSED.MAX.WARN}||`80`|
|{$MEMORY.UTIL.MAX}|<p>This macro is used as a threshold in memory utilization trigger.</p>|`90`|
|{$MEMORY.AVAILABLE.MIN}|<p>This macro is used as a threshold in memory available trigger.</p>|`20M`|
|{$SWAP.PFREE.MIN.WARN}||`50`|
|{$VFS.DEV.READ.AWAIT.WARN}|<p>Disk read average response time (in ms) before the trigger would fire</p>|`20`|
|{$VFS.DEV.WRITE.AWAIT.WARN}|<p>Disk write average response time (in ms) before the trigger would fire</p>|`20`|
|{$VFS.DEV.DEVNAME.NOT_MATCHES}|<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p>|`Macro too long. Please see the template.`|
|{$VFS.DEV.DEVNAME.MATCHES}|<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p>|`.+`|
|{$IF.ERRORS.WARN}||`2`|
|{$IFCONTROL}||`1`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p>|`Macro too long. Please see the template.`|
|{$IF.UTIL.MAX}|<p>This macro is used as a threshold in interface utilization trigger.</p>|`90`|
|{$SYSTEM.FUZZYTIME.MAX}||`60`|
|{$KERNEL.MAXPROC.MIN}||`1024`|
|{$KERNEL.MAXFILES.MIN}||`256`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Linux: Version of Zabbix agent running| |Zabbix agent (active)|agent.version<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Linux: Host name of Zabbix agent running| |Zabbix agent (active)|agent.hostname<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Linux: Zabbix agent ping|<p>The agent always returns 1 for this item. It could be used in combination with nodata() for availability check.</p>|Zabbix agent (active)|agent.ping|
|Linux: Number of CPUs| |Zabbix agent (active)|system.cpu.num<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Linux: Load average (1m avg)| |Zabbix agent (active)|system.cpu.load[all,avg1]|
|Linux: Load average (5m avg)| |Zabbix agent (active)|system.cpu.load[all,avg5]|
|Linux: Load average (15m avg)| |Zabbix agent (active)|system.cpu.load[all,avg15]|
|Linux: CPU utilization|<p>CPU utilization in %.</p>|Dependent item|system.cpu.util<p>**Preprocessing**</p><ul><li>JavaScript: `//Calculate utilization<br>return (100 - value)`</li></ul>|
|Linux: CPU idle time|<p>The time the CPU has spent doing nothing.</p>|Zabbix agent (active)|system.cpu.util[,idle]|
|Linux: CPU system time|<p>The time the CPU has spent running the kernel and its processes.</p>|Zabbix agent (active)|system.cpu.util[,system]|
|Linux: CPU user time|<p>The time the CPU has spent running users' processes that are not niced.</p>|Zabbix agent (active)|system.cpu.util[,user]|
|Linux: CPU nice time|<p>The time the CPU has spent running users' processes that have been niced.</p>|Zabbix agent (active)|system.cpu.util[,nice]|
|Linux: CPU iowait time|<p>Amount of time the CPU has been waiting for I/O to complete.</p>|Zabbix agent (active)|system.cpu.util[,iowait]|
|Linux: CPU steal time|<p>The amount of CPU 'stolen' from this virtual machine by the hypervisor for other tasks (such as running another virtual machine).</p>|Zabbix agent (active)|system.cpu.util[,steal]|
|Linux: CPU interrupt time|<p>The amount of time the CPU has been servicing hardware interrupts.</p>|Zabbix agent (active)|system.cpu.util[,interrupt]|
|Linux: CPU softirq time|<p>The amount of time the CPU has been servicing software interrupts.</p>|Zabbix agent (active)|system.cpu.util[,softirq]|
|Linux: CPU guest time|<p>Guest  time (time  spent  running  a  virtual  CPU  for  a  guest  operating  system).</p>|Zabbix agent (active)|system.cpu.util[,guest]|
|Linux: CPU guest nice time|<p>Time spent running a niced guest (virtual CPU for guest operating systems under the control of the Linux kernel).</p>|Zabbix agent (active)|system.cpu.util[,guest_nice]|
|Linux: Context switches per second| |Zabbix agent (active)|system.cpu.switches<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Linux: Interrupts per second| |Zabbix agent (active)|system.cpu.intr<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Linux: Memory utilization|<p>Memory used percentage is calculated as (100-pavailable)</p>|Dependent item|vm.memory.utilization<p>**Preprocessing**</p><ul><li>JavaScript: `return (100-value);`</li></ul>|
|Linux: Available memory in %|<p>Available memory as percentage of total. See also Appendixes in Zabbix Documentation about parameters of the vm.memory.size item.</p>|Zabbix agent (active)|vm.memory.size[pavailable]|
|Linux: Total memory|<p>Total memory in Bytes.</p>|Zabbix agent (active)|vm.memory.size[total]|
|Linux: Available memory|<p>Available memory, in Linux, available = free + buffers + cache. On other platforms calculation may vary. See also Appendixes in Zabbix Documentation about parameters of the vm.memory.size item.</p>|Zabbix agent (active)|vm.memory.size[available]|
|Linux: Total swap space|<p>The total space of swap volume/file in bytes.</p>|Zabbix agent (active)|system.swap.size[,total]|
|Linux: Free swap space|<p>The free space of swap volume/file in bytes.</p>|Zabbix agent (active)|system.swap.size[,free]|
|Linux: Free swap space in %|<p>The free space of swap volume/file in percent.</p>|Zabbix agent (active)|system.swap.size[,pfree]|
|Linux: System uptime|<p>System uptime in 'N days, hh:mm:ss' format.</p>|Zabbix agent (active)|system.uptime|
|Linux: System boot time| |Zabbix agent (active)|system.boottime<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|Linux: System local time|<p>System local time of the host.</p>|Zabbix agent (active)|system.localtime|
|Linux: System name|<p>System host name.</p>|Zabbix agent (active)|system.hostname<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `12h`</li></ul>|
|Linux: System description|<p>The information as normally returned by 'uname -a'.</p>|Zabbix agent (active)|system.uname<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `12h`</li></ul>|
|Linux: Number of logged in users|<p>Number of users who are currently logged in.</p>|Zabbix agent (active)|system.users.num|
|Linux: Maximum number of open file descriptors|<p>It could be increased by using sysctl utility or modifying file /etc/sysctl.conf.</p>|Zabbix agent (active)|kernel.maxfiles<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Linux: Maximum number of processes|<p>It could be increased by using sysctl utility or modifying file /etc/sysctl.conf.</p>|Zabbix agent (active)|kernel.maxproc<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Linux: Number of processes| |Zabbix agent (active)|proc.num|
|Linux: Number of running processes| |Zabbix agent (active)|proc.num[,,run]|
|Linux: Checksum of /etc/passwd| |Zabbix agent (active)|vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|Linux: Operating system| |Zabbix agent (active)|system.sw.os<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Linux: Operating system architecture|<p>Operating system architecture of the host.</p>|Zabbix agent (active)|system.sw.arch<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Linux: Software installed| |Zabbix agent (active)|system.sw.packages<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: Zabbix agent is not available|<p>For active agents, nodata() with agent.ping is used with {$AGENT.NODATA_TIMEOUT} as time threshold.</p>|`nodata(/Linux by Zabbix agent active/agent.ping,{$AGENT.NODATA_TIMEOUT})=1`|Average|**Manual close**: Yes|
|Linux: Load average is too high|<p>Per CPU load average is too high. Your system may be slow to respond.</p>|`min(/Linux by Zabbix agent active/system.cpu.load[all,avg1],5m)/last(/Linux by Zabbix agent active/system.cpu.num)>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/Linux by Zabbix agent active/system.cpu.load[all,avg5])>0 and last(/Linux by Zabbix agent active/system.cpu.load[all,avg15])>0`|Average||
|Linux: High CPU utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Linux by Zabbix agent active/system.cpu.util,5m)>{$CPU.UTIL.CRIT}`|Warning|**Depends on**:<br><ul><li>Linux: Load average is too high</li></ul>|
|Linux: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Linux by Zabbix agent active/vm.memory.utilization,5m)>{$MEMORY.UTIL.MAX}`|Average|**Depends on**:<br><ul><li>Linux: Lack of available memory</li></ul>|
|Linux: Lack of available memory||`max(/Linux by Zabbix agent active/vm.memory.size[available],5m)<{$MEMORY.AVAILABLE.MIN} and last(/Linux by Zabbix agent active/vm.memory.size[total])>0`|Average||
|Linux: High swap space usage|<p>This trigger is ignored, if there is no swap configured.</p>|`max(/Linux by Zabbix agent active/system.swap.size[,pfree],5m)<{$SWAP.PFREE.MIN.WARN} and last(/Linux by Zabbix agent active/system.swap.size[,total])>0`|Warning|**Depends on**:<br><ul><li>Linux: Lack of available memory</li><li>Linux: High memory utilization</li></ul>|
|Linux: {HOST.NAME} has been restarted|<p>The host uptime is less than 10 minutes.</p>|`last(/Linux by Zabbix agent active/system.uptime)<10m`|Warning|**Manual close**: Yes|
|Linux: System time is out of sync|<p>The host system time is different from the Zabbix server time.</p>|`fuzzytime(/Linux by Zabbix agent active/system.localtime,{$SYSTEM.FUZZYTIME.MAX})=0`|Warning|**Manual close**: Yes|
|Linux: System name has changed|<p>System name has changed. Ack to close.</p>|`last(/Linux by Zabbix agent active/system.hostname,#1)<>last(/Linux by Zabbix agent active/system.hostname,#2) and length(last(/Linux by Zabbix agent active/system.hostname))>0`|Info|**Manual close**: Yes|
|Linux: Configured max number of open filedescriptors is too low||`last(/Linux by Zabbix agent active/kernel.maxfiles)<{$KERNEL.MAXFILES.MIN}`|Info||
|Linux: Configured max number of processes is too low||`last(/Linux by Zabbix agent active/kernel.maxproc)<{$KERNEL.MAXPROC.MIN}`|Info|**Depends on**:<br><ul><li>Linux: Getting closer to process limit</li></ul>|
|Linux: Getting closer to process limit||`last(/Linux by Zabbix agent active/proc.num)/last(/Linux by Zabbix agent active/kernel.maxproc)*100>80`|Warning||
|Linux: /etc/passwd has been changed||`last(/Linux by Zabbix agent active/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/Linux by Zabbix agent active/vfs.file.cksum[/etc/passwd,sha256],#2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: System name has changed</li><li>Linux: Operating system description has changed</li></ul>|
|Linux: Operating system description has changed|<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p>|`last(/Linux by Zabbix agent active/system.sw.os,#1)<>last(/Linux by Zabbix agent active/system.sw.os,#2) and length(last(/Linux by Zabbix agent active/system.sw.os))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: System name has changed</li></ul>|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>Discovery of file systems of different types.</p>|Zabbix agent (active)|vfs.fs.discovery|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FSNAME}: Used space|<p>Used storage in Bytes</p>|Zabbix agent (active)|vfs.fs.size[{#FSNAME},used]|
|{#FSNAME}: Total space|<p>Total space in Bytes</p>|Zabbix agent (active)|vfs.fs.size[{#FSNAME},total]|
|{#FSNAME}: Space utilization|<p>Space utilization in % for {#FSNAME}</p>|Zabbix agent (active)|vfs.fs.size[{#FSNAME},pused]|
|{#FSNAME}: Free inodes in %| |Zabbix agent (active)|vfs.fs.inode[{#FSNAME},pfree]|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSNAME}: Disk space is critically low|<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}. Second condition should be one of the following: - The disk free space is less than {$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}. - The disk will be full in less than 24 hours.</p>|`last(/Linux by Zabbix agent active/vfs.fs.size[{#FSNAME},pused])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/Linux by Zabbix agent active/vfs.fs.size[{#FSNAME},total])-last(/Linux by Zabbix agent active/vfs.fs.size[{#FSNAME},used]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/Linux by Zabbix agent active/vfs.fs.size[{#FSNAME},pused],1h,100)<1d)`|Average|**Manual close**: Yes|
|{#FSNAME}: Disk space is low|<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}. Second condition should be one of the following: - The disk free space is less than {$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}. - The disk will be full in less than 24 hours.</p>|`last(/Linux by Zabbix agent active/vfs.fs.size[{#FSNAME},pused])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/Linux by Zabbix agent active/vfs.fs.size[{#FSNAME},total])-last(/Linux by Zabbix agent active/vfs.fs.size[{#FSNAME},used]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/Linux by Zabbix agent active/vfs.fs.size[{#FSNAME},pused],1h,100)<1d)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#FSNAME}: Disk space is critically low</li></ul>|
|{#FSNAME}: Running out of free inodes|<p>It may become impossible to write to disk if there are no index nodes left.As symptoms, 'No space left on device' or 'Disk is full' errors may be seen even though free space is available.</p>|`min(/Linux by Zabbix agent active/vfs.fs.inode[{#FSNAME},pfree],5m)<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}`|Average||
|{#FSNAME}: Running out of free inodes|<p>It may become impossible to write to disk if there are no index nodes left.As symptoms, 'No space left on device' or 'Disk is full' errors may be seen even though free space is available.</p>|`min(/Linux by Zabbix agent active/vfs.fs.inode[{#FSNAME},pfree],5m)<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}`|Warning|**Depends on**:<br><ul><li>{#FSNAME}: Running out of free inodes</li></ul>|

### LLD rule Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Block devices discovery| |Zabbix agent (active)|vfs.dev.discovery<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1h`</li></ul>|

### Item prototypes for Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DEVNAME}: Get stats|<p>Get contents of /sys/block/{#DEVNAME}/stat for disk stats.</p>|Zabbix agent (active)|vfs.file.contents[/sys/block/{#DEVNAME}/stat]<p>**Preprocessing**</p><ul><li>JavaScript: `return JSON.stringify(value.trim().split(/ +/));`</li></ul>|
|{#DEVNAME}: Disk read rate|<p>r/s. The number (after merges) of read requests completed per second for the device.</p>|Dependent item|vfs.dev.read.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li>JSON Path: `$[0]`</li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk write rate|<p>w/s. The number (after merges) of write requests completed per second for the device.</p>|Dependent item|vfs.dev.write.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li>JSON Path: `$[4]`</li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk read time (rate)|<p>Rate of total read time counter. Used in r_await calculation</p>|Dependent item|vfs.dev.read.time.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li>JSON Path: `$[3]`</li><li>Change per second</li><li>Custom multiplier: `0.001`</li></ul>|
|{#DEVNAME}: Disk write time (rate)|<p>Rate of total write time counter. Used in w_await calculation</p>|Dependent item|vfs.dev.write.time.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li>JSON Path: `$[7]`</li><li>Change per second</li><li>Custom multiplier: `0.001`</li></ul>|
|{#DEVNAME}: Disk read request avg waiting time (r_await)|<p>This formula contains two boolean expressions that evaluates to 1 or 0 in order to set calculated metric to zero and to avoid division by zero exception.</p>|Calculated|vfs.dev.read.await[{#DEVNAME}]|
|{#DEVNAME}: Disk write request avg waiting time (w_await)|<p>This formula contains two boolean expressions that evaluates to 1 or 0 in order to set calculated metric to zero and to avoid division by zero exception.</p>|Calculated|vfs.dev.write.await[{#DEVNAME}]|
|{#DEVNAME}: Disk average queue size (avgqu-sz)|<p>Current average disk queue, the number of requests outstanding on the disk at the time the performance data is collected.</p>|Dependent item|vfs.dev.queue_size[{#DEVNAME}]<p>**Preprocessing**</p><ul><li>JSON Path: `$[10]`</li><li>Change per second</li><li>Custom multiplier: `0.001`</li></ul>|
|{#DEVNAME}: Disk utilization|<p>This item is the percentage of elapsed time that the selected disk drive was busy servicing read or writes requests.</p>|Dependent item|vfs.dev.util[{#DEVNAME}]<p>**Preprocessing**</p><ul><li>JSON Path: `$[9]`</li><li>Change per second</li><li>Custom multiplier: `0.1`</li></ul>|

### Trigger prototypes for Block devices discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#DEVNAME}: Disk read/write request responses are too high|<p>This trigger might indicate disk {#DEVNAME} saturation.</p>|`min(/Linux by Zabbix agent active/vfs.dev.read.await[{#DEVNAME}],15m) > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} or min(/Linux by Zabbix agent active/vfs.dev.write.await[{#DEVNAME}],15m) > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}`|Warning|**Manual close**: Yes|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of network interfaces.</p>|Zabbix agent (active)|net.if.discovery|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}: Bits received| |Zabbix agent (active)|net.if.in["{#IFNAME}"]<p>**Preprocessing**</p><ul><li>Change per second</li><li>Custom multiplier: `8`</li></ul>|
|Interface {#IFNAME}: Bits sent| |Zabbix agent (active)|net.if.out["{#IFNAME}"]<p>**Preprocessing**</p><ul><li>Change per second</li><li>Custom multiplier: `8`</li></ul>|
|Interface {#IFNAME}: Outbound packets with errors| |Zabbix agent (active)|net.if.out["{#IFNAME}",errors]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}: Inbound packets with errors| |Zabbix agent (active)|net.if.in["{#IFNAME}",errors]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}: Outbound packets discarded| |Zabbix agent (active)|net.if.out["{#IFNAME}",dropped]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}: Inbound packets discarded| |Zabbix agent (active)|net.if.in["{#IFNAME}",dropped]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}: Operational status|<p>Reference: https://www.kernel.org/doc/Documentation/networking/operstates.txt</p>|Zabbix agent (active)|vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"]<p>**Preprocessing**</p><ul><li>JavaScript: `The text is too long. Please see the template.`</li></ul>|
|Interface {#IFNAME}: Interface type|<p>Indicates the interface protocol type as a decimal value.</p><p>See include/uapi/linux/if_arp.h for all possible values.</p><p>Reference: https://www.kernel.org/doc/Documentation/ABI/testing/sysfs-class-net</p>|Zabbix agent (active)|vfs.file.contents["/sys/class/net/{#IFNAME}/type"]<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Interface {#IFNAME}: Speed|<p>Indicates the interface latest or current speed value. Value is an integer representing the link speed in bits/sec.</p><p>This attribute is only valid for interfaces that implement the ethtool get_link_ksettings method (mostly Ethernet).</p><p></p><p>Reference: https://www.kernel.org/doc/Documentation/ABI/testing/sysfs-class-net</p>|Zabbix agent (active)|vfs.file.contents["/sys/class/net/{#IFNAME}/speed"]<p>**Preprocessing**</p><ul><li>Custom multiplier: `1000000`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}: High bandwidth usage|<p>The network interface utilization is close to its estimated maximum bandwidth.</p>|`(avg(/Linux by Zabbix agent active/net.if.in["{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"]) or avg(/Linux by Zabbix agent active/net.if.out["{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])) and last(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}: Link down</li></ul>|
|Interface {#IFNAME}: High error rate|<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold.</p>|`min(/Linux by Zabbix agent active/net.if.in["{#IFNAME}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Linux by Zabbix agent active/net.if.out["{#IFNAME}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}: Link down</li></ul>|
|Interface {#IFNAME}: Link down|<p>This trigger expression works as follows:1. Can be triggered if operations status is down.2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.3. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)WARNING: if closed manually - won't fire again on next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"])=2 and (last(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"],#1)<>last(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"],#2))`|Average|**Manual close**: Yes|
|Interface {#IFNAME}: Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p>|`change(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])<0 and last(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/speed"])>0 and (last(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/type"])=6 or last(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/type"])=1) and (last(/Linux by Zabbix agent active/vfs.file.contents["/sys/class/net/{#IFNAME}/operstate"])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}: Link down</li></ul>|

## Feedback

Please report any issues with the template at `https://support.zabbix.com`.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
