
# Kubernetes cluster state by HTTP

## Overview

The template to monitor Kubernetes state.
It works without external scripts and uses the script item to make HTTP requests to the Kubernetes API.

Template `Kubernetes cluster state by HTTP` - collects metrics by HTTP agent from kube-state-metrics endpoint and Kubernetes API.

Don't forget to change macros {$KUBE.API.URL} and {$KUBE.API.TOKEN}.
Also, see the Macros section for a list of macros used to set trigger values.

**Note:** Some metrics may not be collected depending on your Kubernetes version and configuration.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Kubernetes 1.19.10

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Install the [Zabbix Helm Chart](https://git.zabbix.com/projects/ZT/repos/kubernetes-helm/browse?at=refs%2Fheads%2Fmaster) in your Kubernetes cluster.
Internal service metrics are collected from kube-state-metrics endpoint.

Template needs to use authorization via API token.

Set the `{$KUBE.API.URL}` such as `<scheme>://<host>:<port>`.

Get the service account name. If a different release name is used.

`kubectl get serviceaccounts -n monitoring`

Get the generated service account token using the command:

`kubectl get secret zabbix-zabbix-helm-chart -n monitoring -o jsonpath={.data.token} | base64 -d`

Then set it to the macro `{$KUBE.API.TOKEN}`.
Set `{$KUBE.STATE.ENDPOINT.NAME}` with Kube state metrics endpoint name. See `kubectl -n monitoring get ep`. Default: `zabbix-kube-state-metrics`.

**Note:** If you wish to monitor Controller Manager and Scheduler components, you might need to set the `--binding-address` option for them to the address where Zabbix proxy can reach them.
For example, for clusters created with `kubeadm` it can be set in the following manifest files (changes will be applied immediately):

- /etc/kubernetes/manifests/kube-controller-manager.yaml
- /etc/kubernetes/manifests/kube-scheduler.yaml

Depending on your Kubernetes distribution, you might need to adjust `{$KUBE.CONTROL_PLANE.TAINT}` macro (for example, set it to `node-role.kubernetes.io/master` for OpenShift).

**Note:** Some metrics may not be collected depending on your Kubernetes version and configuration.

Also, see the Macros section for a list of macros used to set trigger values.

Set up the macros to filter the metrics of discovered Kubelets by node names:

- {$KUBE.LLD.FILTER.KUBELET_NODE.MATCHES}
- {$KUBE.LLD.FILTER.KUBELET_NODE.NOT_MATCHES}

Set up macros to filter metrics by namespace:

- {$KUBE.LLD.FILTER.NAMESPACE.MATCHES}
- {$KUBE.LLD.FILTER.NAMESPACE.NOT_MATCHES}

Set up macros to filter node metrics by nodename:

- {$KUBE.LLD.FILTER.NODE.MATCHES}
- {$KUBE.LLD.FILTER.NODE.NOT_MATCHES}

**Note:** If you have a large cluster, it is highly recommended to set a filter for discoverable namespaces.

You can use the `{$KUBE.KUBELET.FILTER.LABELS}` and `{$KUBE.KUBELET.FILTER.ANNOTATIONS}` macros for advanced filtering of kubelets by node labels and annotations.

Notes about labels and annotations filters:

- Macro values should be specified separated by commas and must have the key/value form with support for regular expressions in the value (`key1: value, key2: regexp`).
- ECMAScript syntax is used for regular expressions.
- Filters are applied if such label key exists for the entity that is being filtered (it means that if you specify a key in the filter, entities that do not have this key will not be affected by the filter and will still be discovered, and only entities containing that key will be filtered by the value).
- You can also use the exclamation point symbol (`!`) to invert the filter (`!key: value`).

For example: `kubernetes.io/hostname: kubernetes-node[5-25], !node-role.kubernetes.io/ingress: .*`. As a result, the kubelets on nodes 5-25 without the "ingress" role will be discovered.


See the Kubernetes documentation for details about labels and annotations:

- <https://kubernetes.io/docs/concepts/overview/working-with-objects/labels/>
- <https://kubernetes.io/docs/concepts/overview/working-with-objects/annotations/>

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
|{$KUBE.API.URL}|<p>Kubernetes API endpoint URL in the format <scheme>://<host>:<port></p>|`https://kubernetes.default.svc.cluster.local:443`|
|{$KUBE.API.LIVEZ.ENDPOINT}|<p>Kubernetes API livez endpoint /livez</p>|`/livez`|
|{$KUBE.API.COMPONENTSTATUSES.ENDPOINT}|<p>Kubernetes API componentstatuses endpoint /api/v1/componentstatuses</p>|`/api/v1/componentstatuses`|
|{$KUBE.API.READYZ.ENDPOINT}|<p>Kubernetes API readyz endpoint /readyz</p>|`/readyz`|
|{$KUBE.HTTP.PROXY}|<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p>||
|{$KUBE.STATE.ENDPOINT.NAME}|<p>Endpoint name for kube-state-metrics service (check with `kubectl get ep -n monitoring -owide`).</p>|`zabbix-kube-state-metrics`|
|{$OPENSHIFT.STATE.ENDPOINT.NAME}|<p>OpenShift state endpoint name.</p>|`openshift-state-metrics`|
|{$KUBE.API_SERVER.SCHEME}|<p>Kubernetes API servers metrics endpoint scheme. Used in ControlPlane LLD.</p>|`https`|
|{$KUBE.API_SERVER.PORT}|<p>Kubernetes API servers metrics endpoint port. Used in ControlPlane LLD.</p>|`6443`|
|{$KUBE.CONTROL_PLANE.TAINT}|<p>Taint that applies to control plane nodes. Change if needed. Used in ControlPlane LLD.</p>|`node-role.kubernetes.io/control-plane`|
|{$KUBE.CONTROLLER_MANAGER.SCHEME}|<p>Kubernetes Controller manager metrics endpoint scheme. Used in ControlPlane LLD.</p>|`https`|
|{$KUBE.CONTROLLER_MANAGER.PORT}|<p>Kubernetes Controller manager metrics endpoint port. Used in ControlPlane LLD.</p>|`10257`|
|{$KUBE.SCHEDULER.SCHEME}|<p>Kubernetes Scheduler metrics endpoint scheme. Used in ControlPlane LLD.</p>|`https`|
|{$KUBE.SCHEDULER.PORT}|<p>Kubernetes Scheduler metrics endpoint port. Used in ControlPlane LLD.</p>|`10259`|
|{$KUBE.KUBELET.SCHEME}|<p>Kubernetes Kubelet metrics endpoint scheme. Used in Kubelet LLD.</p>|`https`|
|{$KUBE.KUBELET.PORT}|<p>Kubernetes Kubelet metrics endpoint port. Used in Kubelet LLD.</p>|`10250`|
|{$KUBE.LLD.FILTER.NAMESPACE.MATCHES}|<p>Filter of discoverable metrics by namespace.</p>|`.*`|
|{$KUBE.LLD.FILTER.NAMESPACE.NOT_MATCHES}|<p>Filter to exclude discovered metrics by namespace.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.LLD.FILTER.NODE.MATCHES}|<p>Filter of discoverable nodes by nodename.</p>|`.*`|
|{$KUBE.LLD.FILTER.NODE.NOT_MATCHES}|<p>Filter to exclude discovered nodes by nodename.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.LLD.FILTER.KUBELET_NODE.MATCHES}|<p>Filter of discoverable Kubelets by nodename.</p>|`.*`|
|{$KUBE.LLD.FILTER.KUBELET_NODE.NOT_MATCHES}|<p>Filter to exclude discovered Kubelets by nodename.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.KUBELET.FILTER.ANNOTATIONS}|<p>Node annotations to filter Kubelets (regex in values are supported). See the template's README.md for details.</p>||
|{$KUBE.KUBELET.FILTER.LABELS}|<p>Node labels to filter Kubelets (regex in values are supported). See the template's README.md for details.</p>||
|{$KUBE.LLD.FILTER.PV.MATCHES}|<p>Filter of discoverable persistent volumes by name.</p>|`.*`|
|{$KUBE.LLD.FILTER.PV.NOT_MATCHES}|<p>Filter to exclude discovered persistent volumes by name.</p>|`CHANGE_IF_NEEDED`|
|{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD}|<p>The evaluation period range which is used for calculation of expressions in trigger prototypes (time period or value range). Can be used with context.</p>|`#5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get state metrics|<p>Collecting Kubernetes metrics from kube-state-metrics.</p>|Script|kube.state.metrics|
|Control plane LLD|<p>Generation of data for Control plane discovery rules.</p>|Script|kube.control_plane.lld<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Node LLD|<p>Generation of data for Kubelet discovery rules.</p>|Script|kube.node.lld<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get component statuses||HTTP agent|kube.componentstatuses<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get readyz||HTTP agent|kube.readyz<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get livez||HTTP agent|kube.livez<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Namespace count|<p>The number of namespaces.</p>|Dependent item|kube.namespace.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_namespace_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CronJob count|<p>Number of cronjobs.</p>|Dependent item|kube.cronjob.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_cronjob_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Job count|<p>Number of jobs (generated by cronjob + job).</p>|Dependent item|kube.job.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_job_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Endpoint count|<p>Number of endpoints.</p>|Dependent item|kube.endpoint.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_endpoint_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deployment count|<p>The number of deployments.</p>|Dependent item|kube.deployment.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_deployment_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Service count|<p>The number of services.</p>|Dependent item|kube.service.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_service_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|StatefulSet count|<p>The number of statefulsets.</p>|Dependent item|kube.statefulset.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_statefulset_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node count|<p>The number of nodes.</p>|Dependent item|kube.node.count<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `COUNT(kube_node_created)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule API servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|API servers discovery||Dependent item|kube.api_servers.discovery|

