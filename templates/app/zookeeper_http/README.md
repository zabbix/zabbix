
# Zookeeper by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Apache Zookeeper by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.



This template was tested on:

- Apache Zookeeper, version 3.6+

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

This template works with standalone and cluster instances. Metrics are collected from each Zookeper node by requests to [AdminServer](https://zookeeper.apache.org/doc/current/zookeeperAdmin.html#sc_adminserver).
By default AdminServer is enabled and listens on port 8080.
You can enable or configure AdminServer parameters according [official documentations](https://zookeeper.apache.org/doc/current/zookeeperAdmin.html#sc_adminserver_config).
Don't forget to change macros {$ZOOKEEPER.COMMAND_URL}, {$ZOOKEEPER.PORT}, {$ZOOKEEPER.SCHEME}.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ZOOKEEPER.COMMAND_URL} |<p>The URL for listing and issuing commands relative to the root URL (admin.commandURL).</p> |`commands` |
|{$ZOOKEEPER.FILE_DESCRIPTORS.MAX.WARN} |<p>Maximum percentage of file descriptors usage alert threshold (for trigger expression).</p> |`85` |
|{$ZOOKEEPER.OUTSTANDING_REQ.MAX.WARN} |<p>Maximum number of outstanding requests (for trigger expression).</p> |`10` |
|{$ZOOKEEPER.PENDING_SYNCS.MAX.WARN} |<p>Maximum number of pending syncs from the followers (for trigger expression).</p> |`10` |
|{$ZOOKEEPER.PORT} |<p>The port the embedded Jetty server listens on (admin.serverPort).</p> |`8080` |
|{$ZOOKEEPER.SCHEME} |<p>Request scheme which may be http or https</p> |`http` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Clients discovery |<p>Get list of client connections.</p><p>Note, depending on the number of client connections this operation may be expensive (i.e. impact server performance).</p> |HTTP_AGENT |zookeeper.clients<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Leader metrics discovery |<p>Additional metrics for leader node</p> |DEPENDENT |zookeeper.metrics.leader<p>**Preprocessing**:</p><p>- JSONPATH: `$.server_state`</p><p>- JAVASCRIPT: `return JSON.stringify(value == 'leader' ? [{'{#SINGLETON}': ''}] : []);`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Zabbix raw items |Zookeeper: Get server metrics |<p>-</p> |HTTP_AGENT |zookeeper.get_metrics |
|Zabbix raw items |Zookeeper: Get connections stats |<p>Get information on client connections to server. Note, depending on the number of client connections this operation may be expensive (i.e. impact server performance).</p> |HTTP_AGENT |zookeeper.get_connections_stats |
|Zookeeper |Zookeeper: Server mode |<p>Mode of the server. In an ensemble, this may either be leader or follower. Otherwise, it is standalone</p> |DEPENDENT |zookeeper.server_state<p>**Preprocessing**:</p><p>- JSONPATH: `$.server_state`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zookeeper |Zookeeper: Uptime |<p>Uptime of Zookeeper server.</p> |DEPENDENT |zookeeper.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.uptime`</p><p>- MULTIPLIER: `0.001`</p> |
|Zookeeper |Zookeeper: Version |<p>Version of Zookeeper server.</p> |DEPENDENT |zookeeper.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version`</p><p>- REGEX: `([^,]+)--(.+) \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Zookeeper |Zookeeper: Approximate data size |<p>Data tree size in bytes.The size includes the znode path and its value.</p> |DEPENDENT |zookeeper.approximate_data_size<p>**Preprocessing**:</p><p>- JSONPATH: `$.approximate_data_size`</p> |
|Zookeeper |Zookeeper: File descriptors, max |<p>Maximum number of file descriptors that a zookeeper server can open.</p> |DEPENDENT |zookeeper.max_file_descriptor_count<p>**Preprocessing**:</p><p>- JSONPATH: `$.max_file_descriptor_count`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zookeeper |Zookeeper: File descriptors, open |<p>Number of file descriptors that a zookeeper server has open.</p> |DEPENDENT |zookeeper.open_file_descriptor_count<p>**Preprocessing**:</p><p>- JSONPATH: `$.open_file_descriptor_count`</p> |
|Zookeeper |Zookeeper: Outstanding requests |<p>The number of queued requests when the server is under load and is receiving more sustained requests than it can process.</p> |DEPENDENT |zookeeper.outstanding_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.outstanding_requests`</p> |
|Zookeeper |Zookeeper: Commit per sec |<p>The number of commits performed per second</p> |DEPENDENT |zookeeper.commit_count.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.commit_count`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Diff syncs per sec |<p>Number of diff syncs performed per second</p> |DEPENDENT |zookeeper.diff_count.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.diff_count`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Snap syncs per sec |<p>Number of snap syncs performed per second</p> |DEPENDENT |zookeeper.snap_count.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.snap_count`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Looking per sec |<p>Rate of transitions into looking state.</p> |DEPENDENT |zookeeper.looking_count.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.looking_count`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Alive connections |<p>Number of active clients connected to a zookeeper server.</p> |DEPENDENT |zookeeper.num_alive_connections<p>**Preprocessing**:</p><p>- JSONPATH: `$.num_alive_connections`</p> |
|Zookeeper |Zookeeper: Global sessions |<p>Number of global sessions.</p> |DEPENDENT |zookeeper.global_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$.global_sessions`</p> |
|Zookeeper |Zookeeper: Local sessions |<p>Number of local sessions.</p> |DEPENDENT |zookeeper.local_sessions<p>**Preprocessing**:</p><p>- JSONPATH: `$.local_sessions`</p> |
|Zookeeper |Zookeeper: Drop connections per sec |<p>Rate of connection drops.</p> |DEPENDENT |zookeeper.connection_drop_count.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.connection_drop_count`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Rejected connections per sec |<p>Rate of connection rejected.</p> |DEPENDENT |zookeeper.connection_rejected.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.connection_rejected`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Revalidate connections per sec |<p>Rate ofconnection revalidations.</p> |DEPENDENT |zookeeper.connection_revalidate_count.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.connection_revalidate_count`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Revalidate per sec |<p>Rate of revalidations.</p> |DEPENDENT |zookeeper.revalidate_count.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.revalidate_count`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Latency, max |<p>The maximum amount of time it takes for the server to respond to a client request.</p> |DEPENDENT |zookeeper.max_latency<p>**Preprocessing**:</p><p>- JSONPATH: `$.max_latency`</p> |
|Zookeeper |Zookeeper: Latency, min |<p>The minimum amount of time it takes for the server to respond to a client request.</p> |DEPENDENT |zookeeper.min_latency<p>**Preprocessing**:</p><p>- JSONPATH: `$.min_latency`</p> |
|Zookeeper |Zookeeper: Latency, avg |<p>The average amount of time it takes for the server to respond to a client request.</p> |DEPENDENT |zookeeper.avg_latency<p>**Preprocessing**:</p><p>- JSONPATH: `$.avg_latency`</p> |
|Zookeeper |Zookeeper: Znode count |<p>The number of znodes in the ZooKeeper namespace (the data)</p> |DEPENDENT |zookeeper.znode_count<p>**Preprocessing**:</p><p>- JSONPATH: `$.znode_count`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zookeeper |Zookeeper: Ephemeral nodes count |<p>Number of ephemeral nodes that a zookeeper server has in its data tree.</p> |DEPENDENT |zookeeper.ephemerals_count<p>**Preprocessing**:</p><p>- JSONPATH: `$.ephemerals_count`</p> |
|Zookeeper |Zookeeper: Watch count |<p>Number of watches currently set on the local ZooKeeper process.</p> |DEPENDENT |zookeeper.watch_count<p>**Preprocessing**:</p><p>- JSONPATH: `$.watch_count`</p> |
|Zookeeper |Zookeeper: Packets sent per sec |<p>The number of zookeeper packets sent from a server per second.</p> |DEPENDENT |zookeeper.packets_sent<p>**Preprocessing**:</p><p>- JSONPATH: `$.packets_sent`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Packets received per sec |<p>The number of zookeeper packets received by a server per second.</p> |DEPENDENT |zookeeper.packets_received.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.packets_received`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Bytes received per sec |<p>Number of bytes received per second.</p> |DEPENDENT |zookeeper.bytes_received_count.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.bytes_received_count`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper: Election time, avg |<p>Time between entering and leaving election.</p> |DEPENDENT |zookeeper.avg_election_time<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Zookeeper |Zookeeper: Elections |<p>Number of elections happened.</p> |DEPENDENT |zookeeper.cnt_election_time<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Zookeeper |Zookeeper: Fsync time, avg |<p>Time to fsync transaction log.</p> |DEPENDENT |zookeeper.avg_fsynctime<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Zookeeper |Zookeeper: Fsync |<p>Count of performed fsyncs.</p> |DEPENDENT |zookeeper.cnt_fsynctime<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var metrics = JSON.parse(value) return metrics.cnt_fsynctime || metrics.fsynctime_count`</p> |
|Zookeeper |Zookeeper: Snapshot write time, avg |<p>Average time to write a snapshot.</p> |DEPENDENT |zookeeper.avg_snapshottime<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Zookeeper |Zookeeper: Snapshot writes |<p>Count of performed snapshot writes.</p> |DEPENDENT |zookeeper.cnt_snapshottime<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var metrics = JSON.parse(value) return metrics.snapshottime_count || metrics.cnt_snapshottime`</p> |
|Zookeeper |Zookeeper: Pending syncs{#SINGLETON} |<p>Number of pending syncs to carry out to ZooKeeper ensemble followers.</p> |DEPENDENT |zookeeper.pending_syncs[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pending_syncs`</p> |
|Zookeeper |Zookeeper: Quorum size{#SINGLETON} |<p>-</p> |DEPENDENT |zookeeper.quorum_size[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.quorum_size`</p> |
|Zookeeper |Zookeeper: Synced followers{#SINGLETON} |<p>Number of synced followers reported when a node server_state is leader.</p> |DEPENDENT |zookeeper.synced_followers[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.synced_followers`</p> |
|Zookeeper |Zookeeper: Synced non-voting follower{#SINGLETON} |<p>Number of synced voting followers reported when a node server_state is leader.</p> |DEPENDENT |zookeeper.synced_non_voting_followers[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.synced_non_voting_followers`</p> |
|Zookeeper |Zookeeper: Synced observers{#SINGLETON} |<p>Number of synced observers.</p> |DEPENDENT |zookeeper.synced_observers[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.synced_observers`</p> |
|Zookeeper |Zookeeper: Learners{#SINGLETON} |<p>Number of learners.</p> |DEPENDENT |zookeeper.learners[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.learners`</p> |
|Zookeeper |Zookeeper client {#TYPE} [{#CLIENT}]: Latency, max |<p>The maximum amount of time it takes for the server to respond to a client request.</p> |DEPENDENT |zookeeper.max_latency[{#TYPE},{#CLIENT}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.{#TYPE}.[?(@.remote_socket_address == "{#ADDRESS}")].max_latency.first()`</p> |
|Zookeeper |Zookeeper client {#TYPE} [{#CLIENT}]: Latency, min |<p>The minimum amount of time it takes for the server to respond to a client request.</p> |DEPENDENT |zookeeper.min_latency[{#TYPE},{#CLIENT}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.{#TYPE}.[?(@.remote_socket_address == "{#ADDRESS}")].min_latency.first()`</p> |
|Zookeeper |Zookeeper client {#TYPE} [{#CLIENT}]: Latency, avg |<p>The average amount of time it takes for the server to respond to a client request.</p> |DEPENDENT |zookeeper.avg_latency[{#TYPE},{#CLIENT}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.{#TYPE}.[?(@.remote_socket_address == "{#ADDRESS}")].avg_latency.first()`</p> |
|Zookeeper |Zookeeper client {#TYPE} [{#CLIENT}]: Packets sent per sec |<p>The number of packets sent.</p> |DEPENDENT |zookeeper.packets_sent[{#TYPE},{#CLIENT}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.{#TYPE}.[?(@.remote_socket_address == "{#ADDRESS}")].packets_sent.first()`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper client {#TYPE} [{#CLIENT}]: Packets received per sec |<p>The number of packets received.</p> |DEPENDENT |zookeeper.packets_received[{#TYPE},{#CLIENT}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.{#TYPE}.[?(@.remote_socket_address == "{#ADDRESS}")].packets_received.first()`</p><p>- CHANGE_PER_SECOND</p> |
|Zookeeper |Zookeeper client {#TYPE} [{#CLIENT}]: Outstanding requests |<p>The number of queued requests when the server is under load and is receiving more sustained requests than it can process.</p> |DEPENDENT |zookeeper.outstanding_requests[{#TYPE},{#CLIENT}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.{#TYPE}.[?(@.remote_socket_address == "{#ADDRESS}")].outstanding_requests.first()`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Zookeeper: Server mode has changed |<p>Zookeeper node state has changed. Ack to close.</p> |`last(/Zookeeper by HTTP/zookeeper.server_state,#1)<>last(/Zookeeper by HTTP/zookeeper.server_state,#2) and length(last(/Zookeeper by HTTP/zookeeper.server_state))>0` |INFO |<p>Manual close: YES</p> |
|Zookeeper: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Zookeeper by HTTP/zookeeper.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Zookeeper: Failed to fetch info data |<p>Zabbix has not received data for items for the last 10 minutes</p> |`nodata(/Zookeeper by HTTP/zookeeper.uptime,10m)=1` |WARNING |<p>Manual close: YES</p> |
|Zookeeper: Version has changed |<p>Zookeeper version has changed. Ack to close.</p> |`last(/Zookeeper by HTTP/zookeeper.version,#1)<>last(/Zookeeper by HTTP/zookeeper.version,#2) and length(last(/Zookeeper by HTTP/zookeeper.version))>0` |INFO |<p>Manual close: YES</p> |
|Zookeeper: Too many file descriptors used |<p>Number of file descriptors used more than {$ZOOKEEPER.FILE_DESCRIPTORS.MAX.WARN}% of the available number of file descriptors.</p> |`min(/Zookeeper by HTTP/zookeeper.open_file_descriptor_count,5m) * 100 / last(/Zookeeper by HTTP/zookeeper.max_file_descriptor_count) > {$ZOOKEEPER.FILE_DESCRIPTORS.MAX.WARN}` |WARNING | |
|Zookeeper: Too many queued requests |<p>Number of queued requests in the server. This goes up when the server receives more requests than it can process.</p> |`min(/Zookeeper by HTTP/zookeeper.outstanding_requests,5m)>{$ZOOKEEPER.OUTSTANDING_REQ.MAX.WARN}` |AVERAGE |<p>Manual close: YES</p> |
|Zookeeper: Too many pending syncs |<p>-</p> |`min(/Zookeeper by HTTP/zookeeper.pending_syncs[{#SINGLETON}],5m)>{$ZOOKEEPER.PENDING_SYNCS.MAX.WARN}` |AVERAGE |<p>Manual close: YES</p> |
|Zookeeper: Too few active followers |<p>The number of followers should equal the total size of your ZooKeeper ensemble, minus 1 (the leader is not included in the follower count). If the ensemble fails to maintain quorum, all automatic failover features are suspended. </p> |`last(/Zookeeper by HTTP/zookeeper.synced_followers[{#SINGLETON}]) < last(/Zookeeper by HTTP/zookeeper.quorum_size[{#SINGLETON}])-1` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

