
# Kubernetes Scheduler by HTTP

## Overview

The template to monitor Kubernetes Scheduler by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Kubernetes Scheduler by HTTP` - collects metrics by HTTP agent from Scheduler /metrics endpoint.

## Requirements

Zabbix version: 6.4 and higher.

## Tested versions

This template has been tested on:
- Kubernetes Scheduler 1.19.10

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box) section.

## Setup

Internal service metrics are collected from /metrics endpoint.
Template needs to use Authorization via API token.

Don't forget change macros {$KUBE.SCHEDULER.SERVER.URL}, {$KUBE.API.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.

*NOTE.* You might need to set the `--binding-address` option for Scheduler to the address where Zabbix proxy can reach it.
For example, for clusters created with `kubeadm` it can be set in the following manifest file (changes will be applied immediately):

- /etc/kubernetes/manifests/kube-scheduler.yaml

*NOTE.* Some metrics may not be collected depending on your Kubernetes Scheduler instance version and configuration.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.SCHEDULER.SERVER.URL}|<p>Kubernetes Scheduler metrics endpoint URL.</p>|`https://localhost:10259/metrics`|
|{$KUBE.API.TOKEN}|<p>API Authorization Token.</p>||
|{$KUBE.SCHEDULER.HTTP.CLIENT.ERROR}|<p>Maximum number of HTTP client requests failures used for trigger.</p>|`2`|
|{$KUBE.SCHEDULER.UNSCHEDULABLE}|<p>Maximum number of scheduling failures with 'unschedulable' used for trigger.</p>|`2`|
|{$KUBE.SCHEDULER.ERROR}|<p>Maximum number of scheduling failures with 'error' used for trigger.</p>|`2`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes Scheduler: Get Scheduler metrics|<p>Get raw metrics from Scheduler instance /metrics endpoint.</p>|HTTP agent|kubernetes.scheduler.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes Scheduler: Virtual memory, bytes|<p>Virtual memory size in bytes.</p>|Dependent item|kubernetes.scheduler.process_virtual_memory_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_virtual_memory_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes Scheduler: Resident memory, bytes|<p>Resident memory size in bytes.</p>|Dependent item|kubernetes.scheduler.process_resident_memory_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_resident_memory_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes Scheduler: CPU|<p>Total user and system CPU usage ratio.</p>|Dependent item|kubernetes.scheduler.cpu.util<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_cpu_seconds_total)`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Kubernetes Scheduler: Goroutines|<p>Number of goroutines that currently exist.</p>|Dependent item|kubernetes.scheduler.go_goroutines<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(go_goroutines)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes Scheduler: Go threads|<p>Number of OS threads created.</p>|Dependent item|kubernetes.scheduler.go_threads<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(go_threads)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes Scheduler: Fds open|<p>Number of open file descriptors.</p>|Dependent item|kubernetes.scheduler.open_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_open_fds)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes Scheduler: Fds max|<p>Maximum allowed open file descriptors.</p>|Dependent item|kubernetes.scheduler.max_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_max_fds)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes Scheduler: REST Client requests: 2xx, rate|<p>Number of HTTP requests with 2xx status code per second.</p>|Dependent item|kubernetes.scheduler.client_http_requests_200.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "2.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes Scheduler: REST Client requests: 3xx, rate|<p>Number of HTTP requests with 3xx status code per second.</p>|Dependent item|kubernetes.scheduler.client_http_requests_300.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "3.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes Scheduler: REST Client requests: 4xx, rate|<p>Number of HTTP requests with 4xx status code per second.</p>|Dependent item|kubernetes.scheduler.client_http_requests_400.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "4.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes Scheduler: REST Client requests: 5xx, rate|<p>Number of HTTP requests with 5xx status code per second.</p>|Dependent item|kubernetes.scheduler.client_http_requests_500.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "5.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes Scheduler: Schedule attempts: scheduled|<p>Number of attempts to schedule pods with result "scheduled" per second.</p>|Dependent item|kubernetes.scheduler.scheduler_schedule_attempts.scheduled.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(scheduler_schedule_attempts_total{result = "scheduled"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes Scheduler: Schedule attempts: unschedulable|<p>Number of attempts to schedule pods with result "unschedulable" per second.</p>|Dependent item|kubernetes.scheduler.scheduler_schedule_attempts.unschedulable.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Kubernetes Scheduler: Schedule attempts: error|<p>Number of attempts to schedule pods with result "error" per second.</p>|Dependent item|kubernetes.scheduler.scheduler_schedule_attempts.error.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(scheduler_schedule_attempts_total{result = "error"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Scheduler: Too many REST Client errors|<p>"Kubernetes Scheduler REST Client requests is experiencing high error rate (with 5xx HTTP code).</p>|`min(/Kubernetes Scheduler by HTTP/kubernetes.scheduler.client_http_requests_500.rate,5m)>{$KUBE.SCHEDULER.HTTP.CLIENT.ERROR}`|Warning||
|Kubernetes Scheduler: Too many unschedulable pods|<p>Number of attempts to schedule pods with 'unschedulable' result is too high. 'unschedulable' means a pod could not be scheduled.</p>|`min(/Kubernetes Scheduler by HTTP/kubernetes.scheduler.scheduler_schedule_attempts.unschedulable.rate,5m)>{$KUBE.SCHEDULER.UNSCHEDULABLE}`|Warning||
|Kubernetes Scheduler: Too many schedule attempts with errors|<p>Number of attempts to schedule pods with 'error' result is too high. 'error' means an internal scheduler problem.</p>|`min(/Kubernetes Scheduler by HTTP/kubernetes.scheduler.scheduler_schedule_attempts.error.rate,5m)>{$KUBE.SCHEDULER.ERROR}`|Warning||

### LLD rule Scheduling algorithm histogram

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Scheduling algorithm histogram|<p>Discovery raw data of scheduling algorithm latency.</p>|Dependent item|kubernetes.scheduler.scheduling_algorithm.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Scheduling algorithm histogram

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes Scheduler: Scheduling algorithm duration bucket, {#LE}|<p>Scheduling algorithm latency in seconds.</p>|Dependent item|kubernetes.scheduler.scheduling_algorithm_duration[{#LE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes Scheduler: Scheduling algorithm duration, p90|<p>90 percentile of scheduling algorithm latency in seconds.</p>|Calculated|kubernetes.scheduler.scheduling_algorithm_duration_p90[{#SINGLETON}]|
|Kubernetes Scheduler: Scheduling algorithm duration, p95|<p>95 percentile of scheduling algorithm latency in seconds.</p>|Calculated|kubernetes.scheduler.scheduling_algorithm_duration_p95[{#SINGLETON}]|
|Kubernetes Scheduler: Scheduling algorithm duration, p99|<p>99 percentile of scheduling algorithm latency in seconds.</p>|Calculated|kubernetes.scheduler.scheduling_algorithm_duration_p99[{#SINGLETON}]|
|Kubernetes Scheduler: Scheduling algorithm duration, p50|<p>50 percentile of scheduling algorithm latency in seconds.</p>|Calculated|kubernetes.scheduler.scheduling_algorithm_duration_p50[{#SINGLETON}]|

### LLD rule Binding histogram

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Binding histogram|<p>Discovery raw data of binding latency.</p>|Dependent item|kubernetes.scheduler.binding.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `{__name__=~ "scheduler_binding_duration_seconds_*"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Binding histogram

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes Scheduler: Binding duration bucket, {#LE}|<p>Binding latency in seconds.</p>|Dependent item|kubernetes.scheduler.binding_duration[{#LE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes Scheduler: Binding duration, p90|<p>90 percentile of binding latency in seconds.</p>|Calculated|kubernetes.scheduler.binding_duration_p90[{#SINGLETON}]|
|Kubernetes Scheduler: Binding duration, p95|<p>99 percentile of binding latency in seconds.</p>|Calculated|kubernetes.scheduler.binding_duration_p95[{#SINGLETON}]|
|Kubernetes Scheduler: Binding duration, p99|<p>95 percentile of binding latency in seconds.</p>|Calculated|kubernetes.scheduler.binding_duration_p99[{#SINGLETON}]|
|Kubernetes Scheduler: Binding duration, p50|<p>50 percentile of binding latency in seconds.</p>|Calculated|kubernetes.scheduler.binding_duration_p50[{#SINGLETON}]|

### LLD rule e2e scheduling histogram

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|e2e scheduling histogram|<p>Discovery raw data and percentile items of e2e scheduling latency.</p>|Dependent item|kubernetes.controller.e2e_scheduling.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for e2e scheduling histogram

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes Scheduler: ["{#RESULT}"]: e2e scheduling seconds bucket, {#LE}|<p>E2e scheduling latency in seconds (scheduling algorithm + binding)</p>|Dependent item|kubernetes.scheduler.e2e_scheduling_bucket[{#LE},"{#RESULT}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Kubernetes Scheduler: ["{#RESULT}"]: e2e scheduling, p50|<p>50 percentile of e2e scheduling latency.</p>|Calculated|kubernetes.scheduler.e2e_scheduling_p50["{#RESULT}"]|
|Kubernetes Scheduler: ["{#RESULT}"]: e2e scheduling, p90|<p>90 percentile of e2e scheduling latency.</p>|Calculated|kubernetes.scheduler.e2e_scheduling_p90["{#RESULT}"]|
|Kubernetes Scheduler: ["{#RESULT}"]: e2e scheduling, p95|<p>95 percentile of e2e scheduling latency.</p>|Calculated|kubernetes.scheduler.e2e_scheduling_p95["{#RESULT}"]|
|Kubernetes Scheduler: ["{#RESULT}"]: e2e scheduling, p99|<p>95 percentile of e2e scheduling latency.</p>|Calculated|kubernetes.scheduler.e2e_scheduling_p99["{#RESULT}"]|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

