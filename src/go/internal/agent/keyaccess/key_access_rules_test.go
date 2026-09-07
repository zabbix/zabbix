//go:build linux && (amd64 || arm64)

/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package keyaccess

import (
	"testing"

	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/plugin/itemutil"
)

type accessRules struct {
	allowRecords       conf.Node
	denyRecords        conf.Node
	allowRegexpRecords conf.Node
	denyRegexpRecords  conf.Node
}

type scenario struct {
	metric string
	result bool
}

func (r *accessRules) addRule(pattern string, ruleType RuleType) {
	n := len(r.allowRecords.Nodes) + len(r.denyRecords.Nodes) +
		len(r.allowRegexpRecords.Nodes) + len(r.denyRegexpRecords.Nodes) + 1

	if ruleType == ALLOW {
		r.allowRecords.Nodes = append(r.allowRecords.Nodes, &conf.Value{Value: []byte(pattern), Line: n})
	} else {
		r.denyRecords.Nodes = append(r.denyRecords.Nodes, &conf.Value{Value: []byte(pattern), Line: n})
	}
}

func (r *accessRules) addRegexpRule(pattern string, ruleType RuleType) {
	n := len(r.allowRecords.Nodes) + len(r.denyRecords.Nodes) +
		len(r.allowRegexpRecords.Nodes) + len(r.denyRegexpRecords.Nodes) + 1

	if ruleType == ALLOW {
		r.allowRegexpRecords.Nodes = append(r.allowRegexpRecords.Nodes,
			&conf.Value{Value: []byte(pattern), Line: n})
	} else {
		r.denyRegexpRecords.Nodes = append(r.denyRegexpRecords.Nodes,
			&conf.Value{Value: []byte(pattern), Line: n})
	}
}

func RunScenarios(t *testing.T, scenarios []scenario, rules accessRules, numRules int) {

	loadErr := LoadRules(
		&rules.allowRecords, &rules.denyRecords,
		&rules.allowRegexpRecords, &rules.denyRegexpRecords,
	)
	if loadErr != nil {
		t.Errorf("Failed to load rules: %s", loadErr.Error())
	}

	if numRules != GetNumberOfRules() {
		t.Errorf("Number of rules does not match: %d; expected: %d", GetNumberOfRules(), numRules)
	}

	for _, test := range scenarios {
		var key string
		var params []string

		key, params, parseErr := itemutil.ParseKey(test.metric)
		if parseErr != nil {
			t.Errorf("Failed to parse metric \"%s\"", test.metric)
		}

		if ok := CheckRules(test.metric, key, params); ok != test.result {
			t.Errorf("Unexpected result for metric \"%s\": got %v, want %v", test.metric, ok, test.result)
		}
	}
}

