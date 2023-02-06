# Control-M server by HTTP

## Overview

For Zabbix version: 6.2 and higher.

This template is designed to get metrics from the Control-M server using the Control-M Automation API with HTTP agent.

This template monitors server statistics, discovers jobs and agents using Low Level Discovery.

To use this template, macros `{$API.TOKEN}`, `{$API.URI.ENDPOINT}`, and `{$SERVER.NAME}` need to be set.

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

## Tested versions

This template has been tested on:

- Control-M 9.21.0

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
|{$SERVER.NAME} |<p> The name of the Control-M server. </p>| <set the server name here>|
|{$API.URI.ENDPOINT} |<p> The API endpoint is a URI - for example, `https://monitored.controlm.instance:8443/automation-api`. </p>| <set the api uri endpoint here>|
|{$API.TOKEN} |<p> A token to use for API connections. </p>| <set the token here>|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Control-M: Get Control-M server stats|Gets the statistics of the server.|Http Agent|controlm.server.stats<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Error -> Could Not Get Server Stats.</li></ul>
|Control-M: Get jobs|Gets the status of jobs.|Http Agent|controlm.jobs
|Control-M: Get agents|Gets agents for the server.|Http Agent|controlm.agents
|Control-M: Jobs statistics|Gets the statistics of jobs.|Dependent|controlm.jobs.statistics<p>**Preprocessing**</p><ul><li>Jsonpath: `$.['returned', 'total']`</li></ul>
|Control-M: Jobs returned|Gets the count of returned jobs.|Dependent|controlm.jobs.statistics.returned<p>**Preprocessing**</p><ul><li>Jsonpath: `$.[0]`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Control-M: Jobs total|Gets the count of total jobs.|Dependent|controlm.jobs.statistics.total<p>**Preprocessing**</p><ul><li>Jsonpath: `$.[1]`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Control-M: Server state|Gets the metric of the server state.|Dependent|server.state<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Error -> Could Not Get Server State.</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Control-M: Server message|Gets the metric of the server message.|Dependent|server.message<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Error -> Could Not Get Server Message.</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Control-M: Server version|Gets the metric of the server version.|Dependent|server.version<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Error -> Could Not Get Server Version.</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Control-M: Server is down|The server is down.|`last(/Control-M server by HTTP/Control-M: Server state)=0 or last(/Control-M server by HTTP/Control-M: Server state)=10`|High| - 
|Control-M: Server disconnected|The server is disconnected.|`last(/Control-M server by HTTP/Control-M: Server message,#1)="Disconnected"`|High| - 
|Control-M: Server error|The server has encountered an error.|`last(/Control-M server by HTTP/Control-M: Server message,#1)<>"Connected" and last(/Control-M server by HTTP/Control-M: Server message,#1)<>"Disconnected" and last(/Control-M server by HTTP/Control-M: Server message,#1)<>""`|High| - 
|Control-M: Server version has changed|The server version has changed. Acknowledge (Ack) to close.|`last(/Control-M server by HTTP/Control-M: Server version,#1)<>last(/Control-M server by HTTP/Control-M: Server version,#2) and length(last(/Control-M server by HTTP/Control-M: Server version))>0`|Info| - 

### LLD rule for jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Jobs discovery|Discovers jobs on the server.|Dependent|controlm.jobs.discovery<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Value -> []</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>

