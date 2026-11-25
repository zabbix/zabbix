
# OpenShift Cluster by HTTP

## Overview

The template to monitor an OpenShift Cluster.
It works without external scripts and collects metrics by HTTP agent directly from the built-in Thanos Querier API.

Template `OpenShift Cluster by HTTP` collects cluster, namespace, deployment and pod-level metrics by querying the Thanos Querier route (PromQL `/api/v1/query`), which already federates kube-state-metrics, node-exporter, cAdvisor and openshift-state-metrics cluster-wide, so no in-cluster agent or additional scrape target is required.

Besides vanilla Kubernetes objects (nodes, namespaces, deployments, pods), the template also discovers OpenShift-specific objects: Routes, BuildConfigs, Builds and ClusterResourceQuotas.

Each discovered node is created as a separate host with the template `OpenShift Node by HTTP` linked, so node resources are monitored separately from the cluster-level metrics.

Cluster-wide filesystem (which backs kubelet/crio storage) and pod network throughput are also collected, along with currently firing Prometheus alerts (discovered via LLD, one item and trigger per active alert name).

Don't forget to change macros {$OPENSHIFT.THANOS.URL} and {$OPENSHIFT.THANOS.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.

**Note:** Some counters available in vanilla Kubernetes (Job, Endpoint and Ingress object counts; running container count) have no equivalent in OpenShift's built-in kube-state-metrics allow-list and are therefore not collected by this template.

**Note:** On a large cluster, LLD discovery (namespaces, pods, deployments, routes, etc.) can create a large number of items. Use `{$OPENSHIFT.LLD.FILTER.NAMESPACE.NOT_MATCHES}` (for example, set it to `^(openshift|kube)-.*` to exclude platform namespaces) to keep the item count manageable.

## Requirements

Zabbix version: 8.2 and higher.

## Tested versions

This template has been tested on:
- OpenShift v4.22

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.2/manual/config/templates_out_of_the_box) section.

## Setup

Metrics are collected from the OpenShift built-in monitoring stack's **Thanos Querier**, which already federates kube-state-metrics, node-exporter and cAdvisor cluster-wide behind one authenticated route.

The template authenticates via a bearer token.

Get the Thanos Querier route host:

`oc get route thanos-querier -n openshift-monitoring -o jsonpath='{.spec.host}'`

Set the `{$OPENSHIFT.THANOS.URL}` macro to `https://<route host>`.

Create a ServiceAccount and bind it to a ClusterRole that can read cluster monitoring data, for example:

```
oc create serviceaccount zabbix -n openshift-monitoring
oc adm policy add-cluster-role-to-user cluster-monitoring-view -z zabbix -n openshift-monitoring
oc create token zabbix -n openshift-monitoring --duration=8760h
```

Set the resulting token in the `{$OPENSHIFT.THANOS.TOKEN}` macro.

Each discovered node is created as a separate host with the template `OpenShift Node by HTTP` linked. Discovered hosts do not take macros from the cluster host, so to collect node metrics, define `{$OPENSHIFT.THANOS.URL}` and `{$OPENSHIFT.THANOS.TOKEN}` on the global level (**Administration** → **Macros**), with the token as a secret macro.

**Note:** Some counters available in vanilla Kubernetes (Job, Endpoint and Ingress object counts; running container count) have no equivalent in OpenShift's built-in kube-state-metrics allow-list and are therefore not collected by this template.

Also, see the Macros section for a list of macros used to set trigger values.

Set up the fields to filter the metrics of discovered nodes by name:

- {$OPENSHIFT.LLD.FILTER.NODE.MATCHES}
- {$OPENSHIFT.LLD.FILTER.NODE.NOT_MATCHES}

Set up macros to filter metrics by namespace:

- {$OPENSHIFT.LLD.FILTER.NAMESPACE.MATCHES}
- {$OPENSHIFT.LLD.FILTER.NAMESPACE.NOT_MATCHES}

You can also set up evaluation periods for replica mismatch triggers (Deployments) with the macro `{$OPENSHIFT.REPLICA.MISMATCH.EVAL_PERIOD}`, which supports context and regular expressions. For example, you can set the following context values:

- Set the evaluation period for the Deployment "nginx-deployment" in the namespace "default" to the 3 last values:

`{$OPENSHIFT.REPLICA.MISMATCH.EVAL_PERIOD:"deployment:default:nginx-deployment"} = #3`

- Set the evaluation period for all Deployments to the 10 last values:

`{$OPENSHIFT.REPLICA.MISMATCH.EVAL_PERIOD:regex:"deployment:.*:.*"} = #10` or `{$OPENSHIFT.REPLICA.MISMATCH.EVAL_PERIOD:regex:"^deployment.*"} = #10`

- Set the evaluation period for Deployments in the namespace "default" to 15 minutes:

`{$OPENSHIFT.REPLICA.MISMATCH.EVAL_PERIOD:regex:".*:default:.*"} = 15m`

