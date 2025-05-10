![](images/logo.png?raw=true)
# OTRS CE webhook

## Overview

This guide describes how to integrate your Zabbix installation with ((OTRS)) Community Edition using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

## Supported versions

((OTRS)) CE version 6

## Requirements

Zabbix version: 7.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|zabbix_url|\{$ZABBIX\.URL\}|Current Zabbix URL.|
|otrs_closed_state_id|0|((OTRS)) CE state ID for closed tasks. Possible values: 0 - Disable tickets closing; >0 - State ID from the State Management page.|
|otrs_auth_password|\<PUT YOUR USER PASSWORD\>|Agent password.|
|otrs_auth_user|\<PUT YOUR USER NAME\>|Agent username.|
|otrs_customer|\<PUT YOUR CUSTOMER EMAIL\>|((OTRS)) CE customer email.|
|otrs_default_priority_id|3|((OTRS)) CE default priority ID.|
|otrs_queue|\<PUT YOUR QUEUE NAME\>|((OTRS)) CE ticket queue.|
|otrs_ticket_state|new|((OTRS)) CE ticket state.|
|otrs_time_unit|0|((OTRS)) CE time unit.|
|otrs_url|\<PUT YOUR \(\(OTRS\)\) CE URL\>|Frontend URL of your ((OTRS)) CE installation.|

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
|otrs_ticket_id|\{EVENT\.TAGS\.\_\_zbx\_otrs\_ticket\_id\}|((OTRS)) CE ticket ID.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Import [ZabbixTicketConnector.yml](ZabbixTicketConnector.yml) in *Admin* > *Web Services*.

[![](images/thumb.01.png?raw=true)](images/01.png)
[![](images/thumb.02.png?raw=true)](images/02.png)
[![](images/thumb.03.png?raw=true)](images/03.png)

2\. Create a new user for a Zabbix alerter with an email address.

[![](images/thumb.04.png?raw=true)](images/04.png)
[![](images/thumb.05.png?raw=true)](images/05.png)

## Zabbix configuration

1\. Before you can start using the ((OTRS)) CE webhook, you need to set up the global macro `{$ZABBIX.URL}` containing an URL to the Zabbix frontend.

[![](images/thumb.06.png?raw=true)](images/06.png)

2\. In the Zabbix interface *Alerts* > *Media types* section, import the [`media_otrs_ce.yaml`](media_otrs_ce.yaml) file.

3\. Open the newly added **((OTRS)) CE** media type and set:

- **otrs_auth_user** to your **Agent username**
- **otrs_auth_password** to your **Agent password**
- **otrs_customer** to your **((OTRS)) CE customer email**
- **otrs_queue** to your **((OTRS)) CE ticket queue**
- **otrs_url** to the **frontend URL** of your **((OTRS)) CE** installation

[![](images/thumb.07.png?raw=true)](images/07.png)

4\. If you want to prioritize issues according to the **severity** values in Zabbix, you can define mapping parameters:

- **severity_\<name\>**: ((OTRS)) CE priority ID

[![](images/thumb.08.png?raw=true)](images/08.png)

5\. If you have **dynamic fields** in **((OTRS)) CE** and want them to be filled with values from Zabbix, add parameters in the format `dynamicfield_\<((OTRS)) CE dynamic field name\>`. Dynamic fields can only be of the type **text**, **textarea**, **checkbox**, or **date**.

6\. If you want the webhook to close tickets related to **resolved** problems in Zabbix, you can change the following parameter value:

- **otrs_closed_state_id**: ((OTRS)) CE state ID for closed tasks. Possible values: 0 - Disable tickets closing, >0 - State ID from the State Management page.

7\. Click the *Update* button to save the webhook settings.

8\. To receive notifications in ((OTRS)) CE, you need to create a Zabbix user and add **Media** with the **((OTRS)) CE** media type.

Though the *Send to* field is not used in ((OTRS)) CE, it cannot be empty. To comply with the frontend requirements, enter any symbol in the field.

[![](images/thumb.09.png?raw=true)](images/09.png)

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.0/manual/config/notifications) and [((OTRS)) CE](https://otrscommunityedition.com/doc/) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
