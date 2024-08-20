
# AWS Lambda by HTTP

## Overview

This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the [CloudWatch pricing](https://aws.amazon.com/cloudwatch/pricing/) page.

Additional information about metrics and API methods used in the template:
* [Full metrics list related to AWS Lambda](https://docs.aws.amazon.com/lambda/latest/dg/monitoring-metrics.html)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)


## Requirements

Zabbix version: 7.2 and higher.

## Tested versions

This template has been tested on:
- AWS Lambda by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.2/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS Lambda metrics and uses the script item to make HTTP requests to the CloudWatch API.

Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account. For more information, visit the [Lambda permissions page](https://docs.aws.amazon.com/lambda/latest/dg/lambda-permissions.html) on the AWS website.

Add the following required permissions to your Zabbix IAM policy in order to collect AWS Lambda metrics.
```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
              "cloudwatch:DescribeAlarms",
              "cloudwatch:GetMetricData"
          ],
          "Effect":"Allow",
          "Resource":"*"
        }
    ]
  }
```
For using assume role authorization, add the appropriate permissions to the role you are using:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "sts:AssumeRole",
            "Resource": "arn:aws:iam::{Account}:user/{UserName}"
        },
        {
            "Effect": "Allow",
            "Action": [
                "cloudwatch:DescribeAlarms",
                "cloudwatch:GetMetricData"
            ],
            "Resource": "*"
        }
    ]
}
```
Next, add a principal to the trust relationships of the role you are using:
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "AWS": "arn:aws:iam::{Account}:user/{UserName}"
      },
      "Action": "sts:AssumeRole"
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
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation"
            ],
            "Resource": "*"
        }
    ]
}
```
Next, add a principal to the trust relationships of the role you are using:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "Service": [
                    "ec2.amazonaws.com"
                ]
            },
            "Action": [
                "sts:AssumeRole"
            ]
        }
    ]
}
```
**Note**, Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

Set the macros: `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, and `{$AWS.LAMBDA.ARN}`.

If you are using access key-based authorization, set the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

If you are using access assume role authorization, set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

For more information about managing access keys, see the [official AWS documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

See the section below for a list of macros used for LLD filters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.DATA.TIMEOUT}|<p>API response timeout.</p>|`60s`|
|{$AWS.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, no proxy is used.</p>||
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.REGION}|<p>AWS Lambda function region code.</p>|`us-west-1`|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.LAMBDA.ARN}|<p>The Amazon Resource Names (ARN) of the Lambda function.</p>||
|{$AWS.LAMBDA.LLD.FILTER.ALARM_SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.LAMBDA.LLD.FILTER.ALARM_SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.LAMBDA.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by name.</p>|`.*`|
|{$AWS.LAMBDA.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by name.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get metrics data|<p>Get Lambda function metrics.</p><p>Full metrics list related to the Lambda function: https://docs.aws.amazon.com/lambda/latest/dg/monitoring-metrics.html</p>|Script|aws.lambda.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get Lambda alarms data|<p>`DescribeAlarms` API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.lambda.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get metrics check|<p>Check that the Lambda function metrics data has been received correctly.</p>|Dependent item|aws.lambda.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get alarms check|<p>Check that the alarm data has been received correctly.</p>|Dependent item|aws.lambda.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Async events received sum|<p>The number of events that Lambda successfully queues for processing. This metric provides insight into the number of events that a Lambda function receives.</p>|Dependent item|aws.lambda.async_events_received.sum<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Async event age average|<p>The time between when Lambda successfully queues the event and when the function is invoked. The value of this metric increases when events are being retried due to invocation failures or throttling.</p>|Dependent item|aws.lambda.async_event_age.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "AsyncEventAge")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Async events dropped sum|<p>The number of events that are dropped without successfully executing the function. If you configure a dead-letter queue (DLQ) or an `OnFailure` destination, events are sent there before they're dropped.</p>|Dependent item|aws.lambda.async_events_dropped.sum<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Total concurrent executions|<p>The number of function instances that are processing events. If this number reaches your concurrent executions quota for the Region or the reserved concurrency limit on the function, then Lambda will throttle additional invocation requests.</p>|Dependent item|aws.lambda.concurrent_executions.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Unreserved concurrent executions maximum|<p>For a Region, the number of events that function without reserved concurrency are processing.</p>|Dependent item|aws.lambda.unreserved_concurrent_executions.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Invocations sum|<p>The number of times that your function code is invoked, including successful invocations and invocations that result in a function error. Invocations aren't recorded if the invocation request is throttled or otherwise results in an invocation error. The value of `Invocations` equals the number of requests billed.</p>|Dependent item|aws.lambda.invocations.sum<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "Invocations")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Errors sum|<p>The number of invocations that result in a function error. Function errors include exceptions that your code throws and exceptions that the Lambda runtime throws. The runtime returns errors for issues such as timeouts and configuration errors.</p>|Dependent item|aws.lambda.errors.sum<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "Errors")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Dead letter errors sum|<p>For asynchronous invocation, the number of times that Lambda attempts to send an event to a dead-letter queue (DLQ) but fails. Dead-letter errors can occur due to misconfigured resources or size limits.</p>|Dependent item|aws.lambda.dead_letter_errors.sum<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DeadLetterErrors")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Throttles sum|<p>The number of invocation requests that are throttled. When all function instances are processing requests and no concurrency is available to scale up, Lambda rejects additional requests with a `TooManyRequestsException` error.</p>|Dependent item|aws.lambda.throttles.sum<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "Throttles")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Duration average|<p>The amount of time that your function code spends processing an event. The billed duration for an invocation is the value of `Duration` rounded up to the nearest millisecond. Duration does not include cold start time.</p>|Dependent item|aws.lambda.duration.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "Duration")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Failed to get metrics data|<p>Failed to get CloudWatch metrics for the Lambda function.</p>|`length(last(/AWS Lambda by HTTP/aws.lambda.metrics.check))>0`|Warning||
|Failed to get alarms data|<p>Failed to get CloudWatch alarms for the Lambda function.</p>|`length(last(/AWS Lambda by HTTP/aws.lambda.alarms.check))>0`|Warning||

### LLD rule Lambda alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Lambda alarm discovery|<p>Used for the discovery of alarm Lambda functions.</p>|Dependent item|aws.lambda.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Lambda alarm discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#ALARM_NAME}]: Get metrics|<p>Get metrics about the alarm state and its reason.</p>|Dependent item|aws.lambda.alarm.get_metrics["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#ALARM_NAME}]: State reason|<p>An explanation for the alarm state reason in text format.</p><p>Alarm description:</p><p>`{#ALARM_DESCRIPTION}`</p>|Dependent item|aws.lambda.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#ALARM_NAME}]: State|<p>The value of the alarm state. Possible values:</p><p>0 - OK;</p><p>1 - INSUFFICIENT_DATA;</p><p>2 - ALARM.</p><p>Alarm description:</p><p>`{#ALARM_DESCRIPTION}`</p>|Dependent item|aws.lambda.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateValue`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Lambda alarm discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|[{#ALARM_NAME}] has 'Alarm' state|<p>The alarm `{#ALARM_NAME}` is in the ALARM state.<br>Reason: `{ITEM.LASTVALUE2}`</p>|`last(/AWS Lambda by HTTP/aws.lambda.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS Lambda by HTTP/aws.lambda.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|[{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS Lambda by HTTP/aws.lambda.alarm.state["{#ALARM_NAME}"])=1`|Info||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

