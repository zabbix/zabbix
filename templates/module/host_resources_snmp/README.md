
# HOST-RESOURCES-MIB storage by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`^(/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VFS.FS.FSNAME.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`.*(\.4\|\.9\|hrStorageFixedDisk\|hrStorageFlashMemory)$`|
|{$VFS.FS.FREE.MIN.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`5G`|
|{$VFS.FS.FREE.MIN.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`10G`|
|{$VFS.FS.PUSED.MAX.CRIT}||`90`|
|{$VFS.FS.PUSED.MAX.WARN}||`80`|

### LLD rule Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage discovery|<p>HOST-RESOURCES-MIB::hrStorage discovery with storage filter.</p>|SNMP agent|vfs.fs.discovery[snmp]|

### Item prototypes for Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FSNAME}: Used space|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p>|SNMP agent|vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#FSNAME}: Total space|<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main storage allocated to a buffer pool might be modified or the amount of disk space allocated to virtual storage might be modified.</p>|SNMP agent|vfs.fs.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#FSNAME}: Space utilization|<p>The space utilization expressed in % for {#FSNAME}.</p>|Calculated|vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}]|

### Trigger prototypes for Storage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSNAME}: Disk space is critically low|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`.<br>2. The second condition should be one of the following:<br>- the disk free space is less than `{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}`;<br>- the disk will be full in less than 24 hours.</p>|`last(/HOST-RESOURCES-MIB storage by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/HOST-RESOURCES-MIB storage by SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/HOST-RESOURCES-MIB storage by SNMP/vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/HOST-RESOURCES-MIB storage by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}],1h,100)<1d)`|Average|**Manual close**: Yes|
|{#FSNAME}: Disk space is low|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}`.<br>2. The second condition should be one of the following:<br>- the disk free space is less than `{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}`;<br>- the disk will be full in less than 24 hours.</p>|`last(/HOST-RESOURCES-MIB storage by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/HOST-RESOURCES-MIB storage by SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/HOST-RESOURCES-MIB storage by SNMP/vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/HOST-RESOURCES-MIB storage by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}],1h,100)<1d)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#FSNAME}: Disk space is critically low</li></ul>|

# HOST-RESOURCES-MIB memory by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMORY.UTIL.MAX}|<p>The warning threshold of the "Physical memory: Memory utilization" item.</p>|`90`|
|{$MEMORY.TYPE.NOT_MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host or linked template level if you need to filter out results.</p>|`CHANGE_IF_NEEDED`|
|{$MEMORY.TYPE.MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host or linked template level.</p>|`.*(\.2\|hrStorageRam)$`|
|{$MEMORY.NAME.MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$MEMORY.NAME.NOT_MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host or linked template level if you need to filter out results.</p>|`CHANGE_IF_NEEDED`|

### LLD rule Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory discovery|<p>HOST-RESOURCES-MIB::hrStorage discovery with memory filter</p>|SNMP agent|vm.memory.discovery|

### Item prototypes for Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#MEMNAME}: Used memory|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p>|SNMP agent|vm.memory.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#MEMNAME}: Total memory|<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main memory allocated to a buffer pool might be modified or the amount of disk space allocated to virtual memory might be modified.</p>|SNMP agent|vm.memory.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#MEMNAME}: Memory utilization|<p>Memory utilization in %.</p>|Calculated|vm.memory.util[memoryUsedPercentage.{#SNMPINDEX}]|

### Trigger prototypes for Memory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#MEMNAME}: High memory utilization|<p>The system is running out of free memory.</p>|`min(/HOST-RESOURCES-MIB memory by SNMP/vm.memory.util[memoryUsedPercentage.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}`|Average||

# HOST-RESOURCES-MIB CPU by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}||`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU: CPU utilization|<p>MIB: HOST-RESOURCES-MIB</p><p>The average, over the last minute, of the percentage of time that processors was not idle.</p><p>Implementations may approximate this one minute smoothing period if necessary.</p>|SNMP agent|system.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#CPU.UTIL}'].avg()`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|CPU: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/HOST-RESOURCES-MIB CPU by SNMP/system.cpu.util,5m)>{$CPU.UTIL.CRIT}`|Warning||

# HOST-RESOURCES-MIB by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`^(/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VFS.FS.FSNAME.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>This macro is used in filesystems discovery. Can be overridden on the host or linked template level.</p>|`.*(\.4\|\.9\|hrStorageFixedDisk\|hrStorageFlashMemory)$`|
|{$VFS.FS.FREE.MIN.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`5G`|
|{$VFS.FS.FREE.MIN.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`10G`|
|{$VFS.FS.PUSED.MAX.CRIT}||`90`|
|{$VFS.FS.PUSED.MAX.WARN}||`80`|
|{$MEMORY.UTIL.MAX}|<p>The warning threshold of the "Physical memory: Memory utilization" item.</p>|`90`|
|{$MEMORY.TYPE.NOT_MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host or linked template level if you need to filter out results.</p>|`CHANGE_IF_NEEDED`|
|{$MEMORY.TYPE.MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host or linked template level.</p>|`.*(\.2\|hrStorageRam)$`|
|{$MEMORY.NAME.MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$MEMORY.NAME.NOT_MATCHES}|<p>This macro is used in memory discovery. Can be overridden on the host or linked template level if you need to filter out results.</p>|`CHANGE_IF_NEEDED`|
|{$CPU.UTIL.CRIT}||`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU: CPU utilization|<p>MIB: HOST-RESOURCES-MIB</p><p>The average, over the last minute, of the percentage of time that processors was not idle.</p><p>Implementations may approximate this one minute smoothing period if necessary.</p>|SNMP agent|system.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..['{#CPU.UTIL}'].avg()`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|CPU: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/HOST-RESOURCES-MIB by SNMP/system.cpu.util,5m)>{$CPU.UTIL.CRIT}`|Warning||

### LLD rule Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage discovery|<p>HOST-RESOURCES-MIB::hrStorage discovery with storage filter.</p>|SNMP agent|vfs.fs.discovery[snmp]|

### Item prototypes for Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FSNAME}: Used space|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p>|SNMP agent|vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#FSNAME}: Total space|<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main storage allocated to a buffer pool might be modified or the amount of disk space allocated to virtual storage might be modified.</p>|SNMP agent|vfs.fs.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#FSNAME}: Space utilization|<p>The space utilization expressed in % for {#FSNAME}.</p>|Calculated|vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}]|

### Trigger prototypes for Storage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSNAME}: Disk space is critically low|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`.<br>2. The second condition should be one of the following:<br>- the disk free space is less than `{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"}`;<br>- the disk will be full in less than 24 hours.</p>|`last(/HOST-RESOURCES-MIB by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"} and ((last(/HOST-RESOURCES-MIB by SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/HOST-RESOURCES-MIB by SNMP/vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]))<{$VFS.FS.FREE.MIN.CRIT:"{#FSNAME}"} or timeleft(/HOST-RESOURCES-MIB by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}],1h,100)<1d)`|Average|**Manual close**: Yes|
|{#FSNAME}: Disk space is low|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}`.<br>2. The second condition should be one of the following:<br>- the disk free space is less than `{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"}`;<br>- the disk will be full in less than 24 hours.</p>|`last(/HOST-RESOURCES-MIB by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}])>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"} and ((last(/HOST-RESOURCES-MIB by SNMP/vfs.fs.total[hrStorageSize.{#SNMPINDEX}])-last(/HOST-RESOURCES-MIB by SNMP/vfs.fs.used[hrStorageUsed.{#SNMPINDEX}]))<{$VFS.FS.FREE.MIN.WARN:"{#FSNAME}"} or timeleft(/HOST-RESOURCES-MIB by SNMP/vfs.fs.pused[storageUsedPercentage.{#SNMPINDEX}],1h,100)<1d)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#FSNAME}: Disk space is critically low</li></ul>|

### LLD rule Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory discovery|<p>HOST-RESOURCES-MIB::hrStorage discovery with memory filter</p>|SNMP agent|vm.memory.discovery|

### Item prototypes for Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#MEMNAME}: Used memory|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p>|SNMP agent|vm.memory.used[hrStorageUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#MEMNAME}: Total memory|<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in units of hrStorageAllocationUnits.</p><p>This object is writable to allow remote configuration of the size of the storage area in those cases where such an operation makes sense and is possible on the underlying system.</p><p>For example, the amount of main memory allocated to a buffer pool might be modified or the amount of disk space allocated to virtual memory might be modified.</p>|SNMP agent|vm.memory.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#ALLOC_UNITS}`</p></li></ul>|
|{#MEMNAME}: Memory utilization|<p>Memory utilization in %.</p>|Calculated|vm.memory.util[memoryUsedPercentage.{#SNMPINDEX}]|

### Trigger prototypes for Memory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#MEMNAME}: High memory utilization|<p>The system is running out of free memory.</p>|`min(/HOST-RESOURCES-MIB by SNMP/vm.memory.util[memoryUsedPercentage.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