**Note:** Different context macros with regular expressions matching the same string can be applied in an undefined order, and simple context macros (without regular expressions) have higher priority. Read the **Important notes** section in [Zabbix documentation](https://www.zabbix.com/documentation/8.2/manual/config/macros/user_macros_context) for details.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OPENSHIFT.THANOS.TOKEN}|<p>Bearer token for a ServiceAccount authorized to read cluster monitoring data (e.g. bound to the `cluster-monitoring-view` ClusterRole).</p>||
|{$OPENSHIFT.THANOS.URL}|<p>Thanos Querier route URL in the format https://<host>, e.g. the host from `oc get route thanos-querier -n openshift-monitoring`.</p>||
|{$OPENSHIFT.HTTP.PROXY}|<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p>||
|{$OPENSHIFT.LLD.FILTER.NAMESPACE.MATCHES}|<p>Filter of discoverable namespaces by name.</p>|`.*`|
|{$OPENSHIFT.LLD.FILTER.NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered namespaces by name.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSHIFT.LLD.FILTER.NAMESPACE.LABEL_MATCHES}|<p>Filter of discoverable namespaces by their Kubernetes labels (matched against a `key=value,key=value` string of all `label_*` sub-labels on the namespace).</p>|`.*`|
|{$OPENSHIFT.LLD.FILTER.NODE.MATCHES}|<p>Filter of discoverable nodes by name.</p>|`.*`|
|{$OPENSHIFT.LLD.FILTER.NODE.NOT_MATCHES}|<p>Filter to exclude discovered nodes by name.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSHIFT.LLD.FILTER.NODE.LABEL_MATCHES}|<p>Filter of discoverable nodes by their Kubernetes labels (matched against a `key=value,key=value` string of all `label_*` sub-labels on the node).</p>|`.*`|
|{$OPENSHIFT.LLD.FILTER.DEPLOYMENT.MATCHES}|<p>Filter of discoverable Deployments by name.</p>|`.*`|
|{$OPENSHIFT.LLD.FILTER.DEPLOYMENT.NOT_MATCHES}|<p>Filter to exclude discovered Deployments by name.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSHIFT.LLD.FILTER.CLUSTERRESOURCEQUOTA.MATCHES}|<p>Filter of discoverable ClusterResourceQuotas by name.</p>|`.*`|
|{$OPENSHIFT.LLD.FILTER.CLUSTERRESOURCEQUOTA.NOT_MATCHES}|<p>Filter to exclude discovered ClusterResourceQuotas by name.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSHIFT.LLD.FILTER.CLUSTEROPERATOR.MATCHES}|<p>Filter of discoverable ClusterOperators by name.</p>|`.*`|
|{$OPENSHIFT.LLD.FILTER.CLUSTEROPERATOR.NOT_MATCHES}|<p>Filter to exclude discovered ClusterOperators by name.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSHIFT.LLD.FILTER.ALERT.MATCHES}|<p>Filter of discoverable alerts by name.</p>|`.*`|
|{$OPENSHIFT.LLD.FILTER.ALERT.NOT_MATCHES}|<p>Filter to exclude discovered alerts by name.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSHIFT.LLD.FILTER.ALERT.SEVERITY.MATCHES}|<p>Filter of discoverable alerts by severity.</p>|`.*`|
|{$OPENSHIFT.LLD.FILTER.PV.MATCHES}|<p>Filter of discoverable PersistentVolumes by name.</p>|`.*`|
|{$OPENSHIFT.LLD.FILTER.PV.NOT_MATCHES}|<p>Filter to exclude discovered PersistentVolumes by name.</p>|`CHANGE_IF_NEEDED`|
|{$OPENSHIFT.REPLICA.MISMATCH.EVAL_PERIOD}|<p>The evaluation period range which is used for calculation of expressions in trigger prototypes (time period or value range). Can be used with context.</p>|`#5`|
|{$OPENSHIFT.CLUSTER.CPU.UTIL.WARN}|<p>The warning threshold of cluster CPU utilization expressed in %.</p>|`70`|
|{$OPENSHIFT.CLUSTER.CPU.UTIL.CRIT}|<p>The critical threshold of cluster CPU utilization expressed in %.</p>|`80`|
|{$OPENSHIFT.CLUSTER.MEMORY.UTIL.WARN}|<p>The warning threshold of cluster memory utilization expressed in %.</p>|`70`|
|{$OPENSHIFT.CLUSTER.MEMORY.UTIL.CRIT}|<p>The critical threshold of cluster memory utilization expressed in %.</p>|`80`|
|{$OPENSHIFT.CLUSTER.FILESYSTEM.UTIL.WARN}|<p>The warning threshold of cluster filesystem utilization expressed in %.</p>|`80`|
|{$OPENSHIFT.CLUSTER.FILESYSTEM.UTIL.CRIT}|<p>The critical threshold of cluster filesystem utilization expressed in %.</p>|`90`|
|{$OPENSHIFT.APISERVER.SUCCESS_RATE.WARN}|<p>The threshold of API server request success rate in percent, below which a warning is raised.</p>|`99`|
|{$OPENSHIFT.NODE.NUMBER.THRESHOLD}|<p>The number of Ready nodes in the cluster used in triggers.</p>|`2`|
|{$OPENSHIFT.CLUSTER.POD.PENDING.THRESHOLD}|<p>The number of pending pods used in triggers.</p>|`5`|
|{$OPENSHIFT.CLUSTER.POD.FAILED.THRESHOLD}|<p>The number of failed pods used in triggers.</p>|`5`|
|{$OPENSHIFT.NAMESPACE.POD.PENDING.THRESHOLD}|<p>The number of pending pods per namespace used in triggers.</p>|`3`|
|{$OPENSHIFT.POD.RESTARTS.THRESHOLD}|<p>The maximum number of container restarts of a pod during the `{$OPENSHIFT.POD.RESTARTS.PERIOD}` period. Can be used with the namespace as context.</p>|`3`|
|{$OPENSHIFT.POD.RESTARTS.PERIOD}|<p>The evaluation period for the container restarts trigger. Can be used with the namespace as context.</p>|`1h`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get node state metrics|<p>Collecting node-level kube-state-metrics and node-exporter filesystem metrics (restricted to the mountpoint, which backs kubelet/crio storage) from the Thanos Querier API.</p>|HTTP agent|openshift.state.node.metrics.get|
|Get workload state metrics|<p>Collecting namespace, deployment, statefulset, persistent volume/claim and ownership-chain kube-state-metrics from the Thanos Querier API.</p>|HTTP agent|openshift.state.workload.metrics.get|
|Get pod state metrics|<p>Collecting pod-level kube-state-metrics from the Thanos Querier API. Pod metrics are collected separately from workload metrics because pods change more often, so a slow query does not affect the other items.</p>|HTTP agent|openshift.state.pod.metrics.get|
|Get resource usage metrics|<p>Collecting namespace- and pod-level CPU/memory usage, and namespace-level ephemeral filesystem usage, from the Thanos Querier API, pre-aggregated server-side by PromQL instead of client-side, distinguished by the `scope` label (`namespace` = per-namespace total, `pod` = per-pod total used to roll up per-deployment totals).</p>|HTTP agent|openshift.resource.usage.metrics.get|
|Get active pod resource metrics|<p>Collecting namespace-level CPU/memory requests and limits, pre-aggregated server-side by PromQL, summed only over pods currently in the Pending or Running phase (joined against `kube_pod_status_phase` so completed/evicted pods don't inflate the totals).</p>|HTTP agent|openshift.pod.resource.active.metrics.get|
|Get platform health metrics|<p>Collecting ClusterOperator status (and per-condition detail), ClusterVersion status, infrastructure provider/topology, and currently firing Prometheus alerts from the Thanos Querier API.</p>|HTTP agent|openshift.platform.health.metrics.get|
|Get OpenShift object metrics|<p>Collecting OpenShift-specific object metrics (Route, BuildConfig, Build, ClusterResourceQuota) from `openshift-state-metrics`, federated via the Thanos Querier API.</p>|HTTP agent|openshift.state.objects.metrics.get|
|Get network usage metrics|<p>Collecting cluster-wide and namespace-level pod network throughput from the Thanos Querier API, distinguished by the `scope` label (`cluster` = cluster-wide total, `namespace` = per-namespace total). Restricted to `interface="eth0"` (the pod's primary interface): on OVN-Kubernetes clusters cAdvisor also misattributes host-level bridge/tunnel interfaces (br-ex, br-int, veth pairs, etc.) onto every pod's network stats, which would otherwise inflate the sum.</p>|HTTP agent|openshift.network.usage.metrics.get|
|Get control plane metrics|<p>Collecting control plane component health (API servers, controller managers, schedulers, each as a percentage of instances reporting `up`) and the overall API request success rate from the Thanos Querier API.</p>|HTTP agent|openshift.platform.controlplane.metrics.get|
|Get node state metrics check|<p>Check that the node state data has been received correctly from Thanos.</p>|Dependent item|openshift.state.node.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get workload state metrics check|<p>Check that the workload state data has been received correctly from Thanos.</p>|Dependent item|openshift.state.workload.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get pod state metrics check|<p>Check that the pod state data has been received correctly from Thanos.</p>|Dependent item|openshift.state.pod.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get resource usage metrics check|<p>Check that the resource usage data has been received correctly from Thanos.</p>|Dependent item|openshift.resource.usage.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get active pod resource metrics check|<p>Check that the active pod resource data has been received correctly from Thanos.</p>|Dependent item|openshift.pod.resource.active.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get platform health metrics check|<p>Check that the platform health data has been received correctly from Thanos.</p>|Dependent item|openshift.platform.health.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get OpenShift object metrics check|<p>Check that the OpenShift object data has been received correctly from Thanos.</p>|Dependent item|openshift.state.objects.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get network usage metrics check|<p>Check that the network usage data has been received correctly from Thanos.</p>|Dependent item|openshift.network.usage.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get control plane metrics check|<p>Check that the control plane data has been received correctly from Thanos.</p>|Dependent item|openshift.platform.controlplane.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Namespace count|<p>The number of namespaces.</p>|Dependent item|openshift.namespace.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CronJob count|<p>The number of CronJobs.</p>|Dependent item|openshift.cronjob.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment count|<p>The number of deployments.</p>|Dependent item|openshift.deployment.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Service count|<p>The number of services.</p>|Dependent item|openshift.service.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|StatefulSet count|<p>The number of StatefulSets.</p>|Dependent item|openshift.statefulset.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DaemonSet count|<p>The number of DaemonSets.</p>|Dependent item|openshift.daemonset.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ConfigMap count|<p>The number of ConfigMaps.</p>|Dependent item|openshift.configmap.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Secret count|<p>The number of Secrets.</p>|Dependent item|openshift.secret.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PVC count|<p>The number of PVC.</p>|Dependent item|openshift.persistentvolumeclaim.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|PV count|<p>The number of PV.</p>|Dependent item|openshift.persistentvolume.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ReplicaSet count|<p>The number of ReplicaSets in the cluster.</p>|Dependent item|openshift.replicaset.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node count|<p>The number of nodes.</p>|Dependent item|openshift.node.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node Ready count|<p>The number of Ready nodes.</p>|Dependent item|openshift.node.ready.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node Not Ready count|<p>The number of Not Ready nodes.</p>|Dependent item|openshift.node.not_ready.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory allocatable|<p>The allocatable memory in the cluster calculated as a sum of allocatable memory on all nodes.</p>|Dependent item|openshift.cluster.memory_allocatable<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory limits|<p>The memory limits in the cluster calculated as a sum of memory limits on pods in the Pending or Running phase.</p>|Dependent item|openshift.cluster.memory_limits<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory requests|<p>The memory requests in the cluster calculated as a sum of memory requests on pods in the Pending or Running phase.</p>|Dependent item|openshift.cluster.memory_requests<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory usage|<p>The memory usage in the cluster calculated as a sum of container working set memory across all namespaces.</p>|Dependent item|openshift.cluster.memory_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory utilization|<p>Cluster memory utilization calculated as a percentage of used memory from total memory.</p>|Calculated|openshift.cluster.memory_utilization|
|CPU allocatable|<p>The allocatable CPU in the cluster calculated as a sum of allocatable CPU on all nodes.</p>|Dependent item|openshift.cluster.cpu_allocatable<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU limits|<p>The CPU limits in the cluster calculated as a sum of CPU limits on pods in the Pending or Running phase.</p>|Dependent item|openshift.cluster.cpu_limits<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU requests|<p>The CPU requests in the cluster calculated as a sum of CPU requests on pods in the Pending or Running phase.</p>|Dependent item|openshift.cluster.cpu_requests<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU usage|<p>The CPU usage in the cluster calculated as a sum of per-second CPU cores used across all namespaces.</p>|Dependent item|openshift.cluster.cpu_usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU utilization|<p>The CPU utilization in the cluster calculated as a percentage of used CPU from allocatable CPU.</p>|Calculated|openshift.cluster.cpu_utilization|
|Filesystem size|<p>The size of the filesystem summed across all nodes (backs kubelet/crio storage).</p>|Dependent item|openshift.cluster.filesystem_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Filesystem available|<p>The available space on the filesystem summed across all nodes.</p>|Dependent item|openshift.cluster.filesystem_available<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Filesystem utilization|<p>Cluster filesystem utilization calculated as a percentage of used space from its total size.</p>|Calculated|openshift.cluster.filesystem_utilization|
|Network receive|<p>Cluster-wide pod network receive throughput, summed across all pods' primary interface.</p>|Dependent item|openshift.cluster.network_receive<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network transmit|<p>Cluster-wide pod network transmit throughput, summed across all pods' primary interface.</p>|Dependent item|openshift.cluster.network_transmit<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Pods: Running|<p>The number of running pods.</p>|Dependent item|openshift.pod.running<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Pods: Pending|<p>The number of pods in pending state.</p>|Dependent item|openshift.pod.pending<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Pods: Failed|<p>The number of pods in failed state.</p>|Dependent item|openshift.pod.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ClusterOperators degraded count|<p>The number of ClusterOperators currently reporting `Degraded=True` (degraded). An empty result from Thanos (nothing degraded) naturally evaluates to zero matches.</p>|Dependent item|openshift.platform.clusteroperators_degraded<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Cluster version|<p>The completed OpenShift cluster version.</p>|Dependent item|openshift.platform.cluster_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Infrastructure provider|<p>The underlying infrastructure provider the cluster is running on (e.g. AWS, Azure, GCP, VSphere, BareMetal, None).</p>|Dependent item|openshift.platform.infrastructure_provider<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Control plane topology|<p>The control plane topology mode: `HighlyAvailable` (multiple control plane nodes) or `SingleReplica` (a single control plane node, e.g. CRC/SNO).</p>|Dependent item|openshift.platform.infrastructure_topology<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Control plane: API servers up, %|<p>The percentage of `apiserver` instances currently reporting `up`.</p>|Dependent item|openshift.controlplane.apiserver.response_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Control plane: Controller managers up, %|<p>The percentage of `kube-controller-manager` instances currently reporting `up`.</p>|Dependent item|openshift.controlplane.controllermanager.response_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Control plane: Schedulers up, %|<p>The percentage of `scheduler` instances currently reporting `up`.</p>|Dependent item|openshift.controlplane.scheduler.response_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Control plane: API request success rate|<p>The percentage of API server requests completing without a `5xx` response, over the last 5 minutes.</p>|Dependent item|openshift.controlplane.apiserver.request_success_rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Filesystem usage|<p>The used space on the `/var` filesystem summed across all nodes, calculated as size minus available.</p>|Calculated|openshift.cluster.filesystem_usage|
|Pod count|<p>The total number of pods in the cluster (running, pending and failed).</p>|Calculated|openshift.pod.count|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Cluster: Failed to get node state metrics|<p>Failed to get node state metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Cluster by HTTP/openshift.state.node.metrics.get.check))>0`|Warning||
|OpenShift Cluster: Failed to get workload state metrics|<p>Failed to get workload state metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Cluster by HTTP/openshift.state.workload.metrics.get.check))>0`|Warning||
|OpenShift Cluster: Failed to get pod state metrics|<p>Failed to get pod state metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Cluster by HTTP/openshift.state.pod.metrics.get.check))>0`|Warning||
|OpenShift Cluster: Failed to get resource usage metrics|<p>Failed to get resource usage metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Cluster by HTTP/openshift.resource.usage.metrics.get.check))>0`|Warning||
|OpenShift Cluster: Failed to get active pod resource metrics|<p>Failed to get active pod resource metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Cluster by HTTP/openshift.pod.resource.active.metrics.get.check))>0`|Warning||
|OpenShift Cluster: Failed to get platform health metrics|<p>Failed to get platform health metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Cluster by HTTP/openshift.platform.health.metrics.get.check))>0`|Warning||
|OpenShift Cluster: Failed to get OpenShift object metrics|<p>Failed to get OpenShift object metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Cluster by HTTP/openshift.state.objects.metrics.get.check))>0`|Warning||
|OpenShift Cluster: Failed to get network usage metrics|<p>Failed to get network usage metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Cluster by HTTP/openshift.network.usage.metrics.get.check))>0`|Warning||
|OpenShift Cluster: Failed to get control plane metrics|<p>Failed to get control plane metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Cluster by HTTP/openshift.platform.controlplane.metrics.get.check))>0`|Warning||
|OpenShift Cluster: No Ready nodes|<p>There are no Ready nodes in the cluster.</p>|`max(/OpenShift Cluster by HTTP/openshift.node.ready.count,5m)=0 and min(/OpenShift Cluster by HTTP/openshift.node.count,5m)>0`|High||
|OpenShift Cluster: Low number of Ready nodes|<p>The number of Ready nodes in the cluster is less than `{$OPENSHIFT.NODE.NUMBER.THRESHOLD}`.</p>|`max(/OpenShift Cluster by HTTP/openshift.node.ready.count,5m)< {$OPENSHIFT.NODE.NUMBER.THRESHOLD} and min(/OpenShift Cluster by HTTP/openshift.node.count,5m)>0`|High|**Depends on**:<br><ul><li>OpenShift Cluster: No Ready nodes</li></ul>|
|OpenShift Cluster: High memory utilization|<p>The cluster memory utilization has exceeded `{$OPENSHIFT.CLUSTER.MEMORY.UTIL.CRIT}`%.</p>|`min(/OpenShift Cluster by HTTP/openshift.cluster.memory_utilization,5m) > {$OPENSHIFT.CLUSTER.MEMORY.UTIL.CRIT}`|Average||
|OpenShift Cluster: High memory utilization|<p>The cluster memory utilization has exceeded `{$OPENSHIFT.CLUSTER.MEMORY.UTIL.WARN}`%.</p>|`min(/OpenShift Cluster by HTTP/openshift.cluster.memory_utilization,5m) > {$OPENSHIFT.CLUSTER.MEMORY.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>OpenShift Cluster: High memory utilization</li></ul>|
|OpenShift Cluster: CPU utilization is too high|<p>Cluster CPU utilization has exceeded `{$OPENSHIFT.CLUSTER.CPU.UTIL.CRIT}`%. The cluster might be slow to respond.</p>|`min(/OpenShift Cluster by HTTP/openshift.cluster.cpu_utilization,5m) > {$OPENSHIFT.CLUSTER.CPU.UTIL.CRIT}`|Average||
|OpenShift Cluster: High CPU utilization|<p>Cluster CPU utilization is too high. The cluster might be slow to respond.</p>|`min(/OpenShift Cluster by HTTP/openshift.cluster.cpu_utilization,5m) > {$OPENSHIFT.CLUSTER.CPU.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>OpenShift Cluster: CPU utilization is too high</li></ul>|
|OpenShift Cluster: High filesystem utilization|<p>The cluster filesystem utilization has exceeded `{$OPENSHIFT.CLUSTER.FILESYSTEM.UTIL.CRIT}`%.</p>|`min(/OpenShift Cluster by HTTP/openshift.cluster.filesystem_utilization,5m) > {$OPENSHIFT.CLUSTER.FILESYSTEM.UTIL.CRIT}`|Average||
|OpenShift Cluster: High filesystem utilization|<p>The cluster filesystem utilization has exceeded `{$OPENSHIFT.CLUSTER.FILESYSTEM.UTIL.WARN}`%.</p>|`min(/OpenShift Cluster by HTTP/openshift.cluster.filesystem_utilization,5m) > {$OPENSHIFT.CLUSTER.FILESYSTEM.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>OpenShift Cluster: High filesystem utilization</li></ul>|
|OpenShift Cluster: Too many pending pods|<p>The number of pods in the Pending phase has exceeded `{$OPENSHIFT.CLUSTER.POD.PENDING.THRESHOLD}` for 5 minutes.</p>|`min(/OpenShift Cluster by HTTP/openshift.pod.pending,5m) > {$OPENSHIFT.CLUSTER.POD.PENDING.THRESHOLD}`|Warning||
|OpenShift Cluster: Too many failed pods|<p>The number of pods in the Failed phase has exceeded `{$OPENSHIFT.CLUSTER.POD.FAILED.THRESHOLD}` for 5 minutes.</p>|`min(/OpenShift Cluster by HTTP/openshift.pod.failed,5m) > {$OPENSHIFT.CLUSTER.POD.FAILED.THRESHOLD}`|Warning||
|OpenShift Cluster: ClusterOperators degraded|<p>One or more ClusterOperators are reporting a degraded (`Degraded=True`) state.</p>|`min(/OpenShift Cluster by HTTP/openshift.platform.clusteroperators_degraded,5m) > 0`|Average||
|OpenShift Cluster: Cluster version has changed|<p>The OpenShift cluster version has changed. Acknowledge to close the problem manually.</p>|`last(/OpenShift Cluster by HTTP/openshift.platform.cluster_version,#1)<>last(/OpenShift Cluster by HTTP/openshift.platform.cluster_version,#2) and length(last(/OpenShift Cluster by HTTP/openshift.platform.cluster_version))>0`|Info|**Manual close**: Yes|
|OpenShift Cluster: Control plane: One or more API servers are down|<p>One or more `apiserver` instances are not reporting `up`.</p>|`max(/OpenShift Cluster by HTTP/openshift.controlplane.apiserver.response_rate,5m) < 100`|High||
|OpenShift Cluster: Control plane: One or more controller managers are down|<p>One or more `kube-controller-manager` instances are not reporting `up`.</p>|`max(/OpenShift Cluster by HTTP/openshift.controlplane.controllermanager.response_rate,5m) < 100`|High||
|OpenShift Cluster: Control plane: One or more schedulers are down|<p>One or more `scheduler` instances are not reporting `up`.</p>|`max(/OpenShift Cluster by HTTP/openshift.controlplane.scheduler.response_rate,5m) < 100`|High||
|OpenShift Cluster: Control plane: API request success rate low|<p>The API server request success rate has dropped below `{$OPENSHIFT.APISERVER.SUCCESS_RATE.WARN}`%.</p>|`max(/OpenShift Cluster by HTTP/openshift.controlplane.apiserver.request_success_rate,5m) < {$OPENSHIFT.APISERVER.SUCCESS_RATE.WARN}`|Average||

### LLD rule Namespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace discovery||Dependent item|openshift.namespace.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Namespace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}]: CPU: Total usage|<p>The total CPU usage in the namespace calculated as a sum of non-idle CPU cores used by all containers in the namespace.</p>|Dependent item|openshift.namespace.cpu_total[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Namespace [{#NAMESPACE}]: CPU: Limits|<p>The CPU limits in the namespace calculated as a sum of CPU limits on pods in the Pending or Running phase in the namespace.</p>|Dependent item|openshift.namespace.limits.cpu[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: CPU: Requests|<p>The number of CPU cores requested by pods in the Pending or Running phase in the namespace.</p>|Dependent item|openshift.namespace.requests.cpu[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: CPU: Usage, %|<p>The CPU usage in the namespace calculated as a percentage of used CPU from allocatable CPU in the cluster.</p>|Calculated|openshift.namespace.cpu_usage[{#NAMESPACE}]|
|Namespace [{#NAMESPACE}]: Memory: Total usage|<p>The total memory usage in bytes in the namespace calculated as a sum of container working set memory in the namespace.</p>|Dependent item|openshift.namespace.memory_usage[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Namespace [{#NAMESPACE}]: Memory: Usage, %|<p>The memory usage in the namespace calculated as a percentage of used memory from allocatable memory in the cluster.</p>|Calculated|openshift.namespace.memory_usage_percentage[{#NAMESPACE}]|
|Namespace [{#NAMESPACE}]: Memory: Limits|<p>The memory limits in the namespace calculated as a sum of memory limits on pods in the Pending or Running phase in the namespace.</p>|Dependent item|openshift.namespace.limits.memory[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: Memory: Requests|<p>The memory requests in the namespace calculated as a sum of memory requests on pods in the Pending or Running phase in the namespace.</p>|Dependent item|openshift.namespace.requests.memory[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: Filesystem: Usage|<p>The ephemeral filesystem usage in bytes in the namespace calculated as a sum of container filesystem usage in the namespace.</p>|Dependent item|openshift.namespace.filesystem_usage[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Namespace [{#NAMESPACE}]: Filesystem: Usage, %|<p>The filesystem usage in the namespace calculated as a percentage of used space from the total cluster filesystem size.</p>|Calculated|openshift.namespace.filesystem_usage_percentage[{#NAMESPACE}]|
|Namespace [{#NAMESPACE}]: Network: Receive|<p>Pod network receive throughput in the namespace, summed across the namespace's pods' primary interface.</p>|Dependent item|openshift.namespace.network_receive[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Namespace [{#NAMESPACE}]: Network: Transmit|<p>Pod network transmit throughput in the namespace, summed across the namespace's pods' primary interface.</p>|Dependent item|openshift.namespace.network_transmit[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Namespace [{#NAMESPACE}]: Pods: Running|<p>The number of pods in running state in the namespace.</p>|Dependent item|openshift.namespace.pod.running[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: Pods: Pending|<p>The number of pods in the Pending phase in the namespace.</p>|Dependent item|openshift.namespace.pod.pending[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: Pods: Failed|<p>The number of pods in the Failed phase in the namespace.</p>|Dependent item|openshift.namespace.pod.failed[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}]: Status phase|<p>The current status phase of the namespace.</p>|Dependent item|openshift.namespace.status_phase[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Namespace discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Cluster: Namespace [{#NAMESPACE}]: Pods pending high|<p>The number of pods in the Pending phase in the namespace has exceeded `{$OPENSHIFT.NAMESPACE.POD.PENDING.THRESHOLD:"{#NAMESPACE}"}` for 5 minutes.</p>|`min(/OpenShift Cluster by HTTP/openshift.namespace.pod.pending[{#NAMESPACE}],5m) > {$OPENSHIFT.NAMESPACE.POD.PENDING.THRESHOLD:"{#NAMESPACE}"}`|Warning||
|OpenShift Cluster: Namespace [{#NAMESPACE}]: Terminating|<p>The namespace has entered the `Terminating` phase.</p>|`count(/OpenShift Cluster by HTTP/openshift.namespace.status_phase[{#NAMESPACE}],2m,,2)>=2`|Warning||

### LLD rule Deployment discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Deployment discovery||Dependent item|openshift.deployment.discovery[{#NAMESPACE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Deployment discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Deployment [{#DEPLOYMENT}]: CPU: Total usage|<p>The total CPU usage in the deployment calculated as a sum of non-idle CPU cores used by all containers in the deployment's pods.</p>|Dependent item|openshift.deployment.cpu_total[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: CPU: Limits|<p>The CPU limits in the deployment calculated as a sum of CPU limits on pods in the Pending or Running phase in the deployment.</p>|Dependent item|openshift.deployment.limits.cpu[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: CPU: Requests|<p>The number of CPU cores requested by the deployment's pods in the Pending or Running phase.</p>|Dependent item|openshift.deployment.requests.cpu[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: CPU: Usage, %|<p>The CPU usage in the deployment calculated as a percentage of used CPU from allocatable CPU in the cluster.</p>|Calculated|openshift.deployment.cpu_usage[{#NAMESPACE}/{#DEPLOYMENT}]|
|Deployment [{#DEPLOYMENT}]: Memory: Total usage|<p>The total memory usage in bytes in the deployment calculated as a sum of container working set memory in the deployment's pods.</p>|Dependent item|openshift.deployment.memory_usage[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: Memory: Usage, %|<p>The memory usage in the deployment calculated as a percentage of used memory from allocatable memory in the cluster.</p>|Calculated|openshift.deployment.memory_usage_percentage[{#NAMESPACE}/{#DEPLOYMENT}]|
|Deployment [{#DEPLOYMENT}]: Memory: Limits|<p>The memory limits in the deployment calculated as a sum of memory limits on pods in the Pending or Running phase in the deployment.</p>|Dependent item|openshift.deployment.limits.memory[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: Memory: Requests|<p>The memory requests in the deployment calculated as a sum of memory requests on pods in the Pending or Running phase in the deployment.</p>|Dependent item|openshift.deployment.requests.memory[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: Replicas desired|<p>Number of desired pods for a deployment.</p>|Dependent item|openshift.deployment.replicas_desired[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: Replicas available|<p>The number of available replicas per deployment.</p>|Dependent item|openshift.deployment.replicas_available[{#NAMESPACE}/{#DEPLOYMENT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment [{#DEPLOYMENT}]: Replicas mismatched|<p>The number of available replicas not matching the desired number of replicas.</p>|Calculated|openshift.deployment.replicas_mismatched[{#NAMESPACE}/{#DEPLOYMENT}]|

### Trigger prototypes for Deployment discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Cluster: Deployment [{#DEPLOYMENT}]: Deployment replicas mismatch|<p>Deployment has not matched the expected number of replicas during the specified trigger evaluation period.</p>|`min(/OpenShift Cluster by HTTP/openshift.deployment.replicas_mismatched[{#NAMESPACE}/{#DEPLOYMENT}],{$OPENSHIFT.REPLICA.MISMATCH.EVAL_PERIOD:"deployment:{#NAMESPACE}:{#DEPLOYMENT}"})>0 and last(/OpenShift Cluster by HTTP/openshift.deployment.replicas_desired[{#NAMESPACE}/{#DEPLOYMENT}])>=0 and last(/OpenShift Cluster by HTTP/openshift.deployment.replicas_available[{#NAMESPACE}/{#DEPLOYMENT}])>=0`|Warning||

### LLD rule Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pod discovery||Dependent item|openshift.pod.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Status phase|<p>The current phase of the pod.</p>|Dependent item|openshift.pod.status_phase[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Container restarts|<p>The total number of container restarts in the pod.</p>|Dependent item|openshift.pod.containers_restarts[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Pod discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Cluster: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod failed phase|<p>The pod is in the Failed phase: all its containers have terminated and at least one terminated with an error.</p>|`count(/OpenShift Cluster by HTTP/openshift.pod.status_phase[{#NAMESPACE}/{#NAME}],2m,,3)>=2`|Average||
|OpenShift Cluster: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod pending phase|<p>The pod is in the Pending phase: it has been accepted by the Kubernetes system, but one or more of the containers has not been started yet.</p>|`count(/OpenShift Cluster by HTTP/openshift.pod.status_phase[{#NAMESPACE}/{#NAME}],2m,,1)>=2`|Warning||
|OpenShift Cluster: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod unknown phase|<p>The state of the pod could not be obtained, typically because of an error communicating with the node that hosts it.</p>|`count(/OpenShift Cluster by HTTP/openshift.pod.status_phase[{#NAMESPACE}/{#NAME}],2m,,4)>=2`|Warning||
|OpenShift Cluster: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod is crash looping|<p>Containers of the pod keep restarting. This most likely indicates that the pod is in the CrashLoopBackOff state.</p>|`(last(/OpenShift Cluster by HTTP/openshift.pod.containers_restarts[{#NAMESPACE}/{#NAME}])-min(/OpenShift Cluster by HTTP/openshift.pod.containers_restarts[{#NAMESPACE}/{#NAME}],15m))>1`|Warning||
|OpenShift Cluster: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Too many container restarts|<p>Containers of the pod restarted more than `{$OPENSHIFT.POD.RESTARTS.THRESHOLD}` times during the last `{$OPENSHIFT.POD.RESTARTS.PERIOD}`. Unlike the crash looping trigger, this catches pods that restart regularly but too slowly to be detected as crash looping.</p>|`(last(/OpenShift Cluster by HTTP/openshift.pod.containers_restarts[{#NAMESPACE}/{#NAME}])-min(/OpenShift Cluster by HTTP/openshift.pod.containers_restarts[{#NAMESPACE}/{#NAME}],{$OPENSHIFT.POD.RESTARTS.PERIOD:"{#NAMESPACE}"}))>{$OPENSHIFT.POD.RESTARTS.THRESHOLD:"{#NAMESPACE}"}`|Warning|**Depends on**:<br><ul><li>OpenShift Cluster: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod is crash looping</li></ul>|

### LLD rule Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node discovery||Dependent item|openshift.node.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule OpenShift Route discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift Route discovery||Dependent item|openshift.route.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for OpenShift Route discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Route [{#NAME}]: Created|<p>OpenShift Route Unix creation timestamp.</p>|Dependent item|openshift.route.created.time[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Namespace [{#NAMESPACE}] Route [{#NAME}]: Admitted|<p>Whether the route has been admitted by the ingress router (`Admitted` condition status).</p>|Dependent item|openshift.route.status[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Boolean to decimal</li></ul>|

### Trigger prototypes for OpenShift Route discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Cluster: Namespace [{#NAMESPACE}] Route [{#NAME}]: Not admitted||`last(/OpenShift Cluster by HTTP/openshift.route.status[{#NAMESPACE}/{#NAME}])=0`|Warning||

### LLD rule OpenShift BuildConfig discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift BuildConfig discovery||Dependent item|openshift.buildconfig.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for OpenShift BuildConfig discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] BuildConfig [{#NAME}]: Created|<p>OpenShift BuildConfig Unix creation timestamp.</p>|Dependent item|openshift.buildconfig.created.time[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Namespace [{#NAMESPACE}] BuildConfig [{#NAME}]: Generation|<p>Sequence number representing a specific generation of the desired state.</p>|Dependent item|openshift.buildconfig.generation[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] BuildConfig [{#NAME}]: Latest version|<p>The latest version of BuildConfig.</p>|Dependent item|openshift.buildconfig.status[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### LLD rule OpenShift Build discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift Build discovery||Dependent item|openshift.build.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for OpenShift Build discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Build [{#NAME}]: Created|<p>OpenShift Build Unix creation timestamp.</p>|Dependent item|openshift.build.created.time[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Namespace [{#NAMESPACE}] Build [{#NAME}]: Status phase|<p>The current phase of the build.</p>|Dependent item|openshift.build.status_phase[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for OpenShift Build discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Cluster: Namespace [{#NAMESPACE}] Build [{#NAME}]: Build has failed||`count(/OpenShift Cluster by HTTP/openshift.build.status_phase[{#NAMESPACE}/{#NAME}],2m,"ge",6)>=2`|Warning||

### LLD rule OpenShift PVC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift PVC discovery||Dependent item|openshift.pvc.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for OpenShift PVC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] PVC [{#NAME}]: Status phase|<p>The current status phase of the persistent volume claim.</p>|Dependent item|openshift.pvc.status_phase[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Namespace [{#NAMESPACE}] PVC [{#NAME}]: Requested storage|<p>The capacity of storage requested by the persistent volume claim.</p>|Dependent item|openshift.pvc.requested.storage[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for OpenShift PVC discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Cluster: Namespace [{#NAMESPACE}] PVC [{#NAME}]: PVC is pending|<p>The persistent volume claim has been stuck in the Pending phase.</p>|`count(/OpenShift Cluster by HTTP/openshift.pvc.status_phase[{#NAMESPACE}/{#NAME}],2m,,5)>=2`|Warning||
|OpenShift Cluster: Namespace [{#NAMESPACE}] PVC [{#NAME}]: PVC is lost|<p>The persistent volume claim has lost its backing persistent volume.</p>|`count(/OpenShift Cluster by HTTP/openshift.pvc.status_phase[{#NAMESPACE}/{#NAME}],2m,,4)>=2`|Average||

### LLD rule OpenShift PV discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift PV discovery||Dependent item|openshift.pv.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for OpenShift PV discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PV [{#NAME}]: Status phase|<p>The current status phase of the persistent volume.</p>|Dependent item|openshift.pv.status_phase[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|PV [{#NAME}]: Capacity bytes|<p>The capacity of the persistent volume in bytes.</p>|Dependent item|openshift.pv.capacity.bytes[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for OpenShift PV discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Cluster: PV [{#NAME}]: PV has failed|<p>The persistent volume has entered the Failed phase.</p>|`count(/OpenShift Cluster by HTTP/openshift.pv.status_phase[{#NAME}],2m,,3)>=2`|Warning||

### LLD rule OpenShift ClusterResourceQuota discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift ClusterResourceQuota discovery||Dependent item|openshift.cluster.resource.quota.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for OpenShift ClusterResourceQuota discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Quota [{#NAME}]: CPU: Requests hard|<p>The CPU requests quota (hard limit) configured for the ClusterResourceQuota.</p>|Dependent item|openshift.cluster.resource.quota.cpu_requests_hard[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Quota [{#NAME}]: CPU: Requests used|<p>The CPU requests quota usage for the ClusterResourceQuota.</p>|Dependent item|openshift.cluster.resource.quota.cpu_requests_used[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Quota [{#NAME}]: CPU: Limits hard|<p>The CPU limits quota (hard limit) configured for the ClusterResourceQuota.</p>|Dependent item|openshift.cluster.resource.quota.cpu_limits_hard[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Quota [{#NAME}]: CPU: Limits used|<p>The CPU limits quota usage for the ClusterResourceQuota.</p>|Dependent item|openshift.cluster.resource.quota.cpu_limits_used[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Quota [{#NAME}]: Memory: Requests hard|<p>The memory requests quota (hard limit) configured for the ClusterResourceQuota.</p>|Dependent item|openshift.cluster.resource.quota.memory_requests_hard[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Quota [{#NAME}]: Memory: Requests used|<p>The memory requests quota usage for the ClusterResourceQuota.</p>|Dependent item|openshift.cluster.resource.quota.memory_requests_used[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Quota [{#NAME}]: Memory: Limits hard|<p>The memory limits quota (hard limit) configured for the ClusterResourceQuota.</p>|Dependent item|openshift.cluster.resource.quota.memory_limits_hard[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Quota [{#NAME}]: Memory: Limits used|<p>The memory limits quota usage for the ClusterResourceQuota.</p>|Dependent item|openshift.cluster.resource.quota.memory_limits_used[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Quota [{#NAME}]: Pods: Hard|<p>The pods quota (hard limit) configured for the ClusterResourceQuota.</p>|Dependent item|openshift.cluster.resource.quota.pods_hard[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Quota [{#NAME}]: Pods: Used|<p>The pods quota usage for the ClusterResourceQuota.</p>|Dependent item|openshift.cluster.resource.quota.pods_used[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule ClusterOperator discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ClusterOperator discovery||Dependent item|openshift.clusteroperator.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for ClusterOperator discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ClusterOperator [{#NAME}]: Status|<p>The status of the ClusterOperator (matching `oc get clusteroperators`): whichever of its Available/Progressing/Degraded conditions currently reports `True`.</p>|Dependent item|openshift.clusteroperator.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for ClusterOperator discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Cluster: ClusterOperator [{#NAME}]: Not healthy|<p>The ClusterOperator is reporting Degraded or is not Available.</p>|`last(/OpenShift Cluster by HTTP/openshift.clusteroperator.status[{#NAME}])=3`|High||

### LLD rule OpenShift Alert discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift Alert discovery||Dependent item|openshift.alert.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.result[?(@.metric.__name__=='ALERTS')].metric`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `5m`</p></li></ul>|

### Item prototypes for OpenShift Alert discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Alert [{#ALERTNAME}]: State|<p>The current state of the alert in Prometheus/Alertmanager: Firing if the alert condition is true and its `for` duration has elapsed, Pending while that duration is still counting down. "Silenced" and "Not firing" (shown in the OpenShift console) are Alertmanager-level states not exposed via the `ALERTS` metric and cannot be derived here.</p>|Dependent item|openshift.alert.state[{#ALERTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for OpenShift Alert discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Cluster: OpenShift alert firing: {#ALERTNAME}|<p>This OpenShift alert is currently firing in Prometheus/Alertmanager. Check the cluster console's Alerting page for details.</p>|`last(/OpenShift Cluster by HTTP/openshift.alert.state[{#ALERTNAME}])=1`|Warning||

# OpenShift Node by HTTP

## Overview

The template to monitor an OpenShift Node.
It works without external scripts and collects metrics by HTTP agent directly from the built-in Thanos Querier API.

Template `OpenShift Node by HTTP` collects the metrics of a single node (readiness, schedulability, allocatable resources, CPU, load average, memory, filesystem and uptime) by querying the Thanos Querier route (PromQL `/api/v1/query`) and filtering the federated kube-state-metrics and node-exporter series by the node name.

**Note:** Some metrics may not be collected depending on your OpenShift version and configuration.

## Requirements

Zabbix version: 8.2 and higher.

## Tested versions

This template has been tested on:
- OpenShift v4.22

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.2/manual/config/templates_out_of_the_box) section.

## Setup

This template is used in low-level discovery and will be auto-assigned to the node host prototypes of the template `OpenShift Cluster by HTTP`. The node name is passed to the discovered host in the macro `{$OPENSHIFT.NODE.NAME}`.

The discovered node hosts query the same Thanos Querier route as the cluster host, so they need the `{$OPENSHIFT.THANOS.URL}` and `{$OPENSHIFT.THANOS.TOKEN}` macros. Discovered hosts do not take macros from the cluster host, so define both macros on the global level (**Administration** → **Macros**), with the token as a secret macro.

**Note:** Some metrics may not be collected depending on your OpenShift version and configuration.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$OPENSHIFT.NODE.NAME}|<p>The name of the node as reported by the Kubernetes API. Set automatically by the host prototype of the template `OpenShift Cluster by HTTP`.</p>||
|{$OPENSHIFT.HTTP.PROXY}|<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p>||
|{$OPENSHIFT.NODE.CPU.UTIL.CRIT}|<p>The critical threshold of the node CPU utilization expressed in %.</p>|`90`|
|{$OPENSHIFT.NODE.LOAD_AVG_PER_CPU.MAX.WARN}|<p>The CPU load per core that is considered sustainable.</p>|`1.5`|
|{$OPENSHIFT.NODE.MEMORY.UTIL.CRIT}|<p>The critical threshold of the node memory utilization expressed in %.</p>|`90`|
|{$OPENSHIFT.NODE.FILESYSTEM.UTIL.WARN}|<p>The warning threshold of the node filesystem utilization expressed in %.</p>|`80`|
|{$OPENSHIFT.NODE.FILESYSTEM.UTIL.CRIT}|<p>The critical threshold of the node filesystem utilization expressed in %.</p>|`90`|
|{$OPENSHIFT.NODE.NETWORK.IF.EXCLUDE}|<p>Network interfaces excluded from the node's total network receive/transmit, so loopback and OVN-Kubernetes's own internal bridges/tunnels/veths don't double-count real traffic.</p>|`lo\|veth.*\|ovs-system\|br-.*\|tap.*\|tun.*\|genev_sys.*\|ovn-k8s.*`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get node state metrics|<p>Collecting kube-state-metrics of the node from the Thanos Querier API.</p>|HTTP agent|openshift.node.state.metrics.get|
|Get node-exporter metrics|<p>Collecting node-exporter load, memory, boot time and filesystem metrics of the node from the Thanos Querier API. Filesystem metrics are restricted to the mountpoint, which backs kubelet/crio storage.</p>|HTTP agent|openshift.node.exporter.metrics.get|
|Get node usage metrics|<p>Collecting the node CPU utilization, number of CPU cores, and network receive/transmit throughput from the Thanos Querier API, pre-aggregated server-side by PromQL. Network totals exclude loopback and virtual interfaces via `{$OPENSHIFT.NODE.NETWORK.IF.EXCLUDE}`.</p>|HTTP agent|openshift.node.usage.metrics.get|
|Get node state metrics check|<p>Check that the node state data has been received correctly from Thanos.</p>|Dependent item|openshift.node.state.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get node-exporter metrics check|<p>Check that the node-exporter data has been received correctly from Thanos.</p>|Dependent item|openshift.node.exporter.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get node usage metrics check|<p>Check that the node usage data has been received correctly from Thanos.</p>|Dependent item|openshift.node.usage.metrics.get.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Ready|<p>The status of the node's `Ready` condition (matching `oc get nodes`): True, False, or Unknown (the kubelet has stopped reporting, e.g. due to a lost heartbeat).</p>|Dependent item|openshift.node.ready<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Schedulable|<p>Whether a node can schedule new pods.</p><p>The raw value of `kube_node_spec_unschedulable` for the node: note the metric is inverted, i.e. `1` means the node is cordoned (unschedulable).</p>|Dependent item|openshift.node.schedulable<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Uptime|<p>The time since the node was booted.</p>|Dependent item|openshift.node.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `return Math.floor(Date.now() / 1000 - Number(value));`</p></li></ul>|
|CPU: Allocatable|<p>The CPU allocatable on the node.</p>|Dependent item|openshift.node.cpu_allocatable<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU: Cores|<p>The number of CPU cores on the node.</p>|Dependent item|openshift.node.cpu_cores<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU: Utilization|<p>The CPU utilization of the node expressed in %, calculated from the non-idle time of all cores over the last 5 minutes.</p>|Dependent item|openshift.node.cpu_utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU: Load average 1m|<p>The 1-minute load average of the node.</p>|Dependent item|openshift.node.load1<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU: Load average 5m|<p>The 5-minute load average of the node.</p>|Dependent item|openshift.node.load5<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU: Load average 15m|<p>The 15-minute load average of the node.</p>|Dependent item|openshift.node.load15<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory: Allocatable|<p>The memory allocatable on the node.</p>|Dependent item|openshift.node.memory_allocatable<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory: Total|<p>The total memory of the node.</p>|Dependent item|openshift.node.memory_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory: Available|<p>The memory available on the node for starting new applications, without swapping.</p>|Dependent item|openshift.node.memory_available<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory: Utilization|<p>The memory utilization of the node expressed in %, calculated from the available memory.</p>|Calculated|openshift.node.memory_utilization|
|Filesystem: Size|<p>The size of the filesystem on the node.</p>|Dependent item|openshift.node.filesystem_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Filesystem: Available|<p>The available space on the filesystem on the node.</p>|Dependent item|openshift.node.filesystem_available<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Filesystem: Utilization|<p>The utilization of the filesystem on the node expressed in %.</p>|Calculated|openshift.node.filesystem_utilization|
|Network: Receive|<p>The node's network receive throughput, summed across all physical interfaces (excludes loopback and virtual interfaces matched by `{$OPENSHIFT.NODE.NETWORK.IF.EXCLUDE}`).</p>|Dependent item|openshift.node.network_receive<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network: Transmit|<p>The node's network transmit throughput, summed across all physical interfaces (excludes loopback and virtual interfaces matched by `{$OPENSHIFT.NODE.NETWORK.IF.EXCLUDE}`).</p>|Dependent item|openshift.node.network_transmit<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|OpenShift Node: Failed to get node state metrics|<p>Failed to get node state metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Node by HTTP/openshift.node.state.metrics.get.check))>0`|Warning||
|OpenShift Node: Failed to get node-exporter metrics|<p>Failed to get node-exporter metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Node by HTTP/openshift.node.exporter.metrics.get.check))>0`|Warning||
|OpenShift Node: Failed to get node usage metrics|<p>Failed to get node usage metrics from the Thanos Querier API.</p>|`length(last(/OpenShift Node by HTTP/openshift.node.usage.metrics.get.check))>0`|Warning||
|OpenShift Node: Not Ready|<p>The node is not in the Ready condition (False), or has stopped reporting its status (Unknown).</p>|`min(/OpenShift Node by HTTP/openshift.node.ready,5m)>=2`|High||
|OpenShift Node: Node has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/OpenShift Node by HTTP/openshift.node.uptime)<10m`|Info|**Manual close**: Yes|
|OpenShift Node: High CPU utilization|<p>The CPU utilization of the node is too high. The system might be slow to respond.</p>|`min(/OpenShift Node by HTTP/openshift.node.cpu_utilization,5m) > {$OPENSHIFT.NODE.CPU.UTIL.CRIT}`|Warning||
|OpenShift Node: Load average is too high|<p>The load average per CPU is too high. The system might be slow to respond.</p>|`min(/OpenShift Node by HTTP/openshift.node.load1,5m)/last(/OpenShift Node by HTTP/openshift.node.cpu_cores)>{$OPENSHIFT.NODE.LOAD_AVG_PER_CPU.MAX.WARN} and last(/OpenShift Node by HTTP/openshift.node.load5)>0 and last(/OpenShift Node by HTTP/openshift.node.load15)>0`|Average|**Depends on**:<br><ul><li>OpenShift Node: High CPU utilization</li></ul>|
|OpenShift Node: High memory utilization|<p>The memory utilization of the node is too high.</p>|`min(/OpenShift Node by HTTP/openshift.node.memory_utilization,5m) > {$OPENSHIFT.NODE.MEMORY.UTIL.CRIT}`|Average||
|OpenShift Node: High filesystem utilization|<p>The utilization of the filesystem on the node has exceeded `{$OPENSHIFT.NODE.FILESYSTEM.UTIL.CRIT}`%.</p>|`min(/OpenShift Node by HTTP/openshift.node.filesystem_utilization,5m) > {$OPENSHIFT.NODE.FILESYSTEM.UTIL.CRIT}`|Average||
|OpenShift Node: High filesystem utilization|<p>The utilization of the filesystem on the node has exceeded `{$OPENSHIFT.NODE.FILESYSTEM.UTIL.WARN}`%.</p>|`min(/OpenShift Node by HTTP/openshift.node.filesystem_utilization,5m) > {$OPENSHIFT.NODE.FILESYSTEM.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>OpenShift Node: High filesystem utilization</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

