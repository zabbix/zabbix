# QNAP NAS SNMP LLD Template for Zabbix

**Version:** 1.0.0  
**Compatibility:** Zabbix 7.4+ (tested on 8.x)  
**Author:** Majid Abdollahi  

---

## Overview

This template provides **SNMP‑based monitoring** for QNAP NAS devices, including system‑level
metrics, predictive analytics, and LLD‑driven discovery for legacy QNAP models that still
expose storage OIDs. Version **1.0.0** is the **stable SNMP release**, consolidating all
features developed across versions 0.1.0 through 0.9.0.

> ⚠️ **Important Note**  
> Modern QNAP models running **QTS 5.x / QuTS hero** (including TS‑873AeU‑RP) expose only
> basic system metrics via SNMP. Storage‑level OIDs (disks, RAID, pools, volumes, SMART,
> NVMe, fans, PSU) are no longer available.  
>  
> The template remains fully functional for **legacy QNAP models** that support the full
> QNAP private MIB.

---

## Key Capabilities

### 🖥️ System Monitoring (All Models)
- CPU utilization  
- Memory usage  
- System uptime  
- Firmware version  
- SNMP agent availability  
- System identity and model information  

### 📦 Storage & Hardware Monitoring (Legacy Models Only)
- Automatic discovery of:
  - HDDs  
  - RAID groups  
  - Storage pools  
  - Volumes  
  - Fans  
  - Temperature sensors  
  - PSUs  
  - UPS units  
  - PCIe devices  
  - BBU modules  
  - NVMe devices  
  - SMART attributes  

- Metrics include:
  - Capacity, free space, utilization  
  - IOPS and throughput  
  - Disk temperature, wear‑leveling, remaining life  
  - RAID rebuild progress and ETA  
  - Pool fragmentation  
  - Volume latency and queue depth  

### 🧠 Predictive Analytics (Legacy Models)
- SMART anomaly detection  
- SMART volatility index  
- Disk lifetime forecasting  
- Disk degradation velocity  
- Disk failure‑probability scoring  
- Thermal stress index  
- Thermal fatigue index  
- RAID rebuild curve‑fit ETA  
- Volume performance anomaly detection  
- Volume stability index  

### 🔐 Security & System Health
- Failed login bursts  
- Intrusion detection  
- Unexpected reboot detection  
- Antivirus engine status and last scan  
- Service‑level monitoring (SMB, AFP, NFS, FTP, SSH)  

---

## Macros

| Macro                        | Default | Description                                      |
|------------------------------|---------|--------------------------------------------------|
| `{$CPU_WARN}`                | 80      | CPU warning threshold (%)                        |
| `{$CPU_HIGH}`                | 90      | CPU critical threshold (%)                       |
| `{$MEM_WARN}`                | 80      | Memory warning threshold (%)                     |
| `{$MEM_HIGH}`               | 90      | Memory critical threshold (%)                    |
| `{$TEMP_WARN}`               | 60      | Temperature warning threshold (°C)               |
| `{$TEMP_HIGH}`               | 70      | Temperature critical threshold (°C)              |
| `{$DISK_LIFE_WARN}`          | 20      | Remaining life warning threshold (%)             |
| `{$DISK_LIFE_CRIT}`          | 10      | Remaining life critical threshold (%)            |
| `{$TEMPLATE.VERSION}`        | 1.0.0   | Template version metadata                        |

---

## Discovery Rules (Legacy Models)

### ✔ Disk Discovery  
- SMART attributes  
- Temperature  
- Wear‑leveling  
- Remaining life  

### ✔ RAID Discovery  
- RAID state  
- Rebuild progress  
- Rebuild ETA  

### ✔ Pool Discovery  
- Capacity  
- Free space  
- Fragmentation  

### ✔ Volume Discovery  
- Latency  
- Queue depth  
- Utilization  

### ✔ NVMe Discovery  
- Endurance  
- Temperature  
- Remaining life  

### ✔ Fan & Temperature Sensor Discovery  
- Per‑sensor temperature  
- Fan RPM  
- Alarm states  

