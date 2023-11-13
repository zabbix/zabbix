
# AWS Cost Explorer by HTTP

## Overview

The template to monitor AWS Cost Explorer by HTTP via Zabbix, which works without any external scripts.  
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.
*NOTE*
This template uses the Cost Explorer API calls to list and retrieve metrics.
For more information, please refer to the [Cost Explorer pricing](https://aws.amazon.com/aws-cost-management/aws-cost-explorer/pricing/) page.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- AWS by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Before using the template, you need to create an IAM policy for the Zabbix role in your AWS account with the necessary permissions.

* [IAM policies for AWS Cost Management](https://docs.aws.amazon.com/cost-management/latest/userguide/billing-permissions-ref.html)

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
            "Sid": "VisualEditor1",
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
Set macros "{$AWS.AUTH_TYPE}". Possible values: role_base, access_key.

If you are using access key-based authorization, set the following macros {$AWS.ACCESS.KEY.ID}, {$AWS.SECRET.ACCESS.KEY}.

For more information about managing access keys, see the [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys).

Also, see the Macros section for a list of macros used in LLD filters.

Additional information about metrics and used API methods:

* [Describe AWS Cost Explore API actions](https://docs.aws.amazon.com/aws-cost-management/latest/APIReference/API_Operations.html)


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.BILLING.REGION}|<p>Amazon Billing region code.</p>|`us-east-1`|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: role_base, access_key.</p>|`role_base`|
|{$AWS.BILLING.MONTH}|<p>Months to get historical data from AWS Cost Explore API, no more than 12 months.</p>|`11`|
|{$AWS.BILLING.LLD.FILTER.SERVICE.MATCHES}|<p>Filter of discoverable discovered billing service by name.</p>|`.*`|
|{$AWS.BILLING.LLD.FILTER.SERVICE.NOT_MATCHES}|<p>Filter to exclude discovered billing service by name.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS Cost: Get monthly costs||Script|aws.get.monthly.costs<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS Cost: Get daily costs|<p>Get raw data on the daily costs by service</p>|Script|aws.get.daily.costs<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule AWS daily costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS daily costs by services discovery|<p>Discovery of daily blended costs by services.</p>|Dependent item|aws.daily.services.costs.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..Groups.first()`</p></li></ul>|

### Item prototypes for AWS daily costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS Cost: Service ["{#AWS.BILLING.SERVICE.NAME}"]: Blended daily cost|<p>The daily blended cost of the {#AWS.BILLING.SERVICE.NAME} service for the previous day.</p>|Dependent item|aws.daily.service.cost["{#AWS.BILLING.SERVICE.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule AWS monthly costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS monthly costs by services discovery|<p>Discovery of monthly costs by services.</p>|Dependent item|aws.cost.service.monthly.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monthly_service_costs`</p></li></ul>|

### Item prototypes for AWS monthly costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS Cost: ["{#AWS.BILLING.SERVICE.NAME}"]: Month ["{#AWS.BILLING.MONTH}"] Blended cost|<p>The monthly cost by service {#AWS.BILLING.SERVICE.NAME}.</p>|Dependent item|aws.monthly.service.cost["{#AWS.BILLING.SERVICE.NAME}", "{#AWS.BILLING.MONTH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule AWS monthly costs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS monthly costs discovery|<p>Discovery of monthly costs.</p>|Dependent item|aws.monthly.cost.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monthly_costs`</p></li></ul>|

### Item prototypes for AWS monthly costs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS Cost: ["{#AWS.BILLING.MONTH}"]: Blended cost per month|<p>The blended cost by month {#AWS.BILLING.MONTH}.</p>|Dependent item|aws.monthly.cost["{#AWS.BILLING.MONTH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

