
# Envoy Proxy by HTTP

## Overview

The template to monitor Envoy Proxy by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Envoy Proxy by HTTP` - collects metrics by HTTP agent from  metrics endpoint {$ENVOY.METRICS.PATH} endpoint (default: /stats/prometheus).

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Envoy Proxy 1.20.2

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Internal service metrics are collected from {$ENVOY.METRICS.PATH} endpoint (default: /stats/prometheus).
https://www.envoyproxy.io/docs/envoy/v1.20.0/operations/stats_overview

Don't forget to change macros {$ENVOY.URL}, {$ENVOY.METRICS.PATH}.
Also, see the Macros section for a list of macros used to set trigger values.

*NOTE.* Some metrics may not be collected depending on your Envoy Proxy instance version and configuration.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ENVOY.URL}|<p>Instance URL.</p>|`http://localhost:9901`|
|{$ENVOY.METRICS.PATH}|<p>The path Zabbix will scrape metrics in prometheus format from.</p>|`/stats/prometheus`|
|{$ENVOY.CERT.MIN}|<p>Minimum number of days before certificate expiration used for trigger expression.</p>|`7`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Envoy Proxy: Get node metrics|<p>Get server metrics.</p>|HTTP agent|envoy.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Envoy Proxy: Server state|<p>State of the server.</p><p>Live - (default) Server is live and serving traffic.</p><p>Draining - Server is draining listeners in response to external health checks failing.</p><p>Pre initializing - Server has not yet completed cluster manager initialization.</p><p>Initializing - Server is running the cluster manager initialization callbacks (e.g., RDS).</p>|Dependent item|envoy.server.state<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_server_state)`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Envoy Proxy: Server live|<p>1 if the server is not currently draining, 0 otherwise.</p>|Dependent item|envoy.server.live<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_server_live)`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Envoy Proxy: Uptime|<p>Current server uptime in seconds.</p>|Dependent item|envoy.server.uptime<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_server_uptime)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Envoy Proxy: Certificate expiration, day before|<p>Number of days until the next certificate being managed will expire.</p>|Dependent item|envoy.server.days_until_first_cert_expiring<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_server_days_until_first_cert_expiring)`</p></li></ul>|
|Envoy Proxy: Server concurrency|<p>Number of worker threads.</p>|Dependent item|envoy.server.concurrency<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_server_concurrency)`</p></li></ul>|
|Envoy Proxy: Memory allocated|<p>Current amount of allocated memory in bytes. Total of both new and old Envoy processes on hot restart.</p>|Dependent item|envoy.server.memory_allocated<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_server_memory_allocated)`</p></li></ul>|
|Envoy Proxy: Memory heap size|<p>Current reserved heap size in bytes. New Envoy process heap size on hot restart.</p>|Dependent item|envoy.server.memory_heap_size<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_server_memory_heap_size)`</p></li></ul>|
|Envoy Proxy: Memory physical size|<p>Current estimate of total bytes of the physical memory. New Envoy process physical memory size on hot restart.</p>|Dependent item|envoy.server.memory_physical_size<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_server_memory_physical_size)`</p></li></ul>|
|Envoy Proxy: Filesystem, flushed by timer rate|<p>Total number of times internal flush buffers are written to a file due to flush timeout per second.</p>|Dependent item|envoy.filesystem.flushed_by_timer.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_filesystem_flushed_by_timer)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Filesystem, write completed rate|<p>Total number of times a file was written per second.</p>|Dependent item|envoy.filesystem.write_completed.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_filesystem_write_completed)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Filesystem, write failed rate|<p>Total number of times an error occurred during a file write operation per second.</p>|Dependent item|envoy.filesystem.write_failed.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_filesystem_write_failed)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Filesystem, reopen failed rate|<p>Total number of times a file was failed to be opened per second.</p>|Dependent item|envoy.filesystem.reopen_failed.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_filesystem_reopen_failed)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Connections, total|<p>Total connections of both new and old Envoy processes.</p>|Dependent item|envoy.server.total_connections<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_server_total_connections)`</p></li></ul>|
|Envoy Proxy: Connections, parent|<p>Total connections of the old Envoy process on hot restart.</p>|Dependent item|envoy.server.parent_connections<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_server_parent_connections)`</p></li></ul>|
|Envoy Proxy: Clusters, warming|<p>Number of currently warming (not active) clusters.</p>|Dependent item|envoy.cluster_manager.warming_clusters<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_cluster_manager_warming_clusters)`</p></li></ul>|
|Envoy Proxy: Clusters, active|<p>Number of currently active (warmed) clusters.</p>|Dependent item|envoy.cluster_manager.active_clusters<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_cluster_manager_active_clusters)`</p></li></ul>|
|Envoy Proxy: Clusters, added rate|<p>Total clusters added (either via static config or CDS) per second.</p>|Dependent item|envoy.cluster_manager.cluster_added.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_cluster_manager_cluster_added)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Clusters, modified rate|<p>Total clusters modified (via CDS) per second.</p>|Dependent item|envoy.cluster_manager.cluster_modified.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_cluster_manager_cluster_modified)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Clusters, removed rate|<p>Total clusters removed (via CDS) per second.</p>|Dependent item|envoy.cluster_manager.cluster_removed.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_cluster_manager_cluster_removed)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Clusters, updates rate|<p>Total cluster updates per second.</p>|Dependent item|envoy.cluster_manager.cluster_updated.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_cluster_manager_cluster_updated)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Listeners, active|<p>Number of currently active listeners.</p>|Dependent item|envoy.listener_manager.total_listeners_active<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(envoy_listener_manager_total_listeners_active)`</p></li></ul>|
|Envoy Proxy: Listeners, draining|<p>Number of currently draining listeners.</p>|Dependent item|envoy.listener_manager.total_listeners_draining<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(envoy_listener_manager_total_listeners_draining)`</p></li></ul>|
|Envoy Proxy: Listener, warming|<p>Number of currently warming listeners.</p>|Dependent item|envoy.listener_manager.total_listeners_warming<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(envoy_listener_manager_total_listeners_warming)`</p></li></ul>|
|Envoy Proxy: Listener manager, initialized|<p>A boolean (1 if started and 0 otherwise) that indicates whether listeners have been initialized on workers.</p>|Dependent item|envoy.listener_manager.workers_started<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_listener_manager_workers_started)`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Envoy Proxy: Listeners, create failure|<p>Total failed listener object additions to workers per second.</p>|Dependent item|envoy.listener_manager.listener_create_failure.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_listener_manager_listener_create_failure)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Listeners, create success|<p>Total listener objects successfully added to workers per second.</p>|Dependent item|envoy.listener_manager.listener_create_success.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_listener_manager_listener_create_success)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Listeners, added|<p>Total listeners added (either via static config or LDS) per second.</p>|Dependent item|envoy.listener_manager.listener_added.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_listener_manager_listener_added)`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Listeners, stopped|<p>Total listeners stopped per second.</p>|Dependent item|envoy.listener_manager.listener_stopped.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(envoy_listener_manager_listener_stopped)`</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Envoy Proxy: Server state is not live||`last(/Envoy Proxy by HTTP/envoy.server.state) > 0`|Average||
|Envoy Proxy: Service has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Envoy Proxy by HTTP/envoy.server.uptime)<10m`|Info|**Manual close**: Yes|
|Envoy Proxy: Failed to fetch metrics data|<p>Zabbix has not received data for items for the last 10 minutes.</p>|`nodata(/Envoy Proxy by HTTP/envoy.server.uptime,10m)=1`|Warning|**Manual close**: Yes|
|Envoy Proxy: SSL certificate expires soon|<p>Please check certificate. Less than {$ENVOY.CERT.MIN} days left until the next certificate being managed will expire.</p>|`last(/Envoy Proxy by HTTP/envoy.server.days_until_first_cert_expiring)<{$ENVOY.CERT.MIN}`|Warning||

