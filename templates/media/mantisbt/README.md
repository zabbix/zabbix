![](images/logo.png?raw=true)

# Mantis Bug Tracker webhook

This guide describes how to integrate your Zabbix installation with MantisBT issues using Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## In MantisBT

1\. Create or use an existing **project** for creating issues.

[![](images/project_tn.png?raw=true)](images/project.png)

2\. Create or use an existing user in MantisBT with the permission to create issues in the desired project.
You can check the [instruction](https://support.mantishub.com/hc/en-us/articles/203574829-Creating-User-Accounts) how to do it.

[![](images/user_tn.png?raw=true)](images/user.png)

3\. Create an **access token** according to the original [instruction](https://support.mantishub.com/hc/en-us/articles/215787323-Connecting-to-MantisHub-APIs-using-API-Tokens).

[![](images/token_tn.png?raw=true)](images/token.png)

4\. Copy the **access token** to use it in Zabbix.
<br/><br/>

## In Zabbix

MantisBT _media type_ must be configured in Zabbix, which will invoke the webhook to send alerts to MantisBT issues through [MantisBT Rest API](https://www.mantisbt.org/docs/master/en-US/Developers_Guide/html/restapi.html).

1\. [Import](https://www.zabbix.com/documentation/7.0/manual/web_interface/frontend_sections/administration/mediatypes) MantisBT media type from [media_mantisbt.yaml](media_mantisbt.yaml) file.

2\. Change values of the following parameters in the imported media:

- mantisbt_category - category of the issues to be created. Default value: "[All Projects] General"
- mantisbt_token - MantisBT **access token**
- mantisbt_url - MantisBT URL address
- mantisbt_use_zabbix_tags - true|false - whether Zabbix tags should be assigned to the issues. Default value: "true"

[![](images/media_type_tn.png?raw=true)](images/media_type.png)

3\. Create a user and add MantisBT media type to it. Use your MantisBT project name in the "Send to" field.

[![](images/zabbix_user_tn.png?raw=true)](images/zabbix_user.png)

4\. Set up a global macro {$ZABBIX.URL} with the current Zabbix URL. Please note that HTTPS will be used by default if HTTP/HTTPS schema is not present in the URL.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.0/manual/config/notifications) and [MantisBT](https://www.mantisbt.org/documentation.php) documentation.
<br/><br/>

## Supported Versions

Zabbix 7.0, MantisBT 2.22
