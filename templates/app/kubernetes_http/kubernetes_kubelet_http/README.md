
# Kubernetes kubelet by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Kubernetes Controller manager by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Kubernetes Controller manager by HTTP` — collects metrics by HTTP agent from Controller manager /metrics endpoint.



This template was tested on:

- Kubernetes, version 1.19

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Internal service metrics are collected from /metrics endpoint.
Template need to use Authorization via API token. 

Don't forget change macros {$KUBE.KUBELET.URL}, {$KUBE.API.TOKEN}.
*NOTE.* Some metrics may not be collected depending on your Kubernetes instance version and configuration.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.TOKEN} |<p>Service account bearer token</p> |`` |
|{$KUBE.KUBELET.URL} | |`https://localhost:10250` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Runtime operations discovery | |DEPENDENT |kube.kubelet.runtime_operations_bucket.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~ "kubelet_runtime_operations_*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Overrides:**</p><p>bucket item<br> - {#TYPE} MATCHES_REGEX `buckets`<br>  - ITEM_PROTOTYPE LIKE `bucket` - DISCOVER</p><p>total item<br> - {#TYPE} MATCHES_REGEX `totals`<br>  - ITEM_PROTOTYPE NOT_LIKE `bucket` - DISCOVER</p> |
|Pods discovery | |DEPENDENT |kube.kubelet.pods.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Kubernetes |Kubernetes: Get kubelet metrics |<p>Description</p> |HTTP_AGENT |kube.kubelet.metrics |
|Kubernetes |Kubernetes: Get cadvisor metrics |<p>Description</p> |HTTP_AGENT |kube.cadvisor.metrics |
|Kubernetes |Kubernetes: Get pods |<p>Description</p> |HTTP_AGENT |kube.pods |
|Kubernetes |Kubernetes: Pods running |<p>The number of running pods.</p> |DEPENDENT |kube.kubelet.pods.running<p>**Preprocessing**:</p><p>- JSONPATH |
|Kubernetes |Kubernetes: Containers running |<p>The number of running containers.</p> |DEPENDENT |kube.kubelet.containers.running<p>**Preprocessing**:</p><p>- JSONPATH |
|Kubernetes |Kubernetes: Containers last state terminated |<p>The number of containers that were previously terminated.</p> |DEPENDENT |kube.kublet.containers.terminated<p>**Preprocessing**:</p><p>- JSONPATH |
|Kubernetes |Kubernetes: Containers restarts |<p>The number of times the container has been restarted.</p> |DEPENDENT |kube.kubelet.containers.restarts<p>**Preprocessing**:</p><p>- JSONPATH |
|Kubernetes |Kubernetes: CPU cores, total |<p>The number of cores in this machine (available until kubernetes v1.18).</p> |DEPENDENT |kube.kubelet.cpu.cores<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN |
|Kubernetes |Kubernetes: Machine memory, bytes |<p>Resident memory size in bytes.</p> |DEPENDENT |kube.kubelet.machine.memory<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN |
|Kubernetes |Kubernetes: Virtual memory, bytes |<p>Virtual memory size in bytes.</p> |DEPENDENT |kube.kubelet.virtual.memory<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN |
|Kubernetes |Kubernetes: File descriptors, max |<p>Maximum number of open file descriptors.</p> |DEPENDENT |kube.kubelet.process_max_fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN |
|Kubernetes |Kubernetes: File descriptors, open |<p>Number of open file descriptors.</p> |DEPENDENT |kube.kubelet.process_open_fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Runtime operations bucket: le={#LE} |<p>Duration in seconds of runtime operations. Broken down by operation type.</p> |DEPENDENT |kube.kublet.runtime_ops_duration_seconds_bucket["{#OP_TYPE}",{#LE}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Runtime operations total, rate |<p>Cumulative number of runtime operations by operation type.</p> |DEPENDENT |kube.kublet.runtime_ops_total.rate["{#OP_TYPE}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN<p>- CHANGE_PER_SECOND |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Operations, p90 |<p>90 percentile of operation latency distribution in seconds for each verb.</p> |CALCULATED |kube.kublet.runtime_ops_duration_seconds_p90[{#OP_TYPE}]<p>**Expression**:</p>`bucket_percentile(//kube.kublet.runtime_ops_duration_seconds_bucket["{#OP_TYPE}",*],5m,90)` |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Operations, p95 |<p>95 percentile of operation latency distribution in seconds for each verb.</p> |CALCULATED |kube.kublet.runtime_ops_duration_seconds_p95[{#OP_TYPE}]<p>**Expression**:</p>`bucket_percentile(//kube.kublet.runtime_ops_duration_seconds_bucket["{#OP_TYPE}",*],5m,95)` |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Operations, p99 |<p>99 percentile of operation latency distribution in seconds for each verb.</p> |CALCULATED |kube.kublet.runtime_ops_duration_seconds_p99[{#OP_TYPE}]<p>**Expression**:</p>`bucket_percentile(//kube.kublet.runtime_ops_duration_seconds_bucket["{#OP_TYPE}",*],5m,99)` |
|Kubernetes |Kubernetes: [{#OP_TYPE}] Operations, p50 |<p>50 percentile of operation latency distribution in seconds for each verb.</p> |CALCULATED |kube.kublet.runtime_ops_duration_seconds_p50[{#OP_TYPE}]<p>**Expression**:</p>`bucket_percentile(//kube.kublet.runtime_ops_duration_seconds_bucket["{#OP_TYPE}",*],5m,50)` |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#NAME}] CPU: Load average, 10s |<p>Pods cpu load average over the last 10 seconds.</p> |DEPENDENT |kube.pod.container_cpu_load_average_10s[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#NAME}] CPU: System seconds, total |<p>The number of cores used for system time.</p> |DEPENDENT |kube.pod.container_cpu_system_seconds_total[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN |
|Kubernetes |Kubernetes: Namespace [{#NAMESPACE}] Pod [{#NAME}] CPU: User seconds, total |<p>The number of cores used for user time.</p> |DEPENDENT |kube.pod.container_cpu_user_seconds_total[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

