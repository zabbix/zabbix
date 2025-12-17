
# Kubernetes nodes by HTTP

## Overview

The template to monitor Kubernetes nodes that work without any external scripts.
It works without external scripts and uses the script item to make HTTP requests to the Kubernetes API.
Install the Zabbix Helm Chart (https://git.zabbix.com/projects/ZT/repos/kubernetes-helm/browse?at=refs%2Fheads%2Fmaster) in your Kubernetes cluster.

Change the values according to the environment in the file $HOME/zabbix_values.yaml.

For example:

Enables use of **Zabbix proxy**
  `enabled: false`

Set the `{$KUBE.API.URL}` such as `<scheme>://<host>:<port>`.

Get the service account name. If a different release name is used.

`kubectl get serviceaccounts -n monitoring`

Get the generated service account token using the command:

`kubectl get secret zabbix-zabbix-helm-chart -n monitoring -o jsonpath={.data.token} | base64 -d`

Then set it to the macro `{$KUBE.API.TOKEN}`.

Set up the macros to filter the metrics of discovered nodes.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Kubernetes 1.19.10

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Install the [Zabbix Helm Chart](https://git.zabbix.com/projects/ZT/repos/kubernetes-helm/browse?at=refs%2Fheads%2Fmaster) in your Kubernetes cluster.

Set the `{$KUBE.API.URL}` such as `<scheme>://<host>:<port>`.

Get the generated service account token using the command:

`kubectl get secret zabbix-zabbix-helm-chart -n monitoring -o jsonpath={.data.token} | base64 -d`

Then set it to the macro `{$KUBE.API.TOKEN}`.
Set `{$KUBE.NODES.ENDPOINT.NAME}` with Zabbix agent's endpoint name. See `kubectl -n monitoring get ep`. Default: `zabbix-zabbix-helm-chart-agent`.

Set up the macros to filter the metrics of discovered nodes and host creation based on host prototypes:

- {$KUBE.LLD.FILTER.NODE.MATCHES}
- {$KUBE.LLD.FILTER.NODE.NOT_MATCHES}
- {$KUBE.LLD.FILTER.NODE.ROLE.MATCHES}
- {$KUBE.LLD.FILTER.NODE.ROLE.NOT_MATCHES}

Set up macros to filter pod metrics by namespace:

- {$KUBE.LLD.FILTER.POD.NAMESPACE.MATCHES}
- {$KUBE.LLD.FILTER.POD.NAMESPACE.NOT_MATCHES}

**Note:** If you have a large cluster, it is highly recommended to set a filter for discoverable pods.

You can use the `{$KUBE.NODE.FILTER.LABELS}`, `{$KUBE.POD.FILTER.LABELS}`, `{$KUBE.NODE.FILTER.ANNOTATIONS}` and `{$KUBE.POD.FILTER.ANNOTATIONS}` macros for advanced filtering of nodes and pods by labels and annotations.

Notes about labels and annotations filters:

- Macro values should be specified separated by commas and must have the key/value form with support for regular expressions in the value (`key1: value, key2: regexp`).
- ECMAScript syntax is used for regular expressions.
- Filters are applied if such a label key exists for the entity that is being filtered (it means that if you specify a key in a filter, entities which do not have this key will not be affected by the filter and will still be discovered, and only entities containing that key will be filtered by the value).
- You can also use the exclamation point symbol (`!`) to invert the filter (`!key: value`).

For example: `kubernetes.io/hostname: kubernetes-node[5-25], !node-role.kubernetes.io/ingress: .*`. As a result, the nodes 5-25 without the "ingress" role will be discovered.


See the Kubernetes documentation for details about labels and annotations:

- <https://kubernetes.io/docs/concepts/overview/working-with-objects/labels/>
- <https://kubernetes.io/docs/concepts/overview/working-with-objects/annotations/>

**Note:** The discovered nodes will be created as separate hosts in Zabbix with the Linux template automatically assigned to them.



### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.TOKEN}|<p>Service account bearer token.</p>||
|{$KUBE.API.URL}|<p>Kubernetes API endpoint URL in the format <scheme>://<host>:<port></p>|`https://kubernetes.default.svc.cluster.local:443`|
|{$KUBE.HTTP.PROXY}|<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p>||
|{$KUBE.NODES.ENDPOINT.NAME}|<p>Kubernetes nodes endpoint name. See "kubectl -n monitoring get ep".</p>|`zabbix-zabbix-helm-chart-agent`|
|{$KUBE.LLD.FILTER.NODE.MATCHES}|<p>Filter of discoverable nodes.</p>|`.*`|
|{$KUBE.LLD.FILTER.NODE.NOT_MATCHES}|<p>Filter to exclude discovered nodes.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.LLD.FILTER.NODE.ROLE.MATCHES}|<p>Filter of discoverable nodes by role.</p>|`.*`|
|{$KUBE.LLD.FILTER.NODE.ROLE.NOT_MATCHES}|<p>Filter to exclude discovered node by role.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.NODE.FILTER.ANNOTATIONS}|<p>Annotations to filter nodes (regex in values are supported). See the template's README.md for details.</p>||
|{$KUBE.NODE.FILTER.LABELS}|<p>Labels to filter nodes (regex in values are supported). See the template's README.md for details.</p>||
|{$KUBE.POD.FILTER.ANNOTATIONS}|<p>Annotations to filter pods (regex in values are supported). See the template's README.md for details.</p>||
|{$KUBE.POD.FILTER.LABELS}|<p>Labels to filter Pods (regex in values are supported). See the template's README.md for details.</p>||
|{$KUBE.LLD.FILTER.POD.NAMESPACE.MATCHES}|<p>Filter of discoverable pods by namespace.</p>|`.*`|
|{$KUBE.LLD.FILTER.POD.NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered pods by namespace.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get nodes|<p>Collecting and processing cluster nodes data via Kubernetes API.</p>|Script|kube.nodes|
|Get nodes check|<p>Data collection check.</p>|Dependent item|kube.nodes.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes nodes: Failed to get nodes||`length(last(/Kubernetes nodes by HTTP/kube.nodes.check))>0`|Warning||

### LLD rule Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node discovery||Dependent item|kube.node.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nodes..filternode`</p></li></ul>|

### Item prototypes for Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NAME}]: Get data|<p>Collecting and processing cluster by node [{#NAME}] data via Kubernetes API.</p>|Dependent item|kube.node.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nodes..[?(@.metadata.name == "{#NAME}")].first()`</p></li></ul>|
|Node [{#NAME}] Addresses: External IP|<p>Typically the IP address of the node that is externally routable (available from outside the cluster).</p>|Dependent item|kube.node.addresses.external_ip[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node [{#NAME}] Addresses: Internal IP|<p>Typically the IP address of the node that is routable only within the cluster.</p>|Dependent item|kube.node.addresses.internal_ip[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node [{#NAME}] Allocatable: CPU|<p>Allocatable CPU.</p><p></p><p>'Allocatable' on a Kubernetes node is defined as the amount of compute resources that are available for pods. The scheduler does not over-subscribe 'Allocatable'. 'CPU', 'memory' and 'ephemeral-storage' are supported as of now.</p>|Dependent item|kube.node.allocatable.cpu[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.allocatable.cpu`</p></li></ul>|
|Node [{#NAME}] Allocatable: Memory|<p>Allocatable Memory.</p><p></p><p>'Allocatable' on a Kubernetes node is defined as the amount of compute resources that are available for pods. The scheduler does not over-subscribe 'Allocatable'. 'CPU', 'memory' and 'ephemeral-storage' are supported as of now.</p>|Dependent item|kube.node.allocatable.memory[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.allocatable.memory`</p></li></ul>|
|Node [{#NAME}] Allocatable: Pods|<p>https://kubernetes.io/docs/tasks/administer-cluster/reserve-compute-resources/</p>|Dependent item|kube.node.allocatable.pods[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.allocatable.pods`</p></li></ul>|
|Node [{#NAME}] Capacity: CPU|<p>CPU resource capacity.</p><p></p><p>https://kubernetes.io/docs/concepts/architecture/nodes/#capacity</p>|Dependent item|kube.node.capacity.cpu[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.capacity.cpu`</p></li></ul>|
|Node [{#NAME}] Capacity: Memory|<p>Memory resource capacity.</p><p></p><p>https://kubernetes.io/docs/concepts/architecture/nodes/#capacity</p>|Dependent item|kube.node.capacity.memory[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.capacity.memory`</p></li></ul>|
|Node [{#NAME}] Capacity: Pods|<p>https://kubernetes.io/docs/tasks/administer-cluster/reserve-compute-resources/</p>|Dependent item|kube.node.capacity.pods[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.capacity.pods`</p></li></ul>|
|Node [{#NAME}] Conditions: Disk pressure|<p>True if pressure exists on the disk size - that is, if the disk capacity is low; otherwise False.</p>|Dependent item|kube.node.conditions.diskpressure[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NAME}] Conditions: Memory pressure|<p>True if pressure exists on the node memory - that is, if the node memory is low; otherwise False.</p>|Dependent item|kube.node.conditions.memorypressure[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NAME}] Conditions: Network unavailable|<p>True if the network for the node is not correctly configured, otherwise False.</p>|Dependent item|kube.node.conditions.networkunavailable[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NAME}] Conditions: PID pressure|<p>True if pressure exists on the processes - that is, if there are too many processes on the node; otherwise False.</p>|Dependent item|kube.node.conditions.pidpressure[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NAME}] Conditions: Ready|<p>True if the node is healthy and ready to accept pods, False if the node is not healthy and is not accepting pods, and Unknown if the node controller has not heard from the node in the last node-monitor-grace-period (default is 40 seconds).</p>|Dependent item|kube.node.conditions.ready[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.conditions[?(@.type == "Ready")].status.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NAME}] Info: Architecture|<p>Node architecture.</p>|Dependent item|kube.node.info.architecture[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.nodeInfo.architecture`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node [{#NAME}] Info: Container runtime|<p>Container runtime.</p><p></p><p>https://kubernetes.io/docs/setup/production-environment/container-runtimes/</p>|Dependent item|kube.node.info.containerruntime[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.nodeInfo.containerRuntimeVersion`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node [{#NAME}] Info: Kernel version|<p>Node kernel version.</p>|Dependent item|kube.node.info.kernelversion[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.nodeInfo.kernelVersion`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node [{#NAME}] Info: Kubelet version|<p>Version of Kubelet.</p>|Dependent item|kube.node.info.kubeletversion[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.nodeInfo.kubeletVersion`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node [{#NAME}] Info: KubeProxy version|<p>Version of KubeProxy.</p>|Dependent item|kube.node.info.kubeproxyversion[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.nodeInfo.kubeProxyVersion`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node [{#NAME}] Info: Operating system|<p>Node operating system.</p>|Dependent item|kube.node.info.operatingsystem[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.nodeInfo.operatingSystem`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node [{#NAME}] Info: OS image|<p>Node OS image.</p>|Dependent item|kube.node.info.osversion[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.nodeInfo.kernelVersion`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node [{#NAME}] Info: Roles|<p>Node roles.</p>|Dependent item|kube.node.info.roles[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.roles`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node [{#NAME}] Limits: CPU|<p>Node CPU limits.</p><p></p><p>https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/</p>|Dependent item|kube.node.limits.cpu[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NAME}] Limits: Memory|<p>Node Memory limits.</p><p></p><p>https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/</p>|Dependent item|kube.node.limits.memory[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NAME}] Requests: CPU|<p>Node CPU requests.</p><p></p><p>https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/</p>|Dependent item|kube.node.requests.cpu[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NAME}] Requests: Memory|<p>Node Memory requests.</p><p></p><p>https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/</p>|Dependent item|kube.node.requests.memory[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NAME}] Uptime|<p>Node uptime.</p>|Dependent item|kube.node.uptime[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metadata.creationTimestamp`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `return Math.floor((Date.now() - new Date(value)) / 1000);`</p></li></ul>|
|Node [{#NAME}] Used: Pods|<p>Current number of pods on the node.</p>|Dependent item|kube.node.used.pods[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.podsCount`</p></li></ul>|

### Trigger prototypes for Node discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes nodes: Node [{#NAME}] Conditions: Pressure exists on the disk size|<p>True - pressure exists on the disk size - that is, if the disk capacity is low; otherwise False.</p>|`last(/Kubernetes nodes by HTTP/kube.node.conditions.diskpressure[{#NAME}])=1`|Warning||
|Kubernetes nodes: Node [{#NAME}] Conditions: Pressure exists on the node memory|<p>True - pressure exists on the node memory - that is, if the node memory is low; otherwise False</p>|`last(/Kubernetes nodes by HTTP/kube.node.conditions.memorypressure[{#NAME}])=1`|Warning||
|Kubernetes nodes: Node [{#NAME}] Conditions: Network is not correctly configured|<p>True - the network for the node is not correctly configured, otherwise False</p>|`last(/Kubernetes nodes by HTTP/kube.node.conditions.networkunavailable[{#NAME}])=1`|Warning||
|Kubernetes nodes: Node [{#NAME}] Conditions: Pressure exists on the processes|<p>True - pressure exists on the processes - that is, if there are too many processes on the node; otherwise False</p>|`last(/Kubernetes nodes by HTTP/kube.node.conditions.pidpressure[{#NAME}])=1`|Warning||
|Kubernetes nodes: Node [{#NAME}] Conditions: Is not in Ready state|<p>False - if the node is not healthy and is not accepting pods.<br>Unknown - if the node controller has not heard from the node in the last node-monitor-grace-period (default is 40 seconds).</p>|`last(/Kubernetes nodes by HTTP/kube.node.conditions.ready[{#NAME}])<>1`|Warning||
|Kubernetes nodes: Node [{#NAME}] Limits: Total CPU limits are too high||`last(/Kubernetes nodes by HTTP/kube.node.limits.cpu[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.cpu[{#NAME}]) > 0.9`|Warning|**Depends on**:<br><ul><li>Kubernetes nodes: Node [{#NAME}] Limits: Total CPU limits are too high</li></ul>|
|Kubernetes nodes: Node [{#NAME}] Limits: Total CPU limits are too high||`last(/Kubernetes nodes by HTTP/kube.node.limits.cpu[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.cpu[{#NAME}]) > 1`|Average||
|Kubernetes nodes: Node [{#NAME}] Limits: Total memory limits are too high||`last(/Kubernetes nodes by HTTP/kube.node.limits.memory[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.memory[{#NAME}]) > 0.9`|Warning|**Depends on**:<br><ul><li>Kubernetes nodes: Node [{#NAME}] Limits: Total memory limits are too high</li></ul>|
|Kubernetes nodes: Node [{#NAME}] Limits: Total memory limits are too high||`last(/Kubernetes nodes by HTTP/kube.node.limits.memory[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.memory[{#NAME}]) > 1`|Average||
|Kubernetes nodes: Node [{#NAME}] Requests: Total CPU requests are too high||`last(/Kubernetes nodes by HTTP/kube.node.requests.cpu[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.cpu[{#NAME}]) > 0.5`|Warning|**Depends on**:<br><ul><li>Kubernetes nodes: Node [{#NAME}] Requests: Total CPU requests are too high</li></ul>|
|Kubernetes nodes: Node [{#NAME}] Requests: Total CPU requests are too high||`last(/Kubernetes nodes by HTTP/kube.node.requests.cpu[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.cpu[{#NAME}]) > 0.8`|Average||
|Kubernetes nodes: Node [{#NAME}] Requests: Total memory requests are too high||`last(/Kubernetes nodes by HTTP/kube.node.requests.memory[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.memory[{#NAME}]) > 0.5`|Warning|**Depends on**:<br><ul><li>Kubernetes nodes: Node [{#NAME}] Requests: Total memory requests are too high</li></ul>|
|Kubernetes nodes: Node [{#NAME}] Requests: Total memory requests are too high||`last(/Kubernetes nodes by HTTP/kube.node.requests.memory[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.memory[{#NAME}]) > 0.8`|Average||
|Kubernetes nodes: Node [{#NAME}] has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Kubernetes nodes by HTTP/kube.node.uptime[{#NAME}])<10`|Info||
|Kubernetes nodes: Node [{#NAME}] Used: Kubelet too many pods|<p>Kubelet is running at capacity.</p>|`last(/Kubernetes nodes by HTTP/kube.node.used.pods[{#NAME}])/ last(/Kubernetes nodes by HTTP/kube.node.capacity.pods[{#NAME}]) > 0.9`|Warning||

### LLD rule Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pod discovery||Dependent item|kube.pod.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Pods`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE}] Namespace [{#NAMESPACE}] Pod [{#POD}]: Get data|<p>Collecting and processing cluster by node [{#NODE}] data via Kubernetes API.</p>|Dependent item|kube.pod.get[{#NAMESPACE}/{#POD}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NODE}] Namespace [{#NAMESPACE}] Pod [{#POD}] Conditions: Containers ready|<p>All containers in the Pod are ready.</p><p></p><p>https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle/#pod-conditions</p>|Dependent item|kube.pod.conditions.containers_ready[{#NAMESPACE}/{#POD}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conditions[?(@.type == "ContainersReady")].status.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NODE}] Namespace [{#NAMESPACE}] Pod [{#POD}] Conditions: Initialized|<p>All init containers have started successfully.</p><p></p><p>https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle/#pod-conditions</p>|Dependent item|kube.pod.conditions.initialized[{#NAMESPACE}/{#POD}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conditions[?(@.type == "Initialized")].status.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NODE}] Namespace [{#NAMESPACE}] Pod [{#POD}] Conditions: Ready|<p>The Pod is able to serve requests and should be added to the load balancing pools of all matching Services.</p><p></p><p>https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle/#pod-conditions</p>|Dependent item|kube.pod.conditions.ready[{#NAMESPACE}/{#POD}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conditions[?(@.type == "Ready")].status.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NODE}] Namespace [{#NAMESPACE}] Pod [{#POD}] Conditions: Scheduled|<p>The Pod has been scheduled to a node.</p><p></p><p>https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle/#pod-conditions</p>|Dependent item|kube.pod.conditions.scheduled[{#NAMESPACE}/{#POD}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conditions[?(@.type == "PodScheduled")].status.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NODE}] Namespace [{#NAMESPACE}] Pod [{#POD}] Containers: Restarts|<p>The number of times the container has been restarted, currently based on the number of dead containers that have not yet been removed. Note that this is calculated from dead containers. But those containers are subject to garbage collection.</p>|Dependent item|kube.pod.containers.restartcount[{#NAMESPACE}/{#POD}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.containers.restartCount`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NODE}] Namespace [{#NAMESPACE}] Pod [{#POD}] Status: Phase|<p>The phase of a Pod is a simple, high-level summary of where the Pod is in its lifecycle.</p><p></p><p>https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle#pod-phase</p>|Dependent item|kube.pod.status.phase[{#NAMESPACE}/{#POD}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.phase`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NODE}] Namespace [{#NAMESPACE}] Pod [{#POD}]: Uptime|<p>Pod uptime.</p>|Dependent item|kube.pod.uptime[{#NAMESPACE}/{#POD}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.startTime`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `return Math.floor((Date.now() - new Date(value)) / 1000);`</p></li></ul>|

### Trigger prototypes for Pod discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes nodes: Node [{#NODE}] Namespace [{#NAMESPACE}] Pod [{#POD}]: Pod is crash looping|<p>Containers of the pod keep restarting. This most likely indicates that the pod is in the CrashLoopBackOff state.</p>|`(last(/Kubernetes nodes by HTTP/kube.pod.containers.restartcount[{#NAMESPACE}/{#POD}])-min(/Kubernetes nodes by HTTP/kube.pod.containers.restartcount[{#NAMESPACE}/{#POD}],15m))>1`|Warning||
|Kubernetes nodes: Node [{#NODE}] Namespace [{#NAMESPACE}] Pod [{#POD}] Status: Kubernetes Pod not healthy|<p>Pod has been in a non-ready state for longer than 10 minutes.</p>|`count(/Kubernetes nodes by HTTP/kube.pod.status.phase[{#NAMESPACE}/{#POD}],10m, "regexp","^(1\|4\|5)$")>=9`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

