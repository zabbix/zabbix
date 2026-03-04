
# Microsoft Hyper-V Failover Cluster by SSH

## Overview

Microsoft Hyper-V is a native hypervisor that allows for the creation and management of virtual machines on Windows systems.
It provides hardware virtualization, enabling multiple operating systems to run simultaneously on a single physical host by isolating them into separate partitions.
Hyper-V is a key component for building scalable and high-availability virtualization environments, including failover clusters.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- OS Name: Microsoft Windows Server 2025 Datacenter Evaluation
- OS Version: 10.0.26100
- OS Build: 26100
- PS Version: 5.1.26100.7462
- .NET CLR: 4.0.30319.42000
- Mod: FailoverClusters: 2.0.0.0
- Mod: Hyper-V: 2.0.0.0
- Mod: CimCmdlets: 1.0.0.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

### Prerequisites & least privilege scope

The solution requires a service account with sufficient privileges to query cluster resources, performance counters, and virtualization subsystems. The exact implementation of these privileges (e.g., via Local Administrators group, Delegated Permissions, or JEA) is at the discretion of the infrastructure administrator, provided the following functional requirements are met.

#### Connectivity & execution

*   **SSH access:** The account must be able to establish an SSH session to the Cluster VIP or individual nodes.
    
*   **PowerShell execution:** The account requires permission to execute PowerShell commands (non-interactive mode).
    
*   **Inter-node communication:** The script utilizes Invoke-Command to query multiple nodes in parallel. The account must allow WinRM connections from the entry node to all other cluster nodes.
    

#### WMI & CIM namespaces (read access)

The solution relies heavily on WMI queries. Read access is required for the following namespaces:

*   `root\\cimv2` (Operating System, Processor, Physical Memory, Logical Disk).
    
*   `root\\virtualization\\v2` (Hyper-V VM states, Resource Metering, Configuration).
    
*   `root\\MSCluster` (Cluster API, Nodes, Resources, Networks, Quorum).
    
*   `root\\wmi` (specific access to `Win32\_PerfFormattedData\_CsvFsPerfProvider\_ClusterCSVFileSystem` for CSV performance counters).
    

#### Storage subsystem

*   **File system access:** Read access to Cluster Shared Volumes paths (e.g., `C:\\ClusterStorage\\\*`) is required to calculate volume utilization.
    
*   **Storage Management API:** Access to query Storage Pools and Volumes (`Get-StoragePool`, `Get-Volume`, or equivalent CIM classes).
    

#### Cluster API

*   **Cluster access:** The account requires explicit permissions to query the Failover Cluster configuration (equivalent to `Grant-ClusterAccess -Read`).

### SSH setup

1.  **Create a Service Account**: Create a user (e.g., `zabbix\_mon`) in the domain or locally on all nodes.
    
2.  **Install the SSH service**:
    

    ```powershell
    # Install SSH service
    Add-WindowsCapability -Online -Name OpenSSH.Server

    # Set autorun and start service
    Start-Service sshd
    Set-Service -Name sshd -StartupType 'Automatic'

    # Open port in firewall
    if (!(Get-NetFirewallRule -Name "OpenSSH-Server-In-TCP" -ErrorAction SilentlyContinue | Select-Object Name, Enabled)) {
        New-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -DisplayName 'OpenSSH Server (sshd)' -Enabled True -Direction Inbound -Protocol TCP -Action Allow -LocalPort 22
    }

    # Enable resource metering for all VMs (needed for statistics without PerfCounters)
    Get-VM -Name * | Enable-VMResourceMetering
    ```
        
3.  **Zabbix configuration**:
    
    *   Ensure timeout is set to at least **30 seconds** (script takes ~15s).
        

### Verification

```ssh -l <user_name>@<domain_name> <cluster_name>```

or

```ssh -l <local_user> <cluster_name>```

### Enable Resource Metering for VMs

To ensure accurate collection of CPU, Disk I/O, and Network Traffic metrics, Resource Metering must be enabled on all Virtual Machines. By default, Hyper-V creates new VMs with this feature disabled.

