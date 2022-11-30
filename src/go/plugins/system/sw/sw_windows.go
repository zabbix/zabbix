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
	"strings"
	"fmt"

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
	return registry.OpenKey(registry.LOCAL_MACHINE, "SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion", registry.QUERY_VALUE)
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
			result = fmt.Sprintf("Build %s", result);
		}
		return result, nil
	}

	return "", err
}

func setErrorIfEmpty(eIn error, eOut *error) {
	if eIn != nil && *eOut == nil {
		*eOut = eIn
	}

	return
}

func getFullOSInfoString(handle registry.Key) (result string, err error) {
	var name, lab, build string
	var internal_err error

	name, internal_err = getRegistryValue(handle, regProductName, registry.SZ)
	setErrorIfEmpty(internal_err, &err)

	lab, internal_err = getRegistryValue(handle, regLabEX, registry.SZ)
	setErrorIfEmpty(internal_err, &err)

	if len(lab) == 0 {
		lab, internal_err = getRegistryValue(handle, regLab, registry.SZ)
		setErrorIfEmpty(internal_err, &err)
	}

	build, internal_err = getBuildString(handle, true)
	setErrorIfEmpty(internal_err, &err)

	result = joinNonEmptyStrings(" ", []string{name, lab, build})

	if len(result) > 0 {
		return strings.TrimSpace(result), nil
	} else {
		return "", err
	}
}

func getPrettyOSInfoString(handle registry.Key) (result string, err error) {
	var name, build, spVer string
	var internal_err error

	name, internal_err = getRegistryValue(handle, regProductName, registry.SZ)
	setErrorIfEmpty(internal_err, &err)

	build, internal_err = getBuildString(handle, true)
	setErrorIfEmpty(internal_err, &err)

	spVer, internal_err = getRegistryValue(handle, regSPVersion, registry.SZ)
	setErrorIfEmpty(internal_err, &err)

	result = joinNonEmptyStrings(" ",[]string{name, build, spVer})

	if len(result) > 0 {
		return strings.TrimSpace(result), nil
	} else {
		return "", err
	}
}

func (p *Plugin) getPackages(params []string) (result interface{}, err error) {
	return nil, plugin.UnsupportedMetricError
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
		return nil, err
	}
	defer handle.Close()

	switch info {
	case "full":
		return getFullOSInfoString(handle)

	case "short":
		return getPrettyOSInfoString(handle)

	case "name":
		return getRegistryValue(handle, regProductName, registry.SZ)

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
	info.Build, _ =  getBuildString(handle, false)

	info.ProductName, _ = getRegistryValue(handle, regProductName, registry.SZ)
	info.Major, _ = getRegistryValue(handle, regMajor, registry.SZ)
	info.Minor, _ = getRegistryValue(handle, regMinor, registry.DWORD)
	info.Edition, _ = getRegistryValue(handle, regEdition, registry.SZ)
	info.Composition, _ = getRegistryValue(handle, regComposition, registry.SZ)
	info.DisplayVersion, _ = getRegistryValue(handle, regDisplayVersion, registry.SZ)
	info.SPVersion, _ = getRegistryValue(handle, regSPVersion, registry.SZ)
	info.SPBuild, _ = getRegistryValue(handle, regSPBuild, registry.SZ)
	info.Version, _ = getRegistryValue(handle, regVersion, registry.SZ)

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
			info.Architecture = "Intel Itanium"

		case processorArchitectureIntel:
			info.Architecture = "x86"

		case processorArchitectureUnknown:
			info.Architecture = "unknown"
		}
	}

out:
	jsonArray, err = json.Marshal(info)
	if err == nil {
		result = string(jsonArray)
	}

	return
}
