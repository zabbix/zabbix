
# AWS ELB Application Load Balancer by HTTP

## Overview

*Please scroll down for AWS ELB Network Load Balancer by HTTP.*

The template is designed to monitor AWS ELB Application Load Balancer by HTTP via Zabbix, and it works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the [CloudWatch pricing](https://aws.amazon.com/cloudwatch/pricing/) page.

Additional information about metrics and API methods used in the template:
* [Full metrics list related to AWS ELB Application Load Balancer](https://docs.aws.amazon.com/elasticloadbalancing/latest/application/load-balancer-cloudwatch-metrics.html)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)
* [DescribeTargetGroups API method](https://docs.aws.amazon.com/elasticloadbalancing/latest/APIReference/API_DescribeTargetGroups.html)


## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- AWS ELB Application Load Balancer with Target Groups by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS ELB Application Load Balancer metrics and uses the script item to make HTTP requests to the CloudWatch API.

Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account. For more information, visit the [ELB policies page](https://docs.aws.amazon.com/elasticloadbalancing/latest/userguide/elb-api-permissions.html) on the AWS website.

Add the following required permissions to your Zabbix IAM policy in order to collect AWS ELB Application Load Balancer metrics.
```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
              "cloudwatch:DescribeAlarms",
              "cloudwatch:GetMetricData",
              "elasticloadbalancing:DescribeTargetGroups"
          ],
          "Effect":"Allow",
          "Resource":"*"
        }
    ]
  }
  ```

If you are using role-based authorization, set the appropriate permissions:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "iam:PassRole",
            "Resource": "arn:aws:iam::<<--account-id-->>:role/<<--role_name-->>"
        },
        {
            "Sid": "VisualEditor1",
            "Effect": "Allow",
            "Action": [
                "cloudwatch:DescribeAlarms",
                "cloudwatch:GetMetricData",
                "elasticloadbalancing:DescribeTargetGroups",
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation"
            ],
            "Resource": "*"
        }
    ]
}
```

Set the macros `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, and `{$AWS.ELB.ARN}`. If you are using access key-based authorization, set the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

For more information about managing access keys, see [official AWS documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

See the section below for a list of macros used for LLD filters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.DATA.TIMEOUT}|<p>API response timeout.</p>|`60s`|
|{$AWS.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, no proxy is used.</p>||
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.REGION}|<p>AWS Application Load Balancer region code.</p>|`us-west-1`|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `role_base`, `access_key`.</p>|`access_key`|
|{$AWS.ELB.ARN}|<p>Amazon Resource Names (ARN) of the load balancer.</p>||
|{$AWS.HTTP.4XX.FAIL.MAX.WARN}|<p>Maximum number of HTTP request failures for a trigger expression.</p>|`5`|
|{$AWS.HTTP.5XX.FAIL.MAX.WARN}|<p>Maximum number of HTTP request failures for a trigger expression.</p>|`5`|
|{$AWS.ELB.LLD.FILTER.TARGET.GROUP.MATCHES}|<p>Filter of discoverable target groups by name.</p>|`.*`|
|{$AWS.ELB.LLD.FILTER.TARGET.GROUP.NOT_MATCHES}|<p>Filter to exclude discovered target groups by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ELB.LLD.FILTER.ALARM_SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.ELB.LLD.FILTER.ALARM_SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ELB.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by name.</p>|`.*`|
|{$AWS.ELB.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by name.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS ELB ALB: Get metrics data|<p>Get ELB Application Load Balancer metrics.</p><p>Full metrics list related to Application Load Balancer: https://docs.aws.amazon.com/elasticloadbalancing/latest/application/load-balancer-cloudwatch-metrics.html</p>|Script|aws.elb.alb.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Get target groups|<p>Get ELB target group.</p><p>`DescribeTargetGroups` API method: https://docs.aws.amazon.com/elasticloadbalancing/latest/APIReference/API_DescribeTargetGroups.html</p>|Script|aws.elb.alb.get_target_groups<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS CloudWatch: Get ELB ALB alarms data|<p>`DescribeAlarms` API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.elb.alb.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Get metrics check|<p>Check that the Application Load Balancer metrics data has been received correctly.</p>|Dependent item|aws.elb.alb.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ELB ALB: Get alarms check|<p>Check that the alarm data has been received correctly.</p>|Dependent item|aws.elb.alb.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ELB ALB: Active Connection Count|<p>The total number of active concurrent TCP connections from clients to the load balancer and from the load balancer to targets.</p>|Dependent item|aws.elb.alb.active_connection_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: New Connection Count|<p>The total number of new TCP connections established from clients to the load balancer and from the load balancer to targets.</p>|Dependent item|aws.elb.alb.new_connection_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Rejected Connection Count|<p>The number of connections that were rejected because the load balancer had reached its maximum number of connections.</p>|Dependent item|aws.elb.alb.rejected_connection_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Requests Count|<p>The number of requests processed over IPv4 and IPv6.</p><p>This metric is only incremented for requests where the load balancer node was able to choose a target.</p><p>Requests that are rejected before a target is chosen are not reflected in this metric.</p>|Dependent item|aws.elb.alb.requests_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "RequestCount")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Target Response Time|<p>The time elapsed, in seconds, after the request leaves the load balancer until a response from the target is received.</p><p>This is equivalent to the `target_processing_time` field in the access logs.</p>|Dependent item|aws.elb.alb.target_response_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: HTTP Fixed Response Count|<p>The number of fixed-response actions that were successful.</p>|Dependent item|aws.elb.alb.http_fixed_response_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Rule Evaluations|<p>The number of rules processed by the load balancer given a request rate averaged over an hour.</p>|Dependent item|aws.elb.alb.rule_evaluations<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "RuleEvaluations")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Client TLS Negotiation Error Count|<p>The number of TLS connections initiated by the client that did not establish a session with the load balancer due to a TLS error.</p><p>Possible causes include a mismatch of ciphers or protocols or the client failing to verify the server certificate and closing the connection.</p>|Dependent item|aws.elb.alb.client_tls_negotiation_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Target TLS Negotiation Error Count|<p>The number of TLS connections initiated by the load balancer that did not establish a session with the target.</p><p>Possible causes include a mismatch of ciphers or protocols. This metric does not apply if the target is a Lambda function.</p>|Dependent item|aws.elb.alb.target_tls_negotiation_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Target Connection Error Count|<p>The number of connections that were not successfully established between the load balancer and target.</p><p>This metric does not apply if the target is a Lambda function.</p>|Dependent item|aws.elb.alb.target_connection_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Consumed LCUs|<p>The number of load balancer capacity units (LCU) used by your load balancer.</p><p>You pay for the number of LCUs that you use per hour.</p><p>More information on Elastic Load Balancing pricing here: https://aws.amazon.com/elasticloadbalancing/pricing/</p>|Dependent item|aws.elb.alb.capacity_units<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ConsumedLCUs")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Processed Bytes|<p>The total number of bytes processed by the load balancer over IPv4 and IPv6 (HTTP header and HTTP payload).</p><p>This count includes traffic to and from clients and Lambda functions, and traffic from an Identity Provider (IdP) if user authentication is enabled.</p>|Dependent item|aws.elb.alb.processed_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ProcessedBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: Desync Mitigation Mode Non Compliant Request Count|<p>The number of requests that fail to comply with HTTP protocols.</p>|Dependent item|aws.elb.alb.non_compliant_request_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: HTTP Redirect Count|<p>The number of redirect actions that were successful.</p>|Dependent item|aws.elb.alb.http_redirect_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: HTTP Redirect Url Limit Exceeded Count|<p>The number of redirect actions that could not be completed because the URL in the response location header is larger than 8K bytes.</p>|Dependent item|aws.elb.alb.http_redirect_url_limit_exceeded_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB HTTP 3XX Count|<p>The number of HTTP 3XX redirection codes that originate from the load balancer.</p><p>This count does not include response codes generated by targets.</p>|Dependent item|aws.elb.alb.http_3xx_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB HTTP 4XX Count|<p>The number of HTTP 4XX client error codes that originate from the load balancer.</p><p>Client errors are generated when requests are malformed or incomplete. These requests were not received by the target, other than in the case where the load balancer returns an HTTP 460 error code.</p><p>This count does not include any response codes generated by the targets.</p>|Dependent item|aws.elb.alb.http_4xx_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB HTTP 5XX Count|<p>The number of HTTP 5XX server error codes that originate from the load balancer.</p><p>This count does not include any response codes generated by the targets.</p>|Dependent item|aws.elb.alb.http_5xx_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB HTTP 500 Count|<p>The number of HTTP 500 error codes that originate from the load balancer.</p>|Dependent item|aws.elb.alb.http_500_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB HTTP 502 Count|<p>The number of HTTP 502 error codes that originate from the load balancer.</p>|Dependent item|aws.elb.alb.http_502_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB HTTP 503 Count|<p>The number of HTTP 503 error codes that originate from the load balancer.</p>|Dependent item|aws.elb.alb.http_503_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB HTTP 504 Count|<p>The number of HTTP 504 error codes that originate from the load balancer.</p>|Dependent item|aws.elb.alb.http_504_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB Auth Error|<p>The number of user authentications that could not be completed because an authenticate action was misconfigured, the load balancer could not establish a connection with the IdP, or the load balancer could not complete the authentication flow due to an internal error.</p>|Dependent item|aws.elb.alb.auth_error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ELBAuthError")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB Auth Failure|<p>The number of user authentications that could not be completed because the IdP denied access to the user or an authorization code was used more than once.</p>|Dependent item|aws.elb.alb.auth_failure<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ELBAuthFailure")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB Auth User Claims Size Exceeded|<p>The number of times that a configured IdP returned user claims that exceeded 11K bytes in size.</p>|Dependent item|aws.elb.alb.auth_user_claims_size_exceeded<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB Auth Latency|<p>The time elapsed, in milliseconds, to query the IdP for the ID token and user info.</p><p>If one or more of these operations fail, this is the time to failure.</p>|Dependent item|aws.elb.alb.auth_latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ELBAuthLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB: ELB Auth Success|<p>The number of authenticate actions that were successful.</p><p>This metric is incremented at the end of the authentication workflow, after the load balancer has retrieved the user claims from the IdP.</p>|Dependent item|aws.elb.alb.auth_success<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ELBAuthSuccess")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ELB ALB: Failed to get metrics data|<p>Failed to get CloudWatch metrics for Application Load Balancer.</p>|`length(last(/AWS ELB Application Load Balancer by HTTP/aws.elb.alb.metrics.check))>0`|Warning||
|AWS ELB ALB: Failed to get alarms data|<p>Failed to get CloudWatch alarms for Application Load Balancer.</p>|`length(last(/AWS ELB Application Load Balancer by HTTP/aws.elb.alb.alarms.check))>0`|Warning||
|AWS ELB ALB: Too many HTTP 4XX error codes|<p>Too many requests failed with HTTP 4XX code.</p>|`min(/AWS ELB Application Load Balancer by HTTP/aws.elb.alb.http_4xx_count,5m)>{$AWS.HTTP.4XX.FAIL.MAX.WARN}`|Warning||
|AWS ELB ALB: Too many HTTP 5XX error codes|<p>Too many requests failed with HTTP 5XX code.</p>|`min(/AWS ELB Application Load Balancer by HTTP/aws.elb.alb.http_5xx_count,5m)>{$AWS.HTTP.5XX.FAIL.MAX.WARN}`|Warning||

### LLD rule Load Balancer alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Load Balancer alarm discovery|<p>Used for the discovery of alarm balancers.</p>|Dependent item|aws.elb.alb.alarms.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Load Balancer alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS ELB ALB Alarms: [{#ALARM_NAME}]: Get metrics|<p>Get metrics about the alarm state and its reason.</p>|Dependent item|aws.elb.alb.alarm.get_metrics["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Alarms: [{#ALARM_NAME}]: State reason|<p>An explanation for the alarm state reason in text format.</p><p>Alarm description:</p><p>`{#ALARM_DESCRIPTION}`</p>|Dependent item|aws.elb.alb.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ELB ALB Alarms: [{#ALARM_NAME}]: State|<p>The value of the alarm state. Possible values:</p><p>0 - OK;</p><p>1 - INSUFFICIENT_DATA;</p><p>2 - ALARM.</p><p>Alarm description:</p><p>`{#ALARM_DESCRIPTION}`</p>|Dependent item|aws.elb.alb.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateValue`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Load Balancer alarm discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ELB ALB Alarms: [{#ALARM_NAME}] has 'Alarm' state|<p>The alarm `{#ALARM_NAME}` is in the ALARM state.<br>Reason: `{ITEM.LASTVALUE2}`</p>|`last(/AWS ELB Application Load Balancer by HTTP/aws.elb.alb.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS ELB Application Load Balancer by HTTP/aws.elb.alb.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS ELB ALB Alarms: [{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS ELB Application Load Balancer by HTTP/aws.elb.alb.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Target groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Target groups discovery|<p>Used for the discovery of `{$AWS.ELB.TARGET.GROUP.NAME}` target groups.</p>|Dependent item|aws.elb.alb.target_groups.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Target groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Get metrics|<p>Get the metrics of the ELB target group `{#AWS.ELB.TARGET.GROUP.NAME}`.</p><p>Full list of metrics related to AWS ELB here: https://docs.aws.amazon.com/elasticloadbalancing/latest/application/load-balancer-cloudwatch-metrics.html#user-authentication-metric-table</p>|Script|aws.elb.alb.target_groups.get_metrics["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: HTTP Code Target 2XX Count|<p>The number of HTTP response 2XX codes generated by the targets.</p><p>This does not include any response codes generated by the load balancer.</p>|Dependent item|aws.elb.alb.target_groups.http_2xx_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: HTTP Code Target 3XX Count|<p>The number of HTTP response 3XX codes generated by the targets.</p><p>This does not include any response codes generated by the load balancer.</p>|Dependent item|aws.elb.alb.target_groups.http_3xx_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: HTTP Code Target 4XX Count|<p>The number of HTTP response 4XX codes generated by the targets.</p><p>This does not include any response codes generated by the load balancer.</p>|Dependent item|aws.elb.alb.target_groups.http_4xx_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: HTTP Code Target 5XX Count|<p>The number of HTTP response 5XX codes generated by the targets.</p><p>This does not include any response codes generated by the load balancer.</p>|Dependent item|aws.elb.alb.target_groups.http_5xx_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Healthy Host Count|<p>The number of targets that are considered healthy.</p>|Dependent item|aws.elb.alb.target_groups.healthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "HealthyHostCount")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Unhealthy Host Count|<p>The number of targets that are considered unhealthy.</p>|Dependent item|aws.elb.alb.target_groups.unhealthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Healthy State Routing|<p>The number of zones that meet the routing healthy state requirements.</p>|Dependent item|aws.elb.alb.target_groups.healthy_state_routing["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Unhealthy State Routing|<p>The number of zones that do not meet the routing healthy state requirements, and therefore the load balancer distributes traffic to all targets in the zone, including the unhealthy targets.</p>|Dependent item|aws.elb.alb.target_groups.unhealthy_state_routing["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Request Count Per Target|<p>The average request count per target, in a target group.</p><p>You must specify the target group using the TargetGroup dimension.</p>|Dependent item|aws.elb.alb.target_groups.request["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Unhealthy Routing Request Count|<p>The average request count per target, in a target group.</p>|Dependent item|aws.elb.alb.target_groups.unhealthy_routing_request_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Mitigated Host Count|<p>The number of targets under mitigation.</p>|Dependent item|aws.elb.alb.target_groups.mitigated_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Anomalous Host Count|<p>The number of hosts detected with anomalies.</p>|Dependent item|aws.elb.alb.target_groups.anomalous_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Healthy State DNS|<p>The number of zones that meet the DNS healthy state requirements.</p>|Dependent item|aws.elb.alb.target_groups.healthy_state_dns["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "HealthyStateDNS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB ALB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Unhealthy State DNS|<p>The number of zones that do not meet the DNS healthy state requirements and therefore were marked unhealthy in DNS.</p>|Dependent item|aws.elb.alb.target_groups.unhealthy_state_dns["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "UnhealthyStateDNS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

# AWS ELB Network Load Balancer by HTTP

## Overview

The template is designed to monitor AWS ELB Network Load Balancer by HTTP via Zabbix, and it works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the [CloudWatch pricing](https://aws.amazon.com/cloudwatch/pricing/) page.

Additional information about metrics and API methods used in the template:
* [Full metrics list related to AWS ELB Network Load Balancer](https://docs.aws.amazon.com/elasticloadbalancing/latest/network/load-balancer-cloudwatch-metrics.html)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)
* [DescribeTargetGroups API method](https://docs.aws.amazon.com/elasticloadbalancing/latest/APIReference/API_DescribeTargetGroups.html)


## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- AWS ELB Network Load Balancer with Target Groups by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS ELB Network Load Balancer metrics and uses the script item to make HTTP requests to the CloudWatch API.

Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account. For more information, visit the [ELB policies page](https://docs.aws.amazon.com/elasticloadbalancing/latest/userguide/elb-api-permissions.html) on the AWS website.

Add the following required permissions to your Zabbix IAM policy in order to collect AWS ELB Network Load Balancer metrics.
```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
              "cloudwatch:DescribeAlarms",
              "cloudwatch:GetMetricData",
              "elasticloadbalancing:DescribeTargetGroups"
          ],
          "Effect":"Allow",
          "Resource":"*"
        }
    ]
  }
  ```

If you are using role-based authorization, set the appropriate permissions:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "iam:PassRole",
            "Resource": "arn:aws:iam::<<--account-id-->>:role/<<--role_name-->>"
        },
        {
            "Sid": "VisualEditor1",
            "Effect": "Allow",
            "Action": [
                "cloudwatch:DescribeAlarms",
                "cloudwatch:GetMetricData",
                "elasticloadbalancing:DescribeTargetGroups",
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation"
            ],
            "Resource": "*"
        }
    ]
}
```

Set the macros `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, and `{$AWS.ELB.ARN}`. If you are using access key-based authorization, set the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

For more information about managing access keys, see [official AWS documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

See the section below for a list of macros used for LLD filters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.DATA.TIMEOUT}|<p>API response timeout.</p>|`60s`|
|{$AWS.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, no proxy is used.</p>||
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.REGION}|<p>AWS Network Load Balancer region code.</p>|`us-west-1`|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `role_base`, `access_key`.</p>|`access_key`|
|{$AWS.ELB.ARN}|<p>Amazon Resource Names (ARN) of the load balancer.</p>||
|{$AWS.ELB.LLD.FILTER.TARGET.GROUP.MATCHES}|<p>Filter of discoverable target groups by name.</p>|`.*`|
|{$AWS.ELB.LLD.FILTER.TARGET.GROUP.NOT_MATCHES}|<p>Filter to exclude discovered target groups by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ELB.LLD.FILTER.ALARM_SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.ELB.LLD.FILTER.ALARM_SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ELB.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by name.</p>|`.*`|
|{$AWS.ELB.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ELB.UNHEALTHY.HOST.MAX}|<p>Maximum number of unhealthy hosts for a trigger expression.</p>|`0`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS ELB NLB: Get metrics data|<p>Get ELB Network Load Balancer metrics.</p><p>Full metrics list related to Network Load Balancer: https://docs.aws.amazon.com/elasticloadbalancing/latest/network/load-balancer-cloudwatch-metrics.html</p>|Script|aws.elb.nlb.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Get target groups|<p>Get ELB target group.</p><p>`DescribeTargetGroups` API method: https://docs.aws.amazon.com/elasticloadbalancing/latest/APIReference/API_DescribeTargetGroups.html</p>|Script|aws.elb.nlb.get_target_groups<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS CloudWatch: Get ELB NLB alarms data|<p>`DescribeAlarms` API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.elb.nlb.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Get metrics check|<p>Check that the Network Load Balancer metrics data has been received correctly.</p>|Dependent item|aws.elb.nlb.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ELB NLB: Get alarms check|<p>Check that the alarm data has been received correctly.</p>|Dependent item|aws.elb.nlb.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ELB NLB: Active Flow Count|<p>The total number of concurrent flows (or connections) from clients to targets.</p><p>This metric includes connections in the `SYN_SENT` and `ESTABLISHED` states.</p><p>TCP connections are not terminated at the load balancer, so a client opening a TCP connection to a target counts as a single flow.</p>|Dependent item|aws.elb.nlb.active_flow_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ActiveFlowCount")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Active Flow Count TCP|<p>The total number of concurrent TCP flows (or connections) from clients to targets.</p><p>This metric includes connections in the `SYN_SENT` and `ESTABLISHED` states.</p><p>TCP connections are not terminated at the load balancer, so a client opening a TCP connection to a target counts as a single flow.</p>|Dependent item|aws.elb.nlb.active_flow_count_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Active Flow Count TLS|<p>The total number of concurrent TLS flows (or connections) from clients to targets.</p><p>This metric includes connections in the `SYN_SENT` and `ESTABLISHED` states.</p>|Dependent item|aws.elb.nlb.active_flow_count_tls<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Active Flow Count UDP|<p>The total number of concurrent UDP flows (or connections) from clients to targets.</p>|Dependent item|aws.elb.nlb.active_flow_count_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Client TLS Negotiation Error Count|<p>The total number of TLS handshakes that failed during negotiation between a client and a TLS listener.</p>|Dependent item|aws.elb.nlb.client_tls_negotiation_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Consumed LCUs|<p>The number of load balancer capacity units (LCU) used by your load balancer.</p><p>You pay for the number of LCUs that you use per hour.</p><p>More information on Elastic Load Balancing pricing here: https://aws.amazon.com/elasticloadbalancing/pricing/</p>|Dependent item|aws.elb.nlb.capacity_units<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ConsumedLCUs")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Consumed LCUs TCP|<p>The number of load balancer capacity units (LCU) used by your load balancer for TCP.</p><p>You pay for the number of LCUs that you use per hour.</p><p>More information on Elastic Load Balancing pricing here: https://aws.amazon.com/elasticloadbalancing/pricing/</p>|Dependent item|aws.elb.nlb.capacity_units_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ConsumedLCUs_TCP")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Consumed LCUs TLS|<p>The number of load balancer capacity units (LCU) used by your load balancer for TLS.</p><p>You pay for the number of LCUs that you use per hour.</p><p>More information on Elastic Load Balancing pricing here: https://aws.amazon.com/elasticloadbalancing/pricing/</p>|Dependent item|aws.elb.nlb.capacity_units_tls<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ConsumedLCUs_TLS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Consumed LCUs UDP|<p>The number of load balancer capacity units (LCU) used by your load balancer for UDP.</p><p>You pay for the number of LCUs that you use per hour.</p><p>More information on Elastic Load Balancing pricing here: https://aws.amazon.com/elasticloadbalancing/pricing/</p>|Dependent item|aws.elb.nlb.capacity_units_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ConsumedLCUs_UDP")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: New Flow Count|<p>The total number of new flows (or connections) established from clients to targets in the specified time period.</p>|Dependent item|aws.elb.nlb.new_flow_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NewFlowCount")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: New Flow Count TCP|<p>The total number of new TCP flows (or connections) established from clients to targets in the specified time period.</p>|Dependent item|aws.elb.nlb.new_flow_count_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NewFlowCount_TCP")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: New Flow Count TLS|<p>The total number of new TLS flows (or connections) established from clients to targets in the specified time period.</p>|Dependent item|aws.elb.nlb.new_flow_count_tls<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NewFlowCount_TLS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: New Flow Count UDP|<p>The total number of new UDP flows (or connections) established from clients to targets in the specified time period.</p>|Dependent item|aws.elb.nlb.new_flow_count_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NewFlowCount_UDP")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Peak Packets per second|<p>Highest average packet rate (packets processed per second), calculated every 10 seconds during the sampling window.</p><p>This metric includes health check traffic.</p>|Dependent item|aws.elb.nlb.peak_packets.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Port Allocation Error Count|<p>The total number of ephemeral port allocation errors during a client IP translation operation. A non-zero value indicates dropped client connections.</p><p>Note: Network Load Balancers support 55,000 simultaneous connections or about 55,000 connections per minute to each unique target (IP address and port) when performing client address translation.</p><p>To fix port allocation errors, add more targets to the target group.</p>|Dependent item|aws.elb.nlb.port_allocation_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Processed Bytes|<p>The total number of bytes processed by the load balancer, including TCP/IP headers. This count includes traffic to and from targets, minus health check traffic.</p>|Dependent item|aws.elb.nlb.processed_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ProcessedBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Processed Bytes TCP|<p>The total number of bytes processed by TCP listeners.</p>|Dependent item|aws.elb.nlb.processed_bytes_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Processed Bytes TLS|<p>The total number of bytes processed by TLS listeners.</p>|Dependent item|aws.elb.nlb.processed_bytes_tls<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Processed Bytes UDP|<p>The total number of bytes processed by UDP listeners.</p>|Dependent item|aws.elb.nlb.processed_bytes_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Processed Packets|<p>The total number of packets processed by the load balancer. This count includes traffic to and from targets, including health check traffic.</p>|Dependent item|aws.elb.nlb.processed_packets<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ProcessedPackets")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Security Group Blocked Flow Count Inbound ICMP|<p>The number of new ICMP messages rejected by the inbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_inbound_icmp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Security Group Blocked Flow Count Inbound TCP|<p>The number of new TCP flows rejected by the inbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_inbound_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Security Group Blocked Flow Count Inbound UDP|<p>The number of new UDP flows rejected by the inbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_inbound_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Security Group Blocked Flow Count Outbound ICMP|<p>The number of new ICMP messages rejected by the outbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_outbound_icmp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Security Group Blocked Flow Count Outbound TCP|<p>The number of new TCP flows rejected by the outbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_outbound_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Security Group Blocked Flow Count Outbound UDP|<p>The number of new UDP flows rejected by the outbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_outbound_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Target TLS Negotiation Error Count|<p>The total number of TLS handshakes that failed during negotiation between a TLS listener and a target.</p>|Dependent item|aws.elb.nlb.target_tls_negotiation_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: TCP Client Reset Count|<p>The total number of reset (RST) packets sent from a client to a target.</p><p>These resets are generated by the client and forwarded by the load balancer.</p>|Dependent item|aws.elb.nlb.tcp_client_reset_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: TCP ELB Reset Count|<p>The total number of reset (RST) packets generated by the load balancer.</p><p>For more information, see: https://docs.aws.amazon.com/elasticloadbalancing/latest/network/load-balancer-troubleshooting.html#elb-reset-count-metric</p>|Dependent item|aws.elb.nlb.tcp_elb_reset_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: TCP Target Reset Count|<p>The total number of reset (RST) packets sent from a target to a client.</p><p>These resets are generated by the target and forwarded by the load balancer.</p>|Dependent item|aws.elb.nlb.tcp_target_reset_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB: Unhealthy Routing Flow Count|<p>The number of flows (or connections) that are routed using the routing failover action (fail open).</p>|Dependent item|aws.elb.nlb.unhealthy_routing_flow_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ELB NLB: Failed to get metrics data|<p>Failed to get CloudWatch metrics for Network Load Balancer.</p>|`length(last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.metrics.check))>0`|Warning||
|AWS ELB NLB: Failed to get alarms data|<p>Failed to get CloudWatch alarms for Network Load Balancer.</p>|`length(last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.alarms.check))>0`|Warning||

### LLD rule Load Balancer alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Load Balancer alarm discovery|<p>Used for the discovery of alarm balancers.</p>|Dependent item|aws.elb.nlb.alarms.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Load Balancer alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS ELB NLB Alarms: [{#ALARM_NAME}]: Get metrics|<p>Get metrics about the alarm state and its reason.</p>|Dependent item|aws.elb.nlb.alarm.get_metrics["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB Alarms: [{#ALARM_NAME}]: State reason|<p>An explanation for the alarm state reason in text format.</p><p>Alarm description:</p><p>`{#ALARM_DESCRIPTION}`</p>|Dependent item|aws.elb.nlb.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ELB NLB Alarms: [{#ALARM_NAME}]: State|<p>The value of the alarm state. Possible values:</p><p>0 - OK;</p><p>1 - INSUFFICIENT_DATA;</p><p>2 - ALARM.</p><p>Alarm description:</p><p>`{#ALARM_DESCRIPTION}`</p>|Dependent item|aws.elb.nlb.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateValue`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Load Balancer alarm discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ELB NLB Alarms: [{#ALARM_NAME}] has 'Alarm' state|<p>The alarm `{#ALARM_NAME}` is in the ALARM state.<br>Reason: `{ITEM.LASTVALUE2}`</p>|`last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS ELB NLB Alarms: [{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Target groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Target groups discovery|<p>Used for the discovery of `{$AWS.ELB.TARGET.GROUP.NAME}` target groups.</p>|Dependent item|aws.elb.nlb.target_groups.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Target groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS ELB NLB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Get metrics|<p>Get the metrics of the ELB target group `{#AWS.ELB.TARGET.GROUP.NAME}`.</p><p>Full list of metrics related to AWS ELB here: https://docs.aws.amazon.com/elasticloadbalancing/latest/network/load-balancer-cloudwatch-metrics.html#user-authentication-metric-table</p>|Script|aws.elb.nlb.target_groups.get_metrics["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Healthy Host Count|<p>The number of targets that are considered healthy.</p>|Dependent item|aws.elb.nlb.target_groups.healthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "HealthyHostCount")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ELB NLB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Unhealthy Host Count|<p>The number of targets that are considered unhealthy.</p>|Dependent item|aws.elb.nlb.target_groups.unhealthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Target groups discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ELB NLB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Target have become unhealthy|<p>This trigger helps in identifying when your targets have become unhealthy.</p>|`last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.target_groups.healthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]) = 0`|Average||
|AWS ELB NLB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Target have unhealthy host|<p>This trigger allows you to become aware when there are no more registered targets.</p>|`last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.target_groups.unhealthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]) > {$AWS.ELB.UNHEALTHY.HOST.MAX}`|Warning|**Depends on**:<br><ul><li>AWS ELB NLB Target Groups: [{#AWS.ELB.TARGET.GROUP.NAME}]: Target have become unhealthy</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

