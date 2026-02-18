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

// containerInfo contains response of Engine API:
// GET "/containers/{name:.*}/json".
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions
type containerInfo struct {
	ID              string          `json:"Id"`
	Created         unixTime        `json:"Created"`
	Path            string          `json:"Path"`
	Args            []string        `json:"Args"`
	State           *containerState `json:"State"`
	Image           string          `json:"Image"`
	ResolvConfPath  string          `json:"ResolvConfPath"`
	HostnamePath    string          `json:"HostnamePath"`
	HostsPath       string          `json:"HostsPath"`
	LogPath         string          `json:"LogPath"`
	Name            string          `json:"Name"`
	RestartCount    int             `json:"RestartCount"`
	Driver          string          `json:"Driver"`
	Platform        string          `json:"Platform"`
	MountLabel      string          `json:"MountLabel"`
	ProcessLabel    string          `json:"ProcessLabel"`
	AppArmorProfile string          `json:"AppArmorProfile"`
	ExecIDs         []string        `json:"ExecIDs"`
	HostConfig      *hostConfig     `json:"HostConfig"`
	SizeRw          int64           `json:"SizeRw"`
	SizeRootFs      int64           `json:"SizeRootFs"`
}

// containerState represents the state of a container.
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions
type containerState struct {
	Status            string   `json:"Status"`
	Running           bool     `json:"Running"`
	Paused            bool     `json:"Paused"`
	Restarting        bool     `json:"Restarting"`
	OOMKilled         bool     `json:"OOMKilled"`
	RemovalInProgress bool     `json:"RemovalInProgress"`
	Dead              bool     `json:"Dead"`
	Pid               int      `json:"Pid"`
	ExitCode          int      `json:"ExitCode"`
	Error             string   `json:"Error"`
	StartedAt         unixTime `json:"StartedAt"`
	FinishedAt        unixTime `json:"FinishedAt"`
}

// hostConfig the non-portable Config structure of a container.
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions
type hostConfig struct {
	Binds           []string `json:"Binds"`
	ContainerIDFile string   `json:"ContainerIDFile"`
	NetworkMode     string   `json:"NetworkMode"`

	DNS             []string          `json:"Dns"`
	DNSOptions      []string          `json:"DnsOptions"`
	DNSSearch       []string          `json:"DnsSearch"`
	ExtraHosts      []string          `json:"ExtraHosts"`
	GroupAdd        []string          `json:"GroupAdd"`
	Links           []string          `json:"Links"`
	OomScoreAdj     int               `json:"OomScoreAdj"`
	Privileged      bool              `json:"Privileged"`
	PublishAllPorts bool              `json:"PublishAllPorts"`
	ReadonlyRootfs  bool              `json:"ReadonlyRootfs"`
	SecurityOpt     []string          `json:"SecurityOpt"`
	StorageOpt      map[string]string `json:"StorageOpt"`
	Tmpfs           map[string]string `json:"Tmpfs"`
	ShmSize         int64             `json:"ShmSize"`
	Sysctls         map[string]string `json:"Sysctls"`
	Runtime         string            `json:"Runtime"`

	resources
}

// resources contains container's resources.
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions
type resources struct {
	CPUShares int64 `json:"CpuShares"`
	Memory    int64 `json:"Memory"`
	NanoCPUs  int64 `json:"NanoCpus"`

	CgroupParent       string `json:"CgroupParent"`
	BlkioWeight        uint16 `json:"BlkioWeight"`
	CPUPeriod          int64  `json:"CpuPeriod"`
	CPUQuota           int64  `json:"CpuQuota"`
	CPURealtimePeriod  int64  `json:"CpuRealtimePeriod"`
	CPURealtimeRuntime int64  `json:"CpuRealtimeRuntime"`
	CpusetCpus         string `json:"CpusetCpus"`
	CpusetMems         string `json:"CpusetMems"`
	KernelMemory       int64  `json:"KernelMemory"`
	KernelMemoryTCP    int64  `json:"KernelMemoryTCP"`
	MemoryReservation  int64  `json:"MemoryReservation"`
	MemorySwap         int64  `json:"MemorySwap"`
	MemorySwappiness   *int64 `json:"MemorySwappiness"`
	OomKillDisable     *bool  `json:"OomKillDisable"`
	PidsLimit          *int64 `json:"PidsLimit"`

	CPUCount           int64  `json:"CpuCount"`
	CPUPercent         int64  `json:"CpuPercent"`
	IOMaximumIOps      uint64 `json:"IOMaximumIOps"`
	IOMaximumBandwidth uint64 `json:"IOMaximumBandwidth"`
}

func keyContainerInfoHandler(client *http.Client, query string, args ...string) (string, error) {
	if len(args) != 1 {
		return "", errs.WrapConst(errs.New("info must be either 'full' or 'short'"), zbxerr.ErrorInvalidParams)
	}

	body, err := queryDockerAPI(client, query)
	if err != nil {
		return "", err
	}

	switch args[0] {
	case "full":
		return string(body), nil
	case "short":
		var cInfo containerInfo

		err = json.Unmarshal(body, &cInfo)
		if err != nil {
			return "", errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
		}

		result, err := json.Marshal(cInfo)
		if err != nil {
			return "", errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
		}

		return string(result), nil
	default:
		return "", errs.WrapConst(errs.New("info must be either 'full' or 'short'"), zbxerr.ErrorInvalidParams)
	}
}
