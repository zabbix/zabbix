
# Zookeeper by HTTP

## Overview

This template is designed for the effortless deployment of Zookeeper monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Apache Zookeeper, version 3.6+, 3.8+

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

This template works with standalone and cluster instances. Metrics are collected from each Zookeeper node by requests to [AdminServer](https://zookeeper.apache.org/doc/current/zookeeperAdmin.html#sc_adminserver).
By default AdminServer is enabled and listens on port 8080.
You can enable or configure AdminServer parameters according [official documentations](https://zookeeper.apache.org/doc/current/zookeeperAdmin.html#sc_adminserver_config).
Don't forget to change macros {$ZOOKEEPER.COMMAND_URL}, {$ZOOKEEPER.PORT}, {$ZOOKEEPER.SCHEME}.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ZOOKEEPER.PORT}|<p>The port the embedded Jetty server listens on (admin.serverPort).</p>|`8080`|
|{$ZOOKEEPER.COMMAND_URL}|<p>The URL for listing and issuing commands relative to the root URL (admin.commandURL).</p>|`commands`|
|{$ZOOKEEPER.SCHEME}|<p>Request scheme which may be http or https</p>|`http`|
|{$ZOOKEEPER.FILE_DESCRIPTORS.MAX.WARN}|<p>Maximum percentage of file descriptors usage alert threshold (for trigger expression).</p>|`85`|
|{$ZOOKEEPER.OUTSTANDING_REQ.MAX.WARN}|<p>Maximum number of outstanding requests (for trigger expression).</p>|`10`|
|{$ZOOKEEPER.PENDING_SYNCS.MAX.WARN}|<p>Maximum number of pending syncs from the followers (for trigger expression).</p>|`10`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Zookeeper: Get server metrics||HTTP agent|zookeeper.get_metrics|
|Zookeeper: Get connections stats|<p>Get information on client connections to server. Note, depending on the number of client connections this operation may be expensive (i.e. impact server performance).</p>|HTTP agent|zookeeper.get_connections_stats|
|Zookeeper: Server mode|<p>Mode of the server. In an ensemble, this may either be leader or follower. Otherwise, it is standalone</p>|Dependent item|zookeeper.server_state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.server_state`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Zookeeper: Uptime|<p>Uptime that a peer has been in a table leading/following/observing state.</p>|Dependent item|zookeeper.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Zookeeper: Version|<p>Version of Zookeeper server.</p>|Dependent item|zookeeper.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li><li><p>Regular expression: `^([0-9\.]+) \1`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Zookeeper: Approximate data size|<p>Data tree size in bytes.The size includes the znode path and its value.</p>|Dependent item|zookeeper.approximate_data_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.approximate_data_size`</p></li></ul>|
|Zookeeper: File descriptors, max|<p>Maximum number of file descriptors that a zookeeper server can open.</p>|Dependent item|zookeeper.max_file_descriptor_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max_file_descriptor_count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Zookeeper: File descriptors, open|<p>Number of file descriptors that a zookeeper server has open.</p>|Dependent item|zookeeper.open_file_descriptor_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.open_file_descriptor_count`</p></li></ul>|
|Zookeeper: Outstanding requests|<p>The number of queued requests when the server is under load and is receiving more sustained requests than it can process.</p>|Dependent item|zookeeper.outstanding_requests<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.outstanding_requests`</p></li></ul>|
|Zookeeper: Commit per sec|<p>The number of commits performed per second</p>|Dependent item|zookeeper.commit_count.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.commit_count`</p></li><li>Change per second</li></ul>|
|Zookeeper: Diff syncs per sec|<p>Number of diff syncs performed per second</p>|Dependent item|zookeeper.diff_count.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.diff_count`</p></li><li>Change per second</li></ul>|
|Zookeeper: Snap syncs per sec|<p>Number of snap syncs performed per second</p>|Dependent item|zookeeper.snap_count.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.snap_count`</p></li><li>Change per second</li></ul>|
|Zookeeper: Looking per sec|<p>Rate of transitions into looking state.</p>|Dependent item|zookeeper.looking_count.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.looking_count`</p></li><li>Change per second</li></ul>|
|Zookeeper: Alive connections|<p>Number of active clients connected to a zookeeper server.</p>|Dependent item|zookeeper.num_alive_connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_alive_connections`</p></li></ul>|
|Zookeeper: Global sessions|<p>Number of global sessions.</p>|Dependent item|zookeeper.global_sessions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global_sessions`</p></li></ul>|
|Zookeeper: Local sessions|<p>Number of local sessions.</p>|Dependent item|zookeeper.local_sessions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.local_sessions`</p></li></ul>|
|Zookeeper: Drop connections per sec|<p>Rate of connection drops.</p>|Dependent item|zookeeper.connection_drop_count.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connection_drop_count`</p></li><li>Change per second</li></ul>|
|Zookeeper: Rejected connections per sec|<p>Rate of connection rejected.</p>|Dependent item|zookeeper.connection_rejected.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connection_rejected`</p></li><li>Change per second</li></ul>|
|Zookeeper: Revalidate connections per sec|<p>Rate of connection revalidations.</p>|Dependent item|zookeeper.connection_revalidate_count.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connection_revalidate_count`</p></li><li>Change per second</li></ul>|
|Zookeeper: Revalidate per sec|<p>Rate of revalidations.</p>|Dependent item|zookeeper.revalidate_count.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.revalidate_count`</p></li><li>Change per second</li></ul>|
|Zookeeper: Latency, max|<p>The maximum amount of time it takes for the server to respond to a client request.</p>|Dependent item|zookeeper.max_latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max_latency`</p></li></ul>|
|Zookeeper: Latency, min|<p>The minimum amount of time it takes for the server to respond to a client request.</p>|Dependent item|zookeeper.min_latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.min_latency`</p></li></ul>|
|Zookeeper: Latency, avg|<p>The average amount of time it takes for the server to respond to a client request.</p>|Dependent item|zookeeper.avg_latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avg_latency`</p></li></ul>|
|Zookeeper: Znode count|<p>The number of znodes in the ZooKeeper namespace (the data)</p>|Dependent item|zookeeper.znode_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.znode_count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Zookeeper: Ephemeral nodes count|<p>Number of ephemeral nodes that a zookeeper server has in its data tree.</p>|Dependent item|zookeeper.ephemerals_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ephemerals_count`</p></li></ul>|
|Zookeeper: Watch count|<p>Number of watches currently set on the local ZooKeeper process.</p>|Dependent item|zookeeper.watch_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.watch_count`</p></li></ul>|
|Zookeeper: Packets sent per sec|<p>The number of zookeeper packets sent from a server per second.</p>|Dependent item|zookeeper.packets_sent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packets_sent`</p></li><li>Change per second</li></ul>|
|Zookeeper: Packets received per sec|<p>The number of zookeeper packets received by a server per second.</p>|Dependent item|zookeeper.packets_received.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packets_received`</p></li><li>Change per second</li></ul>|
|Zookeeper: Bytes received per sec|<p>Number of bytes received per second.</p>|Dependent item|zookeeper.bytes_received_count.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes_received_count`</p></li><li>Change per second</li></ul>|
|Zookeeper: Election time, avg|<p>Time between entering and leaving election.</p>|Dependent item|zookeeper.avg_election_time<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Zookeeper: Elections|<p>Number of elections happened.</p>|Dependent item|zookeeper.cnt_election_time<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Zookeeper: Fsync time, avg|<p>Time to fsync transaction log.</p>|Dependent item|zookeeper.avg_fsynctime<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Zookeeper: Fsync|<p>Count of performed fsyncs.</p>|Dependent item|zookeeper.cnt_fsynctime<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Zookeeper: Snapshot write time, avg|<p>Average time to write a snapshot.</p>|Dependent item|zookeeper.avg_snapshottime<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Zookeeper: Snapshot writes|<p>Count of performed snapshot writes.</p>|Dependent item|zookeeper.cnt_snapshottime<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Zookeeper: Server mode has changed|<p>Zookeeper node state has changed. Acknowledge to close the problem manually.</p>|`last(/Zookeeper by HTTP/zookeeper.server_state,#1)<>last(/Zookeeper by HTTP/zookeeper.server_state,#2) and length(last(/Zookeeper by HTTP/zookeeper.server_state))>0`|Info|**Manual close**: Yes|
|Zookeeper: Failed to fetch info data|<p>Zabbix has not received data for items for the last 10 minutes</p>|`nodata(/Zookeeper by HTTP/zookeeper.uptime,10m)=1`|Warning|**Manual close**: Yes|
|Zookeeper: Version has changed|<p>Zookeeper version has changed. Acknowledge to close the problem manually.</p>|`last(/Zookeeper by HTTP/zookeeper.version,#1)<>last(/Zookeeper by HTTP/zookeeper.version,#2) and length(last(/Zookeeper by HTTP/zookeeper.version))>0`|Info|**Manual close**: Yes|
|Zookeeper: Too many file descriptors used|<p>Number of file descriptors used more than {$ZOOKEEPER.FILE_DESCRIPTORS.MAX.WARN}% of the available number of file descriptors.</p>|`min(/Zookeeper by HTTP/zookeeper.open_file_descriptor_count,5m) * 100 / last(/Zookeeper by HTTP/zookeeper.max_file_descriptor_count) > {$ZOOKEEPER.FILE_DESCRIPTORS.MAX.WARN}`|Warning||
|Zookeeper: Too many queued requests|<p>Number of queued requests in the server. This goes up when the server receives more requests than it can process.</p>|`min(/Zookeeper by HTTP/zookeeper.outstanding_requests,5m)>{$ZOOKEEPER.OUTSTANDING_REQ.MAX.WARN}`|Average|**Manual close**: Yes|

### LLD rule Leader metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Leader metrics discovery|<p>Additional metrics for leader node</p>|Dependent item|zookeeper.metrics.leader<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.server_state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Leader metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Zookeeper: Pending syncs{#SINGLETON}|<p>Number of pending syncs to carry out to ZooKeeper ensemble followers.</p>|Dependent item|zookeeper.pending_syncs[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pending_syncs`</p></li></ul>|
|Zookeeper: Quorum size{#SINGLETON}||Dependent item|zookeeper.quorum_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.quorum_size`</p></li></ul>|
|Zookeeper: Synced followers{#SINGLETON}|<p>Number of synced followers reported when a node server_state is leader.</p>|Dependent item|zookeeper.synced_followers[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.synced_followers`</p></li></ul>|
|Zookeeper: Synced non-voting follower{#SINGLETON}|<p>Number of synced voting followers reported when a node server_state is leader.</p>|Dependent item|zookeeper.synced_non_voting_followers[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.synced_non_voting_followers`</p></li></ul>|
|Zookeeper: Synced observers{#SINGLETON}|<p>Number of synced observers.</p>|Dependent item|zookeeper.synced_observers[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.synced_observers`</p></li></ul>|
|Zookeeper: Learners{#SINGLETON}|<p>Number of learners.</p>|Dependent item|zookeeper.learners[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.learners`</p></li></ul>|

### Trigger prototypes for Leader metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Zookeeper: Too many pending syncs||`min(/Zookeeper by HTTP/zookeeper.pending_syncs[{#SINGLETON}],5m)>{$ZOOKEEPER.PENDING_SYNCS.MAX.WARN}`|Average|**Manual close**: Yes|
|Zookeeper: Too few active followers|<p>The number of followers should equal the total size of your ZooKeeper ensemble, minus 1 (the leader is not included in the follower count). If the ensemble fails to maintain quorum, all automatic failover features are suspended.</p>|`last(/Zookeeper by HTTP/zookeeper.synced_followers[{#SINGLETON}]) < last(/Zookeeper by HTTP/zookeeper.quorum_size[{#SINGLETON}])-1`|Average||

### LLD rule Clients discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Clients discovery|<p>Get list of client connections.</p><p>Note, depending on the number of client connections this operation may be expensive (i.e. impact server performance).</p>|HTTP agent|zookeeper.clients<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Clients discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Zookeeper client {#TYPE} [{#CLIENT}]: Get client info|<p>The item gets information about "{#CLIENT}" client of "{#TYPE}" type.</p>|Dependent item|zookeeper.client_info[{#TYPE},{#CLIENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Zookeeper client {#TYPE} [{#CLIENT}]: Latency, max|<p>The maximum amount of time it takes for the server to respond to a client request.</p>|Dependent item|zookeeper.max_latency[{#TYPE},{#CLIENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max_latency`</p></li></ul>|
|Zookeeper client {#TYPE} [{#CLIENT}]: Latency, min|<p>The minimum amount of time it takes for the server to respond to a client request.</p>|Dependent item|zookeeper.min_latency[{#TYPE},{#CLIENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.min_latency`</p></li></ul>|
|Zookeeper client {#TYPE} [{#CLIENT}]: Latency, avg|<p>The average amount of time it takes for the server to respond to a client request.</p>|Dependent item|zookeeper.avg_latency[{#TYPE},{#CLIENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avg_latency`</p></li></ul>|
|Zookeeper client {#TYPE} [{#CLIENT}]: Packets sent per sec|<p>The number of packets sent.</p>|Dependent item|zookeeper.packets_sent[{#TYPE},{#CLIENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packets_sent`</p></li><li>Change per second</li></ul>|
|Zookeeper client {#TYPE} [{#CLIENT}]: Packets received per sec|<p>The number of packets received.</p>|Dependent item|zookeeper.packets_received[{#TYPE},{#CLIENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packets_received`</p></li><li>Change per second</li></ul>|
|Zookeeper client {#TYPE} [{#CLIENT}]: Outstanding requests|<p>The number of queued requests when the server is under load and is receiving more sustained requests than it can process.</p>|Dependent item|zookeeper.outstanding_requests[{#TYPE},{#CLIENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.outstanding_requests`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

