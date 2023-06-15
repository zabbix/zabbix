
# Kubernetes Kubelet by HTTP

## Overview

The template to monitor Kubernetes Kubelet by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Kubernetes Kubelet by HTTP` - collects metrics by HTTP agent from Kubelet /metrics endpoint.

Don't forget change macros {$KUBE.KUBELET.URL}, {$KUBE.API.TOKEN}.

*NOTE.* Some metrics may not be collected depending on your Kubernetes instance version and configuration.

## Requirements

Zabbix version: 6.4 and higher.

## Tested versions

This template has been tested on:
- Kubernetes 1.19.10

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box) section.

## Setup

Internal service metrics are collected from /metrics endpoint.
Template needs to use Authorization via API token. 

Don't forget change macros {$KUBE.KUBELET.URL}, {$KUBE.API.TOKEN}.

*NOTE.* Some metrics may not be collected depending on your Kubernetes instance version and configuration.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.TOKEN}|<p>Service account bearer token.</p>||
|{$KUBE.KUBELET.URL}|<p>Kubernetes Kubelet instance URL.</p>|`https://localhost:10250`|
|{$KUBE.KUBELET.METRIC.ENDPOINT}|<p>Kubelet /metrics endpoint.</p>|`/metrics`|
|{$KUBE.KUBELET.CADVISOR.ENDPOINT}|<p>cAdvisor metrics from Kubelet /metrics/cadvisor endpoint.</p>|`/metrics/cadvisor`|
|{$KUBE.KUBELET.PODS.ENDPOINT}|<p>Kubelet /pods endpoint.</p>|`/pods`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes: Get kubelet metrics|<p>Collecting raw Kubelet metrics from /metrics endpoint.</p>|HTTP agent|kube.kubelet.metrics|
|Kubernetes: Get cadvisor metrics|<p>Collecting raw Kubelet metrics from /metrics/cadvisor endpoint.</p>|HTTP agent|kube.cadvisor.metrics|
|Kubernetes: Get pods|<p>Collecting raw Kubelet metrics from /pods endpoint.</p>|HTTP agent|kube.pods|
|Kubernetes: Pods running|<p>The number of running pods.</p>|Dependent item|kube.kubelet.pods.running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items[?(@.status.phase == "Running")].length()`</p></li></ul>|
|Kubernetes: Containers running|<p>The number of running containers.</p>|Dependent item|kube.kubelet.containers.running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items[*].status.containerStatuses[*].restartCount.sum()`</p></li></ul>|
|Kubernetes: Containers last state terminated|<p>The number of containers that were previously terminated.</p>|Dependent item|kube.kublet.containers.terminated<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Kubernetes: Containers restarts|<p>The number of times the container has been restarted.</p>|Dependent item|kube.kubelet.containers.restarts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items[*].status.containerStatuses[*].restartCount.sum()`</p></li></ul>|
|Kubernetes: CPU cores, total|<p>The number of cores in this machine (available until kubernetes v1.18).</p>|Dependent item|kube.kubelet.cpu.cores<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(machine_cpu_cores)`</p></li></ul>|
|Kubernetes: Machine memory, bytes|<p>Resident memory size in bytes.</p>|Dependent item|kube.kubelet.machine.memory<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_resident_memory_bytes)`</p></li></ul>|
|Kubernetes: Virtual memory, bytes|<p>Virtual memory size in bytes.</p>|Dependent item|kube.kubelet.virtual.memory<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_virtual_memory_bytes)`</p></li></ul>|
|Kubernetes: File descriptors, max|<p>Maximum number of open file descriptors.</p>|Dependent item|kube.kubelet.process_max_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_max_fds)`</p></li></ul>|
|Kubernetes: File descriptors, open|<p>Number of open file descriptors.</p>|Dependent item|kube.kubelet.process_open_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_open_fds)`</p></li></ul>|

