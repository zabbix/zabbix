
# Elasticsearch Cluster by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Elasticsearch by Zabbix that work without any external scripts.
It works with both standalone and cluster instances.
The metrics are collected in one pass remotely using an HTTP agent.
They are getting values from REST API _cluster/health, _cluster/stats, _nodes/stats requests.


This template was tested on:

- Elasticsearch, version 6.5..7.6

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

You can set {$ELASTICSEARCH.USERNAME} and {$ELASTICSEARCH.PASSWORD} macros in the template for using on the host level.
If you use an atypical location ES API, don't forget to change the macros {$ELASTICSEARCH.SCHEME},{$ELASTICSEARCH.PORT}.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ELASTICSEARCH.FETCH_LATENCY.MAX.WARN} |<p>Maximum of fetch latency in milliseconds for trigger expression.</p> |`100` |
|{$ELASTICSEARCH.FLUSH_LATENCY.MAX.WARN} |<p>Maximum of flush latency in milliseconds for trigger expression.</p> |`100` |
|{$ELASTICSEARCH.HEAP_USED.MAX.CRIT} |<p>The maximum percent in the use of JVM heap for critically trigger expression.</p> |`95` |
|{$ELASTICSEARCH.HEAP_USED.MAX.WARN} |<p>The maximum percent in the use of JVM heap for warning trigger expression.</p> |`85` |
|{$ELASTICSEARCH.INDEXING_LATENCY.MAX.WARN} |<p>Maximum of indexing latency in milliseconds for trigger expression.</p> |`100` |
|{$ELASTICSEARCH.PASSWORD} |<p>The password of the Elasticsearch.</p> |`` |
|{$ELASTICSEARCH.PORT} |<p>The port of the Elasticsearch host.</p> |`9200` |
|{$ELASTICSEARCH.QUERY_LATENCY.MAX.WARN} |<p>Maximum of query latency in milliseconds for trigger expression.</p> |`100` |
|{$ELASTICSEARCH.RESPONSE_TIME.MAX.WARN} |<p>The ES cluster maximum response time in seconds for trigger expression.</p> |`10s` |
|{$ELASTICSEARCH.SCHEME} |<p>The scheme of the Elasticsearch (http/https).</p> |`http` |
|{$ELASTICSEARCH.USERNAME} |<p>The username of the Elasticsearch.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Cluster nodes discovery |<p>Discovery ES cluster nodes.</p> |HTTP_AGENT |es.nodes.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.nodes.[*]`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|ES cluster |ES: Service status |<p>Checks if the service is running and accepting TCP connections.</p> |SIMPLE |net.tcp.service["{$ELASTICSEARCH.SCHEME}","{HOST.CONN}","{$ELASTICSEARCH.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|ES cluster |ES: Service response time |<p>Checks performance of the TCP service.</p> |SIMPLE |net.tcp.service.perf["{$ELASTICSEARCH.SCHEME}","{HOST.CONN}","{$ELASTICSEARCH.PORT}"] |
|ES cluster |ES: Cluster health status |<p>Health status of the cluster, based on the state of its primary and replica shards. Statuses are:</p><p>green</p><p>All shards are assigned.</p><p>yellow</p><p>All primary shards are assigned, but one or more replica shards are unassigned. If a node in the cluster fails, some data could be unavailable until that node is repaired.</p><p>red</p><p>One or more primary shards are unassigned, so some data is unavailable. This can occur briefly during cluster startup as primary shards are assigned.</p> |DEPENDENT |es.cluster.status<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES: Number of nodes |<p>The number of nodes within the cluster.</p> |DEPENDENT |es.cluster.number_of_nodes<p>**Preprocessing**:</p><p>- JSONPATH: `$.number_of_nodes`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES: Number of data nodes |<p>The number of nodes that are dedicated to data nodes.</p> |DEPENDENT |es.cluster.number_of_data_nodes<p>**Preprocessing**:</p><p>- JSONPATH: `$.number_of_data_nodes`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES: Number of relocating shards |<p>The number of shards that are under relocation.</p> |DEPENDENT |es.cluster.relocating_shards<p>**Preprocessing**:</p><p>- JSONPATH: `$.relocating_shards`</p> |
|ES cluster |ES: Number of initializing shards |<p>The number of shards that are under initialization.</p> |DEPENDENT |es.cluster.initializing_shards<p>**Preprocessing**:</p><p>- JSONPATH: `$.initializing_shards`</p> |
|ES cluster |ES: Number of unassigned shards |<p>The number of shards that are not allocated.</p> |DEPENDENT |es.cluster.unassigned_shards<p>**Preprocessing**:</p><p>- JSONPATH: `$.unassigned_shards`</p> |
|ES cluster |ES: Delayed unassigned shards |<p>The number of shards whose allocation has been delayed by the timeout settings.</p> |DEPENDENT |es.cluster.delayed_unassigned_shards<p>**Preprocessing**:</p><p>- JSONPATH: `$.delayed_unassigned_shards`</p> |
|ES cluster |ES: Number of pending tasks |<p>The number of cluster-level changes that have not yet been executed.</p> |DEPENDENT |es.cluster.number_of_pending_tasks<p>**Preprocessing**:</p><p>- JSONPATH: `$.number_of_pending_tasks`</p> |
|ES cluster |ES: Task max waiting in queue |<p>The time expressed in seconds since the earliest initiated task is waiting for being performed.</p> |DEPENDENT |es.cluster.task_max_waiting_in_queue<p>**Preprocessing**:</p><p>- JSONPATH: `$.task_max_waiting_in_queue_millis`</p><p>- MULTIPLIER: `0.001`</p> |
|ES cluster |ES: Inactive shards percentage |<p>The ratio of inactive shards in the cluster expressed as a percentage.</p> |DEPENDENT |es.cluster.inactive_shards_percent_as_number<p>**Preprocessing**:</p><p>- JSONPATH: `$.active_shards_percent_as_number`</p><p>- JAVASCRIPT: `return (100 - value)`</p> |
|ES cluster |ES: Cluster uptime |<p>Uptime duration in seconds since JVM has last started.</p> |DEPENDENT |es.nodes.jvm.max_uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.nodes.jvm.max_uptime_in_millis`</p><p>- MULTIPLIER: `0.001`</p> |
|ES cluster |ES: Number of non-deleted documents |<p>The total number of non-deleted documents across all primary shards assigned to the selected nodes.</p><p>This number is based on the documents in Lucene segments and may include the documents from nested fields.</p> |DEPENDENT |es.indices.docs.count<p>**Preprocessing**:</p><p>- JSONPATH: `$.indices.docs.count`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES: Indices with shards assigned to nodes |<p>The total number of indices with shards assigned to the selected nodes.</p> |DEPENDENT |es.indices.count<p>**Preprocessing**:</p><p>- JSONPATH: `$.indices.count`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES: Total size of all file stores |<p>The total size in bytes of all file stores across all selected nodes.</p> |DEPENDENT |es.nodes.fs.total_in_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.nodes.fs.total_in_bytes`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES: Total available size to JVM in all file stores |<p>The total number of bytes available to JVM in the file stores across all selected nodes.</p><p>Depending on OS or process-level restrictions, this number may be less than nodes.fs.free_in_byes.</p><p>This is the actual amount of free disk space the selected Elasticsearch nodes can use.</p> |DEPENDENT |es.nodes.fs.available_in_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.nodes.fs.available_in_bytes`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES: Nodes with the data role |<p>The number of selected nodes with the data role.</p> |DEPENDENT |es.nodes.count.data<p>**Preprocessing**:</p><p>- JSONPATH: `$.nodes.count.data`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES: Nodes with the ingest role |<p>The number of selected nodes with the ingest role.</p> |DEPENDENT |es.nodes.count.ingest<p>**Preprocessing**:</p><p>- JSONPATH: `$.nodes.count.ingest`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES: Nodes with the master role |<p>The number of selected nodes with the master role.</p> |DEPENDENT |es.nodes.count.master<p>**Preprocessing**:</p><p>- JSONPATH: `$.nodes.count.master`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES {#ES.NODE}: Total size |<p>Total size (in bytes) of all file stores.</p> |DEPENDENT |es.node.fs.total.total_in_bytes[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].fs.total.total_in_bytes.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|ES cluster |ES {#ES.NODE}: Total available size |<p>The total number of bytes available to this Java virtual machine on all file stores.</p><p>Depending on OS or process level restrictions, this might appear less than fs.total.free_in_bytes.</p><p>This is the actual amount of free disk space the Elasticsearch node can utilize.</p> |DEPENDENT |es.node.fs.total.available_in_bytes[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].fs.total.available_in_bytes.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES {#ES.NODE}: Node uptime |<p>JVM uptime in seconds.</p> |DEPENDENT |es.node.jvm.uptime[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].jvm.uptime_in_millis.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|ES cluster |ES {#ES.NODE}: Maximum JVM memory available for use |<p>The maximum amount of memory, in bytes, available for use by the heap.</p> |DEPENDENT |es.node.jvm.mem.heap_max_in_bytes[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].jvm.mem.heap_max_in_bytes.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|ES cluster |ES {#ES.NODE}: Amount of JVM heap currently in use |<p>The memory, in bytes, currently in use by the heap.</p> |DEPENDENT |es.node.jvm.mem.heap_used_in_bytes[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].jvm.mem.heap_used_in_bytes.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES {#ES.NODE}: Percent of JVM heap currently in use |<p>The percentage of memory currently in use by the heap.</p> |DEPENDENT |es.node.jvm.mem.heap_used_percent[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].jvm.mem.heap_used_percent.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES {#ES.NODE}: Amount of JVM heap committed |<p>The amount of memory, in bytes, available for use by the heap.</p> |DEPENDENT |es.node.jvm.mem.heap_committed_in_bytes[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].jvm.mem.heap_committed_in_bytes.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES {#ES.NODE}: Number of open HTTP connections |<p>The number of currently open HTTP connections for the node.</p> |DEPENDENT |es.node.http.current_open[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].http.current_open.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES {#ES.NODE}: Rate of HTTP connections opened |<p>The number of HTTP connections opened for the node per second.</p> |DEPENDENT |es.node.http.opened.rate[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].http.total_opened.first()`</p><p>- CHANGE_PER_SECOND</p> |
|ES cluster |ES {#ES.NODE}: Time spent throttling operations |<p>Time in seconds spent throttling operations for the last measuring span.</p> |DEPENDENT |es.node.indices.indexing.throttle_time[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.indexing.throttle_time_in_millis.first()`</p><p>- MULTIPLIER: `0.001`</p><p>- SIMPLE_CHANGE</p> |
|ES cluster |ES {#ES.NODE}: Time spent throttling recovery operations |<p>Time in seconds spent throttling recovery operations for the last measuring span.</p> |DEPENDENT |es.node.indices.recovery.throttle_time[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.recovery.throttle_time_in_millis.first()`</p><p>- MULTIPLIER: `0.001`</p><p>- SIMPLE_CHANGE</p> |
|ES cluster |ES {#ES.NODE}: Time spent throttling merge operations |<p>Time in seconds spent throttling merge operations for the last measuring span.</p> |DEPENDENT |es.node.indices.merges.total_throttled_time[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.merges.total_throttled_time_in_millis.first()`</p><p>- MULTIPLIER: `0.001`</p><p>- SIMPLE_CHANGE</p> |
|ES cluster |ES {#ES.NODE}: Rate of queries |<p>The number of query operations per second.</p> |DEPENDENT |es.node.indices.search.query.rate[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.search.query_total.first()`</p><p>- CHANGE_PER_SECOND</p> |
|ES cluster |ES {#ES.NODE}: Time spent performing query |<p>Time in seconds spent performing query operations for the last measuring span.</p> |DEPENDENT |es.node.indices.search.query_time[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.search.query_time_in_millis.first()`</p><p>- MULTIPLIER: `0.001`</p><p>- SIMPLE_CHANGE</p> |
|ES cluster |ES {#ES.NODE}: Query latency |<p>The average query latency calculated by sampling the total number of queries and the total elapsed time at regular intervals.</p> |CALCULATED |es.node.indices.search.query_latency[{#ES.NODE}]<p>**Expression**:</p>`change(//es.node.indices.search.query_time_in_millis[{#ES.NODE}]) /  ( change(//es.node.indices.search.query_total[{#ES.NODE}]) + (change(//es.node.indices.search.query_total[{#ES.NODE}]) = 0) ) ` |
|ES cluster |ES {#ES.NODE}: Current query operations |<p>The number of query operations currently running.</p> |DEPENDENT |es.node.indices.search.query_current[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.search.query_current.first()`</p> |
|ES cluster |ES {#ES.NODE}: Rate of fetch |<p>The number of fetch operations per second.</p> |DEPENDENT |es.node.indices.search.fetch.rate[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.search.fetch_total.first()`</p><p>- CHANGE_PER_SECOND</p> |
|ES cluster |ES {#ES.NODE}: Time spent performing fetch |<p>Time in seconds spent performing fetch operations for the last measuring span.</p> |DEPENDENT |es.node.indices.search.fetch_time[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.search.fetch_time_in_millis.first()`</p><p>- MULTIPLIER: `0.001`</p><p>- SIMPLE_CHANGE</p> |
|ES cluster |ES {#ES.NODE}: Fetch latency |<p>The average fetch latency calculated by sampling the total number of fetches and the total elapsed time at regular intervals.</p> |CALCULATED |es.node.indices.search.fetch_latency[{#ES.NODE}]<p>**Expression**:</p>`change(//es.node.indices.search.fetch_time_in_millis[{#ES.NODE}]) / ( change(//es.node.indices.search.fetch_total[{#ES.NODE}]) + (change(//es.node.indices.search.fetch_total[{#ES.NODE}]) = 0) )` |
|ES cluster |ES {#ES.NODE}: Current fetch operations |<p>The number of fetch operations currently running.</p> |DEPENDENT |es.node.indices.search.fetch_current[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.search.fetch_current.first()`</p> |
|ES cluster |ES {#ES.NODE}: Write thread pool executor tasks completed |<p>The number of tasks completed by the write thread pool executor.</p> |DEPENDENT |es.node.thread_pool.write.completed.rate[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.write.completed.first()`</p><p>- CHANGE_PER_SECOND</p> |
|ES cluster |ES {#ES.NODE}: Write thread pool active threads |<p>The number of active threads in the write thread pool.</p> |DEPENDENT |es.node.thread_pool.write.active[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.write.active.first()`</p> |
|ES cluster |ES {#ES.NODE}: Write thread pool tasks in queue |<p>The number of tasks in queue for the write thread pool.</p> |DEPENDENT |es.node.thread_pool.write.queue[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.write.queue.first()`</p> |
|ES cluster |ES {#ES.NODE}: Write thread pool executor tasks rejected |<p>The number of tasks rejected by the write thread pool executor.</p> |DEPENDENT |es.node.thread_pool.write.rejected.rate[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.write.rejected.first()`</p><p>- CHANGE_PER_SECOND</p> |
|ES cluster |ES {#ES.NODE}: Search thread pool executor tasks completed |<p>The number of tasks completed by the search thread pool executor.</p> |DEPENDENT |es.node.thread_pool.search.completed.rate[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.search.completed.first()`</p><p>- CHANGE_PER_SECOND</p> |
|ES cluster |ES {#ES.NODE}: Search thread pool active threads |<p>The number of active threads in the search thread pool.</p> |DEPENDENT |es.node.thread_pool.search.active[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.search.active.first()`</p> |
|ES cluster |ES {#ES.NODE}: Search thread pool tasks in queue |<p>The number of tasks in queue for the search thread pool.</p> |DEPENDENT |es.node.thread_pool.search.queue[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.search.queue.first()`</p> |
|ES cluster |ES {#ES.NODE}: Search thread pool executor tasks rejected |<p>The number of tasks rejected by the search thread pool executor.</p> |DEPENDENT |es.node.thread_pool.search.rejected.rate[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.search.rejected.first()`</p><p>- CHANGE_PER_SECOND</p> |
|ES cluster |ES {#ES.NODE}: Refresh thread pool executor tasks completed |<p>The number of tasks completed by the refresh thread pool executor.</p> |DEPENDENT |es.node.thread_pool.refresh.completed.rate[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.refresh.completed.first()`</p><p>- CHANGE_PER_SECOND</p> |
|ES cluster |ES {#ES.NODE}: Refresh thread pool active threads |<p>The number of active threads in the refresh thread pool.</p> |DEPENDENT |es.node.thread_pool.refresh.active[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.refresh.active.first()`</p> |
|ES cluster |ES {#ES.NODE}: Refresh thread pool tasks in queue |<p>The number of tasks in queue for the refresh thread pool.</p> |DEPENDENT |es.node.thread_pool.refresh.queue[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.refresh.queue.first()`</p> |
|ES cluster |ES {#ES.NODE}: Refresh thread pool executor tasks rejected |<p>The number of tasks rejected by the refresh thread pool executor.</p> |DEPENDENT |es.node.thread_pool.refresh.rejected.rate[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].thread_pool.refresh.rejected.first()`</p><p>- CHANGE_PER_SECOND</p> |
|ES cluster |ES {#ES.NODE}: Indexing latency |<p>The average indexing latency calculated from the available index_total and index_time_in_millis metrics.</p> |CALCULATED |es.node.indices.indexing.index_latency[{#ES.NODE}]<p>**Expression**:</p>`change(//es.node.indices.indexing.index_time_in_millis[{#ES.NODE}]) / ( change(//es.node.indices.indexing.index_total[{#ES.NODE}]) + (change(//es.node.indices.indexing.index_total[{#ES.NODE}]) = 0) )` |
|ES cluster |ES {#ES.NODE}: Current indexing operations |<p>The number of indexing operations currently running.</p> |DEPENDENT |es.node.indices.indexing.index_current[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.indexing.index_current.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|ES cluster |ES {#ES.NODE}: Flush latency |<p>The average flush latency calculated from the available flush.total and flush.total_time_in_millis metrics.</p> |CALCULATED |es.node.indices.flush.latency[{#ES.NODE}]<p>**Expression**:</p>`change(//es.node.indices.flush.total_time_in_millis[{#ES.NODE}]) / ( change(//es.node.indices.flush.total[{#ES.NODE}]) + (change(//es.node.indices.flush.total[{#ES.NODE}]) = 0) )` |
|ES cluster |ES {#ES.NODE}: Rate of index refreshes |<p>The number of refresh operations per second.</p> |DEPENDENT |es.node.indices.refresh.rate[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.refresh.total.first()`</p><p>- CHANGE_PER_SECOND</p> |
|ES cluster |ES {#ES.NODE}: Time spent performing refresh |<p>Time in seconds spent performing refresh operations for the last measuring span.</p> |DEPENDENT |es.node.indices.refresh.time[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.refresh.total_time_in_millis.first()`</p><p>- MULTIPLIER: `0.001`</p><p>- SIMPLE_CHANGE</p> |
|Zabbix raw items |ES: Get cluster health |<p>Returns the health status of a cluster.</p> |HTTP_AGENT |es.cluster.get_health |
|Zabbix raw items |ES: Get cluster stats |<p>Returns cluster statistics.</p> |HTTP_AGENT |es.cluster.get_stats |
|Zabbix raw items |ES: Get nodes stats |<p>Returns cluster nodes statistics.</p> |HTTP_AGENT |es.nodes.get_stats |
|Zabbix raw items |ES {#ES.NODE}: Total number of query |<p>The total number of query operations.</p> |DEPENDENT |es.node.indices.search.query_total[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.search.query_total.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |ES {#ES.NODE}: Total time spent performing query |<p>Time in milliseconds spent performing query operations.</p> |DEPENDENT |es.node.indices.search.query_time_in_millis[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.search.query_time_in_millis.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |ES {#ES.NODE}: Total number of fetch |<p>The total number of fetch operations.</p> |DEPENDENT |es.node.indices.search.fetch_total[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.search.fetch_total.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |ES {#ES.NODE}: Total time spent performing fetch |<p>Time in milliseconds spent performing fetch operations.</p> |DEPENDENT |es.node.indices.search.fetch_time_in_millis[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.search.fetch_time_in_millis.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |ES {#ES.NODE}: Total number of indexing |<p>The total number of indexing operations.</p> |DEPENDENT |es.node.indices.indexing.index_total[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.indexing.index_total.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |ES {#ES.NODE}: Total time spent performing indexing |<p>Total time in milliseconds spent performing indexing operations.</p> |DEPENDENT |es.node.indices.indexing.index_time_in_millis[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.indexing.index_time_in_millis.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |ES {#ES.NODE}: Total number of index flushes to disk |<p>The total number of flush operations.</p> |DEPENDENT |es.node.indices.flush.total[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.flush.total.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |ES {#ES.NODE}: Total time spent on flushing indices to disk |<p>Total time in milliseconds spent performing flush operations.</p> |DEPENDENT |es.node.indices.flush.total_time_in_millis[{#ES.NODE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..[?(@.name=='{#ES.NODE}')].indices.flush.total_time_in_millis.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|ES: Service is down |<p>The service is unavailable or does not accept TCP connections.</p> |`last(/Elasticsearch Cluster by HTTP/net.tcp.service["{$ELASTICSEARCH.SCHEME}","{HOST.CONN}","{$ELASTICSEARCH.PORT}"])=0` |AVERAGE |<p>Manual close: YES</p> |
|ES: Service response time is too high |<p>The performance of the TCP service is very low.</p> |`min(/Elasticsearch Cluster by HTTP/net.tcp.service.perf["{$ELASTICSEARCH.SCHEME}","{HOST.CONN}","{$ELASTICSEARCH.PORT}"],5m)>{$ELASTICSEARCH.RESPONSE_TIME.MAX.WARN}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- ES: Service is down</p> |
|ES: Health is YELLOW |<p>All primary shards are assigned, but one or more replica shards are unassigned.</p><p>If a node in the cluster fails, some data could be unavailable until that node is repaired.</p> |`last(/Elasticsearch Cluster by HTTP/es.cluster.status)=1` |AVERAGE | |
|ES: Health is RED |<p>One or more primary shards are unassigned, so some data is unavailable.</p><p>This can occur briefly during cluster startup as primary shards are assigned.</p> |`last(/Elasticsearch Cluster by HTTP/es.cluster.status)=2` |HIGH | |
|ES: Health is UNKNOWN |<p>The health status of the cluster is unknown or cannot be obtained.</p> |`last(/Elasticsearch Cluster by HTTP/es.cluster.status)=255` |HIGH | |
|ES: The number of nodes within the cluster has decreased |<p>-</p> |`change(/Elasticsearch Cluster by HTTP/es.cluster.number_of_nodes)<0` |INFO |<p>Manual close: YES</p> |
|ES: The number of nodes within the cluster has increased |<p>-</p> |`change(/Elasticsearch Cluster by HTTP/es.cluster.number_of_nodes)>0` |INFO |<p>Manual close: YES</p> |
|ES: Cluster has the initializing shards |<p>The cluster has the initializing shards longer than 10 minutes.</p> |`min(/Elasticsearch Cluster by HTTP/es.cluster.initializing_shards,10m)>0` |AVERAGE | |
|ES: Cluster has the unassigned shards |<p>The cluster has the unassigned shards longer than 10 minutes.</p> |`min(/Elasticsearch Cluster by HTTP/es.cluster.unassigned_shards,10m)>0` |AVERAGE | |
|ES: Cluster has been restarted |<p>Uptime is less than 10 minutes.</p> |`last(/Elasticsearch Cluster by HTTP/es.nodes.jvm.max_uptime)<10m` |INFO |<p>Manual close: YES</p> |
|ES: Cluster does not have enough space for resharding |<p>There is not enough disk space for index resharding.</p> |`(last(/Elasticsearch Cluster by HTTP/es.nodes.fs.total_in_bytes)-last(/Elasticsearch Cluster by HTTP/es.nodes.fs.available_in_bytes))/(last(/Elasticsearch Cluster by HTTP/es.cluster.number_of_data_nodes)-1)>last(/Elasticsearch Cluster by HTTP/es.nodes.fs.available_in_bytes)` |HIGH | |
|ES: Cluster has only two master nodes |<p>The cluster has only two nodes with a master role and will be unavailable if one of them breaks.</p> |`last(/Elasticsearch Cluster by HTTP/es.nodes.count.master)=2` |DISASTER | |
|ES {#ES.NODE}: has been restarted |<p>Uptime is less than 10 minutes.</p> |`last(/Elasticsearch Cluster by HTTP/es.node.jvm.uptime[{#ES.NODE}])<10m` |INFO |<p>Manual close: YES</p> |
|ES {#ES.NODE}: Percent of JVM heap in use is high |<p>This indicates that the rate of garbage collection isn't keeping up with the rate of garbage creation.</p><p>To address this problem, you can either increase your heap size (as long as it remains below the recommended</p><p>guidelines stated above), or scale out the cluster by adding more nodes.</p> |`min(/Elasticsearch Cluster by HTTP/es.node.jvm.mem.heap_used_percent[{#ES.NODE}],1h)>{$ELASTICSEARCH.HEAP_USED.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- ES {#ES.NODE}: Percent of JVM heap in use is critical</p> |
|ES {#ES.NODE}: Percent of JVM heap in use is critical |<p>This indicates that the rate of garbage collection isn't keeping up with the rate of garbage creation.</p><p>To address this problem, you can either increase your heap size (as long as it remains below the recommended</p><p>guidelines stated above), or scale out the cluster by adding more nodes.</p> |`min(/Elasticsearch Cluster by HTTP/es.node.jvm.mem.heap_used_percent[{#ES.NODE}],1h)>{$ELASTICSEARCH.HEAP_USED.MAX.CRIT}` |HIGH | |
|ES {#ES.NODE}: Query latency is too high |<p>If latency exceeds a threshold, look for potential resource bottlenecks, or investigate whether you need to optimize your queries.</p> |`min(/Elasticsearch Cluster by HTTP/es.node.indices.search.query_latency[{#ES.NODE}],5m)>{$ELASTICSEARCH.QUERY_LATENCY.MAX.WARN}` |WARNING | |
|ES {#ES.NODE}: Fetch latency is too high |<p>The fetch phase should typically take much less time than the query phase. If you notice this metric consistently increasing,</p><p>this could indicate a problem with slow disks, enriching of documents (highlighting the relevant text in search results, etc.),</p><p>or requesting too many results.</p> |`min(/Elasticsearch Cluster by HTTP/es.node.indices.search.fetch_latency[{#ES.NODE}],5m)>{$ELASTICSEARCH.FETCH_LATENCY.MAX.WARN}` |WARNING | |
|ES {#ES.NODE}: Write thread pool executor has the rejected tasks |<p>The number of tasks rejected by the write thread pool executor is over 0 for 5m.</p> |`min(/Elasticsearch Cluster by HTTP/es.node.thread_pool.write.rejected.rate[{#ES.NODE}],5m)>0` |WARNING | |
|ES {#ES.NODE}: Search thread pool executor has the rejected tasks |<p>The number of tasks rejected by the search thread pool executor is over 0 for 5m.</p> |`min(/Elasticsearch Cluster by HTTP/es.node.thread_pool.search.rejected.rate[{#ES.NODE}],5m)>0` |WARNING | |
|ES {#ES.NODE}: Refresh thread pool executor has the rejected tasks |<p>The number of tasks rejected by the refresh thread pool executor is over 0 for 5m.</p> |`min(/Elasticsearch Cluster by HTTP/es.node.thread_pool.refresh.rejected.rate[{#ES.NODE}],5m)>0` |WARNING | |
|ES {#ES.NODE}: Indexing latency is too high |<p>If the latency is increasing, it may indicate that you are indexing too many documents at the same time (Elasticsearch's documentation</p><p>recommends starting with a bulk indexing size of 5 to 15 megabytes and increasing slowly from there).</p> |`min(/Elasticsearch Cluster by HTTP/es.node.indices.indexing.index_latency[{#ES.NODE}],5m)>{$ELASTICSEARCH.INDEXING_LATENCY.MAX.WARN}` |WARNING | |
|ES {#ES.NODE}: Flush latency is too high |<p>If you see this metric increasing steadily, it may indicate a problem with slow disks; this problem may escalate</p><p>and eventually prevent you from being able to add new information to your index.</p> |`min(/Elasticsearch Cluster by HTTP/es.node.indices.flush.latency[{#ES.NODE}],5m)>{$ELASTICSEARCH.FLUSH_LATENCY.MAX.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/399473-discussion-thread-for-official-zabbix-template-for-elasticsearch).


## References

https://www.elastic.co/guide/en/elasticsearch/reference/index.html
