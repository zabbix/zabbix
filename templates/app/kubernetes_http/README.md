# User guide to setting up Zabbix monitoring of the Kubernetes

A template set for monitoring your Kubernetes cluster via Zabbix 6.0 and higher. Zabbix provides a powerful automated solution for monitoring the Kubernetes cluster components.
You need to deploy [Zabbix Helm Chart](https://git.zabbix.com/projects/ZT/repos/kubernetes-helm/browse?at=refs%2Fheads%2Frelease%2F6.0) with Zabbix Proxy and Zabbix agents to monitor the cluster.

## Pre-requisites and installation

Zabbix is an open-source product that can be installed on a majority of Unix-like distributions at no cost  - see [full list of supported distributions](https://www.zabbix.com/download). Alternatively, Zabbix is available on certain [cloud services](https://www.zabbix.com/cloud_images).

Templates for Kubernetes monitoring

| Name                                  | Readme                                                 | Template                                                                          |
|---------------------------------------|--------------------------------------------------------|-----------------------------------------------------------------------------------|
| Kubernetes nodes by HTTP              | [Readme](kubernetes_nodes_http/README.md)              | [Template](kubernetes_nodes_http/template_kube_nodes.yaml)                        |
| Kubernetes cluster state by HTTP      | [Readme](kubernetes_state_http/README.md)              | [Template](kubernetes_state_http/template_kube_state.yaml)                        |
| Kubernetes API server by HTTP         | [Readme](kubernetes_api_server_http/README.md)         | [Template](kubernetes_api_server_http/kubernetes_api_servers.yaml)                |
| Kubernetes Controller manager by HTTP | [Readme](kubernetes_controller_manager_http/README.md) | [Template](kubernetes_controller_manager_http/kubernetes_controller_manager.yaml) |
| Kubernetes Scheduler by HTTP          | [Readme](kubernetes_scheduler_http/README.md)          | [Template](kubernetes_scheduler_http/kubernetes_scheduler.yaml)                   |
| Kubernetes kubelet by HTTP            | [Readme](kubernetes_kubelet_http/README.md)            | [Template](kubernetes_kubelet_http/template_kube_kubelet.yaml)                    |

Templates are of two types:

1. Cluster node monitoring

    [Kubernetes nodes by HTTP](kubernetes_nodes_http) template discovers cluster nodes, creates hosts in Zabbix based on prototypes and assigns the "Linux by Zabbix agent" template to them. The template collects basic node metrics via the Kubernetes API.

2. Main cluster components monitoring

    [Kubernetes cluster state by HTTP](kubernetes_state_http) discovers cluster components and control plane nodes, creates Zabbix hosts and assigns the required templates to them.

## Zabbix set up

* Install Zabbix Helm chart according to the [instructions](https://git.zabbix.com/projects/ZT/repos/kubernetes-helm/browse?at=refs%2Fheads%2Frelease%2F6.0).
* Import all templates into Zabbix.
* Get the token automatically created when you install Zabbix Helm Chart.

```bash
kubectl get secret zabbix-service-account -n monitoring -o jsonpath={.data.token} | base64 -d
```

* Create a generic host for the nodes and assign to it the "Kubernetes nodes by HTTP" template.
* Set macros according to the [template instructions](kubernetes_nodes_http/README.md).
* Create a cluster state host and assign the "Kubernetes cluster state by HTTP" template to it.
* Specify a dummy host interface required for HTTP items.
* Set macros according to the [template instructions](kubernetes_state_http/README.md).

> It is strongly recommended to use filtering macros when configuring templates, as on a large cluster the number of discoverable objects can reduce the performance of the monitoring system.
