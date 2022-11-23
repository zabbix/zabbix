//go:build !windows
// +build !windows

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
	"regexp"
	"sort"
	"strings"
	"time"
	"fmt"
	"os"
	"encoding/json"
	"syscall"
	"strconv"

	"zabbix.com/pkg/zbxcmd"
)

type manager struct {
	name    string
	testCmd string
	cmd     string
	parser  func(in []string, regex string) ([]string, error)
}

type system_info struct {
	OSType         string `json:"os_type"`
	ProductName    string `json:"product_name,omitempty"`
	Architecture   string `json:"architecture,omitempty"`
	Major          string `json:"major,omitempty"`
	Minor          string `json:"minor,omitempty"`
	Patch          string `json:"patch,omitempty"`
	Kernel         string `json:"kernel,omitempty"`
	VersionPretty  string `json:"version_pretty,omitempty"`
	VersionFull    string `json:"version_full"`
}

const (
	swOSFull             = "/proc/version"
	swOSShort            = "/proc/version_signature"
	swOSName             = "/etc/issue.net"
	swOSNameRelease      = "/etc/os-release"
	swOSOptionPrettyName = "PRETTY_NAME"
)

func charArray2String(chArr []int8) (result string) {
	var bin []byte

	for _, v := range chArr {
		if v == int8(0) {
			break
		}
		bin = append(bin, byte(v))
	}

	result = string(bin)

	return
}

func readOsInfoFile(path string) (contents string, err error) {
	var bin []byte

	bin, err = os.ReadFile(path)
	if err != nil {
		return "", fmt.Errorf("Cannot open " + path + ": %s", err)
	}

	return string(bin), nil
}

func getVersionFull() (version string, err error) {
	version, err = readOsInfoFile(swOSFull)

	if err == nil {
		version = strings.TrimSpace(version)
	}

	return
}

func findFirstMatch(src string, pattern string) (res string) {
	regex := regexp.MustCompile("PRETTY_NAME=\"([^\"]+)\"")
	match := regex.FindStringSubmatch(src)
	if len(match) > 1 {
		return match[1]
	}

	return ""
}

func getName() (name string, err error) {
	name, err = readOsInfoFile(swOSNameRelease)

	if err == nil {
		if name = findFirstMatch(name, "PRETTY_NAME=\"([^\"]+)\""); len(name) > 0 {
			return name, nil
		}

		if name = findFirstMatch(name, "PRETTY_NAME=(\\S+)\\s*\\n"); len(name) > 0 {
			return name, nil
		}
	}

	name, err = readOsInfoFile(swOSName)

	return
}

func getManagers() []manager {
	return []manager{
		{"dpkg", "dpkg --version 2> /dev/null", "dpkg --get-selections", parseDpkg},
		{"pkgtools", "[ -d /var/log/packages ] && echo true", "ls /var/log/packages", parseRegex},
		{"rpm", "rpm --version 2> /dev/null", "rpm -qa", parseRegex},
		{"pacman", "pacman --version 2> /dev/null", "pacman -Q", parseRegex},
	}
}

func parseDpkg(in []string, regex string) (out []string, err error) {
	rgx, err := regexp.Compile(regex)
	if err != nil {
		return nil, err
	}

	for _, s := range in {
		split := strings.Fields(s)
		if len(split) < 2 || split[len(split)-1] != "install" {
			continue
		}

		str := strings.Join(split[:len(split)-1], " ")

		matched := rgx.MatchString(str)
		if !matched {
			continue
		}

		out = append(out, str)
	}

	return
}

func (p *Plugin) getPackages(params []string) (result interface{}, err error) {
	var short bool

	var regex string

	manager := "all"

	switch len(params) {
	case 3:
		switch params[2] {
		case "short":
			short = true
		case "full", "":
		default:
			return nil, errors.New("Invalid third parameter.")
		}

		fallthrough
	case 2:
		if params[1] != "" {
			manager = params[1]
		}

		fallthrough
	case 1:
		regex = params[0]
	}

	managers := getManagers()

	for _, m := range managers {
		if manager != "all" && m.name != manager {
			continue
		}

		test, err := zbxcmd.Execute(m.testCmd, time.Second*time.Duration(p.options.Timeout), "")
		if err != nil || test == "" {
			continue
		}

		tmp, err := zbxcmd.Execute(m.cmd, time.Second*time.Duration(p.options.Timeout), "")
		if err != nil {
			p.Errf("Failed to execute command '%s', err: %s", m.cmd, err.Error())

			continue
		}

		var s []string

		if tmp != "" {
			s, err = m.parser(strings.Split(tmp, "\n"), regex)
			if err != nil {
				p.Errf("Failed to parse '%s' output, err: %s", m.cmd, err.Error())

				continue
			}
		}

		sort.Strings(s)

		var out string

		if short {
			out = strings.Join(s, ", ")
		} else {
			if len(s) != 0 {
				out = fmt.Sprintf("[%s] %s", m.name, strings.Join(s, ", "))
			} else {
				out = fmt.Sprintf("[%s]", m.name)
			}
		}

		if result == nil {
			result = out
		} else if out != "" {
			result = fmt.Sprintf("%s\n%s", result, out)
		}
	}

	if result == nil {
		return nil, errors.New("Cannot obtain package information.")
	}

	return
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
		if result, err = getVersionFull(); err == nil {
			return result, nil
		}

	case "short":
		return readOsInfoFile (swOSShort)

	case "name":
		if result, err = getName(); err == nil {
			return result, nil
		}

	default:
		return nil, errors.New("Invalid first parameter.")
	}

	return
}

func (p *Plugin) getOSVersionJSON() (result interface{}, err error) {
	var info system_info
	var jsonArray []byte

	info.OSType = "linux"

	info.ProductName, _ = getName()

	u := syscall.Utsname{}
	syscall.Uname(&u)

	info.Kernel = charArray2String(u.Release[:])
	info.Architecture = charArray2String(u.Machine[:])

	if len(info.ProductName) > 0 {
		info.VersionPretty += info.ProductName
	}
	if len(info.Architecture) > 0 {
		info.VersionPretty += " " + info.Architecture
	}
	if len(info.Kernel) > 0 {
		info.VersionPretty += " " + info.Kernel

		var major, minor, patch int
		read, _ := fmt.Sscanf(info.Kernel, "%d.%d.%d", &major, &minor, &patch)

		if read > 0 {
			info.Major = strconv.Itoa(major)
		}
		if read > 1 {
			info.Minor = strconv.Itoa(minor)
		}
		if read > 2 {
			info.Patch = strconv.Itoa(patch)
		}
	}

	info.VersionFull, _ = getVersionFull()

	jsonArray, err = json.Marshal(info)
	result = string(jsonArray)

	return
}
