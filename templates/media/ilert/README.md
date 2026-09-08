![](images/logo.png?raw=true)
# iLert webhook

## Overview

This guide describes how to integrate your Zabbix installation with iLert using the Zabbix webhook feature.
This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|.ILERT.INCIDENT.SUMMARY|||
|ZABBIX.URL|\{$ZABBIX\.URL\}|some description|
|tls_verify|\{$HTTP\.TLS\.VERIFY:"iLert"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "iLert", e.g. {$HTTP.TLS.VERIFY:"iLert"}.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|.ILERT.ALERT.SOURCE.KEY|\{ALERT\.SENDTO\}|'Send to' value from user media configuration.|
|ALERT.MESSAGE|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|ALERT.SUBJECT|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|EVENT.ACK.STATUS|\{EVENT\.ACK\.STATUS\}|Acknowledgment status of the event (Yes/No).|
|EVENT.DATE|\{EVENT\.DATE\}|Date of the event that triggered an action.|
|EVENT.ID|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|EVENT.NAME|\{EVENT\.NAME\}|Name of the problem event that triggered an action.|
|EVENT.NSEVERITY|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|EVENT.OPDATA|\{EVENT\.OPDATA\}|Operational data of the underlying trigger of a problem.|
|EVENT.RECOVERY.DATE|\{EVENT\.RECOVERY\.DATE\}|Date of the recovery event.|
|EVENT.RECOVERY.TIME|\{EVENT\.RECOVERY\.TIME\}|Time of the recovery event.|
|EVENT.RECOVERY.VALUE|\{EVENT\.RECOVERY\.VALUE\}|Numeric value of the recovery event.|
|EVENT.SEVERITY|\{EVENT\.SEVERITY\}|Name of the event severity.|
|EVENT.TAGS|\{EVENT\.TAGS\}|A comma-separated list of event tags. Expanded to an empty string if no tags exist.|
|EVENT.TIME|\{EVENT\.TIME\}|Time of the event that triggered an action.|
|EVENT.UPDATE.ACTION|\{EVENT\.UPDATE\.ACTION\}|Human-readable name of the action(s) performed during a [problem update]('https://www.zabbix.com/documentation/current/manual/acknowledgment#updating-problems').|
|EVENT.UPDATE.DATE|\{EVENT\.UPDATE\.DATE\}|Date of event [update]('https://www.zabbix.com/documentation/current/manual/config/notifications/action/update_operations') (acknowledgment, etc).|
|EVENT.UPDATE.MESSAGE|\{EVENT\.UPDATE\.MESSAGE\}|Problem update message.|
|EVENT.UPDATE.STATUS|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|EVENT.UPDATE.TIME|\{EVENT\.UPDATE\.TIME\}|Time of event [update]('https://www.zabbix.com/documentation/current/manual/config/notifications/action/update_operations') (acknowledgment, etc).|
|EVENT.VALUE|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|HOST.HOST|\{HOST\.HOST\}|Host name.|
|HOST.IP|\{HOST\.IP\}|Host IP address|
|HOST.NAME|\{HOST\.NAME\}|Visible host name.|
|ITEM.ID1|\{ITEM\.ID1\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|ITEM.ID2|\{ITEM\.ID2\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|ITEM.ID3|\{ITEM\.ID3\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|ITEM.ID4|\{ITEM\.ID4\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|ITEM.ID5|\{ITEM\.ID5\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|ITEM.NAME1|\{ITEM\.NAME1\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|ITEM.NAME2|\{ITEM\.NAME2\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|ITEM.NAME3|\{ITEM\.NAME3\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|ITEM.NAME4|\{ITEM\.NAME4\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|ITEM.NAME5|\{ITEM\.NAME5\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|TRIGGER.DESCRIPTION|\{TRIGGER\.DESCRIPTION\}|Trigger description.|
|TRIGGER.ID|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|TRIGGER.NAME|\{TRIGGER\.NAME\}|Name of the trigger (with macros resolved).|
|TRIGGER.SEVERITY|\{TRIGGER\.SEVERITY\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|TRIGGER.STATUS|\{TRIGGER\.STATUS\}|Trigger value at the time of operation step execution. Can be either PROBLEM or OK.|
|TRIGGER.URL|\{TRIGGER\.URL\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|TRIGGER.VALUE|\{TRIGGER\.VALUE\}|~~~VALUE MISSED FROM THE AUTOFILL DESCRIPTION TABLE. PLEASE FILL THE DESCRIPTION FIELD MANUALLY~~|
|USER.FULLNAME|\{USER\.FULLNAME\}|Name, surname, and username of the user who added an event acknowledgment or started the script.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Go to **Alert sources** and click on **Add a new alert source**.

[![](images/tn_1.png?raw=true)](images/1.png)

2\. Set a name (e.g. "Zabbix") and select your desired escalation policy. Select "Zabbix" as the **Integration Type** and click **Save**.

[![](images/tn_2.png?raw=true)](images/2.png)

3\. On the next page, an **API key** is generated. You will need it when setting up the iLert media type in Zabbix.

[![](images/tn_3.png?raw=true)](images/3.png)

## Zabbix configuration

The configuration consists of a _media type_ in Zabbix which will invoke a webhook to send alerts to iLert through the iLert Event API.
To utilize the media type, we will create a Zabbix user to represent iLert. We will then create an alert action to notify the user via this media type whenever a problem is detected.

> Note: only trigger events are currently supported

### Create Global Macro

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **General** page and choose the **Macros** from drop-down list.

3\. Add the macro {\$ZABBIX.URL} with your Zabbix frontend URL (for example http://192.168.7.123:8081)

[![](images/tn_4.png?raw=true)](images/4.png)

4\. Click the **Update** button to save the global macros.

### Create the iLert media type

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Media types** page and click the **Import** button.

[![](images/tn_5.png?raw=true)](images/5.png)

3\. Select the Import file [media_ilert.yaml](media_ilert.yaml) and click **Import** at the bottom to import the iLert media type.

[![](images/tn_6.png?raw=true)](images/6.png)

4\. Optional: you can overwrite the standard incident summary with a custom template using the **.ILERT.INCIDENT.SUMMARY** variable e.g. `{TRIGGER.NAME}: {TRIGGER.STATUS} for {HOST.HOST}`

### Create the iLert user for alerting

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Users** page and click **Create user**.

[![](images/tn_7.png?raw=true)](images/7.png)

3\. Fill in the details of this new user, and call it "iLert User". The default settings for iLert User should suffice as this user will not be logging into Zabbix.

4\. Click the **Select** button next to **Groups**.

[![](images/tn_8.png?raw=true)](images/8.png)

*   Please note: in order to be notified of host problems this user must have at least read permissions for the given host.

5\. Navigate to the **Media** tab and click on the **Add** button inside of the **Media** box.

[![](images/tn_9.png?raw=true)](images/9.png)

6\. In the new window that appears, configure the media for the user as follows:

*   For the **Type**, select **iLert** (the new media type that was created).
*   For **Send to**: paste the alert source api key that you generated in iLert.
*   Make sure the **Enabled** box is checked.
*   Click the **Add** button when you are done.

7\. Click the **Add** button at the bottom of the user page to save the user.

8\. Use the iLert User in any Actions of your choice. The text from "Action Operations" will be sent to "iLert Alert" when a problem happens.

For more information use the [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [iLert](https://docs.ilert.com/integrations/zabbix/native) documentations.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
