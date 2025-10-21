
# GitHub repository by HTTP

## Overview

This template is designed for the effortless deployment of GitHub repository monitoring by Zabbix via GitHub REST API and doesn't require any external scripts.

For more details about GitHub REST API, refer to the [official documentation](https://docs.github.com/en/rest?apiVersion=2022-11-28).

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- GitHub API version 2022-11-28

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

GitHub limits the number of REST API requests that you can make within a specific amount of time, which also depends on whether you are authenticated or not, the plan, and the token type used. Many REST API endpoints require authentication or return additional information if you are authenticated. Additionally, you can make more requests per hour when you are authenticated.

Additional information is available in the official documentation:
- [Regarding authentication](https://docs.github.com/en/rest/authentication/authenticating-to-the-rest-api?apiVersion=2022-11-28#about-authentication)
- [Rate limits for the REST API](https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api?apiVersion=2022-11-28)

1. **Create an access token for monitoring**

One of the simplest ways to send authenticated requests is to use a [personal access token](https://docs.github.com/en/rest/authentication/authenticating-to-the-rest-api?apiVersion=2022-11-28#authenticating-with-a-personal-access-token) - either a classic or a fine-grained one.

**Classic personal access token**

You can create a new classic personal access token by following the [instructions in the official documentation](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#creating-a-personal-access-token-classic).

For public repositories, no additional permission scopes are required. For monitoring to work on private repositories, the `repo` scope must be set to have full control of private repositories.

Additional information about OAuth scopes is available in the [official documentation](https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/scopes-for-oauth-apps#available-scopes).

Note that authenticated users must have admin access to the repository and the `repo` scope must be set to get information about [self-hosted runners](https://docs.github.com/en/rest/actions/self-hosted-runners?apiVersion=2022-11-28#list-self-hosted-runners-for-a-repository).

**Fine-grained personal access token**

Alternatively, you can use a [fine-grained personal access token](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#creating-a-fine-grained-personal-access-token).

In order to use fine-grained tokens to monitor organization-owned repositories, [organizations must opt in to fine-grained personal access tokens and set up a personal access token policy](https://docs.github.com/en/organizations/managing-programmatic-access-to-your-organization/setting-a-personal-access-token-policy-for-your-organization).

The fine-grained token needs to have the following permissions set to provide access to the repository resources:
- "Actions" repository permissions (read);
- "Administration" repository permissions (read);
- "Contents" repository permissions (read);
- "Issues" repository permissions (read);
- "Metadata" repository permissions (read);
- "Pull requests" repository permissions (read).

2. **Set the access token** that you've created in step 1 in the `{$GITHUB.API.TOKEN}` macro
3. **Change the API URL** in the `{$GITHUB.API.URL}` macro if needed (for self-hosted installations)
4. **Set the repository owner name** in the `{$GITHUB.REPO.OWNER}` macro
5. **Set the repository name** in the `{$GITHUB.REPO.NAME}` macro
6. **Set the LLD rule filters** if needed (you may want to use them to stay within rate limits as on large repositories, LLD rules may generate a lot of script items):
- Filter repository branches by name: `{$GITHUB.BRANCH.NAME.MATCHES}`, `{$GITHUB.BRANCH.NAME.NOT_MATCHES}`;
- Filter repository workflows by name: `{$GITHUB.WORKFLOW.NAME.MATCHES}`, `{$GITHUB.WORKFLOW.NAME.NOT_MATCHES}`;
- Filter repository workflows by state: `{$GITHUB.WORKFLOW.STATE.MATCHES}`, `{$GITHUB.WORKFLOW.STATE.NOT_MATCHES}`;
- Filter self-hosted runners by name: `{$GITHUB.RUNNER.NAME.MATCHES}`, `{$GITHUB.RUNNER.NAME.NOT_MATCHES}`;
- Filter self-hosted runners by OS: `{$GITHUB.RUNNER.OS.MATCHES}`, `{$GITHUB.RUNNER.OS.NOT_MATCHES}`.

Note: Update intervals and timeouts for script items can be changed individually via `{$GITHUB.INTERVAL}` and `{$GITHUB.TIMEOUT}` macros with context. Depending on the repository being monitored, it can be adjusted if needed (if you are exceeding rate limits, you can increase update intervals for some script items to stay within per hour request limits). But be aware that it may also affect the triggers (check whether the item is used in triggers and adjust thresholds and/or evaluation periods if needed).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GITHUB.API.URL}|<p>Set the API URL here.</p>|`https://api.github.com/`|
|{$GITHUB.USER_AGENT}|<p>The user agent that is used in headers for HTTP requests.</p>|`Zabbix/8.0`|
|{$GITHUB.API_VERSION}|<p>The API version that is used in headers for HTTP requests.</p>|`2022-11-28`|
|{$GITHUB.REPO.OWNER}|<p>Set the repository owner here.</p>||
|{$GITHUB.REPO.NAME}|<p>Set the repository name here.</p>||
|{$GITHUB.API.TOKEN}|<p>Set the access token here.</p>||
|{$GITHUB.INTERVAL}|<p>The update interval for the script items that retrieve data from the API. Can be used with context if needed (check the context values in relevant items).</p>|`1m`|
|{$GITHUB.INTERVAL:regex:"get_(tags\|releases\|issues)_count"}|<p>The update interval for the script items that retrieve the number of tags, releases, issues, and pull requests (total, open, closed).</p>|`1h`|
|{$GITHUB.INTERVAL:"get_repo"}|<p>The update interval for the script item that retrieves the repository information.</p>|`15m`|
|{$GITHUB.INTERVAL:"get_(branches\|workflows)"}|<p>The update interval for the script items that retrieve the branches and workflows. Used only for related metric discovery.</p>|`1h`|
|{$GITHUB.INTERVAL:"get_runners"}|<p>The update interval for the script item that retrieves the information about self-hosted runners.</p>|`15m`|
|{$GITHUB.INTERVAL:regex:"get_last_run:.+"}|<p>The update interval for the script items that retrieve the information about the last workflow run results.</p>|`15m`|
|{$GITHUB.INTERVAL:regex:"get_commits_count:.+"}|<p>The update interval for the script items that retrieve the commits count in discovered branches.</p>|`1h`|
|{$GITHUB.TIMEOUT}|<p>The timeout threshold for the script items that retrieve data from the API. Can be used with context if needed (check the context values in relevant items).</p>|`15s`|
|{$GITHUB.HTTP_PROXY}|<p>The HTTP proxy for script items (set if needed). If the macro is empty, then no proxy is used.</p>||
|{$GITHUB.RESULTS_PER_PAGE}|<p>The number of results to fetch per page. Can be used with context and adjusted if needed (check the context values in script parameters of relevant items).</p>|`100`|
|{$GITHUB.WORKFLOW.NAME.MATCHES}|<p>The repository workflow name regex filter to use in workflow-related metric discovery - for including.</p>|`.+`|
|{$GITHUB.WORKFLOW.NAME.NOT_MATCHES}|<p>The repository workflow name regex filter to use in workflow-related metric discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$GITHUB.WORKFLOW.STATE.MATCHES}|<p>The repository workflow state regex filter to use in workflow-related metric discovery - for including.</p>|`active`|
|{$GITHUB.WORKFLOW.STATE.NOT_MATCHES}|<p>The repository workflow state regex filter to use in workflow-related metric discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$GITHUB.BRANCH.NAME.MATCHES}|<p>The repository branch name regex filter to use in branch-related metric discovery - for including.</p>|`.+`|
|{$GITHUB.BRANCH.NAME.NOT_MATCHES}|<p>The repository branch name regex filter to use in branch-related metric discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$GITHUB.RUNNER.NAME.MATCHES}|<p>The repository self-hosted runner name regex filter to use in discovering metrics related to the self-hosted runner - for including.</p>|`.+`|
|{$GITHUB.RUNNER.NAME.NOT_MATCHES}|<p>The repository self-hosted runner name regex filter to use in discovering metrics related to the self-hosted runner - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$GITHUB.RUNNER.OS.MATCHES}|<p>The repository self-hosted runner OS regex filter to use in discovering metrics related to the self-hosted runner - for including.</p>|`.+`|
|{$GITHUB.RUNNER.OS.NOT_MATCHES}|<p>The repository self-hosted runner OS regex filter to use in discovering metrics related to the self-hosted runner - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$GITHUB.REQUESTS.UTIL.WARN}|<p>The threshold percentage of utilized API requests in a Warning trigger expression.</p>|`80`|
|{$GITHUB.REQUESTS.UTIL.HIGH}|<p>The threshold percentage of utilized API requests in a High trigger expression.</p>|`90`|
|{$GITHUB.WORKFLOW.STATUS.QUEUED.THRESH}|<p>The time threshold used in the trigger of a workflow run that has been in the queue for too long. Can be used with context if needed.</p>|`1h`|
|{$GITHUB.WORKFLOW.STATUS.IN_PROGRESS.THRESH}|<p>The time threshold used in the trigger of a workflow run that has been in the queue for too long. Can be used with context if needed.</p>|`24h`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get self-hosted runners|<p>Get the self-hosted runners of the repository.</p><p></p><p>Note that admin access to the repository is required to use this endpoint:</p><p>https://docs.github.com/en/rest/actions/self-hosted-runners?apiVersion=2022-11-28#list-self-hosted-runners-for-a-repository</p>|Script|github.repo.runners.get<p>**Preprocessing**</p><ul><li><p>Check for error using a regular expression: `API rate limit exceeded<br>\0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get self-hosted runner check|<p>Carry out a self-hosted runners data collection check.</p>|Dependent item|github.repo.runners.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Number of releases|<p>The number of releases in the repository. Note that this number also includes draft releases.</p><p></p><p>Information about endpoint:</p><p>https://docs.github.com/en/rest/releases/releases?apiVersion=2022-11-28#list-releases</p>|Script|github.repo.releases.count<p>**Preprocessing**</p><ul><li><p>Check for error using a regular expression: `API rate limit exceeded<br>\0`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Number of tags|<p>The number of tags in the repository.</p><p></p><p>Information about endpoint:</p><p>https://docs.github.com/en/rest/repos/repos?apiVersion=2022-11-28#list-repository-tags</p>|Script|github.repo.tags.count<p>**Preprocessing**</p><ul><li><p>Check for error using a regular expression: `API rate limit exceeded<br>\0`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Get issue count|<p>Get the count of issues and pull requests in the repository (total, open, closed).</p><p></p><p>Information about endpoint for issues:</p><p>https://docs.github.com/en/rest/issues/issues?apiVersion=2022-11-28#list-repository-issues</p><p></p><p>Information about endpoint for pull requests:</p><p>https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28#list-pull-requests</p>|Script|github.repo.issues.get<p>**Preprocessing**</p><ul><li><p>Check for error using a regular expression: `API rate limit exceeded<br>\0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Number of issues|<p>The total number of issues in the repository.</p>|Dependent item|github.repo.issues.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues.total`</p></li></ul>|
|Number of open issues|<p>The number of open issues in the repository.</p>|Dependent item|github.repo.issues.open<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues.open`</p></li></ul>|
|Number of closed issues|<p>The number of closed issues in the repository.</p>|Dependent item|github.repo.issues.closed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issues.closed`</p></li></ul>|
|Number of PRs|<p>The total number of pull requests in the repository.</p>|Dependent item|github.repo.pr.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pr.total`</p></li></ul>|
|Number of open PRs|<p>The number of open pull requests in the repository.</p>|Dependent item|github.repo.pr.open<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pr.open`</p></li></ul>|
|Number of closed PRs|<p>The number of closed pull requests in the repository.</p>|Dependent item|github.repo.pr.closed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pr.closed`</p></li></ul>|
|Request limit|<p>API request limit.</p><p></p><p>Information about request limits in GitHub REST API documentation:</p><p>https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api?apiVersion=2022-11-28</p>|Dependent item|github.repo.requests.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.headers['x-ratelimit-limit']`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Requests used|<p>The number of used API requests.</p><p></p><p>Information about request limits in GitHub REST API documentation:</p><p>https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api?apiVersion=2022-11-28</p>|Dependent item|github.repo.requests.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.headers['x-ratelimit-used']`</p></li></ul>|
|Request limit utilization, in %|<p>The calculated utilization of the API request limit in %.</p><p></p><p>Information about request limits in GitHub REST API documentation:</p><p>https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api?apiVersion=2022-11-28</p>|Dependent item|github.repo.requests.util<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get repository|<p>Get the general repository information. If the repository is not a fork, the community profile metrics are also retrieved.</p><p></p><p>Information about endpoint:</p><p>https://docs.github.com/en/rest/repos/repos?apiVersion=2022-11-28#get-a-repository</p><p></p><p>Information about community profile metrics:</p><p>https://docs.github.com/en/rest/metrics/community?apiVersion=2022-11-28#get-community-profile-metrics</p>|Script|github.repo.repository.get|
|Get repository data check|<p>Data collection check.</p>|Dependent item|github.repo.repository.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Repository is a fork|<p>Indicates whether the repository is a fork.</p>|Dependent item|github.repo.repository.is_fork<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data..fork.first()`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Repository size|<p>The size of the repository.</p>|Dependent item|github.repo.repository.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data..size.first()`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Repository stargazers|<p>The number of GitHub users who have starred the repository.</p>|Dependent item|github.repo.repository.stargazers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data..stargazers_count.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Repository watchers|<p>The number of GitHub users who are subscribed to the repository.</p>|Dependent item|github.repo.repository.watchers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data..subscribers_count.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Repository forks|<p>The number of repository forks.</p>|Dependent item|github.repo.repository.forks.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data..forks_count.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get workflows|<p>Get the repository workflows.</p><p></p><p>Information about endpoint:</p><p>https://docs.github.com/en/rest/actions/workflows?apiVersion=2022-11-28#list-repository-workflows</p>|Script|github.repo.workflows.get<p>**Preprocessing**</p><ul><li><p>Check for error using a regular expression: `API rate limit exceeded<br>\0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get branches|<p>Get the repository branches.</p><p></p><p>Information about endpoint:</p><p>https://docs.github.com/en/rest/branches/branches?apiVersion=2022-11-28#list-branches</p>|Script|github.repo.branches.get<p>**Preprocessing**</p><ul><li><p>Check for error using a regular expression: `API rate limit exceeded<br>\0`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GitHub: No access to repository self-hosted runners|<p>Admin access to the repository is required to use this endpoint:<br>https://docs.github.com/en/rest/actions/self-hosted-runners?apiVersion=2022-11-28#list-self-hosted-runners-for-a-repository</p>|`find(/GitHub repository by HTTP/github.repo.runners.get.check,,"iregexp","Must have admin rights to Repository")=1`|Average||
|GitHub: The total number of issues has increased|<p>The total number of issues has increased which means that either a new issue (or multiple) has been opened.</p>|`last(/GitHub repository by HTTP/github.repo.issues.total)>last(/GitHub repository by HTTP/github.repo.issues.total,#2)`|Warning||
|GitHub: The total number of PRs has increased|<p>The total number of pull requests has increased which means that either a new pull request (or multiple) has been opened.</p>|`last(/GitHub repository by HTTP/github.repo.pr.total)>last(/GitHub repository by HTTP/github.repo.pr.total,#2)`|Info||
|GitHub: API request limit utilization is high|<p>The API request limit utilization is high. It can be lowered by increasing the update intervals for script items (by setting up higher values in corresponding context macros).<br><br>The trigger will be resolved automatically if the limit usage drops 5% below the trigger threshold.<br><br>Information about request limits in GitHub REST API documentation:<br>https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api?apiVersion=2022-11-28</p>|`max(/GitHub repository by HTTP/github.repo.requests.util,1h)>{$GITHUB.REQUESTS.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>GitHub: API request limit utilization is very high</li></ul>|
|GitHub: API request limit utilization is very high|<p>The API request limit utilization is very high. It can be lowered by increasing the update intervals for script items (by setting up higher values in corresponding context macros).<br><br>The trigger will be resolved automatically if the limit usage drops 5% below the trigger threshold.<br><br>Information about request limits in GitHub REST API documentation:<br>https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api?apiVersion=2022-11-28</p>|`max(/GitHub repository by HTTP/github.repo.requests.util,1h)>{$GITHUB.REQUESTS.UTIL.HIGH}`|Average||
|GitHub: There are errors in requests to API|<p>Errors have been received in response to API requests. Check the latest values for details.</p>|`length(last(/GitHub repository by HTTP/github.repo.repository.get.check))>0`|Average||

### LLD rule Workflow discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workflow discovery|<p>Discovers repository workflows. By default, only the active workflows are discovered.</p><p></p><p>Information about endpoint:</p><p>https://docs.github.com/en/rest/actions/workflows?apiVersion=2022-11-28#list-repository-workflows</p>|Dependent item|github.repo.workflows.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|

### Item prototypes for Workflow discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Workflow [{#WORKFLOW_NAME}]: Get last run|<p>Get the data about the last workflow run.</p><p></p><p>Information about endpoint:</p><p>https://docs.github.com/en/rest/actions/workflow-runs?apiVersion=2022-11-28#list-workflow-runs-for-a-workflow</p>|Script|github.repo.workflows.last_run.get[{#WORKFLOW_NAME}]<p>**Preprocessing**</p><ul><li><p>Check for error using a regular expression: `API rate limit exceeded<br>\0`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$.data.first()`</p></li></ul>|
|Workflow [{#WORKFLOW_NAME}]: Last run status|<p>The status of the last workflow run. Possible values:</p><p></p><p>0 - queued</p><p>1 - in_progress</p><p>2 - completed</p><p>10 - unknown</p>|Dependent item|github.repo.workflows.last_run.status[{#WORKFLOW_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Workflow [{#WORKFLOW_NAME}]: Last run conclusion|<p>The conclusion of the last workflow run. Possible values:</p><p></p><p>0 - success</p><p>1 - failure</p><p>2 - neutral</p><p>3 - cancelled</p><p>4 - skipped</p><p>5 - timed_out</p><p>6 - action_required</p><p>10 - unknown</p>|Dependent item|github.repo.workflows.last_run.conclusion[{#WORKFLOW_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conclusion`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Workflow [{#WORKFLOW_NAME}]: Last run start date|<p>The date when the last workflow run was started.</p>|Dependent item|github.repo.workflows.last_run.start_date[{#WORKFLOW_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.run_started_at`</p></li><li><p>JavaScript: `return Math.floor(new Date(value) / 1000);`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Workflow [{#WORKFLOW_NAME}]: Last run update date|<p>The date when the last workflow run was updated.</p>|Dependent item|github.repo.workflows.last_run.update_date[{#WORKFLOW_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.updated_at`</p></li><li><p>JavaScript: `return Math.floor(new Date(value) / 1000);`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Workflow [{#WORKFLOW_NAME}]: Last run duration|<p>The duration of the last workflow run.</p>|Dependent item|github.repo.workflows.last_run.duration[{#WORKFLOW_NAME}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Workflow discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GitHub: Workflow [{#WORKFLOW_NAME}]: The workflow has been in the queue for too long|<p>The last workflow run has been in the "queued" status for too long. This may mean that it has failed to be assigned to a runner. The default threshold is provided as an example and can be adjusted for relevant workflows with context macros.</p>|`last(/GitHub repository by HTTP/github.repo.workflows.last_run.status[{#WORKFLOW_NAME}])=0 and changecount(/GitHub repository by HTTP/github.repo.workflows.last_run.status[{#WORKFLOW_NAME}],{$GITHUB.WORKFLOW.STATUS.QUEUED.THRESH:"workflow_queued:{#WORKFLOW_NAME}"})=0`|Warning||
|GitHub: Workflow [{#WORKFLOW_NAME}]: The workflow has been in progress for too long|<p>The last workflow run has been in the "in_progress" status for too long. The default threshold is provided as an example and can be adjusted for relevant workflows with context macros.</p>|`last(/GitHub repository by HTTP/github.repo.workflows.last_run.status[{#WORKFLOW_NAME}])=1 and changecount(/GitHub repository by HTTP/github.repo.workflows.last_run.status[{#WORKFLOW_NAME}],{$GITHUB.WORKFLOW.STATUS.IN_PROGRESS.THRESH:"workflow_in_progress:{#WORKFLOW_NAME}"})=0`|Warning||
|GitHub: Workflow [{#WORKFLOW_NAME}]: The workflow has failed|<p>The last workflow run has returned a "failure" conclusion.</p>|`last(/GitHub repository by HTTP/github.repo.workflows.last_run.conclusion[{#WORKFLOW_NAME}])=1`|Warning||

### LLD rule Branch discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Branch discovery|<p>Discovers repository branches.</p><p></p><p>Information about endpoint:</p><p>https://docs.github.com/en/rest/branches/branches?apiVersion=2022-11-28#list-branches</p>|Dependent item|github.repo.branches.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|

### Item prototypes for Branch discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Branch [{#BRANCH_NAME}]: Number of commits|<p>Get the number of commits in the branch.</p><p></p><p>Information about endpoint:</p><p>https://docs.github.com/en/rest/commits/commits?apiVersion=2022-11-28#list-commits</p>|Script|github.repo.branches.commits.total[{#BRANCH_NAME}]<p>**Preprocessing**</p><ul><li><p>Check for error using a regular expression: `API rate limit exceeded<br>\0`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### LLD rule Self-hosted runner discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Self-hosted runner discovery|<p>Discovers self-hosted runners of the repository.</p><p></p><p>Note that admin access to the repository is required to use this endpoint:</p><p>https://docs.github.com/en/rest/actions/self-hosted-runners?apiVersion=2022-11-28#list-self-hosted-runners-for-a-repository</p>|Dependent item|github.repo.runners.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Self-hosted runner discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Runner [{#RUNNER_NAME}]: Busy|<p>Indicates whether the runner is currently executing a job.</p>|Dependent item|github.repo.runners.busy[{#RUNNER_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data[?(@.id == "{#RUNNER_ID}")].busy.first()`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Runner [{#RUNNER_NAME}]: Online|<p>Indicates whether the runner is connected to GitHub and is ready to execute jobs.</p>|Dependent item|github.repo.runners.online[{#RUNNER_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data[?(@.id == "{#RUNNER_ID}")].status.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Self-hosted runner discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GitHub: Runner [{#RUNNER_NAME}]: The runner has become offline|<p>The runner was online previously, but is currently not connected to GitHub. This could be because the machine is offline, the self-hosted runner application is not running on the machine, or the self-hosted runner application cannot communicate with GitHub.</p>|`last(/GitHub repository by HTTP/github.repo.runners.online[{#RUNNER_NAME}],#2)=1 and last(/GitHub repository by HTTP/github.repo.runners.online[{#RUNNER_NAME}])=0`|Warning||

### LLD rule Discovery of community profile metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Discovery of community profile metrics|<p>Discovers community profile metrics (the repository must not be a fork).</p><p></p><p>Information about community profile metrics:</p><p>https://docs.github.com/en/rest/metrics/community?apiVersion=2022-11-28#get-community-profile-metrics</p>|Dependent item|github.repo.community_profile.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Discovery of community profile metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Health percentage score|<p>The health percentage score is defined as a percentage of how many of the recommended community health files are present.</p><p></p><p>For more information, see the documentation:</p><p>https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/about-community-profiles-for-public-repositories</p>|Dependent item|github.repo.repository.health[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data..zbx_community_profile.health_percentage.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

