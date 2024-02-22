
# OpenWeatherMap by HTTP

## Overview

This template is designed for the effortless deployment of OpenWeatherMap monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- OpenWeatherMap API

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

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

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OPENWEATHERMAP.API.TOKEN}|<p>Specify openweathermap API key.</p>||
|{$LANG}|<p>List of available languages https://openweathermap.org/current#multi.</p>|`en`|
|{$LOCATION}|<p>Locations can be set by few ways:</p><p>1. by geo coordinates (for example: 56.95,24.0833)</p><p>2. by location name (for example: Riga)</p><p>3. by location ID. Link to the list of city ID: http://bulk.openweathermap.org/sample/city.list.json.gz</p><p>4. by zip/post code with a country code (for example: 94040,us)</p><p>A few locations can be added to the macro at the same time by `\|` delimiter. </p><p>For example: `43.81821,7.76115\|Riga\|2643743\|94040,us`.</p><p>Please note that API requests by city name, zip-codes and city id will be deprecated soon.</p>|`Riga`|
|{$OPENWEATHERMAP.API.ENDPOINT}|<p>OpenWeatherMap API endpoint.</p>|`api.openweathermap.org/data/2.5/weather?`|
|{$UNITS}|<p>Available units of measurement are standard, metric and imperial https://openweathermap.org/current#data.</p>|`metric`|
|{$TEMP.CRIT.HIGH}|<p>Threshold for high temperature trigger.</p>|`30`|
|{$TEMP.CRIT.LOW}|<p>Threshold for low temperature trigger.</p>|`-20`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Openweathermap: Get data|<p>JSON array with result of OpenWeatherMap API requests.</p>|Script|openweathermap.get.data|
|Openweathermap: Get data collection errors|<p>Errors from get data requests by script item.</p>|Dependent item|openweathermap.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Openweathermap: There are errors in requests to OpenWeatherMap API|<p>Zabbix has received errors in requests to OpenWeatherMap API.</p>|`length(last(/OpenWeatherMap by HTTP/openweathermap.get.errors))>0`|Average|**Manual close**: Yes|

### LLD rule Locations discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Locations discovery|<p>Weather metrics discovery by location.</p>|Dependent item|openweathermap.locations.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li><li><p>Does not match regular expression: `\[\]`</p><p>⛔️Custom on fail: Set error to: `Failed to receive data about required locations from API`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Locations discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#LOCATION}, {#COUNTRY}]: Data|<p>JSON with result of OpenWeatherMap API request by location.</p>|Dependent item|openweathermap.location.data[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.[?(@.id=='{#ID}')].first()`</p></li></ul>|
|[{#LOCATION}, {#COUNTRY}]: Atmospheric pressure|<p>Atmospheric pressure in Pa.</p>|Dependent item|openweathermap.pressure[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.main.pressure`</p></li><li><p>Custom multiplier: `100`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|[{#LOCATION}, {#COUNTRY}]: Cloudiness|<p>Cloudiness in %.</p>|Dependent item|openweathermap.clouds[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.clouds.all`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|[{#LOCATION}, {#COUNTRY}]: Humidity|<p>Humidity in %.</p>|Dependent item|openweathermap.humidity[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.main.humidity`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|[{#LOCATION}, {#COUNTRY}]: Rain volume for the last one hour|<p>Rain volume for the lat one hour in m.</p>|Dependent item|openweathermap.rain[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rain.1h`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|[{#LOCATION}, {#COUNTRY}]: Short weather status|<p>Short weather status description.</p>|Dependent item|openweathermap.description[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.weather..description.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|[{#LOCATION}, {#COUNTRY}]: Snow volume for the last one hour|<p>Snow volume for the lat one hour in m.</p>|Dependent item|openweathermap.snow[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.snow.1h`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|[{#LOCATION}, {#COUNTRY}]: Temperature|<p>Atmospheric temperature value.</p>|Dependent item|openweathermap.temp[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.main.temp`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|[{#LOCATION}, {#COUNTRY}]: Visibility|<p>Visibility in m.</p>|Dependent item|openweathermap.visibility[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.visibility`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|[{#LOCATION}, {#COUNTRY}]: Wind direction|<p>Wind direction in degrees.</p>|Dependent item|openweathermap.wind.direction[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wind.deg`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|[{#LOCATION}, {#COUNTRY}]: Wind speed|<p>Wind speed value.</p>|Dependent item|openweathermap.wind.speed[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wind.speed`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Locations discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|[{#LOCATION}, {#COUNTRY}]: Temperature is too high|<p>Temperature value is too high.</p>|`min(/OpenWeatherMap by HTTP/openweathermap.temp[{#ID}],#3)>{$TEMP.CRIT.HIGH}`|Average|**Manual close**: Yes|
|[{#LOCATION}, {#COUNTRY}]: Temperature is too low|<p>Temperature value is too low.</p>|`max(/OpenWeatherMap by HTTP/openweathermap.temp[{#ID}],#3)<{$TEMP.CRIT.LOW}`|Average|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

