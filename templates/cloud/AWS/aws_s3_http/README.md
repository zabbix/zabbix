
# AWS S3 bucket by HTTP

## Overview

For Zabbix version: 6.4 and higher  
The template to monitor AWS S3 bucket by HTTP via Zabbix that works without any external scripts.  
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.
*NOTE*
This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the (CloudWatch pricing)[https://aws.amazon.com/cloudwatch/pricing/] page.

Additional information about metrics and used API methods:

* Full metrics list related to S3: https://docs.aws.amazon.com/AmazonS3/latest/userguide/metrics-dimensions.html



## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/http) for basic instructions.

The template gets AWS S3 metrics and uses the script item to make HTTP requests to the CloudWatch API.

Before using the template, you need to create an IAM policy for the Zabbix role in your AWS account with the necessary permissions.  

Add the following required permissions to your Zabbix IAM policy in order to collect Amazon S3 metrics.  
```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
              "cloudwatch:Describe*",
              "cloudwatch:Get*",
              "cloudwatch:List*"
          ],
          "Effect":"Allow",
          "Resource":"*"
        }
    ]
  }
  ```

To gather Request metrics, [enable Requests metrics](https://docs.aws.amazon.com/AmazonS3/latest/userguide/cloudwatch-monitoring.html) on your Amazon S3 buckets from the AWS console.

Set the macros "{$AWS.ACCESS.KEY.ID}", "{$AWS.SECRET.ACCESS.KEY}", "{$AWS.REGION}", "{$AWS.S3.FILTER.ID}", "{$AWS.S3.BUCKET.NAME}"

For more information about manage access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys)

Also, see the [Macros](#macros_used) section for a list of macros used by LLD filters.

Additional information about metrics and used API methods:
* Full metrics list related to S3: https://docs.aws.amazon.com/AmazonS3/latest/userguide/metrics-dimensions.html


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.ACCESS.KEY.ID} |<p>Access key ID.</p> |`` |
|{$AWS.REGION} |<p>Amazon S3 Region code.</p> |`us-west-1` |
|{$AWS.S3.BUCKET.NAME} |<p>S3 bucket name.</p> |`` |
|{$AWS.S3.FILTER.ID} |<p>S3 bucket requests filter identifier.</p> |`` |
|{$AWS.S3.LLD.FILTER.ALARM_NAME.MATCHES} |<p>Filter of discoverable alarms by name.</p> |`.*` |
|{$AWS.S3.LLD.FILTER.ALARM_NAME.NOT_MATCHES} |<p>Filter to exclude discovered alarms by name.</p> |`CHANGE_IF_NEEDED` |
|{$AWS.SECRET.ACCESS.KEY} |<p>Secret access key.</p> |`` |
|{$AWS.PROXY} |<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Bucket Alarms discovery |<p>Discovery bucket alarms.</p> |DEPENDENT |aws.s3.alarms.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p><p>**Filter**:</p>AND <p>- {#ALARM_NAME} MATCHES_REGEX `{$AWS.S3.LLD.FILTER.ALARM_NAME.MATCHES}`</p><p>- {#ALARM_NAME} NOT_MATCHES_REGEX `{$AWS.S3.LLD.FILTER.ALARM_NAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|AWS S3 |AWS S3: Get metrics check |<p>Data collection check.</p> |DEPENDENT |aws.s3.metrics.check<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|AWS S3 |AWS S3: Get alarms check |<p>Data collection check.</p> |DEPENDENT |aws.s3.alarms.check<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|AWS S3 |AWS S3: Bucket Size |<p>This a daily metric for the bucket.</p><p>The amount of data in bytes stored in a bucket in the STANDARD storage class, INTELLIGENT_TIERING storage class, Standard-Infrequent Access (STANDARD_IA) storage class, OneZone-Infrequent Access (ONEZONE_IA), Reduced Redundancy Storage (RRS) class, S3 Glacier Instant Retrieval storage class, Deep Archive Storage (S3 Glacier Deep Archive) class or, S3 Glacier Flexible Retrieval (GLACIER) storage class. This value is calculated by summing the size of all objects and metadata in the bucket (both current and noncurrent objects), including the size of all parts for all incomplete multipart uploads to the bucket.</p> |DEPENDENT |aws.s3.bucket_size_bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "BucketSizeBytes")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Number of objects |<p>This a daily metric for the bucket.</p><p>The total number of objects stored in a bucket for all storage classes. </p><p>This value is calculated by counting all objects in the bucket (both current and noncurrent objects) and the total number of parts for all incomplete multipart uploads to the bucket.</p> |DEPENDENT |aws.s3.number_of_objects<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "NumberOfObjects")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: All |<p>The total number of HTTP requests made to an Amazon S3 bucket, regardless of type.</p><p>If you're using a metrics configuration with a filter, then this metric only returns the HTTP requests that meet the filter's requirements.</p> |DEPENDENT |aws.s3.all_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "AllRequests")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Get |<p>The number of HTTP GET requests made for objects in an Amazon S3 bucket. This doesn't include list operations.</p><p>Paginated list-oriented requests, like List Multipart Uploads, List Parts, Get Bucket Object versions, and others, are not included in this metric.</p> |DEPENDENT |aws.s3.get_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "GetRequests")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Put |<p>The number of HTTP PUT requests made for objects in an Amazon S3 bucket.</p> |DEPENDENT |aws.s3.put_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "PutRequests")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Delete |<p>The number of HTTP DELETE requests made for objects in an Amazon S3 bucket.</p><p>This also includes Delete Multiple Objects requests. This metric shows the number of requests, not the number of objects deleted.</p> |DEPENDENT |aws.s3.delete_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "DeleteRequests")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Head |<p>The number of HTTP HEAD requests made to an Amazon S3 bucket.</p> |DEPENDENT |aws.s3.head_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "HeadRequests")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Post |<p>The number of HTTP POST requests made to an Amazon S3 bucket.</p><p>Delete Multiple Objects and SELECT Object Content requests are not included in this metric.</p> |DEPENDENT |aws.s3.post_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "PostRequests")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Select |<p>The number of Amazon S3 SELECT Object Content requests made for objects in an Amazon S3 bucket.</p> |DEPENDENT |aws.s3.select_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "SelectRequests")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Select, bytes scanned |<p>The number of bytes of data scanned with Amazon S3 SELECT Object Content requests in an Amazon S3 bucket.</p><p>Statistic: Average (bytes per request).</p> |DEPENDENT |aws.s3.select_bytes_scanned<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "SelectBytesScanned")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Select, bytes returned |<p>The number of bytes of data returned with Amazon S3 SELECT Object Content requests in an Amazon S3 buckets.</p><p>Statistic: Average (bytes per request).</p> |DEPENDENT |aws.s3.select_bytes_returned<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "SelectBytesReturned")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: List |<p>The number of HTTP requests that list the contents of a bucket.</p> |DEPENDENT |aws.s3.list_requests<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "ListRequests")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Bytes downloaded |<p>The number of bytes downloaded for requests made to an Amazon S3 bucket, where the response includes a body.</p><p>Statistic: Average (bytes per request).</p> |DEPENDENT |aws.s3.bytes_downloaded<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "BytesDownloaded")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Bytes uploaded |<p>The number of bytes uploaded that contain a request body, made to an Amazon S3 bucket.</p><p>Statistic: Average (bytes per request).</p> |DEPENDENT |aws.s3.bytes_uploaded<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "BytesUploaded")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Errors, 4xx |<p>The number of HTTP 4xx client error status code requests made to an Amazon S3 bucket with a value of either 0 or 1. </p><p>The average statistic shows the error rate, and the sum statistic shows the count of that type of error, during each period.</p><p>Statistic: Average (reports per request).</p> |DEPENDENT |aws.s3.4xx_errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "4xxErrors")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Requests: Errors, 5xx |<p>The number of HTTP 5xx server error status code requests made to an Amazon S3 bucket with a value of either 0 or 1. </p><p>The average statistic shows the error rate, and the sum statistic shows the count of that type of error, during each period.</p><p>Statistic: Average (reports per request).</p> |DEPENDENT |aws.s3.5xx_errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "5xxErrors")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: First byte latency, avg |<p>The per-request time from the complete request being received by an Amazon S3 bucket to when the response starts to be returned.</p><p>Statistic: Average.</p> |DEPENDENT |aws.s3.first_byte_latency.avg<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "FirstByteLatency")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: First byte latency, p90 |<p>The per-request time from the complete request being received by an Amazon S3 bucket to when the response starts to be returned.</p><p>Statistic: 90 percentile.</p> |DEPENDENT |aws.s3.first_byte_latency.p90<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "FirstByteLatency")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Total request latency, avg |<p>The elapsed per-request time from the first byte received to the last byte sent to an Amazon S3 bucket.</p><p>This includes the time taken to receive the request body and send the response body, which is not included in FirstByteLatency.</p><p>Statistic: Average.</p> |DEPENDENT |aws.s3.total_request_latency.avg<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "TotalRequestLatency")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Total request latency, p90 |<p>The elapsed per-request time from the first byte received to the last byte sent to an Amazon S3 bucket.</p><p>This includes the time taken to receive the request body and send the response body, which is not included in FirstByteLatency.</p><p>Statistic: 90 percentile.</p> |DEPENDENT |aws.s3.total_request_latency.p90<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "TotalRequestLatency")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Replication: Latency |<p>The maximum number of seconds by which the replication destination Region is behind the source Region for a given replication rule.</p> |DEPENDENT |aws.s3.replication_latency<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "ReplicationLatency")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Replication: Bytes pending |<p>The total number of bytes of objects pending replication for a given replication rule.</p> |DEPENDENT |aws.s3.bytes_pending_replication<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "BytesPendingReplication")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3: Replication: Operations pending |<p>The number of operations pending replication for a given replication rule.</p> |DEPENDENT |aws.s3.operations_pending_replication<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.Label == "OperationsPendingReplication")].Values.first().first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|AWS S3 |AWS S3 Alarms: ["{#ALARM_NAME}"]: State reason |<p>An explanation for the alarm state, in text format.</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p> |DEPENDENT |aws.s3.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.AlarmName == "{#ALARM_NAME}")].StateReason.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|AWS S3 |AWS S3 Alarms: ["{#ALARM_NAME}"]: State |<p>The state value for the alarm. Possible values: 0 (OK), 1 (INSUFFICIENT_DATA), 2 (ALARM).</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p> |DEPENDENT |aws.s3.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.AlarmName == "{#ALARM_NAME}")].StateValue.first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- JAVASCRIPT: `var state = ['OK', 'INSUFFICIENT_DATA', 'ALARM']; return state.indexOf(value.trim()) === -1 ? 255 : state.indexOf(value.trim()); `</p> |
|Zabbix raw items |AWS S3: Get metrics data |<p>Get bucket metrics.</p><p>Full metrics list related to S3: https://docs.aws.amazon.com/AmazonS3/latest/userguide/metrics-dimensions.html</p> |SCRIPT |aws.s3.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |AWS S3: Get alarms data |<p>Get alarms data.</p><p>DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p> |SCRIPT |aws.s3.get_alarms<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>**Expression**:</p>`The text is too long. Please see the template.` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|AWS S3: Failed to get metrics data |<p>-</p> |`length(last(/AWS S3 bucket by HTTP/aws.s3.metrics.check))>0` |WARNING | |
|AWS S3: Failed to get alarms data |<p>-</p> |`length(last(/AWS S3 bucket by HTTP/aws.s3.alarms.check))>0` |WARNING | |
|AWS S3 Alarms: "{#ALARM_NAME}" has 'Alarm' state |<p>Alarm "{#ALARM_NAME}" has 'Alarm' state. </p><p>Reason: {ITEM.LASTVALUE2}</p> |`last(/AWS S3 bucket by HTTP/aws.s3.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS S3 bucket by HTTP/aws.s3.alarm.state_reason["{#ALARM_NAME}"]))>0` |AVERAGE | |
|AWS S3 Alarms: "{#ALARM_NAME}" has 'Insufficient data' state |<p>-</p> |`last(/AWS S3 bucket by HTTP/aws.s3.alarm.state["{#ALARM_NAME}"])=1` |INFO | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

