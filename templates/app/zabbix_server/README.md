
# Zabbix server health

## Overview

For Zabbix version: 6.2 and higher.
This template is designed to monitor internal Zabbix metrics on the local Zabbix server.

## Setup

Link this template to the local Zabbix server host.

## Configuration

No specific Zabbix configuration is required.


### Template links

There are no template links in this template.

### Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|High availability cluster node discovery |<p>LLD rule with item and trigger prototypes for the node discovery.</p> |DEPENDENT |zabbix.nodes.discovery |

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Cluster |Cluster node [{#NODE.NAME}]: Stats |<p>Provides the statistics of a node.</p> |DEPENDENT |zabbix.nodes.stats[{#NODE.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.id=="{#NODE.ID}")].first()`</p> |
|Cluster |Cluster node [{#NODE.NAME}]: Address |<p>The IPv4 address of a node.</p> |DEPENDENT |zabbix.nodes.address[{#NODE.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.address`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Cluster |Cluster node [{#NODE.NAME}]: Last access time |<p>Last access time.</p> |DEPENDENT |zabbix.nodes.lastaccess.time[{#NODE.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.lastaccess`</p> |
|Cluster |Cluster node [{#NODE.NAME}]: Last access age |<p>The time between the database's `unix_timestamp()` and the last access time.</p> |DEPENDENT |zabbix.nodes.lastaccess.age[{#NODE.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.lastaccess_age`</p> |
|Cluster |Cluster node [{#NODE.NAME}]: Status |<p>The status of a node.</p> |DEPENDENT |zabbix.nodes.status[{#NODE.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Zabbix raw items |Zabbix stats cluster |<p>The master item of Zabbix cluster statistics.</p> |INTERNAL |zabbix[cluster,discovery,nodes] |
|Zabbix server |Zabbix server: Queue over 10 minutes |<p>The number of monitored items in the queue, which are delayed at least by 10 minutes.</p> |INTERNAL |zabbix[queue,10m] |
|Zabbix server |Zabbix server: Queue |<p>The number of monitored items in the queue, which are delayed at least by 6 seconds.</p> |INTERNAL |zabbix[queue] |
|Zabbix server |Zabbix server: Utilization of alert manager internal processes, in % |<p>The average percentage of the time during which the alert manager processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,alert manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of alert syncer internal processes, in % |<p>The average percentage of the time during which the alert syncer processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,alert syncer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of alerter internal processes, in % |<p>The average percentage of the time during which the alerter processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,alerter,avg,busy] |
|Zabbix server |Zabbix server: Utilization of availability manager internal processes, in % |<p>The average percentage of the time during which the availability manager processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,availability manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of configuration syncer internal processes, in % |<p>The average percentage of the time during which the configuration syncer processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,configuration syncer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of discoverer data collector processes, in % |<p>The average percentage of the time during which the discoverer processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,discoverer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of escalator internal processes, in % |<p>The average percentage of the time during which the escalator processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,escalator,avg,busy] |
|Zabbix server |Zabbix server: Utilization of history poller data collector processes, in % |<p>The average percentage of the time during which the history poller processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,history poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of ODBC poller data collector processes, in % |<p>The average percentage of the time during which the ODBC poller processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,odbc poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of history syncer internal processes, in % |<p>The average percentage of the time during which the history syncer processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,history syncer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of housekeeper internal processes, in % |<p>The average percentage of the time during which the housekeeper processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,housekeeper,avg,busy] |
|Zabbix server |Zabbix server: Utilization of http poller data collector processes, in % |<p>The average percentage of the time during which the http poller processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,http poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of icmp pinger data collector processes, in % |<p>The average percentage of the time during which the icmp pinger processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,icmp pinger,avg,busy] |
|Zabbix server |Zabbix server: Utilization of ipmi manager internal processes, in % |<p>The average percentage of the time during which the ipmi manager processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,ipmi manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of ipmi poller data collector processes, in % |<p>The average percentage of the time during which the ipmi poller processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,ipmi poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of java poller data collector processes, in % |<p>The average percentage of the time during which the java poller processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,java poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of LLD manager internal processes, in % |<p>The average percentage of the time during which the lld manager processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,lld manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of LLD worker internal processes, in % |<p>The average percentage of the time during which the lld worker processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,lld worker,avg,busy] |
|Zabbix server |Zabbix server: Utilization of poller data collector processes, in % |<p>The average percentage of the time during which the poller processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of preprocessing worker internal processes, in % |<p>The average percentage of the time during which the preprocessing worker processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,preprocessing worker,avg,busy] |
|Zabbix server |Zabbix server: Utilization of preprocessing manager internal processes, in % |<p>The average percentage of the time during which the preprocessing manager processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,preprocessing manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of proxy poller data collector processes, in % |<p>The average percentage of the time during which the proxy poller processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,proxy poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of report manager internal processes, in % |<p>The average percentage of the time during which the report manager processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,report manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of report writer internal processes, in % |<p>The average percentage of the time during which the report writer processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,report writer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of self-monitoring internal processes, in % |<p>The average percentage of the time during which the self-monitoring processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,self-monitoring,avg,busy] |
|Zabbix server |Zabbix server: Utilization of snmp trapper data collector processes, in % |<p>The average percentage of the time during which the snmp trapper processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,snmp trapper,avg,busy] |
|Zabbix server |Zabbix server: Utilization of task manager internal processes, in % |<p>The average percentage of the time during which the task manager processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,task manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of timer internal processes, in % |<p>The average percentage of the time during which the timer processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,timer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of service manager internal processes, in % |<p>The average percentage of the time during which the service manager processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,service manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of trigger housekeeper internal processes, in % |<p>The average percentage of the time during which the trigger housekeeper processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,trigger housekeeper,avg,busy] |
|Zabbix server |Zabbix server: Utilization of trapper data collector processes, in % |<p>The average percentage of the time during which the trapper processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,trapper,avg,busy] |
|Zabbix server |Zabbix server: Utilization of unreachable poller data collector processes, in % |<p>The average percentage of the time during which the unreachable poller processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,unreachable poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of vmware data collector processes, in % |<p>The average percentage of the time during which the vmware collector processes have been busy for the last minute.</p> |INTERNAL |zabbix[process,vmware collector,avg,busy] |
|Zabbix server |Zabbix server: Configuration cache, % used |<p>The availability statistics of Zabbix configuration cache. The percentage of used data buffer.</p> |INTERNAL |zabbix[rcache,buffer,pused] |
|Zabbix server |Zabbix server: Trend function cache, % of unique requests |<p>The effectiveness statistics of Zabbix trend function cache. The percentage of cached items calculated from the sum of cached items plus requests.</p><p>Low percentage most likely means that the cache size can be reduced.</p> |INTERNAL |zabbix[tcache,cache,pitems] |
|Zabbix server |Zabbix server: Trend function cache, % of misses |<p>The effectiveness statistics of Zabbix trend function cache. The percentage of cache misses.</p> |INTERNAL |zabbix[tcache,cache,pmisses] |
|Zabbix server |Zabbix server: Value cache, % used |<p>The availability statistics of Zabbix value cache. The percentage of used data buffer.</p> |INTERNAL |zabbix[vcache,buffer,pused] |
|Zabbix server |Zabbix server: Value cache hits |<p>The effectiveness statistics of Zabbix value cache. The number of cache hits (history values taken from the cache).</p> |INTERNAL |zabbix[vcache,cache,hits]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix server |Zabbix server: Value cache misses |<p>The effectiveness statistics of Zabbix value cache. The number of cache misses (history values taken from the database).</p> |INTERNAL |zabbix[vcache,cache,misses]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix server |Zabbix server: Value cache operating mode |<p>The operating mode of the value cache.</p> |INTERNAL |zabbix[vcache,cache,mode] |
|Zabbix server |Zabbix server: Version |<p>A version of Zabbix server.</p> |INTERNAL |zabbix[version]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Zabbix server |Zabbix server: VMware cache, % used |<p>The availability statistics of Zabbix vmware cache. The percentage of used data buffer.</p> |INTERNAL |zabbix[vmware,buffer,pused] |
|Zabbix server |Zabbix server: History write cache, % used |<p>The statistics and availability of Zabbix write cache. The percentage of used history buffer.</p><p>The history cache is used to store item values. A high number indicates performance problems on the database side.</p> |INTERNAL |zabbix[wcache,history,pused] |
|Zabbix server |Zabbix server: History index cache, % used |<p>The statistics and availability of Zabbix write cache. The percentage of used history index buffer.</p><p>The history index cache is used to index values stored in the history cache.</p> |INTERNAL |zabbix[wcache,index,pused] |
|Zabbix server |Zabbix server: Trend write cache, % used |<p>The statistics and availability of Zabbix write cache. The percentage of used trend buffer.</p><p>The trend cache stores the aggregate of all items that have received data for the current hour.</p> |INTERNAL |zabbix[wcache,trend,pused] |
|Zabbix server |Zabbix server: Number of processed values per second |<p>The statistics and availability of Zabbix write cache.</p><p>The total number of values processed by Zabbix server or Zabbix proxy, except unsupported items.</p> |INTERNAL |zabbix[wcache,values]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix server |Zabbix server: Number of processed numeric (float) values per second |<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed float values.</p> |INTERNAL |zabbix[wcache,values,float]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix server |Zabbix server: Number of processed log values per second |<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed log values.</p> |INTERNAL |zabbix[wcache,values,log]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix server |Zabbix server: Number of processed not supported values per second |<p>The statistics and availability of Zabbix write cache.</p><p>The number of times the item processing resulted in an item becoming unsupported or keeping that state.</p> |INTERNAL |zabbix[wcache,values,not supported]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix server |Zabbix server: Number of processed character values per second |<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed character/string values.</p> |INTERNAL |zabbix[wcache,values,str]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix server |Zabbix server: Number of processed text values per second |<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed text values.</p> |INTERNAL |zabbix[wcache,values,text]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix server |Zabbix server: LLD queue |<p>The count of values enqueued in the low-level discovery processing queue.</p> |INTERNAL |zabbix[lld_queue] |
|Zabbix server |Zabbix server: Preprocessing queue |<p>The count of values enqueued in the preprocessing queue.</p> |INTERNAL |zabbix[preprocessing_queue] |
|Zabbix server |Zabbix server: Number of processed numeric (unsigned) values per second |<p>The statistics and availability of Zabbix write cache.</p><p>The number of processed numeric (unsigned) values.</p> |INTERNAL |zabbix[wcache,values,uint]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Cluster node [{#NODE.NAME}]: Status changed |<p>The state of the node has changed. Confirm to close.</p> |`last(/Zabbix server health/zabbix.nodes.status[{#NODE.ID}],#1)<>last(/Zabbix server health/zabbix.nodes.status[{#NODE.ID}],#2)` |INFO |<p>Manual close: YES</p> |
|Zabbix server: More than 100 items having missing data for more than 10 minutes |<p>The `zabbix[stats,{$IP},{$PORT},queue,10m]` item collects data about the number of items that have been missing the data for more than 10 minutes.</p> |`min(/Zabbix server health/zabbix[queue,10m],10m)>100` |WARNING | |
|Zabbix server: Utilization of alert manager processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,alert manager,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,alert manager,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of alert syncer processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,alert syncer,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,alert syncer,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of alerter processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,alerter,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,alerter,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of availability manager processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,availability manager,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,availability manager,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of configuration syncer processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,configuration syncer,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,configuration syncer,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of discoverer processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,discoverer,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,discoverer,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of escalator processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,escalator,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,escalator,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of history poller processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,history poller,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,history poller,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of ODBC poller processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,odbc poller,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,odbc poller,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of history syncer processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,history syncer,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,history syncer,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of housekeeper processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,housekeeper,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,housekeeper,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of http poller processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,http poller,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,http poller,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of icmp pinger processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,icmp pinger,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,icmp pinger,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of ipmi manager processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,ipmi manager,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,ipmi manager,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of ipmi poller processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,ipmi poller,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,ipmi poller,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of java poller processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,java poller,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,java poller,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of lld manager processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,lld manager,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,lld manager,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of lld worker processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,lld worker,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,lld worker,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of poller processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,poller,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,poller,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of preprocessing worker processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,preprocessing worker,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,preprocessing worker,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of preprocessing manager processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,preprocessing manager,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,preprocessing manager,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of proxy poller processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,proxy poller,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,proxy poller,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of report manager processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,report manager,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,report manager,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of report writer processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,report writer,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,report writer,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of self-monitoring processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,self-monitoring,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,self-monitoring,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of snmp trapper processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,snmp trapper,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,snmp trapper,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of task manager processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,task manager,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,task manager,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of timer processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,timer,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,timer,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of service manager processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,service manager,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,service manager,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of trigger housekeeper processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,trigger housekeeper,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,trigger housekeeper,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of trapper processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,trapper,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,trapper,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of unreachable poller processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,unreachable poller,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,unreachable poller,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: Utilization of vmware collector processes is high |<p>-</p> |`avg(/Zabbix server health/zabbix[process,vmware collector,avg,busy],10m)>75`<p>Recovery expression:</p>`avg(/Zabbix server health/zabbix[process,vmware collector,avg,busy],10m)<65` |AVERAGE | |
|Zabbix server: More than 75% used in the configuration cache |<p>Consider increasing `CacheSize` in the `zabbix_server.conf` configuration file.</p> |`max(/Zabbix server health/zabbix[rcache,buffer,pused],10m)>75` |AVERAGE | |
|Zabbix server: More than 95% used in the value cache |<p>Consider increasing `ValueCacheSize` in the `zabbix_server.conf` configuration file.</p> |`max(/Zabbix server health/zabbix[vcache,buffer,pused],10m)>95` |AVERAGE | |
|Zabbix server: Zabbix value cache working in low memory mode |<p>Once the low memory mode has been switched on, the value cache will remain in this state for 24 hours, even if the problem that triggered this mode is resolved sooner.</p> |`last(/Zabbix server health/zabbix[vcache,cache,mode])=1` |HIGH | |
|Zabbix server: Version has changed |<p>Zabbix server version has changed. Acknowledge to close manually.</p> |`last(/Zabbix server health/zabbix[version],#1)<>last(/Zabbix server health/zabbix[version],#2) and length(last(/Zabbix server health/zabbix[version]))>0` |INFO |<p>Manual close: YES</p> |
|Zabbix server: More than 75% used in the vmware cache |<p>Consider increasing `VMwareCacheSize` in the `zabbix_server.conf` configuration file.</p> |`max(/Zabbix server health/zabbix[vmware,buffer,pused],10m)>75` |AVERAGE | |
|Zabbix server: More than 75% used in the history cache |<p>Consider increasing `HistoryCacheSize` in the `zabbix_server.conf` configuration file.</p> |`max(/Zabbix server health/zabbix[wcache,history,pused],10m)>75` |AVERAGE | |
|Zabbix server: More than 75% used in the history index cache |<p>Consider increasing `HistoryIndexCacheSize` in the `zabbix_server.conf` configuration file.</p> |`max(/Zabbix server health/zabbix[wcache,index,pused],10m)>75` |AVERAGE | |
|Zabbix server: More than 75% used in the trends cache |<p>Consider increasing `TrendCacheSize` in the `zabbix_server.conf` configuration file.</p> |`max(/Zabbix server health/zabbix[wcache,trend,pused],10m)>75` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

