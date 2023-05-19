
# SSH Service


### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SSH service is running||Simple check|net.tcp.service[ssh]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|SSH service is down on {HOST.NAME}||`max(/SSH Service/net.tcp.service[ssh],#3)=0`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

