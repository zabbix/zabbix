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

package netif

import (
	"encoding/json"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"

	"golang.org/x/sys/unix"
	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	errorCannotFindIf  = "Cannot find information for this network interface in /proc/net/dev."
	netDevFilepath     = "/proc/net/dev"
	netDevStatsCount   = 16
	sysClassNetDirpath = "/sys/class/net/"

	siocGiwStats = 0x8B0F
	siocGiwRange = 0x8B0B
	siocGiwEssid = 0x8B1B
	siocGiwRate  = 0x8B20
	siocEthtool  = 0x8946
	ethtoolGset  = 0x00000001

	ifNamSiz   = 16
	iwEssidMax = 32
	iwQualDbm  = 0x10
)

type ethtoolCmd struct {
	Cmd           uint32
	Supported     uint32
	Advertising   uint32
	Speed         uint16
	Duplex        uint8
	Port          uint8
	PhyAddress    uint8
	Transceiver   uint8
	Autoneg       uint8
	MdioSupport   uint32
	Maxtxpkt      uint32
	Maxrxpkt      uint32
	SpeedHi       uint16
	EthTpMdixCtrl uint8
	EthTpMdix     uint8
	Reserved2     [2]uint32
}

type iwQual struct {
	Qual    uint8
	Level   uint8
	Noise   uint8
	Updated uint8
}

type iwMiss struct {
	Beacon uint32
}

type iwStats struct {
	Status  uint16
	Qual    iwQual
	_       [2]byte
	Discard [5]uint32
	Miss    iwMiss
}

type iwRange struct {
	_       [20]byte
	MaxQual iwQual
	_       [180]byte
}

type iwParam struct {
	Value    int32
	Fixed    uint8
	Disabled uint8
	Flags    uint16
}

type iwPoint struct {
	Pointer uintptr
	Length  uint16
	Flags   uint16
	_       uint16
}

type iwreq struct {
	Name [ifNamSiz]byte
	Data iwPoint
}

type ifreqEthtool struct {
	Name [ifNamSiz]byte
	Data uintptr
}

type wirelessData struct {
	level   *int64
	noise   *int64
	qual    *int64
	ssid    *string
	bitrate *int64
}

func init() { //nolint:gochecknoinits // legacy implementation
	impl := &Plugin{}

	err := plugin.RegisterMetrics(
		impl, "NetIf",
		"net.if.collisions", "Returns number of out-of-window collisions.",
		"net.if.in", "Returns incoming traffic statistics on network interface.",
		"net.if.out", "Returns outgoing traffic statistics on network interface.",
		"net.if.total", "Returns sum of incoming and outgoing traffic statistics on network interface.",
		"net.if.discovery", "Returns list of network interfaces. Used for low-level discovery.",
		"net.if.get", "Returns list of network interfaces with parameters.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	impl.netDevFilepath = netDevFilepath
	impl.netDevStatsCount = netDevStatsCount
	impl.sysClassNetDirpath = sysClassNetDirpath
}

// Export implements plugin.Exporter interface.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (any, error) {
	switch key {
	case "net.if.discovery":
		return p.exportDiscovery(params)

	case "net.if.get":
		return p.exportGet(params)

	case "net.if.collisions":
		err := validateParams(params, 1, 1)
		if err != nil {
			return nil, err
		}

		return p.getNetStats(params[0], "collisions", directionOut)

	case "net.if.in":
		return p.handleNetIfMetric(params, directionIn)

	case "net.if.out":
		return p.handleNetIfMetric(params, directionOut)

	case "net.if.total":
		return p.handleNetIfMetric(params, directionTotal)
	default:
		/* SHOULD_NEVER_HAPPEN */
		return nil, errs.New(errorUnsupportedMetric)
	}
}

func (p *Plugin) exportDiscovery(params []string) (any, error) {
	if len(params) > 0 {
		return nil, errs.New(errorParametersNotAllowed)
	}

	devices, err := p.getDevDiscovery()
	if err != nil {
		return nil, err
	}

	b, err := json.Marshal(devices)
	if err != nil {
		return nil, errs.Wrap(err, "failed to marshal devices")
	}

	return string(b), nil
}

