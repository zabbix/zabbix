
# Zammad webhook
![](images/zammad_logo.png?raw=true)

This guide describes how to integrate your Zabbix 5.0 installation with Zammad using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

- Zammad with enabled HTTP Token Authentication
- Zabbix version 5.0 or higher

## Setting up a Zammad

1\. Enable **API Token Access** in Settings > System > API. 

[![](images/thumb.01.png?raw=true)](images/01.png)

2\. Create a **new user** for a Zabbix alerter with an **email address** and create a personal user token with **ticket.agent** permissions.

[![](images/thumb.02.png?raw=true)](images/02.png)

## Zabbix Webhook configuration

### Create a global macro

1\. Before setting up the **Webhook**, you need to setup the global macro **{$ZABBIX.URL}**, which must contain the **URL** to the **Zabbix frontend**.

[![](images/thumb.03.png?raw=true)](images/03.png)

2\. In the **Administration** > **Media types** section, import the [media_zammad.yaml](media_zammad.yaml)

3\. Open the added **Zammad** media type and set:

- **zammad_access_token** to the your **Personal User Token**
- **zammad_url** to the **frontend URL** of your **Zammad** installation
- **zammad_customer** to your **Zammad user email**.
- **zammad_enable_tags** to **true** or **false** to enable or disable trigger tags. **Important**: if you enable tag support, each tag is set with a separate request.

[![](images/thumb.04.png?raw=true)](images/04.png)

4\. If you want to prioritize issues according to **severity** values in Zabbix, you can define mapping parameters:

- **severity_\<name\>**: Zammad priority ID

[![](images/thumb.05.png?raw=true)](images/05.png)

6\. Click the **Update** button to save the **Webhook** settings.

7\. To receive notifications in **Zammad**, you need to create a **Zabbix user** and add **Media** with the **Zammad** type.

For **Send to**: enter any text, as this value is not used, but is required.

[![](images/thumb.06.png?raw=true)](images/06.png)

For more information, use the [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [Zammad](https://zammad.org/documentation) documentations.

## Supported Versions

Zabbix 5.0
