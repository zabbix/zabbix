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
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"

	"golang.org/x/sys/unix"
	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
)

type ifConfigData struct {
	Name      string `json:"name"`
	Alias     string `json:"ifalias"`
	Mac       string `json:"mac"`
	Type      string `json:"type"`
	Speed     uint64 `json:"speed"`
	Duplex    string `json:"duplex"`
	AdmState  string `json:"administrative_state"`
	OperState string `json:"operational_state"`
	Carrier   uint64 `json:"carrier"`
}

type ifStatsIn struct {
	Bytes      uint64 `json:"bytes"`
	Packets    uint64 `json:"packets"`
	Err        uint64 `json:"errors"`
	Drop       uint64 `json:"dropped"`
	Fifo       uint64 `json:"overruns"`
	Frame      uint64 `json:"frame"`
	Compressed uint64 `json:"compressed"`
	Multicast  uint64 `json:"multicast"`
}
type ifStatsOut struct {
	Bytes      uint64 `json:"bytes"`
	Packets    uint64 `json:"packets"`
	Err        uint64 `json:"errors"`
	Drop       uint64 `json:"dropped"`
	Fifo       uint64 `json:"overruns"`
	Colls      uint64 `json:"collisions"`
	Carrier    uint64 `json:"carrier"`
	Compressed uint64 `json:"compressed"`
}

type ifValuesData struct {
	Name           string `json:"name"`
	Alias          string `json:"ifalias"`
	Mac            string `json:"mac"`
	Carrier        uint64 `json:"carrier"`
	CarrierChanges uint64 `json:"carrier_changes"`
	CarrierUpCnt   uint64 `json:"carrier_up_count"`
	CarrierDnCnt   uint64 `json:"carrier_down_count"`

	StatsIn  ifStatsIn  `json:"in"`
	StatsOut ifStatsOut `json:"out"`
}

type netIfResult struct {
	Config []ifConfigData `json:"config"`
	Values []ifValuesData `json:"values"`
}

func (p *Plugin) sysClassNetStrGet(ifName, filename string) string {
	path := filepath.Join(p.sysClassNetDirpath, ifName, filename)

	// G304: path is composed of a hardcoded base dir, kernel-supplied interface name, and hardcoded filename;
	// no user input involved.
	//nolint:gosec
	data, err := os.ReadFile(path)
	if err != nil {
		return ""
	}

	return strings.TrimSpace(string(data))
}

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

	flags, err := strconv.ParseUint(s, 0, 64)
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
	result.Values = make([]ifValuesData, 0)

	for _, line := range data {
		ifName, stats, err := p.ifRowScan(line)
		if err != nil {
			/* should never happen */
			return nil, err
		}

		if rgx != nil && !rgx.MatchString(ifName) {
			continue
		}

		conf, val := p.getInterfaceMetrics(ifName, stats)
		result.Config = append(result.Config, *conf)
		result.Values = append(result.Values, *val)
	}

	return &result, nil
}

// returns single interface config and values.
func (p *Plugin) getInterfaceMetrics(ifName string, stats []uint64) (*ifConfigData, *ifValuesData) {
	alias := p.sysClassNetStrGet(ifName, "ifalias")
	mac := p.sysClassNetStrGet(ifName, "address")
	carrier := p.sysClassNetUintGet(ifName, "carrier")

	config := ifConfigData{
		Name:      ifName,
		Alias:     alias,
		Mac:       mac,
		Type:      p.ifTypeGet(ifName),
		Speed:     p.sysClassNetUintGet(ifName, "speed"),
		Duplex:    p.sysClassNetStrGet(ifName, "duplex"),
		AdmState:  p.ifAdminStateGet(ifName),
		OperState: p.sysClassNetStrGet(ifName, "operstate"),
		Carrier:   carrier,
	}

	values := ifValuesData{
		Name:           ifName,
		Alias:          alias,
		Mac:            mac,
		Carrier:        carrier,
		CarrierChanges: p.sysClassNetUintGet(ifName, "carrier_changes"),
		CarrierUpCnt:   p.sysClassNetUintGet(ifName, "carrier_up_count"),
		CarrierDnCnt:   p.sysClassNetUintGet(ifName, "carrier_down_count"),
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
			Fifo:       stats[12],
			Colls:      stats[13],
			Carrier:    stats[14],
			Compressed: stats[15],
		},
	}

	return &config, &values
}
