
# HashiCorp Consul Cluster by HTTP

## Overview

The template to monitor HashiCorp Consul by Zabbix that works without any external scripts.  
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `HashiCorp Consul Cluster by HTTP` — collects metrics by HTTP agent from API endpoints.  
More information about metrics you can find in [official documentation](https://www.consul.io/docs/agent/telemetry).

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- HashiCorp Consul 1.10.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Template need to use Authorization via API token.

Don't forget to change macros {$CONSUL.CLUSTER.URL}, {$CONSUL.TOKEN}.  
Also, see the Macros section for a list of macros used to set trigger values.  

This template support [Consul namespaces](https://www.consul.io/docs/enterprise/namespaces). You can set macro {$CONSUL.NAMESPACE}, if you are interested in only one service namespace. Do not specify this macro to get all of services.  
In case of Open Source version leave this macro empty.

*NOTE.* Some metrics may not be collected depending on your HashiCorp Consul instance version and configuration.  
*NOTE.* You maybe are interested in Envoy Proxy by HTTP [template](../../envoy_proxy_http).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CONSUL.CLUSTER.URL}|<p>Consul cluster URL.</p>|`http://localhost:8500`|
|{$CONSUL.TOKEN}|<p>Consul auth token.</p>|`<PUT YOUR AUTH TOKEN>`|
|{$CONSUL.NAMESPACE}|<p>Consul service namespace. Enterprise only, in case of Open Source version leave this macro empty. Do not specify this macro to get all of services.</p>||
|{$CONSUL.API.SCHEME}|<p>Consul API scheme. Using in node LLD.</p>|`http`|
|{$CONSUL.API.PORT}|<p>Consul API port. Using in node LLD.</p>|`8500`|
|{$CONSUL.LLD.FILTER.NODE_NAME.MATCHES}|<p>Filter of discoverable discovered nodes.</p>|`.*`|
|{$CONSUL.LLD.FILTER.NODE_NAME.NOT_MATCHES}|<p>Filter to exclude discovered nodes.</p>|`CHANGE IF NEEDED`|
|{$CONSUL.LLD.FILTER.SERVICE_NAME.MATCHES}|<p>Filter of discoverable discovered services.</p>|`.*`|
|{$CONSUL.LLD.FILTER.SERVICE_NAME.NOT_MATCHES}|<p>Filter to exclude discovered services.</p>|`CHANGE IF NEEDED`|
|{$CONSUL.SERVICE_NODES.CRITICAL.MAX.AVG}|<p>Maximum number of service nodes in status 'critical' for trigger expression. Can be used with context.</p>|`0`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Consul cluster: Cluster leader|<p>Current leader address.</p>|HTTP agent|consul.get_leader<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Trim: `"`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Consul cluster: Nodes: peers|<p>The number of Raft peers for the datacenter in which the agent is running.</p>|HTTP agent|consul.get_peers<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.length()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Consul cluster: Get nodes|<p>Catalog of nodes registered in a given datacenter.</p>|HTTP agent|consul.get_nodes<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Consul cluster: Get nodes Serf health status|<p>Get Serf Health Status for all agents in cluster.</p>|HTTP agent|consul.get_cluster_serf<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Consul: Nodes: total|<p>Number of nodes on current dc.</p>|Dependent item|consul.nodes_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.length()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Consul: Nodes: passing|<p>Number of agents on current dc with serf health status 'passing'.</p>|Dependent item|consul.nodes_passing<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Status == "passing")].length()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Consul: Nodes: critical|<p>Number of agents on current dc with serf health status 'critical'.</p>|Dependent item|consul.nodes_critical<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Status == "critical")].length()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Consul: Nodes: warning|<p>Number of agents on current dc with serf health status 'warning'.</p>|Dependent item|consul.nodes_warning<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Status == "warning")].length()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Consul cluster: Get services|<p>Catalog of services registered in a given datacenter.</p>|HTTP agent|consul.get_catalog_services<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Consul: Services: total|<p>Number of services on current dc.</p>|Dependent item|consul.services_total<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Consul cluster: Leader has been changed|<p>Consul cluster version has changed. Acknowledge to close the problem manually.</p>|`last(/HashiCorp Consul Cluster by HTTP/consul.get_leader,#1)<>last(/HashiCorp Consul Cluster by HTTP/consul.get_leader,#2) and length(last(/HashiCorp Consul Cluster by HTTP/consul.get_leader))>0`|Info|**Manual close**: Yes|
|Consul: One or more nodes in cluster in 'critical' state|<p>One or more agents on current dc with serf health status 'critical'.</p>|`last(/HashiCorp Consul Cluster by HTTP/consul.nodes_critical)>0`|Average||
|Consul: One or more nodes in cluster in 'warning' state|<p>One or more agents on current dc with serf health status 'warning'.</p>|`last(/HashiCorp Consul Cluster by HTTP/consul.nodes_warning)>0`|Warning||

### LLD rule Consul cluster nodes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Consul cluster nodes discovery||Dependent item|consul.lld_nodes<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Consul cluster nodes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Consul: Node ["{#NODE_NAME}"]: Serf Health|<p>Node Serf Health Status.</p>|Dependent item|consul.serf.health["{#NODE_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### LLD rule Consul cluster services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Consul cluster services discovery||Dependent item|consul.lld_services<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Consul cluster services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Consul: Service ["{#SERVICE_NAME}"]: Nodes passing|<p>The number of nodes with service status `passing` from those registered.</p>|Dependent item|consul.service.nodes_passing["{#SERVICE_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Consul: Service ["{#SERVICE_NAME}"]: Nodes warning|<p>The number of nodes with service status `warning` from those registered.</p>|Dependent item|consul.service.nodes_warning["{#SERVICE_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Consul: Service ["{#SERVICE_NAME}"]: Nodes critical|<p>The number of nodes with service status `critical` from those registered.</p>|Dependent item|consul.service.nodes_critical["{#SERVICE_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Consul cluster: ["{#SERVICE_NAME}"]: Get raw service state|<p>Retrieve service instances providing the service indicated on the path.</p>|HTTP agent|consul.get_service_stats["{#SERVICE_NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Consul cluster services discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Consul: Service ["{#SERVICE_NAME}"]: Too many nodes with service status 'critical'|<p>One or more nodes with service status 'critical'.</p>|`last(/HashiCorp Consul Cluster by HTTP/consul.service.nodes_critical["{#SERVICE_NAME}"])>{$CONSUL.CLUSTER.SERVICE_NODES.CRITICAL.MAX.AVG:"{#SERVICE_NAME}"}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

