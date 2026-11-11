![](images/oncall-logo.png?raw=true)

# Grafana OnCall webhook

This guide describes how to integrate your Zabbix installation with Grafana OnCall using Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.

## In Grafana OnCall

* Read the [instructions](https://grafana.com/docs/grafana-cloud/alerting-and-irm/oncall/integrations/zabbix/).

* Use this MediaType instead of the official `grafana_oncall.sh` script.


## In Zabbix

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to Grafana OnCall through the Grafana OnCall Rest API.

1. Create a global macro `{$ZABBIX.URL}` following instructions in [Zabbix documentation](https://www.zabbix.com/documentation/7.0/manual/config/macros/user_macros)  with Zabbix frontend URL - for example, `http://192.168.7.123:8081`.

[![](images/tn_1.png?raw=true)](images/1.png)

2. Import Grafana OnCall media type from this file [media_grafana_oncall.yaml](media_grafana_oncall.yaml) following instructions in [Zabbix documentation](https://www.zabbix.com/documentation/7.0/manual/web_interface/frontend_sections/alerts/mediatypes).

[![](images/tn_2.png?raw=true)](images/2.png)

3. Change the value of variable:
	* `grafana_url` (something like https://xxxxxxxxx.grafana.net/oncall/integrations/v1/zabbix/xxxxxxxxxxxxxxxxx/);

[![](images/tn_3.png?raw=true)](images/3.png)

For more information on Zabbix webhook configuration, see [Zabbix documentation](https://www.zabbix.com/documentation/7.0/manual/config/notifications/media/webhook).

To utilize the media type, it is recommended to create a dedicated Zabbix user to represent Grafana OnCall.
See more details on creating [Zabbix user](https://www.zabbix.com/documentation/7.0/manual/web_interface/frontend_sections/users/user_list).
Grafana OnCall user should suffice the default settings as this user will not be logging into Zabbix. Note that in order to be notified about problems on a host, this user must have at least read permissions for this host.
When configuring alert action, add this user in the _Send to users_ field (in Operation details) - this will tell Zabbix to use Grafana OnCall webhook when sending notifications from this action.
Use the Grafana OnCall user in any actions of your choice. A text from "Action Operations" will be sent to "Grafana OnCall Alert" when the problem occurs. The text from "Action Recovery Operations" and "Action Update Operations" will be sent to "Grafana OnCall Alert Notes" when the problem is resolved or updated.

### Testing
Media testing can be done manually, from `Media types` page. Press `Test` button opposite to previously defined media type.
1. To create a problem following fields should be set:
    * alert_message = `MEDIA TYPE TEST`
    * alert_uid = `4815162342`
    * title = `MEDIA TYPE TEST`
    * state = `Warning`

    [![](images/tn_4.png?raw=true)](images/4.png)

2. Having successfully sent a request from Zabbix, check if it is received in Grafana OnCall alert panel (it may require refreshing).
3. To close this problem from Zabbix, change `state` to `OK` (it indicates a recovery event) on the test page and press `Test` button again to send the problem close request.
4. Confirm that problem is closed in Grafana OnCall panel.

## Supported Versions

Zabbix 6.0, Grafana OnCall Alert API.

## Feedback
Please report any issues with this media type at https://support.zabbix.com.
You can also provide feedback, discuss the template, or ask for help at ZABBIX forums.
