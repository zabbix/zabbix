
# AWS by HTTP

## Overview

This template is designed for the effortless deployment of AWS monitoring by Zabbix via HTTP and doesn't require any external scripts.
- Currently, the template supports the discovery of EC2 and RDS instances, ECS clusters, ELB, Lambda, S3 buckets, and backup vaults.

## Included Monitoring Templates

- *AWS EC2 by HTTP*
- *AWS ECS Cluster by HTTP*
- *AWS ECS Serverless Cluster by HTTP*
- *AWS ELB Application Load Balancer by HTTP*
- *AWS ELB Network Load Balancer by HTTP*
- *AWS Lambda by HTTP*
- *AWS RDS instance by HTTP*
- *AWS S3 bucket by HTTP*
- *AWS Cost Explorer by HTTP*
- *AWS Backup Vault by HTTP*

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Before using the template, you need to create an IAM policy for the Zabbix role in your AWS account with the necessary permissions.

### Required Permissions
Add the following required permissions to your Zabbix IAM policy in order to collect metrics.

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Action": [
                "cloudwatch:DescribeAlarms",
                "cloudwatch:GetMetricData",
                "ec2:DescribeInstances",
                "ec2:DescribeVolumes",
                "ec2:DescribeRegions",
                "rds:DescribeEvents",
                "rds:DescribeDBInstances",
                "ecs:DescribeClusters",
                "ecs:ListServices",
                "ecs:ListTasks",
                "ecs:ListClusters",
                "s3:ListAllMyBuckets",
                "s3:GetBucketLocation",
                "s3:GetMetricsConfiguration",
                "elasticloadbalancing:DescribeLoadBalancers",
                "elasticloadbalancing:DescribeTargetGroups",
                "ec2:DescribeSecurityGroups",
                "lambda:ListFunctions",
                "backup:ListBackupVaults",
                "backup:ListBackupJobs",
                "backup:ListCopyJobs",
                "backup:ListRestoreJobs"
            ],
            "Effect": "Allow",
            "Resource": "*"
        }
    ]
}
```

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume Role Authorization
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
                "cloudwatch:GetMetricData",
                "ec2:DescribeInstances",
                "ec2:DescribeVolumes",
                "ec2:DescribeRegions",
                "rds:DescribeEvents",
                "rds:DescribeDBInstances",
                "ecs:DescribeClusters",
                "ecs:ListServices",
                "ecs:ListTasks",
                "ecs:ListClusters",
                "s3:ListAllMyBuckets",
                "s3:GetBucketLocation",
                "s3:GetMetricsConfiguration",
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation",
                "elasticloadbalancing:DescribeLoadBalancers",
                "elasticloadbalancing:DescribeTargetGroups",
                "ec2:DescribeSecurityGroups",
                "lambda:ListFunctions",
                "backup:ListBackupVaults",
                "backup:ListBackupJobs",
                "backup:ListCopyJobs",
                "backup:ListRestoreJobs"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
If you are using role-based authorization, add the appropriate permissions:

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
            "Effect": "Allow",
            "Action": [
                "cloudwatch:DescribeAlarms",
                "cloudwatch:GetMetricData",
                "ec2:DescribeInstances",
                "ec2:DescribeVolumes",
                "ec2:DescribeRegions",
                "rds:DescribeEvents",
                "rds:DescribeDBInstances",
                "ecs:DescribeClusters",
                "ecs:ListServices",
                "ecs:ListTasks",
                "ecs:ListClusters",
                "s3:ListAllMyBuckets",
                "s3:GetBucketLocation",
                "s3:GetMetricsConfiguration",
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation",
                "elasticloadbalancing:DescribeLoadBalancers",
                "elasticloadbalancing:DescribeTargetGroups",
                "ec2:DescribeSecurityGroups",
                "lambda:ListFunctions",
                "backup:ListBackupVaults",
                "backup:ListBackupJobs",
                "backup:ListCopyJobs",
                "backup:ListRestoreJobs"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Role-Based Authorization
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

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

To gather Request metrics, enable [Requests metrics](https://docs.aws.amazon.com/AmazonS3/latest/userguide/cloudwatch-monitoring.html) on your Amazon S3 buckets from the AWS console.

Set the macros: `{$AWS.AUTH_TYPE}`. Possible values: `access_key`, `assume_role`, `role_base`.

For more information about managing access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

Refer to the Macros section for a list of macros used for LLD filters.

Additional information about the metrics and used API methods:
* [Full metrics list related to EBS](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using_cloudwatch_ebs.html)
* [Full metrics list related to EC2](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/viewing_metrics_with_cloudwatch.html)
* [Full metrics list related to RDS](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/rds-metrics.html)
* [Full metrics list related to Amazon Aurora](https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/Aurora.AuroraMySQL.Monitoring.Metrics.html#Aurora.AuroraMySQL.Monitoring.Metrics.instances)
* [Full metrics list related to S3](https://docs.aws.amazon.com/AmazonS3/latest/userguide/metrics-dimensions.html)
* [Full metrics list related to ECS](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/cloudwatch-metrics.html)
* [Full metrics list related to ELB ALB](https://docs.aws.amazon.com/elasticloadbalancing/latest/application/load-balancer-cloudwatch-metrics.html)
* [Full metrics list related to Backup vault](https://docs.aws.amazon.com/aws-backup/latest/devguide/API_BackupVaultListMember.html)
* [Full metrics list related to Backup jobs](https://docs.aws.amazon.com/aws-backup/latest/devguide/API_BackupJob.html)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)
* [DescribeVolumes API method](https://docs.aws.amazon.com/AWSEC2/latest/APIReference/API_DescribeVolumes.html)
* [DescribeLoadBalancers API method](https://docs.aws.amazon.com/elasticloadbalancing/latest/APIReference/API_DescribeLoadBalancers.html)


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$AWS.REQUEST.REGION}|<p>Region used in GET request `ListBuckets`.</p>|`us-east-1`|
|{$AWS.DESCRIBE.REGION}|<p>Region used in POST request `DescribeRegions`.</p>|`us-east-1`|
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`60s`|
|{$AWS.EC2.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable EC2 instances by namespace.</p>|`.*`|
|{$AWS.EC2.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discovered EC2 instances by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.EC2.LLD.FILTER.REGION.MATCHES}|<p>Filter of discoverable EC2 instances by region.</p>|`.*`|
|{$AWS.EC2.LLD.FILTER.REGION.NOT_MATCHES}|<p>Filter to exclude discovered EC2 instances by region.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable ECS clusters by name.</p>|`.*`|
|{$AWS.ECS.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discovered ECS clusters by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.LLD.FILTER.STATUS.MATCHES}|<p>Filter of discoverable ECS clusters by status.</p>|`ACTIVE`|
|{$AWS.ECS.LLD.FILTER.STATUS.NOT_MATCHES}|<p>Filter to exclude discovered ECS clusters by status.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.S3.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable S3 buckets by namespace.</p>|`.*`|
|{$AWS.S3.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discovered S3 buckets by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.RDS.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable RDS instances by namespace.</p>|`.*`|
|{$AWS.RDS.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discovered RDS instances by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.RDS.LLD.FILTER.REGION.MATCHES}|<p>Filter of discoverable RDS instances by region.</p>|`.*`|
|{$AWS.RDS.LLD.FILTER.REGION.NOT_MATCHES}|<p>Filter to exclude discovered RDS instances by region.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.LLD.FILTER.REGION.MATCHES}|<p>Filter of discoverable ECS clusters by region.</p>|`.*`|
|{$AWS.ECS.LLD.FILTER.REGION.NOT_MATCHES}|<p>Filter to exclude discovered ECS clusters by region.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ELB.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable ELB load balancers by name.</p>|`.*`|
|{$AWS.ELB.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discovered ELB load balancers by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ELB.LLD.FILTER.REGION.MATCHES}|<p>Filter of discoverable ELB load balancers by region.</p>|`.*`|
|{$AWS.ELB.LLD.FILTER.REGION.NOT_MATCHES}|<p>Filter to exclude discovered ELB load balancers by region.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ELB.LLD.FILTER.STATE.MATCHES}|<p>Filter of discoverable ELB load balancers by status.</p>|`active`|
|{$AWS.ELB.LLD.FILTER.STATE.NOT_MATCHES}|<p>Filter to exclude discovered ELB load balancer by status.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.LAMBDA.LLD.FILTER.REGION.MATCHES}|<p>Filter of discoverable Lambda functions by region.</p>|`.*`|
|{$AWS.LAMBDA.LLD.FILTER.REGION.NOT_MATCHES}|<p>Filter to exclude discovered Lambda functions by region.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.LAMBDA.LLD.FILTER.RUNTIME.MATCHES}|<p>Filter of discoverable Lambda functions by Runtime.</p>|`.*`|
|{$AWS.LAMBDA.LLD.FILTER.RUNTIME.NOT_MATCHES}|<p>Filter to exclude discovered Lambda functions by Runtime.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.LAMBDA.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable Lambda functions by name.</p>|`.*`|
|{$AWS.LAMBDA.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discovered Lambda functions by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.BACKUP_VAULT.LLD.FILTER.NAME.MATCHES}|<p>Filter of discoverable backup vaults by name.</p>|`.*`|
|{$AWS.BACKUP_VAULT.LLD.FILTER.NAME.NOT_MATCHES}|<p>Filter to exclude discovered backup vaults by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.BACKUP_VAULT.LLD.FILTER.REGION.MATCHES}|<p>Filter of discoverable backup vaults by region.</p>|`.*`|
|{$AWS.BACKUP_VAULT.LLD.FILTER.REGION.NOT_MATCHES}|<p>Filter to exclude discovered backup vaults by region.</p>|`CHANGE_IF_NEEDED`|

### LLD rule S3 buckets discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|S3 buckets discovery|<p>Get S3 bucket instances.</p>|Script|aws.s3.discovery|

### LLD rule EC2 instances discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|EC2 instances discovery|<p>Get EC2 instances.</p>|Script|aws.ec2.discovery|

### LLD rule RDS instances discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|RDS instances discovery|<p>Get RDS instances.</p>|Script|aws.rds.discovery|

### LLD rule ECS clusters discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ECS clusters discovery|<p>Get ECS clusters.</p>|Script|aws.ecs.discovery|

### LLD rule ELB load balancers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ELB load balancers discovery|<p>Get ELB load balancers.</p>|Script|aws.elb.discovery|

### LLD rule Lambda discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Lambda discovery|<p>Get Lambda functions.</p>|Script|aws.lambda.discovery|

### LLD rule Backup vault discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Backup vault discovery|<p>Get backup vaults.</p>|Script|aws.backup_vault.discovery|

# AWS EC2 by HTTP

## Overview

The template to monitor AWS EC2 and attached AWS EBS volumes by HTTP via Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

**Note:** This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the [CloudWatch pricing](https://aws.amazon.com/cloudwatch/pricing/) page.

Additional information about metrics and used API methods:
* [Full metrics list related to EBS](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using_cloudwatch_ebs.html)
* [Full metrics list related to EC2](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/viewing_metrics_with_cloudwatch.html)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)
* [DescribeVolumes API method](https://docs.aws.amazon.com/AWSEC2/latest/APIReference/API_DescribeVolumes.html)


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS EC2 by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

The template get AWS EC2 and attached AWS EBS volumes metrics and uses the script item to make HTTP requests to the CloudWatch API.
Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account.

### Required Permissions
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

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume Role Authorization
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
                "ec2:DescribeVolumes",
                "cloudwatch:"DescribeAlarms",
                "cloudwatch:GetMetricData"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
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

#### Trust Relationships for Role-Based Authorization
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

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

For more information, see the [EC2 policies](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/security-iam.html) on the AWS website.

Set the macros: `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, `{$AWS.EC2.INSTANCE.ID}`.

For more information about managing access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

