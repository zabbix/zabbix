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
	"cmp"
	"errors"
	"fmt"
	"math"
	"regexp"
	"slices"

	"golang.zabbix.com/agent2/pkg/wildcard"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin/itemutil"
)

const (
	// matchAllRegexpPattern is the only regexp form treated as unconditional match-all for rule trimming.
	matchAllRegexpPattern = ".*"
)

// PatternWildcard and PatternRegexp are pattern kinds (C: zbx_key_access_pattern_type_t).
const (
	PatternWildcard = iota
	PatternRegexp
)

// ALLOW and DENY are AllowKey and DenyKey rule permissions.
const (
	ALLOW = iota
	DENY
)

var (
	errInvalidRule                 = errors.New("invalid key access rule")
	errRegexpPatternMustNotBeEmpty = errors.New("regular expression pattern must not be empty")

	//nolint:gochecknoglobals // rules are loaded once and used across checks.
	rules []*Rule
)

// PatternType key access rule pattern.
type PatternType int

// RuleType Access rule permission type.
type RuleType int

// BasePattern holds the common fields shared by Record and Rule.
type BasePattern struct {
	Pattern     string
	Permission  RuleType
	PatternType PatternType
}

// Record is a key access record.
type Record struct {
	BasePattern

	Line int
}

// Rule is a key access rule definition.
type Rule struct {
	BasePattern

	Key    string
	Params []string
	Regexp *regexp.Regexp
}

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

// permissionName returns the config parameter name corresponding to a pattern's permission type and kind.
func (b BasePattern) permissionName() string {
	if b.PatternType == PatternRegexp {
		if b.Permission == ALLOW {
			return "AllowKeyRegexp"
		}

		return "DenyKeyRegexp"
	}

	return b.Permission.String()
}

func (r Record) permissionName() string {
	return r.BasePattern.permissionName()
}

func (r *Rule) permissionName() string {
	if r == nil {
		return "unknown"
	}

	return r.BasePattern.permissionName()
}

func parse(rec Record) (r *Rule, err error) {
	r = &Rule{
		BasePattern: rec.BasePattern,
	}

	if rec.PatternType == PatternRegexp {
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
		if proto.PatternType != r.PatternType {
			continue
		}

		if proto.PatternType == PatternRegexp {
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
			rec.permissionName(), rec.Pattern, desc)
		return
	}
	rules = append(rules, rule)
	return
}

func appendRecordsFromNode(records *[]Record, node any, permission RuleType, patternType PatternType) {
	if cfgNode, ok := node.(*conf.Node); ok {
		for _, v := range cfgNode.Nodes {
			if value, ok := v.(*conf.Value); ok {
				*records = append(*records, Record{
					BasePattern: BasePattern{
						Pattern:     string(value.Value),
						Permission:  permission,
						PatternType: patternType,
					},
					Line: value.Line,
				})
			}
		}
	}
}

func addConfiguredRules(records []Record) error {
	for _, r := range records {
		err := addRule(r)
		if err != nil {
			return fmt.Errorf(
				"%w: %s %q %s",
				errInvalidRule,
				r.permissionName(),
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
	sysrunRule, err := parse(Record{
		BasePattern: BasePattern{
			Pattern:     "system.run[*]",
			Permission:  DENY,
			PatternType: PatternWildcard,
		},
	})
	if err != nil {
		return nil, sysrunIndex, rulesNum, err
	}

	if r, i := findRule(sysrunRule); r != nil {
		sysrunIndex = i
		rulesNum--
	}

	return sysrunRule, sysrunIndex, rulesNum, nil
}

func isUnconditionalMatchAll(r *Rule) bool {
	if r.PatternType == PatternRegexp {
		return r.Pattern == matchAllRegexpPattern
	}

	return len(r.Params) == 0 && r.Key == "*"
}

func trimRulesAfterMatchAll(sysrunIndex *int) {
	for i, r := range rules {
		if !isUnconditionalMatchAll(r) {
			continue
		}

		if i < *sysrunIndex {
			*sysrunIndex = i
		}

		for j := i + 1; j < len(rules); j++ {
			log.Warningf(`removed unreachable %s "%s" rule`, rules[j].permissionName(), rules[j].Pattern)
		}

		rules = rules[:i+1]

		return
	}
}

func trimTrailingAllowRules(sysrunIndex int) {
	cutoff := len(rules)

	for i := len(rules) - 1; i >= 0; i-- {
		r := rules[i]
		if r.Permission != ALLOW {
			break
		}

		if !isUnconditionalMatchAll(r) {
			if r.PatternType == PatternRegexp {
				break
			}

			if r.Key == "system.run" {
				// system.run allow rules are not redundant because of default system.run[*] deny rule
				continue
			}
		}

		if i != sysrunIndex {
			log.Warningf(`removed redundant trailing %s "%s" rule`, r.permissionName(), r.Pattern)
		}

		for j := i; j < len(rules)-1; j++ {
			rules[j] = rules[j+1]
		}

		cutoff--
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

	appendRecordsFromNode(&records, allowRecords, ALLOW, PatternWildcard)
	appendRecordsFromNode(&records, denyRecords, DENY, PatternWildcard)
	appendRecordsFromNode(&records, allowRegexpRecords, ALLOW, PatternRegexp)
	appendRecordsFromNode(&records, denyRegexpRecords, DENY, PatternRegexp)

	slices.SortStableFunc(records, func(a, b Record) int {
		return cmp.Compare(a.Line, b.Line)
	})

	err := addConfiguredRules(records)
	if err != nil {
		return err
	}

	var (
		sysrunRule  *Rule
		sysrunIndex int
		rulesNum    int
	)

	sysrunRule, sysrunIndex, rulesNum, err = prepareSystemRunRule()
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
		if r.PatternType == PatternRegexp {
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
