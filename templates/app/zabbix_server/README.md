
# Zabbix server health

## Overview

This template is designed to monitor internal Zabbix metrics on the local Zabbix server.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Zabbix server 7.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Link this template to the local Zabbix server host.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PROXY.LAST_SEEN.MAX}|<p>The maximum number of seconds that Zabbix proxy has not been seen.</p>|`600`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Zabbix server: Zabbix stats cluster|<p>The master item of Zabbix cluster statistics.</p>|Zabbix internal|zabbix[cluster,discovery,nodes]|
|Zabbix server: Zabbix proxies stats|<p>The master item of Zabbix proxies' statistics.</p>|Zabbix internal|zabbix[proxy,discovery]|
|Zabbix server: Queue over 10 minutes|<p>The number of monitored items in the queue, which are delayed at least by 10 minutes.</p>|Zabbix internal|zabbix[queue,10m]|
|Zabbix server: Queue|<p>The number of monitored items in the queue, which are delayed at least by 6 seconds.</p>|Zabbix internal|zabbix[queue]|
|Zabbix server: Utilization of alert manager internal processes, in %|<p>The average percentage of the time during which the alert manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,alert manager,avg,busy]|
|Zabbix server: Utilization of alert syncer internal processes, in %|<p>The average percentage of the time during which the alert syncer processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,alert syncer,avg,busy]|
|Zabbix server: Utilization of alerter internal processes, in %|<p>The average percentage of the time during which the alerter processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,alerter,avg,busy]|
|Zabbix server: Utilization of availability manager internal processes, in %|<p>The average percentage of the time during which the availability manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,availability manager,avg,busy]|
|Zabbix server: Utilization of configuration syncer internal processes, in %|<p>The average percentage of the time during which the configuration syncer processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,configuration syncer,avg,busy]|
|Zabbix server: Utilization of escalator internal processes, in %|<p>The average percentage of the time during which the escalator processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,escalator,avg,busy]|
|Zabbix server: Utilization of history poller data collector processes, in %|<p>The average percentage of the time during which the history poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,history poller,avg,busy]|
|Zabbix server: Utilization of ODBC poller data collector processes, in %|<p>The average percentage of the time during which the ODBC poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,odbc poller,avg,busy]|
|Zabbix server: Utilization of history syncer internal processes, in %|<p>The average percentage of the time during which the history syncer processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,history syncer,avg,busy]|
|Zabbix server: Utilization of housekeeper internal processes, in %|<p>The average percentage of the time during which the housekeeper processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,housekeeper,avg,busy]|
|Zabbix server: Utilization of http poller data collector processes, in %|<p>The average percentage of the time during which the http poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,http poller,avg,busy]|
|Zabbix server: Utilization of icmp pinger data collector processes, in %|<p>The average percentage of the time during which the icmp pinger processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,icmp pinger,avg,busy]|
|Zabbix server: Utilization of ipmi manager internal processes, in %|<p>The average percentage of the time during which the ipmi manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,ipmi manager,avg,busy]|
|Zabbix server: Utilization of ipmi poller data collector processes, in %|<p>The average percentage of the time during which the ipmi poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,ipmi poller,avg,busy]|
|Zabbix server: Utilization of java poller data collector processes, in %|<p>The average percentage of the time during which the java poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,java poller,avg,busy]|
|Zabbix server: Utilization of LLD manager internal processes, in %|<p>The average percentage of the time during which the lld manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,lld manager,avg,busy]|
|Zabbix server: Utilization of LLD worker internal processes, in %|<p>The average percentage of the time during which the lld worker processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,lld worker,avg,busy]|
|Zabbix server: Utilization of connector manager internal processes, in %|<p>The average percentage of the time during which the connector manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,connector manager,avg,busy]|
|Zabbix server: Utilization of connector worker internal processes, in %|<p>The average percentage of the time during which the connector worker processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,connector worker,avg,busy]|
|Zabbix server: Utilization of discovery manager internal processes, in %|<p>The average percentage of the time during which the discovery manager processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,discovery manager,avg,busy]|
|Zabbix server: Utilization of discovery worker internal processes, in %|<p>The average percentage of the time during which the discovery worker processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,discovery worker,avg,busy]|
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
|Zabbix server: Utilization of vmware data collector processes, in %|<p>The average percentage of the time during which the vmware collector processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,vmware collector,avg,busy]|
|Zabbix server: Utilization of agent poller data collector processes, in %|<p>The average percentage of the time during which the agent poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,agent poller,avg,busy]|
|Zabbix server: Utilization of http agent poller data collector processes, in %|<p>The average percentage of the time during which the http agent poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,http agent poller,avg,busy]|
|Zabbix server: Utilization of snmp poller data collector processes, in %|<p>The average percentage of the time during which the snmp poller processes have been busy for the last minute.</p>|Zabbix internal|zabbix[process,snmp poller,avg,busy]|
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
|Zabbix server: Number of processed numeric (float) values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed float values.</p>|Zabbix internal|zabbix[wcache,values,float]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of processed log values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed log values.</p>|Zabbix internal|zabbix[wcache,values,log]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of processed not supported values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of times the item processing resulted in an item becoming unsupported or keeping that state.</p>|Zabbix internal|zabbix[wcache,values,not supported]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of processed character values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed character/string values.</p>|Zabbix internal|zabbix[wcache,values,str]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of processed text values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed text values.</p>|Zabbix internal|zabbix[wcache,values,text]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: Number of values synchronized with the database per second.|<p>Average quantity of values written to the database, recalculated once per minute.</p>|Zabbix internal|zabbix[vps,written]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix server: LLD queue|<p>The count of values enqueued in the low-level discovery processing queue.</p>|Zabbix internal|zabbix[lld_queue]|
|Zabbix server: Preprocessing queue|<p>The count of values enqueued in the preprocessing queue.</p>|Zabbix internal|zabbix[preprocessing_queue]|
|Zabbix server: Connector queue|<p>The count of values enqueued in the connector queue.</p>|Zabbix internal|zabbix[connector_queue]|
|Zabbix server: Discovery queue|<p>The count of values enqueued in the discovery queue.</p>|Zabbix internal|zabbix[discovery_queue]|
|Zabbix server: Number of processed numeric (unsigned) values per second|<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed numeric (unsigned) values.</p>|Zabbix internal|zabbix[wcache,values,uint]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Zabbix server: More than 100 items having missing data for more than 10 minutes|<p>The `zabbix[stats,{$IP},{$PORT},queue,10m]` item collects data about the number of items that have been missing the data for more than 10 minutes.</p>|`min(/Zabbix server health/zabbix[queue,10m],10m)>100`|Warning|**Manual close**: Yes|
|Zabbix server: Utilization of alert manager processes is high||`avg(/Zabbix server health/zabbix[process,alert manager,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of alert syncer processes is high||`avg(/Zabbix server health/zabbix[process,alert syncer,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of alerter processes is high||`avg(/Zabbix server health/zabbix[process,alerter,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of availability manager processes is high||`avg(/Zabbix server health/zabbix[process,availability manager,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of configuration syncer processes is high||`avg(/Zabbix server health/zabbix[process,configuration syncer,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of escalator processes is high||`avg(/Zabbix server health/zabbix[process,escalator,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of history poller processes is high||`avg(/Zabbix server health/zabbix[process,history poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of ODBC poller processes is high||`avg(/Zabbix server health/zabbix[process,odbc poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of history syncer processes is high||`avg(/Zabbix server health/zabbix[process,history syncer,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of housekeeper processes is high||`avg(/Zabbix server health/zabbix[process,housekeeper,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of http poller processes is high||`avg(/Zabbix server health/zabbix[process,http poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of icmp pinger processes is high||`avg(/Zabbix server health/zabbix[process,icmp pinger,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of ipmi manager processes is high||`avg(/Zabbix server health/zabbix[process,ipmi manager,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of ipmi poller processes is high||`avg(/Zabbix server health/zabbix[process,ipmi poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of java poller processes is high||`avg(/Zabbix server health/zabbix[process,java poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of lld manager processes is high||`avg(/Zabbix server health/zabbix[process,lld manager,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of lld worker processes is high||`avg(/Zabbix server health/zabbix[process,lld worker,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of connector manager processes is high||`avg(/Zabbix server health/zabbix[process,connector manager,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of connector worker processes is high||`avg(/Zabbix server health/zabbix[process,connector worker,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of discovery manager processes is high||`avg(/Zabbix server health/zabbix[process,discovery manager,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of discovery worker processes is high||`avg(/Zabbix server health/zabbix[process,discovery worker,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of poller processes is high||`avg(/Zabbix server health/zabbix[process,poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of preprocessing worker processes is high||`avg(/Zabbix server health/zabbix[process,preprocessing worker,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of preprocessing manager processes is high||`avg(/Zabbix server health/zabbix[process,preprocessing manager,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of proxy poller processes is high||`avg(/Zabbix server health/zabbix[process,proxy poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of report manager processes is high||`avg(/Zabbix server health/zabbix[process,report manager,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of report writer processes is high||`avg(/Zabbix server health/zabbix[process,report writer,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of self-monitoring processes is high||`avg(/Zabbix server health/zabbix[process,self-monitoring,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of snmp trapper processes is high||`avg(/Zabbix server health/zabbix[process,snmp trapper,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of task manager processes is high||`avg(/Zabbix server health/zabbix[process,task manager,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of timer processes is high||`avg(/Zabbix server health/zabbix[process,timer,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of service manager processes is high||`avg(/Zabbix server health/zabbix[process,service manager,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of trigger housekeeper processes is high||`avg(/Zabbix server health/zabbix[process,trigger housekeeper,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of trapper processes is high||`avg(/Zabbix server health/zabbix[process,trapper,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of unreachable poller processes is high||`avg(/Zabbix server health/zabbix[process,unreachable poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of vmware collector processes is high||`avg(/Zabbix server health/zabbix[process,vmware collector,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of agent poller processes is high||`avg(/Zabbix server health/zabbix[process,agent poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of http agent poller processes is high||`avg(/Zabbix server health/zabbix[process,http agent poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: Utilization of snmp poller processes is high||`avg(/Zabbix server health/zabbix[process,snmp poller,avg,busy],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: More than 75% used in the configuration cache|<p>Consider increasing `CacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[rcache,buffer,pused],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: More than 95% used in the value cache|<p>Consider increasing `ValueCacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[vcache,buffer,pused],10m)>95`|Average|**Manual close**: Yes|
|Zabbix server: Zabbix value cache working in low memory mode|<p>Once the low memory mode has been switched on, the value cache will remain in this state for 24 hours, even if the problem that triggered this mode is resolved sooner.</p>|`last(/Zabbix server health/zabbix[vcache,cache,mode])=1`|High|**Manual close**: Yes|
|Zabbix server: Version has changed|<p>Zabbix server version has changed. Acknowledge to close the problem manually.</p>|`last(/Zabbix server health/zabbix[version],#1)<>last(/Zabbix server health/zabbix[version],#2) and length(last(/Zabbix server health/zabbix[version]))>0`|Info|**Manual close**: Yes|
|Zabbix server: More than 75% used in the vmware cache|<p>Consider increasing `VMwareCacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[vmware,buffer,pused],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: More than 75% used in the history cache|<p>Consider increasing `HistoryCacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[wcache,history,pused],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: More than 75% used in the history index cache|<p>Consider increasing `HistoryIndexCacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[wcache,index,pused],10m)>75`|Average|**Manual close**: Yes|
|Zabbix server: More than 75% used in the trends cache|<p>Consider increasing `TrendCacheSize` in the `zabbix_server.conf` configuration file.</p>|`max(/Zabbix server health/zabbix[wcache,trend,pused],10m)>75`|Average|**Manual close**: Yes|

### LLD rule Zabbix proxy discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Zabbix proxy discovery|<p>LLD rule with item and trigger prototypes for the proxy discovery.</p>|Dependent item|zabbix.proxy.discovery|

### Item prototypes for Zabbix proxy discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxy [{#PROXY.NAME}]: Stats|<p>The statistics for the discovered proxy.</p>|Dependent item|zabbix.proxy.stats[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.name=="{#PROXY.NAME}")].first()`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: Mode|<p>The mode of Zabbix proxy.</p>|Dependent item|zabbix.proxy.mode[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.passive`</p></li><li><p>JavaScript: `return value === 'false' ? 0 : 1`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: Unencrypted|<p>The encryption status for connections from a proxy.</p>|Dependent item|zabbix.proxy.unencrypted[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.unencrypted`</p></li><li><p>JavaScript: `return value === 'false' ? 0 : 1`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: PSK|<p>The encryption status for connections from a proxy.</p>|Dependent item|zabbix.proxy.psk[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.psk`</p></li><li><p>JavaScript: `return value === 'false' ? 0 : 1`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: Certificate|<p>The encryption status for connections from a proxy.</p>|Dependent item|zabbix.proxy.cert[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cert`</p></li><li><p>JavaScript: `return value === 'false' ? 0 : 1`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: Compression|<p>The compression status of a proxy.</p>|Dependent item|zabbix.proxy.compression[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.compression`</p></li><li><p>JavaScript: `return value === 'false' ? 0 : 1`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: Item count|<p>The number of enabled items on enabled hosts assigned to a proxy.</p>|Dependent item|zabbix.proxy.items[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: Host count|<p>The number of enabled hosts assigned to a proxy.</p>|Dependent item|zabbix.proxy.hosts[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hosts`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: Version|<p>A version of Zabbix proxy.</p>|Dependent item|zabbix.proxy.version[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: Last seen, in seconds|<p>The time when a proxy was last seen by a server.</p>|Dependent item|zabbix.proxy.last_seen[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.last_seen`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: Compatibility|<p>Version of proxy compared to Zabbix server version.</p><p></p><p>Possible values:</p><p>0 - Undefined;</p><p>1 - Current version (proxy and server have the same major version);</p><p>2 - Outdated version (proxy version is older than server version, but is partially supported);</p><p>3 - Unsupported version (proxy version is older than server previous LTS release version or server major version is older than proxy major version).</p>|Dependent item|zabbix.proxy.compatibility[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.compatibility`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxy [{#PROXY.NAME}]: Required VPS|<p>The required performance of a proxy (the number of values that need to be collected per second).</p>|Dependent item|zabbix.proxy.requiredperformance[{#PROXY.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requiredperformance`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Zabbix proxy discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxy [{#PROXY.NAME}]: Proxy last seen|<p>Zabbix proxy is not updating the configuration data.</p>|`last(/Zabbix server health/zabbix.proxy.last_seen[{#PROXY.NAME}],#1)>{$PROXY.LAST_SEEN.MAX}`|Warning||
|Proxy [{#PROXY.NAME}]: Zabbix proxy never seen|<p>Zabbix proxy is not updating the configuration data.</p>|`last(/Zabbix server health/zabbix.proxy.last_seen[{#PROXY.NAME}],#1)=-1`|Warning||
|Proxy [{#PROXY.NAME}]: Zabbix proxy is outdated|<p>Zabbix proxy version is older than server version, but is partially supported. Only data collection and remote execution is available.</p>|`last(/Zabbix server health/zabbix.proxy.compatibility[{#PROXY.NAME}],#1)=2`|Warning||
|Proxy [{#PROXY.NAME}]: Zabbix proxy is not supported|<p>Zabbix proxy version is older than server previous LTS release version or server major version is older than proxy major version.</p>|`last(/Zabbix server health/zabbix.proxy.compatibility[{#PROXY.NAME}],#1)=3`|High||

### LLD rule High availability cluster node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|High availability cluster node discovery|<p>LLD rule with item and trigger prototypes for the node discovery.</p>|Dependent item|zabbix.nodes.discovery|

### Item prototypes for High availability cluster node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster node [{#NODE.NAME}]: Stats|<p>Provides the statistics of a node.</p>|Dependent item|zabbix.nodes.stats[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id=="{#NODE.ID}")].first()`</p></li></ul>|
|Cluster node [{#NODE.NAME}]: Address|<p>The IPv4 address of a node.</p>|Dependent item|zabbix.nodes.address[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.address`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Cluster node [{#NODE.NAME}]: Last access time|<p>Last access time.</p>|Dependent item|zabbix.nodes.lastaccess.time[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastaccess`</p></li></ul>|
|Cluster node [{#NODE.NAME}]: Last access age|<p>The time between the database's `unix_timestamp()` and the last access time.</p>|Dependent item|zabbix.nodes.lastaccess.age[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastaccess_age`</p></li></ul>|
|Cluster node [{#NODE.NAME}]: Status|<p>The status of a node.</p>|Dependent item|zabbix.nodes.status[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for High availability cluster node discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cluster node [{#NODE.NAME}]: Status changed|<p>The state of the node has changed. Acknowledge to close the problem manually.</p>|`last(/Zabbix server health/zabbix.nodes.status[{#NODE.ID}],#1)<>last(/Zabbix server health/zabbix.nodes.status[{#NODE.ID}],#2)`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

