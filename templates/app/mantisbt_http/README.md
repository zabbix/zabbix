
# Mantis BT by HTTP

## Overview

This template is designed for the effortless deployment of Mantis BT monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- MantisBT 2.22

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Generate the API token in Mantis BT. Use this [manual](https://support.mantishub.com/hc/en-us/articles/215787323-Connecting-to-MantisHub-APIs-using-API-Tokens) for detailed instructions.
2. Change values for the {$MANTIS.URL} and {$MANTIS.TOKEN} macros.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MANTIS.URL}|<p>MantisBT URL.</p>||
|{$MANTIS.TOKEN}|<p>MantisBT Token.</p>||
|{$MANTIS.LLD.FILTER.PROJECTS.MATCHES}|<p>Filter of discoverable projects.</p>|`.*`|
|{$MANTIS.LLD.FILTER.PROJECTS.NOT_MATCHES}|<p>Filter to exclude discovered projects.</p>|`CHANGE_IF_NEEDED`|
|{$MANTIS.HTTP.PROXY}|<p>Proxy for http requests.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mantis BT: Get projects|<p>Get projects from Mantis BT.</p>|HTTP agent|mantisbt.get.projects|

### LLD rule Projects discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Projects discovery|<p>Discovery rule for a Mantis BT projects.</p>|Dependent item|mantisbt.projects.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.projects`</p></li></ul>|

