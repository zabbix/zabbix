![](images/logo-glpi-bleu-1.png?raw=true)
# GLPi webhook

## Overview

This guide describes how to integrate your Zabbix installation with GLPi using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

This webhook creates problem records in the GLPi Assistance section. Created problems have the following severity mapping:

|Severity in Zabbix|Urgency in GLPi|
|-|-|
0 - Not classified| Medium (default)|
1 - Information| Very low|
2 - Warning| Low|
3 - Average| Medium|
4 - High| High|
5 - Disaster| Very high|

When an *on Update* action occurs in Zabbix, the webhook updates the problem's title and severity and adds a follow-up entry with the update comment.
When a problem is resolved in Zabbix, the webhook updates the problem's title and adds a follow-up entry with resolution details.
Created problems have the status "New", and resolved problems - "Solved".

Due to the specifics of the webhook, the number of retries is, by default, set to 1. We recommend not changing this setting; should a transaction error occur, additional duplicate objects (problems, followups) may be created during the retry.

## Tested on
 - GLPI 10.0.16

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|zabbix_url|\{$ZABBIX\.URL\}|Current Zabbix URL.|
|glpi_token|\<PLACE GLPI TOKEN\>|GLPi user token.|
|glpi_url|\<PLACE GLPI URL\>|URL of GLPi installation.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|event_severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_update_nseverity|\{EVENT\.UPDATE\.NSEVERITY\}|Numeric value of the event update severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_update_severity|\{EVENT\.UPDATE\.SEVERITY\}|Name of the event update severity.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|glpi_problem_id|\{EVENT\.TAGS\.\_\_zbx\_glpi\_problem\_id\}|GLPi problem ID.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. In [GLPi](https://glpi-user-documentation.readthedocs.io/fr/latest/modules/administration/users/users.html), create a user or choose an existing user - one with permission to create problems and followups.

[![](images/thumb.2.png?raw=true)](images/2.png)
[![](images/thumb.3.png?raw=true)](images/3.png)

2\. Create an API token. Head to your GLPi user profile, check the *Regenerate* box next to *API token*, and hit *Save*.

[![](images/thumb.4.png?raw=true)](images/4.png)

3\. Copy the API token of your new integration to use it in Zabbix.

## Zabbix configuration

1\. Start by setting up the global macro `{$ZABBIX.URL}` containing an URL to the Zabbix frontend. Note that if an HTTP/HTTPS schema is not present in the URL, HTTPS will be used by default.

The global macro can also be used, for example, to populate a custom field in Jira with a URL linking to event information or a graph.

[![](images/thumb.1.png?raw=true)](images/1.png)

2\. [Import](https://www.zabbix.com/documentation/7.4/manual/web_interface/frontend_sections/administration/mediatypes) the GLPi media type from the file [`media_glpi.yaml`](media_glpi.yaml).

3\. In the imported media, change the values of the variable `glpi_token` and `glpi_url`.

For more information about Zabbix webhook configuration, please see [Zabbix documentation](https://www.zabbix.com/documentation/7.4/manual/config/notifications/media/webhook).

4\. Create a Zabbix user and add **Media** with the **GLPi** media type.
Though the *Send to* field is not used in the GLPi webhook, it cannot be left empty. To comply with frontend requirements, enter any symbol in the field.

Make sure the user you created has access to all the hosts for which you would like Zabbix problem notifications to be converted into GLPi problems.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [GLPi](https://glpi-user-documentation.readthedocs.io/fr/latest/) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
