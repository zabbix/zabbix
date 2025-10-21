
# Oracle Cloud by HTTP

## Overview

This template is designed as a master template that discovers various Oracle Cloud Infrastructure (OCI) services
and resources, such as:

* OCI Compute;

* OCI Autonomous Database (serverless);

* OCI Object Storage;

* OCI Virtual Cloud Networks (VCNs);

* OCI Block Volumes;

* OCI Boot Volumes.

For communication with OCI, this template utilizes script items which execute HTTP `GET` and `POST` requests. 
`POST` requests are required for OCI Monitoring API as it utilizes Monitoring Query Language (MQL) which uses an
HTTP request body for queries.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Oracle Cloud Infrastructure

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

## Required setup

For this template to work, it needs authentication details to use in requests. To acquire this information, see
the following steps:

1. Log into your administrator account in Oracle Cloud Console.

2. Create a new user that will be used by Zabbix for monitoring. Optionally, create a new group and assign the monitoring user to this group.

3. Create a new security policy and assign a previously created user or group to it.

4. This policy will contain a set of rules that will give monitoring user/group access to specific resources in your
OCI. Make sure to add the following rules to the policy:
  
    ```
    Allow group 'zabbix_api' to read metrics in tenancy
    Allow group 'zabbix_api' to read instances in tenancy
    Allow group 'zabbix_api' to read subnets in tenancy
    Allow group 'zabbix_api' to read vcns in tenancy
    Allow group 'zabbix_api' to read vnic-attachments in tenancy
    Allow group 'zabbix_api' to read volumes in tenancy
    Allow group 'zabbix_api' to read objectstorage-namespaces in tenancy
    Allow group 'zabbix_api' to read buckets in tenancy
    Allow group 'zabbix_api' to read autonomous-databases in tenancy
    ```
    In the example above, the name of the monitoring group is `zabbix_api`. In your setup, replace it with the
name of your monitoring user/group.
    
    In some cases, these rules might not be enough for the monitoring user to be able to access all resources in your
environment. To fix that, replace the previous rules with this single rule:
    ```
    Allow group 'zabbix_api' to read all-resources in tenancy
    ```

5. Generate an API key pair for your monitoring user - open your monitoring user profile and on the left side,
press `API keys` and then, `Add API key` (if generating a new key pair, do not forget to save the private key).
  
6. After this, Oracle Cloud Console will provide additional information that is required for access, such as:

    * Tenancy OCID;
      
    * User OCID;
      
    * Fingerprint;
      
    * Region.

    > Save this information somewhere or keep this window open. This information will be required in later steps.

7. In Zabbix, create a new host and assign this template to it (Oracle Cloud by HTTP).

