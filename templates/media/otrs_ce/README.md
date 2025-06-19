![](images/logo.png?raw=true)
# OTRS CE webhook

## Overview

This guide describes how to integrate your Zabbix installation with ((OTRS)) Community Edition using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

## Supported versions

((OTRS)) CE version 6

## Requirements

Zabbix version: 7.4 and higher.

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
|otrs_ticket_type|Unclassified|((OTRS)) CE ticket type.|
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

1. Create a new web service. To do so, navigate to *Admin* > *Web services* and import the [ZabbixTicketConnector.yml](ZabbixTicketConnector.yml) file.

[![](images/thumb.1.png?raw=true)](images/1.png)
[![](images/thumb.2.png?raw=true)](images/2.png)
[![](images/thumb.3.png?raw=true)](images/3.png)
[![](images/thumb.4.png?raw=true)](images/4.png)

2. Create a new customer.

[![](images/thumb.5.png?raw=true)](images/5.png)
[![](images/thumb.6.png?raw=true)](images/6.png)
[![](images/thumb.7.png?raw=true)](images/7.png)

3. Create a new customer user. Select the ID of the customer that you created in the previous step.

[![](images/thumb.8.png?raw=true)](images/8.png)
[![](images/thumb.9.png?raw=true)](images/9.png)
[![](images/thumb.10.png?raw=true)](images/10.png)

4. Create a new agent. Depending on the ticket queue you want to use for tickets created by the webhook, set the `RW` permission for the group that this ticket queue belongs to. In the example below, if you want to use the `Misc` queue, you must set the `RW` permission for the group `users`.

[![](images/thumb.11.png?raw=true)](images/11.png)
[![](images/thumb.12.png?raw=true)](images/12.png)
[![](images/thumb.13.png?raw=true)](images/13.png)
[![](images/thumb.14.png?raw=true)](images/14.png)
[![](images/thumb.15.png?raw=true)](images/15.png)

## Zabbix configuration

1. Before you can start using the **((OTRS)) CE** webhook, you need to set the global macro `{$ZABBIX.URL}`:
  - In the Zabbix web interface, go to *Administration* > *Macros* in the top-left dropdown menu.
  - Set the global macro `{$ZABBIX.URL}` to the URL of the Zabbix frontend. The URL should be either an IP address, a fully qualified domain name, or localhost.
  - Specifying a protocol is mandatory, whereas the port is optional. Depending on the web server configuration, you might also need to append `/zabbix` to the end of URL. Good examples:
    - `http://zabbix.com`
    - `https://zabbix.lan/zabbix`
    - `http://server.zabbix.lan/`
    - `http://localhost`
    - `http://127.0.0.1:8080`
  - Bad examples:
    - `zabbix.com`
    - `http://zabbix/`

[![](images/thumb.16.png?raw=true)](images/16.png)

2. Import the media type:
  - In the *Alerts* > *Media types* section, import the [`media_otrs_ce.yaml`](media_otrs_ce.yaml) file.

3. Open the imported **((OTRS)) CE** media type and set the following webhook parameters:
  - `otrs_auth_user` - the username of the agent
  - `otrs_auth_password` - the password of the agent
  - `otrs_customer` - the email of the customer user
  - `otrs_queue` - the queue that will be used for tickets created by the webhook
  - `otrs_url` - the frontend URL of your **((OTRS)) CE** installation (for example, `https://otrs.example.com/otrs`)

[![](images/thumb.17.png?raw=true)](images/17.png)

4. If you want to prioritize issues according to the severity values in Zabbix, you can define mapping parameters (create them as additional webhook parameters):
  - `severity_<name>` - the **((OTRS)) CE** priority ID (`<name>` in the parameter name can be one of the following values: `not_classified`, `information`, `warning`, `average`, `high`, `disaster`)

[![](images/thumb.18.png?raw=true)](images/18.png)

5. If you have **dynamic fields** in **((OTRS)) CE** and want them to be filled with values from Zabbix, add webhook parameters in the format `dynamicfield_<((OTRS)) CE dynamic field name>`, similarly to the previous step. Dynamic fields can only be of the types **text**, **textarea**, **checkbox**, or **date**.

6. If you want the webhook to close tickets related to **resolved** problems in Zabbix, you can change the following parameter value:

- `otrs_closed_state_id` - **((OTRS)) CE** state ID for closed tasks (possible values: 0 - Disable tickets closing, >0 - State ID from the State Management page).

7. If you use the ticket type feature, you can change the type of the created tickets:

- `otrs_ticket_type` - **((OTRS)) CE** ticket type (set to `Unclassified` by default; present on fresh installations).

8. Click the *Enabled* checkbox to enable the mediatype and click the *Update* button to save the webhook settings.

9. Create a Zabbix user and add media:
  - To create a new user,  go to the *Users* > *Users* section and click the *Create user* button in the top-right corner. In the *User* tab, fill in all the required fields (marked with red asterisks).
  - In the *Media* tab, click *Add* and select **OTRS CE** from the *Type* drop-down list. Though the *Send to* field is not used in the **((OTRS)) CE** webhook, it cannot be left empty. To comply with frontend requirements, enter any symbol in the field.
  - Make sure this user has access to all the hosts for which you would like problem notifications to be sent to **((OTRS)) CE**.

[![](images/thumb.19.png?raw=true)](images/19.png)

10. Done! You can now start using this media type in actions and create tickets.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [((OTRS)) CE](https://otrscommunityedition.com/doc/) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
