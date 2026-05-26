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
	"errors"
	"fmt"
	"math"
	"regexp"
	"sort"

	"golang.zabbix.com/agent2/pkg/wildcard"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin/itemutil"
)

var (
	errInvalidRule                 = errors.New("invalid key access rule")
	errRegexpPatternMustNotBeEmpty = errors.New("regular expression pattern must not be empty")
)

// RuleType Access rule permission type
type RuleType int

// Rule access types
const (
	ALLOW RuleType = iota
	DENY
)

func (t RuleType) String() string {
	switch t {
	case ALLOW:
		return "AllowKey"
	case DENY:
		return "DenyKey"
	default:
		return "unknown"
	}
}

// ruleTypeName returns the config parameter name corresponding to a rule's type and kind.
func ruleTypeName(permission RuleType, isRegexp bool) string {
	if isRegexp {
		if permission == ALLOW {
			return "AllowKeyRegexp"
		}

		return "DenyKeyRegexp"
	}

	return permission.String()
}

// Record key access record
type Record struct {
	Pattern    string
	Permission RuleType
	IsRegexp   bool
	Line       int
}

// Rule key access rule definition
type Rule struct {
	Pattern    string
	Permission RuleType
	IsRegexp   bool
	Key        string
	Params     []string
	Regexp     *regexp.Regexp
}

var rules []*Rule

func parse(rec Record) (r *Rule, err error) {
	r = &Rule{
		Permission: rec.Permission,
		Pattern:    rec.Pattern,
		IsRegexp:   rec.IsRegexp,
	}

	if rec.IsRegexp {
		if rec.Pattern == "" {
			return nil, errRegexpPatternMustNotBeEmpty
		}

		r.Regexp, err = regexp.Compile(rec.Pattern)
		if err != nil {
			return nil, fmt.Errorf("failed to compile regular expression: %w", err)
		}

		return r, nil
	}

	if r.Key, r.Params, err = itemutil.ParseWildcardKey(rec.Pattern); err != nil {
		return nil, err
	}

	r.Key = wildcard.Minimize(r.Key)

	for i := range r.Params {
		r.Params[i] = wildcard.Minimize(r.Params[i])
	}
	// remove repeated trailing "*" parameters
	var n int = 0
	for i := len(r.Params) - 1; i >= 0; i-- {
		if r.Params[i] == "*" {
			n++
		}
	}
	if n > 1 {
		r.Params = r.Params[:len(r.Params)-n+1]
	}

	return r, nil
}

// findRule returns a matching rule from the current list.
// Regexp rules match by pattern; wildcard rules match by parsed key and params.
// Regexp and wildcard rules are compared separately.
func findRule(proto *Rule) (rule *Rule, index int) {
	for j, r := range rules {
		if proto.IsRegexp != r.IsRegexp {
			continue
		}

		if proto.IsRegexp {
			if proto.Pattern == r.Pattern {
				return r, j
			}

			continue
		}
		if proto.Key != r.Key || len(proto.Params) != len(r.Params) {
			continue
		}
		for i, p := range proto.Params {
			if p != r.Params[i] {
				goto noMatch
			}
		}
		return r, j
	noMatch:
	}
	return
}

func addRule(rec Record) (err error) {
	var rule *Rule

	if rule, err = parse(rec); err != nil {
		return
	}
	if r, _ := findRule(rule); r != nil {
		var desc string
		if r.Permission == rule.Permission {
			desc = "duplicates"
		} else {
			desc = "conflicts"
		}
		log.Warningf(`%s access rule "%s" was not added because it %s with another rule defined above`,
			ruleTypeName(rec.Permission, rec.IsRegexp), rec.Pattern, desc)
		return
	}
	rules = append(rules, rule)
	return
}

func appendRecordsFromNode(records *[]Record, node any, permission RuleType, isRegexp bool) {
	if cfgNode, ok := node.(*conf.Node); ok {
		for _, v := range cfgNode.Nodes {
			if value, ok := v.(*conf.Value); ok {
				*records = append(*records, Record{
					Pattern:    string(value.Value),
					Permission: permission,
					IsRegexp:   isRegexp,
					Line:       value.Line,
				})
			}
		}
	}
}

func addConfiguredRules(records []Record) error {
	for _, r := range records {
		if err := addRule(r); err != nil {
			return fmt.Errorf(
				"%w: %s %q %s",
				errInvalidRule,
				ruleTypeName(r.Permission, r.IsRegexp),
				r.Pattern,
				err.Error(),
			)
		}
	}

	return nil
}

func prepareSystemRunRule() (*Rule, int, int, error) {
	sysrunIndex := math.MaxInt32
	rulesNum := len(rules)

	// create system.run[*] deny rule to be appended at the end of rule list unless other
	// system.run[*] rules are present
	sysrunRule, err := parse(Record{Pattern: "system.run[*]", Permission: DENY, Line: 0})
	if err != nil {
		return nil, sysrunIndex, rulesNum, err
	}

	if r, i := findRule(sysrunRule); r != nil {
		sysrunIndex = i
		rulesNum--
	}

	return sysrunRule, sysrunIndex, rulesNum, nil
}