Also, see the Macros section for a list of macros used for LLD filters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.REGION}|<p>Amazon EC2 Region code.</p>|`us-west-1`|
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$AWS.EC2.INSTANCE.ID}|<p>EC2 instance ID.</p>||
|{$AWS.EC2.LLD.FILTER.VOLUME_TYPE.MATCHES}|<p>Filter of discoverable volumes by type.</p>|`.*`|
|{$AWS.EC2.LLD.FILTER.VOLUME_TYPE.NOT_MATCHES}|<p>Filter to exclude discovered volumes by type.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.EC2.LLD.FILTER.ALARM_SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.EC2.LLD.FILTER.ALARM_SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.EC2.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by name.</p>|`.*`|
|{$AWS.EC2.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.EC2.CPU.UTIL.WARN.MAX}|<p>The warning threshold of the CPU utilization expressed in %.</p>|`85`|
|{$AWS.EC2.CPU.CREDIT.BALANCE.MIN.WARN}|<p>Minimum number of free earned CPU credits for trigger expression.</p>|`50`|
|{$AWS.EC2.CPU.CREDIT.SURPLUS.BALANCE.MAX.WARN}|<p>Maximum number of spent CPU Surplus credits for trigger expression.</p>|`100`|
|{$AWS.EBS.IO.CREDIT.BALANCE.MIN.WARN}|<p>Minimum percentage of I/O credits remaining for trigger expression.</p>|`20`|
|{$AWS.EBS.BYTE.CREDIT.BALANCE.MIN.WARN}|<p>Minimum percentage of Byte credits remaining for trigger expression.</p>|`20`|
|{$AWS.EBS.BURST.CREDIT.BALANCE.MIN.WARN}|<p>Minimum percentage of Byte credits remaining for trigger expression.</p>|`20`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get metrics data|<p>Get instance metrics.</p><p>Full metrics list related to EC2: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/viewing_metrics_with_cloudwatch.html</p>|Script|aws.ec2.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get instance alarms data|<p>DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.ec2.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get volumes data|<p>Get volumes attached to instance.</p><p>DescribeVolumes API method: https://docs.aws.amazon.com/AWSEC2/latest/APIReference/API_DescribeVolumes.html</p>|Script|aws.ec2.get_volumes<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get metrics check|<p>Check result of the instance metric data has been got correctly.</p>|Dependent item|aws.ec2.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get alarms check|<p>Check result of the alarm data has been got correctly.</p>|Dependent item|aws.ec2.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get volumes info check|<p>Check result of the volume information has been got correctly.</p>|Dependent item|aws.ec2.volumes.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Credit CPU: Balance|<p>The number of earned CPU credits that an instance has accrued since it was launched or started. For T2 Standard, the CPUCreditBalance also includes the number of launch credits that have been accrued.</p><p>Credits are accrued in the credit balance after they are earned, and removed from the credit balance when they are spent. The credit balance has a maximum limit, determined by the instance size. After the limit is reached, any new credits that are earned are discarded. For T2 Standard, launch credits do not count towards the limit.</p><p>The credits in the CPUCreditBalance are available for the instance to spend to burst beyond its baseline CPU utilization.</p><p>When an instance is running, credits in the CPUCreditBalance do not expire. When a T3 or T3a instance stops, the CPUCreditBalance value persists for seven days. Thereafter, all accrued credits are lost. When a T2 instance stops, the CPUCreditBalance value does not persist, and all accrued credits are lost.</p>|Dependent item|aws.ec2.cpu.credit_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUCreditBalance")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Credit CPU: Usage|<p>The number of CPU credits spent by the instance for CPU utilization.</p><p>One CPU credit equals one vCPU running at 100% utilization for one minute or an equivalent combination of vCPUs, utilization, and time (for example, one vCPU running at 50% utilization for two minutes or two vCPUs running at 25% utilization for two minutes).</p>|Dependent item|aws.ec2.cpu.credit_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUCreditUsage")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Credit CPU: Surplus balance|<p>The number of surplus credits that have been spent by an unlimited instance when its CPUCreditBalance value is zero.</p><p>The CPUSurplusCreditBalance value is paid down by earned CPU credits. If the number of surplus credits exceeds the maximum number of credits that the instance can earn in a 24-hour period, the spent surplus credits above the maximum incur an additional charge.</p>|Dependent item|aws.ec2.cpu.surplus_credit_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Credit CPU: Surplus charged|<p>The number of spent surplus credits that are not paid down by earned CPU credits, and which thus incur an additional charge.</p><p></p><p>Spent surplus credits are charged when any of the following occurs:</p><p>- The spent surplus credits exceed the maximum number of credits that the instance can earn in a 24-hour period. Spent surplus credits above the maximum are charged at the end of the hour;</p><p>- The instance is stopped or terminated;</p><p>- The instance is switched from unlimited to standard.</p>|Dependent item|aws.ec2.cpu.surplus_credit_charged<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU: Utilization|<p>The percentage of allocated EC2 compute units that are currently in use on the instance. This metric identifies the processing power required to run an application on a selected instance.</p><p>Depending on the instance type, tools in your operating system can show a lower percentage than CloudWatch when the instance is not allocated a full processor core.</p>|Dependent item|aws.ec2.cpu_utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUUtilization")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Read bytes, rate|<p>Bytes read from all instance store volumes available to the instance.</p><p>This metric is used to determine the volume of the data the application reads from the hard disk of the instance.</p><p>This can be used to determine the speed of the application.</p><p>If there are no instance store volumes, either the value is 0 or the metric is not reported.</p>|Dependent item|aws.ec2.disk.read_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DiskReadBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Disk: Read, rate|<p>Completed read operations from all instance store volumes available to the instance in a specified period of time.</p><p>If there are no instance store volumes, either the value is 0 or the metric is not reported.</p>|Dependent item|aws.ec2.disk.read_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DiskReadOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Disk: Write bytes, rate|<p>Bytes written to all instance store volumes available to the instance.</p><p>This metric is used to determine the volume of the data the application writes onto the hard disk of the instance.</p><p>This can be used to determine the speed of the application.</p><p>If there are no instance store volumes, either the value is 0 or the metric is not reported.</p>|Dependent item|aws.ec2.disk_write_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DiskWriteBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Disk: Write ops, rate|<p>Completed write operations to all instance store volumes available to the instance in a specified period of time.</p><p>If there are no instance store volumes, either the value is 0 or the metric is not reported.</p>|Dependent item|aws.ec2.disk_write_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DiskWriteOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|EBS: Byte balance|<p>Percentage of throughput credits remaining in the burst bucket for Nitro-based instances.</p>|Dependent item|aws.ec2.ebs.byte_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSByteBalance%")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|EBS: IO balance|<p>Percentage of I/O credits remaining in the burst bucket for Nitro-based instances.</p>|Dependent item|aws.ec2.ebs.io_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSIOBalance%")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|EBS: Read bytes, rate|<p>Bytes read from all EBS volumes attached to the instance for Nitro-based instances.</p>|Dependent item|aws.ec2.ebs.read_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSReadBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|EBS: Read, rate|<p>Completed read operations from all Amazon EBS volumes attached to the instance for Nitro-based instances.</p>|Dependent item|aws.ec2.ebs.read_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSReadOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|EBS: Write bytes, rate|<p>Bytes written to all EBS volumes attached to the instance for Nitro-based instances.</p>|Dependent item|aws.ec2.ebs.write_bytes.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSWriteBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|EBS: Write, rate|<p>Completed write operations to all EBS volumes attached to the instance in a specified period of time.</p>|Dependent item|aws.ec2.ebs.write_ops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSWriteOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Metadata: No token|<p>The number of times the instance metadata service was successfully accessed using a method that does not use a token.</p><p>This metric is used to determine if there are any processes accessing instance metadata that are using Instance Metadata Service Version 1, which does not use a token.</p><p>If all requests use token-backed sessions, i.e., Instance Metadata Service Version 2, the value is 0.</p>|Dependent item|aws.ec2.metadata.no_token<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "MetadataNoToken")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Bytes in, rate|<p>The number of bytes received on all network interfaces by the instance.</p><p>This metric identifies the volume of incoming network traffic to a single instance.</p>|Dependent item|aws.ec2.network_in.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NetworkIn")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Network: Bytes out, rate|<p>The number of bytes sent out on all network interfaces by the instance.</p><p>This metric identifies the volume of outgoing network traffic from a single instance.</p>|Dependent item|aws.ec2.network_out.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NetworkOut")].Values.first().first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Network: Packets in, rate|<p>The number of packets received on all network interfaces by the instance.</p><p>This metric identifies the volume of incoming traffic in terms of the number of packets on a single instance.</p><p>This metric is available for basic monitoring only.</p>|Dependent item|aws.ec2.packets_in.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NetworkPacketsIn")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Network: Packets out, rate|<p>The number of packets sent out on all network interfaces by the instance.</p><p>This metric identifies the volume of outgoing traffic in terms of the number of packets on a single instance.</p><p>This metric is available for basic monitoring only.</p>|Dependent item|aws.ec2.packets_out.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NetworkPacketsOut")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Status: Check failed|<p>Reports whether the instance has passed both the instance status check and the system status check in the last minute.</p><p>This metric can be either 0 (passed) or 1 (failed).</p>|Dependent item|aws.ec2.status_check_failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "StatusCheckFailed")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Status: Check failed, instance|<p>Reports whether the instance has passed the instance status check in the last minute.</p><p>This metric can be either 0 (passed) or 1 (failed).</p>|Dependent item|aws.ec2.status_check_failed_instance<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Status: Check failed, system|<p>Reports whether the instance has passed the system status check in the last minute.</p><p>This metric can be either 0 (passed) or 1 (failed).</p>|Dependent item|aws.ec2.status_check_failed_system<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS EC2: Failed to get metrics data|<p>Failed to get CloudWatch metrics for EC2.</p>|`length(last(/AWS EC2 by HTTP/aws.ec2.metrics.check))>0`|Warning||
|AWS EC2: Failed to get alarms data|<p>Failed to get CloudWatch alarms for EC2.</p>|`length(last(/AWS EC2 by HTTP/aws.ec2.alarms.check))>0`|Warning||
|AWS EC2: Failed to get volumes info|<p>Failed to get CloudWatch volumes for EC2.</p>|`length(last(/AWS EC2 by HTTP/aws.ec2.volumes.check))>0`|Warning||
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
|[{#ALARM_NAME}]: Get metrics|<p>Get alarm metrics about the state and its reason.</p>|Dependent item|aws.ec2.alarm.get_metrics["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#ALARM_NAME}]: State reason|<p>An explanation for the alarm state, in text format.</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.ec2.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#ALARM_NAME}]: State|<p>The state value for the alarm. Possible values: 0 (OK), 1 (INSUFFICIENT_DATA), 2 (ALARM).</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.ec2.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateValue`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Instance Alarms discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS EC2: [{#ALARM_NAME}] has 'Alarm' state|<p>Alarm "{#ALARM_NAME}" has 'Alarm' state.<br>Reason: {ITEM.LASTVALUE2}</p>|`last(/AWS EC2 by HTTP/aws.ec2.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS EC2 by HTTP/aws.ec2.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS EC2: [{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS EC2 by HTTP/aws.ec2.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Instance Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Instance Volumes discovery|<p>Discovery attached EBS volumes.</p>|Dependent item|aws.ec2.volumes.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Instance Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#VOLUME_ID}]: Get volume data|<p>Get data of the "{#VOLUME_ID}" volume.</p>|Dependent item|aws.ec2.ebs.get_volume["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.volumeId == "{#VOLUME_ID}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Create time|<p>The time stamp when volume creation was initiated.</p>|Dependent item|aws.ec2.ebs.create_time["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.createTime`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#VOLUME_ID}]: Status|<p>The state of the volume.</p><p>Possible values: 0 (creating), 1 (available), 2 (in-use), 3 (deleting), 4 (deleted), 5 (error).</p>|Dependent item|aws.ec2.ebs.status["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#VOLUME_ID}]: Attachment state|<p>The attachment state of the volume. Possible values: 0 (attaching), 1 (attached), 2 (detaching).</p>|Dependent item|aws.ec2.ebs.attachment_status["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#VOLUME_ID}]: Attachment time|<p>The time stamp when the attachment initiated.</p>|Dependent item|aws.ec2.ebs.attachment_time["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#VOLUME_ID}]: Device|<p>The device name specified in the block device mapping (for example, /dev/sda1).</p>|Dependent item|aws.ec2.ebs.device["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#VOLUME_ID}]: Get metrics|<p>Get metrics of EBS volume.</p><p>Full metrics list related to EBS: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using_cloudwatch_ebs.html</p>|Script|aws.ec2.get_ebs_metrics["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Read, bytes|<p>Provides information on the read operations in a specified period of time.</p><p>The average size of each read operation during the period, except on volumes attached to a Nitro-based instance, where the average represents the average over the specified period.</p><p>For Xen instances, data is reported only when there is read activity on the volume.</p>|Dependent item|aws.ec2.ebs.volume.read_bytes["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeReadBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Write, bytes|<p>Provides information on the write operations in a specified period of time.</p><p>The average size of each write operation during the period, except on volumes attached to a Nitro-based instance, where the average represents the average over the specified period.</p><p>For Xen instances, data is reported only when there is write activity on the volume.</p>|Dependent item|aws.ec2.ebs.volume.write_bytes["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeWriteBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Write, ops|<p>The total number of write operations in a specified period of time. Note: write operations are counted on completion.</p>|Dependent item|aws.ec2.ebs.volume.write_ops["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeWriteOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Read, ops|<p>The total number of read operations in a specified period of time. Note: read operations are counted on completion.</p>|Dependent item|aws.ec2.ebs.volume.read_ops["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeReadOps")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Read time, total|<p>This metric is not supported with Multi-Attach enabled volumes.</p><p>The total number of seconds spent by all read operations that completed in a specified period of time.</p><p>If multiple requests are submitted at the same time, this total could be greater than the length of the period.</p><p>For example, for a period of 1 minutes (60 seconds): if 150 operations completed during that period, and each operation took 1 second, the value would be 150 seconds.</p><p>For Xen instances, data is reported only when there is read activity on the volume.</p>|Dependent item|aws.ec2.ebs.volume.total_read_time["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Write time, total|<p>This metric is not supported with Multi-Attach enabled volumes.</p><p>The total number of seconds spent by all write operations that completed in a specified period of time.</p><p>If multiple requests are submitted at the same time, this total could be greater than the length of the period.</p><p>For example, for a period of 1 minute (60 seconds): if 150 operations completed during that period, and each operation took 1 second, the value would be 150 seconds.</p><p>For Xen instances, data is reported only when there is write activity on the volume.</p>|Dependent item|aws.ec2.ebs.volume.total_write_time["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Idle time|<p>This metric is not supported with Multi-Attach enabled volumes.</p><p>The total number of seconds in a specified period of time when no read or write operations were submitted.</p>|Dependent item|aws.ec2.ebs.volume.idle_time["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeIdleTime")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Queue length|<p>The number of read and write operation requests waiting to be completed in a specified period of time.</p>|Dependent item|aws.ec2.ebs.volume.queue_length["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "VolumeQueueLength")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Throughput, pct|<p>This metric is not supported with Multi-Attach enabled volumes.</p><p>Used with Provisioned IOPS SSD volumes only. The percentage of I/O operations per second (IOPS) delivered of the total IOPS provisioned for an Amazon EBS volume.</p><p>Provisioned IOPS SSD volumes deliver their provisioned performance 99.9 percent of the time.</p><p>During a write, if there are no other pending I/O requests in a minute, the metric value will be 100 percent.</p><p>Also, a volume's I/O performance may become degraded temporarily due to an action you have taken (for example, creating a snapshot of a volume during peak usage, running the volume on a non-EBS-optimized instance, or accessing data on the volume for the first time).</p>|Dependent item|aws.ec2.ebs.volume.throughput_percentage["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Consumed Read/Write, ops|<p>Used with Provisioned IOPS SSD volumes only.</p><p>The total amount of read and write operations (normalized to 256K capacity units) consumed in a specified period of time.</p><p>I/O operations that are smaller than 256K each count as 1 consumed IOPS. I/O operations that are larger than 256K are counted in 256K capacity units.</p><p>For example, a 1024K I/O would count as 4 consumed IOPS.</p>|Dependent item|aws.ec2.ebs.volume.consumed_read_write_ops["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#VOLUME_ID}]: Burst balance|<p>Used with General Purpose SSD (gp2), Throughput Optimized HDD (st1), and Cold HDD (sc1) volumes only.</p><p>Provides information about the percentage of I/O credits (for gp2) or throughput credits (for st1 and sc1) remaining in the burst bucket.</p><p>Data is reported to CloudWatch only when the volume is active. If the volume is not attached, no data is reported.</p>|Dependent item|aws.ec2.ebs.volume.burst_balance["{#VOLUME_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "BurstBalance")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Instance Volumes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS EC2: Volume [{#VOLUME_ID}] has 'error' state||`last(/AWS EC2 by HTTP/aws.ec2.ebs.status["{#VOLUME_ID}"])=5`|Warning||
|AWS EC2: Burst balance is too low||`max(/AWS EC2 by HTTP/aws.ec2.ebs.volume.burst_balance["{#VOLUME_ID}"],5m)<{$AWS.EBS.BURST.CREDIT.BALANCE.MIN.WARN}`|Warning||

# AWS RDS instance by HTTP

## Overview

The template to monitor AWS RDS instance by HTTP via Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

**Note:** This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the [CloudWatch pricing](https://aws.amazon.com/cloudwatch/pricing/) page.

Additional information about metrics and used API methods:

* [Full metrics list related to RDS](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/rds-metrics.html)
* [Full metrics list related to Amazon Aurora](https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/Aurora.AuroraMySQL.Monitoring.Metrics.html#Aurora.AuroraMySQL.Monitoring.Metrics.instances)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS RDS instance by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

The template get AWS RDS instance metrics and uses the script item to make HTTP requests to the CloudWatch API.
Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account.

### Required Permissions
Add the following required permissions to your Zabbix IAM policy in order to collect Amazon RDS metrics.

```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
                "cloudwatch:DescribeAlarms",
                "cloudwatch:GetMetricData",
                "rds:DescribeEvents",
                "rds:DescribeDBInstances"
          ],
          "Effect":"Allow",
          "Resource":"*"
        }
    ]
  }
```

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume Role Authorization
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
                "cloudwatch:GetMetricData",
                "rds:DescribeEvents",
                "rds:DescribeDBInstances"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
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
                "rds:DescribeEvents",
                "rds:DescribeDBInstances",
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Role-Based Authorization
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

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

Set the macros: `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, `{$AWS.RDS.INSTANCE.ID}`.

For more information about managing access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

Also, see the Macros section for a list of macros used for LLD filters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.REGION}|<p>Amazon RDS Region code.</p>|`us-west-1`|
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$AWS.RDS.INSTANCE.ID}|<p>RDS DB Instance identifier.</p>||
|{$AWS.RDS.LLD.FILTER.ALARM_SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.RDS.LLD.FILTER.ALARM_SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.RDS.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by name.</p>|`.*`|
|{$AWS.RDS.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.RDS.LLD.FILTER.EVENT_CATEGORY.MATCHES}|<p>Filter of discoverable events by category.</p>|`.*`|
|{$AWS.RDS.LLD.FILTER.EVENT_CATEGORY.NOT_MATCHES}|<p>Filter to exclude discovered events by category.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.RDS.LLD.FILTER.EVENT_SOURCE_TYPE.MATCHES}|<p>Filter of discoverable events by source type.</p>|`.*`|
|{$AWS.RDS.LLD.FILTER.EVENT_SOURCE_TYPE.NOT_MATCHES}|<p>Filter to exclude discovered events by source type.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.RDS.CPU.UTIL.WARN.MAX}|<p>The warning threshold of the CPU utilization expressed in %.</p>|`85`|
|{$AWS.RDS.CPU.CREDIT.BALANCE.MIN.WARN}|<p>Minimum number of free earned CPU credits for trigger expression.</p>|`50`|
|{$AWS.EBS.IO.CREDIT.BALANCE.MIN.WARN}|<p>Minimum percentage of I/O credits remaining for trigger expression.</p>|`20`|
|{$AWS.EBS.BYTE.CREDIT.BALANCE.MIN.WARN}|<p>Minimum percentage of Byte credits remaining for trigger expression.</p>|`20`|
|{$AWS.RDS.BURST.CREDIT.BALANCE.MIN.WARN}|<p>Minimum percentage of Byte credits remaining for trigger expression.</p>|`20`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get metrics data|<p>Get instance metrics.</p><p>Full metrics list related to RDS: https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/rds-metrics.html</p><p>Full metrics list related to Amazon Aurora: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/Aurora.AuroraMySQL.Monitoring.Metrics.html#Aurora.AuroraMySQL.Monitoring.Metrics.instances</p>|Script|aws.rds.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get instance info|<p>Get instance info.</p><p>DescribeDBInstances API method: https://docs.aws.amazon.com/AmazonRDS/latest/APIReference/API_DescribeDBInstances.html</p>|Script|aws.rds.get_instance_info<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get instance alarms data|<p>DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.rds.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get instance events data|<p>DescribeEvents API method: https://docs.aws.amazon.com/AmazonRDS/latest/APIReference/API_DescribeEvents.html</p>|Script|aws.rds.get_events<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get metrics check|<p>Data collection check.</p>|Dependent item|aws.rds.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get instance info check|<p>Data collection check.</p>|Dependent item|aws.rds.instance_info.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get alarms check|<p>Data collection check.</p>|Dependent item|aws.rds.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get events check|<p>Data collection check.</p>|Dependent item|aws.rds.events.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Class|<p>Contains the name of the compute and memory capacity class of the DB instance.</p>|Dependent item|aws.rds.class<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].DBInstanceClass.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Engine|<p>Database engine.</p>|Dependent item|aws.rds.engine<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..Engine.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Engine version|<p>Indicates the database engine version.</p>|Dependent item|aws.rds.engine.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].EngineVersion.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Status|<p>Specifies the current state of this database.</p><p>All possible status values and their description: https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/accessing-monitoring.html#Overview.DBInstance.Status</p>|Dependent item|aws.rds.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..DBInstanceStatus.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage type|<p>Specifies the storage type associated with DB instance.</p>|Dependent item|aws.rds.storage_type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].StorageType.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Create time|<p>Provides the date and time the DB instance was created.</p>|Dependent item|aws.rds.create_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..InstanceCreateTime.first()`</p></li></ul>|
|Storage: Allocated|<p>Specifies the allocated storage size specified in gibibytes (GiB).</p>|Dependent item|aws.rds.storage.allocated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].AllocatedStorage.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage: Max allocated|<p>The upper limit in gibibytes (GiB) to which Amazon RDS can automatically scale the storage of the DB instance.</p><p>If limit is not specified returns -1.</p>|Dependent item|aws.rds.storage.max_allocated<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Read replica: State|<p>The status of a read replica. If the instance isn't a read replica, this is blank.</p><p>Boolean value that is true if the instance is operating normally, or false if the instance is in an error state.</p>|Dependent item|aws.rds.read_replica_state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..StatusInfos..Normal.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Read replica: Status|<p>The status of a read replica. If the instance isn't a read replica, this is blank.</p><p>Status of the DB instance. For a StatusType of read replica, the values can be replicating, replication stop point set, replication stop point reached, error, stopped, or terminated.</p>|Dependent item|aws.rds.read_replica_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..StatusInfos..Status.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Swap usage|<p>The amount of swap space used.</p><p>This metric is available for the Aurora PostgreSQL DB instance classes db.t3.medium, db.t3.large, db.r4.large, db.r4.xlarge, db.r5.large, db.r5.xlarge, db.r6g.large, and db.r6g.xlarge.</p><p>For Aurora MySQL, this metric applies only to db.t* DB instance classes.</p><p>This metric is not available for SQL Server.</p>|Dependent item|aws.rds.swap_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "SwapUsage")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Write IOPS|<p>The number of write records generated per second. This is more or less the number of log records generated by the database. These do not correspond to 8K page writes, and do not correspond to network packets sent.</p>|Dependent item|aws.rds.write_iops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "WriteIOPS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Write latency|<p>The average amount of time taken per disk I/O operation.</p>|Dependent item|aws.rds.write_latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "WriteLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Write throughput|<p>The average number of bytes written to persistent storage every second.</p>|Dependent item|aws.rds.write_throughput.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "WriteThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Receive throughput|<p>The incoming (Receive) network traffic on the DB instance, including both customer database traffic and Amazon RDS traffic used for monitoring and replication.</p>|Dependent item|aws.rds.network_receive_throughput.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Burst balance|<p>The percent of General Purpose SSD (gp2) burst-bucket I/O credits available.</p>|Dependent item|aws.rds.burst_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "BurstBalance")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU: Utilization|<p>The percentage of CPU utilization.</p>|Dependent item|aws.rds.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUUtilization")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Credit CPU: Balance|<p>The number of CPU credits that an instance has accumulated, reported at 5-minute intervals.</p><p>You can use this metric to determine how long a DB instance can burst beyond its baseline performance level at a given rate.</p><p>When an instance is running, credits in the CPUCreditBalance don't expire. When the instance stops, the CPUCreditBalance does not persist, and all accrued credits are lost.</p><p></p><p>This metric applies only to db.t2.small and db.t2.medium instances for Aurora MySQL, and to db.t3 instances for Aurora PostgreSQL.</p>|Dependent item|aws.rds.cpu.credit_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUCreditBalance")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Credit CPU: Usage|<p>The number of CPU credits consumed during the specified period, reported at 5-minute intervals.</p><p>This metric measures the amount of time during which physical CPUs have been used for processing instructions by virtual CPUs allocated to the DB instance.</p><p></p><p>This metric applies only to db.t2.small and db.t2.medium instances for Aurora MySQL, and to db.t3 instances for Aurora PostgreSQL</p>|Dependent item|aws.rds.cpu.credit_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUCreditUsage")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Connections|<p>The number of client network connections to the database instance.</p><p>The number of database sessions can be higher than the metric value because the metric value doesn't include the following:</p><p></p><p>- Sessions that no longer have a network connection but which the database hasn't cleaned up</p><p>- Sessions created by the database engine for its own purposes</p><p>- Sessions created by the database engine's parallel execution capabilities</p><p>- Sessions created by the database engine job scheduler</p><p>- Amazon Aurora/RDS connections</p>|Dependent item|aws.rds.database_connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Queue depth|<p>The number of outstanding read/write requests waiting to access the disk.</p>|Dependent item|aws.rds.disk_queue_depth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DiskQueueDepth")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|EBS: Byte balance|<p>The percentage of throughput credits remaining in the burst bucket of your RDS database. This metric is available for basic monitoring only.</p><p>To find the instance sizes that support this metric, see the instance sizes with an asterisk (*) in the EBS optimized by default table (https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ebs-optimized.html#current) in Amazon RDS User Guide for Linux Instances.</p>|Dependent item|aws.rds.ebs_byte_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSByteBalance%")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|EBS: IO balance|<p>The percentage of I/O credits remaining in the burst bucket of your RDS database. This metric is available for basic monitoring only.</p><p>To find the instance sizes that support this metric, see the instance sizes with an asterisk (*) in the EBS optimized by default table (https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ebs-optimized.html#current) in Amazon RDS User Guide for Linux Instances.</p>|Dependent item|aws.rds.ebs_io_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSIOBalance%")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory, freeable|<p>The amount of available random access memory.</p><p></p><p>For MariaDB, MySQL, Oracle, and PostgreSQL DB instances, this metric reports the value of the MemAvailable field of /proc/meminfo.</p>|Dependent item|aws.rds.freeable_memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "FreeableMemory")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage: Local free|<p>The amount of local storage available, in bytes.</p><p></p><p>Unlike for other DB engines, for Aurora DB instances this metric reports the amount of storage available to each DB instance.</p><p>This value depends on the DB instance class. You can increase the amount of free storage space for an instance by choosing a larger DB instance class for your instance.</p><p>(This doesn't apply to Aurora Serverless v2.)</p>|Dependent item|aws.rds.free_local_storage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "FreeLocalStorage")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Receive throughput|<p>The incoming (receive) network traffic on the DB instance, including both customer database traffic and Amazon RDS traffic used for monitoring and replication.</p><p>For Amazon Aurora: The amount of network throughput received from the Aurora storage subsystem by each instance in the DB cluster.</p>|Dependent item|aws.rds.storage_network_receive_throughput<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Transmit throughput|<p>The outgoing (transmit) network traffic on the DB instance, including both customer database traffic and Amazon RDS traffic used for monitoring and replication.</p><p>For Amazon Aurora: The amount of network throughput sent to the Aurora storage subsystem by each instance in the Aurora MySQL DB cluster.</p>|Dependent item|aws.rds.storage_network_transmit_throughput<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Read IOPS|<p>The average number of disk I/O operations per second. Aurora PostgreSQL-Compatible Edition reports read and write IOPS separately, in 1-minute intervals.</p>|Dependent item|aws.rds.read_iops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ReadIOPS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Read latency|<p>The average amount of time taken per disk I/O operation.</p>|Dependent item|aws.rds.read_latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ReadLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Read throughput|<p>The average number of bytes read from disk per second.</p>|Dependent item|aws.rds.read_throughput.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ReadThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Transmit throughput|<p>The outgoing (Transmit) network traffic on the DB instance, including both customer database traffic and Amazon RDS traffic used for monitoring and replication.</p>|Dependent item|aws.rds.network_transmit_throughput.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Throughput|<p>The amount of network throughput both received from and transmitted to clients by each instance in the Aurora MySQL DB cluster, in bytes per second. This throughput doesn't include network traffic between instances in the DB cluster and the cluster volume.</p>|Dependent item|aws.rds.network_throughput.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NetworkThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage: Space free|<p>The amount of available storage space.</p>|Dependent item|aws.rds.free_storage_space<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "FreeStorageSpace")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Read IOPS, local storage|<p>The average number of disk read I/O operations to local storage per second. Only applies to Multi-AZ DB clusters.</p>|Dependent item|aws.rds.read_iops_local_storage.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Read latency, local storage|<p>The average amount of time taken per disk I/O operation for local storage. Only applies to Multi-AZ DB clusters.</p>|Dependent item|aws.rds.read_latency_local_storage<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Read throughput, local storage|<p>The average number of bytes read from disk per second for local storage. Only applies to Multi-AZ DB clusters.</p>|Dependent item|aws.rds.read_throughput_local_storage.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Replication: Lag|<p>The amount of time a read replica DB instance lags behind the source DB instance. Applies to MySQL, MariaDB, Oracle, PostgreSQL, and SQL Server read replicas.</p>|Dependent item|aws.rds.replica_lag<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ReplicaLag")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Write IOPS, local storage|<p>The average number of disk write I/O operations per second on local storage in a Multi-AZ DB cluster.</p>|Dependent item|aws.rds.write_iops_local_storage.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Write latency, local storage|<p>The average amount of time taken per disk I/O operation on local storage in a Multi-AZ DB cluster.</p>|Dependent item|aws.rds.write_latency_local_storage<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Write throughput, local storage|<p>The average number of bytes written to disk per second for local storage.</p>|Dependent item|aws.rds.write_throughput_local_storage.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SQLServer: Failed agent jobs|<p>The number of failed Microsoft SQL Server Agent jobs during the last minute.</p>|Dependent item|aws.rds.failed_sql_server_agent_jobs_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk: Binlog Usage|<p>The amount of disk space occupied by binary logs on the master. Applies to MySQL read replicas.</p>|Dependent item|aws.rds.bin_log_disk_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "BinLogDiskUsage")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS RDS: Failed to get metrics data|<p>Failed to get CloudWatch metrics for RDS.</p>|`length(last(/AWS RDS instance by HTTP/aws.rds.metrics.check))>0`|Warning||
|AWS RDS: Failed to get instance data|<p>Failed to get CloudWatch instance info for RDS.</p>|`length(last(/AWS RDS instance by HTTP/aws.rds.instance_info.check))>0`|Warning||
|AWS RDS: Failed to get alarms data|<p>Failed to get CloudWatch alarms for RDS.</p>|`length(last(/AWS RDS instance by HTTP/aws.rds.alarms.check))>0`|Warning||
|AWS RDS: Failed to get events data|<p>Failed to get CloudWatch events for RDS.</p>|`length(last(/AWS RDS instance by HTTP/aws.rds.events.check))>0`|Warning||
|AWS RDS: Read replica in error state|<p>The status of a read replica.<br>False if the instance is in an error state.</p>|`last(/AWS RDS instance by HTTP/aws.rds.read_replica_state)=0`|Average||
|AWS RDS: Burst balance is too low||`max(/AWS RDS instance by HTTP/aws.rds.burst_balance,5m)<{$AWS.RDS.BURST.CREDIT.BALANCE.MIN.WARN}`|Warning||
|AWS RDS: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/AWS RDS instance by HTTP/aws.rds.cpu.utilization,15m)>{$AWS.RDS.CPU.UTIL.WARN.MAX}`|Warning||
|AWS RDS: Instance CPU Credit balance is too low|<p>The number of earned CPU credits has been less than {$AWS.RDS.CPU.CREDIT.BALANCE.MIN.WARN} in the last 5 minutes.</p>|`max(/AWS RDS instance by HTTP/aws.rds.cpu.credit_balance,5m)<{$AWS.RDS.CPU.CREDIT.BALANCE.MIN.WARN}`|Warning||
|AWS RDS: Byte Credit balance is too low||`max(/AWS RDS instance by HTTP/aws.rds.ebs_byte_balance,5m)<{$AWS.EBS.BYTE.CREDIT.BALANCE.MIN.WARN}`|Warning||
|AWS RDS: I/O Credit balance is too low||`max(/AWS RDS instance by HTTP/aws.rds.ebs_io_balance,5m)<{$AWS.EBS.IO.CREDIT.BALANCE.MIN.WARN}`|Warning||

### LLD rule Instance Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Instance Alarms discovery|<p>Discovery instance alarms.</p>|Dependent item|aws.rds.alarms.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Instance Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#ALARM_NAME}]: State reason|<p>An explanation for the alarm state, in text format.</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.rds.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].StateReason.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#ALARM_NAME}]: State|<p>The state value for the alarm. Possible values: 0 (OK), 1 (INSUFFICIENT_DATA), 2 (ALARM).</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.rds.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].StateValue.first()`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Instance Alarms discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS RDS: [{#ALARM_NAME}] has 'Alarm' state|<p>Alarm "{#ALARM_NAME}" has 'Alarm' state.<br>Reason: {ITEM.LASTVALUE2}</p>|`last(/AWS RDS instance by HTTP/aws.rds.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS RDS instance by HTTP/aws.rds.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS RDS: [{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS RDS instance by HTTP/aws.rds.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Aurora metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Aurora metrics discovery|<p>Discovery Amazon Aurora metrics.</p><p>https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/Aurora.AuroraMySQL.Monitoring.Metrics.html#Aurora.AuroraMySQL.Monitoring.Metrics.instances</p>|Dependent item|aws.rds.aurora.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Aurora metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Row lock time|<p>The total time spent acquiring row locks for InnoDB tables.</p>|Dependent item|aws.rds.row_locktime[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "RowLockTime")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Operations: Select throughput|<p>The average number of select queries per second.</p>|Dependent item|aws.rds.select_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "SelectThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Operations: Select latency|<p>The amount of latency for select queries.</p>|Dependent item|aws.rds.select_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "SelectLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Replication: Lag, max|<p>The maximum amount of lag between the primary instance and each Aurora DB instance in the DB cluster.</p>|Dependent item|aws.rds.aurora_replica_lag.max[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Replication: Lag, min|<p>The minimum amount of lag between the primary instance and each Aurora DB instance in the DB cluster.</p>|Dependent item|aws.rds.aurora_replica_lag.min[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Replication: Lag|<p>For an Aurora replica, the amount of lag when replicating updates from the primary instance.</p>|Dependent item|aws.rds.aurora_replica_lag[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "AuroraReplicaLag")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Buffer Cache hit ratio|<p>The percentage of requests that are served by the buffer cache.</p>|Dependent item|aws.rds.buffer_cache_hit_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Operations: Commit latency|<p>The amount of latency for commit operations.</p>|Dependent item|aws.rds.commit_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CommitLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Operations: Commit throughput|<p>The average number of commit operations per second.</p>|Dependent item|aws.rds.commit_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CommitThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deadlocks, rate|<p>The average number of deadlocks in the database per second.</p>|Dependent item|aws.rds.deadlocks.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "Deadlocks")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Engine uptime|<p>The amount of time that the instance has been running.</p>|Dependent item|aws.rds.engine_uptime[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EngineUptime")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Rollback segment history list length|<p>The undo logs that record committed transactions with delete-marked records. These records are scheduled to be processed by the InnoDB purge operation.</p>|Dependent item|aws.rds.rollback_segment_history_list_length[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Throughput|<p>The amount of network throughput received from and sent to the Aurora storage subsystem by each instance in the Aurora MySQL DB cluster.</p>|Dependent item|aws.rds.storage_network_throughput[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Aurora MySQL metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Aurora MySQL metrics discovery|<p>Discovery Aurora MySQL metrics.</p><p>Storage types:</p><p> aurora (for MySQL 5.6-compatible Aurora)</p><p> aurora-mysql (for MySQL 5.7-compatible and MySQL 8.0-compatible Aurora)</p>|Dependent item|aws.rds.postgresql.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Aurora MySQL metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Operations: Delete latency|<p>The amount of latency for delete queries.</p>|Dependent item|aws.rds.delete_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DeleteLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Operations: Delete throughput|<p>The average number of delete queries per second.</p>|Dependent item|aws.rds.delete_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DeleteThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DML: Latency|<p>The amount of latency for inserts, updates, and deletes.</p>|Dependent item|aws.rds.dml_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DMLLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DML: Throughput|<p>The average number of inserts, updates, and deletes per second.</p>|Dependent item|aws.rds.dml_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DMLThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DDL: Latency|<p>The amount of latency for data definition language (DDL) requests - for example, create, alter, and drop requests.</p>|Dependent item|aws.rds.ddl_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DDLLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DDL: Throughput|<p>The average number of DDL requests per second.</p>|Dependent item|aws.rds.ddl_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DDLThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Backtrack: Window, actual|<p>The difference between the target backtrack window and the actual backtrack window.</p>|Dependent item|aws.rds.backtrack_window_actual[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Backtrack: Window, alert|<p>The number of times that the actual backtrack window is smaller than the target backtrack window for a given period of time.</p>|Dependent item|aws.rds.backtrack_window_alert[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Transactions: Blocked, rate|<p>The average number of transactions in the database that are blocked per second.</p>|Dependent item|aws.rds.blocked_transactions.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Replication: Binlog lag|<p>The amount of time that a binary log replica DB cluster running on Aurora MySQL-Compatible Edition lags behind the binary log replication source.</p><p>A lag means that the source is generating records faster than the replica can apply them.</p><p>The metric value indicates the following:</p><p></p><p>A high value: The replica is lagging the replication source.</p><p>0 or a value close to 0: The replica process is active and current.</p><p>-1: Aurora can't determine the lag, which can happen during replica setup or when the replica is in an error state</p>|Dependent item|aws.rds.aurora_replication_binlog_lag[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Transactions: Active, rate|<p>The average number of current transactions executing on an Aurora database instance per second.</p><p>By default, Aurora doesn't enable this metric. To begin measuring this value, set innodb_monitor_enable='all' in the DB parameter group for a specific DB instance.</p>|Dependent item|aws.rds.aurora_transactions_active.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Connections: Aborted|<p>The number of client connections that have not been closed properly.</p>|Dependent item|aws.rds.aurora_clients_aborted[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "AbortedClients")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Operations: Insert latency|<p>The amount of latency for insert queries, in milliseconds.</p>|Dependent item|aws.rds.insert_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "InsertLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Operations: Insert throughput|<p>The average number of insert queries per second.</p>|Dependent item|aws.rds.insert_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "InsertThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Login failures, rate|<p>The average number of failed login attempts per second.</p>|Dependent item|aws.rds.login_failures.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "LoginFailures")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Queries, rate|<p>The average number of queries executed per second.</p>|Dependent item|aws.rds.queries.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "Queries")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Resultset cache hit ratio|<p>The percentage of requests that are served by the Resultset cache.</p>|Dependent item|aws.rds.result_set_cache_hit_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Binary log files, number|<p>The number of binlog files generated.</p>|Dependent item|aws.rds.num_binary_log_files[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NumBinaryLogFiles")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Binary log files, size|<p>The total size of the binlog files.</p>|Dependent item|aws.rds.sum_binary_log_files[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "SumBinaryLogSize")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Operations: Update latency|<p>The amount of latency for update queries.</p>|Dependent item|aws.rds.update_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "UpdateLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Operations: Update throughput|<p>The average number of update queries per second.</p>|Dependent item|aws.rds.update_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "UpdateThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Instance Events discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Instance Events discovery|<p>Discovery instance events.</p>|Dependent item|aws.rds.events.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Instance Events discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#EVENT_CATEGORY}]: {#EVENT_SOURCE_TYPE}/{#EVENT_SOURCE_ID}: Message|<p>Provides the text of this event.</p>|Dependent item|aws.rds.event_message["{#EVENT_CATEGORY}/{#EVENT_SOURCE_TYPE}/{#EVENT_SOURCE_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$[-1]`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#EVENT_CATEGORY}]: {#EVENT_SOURCE_TYPE}/{#EVENT_SOURCE_ID} : Date|<p>Provides the text of this event.</p>|Dependent item|aws.rds.event_date["{#EVENT_CATEGORY}/{#EVENT_SOURCE_TYPE}/{#EVENT_SOURCE_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$[-1]`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

# AWS S3 bucket by HTTP

## Overview

The template to monitor AWS S3 bucket by HTTP via Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

**Note:** This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the [CloudWatch pricing](https://aws.amazon.com/cloudwatch/pricing/) page.

Additional information about metrics and used API methods:

* [Full metrics list related to S3](https://docs.aws.amazon.com/AmazonS3/latest/userguide/metrics-dimensions.html)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS S3 bucket by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS S3 metrics and uses the script item to make HTTP requests to the CloudWatch API.
Before using the template, you need to create an IAM policy for the Zabbix role in your AWS account with the necessary permissions.

### Required Permissions
Add the following required permissions to your Zabbix IAM policy in order to collect Amazon S3 metrics.

```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
              "cloudwatch:DescribeAlarms",
              "cloudwatch:GetMetricData",
              "s3:GetMetricsConfiguration"
          ],
          "Effect":"Allow",
          "Resource":"*"
        }
    ]
  }
```

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume role authorization
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
                "cloudwatch:GetMetricData",
                "s3:GetMetricsConfiguration"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
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
                "s3:GetMetricsConfiguration",
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Role-Based Authorization
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

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

To gather Request metrics, [enable Requests metrics](https://docs.aws.amazon.com/AmazonS3/latest/userguide/cloudwatch-monitoring.html) on your Amazon S3 buckets from the AWS console.

You can also define a filter for the Request metrics using a shared prefix, object tag, or access point.

Set the macros: `{$AWS.AUTH_TYPE}`, `{$AWS.S3.BUCKET.NAME}`.

For more information about managing access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

Also, see the Macros section for a list of macros used for LLD filters.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.REQUEST.REGION}|<p>Region used in GET request `ListBuckets`.</p>|`us-east-1`|
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$AWS.S3.BUCKET.NAME}|<p>S3 bucket name.</p>||
|{$AWS.S3.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by name.</p>|`.*`|
|{$AWS.S3.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.S3.LLD.FILTER.ID.NAME.MATCHES}|<p>Filter of discoverable request metrics by filter ID name.</p>|`.*`|
|{$AWS.S3.LLD.FILTER.ID.NAME.NOT_MATCHES}|<p>Filter to exclude discovered request metrics by filter ID name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.S3.UPDATE.INTERVAL}|<p>Interval in seconds for getting request metrics. Used in the metric configuration and in the JavaScript API query. Must be between 1 and 86400 seconds.</p>|`1800`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get metrics data|<p>Get bucket metrics.</p><p>Full metrics list related to S3: https://docs.aws.amazon.com/AmazonS3/latest/userguide/metrics-dimensions.html</p>|Script|aws.s3.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get alarms data|<p>Get alarms data.</p><p>DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.s3.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get metrics check|<p>Data collection check.</p>|Dependent item|aws.s3.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get alarms check|<p>Data collection check.</p>|Dependent item|aws.s3.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Bucket Size|<p>This is a daily metric for the bucket.</p><p>The amount of data in bytes stored in a bucket in the STANDARD storage class, INTELLIGENT_TIERING storage class, Standard-Infrequent Access (STANDARD_IA) storage class, OneZone-Infrequent Access (ONEZONE_IA), Reduced Redundancy Storage (RRS) class, S3 Glacier Instant Retrieval storage class, Deep Archive Storage (S3 Glacier Deep Archive) class, or S3 Glacier Flexible Retrieval (GLACIER) storage class.</p><p>This value is calculated by summing the size of all objects and metadata in the bucket (both current and noncurrent objects), including the size of all parts for all incomplete multipart uploads to the bucket.</p>|Dependent item|aws.s3.bucket_size_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Number of objects|<p>This is a daily metric for the bucket.</p><p>The total number of objects stored in a bucket for all storage classes.</p><p>This value is calculated by counting all objects in the bucket (both current and noncurrent objects) and the total number of parts for all incomplete multipart uploads to the bucket.</p>|Dependent item|aws.s3.number_of_objects<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS S3: Failed to get metrics data|<p>Failed to get CloudWatch metrics for S3 bucket.</p>|`length(last(/AWS S3 bucket by HTTP/aws.s3.metrics.check))>0`|Warning||
|AWS S3: Failed to get alarms data|<p>Failed to get CloudWatch alarms for S3 bucket.</p>|`length(last(/AWS S3 bucket by HTTP/aws.s3.alarms.check))>0`|Warning||

### LLD rule Bucket Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Bucket Alarms discovery|<p>Discovery of bucket alarms.</p>|Dependent item|aws.s3.alarms.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Bucket Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#ALARM_NAME}]: State reason|<p>An explanation for the alarm state, in text format.</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.s3.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].StateReason.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#ALARM_NAME}]: State|<p>The state value for the alarm. Possible values: 0 (OK), 1 (INSUFFICIENT_DATA), 2 (ALARM).</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.s3.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].StateValue.first()`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Bucket Alarms discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS S3: [{#ALARM_NAME}] has 'Alarm' state|<p>Alarm "{#ALARM_NAME}" has 'Alarm' state.<br>Reason: {ITEM.LASTVALUE2}</p>|`last(/AWS S3 bucket by HTTP/aws.s3.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS S3 bucket by HTTP/aws.s3.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS S3: [{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS S3 bucket by HTTP/aws.s3.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Request Metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Request Metrics discovery|<p>Discovery of request metrics.</p>|Dependent item|aws.s3.configuration.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.filter_id`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Request Metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Get request metrics|<p>Get bucket request metrics filter: '{#AWS.S3.FILTER.ID.NAME}'.</p><p>Full metrics list related to S3: https://docs.aws.amazon.com/AmazonS3/latest/userguide/metrics-dimensions.html</p>|Script|aws.s3.get_metrics["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: All|<p>The total number of HTTP requests made to an Amazon S3 bucket, regardless of type.</p><p>If you're using a metrics configuration with a filter, then this metric only returns the HTTP requests that meet the filter's requirements.</p>|Dependent item|aws.s3.all_requests["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "AllRequests")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Get|<p>The number of HTTP GET requests made for objects in an Amazon S3 bucket. This doesn't include list operations.</p><p>Paginated list-oriented requests, like List Multipart Uploads, List Parts, Get Bucket Object versions, and others, are not included in this metric.</p>|Dependent item|aws.s3.get_requests["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "GetRequests")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Put|<p>The number of HTTP PUT requests made for objects in an Amazon S3 bucket.</p>|Dependent item|aws.s3.put_requests["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "PutRequests")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Delete|<p>The number of HTTP DELETE requests made for objects in an Amazon S3 bucket.</p><p>This also includes Delete Multiple Objects requests. This metric shows the number of requests, not the number of objects deleted.</p>|Dependent item|aws.s3.delete_requests["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DeleteRequests")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Head|<p>The number of HTTP HEAD requests made to an Amazon S3 bucket.</p>|Dependent item|aws.s3.head_requests["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "HeadRequests")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Post|<p>The number of HTTP POST requests made to an Amazon S3 bucket.</p><p>Delete Multiple Objects and SELECT Object Content requests are not included in this metric.</p>|Dependent item|aws.s3.post_requests["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "PostRequests")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Select|<p>The number of Amazon S3 SELECT Object Content requests made for objects in an Amazon S3 bucket.</p>|Dependent item|aws.s3.select_requests["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "SelectRequests")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Select, bytes scanned|<p>The number of bytes of data scanned with Amazon S3 SELECT Object Content requests in an Amazon S3 bucket.</p><p>Statistic: Average (bytes per request).</p>|Dependent item|aws.s3.select_bytes_scanned["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Select, bytes returned|<p>The number of bytes of data returned with Amazon S3 SELECT Object Content requests in an Amazon S3 bucket.</p><p>Statistic: Average (bytes per request).</p>|Dependent item|aws.s3.select_bytes_returned["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: List|<p>The number of HTTP requests that list the contents of a bucket.</p>|Dependent item|aws.s3.list_requests["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ListRequests")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Bytes downloaded|<p>The number of bytes downloaded for requests made to an Amazon S3 bucket, where the response includes a body.</p><p>Statistic: Average (bytes per request).</p>|Dependent item|aws.s3.bytes_downloaded["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "BytesDownloaded")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Bytes uploaded|<p>The number of bytes uploaded that contain a request body, made to an Amazon S3 bucket.</p><p>Statistic: Average (bytes per request).</p>|Dependent item|aws.s3.bytes_uploaded["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "BytesUploaded")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Errors, 4xx|<p>The number of HTTP 4xx client error status code requests made to an Amazon S3 bucket with a value of either 0 or 1.</p><p>The average statistic shows the error rate, and the sum statistic shows the count of that type of error, during each period.</p><p>Statistic: Average (reports per request).</p>|Dependent item|aws.s3.4xx_errors["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "4xxErrors")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Requests: Errors, 5xx|<p>The number of HTTP 5xx server error status code requests made to an Amazon S3 bucket with a value of either 0 or 1.</p><p>The average statistic shows the error rate, and the sum statistic shows the count of that type of error, during each period.</p><p>Statistic: Average (reports per request).</p>|Dependent item|aws.s3.5xx_errors["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "5xxErrors")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: First byte latency, avg|<p>The per-request time from the complete request being received by an Amazon S3 bucket to when the response starts to be returned.</p><p>Statistic: Average.</p>|Dependent item|aws.s3.first_byte_latency.avg["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "FirstByteLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: First byte latency, p90|<p>The per-request time from the complete request being received by an Amazon S3 bucket to when the response starts to be returned.</p><p>Statistic: 90th percentile.</p>|Dependent item|aws.s3.first_byte_latency.p90["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "FirstByteLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Total request latency, avg|<p>The elapsed per-request time from the first byte received to the last byte sent to an Amazon S3 bucket.</p><p>This includes the time taken to receive the request body and send the response body, which is not included in FirstByteLatency.</p><p>Statistic: Average.</p>|Dependent item|aws.s3.total_request_latency.avg["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Total request latency, p90|<p>The elapsed per-request time from the first byte received to the last byte sent to an Amazon S3 bucket.</p><p>This includes the time taken to receive the request body and send the response body, which is not included in FirstByteLatency.</p><p>Statistic: 90th percentile.</p>|Dependent item|aws.s3.total_request_latency.p90["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Replication: Latency|<p>The maximum number of seconds by which the replication destination region is behind the source Region for a given replication rule.</p>|Dependent item|aws.s3.replication_latency["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Replication: Bytes pending|<p>The total number of bytes of objects pending replication for a given replication rule.</p>|Dependent item|aws.s3.bytes_pending_replication["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filter [{#AWS.S3.FILTER.ID.NAME}]: Replication: Operations pending|<p>The number of operations pending replication for a given replication rule.</p>|Dependent item|aws.s3.operations_pending_replication["{#AWS.S3.FILTER.ID.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

# AWS ECS Serverless Cluster by HTTP

## Overview

The template to monitor AWS ECS Serverless Cluster by HTTP via Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

**Note:** This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the [CloudWatch pricing](https://aws.amazon.com/cloudwatch/pricing/) page.

Additional information about the metrics and used API methods:

* [Full metrics list related to ECS](https://docs.aws.amazon.com/AmazonCloudWatch/latest/monitoring/Container-Insights-metrics-ECS.html)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS ECS Cluster by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS ECS metrics and uses the script item to make HTTP requests to the CloudWatch API.
Before using the template, you need to create an IAM policy for the Zabbix role in your AWS account with the necessary permissions.

### Required Permissions
Add the following required permissions to your Zabbix IAM policy in order to collect Amazon ECS metrics.

```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
              "cloudwatch:DescribeAlarms",
              "cloudwatch:GetMetricData",
              "ecs:ListServices"
          ],
          "Effect":"Allow",
          "Resource":"*"
        }
    ]
  }
```

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume role authorization
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
                "cloudwatch:GetMetricData",
                "ecs:ListServices"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
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
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation"
            ],
            "Resource": "*"
        }
    ]
}
```
#### Trust Relationships for Role-Based Authorization
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

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

Set the following macros `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, `{$AWS.ECS.CLUSTER.NAME}`.

For more information about managing access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

Refer to the Macros section for a list of macros used for LLD filters.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.REGION}|<p>Amazon ECS Region code.</p>|`us-west-1`|
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$AWS.ECS.CLUSTER.NAME}|<p>ECS cluster name.</p>||
|{$AWS.ECS.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by name.</p>|`.*`|
|{$AWS.ECS.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.LLD.FILTER.ALARM_SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.ECS.LLD.FILTER.ALARM_SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.LLD.FILTER.SERVICE.MATCHES}|<p>Filter of discoverable services by name.</p>|`.*`|
|{$AWS.ECS.LLD.FILTER.SERVICE.NOT_MATCHES}|<p>Filter to exclude discovered services by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.CLUSTER.CPU.UTIL.WARN}|<p>The warning threshold of the cluster CPU utilization expressed in %.</p>|`70`|
|{$AWS.ECS.CLUSTER.MEMORY.UTIL.WARN}|<p>The warning threshold of the cluster memory utilization expressed in %.</p>|`70`|
|{$AWS.ECS.CLUSTER.SERVICE.CPU.UTIL.WARN}|<p>The warning threshold of the cluster service CPU utilization expressed in %.</p>|`80`|
|{$AWS.ECS.CLUSTER.SERVICE.MEMORY.UTIL.WARN}|<p>The warning threshold of the cluster service memory utilization expressed in %.</p>|`80`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get cluster metrics|<p>Get cluster metrics.</p><p>Full metrics list related to ECS: https://docs.aws.amazon.com/AmazonECS/latest/userguide/metrics-dimensions.html</p>|Script|aws.ecs.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get cluster services|<p>Get cluster services.</p><p>Full metrics list related to ECS: https://docs.aws.amazon.com/AmazonECS/latest/userguide/metrics-dimensions.html</p>|Script|aws.ecs.get_cluster_services<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get alarms data|<p>Get alarms data.</p><p>DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.ecs.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get metrics check|<p>Data collection check.</p>|Dependent item|aws.ecs.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get alarms check|<p>Data collection check.</p>|Dependent item|aws.ecs.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Container Instance Count|<p>The number of EC2 instances running the Amazon ECS agent that are registered with a cluster.</p>|Dependent item|aws.ecs.container_instance_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Task Count|<p>The number of tasks running in the cluster.</p>|Dependent item|aws.ecs.task_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Service Count|<p>The number of services in the cluster.</p>|Dependent item|aws.ecs.service_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU Utilization|<p>Cluster CPU utilization.</p>|Dependent item|aws.ecs.cpu_utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CPUUtilization`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory Utilization|<p>The memory being used by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.</p>|Dependent item|aws.ecs.memory_utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MemoryUtilization`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network rx bytes|<p>The number of bytes received by the resource that is specified by the dimensions that you're using.</p><p>This metric is only available for containers in tasks using the awsvpc or bridge network modes.</p>|Dependent item|aws.ecs.network.rx<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network tx bytes|<p>The number of bytes transmitted by the resource that is specified by the dimensions that you're using.</p><p>This metric is only available for containers in tasks using the awsvpc or bridge network modes.</p>|Dependent item|aws.ecs.network.tx<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Ephemeral Storage Reserved|<p>The number of bytes reserved from ephemeral storage in the resource that is specified by the dimensions that you're using. Ephemeral storage is used for the container root filesystem and any bind mount host volumes defined in the container image and task definition. The amount of ephemeral storage can’t be changed in a running task.</p><p>This metric is only available for tasks that run on Fargate Linux platform version 1.4.0 or later.</p>|Dependent item|aws.ecs.ephemeral.storage.reserved<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1073741824`</p></li></ul>|
|Ephemeral Storage Utilized|<p>The number of bytes used from ephemeral storage in the resource that is specified by the dimensions that you're using. Ephemeral storage is used for the container root filesystem and any bind mount host volumes defined in the container image and task definition. The amount of ephemeral storage can’t be changed in a running task.</p><p>This metric is only available for tasks that run on Fargate Linux platform version 1.4.0 or later.</p>|Dependent item|aws.ecs.ephemeral.storage.utilized<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1073741824`</p></li></ul>|
|Ephemeral Storage Utilization|<p>The calculated Disk Utilization.</p>|Dependent item|aws.ecs.disk.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DiskUtilization`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ECS Serverless: Failed to get metrics data|<p>Failed to get CloudWatch metrics for ECS Cluster.</p>|`length(last(/AWS ECS Serverless Cluster by HTTP/aws.ecs.metrics.check))>0`|Warning||
|AWS ECS Serverless: Failed to get alarms data|<p>Failed to get CloudWatch alarms for ECS Cluster.</p>|`length(last(/AWS ECS Serverless Cluster by HTTP/aws.ecs.alarms.check))>0`|Warning||
|AWS ECS Serverless: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/AWS ECS Serverless Cluster by HTTP/aws.ecs.cpu_utilization,15m)>{$AWS.ECS.CLUSTER.CPU.UTIL.WARN}`|Warning||
|AWS ECS Serverless: High memory utilization|<p>The system is running out of free memory.</p>|`min(/AWS ECS Serverless Cluster by HTTP/aws.ecs.memory_utilization,15m)>{$AWS.ECS.CLUSTER.MEMORY.UTIL.WARN}`|Warning||

