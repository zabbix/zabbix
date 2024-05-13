
# Linux memory by SNMP

## Overview

### Known Issues

Description: Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMORY.UTIL.MAX}||`90`|
|{$MEMORY.AVAILABLE.MIN}||`20M`|
|{$SWAP.PFREE.MIN.WARN}||`50`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Linux: Memory utilization|<p>Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.</p>|Calculated|vm.memory.util[snmp]|
|Linux: Free memory|<p>MIB: UCD-SNMP-MIB</p>|SNMP agent|vm.memory.free[memAvailReal.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Linux: Memory (buffers)|<p>MIB: UCD-SNMP-MIB</p><p>Memory used by kernel buffers (Buffers in /proc/meminfo).</p>|SNMP agent|vm.memory.buffers[memBuffer.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Linux: Memory (cached)|<p>MIB: UCD-SNMP-MIB</p><p>Memory used by the page cache and slabs (Cached and Slab in /proc/meminfo).</p>|SNMP agent|vm.memory.cached[memCached.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Linux: Total memory|<p>MIB: UCD-SNMP-MIB</p><p>The total memory expressed in bytes.</p>|SNMP agent|vm.memory.total[memTotalReal.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Linux: Available memory|<p>Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.</p>|Calculated|vm.memory.available[snmp]|
|Linux: Total swap space|<p>MIB: UCD-SNMP-MIB</p><p>The total amount of swap space configured for this host.</p>|SNMP agent|system.swap.total[memTotalSwap.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Linux: Free swap space|<p>MIB: UCD-SNMP-MIB</p><p>The amount of swap space currently unused or available.</p>|SNMP agent|system.swap.free[memAvailSwap.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Linux: Free swap space in %|<p>The free space of the swap volume/file expressed in %.</p>|Calculated|system.swap.pfree[snmp]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Linux memory by SNMP/vm.memory.util[snmp],5m)>{$MEMORY.UTIL.MAX}`|Average|**Depends on**:<br><ul><li>Linux: Lack of available memory</li></ul>|
|Linux: Lack of available memory||`max(/Linux memory by SNMP/vm.memory.available[snmp],5m)<{$MEMORY.AVAILABLE.MIN} and last(/Linux memory by SNMP/vm.memory.total[memTotalReal.0])>0`|Average||
|Linux: High swap space usage|<p>If there is no swap configured, this trigger is ignored.</p>|`max(/Linux memory by SNMP/system.swap.pfree[snmp],5m)<{$SWAP.PFREE.MIN.WARN} and last(/Linux memory by SNMP/system.swap.total[memTotalSwap.0])>0`|Warning|**Depends on**:<br><ul><li>Linux: Lack of available memory</li><li>Linux: High memory utilization</li></ul>|

# Linux block devices by SNMP

## Overview

This template is designed for the effortless deployment of Linux block devices monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.DEV.DEVNAME.MATCHES}|<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p>|`.+`|
|{$VFS.DEV.DEVNAME.NOT_MATCHES}|<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p>|`Macro too long. Please see the template.`|

### LLD rule Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Block devices discovery|<p>Block devices are discovered from UCD-DISKIO-MIB::diskIOTable (http://net-snmp.sourceforge.net/docs/mibs/ucdDiskIOMIB.html#diskIOTable)</p>|SNMP agent|vfs.dev.discovery[snmp]|

### Item prototypes for Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DEVNAME}: Disk read rate|<p>MIB: UCD-DISKIO-MIB</p><p>The number of read accesses from this device since boot.</p>|SNMP agent|vfs.dev.read.rate[diskIOReads.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#DEVNAME}: Disk write rate|<p>MIB: UCD-DISKIO-MIB</p><p>The number of write accesses from this device since boot.</p>|SNMP agent|vfs.dev.write.rate[diskIOWrites.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#DEVNAME}: Disk utilization|<p>MIB: UCD-DISKIO-MIB</p><p>The 1 minute average load of disk (%)</p>|SNMP agent|vfs.dev.util[diskIOLA1.{#SNMPINDEX}]|

# Linux CPU by SNMP

## Overview

This template is designed for the effortless deployment of Linux CPU monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}||`90`|
|{$LOAD_AVG_PER_CPU.MAX.WARN}|<p>Load per CPU considered sustainable. Tune if needed.</p>|`1.5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Linux: Load average (1m avg)|<p>MIB: UCD-SNMP-MIB</p>|SNMP agent|system.cpu.load.avg1[laLoad.1]|
|Linux: Load average (5m avg)|<p>MIB: UCD-SNMP-MIB</p>|SNMP agent|system.cpu.load.avg5[laLoad.2]|
|Linux: Load average (15m avg)|<p>MIB: UCD-SNMP-MIB</p>|SNMP agent|system.cpu.load.avg15[laLoad.3]|
|Linux: Number of CPUs|<p>MIB: HOST-RESOURCES-MIB</p><p>Count the number of CPU cores by counting number of cores discovered in hrProcessorTable using LLD</p>|SNMP agent|system.cpu.num[snmp]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: Interrupts per second||SNMP agent|system.cpu.intr[ssRawInterrupts.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Linux: Context switches per second||SNMP agent|system.cpu.switches[ssRawContexts.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: Load average is too high|<p>The load average per CPU is too high. The system may be slow to respond.</p>|`min(/Linux CPU by SNMP/system.cpu.load.avg1[laLoad.1],5m)/last(/Linux CPU by SNMP/system.cpu.num[snmp])>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/Linux CPU by SNMP/system.cpu.load.avg5[laLoad.2])>0 and last(/Linux CPU by SNMP/system.cpu.load.avg15[laLoad.3])>0`|Average||

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>This discovery will create set of per core CPU metrics from UCD-SNMP-MIB, using {#CPU.COUNT} in preprocessing. That's the only reason why LLD is used.</p>|Dependent item|cpu.discovery[snmp]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Linux: CPU idle time|<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent doing nothing.</p>|SNMP agent|system.cpu.idle[ssCpuRawIdle.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU system time|<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running the kernel and its processes.</p>|SNMP agent|system.cpu.system[ssCpuRawSystem.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU user time|<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running users' processes that are not niced.</p>|SNMP agent|system.cpu.user[ssCpuRawUser.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU steal time|<p>MIB: UCD-SNMP-MIB</p><p>The amount of "stolen" CPU from this virtual machine by the hypervisor for other tasks, such as running another virtual machine.</p>|SNMP agent|system.cpu.steal[ssCpuRawSteal.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU softirq time|<p>MIB: UCD-SNMP-MIB</p><p>The amount of time the CPU has been servicing software interrupts.</p>|SNMP agent|system.cpu.softirq[ssCpuRawSoftIRQ.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU nice time|<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running users' processes that have been niced.</p>|SNMP agent|system.cpu.nice[ssCpuRawNice.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU iowait time|<p>MIB: UCD-SNMP-MIB</p><p>The amount of time the CPU has been waiting for I/O to complete.</p>|SNMP agent|system.cpu.iowait[ssCpuRawWait.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU interrupt time|<p>MIB: UCD-SNMP-MIB</p><p>The amount of time the CPU has been servicing hardware interrupts.</p>|SNMP agent|system.cpu.interrupt[ssCpuRawInterrupt.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU guest time|<p>MIB: UCD-SNMP-MIB</p><p>Guest time - the time spent on running a virtual CPU for a guest operating system.</p>|SNMP agent|system.cpu.guest[ssCpuRawGuest.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU guest nice time|<p>MIB: UCD-SNMP-MIB</p><p>The time spent on running a niced guest (a virtual CPU for guest operating systems under the control of the Linux kernel).</p>|SNMP agent|system.cpu.guest_nice[ssCpuRawGuestNice.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Linux: CPU utilization|<p>The CPU utilization expressed in %.</p>|Dependent item|system.cpu.util[snmp,{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `//Calculate utilization<br>return (100 - value)`</p></li></ul>|

### Trigger prototypes for CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Linux CPU by SNMP/system.cpu.util[snmp,{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}`|Warning||

# Linux filesystems by SNMP

## Overview

This template is designed for the effortless deployment of Linux filesystems monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p>|`^(/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VFS.FS.FSNAME.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p>|`.+`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p>|`^\s$`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p>|`.*(\.4\|\.9\|hrStorageFixedDisk\|hrStorageFlashMemory)$`|
|{$VFS.FS.INODE.PFREE.MIN.CRIT}||`10`|
|{$VFS.FS.INODE.PFREE.MIN.WARN}||`20`|
|{$VFS.FS.PUSED.MAX.CRIT}||`90`|
|{$VFS.FS.PUSED.MAX.WARN}||`80`|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>HOST-RESOURCES-MIB::hrStorage discovery with storage filter</p>|SNMP agent|vfs.fs.discovery[snmp]|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FSNAME}: Used space|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p>|SNMP agent|vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#FSNAME}: Total space|<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main storage allocated to a buffer pool might be modified or the amount of disk space allocated to virtual storage might be modified.</p>|SNMP agent|vfs.fs.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#FSNAME}: Space utilization|<p>The space utilization expressed in % for {#FSNAME}.</p>|Calculated|vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}]|
|{#FSNAME}: Free inodes in %|<p>MIB: UCD-SNMP-MIB</p><p>If having problems collecting this item make sure access to UCD-SNMP-MIB is allowed.</p>|SNMP agent|vfs.fs.inode.pfree[dskPercentNode.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return (100-value);`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSNAME}: Disk space is critically low|<p>The volume's space usage exceeds the `{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}` limit.</p>|`last(/Linux filesystems by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`|Average|**Manual close**: Yes|
|{#FSNAME}: Disk space is low|<p>The volume's space usage exceeds the `{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}` limit.</p>|`last(/Linux filesystems by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#FSNAME}: Disk space is critically low</li></ul>|
|{#FSNAME}: Running out of free inodes|<p>It may become impossible to write to a disk if there are no index nodes left.<br>The following error messages may be returned as symptoms, even though the free space is available:<br>- 'No space left on device';<br>- 'Disk is full'.</p>|`min(/Linux filesystems by SNMP/vfs.fs.inode.pfree[dskPercentNode.{#SNMPINDEX}],5m)<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}`|Average||
|{#FSNAME}: Running out of free inodes|<p>It may become impossible to write to a disk if there are no index nodes left.<br>The following error messages may be returned as symptoms, even though the free space is available:<br>- 'No space left on device';<br>- 'Disk is full'.</p>|`min(/Linux filesystems by SNMP/vfs.fs.inode.pfree[dskPercentNode.{#SNMPINDEX}],5m)<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}`|Warning|**Depends on**:<br><ul><li>{#FSNAME}: Running out of free inodes</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

