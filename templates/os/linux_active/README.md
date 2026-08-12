
# Linux by Zabbix agent active

## Overview

This is an official Linux template. It requires Zabbix agent 8.0 or newer.

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
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on Linux OS following Zabbix [documentation](https://www.zabbix.com/documentation/8.0/manual/concepts/agent#agent-on-unix-like-systems).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.NODATA_TIMEOUT}|<p>No data timeout for active agents. Consider to keep it relatively high.</p>|`30m`|
|{$AGENT.TIMEOUT}|<p>Timeout after which agent is considered unavailable.</p>|`5m`|
|{$CPU.UTIL.CRIT}|<p>Critical threshold of CPU utilization expressed in %.</p>|`90`|
|{$LOAD_AVG_PER_CPU.MAX.WARN}|<p>The CPU load per core is considered sustainable. If necessary, it can be tuned.</p>|`1.5`|
|{$MEMORY.UTIL.MAX}|<p>Used as a threshold in the memory utilization trigger.</p>|`90`|
|{$MEMORY.AVAILABLE.MIN}|<p>Used as a threshold in the available memory trigger.</p>|`20M`|
|{$SWAP.PFREE.MIN.WARN}|<p>The warning threshold of the minimum free swap.</p>|`50`|
|{$VFS.FS.PUSED.MAX.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`80`|
|{$VFS.FS.PUSED.MAX.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`90`|
|{$VFS.FS.INODE.PFREE.MIN.WARN}|<p>The warning threshold of the filesystem metadata utilization.</p>|`20`|
|{$VFS.FS.INODE.PFREE.MIN.CRIT}|<p>The critical threshold of the filesystem metadata utilization.</p>|`10`|
|{$VFS.FS.FSNAME.MATCHES}|<p>Used for filesystem discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>Used for filesystem discovery. Can be overridden on the host or linked template level.</p>|`^(/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VFS.DEV.DEVNAME.MATCHES}|<p>Used for block device discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.DEV.DEVNAME.NOT_MATCHES}|<p>Used for block device discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$IFCONTROL}|<p>Link status trigger will be fired only for interfaces where the context macro equals "1".</p>|`1`|
|{$IF.UTIL.MAX}|<p>Used as a threshold in the interface utilization trigger.</p>|`90`|
|{$IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|
|{$NET.IF.IFNAME.MATCHES}|<p>Used for network interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filters out `loopbacks`, `nulls`, docker `veth` links and `docker0` bridge by default.</p>|`Macro too long. Please see the template.`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>Used for filesystem discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>Used for filesystem discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$VFS.DEV.READ.AWAIT.WARN}|<p>The average response time (in ms) of disk read before the trigger fires.</p>|`20`|
|{$VFS.DEV.WRITE.AWAIT.WARN}|<p>The average response time (in ms) of disk write before the trigger fires.</p>|`20`|
|{$KERNEL.MAXPROC.MIN}|<p>Configured max number of processes. No less than 0.</p>|`1024`|
|{$KERNEL.MAXFILES.MIN}|<p>Configured max number of open file descriptors. No less than 0.</p>|`256`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Host name of Zabbix agent running||Zabbix agent (active)|agent.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Zabbix agent ping|<p>The agent always returns "1" for this item. May be used in combination with `nodata()` for the availability check.</p>|Zabbix agent (active)|agent.ping|
|Version of Zabbix agent running||Zabbix agent (active)|agent.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Zabbix agent variant|<p>The variant of Zabbix agent (Zabbix agent or Zabbix agent 2).</p>|Zabbix agent (active)|agent.variant<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Maximum number of open file descriptors|<p>May be increased by using the `sysctl` utility or modifying the file `/etc/sysctl.conf`.</p>|Zabbix agent (active)|kernel.maxfiles<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Number of open file descriptors|<p>The number of currently open file descriptors.</p>|Zabbix agent (active)|kernel.openfiles<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Maximum number of processes|<p>May be increased by using the `sysctl` utility or modifying the file `/etc/sysctl.conf`.</p>|Zabbix agent (active)|kernel.maxproc<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Number of processes||Zabbix agent (active)|proc.num|
|Number of running processes||Zabbix agent (active)|proc.num[,,run]|
|System boot time||Zabbix agent (active)|system.boottime<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interrupts per second|<p>Number of interrupts processed.</p>|Zabbix agent (active)|system.cpu.intr<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Load average (15m avg)|<p>Calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent (active)|system.cpu.load[all,avg15]|
|Load average (1m avg)|<p>Calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent (active)|system.cpu.load[all,avg1]|
|Load average (5m avg)|<p>Calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent (active)|system.cpu.load[all,avg5]|
|Number of CPUs||Zabbix agent (active)|system.cpu.num<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Context switches per second|<p>The combined rate at which all processors on the computer are switched from one thread to another.</p>|Zabbix agent (active)|system.cpu.switches<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|CPU utilization|<p>CPU utilization expressed in %.</p>|Dependent item|system.cpu.util<p>**Preprocessing**</p><ul><li><p>JavaScript: `return (100 - value)`</p></li></ul>|
|CPU guest time|<p>Time spent on running a virtual CPU for a guest operating system.</p>|Zabbix agent (active)|system.cpu.util[,guest]|
|CPU guest nice time|<p>Time spent on running a niced guest (a virtual CPU for guest operating systems under the control of the Linux kernel).</p>|Zabbix agent (active)|system.cpu.util[,guest_nice]|
|CPU idle time|<p>Time the CPU has spent doing nothing.</p>|Zabbix agent (active)|system.cpu.util[,idle]|
|CPU interrupt time|<p>Time the CPU has spent servicing hardware interrupts.</p>|Zabbix agent (active)|system.cpu.util[,interrupt]|
|CPU iowait time|<p>Time the CPU has been waiting for I/O to complete.</p>|Zabbix agent (active)|system.cpu.util[,iowait]|
|CPU nice time|<p>Time the CPU has spent running users' processes that have been niced.</p>|Zabbix agent (active)|system.cpu.util[,nice]|
|CPU softirq time|<p>Time the CPU has been servicing software interrupts.</p>|Zabbix agent (active)|system.cpu.util[,softirq]|
|CPU steal time|<p>The amount of "stolen" CPU from this virtual machine by the hypervisor for other tasks, such as running another virtual machine.</p>|Zabbix agent (active)|system.cpu.util[,steal]|
|CPU system time|<p>Time the CPU has spent running the kernel and its processes.</p>|Zabbix agent (active)|system.cpu.util[,system]|
|CPU user time|<p>Time the CPU has spent running users' processes that are not niced.</p>|Zabbix agent (active)|system.cpu.util[,user]|
|System name|<p>The host name of the system.</p>|Zabbix agent (active)|system.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System local time|<p>The local system time of the host.</p>|Zabbix agent (active)|system.localtime|
|Operating system architecture|<p>The architecture of the operating system.</p>|Zabbix agent (active)|system.sw.arch<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Operating system||Zabbix agent (active)|system.sw.os<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Number of installed packages||Zabbix agent (active)|system.sw.packages.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.length()`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Free swap space|<p>The free space of the swap volume/file expressed in bytes.</p>|Zabbix agent (active)|system.swap.size[,free]|
|Free swap space in %|<p>The free space of the swap volume/file expressed in %.</p>|Zabbix agent (active)|system.swap.size[,pfree]|
|Total swap space|<p>The total space of the swap volume/file expressed in bytes.</p>|Zabbix agent (active)|system.swap.size[,total]|
|System description|<p>The information as normally returned by `uname -a`.</p>|Zabbix agent (active)|system.uname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System uptime|<p>The system uptime expressed in the following format: "N days, hh:mm:ss".</p>|Zabbix agent (active)|system.uptime|
|Number of logged in users|<p>The number of users who are currently logged in.</p>|Zabbix agent (active)|system.users.num|
|Checksum of /etc/passwd||Zabbix agent (active)|vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get filesystems|<p>The `vfs.fs.get` key acquires raw information set about the filesystems. Later to be extracted by preprocessing in dependent items.</p>|Zabbix agent (active)|vfs.fs.get|
|Get network interfaces|<p>The `net.if.get` key acquires raw information set about the network interfaces. Later to be extracted by preprocessing in dependent items.</p>|Zabbix agent (active)|net.if.get|
|Get block devices|<p>The `vfs.dev.get` key acquires raw information set about the block devices. Later to be extracted by preprocessing in dependent items.</p>|Zabbix agent (active)|vfs.dev.get[disk_stats,.*]|
|Available memory|<p>The available memory:</p><p>- in Linux = free + buffers + cache;</p><p>- on other platforms calculation may vary.</p><p></p><p>See also Appendixes in Zabbix Documentation about parameters of the `vm.memory.size` item.</p>|Zabbix agent (active)|vm.memory.size[available]|
|Available memory in %|<p>The available memory as percentage of the total. See also Appendixes in Zabbix Documentation about parameters of the `vm.memory.size` item.</p>|Zabbix agent (active)|vm.memory.size[pavailable]|
|Total memory|<p>Total memory expressed in bytes.</p>|Zabbix agent (active)|vm.memory.size[total]|
|Memory utilization|<p>The percentage of used memory is calculated as `100-pavailable`.</p>|Dependent item|vm.memory.util<p>**Preprocessing**</p><ul><li><p>JavaScript: `return (100-value);`</p></li></ul>|
|Active agent availability|<p>Availability of active checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - unknown</p><p>1 - available</p><p>2 - not available</p>|Zabbix internal|zabbix[host,active_agent,available]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: Zabbix agent is not available|<p>For active agents, `nodata()` with `agent.ping` is used with `{$AGENT.NODATA_TIMEOUT}` as a time threshold.</p>|`nodata(/Linux by Zabbix agent active/agent.ping,{$AGENT.NODATA_TIMEOUT})=1`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Active checks are not available</li></ul>|
|Linux: Configured max number of open file descriptors is too low||`last(/Linux by Zabbix agent active/kernel.maxfiles)<{$KERNEL.MAXFILES.MIN}`|Info|**Depends on**:<br><ul><li>Linux: Getting closer to file descriptors limit</li></ul>|
|Linux: Getting closer to file descriptors limit||`min(/Linux by Zabbix agent active/kernel.openfiles,5m)/last(/Linux by Zabbix agent active/kernel.maxfiles)*100>80`|Warning||
|Linux: Configured max number of processes is too low||`last(/Linux by Zabbix agent active/kernel.maxproc)<{$KERNEL.MAXPROC.MIN}`|Info|**Depends on**:<br><ul><li>Linux: Getting closer to process limit</li></ul>|
|Linux: Getting closer to process limit||`last(/Linux by Zabbix agent active/proc.num)/last(/Linux by Zabbix agent active/kernel.maxproc)*100>80`|Warning||
|Linux: Load average is too high|<p>The load average per CPU is too high. The system may be slow to respond.</p>|`min(/Linux by Zabbix agent active/system.cpu.load[all,avg1],5m)/last(/Linux by Zabbix agent active/system.cpu.num)>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/Linux by Zabbix agent active/system.cpu.load[all,avg5])>0 and last(/Linux by Zabbix agent active/system.cpu.load[all,avg15])>0`|Average||
|Linux: High CPU utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Linux by Zabbix agent active/system.cpu.util,5m)>{$CPU.UTIL.CRIT}`|Warning|**Depends on**:<br><ul><li>Linux: Load average is too high</li></ul>|
|Linux: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`change(/Linux by Zabbix agent active/system.hostname) and length(last(/Linux by Zabbix agent active/system.hostname))>0`|Info|**Manual close**: Yes|
|Linux: Operating system description has changed|<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`change(/Linux by Zabbix agent active/system.sw.os) and length(last(/Linux by Zabbix agent active/system.sw.os))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: System name has changed</li></ul>|
|Linux: Number of installed packages has been changed||`change(/Linux by Zabbix agent active/system.sw.packages.get)<>0`|Warning|**Manual close**: Yes|
|Linux: High swap space usage|<p>If there is no swap configured, this trigger is ignored.</p>|`max(/Linux by Zabbix agent active/system.swap.size[,pfree],5m)<{$SWAP.PFREE.MIN.WARN} and last(/Linux by Zabbix agent active/system.swap.size[,total])>0`|Warning|**Depends on**:<br><ul><li>Linux: Lack of available memory</li><li>Linux: High memory utilization</li></ul>|
|Linux: {HOST.NAME} has been restarted|<p>The host uptime is less than 10 minutes.</p>|`last(/Linux by Zabbix agent active/system.uptime)<10m`|Warning|**Manual close**: Yes|
|Linux: /etc/passwd has been changed||`last(/Linux by Zabbix agent active/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/Linux by Zabbix agent active/vfs.file.cksum[/etc/passwd,sha256],#2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: System name has changed</li><li>Linux: Operating system description has changed</li></ul>|
|Linux: Lack of available memory|<p>The system is running out of memory.</p>|`max(/Linux by Zabbix agent active/vm.memory.size[available],5m)<{$MEMORY.AVAILABLE.MIN} and last(/Linux by Zabbix agent active/vm.memory.size[total])>0`|Average||
|Linux: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Linux by Zabbix agent active/vm.memory.util,5m)>{$MEMORY.UTIL.MAX}`|Average|**Depends on**:<br><ul><li>Linux: Lack of available memory</li></ul>|
|Linux: Active checks are not available|<p>Active checks are considered unavailable. Agent has not sent a heartbeat for a prolonged time.</p>|`min(/Linux by Zabbix agent active/zabbix[host,active_agent,available],{$AGENT.TIMEOUT})=2`|High||

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>The discovery of network interfaces.</p>|Dependent item|net.if.get.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.config`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}: Get config data|<p>Intermediate configuration data of the `{#IFNAME}` network interface.</p>|Dependent item|net.if.get.config["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.config[?(@.name=="{#IFNAME}")].first()`</p></li></ul>|
|Interface {#IFNAME}: Get values data|<p>Intermediate statistics data of the `{#IFNAME}` network interface.</p>|Dependent item|net.if.get.values["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.values[?(@.name=="{#IFNAME}")].first()`</p></li></ul>|
|Interface {#IFNAME}: Bits received||Dependent item|net.if.get.in["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.in.bytes`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}: Bits sent||Dependent item|net.if.get.out["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.out.bytes`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}: Outbound packets with errors||Dependent item|net.if.get.out.errors["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.out.errors`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}: Inbound packets with errors||Dependent item|net.if.get.in.errors["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.in.errors`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}: Outbound packets discarded||Dependent item|net.if.get.out.dropped["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.out.dropped`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}: Inbound packets discarded||Dependent item|net.if.get.in.dropped["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.in.dropped`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}: Operational status|<p>Reference: https://www.kernel.org/doc/Documentation/networking/operstates.txt</p>|Dependent item|net.if.get.operstate["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.operational_state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Interface {#IFNAME}: Interface type|<p>The interface protocol type.</p><p>Reference: https://www.kernel.org/doc/Documentation/ABI/testing/sysfs-class-net</p>|Dependent item|net.if.get.type["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}: Speed|<p>The latest or current speed value of the interface, expressed in bits/sec.</p><p>This attribute is only valid for the interfaces that implement the ethtool `get_link_ksettings` method (mostly Ethernet).</p><p></p><p>Reference: https://www.kernel.org/doc/Documentation/ABI/testing/sysfs-class-net</p>|Dependent item|net.if.get.speed["{#IFNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.speed`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: Interface {#IFNAME}: High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Linux by Zabbix agent active/net.if.get.in["{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by Zabbix agent active/net.if.get.speed["{#IFNAME}"]) or avg(/Linux by Zabbix agent active/net.if.get.out["{#IFNAME}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by Zabbix agent active/net.if.get.speed["{#IFNAME}"])) and last(/Linux by Zabbix agent active/net.if.get.speed["{#IFNAME}"])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Interface {#IFNAME}: Link down</li></ul>|
|Linux: Interface {#IFNAME}: High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Linux by Zabbix agent active/net.if.get.in.errors["{#IFNAME}"],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Linux by Zabbix agent active/net.if.get.out.errors["{#IFNAME}"],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Interface {#IFNAME}: Link down</li></ul>|
|Linux: Interface {#IFNAME}: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operational status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Linux by Zabbix agent active/net.if.get.operstate["{#IFNAME}"])=2 and (last(/Linux by Zabbix agent active/net.if.get.operstate["{#IFNAME}"],#1)<>last(/Linux by Zabbix agent active/net.if.get.operstate["{#IFNAME}"],#2))`|Average|**Manual close**: Yes|
|Linux: Interface {#IFNAME}: Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Linux by Zabbix agent active/net.if.get.speed["{#IFNAME}"])<0 and last(/Linux by Zabbix agent active/net.if.get.speed["{#IFNAME}"])>0 and last(/Linux by Zabbix agent active/net.if.get.type["{#IFNAME}"])=0 and (last(/Linux by Zabbix agent active/net.if.get.operstate["{#IFNAME}"])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Interface {#IFNAME}: Link down</li></ul>|

### LLD rule Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Block devices discovery|<p>The discovery of block devices.</p>|Dependent item|vfs.dev.dependent.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.config`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DEVNAME}: Get stats data|<p>Intermediate statistics data of the `{#DEVNAME}` block device.</p>|Dependent item|vfs.dev.stats["{#DEVNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.values[?(@.name=="{#DEVNAME}")].first()`</p></li></ul>|
|{#DEVNAME}: Get stats|<p>The contents of get `/sys/block/{#DEVNAME}/stat` to get the disk statistics.</p>|Zabbix agent (active)|vfs.file.contents[/sys/block/{#DEVNAME}/stat]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return JSON.stringify(value.trim().split(/ +/));`</p></li></ul>|
|{#DEVNAME}: Disk read rate|<p>r/s (read operations per second) - the number (after merges) of read requests completed per second for the device.</p>|Dependent item|vfs.dev.read.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.reads_completed`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk write rate|<p>w/s (write operations per second) - the number (after merges) of write requests completed per second for the device.</p>|Dependent item|vfs.dev.write.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.writes_completed`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk read throughput|<p>The number of bytes read from the device per second.</p>|Dependent item|vfs.dev.read[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.bytes_read`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk write throughput|<p>The number of bytes written to the device per second.</p>|Dependent item|vfs.dev.write[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.bytes_written`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk read time (rate)|<p>The rate of total read time counter; used in `r_await` calculation.</p>|Dependent item|vfs.dev.read.time.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[3]`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#DEVNAME}: Disk write time (rate)|<p>The rate of total write time counter; used in `w_await` calculation.</p>|Dependent item|vfs.dev.write.time.rate[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[7]`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#DEVNAME}: Disk average queue size (avgqu-sz)|<p>The current average disk queue; the number of requests outstanding on the disk while the performance data is being collected.</p>|Dependent item|vfs.dev.queue_size[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[10]`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#DEVNAME}: Disk utilization|<p>The percentage of elapsed time during which the selected disk drive was busy while servicing read or write requests.</p>|Dependent item|vfs.dev.util[{#DEVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stats.io_time_ms`</p></li><li>Change per second</li><li><p>Custom multiplier: `0.1`</p></li></ul>|
|{#DEVNAME}: Disk read request avg waiting time (r_await)|<p>This formula contains two Boolean expressions that evaluate to 1 or 0 in order to set the calculated metric to zero and to avoid the exception - division by zero.</p>|Calculated|vfs.dev.read.await[{#DEVNAME}]|
|{#DEVNAME}: Disk write request avg waiting time (w_await)|<p>This formula contains two Boolean expressions that evaluate to 1 or 0 in order to set the calculated metric to zero and to avoid the exception - division by zero.</p>|Calculated|vfs.dev.write.await[{#DEVNAME}]|

### Trigger prototypes for Block devices discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: {#DEVNAME}: Disk read/write request responses are too high|<p>This trigger might indicate the disk `{#DEVNAME}` saturation.</p>|`min(/Linux by Zabbix agent active/vfs.dev.read.await[{#DEVNAME}],15m) > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"} or min(/Linux by Zabbix agent active/vfs.dev.write.await[{#DEVNAME}],15m) > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}`|Warning|**Manual close**: Yes|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>The discovery of mounted filesystems with different types.</p>|Dependent item|vfs.fs.dependent.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FS [{#FSNAME}]: Get data|<p>Intermediate data of `{#FSNAME}` filesystem.</p>|Dependent item|vfs.fs.dependent[{#FSNAME},data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.fsname=='{#FSNAME}')].first()`</p></li></ul>|
|FS [{#FSNAME}]: Option: Read-only|<p>The filesystem is mounted as read-only. It is available only for Zabbix agents 6.4 and higher.</p>|Dependent item|vfs.fs.dependent[{#FSNAME},readonly]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.options`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Regular expression: `(?:^|,)ro\b 1`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|FS [{#FSNAME}]: Space: Used|<p>Used storage expressed in bytes.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.used`</p></li></ul>|
|FS [{#FSNAME}]: Space: Total|<p>Total space expressed in bytes.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.total`</p></li></ul>|
|FS [{#FSNAME}]: Space: Available|<p>Available storage space expressed in bytes.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.free`</p></li></ul>|
|FS [{#FSNAME}]: Space: Used, in %|<p>Calculated as the percentage of currently used space compared to the maximum available space.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},pused]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.pused`</p></li></ul>|
|FS [{#FSNAME}]: Inodes: Free, in %|<p>Free metadata space expressed in %.</p>|Dependent item|vfs.fs.dependent.inode[{#FSNAME},pfree]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.inodes.pfree`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: FS [{#FSNAME}]: Filesystem has become read-only|<p>The filesystem has become read-only, possibly due to an I/O error. Available only for Zabbix agents 6.4 and higher.</p>|`last(/Linux by Zabbix agent active/vfs.fs.dependent[{#FSNAME},readonly],#2)=0 and last(/Linux by Zabbix agent active/vfs.fs.dependent[{#FSNAME},readonly])=1`|Average|**Manual close**: Yes|
|Linux: FS [{#FSNAME}]: Space is critically low|<p>The volume's space usage exceeds the `{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}%` limit.<br>The trigger expression is based on the current used and maximum available spaces.<br>Event name represents the total volume space, which can differ from the maximum available space, depending on the filesystem type.</p>|`min(/Linux by Zabbix agent active/vfs.fs.dependent.size[{#FSNAME},pused],5m)>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`|Average|**Manual close**: Yes|
|Linux: FS [{#FSNAME}]: Space is low|<p>The volume's space usage exceeds the `{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}%` limit.<br>The trigger expression is based on the current used and maximum available spaces.<br>Event name represents the total volume space, which can differ from the maximum available space, depending on the filesystem type.</p>|`min(/Linux by Zabbix agent active/vfs.fs.dependent.size[{#FSNAME},pused],5m)>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: FS [{#FSNAME}]: Space is critically low</li></ul>|
|Linux: FS [{#FSNAME}]: Running out of free inodes|<p>Disk writing may fail if index nodes are exhausted, leading to error messages like "No space left on device" or "Disk is full", despite available free space.</p>|`min(/Linux by Zabbix agent active/vfs.fs.dependent.inode[{#FSNAME},pfree],5m)<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}`|Average||
|Linux: FS [{#FSNAME}]: Running out of free inodes|<p>Disk writing may fail if index nodes are exhausted, leading to error messages like "No space left on device" or "Disk is full", despite available free space.</p>|`min(/Linux by Zabbix agent active/vfs.fs.dependent.inode[{#FSNAME},pfree],5m)<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}`|Warning|**Depends on**:<br><ul><li>Linux: FS [{#FSNAME}]: Running out of free inodes</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

