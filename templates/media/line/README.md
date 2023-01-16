# LINE webhook 

This guide describes how to integrate your Zabbix installation with LINE messenger using Zabbix webhook feature. It also provides instructions on setting up a media type, a user and an action in Zabbix.

## In LINE developer console

1. Create a messaging `channel access token` following original instructions on [How to use the messaging API](https://developers.line.biz/en/docs/messaging-api/overview/).

2. Copy the `channel access token` of your new integration to be used in Zabbix.

## In Zabbix

The configuration consists of a _Media type_ in Zabbix, which will invoke the webhook to send alerts to LINE messenger through the LINE messaging API.

1. Create a global macro `{$ZABBIX.URL}` following these instructions in [Zabbix documentation](https://www.zabbix.com/documentation/6.4/manual/config/macros/user_macros) with Zabbix frontend URL - for example, `http://192.168.7.123:8081`.

[![](images/tn_1.png?raw=true)](images/1.png)

2. Import LINE media type from this file [media_line.yaml](media_line.yaml) following these instructions in [Zabbix documentation](https://www.zabbix.com/documentation/6.4/manual/web_interface/frontend_sections/administration/mediatypes). 

[![](images/tn_2.png?raw=true)](images/2.png)

3. Change the value of the variable `bot_token` to the `channel access token`.

For more information on Zabbix webhook configuration, see [Zabbix documentation](https://www.zabbix.com/documentation/6.4/manual/config/notifications/media/webhook).

4. Set _Media type_ `LINE` for each user you would like to get notified and fill _Send to_ field with the corresponding ID of the target recipient. Use a `userId`, `groupId`, or `roomId` value. See [Common properties in webhook event objects](https://developers.line.biz/en/reference/messaging-api/#common-properties) for more information.

See more details on creating [Zabbix user](https://www.zabbix.com/documentation/6.4/manual/web_interface/frontend_sections/users/user_list).

LINE user should suffice the default settings as this user will not be logging into Zabbix. Note that in order to be notified about problems on a host, this user must have at least read permissions for this host.  
When configuring an alert action, add this user in the _Send to users_ field (in _Operation_ details) - this will tell Zabbix to use LINE webhook when sending notifications from this action.
Use the LINE user in any actions of your choice.

### Testing
Media testing can be done manually, from `Media types` page. Press `Test` button opposite to the previously defined media type, under _Actions_.
1. To create a problem, following fields should be set:
    * `alert_message` = Test message
    * `alert_subject` = Test subject
    * `bot_token` = `Channel access token`
    * `event_id` = 1234567890
    * `event_nseverity` = 5
    * `event_source` = 0 (it simulates trigger based event)
    * `event_update_status` = 0 (not an update operation)
    * `event_value` = 1 (this is a problem event)
    * `send_to` = `ID of the recipient`
    * `trigger_description` = Test trigger description
    * `trigger_id` = 0987654
    * `zabbix_url` = https://127.0.0.1

    [![](images/tn_3.png?raw=true)](images/3.png)

2. Having successfully sent a message from Zabbix, check if it has been received by the recipient.

## Supported Versions

Zabbix 6.4, LINE messaging API.

## Feedback
Please report any issues with this media type at https://support.zabbix.com.
You can also provide feedback, discuss the template, or ask for help at ZABBIX forums.
