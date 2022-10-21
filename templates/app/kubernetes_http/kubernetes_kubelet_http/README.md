
# Kubernetes Kubelet by HTTP

## Overview

For Zabbix version: 6.2 and higher.
The template to monitor Kubernetes Controller manager by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Kubernetes Controller manager by HTTP` — collects metrics by HTTP agent from Controller manager /metrics endpoint.

Don't forget change macros {$KUBE.KUBELET.URL}, {$KUBE.API.TOKEN}.
*NOTE.* Some metrics may not be collected depending on your Kubernetes instance version and configuration.



This template was tested on:

- Kubernetes, version 1.19

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

Internal service metrics are collected from /metrics endpoint.
Template needs to use Authorization via API token. 

Don't forget change macros {$KUBE.KUBELET.URL}, {$KUBE.API.TOKEN}.
*NOTE.* Some metrics may not be collected depending on your Kubernetes instance version and configuration.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.TOKEN} |<p>Service account bearer token</p> |`` |
|{$KUBE.KUBELET.CADVISOR.ENDPOINT} |<p>cAdvisor metrics from Kubelet /metrics/cadvisor endpoint</p> |`/metrics/cadvisor` |
|{$KUBE.KUBELET.METRIC.ENDPOINT} |<p>Kubelet /metrics endpoint</p> |`/metrics` |
|{$KUBE.KUBELET.PODS.ENDPOINT} |<p>Kubelet /pods endpoint</p> |`/pods` |
|{$KUBE.KUBELET.URL} |<p>Instance URL</p> |`https://localhost:10250` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Container memory discovery | |DEPENDENT |kube.kubelet.container.memory.cache.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON<p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Pods discovery | |DEPENDENT |kube.kubelet.pods.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|REST client requests discovery | |DEPENDENT |kube.kubelet.rest.requests.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON<p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Runtime operations discovery | |DEPENDENT |kube.kubelet.runtime_operations_bucket.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~ "kubelet_runtime_operations_*", operation_type =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Overrides:**</p><p>bucket item<br> - {#TYPE} MATCHES_REGEX `buckets`<br>  - ITEM_PROTOTYPE LIKE `bucket`<br>  - DISCOVER</p><p>total item<br> - {#TYPE} MATCHES_REGEX `totals`<br>  - ITEM_PROTOTYPE NOT_LIKE `bucket`<br>  - DISCOVER</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Kubernetes |Kubernetes: Get kubelet metrics |<p>Collecting raw Kubelet metrics from /metrics endpoint.</p> |HTTP_AGENT |kube.kubelet.metrics |
|Kubernetes |Kubernetes: Get cadvisor metrics |<p>Collecting raw Kubelet metrics from /metrics/cadvisor endpoint.</p> |HTTP_AGENT |kube.cadvisor.metrics |
|Kubernetes |Kubernetes: Get pods |<p>Collecting raw Kubelet metrics from /pods endpoint.</p> |HTTP_AGENT |kube.pods |
|Kubernetes |Kubernetes: Pods running |<p>The number of running pods.</p> |DEPENDENT |kube.kubelet.pods.running<p>**Preprocessing**:</p><p>- JSONPATH: `$.items[?(@.status.phase == "Running")].length()`</p> |
|Kubernetes |Kubernetes: Containers running |<p>The number of running containers.</p> |DEPENDENT |kube.kubelet.containers.running<p>**Preprocessing**:</p><p>- JSONPATH: `$.items[*].status.containerStatuses[*].restartCount.sum()`</p> |
|Kubernetes |Kubernetes: Containers last state terminated |<p>The number of containers that were previously terminated.</p> |DEPENDENT |kube.kublet.containers.terminated<p>**Preprocessing**:</p><p>- JSONPATH: `$.items[*].status.containerStatuses[?(@.lastState.terminated.exitCode > 0)].length()`</p> |
|Kubernetes |Kubernetes: Containers restarts |<p>The number of times the container has been restarted.</p> |DEPENDENT |kube.kubelet.containers.restarts<p>**Preprocessing**:</p><p>- JSONPATH: `$.items[*].status.containerStatuses[*].restartCount.sum()`</p> |
|Kubernetes |Kubernetes: CPU cores, total |<p>The number of cores in this machine (available until kubernetes v1.18).</p> |DEPENDENT |kube.kubelet.cpu.cores<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `machine_cpu_cores`</p> |
|Kubernetes |Kubernetes: Machine memory, bytes |<p>Resident memory size in bytes.</p> |DEPENDENT |kube.kubelet.machine.memory<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_resident_memory_bytes`</p> |
|Kubernetes |Kubernetes: Virtual memory, bytes |<p>Virtual memory size in bytes.</p> |DEPENDENT |kube.kubelet.virtual.memory<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_virtual_memory_bytes`</p> |
|Kubernetes |Kubernetes: File descriptors, max |<p>Maximum number of open file descriptors.</p> |DEPENDENT |kube.kubelet.process_max_fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_max_fds`</p> |
|Kubernetes |Kubernetes: File descriptors, open |<p>Number of open file descriptors.</p> |DEPENDENT |kube.kubelet.process_open_fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_open_fds`</p> |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Runtime operations bucket: {#LE} |<p>Duration in seconds of runtime operations. Broken down by operation type.</p> |DEPENDENT |kube.kublet.runtime_ops_duration_seconds_bucket[{#LE},"{#OP_TYPE}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `kubelet_runtime_operations_duration_seconds_bucket{le="{#LE}",operation_type="{#OP_TYPE}"}`: `function`: `sum`</p> |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Runtime operations total, rate |<p>Cumulative number of runtime operations by operation type.</p> |DEPENDENT |kube.kublet.runtime_ops_total.rate["{#OP_TYPE}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `kubelet_runtime_operations_total{operation_type="{#OP_TYPE}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Operations, p90 |<p>90 percentile of operation latency distribution in seconds for each verb.</p> |CALCULATED |kube.kublet.runtime_ops_duration_seconds_p90["{#OP_TYPE}"]<p>**Expression**:</p>`bucket_percentile(//kube.kublet.runtime_ops_duration_seconds_bucket[*,"{#OP_TYPE}"],5m,90)` |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Operations, p95 |<p>95 percentile of operation latency distribution in seconds for each verb.</p> |CALCULATED |kube.kublet.runtime_ops_duration_seconds_p95["{#OP_TYPE}"]<p>**Expression**:</p>`bucket_percentile(//kube.kublet.runtime_ops_duration_seconds_bucket[*,"{#OP_TYPE}"],5m,95)` |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Operations, p99 |<p>99 percentile of operation latency distribution in seconds for each verb.</p> |CALCULATED |kube.kublet.runtime_ops_duration_seconds_p99["{#OP_TYPE}"]<p>**Expression**:</p>`bucket_percentile(//kube.kublet.runtime_ops_duration_seconds_bucket[*,"{#OP_TYPE}"],5m,99)` |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Operations, p50 |<p>50 percentile of operation latency distribution in seconds for each verb.</p> |CALCULATED |kube.kublet.runtime_ops_duration_seconds_p50["{#OP_TYPE}"]<p>**Expression**:</p>`bucket_percentile(//kube.kublet.runtime_ops_duration_seconds_bucket[*,"{#OP_TYPE}"],5m,50)` |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#NAME}] CPU: Load average, 10s |<p>Pods cpu load average over the last 10 seconds.</p> |DEPENDENT |kube.pod.container_cpu_load_average_10s[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `container_cpu_load_average_10s{pod="{#NAME}", namespace="{#NAMESPACE}"}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#NAME}] CPU: System seconds, total |<p>The number of cores used for system time.</p> |DEPENDENT |kube.pod.container_cpu_system_seconds_total[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `container_cpu_system_seconds_total{pod="{#NAME}", namespace="{#NAMESPACE}"}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#NAME}] CPU: User seconds, total |<p>The number of cores used for user time.</p> |DEPENDENT |kube.pod.container_cpu_user_seconds_total[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `container_cpu_user_seconds_total{pod="{#NAME}", namespace="{#NAMESPACE}"}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes |Kubernetes: Host [{#HOST}] Request method [{#METHOD}] Code:[{#CODE}] |<p>Number of HTTP requests, partitioned by status code, method, and host.</p> |DEPENDENT |kube.kubelet.rest.requests["{#CODE}", "{#HOST}", "{#METHOD}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code="{#CODE}", host="{#HOST}", method="{#METHOD}"}`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: Memory page cache |<p>Number of bytes of page cache memory.</p> |DEPENDENT |kube.kubelet.container.memory.cache["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `container_memory_cache{container="{#CONTAINER}", namespace="{#NAMESPACE}", pod="{#POD}"}`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: Memory max usage |<p>Maximum memory usage recorded in bytes.</p> |DEPENDENT |kube.kubelet.container.memory.max_usage["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `container_memory_max_usage_bytes{container="{#CONTAINER}", namespace="{#NAMESPACE}", pod="{#POD}"}`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: RSS |<p>Size of RSS in bytes.</p> |DEPENDENT |kube.kubelet.container.memory.rss["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `container_memory_rss{container="{#CONTAINER}", namespace="{#NAMESPACE}", pod="{#POD}"}`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: Swap |<p>Container swap usage in bytes.</p> |DEPENDENT |kube.kubelet.container.memory.swap["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `container_memory_swap{container="{#CONTAINER}", namespace="{#NAMESPACE}", pod="{#POD}"}`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: Usage |<p>Current memory usage in bytes, including all memory regardless of when it was accessed.</p> |DEPENDENT |kube.kubelet.container.memory.usage["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `container_memory_usage_bytes{container="{#CONTAINER}", namespace="{#NAMESPACE}", pod="{#POD}"}`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: Working set |<p>Current working set in bytes.</p> |DEPENDENT |kube.kubelet.container.memory.working_set["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `container_memory_working_set_bytes{container="{#CONTAINER}", namespace="{#NAMESPACE}", pod="{#POD}"}`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

