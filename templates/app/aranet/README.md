
# Template App Aranet Cloud

## Overview

For Zabbix version: 5.2  

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/current/manual/config/templates_out_of_the_box/http) for basic instructions.

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ARANET.API.ENDPOINT} |<p>-</p> |`https://aranet.cloud/api` |
|{$ARANET.API.PASSWORD} |<p>-</p> |`` |
|{$ARANET.API.SPACE_NAME} |<p>-</p> |`` |
|{$ARANET.API.USERNAME} |<p>-</p> |`` |
|{$ARANET.LLD.FILTER.SENSOR.MATCHES} |<p>Filter of discoverable sensors</p> |`.+` |
|{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES} |<p>Filter to exclude discoverable sensors</p> |`CHANGE_IF_NEEDED` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Sensors discovery |<p>Discovery for Aranet Cloud sensors</p> |DEPENDENT |aranet.sensor.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var input = JSON.parse(value),     output = []; for (var sensor_idx in input) {     var metrics = input[sensor_idx].metrics;     for (var metric_idx in metrics) {         output.push(             {                 '{#ID}':  input[sensor_idx].id,                 '{#SENSOR}': input[sensor_idx].name,                 '{#METRIC}': metrics[metric_idx].name,                 '{#UNIT}': metrics[metric_idx].unit             }         )     } } return JSON.stringify(output);`</p><p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Aranet |{#METRIC}: {#SENSOR} ({#UNIT}) |<p>-</p> |DEPENDENT |sensor["{#SENSOR}","{#METRIC}","{#ID}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.id == "{#ID}" && @.name == "{#SENSOR}")].metrics[?(@.name == "{#METRIC}")].value.first()`</p> |
|Zabbix_raw_items |Aranet: Get metrics | |INTERNAL |zabbix[uptime]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var Aranet = {     params: {         apiEndpoint: '{$ARANET.API.ENDPOINT}',         username: '{$ARANET.API.USERNAME}',         password: '{$ARANET.API.PASSWORD}',         space_name: '{$ARANET.API.SPACE_NAME}',     },     auth_token: undefined,     space_id: undefined,     request: function (method, query, data) {         var response,             request = new CurlHttpRequest(),             url = (!Aranet.params.apiEndpoint.endsWith('/')                     ? Aranet.params.apiEndpoint + '/'                     : Aranet.params.apiEndpoint)                 + query;         request.AddHeader('Content-Type: application/json');         if (Aranet.auth_token !== null) {             request.AddHeader('Authorization: Bearer ' + Aranet.auth_token);         }         if (typeof data !== 'undefined') {             data = JSON.stringify(data);         }         switch (method) {             case 'get':                 response = request.Get(url, data);                 break;             case 'post':                 response = request.Post(url, data);                 break;             default:                 throw 'Unsupported HTTP request method: ' + method;         }         Zabbix.Log(4, '[ Aranet scraper ] Received response with status code ' + request.Status() + ': ' + response);         if (request.Status() < 200 || request.Status() >= 300) {             var message = 'Request failed with status code ' + request.Status();             message += ': ' + response;             throw message;         }         if (response !== null) {             try {                 response = JSON.parse(response);             }             catch (error) {                 Zabbix.Log(4, '[ Aranet scraper ] Failed to parse response received from Aranet Cloud');                 response = null;             }         }         return {             status: request.Status(),             response: response         };     },     login: function() {         var result,             data = {                 login: Aranet.params.username,                 passw: Aranet.params.password             };         result = Aranet.request('post', 'user/login', data);         if (typeof result.response !== 'object'             || typeof result.response.auth === 'undefined'             || result.status != 200) {             throw 'Cannot login to Aranet Cloud. Check debug log for more information.';         }         Aranet.auth_token = result.response.auth;                  var spaces = result.response.spaces;         for (var key in spaces) {             if (spaces[key] == Aranet.params.space_name) {                 Aranet.space_id = key;                 break;             }         }         return result.response;     },     getMetrics: function() {         var result = Aranet.request('get', 'metrics/' + Aranet.space_id);                  if (typeof result.response !== 'object'             || typeof result.response.data === 'undefined'             || result.status != 200) {             throw 'Cannot get metrics data from Aranet Cloud. Check debug log for more information.';         };         return result.response;     },     getSensors: function() {         var result = Aranet.request('get', 'sensors/' + Aranet.space_id + '?fields=metrics,telemetry,name');                  if (typeof result.response !== 'object'             || typeof result.response.data === 'undefined'             || result.status != 200) {             throw 'Cannot get sensors data from Aranet Cloud. Check debug log for more information.';         };       return result.response;     } } var processed_units = {},     processed_sensors = []; try {     Aranet.login();     var raw_metrics = Aranet.getMetrics(),         raw_sensors = Aranet.getSensors();     var items = raw_metrics.data.items;     for (var item_idx in items) {         var unitName,             units = items[item_idx].units;         for (var unit_idx in units) {             unitName = units[unit_idx].name;             if (units[unit_idx].selected) {                 break             }         }         processed_units[items[item_idx].id] = {             name: items[item_idx].name,             unit: unitName         }     }     var items = raw_sensors.data.items;     for (var item_idx in items) {         var sensor_metrics = [],             metrics = items[item_idx].metrics;             telemetry = items[item_idx].telemetry;         for (var m_idx in metrics) {             unit = processed_units[metrics[m_idx].id];             sensor_metrics.push({                 name: unit.name,                 unit: unit.unit,                 value: metrics[m_idx].v             });         }         for (var t_idx in telemetry) {             unit = processed_units[telemetry[t_idx].id];             sensor_metrics.push({                 name: unit.name,                 unit: unit.unit,                 value: telemetry[t_idx].v             });         }         processed_sensors.push({             id: items[item_idx].id,             name: items[item_idx].name,             metrics: sensor_metrics         })     }          return JSON.stringify(processed_sensors); } catch (error) {     Zabbix.Log(3, '[ Aranet scraper ] ERROR: ' + error);     throw 'Scraping failed: ' + error; }`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com

