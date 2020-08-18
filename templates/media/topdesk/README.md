
# TOPdesk webhook 

This guide describes how to integrate your Zabbix installation with TOPdesk using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## In TOPdesk

1\. Create an **application password** according by original instruction https://developers.topdesk.com/tutorial.html#show-collapse-usage-createAppPassword, please.

2\. Copy the **application password** of your new integration to use it in Zabbix.

## In Zabbix

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to TOPdesk through the TOPdesk Rest API. To utilize the media type, we will create a Zabbix user to represent TOPdesk. We will then create an alert action to notify the user via this media type whenever there is a problem detected.

## Create Global Macro

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **General** page and choose **Macros** from a drop-down list.

3\. Add the macro {$ZABBIX.URL} with Zabbix frontend URL (for example http://192.168.7.123:8081)

[![](images/tn_3.png?raw=true)](images/3.png)

4\. Click the **Update** button to save the global macros.

## Create the TOPdesk media type

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Media types** page and click the **Import** button.

[![](images/tn_5.png?raw=true)](images/5.png)

3\. Select Import file [media_topdesk.xml](media_topdesk.xml) and click the **Import** button at the bottom to import the TOPdesk media type.

4\. Change the values of the variables topdesk_api (URL), topdesk_password, topdesk_user. The topdesk_status is the default status for creating a new TOPdesk ticket.

[![](images/tn_7.png?raw=true)](images/7.png)

For more information about the Zabbix Webhook configuration, please see the [documentation](https://www.zabbix.com/documentation/current/manual/config/notifications/media/webhook).

## Create the TOPdesk user for alerting

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Users** page and click the **Create user** button.

[![](images/tn_4.png?raw=true)](images/4.png)

3\. Fill in the details of this new user, and call it “TOPdesk User”. The default settings for TOPdesk User should suffice as this user will not be logging into Zabbix.

4\. Click the **Select** button next to **Groups**.

[![](images/tn_8.png?raw=true)](images/8.png)

*   Please note, that in order to be notified about problems on a host, this user must have at least read permissions for the host.

5\. Click on the **Media** tab and, inside of the **Media** box, click the **Add** button.

[![](images/tn_9.png?raw=true)](images/9.png)

6\. In the new window that appears, configure the media for the user as follows:

[![](images/tn_10.png?raw=true)](images/10.png)

*   For the **Type**, select **TOPdesk** (the new media type that was created).
*   For **Send to**: enter any text, as this value is not used, but is required.
*   Make sure the **Enabled** box is checked.
*   Click the **Add** button when done.

7\. Click the **Add** button at the bottom of the user page to save the user.

8\. Use the TOPdesk User in any actions of your choice. Text from "Action Operations" will be sent to "TOPdesk First Line Call Request" field when the problem happens. Text from "Action Recovery Operations" and "Action Update Operations" will be sent to "TOPdesk First Line Call Action" field when the problem is resolved or updated.

## Internal alerts
To receive notifications about internal problem and recovery events in TOPdesk: in the internal action configuration mark the Custom message checkbox and specify custom message templates for problem and recovery operations. 
If an internal action operation is configured without a custom message, the notification will not be sent. 
Note, that this step is required only for notifications about internal events; for other event types specifying a custom message is optional. 

For more information, please see [Zabbix](https://www.zabbix.com/documentation/current/manual/config/notifications) and [TOPdesk](https://developers.topdesk.com/documentation/index.html) documentation.

## Supported Versions

Zabbix 5.0, TOPdesk RestApi 3.1.4.
