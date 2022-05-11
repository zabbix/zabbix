![](images/PagerDuty-GreenRGB.png?raw=true) 
# PagerDuty + Zabbix Integration Benefits
* ***Notify on-call responders based on alerts sent from Zabbix.***
* ***Send enriched event data from Zabbix including operational data that was triggered during event.***
* ***Create high and low urgency incidents based on the severity of event from Zabbix.***
* ***Problem updates are synchronised to PagerDuty from Zabbix.***
* ***Incidents will automatically resolve in PagerDuty when the metric in Zabbix returns to normal.***
# How it Works
* ***If a trigger fires, Zabbix will send an event to a service in PagerDuty. Events from Zabbix will trigger a new incident on the corresponding PagerDuty service or group as alerts into an existing incident.***
* ***Once the trigger is resolved, a resolving event will be sent to the PagerDuty service to resolve the alert and associated incident on that service.***
# Requirements
1. PagerDuty integrations with Zabbix require Events API v2 key. If you do not have permission to create Event API v2 key, please reach out to an Admin or Account Owner within your organization to help you configure the integration.
2. PagerDuty webhook integration works with Zabbix version 6.0 or higher.
# Support
* If you need help use [forum](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/393216-discussion-thread-for-official-integration-with-pagerduty) 
* If you have encountered a bug, please report it using [Zabbix Jira bug tracker](https://support.zabbix.com/).
# Description
This guide describes how to integrate your Zabbix 6.0 installation with PagerDuty using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## In PagerDuty

* From the **Service** menu, select **Service Directory**.

* On your Services page:

    *   If you are creating a new service for your integration, click **+New Service**.

    [![](images/tn_1.png?raw=true)](images/1.png)

    *   Set name and description for new service.

    [![](images/tn_1.1.png?raw=true)](images/1.png)

    *   Assign required an Escalation Policy.

    [![](images/tn_1.2.png?raw=true)](images/1.png)

    *  Select Alert Grouping.

    [![](images/tn_1.3.png?raw=true)](images/1.png)

    *  In integration section select Zabbix Webhook using search field and click **Create service**.

    [![](images/tn_1.4.png?raw=true)](images/1.png)

* If you are adding your integration to an existing service, click the name of the service you want to add the integration to. Then click the **Integrations** tab and click the **+Add an Integration** button, select Zabbix Webhook using search field and click **Add**.

    [![](images/tn_2.png?raw=true)](images/2.png)

* After successfully added integration use **Integration Key** from it in **token** macros for PagerDuty zabbix media type.

    [![](images/tn_3.png?raw=true)](images/2.png)

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

3\. Select Import file [media_pagerduty.yaml](media_pagerduty.yaml) and click the **Import** button at the bottom to import the PagerDuty media type.

4\. Change the value of the variable token

[![](images/tn_8.png?raw=true)](images/8.png)

## Create the PagerDuty user for alerting

1\. Go to the **Administration** tab.

2\. Under Administration, go to the **Users** page and click the **Create user** button.

[![](images/tn_9.png?raw=true)](images/9.png)

3\. Fill in the details of this new user, and call it “PagerDuty User”. The default settings for PagerDuty User should suffice as this user will not be logging into Zabbix.

4\. Click the **Select** button next to **Groups**.

[![](images/tn_10.png?raw=true)](images/10.png)

* Please note, that in order to notify on problems with host this user must have at least read permissions for such host.

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

For more information, use the [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [PagerDuty](https://v2.developer.pagerduty.com/docs/send-an-event-events-api-v2) documentations.

# Supported Versions

Zabbix 6.0, PagerDuty Events API v2.
