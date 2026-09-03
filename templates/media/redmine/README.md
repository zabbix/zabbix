![](images/logo.png?raw=true)
# Redmine webhook

## Overview

This guide describes how to integrate your Zabbix 8.0 installation with Redmine using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

Zabbix version: 8.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|redmine_access_key|\<PUT YOUR ACCESS KEY\>|Redmine access key.|
|redmine_project|\<PUT YOUR PROJECT ID OR NAME\>|Redmine project ID or name.|
|redmine_tracker_id|\<PUT YOUR TRACKER ID\>|Redmine tracker ID.|
|redmine_url|\<PUT YOUR REDMINE URL\>|Redmine URL.|
|tls_verify|\{$HTTP\.TLS\.VERIFY:"Redmine"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "Redmine", e.g. {$HTTP.TLS.VERIFY:"Redmine"}.|
|zabbix_url|\{$ZABBIX\.URL\}|Current Zabbix URL.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_update_message|\{EVENT\.UPDATE\.MESSAGE\}|Problem update message.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|redmine_issue_key|\{EVENT\.TAGS\.\_\_zbx\_redmine\_issue\_id\}|Redmine issue ID.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Enable **REST API** in Administration > Settings > API. 

[![](images/thumb.01.png?raw=true)](images/01.png)

2\. Find your **API key** on your account page when logged in, on the right-hand pane of the default layout.

[![](images/thumb.03.png?raw=true)](images/03.png)

## Zabbix configuration

### Create a global macro

1\. Before setting up the **Webhook**, you need to setup the global macro **{$ZABBIX.URL}**, which must contain the **URL** to the **Zabbix frontend**.

[![](images/thumb.04.png?raw=true)](images/04.png)

2\. In the **Administration** > **Media types** section, import the [media_redmine.yaml](media_redmine.yaml)

3\. Open the added **Redmine** media type and set:

- **redmine_access_key** to the your **API key**
- **redmine_url** to the **frontend URL** of your **Redmine** installation
- **redmine_project** to your numeric Project ID or its name. Important: if you specify a project name, each time an additional API call will be made to get its identifier.
You can find Project ID on *http://&lt;YOR_REDMINE_URL&gt;/projects.xml*
- **redmine_tracker_id** to your Tracker ID

[![](images/thumb.05.png?raw=true)](images/05.png)

4\. If you want to close issues on trigger resolve, add parameter **redmine_close_status_id** with close Status ID as value. (Status with "Issue closed" tick)

5\. If you have **custom fields** in **Redmine** and you want them to be filled in with values from Zabbix, add parameters in the form **customfield_\<Redmine custom field ID\>**. Custom fields can only be of the **text**, **integer**, **float** or **date** types. Custom fields of **date** type must be in the format "YYYY-MM-DD".

6\. If you want to prioritize issues according to **severity** values in Zabbix, you can define mapping parameters:

- **severity_\<name\>**: Redmine priority ID

[![](images/thumb.07.png?raw=true)](images/07.png)

7\. Click the **Update** button to save the **Webhook** settings.

8\. To receive notifications in **Redmine**, you need to create a **Zabbix user** and add **Media** with the **Redmine** type.

For **Send to**: enter any text, as this value is not used, but is required.

[![](images/thumb.06.png?raw=true)](images/06.png)

For more information, use the [Zabbix](https://www.zabbix.com/documentation/8.0/manual/config/notifications) and [Redmine](https://www.redmine.org/projects/redmine/wiki/) documentations.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