### LLD rule Cluster metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster metrics discovery||Dependent item|envoy.lld.cluster<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `envoy_cluster_membership_total`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Cluster metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Membership, total|<p>Current cluster membership total.</p>|Dependent item|envoy.cluster.membership_total["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Membership, healthy|<p>Current cluster healthy total (inclusive of both health checking and outlier detection).</p>|Dependent item|envoy.cluster.membership_healthy["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Membership, unhealthy|<p>Current cluster unhealthy.</p>|Calculated|envoy.cluster.membership_unhealthy["{#CLUSTER_NAME}"]|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Membership, degraded|<p>Current cluster degraded total.</p>|Dependent item|envoy.cluster.membership_degraded["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Connections, total|<p>Current cluster total connections.</p>|Dependent item|envoy.cluster.upstream_cx_total["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Connections, active|<p>Current cluster total active connections.</p>|Dependent item|envoy.cluster.upstream_cx_active["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests total, rate|<p>Current cluster request total per second.</p>|Dependent item|envoy.cluster.upstream_rq_total.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests timeout, rate|<p>Current cluster requests that timed out waiting for a response per second.</p>|Dependent item|envoy.cluster.upstream_rq_timeout.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests completed, rate|<p>Total upstream requests completed per second.</p>|Dependent item|envoy.cluster.upstream_rq_completed.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests 2xx, rate|<p>Aggregate HTTP response codes per second.</p>|Dependent item|envoy.cluster.upstream_rq_2x.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests 3xx, rate|<p>Aggregate HTTP response codes per second.</p>|Dependent item|envoy.cluster.upstream_rq_3x.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests 4xx, rate|<p>Aggregate HTTP response codes per second.</p>|Dependent item|envoy.cluster.upstream_rq_4x.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests 5xx, rate|<p>Aggregate HTTP response codes per second.</p>|Dependent item|envoy.cluster.upstream_rq_5x.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests pending|<p>Total active requests pending a connection pool connection.</p>|Dependent item|envoy.cluster.upstream_rq_pending_active["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests active|<p>Total active requests.</p>|Dependent item|envoy.cluster.upstream_rq_active["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Upstream bytes out, rate|<p>Total sent connection bytes per second.</p>|Dependent item|envoy.cluster.upstream_cx_tx_bytes_total.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Upstream bytes in, rate|<p>Total received connection bytes per second.</p>|Dependent item|envoy.cluster.upstream_cx_rx_bytes_total.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Cluster metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Envoy Proxy: There are unhealthy clusters||`last(/Envoy Proxy by HTTP/envoy.cluster.membership_unhealthy["{#CLUSTER_NAME}"]) > 0`|Average||

