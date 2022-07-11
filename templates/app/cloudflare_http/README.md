
# Cloudflare by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Cloudflare to watch your web traffic and DNS metrics.
It works without any external scripts and uses the Script item.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

1\. Create a host, for example mywebsite.com, for a site in your Cloudflare account.

2\. Link the template to the host.

3\. Customize the values of {$CLOUDFLARE.API.TOKEN}, {$CLOUDFLARE.ZONE_ID} macros.  
    Cloudflare API Tokens are available in your Cloudflare account under My Profile > API Tokens.  
    Zone ID is available in your Cloudflare account under Account Home > Site.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CLOUDFLARE.API.TOKEN} |<p>Your Cloudflare API Token.</p> |`<change>` |
|{$CLOUDFLARE.API.URL} |<p>The URL of Cloudflare API endpoint.</p> |`https://api.cloudflare.com/client/v4` |
|{$CLOUDFLARE.CACHED_BANDWIDTH.MIN.WARN} |<p>Minimum of cached bandwidth in %.</p> |`50` |
|{$CLOUDFLARE.ERRORS.MAX.WARN} |<p>Maximum responses with errors in %.</p> |`30` |
|{$CLOUDFLARE.GET_DATA.TIMEOUT} |<p>Response timeout for Cloudflare API.</p> |`3s` |
|{$CLOUDFLARE.ZONE_ID} |<p>Your Cloudflare Site Zone ID.</p> |`<change>` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|General |Cloudflare: Total bandwidth |<p>The volume of all data.</p> |DEPENDENT |cloudflare.bandwidth.all<p>**Preprocessing**:</p><p>- JSONPATH: `$.bandwidth.all`</p> |
|General |Cloudflare: Cached bandwidth |<p>The volume of cached data.</p> |DEPENDENT |cloudflare.bandwidth.cached<p>**Preprocessing**:</p><p>- JSONPATH: `$.bandwidth.cached`</p> |
|General |Cloudflare: Uncached bandwidth |<p>The volume of uncached data.</p> |DEPENDENT |cloudflare.bandwidth.uncached<p>**Preprocessing**:</p><p>- JSONPATH: `$.bandwidth.uncached`</p> |
|General |Cloudflare: Cache hit ratio of bandwidth |<p>The ratio of the amount cached bandwidth to the bandwidth in percentage.</p> |DEPENDENT |cloudflare.bandwidth.cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.bandwidth.cache_hit_ratio`</p> |
|General |Cloudflare: SSL encrypted bandwidth |<p>The volume of encrypted data.</p> |DEPENDENT |cloudflare.bandwidth.ssl.encrypted<p>**Preprocessing**:</p><p>- JSONPATH: `$.bandwidth.encrypted`</p> |
|General |Cloudflare: Unencrypted bandwidth |<p>The volume of unencrypted data.</p> |DEPENDENT |cloudflare.bandwidth.ssl.unencrypted<p>**Preprocessing**:</p><p>- JSONPATH: `$.bandwidth.unencrypted`</p> |
|General |Cloudflare: DNS queries |<p>The amount of all DNS queries.</p> |DEPENDENT |cloudflare.dns.query.all<p>**Preprocessing**:</p><p>- JSONPATH: `$.dns.query.all`</p> |
|General |Cloudflare: Stale DNS queries |<p>The number of stale DNS queries.</p> |DEPENDENT |cloudflare.dns.query.stale<p>**Preprocessing**:</p><p>- JSONPATH: `$.dns.query.stale`</p> |
|General |Cloudflare: Uncached DNS queries |<p>The number of uncached DNS queries.</p> |DEPENDENT |cloudflare.dns.query.uncached<p>**Preprocessing**:</p><p>- JSONPATH: `$.dns.query.uncached`</p> |
|General |Cloudflare: Total page views |<p>The amount of all pageviews.</p> |DEPENDENT |cloudflare.pageviews.all<p>**Preprocessing**:</p><p>- JSONPATH: `$.pageviews.all`</p> |
|General |Cloudflare: Total requests |<p>The amount of all requests.</p> |DEPENDENT |cloudflare.requests.all<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.all`</p> |
|General |Cloudflare: Cached requests |<p>-</p> |DEPENDENT |cloudflare.requests.cached<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.cached`</p> |
|General |Cloudflare: Uncached requests |<p>The number of uncached requests.</p> |DEPENDENT |cloudflare.requests.uncached<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.uncached`</p> |
|General |Cloudflare: Cache hit ratio % over time |<p>The ratio of the amount cached requests to all requests in percentage.</p> |DEPENDENT |cloudflare.requests.cache_hit_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.cache_hit_ratio`</p> |
|General |Cloudflare: Response codes 1xx |<p>The number requests with 1xx response codes.</p> |DEPENDENT |cloudflare.requests.response_100<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.response_100`</p> |
|General |Cloudflare: Response codes 2xx |<p>The number requests with 2xx response codes.</p> |DEPENDENT |cloudflare.requests.response_200<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.response_200`</p> |
|General |Cloudflare: Response codes 3xx |<p>The number requests with 3xx response codes.</p> |DEPENDENT |cloudflare.requests.response_300<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.response_300`</p> |
|General |Cloudflare: Response codes 4xx |<p>The number requests with 4xx response codes.</p> |DEPENDENT |cloudflare.requests.response_400<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.response_400`</p> |
|General |Cloudflare: Response codes 5xx |<p>The number requests with 5xx response codes.</p> |DEPENDENT |cloudflare.requests.response_500<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.response_500`</p> |
|General |Cloudflare: Non-2xx responses ratio |<p>The ratio of the amount requests with non-2xx response codes to all requests in percentage.</p> |DEPENDENT |cloudflare.requests.others_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.others_ratio`</p> |
|General |Cloudflare: 2xx responses ratio |<p>The ratio of the amount requests with 2xx response codes to all requests in percentage.</p> |DEPENDENT |cloudflare.requests.success_ratio<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.success_ratio`</p> |
|General |Cloudflare: SSL encrypted requests |<p>The number of encrypted requests.</p> |DEPENDENT |cloudflare.requests.ssl.encrypted<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.encrypted`</p> |
|General |Cloudflare: Unencrypted requests |<p>The number of unencrypted requests.</p> |DEPENDENT |cloudflare.requests.ssl.unencrypted<p>**Preprocessing**:</p><p>- JSONPATH: `$.requests.unencrypted`</p> |
|General |Cloudflare: Total threats |<p>The number of all threats.</p> |DEPENDENT |cloudflare.threats.all<p>**Preprocessing**:</p><p>- JSONPATH: `$.threats.all`</p> |
|General |Cloudflare: Unique visitors |<p>The number of all visitors IPs.</p> |DEPENDENT |cloudflare.uniques.all<p>**Preprocessing**:</p><p>- JSONPATH: `$.uniques.all`</p> |
|Zabbix raw items |Cloudflare: Get data |<p>The JSON with result of Cloudflare API request.</p> |SCRIPT |cloudflare.get<p>**Expression**:</p>`The text is too long. Please see the template.` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Cloudflare: Cached bandwidth is too low | |`max(/Cloudflare by HTTP/cloudflare.bandwidth.cache_hit_ratio,#3) < {$CLOUDFLARE.CACHED_BANDWIDTH.MIN.WARN}` |WARNING | |
|Cloudflare: Ratio of non-2xx responses is too high |<p>A large number of errors can indicate a malfunction of the site.</p> |`min(/Cloudflare by HTTP/cloudflare.requests.others_ratio,#3) > {$CLOUDFLARE.ERRORS.MAX.WARN}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

