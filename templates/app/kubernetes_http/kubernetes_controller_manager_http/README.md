
# Kubernetes Controller manager by HTTP

## Overview

The template to monitor Kubernetes Controller manager by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Kubernetes Controller manager by HTTP` - collects metrics by HTTP agent from Controller manager /metrics endpoint.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Kubernetes Controller manager 1.19.10

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Internal service metrics are collected from /metrics endpoint.
Template needs to use Authorization via API token.

Don't forget change macros {$KUBE.CONTROLLER.SERVER.URL}, {$KUBE.API.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.

**Note:** You might need to set the `--binding-address` option for Controller Manager to the address where Zabbix proxy can reach it.
For example, for clusters created with `kubeadm` it can be set in the following manifest file (changes will be applied immediately):

- /etc/kubernetes/manifests/kube-controller-manager.yaml

**Note:** Some metrics may not be collected depending on your Kubernetes Controller manager instance version and configuration.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.TOKEN}|<p>API Authorization Token</p>||
|{$KUBE.CONTROLLER.SERVER.URL}|<p>Kubernetes Controller manager metrics endpoint URL.</p>|`https://localhost:10257/metrics`|
|{$KUBE.CONTROLLER.HTTP.CLIENT.ERROR}|<p>Maximum number of HTTP client requests failures used for trigger.</p>|`2`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubernetes Controller: Get Controller metrics|<p>Get raw metrics from Controller instance /metrics endpoint.</p>|HTTP agent|kubernetes.controller.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Leader election status|<p>Gauge of if the reporting system is master of the relevant lease, 0 indicates backup, 1 indicates master.</p>|Dependent item|kubernetes.controller.leader_election_master_status<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(leader_election_master_status)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Virtual memory, bytes|<p>Virtual memory size in bytes.</p>|Dependent item|kubernetes.controller.process_virtual_memory_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_virtual_memory_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Resident memory, bytes|<p>Resident memory size in bytes.</p>|Dependent item|kubernetes.controller.process_resident_memory_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_resident_memory_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU|<p>Total user and system CPU usage ratio.</p>|Dependent item|kubernetes.controller.cpu.util<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_cpu_seconds_total)`</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Goroutines|<p>Number of goroutines that currently exist.</p>|Dependent item|kubernetes.controller.go_goroutines<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(go_goroutines)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Go threads|<p>Number of OS threads created.</p>|Dependent item|kubernetes.controller.go_threads<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(go_threads)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Fds open|<p>Number of open file descriptors.</p>|Dependent item|kubernetes.controller.open_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_open_fds)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Fds max|<p>Maximum allowed open file descriptors.</p>|Dependent item|kubernetes.controller.max_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_max_fds)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|REST Client requests: 2xx, rate|<p>Number of HTTP requests with 2xx status code per second.</p>|Dependent item|kubernetes.controller.client_http_requests_200.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "2.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|REST Client requests: 3xx, rate|<p>Number of HTTP requests with 3xx status code per second.</p>|Dependent item|kubernetes.controller.client_http_requests_300.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "3.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|REST Client requests: 4xx, rate|<p>Number of HTTP requests with 4xx status code per second.</p>|Dependent item|kubernetes.controller.client_http_requests_400.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "4.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|REST Client requests: 5xx, rate|<p>Number of HTTP requests with 5xx status code per second.</p>|Dependent item|kubernetes.controller.client_http_requests_500.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(rest_client_requests_total{code =~ "5.."})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Controller manager: Too many HTTP client errors|<p>"Kubernetes Controller manager is experiencing high error rate (with 5xx HTTP code).</p>|`min(/Kubernetes Controller manager by HTTP/kubernetes.controller.client_http_requests_500.rate,5m)>{$KUBE.CONTROLLER.HTTP.CLIENT.ERROR}`|Warning||

### LLD rule Workqueue metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workqueue metrics discovery||Dependent item|kubernetes.controller.workqueue.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `{__name__=~ "workqueue_*", name =~ ".*"}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Workqueue metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|["{#NAME}"]: Workqueue adds total, rate|<p>Total number of adds handled by workqueue per second.</p>|Dependent item|kubernetes.controller.workqueue_adds_total["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(workqueue_adds_total{name = "{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|["{#NAME}"]: Workqueue depth|<p>Current depth of workqueue.</p>|Dependent item|kubernetes.controller.workqueue_depth["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(workqueue_depth{name = "{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|["{#NAME}"]: Workqueue unfinished work, sec|<p>How many seconds of work has done that is in progress and hasn't been observed by work_duration. Large values indicate stuck threads. One can deduce the number of stuck threads by observing the rate at which this increases.</p>|Dependent item|kubernetes.controller.workqueue_unfinished_work_seconds["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(workqueue_unfinished_work_seconds{name = "{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|["{#NAME}"]: Workqueue retries, rate|<p>Total number of retries handled by workqueue per second.</p>|Dependent item|kubernetes.controller.workqueue_retries_total["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(workqueue_retries_total{name = "{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|["{#NAME}"]: Workqueue longest running processor, sec|<p>How many seconds has the longest running processor for workqueue been running.</p>|Dependent item|kubernetes.controller.workqueue_longest_running_processor_seconds["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|["{#NAME}"]: Workqueue work duration, p90|<p>90 percentile of how long in seconds processing an item from workqueue takes, by queue.</p>|Calculated|kubernetes.controller.workqueue_work_duration_seconds_p90["{#NAME}"]|
|["{#NAME}"]: Workqueue work duration, p95|<p>95 percentile of how long in seconds processing an item from workqueue takes, by queue.</p>|Calculated|kubernetes.controller.workqueue_work_duration_seconds_p95["{#NAME}"]|
|["{#NAME}"]: Workqueue work duration, p99|<p>99 percentile of how long in seconds processing an item from workqueue takes, by queue.</p>|Calculated|kubernetes.controller.workqueue_work_duration_seconds_p99["{#NAME}"]|
|["{#NAME}"]: Workqueue work duration, 50p|<p>50 percentiles of how long in seconds processing an item from workqueue takes, by queue.</p>|Calculated|kubernetes.controller.workqueue_work_duration_seconds_p50["{#NAME}"]|
|["{#NAME}"]: Workqueue queue duration, p90|<p>90 percentile of how long in seconds an item stays in workqueue before being requested, by queue.</p>|Calculated|kubernetes.controller.workqueue_queue_duration_seconds_p90["{#NAME}"]|
|["{#NAME}"]: Workqueue queue duration, p95|<p>95 percentile of how long in seconds an item stays in workqueue before being requested, by queue.</p>|Calculated|kubernetes.controller.workqueue_queue_duration_seconds_p95["{#NAME}"]|
|["{#NAME}"]: Workqueue queue duration, p99|<p>99 percentile of how long in seconds an item stays in workqueue before being requested, by queue.</p>|Calculated|kubernetes.controller.workqueue_queue_duration_seconds_p99["{#NAME}"]|
|["{#NAME}"]: Workqueue queue duration, 50p|<p>50 percentile of how long in seconds an item stays in workqueue before being requested. If there are no requests for 5 minute, item value will be discarded.</p>|Calculated|kubernetes.controller.workqueue_queue_duration_seconds_p50["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|["{#NAME}"]: Workqueue duration seconds bucket, {#LE}|<p>How long in seconds processing an item from workqueue takes.</p>|Dependent item|kubernetes.controller.duration_seconds_bucket[{#LE},"{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|["{#NAME}"]: Queue duration seconds bucket, {#LE}|<p>How long in seconds an item stays in workqueue before being requested.</p>|Dependent item|kubernetes.controller.queue_duration_seconds_bucket[{#LE},"{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

