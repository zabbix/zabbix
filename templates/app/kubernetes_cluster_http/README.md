
# Kubernetes Cluster by HTTP

## Overview

The template to monitor Kubernetes Cluster.
It works without external scripts and uses the script item to make HTTP requests to the Kubernetes API.

Template `Kubernetes Cluster by HTTP` - collects metrics by HTTP agent from kube-state-metrics endpoint and Kubernetes API.

Don't forget to change macros {$KUBE.API.URL} and {$KUBE.API.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.

**Note:** Some metrics may not be collected depending on your Kubernetes version and configuration.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Kubernetes v1.32.6

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Install the [Zabbix Helm Chart](https://git.zabbix.com/projects/ZT/repos/kubernetes-helm/browse?at=refs%2Fheads%2Fmaster) in your Kubernetes cluster.
Internal service metrics are collected from kube-state-metrics endpoint.

Template needs to use authorization via API token.

Set the `{$KUBE.API.URL}` such as `<scheme>://<host>:<port>`.

Get the service account name (if a specific release name is used):

`kubectl get serviceaccounts -n monitoring`

Get the generated service account token using the command:

`kubectl get secret zabbix-zabbix-helm-chart -n monitoring -o jsonpath={.data.token} | base64 -d`

Then set it to the macro `{$KUBE.API.TOKEN}`.

**Note:** Some metrics may not be collected depending on your Kubernetes version and configuration.

Also, see the Macros section for a list of macros used to set trigger values.

Set up the fields to filter the metrics of discovered node by names:

- {$KUBE.LLD.FILTER.NODE.MATCHES}
- {$KUBE.LLD.FILTER.NODE.NOT_MATCHES}

Set up macros to filter metrics by namespace:

- {$KUBE.LLD.FILTER.NAMESPACE.MATCHES}
- {$KUBE.LLD.FILTER.NAMESPACE.NOT_MATCHES}

**Note:** If you have a large cluster, it is highly recommended to set a filter in values of the chart Zabbix Helm Chart to get only the necessary metrics in raw data before they go into Zabbix.

Use the `metricLabelsAllowlist` and `metricAnnotationsAllowList` fields in KSM for advanced filtering by labels and annotations.

You can also set up evaluation periods for replica mismatch triggers (Deployments, ReplicaSets, StatefulSets) with the macro `{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD}`, which supports context and regular expressions. For example, you can create the following macros:

- Set the evaluation period for the Deployment "nginx-deployment" in the namespace "default" to the 3 last values:

`{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD:"deployment:default:nginx-deployment"} = #3`

- Set the evaluation period for all Deployments to the 10 last values:

`{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD:regex:"deployment:.*:.*"} = #10` or `{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD:regex:"^deployment.*"} = #10`

- Set the evaluation period for Deployments, ReplicaSets and StatefulSets in the namespace "default" to 15 minutes:

`{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD:regex:".*:default:.*"} = 15m`

**Note:** that different context macros with regular expressions matching the same string can be applied in an undefined order, and simple context macros (without regular expressions) have higher priority. Read the **Important notes** section in [`Zabbix documentation`](https://www.zabbix.com/documentation/8.0/manual/config/macros/user_macros_context) for details.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.API.TOKEN}|<p>Service account bearer token.</p>||
|{$KUBE.API.URL}|<p>Kubernetes API endpoint URL in the format <scheme>://<host>:<port>.</p>|`https://kubernetes.default.svc`|
|{$KUBE.HTTP.PROXY}|<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p>||
|{$KUBE.NODE.EXPORTER.PORT}|<p>Kubernetes prometheus-node-exporter metrics endpoint port.</p>|`9100`|
|{$KUBE.LLD.FILTER.NAMESPACE.MATCHES}|<p>Filter of discoverable metrics by namespace.</p>|`.*`|
|{$KUBE.LLD.FILTER.NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered metrics by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.LLD.FILTER.NODE.MATCHES}|<p>Filter of discoverable nodes by nodename.</p>|`.*`|
|{$KUBE.LLD.FILTER.NODE.NOT_MATCHES}|<p>Filter to exclude discovered nodes by nodename.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.LLD.FILTER.PV.MATCHES}|<p>Filter of discoverable persistent volumes by name.</p>|`.*`|
|{$KUBE.LLD.FILTER.PV.NOT_MATCHES}|<p>Filter to exclude discovered persistent volumes by name.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD}|<p>The evaluation period range which is used for calculation of expressions in trigger prototypes (time period or value range). Can be used with context.</p>|`#5`|
|{$KUBE.CLUSTER.CPU.UTIL.WARN}|<p>The threshold of CPU usage in percent.</p>|`70`|
|{$KUBE.CLUSTER.MEMORY.UTIL.WARN}|<p>The threshold of memory usage in percent.</p>|`70`|
|{$KUBE.CLUSTER.CPU.UTIL.CRIT}|<p>The threshold of CPU usage in percent.</p>|`80`|
|{$KUBE.CLUSTER.MEMORY.UTIL.CRIT}|<p>The threshold of memory usage in percent.</p>|`80`|
|{$KUBE.NODE.NUMBER.THRESHOLD}|<p>The number of nodes in the cluster used in triggers.</p>|`2`|
|{$KUBE.CLUSTER.POD.PENDING.THRESHOLD}|<p>The number of pending pods used in triggers.</p>|`5`|
|{$KUBE.CLUSTER.POD.FAILED.THRESHOLD}|<p>The number of failed pods used in triggers.</p>|`5`|
|{$KUBE.NAMESPACE.POD.PENDING.THRESHOLD}|<p>The number of pending pods used in triggers.</p>|`3`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get state metrics|<p>Collecting Kubernetes metrics from kube-state-metrics.</p>|Script|kube.state.metrics|
|Get resource metrics|<p>Collecting metrics from kubelet in `/metrics/resource`.</p>|Dependent item|kube.resource.metrics<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_node_info`</p></li><li><p>JSON Path: `$..labels.node`</p><p>⛔️Custom on fail: Set error to: `Failed to get Nodes. Check debug log for more information.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get readyz||HTTP agent|kube.get.readyz<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get livez||HTTP agent|kube.get.livez<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get state metrics check|<p>Check that the kube-state-metrics data has been received correctly.</p>|Dependent item|kube.state.metrics.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Namespace count|<p>The number of namespaces.</p>|Dependent item|kube.namespace.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_namespace_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CronJob count|<p>The number of cronjobs.</p>|Dependent item|kube.cronjob.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_cronjob_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Job count|<p>Number of jobs (generated by cronjob + job).</p>|Dependent item|kube.job.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_job_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Endpoint count|<p>The number of endpoints.</p>|Dependent item|kube.endpoint.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_endpoint_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment count|<p>The number of deployments.</p>|Dependent item|kube.deployment.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_deployment_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Service count|<p>The number of services.</p>|Dependent item|kube.service.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_service_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|StatefulSet count|<p>The number of statefulsets.</p>|Dependent item|kube.statefulset.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_statefulset_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node count|<p>The number of nodes.</p>|Dependent item|kube.node.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_node_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node Ready count|<p>The number of Ready nodes.</p>|Dependent item|kube.node.ready.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node Not Ready count|<p>The number of Not Ready nodes.</p>|Dependent item|kube.node.not_ready.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Configmap count|<p>The number of configmaps.</p>|Dependent item|kube.configmap.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_configmap_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PVC count|<p>The number of PVC.</p>|Dependent item|kube.persistentvolumeclaim.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_persistentvolumeclaim_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PV count|<p>The number of PV.</p>|Dependent item|kube.persistentvolume.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_persistentvolume_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Secret count|<p>The number of secrets.</p>|Dependent item|kube.secret.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_secret_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory allocatable|<p>The memory allocatable in the cluster calculated as a sum of allocatable memory on all nodes.</p>|Dependent item|kube.cluster.memory_allocatable<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(kube_node_status_allocatable{resource="memory"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory limits|<p>The memory limits in the cluster calculated as a sum of memory limits on all pods.</p>|Dependent item|kube.cluster.memory_limits<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(kube_pod_container_resource_limits{resource="memory"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory requests|<p>The memory requests in the cluster calculated as a sum of memory requests on all pods.</p>|Dependent item|kube.cluster.memory_requests<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(kube_pod_container_resource_requests{resource="memory"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory usage|<p>The memory usage in the cluster calculated as a sum of working set memory on all nodes.</p>|Dependent item|kube.cluster.memory_usage<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_memory_working_set_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory utilization|<p>Cluster memory utilization calculated as a percentage of used memory from total memory.</p>|Calculated|kube.cluster.memory_utilization|
|CPU allocatable|<p>The CPU allocatable in the cluster calculated as a sum of allocatable CPU on all nodes.</p>|Dependent item|kube.cluster.cpu_allocatable<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(kube_node_status_allocatable{resource="cpu"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU limits|<p>The CPU limits in the cluster calculated as a sum of CPU limits on all pods.</p>|Dependent item|kube.cluster.cpu_limits<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(kube_pod_container_resource_limits{resource="cpu"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU requests|<p>The CPU requests in the cluster calculated as a sum of CPU requests on all pods.</p>|Dependent item|kube.cluster.cpu_requests<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(kube_pod_container_resource_requests{resource="cpu"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU usage|<p>The CPU usage in the cluster calculated as a sum of CPU seconds on all nodes.</p>|Dependent item|kube.cluster.cpu_usage<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_cpu_usage_seconds_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|CPU utilization|<p>The CPU utilization in the cluster calculated as a percentage of used CPU from allocatable CPU.</p>|Calculated|kube.cluster.cpu_utilization|
|Running pods|<p>The number of running pods.</p>|Dependent item|kube.pod.running<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(kube_pod_status_phase{phase="Running"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Pod pending|<p>The number of pods in pending state.</p>|Dependent item|kube.pod.pending<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(kube_pod_status_phase{phase="Pending"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Pod failed|<p>The number of pods in failed state.</p>|Dependent item|kube.pod.failed<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(kube_pod_status_phase{phase="Failed"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Containers running|<p>The number of running containers in the cluster.</p>|Dependent item|kube.pod.containers_running<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(kube_pod_container_status_running)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ReplicaSets count|<p>The number of ReplicaSets in the cluster.</p>|Dependent item|kube.pod.replicaset_count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_replicaset_owner)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Daemonset count|<p>The number of DaemonSets in the cluster.</p>|Dependent item|kube.pod.daemonset_count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_daemonset_labels)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Ingress count|<p>The number of Ingresses in the cluster.</p>|Dependent item|kube.pod.ingress_count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_ingress_info)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Cluster: Readyz is unhealthy||`count(/Kubernetes Cluster by HTTP/kube.get.readyz,#2,"ne","1")=2 and length(last(/Kubernetes Cluster by HTTP/kube.get.readyz))>0`|Average||
|Kubernetes Cluster: Livez is unhealthy||`count(/Kubernetes Cluster by HTTP/kube.get.livez,#2,"ne","1")=2 and length(last(/Kubernetes Cluster by HTTP/kube.get.livez))>0`|Average||
|Kubernetes Cluster: Failed to get kube-state-metrics data|<p>Failed to get kube-state-metrics.</p>|`length(last(/Kubernetes Cluster by HTTP/kube.state.metrics.check))>0`|Warning||
|Kubernetes Cluster: No Ready nodes|<p>There are no Ready nodes in the cluster.</p>|`last(/Kubernetes Cluster by HTTP/kube.node.ready.count)=0 and last(/Kubernetes Cluster by HTTP/kube.node.count)>0`|High||
|Kubernetes Cluster: Low number of Ready nodes|<p>The number of Ready nodes in the cluster is less than `{$KUBE.NODE.NUMBER.THRESHOLD}`.</p>|`last(/Kubernetes Cluster by HTTP/kube.node.ready.count)< {$KUBE.NODE.NUMBER.THRESHOLD} and last(/Kubernetes Cluster by HTTP/kube.node.count)>0`|High|**Depends on**:<br><ul><li>Kubernetes Cluster: No Ready nodes</li></ul>|
|Kubernetes Cluster: High memory utilization|<p>The cluster memory utilization has exceeded `{$KUBE.CLUSTER.MEMORY.UTIL.CRIT}`%.</p>|`min(/Kubernetes Cluster by HTTP/kube.cluster.memory_utilization,5m) > {$KUBE.CLUSTER.MEMORY.UTIL.CRIT}`|Average||
|Kubernetes Cluster: High memory utilization|<p>The cluster memory utilization has exceeded `{$KUBE.CLUSTER.MEMORY.UTIL.WARN}`%.</p>|`min(/Kubernetes Cluster by HTTP/kube.cluster.memory_utilization,5m) > {$KUBE.CLUSTER.MEMORY.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Kubernetes Cluster: High memory utilization</li></ul>|
|Kubernetes Cluster: High cpu utilization|<p>Cluster CPU utilization has exceeded `{$KUBE.CLUSTER.CPU.UTIL.CRIT}`%. The cluster might be slow to respond.</p>|`min(/Kubernetes Cluster by HTTP/kube.cluster.cpu_utilization,5m) > {$KUBE.CLUSTER.CPU.UTIL.CRIT}`|Average||
|Kubernetes Cluster: High cpu utilization|<p>Cluster CPU utilization is too high. The cluster might be slow to respond.</p>|`min(/Kubernetes Cluster by HTTP/kube.cluster.cpu_utilization,5m) > {$KUBE.CLUSTER.CPU.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Kubernetes Cluster: High cpu utilization</li></ul>|
|Kubernetes Cluster: Pod pending high|<p>The number of pending pods is high `{$KUBE.CLUSTER.POD.PENDING.THRESHOLD}`.</p>|`last(/Kubernetes Cluster by HTTP/kube.pod.pending) > {$KUBE.CLUSTER.POD.PENDING.THRESHOLD}`|Warning||
|Kubernetes Cluster: Pod failed high|<p>The number of failed pods is high `{$KUBE.CLUSTER.POD.FAILED.THRESHOLD}`.</p>|`last(/Kubernetes Cluster by HTTP/kube.pod.failed) > {$KUBE.CLUSTER.POD.FAILED.THRESHOLD}`|Warning||

### LLD rule Namespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace discovery||Dependent item|kube.namespace.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_namespace_created`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Namespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}]: CPU: Total usage|<p>The total CPU usage in the namespace calculated as a sum of non-idle CPU seconds on all containers in the namespace.</p>|Dependent item|kube.namespace.cpu_total[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Namespace [{#NAMESPACE}]: CPU: Limits|<p>The CPU limits in the namespace calculated as a sum of CPU limits on all pods in the namespace.</p>|Dependent item|kube.namespace.limits.cpu[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: CPU: Requests|<p>The number of requested CPU cores by a container.</p>|Dependent item|kube.pod.containers.requests.cpu[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: CPU: Usage, %|<p>The CPU usage in the namespace calculated as a percentage of used CPU from allocatable CPU in the cluster.</p>|Calculated|kube.namespace.cpu_usage[{#NAMESPACE}]|
|Namespace [{#NAMESPACE}]: Memory: Total usage|<p>The total memory usage in bytes in the namespace calculated as a sum of used memory on all containers in the namespace.</p>|Dependent item|kube.namespace.memory_usage[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Namespace [{#NAMESPACE}]: Memory: Usage, %|<p>The memory usage in the namespace calculated as a percentage of used memory from allocatable memory in the cluster.</p>|Calculated|kube.namespace.memory_usage_percentage[{#NAMESPACE}]|
|Namespace [{#NAMESPACE}]: Memory: Limits|<p>The memory limits in the namespace calculated as a sum of memory limits on all pods in the namespace.</p>|Dependent item|kube.namespace.limits.memory[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: Memory: Requests|<p>The memory requests in the namespace calculated as a sum of memory requests on all pods in the namespace.</p>|Dependent item|kube.namespace.requests.memory[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: Pods: Running|<p>The number of pods in running state in the namespace.</p>|Dependent item|kube.namespace.pod.running[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: Pods: Pending|<p>Pod is in pending state.</p>|Dependent item|kube.namespace.pod.pending[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: Pods: Failed|<p>Pod is in failed state.</p>|Dependent item|kube.namespace.pod.failed[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: Status phase|<p>The current status phase of the namespace.</p>|Dependent item|kube.namespace.status_phase[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Namespace discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Cluster: Namespace [{#NAMESPACE}]: Pods pending high||`last(/Kubernetes Cluster by HTTP/kube.namespace.pod.pending[{#NAMESPACE}]) > {$KUBE.NAMESPACE.POD.PENDING.THRESHOLD:"{#NAMESPACE}"}`|High||
|Kubernetes Cluster: Namespace [{#NAMESPACE}]: Terminated||`count(/Kubernetes Cluster by HTTP/kube.namespace.status_phase[{#NAMESPACE}],2m,,2)>=2`|Warning||

### LLD rule Deployment discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Deployment discovery||Dependent item|kube.deployment.discovery[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Deployment discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Deployment [{#DEPLOYMENT}]: CPU: Total usage|<p>The total CPU usage in the deployment calculated as a sum of non-idle CPU seconds on all containers in the deployment.</p>|Dependent item|kube.deployment.cpu_total[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Deployment [{#DEPLOYMENT}]: CPU: Limits|<p>The CPU limits in the deployment calculated as a sum of CPU limits on all pods in the deployment.</p>|Dependent item|kube.deployment.limits.cpu[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: CPU: Requests|<p>The number of requested CPU cores by a container.</p>|Dependent item|kube.deployment.requests.cpu[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: CPU: Usage, %|<p>The CPU usage in the deployment calculated as a percentage of used CPU from allocatable CPU in the cluster.</p>|Calculated|kube.deployment.cpu_usage[{#NAMESPACE}/{#DEPLOYMENT}]|
|Deployment [{#DEPLOYMENT}]: Memory: Total usage|<p>The total memory usage in bytes in the deployment calculated as a sum of used memory on all containers in the deployment.</p>|Dependent item|kube.deployment.memory_usage[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: Memory: Usage, %|<p>The memory usage in the deployment calculated as a percentage of used memory from allocatable memory in the cluster.</p>|Calculated|kube.deployment.memory_usage_percentage[{#NAMESPACE}/{#DEPLOYMENT}]|
|Deployment [{#DEPLOYMENT}]: Memory: Limits|<p>The memory limits in the deployment calculated as a sum of memory limits on all pods in the deployment.</p>|Dependent item|kube.deployment.containers.limits.memory[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: Memory: Requests|<p>The memory requests in the deployment calculated as a sum of memory requests on all pods in the deployment.</p>|Dependent item|kube.deployment.containers.requests.memory[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: Replicas desired|<p>Number of desired pods for a deployment.</p>|Dependent item|kube.deployment.replicas_desired[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: Replicas available|<p>The number of available replicas per deployment.</p>|Dependent item|kube.deployment.replicas_available[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: Replicas mismatched|<p>The number of available replicas not matching the desired number of replicas.</p>|Dependent item|kube.deployment.replicas_mismatched[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Deployment discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Cluster: Deployment [{#DEPLOYMENT}]: Deployment replicas mismatch|<p>Deployment has not matched the expected number of replicas during the specified trigger evaluation period.</p>|`min(/Kubernetes Cluster by HTTP/kube.deployment.replicas_mismatched[{#NAMESPACE}/{#DEPLOYMENT}],{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD:"deployment:{#NAMESPACE}:{#DEPLOYMENT}"})>0 and last(/Kubernetes Cluster by HTTP/kube.deployment.replicas_desired[{#NAMESPACE}/{#DEPLOYMENT}])>=0 and last(/Kubernetes Cluster by HTTP/kube.deployment.replicas_available[{#NAMESPACE}/{#DEPLOYMENT}])>=0`|Warning||

### LLD rule PVC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PVC discovery||Dependent item|kube.pvc.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_persistentvolumeclaim_info`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for PVC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] PVC [{#NAME}] Status phase|<p>The current status phase of the persistent volume claim.</p>|Dependent item|kube.pvc.status_phase[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Namespace [{#NAMESPACE}] PVC [{#NAME}] Requested storage|<p>The capacity of storage requested by the persistent volume claim.</p>|Dependent item|kube.pvc.requested.storage[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] PVC status phase: Bound, sum|<p>The total amount of persistent volume claims in the Bound phase.</p>|Dependent item|kube.pvc.status_phase.bound.sum[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] PVC status phase: Lost, sum|<p>The total amount of persistent volume claims in the Lost phase.</p>|Dependent item|kube.pvc.status_phase.lost.sum[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] PVC status phase: Pending, sum|<p>The total amount of persistent volume claims in the Pending phase.</p>|Dependent item|kube.pvc.status_phase.pending.sum[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for PVC discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Cluster: NS [{#NAMESPACE}] PVC [{#NAME}]: PVC is pending||`count(/Kubernetes Cluster by HTTP/kube.pvc.status_phase[{#NAMESPACE}/{#NAME}],2m,,5)>=2`|Warning||

### LLD rule PV discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PV discovery||Dependent item|kube.pv.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for PV discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PV [{#NAME}] Status phase|<p>The current status phase of the persistent volume.</p>|Dependent item|kube.pv.status_phase[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|PV [{#NAME}] Capacity bytes|<p>A capacity of the persistent volume in bytes.</p>|Dependent item|kube.pv.capacity.bytes[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PV status phase: Pending, sum|<p>The total amount of persistent volumes in the Pending phase.</p>|Dependent item|kube.pv.status_phase.pending.sum[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PV status phase: Available, sum|<p>The total amount of persistent volumes in the Available phase.</p>|Dependent item|kube.pv.status_phase.available.sum[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PV status phase: Bound, sum|<p>The total amount of persistent volumes in the Bound phase.</p>|Dependent item|kube.pv.status_phase.bound.sum[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PV status phase: Released, sum|<p>The total amount of persistent volumes in the Released phase.</p>|Dependent item|kube.pv.status_phase.released.sum[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PV status phase: Failed, sum|<p>The total amount of persistent volumes in the Failed phase.</p>|Dependent item|kube.pv.status_phase.failed.sum[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for PV discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Cluster: PV [{#NAME}]: PV has failed||`count(/Kubernetes Cluster by HTTP/kube.pv.status_phase[{#NAME}],2m,,3)>=2`|Warning||

### LLD rule Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pod discovery||Dependent item|kube.pod.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_pod_info`</p></li></ul>|

### Item prototypes for Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Status phase|<p>Pod status phases.</p>|Dependent item|kube.pod.status_phase[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Containers restarts|<p>The number of container restarts.</p>|Dependent item|kube.pod.containers_restarts[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Pod discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Cluster: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod failed phase|<p>Pod failed state.</p>|`count(/Kubernetes Cluster by HTTP/kube.pod.status_phase[{#NAMESPACE}/{#NAME}],2m,,3)>=2`|Average||
|Kubernetes Cluster: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod pending phase|<p>Pod pending state.</p>|`count(/Kubernetes Cluster by HTTP/kube.pod.status_phase[{#NAMESPACE}/{#NAME}],2m,,1)>=2`|Warning||
|Kubernetes Cluster: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod unknown phase|<p>Pod unknown state.</p>|`count(/Kubernetes Cluster by HTTP/kube.pod.status_phase[{#NAMESPACE}/{#NAME}],2m,,4)>=2`|Warning||
|Kubernetes Cluster: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod is crash looping|<p>Containers of the pod keep restarting. This most likely indicates that the pod is in the CrashLoopBackOff state.</p>|`(last(/Kubernetes Cluster by HTTP/kube.pod.containers_restarts[{#NAMESPACE}/{#NAME}])-min(/Kubernetes Cluster by HTTP/kube.pod.containers_restarts[{#NAMESPACE}/{#NAME}],15m))>1`|Warning||

### LLD rule Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node discovery||Dependent item|kube.node.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_node_info`</p></li></ul>|

# Kubernetes Node by HTTP

## Overview

The template to monitor Kubernetes Node by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Kubernetes Node by HTTP` - collects metrics by HTTP agent from Prometheus metrics endpoint.
Metrics are collected by requests to prometheus-node-exporter.

**Note:** Some metrics may not be collected depending on your Kubernetes instance version and configuration.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Kubernetes v1.32.6

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Internal service metrics are collected from Prometheus metrics endpoint.

This template is used in low-level discovery and will be auto-assigned to host prototypes. Additional configuration is not required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KUBE.HTTP.PROXY}|<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p>||
|{$KUBE.NODE.EXPORTER.PORT}|<p>Kubernetes prometheus-node-exporter metrics endpoint port.</p>|`9100`|
|{$KUBE.NODE.DISK.NAME.MATCHES}|<p>Filter of discoverable metrics by filesystem.</p>|`.*`|
|{$KUBE.NODE.DISK.NAME.NOT_MATCHES}|<p>Filter to exclude discovered metrics by filesystem.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.NODE.NETWORK.IF.NAME.MATCHES}|<p>Filter of discoverable network interfaces.</p>|`.*`|
|{$KUBE.NODE.NETWORK.IF.NAME.NOT_MATCHES}|<p>Filter to exclude discovered network interfaces.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.NODE.NETWORK.IF.OPERSTATUS.MATCHES}|<p>Used for network interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$KUBE.NODE.NETWORK.IF.OPERSTATUS.NOT_MATCHES}|<p>Used for network interface discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.NODE.NETWORK.IF.ADMINSTATUS.MATCHES}|<p>Filter of discoverable network interfaces by administrative status.</p>|`^.*$`|
|{$KUBE.NODE.NETWORK.IF.ADMINSTATUS.NOT_MATCHES}|<p>Ignore the `down` administrative status.</p>|`^down$`|
|{$KUBE.NODE.CPU.UTILIZATION.CRIT}|<p>Critical threshold of CPU utilization expressed in %.</p>|`90`|
|{$KUBE.NODE.LOAD_AVG_PER_CPU.MAX.WARN}|<p>The CPU load per core is considered sustainable. If necessary, it can be tuned.</p>|`1.5`|
|{$KUBE.NODE.MEMORY.UTIL.MAX}|<p>Used as a threshold in the memory utilization trigger.</p>|`90`|
|{$KUBE.NODE.NETWORK.ERRORS.WARN}|<p>The threshold for network interface errors per second - for the Warning trigger.</p>|`10`|
|{$IFCONTROL}|<p>Macro for operational state of the interface for link down trigger. Can be used with interface name as context.</p>|`1`|
|{$KUBE.NODE.FILESYSTEM.MATCHES}|<p>Filter of discoverable filesystem mount points.</p>|`.*`|
|{$KUBE.NODE.FILESYSTEM.NOT_MATCHES}|<p>Filter to exclude discovered filesystem mount points.</p>|`^(/sys\|/proc\|/dev\|/run/containerd)`|
|{$KUBE.NODE.IO.UTIL.HIGH}|<p>High I/O utilization threshold for disk.</p>|`80`|
|{$KUBE.NODE.FS.PUSED.MAX.WARN}|<p>Warning threshold of the filesystem utilization. In the range from 0 to 100 inclusive.</p>|`80`|
|{$KUBE.NODE.FS.PUSED.MAX.CRIT}|<p>Critical threshold of the filesystem utilization. In the range from 0 to 100 inclusive.</p>|`90`|
|{$KUBE.NODE.PSI.CPU.WARN}|<p>Warning threshold for CPU pressure (% of time processes are waiting for CPU).</p>|`20`|
|{$KUBE.NODE.PSI.CPU.CRIT}|<p>Critical threshold for CPU pressure (% of time processes are waiting for CPU).</p>|`50`|
|{$KUBE.NODE.PSI.MEMORY.WARN}|<p>Warning threshold for memory pressure (% of time processes are waiting for memory).</p>|`10`|
|{$KUBE.NODE.PSI.MEMORY.CRIT}|<p>Critical threshold for memory pressure (% of time processes are fully stalled for memory).</p>|`5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get node-exporter metrics|<p>Collecting Kubernetes metrics from prometheus-node-exporter.</p>|HTTP agent|kube.node.exporter.metrics|
|CPU: Utilization|<p>Percentage of CPU time spent in non-idle modes (calculated as 1 - idle time).</p>|Dependent item|kube.node.cpu_utilization<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `AVG(node_cpu_seconds_total{mode="idle"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>JavaScript: `return (1 - value)*100;`</p></li></ul>|
|CPU: Cores|<p>Number of CPU cores.</p>|Dependent item|kube.node.cpu_core<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(node_softnet_cpu_collision_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory: Total|<p>Total memory available in bytes.</p>|Dependent item|kube.node.memory_total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_memory_MemTotal_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory: Available|<p>Available memory in bytes.</p>|Dependent item|kube.node.memory_available<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_memory_MemAvailable_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory: Cache|<p>Cache memory in bytes.</p>|Dependent item|kube.node.memory_cache<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_memory_Cached_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory: Buffers|<p>Buffers memory in bytes.</p>|Dependent item|kube.node.memory_buffers<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_memory_Buffers_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory: Swap total|<p>Swap total memory in bytes.</p>|Dependent item|kube.node.memory_swap_total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_memory_SwapTotal_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory: Swap free|<p>Free swap total memory in bytes.</p>|Dependent item|kube.node.memory_swap_free<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_memory_SwapFree_bytes)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory: Swap used|<p>Used swap memory in bytes.</p>|Calculated|kube.node.memory_swap_used|
|Memory: Utilization|<p>Node memory utilization calculated as a percentage of used memory from total memory.</p>|Calculated|kube.node.memory_utilization|
|CPU: Load average 1min|<p>System load average over 1 minute.</p>|Dependent item|kube.node.load_avg_1min<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_load1)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU: Load average 5min|<p>System load average over 5 minutes.</p>|Dependent item|kube.node.load_avg_5min<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_load5)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU: Load average 15min|<p>System load average over 15 minutes.</p>|Dependent item|kube.node.load_avg_15min<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_load15)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Receive bytes|<p>Network bytes received per second.</p>|Dependent item|kube.node.network_receive_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_network_receive_bytes_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Network: Transmit bytes|<p>Network bytes transmitted per second.</p>|Dependent item|kube.node.network_transmit_bytes<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_network_transmit_bytes_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Uptime|<p>Node uptime.</p>|Dependent item|kube.node.boot_time<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_boot_time_seconds)`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `return (Math.floor(Date.now() / 1000) - Number(value));`</p></li></ul>|
|Pressure: CPU waiting|<p>CPU pressure - time processes spend waiting for CPU scheduling per second (kernel PSI metric).</p>|Dependent item|kube.node.cpu_pressure_waiting<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_pressure_cpu_waiting_seconds_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Pressure: Memory waiting|<p>Memory pressure - time processes spend waiting for memory per second (kernel PSI metric).</p>|Dependent item|kube.node.memory_pressure_waiting<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_pressure_memory_waiting_seconds_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Pressure: Memory stalled|<p>Memory pressure - time processes are fully stalled waiting for memory per second (kernel PSI metric).</p>|Dependent item|kube.node.memory_pressure_stalled<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_pressure_memory_stalled_seconds_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Pressure: I/O waiting|<p>I/O pressure - time processes spend waiting for I/O per second (kernel PSI metric).</p>|Dependent item|kube.node.io_pressure_waiting<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_pressure_io_waiting_seconds_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Pressure: I/O stalled|<p>I/O pressure - time processes are fully stalled waiting for I/O per second (kernel PSI metric).</p>|Dependent item|kube.node.io_pressure_stalled<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_pressure_io_stalled_seconds_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Node: High CPU utilization|<p>CPU utilization is over {$KUBE.NODE.CPU.UTILIZATION.CRIT}% for 5 minutes.</p>|`min(/Kubernetes Node by HTTP/kube.node.cpu_utilization, 5m) > {$KUBE.NODE.CPU.UTILIZATION.CRIT}`|High||
|Kubernetes Node: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Kubernetes Node by HTTP/kube.node.memory_utilization,5m)>{$KUBE.NODE.MEMORY.UTIL.MAX}`|Average||
|Kubernetes Node: Load average is too high|<p>The load average per CPU is too high. The system may be slow to respond.</p>|`min(/Kubernetes Node by HTTP/kube.node.load_avg_1min,5m)/last(/Kubernetes Node by HTTP/kube.node.cpu_core)>{$KUBE.NODE.LOAD_AVG_PER_CPU.MAX.WARN} and last(/Kubernetes Node by HTTP/kube.node.load_avg_5min)>0 and last(/Kubernetes Node by HTTP/kube.node.load_avg_15min)>0`|Average||
|Kubernetes Node: Node has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Kubernetes Node by HTTP/kube.node.boot_time)<10`|Info|**Manual close**: Yes|
|Kubernetes Node: High CPU pressure|<p>Node CPU pressure is above {$KUBE.NODE.PSI.CPU.WARN}%. Processes are spending significant time waiting for CPU scheduling, indicating CPU saturation.</p>|`last(/Kubernetes Node by HTTP/kube.node.cpu_pressure_waiting) > {$KUBE.NODE.PSI.CPU.WARN}`|Warning|**Depends on**:<br><ul><li>Kubernetes Node: Critical CPU pressure</li></ul>|
|Kubernetes Node: Critical CPU pressure|<p>Node CPU pressure is critically high (>{$KUBE.NODE.PSI.CPU.CRIT}%). Severe CPU saturation detected.</p>|`last(/Kubernetes Node by HTTP/kube.node.cpu_pressure_waiting) > {$KUBE.NODE.PSI.CPU.CRIT}`|High||
|Kubernetes Node: High memory pressure|<p>Node memory pressure is above {$KUBE.NODE.PSI.MEMORY.WARN}%. Processes are experiencing significant memory contention.</p>|`last(/Kubernetes Node by HTTP/kube.node.memory_pressure_waiting) > {$KUBE.NODE.PSI.MEMORY.WARN}`|Warning|**Depends on**:<br><ul><li>Kubernetes Node: Critical memory pressure</li></ul>|
|Kubernetes Node: Critical memory pressure|<p>Node memory pressure is critically high (>{$KUBE.NODE.PSI.MEMORY.CRIT}%). Processes are fully stalled waiting for memory - severe memory saturation.</p>|`last(/Kubernetes Node by HTTP/kube.node.memory_pressure_waiting) > {$KUBE.NODE.PSI.MEMORY.CRIT}`|High||

### LLD rule Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage discovery|<p>Discovery Node disk.</p>|Dependent item|kube.node.storage.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `node_disk_written_bytes_total`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk [{#NAME}]: Read IOps|<p>Disk `{#NAME}` read I/O operations per second.</p>|Dependent item|kube.node.disk_read_io_ps[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_disk_reads_completed_total{device="{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Disk [{#NAME}]: Write IOps|<p>Disk `{#NAME}` write I/O operations per second.</p>|Dependent item|kube.node.disk_write_io_ps[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_disk_writes_completed_total{device="{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Disk [{#NAME}]: I/O Utilization|<p>Percentage of time the disk `{#NAME}` was actively processing I/O operations.</p>|Dependent item|kube.node.disk_io_utilization[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_disk_io_time_seconds_total{device="{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `100`</p></li></ul>|
|Disk [{#NAME}]: Read bytes|<p>Disk `{#NAME}` bytes read per second.</p>|Dependent item|kube.node.disk_read_bytes[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_disk_read_bytes_total{device="{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Disk [{#NAME}]: Write bytes|<p>Disk `{#NAME}` bytes written per second.</p>|Dependent item|kube.node.disk_write_bytes[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_disk_written_bytes_total{device="{#NAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Storage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Node: Disk [{#NAME}]: I/O utilization is too high|<p>Current disk `{#NAME}` IO utilization has exceeded `{$KUBE.NODE.IO.UTIL.HIGH:"{#NAME}"}`%.</p>|`min(/Kubernetes Node by HTTP/kube.node.disk_io_utilization[{#NAME}],5m)>={$KUBE.NODE.IO.UTIL.HIGH:"{#NAME}"}`|High||

### LLD rule Filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Filesystem discovery|<p>Discovery of filesystem mount points from node-exporter metrics.</p>|Dependent item|kube.node.filesystem.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `node_filesystem_avail_bytes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Filesystem [{#FSNAME}]: Available space|<p>Available space on filesystem `{#FSNAME}`.</p>|Dependent item|kube.node.filesystem.avail_bytes[{#FSNAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filesystem [{#FSNAME}]: Total space|<p>Total space on filesystem `{#FSNAME}`.</p>|Dependent item|kube.node.filesystem.size_bytes[{#FSNAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filesystem [{#FSNAME}]: Used space|<p>Used space on filesystem `{#FSNAME}`.</p>|Calculated|kube.node.filesystem.used_bytes[{#FSNAME}]|
|Filesystem [{#FSNAME}]: Utilization|<p>Utilization of filesystem `{#FSNAME}` as a percentage.</p>|Calculated|kube.node.filesystem.utilization[{#FSNAME}]|

### Trigger prototypes for Filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Node: Filesystem [{#FSNAME}]: Low available space|<p>Filesystem `{#FSNAME}` available space is lower than {$KUBE.NODE.FS.PUSED.MAX.WARN:"{#FSNAME}"}%.</p>|`min(/Kubernetes Node by HTTP/kube.node.filesystem.utilization[{#FSNAME}], 5m) > {$KUBE.NODE.FS.PUSED.MAX.WARN:"{#FSNAME}"}`|Warning|**Depends on**:<br><ul><li>Kubernetes Node: Filesystem [{#FSNAME}]: Critical available space</li></ul>|
|Kubernetes Node: Filesystem [{#FSNAME}]: Critical available space|<p>Filesystem `{#FSNAME}` available space is critically low (< {$KUBE.NODE.FS.PUSED.MAX.CRIT:"{#FSNAME}"}).</p>|`min(/Kubernetes Node by HTTP/kube.node.filesystem.utilization[{#FSNAME}], 5m) > {$KUBE.NODE.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`|High||

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of network interfaces from node-exporter metrics.</p>|Dependent item|kube.node.network.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `node_network_info`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network [{#IFNAME}]: Operational status|<p>Current operational status of network interface `{#IFNAME}`.</p>|Dependent item|kube.node.network.if.oper.status[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_up{device="{#IFNAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network [{#IFNAME}]: Speed|<p>Network interface `{#IFNAME}` speed in bytes per second.</p>|Dependent item|kube.node.network.if.speed[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(node_network_speed_bytes{device="{#IFNAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `8`</p></li></ul>|
|Network [{#IFNAME}]: Receive bytes|<p>Network interface `{#IFNAME}` bytes received per second.</p>|Dependent item|kube.node.net.if.receive_bytes[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_network_receive_bytes_total{device="{#IFNAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Network [{#IFNAME}]: Transmit bytes|<p>Network interface `{#IFNAME}` bytes transmitted per second.</p>|Dependent item|kube.node.net.if.transmit_bytes[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_network_transmit_bytes_total{device="{#IFNAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Network [{#IFNAME}]: Receive packets|<p>Network interface `{#IFNAME}` packets received per second.</p>|Dependent item|kube.node.net.if.receive_packets[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_network_receive_packets_total{device="{#IFNAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Network [{#IFNAME}]: Transmit packets|<p>Network interface `{#IFNAME}` packets transmitted per second.</p>|Dependent item|kube.node.net.if.transmit_packets[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_network_transmit_packets_total{device="{#IFNAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Network [{#IFNAME}]: Receive errors|<p>Network interface `{#IFNAME}` receive errors per second.</p>|Dependent item|kube.node.net.if.receive_errors[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_network_receive_errs_total{device="{#IFNAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Network [{#IFNAME}]: Transmit errors|<p>Network interface `{#IFNAME}` transmit errors per second.</p>|Dependent item|kube.node.net.if.transmit_errors[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(node_network_transmit_errs_total{device="{#IFNAME}"})`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes Node: Link [{#IFNAME}]: Down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Kubernetes Node by HTTP/kube.node.network.if.oper.status[{#IFNAME}])=0 and (last(/Kubernetes Node by HTTP/kube.node.network.if.oper.status[{#IFNAME}],#1)<>last(/Kubernetes Node by HTTP/kube.node.network.if.oper.status[{#IFNAME}],#2))`|Average|**Manual close**: Yes|
|Kubernetes Node: Network [{#IFNAME}]: High receive errors|<p>Network interface `{#IFNAME}` has more than {$KUBE.NODE.NETWORK.ERRORS.WARN:"{#IFNAME}"} receive errors per second.</p>|`last(/Kubernetes Node by HTTP/kube.node.net.if.receive_errors[{#IFNAME}]) > {$KUBE.NODE.NETWORK.ERRORS.WARN:"{#IFNAME}"}`|Warning||
|Kubernetes Node: Network [{#IFNAME}]: High error rate|<p>It recovers when it is below 80% of the `{$KUBE.NODE.NETWORK.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Kubernetes Node by HTTP/kube.node.net.if.transmit_errors[{#IFNAME}],5m)>{$KUBE.NODE.NETWORK.ERRORS.WARN:"{#IFNAME}"} or min(/Kubernetes Node by HTTP/kube.node.net.if.receive_errors[{#IFNAME}],5m)>{$KUBE.NODE.NETWORK.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Kubernetes Node: Link [{#IFNAME}]: Down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

