/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package docker

import (
	"strconv"
	"time"
)

type unixTime int64

// UnmarshalJSON is used to convert time to unixtime
func (tm *unixTime) UnmarshalJSON(b []byte) (err error) {
	s, err := strconv.Unquote(string(b))
	if err != nil {
		return nil
	}

	t, err := time.Parse(time.RFC3339, s)
	if err != nil {
		return nil
	}

	*tm = unixTime(t.Unix())

	return err
}

// ErrorMessage represents the API error
type ErrorMessage struct {
	Message string `json:"message"`
}

// Port is a type that represents a port mapping returned by the Docker API.
type Port struct {
	PrivatePort int64
	PublicPort  int64
	Type        string
	IP          string
}

// Mount represents a mount point for a container.
type Mount struct {
	Name        string
	Source      string
	Destination string
	Driver      string
	Mode        string
	RW          bool
	Propagation string
	Type        string
}

// ContainerNetwork represents the networking settings of a container per network.
type ContainerNetwork struct {
	Aliases             []string
	MacAddress          string
	GlobalIPv6PrefixLen int
	GlobalIPv6Address   string
	IPv6Gateway         string
	IPPrefixLen         int
	IPAddress           string
	Gateway             string
	EndpointID          string
	NetworkID           string
}

// Container represents each container in the list returned by ListContainers.
type Container struct {
	ID         string `json:"Id"`
	Image      string
	Command    string
	Created    int64
	State      string
	Status     string
	Ports      []Port
	SizeRw     int64
	SizeRootFs int64
	Names      []string
	Mounts     []Mount
}

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

// Info contains response of Engine API:
// GET "/info"
type Info struct {
	ID                 string `json:"Id"`
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
	CPUCfsPeriod       bool `json:"CpuCfsPeriod"`
	CPUCfsQuota        bool `json:"CpuCfsQuota"`
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

// RootFS represents the underlying layers used by an image
type RootFS struct {
	Type   string
	Layers []string
}

// Image contains response of Engine API:
// GET "/images/{name:.*}/json"
type Image struct {
	ID          string `json:"Id"`
	RepoTags    []string
	RepoDigests []string
	Containers  int64
	Created     int64
	Size        int64
	VirtualSize int64
	SharedSize  int64
}

// DiskUsage contains response of Engine API:
// GET "/system/df"
type DiskUsage struct {
	LayersSize int64
	Images     []*Image
	Containers []*Container
	Volumes    []*Volume
}

// Volume volume
type Volume struct {
	CreatedAt  unixTime
	Mountpoint string
	Name       string
	UsageData  *VolumeUsageData
}

// VolumeUsageData Usage details about the volume.
type VolumeUsageData struct {
	RefCount int64
	Size     int64
}

// ContainerInfo contains response of Engine API:
// GET "/containers/{name:.*}/json"
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

// HostConfig the non-portable Config structure of a container
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

// Resources contains container's resources
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

// ThrottlingData stores CPU throttling stats of one running container.
type ThrottlingData struct {
	Periods          uint64 `json:"periods"`
	ThrottledPeriods uint64 `json:"throttled_periods"`
	ThrottledTime    uint64 `json:"throttled_time"`
}

// CPUUsage stores all CPU stats aggregated since container inception.
type CPUUsage struct {
	TotalUsage        uint64   `json:"total_usage"`
	PercpuUsage       []uint64 `json:"percpu_usage"`
	PercentUsage      float64  `json:"percent_usage"`
	UsageInKernelmode uint64   `json:"usage_in_kernelmode"`
	UsageInUsermode   uint64   `json:"usage_in_usermode"`
}

// CPUStats aggregates and wraps all CPU related info of container
type CPUStats struct {
	CPUUsage       CPUUsage       `json:"cpu_usage"`
	SystemUsage    uint64         `json:"system_cpu_usage"`
	OnlineCPUs     uint32         `json:"online_cpus"`
	ThrottlingData ThrottlingData `json:"throttling_data"`
}

// MemoryStats aggregates all memory stats since container inception on Linux.
type MemoryStats struct {
	Usage             uint64            `json:"usage"`
	MaxUsage          uint64            `json:"max_usage"`
	Stats             map[string]uint64 `json:"stats"`
	Failcnt           uint64            `json:"failcnt"`
	Limit             uint64            `json:"limit"`
	Commit            uint64            `json:"commitbytes"`
	CommitPeak        uint64            `json:"commitpeakbytes"`
	PrivateWorkingSet uint64            `json:"privateworkingset"`
}

// NetworkStats aggregates the network stats of one container
type NetworkStats struct {
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

// Stats is struct aggregating all types of stats of one container
type Stats struct {
	CPUStats    CPUStats    `json:"cpu_stats"`
	PreCPUStats CPUStats    `json:"precpu_stats"`
	MemoryStats MemoryStats `json:"memory_stats"`
}

// ContainerStats struct
type ContainerStats struct {
	Stats
	Name     string                  `json:"name"`
	ID       string                  `json:"id"`
	Networks map[string]NetworkStats `json:"networks"`
}
