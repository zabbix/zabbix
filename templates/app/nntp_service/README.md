
# NNTP Service


### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|NNTP service is running||Simple check|net.tcp.service[nntp]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NNTP service is down on {HOST.NAME}||`max(/NNTP Service/net.tcp.service[nntp],#3)=0`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

