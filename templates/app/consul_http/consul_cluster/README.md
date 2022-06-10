
# HashiCorp Consul Cluster by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor HashiCorp Consul by Zabbix that works without any external scripts.  
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `HashiCorp Consul Cluster by HTTP` — collects metrics by HTTP agent from API endpoints.  
More information about metrics you can find in [official documentation](https://www.consul.io/docs/agent/telemetry).



This template was tested on:

- HashiCorp Consul, version 1.10.0

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Template need to use Authorization via API token.

Don't forget to change macros {$CONSUL.CLUSTER.URL}, {$CONSUL.TOKEN}.  
Also, see the Macros section for a list of macros used to set trigger values.  
*NOTE.* Some metrics may not be collected depending on your HashiCorp Consul instance version and configuration.  


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CONSUL.API.PORT} |<p>Consul API port. Using in node LLD.</p> |`8500` |
|{$CONSUL.API.SCHEME} |<p>Consul API scheme. Using in node LLD.</p> |`http` |
|{$CONSUL.CLUSTER.URL} |<p>Consul cluster URL.</p> |`http://localhost:8500` |
|{$CONSUL.LLD.FILTER.NODE_NAME.MATCHES} |<p>Filter of discoverable discovered nodes.</p> |`.*` |
|{$CONSUL.LLD.FILTER.NODE_NAME.NOT_MATCHES} |<p>Filter to exclude discovered nodes.</p> |`CHANGE IF NEEDED` |
|{$CONSUL.LLD.FILTER.SERVICE_NAME.MATCHES} |<p>Filter of discoverable discovered services.</p> |`.*` |
|{$CONSUL.LLD.FILTER.SERVICE_NAME.NOT_MATCHES} |<p>Filter to exclude discovered services.</p> |`CHANGE IF NEEDED` |
|{$CONSUL.SERVICE_NODES.CRITICAL.MAX.AVG} |<p>Maximum number of service nodes in status 'critical' for trigger expression. Can be used with context.</p> |`0` |
|{$CONSUL.TOKEN} |<p>Consul auth token.</p> |`<PUT YOUR AUTH TOKEN>` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Consul cluster nodes discovery |<p>-</p> |DEPENDENT |consul.lld_nodes<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Filter**:</p> <p>- {#NODE_NAME} MATCHES_REGEX `{$CONSUL.LLD.FILTER.NODE_NAME.MATCHES}`</p><p>- {#NODE_NAME} NOT_MATCHES_REGEX `{$CONSUL.LLD.FILTER.NODE_NAME.NOT_MATCHES}`</p> |
|Consul cluster services discovery |<p>-</p> |DEPENDENT |consul.lld_services<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Filter**:</p> <p>- {#SERVICE_NAME} MATCHES_REGEX `{$CONSUL.LLD.FILTER.SERVICE_NAME.MATCHES}`</p><p>- {#SERVICE_NAME} NOT_MATCHES_REGEX `{$CONSUL.LLD.FILTER.SERVICE_NAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Consul |Consul: Nodes: total |<p>Number of nodes on current dc.</p> |DEPENDENT |consul.nodes_total<p>**Preprocessing**:</p><p>- JSONPATH: `$.length()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Consul |Consul: Nodes: passing |<p>Number of agents on current dc with serf health status 'passing'.</p> |DEPENDENT |consul.nodes_passing<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Status == "passing")].length()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Consul |Consul: Nodes: critical |<p>Number of agents on current dc with serf health status 'critical'.</p> |DEPENDENT |consul.nodes_critical<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Status == "critical")].length()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Consul |Consul: Nodes: warning |<p>Number of agents on current dc with serf health status 'warning'.</p> |DEPENDENT |consul.nodes_warning<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Status == "warning")].length()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Consul |Consul: Services: total |<p>Number of services on current dc.</p> |DEPENDENT |consul.services_total<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return Object.keys(JSON.parse(value)).length; `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Consul |Consul: Node ["{#NODE_NAME}"]: Serf Health |<p>Node Serf Health Status.</p> |DEPENDENT |consul.serf.health["{#NODE_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Node == "{#NODE_NAME}" && @.CheckID == "serfHealth")].Status.first()`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Consul |Consul: Service ["{#SERVICE_NAME}"]: Nodes passing |<p>-</p> |DEPENDENT |consul.service.nodes_passing["{#SERVICE_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Node == "{#SERVICE_NAME}")].Checks[?(@.CheckID == "serfHealth" && @.Status == 'passing')].length()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Consul |Consul: Service ["{#SERVICE_NAME}"]: Nodes warning |<p>-</p> |DEPENDENT |consul.service.nodes_warning["{#SERVICE_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Service.Service == "{#SERVICE_NAME}")].Checks[?(@.CheckID == "serfHealth" && @.Status == 'warning')].length()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Consul |Consul: Service ["{#SERVICE_NAME}"]: Nodes critical |<p>-</p> |DEPENDENT |consul.service.nodes_critical["{#SERVICE_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.Service.Service == "{#SERVICE_NAME}")].Checks[?(@.CheckID == "serfHealth" && @.Status == 'critical')].length()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Consul cluster |Consul cluster: Cluster leader |<p>Current leader address.</p> |HTTP_AGENT |consul.get_leader<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- TRIM: `"`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |Consul cluster: Nodes: peers |<p>The number of Raft peers for the datacenter in which the agent is running.</p> |HTTP_AGENT |consul.get_peers<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$.length()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Zabbix raw items |Consul cluster: Get nodes |<p>Catalog of nodes registered in a given datacenter.</p> |HTTP_AGENT |consul.get_nodes<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Zabbix raw items |Consul cluster: Get nodes Serf health status |<p>Get Serf Health Status for all agents in cluster.</p> |HTTP_AGENT |consul.get_cluster_serf<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Zabbix raw items |Consul cluster: Get services |<p>Catalog of services registered in a given datacenter.</p> |HTTP_AGENT |consul.get_catalog_services<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Zabbix raw items |Consul cluster: ["{#SERVICE_NAME}"]: Get raw service state |<p>Retrieve service instances providing the service indicated on the path.</p> |HTTP_AGENT |consul.get_service_stats["{#SERVICE_NAME}"]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Consul: One or more nodes in cluster in 'critical' state |<p>One or more agents on current dc with serf health status 'critical'.</p> |`last(/HashiCorp Consul Cluster by HTTP/consul.nodes_critical)>0` |AVERAGE | |
|Consul: One or more nodes in cluster in 'warning' state |<p>One or more agents on current dc with serf health status 'warning'.</p> |`last(/HashiCorp Consul Cluster by HTTP/consul.nodes_warning)>0` |WARNING | |
|Consul: Service ["{#SERVICE_NAME}"]: Too many nodes with service status 'critical'
 |<p>One or more nodes with service status 'critical'.</p> |`last(/HashiCorp Consul Cluster by HTTP/consul.service.nodes_critical["{#SERVICE_NAME}"])>{$CONSUL.CLUSTER.SERVICE_NODES.CRITICAL.MAX.AVG:"{#SERVICE_NAME}"}` |AVERAGE | |
|Consul cluster: Leader has been changed |<p>Consul cluster version has changed. Ack to close.</p> |`last(/HashiCorp Consul Cluster by HTTP/consul.get_leader,#1)<>last(/HashiCorp Consul Cluster by HTTP/consul.get_leader,#2) and length(last(/HashiCorp Consul Cluster by HTTP/consul.get_leader))>0` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

