
# IMAP Service


### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|IMAP service is running||Simple check|net.tcp.service[imap]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|IMAP service is down on {HOST.NAME}||`max(/IMAP Service/net.tcp.service[imap],#3)=0`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

