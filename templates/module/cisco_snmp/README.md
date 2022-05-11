
# Cisco CISCO-MEMORY-POOL-MIB SNMP

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Memory Discovery |<p>Discovery of ciscoMemoryPoolTable, a table of memory pool monitoring entries.</p><p>http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |memory.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Memory |{#SNMPVALUE}: Used memory |<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Indicates the number of bytes from the memory pool that are currently in use by applications on the managed device.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}] |
|Memory |{#SNMPVALUE}: Free memory |<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Indicates the number of bytes from the memory pool that are currently unused on the managed device. Note that the sum of ciscoMemoryPoolUsed and ciscoMemoryPoolFree is the total amount of memory in the pool</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |vm.memory.free[ciscoMemoryPoolFree.{#SNMPINDEX}] |
|Memory |{#SNMPVALUE}: Memory utilization |<p>Memory utilization in %</p> |CALCULATED |vm.memory.util[vm.memory.util.{#SNMPINDEX}]<p>**Expression**:</p>`last(//vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}])/(last(//vm.memory.free[ciscoMemoryPoolFree.{#SNMPINDEX}])+last(//vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}]))*100` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#SNMPVALUE}: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Cisco CISCO-MEMORY-POOL-MIB SNMP/vm.memory.util[vm.memory.util.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Cisco CISCO-PROCESS-MIB SNMP

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

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|CPU Discovery |<p>If your IOS device has several CPUs, you must use CISCO-PROCESS-MIB and its object cpmCPUTotal5minRev from the table called cpmCPUTotalTable,</p><p>indexed with cpmCPUTotalIndex.</p><p>This table allows CISCO-PROCESS-MIB to keep CPU statistics for different physical entities in the router,</p><p>like different CPU chips, group of CPUs, or CPUs in different modules/cards.</p><p>In case of a single CPU, cpmCPUTotalTable has only one entry.</p> |SNMP |cpu.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |#{#SNMPINDEX}: CPU utilization |<p>MIB: CISCO-PROCESS-MIB</p><p>The cpmCPUTotal5minRev MIB object provides a more accurate view of the performance of the router over time than the MIB objects cpmCPUTotal1minRev and cpmCPUTotal5secRev . These MIB objects are not accurate because they look at CPU at one minute and five second intervals, respectively. These MIBs enable you to monitor the trends and plan the capacity of your network. The recommended baseline rising threshold for cpmCPUTotal5minRev is 90 percent. Depending on the platform, some routers that run at 90 percent, for example, 2500s, can exhibit performance degradation versus a high-end router, for example, the 7500 series, which can operate fine.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p> |SNMP |system.cpu.util[cpmCPUTotal5minRev.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|#{#SNMPINDEX}: High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Cisco CISCO-PROCESS-MIB SNMP/system.cpu.util[cpmCPUTotal5minRev.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Cisco CISCO-PROCESS-MIB IOS versions 12.0_3_T-12.2_3.5 SNMP

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

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|CPU Discovery |<p>If your IOS device has several CPUs, you must use CISCO-PROCESS-MIB and its object cpmCPUTotal5minRev from the table called cpmCPUTotalTable,</p><p>indexed with cpmCPUTotalIndex.</p><p>This table allows CISCO-PROCESS-MIB to keep CPU statistics for different physical entities in the router,</p><p>like different CPU chips, group of CPUs, or CPUs in different modules/cards.</p><p>In case of a single CPU, cpmCPUTotalTable has only one entry.</p> |SNMP |cpu.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |{#SNMPVALUE}: CPU utilization |<p>MIB: CISCO-PROCESS-MIB</p><p>The overall CPU busy percentage in the last 5 minute</p><p>period. This object deprecates the avgBusy5 object from</p><p>the OLD-CISCO-SYSTEM-MIB. This object is deprecated</p><p>by cpmCPUTotal5minRev which has the changed range</p><p>of value (0..100).</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p> |SNMP |system.cpu.util[cpmCPUTotal5min.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#SNMPVALUE}: High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Cisco CISCO-PROCESS-MIB IOS versions 12.0_3_T-12.2_3.5 SNMP/system.cpu.util[cpmCPUTotal5min.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Cisco OLD-CISCO-CPU-MIB SNMP

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
|CPU |CPU utilization |<p>MIB: OLD-CISCO-CPU-MIB</p><p>5 minute exponentially-decayed moving average of the CPU busy percentage.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p> |SNMP |system.cpu.util[avgBusy5] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Cisco OLD-CISCO-CPU-MIB SNMP/system.cpu.util[avgBusy5],5m)>{$CPU.UTIL.CRIT}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Cisco Inventory SNMP

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.


## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Entity Serial Numbers Discovery |<p>-</p> |SNMP |entity_sn.discovery<p>**Filter**:</p>AND <p>- {#ENT_SN} MATCHES_REGEX `.+`</p><p>- {#ENT_CLASS} MATCHES_REGEX `^3$`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Inventory |Hardware model name |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system |<p>MIB: SNMPv2-MIB</p> |SNMP |system.sw.os[sysDescr.0]<p>**Preprocessing**:</p><p>- REGEX: `Version (.+), RELEASE \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Cisco Inventory SNMP/system.hw.serialnumber,#1)<>last(/Cisco Inventory SNMP/system.hw.serialnumber,#2) and length(last(/Cisco Inventory SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`last(/Cisco Inventory SNMP/system.sw.os[sysDescr.0],#1)<>last(/Cisco Inventory SNMP/system.sw.os[sysDescr.0],#2) and length(last(/Cisco Inventory SNMP/system.sw.os[sysDescr.0]))>0` |INFO |<p>Manual close: YES</p> |
|{#ENT_NAME}: Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Cisco Inventory SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#1)<>last(/Cisco Inventory SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#2) and length(last(/Cisco Inventory SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Cisco CISCO-ENVMON-MIB SNMP

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$FAN_CRIT_STATUS:"critical"} |<p>-</p> |`3` |
|{$FAN_CRIT_STATUS:"shutdown"} |<p>-</p> |`4` |
|{$FAN_WARN_STATUS:"notFunctioning"} |<p>-</p> |`6` |
|{$FAN_WARN_STATUS:"warning"} |<p>-</p> |`2` |
|{$PSU_CRIT_STATUS:"critical"} |<p>-</p> |`3` |
|{$PSU_CRIT_STATUS:"shutdown"} |<p>-</p> |`4` |
|{$PSU_WARN_STATUS:"notFunctioning"} |<p>-</p> |`6` |
|{$PSU_WARN_STATUS:"warning"} |<p>-</p> |`2` |
|{$TEMP_CRIT:"CPU"} |<p>-</p> |`75` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT_STATUS} |<p>-</p> |`3` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_DISASTER_STATUS} |<p>-</p> |`4` |
|{$TEMP_WARN:"CPU"} |<p>-</p> |`70` |
|{$TEMP_WARN_STATUS} |<p>-</p> |`2` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|FAN Discovery |<p>The table of fan status maintained by the environmental monitor.</p> |SNMP |fan.discovery |
|PSU Discovery |<p>The table of power supply status maintained by the environmental monitor card.</p> |SNMP |psu.discovery |
|Temperature Discovery |<p>Discovery of ciscoEnvMonTemperatureTable (ciscoEnvMonTemperatureDescr), a table of ambient temperature status</p><p>maintained by the environmental monitor.</p> |SNMP |temperature.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |{#SENSOR_INFO}: Fan status |<p>MIB: CISCO-ENVMON-MIB</p> |SNMP |sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}] |
|Power supply |{#SENSOR_INFO}: Power supply status |<p>MIB: CISCO-ENVMON-MIB</p> |SNMP |sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}] |
|Temperature |{#SNMPVALUE}: Temperature |<p>MIB: CISCO-ENVMON-MIB</p><p>The current measurement of the test point being instrumented.</p> |SNMP |sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}] |
|Temperature |{#SNMPVALUE}: Temperature status |<p>MIB: CISCO-ENVMON-MIB</p><p>The current state of the test point being instrumented.</p> |SNMP |sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#SENSOR_INFO}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Cisco CISCO-ENVMON-MIB SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"critical\"}")=1 or count(/Cisco CISCO-ENVMON-MIB SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"shutdown\"}")=1` |AVERAGE | |
|{#SENSOR_INFO}: Fan is in warning state |<p>Please check the fan unit</p> |`count(/Cisco CISCO-ENVMON-MIB SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"warning\"}")=1 or count(/Cisco CISCO-ENVMON-MIB SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"notFunctioning\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Fan is in critical state</p> |
|{#SENSOR_INFO}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Cisco CISCO-ENVMON-MIB SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"critical\"}")=1 or count(/Cisco CISCO-ENVMON-MIB SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"shutdown\"}")=1` |AVERAGE | |
|{#SENSOR_INFO}: Power supply is in warning state |<p>Please check the power supply unit for errors</p> |`count(/Cisco CISCO-ENVMON-MIB SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"warning\"}")=1 or count(/Cisco CISCO-ENVMON-MIB SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"notFunctioning\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Power supply is in critical state</p> |
|{#SNMPVALUE}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco CISCO-ENVMON-MIB SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"} or last(/Cisco CISCO-ENVMON-MIB SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_WARN_STATUS} `<p>Recovery expression:</p>`max(/Cisco CISCO-ENVMON-MIB SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_WARN:"{#SNMPVALUE}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Temperature is above critical threshold</p> |
|{#SNMPVALUE}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco CISCO-ENVMON-MIB SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"} or last(/Cisco CISCO-ENVMON-MIB SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_CRIT_STATUS} or last(/Cisco CISCO-ENVMON-MIB SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_DISASTER_STATUS} `<p>Recovery expression:</p>`max(/Cisco CISCO-ENVMON-MIB SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"{#SNMPVALUE}"}-3` |HIGH | |
|{#SNMPVALUE}: Temperature is too low |<p>-</p> |`avg(/Cisco CISCO-ENVMON-MIB SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`<p>Recovery expression:</p>`min(/Cisco CISCO-ENVMON-MIB SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

