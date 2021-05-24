# iLert Alerting Integration


This guide describes how to integrate your Zabbix >= 4.4 installation with iLert using the Zabbix webhook feature.
This guide will provide instructions on setting up a media type, a user and an action in Zabbix.
​

## In iLert


1\. Go to **Alert sources** and click on **Add a new alert source**.

[![](images/tn_1.png?raw=true)](images/1.png)

2\. Set a name (e.g. "Zabbix") and select your desired escalation policy. Select "Zabbix" as the **Integration Type** and click **Save**.

[![](images/tn_2.png?raw=true)](images/2.png)

3\. On the next page, an **API key** is generated. You will need it when setting up the iLert media type in Zabbix.

[![](images/tn_3.png?raw=true)](images/3.png)
​

## In Zabbix


The configuration consists of a _media type_ in Zabbix which will invoke a webhook to send alerts to iLert through the iLert Event API.
To utilize the media type, we will create a Zabbix user to represent iLert. We will then create an alert action to notify the user via this media type whenever a problem is detected.

> Note: only trigger events are currently supported
​

## Create Global Macro


1\. Go to the **Administration** tab.

2\. Under Administration, go to the **General** page and choose the **Macros** from drop-down list.

3\. Add the macro {\$ZABBIX.URL} with your Zabbix frontend URL (for example http://192.168.7.123:8081)

[![](images/tn_4.png?raw=true)](images/4.png)

4\. Click the **Update** button to save the global macros.
​

## Create the iLert media type


1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Media types** page and click the **Import** button.

[![](images/tn_5.png?raw=true)](images/5.png)

3\. Select the Import file [media_ilert.yaml](media_ilert.yaml) and click **Import** at the bottom to import the iLert media type.

[![](images/tn_6.png?raw=true)](images/6.png)

4\. Optional: you can overwrite the standard incident summary with a custom template using the **.ILERT.INCIDENT.SUMMARY** variable e.g. `{TRIGGER.NAME}: {TRIGGER.STATUS} for {HOST.HOST}`
​

## Create the iLert user for alerting

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Users** page and click **Create user**.

[![](images/tn_7.png?raw=true)](images/7.png)

3\. Fill in the details of this new user, and call it "iLert User". The default settings for iLert User should suffice as this user will not be logging into Zabbix.

4\. Click the **Select** button next to **Groups**.

[![](images/tn_8.png?raw=true)](images/8.png)
​
*   Please note: in order to be notified of host problems this user must have at least read permissions for the given host.

5\. Navigate to the **Media** tab and click on the **Add** button inside of the **Media** box.

[![](images/tn_9.png?raw=true)](images/9.png)

6\. In the new window that appears, configure the media for the user as follows:
​
*   For the **Type**, select **iLert** (the new media type that was created).
*   For **Send to**: paste the alert source api key that you generated in iLert.
*   Make sure the **Enabled** box is checked.
*   Click the **Add** button when you are done.

7\. Click the **Add** button at the bottom of the user page to save the user.

8\. Use the iLert User in any Actions of your choice. The text from "Action Operations" will be sent to "iLert Alert" when a problem happens.
​
For more information use the [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [iLert](https://docs.ilert.com/integrations/zabbix/native) documentations.
​

## Supported Versions

​
Zabbix >=4.4, iLert API v1.
