
# Hadoop by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template for monitoring Hadoop over HTTP that works without any external scripts.
It collects metrics by polling the Hadoop API remotely using an HTTP agent and JSONPath preprocessing.
Zabbix server (or proxy) execute direct requests to ResourceManager, NodeManagers, NameNode, DataNodes APIs.
All metrics are collected at once, thanks to the Zabbix bulk data collection.


This template was tested on:

- Hadoop, version 3.1 and later

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

You should define the IP address (or FQDN) and Web-UI port for the ResourceManager in {$HADOOP.RESOURCEMANAGER.HOST} and {$HADOOP.RESOURCEMANAGER.PORT} macros and for the NameNode in {$HADOOP.NAMENODE.HOST} and {$HADOOP.NAMENODE.PORT} macros respectively. Macros can be set in the template or overridden at the host level.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HADOOP.CAPACITY_REMAINING.MIN.WARN} |<p>The Hadoop cluster capacity remaining percent for trigger expression.</p> |`20` |
|{$HADOOP.NAMENODE.HOST} |<p>The Hadoop NameNode host IP address or FQDN.</p> |`NameNode` |
|{$HADOOP.NAMENODE.PORT} |<p>The Hadoop NameNode Web-UI port.</p> |`9870` |
|{$HADOOP.NAMENODE.RESPONSE_TIME.MAX.WARN} |<p>The Hadoop NameNode API page maximum response time in seconds for trigger expression.</p> |`10s` |
|{$HADOOP.RESOURCEMANAGER.HOST} |<p>The Hadoop ResourceManager host IP address or FQDN.</p> |`ResourceManager` |
|{$HADOOP.RESOURCEMANAGER.PORT} |<p>The Hadoop ResourceManager Web-UI port.</p> |`8088` |
|{$HADOOP.RESOURCEMANAGER.RESPONSE_TIME.MAX.WARN} |<p>The Hadoop ResourceManager API page maximum response time in seconds for trigger expression.</p> |`10s` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Data node discovery |<p>-</p> |HTTP_AGENT |hadoop.datanode.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Node manager discovery |<p>-</p> |HTTP_AGENT |hadoop.nodemanager.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Hadoop |ResourceManager: Service status |<p>Hadoop ResourceManager API port availability.</p> |SIMPLE |net.tcp.service["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Hadoop |ResourceManager: Service response time |<p>Hadoop ResourceManager API performance.</p> |SIMPLE |net.tcp.service.perf["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"] |
|Hadoop |ResourceManager: Uptime |<p>-</p> |DEPENDENT |hadoop.resourcemanager.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|Hadoop |ResourceManager: RPC queue & processing time |<p>Average time spent on processing RPC requests.</p> |DEPENDENT |hadoop.resourcemanager.rpc_processing_time_avg<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=RpcActivityForPort8031')].RpcProcessingTimeAvgTime.first()`</p> |
|Hadoop |ResourceManager: Active NMs |<p>Number of Active NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_active_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumActiveNMs.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |ResourceManager: Decommissioning NMs |<p>Number of Decommissioning NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_decommissioning_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumDecommissioningNMs.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |ResourceManager: Decommissioned NMs |<p>Number of Decommissioned NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_decommissioned_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumDecommissionedNMs.first()`</p> |
|Hadoop |ResourceManager: Lost NMs |<p>Number of Lost NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_lost_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumLostNMs.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |ResourceManager: Unhealthy NMs |<p>Number of Unhealthy NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_unhealthy_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumUnhealthyNMs.first()`</p> |
|Hadoop |ResourceManager: Rebooted NMs |<p>Number of Rebooted NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_rebooted_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumRebootedNMs.first()`</p> |
|Hadoop |ResourceManager: Shutdown NMs |<p>Number of Shutdown NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_shutdown_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumShutdownNMs.first()`</p> |
|Hadoop |NameNode: Service status |<p>Hadoop NameNode API port availability.</p> |SIMPLE |net.tcp.service["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Hadoop |NameNode: Service response time |<p>Hadoop NameNode API performance.</p> |SIMPLE |net.tcp.service.perf["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"] |
|Hadoop |NameNode: Uptime |<p>-</p> |DEPENDENT |hadoop.namenode.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|Hadoop |NameNode: RPC queue & processing time |<p>Average time spent on processing RPC requests.</p> |DEPENDENT |hadoop.namenode.rpc_processing_time_avg<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=RpcActivityForPort9000')].RpcProcessingTimeAvgTime.first()`</p> |
|Hadoop |NameNode: Block Pool Renaming |<p>-</p> |DEPENDENT |hadoop.namenode.percent_block_pool_used<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=NameNodeInfo')].PercentBlockPoolUsed.first()`</p> |
|Hadoop |NameNode: Transactions since last checkpoint |<p>Total number of transactions since last checkpoint.</p> |DEPENDENT |hadoop.namenode.transactions_since_last_checkpoint<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].TransactionsSinceLastCheckpoint.first()`</p> |
|Hadoop |NameNode: Percent capacity remaining |<p>Available capacity in percent.</p> |DEPENDENT |hadoop.namenode.percent_remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=NameNodeInfo')].PercentRemaining.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |NameNode: Capacity remaining |<p>Available capacity.</p> |DEPENDENT |hadoop.namenode.capacity_remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].CapacityRemaining.first()`</p> |
|Hadoop |NameNode: Corrupt blocks |<p>Number of corrupt blocks.</p> |DEPENDENT |hadoop.namenode.corrupt_blocks<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].CorruptBlocks.first()`</p> |
|Hadoop |NameNode: Missing blocks |<p>Number of missing blocks.</p> |DEPENDENT |hadoop.namenode.missing_blocks<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].MissingBlocks.first()`</p> |
|Hadoop |NameNode: Failed volumes |<p>Number of failed volumes.</p> |DEPENDENT |hadoop.namenode.volume_failures_total<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].VolumeFailuresTotal.first()`</p> |
|Hadoop |NameNode: Alive DataNodes |<p>Count of alive DataNodes.</p> |DEPENDENT |hadoop.namenode.num_live_data_nodes<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].NumLiveDataNodes.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |NameNode: Dead DataNodes |<p>Count of dead DataNodes.</p> |DEPENDENT |hadoop.namenode.num_dead_data_nodes<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].NumDeadDataNodes.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |NameNode: Stale DataNodes |<p>DataNodes that do not send a heartbeat within 30 seconds are marked as "stale".</p> |DEPENDENT |hadoop.namenode.num_stale_data_nodes<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].StaleDataNodes.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |NameNode: Total files |<p>Total count of files tracked by the NameNode.</p> |DEPENDENT |hadoop.namenode.files_total<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].FilesTotal.first()`</p> |
|Hadoop |NameNode: Total load |<p>The current number of concurrent file accesses (read/write) across all DataNodes.</p> |DEPENDENT |hadoop.namenode.total_load<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].TotalLoad.first()`</p> |
|Hadoop |NameNode: Blocks allocable |<p>Maximum number of blocks allocable.</p> |DEPENDENT |hadoop.namenode.block_capacity<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].BlockCapacity.first()`</p> |
|Hadoop |NameNode: Total blocks |<p>Count of blocks tracked by NameNode.</p> |DEPENDENT |hadoop.namenode.blocks_total<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].BlocksTotal.first()`</p> |
|Hadoop |NameNode: Under-replicated blocks |<p>The number of blocks with insufficient replication.</p> |DEPENDENT |hadoop.namenode.under_replicated_blocks<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].UnderReplicatedBlocks.first()`</p> |
|Hadoop |{#HOSTNAME}: RPC queue & processing time |<p>Average time spent on processing RPC requests.</p> |DEPENDENT |hadoop.nodemanager.rpc_processing_time_avg[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NodeManager,name=RpcActivityForPort8040')].RpcProcessingTimeAvgTime.first()`</p> |
|Hadoop |{#HOSTNAME}: Container launch avg duration |<p>-</p> |DEPENDENT |hadoop.nodemanager.container_launch_duration_avg[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NodeManager,name=NodeManagerMetrics')].ContainerLaunchDurationAvgTime.first()`</p> |
|Hadoop |{#HOSTNAME}: JVM Threads |<p>The number of JVM threads.</p> |DEPENDENT |hadoop.nodemanager.jvm.threads[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Threading')].ThreadCount.first()`</p> |
|Hadoop |{#HOSTNAME}: JVM Garbage collection time |<p>The JVM garbage collection time in milliseconds.</p> |DEPENDENT |hadoop.nodemanager.jvm.gc_time[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NodeManager,name=JvmMetrics')].GcTimeMillis.first()`</p> |
|Hadoop |{#HOSTNAME}: JVM Heap usage |<p>The JVM heap usage in MBytes.</p> |DEPENDENT |hadoop.nodemanager.jvm.mem_heap_used[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NodeManager,name=JvmMetrics')].MemHeapUsedM.first()`</p> |
|Hadoop |{#HOSTNAME}: Uptime |<p>-</p> |DEPENDENT |hadoop.nodemanager.uptime[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|Hadoop |{#HOSTNAME}: State |<p>State of the node - valid values are: NEW, RUNNING, UNHEALTHY, DECOMMISSIONING, DECOMMISSIONED, LOST, REBOOTED, SHUTDOWN.</p> |DEPENDENT |hadoop.nodemanager.state[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.HostName=='{#HOSTNAME}')].State.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |{#HOSTNAME}: Version |<p>-</p> |DEPENDENT |hadoop.nodemanager.version[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.HostName=='{#HOSTNAME}')].NodeManagerVersion.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |{#HOSTNAME}: Number of containers |<p>-</p> |DEPENDENT |hadoop.nodemanager.numcontainers[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.HostName=='{#HOSTNAME}')].NumContainers.first()`</p> |
|Hadoop |{#HOSTNAME}: Used memory |<p>-</p> |DEPENDENT |hadoop.nodemanager.usedmemory[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.HostName=='{#HOSTNAME}')].UsedMemoryMB.first()`</p> |
|Hadoop |{#HOSTNAME}: Available memory |<p>-</p> |DEPENDENT |hadoop.nodemanager.availablememory[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.HostName=='{#HOSTNAME}')].AvailableMemoryMB.first()`</p> |
|Hadoop |{#HOSTNAME}: Remaining |<p>Remaining disk space.</p> |DEPENDENT |hadoop.datanode.remaining[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=DataNode,name=FSDatasetState')].Remaining.first()`</p> |
|Hadoop |{#HOSTNAME}: Used |<p>Used disk space.</p> |DEPENDENT |hadoop.datanode.dfs_used[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=DataNode,name=FSDatasetState')].DfsUsed.first()`</p> |
|Hadoop |{#HOSTNAME}: Number of failed volumes |<p>Number of failed storage volumes.</p> |DEPENDENT |hadoop.datanode.numfailedvolumes[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=DataNode,name=FSDatasetState')].NumFailedVolumes.first()`</p> |
|Hadoop |{#HOSTNAME}: JVM Threads |<p>The number of JVM threads.</p> |DEPENDENT |hadoop.datanode.jvm.threads[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Threading')].ThreadCount.first()`</p> |
|Hadoop |{#HOSTNAME}: JVM Garbage collection time |<p>The JVM garbage collection time in milliseconds.</p> |DEPENDENT |hadoop.datanode.jvm.gc_time[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=DataNode,name=JvmMetrics')].GcTimeMillis.first()`</p> |
|Hadoop |{#HOSTNAME}: JVM Heap usage |<p>The JVM heap usage in MBytes.</p> |DEPENDENT |hadoop.datanode.jvm.mem_heap_used[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=DataNode,name=JvmMetrics')].MemHeapUsedM.first()`</p> |
|Hadoop |{#HOSTNAME}: Uptime |<p>-</p> |DEPENDENT |hadoop.datanode.uptime[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|Hadoop |{#HOSTNAME}: Version |<p>DataNode software version.</p> |DEPENDENT |hadoop.datanode.version[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.HostName=='{#HOSTNAME}')].version.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |{#HOSTNAME}: Admin state |<p>Administrative state.</p> |DEPENDENT |hadoop.datanode.admin_state[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.HostName=='{#HOSTNAME}')].adminState.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |{#HOSTNAME}: Oper state |<p>Operational state.</p> |DEPENDENT |hadoop.datanode.oper_state[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.HostName=='{#HOSTNAME}')].operState.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |Get ResourceManager stats |<p>-</p> |HTTP_AGENT |hadoop.resourcemanager.get |
|Zabbix raw items |Get NameNode stats |<p>-</p> |HTTP_AGENT |hadoop.namenode.get |
|Zabbix raw items |Get NodeManagers states |<p>-</p> |HTTP_AGENT |hadoop.nodemanagers.get<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(JSON.parse(JSON.parse(value).beans[0].LiveNodeManagers))`</p> |
|Zabbix raw items |Get DataNodes states |<p>-</p> |HTTP_AGENT |hadoop.datanodes.get<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Zabbix raw items |Hadoop NodeManager {#HOSTNAME}: Get stats |<p>-</p> |HTTP_AGENT |hadoop.nodemanager.get[{#HOSTNAME}] |
|Zabbix raw items |Hadoop DataNode {#HOSTNAME}: Get stats |<p>-</p> |HTTP_AGENT |hadoop.datanode.get[{#HOSTNAME}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|ResourceManager: Service is unavailable |<p>-</p> |`last(/Hadoop by HTTP/net.tcp.service["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"])=0` |AVERAGE |<p>Manual close: YES</p> |
|ResourceManager: Service response time is too high |<p>-</p> |`min(/Hadoop by HTTP/net.tcp.service.perf["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"],5m)>{$HADOOP.RESOURCEMANAGER.RESPONSE_TIME.MAX.WARN}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- ResourceManager: Service is unavailable</p> |
|ResourceManager: Service has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Hadoop by HTTP/hadoop.resourcemanager.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|ResourceManager: Failed to fetch ResourceManager API page |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`nodata(/Hadoop by HTTP/hadoop.resourcemanager.uptime,30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- ResourceManager: Service is unavailable</p> |
|ResourceManager: Cluster has no active NodeManagers |<p>Cluster is unable to execute any jobs without at least one NodeManager.</p> |`max(/Hadoop by HTTP/hadoop.resourcemanager.num_active_nm,5m)=0` |HIGH | |
|ResourceManager: Cluster has unhealthy NodeManagers |<p>YARN considers any node with disk utilization exceeding the value specified under the property yarn.nodemanager.disk-health-checker.max-disk-utilization-per-disk-percentage (in yarn-site.xml) to be unhealthy. Ample disk space is critical to ensure uninterrupted operation of a Hadoop cluster, and large numbers of unhealthyNodes (the number to alert on depends on the size of your cluster) should be quickly investigated and resolved.</p> |`min(/Hadoop by HTTP/hadoop.resourcemanager.num_unhealthy_nm,15m)>0` |AVERAGE | |
|NameNode: Service is unavailable |<p>-</p> |`last(/Hadoop by HTTP/net.tcp.service["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"])=0` |AVERAGE |<p>Manual close: YES</p> |
|NameNode: Service response time is too high |<p>-</p> |`min(/Hadoop by HTTP/net.tcp.service.perf["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"],5m)>{$HADOOP.NAMENODE.RESPONSE_TIME.MAX.WARN}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- NameNode: Service is unavailable</p> |
|NameNode: Service has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Hadoop by HTTP/hadoop.namenode.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|NameNode: Failed to fetch NameNode API page |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`nodata(/Hadoop by HTTP/hadoop.namenode.uptime,30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- NameNode: Service is unavailable</p> |
|NameNode: Cluster capacity remaining is low |<p>A good practice is to ensure that disk use never exceeds 80 percent capacity.</p> |`max(/Hadoop by HTTP/hadoop.namenode.percent_remaining,15m)<{$HADOOP.CAPACITY_REMAINING.MIN.WARN}` |WARNING | |
|NameNode: Cluster has missing blocks |<p>A missing block is far worse than a corrupt block, because a missing block cannot be recovered by copying a replica.</p> |`min(/Hadoop by HTTP/hadoop.namenode.missing_blocks,15m)>0` |AVERAGE | |
|NameNode: Cluster has volume failures |<p>HDFS now allows for disks to fail in place, without affecting DataNode operations, until a threshold value is reached. This is set on each DataNode via the dfs.datanode.failed.volumes.tolerated property; it defaults to 0, meaning that any volume failure will shut down the DataNode; on a production cluster where DataNodes typically have 6, 8, or 12 disks, setting this parameter to 1 or 2 is typically the best practice.</p> |`min(/Hadoop by HTTP/hadoop.namenode.volume_failures_total,15m)>0` |AVERAGE | |
|NameNode: Cluster has DataNodes in Dead state |<p>The death of a DataNode causes a flurry of network activity, as the NameNode initiates replication of blocks lost on the dead nodes.</p> |`min(/Hadoop by HTTP/hadoop.namenode.num_dead_data_nodes,5m)>0` |AVERAGE | |
|{#HOSTNAME}: Service has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Hadoop by HTTP/hadoop.nodemanager.uptime[{#HOSTNAME}])<10m` |INFO |<p>Manual close: YES</p> |
|{#HOSTNAME}: Failed to fetch NodeManager API page |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`nodata(/Hadoop by HTTP/hadoop.nodemanager.uptime[{#HOSTNAME}],30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#HOSTNAME}: NodeManager has state {ITEM.VALUE}.</p> |
|{#HOSTNAME}: NodeManager has state {ITEM.VALUE}. |<p>The state is different from normal.</p> |`last(/Hadoop by HTTP/hadoop.nodemanager.state[{#HOSTNAME}])<>"RUNNING"` |AVERAGE | |
|{#HOSTNAME}: Service has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Hadoop by HTTP/hadoop.datanode.uptime[{#HOSTNAME}])<10m` |INFO |<p>Manual close: YES</p> |
|{#HOSTNAME}: Failed to fetch DataNode API page |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`nodata(/Hadoop by HTTP/hadoop.datanode.uptime[{#HOSTNAME}],30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- {#HOSTNAME}: DataNode has state {ITEM.VALUE}.</p> |
|{#HOSTNAME}: DataNode has state {ITEM.VALUE}. |<p>The state is different from normal.</p> |`last(/Hadoop by HTTP/hadoop.datanode.oper_state[{#HOSTNAME}])<>"Live"` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/413459-discussion-thread-for-official-zabbix-template-hadoop).


## References

https://hadoop.apache.org/docs/current/
