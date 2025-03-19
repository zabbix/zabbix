![](images/logo.png?raw=true)
# GitHub webhook

## Overview

This guide describes how to integrate your Zabbix installation with GitHub using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|zabbix_url|\{$ZABBIX\.URL\}|The URL of the Zabbix frontend.|
|github_api_version|2022\-11\-28|The API version that is used in the headers of HTTP requests.|
|github_token|\<PLACE GITHUB TOKEN\>|The API access token.|
|github_url|https://api\.github\.com|The API URL.|
|github_user_agent|Zabbix/7\.4|The user agent that is used in the headers of HTTP requests.|
|github_zabbix_event_priority_label_prefix|Zabbix Event Priority: |The prefix that is used in event priority issue labels. It is set to "Zabbix Event Priority: " by default.|
|github_zabbix_event_source_label_prefix|Zabbix Event Source: |The prefix that is used in event source issue labels. It is set to "Zabbix Event Source: " by default.|
|github_zabbix_event_status_label_prefix|Zabbix Event Status: |The prefix that is used in event status issue labels. It is set to "Zabbix Event Status: " by default.|
|github_zabbix_generic_label|Zabbix GitHub Webhook|The label that is added to issues created by the webhook. It is set to "Zabbix GitHub Webhook" by default.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|event_update_nseverity|\{EVENT\.UPDATE\.NSEVERITY\}|Numeric value of the event update severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_update_severity|\{EVENT\.UPDATE\.SEVERITY\}|Name of the event update severity.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|github_issue_number|\{EVENT\.TAGS\.\_\_zbx\_github\_issue\_number\}|The issue number in GitHub.|
|github_repo|\{ALERT\.SENDTO\}|The full name of the repository in the format `owner/project name`.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1. Create an **access token**.

One of the simplest ways to send authenticated requests is to use a [personal access token](https://docs.github.com/en/rest/authentication/authenticating-to-the-rest-api?apiVersion=2022-11-28#authenticating-with-a-personal-access-token) - either a classic or a fine-grained one.

**Classic personal access token**

You can create a new classic personal access token by following the [instructions in the official GitHub documentation](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#creating-a-personal-access-token-classic).

The token user must have permission to create issues and issue comments in the desired repositories. For the webhook to work on private repositories, the `repo` scope in the token settings must be defined as having full control of private repositories.

Additional information about OAuth scopes is available in the [official GitHub documentation](https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/scopes-for-oauth-apps#available-scopes).

**Fine-grained personal access token**

Alternatively, you can use a [fine-grained personal access token](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#creating-a-fine-grained-personal-access-token).

In order to use fine-grained tokens in organization-owned repositories, [organizations must opt in to fine-grained personal access tokens and set up a personal access token policy](https://docs.github.com/en/organizations/managing-programmatic-access-to-your-organization/setting-a-personal-access-token-policy-for-your-organization).

The fine-grained token needs to have the following permission set to provide access to the repository issues:
- "Issues" repository permissions (write)

2. Copy and save the created token somewhere, as, for security reasons, it will be shown **only once**!

## Zabbix configuration

1. Before you can start using the GitHub webhook, you need to set up the global macro `{$ZABBIX.URL}`:
  - In the Zabbix web interface, go to *Administration* > *Macros* in the top-left dropdown menu.
  - Set up the global macro `{$ZABBIX.URL}` which will contain the URL to the Zabbix frontend. The URL should be either an IP address, a fully qualified domain name, or a localhost.
  - Specifying a protocol is mandatory, whereas the port is optional. Depending on the web server configuration, you might also need to append `/zabbix` to the end of URL. Good examples:
    - `http://zabbix.com`
    - `https://zabbix.lan/zabbix`
    - `http://server.zabbix.lan/`
    - `http://localhost`
    - `http://127.0.0.1:8080`
  - Bad examples:
    - `zabbix.com`
    - `http://zabbix/`

[![](images/thumb.1.png?raw=true)](images/1.png)

2. Import the media type:
  - In the *Alerts* > *Media types* section, import the [`media_github.yaml`](media_github.yaml) file.

3. Open the imported GitHub media type and set the `github_token` webhook parameter value to the access token that you created previously.

You can also adjust the issue labels created by the webhook in the following parameters:
  - `github_zabbix_event_priority_label_prefix` - the prefix for the issue label that displays the Zabbix event priority in the supported event sources. It is set to `Zabbix Event Priority: ` by default.
  - `github_zabbix_event_source_label_prefix` - the prefix for the issue label that displays the Zabbix event source. It is set to `Zabbix Event Source: ` by default.
  - `github_zabbix_event_status_label_prefix` - the prefix for the issue label that displays the Zabbix event status. It is set to `Zabbix Event Status: ` by default.
  - `github_zabbix_generic_label` - the label that is added to all issues created by the webhook. It is set to `Zabbix GitHub Webhook` by default.

Note that the webhook will reuse the labels with the same name that already exist in the repository (including their color, which can be changed from the default for new labels in GitHub if needed). The labels are replaced when the issue is updated, so any user-added labels will be removed.

[![](images/thumb.2.png?raw=true)](images/2.png)

5. Create a Zabbix user and add media:
  - If you want to create a new user, go to the *Users* > *Users* section and click the *Create user* button in the top-right corner. In the *User* tab, fill in all the required fields (marked with red asterisks).
  - In the *Media* tab, add a new media and select **GitHub** from the *Type* drop-down list. In the *Send to* field, specify the full repo name (`owner/project name`) e.g. `johndoe/example-project`.
  - Make sure this user has access to all the hosts for which you would like problem notifications to be sent to GitHub.

[![](images/thumb.3.png?raw=true)](images/3.png)

6. Done! You can now start using this media type in actions and create GitHub issues.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [GitHub](https://docs.github.com/en/rest?apiVersion=2022-11-28) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
