//go:build linux && (amd64 || arm64)

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
	"strings"
	"testing"
)

var testData = map[string]map[string]string{
	"dpkg": {
		"input": `install ok installed,docker-ce-cli,5:19.03.15~3-0~debian-stretch,amd64,183329
deinstall ok config-files,docutils-common,0.13.1+dfsg-2,all,670
install ok installed,dos2unix,7.4.1-1,amd64,1321`,
		"expectedOutput": `[{"name":"docker-ce-cli","manager":"dpkg","version":"5:19.03.15~3-0~debian-stretch","size":187728896,"arch":"amd64","buildtime":{"timestamp":0,"value":""},"installtime":{"timestamp":0,"value":""}},{"name":"dos2unix","manager":"dpkg","version":"7.4.1-1","size":1352704,"arch":"amd64","buildtime":{"timestamp":0,"value":""},"installtime":{"timestamp":0,"value":""}}]`,
	},
	"rpm": {
		"input": `glibc-gconv-extra,2.34-48.el9,x86_64,8122308,1666100063,1669204555
elfutils-default-yama-scope,0.187-5.el9,noarch,1810,1655411412,1659990239
perl-Scalar-List-Utils,1.56-461.el9,x86_64,143652,1628565132,1662121942`,
		"expectedOutput": `[{"name":"glibc-gconv-extra","manager":"rpm","version":"2.34-48.el9","size":8122308,"arch":"x86_64","buildtime":{"timestamp":1666100063,"value":"Tue Oct 18 16:34:23 2022"},"installtime":{"timestamp":1669204555,"value":"Wed Nov 23 13:55:55 2022"}},{"name":"elfutils-default-yama-scope","manager":"rpm","version":"0.187-5.el9","size":1810,"arch":"noarch","buildtime":{"timestamp":1655411412,"value":"Thu Jun 16 23:30:12 2022"},"installtime":{"timestamp":1659990239,"value":"Mon Aug  8 23:23:59 2022"}},{"name":"perl-Scalar-List-Utils","manager":"rpm","version":"1.56-461.el9","size":143652,"arch":"x86_64","buildtime":{"timestamp":1628565132,"value":"Tue Aug 10 06:12:12 2021"},"installtime":{"timestamp":1662121942,"value":"Fri Sep  2 15:32:22 2022"}}]`,
	},
	"pacman": {
		"input": ` wget, 1.21.3-1, x86_64, 3.03 MiB, Sun Mar 20 21:36:30 2022, Thu Nov 10 18:33:47 2022
 systemd-libs, 251.7-4, x86_64, 2006.57 KiB, Thu Nov 3 16:18:07 2022, Sun Nov 6 00:04:08 2022
 ca-certificates, 20220905-1, any, 0.00 B, Mon Sep 5 21:59:24 2022, Sun Nov 6 00:04:09 2022
 go, 2:1.19.3-1, x86_64, 435.03 MiB, Tue Nov 1 16:49:49 2022, Thu Nov 10 18:33:47 2022`,
		"expectedOutput": `[{"name":"wget","manager":"pacman","version":"1.21.3-1","size":3177185,"arch":"x86_64","buildtime":{"timestamp":1647812190,"value":"Sun Mar 20 21:36:30 2022"},"installtime":{"timestamp":1668105227,"value":"Thu Nov 10 18:33:47 2022"}},{"name":"systemd-libs","manager":"pacman","version":"251.7-4","size":2054727,"arch":"x86_64","buildtime":{"timestamp":1667492287,"value":"Thu Nov 3 16:18:07 2022"},"installtime":{"timestamp":1667693048,"value":"Sun Nov 6 00:04:08 2022"}},{"name":"ca-certificates","manager":"pacman","version":"20220905-1","size":0,"arch":"any","buildtime":{"timestamp":1662415164,"value":"Mon Sep 5 21:59:24 2022"},"installtime":{"timestamp":1667693049,"value":"Sun Nov 6 00:04:09 2022"}},{"name":"go","manager":"pacman","version":"2:1.19.3-1","size":456162017,"arch":"x86_64","buildtime":{"timestamp":1667321389,"value":"Tue Nov 1 16:49:49 2022"},"installtime":{"timestamp":1668105227,"value":"Thu Nov 10 18:33:47 2022"}}]`,
	},
	"pkgtools": {
		"input": `/var/log/packages/aaa_glibc-solibs-2.33-x86_64-5:UNCOMPRESSED PACKAGE SIZE:     14M
/var/log/packages/brotli-1.0.9-x86_64-7:UNCOMPRESSED PACKAGE SIZE:     2.4M
/var/log/packages/ca-certificates-20221205-noarch-1_slack15.0:UNCOMPRESSED PACKAGE SIZE:     360K`,
		"expectedOutput": `[{"name":"aaa_glibc-solibs","manager":"pkgtools","version":"2.33-5","size":14680064,"arch":"x86_64","buildtime":{"timestamp":0,"value":""},"installtime":{"timestamp":0,"value":""}},{"name":"brotli","manager":"pkgtools","version":"1.0.9-7","size":2516582,"arch":"x86_64","buildtime":{"timestamp":0,"value":""},"installtime":{"timestamp":0,"value":""}},{"name":"ca-certificates","manager":"pkgtools","version":"20221205-1_slack15.0","size":368640,"arch":"noarch","buildtime":{"timestamp":0,"value":""},"installtime":{"timestamp":0,"value":""}}]`,
	},
	"portage": {
		"input":          `dev-lang,tcl,8.6.12,r1,gentoo: 1104 files, 25 non-files, 10871914 bytes`,
		"expectedOutput": `[{"name":"tcl","manager":"portage","version":"8.6.12","size":10871914,"arch":"","buildtime":{"timestamp":0,"value":""},"installtime":{"timestamp":0,"value":""}}]`,
	},
}

func TestPackagesGet(t *testing.T) {
	managers := getManagers()

	for _, m := range managers {
		if _, ok := testData[m.name]; !ok {
			t.Errorf("unexpected package manager %s", m.name)

			return
		}

		input, ok := testData[m.name]["input"]
		if !ok {
			t.Errorf("input not defined for package manager %s", m.name)
		}

		expectedOutput, ok := testData[m.name]["expectedOutput"]
		if !ok {
			t.Errorf("output not defined for package manager %s", m.name)
		}

		output, err := m.detailsParser(m.name, strings.Split(input, "\n"), "")
		if err != nil {
			t.Errorf("%s failed: %s", m.name, err)
		} else if expectedOutput != output {
			t.Errorf("unexpected output from %s, expected\n%s\ngot\n%s", m.name, expectedOutput, output)
		}
	}
}
