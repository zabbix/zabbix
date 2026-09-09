![](images/logo.png?raw=true)
# MantisBT webhook

## Overview

This guide describes how to integrate your Zabbix installation with MantisBT issues using Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Supported versions

MantisBT 2.22

## Requirements

Zabbix version: 7.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|mantisbt_category|\[All Projects\] General|Category of the issues to be created.|
|mantisbt_token|\<PLACE MANTISBT TOKEN\>|MantisBT access token.|
|mantisbt_url|\<PLACE MANTISBT URL\>|MantisBT URL address.|
|mantisbt_use_zabbix_tags|true|Sets whether Zabbix tags should be assigned to the issues.|
|tls_verify|\{$HTTP\.TLS\.VERIFY:"MantisBT"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "MantisBT", e.g. {$HTTP.TLS.VERIFY:"MantisBT"}.|
|zabbix_url|\{$ZABBIX\.URL\}|Current Zabbix URL.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_sendto|\{ALERT\.SENDTO\}|'Send to' value from user media configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_recovery_value|\{EVENT\.RECOVERY\.VALUE\}|Numeric value of the recovery event.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_tagsjson|\{EVENT\.TAGSJSON\}|A JSON array containing event tag [objects]('https://www.zabbix.com/documentation/current/manual/api/reference/event/object#event-tag'). Expanded to an empty array if no tags exist.|
|event_update_action|\{EVENT\.UPDATE\.ACTION\}|Human-readable name of the action(s) performed during a [problem update]('https://www.zabbix.com/documentation/current/manual/acknowledgment#updating-problems').|
|event_update_message|\{EVENT\.UPDATE\.MESSAGE\}|Problem update message.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|mantisbt_issue_number|\{EVENT\.TAGS\.\_\_zbx\_mantisbt\_issue\_number\}|MantisBT issue number.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Create or use an existing **project** for creating issues.

[![](images/project_tn.png?raw=true)](images/project.png)

2\. Create or use an existing user in MantisBT with the permission to create issues in the desired project.
You can check the [instruction](https://support.mantishub.com/hc/en-us/articles/203574829-Creating-User-Accounts) how to do it.

[![](images/user_tn.png?raw=true)](images/user.png)

3\. Create an **access token** according to the original [instruction](https://support.mantishub.com/hc/en-us/articles/215787323-Connecting-to-MantisHub-APIs-using-API-Tokens).

[![](images/token_tn.png?raw=true)](images/token.png)

4\. Copy the **access token** to use it in Zabbix.

## Zabbix configuration

MantisBT _media type_ must be configured in Zabbix, which will invoke the webhook to send alerts to MantisBT issues through [MantisBT Rest API](https://www.mantisbt.org/docs/master/en-US/Developers_Guide/html/restapi.html).

1\. [Import](https://www.zabbix.com/documentation/7.0/manual/web_interface/frontend_sections/administration/mediatypes) MantisBT media type from [media_mantisbt.yaml](media_mantisbt.yaml) file.

2\. Change values of the following parameters in the imported media:

- mantisbt_category - category of the issues to be created. Default value: "[All Projects] General"
- mantisbt_token - MantisBT **access token**
- mantisbt_url - MantisBT URL address
- mantisbt_use_zabbix_tags - true|false - whether Zabbix tags should be assigned to the issues. Default value: "true"

[![](images/media_type_tn.png?raw=true)](images/media_type.png)

3\. Create a user and add MantisBT media type to it. Use your MantisBT project name in the "Send to" field.

[![](images/zabbix_user_tn.png?raw=true)](images/zabbix_user.png)

4\. Set up a global macro {$ZABBIX.URL} with the current Zabbix URL. Please note that HTTPS will be used by default if HTTP/HTTPS schema is not present in the URL.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.0/manual/config/notifications) and [MantisBT](https://www.mantisbt.org/documentation.php) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
