
# OpenWeatherMap by HTTP

## Overview

For Zabbix version: 6.0 and higher  
Get weather metrics from OpenWeatherMap current weather API by HTTP.
It works without any external scripts and uses the Script item.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create a host.

2. Link the template to the host.

3. Customize the values of {$OPENWEATHERMAP.API.TOKEN} and {$LOCATION} macros.  
    OpenWeatherMap API Tokens are available in your OpenWeatherMap account https://home.openweathermap.org/api_keys.  
    Locations can be set by few ways:
      - by geo coordinates (for example: 56.95,24.0833)
      - by location name (for example: Riga)
      - by location ID. Link to the list of city ID: http://bulk.openweathermap.org/sample/city.list.json.gz
      - by zip/post code with a country code (for example: 94040,us)
    A few locations can be added to the macro at the same time by `|` delimiter. 
    For example: `43.81821,7.76115|Riga|2643743|94040,us`.
    Please note that API requests by city name, zip-codes and city id will be deprecated soon.
    
    Language and units macros can be customized too if necessary.
    List of available languages: https://openweathermap.org/current#multi.
    Available units of measurement are: standard, metric and imperial https://openweathermap.org/current#data.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$LANG} |<p>List of available languages https://openweathermap.org/current#multi.</p> |`en` |
|{$LOCATION} |<p>Locations can be set by few ways:</p><p>1. by geo coordinates (for example: 56.95,24.0833)</p><p>2. by location name (for example: Riga)</p><p>3. by location ID. Link to the list of city ID: http://bulk.openweathermap.org/sample/city.list.json.gz</p><p>4. by zip/post code with a country code (for example: 94040,us)</p><p>A few locations can be added to the macro at the same time by <code>\|</code> delimiter. </p><p>For example: <code>43.81821,7.76115\|Riga\|2643743\|94040,us</code>.</p><p>Please note that API requests by city name, zip-codes and city id will be deprecated soon.</p> |`Riga` |
|{$OPENWEATHERMAP.API.ENDPOINT} |<p>OpenWeatherMap API endpoint.</p> |`api.openweathermap.org/data/2.5/weather?` |
|{$OPENWEATHERMAP.API.TOKEN} |<p>Specify openweathermap API key.</p> |`` |
|{$OPENWEATHERMAP.DATA.TIMEOUT} |<p>Response timeout for OpenWeatherMap API.</p> |`3s` |
|{$TEMP.CRIT.HIGH} |<p>Threshold for high temperature trigger.</p> |`30` |
|{$TEMP.CRIT.LOW} |<p>Threshold for low temperature trigger.</p> |`-20` |
|{$UNITS} |<p>Available units of measurement are standard, metric and imperial https://openweathermap.org/current#data.</p> |`metric` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Locations discovery |<p>Weather metrics discovery by location.</p> |DEPENDENT |openweathermap.locations.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.data`</p><p>- NOT_MATCHES_REGEX: `\[\]`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Data |<p>JSON with result of OpenWeatherMap API request by location.</p> |DEPENDENT |openweathermap.location.data[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.[?(@.id=='{#ID}')].first()`</p> |
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Atmospheric pressure |<p>Atmospheric pressure in Pa.</p> |DEPENDENT |openweathermap.pressure[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.main.pressure`</p><p>- MULTIPLIER: `100`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Cloudiness |<p>Cloudiness in %.</p> |DEPENDENT |openweathermap.clouds[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.clouds.all`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Humidity |<p>Humidity in %.</p> |DEPENDENT |openweathermap.humidity[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.main.humidity`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Rain volume for the last one hour |<p>Rain volume for the lat one hour in m.</p> |DEPENDENT |openweathermap.rain[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.rain.1h`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Short weather status |<p>Short weather status description.</p> |DEPENDENT |openweathermap.description[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.weather..description.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Snow volume for the last one hour |<p>Snow volume for the lat one hour in m.</p> |DEPENDENT |openweathermap.snow[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.snow.1h`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Temperature |<p>Atmospheric temperature value.</p> |DEPENDENT |openweathermap.temp[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.main.temp`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Visibility |<p>Visibility in m.</p> |DEPENDENT |openweathermap.visibility[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.visibility`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Wind direction |<p>Wind direction in degrees.</p> |DEPENDENT |openweathermap.wind.direction[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.wind.deg`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OpenWeatherMap |[{#LOCATION}, {#COUNTRY}]: Wind speed |<p>Wind speed value.</p> |DEPENDENT |openweathermap.wind.speed[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.wind.speed`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |Openweathermap: Get data |<p>JSON array with result of OpenWeatherMap API requests.</p> |SCRIPT |openweathermap.get.data<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Zabbix raw items |Openweathermap: Get data collection errors |<p>Errors from get data requests by script item.</p> |DEPENDENT |openweathermap.get.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|[{#LOCATION}, {#COUNTRY}]: Temperature is too high |<p>Temperature value is too high.</p> |`min(/OpenWeatherMap by HTTP/openweathermap.temp[{#ID}],#3)>{$TEMP.CRIT.HIGH}` |AVERAGE |<p>Manual close: YES</p> |
|[{#LOCATION}, {#COUNTRY}]: Temperature is too low |<p>Temperature value is too low.</p> |`max(/OpenWeatherMap by HTTP/openweathermap.temp[{#ID}],#3)<{$TEMP.CRIT.LOW}` |AVERAGE |<p>Manual close: YES</p> |
|Openweathermap: There are errors in requests to OpenWeatherMap API |<p>Zabbix has received errors in requests to OpenWeatherMap API.</p> |`length(last(/OpenWeatherMap by HTTP/openweathermap.get.errors))>0` |AVERAGE |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/).

