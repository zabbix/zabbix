
# Travis CI by HTTP

## Overview

The template to monitor Travis CI by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Travis CI API V3 2021 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

You must set {$TRAVIS.API.TOKEN} and {$TRAVIS.API.URL} macros.
{$TRAVIS.API.TOKEN} is a Travis API authentication token located in User -> Settings -> API authentication.
{$TRAVIS.API.URL} could be in 2 different variations:
 - for a private project : api.travis-ci.com
 - for an enterprise projects: api.example.com (where you replace example.com with the domain Travis CI is running on)


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TRAVIS.API.TOKEN}|<p>Travis API Token</p>||
|{$TRAVIS.API.URL}|<p>Travis API URL</p>|`api.travis-ci.com`|
|{$TRAVIS.BUILDS.SUCCESS.PERCENT}|<p>Percent of successful builds in the repo (for trigger expression)</p>|`80`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Travis: Get repos|<p>Getting repos using Travis API.</p>|HTTP agent|travis.get_repos|
|Travis: Get builds|<p>Getting builds using Travis API.</p>|HTTP agent|travis.get_builds|
|Travis: Get jobs|<p>Getting jobs using Travis API.</p>|HTTP agent|travis.get_jobs|
|Travis: Get health|<p>Getting home JSON using Travis API.</p>|HTTP agent|travis.get_health<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Travis: Jobs passed|<p>Total count of passed jobs in all repos.</p>|Dependent item|travis.jobs.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jobs.length()`</p></li></ul>|
|Travis: Jobs active|<p>Active jobs in all repos.</p>|Dependent item|travis.jobs.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jobs[?(@.state == "started")].length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Travis: Jobs in queue|<p>Jobs in queue in all repos.</p>|Dependent item|travis.jobs.queue<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jobs[?(@.state == "received")].length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Travis: Builds|<p>Total count of builds in all repos.</p>|Dependent item|travis.builds.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.builds.length()`</p></li></ul>|
|Travis: Builds duration|<p>Sum of all builds durations in all repos.</p>|Dependent item|travis.builds.duration<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..duration.sum()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Travis: Service is unavailable|<p>Travis API is unavailable. Please check if the correct macros are set.</p>|`last(/Travis CI by HTTP/travis.get_health)=0`|High|**Manual close**: Yes|
|Travis: Failed to fetch home page|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Travis CI by HTTP/travis.get_health,30m)=1`|Warning|**Manual close**: Yes|

### LLD rule Repos metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Repos metrics discovery|<p>Metrics for Repos statistics.</p>|Dependent item|travis.repos.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Repos metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Travis: Repo [{#SLUG}]: Get builds|<p>Getting builds of {#SLUG} using Travis API.</p>|HTTP agent|travis.repo.get_builds[{#SLUG}]|
|Travis: Repo [{#SLUG}]: Get caches|<p>Getting caches of {#SLUG} using Travis API.</p>|HTTP agent|travis.repo.get_caches[{#SLUG}]|
|Travis: Repo [{#SLUG}]: Cache files|<p>Count of cache files in {#SLUG} repo.</p>|Dependent item|travis.repo.caches.files[{#SLUG}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.caches.length()`</p></li></ul>|
|Travis: Repo [{#SLUG}]: Cache size|<p>Total size of cache files in {#SLUG} repo.</p>|Dependent item|travis.repo.caches.size[{#SLUG}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.caches..size.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Travis: Repo [{#SLUG}]: Builds passed|<p>Count of all passed builds in {#SLUG} repo.</p>|Dependent item|travis.repo.builds.passed[{#SLUG}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Travis: Repo [{#SLUG}]: Builds failed|<p>Count of all failed builds in {#SLUG} repo.</p>|Dependent item|travis.repo.builds.failed[{#SLUG}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Travis: Repo [{#SLUG}]: Builds total|<p>Count of total builds in {#SLUG} repo.</p>|Dependent item|travis.repo.builds.total[{#SLUG}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.builds.length()`</p></li></ul>|
|Travis: Repo [{#SLUG}]: Builds passed, %|<p>Percent of passed builds in {#SLUG} repo.</p>|Calculated|travis.repo.builds.passed.pct[{#SLUG}]|
|Travis: Repo [{#SLUG}]: Description|<p>Description of Travis repo (git project description).</p>|Dependent item|travis.repo.description[{#SLUG}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repositories[?(@.slug == "{#SLUG}")].description.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Travis: Repo [{#SLUG}]: Last build duration|<p>Last build duration in {#SLUG} repo.</p>|Dependent item|travis.repo.last_build.duration[{#SLUG}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.builds[0].duration`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Travis: Repo [{#SLUG}]: Last build state|<p>Last build state in {#SLUG} repo.</p>|Dependent item|travis.repo.last_build.state[{#SLUG}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.builds[0].state`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Travis: Repo [{#SLUG}]: Last build number|<p>Last build number in {#SLUG} repo.</p>|Dependent item|travis.repo.last_build.number[{#SLUG}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.builds[0].number`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Travis: Repo [{#SLUG}]: Last build id|<p>Last build id in {#SLUG} repo.</p>|Dependent item|travis.repo.last_build.id[{#SLUG}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.builds[0].id`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Repos metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Travis: Repo [{#SLUG}]: Percent of successful builds|<p>Low successful builds rate.</p>|`last(/Travis CI by HTTP/travis.repo.builds.passed.pct[{#SLUG}])<{$TRAVIS.BUILDS.SUCCESS.PERCENT}`|Warning|**Manual close**: Yes|
|Travis: Repo [{#SLUG}]: Last build status is 'errored'|<p>Last build status is errored.</p>|`find(/Travis CI by HTTP/travis.repo.last_build.state[{#SLUG}],,"like","errored")=1`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

