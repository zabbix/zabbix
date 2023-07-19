
# NTP Service


### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|NTP service is running||Simple check|net.udp.service[ntp]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|NTP service is down on {HOST.NAME}||`max(/NTP Service/net.udp.service[ntp],#3)=0`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

