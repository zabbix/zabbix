
# Cloudflare by HTTP

## Overview

This template is designed for the effortless deployment of Cloudflare monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Cloudflare

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1\. Create a host, for example mywebsite.com, for a site in your Cloudflare account.

2\. Link the template to the host.

3\. Customize the values of {$CLOUDFLARE.API.TOKEN}, {$CLOUDFLARE.ZONE_ID} macros.  
    Cloudflare API Tokens are available in your Cloudflare account under My Profile > API Tokens.  
    Zone ID is available in your Cloudflare account under Account Home > Site.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CLOUDFLARE.API.URL}|<p>The URL of Cloudflare API endpoint.</p>|`https://api.cloudflare.com/client/v4`|
|{$CLOUDFLARE.API.TOKEN}|<p>Your Cloudflare API Token.</p>||
|{$CLOUDFLARE.ZONE_ID}|<p>Your Cloudflare Site Zone ID.</p>||
|{$CLOUDFLARE.ERRORS.MAX.WARN}|<p>Maximum responses with errors in %.</p>|`30`|
|{$CLOUDFLARE.CACHED_BANDWIDTH.MIN.WARN}|<p>Minimum of cached bandwidth in %.</p>|`50`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Total bandwidth|<p>The volume of all data.</p>|Dependent item|cloudflare.bandwidth.all<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bandwidth.all`</p></li></ul>|
|Cached bandwidth|<p>The volume of cached data.</p>|Dependent item|cloudflare.bandwidth.cached<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bandwidth.cached`</p></li></ul>|
|Uncached bandwidth|<p>The volume of uncached data.</p>|Dependent item|cloudflare.bandwidth.uncached<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bandwidth.uncached`</p></li></ul>|
|Cache hit ratio of bandwidth|<p>The ratio of the amount cached bandwidth to the bandwidth in percentage.</p>|Dependent item|cloudflare.bandwidth.cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bandwidth.cache_hit_ratio`</p></li></ul>|
|SSL encrypted bandwidth|<p>The volume of encrypted data.</p>|Dependent item|cloudflare.bandwidth.ssl.encrypted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bandwidth.encrypted`</p></li></ul>|
|Unencrypted bandwidth|<p>The volume of unencrypted data.</p>|Dependent item|cloudflare.bandwidth.ssl.unencrypted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bandwidth.unencrypted`</p></li></ul>|
|DNS queries|<p>The amount of all DNS queries.</p>|Dependent item|cloudflare.dns.query.all<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dns.query.all`</p></li></ul>|
|Stale DNS queries|<p>The number of stale DNS queries.</p>|Dependent item|cloudflare.dns.query.stale<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dns.query.stale`</p></li></ul>|
|Uncached DNS queries|<p>The number of uncached DNS queries.</p>|Dependent item|cloudflare.dns.query.uncached<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dns.query.uncached`</p></li></ul>|
|Get data|<p>The JSON with result of Cloudflare API request.</p>|Script|cloudflare.get|
|Total page views|<p>The amount of all pageviews.</p>|Dependent item|cloudflare.pageviews.all<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pageviews.all`</p></li></ul>|
|Total requests|<p>The amount of all requests.</p>|Dependent item|cloudflare.requests.all<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.all`</p></li></ul>|
|Cached requests||Dependent item|cloudflare.requests.cached<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.cached`</p></li></ul>|
|Uncached requests|<p>The number of uncached requests.</p>|Dependent item|cloudflare.requests.uncached<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.uncached`</p></li></ul>|
|Cache hit ratio % over time|<p>The ratio of the amount cached requests to all requests in percentage.</p>|Dependent item|cloudflare.requests.cache_hit_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.cache_hit_ratio`</p></li></ul>|
|Response codes 1xx|<p>The number requests with 1xx response codes.</p>|Dependent item|cloudflare.requests.response_100<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.response_100`</p></li></ul>|
|Response codes 2xx|<p>The number requests with 2xx response codes.</p>|Dependent item|cloudflare.requests.response_200<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.response_200`</p></li></ul>|
|Response codes 3xx|<p>The number requests with 3xx response codes.</p>|Dependent item|cloudflare.requests.response_300<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.response_300`</p></li></ul>|
|Response codes 4xx|<p>The number requests with 4xx response codes.</p>|Dependent item|cloudflare.requests.response_400<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.response_400`</p></li></ul>|
|Response codes 5xx|<p>The number requests with 5xx response codes.</p>|Dependent item|cloudflare.requests.response_500<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.response_500`</p></li></ul>|
|Non-2xx responses ratio|<p>The ratio of the amount requests with non-2xx response codes to all requests in percentage.</p>|Dependent item|cloudflare.requests.others_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.others_ratio`</p></li></ul>|
|2xx responses ratio|<p>The ratio of the amount requests with 2xx response codes to all requests in percentage.</p>|Dependent item|cloudflare.requests.success_ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.success_ratio`</p></li></ul>|
|SSL encrypted requests|<p>The number of encrypted requests.</p>|Dependent item|cloudflare.requests.ssl.encrypted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.encrypted`</p></li></ul>|
|Unencrypted requests|<p>The number of unencrypted requests.</p>|Dependent item|cloudflare.requests.ssl.unencrypted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.unencrypted`</p></li></ul>|
|Total threats|<p>The number of all threats.</p>|Dependent item|cloudflare.threats.all<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.threats.all`</p></li></ul>|
|Unique visitors|<p>The number of all visitors IPs.</p>|Dependent item|cloudflare.uniques.all<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uniques.all`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cloudflare: Cached bandwidth is too low||`max(/Cloudflare by HTTP/cloudflare.bandwidth.cache_hit_ratio,#3) < {$CLOUDFLARE.CACHED_BANDWIDTH.MIN.WARN}`|Warning||
|Cloudflare: Ratio of non-2xx responses is too high|<p>A large number of errors can indicate a malfunction of the site.</p>|`min(/Cloudflare by HTTP/cloudflare.requests.others_ratio,#3) > {$CLOUDFLARE.ERRORS.MAX.WARN}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