### LLD rule Runtime operations discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Runtime operations discovery||Dependent item|kube.kubelet.runtime_operations_bucket.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Runtime operations discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes: [{#OP_TYPE}] Runtime operations bucket: {#LE}|<p>Duration in seconds of runtime operations. Broken down by operation type.</p>|Dependent item|kube.kublet.runtime_ops_duration_seconds_bucket[{#LE},"{#OP_TYPE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Kubernetes: [{#OP_TYPE}] Runtime operations total, rate|<p>Cumulative number of runtime operations by operation type.</p>|Dependent item|kube.kublet.runtime_ops_total.rate["{#OP_TYPE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes: [{#OP_TYPE}] Operations, p90|<p>90 percentile of operation latency distribution in seconds for each verb.</p>|Calculated|kube.kublet.runtime_ops_duration_seconds_p90["{#OP_TYPE}"]|
|Kubernetes: [{#OP_TYPE}] Operations, p95|<p>95 percentile of operation latency distribution in seconds for each verb.</p>|Calculated|kube.kublet.runtime_ops_duration_seconds_p95["{#OP_TYPE}"]|
|Kubernetes: [{#OP_TYPE}] Operations, p99|<p>99 percentile of operation latency distribution in seconds for each verb.</p>|Calculated|kube.kublet.runtime_ops_duration_seconds_p99["{#OP_TYPE}"]|
|Kubernetes: [{#OP_TYPE}] Operations, p50|<p>50 percentile of operation latency distribution in seconds for each verb.</p>|Calculated|kube.kublet.runtime_ops_duration_seconds_p50["{#OP_TYPE}"]|

### LLD rule Pods discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pods discovery||Dependent item|kube.kubelet.pods.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Item prototypes for Pods discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes: Namespace [{#NAMESPACE}] Pod [{#NAME}] CPU: Load average, 10s|<p>Pods cpu load average over the last 10 seconds.</p>|Dependent item|kube.pod.container_cpu_load_average_10s[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes: Namespace [{#NAMESPACE}] Pod [{#NAME}] CPU: System seconds, total|<p>System cpu time consumed. It is calculated from the cumulative value using the `Change per second` preprocessing step.</p>|Dependent item|kube.pod.container_cpu_system_seconds_total[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes: Namespace [{#NAMESPACE}] Pod [{#NAME}] CPU: Usage seconds, total|<p>Consumed cpu time. It is calculated from the cumulative value using the `Change per second` preprocessing step.</p>|Dependent item|kube.pod.container_cpu_usage_seconds_total[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes: Namespace [{#NAMESPACE}] Pod [{#NAME}] CPU: User seconds, total|<p>User cpu time consumed. It is calculated from the cumulative value using the `Change per second` preprocessing step.</p>|Dependent item|kube.pod.container_cpu_user_seconds_total[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### LLD rule REST client requests discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|REST client requests discovery||Dependent item|kube.kubelet.rest.requests.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for REST client requests discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes: Host [{#HOST}] Request method [{#METHOD}] Code:[{#CODE}]|<p>Number of HTTP requests, partitioned by status code, method, and host.</p>|Dependent item|kube.kubelet.rest.requests["{#CODE}", "{#HOST}", "{#METHOD}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### LLD rule Container memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Container memory discovery||Dependent item|kube.kubelet.container.memory.cache.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Container memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: Memory page cache|<p>Number of bytes of page cache memory.</p>|Dependent item|kube.kubelet.container.memory.cache["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: Memory max usage|<p>Maximum memory usage recorded in bytes.</p>|Dependent item|kube.kubelet.container.memory.max_usage["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: RSS|<p>Size of RSS in bytes.</p>|Dependent item|kube.kubelet.container.memory.rss["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: Swap|<p>Container swap usage in bytes.</p>|Dependent item|kube.kubelet.container.memory.swap["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: Usage|<p>Current memory usage in bytes, including all memory regardless of when it was accessed.</p>|Dependent item|kube.kubelet.container.memory.usage["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Kubernetes: Namespace [{#NAMESPACE}] Pod [{#POD}] Container [{#CONTAINER}]: Working set|<p>Current working set in bytes.</p>|Dependent item|kube.kubelet.container.memory.working_set["{#CONTAINER}", "{#NAMESPACE}", "{#POD}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

