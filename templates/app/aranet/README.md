
# Aranet Cloud

## Overview

For Zabbix version: 5.4 and higher  

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

| Name                                    | Description                                   | Default                    |
|-----------------------------------------|-----------------------------------------------|----------------------------|
| {$ARANET.API.ENDPOINT}                  | <p>Aranet Cloud API endpoint</p>              | `https://aranet.cloud/api` |
| {$ARANET.API.PASSWORD}                  | <p>Aranet Cloud password</p>                  | `<PUT YOUR PASSWORD>`      |
| {$ARANET.API.SPACE_NAME}                | <p>Aranet Cloud space name</p>                | `<PUT YOUR SPACE NAME>`    |
| {$ARANET.API.USERNAME}                  | <p>Aranet Cloud username</p>                  | `<PUT YOUR USERNAME>`      |
| {$ARANET.BATT.VOLTAGE.MIN.CRIT}         | <p>Battery voltage critical threshold</p>     | `2`                        |
| {$ARANET.BATT.VOLTAGE.MIN.WARN}         | <p>Battery voltage warning threshold</p>      | `1`                        |
| {$ARANET.CO2.MAX.CRIT}                  | <p>CO2 critical threshold</p>                 | `1000`                     |
| {$ARANET.CO2.MAX.WARN}                  | <p>CO2 warning threshold</p>                  | `600`                      |
| {$ARANET.HUMIDITY.MAX.WARN}             | <p>Maximum humidity threshold</p>             | `70`                       |
| {$ARANET.HUMIDITY.MIN.WARN}             | <p>Minimum humidity threshold</p>             | `20`                       |
| {$ARANET.LLD.FILTER.SENSOR.MATCHES}     | <p>Filter of discoverable sensors</p>         | `.+`                       |
| {$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES} | <p>Filter to exclude discoverable sensors</p> | `CHANGE_IF_NEEDED`         |

## Template links

There are no template links in this template.

## Discovery rules