func (p *Plugin) exportGet(params []string) (any, error) {
	var err error
	var rgx *regexp.Regexp

	if len(params) > 1 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	if len(params) > 0 && params[0] != "" {
		rgx, err = regexp.Compile(params[0])
		if err != nil {
			return nil, errs.Wrapf(err, "invalid regular expression %q", params[0])
		}
	}

	devices, err := p.getIfGet(rgx)
	if err != nil {
		return nil, err
	}

	b, err := json.Marshal(devices)
	if err != nil {
		return nil, errs.Wrap(err, "failed to marshal devices")
	}

	return string(b), nil
}

func (p *Plugin) handleNetIfMetric(
	params []string, direction networkDirection,
) (any, error) {
	err := validateParams(params, 1, 2)
	if err != nil {
		return nil, err
	}

	mode := "bytes"
	if len(params) == 2 && params[1] != "" {
		mode = params[1]
	}

	return p.getNetStats(params[0], mode, direction)
}

func validateParams(params []string, minParams, maxParams int) error {
	if len(params) < minParams {
		return errs.New(errorEmptyIfName)
	}

	if len(params) > maxParams {
		return zbxerr.ErrorTooManyParameters
	}

	if params[0] == "" {
		return errs.New(errorEmptyIfName)
	}

	return nil
}

// sysClassNetPathSanitize sanitizes /sys/class/net/<interface>/ directory path
func (p *Plugin) sysClassNetPathSanitize(ifName string) error {
	dirPath, err := filepath.Abs(filepath.Join(p.sysClassNetDirpath, ifName))
	if err != nil || !strings.HasPrefix(dirPath, p.sysClassNetDirpath) {
		/* should never happen */
		return errs.Errorf("path traversal was prevented, network interface %q, absolute dir path = %q",
			ifName,
			dirPath,
		)
	}

	return nil
}

// sysClassNetStrGet reads sysfs parameters.
func (p *Plugin) sysClassNetStrGet(ifName, filename string) string {
	path := filepath.Join(p.sysClassNetDirpath, ifName, filename)

	data, err := os.ReadFile(path)
	if err != nil {
		return ""
	}

	return strings.TrimSpace(string(data))
}

// sysClassNetUintGet reads sysfs parameters.
func (p *Plugin) sysClassNetUintGet(ifName, filename string) uint64 {
	s := p.sysClassNetStrGet(ifName, filename)
	if s == "" {
		return 0
	}

	val, err := strconv.ParseUint(s, 10, 64)
	if err != nil {
		return 0
	}

	return val
}

// ifTypeGet returns the network interface type for the given interface name.
//
// The type is determined as follows:
//   - "loopback" if /sys/class/net/<name>/type contains the ARPHRD_LOOPBACK value
//   - "physical" if /sys/class/net/<name>/device symlink is present and
//     /sys/class/net/<name>/device/virtfn* symlinks are not present
//   - "virtual" otherwise
//
// Presence of /sys/class/net/<name>/device/virtfn* symlinks indicates that
// <name> is a Single Root Input/Output Virtualization (SR-IOV) virtual interface.
func (p *Plugin) ifTypeGet(ifName string) string {
	const (
		loopback = "loopback"
		virtual  = "virtual"
		physical = "physical"
	)

	if p.sysClassNetUintGet(ifName, "type") == unix.ARPHRD_LOOPBACK {
		return loopback
	}

	devPath := filepath.Join(p.sysClassNetDirpath, ifName, "device")

	_, err := os.Lstat(devPath)
	if err != nil {
		return virtual
	}

	entries, err := os.ReadDir(devPath)
	if err != nil {
		return virtual
	}

	for _, entry := range entries {
		if strings.HasPrefix(entry.Name(), "virtfn") {
			return virtual
		}
	}

	return physical
}

// ifAdminStateGet returns the administrative state of the interface.
func (p *Plugin) ifAdminStateGet(ifName string) string {
	const (
		unknown = "unknown"
		up      = "up"
		down    = "down"
	)

	s := p.sysClassNetStrGet(ifName, "flags")
	if s == "" {
		return unknown
	}

	flags, err := strconv.ParseUint(s, 16, 64)
	if err != nil {
		/* should never happen */
		return unknown
	}

	if (flags & unix.IFF_UP) != 0 {
		return up
	}

	return down
}

