//go:build windows
// +build windows

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

package sw

import (
	"encoding/json"
	"errors"
	"fmt"
	"strconv"
	"strings"

	"golang.org/x/sys/windows/registry"
	"golang.zabbix.com/agent2/pkg/win32"
	"golang.zabbix.com/sdk/plugin"
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

	regVersionKey = "SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion"
)

type system_info struct {
	OSType         string `json:"os_type"`
	ProductName    string `json:"product_name,omitempty"`
	Architecture   string `json:"architecture,omitempty"`
	Major          string `json:"build_number,omitempty"`
	Minor          string `json:"build_revision,omitempty"`
	Build          string `json:"build,omitempty"`
	Edition        string `json:"edition,omitempty"`
	Composition    string `json:"composition,omitempty"`
	DisplayVersion string `json:"display_version,omitempty"`
	SPVersion      string `json:"sp_version,omitempty"`
	SPBuild        string `json:"sp_build,omitempty"`
	Version        string `json:"version,omitempty"`
	VersionPretty  string `json:"version_pretty,omitempty"`
	VersionFull    string `json:"version_full"`
}

func (p *Plugin) systemSwPackages(params []string, timeout int) (result interface{}, err error) {
	return nil, plugin.UnsupportedMetricError
}

func (p *Plugin) systemSwPackagesGet(params []string, timeout int) (result interface{}, err error) {
	return nil, plugin.UnsupportedMetricError
}

func joinNonEmptyStrings(delim string, strs []string) (res string) {
	var nonEmpty []string
	for _, s := range strs {
		if len(s) > 0 {
			nonEmpty = append(nonEmpty, s)
		}
	}

	return strings.Join(nonEmpty, delim)
}

func getRegistryValue(handle registry.Key, value string, valueType uint32) (result string, err error) {
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

func openRegistrySysInfoKey() (handle registry.Key, err error) {
	return registry.OpenKey(registry.LOCAL_MACHINE, regVersionKey, registry.QUERY_VALUE)
}

func getBuildString(handle registry.Key, inclDesc bool) (result string, err error) {
	var minor, major string

	major, err = getRegistryValue(handle, regMajor, registry.SZ)
	if err == nil {
		minor, _ = getRegistryValue(handle, regMinor, registry.DWORD)
	}

	result = joinNonEmptyStrings(".", []string{major, minor})
	if len(result) > 0 {
		if inclDesc {
			result = fmt.Sprintf("Build %s", result)
		}
		return result, nil
	}

	return "", err
}

func createWinInfoLineOrErr(data []string) (res string, err error) {
	res = joinNonEmptyStrings(" ", data)

	if len(res) > 0 {
		return strings.TrimSpace(res), nil
	} else {
		return "", errors.New("Could not read registry value " + regProductName)
	}
}

func getFullOSInfoString(handle registry.Key) (result string, err error) {
	var name, lab, build string

	name, _ = getRegistryValue(handle, regProductName, registry.SZ)
	lab, _ = getRegistryValue(handle, regLabEX, registry.SZ)

	if len(lab) == 0 {
		lab, _ = getRegistryValue(handle, regLab, registry.SZ)
	}

	build, _ = getBuildString(handle, true)

	return createWinInfoLineOrErr([]string{name, lab, build})
}

func getPrettyOSInfoString(handle registry.Key) (result string, err error) {
	var name, build, spVer string

	name, _ = getRegistryValue(handle, regProductName, registry.SZ)
	build, _ = getBuildString(handle, true)
	spVer, _ = getRegistryValue(handle, regSPVersion, registry.SZ)

	return createWinInfoLineOrErr([]string{name, build, spVer})
}

func (p *Plugin) getOSVersion(params []string) (result interface{}, err error) {
	var info string
	var handle registry.Key

	if len(params) > 0 && params[0] != "" {
		info = params[0]
	} else {
		info = "full"
	}

	handle, err = openRegistrySysInfoKey()
	if err != nil {
		return nil, errors.New("Could not open registry key " + regVersionKey)
	}
	defer handle.Close()

	switch info {
	case "full":
		return getFullOSInfoString(handle)

	case "short":
		return getPrettyOSInfoString(handle)

	case "name":
		result, err = getRegistryValue(handle, regProductName, registry.SZ)
		if err != nil {
			return nil, errors.New("Could not read registry value " + regProductName)
		}

	default:
		return nil, errors.New("Invalid first parameter.")
	}

	return
}

func (p *Plugin) getOSVersionJSON() (result interface{}, err error) {
	var info system_info
	var sysInfo win32.SystemInfo
	var jsonArray []byte
	var handle registry.Key

	info.OSType = "windows"

	handle, err = openRegistrySysInfoKey()
	if err != nil {
		goto out
	}
	defer handle.Close()

	info.VersionFull, _ = getFullOSInfoString(handle)
	info.VersionPretty, _ = getPrettyOSInfoString(handle)
	info.Build, _ = getBuildString(handle, false)

	info.ProductName, _ = getRegistryValue(handle, regProductName, registry.SZ)
	info.Major, _ = getRegistryValue(handle, regMajor, registry.SZ)
	info.Minor, _ = getRegistryValue(handle, regMinor, registry.DWORD)
	info.Edition, _ = getRegistryValue(handle, regEdition, registry.SZ)
	info.Composition, _ = getRegistryValue(handle, regComposition, registry.SZ)
	info.DisplayVersion, _ = getRegistryValue(handle, regDisplayVersion, registry.SZ)
	info.SPVersion, _ = getRegistryValue(handle, regSPVersion, registry.SZ)
	info.SPBuild, _ = getRegistryValue(handle, regSPBuild, registry.SZ)
	info.Version, _ = getRegistryValue(handle, regVersion, registry.SZ)

	sysInfo = win32.GetNativeSystemInfo()
	switch sysInfo.WProcessorArchitecture {
	case processorArchitectureAmd64:
		info.Architecture = "x86_64"

	case processorArchitectureArm:
		info.Architecture = "arm"

	case processorArchitectureArm64:
		info.Architecture = "arm64"

	case processorArchitectureIa64:
		info.Architecture = "Intel Itanium"

	case processorArchitectureIntel:
		info.Architecture = "x86"

	case processorArchitectureUnknown:
		info.Architecture = "unknown"
	}

out:
	jsonArray, err = json.Marshal(info)
	if err == nil {
		result = string(jsonArray)
	}

	return
}
