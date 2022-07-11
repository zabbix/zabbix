
# Travis CI by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Travis CI by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.  



This template was tested on:

- Travis CI, version API V3 2021

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

You must set {$TRAVIS.API.TOKEN} and {$TRAVIS.API.URL} macros.
{$TRAVIS.API.TOKEN} is a Travis API authentication token located in User -> Settings -> API authentication.
{$TRAVIS.API.URL} could be in 2 different variations:
 - for a private project : api.travis-ci.com
 - for an enterprise projects: api.example.com (where you replace example.com with the domain Travis CI is running on)


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TRAVIS.API.TOKEN} |<p>Travis API Token</p> |`` |
|{$TRAVIS.API.URL} |<p>Travis API URL</p> |`api.travis-ci.com` |
|{$TRAVIS.BUILDS.SUCCESS.PERCENT} |<p>Percent of successful builds in the repo (for trigger expression)</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Repos metrics discovery |<p>Metrics for Repos statistics.</p> |DEPENDENT |travis.repos.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Travis |Travis: Get health |<p>Getting home JSON using Travis API.</p> |HTTP_AGENT |travis.get_health<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- JAVASCRIPT: `return JSON.parse(value).config ? 1 : 0`</p> |
|Travis |Travis: Jobs passed |<p>Total count of passed jobs in all repos.</p> |DEPENDENT |travis.jobs.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.jobs.length()`</p> |
|Travis |Travis: Jobs active |<p>Active jobs in all repos.</p> |DEPENDENT |travis.jobs.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.jobs[?(@.state == "started")].length()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Travis |Travis: Jobs in queue |<p>Jobs in queue in all repos.</p> |DEPENDENT |travis.jobs.queue<p>**Preprocessing**:</p><p>- JSONPATH: `$.jobs[?(@.state == "received")].length()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Travis |Travis: Builds |<p>Total count of builds in all repos.</p> |DEPENDENT |travis.builds.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.builds.length()`</p> |
|Travis |Travis: Builds duration |<p>Sum of all builds durations in all repos.</p> |DEPENDENT |travis.builds.duration<p>**Preprocessing**:</p><p>- JSONPATH: `$..duration.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Travis |Travis: Repo [{#SLUG}]: Cache files |<p>Count of cache files in {#SLUG} repo.</p> |DEPENDENT |travis.repo.caches.files[{#SLUG}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.caches.length()`</p> |
|Travis |Travis: Repo [{#SLUG}]: Cache size |<p>Total size of cache files in {#SLUG} repo.</p> |DEPENDENT |travis.repo.caches.size[{#SLUG}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.caches..size.sum()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|Travis |Travis: Repo [{#SLUG}]: Builds passed |<p>Count of all passed builds in {#SLUG} repo.</p> |DEPENDENT |travis.repo.builds.passed[{#SLUG}]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.parse(value).builds.filter(function (e){return e.state == "passed"}).length`</p> |
|Travis |Travis: Repo [{#SLUG}]: Builds failed |<p>Count of all failed builds in {#SLUG} repo.</p> |DEPENDENT |travis.repo.builds.failed[{#SLUG}]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Travis |Travis: Repo [{#SLUG}]: Builds total |<p>Count of total builds in {#SLUG} repo.</p> |DEPENDENT |travis.repo.builds.total[{#SLUG}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.builds.length()`</p> |
|Travis |Travis: Repo [{#SLUG}]: Builds passed, % |<p>Percent of passed builds in {#SLUG} repo.</p> |CALCULATED |travis.repo.builds.passed.pct[{#SLUG}]<p>**Expression**:</p>`last(//travis.repo.builds.passed[{#SLUG}])/last(//travis.repo.builds.total[{#SLUG}])*100` |
|Travis |Travis: Repo [{#SLUG}]: Description |<p>Description of Travis repo (git project description).</p> |DEPENDENT |travis.repo.description[{#SLUG}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.repositories[?(@.slug == "{#SLUG}")].description.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Travis |Travis: Repo [{#SLUG}]: Last build duration |<p>Last build duration in {#SLUG} repo.</p> |DEPENDENT |travis.repo.last_build.duration[{#SLUG}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.builds[0].duration`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Travis |Travis: Repo [{#SLUG}]: Last build state |<p>Last build state in {#SLUG} repo.</p> |DEPENDENT |travis.repo.last_build.state[{#SLUG}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.builds[0].state`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Travis |Travis: Repo [{#SLUG}]: Last build number |<p>Last build number in {#SLUG} repo.</p> |DEPENDENT |travis.repo.last_build.number[{#SLUG}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.builds[0].number`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Travis |Travis: Repo [{#SLUG}]: Last build id |<p>Last build id in {#SLUG} repo.</p> |DEPENDENT |travis.repo.last_build.id[{#SLUG}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.builds[0].id`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |Travis: Get repos |<p>Getting repos using Travis API.</p> |HTTP_AGENT |travis.get_repos |
|Zabbix raw items |Travis: Get builds |<p>Getting builds using Travis API.</p> |HTTP_AGENT |travis.get_builds |
|Zabbix raw items |Travis: Get jobs |<p>Getting jobs using Travis API.</p> |HTTP_AGENT |travis.get_jobs |
|Zabbix raw items |Travis: Repo [{#SLUG}]: Get builds |<p>Getting builds of {#SLUG} using Travis API.</p> |HTTP_AGENT |travis.repo.get_builds[{#SLUG}] |
|Zabbix raw items |Travis: Repo [{#SLUG}]: Get caches |<p>Getting caches of {#SLUG} using Travis API.</p> |HTTP_AGENT |travis.repo.get_caches[{#SLUG}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Travis: Service is unavailable |<p>Travis API is unavailable. Please check if the correct macros are set.</p> |`last(/Travis CI by HTTP/travis.get_health)=0` |HIGH |<p>Manual close: YES</p> |
|Travis: Failed to fetch home page |<p>Zabbix has not received data for items for the last 30 minutes.</p> |`nodata(/Travis CI by HTTP/travis.get_health,30m)=1` |WARNING |<p>Manual close: YES</p> |
|Travis: Repo [{#SLUG}]: Percent of successful builds |<p>Low successful builds rate.</p> |`last(/Travis CI by HTTP/travis.repo.builds.passed.pct[{#SLUG}])<{$TRAVIS.BUILDS.SUCCESS.PERCENT}` |WARNING |<p>Manual close: YES</p> |
|Travis: Repo [{#SLUG}]: Last build status is 'errored' |<p>Last build status is errored.</p> |`find(/Travis CI by HTTP/travis.repo.last_build.state[{#SLUG}],,"like","errored")=1` |WARNING |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

