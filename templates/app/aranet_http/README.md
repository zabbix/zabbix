
# Aranet Cloud

## Overview



## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Aranet Cloud

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ARANET.API.ENDPOINT}|<p>Aranet Cloud API endpoint.</p>|`https://aranet.cloud/api`|
|{$ARANET.API.USERNAME}|<p>Aranet Cloud username.</p>||
|{$ARANET.API.PASSWORD}|<p>Aranet Cloud password.</p>||
|{$ARANET.API.SPACE_NAME}|<p>Aranet Cloud organization name.</p>||
|{$ARANET.LLD.FILTER.SENSOR_NAME.MATCHES}|<p>Filter of discoverable sensors by name.</p>|`.+`|
|{$ARANET.LLD.FILTER.SENSOR_NAME.NOT_MATCHES}|<p>Filter to exclude discoverable sensors by name.</p>|`CHANGE_IF_NEEDED`|
|{$ARANET.LLD.FILTER.SENSOR_ID.MATCHES}|<p>Filter of discoverable sensors by id.</p>|`.+`|
|{$ARANET.LLD.FILTER.GATEWAY_NAME.MATCHES}|<p>Filter of discoverable sensors by gateway name.</p>|`.+`|
|{$ARANET.LLD.FILTER.GATEWAY_NAME.NOT_MATCHES}|<p>Filter to exclude discoverable sensors by gateway name.</p>|`CHANGE_IF_NEEDED`|
|{$ARANET.LLD.FILTER.GATEWAY_ID.MATCHES}|<p>Filter of discoverable sensors by gateway id.</p>|`.+`|
|{$ARANET.BATT.VOLTAGE.MIN.WARN}|<p>Battery voltage warning threshold.</p>|`1`|
|{$ARANET.BATT.VOLTAGE.MIN.CRIT}|<p>Battery voltage critical threshold.</p>|`2`|
|{$ARANET.HUMIDITY.MIN.WARN}|<p>Minimum humidity threshold.</p>|`20`|
|{$ARANET.HUMIDITY.MAX.WARN}|<p>Maximum humidity threshold.</p>|`70`|
|{$ARANET.CO2.MAX.WARN}|<p>CO2 warning threshold.</p>|`600`|
|{$ARANET.CO2.MAX.CRIT}|<p>CO2 critical threshold.</p>|`1000`|
|{$ARANET.LAST_UPDATE.MAX.WARN}|<p>Data update delay threshold.</p>|`1h`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensors discovery|<p>Discovery for Aranet Cloud sensors</p>|Dependent item|aranet.sensor.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `15m`</p></li></ul>|
|Get data||Script|aranet.get_data|

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>Discovery for Aranet Cloud temperature sensors</p>|Dependent item|aranet.temp.discovery|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.temp["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Humidity discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Humidity discovery|<p>Discovery for Aranet Cloud humidity sensors</p>|Dependent item|aranet.humidity.discovery|

### Item prototypes for Humidity discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.humidity["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Humidity discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aranet: {#METRIC}: Low humidity on "[{#GATEWAY_NAME}] {#SENSOR_NAME}"||`max(/Aranet Cloud/aranet.humidity["{#GATEWAY_ID}", "{#SENSOR_ID}"],5m) < {$ARANET.HUMIDITY.MIN.WARN:"{#SENSOR_NAME}"}`|Warning|**Depends on**:<br><ul><li>Aranet: {#METRIC}: High humidity on "[{#GATEWAY_NAME}] {#SENSOR_NAME}"</li></ul>|
|Aranet: {#METRIC}: High humidity on "[{#GATEWAY_NAME}] {#SENSOR_NAME}"||`min(/Aranet Cloud/aranet.humidity["{#GATEWAY_ID}", "{#SENSOR_ID}"],5m) > {$ARANET.HUMIDITY.MAX.WARN:"{#SENSOR_NAME}"}`|High||

### LLD rule RSSI discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|RSSI discovery|<p>Discovery for Aranet Cloud RSSI sensors</p>|Dependent item|aranet.rssi.discovery|

### Item prototypes for RSSI discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.rssi["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Battery voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery voltage discovery|<p>Discovery for Aranet Cloud Battery voltage sensors</p>|Dependent item|aranet.battery.voltage.discovery|

### Item prototypes for Battery voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.battery.voltage["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Battery voltage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aranet: {#METRIC}: Low battery voltage on "[{#GATEWAY_NAME}] {#SENSOR_NAME}"||`max(/Aranet Cloud/aranet.battery.voltage["{#GATEWAY_ID}", "{#SENSOR_ID}"],5m) < {$ARANET.BATT.VOLTAGE.MIN.WARN:"{#SENSOR_NAME}"}`|Warning|**Depends on**:<br><ul><li>Aranet: {#METRIC}: Critically low battery voltage on "[{#GATEWAY_NAME}] {#SENSOR_NAME}"</li></ul>|
|Aranet: {#METRIC}: Critically low battery voltage on "[{#GATEWAY_NAME}] {#SENSOR_NAME}"||`max(/Aranet Cloud/aranet.battery.voltage["{#GATEWAY_ID}", "{#SENSOR_ID}"],5m) < {$ARANET.BATT.VOLTAGE.MIN.CRIT:"{#SENSOR_NAME}"}`|High||

### LLD rule CO2 discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CO2 discovery|<p>Discovery for Aranet Cloud CO2 sensors</p>|Dependent item|aranet.co2.discovery|

### Item prototypes for CO2 discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.co2["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for CO2 discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aranet: {#METRIC}: High CO2 level on "[{#GATEWAY_NAME}] {#SENSOR_NAME}"||`min(/Aranet Cloud/aranet.co2["{#GATEWAY_ID}", "{#SENSOR_ID}"],5m) > {$ARANET.CO2.MAX.WARN:"{#SENSOR_NAME}"}`|Warning|**Depends on**:<br><ul><li>Aranet: {#METRIC}: Critically high CO2 level on "[{#GATEWAY_NAME}] {#SENSOR_NAME}"</li></ul>|
|Aranet: {#METRIC}: Critically high CO2 level on "[{#GATEWAY_NAME}] {#SENSOR_NAME}"||`min(/Aranet Cloud/aranet.co2["{#GATEWAY_ID}", "{#SENSOR_ID}"],5m) > {$ARANET.CO2.MAX.CRIT:"{#SENSOR_NAME}"}`|High||

### LLD rule Atmospheric pressure discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Atmospheric pressure discovery|<p>Discovery for Aranet Cloud atmospheric pressure sensors</p>|Dependent item|aranet.pressure.discovery|

### Item prototypes for Atmospheric pressure discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.pressure["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Voltage discovery|<p>Discovery for Aranet Cloud Voltage sensors</p>|Dependent item|aranet.voltage.discovery|

### Item prototypes for Voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.voltage["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Weight discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Weight discovery|<p>Discovery for Aranet Cloud Weight sensors</p>|Dependent item|aranet.weight.discovery|

### Item prototypes for Weight discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.weight["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Volumetric Water Content discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Volumetric Water Content discovery|<p>Discovery for Aranet Cloud Volumetric Water Content sensors</p>|Dependent item|aranet.volum_water_content.discovery|

### Item prototypes for Volumetric Water Content discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.volumetric.water.content["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule PPFD discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PPFD discovery|<p>Discovery for Aranet Cloud PPFD sensors</p>|Dependent item|aranet.ppfd.discovery|

### Item prototypes for PPFD discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.ppfd["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Distance discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Distance discovery|<p>Discovery for Aranet Cloud Distance sensors</p>|Dependent item|aranet.distance.discovery|

### Item prototypes for Distance discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.distance["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Illuminance discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Illuminance discovery|<p>Discovery for Aranet Cloud Illuminance sensors</p>|Dependent item|aranet.illuminance.discovery|

### Item prototypes for Illuminance discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.illuminance["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule pH discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|pH discovery|<p>Discovery for Aranet Cloud pH sensors</p>|Dependent item|aranet.ph.discovery|

### Item prototypes for pH discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.ph["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Current discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Current discovery|<p>Discovery for Aranet Cloud Current sensors</p>|Dependent item|aranet.current.discovery|

### Item prototypes for Current discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.current["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Soil Dielectric Permittivity discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Soil Dielectric Permittivity discovery|<p>Discovery for Aranet Cloud Soil Dielectric Permittivity sensors</p>|Dependent item|aranet.soil_dielectric_perm.discovery|

### Item prototypes for Soil Dielectric Permittivity discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.soil_dielectric_perm["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Soil Electrical Conductivity discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Soil Electrical Conductivity discovery|<p>Discovery for Aranet Cloud Soil Electrical Conductivity sensors</p>|Dependent item|aranet.soil_electric_cond.discovery|

### Item prototypes for Soil Electrical Conductivity discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.soil_electric_cond["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Pore Electrical Conductivity discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pore Electrical Conductivity discovery|<p>Discovery for Aranet Cloud Pore Electrical Conductivity sensors</p>|Dependent item|aranet.pore_electric_cond.discovery|

### Item prototypes for Pore Electrical Conductivity discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.pore_electric_cond["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Pulses discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pulses discovery|<p>Discovery for Aranet Cloud Pulses sensors</p>|Dependent item|aranet.pulses.discovery|

### Item prototypes for Pulses discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.pulses["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Pulses Cumulative discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pulses Cumulative discovery|<p>Discovery for Aranet Cloud Pulses Cumulative sensors</p>|Dependent item|aranet.pulses_cumulative.discovery|

### Item prototypes for Pulses Cumulative discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.pulses_cumulative["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Differential Pressure discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Differential Pressure discovery|<p>Discovery for Aranet Cloud Differential Pressure sensors</p>|Dependent item|aranet.diff_pressure.discovery|

### Item prototypes for Differential Pressure discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.diff_pressure["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Last update discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Last update discovery|<p>Discovery for Aranet Cloud Last update metric</p>|Dependent item|aranet.last_update.discovery|

### Item prototypes for Last update discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#METRIC}: [{#GATEWAY_NAME}] {#SENSOR_NAME}||Dependent item|aranet.last_update["{#GATEWAY_ID}", "{#SENSOR_ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Last update discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aranet: {#METRIC}: Sensor data "[{#GATEWAY_NAME}] {#SENSOR_NAME}" is not updated||`last(/Aranet Cloud/aranet.last_update["{#GATEWAY_ID}", "{#SENSOR_ID}"]) > {$ARANET.LAST_UPDATE.MAX.WARN:"{#SENSOR_NAME}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

