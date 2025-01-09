//go:build !windows
// +build !windows

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
	"bufio"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"syscall"
	"time"

	"golang.zabbix.com/agent2/pkg/zbxcmd"
	"golang.zabbix.com/agent2/util"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxerr"
)

const timeFmt = "Mon Jan _2 15:04:05 2006"

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

type systemInfo struct {
	OSType        string `json:"os_type"`
	ProductName   string `json:"product_name,omitempty"`
	Architecture  string `json:"architecture,omitempty"`
	Major         string `json:"kernel_major,omitempty"`
	Minor         string `json:"kernel_minor,omitempty"`
	Patch         string `json:"kernel_patch,omitempty"`
	Kernel        string `json:"kernel,omitempty"`
	VersionPretty string `json:"version_pretty,omitempty"`
	VersionFull   string `json:"version_full"`
}

const (
	swOSFull             = "/proc/version"
	swOSShort            = "/proc/version_signature"
	swOSName             = "/etc/issue.net"
	swOSNameRelease      = "/etc/os-release"
	swOSOptionPrettyName = "PRETTY_NAME"
)

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
			pkgtoolsDetails,
		},
		{
			"portage",
			"qsize --version 2> /dev/null",
			"qlist -C -I -F '%{PN},%{PV},%{PR}'",
			"qsize -C --bytes -F '%{CATEGORY},%{PN},%{PV},%{PR},%{REPO}'",
			parseRegex,
			portageDetails,
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

func appendPackage(name string, manager string, version string, size uint64, arch string, buildtime_timestamp int64,
	buildtime_value string, installtime_timestamp int64, installtime_value string) PackageDetails {
	return PackageDetails{
		Name:    name,
		Manager: manager,
		Version: version,
		Size:    size,
		Arch:    arch,
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

		// According to the Debian project's Policy Manual on Binary package
		// control files[1], the Installed-Size field[2] is not mandatory in
		// the stanza. The query for such packages would return an empty value,
		// which strconv obviously fails to parse into an Uint.
		//
		// When that is the case, we simply report the size as 0.
		//
		// [1]: https://www.debian.org/doc/debian-policy/ch-controlfields.html#binary-package-control-files-debian-control
		// [2]: https://www.debian.org/doc/debian-policy/ch-controlfields.html#s-f-installed-size

		if split[4] != "" {
			size, err = strconv.ParseUint(split[4], 10, 64)
			if err != nil {
				return "", err
			}
		}

		// the reported size is in kB, we want bytes
		size *= 1024

		// dpkg has no build/install time information
		pd = append(pd, appendPackage(split[1], manager, split[2], size, split[3], 0, "", 0, ""))
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

		pd = append(pd, appendPackage(split[0], manager, split[1], size, split[2], buildtime_timestamp,
			buildtime_tm.Format(timeFmt), installtime_timestamp, installtime_tm.Format(timeFmt)))
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

		pd = append(pd, appendPackage(split[0], manager, split[1], size, split[2], buildtime.Unix(), split[4],
			installtime.Unix(), split[5]))
	}

	var b []byte

	b, err = json.Marshal(pd)
	if err != nil {
		return
	}

	out = string(b)

	return
}

func pkgtoolsDetails(manager string, in []string, regex string) (out string, err error) {
	const num_fields = 4

	pkg_rgx, err := regexp.Compile(regex)
	if err != nil {
		log.Debugf("internal error: cannot compile regex \"%s\"", regex)

		return
	}

	line_rgx, err := regexp.Compile(`^/var/log/packages/(.*)-([^-]+)-([^-]+)-([^:]+):UNCOMPRESSED PACKAGE SIZE:\s+(.*)$`)
	if err != nil {
		log.Debugf("internal error: cannot compile regex for parsing package details")

		return
	}

	// initialize empty slice instead of nil slice
	pd := []PackageDetails{}

	for _, s := range in {
		// ...Name-Version-Arch-Release:...: Size
		// e. g.: /var/log/packages/util-linux-2.27.1-x86_64-1:UNCOMPRESSED PACKAGE SIZE:     1.9M
		// note the possible dash in the package name, this is why we are forced to use regex
		s_ := line_rgx.ReplaceAllString(s, `$1,$2-$4,$3,$5`)

		// Name, Version, Arch, Size
		split := strings.Split(s_, ",")

		if len(split) != num_fields {
			log.Debugf("unexpected number of fields while expected %d in \"%s\", ignoring", num_fields, s)

			continue
		}

		matched := pkg_rgx.MatchString(split[0])

		if !matched {
			continue
		}

		var size_float float64

		size_float, err = strconv.ParseFloat(split[3][:len(split[3])-1], 64)
		if err != nil {
			log.Debugf("unexpected size \"%s\" in \"%s\", ignoring", split[3], s)

			continue
		}

		// according to pkgtools source code the size suffix is
		// either 'K' or 'M' and it may be specified in 3 formats:
		//   <n>K
		//   <n>.<n>M
		//   <n>M
		var size uint64

		i := strings.Index(split[3], "K")

		if i >= 1 {
			size = uint64(size_float * 1024)
		} else {
			i := strings.Index(split[3], "M")

			if i >= 1 {
				size = uint64(size_float * 1024 * 1024)
			} else {
				log.Debugf("unexpected size suffix in \"%s\", expected 'K' or 'M' in \"%s\", ignoring",
					split[3], s)

				continue
			}
		}

		// pkgtools has no build/install time information
		pd = append(pd, appendPackage(split[0], manager, split[1], size, split[2], 0, "", 0, ""))
	}

	var b []byte

	b, err = json.Marshal(pd)
	if err != nil {
		return
	}

	out = string(b)

	return
}

func portageParseSizeInfo(in string) (out uint64, err error) {
	const sizeinfo_num_fields = 3

	// "n files, n non-files, n bytes"
	sizeinfo := strings.Split(in, ", ")

	if len(sizeinfo) != sizeinfo_num_fields {
		err = errors.New("invalid input format: separator \", \" not found in \"%s\"")
		return
	}

	_, err = fmt.Sscanf(sizeinfo[2], "%d bytes", &out)
	if err != nil {
		return
	}

	return
}

func portageDetails(manager string, in []string, regex string) (out string, err error) {
	const num_fields, pkginfo_num_fields, sizeinfo_num_fields = 2, 5, 3

	rgx, err := regexp.Compile(regex)
	if err != nil {
		log.Debugf("internal error: cannot compile regex \"%s\"", regex)

		return
	}

	pd := []PackageDetails{}

	for _, s := range in {
		var size uint64

		// category,name,version,revision,repo: file count, nonfile count, size
		split := strings.Split(s, ":")

		if len(split) != num_fields {
			log.Debugf("invalid input format: separator \":\" not found in \"%s\"", s)

			continue
		}

		// category,name,version,revision,repo
		pkginfo := strings.Split(split[0], ",")

		if "" != regex && !rgx.MatchString(pkginfo[1]) {
			continue
		}

		size, err = portageParseSizeInfo(split[1])
		if err != nil {
			log.Debugf("internal error: failed to parse package size information in \"%s\"", split[1])
			continue
		}

		pd = append(pd, appendPackage(pkginfo[1], manager, pkginfo[2], size, "", 0, "", 0, ""))
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

func (p *Plugin) systemSwPackages(params []string, timeout int) (result string, err error) {
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

		test, err := zbxcmd.Execute(m.testCmd, time.Second*time.Duration(timeout), "")
		if err != nil || test == "" {
			continue
		}

		tmp, err := zbxcmd.Execute(m.listCmd, time.Second*time.Duration(timeout), "")
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

func (p *Plugin) systemSwPackagesGet(params []string, timeout int) (result string, err error) {
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

		test, err := zbxcmd.Execute(m.testCmd, time.Second*time.Duration(timeout), "")
		if err != nil || test == "" {
			continue
		}

		tmp, err := zbxcmd.Execute(m.detailsCmd, time.Second*time.Duration(timeout), "")
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
		return "", fmt.Errorf("Cannot open "+path+": %s", err)
	}

	return strings.TrimSpace(string(bin)), nil
}

func findFirstMatch(src string, reg *regexp.Regexp) (res string) {
	match := reg.FindStringSubmatch(src)
	if len(match) > 1 {
		return match[1]
	}

	return ""
}

func getName() (name string, err error) {
	if readTextLineFromFile, err := os.Open(swOSNameRelease); err == nil {
		defer readTextLineFromFile.Close()

		fileScanner := bufio.NewScanner(readTextLineFromFile)
		fileScanner.Split(bufio.ScanLines)

		regexQuoted := regexp.MustCompile(swOSOptionPrettyName + "=\"([^\"]+)\"")
		regexUnquoted := regexp.MustCompile(swOSOptionPrettyName + "=(\\S+)\\s*$")
		var tmpStr string

		for fileScanner.Scan() {
			tmpStr = fileScanner.Text()
			name = findFirstMatch(tmpStr, regexQuoted)

			if len(name) == 0 {
				name = findFirstMatch(tmpStr, regexUnquoted)
			}

			if len(name) > 0 {
				return name, nil
			}
		}
	}

	return readOsInfoFile(swOSName)
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
		if result, err = readOsInfoFile(swOSFull); err == nil {
			return result, nil
		}

	case "short":
		return readOsInfoFile(swOSShort)

	case "name":
		if result, err = getName(); err == nil {
			return result, nil
		}

	default:
		return nil, errors.New("Invalid first parameter.")
	}

	return
}

func parseKernelVersion(info *systemInfo) {
	const (
		gotMajor = 1
		gotMinor = 2
		gotPatch = 3
	)

	var major, minor, patch int
	read, _ := fmt.Sscanf((*info).Kernel, "%d.%d.%d", &major, &minor, &patch)

	if read >= gotMajor {
		(*info).Major = strconv.Itoa(major)
	}
	if read >= gotMinor {
		(*info).Minor = strconv.Itoa(minor)
	}
	if read >= gotPatch {
		(*info).Patch = strconv.Itoa(patch)
	}
}

func (p *Plugin) getOSVersionJSON() (result interface{}, err error) {
	var info systemInfo
	var jsonArray []byte

	info.OSType = "linux"

	info.ProductName, _ = getName()
	info.VersionFull, _ = readOsInfoFile(swOSFull)

	u := syscall.Utsname{}
	if syscall.Uname(&u) == nil {
		info.Kernel += util.UnameArrayToString(&u.Release)
		info.Architecture += util.UnameArrayToString(&u.Machine)

		if len(info.ProductName) > 0 {
			info.VersionPretty += info.ProductName
		}
		if len(info.Architecture) > 0 {
			info.VersionPretty += " " + info.Architecture
		}
		if len(info.Kernel) > 0 {
			info.VersionPretty += " " + info.Kernel

			parseKernelVersion(&info)
		}
	}

	jsonArray, err = json.Marshal(info)
	if err == nil {
		result = string(jsonArray)
	}

	return
}
