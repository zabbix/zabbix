
# Jira Data Center by JMX

## Overview

This template is used for monitoring Jira Data Center health. It is designed for standalone operation for on-premises Jira installations.

This template uses a single data source, JMX, which requires JMX RMI setup of your Jira application and Java Gateway setup on the Zabbix side.
If you need "Garbage collector" and "Web server" monitoring, add "Generic Java JMX" and "Apache Tomcat by JMX" templates on the same host.

## Requirements

Zabbix version: 7.2 and higher.

## Tested versions

This template has been tested on:
- Jira Data Center 9.14.1
- Jira Data Center 9.12.4

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.2/manual/config/templates_out_of_the_box) section.

## Setup

Metrics are collected by JMX.
0. Deploy the Zabbix Java Gateway component ([instructions](https://www.zabbix.com/documentation/7.2/manual/concepts/java)).
1. Enable and configure JMX access to Jira Data Center. See documentation for [instructions](https://confluence.atlassian.com/adminjiraserver/live-monitoring-using-the-jmx-interface-939707304.html).
2. Assign the "Jira Data Center by JMX" template to the host with a JMX interface.
2. If your Jira installation requires authentication for JMX, set the values in the host macros `{$JMX.USERNAME}` and `{$JMX.PASSWORD}`.
3. (Optional) Set custom macro values and add macros with context for specific metrics following the macro description.
4. (Optional) Assign the "Generic Java JMX" template for garbage collector monitoring.
5. (Optional) Assign the "Apache Tomcat by JMX" template for web server monitoring.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$JMX.USER}|<p>User for JMX.</p>||
|{$JMX.PASSWORD}|<p>Password for JMX.</p>||
|{$JIRA_DC.LICENSE.USER.CAPACITY.WARN}|<p>User capacity warning threshold (%).</p>|`80`|
|{$JIRA_DC.DB.CONNECTION.USAGE.WARN}|<p>Warning threshold for database connections usage (%).</p>|`80`|
|{$JIRA_DC.ISSUE.LATENCY.WARN}|<p>Warning threshold for issue operation latency (in seconds).</p>|`5`|
|{$JIRA_DC.STORAGE.LATENCY.WARN}|<p>Warning threshold for storage write operation latency (in seconds).</p>|`5`|
|{$JIRA_DC.INDEXING.LATENCY.WARN}|<p>Warning threshold for indexing operation latency (in seconds).</p>|`5`|
|{$JIRA_DC.LLD.FILTER.MATCHES.HOMEFOLDERS}|<p>Used for storage metric discovery.</p>|`local\|share`|
|{$JIRA_DC.LLD.FILTER.NOT.MATCHES.HOMEFOLDERS}|<p>Used for storage metric discovery.</p>|`NO MATCH`|
|{$JIRA_DC.LLD.FILTER.MATCHES.INDEXING}|<p>Used for indexing metric discovery.</p>|`.*`|
|{$JIRA_DC.LLD.FILTER.NOT.MATCHES.INDEXING}|<p>Used for indexing metric discovery.</p>|`NO MATCH`|
|{$JIRA_DC.LLD.FILTER.MATCHES.ISSUE}|<p>Used for issue discovery.</p>|`.*`|
|{$JIRA_DC.LLD.FILTER.NOT.MATCHES.ISSUE}|<p>Used for issue discovery.</p>|`NO MATCH`|
|{$JIRA_DC.LLD.FILTER.MATCHES.MAIL}|<p>Used for mail server connection metric discovery.</p>|`.*`|
|{$JIRA_DC.LLD.FILTER.NOT.MATCHES.MAIL}|<p>Used for mail server connection metric discovery.</p>|`NO MATCH`|
|{$JIRA_DC.LLD.FILTER.MATCHES.LICENSE}|<p>Used for license discovery.</p>|`.*`|
|{$JIRA_DC.LLD.FILTER.NOT.MATCHES.LICENSE}|<p>Used for license discovery.</p>|`NO MATCH`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|DB: Connections: State|<p>The state of the database connection.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=db,category01=connection,category02=state,name=value",Value]|
|DB: Connections: Failed per minute|<p>The count of database connection failures registered in one minute.</p><p>Units: fpm - fails per minute.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=db,category01=connection,category02=failures,name=counter",Count]<p>**Preprocessing**</p><ul><li>Simple change: </li></ul>|
|DB: Pool: Connections: Idle|<p>Idle connection count of the database pool.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=db,category01=connection,category02=pool,category03=numIdle,name=value",Value]|
|DB: Pool: Connections: Active|<p>Active connection count of the database pool.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=db,category01=connection,category02=pool,category03=numActive,name=value",Value]|
|DB: Reads|<p>Database read operations from Jira per second.</p><p>Units: rps - read operations per second.</p>|JMX agent|jmx["com.atlassian.jira:type=db.reads",invocation\.count]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|DB: Writes|<p>Database write operations from Jira per second.</p><p>Units: wps - write operations per second.</p>|JMX agent|jmx["com.atlassian.jira:type=db.writes",invocation\.count]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|DB: Connections: Limit|<p>Total allowed database connection count.</p>|JMX agent|jmx["com.atlassian.jira:name=BasicDataSource,connectionpool=connections",MaxTotal]|
|DB: Connections: Active|<p>Active database connection count.</p>|JMX agent|jmx["com.atlassian.jira:name=BasicDataSource,connectionpool=connections",NumActive]|
|DB: Connections: Latency|<p>The latest measure of latency when querying the database.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=db,category01=connection,category02=latency,name=value",Value]|
|License: Users: Get|<p>License data for the discovery rule.</p>|JMX agent|jmx.discovery[attributes,"com.atlassian.jira:type=jira.license"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|HTTP: Pool: Connections: Active|<p>The latest measure of the number of active connections in the HTTP connection pool.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=http,category01=connection,category02=pool,category03=numActive,name=value",Value]|
|HTTP: Pool: Connections: Idle|<p>The latest measure of the number of idle connections in the HTTP connection pool.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=http,category01=connection,category02=pool,category03=numIdle,name=value",Value]|
|HTTP: Sessions: Active|<p>The latest measure of the number of active user sessions.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=http,category01=connection,category02=sessions,category03=active,name=value",Value]|
|HTTP: Requests per minute|<p>The latest measure of the total number of HTTP requests per minute.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=http,category01=requests,name=value",Value]|
|Mail: Queue|<p>The latest measure of the number of items in a mail queue.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=mail,category01=queue,category02=numItems,name=value",Value]|
|Mail: Queue: Error|<p>The latest measure of the number of items in an error mail queue.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=mail,category01=queue,category02=numErrors,name=value",Value]|
|Mail: Sent per minute|<p>The latest measure of the number of emails sent by the SMTP server per minute.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=mail,category01=queue,category02=numEmailsSentPerMin,name=value",Value]|
|Mail: Processed per minute|<p>The latest measure of the number of items processed by a mail queue per minute.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=mail,category01=queue,category02=numItemsProcessedPerMin,name=value",Value]|
|Mail: Queue: Processing state|<p>The latest indicator of the state of a mail queue job.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=mail,category01=queue,category02=jobRunning,name=value",Value]|
|Entity: Issues|<p>The number of issues.</p>|JMX agent|jmx["com.atlassian.jira:type=entity.issues.total",Value]|
|Entity: Attachments|<p>The number of attachments.</p>|JMX agent|jmx["com.atlassian.jira:type=entity.attachments.total",Value]|
|Entity: Components|<p>The number of components.</p>|JMX agent|jmx["com.atlassian.jira:type=entity.components.total",Value]|
|Entity: Custom fields|<p>The number of custom fields.</p>|JMX agent|jmx["com.atlassian.jira:type=entity.customfields.total",Value]|
|Entity: Filters|<p>The number of filters.</p>|JMX agent|jmx["com.atlassian.jira:type=entity.filters.total",Value]|
|Entity: Versions created|<p>The number of versions created.</p>|JMX agent|jmx["com.atlassian.jira:type=entity.versions.total",Value]|
|Issue: Search per minute|<p>Issue searches performed per minute.</p>|JMX agent|jmx["com.atlassian.jira:type=issue.search.count",Value]<p>**Preprocessing**</p><ul><li>Simple change: </li></ul>|
|Issue: Created per minute|<p>Issues created per minute.</p>|JMX agent|jmx["com.atlassian.jira:type=issue.created.count",Value]<p>**Preprocessing**</p><ul><li>Simple change: </li></ul>|
|Issue: Updates per minute|<p>Issue updates performed per minute.</p>|JMX agent|jmx["com.atlassian.jira:type=issue.updated.count",Value]<p>**Preprocessing**</p><ul><li>Simple change: </li></ul>|
|Quicksearch: Concurrent searches|<p>The number of concurrent searches that are being performed in real-time by using the quick search.</p>|JMX agent|jmx["com.atlassian.jira:type=quicksearch.concurrent.search",Value]<p>**Preprocessing**</p><ul><li>Simple change: </li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|DB: Connection lost|<p>Database connection lost</p>|`max(/Jira Data Center by JMX/jmx["com.atlassian.jira:type=metrics,category00=db,category01=connection,category02=state,name=value",Value],3m)=0`|Average|**Manual close**: Yes|
|DB: Pool: Out of idle connections|<p>Fires when out of idle connections in database pool for 5 minutes.</p>|`min(/Jira Data Center by JMX/jmx["com.atlassian.jira:type=metrics,category00=db,category01=connection,category02=pool,category03=numIdle,name=value",Value],5m)<=0`|Warning|**Manual close**: Yes|
|DB: Connection usage is near the limit||`100*min(/Jira Data Center by JMX/jmx["com.atlassian.jira:name=BasicDataSource,connectionpool=connections",NumActive],5m)/last(/Jira Data Center by JMX/jmx["com.atlassian.jira:name=BasicDataSource,connectionpool=connections",MaxTotal])>{$JIRA_DC.DB.CONNECTION.USAGE.WARN}`|Warning|**Manual close**: Yes|
|DB: Connection limit reached||`min(/Jira Data Center by JMX/jmx["com.atlassian.jira:name=BasicDataSource,connectionpool=connections",NumActive],5m)=last(/Jira Data Center by JMX/jmx["com.atlassian.jira:name=BasicDataSource,connectionpool=connections",MaxTotal])`|Warning|**Manual close**: Yes|
|HTTP: Pool: Out of idle connections|<p>All available connections are utilized. It can cause outages for users as the system is unable to serve their requests.</p>|`min(/Jira Data Center by JMX/jmx["com.atlassian.jira:type=metrics,category00=http,category01=connection,category02=pool,category03=numIdle,name=value",Value],5m)<=0`|Warning|**Manual close**: Yes|
|Mail: Queue: Doesn’t empty over an extended period|<p>Might indicate SMTP performance or connection problems.</p>|`min(/Jira Data Center by JMX/jmx["com.atlassian.jira:type=metrics,category00=mail,category01=queue,category02=numItems,name=value",Value],30m)>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Mail: Queue job is not running</li></ul>|
|Mail: Error queue contains one or more items|<p>A mail queue attempts to resend items up to 10 times. If the operation fails for the 11th time, the items are put into an error mail queue.<br>You can remove items from the error mail queue in one of the following ways:<br>  - Manually clear the whole error queue.<br>  - Manually resend all items from the error queue to a mail queue.<br>You should pay attention to the cases where an error mail queue item gets back to an error mail queue after you resend the items manually. These cases might indicate permanent performance issues.</p>|`max(/Jira Data Center by JMX/jmx["com.atlassian.jira:type=metrics,category00=mail,category01=queue,category02=numErrors,name=value",Value],5m)>0`|Warning|**Manual close**: Yes|
|Mail: Queue job is not running|<p>It should be running when its queue is not empty.<br>Might indicate SMTP server connection problems.</p>|`max(/Jira Data Center by JMX/jmx["com.atlassian.jira:type=metrics,category00=mail,category01=queue,category02=jobRunning,name=value",Value],15m)=0 and min(/Jira Data Center by JMX/jmx["com.atlassian.jira:type=metrics,category00=mail,category01=queue,category02=numItems,name=value",Value],15m)>0`|Average|**Manual close**: Yes|

### LLD rule Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage discovery|<p>Discovery of the Jira storage metrics.</p>|JMX agent|jmx.discovery[beans,"com.atlassian.jira:type=metrics,category00=home,category01=*,category02=write,category03=latency,*,name=value"]|

### Item prototypes for Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage [{#JMXCATEGORY01}]: Latency|<p>The median latency of writing a small file (~30 bytes) to `{#JMXCATEGORY01}`.</p>|JMX agent|jmx["{#JMXOBJ}",Value]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Trigger prototypes for Storage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Storage [{#JMXCATEGORY01}]: Slow performance|<p>Fires when latency grows above the threshold: `{$JIRA_DC.STORAGE.LATENCY.WARN:"{#JMXCATEGORY01}"}`s</p>|`min(/Jira Data Center by JMX/jmx["{#JMXOBJ}",Value],5m)>{$JIRA_DC.STORAGE.LATENCY.WARN:"{#JMXCATEGORY01}"}`|Warning|**Manual close**: Yes|

### LLD rule Mail server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mail server discovery|<p>Discovery of the Jira connected mail servers.</p>|JMX agent|jmx.discovery[beans,"com.atlassian.jira:type=metrics,category00=mail,category01=*,category02=connection,category03=state,name=*"]|

### Item prototypes for Mail server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mail [{#JMXCATEGORY01},{#JMXNAME}]: Connection state|<p>Shows connection state of Jira to discovered mail server: `{#JMXCATEGORY01}-{#JMXNAME}`</p>|JMX agent|jmx["{#JMXOBJ}",Connected]<p>**Preprocessing**</p><ul><li>Boolean to decimal: </li></ul>|
|Mail [{#JMXCATEGORY01},{#JMXNAME}]: Failures per minute|<p>Count of failed connections to discovered mail server `{#JMXCATEGORY01}-{#JMXNAME}` per minute</p>|JMX agent|jmx["{#JMXOBJ}",TotalFailures]<p>**Preprocessing**</p><ul><li>Simple change: </li></ul>|

### Trigger prototypes for Mail server discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Mail [{#JMXCATEGORY01}-{#JMXNAME}]: Server disconnected|<p>Trigger is fired when discovered mail server `{#JMXCATEGORY01}-{#JMXNAME}` becomes unavailable</p>|`max(/Jira Data Center by JMX/jmx["{#JMXOBJ}",Connected],5m)=0`|Average|**Manual close**: Yes|

### LLD rule  Indexing latency discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Indexing latency discovery|<p>Discovery of the Jira indexing metrics.</p>|JMX agent|jmx.discovery[beans,"com.atlassian.jira:type=metrics,category00=indexing,name=*"]|

### Item prototypes for  Indexing latency discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Indexing [{#JMXNAME}]: Latency|<p>Average time spent on indexing operations.</p>|JMX agent|jmx["com.atlassian.jira:type=metrics,category00=indexing,name={#JMXNAME}",Mean]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Trigger prototypes for  Indexing latency discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Indexing [{#JMXNAME}]: Slow performance|<p>Fires when latency grows above the threshold: `{$JIRA_DC.INDEXING.LATENCY.WARN:"{#JMXNAME}"}`s</p>|`min(/Jira Data Center by JMX/jmx["com.atlassian.jira:type=metrics,category00=indexing,name={#JMXNAME}",Mean],5m)>{$JIRA_DC.INDEXING.LATENCY.WARN:"{#JMXNAME}"}`|Warning|**Manual close**: Yes|

### LLD rule  Issue latency discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Issue latency discovery|<p>Discovery of the Jira issue latency metrics.</p>|JMX agent|jmx.discovery[beans,"com.atlassian.jira:type=metrics,category00=issue,name=*"]|

### Item prototypes for  Issue latency discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Issue [{#JMXNAME}]: Latency|<p>Average time spent on issue `{#JMXNAME}` operations.</p>|JMX agent|jmx["{#JMXOBJ}",Mean]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Trigger prototypes for  Issue latency discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Issue [{#JMXNAME}]: Slow operations|<p>Fires when latency grows above the threshold: `{$JIRA_DC.ISSUE.LATENCY.WARN:"{#JMXNAME}"}`s</p>|`min(/Jira Data Center by JMX/jmx["{#JMXOBJ}",Mean],5m)>{$JIRA_DC.ISSUE.LATENCY.WARN:"{#JMXNAME}"}`|Warning|**Manual close**: Yes|

### LLD rule  License discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|License discovery|<p>Discovery of the Jira licenses.</p>|Dependent item|jmx.license.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for  License discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|License [{#LICENSE.TYPE}]: Users: Current|<p>Current user count for `{#LICENSE.TYPE}`.</p>|Dependent item|jmx.license.get.user.current["{#LICENSE.TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#LICENSE.TYPE}.properties.current_user_count`</p></li></ul>|
|License [{#LICENSE.TYPE}]: Users: Maximum|<p>User count limit for `{#LICENSE.TYPE}`.</p><p>`-1` = No limits for the license type.</p>|Dependent item|jmx.license.get.user.max["{#LICENSE.TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.{#LICENSE.TYPE}.properties.max_user_count`</p></li></ul>|

### Trigger prototypes for  License discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|License [{#LICENSE.TYPE}]: Low user capacity|<p>Fires when relative user quantity grows above the threshold: `{$JIRA_DC.LICENSE.USER.CAPACITY.WARN:"{#LICENSE.TYPE}"}`%</p>|`last(/Jira Data Center by JMX/jmx.license.get.user.max["{#LICENSE.TYPE}"])>=0 * (100*last(/Jira Data Center by JMX/jmx.license.get.user.current["{#LICENSE.TYPE}"])/last(/Jira Data Center by JMX/jmx.license.get.user.max["{#LICENSE.TYPE}"])>{$JIRA_DC.LICENSE.USER.CAPACITY.WARN:"{#LICENSE.TYPE}"})`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>License [{#LICENSE.TYPE}]: User count reached the limit</li></ul>|
|License [{#LICENSE.TYPE}]: User count reached the limit|<p>Fires when user quantity reaches the limit.<br>It won't fire if the limit is disabled (set to `-1`).</p>|`last(/Jira Data Center by JMX/jmx.license.get.user.max["{#LICENSE.TYPE}"])>=0 * ((last(/Jira Data Center by JMX/jmx.license.get.user.max["{#LICENSE.TYPE}"])-last(/Jira Data Center by JMX/jmx.license.get.user.current["{#LICENSE.TYPE}"]))<=0)`|Average|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

