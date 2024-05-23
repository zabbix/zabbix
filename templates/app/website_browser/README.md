
# Website by Browser

## Overview



## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- ChromeDriver 124.0.6367.207, selenium-server-4.0.0-alpha-6

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Install WebDriver.
For more information, please refer to the [Selenium WebDriver](https://www.selenium.dev/documentation/webdriver/) page.
Run selenium-server.
Add in configuration file WebDriver interface HTTP[S] URL. For example http://localhost:4444

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$WEBSITE.BROWSER}|<p>Browser to be used for data collection.</p>|`chrome`|
|{$WEBSITE.DOMAIN}|<p>The domain name.</p>|`www.example.com`|
|{$WEBSITE.PATH}|<p>The path to resource.</p>||
|{$WEBSITE.SCHEME}|<p>The request scheme, which may be either HTTP or HTTPS.</p>|`https`|
|{$WEBSITE.SCREEN.WIDTH}|<p>Screen size width in pixels, used for screenshot.</p>|`1920`|
|{$WEBSITE.SCREEN.HEIGHT}|<p>Screen size height in pixels, used for screenshot.</p>|`1080`|
|{$WEBSITE.RESOURCE.LOAD.MAX.WARN}|<p>The maximum browser response time expressed in seconds for a trigger expression.</p>|`5`|
|{$WEBSITE.NAVIGATION.LOAD.MAX.WARN}|<p>The maximum browser response time expressed in seconds for a trigger expression.</p>|`5`|
|{$WEBSITE.GET.DATA.INTERVAL}|<p>Update interval for get raw data item.</p>|`0s;m/15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Website {$WEBSITE.DOMAIN} Get data|<p>Returns the JSON with performance counters of the requested website.</p>|Browser|website.get.data<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get metrics check|<p>Check that the performance counters of the requested website data has been received correctly.</p>|Dependent item|website.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Screenshot|<p>Website {$WEBSITE.DOMAIN} screenshot.</p>|Dependent item|website.screenshot<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.screenshot`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation load event time|<p>Measuring of load finished time (loadEventEnd).</p>|Dependent item|website.navigation.load_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.navigation.load_finished`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation response time|<p>Measuring of time spend on the response (responseEnd - responseStart).</p>|Dependent item|website.navigation.response_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.navigation.response_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation request time|<p>Measuring of time spend on the request (responseStart - requestStart).</p>|Dependent item|website.navigation.request_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.navigation.request_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation resource fetch time|<p>Measuring of time spent to fetch the resource (without redirects) (responseEnd - fetchStart).</p>|Dependent item|website.navigation.resource_fetch_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.navigation.resource_fetch_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation service worker processing time|<p>Measuring of sum of time spend on browser's service worker processing (fetchStart - workerStart).</p>|Dependent item|website.navigation.service_worker_processing_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation domContentLoaded time|<p>Measuring of time spent on DOM content loading (domContentLoadedEventEnd - domContentLoadedEventStart).</p>|Dependent item|website.navigation.dom_content_loaded_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation DNS lookup time|<p>Measuring of time spent on DNS lookup (domainLookupEnd - domainLookupStart).</p>|Dependent item|website.navigation.dns_lookup_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.navigation.dns_lookup_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation TCP handshake time|<p>Measuring of time spent on TCP handshake (connectEnd - connectStart).</p>|Dependent item|website.navigation.tcp_handshake_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.navigation.tcp_handshake_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation TLS negotiation time|<p>Measuring of time spent on TLS negotiation (requestStart - secureConnectionStart).</p>|Dependent item|website.navigation.tls_negotiation_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.navigation.tls_negotiation_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation encodedBody size|<p>Measuring of encoded size (encodedBodySize).</p>|Dependent item|website.navigation.encoded_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.navigation.encoded_size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation decodedBody size|<p>Measuring of total size (decodedBodySize).</p>|Dependent item|website.navigation.total_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.navigation.total_size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Navigation transfer size|<p>Measuring of transferred size (transferSize).</p>|Dependent item|website.navigation.transferred_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.navigation.transferred_size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource load event time|<p>Measuring of load finished time (loadEventEnd).</p>|Dependent item|website.resource.load_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.load_finished`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource response time|<p>Measuring of time spend on the response (responseEnd - responseStart).</p>|Dependent item|website.resource.response_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.response_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource request time|<p>Measuring of time spend on the request (responseStart - requestStart).</p>|Dependent item|website.resource.request_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.request_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource fetch time|<p>Measuring of time spent to fetch the resource (without redirects) (responseEnd - fetchStart).</p>|Dependent item|website.resource.fetch_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.resource_fetch_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource service worker processing time|<p>Measuring of sum of time spend on browser's service worker processing (fetchStart - workerStart).</p>|Dependent item|website.resource.service_worker_processing_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource domContentLoaded time|<p>Measuring of time spent on DOM content loading (domContentLoadedEventEnd - domContentLoadedEventStart).</p>|Dependent item|website.resource.dom_content_loaded_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.dom_content_loading_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource DNS lookup time|<p>Measuring of time spent on DNS lookup (domainLookupEnd - domainLookupStart).</p>|Dependent item|website.resource.dns_lookup_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.dns_lookup_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource TCP handshake time|<p>Measuring of time spent on TCP handshake (connectEnd - connectStart).</p>|Dependent item|website.resource.tcp_handshake_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.tcp_handshake_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource TLS negotiation time|<p>Measuring of time spent on TLS negotiation (requestStart - secureConnectionStart).</p>|Dependent item|website.resource.tls_negotiation_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.tls_negotiation_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource encodedBody size|<p>Measuring of encoded size (encodedBodySize).</p>|Dependent item|website.resource.encoded_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.encoded_size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource decodedBody size|<p>Measuring of total size (decodedBodySize).</p>|Dependent item|website.resource.total_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.total_size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Website {$WEBSITE.DOMAIN} Resource transfer size|<p>Measuring of transferred size (transferSize).</p>|Dependent item|website.resource.transferred_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.performance_data.summary.resource.transferred_size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Failed to get metrics data|<p>Failed to get JSON with performance counters of the requested website '{$WEBSITE.DOMAIN}'.</p>|`length(last(/Website by Browser/website.metrics.check))>0`|High||
|Website navigation load event time is too slow||`last(/Website by Browser/website.navigation.load_time)>{$WEBSITE.NAVIGATION.LOAD.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Failed to get metrics data</li></ul>|
|Website resource load event time is too slow||`last(/Website by Browser/website.resource.load_time)>{$WEBSITE.RESOURCE.LOAD.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Failed to get metrics data</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

