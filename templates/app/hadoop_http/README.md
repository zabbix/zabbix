
# Hadoop by HTTP

## Overview

The template for monitoring Hadoop over HTTP that works without any external scripts.
It collects metrics by polling the Hadoop API remotely using an HTTP agent and JSONPath preprocessing.
Zabbix server (or proxy) execute direct requests to ResourceManager, NodeManagers, NameNode, DataNodes APIs.
All metrics are collected at once, thanks to the Zabbix bulk data collection.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Hadoop 3.1 and later

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

You should define the IP address (or FQDN) and Web-UI port for the ResourceManager in {$HADOOP.RESOURCEMANAGER.HOST} and {$HADOOP.RESOURCEMANAGER.PORT} macros and for the NameNode in {$HADOOP.NAMENODE.HOST} and {$HADOOP.NAMENODE.PORT} macros respectively. Macros can be set in the template or overridden at the host level.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HADOOP.RESOURCEMANAGER.HOST}|<p>The Hadoop ResourceManager host IP address or FQDN.</p>|`ResourceManager`|
|{$HADOOP.RESOURCEMANAGER.PORT}|<p>The Hadoop ResourceManager Web-UI port.</p>|`8088`|
|{$HADOOP.RESOURCEMANAGER.RESPONSE_TIME.MAX.WARN}|<p>The Hadoop ResourceManager API page maximum response time in seconds for trigger expression.</p>|`10s`|
|{$HADOOP.NAMENODE.HOST}|<p>The Hadoop NameNode host IP address or FQDN.</p>|`NameNode`|
|{$HADOOP.NAMENODE.PORT}|<p>The Hadoop NameNode Web-UI port.</p>|`9870`|
|{$HADOOP.NAMENODE.RESPONSE_TIME.MAX.WARN}|<p>The Hadoop NameNode API page maximum response time in seconds for trigger expression.</p>|`10s`|
|{$HADOOP.CAPACITY_REMAINING.MIN.WARN}|<p>The Hadoop cluster capacity remaining percent for trigger expression.</p>|`20`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ResourceManager: Service status|<p>Hadoop ResourceManager API port availability.</p>|Simple check|net.tcp.service["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|ResourceManager: Service response time|<p>Hadoop ResourceManager API performance.</p>|Simple check|net.tcp.service.perf["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"]|
|Hadoop: Get ResourceManager stats||HTTP agent|hadoop.resourcemanager.get|
|ResourceManager: Uptime||Dependent item|hadoop.resourcemanager.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|ResourceManager: Get info||Dependent item|hadoop.resourcemanager.info<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.beans[?(@.name=~'Hadoop:service=ResourceManager,name=*')]`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|
|ResourceManager: RPC queue & processing time|<p>Average time spent on processing RPC requests.</p>|Dependent item|hadoop.resourcemanager.rpc_processing_time_avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|ResourceManager: Active NMs|<p>Number of Active NodeManagers.</p>|Dependent item|hadoop.resourcemanager.num_active_nm<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ResourceManager: Decommissioning NMs|<p>Number of Decommissioning NodeManagers.</p>|Dependent item|hadoop.resourcemanager.num_decommissioning_nm<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ResourceManager: Decommissioned NMs|<p>Number of Decommissioned NodeManagers.</p>|Dependent item|hadoop.resourcemanager.num_decommissioned_nm<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|ResourceManager: Lost NMs|<p>Number of Lost NodeManagers.</p>|Dependent item|hadoop.resourcemanager.num_lost_nm<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|ResourceManager: Unhealthy NMs|<p>Number of Unhealthy NodeManagers.</p>|Dependent item|hadoop.resourcemanager.num_unhealthy_nm<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|ResourceManager: Rebooted NMs|<p>Number of Rebooted NodeManagers.</p>|Dependent item|hadoop.resourcemanager.num_rebooted_nm<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|ResourceManager: Shutdown NMs|<p>Number of Shutdown NodeManagers.</p>|Dependent item|hadoop.resourcemanager.num_shutdown_nm<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Service status|<p>Hadoop NameNode API port availability.</p>|Simple check|net.tcp.service["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|NameNode: Service response time|<p>Hadoop NameNode API performance.</p>|Simple check|net.tcp.service.perf["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"]|
|Hadoop: Get NameNode stats||HTTP agent|hadoop.namenode.get|
|NameNode: Uptime||Dependent item|hadoop.namenode.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|NameNode: Get info||Dependent item|hadoop.namenode.info<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.beans[?(@.name=~'Hadoop:service=NameNode,name=*')]`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|
|NameNode: RPC queue & processing time|<p>Average time spent on processing RPC requests.</p>|Dependent item|hadoop.namenode.rpc_processing_time_avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Block Pool Renaming||Dependent item|hadoop.namenode.percent_block_pool_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Transactions since last checkpoint|<p>Total number of transactions since last checkpoint.</p>|Dependent item|hadoop.namenode.transactions_since_last_checkpoint<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Percent capacity remaining|<p>Available capacity in percent.</p>|Dependent item|hadoop.namenode.percent_remaining<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|NameNode: Capacity remaining|<p>Available capacity.</p>|Dependent item|hadoop.namenode.capacity_remaining<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Corrupt blocks|<p>Number of corrupt blocks.</p>|Dependent item|hadoop.namenode.corrupt_blocks<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Missing blocks|<p>Number of missing blocks.</p>|Dependent item|hadoop.namenode.missing_blocks<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Failed volumes|<p>Number of failed volumes.</p>|Dependent item|hadoop.namenode.volume_failures_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Alive DataNodes|<p>Count of alive DataNodes.</p>|Dependent item|hadoop.namenode.num_live_data_nodes<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|NameNode: Dead DataNodes|<p>Count of dead DataNodes.</p>|Dependent item|hadoop.namenode.num_dead_data_nodes<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|NameNode: Stale DataNodes|<p>DataNodes that do not send a heartbeat within 30 seconds are marked as "stale".</p>|Dependent item|hadoop.namenode.num_stale_data_nodes<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|NameNode: Total files|<p>Total count of files tracked by the NameNode.</p>|Dependent item|hadoop.namenode.files_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Total load|<p>The current number of concurrent file accesses (read/write) across all DataNodes.</p>|Dependent item|hadoop.namenode.total_load<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Blocks allocable|<p>Maximum number of blocks allocable.</p>|Dependent item|hadoop.namenode.block_capacity<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Total blocks|<p>Count of blocks tracked by NameNode.</p>|Dependent item|hadoop.namenode.blocks_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|NameNode: Under-replicated blocks|<p>The number of blocks with insufficient replication.</p>|Dependent item|hadoop.namenode.under_replicated_blocks<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Hadoop: Get NodeManagers states||HTTP agent|hadoop.nodemanagers.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Hadoop: Get DataNodes states||HTTP agent|hadoop.datanodes.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ResourceManager: Service is unavailable||`last(/Hadoop by HTTP/net.tcp.service["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"])=0`|Average|**Manual close**: Yes|
|ResourceManager: Service response time is too high||`min(/Hadoop by HTTP/net.tcp.service.perf["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"],5m)>{$HADOOP.RESOURCEMANAGER.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>ResourceManager: Service is unavailable</li></ul>|
|ResourceManager: Service has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Hadoop by HTTP/hadoop.resourcemanager.uptime)<10m`|Info|**Manual close**: Yes|
|ResourceManager: Failed to fetch ResourceManager API page|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Hadoop by HTTP/hadoop.resourcemanager.uptime,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>ResourceManager: Service is unavailable</li></ul>|
|ResourceManager: Cluster has no active NodeManagers|<p>Cluster is unable to execute any jobs without at least one NodeManager.</p>|`max(/Hadoop by HTTP/hadoop.resourcemanager.num_active_nm,5m)=0`|High||
|ResourceManager: Cluster has unhealthy NodeManagers|<p>YARN considers any node with disk utilization exceeding the value specified under the property yarn.nodemanager.disk-health-checker.max-disk-utilization-per-disk-percentage (in yarn-site.xml) to be unhealthy. Ample disk space is critical to ensure uninterrupted operation of a Hadoop cluster, and large numbers of unhealthyNodes (the number to alert on depends on the size of your cluster) should be quickly investigated and resolved.</p>|`min(/Hadoop by HTTP/hadoop.resourcemanager.num_unhealthy_nm,15m)>0`|Average||
|NameNode: Service is unavailable||`last(/Hadoop by HTTP/net.tcp.service["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"])=0`|Average|**Manual close**: Yes|
|NameNode: Service response time is too high||`min(/Hadoop by HTTP/net.tcp.service.perf["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"],5m)>{$HADOOP.NAMENODE.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>NameNode: Service is unavailable</li></ul>|
|NameNode: Service has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Hadoop by HTTP/hadoop.namenode.uptime)<10m`|Info|**Manual close**: Yes|
|NameNode: Failed to fetch NameNode API page|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Hadoop by HTTP/hadoop.namenode.uptime,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>NameNode: Service is unavailable</li></ul>|
|NameNode: Cluster capacity remaining is low|<p>A good practice is to ensure that disk use never exceeds 80 percent capacity.</p>|`max(/Hadoop by HTTP/hadoop.namenode.percent_remaining,15m)<{$HADOOP.CAPACITY_REMAINING.MIN.WARN}`|Warning||
|NameNode: Cluster has missing blocks|<p>A missing block is far worse than a corrupt block, because a missing block cannot be recovered by copying a replica.</p>|`min(/Hadoop by HTTP/hadoop.namenode.missing_blocks,15m)>0`|Average||
|NameNode: Cluster has volume failures|<p>HDFS now allows for disks to fail in place, without affecting DataNode operations, until a threshold value is reached. This is set on each DataNode via the dfs.datanode.failed.volumes.tolerated property; it defaults to 0, meaning that any volume failure will shut down the DataNode; on a production cluster where DataNodes typically have 6, 8, or 12 disks, setting this parameter to 1 or 2 is typically the best practice.</p>|`min(/Hadoop by HTTP/hadoop.namenode.volume_failures_total,15m)>0`|Average||
|NameNode: Cluster has DataNodes in Dead state|<p>The death of a DataNode causes a flurry of network activity, as the NameNode initiates replication of blocks lost on the dead nodes.</p>|`min(/Hadoop by HTTP/hadoop.namenode.num_dead_data_nodes,5m)>0`|Average||

### LLD rule Node manager discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node manager discovery||HTTP agent|hadoop.nodemanager.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Node manager discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hadoop NodeManager {#HOSTNAME}: Get stats||HTTP agent|hadoop.nodemanager.get[{#HOSTNAME}]|
|{#HOSTNAME}: RPC queue & processing time|<p>Average time spent on processing RPC requests.</p>|Dependent item|hadoop.nodemanager.rpc_processing_time_avg[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: Container launch avg duration||Dependent item|hadoop.nodemanager.container_launch_duration_avg[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: JVM Threads|<p>The number of JVM threads.</p>|Dependent item|hadoop.nodemanager.jvm.threads[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: JVM Garbage collection time|<p>The JVM garbage collection time in milliseconds.</p>|Dependent item|hadoop.nodemanager.jvm.gc_time[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: JVM Heap usage|<p>The JVM heap usage in MBytes.</p>|Dependent item|hadoop.nodemanager.jvm.mem_heap_used[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: Uptime||Dependent item|hadoop.nodemanager.uptime[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Hadoop NodeManager {#HOSTNAME}: Get raw info||Dependent item|hadoop.nodemanager.raw_info[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.HostName=='{#HOSTNAME}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#HOSTNAME}: State|<p>State of the node - valid values are: NEW, RUNNING, UNHEALTHY, DECOMMISSIONING, DECOMMISSIONED, LOST, REBOOTED, SHUTDOWN.</p>|Dependent item|hadoop.nodemanager.state[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.State`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#HOSTNAME}: Version||Dependent item|hadoop.nodemanager.version[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NodeManagerVersion`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#HOSTNAME}: Number of containers||Dependent item|hadoop.nodemanager.numcontainers[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NumContainers`</p></li></ul>|
|{#HOSTNAME}: Used memory||Dependent item|hadoop.nodemanager.usedmemory[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.UsedMemoryMB`</p></li></ul>|
|{#HOSTNAME}: Available memory||Dependent item|hadoop.nodemanager.availablememory[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.AvailableMemoryMB`</p></li></ul>|

### Trigger prototypes for Node manager discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#HOSTNAME}: Service has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Hadoop by HTTP/hadoop.nodemanager.uptime[{#HOSTNAME}])<10m`|Info|**Manual close**: Yes|
|{#HOSTNAME}: Failed to fetch NodeManager API page|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Hadoop by HTTP/hadoop.nodemanager.uptime[{#HOSTNAME}],30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#HOSTNAME}: NodeManager has state {ITEM.VALUE}.</li></ul>|
|{#HOSTNAME}: NodeManager has state {ITEM.VALUE}.|<p>The state is different from normal.</p>|`last(/Hadoop by HTTP/hadoop.nodemanager.state[{#HOSTNAME}])<>"RUNNING"`|Average||

### LLD rule Data node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Data node discovery||HTTP agent|hadoop.datanode.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Data node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hadoop DataNode {#HOSTNAME}: Get stats||HTTP agent|hadoop.datanode.get[{#HOSTNAME}]|
|{#HOSTNAME}: Remaining|<p>Remaining disk space.</p>|Dependent item|hadoop.datanode.remaining[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: Used|<p>Used disk space.</p>|Dependent item|hadoop.datanode.dfs_used[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: Number of failed volumes|<p>Number of failed storage volumes.</p>|Dependent item|hadoop.datanode.numfailedvolumes[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: JVM Threads|<p>The number of JVM threads.</p>|Dependent item|hadoop.datanode.jvm.threads[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: JVM Garbage collection time|<p>The JVM garbage collection time in milliseconds.</p>|Dependent item|hadoop.datanode.jvm.gc_time[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: JVM Heap usage|<p>The JVM heap usage in MBytes.</p>|Dependent item|hadoop.datanode.jvm.mem_heap_used[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#HOSTNAME}: Uptime||Dependent item|hadoop.datanode.uptime[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Hadoop DataNode {#HOSTNAME}: Get raw info||Dependent item|hadoop.datanode.raw_info[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.HostName=='{#HOSTNAME}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#HOSTNAME}: Version|<p>DataNode software version.</p>|Dependent item|hadoop.datanode.version[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#HOSTNAME}: Admin state|<p>Administrative state.</p>|Dependent item|hadoop.datanode.admin_state[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.adminState`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#HOSTNAME}: Oper state|<p>Operational state.</p>|Dependent item|hadoop.datanode.oper_state[{#HOSTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.operState`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Data node discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#HOSTNAME}: Service has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Hadoop by HTTP/hadoop.datanode.uptime[{#HOSTNAME}])<10m`|Info|**Manual close**: Yes|
|{#HOSTNAME}: Failed to fetch DataNode API page|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Hadoop by HTTP/hadoop.datanode.uptime[{#HOSTNAME}],30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>{#HOSTNAME}: DataNode has state {ITEM.VALUE}.</li></ul>|
|{#HOSTNAME}: DataNode has state {ITEM.VALUE}.|<p>The state is different from normal.</p>|`last(/Hadoop by HTTP/hadoop.datanode.oper_state[{#HOSTNAME}])<>"Live"`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

