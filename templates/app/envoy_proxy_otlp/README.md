
# Envoy Proxy by OTLP

## Overview

This template collects Envoy Proxy's OpenTelemetry (OTLP) traces and logs via
Zabbix's native APM query items (Telemetry query), without external scripts or a
Zabbix proxy script layer. It requires Envoy's OpenTelemetry tracing to be enabled
(directly, or via an OpenTelemetry Collector in front of Envoy).

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Envoy Proxy (OpenTelemetry tracing)

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Enable Envoy's OpenTelemetry tracing (or point an OpenTelemetry Collector in front
   of Envoy) at your Zabbix server/proxy's OTLP endpoint. See
   https://www.envoyproxy.io/docs/envoy/latest/configuration/other_features/tracing#opentelemetry
2. Ensure the `TelemetryProvider` parameter is configured in the Zabbix server/proxy
   configuration file.
3. Apply the template to a host representing the Envoy instance.
4. Adjust the remaining macros (update interval, thresholds) if needed.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ENVOY.OTLP.INTERVAL}|<p>Update interval of the Telemetry query and calculated items. The aggregation bucket (granularity) stays fixed at 1 minute, so use a multiple of 1 minute.</p>|`1m`|
|{$ENVOY.OTLP.TIME_SHIFT}|<p>Time shift of the processing window of the Telemetry query items. Increase it if the OTLP collector writes data with a delay.</p>|`15s`|
|{$ENVOY.OTLP.NODATA.TIMEOUT}|<p>Time period without span data after which the availability trigger fires.</p>|`30m`|
|{$ENVOY.OTLP.SPAN.DOWNSTREAM.ERROR.WARN}|<p>Downstream (SpanKind Server) span error rate threshold, in %.</p>|`30`|
|{$ENVOY.OTLP.SPAN.DOWNSTREAM.P95.WARN}|<p>95th percentile downstream (SpanKind Server) span duration threshold, in seconds.</p>|`1`|
|{$ENVOY.OTLP.SPAN.UPSTREAM.ERROR.WARN}|<p>Upstream (SpanKind Client) span error rate threshold, in %.</p>|`30`|
|{$ENVOY.OTLP.SPAN.UPSTREAM.P95.WARN}|<p>95th percentile upstream (SpanKind Client) span duration threshold, in seconds.</p>|`1`|
|{$ENVOY.OTLP.LOG.SEVERITY.WARN}|<p>Threshold for `Warning` severity entries in logs.</p>|`10`|
|{$ENVOY.OTLP.LOG.SEVERITY.ERROR}|<p>Threshold for `Error` severity entries in logs.</p>|`5`|
|{$ENVOY.OTLP.LOG.SEVERITY.FATAL}|<p>Threshold for `Fatal` severity entries in logs.</p>|`0`|
|{$ENVOY.OTLP.HTTP.5XX.WARN}|<p>Downstream 5xx response rate threshold, in %.</p>|`5`|
|{$ENVOY.OTLP.LB.PANIC.WARN}|<p>Number of load-balancer panic-mode events (no healthy upstream hosts) per aggregation window that triggers an alert.</p>|`0`|
|{$ENVOY.OTLP.LISTENER.CX_OVERFLOW.WARN}|<p>Number of rejected connections due to listener connection-limit overflow per aggregation window that triggers an alert.</p>|`0`|
|{$ENVOY.OTLP.WATCHDOG.MEGA_MISS.WARN}|<p>Number of worker-thread watchdog "mega miss" events (severe event-loop stall) per aggregation window that triggers an alert.</p>|`0`|
|{$ENVOY.OTLP.OVERLOAD.CLOSE.WARN}|<p>Number of downstream requests closed due to Envoy overload-protection per aggregation window that triggers an alert.</p>|`0`|
|{$ENVOY.OTLP.CIRCUIT_BREAKER.WARN}|<p>Number of aggregation windows a default-priority circuit breaker is seen open (tripped) before the trigger fires.</p>|`0`|
|{$ENVOY.OTLP.CLUSTER.HEALTHY.MIN.WARN}|<p>Minimum share of healthy upstream hosts, in percent (summed across all clusters), before the trigger fires.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Downstream span: Total count|<p>Total number of downstream (SpanKind Server, ingress) spans over the aggregation window.</p>|Telemetry query|envoy.otlp.span.downstream.total<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Downstream span: Error count|<p>Number of downstream (SpanKind Server, ingress) spans with status code "Error" over the aggregation window.</p>|Telemetry query|envoy.otlp.span.downstream.error<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Downstream span: Error rate|<p>Percentage of error spans among downstream (ingress) spans over the aggregation window.</p>|Calculated|envoy.otlp.span.downstream.error.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Downstream span: Duration, average|<p>Average downstream (ingress) span duration over the aggregation window.</p>|Telemetry query|envoy.otlp.span.downstream.duration.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.avg`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Downstream span: Duration, minimum|<p>Minimum downstream (ingress) span duration over the aggregation window.</p>|Telemetry query|envoy.otlp.span.downstream.duration.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.min`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Downstream span: Duration, maximum|<p>Maximum downstream (ingress) span duration over the aggregation window.</p>|Telemetry query|envoy.otlp.span.downstream.duration.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.max`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Downstream span: Duration, 95th percentile|<p>95th percentile of downstream (ingress) span durations over the aggregation window.</p>|Telemetry query|envoy.otlp.span.downstream.duration.p95<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.p95`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Upstream span: Total count|<p>Total number of upstream (SpanKind Client, egress) spans over the aggregation window.</p>|Telemetry query|envoy.otlp.span.upstream.total<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Upstream span: Error count|<p>Number of upstream (SpanKind Client, egress) spans with status code "Error" over the aggregation window.</p>|Telemetry query|envoy.otlp.span.upstream.error<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Upstream span: Error rate|<p>Percentage of error spans among upstream (egress) spans over the aggregation window — i.e. how often calls to upstream clusters fail.</p>|Calculated|envoy.otlp.span.upstream.error.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Upstream span: Duration, average|<p>Average upstream (egress) span duration over the aggregation window — the latency of Envoy's calls to upstream clusters.</p>|Telemetry query|envoy.otlp.span.upstream.duration.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.avg`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Upstream span: Duration, minimum|<p>Minimum upstream (egress) span duration over the aggregation window.</p>|Telemetry query|envoy.otlp.span.upstream.duration.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.min`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Upstream span: Duration, maximum|<p>Maximum upstream (egress) span duration over the aggregation window.</p>|Telemetry query|envoy.otlp.span.upstream.duration.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.max`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Upstream span: Duration, 95th percentile|<p>95th percentile of upstream (egress) span durations over the aggregation window.</p>|Telemetry query|envoy.otlp.span.upstream.duration.p95<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.p95`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Downstream requests: Total, rate|<p>Number of new downstream HTTP requests (envoy_http_downstream_rq_total) per aggregation window, across all listeners.</p>|Telemetry query|envoy.otlp.http.rq.total.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Downstream requests: 5xx, rate|<p>Number of downstream responses with a 5xx status class (envoy_http_downstream_rq_xx, envoy_response_code_class=5) per aggregation window.</p>|Telemetry query|envoy.otlp.http.rq.5xx.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Downstream requests: 4xx, rate|<p>Number of downstream responses with a 4xx status class (envoy_http_downstream_rq_xx, envoy_response_code_class=4) per aggregation window.</p>|Telemetry query|envoy.otlp.http.rq.4xx.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Downstream requests: 2xx, rate|<p>Number of downstream responses with a 2xx status class (envoy_http_downstream_rq_xx, envoy_response_code_class=2) per aggregation window.</p>|Telemetry query|envoy.otlp.http.rq.2xx.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Downstream requests: 3xx, rate|<p>Number of downstream responses with a 3xx status class (envoy_http_downstream_rq_xx, envoy_response_code_class=3) per aggregation window.</p>|Telemetry query|envoy.otlp.http.rq.3xx.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Downstream requests: 5xx rate, in %|<p>Share of downstream HTTP responses with a 5xx status class, in percent of total requests.</p>|Calculated|envoy.otlp.http.rq.error.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Downstream requests: Timeouts, rate|<p>Number of downstream requests closed due to a timeout (envoy_http_downstream_rq_timeout) per aggregation window. Distinct from cluster.rq.timeout.rate, which counts upstream timeouts.</p>|Telemetry query|envoy.otlp.http.rq.timeout.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Downstream requests: Overload-closed, rate|<p>Number of downstream requests closed because Envoy itself is overloaded (envoy_http_downstream_rq_overload_close — one of Envoy's own overload-protection actions triggered) per aggregation window.</p>|Telemetry query|envoy.otlp.http.rq.overload_close.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Downstream requests: Premature resets, rate|<p>Number of connections closed for sending too many premature stream resets (envoy_http_downstream_rq_too_many_premature_resets) per aggregation window — Envoy's built-in mitigation for the HTTP/2 rapid-reset abuse pattern (CVE-2023-44487).</p>|Telemetry query|envoy.otlp.http.rq.premature_resets.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Upstream: Connect failures, rate|<p>Number of failed upstream connection attempts (envoy_cluster_upstream_cx_connect_fail) per aggregation window, across all clusters.</p>|Telemetry query|envoy.otlp.cluster.cx.connect_fail.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Upstream: Request timeouts, rate|<p>Number of upstream requests that timed out (envoy_cluster_upstream_rq_timeout) per aggregation window, across all clusters.</p>|Telemetry query|envoy.otlp.cluster.rq.timeout.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Upstream: No healthy hosts (LB panic), rate|<p>Number of times load balancing entered panic mode (envoy_cluster_lb_healthy_panic — no healthy upstream hosts, routing to all hosts regardless of health) per aggregation window.</p>|Telemetry query|envoy.otlp.cluster.lb.healthy_panic.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Listener: Connection overflow, rate|<p>Number of connections rejected because a listener's connection limit was reached (envoy_listener_downstream_cx_overflow) per aggregation window.</p>|Telemetry query|envoy.otlp.listener.cx.overflow.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Worker: Watchdog miss, rate|<p>Number of worker-thread event-loop watchdog misses (envoy_server_worker_watchdog_miss — the thread was unresponsive longer than expected) per aggregation window.</p>|Telemetry query|envoy.otlp.worker.watchdog.miss.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Worker: Watchdog mega miss, rate|<p>Number of worker-thread event-loop watchdog "mega miss" events (envoy_server_worker_watchdog_mega_miss — a severe, prolonged event-loop stall) per aggregation window.</p>|Telemetry query|envoy.otlp.worker.watchdog.mega_miss.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li>Simple change</li></ul>|
|Circuit breaker: Connection pool open|<p>1 if the default-priority connection-pool circuit breaker is open (tripped) for at least one cluster (envoy_cluster_circuit_breakers_default_cx_pool_open), 0 otherwise.</p>|Telemetry query|envoy.otlp.cluster.cb.cx_pool_open<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"value":0}}`</p></li><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Circuit breaker: Request queue open|<p>1 if the default-priority pending-request circuit breaker is open for at least one cluster (envoy_cluster_circuit_breakers_default_rq_pending_open), 0 otherwise.</p>|Telemetry query|envoy.otlp.cluster.cb.rq_pending_open<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"value":0}}`</p></li><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Circuit breaker: Requests open|<p>1 if the default-priority request circuit breaker is open for at least one cluster (envoy_cluster_circuit_breakers_default_rq_open — max concurrent requests reached), 0 otherwise.</p>|Telemetry query|envoy.otlp.cluster.cb.rq_open<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"value":0}}`</p></li><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Circuit breaker: Retries open|<p>1 if the default-priority retry circuit breaker is open for at least one cluster (envoy_cluster_circuit_breakers_default_rq_retry_open — retry budget exhausted), 0 otherwise.</p>|Telemetry query|envoy.otlp.cluster.cb.rq_retry_open<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"value":0}}`</p></li><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Cluster membership: Healthy|<p>Number of healthy upstream hosts (envoy_cluster_membership_healthy), summed across all clusters, at the lowest point of the aggregation window.</p>|Telemetry query|envoy.otlp.cluster.membership.healthy<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Cluster membership: Total|<p>Total number of configured upstream hosts (envoy_cluster_membership_total), summed across all clusters.</p>|Telemetry query|envoy.otlp.cluster.membership.total<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster membership: Degraded|<p>Number of degraded upstream hosts (envoy_cluster_membership_degraded), summed across all clusters, at the highest point of the aggregation window.</p>|Telemetry query|envoy.otlp.cluster.membership.degraded<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Cluster membership: Excluded|<p>Number of excluded upstream hosts (envoy_cluster_membership_excluded), summed across all clusters, at the highest point of the aggregation window.</p>|Telemetry query|envoy.otlp.cluster.membership.excluded<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Cluster membership: Healthy, in %|<p>Share of healthy upstream hosts, in percent of all configured hosts, summed across all clusters.</p>|Calculated|envoy.otlp.cluster.membership.healthy.pct<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Downstream: Active requests|<p>Number of currently active (in-flight) downstream HTTP requests (envoy_http_downstream_rq_active), across all listeners.</p>|Telemetry query|envoy.otlp.http.rq.active<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"value":0}}`</p></li><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Downstream: Active connections|<p>Number of currently active downstream HTTP connections (envoy_http_downstream_cx_active), across all listeners.</p>|Telemetry query|envoy.otlp.http.cx.active<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"value":0}}`</p></li><li><p>JSON Path: `$.columns.value`</p></li></ul>|
|Get logs|<p>Collect Envoy Proxy OpenTelemetry log counts grouped by severity.</p>|Telemetry query|envoy.otlp.get.logs<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set error to: `No logs received for Envoy Proxy.`</p></li></ul>|
|Logs: Info entries count|<p>Number of `Info` severity entries in logs.</p>|Dependent item|envoy.otlp.logs.info<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Logs: Warning entries count|<p>Number of `Warning` severity entries in logs.</p>|Dependent item|envoy.otlp.logs.warn<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Logs: Error entries count|<p>Number of `Error` severity entries in logs.</p>|Dependent item|envoy.otlp.logs.error<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Logs: Fatal entries count|<p>Number of `Fatal` severity entries in logs.</p>|Dependent item|envoy.otlp.logs.fatal<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Envoy Proxy: No telemetry data received|<p>No spans have arrived from the telemetry backend for {$ENVOY.OTLP.NODATA.TIMEOUT}.<br>Check Envoy's OpenTelemetry tracing config, the OTLP collector, and the `TelemetryProvider` setting of Zabbix server or proxy.</p>|`nodata(/Envoy Proxy by OTLP/envoy.otlp.span.downstream.total,{$ENVOY.OTLP.NODATA.TIMEOUT}) = 1`|Average||
|Envoy Proxy: High downstream span error rate||`min(/Envoy Proxy by OTLP/envoy.otlp.span.downstream.error.rate,5m) > {$ENVOY.OTLP.SPAN.DOWNSTREAM.ERROR.WARN}`|Warning||
|Envoy Proxy: High downstream span duration for 95% of spans||`min(/Envoy Proxy by OTLP/envoy.otlp.span.downstream.duration.p95,5m) > {$ENVOY.OTLP.SPAN.DOWNSTREAM.P95.WARN}`|Warning||
|Envoy Proxy: High upstream span error rate||`min(/Envoy Proxy by OTLP/envoy.otlp.span.upstream.error.rate,5m) > {$ENVOY.OTLP.SPAN.UPSTREAM.ERROR.WARN}`|Warning||
|Envoy Proxy: High upstream span duration for 95% of spans||`min(/Envoy Proxy by OTLP/envoy.otlp.span.upstream.duration.p95,5m) > {$ENVOY.OTLP.SPAN.UPSTREAM.P95.WARN}`|Warning||
|Envoy Proxy: High 5xx response rate||`min(/Envoy Proxy by OTLP/envoy.otlp.http.rq.error.rate,5m) > {$ENVOY.OTLP.HTTP.5XX.WARN}`|Warning||
|Envoy Proxy: Envoy shedding requests due to overload||`min(/Envoy Proxy by OTLP/envoy.otlp.http.rq.overload_close.rate,5m) > {$ENVOY.OTLP.OVERLOAD.CLOSE.WARN}`|High||
|Envoy Proxy: Load balancer entered panic mode||`min(/Envoy Proxy by OTLP/envoy.otlp.cluster.lb.healthy_panic.rate,5m) > {$ENVOY.OTLP.LB.PANIC.WARN}`|High||
|Envoy Proxy: Listener rejecting connections (overflow)||`min(/Envoy Proxy by OTLP/envoy.otlp.listener.cx.overflow.rate,5m) > {$ENVOY.OTLP.LISTENER.CX_OVERFLOW.WARN}`|Warning||
|Envoy Proxy: Worker thread severely stalled (watchdog mega miss)||`min(/Envoy Proxy by OTLP/envoy.otlp.worker.watchdog.mega_miss.rate,5m) > {$ENVOY.OTLP.WATCHDOG.MEGA_MISS.WARN}`|High||
|Envoy Proxy: Connection pool circuit breaker open||`min(/Envoy Proxy by OTLP/envoy.otlp.cluster.cb.cx_pool_open,5m) > {$ENVOY.OTLP.CIRCUIT_BREAKER.WARN}`|Warning||
|Envoy Proxy: Request queue circuit breaker open||`min(/Envoy Proxy by OTLP/envoy.otlp.cluster.cb.rq_pending_open,5m) > {$ENVOY.OTLP.CIRCUIT_BREAKER.WARN}`|Warning||
|Envoy Proxy: Request circuit breaker open||`min(/Envoy Proxy by OTLP/envoy.otlp.cluster.cb.rq_open,5m) > {$ENVOY.OTLP.CIRCUIT_BREAKER.WARN}`|Warning||
|Envoy Proxy: Retry circuit breaker open||`min(/Envoy Proxy by OTLP/envoy.otlp.cluster.cb.rq_retry_open,5m) > {$ENVOY.OTLP.CIRCUIT_BREAKER.WARN}`|Warning||
|Envoy Proxy: Low share of healthy upstream hosts||`max(/Envoy Proxy by OTLP/envoy.otlp.cluster.membership.healthy.pct,5m) < {$ENVOY.OTLP.CLUSTER.HEALTHY.MIN.WARN}`|Warning||
|Envoy Proxy: Warning severity level entries in logs||`min(/Envoy Proxy by OTLP/envoy.otlp.logs.warn,5m) > {$ENVOY.OTLP.LOG.SEVERITY.WARN}`|Warning||
|Envoy Proxy: Error severity level entries in logs||`min(/Envoy Proxy by OTLP/envoy.otlp.logs.error,5m) > {$ENVOY.OTLP.LOG.SEVERITY.ERROR}`|Average||
|Envoy Proxy: Fatal severity level entries in logs||`min(/Envoy Proxy by OTLP/envoy.otlp.logs.fatal,5m) > {$ENVOY.OTLP.LOG.SEVERITY.FATAL}`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

