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

package procfs

const (
	// ModeContains is used to find any line that has given pattern.
	ModeContains MatchMode = iota
	// ModePrefix is used to find any line that starts with given pattern.
	ModePrefix
	// ModeSuffix is used to find any line that ends with given pattern.
	ModeSuffix
	// ModeRegex is used to find any line that matches the given regex pattern.
	ModeRegex
)

const (
	// StrategyReadAll reads the entire file content at once.
	// Use this for /proc files or small configs.
	StrategyReadAll ScanStrategy = iota

	// StrategyReadLineByLine reads the file one line at a time using a scanner.
	// Use this for large files to lower RAM usage.
	StrategyReadLineByLine
)

// MatchMode sets what parser mode will be used to find compatible lines.
type MatchMode int

// ScanStrategy sets what reading approach will be used when reading file.
type ScanStrategy int

// SetMaxMatches sets the maximum number of matches to find from the file.
// 0 indicates unlimited lines.
func (p *Parser) SetMaxMatches(lines int) *Parser {
	p.maxMatches = lines

	return p
}

// SetMatchMode sets the strategy used to find the pattern in the line.
func (p *Parser) SetMatchMode(mode MatchMode) *Parser {
	p.matchMode = mode

	return p
}

// SetScanStrategy sets the strategy which will be used to read the file.
func (p *Parser) SetScanStrategy(scanStrategy ScanStrategy) *Parser {
	p.scanStrategy = scanStrategy

	return p
}

// SetPattern sets the pattern to search for.
// If pattern is empty, all lines are considered matches.
func (p *Parser) SetPattern(pattern string) *Parser {
	p.pattern = pattern
	p.patternBytes = []byte(pattern)

	return p
}

// SetSplitter sets the separator and the index of the field in the resulting slice to return.
// If the separator is empty, splitting is disabled.
func (p *Parser) SetSplitter(separator string, index int) *Parser {
	p.splitSep = separator
	p.splitSepBytes = []byte(separator)
	p.splitIndex = index

	return p
}