### LLD rule Cluster Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster Alarms discovery|<p>Discovery instance alarms.</p>|Dependent item|aws.ecs.alarms.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Cluster Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#ALARM_NAME}]: Get metrics|<p>Get alarm metrics about the state and its reason.</p>|Dependent item|aws.ecs.alarm.get_metrics["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#ALARM_NAME}]: State reason|<p>An explanation for the alarm state, in text format.</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.ecs.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#ALARM_NAME}]: State|<p>The state value for the alarm. Possible values: 0 (OK), 1 (INSUFFICIENT_DATA), 2 (ALARM).</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.ecs.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateValue`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Cluster Alarms discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ECS Serverless: [{#ALARM_NAME}] has 'Alarm' state|<p>Alarm "{#ALARM_NAME}" has 'Alarm' state.<br>Reason: {ITEM.LASTVALUE2}</p>|`last(/AWS ECS Serverless Cluster by HTTP/aws.ecs.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS ECS Serverless Cluster by HTTP/aws.ecs.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS ECS Serverless: [{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS ECS Serverless Cluster by HTTP/aws.ecs.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Cluster Services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster Services discovery|<p>Discovery {$AWS.ECS.CLUSTER.NAME} services.</p>|Dependent item|aws.ecs.services.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Cluster Services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#AWS.ECS.SERVICE.NAME}]: Running Task|<p>The number of tasks currently in the `running` state.</p>|Dependent item|aws.ecs.services.running.task["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Pending Task|<p>The number of tasks currently in the `pending` state.</p>|Dependent item|aws.ecs.services.pending.task["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Desired Task|<p>The desired number of tasks for an {#AWS.ECS.SERVICE.NAME} service.</p>|Dependent item|aws.ecs.services.desired.task["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Task Set|<p>The number of task sets in the {#AWS.ECS.SERVICE.NAME} service.</p>|Dependent item|aws.ecs.services.task.set["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: CPU Reserved|<p>A number of CPU units reserved by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined CPU reservation in their task definition.</p>|Dependent item|aws.ecs.services.cpu_reserved["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: CPU Utilization|<p>A number of CPU units used by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined CPU reservation in their task definition.</p>|Dependent item|aws.ecs.services.cpu.utilization["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Memory utilized|<p>The memory being used by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.</p>|Dependent item|aws.ecs.services.memory_utilized["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Memory utilization|<p>The memory being used by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.</p>|Dependent item|aws.ecs.services.memory.utilization["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Memory reserved|<p>The memory that is reserved by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.</p>|Dependent item|aws.ecs.services.memory_reserved["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Network rx bytes|<p>The number of bytes received by the resource that is specified by the dimensions that you're using.</p><p>This metric is only available for containers in tasks using the awsvpc or bridge network modes.</p>|Dependent item|aws.ecs.services.network.rx["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Network tx bytes|<p>The number of bytes transmitted by the resource that is specified by the dimensions that you're using.</p><p>This metric is only available for containers in tasks using the awsvpc or bridge network modes.</p>|Dependent item|aws.ecs.services.network.tx["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Ephemeral storage reserved|<p>The number of bytes reserved from ephemeral storage in the resource that is specified by the dimensions that you're using. Ephemeral storage is used for the container root filesystem and any bind mount host volumes defined in the container image and task definition. The amount of ephemeral storage can’t be changed in a running task.</p><p>This metric is only available for tasks that run on Fargate Linux platform version 1.4.0 or later.</p>|Dependent item|aws.ecs.services.ephemeral.storage.reserved["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1073741824`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Ephemeral storage utilized|<p>The number of bytes used from ephemeral storage in the resource that is specified by the dimensions that you're using. Ephemeral storage is used for the container root filesystem and any bind mount host volumes defined in the container image and task definition. The amount of ephemeral storage can’t be changed in a running task.</p><p>This metric is only available for tasks that run on Fargate Linux platform version 1.4.0 or later.</p>|Dependent item|aws.ecs.services.ephemeral.storage.utilized["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1073741824`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Storage read bytes|<p>The number of bytes read from storage in the resource that is specified by the dimensions that you're using.</p>|Dependent item|aws.ecs.services.storage.read.bytes["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Storage write bytes|<p>The number of bytes written to storage in the resource that is specified by the dimensions that you're using.</p>|Dependent item|aws.ecs.services.storage.write.bytes["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Get metrics|<p>Get metrics of ESC services.</p><p>Full metrics list related to ECS : https://docs.aws.amazon.com/ecs/index.html</p>|Script|aws.ecs.services.get_metrics["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Cluster Services discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ECS Serverless: [{#AWS.ECS.SERVICE.NAME}]: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/AWS ECS Serverless Cluster by HTTP/aws.ecs.services.cpu.utilization["{#AWS.ECS.SERVICE.NAME}"],15m)>{$AWS.ECS.CLUSTER.SERVICE.CPU.UTIL.WARN}`|Warning||
|AWS ECS Serverless: [{#AWS.ECS.SERVICE.NAME}]: High memory utilization|<p>The system is running out of free memory.</p>|`min(/AWS ECS Serverless Cluster by HTTP/aws.ecs.services.memory.utilization["{#AWS.ECS.SERVICE.NAME}"],15m)>{$AWS.ECS.CLUSTER.SERVICE.MEMORY.UTIL.WARN}`|Warning||

# AWS ECS Cluster by HTTP

## Overview

The template to monitor AWS ECS Cluster by HTTP via Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

**Note:** This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the [CloudWatch pricing](https://aws.amazon.com/cloudwatch/pricing/) page.

Additional information about the metrics and used API methods:

* [Full metrics list related to ECS](https://docs.aws.amazon.com/AmazonCloudWatch/latest/monitoring/Container-Insights-metrics-ECS.html)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS ECS Cluster by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS ECS metrics and uses the script item to make HTTP requests to the CloudWatch API.
Before using the template, you need to create an IAM policy for the Zabbix role in your AWS account with the necessary permissions.

### Required Permissions
Add the following required permissions to your Zabbix IAM policy in order to collect Amazon ECS metrics.

```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
              "cloudwatch:DescribeAlarms",
              "cloudwatch:GetMetricData",
              "ecs:ListServices"
          ],
          "Effect":"Allow",
          "Resource":"*"
        }
    ]
  }
```

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume role authorization
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
                "cloudwatch:GetMetricData",
                "ecs:ListServices"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
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
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Role-Based Authorization
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

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

Set the following macros `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, `{$AWS.ECS.CLUSTER.NAME}`.

For more information about managing access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

Refer to the Macros section for a list of macros used for LLD filters.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.REGION}|<p>Amazon ECS Region code.</p>|`us-west-1`|
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$AWS.ECS.CLUSTER.NAME}|<p>ECS cluster name.</p>||
|{$AWS.ECS.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by name.</p>|`.*`|
|{$AWS.ECS.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.LLD.FILTER.ALARM_SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.ECS.LLD.FILTER.ALARM_SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.LLD.FILTER.SERVICE.MATCHES}|<p>Filter of discoverable services by name.</p>|`.*`|
|{$AWS.ECS.LLD.FILTER.SERVICE.NOT_MATCHES}|<p>Filter to exclude discovered services by name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.ECS.CLUSTER.CPU.UTIL.WARN}|<p>The warning threshold of the cluster CPU utilization expressed in %.</p>|`70`|
|{$AWS.ECS.CLUSTER.MEMORY.UTIL.WARN}|<p>The warning threshold of the cluster memory utilization expressed in %.</p>|`70`|
|{$AWS.ECS.CLUSTER.SERVICE.CPU.UTIL.WARN}|<p>The warning threshold of the cluster service CPU utilization expressed in %.</p>|`80`|
|{$AWS.ECS.CLUSTER.SERVICE.MEMORY.UTIL.WARN}|<p>The warning threshold of the cluster service memory utilization expressed in %.</p>|`80`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get cluster metrics|<p>Get cluster metrics.</p><p>Full metrics list related to ECS: https://docs.aws.amazon.com/AmazonECS/latest/userguide/metrics-dimensions.html</p>|Script|aws.ecs.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get cluster services|<p>Get cluster services.</p><p>Full metrics list related to ECS: https://docs.aws.amazon.com/AmazonECS/latest/userguide/metrics-dimensions.html</p>|Script|aws.ecs.get_cluster_services<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get alarms data|<p>Get alarms data.</p><p>DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.ecs.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get metrics check|<p>Data collection check.</p>|Dependent item|aws.ecs.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get alarms check|<p>Data collection check.</p>|Dependent item|aws.ecs.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Container Instance Count|<p>The number of EC2 instances running the Amazon ECS agent that are registered with a cluster.</p>|Dependent item|aws.ecs.container_instance_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Task Count|<p>The number of tasks running in the cluster.</p>|Dependent item|aws.ecs.task_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Service Count|<p>The number of services in the cluster.</p>|Dependent item|aws.ecs.service_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU Reserved|<p>A number of CPU units reserved by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined CPU reservation in their task definition.</p>|Dependent item|aws.ecs.cpu_reserved<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CpuReserved")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU Utilization|<p>Cluster CPU utilization</p>|Dependent item|aws.ecs.cpu_utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CPUUtilization`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory Utilization|<p>The memory being used by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.</p>|Dependent item|aws.ecs.memory_utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MemoryUtilization`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network rx bytes|<p>The number of bytes received by the resource that is specified by the dimensions that you're using.</p><p>This metric is only available for containers in tasks using the awsvpc or bridge network modes.</p>|Dependent item|aws.ecs.network.rx<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network tx bytes|<p>The number of bytes transmitted by the resource that is specified by the dimensions that you're using.</p><p>This metric is only available for containers in tasks using the awsvpc or bridge network modes.</p>|Dependent item|aws.ecs.network.tx<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ECS Cluster: Failed to get metrics data|<p>Failed to get CloudWatch metrics for ECS Cluster.</p>|`length(last(/AWS ECS Cluster by HTTP/aws.ecs.metrics.check))>0`|Warning||
|AWS ECS Cluster: Failed to get alarms data|<p>Failed to get CloudWatch alarms for ECS Cluster.</p>|`length(last(/AWS ECS Cluster by HTTP/aws.ecs.alarms.check))>0`|Warning||
|AWS ECS Cluster: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/AWS ECS Cluster by HTTP/aws.ecs.cpu_utilization,15m)>{$AWS.ECS.CLUSTER.CPU.UTIL.WARN}`|Warning||
|AWS ECS Cluster: High memory utilization|<p>The system is running out of free memory.</p>|`min(/AWS ECS Cluster by HTTP/aws.ecs.memory_utilization,15m)>{$AWS.ECS.CLUSTER.MEMORY.UTIL.WARN}`|Warning||

### LLD rule Cluster Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster Alarms discovery|<p>Discovery instance alarms.</p>|Dependent item|aws.ecs.alarms.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Cluster Alarms discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#ALARM_NAME}]: Get metrics|<p>Get alarm metrics about the state and its reason.</p>|Dependent item|aws.ecs.alarm.get_metrics["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#ALARM_NAME}]: State reason|<p>An explanation for the alarm state, in text format.</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.ecs.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#ALARM_NAME}]: State|<p>The state value for the alarm. Possible values: 0 (OK), 1 (INSUFFICIENT_DATA), 2 (ALARM).</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.ecs.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateValue`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Cluster Alarms discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ECS Cluster: [{#ALARM_NAME}] has 'Alarm' state|<p>Alarm "{#ALARM_NAME}" has `Alarm` state.<br>Reason: {ITEM.LASTVALUE2}</p>|`last(/AWS ECS Cluster by HTTP/aws.ecs.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS ECS Cluster by HTTP/aws.ecs.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS ECS Cluster: [{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS ECS Cluster by HTTP/aws.ecs.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Cluster Services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster Services discovery|<p>Discovery {$AWS.ECS.CLUSTER.NAME} services.</p>|Dependent item|aws.ecs.services.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Cluster Services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#AWS.ECS.SERVICE.NAME}]: Running Task|<p>The number of tasks currently in the `running` state.</p>|Dependent item|aws.ecs.services.running.task["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Pending Task|<p>The number of tasks currently in the `pending` state.</p>|Dependent item|aws.ecs.services.pending.task["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Desired Task|<p>The desired number of tasks for an {#AWS.ECS.SERVICE.NAME} service.</p>|Dependent item|aws.ecs.services.desired.task["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Task Set|<p>The number of task sets in the {#AWS.ECS.SERVICE.NAME} service.</p>|Dependent item|aws.ecs.services.task.set["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: CPU Reserved|<p>A number of CPU units reserved by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined CPU reservation in their task definition.</p>|Dependent item|aws.ecs.services.cpu_reserved["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: CPU Utilization|<p>A number of CPU units used by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined CPU reservation in their task definition.</p>|Dependent item|aws.ecs.services.cpu.utilization["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Memory utilized|<p>The memory being used by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.</p>|Dependent item|aws.ecs.services.memory_utilized["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Memory utilization|<p>The memory being used by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.</p>|Dependent item|aws.ecs.services.memory.utilization["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Memory reserved|<p>The memory that is reserved by tasks in the resource that is specified by the dimension set that you're using.</p><p>This metric is only collected for tasks that have a defined memory reservation in their task definition.</p>|Dependent item|aws.ecs.services.memory_reserved["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Network rx bytes|<p>The number of bytes received by the resource that is specified by the dimensions that you're using.</p><p>This metric is only available for containers in tasks using the awsvpc or bridge network modes.</p>|Dependent item|aws.ecs.services.network.rx["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Network tx bytes|<p>The number of bytes transmitted by the resource that is specified by the dimensions that you're using.</p><p>This metric is only available for containers in tasks using the awsvpc or bridge network modes.</p>|Dependent item|aws.ecs.services.network.tx["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ECS.SERVICE.NAME}]: Get metrics|<p>Get metrics of ESC services.</p><p>Full metrics list related to ECS : https://docs.aws.amazon.com/ecs/index.html</p>|Script|aws.ecs.services.get_metrics["{#AWS.ECS.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Cluster Services discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ECS Cluster: [{#AWS.ECS.SERVICE.NAME}]: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/AWS ECS Cluster by HTTP/aws.ecs.services.cpu.utilization["{#AWS.ECS.SERVICE.NAME}"],15m)>{$AWS.ECS.CLUSTER.SERVICE.CPU.UTIL.WARN}`|Warning||
|AWS ECS Cluster: [{#AWS.ECS.SERVICE.NAME}]: High memory utilization|<p>The system is running out of free memory.</p>|`min(/AWS ECS Cluster by HTTP/aws.ecs.services.memory.utilization["{#AWS.ECS.SERVICE.NAME}"],15m)>{$AWS.ECS.CLUSTER.SERVICE.MEMORY.UTIL.WARN}`|Warning||

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

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS ELB Application Load Balancer with Target Groups by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS ELB Application Load Balancer metrics and uses the script item to make HTTP requests to the CloudWatch API.

Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account. For more information, visit the [ELB policies page](https://docs.aws.amazon.com/elasticloadbalancing/latest/userguide/elb-api-permissions.html) on the AWS website.

### Required Permissions
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

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume role authorization
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
                "cloudwatch:GetMetricData",
                "elasticloadbalancing:DescribeTargetGroups"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
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

#### Trust Relationships for Role-Based Authorization
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

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

Set the macros: `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, and `{$AWS.ELB.ARN}`.

For more information about managing access keys, see [official AWS documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

See the section below for a list of macros used for LLD filters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.REGION}|<p>AWS Application Load Balancer region code.</p>|`us-west-1`|
|{$AWS.DATA.TIMEOUT}|<p>API response timeout.</p>|`60s`|
|{$AWS.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, no proxy is used.</p>||
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
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
|Get metrics data|<p>Get ELB Application Load Balancer metrics.</p><p>Full metrics list related to Application Load Balancer: https://docs.aws.amazon.com/elasticloadbalancing/latest/application/load-balancer-cloudwatch-metrics.html</p>|Script|aws.elb.alb.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get target groups|<p>Get ELB target group.</p><p>`DescribeTargetGroups` API method: https://docs.aws.amazon.com/elasticloadbalancing/latest/APIReference/API_DescribeTargetGroups.html</p>|Script|aws.elb.alb.get_target_groups<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get ELB ALB alarms data|<p>`DescribeAlarms` API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.elb.alb.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get metrics check|<p>Check that the Application Load Balancer metrics data has been received correctly.</p>|Dependent item|aws.elb.alb.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get alarms check|<p>Check that the alarm data has been received correctly.</p>|Dependent item|aws.elb.alb.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Active Connection Count|<p>The total number of active concurrent TCP connections from clients to the load balancer and from the load balancer to targets.</p>|Dependent item|aws.elb.alb.active_connection_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|New Connection Count|<p>The total number of new TCP connections established from clients to the load balancer and from the load balancer to targets.</p>|Dependent item|aws.elb.alb.new_connection_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Rejected Connection Count|<p>The number of connections that were rejected because the load balancer had reached its maximum number of connections.</p>|Dependent item|aws.elb.alb.rejected_connection_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Requests Count|<p>The number of requests processed over IPv4 and IPv6.</p><p>This metric is only incremented for requests where the load balancer node was able to choose a target.</p><p>Requests that are rejected before a target is chosen are not reflected in this metric.</p>|Dependent item|aws.elb.alb.requests_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "RequestCount")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Target Response Time|<p>The time elapsed, in seconds, after the request leaves the load balancer until a response from the target is received.</p><p>This is equivalent to the `target_processing_time` field in the access logs.</p>|Dependent item|aws.elb.alb.target_response_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HTTP Fixed Response Count|<p>The number of fixed-response actions that were successful.</p>|Dependent item|aws.elb.alb.http_fixed_response_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Rule Evaluations|<p>The number of rules processed by the load balancer given a request rate averaged over an hour.</p>|Dependent item|aws.elb.alb.rule_evaluations<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "RuleEvaluations")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Client TLS Negotiation Error Count|<p>The number of TLS connections initiated by the client that did not establish a session with the load balancer due to a TLS error.</p><p>Possible causes include a mismatch of ciphers or protocols or the client failing to verify the server certificate and closing the connection.</p>|Dependent item|aws.elb.alb.client_tls_negotiation_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Target TLS Negotiation Error Count|<p>The number of TLS connections initiated by the load balancer that did not establish a session with the target.</p><p>Possible causes include a mismatch of ciphers or protocols. This metric does not apply if the target is a Lambda function.</p>|Dependent item|aws.elb.alb.target_tls_negotiation_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Target Connection Error Count|<p>The number of connections that were not successfully established between the load balancer and target.</p><p>This metric does not apply if the target is a Lambda function.</p>|Dependent item|aws.elb.alb.target_connection_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Consumed LCUs|<p>The number of load balancer capacity units (LCU) used by your load balancer.</p><p>You pay for the number of LCUs that you use per hour.</p><p>More information on Elastic Load Balancing pricing here: https://aws.amazon.com/elasticloadbalancing/pricing/</p>|Dependent item|aws.elb.alb.capacity_units<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ConsumedLCUs")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Processed Bytes|<p>The total number of bytes processed by the load balancer over IPv4 and IPv6 (HTTP header and HTTP payload).</p><p>This count includes traffic to and from clients and Lambda functions, and traffic from an Identity Provider (IdP) if user authentication is enabled.</p>|Dependent item|aws.elb.alb.processed_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ProcessedBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Desync Mitigation Mode Non Compliant Request Count|<p>The number of requests that fail to comply with HTTP protocols.</p>|Dependent item|aws.elb.alb.non_compliant_request_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HTTP Redirect Count|<p>The number of redirect actions that were successful.</p>|Dependent item|aws.elb.alb.http_redirect_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HTTP Redirect Url Limit Exceeded Count|<p>The number of redirect actions that could not be completed because the URL in the response location header is larger than 8K bytes.</p>|Dependent item|aws.elb.alb.http_redirect_url_limit_exceeded_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB HTTP 3XX Count|<p>The number of HTTP 3XX redirection codes that originate from the load balancer.</p><p>This count does not include response codes generated by targets.</p>|Dependent item|aws.elb.alb.http_3xx_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB HTTP 4XX Count|<p>The number of HTTP 4XX client error codes that originate from the load balancer.</p><p>Client errors are generated when requests are malformed or incomplete. These requests were not received by the target, other than in the case where the load balancer returns an HTTP 460 error code.</p><p>This count does not include any response codes generated by the targets.</p>|Dependent item|aws.elb.alb.http_4xx_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB HTTP 5XX Count|<p>The number of HTTP 5XX server error codes that originate from the load balancer.</p><p>This count does not include any response codes generated by the targets.</p>|Dependent item|aws.elb.alb.http_5xx_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB HTTP 500 Count|<p>The number of HTTP 500 error codes that originate from the load balancer.</p>|Dependent item|aws.elb.alb.http_500_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB HTTP 502 Count|<p>The number of HTTP 502 error codes that originate from the load balancer.</p>|Dependent item|aws.elb.alb.http_502_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB HTTP 503 Count|<p>The number of HTTP 503 error codes that originate from the load balancer.</p>|Dependent item|aws.elb.alb.http_503_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB HTTP 504 Count|<p>The number of HTTP 504 error codes that originate from the load balancer.</p>|Dependent item|aws.elb.alb.http_504_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB Auth Error|<p>The number of user authentications that could not be completed because an authenticate action was misconfigured, the load balancer could not establish a connection with the IdP, or the load balancer could not complete the authentication flow due to an internal error.</p>|Dependent item|aws.elb.alb.auth_error<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ELBAuthError")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB Auth Failure|<p>The number of user authentications that could not be completed because the IdP denied access to the user or an authorization code was used more than once.</p>|Dependent item|aws.elb.alb.auth_failure<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ELBAuthFailure")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB Auth User Claims Size Exceeded|<p>The number of times that a configured IdP returned user claims that exceeded 11K bytes in size.</p>|Dependent item|aws.elb.alb.auth_user_claims_size_exceeded<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB Auth Latency|<p>The time elapsed, in milliseconds, to query the IdP for the ID token and user info.</p><p>If one or more of these operations fail, this is the time to failure.</p>|Dependent item|aws.elb.alb.auth_latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ELBAuthLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ELB Auth Success|<p>The number of authenticate actions that were successful.</p><p>This metric is incremented at the end of the authentication workflow, after the load balancer has retrieved the user claims from the IdP.</p>|Dependent item|aws.elb.alb.auth_success<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ELBAuthSuccess")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

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
|[{#ALARM_NAME}]: Get metrics|<p>Get metrics about the alarm state and its reason.</p>|Dependent item|aws.elb.alb.alarm.get_metrics["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#ALARM_NAME}]: State reason|<p>An explanation for the alarm state reason in text format.</p><p>Alarm description:</p><p>`{#ALARM_DESCRIPTION}`</p>|Dependent item|aws.elb.alb.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#ALARM_NAME}]: State|<p>The value of the alarm state. Possible values:</p><p>0 - OK;</p><p>1 - INSUFFICIENT_DATA;</p><p>2 - ALARM.</p><p>Alarm description:</p><p>`{#ALARM_DESCRIPTION}`</p>|Dependent item|aws.elb.alb.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateValue`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Load Balancer alarm discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ELB ALB: [{#ALARM_NAME}] has 'Alarm' state|<p>The alarm `{#ALARM_NAME}` is in the ALARM state.<br>Reason: `{ITEM.LASTVALUE2}`</p>|`last(/AWS ELB Application Load Balancer by HTTP/aws.elb.alb.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS ELB Application Load Balancer by HTTP/aws.elb.alb.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS ELB ALB: [{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS ELB Application Load Balancer by HTTP/aws.elb.alb.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Target groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Target groups discovery|<p>Used for the discovery of `{$AWS.ELB.TARGET.GROUP.NAME}` target groups.</p>|Dependent item|aws.elb.alb.target_groups.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Target groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Get metrics|<p>Get the metrics of the ELB target group `{#AWS.ELB.TARGET.GROUP.NAME}`.</p><p>Full list of metrics related to AWS ELB here: https://docs.aws.amazon.com/elasticloadbalancing/latest/application/load-balancer-cloudwatch-metrics.html#user-authentication-metric-table</p>|Script|aws.elb.alb.target_groups.get_metrics["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: HTTP Code Target 2XX Count|<p>The number of HTTP response 2XX codes generated by the targets.</p><p>This does not include any response codes generated by the load balancer.</p>|Dependent item|aws.elb.alb.target_groups.http_2xx_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: HTTP Code Target 3XX Count|<p>The number of HTTP response 3XX codes generated by the targets.</p><p>This does not include any response codes generated by the load balancer.</p>|Dependent item|aws.elb.alb.target_groups.http_3xx_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: HTTP Code Target 4XX Count|<p>The number of HTTP response 4XX codes generated by the targets.</p><p>This does not include any response codes generated by the load balancer.</p>|Dependent item|aws.elb.alb.target_groups.http_4xx_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: HTTP Code Target 5XX Count|<p>The number of HTTP response 5XX codes generated by the targets.</p><p>This does not include any response codes generated by the load balancer.</p>|Dependent item|aws.elb.alb.target_groups.http_5xx_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Healthy Host Count|<p>The number of targets that are considered healthy.</p>|Dependent item|aws.elb.alb.target_groups.healthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "HealthyHostCount")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Unhealthy Host Count|<p>The number of targets that are considered unhealthy.</p>|Dependent item|aws.elb.alb.target_groups.unhealthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Healthy State Routing|<p>The number of zones that meet the routing healthy state requirements.</p>|Dependent item|aws.elb.alb.target_groups.healthy_state_routing["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Unhealthy State Routing|<p>The number of zones that do not meet the routing healthy state requirements, and therefore the load balancer distributes traffic to all targets in the zone, including the unhealthy targets.</p>|Dependent item|aws.elb.alb.target_groups.unhealthy_state_routing["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Request Count Per Target|<p>The average request count per target, in a target group.</p><p>You must specify the target group using the TargetGroup dimension.</p>|Dependent item|aws.elb.alb.target_groups.request["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Unhealthy Routing Request Count|<p>The average request count per target, in a target group.</p>|Dependent item|aws.elb.alb.target_groups.unhealthy_routing_request_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Mitigated Host Count|<p>The number of targets under mitigation.</p>|Dependent item|aws.elb.alb.target_groups.mitigated_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Anomalous Host Count|<p>The number of hosts detected with anomalies.</p>|Dependent item|aws.elb.alb.target_groups.anomalous_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Healthy State DNS|<p>The number of zones that meet the DNS healthy state requirements.</p>|Dependent item|aws.elb.alb.target_groups.healthy_state_dns["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "HealthyStateDNS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Unhealthy State DNS|<p>The number of zones that do not meet the DNS healthy state requirements and therefore were marked unhealthy in DNS.</p>|Dependent item|aws.elb.alb.target_groups.unhealthy_state_dns["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "UnhealthyStateDNS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

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

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS ELB Network Load Balancer with Target Groups by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS ELB Network Load Balancer metrics and uses the script item to make HTTP requests to the CloudWatch API.

Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account. For more information, visit the [ELB policies page](https://docs.aws.amazon.com/elasticloadbalancing/latest/userguide/elb-api-permissions.html) on the AWS website.

### Required Permissions
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

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume role authorization
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
                "cloudwatch:GetMetricData",
                "elasticloadbalancing:DescribeTargetGroups"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
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

#### Trust Relationships for Role-Based Authorization
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

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

Set the macros: `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, and `{$AWS.ELB.ARN}`.

For more information about managing access keys, see [official AWS documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

See the section below for a list of macros used for LLD filters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.REGION}|<p>AWS Network Load Balancer region code.</p>|`us-west-1`|
|{$AWS.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, no proxy is used.</p>||
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.DATA.TIMEOUT}|<p>API response timeout.</p>|`60s`|
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
|Get metrics data|<p>Get ELB Network Load Balancer metrics.</p><p>Full metrics list related to Network Load Balancer: https://docs.aws.amazon.com/elasticloadbalancing/latest/network/load-balancer-cloudwatch-metrics.html</p>|Script|aws.elb.nlb.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get target groups|<p>Get ELB target group.</p><p>`DescribeTargetGroups` API method: https://docs.aws.amazon.com/elasticloadbalancing/latest/APIReference/API_DescribeTargetGroups.html</p>|Script|aws.elb.nlb.get_target_groups<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get ELB NLB alarms data|<p>`DescribeAlarms` API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.elb.nlb.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get metrics check|<p>Check that the Network Load Balancer metrics data has been received correctly.</p>|Dependent item|aws.elb.nlb.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get alarms check|<p>Check that the alarm data has been received correctly.</p>|Dependent item|aws.elb.nlb.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Active Flow Count|<p>The total number of concurrent flows (or connections) from clients to targets.</p><p>This metric includes connections in the `SYN_SENT` and `ESTABLISHED` states.</p><p>TCP connections are not terminated at the load balancer, so a client opening a TCP connection to a target counts as a single flow.</p>|Dependent item|aws.elb.nlb.active_flow_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ActiveFlowCount")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Active Flow Count TCP|<p>The total number of concurrent TCP flows (or connections) from clients to targets.</p><p>This metric includes connections in the `SYN_SENT` and `ESTABLISHED` states.</p><p>TCP connections are not terminated at the load balancer, so a client opening a TCP connection to a target counts as a single flow.</p>|Dependent item|aws.elb.nlb.active_flow_count_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Active Flow Count TLS|<p>The total number of concurrent TLS flows (or connections) from clients to targets.</p><p>This metric includes connections in the `SYN_SENT` and `ESTABLISHED` states.</p>|Dependent item|aws.elb.nlb.active_flow_count_tls<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Active Flow Count UDP|<p>The total number of concurrent UDP flows (or connections) from clients to targets.</p>|Dependent item|aws.elb.nlb.active_flow_count_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Client TLS Negotiation Error Count|<p>The total number of TLS handshakes that failed during negotiation between a client and a TLS listener.</p>|Dependent item|aws.elb.nlb.client_tls_negotiation_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Consumed LCUs|<p>The number of load balancer capacity units (LCU) used by your load balancer.</p><p>You pay for the number of LCUs that you use per hour.</p><p>More information on Elastic Load Balancing pricing here: https://aws.amazon.com/elasticloadbalancing/pricing/</p>|Dependent item|aws.elb.nlb.capacity_units<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ConsumedLCUs")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Consumed LCUs TCP|<p>The number of load balancer capacity units (LCU) used by your load balancer for TCP.</p><p>You pay for the number of LCUs that you use per hour.</p><p>More information on Elastic Load Balancing pricing here: https://aws.amazon.com/elasticloadbalancing/pricing/</p>|Dependent item|aws.elb.nlb.capacity_units_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ConsumedLCUs_TCP")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Consumed LCUs TLS|<p>The number of load balancer capacity units (LCU) used by your load balancer for TLS.</p><p>You pay for the number of LCUs that you use per hour.</p><p>More information on Elastic Load Balancing pricing here: https://aws.amazon.com/elasticloadbalancing/pricing/</p>|Dependent item|aws.elb.nlb.capacity_units_tls<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ConsumedLCUs_TLS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Consumed LCUs UDP|<p>The number of load balancer capacity units (LCU) used by your load balancer for UDP.</p><p>You pay for the number of LCUs that you use per hour.</p><p>More information on Elastic Load Balancing pricing here: https://aws.amazon.com/elasticloadbalancing/pricing/</p>|Dependent item|aws.elb.nlb.capacity_units_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ConsumedLCUs_UDP")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|New Flow Count|<p>The total number of new flows (or connections) established from clients to targets in the specified time period.</p>|Dependent item|aws.elb.nlb.new_flow_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NewFlowCount")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|New Flow Count TCP|<p>The total number of new TCP flows (or connections) established from clients to targets in the specified time period.</p>|Dependent item|aws.elb.nlb.new_flow_count_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NewFlowCount_TCP")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|New Flow Count TLS|<p>The total number of new TLS flows (or connections) established from clients to targets in the specified time period.</p>|Dependent item|aws.elb.nlb.new_flow_count_tls<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NewFlowCount_TLS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|New Flow Count UDP|<p>The total number of new UDP flows (or connections) established from clients to targets in the specified time period.</p>|Dependent item|aws.elb.nlb.new_flow_count_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NewFlowCount_UDP")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Peak Packets per second|<p>Highest average packet rate (packets processed per second), calculated every 10 seconds during the sampling window.</p><p>This metric includes health check traffic.</p>|Dependent item|aws.elb.nlb.peak_packets.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Port Allocation Error Count|<p>The total number of ephemeral port allocation errors during a client IP translation operation. A non-zero value indicates dropped client connections.</p><p>Note: Network Load Balancers support 55,000 simultaneous connections or about 55,000 connections per minute to each unique target (IP address and port) when performing client address translation.</p><p>To fix port allocation errors, add more targets to the target group.</p>|Dependent item|aws.elb.nlb.port_allocation_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Processed Bytes|<p>The total number of bytes processed by the load balancer, including TCP/IP headers. This count includes traffic to and from targets, minus health check traffic.</p>|Dependent item|aws.elb.nlb.processed_bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ProcessedBytes")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Processed Bytes TCP|<p>The total number of bytes processed by TCP listeners.</p>|Dependent item|aws.elb.nlb.processed_bytes_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Processed Bytes TLS|<p>The total number of bytes processed by TLS listeners.</p>|Dependent item|aws.elb.nlb.processed_bytes_tls<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Processed Bytes UDP|<p>The total number of bytes processed by UDP listeners.</p>|Dependent item|aws.elb.nlb.processed_bytes_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Processed Packets|<p>The total number of packets processed by the load balancer. This count includes traffic to and from targets, including health check traffic.</p>|Dependent item|aws.elb.nlb.processed_packets<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ProcessedPackets")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Security Group Blocked Flow Count Inbound ICMP|<p>The number of new ICMP messages rejected by the inbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_inbound_icmp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Security Group Blocked Flow Count Inbound TCP|<p>The number of new TCP flows rejected by the inbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_inbound_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Security Group Blocked Flow Count Inbound UDP|<p>The number of new UDP flows rejected by the inbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_inbound_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Security Group Blocked Flow Count Outbound ICMP|<p>The number of new ICMP messages rejected by the outbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_outbound_icmp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Security Group Blocked Flow Count Outbound TCP|<p>The number of new TCP flows rejected by the outbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_outbound_tcp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Security Group Blocked Flow Count Outbound UDP|<p>The number of new UDP flows rejected by the outbound rules of the load balancer security groups.</p>|Dependent item|aws.elb.nlb.sg_blocked_outbound_udp<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Target TLS Negotiation Error Count|<p>The total number of TLS handshakes that failed during negotiation between a TLS listener and a target.</p>|Dependent item|aws.elb.nlb.target_tls_negotiation_error_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TCP Client Reset Count|<p>The total number of reset (RST) packets sent from a client to a target.</p><p>These resets are generated by the client and forwarded by the load balancer.</p>|Dependent item|aws.elb.nlb.tcp_client_reset_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TCP ELB Reset Count|<p>The total number of reset (RST) packets generated by the load balancer.</p><p>For more information, see: https://docs.aws.amazon.com/elasticloadbalancing/latest/network/load-balancer-troubleshooting.html#elb-reset-count-metric</p>|Dependent item|aws.elb.nlb.tcp_elb_reset_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|TCP Target Reset Count|<p>The total number of reset (RST) packets sent from a target to a client.</p><p>These resets are generated by the target and forwarded by the load balancer.</p>|Dependent item|aws.elb.nlb.tcp_target_reset_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Unhealthy Routing Flow Count|<p>The number of flows (or connections) that are routed using the routing failover action (fail open).</p>|Dependent item|aws.elb.nlb.unhealthy_routing_flow_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

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
|[{#ALARM_NAME}]: Get metrics|<p>Get metrics about the alarm state and its reason.</p>|Dependent item|aws.elb.nlb.alarm.get_metrics["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#ALARM_NAME}]: State reason|<p>An explanation for the alarm state reason in text format.</p><p>Alarm description:</p><p>`{#ALARM_DESCRIPTION}`</p>|Dependent item|aws.elb.nlb.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|[{#ALARM_NAME}]: State|<p>The value of the alarm state. Possible values:</p><p>0 - OK;</p><p>1 - INSUFFICIENT_DATA;</p><p>2 - ALARM.</p><p>Alarm description:</p><p>`{#ALARM_DESCRIPTION}`</p>|Dependent item|aws.elb.nlb.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StateValue`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Load Balancer alarm discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ELB NLB: [{#ALARM_NAME}] has 'Alarm' state|<p>The alarm `{#ALARM_NAME}` is in the ALARM state.<br>Reason: `{ITEM.LASTVALUE2}`</p>|`last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS ELB NLB: [{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Target groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Target groups discovery|<p>Used for the discovery of `{$AWS.ELB.TARGET.GROUP.NAME}` target groups.</p>|Dependent item|aws.elb.nlb.target_groups.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Target groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Get metrics|<p>Get the metrics of the ELB target group `{#AWS.ELB.TARGET.GROUP.NAME}`.</p><p>Full list of metrics related to AWS ELB here: https://docs.aws.amazon.com/elasticloadbalancing/latest/network/load-balancer-cloudwatch-metrics.html#user-authentication-metric-table</p>|Script|aws.elb.nlb.target_groups.get_metrics["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Healthy Host Count|<p>The number of targets that are considered healthy.</p>|Dependent item|aws.elb.nlb.target_groups.healthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "HealthyHostCount")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|[{#AWS.ELB.TARGET.GROUP.NAME}]: Unhealthy Host Count|<p>The number of targets that are considered unhealthy.</p>|Dependent item|aws.elb.nlb.target_groups.unhealthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Target groups discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS ELB NLB: [{#AWS.ELB.TARGET.GROUP.NAME}]: Target have become unhealthy|<p>This trigger helps in identifying when your targets have become unhealthy.</p>|`last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.target_groups.healthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]) = 0`|Average||
|AWS ELB NLB: [{#AWS.ELB.TARGET.GROUP.NAME}]: Target have unhealthy host|<p>This trigger allows you to become aware when there are no more registered targets.</p>|`last(/AWS ELB Network Load Balancer by HTTP/aws.elb.nlb.target_groups.unhealthy_host_count["{#AWS.ELB.TARGET.GROUP.NAME}"]) > {$AWS.ELB.UNHEALTHY.HOST.MAX}`|Warning|**Depends on**:<br><ul><li>AWS ELB NLB: [{#AWS.ELB.TARGET.GROUP.NAME}]: Target have become unhealthy</li></ul>|

# AWS Lambda by HTTP

## Overview

This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the [CloudWatch pricing](https://aws.amazon.com/cloudwatch/pricing/) page.

Additional information about metrics and API methods used in the template:
* [Full metrics list related to AWS Lambda](https://docs.aws.amazon.com/lambda/latest/dg/monitoring-metrics.html)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS Lambda by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS Lambda metrics and uses the script item to make HTTP requests to the CloudWatch API.

Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account. For more information, visit the [Lambda permissions page](https://docs.aws.amazon.com/lambda/latest/dg/lambda-permissions.html) on the AWS website.

### Required Permissions
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

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume role authorization
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

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
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
#### Trust Relationships for Role-Based Authorization
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

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

Set the macros: `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, and `{$AWS.LAMBDA.ARN}`.

For more information about managing access keys, see the [official AWS documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

See the section below for a list of macros used for LLD filters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.REGION}|<p>AWS Lambda function region code.</p>|`us-west-1`|
|{$AWS.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, no proxy is used.</p>||
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.DATA.TIMEOUT}|<p>API response timeout.</p>|`60s`|
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
|AWS Lambda: Failed to get metrics data|<p>Failed to get CloudWatch metrics for the Lambda function.</p>|`length(last(/AWS Lambda by HTTP/aws.lambda.metrics.check))>0`|Warning||
|AWS Lambda: Failed to get alarms data|<p>Failed to get CloudWatch alarms for the Lambda function.</p>|`length(last(/AWS Lambda by HTTP/aws.lambda.alarms.check))>0`|Warning||

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
|AWS Lambda: [{#ALARM_NAME}] has 'Alarm' state|<p>The alarm `{#ALARM_NAME}` is in the ALARM state.<br>Reason: `{ITEM.LASTVALUE2}`</p>|`last(/AWS Lambda by HTTP/aws.lambda.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS Lambda by HTTP/aws.lambda.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS Lambda: [{#ALARM_NAME}] has 'Insufficient data' state|<p>Either the alarm has just started, the metric is not available, or not enough data is available for the metric to determine the alarm state.</p>|`last(/AWS Lambda by HTTP/aws.lambda.alarm.state["{#ALARM_NAME}"])=1`|Info||

# AWS Backup Vault by HTTP

## Overview

This template uses AWS Backup API calls to list and retrieve metrics.
For more information, please refer to the [AWS Backup API](https://docs.aws.amazon.com/aws-backup/latest/devguide/api-reference.html) page.

Additional information about metrics and API methods used in the template:
* [Metrics related to backup vaults](https://docs.aws.amazon.com/aws-backup/latest/devguide/API_BackupVaultListMember.html)
* [Metrics related to backup jobs](https://docs.aws.amazon.com/aws-backup/latest/devguide/API_BackupJob.html)


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS Backup Vault service

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

The template gets AWS Backup vault metrics and uses the script item to make HTTP requests to the AWS Backup API.

Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account.

### Required permissions
Add the following required permissions to your Zabbix IAM policy in order to collect AWS backup vaults and jobs.

```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
              "backup:ListBackupVaults",
              "backup:ListBackupJobs",
              "backup:ListCopyJobs",
              "backup:ListRestoreJobs"
          ],
          "Effect":"Allow",
          "Resource":"*"
        }
    ]
  }
```

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and a secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and a secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume Role authorization
For using Assume Role authorization, add the appropriate permissions to the role you are using:

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
                "backup:ListBackupVaults",
                "backup:ListBackupJobs",
                "backup:ListCopyJobs",
                "backup:ListRestoreJobs"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
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
                "backup:ListBackupVaults",
                "backup:ListBackupJobs",
                "backup:ListCopyJobs",
                "backup:ListRestoreJobs"
            ],
            "Resource": "*"
        }
    ]
}
```
#### Trust Relationships for Role-Based Authorization
Next, add a principal to the trust relationships of the role you are using:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "Service": [
                    "backup.amazonaws.com"
                ]
            },
            "Action": [
                "sts:AssumeRole"
            ]
        }
    ]
}
```

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

Set the macros: `{$AWS.AUTH_TYPE}`, `{$AWS.REGION}`, and `{$AWS.BACKUP_VAULT.NAME}`.

For more information about managing access keys, see the [official AWS documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

See the section below for a list of macros used for LLD filters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.DATA.TIMEOUT}|<p>API response timeout.</p>|`60s`|
|{$AWS.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, no proxy is used.</p>||
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.REGION}|<p>AWS backup vault region code.</p>|`us-west-1`|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.BACKUP_VAULT.NAME}|<p>AWS backup vault name.</p>||
|{$AWS.BACKUP_JOB.STATE.MATCHES}|<p>Filter of discoverable jobs by state.</p>|`.*`|
|{$AWS.BACKUP_JOB.STATE.NOT_MATCHES}|<p>Filter to exclude discovered jobs by state.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.BACKUP_JOB.RESOURCE_TYPE.MATCHES}|<p>Filter of discoverable jobs by resource type.</p>|`.*`|
|{$AWS.BACKUP_JOB.RESOURCE_TYPE.NOT_MATCHES}|<p>Filter to exclude discovered jobs by resource type.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.BACKUP_JOB.RESOURCE_NAME.MATCHES}|<p>Filter of discoverable jobs by resource name.</p>|`.*`|
|{$AWS.BACKUP_JOB.RESOURCE_NAME.NOT_MATCHES}|<p>Filter to exclude discovered jobs by resource name.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.BACKUP_JOB.PERIOD}|<p>The number of days over which to retrieve backup jobs.</p>|`7`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get jobs|<p>Get a list of jobs in the vault.</p>|Script|aws.backup_vault.job.get|
|Get data|<p>Retrieve AWS backup vault metrics.</p><p>More information here: https://docs.aws.amazon.com/aws-backup/latest/devguide/API_BackupVaultListMember.html</p>|Script|aws.backup_vault.data.get|
|Recovery points|<p>The total number of recovery points in the backup vault.</p>|Dependent item|aws.backup_vault.recovery_points<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NumberOfRecoveryPoints`</p></li><li><p>Does not match regular expression: `null`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Age|<p>The age of the vault.</p>|Dependent item|aws.backup_vault.age<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CreationDate`</p></li><li><p>JavaScript: `return Date.now() / 1000 - value`</p></li></ul>|
|Retention period, min|<p>The minimum retention period that the vault retains its recovery points.</p>|Dependent item|aws.backup_vault.retention.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MinRetentionDays`</p></li><li><p>Does not match regular expression: `null`</p><p>⛔️Custom on fail: Set error to: `The vault does not have the minimum retention period set.`</p></li></ul>|
|Retention period, max|<p>The maximum retention period that the vault retains its recovery points.</p>|Dependent item|aws.backup_vault.retention.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MaxRetentionDays`</p></li><li><p>Does not match regular expression: `null`</p><p>⛔️Custom on fail: Set error to: `The vault does not have the maximum retention period set.`</p></li></ul>|
|Lock status|<p>Indicates whether AWS Backup Vault Lock is applied to the selected backup vault. When the vault is locked, delete and update operations on recovery points in that vault are prevented.</p>|Dependent item|aws.backup_vault.lock.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Locked`</p></li><li><p>Replace: `false -> 0`</p></li><li><p>Replace: `true -> 1`</p></li></ul>|
|Lock time remain|<p>The remaining time before AWS Backup Vault Lock configuration becomes immutable, meaning it cannot be changed or deleted.</p>|Dependent item|aws.backup_vault.lock.time_left<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LockDate`</p></li><li><p>Does not match regular expression: `null`</p><p>⛔️Custom on fail: Set error to: `Either the vault is not locked, or the lock date is not specified.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Lock date|<p>The date and time when AWS Backup Vault Lock configuration becomes immutable, meaning it cannot be changed or deleted.</p>|Dependent item|aws.backup_vault.lock.date<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LockDate`</p></li><li><p>Does not match regular expression: `null`</p><p>⛔️Custom on fail: Set error to: `Either the vault is not locked, or the lock date is not specified.`</p></li></ul>|
|State|<p>The current state of the backup vault.</p><p>Possible values are:</p><p>- Unknown</p><p>- Creating</p><p>- Available</p><p>- Failed</p>|Dependent item|aws.backup_vault.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VaultState`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Jobs: Size, avg|<p>The average size, in bytes, of a backup (recovery point).</p><p>This value can render differently depending on the resource type as AWS Backup pulls in data information from other AWS services. For example, the value returned may show a value of `0`, which may differ from the anticipated value.</p>|Dependent item|aws.backup_vault.job.size.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.job_size > 0)].job_size.avg()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Size, max|<p>The maximum size, in bytes, of a backup (recovery point).</p><p>This value can render differently depending on the resource type as AWS Backup pulls in data information from other AWS services. For example, the value returned may show a value of `0`, which may differ from the anticipated value.</p>|Dependent item|aws.backup_vault.job.size.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.job_size > 0)].job_size.max()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Size, min|<p>The minimum size, in bytes, of a backup (recovery point).</p><p>This value can render differently depending on the resource type as AWS Backup pulls in data information from other AWS services. For example, the value returned may show a value of `0`, which may differ from the anticipated value.</p>|Dependent item|aws.backup_vault.job.size.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.job_size > 0)].job_size.min()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Backup|<p>The number of backup jobs in the vault over the last `{$AWS.BACKUP_JOB.PERIOD}` day(s).</p>|Dependent item|aws.backup_vault.job.backup.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.job_type == "backup-job")].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Restore|<p>The number of restore jobs in the vault over the last `{$AWS.BACKUP_JOB.PERIOD}` day(s).</p>|Dependent item|aws.backup_vault.job.restore.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.job_type == "restore-job")].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Copy|<p>The number of copy jobs in the vault over the last `{$AWS.BACKUP_JOB.PERIOD}` day(s).</p>|Dependent item|aws.backup_vault.job.copy.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.job_type == "copy-job")].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Total|<p>The total number of jobs in the vault over the last `{$AWS.BACKUP_JOB.PERIOD}` day(s).</p>|Dependent item|aws.backup_vault.job.total.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Failed backup|<p>The number of failed backup jobs in the vault over the last `{$AWS.BACKUP_JOB.PERIOD}` day(s).</p>|Dependent item|aws.backup_vault.job.backup.failed.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Failed restore|<p>The number of failed restore jobs in the vault over the last `{$AWS.BACKUP_JOB.PERIOD}` day(s).</p>|Dependent item|aws.backup_vault.job.restore.failed.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Failed copy|<p>The number of failed copy jobs in the vault over the last `{$AWS.BACKUP_JOB.PERIOD}` day(s).</p>|Dependent item|aws.backup_vault.job.copy.failed.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS Backup vault: Restore job has appeared|<p>New restore job has appeared.</p>|`change(/AWS Backup Vault by HTTP/aws.backup_vault.job.restore.count)>0`|Average|**Manual close**: Yes|
|AWS Backup vault: Copy job has appeared|<p>New copy job has appeared.</p>|`change(/AWS Backup Vault by HTTP/aws.backup_vault.job.copy.count)>0`|Warning|**Manual close**: Yes|

### LLD rule AWS Backup job discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS Backup job discovery|<p>AWS Backup job discovery.</p>|Dependent item|aws.backup_vault.job.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for AWS Backup job discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Job state [{#AWS.BACKUP_JOB.RESOURCE_NAME}][{#AWS.BACKUP_JOB.ID}]|<p>The state of the job.</p><p>Possible values are:</p><p>- Unknown</p><p>- Created</p><p>- Pending</p><p>- Running</p><p>- Aborting</p><p>- Aborted</p><p>- Completed</p><p>- Failed</p><p>- Expired</p><p>- Partial</p>|Dependent item|aws.backup_vault.job.state["{#AWS.BACKUP_JOB.ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.job_id == "{#AWS.BACKUP_JOB.ID}")].job_state.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for AWS Backup job discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS Backup vault: Job failed [{#AWS.BACKUP_JOB.ID}]|<p>Job has failed.</p>|`last(/AWS Backup Vault by HTTP/aws.backup_vault.job.state["{#AWS.BACKUP_JOB.ID}"])=7`|High|**Manual close**: Yes|
|AWS Backup vault: Job has been aborted [{#AWS.BACKUP_JOB.ID}]|<p>Job has been aborted.</p>|`last(/AWS Backup Vault by HTTP/aws.backup_vault.job.state["{#AWS.BACKUP_JOB.ID}"])=5`|Average|**Manual close**: Yes|
|AWS Backup vault: Job has expired [{#AWS.BACKUP_JOB.ID}]|<p>Job expired.</p>|`last(/AWS Backup Vault by HTTP/aws.backup_vault.job.state["{#AWS.BACKUP_JOB.ID}"])=8`|Warning|**Manual close**: Yes|
|AWS Backup vault: Job is in an unknown state [{#AWS.BACKUP_JOB.ID}]|<p>Job is in unknown state.</p>|`last(/AWS Backup Vault by HTTP/aws.backup_vault.job.state["{#AWS.BACKUP_JOB.ID}"])=0`|Warning|**Manual close**: Yes|

# AWS Cost Explorer by HTTP

## Overview

The template to monitor AWS Cost Explorer by HTTP via Zabbix, which works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

**Note:** This template uses the Cost Explorer API calls to list and retrieve metrics.

For more information, please refer to the [Cost Explorer pricing](https://aws.amazon.com/aws-cost-management/aws-cost-explorer/pricing/) page.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- AWS by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Before using the template, you need to create an IAM policy for the Zabbix role in your AWS account with the necessary permissions.

* [IAM policies for AWS Cost Management](https://docs.aws.amazon.com/cost-management/latest/userguide/billing-permissions-ref.html)

### Required Permissions
Add the following required permissions to your Zabbix IAM policy in order to collect metrics.

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Action": [
                "ce:GetDimensionValues",
                "ce:GetCostAndUsage"
            ],
            "Effect": "Allow",
            "Resource": "*"
        }
    ]
}
```

### Access Key Authorization

If you are using access key authorization, you need to generate an access key and secret key for an IAM user with the necessary permissions:

1. Create an IAM user with programmatic access.
2. Attach the required policy to the IAM user.
3. Generate an access key and secret key.
4. Use the generated credentials in the macros `{$AWS.ACCESS.KEY.ID}` and `{$AWS.SECRET.ACCESS.KEY}`.

### Assume Role Authorization
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
                "ce:GetDimensionValues",
                "ce:GetCostAndUsage"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Assume Role Authorization
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
Set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

**Note**: If you set the `{$AWS.ASSUME.ROLE.AUTH.METADATA}` macro to `true` and set the macros `{$AWS.STS.REGION}` and `{$AWS.ASSUME.ROLE.ARN}`, the Zabbix server or proxy will attempt to retrieve the role credentials from the instance metadata service.
This means that the Zabbix server or proxy must be running on an AWS EC2 instance with an IAM role assigned that has the necessary permissions.
This approach is recommended when running Zabbix inside an AWS EC2 instance with an IAM role assigned, as it simplifies credential management.

### Role-Based Authorization
If you are using role-based authorization, add the appropriate permissions:

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
            "Effect": "Allow",
            "Action": [
                "ce:GetDimensionValues",
                "ce:GetCostAndUsage",
                "ec2:AssociateIamInstanceProfile",
                "ec2:ReplaceIamInstanceProfileAssociation"
            ],
            "Resource": "*"
        }
    ]
}
```

#### Trust Relationships for Role-Based Authorization
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

**Note**: Using role-based authorization is only possible when you use a Zabbix server or proxy inside AWS.

Set the macros: `{$AWS.AUTH_TYPE}`. Possible values: `access_key`, `assume_role`, `role_base`.

For more information about managing access keys, see the [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

Also, see the Macros section for a list of macros used in LLD filters.

Additional information about metrics and used API methods:

* [Describe AWS Cost Explore API actions](https://docs.aws.amazon.com/aws-cost-management/latest/APIReference/API_Operations.html)


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.ASSUME.ROLE.AUTH.METADATA}|<p>Add when using the `assume_role` through instance metadata or environment authorization method. Possible values: `false`, `true`.</p>|`false`|
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.BILLING.REGION}|<p>Amazon Billing region code.</p>|`us-east-1`|
|{$AWS.BILLING.MONTH}|<p>Months to get historical data from AWS Cost Explore API, no more than 12 months.</p>|`11`|
|{$AWS.BILLING.LLD.FILTER.SERVICE.MATCHES}|<p>Filter of discoverable discovered billing service by name.</p>|`.*`|
|{$AWS.BILLING.LLD.FILTER.SERVICE.NOT_MATCHES}|<p>Filter to exclude discovered billing service by name.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get monthly costs|<p>Get raw data on the monthly costs by service.</p>|Script|aws.get.monthly.costs<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get daily costs|<p>Get raw data on the daily costs by service.</p>|Script|aws.get.daily.costs<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule AWS daily costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS daily costs by services discovery|<p>Discovery of daily blended costs by services.</p>|Dependent item|aws.daily.services.costs.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..Groups.first()`</p></li></ul>|

### Item prototypes for AWS daily costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service [{#AWS.BILLING.SERVICE.NAME}]: Blended daily cost|<p>The daily blended cost of the {#AWS.BILLING.SERVICE.NAME} service for the previous day.</p>|Dependent item|aws.daily.service.cost["{#AWS.BILLING.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule AWS monthly costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS monthly costs by services discovery|<p>Discovery of monthly costs by services.</p>|Dependent item|aws.cost.service.monthly.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monthly_service_costs`</p></li></ul>|

### Item prototypes for AWS monthly costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#AWS.BILLING.SERVICE.NAME}]: Month [{#AWS.BILLING.MONTH}] Blended cost|<p>The monthly cost by service {#AWS.BILLING.SERVICE.NAME}.</p>|Dependent item|aws.monthly.service.cost["{#AWS.BILLING.SERVICE.NAME}", "{#AWS.BILLING.MONTH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule AWS monthly costs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS monthly costs discovery|<p>Discovery of monthly costs.</p>|Dependent item|aws.monthly.cost.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monthly_costs`</p></li></ul>|

### Item prototypes for AWS monthly costs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#AWS.BILLING.MONTH}]: Blended cost per month|<p>The blended cost by month {#AWS.BILLING.MONTH}.</p>|Dependent item|aws.monthly.cost["{#AWS.BILLING.MONTH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

