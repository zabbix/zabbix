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

// Package version provides zabbix release version
package version

import (
	"fmt"
	"runtime"
	"strings"
)

const (
	ZABBIX_REVDATE          = "29 October 2025"
	ZABBIX_VERSION_MAJOR    = 8
	ZABBIX_VERSION_MINOR    = 0
	ZABBIX_VERSION_PATCH    = 0
	ZABBIX_VERSION_RC       = "alpha2"
	ZABBIX_VERSION_RC_NUM   = "{ZABBIX_RC_NUM}"
	ZABBIX_VERSION_REVISION = "{ZABBIX_REVISION}"
	copyrightMessage        = "Copyright (C) 2025 Zabbix SIA\n" +
		"License AGPLv3: GNU Affero General Public License version 3 <https://www.gnu.org/licenses/>.\n" +
		"This is free software: you are free to change and redistribute it according to\n" +
		"the license. There is NO WARRANTY, to the extent permitted by law."
)

var (
	titleMessage  string = "{undefined}"
	compileDate   string = "{undefined}"
	compileTime   string = "{undefined}"
	compileOs     string = "{undefined}"
	compileArch   string = "{undefined}"
	compileMode   string
	extraLicenses []string
)

func RevDate() string {
	return ZABBIX_REVDATE
}

func Major() int {
	return ZABBIX_VERSION_MAJOR
}

func Minor() int {
	return ZABBIX_VERSION_MINOR
}

func Patch() int {
	return ZABBIX_VERSION_PATCH
}

func RC() string {
	return ZABBIX_VERSION_RC
}

func LongStr() string {
	var ver string = fmt.Sprintf("%d.%d.%d", Major(), Minor(), Patch())
	if len(RC()) != 0 {
		ver += " " + RC()
	}
	return ver
}

func Long() string {
	var ver string = fmt.Sprintf("%d.%d.%d", Major(), Minor(), Patch())
	if len(RC()) != 0 {
		ver += RC()
	}
	return ver
}
func LongNoRC() string {
	var ver string = fmt.Sprintf("%d.%d.%d", Major(), Minor(), Patch())
	return ver
}

func Short() string {
	return fmt.Sprintf("%d.%d", Major(), Minor())
}

func Revision() string {
	return ZABBIX_VERSION_REVISION
}

func CopyrightMessage() string {
	msg := copyrightMessage

	for _, license := range extraLicenses {
		msg += license
	}

	return msg
}

func CompileDate() string {
	return compileDate
}

func CompileTime() string {
	return compileTime
}

func CompileOs() string {
	return compileOs
}

func CompileArch() string {
	return compileArch
}

func CompileMode() string {
	return compileMode
}

func TitleMessage() string {
	var title string = titleMessage
	if "windows" == compileOs {
		if -1 < strings.Index(compileArch, "64") {
			title += " Win64"
		} else {
			title += " Win32"
		}
	}

	if len(compileMode) != 0 {
		title += fmt.Sprintf(" (%s)", compileMode)
	}

	return title
}

// Display shows program version.
// Program version includes Zabbix revision and it's time and date, compilation time and date, Go compiler tree's
// version string, copyright message, and additionalMessages provided by the caller function.
func Display(additionalMessages []string) {
	fmt.Printf("%s (Zabbix) %s\n", TitleMessage(), Long())
	fmt.Printf(
		"Revision %s %s, compilation time: %s %s, built with: %s\n",
		Revision(), RevDate(), CompileDate(), CompileTime(), runtime.Version(),
	)

	for _, msg := range additionalMessages {
		fmt.Println(msg)
	}

	fmt.Println()
	fmt.Println(CopyrightMessage())
}

func Init(title string, extra ...string) {
	titleMessage = title
	extraLicenses = append(extraLicenses, extra...)
}

func init() {
	extraLicenses = make([]string, 0)
}
