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
	"net/http"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

// info contains response of Engine API:
// GET "/info".
type info struct {
	ID                 string `json:"Id"` //nolint:tagliatelle
	Containers         int
	ContainersRunning  int
	ContainersPaused   int
	ContainersStopped  int
	Images             int
	Driver             string
	MemoryLimit        bool
	SwapLimit          bool
	KernelMemory       bool
	KernelMemoryTCP    bool
	CPUCfsPeriod       bool `json:"CpuCfsPeriod"` //nolint:tagliatelle
	CPUCfsQuota        bool `json:"CpuCfsQuota"`  //nolint:tagliatelle
	CPUShares          bool
	CPUSet             bool
	PidsLimit          bool
	IPv4Forwarding     bool
	BridgeNfIptables   bool
	BridgeNfIP6tables  bool
	Debug              bool
	NFd                int
	OomKillDisable     bool
	NGoroutines        int
	LoggingDriver      string
	CgroupDriver       string
	NEventsListener    int
	KernelVersion      string
	OperatingSystem    string
	OSVersion          string
	OSType             string
	Architecture       string
	IndexServerAddress string
	NCPU               int
	MemTotal           int64
	DockerRootDir      string
	Name               string
	ExperimentalBuild  bool
	ServerVersion      string
	ClusterStore       string
	ClusterAdvertise   string
	DefaultRuntime     string
	LiveRestoreEnabled bool
	InitBinary         string
	SecurityOptions    []string
	Warnings           []string
}

func keyInfoHandler(client *http.Client, query string, _ ...string) (string, error) {
	var data info

	body, err := queryDockerAPI(client, query)
	if err != nil {
		return "", err
	}

	if err = json.Unmarshal(body, &data); err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	result, err := json.Marshal(data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return string(result), nil
}
