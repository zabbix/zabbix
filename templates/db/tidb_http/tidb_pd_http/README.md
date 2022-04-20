
# TiDB PD by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor PD server of TiDB cluster by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `TiDB PD by HTTP` — collects metrics by HTTP agent from PD /metrics endpoint and from monitoring API.
See https://docs.pingcap.com/tidb/stable/tidb-monitoring-api.


This template was tested on:

- TiDB cluster, version 4.0.10

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

This template works with PD server of TiDB cluster.
Internal service metrics are collected from PD /metrics endpoint and from monitoring API.
See https://docs.pingcap.com/tidb/stable/tidb-monitoring-api.
Don't forget to change the macros {$PD.URL}, {$PD.PORT}.
Also, see the Macros section for a list of macros used to set trigger values.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PD.MISS_REGION.MAX.WARN} |<p>Maximum number of missed regions</p> |`100` |
|{$PD.PORT} |<p>The port of PD server metrics web endpoint</p> |`2379` |
|{$PD.STORAGE_USAGE.MAX.WARN} |<p>Maximum percentage of cluster space used</p> |`80` |
|{$PD.URL} |<p>PD server URL</p> |`localhost` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Cluster metrics discovery |<p>Discovery cluster specific metrics.</p> |DEPENDENT |pd.cluster.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="pd_cluster_status")]`</p><p>- JAVASCRIPT: `return JSON.stringify(value != "[]" ? [{'{#SINGLETON}': ''}] : []);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|gRPC commands discovery |<p>Discovery grpc commands specific metrics.</p> |DEPENDENT |pd.grpc_command.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "grpc_server_handling_seconds_count")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Region discovery |<p>Discovery region specific metrics.</p> |DEPENDENT |pd.region.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_scheduler_region_heartbeat")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Region labels discovery |<p>Discovery region labels specific metrics.</p> |DEPENDENT |pd.region_labels.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_regions_label_level")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Region status discovery |<p>Discovery region status specific metrics.</p> |DEPENDENT |pd.region_status.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_regions_status")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Overrides:**</p><p>Too many missed regions trigger<br> - {#TYPE} MATCHES_REGEX `miss_peer_region_count`<br>  - TRIGGER_PROTOTYPE LIKE `Too many missed regions` - DISCOVER</p><p>Unresponsive peers trigger<br> - {#TYPE} MATCHES_REGEX `down_peer_region_count`<br>  - TRIGGER_PROTOTYPE LIKE `There are unresponsive peers` - DISCOVER</p> |
|Running scheduler discovery |<p>Discovery scheduler specific metrics.</p> |DEPENDENT |pd.scheduler.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_scheduler_status" && @.labels.type == "allow")]`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|PD instance |PD: Status |<p>Status of PD instance.</p> |DEPENDENT |pd.status<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|PD instance |PD: GRPC Commands total, rate |<p>The rate at which gRPC commands are completed.</p> |DEPENDENT |pd.grpc_command.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "grpc_server_handling_seconds_count")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|PD instance |PD: Version |<p>Version of the PD instance.</p> |DEPENDENT |pd.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|PD instance |PD: Uptime |<p>The runtime of each PD instance.</p> |DEPENDENT |pd.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.start_timestamp`</p><p>- JAVASCRIPT: `//use boottime to calculate uptime return (Math.floor(Date.now()/1000)-Number(value)); `</p> |
|PD instance |PD: GRPC Commands: {#GRPC_METHOD}, rate |<p>The rate per command type at which gRPC commands are completed.</p> |DEPENDENT |pd.grpc_command.rate[{#GRPC_METHOD}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "grpc_server_handling_seconds_count" && @.labels.grpc_method == "{#GRPC_METHOD}")].value.first()`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB cluster |TiDB cluster: Offline stores |<p>-</p> |DEPENDENT |pd.cluster_status.store_offline[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "store_offline_count")].value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TiDB cluster |TiDB cluster: Tombstone stores |<p>The count of tombstone stores.</p> |DEPENDENT |pd.cluster_status.store_tombstone[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "store_tombstone_count")].value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TiDB cluster |TiDB cluster: Down stores |<p>The count of down stores.</p> |DEPENDENT |pd.cluster_status.store_down[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "store_down_count")].value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TiDB cluster |TiDB cluster: Lowspace stores |<p>The count of low space stores.</p> |DEPENDENT |pd.cluster_status.store_low_space[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "store_low_space_count")].value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TiDB cluster |TiDB cluster: Unhealth stores |<p>The count of unhealthy stores.</p> |DEPENDENT |pd.cluster_status.store_unhealth[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "store_unhealth_count")].value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TiDB cluster |TiDB cluster: Disconnect stores |<p>The count of disconnected stores.</p> |DEPENDENT |pd.cluster_status.store_disconnected[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "store_disconnected_count")].value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TiDB cluster |TiDB cluster: Normal stores |<p>The count of healthy storage instances.</p> |DEPENDENT |pd.cluster_status.store_up[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "store_up_count")].value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TiDB cluster |TiDB cluster: Storage capacity |<p>The total storage capacity for this TiDB cluster.</p> |DEPENDENT |pd.cluster_status.storage_capacity[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "storage_capacity")].value.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TiDB cluster |TiDB cluster: Storage size |<p>The storage size that is currently used by the TiDB cluster.</p> |DEPENDENT |pd.cluster_status.storage_size[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "storage_size")].value.first()`</p> |
|TiDB cluster |TiDB cluster: Number of regions |<p>The total count of cluster Regions.</p> |DEPENDENT |pd.cluster_status.leader_count[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "leader_count")].value.first()`</p> |
|TiDB cluster |TiDB cluster: Current peer count |<p>The current count of all cluster peers.</p> |DEPENDENT |pd.cluster_status.region_count[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_cluster_status" && @.labels.type == "region_count")].value.first()`</p> |
|TiDB cluster |TiDB cluster: Regions label: {#TYPE} |<p>The number of Regions in different label levels.</p> |DEPENDENT |pd.region_labels[{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_regions_label_level" && @.labels.type == "{#TYPE}")].value.first()`</p> |
|TiDB cluster |TiDB cluster: Regions status: {#TYPE} |<p>The health status of Regions indicated via the count of unusual Regions including pending peers, down peers, extra peers, offline peers, missing peers, learner peers and incorrect namespaces.</p> |DEPENDENT |pd.region_status[{#TYPE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_regions_status" && @.labels.type == "{#TYPE}")].value.first()`</p> |
|TiDB cluster |TiDB cluster: Scheduler status: {#KIND} |<p>The current running schedulers.</p> |DEPENDENT |pd.scheduler[{#KIND}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_regions_status" && @.labels.type == "allow" && @.labels.kind == "{#KIND}")].value.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|TiDB cluster |PD: Region heartbeat: active, rate |<p>The count of heartbeats with the ok status per second.</p> |DEPENDENT |pd.region_heartbeat.ok.rate[{#STORE_ADDRESS}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_scheduler_region_heartbeat" && @.labels.status == "ok" && @.labels.type == "report" && @.labels.address == "{#STORE_ADDRESS}")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB cluster |PD: Region heartbeat: error, rate |<p>The count of heartbeats with the error status per second.</p> |DEPENDENT |pd.region_heartbeat.error.rate[{#STORE_ADDRESS}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_scheduler_region_heartbeat" && @.labels.status == "err" && @.labels.type == "report" && @.labels.address == "{#STORE_ADDRESS}")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB cluster |PD: Region heartbeat: total, rate |<p>The count of heartbeats reported to PD per instance per second.</p> |DEPENDENT |pd.region_heartbeat.rate[{#STORE_ADDRESS}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_scheduler_region_heartbeat" && @.labels.type == "report" && @.labels.address == "{#STORE_ADDRESS}")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|TiDB cluster |PD: Region schedule push: total, rate |<p>-</p> |DEPENDENT |pd.region_heartbeat.push.err.rate[{#STORE_ADDRESS}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name == "pd_scheduler_region_heartbeat" && @.labels.type == "push" && @.labels.address == "{#STORE_ADDRESS}")].value.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- CHANGE_PER_SECOND</p> |
|Zabbix raw items |PD: Get instance metrics |<p>Get TiDB PD instance metrics.</p> |HTTP_AGENT |pd.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- PROMETHEUS_TO_JSON</p> |
|Zabbix raw items |PD: Get instance status |<p>Get TiDB PD instance status info.</p> |HTTP_AGENT |pd.get_status<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"status": "0"}`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|PD: Instance is not responding |<p>-</p> |`last(/TiDB PD by HTTP/pd.status)=0` |AVERAGE | |
|PD: Version has changed |<p>PD version has changed. Ack to close.</p> |`last(/TiDB PD by HTTP/pd.version,#1)<>last(/TiDB PD by HTTP/pd.version,#2) and length(last(/TiDB PD by HTTP/pd.version))>0` |INFO |<p>Manual close: YES</p> |
|PD: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/TiDB PD by HTTP/pd.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|TiDB cluster: There are offline TiKV nodes |<p>PD has not received a TiKV heartbeat for a long time.</p> |`last(/TiDB PD by HTTP/pd.cluster_status.store_down[{#SINGLETON}])>0` |AVERAGE | |
|TiDB cluster: There are low space TiKV nodes |<p>Indicates that there is no sufficient space on the TiKV node.</p> |`last(/TiDB PD by HTTP/pd.cluster_status.store_low_space[{#SINGLETON}])>0` |AVERAGE | |
|TiDB cluster: There are disconnected TiKV nodes |<p>PD does not receive a TiKV heartbeat within 20 seconds. Normally a TiKV heartbeat comes in every 10 seconds.</p> |`last(/TiDB PD by HTTP/pd.cluster_status.store_disconnected[{#SINGLETON}])>0` |WARNING | |
|TiDB cluster: Current storage usage is too high |<p>Over {$PD.STORAGE_USAGE.MAX.WARN}% of the cluster space is occupied.</p> |`min(/TiDB PD by HTTP/pd.cluster_status.storage_size[{#SINGLETON}],5m)/last(/TiDB PD by HTTP/pd.cluster_status.storage_capacity[{#SINGLETON}])*100>{$PD.STORAGE_USAGE.MAX.WARN}` |WARNING | |
|TiDB cluster: Too many missed regions |<p>The number of Region replicas is smaller than the value of max-replicas. When a TiKV machine is down and its downtime exceeds max-down-time, it usually leads to missing replicas for some Regions during a period of time. When a TiKV node is made offline, it might result in a small number of Regions with missing replicas.</p> |`min(/TiDB PD by HTTP/pd.region_status[{#TYPE}],5m)>{$PD.MISS_REGION.MAX.WARN}` |WARNING | |
|TiDB cluster: There are unresponsive peers |<p>The number of Regions with an unresponsive peer reported by the Raft leader.</p> |`min(/TiDB PD by HTTP/pd.region_status[{#TYPE}],5m)>0` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