func TestNoRules(t *testing.T) {
	var records accessRules

	var scenarios = []scenario{
		{metric: "vfs.file.contents[]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestDenyAll(t *testing.T) {
	var records accessRules

	records.addRule("*", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "system.run[echo 1]", result: false},
		{metric: "system.localtime[utc]", result: false},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestNoParameters(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents", result: false},
		{metric: "vfs.file.contents[]", result: true},
		{metric: "vfs.file.contents[/etc/passwd]", result: true},
		{metric: "vfs.file.contents.ext", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestEmptyParameters(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents[]", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[]", result: false},
		{metric: "vfs.file.contents[ ]", result: false},
		{metric: "vfs.file.contents[\"\"]", result: false},
		{metric: "vfs.file.contents[ \"\" ]", result: false},
		{metric: "vfs.file.contents", result: true},
		{metric: "vfs.file.contents[/etc/passwd]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestAnyParameters(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents[*]", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[]", result: false},
		{metric: "vfs.file.contents[/path/to/file]", result: false},
		{metric: "vfs.file.contents", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestAnyParametersDoubleAsterisk(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents[**]", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[]", result: false},
		{metric: "vfs.file.contents[/path/to/file]", result: false},
		{metric: "vfs.file.contents[/path/to/file,UTF8]", result: false},
		{metric: "vfs.file.contents", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestSpecificFirstParameter(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents[/etc/passwd,*]", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd,]", result: false},
		{metric: "vfs.file.contents[/etc/passwd,utf8]", result: false},
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.contents[ /etc/passwd]", result: false},
		{metric: "vfs.file.contents[/etc/passwd ]", result: true},
		{metric: "vfs.file.contents[/var/log/zabbix_server.log]", result: true},
		{metric: "vfs.file.contents[]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestFirstParameterPattern(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents[*passwd*]", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.contents[ /etc/passwd]", result: false},
		{metric: "vfs.file.contents[/etc/passwd ]", result: false},
		{metric: "vfs.file.contents[/etc/passwd,]", result: true},
		{metric: "vfs.file.contents[/etc/passwd,utf8]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestAnySecondParameter(t *testing.T) {
	var records accessRules

	records.addRule("test[a,*]", DENY)

	var scenarios = []scenario{
		{metric: "test[a]", result: false},
		{metric: "test[a,]", result: false},
		{metric: "test[a,anything]", result: false},
		{metric: "test[]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestFirstParameterPatternAndAnyFollowing(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents[*passwd*,*]", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd,]", result: false},
		{metric: "vfs.file.contents[/etc/passwd,utf8]", result: false},
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.contents[/tmp/test]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestAnyFirstParameter(t *testing.T) {
	var records accessRules

	records.addRule("test[*,b]", DENY)

	var scenarios = []scenario{
		{metric: "test[anything,c]", result: true},
		{metric: "test[anything,b]", result: false},
		{metric: "test[anything,b,c]", result: true},
		{metric: "test[anything,b,]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestEmptySecondParameterValue(t *testing.T) {
	var records accessRules

	records.addRule("test[a,,c]", DENY)

	var scenarios = []scenario{
		{metric: "test[a,,c]", result: false},
		{metric: "test[a, ,c]", result: false},
		{metric: "test[a,b,c]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestAnySecondParameterValue(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents[/var/log/zabbix_server.log,*,abc]", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/var/log/zabbix_server.log,,abc]", result: false},
		{metric: "vfs.file.contents[/var/log/zabbix_server.log,utf8,abc]", result: false},
		{metric: "vfs.file.contents[/var/log/zabbix_server.log,,abc,def]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestSpecificParameters(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents[/etc/passwd,utf8]", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd,utf8]", result: false},
		{metric: "vfs.file.contents[/etc/sudoers,utf8]", result: true},
		{metric: "vfs.file.contents[/etc/passwd,]", result: true},
		{metric: "vfs.file.contents[/etc/passwd,utf16]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestQuotedParameters(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents[/etc/passwd,utf8]", DENY)
	records.addRule("system.run[*]", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[\"/etc/passwd\",\"utf8\"]", result: false},
		{metric: "vfs.file.contents[\"/etc/passwd\",\"\"]", result: true},
		{metric: "vfs.file.contents[\"/etc/passwd\",\"utf16\"]", result: true},
		{metric: "system.run[\"echo 1\"]", result: false},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestKeyPatternWithoutParameters(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents", result: false},
		{metric: "vfs.file.size", result: false},
		{metric: "vfs.file.contents[]", result: true},
		{metric: "vfs.file.size[/var/log/zabbix_server.log]", result: true},
		{metric: "vfs.dev.list", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestKeyPatternWithAnyParameters(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*[*]", DENY)
	records.addRule("vfs.*.contents", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.size.bytes[]", result: false},
		{metric: "vfs.file.size[/var/log/zabbix_server.log, utf8]", result: false},
		{metric: "vfs.file.size.bytes", result: true},
		{metric: "vfs.mount.point.file.contents", result: false},
		{metric: "vfs..contents", result: false},
		{metric: "vfs.contents", result: true},
	}

	RunScenarios(t, scenarios, records, 3)
}

func TestWhitelist(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*[/var/log/*]", ALLOW)
	records.addRule("system.localtime[*]", ALLOW)
	records.addRule("system.localtime[*]", DENY) // Will not be added
	records.addRule("*", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.size[/var/log/zabbix_server.log]", result: true},
		{metric: "vfs.file.contents[/var/log/zabbix_server.log]", result: true},
		{metric: "vfs.file.contents[/tmp/zabbix_server.log]", result: false},
		{metric: "system.localtime[]", result: true},
		{metric: "system.localtime[utc]", result: true},
		{metric: "system.localtime", result: false},
	}

	RunScenarios(t, scenarios, records, 3)
}

func TestBlacklist(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.contents[/etc/passwd,*]", DENY)
	records.addRule("system.run[*]", DENY)

	var scenarios = []scenario{
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
	var records accessRules

	records.addRule("t*t*[a]", DENY)

	var scenarios = []scenario{
		{metric: "test1[a]", result: false},
		{metric: "tt1[a]", result: false},
		{metric: "tet[a]", result: false},
		{metric: "tt[a]", result: false},
		{metric: "test_best2[a]", result: false},
		{metric: "tests[a]", result: false},
		{metric: "test[a]", result: false},
		{metric: "best[a]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

func TestDuplicateRules(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*", DENY)
	records.addRule("vfs.file.*", DENY) // Will not be added
	records.addRule("vfs.file.contents", DENY)
	records.addRule("vfs.file.contents[]", DENY)
	records.addRule("vfs.file.contents[/etc/passwd]", DENY)
	records.addRule("vfs.file.contents[/etc/passwd,*]", DENY)
	records.addRule("vfs.file.*", ALLOW)                       // Will not be added
	records.addRule("vfs.file.contents", ALLOW)                // Will not be added
	records.addRule("vfs.file.contents[]", ALLOW)              // Will not be added
	records.addRule("vfs.file.contents[/etc/passwd]", ALLOW)   // Will not be added
	records.addRule("vfs.file.contents[/etc/passwd,*]", ALLOW) // Will not be added
	records.addRule("net.*.in", ALLOW)
	records.addRule("net.*.in", ALLOW) // Will not be added
	records.addRule("net.*.in[]", ALLOW)
	records.addRule("net.*.in[eth0]", ALLOW)
	records.addRule("net.*.in[eth0,*]", ALLOW)
	records.addRule("net.*.in", DENY)         // Will not be added
	records.addRule("net.*.in[]", DENY)       // Will not be added
	records.addRule("net.*.in[eth0]", DENY)   // Will not be added
	records.addRule("net.*.in[eth0,*]", DENY) // Will not be added
	records.addRule("net.*.in[eth0,bytes]", DENY)
	records.addRule("*", DENY)

	var scenarios = []scenario{
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
	var records accessRules

	records.addRule("vfs.file.*[*]", DENY)
	records.addRule("*", ALLOW)            // Will not be added
	records.addRule("system.run[*]", DENY) // Will not be added

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.size[/etc/systemd.conf]", result: false},
		{metric: "system.run[echo 1]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestNoRulesAfterDenyAll(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*[*]", ALLOW)
	records.addRule("*", DENY)
	records.addRule("system.run[*]", ALLOW) // Will not be added

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: true},
		{metric: "vfs.file.size[/etc/systemd.conf]", result: true},
		{metric: "system.run[echo 1]", result: false},
		{metric: "system.localtime", result: false},
	}

	RunScenarios(t, scenarios, records, 2)
}

//nolint:paralleltest
func TestNoRulesAfterAllowAllRegexp(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*[*]", DENY)
	records.addRegexpRule(".*", ALLOW)     // Will not be added
	records.addRule("system.run[*]", DENY) // Will not be added

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.size[/etc/systemd.conf]", result: false},
		{metric: "system.run[echo 1]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

//nolint:paralleltest
func TestNoRulesAfterDenyAllRegexp(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*[*]", ALLOW)
	records.addRegexpRule(".*", DENY)
	records.addRule("system.run[*]", ALLOW) // Will not be added

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: true},
		{metric: "vfs.file.size[/etc/systemd.conf]", result: true},
		{metric: "system.run[echo 1]", result: false},
		{metric: "system.localtime", result: false},
	}

	RunScenarios(t, scenarios, records, 2)
}

//nolint:paralleltest
func TestNonCanonicalMatchAllRegexpIsNotTrimmed(t *testing.T) {
	var records accessRules

	records.addRegexpRule("^.*$", ALLOW)
	records.addRule("vfs.file.*[*]", DENY) // Not trimmed, although unreachable

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: true},
		{metric: "system.run[echo 1]", result: true},
	}

	RunScenarios(t, scenarios, records, 3)
}

func TestIncompleteWhitelist(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*[/var/log/*]", ALLOW)
	records.addRule("system.localtime[*]", ALLOW)
	// Trailing DenyKey=* is missing

	err := LoadRules(
		&records.allowRecords, &records.denyRecords,
		&records.allowRegexpRecords, &records.denyRegexpRecords,
	)

	if err == nil {
		t.Errorf("Failure expected while loading incomplete whitelist")
	}
}

func TestNoTrailingAllowRules(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*[*]", DENY)
	records.addRule("system.run[*]", ALLOW) // Will not be added
	records.addRule("*", ALLOW)             // Will not be added

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.size[/etc/systemd.conf]", result: false},
		{metric: "system.run[echo 1]", result: true},
		{metric: "system.localtime", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

//nolint:paralleltest
func TestTrailingAllowRuleTrimmedAfterSystemRunAllow(t *testing.T) {
	var records accessRules

	records.addRule("system.*", ALLOW)
	records.addRule("system.run[*]", ALLOW)

	var scenarios = []scenario{
		{metric: "system.run[/usr/bin/hostname -f]", result: true},
		{metric: "system.localtime", result: true},
		{metric: "vfs.file.contents[/etc/passwd]", result: true},
	}

	RunScenarios(t, scenarios, records, 1)
}

func TestEmptyParametersMatch(t *testing.T) {
	var records accessRules

	records.addRule("web.page.get[localhost,*,*]", DENY)

	var scenarios = []scenario{
		{metric: "web.page.get[localhost]", result: false},
		{metric: "web.page.get[localhost,]", result: false},
		{metric: "web.page.get[localhost,/,80]", result: false},
		{metric: "web.page.get[localhost,/]", result: false},
		{metric: "web.page.get[localhost,,80]", result: false},
		{metric: "web.page.get[127.0.0.1]", result: true},
	}

	RunScenarios(t, scenarios, records, 2)
}

//nolint:paralleltest
func TestRegexpHostnameVariants(t *testing.T) {
	var records accessRules

	records.addRegexpRule(`system\.run\[(?:/usr)?/bin/hostname(?: -[fsd])?\]`, ALLOW)

	var scenarios = []scenario{
		{metric: "system.run[/bin/hostname]", result: true},
		{metric: "system.run[/bin/hostname -f]", result: true},
		{metric: "system.run[/bin/hostname -s]", result: true},
		{metric: "system.run[/bin/hostname -d]", result: true},
		{metric: "system.run[/usr/bin/hostname]", result: true},
		{metric: "system.run[/usr/bin/hostname -f]", result: true},
		{metric: "system.run[/usr/bin/hostname -s]", result: true},
		{metric: "system.run[/usr/bin/hostname -d]", result: true},
		{metric: "system.run[/bin/hostname -x]", result: false},
		{metric: "system.run[echo 1]", result: false},
	}

	RunScenarios(t, scenarios, records, 2)
}

//nolint:paralleltest
func TestRegexpServiceStartRestart(t *testing.T) {
	var records accessRules

	records.addRegexpRule(`system\.run\[sc (?:re)?start ABC[1-4]\]`, ALLOW)

	var scenarios = []scenario{
		{metric: "system.run[sc start ABC1]", result: true},
		{metric: "system.run[sc start ABC2]", result: true},
		{metric: "system.run[sc start ABC3]", result: true},
		{metric: "system.run[sc start ABC4]", result: true},
		{metric: "system.run[sc restart ABC1]", result: true},
		{metric: "system.run[sc restart ABC2]", result: true},
		{metric: "system.run[sc restart ABC3]", result: true},
		{metric: "system.run[sc restart ABC4]", result: true},
		{metric: "system.run[sc stop ABC1]", result: false},
		{metric: "system.run[sc start ABC5]", result: false},
		{metric: "system.run[echo 1]", result: false},
	}

	RunScenarios(t, scenarios, records, 2)
}

//nolint:paralleltest
func TestMixedWildcardAndRegexp(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*[*]", ALLOW)
	records.addRegexpRule(`system\.run\[/bin/hostname\]`, ALLOW)
	records.addRule("*", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.size[/tmp/x]", result: true},
		{metric: "vfs.file.contents[/etc/passwd]", result: true},
		{metric: "system.run[/bin/hostname]", result: true},
		{metric: "system.run[echo 1]", result: false},
		{metric: "system.localtime", result: false},
	}

	RunScenarios(t, scenarios, records, 3)
}

//nolint:paralleltest
func TestRegexpDenyBeforeWildcardAllow(t *testing.T) {
	var records accessRules

	records.addRegexpRule(`vfs\.file\.contents\[/etc/passwd\]`, DENY)
	records.addRule("vfs.file.*[*]", ALLOW)
	records.addRule("*", DENY)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "vfs.file.contents[/tmp/test]", result: true},
		{metric: "vfs.file.size[/tmp/x]", result: true},
		{metric: "system.localtime", result: false},
	}

	RunScenarios(t, scenarios, records, 3)
}

//nolint:paralleltest
func TestRegexpExplicitAnchorsEnforceFullStringMatch(t *testing.T) {
	var records accessRules

	// user-supplied patterns are not auto-anchored; use ^...$ for full-string match
	records.addRegexpRule(`^system\.run$`, ALLOW)
	records.addRule("*", DENY)

	var scenarios = []scenario{
		{metric: "system.run", result: true},
		{metric: "system.run[echo 1]", result: false},
		{metric: "system.run[/bin/hostname]", result: false},
		{metric: "vfs.file.size[/tmp/x]", result: false},
	}

	RunScenarios(t, scenarios, records, 2)
}

//nolint:paralleltest
func TestRegexpInvalidPattern(t *testing.T) {
	var records accessRules

	records.addRegexpRule(`[invalid`, DENY)

	err := LoadRules(
		&records.allowRecords, &records.denyRecords,
		&records.allowRegexpRecords, &records.denyRegexpRecords,
	)
	if err == nil {
		t.Errorf("Expected error for invalid regexp pattern, got nil")
	}
}

//nolint:paralleltest
func TestRegexpEmptyPattern(t *testing.T) {
	var records accessRules

	records.addRegexpRule(``, DENY)

	err := LoadRules(
		&records.allowRecords, &records.denyRecords,
		&records.allowRegexpRecords, &records.denyRegexpRecords,
	)
	if err == nil {
		t.Errorf("Expected error for empty regexp pattern, got nil")
	}
}

//nolint:paralleltest
func TestRegexpDuplicatePattern(t *testing.T) {
	var records accessRules

	records.addRegexpRule(`system\.run\[.*\]`, DENY)
	records.addRegexpRule(`system\.run\[.*\]`, DENY) // exact duplicate: will not be added
	records.addRule("*", DENY)

	var scenarios = []scenario{
		{metric: "system.run[echo 1]", result: false},
		{metric: "system.localtime", result: false},
	}

	RunScenarios(t, scenarios, records, 2)
}

//nolint:paralleltest
func TestRegexpConflictingPattern(t *testing.T) {
	var records accessRules

	records.addRegexpRule(`system\.run\[.*\]`, DENY)
	records.addRegexpRule(`system\.run\[.*\]`, ALLOW) // conflicts with: will not be added
	records.addRule("*", DENY)

	var scenarios = []scenario{
		{metric: "system.run[echo 1]", result: false},
		{metric: "system.run[/bin/hostname]", result: false},
		{metric: "system.localtime", result: false},
	}

	RunScenarios(t, scenarios, records, 2)
}

//nolint:paralleltest
func TestNoTrailingAllowRulesWithRegexp(t *testing.T) {
	var records accessRules

	records.addRule("vfs.file.*[*]", DENY)
	records.addRegexpRule(`system\.localtime`, ALLOW)

	var scenarios = []scenario{
		{metric: "vfs.file.contents[/etc/passwd]", result: false},
		{metric: "system.localtime", result: true},
		{metric: "system.run[echo 1]", result: false},
	}

	RunScenarios(t, scenarios, records, 3)
}
