
# Windows by Zabbix agent

## Overview

This is an official Windows template. It requires Zabbix agent 8.0 or newer.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Windows 7 and newer.
- Windows Server 2008 R2 and newer.

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on Windows OS according to Zabbix documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.TIMEOUT}|<p>Timeout after which agent is considered unavailable.</p>|`5m`|
|{$CPU.UTIL.CRIT}|<p>The critical threshold of the CPU utilization expressed in %.</p>|`90`|
|{$CPU.INTERRUPT.CRIT.MAX}|<p>The critical threshold of the % Interrupt Time counter.</p>|`50`|
|{$CPU.PRIV.CRIT.MAX}|<p>The threshold of the % Privileged Time counter.</p>|`30`|
|{$CPU.QUEUE.CRIT.MAX}|<p>The threshold of the Processor Queue Length counter.</p>|`3`|
|{$MEMORY.UTIL.MAX}|<p>The warning threshold of the Memory util item.</p>|`90`|
|{$SWAP.PFREE.MIN.WARN}|<p>The warning threshold of the minimum free swap.</p>|`20`|
|{$MEM.PAGE_TABLE_CRIT.MIN}|<p>The warning threshold of the Free System Page Table Entries counter.</p>|`5000`|
|{$MEM.PAGE_SEC.CRIT.MAX}|<p>The warning threshold of the Memory Pages/sec counter.</p>|`1000`|
|{$FSCONTROL}|<p>Macro for the filesystem space triggers. Can be used with the filesystem name as context.</p>|`1`|
|{$VFS.FS.PUSED.MAX.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`80`|
|{$VFS.FS.PUSED.MAX.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`90`|
|{$VFS.FS.FSNAME.MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$VFS.FS.FSTYPE.MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$VFS.FS.FSTYPE.NOT_MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$VFS.FS.FSDRIVETYPE.MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`fixed`|
|{$VFS.FS.FSDRIVETYPE.NOT_MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$VFS.DEV.UTIL.MAX.WARN}|<p>The warning threshold of disk time utilization in percent.</p>|`95`|
|{$VFS.DEV.READ.AWAIT.WARN}|<p>Disk read average response time (in s) before the trigger fires.</p>|`0.02`|
|{$VFS.DEV.WRITE.AWAIT.WARN}|<p>Disk write average response time (in s) before the trigger fires.</p>|`0.02`|
|{$VFS.DEV.DEVNAME.MATCHES}|<p>Used in physical disk discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$VFS.DEV.DEVNAME.NOT_MATCHES}|<p>Used in physical disk discovery. Can be overridden on the host or linked template level.</p>|`_Total`|
|{$IFCONTROL}|<p>Macro for the interface operational state for the "link down" trigger. Can be used with interface name as context.</p>|`1`|
|{$IF.UTIL.MAX}|<p>Used as a threshold in the interface utilization trigger.</p>|`90`|
|{$IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|
|{$NET.IF.IFNAME.MATCHES}|<p>Used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFALIAS.MATCHES}|<p>Used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}|<p>Used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFDESCR.MATCHES}|<p>Used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}|<p>Used in Network interface discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SERVICE.NAME.MATCHES}|<p>Used in Service discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$SERVICE.NAME.NOT_MATCHES}|<p>Used in Service discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$SERVICE.STARTUPNAME.MATCHES}|<p>Used in Service discovery. Can be overridden on the host or linked template level.</p>|`^(?:automatic\|automatic delayed)$`|
|{$SERVICE.STARTUPNAME.NOT_MATCHES}|<p>Used in Service discovery. Can be overridden on the host or linked template level.</p>|`^(?:manual\|disabled)$`|
|{$SERVICE.STARTUPTRIGGER.MATCHES}|<p>Used in Service discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$SERVICE.STARTUPTRIGGER.NOT_MATCHES}|<p>Used in Service discovery. Can be overridden on the host or linked template level.</p>|`^1$`|
|{$SERVICE.FORBIDDEN.MATCHES}|<p>Used in Service discovery to list the services that must not be running. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SERVICE.STARTUP.GRACE}|<p>The grace period after a system boot during which the service state is not checked. Can be used with the service name as context.</p>|`10m`|
|{$WINDOWS.TASK.NAME.MATCHES}|<p>Used in Windows scheduled tasks discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$WINDOWS.TASK.NAME.NOT_MATCHES}|<p>Used in Windows scheduled tasks discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$WINDOWS.TASK.PATH.MATCHES}|<p>Used in Windows scheduled tasks discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$WINDOWS.TASK.PATH.NOT_MATCHES}|<p>Used in Windows scheduled tasks discovery. Can be overridden on the host or linked template level.</p>|`^\s$`|
|{$WINDOWS.TASK.CONTROL}|<p>Macro for the scheduled task state trigger. Can be used with the task name as context.</p>|`1`|
|{$WINDOWS.SECURITY.SECUREBOOT.IGNORE}|<p>Set to "1" to suppress the "SecureBoot is disabled" trigger on hosts that legitimately run without UEFI Secure Boot (e.g. legacy BIOS/CSM).</p>|`0`|
|{$WINDOWS.SECURITY.ACTIVATION.GRACE.MIN.WARN}|<p>The minimum remaining Windows activation grace period before the trigger fires.</p>|`30d`|
|{$WINDOWS.SECURITY.DEFENDER.SIGNATURE.MAX.WARN}|<p>The maximum age of Microsoft Defender signatures before the trigger fires.</p>|`7d`|
|{$WINDOWS.SECURITY.DEFENDER.FULLSCAN.MAX.WARN}|<p>The maximum age of the last Microsoft Defender full scan before the trigger fires.</p>|`30d`|
|{$WINDOWS.UPDATES.STALE.MAX.WARN}|<p>The maximum number of days since the last installed update (KB) before the trigger fires.</p>|`45`|
|{$WINDOWS.UPDATES.SECURITY.STALE.MAX.WARN}|<p>The maximum number of days since the last installed security update (KB) before the trigger fires.</p>|`60`|
|{$WINDOWS.SECURITY.FW.PROFILE.MATCHES}|<p>Filter for the Windows Firewall profiles to discover, by profile name.</p>|`^(Domain\|Private\|Public)$`|
|{$WINDOWS.SECURITY.FW.PROFILE.NOT_MATCHES}|<p>Used in Windows Firewall profiles discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$WINDOWS.SECURITY.BITLOCKER.FSNAME.MATCHES}|<p>Filter for the volumes to discover for BitLocker, by drive letter (e.g. "C:").</p>|`^[A-Za-z]:`|
|{$WINDOWS.SECURITY.BITLOCKER.FSNAME.NOT_MATCHES}|<p>Used in BitLocker volume discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$WINDOWS.SECURITY.BITLOCKER.ENFORCE.MATCHES}|<p>Volumes (by drive letter, e.g. "C:") on which BitLocker is enforced. The "BitLocker is not enabled" trigger fires only for matching volumes.</p>|`^C:$`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU DPC time|<p>Processor DPC time is the time that a single processor spent receiving and servicing deferred procedure calls (DPCs). DPCs are interrupts that run at a lower priority than standard interrupts. `% DPC Time` is a component of `% Privileged Time` because DPCs are executed in privileged mode. If a high `% DPC Time` is sustained, there may be a processor bottleneck or an application or hardware related issue that can significantly diminish overall system performance.</p>|Zabbix agent|perf_counter_en["\Processor Information(_total)\% DPC Time"]|
|CPU interrupt time|<p>The processor information `% Interrupt Time` counter indicates how much time the processor spends handling hardware interrupts during sample intervals. It reflects the activity of devices like the system clock, mouse, disk drivers, and network cards. A value above 20% suggests possible hardware issues.</p>|Zabbix agent|perf_counter_en["\Processor Information(_total)\% Interrupt Time"]|
|CPU privileged time|<p>The processor information `% Privileged Time` counter shows the percent of time that the processor is spent executing in Kernel (or Privileged) mode. Privileged mode includes services interrupts inside Interrupt Service Routines (ISRs), executing Deferred Procedure Calls (DPCs), Device Driver calls and other kernel-mode functions of the Windows Operating System.</p>|Zabbix agent|perf_counter_en["\Processor Information(_total)\% Privileged Time"]|
|CPU queue length|<p>The Processor Queue Length shows the number of threads that are observed as delayed in the processor Ready Queue and are waiting to be executed.</p>|Zabbix agent|perf_counter_en["\System\Processor Queue Length"]|
|CPU user time|<p>The processor information `% User Time` counter shows the percent of time that the processor(s) is spent executing in User mode.</p>|Zabbix agent|perf_counter_en["\Processor Information(_total)\% User Time"]|
|CPU utilization|<p>CPU utilization expressed in %.</p>|Zabbix agent|system.cpu.util|
|Cache bytes|<p>Cache Bytes is the sum of the Memory\\System Cache Resident Bytes, Memory\\System Driver Resident Bytes, Memory\\System Code Resident Bytes, and Memory\\Pool Paged Resident Bytes counters. This counter displays the last observed value only; it is not an average.</p>|Zabbix agent|perf_counter_en["\Memory\Cache Bytes"]|
|Context switches per second|<p>Context Switches/sec is the combined rate at which all processors on the computer are switched from one thread to another.</p><p>Context switches occur when a running thread voluntarily relinquishes the processor, is preempted by a higher priority ready thread, or switches between user-mode and privileged (kernel) mode to use an Executive or subsystem service.</p><p>It is the sum of Thread\\Context Switches/sec for all threads running on all processors in the computer and is measured in numbers of switches.</p><p>There are context switch counters on the System and Thread objects. This counter displays the difference between the values observed in the last two samples, divided by the duration of the sample interval.</p>|Zabbix agent|perf_counter_en["\System\Context Switches/sec"]|
|Free swap space|<p>The free space of the swap volume/file expressed in bytes.</p>|Zabbix agent|system.swap.size[,free]|
|Free swap space in %|<p>The free space of the swap volume/file expressed in %.</p>|Dependent item|system.swap.pfree<p>**Preprocessing**</p><ul><li><p>JavaScript: `return (100 - value)`</p></li></ul>|
|Free system page table entries|<p>This indicates the number of page table entries not currently in use by the system. If the number is less than 5,000, there may be a memory leak or you running out of memory.</p>|Zabbix agent|perf_counter_en["\Memory\Free System Page Table Entries"]|
|Get filesystems|<p>The `vfs.fs.get` key acquires raw information set about the filesystems. Later to be extracted by preprocessing in dependent items.</p>|Zabbix agent|vfs.fs.get|
|Host name of Zabbix agent running||Zabbix agent|agent.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Memory page faults per second|<p>Page Faults/sec is the average number of pages faulted per second. It is measured in number of pages faulted per second because only one page is faulted in each fault operation, hence this is also equal to the number of page fault operations. This counter includes both hard faults (those that require disk access) and soft faults (where the faulted page is found elsewhere in physical memory.) Most processors can handle large numbers of soft faults without significant consequence. However, hard faults, which require disk access, can cause significant delays.</p>|Zabbix agent|perf_counter_en["\Memory\Page Faults/sec"]|
|Memory pages per second|<p>This measures the rate at which pages are read from or written to disk to resolve hard page faults.</p><p>If the value is greater than 1,000, as a result of excessive paging, there may be a memory leak.</p>|Zabbix agent|perf_counter_en["\Memory\Pages/sec"]|
|Memory pool non-paged|<p>This measures the size, in bytes, of the non-paged pool. This is an area of system memory for objects that cannot be written to disk but instead must remain in physical memory as long as they are allocated.</p><p>There is a possible memory leak if the value is greater than 175MB (or 100MB with the /3GB switch). Consequently, Event ID 2019 is recorded in the system event log.</p>|Zabbix agent|perf_counter_en["\Memory\Pool Nonpaged Bytes"]|
|Memory utilization|<p>Memory utilization in %.</p>|Zabbix agent|vm.memory.size[pused]|
|Number of cores|<p>The number of logical processors available on the computer.</p>|Zabbix agent|wmi.get[root/cimv2,"Select NumberOfLogicalProcessors from Win32_ComputerSystem"]<p>**Preprocessing**</p><ul><li><p>Regular expression: `(\d+) \1`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Number of processes|<p>The number of processes.</p>|Zabbix agent|proc.num[]|
|Number of threads|<p>The number of threads used by all running processes.</p>|Zabbix agent|perf_counter_en["\System\Threads"]|
|Operating system||Zabbix agent|system.sw.os<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Operating system architecture|<p>The architecture of the operating system.</p>|Zabbix agent|system.sw.arch<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System description|<p>System description of the host.</p>|Zabbix agent|system.uname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System local time|<p>The local system time of the host.</p>|Zabbix agent|system.localtime[local]|
|System name|<p>The host name of the system.</p>|Zabbix agent|system.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Total memory|<p>Total memory expressed in bytes.</p>|Zabbix agent|vm.memory.size[total]|
|Total swap space|<p>The total space of the swap volume/file expressed in bytes.</p>|Zabbix agent|system.swap.size[,total]|
|Uptime|<p>The system uptime expressed in the following format: "N days, hh:mm:ss".</p>|Zabbix agent|system.uptime|
|Used memory|<p>Used memory in bytes.</p>|Zabbix agent|vm.memory.size[used]|
|Used swap space in %|<p>The used space of swap volume/file in percent.</p>|Zabbix agent|perf_counter_en["\Paging file(_Total)\% Usage"]|
|Version of Zabbix agent running||Zabbix agent|agent.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows: Network interfaces WMI get|<p>Raw data of `win32_networkadapter.`</p>|Zabbix agent|wmi.getall[root\cimv2,"select Name,Description,NetConnectionID,Speed,AdapterTypeId,NetConnectionStatus,GUID from win32_networkadapter where PhysicalAdapter=True and NetConnectionStatus>0"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Zabbix agent availability|<p>Used for monitoring the availability status of the agent.</p>|Zabbix internal|zabbix[host,agent,available]|
|Zabbix agent ping|<p>The agent always returns "1" for this item. May be used in combination with `nodata()` for the availability check.</p>|Zabbix agent|agent.ping|
|Windows TPM: Get info|<p>Raw TPM (Trusted Platform Module) data from the `Win32_Tpm` WMI class.</p><p>The classic Windows agent does not invoke WMI methods, so the current state is read from the `*_InitialValue` properties captured at the most recent provisioning/clear event; on a stable system this matches the actual state.</p>|Zabbix agent|wmi.getall[root\CIMV2\Security\MicrosoftTpm,"select IsEnabled_InitialValue,IsActivated_InitialValue,IsOwned_InitialValue,SpecVersion,ManufacturerVersion from Win32_Tpm"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows TPM: Presence|<p>Whether a TPM chip is present on the host (derived from the number of `Win32_Tpm` instances returned by the master item).</p>|Dependent item|tpm.presence<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows TPM: Enabled|<p>Whether the TPM is enabled (from `IsEnabled_InitialValue`).</p>|Dependent item|tpm.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].IsEnabled_InitialValue`</p><p>⛔️Custom on fail: Set value to: `False`</p></li><li>Boolean to decimal</li></ul>|
|Windows TPM: Activated|<p>Whether the TPM is activated (from `IsActivated_InitialValue`).</p>|Dependent item|tpm.activated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].IsActivated_InitialValue`</p><p>⛔️Custom on fail: Set value to: `False`</p></li><li>Boolean to decimal</li></ul>|
|Windows TPM: Owned|<p>Whether the TPM is owned (from `IsOwned_InitialValue`).</p>|Dependent item|tpm.owned<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].IsOwned_InitialValue`</p><p>⛔️Custom on fail: Set value to: `False`</p></li><li>Boolean to decimal</li></ul>|
|Windows TPM: Spec version|<p>The TPM specification version (the leading part of `SpecVersion`, e.g. "2.0").</p>|Dependent item|tpm.spec.version<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows TPM: Manufacturer version|<p>The TPM firmware (manufacturer) version (from `ManufacturerVersion`).</p>|Dependent item|tpm.manufacturer.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].ManufacturerVersion`</p><p>⛔️Custom on fail: Set value to: ``</p></li></ul>|
|Windows SecureBoot: State|<p>The UEFI Secure Boot state (0 - disabled, 1 - enabled).</p><p>On legacy BIOS/CSM hosts the registry key does not exist and the item becomes NOTSUPPORTED; set `{$WINDOWS.SECURITY.SECUREBOOT.IGNORE}=1` on such hosts.</p>|Zabbix agent|registry.data[HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\SecureBoot\State,UEFISecureBootEnabled]|
|Windows activation: Get product info|<p>Raw Windows licensing product data from the `SoftwareLicensingProduct` WMI class (filtered to the active Windows OS product).</p>|Zabbix agent|wmi.getall[root\CIMV2,"select Name,Description,LicenseStatus,PartialProductKey,GracePeriodRemaining,LicenseStatusReason from SoftwareLicensingProduct where PartialProductKey is not null and ApplicationID='55c92734-d682-4d71-983e-d6ec3f16059f'"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows activation: Get service info|<p>Raw Windows licensing service data from the `SoftwareLicensingService` WMI class (KMS configuration).</p>|Zabbix agent|wmi.getall[root\CIMV2,"select Version,KeyManagementServiceMachine,KeyManagementServicePort,ClientMachineID,IsKeyManagementServiceMachine from SoftwareLicensingService"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows activation: License status|<p>The Windows license status (0 - Unlicensed, 1 - Licensed, 2 - OOB grace, 3 - OOT grace, 4 - Non-genuine grace, 5 - Notification, 6 - Extended grace).</p>|Dependent item|activation.license.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].LicenseStatus`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Windows activation: Grace period remaining|<p>The time remaining in the current licensing grace period.</p>|Dependent item|activation.grace<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].GracePeriodRemaining`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `60`</p></li></ul>|
|Windows activation: Product name|<p>The licensed product name (from `Name`).</p>|Dependent item|activation.product.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].Name`</p><p>⛔️Custom on fail: Set value to: ``</p></li></ul>|
|Windows activation: Partial product key|<p>The last five characters of the installed product key (from `PartialProductKey`).</p>|Dependent item|activation.product.key<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].PartialProductKey`</p><p>⛔️Custom on fail: Set value to: ``</p></li></ul>|
|Windows activation: Status reason code|<p>The licensing status reason code (from `LicenseStatusReason`).</p>|Dependent item|activation.status.reason<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].LicenseStatusReason`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Windows activation: KMS server|<p>The Key Management Service (KMS) host name used for activation (from `KeyManagementServiceMachine`).</p>|Dependent item|activation.kms.server<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].KeyManagementServiceMachine`</p><p>⛔️Custom on fail: Set value to: ``</p></li></ul>|
|Windows activation: KMS port|<p>The Key Management Service (KMS) port (from `KeyManagementServicePort`).</p>|Dependent item|activation.kms.port<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].KeyManagementServicePort`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Windows activation: Client machine ID|<p>The KMS client machine identifier (from `ClientMachineID`).</p>|Dependent item|activation.client.id<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].ClientMachineID`</p><p>⛔️Custom on fail: Set value to: ``</p></li></ul>|
|Windows activation: Is KMS host|<p>Whether this machine is a KMS host (from `IsKeyManagementServiceMachine`).</p>|Dependent item|activation.kms.host<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].IsKeyManagementServiceMachine`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Windows Defender: Get status|<p>Raw Microsoft Defender antimalware status from the `MSFT_MpComputerStatus` WMI class.</p>|Zabbix agent|wmi.getall[root\Microsoft\Windows\Defender,"select AMServiceEnabled,AntispywareEnabled,AntivirusEnabled,RealTimeProtectionEnabled,AntispywareSignatureLastUpdated,AntivirusSignatureLastUpdated,QuickScanAge,FullScanAge,AntivirusSignatureVersion from MSFT_MpComputerStatus"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows Defender: AM service enabled|<p>Whether the Defender antimalware service is running (from `AMServiceEnabled`).</p>|Dependent item|defender.amservice.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].AMServiceEnabled`</p><p>⛔️Custom on fail: Set value to: `False`</p></li><li>Boolean to decimal</li></ul>|
|Windows Defender: Real-time protection enabled|<p>Whether real-time protection is enabled (from `RealTimeProtectionEnabled`).</p>|Dependent item|defender.rtp.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].RealTimeProtectionEnabled`</p><p>⛔️Custom on fail: Set value to: `False`</p></li><li>Boolean to decimal</li></ul>|
|Windows Defender: Antivirus enabled|<p>Whether antivirus protection is enabled (from `AntivirusEnabled`).</p>|Dependent item|defender.av.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].AntivirusEnabled`</p><p>⛔️Custom on fail: Set value to: `False`</p></li><li>Boolean to decimal</li></ul>|
|Windows Defender: Antispyware enabled|<p>Whether antispyware protection is enabled (from `AntispywareEnabled`).</p>|Dependent item|defender.as.enabled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].AntispywareEnabled`</p><p>⛔️Custom on fail: Set value to: `False`</p></li><li>Boolean to decimal</li></ul>|
|Windows Defender: Antivirus signature age|<p>Time since the antivirus signatures were last updated.</p>|Dependent item|defender.av.signature.age<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows Defender: Antispyware signature age|<p>Time since the antispyware signatures were last updated.</p>|Dependent item|defender.as.signature.age<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows Defender: Quick scan age|<p>Time since the last quick scan. Reads as "Never scanned" if no quick scan has ever run.</p>|Dependent item|defender.quickscan.age<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows Defender: Full scan age|<p>Time since the last full scan. Reads as "Never scanned" if no full scan has ever run.</p>|Dependent item|defender.fullscan.age<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows Defender: Antivirus signature version|<p>The antivirus signature package version (from `AntivirusSignatureVersion`).</p>|Dependent item|defender.av.signature.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[0].AntivirusSignatureVersion`</p><p>⛔️Custom on fail: Set value to: ``</p></li></ul>|
|Windows updates: Get installed KBs|<p>Raw list of installed Windows updates (hotfixes) from the `Win32_QuickFixEngineering` WMI class.</p>|Zabbix agent|wmi.getall[root\cimv2,"select HotFixID,Description,InstalledOn,Caption from Win32_QuickFixEngineering"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows updates: KBs installed (total)|<p>The total number of installed updates (KBs).</p>|Dependent item|updates.kb.total<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows updates: Security KBs installed|<p>The number of installed updates whose description is "Security Update".</p>|Dependent item|updates.kb.security<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows updates: Last KB ID|<p>The HotFixID of the most recently installed update.</p>|Dependent item|updates.kb.last.id<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows updates: Last KB install date|<p>The install date of the most recently installed update.</p>|Dependent item|updates.kb.last.date<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows updates: Days since last KB|<p>The number of whole days since the most recently installed update.</p>|Dependent item|updates.kb.days<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows updates: Last Security KB install date|<p>The install date of the most recently installed "Security Update".</p>|Dependent item|updates.security.last.date<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows updates: Days since last Security KB|<p>The number of whole days since the most recently installed "Security Update".</p>|Dependent item|updates.security.days<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Windows Firewall: Get profiles|<p>Raw Windows Firewall profile data from the `MSFT_NetFirewallProfile` WMI class.</p>|Zabbix agent|wmi.getall[root\StandardCimv2,"select Name,Enabled,DefaultInboundAction,DefaultOutboundAction from MSFT_NetFirewallProfile"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows BitLocker: Get encryptable volumes|<p>Raw BitLocker volume data from the `Win32_EncryptableVolume` WMI class.</p>|Zabbix agent|wmi.getall[root\CIMV2\Security\MicrosoftVolumeEncryption,"select DeviceID,DriveLetter,ProtectionStatus,EncryptionMethod,ConversionStatus,VolumeType,IsVolumeInitializedForProtection from Win32_EncryptableVolume"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Windows scheduled tasks: Get tasks|<p>Raw Windows scheduled task data from the `MSFT_ScheduledTask` WMI class.</p>|Zabbix agent|wmi.getall[root\Microsoft\Windows\TaskScheduler,"select TaskName,TaskPath,State,URI from MSFT_ScheduledTask"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Windows: CPU interrupt time is too high|<p>The CPU Interrupt Time in the last 5 minutes exceeds `{$CPU.INTERRUPT.CRIT.MAX}`%.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\Processor Information(_total)\% Interrupt Time"],5m)>{$CPU.INTERRUPT.CRIT.MAX}`|Warning|**Depends on**:<br><ul><li>Windows: High CPU utilization</li></ul>|
|Windows: CPU privileged time is too high|<p>The CPU privileged time in the last 5 minutes exceeds {$CPU.PRIV.CRIT.MAX}%.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\Processor Information(_total)\% Privileged Time"],5m)>{$CPU.PRIV.CRIT.MAX}`|Warning|**Depends on**:<br><ul><li>Windows: CPU interrupt time is too high</li><li>Windows: High CPU utilization</li></ul>|
|Windows: CPU queue length is too high|<p>The CPU Queue Length in the last 5 minutes exceeds `{$CPU.QUEUE.CRIT.MAX}`. According to actual observations, PQL should not exceed the number of cores * 2. To fine-tune the conditions, use the macro `{$CPU.QUEUE.CRIT.MAX }`.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\System\Processor Queue Length"],5m) - last(/Windows by Zabbix agent/wmi.get[root/cimv2,"Select NumberOfLogicalProcessors from Win32_ComputerSystem"]) * 2 > {$CPU.QUEUE.CRIT.MAX}`|Warning|**Depends on**:<br><ul><li>Windows: High CPU utilization</li></ul>|
|Windows: High CPU utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Windows by Zabbix agent/system.cpu.util,5m)>{$CPU.UTIL.CRIT}`|Warning||
|Windows: Number of free system page table entries is too low|<p>`Memory\Free System Page Table Entries` has been less than `{$MEM.PAGE_TABLE_CRIT.MIN}` for 5 minutes. If the number is less than 5,000, there may be a memory leak.</p>|`max(/Windows by Zabbix agent/perf_counter_en["\Memory\Free System Page Table Entries"],5m)<{$MEM.PAGE_TABLE_CRIT.MIN}`|Warning|**Depends on**:<br><ul><li>Windows: High memory utilization</li></ul>|
|Windows: The Memory Pages/sec is too high|<p>The Memory Pages/sec in the last 5 minutes exceeds `{$MEM.PAGE_SEC.CRIT.MAX}`. If the value is greater than 1,000, as a result of excessive paging, there may be a memory leak.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\Memory\Pages/sec"],5m)>{$MEM.PAGE_SEC.CRIT.MAX}`|Warning|**Depends on**:<br><ul><li>Windows: High memory utilization</li></ul>|
|Windows: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Windows by Zabbix agent/vm.memory.size[pused],5m)>{$MEMORY.UTIL.MAX}`|Average||
|Windows: Operating system description has changed|<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`change(/Windows by Zabbix agent/system.sw.os) and length(last(/Windows by Zabbix agent/system.sw.os))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Windows: System name has changed</li></ul>|
|Windows: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`change(/Windows by Zabbix agent/system.hostname) and length(last(/Windows by Zabbix agent/system.hostname))>0`|Info|**Manual close**: Yes|
|Windows: High swap space usage|<p>This trigger is ignored, if there is no swap configured</p>|`max(/Windows by Zabbix agent/system.swap.pfree,5m)<{$SWAP.PFREE.MIN.WARN} and last(/Windows by Zabbix agent/system.swap.size[,total])>0`|Warning|**Depends on**:<br><ul><li>Windows: High memory utilization</li></ul>|
|Windows: Host has been restarted|<p>The device uptime is less than 10 minutes.</p>|`last(/Windows by Zabbix agent/system.uptime)<10m`|Warning|**Manual close**: Yes|
|Windows: Zabbix agent is not available|<p>For passive agents only, host availability is used with `{$AGENT.TIMEOUT}` as a time threshold.</p>|`max(/Windows by Zabbix agent/zabbix[host,agent,available],{$AGENT.TIMEOUT})=0`|Average|**Manual close**: Yes|
|Windows: TPM is not present|<p>No TPM chip was detected on the host. A TPM is required for some security features (BitLocker, virtualization-based security, Windows 11 readiness).</p>|`last(/Windows by Zabbix agent/tpm.presence)=0`|Info||
|Windows: SecureBoot is disabled|<p>UEFI Secure Boot is disabled. It helps prevent malicious software from loading during the boot process. If this host legitimately runs without Secure Boot (e.g. legacy BIOS/CSM), set `{$WINDOWS.SECURITY.SECUREBOOT.IGNORE}=1`.</p>|`last(/Windows by Zabbix agent/registry.data[HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\SecureBoot\State,UEFISecureBootEnabled])=0 and {$WINDOWS.SECURITY.SECUREBOOT.IGNORE}=0`|Warning||
|Windows: Is not licensed|<p>The Windows license status is not "Licensed". The system may be unlicensed, in a grace period, or in notification mode.</p>|`last(/Windows by Zabbix agent/activation.license.status)<>1`|Warning||
|Windows: Activation grace period running out|<p>The Windows activation grace period is about to expire. Activate Windows before the grace period ends.</p>|`last(/Windows by Zabbix agent/activation.grace)>0 and last(/Windows by Zabbix agent/activation.grace)<{$WINDOWS.SECURITY.ACTIVATION.GRACE.MIN.WARN}`|Warning||
|Windows: Defender real-time protection is off|<p>Microsoft Defender real-time protection is disabled. The system is not actively protected against malware.</p>|`last(/Windows by Zabbix agent/defender.rtp.enabled)=0`|Average||
|Windows: Defender signatures are out of date|<p>The Microsoft Defender antivirus signatures have not been updated within the expected interval.</p>|`min(/Windows by Zabbix agent/defender.av.signature.age,5m)>{$WINDOWS.SECURITY.DEFENDER.SIGNATURE.MAX.WARN}`|Warning||
|Windows: Defender full scan overdue|<p>A full Microsoft Defender scan has not been completed within the expected interval.</p>|`last(/Windows by Zabbix agent/defender.fullscan.age)>{$WINDOWS.SECURITY.DEFENDER.FULLSCAN.MAX.WARN}`|Warning||
|Windows updates: No KB installed recently|<p>No Windows update (KB) has been installed within the configured number of days.</p>|`last(/Windows by Zabbix agent/updates.kb.days)>{$WINDOWS.UPDATES.STALE.MAX.WARN}`|Warning||
|Windows updates: No Security KB installed recently|<p>No Windows security update (KB) has been installed within the configured number of days.</p>|`last(/Windows by Zabbix agent/updates.security.days)>{$WINDOWS.UPDATES.SECURITY.STALE.MAX.WARN}`|Average||

### LLD rule Windows scheduled tasks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Windows scheduled tasks discovery|<p>Discovery of Windows scheduled tasks. No task is discovered until `{$WINDOWS.TASK.NAME.MATCHES}` is set, because a stock Windows installation carries about 200 tasks.</p><p>On hosts where the Task Scheduler WMI namespace is unavailable the master item returns an empty list, so no items are created instead of becoming unsupported.</p>|Dependent item|task.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Windows scheduled tasks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Scheduled task [{#TASK.PATH}{#TASK.NAME}]: State|<p>The current state of the "{#TASK.NAME}" scheduled task.</p>|Dependent item|task.state["{#TASK.URI}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.URI == "{#TASK.URI}")].State.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Windows scheduled tasks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Windows: Scheduled task [{#TASK.PATH}{#TASK.NAME}] is disabled|<p>The "{#TASK.NAME}" scheduled task is disabled and will not run.</p>|`{$WINDOWS.TASK.CONTROL:"{#TASK.NAME}"}=1 and last(/Windows by Zabbix agent/task.state["{#TASK.URI}"])=1`|Warning|**Manual close**: Yes|

### LLD rule Windows Firewall: Profiles discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Windows Firewall: Profiles discovery|<p>Discovery of Windows Firewall profiles.</p>|Dependent item|firewall.profiles.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Windows Firewall: Profiles discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Windows Firewall [{#FW.PROFILE}]: Enabled|<p>Whether the "{#FW.PROFILE}" firewall profile is enabled.</p>|Dependent item|firewall.enabled["{#FW.PROFILE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Name == "{#FW.PROFILE}")].Enabled.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Windows Firewall [{#FW.PROFILE}]: Default inbound action|<p>The default inbound action of the "{#FW.PROFILE}" firewall profile.</p>|Dependent item|firewall.inbound["{#FW.PROFILE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.Name == "{#FW.PROFILE}")].DefaultInboundAction.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Windows Firewall [{#FW.PROFILE}]: Default outbound action|<p>The default outbound action of the "{#FW.PROFILE}" firewall profile.</p>|Dependent item|firewall.outbound["{#FW.PROFILE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Windows Firewall: Profiles discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Windows: Firewall [{#FW.PROFILE}] is disabled|<p>The "{#FW.PROFILE}" Windows Firewall profile is not enabled.</p>|`last(/Windows by Zabbix agent/firewall.enabled["{#FW.PROFILE}"])<>1`|Warning||

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>Discovery of filesystems of different types.</p>|Dependent item|vfs.fs.dependent.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FS [{#FSLABEL}({#FSNAME})]: Get data|<p>Intermediate data of `{#FSNAME}` filesystem.</p>|Dependent item|vfs.fs.dependent[{#FSNAME},data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.fsname=='{#FSNAME}')].first()`</p></li></ul>|
|FS [{#FSLABEL}({#FSNAME})]: Space: Available|<p>Available storage space expressed in bytes.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.free`</p></li></ul>|
|FS [{#FSLABEL}({#FSNAME})]: Space: Total|<p>Total space expressed in bytes.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.total`</p></li></ul>|
|FS [{#FSLABEL}({#FSNAME})]: Space: Used|<p>Used storage expressed in bytes.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.used`</p></li></ul>|
|FS [{#FSLABEL}({#FSNAME})]: Space: Used, in %|<p>Calculated as the percentage of currently used space compared to the maximum available space.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},pused]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.pused`</p></li></ul>|
|FS [{#FSLABEL}({#FSNAME})]: Space: Available, in %|<p>Calculated as the percentage of currently available space compared to the maximum available space.</p>|Dependent item|vfs.fs.dependent.size[{#FSNAME},pfree]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.pfree`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Windows: FS [{#FSLABEL}({#FSNAME})]: Space is critically low|<p>The volume's space usage exceeds the `{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}%` limit.</p><p><br>The trigger expression is based on the current used and maximum available spaces.</p><p><br>Event name represents the total volume space, which can differ from the maximum available space, depending on the filesystem type.</p>|`{$FSCONTROL:"{#FSNAME}"}=1 and min(/Windows by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},pused],5m)>{$VFS.FS.PUSED.MAX.CRIT:"{#FSLABEL}({#FSNAME})"}`|Average|**Manual close**: Yes|
|Windows: FS [{#FSLABEL}({#FSNAME})]: Space is low|<p>The volume's space usage exceeds the `{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}%` limit.</p><p><br>The trigger expression is based on the current used and maximum available spaces.</p><p><br>Event name represents the total volume space, which can differ from the maximum available space, depending on the filesystem type.</p>|`{$FSCONTROL:"{#FSNAME}"}=1 and min(/Windows by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},pused],5m)>{$VFS.FS.PUSED.MAX.WARN:"{#FSLABEL}({#FSNAME})"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Windows: FS [{#FSLABEL}({#FSNAME})]: Space is critically low</li></ul>|

### LLD rule Windows BitLocker: Volume discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Windows BitLocker: Volume discovery|<p>Discovery of BitLocker encryptable volumes. On hosts where BitLocker is unavailable it returns no volumes, so the per-volume items are not created instead of becoming unsupported.</p>|Dependent item|bitlocker.volume.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Windows BitLocker: Volume discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BitLocker [{#FSNAME}]: Protection status|<p>The BitLocker protection status of volume "{#FSNAME}".</p>|Dependent item|bitlocker.protection["{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.DriveLetter == "{#FSNAME}")].ProtectionStatus.first()`</p><p>⛔️Custom on fail: Set value to: `2`</p></li></ul>|
|BitLocker [{#FSNAME}]: Encryption method|<p>The BitLocker encryption method of volume "{#FSNAME}".</p>|Dependent item|bitlocker.method["{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.DriveLetter == "{#FSNAME}")].EncryptionMethod.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|BitLocker [{#FSNAME}]: Conversion status|<p>The BitLocker conversion (encryption/decryption progress) status of volume "{#FSNAME}".</p>|Dependent item|bitlocker.conversion["{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.DriveLetter == "{#FSNAME}")].ConversionStatus.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Windows BitLocker: Volume discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Windows: BitLocker [{#FSNAME}] is not enabled on enforced volume|<p>BitLocker protection is not enabled on volume "{#FSNAME}", which is required by `{$WINDOWS.SECURITY.BITLOCKER.ENFORCE.MATCHES}`.</p>|`last(/Windows by Zabbix agent/bitlocker.protection["{#FSNAME}"])<>1`|Average||

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovery of installed network interfaces.</p>|Dependent item|net.if.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>Incoming traffic on the network interface.</p>|Zabbix agent|net.if.in["{#IFGUID}"]<p>**Preprocessing**</p><ul><li>Change per second: </li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>Outgoing traffic on the network interface.</p>|Zabbix agent|net.if.out["{#IFGUID}"]<p>**Preprocessing**</p><ul><li>Change per second: </li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>The number of incoming packets dropped on the network interface.</p>|Zabbix agent|net.if.in["{#IFGUID}",dropped]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>The number of incoming packets with errors on the network interface.</p>|Zabbix agent|net.if.in["{#IFGUID}",errors]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>The type of the network interface.</p>|Dependent item|net.if.type["{#IFGUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.GUID == "{#IFGUID}")].AdapterTypeId.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>The operational status of the network interface.</p>|Dependent item|net.if.status["{#IFGUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.GUID == "{#IFGUID}")].NetConnectionStatus.first()`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>The number of outgoing packets dropped on the network interface.</p>|Zabbix agent|net.if.out["{#IFGUID}",dropped]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>The number of outgoing packets with errors on the network interface.</p>|Zabbix agent|net.if.out["{#IFGUID}",errors]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>Estimated bandwidth of the network interface if any.</p>|Dependent item|net.if.speed["{#IFGUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.GUID == "{#IFGUID}")].Speed.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>JavaScript: `return (value=='9223372036854775807' ? 0 : value)`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Windows: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Windows by Zabbix agent/net.if.in["{#IFGUID}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Windows by Zabbix agent/net.if.speed["{#IFGUID}"]) or avg(/Windows by Zabbix agent/net.if.out["{#IFGUID}"],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Windows by Zabbix agent/net.if.speed["{#IFGUID}"])) and last(/Windows by Zabbix agent/net.if.speed["{#IFGUID}"])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Windows: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Windows: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Windows by Zabbix agent/net.if.in["{#IFGUID}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Windows by Zabbix agent/net.if.out["{#IFGUID}",errors],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Windows: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Windows: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:</p><p><br>1. It can be triggered if the operations status is down.</p><p><br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important.</p><p><br>No new trigger will be fired if this interface is down.</p><p><br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for the 'eternal off' interfaces.)</p><p><br></p><p><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Windows by Zabbix agent/net.if.status["{#IFGUID}"])<>2 and (last(/Windows by Zabbix agent/net.if.status["{#IFGUID}"],#1)<>last(/Windows by Zabbix agent/net.if.status["{#IFGUID}"],#2))`|Average|**Manual close**: Yes|
|Windows: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Windows by Zabbix agent/net.if.speed["{#IFGUID}"])<0 and last(/Windows by Zabbix agent/net.if.speed["{#IFGUID}"])>0 and last(/Windows by Zabbix agent/net.if.status["{#IFGUID}"])=2`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Windows: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

### LLD rule Physical disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical disks discovery|<p>Discovery of installed physical disks.</p>|Zabbix agent|perf_instance_en.discovery[PhysicalDisk]<p>**Preprocessing**</p><ul><li><p>Replace: `{#INSTANCE} -> {#DEVNAME}`</p></li></ul>|

### Item prototypes for Physical disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DEVNAME}: Average disk read queue length|<p>Average disk read queue, the number of requests outstanding on the disk at the time the performance data is collected.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk Read Queue Length",60]|
|{#DEVNAME}: Average disk write queue length|<p>Average disk write queue, the number of requests outstanding on the disk at the time the performance data is collected.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk Write Queue Length",60]|
|{#DEVNAME}: Disk average queue size (avgqu-sz)|<p>The current average disk queue; the number of requests outstanding on the disk while the performance data is being collected.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Current Disk Queue Length",60]|
|{#DEVNAME}: Disk read rate|<p>Rate of read operations on the disk.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Disk Reads/sec",60]|
|{#DEVNAME}: Disk read request avg waiting time|<p>The average time for read requests issued to the device to be served. This includes the time spent by the requests in queue and the time spent servicing them.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Read",60]|
|{#DEVNAME}: Disk utilization by idle time|<p>This item is the percentage of elapsed time that the selected disk drive was busy servicing read or writes requests based on idle time.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\% Idle Time",60]<p>**Preprocessing**</p><ul><li><p>JavaScript: `return (100 - value)`</p></li></ul>|
|{#DEVNAME}: Disk write rate|<p>Rate of write operations on the disk.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Disk Writes/sec",60]|
|{#DEVNAME}: Disk write request avg waiting time|<p>The average time for write requests issued to the device to be served. This includes the time spent by the requests in queue and the time spent servicing them.</p>|Zabbix agent|perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Write",60]|

### Trigger prototypes for Physical disks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Windows: {#DEVNAME}: Disk read request responses are too high|<p>This trigger might indicate the disk {#DEVNAME} saturation.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Read",60],15m) > {$VFS.DEV.READ.AWAIT.WARN:"{#DEVNAME}"}`|Warning|**Manual close**: Yes|
|Windows: {#DEVNAME}: Disk is overloaded|<p>The disk appears to be under heavy load.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\PhysicalDisk({#DEVNAME})\% Idle Time",60],15m)>{$VFS.DEV.UTIL.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Windows: {#DEVNAME}: Disk read request responses are too high</li><li>Windows: {#DEVNAME}: Disk write request responses are too high</li></ul>|
|Windows: {#DEVNAME}: Disk write request responses are too high|<p>This trigger might indicate the disk {#DEVNAME} saturation.</p>|`min(/Windows by Zabbix agent/perf_counter_en["\PhysicalDisk({#DEVNAME})\Avg. Disk sec/Write",60],15m) > {$VFS.DEV.WRITE.AWAIT.WARN:"{#DEVNAME}"}`|Warning|**Manual close**: Yes|

### LLD rule Windows services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Windows services discovery|<p>Used for the discovery of Windows services of different types as defined in the template's macros.</p><p>Services matching `{$SERVICE.FORBIDDEN.MATCHES}` are discovered regardless of their startup type, so that a service which must stay stopped can be watched as well.</p>|Zabbix agent|service.discovery|

### Item prototypes for Windows services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|State of service "{#SERVICE.NAME}" ({#SERVICE.DISPLAYNAME})|<p>{#SERVICE.DESCRIPTION}</p>|Zabbix agent|service.info["{#SERVICE.NAME}",state]|

### Trigger prototypes for Windows services discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Windows: "{#SERVICE.NAME}" ({#SERVICE.DISPLAYNAME}) is not running|<p>The service has a state other than "Running" for the last three times.</p><p><br>The check is suppressed until the system uptime exceeds `{$SERVICE.STARTUP.GRACE}`, so that services with the "automatic delayed" startup type are given time to start after a reboot.</p>|`min(/Windows by Zabbix agent/service.info["{#SERVICE.NAME}",state],#3)<>0 and last(/Windows by Zabbix agent/system.uptime)>{$SERVICE.STARTUP.GRACE:"{#SERVICE.NAME}"}`|Average||
|Windows: "{#SERVICE.NAME}" ({#SERVICE.DISPLAYNAME}) must not be running|<p>The service matches `{$SERVICE.FORBIDDEN.MATCHES}` and is expected to stay stopped, but it has been running for the last three times.</p><p><br>The trigger is discovered only for the services listed in that macro.</p>|`min(/Windows by Zabbix agent/service.info["{#SERVICE.NAME}",state],#3)=0`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

