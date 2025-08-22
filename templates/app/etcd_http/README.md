
# Etcd by HTTP

## Overview

This template is designed to monitor `etcd` by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template `Etcd by HTTP` — collects metrics by help of the HTTP agent from `/metrics` endpoint.

> Refer to the [`vendor documentation`](https://etcd.io/docs/v3.5/op-guide/monitoring/#metrics-endpoint).

**For the users of `etcd version <= 3.4` !**

> In `etcd v3.5` some metrics have been deprecated. See more details on [`Upgrade etcd from 3.4 to 3.5`](https://etcd.io/docs/v3.4/upgrades/upgrade_3_5/).
Please upgrade your `etcd` instance, or use older `Etcd by HTTP` template version.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Etcd 3.5.6

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Make sure that `etcd` allows the collection of metrics. You can test it by running: `curl -L http://localhost:2379/metrics`.

2. Check if `etcd` is accessible from Zabbix proxy or Zabbix server depending on where you are planning to do the monitoring. To verify it, run `curl -L  http://<etcd_node_address>:2379/metrics`.

3. Add the template to the `etcd` node. Set the hostname or IP address of the `etcd` host in the `{$ETCD.HOST}` macro. By default, the template uses a client's port.
You can configure metrics endpoint location by adding `--listen-metrics-urls` flag.

For more details, see the [`etcd documentation`](https://etcd.io/docs/v3.5/op-guide/configuration/#profiling-and-monitoring).

Additional points to consider:

- If you have specified a non-standard port for `etcd`, don't forget to change macros: `{$ETCD.SCHEME}` and `{$ETCD.PORT}`.
- You can set `{$ETCD.USERNAME}` and `{$ETCD.PASSWORD}` macros in the template to use on a host level if necessary.
- To test availability, run: `zabbix_get -s etcd-host -k etcd.health`.
- See the macros section, as it will set the trigger values.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ETCD.HOST}|<p>The hostname or IP address of the `etcd` API endpoint.</p>||
|{$ETCD.PORT}|<p>The port of the `etcd` API endpoint.</p>|`2379`|
|{$ETCD.SCHEME}|<p>The request scheme which may be `http` or `https`.</p>|`http`|
|{$ETCD.USER}|||
|{$ETCD.PASSWORD}|||
|{$ETCD.LEADER.CHANGES.MAX.WARN}|<p>The maximum number of leader changes.</p>|`5`|
|{$ETCD.PROPOSAL.FAIL.MAX.WARN}|<p>The maximum number of proposal failures.</p>|`2`|
|{$ETCD.HTTP.FAIL.MAX.WARN}|<p>The maximum number of HTTP request failures.</p>|`2`|
|{$ETCD.PROPOSAL.PENDING.MAX.WARN}|<p>The maximum number of proposals in queue.</p>|`5`|
|{$ETCD.OPEN.FDS.MAX.WARN}|<p>The maximum percentage of used file descriptors.</p>|`90`|
|{$ETCD.GRPC_CODE.MATCHES}|<p>The filter of discoverable gRPC codes. See more details on https://github.com/grpc/grpc/blob/master/doc/statuscodes.md.</p>|`.*`|
|{$ETCD.GRPC_CODE.NOT_MATCHES}|<p>The filter to exclude discovered gRPC codes. See more details on https://github.com/grpc/grpc/blob/master/doc/statuscodes.md.</p>|`CHANGE_IF_NEEDED`|
|{$ETCD.GRPC.ERRORS.MAX.WARN}|<p>The maximum number of gRPC request failures.</p>|`1`|
|{$ETCD.GRPC_CODE.TRIGGER.MATCHES}|<p>The filter of discoverable gRPC codes, which will create triggers.</p>|`Aborted\|Unavailable`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service's TCP port state||Simple check|net.tcp.service["{$ETCD.SCHEME}","{$ETCD.HOST}","{$ETCD.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Get node metrics||HTTP agent|etcd.get_metrics|
|Node health||HTTP agent|etcd.health<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health`</p></li><li><p>Boolean to decimal</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Server is a leader|<p>It defines - whether or not this member is a leader:</p><p>1 - it is;</p><p>0 - otherwise.</p>|Dependent item|etcd.is.leader<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_server_is_leader)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Server has a leader|<p>It defines - whether or not a leader exists:</p><p>1 - it exists;</p><p>0 - it does not.</p>|Dependent item|etcd.has.leader<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_server_has_leader)`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Leader changes|<p>The number of leader changes the member has seen since its start.</p>|Dependent item|etcd.leader.changes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_server_leader_changes_seen_total)`</p></li></ul>|
|Proposals committed per second|<p>The number of consensus proposals committed.</p>|Dependent item|etcd.proposals.committed.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_server_proposals_committed_total)`</p></li><li>Change per second</li></ul>|
|Proposals applied per second|<p>The number of consensus proposals applied.</p>|Dependent item|etcd.proposals.applied.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_server_proposals_applied_total)`</p></li><li>Change per second</li></ul>|
|Proposals failed per second|<p>The number of failed proposals seen.</p>|Dependent item|etcd.proposals.failed.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_server_proposals_failed_total)`</p></li><li>Change per second</li></ul>|
|Proposals pending|<p>The current number of pending proposals to commit.</p>|Dependent item|etcd.proposals.pending<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_server_proposals_pending)`</p></li></ul>|
|Reads per second|<p>The number of read actions by `get/getRecursive`, local to this member.</p>|Dependent item|etcd.reads.rate<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `etcd_debugging_store_reads_total`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Writes per second|<p>The number of writes (e.g., `set/compareAndDelete`) seen by this member.</p>|Dependent item|etcd.writes.rate<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `etcd_debugging_store_writes_total`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Client gRPC received bytes per second|<p>The number of bytes received from gRPC clients per second.</p>|Dependent item|etcd.network.grpc.received.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_network_client_grpc_received_bytes_total)`</p></li><li>Change per second</li></ul>|
|Client gRPC sent bytes per second|<p>The number of bytes sent from gRPC clients per second.</p>|Dependent item|etcd.network.grpc.sent.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_network_client_grpc_sent_bytes_total)`</p></li><li>Change per second</li></ul>|
|HTTP requests received|<p>The number of requests received into the system (successfully parsed and `authd`).</p>|Dependent item|etcd.http.requests.rate<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `etcd_http_received_total`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|HTTP 5XX|<p>The number of handled failures of requests (non-watches), by the method (`GET/PUT` etc.), and the code `5XX`.</p>|Dependent item|etcd.http.requests.5xx.rate<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `etcd_http_failed_total{code=~"5.+"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|HTTP 4XX|<p>The number of handled failures of requests (non-watches), by the method (`GET/PUT` etc.), and the code `4XX`.</p>|Dependent item|etcd.http.requests.4xx.rate<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `etcd_http_failed_total{code=~"4.+"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|RPCs received per second|<p>The number of RPC stream messages received on the server.</p>|Dependent item|etcd.grpc.received.rate<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `grpc_server_msg_received_total`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|RPCs sent per second|<p>The number of gRPC stream messages sent by the server.</p>|Dependent item|etcd.grpc.sent.rate<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `grpc_server_msg_sent_total`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|RPCs started per second|<p>The number of RPCs started on the server.</p>|Dependent item|etcd.grpc.started.rate<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `grpc_server_started_total`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Get version||HTTP agent|etcd.get_version|
|Server version|<p>The version of the `etcd server`.</p>|Dependent item|etcd.server.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.etcdserver`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cluster version|<p>The version of the `etcd cluster`.</p>|Dependent item|etcd.cluster.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.etcdcluster`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|DB size|<p>The total size of the underlying database.</p>|Dependent item|etcd.db.size<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_mvcc_db_total_size_in_bytes)`</p></li></ul>|
|Keys compacted per second|<p>The number of DB keys compacted per second.</p>|Dependent item|etcd.keys.compacted.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_debugging_mvcc_db_compaction_keys_total)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Keys expired per second|<p>The number of expired keys per second.</p>|Dependent item|etcd.keys.expired.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_debugging_store_expires_total)`</p></li><li>Change per second</li></ul>|
|Keys total|<p>The total number of keys.</p>|Dependent item|etcd.keys.total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_debugging_mvcc_keys_total)`</p></li></ul>|
|Uptime|<p>`Etcd` server uptime.</p>|Dependent item|etcd.uptime<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_start_time_seconds)`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Virtual memory|<p>The size of virtual memory expressed in bytes.</p>|Dependent item|etcd.virtual.bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_virtual_memory_bytes)`</p></li></ul>|
|Resident memory|<p>The size of resident memory expressed in bytes.</p>|Dependent item|etcd.res.bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_resident_memory_bytes)`</p></li></ul>|
|CPU|<p>The total user and system CPU time spent in seconds.</p>|Dependent item|etcd.cpu.util<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_cpu_seconds_total)`</p></li><li>Change per second</li></ul>|
|Open file descriptors|<p>The number of open file descriptors.</p>|Dependent item|etcd.open.fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_open_fds)`</p></li></ul>|
|Maximum open file descriptors|<p>The Maximum number of open file descriptors.</p>|Dependent item|etcd.max.fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_max_fds)`</p></li></ul>|
|Deletes per second|<p>The number of deletes seen by this member per second.</p>|Dependent item|etcd.delete.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_mvcc_delete_total)`</p></li><li>Change per second</li></ul>|
|PUT per second|<p>The number of puts seen by this member per second.</p>|Dependent item|etcd.put.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_mvcc_put_total)`</p></li><li>Change per second</li></ul>|
|Range per second|<p>The number of ranges seen by this member per second.</p>|Dependent item|etcd.range.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_debugging_mvcc_range_total)`</p></li><li>Change per second</li></ul>|
|Transaction per second|<p>The number of transactions seen by this member per second.</p>|Dependent item|etcd.txn.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_debugging_mvcc_range_total)`</p></li><li>Change per second</li></ul>|
|Pending events|<p>The total number of pending events to be sent.</p>|Dependent item|etcd.events.sent.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_debugging_mvcc_pending_events_total)`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Etcd: Service is unavailable||`last(/Etcd by HTTP/net.tcp.service["{$ETCD.SCHEME}","{$ETCD.HOST}","{$ETCD.PORT}"])=0`|Average|**Manual close**: Yes|
|Etcd: Node healthcheck failed|<p>See more details on https://etcd.io/docs/v3.5/op-guide/monitoring/#health-check.</p>|`last(/Etcd by HTTP/etcd.health)=0`|Average|**Depends on**:<br><ul><li>Etcd: Service is unavailable</li></ul>|
|Etcd: Failed to fetch info data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Etcd by HTTP/etcd.is.leader,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Etcd: Service is unavailable</li></ul>|
|Etcd: Member has no leader|<p>If a member does not have a leader, it is totally unavailable.</p>|`last(/Etcd by HTTP/etcd.has.leader)=0`|Average||
|Etcd: Instance has seen too many leader changes|<p>Rapid leadership changes impact the performance of `etcd` significantly. It also signals that the leader is unstable, perhaps due to network connectivity issues or excessive load hitting the `etcd cluster`.</p>|`(max(/Etcd by HTTP/etcd.leader.changes,15m)-min(/Etcd by HTTP/etcd.leader.changes,15m))>{$ETCD.LEADER.CHANGES.MAX.WARN}`|Warning||
|Etcd: Too many proposal failures|<p>Normally related to two issues: temporary failures related to a leader election or longer downtime caused by a loss of quorum in the cluster.</p>|`min(/Etcd by HTTP/etcd.proposals.failed.rate,5m)>{$ETCD.PROPOSAL.FAIL.MAX.WARN}`|Warning||
|Etcd: Too many proposals are queued to commit|<p>Rising pending proposals suggests there is a high client load, or the member cannot commit proposals.</p>|`min(/Etcd by HTTP/etcd.proposals.pending,5m)>{$ETCD.PROPOSAL.PENDING.MAX.WARN}`|Warning||
|Etcd: Too many HTTP requests failures|<p>Too many requests failed on `etcd` instance with the `5xx HTTP code`.</p>|`min(/Etcd by HTTP/etcd.http.requests.5xx.rate,5m)>{$ETCD.HTTP.FAIL.MAX.WARN}`|Warning||
|Etcd: Server version has changed|<p>Etcd version has changed. Acknowledge to close the problem manually.</p>|`last(/Etcd by HTTP/etcd.server.version,#1)<>last(/Etcd by HTTP/etcd.server.version,#2) and length(last(/Etcd by HTTP/etcd.server.version))>0`|Info|**Manual close**: Yes|
|Etcd: Cluster version has changed|<p>Etcd version has changed. Acknowledge to close the problem manually.</p>|`last(/Etcd by HTTP/etcd.cluster.version,#1)<>last(/Etcd by HTTP/etcd.cluster.version,#2) and length(last(/Etcd by HTTP/etcd.cluster.version))>0`|Info|**Manual close**: Yes|
|Etcd: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Etcd by HTTP/etcd.uptime)<10m`|Info|**Manual close**: Yes|
|Etcd: Current number of open files is too high|<p>Heavy usage of a file descriptor (i.e., near the limit of the process's file descriptor) indicates a potential file descriptor exhaustion issue.<br>If the file descriptors are exhausted, `etcd` may panic because it cannot create new WAL files.</p>|`min(/Etcd by HTTP/etcd.open.fds,5m)/last(/Etcd by HTTP/etcd.max.fds)*100>{$ETCD.OPEN.FDS.MAX.WARN}`|Warning||

### LLD rule gRPC codes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|gRPC codes discovery||Dependent item|etcd.grpc_code.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `grpc_server_handled_total`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for gRPC codes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|RPCs completed with code {#GRPC.CODE}|<p>The number of RPCs completed on the server with grpc_code {#GRPC.CODE}.</p>|Dependent item|etcd.grpc.handled.rate[{#GRPC.CODE}]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `grpc_server_handled_total{grpc_method="{#GRPC.CODE}"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for gRPC codes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Etcd: Too many failed gRPC requests with code: {#GRPC.CODE}||`min(/Etcd by HTTP/etcd.grpc.handled.rate[{#GRPC.CODE}],5m)>{$ETCD.GRPC.ERRORS.MAX.WARN}`|Warning||

### LLD rule Peers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Peers discovery||Dependent item|etcd.peer.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `etcd_network_peer_sent_bytes_total`</p></li></ul>|

### Item prototypes for Peers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Etcd peer {#ETCD.PEER}: Bytes sent|<p>The number of bytes sent to a peer with the ID `{#ETCD.PEER}`.</p>|Dependent item|etcd.bytes.sent.rate[{#ETCD.PEER}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_network_peer_sent_bytes_total{To="{#ETCD.PEER}"})`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Etcd peer {#ETCD.PEER}: Bytes received|<p>The number of bytes received from a peer with the ID `{#ETCD.PEER}`.</p>|Dependent item|etcd.bytes.received.rate[{#ETCD.PEER}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Etcd peer {#ETCD.PEER}: Send failures|<p>The number of sent failures from a peer with the ID `{#ETCD.PEER}`.</p>|Dependent item|etcd.sent.fail.rate[{#ETCD.PEER}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Etcd peer {#ETCD.PEER}: Receive failures|<p>The number of received failures from a peer with the ID `{#ETCD.PEER}`.</p>|Dependent item|etcd.received.fail.rate[{#ETCD.PEER}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

