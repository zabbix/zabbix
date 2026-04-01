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
	errInvalidRule = errors.New("invalid key access rule")
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
		// Wrap in non-capturing group and anchor to enforce full-string matching (RE2 compatible).
		anchored := "^(?:" + rec.Pattern + ")$"
		r.Regexp, err = regexp.Compile(anchored)
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

// GetNumberOfRules returns a number of access rules configured
func GetNumberOfRules() int {
	return len(rules)
}

// LoadRules adds key access records to access rule list.
// LoadRules merges AllowKey/DenyKey/AllowKeyRegexp/DenyKeyRegexp in config order.
// Regexp patterns are compiled at load time; invalid or empty patterns fail loading.
func LoadRules(allowRecords, denyRecords, allowRegexpRecords, denyRegexpRecords interface{}) (err error) {
	rules = rules[:0]
	var records []Record
	sysrunIndex := math.MaxInt32

	// load AllowKey/DenyKey parameters
	if node, ok := allowRecords.(*conf.Node); ok {
		for _, v := range node.Nodes {
			if value, ok := v.(*conf.Value); ok {
				records = append(records, Record{Pattern: string(value.Value),
					Permission: ALLOW, Line: value.Line})
			}
		}
	}
	if node, ok := denyRecords.(*conf.Node); ok {
		for _, v := range node.Nodes {
			if value, ok := v.(*conf.Value); ok {
				records = append(records, Record{Pattern: string(value.Value),
					Permission: DENY, Line: value.Line})
			}
		}
	}
	// load AllowKeyRegexp/DenyKeyRegexp parameters
	if node, ok := allowRegexpRecords.(*conf.Node); ok {
		for _, v := range node.Nodes {
			if value, ok := v.(*conf.Value); ok {
				records = append(records, Record{Pattern: string(value.Value),
					Permission: ALLOW, IsRegexp: true, Line: value.Line})
			}
		}
	}
	if node, ok := denyRegexpRecords.(*conf.Node); ok {
		for _, v := range node.Nodes {
			if value, ok := v.(*conf.Value); ok {
				records = append(records, Record{Pattern: string(value.Value),
					Permission: DENY, IsRegexp: true, Line: value.Line})
			}
		}
	}

	sort.SliceStable(records, func(i, j int) bool {
		return records[i].Line < records[j].Line
	})

	for _, r := range records {
		if err = addRule(r); err != nil {
			err = fmt.Errorf(
				"%w: %s %q %s",
				errInvalidRule,
				ruleTypeName(r.Permission, r.IsRegexp),
				r.Pattern,
				err.Error(),
			)
			return
		}
	}

	rulesNum := len(rules)
	// create system.run[*] deny rule to be appended at the end of rule list unless other
	// system.run[*] rules are present
	sysrunRule, err := parse(Record{Pattern: "system.run[*]", Permission: DENY, Line: 0})
	if err != nil {
		return
	}
	if r, i := findRule(sysrunRule); r != nil {
		sysrunIndex = i
		rulesNum--
	}

	if rulesNum != 0 {
		// remove rules after 'full match' rule
		for i, r := range rules {
			if !r.IsRegexp && len(r.Params) == 0 && r.Key == "*" {
				if i < sysrunIndex {
					sysrunIndex = i
				}
				for j := i + 1; j < len(rules); j++ {
					log.Warningf(`removed unreachable %s "%s" rule`,
						ruleTypeName(rules[j].Permission, rules[j].IsRegexp), rules[j].Pattern)
				}
				rules = rules[:i+1]
				break
			}
		}

		// remove trailing 'allow' rules
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

// CheckRules checks if specified key and parameters are not restricted by defined rules
func CheckRules(rawMetric string, key string, params []string) (result bool) {
	result = true

	emptyParams := len(params) == 1 && len(params[0]) == 0

	for _, r := range rules {
		if r.IsRegexp {
			if r.Regexp.MatchString(rawMetric) {
				return r.Permission == ALLOW
			}

			continue
		}

		numParamsRule := len(r.Params)
		numParams := len(params)

		// match all rule
		if r.Key == "*" && numParamsRule == 0 {
			return r.Permission == ALLOW
		}

		if numParamsRule > 0 {
			if r.Params[numParamsRule-1] == "*" {
				if numParamsRule == 1 && numParams == 0 {
					continue // rule: key[*], request: key
				}
			} else {
				if numParams < numParamsRule {
					continue // too few parameters
				}
				if numParams > numParamsRule {
					continue // too many params
				}
			}
		}

		if !wildcard.Match(key, r.Key) {
			continue // key doesn't match
		}

		if numParamsRule == 0 {
			if emptyParams {
				continue // no parameters expected by rule
			}
			if numParams == 0 {
				return r.Permission == ALLOW
			}
		}

		for i, p := range r.Params {
			if i == numParamsRule-1 { // last parameter
				if p == "*" {
					return r.Permission == ALLOW // skip next parameter checks
				}
				if numParams <= i {
					break // out of parameters
				}
				if !wildcard.Match(params[i], p) {
					break // parameter doesn't match pattern
				}
				return r.Permission == ALLOW
			}
			if numParams <= i || !wildcard.Match(params[i], p) {
				break // parameter doesn't match pattern
			}
		}
	}

	return true // allow by default for backward compatibility
}