| Name                                   | Description                                                        | Type      | Key and additional info                                                                                                                                                                                                                                                                |
|----------------------------------------|--------------------------------------------------------------------|-----------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Temperature sensors discovery          | <p>Discovery for Aranet Cloud temperature sensors</p>              | DEPENDENT | aranet.temp.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `Temperature`</p>                                 |
| Humidity sensors discovery             | <p>Discovery for Aranet Cloud humidity sensors</p>                 | DEPENDENT | aranet.humidity.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `Humidity`</p>                                |
| RSSI sensors discovery                 | <p>Discovery for Aranet Cloud RSSI sensors</p>                     | DEPENDENT | aranet.rssi.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `RSSI`</p>                                        |
| Battery voltage sensors discovery      | <p>Discovery for Aranet Cloud battery voltage sensors</p>          | DEPENDENT | aranet.battery.voltage.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `Battery voltage`</p>                  |
| CO2 sensors discovery                  | <p>Discovery for Aranet Cloud CO2 sensors</p>                      | DEPENDENT | aranet.co2.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `CO₂`</p>                                          |
| Atmospheric pressure sensors discovery | <p>Discovery for Aranet Cloud atmospheric pressure sensors</p>     | DEPENDENT | aranet.pressure.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `Atmospheric Pressure`</p>                    |
| Voltage sensors discovery              | <p>Discovery for Aranet Cloud voltage sensors</p>                  | DEPENDENT | aranet.voltage.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `Voltage`</p>                                  |
| Weight sensors discovery               | <p>Discovery for Aranet Cloud weight sensors</p>                   | DEPENDENT | aranet.weight.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `Weight`</p>                                    |
| Volumetric Water Content discovery     | <p>Discovery for Aranet Cloud volumetric Water Content sensors</p> | DEPENDENT | aranet.olumetric.water.content.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `Volumetric Water Content`</p> |
| PPFD sensors discovery                 | <p>Discovery for Aranet Cloud PPFD sensors</p>                     | DEPENDENT | aranet.ppfd.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `PPFD`</p>                                        |
| Distance sensors discovery             | <p>Discovery for Aranet Cloud distance sensors</p>                 | DEPENDENT | aranet.distance.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `Distance`</p>                                |
| Illuminance sensors discovery          | <p>Discovery for Aranet Cloud illuminance sensors</p>              | DEPENDENT | aranet.illuminance.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `Illuminance`</p>                          |
| pH sensors discovery                   | <p>Discovery for Aranet Cloud pH sensors</p>                       | DEPENDENT | aranet.ph.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `pH`</p>                                            |
| Current sensors discovery              | <p>Discovery for Aranet Cloud current sensors</p>                  | DEPENDENT | aranet.current.discovery<p>**Filter**:</p>AND <p>- A: {#SENSOR} MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.MATCHES}`</p><p>- B: {#SENSOR} NOT_MATCHES_REGEX `{$ARANET.LLD.FILTER.SENSOR.NOT_MATCHES}`</p><p>- C: {#METRIC} MATCHES_REGEX `Current`</p>                                  |

## Items collected

| Group            | Name                      | Description                               | Type      | Key and additional info                                                                                                                                      |
|------------------|---------------------------|-------------------------------------------|-----------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Zabbix_raw_items | Aranet: Sensors discovery | <p>Discovery for Aranet Cloud sensors</p> | DEPENDENT | aranet.sensor.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `Text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `15m`</p> |
| Zabbix_raw_items | Aranet: Get data          |                                           | SCRIPT    | aranet.get_data                                                                                                                                              |

## Triggers

| Name                                                                                                                       | Description | Expression                                                                                              | Severity | Dependencies and additional info                                                                                                                          |
|----------------------------------------------------------------------------------------------------------------------------|-------------|---------------------------------------------------------------------------------------------------------|----------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|
| {#METRIC}: Low humidity on "{#SENSOR}" (below {$ARANET.HUMIDITY.MIN.WARN:"{#SENSOR}"}{#UNIT} for 5m)                       | <p>-</p>    | `{TEMPLATE_NAME:aranet.humidity["{#ID}"].max(5m)} < {$ARANET.HUMIDITY.MIN.WARN:"{#SENSOR}"}`            | WARNING  | <p>**Depends on**:</p><p>- {#METRIC}: High humidity on "{#SENSOR}" (over {$ARANET.HUMIDITY.MAX.WARN:"{#SENSOR}"}{#UNIT} for 5m)</p>                       |
| {#METRIC}: High humidity on "{#SENSOR}" (over {$ARANET.HUMIDITY.MAX.WARN:"{#SENSOR}"}{#UNIT} for 5m)                       | <p>-</p>    | `{TEMPLATE_NAME:aranet.humidity["{#ID}"].min(5m)} > {$ARANET.HUMIDITY.MAX.WARN:"{#SENSOR}"}`            | HIGH     |                                                                                                                                                           |
| {#METRIC}: Low battery voltage on "{#SENSOR}" (below {$ARANET.BATT.VOLTAGE.MIN.WARN:"{#SENSOR}"}{#UNIT} for 5m)            | <p>-</p>    | `{TEMPLATE_NAME:aranet.battery.voltage["{#ID}"].max(5m)} < {$ARANET.BATT.VOLTAGE.MIN.WARN:"{#SENSOR}"}` | WARNING  | <p>**Depends on**:</p><p>- {#METRIC}: Critically low battery voltage on "{#SENSOR}" (below {$ARANET.BATT.VOLTAGE.MIN.CRIT:"{#SENSOR}"}{#UNIT} for 5m)</p> |
| {#METRIC}: Critically low battery voltage on "{#SENSOR}" (below {$ARANET.BATT.VOLTAGE.MIN.CRIT:"{#SENSOR}"}{#UNIT} for 5m) | <p>-</p>    | `{TEMPLATE_NAME:aranet.battery.voltage["{#ID}"].max(5m)} < {$ARANET.BATT.VOLTAGE.MIN.CRIT:"{#SENSOR}"}` | HIGH     |                                                                                                                                                           |
| {#METRIC}: High CO2 level on "{#SENSOR}" (over {$ARANET.CO2.MAX.WARN:"{#SENSOR}"}{#UNIT} for 5m)                           | <p>-</p>    | `{TEMPLATE_NAME:aranet.co2["{#ID}"].min(5m)} > {$ARANET.CO2.MAX.WARN:"{#SENSOR}"}`                      | WARNING  | <p>**Depends on**:</p><p>- {#METRIC}: Critically high CO2 level on "{#SENSOR}" (over {$ARANET.CO2.MAX.CRIT:"{#SENSOR}"}{#UNIT} for 5m)</p>                |
| {#METRIC}: Critically high CO2 level on "{#SENSOR}" (over {$ARANET.CO2.MAX.CRIT:"{#SENSOR}"}{#UNIT} for 5m)                | <p>-</p>    | `{TEMPLATE_NAME:aranet.co2["{#ID}"].min(5m)} > {$ARANET.CO2.MAX.CRIT:"{#SENSOR}"}`                      | HIGH     |                                                                                                                                                           |

## Feedback

Please report any issues with the template at https://support.zabbix.com

