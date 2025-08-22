
# HashiCorp Consul Node by HTTP

## Overview

The template to monitor HashiCorp Consul by Zabbix that works without any external scripts.  
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.  
Do not forget to enable Prometheus format for export metrics.
See [documentation](https://www.consul.io/docs/agent/options#telemetry-prometheus_retention_time).  
More information about metrics you can find in [official documentation](https://www.consul.io/docs/agent/telemetry).  

Template `HashiCorp Consul Node by HTTP` — collects metrics by HTTP agent from /v1/agent/metrics endpoint.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- HashiCorp Consul 1.10.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Internal service metrics are collected from /v1/agent/metrics endpoint.
Do not forget to enable Prometheus format for export metrics. See [documentation](https://www.consul.io/docs/agent/options#telemetry-prometheus_retention_time).
Template need to use Authorization via API token.

Don't forget to change macros {$CONSUL.NODE.API.URL}, {$CONSUL.TOKEN}.  
Also, see the Macros section for a list of macros used to set trigger values. 
More information about metrics you can find in [official documentation](https://www.consul.io/docs/agent/telemetry). 

This template support [Consul namespaces](https://www.consul.io/docs/enterprise/namespaces). You can set macros {$CONSUL.LLD.FILTER.SERVICE_NAMESPACE.MATCHES}, {$CONSUL.LLD.FILTER.SERVICE_NAMESPACE.NOT_MATCHES} if you want to filter discovered services by namespace.  
In case of Open Source version service namespace will be set to 'None'.

*NOTE.* Some metrics may not be collected depending on your HashiCorp Consul instance version and configuration.  
*NOTE.* You maybe are interested in Envoy Proxy by HTTP [template](../../envoy_proxy_http).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CONSUL.NODE.API.URL}|<p>Consul instance URL.</p>|`http://localhost:8500`|
|{$CONSUL.TOKEN}|<p>Consul auth token.</p>||
|{$CONSUL.OPEN.FDS.MAX.WARN}|<p>Maximum percentage of used file descriptors.</p>|`90`|
|{$CONSUL.LLD.FILTER.LOCAL_SERVICE_NAME.MATCHES}|<p>Filter of discoverable discovered services on local node.</p>|`.*`|
|{$CONSUL.LLD.FILTER.LOCAL_SERVICE_NAME.NOT_MATCHES}|<p>Filter to exclude discovered services on local node.</p>|`CHANGE IF NEEDED`|
|{$CONSUL.LLD.FILTER.SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable discovered service by namespace on local node. Enterprise only, in case of Open Source version Namespace will be set to 'None'.</p>|`.*`|
|{$CONSUL.LLD.FILTER.SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered service by namespace on local node. Enterprise only, in case of Open Source version Namespace will be set to 'None'.</p>|`CHANGE IF NEEDED`|
|{$CONSUL.NODE.HEALTH_SCORE.MAX.WARN}|<p>Maximum acceptable value of node's health score for WARNING trigger expression.</p>|`2`|
|{$CONSUL.NODE.HEALTH_SCORE.MAX.HIGH}|<p>Maximum acceptable value of node's health score for AVERAGE trigger expression.</p>|`4`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get instance metrics|<p>Get raw metrics from Consul instance /metrics endpoint.</p>|HTTP agent|consul.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get node info|<p>Get configuration and member information of the local agent.</p>|HTTP agent|consul.get_node_info<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Role|<p>Role of current Consul agent.</p>|Dependent item|consul.role<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Config.Server`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Version|<p>Version of Consul agent.</p>|Dependent item|consul.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Config.Version`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Number of services|<p>Number of services on current node.</p>|Dependent item|consul.services_number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Stats.agent.services`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Number of checks|<p>Number of checks on current node.</p>|Dependent item|consul.checks_number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Stats.agent.checks`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Number of check monitors|<p>Number of check monitors on current node.</p>|Dependent item|consul.check_monitors_number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Stats.agent.check_monitors`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Process CPU seconds, total|<p>Total user and system CPU time spent in seconds.</p>|Dependent item|consul.cpu_seconds_total.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_cpu_seconds_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Virtual memory size|<p>Virtual memory size in bytes.</p>|Dependent item|consul.virtual_memory_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_virtual_memory_bytes)`</p></li></ul>|
|RSS memory usage|<p>Resident memory size in bytes.</p>|Dependent item|consul.resident_memory_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_resident_memory_bytes)`</p></li></ul>|
|Goroutine count|<p>The number of Goroutines on Consul instance.</p>|Dependent item|consul.goroutines<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(go_goroutines)`</p></li></ul>|
|Open file descriptors|<p>Number of open file descriptors.</p>|Dependent item|consul.process_open_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_open_fds)`</p></li></ul>|
|Open file descriptors, max|<p>Maximum number of open file descriptors.</p>|Dependent item|consul.process_max_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_max_fds)`</p></li></ul>|
|Client RPC, per second|<p>Number of times per second whenever a Consul agent in client mode makes an RPC request to a Consul server.</p><p>This gives a measure of how much a given agent is loading the Consul servers.</p><p>This is only generated by agents in client mode, not Consul servers.</p>|Dependent item|consul.client_rpc<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_client_rpc)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Client RPC failed ,per second|<p>Number of times per second whenever a Consul agent in client mode makes an RPC request to a Consul server and fails.</p>|Dependent item|consul.client_rpc_failed<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_client_rpc_failed)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TCP connections, accepted per second|<p>This metric counts the number of times a Consul agent has accepted an incoming TCP stream connection per second.</p>|Dependent item|consul.memberlist.tcp_accept<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_tcp_accept)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TCP connections, per second|<p>This metric counts the number of times a Consul agent has initiated a push/pull sync with an other agent per second.</p>|Dependent item|consul.memberlist.tcp_connect<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_tcp_connect)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TCP send bytes, per second|<p>This metric measures the total number of bytes sent by a Consul agent through the TCP protocol per second.</p>|Dependent item|consul.memberlist.tcp_sent<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_tcp_sent)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|UDP received bytes, per second|<p>This metric measures the total number of bytes received by a Consul agent through the UDP protocol per second.</p>|Dependent item|consul.memberlist.udp_received<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_udp_received)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|UDP sent bytes, per second|<p>This metric measures the total number of bytes sent by a Consul agent through the UDP protocol per second.</p>|Dependent item|consul.memberlist.udp_sent<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_udp_sent)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GC pause, p90|<p>The 90 percentile for the number of nanoseconds consumed by stop-the-world garbage collection (GC) pauses since Consul started, in milliseconds.</p>|Dependent item|consul.gc_pause.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_runtime_gc_pause_ns{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|GC pause, p50|<p>The 50 percentile (median) for the number of nanoseconds consumed by stop-the-world garbage collection (GC) pauses since Consul started, in milliseconds.</p>|Dependent item|consul.gc_pause.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_runtime_gc_pause_ns{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Memberlist: degraded|<p>This metric counts the number of times the Consul agent has performed failure detection on another agent at a slower probe rate.</p><p>The agent uses its own health metric as an indicator to perform this action.</p><p>If its health score is low, it means that the node is healthy, and vice versa.</p>|Dependent item|consul.memberlist.degraded<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_degraded)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memberlist: health score|<p>This metric describes a node's perception of its own health based on how well it is meeting the soft real-time requirements of the protocol.</p><p>This metric ranges from 0 to 8, where 0 indicates "totally healthy".</p>|Dependent item|consul.memberlist.health_score<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_health_score)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memberlist: gossip, p90|<p>The 90 percentile for the number of gossips (messages) broadcasted to a set of randomly selected nodes.</p>|Dependent item|consul.memberlist.dispatch_log.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_gossip{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Memberlist: gossip, p50|<p>The 50 for the number of gossips (messages) broadcasted to a set of randomly selected nodes.</p>|Dependent item|consul.memberlist.gossip.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_gossip{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Memberlist: msg alive|<p>This metric counts the number of alive Consul agents, that the agent has mapped out so far, based on the message information given by the network layer.</p>|Dependent item|consul.memberlist.msg.alive<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_msg_alive)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memberlist: msg dead|<p>This metric counts the number of times a Consul agent has marked another agent to be a dead node.</p>|Dependent item|consul.memberlist.msg.dead<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_msg_dead)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memberlist: msg suspect|<p>The number of times a Consul agent suspects another as failed while probing during gossip protocol.</p>|Dependent item|consul.memberlist.msg.suspect<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_msg_suspect)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memberlist: probe node, p90|<p>The 90 percentile for the time taken to perform a single round of failure detection on a select Consul agent.</p>|Dependent item|consul.memberlist.probe_node.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_probeNode{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Memberlist: probe node, p50|<p>The 50 percentile (median) for the time taken to perform a single round of failure detection on a select Consul agent.</p>|Dependent item|consul.memberlist.probe_node.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_probeNode{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Memberlist: push pull node, p90|<p>The 90 percentile for the number of Consul agents that have exchanged state with this agent.</p>|Dependent item|consul.memberlist.push_pull_node.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_pushPullNode{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Memberlist: push pull node, p50|<p>The 50 percentile (median) for the number of Consul agents that have exchanged state with this agent.</p>|Dependent item|consul.memberlist.push_pull_node.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_pushPullNode{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|KV store: apply, p90|<p>The 90 percentile for the time it takes to complete an update to the KV store.</p>|Dependent item|consul.kvs.apply.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_kvs_apply{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|KV store: apply, p50|<p>The 50 percentile (median) for the time it takes to complete an update to the KV store.</p>|Dependent item|consul.kvs.apply.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_kvs_apply{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|KV store: apply, rate|<p>The number of updates to the KV store per second.</p>|Dependent item|consul.kvs.apply.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_kvs_apply_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Serf member: flap, rate|<p>Increments when an agent is marked dead and then recovers within a short time period.</p><p>This can be an indicator of overloaded agents, network problems, or configuration errors where agents cannot connect to each other on the required ports.</p><p>Shown as events per second.</p>|Dependent item|consul.serf.member.flap.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_member_flap)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Serf member: failed, rate|<p>Increments when an agent is marked dead.</p><p>This can be an indicator of overloaded agents, network problems, or configuration errors where agents cannot connect to each other on the required ports.</p><p>Shown as events per second.</p>|Dependent item|consul.serf.member.failed.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_member_failed)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Serf member: join, rate|<p>Increments when an agent joins the cluster. If an agent flapped or failed this counter also increments when it re-joins.</p><p>Shown as events per second.</p>|Dependent item|consul.serf.member.join.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_member_join)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Serf member: left, rate|<p>Increments when an agent leaves the cluster. Shown as events per second.</p>|Dependent item|consul.serf.member.left.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_member_left)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Serf member: update, rate|<p>Increments when a Consul agent updates. Shown as events per second.</p>|Dependent item|consul.serf.member.update.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_member_update)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|ACL: resolves, rate|<p>The number of ACL resolves per second.</p>|Dependent item|consul.acl.resolves.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_acl_ResolveToken_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Catalog: register, rate|<p>The number of catalog register operation per second.</p>|Dependent item|consul.catalog.register.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_catalog_register_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Catalog: deregister, rate|<p>The number of catalog deregister operation per second.</p>|Dependent item|consul.catalog.deregister.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_catalog_deregister_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Snapshot: append line, p90|<p>The 90 percentile for the time taken by the Consul agent to append an entry into the existing log.</p>|Dependent item|consul.snapshot.append_line.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_snapshot_appendLine{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Snapshot: append line, p50|<p>The 50 percentile (median) for the time taken by the Consul agent to append an entry into the existing log.</p>|Dependent item|consul.snapshot.append_line.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_snapshot_appendLine{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Snapshot: append line, rate|<p>The number of snapshot appendLine operations per second.</p>|Dependent item|consul.snapshot.append_line.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_snapshot_appendLine_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Snapshot: compact, p90|<p>The 90 percentile for the time taken by the Consul agent to compact a log.</p><p>This operation occurs only when the snapshot becomes large enough to justify the compaction.</p>|Dependent item|consul.snapshot.compact.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_snapshot_compact{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Snapshot: compact, p50|<p>The 50 percentile (median) for the time taken by the Consul agent to compact a log.</p><p>This operation occurs only when the snapshot becomes large enough to justify the compaction.</p>|Dependent item|consul.snapshot.compact.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_snapshot_compact{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Snapshot: compact, rate|<p>The number of snapshot compact operations per second.</p>|Dependent item|consul.snapshot.compact.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_serf_snapshot_compact_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Get local services|<p>Get all the services that are registered with the local agent and their status.</p>|Script|consul.get_local_services|
|Get local services check|<p>Data collection check.</p>|Dependent item|consul.get_local_services.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HashiCorp Consul Node: Version has been changed|<p>Consul version has changed. Acknowledge to close the problem manually.</p>|`last(/HashiCorp Consul Node by HTTP/consul.version,#1)<>last(/HashiCorp Consul Node by HTTP/consul.version,#2) and length(last(/HashiCorp Consul Node by HTTP/consul.version))>0`|Info|**Manual close**: Yes|
|HashiCorp Consul Node: Current number of open files is too high|<p>"Heavy file descriptor usage (i.e., near the process’s file descriptor limit) indicates a potential file descriptor exhaustion issue."</p>|`min(/HashiCorp Consul Node by HTTP/consul.process_open_fds,5m)/last(/HashiCorp Consul Node by HTTP/consul.process_max_fds)*100>{$CONSUL.OPEN.FDS.MAX.WARN}`|Warning||
|HashiCorp Consul Node: Node's health score is warning|<p>This metric ranges from 0 to 8, where 0 indicates "totally healthy".<br>This health score is used to scale the time between outgoing probes, and higher scores translate into longer probing intervals.<br>For more details see section IV of the Lifeguard paper: https://arxiv.org/pdf/1707.00788.pdf</p>|`max(/HashiCorp Consul Node by HTTP/consul.memberlist.health_score,#3)>{$CONSUL.NODE.HEALTH_SCORE.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>HashiCorp Consul Node: Node's health score is critical</li></ul>|
|HashiCorp Consul Node: Node's health score is critical|<p>This metric ranges from 0 to 8, where 0 indicates "totally healthy".<br>This health score is used to scale the time between outgoing probes, and higher scores translate into longer probing intervals.<br>For more details see section IV of the Lifeguard paper: https://arxiv.org/pdf/1707.00788.pdf</p>|`max(/HashiCorp Consul Node by HTTP/consul.memberlist.health_score,#3)>{$CONSUL.NODE.HEALTH_SCORE.MAX.HIGH}`|Average||
|HashiCorp Consul Node: Failed to get local services|<p>Failed to get local services. Check debug log for more information.</p>|`length(last(/HashiCorp Consul Node by HTTP/consul.get_local_services.check))>0`|Warning||

### LLD rule Local node services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Local node services discovery|<p>Discover metrics for services that are registered with the local agent.</p>|Dependent item|consul.node_services_lld<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Local node services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|["{#SERVICE_NAME}"]: Aggregated status|<p>Aggregated values of all health checks for the service instance.</p>|Dependent item|consul.service.aggregated_state["{#SERVICE_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == "{#SERVICE_ID}")].status.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|["{#SERVICE_NAME}"]: Check ["{#SERVICE_CHECK_NAME}"]: Status|<p>Current state of health check for the service.</p>|Dependent item|consul.service.check.state["{#SERVICE_ID}/{#SERVICE_CHECK_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|["{#SERVICE_NAME}"]: Check ["{#SERVICE_CHECK_NAME}"]: Output|<p>Current output of health check for the service.</p>|Dependent item|consul.service.check.output["{#SERVICE_ID}/{#SERVICE_CHECK_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Local node services discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HashiCorp Consul Node: Aggregated status is 'warning'|<p>Aggregated state of service on the local agent is 'warning'.</p>|`last(/HashiCorp Consul Node by HTTP/consul.service.aggregated_state["{#SERVICE_ID}"]) = 1`|Warning||
|HashiCorp Consul Node: Aggregated status is 'critical'|<p>Aggregated state of service on the local agent is 'critical'.</p>|`last(/HashiCorp Consul Node by HTTP/consul.service.aggregated_state["{#SERVICE_ID}"]) = 2`|Average||

### LLD rule HTTP API methods discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HTTP API methods discovery|<p>Discovery HTTP API methods specific metrics.</p>|Dependent item|consul.http_api_discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `consul_api_http{method =~ ".*"}`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for HTTP API methods discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HTTP request: ["{#HTTP_METHOD}"], p90|<p>The 90 percentile of how long it takes to service the given HTTP request for the given verb.</p>|Dependent item|consul.http.api.p90["{#HTTP_METHOD}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HTTP request: ["{#HTTP_METHOD}"], p50|<p>The 50 percentile (median) of how long it takes to service the given HTTP request for the given verb.</p>|Dependent item|consul.http.api.p50["{#HTTP_METHOD}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HTTP request: ["{#HTTP_METHOD}"], rate|<p>The number of HTTP request for the given verb per second.</p>|Dependent item|consul.http.api.rate["{#HTTP_METHOD}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(consul_api_http_count{method = "{#HTTP_METHOD}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### LLD rule Raft server metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Raft server metrics discovery|<p>Discover raft metrics for server nodes.</p>|Dependent item|consul.raft.server.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Raft server metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Raft state|<p>Current state of Consul agent.</p>|Dependent item|consul.raft.state[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Stats.raft.state`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Raft state: leader|<p>Increments when a server becomes a leader.</p>|Dependent item|consul.raft.state_leader[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_state_leader)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Raft state: candidate|<p>The number of initiated leader elections.</p>|Dependent item|consul.raft.state_candidate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_state_candidate)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Raft: apply, rate|<p>Incremented whenever a leader first passes a message into the Raft commit process (called an Apply operation).</p><p>This metric describes the arrival rate of new logs into Raft per second.</p>|Dependent item|consul.raft.apply.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_apply)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### LLD rule Raft leader metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Raft leader metrics discovery|<p>Discover raft metrics for leader nodes.</p>|Dependent item|consul.raft.leader.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Raft leader metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Raft state: leader last contact, p90|<p>The 90 percentile of how long it takes a leader node to communicate with followers during a leader lease check, in milliseconds.</p>|Dependent item|consul.raft.leader_last_contact.p90[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_leader_lastContact{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: leader last contact, p50|<p>The 50 percentile (median) of how long it takes a leader node to communicate with followers during a leader lease check, in milliseconds.</p>|Dependent item|consul.raft.leader_last_contact.p50[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_leader_lastContact{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: commit time, p90|<p>The 90 percentile time it takes to commit a new entry to the raft log on the leader, in milliseconds.</p>|Dependent item|consul.raft.commit_time.p90[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_commitTime{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: commit time, p50|<p>The 50 percentile (median) time it takes to commit a new entry to the raft log on the leader, in milliseconds.</p>|Dependent item|consul.raft.commit_time.p50[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_commitTime{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: dispatch log, p90|<p>The 90 percentile time it takes for the leader to write log entries to disk, in milliseconds.</p>|Dependent item|consul.raft.dispatch_log.p90[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_leader_dispatchLog{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: dispatch log, p50|<p>The 50 percentile (median) time it takes for the leader to write log entries to disk, in milliseconds.</p>|Dependent item|consul.raft.dispatch_log.p50[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_leader_dispatchLog{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: dispatch log, rate|<p>The number of times a Raft leader writes a log to disk per second.</p>|Dependent item|consul.raft.dispatch_log.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_leader_dispatchLog_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Raft state: commit, rate|<p>The number of commits a new entry to the Raft log on the leader per second.</p>|Dependent item|consul.raft.commit_time.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_commitTime_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Autopilot healthy|<p>Tracks the overall health of the local server cluster. 1 if all servers are healthy, 0 if one or more are unhealthy.</p>|Dependent item|consul.autopilot.healthy[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_autopilot_healthy)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

