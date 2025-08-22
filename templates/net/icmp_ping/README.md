
# ICMP Ping

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ICMP ping|<p>The host accessibility by ICMP ping.</p><p></p><p>0 - ICMP ping fails;</p><p>1 - ICMP ping successful.</p>|Simple check|icmpping|
|ICMP loss|<p>The percentage of lost packets.</p>|Simple check|icmppingloss|
|ICMP response time|<p>The ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ICMP Ping: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/ICMP Ping/icmpping,#3)=0`|High||
|ICMP Ping: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/ICMP Ping/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/ICMP Ping/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>ICMP Ping: Unavailable by ICMP ping</li></ul>|
|ICMP Ping: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/ICMP Ping/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>ICMP Ping: High ICMP ping loss</li><li>ICMP Ping: Unavailable by ICMP ping</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