### Item prototypes for Projects discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Project [{#NAME}]: Get issues|<p>Getting project issues.</p>|HTTP agent|mantisbt.get.issues[{#NAME}]|
|Project [{#NAME}]: Total issues|<p>Count of issues in project.</p>|Dependent item|mantis.project.total_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues.length()`</p></li></ul>|
|Project [{#NAME}]: New issues|<p>Count of issues with 'new' status.</p>|Dependent item|mantis.project.status.new_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.status.name=='new')].length()`</p></li></ul>|
|Project [{#NAME}]: Resolved issues|<p>Count of issues with 'resolved' status.</p>|Dependent item|mantis.project.status.resolved_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.status.name=='resolved')].length()`</p></li></ul>|
|Project [{#NAME}]: Closed issues|<p>Count of issues with 'closed' status.</p>|Dependent item|mantis.project.status.closed_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.status.name=='closed')].length()`</p></li></ul>|
|Project [{#NAME}]: Assigned issues|<p>Count of issues with 'assigned' status.</p>|Dependent item|mantis.project.status.assigned_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.status.name=='assigned')].length()`</p></li></ul>|
|Project [{#NAME}]: Feedback issues|<p>Count of issues with 'feedback' status.</p>|Dependent item|mantis.project.status.feedback_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.status.name=='feedback')].length()`</p></li></ul>|
|Project [{#NAME}]: Acknowledged issues|<p>Count of issues with 'acknowledged' status.</p>|Dependent item|mantis.project.status.acknowledged_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.status.name=='acknowledged')].length()`</p></li></ul>|
|Project [{#NAME}]: Confirmed issues|<p>Count of issues with 'confirmed' status.</p>|Dependent item|mantis.project.status.confirmed_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.status.name=='confirmed')].length()`</p></li></ul>|
|Project [{#NAME}]: Open issues|<p>Count of "open" resolution issues.</p>|Dependent item|mantis.project.resolution.open_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.resolution.name=='open')].length()`</p></li></ul>|
|Project [{#NAME}]: Fixed issues|<p>Count of "fixed" resolution issues.</p>|Dependent item|mantis.project.resolution.fixed_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.resolution.name=='fixed')].length()`</p></li></ul>|
|Project [{#NAME}]: Reopened issues|<p>Count of "reopened" resolution issues.</p>|Dependent item|mantis.project.resolution.reopened_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.resolution.name=='reopened')].length()`</p></li></ul>|
|Project [{#NAME}]: Unable to reproduce issues|<p>Count of "unable to reproduce" resolution issues.</p>|Dependent item|mantis.project.resolution.unable_to_reproduce_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Project [{#NAME}]: Not fixable issues|<p>Count of "not fixable" resolution issues.</p>|Dependent item|mantis.project.resolution.not_fixable_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.resolution.name=='not fixable')].length()`</p></li></ul>|
|Project [{#NAME}]: Duplicate issues|<p>Count of "duplicate" resolution issues.</p>|Dependent item|mantis.project.resolution.duplicate_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.resolution.name=='duplicate')].length()`</p></li></ul>|
|Project [{#NAME}]: No change required issues|<p>Count of "no change required" resolution issues.</p>|Dependent item|mantis.project.resolution.no_change_required_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Project [{#NAME}]: Suspended issues|<p>Count of "suspended" resolution issues.</p>|Dependent item|mantis.project.resolution.suspended_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.resolution.name=='suspended')].length()`</p></li></ul>|
|Project [{#NAME}]: Will not fix issues|<p>Count of "wont fix" resolution issues.</p>|Dependent item|mantis.project.resolution.wont_fix_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.resolution.name=='wont fix')].length()`</p></li></ul>|
|Project [{#NAME}]: Feature severity issues|<p>Count of "feature" severity issues.</p>|Dependent item|mantis.project.severity.feature_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.severity.name=='feature')].length()`</p></li></ul>|
|Project [{#NAME}]: Trivial severity issues|<p>Count of "trivial" severity issues.</p>|Dependent item|mantis.project.severity.trivial_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.severity.name=='trivial')].length()`</p></li></ul>|
|Project [{#NAME}]: Text severity issues|<p>Count of "text" severity issues.</p>|Dependent item|mantis.project.severity.text_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.severity.name=='text')].length()`</p></li></ul>|
|Project [{#NAME}]: Tweak severity issues|<p>Count of "tweak" severity issues.</p>|Dependent item|mantis.project.severity.tweak_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.severity.name=='tweak')].length()`</p></li></ul>|
|Project [{#NAME}]: Minor severity issues|<p>Count of "minor" severity issues.</p>|Dependent item|mantis.project.severity.minor_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.severity.name=='minor')].length()`</p></li></ul>|
|Project [{#NAME}]: Major severity issues|<p>Count of "major" severity issues.</p>|Dependent item|mantis.project.severity.major_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.severity.name=='major')].length()`</p></li></ul>|
|Project [{#NAME}]: Crash severity issues|<p>Count of "crash" severity issues.</p>|Dependent item|mantis.project.severity.crash_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.severity.name=='crash')].length()`</p></li></ul>|
|Project [{#NAME}]: Block severity issues|<p>Count of "block" severity issues.</p>|Dependent item|mantis.project.severity.block_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.severity.name=='block')].length()`</p></li></ul>|
|Project [{#NAME}]: None priority issues|<p>Count of "none" priority issues.</p>|Dependent item|mantis.project.priority.none_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.priority.name=='none')].length()`</p></li></ul>|
|Project [{#NAME}]: Low priority issues|<p>Count of "low" priority issues.</p>|Dependent item|mantis.project.priority.low_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.priority.name=='low')].length()`</p></li></ul>|
|Project [{#NAME}]: Normal priority issues|<p>Count of "normal" priority issues.</p>|Dependent item|mantis.project.priority.normal_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.priority.name=='normal')].length()`</p></li></ul>|
|Project [{#NAME}]: High priority issues|<p>Count of "high" priority issues.</p>|Dependent item|mantis.project.priority.high_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.priority.name=='high')].length()`</p></li></ul>|
|Project [{#NAME}]: Urgent priority issues|<p>Count of "urgent" priority issues.</p>|Dependent item|mantis.project.priority.urgent_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.priority.name=='urgent')].length()`</p></li></ul>|
|Project [{#NAME}]: Immediate priority issues|<p>Count of "immediate" priority issues.</p>|Dependent item|mantis.project.priority.immediate_issues[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues[?(@.priority.name=='immediate')].length()`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

