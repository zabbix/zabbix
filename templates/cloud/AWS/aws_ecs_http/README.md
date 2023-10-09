
# AWS ECS Cluster by HTTP

## Overview

The template to monitor AWS ECS Cluster by HTTP via Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.
*NOTE*
This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the (CloudWatch pricing)[https://aws.amazon.com/cloudwatch/pricing/] page.

Additional information about the metrics and used API methods:

* Full metrics list related to ECS: https://docs.aws.amazon.com/AmazonCloudWatch/latest/monitoring/Container-Insights-metrics-ECS.html

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- AWS ECS Cluster by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS ECS metrics and uses the script item to make HTTP requests to the CloudWatch API.

Before using the template, you need to create an IAM policy for the Zabbix role in your AWS account with the necessary permissions.

Add the following required permissions to your Zabbix IAM policy in order to collect Amazon ECS metrics.
```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
                "cloudwatch:DescribeAlarms",
                "cloudwatch:GetMetricData",
                "ecs:ListServices",
                "esc:ListTasks"
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
                "ecs:ListServices",
                "esc:ListTasks",
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation"
            ],
            "Resource": "*"
        }
    ]
}
```

Set the following macros "{$AWS.AUTH_TYPE}", "{$AWS.REGION}", "{$AWS.ECS.CLUSTER.NAME}"

If you are using access key-based authorization, set the following macros "{$AWS.ACCESS.KEY.ID}", "{$AWS.SECRET.ACCESS.KEY}"

For more information about managing access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys)

Refer to the Macros section for a list of macros used for LLD filters.

