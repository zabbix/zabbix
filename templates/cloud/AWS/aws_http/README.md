
# AWS by HTTP

## Overview

For Zabbix version: 6.2 and higher  
The template to monitor AWS EC2, RDS and S3 instances by HTTP via Zabbix that works without any external scripts.
Additional information about metrics and used API methods:
* Full metrics list related to EBS: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using_cloudwatch_ebs.html
* Full metrics list related to EC2: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/viewing_metrics_with_cloudwatch.html
* DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html
* DescribeVolumes API method: https://docs.aws.amazon.com/AWSEC2/latest/APIReference/API_DescribeVolumes.html
* Full metrics list related to RDS: https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/rds-metrics.html
* Full metrics list related to Amazon Aurora: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/Aurora.AuroraMySQL.Monitoring.Metrics.html#Aurora.AuroraMySQL.Monitoring.Metrics.instances
* DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html
* Full metrics list related to S3: https://docs.aws.amazon.com/AmazonS3/latest/userguide/metrics-dimensions.html


## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

Before using the template, you need to create an IAM policy for the Zabbix role in your AWS account with the necessary permissions.  

Add the following required permissions to your Zabbix IAM policy in order to collect metrics.  
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Action": [
                "cloudwatch:Describe*",
                "cloudwatch:Get*",
                "cloudwatch:List*",
                "ec2:Describe*",
                "rds:Describe*",
                "s3:ListAllMyBuckets",
                "s3:GetBucketLocation"
            ],
            "Effect": "Allow",
            "Resource": "*"
        }
    ]
}
  ```

To gather Request Metrics, [Enable Requests metrics](https://docs.aws.amazon.com/AmazonS3/latest/userguide/cloudwatch-monitoring.html) on your Amazon S3 buckets from the AWS console.

Set macros "{$AWS.ACCESS.KEY.ID}", "{$AWS.SECRET.ACCESS.KEY}", "{$AWS.REGION}"

For more information about manage access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys)

Also, see the Macros section for a list of macros used to LLD filters.

Additional information about metrics and used API methods:
* Full metrics list related to EBS: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using_cloudwatch_ebs.html
* Full metrics list related to EC2: https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/viewing_metrics_with_cloudwatch.html
* Full metrics list related to RDS: https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/rds-metrics.html
* Full metrics list related to Amazon Aurora: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/Aurora.AuroraMySQL.Monitoring.Metrics.html#Aurora.AuroraMySQL.Monitoring.Metrics.instances
* Full metrics list related to S3: https://docs.aws.amazon.com/AmazonS3/latest/userguide/metrics-dimensions.html


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.ACCESS.KEY.ID} |<p>Access key ID.</p> |`` |
|{$AWS.REGION} |<p>Amazon EC2 region code.</p> |`us-west-1` |
|{$AWS.SECRET.ACCESS.KEY} |<p>Secret access key.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|EC2 instances discovery |<p>Get EC2 instances.</p> |SCRIPT |aws.ec2.discovery |
|RDS instances discovery |<p>Get RDS instances.</p> |SCRIPT |aws.rds.discovery |
|S3 backets discovery |<p>Get S3 bucket instances.</p> |SCRIPT |aws.s3.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

