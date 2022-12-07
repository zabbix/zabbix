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
	"encoding/json"
	"errors"
	"fmt"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/zbxerr"
	"zabbix.com/pkg/zbxcmd"
)

const timeFmt = "Mon Jan  2 15:04:05 2006"

type manager struct {
	name          string
	testCmd       string
	listCmd       string
	detailsCmd    string
	listParser    func(in []string, regex string) ([]string, error)
	detailsParser func(manager string, in []string, regex string) (string, error)
}

type TimeDetails struct {
	Timestamp int64  `json:"timestamp"`
	Value     string `json:"value"`
}

type PackageDetails struct {
	Name        string      `json:"name"`
	Manager     string      `json:"manager"`
	Version     string      `json:"version"`
	Size        uint64      `json:"size"`
	Arch        string      `json:"arch"`
	Buildtime   TimeDetails `json:"buildtime"`
	Installtime TimeDetails `json:"installtime"`
}

func getManagers() []manager {
	return []manager{
		{
			"dpkg",
			"dpkg --version 2> /dev/null",
			"dpkg --get-selections",
			"LC_ALL=C dpkg-query -W -f='${Status},${Package},${Version},${Architecture},${Installed-Size}\n'",
			dpkgList,
			dpkgDetails,
		},
		{
			"rpm",
			"rpm --version 2> /dev/null",
			"rpm -qa",
			"LC_ALL=C rpm -qa --queryformat '%{NAME},%{VERSION}-%{RELEASE},%{ARCH},%{SIZE},%{BUILDTIME},%{INSTALLTIME}\n'",
			parseRegex,
			rpmDetails,
		},
		{
			"pacman",
			"pacman --version 2> /dev/null",
			"pacman -Q",
			"LC_ALL=C pacman -Qi 2>/dev/null | grep -E '^(Name|Installed Size|Version|Architecture|(Install|Build) Date)'" +
				" | cut -f2- -d: | paste -d, - - - - - -",
			parseRegex,
			pacmanDetails,
		},
		{
			"pkgtools",
			"[ -d /var/log/packages ] && echo true",
			"ls /var/log/packages",
			"grep -r '^UNCOMPRESSED PACKAGE SIZE' /var/log/packages",
			parseRegex,
			dpkgDetails,
		},
	}
}

func parseRegex(in []string, regex string) (out []string, err error) {
	if regex == "" {
		return in, nil
	}

	rgx, err := regexp.Compile(regex)
	if err != nil {
		return nil, err
	}

	for _, s := range in {
		matched := rgx.MatchString(s)
		if !matched {
			continue
		}

		out = append(out, s)
	}

	return
}

