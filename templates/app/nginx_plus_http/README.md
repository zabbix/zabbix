
# Template App Nginx Plus HTTP

## Overview

For Zabbix version: 4.4  

## Setup


## Zabbix configuration


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NGINX_API_URL}|<p>-</p>|`http://demo.nginx.com/api/3`|

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Nginx Plus server zones discovery|<p>Discover NginxHTTP virtual servers</p>|DEPENDENT|nginx.plus.get_server_zones.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `//parsing NGINX plus output like in footer: output = Object.keys(JSON.parse(value)).map(function(zone){     return {"{#NGINX_ZONE}": zone} }) return JSON.stringify({"data": output}) /* http://demo.nginx.com/api/3/http/server_zones {   "hg.nginx.org": {     "processing": 0,     "requests": 175276,     "responses": {       "1xx": 0,       "2xx": 162948,       "3xx": 10117,       "4xx": 2125,       "5xx": 8,       "total": 175198     },     "discarded": 78,     "received": 50484208,     "sent": 7356417338   },   "trac.nginx.org": {     "processing": 7,     "requests": 448613,     "responses": {       "1xx": 0,       "2xx": 305562,       "3xx": 87065,       "4xx": 23136,       "5xx": 5127,       "total": 420890     },     "discarded": 27716,     "received": 137307886,     "sent": 3989556941   },   "lxr.nginx.org": {     "processing": 0,     "requests": 48743,     "responses": {       "1xx": 0,       "2xx": 47132,       "3xx": 97,       "4xx": 792,       "5xx": 719,       "total": 48740     },     "discarded": 3,     "received": 14502895,     "sent": 6756762274   } } */ `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p>|

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Nginx|Nginx: Get server zones|<p>Display information about HTTP virtual servers</p>|HTTP_AGENT|nginx.plus.get_server_zones|
|Nginx|{#NGINX_ZONE}: Discarded|<p>-</p>|DEPENDENT|nginx.plus.discarded[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].discarded`</p>|
|Nginx|{#NGINX_ZONE}: Processing|<p>-</p>|DEPENDENT|nginx.plus.processing[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].processing`</p>|
|Nginx|{#NGINX_ZONE}: Received|<p>-</p>|DEPENDENT|nginx.plus.received[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].received`</p>|
|Nginx|{#NGINX_ZONE}: Requests|<p>-</p>|DEPENDENT|nginx.plus.requests[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].requests`</p>|
|Nginx|{#NGINX_ZONE}: Responses 1xx|<p>-</p>|DEPENDENT|nginx.plus.responses.1xx[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].responses.1xx`</p>|
|Nginx|{#NGINX_ZONE}: Responses 2xx|<p>-</p>|DEPENDENT|nginx.plus.responses.2xx[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].responses.2xx`</p>|
|Nginx|{#NGINX_ZONE}: Responses 3xx|<p>-</p>|DEPENDENT|nginx.plus.responses.3xx[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].responses.3xx`</p>|
|Nginx|{#NGINX_ZONE}: Responses 4xx|<p>-</p>|DEPENDENT|nginx.plus.responses.4xx[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].responses.4xx`</p>|
|Nginx|{#NGINX_ZONE}: Responses 5xx|<p>-</p>|DEPENDENT|nginx.plus.responses.5xx[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].responses.5xx`</p>|
|Nginx|{#NGINX_ZONE}: Responses total|<p>-</p>|DEPENDENT|nginx.plus.responses.total[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].responses.total`</p>|
|Nginx|{#NGINX_ZONE}: Sent|<p>-</p>|DEPENDENT|nginx.plus.sent[{#NGINX_ZONE}]<p>**Preprocessing**:</p><p>- JSONPATH: `$["{#NGINX_ZONE}"].sent`</p>|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com

