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

package mock

import (
	_ "embed"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"runtime"
)

// Outputs is a collection of all the smartctl command outputs that can be used
// as test data, grouped by environment.
//
//nolint:gochecknoglobals
var Outputs = EnvironmentCollection{}

// Smartctl outputs that dont belong to an environment.
var (
	//go:embed outputs/version_valid.json
	OutputVersionValid []byte

	//go:embed outputs/version_invalid.json
	OutputVersionInvalid []byte

	//go:embed outputs/scan.json
	OutputScan []byte

	//go:embed outputs/env_mac_scan_basic.json
	OutputEnvMacScanBasic []byte

	//go:embed outputs/env_mac_scan_raid.json
	OutputEnvMacScanRaid []byte

	//go:embed outputs/scan_type_sat.json
	OutputScanTypeSAT []byte

	//go:embed outputs/scan_basic.json
	OutputScanBasic []byte

	//go:embed outputs/all_disc_info_csmi.json
	OutputAllDiscInfoCSMI []byte

	//go:embed outputs/all_disc_info_sda.json
	OutputAllDiscInfoSDA []byte

	//go:embed outputs/all_disc_info_mac.json
	OutputAllDiscInfoMac []byte
)

// EnvironmentCollection is a collection of all the smartctl command outputs,
// grouped by environment.
type EnvironmentCollection map[string]Environment

// SmartInfoScans is a collection of all the smartctl command outputs for
// smart info scans, grouped by arguments.
//
// `smartctl -a /dev/xxx -d xxx -j`.
type SmartInfoScans map[string]json.RawMessage

// Environment is a collection of all the smartctl command outputs for a
// specific environment.
type Environment struct {
	Version           json.RawMessage `json:"version"`
	AllDevicesScan    json.RawMessage `json:"all_devices_scan"`
	RaidDevicesScan   json.RawMessage `json:"raid_devices_scan"`
	AllSmartInfoScans SmartInfoScans  `json:"all_smart_info_scans"`
}

//nolint:gochecknoinits
func init() {
	_, file, _, ok := runtime.Caller(0)
	if !ok {
		panic("No caller information")
	}

	b, err := os.ReadFile(filepath.Join(filepath.Dir(file), "outputs.json"))
	if err != nil {
		panic(err)
	}

	err = json.Unmarshal(b, &Outputs)
	if err != nil {
		panic(err)
	}
}

// Get returns the environment with the given name.
func (ec EnvironmentCollection) Get(name string) Environment {
	e, ok := ec[name]
	if !ok {
		panic(fmt.Sprintf("Environment %s not found", name))
	}

	return e
}

// Get returns the smart info scan with the given args.
func (si SmartInfoScans) Get(args string) json.RawMessage {
	s, ok := si[args]
	if !ok {
		panic(fmt.Sprintf("SmartInfoScan %s not found", args))
	}

	return s
}
