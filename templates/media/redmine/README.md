
# Redmine webhook
![](images/redmine_logo_v1.png?raw=true)

This guide describes how to integrate your Zabbix 6.0 installation with Redmine using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

- Redmine with enabled REST API and Authentication
- Zabbix version 6.0 or higher

## Setting up a Redmine

1\. Enable **REST API** in Administration > Settings > API. 

[![](images/thumb.01.png?raw=true)](images/01.png)

2\. Find your **API key** on your account page when logged in, on the right-hand pane of the default layout.

[![](images/thumb.03.png?raw=true)](images/03.png)

## Zabbix Webhook configuration

### Create a global macro

1\. Before setting up the **Webhook**, you need to setup the global macro **{$ZABBIX.URL}**, which must contain the **URL** to the **Zabbix frontend**.

[![](images/thumb.04.png?raw=true)](images/04.png)

2\. In the **Administration** > **Media types** section, import the [media_redmine.yaml](media_redmine.yaml)

3\. Open the added **Redmine** media type and set:

- **redmine_access_key** to the your **API key**
- **redmine_url** to the **frontend URL** of your **Redmine** installation
- **redmine_project** to your numeric Project ID or its name. Important: if you specify a project name, each time an additional API call will be made to get its identifier.<br>
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

For more information, use the [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [Redmine](https://www.redmine.org/projects/redmine/wiki/) documentations.

## Supported Versions

Zabbix 6.0
