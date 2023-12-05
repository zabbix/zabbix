
# Windows by Zabbix agent

## Overview

New official Windows template. Requires agent of Zabbix 7.0 and newer.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Windows 7 and newer.
- Windows Server 2008 R2 and newer.

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on Windows OS according to Zabbix documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.TIMEOUT}|<p>Timeout after which agent is considered unavailable. Works only for agents reachable from Zabbix server/proxy (passive mode).</p>|`3m`|
|{$CPU.INTERRUPT.CRIT.MAX}|<p>The critical threshold of the % Interrupt Time counter.</p>|`50`|
|{$CPU.PRIV.CRIT.MAX}|<p>The threshold of the % Privileged Time counter.</p>|`30`|
|{$CPU.QUEUE.CRIT.MAX}|<p>The threshold of the Processor Queue Length counter.</p>|`3`|
|{$CPU.UTIL.CRIT}|<p>The critical threshold of the CPU utilization expressed in %.</p>|`90`|
|{$MEM.PAGE_TABLE_CRIT.MIN}|<p>The warning threshold of the Free System Page Table Entries counter.</p>|`5000`|
|{$MEM.PAGE_SEC.CRIT.MAX}|<p>The warning threshold of the Memory Pages/sec counter.</p>|`1000`|
|{$MEMORY.UTIL.MAX}|<p>The warning threshold of the Memory util item.</p>|`90`|
|{$SWAP.PFREE.MIN.WARN}|<p>The warning threshold of the minimum free swap.</p>|`20`|
|{$VFS.FS.FSNAME.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`^(?:/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$VFS.FS.FSDRIVETYPE.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`fixed`|
|{$VFS.FS.FSDRIVETYPE.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$VFS.FS.PUSED.MAX.CRIT}|<p>The critical threshold of the filesystem utilization in percent.</p>|`90`|
|{$VFS.FS.PUSED.MAX.WARN}|<p>The warning threshold of the filesystem utilization in percent.</p>|`80`|
|{$VFS.FS.FREE.MIN.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`5G`|
|{$VFS.FS.FREE.MIN.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`10G`|
|{$VFS.DEV.DEVNAME.MATCHES}|<p>This macro is used in physical disks discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$VFS.DEV.DEVNAME.NOT_MATCHES}|<p>This macro is used in physical disks discovery. Can be overridden on the host or linked template level.</p>|`_Total`|
|{$VFS.DEV.UTIL.MAX.WARN}|<p>The warning threshold of disk time utilization in percent.</p>|`95`|
|{$VFS.DEV.READ.AWAIT.WARN}|<p>Disk read average response time (in s) before the trigger would fire.</p>|`0.02`|
|{$VFS.DEV.WRITE.AWAIT.WARN}|<p>Disk write average response time (in s) before the trigger would fire.</p>|`0.02`|
|{$SYSTEM.FUZZYTIME.MAX}|<p>The threshold for difference of system time in seconds.</p>|`60`|
|{$IFCONTROL}||`1`|
|{$NET.IF.IFNAME.MATCHES}|<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFALIAS.MATCHES}|<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}|<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_THIS`|
|{$NET.IF.IFDESCR.MATCHES}|<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}|<p>This macro is used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_THIS`|
|{$IF.UTIL.MAX}||`90`|
|{$IF.ERRORS.WARN}||`2`|
|{$SERVICE.NAME.MATCHES}|<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$SERVICE.NAME.NOT_MATCHES}|<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$SERVICE.STARTUPNAME.MATCHES}|<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p>|`^(?:automatic\|automatic delayed)$`|
|{$SERVICE.STARTUPNAME.NOT_MATCHES}|<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p>|`^(?:manual\|disabled)$`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Windows: Version of Zabbix agent running||Zabbix agent|agent.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows: Host name of Zabbix agent running||Zabbix agent|agent.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows: Zabbix agent ping|<p>The agent always returns 1 for this item. It could be used in combination with nodata() for availability check.</p>|Zabbix agent|agent.ping|
|Windows: Zabbix agent availability|<p>Monitoring the availability status of the agent.</p>|Zabbix internal|zabbix[host,agent,available]|
|Windows: CPU utilization|<p>The CPU utilization expressed in %.</p>|Zabbix agent|system.cpu.util|
|Windows: CPU interrupt time|<p>The Processor Information\% Interrupt Time is the time the processor spends receiving and servicing</p><p>hardware interrupts during sample intervals. This value is an indirect indicator of the activity of</p><p>devices that generate interrupts, such as the system clock, the mouse, disk drivers, data communication</p><p>lines, network interface cards and other peripheral devices. This is an easy way to identify a potential</p><p>hardware failure. This should never be higher than 20%.</p>|Zabbix agent|perf_counter_en["\Processor Information(_total)\% Interrupt Time"]|
|Windows: Context switches per second|<p>Context Switches/sec is the combined rate at which all processors on the computer are switched from one thread to another.</p><p>Context switches occur when a running thread voluntarily relinquishes the processor, is preempted by a higher priority ready thread, or switches between user-mode and privileged (kernel) mode to use an Executive or subsystem service.</p><p>It is the sum of Thread\\Context Switches/sec for all threads running on all processors in the computer and is measured in numbers of switches.</p><p>There are context switch counters on the System and Thread objects. This counter displays the difference between the values observed in the last two samples, divided by the duration of the sample interval.</p>|Zabbix agent|perf_counter_en["\System\Context Switches/sec"]|
|Windows: CPU privileged time|<p>The Processor Information\% Privileged Time counter shows the percent of time that the processor is spent</p><p>executing in Kernel (or Privileged) mode. Privileged mode includes services interrupts inside Interrupt</p><p>Service Routines (ISRs), executing Deferred Procedure Calls (DPCs), Device Driver calls and other kernel-mode</p><p>functions of the Windows® Operating System.</p>|Zabbix agent|perf_counter_en["\Processor Information(_total)\% Privileged Time"]|
|Windows: CPU DPC time|<p>Processor DPC time is the time that a single processor spent receiving and servicing deferred procedure</p><p>calls (DPCs). DPCs are interrupts that run at a lower priority than standard interrupts. % DPC Time is a</p><p>component of % Privileged Time because DPCs are executed in privileged mode. If a high % DPC Time is</p><p>sustained, there may be a processor bottleneck or an application or hardware related issue that can</p><p>significantly diminish overall system performance.</p>|Zabbix agent|perf_counter_en["\Processor Information(_total)\% DPC Time"]|
|Windows: CPU user time|<p>The Processor Information\% User Time counter shows the percent of time that the processor(s) is spent executing</p><p>in User mode.</p>|Zabbix agent|perf_counter_en["\Processor Information(_total)\% User Time"]|
|Windows: Number of cores|<p>The number of logical processors available on the computer.</p>|Zabbix agent|wmi.get[root/cimv2,"Select NumberOfLogicalProcessors from Win32_ComputerSystem"]|
|Windows: CPU queue length|<p>The Processor Queue Length shows the number of threads that are observed as delayed in the processor Ready Queue</p><p>and are waiting to be executed.</p>|Zabbix agent|perf_counter_en["\System\Processor Queue Length"]|
|Windows: Used memory|<p>Used memory in bytes.</p>|Zabbix agent|vm.memory.size[used]|
|Windows: Total memory|<p>The total memory expressed in bytes.</p>|Zabbix agent|vm.memory.size[total]|
|Windows: Memory utilization|<p>Memory utilization in %.</p>|Calculated|vm.memory.util|
|Windows: Cache bytes|<p>Cache Bytes is the sum of the Memory\\System Cache Resident Bytes, Memory\\System Driver Resident Bytes,</p><p>Memory\\System Code Resident Bytes, and Memory\\Pool Paged Resident Bytes counters. This counter displays</p><p>the last observed value only; it is not an average.</p>|Zabbix agent|perf_counter_en["\Memory\Cache Bytes"]|
|Windows: Free swap space|<p>The free space of the swap volume/file expressed in bytes.</p>|Calculated|system.swap.free|
|Windows: Free swap space in %|<p>The free space of the swap volume/file expressed in %.</p>|Dependent item|system.swap.pfree<p>**Preprocessing**</p><ul><li><p>JavaScript: `return (100 - value)`</p></li></ul>|
|Windows: Used swap space in %|<p>The used space of swap volume/file in percent.</p>|Zabbix agent|perf_counter_en["\Paging file(_Total)\% Usage"]|
|Windows: Total swap space|<p>The total space of the swap volume/file expressed in bytes.</p>|Zabbix agent|system.swap.size[,total]|
|Windows: Free system page table entries|<p>This indicates the number of page table entries not currently in use by the system. If the number is less</p><p>than 5,000, there may well be a memory leak or you running out of memory.</p>|Zabbix agent|perf_counter_en["\Memory\Free System Page Table Entries"]|
|Windows: Memory page faults per second|<p>Page Faults/sec is the average number of pages faulted per second. It is measured in number of pages</p><p>faulted per second because only one page is faulted in each fault operation, hence this is also equal</p><p>to the number of page fault operations. This counter includes both hard faults (those that require</p><p>disk access) and soft faults (where the faulted page is found elsewhere in physical memory.) Most</p><p>processors can handle large numbers of soft faults without significant consequence. However, hard faults,</p><p>which require disk access, can cause significant delays.</p>|Zabbix agent|perf_counter_en["\Memory\Page Faults/sec"]|
|Windows: Memory pages per second|<p>This measures the rate at which pages are read from or written to disk to resolve hard page faults.</p><p>If the value is greater than 1,000, as a result of excessive paging, there may be a memory leak.</p>|Zabbix agent|perf_counter_en["\Memory\Pages/sec"]|
|Windows: Memory pool non-paged|<p>This measures the size, in bytes, of the non-paged pool. This is an area of system memory for objects</p><p>that cannot be written to disk but instead must remain in physical memory as long as they are allocated.</p><p>There is a possible memory leak if the value is greater than 175MB (or 100MB with the /3GB switch).</p><p>A typical Event ID 2019 is recorded in the system event log.</p>|Zabbix agent|perf_counter_en["\Memory\Pool Nonpaged Bytes"]|
|Windows: Get filesystems|<p>The `vfs.fs.get` key acquires raw information set about the file systems. Later to be extracted by preprocessing in dependent items.</p>|Zabbix agent|vfs.fs.get|
|Windows: Uptime|<p>The system uptime expressed in the following format: "N days, hh:mm:ss".</p>|Zabbix agent|system.uptime|
|Windows: System local time|<p>The local system time of the host.</p>|Zabbix agent|system.localtime|
|Windows: System name|<p>The host name of the system.</p>|Zabbix agent|system.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows: System description|<p>System description of the host.</p>|Zabbix agent|system.uname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows: Number of processes|<p>The number of processes.</p>|Zabbix agent|proc.num[]|
|Windows: Number of threads|<p>The number of threads used by all running processes.</p>|Zabbix agent|perf_counter_en["\System\Threads"]|
|Windows: Operating system architecture|<p>The architecture of the operating system.</p>|Zabbix agent|system.sw.arch<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows: Operating system||Zabbix agent|system.sw.os<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows: Network interfaces WMI get|<p>Raw data of win32_networkadapter.</p>|Zabbix agent|wmi.getall[root\cimv2,"select Name,Description,NetConnectionID,Speed,AdapterTypeId,NetConnectionStatus,GUID from win32_networkadapter where PhysicalAdapter=True and NetConnectionStatus>0"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Windows: Zabbix agent is not available|<p>For passive only agents, host availability is used with {$AGENT.TIMEOUT} as time threshold.</p>|`max(/Windows by Zabbix agent/zabbix[host,agent,available],{$AGENT.TIMEOUT})=0`|Average|**Manual close**: Yes|
|Windows: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Windows by Zabbix agent/system.cpu.util,5m)>{$CPU.UTIL.CRIT}`|Warning||
|Windows: CPU interrupt time is too high|<p>"The CPU Interrupt Time in the last 5 minutes exceeds {$CPU.INTERRUPT.CRIT.MAX}%."<br>The Processor Information\% Interrupt Time is the time the processor spends receiving and servicing<br>hardware interrupts during sample intervals. This value is an indirect indicator of the activity of<br>devices that generate interrupts, such as the system clock, the mouse, disk drivers, data communication<br>lines, network interface cards and other peripheral devices. This is an easy way to identify a potential<br>hardware failure. This should never be higher than 20%.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\Processor Information(_total)\% Interrupt Time"],5m)>{$CPU.INTERRUPT.CRIT.MAX}`|Warning|**Depends on**:<br><ul><li>Windows: High CPU utilization</li></ul>|
|Windows: CPU privileged time is too high|<p>The CPU privileged time in the last 5 minutes exceeds {$CPU.PRIV.CRIT.MAX}%.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\Processor Information(_total)\% Privileged Time"],5m)>{$CPU.PRIV.CRIT.MAX}`|Warning|**Depends on**:<br><ul><li>Windows: High CPU utilization</li><li>Windows: CPU interrupt time is too high</li></ul>|
|Windows: CPU queue length is too high|<p>The CPU Queue Length in the last 5 minutes exceeds {$CPU.QUEUE.CRIT.MAX}. According to actual observations, PQL should not exceed the number of cores * 2. To fine-tune the conditions, use the macro {$CPU.QUEUE.CRIT.MAX }.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\System\Processor Queue Length"],5m) - last(/Windows by Zabbix agent/wmi.get[root/cimv2,"Select NumberOfLogicalProcessors from Win32_ComputerSystem"]) * 2 > {$CPU.QUEUE.CRIT.MAX}`|Warning|**Depends on**:<br><ul><li>Windows: High CPU utilization</li></ul>|
|Windows: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Windows by Zabbix agent/vm.memory.util,5m)>{$MEMORY.UTIL.MAX}`|Average||
|Windows: High swap space usage|<p>This trigger is ignored, if there is no swap configured</p>|`max(/Windows by Zabbix agent/system.swap.pfree,5m)<{$SWAP.PFREE.MIN.WARN} and last(/Windows by Zabbix agent/system.swap.size[,total])>0`|Warning|**Depends on**:<br><ul><li>Windows: High memory utilization</li></ul>|
|Windows: Number of free system page table entries is too low|<p>The Memory Free System Page Table Entries is less than {$MEM.PAGE_TABLE_CRIT.MIN} for 5 minutes. If the number is less than 5,000, there may well be a memory leak.</p>|`max(/Windows by Zabbix agent/perf_counter_en["\Memory\Free System Page Table Entries"],5m)<{$MEM.PAGE_TABLE_CRIT.MIN}`|Warning|**Depends on**:<br><ul><li>Windows: High memory utilization</li></ul>|
|Windows: The Memory Pages/sec is too high|<p>The Memory Pages/sec in the last 5 minutes exceeds {$MEM.PAGE_SEC.CRIT.MAX}. If the value is greater than 1,000, as a result of excessive paging, there may be a memory leak.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\Memory\Pages/sec"],5m)>{$MEM.PAGE_SEC.CRIT.MAX}`|Warning|**Depends on**:<br><ul><li>Windows: High memory utilization</li></ul>|
|Windows: Host has been restarted|<p>The device uptime is less than 10 minutes.</p>|`last(/Windows by Zabbix agent/system.uptime)<10m`|Warning|**Manual close**: Yes|
|Windows: System time is out of sync|<p>The host's system time is different from Zabbix server time.</p>|`fuzzytime(/Windows by Zabbix agent/system.localtime,{$SYSTEM.FUZZYTIME.MAX})=0`|Warning|**Manual close**: Yes|
|Windows: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`change(/Windows by Zabbix agent/system.hostname) and length(last(/Windows by Zabbix agent/system.hostname))>0`|Info|**Manual close**: Yes|
|Windows: Operating system description has changed|<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`change(/Windows by Zabbix agent/system.sw.os) and length(last(/Windows by Zabbix agent/system.sw.os))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Windows: System name has changed</li></ul>|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>Discovery of file systems of different types.</p>|Dependent item|vfs.fs.dependent.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FSLABEL}({#FSNAME}): Get filesystem data||Dependent item|vfs.fs.dependent[{#FSNAME},data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.fsname=='{#FSNAME}')].first()`</p></li></ul>|
|{#FSLABEL}({#FSNAME}): Used space|<p>Used storage expressed in bytes.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.used`</p></li></ul>|
|{#FSLABEL}({#FSNAME}): Total space|<p>The total space expressed in bytes.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.total`</p></li></ul>|
|{#FSLABEL}({#FSNAME}): Space utilization|<p>Space utilization expressed in % for `{#FSNAME}`.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},pused]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.pused`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSLABEL}({#FSNAME}): Disk space is critically low|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`.<br>2. The second condition should be one of the following:<br>- the disk free space is less than `{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}`;<br>- the disk will be full in less than 24 hours.</p>|`last(/Windows by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},pused])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/Windows by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},total])-last(/Windows by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},used]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/Windows by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},pused],1h,100)<1d)`|Average|**Manual close**: Yes|
|{#FSLABEL}({#FSNAME}): Disk space is low|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}`.<br>2. The second condition should be one of the following:<br>- the disk free space is less than `{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}`;<br>- the disk will be full in less than 24 hours.</p>|`last(/Windows by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},pused])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/Windows by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},total])-last(/Windows by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},used]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/Windows by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},pused],1h,100)<1d)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#FSLABEL}({#FSNAME}): Disk space is critically low</li></ul>|

### LLD rule Physical disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical disks discovery|<p>Discovery of installed physical disks.</p>|Zabbix agent|perf_instance_en.discovery[PhysicalDisk]<p>**Preprocessing**</p><ul><li><p>Replace: `{#INSTANCE} -> {#DEVNAME}`</p></li></ul>|

### Item prototypes for Physical disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DEVNAME}: Disk read rate|<p>Rate of read operations on the disk.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Disk Reads/sec",60]|
|{#DEVNAME}: Disk write rate|<p>Rate of write operations on the disk.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Disk Writes/sec",60]|
|{#DEVNAME}: Disk average queue size (avgqu-sz)|<p>The current average disk queue; the number of requests outstanding on the disk while the performance data is being collected.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Current Disk Queue Length",60]|
|{#DEVNAME}: Disk utilization by idle time|<p>This item is the percentage of elapsed time that the selected disk drive was busy servicing read or writes requests based on idle time.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\% Idle Time",60]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return (100 - value)`</p></li></ul>|
|{#DEVNAME}: Disk read request avg waiting time|<p>The average time for read requests issued to the device to be served. This includes the time spent by the requests in queue and the time spent servicing them.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Read",60]|
|{#DEVNAME}: Disk write request avg waiting time|<p>The average time for write requests issued to the device to be served. This includes the time spent by the requests in queue and the time spent servicing them.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Write",60]|
|{#DEVNAME}: Average disk read queue length|<p>Average disk read queue, the number of requests outstanding on the disk at the time the performance data is collected.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk Read Queue Length",60]|
|{#DEVNAME}: Average disk write queue length|<p>Average disk write queue, the number of requests outstanding on the disk at the time the performance data is collected.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk Write Queue Length",60]|

### Trigger prototypes for Physical disks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#DEVNAME}: Disk is overloaded|<p>The disk appears to be under heavy load.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\PhysicalDisk({#DEVNAME})\% Idle Time",60],15m)>{$VFS.DEV.UTIL.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#DEVNAME}: Disk read request responses are too high</li><li>{#DEVNAME}: Disk write request responses are too high</li></ul>|
|{#DEVNAME}: Disk read request responses are too high|<p>This trigger might indicate the disk {#DEVNAME} saturation.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Read",60],15m) > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"}`|Warning|**Manual close**: Yes|
|{#DEVNAME}: Disk write request responses are too high|<p>This trigger might indicate the disk {#DEVNAME} saturation.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Write",60],15m) > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}`|Warning|**Manual close**: Yes|

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovery of installed network interfaces.</p>|Dependent item|net.if.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>Incoming traffic on the network interface.</p>|Zabbix agent|net.if.in["{#IFGUID}"]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>Outgoing traffic on the network interface.</p>|Zabbix agent|net.if.out["{#IFGUID}"]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>The number of incoming packets dropped on the network interface.</p>|Zabbix agent|net.if.in["{#IFGUID}",dropped]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>The number of outgoing packets dropped on the network interface.</p>|Zabbix agent|net.if.out["{#IFGUID}",dropped]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>The number of incoming packets with errors on the network interface.</p>|Zabbix agent|net.if.in["{#IFGUID}",errors]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>The number of outgoing packets with errors on the network interface.</p>|Zabbix agent|net.if.out["{#IFGUID}",errors]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>Estimated bandwidth of the network interface if any.</p>|Dependent item|net.if.speed["{#IFGUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.GUID == "{#IFGUID}")].Speed.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>JavaScript: `return (value=='9223372036854775807' ? 0 : value)`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>The type of the network interface.</p>|Dependent item|net.if.type["{#IFGUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.GUID == "{#IFGUID}")].AdapterTypeId.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>The operational status of the network interface.</p>|Dependent item|net.if.status["{#IFGUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.GUID == "{#IFGUID}")].NetConnectionStatus.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Windows by Zabbix agent/net.if.in["{#IFGUID}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Windows by Zabbix agent/net.if.speed["{#IFGUID}"]) or avg(/Windows by Zabbix agent/net.if.out["{#IFGUID}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Windows by Zabbix agent/net.if.speed["{#IFGUID}"])) and last(/Windows by Zabbix agent/net.if.speed["{#IFGUID}"])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Windows by Zabbix agent/net.if.in["{#IFGUID}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Windows by Zabbix agent/net.if.out["{#IFGUID}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Windows by Zabbix agent/net.if.speed["{#IFGUID}"])<0 and last(/Windows by Zabbix agent/net.if.speed["{#IFGUID}"])>0 and last(/Windows by Zabbix agent/net.if.status["{#IFGUID}"])=2`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important.<br>No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Windows by Zabbix agent/net.if.status["{#IFGUID}"])<>2 and (last(/Windows by Zabbix agent/net.if.status["{#IFGUID}"],#1)<>last(/Windows by Zabbix agent/net.if.status["{#IFGUID}"],#2))`|Average|**Manual close**: Yes|

### LLD rule Windows services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Windows services discovery|<p>Discovery of Windows services of different types as defined in template's macros.</p>|Zabbix agent|service.discovery|

### Item prototypes for Windows services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|State of service "{#SERVICE.NAME}" ({#SERVICE.DISPLAYNAME})||Zabbix agent|service.info["{#SERVICE.NAME}",state]|

### Trigger prototypes for Windows services discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|"{#SERVICE.NAME}" ({#SERVICE.DISPLAYNAME}) is not running|<p>The service has a state other than "Running" for the last three times.</p>|`min(/Windows by Zabbix agent/service.info["{#SERVICE.NAME}",state],#3)<>0`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

