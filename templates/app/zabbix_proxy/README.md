
# Zabbix proxy health

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ZABBIX.PROXY.UTIL.MAX}|<p>Maximum average percentage of time processes busy in the last minute (default is 75).</p>|`75`|
|{$ZABBIX.PROXY.UTIL.MIN}|<p>Minimum average percentage of time processes busy in the last minute (default is 65).</p>|`65`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Zabbix proxy: Queue over 10 minutes|<p>Number of monitored items in the queue which are delayed at least by 10 minutes.</p>|Zabbix internal|zabbix[queue,10m]|
|Zabbix proxy: Queue|<p>Number of monitored items in the queue which are delayed at least by 6 seconds.</p>|Zabbix internal|zabbix[queue]|
|Zabbix proxy: Utilization of data sender internal processes, in %|<p>Average percentage of time data sender processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,data sender,avg,busy]|
|Zabbix proxy: Utilization of availability manager internal processes, in %|<p>Average percentage of time availability manager processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,availability manager,avg,busy]|
|Zabbix proxy: Utilization of configuration syncer internal processes, in %|<p>Average percentage of time configuration syncer processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,configuration syncer,avg,busy]|
|Zabbix proxy: Utilization of discovery worker internal processes, in %|<p>Average percentage of time discovery worker processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,discovery worker,avg,busy]|
|Zabbix proxy: Utilization of discovery manager internal processes, in %|<p>Average percentage of time discovery manager processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,discovery manager,avg,busy]|
|Zabbix proxy: Utilization of ODBC poller data collector processes, in %|<p>Average percentage of time ODBC poller processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,odbc poller,avg,busy]|
|Zabbix proxy: Utilization of history poller data collector processes, in %|<p>Average percentage of time history poller processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,history poller,avg,busy]|
|Zabbix proxy: Utilization of history syncer internal processes, in %|<p>Average percentage of time history syncer processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,history syncer,avg,busy]|
|Zabbix proxy: Utilization of housekeeper internal processes, in %|<p>Average percentage of time housekeeper processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,housekeeper,avg,busy]|
|Zabbix proxy: Utilization of http poller data collector processes, in %|<p>Average percentage of time http poller processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,http poller,avg,busy]|
|Zabbix proxy: Utilization of icmp pinger data collector processes, in %|<p>Average percentage of time icmp pinger processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,icmp pinger,avg,busy]|
|Zabbix proxy: Utilization of ipmi manager internal processes, in %|<p>Average percentage of time ipmi manager processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,ipmi manager,avg,busy]|
|Zabbix proxy: Utilization of ipmi poller data collector processes, in %|<p>Average percentage of time ipmi poller processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,ipmi poller,avg,busy]|
|Zabbix proxy: Utilization of java poller data collector processes, in %|<p>Average percentage of time java poller processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,java poller,avg,busy]|
|Zabbix proxy: Utilization of poller data collector processes, in %|<p>Average percentage of time poller processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,poller,avg,busy]|
|Zabbix proxy: Utilization of preprocessing worker internal processes, in %|<p>Average percentage of time preprocessing worker processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,preprocessing worker,avg,busy]|
|Zabbix proxy: Utilization of preprocessing manager internal processes, in %|<p>Average percentage of time preprocessing manager processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,preprocessing manager,avg,busy]|
|Zabbix proxy: Utilization of self-monitoring internal processes, in %|<p>Average percentage of time self-monitoring processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,self-monitoring,avg,busy]|
|Zabbix proxy: Utilization of snmp trapper data collector processes, in %|<p>Average percentage of time snmp trapper processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,snmp trapper,avg,busy]|
|Zabbix proxy: Utilization of task manager internal processes, in %|<p>Average percentage of time task manager processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,task manager,avg,busy]|
|Zabbix proxy: Utilization of trapper data collector processes, in %|<p>Average percentage of time trapper processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,trapper,avg,busy]|
|Zabbix proxy: Utilization of unreachable poller data collector processes, in %|<p>Average percentage of time unreachable poller processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,unreachable poller,avg,busy]|
|Zabbix proxy: Utilization of vmware data collector processes, in %|<p>Average percentage of time vmware collector processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,vmware collector,avg,busy]|
|Zabbix proxy: Utilization of agent poller data collector processes, in %|<p>Average percentage of time agent poller processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,agent poller,avg,busy]|
|Zabbix proxy: Utilization of http agent poller data collector processes, in %|<p>Average percentage of time http agent poller processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,http agent poller,avg,busy]|
|Zabbix proxy: Utilization of snmp poller data collector processes, in %|<p>Average percentage of time snmp poller processes have been busy in the last minute.</p>|Zabbix internal|zabbix[process,snmp poller,avg,busy]|
|Zabbix proxy: Configuration cache, % used|<p>Availability statistics of Zabbix configuration cache. Percentage of used buffer.</p>|Zabbix internal|zabbix[rcache,buffer,pused]|
|Zabbix proxy: Version|<p>Version of Zabbix proxy.</p>|Zabbix internal|zabbix[version]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Zabbix proxy: VMware cache, % used|<p>Availability statistics of Zabbix vmware cache. Percentage of used buffer.</p>|Zabbix internal|zabbix[vmware,buffer,pused]|
|Zabbix proxy: History write cache, % used|<p>Statistics and availability of Zabbix write cache. Percentage of used history buffer.</p><p>History cache is used to store item values. A high number indicates performance problems on the database side.</p>|Zabbix internal|zabbix[wcache,history,pused]|
|Zabbix proxy: History index cache, % used|<p>Statistics and availability of Zabbix write cache. Percentage of used history index buffer.</p><p>History index cache is used to index values stored in history cache.</p>|Zabbix internal|zabbix[wcache,index,pused]|
|Zabbix proxy: Proxy memory buffer, % used|<p>Statistics and availability of Zabbix proxy memory buffer usage statistics. Percentage of used proxy memory buffer.</p><p>Proxy memory buffer is used to store the new historical data and upload from it without accessing database.</p>|Zabbix internal|zabbix[proxy_buffer,buffer,pused]|
|Zabbix proxy: Proxy buffer, state|<p>The current working state of proxy buffer where the new data is being stored. Possible values:</p><p></p><p>0 - disk (also returned when memory buffer is disabled);</p><p>1 - memory.</p>|Zabbix internal|zabbix[proxy_buffer,state,current]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Zabbix proxy: Proxy buffer, state changes|<p>The number of state changes between disk/memory buffer modes since proxy start.</p>|Zabbix internal|zabbix[proxy_buffer,state,changes]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Zabbix proxy: Number of processed values per second|<p>Statistics and availability of Zabbix write cache.</p><p>Total number of values processed by Zabbix server or Zabbix proxy, except unsupported items.</p>|Zabbix internal|zabbix[wcache,values]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix proxy: Number of processed numeric (float) values per second|<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed float values.</p>|Zabbix internal|zabbix[wcache,values,float]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix proxy: Number of processed log values per second|<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed log values.</p>|Zabbix internal|zabbix[wcache,values,log]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix proxy: Number of processed not supported values per second|<p>Statistics and availability of Zabbix write cache.</p><p>Number of times item processing resulted in item becoming unsupported or keeping that state.</p>|Zabbix internal|zabbix[wcache,values,not supported]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix proxy: Number of processed character values per second|<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed character/string values.</p>|Zabbix internal|zabbix[wcache,values,str]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix proxy: Number of processed text values per second|<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed text values.</p>|Zabbix internal|zabbix[wcache,values,text]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix proxy: Preprocessing queue|<p>Count of values enqueued in the preprocessing queue.</p>|Zabbix internal|zabbix[preprocessing_queue]|
|Zabbix proxy: Discovery queue|<p>Count of values enqueued in the discovery queue.</p>|Zabbix internal|zabbix[discovery_queue]|
|Zabbix proxy: Number of processed numeric (unsigned) values per second|<p>Statistics and availability of Zabbix write cache.</p><p>Number of processed numeric (unsigned) values.</p>|Zabbix internal|zabbix[wcache,values,uint]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Zabbix proxy: Values waiting to be sent|<p>Number of values in the proxy history table waiting to be sent to the server.</p>|Zabbix internal|zabbix[proxy_history]|
|Zabbix proxy: Required performance|<p>Required performance of Zabbix proxy, in new values per second expected.</p>|Zabbix internal|zabbix[requiredperformance]|
|Zabbix proxy: Uptime|<p>Uptime of Zabbix proxy process in seconds.</p>|Zabbix internal|zabbix[uptime]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Zabbix proxy: More than 100 items having missing data for more than 10 minutes|<p>zabbix[stats,{$IP},{$PORT},queue,10m] item is collecting data about how many items are missing data for more than 10 minutes.</p>|`min(/Zabbix proxy health/zabbix[queue,10m],10m)>100`|Warning|**Manual close**: Yes|
|Zabbix proxy: Utilization of data sender processes is high||`avg(/Zabbix proxy health/zabbix[process,data sender,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"data sender"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of availability manager processes is high||`avg(/Zabbix proxy health/zabbix[process,availability manager,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"availability manager"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of configuration syncer processes is high||`avg(/Zabbix proxy health/zabbix[process,configuration syncer,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"configuration syncer"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of discovery worker processes is high||`avg(/Zabbix proxy health/zabbix[process,discovery worker,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"discovery worker"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of discovery manager processes is high||`avg(/Zabbix proxy health/zabbix[process,discovery manager,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"discovery manager"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of ODBC poller processes is high||`avg(/Zabbix proxy health/zabbix[process,odbc poller,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"ODBC poller"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of history poller processes is high||`avg(/Zabbix proxy health/zabbix[process,history poller,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"history poller"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of history syncer processes is high||`avg(/Zabbix proxy health/zabbix[process,history syncer,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"history syncer"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of housekeeper processes is high||`avg(/Zabbix proxy health/zabbix[process,housekeeper,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"housekeeper"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of http poller processes is high||`avg(/Zabbix proxy health/zabbix[process,http poller,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"http poller"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of icmp pinger processes is high||`avg(/Zabbix proxy health/zabbix[process,icmp pinger,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"icmp pinger"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of ipmi manager processes is high||`avg(/Zabbix proxy health/zabbix[process,ipmi manager,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"ipmi manager"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of ipmi poller processes is high||`avg(/Zabbix proxy health/zabbix[process,ipmi poller,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"ipmi poller"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of java poller processes is high||`avg(/Zabbix proxy health/zabbix[process,java poller,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"java poller"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of poller processes is high||`avg(/Zabbix proxy health/zabbix[process,poller,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"poller"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of preprocessing worker processes is high||`avg(/Zabbix proxy health/zabbix[process,preprocessing worker,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"preprocessing worker"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of preprocessing manager processes is high||`avg(/Zabbix proxy health/zabbix[process,preprocessing manager,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"preprocessing manager"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of self-monitoring processes is high||`avg(/Zabbix proxy health/zabbix[process,self-monitoring,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"self-monitoring"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of snmp trapper processes is high||`avg(/Zabbix proxy health/zabbix[process,snmp trapper,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"snmp trapper"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of task manager processes is high||`avg(/Zabbix proxy health/zabbix[process,task manager,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"task manager"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of trapper processes is high||`avg(/Zabbix proxy health/zabbix[process,trapper,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"trapper"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of unreachable poller processes is high||`avg(/Zabbix proxy health/zabbix[process,unreachable poller,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"unreachable poller"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of vmware collector processes is high||`avg(/Zabbix proxy health/zabbix[process,vmware collector,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"vmware collector"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of agent poller processes is high||`avg(/Zabbix proxy health/zabbix[process,agent poller,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"agent poller"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of http agent poller processes is high||`avg(/Zabbix proxy health/zabbix[process,http agent poller,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"http agent poller"}`|Average|**Manual close**: Yes|
|Zabbix proxy: Utilization of snmp poller processes is high||`avg(/Zabbix proxy health/zabbix[process,snmp poller,avg,busy],10m)>{$ZABBIX.PROXY.UTIL.MAX:"snmp poller"}`|Average|**Manual close**: Yes|
|Zabbix proxy: More than {$ZABBIX.PROXY.UTIL.MAX}% used in the configuration cache|<p>Consider increasing CacheSize in the zabbix_proxy.conf configuration file.</p>|`max(/Zabbix proxy health/zabbix[rcache,buffer,pused],10m)>{$ZABBIX.PROXY.UTIL.MAX}`|Average|**Manual close**: Yes|
|Zabbix proxy: Version has changed|<p>Zabbix proxy version has changed. Acknowledge to close the problem manually.</p>|`last(/Zabbix proxy health/zabbix[version],#1)<>last(/Zabbix proxy health/zabbix[version],#2) and length(last(/Zabbix proxy health/zabbix[version]))>0`|Info|**Manual close**: Yes|
|Zabbix proxy: More than {$ZABBIX.PROXY.UTIL.MAX}% used in the vmware cache|<p>Consider increasing VMwareCacheSize in the zabbix_proxy.conf configuration file.</p>|`max(/Zabbix proxy health/zabbix[vmware,buffer,pused],10m)>{$ZABBIX.PROXY.UTIL.MAX}`|Average|**Manual close**: Yes|
|Zabbix proxy: More than {$ZABBIX.PROXY.UTIL.MAX}% used in the history cache|<p>Consider increasing HistoryCacheSize in the zabbix_proxy.conf configuration file.</p>|`max(/Zabbix proxy health/zabbix[wcache,history,pused],10m)>{$ZABBIX.PROXY.UTIL.MAX}`|Average|**Manual close**: Yes|
|Zabbix proxy: More than {$ZABBIX.PROXY.UTIL.MAX}% used in the history index cache|<p>Consider increasing HistoryIndexCacheSize in the zabbix_proxy.conf configuration file.</p>|`max(/Zabbix proxy health/zabbix[wcache,index,pused],10m)>{$ZABBIX.PROXY.UTIL.MAX}`|Average|**Manual close**: Yes|
|Zabbix proxy: More than {$ZABBIX.PROXY.UTIL.MAX}% used in the proxy memory buffer|<p>Consider increasing ProxyMemoryBufferSize in the zabbix_proxy.conf configuration file.</p>|`max(/Zabbix proxy health/zabbix[proxy_buffer,buffer,pused],10m)>{$ZABBIX.PROXY.UTIL.MAX}`|Average|**Manual close**: Yes|
|Zabbix proxy: {HOST.NAME} has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Zabbix proxy health/zabbix[uptime])<10m`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

