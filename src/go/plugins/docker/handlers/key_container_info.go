/*
** Copyright (C) 2001-2025 Zabbix SIA
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
	"errors"
	"net/http"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

// ContainerState represents the state of a container.
type ContainerState struct {
	Status            string
	Running           bool
	Paused            bool
	Restarting        bool
	OOMKilled         bool
	RemovalInProgress bool
	Dead              bool
	Pid               int
	ExitCode          int
	Error             string
	StartedAt         unixTime
	FinishedAt        unixTime
}

// ContainerInfo contains response of Engine API:
// GET "/containers/{name:.*}/json".
type ContainerInfo struct {
	ID              string `json:"Id"`
	Created         unixTime
	Path            string
	Args            []string
	State           *ContainerState
	Image           string
	ResolvConfPath  string
	HostnamePath    string
	HostsPath       string
	LogPath         string
	Name            string
	RestartCount    int
	Driver          string
	Platform        string
	MountLabel      string
	ProcessLabel    string
	AppArmorProfile string
	ExecIDs         []string
	HostConfig      *HostConfig
	SizeRw          int64
	SizeRootFs      int64
}

// HostConfig the non-portable Config structure of a container.
type HostConfig struct {
	Binds           []string
	ContainerIDFile string
	NetworkMode     string

	DNS             []string `json:"Dns"`
	DNSOptions      []string `json:"DnsOptions"`
	DNSSearch       []string `json:"DnsSearch"`
	ExtraHosts      []string
	GroupAdd        []string
	Links           []string
	OomScoreAdj     int
	Privileged      bool
	PublishAllPorts bool
	ReadonlyRootfs  bool
	SecurityOpt     []string
	StorageOpt      map[string]string
	Tmpfs           map[string]string
	ShmSize         int64
	Sysctls         map[string]string
	Runtime         string

	Resources
}

// Resources contains container's resources.
type Resources struct {
	CPUShares int64 `json:"CpuShares"`
	Memory    int64
	NanoCPUs  int64 `json:"NanoCpus"`

	CgroupParent       string
	BlkioWeight        uint16
	CPUPeriod          int64 `json:"CpuPeriod"`
	CPUQuota           int64 `json:"CpuQuota"`
	CPURealtimePeriod  int64 `json:"CpuRealtimePeriod"`
	CPURealtimeRuntime int64 `json:"CpuRealtimeRuntime"`
	CpusetCpus         string
	CpusetMems         string
	KernelMemory       int64
	KernelMemoryTCP    int64
	MemoryReservation  int64
	MemorySwap         int64
	MemorySwappiness   *int64
	OomKillDisable     *bool
	PidsLimit          *int64

	CPUCount           int64 `json:"CpuCount"`
	CPUPercent         int64 `json:"CpuPercent"`
	IOMaximumIOps      uint64
	IOMaximumBandwidth uint64
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
		var info ContainerInfo

		err = json.Unmarshal(body, &info)
		if err != nil {
			return "", errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
		}

		result, err := json.Marshal(info)
		if err != nil {
			return "", errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
		}

		return string(result), nil
	default:
		return "", errs.WrapConst(errors.New("info must be either 'full' or 'short'"), zbxerr.ErrorInvalidParams)
	}
}
