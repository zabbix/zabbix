![](images/logo.png?raw=true)
# Zendesk webhook

## Overview

This guide describes how to integrate your Zabbix installation with Zendesk using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user, and an action in Zabbix.

## How it Works
If a trigger fires, Zabbix will send an event to a service in Zendesk. Event from Zabbix will trigger a new incident on the Zendesk service.
The Zendesk tickets are created for Problem, Problem recovery, Problem update, Discovery, Autoregistration, Internal problem events. Internal problem recovery event is unsupported.
The Zendesk ticket type field is defined in the zendesk_type parameter of the media. The following event types are supported: question, incident, problems, task.
Custom fields and the subject field are updated when the update action (Problem update or Problem recovery) is performed.
Tags, priority, and status fields are filled in only when creating a ticket. They are not overwritten when the update action is performed.
Custom fields can only be of the text, number, or date types. Other field types or nonexistent IDs will be ignored.
The {ALERT.MESSAGE} macro is used to fill in the ticket body for all actions.
The {EVENT.NAME} macro is used as a subject of a ticket for trigger-based events, the {ALERT.SUBJECT} macro is used for non trigger-based events.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|severity_average|normal|Average severity.|
|severity_default|\-|Default severity.|
|severity_disaster|urgent|Disaster severity.|
|severity_high|high|High severity.|
|severity_information|low|Information severity.|
|severity_not_classified|low|Not classified severity.|
|severity_warning|normal|Warning severity.|
|tls_verify|\{$HTTP\.TLS\.VERIFY:"Zendesk"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "Zendesk", e.g. {$HTTP.TLS.VERIFY:"Zendesk"}.|
|zbxurl|\{$ZABBIX\.URL\}|Current Zabbix URL.|
|zendesk_token|\<put your \{enduser\_email\_address\}/token:\{api\_token\}\>|Zendesk token.|
|zendesk_type|incident|Zendes type.|
|zendesk_url|\<put your Zendesk URL\>|Zendesk URL.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_tags|\{EVENT\.TAGS\}|A comma-separated list of event tags. Expanded to an empty string if no tags exist.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|zendesk_issue_key|\{EVENT\.TAGS\.\_\_zbx\_zdk\_issuekey\}|Zendesk issue key.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. [Create a user](https://support.zendesk.com/hc/en-us/articles/203690886-Adding-and-managing-end-users) to connect between Zabbix and Zendesk.

2\. [Generate a new API token](https://support.zendesk.com/hc/en-us/articles/226022787-Generating-a-new-API-token-).

3\. Copy the **Active API Token** into your integration with Zabbix.

## Zabbix configuration

The configuration consists of a _media type_ in Zabbix, which will invoke webhook to send alerts to Zendesk through the Zendesk API. To utilize the media type, we will create a Zabbix user to represent Zendesk. We will then create an alert action to notify the user via this media type whenever there is a problem detected.

### Create Global Macro

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **General** page and choose **Macros** from the drop-down list.

3\. Add a macro {$ZABBIX.URL} with Zabbix frontend URL (for example http://192.168.7.123:8081).

[![](images/tn_6.png?raw=true)](images/6.png)

4\. Click the **Update** button to save the global macro.

### Create a Zendesk media type

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Media types** page and click **Import** button.

[![](images/tn_7.png?raw=true)](images/7.png)

3\. Select Import file [media_zendesk.yaml](media_zendesk.yaml) and click **Import** button at the bottom to import the Zendesk media type.

4\. Change the value of zendesk_url and zendesk_token variables. Zendesk_type parameter determines the ticket type in Zendesk. One of the following types can be used: question, incident (default), problems, task.  
In addition, you can override the severity mapping between the Zabbix problem and the Zendesk ticket.  

[![](images/tn_8.png?raw=true)](images/8.png)

5\. If you have custom fields in Zendesk and you want them to be filled in with values from Zabbix, add parameters in the form customfield_\<Zendesk custom field ID\>. Custom fields can only be of the text, number, or date types.

[![](images/tn_13.png?raw=true)](images/13.png)

### Create the Zendesk user for alerting

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Users** page and click the **Create user** button.

[![](images/tn_9.png?raw=true)](images/9.png)

3\. Fill in the details of this new user, and call it “Media User”. The default settings for Zendesk User should suffice as this user will not be logging into Zabbix.

4\. Click the **Select** button next to **Groups**.

[![](images/tn_10.png?raw=true)](images/10.png)

* Please note, that in order to notify on problems with host this user must have at least read permissions for such host.

5\. Click on the **Media** tab and, inside of the **Media** box, click the **Add** button.

[![](images/tn_11.png?raw=true)](images/11.png)

6\. In the new window that appears, configure the media for the user as follows:

[![](images/tn_12.png?raw=true)](images/12.png)

* For the **Type**, select **Zendesk** (the new media type that was created).
* For **Send to**: enter any text, as this value is not used, but is required.
* Make sure the **Enabled** box is checked.
* Click the **Add** button when done.

7\. Click the **Add** button at the bottom of the user page to save the user.

8\. Use the Zendesk User in any Actions of your choice.

For more information, use the [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [Zendesk](https://developer.zendesk.com/rest_api/docs/support/tickets) documentations.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
