
# Hadoop by HTTP

## Overview

For Zabbix version: 5.0 and higher  
The template for monitoring Hadoop over HTTP that works without any external scripts.  
It collects metrics by polling the Hadoop API remotely using an HTTP agent and JSONPATH preprocessing.  
All metrics are collected at once, thanks to Zabbix's bulk data collection.


This template was tested on:

- Zabbix, version 5.0 and later
- Hadoop, version 3.1 and later

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/5.0/manual/config/templates_out_of_the_box/http) for basic instructions.

You should define the IP address (or FQDN) and TCP port of ResourceManager and NameNode in macros in the template to use on the host level.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HADOOP.CAPACITY_REMAINING.MIN.WARN} |<p>The Hadoop cluster capacity remaining percent for trigger expression.</p> |`20` |
|{$HADOOP.NAMENODE.HOST} |<p>The Hadoop NameNode host IP address or FQDN.</p> |`192.168.7.123` |
|{$HADOOP.NAMENODE.PORT} |<p>The Hadoop NameNode TCP port.</p> |`9870` |
|{$HADOOP.NAMENODE.RESPONSE_TIME.MAX.WARN} |<p>The Hadoop NameNode API page maximum response time in seconds for trigger expression.</p> |`10s` |
|{$HADOOP.RESOURCEMANAGER.HOST} |<p>The Hadoop ResourceManager host IP address or FQDN.</p> |`192.168.7.123` |
|{$HADOOP.RESOURCEMANAGER.PORT} |<p>The Hadoop ResourceManager TCP port.</p> |`8088` |
|{$HADOOP.RESOURCEMANAGER.RESPONSE_TIME.MAX.WARN} |<p>The Hadoop ResourceManager API page maximum response time in seconds for trigger expression.</p> |`10s` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Node manager discovery |<p>-</p> |HTTP_AGENT |hadoop.nodemanager.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `Text is too long. Please see the template.`</p> |
|Data node discovery |<p>-</p> |HTTP_AGENT |hadoop.datanode.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `Text is too long. Please see the template.`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Hadoop |Resourcemanager: Service status |<p>Hadoop Resourcemanager API port availability.</p> |SIMPLE |net.tcp.service["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Hadoop |Resourcemanager: Service response time |<p>Hadoop Resourcemanager API performance.</p> |SIMPLE |net.tcp.service.perf["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"] |
|Hadoop |Resourcemanager: Uptime | |DEPENDENT |hadoop.resourcemanager.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|Hadoop |Resourcemanager: RPC queue & processing time | |DEPENDENT |hadoop.resourcemanager.rpc_processing_time_avg<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=RpcActivityForPort8031')].RpcProcessingTimeAvgTime.first()`</p> |
|Hadoop |Resourcemanager: Active NMs |<p>Number of Active NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_active_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumActiveNMs.first()`</p> |
|Hadoop |Resourcemanager: Decommissioning NMs |<p>Number of Decommissioning NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_decommissioning_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumDecommissioningNMs.first()`</p> |
|Hadoop |Resourcemanager: Decommissioned NMs |<p>Number of Decommissioned NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_decommissioned_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumDecommissionedNMs.first()`</p> |
|Hadoop |Resourcemanager: Lost NMs |<p>Number of Lost NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_lost_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumLostNMs.first()`</p> |
|Hadoop |Resourcemanager: Unhealthy NMs |<p>Number of Unhealthy NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_unhealthy_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumUnhealthyNMs.first()`</p> |
|Hadoop |Resourcemanager: Rebooted NMs |<p>Number of Rebooted NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_rebooted_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumRebootedNMs.first()`</p> |
|Hadoop |Resourcemanager: Shutdown NMs |<p>Number of Shutdown NodeManagers.</p> |DEPENDENT |hadoop.resourcemanager.num_shutdown_nm<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=ResourceManager,name=ClusterMetrics')].NumShutdownNMs.first()`</p> |
|Hadoop |Namenode: Service status |<p>Hadoop Namenode API port availability.</p> |SIMPLE |net.tcp.service["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Hadoop |Namenode: Service response time |<p>Hadoop Namenode API performance.</p> |SIMPLE |net.tcp.service.perf["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"] |
|Hadoop |Namenode: Uptime | |DEPENDENT |hadoop.namenode.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|Hadoop |Namenode: RPC queue & processing time | |DEPENDENT |hadoop.namenode.rpc_processing_time_avg<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=RpcActivityForPort9000')].RpcProcessingTimeAvgTime.first()`</p> |
|Hadoop |Namenode: Block Pool Renaming | |DEPENDENT |hadoop.namenode.percent_block_pool_used<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=NameNodeInfo')].PercentBlockPoolUsed.first()`</p> |
|Hadoop |Namenode: Transactions since last checkpoint | |DEPENDENT |hadoop.namenode.transactions_since_last_checkpoint<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].TransactionsSinceLastCheckpoint.first()`</p> |
|Hadoop |Namenode: Percent capacity remaining |<p>Available capacity in percent.</p> |DEPENDENT |hadoop.nodemanager.percent_remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=NameNodeInfo')].PercentRemaining.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |Namenode: Capacity remaining |<p>Available capacity</p> |DEPENDENT |hadoop.nodemanager.capacity_remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].CapacityRemaining.first()`</p> |
|Hadoop |Namenode: Corrupt blocks |<p>Number of corrupt blocks</p> |DEPENDENT |hadoop.nodemanager.corrupt_blocks<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].CorruptBlocks.first()`</p> |
|Hadoop |Namenode: Missing blocks |<p>Number of missing blocks</p> |DEPENDENT |hadoop.nodemanager.missing_blocks<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].MissingBlocks.first()`</p> |
|Hadoop |Namenode: Failed volumes |<p>Number of failed volumes</p> |DEPENDENT |hadoop.nodemanager.volume_failures_total<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].VolumeFailuresTotal.first()`</p> |
|Hadoop |Namenode: Alive Datanodes |<p>Count of alive DataNodes</p> |DEPENDENT |hadoop.nodemanager.num_live_data_nodes<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].NumLiveDataNodes.first()`</p> |
|Hadoop |Namenode: Dead Datanodes |<p>Count of dead DataNodes</p> |DEPENDENT |hadoop.nodemanager.num_dead_data_nodes<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].NumDeadDataNodes.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |Namenode: Stale Datanodes |<p>DataNodes that do not send a heartbeat within 30 seconds are marked as “stale”</p> |DEPENDENT |hadoop.nodemanager.num_stale_data_nodes<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].StaleDataNodes.first()`</p> |
|Hadoop |Namenode: Total files |<p>Total count of files tracked by the NameNode</p> |DEPENDENT |hadoop.nodemanager.files_total<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].FilesTotal.first()`</p> |
|Hadoop |Namenode: Total load |<p>The current number of concurrent file accesses (read/write) across all DataNodes.</p> |DEPENDENT |hadoop.nodemanager.total_load<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].TotalLoad.first()`</p> |
|Hadoop |Namenode: Blocks allocable |<p>Maximum number of blocks allocable</p> |DEPENDENT |hadoop.nodemanager.block_capacity<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].BlockCapacity.first()`</p> |
|Hadoop |Namenode: Total blocks |<p>Count of blocks tracked by NameNode</p> |DEPENDENT |hadoop.nodemanager.blocks_total<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].BlocksTotal.first()`</p> |
|Hadoop |Namenode: Under-replicated blocks |<p>The number of blocks with insufficient replication</p> |DEPENDENT |hadoop.nodemanager.under_replicated_blocks<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NameNode,name=FSNamesystem')].UnderReplicatedBlocks.first()`</p> |
|Hadoop |{#HOSTNAME}: State |<p>State of the node - valid values are: NEW, RUNNING, UNHEALTHY, DECOMMISSIONING, DECOMMISSIONED, LOST, REBOOTED, SHUTDOWN</p> |DEPENDENT |hadoop.nodemanager.state[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.HostName=='{#HOSTNAME}')].State.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |{#HOSTNAME}: Version | |DEPENDENT |hadoop.nodemanager.version[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.HostName=='{#HOSTNAME}')].NodeManagerVersion.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Hadoop |{#HOSTNAME}: Number of containers | |DEPENDENT |hadoop.nodemanager.numcontainers[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.HostName=='{#HOSTNAME}')].NumContainers.first()`</p> |
|Hadoop |{#HOSTNAME}: Used memory | |DEPENDENT |hadoop.nodemanager.usedmemory[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.HostName=='{#HOSTNAME}')].UsedMemoryMB.first()`</p> |
|Hadoop |{#HOSTNAME}: Available memory | |DEPENDENT |hadoop.nodemanager.availablememory[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.HostName=='{#HOSTNAME}')].AvailableMemoryMB.first()`</p> |
|Zabbix_raw_items |Get Resourcemanager stats |<p>-</p> |HTTP_AGENT |hadoop.resourcemanager.get |
|Zabbix_raw_items |Get Namenode stats |<p>-</p> |HTTP_AGENT |hadoop.namenode.get |
|Zabbix_raw_items |Get Nodemanagers states |<p>-</p> |HTTP_AGENT |hadoop.nodemanagers.get<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(JSON.parse(JSON.parse(value).beans[0].LiveNodeManagers)) `</p> |
|Zabbix_raw_items |Get Datanodes states |<p>-</p> |HTTP_AGENT |hadoop.datanodes.get<p>**Preprocessing**:</p><p>- JAVASCRIPT: `Text is too long. Please see the template.`</p> |
|Zabbix_raw_items |Hadoop Nodemanager {#HOSTNAME}: Get {#HOSTNAME} nodemanager stats | |HTTP_AGENT |hadoop.nodemanager.get[{#HOSTNAME}] |
|Zabbix_raw_items |{#HOSTNAME}: RPC queue & processing time | |DEPENDENT |hadoop.nodemanager.rpc_processing_time_avg[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NodeManager,name=RpcActivityForPort8040')].RpcProcessingTimeAvgTime.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: Container launch avg duration | |DEPENDENT |hadoop.nodemanager.container_launch_duration_avg[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NodeManager,name=NodeManagerMetrics')].ContainerLaunchDurationAvgTime.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: JVM Threads |<p>The number of JVM threads.</p> |DEPENDENT |hadoop.nodemanager.jvm.threads[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Threading')].ThreadCount.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: JVM Garbage collection time |<p>The JVM garbage collection time in milliseconds.</p> |DEPENDENT |hadoop.nodemanager.jvm.gc_time[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NodeManager,name=JvmMetrics')].GcTimeMillis.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: JVM Heap usage |<p>The JVM heap usage in MBytes.</p> |DEPENDENT |hadoop.nodemanager.jvm.mem_heap_used[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=NodeManager,name=JvmMetrics')].MemHeapUsedM.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: Uptime | |DEPENDENT |hadoop.nodemanager.uptime[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|Zabbix_raw_items |Hadoop Datanode {#HOSTNAME}: Get {#HOSTNAME} datanode stats | |HTTP_AGENT |hadoop.datanode.get[{#HOSTNAME}] |
|Zabbix_raw_items |{#HOSTNAME}: Remaining |<p>Remaining disk space</p> |DEPENDENT |hadoop.datanode.remaining[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=DataNode,name=FSDatasetState')].Remaining.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: Used |<p>Used disk space</p> |DEPENDENT |hadoop.datanode.dfs_used[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=DataNode,name=FSDatasetState')].DfsUsed.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: Number of failed volumes |<p>Number of failed storage volumes.</p> |DEPENDENT |hadoop.datanode.numfailedvolumes[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=DataNode,name=FSDatasetState')].NumFailedVolumes.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: JVM Threads |<p>The number of JVM threads.</p> |DEPENDENT |hadoop.datanode.jvm.threads[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Threading')].ThreadCount.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: JVM Garbage collection time |<p>The JVM garbage collection time in milliseconds.</p> |DEPENDENT |hadoop.datanode.jvm.gc_time[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=DataNode,name=JvmMetrics')].GcTimeMillis.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: JVM Heap usage |<p>The JVM heap usage in MBytes.</p> |DEPENDENT |hadoop.datanode.jvm.mem_heap_used[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='Hadoop:service=DataNode,name=JvmMetrics')].MemHeapUsedM.first()`</p> |
|Zabbix_raw_items |{#HOSTNAME}: Uptime | |DEPENDENT |hadoop.datanode.uptime[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.beans[?(@.name=='java.lang:type=Runtime')].Uptime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|Zabbix_raw_items |{#HOSTNAME}: Version |<p>Datanode software version</p> |DEPENDENT |hadoop.datanode.version[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.HostName=='{#HOSTNAME}')].version.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |{#HOSTNAME}: Admin state |<p>Administrative state</p> |DEPENDENT |hadoop.datanode.admin_state[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.HostName=='{#HOSTNAME}')].adminState.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |{#HOSTNAME}: Oper state |<p>Operational state</p> |DEPENDENT |hadoop.datanode.oper_state[{#HOSTNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.HostName=='{#HOSTNAME}')].operState.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Resourcemanager: Service is unavailable |<p>-</p> |`{TEMPLATE_NAME:net.tcp.service["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"].last()}=0` |AVERAGE |<p>Manual close: YES</p> |
|Resourcemanager: Service response time is too high (over {$HADOOP.RESOURCEMANAGER.RESPONSE_TIME.MAX.WARN} for 5m) |<p>-</p> |`{TEMPLATE_NAME:net.tcp.service.perf["tcp","{$HADOOP.RESOURCEMANAGER.HOST}","{$HADOOP.RESOURCEMANAGER.PORT}"].min(5m)}>{$HADOOP.RESOURCEMANAGER.RESPONSE_TIME.MAX.WARN}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Resourcemanager: Service is unavailable</p> |
|Resourcemanager: Cluster has not active NodeManagers |<p>Cluster is unable to execute any jobs without at least one NodeManager.</p> |`{TEMPLATE_NAME:hadoop.resourcemanager.num_active_nm.max(5m)}=0` |HIGH | |
|Resourcemanager: Cluster has the lost NodeManagers |<p>Cluster lost some NodeManagers.</p> |`{TEMPLATE_NAME:hadoop.resourcemanager.num_lost_nm.min(5m)}>0` |WARNING |<p>**Depends on**:</p><p>- Resourcemanager: Cluster has not active NodeManagers</p> |
|Resourcemanager: Cluster has unhealthy NodeManagers |<p>YARN considers any node with disk utilization exceeding the value specified under the property</p><p> yarn.nodemanager.disk-health-checker.max-disk-utilization-per-disk-percentage (in yarn-site.xml) to be unhealthy.</p><p> Ample disk space is critical to ensure uninterrupted operation of a Hadoop cluster, and large numbers of</p><p> unhealthyNodes (the number to alert on depends on the size of your cluster) should be quickly investigated and resolved.</p> |`{TEMPLATE_NAME:hadoop.resourcemanager.num_unhealthy_nm.min(15m)}>0` |AVERAGE | |
|Namenode: Service is unavailable |<p>-</p> |`{TEMPLATE_NAME:net.tcp.service["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"].last()}=0` |AVERAGE |<p>Manual close: YES</p> |
|Namenode: Service response time is too high (over {$HADOOP.NAMENODE.RESPONSE_TIME.MAX.WARN} for 5m) |<p>-</p> |`{TEMPLATE_NAME:net.tcp.service.perf["tcp","{$HADOOP.NAMENODE.HOST}","{$HADOOP.NAMENODE.PORT}"].min(5m)}>{$HADOOP.NAMENODE.RESPONSE_TIME.MAX.WARN}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Namenode: Service is unavailable</p> |
|Namenode: Cluster capacity remaining is low (below {$HADOOP.CAPACITY_REMAINING.MIN.WARN}% for 15m) |<p>It is good practice to ensure that disk use never exceeds 80 percent capacity.</p> |`{TEMPLATE_NAME:hadoop.nodemanager.percent_remaining.max(15m)}<{$HADOOP.CAPACITY_REMAINING.MIN.WARN}` |WARNING | |
|Namenode: Cluster has missing blocks. |<p>A missing block is far worse than a corrupt block, because a missing block cannot be recovered by copying a replica.</p> |`{TEMPLATE_NAME:hadoop.nodemanager.missing_blocks.min(15m)}>0` |AVERAGE | |
|Namenode: Cluster has volume failures. |<p>HDFS now allows for disks to fail in place, without affecting DataNode operations, until a threshold value is reached.</p><p> This is set on each DataNode via the dfs.datanode.failed.volumes.tolerated property; it defaults to 0, meaning that</p><p> any volume failure will shut down the DataNode; on a production cluster where DataNodes typically have 6, 8, or 12 disks,</p><p> setting this parameter to 1 or 2 is typically the best practice.</p> |`{TEMPLATE_NAME:hadoop.nodemanager.volume_failures_total.min(15m)}>0` |AVERAGE | |
|Namenode: Cluster has Datanodes in Dead state. |<p>The death of a DataNode causes a flurry of network activity, as the NameNode initiates replication of blocks lost on the dead nodes.</p> |`{TEMPLATE_NAME:hadoop.nodemanager.num_dead_data_nodes.min(5m)}>0` |AVERAGE | |
|{#HOSTNAME}: Nodemanager has state {ITEM.VALUE}. |<p>The state is different from normal.</p> |`{TEMPLATE_NAME:hadoop.nodemanager.state[{#HOSTNAME}].last()}<>"RUNNING"` |AVERAGE | |
|{#HOSTNAME}: Datanode has state {ITEM.VALUE}. |<p>The state is different from normal.</p> |`{TEMPLATE_NAME:hadoop.datanode.oper_state[{#HOSTNAME}].last()}<>"Live"` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).


## References

https://hadoop.apache.org/docs/current/
