
# Remote Zabbix proxy

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ZABBIX.PROXY.ADDRESS} |<p>IP/DNS/network mask list of proxies to be remotely queried (default is 127.0.0.1</p> |`127.0.0.1` |
|{$ZABBIX.PROXY.PORT} |<p>Port of proxy to be remotely queried (default is 10051)</p> |`10051` |
|{$ZABBIX.PROXY.UTIL.MAX} |<p>Maximum average percentage of time processes busy in the last minute (default is 75)</p> |`75` |
|{$ZABBIX.PROXY.UTIL.MIN} |<p>Minimum average percentage of time processes busy in the last minute (default is 65)</p> |`65` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Zabbix_raw_items |Remote Zabbix proxy: Zabbix stats |<p>Zabbix server statistics master item.</p> |INTERNAL |zabbix[stats,{$ZABBIX.PROXY.ADDRESS},{$ZABBIX.PROXY.PORT}] |
|Zabbix proxy |Remote Zabbix proxy: Zabbix stats queue over 10m |<p>Number of monitored items in the queue which are delayed at least by 10 minutes</p> |INTERNAL |zabbix[stats,{$ZABBIX.PROXY.ADDRESS},{$ZABBIX.PROXY.PORT},queue,10m]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queue`</p> |
|Zabbix proxy |Remote Zabbix proxy: Zabbix stats queue |<p>Number of monitored items in the queue which are delayed at least by 6 seconds</p> |INTERNAL |zabbix[stats,{$ZABBIX.PROXY.ADDRESS},{$ZABBIX.PROXY.PORT},queue]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queue`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of data sender internal processes, in % |<p>Average percentage of time data sender processes have been busy in the last minute</p> |DEPENDENT |process.data_sender.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['data sender'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes data sender not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of availability manager internal processes, in % |<p>Average percentage of time availability manager processes have been busy in the last minute</p> |DEPENDENT |process.availability_manager.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['availability manager'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes availability manager not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of configuration syncer internal processes, in % |<p>Average percentage of time configuration syncer processes have been busy in the last minute</p> |DEPENDENT |process.configuration_syncer.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['configuration syncer'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes configuration syncer not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of discoverer data collector processes, in % |<p>Average percentage of time discoverer processes have been busy in the last minute</p> |DEPENDENT |process.discoverer.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['discoverer'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes discoverer not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of heartbeat sender internal processes, in % |<p>Average percentage of time heartbeat sender processes have been busy in the last minute</p> |DEPENDENT |process.heartbeat_sender.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['heartbeat sender'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes heartbeat sender not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of history poller data collector processes, in % |<p>Average percentage of time history poller processes have been busy in the last minute</p> |DEPENDENT |process.history_poller.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['history poller'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes history poller not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of history syncer internal processes, in % |<p>Average percentage of time history syncer processes have been busy in the last minute</p> |DEPENDENT |process.history_syncer.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['history syncer'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes history syncer not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of housekeeper internal processes, in % |<p>Average percentage of time housekeeper processes have been busy in the last minute</p> |DEPENDENT |process.housekeeper.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['housekeeper'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes housekeeper not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of http poller data collector processes, in % |<p>Average percentage of time http poller processes have been busy in the last minute</p> |DEPENDENT |process.http_poller.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['http poller'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes http poller not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of icmp pinger data collector processes, in % |<p>Average percentage of time icmp pinger processes have been busy in the last minute</p> |DEPENDENT |process.icmp_pinger.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['icmp pinger'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes icmp pinger not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of ipmi manager internal processes, in % |<p>Average percentage of time ipmi manager processes have been busy in the last minute</p> |DEPENDENT |process.ipmi_manager.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['ipmi manager'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes ipmi manager not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of ipmi poller data collector processes, in % |<p>Average percentage of time ipmi poller processes have been busy in the last minute</p> |DEPENDENT |process.ipmi_poller.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['ipmi poller'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes ipmi poller not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of java poller data collector processes, in % |<p>Average percentage of time java poller processes have been busy in the last minute</p> |DEPENDENT |process.java_poller.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['java poller'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes java poller not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of poller data collector processes, in % |<p>Average percentage of time poller processes have been busy in the last minute</p> |DEPENDENT |process.poller.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['poller'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes poller not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of preprocessing worker internal processes, in % |<p>Average percentage of time preprocessing worker processes have been busy in the last minute</p> |DEPENDENT |process.preprocessing_worker.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['preprocessing worker'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes preprocessing worker not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of preprocessing manager internal processes, in % |<p>Average percentage of time preprocessing manager processes have been busy in the last minute</p> |DEPENDENT |process.preprocessing_manager.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['preprocessing manager'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes preprocessing manager not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of self-monitoring internal processes, in % |<p>Average percentage of time self-monitoring processes have been busy in the last minute</p> |DEPENDENT |process.self-monitoring.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['self-monitoring'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes self-monitoring not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of snmp trapper data collector processes, in % |<p>Average percentage of time snmp trapper processes have been busy in the last minute</p> |DEPENDENT |process.snmp_trapper.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['snmp trapper'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes snmp trapper not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of task manager internal processes, in % |<p>Average percentage of time task manager processes have been busy in the last minute</p> |DEPENDENT |process.task_manager.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['task manager'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes task manager not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of trapper data collector processes, in % |<p>Average percentage of time trapper processes have been busy in the last minute</p> |DEPENDENT |process.trapper.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['trapper'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes trapper not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of unreachable poller data collector processes, in % |<p>Average percentage of time unreachable poller processes have been busy in the last minute</p> |DEPENDENT |process.unreachable_poller.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['unreachable poller'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes unreachable poller not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Utilization of vmware data collector processes, in % |<p>Average percentage of time vmware collector processes have been busy in the last minute</p> |DEPENDENT |process.vmware_collector.avg.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.process['vmware collector'].busy.avg`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> Processes vmware collector not started`</p> |
|Zabbix proxy |Remote Zabbix proxy: Configuration cache, % used |<p>Availability statistics of Zabbix configuration cache. Percentage of used buffer</p> |DEPENDENT |rcache.buffer.pused<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.rcache.pused`</p> |
|Zabbix proxy |Remote Zabbix proxy: Version |<p>Version of Zabbix proxy.</p> |DEPENDENT |version<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.version`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Zabbix proxy |Remote Zabbix proxy: VMware cache, % used |<p>Availability statistics of Zabbix vmware cache. Percentage of used buffer</p> |DEPENDENT |vmware.buffer.pused<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.vmware.pused`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> No vmware collector processes started`</p> |
|Zabbix proxy |Remote Zabbix proxy: History write cache, % used |<p>Statistics and availability of Zabbix write cache. Percentage of used history buffer.</p><p>History cache is used to store item values. A high number indicates performance problems on the database side.</p> |DEPENDENT |wcache.history.pused<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.wcache.history.pused`</p> |
|Zabbix proxy |Remote Zabbix proxy: History index cache, % used |<p>Statistics and availability of Zabbix write cache. Percentage of used history index buffer.</p><p>History index cache is used to index values stored in history cache.</p> |DEPENDENT |wcache.index.pused<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.wcache.index.pused`</p> |
|Zabbix proxy |Remote Zabbix proxy: Number of processed values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Total number of values processed by Zabbix server or Zabbix proxy, except unsupported items.</p> |DEPENDENT |wcache.values<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.wcache.values.all`</p><p>- CHANGE_PER_SECOND |
|Zabbix proxy |Remote Zabbix proxy: Number of processed numeric (float) values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed float values.</p> |DEPENDENT |wcache.values.float<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.wcache.values.float`</p><p>- CHANGE_PER_SECOND |
|Zabbix proxy |Remote Zabbix proxy: Number of processed log values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed log values.</p> |DEPENDENT |wcache.values.log<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.wcache.values.log`</p><p>- CHANGE_PER_SECOND |
|Zabbix proxy |Remote Zabbix proxy: Number of processed not supported values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of times item processing resulted in item becoming unsupported or keeping that state.</p> |DEPENDENT |wcache.values.not_supported<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.wcache.values['not supported']`</p><p>- CHANGE_PER_SECOND |
|Zabbix proxy |Remote Zabbix proxy: Number of processed character values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed character/string values.</p> |DEPENDENT |wcache.values.str<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.wcache.values.str`</p><p>- CHANGE_PER_SECOND |
|Zabbix proxy |Remote Zabbix proxy: Number of processed text values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed text values.</p> |DEPENDENT |wcache.values.text<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.wcache.values.text`</p><p>- CHANGE_PER_SECOND |
|Zabbix proxy |Remote Zabbix proxy: Preprocessing queue |<p>Count of values enqueued in the preprocessing queue.</p> |DEPENDENT |preprocessing_queue<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.preprocessing_queue`</p> |
|Zabbix proxy |Remote Zabbix proxy: Number of processed numeric (unsigned) values per second |<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed numeric (unsigned) values.</p> |DEPENDENT |wcache.values.uint<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.wcache.values.uint`</p><p>- CHANGE_PER_SECOND |
|Zabbix proxy |Remote Zabbix proxy: Required performance |<p>Required performance of Zabbix proxy, in new values per second expected</p> |DEPENDENT |requiredperformance<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.requiredperformance`</p> |
|Zabbix proxy |Remote Zabbix proxy: Uptime |<p>Uptime of Zabbix proxy process in seconds.</p> |DEPENDENT |uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.uptime`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Remote Zabbix proxy: More than 100 items having missing data for more than 10 minutes |<p>zabbix[stats,{$ZABBIX.PROXY.ADDRESS},{$ZABBIX.PROXY.PORT},queue,10m] item is collecting data about how many items are missing data for more than 10 minutes</p> |`-` |WARNING | |
|Remote Zabbix proxy: Utilization of data sender processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of availability manager processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of configuration syncer processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of discoverer processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of heartbeat sender processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of history poller processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of history syncer processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of housekeeper processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of http poller processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of icmp pinger processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of ipmi manager processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of ipmi poller processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of java poller processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of poller processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of preprocessing worker processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of preprocessing manager processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of self-monitoring processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of snmp trapper processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of task manager processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of trapper processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of unreachable poller processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Utilization of vmware collector processes over {$ZABBIX.PROXY.UTIL.MAX}% |<p>-</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: More than {$ZABBIX.PROXY.UTIL.MAX}% used in the configuration cache |<p>Consider increasing CacheSize in the zabbix_server.conf configuration file</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: Version has changed (new version: {ITEM.VALUE}) |<p>Remote Zabbix proxy version has changed. Ack to close.</p> |`last(/Remote Zabbix proxy/version,#1)<>last(/Remote Zabbix proxy/version,#2) and length(last(/Remote Zabbix proxy/version))>0` |INFO |<p>Manual close: YES</p> |
|Remote Zabbix proxy: More than {$ZABBIX.PROXY.UTIL.MAX}% used in the vmware cache |<p>Consider increasing VMwareCacheSize in the zabbix_server.conf configuration file</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: More than {$ZABBIX.PROXY.UTIL.MAX}% used in the history cache |<p>Consider increasing HistoryCacheSize in the zabbix_server.conf configuration file</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: More than {$ZABBIX.PROXY.UTIL.MAX}% used in the history index cache |<p>Consider increasing HistoryIndexCacheSize in the zabbix_server.conf configuration file</p> |`-` |AVERAGE | |
|Remote Zabbix proxy: has been restarted (uptime < 10m) |<p>Uptime is less than 10 minutes</p> |`-` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

