# 3Com 242x SNMP LLD Template for Zabbix

**Version:** 2.0.0  
**Compatibility:** Zabbix 7.4+ (tested on 8.x)  
**Author:** Majid Abdollahi  

---

## Overview

This template provides **enterprise‑grade LLD monitoring** for 3Com 242x switches, integrating 20 phases of enhancements including predictive analytics, anomaly detection, stability scoring, noise suppression, and GitHub‑ready metadata.

It is fully aligned with the **Zabbix 7.4 YAML schema**, uses native valuemap blocks, and has been validated on **Zabbix 8.x**.

---

## Key Capabilities

### 🔍 Interface Discovery & Metadata
- Full **LLD interface discovery** (items & triggers auto‑generated)
- Collects **VLAN, MTU, duplex, role, speed, metadata**
- Enhanced tagging for dashboards and filtering

### 📊 Performance & Reliability Metrics
- Traffic (bps), utilization (smoothed), broadcast/multicast
- Error/discard/CRC/collision counters
- Queue drop monitoring
- Latency estimation
- SLA compliance KPI
- Error/discard percentage KPI

### 🧠 Intelligent Analytics (Phases 11–17)
- **Interface Quality Score (IQS)**  
- **Interface Health Classification (IHC)**  
- **Interface Stability Index (ISI)**  
- **Noise Filter (NF)**  
- **Interface Behavior Prediction (IBP)**  
- **Anomaly Detection (AD)**  

### 🧭 Role Detection (Phase 13)
- Auto‑detects:
  - Access port  
  - Trunk port  
  - Uplink (100M / 1G / 10G)  
  - Server/high‑speed port  

### 🔕 Noise Reduction (Phase 15)
- Hysteresis‑based triggers  
- Noise suppression logic  
- Prevents alert storms and false positives  

### 🧱 GitHub‑Ready Structure (Phase 20)
- Template metadata block  
- Release tagging  
- Template version macros  
- Clean UUID alignment  
- Native valuemap definitions  

---

## Macros

| Macro                        | Default | Description                                           |
|------------------------------|---------|-------------------------------------------------------|
| `{$IF_UTIL_WARN}`            | 80      | Utilization warning threshold (%)                     |
| `{$IF_UTIL_HIGH}`            | 90      | Utilization critical threshold (%)                    |
| `{$IF_ERROR_RATE}`           | 10      | Sustained error threshold                             |
| `{$IF_DISCARD_RATE}`         | 10      | Discard rate threshold                                |
| `{$BROADCAST_STORM}`         | 1000    | Broadcast storm threshold                             |
| `{$IF_CRC_THRESHOLD}`        | 10      | CRC error spike threshold                             |
| `{$IF_COLLISION_THRESHOLD}`  | 5       | Collision anomaly threshold                           |
| `{$IF_ERROR_PCT_WARN}`       | 5       | Error/discard percentage warning threshold            |
| `{$IF_QUEUE_DROP_THRESHOLD}` | 10      | Queue drop anomaly threshold                          |
| `{$IF_LATENCY_WARN}`         | 50      | Latency warning threshold (ms)                        |
| `{$IF_SLA_MIN}`              | 95      | Minimum acceptable SLA (%)                            |
| `{$TEMPLATE.VERSION}`        | 2.0.0   | Template version metadata                             |
| `{$TEMPLATE.PHASES}`         | 20      | Number of completed phases                            |

---

## Discovery Rule

- **Name:** Interface discovery  
- **Type:** SNMP_AGENT  
- **Key:** `if.discovery`  
- **OID:** `discovery[{#IFINDEX},ifIndex,{#IFNAME},ifDescr]`  
- **Delay:** 1h  
- **Filter:** Excludes loopback, VLAN, and null interfaces  
- **Metadata:** `LLD Version: 18`  
- **Result:** Automatically creates item prototypes, trigger prototypes, and tags per interface  

---

## Item Prototypes (Summary)

