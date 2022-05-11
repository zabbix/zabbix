
# Microsoft SharePoint by HTTP

## Overview

For Zabbix version: 6.0 and higher  
SharePoint includes a Representational State Transfer (REST) service. Developers can perform read operations from their SharePoint Add-ins, solutions, and client applications, using REST web technologies and standard Open Data Protocol (OData) syntax. Details in
https://docs.microsoft.com/ru-ru/sharepoint/dev/sp-add-ins/get-to-know-the-sharepoint-rest-service?tabs=csom


This template was tested on:

- SharePoint Server, version 2019

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Create a new host.
Define macros according to your Sharepoint web portal.
It is recommended to fill in the values of the filter macros to avoid getting redundant data.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SHAREPOINT.GET_INTERVAL} |<p>-</p> |`1m` |
|{$SHAREPOINT.LLD.FILTER.FULL_PATH.MATCHES} |<p>Filter of discoverable dictionaries by full path.</p> |`^/` |
|{$SHAREPOINT.LLD.FILTER.FULL_PATH.NOT_MATCHES} |<p>Filter to exclude discovered dictionaries by full path.</p> |`CHANGE_IF_NEEDED` |
|{$SHAREPOINT.LLD.FILTER.NAME.MATCHES} |<p>Filter of discoverable dictionaries by name.</p> |`.*` |
|{$SHAREPOINT.LLD.FILTER.NAME.NOT_MATCHES} |<p>Filter to exclude discovered dictionaries by name.</p> |`CHANGE_IF_NEEDED` |
|{$SHAREPOINT.LLD.FILTER.TYPE.MATCHES} |<p>Filter of discoverable types.</p> |`FOLDER` |
|{$SHAREPOINT.LLD.FILTER.TYPE.NOT_MATCHES} |<p>Filter to exclude discovered types.</p> |`CHANGE_IF_NEEDED` |
|{$SHAREPOINT.LLD_INTERVAL} |<p>-</p> |`3h` |
|{$SHAREPOINT.MAX_HEALT_SCORE} |<p>Must be in the range from 0 to 10</p><p>in details: https://docs.microsoft.com/en-us/openspecs/sharepoint_protocols/ms-wsshp/c60ddeb6-4113-4a73-9e97-26b5c3907d33</p> |`5` |
|{$SHAREPOINT.PASSWORD} |<p>-</p> |`` |
|{$SHAREPOINT.ROOT} |<p>-</p> |`/Shared Documents` |
|{$SHAREPOINT.URL} |<p>Portal page URL. For example http://sharepoint.companyname.local/</p> |`` |
|{$SHAREPOINT.USER} |<p>-</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Directory discovery |<p>-</p> |SCRIPT |sharepoint.directory.discovery<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#SHAREPOINT.LLD.NAME} MATCHES_REGEX `{$SHAREPOINT.LLD.FILTER.NAME.MATCHES}`</p><p>- {#SHAREPOINT.LLD.NAME} NOT_MATCHES_REGEX `{$SHAREPOINT.LLD.FILTER.NAME.NOT_MATCHES}`</p><p>- {#SHAREPOINT.LLD.FULL_PATH} MATCHES_REGEX `{$SHAREPOINT.LLD.FILTER.FULL_PATH.MATCHES}`</p><p>- {#SHAREPOINT.LLD.FULL_PATH} NOT_MATCHES_REGEX `{$SHAREPOINT.LLD.FILTER.FULL_PATH.NOT_MATCHES}`</p><p>- {#SHAREPOINT.LLD.TYPE} MATCHES_REGEX `{$SHAREPOINT.LLD.FILTER.TYPE.MATCHES}`</p><p>- {#SHAREPOINT.LLD.TYPE} NOT_MATCHES_REGEX `{$SHAREPOINT.LLD.FILTER.TYPE.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Sharepoint |Sharepoint: Get directory structure: Status |<p>HTTP response (status) code. Indicates whether the HTTP request was successfully completed. Additional information is available in the server log file.</p> |DEPENDENT |sharepoint.get_dir.status<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> DISCARD_VALUE`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Sharepoint |Sharepoint: Get directory structure: Exec time |<p>The time taken to execute the script for obtaining the data structure (in ms). Less is better.</p> |DEPENDENT |sharepoint.get_dir.time<p>**Preprocessing**:</p><p>- JSONPATH: `$.time`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> DISCARD_VALUE`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Sharepoint |Sharepoint: Health score |<p>This item specifies a value between 0 and 10, where 0 represents a low load and a high ability to process requests and 10 represents a high load and that the server is throttling requests to maintain adequate throughput.</p> |HTTP_AGENT |sharepoint.health_score<p>**Preprocessing**:</p><p>- REGEX: `X-SharePointHealthScore\b:\s(\d+) \1`</p><p>- IN_RANGE: `0 10`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Sharepoint |Sharepoint: Size ({#SHAREPOINT.LLD.FULL_PATH}) |<p>Size of:</p><p>{#SHAREPOINT.LLD.FULL_PATH}</p> |DEPENDENT |sharepoint.size["{#SHAREPOINT.LLD.FULL_PATH}"]<p>**Preprocessing**:</p><p>- JSONPATH: `{{#SHAREPOINT.LLD.JSON_PATH}.regsub("(.*)", \1)}.meta.size`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Sharepoint |Sharepoint: Modified ({#SHAREPOINT.LLD.FULL_PATH}) |<p>Date of change:</p><p>{#SHAREPOINT.LLD.FULL_PATH}</p> |DEPENDENT |sharepoint.modified["{#SHAREPOINT.LLD.FULL_PATH}"]<p>**Preprocessing**:</p><p>- JSONPATH: `{{#SHAREPOINT.LLD.JSON_PATH}.regsub("(.*)", \1)}.meta.modified`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Sharepoint |Sharepoint: Created ({#SHAREPOINT.LLD.FULL_PATH}) |<p>Date of creation:</p><p>{#SHAREPOINT.LLD.FULL_PATH}</p> |DEPENDENT |sharepoint.created["{#SHAREPOINT.LLD.FULL_PATH}"]<p>**Preprocessing**:</p><p>- JSONPATH: `{{#SHAREPOINT.LLD.JSON_PATH}.regsub("(.*)", \1)}.meta.created`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Zabbix raw items |Sharepoint: Get directory structure |<p>Used to get directory structure information</p> |SCRIPT |sharepoint.get_dir<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"status":520,"data":{},"time":0}`</p><p>**Expression**:</p>`The text is too long. Please see the template.` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Sharepoint: Error getting directory structure. |<p>Error getting directory structure. Check the Zabbix server log for more details.</p> |`last(/Microsoft SharePoint by HTTP/sharepoint.get_dir.status)<>200` |WARNING | |
|Sharepoint: Server responds slowly to API request |<p>-</p> |`last(/Microsoft SharePoint by HTTP/sharepoint.get_dir.time)>2000` |WARNING | |
|Sharepoint: Bad health score |<p>-</p> |`last(/Microsoft SharePoint by HTTP/sharepoint.health_score)>"{$SHAREPOINT.MAX_HEALT_SCORE}"` |AVERAGE | |
|Sharepoint: Sharepoint object is changed |<p>Updated date of modification of folder / file </p> |`last(/Microsoft SharePoint by HTTP/sharepoint.modified["{#SHAREPOINT.LLD.FULL_PATH}"],#1)<>last(/Microsoft SharePoint by HTTP/sharepoint.modified["{#SHAREPOINT.LLD.FULL_PATH}"],#2)` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

