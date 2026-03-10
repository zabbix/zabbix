/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions.
type info struct {
	ID                 string   `json:"ID"`
	Containers         int      `json:"Containers"`
	ContainersRunning  int      `json:"ContainersRunning"`
	ContainersPaused   int      `json:"ContainersPaused"`
	ContainersStopped  int      `json:"ContainersStopped"`
	Images             int      `json:"Images"`
	Driver             string   `json:"Driver"`
	MemoryLimit        bool     `json:"MemoryLimit"`
	SwapLimit          bool     `json:"SwapLimit"`
	KernelMemory       bool     `json:"KernelMemory,omitempty"`
	KernelMemoryTCP    bool     `json:"KernelMemoryTCP,omitempty"`
	CPUCfsPeriod       bool     `json:"CpuCfsPeriod"`
	CPUCfsQuota        bool     `json:"CpuCfsQuota"`
	CPUShares          bool     `json:"CPUShares"`
	CPUSet             bool     `json:"CPUSet"`
	PidsLimit          bool     `json:"PidsLimit"`
	IPv4Forwarding     bool     `json:"IPv4Forwarding"`
	BridgeNfIptables   bool     `json:"BridgeNfIptables,omitempty"`
	BridgeNfIP6tables  bool     `json:"BridgeNfIP6tables,omitempty"`
	Debug              bool     `json:"Debug"`
	NFd                int      `json:"NFd"`
	OomKillDisable     bool     `json:"OomKillDisable"`
	NGoroutines        int      `json:"NGoroutines"`
	LoggingDriver      string   `json:"LoggingDriver"`
	CgroupDriver       string   `json:"CgroupDriver"`
	NEventsListener    int      `json:"NEventsListener"`
	KernelVersion      string   `json:"KernelVersion"`
	OperatingSystem    string   `json:"OperatingSystem"`
	OSVersion          string   `json:"OSVersion"`
	OSType             string   `json:"OSType"`
	Architecture       string   `json:"Architecture"`
	IndexServerAddress string   `json:"IndexServerAddress"`
	NCPU               int      `json:"NCPU"`
	MemTotal           int64    `json:"MemTotal"`
	DockerRootDir      string   `json:"DockerRootDir"`
	Name               string   `json:"Name"`
	ExperimentalBuild  bool     `json:"ExperimentalBuild"`
	ServerVersion      string   `json:"ServerVersion"`
	ClusterStore       string   `json:"ClusterStore,omitempty"`
	ClusterAdvertise   string   `json:"ClusterAdvertise,omitempty"`
	DefaultRuntime     string   `json:"DefaultRuntime"`
	LiveRestoreEnabled bool     `json:"LiveRestoreEnabled"`
	InitBinary         string   `json:"InitBinary"`
	SecurityOptions    []string `json:"SecurityOptions"`
	Warnings           []string `json:"Warnings"`
}

func keyInfoHandler(client *http.Client, query string, _ ...string) (string, error) {
	var data info

	body, err := queryDockerAPI(client, query)
	if err != nil {
		return "", err
	}

	err = json.Unmarshal(body, &data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	result, err := json.Marshal(data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return string(result), nil
}