// ifRowScan scans one line from /proc/net/dev representing network interface.
func (p *Plugin) ifRowScan(line string) (string, []uint64, error) {
	dev := strings.Split(line, ":")

	/* should never happen */
	if len(dev) == 1 {
		return "", nil, errs.Errorf(
			"cannot read interface name from of \"%s\"",
			p.netDevFilepath,
		)

	}

	name := strings.TrimSpace(dev[0])
	stats := strings.Fields(dev[1])

	/* should never happen */
	if len(stats) != p.netDevStatsCount {
		return name, nil, errs.Errorf(
			"unexpected number of %d values read from \"%s\" for interface \"%s\"",
			len(stats),
			p.netDevFilepath,
			name,
		)
	}

	ui64 := make([]uint64, 0, len(stats)-1)

	for i, s := range stats {
		n, err := strconv.ParseUint(s, 10, 64)
		if err != nil {
			/* should never happen */
			return name, nil, errs.Errorf(
				"could convert to integer just %d values from \"%s\" for interface \"%s\"",
				i,
				p.netDevFilepath,
				name,
			)
		}
		ui64 = append(ui64, n)
	}

	return name, ui64, nil
}

// getIfGet retrieves interface data.
func (p *Plugin) getIfGet(rgx *regexp.Regexp) (*netIfResult, error) {
	var result netIfResult

	parser := procfs.NewParser().
		SetScanStrategy(procfs.StrategyOSReadFile).
		SetMatchMode(procfs.ModeContains).
		SetPattern(":")

	data, err := parser.Parse(p.netDevFilepath)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to parse file \"%s\"", p.netDevFilepath)
	}

	result.Config = make([]ifConfigData, 0)
	result.Values = make([]IfValuesData, 0)

	for _, line := range data {
		ifName, stats, err := p.ifRowScan(line)
		if err != nil {
			return nil, errs.Wrapf(err, "failed to parse file \"%s\"", p.netDevFilepath)
		}

		if rgx != nil && !rgx.MatchString(ifName) {
			continue
		}

		err = p.sysClassNetPathSanitize(ifName)
		if err != nil {
			/* should never happen */
			return nil, errs.Wrapf(err, "failed to parse file \"%s\"", p.netDevFilepath)
		}

		conf, val := p.getInterfaceMetrics(ifName, stats)
		result.Config = append(result.Config, *conf)
		result.Values = append(result.Values, *val)
	}

	return &result, nil
}

// returns single interface config and values.
func (p *Plugin) getInterfaceMetrics(ifName string, stats []uint64) (*ifConfigData, *IfValuesData) {
	alias := p.sysClassNetStrGet(ifName, "ifalias")
	mac := p.sysClassNetStrGet(ifName, "address")
	carrier:=p.sysClassNetUintGet(ifName, "carrier")

	config := ifConfigData{
		Name:      ifName,
		Alias:     alias,
		Mac:       mac,
		Type:      p.ifTypeGet(ifName),
		Speed:     p.sysClassNetUintGet(ifName, "speed"),
		Duplex:    p.sysClassNetStrGet(ifName, "duplex"),
		AdmState:  p.ifAdminStateGet(ifName),
		OperState: p.sysClassNetStrGet(ifName, "operational_state"),
		Carrier:   carrier,
	}

	values := IfValuesData{
		Ifname:    ifName,
		Ifalias:   alias,
		Ifmac:     mac,
		Ifcarrier: carrier,
		StatsIn: ifStatsIn{
			Bytes:      stats[0],
			Packets:    stats[1],
			Err:        stats[2],
			Drop:       stats[3],
			Fifo:       stats[4],
			Frame:      stats[5],
			Compressed: stats[6],
			Multicast:  stats[7],
		},
		StatsOut: ifStatsOut{
			Bytes:      stats[8],
			Packets:    stats[9],
			Err:        stats[10],
			Drop:       stats[11],
			Colls:      stats[12],
			Fifo:       stats[13],
			Carrier:    stats[14],
			Compressed: stats[15],
		},
	}

	return &config, &values
}
