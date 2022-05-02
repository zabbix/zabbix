
# GLPi webhook 

## About webhook

This webhook creates problems in GLPi Assistance section. Created problems have the next severity mapping:

|Severity In zabbix|Urgency in GLPi|
|-|-|
0 - Not classified| Medium (default)|
1 - Information| Very low|
2 - Warning| Low|
3 - Average| Medium|
4 - High| High|
5 - Disaster| Very High|

On Update action in zabbix, webhook updates created problem's title, severity and creates followup with update comment.

On resolve action, webhook updates created problem title and creates followup with resolve information.

Created problems have "New" status, and resolved - "Solved" status.

Due to the specifics of the webhook, the number of retries is set to 1 by default. We recommend that you do not change this setting, because in case of a transaction error, additional duplicate objects (problems, followups) may be created during the retry.

## Installation guide

This guide describes how to integrate your Zabbix installation with GLPi problems using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.
<br/><br/>
## In GLPi

1\. Create or use existing user in GLPi with permission to create problems and followups. 
[![](images/1.thumb.png?raw=true)](images/1.png)
[![](images/2.thumb.png?raw=true)](images/2.png)

2\. Please create an **API token**. For that you should go into user profile and set tick in "Regenerate" field against "API token" and hit save.
[![](images/3.thumb.png?raw=true)](images/3.png)


3\. Copy the **API token** of your new integration to use it in Zabbix.
<br/><br/>
## In Zabbix

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to GLPi problems through the GLPi Rest API.


1\. [Import](https://www.zabbix.com/documentation/6.0/manual/web_interface/frontend_sections/administration/mediatypes) the GLPi media type from file [media_glpi.yaml](media_glpi.yaml).

2\. Change in the imported media the values of the variable *glpi_token* and *glpi_url*.


For more information about the Zabbix Webhook configuration, please see the [documentation](https://www.zabbix.com/documentation/6.0/manual/config/notifications/media/webhook).

3\. Create a **Zabbix user** and add **Media** with the **GLPi** media type. 
Though a "Send to" field is not used in GLPi webhook, it cannot be empty. To comply with frontend requirements, you can put any symbol there.
Make sure this user has access to all hosts for which you would like problem notifications to be converted into GLPi problems.

4\. Set up a global macro {$ZABBIX.URL} with URL of current zabbix. Please notice that HTTPS will be used by default if HTTP/HTTPS schema is not present in the URL.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [GLPi](https://glpi-project.org/DOC/EN/) documentation.
<br/><br/>

## Tested on 
GLPI 9.5.7
<br/><br/>
## Supported Versions

Zabbix 6.0
