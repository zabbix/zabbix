
# Template OS Linux by Prom

## Overview

For Zabbix version: 5.0 and higher  
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
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p> |`(^Software Loopback Interface|^NULL[0-9.]*$|^[Ll]o[0-9.]*$|^[Ss]ystem$|^Nu[0-9.]*$|^veth[0-9A-z]+$|docker[0-9]+|br-[a-z0-9]{12})` |
|{$NET.IF.IFOPERSTATUS.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES} |<p>Ignore notPresent(7)</p> |`^7$` |
|{$NODE_EXPORTER_PORT} |<p>TCP Port node_exporter is listening on.</p> |`9100` |
|{$SWAP.PFREE.MIN.WARN} |<p>-</p> |`50` |
|{$SYSTEM.FUZZYTIME.MAX} |<p>-</p> |`60` |
|{$VFS.DEV.DEVNAME.MATCHES} |<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p> |`.+` |
|{$VFS.DEV.DEVNAME.NOT_MATCHES} |<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p> |`^(loop[0-9]*|sd[a-z][0-9]+|nbd[0-9]+|sr[0-9]+|fd[0-9]+|dm-[0-9]+|ram[0-9]+|ploop[a-z0-9]+|md[0-9]*|hcp[0-9]*|zram[0-9]*)` |
|{$VFS.DEV.READ.AWAIT.WARN} |<p>Disk read average response time (in ms) before the trigger would fire</p> |`20` |
|{$VFS.DEV.WRITE.AWAIT.WARN} |<p>Disk write average response time (in ms) before the trigger would fire</p> |`20` |
|{$VFS.FS.FREE.MIN.CRIT} |<p>The critical threshold of the filesystem utilization.</p> |`5G` |
|{$VFS.FS.FREE.MIN.WARN} |<p>The warning threshold of the filesystem utilization.</p> |`10G` |
|{$VFS.FS.FSDEVICE.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p> |`^.+$` |
|{$VFS.FS.FSDEVICE.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p> |`^\s$` |
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
|Network interface discovery |<p>Discovery of network interfaces. Requires node_exporter v0.18 and up.</p> |DEPENDENT |net.if.discovery[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_network_info$"}`</p><p>**Filter**:</p>AND <p>- A: {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- B: {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- C: {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- D: {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- E: {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- F: {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p> |
|Mounted filesystem discovery |<p>Discovery of file systems of different types.</p> |DEPENDENT |vfs.fs.discovery[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_filesystem_size(?:_bytes)?$", mountpoint=~".+"}`</p><p>**Filter**:</p>AND <p>- A: {#FSTYPE} MATCHES_REGEX `{$VFS.FS.FSTYPE.MATCHES}`</p><p>- B: {#FSTYPE} NOT_MATCHES_REGEX `{$VFS.FS.FSTYPE.NOT_MATCHES}`</p><p>- C: {#FSNAME} MATCHES_REGEX `{$VFS.FS.FSNAME.MATCHES}`</p><p>- D: {#FSNAME} NOT_MATCHES_REGEX `{$VFS.FS.FSNAME.NOT_MATCHES}`</p><p>- E: {#FSNAME} MATCHES_REGEX `{$VFS.FS.FSDEVICE.MATCHES}`</p><p>- F: {#FSDEVICE} NOT_MATCHES_REGEX `{$VFS.FS.FSDEVICE.NOT_MATCHES}`</p> |
|Block devices discovery |<p>-</p> |DEPENDENT |vfs.dev.discovery[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `node_disk_io_now{device=~".+"}`</p><p>**Filter**:</p>AND <p>- A: {#DEVNAME} MATCHES_REGEX `{$VFS.DEV.DEVNAME.MATCHES}`</p><p>- B: {#DEVNAME} NOT_MATCHES_REGEX `{$VFS.DEV.DEVNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |Load average (1m avg) |<p>-</p> |DEPENDENT |system.cpu.load.avg1[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_load1 `</p> |
|CPU |Load average (5m avg) |<p>-</p> |DEPENDENT |system.cpu.load.avg5[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_load5 `</p> |
|CPU |Load average (15m avg) |<p>-</p> |DEPENDENT |system.cpu.load.avg15[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_load15 `</p> |
|CPU |Number of CPUs |<p>-</p> |DEPENDENT |system.cpu.num[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="idle"}`</p><p>- JAVASCRIPT: `//count the number of cores return JSON.parse(value).length `</p> |
|CPU |CPU utilization |<p>CPU utilization in %.</p> |DEPENDENT |system.cpu.util[node_exporter]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `//Calculate utilization return (100 - value)`</p> |
|CPU |CPU idle time |<p>The time the CPU has spent doing nothing.</p> |DEPENDENT |system.cpu.idle[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="idle"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|CPU |CPU system time |<p>The time the CPU has spent running the kernel and its processes.</p> |DEPENDENT |system.cpu.system[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="system"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|CPU |CPU user time |<p>The time the CPU has spent running users' processes that are not niced.</p> |DEPENDENT |system.cpu.user[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="user"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|CPU |CPU steal time |<p>The amount of CPU 'stolen' from this virtual machine by the hypervisor for other tasks (such as running another virtual machine).</p> |DEPENDENT |system.cpu.steal[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="steal"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|CPU |CPU softirq time |<p>The amount of time the CPU has been servicing software interrupts.</p> |DEPENDENT |system.cpu.softirq[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="softirq"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|CPU |CPU nice time |<p>The time the CPU has spent running users' processes that have been niced.</p> |DEPENDENT |system.cpu.nice[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="nice"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|CPU |CPU iowait time |<p>Amount of time the CPU has been waiting for I/O to complete.</p> |DEPENDENT |system.cpu.iowait[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="iowait"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|CPU |CPU interrupt time |<p>The amount of time the CPU has been servicing hardware interrupts.</p> |DEPENDENT |system.cpu.interrupt[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_seconds_total)?$",cpu=~".+",mode="irq"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|CPU |CPU guest time |<p>Guest  time (time  spent  running  a  virtual  CPU  for  a  guest  operating  system).</p> |DEPENDENT |system.cpu.guest[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_guest_seconds_total)?$",cpu=~".+",mode=~"^(?:user|guest)$"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|CPU |CPU guest nice time |<p>Time spent running a niced guest (virtual CPU for guest operating systems under the control of the Linux kernel).</p> |DEPENDENT |system.cpu.guest_nice[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"^node_cpu(?:_guest_seconds_total)?$",cpu=~".+",mode=~"^(?:nice|guest_nice)$"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|CPU |Interrupts per second |<p>-</p> |DEPENDENT |system.cpu.intr[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_intr"} `</p><p>- CHANGE_PER_SECOND |
|CPU |Context switches per second |<p>-</p> |DEPENDENT |system.cpu.switches[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_context_switches"} `</p><p>- CHANGE_PER_SECOND |
|General |System boot time |<p>-</p> |DEPENDENT |system.boottime[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"^node_boot_time(?:_seconds)?$"} `</p> |
|General |System local time |<p>System local time of the host.</p> |DEPENDENT |system.localtime[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"^node_time(?:_seconds)?$"} `</p> |
|General |System name |<p>System host name.</p> |DEPENDENT |system.name[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_uname_info nodename`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |System description |<p>Labeled system information as provided by the uname system call.</p> |DEPENDENT |system.descr[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `node_uname_info`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |Maximum number of open file descriptors |<p>It could be increased by using sysctl utility or modifying file /etc/sysctl.conf.</p> |DEPENDENT |kernel.maxfiles[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_filefd_maximum `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |Number of open file descriptors |<p>-</p> |DEPENDENT |fd.open[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_filefd_allocated `</p> |
|Inventory |Operating system |<p>-</p> |DEPENDENT |system.sw.os[node_exporter]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system architecture |<p>Operating system architecture of the host.</p> |DEPENDENT |system.sw.arch[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_uname_info machine`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |Memory utilization |<p>Memory used percentage is calculated as (total-available)/total*100</p> |CALCULATED |vm.memory.util[node_exporter]<p>**Expression**:</p>`(last("vm.memory.total[node_exporter]")-last("vm.memory.available[node_exporter]"))/last("vm.memory.total[node_exporter]")*100` |
|Memory |Total memory |<p>Total memory in Bytes.</p> |DEPENDENT |vm.memory.total[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_memory_MemTotal"} `</p> |
|Memory |Available memory |<p>Available memory, in Linux, available = free + buffers + cache. On other platforms calculation may vary. See also Appendixes in Zabbix Documentation about parameters of the vm.memory.size item.</p> |DEPENDENT |vm.memory.available[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_memory_MemAvailable"} `</p> |
|Memory |Total swap space |<p>The total space of swap volume/file in bytes.</p> |DEPENDENT |system.swap.total[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_memory_SwapTotal"} `</p> |
|Memory |Free swap space |<p>The free space of swap volume/file in bytes.</p> |DEPENDENT |system.swap.free[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"node_memory_SwapFree"} `</p> |
|Memory |Free swap space in % |<p>The free space of swap volume/file in percent.</p> |CALCULATED |system.swap.pfree[node_exporter]<p>**Expression**:</p>`last("system.swap.free[node_exporter]")/last("system.swap.total[node_exporter]")*100` |
|Monitoring_agent |Version of node_exporter running |<p>-</p> |DEPENDENT |agent.version[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_exporter_build_info version`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Bits received | |DEPENDENT |net.if.in[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_receive_bytes_total{device="{#IFNAME}"} `</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `8`</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Bits sent | |DEPENDENT |net.if.out[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_transmit_bytes_total{device="{#IFNAME}"} `</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `8`</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors | |DEPENDENT |net.if.out.errors[node_exporter"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_transmit_errs_total{device="{#IFNAME}"} `</p><p>- CHANGE_PER_SECOND |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors | |DEPENDENT |net.if.in.errors[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_receive_errs_total{device="{#IFNAME}"} `</p><p>- CHANGE_PER_SECOND |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded | |DEPENDENT |net.if.in.discards[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_receive_drop_total{device="{#IFNAME}"} `</p><p>- CHANGE_PER_SECOND |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded | |DEPENDENT |net.if.out.discards[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_transmit_drop_total{device="{#IFNAME}"} `</p><p>- CHANGE_PER_SECOND |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Speed |<p>Sets value to 0 if metric is missing in node_exporter output.</p> |DEPENDENT |net.if.speed[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_speed_bytes{device="{#IFNAME}"} `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `8`</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Interface type |<p>node_network_protocol_type protocol_type value of /sys/class/net/<iface>.</p> |DEPENDENT |net.if.type[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_protocol_type{device="{#IFNAME}"} `</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Operational status |<p>Reference: https://www.kernel.org/doc/Documentation/networking/operstates.txt</p> |DEPENDENT |net.if.status[node_exporter,"{#IFNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_network_info{device="{#IFNAME}"} operstate`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Status |System uptime |<p>System uptime in 'N days, hh:mm:ss' format.</p> |DEPENDENT |system.uptime[node_exporter]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"^node_boot_time(?:_seconds)?$"} `</p><p>- JAVASCRIPT: `//use boottime to calculate uptime return (Math.floor(Date.now()/1000)-Number(value));`</p> |
|Storage |{#FSNAME}: Free space |<p>-</p> |DEPENDENT |vfs.fs.free[node_exporter,"{#FSNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"^node_filesystem_avail(?:_bytes)?$", mountpoint="{#FSNAME}"} `</p> |
|Storage |{#FSNAME}: Total space |<p>Total space in Bytes</p> |DEPENDENT |vfs.fs.total[node_exporter,"{#FSNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `{__name__=~"^node_filesystem_size(?:_bytes)?$", mountpoint="{#FSNAME}"} `</p> |
|Storage |{#FSNAME}: Used space |<p>Used storage in Bytes</p> |CALCULATED |vfs.fs.used[node_exporter,"{#FSNAME}"]<p>**Expression**:</p>`(last("vfs.fs.total[node_exporter,\"{#FSNAME}\"]")-last("vfs.fs.free[node_exporter,\"{#FSNAME}\"]"))` |
|Storage |{#FSNAME}: Space utilization |<p>Space utilization in % for {#FSNAME}</p> |CALCULATED |vfs.fs.pused[node_exporter,"{#FSNAME}"]<p>**Expression**:</p>`(last("vfs.fs.used[node_exporter,\"{#FSNAME}\"]")/last("vfs.fs.total[node_exporter,\"{#FSNAME}\"]"))*100` |
|Storage |{#FSNAME}: Free inodes in % |<p>-</p> |DEPENDENT |vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~"node_filesystem_files.*",mountpoint="{#FSNAME}"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Storage |{#DEVNAME}: Disk read rate |<p>r/s. The number (after merges) of read requests completed per second for the device.</p> |DEPENDENT |vfs.dev.read.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_reads_completed_total{device="{#DEVNAME}"} `</p><p>- CHANGE_PER_SECOND |
|Storage |{#DEVNAME}: Disk write rate |<p>w/s. The number (after merges) of write requests completed per second for the device.</p> |DEPENDENT |vfs.dev.write.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_writes_completed_total{device="{#DEVNAME}"} `</p><p>- CHANGE_PER_SECOND |
|Storage |{#DEVNAME}: Disk read request avg waiting time (r_await) |<p>This formula contains two boolean expressions that evaluates to 1 or 0 in order to set calculated metric to zero and to avoid division by zero exception.</p> |CALCULATED |vfs.dev.read.await[node_exporter,"{#DEVNAME}"]<p>**Expression**:</p>`(last("vfs.dev.read.time.rate[node_exporter,\"{#DEVNAME}\"]")/(last("vfs.dev.read.rate[node_exporter,\"{#DEVNAME}\"]")+(last("vfs.dev.read.rate[node_exporter,\"{#DEVNAME}\"]")=0)))*1000*(last("vfs.dev.read.rate[node_exporter,\"{#DEVNAME}\"]") > 0)` |
|Storage |{#DEVNAME}: Disk write request avg waiting time (w_await) |<p>This formula contains two boolean expressions that evaluates to 1 or 0 in order to set calculated metric to zero and to avoid division by zero exception.</p> |CALCULATED |vfs.dev.write.await[node_exporter,"{#DEVNAME}"]<p>**Expression**:</p>`(last("vfs.dev.write.time.rate[node_exporter,\"{#DEVNAME}\"]")/(last("vfs.dev.write.rate[node_exporter,\"{#DEVNAME}\"]")+(last("vfs.dev.write.rate[node_exporter,\"{#DEVNAME}\"]")=0)))*1000*(last("vfs.dev.write.rate[node_exporter,\"{#DEVNAME}\"]") > 0)` |
|Storage |{#DEVNAME}: Disk average queue size (avgqu-sz) |<p>Current average disk queue, the number of requests outstanding on the disk at the time the performance data is collected.</p> |DEPENDENT |vfs.dev.queue_size[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_io_time_weighted_seconds_total{device="{#DEVNAME}"} `</p><p>- CHANGE_PER_SECOND |
|Storage |{#DEVNAME}: Disk utilization |<p>This item is the percentage of elapsed time that the selected disk drive was busy servicing read or writes requests.</p> |DEPENDENT |vfs.dev.util[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_io_time_seconds_total{device="{#DEVNAME}"} `</p><p>- CHANGE_PER_SECOND<p>- MULTIPLIER: `100`</p> |
|Zabbix_raw_items |Get node_exporter metrics |<p>-</p> |HTTP_AGENT |node_exporter.get |
|Zabbix_raw_items |{#DEVNAME}: Disk read time (rate) |<p>Rate of total read time counter. Used in r_await calculation</p> |DEPENDENT |vfs.dev.read.time.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_read_time_seconds_total{device="{#DEVNAME}"} `</p><p>- CHANGE_PER_SECOND |
|Zabbix_raw_items |{#DEVNAME}: Disk write time (rate) |<p>Rate of total write time counter. Used in w_await calculation</p> |DEPENDENT |vfs.dev.write.time.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `node_disk_write_time_seconds_total{device="{#DEVNAME}"} `</p><p>- CHANGE_PER_SECOND |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Load average is too high (per CPU load over {$LOAD_AVG_PER_CPU.MAX.WARN} for 5m) |<p>Per CPU load average is too high. Your system may be slow to respond.</p> |`{TEMPLATE_NAME:system.cpu.load.avg1[node_exporter].min(5m)}/{TEMPLATE_NAME:system.cpu.num[node_exporter].last()}>{$LOAD_AVG_PER_CPU.MAX.WARN} and {TEMPLATE_NAME:system.cpu.load.avg5[node_exporter].last()}>0 and {TEMPLATE_NAME:system.cpu.load.avg15[node_exporter].last()}>0` |AVERAGE | |
|High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`{TEMPLATE_NAME:system.cpu.util[node_exporter].min(5m)}>{$CPU.UTIL.CRIT}` |WARNING |<p>**Depends on**:</p><p>- Load average is too high (per CPU load over {$LOAD_AVG_PER_CPU.MAX.WARN} for 5m)</p> |
|System time is out of sync (diff with Zabbix server > {$SYSTEM.FUZZYTIME.MAX}s) |<p>The host system time is different from the Zabbix server time.</p> |`{TEMPLATE_NAME:system.localtime[node_exporter].fuzzytime({$SYSTEM.FUZZYTIME.MAX})}=0` |WARNING |<p>Manual close: YES</p> |
|System name has changed (new name: {ITEM.VALUE}) |<p>System name has changed. Ack to close.</p> |`{TEMPLATE_NAME:system.name[node_exporter].diff()}=1 and {TEMPLATE_NAME:system.name[node_exporter].strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Configured max number of open filedescriptors is too low (< {$KERNEL.MAXFILES.MIN}) |<p>-</p> |`{TEMPLATE_NAME:kernel.maxfiles[node_exporter].last()}<{$KERNEL.MAXFILES.MIN}` |INFO |<p>**Depends on**:</p><p>- Running out of file descriptors (less than < 20% free)</p> |
|Running out of file descriptors (less than < 20% free) |<p>-</p> |`{TEMPLATE_NAME:fd.open[node_exporter].last()}/{TEMPLATE_NAME:kernel.maxfiles[node_exporter].last()}*100>80` |WARNING | |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`{TEMPLATE_NAME:system.sw.os[node_exporter].diff()}=1 and {TEMPLATE_NAME:system.sw.os[node_exporter].strlen()}>0` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- System name has changed (new name: {ITEM.VALUE})</p> |
|High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`{TEMPLATE_NAME:vm.memory.util[node_exporter].min(5m)}>{$MEMORY.UTIL.MAX}` |AVERAGE |<p>**Depends on**:</p><p>- Lack of available memory (<{$MEMORY.AVAILABLE.MIN} of {ITEM.VALUE2})</p> |
|Lack of available memory (<{$MEMORY.AVAILABLE.MIN} of {ITEM.VALUE2}) |<p>-</p> |`{TEMPLATE_NAME:vm.memory.available[node_exporter].max(5m)}<{$MEMORY.AVAILABLE.MIN} and {TEMPLATE_NAME:vm.memory.total[node_exporter].last()}>0` |AVERAGE | |
|High swap space usage (less than {$SWAP.PFREE.MIN.WARN}% free) |<p>This trigger is ignored, if there is no swap configured.</p> |`{TEMPLATE_NAME:system.swap.pfree[node_exporter].max(5m)}<{$SWAP.PFREE.MIN.WARN} and {TEMPLATE_NAME:system.swap.total[node_exporter].last()}>0` |WARNING |<p>**Depends on**:</p><p>- High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m)</p><p>- Lack of available memory (<{$MEMORY.AVAILABLE.MIN} of {ITEM.VALUE2})</p> |
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage (>{$IF.UTIL.MAX:"{#IFNAME}"}%) |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`({TEMPLATE_NAME:net.if.in[node_exporter,"{#IFNAME}"].avg(15m)}>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*{TEMPLATE_NAME:net.if.speed[node_exporter,"{#IFNAME}"].last()} or {TEMPLATE_NAME:net.if.out[node_exporter,"{#IFNAME}"].avg(15m)}>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*{TEMPLATE_NAME:net.if.speed[node_exporter,"{#IFNAME}"].last()}) and {TEMPLATE_NAME:net.if.speed[node_exporter,"{#IFNAME}"].last()}>0`<p>Recovery expression:</p>`{TEMPLATE_NAME:net.if.in[node_exporter,"{#IFNAME}"].avg(15m)}<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*{TEMPLATE_NAME:net.if.speed[node_exporter,"{#IFNAME}"].last()} and {TEMPLATE_NAME:net.if.out[node_exporter,"{#IFNAME}"].avg(15m)}<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*{TEMPLATE_NAME:net.if.speed[node_exporter,"{#IFNAME}"].last()}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High error rate (>{$IF.ERRORS.WARN:"{#IFNAME}"} for 5m) |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`{TEMPLATE_NAME:net.if.in.errors[node_exporter,"{#IFNAME}"].min(5m)}>{$IF.ERRORS.WARN:"{#IFNAME}"} or {TEMPLATE_NAME:net.if.out.errors[node_exporter"{#IFNAME}"].min(5m)}>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:net.if.in.errors[node_exporter,"{#IFNAME}"].max(5m)}<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and {TEMPLATE_NAME:net.if.out.errors[node_exporter"{#IFNAME}"].max(5m)}<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`{TEMPLATE_NAME:net.if.speed[node_exporter,"{#IFNAME}"].change()}<0 and {TEMPLATE_NAME:net.if.speed[node_exporter,"{#IFNAME}"].last()}>0 and ( {TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].last()}=6 or {TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].last()}=7 or {TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].last()}=11 or {TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].last()}=62 or {TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].last()}=69 or {TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].last()}=117 ) and ({TEMPLATE_NAME:net.if.status[node_exporter,"{#IFNAME}"].last()}<>2)`<p>Recovery expression:</p>`({TEMPLATE_NAME:net.if.speed[node_exporter,"{#IFNAME}"].change()}>0 and {TEMPLATE_NAME:net.if.speed[node_exporter,"{#IFNAME}"].prev()}>0) or ({TEMPLATE_NAME:net.if.status[node_exporter,"{#IFNAME}"].last()}=2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`{TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].change()}<0 and {TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].last()}>0 and ({TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].last()}=6 or {TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].last()}=1) and ({TEMPLATE_NAME:net.if.status[node_exporter,"{#IFNAME}"].last()}<>2)`<p>Recovery expression:</p>`({TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].change()}>0 and {TEMPLATE_NAME:net.if.type[node_exporter,"{#IFNAME}"].prev()}>0) or ({TEMPLATE_NAME:net.if.status[node_exporter,"{#IFNAME}"].last()}=2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and ({TEMPLATE_NAME:net.if.status[node_exporter,"{#IFNAME}"].last()}=2 and {TEMPLATE_NAME:net.if.status[node_exporter,"{#IFNAME}"].diff()}=1)`<p>Recovery expression:</p>`{TEMPLATE_NAME:net.if.status[node_exporter,"{#IFNAME}"].last()}<>2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|{HOST.NAME} has been restarted (uptime < 10m) |<p>The device uptime is less than 10 minutes</p> |`{TEMPLATE_NAME:system.uptime[node_exporter].last()}<10m` |WARNING |<p>Manual close: YES</p> |
|{#FSNAME}: Disk space is critically low (used > {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}%) |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`{TEMPLATE_NAME:vfs.fs.pused[node_exporter,"{#FSNAME}"].last()}>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and (({TEMPLATE_NAME:vfs.fs.total[node_exporter,"{#FSNAME}"].last()}-{TEMPLATE_NAME:vfs.fs.used[node_exporter,"{#FSNAME}"].last()})<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or {TEMPLATE_NAME:vfs.fs.pused[node_exporter,"{#FSNAME}"].timeleft(1h,,100)}<1d)` |AVERAGE |<p>Manual close: YES</p> |
|{#FSNAME}: Disk space is low (used > {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}%) |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`{TEMPLATE_NAME:vfs.fs.pused[node_exporter,"{#FSNAME}"].last()}>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and (({TEMPLATE_NAME:vfs.fs.total[node_exporter,"{#FSNAME}"].last()}-{TEMPLATE_NAME:vfs.fs.used[node_exporter,"{#FSNAME}"].last()})<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or {TEMPLATE_NAME:vfs.fs.pused[node_exporter,"{#FSNAME}"].timeleft(1h,,100)}<1d)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#FSNAME}: Disk space is critically low (used > {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}%)</p> |
|{#FSNAME}: Running out of free inodes (free < {$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}%) |<p>It may become impossible to write to disk if there are no index nodes left.</p><p>As symptoms, 'No space left on device' or 'Disk is full' errors may be seen even though free space is available.</p> |`{TEMPLATE_NAME:vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"].min(5m)}<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}` |AVERAGE | |
|{#FSNAME}: Running out of free inodes (free < {$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}%) |<p>It may become impossible to write to disk if there are no index nodes left.</p><p>As symptoms, 'No space left on device' or 'Disk is full' errors may be seen even though free space is available.</p> |`{TEMPLATE_NAME:vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"].min(5m)}<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}` |WARNING |<p>**Depends on**:</p><p>- {#FSNAME}: Running out of free inodes (free < {$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}%)</p> |
|{#DEVNAME}: Disk read/write request responses are too high (read > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} ms for 15m or write > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"} ms for 15m) |<p>This trigger might indicate disk {#DEVNAME} saturation.</p> |`{TEMPLATE_NAME:vfs.dev.read.await[node_exporter,"{#DEVNAME}"].min(15m)} > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} or {TEMPLATE_NAME:vfs.dev.write.await[node_exporter,"{#DEVNAME}"].min(15m)} > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}` |WARNING |<p>Manual close: YES</p> |
|node_exporter is not available (or no data for 30m) |<p>Failed to fetch system metrics from node_exporter in time.</p> |`{TEMPLATE_NAME:node_exporter.get.nodata(30m)}=1` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/387225-discussion-thread-for-official-zabbix-template-for-linux).

## Known Issues

- Description: node_exporter v0.16.0 renamed many metrics. CPU utilization for 'guest' and 'guest_nice' metrics are not supported in this template with node_exporter < 0.16. Disk IO metrics are not supported. Other metrics provided as 'best effort'.  
 See https://github.com/prometheus/node_exporter/releases/tag/v0.16.0 for details.
  - Version: below 0.16.0

- Description: metric node_network_info with label 'device' cannot be found, so network discovery is not possible.
  - Version: below 0.18


## References

https://github.com/prometheus/node_exporter
