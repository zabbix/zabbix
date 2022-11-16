
# Linux by Prom

## Overview

For Zabbix version: 6.2 and higher.
This template collects Linux metrics from node_exporter 0.18 and above. Support for older node_exporter versions is provided as 'best effort'.

This template was tested on:

- node_exporter, version 0.17.0
- node_exporter, version 0.18.1

## Setup

Please refer to the node_exporter docs. Use node_exporter v0.18.0 or above.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$IF.ERRORS.WARN} |<p>-</p> |`2` |
|{$IF.UTIL.MAX} |<p>-</p> |`90` |
|{$IFCONTROL} |<p>-</p> |`1` |
|{$KERNEL.MAXFILES.MIN} |<p>-</p> |`256` |
|{$LOAD_AVG_PER_CPU.MAX.WARN} |<p>Load per CPU considered sustainable. Tune if needed.</p> |`1.5` |
|{$MEMORY.AVAILABLE.MIN} |<p>-</p> |`20M` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$NET.IF.IFALIAS.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFALIAS.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFNAME.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default.</p> |`(^Software Loopback Interface|^NULL[0-9.]*$|^[Ll]o[0-9.]*$|^[Ss]ystem$|^Nu[0-9.]*$|^veth[0-9A-z]+$|docker[0-9]+|br-[a-z0-9]{12})` |
|{$NET.IF.IFOPERSTATUS.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES} |<p>Ignore notPresent(7).</p> |`^7$` |
|{$NODE_EXPORTER_PORT} |<p>TCP Port node_exporter is listening on.</p> |`9100` |
|{$SWAP.PFREE.MIN.WARN} |<p>-</p> |`50` |
|{$SYSTEM.FUZZYTIME.MAX} |<p>-</p> |`60` |
|{$VFS.DEV.DEVNAME.MATCHES} |<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level.</p> |`.+` |
|{$VFS.DEV.DEVNAME.NOT_MATCHES} |<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level.</p> |`^(loop[0-9]*|sd[a-z][0-9]+|nbd[0-9]+|sr[0-9]+|fd[0-9]+|dm-[0-9]+|ram[0-9]+|ploop[a-z0-9]+|md[0-9]*|hcp[0-9]*|zram[0-9]*)` |
|{$VFS.DEV.READ.AWAIT.WARN} |<p>Disk read average response time (in ms) before the trigger would fire.</p> |`20` |
|{$VFS.DEV.WRITE.AWAIT.WARN} |<p>Disk write average response time (in ms) before the trigger would fire.</p> |`20` |
|{$VFS.FS.FREE.MIN.CRIT} |<p>The critical threshold of the filesystem utilization.</p> |`5G` |
|{$VFS.FS.FREE.MIN.WARN} |<p>The warning threshold of the filesystem utilization.</p> |`10G` |
|{$VFS.FS.FSDEVICE.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`^.+$` |
|{$VFS.FS.FSDEVICE.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`^\s$` |
|{$VFS.FS.FSNAME.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`.+` |
|{$VFS.FS.FSNAME.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`^(/dev|/sys|/run|/proc|.+/shm$)` |
|{$VFS.FS.FSTYPE.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`^(btrfs|ext2|ext3|ext4|reiser|xfs|ffs|ufs|jfs|jfs2|vxfs|hfs|apfs|refs|ntfs|fat32|zfs)$` |
|{$VFS.FS.FSTYPE.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`^\s$` |
|{$VFS.FS.INODE.PFREE.MIN.CRIT} |<p>-</p> |`10` |
|{$VFS.FS.INODE.PFREE.MIN.WARN} |<p>-</p> |`20` |
|{$VFS.FS.PUSED.MAX.CRIT} |<p>-</p> |`90` |
|{$VFS.FS.PUSED.MAX.WARN} |<p>-</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Block devices discovery |<p>-</p> |DEPENDENT |vfs.dev.discovery[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `node_disk_io_now{device=~".+"}`</p><p>**Filter**:</p>AND <p>- {#DEVNAME} MATCHES_REGEX `{$VFS.DEV.DEVNAME.MATCHES}`</p><p>- {#DEVNAME} NOT_MATCHES_REGEX `{$VFS.DEV.DEVNAME.NOT_MATCHES}`</p> |
|Mounted filesystem discovery |<p>Discovery of file systems of different types.</p> |DEPENDENT |vfs.fs.discovery[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_filesystem_size(?:_bytes)?$", mountpoint=~".+"}`</p><p>**Filter**:</p>AND <p>- {#FSTYPE} MATCHES_REGEX `{$VFS.FS.FSTYPE.MATCHES}`</p><p>- {#FSTYPE} NOT_MATCHES_REGEX `{$VFS.FS.FSTYPE.NOT_MATCHES}`</p><p>- {#FSNAME} MATCHES_REGEX `{$VFS.FS.FSNAME.MATCHES}`</p><p>- {#FSNAME} NOT_MATCHES_REGEX `{$VFS.FS.FSNAME.NOT_MATCHES}`</p><p>- {#FSNAME} MATCHES_REGEX `{$VFS.FS.FSDEVICE.MATCHES}`</p><p>- {#FSDEVICE} NOT_MATCHES_REGEX `{$VFS.FS.FSDEVICE.NOT_MATCHES}`</p> |
|Network interface discovery |<p>Discovery of network interfaces. Requires node_exporter v0.18 and up.</p> |DEPENDENT |net.if.discovery[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_network_info$"}`</p><p>**Filter**:</p>AND <p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |Load average (1m avg) |<p>-</p> |DEPENDENT |system.cpu.load.avg1[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_load1`</p> |
|CPU |Load average (5m avg) |<p>-</p> |DEPENDENT |system.cpu.load.avg5[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_load5`</p> |
|CPU |Load average (15m avg) |<p>-</p> |DEPENDENT |system.cpu.load.avg15[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_load15`</p> |
|CPU |Number of CPUs |<p>-</p> |DEPENDENT |system.cpu.num[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="idle"}`</p><p>- JAVASCRIPT: `//count the number of cores return JSON.parse(value).length `</p> |
|CPU |CPU utilization |<p>The CPU utilization expressed in %.</p> |DEPENDENT |system.cpu.util[node_exporter]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `//Calculate utilization return (100 - value) `</p> |
|CPU |CPU idle time |<p>The time the CPU has spent doing nothing.</p> |DEPENDENT |system.cpu.idle[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="idle"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|CPU |CPU system time |<p>The time the CPU has spent running the kernel and its processes.</p> |DEPENDENT |system.cpu.system[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="system"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|CPU |CPU user time |<p>The time the CPU has spent running users' processes that are not niced.</p> |DEPENDENT |system.cpu.user[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="user"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|CPU |CPU steal time |<p>The amount of 'stolen' CPU from this virtual machine by the hypervisor for other tasks, such as running another virtual machine.</p> |DEPENDENT |system.cpu.steal[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="steal"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|CPU |CPU softirq time |<p>The amount of time the CPU has been servicing software interrupts.</p> |DEPENDENT |system.cpu.softirq[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="softirq"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|CPU |CPU nice time |<p>The time the CPU has spent running users' processes that have been niced.</p> |DEPENDENT |system.cpu.nice[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="nice"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|CPU |CPU iowait time |<p>The amount of time the CPU has been waiting for I/O to complete.</p> |DEPENDENT |system.cpu.iowait[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="iowait"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|CPU |CPU interrupt time |<p>The amount of time the CPU has been servicing hardware interrupts.</p> |DEPENDENT |system.cpu.interrupt[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="irq"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|CPU |CPU guest time |<p>Guest time - the time spent on running a virtual CPU for a guest operating system.</p> |DEPENDENT |system.cpu.guest[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_guest_seconds_total)?$",cpu=~".+",mode=~"^(?:user|guest)$"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|CPU |CPU guest nice time |<p>The time spent on running a niced guest (a virtual CPU for guest operating systems under the control of the Linux kernel).</p> |DEPENDENT |system.cpu.guest_nice[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_guest_seconds_total)?$",cpu=~".+",mode=~"^(?:nice|guest_nice)$"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|CPU |Interrupts per second |<p>-</p> |DEPENDENT |system.cpu.intr[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_intr"}`</p><p>- CHANGE_PER_SECOND</p> |
|CPU |Context switches per second |<p>-</p> |DEPENDENT |system.cpu.switches[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_context_switches"}`</p><p>- CHANGE_PER_SECOND</p> |
|General |System boot time |<p>-</p> |DEPENDENT |system.boottime[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"^node_boot_time(?:_seconds)?$"}`</p> |
|General |System local time |<p>The local system time of the host.</p> |DEPENDENT |system.localtime[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"^node_time(?:_seconds)?$"}`</p> |
|General |System name |<p>The host name of the system.</p> |DEPENDENT |system.name[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_uname_info`: `label`: `nodename`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |System description |<p>Labeled system information as provided by the uname system call.</p> |DEPENDENT |system.descr[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `node_uname_info`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |Maximum number of open file descriptors |<p>It could be increased by using sysctl utility or modifying the file /etc/sysctl.conf.</p> |DEPENDENT |kernel.maxfiles[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_filefd_maximum`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |Number of open file descriptors |<p>-</p> |DEPENDENT |fd.open[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_filefd_allocated`</p> |
|Inventory |Operating system |<p>-</p> |DEPENDENT |system.sw.os[node_exporter]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system architecture |<p>The architecture of the host's operating system.</p> |DEPENDENT |system.sw.arch[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_uname_info`: `label`: `machine`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |Memory utilization |<p>Memory used percentage is calculated as (total-available)/total*100.</p> |CALCULATED |vm.memory.util[node_exporter]<p>**Expression**:</p>`(last(//vm.memory.total[node_exporter])-last(//vm.memory.available[node_exporter]))/last(//vm.memory.total[node_exporter])*100` |
|Memory |Total memory |<p>The total memory expressed in Bytes.</p> |DEPENDENT |vm.memory.total[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_memory_MemTotal"}`</p> |
|Memory |Available memory |<p>The available memory:</p><p> - in Linux - available = free + buffers + cache;</p><p> - on other platforms calculation may vary.</p><p>See also Appendixes in Zabbix Documentation about parameters of the vm.memory.size item.</p> |DEPENDENT |vm.memory.available[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_memory_MemAvailable"}`</p> |
|Memory |Total swap space |<p>The total space of the swap volume/file expressed in bytes.</p> |DEPENDENT |system.swap.total[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_memory_SwapTotal"}`</p> |
|Memory |Free swap space |<p>The free space of the swap volume/file expressed in bytes.</p> |DEPENDENT |system.swap.free[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_memory_SwapFree"}`</p> |
|Memory |Free swap space in % |<p>The free space of the swap volume/file expressed in %.</p> |CALCULATED |system.swap.pfree[node_exporter]<p>**Expression**:</p>`last(//system.swap.free[node_exporter])/last(//system.swap.total[node_exporter])*100` |
|Monitoring agent |Version of node_exporter running |<p>-</p> |DEPENDENT |agent.version[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_exporter_build_info`: `label`: `version`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits received |<p>-</p> |DEPENDENT |net.if.in[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_receive_bytes_total{device="{#IFNAME}"}`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits sent |<p>-</p> |DEPENDENT |net.if.out[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_transmit_bytes_total{device="{#IFNAME}"}`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors |<p>-</p> |DEPENDENT |net.if.out.errors[node_exporter"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_transmit_errs_total{device="{#IFNAME}"}`</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors |<p>-</p> |DEPENDENT |net.if.in.errors[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_receive_errs_total{device="{#IFNAME}"}`</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded |<p>-</p> |DEPENDENT |net.if.in.discards[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_receive_drop_total{device="{#IFNAME}"}`</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded |<p>-</p> |DEPENDENT |net.if.out.discards[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_transmit_drop_total{device="{#IFNAME}"}`</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Speed |<p>Sets value to 0 if metric is missing in node_exporter output.</p> |DEPENDENT |net.if.speed[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_speed_bytes{device="{#IFNAME}"}`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Interface type |<p>node_network_protocol_type protocol_type value of /sys/class/net/<iface>.</p> |DEPENDENT |net.if.type[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_protocol_type{device="{#IFNAME}"}`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Operational status |<p>Reference: https://www.kernel.org/doc/Documentation/networking/operstates.txt</p> |DEPENDENT |net.if.status[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_info{device="{#IFNAME}"}`: `label`: `operstate`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Status |System uptime |<p>The system uptime expressed in the following format:'N days, hh:mm:ss'.</p> |DEPENDENT |system.uptime[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"^node_boot_time(?:_seconds)?$"}`</p><p>- JAVASCRIPT: `//use boottime to calculate uptime return (Math.floor(Date.now()/1000)-Number(value)); `</p> |
|Storage |{#FSNAME}: Free space |<p>-</p> |DEPENDENT |vfs.fs.free[node_exporter,"{#FSNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"^node_filesystem_avail(?:_bytes)?$", mountpoint="{#FSNAME}"}`</p> |
|Storage |{#FSNAME}: Total space |<p>The total space expressed in Bytes.</p> |DEPENDENT |vfs.fs.total[node_exporter,"{#FSNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"^node_filesystem_size(?:_bytes)?$", mountpoint="{#FSNAME}"}`</p> |
|Storage |{#FSNAME}: Used space |<p>Used storage expressed in Bytes</p> |CALCULATED |vfs.fs.used[node_exporter,"{#FSNAME}"]<p>**Expression**:</p>`(last(//vfs.fs.total[node_exporter,"{#FSNAME}"])-last(//vfs.fs.free[node_exporter,"{#FSNAME}"]))` |
|Storage |{#FSNAME}: Space utilization |<p>The space utilization expressed in % for {#FSNAME}.</p> |CALCULATED |vfs.fs.pused[node_exporter,"{#FSNAME}"]<p>**Expression**:</p>`(last(//vfs.fs.used[node_exporter,"{#FSNAME}"])/last(//vfs.fs.total[node_exporter,"{#FSNAME}"]))*100` |
|Storage |{#FSNAME}: Free inodes in % |<p>-</p> |DEPENDENT |vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"node_filesystem_files.*",mountpoint="{#FSNAME}"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Storage |{#DEVNAME}: Disk read rate |<p>r/s. The number (after merges) of read requests completed per second for the device.</p> |DEPENDENT |vfs.dev.read.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_reads_completed_total{device="{#DEVNAME}"}`</p><p>- CHANGE_PER_SECOND</p> |
|Storage |{#DEVNAME}: Disk write rate |<p>w/s. The number (after merges) of write requests completed per second for the device.</p> |DEPENDENT |vfs.dev.write.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_writes_completed_total{device="{#DEVNAME}"}`</p><p>- CHANGE_PER_SECOND</p> |
|Storage |{#DEVNAME}: Disk read request avg waiting time (r_await) |<p>This formula contains two boolean expressions that evaluates to 1 or 0 in order to set calculated metric to zero and to avoid division by zero exception.</p> |CALCULATED |vfs.dev.read.await[node_exporter,"{#DEVNAME}"]<p>**Expression**:</p>`(last(//vfs.dev.read.time.rate[node_exporter,"{#DEVNAME}"])/(last(//vfs.dev.read.rate[node_exporter,"{#DEVNAME}"])+(last(//vfs.dev.read.rate[node_exporter,"{#DEVNAME}"])=0)))*1000*(last(//vfs.dev.read.rate[node_exporter,"{#DEVNAME}"]) > 0)` |
|Storage |{#DEVNAME}: Disk write request avg waiting time (w_await) |<p>This formula contains two boolean expressions that evaluates to 1 or 0 in order to set calculated metric to zero and to avoid division by zero exception.</p> |CALCULATED |vfs.dev.write.await[node_exporter,"{#DEVNAME}"]<p>**Expression**:</p>`(last(//vfs.dev.write.time.rate[node_exporter,"{#DEVNAME}"])/(last(//vfs.dev.write.rate[node_exporter,"{#DEVNAME}"])+(last(//vfs.dev.write.rate[node_exporter,"{#DEVNAME}"])=0)))*1000*(last(//vfs.dev.write.rate[node_exporter,"{#DEVNAME}"]) > 0)` |
|Storage |{#DEVNAME}: Disk average queue size (avgqu-sz) |<p>The current average disk queue; the number of requests outstanding on the disk while the performance data is being collected.</p> |DEPENDENT |vfs.dev.queue_size[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_io_time_weighted_seconds_total{device="{#DEVNAME}"}`</p><p>- CHANGE_PER_SECOND</p> |
|Storage |{#DEVNAME}: Disk utilization |<p>This item is the percentage of elapsed time during which the selected disk drive was busy while servicing read or write requests.</p> |DEPENDENT |vfs.dev.util[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_io_time_seconds_total{device="{#DEVNAME}"}`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|Zabbix raw items |Get node_exporter metrics |<p>-</p> |HTTP_AGENT |node_exporter.get |
|Zabbix raw items |{#DEVNAME}: Disk read time (rate) |<p>Rate of total read time counter. Used in r_await calculation.</p> |DEPENDENT |vfs.dev.read.time.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_read_time_seconds_total{device="{#DEVNAME}"}`</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix raw items |{#DEVNAME}: Disk write time (rate) |<p>Rate of total write time counter. Used in w_await calculation.</p> |DEPENDENT |vfs.dev.write.time.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_write_time_seconds_total{device="{#DEVNAME}"}`</p><p>- CHANGE_PER_SECOND</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Load average is too high |<p>The load average per CPU is too high. The system may be slow to respond.</p> |`min(/Linux by Prom/system.cpu.load.avg1[node_exporter],5m)/last(/Linux by Prom/system.cpu.num[node_exporter])>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/Linux by Prom/system.cpu.load.avg5[node_exporter])>0 and last(/Linux by Prom/system.cpu.load.avg15[node_exporter])>0` |AVERAGE | |
|High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Linux by Prom/system.cpu.util[node_exporter],5m)>{$CPU.UTIL.CRIT}` |WARNING |<p>**Depends on**:</p><p>- Load average is too high</p> |
|System time is out of sync |<p>The host's system time is different from Zabbix server time.</p> |`fuzzytime(/Linux by Prom/system.localtime[node_exporter],{$SYSTEM.FUZZYTIME.MAX})=0` |WARNING |<p>Manual close: YES</p> |
|System name has changed |<p>The name of the system has changed. Ack to close the problem manually.</p> |`last(/Linux by Prom/system.name[node_exporter],#1)<>last(/Linux by Prom/system.name[node_exporter],#2) and length(last(/Linux by Prom/system.name[node_exporter]))>0` |INFO |<p>Manual close: YES</p> |
|Configured max number of open filedescriptors is too low |<p>-</p> |`last(/Linux by Prom/kernel.maxfiles[node_exporter])<{$KERNEL.MAXFILES.MIN}` |INFO |<p>**Depends on**:</p><p>- Running out of file descriptors</p> |
|Running out of file descriptors |<p>-</p> |`last(/Linux by Prom/fd.open[node_exporter])/last(/Linux by Prom/kernel.maxfiles[node_exporter])*100>80` |WARNING | |
|Operating system description has changed |<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Ack to close the problem manually.</p> |`last(/Linux by Prom/system.sw.os[node_exporter],#1)<>last(/Linux by Prom/system.sw.os[node_exporter],#2) and length(last(/Linux by Prom/system.sw.os[node_exporter]))>0` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- System name has changed</p> |
|High memory utilization |<p>The system is running out of free memory.</p> |`min(/Linux by Prom/vm.memory.util[node_exporter],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE |<p>**Depends on**:</p><p>- Lack of available memory</p> |
|Lack of available memory |<p>-</p> |`max(/Linux by Prom/vm.memory.available[node_exporter],5m)<{$MEMORY.AVAILABLE.MIN} and last(/Linux by Prom/vm.memory.total[node_exporter])>0` |AVERAGE | |
|High swap space usage |<p>If there is no swap configured, this trigger is ignored.</p> |`max(/Linux by Prom/system.swap.pfree[node_exporter],5m)<{$SWAP.PFREE.MIN.WARN} and last(/Linux by Prom/system.swap.total[node_exporter])>0` |WARNING |<p>**Depends on**:</p><p>- High memory utilization</p><p>- Lack of available memory</p> |
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage |<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p> |`(avg(/Linux by Prom/net.if.in[node_exporter,"{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"]) or avg(/Linux by Prom/net.if.out[node_exporter,"{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])) and last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])>0`<p>Recovery expression:</p>`avg(/Linux by Prom/net.if.in[node_exporter,"{#IFNAME}"],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"]) and avg(/Linux by Prom/net.if.out[node_exporter,"{#IFNAME}"],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High error rate |<p>It recovers when it is below 80% of the {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`min(/Linux by Prom/net.if.in.errors[node_exporter,"{#IFNAME}"],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Linux by Prom/net.if.out.errors[node_exporter"{#IFNAME}"],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/Linux by Prom/net.if.in.errors[node_exporter,"{#IFNAME}"],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and max(/Linux by Prom/net.if.out.errors[node_exporter"{#IFNAME}"],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge (Ack) to close the problem manually.</p> |`change(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])<0 and last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])>0 and ( last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=6 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=7 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=11 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=62 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=69 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=117 ) and (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])<>2) `<p>Recovery expression:</p>`(change(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])>0 and last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"],#2)>0) or (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])=2) ` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge (Ack) to close the problem manually.</p> |`change(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])<0 and last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])>0 and (last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=6 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=1) and (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])<>2) `<p>Recovery expression:</p>`(change(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])>0 and last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"],#2)>0) or (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])=2) ` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Link down |<p>This trigger expression works as follows:</p><p>1. It can be triggered if the operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])=2 and (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"],#1)<>last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"],#2))`<p>Recovery expression:</p>`last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])<>2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|has been restarted |<p>The device uptime is less than 10 minutes.</p> |`last(/Linux by Prom/system.uptime[node_exporter])<10m` |WARNING |<p>Manual close: YES</p> |
|{#FSNAME}: Disk space is critically low |<p>Two conditions should match:</p><p> 1. The first condition - utilization of space should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.</p><p> 2. The second condition should be one of the following:</p><p>  - the disk free space is less than {$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"};</p><p>  - the disk will be full in less than 24 hours.</p> |`last(/Linux by Prom/vfs.fs.pused[node_exporter,"{#FSNAME}"])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/Linux by Prom/vfs.fs.total[node_exporter,"{#FSNAME}"])-last(/Linux by Prom/vfs.fs.used[node_exporter,"{#FSNAME}"]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/Linux by Prom/vfs.fs.pused[node_exporter,"{#FSNAME}"],1h,100)<1d) ` |AVERAGE |<p>Manual close: YES</p> |
|{#FSNAME}: Disk space is low |<p>Two conditions should match:</p><p> 1. The first condition - utilization of space should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.</p><p> 2. The second condition should be one of the following:</p><p>  - the disk free space is less than {$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"};</p><p>  - the disk will be full in less than 24 hours.</p> |`last(/Linux by Prom/vfs.fs.pused[node_exporter,"{#FSNAME}"])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/Linux by Prom/vfs.fs.total[node_exporter,"{#FSNAME}"])-last(/Linux by Prom/vfs.fs.used[node_exporter,"{#FSNAME}"]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/Linux by Prom/vfs.fs.pused[node_exporter,"{#FSNAME}"],1h,100)<1d) ` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#FSNAME}: Disk space is critically low</p> |
|{#FSNAME}: Running out of free inodes |<p>It may become impossible to write to a disk if there are no index nodes left.</p><p>Following error messages may be returned as symptoms, even though the free space is available:</p><p> - 'No space left on device';</p><p> - 'Disk is full'.</p> |`min(/Linux by Prom/vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"],5m)<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}` |AVERAGE | |
|{#FSNAME}: Running out of free inodes |<p>It may become impossible to write to a disk if there are no index nodes left.</p><p>Following error messages may be returned as symptoms, even though the free space is available:</p><p> - 'No space left on device';</p><p> - 'Disk is full'.</p> |`min(/Linux by Prom/vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"],5m)<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}` |WARNING |<p>**Depends on**:</p><p>- {#FSNAME}: Running out of free inodes</p> |
|{#DEVNAME}: Disk read/write request responses are too high |<p>This trigger might indicate the disk {#DEVNAME} saturation.</p> |`min(/Linux by Prom/vfs.dev.read.await[node_exporter,"{#DEVNAME}"],15m) > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} or min(/Linux by Prom/vfs.dev.write.await[node_exporter,"{#DEVNAME}"],15m) > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}` |WARNING |<p>Manual close: YES</p> |
|node_exporter is not available |<p>Failed to fetch system metrics from node_exporter in time.</p> |`nodata(/Linux by Prom/node_exporter.get,30m)=1` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/387225-discussion-thread-for-official-zabbix-template-for-linux).

## Known Issues

- Description: node_exporter v0.16.0 renamed many metrics. CPU utilization for 'guest' and 'guest_nice' metrics are not supported in this template with node_exporter < 0.16. Disk IO metrics are not supported. Other metrics provided as 'best effort'.
See https://github.com/prometheus/node_exporter/releases/tag/v0.16.0 for details.
  - Version: below 0.16.0

- Description: metric node_network_info with label 'device' cannot be found, so network discovery is not possible.
  - Version: below 0.18


## References

https://github.com/prometheus/node_exporter
