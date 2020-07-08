
# Opsgenie webhook 

This guide describes how to integrate your Zabbix 4.4 installation with Opsgenie using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## In Opsgenie

1\. From the **Settings** menu, select **Integration list** and push **Add** on Rest API HTTPS over JSON.

[![](images/tn_1.png?raw=true)](images/1.png)

2\. Copy the **API Key** for your new integration and push **Save Integration** at the bottom of frame.

[![](images/tn_2.png?raw=true)](images/2.png)

*   You can make finer adjustments later.

## In Zabbix

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to Opsgenie through the Opsgenie Rest API. To utilize the media type, we will create a Zabbix user to represent Opsgenie. We will then create an alert action to notify the user via this media type whenever there is a problem detected.

## Create Global Macro

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **General** page and choose **Macros** from a drop-down list.

3\. Add the macro {$ZABBIX.URL} with Zabbix frontend URL (for example http://192.168.7.123:8081)

[![](images/tn_3.png?raw=true)](images/3.png)

4\. Click the **Update** button to save the global macros.

## Create the Opsgenie media type

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Media types** page and click the **Import** button.

[![](images/tn_5.png?raw=true)](images/5.png)

3\. Select Import file [media_opsgenie.xml](media_opsgenie.xml) and click the **Import** button at the bottom to import the Opsgenie media type.

4\. Change the values of the variables opsgenie_api (https://api.opsgenie.com/v2/alerts or https://api.eu.opsgenie.com/v2/alerts) , opsgenie_web (for example, https://myzabbix.app.opsgenie.com), opsgenie_token.
Also you could set own tags into opsgenie_tags as <comma_separated_list_of_tags> and team names into opsgenie_teams as <comma_separated_list_of_responders>.  
The priority level in severity_default will be used for non-triggered actions.

[![](images/tn_7.png?raw=true)](images/7.png)

## Create the Opsgenie user for alerting

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Users** page and click the **Create user** button.

[![](images/tn_4.png?raw=true)](images/4.png)

3\. Fill in the details of this new user, and call it “Opsgenie User”. The default settings for Opsgenie User should suffice as this user will not be logging into Zabbix.

4\. Click the **Select** button next to **Groups**.

[![](images/tn_8.png?raw=true)](images/8.png)

*   Please note, that in order to be notified about problems on a host, this user must have at least read permissions for the host.

5\. Click on the **Media** tab and, inside of the **Media** box, click the **Add** button.

[![](images/tn_9.png?raw=true)](images/9.png)

6\. In the new window that appears, configure the media for the user as follows:

[![](images/tn_10.png?raw=true)](images/10.png)

*   For the **Type**, select **Opsgenie** (the new media type that was created).
*   For **Send to**: enter any text, as this value is not used, but is required.
*   Make sure the **Enabled** box is checked.
*   Click the **Add** button when done.

7\. Click the **Add** button at the bottom of the user page to save the user.

8\. Use the Opsgenie User in any actions of your choice. Text from "Action Operations" will be sent to "Opsgenie Alert" when the problem happens. Text from "Action Recovery Operations" and "Action Update Operations" will be sent to "Opsgenie Alert Notes" when the problem is resolved or updated.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/current/manual/config/notifications) and [Opsgenie](https://docs.opsgenie.com/docs/alert-api) documentation.

## Supported Versions

Zabbix 5.0, Opsgenie Alert API.
