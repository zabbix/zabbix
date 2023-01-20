
# Etcd by HTTP

## Overview

This template is designed to monitor `etcd` by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template `Etcd by HTTP` — collects metrics by help of the HTTP agent from `/metrics` endpoint.

> Refer to the [vendor documentation](https://etcd.io/docs/v3.5/op-guide/monitoring/#metrics-endpoint).

**For the users of `etcd version <= 3.4` !**

> In `etcd v3.5` some metrics have been deprecated. See more details on [Upgrade etcd from 3.4 to 3.5](https://etcd.io/docs/v3.4/upgrades/upgrade_3_5/).
Please upgrade your `etcd` instance, or use older `Etcd by HTTP` template version.



This template has been tested on:

- Etcd, version 3.5.6

## Requirements

For Zabbix version: 6.4 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/http) for basic instructions.

Follow these instructions:

1. Import the template into Zabbix.
2. After importing the template, make sure that `etcd` allows the collection of metrics. You can test it by running: `curl -L http://localhost:2379/metrics`.
3. Check if `etcd` is accessible from Zabbix proxy or Zabbix server depending on where you are planning to do the monitoring. To verify it,  run `curl -L  http://<etcd_node_address>:2379/metrics`.
4. Add the template to each `etcd node`. By default, the template uses a client's port.
You can configure metrics endpoint location by adding `--listen-metrics-urls flag`.
(For more details, see [etcd documentation](https://etcd.io/docs/v3.5/op-guide/configuration/#profiling-and-monitoring)).

Additional points to consider:

-  If you have specified a non-standard port for `etcd`, don't forget to change macros: `{$ETCD.SCHEME}` and `{$ETCD.PORT}`.
-  You can set `{$ETCD.USERNAME}` and `{$ETCD.PASSWORD}` macros in the template to use on a host level if necessary.
-  To test availability, run : `zabbix_get -s etcd-host -k etcd.health`.
-  See the macros section, as it will set the trigger values.


## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ETCD.GRPC.ERRORS.MAX.WARN} |<p>The maximum number of gRPC request failures.</p> |`1` |
|{$ETCD.GRPC_CODE.MATCHES} |<p>The filter of discoverable gRPC codes. See more details on https://github.com/grpc/grpc/blob/master/doc/statuscodes.md.</p> |`.*` |
|{$ETCD.GRPC_CODE.NOT_MATCHES} |<p>The filter to exclude discovered gRPC codes. See more details on https://github.com/grpc/grpc/blob/master/doc/statuscodes.md.</p> |`CHANGE_IF_NEEDED` |
|{$ETCD.GRPC_CODE.TRIGGER.MATCHES} |<p>The filter of discoverable gRPC codes, which will create triggers.</p> |`Aborted|Unavailable` |
|{$ETCD.HTTP.FAIL.MAX.WARN} |<p>The maximum number of HTTP request failures.</p> |`2` |
|{$ETCD.LEADER.CHANGES.MAX.WARN} |<p>The maximum number of leader changes.</p> |`5` |
|{$ETCD.OPEN.FDS.MAX.WARN} |<p>The maximum percentage of used file descriptors.</p> |`90` |
|{$ETCD.PASSWORD} |<p>-</p> |`` |
|{$ETCD.PORT} |<p>The port of `etcd` API endpoint.</p> |`2379` |
|{$ETCD.PROPOSAL.FAIL.MAX.WARN} |<p>The maximum number of proposal failures.</p> |`2` |
|{$ETCD.PROPOSAL.PENDING.MAX.WARN} |<p>The maximum number of proposals in queue.</p> |`5` |
|{$ETCD.SCHEME} |<p>The request scheme which may be `http` or `https`.</p> |`http` |
|{$ETCD.USER} |<p>-</p> |`` |

### Template links

There are no template links in this template.

### Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|gRPC codes discovery |<p>-</p> |DEPENDENT |etcd.grpc_code.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `grpc_server_handled_total`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Filter**:</p>AND <p>- {#GRPC.CODE} NOT_MATCHES_REGEX `{$ETCD.GRPC_CODE.NOT_MATCHES}`</p><p>- {#GRPC.CODE} MATCHES_REGEX `{$ETCD.GRPC_CODE.MATCHES}`</p><p>**Overrides:**</p><p>trigger<br> - {#GRPC.CODE} MATCHES_REGEX `{$ETCD.GRPC_CODE.TRIGGER.MATCHES}`<br>  - TRIGGER_PROTOTYPE LIKE `Too many failed gRPC requests`<br>  - DISCOVER</p> |
|Peers discovery |<p>-</p> |DEPENDENT |etcd.peer.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `etcd_network_peer_sent_bytes_total`</p> |

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Etcd |Etcd: Service's TCP port state |<p>-</p> |SIMPLE |net.tcp.service["{$ETCD.SCHEME}","{HOST.CONN}","{$ETCD.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Etcd |Etcd: Node health |<p>-</p> |HTTP_AGENT |etcd.health<p>**Preprocessing**:</p><p>- JSONPATH: `$.health`</p><p>- BOOL_TO_DECIMAL</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Etcd |Etcd: Server is a leader |<p>It defines - whether or not this member is a leader:</p><p>1 - it is;</p><p>0 - otherwise.</p> |DEPENDENT |etcd.is.leader<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_server_is_leader`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Etcd |Etcd: Server has a leader |<p>It defines - whether or not a leader exists:</p><p>1 - it exists;</p><p>0 - it does not.</p> |DEPENDENT |etcd.has.leader<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_server_has_leader`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Etcd |Etcd: Leader changes |<p>The number of leader changes the member has seen since its start.</p> |DEPENDENT |etcd.leader.changes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_server_leader_changes_seen_total`</p> |
|Etcd |Etcd: Proposals committed per second |<p>The number of consensus proposals committed.</p> |DEPENDENT |etcd.proposals.committed.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_server_proposals_committed_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Proposals applied per second |<p>The number of consensus proposals applied.</p> |DEPENDENT |etcd.proposals.applied.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_server_proposals_applied_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Proposals failed per second |<p>The number of failed proposals seen.</p> |DEPENDENT |etcd.proposals.failed.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_server_proposals_failed_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Proposals pending |<p>The current number of pending proposals to commit.</p> |DEPENDENT |etcd.proposals.pending<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_server_proposals_pending`</p> |
|Etcd |Etcd: Reads per second |<p>The number of read actions by `get/getRecursive`, local to this member.</p> |DEPENDENT |etcd.reads.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `etcd_debugging_store_reads_total`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Writes per second |<p>The number of writes (e.g., `set/compareAndDelete`) seen by this member.</p> |DEPENDENT |etcd.writes.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `etcd_debugging_store_writes_total`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Client gRPC received bytes per second |<p>The number of bytes received from gRPC clients per second.</p> |DEPENDENT |etcd.network.grpc.received.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_network_client_grpc_received_bytes_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Client gRPC sent bytes per second |<p>The number of bytes sent from gRPC clients per second.</p> |DEPENDENT |etcd.network.grpc.sent.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_network_client_grpc_sent_bytes_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: HTTP requests received |<p>The number of requests received into the system (successfully parsed and `authd`).</p> |DEPENDENT |etcd.http.requests.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `etcd_http_received_total`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: HTTP 5XX |<p>The number of handled failures of requests (non-watches), by the method (`GET/PUT` etc.), and the code `5XX`.</p> |DEPENDENT |etcd.http.requests.5xx.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `etcd_http_failed_total{code=~"5.+"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: HTTP 4XX |<p>The number of handled failures of requests (non-watches), by the method (`GET/PUT` etc.), and the code `4XX`.</p> |DEPENDENT |etcd.http.requests.4xx.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `etcd_http_failed_total{code=~"4.+"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: RPCs received per second |<p>The number of RPC stream messages received on the server.</p> |DEPENDENT |etcd.grpc.received.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `grpc_server_msg_received_total`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: RPCs sent per second |<p>The number of gRPC stream messages sent by the server.</p> |DEPENDENT |etcd.grpc.sent.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `grpc_server_msg_sent_total`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: RPCs started per second |<p>The number of RPCs started on the server.</p> |DEPENDENT |etcd.grpc.started.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `grpc_server_started_total`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Server version |<p>The version of the `etcd server`.</p> |DEPENDENT |etcd.server.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.etcdserver`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Etcd |Etcd: Cluster version |<p>The version of the `etcd cluster`.</p> |DEPENDENT |etcd.cluster.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.etcdcluster`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Etcd |Etcd: DB size |<p>The total size of the underlying database.</p> |DEPENDENT |etcd.db.size<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_mvcc_db_total_size_in_bytes`</p> |
|Etcd |Etcd: Keys compacted per second |<p>The number of DB keys compacted per second.</p> |DEPENDENT |etcd.keys.compacted.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_debugging_mvcc_db_compaction_keys_total`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Keys expired per second |<p>The number of expired keys per second.</p> |DEPENDENT |etcd.keys.expired.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_debugging_store_expires_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Keys total |<p>The total number of keys.</p> |DEPENDENT |etcd.keys.total<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_debugging_mvcc_keys_total`</p> |
|Etcd |Etcd: Uptime |<p>`Etcd` server uptime.</p> |DEPENDENT |etcd.uptime<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_start_time_seconds`</p><p>- JAVASCRIPT: `//use boottime to calculate uptime return (Math.floor(Date.now()/1000)-Number(value)); `</p> |
|Etcd |Etcd: Virtual memory |<p>The size of virtual memory expressed in bytes.</p> |DEPENDENT |etcd.virtual.bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_virtual_memory_bytes`</p> |
|Etcd |Etcd: Resident memory |<p>The size of resident memory expressed in bytes.</p> |DEPENDENT |etcd.res.bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_resident_memory_bytes`</p> |
|Etcd |Etcd: CPU |<p>The total user and system CPU time spent in seconds.</p> |DEPENDENT |etcd.cpu.util<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_cpu_seconds_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Open file descriptors |<p>The number of open file descriptors.</p> |DEPENDENT |etcd.open.fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_open_fds`</p> |
|Etcd |Etcd: Maximum open file descriptors |<p>The Maximum number of open file descriptors.</p> |DEPENDENT |etcd.max.fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_max_fds`</p> |
|Etcd |Etcd: Deletes per second |<p>The number of deletes seen by this member per second.</p> |DEPENDENT |etcd.delete.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_mvcc_delete_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: PUT per second |<p>The number of puts seen by this member per second.</p> |DEPENDENT |etcd.put.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_mvcc_put_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Range per second |<p>The number of ranges seen by this member per second.</p> |DEPENDENT |etcd.range.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_debugging_mvcc_range_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Transaction per second |<p>The number of transactions seen by this member per second.</p> |DEPENDENT |etcd.txn.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_debugging_mvcc_range_total`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Pending events |<p>The total number of pending events to be sent.</p> |DEPENDENT |etcd.events.sent.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_debugging_mvcc_pending_events_total`</p> |
|Etcd |Etcd: RPCs completed with code {#GRPC.CODE} |<p>The number of RPCs completed on the server with grpc_code {#GRPC.CODE}.</p> |DEPENDENT |etcd.grpc.handled.rate[{#GRPC.CODE}]<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `grpc_server_handled_total{grpc_method="{#GRPC.CODE}"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Etcd peer {#ETCD.PEER}: Bytes sent |<p>The number of bytes sent to a peer with the ID `{#ETCD.PEER}`.</p> |DEPENDENT |etcd.bytes.sent.rate[{#ETCD.PEER}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_network_peer_sent_bytes_total{To="{#ETCD.PEER}"}`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Etcd peer {#ETCD.PEER}: Bytes received |<p>The number of bytes received from a peer with the ID `{#ETCD.PEER}`.</p> |DEPENDENT |etcd.bytes.received.rate[{#ETCD.PEER}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_network_peer_received_bytes_total{From="{#ETCD.PEER}"}`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Etcd peer {#ETCD.PEER}: Send failures |<p>The number of sent failures from a peer with the ID `{#ETCD.PEER}`.</p> |DEPENDENT |etcd.sent.fail.rate[{#ETCD.PEER}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_network_peer_sent_failures_total{To="{#ETCD.PEER}"}`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Etcd |Etcd: Etcd peer {#ETCD.PEER}: Receive failures |<p>The number of received failures from a peer with the ID `{#ETCD.PEER}`.</p> |DEPENDENT |etcd.received.fail.rate[{#ETCD.PEER}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_network_peer_received_failures_total{To="{#ETCD.PEER}"}`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix raw items |Etcd: Get node metrics |<p>-</p> |HTTP_AGENT |etcd.get_metrics |
|Zabbix raw items |Etcd: Get version |<p>-</p> |HTTP_AGENT |etcd.get_version |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Etcd: Service is unavailable |<p>-</p> |`last(/Etcd by HTTP/net.tcp.service["{$ETCD.SCHEME}","{HOST.CONN}","{$ETCD.PORT}"])=0` |AVERAGE |<p>Manual close: YES</p> |
|Etcd: Node healthcheck failed |<p>See more details on https://etcd.io/docs/v3.5/op-guide/monitoring/#health-check.</p> |`last(/Etcd by HTTP/etcd.health)=0` |AVERAGE |<p>**Depends on**:</p><p>- Etcd: Service is unavailable</p> |
|Etcd: Failed to fetch info data |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`nodata(/Etcd by HTTP/etcd.is.leader,30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Etcd: Service is unavailable</p> |
|Etcd: Member has no leader |<p>If a member does not have a leader, it is totally unavailable.</p> |`last(/Etcd by HTTP/etcd.has.leader)=0` |AVERAGE | |
|Etcd: Instance has seen too many leader changes |<p>Rapid leadership changes impact the performance of `etcd` significantly. It also signals that the leader is unstable, perhaps due to network connectivity issues or excessive load hitting the `etcd cluster`.</p> |`(max(/Etcd by HTTP/etcd.leader.changes,15m)-min(/Etcd by HTTP/etcd.leader.changes,15m))>{$ETCD.LEADER.CHANGES.MAX.WARN}` |WARNING | |
|Etcd: Too many proposal failures |<p>Normally related to two issues: temporary failures related to a leader election or longer downtime caused by a loss of quorum in the cluster.</p> |`min(/Etcd by HTTP/etcd.proposals.failed.rate,5m)>{$ETCD.PROPOSAL.FAIL.MAX.WARN}` |WARNING | |
|Etcd: Too many proposals are queued to commit |<p>Rising pending proposals suggests there is a high client load, or the member cannot commit proposals.</p> |`min(/Etcd by HTTP/etcd.proposals.pending,5m)>{$ETCD.PROPOSAL.PENDING.MAX.WARN}` |WARNING | |
|Etcd: Too many HTTP requests failures |<p>Too many requests failed on `etcd` instance with the `5xx HTTP code`.</p> |`min(/Etcd by HTTP/etcd.http.requests.5xx.rate,5m)>{$ETCD.HTTP.FAIL.MAX.WARN}` |WARNING | |
|Etcd: Server version has changed |<p>The Etcd version has changed. Acknowledge to close manually.</p> |`last(/Etcd by HTTP/etcd.server.version,#1)<>last(/Etcd by HTTP/etcd.server.version,#2) and length(last(/Etcd by HTTP/etcd.server.version))>0` |INFO |<p>Manual close: YES</p> |
|Etcd: Cluster version has changed |<p>The Etcd version has changed. Acknowledge to close manually.</p> |`last(/Etcd by HTTP/etcd.cluster.version,#1)<>last(/Etcd by HTTP/etcd.cluster.version,#2) and length(last(/Etcd by HTTP/etcd.cluster.version))>0` |INFO |<p>Manual close: YES</p> |
|Etcd: Host has been restarted |<p>The host uptime is less than 10 minutes.</p> |`last(/Etcd by HTTP/etcd.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Etcd: Current number of open files is too high |<p>Heavy usage of a file descriptor (i.e., near the limit of the process's file descriptor) indicates a potential file descriptor exhaustion issue.</p><p>If the file descriptors are exhausted, `etcd` may panic because it cannot create new WAL files.</p> |`min(/Etcd by HTTP/etcd.open.fds,5m)/last(/Etcd by HTTP/etcd.max.fds)*100>{$ETCD.OPEN.FDS.MAX.WARN}` |WARNING | |
|Etcd: Too many failed gRPC requests with code: {#GRPC.CODE} |<p>-</p> |`min(/Etcd by HTTP/etcd.grpc.handled.rate[{#GRPC.CODE}],5m)>{$ETCD.GRPC.ERRORS.MAX.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

