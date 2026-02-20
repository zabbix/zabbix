
# Linux by Prom

## Overview

This template collects Linux metrics from Node Exporter 0.18 and above. Support for older Node Exporter versions is provided as best effort.

### Known Issues

  - Node Exporter 0.16.0 renamed many metrics. CPU utilization for "guest" and "guest_nice" metrics are not supported in this template with Node Exporter < 0.16. Disk IO metrics are not supported. Other metrics provided as best effort. See https://github.com/prometheus/node_exporter/releases/tag/v0.16.0 for details.
    - Version: below 0.16.0
  - Metric node_network_info with label 'device' cannot be found, so network discovery is not possible.
    - Version: below 0.18

#### Notes on filesystem (FS) discovery:
  - The ext4/3/2 FS reserves space for privileged usage, typically set at 5% by default.
  - BTRFS allocates a default of 10% of the volume for its own needs.
  - To mitigate potential disasters, FS usage triggers are based on the maximum available space.
    - Utilization formula: `pused = 100 - 100 * (available / total - free + available)`
  - The FS utilization chart, derived from graph prototypes, reflects FS reserved space as the difference between used and available space from the total volume.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- node_exporter 0.17.0
- node_exporter 0.18.1
- node_exporter 1.17.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Set up the node_exporter according to the [`official documentation`](https://prometheus.io/docs/guides/node-exporter/). Use node_exporter v0.18.0 or above.

2. Set the hostname or IP address of the node_exporter host in the `{$NODE_EXPORTER_HOST}` macro. You can also change the Prometheus endpoint port in the `{$NODE_EXPORTER_PORT}` macro if necessary.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>Critical threshold of CPU utilization expressed in %.</p>|`90`|
|{$LOAD_AVG_PER_CPU.MAX.WARN}|<p>Load per CPU considered sustainable. Tune if needed.</p>|`1.5`|
|{$MEMORY.UTIL.MAX}|<p>Used as a threshold in the memory utilization trigger.</p>|`90`|
|{$MEMORY.AVAILABLE.MIN}|<p>Used as a threshold in the memory available trigger.</p>|`20M`|
|{$SWAP.PFREE.MIN.WARN}|<p>Warning threshold of the minimum free swap.</p>|`50`|
|{$IFCONTROL}|<p>Link status trigger will be fired only for interfaces where the context macro equals "1".</p>|`1`|
|{$IF.UTIL.MAX}|<p>Used as a threshold in the interface utilization trigger.</p>|`90`|
|{$IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|
|{$KERNEL.MAXFILES.MIN}||`256`|
|{$SYSTEM.FUZZYTIME.MIN}|<p>The lower threshold for difference of system time. Used in recovery expression to avoid trigger flapping.</p>|`10s`|
|{$SYSTEM.FUZZYTIME.MAX}|<p>The upper threshold for difference of system time.</p>|`60s`|
|{$NODE_EXPORTER_HOST}|<p>The hostname or IP address of the node_exporter host.</p>|`<SET NODE EXPORTER HOST>`|
|{$NODE_EXPORTER_PORT}|<p>TCP Port node_exporter is listening on.</p>|`9100`|
|{$VFS.DEV.READ.AWAIT.WARN}|<p>Disk read average response time (in ms) before the trigger fires.</p>|`20`|
|{$VFS.DEV.WRITE.AWAIT.WARN}|<p>Disk write average response time (in ms) before the trigger fires.</p>|`20`|
|{$VFS.FS.PUSED.MAX.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`80`|
|{$VFS.FS.PUSED.MAX.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`90`|
|{$VFS.FS.INODE.PFREE.MIN.WARN}|<p>The warning threshold of the filesystem metadata utilization.</p>|`20`|
|{$VFS.FS.INODE.PFREE.MIN.CRIT}|<p>The critical threshold of the filesystem metadata utilization.</p>|`10`|
|{$VFS.DEV.DEVNAME.MATCHES}|<p>Used in block device discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.DEV.DEVNAME.NOT_MATCHES}|<p>Used in block device discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFNAME.MATCHES}|<p>Used for network interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filters out `loopbacks`, `nulls`, docker `veth` links and `docker0` bridge by default.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore `notpresent(1)`.</p>|`^notpresent$`|
|{$NET.IF.IFALIAS.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`^.*$`|
|{$NET.IF.IFALIAS.NOT_MATCHES}|<p>Used in network interface discovery rule filters.</p>|`CHANGE_IF_NEEDED`|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`^(/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VFS.FS.FSNAME.MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$VFS.FS.FSDEVICE.MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`^.+$`|
|{$VFS.FS.FSDEVICE.NOT_MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get node_exporter metrics||HTTP agent|node_exporter.get|
|Version of node_exporter running||Dependent item|agent.version[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `node_exporter_build_info` label `version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System boot time||Dependent item|system.boottime[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"^node_boot_time(?:_seconds)?$"})`</p></li></ul>|
|System local time|<p>The local system time of the host.</p>|Dependent item|system.localtime[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"^node_time(?:_seconds)?$"})`</p></li></ul>|
|System name|<p>The host name of the system.</p>|Dependent item|system.name[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `node_uname_info` label `nodename`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System description|<p>Labeled system information as provided by the uname system call.</p>|Dependent item|system.descr[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `node_uname_info`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Maximum number of open file descriptors|<p>May be increased by using `sysctl` utility or modifying the file `/etc/sysctl.conf`.</p>|Dependent item|kernel.maxfiles[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_filefd_maximum)`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Number of open file descriptors||Dependent item|fd.open[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_filefd_allocated)`</p></li></ul>|
|Operating system||Dependent item|system.sw.os[node_exporter]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Operating system architecture||Dependent item|system.sw.arch[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `node_uname_info` label `machine`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System uptime|<p>The system uptime expressed in the following format: "N days, hh:mm:ss".</p>|Dependent item|system.uptime[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"^node_boot_time(?:_seconds)?$"})`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Load average (1m avg)|<p>Calculated as the system CPU load divided by the number of CPU cores.</p>|Dependent item|system.cpu.load.avg1[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_load1)`</p></li></ul>|
|Load average (5m avg)|<p>Calculated as the system CPU load divided by the number of CPU cores.</p>|Dependent item|system.cpu.load.avg5[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_load5)`</p></li></ul>|
|Load average (15m avg)|<p>Calculated as the system CPU load divided by the number of CPU cores.</p>|Dependent item|system.cpu.load.avg15[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_load15)`</p></li></ul>|
|Number of CPUs||Dependent item|system.cpu.num[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU idle time|<p>Time the CPU has spent doing nothing.</p>|Dependent item|system.cpu.idle[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|CPU utilization|<p>CPU utilization expressed in %.</p>|Dependent item|system.cpu.util[node_exporter]<p>**Preprocessing**</p><ul><li><p>JavaScript: `//Calculate utilization<br>return (100 - value)`</p></li></ul>|
|CPU system time|<p>Time the CPU has spent running the kernel and its processes.</p>|Dependent item|system.cpu.system[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|CPU user time|<p>Time the CPU has spent running users' processes that are not niced.</p>|Dependent item|system.cpu.user[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|CPU steal time|<p>The amount of "stolen" CPU from this virtual machine by the hypervisor for other tasks, such as running another virtual machine.</p>|Dependent item|system.cpu.steal[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|CPU softirq time|<p>Time the CPU has spent servicing software interrupts.</p>|Dependent item|system.cpu.softirq[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|CPU nice time|<p>Time the CPU has spent running users' processes that have been niced.</p>|Dependent item|system.cpu.nice[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|CPU iowait time|<p>Time the CPU has been waiting for I/O to complete.</p>|Dependent item|system.cpu.iowait[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|CPU interrupt time|<p>Time the CPU has spent servicing hardware interrupts.</p>|Dependent item|system.cpu.interrupt[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|CPU guest time|<p>Time spent on running a virtual CPU for a guest operating system.</p>|Dependent item|system.cpu.guest[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|CPU guest nice time|<p>The time spent on running a niced guest (a virtual CPU for guest operating systems under the control of the Linux kernel).</p>|Dependent item|system.cpu.guest_nice[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Interrupts per second|<p>Number of interrupts processed.</p>|Dependent item|system.cpu.intr[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_intr"})`</p></li><li>Change per second</li></ul>|
|Context switches per second|<p>The combined rate at which all processors on the computer are switched from one thread to another.</p>|Dependent item|system.cpu.switches[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_context_switches"})`</p></li><li>Change per second</li></ul>|
|Memory utilization|<p>Percentage calculated as (total-available)/total*100.</p>|Calculated|vm.memory.util[node_exporter]|
|Total memory|<p>Total memory expressed in bytes.</p>|Dependent item|vm.memory.total[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_memory_MemTotal"})`</p></li></ul>|
|Available memory|<p>The available memory:</p><p>- in Linux - available = free + buffers + cache;</p><p>- on other platforms calculation may vary.</p><p></p><p>See also Appendixes in Zabbix Documentation about parameters of the `vm.memory.size` item.</p>|Dependent item|vm.memory.available[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_memory_MemAvailable"})`</p></li></ul>|
|Total swap space|<p>Total space of the swap volume/file expressed in bytes.</p>|Dependent item|system.swap.total[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_memory_SwapTotal"})`</p></li></ul>|
|Free swap space|<p>The free space of the swap volume/file expressed in bytes.</p>|Dependent item|system.swap.free[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE({__name__=~"node_memory_SwapFree"})`</p></li></ul>|
|Free swap space in %|<p>The free space of the swap volume/file expressed in %.</p>|Calculated|system.swap.pfree[node_exporter]|

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
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>Sets value to "0" if metric is missing in `node_exporter` output.</p>|Dependent item|net.if.speed[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_speed_bytes{device="{#IFNAME}"})`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>node_network_protocol_type protocol_type value of /sys/class/net/<iface>.</p>|Dependent item|net.if.type[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_protocol_type{device="{#IFNAME}"})`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>Reference: https://www.kernel.org/doc/Documentation/networking/operstates.txt</p>|Dependent item|net.if.status[node_exporter,"{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `node_network_info{device="{#IFNAME}"}` label `operstate`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Linux by Prom/net.if.in[node_exporter,"{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"]) or avg(/Linux by Prom/net.if.out[node_exporter,"{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])) and last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Linux: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Linux by Prom/net.if.in.errors[node_exporter,"{#IFNAME}"],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Linux by Prom/net.if.out.errors[node_exporter"{#IFNAME}"],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Linux: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])<0 and last(/Linux by Prom/net.if.speed[node_exporter,"{#IFNAME}"])>0 and ( last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=6 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=7 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=11 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=62 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=69 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=117 ) and (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Linux: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])<0 and last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])>0 and (last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=6 or last(/Linux by Prom/net.if.type[node_exporter,"{#IFNAME}"])=1) and (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Linux: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"])=2 and (last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"],#1)<>last(/Linux by Prom/net.if.status[node_exporter,"{#IFNAME}"],#2))`|Average|**Manual close**: Yes|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>Discovery of file systems of different types.</p>|Dependent item|vfs.fs.discovery[node_exporter]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FS [{#FSNAME}]: Space: Available|<p>Available storage space expressed in bytes.</p>|Dependent item|vfs.fs.free[node_exporter,"{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}]: Space: Total|<p>Total space expressed in bytes.</p>|Dependent item|vfs.fs.total[node_exporter,"{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}]: Space: Used|<p>Used storage expressed in bytes.</p><p>Reserved space is not counted in.</p>|Dependent item|vfs.fs.used[node_exporter,"{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}]: Space: Used, in %|<p>Calculated as the percentage of currently used space compared to the maximum available space.</p>|Dependent item|vfs.fs.pused[node_exporter,"{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}]: Inodes: Free, in %|<p>Free metadata space expressed as a percentage.</p>|Dependent item|vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `{__name__=~"node_filesystem_files.*",mountpoint="{#FSNAME}"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}]: Filesystem is read-only|<p>The filesystem is mounted as read-only. It is available only for Zabbix agents 6.4 and higher.</p>|Dependent item|vfs.fs.[node_exporter,"{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: FS [{#FSNAME}]: Space is critically low|<p>The storage space usage exceeds the '{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}%' limit.<br>The trigger expression is based on the current used and maximum available spaces.<br>Event name represents the total volume space, which can differ from the maximum available space, depending on the filesystem type.</p>|`min(/Linux by Prom/vfs.fs.pused[node_exporter,"{#FSNAME}"],5m)>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`|Average|**Manual close**: Yes|
|Linux: FS [{#FSNAME}]: Space is low|<p>The storage space usage exceeds the '{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}%' limit.<br>The trigger expression is based on the current used and maximum available spaces.<br>Event name represents the total volume space, which can differ from the maximum available space, depending on the filesystem type.</p>|`min(/Linux by Prom/vfs.fs.pused[node_exporter,"{#FSNAME}"],5m)>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: FS [{#FSNAME}]: Space is critically low</li></ul>|
|Linux: FS [{#FSNAME}]: Running out of free inodes|<p>Disk writing may fail if index nodes are exhausted, leading to error messages like "No space left on device" or "Disk is full", despite available free space.</p>|`min(/Linux by Prom/vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"],5m)<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}`|Average||
|Linux: FS [{#FSNAME}]: Running out of free inodes|<p>Disk writing may fail if index nodes are exhausted, leading to error messages like "No space left on device" or "Disk is full", despite available free space.</p>|`min(/Linux by Prom/vfs.fs.inode.pfree[node_exporter,"{#FSNAME}"],5m)<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}`|Warning|**Depends on**:<br><ul><li>Linux: FS [{#FSNAME}]: Running out of free inodes</li></ul>|
|Linux: FS [{#FSNAME}]: Filesystem has become read-only|<p>The filesystem has become read-only, possibly due to an I/O error. It is available only for Zabbix agents 6.4 and higher.</p>|`last(/Linux by Prom/vfs.fs.[node_exporter,"{#FSNAME}"],#2)=0 and last(/Linux by Prom/vfs.fs.[node_exporter,"{#FSNAME}"])=1`|Average|**Manual close**: Yes|

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
|Linux: {#DEVNAME}: Disk read/write request responses are too high|<p>This trigger might indicate the disk `{#DEVNAME}` saturation.</p>|`min(/Linux by Prom/vfs.dev.read.await[node_exporter,"{#DEVNAME}"],15m) > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} or min(/Linux by Prom/vfs.dev.write.await[node_exporter,"{#DEVNAME}"],15m) > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