### LLD rule Controller manager nodes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controller manager nodes discovery||Dependent item|kube.controller_manager.discovery|

### LLD rule Scheduler servers nodes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Scheduler servers nodes discovery||Dependent item|kube.scheduler.discovery|

### LLD rule Kubelet discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kubelet discovery||Dependent item|kube.kubelet.discovery|

### LLD rule Daemonset discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Daemonset discovery||Dependent item|kube.daemonset.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_daemonset_status_number_ready`</p></li></ul>|

### Item prototypes for Daemonset discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Daemonset [{#NAME}]: Ready|<p>The number of nodes that should be running the daemon pod and have one or more running and ready.</p>|Dependent item|kube.daemonset.ready[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Daemonset [{#NAME}]: Scheduled|<p>The number of nodes that run at least one daemon pod and are supposed to.</p>|Dependent item|kube.daemonset.scheduled[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Daemonset [{#NAME}]: Desired|<p>The number of nodes that should be running the daemon pod.</p>|Dependent item|kube.daemonset.desired[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Daemonset [{#NAME}]: Misscheduled|<p>The number of nodes that run a daemon pod but are not supposed to.</p>|Dependent item|kube.daemonset.misscheduled[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Daemonset [{#NAME}]: Updated number scheduled|<p>The total number of nodes that are running updated daemon pod.</p>|Dependent item|kube.daemonset.updated[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule PVC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PVC discovery||Dependent item|kube.pvc.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_persistentvolumeclaim_info`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

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
|Kubernetes cluster state: NS [{#NAMESPACE}] PVC [{#NAME}]: PVC is pending||`count(/Kubernetes cluster state by HTTP/kube.pvc.status_phase[{#NAMESPACE}/{#NAME}],2m,,5)>=2`|Warning||

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
|Kubernetes cluster state: PV [{#NAME}]: PV has failed||`count(/Kubernetes cluster state by HTTP/kube.pv.status_phase[{#NAME}],2m,,3)>=2`|Warning||

### LLD rule Deployment discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Deployment discovery||Dependent item|kube.deployment.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_deployment_spec_paused`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Deployment discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Deployment [{#NAME}]: Paused|<p>Whether the deployment is paused and will not be processed by the deployment controller.</p>|Dependent item|kube.deployment.spec_paused[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Deployment [{#NAME}]: Replicas desired|<p>Number of desired pods for a deployment.</p>|Dependent item|kube.deployment.replicas_desired[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Deployment [{#NAME}]: Rollingupdate max unavailable|<p>Maximum number of unavailable replicas during a rolling update of a deployment.</p>|Dependent item|kube.deployment.rollingupdate.max_unavailable[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Deployment [{#NAME}]: Replicas|<p>The number of replicas per deployment.</p>|Dependent item|kube.deployment.replicas[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Deployment [{#NAME}]: Replicas available|<p>The number of available replicas per deployment.</p>|Dependent item|kube.deployment.replicas_available[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Deployment [{#NAME}]: Replicas unavailable|<p>The number of unavailable replicas per deployment.</p>|Dependent item|kube.deployment.replicas_unavailable[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Deployment [{#NAME}]: Replicas updated|<p>The number of updated replicas per deployment.</p>|Dependent item|kube.deployment.replicas_updated[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Deployment [{#NAME}]: Replicas mismatched|<p>The number of available replicas not matching the desired number of replicas.</p>|Dependent item|kube.deployment.replicas_mismatched[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Deployment discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes cluster state: Namespace [{#NAMESPACE}] Deployment [{#NAME}]: Deployment replicas mismatch|<p>Deployment has not matched the expected number of replicas during the specified trigger evaluation period.</p>|`min(/Kubernetes cluster state by HTTP/kube.deployment.replicas_mismatched[{#NAMESPACE}/{#NAME}],{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD:"deployment:{#NAMESPACE}:{#NAME}"})>0 and last(/Kubernetes cluster state by HTTP/kube.deployment.replicas_desired[{#NAMESPACE}/{#NAME}])>=0 and last(/Kubernetes cluster state by HTTP/kube.deployment.replicas_available[{#NAMESPACE}/{#NAME}])>=0`|Warning||

### LLD rule Endpoint discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Endpoint discovery||Dependent item|kube.endpoint.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_endpoint_created`</p></li></ul>|

### Item prototypes for Endpoint discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Endpoint [{#NAME}]: Address available|<p>Number of addresses available in endpoint.</p>|Dependent item|kube.endpoint.address_available[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Endpoint [{#NAME}]: Address not ready|<p>Number of addresses not ready in endpoint.</p>|Dependent item|kube.endpoint.address_not_ready[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Endpoint [{#NAME}]: Age|<p>Endpoint age (number of seconds since creation).</p>|Dependent item|kube.endpoint.age[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `return (Math.floor(Date.now()/1000)-Number(value));`</p></li></ul>|

### LLD rule Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node discovery||Dependent item|kube.node.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_node_info`</p></li></ul>|

### Item prototypes for Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NAME}]: CPU allocatable|<p>The CPU resources of a node that are available for scheduling.</p>|Dependent item|kube.node.cpu_allocatable[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NAME}]: Memory allocatable|<p>The memory resources of a node that are available for scheduling.</p>|Dependent item|kube.node.memory_allocatable[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NAME}]: Pods allocatable|<p>The pods resources of a node that are available for scheduling.</p>|Dependent item|kube.node.pods_allocatable[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NAME}]: Ephemeral storage allocatable|<p>The allocatable ephemeral storage of a node that is available for scheduling.</p>|Dependent item|kube.node.ephemeral_storage_allocatable[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NAME}]: CPU capacity|<p>The capacity for CPU resources of a node.</p>|Dependent item|kube.node.cpu_capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NAME}]: Memory capacity|<p>The capacity for memory resources of a node.</p>|Dependent item|kube.node.memory_capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NAME}]: Ephemeral storage capacity|<p>The ephemeral storage capacity of a node.</p>|Dependent item|kube.node.ephemeral_storage_capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NAME}]: Pods capacity|<p>The capacity for pods resources of a node.</p>|Dependent item|kube.node.pods_capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pod discovery||Dependent item|kube.pod.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_pod_start_time`</p></li></ul>|

### Item prototypes for Pod discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Pod [{#NAME}] Phase: Pending|<p>Pod is in pending state.</p>|Dependent item|kube.pod.phase.pending[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}] Phase: Succeeded|<p>Pod is in succeeded state.</p>|Dependent item|kube.pod.phase.succeeded[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}] Phase: Failed|<p>Pod is in failed state.</p>|Dependent item|kube.pod.phase.failed[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}] Phase: Unknown|<p>Pod is in unknown state.</p>|Dependent item|kube.pod.phase.unknown[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}] Phase: Running|<p>Pod is in unknown state.</p>|Dependent item|kube.pod.phase.running[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Containers terminated|<p>Describes whether the container is currently in terminated state.</p>|Dependent item|kube.pod.containers_terminated[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Containers waiting|<p>Describes whether the container is currently in waiting state.</p>|Dependent item|kube.pod.containers_waiting[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Containers ready|<p>Describes whether the containers readiness check succeeded.</p>|Dependent item|kube.pod.containers_ready[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Containers restarts|<p>The number of container restarts.</p>|Dependent item|kube.pod.containers_restarts[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Containers running|<p>Describes whether the container is currently in running state.</p>|Dependent item|kube.pod.containers_running[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Ready|<p>Describes whether the pod is ready to serve requests.</p>|Dependent item|kube.pod.ready[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Scheduled|<p>Describes the status of the scheduling process for the pod.</p>|Dependent item|kube.pod.scheduled[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Unschedulable|<p>Describes the unschedulable status for the pod.</p>|Dependent item|kube.pod.unschedulable[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Containers CPU limits|<p>The limit on CPU cores to be used by a container.</p>|Dependent item|kube.pod.containers.limits.cpu[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Containers memory limits|<p>The limit on memory to be used by a container.</p>|Dependent item|kube.pod.containers.limits.memory[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Containers CPU requests|<p>The number of requested CPU cores by a container.</p>|Dependent item|kube.pod.containers.requests.cpu[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Pod [{#NAME}]: Containers memory requests|<p>The number of requested memory bytes by a container.</p>|Dependent item|kube.pod.containers.requests.memory[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Pod discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes cluster state: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod is not healthy||`min(/Kubernetes cluster state by HTTP/kube.pod.phase.failed[{#NAMESPACE}/{#NAME}],10m)>0 or min(/Kubernetes cluster state by HTTP/kube.pod.phase.pending[{#NAMESPACE}/{#NAME}],10m)>0 or min(/Kubernetes cluster state by HTTP/kube.pod.phase.unknown[{#NAMESPACE}/{#NAME}],10m)>0`|High||
|Kubernetes cluster state: Namespace [{#NAMESPACE}] Pod [{#NAME}]: Pod is crash looping|<p>Containers of the pod keep restarting. This most likely indicates that the pod is in the CrashLoopBackOff state.</p>|`(last(/Kubernetes cluster state by HTTP/kube.pod.containers_restarts[{#NAMESPACE}/{#NAME}])-min(/Kubernetes cluster state by HTTP/kube.pod.containers_restarts[{#NAMESPACE}/{#NAME}],15m))>1`|Warning||

### LLD rule ReplicaSet discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ReplicaSet discovery||Dependent item|kube.replicaset.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_replicaset_status_replicas`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for ReplicaSet discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] ReplicaSet [{#NAME}]: Replicas|<p>The number of replicas per ReplicaSet.</p>|Dependent item|kube.replicaset.replicas[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] ReplicaSet [{#NAME}]: Desired replicas|<p>Number of desired pods for a ReplicaSet.</p>|Dependent item|kube.replicaset.replicas_desired[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] ReplicaSet [{#NAME}]: Fully labeled replicas|<p>The number of fully labeled replicas per ReplicaSet.</p>|Dependent item|kube.replicaset.fully_labeled_replicas[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] ReplicaSet [{#NAME}]: Ready|<p>The number of ready replicas per ReplicaSet.</p>|Dependent item|kube.replicaset.ready[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] ReplicaSet [{#NAME}]: Replicas mismatched|<p>The number of ready replicas not matching the desired number of replicas.</p>|Dependent item|kube.replicaset.replicas_mismatched[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for ReplicaSet discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes cluster state: Namespace [{#NAMESPACE}] RS [{#NAME}]: ReplicaSet mismatch|<p>ReplicaSet has not matched the expected number of replicas during the specified trigger evaluation period.</p>|`min(/Kubernetes cluster state by HTTP/kube.replicaset.replicas_mismatched[{#NAMESPACE}/{#NAME}],{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD:"replicaset:{#NAMESPACE}:{#NAME}"})>0 and last(/Kubernetes cluster state by HTTP/kube.replicaset.replicas_desired[{#NAMESPACE}/{#NAME}])>=0 and last(/Kubernetes cluster state by HTTP/kube.replicaset.ready[{#NAMESPACE}/{#NAME}])>=0`|Warning||

### LLD rule StatefulSet discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|StatefulSet discovery||Dependent item|kube.statefulset.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_statefulset_status_replicas`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for StatefulSet discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] StatefulSet [{#NAME}]: Replicas|<p>The number of replicas per StatefulSet.</p>|Dependent item|kube.statefulset.replicas[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] StatefulSet [{#NAME}]: Desired replicas|<p>Number of desired pods for a StatefulSet.</p>|Dependent item|kube.statefulset.replicas_desired[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] StatefulSet [{#NAME}]: Current replicas|<p>The number of current replicas per StatefulSet.</p>|Dependent item|kube.statefulset.replicas_current[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] StatefulSet [{#NAME}]: Ready replicas|<p>The number of ready replicas per StatefulSet.</p>|Dependent item|kube.statefulset.replicas_ready[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] StatefulSet [{#NAME}]: Updated replicas|<p>The number of updated replicas per StatefulSet.</p>|Dependent item|kube.statefulset.replicas_updated[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] StatefulSet [{#NAME}]: Replicas mismatched|<p>The number of ready replicas not matching the number of replicas.</p>|Dependent item|kube.statefulset.replicas_mismatched[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `The text is too long. Please see the template.`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for StatefulSet discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes cluster state: Namespace [{#NAMESPACE}] StatefulSet [{#NAME}]: StatefulSet is down||`(last(/Kubernetes cluster state by HTTP/kube.statefulset.replicas_ready[{#NAMESPACE}/{#NAME}]) / last(/Kubernetes cluster state by HTTP/kube.statefulset.replicas_current[{#NAMESPACE}/{#NAME}]))<>1`|High||
|Kubernetes cluster state: Namespace [{#NAMESPACE}] StatefulSet [{#NAME}]: StatefulSet replicas mismatch|<p>StatefulSet has not matched the number of replicas during the specified trigger evaluation period.</p>|`min(/Kubernetes cluster state by HTTP/kube.statefulset.replicas_mismatched[{#NAMESPACE}/{#NAME}],{$KUBE.REPLICA.MISMATCH.EVAL_PERIOD:"statefulset:{#NAMESPACE}:{#NAME}"})>0 and last(/Kubernetes cluster state by HTTP/kube.statefulset.replicas[{#NAMESPACE}/{#NAME}])>=0 and last(/Kubernetes cluster state by HTTP/kube.statefulset.replicas_ready[{#NAMESPACE}/{#NAME}])>=0`|Warning||

### LLD rule PodDisruptionBudget discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PodDisruptionBudget discovery||Dependent item|kube.pdb.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_poddisruptionbudget_created`</p></li></ul>|

### Item prototypes for PodDisruptionBudget discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] PodDisruptionBudget [{#NAME}]: Pods healthy|<p>Current number of healthy pods.</p>|Dependent item|kube.pdb.pods_healthy[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] PodDisruptionBudget [{#NAME}]: Pods desired|<p>Minimum desired number of healthy pods.</p>|Dependent item|kube.pdb.pods_desired[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] PodDisruptionBudget [{#NAME}]: Disruptions allowed|<p>Number of pod disruptions that are allowed.</p>|Dependent item|kube.pdb.disruptions_allowed[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] PodDisruptionBudget [{#NAME}]: Pods total|<p>Total number of pods counted by this disruption budget.</p>|Dependent item|kube.pdb.pods_total[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule CronJob discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CronJob discovery||Dependent item|kube.cronjob.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_cronjob_created`</p></li></ul>|

### Item prototypes for CronJob discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] CronJob [{#NAME}]: Suspend|<p>Suspend flag tells the controller to suspend subsequent executions.</p>|Dependent item|kube.cronjob.spec_suspend[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Namespace [{#NAMESPACE}] CronJob [{#NAME}]: Active|<p>Active holds pointers to currently running jobs.</p>|Dependent item|kube.cronjob.status_active[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] CronJob [{#NAME}]: Last schedule|<p>LastScheduleTime keeps information of when was the last time the job was successfully scheduled.</p>|Dependent item|kube.cronjob.last_schedule_time[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1`</p></li></ul>|
|Namespace [{#NAMESPACE}] CronJob [{#NAME}]: Next schedule|<p>Next time the cronjob should be scheduled. The time after lastScheduleTime or after the cron job's creation time if it's never been scheduled. Use this to determine if the job is delayed.</p>|Dependent item|kube.cronjob.next_schedule_time[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1`</p></li></ul>|
|Namespace [{#NAMESPACE}] CronJob [{#NAME}]: Failed|<p>The number of pods which reached the Failed phase and the reason for failure.</p>|Dependent item|kube.cronjob.status_failed[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] CronJob [{#NAME}]: Succeeded|<p>The number of pods which reached the Succeeded phase.</p>|Dependent item|kube.cronjob.status_succeeded[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] CronJob [{#NAME}]: Completion succeeded|<p>Number of jobs the execution of which has been completed.</p>|Dependent item|kube.cronjob.completion.succeeded[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] CronJob [{#NAME}]: Completion failed|<p>Number of jobs the execution of which has failed.</p>|Dependent item|kube.cronjob.completion.failed[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Job discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Job discovery||Dependent item|kube.job.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `kube_job_owner{owner_is_controller!="true"}`</p></li></ul>|

### Item prototypes for Job discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Job [{#NAME}]: Failed|<p>The number of pods which reached the Failed phase and the reason for failure.</p>|Dependent item|kube.job.status_failed[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Job [{#NAME}]: Succeeded|<p>The number of pods which reached the Succeeded phase.</p>|Dependent item|kube.job.status_succeeded[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Job [{#NAME}]: Completion succeeded|<p>Number of jobs the execution of which has been completed.</p>|Dependent item|kube.job.completion.succeeded[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Job [{#NAME}]: Completion failed|<p>Number of jobs the execution of which has failed.</p>|Dependent item|kube.job.completion.failed[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Component statuses discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Component statuses discovery||Dependent item|kube.componentstatuses.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Component statuses discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Component [{#NAME}]: Healthy|<p>Cluster component healthy.</p>|Dependent item|kube.componentstatuses.healthy[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Component statuses discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes cluster state: Component [{#NAME}] is unhealthy||`count(/Kubernetes cluster state by HTTP/kube.componentstatuses.healthy[{#NAME}],#2,"ne","True")=2 and length(last(/Kubernetes cluster state by HTTP/kube.componentstatuses.healthy[{#NAME}]))>0`|Warning||

### LLD rule Readyz discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Readyz discovery||Dependent item|kube.readyz.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Readyz discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Readyz [{#NAME}]: Healthcheck|<p>Result of readyz healthcheck for component.</p>|Dependent item|kube.readyz.healthcheck[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.name == "{#NAME}")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Readyz discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes cluster state: Readyz [{#NAME}] is unhealthy||`count(/Kubernetes cluster state by HTTP/kube.readyz.healthcheck[{#NAME}],#2,"ne","ok")=2 and length(last(/Kubernetes cluster state by HTTP/kube.readyz.healthcheck[{#NAME}]))>0`|Warning||

### LLD rule Livez discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Livez discovery||Dependent item|kube.livez.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Livez discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Livez [{#NAME}]: Healthcheck|<p>Result of livez healthcheck for component.</p>|Dependent item|kube.livez.healthcheck[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.name == "{#NAME}")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Livez discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes cluster state: Livez [{#NAME}] is unhealthy||`count(/Kubernetes cluster state by HTTP/kube.livez.healthcheck[{#NAME}],#2,"ne","ok")=2 and length(last(/Kubernetes cluster state by HTTP/kube.livez.healthcheck[{#NAME}]))>0`|Warning||

### LLD rule OpenShift BuildConfig discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift BuildConfig discovery||Dependent item|openshift.buildconfig.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `openshift_buildconfig_created`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for OpenShift BuildConfig discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] BuildConfig [{#NAME}]: Created|<p>OpenShift BuildConfig Unix creation timestamp.</p>|Dependent item|openshift.buildconfig.created.time[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Namespace [{#NAMESPACE}] BuildConfig [{#NAME}]: Generation|<p>Sequence number representing a specific generation of the desired state.</p>|Dependent item|openshift.buildconfig.generation[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] BuildConfig [{#NAME}]: Latest version|<p>The latest version of BuildConfig.</p>|Dependent item|openshift.buildconfig.status[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### LLD rule OpenShift Build discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift Build discovery||Dependent item|openshift.build.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `openshift_build_created`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for OpenShift Build discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Build [{#NAME}]: Created|<p>OpenShift Build Unix creation timestamp.</p>|Dependent item|openshift.build.created.time[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Namespace [{#NAMESPACE}] Build [{#NAME}]: Generation|<p>Sequence number representing a specific generation of the desired state.</p>|Dependent item|openshift.build.sequence.number[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Namespace [{#NAMESPACE}] Build [{#NAME}]: Status phase|<p>The Build phase.</p>|Dependent item|openshift.build.status_phase[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for OpenShift Build discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes cluster state: Build [{#NAME}]: Build has failed||`count(/Kubernetes cluster state by HTTP/openshift.build.status_phase[{#NAMESPACE}/{#NAME}],2m,"ge",6)>=2`|Warning||

### LLD rule OpenShift ClusterResourceQuota discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift ClusterResourceQuota discovery||Dependent item|openshift.cluster.resource.quota.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `openshift_clusterresourcequota_usage`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for OpenShift ClusterResourceQuota discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Quota [{#NAME}] Resource [{#RESOURCE}]: Type [{#TYPE}]]|<p>Usage about resource quota.</p>|Dependent item|openshift.cluster.resource.quota[{#RESOURCE}/{#NAME}/{#TYPE}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule OpenShift Route discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OpenShift Route discovery||Dependent item|openshift.route.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `openshift_route_info`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for OpenShift Route discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Namespace [{#NAMESPACE}] Route [{#NAME}]: Created|<p>OpenShift Route Unix creation timestamp.</p>|Dependent item|openshift.route.created.time[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Namespace [{#NAMESPACE}] Route [{#NAME}]: Status|<p>Information about route status.</p>|Dependent item|openshift.route.status[{#NAMESPACE}/{#NAME}]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `openshift_route_status{route="{#NAME}"} == 1` label `status`</p><p>⛔️Custom on fail: Discard value</p></li><li>Boolean to decimal</li></ul>|

### Trigger prototypes for OpenShift Route discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kubernetes cluster state: Route [{#NAME}] with issue: Status is false||`count(/Kubernetes cluster state by HTTP/openshift.route.status[{#NAMESPACE}/{#NAME}],2m,,0)>=2`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

