
# Windows CPU by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.INTERRUPT.CRIT.MAX} |<p>The critical threshold of the % Interrupt Time counter.</p> |`50` |
|{$CPU.PRIV.CRIT.MAX} |<p>The threshold of the % Privileged Time counter.</p> |`30` |
|{$CPU.QUEUE.CRIT.MAX} |<p>The threshold of the Processor Queue Length counter.</p> |`3` |
|{$CPU.UTIL.CRIT} |<p>The critical threshold of the CPU utilization in %.</p> |`90` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>CPU utilization in %.</p> |ZABBIX_PASSIVE |system.cpu.util |
|CPU |CPU interrupt time |<p>The Processor Information\% Interrupt Time is the time the processor spends receiving and servicing</p><p>hardware interrupts during sample intervals. This value is an indirect indicator of the activity of</p><p>devices that generate interrupts, such as the system clock, the mouse, disk drivers, data communication</p><p>lines, network interface cards and other peripheral devices. This is an easy way to identify a potential</p><p>hardware failure. This should never be higher than 20%.</p> |ZABBIX_PASSIVE |perf_counter_en["\Processor Information(_total)\% Interrupt Time"] |
|CPU |Context switches per second |<p>Context Switches/sec is the combined rate at which all processors on the computer are switched from one thread to another.</p><p>Context switches occur when a running thread voluntarily relinquishes the processor, is preempted by a higher priority ready thread, or switches between user-mode and privileged (kernel) mode to use an Executive or subsystem service.</p><p>It is the sum of Thread\\Context Switches/sec for all threads running on all processors in the computer and is measured in numbers of switches.</p><p>There are context switch counters on the System and Thread objects. This counter displays the difference between the values observed in the last two samples, divided by the duration of the sample interval.</p> |ZABBIX_PASSIVE |perf_counter_en["\System\Context Switches/sec"] |
|CPU |CPU privileged time |<p>The Processor Information\% Privileged Time counter shows the percent of time that the processor is spent</p><p>executing in Kernel (or Privileged) mode. Privileged mode includes services interrupts inside Interrupt</p><p>Service Routines (ISRs), executing Deferred Procedure Calls (DPCs), Device Driver calls and other kernel-mode</p><p>functions of the Windows® Operating System.</p> |ZABBIX_PASSIVE |perf_counter_en["\Processor Information(_total)\% Privileged Time"] |
|CPU |CPU DPC time |<p>Processor DPC time is the time that a single processor spent receiving and servicing deferred procedure</p><p>calls (DPCs). DPCs are interrupts that run at a lower priority than standard interrupts. % DPC Time is a</p><p>component of % Privileged Time because DPCs are executed in privileged mode. If a high % DPC Time is</p><p>sustained, there may be a processor bottleneck or an application or hardware related issue that can</p><p>significantly diminish overall system performance.</p> |ZABBIX_PASSIVE |perf_counter_en["\Processor Information(_total)\% DPC Time"] |
|CPU |CPU user time |<p>The Processor Information\% User Time counter shows the percent of time that the processor(s) is spent executing</p><p>in User mode.</p> |ZABBIX_PASSIVE |perf_counter_en["\Processor Information(_total)\% User Time"] |
|CPU |Number of cores |<p>The number of logical processors available on the computer.</p> |ZABBIX_PASSIVE |wmi.get[root/cimv2,"Select NumberOfLogicalProcessors from Win32_ComputerSystem"] |
|CPU |CPU queue length |<p>The Processor Queue Length shows the number of threads that are observed as delayed in the processor Ready Queue</p><p>and are waiting to be executed.</p> |ZABBIX_PASSIVE |perf_counter_en["\System\Processor Queue Length"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Windows CPU by Zabbix agent/system.cpu.util,5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|CPU interrupt time is too high |<p>"The CPU Interrupt Time in the last 5 minutes exceeds {$CPU.INTERRUPT.CRIT.MAX}%."</p><p>The Processor Information\% Interrupt Time is the time the processor spends receiving and servicing</p><p>hardware interrupts during sample intervals. This value is an indirect indicator of the activity of</p><p>devices that generate interrupts, such as the system clock, the mouse, disk drivers, data communication</p><p>lines, network interface cards and other peripheral devices. This is an easy way to identify a potential</p><p>hardware failure. This should never be higher than 20%.</p> |`min(/Windows CPU by Zabbix agent/perf_counter_en["\Processor Information(_total)\% Interrupt Time"],5m)>{$CPU.INTERRUPT.CRIT.MAX}` |WARNING |<p>**Depends on**:</p><p>- High CPU utilization</p> |
|CPU privileged time is too high |<p>The CPU privileged time in the last 5 minutes exceeds {$CPU.PRIV.CRIT.MAX}%.</p> |`min(/Windows CPU by Zabbix agent/perf_counter_en["\Processor Information(_total)\% Privileged Time"],5m)>{$CPU.PRIV.CRIT.MAX}` |WARNING |<p>**Depends on**:</p><p>- CPU interrupt time is too high</p><p>- High CPU utilization</p> |
|CPU queue length is too high |<p>The CPU Queue Length in the last 5 minutes exceeds {$CPU.QUEUE.CRIT.MAX}. According to actual observations, PQL should not exceed the number of cores * 2. To fine-tune the conditions, use the macro {$CPU.QUEUE.CRIT.MAX }.</p> |`min(/Windows CPU by Zabbix agent/perf_counter_en["\System\Processor Queue Length"],5m) - last(/Windows CPU by Zabbix agent/wmi.get[root/cimv2,"Select NumberOfLogicalProcessors from Win32_ComputerSystem"]) * 2 > {$CPU.QUEUE.CRIT.MAX}` |WARNING |<p>**Depends on**:</p><p>- High CPU utilization</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Windows memory by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEM.PAGE_SEC.CRIT.MAX} |<p>The warning threshold of the Memory Pages/sec counter.</p> |`1000` |
|{$MEM.PAGE_TABLE_CRIT.MIN} |<p>The warning threshold of the Free System Page Table Entries counter.</p> |`5000` |
|{$MEMORY.UTIL.MAX} |<p>The warning threshold of the Memory util item.</p> |`90` |
|{$SWAP.PFREE.MIN.WARN} |<p>The warning threshold of the minimum free swap.</p> |`20` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Memory |Used memory |<p>Used memory in Bytes.</p> |ZABBIX_PASSIVE |vm.memory.size[used] |
|Memory |Total memory |<p>Total memory in Bytes.</p> |ZABBIX_PASSIVE |vm.memory.size[total] |
|Memory |Memory utilization |<p>Memory utilization in %.</p> |CALCULATED |vm.memory.util<p>**Expression**:</p>`last(//vm.memory.size[used]) / last(//vm.memory.size[total]) * 100` |
|Memory |Cache bytes |<p>Cache Bytes is the sum of the Memory\\System Cache Resident Bytes, Memory\\System Driver Resident Bytes,</p><p>Memory\\System Code Resident Bytes, and Memory\\Pool Paged Resident Bytes counters. This counter displays</p><p>the last observed value only; it is not an average.</p> |ZABBIX_PASSIVE |perf_counter_en["\Memory\Cache Bytes"] |
|Memory |Free swap space |<p>The free space of swap volume/file in bytes.</p> |CALCULATED |system.swap.free<p>**Expression**:</p>`last(//system.swap.size[,total]) - last(//system.swap.size[,total]) / 100 * last(//perf_counter_en["\Paging file(_Total)\% Usage"])` |
|Memory |Free swap space in % |<p>The free space of swap volume/file in percent.</p> |DEPENDENT |system.swap.pfree<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return (100 - value)`</p> |
|Memory |Used swap space in % |<p>The used space of swap volume/file in percent.</p> |ZABBIX_PASSIVE |perf_counter_en["\Paging file(_Total)\% Usage"] |
|Memory |Total swap space |<p>The total space of swap volume/file in bytes.</p> |ZABBIX_PASSIVE |system.swap.size[,total] |
|Memory |Free system page table entries |<p>This indicates the number of page table entries not currently in use by the system. If the number is less</p><p>than 5,000, there may well be a memory leak or you running out of memory.</p> |ZABBIX_PASSIVE |perf_counter_en["\Memory\Free System Page Table Entries"] |
|Memory |Memory page faults per second |<p>Page Faults/sec is the average number of pages faulted per second. It is measured in number of pages</p><p>faulted per second because only one page is faulted in each fault operation, hence this is also equal</p><p>to the number of page fault operations. This counter includes both hard faults (those that require</p><p>disk access) and soft faults (where the faulted page is found elsewhere in physical memory.) Most</p><p>processors can handle large numbers of soft faults without significant consequence. However, hard faults,</p><p>which require disk access, can cause significant delays.</p> |ZABBIX_PASSIVE |perf_counter_en["\Memory\Page Faults/sec"] |
|Memory |Memory pages per second |<p>This measures the rate at which pages are read from or written to disk to resolve hard page faults.</p><p>If the value is greater than 1,000, as a result of excessive paging, there may be a memory leak.</p> |ZABBIX_PASSIVE |perf_counter_en["\Memory\Pages/sec"] |
|Memory |Memory pool non-paged |<p>This measures the size, in bytes, of the non-paged pool. This is an area of system memory for objects</p><p>that cannot be written to disk but instead must remain in physical memory as long as they are allocated.</p><p>There is a possible memory leak if the value is greater than 175MB (or 100MB with the /3GB switch).</p><p>A typical Event ID 2019 is recorded in the system event log.</p> |ZABBIX_PASSIVE |perf_counter_en["\Memory\Pool Nonpaged Bytes"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High memory utilization |<p>The system is running out of free memory.</p> |`min(/Windows memory by Zabbix agent/vm.memory.util,5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|High swap space usage |<p>This trigger is ignored, if there is no swap configured</p> |`max(/Windows memory by Zabbix agent/system.swap.pfree,5m)<{$SWAP.PFREE.MIN.WARN} and last(/Windows memory by Zabbix agent/system.swap.size[,total])>0` |WARNING |<p>**Depends on**:</p><p>- High memory utilization</p> |
|Number of free system page table entries is too low |<p>The Memory Free System Page Table Entries is less than {$MEM.PAGE_TABLE_CRIT.MIN} for 5 minutes. If the number is less than 5,000, there may well be a memory leak.</p> |`max(/Windows memory by Zabbix agent/perf_counter_en["\Memory\Free System Page Table Entries"],5m)<{$MEM.PAGE_TABLE_CRIT.MIN}` |WARNING |<p>**Depends on**:</p><p>- High memory utilization</p> |
|The Memory Pages/sec is too high |<p>The Memory Pages/sec in the last 5 minutes exceeds {$MEM.PAGE_SEC.CRIT.MAX}. If the value is greater than 1,000, as a result of excessive paging, there may be a memory leak.</p> |`min(/Windows memory by Zabbix agent/perf_counter_en["\Memory\Pages/sec"],5m)>{$MEM.PAGE_SEC.CRIT.MAX}` |WARNING |<p>**Depends on**:</p><p>- High memory utilization</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Windows filesystems by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.FS.FREE.MIN.CRIT} |<p>The critical threshold of the filesystem utilization.</p> |`5G` |
|{$VFS.FS.FREE.MIN.WARN} |<p>The warning threshold of the filesystem utilization.</p> |`10G` |
|{$VFS.FS.FSDRIVETYPE.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`fixed` |
|{$VFS.FS.FSDRIVETYPE.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`^\s$` |
|{$VFS.FS.FSNAME.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`.*` |
|{$VFS.FS.FSNAME.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`^(?:/dev|/sys|/run|/proc|.+/shm$)` |
|{$VFS.FS.FSTYPE.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`.*` |
|{$VFS.FS.FSTYPE.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`^\s$` |
|{$VFS.FS.PUSED.MAX.CRIT} |<p>The critical threshold of the filesystem utilization in percent.</p> |`90` |
|{$VFS.FS.PUSED.MAX.WARN} |<p>The warning threshold of the filesystem utilization in percent.</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Mounted filesystem discovery |<p>Discovery of file systems of different types.</p> |ZABBIX_PASSIVE |vfs.fs.discovery<p>**Filter**:</p>AND <p>- {#FSTYPE} MATCHES_REGEX `{$VFS.FS.FSTYPE.MATCHES}`</p><p>- {#FSTYPE} NOT_MATCHES_REGEX `{$VFS.FS.FSTYPE.NOT_MATCHES}`</p><p>- {#FSNAME} MATCHES_REGEX `{$VFS.FS.FSNAME.MATCHES}`</p><p>- {#FSNAME} NOT_MATCHES_REGEX `{$VFS.FS.FSNAME.NOT_MATCHES}`</p><p>- {#FSDRIVETYPE} MATCHES_REGEX `{$VFS.FS.FSDRIVETYPE.MATCHES}`</p><p>- {#FSDRIVETYPE} NOT_MATCHES_REGEX `{$VFS.FS.FSDRIVETYPE.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Filesystems |{#FSLABEL}({#FSNAME}): Used space |<p>Used storage in Bytes</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},used] |
|Filesystems |{#FSLABEL}({#FSNAME}): Total space |<p>Total space in Bytes</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},total] |
|Filesystems |{#FSLABEL}({#FSNAME}): Space utilization |<p>Space utilization in % for {#FSNAME}</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},pused] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#FSLABEL}({#FSNAME}): Disk space is critically low |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`last(/Windows filesystems by Zabbix agent/vfs.fs.size[{#FSNAME},pused])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/Windows filesystems by Zabbix agent/vfs.fs.size[{#FSNAME},total])-last(/Windows filesystems by Zabbix agent/vfs.fs.size[{#FSNAME},used]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/Windows filesystems by Zabbix agent/vfs.fs.size[{#FSNAME},pused],1h,100)<1d) ` |AVERAGE |<p>Manual close: YES</p> |
|{#FSLABEL}({#FSNAME}): Disk space is low |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`last(/Windows filesystems by Zabbix agent/vfs.fs.size[{#FSNAME},pused])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/Windows filesystems by Zabbix agent/vfs.fs.size[{#FSNAME},total])-last(/Windows filesystems by Zabbix agent/vfs.fs.size[{#FSNAME},used]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/Windows filesystems by Zabbix agent/vfs.fs.size[{#FSNAME},pused],1h,100)<1d) ` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#FSLABEL}({#FSNAME}): Disk space is critically low</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Windows physical disks by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.DEV.DEVNAME.MATCHES} |<p>This macro is used in physical disks discovery. Can be overridden on the host or linked template level.</p> |`.*` |
|{$VFS.DEV.DEVNAME.NOT_MATCHES} |<p>This macro is used in physical disks discovery. Can be overridden on the host or linked template level.</p> |`_Total` |
|{$VFS.DEV.READ.AWAIT.WARN} |<p>Disk read average response time (in s) before the trigger would fire.</p> |`0.02` |
|{$VFS.DEV.UTIL.MAX.WARN} |<p>The warning threshold of disk time utilization in percent.</p> |`95` |
|{$VFS.DEV.WRITE.AWAIT.WARN} |<p>Disk write average response time (in s) before the trigger would fire.</p> |`0.02` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Physical disks discovery |<p>Discovery of installed physical disks.</p> |ZABBIX_PASSIVE |perf_instance_en.discovery[PhysicalDisk]<p>**Preprocessing**:</p><p>- STR_REPLACE: `{#INSTANCE} {#DEVNAME}`</p><p>**Filter**:</p>AND <p>- {#DEVNAME} MATCHES_REGEX `{$VFS.DEV.DEVNAME.MATCHES}`</p><p>- {#DEVNAME} NOT_MATCHES_REGEX `{$VFS.DEV.DEVNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Storage |{#DEVNAME}: Disk read rate |<p>Rate of read operations on the disk.</p> |ZABBIX_PASSIVE |perf_counter_en["\PhysicalDisk({#DEVNAME})\Disk Reads/sec",60] |
|Storage |{#DEVNAME}: Disk write rate |<p>Rate of write operations on the disk.</p> |ZABBIX_PASSIVE |perf_counter_en["\PhysicalDisk({#DEVNAME})\Disk Writes/sec",60] |
|Storage |{#DEVNAME}: Disk average queue size (avgqu-sz) |<p>Current average disk queue, the number of requests outstanding on the disk at the time the performance data is collected.</p> |ZABBIX_PASSIVE |perf_counter_en["\PhysicalDisk({#DEVNAME})\Current Disk Queue Length",60] |
|Storage |{#DEVNAME}: Disk utilization by idle time |<p>This item is the percentage of elapsed time that the selected disk drive was busy servicing read or writes requests based on idle time.</p> |ZABBIX_PASSIVE |perf_counter_en["\PhysicalDisk({#DEVNAME})\% Idle Time",60]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return (100 - value)`</p> |
|Storage |{#DEVNAME}: Disk read request avg waiting time |<p>The average time for read requests issued to the device to be served. This includes the time spent by the requests in queue and the time spent servicing them.</p> |ZABBIX_PASSIVE |perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Read",60] |
|Storage |{#DEVNAME}: Disk write request avg waiting time |<p>The average time for write requests issued to the device to be served. This includes the time spent by the requests in queue and the time spent servicing them.</p> |ZABBIX_PASSIVE |perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Write",60] |
|Storage |{#DEVNAME}: Average disk read queue length |<p>Average disk read queue, the number of requests outstanding on the disk at the time the performance data is collected.</p> |ZABBIX_PASSIVE |perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk Read Queue Length",60] |
|Storage |{#DEVNAME}: Average disk write queue length |<p>Average disk write queue, the number of requests outstanding on the disk at the time the performance data is collected.</p> |ZABBIX_PASSIVE |perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk Write Queue Length",60] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#DEVNAME}: Disk is overloaded |<p>The disk appears to be under heavy load</p> |`min(/Windows physical disks by Zabbix agent/perf_counter_en["\PhysicalDisk({#DEVNAME})\% Idle Time",60],15m)>{$VFS.DEV.UTIL.MAX.WARN}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#DEVNAME}: Disk read request responses are too high</p><p>- {#DEVNAME}: Disk write request responses are too high</p> |
|{#DEVNAME}: Disk read request responses are too high |<p>This trigger might indicate disk {#DEVNAME} saturation.</p> |`min(/Windows physical disks by Zabbix agent/perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Read",60],15m) > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"}` |WARNING |<p>Manual close: YES</p> |
|{#DEVNAME}: Disk write request responses are too high |<p>This trigger might indicate disk {#DEVNAME} saturation.</p> |`min(/Windows physical disks by Zabbix agent/perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Write",60],15m) > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Windows generic by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SYSTEM.FUZZYTIME.MAX} |<p>The threshold for difference of system time in seconds.</p> |`60` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|General |System local time |<p>System local time of the host.</p> |ZABBIX_PASSIVE |system.localtime |
|General |System name |<p>System host name.</p> |ZABBIX_PASSIVE |system.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |System description |<p>System description of the host.</p> |ZABBIX_PASSIVE |system.uname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |Number of processes |<p>The number of processes.</p> |ZABBIX_PASSIVE |proc.num[] |
|General |Number of threads |<p>The number of threads used by all running processes.</p> |ZABBIX_PASSIVE |perf_counter_en["\System\Threads"] |
|Inventory |Operating system architecture |<p>Operating system architecture of the host.</p> |ZABBIX_PASSIVE |system.sw.arch<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Status |Uptime |<p>System uptime in 'N days, hh:mm:ss' format.</p> |ZABBIX_PASSIVE |system.uptime |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|System time is out of sync |<p>The host system time is different from the Zabbix server time.</p> |`fuzzytime(/Windows generic by Zabbix agent/system.localtime,{$SYSTEM.FUZZYTIME.MAX})=0` |WARNING |<p>Manual close: YES</p> |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Windows generic by Zabbix agent/system.hostname,#1)<>last(/Windows generic by Zabbix agent/system.hostname,#2) and length(last(/Windows generic by Zabbix agent/system.hostname))>0` |INFO |<p>Manual close: YES</p> |
|Host has been restarted |<p>The device uptime is less than 10 minutes.</p> |`last(/Windows generic by Zabbix agent/system.uptime)<10m` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Windows network by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IF.ERRORS.WARN} |<p>-</p> |`2` |
|{$IF.UTIL.MAX} |<p>-</p> |`90` |
|{$IFCONTROL} |<p>-</p> |`1` |
|{$NET.IF.IFALIAS.MATCHES} |<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p> |`.*` |
|{$NET.IF.IFALIAS.NOT_MATCHES} |<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p> |`CHANGE_THIS` |
|{$NET.IF.IFDESCR.MATCHES} |<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p> |`.*` |
|{$NET.IF.IFDESCR.NOT_MATCHES} |<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p> |`CHANGE_THIS` |
|{$NET.IF.IFNAME.MATCHES} |<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p> |`.*` |
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p> |`Miniport|Virtual|Teredo|Kernel|Loopback|Bluetooth|HTTPS|6to4|QoS|Layer` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Network interfaces discovery |<p>Discovery of installed network interfaces.</p> |DEPENDENT |net.if.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- {#IFDESCR} MATCHES_REGEX `{$NET.IF.IFDESCR.MATCHES}`</p><p>- {#IFDESCR} NOT_MATCHES_REGEX `{$NET.IF.IFDESCR.NOT_MATCHES}`</p><p>- {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits received |<p>Incoming traffic on the network interface.</p> |ZABBIX_PASSIVE |net.if.in["{#IFGUID}"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits sent |<p>Outgoing traffic on the network interface.</p> |ZABBIX_PASSIVE |net.if.out["{#IFGUID}"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded |<p>The number of incoming packets dropped on the network interface.</p> |ZABBIX_PASSIVE |net.if.in["{#IFGUID}",dropped]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded |<p>The number of outgoing packets dropped on the network interface.</p> |ZABBIX_PASSIVE |net.if.out["{#IFGUID}",dropped]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors |<p>The number of incoming packets with errors on the network interface.</p> |ZABBIX_PASSIVE |net.if.in["{#IFGUID}",errors]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors |<p>The number of outgoing packets with errors on the network interface.</p> |ZABBIX_PASSIVE |net.if.out["{#IFGUID}",errors]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Speed |<p>Estimated bandwidth of the network interface if any.</p> |DEPENDENT |net.if.speed["{#IFGUID}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.GUID == "{#IFGUID}")].Speed.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- JAVASCRIPT: `return (value=='9223372036854775807' ? 0 : value) `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Interface type |<p>The type of the network interface.</p> |DEPENDENT |net.if.type["{#IFGUID}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.GUID == "{#IFGUID}")].AdapterTypeId.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Operational status |<p>The operational status of the network interface.</p> |DEPENDENT |net.if.status["{#IFGUID}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.GUID == "{#IFGUID}")].NetConnectionStatus.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Zabbix raw items |Network interfaces WMI get |<p>Raw data of win32_networkadapter.</p> |ZABBIX_PASSIVE |wmi.getall[root\cimv2,"select Name,Description,NetConnectionID,Speed,AdapterTypeId,NetConnectionStatus,GUID from win32_networkadapter where PhysicalAdapter=True and NetConnectionStatus>0"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/Windows network by Zabbix agent/net.if.in["{#IFGUID}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Windows network by Zabbix agent/net.if.speed["{#IFGUID}"]) or avg(/Windows network by Zabbix agent/net.if.out["{#IFGUID}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Windows network by Zabbix agent/net.if.speed["{#IFGUID}"])) and last(/Windows network by Zabbix agent/net.if.speed["{#IFGUID}"])>0`<p>Recovery expression:</p>`avg(/Windows network by Zabbix agent/net.if.in["{#IFGUID}"],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Windows network by Zabbix agent/net.if.speed["{#IFGUID}"]) and avg(/Windows network by Zabbix agent/net.if.out["{#IFGUID}"],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Windows network by Zabbix agent/net.if.speed["{#IFGUID}"])` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`min(/Windows network by Zabbix agent/net.if.in["{#IFGUID}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Windows network by Zabbix agent/net.if.out["{#IFGUID}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} `<p>Recovery expression:</p>`max(/Windows network by Zabbix agent/net.if.in["{#IFGUID}",errors],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and max(/Windows network by Zabbix agent/net.if.out["{#IFGUID}",errors],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 ` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`change(/Windows network by Zabbix agent/net.if.speed["{#IFGUID}"])<0 and last(/Windows network by Zabbix agent/net.if.speed["{#IFGUID}"])>0 and last(/Windows network by Zabbix agent/net.if.status["{#IFGUID}"])=2 ` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:\"{#IFNAME}\"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important.</p><p>    No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status is different from Connected(2).</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Windows network by Zabbix agent/net.if.status["{#IFGUID}"])<>2 and (last(/Windows network by Zabbix agent/net.if.status["{#IFGUID}"],#1)<>last(/Windows network by Zabbix agent/net.if.status["{#IFGUID}"],#2))`<p>Recovery expression:</p>`last(/Windows network by Zabbix agent/net.if.status["{#IFGUID}"])=2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Windows services by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  
Special version of services template that is required for Windows OS.

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SERVICE.NAME.MATCHES} |<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p> |`^.*$` |
|{$SERVICE.NAME.NOT_MATCHES} |<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p> |`^(?:RemoteRegistry|MMCSS|gupdate|SysmonLog|clr_optimization_v.+|sppsvc|gpsvc|Pml Driver HPZ12|Net Driver HPZ12|MapsBroker|IntelAudioService|Intel\(R\) TPM Provisioning Service|dbupdate|DoSvc|CDPUserSvc_.+|WpnUserService_.+|OneSyncSvc_.+|WbioSrvc|BITS|tiledatamodelsvc|GISvc|ShellHWDetection|TrustedInstaller|TabletInputService|CDPSvc|wuauserv)$` |
|{$SERVICE.STARTUPNAME.MATCHES} |<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p> |`^(?:automatic|automatic delayed)$` |
|{$SERVICE.STARTUPNAME.NOT_MATCHES} |<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p> |`^(?:manual|disabled)$` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Windows services discovery |<p>Discovery of Windows services of different types as defined in template's macros.</p> |ZABBIX_PASSIVE |service.discovery<p>**Filter**:</p>AND <p>- {#SERVICE.NAME} MATCHES_REGEX `{$SERVICE.NAME.MATCHES}`</p><p>- {#SERVICE.NAME} NOT_MATCHES_REGEX `{$SERVICE.NAME.NOT_MATCHES}`</p><p>- {#SERVICE.STARTUPNAME} MATCHES_REGEX `{$SERVICE.STARTUPNAME.MATCHES}`</p><p>- {#SERVICE.STARTUPNAME} NOT_MATCHES_REGEX `{$SERVICE.STARTUPNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Services |State of service "{#SERVICE.NAME}" ({#SERVICE.DISPLAYNAME}) |<p>-</p> |ZABBIX_PASSIVE |service.info["{#SERVICE.NAME}",state] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|"{#SERVICE.NAME}" ({#SERVICE.DISPLAYNAME}) is not running |<p>The service has a state other than "Running" for the last three times.</p> |`min(/Windows services by Zabbix agent/service.info["{#SERVICE.NAME}",state],#3)<>0` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

