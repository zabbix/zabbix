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
	"fmt"
	"sort"

	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/wildcard"
)

// RuleType Access rule permission type
type RuleType int

// Rule access types
const (
	ALLOW RuleType = iota
	DENY
)

// Record key access record
type Record struct {
	Pattern    string
	Permission RuleType
	Line       int
}

// Rule key access rule definition
type Rule struct {
	Permission RuleType
	Key        string
	Params     []string
}

var rules []*Rule
var noMoreRules bool = false

func parse(rec Record) (r *Rule, err error) {
	r = &Rule{}
	r.Permission = rec.Permission

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

func findRule(rule *Rule) *Rule {
	for _, r := range rules {
		if rule.Key != r.Key || len(rule.Params) != len(r.Params) {
			continue
		}
		for i, p := range rule.Params {
			if p != r.Params[i] {
				goto noMatch
			}
		}
		return r
	noMatch:
	}
	return nil
}

func addRule(rec Record) (err error) {
	var rule *Rule

	if rule, err = parse(rec); err != nil {
		return
	} else if !noMoreRules {
		if r := findRule(rule); r != nil {
			var ruleType string
			if rule.Permission == ALLOW {
				ruleType = "AllowKey"
			} else {
				ruleType = "DenyKey"
			}
			if r.Permission == rule.Permission {
				log.Warningf("key access rule \"%s=%s\" duplicates another rule defined above", ruleType, rec.Pattern)
			} else {
				log.Warningf("key access rule \"%s=%s\" conflicts with another rule defined above", ruleType, rec.Pattern)
			}
		} else if len(rule.Params) == 0 && rule.Key == "*" {
			if rule.Permission == DENY {
				rules = append(rules, rule)
			}
			noMoreRules = true // any rules after "allow/deny all" are meaningless
		} else {
			rules = append(rules, rule)
		}
	}
	return nil
}

// GetNumberOfRules returns a number of access rules configured
func GetNumberOfRules() int {
	return len(rules)
}

// LoadRules adds key access records to access rule list
func LoadRules(allowRecords interface{}, denyRecords interface{}) (err error) {
	rules = rules[:0]
	noMoreRules = false

	var records []Record

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

	var allowRules, denyRules int = 0, 0
	for _, r := range rules {
		switch r.Permission {
		case ALLOW:
			allowRules++
		case DENY:
			denyRules++
		}
	}

	if allowRules > 0 && denyRules == 0 {
		err = fmt.Errorf("\"AllowKey\" without \"DenyKey\" rules are meaningless")
		return
	} else {
		// trailing AllowKey rules are meaningless, because AllowKey=* is default behavior
		for i := len(rules) - 1; i >= 0; i-- {
			r := rules[i]
			if r.Permission == DENY {
				break
			}
			rules = rules[:len(rules)-1]
		}
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
