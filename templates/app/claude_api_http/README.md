
# Claude API by HTTP

## Overview

This template is designed for the effortless deployment of [`Claude API`](https://platform.claude.com) monitoring by Zabbix via HTTP and doesn't require any external scripts.

Please consult the Claude [`API documentation`](https://platform.claude.com/docs/en/api/overview) for more details.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Claude API

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an admin token on the [`Claude Console`](https://platform.claude.com/settings/admin-keys) page.
2. Enter the admin token into the `API token` field.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CLAUDE.API.URL}|<p>Claude API URL.</p>|`https://api.anthropic.com/v1`|
|{$CLAUDE.API.TOKEN}|<p>Claude API token.</p>||
|{$CLAUDE.API.TIMEOUT}|<p>Response timeout for an API.</p>|`15s`|
|{$CLAUDE.API.INTERVAL}|<p>Update interval for raw items.</p>|`1h`|
|{$CLAUDE.API.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See the documentation at https://www.zabbix.com/documentation/8.0/manual/config/items/itemtypes/http</p>||
|{$CLAUDE.API.MSG.USAGE.BUCKET}|<p>Time period and gathering interval for Claude API message usage report data.</p>|`1m`|
|{$CLAUDE.API.CODE.COST.WARN}|<p>Warning threshold for estimated Claude Code usage cost.</p>|`10`|
|{$CLAUDE.API.MODEL.COST.WARN}|<p>Warning threshold for previous day expenses. Supports macro context for per-workspace threshold tuning.</p>|`10`|
|{$CLAUDE.API.WORKSPACE.COST.WARN}|<p>Warning threshold for previous day expenses. Supports macro context for per-workspace threshold tuning.</p>|`10`|
|{$CLAUDE.API.INVITE.EXPIRE.WARN}|<p>Number of hours before invite expiration.</p>|`24`|
|{$CLAUDE.API.INVITE.STATUS.MATCHES}|<p>Filter to include invites by regex.</p>|`.*`|
|{$CLAUDE.API.INVITE.STATUS.NOT_MATCHES}|<p>Filter to exclude invites by regex.</p>|`accepted\|deleted`|
|{$CLAUDE.API.KEY.STATUS.MATCHES}|<p>Filter to include API keys by regex.</p>|`.*`|
|{$CLAUDE.API.KEY.STATUS.NOT_MATCHES}|<p>Filter to exclude API keys by regex.</p>|`archived`|
|{$CLAUDE.API.WORKSPACE.MATCHES}|<p>Filter to include workspaces by regex.</p>|`.*`|
|{$CLAUDE.API.WORKSPACE.NOT_MATCHES}|<p>Filter to exclude workspaces by regex.</p>|`CHANGE_IF_NEEDED`|
|{$CLAUDE.API.MODEL.MATCHES}|<p>Filter to include model by regex.</p>|`.*`|
|{$CLAUDE.API.MODEL.NOT_MATCHES}|<p>Filter to exclude model by regex.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|API key: Get data|<p>Item for gathering Claude API data about API keys.</p>|Script|claude.keys.get|
|API key: Get data errors|<p>Item for gathering errors from `API key: Get data` item.</p>|Dependent item|claude.keys.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Users: Get data|<p>Item for gathering Claude API data about users.</p>|Script|claude.users.get|
|Users: Get data errors|<p>Item for gathering errors from `Users: Get data` item.</p>|Dependent item|claude.users.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Workspace: Get data|<p>Item for gathering Claude API data about workspaces.</p>|Script|claude.workspaces.get|
|Workspace: Get data errors|<p>Item for gathering errors from `Workspace: Get data` item.</p>|Dependent item|claude.workspaces.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Invite: Get data|<p>Item for gathering Claude API data about invites.</p>|Script|claude.invites.get|
|Invite: Get data errors|<p>Item for gathering errors from `Invite: Get data` item.</p>|Dependent item|claude.invites.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Usage [messages]: Get data|<p>Item for gathering Claude API message usage report.</p>|Script|claude.usage.messages.get|
|Usage [messages]: Get data errors|<p>Item for gathering errors from `Usage [messages]: Get data` item.</p>|Dependent item|claude.usage.messages.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Usage [code]: Get data|<p>Item retrieves previous day aggregated usage metrics for Claude Code users.</p>|Script|claude.usage.code.get|
|Usage [code]: Get data errors|<p>Item for gathering errors from `Usage [code]: Get data` item.</p>|Dependent item|claude.usage.code.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Costs: Get data|<p>Item retrieves previous day aggregated cost report.</p>|Script|claude.costs.get|
|Costs: Get data errors|<p>Item for gathering errors from `Costs: Get data` item.</p>|Dependent item|claude.costs.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Workspace: Total count|<p>Total number of workspaces in your organization.</p>|Dependent item|claude.workspaces.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Limits: Get data|<p>Item retrieves organization limits.</p>|Script|claude.limits.get|
|Limits: Get data errors|<p>Item for gathering errors from `Limits: Get data` item.</p>|Dependent item|claude.limits.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Users: Total count|<p>Total number of users in your organization.</p>|Dependent item|claude.users.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Users: Role [user], count|<p>Number of users with "user" role in your organization.</p>|Dependent item|claude.users.user<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.role == 'user')].length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Users: Role [developer], count|<p>Number of users with "developer" role in your organization.</p>|Dependent item|claude.users.developer<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.role == 'developer')].length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Users: Role [billing], count|<p>Number of users with "billing" role in your organization.</p>|Dependent item|claude.users.billing<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.role == 'billing')].length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Users: Role [admin], count|<p>Number of users with "admin" role in your organization.</p>|Dependent item|claude.users.admin<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.role == 'admin')].length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Users: Role [claude_code_user], count|<p>Number of users with "claude_code_user" role in your organization.</p>|Dependent item|claude.users.claude_code_user<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.role == 'claude_code_user')].length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Claude API: There are errors in the `API key: Get data` item|<p>An error occurred while attempting to retrieve values for the `API key: Get data` item.</p>|`length(last(/Claude API by HTTP/claude.keys.errors))>0`|Warning||
|Claude API: There are errors in the `Users: Get data` item|<p>An error occurred while attempting to retrieve values for the `Users: Get data` item.</p>|`length(last(/Claude API by HTTP/claude.users.errors))>0`|Warning||
|Claude API: There are errors in the `Workspace: Get data` item|<p>An error occurred while attempting to retrieve values for the `Workspace: Get data` item.</p>|`length(last(/Claude API by HTTP/claude.workspaces.errors))>0`|Warning||
|Claude API: There are errors in the `Invite: Get data` item|<p>An error occurred while attempting to retrieve values for the `Invite: Get data` item.</p>|`length(last(/Claude API by HTTP/claude.invites.errors))>0`|Warning||
|Claude API: There are errors in the `Usage [messages]: Get data` item|<p>An error occurred while attempting to retrieve values for the `Usage [messages]: Get data` item.</p>|`length(last(/Claude API by HTTP/claude.usage.messages.errors))>0`|Warning||
|Claude API: There are errors in the `Usage [code]: Get data` item|<p>An error occurred while attempting to retrieve values for the `Usage [code]: Get data` item.</p>|`length(last(/Claude API by HTTP/claude.usage.code.errors))>0`|Warning||
|Claude API: There are errors in the `Costs: Get data` item|<p>An error occurred while attempting to retrieve values for the `Costs: Get data` item.</p>|`length(last(/Claude API by HTTP/claude.costs.errors))>0`|Warning||
|Claude API: There are errors in the `Limits: Get data` item|<p>An error occurred while attempting to retrieve values for the `Limits: Get data` item.</p>|`length(last(/Claude API by HTTP/claude.limits.errors))>0`|Warning||

