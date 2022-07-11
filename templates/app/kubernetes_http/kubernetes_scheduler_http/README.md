
# Kubernetes Scheduler by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Kubernetes Scheduler by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Kubernetes Scheduler by HTTP` — collects metrics by HTTP agent from Scheduler /metrics endpoint.



This template was tested on:

- Kubernetes Scheduler, version 1.19.10

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Internal service metrics are collected from /metrics endpoint.
Template needs to use Authorization via API token.

Don't forget change macros {$KUBE.SCHEDULER.SERVER.URL}, {$KUBE.API.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.
*NOTE.* Some metrics may not be collected depending on your Kubernetes Scheduler instance version and configuration.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.TOKEN} |<p>API Authorization Token</p> |`` |
|{$KUBE.SCHEDULER.ERROR} |<p>Maximum number of scheduling failures with 'error' used for trigger</p> |`2` |
|{$KUBE.SCHEDULER.HTTP.CLIENT.ERROR} |<p>Maximum number of HTTP client requests failures used for trigger</p> |`2` |
|{$KUBE.SCHEDULER.SERVER.URL} |<p>Instance URL</p> |`http://localhost:10251/metrics` |
|{$KUBE.SCHEDULER.UNSCHEDULABLE} |<p>Maximum number of scheduling failures with 'unschedulable' used for trigger</p> |`2` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Binding histogram |<p>Discovery raw data of binding latency.</p> |DEPENDENT |kubernetes.scheduler.binding.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~ "scheduler_binding_duration_seconds_*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Overrides:**</p><p>bucket item<br> - {#TYPE} MATCHES_REGEX `buckets`<br>  - ITEM_PROTOTYPE LIKE `bucket` - DISCOVER</p><p>total item<br> - {#TYPE} MATCHES_REGEX `totals`<br>  - ITEM_PROTOTYPE NOT_LIKE `bucket` - DISCOVER</p> |
|e2e scheduling histogram |<p>Discovery raw data and percentile items of e2e scheduling latency.</p> |DEPENDENT |kubernetes.controller.e2e_scheduling.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~ "scheduler_e2e_scheduling_duration_*", result =~ ".*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Overrides:**</p><p>bucket item<br> - {#TYPE} MATCHES_REGEX `buckets`<br>  - ITEM_PROTOTYPE LIKE `bucket` - DISCOVER</p><p>total item<br> - {#TYPE} MATCHES_REGEX `totals`<br>  - ITEM_PROTOTYPE NOT_LIKE `bucket` - DISCOVER</p> |
|Scheduling algorithm histogram |<p>Discovery raw data of scheduling algorithm latency.</p> |DEPENDENT |kubernetes.scheduler.scheduling_algorithm.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `{__name__=~ "scheduler_scheduling_algorithm_duration_seconds_*"}`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Overrides:**</p><p>bucket item<br> - {#TYPE} MATCHES_REGEX `buckets`<br>  - ITEM_PROTOTYPE LIKE `bucket` - DISCOVER</p><p>total item<br> - {#TYPE} MATCHES_REGEX `totals`<br>  - ITEM_PROTOTYPE NOT_LIKE `bucket` - DISCOVER</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Kubernetes Scheduler |Kubernetes Scheduler: Virtual memory, bytes |<p>Virtual memory size in bytes.</p> |DEPENDENT |kubernetes.scheduler.process_virtual_memory_bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_virtual_memory_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Resident memory, bytes |<p>Resident memory size in bytes.</p> |DEPENDENT |kubernetes.scheduler.process_resident_memory_bytes<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_resident_memory_bytes`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: CPU |<p>Total user and system CPU usage ratio.</p> |DEPENDENT |kubernetes.scheduler.cpu.util<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_cpu_seconds_total`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `100`</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Goroutines |<p>Number of goroutines that currently exist.</p> |DEPENDENT |kubernetes.scheduler.go_goroutines<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `go_goroutines`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Go threads |<p>Number of OS threads created.</p> |DEPENDENT |kubernetes.scheduler.go_threads<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `go_threads`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Fds open |<p>Number of open file descriptors.</p> |DEPENDENT |kubernetes.scheduler.open_fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_open_fds`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Fds max |<p>Maximum allowed open file descriptors.</p> |DEPENDENT |kubernetes.scheduler.max_fds<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `process_max_fds`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: REST Client requests: 2xx, rate |<p>Number of HTTP requests with 2xx status code per second.</p> |DEPENDENT |kubernetes.scheduler.client_http_requests_200.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "2.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: REST Client requests: 3xx, rate |<p>Number of HTTP requests with 3xx status code per second.</p> |DEPENDENT |kubernetes.scheduler.client_http_requests_300.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "3.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: REST Client requests: 4xx, rate |<p>Number of HTTP requests with 4xx status code per second.</p> |DEPENDENT |kubernetes.scheduler.client_http_requests_400.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "4.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: REST Client requests: 5xx, rate |<p>Number of HTTP requests with 5xx status code per second.</p> |DEPENDENT |kubernetes.scheduler.client_http_requests_500.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `rest_client_requests_total{code =~ "5.."}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Schedule attempts: scheduled |<p>Number of attempts to schedule pods with result "scheduled" per second.</p> |DEPENDENT |kubernetes.scheduler.scheduler_schedule_attempts.scheduled.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `scheduler_schedule_attempts_total{result = "scheduled"}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Schedule attempts: unschedulable |<p>Number of attempts to schedule pods with result "unschedulable" per second.</p> |DEPENDENT |kubernetes.scheduler.scheduler_schedule_attempts.unschedulable.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `scheduler_schedule_attempts_total{result = "unschedulable"}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Schedule attempts: error |<p>Number of attempts to schedule pods with result "error" per second.</p> |DEPENDENT |kubernetes.scheduler.scheduler_schedule_attempts.error.rate<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `scheduler_schedule_attempts_total{result = "error"}`: `function`: `sum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Scheduling algorithm duration bucket, {#LE} |<p>Scheduling algorithm latency in seconds.</p> |DEPENDENT |kubernetes.scheduler.scheduling_algorithm_duration[{#LE}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `scheduler_scheduling_algorithm_duration_seconds_bucket{le = "{#LE}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Scheduling algorithm duration, p90 |<p>90 percentile of scheduling algorithm latency in seconds.</p> |CALCULATED |kubernetes.scheduler.scheduling_algorithm_duration_p90[{#SINGLETON}]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.scheduling_algorithm_duration[*],5m,90)` |
|Kubernetes Scheduler |Kubernetes Scheduler: Scheduling algorithm duration, p95 |<p>95 percentile of scheduling algorithm latency in seconds.</p> |CALCULATED |kubernetes.scheduler.scheduling_algorithm_duration_p95[{#SINGLETON}]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.scheduling_algorithm_duration[*],5m,95)` |
|Kubernetes Scheduler |Kubernetes Scheduler: Scheduling algorithm duration, p99 |<p>99 percentile of scheduling algorithm latency in seconds.</p> |CALCULATED |kubernetes.scheduler.scheduling_algorithm_duration_p99[{#SINGLETON}]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.scheduling_algorithm_duration[*],5m,99)` |
|Kubernetes Scheduler |Kubernetes Scheduler: Scheduling algorithm duration, p50 |<p>50 percentile of scheduling algorithm latency in seconds.</p> |CALCULATED |kubernetes.scheduler.scheduling_algorithm_duration_p50[{#SINGLETON}]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.scheduling_algorithm_duration[*],5m,50)` |
|Kubernetes Scheduler |Kubernetes Scheduler: Binding duration bucket, {#LE} |<p>Binding latency in seconds.</p> |DEPENDENT |kubernetes.scheduler.binding_duration[{#LE}]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `scheduler_binding_duration_seconds_bucket{le = "{#LE}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: Binding duration, p90 |<p>90 percentile of binding latency in seconds.</p> |CALCULATED |kubernetes.scheduler.binding_duration_p90[{#SINGLETON}]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.binding_duration[*],5m,90)` |
|Kubernetes Scheduler |Kubernetes Scheduler: Binding duration, p95 |<p>99 percentile of binding latency in seconds.</p> |CALCULATED |kubernetes.scheduler.binding_duration_p95[{#SINGLETON}]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.binding_duration[*],5m,95)` |
|Kubernetes Scheduler |Kubernetes Scheduler: Binding duration, p99 |<p>95 percentile of binding latency in seconds.</p> |CALCULATED |kubernetes.scheduler.binding_duration_p99[{#SINGLETON}]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.binding_duration[*],5m,99)` |
|Kubernetes Scheduler |Kubernetes Scheduler: Binding duration, p50 |<p>50 percentile of binding latency in seconds.</p> |CALCULATED |kubernetes.scheduler.binding_duration_p50[{#SINGLETON}]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.binding_duration[*],5m,50)` |
|Kubernetes Scheduler |Kubernetes Scheduler: ["{#RESULT}"]: e2e scheduling seconds bucket, {#LE} |<p>E2e scheduling latency in seconds (scheduling algorithm + binding)</p> |DEPENDENT |kubernetes.scheduler.e2e_scheduling_bucket[{#LE},"{#RESULT}"]<p>**Preprocessing**:</p><p>- PROMETHEUS_PATTERN: `scheduler_e2e_scheduling_duration_seconds_bucket{result = "{#RESULT}",le = "{#LE}"}`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes Scheduler |Kubernetes Scheduler: ["{#RESULT}"]: e2e scheduling, p50 |<p>50 percentile of e2e scheduling latency.</p> |CALCULATED |kubernetes.scheduler.e2e_scheduling_p50["{#RESULT}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.e2e_scheduling_bucket[*,"{#RESULT}"],5m,50)` |
|Kubernetes Scheduler |Kubernetes Scheduler: ["{#RESULT}"]: e2e scheduling, p90 |<p>90 percentile of e2e scheduling latency.</p> |CALCULATED |kubernetes.scheduler.e2e_scheduling_p90["{#RESULT}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.e2e_scheduling_bucket[*,"{#RESULT}"],5m,90)` |
|Kubernetes Scheduler |Kubernetes Scheduler: ["{#RESULT}"]: e2e scheduling, p95 |<p>95 percentile of e2e scheduling latency.</p> |CALCULATED |kubernetes.scheduler.e2e_scheduling_p95["{#RESULT}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.e2e_scheduling_bucket[*,"{#RESULT}"],5m,95)` |
|Kubernetes Scheduler |Kubernetes Scheduler: ["{#RESULT}"]: e2e scheduling, p99 |<p>95 percentile of e2e scheduling latency.</p> |CALCULATED |kubernetes.scheduler.e2e_scheduling_p99["{#RESULT}"]<p>**Expression**:</p>`bucket_percentile(//kubernetes.scheduler.e2e_scheduling_bucket[*,"{#RESULT}"],5m,99)` |
|Zabbix raw items |Kubernetes Scheduler: Get Scheduler metrics |<p>Get raw metrics from Scheduler instance /metrics endpoint.</p> |HTTP_AGENT |kubernetes.scheduler.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Kubernetes Scheduler: Too many REST Client errors |<p>"Kubernetes Scheduler REST Client requests is experiencing high error rate (with 5xx HTTP code).</p> |`min(/Kubernetes Scheduler by HTTP/kubernetes.scheduler.client_http_requests_500.rate,5m)>{$KUBE.SCHEDULER.HTTP.CLIENT.ERROR}` |WARNING | |
|Kubernetes Scheduler: Too many unschedulable pods |<p>"Number of attempts to schedule pods with 'unschedulable' result is too high. 'unschedulable' means a pod could not be scheduled."</p> |`min(/Kubernetes Scheduler by HTTP/kubernetes.scheduler.scheduler_schedule_attempts.unschedulable.rate,5m)>{$KUBE.SCHEDULER.UNSCHEDULABLE}` |WARNING | |
|Kubernetes Scheduler: Too many schedule attempts with errors |<p>"Number of attempts to schedule pods with 'error' result is too high. 'error' means an internal scheduler problem."</p> |`min(/Kubernetes Scheduler by HTTP/kubernetes.scheduler.scheduler_schedule_attempts.error.rate,5m)>{$KUBE.SCHEDULER.ERROR}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

