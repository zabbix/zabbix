
# Kubernetes nodes by HTTP

## Overview

For Zabbix version: 6.0 and higher.  
The template to monitor Kubernetes nodes that work without any external scripts.  
It works without external scripts and uses the script item to make HTTP requests to the Kubernetes API.
Install the Zabbix Helm Chart (https://git.zabbix.com/projects/ZT/repos/kubernetes-helm/browse?at=refs%2Fheads%2Frelease%2F6.0) in your Kubernetes cluster.

Set the `{$KUBE.API.ENDPOINT.URL}` such as `<scheme>://<host>:<port>/api`.

Get the generated service account token using the command

`kubectl get secret zabbix-service-account -n monitoring -o jsonpath={.data.token} | base64 -d`

Then set it to the macro `{$KUBE.API.TOKEN}`.

Set up the macros to filter the metrics of discovered nodes


This template was tested on:

- Kubernetes, version 1.19

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Install the [Zabbix Helm Chart](https://git.zabbix.com/projects/ZT/repos/kubernetes-helm/browse?at=refs%2Fheads%2Frelease%2F6.0) in your Kubernetes cluster.

Set the `{$KUBE.API.ENDPOINT.URL}` such as `<scheme>://<host>:<port>/api`.

Get the generated service account token using the command

`kubectl get secret zabbix-service-account -n monitoring -o jsonpath={.data.token} | base64 -d`

Then set it to the macro `{$KUBE.API.TOKEN}`.  
Set `{$KUBE.NODES.ENDPOINT.NAME}` with Zabbix agent's endpoint name. See `kubectl -n monitoring get ep`. Default: `zabbix-zabbix-helm-chrt-agent`.

Set up the macros to filter the metrics of discovered nodes:

- {$KUBE.LLD.FILTER.NODE.MATCHES}
- {$KUBE.LLD.FILTER.NODE.NOT_MATCHES}
- {$KUBE.LLD.FILTER.NODE.ROLE.MATCHES}
- {$KUBE.LLD.FILTER.NODE.ROLE.NOT_MATCHES}

Set up the macros to filter host creation based on host prototypes:

- {$KUBE.LLD.FILTER.NODE_HOST.MATCHES}
- {$KUBE.LLD.FILTER.NODE_HOST.NOT_MATCHES}
- {$KUBE.LLD.FILTER.NODE_HOST.ROLE.MATCHES}
- {$KUBE.LLD.FILTER.NODE_HOST.ROLE.NOT_MATCHES}

Set up macros to filter pod metrics by namespace:

- {$KUBE.LLD.FILTER.POD.NAMESPACE.MATCHES}
- {$KUBE.LLD.FILTER.POD.NAMESPACE.NOT_MATCHES}

**Note**, If you have a large cluster, it is highly recommended to set a filter for discoverable pods.

You can use `{$KUBE.NODE.FILTER.LABELS}`, `{$KUBE.POD.FILTER.LABELS}`, `{$KUBE.NODE.FILTER.ANNOTATIONS}` and `{$KUBE.POD.FILTER.ANNOTATIONS}` macros for advanced filtering nodes and pods by labels and annotations. Macro values are specified separated by commas and must have the key/value form with support for regular expressions in the value.

For example: `kubernetes.io/hostname: kubernetes-node[5-25], !node-role.kubernetes.io/ingress: .*`. As a result, the nodes 5-25 without the "ingress" role will be discovered.


See documentation for details:

- <https://kubernetes.io/docs/concepts/overview/working-with-objects/labels/>
- <https://kubernetes.io/docs/concepts/overview/working-with-objects/annotations/>

**Note**, the discovered nodes will be created as separate hosts in Zabbix with the Linux template automatically assigned to them.



## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.ENDPOINT.URL} |<p>Kubernetes API endpoint URL in the format <scheme>://<host>:<port>/api</p> |`https://localhost:6443/api` |
|{$KUBE.API.TOKEN} |<p>Service account bearer token</p> |`` |
|{$KUBE.LLD.FILTER.NODE.MATCHES} |<p>Filter of discoverable nodes</p> |`.*` |
|{$KUBE.LLD.FILTER.NODE.NOT_MATCHES} |<p>Filter to exclude discovered nodes</p> |`CHANGE_IF_NEEDED` |
|{$KUBE.LLD.FILTER.NODE.ROLE.MATCHES} |<p>Filter of discoverable nodes by role</p> |`.*` |
|{$KUBE.LLD.FILTER.NODE.ROLE.NOT_MATCHES} |<p>Filter to exclude discovered node by role</p> |`CHANGE_IF_NEEDED` |
|{$KUBE.LLD.FILTER.NODE_HOST.MATCHES} |<p>Filter of discoverable cluster nodes</p> |`.*` |
|{$KUBE.LLD.FILTER.NODE_HOST.NOT_MATCHES} |<p>Filter to exclude discovered cluster nodes</p> |`CHANGE_IF_NEEDED` |
|{$KUBE.LLD.FILTER.NODE_HOST.ROLE.MATCHES} |<p>Filter of discoverable nodes hosts by role</p> |`.*` |
|{$KUBE.LLD.FILTER.NODE_HOST.ROLE.NOT_MATCHES} |<p>Filter to exclude discovered cluster nodes by role</p> |`CHANGE_IF_NEEDED` |
|{$KUBE.LLD.FILTER.POD.NAMESPACE.MATCHES} |<p>Filter of discoverable pods by namespace</p> |`.*` |
|{$KUBE.LLD.FILTER.POD.NAMESPACE.NOT_MATCHES} |<p>Filter to exclude discovered pods by namespace</p> |`CHANGE_IF_NEEDED` |
|{$KUBE.NODE.FILTER.ANNOTATIONS} |<p>Annotations to filter nodes (regex in values are supported)</p> |`` |
|{$KUBE.NODE.FILTER.LABELS} |<p>Labels to filter nodes (regex in values are supported)</p> |`` |
|{$KUBE.NODES.ENDPOINT.NAME} |<p>Kubernetes nodes endpoint name. See kubectl -n monitoring get ep</p> |`zabbix-zabbix-helm-chrt-agent` |
|{$KUBE.POD.FILTER.ANNOTATIONS} |<p>Annotations to filter pods (regex in values are supported)</p> |`` |
|{$KUBE.POD.FILTER.LABELS} |<p>Labels to filter Pods (regex in values are supported)</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Cluster node discovery |<p>-</p> |DEPENDENT |kube.node_host.discovery<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$KUBE.LLD.FILTER.NODE_HOST.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$KUBE.LLD.FILTER.NODE_HOST.NOT_MATCHES}`</p><p>- {#ROLES} MATCHES_REGEX `{$KUBE.LLD.FILTER.NODE_HOST.ROLE.MATCHES}`</p><p>- {#ROLES} NOT_MATCHES_REGEX `{$KUBE.LLD.FILTER.NODE_HOST.ROLE.NOT_MATCHES}`</p> |
|Node discovery |<p>-</p> |DEPENDENT |kube.node.discovery<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$KUBE.LLD.FILTER.NODE.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$KUBE.LLD.FILTER.NODE.NOT_MATCHES}`</p><p>- {#ROLES} MATCHES_REGEX `{$KUBE.LLD.FILTER.NODE.ROLE.MATCHES}`</p><p>- {#ROLES} NOT_MATCHES_REGEX `{$KUBE.LLD.FILTER.NODE.ROLE.NOT_MATCHES}`</p> |
|Pod discovery |<p>-</p> |DEPENDENT |kube.pod.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT<p>- DISCARD_UNCHANGED_HEARTBEAT<p>**Filter**:</p>AND <p>- {#NODE} MATCHES_REGEX `{$KUBE.LLD.FILTER.NODE.MATCHES}`</p><p>- {#NODE} NOT_MATCHES_REGEX `{$KUBE.LLD.FILTER.NODE.NOT_MATCHES}`</p><p>- {#NAMESPACE} MATCHES_REGEX `{$KUBE.LLD.FILTER.POD.NAMESPACE.MATCHES}`</p><p>- {#NAMESPACE} NOT_MATCHES_REGEX `{$KUBE.LLD.FILTER.POD.NAMESPACE.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Kubernetes |Kubernetes: Get nodes |<p>Collecting and processing cluster nodes data via Kubernetes API.</p> |SCRIPT |kube.nodes<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Kubernetes |Get nodes check |<p>Data collection check.</p> |DEPENDENT |kube.nodes.check<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node LLD |<p>Generation of data for node discovery rules.</p> |DEPENDENT |kube.nodes.lld<p>**Preprocessing**:</p><p>- JAVASCRIPT: `function parseFilters(filter) {     var pairs = {};     filter.split(/\s*,\s*/).forEach(function (kv) {         if (/([\w\.-]+\/[\w\.-]+):\s*.+/.test(kv)) {             var pair = kv.split(/\s*:\s*/);             pairs[pair[0]] = pair[1];         }     });     return pairs; } function filter(name, data, filters) {     var filtered = true;     if (typeof data === 'object') {         Object.keys(filters).some(function (filter) {             var exclude = filter.match(/^!(.+)/);             if (filter in data || (exclude && exclude[1] in data)) {                 if ((exclude && new RegExp(filters[filter]).test(data[exclude[1]]))                     || (!exclude && !(new RegExp(filters[filter]).test(data[filter])))) {                     Zabbix.log(4, '[ Kubernetes discovery ] Discarded "' + name + '" by filter "' + filter + ': ' + filters[filter] + '"');                     filtered = false;                     return true;                 }             };         });     }     return filtered; } try {     var input = JSON.parse(value),         output = [];         api_url = '{$KUBE.API.ENDPOINT.URL}',         hostname = api_url.match(/\/\/(.+):/);     if (typeof hostname[1] === 'undefined') {         Zabbix.log(4, '[ Kubernetes ] Received incorrect Kubernetes API url: ' + api_url + '. Expected format: <scheme>://<host>:<port>');         throw 'Cannot get hostname from Kubernetes API url. Check debug log for more information.';     };     if (typeof input !== 'object' || typeof input.items === 'undefined') {         Zabbix.log(4, '[ Kubernetes ] Received incorrect JSON: ' + value);         throw 'Incorrect JSON. Check debug log for more information.';     }     var filterLabels = parseFilters('{$KUBE.NODE.FILTER.LABELS}'),         filterAnnotations = parseFilters('{$KUBE.NODE.FILTER.ANNOTATIONS}');     input.items.forEach(function (node) {         if (filter(node.metadata.name, node.metadata.labels, filterLabels)             && filter(node.metadata.name, node.metadata.annotations, filterAnnotations)) {             Zabbix.log(4, '[ Kubernetes discovery ] Filtered node "' + node.metadata.name + '"');             var internalIPs = node.status.addresses.filter(function (addr) {                 return addr.type === 'InternalIP';             });             var internalIP = internalIPs.length && internalIPs[0].address;             if (internalIP in input.endpointIPs) {                 output.push({                     '{#NAME}': node.metadata.name,                     '{#IP}': internalIP,                     '{#ROLES}': node.status.roles,                     '{#ARCH}': node.metadata.labels['kubernetes.io/arch'] || '',                     '{#OS}': node.metadata.labels['kubernetes.io/os'] || '',                     '{#CLUSTER_HOSTNAME}': hostname[1]                 });             }             else {                 Zabbix.log(4, '[ Kubernetes discovery ] Node "' + node.metadata.name + '" is not included in the list of endpoint IPs');             }         }     });     return JSON.stringify(output); } catch (error) {     error += (String(error).endsWith('.')) ? '' : '.';     Zabbix.log(3, '[ Kubernetes discovery ] ERROR: ' + error);     throw 'Discovery error: ' + error; } `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}]: Get data |<p>Collecting and processing cluster by node [{#NAME}] data via Kubernetes API.</p> |DEPENDENT |kube.node.get[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.items[?(@.metadata.name == "{#NAME}")].first()`</p> |
|Kubernetes |Node [{#NAME}] Addresses: External IP |<p>Typically the IP address of the node that is externally routable (available from outside the cluster).</p> |DEPENDENT |kube.node.addresses.external_ip[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.addresses[?(@.type == "ExternalIP")].address.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}] Addresses: Internal IP |<p>Typically the IP address of the node that is routable only within the cluster.</p> |DEPENDENT |kube.node.addresses.internal_ip[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.addresses[?(@.type == "InternalIP")].address.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}] Allocatable: CPU |<p>Allocatable CPU.</p><p>'Allocatable' on a Kubernetes node is defined as the amount of compute resources that are available for pods. The scheduler does not over-subscribe 'Allocatable'. 'CPU', 'memory' and 'ephemeral-storage' are supported as of now.</p> |DEPENDENT |kube.node.allocatable.cpu[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.allocatable.cpu`</p> |
|Kubernetes |Node [{#NAME}] Allocatable: Memory |<p>Allocatable Memory.</p><p>'Allocatable' on a Kubernetes node is defined as the amount of compute resources that are available for pods. The scheduler does not over-subscribe 'Allocatable'. 'CPU', 'memory' and 'ephemeral-storage' are supported as of now.</p> |DEPENDENT |kube.node.allocatable.memory[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.allocatable.memory`</p> |
|Kubernetes |Node [{#NAME}] Allocatable: Pods |<p>https://kubernetes.io/docs/tasks/administer-cluster/reserve-compute-resources/</p> |DEPENDENT |kube.node.allocatable.pods[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.allocatable.pods`</p> |
|Kubernetes |Node [{#NAME}] Capacity: CPU |<p>CPU resource capacity.</p><p>https://kubernetes.io/docs/concepts/architecture/nodes/#capacity</p> |DEPENDENT |kube.node.capacity.cpu[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.capacity.cpu`</p> |
|Kubernetes |Node [{#NAME}] Capacity: Memory |<p>Memory resource capacity.</p><p>https://kubernetes.io/docs/concepts/architecture/nodes/#capacity</p> |DEPENDENT |kube.node.capacity.memory[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.capacity.memory`</p> |
|Kubernetes |Node [{#NAME}] Capacity: Pods |<p>https://kubernetes.io/docs/tasks/administer-cluster/reserve-compute-resources/</p> |DEPENDENT |kube.node.capacity.pods[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.capacity.pods`</p> |
|Kubernetes |Node [{#NAME}] Conditions: Disk pressure |<p>True if pressure exists on the disk size - that is, if the disk capacity is low; otherwise False.</p> |DEPENDENT |kube.node.conditions.diskpressure[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.conditions[?(@.type == "DiskPressure")].status.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return ['True', 'False', 'Unknown'].indexOf(value) + 1 || 'Problem with status processing in JS'; `</p> |
|Kubernetes |Node [{#NAME}] Conditions: Memory pressure |<p>True if pressure exists on the node memory - that is, if the node memory is low; otherwise False.</p> |DEPENDENT |kube.node.conditions.memorypressure[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.conditions[?(@.type == "MemoryPressure")].status.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return ['True', 'False', 'Unknown'].indexOf(value) + 1 || 'Problem with status processing in JS'; `</p> |
|Kubernetes |Node [{#NAME}] Conditions: Network unavailable |<p>True if the network for the node is not correctly configured, otherwise False.</p> |DEPENDENT |kube.node.conditions.networkunavailable[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.conditions[?(@.type == "NetworkUnavailable")].status.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return ['True', 'False', 'Unknown'].indexOf(value) + 1 || 'Problem with status processing in JS'; `</p> |
|Kubernetes |Node [{#NAME}] Conditions: PID pressure |<p>True if pressure exists on the processes - that is, if there are too many processes on the node; otherwise False.</p> |DEPENDENT |kube.node.conditions.pidpressure[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.conditions[?(@.type == "PIDPressure")].status.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return ['True', 'False', 'Unknown'].indexOf(value) + 1 || 'Problem with status processing in JS'; `</p> |
|Kubernetes |Node [{#NAME}] Conditions: Ready |<p>True if the node is healthy and ready to accept pods, False if the node is not healthy and is not accepting pods, and Unknown if the node controller has not heard from the node in the last node-monitor-grace-period (default is 40 seconds).</p> |DEPENDENT |kube.node.conditions.ready[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.conditions[?(@.type == "Ready")].status.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return ['True', 'False', 'Unknown'].indexOf(value) + 1 || 'Problem with status processing in JS'; `</p> |
|Kubernetes |Node [{#NAME}] Info: Architecture |<p>Node architecture.</p> |DEPENDENT |kube.node.info.architecture[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.nodeInfo.architecture`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}] Info: Container runtime |<p>Container runtime.</p><p>https://kubernetes.io/docs/setup/production-environment/container-runtimes/</p> |DEPENDENT |kube.node.info.containerruntime[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.nodeInfo.containerRuntimeVersion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}] Info: Kernel version |<p>Node kernel version.</p> |DEPENDENT |kube.node.info.kernelversion[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.nodeInfo.kernelVersion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}] Info: Kubelet version |<p>Version of Kubelet.</p> |DEPENDENT |kube.node.info.kubeletversion[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.nodeInfo.kubeletVersion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}] Info: KubeProxy version |<p>Version of KubeProxy.</p> |DEPENDENT |kube.node.info.kubeproxyversion[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.nodeInfo.kubeProxyVersion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}] Info: Operating system |<p>Node operating system.</p> |DEPENDENT |kube.node.info.operatingsystem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.nodeInfo.operatingSystem`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}] Info: OS image |<p>Node OS image.</p> |DEPENDENT |kube.node.info.osversion[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.nodeInfo.kernelVersion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}] Info: Roles |<p>Node roles.</p> |DEPENDENT |kube.node.info.roles[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.roles`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Kubernetes |Node [{#NAME}] Limits: CPU |<p>Node CPU limits.</p><p>https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/</p> |DEPENDENT |kube.node.limits.cpu[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pods[*].containers.limits.cpu.sum()`</p> |
|Kubernetes |Node [{#NAME}] Limits: Memory |<p>Node Memory limits.</p><p>https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/</p> |DEPENDENT |kube.node.limits.memory[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pods[*].containers.limits.memory.sum()`</p> |
|Kubernetes |Node [{#NAME}] Requests: CPU |<p>Node CPU requests.</p><p>https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/</p> |DEPENDENT |kube.node.requests.cpu[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pods[*].containers.requests.cpu.sum()`</p> |
|Kubernetes |Node [{#NAME}] Requests: Memory |<p>Node Memory requests.</p><p>https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/</p> |DEPENDENT |kube.node.requests.memory[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pods[*].containers.requests.memory.sum()`</p> |
|Kubernetes |Node [{#NAME}] Uptime |<p>Node uptime.</p> |DEPENDENT |kube.node.uptime[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.metadata.creationTimestamp`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return Math.floor((Date.now() - new Date(value)) / 1000);`</p> |
|Kubernetes |Node [{#NAME}] Used: Pods |<p>Current number of pods on the node.</p> |DEPENDENT |kube.node.used.pods[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status.podsCount`</p> |
|Kubernetes |Node [{#NODE}] Pod [{#POD}]: Get data |<p>Collecting and processing cluster by node [{#NODE}] data via Kubernetes API.</p> |DEPENDENT |kube.pod.get[{#POD}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.items[?(@.metadata.name == "{#NODE}")].pods[?(@.name == "{#POD}")].first()`</p> |
|Kubernetes |Node [{#NODE}] Pod [{#POD}] Conditions: Containers ready |<p>All containers in the Pod are ready.</p><p>https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle/#pod-conditions</p> |DEPENDENT |kube.pod.conditions.containers_ready[{#POD}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.conditions[?(@.type == "ContainersReady")].status.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return ['True', 'False', 'Unknown'].indexOf(value) + 1 || 'Problem with status processing in JS'; `</p> |
|Kubernetes |Node [{#NODE}] Pod [{#POD}] Conditions: Initialized |<p>All init containers have started successfully.</p><p>https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle/#pod-conditions</p> |DEPENDENT |kube.pod.conditions.initialized[{#POD}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.conditions[?(@.type == "Initialized")].status.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return ['True', 'False', 'Unknown'].indexOf(value) + 1 || 'Problem with status processing in JS'; `</p> |
|Kubernetes |Node [{#NODE}] Pod [{#POD}] Conditions: Ready |<p>The Pod is able to serve requests and should be added to the load balancing pools of all matching Services.</p><p>https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle/#pod-conditions</p> |DEPENDENT |kube.pod.conditions.ready[{#POD}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.conditions[?(@.type == "Ready")].status.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return ['True', 'False', 'Unknown'].indexOf(value) + 1 || 'Problem with status processing in JS'; `</p> |
|Kubernetes |Node [{#NODE}] Pod [{#POD}] Conditions: Scheduled |<p>The Pod has been scheduled to a node.</p><p>https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle/#pod-conditions</p> |DEPENDENT |kube.pod.conditions.scheduled[{#POD}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.conditions[?(@.type == "PodScheduled")].status.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return ['True', 'False', 'Unknown'].indexOf(value) + 1 || 'Problem with status processing in JS'; `</p> |
|Kubernetes |Node [{#NODE}] Pod [{#POD}] Containers: Restarts |<p>The number of times the container has been restarted, currently based on the number of dead containers that have not yet been removed. Note that this is calculated from dead containers. But those containers are subject to garbage collection.</p> |DEPENDENT |kube.pod.containers.restartcount[{#POD}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.containers.restartCount`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Kubernetes |Node [{#NODE}] Pod [{#POD}] Status: Phase |<p>The phase of a Pod is a simple, high-level summary of where the Pod is in its lifecycle.</p><p>https://kubernetes.io/docs/concepts/workloads/pods/pod-lifecycle#pod-phase</p> |DEPENDENT |kube.pod.status.phase[{#POD}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.phase`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return ['Pending', 'Running', 'Succeeded', 'Failed', 'Unknown'].indexOf(value) + 1 || 'Problem with status processing in JS'; `</p> |
|Kubernetes |Node [{#NODE}] Pod [{#POD}] Uptime |<p>Pod uptime.</p> |DEPENDENT |kube.pod.uptime[{#POD}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.startTime`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- JAVASCRIPT: `return Math.floor((Date.now() - new Date(value)) / 1000);`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Kubernetes: Failed to get nodes |<p>-</p> |`length(last(/Kubernetes nodes by HTTP/kube.nodes.check))>0` |WARNING | |
|Node [{#NAME}] Conditions: Pressure exists on the disk size |<p>True - pressure exists on the disk size - that is, if the disk capacity is low; otherwise False.</p> |`last(/Kubernetes nodes by HTTP/kube.node.conditions.diskpressure[{#NAME}])=1` |WARNING | |
|Node [{#NAME}] Conditions: Pressure exists on the node memory |<p>True - pressure exists on the node memory - that is, if the node memory is low; otherwise False</p> |`last(/Kubernetes nodes by HTTP/kube.node.conditions.memorypressure[{#NAME}])=1` |WARNING | |
|Node [{#NAME}] Conditions: Network is not correctly configured |<p>True - the network for the node is not correctly configured, otherwise False</p> |`last(/Kubernetes nodes by HTTP/kube.node.conditions.networkunavailable[{#NAME}])=1` |WARNING | |
|Node [{#NAME}] Conditions: Pressure exists on the processes |<p>True - pressure exists on the processes - that is, if there are too many processes on the node; otherwise False</p> |`last(/Kubernetes nodes by HTTP/kube.node.conditions.pidpressure[{#NAME}])=1` |WARNING | |
|Node [{#NAME}] Conditions: Is not in Ready state |<p>False - if the node is not healthy and is not accepting pods.</p><p>Unknown - if the node controller has not heard from the node in the last node-monitor-grace-period (default is 40 seconds).</p> |`last(/Kubernetes nodes by HTTP/kube.node.conditions.ready[{#NAME}])<>1` |WARNING | |
|Node [{#NAME}] Limits: Total CPU limits are too high |<p>-</p> |`last(/Kubernetes nodes by HTTP/kube.node.limits.cpu[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.cpu[{#NAME}]) > 0.9` |WARNING |<p>**Depends on**:</p><p>- Node [{#NAME}] Limits: Total CPU limits are too high</p> |
|Node [{#NAME}] Limits: Total CPU limits are too high |<p>-</p> |`last(/Kubernetes nodes by HTTP/kube.node.limits.cpu[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.cpu[{#NAME}]) > 1` |AVERAGE | |
|Node [{#NAME}] Limits: Total memory limits are too high |<p>-</p> |`last(/Kubernetes nodes by HTTP/kube.node.limits.memory[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.memory[{#NAME}]) > 0.9` |WARNING |<p>**Depends on**:</p><p>- Node [{#NAME}] Limits: Total memory limits are too high</p> |
|Node [{#NAME}] Limits: Total memory limits are too high |<p>-</p> |`last(/Kubernetes nodes by HTTP/kube.node.limits.memory[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.memory[{#NAME}]) > 1` |AVERAGE | |
|Node [{#NAME}] Requests: Total CPU requests are too high |<p>-</p> |`last(/Kubernetes nodes by HTTP/kube.node.requests.cpu[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.cpu[{#NAME}]) > 0.5` |WARNING |<p>**Depends on**:</p><p>- Node [{#NAME}] Requests: Total CPU requests are too high</p> |
|Node [{#NAME}] Requests: Total CPU requests are too high |<p>-</p> |`last(/Kubernetes nodes by HTTP/kube.node.requests.cpu[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.cpu[{#NAME}]) > 0.8` |AVERAGE | |
|Node [{#NAME}] Requests: Total memory requests are too high |<p>-</p> |`last(/Kubernetes nodes by HTTP/kube.node.requests.memory[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.memory[{#NAME}]) > 0.5` |WARNING |<p>**Depends on**:</p><p>- Node [{#NAME}] Requests: Total memory requests are too high</p> |
|Node [{#NAME}] Requests: Total memory requests are too high |<p>-</p> |`last(/Kubernetes nodes by HTTP/kube.node.requests.memory[{#NAME}]) / last(/Kubernetes nodes by HTTP/kube.node.allocatable.memory[{#NAME}]) > 0.8` |AVERAGE | |
|Node [{#NAME}]: Has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Kubernetes nodes by HTTP/kube.node.uptime[{#NAME}])<10` |INFO | |
|Node [{#NAME}] Used: Kubelet too many pods |<p>Kubelet is running at capacity.</p> |`last(/Kubernetes nodes by HTTP/kube.node.used.pods[{#NAME}])/ last(/Kubernetes nodes by HTTP/kube.node.capacity.pods[{#NAME}]) > 0.9` |WARNING | |
|Node [{#NODE}] Pod [{#POD}]: Pod is crash looping |<p>Pos restarts more than 2 times in the last 3 minutes.</p> |`(last(/Kubernetes nodes by HTTP/kube.pod.containers.restartcount[{#POD}])-min(/Kubernetes nodes by HTTP/kube.pod.containers.restartcount[{#POD}],3m))>2` |WARNING | |
|Node [{#NODE}] Pod [{#POD}] Status: Kubernetes Pod not healthy |<p>Pod has been in a non-ready state for longer than 10 minutes.</p> |`count(/Kubernetes nodes by HTTP/kube.pod.status.phase[{#POD}],10m, "regexp","^(1|4|5)$")>=9` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

