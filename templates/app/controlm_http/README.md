
# Control-M enterprise manager by HTTP

## Overview

The template to monitor Control-M by Zabbix that work without any external scripts.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Control-M 9.21.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template is intended to be used on Control-M Enterprise Manager instances. 

It monitors:
* active SLA services;
* discovers Control-M servers using Low Level Discovery;
* creates host prototypes for discovered servers with the `Control-M server by HTTP` template.

To use this template, you must set macros: **{$API.TOKEN}** and **{$API.URI.ENDPOINT}**. 

To access the API token, use one of the following Control-M interfaces:

> [Control-M WEB user interface](https://documents.bmc.com/supportu/controlm-saas/en-US/Documentation/Creating_an_API_Token.htm);

> [Control-M command line interface tool CTM](https://docs.bmc.com/docs/saas-api/authentication-service-941879068.html).

`{$API.URI.ENDPOINT}` - is the Control-M Automation API endpoint for the API requests, including your server IP, or DNS address, Automation API port and path.

For example, `https://monitored.controlm.instance:8443/automation-api`.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$API.URI.ENDPOINT}|<p>The API endpoint is a URI - for example, `https://monitored.controlm.instance:8443/automation-api`.</p>||
|{$API.TOKEN}|<p>A token to use for API connections.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get Control-M servers|<p>Gets a list of servers.</p>|HTTP agent|controlm.servers|
|Get SLA services|<p>Gets all the SLA active services.</p>|HTTP agent|controlm.services|

### LLD rule Server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server discovery|<p>Discovers the Control-M servers.</p>|Dependent item|controlm.server.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `2h`</p></li></ul>|

### LLD rule SLA services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SLA services discovery|<p>Discovers the SLA services in the Control-M environment.</p>|Dependent item|controlm.services.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.activeServices`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SLA services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: stats|<p>Gets the service statistics.</p>|Dependent item|service.stats['{#SERVICE.NAME}','{#SERVICE.JOB}']<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.activeServices.[?(@.serviceName == '{#SERVICE.NAME}')]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.[?(@.serviceJob == '{#SERVICE.JOB}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status|<p>Gets the service status.</p>|Dependent item|service.status['{#SERVICE.NAME}','{#SERVICE.JOB}']<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'executed'|<p>Gets the number of jobs in the state - `executed`.</p>|Dependent item|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',executed]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statusByJobs.executed`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitCondition'|<p>Gets the number of jobs in the state - `waitCondition`.</p>|Dependent item|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitCondition]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statusByJobs.waitCondition`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitResource'|<p>Gets the number of jobs in the state - `waitResource`.</p>|Dependent item|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitResource]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statusByJobs.waitResource`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitHost'|<p>Gets the number of jobs in the state - `waitHost`.</p>|Dependent item|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitHost]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statusByJobs.waitHost`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitWorkload'|<p>Gets the number of jobs in the state - `waitWorkload`.</p>|Dependent item|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitWorkload]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statusByJobs.waitWorkload`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'completed'|<p>Gets the number of jobs in the state - `completed`.</p>|Dependent item|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',completed]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statusByJobs.completed`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'error'|<p>Gets the number of jobs in the state - `error`.</p>|Dependent item|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',error]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statusByJobs.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for SLA services discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Control-M: Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status [{ITEM.VALUE}]|<p>The service has encountered an issue.</p>|`last(/Control-M enterprise manager by HTTP/service.status['{#SERVICE.NAME}','{#SERVICE.JOB}'],#1)=0 or last(/Control-M enterprise manager by HTTP/service.status['{#SERVICE.NAME}','{#SERVICE.JOB}'],#1)=10`|Average|**Manual close**: Yes|
|Control-M: Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status [{ITEM.VALUE}]|<p>The service has finished its job late.</p>|`last(/Control-M enterprise manager by HTTP/service.status['{#SERVICE.NAME}','{#SERVICE.JOB}'],#1)=3`|Warning|**Manual close**: Yes|
|Control-M: Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs in 'error' state|<p>There are services present which are in the state - `error`.</p>|`last(/Control-M enterprise manager by HTTP/service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',error],#1)>0`|Average||

# Control-M server by HTTP

## Overview

This template is designed to get metrics from the Control-M server using the Control-M Automation API with HTTP agent.

This template monitors server statistics, discovers jobs and agents using Low Level Discovery.

To use this template, macros {$API.TOKEN}, {$API.URI.ENDPOINT}, and {$SERVER.NAME} need to be set.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Control-M 9.21.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template is primarily intended for using in conjunction with the `Control-M enterprise manager by HTTP` template in order to create host prototypes.

It monitors:
* server statistics;
* discovers jobs using Low Level Discovery;
* discovers agents using Low Level Discovery.

However, if you wish to monitor the Control-M server separately with this template, you must set the following macros: **{$API.TOKEN}**, **{$API.URI.ENDPOINT}**, and **{$SERVER.NAME}**. 

To access the `{$API.TOKEN}` macro, use one of the following interfaces:

> [Control-M WEB user interface](https://documents.bmc.com/supportu/controlm-saas/en-US/Documentation/Creating_an_API_Token.htm);

> [Control-M command line interface tool CTM](https://docs.bmc.com/docs/saas-api/authentication-service-941879068.html).

`{$API.URI.ENDPOINT}` - is the Control-M Automation API endpoint for the API requests, including your server IP, or DNS address, the Automation API port and path.

For example, `https://monitored.controlm.instance:8443/automation-api`.

`{$SERVER.NAME}` - is the name of the Control-M server to be monitored.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SERVER.NAME}|<p>The name of the Control-M server.</p>||
|{$API.URI.ENDPOINT}|<p>The API endpoint is a URI - for example, `https://monitored.controlm.instance:8443/automation-api`.</p>||
|{$API.TOKEN}|<p>A token to use for API connections.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get Control-M server stats|<p>Gets the statistics of the server.</p>|HTTP agent|controlm.server.stats<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.name == '{$SERVER.NAME}')].first()`</p><p>⛔️Custom on fail: Set error to: `Could not get server stats.`</p></li></ul>|
|Get jobs|<p>Gets the status of jobs.</p>|HTTP agent|controlm.jobs|
|Get agents|<p>Gets agents for the server.</p>|HTTP agent|controlm.agents|
|Jobs statistics|<p>Gets the statistics of jobs.</p>|Dependent item|controlm.jobs.statistics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['returned', 'total']`</p></li></ul>|
|Jobs returned|<p>Gets the count of returned jobs.</p>|Dependent item|controlm.jobs.statistics.returned<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[0]`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs total|<p>Gets the count of total jobs.</p>|Dependent item|controlm.jobs.statistics.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[1]`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Server state|<p>Gets the metric of the server state.</p>|Dependent item|server.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p><p>⛔️Custom on fail: Set error to: `Could not get server state.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Server message|<p>Gets the metric of the server message.</p>|Dependent item|server.message<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message`</p><p>⛔️Custom on fail: Set error to: `Could not get server message.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Server version|<p>Gets the metric of the server version.</p>|Dependent item|server.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p><p>⛔️Custom on fail: Set error to: `Could not get server version.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Control-M: Server is down|<p>The server is down.</p>|`last(/Control-M server by HTTP/server.state)=0 or last(/Control-M server by HTTP/server.state)=10`|High||
|Control-M: Server disconnected|<p>The server is disconnected.</p>|`last(/Control-M server by HTTP/server.message,#1)="Disconnected"`|High||
|Control-M: Server error|<p>The server has encountered an error.</p>|`last(/Control-M server by HTTP/server.message,#1)<>"Connected" and last(/Control-M server by HTTP/server.message,#1)<>"Disconnected" and last(/Control-M server by HTTP/server.message,#1)<>""`|High||
|Control-M: Server version has changed|<p>The server version has changed. Acknowledge to close the problem manually.</p>|`last(/Control-M server by HTTP/server.version,#1)<>last(/Control-M server by HTTP/server.version,#2) and length(last(/Control-M server by HTTP/server.version))>0`|Info|**Manual close**: Yes|

