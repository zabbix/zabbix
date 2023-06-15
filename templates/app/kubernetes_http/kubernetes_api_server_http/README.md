
# Kubernetes API server by HTTP

## Overview

The template to monitor Kubernetes API server that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Kubernetes API server by HTTP` - collects metrics by HTTP agent from API server /metrics endpoint.

## Requirements

Zabbix version: 6.4 and higher.

## Tested versions

This template has been tested on:
- Kubernetes API server 1.19.10

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box) section.

## Setup

Internal service metrics are collected from /metrics endpoint.
Template needs to use Authorization via API token.

Don't forget change macros {$KUBE.API.SERVER.URL}, {$KUBE.API.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.

*NOTE.* Some metrics may not be collected depending on your Kubernetes API server instance version and configuration.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.SERVER.URL}|<p>Kubernetes API server metrics endpoint URL.</p>|`https://localhost:6443/metrics`|
|{$KUBE.API.TOKEN}|<p>API Authorization Token.</p>||
|{$KUBE.API.CERT.EXPIRATION}|<p>Number of days for alert of client certificate used for trigger.</p>|`7`|
|{$KUBE.API.HTTP.CLIENT.ERROR}|<p>Maximum number of HTTP client requests failures used for trigger.</p>|`2`|
|{$KUBE.API.HTTP.SERVER.ERROR}|<p>Maximum number of HTTP server requests failures used for trigger.</p>|`2`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: Get API instance metrics|<p>Get raw metrics from API instance /metrics endpoint.</p>|HTTP agent|kubernetes.api.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: Audit events, total|<p>Accumulated number audit events generated and sent to the audit backend.</p>|Dependent item|kubernetes.api.audit_event_total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(apiserver_audit_event_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: Virtual memory, bytes|<p>Virtual memory size in bytes.</p>|Dependent item|kubernetes.api.process_virtual_memory_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_virtual_memory_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: Resident memory, bytes|<p>Resident memory size in bytes.</p>|Dependent item|kubernetes.api.process_resident_memory_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_resident_memory_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: CPU|<p>Total user and system CPU usage ratio.</p>|Dependent item|kubernetes.api.cpu.util<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_cpu_seconds_total)`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Kubernetes API: Goroutines|<p>Number of goroutines that currently exist.</p>|Dependent item|kubernetes.api.go_goroutines<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(go_goroutines)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: Go threads|<p>Number of OS threads created.</p>|Dependent item|kubernetes.api.go_threads<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(go_threads)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: Fds open|<p>Number of open file descriptors.</p>|Dependent item|kubernetes.api.open_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_open_fds)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: Fds max|<p>Maximum allowed open file descriptors.</p>|Dependent item|kubernetes.api.max_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_max_fds)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: gRPCs client started, rate|<p>Total number of RPCs started per second.</p>|Dependent item|kubernetes.api.grpc_client_started.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(grpc_client_started_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: gRPCs messages received, rate|<p>Total number of gRPC stream messages received per second.</p>|Dependent item|kubernetes.api.grpc_client_msg_received.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(grpc_client_msg_received_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: gRPCs messages sent, rate|<p>Total number of gRPC stream messages sent per second.</p>|Dependent item|kubernetes.api.grpc_client_msg_sent.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(grpc_client_msg_sent_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: Request terminations, rate|<p>Number of requests which apiserver terminated in self-defense per second.</p>|Dependent item|kubernetes.api.apiserver_request_terminations<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(apiserver_request_terminations_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: TLS handshake errors, rate|<p>Number of requests dropped with 'TLS handshake error from' error per second.</p>|Dependent item|kubernetes.api.apiserver_tls_handshake_errors_total.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(apiserver_tls_handshake_errors_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: API server requests: 5xx, rate|<p>Counter of apiserver requests broken out for each HTTP response code.</p>|Dependent item|kubernetes.api.apiserver_request_total_500.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(apiserver_request_total{code =~ "5.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: API server requests: 4xx, rate|<p>Counter of apiserver requests broken out for each HTTP response code.</p>|Dependent item|kubernetes.api.apiserver_request_total_400.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(apiserver_request_total{code =~ "4.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: API server requests: 3xx, rate|<p>Counter of apiserver requests broken out for each HTTP response code.</p>|Dependent item|kubernetes.api.apiserver_request_total_300.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(apiserver_request_total{code =~ "3.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: API server requests: 0|<p>Counter of apiserver requests broken out for each HTTP response code.</p>|Dependent item|kubernetes.api.apiserver_request_total_0.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(apiserver_request_total{code = "0"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: API server requests: 2xx, rate|<p>Counter of apiserver requests broken out for each HTTP response code.</p>|Dependent item|kubernetes.api.apiserver_request_total_200.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(apiserver_request_total{code =~ "2.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: HTTP requests: 5xx, rate|<p>Number of HTTP requests with 5xx status code per second.</p>|Dependent item|kubernetes.api.rest_client_requests_total_500.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "5.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: HTTP requests: 4xx, rate|<p>Number of HTTP requests with 4xx status code per second.</p>|Dependent item|kubernetes.api.rest_client_requests_total_400.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "4.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: HTTP requests: 3xx, rate|<p>Number of HTTP requests with 3xx status code per second.</p>|Dependent item|kubernetes.api.rest_client_requests_total_300.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "3.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes API: HTTP requests: 2xx, rate|<p>Number of HTTP requests with 2xx status code per second.</p>|Dependent item|kubernetes.api.rest_client_requests_total_200.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "2.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes API: Too many server errors|<p>"Kubernetes API server is experiencing high error rate (with 5xx HTTP code).</p>|`min(/Kubernetes API server by HTTP/kubernetes.api.apiserver_request_total_500.rate,5m)>{$KUBE.API.HTTP.SERVER.ERROR}`|Warning||
|Kubernetes API: Too many client errors|<p>"Kubernetes API client is experiencing high error rate (with 5xx HTTP code).</p>|`min(/Kubernetes API server by HTTP/kubernetes.api.rest_client_requests_total_500.rate,5m)>{$KUBE.API.HTTP.CLIENT.ERROR}`|Warning||

### LLD rule Long-running requests

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Long-running requests|<p>Discovery of long-running requests by verb, resource and scope.</p>|Dependent item|kubernetes.api.longrunning_gauge.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Long-running requests

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: Long-running ["{#VERB}"] requests ["{#RESOURCE}"]: {#SCOPE}|<p>Gauge of all active long-running apiserver requests broken out by verb, resource and scope. Not all requests are tracked this way.</p>|Dependent item|kubernetes.api.longrunning_gauge["{#RESOURCE}","{#SCOPE}","{#VERB}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Request duration histogram

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Request duration histogram|<p>Discovery raw data and percentile items of request duration.</p>|Dependent item|kubernetes.api.requests_bucket.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `{__name__=~ "apiserver_request_duration_*", verb =~ ".*"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Request duration histogram

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: ["{#VERB}"] Requests bucket: {#LE}|<p>Response latency distribution in seconds for each verb.</p>|Dependent item|kubernetes.api.request_duration_seconds_bucket[{#LE},"{#VERB}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Kubernetes API: ["{#VERB}"] Requests, p90|<p>90 percentile of response latency distribution in seconds for each verb.</p>|Calculated|kubernetes.api.request_duration_seconds_p90["{#VERB}"]|
|Kubernetes API: ["{#VERB}"] Requests, p95|<p>95 percentile of response latency distribution in seconds for each verb.</p>|Calculated|kubernetes.api.request_duration_seconds_p95["{#VERB}"]|
|Kubernetes API: ["{#VERB}"] Requests, p99|<p>99 percentile of response latency distribution in seconds for each verb.</p>|Calculated|kubernetes.api.request_duration_seconds_p99["{#VERB}"]|
|Kubernetes API: ["{#VERB}"] Requests, p50|<p>50 percentile of response latency distribution in seconds for each verb.</p>|Calculated|kubernetes.api.request_duration_seconds_p50["{#VERB}"]|

### LLD rule Requests inflight discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Requests inflight discovery|<p>Discovery requests inflight by kind.</p>|Dependent item|kubernetes.api.inflight_requests.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `apiserver_current_inflight_requests{request_kind =~ ".*"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Requests inflight discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: Requests current: {#KIND}|<p>Maximal number of currently used inflight request limit of this apiserver per request kind in last second.</p>|Dependent item|kubernetes.api.current_inflight_requests["{#KIND}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule gRPC completed requests discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|gRPC completed requests discovery|<p>Discovery grpc completed requests by grpc code.</p>|Dependent item|kubernetes.api.grpc_client_handled.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `grpc_client_handled_total{grpc_code =~ ".*"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for gRPC completed requests discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: gRPCs completed: {#GRPC_CODE}, rate|<p>Total number of RPCs completed by the client regardless of success or failure per second.</p>|Dependent item|kubernetes.api.grpc_client_handled_total.rate["{#GRPC_CODE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(grpc_client_handled_total{grpc_code = "{#GRPC_CODE}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### LLD rule Authentication attempts discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Authentication attempts discovery|<p>Discovery authentication attempts by result.</p>|Dependent item|kubernetes.api.authentication_attempts.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `authentication_attempts{result =~ ".*"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Authentication attempts discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: Authentication attempts: {#RESULT}, rate|<p>Authentication attempts by result per second.</p>|Dependent item|kubernetes.api.authentication_attempts.rate["{#RESULT}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(authentication_attempts{result = "{#RESULT}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### LLD rule Authentication requests discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Authentication requests discovery|<p>Discovery authentication attempts by name.</p>|Dependent item|kubernetes.api.authenticated_user_requests.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `authenticated_user_requests{username =~ ".*"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Authentication requests discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: Authenticated requests: {#NAME}, rate|<p>Counter of authenticated requests broken out by username per second.</p>|Dependent item|kubernetes.api.authenticated_user_requests.rate["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(authenticated_user_requests{result = "{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### LLD rule Watchers metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Watchers metrics discovery|<p>Discovery watchers by kind.</p>|Dependent item|kubernetes.api.apiserver_registered_watchers.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `apiserver_registered_watchers{kind =~ ".*"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Watchers metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: Watchers: {#KIND}|<p>Number of currently registered watchers for a given resource.</p>|Dependent item|kubernetes.api.apiserver_registered_watchers["{#KIND}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(apiserver_registered_watchers{kind = "{#KIND}"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Etcd objects metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Etcd objects metrics discovery|<p>Discovery etcd objects by resource.</p>|Dependent item|kubernetes.api.etcd_object_counts.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `etcd_object_counts{resource =~ ".*"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Etcd objects metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: etcd objects: {#RESOURCE}|<p>Number of stored objects at the time of last check split by kind.</p>|Dependent item|kubernetes.api.etcd_object_counts["{#RESOURCE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(etcd_object_counts{ resource = "{#RESOURCE}"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Workqueue metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workqueue metrics discovery|<p>Discovery workqueue metrics by name.</p>|Dependent item|kubernetes.api.workqueue.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `workqueue_adds_total{name =~ ".*"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Workqueue metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: ["{#NAME}"] Workqueue depth|<p>Current depth of workqueue.</p>|Dependent item|kubernetes.api.workqueue_depth["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(workqueue_depth{name = "{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: ["{#NAME}"] Workqueue adds total, rate|<p>Total number of adds handled by workqueue per second.</p>|Dependent item|kubernetes.api.workqueue_adds_total.rate["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(workqueue_adds_total{name = "{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### LLD rule Client certificate expiration histogram

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Client certificate expiration histogram|<p>Discovery raw data of client certificate expiration</p>|Dependent item|kubernetes.api.certificate_expiration.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Client certificate expiration histogram

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes API: Certificate expiration seconds bucket, {#LE}|<p>Distribution of the remaining lifetime on the certificate used to authenticate a request.</p>|Dependent item|kubernetes.api.client_certificate_expiration_seconds_bucket[{#LE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes API: Client certificate expiration, p1|<p>1 percentile of the remaining lifetime on the certificate used to authenticate a request.</p>|Calculated|kubernetes.api.client_certificate_expiration_p1[{#SINGLETON}]|

### Trigger prototypes for Client certificate expiration histogram

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes API: Kubernetes client certificate is expiring|<p>A client certificate used to authenticate to the apiserver is expiring in {$KUBE.API.CERT.EXPIRATION} days.</p>|`last(/Kubernetes API server by HTTP/kubernetes.api.client_certificate_expiration_p1[{#SINGLETON}]) > 0 and last(/Kubernetes API server by HTTP/kubernetes.api.client_certificate_expiration_p1[{#SINGLETON}]) < {$KUBE.API.CERT.EXPIRATION}*24*60*60`|Warning|**Depends on**:<br><ul><li>Kubernetes API: Kubernetes client certificate expires soon</li></ul>|
|Kubernetes API: Kubernetes client certificate expires soon|<p>A client certificate used to authenticate to the apiserver is expiring in less than 24.0 hours.</p>|`last(/Kubernetes API server by HTTP/kubernetes.api.client_certificate_expiration_p1[{#SINGLETON}]) > 0 and last(/Kubernetes API server by HTTP/kubernetes.api.client_certificate_expiration_p1[{#SINGLETON}]) < 24*60*60`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

