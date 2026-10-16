
# Generic OpenTelemetry by OTLP

## Overview

This template collects OpenTelemetry (OTLP) telemetry — traces, metrics and logs — via Zabbix's native APM query items (Telemetry query), without external scripts
or a Zabbix proxy script layer. It works with any OTLP-instrumented application or with an OpenTelemetry Collector in front of it.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- OpenTelemetry Collector

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Point your application's OpenTelemetry SDK (or an OpenTelemetry Collector in front of it) at your Zabbix server/proxy's OTLP endpoint.
2. Apply this template to a host representing the instrumented application or service.
3. Adjust the interval macros if the defaults don't match how often your
  source actually emits data.
4. Adjust thresholds macros.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OTLP.FREQUENCY}|<p>The update interval for OpenTelemetry items. Could be used with context.</p>|`1m`|
|{$OTLP.LOOPBACK}|<p>The loopback for OpenTelemetry items. Could be used with context.</p>|`10m`|
|{$OTLP.TIMESHIFT}|<p>The TimeShift for OpenTelemetry items. Could be used with context.</p>|`10s`|
|{$OTLP.GRANULARITY}|<p>The granularity for OpenTelemetry items. Could be used with context.</p>|`1m`|
|{$SPAN.ERROR.WARN}|<p>Span error rate threshold, in %.</p>|`5`|
|{$LOG.SEVERITY.FATAL}|<p>Threshold for `Fatal` severity entries in logs.</p>|`0`|
|{$LOG.SEVERITY.ERROR}|<p>Threshold for `Error` severity entries in logs.</p>|`5`|
|{$LOG.SEVERITY.WARN}|<p>Threshold for `Warning` severity entries in logs.</p>|`10`|
|{$SPAN.OK.DURATION.WARN}|<p>Threshold for `Ok` span duration, in seconds.</p>|`0.5`|
|{$SPAN.UNSET.DURATION.WARN}|<p>Threshold for `Unset` span duration, in seconds.</p>|`1`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get logs|<p>Collect OpenTelemetry log counts grouped by severity.</p>|Telemetry query|otlp.logs.get|
|Span: Error count|<p>Number of spans with status code "Error".</p>|Telemetry query|otlp.span.status.error<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Span: Error duration|<p>Average duration of spans with status code "Error".</p>|Telemetry query|otlp.span.status.error.duration<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.avg`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Span: Ok count|<p>Number of spans with status code "Ok".</p>|Telemetry query|otlp.span.status.ok<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Span: Ok duration|<p>Average duration of spans with status code "Ok".</p>|Telemetry query|otlp.span.status.ok.duration<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.avg`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|Span: Unset count|<p>Number of spans with status code "Unset".</p>|Telemetry query|otlp.span.status.unset<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Span: Unset duration|<p>Average duration of spans with status code "Unset".</p>|Telemetry query|otlp.span.status.unset.duration<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.avg`</p></li><li><p>Custom multiplier: `1.0E-9`</p></li></ul>|
|System: Uptime|<p>System uptime.</p>|Telemetry query|otlp.metrics.system.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.columns.avg`</p></li></ul>|
|Metrics: Count|<p>Amount of incoming metrics over the {$OTLP.FREQUENCY:metrics}.</p>|Telemetry query|otlp.metrics.count<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Metrics: Gauge count|<p>Amount of incoming gauge metrics over the {$OTLP.FREQUENCY:gauge}.</p>|Telemetry query|otlp.metrics_gauge.count<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Metrics: Histogram count|<p>Amount of incoming histogram metrics over the {$OTLP.FREQUENCY:histogram}.</p>|Telemetry query|otlp.metrics_histogram.count<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches "^No value$"`</p><p>⛔️Custom on fail: Set value to: `{"columns":{"count":0}}`</p></li><li><p>JSON Path: `$.columns.count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Logs: Info entries count|<p>Number of `Info` severity entries in logs.</p>|Dependent item|otlp.logs.info<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Logs: Warning entries count|<p>Number of `Warning` severity entries in logs.</p>|Dependent item|otlp.logs.warn<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Logs: Error entries count|<p>Number of `Error` severity entries in logs.</p>|Dependent item|otlp.logs.average<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Logs: Fatal entries count|<p>Number of `Fatal` severity entries in logs.</p>|Dependent item|otlp.logs.high<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Span: Error percentage|<p>Percentage of error spans.</p>|Calculated|otlp.span.error.percentage<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenTelemetry: Span duration is too big||`min(/Generic OpenTelemetry by OTLP/otlp.span.status.ok.duration,5m)>{$SPAN.OK.DURATION.WARN}`|Warning||
|OpenTelemetry: Unset span duration is too big||`min(/Generic OpenTelemetry by OTLP/otlp.span.status.unset.duration,5m)>{$SPAN.UNSET.DURATION.WARN}`|Warning||
|OpenTelemetry: Warning severity level entries in logs||`last(/Generic OpenTelemetry by OTLP/otlp.logs.warn)>{$LOG.SEVERITY.WARN}`|Warning||
|OpenTelemetry: Error severity level entries in logs||`last(/Generic OpenTelemetry by OTLP/otlp.logs.average)>{$LOG.SEVERITY.ERROR}`|Average||
|OpenTelemetry: Fatal severity level entries in logs||`last(/Generic OpenTelemetry by OTLP/otlp.logs.high)>{$LOG.SEVERITY.FATAL}`|High||
|OpenTelemetry: High span error rate||`last(/Generic OpenTelemetry by OTLP/otlp.span.error.percentage)>{$SPAN.ERROR.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

