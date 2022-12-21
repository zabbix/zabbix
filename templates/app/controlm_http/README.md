
# Control-M server by HTTP

## Overview

For Zabbix version: 6.0 and higher
Get metrics from the Control-M server using Control-M Automation API with HTTP agent.

This template monitors server stats, discovers jobs and agents using Low Level Discovery.

To use this template macros {$API.TOKEN}, {$API.URI.ENDPOINT} and {$SERVER.NAME} need to be set. For more information refer to the template documentation.


This template was tested on:
- Control-M 9.21.0

## Setup

This template is primarily intended to be used by the ```Control-M enterprise manager by HTTP``` template for host prototype creation. It monitors: 
* server statistics;
* discovers jobs using Low Level Discovery;
* discovers agents using Low Level Discovery.

However, if you wish to monitor a Control-M server separately with this template, you must set **{$API.TOKEN}**, **{$API.URI.ENDPOINT}** and **{$SERVER.NAME}** macros. 

```{$API.TOKEN}``` can be accessed either using [Control-M WEB user interface](https://documents.bmc.com/supportu/controlm-saas/en-US/Documentation/Creating_an_API_Token.htm) or [Control-M command line interface tool CTM](https://docs.bmc.com/docs/saas-api/authentication-service-941879068.html).

```{$API.URI.ENDPOINT}``` is the Control-M Automation API endpoint for the API requests, including your server IP or DNS address, Automation API port and path, for example, ```https://monitored.controlm.instance:8443/automation-api```.

```{$SERVER.NAME}``` is the name of the Control-M server to monitor.


## Links

- Forum - https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/
## Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SERVER.NAME} |<p> Control-M server name. </p>| <set the server name here>|
|{$API.URI.ENDPOINT} |<p> e.g. https://monitored.controlm.instance:8443/automation-api </p>| <set the api uri endpoint here>|
|{$API.TOKEN} |<p> Token to use for API connections. </p>| <set the token here>|
## Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Control-M: Get Control-M server stats|Get server {#SERVER.NAME} stats.|Http Agent|controlm.server.stats<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Error -> Could Not Get Server Stats.</li></ul>
|Control-M: Get jobs|Get status of jobs.|Http Agent|controlm.jobs
|Control-M: Get agents|Get agents for server.|Http Agent|controlm.agents
|Control-M: Jobs statistics|Get jobs statistics.|Dependent|controlm.jobs.statistics<p>**Preprocessing**</p><ul><li>Jsonpath: `$.['returned', 'total']`</li></ul>
|Control-M: Jobs returned|Get returned jobs count.|Dependent|controlm.jobs.statistics.returned<p>**Preprocessing**</p><ul><li>Jsonpath: `$.[0]`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Control-M: Jobs total|Get total jobs count.|Dependent|controlm.jobs.statistics.total<p>**Preprocessing**</p><ul><li>Jsonpath: `$.[1]`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Control-M: Server state|Get server state metric.|Dependent|server.state<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Error -> Could Not Get Server State.</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Control-M: Server message|Get server message metric.|Dependent|server.message<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Error -> Could Not Get Server Message.</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Control-M: Server version|Get server version metric.|Dependent|server.version<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Error -> Could Not Get Server Version.</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Control-M: Server is down|-|last(/Control-M server by HTTP/Control-M: Server state)=0 or last(/Control-M server by HTTP/Control-M: Server state)=10|High| - 
|Control-M: Server disconnected|-|last(/Control-M server by HTTP/Control-M: Server message,#1)="Disconnected"|High| - 
|Control-M: Server error|-|last(/Control-M server by HTTP/Control-M: Server message,#1)<>"Connected" and last(/Control-M server by HTTP/Control-M: Server message,#1)<>"Disconnected" and last(/Control-M server by HTTP/Control-M: Server message,#1)<>""|High| - 
|Control-M: Server version has changed|-|last(/Control-M server by HTTP/Control-M: Server version,#1)<>last(/Control-M server by HTTP/Control-M: Server version,#2)|Info| - 
## LLD rule Jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Jobs discovery|Discovers jobs on server.|Dependent|controlm.jobs.discovery<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Discard_Value</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
### Items for Jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Job [{#JOB.ID}]: stats|Get job statistics.|Dependent|job.stats['{#JOB.ID}']<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Error -> Could Not Find Job Data.</li></ul>
|Job [{#JOB.ID}]: status|Get job status.|Dependent|job.status['{#JOB.ID}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.status`</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Job [{#JOB.ID}]: number of runs|Get number of runs for a job.|Dependent|job.numberOfRuns['{#JOB.ID}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.numberOfRuns`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Job [{#JOB.ID}]: type|Get job type.|Dependent|job.type['{#JOB.ID}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.type`</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Job [{#JOB.ID}]: held status|Get held status of a job.|Dependent|job.held['{#JOB.ID}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.held`</li><li>Javascript: `The text is too long. Please see the template.`</li></ul>
### Triggers for Jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Job [{#JOB.ID}]: status [{ITEM.VALUE}]|-|last(/Control-M server by HTTP/Job [{#JOB.ID}]: status,#1)=1 or last(/Control-M server by HTTP/Job [{#JOB.ID}]: status,#1)=10|Warning| - 
## LLD rule Agent discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Agent discovery|Discovers agents on server.|Dependent|controlm.agent.discovery<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Discard_Value</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
### Items for Agent discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Agent [{#AGENT.NAME}]: stats|Get agent statistics.|Dependent|agent.stats['{#AGENT.NAME}']<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Error -> Could Not Get Agent Data.</li></ul>
|Agent [{#AGENT.NAME}]: status|Get agent status.|Dependent|agent.status['{#AGENT.NAME}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.status`</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Agent [{#AGENT.NAME}]: version|Get agent version.|Dependent|agent.version['{#AGENT.NAME}']<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Value -> Unknown</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
### Triggers for Agent discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Agent [{#AGENT.NAME}]: status [{ITEM.VALUE}]|-|last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: status,#1)=1 or last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: status,#1)=10|Average| - 
|Agent [{#AGENT.NAME}}: status [{ITEM.VALUE}]|-|last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: status,#1)=2 or last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: status,#1)=3|Info| - 
|Agent [{#AGENT.NAME}]: version has changed|-|last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: version,#1)<>last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: version,#2)|Info| - 

# Control-M enterprise manager by HTTP

## Overview

For Zabbix version: 6.0 and higher
Get metrics from the Control-M Enterprise Manager using Control-M Automation API with HTTP agent.

This template monitors active SLA services, discovers Control-M servers using Low Level Discovery and creates host prototypes for them with 'Control-M server by HTTP' template.

To use this template macros {$API.TOKEN} and {$API.URI.ENDPOINT} need to be set. For more information refer to the template documentation.


This template was tested on:
- Control-M 9.21.0

## Setup

This template is intended to be used on Control-M Enterprise Manager instances. It monitors:
* active SLA services;
* discovers Control-M servers using Low Level Discovery;
* creates host prototypes for discovered servers with ```Control-M server by HTTP``` template.

You must set the **{$API.TOKEN}** and **{$API.URI.ENDPOINT}** macros. 

```{$API.TOKEN}``` can be accessed either using [Control-M WEB user interface](https://documents.bmc.com/supportu/controlm-saas/en-US/Documentation/Creating_an_API_Token.htm) or [Control-M command line interface tool CTM](https://docs.bmc.com/docs/saas-api/authentication-service-941879068.html).

```{$API.URI.ENDPOINT}``` is the Control-M Automation API endpoint for the API requests, including your server IP or DNS address, Automation API port and path, for example, ```https://monitored.controlm.instance:8443/automation-api```.


## Links

- Forum - https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/
## Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$API.URI.ENDPOINT} |<p> e.g. https://monitored.controlm.instance:8443/automation-api </p>| <set the api uri endpoint here>|
|{$API.TOKEN} |<p> Token to use for API connections. </p>| <set the token here>|
## Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Control-M: Get Control-M servers|Get servers.|Http Agent|controlm.servers<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Discard_Value</li></ul>
|Control-M: Get SLA services|Get all Service Level Agreement (SLA) active services.|Http Agent|controlm.services
## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
## LLD rule Server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server discovery|Discovers Control-M servers.|Dependent|controlm.server.discovery<p>**Preprocessing**</p><ul><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `2h`</li></ul>
## LLD rule SLA services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SLA services discovery|Discovers SLA services in Control-M environment.|Dependent|controlm.services.discovery<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Discard_Value</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
### Items for SLA services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: stats|Get service statistics.|Dependent|service.stats['{#SERVICE.NAME}','{#SERVICE.JOB}']<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Discard_Value</li><li>Jsonpath: `$.[?(@.serviceJob == '{#SERVICE.JOB}')].first()`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status|Get service status.|Dependent|service.status['{#SERVICE.NAME}','{#SERVICE.JOB}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.status`</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'executed'|Get number of jobs in state 'executed'.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',executed]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.executed`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitCondition'|Get number of jobs in state 'waitCondition'.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitCondition]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.waitCondition`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitResource'|Get number of jobs in state 'waitResource'.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitResource]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.waitResource`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitHost'|Get number of jobs in state 'waitHost'.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitHost]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.waitHost`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitWorkload'|Get number of jobs in state 'waitWorkload'.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitWorkload]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.waitWorkload`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'completed'|Get number of jobs in state 'completed'.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',completed]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.completed`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'error'|Get number of jobs in state 'error'.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',error]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.error`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
### Triggers for SLA services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status [{ITEM.VALUE}]|-|last(/Control-M enterprise manager by HTTP/Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status,#1)=0 or last(/Control-M enterprise manager by HTTP/Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status,#1)=10|Average| - 
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status [{ITEM.VALUE}]|-|last(/Control-M enterprise manager by HTTP/Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status,#1)=3|Warning| - 
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs in 'error' state|-|last(/Control-M enterprise manager by HTTP/Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'error',#1)>0|Average| - 