Recommended approach: create a Scheduled Task on each Hyper-V Node to automatically enable metering for new VMs.

PowerShell Deployment Command (Run as Admin): this command creates a daily task named `HyperV-AutoEnableMetering` that runs under the `SYSTEM` account.

```powershell
$Action = New-ScheduledTaskAction -Execute "powershell.exe" `
    -Argument '-NoProfile -Command "Get-VM | Where-Object ResourceMeteringEnabled -eq $false | Enable-VMResourceMetering"'

$Trigger = New-ScheduledTaskTrigger -Daily -At "04:00"

Register-ScheduledTask -Action $Action -Trigger $Trigger `
    -TaskName "HyperV-AutoEnableMetering" `
    -User "NT AUTHORITY\SYSTEM" `
    -RunLevel Highest `
    -Description "Automatically enables Resource Metering for new VMs to support Zabbix monitoring."
```

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HYPERV.SSH.USER}|<p>SSH username.</p>|`user[@domain]`|
|{$HYPERV.SSH.PASSWORD}|<p>SSH password.</p>|`password`|
|{$HYPERV.SSH.ADDRESS}|<p>SSH address.</p>|`127.0.0.1`|
|{$HYPERV.SSH.PORT}|<p>SSH port.</p>|`22`|
|{$HYPERV.TRIGGER.NODE.MEMORY.UTILIZATION}|<p>Threshold for Hyper-V Cluster Node memory utilization trigger. Supports context `{#NODE.NAME}`.</p>|`85`|
|{$HYPERV.TRIGGER.NODE.CPU.UTILIZATION}|<p>Threshold for Hyper-V Cluster Node CPU utilization trigger. Supports context `{#NODE.NAME}`.</p>|`85`|
|{$HYPERV.TRIGGER.CSV.SPACE.UTILIZATION}|<p>Threshold for Hyper-V Cluster CSV space utilization trigger. Supports context `{#CSV.NAME}`.</p>|`85`|
|{$HYPERV.TRIGGER.VM.STATUS.NORMAL}|<p>Normal state for Hyper-V Virtual Machine status trigger. Supports context `{#VM.NAME}`.</p>|`2`|
|{$HYPERV.TRIGGER.VM.MEMORY.UTILIZATION}|<p>Threshold for Hyper-V Virtual Machine memory utilization trigger. Supports context `{#VM.NAME}`.</p>|`85`|
|{$HYPERV.TRIGGER.POOL.SPACE.UTILIZATION}|<p>Threshold for Hyper-V Pool space utilization trigger. Supports context `{#POOL.NAME}`.</p>|`85`|
|{$HYPERV.FILTER.LLD.NODE.NAME.MATCHES}|<p>Regular expression to filter Hyper-V Cluster Nodes based on their names. Only nodes with names matching this regex will be monitored.</p>|`.*`|
|{$HYPERV.FILTER.LLD.NODE.NAME.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Cluster Nodes based on their names. Nodes with names matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.NODE.STATUS.MATCHES}|<p>Regular expression to filter Hyper-V Cluster Node status. Only nodes with statuses matching this regex will be monitored. See value mappings for possible status values.</p>|`.*`|
|{$HYPERV.FILTER.LLD.NODE.STATUS.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Cluster Node status. Nodes with statuses matching this regex will be excluded from monitoring. See value mappings for possible status values.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.CSV.NAME.MATCHES}|<p>Regular expression to filter Hyper-V Cluster CSVs based on their names. Only CSVs with names matching this regex will be monitored.</p>|`.*`|
|{$HYPERV.FILTER.LLD.CSV.NAME.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Cluster CSVs based on their names. CSVs with names matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.CSV.STATUS.MATCHES}|<p>Regular expression to filter Hyper-V Cluster CSV Status. Only CSVs with statuses matching this regex will be monitored. See value mappings for possible status values.</p>|`.*`|
|{$HYPERV.FILTER.LLD.CSV.STATUS.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Cluster CSV Status. CSVs with statuses matching this regex will be excluded from monitoring. See value mappings for possible status values.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.VM.NAME.MATCHES}|<p>Regular expression to filter Hyper-V Virtual Machines based on their names. Only VMs with names matching this regex will be monitored.</p>|`.*`|
|{$HYPERV.FILTER.LLD.VM.NAME.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Virtual Machines based on their names. VMs with names matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.VM.STATUS.MATCHES}|<p>Regular expression to filter Hyper-V Virtual Machine status. Only VMs with statuses matching this regex will be monitored. See value mappings for possible status values.</p>|`.*`|
|{$HYPERV.FILTER.LLD.VM.STATUS.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Virtual Machine status. VMs with statuses matching this regex will be excluded from monitoring. See value mappings for possible status values.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.POOL.NAME.MATCHES}|<p>Regular expression to filter Hyper-V Pools based on their names. Only Pools with names matching this regex will be monitored.</p>|`.*`|
|{$HYPERV.FILTER.LLD.POOL.NAME.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Pools based on their names. Pools with names matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.POOL.STATUS.MATCHES}|<p>Regular expression to filter Hyper-V Pool Status. Only Pools with statuses matching this regex will be monitored. See value mappings for possible status values.</p>|`.*`|
|{$HYPERV.FILTER.LLD.POOL.STATUS.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Pool Status. Pools with statuses matching this regex will be excluded from monitoring. See value mappings for possible status values.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.DISK.NODE_NAME.MATCHES}|<p>Regular expression to filter Hyper-V Disks based on their node names. Only Disks with node names matching this regex will be monitored.</p>|`.*`|
|{$HYPERV.FILTER.LLD.DISK.NODE_NAME.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Disks based on their node names. Disks with node names matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.DISK.SLOT.MATCHES}|<p>Regular expression to filter Hyper-V Disks based on their slots. Only Disks with slots matching this regex will be monitored.</p>|`.*`|
|{$HYPERV.FILTER.LLD.DISK.SLOT.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Disks based on their slots. Disks with slots matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.DISK.MODEL.MATCHES}|<p>Regular expression to filter Hyper-V Disks based on their models. Only Disks with models matching this regex will be monitored.</p>|`.*`|
|{$HYPERV.FILTER.LLD.DISK.MODEL.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Disks based on their models. Disks with models matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>Collects data about Microsoft Hyper-V Failover Cluster via SSH.</p>|SSH agent|ssh.run[hyper-v.get_data,{$HYPERV.SSH.ADDRESS},{$HYPERV.SSH.PORT}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches ".*timeout error.*"`</p><p>⛔️Custom on fail: Set value to: `{"Message": "Timeout error. Consider increasing the timeout setting.", "Data": {"Perf": {"TotalTime": 0}, "Cluster": {"QuorumState": -1}}}`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"Message": "Error getting data. Check the logs for more details.", "Data": {"Perf": {"TotalTime": 0}, "Cluster": {"QuorumState": -1}}}`</p></li></ul>|
|Get data message|<p>Message from the data retrieval process for Microsoft Hyper-V Failover Cluster.</p>|Dependent item|hyperv.get_data.message<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Message`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get data performance|<p>Time taken to retrieve data about Microsoft Hyper-V Failover Cluster via SSH.</p>|Dependent item|hyperv.get_data.performance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Perf.TotalTime`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Cluster quorum status|<p>Status of the Hyper-V Failover Cluster Quorum.</p>|Dependent item|hyperv.cluster.quorum.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Cluster.QuorumState`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster name|<p>Name of the Hyper-V Failover Cluster.</p>|Dependent item|hyperv.cluster.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Cluster.Name`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Hyper-V Failover Cluster: Data retrieval failure|<p>Trigger fires when there is an error retrieving data from Hyper-V Failover Cluster.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.get_data.message)<>"OK"`|Disaster||
|Hyper-V Failover Cluster: Cluster Quorum {ITEM.VALUE}|<p>Trigger fires when the Hyper-V Failover Cluster Quorum is offline (lost quorum).</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.quorum.status)=-1 or last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.quorum.status)>=3`|High||
|Hyper-V Failover Cluster: Cluster Quorum {ITEM.VALUE}|<p>Trigger fires when the Hyper-V Failover Cluster Quorum is not online.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.quorum.status)<>2`|Info|**Depends on**:<br><ul><li>Hyper-V Failover Cluster: Cluster Quorum {ITEM.VALUE}</li></ul>|

### LLD rule Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node discovery|<p>Discovery of Hyper-V Cluster Nodes.</p>|Dependent item|hyperv.cluster.node.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Nodes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: Status|<p>Status of Hyper-V Cluster Node `{#NODE.NAME}`.</p>|Dependent item|hyperv.cluster.node.status[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Nodes[?(@.Id=='{#NODE.ID}')].State.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Response time|<p>Response time of Hyper-V Cluster Node `{#NODE.NAME}`.</p>|Dependent item|hyperv.cluster.node.response_time[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Nodes[?(@.Id=='{#NODE.ID}')].ResponseTime.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Node [{#NODE.NAME}]: Free memory|<p>Free memory of Hyper-V Cluster Node `{#NODE.NAME}`.</p>|Dependent item|hyperv.cluster.node.memory.free[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Nodes[?(@.Id=='{#NODE.ID}')].FreeMem.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NODE.NAME}]: Total memory|<p>Total memory of Hyper-V Cluster Node `{#NODE.NAME}`.</p>|Dependent item|hyperv.cluster.node.memory.total[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Nodes[?(@.Id=='{#NODE.ID}')].TotalMem.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Memory utilization|<p>Memory utilization of Hyper-V Cluster Node `{#NODE.NAME}`.</p>|Dependent item|hyperv.cluster.node.memory.utilization[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Nodes[?(@.Id=='{#NODE.ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NODE.NAME}]: CPU utilization|<p>CPU utilization of Hyper-V Cluster Node `{#NODE.NAME}`.</p>|Dependent item|hyperv.cluster.node.cpu.utilization[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Nodes[?(@.Id=='{#NODE.ID}')].CPU.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Node discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Hyper-V Failover Cluster: Cluster node [{#NODE.NAME}] {ITEM.VALUE}|<p>Trigger fires when the Hyper-V Cluster Node `{#NODE.NAME}` is down.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.node.status[{#NODE.ID}])=1 or last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.node.status[{#NODE.ID}])=-1`|High||
|Hyper-V Failover Cluster: Cluster node [{#NODE.NAME}] {ITEM.VALUE}|<p>Trigger fires when the Hyper-V Cluster Node `{#NODE.NAME}` is not up.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.node.status[{#NODE.ID}])<>0`|Info|**Depends on**:<br><ul><li>Hyper-V Failover Cluster: Cluster node [{#NODE.NAME}] {ITEM.VALUE}</li></ul>|
|Hyper-V Failover Cluster: Cluster node [{#NODE.NAME}]: High memory utilization|<p>Trigger fires when the memory utilization on Hyper-V Cluster Node `{#NODE.NAME}` exceeds `{$HYPERV.TRIGGER.NODE.MEMORY.UTILIZATION:"{#NODE.NAME}"}`%.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.node.memory.utilization[{#NODE.ID}])>{$HYPERV.TRIGGER.NODE.MEMORY.UTILIZATION:"{#NODE.NAME}"}`|Warning||
|Hyper-V Failover Cluster: Cluster node [{#NODE.NAME}]: High CPU utilization|<p>Trigger fires when the CPU utilization on Hyper-V Cluster Node `{#NODE.NAME}` exceeds `{$HYPERV.TRIGGER.NODE.CPU.UTILIZATION:"{#NODE.NAME}"}`%.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.node.cpu.utilization[{#NODE.ID}])>{$HYPERV.TRIGGER.NODE.CPU.UTILIZATION:"{#NODE.NAME}"}`|Warning||

### LLD rule CSV discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CSV discovery|<p>Discovery of Hyper-V Cluster CSVs.</p>|Dependent item|hyperv.cluster.csv.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Storage`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for CSV discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CSV [{#CSV.NAME}]: Status|<p>Status of Hyper-V Cluster CSV `{#CSV.NAME}`.</p>|Dependent item|hyperv.cluster.csv.status[{#CSV.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Storage[?(@.Id=='{#CSV.ID}')].State.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CSV [{#CSV.NAME}]: Free space|<p>Free space of Hyper-V Cluster CSV `{#CSV.NAME}`.</p>|Dependent item|hyperv.cluster.csv.space.free[{#CSV.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Storage[?(@.Id=='{#CSV.ID}')].Free.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CSV [{#CSV.NAME}]: Total space|<p>Total space of Hyper-V Cluster CSV `{#CSV.NAME}`.</p>|Dependent item|hyperv.cluster.csv.space.total[{#CSV.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Storage[?(@.Id=='{#CSV.ID}')].Total.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CSV [{#CSV.NAME}]: Space utilization|<p>Space utilization of Hyper-V Cluster CSV `{#CSV.NAME}`.</p>|Dependent item|hyperv.cluster.csv.space.utilization[{#CSV.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Storage[?(@.Id=='{#CSV.ID}')].UsedPct.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CSV [{#CSV.NAME}]: Read speed|<p>Read speed of Hyper-V Cluster CSV `{#CSV.NAME}`, aggregated from nodes.</p>|Dependent item|hyperv.cluster.csv.speed.read[{#CSV.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Storage[?(@.Id=='{#CSV.ID}')].ReadBps.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CSV [{#CSV.NAME}]: Write speed|<p>Write speed of Hyper-V Cluster CSV `{#CSV.NAME}`, aggregated from nodes.</p>|Dependent item|hyperv.cluster.csv.speed.write[{#CSV.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Storage[?(@.Id=='{#CSV.ID}')].WriteBps.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CSV [{#CSV.NAME}]: Path|<p>Path of Hyper-V Cluster CSV `{#CSV.NAME}`.</p>|Dependent item|hyperv.cluster.csv.path[{#CSV.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Storage[?(@.Id=='{#CSV.ID}')].Path.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for CSV discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Hyper-V Failover Cluster: Cluster CSV [{#CSV.NAME}] {ITEM.VALUE}|<p>Trigger fires when the Hyper-V Cluster CSV `{#CSV.NAME}` indicates an error `{ITEM.VALUE}`.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.csv.status[{#CSV.ID}])=-1 or last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.csv.status[{#CSV.ID}])>=3`|High||
|Hyper-V Failover Cluster: Cluster CSV [{#CSV.NAME}] {ITEM.VALUE}|<p>Trigger fires when the Hyper-V Cluster CSV `{#CSV.NAME}` is not online.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.csv.status[{#CSV.ID}])<>2`|Info|**Depends on**:<br><ul><li>Hyper-V Failover Cluster: Cluster CSV [{#CSV.NAME}] {ITEM.VALUE}</li></ul>|
|Hyper-V Failover Cluster: Cluster CSV [{#CSV.NAME}]: High space utilization|<p>Trigger fires when the space utilization on Hyper-V Cluster CSV `{#CSV.NAME}` exceeds `{$HYPERV.TRIGGER.CSV.SPACE.UTILIZATION:"{#CSV.NAME}"}`%.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.cluster.csv.space.utilization[{#CSV.ID}])>{$HYPERV.TRIGGER.CSV.SPACE.UTILIZATION:"{#CSV.NAME}"}`|Warning||

### LLD rule VM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VM discovery|<p>Discovery of Hyper-V Virtual Machines.</p>|Dependent item|hyperv.vm.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for VM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VM [{#VM.NAME}]: Status|<p>Status of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.status[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].State.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM [{#VM.NAME}]: Uptime|<p>Uptime of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.uptime[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].Uptime.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VM [{#VM.NAME}]: Node owner|<p>Node owner of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.owner[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].Owner.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM [{#VM.NAME}]: CPU utilization|<p>CPU utilization of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.cpu.utilization[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].CPU.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1000000`</p></li></ul>|
|VM [{#VM.NAME}]: Used memory|<p>Used memory of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.memory.used[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].UsedMem.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VM [{#VM.NAME}]: Total memory|<p>Total memory of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.memory.total[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].TotalMem.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM [{#VM.NAME}]: Memory utilization|<p>Memory utilization of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.memory.utilization[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|VM [{#VM.NAME}]: Network in (bits per second)|<p>Network incoming traffic of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.net.in[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].NetIn.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|VM [{#VM.NAME}]: Network out (bits per second)|<p>Network outgoing traffic of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.net.out[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].NetOut.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|VM [{#VM.NAME}]: Disk read (bytes per second)|<p>Disk read speed of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.disk.read[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].DiskRead.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li><li>Change per second</li></ul>|
|VM [{#VM.NAME}]: Disk write (bytes per second)|<p>Disk write speed of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.disk.write[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].DiskWrite.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for VM discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Hyper-V Failover Cluster: Virtual machine [{#VM.NAME}] {ITEM.VALUE}|<p>Trigger fires when Hyper-V Virtual Machine `{#VM.NAME}` is not in normal state.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.vm.status[{#VM.ID}])<>{$HYPERV.TRIGGER.VM.STATUS.NORMAL:"{#VM.NAME}"}`|Average||
|Hyper-V Failover Cluster: Virtual machine [{#VM.NAME}] has rebooted|<p>Trigger fires when the uptime of Hyper-V Virtual Machine `{#VM.NAME}` is less than 15 minutes, which may indicate that the VM has recently rebooted.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.vm.uptime[{#VM.ID}])<900 and last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.vm.uptime[{#VM.ID}])<>0`|Info||
|Hyper-V Failover Cluster: Virtual machine [{#VM.NAME}] owner changed|<p>Trigger fires when the owner node of Hyper-V Virtual Machine `{#VM.NAME}` changes.</p>|`change(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.vm.owner[{#VM.ID}])`|Info||
|Hyper-V Failover Cluster: Virtual machine [{#VM.NAME}]: High memory utilization|<p>Trigger fires when the memory utilization on Hyper-V Virtual Machine `{#VM.NAME}` exceeds `{$HYPERV.TRIGGER.VM.MEMORY.UTILIZATION:"{#VM.NAME}"}`%.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.vm.memory.utilization[{#VM.ID}])>{$HYPERV.TRIGGER.VM.MEMORY.UTILIZATION:"{#VM.NAME}"}`|Warning||

### LLD rule S2D pool discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|S2D pool discovery|<p>Discovery of Hyper-V S2D Pools.</p>|Dependent item|hyperv.s2d.pool.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.S2D.Pools`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for S2D pool discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|S2D Pool [{#POOL.NAME}]: Status|<p>Status of Hyper-V S2D Pool `{#POOL.NAME}`.</p>|Dependent item|hyperv.s2d.pool.status[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.S2D.Pools[?(@.Name=='{#POOL.NAME}')].Status.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|S2D Pool [{#POOL.NAME}]: Allocated space|<p>Allocated space of Hyper-V S2D Pool `{#POOL.NAME}`.</p>|Dependent item|hyperv.s2d.pool.allocated[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|S2D Pool [{#POOL.NAME}]: Health status|<p>Health status of Hyper-V S2D Pool `{#POOL.NAME}`.</p>|Dependent item|hyperv.s2d.pool.health[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.S2D.Pools[?(@.Name=='{#POOL.NAME}')].Health.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|S2D Pool [{#POOL.NAME}]: Total space|<p>Total space of Hyper-V S2D Pool `{#POOL.NAME}`.</p>|Dependent item|hyperv.s2d.pool.total[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.S2D.Pools[?(@.Name=='{#POOL.NAME}')].Total.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|S2D Pool [{#POOL.NAME}]: Free space|<p>Free space of Hyper-V S2D Pool `{#POOL.NAME}`.</p>|Dependent item|hyperv.s2d.pool.free[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.S2D.Pools[?(@.Name=='{#POOL.NAME}')].Free.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|S2D Pool [{#POOL.NAME}]: Space utilization|<p>Space utilization of Hyper-V S2D Pool `{#POOL.NAME}`.</p>|Dependent item|hyperv.s2d.pool.utilization[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.S2D.Pools[?(@.Name=='{#POOL.NAME}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for S2D pool discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Hyper-V Failover Cluster: S2D Pool [{#POOL.NAME}] not ready|<p>Trigger fires when Hyper-V S2D Pool `{#POOL.NAME}` is not ready.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.s2d.pool.status[{#POOL.NAME}])<>2`|High||
|Hyper-V Failover Cluster: S2D Pool [{#POOL.NAME}]: Unhealthy|<p>Trigger fires when Hyper-V S2D Pool `{#POOL.NAME}` is unhealthy.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.s2d.pool.health[{#POOL.NAME}])<>0`|High||
|Hyper-V Failover Cluster: S2D Pool [{#POOL.NAME}]: High space utilization|<p>Trigger fires when the space utilization on Hyper-V S2D Pool `{#POOL.NAME}` exceeds `{$HYPERV.TRIGGER.POOL.SPACE.UTILIZATION:"{#POOL.NAME}"}`%.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.s2d.pool.utilization[{#POOL.NAME}])>{$HYPERV.TRIGGER.POOL.SPACE.UTILIZATION:"{#POOL.NAME}"}`|Warning||

### LLD rule S2D physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|S2D physical disk discovery|<p>Discovery of Hyper-V S2D Physical Disks.</p>|Dependent item|hyperv.s2d.disk.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.S2D.PhysicalDisks`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for S2D physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|S2D Physical Disk [{#DISK.NODE}/{#DISK.SLOT}/{#DISK.MODEL}]: Size|<p>Size of Hyper-V S2D Physical Disk `{#DISK.ID}` on Node `{#DISK.NODE}`.</p>|Dependent item|hyperv.s2d.disk.size[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.S2D.PhysicalDisks[?(@.Id=='{#DISK.ID}')].Size.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|S2D Physical Disk [{#DISK.NODE}/{#DISK.SLOT}/{#DISK.MODEL}]: Type|<p>Type of Hyper-V S2D Physical Disk `{#DISK.ID}` on Node `{#DISK.NODE}`.</p>|Dependent item|hyperv.s2d.disk.type[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|S2D Physical Disk [{#DISK.NODE}/{#DISK.SLOT}/{#DISK.MODEL}]: Health status|<p>Health status of Hyper-V S2D Physical Disk `{#DISK.ID}` on Node `{#DISK.NODE}`.</p>|Dependent item|hyperv.s2d.disk.health[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|S2D Physical Disk [{#DISK.NODE}/{#DISK.SLOT}/{#DISK.MODEL}]: Operational status|<p>Operational status of Hyper-V S2D Physical Disk `{#DISK.ID}` on Node `{#DISK.NODE}`.</p>|Dependent item|hyperv.s2d.disk.status[{#DISK.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for S2D physical disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Hyper-V Failover Cluster: S2D Physical Disk [{#DISK.NODE}/{#DISK.SLOT}/{#DISK.MODEL}]: Unhealthy|<p>Trigger fires when Hyper-V S2D Physical Disk `{#DISK.ID}` on Node `{#DISK.NODE}` is unhealthy.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.s2d.disk.health[{#DISK.ID}])<>0`|High||
|Hyper-V Failover Cluster: S2D Physical Disk [{#DISK.NODE}/{#DISK.SLOT}/{#DISK.MODEL}]: Not ready|<p>Trigger fires when Hyper-V S2D Physical Disk `{#DISK.ID}` on Node `{#DISK.NODE}` is not ready.</p>|`last(/Microsoft Hyper-V Failover Cluster by SSH/hyperv.s2d.disk.status[{#DISK.ID}])<>2`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

