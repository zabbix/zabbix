
# HOST-RESOURCES-MIB storage SNMP

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
|{$VFS.FS.FSNAME.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`.+` |
|{$VFS.FS.FSNAME.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`^(/dev|/sys|/run|/proc|.+/shm$)` |
|{$VFS.FS.FSTYPE.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`.*(\.4|\.9|hrStorageFixedDisk|hrStorageFlashMemory)$` |
|{$VFS.FS.FSTYPE.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`CHANGE_IF_NEEDED` |
|{$VFS.FS.PUSED.MAX.CRIT} |<p>-</p> |`90` |
|{$VFS.FS.PUSED.MAX.WARN} |<p>-</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Storage discovery |<p>HOST-RESOURCES-MIB::hrStorage discovery with storage filter.</p> |SNMP |vfs.fs.discovery[snmp]<p>**Filter**:</p>AND <p>- {#FSTYPE} MATCHES_REGEX `{$VFS.FS.FSTYPE.MATCHES}`</p><p>- {#FSTYPE} NOT_MATCHES_REGEX `{$VFS.FS.FSTYPE.NOT_MATCHES}`</p><p>- {#FSNAME} MATCHES_REGEX `{$VFS.FS.FSNAME.MATCHES}`</p><p>- {#FSNAME} NOT_MATCHES_REGEX `{$VFS.FS.FSNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Storage |{#FSNAME}: Used space |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p> |SNMP |vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ALLOC_UNITS}`</p> |
|Storage |{#FSNAME}: Total space |<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main storage allocated to a buffer pool might be modified or the amount of disk space allocated to virtual storage might be modified.</p> |SNMP |vfs.fs.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ALLOC_UNITS}`</p> |
|Storage |{#FSNAME}: Space utilization |<p>Space utilization in % for {#FSNAME}</p> |CALCULATED |vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}]<p>**Expression**:</p>`(last(//vfs.fs.used[hrStorageUsed.{#SNMPINDEX}])/last(//vfs.fs.total[hrStorageSize.{#SNMPINDEX}]))*100` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#FSNAME}: Disk space is critically low |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`last(/HOST-RESOURCES-MIB storage SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/HOST-RESOURCES-MIB storage SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/HOST-RESOURCES-MIB storage SNMP/vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/HOST-RESOURCES-MIB storage SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}],1h,100)<1d) ` |AVERAGE |<p>Manual close: YES</p> |
|{#FSNAME}: Disk space is low |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`last(/HOST-RESOURCES-MIB storage SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/HOST-RESOURCES-MIB storage SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/HOST-RESOURCES-MIB storage SNMP/vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/HOST-RESOURCES-MIB storage SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}],1h,100)<1d) ` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#FSNAME}: Disk space is critically low</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# HOST-RESOURCES-MIB memory SNMP

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMORY.NAME.MATCHES} |<p>This macro is used in memory discovery. Can be overridden on the host or linked template level.</p> |`.*` |
|{$MEMORY.NAME.NOT_MATCHES} |<p>This macro is used in memory discovery. Can be overridden on the host or linked template level if you need to filter out results.</p> |`CHANGE_IF_NEEDED` |
|{$MEMORY.TYPE.MATCHES} |<p>This macro is used in memory discovery. Can be overridden on the host or linked template level.</p> |`.*(\.2|hrStorageRam)$` |
|{$MEMORY.TYPE.NOT_MATCHES} |<p>This macro is used in memory discovery. Can be overridden on the host or linked template level if you need to filter out results.</p> |`CHANGE_IF_NEEDED` |
|{$MEMORY.UTIL.MAX} |<p>The warning threshold of the "Physical memory: Memory utilization" item.</p> |`90` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Memory discovery |<p>HOST-RESOURCES-MIB::hrStorage discovery with memory filter</p> |SNMP |vm.memory.discovery<p>**Filter**:</p>AND <p>- {#MEMTYPE} MATCHES_REGEX `{$MEMORY.TYPE.MATCHES}`</p><p>- {#MEMTYPE} NOT_MATCHES_REGEX `{$MEMORY.TYPE.NOT_MATCHES}`</p><p>- {#MEMNAME} MATCHES_REGEX `{$MEMORY.NAME.MATCHES}`</p><p>- {#MEMNAME} NOT_MATCHES_REGEX `{$MEMORY.NAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Memory |{#MEMNAME}: Used memory |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p> |SNMP |vm.memory.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ALLOC_UNITS}`</p> |
|Memory |{#MEMNAME}: Total memory |<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main memory allocated to a buffer pool might be modified or the amount of disk space allocated to virtual memory might be modified.</p> |SNMP |vm.memory.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ALLOC_UNITS}`</p> |
|Memory |{#MEMNAME}: Memory utilization |<p>Memory utilization in %.</p> |CALCULATED |vm.memory.util[memoryUsedPercentage.{#SNMPINDEX}]<p>**Expression**:</p>`last(//vm.memory.used[hrStorageUsed.{#SNMPINDEX}])/last(//vm.memory.total[hrStorageSize.{#SNMPINDEX}])*100` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#MEMNAME}: High memory utilization |<p>The system is running out of free memory.</p> |`min(/HOST-RESOURCES-MIB memory SNMP/vm.memory.util[memoryUsedPercentage.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# HOST-RESOURCES-MIB CPU SNMP

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: HOST-RESOURCES-MIB</p><p>The average, over the last minute, of the percentage of time that processors was not idle.</p><p>Implementations may approximate this one minute smoothing period if necessary.</p> |SNMP |system.cpu.util<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#CPU.UTIL}'].avg()`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/HOST-RESOURCES-MIB CPU SNMP/system.cpu.util,5m)>{$CPU.UTIL.CRIT}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# HOST-RESOURCES-MIB SNMP

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$MEMORY.NAME.MATCHES} |<p>This macro is used in memory discovery. Can be overridden on the host or linked template level.</p> |`.*` |
|{$MEMORY.NAME.NOT_MATCHES} |<p>This macro is used in memory discovery. Can be overridden on the host or linked template level if you need to filter out results.</p> |`CHANGE_IF_NEEDED` |
|{$MEMORY.TYPE.MATCHES} |<p>This macro is used in memory discovery. Can be overridden on the host or linked template level.</p> |`.*(\.2|hrStorageRam)$` |
|{$MEMORY.TYPE.NOT_MATCHES} |<p>This macro is used in memory discovery. Can be overridden on the host or linked template level if you need to filter out results.</p> |`CHANGE_IF_NEEDED` |
|{$MEMORY.UTIL.MAX} |<p>The warning threshold of the "Physical memory: Memory utilization" item.</p> |`90` |
|{$VFS.FS.FREE.MIN.CRIT} |<p>The critical threshold of the filesystem utilization.</p> |`5G` |
|{$VFS.FS.FREE.MIN.WARN} |<p>The warning threshold of the filesystem utilization.</p> |`10G` |
|{$VFS.FS.FSNAME.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`.+` |
|{$VFS.FS.FSNAME.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`^(/dev|/sys|/run|/proc|.+/shm$)` |
|{$VFS.FS.FSTYPE.MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`.*(\.4|\.9|hrStorageFixedDisk|hrStorageFlashMemory)$` |
|{$VFS.FS.FSTYPE.NOT_MATCHES} |<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p> |`CHANGE_IF_NEEDED` |
|{$VFS.FS.PUSED.MAX.CRIT} |<p>-</p> |`90` |
|{$VFS.FS.PUSED.MAX.WARN} |<p>-</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Memory discovery |<p>HOST-RESOURCES-MIB::hrStorage discovery with memory filter</p> |SNMP |vm.memory.discovery<p>**Filter**:</p>AND <p>- {#MEMTYPE} MATCHES_REGEX `{$MEMORY.TYPE.MATCHES}`</p><p>- {#MEMTYPE} NOT_MATCHES_REGEX `{$MEMORY.TYPE.NOT_MATCHES}`</p><p>- {#MEMNAME} MATCHES_REGEX `{$MEMORY.NAME.MATCHES}`</p><p>- {#MEMNAME} NOT_MATCHES_REGEX `{$MEMORY.NAME.NOT_MATCHES}`</p> |
|Storage discovery |<p>HOST-RESOURCES-MIB::hrStorage discovery with storage filter.</p> |SNMP |vfs.fs.discovery[snmp]<p>**Filter**:</p>AND <p>- {#FSTYPE} MATCHES_REGEX `{$VFS.FS.FSTYPE.MATCHES}`</p><p>- {#FSTYPE} NOT_MATCHES_REGEX `{$VFS.FS.FSTYPE.NOT_MATCHES}`</p><p>- {#FSNAME} MATCHES_REGEX `{$VFS.FS.FSNAME.MATCHES}`</p><p>- {#FSNAME} NOT_MATCHES_REGEX `{$VFS.FS.FSNAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: HOST-RESOURCES-MIB</p><p>The average, over the last minute, of the percentage of time that processors was not idle.</p><p>Implementations may approximate this one minute smoothing period if necessary.</p> |SNMP |system.cpu.util<p>**Preprocessing**:</p><p>- JSONPATH: `$..['{#CPU.UTIL}'].avg()`</p> |
|Memory |{#MEMNAME}: Used memory |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p> |SNMP |vm.memory.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ALLOC_UNITS}`</p> |
|Memory |{#MEMNAME}: Total memory |<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main memory allocated to a buffer pool might be modified or the amount of disk space allocated to virtual memory might be modified.</p> |SNMP |vm.memory.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ALLOC_UNITS}`</p> |
|Memory |{#MEMNAME}: Memory utilization |<p>Memory utilization in %.</p> |CALCULATED |vm.memory.util[memoryUsedPercentage.{#SNMPINDEX}]<p>**Expression**:</p>`last(//vm.memory.used[hrStorageUsed.{#SNMPINDEX}])/last(//vm.memory.total[hrStorageSize.{#SNMPINDEX}])*100` |
|Storage |{#FSNAME}: Used space |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p> |SNMP |vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ALLOC_UNITS}`</p> |
|Storage |{#FSNAME}: Total space |<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main storage allocated to a buffer pool might be modified or the amount of disk space allocated to virtual storage might be modified.</p> |SNMP |vfs.fs.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ALLOC_UNITS}`</p> |
|Storage |{#FSNAME}: Space utilization |<p>Space utilization in % for {#FSNAME}</p> |CALCULATED |vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}]<p>**Expression**:</p>`(last(//vfs.fs.used[hrStorageUsed.{#SNMPINDEX}])/last(//vfs.fs.total[hrStorageSize.{#SNMPINDEX}]))*100` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/HOST-RESOURCES-MIB SNMP/system.cpu.util,5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|{#MEMNAME}: High memory utilization |<p>The system is running out of free memory.</p> |`min(/HOST-RESOURCES-MIB SNMP/vm.memory.util[memoryUsedPercentage.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|{#FSNAME}: Disk space is critically low |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`last(/HOST-RESOURCES-MIB SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/HOST-RESOURCES-MIB SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/HOST-RESOURCES-MIB SNMP/vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/HOST-RESOURCES-MIB SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}],1h,100)<1d) ` |AVERAGE |<p>Manual close: YES</p> |
|{#FSNAME}: Disk space is low |<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than {$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}.</p><p> - The disk will be full in less than 24 hours.</p> |`last(/HOST-RESOURCES-MIB SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/HOST-RESOURCES-MIB SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/HOST-RESOURCES-MIB SNMP/vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/HOST-RESOURCES-MIB SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}],1h,100)<1d) ` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#FSNAME}: Disk space is critically low</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

