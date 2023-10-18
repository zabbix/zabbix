
# Microsoft SharePoint by HTTP

## Overview

This template is designed for the effortless deployment of Microsoft SharePoint monitoring by Zabbix via HTTP and doesn't require any external scripts.

SharePoint includes a Representational State Transfer (REST) service. Developers can perform read operations from their SharePoint Add-ins, solutions, and client applications, using REST web technologies and standard Open Data Protocol (OData) syntax. Details in
https://docs.microsoft.com/ru-ru/sharepoint/dev/sp-add-ins/get-to-know-the-sharepoint-rest-service?tabs=csom

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- SharePoint Server 2019

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Create a new host.
Define macros according to your Sharepoint web portal.
It is recommended to fill in the values of the filter macros to avoid getting redundant data.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SHAREPOINT.USER}|||
|{$SHAREPOINT.PASSWORD}|||
|{$SHAREPOINT.URL}|<p>Portal page URL. For example http://sharepoint.companyname.local/</p>||
|{$SHAREPOINT.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable dictionaries by name.</p>|`.*`|
|{$SHAREPOINT.LLD.FILTER.FULL_PATH.MATCHES}|<p>Filter of discoverable dictionaries by full path.</p>|`^/`|
|{$SHAREPOINT.LLD.FILTER.TYPE.MATCHES}|<p>Filter of discoverable types.</p>|`FOLDER`|
|{$SHAREPOINT.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discovered dictionaries by name.</p>|`CHANGE_IF_NEEDED`|
|{$SHAREPOINT.LLD.FILTER.FULL_PATH.NOT_MATCHES}|<p>Filter to exclude discovered dictionaries by full path.</p>|`CHANGE_IF_NEEDED`|
|{$SHAREPOINT.LLD.FILTER.TYPE.NOT_MATCHES}|<p>Filter to exclude discovered types.</p>|`CHANGE_IF_NEEDED`|
|{$SHAREPOINT.ROOT}||`/Shared Documents`|
|{$SHAREPOINT.LLD_INTERVAL}||`3h`|
|{$SHAREPOINT.GET_INTERVAL}||`1m`|
|{$SHAREPOINT.MAX_HEALT_SCORE}|<p>Must be in the range from 0 to 10</p><p>in details: https://docs.microsoft.com/en-us/openspecs/sharepoint_protocols/ms-wsshp/c60ddeb6-4113-4a73-9e97-26b5c3907d33</p>|`5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sharepoint: Get directory structure|<p>Used to get directory structure information</p>|Script|sharepoint.get_dir<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"status":520,"data":{},"time":0}`</p></li></ul>|
|Sharepoint: Get directory structure: Status|<p>HTTP response (status) code. Indicates whether the HTTP request was successfully completed. Additional information is available in the server log file.</p>|Dependent item|sharepoint.get_dir.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set error to: `DISCARD_VALUE`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Sharepoint: Get directory structure: Exec time|<p>The time taken to execute the script for obtaining the data structure (in ms). Less is better.</p>|Dependent item|sharepoint.get_dir.time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.time`</p><p>⛔️Custom on fail: Set error to: `DISCARD_VALUE`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Sharepoint: Health score|<p>This item specifies a value between 0 and 10, where 0 represents a low load and a high ability to process requests and 10 represents a high load and that the server is throttling requests to maintain adequate throughput.</p>|HTTP agent|sharepoint.health_score<p>**Preprocessing**</p><ul><li><p>Regular expression: `X-SharePointHealthScore\b:\s(\d+) \1`</p></li><li><p>In range: `0 -> 10`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Sharepoint: Error getting directory structure.|<p>Error getting directory structure. Check the Zabbix server log for more details.</p>|`last(/Microsoft SharePoint by HTTP/sharepoint.get_dir.status)<>200`|Warning|**Manual close**: Yes|
|Sharepoint: Server responds slowly to API request||`last(/Microsoft SharePoint by HTTP/sharepoint.get_dir.time)>2000`|Warning|**Manual close**: Yes|
|Sharepoint: Bad health score||`last(/Microsoft SharePoint by HTTP/sharepoint.health_score)>"{$SHAREPOINT.MAX_HEALT_SCORE}"`|Average||

### LLD rule Directory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Directory discovery||Script|sharepoint.directory.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Directory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sharepoint: Size ({#SHAREPOINT.LLD.FULL_PATH})|<p>Size of:</p><p>{#SHAREPOINT.LLD.FULL_PATH}</p>|Dependent item|sharepoint.size["{#SHAREPOINT.LLD.FULL_PATH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `{{#SHAREPOINT.LLD.JSON_PATH}.regsub("(.*)", \1)}.meta.size`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `24h`</p></li></ul>|
|Sharepoint: Modified ({#SHAREPOINT.LLD.FULL_PATH})|<p>Date of change:</p><p>{#SHAREPOINT.LLD.FULL_PATH}</p>|Dependent item|sharepoint.modified["{#SHAREPOINT.LLD.FULL_PATH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Sharepoint: Created ({#SHAREPOINT.LLD.FULL_PATH})|<p>Date of creation:</p><p>{#SHAREPOINT.LLD.FULL_PATH}</p>|Dependent item|sharepoint.created["{#SHAREPOINT.LLD.FULL_PATH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Directory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Sharepoint: Sharepoint object is changed|<p>Updated date of modification of folder / file</p>|`last(/Microsoft SharePoint by HTTP/sharepoint.modified["{#SHAREPOINT.LLD.FULL_PATH}"],#1)<>last(/Microsoft SharePoint by HTTP/sharepoint.modified["{#SHAREPOINT.LLD.FULL_PATH}"],#2)`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