| Item Name                       | Key                                | Type        | Description                                   |
|--------------------------------|------------------------------------|-------------|-----------------------------------------------|
| Description                    | `ifAlias[{#IFINDEX}]`              | SNMP        | Interface description                          |
| Oper/Admin status              | `ifOperStatus`, `ifAdminStatus`    | SNMP        | Up/Down state                                  |
| Speed                          | `ifSpeed[{#IFINDEX}]`              | SNMP        | Interface speed                                |
| Traffic (in/out)               | `ifInOctets`, `ifOutOctets`        | SNMP        | bps with preprocessing                         |
| Utilization (smoothed)         | `if.util.smoothed`                 | CALCULATED  | Smoothed utilization (%)                       |
| Errors (in/out)                | `ifInErrors`, `ifOutErrors`        | SNMP        | Errors per second                              |
| Discards (in/out)              | `ifInDiscards`, `ifOutDiscards`    | SNMP        | Discards per second                            |
| CRC errors                     | `ifInCrcErrors`                    | SNMP        | CRC error counter                              |
| Collisions                     | `ifCollisions`                     | SNMP        | Collision counter                              |
| Broadcast/Multicast counters   | `ifInBroadcastPkts`, `ifOutBroadcastPkts`, `ifInMulticastPkts`, `ifOutMulticastPkts` | SNMP | L2 anomaly detection |
| Queue drops                    | `ifQueueDrops`                     | SNMP        | Queue drop counter                             |
| Latency                        | `ifLatency`                        | CALCULATED  | Basic latency estimation                       |
| SLA compliance                 | `ifSLA`                            | CALCULATED  | SLA % based on errors/discards                 |
| Error/discard % KPI            | `ifErrorDiscardPct`                | CALCULATED  | Reliability KPI                                |
| Role (auto‑detected)           | `ifRoleAuto`                       | CALCULATED  | Uplink/access/trunk/server classification      |
| Interface Quality Score        | `ifQualityScore`                   | CALCULATED  | Composite reliability score                    |
| Interface Health State         | `ifHealthState`                    | CALCULATED  | Healthy / Warning / Critical                   |
| Stability Index                | `ifStabilityIndex`                 | CALCULATED  | Stability scoring (0–100)                      |
| Noise Filter                   | `ifNoiseFilter`                    | CALCULATED  | Noise suppression metric                       |
| Behavior Prediction Score      | `ifBehaviorPrediction`             | CALCULATED  | Predictive degradation score                   |
| Anomaly Score                  | `ifAnomalyScore`                   | CALCULATED  | Statistical anomaly detection                  |
| VLAN / MTU / Duplex            | `ifVlan`, `ifMtu`, `ifDuplex`      | SNMP        | Interface metadata                             |

---

## Trigger Prototypes (Summary)

| Trigger Name                                | Description                                   | Priority |
|---------------------------------------------|-----------------------------------------------|----------|
| Interface DOWN                              | Admin up but oper down                        | AVERAGE  |
| Utilization > warn/high                     | Smoothed utilization                           | WARNING / HIGH |
| Sustained error rate                        | Based on `{$IF_ERROR_RATE}`                   | WARNING  |
| High discard rate                           | Based on `{$IF_DISCARD_RATE}`                 | WARNING  |
| Duplex mismatch                             | Wrong duplex at high speed                    | WARNING  |
| Broadcast storm                             | Based on `{$BROADCAST_STORM}`                 | HIGH     |
| Multicast anomaly                           | Sudden multicast spike                        | WARNING  |
| CRC error spike                             | Based on `{$IF_CRC_THRESHOLD}`                | WARNING  |
| Collision anomaly                           | Based on `{$IF_COLLISION_THRESHOLD}`          | INFO     |
| Error/discard % > threshold                 | KPI‑based reliability alert                   | WARNING  |
| Queue drop anomaly                          | Based on `{$IF_QUEUE_DROP_THRESHOLD}`         | WARNING  |
| Latency > threshold                         | Based on `{$IF_LATENCY_WARN}`                 | WARNING  |
| SLA < minimum                               | Based on `{$IF_SLA_MIN}`                      | HIGH     |
| Role changed unexpectedly                   | Auto‑detected role mismatch                   | INFO     |
| Stability Index < 60% / < 30%               | Degradation alerts                            | WARNING / HIGH |
| Noise level high (suppression)              | Suppresses noisy interfaces                   | INFO     |
| Predictive degradation (IBP < 60%)          | Forecasted reliability issue                  | WARNING  |
| Anomaly detected (AD > threshold)           | Behavioral anomaly                            | WARNING  |

---

## Tags

- **interface:** `{#IFNAME}`
- **direction:** in / out
- **component:** metadata / status / performance / reliability / kpi / prediction / anomaly / stability / noise
- **role:** access / trunk / uplink / server
- **release:** github-ready

---

## Features Summary

- Fully **LLD** (no manual per‑port configuration)
- Predictive analytics (IBP)
- Anomaly detection (AD)
- Stability scoring (ISI)
- Noise suppression (NF)
- Multi‑tier role detection
- SLA, latency, and KPI‑based reliability metrics
- Dashboard‑friendly tagging
- Optimized history/trends for performance
- Native Zabbix 7.4 valuemap support
- GitHub‑ready metadata and versioning

---

## Usage Instructions

1. Import the latest template YAML (`3Com_242x_SNMP_LLD_Base_v2.0.0.yaml`) into Zabbix.  
2. Assign the template to any 3Com 242x switch host.  
3. Adjust macros as needed for your environment.  
4. Interfaces will be discovered automatically.  
5. Use tags for dashboards, filtering, and alert routing.  

---

## Notes

- Template is fully LLD and scales to any number of ports.  
- Predictive and anomaly‑based triggers reduce false positives.  
- Stability and noise scoring improve alert quality.  
- All items and triggers are auto‑generated per interface.  
- Fully validated for Zabbix 7.4 and tested on 8.x.  

---

**End of README**
