
# ICMP Ping

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ICMP ping||Simple check|icmpping|
|ICMP loss||Simple check|icmppingloss|
|ICMP response time||Simple check|icmppingsec|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Unavailable by ICMP ping|<p>Last three attempts returned timeout.  Please check device connectivity.</p>|`max(/ICMP Ping/icmpping,#3)=0`|High||
|High ICMP ping loss||`min(/ICMP Ping/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/ICMP Ping/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Unavailable by ICMP ping</li></ul>|
|High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/ICMP Ping/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>High ICMP ping loss</li><li>Unavailable by ICMP ping</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

