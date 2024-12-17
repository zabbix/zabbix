
# Nvidia by Zabbix agent 2

## Overview

This template is designed for Nvidia GPU monitoring and doesn't require any external scripts.
All Nvidia GPUs will be discovered. Set filters with macros if you want to override default filter parameters.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Nvidia GTX 1650s
- Nvidia RTX 2070Ti

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Setup and configure Zabbix agent 2 compiled with the Nvidia monitoring plugin.
2. Create a host with Zabbix agent interface and attach the template to it.

Test availability: `zabbix_get -s nvidia-host -k nvml.system.driver.version`

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NVIDIA.GPU.UTIL.WARN}|<p>Warning threshold for GPU overall utilization, in %.</p>|`80`|
|{$NVIDIA.GPU.UTIL.CRIT}|<p>Critical threshold for GPU overall utilization, in %.</p>|`90`|
|{$NVIDIA.ENCODER.UTIL.WARN}|<p>Warning threshold for encoder utilization, in %.</p>|`80`|
|{$NVIDIA.ENCODER.UTIL.CRIT}|<p>Critical threshold for encoder utilization, in %.</p>|`90`|
|{$NVIDIA.DECODER.UTIL.WARN}|<p>Warning threshold for decoder utilization, in %.</p>|`80`|
|{$NVIDIA.DECODER.UTIL.CRIT}|<p>Critical threshold for decoder utilization, in %.</p>|`90`|
|{$NVIDIA.MEMORY.UTIL.WARN}|<p>Warning threshold for memory utilization, in %.</p>|`80`|
|{$NVIDIA.MEMORY.UTIL.CRIT}|<p>Critical threshold for memory utilization, in %.</p>|`90`|
|{$NVIDIA.FAN.SPEED.WARN}|<p>Warning threshold for fan speed, in %.</p>|`80`|
|{$NVIDIA.FAN.SPEED.CRIT}|<p>Critical threshold for fan speed, in %.</p>|`90`|
|{$NVIDIA.TEMPERATURE.WARN}|<p>Warning threshold for temperature, in %.</p>|`80`|
|{$NVIDIA.TEMPERATURE.CRIT}|<p>Critical threshold for temperature, in %.</p>|`90`|
|{$NVIDIA.POWER.UTIL.WARN}|<p>Warning threshold for power usage, in %.</p>|`80`|
|{$NVIDIA.POWER.UTIL.CRIT}|<p>Critical threshold for power usage, in %.</p>|`90`|
|{$NVIDIA.NAME.MATCHES}|<p>Filter to include GPUs by name in discovery.</p>|`.*`|
|{$NVIDIA.NAME.NOT_MATCHES}|<p>Filter to exclude GPUs by name in discovery.</p>|`CHANGE IF NEEDED`|
|{$NVIDIA.UUID.MATCHES}|<p>Filter to include GPUs by UUID in discovery.</p>|`.*`|
|{$NVIDIA.UUID.NOT_MATCHES}|<p>Filter to exclude GPUs by UUID in discovery.</p>|`CHANGE IF NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Driver version|<p>Retrieves the version of the system's graphics driver.</p><p>For all Nvidia products.</p>|Zabbix agent|nvml.system.driver.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|NVML library version|<p>Retrieves the version of the NVML library.</p><p>For all Nvidia products.</p>|Zabbix agent|nvml.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Number of devices|<p>Retrieves the number of compute devices in the system. A compute device is a single GPU.</p><p>For all Nvidia products.</p>|Zabbix agent|nvml.device.count<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Get devices|<p>Retrieves list of Nvidia devices in the system.</p>|Zabbix agent|nvml.device.get|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nvidia: Driver version has changed|<p>Driver version has changed.<br>Check out changelog for specific driver version at Nvidia website: https://www.nvidia.com/en-us/drivers/</p>|`change(/Nvidia by Zabbix agent 2/nvml.system.driver.version) <> 0`|Info|**Manual close**: Yes|
|Nvidia: NVML library has changed|<p>NVML library version has changed.<br>Changelog can be found here: https://docs.nvidia.com/deploy/nvml-api/change-log.html</p>|`change(/Nvidia by Zabbix agent 2/nvml.version) <> 0`|Info|**Manual close**: Yes|
|Nvidia: Number of devices has changed|<p>Number of devices has changed. Check out if it was intentional.</p>|`change(/Nvidia by Zabbix agent 2/nvml.device.count) <> 0`|Warning|**Manual close**: Yes|

### LLD rule GPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GPU Discovery|<p>Nvidia GPU discovery in the system.</p>|Dependent item|nvml.device.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for GPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#UUID}]: Serial number|<p>Retrieves the globally unique board serial number associated with this device's board.</p><p>For all products with an inforom.</p><p>This number matches the serial number tag that is physically attached to the board.</p>|Zabbix agent|nvml.device.serial["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set error to: `The device does not support operation to retrieve serial number.`</p></li></ul>|
|[{#UUID}]: Encoder utilization|<p>Retrieves the current utilization for the Encoder.</p><p>For Nvidia Kepler or newer fully supported devices.</p>|Zabbix agent|nvml.device.encoder.utilization["{#UUID}"]|
|[{#UUID}]: Decoder utilization|<p>Retrieves the current utilization for the Decoder.</p><p>For Nvidia Kepler or newer fully supported devices.</p>|Zabbix agent|nvml.device.decoder.utilization["{#UUID}"]|
|[{#UUID}]: Fan speed|<p>Retrieves the intended operating speed of the device's specified fan.</p><p>Note: The reported speed is the intended fan speed. If the fan is physically blocked and unable to spin, the output will not match the actual fan speed.</p><p>For all Nvidia discrete products with dedicated fans.</p><p>The fan speed is expressed as a percentage of the product's maximum noise tolerance fan speed. This value may exceed 100% in certain cases.</p>|Zabbix agent|nvml.device.fan.speed.avg["{#UUID}"]|
|[{#UUID}]: Power usage|<p>Retrieves power usage for this GPU in watts and its associated circuitry (e.g. memory).</p><p>For Nvidia Fermi or newer fully supported devices.</p><p>On Fermi and Kepler GPUs the reading is accurate to within +/- 5% of current power draw. On Ampere (except GA100) or newer GPUs, the API returns power averaged over 1 sec interval. On GA100 and older architectures, instantaneous power is returned.</p>|Zabbix agent|nvml.device.power.usage["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|[{#UUID}]: Power limit|<p>Retrieves the power management limit associated with this device.</p><p>For Nvidia Fermi or newer fully supported devices.</p><p>The power limit defines the upper boundary for the card's power draw. If the card's total power draw reaches this limit the power management algorithm kicks in.</p><p>This reading is only available if power management mode is supported.</p>|Zabbix agent|nvml.device.power.limit["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|[{#UUID}]: Energy consumption|<p>Retrieves total energy consumption for this GPU in joules (J) since the driver was last reloaded.</p><p>For Nvidia Volta or newer fully supported devices.</p>|Zabbix agent|nvml.device.energy.consumption["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|[{#UUID}]: Temperature|<p>Retrieves the current temperature readings for the device, in degrees C.</p><p>For Nvidia all products.</p>|Zabbix agent|nvml.device.temperature["{#UUID}"]|
|[{#UUID}]: Memory frequency|<p>Retrieves the current memory clock speed for the device.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Zabbix agent|nvml.device.memory.frequency["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li></ul>|
|[{#UUID}]: SM frequency|<p>Retrieves the current SM clock speed for the device.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Zabbix agent|nvml.device.sm.frequency["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li></ul>|
|[{#UUID}]: Graphics frequency|<p>Retrieves the current graphics clock speed for the device.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Zabbix agent|nvml.device.graphics.frequency["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li></ul>|
|[{#UUID}]: Video frequency|<p>Retrieves the current video encoder/decoder clock speed for the device.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Zabbix agent|nvml.device.video.frequency["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li></ul>|
|[{#UUID}]: Performance state|<p>Retrieves the current performance state for the device.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Zabbix agent|nvml.device.performance.state["{#UUID}"]|
|[{#UUID}]: Device utilization, get|<p>Retrieves the current utilization rates for the device's major subsystems.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Zabbix agent|nvml.device.utilization["{#UUID}"]|
|[{#UUID}]: GPU utilization|<p>Percent of time over the past sample period during which one or more kernels was executing on the GPU.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Dependent item|nvml.device.utilization.gpu["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.device`</p></li></ul>|
|[{#UUID}]: Memory utilization|<p>Percent of time over the past sample period during which global (device) memory was being read or written.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Dependent item|nvml.device.utilization.memory["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memory`</p></li></ul>|
|[{#UUID}]: Encoder stats|<p>Retrieves the current encoder statistics for a given device.</p><p>For Nvidia Maxwell or newer fully supported devices.</p>|Zabbix agent|nvml.device.encoder.stats.get["{#UUID}"]|
|[{#UUID}]: Encoder sessions|<p>Retrieves the current count of active encoder sessions for a given device.</p><p>For Nvidia Maxwell or newer fully supported devices.</p>|Dependent item|nvml.device.encoder.stats.sessions["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.session_count`</p></li></ul>|
|[{#UUID}]: Encoder average FPS|<p>Retrieves the trailing average FPS of all active encoder sessions for a given device.</p><p>For Nvidia Maxwell or newer fully supported devices.</p>|Dependent item|nvml.device.encoder.stats.fps["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.average_fps`</p></li></ul>|
|[{#UUID}]: Encoder average latency|<p>Retrieves the current encode latency for a given device.</p><p>For Nvidia Maxwell or newer fully supported devices.</p>|Dependent item|nvml.device.encoder.stats.latency["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.average_latency_ms`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|[{#UUID}]: FB memory, get|<p>Retrieves the amount of used, free, reserved and total memory available on the device.</p><p>For all Nvidia products.</p><p>Enabling ECC reduces the amount of total available memory, due to the extra required parity bits. Under WDDM most device memory is allocated and managed on startup by Windows.</p><p>Under Linux and Windows TCC, the reported amount of used memory is equal to the sum of memory allocated by all active channels on the device.</p>|Zabbix agent|nvml.device.memory.fb.get["{#UUID}"]|
|[{#UUID}]: FB memory, total|<p>Total physical memory on the device.</p><p>For all Nvidia products.</p>|Dependent item|nvml.device.memory.fb.total["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_memory_bytes`</p></li></ul>|
|[{#UUID}]: FB memory, reserved|<p>Memory reserved for system use (driver or firmware) on the device.</p><p>For all Nvidia products.</p>|Dependent item|nvml.device.memory.fb.reserved["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.reserved_memory_bytes`</p><p>⛔️Custom on fail: Set error to: `NVML library too old to support this metric.`</p></li></ul>|
|[{#UUID}]: FB memory, free|<p>Unallocated memory on the device.</p><p>For all Nvidia products.</p>|Dependent item|nvml.device.memory.fb.free["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.free_memory_bytes`</p></li></ul>|
|[{#UUID}]: FB memory, used|<p>Allocated memory on the device.</p><p>For all Nvidia products.</p>|Dependent item|nvml.device.memory.fb.used["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_bytes`</p></li></ul>|
|[{#UUID}]: BAR1 memory, get|<p>Gets Total, Available and Used size of BAR1 memory.</p><p>BAR1 is used to map the FB (device memory) so that it can be directly accessed by the CPU or by 3rd party devices (peer-to-peer on the PCIE bus).</p><p>For Nvidia Kepler or newer fully supported devices</p>|Zabbix agent|nvml.device.memory.bar1.get["{#UUID}"]|
|[{#UUID}]: BAR1 memory, total|<p>Total BAR1 memory on the device.</p><p>For Nvidia Kepler or newer fully supported devices</p>|Dependent item|nvml.device.memory.bar1.total["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_memory_bytes`</p></li></ul>|
|[{#UUID}]: BAR1 memory, free|<p>Unallocated BAR1 memory on the device.</p><p>For Nvidia Kepler or newer fully supported devices</p>|Dependent item|nvml.device.memory.bar1.free["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.free_memory_bytes`</p></li></ul>|
|[{#UUID}]: BAR1 memory, used|<p>Allocated used BAR1 memory on the device.</p><p>For Nvidia Kepler or newer fully supported devices</p>|Dependent item|nvml.device.memory.bar1.used["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used_memory_bytes`</p></li></ul>|
|[{#UUID}]: Memory ECC errors, get|<p>Retrieves the GPU device memory error counters for the device.</p><p>For Nvidia Fermi or newer fully supported devices.</p><p>Requires NVML_INFOROM_ECC version 2.0 or higher to report aggregate location-based memory error counts. Requires NVML_INFOROM_ECC version 1.0 or higher to report all other memory error counts.</p><p>Only applicable to devices with ECC.</p><p>Requires ECC Mode to be enabled.</p>|Zabbix agent|nvml.device.errors.memory["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set error to: `No ECC on the device or ECC mode is turned off.`</p></li></ul>|
|[{#UUID}]: Memory ECC errors, corrected|<p>Retrieves the count of GPU device memory errors that were corrected. For ECC errors, these are single bit errors, for Texture memory, these are errors fixed by resend.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Dependent item|nvml.device.errors.memory.corrected["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.corrected`</p></li></ul>|
|[{#UUID}]: Memory ECC errors, uncorrected|<p>Retrieves the count of GPU device memory errors that were not corrected. For ECC errors, these are double bit errors, for Texture memory, these are errors where the resend fails.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Dependent item|nvml.device.errors.memory.uncorrected["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uncorrected`</p></li></ul>|
|[{#UUID}]: Register file errors, get|<p>Retrieves the GPU register file error counters for the device.</p><p>For Nvidia Fermi or newer fully supported devices.</p><p>Requires NVML_INFOROM_ECC version 2.0 or higher to report aggregate location-based memory error counts. Requires NVML_INFOROM_ECC version 1.0 or higher to report all other memory error counts.</p><p>Only applicable to devices with ECC.</p><p>Requires ECC Mode to be enabled.</p>|Zabbix agent|nvml.device.errors.register["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set error to: `No ECC on the device or ECC mode is turned off.`</p></li></ul>|
|[{#UUID}]: Register file errors, corrected|<p>Retrieves the count of GPU register file errors that were corrected. For ECC errors, these are single bit errors, for Texture memory, these are errors fixed by resend.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Dependent item|nvml.device.errors.register.corrected["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.corrected`</p></li></ul>|
|[{#UUID}]: Register file errors, uncorrected|<p>Retrieves the count of GPU register file errors that were not corrected. For ECC errors, these are double bit errors, for Texture memory, these are errors where the resend fails.</p><p>For Nvidia Fermi or newer fully supported devices.</p>|Dependent item|nvml.device.errors.register.uncorrected["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uncorrected`</p></li></ul>|
|[{#UUID}]: PCIe utilization, get|<p>Retrieve PCIe utilization information.</p><p>For Maxwell or newer fully supported devices.</p>|Zabbix agent|nvml.device.pci.utilization["{#UUID}"]|
|[{#UUID}]: PCIe utilization, Rx|<p>The PCIe Rx (receive) throughput over 20ms interval on the device.</p><p>For Maxwell or newer fully supported devices.</p>|Dependent item|nvml.device.pci.utilization.rx.rate["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rx_rate_kb_s`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|[{#UUID}]: PCIe utilization, Tx|<p>The PCIe Tx (transmit) throughput over 20ms interval on the device.</p><p>For Maxwell or newer fully supported devices.</p>|Dependent item|nvml.device.pci.utilization.tx.rate["{#UUID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_rate_kb_s`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|

### Trigger prototypes for GPU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nvidia: [{#UUID}]: Encoder utilization exceeded critical threshold|<p>[{#UUID}]: Encoder utilization is very high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.encoder.utilization["{#UUID}"],3m) > {$NVIDIA.ENCODER.UTIL.CRIT}`|Average||
|Nvidia: [{#UUID}]: Encoder utilization exceeded warning threshold|<p>[{#UUID}]: Encoder utilization is high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.encoder.utilization["{#UUID}"],3m) > {$NVIDIA.ENCODER.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Nvidia: [{#UUID}]: Encoder utilization exceeded critical threshold</li></ul>|
|Nvidia: [{#UUID}]: Decoder utilization exceeded critical threshold|<p>[{#UUID}]: Decoder utilization is very high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.decoder.utilization["{#UUID}"],3m) > {$NVIDIA.DECODER.UTIL.CRIT}`|Average||
|Nvidia: [{#UUID}]: Decoder utilization exceeded warning threshold|<p>[{#UUID}]: Decoder utilization is high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.decoder.utilization["{#UUID}"],3m) > {$NVIDIA.DECODER.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Nvidia: [{#UUID}]: Decoder utilization exceeded critical threshold</li></ul>|
|Nvidia: [{#UUID}]: Fan speed exceeded critical threshold|<p>[{#UUID}]: Fan speed is very high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.fan.speed.avg["{#UUID}"],3m) > {$NVIDIA.FAN.SPEED.CRIT}`|Average||
|Nvidia: [{#UUID}]: Fan speed exceeded warning threshold|<p>[{#UUID}]: Fan speed is high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.fan.speed.avg["{#UUID}"],3m) > {$NVIDIA.FAN.SPEED.WARN}`|Warning|**Depends on**:<br><ul><li>Nvidia: [{#UUID}]: Fan speed exceeded critical threshold</li></ul>|
|Nvidia: [{#UUID}]: Power usage exceeded critical threshold|<p>[{#UUID}]: Power usage is very high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`(min(/Nvidia by Zabbix agent 2/nvml.device.power.usage["{#UUID}"],3m) * 100 / last(/Nvidia by Zabbix agent 2/nvml.device.power.limit["{#UUID}"])) > {$NVIDIA.POWER.UTIL.CRIT}`|Average||
|Nvidia: [{#UUID}]: Power usage exceeded warning threshold|<p>[{#UUID}]: Power usage is high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`(min(/Nvidia by Zabbix agent 2/nvml.device.power.usage["{#UUID}"],3m) * 100 / last(/Nvidia by Zabbix agent 2/nvml.device.power.limit["{#UUID}"])) > {$NVIDIA.POWER.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Nvidia: [{#UUID}]: Power usage exceeded critical threshold</li></ul>|
|Nvidia: [{#UUID}]: Power limit has changed|<p>Power limit for the device has changed. Checkout out if it was intentional.</p>|`change(/Nvidia by Zabbix agent 2/nvml.device.power.limit["{#UUID}"]) <> 0`|Info|**Manual close**: Yes|
|Nvidia: [{#UUID}]: Temperature exceeded critical threshold|<p>[{#UUID}]: Temperature is very high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.temperature["{#UUID}"],3m) > {$NVIDIA.TEMPERATURE.CRIT}`|Average||
|Nvidia: [{#UUID}]: Temperature exceeded warning threshold|<p>[{#UUID}]: Temperature is high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.temperature["{#UUID}"],3m) > {$NVIDIA.TEMPERATURE.WARN}`|Warning|**Depends on**:<br><ul><li>Nvidia: [{#UUID}]: Temperature exceeded critical threshold</li></ul>|
|Nvidia: [{#UUID}]: GPU utilization exceeded critical threshold|<p>[{#UUID}]: GPU utilization is very high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.utilization.gpu["{#UUID}"],3m) > {$NVIDIA.GPU.UTIL.CRIT}`|Average||
|Nvidia: [{#UUID}]: GPU utilization exceeded warning threshold|<p>[{#UUID}]: GPU utilization is high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.utilization.gpu["{#UUID}"],3m) > {$NVIDIA.GPU.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Nvidia: [{#UUID}]: GPU utilization exceeded critical threshold</li></ul>|
|Nvidia: [{#UUID}]: Memory utilization exceeded critical threshold|<p>[{#UUID}]: Memory utilization is very high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.utilization.memory["{#UUID}"],3m) > {$NVIDIA.MEMORY.UTIL.CRIT}`|Average||
|Nvidia: [{#UUID}]: Memory utilization exceeded warning threshold|<p>[{#UUID}]: Memory utilization is high. It may indicate abnormal behavior/activity. Change corresponding macro in case of false-positive.</p>|`min(/Nvidia by Zabbix agent 2/nvml.device.utilization.memory["{#UUID}"],3m) > {$NVIDIA.MEMORY.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Nvidia: [{#UUID}]: Memory utilization exceeded critical threshold</li></ul>|
|Nvidia: [{#UUID}]: Encoder average latency is high||`last(/Nvidia by Zabbix agent 2/nvml.device.encoder.stats.latency["{#UUID}"]) > (2 * avg(/Nvidia by Zabbix agent 2/nvml.device.encoder.stats.latency["{#UUID}"],3m))`|Warning||
|Nvidia: [{#UUID}]: Total FB memory has changed|<p>Total FB memory has changed. That could mean possible memory degradation, hardware configuration changes or memory reservation by system or software.</p>|`change(/Nvidia by Zabbix agent 2/nvml.device.memory.fb.total["{#UUID}"]) <> 0`|Warning|**Manual close**: Yes|
|Nvidia: [{#UUID}]: Total BAR1 memory has changed|<p>Total BAR1 memory has changed. That could mean possible memory degradation, hardware configuration changes or memory reservation by system or software.</p>|`change(/Nvidia by Zabbix agent 2/nvml.device.memory.bar1.total["{#UUID}"]) <> 0`|Warning|**Manual close**: Yes|
|Nvidia: [{#UUID}]: Number of corrected memory ECC errors has changed|<p>Increasing number of corrected ECC errors can indicate (but not necessary mean) aging or degrading memory.</p>|`change(/Nvidia by Zabbix agent 2/nvml.device.errors.memory.corrected["{#UUID}"]) <> 0`|Info|**Manual close**: Yes|
|Nvidia: [{#UUID}]: Number of uncorrected memory ECC errors has changed|<p>Increasing number of uncorrected ECC errors can indicate potential issues such as: data corruption, system instability, hardware issues</p>|`change(/Nvidia by Zabbix agent 2/nvml.device.errors.memory.uncorrected["{#UUID}"]) <> 0`|Info|**Manual close**: Yes|
|Nvidia: [{#UUID}]: Number of corrected register file errors has changed|<p>Increasing number of corrected register file errors can indicate (but not necessary mean) wearing, aging or degrading memory.</p>|`change(/Nvidia by Zabbix agent 2/nvml.device.errors.register.corrected["{#UUID}"]) <> 0`|Info|**Manual close**: Yes|
|Nvidia: [{#UUID}]: Number of uncorrected register file errors has changed|<p>Increasing number of uncorrected register file errors can indicate potential issues such as: data corruption, system instability, hardware degradation</p>|`change(/Nvidia by Zabbix agent 2/nvml.device.errors.register.uncorrected["{#UUID}"]) <> 0`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

