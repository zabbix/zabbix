
# OpenRouter traces by OTLP

## Overview

This template is designed to collect OpenRouter trace data. It provides a basic span overview
with health indicators, durations, statuses, errors.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- OpenRouter

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Ensure that OpenRouter account is configured to send traces to your OpenTelemetry collector.
  Each workspace requires separate configuration.
2. Ensure that the `TelemetryProvider` parameter is configured in the Zabbix server configuration file.
3. Apply the template to the host.
4. Adjust macros if needed.

*Note: OpenRouter sends completions traces only.*

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OPENROUTER.FREQUENCY}|<p>The update interval for OpenTelemetry items.</p>|`1m`|
|{$OPENROUTER.LOOPBACK}|<p>The loopback for OpenTelemetry items.</p>|`10m`|
|{$OPENROUTER.TIMESHIFT}|<p>The TimeShift for OpenTelemetry items.</p>|`10s`|
|{$OPENROUTER.SPAN.ERROR.WARN}|<p>Span error rate threshold, in %.</p>|`5`|
|{$OPENROUTER.SPAN.HIT_LIMIT.WARN}|<p>Span hit limit stop reason rate threshold, in %.</p>|`10`|
|{$OPENROUTER.SPAN.BYOK.WARN}|<p>BYOK spans rate threshold, in %.</p>|`25`|
|{$OPENROUTER.SPAN.PERCENTILE.WARN}|<p>80th percentile for duration threshold, in seconds.</p>|`10`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Span: Total count|<p>Total number of spans over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.total<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Span: Error count|<p>Number of spans with status code "Error" over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.status.error<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Span: Error rate|<p>Percentage of error spans.</p>|Calculated|openrouter.span.error.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Span: Stop reason [normal] count|<p>Number of spans normally stopped over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.stop.normal<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Span: Stop reason [hit limit] count|<p>Number of spans stopped because of limit hit over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.stop.hit_limit<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Span: Stop reason [error] count|<p>Number of spans stopped because of the error over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.stop.error<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Span: Hit limit rate|<p>Percentage of spans that hit limit.</p>|Calculated|openrouter.span.hit_limit.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Span: BYOK [false] count|<p>Number of spans without your own key over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.byok.false<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Span: BYOK [true] count|<p>Number of spans with your own key over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.byok.true<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li></ul>|
|Span: BYOK rate|<p>Percentage of BYOK spans.</p>|Calculated|openrouter.span.byok.rate<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Span: Duration, average|<p>Average span duration over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.duration.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.avg`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Span: Duration, minimum|<p>Minimum span duration over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.duration.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.min`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Span: Duration, maximum|<p>Maximum span duration over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.duration.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.max`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Span: Duration, 80th percentile|<p>80th percentile of span durations over the last `{$OPENROUTER.FREQUENCY}`.</p>|Telemetry query|openrouter.span.duration.percentile<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.percentile`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenRouter: High span error rate||`min(/OpenRouter traces by OTLP/openrouter.span.error.rate,5m)>{$OPENROUTER.SPAN.ERROR.WARN}`|Warning||
|OpenRouter: High hit limit stop reason rate||`min(/OpenRouter traces by OTLP/openrouter.span.hit_limit.rate,5m)>{$OPENROUTER.SPAN.HIT_LIMIT.WARN}`|Warning||
|OpenRouter: High BYOK rate||`min(/OpenRouter traces by OTLP/openrouter.span.byok.rate,5m)>{$OPENROUTER.SPAN.BYOK.WARN}`|Warning||
|OpenRouter: High span duration for 80% of spans||`min(/OpenRouter traces by OTLP/openrouter.span.duration.percentile,5m)>{$OPENROUTER.SPAN.PERCENTILE.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

