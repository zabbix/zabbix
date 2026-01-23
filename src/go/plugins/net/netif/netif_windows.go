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
	"errors"
	"fmt"
	"net"
	"regexp"
	"strconv"
	"unsafe"

	"golang.org/x/sys/windows"
	"golang.zabbix.com/agent2/pkg/win32"
	"golang.zabbix.com/agent2/pkg/wmi"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

const (
	errorEmptyIpTable = "Empty IP address table returned."
	errorCannotFindIf = "Cannot obtain network interface information."
	guidStringLen     = 38
)

type wmiAdapterData struct {
	Name       string
	Speed      *uint64
	FullDuplex *bool
}

type wmiAdvancedData struct {
	Name         string
	DisplayValue string
}

func init() {
	impl := &Plugin{}

	err := plugin.RegisterMetrics(
		impl, "NetIf",
		"net.if.list", "Returns a list of network interfaces in text format.",
		"net.if.in", "Returns incoming traffic statistics on network interface.",
		"net.if.out", "Returns outgoing traffic statistics on network interface.",
		"net.if.total", "Returns sum of incoming and outgoing traffic statistics on network interface.",
		"net.if.discovery", "Returns list of network interfaces. Used for low-level discovery.",
		"net.if.get", "Returns list of network interfaces.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func (p *Plugin) nToIP(addr uint32) net.IP {
	b := (*[4]byte)(unsafe.Pointer(&addr))
	return net.IPv4(b[0], b[1], b[2], b[3])
}

func (p *Plugin) getIpAddrTable() (addrs []win32.MIB_IPADDRROW, err error) {
	var ipTable *win32.MIB_IPADDRTABLE
	var sizeIn, sizeOut uint32
	if sizeOut, err = win32.GetIpAddrTable(nil, 0, false); err != nil {
		return
	}
	if sizeOut == 0 {
		return
	}
	for sizeOut > sizeIn {
		sizeIn = sizeOut
		buf := make([]byte, sizeIn)
		ipTable = (*win32.MIB_IPADDRTABLE)(unsafe.Pointer(&buf[0]))
		if sizeOut, err = win32.GetIpAddrTable(ipTable, sizeIn, false); err != nil {
			return
		}
	}
	return (*win32.RGMIB_IPADDRROW)(unsafe.Pointer(&ipTable.Table[0]))[:ipTable.NumEntries:ipTable.NumEntries], nil
}

func (p *Plugin) getIfRowByIP(ipaddr string, ifs []win32.MIB_IF_ROW2) (row *win32.MIB_IF_ROW2) {
	var ip net.IP
	if ip = net.ParseIP(ipaddr); ip == nil {
		return
	}

	var err error
	var ips []win32.MIB_IPADDRROW
	if ips, err = p.getIpAddrTable(); err != nil {
		return
	}

	for i := range ips {
		if ip.Equal(p.nToIP(ips[i].Addr)) {
			for j := range ifs {
				if ifs[j].InterfaceIndex == ips[i].Index {
					return &ifs[j]
				}
			}
		}
	}
	return
}

func (p *Plugin) getGuidString(winGuid win32.GUID) string {
	return fmt.Sprintf(
		"{%08X-%04X-%04X-%02X-%02X}",
		winGuid.Data1,
		winGuid.Data2,
		winGuid.Data3,
		winGuid.Data4[:2],
		winGuid.Data4[2:],
	)
}

func (p *Plugin) getNetStats(networkIf, statName string, direction networkDirection) (result uint64, err error) {
	var ifTable *win32.MIB_IF_TABLE2
	if ifTable, err = win32.GetIfTable2(); err != nil {
		return
	}
	defer win32.FreeMibTable(ifTable)

	ifs := (*win32.RGMIB_IF_ROW2)(unsafe.Pointer(&ifTable.Table[0]))[:ifTable.NumEntries:ifTable.NumEntries]

	var row *win32.MIB_IF_ROW2
	for i := range ifs {
		if len(networkIf) == guidStringLen && networkIf[0] == '{' && networkIf[guidStringLen-1] == '}' &&
			networkIf == p.getGuidString(ifs[i].InterfaceGuid) {
			row = &ifs[i]
			break
		}
		if networkIf == windows.UTF16ToString(ifs[i].Description[:]) {
			row = &ifs[i]
			break
		}
	}
	if row == nil {
		row = p.getIfRowByIP(networkIf, ifs)
	}
	if row == nil {
		return 0, errors.New(errorCannotFindIf)
	}

	var value uint64
	switch statName {
	case "bytes":
		if direction == directionIn || direction == directionTotal {
			value += row.InOctets
		}
		if direction == directionOut || direction == directionTotal {
			value += row.OutOctets
		}
	case "packets":
		if direction == directionIn || direction == directionTotal {
			value += row.InUcastPkts + row.InNUcastPkts
		}
		if direction == directionOut || direction == directionTotal {
			value += row.OutUcastPkts + row.OutNUcastPkts
		}
	case "errors":
		if direction == directionIn || direction == directionTotal {
			value += row.InErrors
		}
		if direction == directionOut || direction == directionTotal {
			value += row.OutErrors
		}
	case "dropped":
		if direction == directionIn || direction == directionTotal {
			value += row.InDiscards + row.InUnknownProtos
		}
		if direction == directionOut || direction == directionTotal {
			value += row.OutDiscards
		}
	default:
		return 0, errors.New(errorInvalidSecondParam)
	}
	return value, nil
}

func (p *Plugin) getDevDiscovery() (devices []msgIfDiscovery, err error) {
	var table *win32.MIB_IF_TABLE2
	if table, err = win32.GetIfTable2(); err != nil {
		return
	}
	defer win32.FreeMibTable(table)

	devices = make([]msgIfDiscovery, 0, table.NumEntries)
	rows := (*win32.RGMIB_IF_ROW2)(unsafe.Pointer(&table.Table[0]))[:table.NumEntries:table.NumEntries]
	for i := range rows {
		guid := p.getGuidString(rows[i].InterfaceGuid)
		devices = append(devices, msgIfDiscovery{windows.UTF16ToString(rows[i].Description[:]), &guid})
	}
	return
}

func (p *Plugin) getMacAddress(physAddr [32]byte, physAddrLen uint32) string {
	if physAddrLen == 0 {
		return ""
	}
	mac := make([]byte, physAddrLen)
	for i := uint32(0); i < physAddrLen; i++ {
		mac[i] = physAddr[i]
	}
	return net.HardwareAddr(mac).String()
}

func uint64Ptr(v uint64) *uint64 {
	return &v
}

func (p *Plugin) getWMIAdapterDataBatch() (map[string]wmiAdapterData, error) {
	query := "SELECT Name, Speed, FullDuplex FROM MSFT_NetAdapter"
	results, err := wmi.QueryTable("root\\StandardCimv2", query)
	if err != nil {
		return nil, err
	}

	dataMap := make(map[string]wmiAdapterData)
	for _, item := range results {
		data := wmiAdapterData{}

		if name, ok := item["Name"].(string); ok {
			data.Name = name
		} else {
			continue
		}

		if s, ok := item["Speed"]; ok {
			if valStr, ok := s.(string); ok {
				if u, err := strconv.ParseUint(valStr, 10, 64); err == nil {
					data.Speed = &u
				}
			} else if valUint, ok := s.(uint64); ok {
				data.Speed = &valUint
			}
		}

		if duplex, ok := item["FullDuplex"].(bool); ok {
			data.FullDuplex = &duplex
		}

		dataMap[data.Name] = data
	}

	return dataMap, nil
}

func (p *Plugin) getWMIAdvancedDataBatch() (map[string]string, error) {
	query := "SELECT Name, DisplayValue FROM MSFT_NetAdapterAdvancedPropertySettingData WHERE DisplayName = 'Speed & Duplex'"
	results, err := wmi.QueryTable("root\\StandardCimv2", query)
	if err != nil {
		return nil, err
	}

	dataMap := make(map[string]string)
	for _, item := range results {
		name, nameOk := item["Name"].(string)
		displayValue, displayOk := item["DisplayValue"].(string)

		if nameOk && displayOk {
			dataMap[name] = displayValue
		}
	}

	return dataMap, nil
}

func (p *Plugin) getDevGet(regexExpr string) (result netIfResult, err error) {
	var table *win32.MIB_IF_TABLE2
	var compiledRegex *regexp.Regexp

	if table, err = win32.GetIfTable2(); err != nil {
		return
	}
	defer win32.FreeMibTable(table)

	if regexExpr != "" {
		compiledRegex, err = regexp.Compile(regexExpr)
		if err != nil {
			err = fmt.Errorf("invalid regex expression '%s': %w", regexExpr, err)
			return
		}
	}

	wmiAdapters, wmiErr := p.getWMIAdapterDataBatch()
	if wmiErr != nil {
		wmiAdapters = make(map[string]wmiAdapterData)
	}

	wmiAdvanced, wmiErr := p.getWMIAdvancedDataBatch()
	if wmiErr != nil {
		wmiAdvanced = make(map[string]string)
	}

	result.Config = make([]ifConfigData, 0, table.NumEntries)
	result.Values = make([]IfValuesData, 0, table.NumEntries)

	rows := (*win32.RGMIB_IF_ROW2)(unsafe.Pointer(&table.Table[0]))[:table.NumEntries:table.NumEntries]

	for i := range rows {
		ifName := windows.UTF16ToString(rows[i].Description[:])

		if compiledRegex != nil {
			if !compiledRegex.MatchString(ifName) {
				continue
			}
		}

		mac := p.getMacAddress(rows[i].PhysicalAddress, rows[i].PhysicalAddressLength)

		operState := "down"
		if rows[i].OperStatus == windows.IfOperStatusUp {
			operState = "up"
		}

		admStateVal := "down"
		if rows[i].AdminStatus == 1 {
			admStateVal = "up"
		}

		ifAliasVal := windows.UTF16ToString(rows[i].Alias[:])

		negotiationStatus := ""
		if displayValue, ok := wmiAdvanced[ifAliasVal]; ok {
			if displayValue == "Auto Negotiation" {
				negotiationStatus = "on"
			} else {
				negotiationStatus = "off"
			}
		}

		duplexStatus := ""
		var speedPtr *uint64

		if adapterData, ok := wmiAdapters[ifAliasVal]; ok {
			if adapterData.FullDuplex != nil {
				if *adapterData.FullDuplex {
					duplexStatus = "full"
				} else {
					duplexStatus = "half"
				}
			}

			if adapterData.Speed != nil {
				val := *adapterData.Speed / 1000000
				speedPtr = &val
			}
		}

		result.Config = append(result.Config, ifConfigData{
			Ifname:      ifName,
			Ifmac:       mac,
			Ifalias:     ifAliasVal,
			IfAdmState:  &admStateVal,
			IfOperState: &operState,
		})

		ifTypeVal := uint64(rows[i].Type)
		ifType := &ifTypeVal

		carrierVal := uint64(rows[i].InterfaceAndOperStatusFlags & 1)
		result.Values = append(result.Values, IfValuesData{
			Ifname: ifName,
			Ifmac:  mac,
			Iftype: ifType,
			In: IfStatistics{
				Ifbytes:   uint64Ptr(rows[i].InOctets),
				Ifpackets: uint64Ptr(rows[i].InUcastPkts + rows[i].InNUcastPkts),
				Iferrors:  uint64Ptr(rows[i].InErrors),
				Ifdropped: uint64Ptr(rows[i].InDiscards + rows[i].InUnknownProtos),
			},

			Out: IfStatistics{
				Ifbytes:   uint64Ptr(rows[i].OutOctets),
				Ifpackets: uint64Ptr(rows[i].OutUcastPkts + rows[i].OutNUcastPkts),
				Iferrors:  uint64Ptr(rows[i].OutErrors),
				Ifdropped: uint64Ptr(rows[i].OutDiscards),
			},

			Ifcarrier:     &carrierVal,
			Ifalias:       ifAliasVal,
			Ifnegotiation: negotiationStatus,
			Ifduplex:      duplexStatus,
			Ifspeed:       speedPtr,
		})
	}
	return
}

func (p *Plugin) getIfType(iftype uint32) string {
	switch iftype {
	case windows.IF_TYPE_OTHER:
		return "Other"
	case windows.IF_TYPE_ETHERNET_CSMACD:
		return "Ethernet"
	case windows.IF_TYPE_ISO88025_TOKENRING:
		return "Token Ring"
	case windows.IF_TYPE_PPP:
		return "PPP"
	case windows.IF_TYPE_SOFTWARE_LOOPBACK:
		return "Software Loopback"
	case windows.IF_TYPE_ATM:
		return "ATM"
	case windows.IF_TYPE_IEEE80211:
		return "IEEE 802.11 Wireless"
	case windows.IF_TYPE_TUNNEL:
		return "Tunnel type encapsulation"
	case windows.IF_TYPE_IEEE1394:
		return "IEEE 1394 Firewire"
	default:
		return "unknown"
	}
}

func (p *Plugin) getAdminStatus(status int32) string {
	switch status {
	case 0:
		return "disabled"
	case 1:
		return "enabled"
	default:
		return "unknown"
	}
}

func (p *Plugin) getIP(index uint32, ips []win32.MIB_IPADDRROW) string {
	for i := range ips {
		if ips[i].Index == index {
			return fmt.Sprintf(" %-15s", p.nToIP(ips[i].Addr))
		}
	}
	return " -"
}

func (p *Plugin) getDevList() (devices string, err error) {
	var ifTable *win32.MIB_IF_TABLE2
	if ifTable, err = win32.GetIfTable2(); err != nil {
		return
	}
	defer win32.FreeMibTable(ifTable)
	ifs := (*win32.RGMIB_IF_ROW2)(unsafe.Pointer(&ifTable.Table[0]))[:ifTable.NumEntries:ifTable.NumEntries]

	var ips []win32.MIB_IPADDRROW
	if ips, err = p.getIpAddrTable(); err != nil {
		return
	}

	for i := range ifs {
		devices += fmt.Sprintf("%-25s", p.getIfType(ifs[i].Type))
		devices += fmt.Sprintf(" %-8s", p.getAdminStatus(ifs[i].AdminStatus))
		devices += p.getIP(ifs[i].InterfaceIndex, ips)
		devices += fmt.Sprintf(" %s\n", windows.UTF16ToString(ifs[i].Description[:]))
	}

	return
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var direction networkDirection
	var mode string

	switch key {
	case "net.if.discovery":
		if len(params) > 0 {
			return nil, errors.New(errorParametersNotAllowed)
		}
		var devices []msgIfDiscovery
		if devices, err = p.getDevDiscovery(); err != nil {
			return
		}
		var b []byte
		if b, err = json.Marshal(devices); err != nil {
			return
		}
		return string(b), nil
	case "net.if.get":
		if len(params) > 1 {
			return nil, errors.New(errorTooManyParams)
		}
		expression := ""
		if len(params) > 0 {
			expression = params[0]
		}

		var res netIfResult
		if res, err = p.getDevGet(expression); err != nil {
			return
		}
		var b []byte
		if b, err = json.Marshal(res); err != nil {
			return
		}
		return string(b), nil
	case "net.if.list":
		if len(params) > 0 {
			return nil, errors.New(errorParametersNotAllowed)
		}
		return p.getDevList()
	case "net.if.in":
		direction = directionIn
	case "net.if.out":
		direction = directionOut
	case "net.if.total":
		direction = directionTotal
	default:
		/* SHOULD_NEVER_HAPPEN */
		return nil, errors.New(errorUnsupportedMetric)
	}

	if len(params) < 1 || params[0] == "" {
		return nil, errors.New(errorEmptyIfName)
	}

	if len(params) > 2 {
		return nil, errors.New(errorTooManyParams)
	}

	if len(params) == 2 && params[1] != "" {
		mode = params[1]
	} else {
		mode = "bytes"
	}

	return p.getNetStats(params[0], mode, direction)
}