---

## Item Prototypes (Summary)

| Item Name                       | Key                                | Type        | Description                                   |
|--------------------------------|------------------------------------|-------------|-----------------------------------------------|
| CPU usage                      | `qnap.cpu.usage`                   | SNMP        | System CPU load                                |
| Memory usage                   | `qnap.mem.usage`                   | SNMP        | System memory load                             |
| System temperature             | `qnap.temp.system`                 | SNMP        | Chassis temperature                            |
| Disk temperature               | `qnap.disk.temp[{#DISK}]`          | SNMP        | Per‑disk temperature                           |
| Disk remaining life            | `qnap.disk.life[{#DISK}]`          | SNMP        | SSD/HDD remaining life (%)                     |
| SMART attributes               | `qnap.smart.*[{#DISK}]`            | SNMP        | SMART counters                                 |
| RAID state                     | `qnap.raid.state[{#RAID}]`         | SNMP        | RAID health                                    |
| RAID rebuild progress          | `qnap.raid.rebuild[{#RAID}]`       | SNMP        | Rebuild %                                      |
| Pool fragmentation             | `qnap.pool.frag[{#POOL}]`          | SNMP        | Fragmentation level                            |
| Volume latency                 | `qnap.vol.latency[{#VOL}]`         | SNMP        | Read/write latency                             |
| NVMe endurance                 | `qnap.nvme.endurance[{#NVME}]`     | SNMP        | Remaining endurance (%)                        |

---

## Trigger Prototypes (Summary)

| Trigger Name                                | Description                                   | Priority |
|---------------------------------------------|-----------------------------------------------|----------|
| CPU > warn/high                             | CPU utilization thresholds                    | WARNING / HIGH |
| Memory > warn/high                          | Memory utilization thresholds                 | WARNING / HIGH |
| System temperature high                     | Chassis temperature                           | HIGH     |
| Disk temperature high                       | Per‑disk temperature                          | WARNING / HIGH |
| Disk remaining life low                     | SSD/HDD wear‑out                              | WARNING / HIGH |
| SMART anomaly detected                      | Based on anomaly score                        | WARNING  |
| SMART volatility high                       | Attribute instability                         | WARNING  |
| Disk failure probability high               | Predictive failure model                      | HIGH     |
| RAID degraded                               | RAID state abnormal                           | HIGH     |
| RAID rebuild slow                           | Rebuild speed anomaly                         | WARNING  |
| Pool fragmentation high                     | Fragmentation threshold                       | WARNING  |
| Volume latency high                         | Performance degradation                       | WARNING  |
| NVMe endurance low                          | NVMe wear‑out                                 | HIGH     |

---

## Tags

- **component:** cpu / memory / disk / raid / pool / volume / nvme / temp / fan / smart / security  
- **severity:** info / warning / high  
- **prediction:** anomaly / volatility / degradation / failure  
- **release:** stable  

---

## Features Summary

- Fully SNMP‑based  
- Predictive analytics (v0.6.0–v0.9.0)  
- Anomaly detection & volatility scoring  
- Stability and degradation metrics  
- SLA‑oriented performance indicators  
- Dashboard‑friendly tagging  
- Optimized history/trends  
- GitHub‑ready metadata and versioning  

---

## Usage Instructions

1. Import the template YAML (`QNAP_SNMP_LLD_v1.0.0.yaml`) into Zabbix.  
2. Assign the template to your QNAP NAS host.  
3. Adjust macros as needed.  
4. System‑level metrics will populate immediately.  
5. Storage‑level LLD will populate **only on legacy QNAP models** with full SNMP support.  

---

## Notes

- Modern QNAP models (QTS 5.x / QuTS hero) expose **limited SNMP data**.  
- Storage‑level monitoring requires legacy QNAP firmware with full SNMP MIB support.  
- Predictive analytics rely on SMART and storage OIDs; unavailable on newer models.  
- Version 1.0.0 finalizes the SNMP branch; future versions will use REST API.  

---

**End of README**
