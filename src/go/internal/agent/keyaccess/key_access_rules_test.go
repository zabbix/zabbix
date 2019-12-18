// +build linux,amd64

/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package keyaccess

import (
	"testing"

	"zabbix.com/pkg/itemutil"
)

type Scenario struct {
	metric string
	result bool
}

func RunScenarios(t *testing.T, scenarios []Scenario, records []Record, numRules int) {
	var err error

	if err := LoadRules(records); err != nil {
		t.Errorf("Failed to load rules: %s", err.Error())
	}

	if numRules != len(Rules) {
		t.Errorf("Number of rules does not match: %d; expected: %d", len(Rules), numRules)
	}

	for _, test := range scenarios {
		var key string
		var params []string

		if key, params, err = itemutil.ParseKey(test.metric); err != nil {
			t.Errorf("Failed to parse metric \"%s\"", test.metric)
		}
		if ok := CheckRules(key, params); ok != test.result {
			t.Errorf("Unexpected result for metric \"%s\"", test.metric)
		}
	}
}

func TestNoRules(t *testing.T) {
	var records = []Record{}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[]", result: true},
	}

	RunScenarios(t, scenarios, records, 0)
}

func TestDenyAll(t *testing.T) {
	var records = []Record{
		{Pattern: "*", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "system.run[echo 1]", result: false},
		{metric: "system.localtime[utc]", result: false},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestNoParameters(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents", result: false},
		{metric: "vfs.file.contents[]", result: true},
		{metric: "vfs.file.contents[/etc/passwd]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestEmptyParameters(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents[]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[]", result: false},
		{metric: "vfs.file.contents[\"\"]", result: false},
		{metric: "vfs.file.contents", result: true},
		{metric: "vfs.file.contents[/etc/passwd]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestAnyParameters(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents[*]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[]", result: false},
		{metric: "vfs.file.contents[/path/to/file]", result: false},
		{metric: "vfs.file.contents", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestAnyParametersDoubleAsterisk(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents[**]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[]", result: false},
		{metric: "vfs.file.contents[/path/to/file]", result: false},
		{metric: "vfs.file.contents[/path/to/file,UTF8]", result: false},
		{metric: "vfs.file.contents", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestSpecificFirstParameter(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents[/etc/passwd,*]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[/etc/passwd,]", result: false},
		{metric: "vfs.file.contents[/etc/passwd,utf8]", result: false},
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.contents[/var/log/zabbix_server.log]", result: true},
		{metric: "vfs.file.contents[]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestFirstParameterPattern(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents[*passwd*]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.contents[/etc/passwd,]", result: true},
		{metric: "vfs.file.contents[/etc/passwd,utf8]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestAnySecondParameter(t *testing.T) {
	var records = []Record{
		{Pattern: "test[a,*]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "test[a]", result: false},
		{metric: "test[a,]", result: false},
		{metric: "test[a,anything]", result: false},
		{metric: "test[]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestFirstParameterPatternAndAnyFollowing(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents[*passwd*,*]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[/etc/passwd,]", result: false},
		{metric: "vfs.file.contents[/etc/passwd,utf8]", result: false},
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.contents[/tmp/test]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestAnyFirstParameter(t *testing.T) {
	var records = []Record{
		{Pattern: "test[*,b]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "test[anything,c]", result: true},
		{metric: "test[anything,b]", result: false},
		{metric: "test[anything,b,c]", result: true},
		{metric: "test[anything,b,]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestEmptySecondParameterValue(t *testing.T) {
	var records = []Record{
		{Pattern: "test[a,,c]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "test[a,,c]", result: false},
		{metric: "test[a,b,c]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestAnySecondParameterValue(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents[/var/log/zabbix_server.log,*,abc]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[/var/log/zabbix_server.log,,abc]", result: false},
		{metric: "vfs.file.contents[/var/log/zabbix_server.log,utf8,abc]", result: false},
		{metric: "vfs.file.contents[/var/log/zabbix_server.log,,abc,def]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestSpecificParameters(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents[/etc/passwd,utf8]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[/etc/passwd,utf8]", result: false},
		{metric: "vfs.file.contents[/etc/passwd,]", result: true},
		{metric: "vfs.file.contents[/etc/passwd,utf16]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestQuotedParameters(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents[/etc/passwd,utf8]", Deny: true},
		{Pattern: "system.run[*]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[\"/etc/passwd\",\"utf8\"]", result: false},
		{metric: "vfs.file.contents[\"/etc/passwd\",\"\"]", result: true},
		{metric: "vfs.file.contents[\"/etc/passwd\",\"utf16\"]", result: true},
		{metric: "system.run[\"echo 1\"]", result: false},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestKeyPatternWithoutParameters(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.*", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents", result: false},
		{metric: "vfs.file.size", result: false},
		{metric: "vfs.file.contents[]", result: true},
		{metric: "vfs.file.size[/var/log/zabbix_server.log]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestKeyPatternWithAnyParameters(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.*[*]", Deny: true},
		{Pattern: "vfs.*.contents", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.size.bytes[]", result: false},
		{metric: "vfs.file.size[/var/log/zabbix_server.log, utf8]", result: false},
		{metric: "vfs.file.size.bytes", result: true},
		{metric: "vfs.mount.point.file.contents", result: false},
		{metric: "vfs..contents", result: false},
		{metric: "vfs.contents", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestWhitelist(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.*[/var/log/*]", Deny: false},
		{Pattern: "system.localtime[*]", Deny: false},
		{Pattern: "*", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.size[/var/log/zabbix_server.log]", result: true},
		{metric: "vfs.file.contents[/var/log/zabbix_server.log]", result: true},
		{metric: "system.localtime[]", result: true},
		{metric: "system.localtime[utc]", result: true},
		{metric: "system.localtime", result: false},
	}

	RunScenarios(t, scenarios, records, 3)
}

func TestBlacklist(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.contents[/etc/passwd,*]", Deny: true},
		{Pattern: "system.run[*]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.contents[/etc/passwd,]", result: false},
		{metric: "system.run[]", result: false},
		{metric: "system.run[echo 1]", result: false},
		{metric: "system.run[echo 2,a]", result: false},
		{metric: "system.localtime[utc]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestCombinedWildcardInKey(t *testing.T) {
	var records = []Record{
		{Pattern: "t*t*[a]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "test1[a]", result: false},
		{metric: "test_best2[a]", result: false},
		{metric: "tests[a]", result: false},
		{metric: "test[a]", result: false},
		{metric: "best[a]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestDuplicateRules(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.*", Deny: true},
		{Pattern: "vfs.file.*", Deny: true},
		{Pattern: "vfs.file.contents", Deny: true},
		{Pattern: "vfs.file.contents[]", Deny: true},
		{Pattern: "vfs.file.contents[/etc/passwd]", Deny: true},
		{Pattern: "vfs.file.contents[/etc/passwd,*]", Deny: true},
		{Pattern: "vfs.file.*", Deny: false},
		{Pattern: "vfs.file.contents", Deny: false},
		{Pattern: "vfs.file.contents[]", Deny: false},
		{Pattern: "vfs.file.contents[/etc/passwd]", Deny: false},
		{Pattern: "vfs.file.contents[/etc/passwd,*]", Deny: false},
		{Pattern: "net.*.in", Deny: false},
		{Pattern: "net.*.in", Deny: false},
		{Pattern: "net.*.in[]", Deny: false},
		{Pattern: "net.*.in[eth0]", Deny: false},
		{Pattern: "net.*.in[eth0,*]", Deny: false},
		{Pattern: "net.*.in", Deny: true},
		{Pattern: "net.*.in[]", Deny: true},
		{Pattern: "net.*.in[eth0]", Deny: true},
		{Pattern: "net.*.in[eth0,*]", Deny: true},
		{Pattern: "net.*.in[eth0,bytes]", Deny: true},
		{Pattern: "*", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.size", result: false},
		{metric: "vfs.file.contents", result: false},
		{metric: "vfs.file.contents[]", result: false},
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.contents[/etc/passwd,utf8]", result: false},
		{metric: "net.if.in", result: true},
		{metric: "net.if.in[]", result: true},
		{metric: "net.if.in[eth0]", result: true},
		{metric: "net.if.in[eth0,]", result: true},
		{metric: "net.if.in[eth0,packets]", result: true},
		{metric: "net.if.in[eth0,bytes]", result: true},
		{metric: "system.run[echo 1]", result: false},
	}

	RunScenarios(t, scenarios, records, 11)
}

func TestNoRulesAfterAllowAll(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.*[*]", Deny: true},
		{Pattern: "*", Deny: false},
		{Pattern: "system.run[*]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.size[/etc/systemd.conf]", result: false},
		{metric: "system.run[echo 1]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestNoRulesAfterDenyAll(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.*[*]", Deny: false},
		{Pattern: "*", Deny: true},
		{Pattern: "system.run[*]", Deny: false},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: true},
		{metric: "vfs.file.size[/etc/systemd.conf]", result: true},
		{metric: "system.run[echo 1]", result: false},
		{metric: "system.localtime", result: false},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestIncompleteWhitelist(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.*[/var/log/*]", Deny: false},
		{Pattern: "system.localtime[*]", Deny: false},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.size[/var/log/zabbix_server.log]", result: true},
		{metric: "vfs.file.contents[/var/log/zabbix_server.log]", result: true},
		{metric: "system.localtime[]", result: true},
		{metric: "system.localtime[utc]", result: true},
		{metric: "system.localtime", result: false},
	}

	RunScenarios(t, scenarios, records, 3)
}

func TestNoTrailingAllowRules(t *testing.T) {
	var records = []Record{
		{Pattern: "vfs.file.*[*]", Deny: true},
		{Pattern: "system.run[*]", Deny: false},
		{Pattern: "*", Deny: false},
	}

	var scenarios = []Scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.size[/etc/systemd.conf]", result: false},
		{metric: "system.run[echo 1]", result: true},
		{metric: "system.localtime", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestEmptyParametersMatch(t *testing.T) {
	var records = []Record{
		{Pattern: "web.page.get[localhost,*,*]", Deny: true},
	}

	var scenarios = []Scenario{
		{metric: "web.page.get[localhost]", result: false},
		{metric: "web.page.get[localhost,]", result: false},
		{metric: "web.page.get[localhost,/,80]", result: false},
		{metric: "web.page.get[localhost,/]", result: false},
		{metric: "web.page.get[localhost,,80]", result: false},
		{metric: "web.page.get[127.0.0.1]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}
