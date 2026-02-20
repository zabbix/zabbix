/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package handlers

import (
	"encoding/json"
	"net/http"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

// containerStats struct.
type containerStats struct {
	stats

	Name     string                  `json:"name"`
	ID       string                  `json:"id"`
	Networks map[string]networkStats `json:"networks"`
}

// networkStats aggregates the network stats of one container.
type networkStats struct {
	RxBytes    uint64 `json:"rx_bytes"`
	RxPackets  uint64 `json:"rx_packets"`
	RxErrors   uint64 `json:"rx_errors"`
	RxDropped  uint64 `json:"rx_dropped"`
	TxBytes    uint64 `json:"tx_bytes"`
	TxPackets  uint64 `json:"tx_packets"`
	TxErrors   uint64 `json:"tx_errors"`
	TxDropped  uint64 `json:"tx_dropped"`
	EndpointID string `json:"endpoint_id"`
	InstanceID string `json:"instance_id"`
}

// stats is struct aggregating all types of stats of one container.
type stats struct {
	CPUStats    cpuStats    `json:"cpu_stats"`
	PreCPUStats cpuStats    `json:"precpu_stats"`
	MemoryStats memoryStats `json:"memory_stats"`
	PIDsStats   pidsStats   `json:"pids_stats"`
}

// cpuUsage stores all CPU stats aggregated since container inception.
type cpuUsage struct {
	TotalUsage        uint64   `json:"total_usage"`
	PercpuUsage       []uint64 `json:"percpu_usage"`
	PercentUsage      float64  `json:"percent_usage"`
	UsageInKernelmode uint64   `json:"usage_in_kernelmode"`
	UsageInUsermode   uint64   `json:"usage_in_usermode"`
}

// cpuStats aggregates and wraps all CPU related info of container.
type cpuStats struct {
	CPUUsage       cpuUsage       `json:"cpu_usage"`
	SystemUsage    uint64         `json:"system_cpu_usage"`
	OnlineCPUs     uint32         `json:"online_cpus"`
	ThrottlingData throttlingData `json:"throttling_data"`
}

// throttlingData stores CPU throttling stats of one running container.
type throttlingData struct {
	Periods          uint64 `json:"periods"`
	ThrottledPeriods uint64 `json:"throttled_periods"`
	ThrottledTime    uint64 `json:"throttled_time"`
}

// memoryStats aggregates all memory stats since container inception on Linux.
type memoryStats struct {
	Usage             uint64            `json:"usage"`
	MaxUsage          uint64            `json:"max_usage"`
	Stats             map[string]uint64 `json:"stats"`
	Failcnt           uint64            `json:"failcnt"`
	Limit             uint64            `json:"limit"`
	Commit            uint64            `json:"commitbytes"`
	CommitPeak        uint64            `json:"commitpeakbytes"`
	PrivateWorkingSet uint64            `json:"privateworkingset"`
}

// pidsStats holds pid information.
type pidsStats struct {
	Current uint64 `json:"current"`
}

func keyContainerStatsHandler(client *http.Client, query string, _ ...string) (string, error) {
	var data containerStats

	body, err := queryDockerAPI(client, query)
	if err != nil {
		return "", err
	}

	err = json.Unmarshal(body, &data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	data.setCPUPercentUsage()

	result, err := json.Marshal(data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return string(result), nil
}

func (s *stats) setCPUPercentUsage() {
	// based on formula from docker api doc.
	delta := s.CPUStats.CPUUsage.TotalUsage - s.PreCPUStats.CPUUsage.TotalUsage
	systemDelta := s.CPUStats.SystemUsage - s.PreCPUStats.SystemUsage
	cpuNum := s.CPUStats.OnlineCPUs
	s.CPUStats.CPUUsage.PercentUsage = (float64(delta) / float64(systemDelta)) * float64(cpuNum) * 100.0
}
