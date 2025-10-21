
# Elasticsearch Cluster by HTTP

## Overview

The template to monitor Elasticsearch by Zabbix that work without any external scripts.
It works with both standalone and cluster instances.
The metrics are collected in one pass remotely using an HTTP agent.
They are getting values from REST API `_cluster/health`, `_cluster/stats`, `_nodes/stats` requests.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Elasticsearch 6.5, 7.6

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Set the hostname or IP address of the Elasticsearch host in the `{$ELASTICSEARCH.HOST}` macro.

2. Set the login and password in the `{$ELASTICSEARCH.USERNAME}` and `{$ELASTICSEARCH.PASSWORD}` macros.

3. If you use an atypical location of ES API, don't forget to change the macros `{$ELASTICSEARCH.SCHEME}`,`{$ELASTICSEARCH.PORT}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ELASTICSEARCH.USERNAME}|<p>The username of the Elasticsearch.</p>||
|{$ELASTICSEARCH.PASSWORD}|<p>The password of the Elasticsearch.</p>||
|{$ELASTICSEARCH.HOST}|<p>The hostname or IP address of the Elasticsearch host.</p>||
|{$ELASTICSEARCH.PORT}|<p>The port of the Elasticsearch host.</p>|`9200`|
|{$ELASTICSEARCH.SCHEME}|<p>The scheme of the Elasticsearch (http/https).</p>|`http`|
|{$ELASTICSEARCH.RESPONSE_TIME.MAX.WARN}|<p>The ES cluster maximum response time in seconds for trigger expression.</p>|`10s`|
|{$ELASTICSEARCH.QUERY_LATENCY.MAX.WARN}|<p>Maximum of query latency in milliseconds for trigger expression.</p>|`100`|
|{$ELASTICSEARCH.FETCH_LATENCY.MAX.WARN}|<p>Maximum of fetch latency in milliseconds for trigger expression.</p>|`100`|
|{$ELASTICSEARCH.INDEXING_LATENCY.MAX.WARN}|<p>Maximum of indexing latency in milliseconds for trigger expression.</p>|`100`|
|{$ELASTICSEARCH.FLUSH_LATENCY.MAX.WARN}|<p>Maximum of flush latency in milliseconds for trigger expression.</p>|`100`|
|{$ELASTICSEARCH.HEAP_USED.MAX.WARN}|<p>The maximum percent in the use of JVM heap for warning trigger expression.</p>|`85`|
|{$ELASTICSEARCH.HEAP_USED.MAX.CRIT}|<p>The maximum percent in the use of JVM heap for critically trigger expression.</p>|`95`|
|{$ELASTICSEARCH.SINGLE.NODE.JVM.SPACE.MIN}|<p>Minimum free space available to JVM in single node instance in bytes for trigger expression.</p>|`10G`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service status|<p>Checks if the service is running and accepting TCP connections.</p>|Simple check|net.tcp.service["{$ELASTICSEARCH.SCHEME}","{$ELASTICSEARCH.HOST}","{$ELASTICSEARCH.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Service response time|<p>Checks performance of the TCP service.</p>|Simple check|net.tcp.service.perf["{$ELASTICSEARCH.SCHEME}","{$ELASTICSEARCH.HOST}","{$ELASTICSEARCH.PORT}"]|
|Get cluster health|<p>Returns the health status of a cluster.</p>|HTTP agent|es.cluster.get_health|
|Cluster health status|<p>Health status of the cluster, based on the state of its primary and replica shards. Statuses are:</p><p>green</p><p>All shards are assigned.</p><p>yellow</p><p>All primary shards are assigned, but one or more replica shards are unassigned. If a node in the cluster fails, some data could be unavailable until that node is repaired.</p><p>red</p><p>One or more primary shards are unassigned, so some data is unavailable. This can occur briefly during cluster startup as primary shards are assigned.</p>|Dependent item|es.cluster.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Number of nodes|<p>The number of nodes within the cluster.</p>|Dependent item|es.cluster.number_of_nodes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.number_of_nodes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Number of data nodes|<p>The number of nodes that are dedicated to data nodes.</p>|Dependent item|es.cluster.number_of_data_nodes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.number_of_data_nodes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Number of relocating shards|<p>The number of shards that are under relocation.</p>|Dependent item|es.cluster.relocating_shards<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.relocating_shards`</p></li></ul>|
|Number of initializing shards|<p>The number of shards that are under initialization.</p>|Dependent item|es.cluster.initializing_shards<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.initializing_shards`</p></li></ul>|
|Number of unassigned shards|<p>The number of shards that are not allocated.</p>|Dependent item|es.cluster.unassigned_shards<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.unassigned_shards`</p></li></ul>|
|Delayed unassigned shards|<p>The number of shards whose allocation has been delayed by the timeout settings.</p>|Dependent item|es.cluster.delayed_unassigned_shards<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.delayed_unassigned_shards`</p></li></ul>|
|Number of pending tasks|<p>The number of cluster-level changes that have not yet been executed.</p>|Dependent item|es.cluster.number_of_pending_tasks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.number_of_pending_tasks`</p></li></ul>|
|Task max waiting in queue|<p>The time expressed in seconds since the earliest initiated task is waiting for being performed.</p>|Dependent item|es.cluster.task_max_waiting_in_queue<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.task_max_waiting_in_queue_millis`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Inactive shards percentage|<p>The ratio of inactive shards in the cluster expressed as a percentage.</p>|Dependent item|es.cluster.inactive_shards_percent_as_number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_shards_percent_as_number`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get cluster stats|<p>Returns cluster statistics.</p>|HTTP agent|es.cluster.get_stats|
|Cluster uptime|<p>Uptime duration in seconds since JVM has last started.</p>|Dependent item|es.nodes.jvm.max_uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nodes.jvm.max_uptime_in_millis`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Number of non-deleted documents|<p>The total number of non-deleted documents across all primary shards assigned to the selected nodes.</p><p>This number is based on the documents in Lucene segments and may include the documents from nested fields.</p>|Dependent item|es.indices.docs.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.indices.docs.count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Indices with shards assigned to nodes|<p>The total number of indices with shards assigned to the selected nodes.</p>|Dependent item|es.indices.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.indices.count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Total size of all file stores|<p>The total size in bytes of all file stores across all selected nodes.</p>|Dependent item|es.nodes.fs.total_in_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nodes.fs.total_in_bytes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Total available size to JVM in all file stores|<p>The total number of bytes available to JVM in the file stores across all selected nodes.</p><p>Depending on OS or process-level restrictions, this number may be less than nodes.fs.free_in_byes.</p><p>This is the actual amount of free disk space the selected Elasticsearch nodes can use.</p>|Dependent item|es.nodes.fs.available_in_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nodes.fs.available_in_bytes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nodes with the data role|<p>The number of selected nodes with the data role.</p>|Dependent item|es.nodes.count.data<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nodes.count.data`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nodes with the ingest role|<p>The number of selected nodes with the ingest role.</p>|Dependent item|es.nodes.count.ingest<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nodes.count.ingest`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nodes with the master role|<p>The number of selected nodes with the master role.</p>|Dependent item|es.nodes.count.master<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nodes.count.master`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get nodes stats|<p>Returns cluster nodes statistics.</p>|HTTP agent|es.nodes.get_stats|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Elasticsearch: Service is down|<p>The service is unavailable or does not accept TCP connections.</p>|`last(/Elasticsearch Cluster by HTTP/net.tcp.service["{$ELASTICSEARCH.SCHEME}","{$ELASTICSEARCH.HOST}","{$ELASTICSEARCH.PORT}"])=0`|Average|**Manual close**: Yes|
|Elasticsearch: Service response time is too high|<p>The performance of the TCP service is very low.</p>|`min(/Elasticsearch Cluster by HTTP/net.tcp.service.perf["{$ELASTICSEARCH.SCHEME}","{$ELASTICSEARCH.HOST}","{$ELASTICSEARCH.PORT}"],5m)>{$ELASTICSEARCH.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Elasticsearch: Service is down</li></ul>|
|Elasticsearch: Health is YELLOW|<p>All primary shards are assigned, but one or more replica shards are unassigned.<br>If a node in the cluster fails, some data could be unavailable until that node is repaired.</p>|`last(/Elasticsearch Cluster by HTTP/es.cluster.status)=1`|Average||
|Elasticsearch: Health is RED|<p>One or more primary shards are unassigned, so some data is unavailable.<br>This can occur briefly during cluster startup as primary shards are assigned.</p>|`last(/Elasticsearch Cluster by HTTP/es.cluster.status)=2`|High||
|Elasticsearch: Health is UNKNOWN|<p>The health status of the cluster is unknown or cannot be obtained.</p>|`last(/Elasticsearch Cluster by HTTP/es.cluster.status)=255`|High||
|Elasticsearch: The number of nodes within the cluster has decreased||`change(/Elasticsearch Cluster by HTTP/es.cluster.number_of_nodes)<0`|Info|**Manual close**: Yes|
|Elasticsearch: The number of nodes within the cluster has increased||`change(/Elasticsearch Cluster by HTTP/es.cluster.number_of_nodes)>0`|Info|**Manual close**: Yes|
|Elasticsearch: Cluster has the initializing shards|<p>The cluster has the initializing shards longer than 10 minutes.</p>|`min(/Elasticsearch Cluster by HTTP/es.cluster.initializing_shards,10m)>0`|Average||
|Elasticsearch: Cluster has the unassigned shards|<p>The cluster has the unassigned shards longer than 10 minutes.</p>|`min(/Elasticsearch Cluster by HTTP/es.cluster.unassigned_shards,10m)>0`|Average||
|Elasticsearch: Cluster has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Elasticsearch Cluster by HTTP/es.nodes.jvm.max_uptime)<10m`|Info|**Manual close**: Yes|
|Elasticsearch: Cluster does not have enough space for resharding|<p>There is not enough disk space for index resharding.</p>|`((last(/Elasticsearch Cluster by HTTP/es.nodes.fs.total_in_bytes) - last(/Elasticsearch Cluster by HTTP/es.nodes.fs.available_in_bytes)) / (last(/Elasticsearch Cluster by HTTP/es.cluster.number_of_data_nodes) - (last(/Elasticsearch Cluster by HTTP/es.cluster.number_of_data_nodes) >= 2)) > last(/Elasticsearch Cluster by HTTP/es.nodes.fs.available_in_bytes)) and (last(/Elasticsearch Cluster by HTTP/es.cluster.number_of_data_nodes) > 1)`|High||
|Elasticsearch: Cluster does not have enough space (single node)|<p>The total number of bytes available to JVM in the file store is less than `{$ELASTICSEARCH.SINGLE.NODE.JVM.SPACE.MIN}`.<br>This is the actual amount of free disk space the selected Elasticsearch node can use.</p>|`(last(/Elasticsearch Cluster by HTTP/es.nodes.fs.available_in_bytes) < {$ELASTICSEARCH.SINGLE.NODE.JVM.SPACE.MIN}) and last(/Elasticsearch Cluster by HTTP/es.cluster.number_of_data_nodes) = 1`|High||
|Elasticsearch: Cluster has only two master nodes|<p>The cluster has only two nodes with a master role and will be unavailable if one of them breaks.</p>|`last(/Elasticsearch Cluster by HTTP/es.nodes.count.master)=2`|Disaster||

### LLD rule Cluster nodes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster nodes discovery|<p>Discovery ES cluster nodes.</p>|HTTP agent|es.nodes.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nodes.[*]`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Cluster nodes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ES {#ES.NODE}: Get data|<p>Returns cluster nodes statistics.</p>|Dependent item|es.node.get.data[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..[?(@.name=='{#ES.NODE}')].first()`</p></li></ul>|
|ES {#ES.NODE}: Total size|<p>Total size (in bytes) of all file stores.</p>|Dependent item|es.node.fs.total.total_in_bytes[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..fs.total.total_in_bytes.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|ES {#ES.NODE}: Total available size|<p>The total number of bytes available to this Java virtual machine on all file stores.</p><p>Depending on OS or process level restrictions, this might appear less than fs.total.free_in_bytes.</p><p>This is the actual amount of free disk space the Elasticsearch node can utilize.</p>|Dependent item|es.node.fs.total.available_in_bytes[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..fs.total.available_in_bytes.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Node uptime|<p>JVM uptime in seconds.</p>|Dependent item|es.node.jvm.uptime[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..jvm.uptime_in_millis.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|ES {#ES.NODE}: Maximum JVM memory available for use|<p>The maximum amount of memory, in bytes, available for use by the heap.</p>|Dependent item|es.node.jvm.mem.heap_max_in_bytes[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..jvm.mem.heap_max_in_bytes.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|ES {#ES.NODE}: Amount of JVM heap currently in use|<p>The memory, in bytes, currently in use by the heap.</p>|Dependent item|es.node.jvm.mem.heap_used_in_bytes[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..jvm.mem.heap_used_in_bytes.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Percent of JVM heap currently in use|<p>The percentage of memory currently in use by the heap.</p>|Dependent item|es.node.jvm.mem.heap_used_percent[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..jvm.mem.heap_used_percent.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Amount of JVM heap committed|<p>The amount of memory, in bytes, available for use by the heap.</p>|Dependent item|es.node.jvm.mem.heap_committed_in_bytes[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..jvm.mem.heap_committed_in_bytes.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Number of open HTTP connections|<p>The number of currently open HTTP connections for the node.</p>|Dependent item|es.node.http.current_open[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..http.current_open.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Rate of HTTP connections opened|<p>The number of HTTP connections opened for the node per second.</p>|Dependent item|es.node.http.opened.rate[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..http.total_opened.first()`</p></li><li>Change per second</li></ul>|
|ES {#ES.NODE}: Time spent throttling operations|<p>Time in seconds spent throttling operations for the last measuring span.</p>|Dependent item|es.node.indices.indexing.throttle_time[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.indexing.throttle_time_in_millis.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Simple change</li></ul>|
|ES {#ES.NODE}: Time spent throttling recovery operations|<p>Time in seconds spent throttling recovery operations for the last measuring span.</p>|Dependent item|es.node.indices.recovery.throttle_time[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.recovery.throttle_time_in_millis.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Simple change</li></ul>|
|ES {#ES.NODE}: Time spent throttling merge operations|<p>Time in seconds spent throttling merge operations for the last measuring span.</p>|Dependent item|es.node.indices.merges.total_throttled_time[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.merges.total_throttled_time_in_millis.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Simple change</li></ul>|
|ES {#ES.NODE}: Rate of queries|<p>The number of query operations per second.</p>|Dependent item|es.node.indices.search.query.rate[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.search.query_total.first()`</p></li><li>Change per second</li></ul>|
|ES {#ES.NODE}: Total number of query|<p>The total number of query operations.</p>|Dependent item|es.node.indices.search.query_total[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.search.query_total.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Time spent performing query|<p>Time in seconds spent performing query operations for the last measuring span.</p>|Dependent item|es.node.indices.search.query_time[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.search.query_time_in_millis.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Simple change</li></ul>|
|ES {#ES.NODE}: Total time spent performing query|<p>Time in milliseconds spent performing query operations.</p>|Dependent item|es.node.indices.search.query_time_in_millis[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.search.query_time_in_millis.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Query latency|<p>The average query latency calculated by sampling the total number of queries and the total elapsed time at regular intervals.</p>|Calculated|es.node.indices.search.query_latency[{#ES.NODE}]|
|ES {#ES.NODE}: Current query operations|<p>The number of query operations currently running.</p>|Dependent item|es.node.indices.search.query_current[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.search.query_current.first()`</p></li></ul>|
|ES {#ES.NODE}: Rate of fetch|<p>The number of fetch operations per second.</p>|Dependent item|es.node.indices.search.fetch.rate[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.search.fetch_total.first()`</p></li><li>Change per second</li></ul>|
|ES {#ES.NODE}: Total number of fetch|<p>The total number of fetch operations.</p>|Dependent item|es.node.indices.search.fetch_total[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.search.fetch_total.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Time spent performing fetch|<p>Time in seconds spent performing fetch operations for the last measuring span.</p>|Dependent item|es.node.indices.search.fetch_time[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.search.fetch_time_in_millis.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Simple change</li></ul>|
|ES {#ES.NODE}: Total time spent performing fetch|<p>Time in milliseconds spent performing fetch operations.</p>|Dependent item|es.node.indices.search.fetch_time_in_millis[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.search.fetch_time_in_millis.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Fetch latency|<p>The average fetch latency calculated by sampling the total number of fetches and the total elapsed time at regular intervals.</p>|Calculated|es.node.indices.search.fetch_latency[{#ES.NODE}]|
|ES {#ES.NODE}: Current fetch operations|<p>The number of fetch operations currently running.</p>|Dependent item|es.node.indices.search.fetch_current[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.search.fetch_current.first()`</p></li></ul>|
|ES {#ES.NODE}: Write thread pool executor tasks completed|<p>The number of tasks completed by the write thread pool executor.</p>|Dependent item|es.node.thread_pool.write.completed.rate[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.write.completed.first()`</p></li><li>Change per second</li></ul>|
|ES {#ES.NODE}: Write thread pool active threads|<p>The number of active threads in the write thread pool.</p>|Dependent item|es.node.thread_pool.write.active[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.write.active.first()`</p></li></ul>|
|ES {#ES.NODE}: Write thread pool tasks in queue|<p>The number of tasks in queue for the write thread pool.</p>|Dependent item|es.node.thread_pool.write.queue[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.write.queue.first()`</p></li></ul>|
|ES {#ES.NODE}: Write thread pool executor tasks rejected|<p>The number of tasks rejected by the write thread pool executor.</p>|Dependent item|es.node.thread_pool.write.rejected.rate[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.write.rejected.first()`</p></li><li>Change per second</li></ul>|
|ES {#ES.NODE}: Search thread pool executor tasks completed|<p>The number of tasks completed by the search thread pool executor.</p>|Dependent item|es.node.thread_pool.search.completed.rate[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.search.completed.first()`</p></li><li>Change per second</li></ul>|
|ES {#ES.NODE}: Search thread pool active threads|<p>The number of active threads in the search thread pool.</p>|Dependent item|es.node.thread_pool.search.active[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.search.active.first()`</p></li></ul>|
|ES {#ES.NODE}: Search thread pool tasks in queue|<p>The number of tasks in queue for the search thread pool.</p>|Dependent item|es.node.thread_pool.search.queue[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.search.queue.first()`</p></li></ul>|
|ES {#ES.NODE}: Search thread pool executor tasks rejected|<p>The number of tasks rejected by the search thread pool executor.</p>|Dependent item|es.node.thread_pool.search.rejected.rate[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.search.rejected.first()`</p></li><li>Change per second</li></ul>|
|ES {#ES.NODE}: Refresh thread pool executor tasks completed|<p>The number of tasks completed by the refresh thread pool executor.</p>|Dependent item|es.node.thread_pool.refresh.completed.rate[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.refresh.completed.first()`</p></li><li>Change per second</li></ul>|
|ES {#ES.NODE}: Refresh thread pool active threads|<p>The number of active threads in the refresh thread pool.</p>|Dependent item|es.node.thread_pool.refresh.active[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.refresh.active.first()`</p></li></ul>|
|ES {#ES.NODE}: Refresh thread pool tasks in queue|<p>The number of tasks in queue for the refresh thread pool.</p>|Dependent item|es.node.thread_pool.refresh.queue[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.refresh.queue.first()`</p></li></ul>|
|ES {#ES.NODE}: Refresh thread pool executor tasks rejected|<p>The number of tasks rejected by the refresh thread pool executor.</p>|Dependent item|es.node.thread_pool.refresh.rejected.rate[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..thread_pool.refresh.rejected.first()`</p></li><li>Change per second</li></ul>|
|ES {#ES.NODE}: Total number of indexing|<p>The total number of indexing operations.</p>|Dependent item|es.node.indices.indexing.index_total[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.indexing.index_total.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Total time spent performing indexing|<p>Total time in milliseconds spent performing indexing operations.</p>|Dependent item|es.node.indices.indexing.index_time_in_millis[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.indexing.index_time_in_millis.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Indexing latency|<p>The average indexing latency calculated from the available index_total and index_time_in_millis metrics.</p>|Calculated|es.node.indices.indexing.index_latency[{#ES.NODE}]|
|ES {#ES.NODE}: Current indexing operations|<p>The number of indexing operations currently running.</p>|Dependent item|es.node.indices.indexing.index_current[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.indexing.index_current.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Total number of index flushes to disk|<p>The total number of flush operations.</p>|Dependent item|es.node.indices.flush.total[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.flush.total.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Total time spent on flushing indices to disk|<p>Total time in milliseconds spent performing flush operations.</p>|Dependent item|es.node.indices.flush.total_time_in_millis[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.flush.total_time_in_millis.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ES {#ES.NODE}: Flush latency|<p>The average flush latency calculated from the available flush.total and flush.total_time_in_millis metrics.</p>|Calculated|es.node.indices.flush.latency[{#ES.NODE}]|
|ES {#ES.NODE}: Rate of index refreshes|<p>The number of refresh operations per second.</p>|Dependent item|es.node.indices.refresh.rate[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.refresh.total.first()`</p></li><li>Change per second</li></ul>|
|ES {#ES.NODE}: Time spent performing refresh|<p>Time in seconds spent performing refresh operations for the last measuring span.</p>|Dependent item|es.node.indices.refresh.time[{#ES.NODE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..indices.refresh.total_time_in_millis.first()`</p></li><li><p>Custom multiplier: `0.001`</p></li><li>Simple change</li></ul>|

### Trigger prototypes for Cluster nodes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Elasticsearch: ES {#ES.NODE}: Node has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Elasticsearch Cluster by HTTP/es.node.jvm.uptime[{#ES.NODE}])<10m`|Info|**Manual close**: Yes|
|Elasticsearch: ES {#ES.NODE}: Percent of JVM heap in use is high|<p>This indicates that the rate of garbage collection isn't keeping up with the rate of garbage creation.<br>To address this problem, you can either increase your heap size (as long as it remains below the recommended<br>guidelines stated above), or scale out the cluster by adding more nodes.</p>|`min(/Elasticsearch Cluster by HTTP/es.node.jvm.mem.heap_used_percent[{#ES.NODE}],1h)>{$ELASTICSEARCH.HEAP_USED.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Elasticsearch: ES {#ES.NODE}: Percent of JVM heap in use is critical</li></ul>|
|Elasticsearch: ES {#ES.NODE}: Percent of JVM heap in use is critical|<p>This indicates that the rate of garbage collection isn't keeping up with the rate of garbage creation.<br>To address this problem, you can either increase your heap size (as long as it remains below the recommended<br>guidelines stated above), or scale out the cluster by adding more nodes.</p>|`min(/Elasticsearch Cluster by HTTP/es.node.jvm.mem.heap_used_percent[{#ES.NODE}],1h)>{$ELASTICSEARCH.HEAP_USED.MAX.CRIT}`|High||
|Elasticsearch: ES {#ES.NODE}: Query latency is too high|<p>If latency exceeds a threshold, look for potential resource bottlenecks, or investigate whether you need to optimize your queries.</p>|`min(/Elasticsearch Cluster by HTTP/es.node.indices.search.query_latency[{#ES.NODE}],5m)>{$ELASTICSEARCH.QUERY_LATENCY.MAX.WARN}`|Warning||
|Elasticsearch: ES {#ES.NODE}: Fetch latency is too high|<p>The fetch phase should typically take much less time than the query phase. If you notice this metric consistently increasing,<br>this could indicate a problem with slow disks, enriching of documents (highlighting the relevant text in search results, etc.),<br>or requesting too many results.</p>|`min(/Elasticsearch Cluster by HTTP/es.node.indices.search.fetch_latency[{#ES.NODE}],5m)>{$ELASTICSEARCH.FETCH_LATENCY.MAX.WARN}`|Warning||
|Elasticsearch: ES {#ES.NODE}: Write thread pool executor has the rejected tasks|<p>The number of tasks rejected by the write thread pool executor is over 0 for 5m.</p>|`min(/Elasticsearch Cluster by HTTP/es.node.thread_pool.write.rejected.rate[{#ES.NODE}],5m)>0`|Warning||
|Elasticsearch: ES {#ES.NODE}: Search thread pool executor has the rejected tasks|<p>The number of tasks rejected by the search thread pool executor is over 0 for 5m.</p>|`min(/Elasticsearch Cluster by HTTP/es.node.thread_pool.search.rejected.rate[{#ES.NODE}],5m)>0`|Warning||
|Elasticsearch: ES {#ES.NODE}: Refresh thread pool executor has the rejected tasks|<p>The number of tasks rejected by the refresh thread pool executor is over 0 for 5m.</p>|`min(/Elasticsearch Cluster by HTTP/es.node.thread_pool.refresh.rejected.rate[{#ES.NODE}],5m)>0`|Warning||
|Elasticsearch: ES {#ES.NODE}: Indexing latency is too high|<p>If the latency is increasing, it may indicate that you are indexing too many documents at the same time (Elasticsearch's documentation<br>recommends starting with a bulk indexing size of 5 to 15 megabytes and increasing slowly from there).</p>|`min(/Elasticsearch Cluster by HTTP/es.node.indices.indexing.index_latency[{#ES.NODE}],5m)>{$ELASTICSEARCH.INDEXING_LATENCY.MAX.WARN}`|Warning||
|Elasticsearch: ES {#ES.NODE}: Flush latency is too high|<p>If you see this metric increasing steadily, it may indicate a problem with slow disks; this problem may escalate<br>and eventually prevent you from being able to add new information to your index.</p>|`min(/Elasticsearch Cluster by HTTP/es.node.indices.flush.latency[{#ES.NODE}],5m)>{$ELASTICSEARCH.FLUSH_LATENCY.MAX.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

