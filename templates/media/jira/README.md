![](images/logo.png?raw=true)
# Jira webhook

## Overview

This guide describes how to integrate your Zabbix installation with Jira using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|jira_issue_type|\<PLACE ISSUETYPE NAME\>|Name of issue type to be used when creating new issues.|
|jira_password|\<PLACE PASSWORD OR TOKEN\>|Password or token of Jira user.|
|jira_project_key|\<PLACE PROJECT KEY\>|Text key of Jira project.|
|jira_url|\<PLACE YOUR JIRA URL\>|URL of Jira instance.|
|jira_user|\<PLACE LOGIN\>|Username of Jira user.|
|jira_priority_autoregistration|Low|String value of Jira priority to assign to autoregistration event tickets.|
|jira_priority_discovery|Low|String value of Jira priority to assign to discovery event tickets.|
|jira_priority_internal|Low|String value of Jira priority to assign to internal event tickets.|
|severity_not_classified|Lowest|Jira priority to assign to tickets when event has Zabbix severity "Not classified".|
|severity_information|Lowest|Jira priority to assign to tickets when event has Zabbix severity "Information".|
|severity_warning|Low|Jira priority to assign to tickets when event has Zabbix severity "Warning".|
|severity_average|Medium|Jira priority to assign to tickets when event has Zabbix severity "Average".|
|severity_high|High|Jira priority to assign to tickets when event has Zabbix severity "High".|
|severity_disaster|Highest|Jira priority to assign to tickets when event has Zabbix severity "Disaster".|
|zabbix_url|\{$ZABBIX\.URL\}|Current Zabbix URL.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|trigger_description|\{TRIGGER\.DESCRIPTION\}|Trigger description.|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_recovery_value|\{EVENT\.RECOVERY\.VALUE\}|Numeric value of the recovery event.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_tags_json|\{EVENT\.TAGSJSON\}|A JSON array containing event tag [objects]('https://www.zabbix.com/documentation/current/manual/api/reference/event/object#event-tag'). Expanded to an empty array if no tags exist.|
|event_update_action|\{EVENT\.UPDATE\.ACTION\}|Human-readable name of the action(s) performed during a [problem update]('https://www.zabbix.com/documentation/current/manual/acknowledgment#updating-problems').|
|event_update_message|\{EVENT\.UPDATE\.MESSAGE\}|Problem update message.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_update_user|\{USER\.FULLNAME\}|Name, surname, and username of the user who added an event acknowledgment or started the script.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|event_update_nseverity|\{EVENT\.UPDATE\.NSEVERITY\}|Numeric value of the event update severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_update_severity|\{EVENT\.UPDATE\.SEVERITY\}|Name of the event update severity.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

Refer to the vendor documentation.

## Zabbix configuration

1\. Before you can start using the Jira webhook, you need to set up the global macro `{$ZABBIX.URL}` containing an URL to the Zabbix frontend.

The global macro can also be used, for example, to populate a custom field in Jira with a URL linking to event information or a graph.

[![](images/thumb.1.png?raw=true)](images/1.png)

2\. In the Zabbix frontend *Alerts > Media types* section, import the file [`media_jira.yaml`](media_jira.yaml).

3\. Open the newly added Jira media type and, under *Parameters*, replace all <PLACEHOLDERS> with your values.
The following parameters are required:
- **jira_url** - actual URL of your Jira instance,
- **jira_user** - Jira user login,
- **jira_password** - password or API token (for Jira Cloud installations, an API token can be obtained at https://id.atlassian.com/manage/api-tokens),
- **jira_project_key** - text key of the Jira project (not to be mistaken with the ID!),
- **jira_issue_type** - name of the issue type to be used when creating new issues from Zabbix notifications.

4\. It is also possible to set Jira ticket priorities by parameters. Predefined parameter values are already in place;
however, be aware that they use the default Jira priorities.
**Please, adjust these values to suit your Jira environment.**

The following parameters are for events in Zabbix that support severities:
- **severity_not_classified** - for "Not Classified" Zabbix severity
- **severity_information** - for "Information" Zabbix severity
- **severity_warning** - for "Warning" Zabbix severity
- **severity_average** - for "Average" Zabbix severity
- **severity_high** - for "High" Zabbix severity
- **severity_disaster** - for "Disaster" Zabbix severity

And the following for Zabbix events that do not have severities:
- **jira_priority_internal** - for Zabbix internal events
- **jira_priority_discovery** - for Zabbix discovery events
- **jira_priority_autoregistration** - for Zabbix autoregistration events

[![](images/2.png?raw=true)](images/2.png)

5\. You can customize Jira issues with custom fields.

By default, the webhook does not use Jira custom fields. In order to populate a Jira custom field via webhook, you must add a parameter to the Jira media type configuration where the custom field ID is used as the key (*Name*). For details on finding custom field IDs, see [Jira documentation](https://confluence.atlassian.com/jirakb/how-to-find-id-for-custom-field-s-744522503.html).

The following custom field types are supported:
- String
- URL
- Number
- Date
- DateTime
- Single-select
- Multi-select
- Checkbox
- Radio button

Note that you can enter only one value per custom field, except in the `multi-select` and `checkbox` field types where multiple values can be entered, using a comma as a separator (`option1,option2,option3`).

*Examples:*

[![](images/3.png?raw=true)](images/3.png)

**URL custom fields** can be configured in different ways.

If you want a Zabbix URL to appear in the Jira custom URL field, set the field to `zabbix_url`. By default, the value of `zabbix_url` is the `{$ZABBIX.URL}` macro. The script will then use the value of `zabbix_url` for your custom field, formatting it to the action being run:
  * for trigger actions, the script will append a path to your Zabbix URL leading directly to the problem event;
  * for service actions, the script will append a path to your Zabbix URL leading to service actions;
  * for the rest of the actions, it will leave the URL as is.

[![](images/4.png?raw=true)](images/4.png)

If you don't need to use the Zabbix URL in the custom field and want to link to something else (another service), you can manually enter the URL, and the script won’t modify it.

[![](images/5.png?raw=true)](images/5.png)

For the **DateTime custom field**, you can combine parameter values using the format `{EVENT.DATE}T{EVENT.TIME}`.

Note that the date and time must be provided in the ISO 8601 format with the timezone of Zabbix server (2020-01-01T23:59:59+0200).
If server time is set to UTC, there is no need to add the timezone at the end of the parameter value.

6\. This webhook also supports components, but they’re not used by default. To include a component, add a new parameter to the Jira media type for each one. The parameter key (*Name*) should start with `component_` and end with a unique identifier. In the *Value* field, enter the component name from Jira.

[![](images/6.png?raw=true)](images/6.png)

> IMPORTANT! Compass components are not supported!

7\. Create a Zabbix user and add **Media** with the **Jira** media type.
Though the *Send to* field is not used in the Jira webhook, it cannot be left empty. To comply with frontend requirements, enter any symbol in the field.

Make sure this user has access to all the hosts for which you would like problem notifications to be converted into Jira tasks.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [Jira](https://support.atlassian.com/jira-software-cloud/) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
