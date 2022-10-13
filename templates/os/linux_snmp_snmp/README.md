
# Template Module Linux memory SNMP

## Overview

For Zabbix version: 5.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMORY.AVAILABLE.MIN} |<p>-</p> |`20M` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$SWAP.PFREE.MIN.WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Memory |Memory utilization |<p>Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.</p> |CALCULATED |vm.memory.util[snmp]<p>**Expression**:</p>`(last("vm.memory.total[memTotalReal.0]")-(last("vm.memory.free[memAvailReal.0]")+last("vm.memory.buffers[memBuffer.0]")+last("vm.memory.cached[memCached.0]")))/last("vm.memory.total[memTotalReal.0]")*100` |
|Memory |Free memory |<p>MIB: UCD-SNMP-MIB</p> |SNMP |vm.memory.free[memAvailReal.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Memory (buffers) |<p>MIB: UCD-SNMP-MIB</p><p>Memory used by kernel buffers (Buffers in /proc/meminfo).</p> |SNMP |vm.memory.buffers[memBuffer.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Memory (cached) |<p>MIB: UCD-SNMP-MIB</p><p>Memory used by the page cache and slabs (Cached and Slab in /proc/meminfo).</p> |SNMP |vm.memory.cached[memCached.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Total memory |<p>MIB: UCD-SNMP-MIB</p><p>Total memory in Bytes.</p> |SNMP |vm.memory.total[memTotalReal.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Available memory |<p>Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.</p> |CALCULATED |vm.memory.available[snmp]<p>**Expression**:</p>`last("vm.memory.free[memAvailReal.0]")+last("vm.memory.buffers[memBuffer.0]")+last("vm.memory.cached[memCached.0]")` |
|Memory |Total swap space |<p>MIB: UCD-SNMP-MIB</p><p>The total amount of swap space configured for this host.</p> |SNMP |system.swap.total[memTotalSwap.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Free swap space |<p>MIB: UCD-SNMP-MIB</p><p>The amount of swap space currently unused or available.</p> |SNMP |system.swap.free[memAvailSwap.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Free swap space in % |<p>The free space of swap volume/file in percent.</p> |CALCULATED |system.swap.pfree[snmp]<p>**Expression**:</p>`last("system.swap.free[memAvailSwap.0]")/last("system.swap.total[memTotalSwap.0]")*100` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`{TEMPLATE_NAME:vm.memory.util[snmp].min(5m)}>{$MEMORY.UTIL.MAX}` |AVERAGE |<p>**Depends on**:</p><p>- Lack of available memory (<{$MEMORY.AVAILABLE.MIN} of {ITEM.VALUE2})</p> |
|Lack of available memory (<{$MEMORY.AVAILABLE.MIN} of {ITEM.VALUE2}) |<p>-</p> |`{TEMPLATE_NAME:vm.memory.available[snmp].max(5m)}<{$MEMORY.AVAILABLE.MIN} and {TEMPLATE_NAME:vm.memory.total[memTotalReal.0].last()}>0` |AVERAGE | |
|High swap space usage (less than {$SWAP.PFREE.MIN.WARN}% free) |<p>This trigger is ignored, if there is no swap configured.</p> |`{TEMPLATE_NAME:system.swap.pfree[snmp].max(5m)}<{$SWAP.PFREE.MIN.WARN} and {TEMPLATE_NAME:system.swap.total[memTotalSwap.0].last()}>0` |WARNING |<p>**Depends on**:</p><p>- High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m)</p><p>- Lack of available memory (<{$MEMORY.AVAILABLE.MIN} of {ITEM.VALUE2})</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

## Known Issues

- Description: Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.

# Template Module Linux block devices SNMP

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

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Block devices discovery |<p>Block devices are discovered from UCD-DISKIO-MIB::diskIOTable (http://net-snmp.sourceforge.net/docs/mibs/ucdDiskIOMIB.html#diskIOTable)</p> |SNMP |vfs.dev.discovery[snmp]<p>**Filter**:</p>AND <p>- A: {#DEVNAME} MATCHES_REGEX `{$VFS.DEV.DEVNAME.MATCHES}`</p><p>- B: {#DEVNAME} NOT_MATCHES_REGEX `{$VFS.DEV.DEVNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Storage |{#DEVNAME}: Disk read rate |<p>MIB: UCD-DISKIO-MIB</p><p>The number of read accesses from this device since boot.</p> |SNMP |vfs.dev.read.rate[diskIOReads.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Storage |{#DEVNAME}: Disk write rate |<p>MIB: UCD-DISKIO-MIB</p><p>The number of write accesses from this device since boot.</p> |SNMP |vfs.dev.write.rate[diskIOWrites.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Storage |{#DEVNAME}: Disk utilization |<p>MIB: UCD-DISKIO-MIB</p><p>The 1 minute average load of disk (%)</p> |SNMP |vfs.dev.util[diskIOLA1.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Template Module Linux CPU SNMP

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

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|CPU discovery |<p>This discovery will create set of per core CPU metrics from UCD-SNMP-MIB, using {#CPU.COUNT} in preprocessing. That's the only reason why LLD is used.</p> |DEPENDENT |cpu.discovery[snmp]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |Load average (1m avg) |<p>MIB: UCD-SNMP-MIB</p> |SNMP |system.cpu.load.avg1[laLoad.1] |
|CPU |Load average (5m avg) |<p>MIB: UCD-SNMP-MIB</p> |SNMP |system.cpu.load.avg5[laLoad.2] |
|CPU |Load average (15m avg) |<p>MIB: UCD-SNMP-MIB</p> |SNMP |system.cpu.load.avg15[laLoad.3] |
|CPU |Number of CPUs |<p>MIB: HOST-RESOURCES-MIB</p><p>Count the number of CPU cores by counting number of cores discovered in hrProcessorTable using LLD</p> |SNMP |system.cpu.num[snmp]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `//count the number of cores return JSON.parse(value).length; `</p> |
|CPU |Interrupts per second |<p>-</p> |SNMP |system.cpu.intr[ssRawInterrupts.0]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|CPU |Context switches per second |<p>-</p> |SNMP |system.cpu.switches[ssRawContexts.0]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|CPU |CPU idle time |<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent doing nothing.</p> |SNMP |system.cpu.idle[ssCpuRawIdle.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |CPU system time |<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running the kernel and its processes.</p> |SNMP |system.cpu.system[ssCpuRawSystem.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |CPU user time |<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running users' processes that are not niced.</p> |SNMP |system.cpu.user[ssCpuRawUser.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |CPU steal time |<p>MIB: UCD-SNMP-MIB</p><p>The amount of CPU 'stolen' from this virtual machine by the hypervisor for other tasks (such as running another virtual machine).</p> |SNMP |system.cpu.steal[ssCpuRawSteal.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |CPU softirq time |<p>MIB: UCD-SNMP-MIB</p><p>The amount of time the CPU has been servicing software interrupts.</p> |SNMP |system.cpu.softirq[ssCpuRawSoftIRQ.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |CPU nice time |<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running users' processes that have been niced.</p> |SNMP |system.cpu.nice[ssCpuRawNice.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |CPU iowait time |<p>MIB: UCD-SNMP-MIB</p><p>Amount of time the CPU has been waiting for I/O to complete.</p> |SNMP |system.cpu.iowait[ssCpuRawWait.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |CPU interrupt time |<p>MIB: UCD-SNMP-MIB</p><p>The amount of time the CPU has been servicing hardware interrupts.</p> |SNMP |system.cpu.interrupt[ssCpuRawInterrupt.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |CPU guest time |<p>MIB: UCD-SNMP-MIB</p><p>Guest  time (time  spent  running  a  virtual  CPU  for  a  guest  operating  system).</p> |SNMP |system.cpu.guest[ssCpuRawGuest.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |CPU guest nice time |<p>MIB: UCD-SNMP-MIB</p><p>Time spent running a niced guest (virtual CPU for guest operating systems under the control of the Linux kernel).</p> |SNMP |system.cpu.guest_nice[ssCpuRawGuestNice.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND<p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |CPU utilization |<p>CPU utilization in %.</p> |DEPENDENT |system.cpu.util[snmp,{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `//Calculate utilization return (100 - value) `</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Load average is too high (per CPU load over {$LOAD_AVG_PER_CPU.MAX.WARN} for 5m) |<p>Per CPU load average is too high. Your system may be slow to respond.</p> |`{TEMPLATE_NAME:system.cpu.load.avg1[laLoad.1].min(5m)}/{TEMPLATE_NAME:system.cpu.num[snmp].last()}>{$LOAD_AVG_PER_CPU.MAX.WARN} and {TEMPLATE_NAME:system.cpu.load.avg5[laLoad.2].last()}>0 and {TEMPLATE_NAME:system.cpu.load.avg15[laLoad.3].last()}>0` |AVERAGE | |
|High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`{TEMPLATE_NAME:system.cpu.util[snmp,{#SNMPINDEX}].min(5m)}>{$CPU.UTIL.CRIT}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Template Module Linux filesystems SNMP

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
|{$VFS.FS.FSTYPE.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level</p> |`.*(\.4|\.9|hrStorageFixedDisk|hrStorageFlashMemory)$` |
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
|Mounted filesystem discovery |<p>HOST-RESOURCES-MIB::hrStorage discovery with storage filter</p> |SNMP |vfs.fs.discovery[snmp]<p>**Filter**:</p>AND <p>- A: {#FSTYPE} MATCHES_REGEX `{$VFS.FS.FSTYPE.MATCHES}`</p><p>- B: {#FSTYPE} NOT_MATCHES_REGEX `{$VFS.FS.FSTYPE.NOT_MATCHES}`</p><p>- C: {#FSNAME} MATCHES_REGEX `{$VFS.FS.FSNAME.MATCHES}`</p><p>- D: {#FSNAME} NOT_MATCHES_REGEX `{$VFS.FS.FSNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Storage |{#FSNAME}: Used space |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p> |SNMP |vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ALLOC_UNITS}`</p> |
|Storage |{#FSNAME}: Total space |<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main storage allocated to a buffer pool might be modified or the amount of disk space allocated to virtual storage might be modified.</p> |SNMP |vfs.fs.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ALLOC_UNITS}`</p> |
|Storage |{#FSNAME}: Space utilization |<p>Space utilization in % for {#FSNAME}</p> |CALCULATED |vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}]<p>**Expression**:</p>`(last("vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]")/last("vfs.fs.total[hrStorageSize.{#SNMPINDEX}]"))*100` |
|Storage |{#FSNAME}: Free inodes in % |<p>MIB: UCD-SNMP-MIB</p><p>If having problems collecting this item make sure access to UCD-SNMP-MIB is allowed.</p> |SNMP |vfs.fs.inode.pfree[dskPercentNode.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return (100-value);`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#FSNAME}: Disk space is critically low (used > {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}%) |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`{TEMPLATE_NAME:vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}].last()}>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and (({TEMPLATE_NAME:vfs.fs.total[hrStorageSize.{#SNMPINDEX}].last()}-{TEMPLATE_NAME:vfs.fs.used[hrStorageUsed.{#SNMPINDEX}].last()})<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or {TEMPLATE_NAME:vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}].timeleft(1h,,100)}<1d)` |AVERAGE |<p>Manual close: YES</p> |
|{#FSNAME}: Disk space is low (used > {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}%) |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`{TEMPLATE_NAME:vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}].last()}>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and (({TEMPLATE_NAME:vfs.fs.total[hrStorageSize.{#SNMPINDEX}].last()}-{TEMPLATE_NAME:vfs.fs.used[hrStorageUsed.{#SNMPINDEX}].last()})<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or {TEMPLATE_NAME:vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}].timeleft(1h,,100)}<1d)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#FSNAME}: Disk space is critically low (used > {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}%)</p> |
|{#FSNAME}: Running out of free inodes (free < {$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}%) |<p>It may become impossible to write to disk if there are no index nodes left.</p><p>As symptoms, 'No space left on device' or 'Disk is full' errors may be seen even though free space is available.</p> |`{TEMPLATE_NAME:vfs.fs.inode.pfree[dskPercentNode.{#SNMPINDEX}].min(5m)}<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}` |AVERAGE | |
|{#FSNAME}: Running out of free inodes (free < {$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}%) |<p>It may become impossible to write to disk if there are no index nodes left.</p><p>As symptoms, 'No space left on device' or 'Disk is full' errors may be seen even though free space is available.</p> |`{TEMPLATE_NAME:vfs.fs.inode.pfree[dskPercentNode.{#SNMPINDEX}].min(5m)}<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}` |WARNING |<p>**Depends on**:</p><p>- {#FSNAME}: Running out of free inodes (free < {$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}%)</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Template OS Linux SNMP

## Overview

For Zabbix version: 5.0 and higher  

## Setup

Install snmpd agent on Linux OS, enable SNMPv2.  

Make sure access to UCD-SNMP-MIB is allowed from Zabbix server/proxy host, since,  
by default, snmpd (for example, in Ubuntu) limits access to basic system information only:

```text
rocommunity public  default    -V systemonly
```

Make sure you change that in order to read metrics of UCD-SNMP-MIB and UCD-DISKIO-MIB. Please refer to the documentation:  
http://www.net-snmp.org/wiki/index.php/Vacm  

You can also try to use `snmpconf`:
  
http://www.net-snmp.org/wiki/index.php/TUT:snmpd_configuration

Change {$SNMP_COMMUNITY} on the host level in Zabbix.


## Zabbix configuration

No specific Zabbix configuration is required.


## Template links

|Name|
|----|
|EtherLike-MIB SNMP |
|Generic SNMP |
|Interfaces SNMP |
|Linux CPU SNMP |
|Linux block devices SNMP |
|Linux filesystems SNMP |
|Linux memory SNMP |

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


## References

https://docs.fedoraproject.org/en-US/Fedora/26/html/System_Administrators_Guide/sect-System_Monitoring_Tools-Net-SNMP-Retrieving.html
