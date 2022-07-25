
# Azure by HTTP

## Overview

For Zabbix version: 6.2 and higher  
The template to monitor Microsoft Azure by HTTP.
It works without any external scripts and uses the script item.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via Azure CLI for your subscription.
  `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`
  https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli?toc=%2Fazure%2Fazure-resource-manager%2Ftoc.json&view=azure-cli-latest
2. Link template to the host.
3. Configure macros {$AZURE.APP_ID}, {$AZURE.PASSWORD}, {$AZURE.TENANT_ID} and {$AZURE.SUBSCRIPTION_ID}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP_ID} |<p>Microsoft Azure app ID.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>Response timeout for API.</p> |`15s` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.SUBSCRIPTION_ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT_ID} |<p>Microsoft Azure tenant ID.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Virtual machines discovery |<p>A list of the virtual machines in the subscription.</p> |DEPENDENT |azure.vm.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.resources.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `^Microsoft.Compute/virtualMachines$`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure: Get resources |<p>The JSON with result of API requests.</p> |SCRIPT |azure.get.resources<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.get.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure: There are errors in requests to API |<p>Zabbix has received errors in requests to API.</p> |`length(last(/Azure by HTTP/azure.get.errors))>0` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

