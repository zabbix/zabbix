
# Generic Java JMX

## Overview

For Zabbix version: 6.0 and higher  
Official JMX Template from Zabbix distribution. Could be useful for many Java Applications (JMX).



## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$JMX.CPU.LOAD.MAX} |<p>A threshold in percent for CPU utilization trigger.</p> |`85` |
|{$JMX.CPU.LOAD.TIME} |<p>The time during which the CPU utilization may exceed the threshold.</p> |`5m` |
|{$JMX.FILE.DESCRIPTORS.MAX} |<p>A threshold in percent for file descriptors count trigger.</p> |`85` |
|{$JMX.FILE.DESCRIPTORS.TIME} |<p>The time during which the file descriptors count may exceed the threshold.</p> |`3m` |
|{$JMX.HEAP.MEM.USAGE.MAX} |<p>A threshold in percent for Heap memory utilization trigger.</p> |`85` |
|{$JMX.HEAP.MEM.USAGE.TIME} |<p>The time during which the Heap memory utilization may exceed the threshold.</p> |`10m` |
|{$JMX.MP.USAGE.MAX} |<p>A threshold in percent for memory pools utilization trigger. Use a context to change the threshold for a specific pool.</p> |`85` |
|{$JMX.MP.USAGE.TIME} |<p>The time during which the memory pools utilization may exceed the threshold.</p> |`10m` |
|{$JMX.NONHEAP.MEM.USAGE.MAX} |<p>A threshold in percent for Non-heap memory utilization trigger.</p> |`85` |
|{$JMX.NONHEAP.MEM.USAGE.TIME} |<p>The time during which the Non-heap memory utilization may exceed the threshold.</p> |`10m` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|JMX |ClassLoading: Loaded class count |<p>Displays number of classes that are currently loaded in the Java virtual machine.</p> |JMX |jmx["java.lang:type=ClassLoading","LoadedClassCount"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |ClassLoading: Total loaded class count |<p>Displays the total number of classes that have been loaded since the Java virtual machine has started execution.</p> |JMX |jmx["java.lang:type=ClassLoading","TotalLoadedClassCount"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |ClassLoading: Unloaded class count |<p>Displays the total number of classes that have been loaded since the Java virtual machine has started execution.</p> |JMX |jmx["java.lang:type=ClassLoading","UnloadedClassCount"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |Compilation: Name of the current JIT compiler |<p>Displays the total number of classes unloaded since the Java virtual machine has started execution.</p> |JMX |jmx["java.lang:type=Compilation","Name"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|JMX |Compilation: Accumulated time spent |<p>Displays the approximate accumulated elapsed time spent in compilation, in seconds.</p> |JMX |jmx["java.lang:type=Compilation","TotalCompilationTime"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |GarbageCollector: ConcurrentMarkSweep number of collections per second |<p>Displays the total number of collections that have occurred per second.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=ConcurrentMarkSweep","CollectionCount"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|JMX |GarbageCollector: ConcurrentMarkSweep accumulated time spent in collection |<p>Displays the approximate accumulated collection elapsed time, in seconds.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=ConcurrentMarkSweep","CollectionTime"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |GarbageCollector: Copy number of collections per second |<p>Displays the total number of collections that have occurred per second.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=Copy","CollectionCount"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|JMX |GarbageCollector: Copy accumulated time spent in collection |<p>Displays the approximate accumulated collection elapsed time, in seconds.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=Copy","CollectionTime"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |GarbageCollector: MarkSweepCompact number of collections per second |<p>Displays the total number of collections that have occurred per second.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=MarkSweepCompact","CollectionCount"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|JMX |GarbageCollector: MarkSweepCompact accumulated time spent in collection |<p>Displays the approximate accumulated collection elapsed time, in seconds.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=MarkSweepCompact","CollectionTime"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |GarbageCollector: ParNew number of collections per second |<p>Displays the total number of collections that have occurred per second.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=ParNew","CollectionCount"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|JMX |GarbageCollector: ParNew accumulated time spent in collection |<p>Displays the approximate accumulated collection elapsed time, in seconds.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=ParNew","CollectionTime"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |GarbageCollector: PS MarkSweep number of collections per second |<p>Displays the total number of collections that have occurred per second.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=PS MarkSweep","CollectionCount"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|JMX |GarbageCollector: PS MarkSweep accumulated time spent in collection |<p>Displays the approximate accumulated collection elapsed time, in seconds.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=PS MarkSweep","CollectionTime"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |GarbageCollector: PS Scavenge number of collections per second |<p>Displays the total number of collections that have occurred per second.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=PS Scavenge","CollectionCount"]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|JMX |GarbageCollector: PS Scavenge accumulated time spent in collection |<p>Displays the approximate accumulated collection elapsed time, in seconds.</p> |JMX |jmx["java.lang:type=GarbageCollector,name=PS Scavenge","CollectionTime"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |Memory: Heap memory committed |<p>Current heap memory allocated. This amount of memory is guaranteed for the Java virtual machine to use.</p> |JMX |jmx["java.lang:type=Memory","HeapMemoryUsage.committed"] |
|JMX |Memory: Heap memory maximum size |<p>Maximum amount of heap that can be used for memory management. This amount of memory is not guaranteed to be available if it is greater than the amount of committed memory. The Java virtual machine may fail to allocate memory even if the amount of used memory does not exceed this maximum size.</p> |JMX |jmx["java.lang:type=Memory","HeapMemoryUsage.max"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |Memory: Heap memory used |<p>Current memory usage outside the heap.</p> |JMX |jmx["java.lang:type=Memory","HeapMemoryUsage.used"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |Memory: Non-Heap memory committed |<p>Current memory allocated outside the heap. This amount of memory is guaranteed for the Java virtual machine to use.</p> |JMX |jmx["java.lang:type=Memory","NonHeapMemoryUsage.committed"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |Memory: Non-Heap memory maximum size |<p>Maximum amount of non-heap memory that can be used for memory management. This amount of memory is not guaranteed to be available if it is greater than the amount of committed memory. The Java virtual machine may fail to allocate memory even if the amount of used memory does not exceed this maximum size.</p> |JMX |jmx["java.lang:type=Memory","NonHeapMemoryUsage.max"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |Memory: Non-Heap memory used |<p>Current memory usage outside the heap</p> |JMX |jmx["java.lang:type=Memory","NonHeapMemoryUsage.used"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |Memory: Object pending finalization count |<p>The approximate number of objects for which finalization is pending.</p> |JMX |jmx["java.lang:type=Memory","ObjectPendingFinalizationCount"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |MemoryPool: CMS Old Gen committed |<p>Current memory allocated</p> |JMX |jmx["java.lang:type=MemoryPool,name=CMS Old Gen","Usage.committed"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |MemoryPool: CMS Old Gen maximum size |<p>Maximum amount of memory that can be used for memory management. This amount of memory is not guaranteed to be available if it is greater than the amount of committed memory. The Java virtual machine may fail to allocate memory even if the amount of used memory does not exceed this maximum size.</p> |JMX |jmx["java.lang:type=MemoryPool,name=CMS Old Gen","Usage.max"] |
|JMX |MemoryPool: CMS Old Gen used |<p>Current memory usage</p> |JMX |jmx["java.lang:type=MemoryPool,name=CMS Old Gen","Usage.used"] |
|JMX |MemoryPool: CMS Perm Gen committed |<p>Current memory allocated</p> |JMX |jmx["java.lang:type=MemoryPool,name=CMS Perm Gen","Usage.committed"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |MemoryPool: CMS Perm Gen maximum size |<p>Maximum amount of memory that can be used for memory management. This amount of memory is not guaranteed to be available if it is greater than the amount of committed memory. The Java virtual machine may fail to allocate memory even if the amount of used memory does not exceed this maximum size.</p> |JMX |jmx["java.lang:type=MemoryPool,name=CMS Perm Gen","Usage.max"] |
|JMX |MemoryPool: CMS Perm Gen used |<p>Current memory usage</p> |JMX |jmx["java.lang:type=MemoryPool,name=CMS Perm Gen","Usage.used"] |
|JMX |MemoryPool: Code Cache committed |<p>Current memory allocated</p> |JMX |jmx["java.lang:type=MemoryPool,name=Code Cache","Usage.committed"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |MemoryPool: CodeCache maximum size |<p>Maximum amount of memory that can be used for memory management. This amount of memory is not guaranteed to be available if it is greater than the amount of committed memory. The Java virtual machine may fail to allocate memory even if the amount of used memory does not exceed this maximum size.</p> |JMX |jmx["java.lang:type=MemoryPool,name=Code Cache","Usage.max"] |
|JMX |MemoryPool: Code Cache used |<p>Current memory usage</p> |JMX |jmx["java.lang:type=MemoryPool,name=Code Cache","Usage.used"] |
|JMX |MemoryPool: Perm Gen committed |<p>Current memory allocated</p> |JMX |jmx["java.lang:type=MemoryPool,name=Perm Gen","Usage.committed"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |MemoryPool: Perm Gen maximum size |<p>Maximum amount of memory that can be used for memory management. This amount of memory is not guaranteed to be available if it is greater than the amount of committed memory. The Java virtual machine may fail to allocate memory even if the amount of used memory does not exceed this maximum size.</p> |JMX |jmx["java.lang:type=MemoryPool,name=Perm Gen","Usage.max"] |
|JMX |MemoryPool: Perm Gen used |<p>Current memory usage</p> |JMX |jmx["java.lang:type=MemoryPool,name=Perm Gen","Usage.used"] |
|JMX |MemoryPool: PS Old Gen |<p>Current memory allocated</p> |JMX |jmx["java.lang:type=MemoryPool,name=PS Old Gen","Usage.committed"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |MemoryPool: PS Old Gen maximum size |<p>Maximum amount of memory that can be used for memory management. This amount of memory is not guaranteed to be available if it is greater than the amount of committed memory. The Java virtual machine may fail to allocate memory even if the amount of used memory does not exceed this maximum size.</p> |JMX |jmx["java.lang:type=MemoryPool,name=PS Old Gen","Usage.max"] |
|JMX |MemoryPool: PS Old Gen used |<p>Current memory usage</p> |JMX |jmx["java.lang:type=MemoryPool,name=PS Old Gen","Usage.used"] |
|JMX |MemoryPool: PS Perm Gen committed |<p>Current memory allocated</p> |JMX |jmx["java.lang:type=MemoryPool,name=PS Perm Gen","Usage.committed"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |MemoryPool: PS Perm Gen maximum size |<p>Maximum amount of memory that can be used for memory management. This amount of memory is not guaranteed to be available if it is greater than the amount of committed memory. The Java virtual machine may fail to allocate memory even if the amount of used memory does not exceed this maximum size.</p> |JMX |jmx["java.lang:type=MemoryPool,name=PS Perm Gen","Usage.max"] |
|JMX |MemoryPool: PS Perm Gen used |<p>Current memory usage</p> |JMX |jmx["java.lang:type=MemoryPool,name=PS Perm Gen","Usage.used"] |
|JMX |MemoryPool: Tenured Gen committed |<p>Current memory allocated</p> |JMX |jmx["java.lang:type=MemoryPool,name=Tenured Gen","Usage.committed"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |MemoryPool: Tenured Gen maximum size |<p>Maximum amount of memory that can be used for memory management. This amount of memory is not guaranteed to be available if it is greater than the amount of committed memory. The Java virtual machine may fail to allocate memory even if the amount of used memory does not exceed this maximum size.</p> |JMX |jmx["java.lang:type=MemoryPool,name=Tenured Gen","Usage.max"] |
|JMX |MemoryPool: Tenured Gen used |<p>Current memory usage</p> |JMX |jmx["java.lang:type=MemoryPool,name=Tenured Gen","Usage.used"] |
|JMX |OperatingSystem: File descriptors maximum count |<p>This is the number of file descriptors we can have opened in the same process, as determined by the operating system. You can never have more file descriptors than this number.</p> |JMX |jmx["java.lang:type=OperatingSystem","MaxFileDescriptorCount"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |OperatingSystem: File descriptors opened |<p>This is the number of opened file descriptors at the moment, if this reaches the MaxFileDescriptorCount, the application will throw an IOException: Too many open files. This could mean you’re are opening file descriptors and never closing them.</p> |JMX |jmx["java.lang:type=OperatingSystem","OpenFileDescriptorCount"] |
|JMX |OperatingSystem: Process CPU Load |<p>ProcessCpuLoad represents the CPU load in this process.</p> |JMX |jmx["java.lang:type=OperatingSystem","ProcessCpuLoad"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `100`</p> |
|JMX |Runtime: JVM uptime |<p>-</p> |JMX |jmx["java.lang:type=Runtime","Uptime"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|JMX |Runtime: JVM name |<p>-</p> |JMX |jmx["java.lang:type=Runtime","VmName"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|JMX |Runtime: JVM version |<p>-</p> |JMX |jmx["java.lang:type=Runtime","VmVersion"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|JMX |Threading: Daemon thread count |<p>Number of daemon threads running.</p> |JMX |jmx["java.lang:type=Threading","DaemonThreadCount"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|JMX |Threading: Peak thread count |<p>Maximum number of threads being executed at the same time since the JVM was started or the peak was reset.</p> |JMX |jmx["java.lang:type=Threading","PeakThreadCount"] |
|JMX |Threading: Thread count |<p>The number of threads running at the current moment.</p> |JMX |jmx["java.lang:type=Threading","ThreadCount"] |
|JMX |Threading: Total started thread count |<p>The number of threads started since the JVM was launched.</p> |JMX |jmx["java.lang:type=Threading","TotalStartedThreadCount"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Compilation: {HOST.NAME} uses suboptimal JIT compiler |<p>-</p> |`find(/Generic Java JMX/jmx["java.lang:type=Compilation","Name"],,"like","Client")=1` |INFO |<p>Manual close: YES</p> |
|GarbageCollector: Concurrent Mark Sweep in fire fighting mode |<p>-</p> |`last(/Generic Java JMX/jmx["java.lang:type=GarbageCollector,name=ConcurrentMarkSweep","CollectionCount"])>last(/Generic Java JMX/jmx["java.lang:type=GarbageCollector,name=ParNew","CollectionCount"])` |AVERAGE | |
|GarbageCollector: Mark Sweep Compact in fire fighting mode |<p>-</p> |`last(/Generic Java JMX/jmx["java.lang:type=GarbageCollector,name=MarkSweepCompact","CollectionCount"])>last(/Generic Java JMX/jmx["java.lang:type=GarbageCollector,name=Copy","CollectionCount"])` |AVERAGE | |
|GarbageCollector: PS Mark Sweep in fire fighting mode |<p>-</p> |`last(/Generic Java JMX/jmx["java.lang:type=GarbageCollector,name=PS MarkSweep","CollectionCount"])>last(/Generic Java JMX/jmx["java.lang:type=GarbageCollector,name=PS Scavenge","CollectionCount"])` |AVERAGE | |
|Memory: Heap memory usage more than {$JMX.HEAP.USAGE.MAX}% for {$JMX.HEAP.MEM.USAGE.TIME} |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=Memory","HeapMemoryUsage.used"],{$JMX.HEAP.MEM.USAGE.TIME})>(last(/Generic Java JMX/jmx["java.lang:type=Memory","HeapMemoryUsage.max"])*{$JMX.HEAP.MEM.USAGE.MAX}/100)` |WARNING | |
|Memory: Non-Heap memory usage more than {$JMX.NONHEAP.MEM.USAGE.MAX}% for {$JMX.NONHEAP.MEM.USAGE.TIME} |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=Memory","NonHeapMemoryUsage.used"],{$JMX.NONHEAP.MEM.USAGE.TIME})>(last(/Generic Java JMX/jmx["java.lang:type=Memory","NonHeapMemoryUsage.max"])*{$JMX.NONHEAP.MEM.USAGE.MAX}/100)` |WARNING | |
|MemoryPool: CMS Old Gen memory usage more than {$JMX.MP.USAGE.MAX:"CMS Old Gen"}% for {$JMX.MP.USAGE.TIME:"CMS Old Gen"} |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=CMS Old Gen","Usage.used"],{$JMX.MP.USAGE.TIME:"CMS Old Gen"})>(last(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=CMS Old Gen","Usage.max"])*{$JMX.MP.USAGE.MAX:"CMS Old Gen"}/100)` |WARNING | |
|MemoryPool: CMS Perm Gen memory usage more than {$JMX.MP.USAGE.MAX:"CMS Perm Gen"}% for {$JMX.MP.USAGE.TIME:"CMS Perm Gen"} |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=CMS Perm Gen","Usage.used"],{$JMX.MP.USAGE.TIME:"CMS Perm Gen"})>(last(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=CMS Perm Gen","Usage.max"])*{$JMX.MP.USAGE.MAX:"CMS Perm Gen"}/100)` |WARNING | |
|MemoryPool: Code Cache memory usage more than {$JMX.MP.USAGE.MAX:"Code Cache"}% for {$JMX.MP.USAGE.TIME:"Code Cache"} |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=Code Cache","Usage.used"],{$JMX.MP.USAGE.TIME:"Code Cache"})>(last(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=Code Cache","Usage.max"])*{$JMX.MP.USAGE.MAX:"Code Cache"}/100)` |WARNING | |
|MemoryPool: Perm Gen memory usage more than {$JMX.MP.USAGE.MAX:"Perm Gen"}% for {$JMX.MP.USAGE.TIME:"Perm Gen"} |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=Perm Gen","Usage.used"],{$JMX.MP.USAGE.TIME:"Perm Gen"})>(last(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=Perm Gen","Usage.max"])*{$JMX.MP.USAGE.MAX:"Perm Gen"}/100)` |WARNING | |
|MemoryPool: PS Old Gen memory usage more than {$JMX.MP.USAGE.MAX:"PS Old Gen"}% for {$JMX.MP.USAGE.TIME:"PS Old Gen"} |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=PS Old Gen","Usage.used"],{$JMX.MP.USAGE.TIME:"PS Old Gen"})>(last(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=PS Old Gen","Usage.max"])*{$JMX.MP.USAGE.MAX:"PS Old Gen"}/100)` |WARNING | |
|MemoryPool: PS Perm Gen memory usage more than {$JMX.MP.USAGE.MAX:"PS Perm Gen"}% for {$JMX.MP.USAGE.TIME:"PS Perm Gen"} |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=PS Perm Gen","Usage.used"],{$JMX.MP.USAGE.TIME:"PS Perm Gen"})>(last(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=PS Perm Gen","Usage.max"])*{$JMX.MP.USAGE.MAX:"PS Perm Gen"}/100)` |WARNING | |
|MemoryPool: Tenured Gen memory usage more than {$JMX.MP.USAGE.MAX:"Tenured Gen"}% for {$JMX.MP.USAGE.TIME:"Tenured Gen"} |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=Tenured Gen","Usage.used"],{$JMX.MP.USAGE.TIME:"Tenured Gen"})>(last(/Generic Java JMX/jmx["java.lang:type=MemoryPool,name=Tenured Gen","Usage.max"])*{$JMX.MP.USAGE.MAX:"Tenured Gen"}/100)` |WARNING | |
|OperatingSystem: Opened file descriptor count more than {$JMX.FILE.DESCRIPTORS.MAX}% of maximum |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=OperatingSystem","OpenFileDescriptorCount"],{$JMX.FILE.DESCRIPTORS.TIME})>(last(/Generic Java JMX/jmx["java.lang:type=OperatingSystem","MaxFileDescriptorCount"])*{$JMX.FILE.DESCRIPTORS.MAX}/100)` |WARNING | |
|OperatingSystem: Process CPU Load more than {$JMX.CPU.LOAD.MAX}% for {$JMX.CPU.LOAD.TIME} |<p>-</p> |`min(/Generic Java JMX/jmx["java.lang:type=OperatingSystem","ProcessCpuLoad"],{$JMX.CPU.LOAD.TIME})>{$JMX.CPU.LOAD.MAX}` |AVERAGE | |
|Runtime: JVM is not reachable |<p>-</p> |`nodata(/Generic Java JMX/jmx["java.lang:type=Runtime","Uptime"],5m)=1` |AVERAGE |<p>Manual close: YES</p> |
|Runtime: {HOST.NAME} runs suboptimal VM type |<p>-</p> |`find(/Generic Java JMX/jmx["java.lang:type=Runtime","VmName"],,"like","Server")<>1` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