func trimRulesAfterMatchAll(sysrunIndex *int) {
	for i, r := range rules {
		if !r.IsRegexp && len(r.Params) == 0 && r.Key == "*" {
			if i < *sysrunIndex {
				*sysrunIndex = i
			}

			for j := i + 1; j < len(rules); j++ {
				log.Warningf(`removed unreachable %s "%s" rule`,
					ruleTypeName(rules[j].Permission, rules[j].IsRegexp), rules[j].Pattern)
			}

			rules = rules[:i+1]

			return
		}
	}
}

func trimTrailingAllowRules(sysrunIndex int) {
	cutoff := len(rules)

	for i := len(rules) - 1; i >= 0; i-- {
		if rules[i].Permission != ALLOW {
			break
		}

		if rules[i].IsRegexp {
			break
		}
		// system.run allow rules are not redundant because of default system.run[*] deny rule
		if rules[i].Key != "system.run" {
			if i != sysrunIndex {
				log.Warningf(`removed redundant trailing AllowKey "%s" rule`, rules[i].Pattern)
			}

			for j := i; j < len(rules)-1; j++ {
				rules[j] = rules[j+1]
			}

			cutoff--
		}
	}

	rules = rules[:cutoff]
}

// GetNumberOfRules returns a number of access rules configured.
func GetNumberOfRules() int {
	return len(rules)
}

// LoadRules adds key access records to access rule list.
// LoadRules merges AllowKey/DenyKey/AllowKeyRegexp/DenyKeyRegexp in config order.
// Regexp patterns are compiled at load time; invalid or empty patterns fail loading.
func LoadRules(allowRecords, denyRecords, allowRegexpRecords, denyRegexpRecords any) error {
	rules = rules[:0]
	records := make([]Record, 0)

	appendRecordsFromNode(&records, allowRecords, ALLOW, false)
	appendRecordsFromNode(&records, denyRecords, DENY, false)
	appendRecordsFromNode(&records, allowRegexpRecords, ALLOW, true)
	appendRecordsFromNode(&records, denyRegexpRecords, DENY, true)

	sort.SliceStable(records, func(i, j int) bool {
		return records[i].Line < records[j].Line
	})

	if err := addConfiguredRules(records); err != nil {
		return err
	}

	sysrunRule, sysrunIndex, rulesNum, err := prepareSystemRunRule()
	if err != nil {
		return err
	}

	if rulesNum != 0 {
		trimRulesAfterMatchAll(&sysrunIndex)
		trimTrailingAllowRules(sysrunIndex)

		if len(rules) == 0 {
			return errors.New("Item key access rules are configured to match all keys," +
				" indicating possible configuration problem. " +
				" Please remove the rules if that was the purpose.")
		}
	}

	if sysrunIndex == math.MaxInt32 {
		rules = append(rules, sysrunRule)
	}

	return nil
}

// matchWildcardRule evaluates a wildcard rule and reports whether it matched and, if so, whether it allows access.
func matchWildcardRule(r *Rule, key string, params []string, emptyParams bool) (bool, bool) {
	numParamsRule := len(r.Params)
	numParams := len(params)

	// match all rule
	if r.Key == "*" && numParamsRule == 0 {
		return true, r.Permission == ALLOW
	}

	if !wildcardParamCountMatches(r, numParamsRule, numParams) {
		return false, false
	}

	if !wildcard.Match(key, r.Key) {
		return false, false // key doesn't match
	}

	if numParamsRule == 0 {
		if emptyParams {
			return false, false // no parameters expected by rule
		}

		if numParams == 0 {
			return true, r.Permission == ALLOW
		}
	}

	if !wildcardParamsMatch(r, params, numParamsRule, numParams) {
		return false, false
	}

	return true, r.Permission == ALLOW
}

func wildcardParamCountMatches(r *Rule, numParamsRule, numParams int) bool {
	if numParamsRule == 0 {
		return true
	}

	if r.Params[numParamsRule-1] == "*" {
		// rule: key[*], request: key
		return numParamsRule != 1 || numParams != 0
	}

	if numParams < numParamsRule {
		return false // too few parameters
	}

	if numParams > numParamsRule {
		return false // too many params
	}

	return true
}

func wildcardParamsMatch(r *Rule, params []string, numParamsRule, numParams int) bool {
	for i, p := range r.Params {
		if i == numParamsRule-1 { // last parameter
			if p == "*" {
				return true // skip next parameter checks
			}

			if numParams <= i {
				return false // out of parameters
			}

			return wildcard.Match(params[i], p)
		}

		if numParams <= i || !wildcard.Match(params[i], p) {
			return false // parameter doesn't match pattern
		}
	}

	return false
}

// CheckRules checks if specified key and parameters are not restricted by defined rules.
func CheckRules(rawMetric, key string, params []string) bool {
	emptyParams := len(params) == 1 && params[0] == ""

	for _, r := range rules {
		if r.IsRegexp {
			if r.Regexp.MatchString(rawMetric) {
				return r.Permission == ALLOW
			}

			continue
		}

		matched, allowed := matchWildcardRule(r, key, params, emptyParams)
		if matched {
			return allowed
		}
	}

	return true // allow by default for backward compatibility
}
