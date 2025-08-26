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

package hw

import (
	"os"
	"strings"
	"time"

	"golang.zabbix.com/agent2/pkg/zbxcmd"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	pciCMD = "lspci"
	usbCMD = "lsusb"

	dmiTable = "/sys/firmware/dmi/tables/DMI"

	chassisVendor = 1 << iota
	chassisModel
	chassisSerial
	chassisType

	maxChassisTypeLen = 36
	minChassisTypelen = 1
)

// Plugin -
type Plugin struct {
	plugin.Base
	executor zbxcmd.Executor
}

var impl Plugin

// from System Management BIOS (SMBIOS) Reference Specification v2.7.1
var chassisTypes = []string{
	"Other",
	"Unknown",
	"Desktop",
	"Low Profile Desktop",
	"Pizza Box",
	"Mini Tower",
	"Tower",
	"Portable",
	"LapTop",
	"Notebook",
	"Hand Held",
	"Docking Station",
	"All in One",
	"Sub Notebook",
	"Space-saving",
	"Lunch Box",
	"Main Server Chassis",
	"Expansion Chassis",
	"SubChassis",
	"Bus Expansion Chassis",
	"Peripheral Chassis",
	"RAID Chassis",
	"Rack Mount Chassis",
	"Sealed-case PC",
	"Multi-system chassis",
	"Compact PCI",
	"Advanced TCA",
	"Blade",
	"Blade Enclosure",
	"Tablet",
	"Convertible",
	"Detachable",
	"IoT Gateway",
	"Embedded PC",
	"Mini PC",
	"Stick PC",
}

func init() {
	err := plugin.RegisterMetrics(
		&impl, "Hw",
		"system.hw.chassis", "Chassis information.",
		"system.hw.devices", "Listing of PCI or USB devices.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Configure -
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
}

// Validate -
func (p *Plugin) Validate(options interface{}) error { return nil }

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "system.hw.chassis":
		return p.exportChassis(params)
	case "system.hw.devices":
		return p.exportDevices(params, ctx.Timeout())
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

func (p *Plugin) exportChassis(params []string) (result interface{}, err error) {
	content, flags, length, err := getParams(params)
	if err != nil {
		return
	}

	var out string
	for i := 0; i+4 <= length; {
		var value string
		value, flags = getChassisValues(content, flags, i)

		out += value
		if flags == 0 {
			break
		}

		i = updateStartCounter(content, i)
	}

	out = strings.TrimSpace(out)
	if out == "" {
		return nil, zbxerr.New("cannot obtain hardware information")
	}

	return out, nil
}

func updateStartCounter(content []byte, start int) int {
	start += int(content[1])
	for {
		if content[start] == 0 && content[start+1] == 0 {
			break
		}

		start++
	}

	start += 2

	return start
}

func getChassisValues(content []byte, flags, start int) (string, int) {
	var value string

	positionNumbers := []int{4, 5, 7}
	types := []int{chassisVendor, chassisModel, chassisSerial}

	if content[start] == 1 {
		for i, nr := range positionNumbers {
			var tmp string
			tmp, flags = getChassisValue(content, start, nr, flags, types[i])
			value += " " + tmp
		}
	} else if content[start] == 3 && flags&chassisType != 0 {
		value = getChassisType(content[start+5])
		if value != "" {
			value = " " + value
		}
		flags -= chassisType
	}

	return value, flags
}

func getChassisValue(content []byte, start, magicNumber, flags, flag int) (string, int) {
	var value string
	if flags&flag != 0 {
		value = getDmiString(content[start:], content[start+magicNumber])
		flags -= flag
	}

	return value, flags
}

func getParams(params []string) (content []byte, flags, conLength int, err error) {
	if flags, err = getFlags(params); err != nil {
		return
	}

	if content, err = os.ReadFile(dmiTable); err != nil {
		return
	}

	return content, flags, len(content), nil
}

func getChassisType(num byte) (out string) {
	if num < minChassisTypelen || num > maxChassisTypeLen {
		return ""
	}

	return chassisTypes[num-1]
}

func getFlags(params []string) (int, error) {
	var mode string

	switch len(params) {
	case 1:
		mode = params[0]
	case 0:
		mode = "full"
	default:
		return 0, zbxerr.ErrorTooManyParameters
	}

	switch mode {
	case "full", "":
		return chassisVendor | chassisModel | chassisSerial | chassisType, nil
	case "model":
		return chassisModel, nil
	case "serial":
		return chassisSerial, nil
	case "type":
		return chassisType, nil
	case "vendor":
		return chassisVendor, nil
	default:
		return 0, zbxerr.New("incorrect first parameter")
	}
}

func getDmiString(in []byte, num byte) (out string) {
	if num == 0 || len(in) < 2 || int(in[1]) > len(in) {
		return
	}

	c := in[in[1]:]
	for num > 1 {
		c = c[clen(c)+1:]
		num--
	}

	return string(c[:clen(c)])
}

func clen(n []byte) int {
	for i := 0; i < len(n); i++ {
		if n[i] == 0 {
			return i
		}
	}

	return len(n)
}

func (p *Plugin) exportDevices(params []string, timeout int) (any, error) {
	cmd, err := getDeviceCmd(params)
	if err != nil {
		return nil, err
	}

	// Needed so the executor is initialized once, this should be done in configure, but then Zabbix agent 2
	// will not start if there are issues with finding cmd.exe on windows, and that will break backwards compatibility.
	if p.executor == nil {
		p.executor, err = zbxcmd.InitExecutor()
		if err != nil {
			return nil, errs.Wrap(err, "command init failed")
		}
	}

	out, err := p.executor.ExecuteStrict(cmd, time.Second*time.Duration(timeout), "")
	if err != nil {
		return "", errs.New("command exec failed")
	}

	return out, nil
}

func getDeviceCmd(params []string) (string, error) {
	switch len(params) {
	case 1:
		switch params[0] {
		case "pci", "":
			return pciCMD, nil
		case "usb":
			return usbCMD, nil
		default:
			return "", zbxerr.New("invalid first parameter")
		}
	case 0:
		return pciCMD, nil
	default:
		return "", zbxerr.ErrorTooManyParameters
	}
}
