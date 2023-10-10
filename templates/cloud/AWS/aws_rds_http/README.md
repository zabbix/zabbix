
# AWS RDS instance by HTTP

## Overview

The template to monitor AWS RDS instance by HTTP via Zabbix that works without any external scripts.  
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.
*NOTE*
This template uses the GetMetricData CloudWatch API calls to list and retrieve metrics.
For more information, please refer to the (CloudWatch pricing)[https://aws.amazon.com/cloudwatch/pricing/] page.

Additional information about metrics and used API methods:

* Full metrics list related to RDS: https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/rds-metrics.html
* Full metrics list related to Amazon Aurora: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/Aurora.AuroraMySQL.Monitoring.Metrics.html#Aurora.AuroraMySQL.Monitoring.Metrics.instances
* DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- AWS RDS instance by HTTP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

The template get AWS RDS instance metrics and uses the script item to make HTTP requests to the CloudWatch API.

Before using the template, you need to create an IAM policy with the necessary permissions for the Zabbix role in your AWS account.  

Add the following required permissions to your Zabbix IAM policy in order to collect Amazon RDS metrics.  
```json
{
    "Version":"2012-10-17",
    "Statement":[
        {
          "Action":[
                "cloudwatch:DescribeAlarms",
                "rds:DescribeEvents",
                "rds:DescribeDBInstances"
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

Set macros "{$AWS.AUTH_TYPE}", "{$AWS.REGION}", "{$AWS.RDS.INSTANCE.ID}"

If you are using access key-based authorization, set the following macros "{$AWS.ACCESS.KEY.ID}", "{$AWS.SECRET.ACCESS.KEY}"

For more information about manage access keys, see [official documentation](https://docs.aws.amazon.com/general/latest/gr/aws-sec-cred-types.html#access-keys-and-secret-access-keys)

Also, see the Macros section for a list of macros used for LLD filters.

Additional information about metrics and used API methods:
* Full metrics list related to RDS: https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/rds-metrics.html
* Full metrics list related to Amazon Aurora: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/Aurora.AuroraMySQL.Monitoring.Metrics.html#Aurora.AuroraMySQL.Monitoring.Metrics.instances
* DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AWS.PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$AWS.ACCESS.KEY.ID}|<p>Access key ID.</p>||
|{$AWS.SECRET.ACCESS.KEY}|<p>Secret access key.</p>||
|{$AWS.REGION}|<p>Amazon RDS Region code.</p>|`us-west-1`|
|{$AWS.AUTH_TYPE}|<p>Authorization method. Possible values: role_base, access_key.</p>|`role_base`|
|{$AWS.RDS.INSTANCE.ID}|<p>RDS DB Instance identifier.</p>||
|{$AWS.RDS.LLD.FILTER.ALARM_SERVICE_NAMESPACE.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.RDS.LLD.FILTER.ALARM_SERVICE_NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$AWS.RDS.LLD.FILTER.ALARM_NAME.MATCHES}|<p>Filter of discoverable alarms by namespace.</p>|`.*`|
|{$AWS.RDS.LLD.FILTER.ALARM_NAME.NOT_MATCHES}|<p>Filter to exclude discovered alarms by namespace.</p>|`CHANGE_IF_NEEDED`|
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
|AWS RDS: Get metrics data|<p>Get instance metrics.</p><p>Full metrics list related to RDS: https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/rds-metrics.html</p><p>Full metrics list related to Amazon Aurora: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/Aurora.AuroraMySQL.Monitoring.Metrics.html#Aurora.AuroraMySQL.Monitoring.Metrics.instances</p>|Script|aws.rds.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Get instance info|<p>Get instance info.</p><p>DescribeDBInstances API method: https://docs.aws.amazon.com/AmazonRDS/latest/APIReference/API_DescribeDBInstances.html</p>|Script|aws.rds.get_instance_info<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS CloudWatch: Get instance alarms data|<p>DescribeAlarms API method: https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_DescribeAlarms.html</p>|Script|aws.rds.get_alarms<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Get instance events data|<p>DescribeEvents API method: https://docs.aws.amazon.com/AmazonRDS/latest/APIReference/API_DescribeEvents.html</p>|Script|aws.rds.get_events<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Get metrics check|<p>Data collection check.</p>|Dependent item|aws.rds.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Get instance info check|<p>Data collection check.</p>|Dependent item|aws.rds.instance_info.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Get alarms check|<p>Data collection check.</p>|Dependent item|aws.rds.alarms.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Get events check|<p>Data collection check.</p>|Dependent item|aws.rds.events.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Class|<p>Contains the name of the compute and memory capacity class of the DB instance.</p>|Dependent item|aws.rds.class<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].DBInstanceClass.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Engine|<p>Database engine.</p>|Dependent item|aws.rds.engine<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..Engine.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Engine version|<p>Indicates the database engine version.</p>|Dependent item|aws.rds.engine.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].EngineVersion.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Status|<p>Specifies the current state of this database.</p><p>All possible status values and their description: https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/accessing-monitoring.html#Overview.DBInstance.Status</p>|Dependent item|aws.rds.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..DBInstanceStatus.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Storage type|<p>Specifies the storage type associated with DB instance.</p>|Dependent item|aws.rds.storage_type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].StorageType.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Create time|<p>Provides the date and time the DB instance was created.</p>|Dependent item|aws.rds.create_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..InstanceCreateTime.first()`</p></li></ul>|
|AWS RDS: Storage: Allocated|<p>Specifies the allocated storage size specified in gibibytes (GiB).</p>|Dependent item|aws.rds.storage.allocated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[*].AllocatedStorage.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Storage: Max allocated|<p>The upper limit in gibibytes (GiB) to which Amazon RDS can automatically scale the storage of the DB instance.</p><p>If limit is not specified returns -1.</p>|Dependent item|aws.rds.storage.max_allocated<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Read replica: State|<p>The status of a read replica. If the instance isn't a read replica, this is blank.</p><p>Boolean value that is true if the instance is operating normally, or false if the instance is in an error state.</p>|Dependent item|aws.rds.read_replica_state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..StatusInfos..Normal.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Read replica: Status|<p>The status of a read replica. If the instance isn't a read replica, this is blank.</p><p>Status of the DB instance. For a StatusType of read replica, the values can be replicating, replication stop point set, replication stop point reached, error, stopped, or terminated.</p>|Dependent item|aws.rds.read_replica_status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..StatusInfos..Status.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS: Swap usage|<p>The amount of swap space used. </p><p>This metric is available for the Aurora PostgreSQL DB instance classes db.t3.medium, db.t3.large, db.r4.large, db.r4.xlarge, db.r5.large, db.r5.xlarge, db.r6g.large, and db.r6g.xlarge. </p><p>For Aurora MySQL, this metric applies only to db.t* DB instance classes.</p><p>This metric is not available for SQL Server.</p>|Dependent item|aws.rds.swap_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "SwapUsage")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Write IOPS|<p>The number of write records generated per second. This is more or less the number of log records generated by the database. These do not correspond to 8K page writes, and do not correspond to network packets sent.</p>|Dependent item|aws.rds.write_iops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "WriteIOPS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Write latency|<p>The average amount of time taken per disk I/O operation.</p>|Dependent item|aws.rds.write_latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "WriteLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Write throughput|<p>The average number of bytes written to persistent storage every second.</p>|Dependent item|aws.rds.write_throughput.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "WriteThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Network: Receive throughput|<p>The incoming (Receive) network traffic on the DB instance, including both customer database traffic and Amazon RDS traffic used for monitoring and replication.</p>|Dependent item|aws.rds.network_receive_throughput.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Burst balance|<p>The percent of General Purpose SSD (gp2) burst-bucket I/O credits available.</p>|Dependent item|aws.rds.burst_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "BurstBalance")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: CPU: Utilization|<p>The percentage of CPU utilization.</p>|Dependent item|aws.rds.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUUtilization")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Credit CPU: Balance|<p>The number of CPU credits that an instance has accumulated, reported at 5-minute intervals.</p><p>You can use this metric to determine how long a DB instance can burst beyond its baseline performance level at a given rate.</p><p>When an instance is running, credits in the CPUCreditBalance don't expire. When the instance stops, the CPUCreditBalance does not persist, and all accrued credits are lost.</p><p></p><p>This metric applies only to db.t2.small and db.t2.medium instances for Aurora MySQL, and to db.t3 instances for Aurora PostgreSQL.</p>|Dependent item|aws.rds.cpu.credit_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUCreditBalance")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Credit CPU: Usage|<p>The number of CPU credits consumed during the specified period, reported at 5-minute intervals.</p><p>This metric measures the amount of time during which physical CPUs have been used for processing instructions by virtual CPUs allocated to the DB instance.</p><p></p><p>This metric applies only to db.t2.small and db.t2.medium instances for Aurora MySQL, and to db.t3 instances for Aurora PostgreSQL</p>|Dependent item|aws.rds.cpu.credit_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CPUCreditUsage")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Connections|<p>The number of client network connections to the database instance.</p><p>The number of database sessions can be higher than the metric value because the metric value doesn't include the following:</p><p></p><p>- Sessions that no longer have a network connection but which the database hasn't cleaned up</p><p>- Sessions created by the database engine for its own purposes</p><p>- Sessions created by the database engine's parallel execution capabilities</p><p>- Sessions created by the database engine job scheduler</p><p>- Amazon Aurora/RDS connections</p>|Dependent item|aws.rds.database_connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Queue depth|<p>The number of outstanding read/write requests waiting to access the disk.</p>|Dependent item|aws.rds.disk_queue_depth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DiskQueueDepth")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: EBS: Byte balance|<p>The percentage of throughput credits remaining in the burst bucket of your RDS database. This metric is available for basic monitoring only.</p><p>To find the instance sizes that support this metric, see the instance sizes with an asterisk (*) in the EBS optimized by default table (https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ebs-optimized.html#current) in Amazon RDS User Guide for Linux Instances.</p>|Dependent item|aws.rds.ebs_byte_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSByteBalance%")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: EBS: IO balance|<p>The percentage of I/O credits remaining in the burst bucket of your RDS database. This metric is available for basic monitoring only.</p><p>To find the instance sizes that support this metric, see the instance sizes with an asterisk (*) in the EBS optimized by default table (https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ebs-optimized.html#current) in Amazon RDS User Guide for Linux Instances.</p>|Dependent item|aws.rds.ebs_io_balance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EBSIOBalance%")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Memory, freeable|<p>The amount of available random access memory.</p><p></p><p>For MariaDB, MySQL, Oracle, and PostgreSQL DB instances, this metric reports the value of the MemAvailable field of /proc/meminfo.</p>|Dependent item|aws.rds.freeable_memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "FreeableMemory")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Storage: Local free|<p>The amount of local storage available, in bytes.</p><p></p><p>Unlike for other DB engines, for Aurora DB instances this metric reports the amount of storage available to each DB instance. </p><p>This value depends on the DB instance class. You can increase the amount of free storage space for an instance by choosing a larger DB instance class for your instance.</p><p>(This doesn't apply to Aurora Serverless v2.)</p>|Dependent item|aws.rds.free_local_storage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "FreeLocalStorage")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Network: Receive throughput|<p>The incoming (receive) network traffic on the DB instance, including both customer database traffic and Amazon RDS traffic used for monitoring and replication.</p><p>For Amazon Aurora: The amount of network throughput received from the Aurora storage subsystem by each instance in the DB cluster.</p>|Dependent item|aws.rds.storage_network_receive_throughput<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Network: Transmit throughput|<p>The outgoing (transmit) network traffic on the DB instance, including both customer database traffic and Amazon RDS traffic used for monitoring and replication.</p><p>For Amazon Aurora: The amount of network throughput sent to the Aurora storage subsystem by each instance in the Aurora MySQL DB cluster.</p>|Dependent item|aws.rds.storage_network_transmit_throughput<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Read IOPS|<p>The average number of disk I/O operations per second. Aurora PostgreSQL-Compatible Edition reports read and write IOPS separately, in 1-minute intervals.</p>|Dependent item|aws.rds.read_iops.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ReadIOPS")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Read latency|<p>The average amount of time taken per disk I/O operation.</p>|Dependent item|aws.rds.read_latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ReadLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Read throughput|<p>The average number of bytes read from disk per second.</p>|Dependent item|aws.rds.read_throughput.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ReadThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Network: Transmit throughput|<p>The outgoing (Transmit) network traffic on the DB instance, including both customer database traffic and Amazon RDS traffic used for monitoring and replication.</p>|Dependent item|aws.rds.network_transmit_throughput.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Network: Throughput|<p>The amount of network throughput both received from and transmitted to clients by each instance in the Aurora MySQL DB cluster, in bytes per second. This throughput doesn't include network traffic between instances in the DB cluster and the cluster volume.</p>|Dependent item|aws.rds.network_throughput.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NetworkThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Storage: Space free|<p>The amount of available storage space.</p>|Dependent item|aws.rds.free_storage_space<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "FreeStorageSpace")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Read IOPS, local storage|<p>The average number of disk read I/O operations to local storage per second. Only applies to Multi-AZ DB clusters.</p>|Dependent item|aws.rds.read_iops_local_storage.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Read latency, local storage|<p>The average amount of time taken per disk I/O operation for local storage. Only applies to Multi-AZ DB clusters.</p>|Dependent item|aws.rds.read_latency_local_storage<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Read throughput, local storage|<p>The average number of bytes read from disk per second for local storage. Only applies to Multi-AZ DB clusters.</p>|Dependent item|aws.rds.read_throughput_local_storage.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Replication: Lag|<p>The amount of time a read replica DB instance lags behind the source DB instance. Applies to MySQL, MariaDB, Oracle, PostgreSQL, and SQL Server read replicas.</p>|Dependent item|aws.rds.replica_lag<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "ReplicaLag")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Write IOPS, local storage|<p>The average number of disk write I/O operations per second on local storage in a Multi-AZ DB cluster.</p>|Dependent item|aws.rds.write_iops_local_storage.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Write latency, local storage|<p>The average amount of time taken per disk I/O operation on local storage in a Multi-AZ DB cluster.</p>|Dependent item|aws.rds.write_latency_local_storage<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Write throughput, local storage|<p>The average number of bytes written to disk per second for local storage.</p>|Dependent item|aws.rds.write_throughput_local_storage.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: SQLServer: Failed agent jobs|<p>The number of failed Microsoft SQL Server Agent jobs during the last minute.</p>|Dependent item|aws.rds.failed_sql_server_agent_jobs_count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Disk: Binlog Usage|<p>The amount of disk space occupied by binary logs on the master. Applies to MySQL read replicas.</p>|Dependent item|aws.rds.bin_log_disk_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "BinLogDiskUsage")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS RDS: Failed to get metrics data||`length(last(/AWS RDS instance by HTTP/aws.rds.metrics.check))>0`|Warning||
|AWS RDS: Failed to get instance data||`length(last(/AWS RDS instance by HTTP/aws.rds.instance_info.check))>0`|Warning||
|AWS RDS: Failed to get alarms data||`length(last(/AWS RDS instance by HTTP/aws.rds.alarms.check))>0`|Warning||
|AWS RDS: Failed to get events data||`length(last(/AWS RDS instance by HTTP/aws.rds.events.check))>0`|Warning||
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
|AWS RDS Alarms: ["{#ALARM_NAME}"]: State reason|<p>An explanation for the alarm state, in text format.</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.rds.alarm.state_reason["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].StateReason.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS Alarms: ["{#ALARM_NAME}"]: State|<p>The state value for the alarm. Possible values: 0 (OK), 1 (INSUFFICIENT_DATA), 2 (ALARM).</p><p>Alarm description:</p><p>{#ALARM_DESCRIPTION}</p>|Dependent item|aws.rds.alarm.state["{#ALARM_NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.AlarmName == "{#ALARM_NAME}")].StateValue.first()`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Instance Alarms discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AWS RDS Alarms: "{#ALARM_NAME}" has 'Alarm' state|<p>Alarm "{#ALARM_NAME}" has 'Alarm' state. <br>Reason: {ITEM.LASTVALUE2}</p>|`last(/AWS RDS instance by HTTP/aws.rds.alarm.state["{#ALARM_NAME}"])=2 and length(last(/AWS RDS instance by HTTP/aws.rds.alarm.state_reason["{#ALARM_NAME}"]))>0`|Average||
|AWS RDS Alarms: "{#ALARM_NAME}" has 'Insufficient data' state||`last(/AWS RDS instance by HTTP/aws.rds.alarm.state["{#ALARM_NAME}"])=1`|Info||

### LLD rule Aurora metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Aurora metrics discovery|<p>Discovery Amazon Aurora metrics.</p><p>https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/Aurora.AuroraMySQL.Monitoring.Metrics.html#Aurora.AuroraMySQL.Monitoring.Metrics.instances</p>|Dependent item|aws.rds.aurora.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Aurora metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS RDS: Row lock time|<p>The total time spent acquiring row locks for InnoDB tables.</p>|Dependent item|aws.rds.row_locktime[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "RowLockTime")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Operations: Select throughput|<p>The average number of select queries per second.</p>|Dependent item|aws.rds.select_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "SelectThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Operations: Select latency|<p>The amount of latency for select queries.</p>|Dependent item|aws.rds.select_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "SelectLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Replication: Lag, max|<p>The maximum amount of lag between the primary instance and each Aurora DB instance in the DB cluster.</p>|Dependent item|aws.rds.aurora_replica_lag.max[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Replication: Lag, min|<p>The minimum amount of lag between the primary instance and each Aurora DB instance in the DB cluster.</p>|Dependent item|aws.rds.aurora_replica_lag.min[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Replication: Lag|<p>For an Aurora replica, the amount of lag when replicating updates from the primary instance.</p>|Dependent item|aws.rds.aurora_replica_lag[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "AuroraReplicaLag")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Buffer Cache hit ratio|<p>The percentage of requests that are served by the buffer cache.</p>|Dependent item|aws.rds.buffer_cache_hit_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Operations: Commit latency|<p>The amount of latency for commit operations.</p>|Dependent item|aws.rds.commit_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CommitLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Operations: Commit throughput|<p>The average number of commit operations per second.</p>|Dependent item|aws.rds.commit_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "CommitThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Deadlocks, rate|<p>The average number of deadlocks in the database per second.</p>|Dependent item|aws.rds.deadlocks.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "Deadlocks")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Engine uptime|<p>The amount of time that the instance has been running.</p>|Dependent item|aws.rds.engine_uptime[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "EngineUptime")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Rollback segment history list length|<p>The undo logs that record committed transactions with delete-marked records. These records are scheduled to be processed by the InnoDB purge operation.</p>|Dependent item|aws.rds.rollback_segment_history_list_length[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Network: Throughput|<p>The amount of network throughput received from and sent to the Aurora storage subsystem by each instance in the Aurora MySQL DB cluster.</p>|Dependent item|aws.rds.storage_network_throughput[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Aurora MySQL metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Aurora MySQL metrics discovery|<p>Discovery Aurora MySQL metrics.</p><p>Storage types:</p><p> aurora (for MySQL 5.6-compatible Aurora)</p><p> aurora-mysql (for MySQL 5.7-compatible and MySQL 8.0-compatible Aurora)</p>|Dependent item|aws.rds.postgresql.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Aurora MySQL metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS RDS: Operations: Delete latency|<p>The amount of latency for delete queries.</p>|Dependent item|aws.rds.delete_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DeleteLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Operations: Delete throughput|<p>The average number of delete queries per second.</p>|Dependent item|aws.rds.delete_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DeleteThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: DML: Latency|<p>The amount of latency for inserts, updates, and deletes.</p>|Dependent item|aws.rds.dml_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DMLLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: DML: Throughput|<p>The average number of inserts, updates, and deletes per second.</p>|Dependent item|aws.rds.dml_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DMLThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: DDL: Latency|<p>The amount of latency for data definition language (DDL) requests - for example, create, alter, and drop requests.</p>|Dependent item|aws.rds.ddl_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DDLLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: DDL: Throughput|<p>The average number of DDL requests per second.</p>|Dependent item|aws.rds.ddl_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "DDLThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Backtrack: Window, actual|<p>The difference between the target backtrack window and the actual backtrack window.</p>|Dependent item|aws.rds.backtrack_window_actual[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Backtrack: Window, alert|<p>The number of times that the actual backtrack window is smaller than the target backtrack window for a given period of time.</p>|Dependent item|aws.rds.backtrack_window_alert[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Transactions: Blocked, rate|<p>The average number of transactions in the database that are blocked per second.</p>|Dependent item|aws.rds.blocked_transactions.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Replication: Binlog lag|<p>The amount of time that a binary log replica DB cluster running on Aurora MySQL-Compatible Edition lags behind the binary log replication source. </p><p>A lag means that the source is generating records faster than the replica can apply them.</p><p>The metric value indicates the following:</p><p></p><p>A high value: The replica is lagging the replication source.</p><p>0 or a value close to 0: The replica process is active and current.</p><p>-1: Aurora can't determine the lag, which can happen during replica setup or when the replica is in an error state</p>|Dependent item|aws.rds.aurora_replication_binlog_lag[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Transactions: Active, rate|<p>The average number of current transactions executing on an Aurora database instance per second.</p><p>By default, Aurora doesn't enable this metric. To begin measuring this value, set innodb_monitor_enable='all' in the DB parameter group for a specific DB instance.</p>|Dependent item|aws.rds.aurora_transactions_active.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Connections: Aborted|<p>The number of client connections that have not been closed properly.</p>|Dependent item|aws.rds.aurora_clients_aborted[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "AbortedClients")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Operations: Insert latency|<p>The amount of latency for insert queries, in milliseconds.</p>|Dependent item|aws.rds.insert_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "InsertLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Operations: Insert throughput|<p>The average number of insert queries per second.</p>|Dependent item|aws.rds.insert_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "InsertThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Login failures, rate|<p>The average number of failed login attempts per second.</p>|Dependent item|aws.rds.login_failures.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "LoginFailures")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Queries, rate|<p>The average number of queries executed per second.</p>|Dependent item|aws.rds.queries.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "Queries")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Resultset cache hit ratio|<p>The percentage of requests that are served by the Resultset cache.</p>|Dependent item|aws.rds.result_set_cache_hit_ratio[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Binary log files, number|<p>The number of binlog files generated.</p>|Dependent item|aws.rds.num_binary_log_files[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "NumBinaryLogFiles")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Binary log files, size|<p>The total size of the binlog files.</p>|Dependent item|aws.rds.sum_binary_log_files[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "SumBinaryLogSize")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Operations: Update latency|<p>The amount of latency for update queries.</p>|Dependent item|aws.rds.update_latency[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "UpdateLatency")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|AWS RDS: Operations: Update throughput|<p>The average number of update queries per second.</p>|Dependent item|aws.rds.update_throughput.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.Label == "UpdateThroughput")].Values.first().first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Instance Events discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Instance Events discovery|<p>Discovery instance events.</p>|Dependent item|aws.rds.events.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Instance Events discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AWS RDS Events: [{#EVENT_CATEGORY}]: {#EVENT_SOURCE_TYPE}/{#EVENT_SOURCE_ID}: Message|<p>Provides the text of this event.</p>|Dependent item|aws.rds.event_message["{#EVENT_CATEGORY}/{#EVENT_SOURCE_TYPE}/{#EVENT_SOURCE_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$[-1]`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|AWS RDS Events: [{#EVENT_CATEGORY}]: {#EVENT_SOURCE_TYPE}/{#EVENT_SOURCE_ID} : Date|<p>Provides the text of this event.</p>|Dependent item|aws.rds.event_date["{#EVENT_CATEGORY}/{#EVENT_SOURCE_TYPE}/{#EVENT_SOURCE_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `$[-1]`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