8. Open the `Macros` section of the host you created and set the following user macro values according to the
OCI configuration file (from step #6):

    * `{$OCI.API.TENANCY}` - set the tenancy OCID value;
      
    * `{$OCI.API.USER}` - set the user OCID value;
      
    * `{$OCI.API.FINGERPRINT}` - set the fingerprint value;
      
    * `{$OCI.API.PRIVATE.KEY}` - copy and paste the contents of private key file here.

9. After the authentication credentials are entered, you need to identify the OCI API endpoints that match your
region (as provided by Oracle Cloud Console in step #6).
To do so, you can use the OCI [API Reference and Endpoints](https://docs.public.oneportal.content.oci.oraclecloud.com/en-us/iaas/api/#/) list, where each API service has a dedicated page with the respective API endpoints.

   The required API service endpoints are:
  
   * [Core Services API](https://docs.public.oneportal.content.oci.oraclecloud.com/en-us/iaas/api/#/en/iaas/20160918/);
  
   * [Database Service API](https://docs.public.oneportal.content.oci.oraclecloud.com/en-us/iaas/api/#/en/database/20160918/);
  
   * [Object Storage Service API](https://docs.public.oneportal.content.oci.oraclecloud.com/en-us/iaas/api/#/en/objectstorage/20160918/);
  
   * [Monitoring API](https://docs.public.oneportal.content.oci.oraclecloud.com/en-us/iaas/api/#/en/monitoring/20180401/).
        
10. When the API endpoints are identified, you need to set them in Zabbix as user macros to the host that the
template is attached to (similarly to step #8):

    * `{$OCI.API.CORE.HOST}` - Core Services API endpoint, for example, `iaas.eu-stockholm-1.oraclecloud.com`;

    * `{$OCI.API.AUTONOMOUS.DB.HOST}` - Database Service API endpoint, for example, `database.eu-stockholm-1.oraclecloud.com`;

    * `{$OCI.API.OBJECT.STORAGE.HOST}` - Object Storage Service API endpoint, for example, `objectstorage.eu-stockholm-1.oraclecloud.com`;

    * `{$OCI.API.TELEMETRY.HOST}` - Monitoring API endpoint, for example, `telemetry.eu-stockholm-1.oraclecloud.com`;
                            
    > IMPORTANT! API Endpoint URLs need to be entered without the HTTP scheme (`https://`).
                            
11. Once you've completed adding the host to Zabbix, and it will automatically discover services and monitor them.

## Optional setup

### LLD resource filtering by free-form tags of OCI resources

Every LLD rule has pre-added filtering options to avoid discovering unwanted resources, such as terminated OCI 
compute instances. Most of these filters use specific service item names and states, and values of these filters
are defined by the user macros `{$....MATCHES}` and `{$....NOT_MATCHES}`.

To add additional filtering options, every discovery script (except VCN discovery), gathers free-form tag data
about a specific resource. Since free-form tags are completely custom and format or usage will vary between
users, free-from tag filters are not included under LLD filters by default, but can be easily added as they are
already being collected by scripts.

#### Example

1. In Oracle Cloud Console, add a free-form tag to a resource, for example, a compute instance.
The tag key will be `location_group` and the tag value will be `eu-north-1`.

2. Open the Oracle Cloud by HTTP template in Zabbix and go to "Discovery rules".
Find "Compute instances discovery" and open it.

3. Under "LLD macros", add a new macro that will represent this location group tag, for example:
`{#LOCATION_GROUP}` `$.tags.location_group`.
                                                                                            
4. Under the "Filters" tab, there will already be filters regarding the compute instance name and state.
Click "Add" to add a new filter and define the previously created LLD macro and add a matching pattern and
value, for example, `{#LOCATION_GROUP}` `matches` `eu-north-*`.

5. The next time `Compute instances discovery` is executed, it will only discover OCI compute instances that
have the free-form tag `location_group` that matches the regex of `eu-north-*`. You can also experiment with
the LLD filter pattern matching value to receive different matching results for a specified value.
  
### HTTP proxy usage

If needed, you can specify an HTTP proxy for the template by changing the value of the `{$OCI.HTTP.PROXY}` user
macro.

### Custom OK HTTP response

If using a proxy, the returned OK HTTP response could change from "200" to a different value.
In that case, please adjust the user macro `{$OCI.HTTP.RETURN.CODE.OK}`.

### LLD filter value changing

LLD filter values and trigger threshold values can be changed with the respective user macros.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OCI.API.CORE.HOST}|<p>Host for OCI Core Services API endpoint.</p>||
|{$OCI.API.TELEMETRY.HOST}|<p>Host for OCI Monitoring API endpoint.</p>||
|{$OCI.API.OBJECT.STORAGE.HOST}|<p>Host for OCI Object Storage API endpoint.</p>||
|{$OCI.API.AUTONOMOUS.DB.HOST}|<p>Host for OCI Autonomous Database API endpoint.</p>||
|{$OCI.API.COMPARTMENT.COMPUTE}|<p>Compartment OCIDs for compute instances. Can be a single value or a comma separated list of values.</p>||
|{$OCI.API.COMPARTMENT.VCN}|<p>Compartment OCIDs for virtual cloud networks. Can be a single value or a comma separated list of values.</p>||
|{$OCI.API.COMPARTMENT.VOLUME.BLOCK}|<p>Compartment OCIDs for block volumes. Can be a single value or a comma separated list of values.</p>||
|{$OCI.API.COMPARTMENT.VOLUME.BOOT}|<p>Compartment OCIDs for boot volumes. Can be a single value or a comma separated list of values.</p>||
|{$OCI.API.COMPARTMENT.OBJECT.STORAGE}|<p>Compartment OCIDs for object storage buckets. Can be a single value or a comma separated list of values.</p>||
|{$OCI.API.COMPARTMENT.AUTONOMOUS.DB}|<p>Compartment OCIDs for autonomous databases. Can be a single value or a comma separated list of values.</p>||
|{$OCI.API.TENANCY}|<p>OCID of tenancy.</p>||
|{$OCI.API.USER}|<p>OCID of user.</p>||
|{$OCI.API.PRIVATE.KEY}|<p>Entire private key for API access.</p>||
|{$OCI.API.FINGERPRINT}|<p>Fingerprint of private key.</p>||
|{$OCI.COMPUTE.DISCOVERY.STATE.MATCHES}|<p>Sets the regex string of compute instance states to allow in discovery.</p>|`.*`|
|{$OCI.COMPUTE.DISCOVERY.STATE.NOT_MATCHES}|<p>Sets the regex string of compute instance states to ignore in discovery.</p>|`TERMINATED`|
|{$OCI.COMPUTE.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of compute instance names to allow in discovery.</p>|`.*`|
|{$OCI.COMPUTE.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of compute instance names to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.VCN.DISCOVERY.STATE.MATCHES}|<p>Sets the regex string of virtual cloud network states to allow in discovery.</p>|`.*`|
|{$OCI.VCN.DISCOVERY.STATE.NOT_MATCHES}|<p>Sets the regex string of virtual cloud network states to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.VCN.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of virtual cloud network names to allow in discovery.</p>|`.*`|
|{$OCI.VCN.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of virtual cloud network names to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.VOLUME.BLOCK.DISCOVERY.STATE.MATCHES}|<p>Sets the regex string of block volume states to allow in discovery.</p>|`.*`|
|{$OCI.VOLUME.BLOCK.DISCOVERY.STATE.NOT_MATCHES}|<p>Sets the regex string of block volume states to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.VOLUME.BLOCK.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of block volume names to allow in discovery.</p>|`.*`|
|{$OCI.VOLUME.BLOCK.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of block volume names to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.VOLUME.BOOT.DISCOVERY.STATE.MATCHES}|<p>Sets the regex string of boot volume states to allow in discovery.</p>|`.*`|
|{$OCI.VOLUME.BOOT.DISCOVERY.STATE.NOT_MATCHES}|<p>Sets the regex string of boot volume states to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.VOLUME.BOOT.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of boot volume names to allow in discovery.</p>|`.*`|
|{$OCI.VOLUME.BOOT.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of boot volume names to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.OBJECT.STORAGE.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of storage names to allow in discovery.</p>|`.*`|
|{$OCI.OBJECT.STORAGE.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of storage names to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.AUTONOMOUS.DB.DISCOVERY.STATE.MATCHES}|<p>Sets the regex string of autonomous database states to allow in discovery.</p>|`.*`|
|{$OCI.AUTONOMOUS.DB.DISCOVERY.STATE.NOT_MATCHES}|<p>Sets the regex string of autonomous database states to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.AUTONOMOUS.DB.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of autonomous database names to allow in discovery.</p>|`.*`|
|{$OCI.AUTONOMOUS.DB.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of autonomous database names to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.HTTP.PROXY}|<p>Set an HTTP proxy for OCI API requests if needed.</p>||
|{$OCI.HTTP.RETURN.CODE.OK}|<p>Set the HTTP return code that represents an OK response from the API. The default is "200",  but can vary, for example, if a proxy is used.</p>|`200`|

### LLD rule Compute instances discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Compute instances discovery|<p>Discover compute instances.</p>|Script|oci.compute.discovery|

### LLD rule Virtual cloud networks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual cloud networks discovery|<p>Discover virtual cloud networks (VCNs).</p>|Script|oci.vcn.discovery|

### LLD rule Block volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Block volumes discovery|<p>Discover block volumes.</p>|Script|oci.block.volumes.discovery|

### LLD rule Boot volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Boot volumes discovery|<p>Discover boot volumes.</p>|Script|oci.boot.volumes.discovery|

### LLD rule Object storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Object storage discovery|<p>Discover object storage.</p>|Script|oci.object.storage.discovery|

### LLD rule Autonomous database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Autonomous database discovery|<p>Discover autonomous databases.</p>|Script|oci.object.autonomous.db.discovery|

# Oracle Cloud Compute by HTTP

## Overview

This template monitors Oracle Cloud Infrastructure (OCI) single compute instance resources and discovers attached
virtual network interface cards (VNICs) and monitors their resources.

This template is not meant to be used independently, but together with Oracle Cloud by HTTP as a template for
LLD host prototypes.

For communication with OCI, this template utilizes script items which execute HTTP `GET` and `POST` requests.
`POST` requests are required for OCI Monitoring API as it utilizes Monitoring Query Language (MQL) which uses
the HTTP request body for queries.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Oracle Cloud Infrastructure

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template is not meant to be used independently. A host with the `Oracle Cloud by HTTP` template
will discover OCI compute instances automatically, create host prototypes for each discovered instance,
and apply it to this template.

If needed, you can specify an HTTP proxy for the template to use by changing the value of the
`{$OCI.HTTP.PROXY}` user macro.

If using a proxy, the returned OK HTTP response could change from "200" to a different value. In that case,
please adjust the user macro `{$OCI.HTTP.RETURN.CODE.OK}`.

LLD filter values and trigger threshold values can be changed with the respective user macros.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OCI.HTTP.PROXY}|<p>Set an HTTP proxy for OCI API requests if needed.</p>||
|{$OCI.HTTP.RETURN.CODE.OK}|<p>Set the HTTP return code that represents an OK response from the API. The default is "200",  but can vary, for example, if a proxy is used.</p>|`200`|
|{$OCI.COMPUTE.VNIC.DISCOVERY.STATE.MATCHES}|<p>Sets the regex string of VNIC states to allow in discovery.</p>|`.*`|
|{$OCI.COMPUTE.VNIC.DISCOVERY.STATE.NOT_MATCHES}|<p>Sets the regex string of VNIC states to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.COMPUTE.VNIC.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of VNIC names to allow in discovery.</p>|`.*`|
|{$OCI.COMPUTE.VNIC.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of VNIC names to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.COMPUTE.CPU.UTIL.WARN}|<p>Sets the percentage threshold for creating a "warning" severity event about CPU resource utilization.</p>|`75`|
|{$OCI.COMPUTE.CPU.UTIL.HIGH}|<p>Sets the percentage threshold for creating a "high" severity event about CPU resource utilization.</p>|`90`|
|{$OCI.COMPUTE.MEM.UTIL.WARN}|<p>Sets the percentage threshold for creating a "warning" severity event about memory resource utilization.</p>|`75`|
|{$OCI.COMPUTE.MEM.UTIL.HIGH}|<p>Sets the percentage threshold for creating a "high" severity event about memory resource utilization.</p>|`90`|
|{$OCI.COMPUTE.VNIC.CONNTRACK.UTIL.WARN}|<p>Sets the percentage threshold for creating a "warning" severity event about VNIC connection tracking table utilization.</p>|`75`|
|{$OCI.COMPUTE.VNIC.CONNTRACK.UTIL.HIGH}|<p>Sets the percentage threshold for creating a "high" severity event about VNIC connection tracking table utilization.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get instance availability|<p>The accessibility status of a virtual machine instance.</p><p>A value of "1" indicates that the instance is unresponsive due to an issue with</p><p>the infrastructure or the instance itself.</p><p>A value of "0" indicates that an accessibility issue has not been detected.</p><p>If the instance is stopped, then the metric does not have a value.</p>|Script|oci.compute.availability.get|
|State|<p>The current state of the instance.</p>|Script|oci.compute.state.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get VNICs|<p>Gets information about all virtual network interface cards attached to the instance.</p>|Script|oci.compute.vnic.get|
|Get compute metrics|<p>Gets compute instance metrics.</p>|Script|oci.compute.metrics.get|
|CPU utilization, in %|<p>Activity level from the CPU. Expressed as a percentage of the total time.</p>|Dependent item|oci.compute.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CpuUtilization`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory utilization, in %|<p>Space currently in use, measured in pages. Expressed as a percentage of used pages.</p>|Dependent item|oci.compute.mem.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MemoryUtilization`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory allocation stalls|<p>Number of times page reclaim was called directly.</p>|Dependent item|oci.compute.mem.stalls<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MemoryAllocationStalls`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Load average|<p>Average system load calculated over a 1-minute period. Expressed as a number of processes.</p>|Dependent item|oci.compute.load.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LoadAverage`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk bytes read|<p>Read throughput. Expressed as bytes read per interval.</p>|Dependent item|oci.compute.disk.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DiskBytesRead`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk bytes written|<p>Write throughput. Expressed as bytes written per interval.</p>|Dependent item|oci.compute.disk.written<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DiskBytesWritten`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk read I/O|<p>Activity level from I/O reads. Expressed as reads per interval.</p>|Dependent item|oci.compute.disk.io.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DiskIopsRead`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk write I/O|<p>Activity level from I/O writes. Expressed as writes per interval.</p>|Dependent item|oci.compute.disk.io.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DiskIopsWritten`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Network bytes in|<p>Network bytes in for the compute instance.</p>|Dependent item|oci.compute.network.in<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NetworksBytesIn`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Network bytes out|<p>Network bytes out for the compute instance.</p>|Dependent item|oci.compute.network.out<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NetworksBytesOut`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OCI Compute: Compute instance is not available|<p>Current instance availability.</p>|`last(/Oracle Cloud Compute by HTTP/oci.compute.availability.get) = 1`|High||
|OCI Compute: State has changed|<p>Compute instance state has changed.</p>|`last(/Oracle Cloud Compute by HTTP/oci.compute.state.get,#1)<>last(/Oracle Cloud Compute by HTTP/oci.compute.state.get,#2)`|Info|**Manual close**: Yes|
|OCI Compute: Current CPU utilization is too high|<p>Current CPU utilization has exceeded `{$OCI.COMPUTE.CPU.UTIL.HIGH}`% of the max available value.</p>|`min(/Oracle Cloud Compute by HTTP/oci.compute.cpu.util,5m) >= {$OCI.COMPUTE.CPU.UTIL.HIGH}`|High||
|OCI Compute: Current CPU utilization is high|<p>Current CPU utilization has exceeded `{$OCI.COMPUTE.CPU.UTIL.WARN}`% of the max available value.</p>|`min(/Oracle Cloud Compute by HTTP/oci.compute.cpu.util,5m) >= {$OCI.COMPUTE.CPU.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>OCI Compute: Current CPU utilization is too high</li></ul>|
|OCI Compute: Current memory utilization is too high|<p>Current memory utilization has exceeded `{$OCI.COMPUTE.MEM.UTIL.HIGH}`% of the max available value.</p>|`min(/Oracle Cloud Compute by HTTP/oci.compute.mem.util,5m) >= {$OCI.COMPUTE.MEM.UTIL.HIGH}`|High||
|OCI Compute: Current memory utilization is high|<p>Current memory utilization has exceeded `{$OCI.COMPUTE.MEM.UTIL.WARN}`% of the max available value.</p>|`min(/Oracle Cloud Compute by HTTP/oci.compute.mem.util,5m) >= {$OCI.COMPUTE.MEM.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>OCI Compute: Current memory utilization is too high</li></ul>|

### LLD rule VNIC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VNIC discovery|<p>Discover compute instance VNICs.</p>|Dependent item|oci.compute.vnic.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for VNIC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VNIC [{#NAME}]: Attachment state|<p>Current attachment state of the VNIC.</p>|Dependent item|oci.compute.vnic.attachment[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == '{#ID}')].state.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Get metrics|<p>Gets virtual network interface card metrics.</p>|Script|oci.compute.vnic.metrics.get[{#ID}]|
|VNIC [{#NAME}]: Egress packets dropped by security list|<p>Packets sent by the VNIC, destined for the network, dropped due to security rule violations.</p>|Dependent item|oci.compute.vnic.egress.packets.dropped[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicEgressDropsSecurityList`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Ingress packets dropped by security list|<p>Packets received from the network, destined for the VNIC, dropped due to security rule violations.</p>|Dependent item|oci.compute.vnic.ingress.packets.dropped[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicIngressDropsSecurityList`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Bytes from network|<p>Bytes received at the VNIC from the network, after drops.</p>|Dependent item|oci.compute.vnic.net.bytes.ingr[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicFromNetworkBytes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Bytes to network|<p>Bytes sent from the VNIC to the network, before drops.</p>|Dependent item|oci.compute.vnic.net.bytes.egr[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicToNetworkBytes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Packets from network|<p>Packets received at the VNIC from the network, after drops.</p>|Dependent item|oci.compute.vnic.net.packets.ingr[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicFromNetworkPackets`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Packets to network|<p>Packets sent from the VNIC to the network, before drops.</p>|Dependent item|oci.compute.vnic.net.packets.egr[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicToNetworkPackets`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Throttled ingress packets|<p>Packets received from the network, destined for the VNIC, dropped due to throttling.</p>|Dependent item|oci.compute.vnic.net.packets.ingr.throttled[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicIngressDropsThrottle`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Throttled egress packets|<p>Packets sent from the VNIC, destined for the network, dropped due to throttling.</p>|Dependent item|oci.compute.vnic.net.packets.egr.throttled[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicEgressDropsThrottle`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Ingress packets dropped by full connection tracking table|<p>Packets received from the network, destined for the VNIC, dropped due to the full connection tracking table.</p>|Dependent item|oci.compute.vnic.net.packets.ingr.drop[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicIngressDropsConntrackFull`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Egress packets dropped by full connection tracking table|<p>Packets sent from the VNIC, destined for the network, dropped due to the full connection tracking table.</p>|Dependent item|oci.compute.vnic.net.packets.egr.drop[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicEgressDropsConntrackFull`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Connection tracking table utilization, in %|<p>Total utilization percentage (0-100%) of the connection tracking table.</p>|Dependent item|oci.compute.vnic.net.conntrack.util[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicConntrackUtilPercent`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Connection tracking table full|<p>Boolean (0/false, 1/true) that indicates the connection tracking table is full.</p>|Dependent item|oci.compute.vnic.net.conntrack.full[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VnicConntrackIsFull`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Smartnic buffer drops from network|<p>Number of packets dropped in SmartNIC from the network due to buffer exhaustion.</p><p>This metric is available only for Bare Metal Instances. For virtual machines, these metric values are zero.</p>|Dependent item|oci.compute.vnic.net.smartnic.drops[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SmartnicBufferDropsFromNetwork`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VNIC [{#NAME}]: Smartnic buffer drops from host|<p>Number of packets dropped in SmartNIC from the host due to buffer exhaustion.</p><p>This metric is available only for Bare Metal Instances. For virtual machines, these metric values are zero.</p>|Dependent item|oci.compute.vnic.host.smartnic.drops[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SmartnicBufferDropsFromHost`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for VNIC discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OCI Compute: VNIC [{#NAME}]: VNIC is not attached|<p>Virtual network interface card attachment status.</p>|`min(/Oracle Cloud Compute by HTTP/oci.compute.vnic.attachment[{#ID}],5m) >= 3`|High||
|OCI Compute: VNIC [{#NAME}]: Current conntrack table utilization is too high|<p>Current conntrack table utilization has exceeded `{$OCI.COMPUTE.VNIC.CONNTRACK.UTIL.HIGH}`% of the max available value.</p>|`min(/Oracle Cloud Compute by HTTP/oci.compute.vnic.net.conntrack.util[{#ID}],5m) >= {$OCI.COMPUTE.VNIC.CONNTRACK.UTIL.HIGH}`|High||
|OCI Compute: VNIC [{#NAME}]: Current conntrack table utilization is high|<p>Current conntrack table utilization has exceeded `{$OCI.COMPUTE.VNIC.CONNTRACK.UTIL.WARN}`% of the max available value.</p>|`min(/Oracle Cloud Compute by HTTP/oci.compute.vnic.net.conntrack.util[{#ID}],5m) >= {$OCI.COMPUTE.VNIC.CONNTRACK.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>OCI Compute: VNIC [{#NAME}]: Current conntrack table utilization is too high</li></ul>|
|OCI Compute: VNIC [{#NAME}]: Conntrack table full|<p>Virtual network interface card connection tracking table is full.</p>|`min(/Oracle Cloud Compute by HTTP/oci.compute.vnic.net.conntrack.full[{#ID}],5m) = 1`|High||

# Oracle Cloud Object Storage by HTTP

## Overview

This template monitors Oracle Cloud Infrastructure (OCI) object storage resources.

This template is not meant to be used independently, but together with Oracle Cloud by HTTP as a template for
LLD host prototypes.

For communication with OCI, this template utilizes script items which execute HTTP `GET` and `POST` requests.
`POST` requests are required for OCI Monitoring API as it utilizes Monitoring Query Language (MQL) which uses
HTTP request body for queries.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Oracle Cloud Infrastructure

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template is not meant to be used independently. A host with the `Oracle Cloud by HTTP` template will
discover OCI object storage buckets automatically, create host prototypes for each discovered bucket, and apply
it this template.

If needed, you can specify an HTTP proxy for the template to use by changing the value of the
`{$OCI.HTTP.PROXY}` user macro.

If using a proxy, the returned OK HTTP response could change from "200" to a different value.
In that case, please adjust the user macro `{$OCI.HTTP.RETURN.CODE.OK}`.

LLD filter values and trigger threshold values can be changed with the respective user macros.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OCI.HTTP.PROXY}|<p>Set an HTTP proxy for OCI API requests if needed.</p>||
|{$OCI.HTTP.RETURN.CODE.OK}|<p>Set the HTTP return code that represents an OK response from the API. The default is "200",  but can vary, for example, if a proxy is used.</p>|`200`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get frequent metrics|<p>Gets all metrics related to a specific bucket that have frequent update time (100 milliseconds).</p>|Script|oci.obj.storage.metrics.frequent.get|
|All requests count|<p>The total number of all HTTP requests made in a bucket.</p>|Dependent item|oci.obj.storage.requests<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.AllRequests`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Client-side error count|<p>The total number of 4xx errors for requests made in a bucket.</p>|Dependent item|oci.obj.storage.client.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ClientErrors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|First byte latency time|<p>The per-request time measured from the time Object Storage receives the complete request to when Object Storage returns the first byte of the response.</p>|Dependent item|oci.obj.storage.latency.byte<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.FirstByteLatency`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Post object request count|<p>The total number of HTTP `POST` requests made in a bucket.</p>|Dependent item|oci.obj.storage.requests.post<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.PostRequests`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Put object request count|<p>The total number of `PutObject` requests made in a bucket.</p>|Dependent item|oci.obj.storage.requests.put<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.PutRequests`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Overall latency time|<p>The per-request time from the first byte received by Object Storage to the last byte sent from</p><p>Object Storage.</p>|Dependent item|oci.obj.storage.latency.overall<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.TotalRequestLatency`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get hourly metrics|<p>Gets all metrics related to specific bucket that have update time of 1 hour.</p>|Script|oci.obj.storage.metrics.hourly.get|
|Number of objects|<p>The count of objects in the bucket, excluding any multipart upload parts that have not been discarded (aborted) or committed.</p>|Dependent item|oci.obj.storage.objects<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ObjectCount`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Bucket size|<p>The size of the bucket, excluding any multipart upload parts that have not been discarded (aborted) or committed.</p>|Dependent item|oci.obj.storage.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StoredBytes`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Incomplete multipart upload size|<p>The size of any multipart upload parts that have not been discarded (aborted) or committed.</p>|Dependent item|oci.obj.storage.size.incomplete<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.UncommittedParts`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get enabled object lifecycle management|<p>Indicates whether a bucket has any executable Object Lifecycle Management policies configured. `EnabledOLM` emits:</p><p></p><p>    1 - if policies are configured</p><p>    0 - if no policies are configured</p>|Script|oci.obj.storage.metrics.olm.get<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OCI Object Storage: Object lifecycle management policy has changed|<p>The object lifecycle management policy configuration has changed.</p>|`last(/Oracle Cloud Object Storage by HTTP/oci.obj.storage.metrics.olm.get,#1)<>last(/Oracle Cloud Object Storage by HTTP/oci.obj.storage.metrics.olm.get,#2) and length(last(/Oracle Cloud Object Storage by HTTP/oci.obj.storage.metrics.olm.get))>0`|Info||

# Oracle Cloud Autonomous Database by HTTP

## Overview

This template monitors Oracle Cloud Infrastructure (OCI) autonomous database (serverless) resources.

This template is not meant to be used independently, but together with Oracle Cloud by HTTP as a template for
LLD host prototypes.

For communication with OCI, this template utilizes script items which execute HTTP `GET` and `POST` requests.
`POST` requests are required for OCI Monitoring API as it utilizes Monitoring Query Language (MQL) which uses
the HTTP request body for queries.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Oracle Cloud Infrastructure

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template is not meant to be used independently. A host with the `Oracle Cloud by HTTP` template will
discover OCI autonomous databases automatically, create host prototypes for each discovered database, and apply
it to this template.

If needed, you can specify an HTTP proxy for the template to use by changing the value of the
`{$OCI.HTTP.PROXY}` user macro.

If using a proxy, the returned OK HTTP response could change from "200" to a different value. In that case,
please adjust the user macro `{$OCI.HTTP.RETURN.CODE.OK}`.

The LLD filter values and trigger threshold values can be changed with the respective user macros.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OCI.HTTP.PROXY}|<p>Set an HTTP proxy for OCI API requests if needed.</p>||
|{$OCI.HTTP.RETURN.CODE.OK}|<p>Set the HTTP return code that represents an OK response from the API. The default is "200",  but can vary, for example, if a proxy is used.</p>|`200`|
|{$OCI.AUTONOMOUS.DB.CPU.UTIL.WARN}|<p>Sets the percentage threshold for creating a "warning" severity event about CPU resource utilization.</p>|`75`|
|{$OCI.AUTONOMOUS.DB.CPU.UTIL.HIGH}|<p>Sets the percentage threshold for creating a "high" severity event about CPU resource utilization.</p>|`90`|
|{$OCI.AUTONOMOUS.DB.STORAGE.UTIL.WARN}|<p>Sets the percentage threshold for creating a "warning" severity event about storage resource utilization.</p>|`75`|
|{$OCI.AUTONOMOUS.DB.STORAGE.UTIL.HIGH}|<p>Sets the percentage threshold for creating a "high" severity event about storage resource utilization.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|State|<p>Gets the autonomous database state.</p>|Script|oci.aut.db.state<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get frequent metrics|<p>Gets all metrics related to the database that have a collection frequency of 1 minute.</p>|Script|oci.aut.db.metrics.frequent.get|
|CPU time|<p>Average rate of accumulation of CPU time by foreground sessions in the database over the selected time interval.</p>|Dependent item|oci.aut.db.cpu.time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CpuTime`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU utilization, in %|<p>The CPU usage expressed as a percentage, aggregated across all consumer groups.</p><p>The utilization percentage is reported with respect to the number of CPUs the database is allowed to use.</p>|Dependent item|oci.aut.db.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CpuUtilization`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Current logons|<p>The number of successful logons during the selected time interval.</p>|Dependent item|oci.aut.db.logons<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CurrentLogons`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|DB block changes|<p>The number of changes that were part of an update or delete operation that were made to all blocks in the SGA.</p><p></p><p>Such changes generate redo log entries and thus become permanent changes to the database if</p><p>the transaction is committed.</p><p></p><p>This statistic approximates total database work and indicates the rate at which buffers are being dirtied</p><p>during the selected time interval.</p>|Dependent item|oci.aut.db.block.changes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DBBlockChanges`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|DB time|<p>The amount of time database user sessions spend executing database code (CPU time + wait time).</p><p>Database time is used to infer database call latency as it increases in direct proportion</p><p>to both database call latency (response time) and call volume.</p><p></p><p>It is calculated as the average rate of accumulation of database time by foreground sessions in</p><p>the database over the selected time interval.</p>|Dependent item|oci.aut.db.time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DBTime`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Execute count|<p>The number of user and recursive calls that executed SQL statements during the selected time interval.</p>|Dependent item|oci.aut.db.exec.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ExecuteCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Failed connections|<p>The number of failed database connections.</p>|Dependent item|oci.aut.db.conn.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.FailedConnections`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Failed logons|<p>The number of logons that failed because of an invalid user name and/or password</p><p>during the selected time interval.</p>|Dependent item|oci.aut.db.logons.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.FailedLogons`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Parse count (hard)|<p>The number of parse calls (real parses) during the selected time interval.</p><p>A hard parse is an expensive operation in terms of memory use as it requires Oracle to allocate a</p><p>workheap and other memory structures and then build a parse tree.</p>|Dependent item|oci.aut.db.parse.count.hard<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.HardParseCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Session logical reads|<p>The sum of `db block gets` and `consistent gets` during the selected time interval.</p><p>This includes logical reads of database blocks from either the buffer cache or process private memory.</p>|Dependent item|oci.aut.db.logical.reads.session<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LogicalReads`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Parse count (total)|<p>The number of hard and soft parses during the selected time interval.</p>|Dependent item|oci.aut.db.parse.count.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ParseCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Parse count (failures)|<p>The number of parse failures during the selected time interval.</p>|Dependent item|oci.aut.db.parse.count.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ParseFailureCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Physical reads|<p>The number of data blocks read from disk during the selected time interval.</p>|Dependent item|oci.aut.db.physical.reads<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.PhysicalReads`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Physical read total bytes|<p>The size in bytes of disk reads by all database instance activity including</p><p>application reads, backup and recovery, and other utilities during the selected time interval.</p>|Dependent item|oci.aut.db.physical.read.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.PhysicalReadTotalBytes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Physical writes|<p>The number of data blocks written to disk during the selected time interval.</p>|Dependent item|oci.aut.db.physical.writes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.PhysicalWrites`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Physical write total bytes|<p>The size in bytes of all disk writes for the database instance including</p><p>application activity, backup and recovery, and other utilities during the selected time interval.</p>|Dependent item|oci.aut.db.physical.write.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.PhysicalWriteTotalBytes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Queued statements|<p>The number of queued SQL statements aggregated across all consumer groups during the selected time interval.</p>|Dependent item|oci.aut.db.queued.statements<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.QueuedStatements`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Redo generated|<p>Amount of redo generated in bytes during the selected time interval.</p>|Dependent item|oci.aut.db.redo.gen<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.RedoGenerated`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Running statements|<p>The number of running SQL statements aggregated across all consumer groups during the selected time interval.</p>|Dependent item|oci.aut.db.statements.running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.RunningStatements`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Sessions|<p>The number of sessions in the database.</p>|Dependent item|oci.aut.db.sessions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Sessions`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bytes received via SQL*Net from client|<p>The number of bytes received from the client over Oracle Net Services during the selected time interval.</p>|Dependent item|oci.aut.db.sqlnet.bytes.recv.client<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SQLNetBytesFromClient`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bytes received via SQL*Net from DBLink|<p>The number of bytes received from a database link over Oracle Net Services during the selected time interval.</p>|Dependent item|oci.aut.db.sqlnet.bytes.recv.dblink<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SQLNetBytesFromDBLink`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bytes sent via SQL*Net to client|<p>The number of bytes sent to the client from the foreground processes during the selected time interval.</p>|Dependent item|oci.aut.db.sqlnet.bytes.sent.client<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SQLNetBytesToClient`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Bytes sent via SQL*Net to DBLink|<p>The number of bytes sent over a database link during the selected time interval.</p>|Dependent item|oci.aut.db.sqlnet.bytes.sent.dblink<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SQLNetBytesToDBLink`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Transaction count|<p>The combined number of user commits and user rollbacks during the selected time interval.</p>|Dependent item|oci.aut.db.transaction.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.TransactionCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User calls|<p>The combined number of logons, parses, and execute calls during the selected time interval.</p>|Dependent item|oci.aut.db.user.calls<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.UserCalls`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User commits|<p>The number of user commits during the selected time interval.</p><p>When a user commits a transaction, the generated redo that reflects the changes made to database</p><p>blocks must be written to disk. Commits often represent the closest thing to a user transaction rate.</p>|Dependent item|oci.aut.db.user.commits<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.UserCommits`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User rollbacks|<p>Number of times users manually issue the `ROLLBACK` statement or an error occurs during a user's</p><p>transactions during the selected time interval.</p>|Dependent item|oci.aut.db.user.rollbacks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.UserRollbacks`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Wait time|<p>Average rate of accumulation of non-idle wait time by foreground sessions in the database over the selected</p><p>time interval.</p>|Dependent item|oci.aut.db.wait.time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.WaitTime`</p></li><li><p>JavaScript: `return Math.round(value * 100) / 100;`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get database stats|<p>Gets all metrics related to specific database that have a collection frequency of 5 minutes.</p>|Script|oci.aut.db.metrics.stats|
|Database availability|<p>The database is available for connections in the given minute.</p>|Dependent item|oci.aut.db.availability<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.DatabaseAvailability`</p></li><li><p>Discard unchanged with heartbeat: `5h`</p></li></ul>|
|Connection latency|<p>The time taken to connect to an Oracle Autonomous Database Serverless instance in each region</p><p>from a Compute service virtual machine in the same region.</p>|Dependent item|oci.aut.db.latency.conn<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ConnectionLatency`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `5h`</p></li></ul>|
|Query latency|<p>The time taken to display the results of a simple query on the user's screen.</p>|Dependent item|oci.aut.db.latency.query<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.QueryLatency`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `5h`</p></li></ul>|
|Get storage stats|<p>Gets all storage metrics related to a specific database that have a collection frequency of 60 minutes.</p>|Script|oci.aut.db.metrics.storage.stats|
|Storage space allocated|<p>Amount of space allocated to the database for all tablespaces during the selected time interval.</p>|Dependent item|oci.aut.db.storage.space.alloc<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StorageAllocated`</p></li><li><p>Custom multiplier: `1073741824`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Maximum storage space|<p>Maximum amount of storage reserved for the database during the selected time interval.</p>|Dependent item|oci.aut.db.storage.space.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StorageMax`</p></li><li><p>Custom multiplier: `1073741824`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Storage space used|<p>Maximum amount of space used during the selected time interval.</p>|Dependent item|oci.aut.db.storage.space.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StorageUsed`</p></li><li><p>Custom multiplier: `1073741824`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Storage utilization, in %|<p>The percentage of the reserved maximum storage currently allocated for all database tablespaces.</p><p>Represents the total reserved space for all tablespaces.</p>|Dependent item|oci.aut.db.storage.space.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StorageUtilization`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OCI Autonomous DB: Restore has failed|<p>Autonomous database restore has failed.</p>|`last(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.state) = 9`|Warning||
|OCI Autonomous DB: Database is not available or accessible|<p>Autonomous database is not available or accessible.</p>|`last(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.state) = 19 or last(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.state) = 20`|High||
|OCI Autonomous DB: Available, needs attention|<p>Autonomous database is available, but needs attention.</p>|`last(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.state) = 12`|Warning||
|OCI Autonomous DB: State unknown|<p>Autonomous database state is unknown.</p>|`last(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.state) = 0`|Warning||
|OCI Autonomous DB: State has changed|<p>Autonomous database state has changed.</p>|`last(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.state,#1)<>last(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.state,#2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>OCI Autonomous DB: Restore has failed</li><li>OCI Autonomous DB: Database is not available or accessible</li><li>OCI Autonomous DB: Available, needs attention</li><li>OCI Autonomous DB: State unknown</li></ul>|
|OCI Autonomous DB: Current CPU utilization is too high|<p>Current CPU utilization has exceeded `{$OCI.AUTONOMOUS.DB.CPU.UTIL.HIGH}`% of the max available value.</p>|`min(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.cpu.util,5m) >= {$OCI.AUTONOMOUS.DB.CPU.UTIL.HIGH}`|High||
|OCI Autonomous DB: Current CPU utilization is high|<p>Current CPU utilization has exceeded `{$OCI.AUTONOMOUS.DB.CPU.UTIL.WARN}`% of the max available value.</p>|`min(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.cpu.util,5m) >= {$OCI.AUTONOMOUS.DB.CPU.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>OCI Autonomous DB: Current CPU utilization is too high</li></ul>|
|OCI Autonomous DB: Database is not available|<p>Autonomous database is not available.</p>|`last(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.availability) = 0`|High|**Depends on**:<br><ul><li>OCI Autonomous DB: Database is not available or accessible</li></ul>|
|OCI Autonomous DB: Current storage utilization is too high|<p>Current storage utilization has exceeded `{$OCI.AUTONOMOUS.DB.STORAGE.UTIL.HIGH}`% of the max available value.</p>|`min(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.storage.space.util,5m) >= {$OCI.AUTONOMOUS.DB.STORAGE.UTIL.HIGH}`|High||
|OCI Autonomous DB: Current storage utilization is high|<p>Current storage utilization has exceeded `{$OCI.AUTONOMOUS.DB.STORAGE.UTIL.WARN}`% of the max available value.</p>|`min(/Oracle Cloud Autonomous Database by HTTP/oci.aut.db.storage.space.util,5m) >= {$OCI.AUTONOMOUS.DB.STORAGE.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>OCI Autonomous DB: Current storage utilization is too high</li></ul>|

# Oracle Cloud Block Volume by HTTP

## Overview

This template monitors Oracle Cloud Infrastructure (OCI) block volume resources.

This template is not meant to be used independently, but together with Oracle Cloud by HTTP as a template for
LLD host prototypes.

For communication with OCI, this template utilizes script items which execute HTTP `GET` and `POST` requests.
`POST` requests are required for OCI Monitoring API as it utilizes Monitoring Query Language (MQL) which uses
HTTP request body for queries.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Oracle Cloud Infrastructure

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template is not meant to be used independently. A host with the `Oracle Cloud by HTTP` template will
discover OCI block volumes automatically, create host prototypes for each discovered
block volume, and apply it this template.

If needed, you can specify an HTTP proxy for the template to use by changing the value of `{$OCI.HTTP.PROXY}`
user macro.

If using a proxy, the returned OK HTTP response could change from "200" to a different value. In that case,
please adjust the user macro `{$OCI.HTTP.RETURN.CODE.OK}`

LLD filter values and trigger threshold values can be changed with respective user macros.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OCI.HTTP.PROXY}|<p>Set an HTTP proxy for OCI API requests if needed.</p>||
|{$OCI.HTTP.RETURN.CODE.OK}|<p>Set the HTTP return code that represents an OK response from the API. The default is "200",  but can vary, for example, if a proxy is used.</p>|`200`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|State|<p>Gets the block volume state.</p>|Script|oci.block.volume.state<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get metrics|<p>Gets block volume metrics.</p>|Script|oci.block.volume.metrics.get|
|Volume read throughput|<p>Read throughput. Expressed as bytes read per interval.</p>|Dependent item|oci.block.volume.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeReadThroughput`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume write throughput|<p>Write throughput. Expressed as bytes read per interval.</p>|Dependent item|oci.block.volume.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeWriteThroughput`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume read operations|<p>Activity level from I/O reads. Expressed as reads per interval.</p>|Dependent item|oci.block.volume.read.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeReadOps`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume write operations|<p>Activity level from I/O writes. Expressed as writes per interval.</p>|Dependent item|oci.block.volume.write.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeWriteOps`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume throttled operations|<p>Total sum of all the I/O operations that were throttled during a given time interval.</p>|Dependent item|oci.block.volume.throttled.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeThrottledIOs`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume guaranteed VPUs/GB|<p>Rate of change for currently active VPUs/GB.</p><p>Expressed as the average of active VPUs/GB during a given time interval.</p>|Dependent item|oci.block.volume.vpu<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeGuaranteedVPUsPerGB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume guaranteed IOPS|<p>Rate of change for guaranteed IOPS per SLA.</p><p>Expressed as the average of guaranteed IOPS during a given time interval.</p>|Dependent item|oci.block.volume.iops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeGuaranteedIOPS`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume guaranteed throughput|<p>Rate of change for guaranteed throughput per SLA. Expressed as megabytes per interval.</p>|Dependent item|oci.block.volume.throughput<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeGuaranteedThroughput`</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OCI Block Volume: Block volume terminated or faulty|<p>Block volume state is "terminated"/"terminating" or "faulty".</p>|`min(/Oracle Cloud Block Volume by HTTP/oci.block.volume.state,5m) >= 4`|High||
|OCI Block Volume: Block volume state unknown|<p>Block volume state is unknown.</p>|`min(/Oracle Cloud Block Volume by HTTP/oci.block.volume.state,5m) = 0`|Warning|**Depends on**:<br><ul><li>OCI Block Volume: Block volume terminated or faulty</li></ul>|

# Oracle Cloud Boot Volume by HTTP

## Overview

This template monitors Oracle Cloud Infrastructure (OCI) boot volume resources.

This template is not meant to be used independently, but together with Oracle Cloud by HTTP as a template for
LLD host prototypes.

For communication with OCI, this template utilizes script items which execute HTTP `GET` and `POST` requests.
`POST` requests are required for OCI Monitoring API as it utilizes Monitoring Query Language (MQL) which uses
HTTP request body for queries.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Oracle Cloud Infrastructure

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template is not meant to be used independently. A host with the `Oracle Cloud by HTTP` template will
discover OCI boot volumes automatically, create host prototypes for each discovered
boot volume, and apply it this template.

If needed, you can specify an HTTP proxy for the template to use by changing the value of the 
`{$OCI.HTTP.PROXY}` user macro.

If using a proxy, the returned OK HTTP response could change from "200" to a different value. In that case,
please adjust the user macro `{$OCI.HTTP.RETURN.CODE.OK}`

LLD filter values and trigger threshold values can be changed with respective user macros.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OCI.HTTP.PROXY}|<p>Set an HTTP proxy for OCI API requests if needed.</p>||
|{$OCI.HTTP.RETURN.CODE.OK}|<p>Set the HTTP return code that represents an OK response from the API. The default is "200",  but can vary, for example, if a proxy is used.</p>|`200`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|State|<p>Gets the boot volume state.</p>|Script|oci.boot.volume.state<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get metrics|<p>Gets boot volume metrics.</p>|Script|oci.boot.volume.metrics.get|
|Volume read throughput|<p>Read throughput. Expressed as bytes read per interval.</p>|Dependent item|oci.boot.volume.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeReadThroughput`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume write throughput|<p>Write throughput. Expressed as bytes read per interval.</p>|Dependent item|oci.boot.volume.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeWriteThroughput`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume read operations|<p>Activity level from I/O reads. Expressed as reads per interval.</p>|Dependent item|oci.boot.volume.read.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeReadOps`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume write operations|<p>Activity level from I/O writes. Expressed as writes per interval.</p>|Dependent item|oci.boot.volume.write.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeWriteOps`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume throttled operations|<p>Total sum of all the I/O operations that were throttled during a given time interval.</p>|Dependent item|oci.boot.volume.throttled.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeThrottledIOs`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume guaranteed VPUs/GB|<p>Rate of change for currently active VPUs/GB.</p><p>Expressed as the average of active VPUs/GB during a given time interval.</p>|Dependent item|oci.boot.volume.vpu<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeGuaranteedVPUsPerGB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume guaranteed IOPS|<p>Rate of change for guaranteed IOPS per SLA.</p><p>Expressed as the average of guaranteed IOPS during a given time interval.</p>|Dependent item|oci.boot.volume.iops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeGuaranteedIOPS`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume guaranteed throughput|<p>Rate of change for guaranteed throughput per SLA. Expressed as megabytes per interval.</p>|Dependent item|oci.boot.volume.throughput<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VolumeGuaranteedThroughput`</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OCI Boot Volume: Boot volume terminated or faulty|<p>Boot volume state is "terminated"/"terminating" or "faulty".</p>|`min(/Oracle Cloud Boot Volume by HTTP/oci.boot.volume.state,5m) >= 4`|High||
|OCI Boot Volume: Boot volume state unknown|<p>Boot volume state is unknown.</p>|`min(/Oracle Cloud Boot Volume by HTTP/oci.boot.volume.state,5m) = 0`|Warning|**Depends on**:<br><ul><li>OCI Boot Volume: Boot volume terminated or faulty</li></ul>|

# Oracle Cloud Networking by HTTP

## Overview

This template monitors Oracle Cloud Infrastructure (OCI) single virtual network card availability and discovers
attached subnets and monitors their availability.

This template is not meant to be used independently, but together with Oracle Cloud by HTTP as a template for
LLD host prototypes.

For communication with OCI, this template utilizes script items which execute HTTP `GET` requests.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Oracle Cloud Infrastructure

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

This template is not meant to be used independently. A host with the `Oracle Cloud by HTTP` template will
discover OCI virtual cloud networks (VCNs) automatically, create host prototypes for each discovered
VCN, and apply it this template.

If needed, you can specify an HTTP proxy for the template to use by changing the value of `{$OCI.HTTP.PROXY}`
user macro.

If using a proxy, the returned OK HTTP response could change from "200" to a different value. In that case,
please adjust the user macro `{$OCI.HTTP.RETURN.CODE.OK}`

LLD filter values and trigger threshold values can be changed with respective user macros.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OCI.HTTP.PROXY}|<p>Set an HTTP proxy for OCI API requests if needed.</p>||
|{$OCI.HTTP.RETURN.CODE.OK}|<p>Set the HTTP return code that represents an OK response from the API. The default is "200",  but can vary, for example, if a proxy is used.</p>|`200`|
|{$OCI.VCN.SUBNET.DISCOVERY.STATE.MATCHES}|<p>Sets the regex string of VCN subnet states to allow in discovery.</p>|`.*`|
|{$OCI.VCN.SUBNET.DISCOVERY.STATE.NOT_MATCHES}|<p>Sets the regex string of VCN subnet states to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$OCI.VCN.SUBNET.DISCOVERY.NAME.MATCHES}|<p>Sets the regex string of VCN subnet names to allow in discovery.</p>|`.*`|
|{$OCI.VCN.SUBNET.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the regex string of VCN subnet names to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get VCN state|<p>State of the virtual cloud network.</p>|Script|oci.vcn.state.get<p>**Preprocessing**</p><ul><li><p>Replace: `" -> `</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get subnets|<p>Get data about subnets linked to the particular VCN.</p>|Script|oci.vcn.subnets.get|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OCI VCN: VCN state terminated|<p>Virtual cloud network state is "terminated" or "terminating".</p>|`min(/Oracle Cloud Networking by HTTP/oci.vcn.state.get,5m) = 3 or min(/Oracle Cloud Networking by HTTP/oci.vcn.state.get,5m) = 4`|High||
|OCI VCN: VCN state unknown|<p>Virtual cloud network state is unknown.</p>|`min(/Oracle Cloud Networking by HTTP/oci.vcn.state.get,5m) = 0`|Warning||

### LLD rule Subnet discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Subnet discovery|<p>Discover subnets linked to the particular VCN.</p>|Dependent item|oci.vcn.subnet.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Subnet discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Subnet [{#NAME}]: Get subnet state|<p>Current state of subnet.</p>|Dependent item|oci.vcn.subnet.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..[?(@.id == '{#ID}')].state.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Subnet discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OCI VCN: Subnet [{#NAME}]: Subnet state terminated|<p>Virtual cloud network subnet state is "terminated" or "terminating".</p>|`min(/Oracle Cloud Networking by HTTP/oci.vcn.subnet.state[{#ID}],5m) = 3 or min(/Oracle Cloud Networking by HTTP/oci.vcn.subnet.state[{#ID}],5m) = 4`|High||
|OCI VCN: Subnet [{#NAME}]: Subnet state unknown|<p>Virtual cloud network subnet state is unknown.</p>|`min(/Oracle Cloud Networking by HTTP/oci.vcn.subnet.state[{#ID}],5m) = 0`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