func dpkgList(in []string, regex string) (out []string, err error) {
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

func appendPackage(name string, manager string, version string, arch string, size uint64, buildtime_value string,
	buildtime_timestamp int64, installtime_value string, installtime_timestamp int64) PackageDetails {
	return PackageDetails{
		Name:    name,
		Manager: manager,
		Version: version,
		Arch:    arch,
		Size:    size,
		Buildtime: TimeDetails{
			Timestamp: buildtime_timestamp,
			Value:     buildtime_value,
		},
		Installtime: TimeDetails{
			Timestamp: installtime_timestamp,
			Value:     installtime_value,
		},
	}
}

func dpkgDetails(manager string, in []string, regex string) (out string, err error) {
	const num_fields = 5

	rgx, err := regexp.Compile(regex)
	if err != nil {
		log.Debugf("internal error: cannot compile regex \"%s\"", regex)

		return
	}

	// initialize empty slice instead of nil slice
	pd := []PackageDetails{}

	for _, s := range in {
		// Status, Name, Version, Arch, Size
		split := strings.Split(s, ",")

		if len(split) != num_fields {
			log.Debugf("unexpected number of fields while expected %d in \"%s\", ignoring", num_fields, s)

			continue
		}

		if split[0] != "install ok installed" {
			continue
		}

		matched := rgx.MatchString(split[1])

		if !matched {
			continue
		}

		var size uint64

		size, err = strconv.ParseUint(split[4], 10, 64)

		if err != nil {
			return
		}

		// the reported size is in kB, we want bytes
		size *= 1024

		// dpkg has no build/install time information
		pd = append(pd, appendPackage(split[1], manager, split[2], split[3], size, "", 0, "", 0))
	}

	var b []byte

	b, err = json.Marshal(pd)

	if err != nil {
		return
	}

	out = string(b)

	return
}

func rpmDetails(manager string, in []string, regex string) (out string, err error) {
	const num_fields = 6

	rgx, err := regexp.Compile(regex)
	if err != nil {
		log.Debugf("internal error: cannot compile regex \"%s\"", regex)

		return
	}

	// initialize empty slice instead of nil slice
	pd := []PackageDetails{}

	for _, s := range in {
		// Name, Version, Arch, Size, Build time, Install time
		split := strings.Split(s, ",")

		if len(split) != num_fields {
			log.Debugf("unexpected number of fields while expected %d in \"%s\", ignoring", num_fields, s)

			continue
		}

		matched := rgx.MatchString(split[0])

		if !matched {
			continue
		}

		var size uint64
		var buildtime_timestamp, installtime_timestamp int64

		size, err = strconv.ParseUint(split[3], 10, 64)

		if err != nil {
			return
		}

		buildtime_timestamp, err = strconv.ParseInt(split[4], 10, 64)

		if err != nil {
			return
		}

		installtime_timestamp, err = strconv.ParseInt(split[5], 10, 64)

		if err != nil {
			return
		}

		buildtime_tm := time.Unix(buildtime_timestamp, 0)
		installtime_tm := time.Unix(installtime_timestamp, 0)

		pd = append(pd, appendPackage(split[0], manager, split[1], split[2], size, buildtime_tm.Format(timeFmt),
			buildtime_timestamp, installtime_tm.Format(timeFmt), installtime_timestamp))
	}

	var b []byte

	b, err = json.Marshal(pd)

	if err != nil {
		return
	}

	out = string(b)

	return
}

func pacmanDetails(manager string, in []string, regex string) (out string, err error) {
	const num_fields = 6

	rgx, err := regexp.Compile(regex)
	if err != nil {
		log.Debugf("internal error: cannot compile regex \"%s\"", regex)

		return
	}

	// initialize empty slice instead of nil slice
	pd := []PackageDetails{}

	for _, s := range in {
		s = strings.Trim(s, " ")

		// Name, Version, Arch, Size, Build time, Install time
		split := strings.Split(s, ", ")

		if len(split) != num_fields {
			log.Debugf("unexpected number of fields while expected %d in \"%s\", ignoring", num_fields, s)

			continue
		}

		matched := rgx.MatchString(split[0])

		if !matched {
			continue
		}

		size_parts := strings.Split(split[3], " ")

		if len(size_parts) != 2 {
			log.Debugf("unexpected size field \"%s\" in \"%s\", ignoring", split[3], s)

			continue
		}

		var size_float float64

		size_float, err = strconv.ParseFloat(size_parts[0], 64)

		if err != nil {
			log.Debugf("unexpected size \"%s\" in \"%s\", ignoring", size_parts[0], s)

			continue
		}

		// pacman supports the following labels:
		// "B", "KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB"
		var size uint64

		switch size_parts[1] {
			case "B":
				size = uint64(size_float)
			case "KiB":
				size = uint64(size_float * 1024)
			case "MiB":
				size = uint64(size_float * 1024 * 1024)
			case "GiB":
				size = uint64(size_float * 1024 * 1024 * 1024)
			case "TiB":
				size = uint64(size_float * 1024 * 1024 * 1024 * 1024)
			default:
				log.Debugf("unexpected Install Size suffix \"%s\" in \"%s\", ignoring", size_parts[1], s)

				continue
		}

		var buildtime, installtime time.Time

		buildtime, err = time.Parse(timeFmt, split[4])

		if err != nil {
		        log.Debugf("unexpected buildtime \"%s\" in \"%s\", ignoring", split[4], s)

			continue
		}

		installtime, err = time.Parse(timeFmt, split[5])

		if err != nil {
		        log.Debugf("unexpected installtime \"%s\" in \"%s\", ignoring", split[5], s)

			continue
		}

		pd = append(pd, appendPackage(split[0], manager, split[1], split[2], size, split[4], buildtime.Unix(),
			split[5], installtime.Unix()))
	}

	var b []byte

	b, err = json.Marshal(pd)

	if err != nil {
		return
	}

	out = string(b)

	return
}

func getParams(params []string, maxparams int) (regex string, manager string, short bool, err error) {
	if len(params) > maxparams {
		err = zbxerr.ErrorTooManyParameters

		return
	}

	manager = "all"
	short = false

	switch len(params) {
	case 3:
		switch params[2] {
		case "short":
			short = true
		case "full", "":
		default:
			err = errors.New("Invalid third parameter.")

			return
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

	return
}

func (p *Plugin) systemSwPackages(params []string) (result string, err error) {
	var regex, manager string
	var short bool

	regex, manager, short, err = getParams(params, 3)

	if err != nil {
		return
	}

	managers := getManagers()
	manager_found := false

	for _, m := range managers {
		if manager != "all" && m.name != manager {
			continue
		}

		test, err := zbxcmd.Execute(m.testCmd, time.Second*time.Duration(p.options.Timeout), "")
		if err != nil || test == "" {
			continue
		}

		tmp, err := zbxcmd.Execute(m.listCmd, time.Second*time.Duration(p.options.Timeout), "")
		if err != nil {
			p.Errf("Failed to execute command '%s', err: %s", m.listCmd, err.Error())

			continue
		}

		var s []string

		if tmp != "" {
			s, err = m.listParser(strings.Split(tmp, "\n"), regex)
			if err != nil {
				p.Errf("Failed to parse '%s' output, err: %s", m.listCmd, err.Error())

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

		if !manager_found {
			manager_found = true
			result = out
		} else if out != "" {
			result = fmt.Sprintf("%s\n%s", result, out)
		}
	}

	if !manager_found {
		err = errors.New("Cannot obtain package information.")
	}

	return
}

func (p *Plugin) systemSwPackagesGet(params []string) (result string, err error) {
	var regex, manager string

	regex, manager, _, err = getParams(params, 2)

	if err != nil {
		return
	}

	managers := getManagers()
	manager_found := false

	for _, m := range managers {
		if manager != "all" && m.name != manager {
			continue
		}

		test, err := zbxcmd.Execute(m.testCmd, time.Second*time.Duration(p.options.Timeout), "")
		if err != nil || test == "" {
			continue
		}

		tmp, err := zbxcmd.Execute(m.detailsCmd, time.Second*time.Duration(p.options.Timeout), "")
		if err != nil {
			p.Errf("Failed to execute command '%s', err: %s", m.listCmd, err.Error())

			continue
		}

		var json string

		if tmp != "" {
			json, err = m.detailsParser(m.name, strings.Split(tmp, "\n"), regex)
			if err != nil {
				p.Errf("Failed to parse '%s' output, err: %s", m.listCmd, err.Error())

				continue
			}
		}

		if !manager_found {
			manager_found = true
			result = json
		} else if json != "" {
			result = json
		}
	}

	if !manager_found {
		err = errors.New("Cannot obtain package information.")
	}

	return
}
