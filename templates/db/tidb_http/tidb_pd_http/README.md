
# TiDB PD by HTTP

## Overview

The template to monitor PD server of TiDB cluster by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `TiDB PD by HTTP` — collects metrics by HTTP agent from PD /metrics endpoint and from monitoring API.
See https://docs.pingcap.com/tidb/stable/tidb-monitoring-api.


## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- TiDB cluster 4.0.10 

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

This template works with PD server of TiDB cluster.
Internal service metrics are collected from PD /metrics endpoint and from monitoring API.
See https://docs.pingcap.com/tidb/stable/tidb-monitoring-api.
Don't forget to change the macros {$PD.URL}, {$PD.PORT}.
Also, see the Macros section for a list of macros used to set trigger values.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PD.PORT}|<p>The port of PD server metrics web endpoint</p>|`2379`|
|{$PD.URL}|<p>PD server URL</p>|`localhost`|
|{$PD.MISS_REGION.MAX.WARN}|<p>Maximum number of missed regions</p>|`100`|
|{$PD.STORAGE_USAGE.MAX.WARN}|<p>Maximum percentage of cluster space used</p>|`80`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PD: Get instance metrics|<p>Get TiDB PD instance metrics.</p>|HTTP agent|pd.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li><li>Prometheus to JSON</li></ul>|
|PD: Get instance status|<p>Get TiDB PD instance status info.</p>|HTTP agent|pd.get_status<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Set value to: `{"status": "0"}`</p></li></ul>|
|PD: Status|<p>Status of PD instance.</p>|Dependent item|pd.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set value to: `1`</p></li><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|PD: GRPC Commands total, rate|<p>The rate at which gRPC commands are completed.</p>|Dependent item|pd.grpc_command.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|PD: Version|<p>Version of the PD instance.</p>|Dependent item|pd.version<p>**Preprocessing**</p><ul><li>JSON Path: `$.version`</li><li>Discard unchanged with heartbeat: `3h`</li></ul>|
|PD: Uptime|<p>The runtime of each PD instance.</p>|Dependent item|pd.uptime<p>**Preprocessing**</p><ul><li>JSON Path: `$.start_timestamp`</li><li>JavaScript: `The text is too long. Please see the template.`</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PD: Instance is not responding||`last(/TiDB PD by HTTP/pd.status)=0`|Average||
|PD: Version has changed|<p>PD version has changed. Acknowledge to close manually.</p>|`last(/TiDB PD by HTTP/pd.version,#1)<>last(/TiDB PD by HTTP/pd.version,#2) and length(last(/TiDB PD by HTTP/pd.version))>0`|Info|**Manual close**: Yes|
|PD: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/TiDB PD by HTTP/pd.uptime)<10m`|Info|**Manual close**: Yes|

### LLD rule Cluster metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster metrics discovery|<p>Discovery cluster specific metrics.</p>|Dependent item|pd.cluster.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="pd_cluster_status")]`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|

