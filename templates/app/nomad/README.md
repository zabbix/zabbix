
# HashiCorp Nomad by HTTP

## Overview

This template is designed to monitor HashiCorp Nomad by Zabbix.
It works without any external scripts.
Currently the template supports Nomad servers and clients discovery.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- HashiCorp Nomad version 1.5.6/1.6.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a synthetic Nomad host. It should be one of the Nomad cluster members, load-balancing service (if cluster is used) or a single node in a selected Nomad region.
2. Define the `{$NOMAD.ENDPOINT.API.URL}` macro value with correct web protocol, host and port.
3. Prepare an ACL token with `node:read`, `namespace:read-job`, `agent:read` and `management` permissions applied. Define the `{$NOMAD.TOKEN}` macro value.
> Refer to the vendor documentation about [`Nomad native ACL`](https://developer.hashicorp.com/nomad/tutorials/access-control/access-control-policies) or [`Nomad Vault-generated tokens`](https://developer.hashicorp.com/nomad/tutorials/access-control/vault-nomad-secrets) if you have the HashiCorp Vault integration configured.

**Additional information**:

* Synthetic Nomad host will be used just as an endpoint for servers and clients discovery (general cluster information), it will not be monitored as a Nomad server or client, so that to prevent duplicate entities.
* If you're not using ACL - skip 3rd setup step.
* The Nomad servers/clients discovery is limited by region. If you're using multi-region cluster- create one synthetic host per region.
* The Nomad server/client templates are ready for separate usage. Feel free to use if you prefer manual host creation.

**Useful links**
* [HashiCorp Nomad multi-region federation](https://developer.hashicorp.com/nomad/tutorials/manage-clusters/federation)
* [HashiCorp Nomad agent API reference](https://developer.hashicorp.com/nomad/api-docs/agent)
* [HashiCorp Nomad raft operator API reference](https://developer.hashicorp.com/nomad/api-docs/operator/raft)
* [HashiCorp Nomad nodes API reference](https://developer.hashicorp.com/nomad/api-docs/nodes)

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NOMAD.ENDPOINT.API.URL}|<p>API endpoint URL for one of the Nomad cluster members.</p>|`http://localhost:4646`|
|{$NOMAD.TOKEN}|<p>Nomad authentication token.</p>||
|{$NOMAD.DATA.TIMEOUT}|<p>Response timeout for an API.</p>|`15s`|
|{$NOMAD.HTTP.PROXY}|<p>Sets the HTTP proxy for script and HTTP agent items. If this parameter is empty, then no proxy is used.</p>||
|{$NOMAD.API.RESPONSE.SUCCESS}|<p>HTTP API successful response code. Availability triggers threshold. Change, if needed.</p>|`200`|
|{$NOMAD.SERVER.NAME.MATCHES}|<p>The filter to include HashiCorp Nomad servers by name.</p>|`.*`|
|{$NOMAD.SERVER.NAME.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad servers by name.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.SERVER.DC.MATCHES}|<p>The filter to include HashiCorp Nomad servers by datacenter belonging.</p>|`.*`|
|{$NOMAD.SERVER.DC.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad servers by datacenter belonging.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.CLIENT.NAME.MATCHES}|<p>The filter to include HashiCorp Nomad clients by name.</p>|`.*`|
|{$NOMAD.CLIENT.NAME.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad clients by name.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.CLIENT.DC.MATCHES}|<p>The filter to include HashiCorp Nomad clients by datacenter belonging.</p>|`.*`|
|{$NOMAD.CLIENT.DC.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad clients by datacenter belonging.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.CLIENT.SCHEDULE.ELIGIBILITY.MATCHES}|<p>The filter to include HashiCorp Nomad clients by scheduling eligibility.</p>|`.*`|
|{$NOMAD.CLIENT.SCHEDULE.ELIGIBILITY.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad clients by scheduling eligibility.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nomad clients get|<p>Nomad clients data in raw format.</p>|HTTP agent|nomad.client.nodes.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"header":{"HTTP/1.1 408 Request timeout":""}}`</p></li></ul>|
|Client nodes API response|<p>Client nodes API response message.</p>|Dependent item|nomad.client.nodes.api.response<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nomad servers get|<p>Nomad servers data in raw format.</p>|Script|nomad.server.nodes.get|
|Server-related APIs response|<p>Server-related (`operator/raft/configuration`, `agent/members`) APIs error response message.</p>|Dependent item|nomad.server.api.response<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: `HTTP/1.1 200 OK`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Region|<p>Current cluster region.</p>|Dependent item|nomad.region<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..region.first()`</p></li></ul>|
|Nomad servers count|<p>Nomad servers count.</p>|Dependent item|nomad.servers.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Name)].length()`</p></li></ul>|
|Nomad clients count|<p>Nomad clients count.</p>|Dependent item|nomad.clients.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body[?(@.Name)].length()`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HashiCorp Nomad: Client nodes API connection has failed|<p>Client nodes API connection has failed.<br>Ensure that Nomad API URL and the necessary permissions have been defined correctly, check the service state and network connectivity between Nomad and Zabbix.</p>|`find(/HashiCorp Nomad by HTTP/nomad.client.nodes.api.response,,"like","{$NOMAD.API.RESPONSE.SUCCESS}")=0`|Average|**Manual close**: Yes|
|HashiCorp Nomad: Server-related API connection has failed|<p>Server-related API connection has failed.<br>Ensure that Nomad API URL and the necessary permissions have been defined correctly, check the service state and network connectivity between Nomad and Zabbix.</p>|`find(/HashiCorp Nomad by HTTP/nomad.server.api.response,,"like","{$NOMAD.API.RESPONSE.SUCCESS}")=0`|Average|**Manual close**: Yes|

### LLD rule Clients discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Clients discovery|<p>Client nodes discovery.</p>|Dependent item|nomad.clients.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Servers discovery|<p>Server nodes discovery.</p>|Dependent item|nomad.servers.discovery<p>**Preprocessing**</p><ul><li><p>Check for error in JSON: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

# HashiCorp Nomad Client by HTTP

## Overview

This template is designed to monitor HashiCorp Nomad clients by Zabbix.
It works without any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- HashiCorp Nomad version 1.5.6/1.6.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Enable telemetry in HashiCorp Nomad agent configuration file. Set the Prometheus metrics format.
>Refer to the [`vendor documentation`](https://developer.hashicorp.com/nomad/docs/configuration/telemetry).
2. Prepare an ACL token with `node:read`, `namespace:read-job` permissions applied. Define the `{$NOMAD.TOKEN}` macro value.
> Refer to the vendor documentation about [`Nomad native ACL`](https://developer.hashicorp.com/nomad/tutorials/access-control/access-control-policies) or [`Nomad Vault-generated tokens`](https://developer.hashicorp.com/nomad/tutorials/access-control/vault-nomad-secrets) if you're using integration with HashiCorp Vault.
3. Set the values for the `{$NOMAD.CLIENT.API.SCHEME}` and `{$NOMAD.CLIENT.API.PORT}` macros to define the common Nomad API web schema and connection port.

**Additional information**:

* You have to prepare an additional ACL token only if you wish to monitor Nomad clients as separate entities. If you're using clients discovery - token will be inherited from the master host linked to the HashiCorp Nomad by HTTP template.

* If you're not using ACL - skip 2nd setup step.

* The Nomad clients use the default web schema - `HTTP` and default API port - `4646`. If you're using clients discovery and you need to re-define macros for the particular host created from prototype, use the context macros like {{$NOMAD.CLIENT.API.SCHEME:`NECESSARY.IP`}} or/and {{$NOMAD.CLIENT.API.PORT:`NECESSARY.IP`}} on master host or template level.
* Some metrics may not be collected depending on your HashiCorp Nomad agent version and configuration.

**Useful links**:

* [HashiCorp Nomad metrics list](https://developer.hashicorp.com/nomad/docs/operations/metrics-reference)
* [HashiCorp Nomad telemetry configuration reference](https://developer.hashicorp.com/nomad/docs/configuration/telemetry)
* [HashiCorp Nomad metrics API reference](https://developer.hashicorp.com/nomad/api-docs/metrics)
* [HashiCorp Nomad nodes API reference](https://developer.hashicorp.com/nomad/api-docs/nodes)
* [HashiCorp Nomad allocations API reference](https://developer.hashicorp.com/nomad/api-docs/allocations)
* [Zabbix user macros with context](https://www.zabbix.com/documentation/8.0/manual/config/macros/user_macros_context)

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NOMAD.CLIENT.API.SCHEME}|<p>Nomad client API scheme.</p>|`http`|
|{$NOMAD.CLIENT.API.PORT}|<p>Nomad client API port.</p>|`4646`|
|{$NOMAD.TOKEN}|<p>Nomad authentication token.</p>||
|{$NOMAD.DATA.TIMEOUT}|<p>Response timeout for an API.</p>|`15s`|
|{$NOMAD.HTTP.PROXY}|<p>Sets the HTTP proxy for HTTP agent item. If this parameter is empty, then no proxy is used.</p>||
|{$NOMAD.API.RESPONSE.SUCCESS}|<p>HTTP API successful response code. Availability triggers threshold. Change, if needed.</p>|`200`|
|{$NOMAD.CLIENT.RPC.PORT}|<p>Nomad RPC service port.</p>|`4647`|
|{$NOMAD.CLIENT.SERF.PORT}|<p>Nomad serf service port.</p>|`4648`|
|{$NOMAD.CLIENT.OPEN.FDS.MAX.WARN}|<p>Maximum percentage of used file descriptors.</p>|`90`|
|{$NOMAD.DISK.NAME.MATCHES}|<p>The filter to include HashiCorp Nomad client disks by name.</p>|`.*`|
|{$NOMAD.DISK.NAME.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad client disks by name.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.JOB.NAME.MATCHES}|<p>The filter to include HashiCorp Nomad client jobs by name.</p>|`.*`|
|{$NOMAD.JOB.NAME.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad client jobs by name.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.JOB.NAMESPACE.MATCHES}|<p>The filter to include HashiCorp Nomad client jobs by namespace.</p>|`.*`|
|{$NOMAD.JOB.NAMESPACE.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad client jobs by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.JOB.TYPE.MATCHES}|<p>The filter to include HashiCorp Nomad client jobs by type.</p>|`.*`|
|{$NOMAD.JOB.TYPE.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad client jobs by type.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.JOB.TASK.GROUP.MATCHES}|<p>The filter to include HashiCorp Nomad client jobs by task group belonging.</p>|`.*`|
|{$NOMAD.JOB.TASK.GROUP.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad client jobs by task group belonging.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.DRIVER.NAME.MATCHES}|<p>The filter to include HashiCorp Nomad client drivers by name.</p>|`.*`|
|{$NOMAD.DRIVER.NAME.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad client drivers by name.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.DRIVER.DETECT.MATCHES}|<p>The filter to include HashiCorp Nomad client drivers by detection state. Possible filtering values: `true`, `false`.</p>|`.*`|
|{$NOMAD.DRIVER.DETECT.NOT_MATCHES}|<p>The filter to exclude HashiCorp Nomad client drivers by detection state. Possible filtering values: `true`, `false`.</p>|`CHANGE_IF_NEEDED`|
|{$NOMAD.CPU.UTIL.MIN}|<p>CPU utilization threshold. Measured as a percentage.</p>|`90`|
|{$NOMAD.RAM.AVAIL.MIN}|<p>CPU utilization threshold. Measured as a percentage.</p>|`5`|
|{$NOMAD.INODES.FREE.MIN.WARN}|<p>Warning threshold of the filesystem metadata utilization. Measured as a percentage.</p>|`20`|
|{$NOMAD.INODES.FREE.MIN.CRIT}|<p>Critical threshold of the filesystem metadata utilization. Measured as a percentage.</p>|`10`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Telemetry get|<p>Telemetry data in raw format.</p>|HTTP agent|nomad.client.data.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"header":{"HTTP/1.1 408 Request timeout":""}}`</p></li></ul>|
|Metrics|<p>Nomad client metrics in raw format.</p>|Dependent item|nomad.client.metrics.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Monitoring API response|<p>Monitoring API response message.</p>|Dependent item|nomad.client.data.api.response<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [rpc] state|<p>Current [rpc] service state.</p>|Simple check|net.tcp.service[tcp,,{$NOMAD.CLIENT.RPC.PORT}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [serf] state|<p>Current [serf] service state.</p>|Simple check|net.tcp.service[tcp,,{$NOMAD.CLIENT.SERF.PORT}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU allocated|<p>Total amount of CPU shares the scheduler has allocated to tasks.</p>|Dependent item|nomad.client.allocated.cpu<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_allocated_cpu)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU unallocated|<p>Total amount of CPU shares free for the scheduler to allocate to tasks.</p>|Dependent item|nomad.client.unallocated.cpu<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_unallocated_cpu)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory allocated|<p>Total amount of memory the scheduler has allocated to tasks.</p>|Dependent item|nomad.client.allocated.memory<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_allocated_memory)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E+6`</p></li></ul>|
|Memory unallocated|<p>Total amount of memory free for the scheduler to allocate to tasks.</p>|Dependent item|nomad.client.unallocated.memory<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_unallocated_memory)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E+6`</p></li></ul>|
|Disk allocated|<p>Total amount of disk space the scheduler has allocated to tasks.</p>|Dependent item|nomad.client.allocated.disk<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_allocated_disk)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E+6`</p></li></ul>|
|Disk unallocated|<p>Total amount of disk space free for the scheduler to allocate to tasks.</p>|Dependent item|nomad.client.unallocated.disk<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_unallocated_disk)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1.0E+6`</p></li></ul>|
|Allocations blocked|<p>Number of allocations waiting for previous versions.</p>|Dependent item|nomad.client.allocations.blocked<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_allocations_blocked)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Allocations migrating|<p>Number of allocations migrating data from previous versions.</p>|Dependent item|nomad.client.allocations.migrating<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_allocations_migrating)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Allocations pending|<p>Number of allocations pending (received by the client but not yet running).</p>|Dependent item|nomad.client.allocations.pending<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_allocations_pending)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Allocations starting|<p>Number of allocations starting.</p>|Dependent item|nomad.client.allocations.start<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_allocations_start)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Allocations running|<p>Number of allocations running.</p>|Dependent item|nomad.client.allocations.running<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_allocations_running)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Allocations terminal|<p>Number of allocations terminal.</p>|Dependent item|nomad.client.allocations.terminal<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_allocations_terminal)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Allocations failed, rate|<p>Number of allocations failed.</p>|Dependent item|nomad.client.allocations.failed<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_client_allocs_failed)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Allocations completed, rate|<p>Number of allocations completed.</p>|Dependent item|nomad.client.allocations.complete<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_client_allocs_complete)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Allocations restarted, rate|<p>Number of allocations restarted.</p>|Dependent item|nomad.client.allocations.restart<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_client_allocs_restart)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Allocations OOM killed|<p>Number of allocations OOM killed.</p>|Dependent item|nomad.client.allocations.oom_killed<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_allocs_oom_killed)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU idle utilization|<p>CPU utilization in idle state.</p>|Dependent item|nomad.client.cpu.idle<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `AVG(nomad_client_host_cpu_idle)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU system utilization|<p>CPU utilization in system space.</p>|Dependent item|nomad.client.cpu.system<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `AVG(nomad_client_host_cpu_system)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU total utilization|<p>Total CPU utilization.</p>|Dependent item|nomad.client.cpu.total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `AVG(nomad_client_host_cpu_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU user utilization|<p>CPU utilization in user space.</p>|Dependent item|nomad.client.cpu.user<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `AVG(nomad_client_host_cpu_user)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory available|<p>Total amount of memory available to processes which includes free and cached memory.</p>|Dependent item|nomad.client.memory.available<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_host_memory_available)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory free|<p>Amount of memory which is free.</p>|Dependent item|nomad.client.memory.free<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_host_memory_free)`</p></li></ul>|
|Memory size|<p>Total amount of physical memory on the node.</p>|Dependent item|nomad.client.memory.total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_host_memory_total)`</p></li></ul>|
|Memory used|<p>Amount of memory used by processes.</p>|Dependent item|nomad.client.memory.used<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_host_memory_used)`</p></li></ul>|
|Uptime|<p>Uptime of the host running the Nomad client.</p>|Dependent item|nomad.client.uptime<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_uptime)`</p></li></ul>|
|Node info get|<p>Node info data in raw format.</p>|HTTP agent|nomad.client.node.info.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"header":{"HTTP/1.1 408 Request timeout":""}}`</p></li></ul>|
|Nomad client version|<p>Nomad client version.</p>|Dependent item|nomad.client.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body..Version.first()`</p></li></ul>|
|Nodes API response|<p>Nodes API response message.</p>|Dependent item|nomad.client.node.info.api.response<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Allocated jobs get|<p>Allocated jobs data in raw format.</p>|HTTP agent|nomad.client.job.allocs.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"header":{"HTTP/1.1 408 Request timeout":""}}`</p></li></ul>|
|Allocations API response|<p>Allocations API response message.</p>|Dependent item|nomad.client.job.allocs.api.response<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HashiCorp Nomad Client: Monitoring API connection has failed|<p>Monitoring API connection has failed.<br>Ensure that Nomad API URL and the necessary permissions have been defined correctly, check the service state and network connectivity between Nomad and Zabbix.</p>|`find(/HashiCorp Nomad Client by HTTP/nomad.client.data.api.response,,"like","{$NOMAD.API.RESPONSE.SUCCESS}")=0`|Average|**Manual close**: Yes|
|HashiCorp Nomad Client: Service [rpc] is down|<p>Cannot establish the connection to [rpc] service port {$NOMAD.CLIENT.RPC.PORT}.<br>Check the Nomad state and network connectivity between Nomad and Zabbix.</p>|`last(/HashiCorp Nomad Client by HTTP/net.tcp.service[tcp,,{$NOMAD.CLIENT.RPC.PORT}]) = 0`|Average|**Manual close**: Yes|
|HashiCorp Nomad Client: Service [serf] is down|<p>Cannot establish the connection to [serf] service port {$NOMAD.CLIENT.SERF.PORT}.<br>Check the Nomad state and network connectivity between Nomad and Zabbix.</p>|`last(/HashiCorp Nomad Client by HTTP/net.tcp.service[tcp,,{$NOMAD.CLIENT.SERF.PORT}]) = 0`|Average|**Manual close**: Yes|
|HashiCorp Nomad Client: OOM killed allocations found|<p>OOM killed allocations found.</p>|`last(/HashiCorp Nomad Client by HTTP/nomad.client.allocations.oom_killed) > 0`|Warning|**Manual close**: Yes|
|HashiCorp Nomad Client: High CPU utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/HashiCorp Nomad Client by HTTP/nomad.client.cpu.total, 10m) >= {$NOMAD.CPU.UTIL.MIN}`|Average||
|HashiCorp Nomad Client: High memory utilization|<p>RAM utilization is too high. The system might be slow to respond.</p>|`(min(/HashiCorp Nomad Client by HTTP/nomad.client.memory.available, 10m) / last(/HashiCorp Nomad Client by HTTP/nomad.client.memory.total))*100 <= {$NOMAD.RAM.AVAIL.MIN}`|Average||
|HashiCorp Nomad Client: The host has been restarted|<p>The host uptime is less than 10 minutes.</p>|`last(/HashiCorp Nomad Client by HTTP/nomad.client.uptime) < 10m`|Warning|**Manual close**: Yes|
|HashiCorp Nomad Client: Nomad client version has changed|<p>Nomad client version has changed.</p>|`change(/HashiCorp Nomad Client by HTTP/nomad.client.version)<>0`|Info|**Manual close**: Yes|
|HashiCorp Nomad Client: Nodes API connection has failed|<p>Nodes API connection has failed.<br>Ensure that Nomad API URL and the necessary permissions have been defined correctly, check the service state and network connectivity between Nomad and Zabbix.</p>|`find(/HashiCorp Nomad Client by HTTP/nomad.client.node.info.api.response,,"like","{$NOMAD.API.RESPONSE.SUCCESS}")=0`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HashiCorp Nomad Client: Monitoring API connection has failed</li></ul>|
|HashiCorp Nomad Client: Allocations API connection has failed|<p>Allocations API connection has failed.<br>Ensure that Nomad API URL and the necessary permissions have been defined correctly, check the service state and network connectivity between Nomad and Zabbix.</p>|`find(/HashiCorp Nomad Client by HTTP/nomad.client.job.allocs.api.response,,"like","{$NOMAD.API.RESPONSE.SUCCESS}")=0`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HashiCorp Nomad Client: Monitoring API connection has failed</li></ul>|

### LLD rule Drivers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Drivers discovery|<p>Client drivers discovery.</p>|Dependent item|nomad.client.drivers.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Drivers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Driver [{#DRIVER.NAME}] state|<p>Driver [{#DRIVER.NAME}] state.</p>|Dependent item|nomad.client.driver.state["{#DRIVER.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body..Drivers.{#DRIVER.NAME}.Healthy.first()`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Driver [{#DRIVER.NAME}] detection state|<p>Driver [{#DRIVER.NAME}] detection state.</p>|Dependent item|nomad.client.driver.detected["{#DRIVER.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body..Drivers.{#DRIVER.NAME}.Detected.first()`</p></li><li>Boolean to decimal</li></ul>|

### Trigger prototypes for Drivers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HashiCorp Nomad Client: Driver [{#DRIVER.NAME}] is in unhealthy state|<p>The [{#DRIVER.NAME}] driver detected, but its state is unhealthy.</p>|`last(/HashiCorp Nomad Client by HTTP/nomad.client.driver.state["{#DRIVER.NAME}"]) = 0 and last(/HashiCorp Nomad Client by HTTP/nomad.client.driver.detected["{#DRIVER.NAME}"]) = 1`|Warning|**Manual close**: Yes|
|HashiCorp Nomad Client: Driver [{#DRIVER.NAME}] detection state has changed|<p>The [{#DRIVER.NAME}] driver detection state has changed.</p>|`change(/HashiCorp Nomad Client by HTTP/nomad.client.driver.detected["{#DRIVER.NAME}"]) <> 0`|Info|**Manual close**: Yes|

### LLD rule Physical disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical disks discovery|<p>Physical disks discovery.</p>|Dependent item|nomad.client.disk.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `nomad_client_host_disk_available{disk=~".*"}`</p></li></ul>|

### Item prototypes for Physical disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk ["{#DEV.NAME}"] space available|<p>Amount of space which is available on ["{#DEV.NAME}"] disk.</p>|Dependent item|nomad.client.disk.available["{#DEV.NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_host_disk_available{disk="{#DEV.NAME}"})`</p></li></ul>|
|Disk ["{#DEV.NAME}"] inodes utilization|<p>Disk space consumed by the inodes on ["{#DEV.NAME}"] disk.</p>|Dependent item|nomad.client.disk.inodes_percent["{#DEV.NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Disk ["{#DEV.NAME}"] size|<p>Total size of the ["{#DEV.NAME}"] device.</p>|Dependent item|nomad.client.disk.size["{#DEV.NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_host_disk_size{disk="{#DEV.NAME}"})`</p></li></ul>|
|Disk ["{#DEV.NAME}"] space utilization|<p>Percentage of disk ["{#DEV.NAME}"] space used.</p>|Dependent item|nomad.client.disk.used_percent["{#DEV.NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Disk ["{#DEV.NAME}"] space used|<p>Amount of disk ["{#DEV.NAME}"] space which has been used.</p>|Dependent item|nomad.client.disk.used["{#DEV.NAME}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_client_host_disk_used{disk="{#DEV.NAME}"})`</p></li></ul>|

### Trigger prototypes for Physical disks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HashiCorp Nomad Client: Running out of free inodes on [{#DEV.NAME}] device|<p>It may become impossible to write to a disk if there are no index nodes left.<br>The following error messages may be returned as symptoms, even though the free space:<br>- No space left on device;<br>- Disk is full.</p>|`min(/HashiCorp Nomad Client by HTTP/nomad.client.disk.inodes_percent["{#DEV.NAME}"],5m) >= {$NOMAD.INODES.FREE.MIN.WARN:"{#DEV.NAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HashiCorp Nomad Client: Running out of free inodes on [{#DEV.NAME}] device</li></ul>|
|HashiCorp Nomad Client: Running out of free inodes on [{#DEV.NAME}] device|<p>It may become impossible to write to a disk if there are no index nodes left.<br>The following error messages may be returned as symptoms, even though the free space:<br>- No space left on device;<br>- Disk is full.</p>|`min(/HashiCorp Nomad Client by HTTP/nomad.client.disk.inodes_percent["{#DEV.NAME}"],5m) >= {$NOMAD.INODES.FREE.MIN.CRIT:"{#DEV.NAME}"}`|Average|**Manual close**: Yes|
|HashiCorp Nomad Client: High disk [{#DEV.NAME}] utilization|<p>High disk [{#DEV.NAME}] utilization.</p>|`min(/HashiCorp Nomad Client by HTTP/nomad.client.disk.used_percent["{#DEV.NAME}"],5m) >= {$NOMAD.DISK.UTIL.MIN.WARN:"{#DEV.NAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HashiCorp Nomad Client: Running out of free inodes on [{#DEV.NAME}] device</li></ul>|
|HashiCorp Nomad Client: High disk [{#DEV.NAME}] utilization|<p>High disk [{#DEV.NAME}] utilization.</p>|`min(/HashiCorp Nomad Client by HTTP/nomad.client.disk.used_percent["{#DEV.NAME}"],5m) >= {$NOMAD.DISK.UTIL.MIN.CRIT:"{#DEV.NAME}"}`|Average|**Manual close**: Yes|

### LLD rule Allocated jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Allocated jobs discovery|<p>Allocated jobs discovery.</p>|Dependent item|nomad.client.alloc.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Allocated jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Job ["{#JOB.NAME}"] CPU allocated|<p>Total CPU resources allocated by the ["{#JOB.NAME}"] job across all cores.</p>|Dependent item|nomad.client.allocs.cpu.allocated["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Job ["{#JOB.NAME}"] CPU system utilization|<p>Total CPU resources consumed by the ["{#JOB.NAME}"] job in system space.</p>|Dependent item|nomad.client.allocs.cpu.system["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Job ["{#JOB.NAME}"] CPU user utilization|<p>Total CPU resources consumed by the ["{#JOB.NAME}"] job in user space.</p>|Dependent item|nomad.client.allocs.cpu.user["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Job ["{#JOB.NAME}"] CPU total utilization|<p>Total CPU resources consumed by the ["{#JOB.NAME}"] job across all cores.</p>|Dependent item|nomad.client.allocs.cpu.total_percent["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Job ["{#JOB.NAME}"] CPU throttled periods time|<p>Total number of CPU periods that the ["{#JOB.NAME}"] job was throttled.</p>|Dependent item|nomad.client.allocs.cpu.throttled_periods["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Job ["{#JOB.NAME}"] CPU throttled time|<p>Total time that the ["{#JOB.NAME}"] job was throttled.</p>|Dependent item|nomad.client.allocs.cpu.throttled_time["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Job ["{#JOB.NAME}"] CPU ticks|<p>CPU ticks consumed by the process for the ["{#JOB.NAME}"] job in the last collection interval.</p>|Dependent item|nomad.client.allocs.cpu.total_ticks["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Job ["{#JOB.NAME}"] Memory allocated|<p>Amount of memory allocated by the ["{#JOB.NAME}"] job.</p>|Dependent item|nomad.client.allocs.memory.allocated["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Job ["{#JOB.NAME}"] Memory cached|<p>Amount of memory cached by the ["{#JOB.NAME}"] job.</p>|Dependent item|nomad.client.allocs.memory.cache["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Job ["{#JOB.NAME}"] Memory used|<p>Total amount of memory used by the ["{#JOB.NAME}"] job.</p>|Dependent item|nomad.client.allocs.memory.usage["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|
|Job ["{#JOB.NAME}"] Memory swapped|<p>Amount of memory swapped by the ["{#JOB.NAME}"] job.</p>|Dependent item|nomad.client.allocs.memory.swap["{#JOB.NAME}","{#JOB.TASK.GROUP}","{#JOB.NAMESPACE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p></li></ul>|

# HashiCorp Nomad Server by HTTP

## Overview

This template is designed to monitor HashiCorp Nomad servers by Zabbix.
It works without any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- HashiCorp Nomad version 1.5.6/1.6.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Enable telemetry in HashiCorp Nomad agent configuration file. Set the Prometheus metrics format.
>Refer to the [`vendor documentation`](https://developer.hashicorp.com/nomad/docs/configuration/telemetry).
2. Set the values for the `{$NOMAD.SERVER.API.SCHEME}` and `{$NOMAD.SERVER.API.PORT}` macros to define the common Nomad API web schema and connection port.

**Additional information**:

* The Nomad servers use the default web schema - `HTTP` and default API port - `4646`. If you're using servers discovery and you need to re-define macros for the particular host created from prototype, use the context macros like {{$NOMAD.SERVER.API.SCHEME:`NECESSARY.IP`}} or/and {{$NOMAD.SERVER.API.PORT:`NECESSARY.IP`}} on master host or template level.
* Some metrics may not be collected depending on your HashiCorp Nomad agent version, configuration and cluster role.
* Don't forget to define the `{$NOMAD.REDUNDANCY.MIN}` macro value, based on your cluster nodes amount to configure the failure tolerance triggers correctly.

**Useful links**:

* [HashiCorp Nomad metrics list](https://developer.hashicorp.com/nomad/docs/operations/metrics-reference)
* [HashiCorp Nomad telemetry configuration reference](https://developer.hashicorp.com/nomad/docs/configuration/telemetry)
* [HashiCorp Nomad metrics API reference](https://developer.hashicorp.com/nomad/api-docs/metrics)
* [HashiCorp Nomad agent API reference](https://developer.hashicorp.com/nomad/api-docs/agent#query-self)
* [HashiCorp Nomad cluster failure tolerance reference](https://developer.hashicorp.com/nomad/docs/concepts/consensus#deployment-table)
* [Zabbix user macros with context](https://www.zabbix.com/documentation/8.0/manual/config/macros/user_macros_context)

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NOMAD.SERVER.API.SCHEME}|<p>Nomad SERVER API scheme.</p>|`http`|
|{$NOMAD.SERVER.API.PORT}|<p>Nomad SERVER API port.</p>|`4646`|
|{$NOMAD.TOKEN}|<p>Nomad authentication token.</p>||
|{$NOMAD.DATA.TIMEOUT}|<p>Response timeout for an API.</p>|`15s`|
|{$NOMAD.HTTP.PROXY}|<p>Sets the HTTP proxy for HTTP agent item. If this parameter is empty, then no proxy is used.</p>||
|{$NOMAD.API.RESPONSE.SUCCESS}|<p>HTTP API successful response code. Availability triggers threshold. Change, if needed.</p>|`200`|
|{$NOMAD.SERVER.RPC.PORT}|<p>Nomad RPC service port.</p>|`4647`|
|{$NOMAD.SERVER.SERF.PORT}|<p>Nomad serf service port.</p>|`4648`|
|{$NOMAD.REDUNDANCY.MIN}|<p>Amount of redundant servers to keep the cluster safe.</p><p>Default value - '1' for the 3-nodes cluster.</p><p>Change if needed.</p>|`1`|
|{$NOMAD.OPEN.FDS.MAX}|<p>Maximum percentage of used file descriptors.</p>|`90`|
|{$NOMAD.SERVER.LEADER.LATENCY}|<p>Leader last contact latency threshold.</p>|`0.3s`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Telemetry get|<p>Telemetry data in raw format.</p>|HTTP agent|nomad.server.data.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"header":{"HTTP/1.1 408 Request timeout":""}}`</p></li></ul>|
|Metrics|<p>Nomad server metrics in raw format.</p>|Dependent item|nomad.server.metrics.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Monitoring API response|<p>Monitoring API response message.</p>|Dependent item|nomad.server.data.api.response<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Internal stats get|<p>Internal stats data in raw format.</p>|HTTP agent|nomad.server.stats.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"header":{"HTTP/1.1 408 Request timeout":""}}`</p></li></ul>|
|Internal stats API response|<p>Internal stats API response message.</p>|Dependent item|nomad.server.stats.api.response<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nomad server version|<p>Nomad server version.</p>|Dependent item|nomad.server.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body.config.Version.Version`</p></li></ul>|
|Nomad raft version|<p>Nomad raft version.</p>|Dependent item|nomad.raft.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body.stats.raft.protocol_version`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Raft peers|<p>Current cluster raft peers amount.</p>|Dependent item|nomad.server.raft.peers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body.stats.raft.num_peers`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Cluster role|<p>Current role in the cluster.</p>|Dependent item|nomad.server.raft.cluster_role<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.body.stats.raft.state`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU time, rate|<p>Total user and system CPU time spent in seconds.</p>|Dependent item|nomad.server.cpu.time<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_cpu_seconds_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Memory used|<p>Memory utilization in bytes.</p>|Dependent item|nomad.server.runtime.alloc_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_runtime_alloc_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Virtual memory size|<p>Virtual memory size in bytes.</p>|Dependent item|nomad.server.virtual_memory_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_virtual_memory_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Resident memory size|<p>Resident memory size in bytes.</p>|Dependent item|nomad.server.resident_memory_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_resident_memory_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Heap objects|<p>Number of objects on the heap.</p><p>General memory pressure indicator.</p>|Dependent item|nomad.server.runtime.heap_objects<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_runtime_heap_objects)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Open file descriptors|<p>Number of open file descriptors.</p>|Dependent item|nomad.server.process_open_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_open_fds)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Open file descriptors, max|<p>Maximum number of open file descriptors.</p>|Dependent item|nomad.server.process_max_fds<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(process_max_fds)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Goroutines|<p>Number of goroutines and general load pressure indicator.</p>|Dependent item|nomad.server.runtime.num_goroutines<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_runtime_num_goroutines)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Evaluations pending|<p>Evaluations that are pending until an existing evaluation for the same job completes.</p>|Dependent item|nomad.server.broker.total_pending<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_broker_total_pending)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Evaluations ready|<p>Number of evaluations ready to be processed.</p>|Dependent item|nomad.server.broker.total_ready<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_broker_total_ready)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Evaluations unacked|<p>Evaluations dispatched for processing but incomplete.</p>|Dependent item|nomad.server.broker.total_unacked<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_broker_total_unacked)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU shares for blocked evaluations|<p>Amount of CPU shares requested by blocked evals.</p>|Dependent item|nomad.server.blocked_evals.cpu<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_blocked_evals_cpu)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory shares by blocked evaluations|<p>Amount of memory requested by blocked evals.</p>|Dependent item|nomad.server.blocked_evals.memory<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_blocked_evals_memory)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU shares for blocked job evaluations|<p>Amount of CPU shares requested by blocked evals of a job.</p>|Dependent item|nomad.server.blocked_evals.job.cpu<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_blocked_evals_job_cpu)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory shares for blocked job evaluations|<p>Amount of memory requested by blocked evals of a job.</p>|Dependent item|nomad.server.blocked_evals.job.memory<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_blocked_evals_job_memory)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Evaluations blocked|<p>Count of evals in the blocked state for any reason (cluster resource exhaustion or quota limits).</p>|Dependent item|nomad.server.blocked_evals.total_blocked<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_blocked_evals_total_blocked)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Evaluations escaped|<p>Count of evals that have escaped computed node classes.</p><p>This indicates a scheduler optimization was skipped and is not usually a source of concern.</p>|Dependent item|nomad.server.blocked_evals.total_escaped<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_blocked_evals_total_escaped)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Evaluations waiting|<p>Count of evals waiting to be enqueued.</p>|Dependent item|nomad.server.broker.total_waiting<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_broker_total_waiting)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Evaluations blocked due to quota limit|<p>Count of blocked evals due to quota limits (the resources for these jobs are not counted in other blocked_evals metrics, except for total_blocked).</p>|Dependent item|nomad.server.blocked_evals.total_quota_limit<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_blocked_evals_total_quota_limit)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Evaluations enqueue time|<p>Average time elapsed with evaluations waiting to be enqueued.</p>|Dependent item|nomad.server.broker.eval_waiting<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `AVG(nomad_nomad_eval_ack_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|RPC evaluation acknowledgement time|<p>Time elapsed for Eval.Ack RPC call.</p>|Dependent item|nomad.server.eval.ack<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_eval_ack_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|RPC job summary time|<p>Time elapsed for Job.Summary RPC call.</p>|Dependent item|nomad.server.job_summary.get_job_summary<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_job_summary_get_job_summary_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Heartbeats active|<p>Number of active heartbeat timers.</p><p>Each timer represents a Nomad client connection.</p>|Dependent item|nomad.server.heartbeat.active<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_heartbeat_active)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|RPC requests, rate|<p>Number of RPC requests being handled.</p>|Dependent item|nomad.server.rpc.request<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_rpc_request)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|RPC error requests, rate|<p>Number of RPC requests being handled that result in an error.</p>|Dependent item|nomad.server.rpc.request_error<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_rpc_request)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|RPC queries, rate|<p>Number of RPC queries.</p>|Dependent item|nomad.server.rpc.query<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_rpc_query)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|RPC job allocations time|<p>Time elapsed for Job.Allocations RPC call.</p>|Dependent item|nomad.server.job.allocations<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_job_allocations_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|RPC job evaluations time|<p>Time elapsed for Job.Evaluations RPC call.</p>|Dependent item|nomad.server.job.evaluations<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_job_evaluations_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|RPC get job time|<p>Time elapsed for Job.GetJob RPC call.</p>|Dependent item|nomad.server.job.get_job<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_job_get_job_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Plan apply time|<p>Time elapsed to apply a plan.</p>|Dependent item|nomad.server.plan.apply<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_plan_apply_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Plan evaluate time|<p>Time elapsed to evaluate a plan.</p>|Dependent item|nomad.server.plan.evaluate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_plan_evaluate_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|RPC plan submit time|<p>Time elapsed for Plan.Submit RPC call.</p>|Dependent item|nomad.server.plan.submit<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_plan_submit_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Plan raft index processing time|<p>Time elapsed that planner waits for the raft index of the plan to be processed.</p>|Dependent item|nomad.server.plan.wait_for_index<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_plan_wait_for_index_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|RPC list time|<p>Time elapsed for Node.List RPC call.</p>|Dependent item|nomad.server.client.list<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_client_list_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|RPC update allocations time|<p>Time elapsed for Node.UpdateAlloc RPC call.</p>|Dependent item|nomad.server.client.update_alloc<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_client_update_alloc_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|RPC update status time|<p>Time elapsed for Node.UpdateStatus RPC call.</p>|Dependent item|nomad.server.client.update_status<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_client_update_status_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|RPC get client allocs time|<p>Time elapsed for Node.GetClientAllocs RPC call.</p>|Dependent item|nomad.server.client.get_client_allocs<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_client_get_client_allocs_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|RPC eval dequeue time|<p>Time elapsed for Eval.Dequeue RPC call.</p>|Dependent item|nomad.server.client.dequeue<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_eval_dequeue_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Vault token last renewal|<p>Time since last successful Vault token renewal.</p>|Dependent item|nomad.server.vault.token_last_renewal<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_vault_token_last_renewal)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Vault token next renewal|<p>Time until next Vault token renewal attempt.</p>|Dependent item|nomad.server.vault.token_next_renewal<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_vault_token_next_renewal)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Vault token TTL|<p>Time to live for Vault token.</p>|Dependent item|nomad.server.vault.token_ttl<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_vault_token_ttl)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Vault tokens revoked|<p>Count of revoked tokens.</p>|Dependent item|nomad.server.vault.distributed_tokens_revoked<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_vault_distributed_tokens_revoking)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jobs dead|<p>Number of dead jobs.</p>|Dependent item|nomad.server.job_status.dead<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_job_status_dead)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Jobs pending|<p>Number of pending jobs.</p>|Dependent item|nomad.server.job_status.pending<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_job_status_pending)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Jobs running|<p>Number of running jobs.</p>|Dependent item|nomad.server.job_status.running<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_job_status_running)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Job allocations completed|<p>Number of complete allocations for a job.</p>|Dependent item|nomad.server.job_summary.complete<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_nomad_job_summary_complete)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Job allocations failed|<p>Number of failed allocations for a job.</p>|Dependent item|nomad.server.job_summary.failed<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_nomad_job_summary_failed)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Job allocations lost|<p>Number of lost allocations for a job.</p>|Dependent item|nomad.server.job_summary.lost<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_nomad_job_summary_lost)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Job allocations unknown|<p>Number of unknown allocations for a job.</p>|Dependent item|nomad.server.job_summary.unknown<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_nomad_job_summary_unknown)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Job allocations queued|<p>Number of queued allocations for a job.</p>|Dependent item|nomad.server.job_summary.queued<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_nomad_job_summary_queued)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Job allocations running|<p>Number of running allocations for a job.</p>|Dependent item|nomad.server.job_summary.running<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_nomad_job_summary_running)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Job allocations starting|<p>Number of starting allocations for a job.</p>|Dependent item|nomad.server.job_summary.starting<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_nomad_job_summary_starting)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Gossip time|<p>Time elapsed to broadcast gossip messages.</p>|Dependent item|nomad.server.memberlist.gossip<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_memberlist_gossip_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Leader barrier time|<p>Time elapsed to establish a raft barrier during leader transition.</p>|Dependent item|nomad.server.leader.barrier<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_leader_barrier_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Reconcile peer time|<p>Time elapsed to reconcile a serf peer with state store.</p>|Dependent item|nomad.server.leader.reconcile_member<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_leader_reconcileMember_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Total reconcile time|<p>Time elapsed to reconcile all serf peers with state store.</p>|Dependent item|nomad.server.leader.reconcile<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_leader_reconcile_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Leader last contact|<p>Time since last contact to leader.</p><p>General indicator of Raft latency.</p>|Dependent item|nomad.server.raft.leader.lastContact<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_leader_lastContact{quantile="0.99"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Replace: `NaN -> 0`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Plan queue|<p>Count of evals in the plan queue.</p>|Dependent item|nomad.server.plan.queue_depth<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_plan_queue_depth)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Worker evaluation create time|<p>Time elapsed for worker to create an eval.</p>|Dependent item|nomad.server.worker.create_eval<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_worker_dequeue_eval_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Worker evaluation dequeue time|<p>Time elapsed for worker to dequeue an eval.</p>|Dependent item|nomad.server.worker.dequeue_eval<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_worker_dequeue_eval_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Worker invoke scheduler time|<p>Time elapsed for worker to invoke the scheduler.</p>|Dependent item|nomad.server.worker.invoke_scheduler_service<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_worker_invoke_scheduler_service_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Worker acknowledgement send time|<p>Time elapsed for worker to send acknowledgement.</p>|Dependent item|nomad.server.worker.send_ack<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_worker_send_ack_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Worker submit plan time|<p>Time elapsed for worker to submit plan.</p>|Dependent item|nomad.server.worker.submit_plan<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_worker_submit_plan_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Worker update evaluation time|<p>Time elapsed for worker to submit updated eval.</p>|Dependent item|nomad.server.worker.update_eval<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_worker_update_eval_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Worker log replication time|<p>Time elapsed that worker waits for the raft index of the eval to be processed.</p>|Dependent item|nomad.server.worker.wait_for_index<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_worker_wait_for_index_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Raft calls blocked, rate|<p>Count of blocking raft API calls.</p>|Dependent item|nomad.server.raft.barrier<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_barrier)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Raft commit logs enqueued|<p>Count of logs enqueued.</p>|Dependent item|nomad.server.raft.commit_num_logs<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_commitNumLogs)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Raft transactions, rate|<p>Number of Raft transactions.</p>|Dependent item|nomad.server.raft.apply<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_apply)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Raft commit time|<p>Time elapsed to commit writes.</p>|Dependent item|nomad.server.raft.commit_time<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_worker_dequeue_eval_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Raft transaction commit time|<p>Raft transaction commit time.</p>|Dependent item|nomad.server.raft.replication.appendEntries<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `AVG(nomad_raft_replication_appendEntries_rpc)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|FSM apply time|<p>Time elapsed to apply write to FSM.</p>|Dependent item|nomad.server.raft.fsm.apply<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_fsm_apply_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|FSM enqueue time|<p>Time elapsed to enqueue write to FSM.</p>|Dependent item|nomad.server.raft.fsm.enqueue<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_fsm_enqueue_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|FSM autopilot time|<p>Time elapsed to apply Autopilot raft entry.</p>|Dependent item|nomad.server.raft.fsm.autopilot<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_fsm_autopilot_sum)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|FSM register node time|<p>Time elapsed to apply RegisterNode raft entry.</p>|Dependent item|nomad.server.raft.fsm.register_node<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_fsm_register_node_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|FSM index|<p>Current index applied to FSM.</p>|Dependent item|nomad.server.raft.applied_index<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_appliedIndex)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Raft last index|<p>Most recent index seen.</p>|Dependent item|nomad.server.raft.last_index<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_lastIndex)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Dispatch log time|<p>Time elapsed to write log, mark in flight, and start replication.</p>|Dependent item|nomad.server.raft.leader.dispatch_log<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_leader_dispatchLog_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Logs dispatched|<p>Count of logs dispatched.</p>|Dependent item|nomad.server.raft.leader.dispatch_num_logs<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_leader_dispatchNumLogs)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Heartbeat fails|<p>Count of failing to heartbeat and starting election.</p>|Dependent item|nomad.server.raft.transition.heartbeat_timeout<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_transition_heartbeat_timeout)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Objects freed, rate|<p>Count of objects freed from heap by go runtime GC.</p>|Dependent item|nomad.server.runtime.free_count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_runtime_free_count)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GC pause time|<p>Go runtime GC pause times.</p>|Dependent item|nomad.server.runtime.gc_pause_ns<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_runtime_gc_pause_ns_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|GC metadata size|<p>Go runtime GC metadata size in bytes.</p>|Dependent item|nomad.server.runtime.sys_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_runtime_sys_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GC runs|<p>Count of go runtime GC runs.</p>|Dependent item|nomad.server.runtime.total_gc_runs<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_runtime_total_gc_runs)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memberlist events|<p>Count of memberlist events received.</p>|Dependent item|nomad.server.serf.queue.event<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_serf_queue_Event_sum)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memberlist changes|<p>Count of memberlist changes.</p>|Dependent item|nomad.server.serf.queue.intent<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_serf_queue_Intent_sum)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memberlist queries|<p>Count of memberlist queries.</p>|Dependent item|nomad.server.serf.queue.queries<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_serf_queue_Query_sum)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Snapshot index|<p>Current snapshot index.</p>|Dependent item|nomad.server.state.snapshot.index<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_state_snapshotIndex)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Services ready to schedule|<p>Count of service evals ready to be scheduled.</p>|Dependent item|nomad.server.broker.service_ready<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_broker_service_ready)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Services unacknowledged|<p>Count of unacknowledged service evals.</p>|Dependent item|nomad.server.broker.service_unacked<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_broker_service_unacked)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|System evaluations ready to schedule|<p>Count of service evals ready to be scheduled.</p>|Dependent item|nomad.server.broker.system_ready<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_broker_system_ready)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|System evaluations unacknowledged|<p>Count of unacknowledged system evals.</p>|Dependent item|nomad.server.broker.system_unacked<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_broker_system_unacked)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|BoltDB free pages|<p>Number of BoltDB free pages.</p>|Dependent item|nomad.server.raft.boltdb.num_free_pages<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_numFreePages)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|BoltDB pending pages|<p>Number of BoltDB pending pages.</p>|Dependent item|nomad.server.raft.boltdb.num_pending_pages<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_numPendingPages)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|BoltDB free page bytes|<p>Number of free page bytes.</p>|Dependent item|nomad.server.raft.boltdb.free_page_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_freePageBytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|BoltDB freelist bytes|<p>Number of freelist bytes.</p>|Dependent item|nomad.server.raft.boltdb.freelist_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_freelistBytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|BoltDB read transactions, rate|<p>Count of total read transactions.</p>|Dependent item|nomad.server.raft.boltdb.total_read_txn<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_totalReadTxn)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|BoltDB open read transactions|<p>Number of current open read transactions.</p>|Dependent item|nomad.server.raft.boltdb.open_read_txn<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_openReadTxn)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|BoltDB pages in use|<p>Number of pages in use.</p>|Dependent item|nomad.server.raft.boltdb.txstats.page_count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_pageCount)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|BoltDB page allocations, rate|<p>Number of page allocations.</p>|Dependent item|nomad.server.raft.boltdb.txstats.page_alloc<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_pageAlloc)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|BoltDB cursors|<p>Count of total database cursors.</p>|Dependent item|nomad.server.raft.boltdb.txstats.cursor_count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_cursorCount)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|BoltDB nodes, rate|<p>Count of total database nodes.</p>|Dependent item|nomad.server.raft.boltdb.txstats.node_count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_nodeCount)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|BoltDB node dereferences, rate|<p>Count of total database node dereferences.</p>|Dependent item|nomad.server.raft.boltdb.txstats.node_deref<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_nodeDeref)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|BoltDB rebalance operations, rate|<p>Count of total rebalance operations.</p>|Dependent item|nomad.server.raft.boltdb.txstats.rebalance<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_rebalance)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|BoltDB split operations, rate|<p>Count of total split operations.</p>|Dependent item|nomad.server.raft.boltdb.txstats.split<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_split)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|BoltDB spill operations, rate|<p>Count of total spill operations.</p>|Dependent item|nomad.server.raft.boltdb.txstats.spill<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_spill)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|BoltDB write operations, rate|<p>Count of total write operations.</p>|Dependent item|nomad.server.raft.boltdb.txstats.write<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_write)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|BoltDB rebalance time|<p>Sample of rebalance operation times.</p>|Dependent item|nomad.server.raft.boltdb.txstats.rebalance_time<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_rebalanceTime_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|BoltDB spill time|<p>Sample of spill operation times.</p>|Dependent item|nomad.server.raft.boltdb.txstats.spill_time<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_spillTime_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|BoltDB write time|<p>Sample of write operation times.</p>|Dependent item|nomad.server.raft.boltdb.txstats.write_time<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_raft_boltdb_txstats_writeTime_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Service [rpc] state|<p>Current [rpc] service state.</p>|Simple check|net.tcp.service[tcp,,{$NOMAD.SERVER.RPC.PORT}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [serf] state|<p>Current [serf] service state.</p>|Simple check|net.tcp.service[tcp,,{$NOMAD.SERVER.SERF.PORT}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Namespace list time|<p>Time elapsed for Namespace.ListNamespaces.</p>|Dependent item|nomad.server.namespace.list_namespace<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_namespace_list_namespace_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Autopilot state|<p>Current autopilot state.</p>|Dependent item|nomad.server.autopilot.state<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_autopilot_healthy)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Autopilot failure tolerance|<p>The number of redundant healthy servers that can fail without causing an outage.</p>|Dependent item|nomad.server.autopilot.failure_tolerance<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_autopilot_failure_tolerance)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|FSM allocation client update time|<p>Time elapsed to apply AllocClientUpdate raft entry.</p>|Dependent item|nomad.server.alloc_client_update<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_fsm_alloc_client_update_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|FSM apply plan results time|<p>Time elapsed to apply ApplyPlanResults raft entry.</p>|Dependent item|nomad.server.fsm.apply_plan_results<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_fsm_apply_plan_results_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|FSM update evaluation time|<p>Time elapsed to apply UpdateEval raft entry.</p>|Dependent item|nomad.server.fsm.update_eval<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_fsm_update_eval_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|FSM job registration time|<p>Time elapsed to apply RegisterJob raft entry.</p>|Dependent item|nomad.server.fsm.register_job<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(nomad_nomad_fsm_register_job_sum)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Allocation reschedule attempts|<p>Count of attempts to reschedule an allocation.</p>|Dependent item|nomad.server.scheduler.allocs.rescheduled.attempted<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(nomad_scheduler_allocs_reschedule_attempted)`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HashiCorp Nomad Server: Monitoring API connection has failed|<p>Monitoring API connection has failed.<br>Ensure that Nomad API URL and the necessary permissions have been defined correctly, check the service state and network connectivity between Nomad and Zabbix.</p>|`find(/HashiCorp Nomad Server by HTTP/nomad.server.data.api.response,,"like","{$NOMAD.API.RESPONSE.SUCCESS}")=0`|Average|**Manual close**: Yes|
|HashiCorp Nomad Server: Internal stats API connection has failed|<p>Internal stats API connection has failed.<br>Ensure that Nomad API URL and the necessary permissions have been defined correctly, check the service state and network connectivity between Nomad and Zabbix.</p>|`find(/HashiCorp Nomad Server by HTTP/nomad.server.stats.api.response,,"like","{$NOMAD.API.RESPONSE.SUCCESS}")=0`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HashiCorp Nomad Server: Monitoring API connection has failed</li></ul>|
|HashiCorp Nomad Server: Nomad server version has changed|<p>Nomad server version has changed.</p>|`change(/HashiCorp Nomad Server by HTTP/nomad.server.version)<>0`|Info|**Manual close**: Yes|
|HashiCorp Nomad Server: Cluster role has changed|<p>Cluster role has changed.</p>|`change(/HashiCorp Nomad Server by HTTP/nomad.server.raft.cluster_role) <> 0`|Info|**Manual close**: Yes|
|HashiCorp Nomad Server: Current number of open files is too high|<p>Heavy file descriptor usage (i.e., near the process file descriptor limit) indicates a potential file descriptor exhaustion issue.</p>|`min(/HashiCorp Nomad Server by HTTP/nomad.server.process_open_fds,5m)/last(/HashiCorp Nomad Server by HTTP/nomad.server.process_max_fds)*100>{$NOMAD.OPEN.FDS.MAX}`|Warning||
|HashiCorp Nomad Server: Dead jobs found|<p>Jobs with the `Dead` state discovered.<br>Check the {$NOMAD.SERVER.API.SCHEME}://{HOST.IP}:{$NOMAD.SERVER.API.PORT}/v1/jobs URL for the details.</p>|`last(/HashiCorp Nomad Server by HTTP/nomad.server.job_status.dead) > 0 and nodata(/HashiCorp Nomad Server by HTTP/nomad.server.job_status.dead,5m) = 0`|Warning|**Manual close**: Yes|
|HashiCorp Nomad Server: Leader last contact timeout exceeded|<p>The nomad.raft.leader.lastContact metric is a general indicator of Raft latency which can be used to observe how Raft timing is performing and guide infrastructure provisioning.<br>If this number trends upwards, look at CPU, disk IOPs, and network latency. nomad.raft.leader.lastContact should not get too close to the leader lease timeout of 500ms.</p>|`min(/HashiCorp Nomad Server by HTTP/nomad.server.raft.leader.lastContact,5m) >= {$NOMAD.SERVER.LEADER.LATENCY} and nodata(/HashiCorp Nomad Server by HTTP/nomad.server.raft.leader.lastContact,5m) = 0`|Warning||
|HashiCorp Nomad Server: Service [rpc] is down|<p>Cannot establish the connection to [rpc] service port {$NOMAD.SERVER.RPC.PORT}.<br>Check the Nomad state and network connectivity between Nomad and Zabbix.</p>|`last(/HashiCorp Nomad Server by HTTP/net.tcp.service[tcp,,{$NOMAD.SERVER.RPC.PORT}]) = 0`|Average|**Manual close**: Yes|
|HashiCorp Nomad Server: Service [serf] is down|<p>Cannot establish the connection to [serf] service port {$NOMAD.SERVER.SERF.PORT}.<br>Check the Nomad state and network connectivity between Nomad and Zabbix.</p>|`last(/HashiCorp Nomad Server by HTTP/net.tcp.service[tcp,,{$NOMAD.SERVER.SERF.PORT}]) = 0`|Average|**Manual close**: Yes|
|HashiCorp Nomad Server: Autopilot is unhealthy|<p>The autopilot is in unhealthy state. The successful failover probability is extremely low.</p>|`last(/HashiCorp Nomad Server by HTTP/nomad.server.autopilot.state) = 0 and nodata(/HashiCorp Nomad Server by HTTP/nomad.server.autopilot.state,5m) = 0`|Average|**Manual close**: Yes|
|HashiCorp Nomad Server: Autopilot redundancy is low|<p>The autopilot redundancy is low.<br>Cluster crash risk is high due to one more server failure.</p>|`last(/HashiCorp Nomad Server by HTTP/nomad.server.autopilot.failure_tolerance) < {$NOMAD.REDUNDANCY.MIN} and nodata(/HashiCorp Nomad Server by HTTP/nomad.server.autopilot.failure_tolerance,5m) = 0`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

