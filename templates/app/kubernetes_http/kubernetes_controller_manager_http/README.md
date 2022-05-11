
# Kubernetes Controller manager by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Kubernetes Controller manager by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Kubernetes Controller manager by HTTP` — collects metrics by HTTP agent from Controller manager /metrics endpoint.



This template was tested on:

- Kubernetes Controller manager, version 1.19.10

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Internal service metrics are collected from /metrics endpoint.
Template needs to use Authorization via API token. 

Don't forget change macros {$KUBE.CONTROLLER.SERVER.URL}, {$KUBE.API.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.
*NOTE.* Some metrics may not be collected depending on your Kubernetes Controller manager instance version and configuration.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.TOKEN} |<p>API Authorization Token</p> |`` |
|{$KUBE.CONTROLLER.HTTP.CLIENT.ERROR} |<p>Maximum number of HTTP client requests failures used for trigger</p> |`2` |
|{$KUBE.CONTROLLER.SERVER.URL} |<p>Instance URL</p> |`http://localhost:10252/metrics` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Workqueue metrics discovery | |DEPENDENT |kubernetes.controller.workqueue.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~ "workqueue_*", name =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Overrides:**</p><p>bucket item<br> - {#TYPE} MATCHES_REGEX `buckets`<br>  - ITEM_PROTOTYPE LIKE `bucket` - DISCOVER</p><p>total item<br> - {#TYPE} MATCHES_REGEX `totals`<br>  - ITEM_PROTOTYPE NOT_LIKE `bucket` - DISCOVER</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Kubernetes Controller |Kubernetes Controller Manager: Leader election status |<p>Gauge of if the reporting system is master of the relevant lease, 0 indicates backup, 1 indicates master.</p> |DEPENDENT |kubernetes.controller.leader_election_master_status<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `leader_election_master_status`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: Virtual memory, bytes |<p>Virtual memory size in bytes.</p> |DEPENDENT |kubernetes.controller.process_virtual_memory_bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_virtual_memory_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: Resident memory, bytes |<p>Resident memory size in bytes.</p> |DEPENDENT |kubernetes.controller.process_resident_memory_bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_resident_memory_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: CPU |<p>Total user and system CPU usage ratio.</p> |DEPENDENT |kubernetes.controller.cpu.util<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_cpu_seconds_total`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|Kubernetes Controller |Kubernetes Controller Manager: Goroutines |<p>Number of goroutines that currently exist.</p> |DEPENDENT |kubernetes.controller.go_goroutines<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `go_goroutines`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: Go threads |<p>Number of OS threads created.</p> |DEPENDENT |kubernetes.controller.go_threads<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `go_threads`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: Fds open |<p>Number of open file descriptors.</p> |DEPENDENT |kubernetes.controller.open_fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_open_fds`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: Fds max |<p>Maximum allowed open file descriptors.</p> |DEPENDENT |kubernetes.controller.max_fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_max_fds`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: REST Client requests: 2xx, rate |<p>Number of HTTP requests with 2xx status code per second.</p> |DEPENDENT |kubernetes.controller.client_http_requests_200.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "2.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Controller |Kubernetes Controller Manager: REST Client requests: 3xx, rate |<p>Number of HTTP requests with 3xx status code per second.</p> |DEPENDENT |kubernetes.controller.client_http_requests_300.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "3.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Controller |Kubernetes Controller Manager: REST Client requests: 4xx, rate |<p>Number of HTTP requests with 4xx status code per second.</p> |DEPENDENT |kubernetes.controller.client_http_requests_400.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "4.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Controller |Kubernetes Controller Manager: REST Client requests: 5xx, rate |<p>Number of HTTP requests with 5xx status code per second.</p> |DEPENDENT |kubernetes.controller.client_http_requests_500.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "5.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue adds total, rate |<p>Total number of adds handled by workqueue per second.</p> |DEPENDENT |kubernetes.controller.workqueue_adds_total["{#NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `workqueue_adds_total{name = "{#NAME}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue depth |<p>Current depth of workqueue.</p> |DEPENDENT |kubernetes.controller.workqueue_depth["{#NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `workqueue_depth{name = "{#NAME}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue unfinished work, sec |<p>How many seconds of work has done that is in progress and hasn't been observed by work_duration. Large values indicate stuck threads. One can deduce the number of stuck threads by observing the rate at which this increases.</p> |DEPENDENT |kubernetes.controller.workqueue_unfinished_work_seconds["{#NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `workqueue_unfinished_work_seconds{name = "{#NAME}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue retries, rate |<p>Total number of retries handled by workqueue per second.</p> |DEPENDENT |kubernetes.controller.workqueue_retries_total["{#NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `workqueue_retries_total{name = "{#NAME}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue longest running processor, sec |<p>How many seconds has the longest running processor for workqueue been running.</p> |DEPENDENT |kubernetes.controller.workqueue_longest_running_processor_seconds["{#NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `workqueue_longest_running_processor_seconds{name = "{#NAME}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue work duration, p90 |<p>90 percentile of how long in seconds processing an item from workqueue takes, by queue.</p> |CALCULATED |kubernetes.controller.workqueue_work_duration_seconds_p90["{#NAME}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.controller.duration_seconds_bucket[*,"{#NAME}"],5m,90)` |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue work duration, p95 |<p>95 percentile of how long in seconds processing an item from workqueue takes, by queue.</p> |CALCULATED |kubernetes.controller.workqueue_work_duration_seconds_p95["{#NAME}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.controller.duration_seconds_bucket[*,"{#NAME}"],5m,95)` |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue work duration, p99 |<p>99 percentile of how long in seconds processing an item from workqueue takes, by queue.</p> |CALCULATED |kubernetes.controller.workqueue_work_duration_seconds_p99["{#NAME}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.controller.duration_seconds_bucket[*,"{#NAME}"],5m,99)` |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue work duration, 50p |<p>50 percentiles of how long in seconds processing an item from workqueue takes, by queue.</p> |CALCULATED |kubernetes.controller.workqueue_work_duration_seconds_p50["{#NAME}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.controller.duration_seconds_bucket[*,"{#NAME}"],5m,50)` |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue queue duration, p90 |<p>90 percentile of how long in seconds an item stays in workqueue before being requested, by queue.</p> |CALCULATED |kubernetes.controller.workqueue_queue_duration_seconds_p90["{#NAME}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.controller.queue_duration_seconds_bucket[*,"{#NAME}"],5m,90)` |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue queue duration, p95 |<p>95 percentile of how long in seconds an item stays in workqueue before being requested, by queue.</p> |CALCULATED |kubernetes.controller.workqueue_queue_duration_seconds_p95["{#NAME}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.controller.queue_duration_seconds_bucket[*,"{#NAME}"],5m,95)` |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue queue duration, p99 |<p>99 percentile of how long in seconds an item stays in workqueue before being requested, by queue.</p> |CALCULATED |kubernetes.controller.workqueue_queue_duration_seconds_p99["{#NAME}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.controller.queue_duration_seconds_bucket[*,"{#NAME}"],5m,99)` |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue queue duration, 50p |<p>50 percentile of how long in seconds an item stays in workqueue before being requested. If there are no requests for 5 minute, item value will be discarded.</p> |CALCULATED |kubernetes.controller.workqueue_queue_duration_seconds_p50["{#NAME}"]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>**Expression**:</p>`bucket_percentile(//kubernetes.controller.queue_duration_seconds_bucket[*,"{#NAME}"],5m,50)` |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Workqueue duration seconds bucket, {#LE} |<p>How long in seconds processing an item from workqueue takes.</p> |DEPENDENT |kubernetes.controller.duration_seconds_bucket[{#LE},"{#NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `workqueue_work_duration_seconds_bucket{name = "{#NAME}",le = "{#LE}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Controller |Kubernetes Controller Manager: ["{#NAME}"]: Queue duration seconds bucket, {#LE} |<p>How long in seconds an item stays in workqueue before being requested.</p> |DEPENDENT |kubernetes.controller.queue_duration_seconds_bucket[{#LE},"{#NAME}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `workqueue_queue_duration_seconds_bucket{name = "{#NAME}",le = "{#LE}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Zabbix raw items |Kubernetes Controller: Get Controller metrics |<p>Get raw metrics from Controller instance /metrics endpoint.</p> |HTTP_AGENT |kubernetes.controller.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Kubernetes Controller Manager: Too many HTTP client errors |<p>"Kubernetes Controller manager is experiencing high error rate (with 5xx HTTP code).</p> |`min(/Kubernetes Controller manager by HTTP/kubernetes.controller.client_http_requests_500.rate,5m)>{$KUBE.CONTROLLER.HTTP.CLIENT.ERROR}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

