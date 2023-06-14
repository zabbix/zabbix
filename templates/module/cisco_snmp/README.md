
# Cisco CISCO-MEMORY-POOL-MIB by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMORY.UTIL.MAX}||`90`|

### LLD rule Memory Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory Discovery|<p>Discovery of ciscoMemoryPoolTable, a table of memory pool monitoring entries.</p><p>http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p>|SNMP agent|memory.discovery|

### Item prototypes for Memory Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Used memory|<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Indicates the number of bytes from the memory pool that are currently in use by applications on the managed device.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p>|SNMP agent|vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}]|
|{#SNMPVALUE}: Free memory|<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Indicates the number of bytes from the memory pool that are currently unused on the managed device. Note that the sum of ciscoMemoryPoolUsed and ciscoMemoryPoolFree is the total amount of memory in the pool</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p>|SNMP agent|vm.memory.free[ciscoMemoryPoolFree.{#SNMPINDEX}]|
|{#SNMPVALUE}: Memory utilization|<p>Memory utilization in %.</p>|Calculated|vm.memory.util[vm.memory.util.{#SNMPINDEX}]|

### Trigger prototypes for Memory Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Cisco CISCO-MEMORY-POOL-MIB by SNMP/vm.memory.util[vm.memory.util.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}`|Average||

# Cisco CISCO-PROCESS-MIB by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}||`90`|

### LLD rule CPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU Discovery|<p>If your IOS device has several CPUs, you must use CISCO-PROCESS-MIB and its object cpmCPUTotal5minRev from the table called cpmCPUTotalTable,</p><p>indexed with cpmCPUTotalIndex.</p><p>This table allows CISCO-PROCESS-MIB to keep CPU statistics for different physical entities in the router,</p><p>like different CPU chips, group of CPUs, or CPUs in different modules/cards.</p><p>In case of a single CPU, cpmCPUTotalTable has only one entry.</p>|SNMP agent|cpu.discovery|

### Item prototypes for CPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|#{#SNMPINDEX}: CPU utilization|<p>MIB: CISCO-PROCESS-MIB</p><p>The cpmCPUTotal5minRev MIB object provides a more accurate view of the performance of the router over time than the MIB objects cpmCPUTotal1minRev and cpmCPUTotal5secRev . These MIB objects are not accurate because they look at CPU at one minute and five second intervals, respectively. These MIBs enable you to monitor the trends and plan the capacity of your network. The recommended baseline rising threshold for cpmCPUTotal5minRev is 90 percent. Depending on the platform, some routers that run at 90 percent, for example, 2500s, can exhibit performance degradation versus a high-end router, for example, the 7500 series, which can operate fine.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p>|SNMP agent|system.cpu.util[cpmCPUTotal5minRev.{#SNMPINDEX}]|

### Trigger prototypes for CPU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|#{#SNMPINDEX}: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Cisco CISCO-PROCESS-MIB by SNMP/system.cpu.util[cpmCPUTotal5minRev.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}`|Warning||

# Cisco CISCO-PROCESS-MIB IOS versions 12.0_3_T-12.2_3.5 by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}||`90`|

### LLD rule CPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU Discovery|<p>If your IOS device has several CPUs, you must use CISCO-PROCESS-MIB and its object cpmCPUTotal5minRev from the table called cpmCPUTotalTable,</p><p>indexed with cpmCPUTotalIndex.</p><p>This table allows CISCO-PROCESS-MIB to keep CPU statistics for different physical entities in the router,</p><p>like different CPU chips, group of CPUs, or CPUs in different modules/cards.</p><p>In case of a single CPU, cpmCPUTotalTable has only one entry.</p>|SNMP agent|cpu.discovery|

### Item prototypes for CPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: CPU utilization|<p>MIB: CISCO-PROCESS-MIB</p><p>The overall CPU busy percentage in the last 5 minute</p><p>period. This object deprecates the avgBusy5 object from</p><p>the OLD-CISCO-SYSTEM-MIB. This object is deprecated</p><p>by cpmCPUTotal5minRev which has the changed range</p><p>of value (0..100).</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p>|SNMP agent|system.cpu.util[cpmCPUTotal5min.{#SNMPINDEX}]|

### Trigger prototypes for CPU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Cisco CISCO-PROCESS-MIB IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.cpu.util[cpmCPUTotal5min.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}`|Warning||

# Cisco OLD-CISCO-CPU-MIB by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}||`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cisco OLD-CISCO-CPU-MIB: CPU utilization|<p>MIB: OLD-CISCO-CPU-MIB</p><p>5 minute exponentially-decayed moving average of the CPU busy percentage.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p>|SNMP agent|system.cpu.util[avgBusy5]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco OLD-CISCO-CPU-MIB: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Cisco OLD-CISCO-CPU-MIB by SNMP/system.cpu.util[avgBusy5],5m)>{$CPU.UTIL.CRIT}`|Warning||

# Cisco Inventory by SNMP


### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cisco Inventory: Hardware model name|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Inventory: Hardware serial number|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Inventory: Operating system|<p>MIB: SNMPv2-MIB</p>|SNMP agent|system.sw.os[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Regular expression: `Version (.+), RELEASE \1`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco Inventory: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco Inventory by SNMP/system.hw.serialnumber,#1)<>last(/Cisco Inventory by SNMP/system.hw.serialnumber,#2) and length(last(/Cisco Inventory by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Cisco Inventory: Operating system description has changed|<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/Cisco Inventory by SNMP/system.sw.os[sysDescr.0],#1)<>last(/Cisco Inventory by SNMP/system.sw.os[sysDescr.0],#2) and length(last(/Cisco Inventory by SNMP/system.sw.os[sysDescr.0]))>0`|Info|**Manual close**: Yes|

### LLD rule Entity Serial Numbers Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Entity Serial Numbers Discovery||SNMP agent|entity_sn.discovery|

### Item prototypes for Entity Serial Numbers Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ENT_NAME}: Hardware serial number|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Entity Serial Numbers Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#ENT_NAME}: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco Inventory by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#1)<>last(/Cisco Inventory by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#2) and length(last(/Cisco Inventory by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

# Cisco CISCO-ENVMON-MIB by SNMP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TEMP_CRIT}||`60`|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_WARN}||`50`|
|{$TEMP_CRIT:"CPU"}||`75`|
|{$TEMP_WARN:"CPU"}||`70`|
|{$TEMP_WARN_STATUS}||`2`|
|{$TEMP_CRIT_STATUS}||`3`|
|{$TEMP_DISASTER_STATUS}||`4`|
|{$PSU_CRIT_STATUS:"critical"}||`3`|
|{$PSU_CRIT_STATUS:"shutdown"}||`4`|
|{$PSU_WARN_STATUS:"warning"}||`2`|
|{$PSU_WARN_STATUS:"notFunctioning"}||`6`|
|{$FAN_CRIT_STATUS:"critical"}||`3`|
|{$FAN_CRIT_STATUS:"shutdown"}||`4`|
|{$FAN_WARN_STATUS:"warning"}||`2`|
|{$FAN_WARN_STATUS:"notFunctioning"}||`6`|

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery|<p>Discovery of ciscoEnvMonTemperatureTable (ciscoEnvMonTemperatureDescr), a table of ambient temperature status</p><p>maintained by the environmental monitor.</p>|SNMP agent|temperature.discovery|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Temperature|<p>MIB: CISCO-ENVMON-MIB</p><p>The current measurement of the test point being instrumented.</p>|SNMP agent|sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}]|
|{#SNMPVALUE}: Temperature status|<p>MIB: CISCO-ENVMON-MIB</p><p>The current state of the test point being instrumented.</p>|SNMP agent|sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"} or last(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_WARN_STATUS}`|Warning|**Depends on**:<br><ul><li>{#SNMPVALUE}: Temperature is above critical threshold</li></ul>|
|{#SNMPVALUE}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"} or last(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_CRIT_STATUS} or last(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_DISASTER_STATUS}`|High||
|{#SNMPVALUE}: Temperature is too low||`avg(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`|Average||

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery|<p>The table of power supply status maintained by the environmental monitor card.</p>|SNMP agent|psu.discovery|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_INFO}: Power supply status|<p>MIB: CISCO-ENVMON-MIB</p>|SNMP agent|sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SENSOR_INFO}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"critical\"}")=1 or count(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"shutdown\"}")=1`|Average||
|{#SENSOR_INFO}: Power supply is in warning state|<p>Please check the power supply unit for errors</p>|`count(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"warning\"}")=1 or count(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"notFunctioning\"}")=1`|Warning|**Depends on**:<br><ul><li>{#SENSOR_INFO}: Power supply is in critical state</li></ul>|

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery|<p>The table of fan status maintained by the environmental monitor.</p>|SNMP agent|fan.discovery|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_INFO}: Fan status|<p>MIB: CISCO-ENVMON-MIB</p>|SNMP agent|sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}]|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SENSOR_INFO}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"critical\"}")=1 or count(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"shutdown\"}")=1`|Average||
|{#SENSOR_INFO}: Fan is in warning state|<p>Please check the fan unit</p>|`count(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"warning\"}")=1 or count(/Cisco CISCO-ENVMON-MIB by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"notFunctioning\"}")=1`|Warning|**Depends on**:<br><ul><li>{#SENSOR_INFO}: Fan is in critical state</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

