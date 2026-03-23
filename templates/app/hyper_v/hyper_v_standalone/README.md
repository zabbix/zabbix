
# Microsoft Hyper-V Standalone by SSH

## Overview

Microsoft Hyper-V is a native hypervisor that allows for the creation and management of virtual machines on Windows systems.
It provides hardware virtualization, enabling multiple operating systems to run simultaneously on a single physical host by isolating them into separate partitions.
Hyper-V is a key component for building scalable and high-availability virtualization environments, including failover clusters.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- OS Name: Microsoft Windows Server 2025 Standard Evaluation
- OS Version: 10.0.26100
- OS Build: 26100
- PS Version: 5.1.26100.7462
- .NET CLR: 4.0.30319.42000
- Mod: Hyper-V: 2.0.0.0
- Mod: CimCmdlets: 1.0.0.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

### Prerequisites & least privilege scope

The solution requires a service account with sufficient privileges to query virtualization subsystems. The exact implementation of these privileges (e.g., via Local Administrators group, Delegated Permissions, or JEA) is at the discretion of the infrastructure administrator, provided the following functional requirements are met.

#### Connectivity & execution

*   **SSH access:** The account must be able to establish an SSH session.
    
*   **PowerShell execution:** The account requires permission to execute PowerShell commands (non-interactive mode).
    

#### WMI & CIM namespaces (read access)

The solution relies heavily on WMI queries. Read access is required for the following namespaces:

*   `root\\cimv2` (Operating System, Processor, Physical Memory, Logical Disk).
    
*   `root\\virtualization\\v2` (Hyper-V VM states, Resource Metering, Configuration).
        
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

```ssh -l <user_name>@<domain_name> <server_name>```

or

```ssh -l <local_user> <server_name>```

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
|{$HYPERV.TRIGGER.VM.STATUS.NORMAL}|<p>Normal state for Hyper-V Virtual Machine status trigger. Supports context `{#VM.NAME}`.</p>|`2`|
|{$HYPERV.TRIGGER.VM.MEMORY.UTILIZATION}|<p>Threshold for Hyper-V Virtual Machine memory utilization trigger. Supports context `{#VM.NAME}`.</p>|`85`|
|{$HYPERV.FILTER.LLD.VM.NAME.MATCHES}|<p>Regular expression to filter Hyper-V Virtual Machines based on their names. Only VMs with names matching this regex will be monitored.</p>|`.*`|
|{$HYPERV.FILTER.LLD.VM.NAME.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Virtual Machines based on their names. VMs with names matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$HYPERV.FILTER.LLD.VM.STATUS.MATCHES}|<p>Regular expression to filter Hyper-V Virtual Machine Status. Only VMs with statuses matching this regex will be monitored. See value mappings for possible status values.</p>|`.*`|
|{$HYPERV.FILTER.LLD.VM.STATUS.NOT_MATCHES}|<p>Regular expression to filter Hyper-V Virtual Machine Status. VMs with statuses matching this regex will be excluded from monitoring. See value mappings for possible status values.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>Collects data about Microsoft Hyper-V via SSH.</p>|SSH agent|ssh.run[hyper-v.get_data,{$HYPERV.SSH.ADDRESS},{$HYPERV.SSH.PORT}]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `error matches ".*timeout error.*"`</p><p>⛔️Custom on fail: Set value to: `{"Message": "Timeout error. Consider increasing the timeout setting.", "Data": {"Perf": {"TotalTime": 0}}}`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"Message": "Error getting data. Check the logs for more details.", "Data": {"Perf": {"TotalTime": 0}}}`</p></li></ul>|
|Get data message|<p>Message from the data retrieval process for Microsoft Hyper-V.</p>|Dependent item|hyperv.get_data.message<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Message`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get data performance|<p>Time taken to retrieve data about Microsoft Hyper-V via SSH.</p>|Dependent item|hyperv.get_data.performance<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.Perf.TotalTime`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Hyper-V Standalone: Data retrieval failure|<p>Trigger fires when there is an error retrieving data from Hyper-V.</p>|`last(/Microsoft Hyper-V Standalone by SSH/hyperv.get_data.message)<>"OK"`|Disaster||

### LLD rule VM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VM discovery|<p>Discovery of Hyper-V Virtual Machines.</p>|Dependent item|hyperv.vm.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for VM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VM [{#VM.NAME}]: Status|<p>Status of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.status[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].State.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM [{#VM.NAME}]: Uptime|<p>Uptime of Hyper-V Virtual Machine `{#VM.NAME}`.</p>|Dependent item|hyperv.vm.uptime[{#VM.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Data.VMs[?(@.Id=='{#VM.ID}')].Uptime.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
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
|Hyper-V Standalone: Virtual machine [{#VM.NAME}] {ITEM.VALUE}|<p>Trigger fires when Hyper-V Virtual Machine `{#VM.NAME}` is not in normal status.</p>|`last(/Microsoft Hyper-V Standalone by SSH/hyperv.vm.status[{#VM.ID}])<>{$HYPERV.TRIGGER.VM.STATUS.NORMAL:"{#VM.NAME}"}`|Average||
|Hyper-V Standalone: Virtual machine [{#VM.NAME}] has rebooted|<p>Trigger fires when the uptime of Hyper-V Virtual Machine `{#VM.NAME}` is less than 15 minutes, which may indicate that the VM has recently rebooted.</p>|`last(/Microsoft Hyper-V Standalone by SSH/hyperv.vm.uptime[{#VM.ID}])<900 and last(/Microsoft Hyper-V Standalone by SSH/hyperv.vm.uptime[{#VM.ID}])<>0`|Info||
|Hyper-V Standalone: Virtual machine [{#VM.NAME}]: High memory utilization|<p>Trigger fires when the memory utilization on Hyper-V Virtual Machine `{#VM.NAME}` exceeds `{$HYPERV.TRIGGER.VM.MEMORY.UTILIZATION:"{#VM.NAME}"}`%.</p>|`last(/Microsoft Hyper-V Standalone by SSH/hyperv.vm.memory.utilization[{#VM.ID}])>{$HYPERV.TRIGGER.VM.MEMORY.UTILIZATION:"{#VM.NAME}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

