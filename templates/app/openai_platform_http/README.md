
# OpenAI Platform by HTTP

## Overview

This template is designed for the effortless deployment of [OpenAI Platform](https://platform.openai.com) monitoring by Zabbix via HTTP and doesn't require any external scripts.

Please consult the OpenAI [API documentation](https://platform.openai.com/docs/api-reference/introduction) for more details.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- OpenAI Platform

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an API token (admin key) on the [OpenAI Platform](https://platform.openai.com/settings/organization/admin-keys) page.
2. Enter the API token into the `{$OPENAI.API.TOKEN}` macro.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OPENAI.API.URL}|<p>OpenAI API URL.</p>|`https://api.openai.com/v1`|
|{$OPENAI.TOKEN}|<p>OpenAI API token.</p>||
|{$OPENAI.TOTAL_EXPENSES.MAX}|<p>Limit on total daily expenses for the entire organization.</p>|`1000`|
|{$OPENAI.EXPENSES.MAX}|<p>Limit on daily expenses per project.</p>|`100`|
|{$OPENAI.PROJECT.NAME.MATCHES}|<p>This macro is used in OpenAI Platform project discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$OPENAI.PROJECT.NAME.NOT_MATCHES}|<p>This macro is used in OpenAI Platform project discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$OPENAI.MODEL.NAME.MATCHES}|<p>This macro is used in OpenAI Platform model discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$OPENAI.MODEL.NAME.NOT_MATCHES}|<p>This macro is used in OpenAI Platform model discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$OPENAI.DATA.TIMEOUT}|<p>Response timeout for an API.</p>|`15s`|
|{$OPENAI.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See the documentation at https://www.zabbix.com/documentation/8.0/manual/config/items/itemtypes/http</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>Item for gathering OpenAI Platform data.</p>|Script|openai.platform_data.get|
|Get data item errors|<p>Item for gathering all the data item errors.</p>|Dependent item|openai.platform_data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get costs|<p>Item for gathering cost data from the OpenAI Platform.</p>|Script|openai.costs_data.get|
|Get cost item errors|<p>Item for gathering all the data item errors.</p>|Dependent item|openai.costs_data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get completions|<p>Item for gathering completion data from the OpenAI Platform.</p>|Script|openai.completions_data.get|
|Get completion item errors|<p>Item for gathering all the data item errors.</p>|Dependent item|openai.completions_data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Version|<p>REST API version used for requests.</p>|Dependent item|openai.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.headers["openai-version"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Total daily expenses|<p>Total amount of expenses for the past day.</p>|Dependent item|openai.expenses.total<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>JSON Path: `$.costs[0].results..amount.value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenAI Platform: There are errors in the 'Get data' metric|<p>An error occurred while attempting to retrieve values for the 'Get data' item.</p>|`length(last(/OpenAI Platform by HTTP/openai.platform_data.errors))>0`|Warning||
|OpenAI Platform: There are errors in the 'Get costs' metric|<p>An error occurred while attempting to retrieve values for the 'Get costs' item.</p>|`length(last(/OpenAI Platform by HTTP/openai.costs_data.errors))>0`|Warning||
|OpenAI Platform: There are errors in the 'Get completions' metric|<p>An error occurred while attempting to retrieve values for the 'Get completions' item.</p>|`length(last(/OpenAI Platform by HTTP/openai.completions_data.errors))>0`|Warning||
|OpenAI Platform: Total daily expenses exceeded|<p>The daily expense limit is higher than the maximum.</p>|`last(/OpenAI Platform by HTTP/openai.expenses.total)>{$OPENAI.TOTAL_EXPENSES.MAX}`|Warning|**Manual close**: Yes|

### LLD rule Project discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Project discovery|<p>Used for discovering projects on the OpenAI Platform.</p>|Dependent item|openai.project.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.projects`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Item prototypes for Project discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#NAME}: Get expense data|<p>Item for gathering expense data for the {#NAME} project.</p>|Dependent item|openai.expenses.get[{#ID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>JSON Path: `$.costs[0].results..[?(@.project_id == '{#ID}')]`</p><p>⛔️Custom on fail: Set value to: `{}`</p></li></ul>|
|{#NAME}: Daily expenses|<p>Amount of expenses for the past day.</p>|Dependent item|openai.expenses.amount[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..amount.value.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME}: Get completion data|<p>Item for gathering completion data for the {#NAME} project.</p>|Dependent item|openai.completions.get[{#ID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JSON Path: `$.completions..results..[?(@.project_id == '{#ID}')]`</p><p>⛔️Custom on fail: Set value to: `{}`</p></li></ul>|
|{#NAME}: Input tokens|<p>Total number of input tokens received by all models in the project.</p>|Dependent item|openai.tokens.input[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..input_tokens.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME}: Output tokens|<p>Total number of output tokens sent by all models in the project.</p>|Dependent item|openai.tokens.output[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..output_tokens.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME}: Input cached tokens|<p>Total number of input cached tokens sent by all models in the project.</p>|Dependent item|openai.tokens.input_cached[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..input_cached_tokens.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME}: Input audio tokens|<p>Total number of input audio tokens sent by all models in the project.</p>|Dependent item|openai.tokens.input_audio[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..input_audio_tokens.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME}: Output audio tokens|<p>Total number of output audio tokens sent by all models in the project.</p>|Dependent item|openai.tokens.output_audio[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..output_audio_tokens.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME}: Model request number|<p>Total number of requests to all models in the project.</p>|Dependent item|openai.requests.number[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..num_model_requests.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Project discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenAI Platform: {#NAME}: Daily expenses exceeded|<p>The daily expense limit for the {#NAME} ({#ID}) project is higher than the maximum.</p>|`last(/OpenAI Platform by HTTP/openai.expenses.amount[{#ID}])>{$OPENAI.EXPENSES.MAX:"{#ID}"}`|Warning|**Manual close**: Yes|

### LLD rule {#NAME}: Model discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#NAME}: Model discovery|<p>Used for discovering models used in the {#NAME} project on the OpenAI Platform.</p>|Dependent item|openai.model.discovery[{#ID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JSON Path: `$.completions..results[?(@.project_id == '{#ID}')]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Item prototypes for {#NAME}: Model discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#NAME} [{#MODEL}]: Get model data|<p>Item for gathering data of the {#MODEL} model.</p>|Dependent item|openai.model.get[{#ID},"{#MODEL}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `{}`</p></li></ul>|
|{#NAME} [{#MODEL}]: Input tokens|<p>Total number of input tokens received by the model {#MODEL} in the {#NAME} project.</p>|Dependent item|openai.tokens.input[{#ID},"{#MODEL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.input_tokens`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME} [{#MODEL}]: Output tokens|<p>Total number of output tokens sent by the model {#MODEL} in the {#NAME} project.</p>|Dependent item|openai.tokens.output[{#ID},"{#MODEL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.output_tokens`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME} [{#MODEL}]: Input cached tokens|<p>Total number of input cached tokens sent by the model {#MODEL} in the {#NAME} project.</p>|Dependent item|openai.tokens.input_cached[{#ID},"{#MODEL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.input_cached_tokens`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME} [{#MODEL}]: Input audio tokens|<p>Total number of input audio tokens sent by the model {#MODEL} in the {#NAME} project.</p>|Dependent item|openai.tokens.input_audio[{#ID},"{#MODEL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.input_audio_tokens`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME} [{#MODEL}]: Output audio tokens|<p>Total number of output audio tokens sent by the model {#MODEL} in the {#NAME} project.</p>|Dependent item|openai.tokens.output_audio[{#ID},"{#MODEL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.output_audio_tokens`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#NAME} [{#MODEL}]: Model request number|<p>Total number of requests to the model {#MODEL} in the {#NAME} project.</p>|Dependent item|openai.requests.number[{#ID},"{#MODEL}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.num_model_requests`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

