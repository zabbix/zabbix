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
	"bufio"
	"encoding/json"
	"fmt"
	"net"
	"os"
	"regexp"
	"strconv"
	"strings"
	"syscall"
	"unsafe"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

const (
	errorCannotFindIf = "Cannot find information for this network interface in /proc/net/dev."
	netDevFilepath    = "/proc/net/dev"

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
}

// Export implements plugin.Exporter interface.
//
//nolint:cyclop // export function delegates its requests, so high cyclo is expected.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (any, error) {
	switch key {
	case "net.if.discovery":
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

	case "net.if.get":
		if len(params) > 1 {
			return nil, errs.New(errorTooManyParams)
		}

		expression := ""
		if len(params) > 0 {
			expression = params[0]
		}

		devices, err := p.getIfGet(expression)
		if err != nil {
			return nil, err
		}

		b, err := json.Marshal(devices)
		if err != nil {
			return nil, errs.Wrap(err, "failed to marshal devices")
		}

		return string(b), nil

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
		return errs.New(errorTooManyParams)
	}

	if params[0] == "" {
		return errs.New(errorEmptyIfName)
	}

	return nil
}

// fillNetIfGetParams reads sysfs parameters.
func (*Plugin) fillNetIfGetParams(ifName, param string) string {
	path := fmt.Sprintf("/sys/class/net/%s/%s", ifName, param)

	data, err := os.ReadFile(path)
	if err != nil {
		return ""
	}

	return strings.TrimSpace(string(data))
}

// parseUintPointer attempts to parse a string into a uint64.
func parseUintPointer(s string) *uint64 {
	val, err := strconv.ParseUint(s, 10, 64)
	if err != nil {
		return nil
	}

	return &val
}

// getIfGet retrieves interface data.
func (p *Plugin) getIfGet(regexExpr string) (netIfResult, error) {
	var (
		compiledRegex *regexp.Regexp
		result        netIfResult
	)

	f, err := os.Open(p.netDevFilepath)
	if err != nil {
		return result, fmt.Errorf("cannot open %s: %w", p.netDevFilepath, err)
	}

	//nolint:errcheck
	defer f.Close()

	if regexExpr != "" {
		compiledRegex, err = regexp.Compile(regexExpr)
		if err != nil {
			return result, fmt.Errorf("invalid regex expression '%s': %w", regexExpr, err)
		}
	}

	result.Config = make([]ifConfigData, 0)
	result.Values = make([]IfValuesData, 0)

	for sLines := bufio.NewScanner(f); sLines.Scan(); {
		line := sLines.Text()
		if !strings.Contains(line, ":") {
			continue
		}

		dev := strings.Split(line, ":")
		ifName := strings.TrimSpace(dev[0])

		if len(dev) <= 1 {
			continue
		}

		if compiledRegex != nil {
			if !compiledRegex.MatchString(ifName) {
				continue
			}
		}

		stats := strings.Fields(dev[1])
		if len(stats) < 16 {
			continue
		}

		conf, val := p.getInterfaceMetrics(ifName, stats)
		result.Config = append(result.Config, conf)
		result.Values = append(result.Values, val)
	}

	return result, nil
}

// returns single interface config and values.
func (p *Plugin) getInterfaceMetrics(ifName string, stats []string) (ifConfigData, IfValuesData) {
	var admState, operState *string

	iface, err := net.InterfaceByName(ifName)
	if err == nil {
		adm := "down"
		if iface.Flags&net.FlagUp != 0 {
			adm = "up"
		}

		admState = &adm

		oper := "down"
		if iface.Flags&net.FlagRunning != 0 {
			oper = "up"
		}

		operState = &oper
	}

	wData := p.getWirelessDetails(ifName)

	autoneg := "off"
	if p.getAutoneg(ifName) {
		autoneg = "on"
	}

	speedPtr := parseUintPointer(p.fillNetIfGetParams(ifName, "speed"))
	typePtr := parseUintPointer(p.fillNetIfGetParams(ifName, "type"))
	carrierPtr := parseUintPointer(p.fillNetIfGetParams(ifName, "carrier"))

	config := ifConfigData{
		Ifname:      ifName,
		Ifalias:     p.fillNetIfGetParams(ifName, "ifalias"),
		Ifmac:       p.fillNetIfGetParams(ifName, "address"),
		IfOperState: operState,
		IfAdmState:  admState,
	}

	values := IfValuesData{
		Ifname:  ifName,
		Ifalias: p.fillNetIfGetParams(ifName, "ifalias"),
		Ifmac:   p.fillNetIfGetParams(ifName, "address"),
		Iftype:  typePtr,

		In: IfStatistics{
			Ifbytes:      parseUintPointer(stats[0]),
			Ifpackets:    parseUintPointer(stats[1]),
			Iferrors:     parseUintPointer(stats[2]),
			Ifdropped:    parseUintPointer(stats[3]),
			Ifoverrruns:  parseUintPointer(stats[4]),
			Ifframe:      parseUintPointer(stats[5]),
			Ifcompressed: parseUintPointer(stats[6]),
			Ifmulticast:  parseUintPointer(stats[7]),
		},
		Out: IfStatistics{
			Ifbytes:      parseUintPointer(stats[8]),
			Ifpackets:    parseUintPointer(stats[9]),
			Iferrors:     parseUintPointer(stats[10]),
			Ifdropped:    parseUintPointer(stats[11]),
			Ifoverrruns:  parseUintPointer(stats[12]),
			Ifcollisions: parseUintPointer(stats[13]),
			Ifcarrier:    parseUintPointer(stats[14]),
			Ifcompressed: parseUintPointer(stats[15]),
		},
		Ifcarrier:     carrierPtr,
		Ifnegotiation: autoneg,
		Ifduplex:      p.fillNetIfGetParams(ifName, "duplex"),
		Ifspeed:       speedPtr,
		Ifslevel:      wData.level,
		Iflquality:    wData.qual,
		Ifnoiselevel:  wData.noise,
		Ifssid:        wData.ssid,
		Ifbitrate:     wData.bitrate,
	}

	return config, values
}

