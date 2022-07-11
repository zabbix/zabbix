
# Kubernetes API server by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor InfluxDB by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Kubernetes API server by HTTP` — collects metrics by HTTP agent from API server /metrics endpoint.



This template was tested on:

- Kubernetes API server, version 1.19.10

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Internal service metrics are collected from /metrics endpoint.
Template needs to use Authorization via API token.

Don't forget change macros {$KUBE.API.SERVER.URL}, {$KUBE.API.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.
*NOTE.* Some metrics may not be collected depending on your Kubernetes API server instance version and configuration.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.CERT.EXPIRATION} |<p>Number of days for alert of client certificate used for trigger</p> |`7` |
|{$KUBE.API.HTTP.CLIENT.ERROR} |<p>Maximum number of HTTP client requests failures used for trigger</p> |`2` |
|{$KUBE.API.HTTP.SERVER.ERROR} |<p>Maximum number of HTTP client requests failures used for trigger</p> |`2` |
|{$KUBE.API.SERVER.URL} |<p>instance URL</p> |`http://localhost:8086/metrics` |
|{$KUBE.API.TOKEN} |<p>API Authorization Token</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Authentication attempts discovery |<p>Discovery authentication attempts by result.</p> |DEPENDENT |kubernetes.api.authentication_attempts.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `authentication_attempts{result =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Authentication requests discovery |<p>Discovery authentication attempts by name.</p> |DEPENDENT |kubernetes.api.authenticated_user_requests.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `authenticated_user_requests{username =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Client certificate expiration histogram |<p>Discovery raw data of client certificate expiration</p> |DEPENDENT |kubernetes.api.certificate_expiration.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~ "apiserver_client_certificate_expiration_seconds_*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Overrides:**</p><p>bucket item<br> - {#TYPE} MATCHES_REGEX `buckets`<br>  - ITEM_PROTOTYPE LIKE `bucket` - DISCOVER</p><p>total item<br> - {#TYPE} MATCHES_REGEX `totals`<br>  - ITEM_PROTOTYPE NOT_LIKE `bucket` - DISCOVER</p> |
|Etcd objects metrics discovery |<p>Discovery etcd objects by resource.</p> |DEPENDENT |kubernetes.api.etcd_object_counts.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `etcd_object_counts{resource =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|gRPC completed requests discovery |<p>Discovery grpc completed requests by grpc code.</p> |DEPENDENT |kubernetes.api.grpc_client_handled.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `grpc_client_handled_total{grpc_code =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Long-running requests |<p>Discovery of long-running requests by verb, resource and scope.</p> |DEPENDENT |kubernetes.api.longrunning_gauge.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `apiserver_longrunning_gauge{resource =~ ".*", scope =~ ".*", verb =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Request duration histogram |<p>Discovery raw data and percentile items of request duration.</p> |DEPENDENT |kubernetes.api.requests_bucket.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~ "apiserver_request_duration_*", verb =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Overrides:**</p><p>bucket item<br> - {#TYPE} MATCHES_REGEX `buckets`<br>  - ITEM_PROTOTYPE LIKE `bucket` - DISCOVER</p><p>total item<br> - {#TYPE} MATCHES_REGEX `totals`<br>  - ITEM_PROTOTYPE NOT_LIKE `bucket` - DISCOVER</p> |
|Requests inflight discovery |<p>Discovery requests inflight by kind.</p> |DEPENDENT |kubernetes.api.inflight_requests.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `apiserver_current_inflight_requests{request_kind =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Watchers metrics discovery |<p>Discovery watchers by kind.</p> |DEPENDENT |kubernetes.api.apiserver_registered_watchers.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `apiserver_registered_watchers{kind =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Workqueue metrics discovery |<p>Discovery workqueue metrics by name.</p> |DEPENDENT |kubernetes.api.workqueue.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `workqueue_adds_total{name =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Kubernetes API |Kubernetes API: Audit events, total |<p>Accumulated number audit events generated and sent to the audit backend.</p> |DEPENDENT |kubernetes.api.audit_event_total<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_audit_event_total`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: Virtual memory, bytes |<p>Virtual memory size in bytes.</p> |DEPENDENT |kubernetes.api.process_virtual_memory_bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_virtual_memory_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: Resident memory, bytes |<p>Resident memory size in bytes.</p> |DEPENDENT |kubernetes.api.process_resident_memory_bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_resident_memory_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: CPU |<p>Total user and system CPU usage ratio.</p> |DEPENDENT |kubernetes.api.cpu.util<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_cpu_seconds_total`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|Kubernetes API |Kubernetes API: Goroutines |<p>Number of goroutines that currently exist.</p> |DEPENDENT |kubernetes.api.go_goroutines<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `go_goroutines`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: Go threads |<p>Number of OS threads created.</p> |DEPENDENT |kubernetes.api.go_threads<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `go_threads`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: Fds open |<p>Number of open file descriptors.</p> |DEPENDENT |kubernetes.api.open_fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_open_fds`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: Fds max |<p>Maximum allowed open file descriptors.</p> |DEPENDENT |kubernetes.api.max_fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_max_fds`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: gRPCs client started, rate |<p>Total number of RPCs started per second.</p> |DEPENDENT |kubernetes.api.grpc_client_started.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `grpc_client_started_total`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: gRPCs messages ressived, rate |<p>Total number of gRPC stream messages received per second.</p> |DEPENDENT |kubernetes.api.grpc_client_msg_received.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `grpc_client_msg_received_total`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: gRPCs messages sent, rate |<p>Total number of gRPC stream messages sent per second.</p> |DEPENDENT |kubernetes.api.grpc_client_msg_sent.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `grpc_client_msg_sent_total`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: Request terminations, rate |<p>Number of requests which apiserver terminated in self-defense per second.</p> |DEPENDENT |kubernetes.api.apiserver_request_terminations<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_request_terminations_total`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: TLS handshake errors, rate |<p>Number of requests dropped with 'TLS handshake error from' error per second.</p> |DEPENDENT |kubernetes.api.apiserver_tls_handshake_errors_total.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_tls_handshake_errors_total`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: API server requests: 5xx, rate |<p>Counter of apiserver requests broken out for each HTTP response code.</p> |DEPENDENT |kubernetes.api.apiserver_request_total_500.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_request_total{code =~ "5.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: API server requests: 4xx, rate |<p>Counter of apiserver requests broken out for each HTTP response code.</p> |DEPENDENT |kubernetes.api.apiserver_request_total_400.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_request_total{code =~ "4.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: API server requests: 3xx, rate |<p>Counter of apiserver requests broken out for each HTTP response code.</p> |DEPENDENT |kubernetes.api.apiserver_request_total_300.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_request_total{code =~ "3.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: API server requests: 0 |<p>Counter of apiserver requests broken out for each HTTP response code.</p> |DEPENDENT |kubernetes.api.apiserver_request_total_0.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_request_total{code = "0"}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: API server requests: 2xx, rate |<p>Counter of apiserver requests broken out for each HTTP response code.</p> |DEPENDENT |kubernetes.api.apiserver_request_total_200.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_request_total{code =~ "2.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: HTTP requests: 5xx, rate |<p>Number of HTTP requests with 5xx status code per second.</p> |DEPENDENT |kubernetes.api.rest_client_requests_total_500.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "5.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: HTTP requests: 4xx, rate |<p>Number of HTTP requests with 4xx status code per second.</p> |DEPENDENT |kubernetes.api.rest_client_requests_total_400.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "4.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: HTTP requests: 3xx, rate |<p>Number of HTTP requests with 3xx status code per second.</p> |DEPENDENT |kubernetes.api.rest_client_requests_total_300.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "3.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: HTTP requests: 2xx, rate |<p>Number of HTTP requests with 2xx status code per second.</p> |DEPENDENT |kubernetes.api.rest_client_requests_total_200.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "2.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: Long-running ["{#VERB}"] requests ["{#RESOURCE}"]: {#SCOPE} |<p>Gauge of all active long-running apiserver requests broken out by verb, resource and scope. Not all requests are tracked this way.</p> |DEPENDENT |kubernetes.api.longrunning_gauge["{#RESOURCE}","{#SCOPE}","{#VERB}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_longrunning_gauge{resource = "{#RESOURCE}", scope = "{#SCOPE}", verb = "{#VERB}"}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: ["{#VERB}"] Requests bucket: {#LE} |<p>Response latency distribution in seconds for each verb.</p> |DEPENDENT |kubernetes.api.request_duration_seconds_bucket[{#LE},"{#VERB}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_request_duration_seconds_bucket{le="{#LE}",verb="{#VERB}"}`: `function`: `sum`</p> |
|Kubernetes API |Kubernetes API: ["{#VERB}"] Requests, p90 |<p>90 percentile of response latency distribution in seconds for each verb.</p> |CALCULATED |kubernetes.api.request_duration_seconds_p90["{#VERB}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.api.request_duration_seconds_bucket[*,"{#VERB}"],5m,90)` |
|Kubernetes API |Kubernetes API: ["{#VERB}"] Requests, p95 |<p>95 percentile of response latency distribution in seconds for each verb.</p> |CALCULATED |kubernetes.api.request_duration_seconds_p95["{#VERB}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.api.request_duration_seconds_bucket[*,"{#VERB}"],5m,95)` |
|Kubernetes API |Kubernetes API: ["{#VERB}"] Requests, p99 |<p>99 percentile of response latency distribution in seconds for each verb.</p> |CALCULATED |kubernetes.api.request_duration_seconds_p99["{#VERB}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.api.request_duration_seconds_bucket[*,"{#VERB}"],5m,99)` |
|Kubernetes API |Kubernetes API: ["{#VERB}"] Requests, p50 |<p>50 percentile of response latency distribution in seconds for each verb.</p> |CALCULATED |kubernetes.api.request_duration_seconds_p50["{#VERB}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.api.request_duration_seconds_bucket[*,"{#VERB}"],5m,50)` |
|Kubernetes API |Kubernetes API: Requests current: {#KIND} |<p>Maximal number of currently used inflight request limit of this apiserver per request kind in last second.</p> |DEPENDENT |kubernetes.api.current_inflight_requests["{#KIND}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_current_inflight_requests{request_kind = "{#KIND}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: gRPCs completed: {#GRPC_CODE}, rate |<p>Total number of RPCs completed by the client regardless of success or failure per second.</p> |DEPENDENT |kubernetes.api.grpc_client_handled_total.rate["{#GRPC_CODE}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `grpc_client_handled_total{grpc_code = "{#GRPC_CODE}"}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: Authentication attempts: {#RESULT}, rate |<p>Authentication attempts by result per second.</p> |DEPENDENT |kubernetes.api.authentication_attempts.rate["{#RESULT}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `authentication_attempts{result = "{#RESULT}"}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: Authenticated requests: {#NAME}, rate |<p>Counter of authenticated requests broken out by username per second.</p> |DEPENDENT |kubernetes.api.authenticated_user_requests.rate["{#NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `authenticated_user_requests{result = "{#NAME}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: Watchers: {#KIND} |<p>Number of currently registered watchers for a given resource.</p> |DEPENDENT |kubernetes.api.apiserver_registered_watchers["{#KIND}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_registered_watchers{kind = "{#KIND}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: etcd objects: {#RESOURCE} |<p>Number of stored objects at the time of last check split by kind.</p> |DEPENDENT |kubernetes.api.etcd_object_counts["{#RESOURCE}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `etcd_object_counts{ resource = "{#RESOURCE}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: ["{#NAME}"] Workqueue depth |<p>Current depth of workqueue.</p> |DEPENDENT |kubernetes.api.workqueue_depth["{#NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `workqueue_depth{name = "{#NAME}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: ["{#NAME}"] Workqueue adds total, rate |<p>Total number of adds handled by workqueue per second.</p> |DEPENDENT |kubernetes.api.workqueue_adds_total.rate["{#NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `workqueue_adds_total{name = "{#NAME}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes API |Kubernetes API: Certificate expiration seconds bucket, {#LE} |<p>Distribution of the remaining lifetime on the certificate used to authenticate a request.</p> |DEPENDENT |kubernetes.api.client_certificate_expiration_seconds_bucket[{#LE}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `apiserver_client_certificate_expiration_seconds_bucket{le = "{#LE}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes API |Kubernetes API: Client certificate expiration, p1 |<p>1 percentile of of the remaining lifetime on the certificate used to authenticate a request.</p> |CALCULATED |kubernetes.api.client_certificate_expiration_p1[{#SINGLETON}]<p>**Expression**:</p>`bucket_percentile(//kubernetes.api.client_certificate_expiration_seconds_bucket[*],5m,1)` |
|Zabbix raw items |Kubernetes API: Get API instance metrics |<p>Get raw metrics from API instance /metrics endpoint.</p> |HTTP_AGENT |kubernetes.api.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Kubernetes API: Too many server errors |<p>"Kubernetes API server is experiencing high error rate (with 5xx HTTP code).</p> |`min(/Kubernetes API server by HTTP/kubernetes.api.apiserver_request_total_500.rate,5m)>{$KUBE.API.HTTP.SERVER.ERROR}` |WARNING | |
|Kubernetes API: Too many client errors |<p>"Kubernetes API client is experiencing high error rate (with 5xx HTTP code).</p> |`min(/Kubernetes API server by HTTP/kubernetes.api.rest_client_requests_total_500.rate,5m)>{$KUBE.API.HTTP.CLIENT.ERROR}` |WARNING | |
|Kubernetes API: Kubernetes client certificate is expiring |<p>A client certificate used to authenticate to the apiserver is expiring in {$KUBE.API.CERT.EXPIRATION} days.</p> |`last(/Kubernetes API server by HTTP/kubernetes.api.client_certificate_expiration_p1[{#SINGLETON}]) > 0 and last(/Kubernetes API server by HTTP/kubernetes.api.client_certificate_expiration_p1[{#SINGLETON}]) < {$KUBE.API.CERT.EXPIRATION}*24*60*60` |WARNING |<p>**Depends on**:</p><p>- Kubernetes API: Kubernetes client certificate expires soon</p> |
|Kubernetes API: Kubernetes client certificate expires soon |<p>A client certificate used to authenticate to the apiserver is expiring in less than 24.0 hours.</p> |`last(/Kubernetes API server by HTTP/kubernetes.api.client_certificate_expiration_p1[{#SINGLETON}]) > 0 and last(/Kubernetes API server by HTTP/kubernetes.api.client_certificate_expiration_p1[{#SINGLETON}]) < 24*60*60` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

