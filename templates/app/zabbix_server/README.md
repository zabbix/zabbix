
# Zabbix server health

## Overview

This template is designed to monitor internal Zabbix metrics on the local Zabbix server.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- Zabbix server 6.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Link this template to the local Zabbix server host.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ZABBIX.SERVER.UTIL.MAX}|<p>Default maximum threshold for percentage utilization triggers (use macro context for specification).</p>|`75`|
|{$ZABBIX.SERVER.UTIL.MIN}|<p>Default minimum threshold for percentage utilization triggers (use macro context for specification).</p>|`65`|
|{$ZABBIX.SERVER.UTIL.MAX:"value cache"}|<p>Maximum threshold for value cache utilization trigger.</p>|`95`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Zabbix server: Cluster stats|<p>The master item of Zabbix cluster statistics.</p>|Zabbix internal|zabbix[cluster,discovery,nodes]|
|Zabbix server: Queue over 10 minutes|<p>The number of monitored items in the queue, which are delayed at least by 10 minutes.</p>|Zabbix internal|zabbix[queue,10m]|
|Zabbix server: Queue|<p>The number of monitored items in the queue, which are delayed at least by 6 seconds.</p>|Zabbix internal|zabbix[queue]|
|Zabbix server: Utilization of alert manager internal processes, in %|<p>The average percentage of the time during which the alert manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,alert manager,avg,busy]|
|Zabbix server: Utilization of alert syncer internal processes, in %|<p>The average percentage of the time during which the alert syncer processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,alert syncer,avg,busy]|
|Zabbix server: Utilization of alerter internal processes, in %|<p>The average percentage of the time during which the alerter processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,alerter,avg,busy]|
|Zabbix server: Utilization of availability manager internal processes, in %|<p>The average percentage of the time during which the availability manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,availability manager,avg,busy]|
|Zabbix server: Utilization of configuration syncer internal processes, in %|<p>The average percentage of the time during which the configuration syncer processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,configuration syncer,avg,busy]|
|Zabbix server: Utilization of discoverer internal processes, in %|<p>The average percentage of the time during which the discoverer processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,discoverer,avg,busy]|
|Zabbix server: Utilization of escalator internal processes, in %|<p>The average percentage of the time during which the escalator processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,escalator,avg,busy]|
|Zabbix server: Utilization of history poller internal processes, in %|<p>The average percentage of the time during which the history poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,history poller,avg,busy]|
|Zabbix server: Utilization of ODBC poller data collector processes, in %|<p>The average percentage of the time during which the ODBC poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,odbc poller,avg,busy]|
|Zabbix server: Utilization of history syncer internal processes, in %|<p>The average percentage of the time during which the history syncer processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,history syncer,avg,busy]|
|Zabbix server: Utilization of housekeeper internal processes, in %|<p>The average percentage of the time during which the housekeeper processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,housekeeper,avg,busy]|
|Zabbix server: Utilization of http poller data collector processes, in %|<p>The average percentage of the time during which the http poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,http poller,avg,busy]|
|Zabbix server: Utilization of icmp pinger data collector processes, in %|<p>The average percentage of the time during which the icmp pinger processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,icmp pinger,avg,busy]|
|Zabbix server: Utilization of ipmi manager internal processes, in %|<p>The average percentage of the time during which the ipmi manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,ipmi manager,avg,busy]|
|Zabbix server: Utilization of ipmi poller data collector processes, in %|<p>The average percentage of the time during which the ipmi poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,ipmi poller,avg,busy]|
|Zabbix server: Utilization of java poller data collector processes, in %|<p>The average percentage of the time during which the java poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,java poller,avg,busy]|
|Zabbix server: Utilization of LLD manager internal processes, in %|<p>The average percentage of the time during which the LLD manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,lld manager,avg,busy]|
|Zabbix server: Utilization of LLD worker internal processes, in %|<p>The average percentage of the time during which the LLD worker processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,lld worker,avg,busy]|
|Zabbix server: Utilization of poller data collector processes, in %|<p>The average percentage of the time during which the poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,poller,avg,busy]|
|Zabbix server: Utilization of preprocessing worker internal processes, in %|<p>The average percentage of the time during which the preprocessing worker processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,preprocessing worker,avg,busy]|
|Zabbix server: Utilization of preprocessing manager internal processes, in %|<p>The average percentage of the time during which the preprocessing manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,preprocessing manager,avg,busy]|
|Zabbix server: Utilization of proxy poller data collector processes, in %|<p>The average percentage of the time during which the proxy poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,proxy poller,avg,busy]|
|Zabbix server: Utilization of report manager internal processes, in %|<p>The average percentage of the time during which the report manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,report manager,avg,busy]|
|Zabbix server: Utilization of report writer internal processes, in %|<p>The average percentage of the time during which the report writer processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,report writer,avg,busy]|
|Zabbix server: Utilization of self-monitoring internal processes, in %|<p>The average percentage of the time during which the self-monitoring processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,self-monitoring,avg,busy]|
|Zabbix server: Utilization of snmp trapper data collector processes, in %|<p>The average percentage of the time during which the snmp trapper processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,snmp trapper,avg,busy]|
|Zabbix server: Utilization of task manager internal processes, in %|<p>The average percentage of the time during which the task manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,task manager,avg,busy]|
|Zabbix server: Utilization of timer internal processes, in %|<p>The average percentage of the time during which the timer processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,timer,avg,busy]|
|Zabbix server: Utilization of service manager internal processes, in %|<p>The average percentage of the time during which the service manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,service manager,avg,busy]|
|Zabbix server: Utilization of trigger housekeeper internal processes, in %|<p>The average percentage of the time during which the trigger housekeeper processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,trigger housekeeper,avg,busy]|
|Zabbix server: Utilization of trapper data collector processes, in %|<p>The average percentage of the time during which the trapper processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,trapper,avg,busy]|
|Zabbix server: Utilization of unreachable poller data collector processes, in %|<p>The average percentage of the time during which the unreachable poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,unreachable poller,avg,busy]|
|Zabbix server: Utilization of vmware collector data collector processes, in %|<p>The average percentage of the time during which the vmware collector processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,vmware collector,avg,busy]|
|Zabbix server: Configuration cache, % used|<p>The availability statistics of Zabbix configuration cache. The percentage of used data buffer.</p>|Zabbix internal|zabbix[rcache,buffer,pused]|
|Zabbix server: Trend function cache, % of unique requests|<p>The effectiveness statistics of Zabbix trend function cache. The percentage of cached items calculated from the sum of cached items plus requests.</p><p>Low percentage most likely means that the cache size can be reduced.</p>|Zabbix internal|zabbix[tcache,cache,pitems]|
|Zabbix server: Trend function cache, % of misses|<p>The effectiveness statistics of Zabbix trend function cache. The percentage of cache misses.</p>|Zabbix internal|zabbix[tcache,cache,pmisses]|
|Zabbix server: Value cache, % used|<p>The availability statistics of Zabbix value cache. The percentage of used data buffer.</p>|Zabbix internal|zabbix[vcache,buffer,pused]|
|Zabbix server: Value cache hits|<p>The effectiveness statistics of Zabbix value cache. The number of cache hits (history values taken from the cache).</p>|Zabbix internal|zabbix[vcache,cache,hits]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Value cache misses|<p>The effectiveness statistics of Zabbix value cache. The number of cache misses (history values taken from the database).</p>|Zabbix internal|zabbix[vcache,cache,misses]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Value cache operating mode|<p>The operating mode of the value cache.</p>|Zabbix internal|zabbix[vcache,cache,mode]|
|Zabbix server: Version|<p>A version of Zabbix server.</p>|Zabbix internal|zabbix[version]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Zabbix server: VMware cache, % used|<p>The availability statistics of Zabbix vmware cache. The percentage of used data buffer.</p>|Zabbix internal|zabbix[vmware,buffer,pused]|
|Zabbix server: History write cache, % used|<p>The statistics and availability of Zabbix write cache. The percentage of used history buffer.</p><p>The history cache is used to store item values. A high number indicates performance problems on the database side.</p>|Zabbix internal|zabbix[wcache,history,pused]|
|Zabbix server: History index cache, % used|<p>The statistics and availability of Zabbix write cache. The percentage of used history index buffer.</p><p>The history index cache is used to index values stored in the history cache.</p>|Zabbix internal|zabbix[wcache,index,pused]|
|Zabbix server: Trend write cache, % used|<p>The statistics and availability of Zabbix write cache. The percentage of used trend buffer.</p><p>The trend cache stores the aggregate of all items that have received data for the current hour.</p>|Zabbix internal|zabbix[wcache,trend,pused]|
|Zabbix server: Number of processed values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The total number of values processed by Zabbix server or Zabbix proxy, except unsupported items.</p>|Zabbix internal|zabbix[wcache,values]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of processed numeric (unsigned) values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed numeric (unsigned) values.</p>|Zabbix internal|zabbix[wcache,values,uint]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of processed numeric (float) values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed numeric (float) values.</p>|Zabbix internal|zabbix[wcache,values,float]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of processed log values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed log values.</p>|Zabbix internal|zabbix[wcache,values,log]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of processed not supported values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of times the item processing resulted in an item becoming unsupported or keeping that state.</p>|Zabbix internal|zabbix[wcache,values,not supported]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of processed character values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed character values.</p>|Zabbix internal|zabbix[wcache,values,str]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of processed text values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed text values.</p>|Zabbix internal|zabbix[wcache,values,text]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: LLD queue|<p>The count of values enqueued in the low-level discovery processing queue.</p>|Zabbix internal|zabbix[lld_queue]|
|Zabbix server: Preprocessing queue|<p>The count of values enqueued in the preprocessing queue.</p>|Zabbix internal|zabbix[preprocessing_queue]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Zabbix server: More than 100 items having missing data for more than 10 minutes||`min(/Zabbix server health/zabbix[queue,10m],10m)>100`|Warning|**Manual close**: Yes|
|Zabbix server: Utilization of alert manager processes is high||`avg(/Zabbix server health/zabbix[process,alert manager,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"alert manager"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of alert syncer processes is high||`avg(/Zabbix server health/zabbix[process,alert syncer,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"alert syncer"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of alerter processes is high||`avg(/Zabbix server health/zabbix[process,alerter,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"alerter"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of availability manager processes is high||`avg(/Zabbix server health/zabbix[process,availability manager,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"availability manager"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of configuration syncer processes is high||`avg(/Zabbix server health/zabbix[process,configuration syncer,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"configuration syncer"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of discoverer processes is high||`avg(/Zabbix server health/zabbix[process,discoverer,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"discoverer"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of escalator processes is high||`avg(/Zabbix server health/zabbix[process,escalator,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"escalator"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of history poller processes is high||`avg(/Zabbix server health/zabbix[process,history poller,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"history poller"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of ODBC poller processes is high||`avg(/Zabbix server health/zabbix[process,odbc poller,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"ODBC poller"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of history syncer processes is high||`avg(/Zabbix server health/zabbix[process,history syncer,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"history syncer"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of housekeeper processes is high||`avg(/Zabbix server health/zabbix[process,housekeeper,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"housekeeper"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of http poller processes is high||`avg(/Zabbix server health/zabbix[process,http poller,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"http poller"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of icmp pinger processes is high||`avg(/Zabbix server health/zabbix[process,icmp pinger,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"icmp pinger"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of ipmi manager processes is high||`avg(/Zabbix server health/zabbix[process,ipmi manager,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"ipmi manager"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of ipmi poller processes is high||`avg(/Zabbix server health/zabbix[process,ipmi poller,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"ipmi poller"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of java poller processes is high||`avg(/Zabbix server health/zabbix[process,java poller,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"java poller"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of LLD manager processes is high||`avg(/Zabbix server health/zabbix[process,lld manager,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"LLD manager"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of LLD worker processes is high||`avg(/Zabbix server health/zabbix[process,lld worker,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"LLD worker"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of poller processes is high||`avg(/Zabbix server health/zabbix[process,poller,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"poller"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of preprocessing worker processes is high||`avg(/Zabbix server health/zabbix[process,preprocessing worker,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"preprocessing worker"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of preprocessing manager processes is high||`avg(/Zabbix server health/zabbix[process,preprocessing manager,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"preprocessing manager"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of proxy poller processes is high||`avg(/Zabbix server health/zabbix[process,proxy poller,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"proxy poller"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of report manager processes is high||`avg(/Zabbix server health/zabbix[process,report manager,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"report manager"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of report writer processes is high||`avg(/Zabbix server health/zabbix[process,report writer,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"report writer"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of self-monitoring processes is high||`avg(/Zabbix server health/zabbix[process,self-monitoring,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"self-monitoring"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of snmp trapper processes is high||`avg(/Zabbix server health/zabbix[process,snmp trapper,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"snmp trapper"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of task manager processes is high||`avg(/Zabbix server health/zabbix[process,task manager,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"task manager"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of timer processes is high||`avg(/Zabbix server health/zabbix[process,timer,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"timer"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of service manager processes is high||`avg(/Zabbix server health/zabbix[process,service manager,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"service manager"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of trigger housekeeper processes is high||`avg(/Zabbix server health/zabbix[process,trigger housekeeper,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"trigger housekeeper"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of trapper processes is high||`avg(/Zabbix server health/zabbix[process,trapper,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"trapper"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of unreachable poller processes is high||`avg(/Zabbix server health/zabbix[process,unreachable poller,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"unreachable poller"}`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of vmware collector processes is high||`avg(/Zabbix server health/zabbix[process,vmware collector,avg,busy],10m)>{$ZABBIX.SERVER.UTIL.MAX:"vmware collector"}`|Average|**Manual close**: Yes|
|Zabbix server: More than {$ZABBIX.SERVER.UTIL.MAX:"configuration cache"}% used in the configuration cache|<p>Consider increasing `CacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[rcache,buffer,pused],10m)>{$ZABBIX.SERVER.UTIL.MAX:"configuration cache"}`|Average|**Manual close**: Yes|
|Zabbix server: More than {$ZABBIX.SERVER.UTIL.MAX:"value cache"}% used in the value cache|<p>Consider increasing `ValueCacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[vcache,buffer,pused],10m)>{$ZABBIX.SERVER.UTIL.MAX:"value cache"}`|Average|**Manual close**: Yes|
|Zabbix server: Zabbix value cache working in low memory mode|<p>Once the low memory mode has been switched on, the value cache will remain in this state for 24 hours, even if the problem that triggered this mode is resolved sooner.</p>|`last(/Zabbix server health/zabbix[vcache,cache,mode])=1`|High|**Manual close**: Yes|
|Zabbix server: Version has changed|<p>Zabbix server version has changed. Acknowledge to close the problem manually.</p>|`last(/Zabbix server health/zabbix[version],#1)<>last(/Zabbix server health/zabbix[version],#2) and length(last(/Zabbix server health/zabbix[version]))>0`|Info|**Manual close**: Yes|
|Zabbix server: More than {$ZABBIX.SERVER.UTIL.MAX:"vmware cache"}% used in the vmware cache|<p>Consider increasing `VMwareCacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[vmware,buffer,pused],10m)>{$ZABBIX.SERVER.UTIL.MAX:"vmware cache"}`|Average|**Manual close**: Yes|
|Zabbix server: More than {$ZABBIX.SERVER.UTIL.MAX:"history cache"}% used in the history cache|<p>Consider increasing `HistoryCacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[wcache,history,pused],10m)>{$ZABBIX.SERVER.UTIL.MAX:"history cache"}`|Average|**Manual close**: Yes|
|Zabbix server: More than {$ZABBIX.SERVER.UTIL.MAX:"index cache"}% used in the history index cache|<p>Consider increasing `HistoryIndexCacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[wcache,index,pused],10m)>{$ZABBIX.SERVER.UTIL.MAX:"index cache"}`|Average|**Manual close**: Yes|
|Zabbix server: More than {$ZABBIX.SERVER.UTIL.MAX:"trend cache"}% used in the trends cache|<p>Consider increasing `TrendCacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[wcache,trend,pused],10m)>{$ZABBIX.SERVER.UTIL.MAX:"trend cache"}`|Average|**Manual close**: Yes|

### LLD rule High availability cluster node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|High availability cluster node discovery|<p>LLD rule with item and trigger prototypes for the node discovery.</p>|Dependent item|zabbix.nodes.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for High availability cluster node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster node [{#NODE.NAME}]: Stats|<p>Provides the statistics of a node.</p>|Dependent item|zabbix.node.stats[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id=="{#NODE.ID}")].first()`</p></li></ul>|
|Cluster node [{#NODE.NAME}]: Address|<p>The IPv4 address of a node.</p>|Dependent item|zabbix.node.address[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.address`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Cluster node [{#NODE.NAME}]: Last access time|<p>Last access time.</p>|Dependent item|zabbix.node.lastaccess.time[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastaccess`</p></li></ul>|
|Cluster node [{#NODE.NAME}]: Last access age|<p>The time between the database's `unix_timestamp()` and the last access time.</p>|Dependent item|zabbix.node.lastaccess.age[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastaccess_age`</p></li></ul>|
|Cluster node [{#NODE.NAME}]: Status|<p>The status of a node.</p>|Dependent item|zabbix.node.status[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for High availability cluster node discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cluster node [{#NODE.NAME}]: Status changed|<p>The state of the node has changed. Acknowledge to close the problem manually.</p>|`last(/Zabbix server health/zabbix.node.status[{#NODE.ID}],#1)<>last(/Zabbix server health/zabbix.node.status[{#NODE.ID}],#2)`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

