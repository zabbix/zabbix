
# Zabbix Server

## Overview

For Zabbix version: 5.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.


## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Zabbix server |Zabbix server: Queue over 10 minutes |<p>Number of monitored items in the queue which are delayed at least by 10 minutes</p> |INTERNAL |zabbix[queue,10m] |
|Zabbix server |Zabbix server: Queue |<p>Number of monitored items in the queue which are delayed at least by 6 seconds</p> |INTERNAL |zabbix[queue] |
|Zabbix server |Zabbix server: Utilization of alert manager internal processes, in % |<p>Average time of alert manager processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,alert manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of alert syncer internal processes, in % |<p>Average time of alert syncer processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,alert syncer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of alerter internal processes, in % |<p>Average time of alerter processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,alerter,avg,busy] |
|Zabbix server |Zabbix server: Utilization of availability manager internal processes, in % |<p>Average time of availability manager processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,availability manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of configuration syncer internal processes, in % |<p>Average time of configuration syncer processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,configuration syncer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of discoverer data collector processes, in % |<p>Average time of discoverer processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,discoverer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of escalator internal processes, in % |<p>Average time of escalator processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,escalator,avg,busy] |
|Zabbix server |Zabbix server: Utilization of history poller data collector processes, in % |<p>Average time of history poller processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,history poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of history syncer internal processes, in % |<p>Average time of history syncer processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,history syncer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of housekeeper internal processes, in % |<p>Average time of housekeeper processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,housekeeper,avg,busy] |
|Zabbix server |Zabbix server: Utilization of http poller data collector processes, in % |<p>Average time of http poller processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,http poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of icmp pinger data collector processes, in % |<p>Average time of icmp pinger processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,icmp pinger,avg,busy] |
|Zabbix server |Zabbix server: Utilization of ipmi manager internal processes, in % |<p>Average time of ipmi manager processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,ipmi manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of ipmi poller data collector processes, in % |<p>Average time of ipmi poller processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,ipmi poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of java poller data collector processes, in % |<p>Average time of java poller processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,java poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of LLD manager internal processes, in % |<p>Average time of lld manager processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,lld manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of LLD worker internal processes, in % |<p>Average time of lld worker processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,lld worker,avg,busy] |
|Zabbix server |Zabbix server: Utilization of poller data collector processes, in % |<p>Average time of poller processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of preprocessing worker internal processes, in % |<p>Average time of preprocessing worker processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,preprocessing worker,avg,busy] |
|Zabbix server |Zabbix server: Utilization of preprocessing manager internal processes, in % |<p>Average time of preprocessing manager processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,preprocessing manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of proxy poller data collector processes, in % |<p>Average time of proxy poller processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,proxy poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of report manager internal processes, in % |<p>Average time of report manager processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,report manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of report writer internal processes, in % |<p>Average time of report writer processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,report writer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of self-monitoring internal processes, in % |<p>Average time of self-monitoring processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,self-monitoring,avg,busy] |
|Zabbix server |Zabbix server: Utilization of snmp trapper data collector processes, in % |<p>Average time of snmp trapper processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,snmp trapper,avg,busy] |
|Zabbix server |Zabbix server: Utilization of task manager internal processes, in % |<p>Average time of task manager processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,task manager,avg,busy] |
|Zabbix server |Zabbix server: Utilization of timer internal processes, in % |<p>Average time of timer processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,timer,avg,busy] |
|Zabbix server |Zabbix server: Utilization of trapper data collector processes, in % |<p>Average time of trapper processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,trapper,avg,busy] |
|Zabbix server |Zabbix server: Utilization of unreachable poller data collector processes, in % |<p>Average time of unreachable poller processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,unreachable poller,avg,busy] |
|Zabbix server |Zabbix server: Utilization of vmware data collector processes, in % |<p>Average time of vmware collector processes spent doing something during the last minute</p> |INTERNAL |zabbix[process,vmware collector,avg,busy] |
|Zabbix server |Zabbix server: Configuration cache, % used |<p>Availability statistics of Zabbix configuration cache. Percentage of used buffer</p> |INTERNAL |zabbix[rcache,buffer,pused] |
|Zabbix server |Zabbix server: Trend function cache, % unique requests |<p>Effectiveness statistics of the Zabbix trend function cache. Percentage of cached items from cached items + requests. Low percentage most likely means that the cache size can be reduced.</p> |INTERNAL |zabbix[tcache,cache,pitems] |
|Zabbix server |Zabbix server: Trend function cache, % misses |<p>Effectiveness statistics of the Zabbix trend function cache.	Percentage of cache misses</p> |INTERNAL |zabbix[tcache,cache,pmisses] |
|Zabbix server |Zabbix server: Value cache, % used |<p>Availability statistics of Zabbix value cache.	Percentage of used buffer</p> |INTERNAL |zabbix[vcache,buffer,pused] |
|Zabbix server |Zabbix server: Value cache hits |<p>Effectiveness statistics of Zabbix value cache. Number of cache hits (history values taken from the cache)</p> |INTERNAL |zabbix[vcache,cache,hits]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Zabbix server |Zabbix server: Value cache misses |<p>Effectiveness statistics of Zabbix value cache. Number of cache misses (history values taken from the database)</p> |INTERNAL |zabbix[vcache,cache,misses]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Zabbix server |Zabbix server: Value cache operating mode |<p>Value cache operating mode</p> |INTERNAL |zabbix[vcache,cache,mode] |
|Zabbix server |Zabbix server: Version |<p>Version of Zabbix server.</p> |INTERNAL |zabbix[version]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Zabbix server |Zabbix server: VMware cache, % used |<p>Availability statistics of Zabbix vmware cache. Percentage of used buffer</p> |INTERNAL |zabbix[vmware,buffer,pused] |
|Zabbix server |Zabbix server: History write cache, % used |<p>Statistics and availability of Zabbix write cache. Percentage of used history buffer.</p><p>History cache is used to store item values. A high number indicates performance problems on the database side.</p> |INTERNAL |zabbix[wcache,history,pused] |
|Zabbix server |Zabbix server: History index cache, % used |<p>Statistics and availability of Zabbix write cache. Percentage of used history index buffer.</p><p>History index cache is used to index values stored in history cache.</p> |INTERNAL |zabbix[wcache,index,pused] |
|Zabbix server |Zabbix server: Trend write cache, % used |<p>Statistics and availability of Zabbix write cache. Percentage of used trend buffer.</p><p>Trend cache stores aggregate for the current hour for all items that receive data.</p> |INTERNAL |zabbix[wcache,trend,pused] |
|Zabbix server |Zabbix server: Number of processed values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Total number of values processed by Zabbix server or Zabbix proxy, except unsupported items.</p> |INTERNAL |zabbix[wcache,values]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Zabbix server |Zabbix server: Number of processed numeric (float) values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed float values.</p> |INTERNAL |zabbix[wcache,values,float]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Zabbix server |Zabbix server: Number of processed log values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed log values.</p> |INTERNAL |zabbix[wcache,values,log]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Zabbix server |Zabbix server: Number of processed not supported values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of times item processing resulted in item becoming unsupported or keeping that state.</p> |INTERNAL |zabbix[wcache,values,not supported]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Zabbix server |Zabbix server: Number of processed character values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed character/string values.</p> |INTERNAL |zabbix[wcache,values,str]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Zabbix server |Zabbix server: Number of processed text values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed text values.</p> |INTERNAL |zabbix[wcache,values,text]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Zabbix server |Zabbix server: LLD queue |<p>Count of values enqueued in the low-level discovery processing queue.</p> |INTERNAL |zabbix[lld_queue] |
|Zabbix server |Zabbix server: Preprocessing queue |<p>Count of values enqueued in the preprocessing queue.</p> |INTERNAL |zabbix[preprocessing_queue] |
|Zabbix server |Zabbix server: Number of processed numeric (unsigned) values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed numeric (unsigned) values.</p> |INTERNAL |zabbix[wcache,values,uint]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Zabbix server: More than 100 items having missing data for more than 10 minutes |<p>zabbix[stats,{$IP},{$PORT},queue,10m] item is collecting data about how many items are missing data for more than 10 minutes</p> |`{TEMPLATE_NAME:zabbix[queue,10m].min(10m)}>100` |WARNING | |
|Zabbix server: alert manager processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,alert manager,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,alert manager,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: alert syncer processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,alert syncer,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,alert syncer,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: alerter processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,alerter,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,alerter,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: availability manager processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,availability manager,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,availability manager,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: configuration syncer processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,configuration syncer,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,configuration syncer,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: discoverer processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,discoverer,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,discoverer,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: escalator processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,escalator,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,escalator,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: history poller processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,history poller,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,history poller,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: history syncer processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,history syncer,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,history syncer,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: housekeeper processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,housekeeper,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,housekeeper,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: http poller processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,http poller,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,http poller,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: icmp pinger processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,icmp pinger,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,icmp pinger,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: ipmi manager processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,ipmi manager,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,ipmi manager,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: ipmi poller processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,ipmi poller,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,ipmi poller,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: java poller processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,java poller,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,java poller,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: lld manager processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,lld manager,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,lld manager,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: lld worker processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,lld worker,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,lld worker,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: poller processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,poller,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,poller,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: preprocessing worker processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,preprocessing worker,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,preprocessing worker,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: preprocessing manager processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,preprocessing manager,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,preprocessing manager,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: proxy poller processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,proxy poller,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,proxy poller,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: report manager processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,report manager,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,report manager,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: report writer processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,report writer,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,report writer,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: self-monitoring processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,self-monitoring,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,self-monitoring,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: snmp trapper processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,snmp trapper,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,snmp trapper,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: task manager processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,task manager,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,task manager,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: timer processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,timer,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,timer,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: trapper processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,trapper,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,trapper,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: unreachable poller processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,unreachable poller,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,unreachable poller,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: vmware collector processes more than 75% busy |<p>-</p> |`{TEMPLATE_NAME:zabbix[process,vmware collector,avg,busy].avg(10m)}>75`<p>Recovery expression:</p>`{TEMPLATE_NAME:zabbix[process,vmware collector,avg,busy].avg(10m)}<65` |AVERAGE | |
|Zabbix server: More than 75% used in the configuration cache |<p>Consider increasing CacheSize in the zabbix_server.conf configuration file</p> |`{TEMPLATE_NAME:zabbix[rcache,buffer,pused].max(10m)}>75` |AVERAGE | |
|Zabbix server: More than 95% used in the value cache |<p>Consider increasing ValueCacheSize in the zabbix_server.conf configuration file</p> |`{TEMPLATE_NAME:zabbix[vcache,buffer,pused].max(10m)}>95` |AVERAGE | |
|Zabbix server: Zabbix value cache working in low memory mode |<p>Once the low memory mode has been switched on, the value cache will remain in this state for 24 hours, even if the problem that triggered this mode is resolved sooner.</p> |`{TEMPLATE_NAME:zabbix[vcache,cache,mode].last()}=1` |HIGH | |
|Zabbix server: Version has changed (new version: {ITEM.VALUE}) |<p>Zabbix server version has changed. Ack to close.</p> |`{TEMPLATE_NAME:zabbix[version].diff()}=1 and {TEMPLATE_NAME:zabbix[version].strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Zabbix server: More than 75% used in the vmware cache |<p>Consider increasing VMwareCacheSize in the zabbix_server.conf configuration file</p> |`{TEMPLATE_NAME:zabbix[vmware,buffer,pused].max(10m)}>75` |AVERAGE | |
|Zabbix server: More than 75% used in the history cache |<p>Consider increasing HistoryCacheSize in the zabbix_server.conf configuration file</p> |`{TEMPLATE_NAME:zabbix[wcache,history,pused].max(10m)}>75` |AVERAGE | |
|Zabbix server: More than 75% used in the history index cache |<p>Consider increasing HistoryIndexCacheSize in the zabbix_server.conf configuration file</p> |`{TEMPLATE_NAME:zabbix[wcache,index,pused].max(10m)}>75` |AVERAGE | |
|Zabbix server: More than 75% used in the trends cache |<p>Consider increasing TrendCacheSize in the zabbix_server.conf configuration file</p> |`{TEMPLATE_NAME:zabbix[wcache,trend,pused].max(10m)}>75` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