### Item prototypes for Cluster metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB cluster: Offline stores||Dependent item|pd.cluster_status.store_offline[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|TiDB cluster: Tombstone stores|<p>The count of tombstone stores.</p>|Dependent item|pd.cluster_status.store_tombstone[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|TiDB cluster: Down stores|<p>The count of down stores.</p>|Dependent item|pd.cluster_status.store_down[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|TiDB cluster: Lowspace stores|<p>The count of low space stores.</p>|Dependent item|pd.cluster_status.store_low_space[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|TiDB cluster: Unhealth stores|<p>The count of unhealthy stores.</p>|Dependent item|pd.cluster_status.store_unhealth[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|TiDB cluster: Disconnect stores|<p>The count of disconnected stores.</p>|Dependent item|pd.cluster_status.store_disconnected[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|TiDB cluster: Normal stores|<p>The count of healthy storage instances.</p>|Dependent item|pd.cluster_status.store_up[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|TiDB cluster: Storage capacity|<p>The total storage capacity for this TiDB cluster.</p>|Dependent item|pd.cluster_status.storage_capacity[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|TiDB cluster: Storage size|<p>The storage size that is currently used by the TiDB cluster.</p>|Dependent item|pd.cluster_status.storage_size[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li></ul>|
|TiDB cluster: Number of regions|<p>The total count of cluster Regions.</p>|Dependent item|pd.cluster_status.leader_count[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li></ul>|
|TiDB cluster: Current peer count|<p>The current count of all cluster peers.</p>|Dependent item|pd.cluster_status.region_count[{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li></ul>|

### Trigger prototypes for Cluster metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TiDB cluster: There are offline TiKV nodes|<p>PD has not received a TiKV heartbeat for a long time.</p>|`last(/TiDB PD by HTTP/pd.cluster_status.store_down[{#SINGLETON}])>0`|Average||
|TiDB cluster: There are low space TiKV nodes|<p>Indicates that there is no sufficient space on the TiKV node.</p>|`last(/TiDB PD by HTTP/pd.cluster_status.store_low_space[{#SINGLETON}])>0`|Average||
|TiDB cluster: There are disconnected TiKV nodes|<p>PD does not receive a TiKV heartbeat within 20 seconds. Normally a TiKV heartbeat comes in every 10 seconds.</p>|`last(/TiDB PD by HTTP/pd.cluster_status.store_disconnected[{#SINGLETON}])>0`|Warning||
|TiDB cluster: Current storage usage is too high|<p>Over {$PD.STORAGE_USAGE.MAX.WARN}% of the cluster space is occupied.</p>|`min(/TiDB PD by HTTP/pd.cluster_status.storage_size[{#SINGLETON}],5m)/last(/TiDB PD by HTTP/pd.cluster_status.storage_capacity[{#SINGLETON}])*100>{$PD.STORAGE_USAGE.MAX.WARN}`|Warning||

### LLD rule Region labels discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Region labels discovery|<p>Discovery region labels specific metrics.</p>|Dependent item|pd.region_labels.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "pd_regions_label_level")]`</p><p>⛔️Custom on fail: Discard value</p></li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|

### Item prototypes for Region labels discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB cluster: Regions label: {#TYPE}|<p>The number of Regions in different label levels.</p>|Dependent item|pd.region_labels[{#TYPE}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li></ul>|

### LLD rule Region status discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Region status discovery|<p>Discovery region status specific metrics.</p>|Dependent item|pd.region_status.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "pd_regions_status")]`</p><p>⛔️Custom on fail: Discard value</p></li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|

### Item prototypes for Region status discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB cluster: Regions status: {#TYPE}|<p>The health status of Regions indicated via the count of unusual Regions including pending peers, down peers, extra peers, offline peers, missing peers, learner peers and incorrect namespaces.</p>|Dependent item|pd.region_status[{#TYPE}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li></ul>|

### Trigger prototypes for Region status discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TiDB cluster: Too many missed regions|<p>The number of Region replicas is smaller than the value of max-replicas. When a TiKV machine is down and its downtime exceeds max-down-time, it usually leads to missing replicas for some Regions during a period of time. When a TiKV node is made offline, it might result in a small number of Regions with missing replicas.</p>|`min(/TiDB PD by HTTP/pd.region_status[{#TYPE}],5m)>{$PD.MISS_REGION.MAX.WARN}`|Warning||
|TiDB cluster: There are unresponsive peers|<p>The number of Regions with an unresponsive peer reported by the Raft leader.</p>|`min(/TiDB PD by HTTP/pd.region_status[{#TYPE}],5m)>0`|Warning||

### LLD rule Running scheduler discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Running scheduler discovery|<p>Discovery scheduler specific metrics.</p>|Dependent item|pd.scheduler.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|

### Item prototypes for Running scheduler discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TiDB cluster: Scheduler status: {#KIND}|<p>The current running schedulers.</p>|Dependent item|pd.scheduler[{#KIND}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### LLD rule gRPC commands discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|gRPC commands discovery|<p>Discovery grpc commands specific metrics.</p>|Dependent item|pd.grpc_command.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "grpc_server_handling_seconds_count")]`</p><p>⛔️Custom on fail: Discard value</p></li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|

### Item prototypes for gRPC commands discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PD: GRPC Commands: {#GRPC_METHOD}, rate|<p>The rate per command type at which gRPC commands are completed.</p>|Dependent item|pd.grpc_command.rate[{#GRPC_METHOD}]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li><li>Change per second</li></ul>|

### LLD rule Region discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Region discovery|<p>Discovery region specific metrics.</p>|Dependent item|pd.region.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "pd_scheduler_region_heartbeat")]`</p><p>⛔️Custom on fail: Discard value</p></li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `1h`</li></ul>|

### Item prototypes for Region discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PD: Region heartbeat: active, rate|<p>The count of heartbeats with the ok status per second.</p>|Dependent item|pd.region_heartbeat.ok.rate[{#STORE_ADDRESS}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|PD: Region heartbeat: error, rate|<p>The count of heartbeats with the error status per second.</p>|Dependent item|pd.region_heartbeat.error.rate[{#STORE_ADDRESS}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|PD: Region heartbeat: total, rate|<p>The count of heartbeats reported to PD per instance per second.</p>|Dependent item|pd.region_heartbeat.rate[{#STORE_ADDRESS}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|PD: Region schedule push: total, rate||Dependent item|pd.region_heartbeat.push.err.rate[{#STORE_ADDRESS}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|

## Feedback

Please report any issues with the template at `https://support.zabbix.com`.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