### LLD rule Workspace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace discovery|<p>Workspace discovery.</p>|Dependent item|claude.workspaces.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Workspace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: Member, count|<p>Number of members in the workspace.</p>|Dependent item|claude.workspaces.members[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == '{#WORKSPACE.ID}')].members[*].length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Messages: Output tokens|<p>The number of output tokens generated.</p>|Dependent item|claude.workspaces.msg.tokens.out[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Messages: Input tokens, uncached|<p>The number of uncached input tokens processed.</p>|Dependent item|claude.workspaces.msg.tokens.in.uncached[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Messages: Input tokens, cached|<p>The number of input tokens read from the cache.</p>|Dependent item|claude.workspaces.msg.tokens.in.cached[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: API key count|<p>Number of API keys.</p>|Dependent item|claude.workspaces.keys.count[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.workspace_id == '{#WORKSPACE.ID}')].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Spent, total|<p>Total expenses for the previous day.</p>|Dependent item|claude.workspaces.costs.total[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.workspace_id == '{#WORKSPACE.ID}')].amount.sum()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Uptime|<p>Workspace uptime - how long ago it was created.</p>|Dependent item|claude.workspaces.uptime[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == '{#WORKSPACE.ID}')].created_at.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Limit: Batch requests|<p>Batch requests limit for `{#WORKSPACE.NAME}` workspace.</p>|Dependent item|claude.workspaces.limit.requests[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Limit: Web search|<p>Web search limit for `{#WORKSPACE.NAME}` workspace.</p>|Dependent item|claude.workspaces.limit.web_search[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Users, count|<p>Number of user role members in the workspace.</p>|Dependent item|claude.workspaces.member_role.user[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Developers, count|<p>Number of developer role members in the workspace.</p>|Dependent item|claude.workspaces.member_role.developer[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Restricted developers, count|<p>Number of restricted developer role members in the workspace.</p>|Dependent item|claude.workspaces.member_role.developer.restricted[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Admins, count|<p>Number of admin role members in the workspace.</p>|Dependent item|claude.workspaces.member_role.admin[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Billing, count|<p>Number of billing role members in the workspace.</p>|Dependent item|claude.workspaces.member_role.billing[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Workspace discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Claude API: Workspace [{#WORKSPACE.NAME}] expenses exceeded the threshold|<p>Total workspace expenses exceeded the threshold in the previous day.</p>|`last(/Claude API by HTTP/claude.workspaces.costs.total[{#WORKSPACE.ID}])>({$CLAUDE.API.WORKSPACE.COST.WARN:"{#WORKSPACE.NAME}"})`|Warning||