### Items for jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Job [{#JOB.ID}]: stats|Gets the statistics of a job.|Dependent|job.stats['{#JOB.ID}']<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Discard_Value</li></ul>
|Job [{#JOB.ID}]: status|Gets the status of a job.|Dependent|job.status['{#JOB.ID}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.status`</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Job [{#JOB.ID}]: number of runs|Gets the number of runs for a job.|Dependent|job.numberOfRuns['{#JOB.ID}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.numberOfRuns`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Job [{#JOB.ID}]: type|Gets the job type.|Dependent|job.type['{#JOB.ID}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.type`</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Job [{#JOB.ID}]: held status|Gets the held status of a job.|Dependent|job.held['{#JOB.ID}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.held`</li><li>Javascript: `The text is too long. Please see the template.`</li></ul>

### Triggers for jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Job [{#JOB.ID}]: status [{ITEM.VALUE}]|The job has encountered an issue.|`last(/Control-M server by HTTP/Job [{#JOB.ID}]: status,#1)=1 or last(/Control-M server by HTTP/Job [{#JOB.ID}]: status,#1)=10`|Warning| - 

### LLD rule for agent discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Agent discovery|Discovers agents on the server.|Dependent|controlm.agent.discovery<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Value -> []</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>

### Items for agent discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Agent [{#AGENT.NAME}]: stats|Gets the statistics of an agent.|Dependent|agent.stats['{#AGENT.NAME}']<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Discard_Value</li></ul>
|Agent [{#AGENT.NAME}]: status|Gets the status of an agent.|Dependent|agent.status['{#AGENT.NAME}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.status`</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Agent [{#AGENT.NAME}]: version|Gets the version number of an agent.|Dependent|agent.version['{#AGENT.NAME}']<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Value -> Unknown</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>

### Triggers for agent discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Agent [{#AGENT.NAME}]: status [{ITEM.VALUE}]|The agent has encountered an issue.|`last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: status,#1)=1 or last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: status,#1)=10`|Average| - 
|Agent [{#AGENT.NAME}}: status disabled|The agent is disabled.|`last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: status,#1)=2 or last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: status,#1)=3`|Info| - 
|Agent [{#AGENT.NAME}]: version has changed|The agent version has changed. Acknowledge (Ack) to close.|`last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: version,#1)<>last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: version,#2)`|Info| - 
|Agent [{#AGENT.NAME}]: unknown version|The agent version is unknown.|`last(/Control-M server by HTTP/Agent [{#AGENT.NAME}]: version,#1)="Unknown"`|Warning| - 

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Control-M enterprise manager by HTTP

## Overview

For Zabbix version: 6.2 and higher.

This template is designed to get metrics from the Control-M Enterprise Manager using the Control-M Automation API with HTTP agent.

This template monitors active Service Level Agreement (SLA) services, discovers Control-M servers using Low Level Discovery and also creates host prototypes for them in conjunction with the `Control-M server by HTTP` template.

To use this template, macros `{$API.TOKEN}` and `{$API.URI.ENDPOINT}` need to be set.

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

## Tested versions

This template has been tested on:

- Control-M 9.21.0

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
|{$API.URI.ENDPOINT} |<p> The API endpoint is a URI - for example, `https://monitored.controlm.instance:8443/automation-api`. </p>| <set the api uri endpoint here>|
|{$API.TOKEN} |<p> A token to use for API connections. </p>| <set the token here>|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Control-M: Get Control-M servers|Gets a list of servers.|Http Agent|controlm.servers
|Control-M: Get SLA services|Gets all the SLA active services.|Http Agent|controlm.services

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|

### LLD rule for server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server discovery|Discovers the Control-M servers.|Dependent|controlm.server.discovery<p>**Preprocessing**</p><ul><li>Discard_Unchanged_Heartbeat: `2h`</li></ul>

### LLD rule for sla services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SLA services discovery|Discovers the SLA services in the Control-M environment.|Dependent|controlm.services.discovery<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Custom_Value -> []</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>

### Items for sla services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: stats|Gets the service statistics.|Dependent|service.stats['{#SERVICE.NAME}','{#SERVICE.JOB}']<p>**Preprocessing**</p><ul><li>Jsonpath</p><p>⛔️On fail: Discard_Value</li><li>Jsonpath</p><p>⛔️On fail: Discard_Value</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status|Gets the service status.|Dependent|service.status['{#SERVICE.NAME}','{#SERVICE.JOB}']<p>**Preprocessing**</p><ul><li>Jsonpath: `$.status`</li><li>Javascript: `The text is too long. Please see the template.`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'executed'|Gets the number of jobs in the state - `executed`.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',executed]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.executed`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitCondition'|Gets the number of jobs in the state - `waitCondition`.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitCondition]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.waitCondition`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitResource'|Gets the number of jobs in the state - `waitResource`.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitResource]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.waitResource`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitHost'|Gets the number of jobs in the state - `waitHost`.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitHost]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.waitHost`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'waitWorkload'|Gets the number of jobs in the state - `waitWorkload`.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',waitWorkload]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.waitWorkload`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'completed'|Gets the number of jobs in the state - `completed`.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',completed]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.completed`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'error'|Gets the number of jobs in the state - `error`.|Dependent|service.jobs.status['{#SERVICE.NAME}','{#SERVICE.JOB}',error]<p>**Preprocessing**</p><ul><li>Jsonpath: `$.statusByJobs.error`</li><li>Discard_Unchanged_Heartbeat: `1h`</li></ul>

### Triggers for sla services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status [{ITEM.VALUE}]|The service has encountered an issue.|`last(/Control-M enterprise manager by HTTP/Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status,#1)=0 or last(/Control-M enterprise manager by HTTP/Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status,#1)=10`|Average| - 
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status [{ITEM.VALUE}]|The service has finished its job late.|`last(/Control-M enterprise manager by HTTP/Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: status,#1)=3`|Warning| - 
|Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs in 'error' state|There are services present which are in the state - `error`.|`last(/Control-M enterprise manager by HTTP/Service [{#SERVICE.NAME}, {#SERVICE.JOB}]: jobs 'error',#1)>0`|Average| - 

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).
