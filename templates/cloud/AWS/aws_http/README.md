
# AWS by HTTP

## Overview

This template is designed for the effortless deployment of AWS monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.2 and higher.

## Tested versions

This template has been tested on:
- AWS by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.2/manual/config/templates_out_of_the_box) section.

## Setup

Before using the template, you need to create an IAM policy for the Zabbix role in your AWS account with the necessary permissions.

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
                "lambda:ListFunctions"
            ],
            "Effect": "Allow",
            "Resource": "*"
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
                "lambda:ListFunctions"
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
                "lambda:ListFunctions"
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

To gather Request metrics, enable [Requests metrics](https://docs.aws.amazon.com/AmazonS3/latest/userguide/cloudwatch-monitoring.html) on your Amazon S3 buckets from the AWS console.

Set the macros: `{$AWS.AUTH_TYPE}`. Possible values: `access_key`, `assume_role`, `role_base`.

If you are using access key-based authorization, set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`.

If you are using access assume role authorization, set the following macros: `{$AWS.ACCESS.KEY.ID}`, `{$AWS.SECRET.ACCESS.KEY}`, `{$AWS.STS.REGION}`, `{$AWS.ASSUME.ROLE.ARN}`.

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
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)
* [DescribeVolumes API method](https://docs.aws.amazon.com/AWSEC2/latest/APIReference/API_DescribeVolumes.html)
* [DescribeAlarms API method](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html)
* [DescribeLoadBalancers API method](https://docs.aws.amazon.com/elasticloadbalancing/latest/APIReference/API_DescribeLoadBalancers.html)


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`60s`|
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: `access_key`, `assume_role`, `role_base`.</p>|`access_key`|
|{$AWS.REQUEST.REGION}|<p>Region used in GET request `ListBuckets`.</p>|`us-east-1`|
|{$AWS.DESCRIBE.REGION}|<p>Region used in POST request `DescribeRegions`.</p>|`us-east-1`|
|{$AWS.STS.REGION}|<p>Region used in assume role request.</p>|`us-east-1`|
|{$AWS.ASSUME.ROLE.ARN}|<p>ARN assume role; add when using the `assume_role` authorization method.</p>||
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

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