### LLD rule Workspace [{#WORKSPACE.NAME}]: Cost model discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: Cost model discovery|<p>Claude costs model discovery.</p>|Dependent item|claude.costs.model.discovery[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.workspace_id == '{#WORKSPACE.ID}')]`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Workspace [{#WORKSPACE.NAME}]: Cost model discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: Model [{#MODEL.NAME}]: Total costs|<p>Total `{#WORKSPACE.NAME}` expenses for `{#MODEL.NAME}`.</p>|Dependent item|claude.costs.model.total[{#WORKSPACE.NAME}, {#MODEL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|

### Trigger prototypes for Workspace [{#WORKSPACE.NAME}]: Cost model discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Claude API: Model {#MODEL.NAME} expenses exceeded the threshold|<p>Model expenses exceeded the threshold in the previous day.</p>|`last(/Claude API by HTTP/claude.costs.model.total[{#WORKSPACE.NAME}, {#MODEL.NAME}])>({$CLAUDE.API.MODEL.COST.WARN:"{#WORKSPACE.NAME}"})`|Warning||

### LLD rule Workspace [{#WORKSPACE.NAME}]: Limits discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: Limits discovery|<p>Limits discovery.</p>|Dependent item|claude.limits.discovery[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Workspace [{#WORKSPACE.NAME}]: Limits discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: Model group [{#GROUP.NAME}]: Limit: Output tokens|<p>`{#GROUP.NAME}` model group output token limit for `{#WORKSPACE.NAME}` workspace.</p>|Dependent item|claude.workspaces.model.limit.token.output[{#WORKSPACE.ID}, {#GROUP.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Model group [{#GROUP.NAME}]: Limit: Input tokens|<p>`{#GROUP.NAME}` model group input token limit for `{#WORKSPACE.NAME}` workspace.</p>|Dependent item|claude.workspaces.model.limit.token.input[{#WORKSPACE.ID}, {#GROUP.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Model group [{#GROUP.NAME}]: Limit: Requests per minute|<p>`{#GROUP.NAME}` model group requests per minute limit for `{#WORKSPACE.NAME}` workspace.</p>|Dependent item|claude.workspaces.model.limit.requests[{#WORKSPACE.ID}, {#GROUP.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Workspace [{#WORKSPACE.NAME}]: API key discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: API key discovery|<p>API keys discovery.</p>|Dependent item|claude.keys.discovery[{#WORKSPACE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.workspace_id == '{#WORKSPACE.ID}')]`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Workspace [{#WORKSPACE.NAME}]: API key discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Status|<p>API key status. Possible values: "active", "inactive", "archived" and "expired".</p>|Dependent item|claude.keys.status[{#WORKSPACE.ID}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == '{#KEY.ID}')].status.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Uptime|<p>API key uptime - how long ago it was created.</p>|Dependent item|claude.keys.uptime[{#WORKSPACE.ID}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == '{#KEY.ID}')].created_at.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code: Commits|<p>Number of git commits created yesterday through Claude Code's commit functionality.</p>|Dependent item|claude.code.commits[{#WORKSPACE.NAME}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code: Lines added|<p>Total number of lines of code added yesterday across all files by Claude Code.</p>|Dependent item|claude.code.lines.added[{#WORKSPACE.NAME}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code: Lines removed|<p>Total number of lines of code removed yesterday across all files by Claude Code.</p>|Dependent item|claude.code.lines.removed[{#WORKSPACE.NAME}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code: Sessions|<p>Number of distinct Claude Code sessions initiated yesterday by this API key.</p>|Dependent item|claude.code.sessions[{#WORKSPACE.NAME}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code: Pull requests|<p>Number of pull requests created yesterday through Claude Code's PR functionality.</p>|Dependent item|claude.code.pull_requests[{#WORKSPACE.NAME}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code: Estimated cost, total|<p>Estimated cost amount for previous day for all models.</p>|Dependent item|claude.code.cost.total[{#WORKSPACE.NAME}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code: Output tokens|<p>Total number of output tokens generated by all models for previous day.</p>|Dependent item|claude.code.token.output.total[{#WORKSPACE.NAME}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code: Input tokens|<p>Total number of input tokens consumed by all models for previous day.</p>|Dependent item|claude.code.token.input.total[{#WORKSPACE.NAME}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Workspace [{#WORKSPACE.NAME}]: API key discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Claude API: API key [{#KEY.NAME}] expired|<p>API key expired.</p>|`last(/Claude API by HTTP/claude.keys.status[{#WORKSPACE.ID}, {#KEY.ID}])=4`|Warning|**Manual close**: Yes|
|Claude API: API key [{#KEY.NAME}] became inactive|<p>API key became inactive.</p>|`last(/Claude API by HTTP/claude.keys.status[{#WORKSPACE.ID}, {#KEY.ID}])=2`|Warning|**Manual close**: Yes|
|Claude API: Code usage estimated costs by {#KEY.NAME} key exceeded the threshold|<p>Code usage estimated costs exceeded the threshold in the previous day.</p>|`last(/Claude API by HTTP/claude.code.cost.total[{#WORKSPACE.NAME}, {#KEY.ID}])>({$CLAUDE.API.CODE.COST.WARN:"{#WORKSPACE.NAME}"})`|Warning||

### LLD rule Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Messages model discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Messages model discovery|<p>Claude messages model discovery.</p>|Dependent item|claude.messages.model.discovery[{#WORKSPACE.ID}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.api_key_id == '{#KEY.ID}')]`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Messages model discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Model [{#MODEL.NAME}]: Messages: Uncached input tokens|<p>The number of uncached input tokens processed for {#MODEL.NAME} model.</p>|Dependent item|claude.messages.model.token.input.uncached[{#WORKSPACE.NAME}, {#KEY.NAME}, {#MODEL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Model [{#MODEL.NAME}]: Messages: Cached input tokens|<p>The number of input tokens read from the cache for {#MODEL.NAME} model.</p>|Dependent item|claude.messages.model.token.input.cached[{#WORKSPACE.NAME}, {#KEY.NAME}, {#MODEL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Model [{#MODEL.NAME}]: Messages: Output tokens|<p>The number of output tokens generated for {#MODEL.NAME} model.</p>|Dependent item|claude.messages.model.token.output[{#WORKSPACE.NAME}, {#KEY.NAME}, {#MODEL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code model discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code model discovery|<p>Claude code model discovery.</p>|Dependent item|claude.code.model.discovery[{#WORKSPACE.ID}, {#KEY.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Code model discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Model [{#MODEL.NAME}]: Code: Input tokens|<p>Total number of input tokens consumed by {#MODEL.NAME} model for previous day.</p>|Dependent item|claude.code.token.input[{#WORKSPACE.NAME}, {#KEY.ID}, {#MODEL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Model [{#MODEL.NAME}]: Code: Output tokens|<p>Total number of output tokens generated by {#MODEL.NAME} model for previous day.</p>|Dependent item|claude.code.token.output[{#WORKSPACE.NAME}, {#KEY.ID}, {#MODEL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Workspace [{#WORKSPACE.NAME}]: Key [{#KEY.NAME}]: Model [{#MODEL.NAME}]: Code: Estimated cost|<p>Estimated cost amount for {#MODEL.NAME} model for previous day.</p>|Dependent item|claude.code.costs[{#WORKSPACE.NAME}, {#KEY.ID}, {#MODEL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|

### LLD rule Invite discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Invite discovery|<p>Invite discovery.</p>|Dependent item|claude.invites.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Invite discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Invite [{#INVITE.EMAIL}]: Status|<p>Invite status.</p><p>Possible values: "accepted", "expired", "deleted", "pending" and "unknown".</p>|Dependent item|claude.invites.status[{#INVITE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == '{#INVITE.ID}')].status.first()`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Invite [{#INVITE.EMAIL}]: Time left|<p>Time left before the invite will expire.</p>|Dependent item|claude.invites.expires[{#INVITE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == '{#INVITE.ID}')].expires_at.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Custom multiplier: `-1`</p></li><li><p>In range: `1 -> `</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Invite discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Claude API: Invite [{#INVITE.EMAIL}] expired|<p>Invite has expired.</p>|`last(/Claude API by HTTP/claude.invites.status[{#INVITE.ID}])=2`|Warning|**Manual close**: Yes|
|Claude API: Invite [{#INVITE.EMAIL}] was accepted|<p>Invite was accepted.</p>|`last(/Claude API by HTTP/claude.invites.status[{#INVITE.ID}])=1`|Info|**Manual close**: Yes|
|Claude API: Invite [{#INVITE.EMAIL}] will expire soon|<p>Invite will expire.</p>|`last(/Claude API by HTTP/claude.invites.expires[{#INVITE.ID}])<({$CLAUDE.API.INVITE.EXPIRE.WARN}*3600)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Claude API: Invite [{#INVITE.EMAIL}] expired</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

