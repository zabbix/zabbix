
# AWS EC2 by HTTP

## Overview

The template to monitor AWS EC2 and attached AWS EBS volumes by HTTP via Zabbix that works without any external scripts.  
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.
*NOTE*
This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the (CloudWatch pricing)[https://aws.amazon.com/cloudwatch/pricing/] page.

Additional information about metrics and used API methods:
* Full metrics list related to EBS: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using_cloudwatch_ebs.html
* Full metrics list related to EC2: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/viewing_metrics_with_cloudwatch.html
* DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html
* DescribeVolumes API method: https://docs.aws.amazon.com/AWSEC2/latest/APIReference/API_DescribeVolumes.html


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- AWS EC2 by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

The template get AWS EC2 and attached AWS EBS volumes metrics and uses the script item to make HTTP requests to the CloudWatch API.

Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account.  

Add the following required permissions to your Zabbix IAM policy in order to collect Amazon EC2 metrics.  
```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
              "ec2:DescribeVolumes",
              "cloudwatch:"DescribeAlarms",
              "cloudwatch:GetMetricData"
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
                "ec2:DescribeVolumes",
                "cloudwatch:"DescribeAlarms",
                "cloudwatch:GetMetricData"
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation"
            ],
            "Resource": "*"
        }
    ]
}
```

For more information, see the [EC2 policies](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/security-iam.html) on the AWS website.

Set macros "{$AWS.AUTH_TYPE}", "{$AWS.REGION}", "{$AWS.EC2.INSTANCE.ID}".

If you are using access key-based authorization, set the following macros "{$AWS.ACCESS.KEY.ID}", "{$AWS.SECRET.ACCESS.KEY}"

For more information about manage access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys)

Also, see the Macros section for a list of macros used for LLD filters.