### LLD rule Jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Jobs discovery|<p>Discovers jobs on the server.</p>|Dependent item|controlm.jobs.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statuses`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Job [{#JOB.ID}]: stats|<p>Gets the statistics of a job.</p>|Dependent item|job.stats['{#JOB.ID}']<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.statuses.[?(@.jobId == '{#JOB.ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Job [{#JOB.ID}]: status|<p>Gets the status of a job.</p>|Dependent item|job.status['{#JOB.ID}']<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Job [{#JOB.ID}]: number of runs|<p>Gets the number of runs for a job.</p>|Dependent item|job.numberOfRuns['{#JOB.ID}']<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numberOfRuns`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Job [{#JOB.ID}]: type|<p>Gets the job type.</p>|Dependent item|job.type['{#JOB.ID}']<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Job [{#JOB.ID}]: held status|<p>Gets the held status of a job.</p>|Dependent item|job.held['{#JOB.ID}']<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.held`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Jobs discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Control-M: Job [{#JOB.ID}]: status [{ITEM.VALUE}]|<p>The job has encountered an issue.</p>|`last(/Control-M server by HTTP/job.status['{#JOB.ID}'],#1)=1 or last(/Control-M server by HTTP/job.status['{#JOB.ID}'],#1)=10`|Warning|**Manual close**: Yes|

### LLD rule Agent discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Agent discovery|<p>Discovers agents on the server.</p>|Dependent item|controlm.agent.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.agents`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Agent discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Agent [{#AGENT.NAME}]: stats|<p>Gets the statistics of an agent.</p>|Dependent item|agent.stats['{#AGENT.NAME}']<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.agents.[?(@.nodeid == '{#AGENT.NAME}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Agent [{#AGENT.NAME}]: status|<p>Gets the status of an agent.</p>|Dependent item|agent.status['{#AGENT.NAME}']<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Agent [{#AGENT.NAME}]: version|<p>Gets the version number of an agent.</p>|Dependent item|agent.version['{#AGENT.NAME}']<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p><p>⛔️Custom on fail: Set value to: `Unknown`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Agent discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Control-M: Agent [{#AGENT.NAME}]: status [{ITEM.VALUE}]|<p>The agent has encountered an issue.</p>|`last(/Control-M server by HTTP/agent.status['{#AGENT.NAME}'],#1)=1 or last(/Control-M server by HTTP/agent.status['{#AGENT.NAME}'],#1)=10`|Average|**Manual close**: Yes|
|Control-M: Agent [{#AGENT.NAME}}: status disabled|<p>The agent is disabled.</p>|`last(/Control-M server by HTTP/agent.status['{#AGENT.NAME}'],#1)=2 or last(/Control-M server by HTTP/agent.status['{#AGENT.NAME}'],#1)=3`|Info|**Manual close**: Yes|
|Control-M: Agent [{#AGENT.NAME}]: version has changed|<p>The agent version has changed. Acknowledge to close the problem manually.</p>|`last(/Control-M server by HTTP/agent.version['{#AGENT.NAME}'],#1)<>last(/Control-M server by HTTP/agent.version['{#AGENT.NAME}'],#2)`|Info|**Manual close**: Yes|
|Control-M: Agent [{#AGENT.NAME}]: unknown version|<p>The agent version is unknown.</p>|`last(/Control-M server by HTTP/agent.version['{#AGENT.NAME}'],#1)="Unknown"`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

