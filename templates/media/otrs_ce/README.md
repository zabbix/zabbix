
# ((OTRS)) Community Edition webhook
![](images/otrs_logo.png?raw=true)

This guide describes how to integrate your Zabbix 6.4 installation with ((OTRS)) Community Edition, hereinafter - "((OTRS)) CE", using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

- ((OTRS)) CE version 6
- Zabbix version 6.4 or higher

## Setting up a ((OTRS)) CE

1\. Import [ZabbixTicketConnector.yml](ZabbixTicketConnector.yml) in Admin > Web Services.

[![](images/thumb.01.png?raw=true)](images/01.png)
[![](images/thumb.02.png?raw=true)](images/02.png)
[![](images/thumb.03.png?raw=true)](images/03.png)

2\. Create a **new user** for a Zabbix alerter with an **email address**.

[![](images/thumb.04.png?raw=true)](images/04.png)
[![](images/thumb.05.png?raw=true)](images/05.png)

## Zabbix Webhook configuration

### Create a global macro

1\. Before setting up the **Webhook**, you need to setup the global macro **{$ZABBIX.URL}**, which must contain the **URL** to the **Zabbix frontend**.

[![](images/thumb.06.png?raw=true)](images/06.png)

2\. In the **Administration** > **Media types** section, import the [media_otrs_ce.yaml](media_otrs_ce.yaml)

3\. Open the added **((OTRS)) CE** media type and set:

- **otrs_auth_user** to the your **Agent username**
- **otrs_auth_password** to the your **Agent password**
- **otrs_customer** to your **((OTRS)) CE customer email**
- **otrs_queue** to your **((OTRS)) CE ticket queue**
- **otrs_url** to the **frontend URL** of your **((OTRS)) CE** installation

[![](images/thumb.07.png?raw=true)](images/07.png)

4\. If you want to prioritize issues according to **severity** values in Zabbix, you can define mapping parameters:

- **severity_\<name\>**: ((OTRS)) CE priority ID

[![](images/thumb.08.png?raw=true)](images/08.png)

5\. If you have **dynamic fields** in **((OTRS)) CE** and you want them to be filled with values from Zabbix, add parameters in the form **dynamicfield_\<((OTRS)) CE dynamic field name\>**. Dynamic fields can only be of the **text**, **textarea**, **checkbox**, or **date** types.

6\. Click the **Update** button to save the **Webhook** settings.

7\. To receive notifications in **((OTRS)) CE**, you need to create a **Zabbix user** and add **Media** with the **((OTRS)) CE** type.

For **Send to**: enter any text, as this value is not used, but is required.

[![](images/thumb.09.png?raw=true)](images/09.png)

For more information, use the [Zabbix](https://www.zabbix.com/documentation/6.4/manual/config/notifications) and [((OTRS)) CE](https://otrscommunityedition.com/doc/) documentations.

## Supported Versions

Zabbix 6.4
