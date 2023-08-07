
# Linux by Prom

## Overview

This template collects Linux metrics from node_exporter 0.18 and above. Support for older node_exporter versions is provided as 'best effort'.

### Known Issues

- Description: node_exporter v0.16.0 renamed many metrics. CPU utilization for 'guest' and 'guest_nice' metrics are not supported in this template with node_exporter < 0.16. Disk IO metrics are not supported. Other metrics provided as 'best effort'. See https://github.com/prometheus/node_exporter/releases/tag/v0.16.0 for details.
  - version: below 0.16.0

- Description: metric node_network_info with label 'device' cannot be found, so network discovery is not possible.
  - version: below 0.18

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- node_exporter 0.17.0
- node_exporter 0.18.1

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Please refer to the node_exporter docs. Use node_exporter v0.18.0 or above.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}||`90`|
|{$IF.ERRORS.WARN}||`2`|
|{$IF.UTIL.MAX}||`90`|
|{$SYSTEM.FUZZYTIME.MAX}||`60`|
|{$KERNEL.MAXFILES.MIN}||`256`|
|{$LOAD_AVG_PER_CPU.MAX.WARN}|<p>Load per CPU considered sustainable. Tune if needed.</p>|`1.5`|
|{$NODE_EXPORTER_PORT}|<p>TCP Port node_exporter is listening on.</p>|`9100`|
|{$SWAP.PFREE.MIN.WARN}||`50`|
|{$VFS.DEV.READ.AWAIT.WARN}|<p>Disk read average response time (in ms) before the trigger would fire.</p>|`20`|
|{$VFS.DEV.WRITE.AWAIT.WARN}|<p>Disk write average response time (in ms) before the trigger would fire.</p>|`20`|
|{$VFS.DEV.DEVNAME.NOT_MATCHES}|<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$VFS.DEV.DEVNAME.MATCHES}|<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`^(/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VFS.FS.FSNAME.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$VFS.FS.FSDEVICE.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`^.+$`|
|{$VFS.FS.FSDEVICE.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$MEMORY.UTIL.MAX}||`90`|
|{$MEMORY.AVAILABLE.MIN}||`20M`|
|{$IFCONTROL}||`1`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}||`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore notPresent(7).</p>|`^7$`|
|{$NET.IF.IFALIAS.MATCHES}||`^.*$`|
|{$NET.IF.IFALIAS.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$VFS.FS.FREE.MIN.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`5G`|
|{$VFS.FS.FREE.MIN.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`10G`|
|{$VFS.FS.INODE.PFREE.MIN.CRIT}||`10`|
|{$VFS.FS.INODE.PFREE.MIN.WARN}||`20`|
|{$VFS.FS.PUSED.MAX.CRIT}||`90`|
|{$VFS.FS.PUSED.MAX.WARN}||`80`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Linux: Get node_exporter metrics||HTTP agent|node_exporter.get|
|Linux: Version of node_exporter running||Dependent item|agent.version[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `node_exporter_build_info` label `version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: System boot time||Dependent item|system.boottime[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"^node_boot_time(?:_seconds)?$"})`</p></li></ul>|
|Linux: System local time|<p>The local system time of the host.</p>|Dependent item|system.localtime[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"^node_time(?:_seconds)?$"})`</p></li></ul>|
|Linux: System name|<p>The host name of the system.</p>|Dependent item|system.name[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `node_uname_info` label `nodename`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: System description|<p>Labeled system information as provided by the uname system call.</p>|Dependent item|system.descr[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `node_uname_info`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: Maximum number of open file descriptors|<p>It could be increased by using `sysctl` utility or modifying the file `/etc/sysctl.conf`.</p>|Dependent item|kernel.maxfiles[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_filefd_maximum)`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: Number of open file descriptors||Dependent item|fd.open[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_filefd_allocated)`</p></li></ul>|
|Linux: Operating system||Dependent item|system.sw.os[node_exporter]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: Operating system architecture|<p>The architecture of the operating system.</p>|Dependent item|system.sw.arch[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `node_uname_info` label `machine`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Linux: System uptime|<p>The system uptime expressed in the following format: "N days, hh:mm:ss".</p>|Dependent item|system.uptime[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"^node_boot_time(?:_seconds)?$"})`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: Load average (1m avg)||Dependent item|system.cpu.load.avg1[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_load1)`</p></li></ul>|
|Linux: Load average (5m avg)||Dependent item|system.cpu.load.avg5[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_load5)`</p></li></ul>|
|Linux: Load average (15m avg)||Dependent item|system.cpu.load.avg15[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_load15)`</p></li></ul>|
|Linux: Number of CPUs||Dependent item|system.cpu.num[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU idle time|<p>The time the CPU has spent doing nothing.</p>|Dependent item|system.cpu.idle[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Linux: CPU utilization|<p>The CPU utilization expressed in %.</p>|Dependent item|system.cpu.util[node_exporter]<p>**Preprocessing**</p><ul><li><p>JavaScript: `//Calculate utilization<br>return (100 - value)`</p></li></ul>|
|Linux: CPU system time|<p>The time the CPU has spent running the kernel and its processes.</p>|Dependent item|system.cpu.system[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Linux: CPU user time|<p>The time the CPU has spent running users' processes that are not niced.</p>|Dependent item|system.cpu.user[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Linux: CPU steal time|<p>The amount of "stolen" CPU from this virtual machine by the hypervisor for other tasks, such as running another virtual machine.</p>|Dependent item|system.cpu.steal[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Linux: CPU softirq time|<p>The amount of time the CPU has been servicing software interrupts.</p>|Dependent item|system.cpu.softirq[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Linux: CPU nice time|<p>The time the CPU has spent running users' processes that have been niced.</p>|Dependent item|system.cpu.nice[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Linux: CPU iowait time|<p>The amount of time the CPU has been waiting for I/O to complete.</p>|Dependent item|system.cpu.iowait[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Linux: CPU interrupt time|<p>The amount of time the CPU has been servicing hardware interrupts.</p>|Dependent item|system.cpu.interrupt[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Linux: CPU guest time|<p>Guest time - the time spent on running a virtual CPU for a guest operating system.</p>|Dependent item|system.cpu.guest[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Linux: CPU guest nice time|<p>The time spent on running a niced guest (a virtual CPU for guest operating systems under the control of the Linux kernel).</p>|Dependent item|system.cpu.guest_nice[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Linux: Interrupts per second||Dependent item|system.cpu.intr[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_intr"})`</p></li><li>Change per second</li></ul>|
|Linux: Context switches per second||Dependent item|system.cpu.switches[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_context_switches"})`</p></li><li>Change per second</li></ul>|
|Linux: Memory utilization|<p>Memory used percentage is calculated as (total-available)/total*100.</p>|Calculated|vm.memory.util[node_exporter]|
|Linux: Total memory|<p>The total memory expressed in bytes.</p>|Dependent item|vm.memory.total[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_memory_MemTotal"})`</p></li></ul>|
|Linux: Available memory|<p>The available memory:</p><p>- in Linux - available = free + buffers + cache;</p><p>- on other platforms calculation may vary.</p><p></p><p>See also Appendixes in Zabbix Documentation about parameters of the `vm.memory.size` item.</p>|Dependent item|vm.memory.available[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_memory_MemAvailable"})`</p></li></ul>|
|Linux: Total swap space|<p>The total space of the swap volume/file expressed in bytes.</p>|Dependent item|system.swap.total[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_memory_SwapTotal"})`</p></li></ul>|
|Linux: Free swap space|<p>The free space of the swap volume/file expressed in bytes.</p>|Dependent item|system.swap.free[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_memory_SwapFree"})`</p></li></ul>|
|Linux: Free swap space in %|<p>The free space of the swap volume/file expressed in %.</p>|Calculated|system.swap.pfree[node_exporter]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: node_exporter is not available|<p>Failed to fetch system metrics from node_exporter in time.</p>|`nodata(/Linux by Prom/node_exporter.get,30m)=1`|Warning|**Manual close**: Yes|
|Linux: System time is out of sync|<p>The host's system time is different from Zabbix server time.</p>|`fuzzytime(/Linux by Prom/system.localtime[node_exporter],{$SYSTEM.FUZZYTIME.MAX})=0`|Warning|**Manual close**: Yes|
|Linux: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Linux by Prom/system.name[node_exporter],#1)<>last(/Linux by Prom/system.name[node_exporter],#2) and length(last(/Linux by Prom/system.name[node_exporter]))>0`|Info|**Manual close**: Yes|
|Linux: Configured max number of open filedescriptors is too low||`last(/Linux by Prom/kernel.maxfiles[node_exporter])<{$KERNEL.MAXFILES.MIN}`|Info|**Depends on**:<br><ul><li>Linux: Running out of file descriptors</li></ul>|
|Linux: Running out of file descriptors||`last(/Linux by Prom/fd.open[node_exporter])/last(/Linux by Prom/kernel.maxfiles[node_exporter])*100>80`|Warning||
|Linux: Operating system description has changed|<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/Linux by Prom/system.sw.os[node_exporter],#1)<>last(/Linux by Prom/system.sw.os[node_exporter],#2) and length(last(/Linux by Prom/system.sw.os[node_exporter]))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: System name has changed</li></ul>|
|Linux: {HOST.NAME} has been restarted|<p>The device uptime is less than 10 minutes.</p>|`last(/Linux by Prom/system.uptime[node_exporter])<10m`|Warning|**Manual close**: Yes|
|Linux: Load average is too high|<p>The load average per CPU is too high. The system may be slow to respond.</p>|`min(/Linux by Prom/system.cpu.load.avg1[node_exporter],5m)/last(/Linux by Prom/system.cpu.num[node_exporter])>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/Linux by Prom/system.cpu.load.avg5[node_exporter])>0 and last(/Linux by Prom/system.cpu.load.avg15[node_exporter])>0`|Average||
|Linux: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Linux by Prom/system.cpu.util[node_exporter],5m)>{$CPU.UTIL.CRIT}`|Warning|**Depends on**:<br><ul><li>Linux: Load average is too high</li></ul>|
|Linux: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Linux by Prom/vm.memory.util[node_exporter],5m)>{$MEMORY.UTIL.MAX}`|Average|**Depends on**:<br><ul><li>Linux: Lack of available memory</li></ul>|
|Linux: Lack of available memory||`max(/Linux by Prom/vm.memory.available[node_exporter],5m)<{$MEMORY.AVAILABLE.MIN} and last(/Linux by Prom/vm.memory.total[node_exporter])>0`|Average||
|Linux: High swap space usage|<p>If there is no swap configured, this trigger is ignored.</p>|`max(/Linux by Prom/system.swap.pfree[node_exporter],5m)<{$SWAP.PFREE.MIN.WARN} and last(/Linux by Prom/system.swap.total[node_exporter])>0`|Warning|**Depends on**:<br><ul><li>Linux: Lack of available memory</li><li>Linux: High memory utilization</li></ul>|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of network interfaces. Requires node_exporter v0.18 and up.</p>|Dependent item|net.if.discovery[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `{__name__=~"^node_network_info$"}`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Bits received||Dependent item|net.if.in[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_receive_bytes_total{device="{#IFNAME}"})`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent||Dependent item|net.if.out[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_transmit_bytes_total{device="{#IFNAME}"})`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors||Dependent item|net.if.out.errors[node_exporter"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_transmit_errs_total{device="{#IFNAME}"})`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors||Dependent item|net.if.in.errors[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_receive_errs_total{device="{#IFNAME}"})`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded||Dependent item|net.if.in.discards[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_receive_drop_total{device="{#IFNAME}"})`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded||Dependent item|net.if.out.discards[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_transmit_drop_total{device="{#IFNAME}"})`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>Sets value to 0 if metric is missing in node_exporter output.</p>|Dependent item|net.if.speed[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_speed_bytes{device="{#IFNAME}"})`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>node_network_protocol_type protocol_type value of /sys/class/net/<iface>.</p>|Dependent item|net.if.type[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_protocol_type{device="{#IFNAME}"})`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>Reference: https://www.kernel.org/doc/Documentation/networking/operstates.txt</p>|Dependent item|net.if.status[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `node_network_info{device="{#IFNAME}"}` label `operstate`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Linux by Prom/net.if.in[node_exporter,"{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"]) or avg(/Linux by Prom/net.if.out[node_exporter,"{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])) and last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Linux by Prom/net.if.in.errors[node_exporter,"{#IFNAME}"],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Linux by Prom/net.if.out.errors[node_exporter"{#IFNAME}"],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])<0 and last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])>0 and ( last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=6 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=7 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=11 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=62 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=69 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=117 ) and (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])<0 and last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])>0 and (last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=6 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=1) and (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br>          <br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])=2 and (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"],#1)<>last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"],#2))`|Average|**Manual close**: Yes|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>Discovery of file systems of different types.</p>|Dependent item|vfs.fs.discovery[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FSNAME}: Free space||Dependent item|vfs.fs.free[node_exporter,"{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|{#FSNAME}: Total space|<p>Total space in bytes</p>|Dependent item|vfs.fs.total[node_exporter,"{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|{#FSNAME}: Used space|<p>Used storage in bytes</p>|Calculated|vfs.fs.used[node_exporter,"{#FSNAME}"]|
|{#FSNAME}: Space utilization|<p>The space utilization expressed in % for {#FSNAME}.</p>|Calculated|vfs.fs.pused[node_exporter,"{#FSNAME}"]|
|{#FSNAME}: Free inodes in %||Dependent item|vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `{__name__=~"node_filesystem_files.*",mountpoint="{#FSNAME}"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSNAME}: Disk space is critically low|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`.<br>2. The second condition should be one of the following:<br>- the disk free space is less than `{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}`;<br>- the disk will be full in less than 24 hours.</p>|`last(/Linux by Prom/vfs.fs.pused[node_exporter,"{#FSNAME}"])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/Linux by Prom/vfs.fs.total[node_exporter,"{#FSNAME}"])-last(/Linux by Prom/vfs.fs.used[node_exporter,"{#FSNAME}"]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/Linux by Prom/vfs.fs.pused[node_exporter,"{#FSNAME}"],1h,100)<1d)`|Average|**Manual close**: Yes|
|{#FSNAME}: Disk space is low|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}`.<br>2. The second condition should be one of the following:<br>- the disk free space is less than `{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}`;<br>- the disk will be full in less than 24 hours.</p>|`last(/Linux by Prom/vfs.fs.pused[node_exporter,"{#FSNAME}"])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/Linux by Prom/vfs.fs.total[node_exporter,"{#FSNAME}"])-last(/Linux by Prom/vfs.fs.used[node_exporter,"{#FSNAME}"]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/Linux by Prom/vfs.fs.pused[node_exporter,"{#FSNAME}"],1h,100)<1d)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#FSNAME}: Disk space is critically low</li></ul>|
|{#FSNAME}: Running out of free inodes|<p>It may become impossible to write to a disk if there are no index nodes left.<br>The following error messages may be returned as symptoms, even though the free space is available:<br>- 'No space left on device';<br>- 'Disk is full'.</p>|`min(/Linux by Prom/vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"],5m)<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}`|Average||
|{#FSNAME}: Running out of free inodes|<p>It may become impossible to write to a disk if there are no index nodes left.<br>The following error messages may be returned as symptoms, even though the free space is available:<br>- 'No space left on device';<br>- 'Disk is full'.</p>|`min(/Linux by Prom/vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"],5m)<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}`|Warning|**Depends on**:<br><ul><li>{#FSNAME}: Running out of free inodes</li></ul>|

### LLD rule Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Block devices discovery||Dependent item|vfs.dev.discovery[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `node_disk_io_now{device=~".+"}`</p></li></ul>|

### Item prototypes for Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DEVNAME}: Disk read rate|<p>r/s. The number (after merges) of read requests completed per second for the device.</p>|Dependent item|vfs.dev.read.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_disk_reads_completed_total{device="{#DEVNAME}"})`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk write rate|<p>w/s. The number (after merges) of write requests completed per second for the device.</p>|Dependent item|vfs.dev.write.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_disk_writes_completed_total{device="{#DEVNAME}"})`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk read time (rate)|<p>Rate of total read time counter. Used in `r_await` calculation.</p>|Dependent item|vfs.dev.read.time.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk write time (rate)|<p>Rate of total write time counter. Used in `w_await` calculation.</p>|Dependent item|vfs.dev.write.time.rate[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk read request avg waiting time (r_await)|<p>This formula contains two Boolean expressions that evaluates to 1 or 0 in order to set calculated metric to zero and to avoid division by zero exception.</p>|Calculated|vfs.dev.read.await[node_exporter,"{#DEVNAME}"]|
|{#DEVNAME}: Disk write request avg waiting time (w_await)|<p>This formula contains two Boolean expressions that evaluates to 1 or 0 in order to set calculated metric to zero and to avoid division by zero exception.</p>|Calculated|vfs.dev.write.await[node_exporter,"{#DEVNAME}"]|
|{#DEVNAME}: Disk average queue size (avgqu-sz)|<p>The current average disk queue; the number of requests outstanding on the disk while the performance data is being collected.</p>|Dependent item|vfs.dev.queue_size[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk utilization|<p>This item is the percentage of elapsed time during which the selected disk drive was busy while servicing read or write requests.</p>|Dependent item|vfs.dev.util[node_exporter,"{#DEVNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_disk_io_time_seconds_total{device="{#DEVNAME}"})`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|

### Trigger prototypes for Block devices discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#DEVNAME}: Disk read/write request responses are too high|<p>This trigger might indicate the disk {#DEVNAME} saturation.</p>|`min(/Linux by Prom/vfs.dev.read.await[node_exporter,"{#DEVNAME}"],15m) > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} or min(/Linux by Prom/vfs.dev.write.await[node_exporter,"{#DEVNAME}"],15m) > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