Additional information about the metrics and used API methods:
* Full metrics list related to ECS: https://docs.aws.amazon.com/AmazonECS/latest/userguide/metrics-dimensions.html

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.REGION}|<p>Amazon ECS Region code.</p>|`us-west-1`|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: role_base, access_key.</p>|`role_base`|
|{$AWS.ECS.CLUSTER.NAME}|<p>ECS cluster name.</p>||
|{$AWS.ECS.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by name.</p>|`.*`|
|{$AWS.ECS.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.LLD.FILTER.SERVICE.MATCHES}|<p>Filter of discoverable services by name.</p>|`.*`|
|{$AWS.ECS.LLD.FILTER.SERVICE.NOT_MATCHES}|<p>Filter to exclude discovered services by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.CLUSTER.CPU.UTIL.WARN}|<p>The warning threshold of the cluster CPU utilization expressed in %.</p>|`70`|
|{$AWS.ECS.CLUSTER.MEMORY.UTIL.WARN}|<p>The warning threshold of the cluster memory utilization expressed in %.</p>|`70`|
|{$AWS.ECS.CLUSTER.SERVICE.CPU.UTIL.WARN}|<p>The warning threshold of the cluster service CPU utilization expressed in %.</p>|`80`|
|{$AWS.ECS.CLUSTER.SERVICE.MEMORY.UTIL.WARN}|<p>The warning threshold of the cluster service memory utilization expressed in %.</p>|`80`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS ECS Cluster: Get cluster metrics|<p>Get cluster metrics.</p><p>Full metrics list related to ECS: https://docs.aws.amazon.com/AmazonECS/latest/userguide/metrics-dimensions.html</p>|Script|aws.ecs.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster: Get cluster services|<p>Get cluster services.</p><p>Full metrics list related to ECS: https://docs.aws.amazon.com/AmazonECS/latest/userguide/metrics-dimensions.html</p>|Script|aws.ecs.get_cluster_services<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster: Get alarms data|<p>Get alarms data.</p><p>DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.ecs.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster: Get metrics check|<p>Data collection check.</p>|Dependent item|aws.ecs.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ECS Cluster: Get alarms check|<p>Data collection check.</p>|Dependent item|aws.ecs.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ECS Cluster: Container Instance Count|<p>'The number of EC2 instances running the Amazon ECS agent that are registered with a cluster.'</p>|Dependent item|aws.ecs.container_instance_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster: Task Count|<p>'The number of tasks running in the cluster.'</p>|Dependent item|aws.ecs.task_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster: Service Count|<p>'The number of services in the cluster.'</p>|Dependent item|aws.ecs.service_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster: CPU Reserved|<p>'A number of CPU units reserved by tasks in the resource that is specified by the dimension set that you're using.</p><p> This metric is only collected for tasks that have a defined CPU reservation in their task definition.'</p>|Dependent item|aws.ecs.cpu_reserved<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CpuReserved")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster: CPU Utilization|<p>Cluster CPU utilization</p>|Dependent item|aws.ecs.cpu_utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CPUUtilization`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster: Memory Utilization|<p>'The memory being used by tasks in the resource that is specified by the dimension set that you're using.</p><p> This metric is only collected for tasks that have a defined memory reservation in their task definition.'</p>|Dependent item|aws.ecs.memory_utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MemoryUtilization`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster: Network rx bytes|<p>'The number of bytes received by the resource that is specified by the dimensions that you're using.</p><p> This metric is only available for containers in tasks using the awsvpc or bridge network modes.'</p>|Dependent item|aws.ecs.network.rx<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster: Network tx bytes|<p>'The number of bytes transmitted by the resource that is specified by the dimensions that you're using.</p><p> This metric is only available for containers in tasks using the awsvpc or bridge network modes.'</p>|Dependent item|aws.ecs.network.tx<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ECS Cluster: Failed to get metrics data||`length(last(/AWS ECS Cluster by HTTP/aws.ecs.metrics.check))>0`|Warning||
|AWS ECS Cluster: Failed to get alarms data||`length(last(/AWS ECS Cluster by HTTP/aws.ecs.alarms.check))>0`|Warning||
|AWS ECS Cluster: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/AWS ECS Cluster by HTTP/aws.ecs.cpu_utilization,15m)>{$AWS.ECS.CLUSTER.CPU.UTIL.WARN}`|Warning||
|AWS ECS Cluster: High memory utilization|<p>The system is running out of free memory.</p>|`min(/AWS ECS Cluster by HTTP/aws.ecs.memory_utilization,15m)>{$AWS.ECS.CLUSTER.MEMORY.UTIL.WARN}`|Warning||

### LLD rule Cluster Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster Alarms discovery|<p>Discovery instance alarms.</p>|Dependent item|aws.ecs.alarms.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Cluster Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS ECS Cluster Alarms: ["{#ALARM_NAME}"]: Get metrics|<p>Get alarm metrics about the state and its reason.</p>|Dependent item|aws.ecs.alarm.get_metrics["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster Alarms: ["{#ALARM_NAME}"]: State reason|<p>An explanation for the alarm state, in text format.</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.ecs.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ECS Cluster Alarms: ["{#ALARM_NAME}"]: State|<p>The state value for the alarm. Possible values: 0 (OK), 1 (INSUFFICIENT_DATA), 2 (ALARM).</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.ecs.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateValue`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Cluster Alarms discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ECS Cluster Alarms: "{#ALARM_NAME}" has 'Alarm' state|<p>Alarm "{#ALARM_NAME}" has 'Alarm' state. <br>Reason: {ITEM.LASTVALUE2}</p>|`last(/AWS ECS Cluster by HTTP/aws.ecs.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS ECS Cluster by HTTP/aws.ecs.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS ECS Cluster Alarms: "{#ALARM_NAME}" has 'Insufficient data' state||`last(/AWS ECS Cluster by HTTP/aws.ecs.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Cluster Services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster Services discovery|<p>Discovery {$AWS.ECS.CLUSTER.NAME} services.</p>|Dependent item|aws.ecs.services.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.services`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Cluster Services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: Running Task|<p>The number of tasks currently in the `running` state.</p>|Dependent item|aws.ecs.services.running.task["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: Pending Task|<p>The number of tasks currently in the `pending` state.</p>|Dependent item|aws.ecs.services.pending.task["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: Desired Task|<p>The desired number of tasks for an {#AWS.ECS.SERVICE.NAME} service.</p>|Dependent item|aws.ecs.services.desired.task["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: Task Set|<p>The number of task sets in the {#AWS.ECS.SERVICE.NAME} service.</p>|Dependent item|aws.ecs.services.task.set["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: CPU Reserved|<p>"A number of CPU units reserved by tasks in the resource that is specified by the dimension set that you're using.</p><p> This metric is only collected for tasks that have a defined CPU reservation in their task definition."</p>|Dependent item|aws.ecs.services.cpu_reserved["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: CPU Utilization|<p>"A number of CPU units used by tasks in the resource that is specified by the dimension set that you're using.</p><p> This metric is only collected for tasks that have a defined CPU reservation in their task definition."</p>|Dependent item|aws.ecs.services.cpu.utilization["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: Memory utilized|<p>'The memory being used by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.'</p>|Dependent item|aws.ecs.services.memory_utilized["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: Memory utilization|<p>'The memory being used by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.'</p>|Dependent item|aws.ecs.services.memory.utilization["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: Memory reserved|<p>'The memory that is reserved by tasks in the resource that is specified by the dimension set that you're using. </p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.'</p>|Dependent item|aws.ecs.services.memory_reserved["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: Network rx bytes|<p>'The number of bytes received by the resource that is specified by the dimensions that you're using.</p><p>This metric is only available for containers in tasks using the awsvpc or bridge network modes.'</p>|Dependent item|aws.ecs.services.network.rx["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: Network tx bytes|<p>'The number of bytes transmitted by the resource that is specified by the dimensions that you're using.</p><p>This metric is only available for containers in tasks using the awsvpc or bridge network modes.'</p>|Dependent item|aws.ecs.services.network.tx["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS ECS Cluster Service: ["{#AWS.ECS.SERVICE.NAME}"]: Get metrics|<p>Get metrics of ESC services.</p><p>Full metrics list related to ECS : https://docs.aws.amazon.com/ecs/index.html</p>|Script|aws.ecs.services.get_metrics["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Cluster Services discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ECS Cluster Service: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/AWS ECS Cluster by HTTP/aws.ecs.services.cpu.utilization["{#AWS.ECS.SERVICE.NAME}"],15m)>{$AWS.ECS.CLUSTER.SERVICE.CPU.UTIL.WARN}`|Warning||
|AWS ECS Cluster Service: High memory utilization|<p>The system is running out of free memory.</p>|`min(/AWS ECS Cluster by HTTP/aws.ecs.services.memory.utilization["{#AWS.ECS.SERVICE.NAME}"],15m)>{$AWS.ECS.CLUSTER.SERVICE.MEMORY.UTIL.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

