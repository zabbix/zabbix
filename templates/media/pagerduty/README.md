
This guide describes how to integrate your Zabbix 4.4 installation with PagerDuty using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## In PagerDuty

1\. From the **Configuration** menu, select **Services.**

2\. On your Services page:

*   If you are creating a new service for your integration, click **+New Service**.

[![](images/tn_1.png?raw=true)](images/1.png)

* If you are adding your integration to an existing service, click the name of the service you want to add the integration to. Then click the **Integrations** tab and click the **+New Integration** button.

[![](images/tn_2.png?raw=true)](images/2.png)

3\. Select **Use our API directly** and **Events API v2** from the **Integration Type** menu and enter an **Integration Name**. If you are creating a new service for your integration, in General Settings, enter a **Name** for your new service.

4\. Click the **Add Service** or **Add Integration** button to save your new integration. You will be redirected to the Integrations page for your service.

[![](images/tn_3.png?raw=true)](images/3.png)

[![](images/tn_4.png?raw=true)](images/4.png)

5\. Copy the **Integration Key** for your new integration:

[![](images/tn_5.png?raw=true)](images/5.png)

## In Zabbix

The configuration consists of a _media type_ in Zabbix, which will invoke webhook to send alerts to PagerDuty through the PagerDuty Event API v2\. To utilize the media type, we will create a Zabbix user to represent PagerDuty. We will then create an alert action to notify the user via this media type whenever there is a problem detected.

## Create Global Macro

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **General** page and choose the **Macros** from drop-down list.

3\. Add the macro {$ZABBIX.URL} with Zabbix frontend URL (for example http://192.168.7.123:8081).

[![](images/tn_6.png?raw=true)](images/6.png)

4\. Click the **Update** button to save the global macros.

## Create the PagerDuty media type

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Media types** page and click the **Import** button.

[![](images/tn_7.png?raw=true)](images/7.png)

3\. Select Import file [media_pagerduty.xml](media_pagerduty.xml) and click the **Import** button at the bottom to import the PagerDuty media type.

4\. Change the value of the variable token

[![](images/tn_8.png?raw=true)](images/8.png)

## Create the PagerDuty user for alerting

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Users** page and click the **Create user** button.

[![](images/tn_9.png?raw=true)](images/9.png)

3\. Fill in the details of this new user, and call it “PagerDuty User”. The default settings for PagerDuty User should suffice as this user will not be logging into Zabbix.

4\. Click the **Select** button next to **Groups**.

[![](images/tn_10.png?raw=true)](images/10.png)

* Please note, that in order to notify on problems with host this user must has at least read permissions for such host.

5\. Click on the **Media** tab and, inside of the **Media** box, click the **Add** button.

[![](images/tn_11.png?raw=true)](images/11.png)

6\. In the new window that appears, configure the media for the user as follows:

[![](images/tn_12.png?raw=true)](images/12.png)

* For the **Type**, select **PagerDuty** (the new media type that was created).
* For **Send to**: enter any text, as this value is not used, but is required.
* Make sure the **Enabled** box is checked.
* Click the **Add** button when done.

7\. Click the **Add** button at the bottom of the user page to save the user.

8\. Use the PagerDuty User in any Actions of your choice.

For more information, use the [Zabbix](https://www.zabbix.com/documentation/current/manual/config/notifications) and [PagerDuty](https://v2.developer.pagerduty.com/docs/send-an-event-events-api-v2) documentations.

# Supported Versions

Zabbix 4.4, PagerDuty Events API v2.
