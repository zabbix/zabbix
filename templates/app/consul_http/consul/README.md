
# HashiCorp Consul Node by HTTP

## Overview

The template to monitor HashiCorp Consul by Zabbix that works without any external scripts.  
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.  
Do not forget to enable Prometheus format for export metrics.
See [documentation](https://www.consul.io/docs/agent/options#telemetry-prometheus_retention_time).  
More information about metrics you can find in [official documentation](https://www.consul.io/docs/agent/telemetry).  

Template `HashiCorp Consul Node by HTTP` — collects metrics by HTTP agent from /v1/agent/metrics endpoint.

## Requirements

Zabbix version: 8.2 and higher.

## Tested versions

This template has been tested on:
- HashiCorp Consul 2.0.4

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.2/manual/config/templates_out_of_the_box) section.

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
|{$HTTP.TLS.VERIFY}|<p>TLS certificate verification for the script item: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this template only, define the macro with the context "HashiCorp Consul Node", e.g. {$HTTP.TLS.VERIFY:"HashiCorp Consul Node"}.</p>|`full`|
|{$CONSUL.NODE.API.URL}|<p>Consul instance URL.</p>|`http://localhost:8500`|
|{$CONSUL.TOKEN}|<p>Consul auth token.</p>||
|{$CONSUL.OPEN.FDS.MAX.WARN}|<p>Maximum percentage of used file descriptors.</p>|`90`|
|{$CONSUL.TLS.CERT.EXPIRY.MIN}|<p>Minimum acceptable time before TLS certificate expiry for the trigger expression.</p>|`7d`|
|{$CONSUL.LEAF.CERT.EXPIRY.MIN}|<p>Minimum acceptable time before Connect leaf certificate expiry for the trigger expression. Leaf certs typically rotate much more often than the agent TLS cert (hours, not days).</p>|`6h`|
|{$CONSUL.MESH.ROOT_CA.EXPIRY.MIN}|<p>Minimum acceptable time before service mesh root CA certificate expiry for the trigger expression. Root CA rotation is a rare, planned operation — keep this threshold generous.</p>|`30d`|
|{$CONSUL.MESH.SIGNING_CA.EXPIRY.MIN}|<p>Minimum acceptable time before service mesh signing CA certificate expiry for the trigger expression.</p>|`30d`|
|{$CONSUL.LICENSE.EXPIRY.MIN}|<p>Minimum acceptable time before Consul Enterprise license expiry for the trigger expression. Enterprise only.</p>|`30d`|
|{$CONSUL.LLD.FILTER.LOCAL_SERVICE_NAME.MATCHES}|<p>Filter of discoverable discovered services on local node.</p>|`.*`|
|{$CONSUL.LLD.FILTER.LOCAL_SERVICE_NAME.NOT_MATCHES}|<p>Filter to exclude discovered services on local node.</p>|`CHANGE IF NEEDED`|
|{$CONSUL.LLD.FILTER.SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable discovered service by namespace on local node. Enterprise only, in case of Open Source version Namespace will be set to 'None'.</p>|`.*`|
|{$CONSUL.LLD.FILTER.SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered service by namespace on local node. Enterprise only, in case of Open Source version Namespace will be set to 'None'.</p>|`CHANGE IF NEEDED`|

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
|Client RPC failed, per second|<p>Number of times per second whenever a Consul agent in client mode makes an RPC request to a Consul server and fails.</p>|Dependent item|consul.client_rpc_failed<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_client_rpc_failed)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Client RPC exceeded, per second|<p>Number of times per second whenever a Consul agent makes an RPC request to a Consul server and gets rate limited by that agent's limits configuration.</p>|Dependent item|consul.client_rpc_exceeded<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_client_rpc_exceeded)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|RPC: Request errors, per second|<p>Number of times per second a server returns an error from an RPC request.</p>|Dependent item|consul.rpc.request_error.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_rpc_request_error)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|RPC: Rate limit exceeded, per second|<p>Number of times per second an RPC is over a configured rate limit. Note: in permissive mode, the RPC will have still been allowed to proceed.</p>|Dependent item|consul.rpc.rate_limit_exceeded.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_rpc_rate_limit_exceeded)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TCP connections, accepted per second|<p>This metric counts the number of times a Consul agent has accepted an incoming TCP stream connection per second.</p>|Dependent item|consul.memberlist.tcp_accept<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_tcp_accept)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TCP connections, per second|<p>This metric counts the number of times a Consul agent has initiated a push/pull sync with an other agent per second.</p>|Dependent item|consul.memberlist.tcp_connect<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_tcp_connect)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TCP send bytes, per second|<p>This metric measures the total number of bytes sent by a Consul agent through the TCP protocol per second.</p>|Dependent item|consul.memberlist.tcp_sent<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_tcp_sent)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|UDP received bytes, per second|<p>This metric measures the total number of bytes received by a Consul agent through the UDP protocol per second.</p>|Dependent item|consul.memberlist.udp_received<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_udp_received)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|UDP sent bytes, per second|<p>This metric measures the total number of bytes sent by a Consul agent through the UDP protocol per second.</p>|Dependent item|consul.memberlist.udp_sent<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_udp_sent)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GC pause, p90|<p>The 90 percentile for the number of nanoseconds consumed by stop-the-world garbage collection (GC) pauses since Consul started, in milliseconds.</p>|Dependent item|consul.gc_pause.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_runtime_gc_pause_ns{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|GC pause, p50|<p>The 50 percentile (median) for the number of nanoseconds consumed by stop-the-world garbage collection (GC) pauses since Consul started, in milliseconds.</p>|Dependent item|consul.gc_pause.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_runtime_gc_pause_ns{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Memberlist: Gossip, p90|<p>The 90 percentile for the number of gossips (messages) broadcasted to a set of randomly selected nodes.</p>|Dependent item|consul.memberlist.dispatch_log.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_gossip{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Memberlist: Gossip, p50|<p>The 50 for the number of gossips (messages) broadcasted to a set of randomly selected nodes.</p>|Dependent item|consul.memberlist.gossip.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_gossip{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Memberlist: Probe node, p90|<p>The 90 percentile for the time taken to perform a single round of failure detection on a select Consul agent.</p>|Dependent item|consul.memberlist.probe_node.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_probeNode{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Memberlist: Probe node, p50|<p>The 50 percentile (median) for the time taken to perform a single round of failure detection on a select Consul agent.</p>|Dependent item|consul.memberlist.probe_node.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_probeNode{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Memberlist: Push pull node, p90|<p>The 90 percentile for the number of Consul agents that have exchanged state with this agent.</p>|Dependent item|consul.memberlist.push_pull_node.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_pushPullNode{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Memberlist: Push pull node, p50|<p>The 50 percentile (median) for the number of Consul agents that have exchanged state with this agent.</p>|Dependent item|consul.memberlist.push_pull_node.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_memberlist_pushPullNode{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|KV store: Apply, p90|<p>The 90 percentile for the time it takes to complete an update to the KV store.</p>|Dependent item|consul.kvs.apply.p90<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_kvs_apply{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|KV store: Apply, p50|<p>The 50 percentile (median) for the time it takes to complete an update to the KV store.</p>|Dependent item|consul.kvs.apply.p50<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_kvs_apply{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|KV store: Apply, rate|<p>The number of updates to the KV store per second.</p>|Dependent item|consul.kvs.apply.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_kvs_apply_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|ACL: Resolves, rate|<p>The number of ACL resolves per second.</p>|Dependent item|consul.acl.resolves.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_acl_ResolveToken_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Catalog: Register, rate|<p>The number of catalog register operation per second.</p>|Dependent item|consul.catalog.register.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_catalog_register_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Catalog: Deregister, rate|<p>The number of catalog deregister operation per second.</p>|Dependent item|consul.catalog.deregister.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_catalog_deregister_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Cache: Entries count|<p>Represents the number of entries in the agent's cache.</p>|Dependent item|consul.cache.entries_count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_cache_entries_count)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Cache: Fetch errors, per second|<p>Number of failed fetches by the cache per second.</p>|Dependent item|consul.cache.fetch_error.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_cache_fetch_error)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Cache: Bypass, per second|<p>Number of times per second a request bypassed the cache because no cache-key was provided.</p>|Dependent item|consul.cache.bypass.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_cache_bypass)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|TLS certificate expiry|<p>Seconds until the agent TLS certificate expires. Updated every hour.</p>|Dependent item|consul.tls_cert_expiry<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_agent_tls_cert_expiry)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Leaf certificate expiry|<p>Seconds until the soonest-expiring Connect leaf certificate across all registered services expires. Leaf certificates are short-lived and renewed automatically well before expiry.</p>|Dependent item|consul.leaf_cert_expiry<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `MIN(consul_leaf_certs_cert_expiry)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Leaf certificate renewal failures|<p>Number of failures to renew the Connect leaf certificate.</p>|Dependent item|consul.leaf_cert_renewal_failure<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(consul_leaf_certs_cert_renewal_failure)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Mesh root CA expiry|<p>Seconds until the service mesh root certificate expires. Updated every hour.</p>|Dependent item|consul.mesh_root_ca_expiry<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_mesh_active_root_ca_expiry)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Mesh signing CA expiry|<p>Seconds until the service mesh signing (intermediate) certificate expires. Updated every hour.</p>|Dependent item|consul.mesh_signing_ca_expiry<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_mesh_active_signing_ca_expiry)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|License expiry|<p>Seconds until the Consul Enterprise license expires. Enterprise only.</p>|Dependent item|consul.license_expiry<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_system_licenseExpiration)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `3600`</p></li></ul>|
|Get local services|<p>Get all the services that are registered with the local agent and their status.</p>|Script|consul.get_local_services|
|Get local services check|<p>Data collection check.</p>|Dependent item|consul.get_local_services.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HashiCorp Consul Node: Version has been changed|<p>Consul version has changed. Acknowledge to close the problem manually.</p>|`last(/HashiCorp Consul Node by HTTP/consul.version,#1)<>last(/HashiCorp Consul Node by HTTP/consul.version,#2) and length(last(/HashiCorp Consul Node by HTTP/consul.version))>0`|Info|**Manual close**: Yes|
|HashiCorp Consul Node: Current number of open files is too high|<p>"Heavy file descriptor usage (i.e., near the process’s file descriptor limit) indicates a potential file descriptor exhaustion issue."</p>|`min(/HashiCorp Consul Node by HTTP/consul.process_open_fds,5m)/last(/HashiCorp Consul Node by HTTP/consul.process_max_fds)*100>{$CONSUL.OPEN.FDS.MAX.WARN}`|Warning||
|HashiCorp Consul Node: TLS certificate expires soon|<p>Agent's TLS certificate will expire soon. Renew it before expiration to avoid connectivity issues.</p>|`last(/HashiCorp Consul Node by HTTP/consul.tls_cert_expiry)>0 and last(/HashiCorp Consul Node by HTTP/consul.tls_cert_expiry)<{$CONSUL.TLS.CERT.EXPIRY.MIN}`|Warning||
|HashiCorp Consul Node: TLS certificate has already expired|<p>Agent's TLS certificate has expired. Connectivity relying on this certificate is likely broken.</p>|`last(/HashiCorp Consul Node by HTTP/consul.tls_cert_expiry)<0`|High||
|HashiCorp Consul Node: Leaf certificate expires soon|<p>Connect leaf certificate will expire soon and has not been renewed in time. This may indicate a problem with automatic certificate renewal.</p>|`last(/HashiCorp Consul Node by HTTP/consul.leaf_cert_expiry)>0 and last(/HashiCorp Consul Node by HTTP/consul.leaf_cert_expiry)<{$CONSUL.LEAF.CERT.EXPIRY.MIN}`|Warning||
|HashiCorp Consul Node: Leaf certificate has already expired|<p>Connect leaf certificate has expired. Services in the mesh relying on it have likely lost connectivity.</p>|`last(/HashiCorp Consul Node by HTTP/consul.leaf_cert_expiry)<0`|High||
|HashiCorp Consul Node: Leaf certificate renewal failed|<p>Connect leaf certificate renewal has failed. Services in the mesh may lose connectivity once the current certificate expires.</p>|`last(/HashiCorp Consul Node by HTTP/consul.leaf_cert_renewal_failure)>0`|Warning||
|HashiCorp Consul Node: Mesh root CA expires soon|<p>Service mesh root CA certificate will expire soon. Root CA rotation requires planning across the whole mesh — do not leave this until the last moment.</p>|`last(/HashiCorp Consul Node by HTTP/consul.mesh_root_ca_expiry)>0 and last(/HashiCorp Consul Node by HTTP/consul.mesh_root_ca_expiry)<{$CONSUL.MESH.ROOT_CA.EXPIRY.MIN}`|Warning||
|HashiCorp Consul Node: Mesh root CA has already expired|<p>Service mesh root CA certificate has expired. This is likely causing a mesh-wide outage.</p>|`last(/HashiCorp Consul Node by HTTP/consul.mesh_root_ca_expiry)<0`|High||
|HashiCorp Consul Node: Mesh signing CA expires soon|<p>Service mesh signing CA certificate will expire soon.</p>|`last(/HashiCorp Consul Node by HTTP/consul.mesh_signing_ca_expiry)>0 and last(/HashiCorp Consul Node by HTTP/consul.mesh_signing_ca_expiry)<{$CONSUL.MESH.SIGNING_CA.EXPIRY.MIN}`|Warning||
|HashiCorp Consul Node: Mesh signing CA has already expired|<p>Service mesh signing CA certificate has expired. This is likely causing a mesh-wide outage.</p>|`last(/HashiCorp Consul Node by HTTP/consul.mesh_signing_ca_expiry)<0`|High||
|HashiCorp Consul Node: License expires soon|<p>Consul Enterprise license will expire soon. Renew it to avoid Enterprise features being disabled.</p>|`last(/HashiCorp Consul Node by HTTP/consul.license_expiry)>0 and last(/HashiCorp Consul Node by HTTP/consul.license_expiry)<{$CONSUL.LICENSE.EXPIRY.MIN}`|Warning||
|HashiCorp Consul Node: License has already expired|<p>Consul Enterprise license has expired.</p>|`last(/HashiCorp Consul Node by HTTP/consul.license_expiry)<0`|High||
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
|Raft state: Leader|<p>Increments when a server becomes a leader.</p>|Dependent item|consul.raft.state_leader[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_state_leader)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Raft state: Candidate|<p>The number of initiated leader elections.</p>|Dependent item|consul.raft.state_candidate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_state_candidate)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Raft: Apply, rate|<p>Incremented whenever a leader first passes a message into the Raft commit process (called an Apply operation).</p><p>This metric describes the arrival rate of new logs into Raft per second.</p>|Dependent item|consul.raft.apply.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_apply)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Server: Is leader|<p>Tracks if the server is a leader.</p>|Dependent item|consul.server.is_leader[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_server_isLeader)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|gRPC: Active connections|<p>Measures the number of active gRPC connections open on the server.</p>|Dependent item|consul.grpc.server_connections[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_grpc_server_connections)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|gRPC: Active streams|<p>Measures the number of active gRPC streams handled by the server.</p>|Dependent item|consul.grpc.server_streams[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_grpc_server_streams)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|xDS: Active streams|<p>Measures the number of active xDS streams handled by the server, split by protocol version.</p>|Dependent item|consul.xds.server_streams[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(consul_xds_server_streams)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Raft leader metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Raft leader metrics discovery|<p>Discover raft metrics for leader nodes.</p>|Dependent item|consul.raft.leader.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Raft leader metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Raft state: Leader last contact, p90|<p>The 90 percentile of how long it takes a leader node to communicate with followers during a leader lease check, in milliseconds.</p>|Dependent item|consul.raft.leader_last_contact.p90[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_leader_lastContact{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: Leader last contact, p50|<p>The 50 percentile (median) of how long it takes a leader node to communicate with followers during a leader lease check, in milliseconds.</p>|Dependent item|consul.raft.leader_last_contact.p50[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_leader_lastContact{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: Commit time, p90|<p>The 90 percentile time it takes to commit a new entry to the raft log on the leader, in milliseconds.</p>|Dependent item|consul.raft.commit_time.p90[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_commitTime{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: Commit time, p50|<p>The 50 percentile (median) time it takes to commit a new entry to the raft log on the leader, in milliseconds.</p>|Dependent item|consul.raft.commit_time.p50[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_commitTime{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: Dispatch log, p90|<p>The 90 percentile time it takes for the leader to write log entries to disk, in milliseconds.</p>|Dependent item|consul.raft.dispatch_log.p90[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_leader_dispatchLog{quantile="0.9"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: Dispatch log, p50|<p>The 50 percentile (median) time it takes for the leader to write log entries to disk, in milliseconds.</p>|Dependent item|consul.raft.dispatch_log.p50[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_leader_dispatchLog{quantile="0.5"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Raft state: Dispatch log, rate|<p>The number of times a Raft leader writes a log to disk per second.</p>|Dependent item|consul.raft.dispatch_log.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_leader_dispatchLog_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Raft state: Commit, rate|<p>The number of commits a new entry to the Raft log on the leader per second.</p>|Dependent item|consul.raft.commit_time.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_raft_commitTime_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Autopilot healthy|<p>Tracks the overall health of the local server cluster. 1 if all servers are healthy, 0 if one or more are unhealthy.</p>|Dependent item|consul.autopilot.healthy[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_autopilot_healthy)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Autopilot: Failure tolerance|<p>Tracks the number of voting servers that the cluster can lose while continuing to function.</p>|Dependent item|consul.autopilot.failure_tolerance[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(consul_autopilot_failure_tolerance)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