### LLD rule Listeners metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Listeners metrics discovery||Dependent item|envoy.lld.listeners<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `envoy_listener_downstream_cx_active`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Listeners metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Envoy Proxy: Listener ["{#LISTENER_ADDRESS}"]: Connections, active|<p>Total active connections.</p>|Dependent item|envoy.listener.downstream_cx_active["{#LISTENER_ADDRESS}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Envoy Proxy: Listener ["{#LISTENER_ADDRESS}"]: Connections, rate|<p>Total connections per second.</p>|Dependent item|envoy.listener.downstream_cx_total.rate["{#LISTENER_ADDRESS}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: Listener ["{#LISTENER_ADDRESS}"]: Sockets, undergoing|<p>Sockets currently undergoing listener filter processing.</p>|Dependent item|envoy.listener.downstream_pre_cx_active["{#LISTENER_ADDRESS}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule HTTP metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HTTP metrics discovery||Dependent item|envoy.lld.http<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `envoy_http_downstream_rq_total`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for HTTP metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Requests, rate|<p>Total active connections per second.</p>|Dependent item|envoy.http.downstream_rq_total.rate["{#CONN_MANAGER}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Requests, active|<p>Total active requests.</p>|Dependent item|envoy.http.downstream_rq_active["{#CONN_MANAGER}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Requests timeout, rate|<p>Total requests closed due to a timeout on the request path per second.</p>|Dependent item|envoy.http.downstream_rq_timeout["{#CONN_MANAGER}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Connections, rate|<p>Total connections per second.</p>|Dependent item|envoy.http.downstream_cx_total["{#CONN_MANAGER}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Connections, active|<p>Total active connections.</p>|Dependent item|envoy.http.downstream_cx_active["{#CONN_MANAGER}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Bytes in, rate|<p>Total bytes received per second.</p>|Dependent item|envoy.http.downstream_cx_rx_bytes_total.rate["{#CONN_MANAGER}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Bytes out, rate|<p>Total bytes sent per second.</p>|Dependent item|envoy.http.downstream_cx_tx_bytes_tota.rate["{#CONN_MANAGER}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