Additional information about metrics and used API methods:
* Full metrics list related to EBS: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using_cloudwatch_ebs.html
* Full metrics list related to EC2: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/viewing_metrics_with_cloudwatch.html
* DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html
* DescribeVolumes API method: https://docs.aws.amazon.com/AWSEC2/latest/APIReference/API_DescribeVolumes.html


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.REGION}|<p>Amazon EC2 Region code.</p>|`us-west-1`|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: role_base, access_key.</p>|`role_base`|
|{$AWS.EC2.INSTANCE.ID}|<p>EC2 instance ID.</p>||
|{$AWS.EC2.LLD.FILTER.VOLUME_TYPE.MATCHES}|<p>Filter of discoverable volumes by type.</p>|`.*`|
|{$AWS.EC2.LLD.FILTER.VOLUME_TYPE.NOT_MATCHES}|<p>Filter to exclude discovered volumes by type.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.EC2.LLD.FILTER.ALARM_SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.EC2.LLD.FILTER.ALARM_SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.EC2.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.EC2.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.EC2.CPU.UTIL.WARN.MAX}|<p>The warning threshold of the CPU utilization expressed in %.</p>|`85`|
|{$AWS.EC2.CPU.CREDIT.BALANCE.MIN.WARN}|<p>Minimum number of free earned CPU credits for trigger expression.</p>|`50`|
|{$AWS.EC2.CPU.CREDIT.SURPLUS.BALANCE.MAX.WARN}|<p>Maximum number of spent CPU Surplus credits for trigger expression.</p>|`100`|
|{$AWS.EBS.IO.CREDIT.BALANCE.MIN.WARN}|<p>Minimum percentage of I/O credits remaining for trigger expression.</p>|`20`|
|{$AWS.EBS.BYTE.CREDIT.BALANCE.MIN.WARN}|<p>Minimum percentage of Byte credits remaining for trigger expression.</p>|`20`|
|{$AWS.EBS.BURST.CREDIT.BALANCE.MIN.WARN}|<p>Minimum percentage of Byte credits remaining for trigger expression.</p>|`20`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS EC2: Get metrics data|<p>Get instance metrics.</p><p>Full metrics list related to EC2: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/viewing_metrics_with_cloudwatch.html</p>|Script|aws.ec2.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS CloudWatch: Get instance alarms data|<p>DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.ec2.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: Get volumes data|<p>Get volumes attached to instance.</p><p>DescribeVolumes API method: https://docs.aws.amazon.com/AWSEC2/latest/APIReference/API_DescribeVolumes.html</p>|Script|aws.ec2.get_volumes<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: Get metrics check|<p>Check result of the instance metric data has been got correctly.</p>|Dependent item|aws.ec2.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS EC2: Get alarms check|<p>Check result of the alarm data has been got correctly.</p>|Dependent item|aws.ec2.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS EC2: Get volumes info check|<p>Check result of the volume information has been got correctly.</p>|Dependent item|aws.ec2.volumes.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS EC2: Credit CPU: Balance|<p>The number of earned CPU credits that an instance has accrued since it was launched or started. For T2 Standard, the CPUCreditBalance also includes the number of launch credits that have been accrued.</p><p>Credits are accrued in the credit balance after they are earned, and removed from the credit balance when they are spent. The credit balance has a maximum limit, determined by the instance size. After the limit is reached, any new credits that are earned are discarded. For T2 Standard, launch credits do not count towards the limit.</p><p>The credits in the CPUCreditBalance are available for the instance to spend to burst beyond its baseline CPU utilization.</p><p>When an instance is running, credits in the CPUCreditBalance do not expire. When a T3 or T3a instance stops, the CPUCreditBalance value persists for seven days. Thereafter, all accrued credits are lost. When a T2 instance stops, the CPUCreditBalance value does not persist, and all accrued credits are lost.</p>|Dependent item|aws.ec2.cpu.credit_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUCreditBalance")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: Credit CPU: Usage|<p>The number of CPU credits spent by the instance for CPU utilization.</p><p>One CPU credit equals one vCPU running at 100% utilization for one minute or an equivalent combination of vCPUs, utilization, and time (for example, one vCPU running at 50% utilization for two minutes or two vCPUs running at 25% utilization for two minutes).</p>|Dependent item|aws.ec2.cpu.credit_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUCreditUsage")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: Credit CPU: Surplus balance|<p>The number of surplus credits that have been spent by an unlimited instance when its CPUCreditBalance value is zero.</p><p>The CPUSurplusCreditBalance value is paid down by earned CPU credits. If the number of surplus credits exceeds the maximum number of credits that the instance can earn in a 24-hour period, the spent surplus credits above the maximum incur an additional charge.</p>|Dependent item|aws.ec2.cpu.surplus_credit_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: Credit CPU: Surplus charged|<p>The number of spent surplus credits that are not paid down by earned CPU credits, and which thus incur an additional charge.</p><p></p><p>Spent surplus credits are charged when any of the following occurs:</p><p>- The spent surplus credits exceed the maximum number of credits that the instance can earn in a 24-hour period. Spent surplus credits above the maximum are charged at the end of the hour;</p><p>- The instance is stopped or terminated;</p><p>- The instance is switched from unlimited to standard.</p>|Dependent item|aws.ec2.cpu.surplus_credit_charged<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: CPU: Utilization|<p>The percentage of allocated EC2 compute units that are currently in use on the instance. This metric identifies the processing power required to run an application on a selected instance.</p><p>Depending on the instance type, tools in your operating system can show a lower percentage than CloudWatch when the instance is not allocated a full processor core.</p>|Dependent item|aws.ec2.cpu_utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUUtilization")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: Disk: Read bytes, rate|<p>Bytes read from all instance store volumes available to the instance.</p><p>This metric is used to determine the volume of the data the application reads from the hard disk of the instance.</p><p>This can be used to determine the speed of the application.</p><p>If there are no instance store volumes, either the value is 0 or the metric is not reported.</p>|Dependent item|aws.ec2.disk.read_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DiskReadBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: Disk: Read, rate|<p>Completed read operations from all instance store volumes available to the instance in a specified period of time.</p><p>If there are no instance store volumes, either the value is 0 or the metric is not reported.</p>|Dependent item|aws.ec2.disk.read_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DiskReadOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: Disk: Write bytes, rate|<p>Bytes written to all instance store volumes available to the instance.</p><p>This metric is used to determine the volume of the data the application writes onto the hard disk of the instance.</p><p>This can be used to determine the speed of the application.</p><p>If there are no instance store volumes, either the value is 0 or the metric is not reported.</p>|Dependent item|aws.ec2.disk_write_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DiskWriteBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: Disk: Write ops, rate|<p>Completed write operations to all instance store volumes available to the instance in a specified period of time.</p><p>If there are no instance store volumes, either the value is 0 or the metric is not reported.</p>|Dependent item|aws.ec2.disk_write_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DiskWriteOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: EBS: Byte balance|<p>Percentage of throughput credits remaining in the burst bucket for Nitro-based instances.</p>|Dependent item|aws.ec2.ebs.byte_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSByteBalance%")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: EBS: IO balance|<p>Percentage of I/O credits remaining in the burst bucket for Nitro-based instances.</p>|Dependent item|aws.ec2.ebs.io_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSIOBalance%")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: EBS: Read bytes, rate|<p>Bytes read from all EBS volumes attached to the instance for Nitro-based instances.</p>|Dependent item|aws.ec2.ebs.read_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSReadBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: EBS: Read, rate|<p>Completed read operations from all Amazon EBS volumes attached to the instance for Nitro-based instances.</p>|Dependent item|aws.ec2.ebs.read_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSReadOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: EBS: Write bytes, rate|<p>Bytes written to all EBS volumes attached to the instance for Nitro-based instances.</p>|Dependent item|aws.ec2.ebs.write_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSWriteBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: EBS: Write, rate|<p>Completed write operations to all EBS volumes attached to the instance in a specified period of time.</p>|Dependent item|aws.ec2.ebs.write_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSWriteOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: Metadata: No token|<p>The number of times the instance metadata service was successfully accessed using a method that does not use a token.</p><p>This metric is used to determine if there are any processes accessing instance metadata that are using Instance Metadata Service Version 1, which does not use a token.</p><p>If all requests use token-backed sessions, i.e., Instance Metadata Service Version 2, the value is 0.</p>|Dependent item|aws.ec2.metadata.no_token<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "MetadataNoToken")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: Network: Bytes in, rate|<p>The number of bytes received on all network interfaces by the instance.</p><p>This metric identifies the volume of incoming network traffic to a single instance.</p>|Dependent item|aws.ec2.network_in.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NetworkIn")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: Network: Bytes out, rate|<p>The number of bytes sent out on all network interfaces by the instance. </p><p>This metric identifies the volume of outgoing network traffic from a single instance.</p>|Dependent item|aws.ec2.network_out.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NetworkOut")].Values.first().first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: Network: Packets in, rate|<p>The number of packets received on all network interfaces by the instance.</p><p>This metric identifies the volume of incoming traffic in terms of the number of packets on a single instance.</p><p>This metric is available for basic monitoring only.</p>|Dependent item|aws.ec2.packets_in.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NetworkPacketsIn")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: Network: Packets out, rate|<p>The number of packets sent out on all network interfaces by the instance.</p><p>This metric identifies the volume of outgoing traffic in terms of the number of packets on a single instance.</p><p>This metric is available for basic monitoring only.</p>|Dependent item|aws.ec2.packets_out.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NetworkPacketsOut")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|AWS EC2: Status: Check failed|<p>Reports whether the instance has passed both the instance status check and the system status check in the last minute.</p><p>This metric can be either 0 (passed) or 1 (failed).</p>|Dependent item|aws.ec2.status_check_failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "StatusCheckFailed")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: Status: Check failed, instance|<p>Reports whether the instance has passed the instance status check in the last minute.</p><p>This metric can be either 0 (passed) or 1 (failed).</p>|Dependent item|aws.ec2.status_check_failed_instance<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2: Status: Check failed, system|<p>Reports whether the instance has passed the system status check in the last minute.</p><p>This metric can be either 0 (passed) or 1 (failed).</p>|Dependent item|aws.ec2.status_check_failed_system<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS EC2: Failed to get metrics data||`length(last(/AWS EC2 by HTTP/aws.ec2.metrics.check))>0`|Warning||
|AWS EC2: Failed to get alarms data||`length(last(/AWS EC2 by HTTP/aws.ec2.alarms.check))>0`|Warning||
|AWS EC2: Failed to get volumes info||`length(last(/AWS EC2 by HTTP/aws.ec2.volumes.check))>0`|Warning||
|AWS EC2: Instance CPU Credit balance is too low|<p>The number of earned CPU credits has been less than {$AWS.EC2.CPU.CREDIT.BALANCE.MIN.WARN} in the last 5 minutes.</p>|`max(/AWS EC2 by HTTP/aws.ec2.cpu.credit_balance,5m)<{$AWS.EC2.CPU.CREDIT.BALANCE.MIN.WARN}`|Warning||
|AWS EC2: Instance has spent too many CPU surplus credits|<p>The number of spent surplus credits that are not paid down and which thus incur an additional charge is over {$AWS.EC2.CPU.CREDIT.SURPLUS.BALANCE.MAX.WARN}.</p>|`last(/AWS EC2 by HTTP/aws.ec2.cpu.surplus_credit_charged)>{$AWS.EC2.CPU.CREDIT.SURPLUS.BALANCE.MAX.WARN}`|Warning||
|AWS EC2: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/AWS EC2 by HTTP/aws.ec2.cpu_utilization,15m)>{$AWS.EC2.CPU.UTIL.WARN.MAX}`|Warning||
|AWS EC2: Byte Credit balance is too low||`max(/AWS EC2 by HTTP/aws.ec2.ebs.byte_balance,5m)<{$AWS.EBS.BYTE.CREDIT.BALANCE.MIN.WARN}`|Warning||
|AWS EC2: I/O Credit balance is too low||`max(/AWS EC2 by HTTP/aws.ec2.ebs.io_balance,5m)<{$AWS.EBS.IO.CREDIT.BALANCE.MIN.WARN}`|Warning||
|AWS EC2: Instance status check failed|<p>These checks detect problems that require your involvement to repair.<br>The following are examples of problems that can cause instance status checks to fail:<br><br>Failed system status checks<br>Incorrect networking or startup configuration<br>Exhausted memory<br>Corrupted file system<br>Incompatible kernel</p>|`last(/AWS EC2 by HTTP/aws.ec2.status_check_failed_instance)=1`|Average||
|AWS EC2: System status check failed|<p>These checks detect underlying problems with your instance that require AWS involvement to repair.<br>The following are examples of problems that can cause system status checks to fail:<br><br>Loss of network connectivity<br>Loss of system power<br>Software issues on the physical host<br>Hardware issues on the physical host that impact network reachability</p>|`last(/AWS EC2 by HTTP/aws.ec2.status_check_failed_system)=1`|Average||

### LLD rule Instance Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Instance Alarms discovery|<p>Discovery instance and attached EBS volumes alarms.</p>|Dependent item|aws.ec2.alarms.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Instance Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS EC2 Alarms: ["{#ALARM_NAME}"]: Get metrics|<p>Get alarm metrics about the state and its reason.</p>|Dependent item|aws.ec2.alarm.get_metrics["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EC2 Alarms: ["{#ALARM_NAME}"]: State reason|<p>An explanation for the alarm state, in text format.</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.ec2.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS EC2 Alarms: ["{#ALARM_NAME}"]: State|<p>The state value for the alarm. Possible values: 0 (OK), 1 (INSUFFICIENT_DATA), 2 (ALARM).</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.ec2.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateValue`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Instance Alarms discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS EC2 Alarms: "{#ALARM_NAME}" has 'Alarm' state|<p>Alarm "{#ALARM_NAME}" has 'Alarm' state. <br>Reason: {ITEM.LASTVALUE2}</p>|`last(/AWS EC2 by HTTP/aws.ec2.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS EC2 by HTTP/aws.ec2.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS EC2 Alarms: "{#ALARM_NAME}" has 'Insufficient data' state||`last(/AWS EC2 by HTTP/aws.ec2.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Instance Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Instance Volumes discovery|<p>Discovery attached EBS volumes.</p>|Dependent item|aws.ec2.volumes.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Instance Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS EBS: ["{#VOLUME_ID}"]: Get volume data|<p>Get data of the "{#VOLUME_ID}" volume.</p>|Dependent item|aws.ec2.ebs.get_volume["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.volumeId == "{#VOLUME_ID}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Create time|<p>The time stamp when volume creation was initiated.</p>|Dependent item|aws.ec2.ebs.create_time["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.createTime`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Status|<p>The state of the volume.</p><p>Possible values: 0 (creating), 1 (available), 2 (in-use), 3 (deleting), 4 (deleted), 5 (error).</p>|Dependent item|aws.ec2.ebs.status["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Attachment state|<p>The attachment state of the volume. Possible values: 0 (attaching), 1 (attached), 2 (detaching).</p>|Dependent item|aws.ec2.ebs.attachment_status["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Attachment time|<p>The time stamp when the attachment initiated.</p>|Dependent item|aws.ec2.ebs.attachment_time["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Device|<p>The device name specified in the block device mapping (for example, /dev/sda1).</p>|Dependent item|aws.ec2.ebs.device["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Get metrics|<p>Get metrics of EBS volume.</p><p>Full metrics list related to EBS: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using_cloudwatch_ebs.html</p>|Script|aws.ec2.get_ebs_metrics["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Read, bytes|<p>Provides information on the read operations in a specified period of time.</p><p>The average size of each read operation during the period, except on volumes attached to a Nitro-based instance, where the average represents the average over the specified period.</p><p>For Xen instances, data is reported only when there is read activity on the volume.</p>|Dependent item|aws.ec2.ebs.volume.read_bytes["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeReadBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Write, bytes|<p>Provides information on the write operations in a specified period of time.</p><p>The average size of each write operation during the period, except on volumes attached to a Nitro-based instance, where the average represents the average over the specified period.</p><p>For Xen instances, data is reported only when there is write activity on the volume.</p>|Dependent item|aws.ec2.ebs.volume.write_bytes["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeWriteBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Write, ops|<p>The total number of write operations in a specified period of time. Note: write operations are counted on completion.</p>|Dependent item|aws.ec2.ebs.volume.write_ops["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeWriteOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Read, ops|<p>The total number of read operations in a specified period of time. Note: read operations are counted on completion.</p>|Dependent item|aws.ec2.ebs.volume.read_ops["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeReadOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Read time, total|<p>This metric is not supported with Multi-Attach enabled volumes.</p><p>The total number of seconds spent by all read operations that completed in a specified period of time.</p><p>If multiple requests are submitted at the same time, this total could be greater than the length of the period. </p><p>For example, for a period of 1 minutes (60 seconds): if 150 operations completed during that period, and each operation took 1 second, the value would be 150 seconds. </p><p>For Xen instances, data is reported only when there is read activity on the volume.</p>|Dependent item|aws.ec2.ebs.volume.total_read_time["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Write time, total|<p>This metric is not supported with Multi-Attach enabled volumes.</p><p>The total number of seconds spent by all write operations that completed in a specified period of time.</p><p>If multiple requests are submitted at the same time, this total could be greater than the length of the period.</p><p>For example, for a period of 1 minute (60 seconds): if 150 operations completed during that period, and each operation took 1 second, the value would be 150 seconds. </p><p>For Xen instances, data is reported only when there is write activity on the volume.</p>|Dependent item|aws.ec2.ebs.volume.total_write_time["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Idle time|<p>This metric is not supported with Multi-Attach enabled volumes.</p><p>The total number of seconds in a specified period of time when no read or write operations were submitted.</p>|Dependent item|aws.ec2.ebs.volume.idle_time["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeIdleTime")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Queue length|<p>The number of read and write operation requests waiting to be completed in a specified period of time.</p>|Dependent item|aws.ec2.ebs.volume.queue_length["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeQueueLength")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Throughput, pct|<p>This metric is not supported with Multi-Attach enabled volumes.</p><p>Used with Provisioned IOPS SSD volumes only. The percentage of I/O operations per second (IOPS) delivered of the total IOPS provisioned for an Amazon EBS volume.</p><p>Provisioned IOPS SSD volumes deliver their provisioned performance 99.9 percent of the time.</p><p>During a write, if there are no other pending I/O requests in a minute, the metric value will be 100 percent.</p><p>Also, a volume's I/O performance may become degraded temporarily due to an action you have taken (for example, creating a snapshot of a volume during peak usage, running the volume on a non-EBS-optimized instance, or accessing data on the volume for the first time).</p>|Dependent item|aws.ec2.ebs.volume.throughput_percentage["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Consumed Read/Write, ops|<p>Used with Provisioned IOPS SSD volumes only.</p><p>The total amount of read and write operations (normalized to 256K capacity units) consumed in a specified period of time.</p><p>I/O operations that are smaller than 256K each count as 1 consumed IOPS. I/O operations that are larger than 256K are counted in 256K capacity units. </p><p>For example, a 1024K I/O would count as 4 consumed IOPS.</p>|Dependent item|aws.ec2.ebs.volume.consumed_read_write_ops["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS EBS: ["{#VOLUME_ID}"]: Burst balance|<p>Used with General Purpose SSD (gp2), Throughput Optimized HDD (st1), and Cold HDD (sc1) volumes only.</p><p>Provides information about the percentage of I/O credits (for gp2) or throughput credits (for st1 and sc1) remaining in the burst bucket. </p><p>Data is reported to CloudWatch only when the volume is active. If the volume is not attached, no data is reported.</p>|Dependent item|aws.ec2.ebs.volume.burst_balance["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "BurstBalance")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Instance Volumes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS EBS: Volume "{#VOLUME_ID}" has 'error' state||`last(/AWS EC2 by HTTP/aws.ec2.ebs.status["{#VOLUME_ID}"])=5`|Warning||
|AWS EBS: Burst balance is too low||`max(/AWS EC2 by HTTP/aws.ec2.ebs.volume.burst_balance["{#VOLUME_ID}"],5m)<{$AWS.EBS.BURST.CREDIT.BALANCE.MIN.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

