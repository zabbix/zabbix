# iTop webhook

This guide describes how to integrate Zabbix 5.4 installation with iTop using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.<br>
Please note that recovery and update operations are supported only for trigger-based events.

## Setting up iTop
1\. Create a user for API with profile "REST Services User" or use an existing one. Make sure the user is able to create tickets in the required ticketing module.<br>
2\. Get the organization's ID. You can obtain it from the URL of organization's profile in *Data administration > Catalog > Organizations*.<br>
*&lt;itop_url&gt;/pages/UI.php?operation=details&class=Organization&**id=1**&c\[menu\]=Organization*


## Setting up the webhook in Zabbix
1\. In the *Administration > Media types* section, import [media_itop.yaml](media_itop.yaml).

2\. Open the newly added **iTop** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters are required:<br>
**itop_url** - actual URL of your iTop instance.<br>
**itop_user** - iTop user login.<br>
**itop_password** - user's password.<br>
**itop_organization_id** - ID of your organization.<br>
**itop_class** - name of the class to be used when creating new tickets from Zabbix notifications. For example, *UserRequest* or *Problem*.<br>
**itop_log** - the type of log section in the ticket for posting problem's updates from Zabbix. Must be *Private* or *Public*.<br>
**itop_comment** - the comment that will be posted to ticket's history.

3\. Create a **Zabbix user** and add **Media** with the **iTop** media type. 
Though a "Send to" field is not used in iTop webhook, it cannot be empty. To comply with frontend requirements, you can put any symbol there.
Make sure this user has access to all hosts, for which you would like problem notifications to be converted into iTop tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [iTop](https://www.itophub.io/wiki/page) documentations.

## Supported versions
Zabbix 6.0 and higher
