
# Envoy Proxy by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Envoy Proxy by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.


Template `Envoy Proxy by HTTP` — collects metrics by HTTP agent from  metrics endpoint {$ENVOY.METRICS.PATH} endpoint (default: /stats/prometheus).



This template was tested on:

- Envoy Proxy, version 1.20.2

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Internal service metrics are collected from {$ENVOY.METRICS.PATH} endpoint (default: /stats/prometheus).
https://www.envoyproxy.io/docs/envoy/v1.20.0/operations/stats_overview


Don't forget to change macros {$ENVOY.URL}, {$ENVOY.METRICS.PATH}.
Also, see the Macros section for a list of macros used to set trigger values.  
*NOTE.* Some metrics may not be collected depending on your Envoy Proxy instance version and configuration.  


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ENVOY.CERT.MIN} |<p>Minimum number of days before certificate expiration used for trigger expression.</p> |`7` |
|{$ENVOY.METRICS.PATH} |<p>The path Zabbix will scrape metrics in prometheus format from.</p> |`/stats/prometheus` |
|{$ENVOY.URL} |<p>Instance URL.</p> |`http://localhost:9901` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Cluster metrics discovery |<p>-</p> |DEPENDENT |envoy.lld.cluster<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON<p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|HTTP metrics discovery |<p>-</p> |DEPENDENT |envoy.lld.http<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON<p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Listeners metrics discovery |<p>-</p> |DEPENDENT |envoy.lld.listeners<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON<p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Envoy Proxy |Envoy Proxy: Server state |<p>State of the server.</p><p>Live - (default) ⁣Server is live and serving traffic.</p><p>Draining - ⁣Server is draining listeners in response to external health checks failing.</p><p>Pre initializing - ⁣Server has not yet completed cluster manager initialization.</p><p>Initializing - Server is running the cluster manager initialization callbacks (e.g., RDS).</p> |DEPENDENT |envoy.server.state<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_server_state`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Envoy Proxy |Envoy Proxy: Server live |<p>1 if the server is not currently draining, 0 otherwise.</p> |DEPENDENT |envoy.server.live<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_server_live`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Envoy Proxy |Envoy Proxy: Uptime |<p>Current server uptime in seconds.</p> |DEPENDENT |envoy.server.uptime<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_server_uptime`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Envoy Proxy |Envoy Proxy: Certificate expiration, day before |<p>Number of days until the next certificate being managed will expire.</p> |DEPENDENT |envoy.server.days_until_first_cert_expiring<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_server_days_until_first_cert_expiring`</p> |
|Envoy Proxy |Envoy Proxy: Server concurrency |<p>Number of worker threads.</p> |DEPENDENT |envoy.server.concurrency<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_server_concurrency`</p> |
|Envoy Proxy |Envoy Proxy: Memory allocated |<p>Current amount of allocated memory in bytes. Total of both new and old Envoy processes on hot restart.</p> |DEPENDENT |envoy.server.memory_allocated<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_server_memory_allocated`</p> |
|Envoy Proxy |Envoy Proxy: Memory heap size |<p>Current reserved heap size in bytes. New Envoy process heap size on hot restart.</p> |DEPENDENT |envoy.server.memory_heap_size<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_server_memory_heap_size`</p> |
|Envoy Proxy |Envoy Proxy: Memory physical size |<p>Current estimate of total bytes of the physical memory. New Envoy process physical memory size on hot restart.</p> |DEPENDENT |envoy.server.memory_physical_size<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_server_memory_physical_size`</p> |
|Envoy Proxy |Envoy Proxy: Filesystem, flushed by timer rate |<p>Total number of times internal flush buffers are written to a file due to flush timeout per second.</p> |DEPENDENT |envoy.filesystem.flushed_by_timer.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_filesystem_flushed_by_timer`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Filesystem, write completed rate |<p>Total number of times a file was written per second.</p> |DEPENDENT |envoy.filesystem.write_completed.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_filesystem_write_completed`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Filesystem, write failed rate |<p>Total number of times an error occurred during a file write operation per second.</p> |DEPENDENT |envoy.filesystem.write_failed.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_filesystem_write_failed`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Filesystem, reopen failed rate |<p>Total number of times a file was failed to be opened per second.</p> |DEPENDENT |envoy.filesystem.reopen_failed.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_filesystem_reopen_failed`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Connections, total |<p>Total connections of both new and old Envoy processes.</p> |DEPENDENT |envoy.server.total_connections<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_server_total_connections`</p> |
|Envoy Proxy |Envoy Proxy: Connections, parent |<p>Total connections of the old Envoy process on hot restart.</p> |DEPENDENT |envoy.server.parent_connections<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_server_parent_connections`</p> |
|Envoy Proxy |Envoy Proxy: Clusters, warming |<p>Number of currently warming (not active) clusters.</p> |DEPENDENT |envoy.cluster_manager.warming_clusters<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_manager_warming_clusters`</p> |
|Envoy Proxy |Envoy Proxy: Clusters, active |<p>Number of currently active (warmed) clusters.</p> |DEPENDENT |envoy.cluster_manager.active_clusters<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_manager_active_clusters`</p> |
|Envoy Proxy |Envoy Proxy: Clusters, added rate |<p>Total clusters added (either via static config or CDS) per second.</p> |DEPENDENT |envoy.cluster_manager.cluster_added.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_manager_cluster_added`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Clusters, modified rate |<p>Total clusters modified (via CDS) per second.</p> |DEPENDENT |envoy.cluster_manager.cluster_modified.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_manager_cluster_modified`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Clusters, removed rate |<p>Total clusters removed (via CDS) per second.</p> |DEPENDENT |envoy.cluster_manager.cluster_removed.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_manager_cluster_removed`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Clusters, updates rate |<p>Total cluster updates per second.</p> |DEPENDENT |envoy.cluster_manager.cluster_updated.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_manager_cluster_updated`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Listeners, active |<p>Number of currently active listeners.</p> |DEPENDENT |envoy.listener_manager.total_listeners_active<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_manager_total_listeners_active`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Listeners, draining |<p>Number of currently draining listeners.</p> |DEPENDENT |envoy.listener_manager.total_listeners_draining<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_manager_total_listeners_draining`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Listener, warming |<p>Number of currently warming listeners.</p> |DEPENDENT |envoy.listener_manager.total_listeners_warming<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_manager_total_listeners_warming`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Listener manager, initialized |<p>A boolean (1 if started and 0 otherwise) that indicates whether listeners have been initialized on workers.</p> |DEPENDENT |envoy.listener_manager.workers_started<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_manager_workers_started`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Envoy Proxy |Envoy Proxy: Listeners, create failure |<p>Total failed listener object additions to workers per second.</p> |DEPENDENT |envoy.listener_manager.listener_create_failure.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_manager_listener_create_failure`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Listeners, create success |<p>Total listener objects successfully added to workers per second.</p> |DEPENDENT |envoy.listener_manager.listener_create_success.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_manager_listener_create_success`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Listeners, added |<p>Total listeners added (either via static config or LDS) per second.</p> |DEPENDENT |envoy.listener_manager.listener_added.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_manager_listener_added`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Listeners, stopped |<p>Total listeners stopped per second.</p> |DEPENDENT |envoy.listener_manager.listener_stopped.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_manager_listener_stopped`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Membership, total |<p>Current cluster membership total.</p> |DEPENDENT |envoy.cluster.membership_total["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_membership_total{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Membership, healthy |<p>Current cluster healthy total (inclusive of both health checking and outlier detection).</p> |DEPENDENT |envoy.cluster.membership_healthy["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_membership_healthy{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Membership, unhealthy |<p>Current cluster unhealthy.</p> |CALCULATED |envoy.cluster.membership_unhealthy["{#CLUSTER_NAME}"]<p>**Expression**:</p>`last(//envoy.cluster.membership_total["{#CLUSTER_NAME}"]) - last(//envoy.cluster.membership_healthy["{#CLUSTER_NAME}"])` |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Membership, degraded |<p>Current cluster degraded total.</p> |DEPENDENT |envoy.cluster.membership_degraded["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_membership_degraded{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Connections, total |<p>Current cluster total connections.</p> |DEPENDENT |envoy.cluster.upstream_cx_total["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_cx_total{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Connections, active |<p>Current cluster total active connections.</p> |DEPENDENT |envoy.cluster.upstream_cx_active["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_cx_active{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests total, rate |<p>Current cluster request total per second.</p> |DEPENDENT |envoy.cluster.upstream_rq_total.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_rq_total{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests timeout, rate |<p>Current cluster requests that timed out waiting for a response per second.</p> |DEPENDENT |envoy.cluster.upstream_rq_timeout.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_rq_timeout{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests completed, rate |<p>Total upstream requests completed per second.</p> |DEPENDENT |envoy.cluster.upstream_rq_completed.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_rq_completed{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests 2xx, rate |<p>Aggregate HTTP response codes per second.</p> |DEPENDENT |envoy.cluster.upstream_rq_2x.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_rq_xx{envoy_cluster_name = "{#CLUSTER_NAME}", envoy_response_code_class="2"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests 3xx, rate |<p>Aggregate HTTP response codes per second.</p> |DEPENDENT |envoy.cluster.upstream_rq_3x.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_rq_xx{envoy_cluster_name = "{#CLUSTER_NAME}", envoy_response_code_class="3"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests 4xx, rate |<p>Aggregate HTTP response codes per second.</p> |DEPENDENT |envoy.cluster.upstream_rq_4x.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_rq_xx{envoy_cluster_name = "{#CLUSTER_NAME}", envoy_response_code_class="4"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests 5xx, rate |<p>Aggregate HTTP response codes per second.</p> |DEPENDENT |envoy.cluster.upstream_rq_5x.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_rq_xx{envoy_cluster_name = "{#CLUSTER_NAME}", envoy_response_code_class="5"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests pending |<p>Total active requests pending a connection pool connection.</p> |DEPENDENT |envoy.cluster.upstream_rq_pending_active["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_rq_pending_active{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Requests active |<p>Total active requests.</p> |DEPENDENT |envoy.cluster.upstream_rq_active["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_rq_active{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Upstream bytes out, rate |<p>Total sent connection bytes per second.</p> |DEPENDENT |envoy.cluster.upstream_cx_tx_bytes_total.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_cx_tx_bytes_total{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Cluster ["{#CLUSTER_NAME}"]: Upstream bytes in, rate |<p>Total received connection bytes per second.</p> |DEPENDENT |envoy.cluster.upstream_cx_rx_bytes_total.rate["{#CLUSTER_NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_cluster_upstream_cx_rx_bytes_total{envoy_cluster_name = "{#CLUSTER_NAME}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Listener ["{#LISTENER_ADDRESS}"]: Connections, active |<p>Total active connections.</p> |DEPENDENT |envoy.listener.downstream_cx_active["{#LISTENER_ADDRESS}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_downstream_cx_active{envoy_listener_address = "{#LISTENER_ADDRESS}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: Listener ["{#LISTENER_ADDRESS}"]: Connections, rate |<p>Total connections per second.</p> |DEPENDENT |envoy.listener.downstream_cx_total.rate["{#LISTENER_ADDRESS}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_downstream_cx_total{envoy_listener_address = "{#LISTENER_ADDRESS}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: Listener ["{#LISTENER_ADDRESS}"]: Sockets, undergoing |<p>Sockets currently undergoing listener filter processing.</p> |DEPENDENT |envoy.listener.downstream_pre_cx_active["{#LISTENER_ADDRESS}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_listener_downstream_pre_cx_active{envoy_listener_address = "{#LISTENER_ADDRESS}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Requests, rate |<p>Total active connections per second.</p> |DEPENDENT |envoy.http.downstream_rq_total.rate["{#CONN_MANAGER}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_http_downstream_rq_total{envoy_http_conn_manager_prefix = "{#CONN_MANAGER}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Requests, active |<p>Total active requests.</p> |DEPENDENT |envoy.http.downstream_rq_active["{#CONN_MANAGER}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_http_downstream_rq_active{envoy_http_conn_manager_prefix = "{#CONN_MANAGER}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Requests timeout, rate |<p>Total requests closed due to a timeout on the request path per second.</p> |DEPENDENT |envoy.http.downstream_rq_timeout["{#CONN_MANAGER}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_http_downstream_rq_timeout{envoy_http_conn_manager_prefix = "{#CONN_MANAGER}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Connections, rate |<p>Total connections per second.</p> |DEPENDENT |envoy.http.downstream_cx_total["{#CONN_MANAGER}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_http_downstream_cx_total{envoy_http_conn_manager_prefix = "{#CONN_MANAGER}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Connections, active |<p>Total active connections.</p> |DEPENDENT |envoy.http.downstream_cx_active["{#CONN_MANAGER}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_http_downstream_cx_active{envoy_http_conn_manager_prefix = "{#CONN_MANAGER}"}`: `function`: `sum`</p> |
|Envoy Proxy |Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Bytes in, rate |<p>Total bytes received per second.</p> |DEPENDENT |envoy.http.downstream_cx_rx_bytes_total.rate["{#CONN_MANAGER}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_http_downstream_cx_rx_bytes_total{envoy_http_conn_manager_prefix = "{#CONN_MANAGER}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Envoy Proxy |Envoy Proxy: HTTP ["{#CONN_MANAGER}"]: Bytes out, rate |<p>Total bytes sent per second.</p> |DEPENDENT |envoy.http.downstream_cx_tx_bytes_tota.rate["{#CONN_MANAGER}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `envoy_http_downstream_cx_tx_bytes_total{envoy_http_conn_manager_prefix = "{#CONN_MANAGER}"}`: `function`: `sum`</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix raw items |Envoy Proxy: Get node metrics |<p>Get server metrics.</p> |HTTP_AGENT |envoy.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Envoy Proxy: Server state is not live |<p>-</p> |`last(/Envoy Proxy by HTTP/envoy.server.state) > 0` |AVERAGE | |
|Envoy Proxy: Service has been restarted |<p>Uptime is less than 10 minutes.</p> |`last(/Envoy Proxy by HTTP/envoy.server.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Envoy Proxy: Failed to fetch metrics data |<p>Zabbix has not received data for items for the last 10 minutes.</p> |`nodata(/Envoy Proxy by HTTP/envoy.server.uptime,10m)=1` |WARNING |<p>Manual close: YES</p> |
|Envoy Proxy: SSL certificate expires soon |<p>Please check certificate. Less than {$ENVOY.CERT.MIN} days left until the next certificate being managed will expire.</p> |`last(/Envoy Proxy by HTTP/envoy.server.days_until_first_cert_expiring)<{$ENVOY.CERT.MIN}` |WARNING | |
|Envoy Proxy: There are unhealthy clusters |<p>-</p> |`last(/Envoy Proxy by HTTP/envoy.cluster.membership_unhealthy["{#CLUSTER_NAME}"]) > 0` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