// retrieves interface wireless metrics using IOCTLs.
func (p *Plugin) getWirelessDetails(ifName string) *wirelessData {
	out := &wirelessData{}

	if len(ifName) >= ifNamSiz {
		return out
	}

	fd, err := syscall.Socket(syscall.AF_INET, syscall.SOCK_DGRAM, 0)
	if err != nil {
		return out
	}

	//nolint:errcheck
	defer syscall.Close(fd)

	out.level, out.noise, out.qual = p.getWirelessStatsAndRange(fd, ifName)
	out.ssid = p.getWirelessSSID(fd, ifName)
	out.bitrate = p.getWirelessBitrate(fd, ifName)

	return out
}

func (*Plugin) getWirelessStatsAndRange(fd int, ifName string) (*int64, *int64, *int64) {
	var (
		stats  iwStats
		wr     iwreq
		wrange iwRange
	)

	//nolint:gosec
	wr.Data = iwPoint{Pointer: uintptr(unsafe.Pointer(&stats)), Length: uint16(unsafe.Sizeof(stats)), Flags: 1}

	copy(wr.Name[:], ifName)

	//nolint:gosec
	_, _, errno := syscall.Syscall(
		syscall.SYS_IOCTL,
		uintptr(fd),
		siocGiwStats,
		uintptr(unsafe.Pointer(&wr)),
	)

	if errno != 0 {
		return nil, nil, nil
	}

	l := int64(stats.Qual.Level)
	n := int64(stats.Qual.Noise)

	if stats.Qual.Updated&iwQualDbm != 0 {
		l -= 256
		n -= 256
	}

	level := &l
	noise := &n

	//nolint:gosec
	wr.Data.Pointer = uintptr(unsafe.Pointer(&wrange))
	wr.Data.Length = uint16(unsafe.Sizeof(wrange))
	wr.Data.Flags = 0

	//nolint:gosec
	_, _, errnoRange := syscall.Syscall(
		syscall.SYS_IOCTL,
		uintptr(fd),
		siocGiwRange,
		uintptr(unsafe.Pointer(&wr)),
	)

	q := int64(stats.Qual.Qual)

	if errnoRange == 0 && wrange.MaxQual.Qual != 0 {
		q = (q * 100) / int64(wrange.MaxQual.Qual)
	}

	qual := &q

	return level, noise, qual
}

func (*Plugin) getWirelessSSID(fd int, ifName string) *string {
	var (
		buf [iwEssidMax + 1]byte
		wr  iwreq
	)

	copy(wr.Name[:], ifName)

	//nolint:gosec
	wr.Data.Pointer = uintptr(unsafe.Pointer(&buf[0]))
	wr.Data.Length = uint16(len(buf))
	wr.Data.Flags = 0

	//nolint:gosec
	_, _, errno := syscall.Syscall(
		syscall.SYS_IOCTL,
		uintptr(fd),
		siocGiwEssid,
		uintptr(unsafe.Pointer(&wr)),
	)

	if errno != 0 {
		return nil
	}

	length := int(wr.Data.Length)
	length = min(length, len(buf))

	if length > 0 {
		s := strings.TrimRight(string(buf[:length]), "\x00")
		if s != "" {
			return &s
		}
	}

	return nil
}

func (*Plugin) getWirelessBitrate(fd int, ifName string) *int64 {
	var (
		rate iwParam
		wr   iwreq
	)

	copy(wr.Name[:], ifName)

	//nolint:gosec
	wr.Data.Pointer = uintptr(unsafe.Pointer(&rate))
	wr.Data.Flags = 0

	//nolint:gosec
	_, _, errno := syscall.Syscall(
		syscall.SYS_IOCTL,
		uintptr(fd),
		siocGiwRate,
		uintptr(unsafe.Pointer(&wr)),
	)

	if errno != 0 {
		return nil
	}

	val := int64(rate.Value) / 1000000

	return &val
}

// retrieves autonegotiation using SIOCETHTOOL.
func (*Plugin) getAutoneg(ifName string) bool {
	if len(ifName) >= ifNamSiz {
		return false
	}

	fd, err := syscall.Socket(syscall.AF_INET, syscall.SOCK_DGRAM, 0)
	if err != nil {
		return false
	}

	//nolint:errcheck
	defer syscall.Close(fd)

	var ec ethtoolCmd

	ec.Cmd = ethtoolGset

	var ifr ifreqEthtool

	copy(ifr.Name[:], ifName)
	//nolint:gosec
	ifr.Data = uintptr(unsafe.Pointer(&ec))

	_, _, errno := syscall.Syscall(
		syscall.SYS_IOCTL,
		uintptr(fd),
		uintptr(siocEthtool),
		//nolint:gosec
		uintptr(unsafe.Pointer(&ifr)),
	)

	return errno == 0 && ec.Autoneg != 0
}
