//go:build windows
// +build windows

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

package sw

import (
	"errors"
	"encoding/json"
	"strconv"

	"golang.org/x/sys/windows/registry"
	"git.zabbix.com/ap/plugin-support/plugin"
	"zabbix.com/pkg/win32"
)

const (
	processorArchitectureAmd64   = 9
	processorArchitectureArm     = 5
	processorArchitectureArm64   = 12
	processorArchitectureIa64    = 6
	processorArchitectureIntel   = 0
	processorArchitectureUnknown = 0xffff
)

const (
	regProductName    = "ProductName"
	regMajor          = "CurrentBuildNumber"
	regMinor          = "UBR"
	regEdition        = "EditionID"
	regComposition    = "CompositionEditionID"
	regDisplayVersion = "DisplayVersion"
	regSPVersion      = "CSDVersion"
	regSPBuild        = "CSDBuildNumber"
	regVersion        = "CurrentVersion"
	regLab            = "BuildLab"
	regLabEX          = "BuildLabEx"
)

type system_info struct {
	OSType         string `json:"os_type"`
	ProductName    string `json:"product_name,omitempty"`
	Architecture   string `json:"architecture,omitempty"`
	Major          string `json:"major,omitempty"`
	Minor          string `json:"minor,omitempty"`
	Edition        string `json:"edition,omitempty"`
	Composition    string `json:"composition,omitempty"`
	DisplayVersion string `json:"display_version,omitempty"`
	SPVersion      string `json:"sp_version,omitempty"`
	SPBuild        string `json:"sp_build,omitempty"`
	Version        string `json:"version,omitempty"`
	VersionPretty  string `json:"version_pretty,omitempty"`
	VersionFull    string `json:"version_full"`
}

func getRegistryValue(value string, valueType uint32) (result string, err error) {
	handle, err := registry.OpenKey(registry.LOCAL_MACHINE, "SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion", registry.QUERY_VALUE)
	if err != nil {
		return "", err
	}
	defer handle.Close()

	if valueType == registry.SZ {
		result, _, err = handle.GetStringValue(value)
		if err != nil {
			return "", err
		}
	} else if valueType == registry.DWORD {
		var intVal uint64
		intVal, _, err = handle.GetIntegerValue(value)
		if err != nil {
			return "", err
		}
		result = strconv.FormatUint(intVal, 10)
	}

	return
}

func getBuildString() (result string, err error) {
	var tmp string

	tmp, err = getRegistryValue(regMajor, registry.SZ)
	if err == nil {
		result += "Build " + tmp

		tmp, err = getRegistryValue(regMinor, registry.DWORD)
		if err == nil {
			result += "." + tmp
		}
	}

	if len(result) > 0 {
		err = nil
	}

	return
}

func getFullOSInfoString() (result string, err error) {
	var tmp string
	gotSomething := false

	tmp, err = getRegistryValue(regProductName, registry.SZ)
	if err == nil {
		result += tmp
		gotSomething = true
	}

	tmp, err = getRegistryValue(regLabEX, registry.SZ)
	if err == nil {
		if gotSomething {
			result += " "
		}
		result += tmp
		gotSomething = true
	} else {
		tmp, err = getRegistryValue(regLab, registry.SZ)
		if err == nil {
			if gotSomething {
				result += " "
			}
			result += tmp
			gotSomething = true
		}
	}

	tmp, err = getBuildString()
	if err == nil {
		if gotSomething {
			result += " "
		}
		result += tmp
		gotSomething = true
	}

	if gotSomething {
		err = nil
	}
	return
}

func getPrettyOSInfoString() (result string, err error) {
	var tmp string
	gotSomething := false

	tmp, err = getRegistryValue(regProductName, registry.SZ)
	if err == nil {
		result += tmp
		gotSomething = true
	}

	tmp, err = getBuildString()
	if err == nil {
		if gotSomething {
			result += " "
		}
		result += tmp
		gotSomething = true
	}

	tmp, err = getRegistryValue(regSPVersion, registry.SZ)
	if err == nil {
		if gotSomething {
			result += " "
		}
		result += tmp
		gotSomething = true
	}

	if gotSomething {
		err = nil
	}
	return
}

func (p *Plugin) getPackages(params []string) (result interface{}, err error) {
	return nil, plugin.UnsupportedMetricError
}

func (p *Plugin) getOSVersion(params []string) (result interface{}, err error) {
	var info string

	if len(params) > 0 && params[0] != "" {
		info = params[0]
	} else {
		info = "full"
	}

	switch info {
	case "full":
		return getFullOSInfoString()

	case "short":
		return getPrettyOSInfoString()

	case "name":
		return getRegistryValue(regProductName, registry.SZ)

	default:
		return nil, errors.New("Invalid first parameter.")
	}

	return
}

func (p *Plugin) getOSVersionJSON() (result interface{}, err error) {
	var info system_info
	var sysInfo win32.SystemInfo
	var jsonArray []byte

	info.OSType = "windows"

	info.VersionFull, _ = getFullOSInfoString()
	info.VersionPretty, _ = getPrettyOSInfoString()

	info.ProductName, _ = getRegistryValue(regProductName, registry.SZ)
	info.Major, _ = getRegistryValue(regMajor, registry.SZ)
	info.Minor, _ = getRegistryValue(regMinor, registry.DWORD)
	info.Edition, _ = getRegistryValue(regEdition, registry.SZ)
	info.Composition, _ = getRegistryValue(regComposition, registry.SZ)
	info.DisplayVersion, _ = getRegistryValue(regDisplayVersion, registry.SZ)
	info.SPVersion, _ = getRegistryValue(regSPVersion, registry.SZ)
	info.SPBuild, _ = getRegistryValue(regSPBuild, registry.SZ)
	info.Version, _ = getRegistryValue(regVersion, registry.SZ)

	sysInfo, err = win32.GetNativeSystemInfo()
	if err == nil {
		switch sysInfo.WProcessorArchitecture {
		case processorArchitectureAmd64:
			info.Architecture = "x86_64"

		case processorArchitectureArm:
			info.Architecture = "arm"

		case processorArchitectureArm64:
			info.Architecture = "arm64"

		case processorArchitectureIa64:
			info.Architecture = "Itanium64"

		case processorArchitectureIntel:
			info.Architecture = "x86"

		case processorArchitectureUnknown:
			info.Architecture = "unknown"
		}
	}

	jsonArray, err = json.Marshal(info)
	result = string(jsonArray)

	return
}
