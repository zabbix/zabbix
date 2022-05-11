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

package keyaccess

import (
	"errors"
	"fmt"
	"math"
	"sort"

	"git.zabbix.com/ap/plugin-support/conf"
	"git.zabbix.com/ap/plugin-support/log"
	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/wildcard"
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

// Record key access record
type Record struct {
	Pattern    string
	Permission RuleType
	Line       int
}

// Rule key access rule definition
type Rule struct {
	Pattern    string
	Permission RuleType
	Key        string
	Params     []string
}

var rules []*Rule

func parse(rec Record) (r *Rule, err error) {
	r = &Rule{
		Permission: rec.Permission,
		Pattern:    rec.Pattern,
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

func findRule(proto *Rule) (rule *Rule, index int) {
	for j, r := range rules {
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
			rec.Permission, rec.Pattern, desc)
		return
	}
	rules = append(rules, rule)
	return
}

// GetNumberOfRules returns a number of access rules configured
func GetNumberOfRules() int {
	return len(rules)
}

// LoadRules adds key access records to access rule list
func LoadRules(allowRecords interface{}, denyRecords interface{}) (err error) {
	rules = rules[:0]
	var records []Record
	sysrunIndex := math.MaxInt32

	// load AllowKey/DenyKey parameters
	if node, ok := allowRecords.(*conf.Node); ok {
		for _, v := range node.Nodes {
			if value, ok := v.(*conf.Value); ok {
				records = append(records, Record{Pattern: string(value.Value), Permission: ALLOW, Line: value.Line})
			}
		}
	}
	if node, ok := denyRecords.(*conf.Node); ok {
		for _, v := range node.Nodes {
			if value, ok := v.(*conf.Value); ok {
				records = append(records, Record{Pattern: string(value.Value), Permission: DENY, Line: value.Line})
			}
		}
	}

	sort.SliceStable(records, func(i, j int) bool {
		return records[i].Line < records[j].Line
	})

	for _, r := range records {
		if err = addRule(r); err != nil {
			err = fmt.Errorf("\"%s\" %s", r.Pattern, err.Error())
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
			if len(r.Params) == 0 && r.Key == "*" {
				if i < sysrunIndex {
					sysrunIndex = i
				}
				for j := i + 1; j < len(rules); j++ {
					log.Warningf(`removed unreachable %s "%s" rule`, rules[j].Permission, rules[j].Pattern)
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
func CheckRules(key string, params []string) (result bool) {
	result = true

	emptyParams := len(params) == 1 && len(params[0]) == 0

	for _, r := range rules {
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
