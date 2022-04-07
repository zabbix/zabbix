
# Hikvision camera by HTTP

## Overview

For Zabbix version: 6.0 and higher  
Sample device overview page: https://www.hikvision.com/en/products/IP-Products/Network-Cameras/


This template was tested on:

- DS-I220
- DS-I450
- DS-2CD2620F-I
- DS-2CD1631FWD-I
- DS-2CD2020F-I
- DS-2CD2042WD-I
- DS-2CD2T43G0-I5
- DS-2DF5286-AEL
- DS-2CD2T25FWD-I5
- DS-2CD4A35FWD-IZHS
- DS-I200
- DS-2CD1031-I
- DS-2CD2125FWD-IS
- DS-I122
- DS-I203
- DS-N201
- DS-2CD2622FWD-IZS
- DS-2CD2023G0-I

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Define macros according to your camera configuration


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$HIKVISION_ISAPI_PORT} |<p>ISAPI port on device</p> |`80` |
|{$HIKVISION_MAIN_CHANNEL_ID} |<p>Main video stream ID</p> |`101` |
|{$HIKVISION_STREAM_HEIGHT} |<p>Main video stream image height</p> |`1080` |
|{$HIKVISION_STREAM_WIDTH} |<p>Main video stream image width</p> |`1920` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`95` |
|{$PASSWORD} |<p>-</p> |`1234` |
|{$USER} |<p>-</p> |`admin` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|PTZ discovery |<p>-</p> |HTTP_AGENT |hikvision_cam.ptz.discovery<p>**Preprocessing**:</p><p>- XML_TO_JSON<p>- JAVASCRIPT |
|Streaming channels discovery |<p>-</p> |HTTP_AGENT |hikvision_cam.streaming.discovery<p>**Preprocessing**:</p><p>- XML_TO_JSON<p>- JAVASCRIPT<p>**Filter**:</p>AND <p>- {#CHANNEL_ENABLED} MATCHES_REGEX `true`</p><p>**Overrides:**</p><p>trigger disabled non main channels<br> - {#CHANNEL_ID} NOT_MATCHES_REGEX `{$HIKVISION_MAIN_CHANNEL_ID}`<br>  - TRIGGER_PROTOTYPE LIKE `Invalid video stream resolution parameters` - NO_DISCOVER</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |Hikvision camera: CPU utilization |<p>CPU utilization in %</p> |DEPENDENT |hikvision_cam.cpu.util<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceStatus.CPUList.CPU.cpuUtilization`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Hikvision camera |Hikvision camera: Boot loader released date |<p>-</p> |DEPENDENT |hikvision_cam.boot_released_date<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.bootReleasedDate`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Boot loader version |<p>-</p> |DEPENDENT |hikvision_cam.boot_version<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.bootVersion`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Current device time |<p>-</p> |DEPENDENT |hikvision_cam.current_device_time<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceStatus.currentDeviceTime`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Hikvision camera |Hikvision camera: Device description |<p>-</p> |DEPENDENT |hikvision_cam.device_description<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.deviceDescription`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Device ID |<p>-</p> |DEPENDENT |hikvision_cam.device_id<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.deviceID`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Device location |<p>-</p> |DEPENDENT |hikvision_cam.device_location<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.deviceLocation`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Device name |<p>-</p> |DEPENDENT |hikvision_cam.device_name<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.deviceName`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Hikvision camera |Hikvision camera: Device type |<p>-</p> |DEPENDENT |hikvision_cam.device_type<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.deviceType`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Encoder released date |<p>-</p> |DEPENDENT |hikvision_cam.encoder_released_date<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.encoderReleasedDate`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Encoder version |<p>-</p> |DEPENDENT |hikvision_cam.encoder_version<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.encoderVersion`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Firmware released date |<p>-</p> |DEPENDENT |hikvision_cam.firmware_released_date<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.firmwareReleasedDate`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Firmware version |<p>-</p> |DEPENDENT |hikvision_cam.firmware_version<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.firmwareVersion`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Hardware version |<p>-</p> |DEPENDENT |hikvision_cam.hardware_version<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.hardwareVersion`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: MACaddress |<p>-</p> |DEPENDENT |hikvision_cam.mac_address<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.macAddress`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Model |<p>-</p> |DEPENDENT |hikvision_cam.model<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.model`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Serial number |<p>-</p> |DEPENDENT |hikvision_cam.serial_number<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.serialNumber`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Supported beep |<p>-</p> |DEPENDENT |hikvision_cam.support_beep<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.supportBeep`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Supported video loss |<p>-</p> |DEPENDENT |hikvision_cam.support_video_loss<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.supportVideoLoss`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: System contact |<p>-</p> |DEPENDENT |hikvision_cam.system_contact<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.systemContact`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Hikvision camera |Hikvision camera: Telecontrol ID |<p>-</p> |DEPENDENT |hikvision_cam.telecontrol_id<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceInfo.telecontrolID`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `24h`</p> |
|Memory |Hikvision camera: Memory utilization |<p>Memory utilization in %</p> |DEPENDENT |hikvision_cam.memory.usage<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceStatus.MemoryList.Memory.memoryUsage`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|PTZ |Channel "{#PTZ_CHANNEL_ID}": Absolute zoom |<p>-</p> |DEPENDENT |hikvision_cam.ptz.absolute_zoom[{#PTZ_CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.PTZStatus.AbsoluteHigh.absoluteZoom`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|PTZ |Channel "{#PTZ_CHANNEL_ID}": Azimuth |<p>-</p> |DEPENDENT |hikvision_cam.ptz.azimuth[{#PTZ_CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.PTZStatus.AbsoluteHigh.azimuth`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|PTZ |Channel "{#PTZ_CHANNEL_ID}": Elevation |<p>-</p> |DEPENDENT |hikvision_cam.ptz.elevation[{#PTZ_CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.PTZStatus.AbsoluteHigh.elevation`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |Hikvision camera: Get device info: Login status |<p>-</p> |DEPENDENT |hikvision_cam.get_info.login_status<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var data = JSON.parse(value); if ("html" in data){     if (data.html.head.title === "Document Error: Unauthorized")         {return 1}     else if (data.html.head.title === "Connection error")         {return 2} } return 0; `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |Hikvision camera: Get system status: Login status |<p>-</p> |DEPENDENT |hikvision_cam.get_status.login_status<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var data = JSON.parse(value); if ("html" in data){     if (data.html.head.title === "Document Error: Unauthorized")         {return 1}     else if (data.html.head.title === "Connection error")         {return 2} } return 0; `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |Hikvision camera: Get streaming channels: Login status |<p>-</p> |DEPENDENT |hikvision_cam.get_streaming.login_status<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var data = JSON.parse(value); if ("html" in data){     if (data.html.head.title === "Document Error: Unauthorized")         {return 1}     else if (data.html.head.title === "Connection error")         {return 2} } return 0; `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Status |Hikvision camera: Uptime |<p>System uptime in 'N days, hh:mm:ss' format.</p> |DEPENDENT |hikvision_cam.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.DeviceStatus.deviceUpTime`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Constant bitRate |<p>-</p> |DEPENDENT |hikvision_cam.constant_bit_rate[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.constantBitRate`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$.[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Fixed quality |<p>-</p> |DEPENDENT |hikvision_cam.fixed_quality[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.fixedQuality`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": GovLength |<p>-</p> |DEPENDENT |hikvision_cam.gov_length[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.GovLength`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": H264Profile |<p>-</p> |DEPENDENT |hikvision_cam.h264Profile[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.H264Profile`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Key frame interval |<p>-</p> |DEPENDENT |hikvision_cam.key_frame_interval[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.keyFrameInterval`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.01`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Frame rate (max) |<p>-</p> |DEPENDENT |hikvision_cam.max_frame_rate[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.maxFrameRate`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.01`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Smoothing |<p>-</p> |DEPENDENT |hikvision_cam.smoothing[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.smoothing`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Snapshot image type |<p>-</p> |DEPENDENT |hikvision_cam.snap_shot_image_type[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.snapShotImageType`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": VBR lower |<p>-</p> |DEPENDENT |hikvision_cam.vbr_lower_cap[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.vbrLowerCap`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": VBR upper |<p>-</p> |DEPENDENT |hikvision_cam.vbr_upper_cap[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.vbrUpperCap`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Video codec type |<p>-</p> |DEPENDENT |hikvision_cam.video_codec_type[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.videoCodecType`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Video quality control type |<p>-</p> |DEPENDENT |hikvision_cam.video_quality_control_type[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.videoQualityControlType`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Resolution height |<p>-</p> |DEPENDENT |hikvision_cam.video_resolution_height[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.videoResolutionHeight`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Resolution width |<p>-</p> |DEPENDENT |hikvision_cam.video_resolution_width[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.videoResolutionWidth`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Streaming Channel |Channel "{#CHANNEL_ID}": Video scan type |<p>-</p> |DEPENDENT |hikvision_cam.video_scan_type[{#CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.StreamingChannelList.StreamingChannel[?(@.id=={#CHANNEL_ID})].Video.videoScanType`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JSONPATH: `$[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |Hikvision camera: Get device info |<p>Used to get the device information</p> |HTTP_AGENT |hikvision_cam.get_info<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"html":{"head":{"title":"Connection error"}}}`</p><p>- XML_TO_JSON: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"html":{"head":{"title":"Connection error"}}}`</p> |
|Zabbix raw items |Hikvision camera: Get system status |<p>It is used to get the status information of the device</p> |HTTP_AGENT |hikvision_cam.get_status<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"html":{"head":{"title":"Connection error"}}}`</p><p>- XML_TO_JSON: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"html":{"head":{"title":"Connection error"}}}`</p> |
|Zabbix raw items |Hikvision camera: Get streaming channels |<p>Used to get the properties of streaming channels for the device</p> |HTTP_AGENT |hikvision_cam.get_streaming<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"html":{"head":{"title":"Connection error"}}}`</p><p>- XML_TO_JSON: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"html":{"head":{"title":"Connection error"}}}`</p> |
|Zabbix raw items |Hikvision camera: Get PTZ info: Channel "{#PTZ_CHANNEL_ID}": Login status |<p>-</p> |DEPENDENT |hikvision_cam.get_ptz.login_status[{#PTZ_CHANNEL_ID}]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var data = JSON.parse(value); if ("html" in data){     if (data.html.head.title === "Document Error: Unauthorized")         {return 1}     else if (data.html.head.title === "Connection error")         {return 2} } return 0; `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |Hikvision camera: Get PTZ info |<p>High precision positioning which is accurate to a bit after the decimal point</p> |HTTP_AGENT |hikvision_cam.get_ptz[{#PTZ_CHANNEL_ID}]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"html":{"head":{"title":"Connection error"}}}`</p><p>- XML_TO_JSON: ``</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"html":{"head":{"title":"Connection error"}}}`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Hikvision camera: High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Hikvision camera by HTTP/hikvision_cam.cpu.util,5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|Hikvision camera: Version has changed |<p>Hikvision camera version has changed. Ack to close.</p> |`last(/Hikvision camera by HTTP/hikvision_cam.firmware_version,#1)<>last(/Hikvision camera by HTTP/hikvision_cam.firmware_version,#2) and length(last(/Hikvision camera by HTTP/hikvision_cam.firmware_version))>0` |INFO |<p>Manual close: YES</p> |
|Hikvision camera: Camera has been replaced |<p>Camera serial number has changed. Ack to close</p> |`last(/Hikvision camera by HTTP/hikvision_cam.serial_number,#1)<>last(/Hikvision camera by HTTP/hikvision_cam.serial_number,#2) and length(last(/Hikvision camera by HTTP/hikvision_cam.serial_number))>0` |INFO |<p>Manual close: YES</p> |
|Hikvision camera: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Hikvision camera by HTTP/hikvision_cam.memory.usage,5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|Channel "{#PTZ_CHANNEL_ID}": PTZ position changed |<p>The direction of the camera has changed</p> |`last(/Hikvision camera by HTTP/hikvision_cam.ptz.absolute_zoom[{#PTZ_CHANNEL_ID}],#1)<>last(/Hikvision camera by HTTP/hikvision_cam.ptz.absolute_zoom[{#PTZ_CHANNEL_ID}],#2) or  last(/Hikvision camera by HTTP/hikvision_cam.ptz.azimuth[{#PTZ_CHANNEL_ID}],#1)<>last(/Hikvision camera by HTTP/hikvision_cam.ptz.azimuth[{#PTZ_CHANNEL_ID}],#2) or  last(/Hikvision camera by HTTP/hikvision_cam.ptz.elevation[{#PTZ_CHANNEL_ID}],#1)<>last(/Hikvision camera by HTTP/hikvision_cam.ptz.elevation[{#PTZ_CHANNEL_ID}],#2) ` |INFO |<p>Manual close: YES</p> |
|Hikvision camera: Authorisation error |<p>Check the correctness of the authorization data</p> |`last(/Hikvision camera by HTTP/hikvision_cam.get_info.login_status)=1 or last(/Hikvision camera by HTTP/hikvision_cam.get_streaming.login_status)=1 or last(/Hikvision camera by HTTP/hikvision_cam.get_status.login_status)=1 ` |WARNING |<p>Manual close: YES</p> |
|Hikvision camera: Error receiving data |<p>Check the availability of the HTTP port</p> |`last(/Hikvision camera by HTTP/hikvision_cam.get_info.login_status)=2 or last(/Hikvision camera by HTTP/hikvision_cam.get_streaming.login_status)=2 or last(/Hikvision camera by HTTP/hikvision_cam.get_status.login_status)=2 ` |WARNING |<p>Manual close: YES</p> |
|Hikvision camera: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Hikvision camera by HTTP/hikvision_cam.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Channel "{#CHANNEL_ID}": Invalid video stream resolution parameters |<p>expected: {$HIKVISION_STREAM_WIDTH} px x {$HIKVISION_STREAM_HEIGHT} px</p><p>received: {ITEM.LASTVALUE2} x {ITEM.LASTVALUE1}</p> |`last(/Hikvision camera by HTTP/hikvision_cam.video_resolution_height[{#CHANNEL_ID}])<>{$HIKVISION_STREAM_HEIGHT} or last(/Hikvision camera by HTTP/hikvision_cam.video_resolution_width[{#CHANNEL_ID}])<>{$HIKVISION_STREAM_WIDTH} ` |WARNING |<p>Manual close: YES</p> |
|Channel "{#CHANNEL_ID}": Parameters of video stream are changed |<p>-</p> |`last(/Hikvision camera by HTTP/hikvision_cam.fixed_quality[{#CHANNEL_ID}],#1)<>last(/Hikvision camera by HTTP/hikvision_cam.fixed_quality[{#CHANNEL_ID}],#2) or last(/Hikvision camera by HTTP/hikvision_cam.constant_bit_rate[{#CHANNEL_ID}],#1)<>last(/Hikvision camera by HTTP/hikvision_cam.constant_bit_rate[{#CHANNEL_ID}],#2) or last(/Hikvision camera by HTTP/hikvision_cam.video_quality_control_type[{#CHANNEL_ID}],#1)<>last(/Hikvision camera by HTTP/hikvision_cam.video_quality_control_type[{#CHANNEL_ID}],#2) or last(/Hikvision camera by HTTP/hikvision_cam.video_resolution_width[{#CHANNEL_ID}],#1)<>last(/Hikvision camera by HTTP/hikvision_cam.video_resolution_width[{#CHANNEL_ID}],#2) or last(/Hikvision camera by HTTP/hikvision_cam.video_resolution_height[{#CHANNEL_ID}],#1)<>last(/Hikvision camera by HTTP/hikvision_cam.video_resolution_height[{#CHANNEL_ID}],#2) ` |INFO |<p>Manual close: YES</p> |
|Hikvision camera: Authorisation error on get PTZ channels |<p>Check the correctness of the authorization data</p> |`last(/Hikvision camera by HTTP/hikvision_cam.get_ptz.login_status[{#PTZ_CHANNEL_ID}])=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Hikvision camera: Authorisation error</p> |
|Hikvision camera: Error receiving data on PTZ channels |<p>Check the availability of the HTTP port</p> |`last(/Hikvision camera by HTTP/hikvision_cam.get_ptz.login_status[{#PTZ_CHANNEL_ID}])=2` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Hikvision camera: Error receiving data</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

