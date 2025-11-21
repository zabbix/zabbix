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

import (
	"bufio"
	"bytes"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"

	"golang.zabbix.com/sdk/errs"
)

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

var validModes = map[MatchMode]struct{}{ //nolint:gochecknoglobals // used as a constant check map.
	ModeContains: {},
	ModePrefix:   {},
	ModeSuffix:   {},
	ModeRegex:    {},
}

var validStrategies = map[ScanStrategy]struct{}{ //nolint:gochecknoglobals // used as a constant check map.
	StrategyReadAll:        {},
	StrategyReadLineByLine: {},
}

var allowedPrefixes = []string{"/proc", "/dev"} //nolint:gochecknoglobals // used as a constant check slice.

// MatchMode sets what parser mode will be used to find compatible lines.
type MatchMode int

// ScanStrategy sets what reading approach will be used when reading file.
type ScanStrategy int

// Parser holds the configuration for the file parsing process.
type Parser struct {
	maxMatches   int
	scanStrategy ScanStrategy
	matchMode    MatchMode
}

// NewParser creates a new Parser instance with default settings (Unlimited lines, Contains mode).
func NewParser() *Parser {
	return &Parser{
		maxMatches:   0,
		matchMode:    ModeContains,
		scanStrategy: StrategyReadAll,
	}
}

// SetMaxMatches sets the maximum number of matches to find from the file.
// 0 indicates unlimited lines.
func (p *Parser) SetMaxMatches(lines int) {
	p.maxMatches = lines
}

// SetMatchMode sets the strategy used to find the pattern in the line.
func (p *Parser) SetMatchMode(mode MatchMode) {
	_, ok := validModes[mode]
	if !ok {
		return
	}

	p.matchMode = mode
}

// SetScanStrategy sets the strategy which will be used to read the file.
func (p *Parser) SetScanStrategy(scanStrategy ScanStrategy) {
	_, ok := validStrategies[scanStrategy]
	if !ok {
		return
	}

	p.scanStrategy = scanStrategy
}

// Parse opens the file at path and returns the remaining content of all lines matching the pattern.
func (p *Parser) Parse(path, pattern string) ([]string, error) {
	var (
		cleanPath     = filepath.Clean(path)
		isValidPrefix = false
	)

	for _, prefix := range allowedPrefixes {
		if strings.HasPrefix(cleanPath, prefix) {
			isValidPrefix = true

			break
		}
	}

	if !isValidPrefix {
		return nil, errs.Errorf("path %s is not allowed", path)
	}

	var reg *regexp.Regexp

	if p.matchMode == ModeRegex {
		var err error

		reg, err = regexp.Compile(pattern)
		if err != nil {
			return nil, errs.Wrapf(err, "failed to compile regex pattern %s", pattern)
		}
	}

	switch p.scanStrategy {
	case StrategyReadLineByLine:
		return p.parseLineByLine(cleanPath, pattern, reg)
	case StrategyReadAll:
		return p.parseReadAll(cleanPath, pattern, reg)
	default:
		return nil, errs.Errorf("unknown scan strategy: %d", p.scanStrategy)
	}
}

func (p *Parser) parseReadAll(path, pattern string, reg *regexp.Regexp) ([]string, error) {
	// ReadAll reads the whole file safely.
	content, err := ReadAll(path)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to read file %s", path)
	}

	// Convert pattern to bytes once
	patternBytes := []byte(pattern)

	var (
		results []string
		lines   = bytes.Split(content, []byte("\n"))
	)

	for _, lineBytes := range lines {
		// Check match limit at the start of iteration
		if p.maxMatches > 0 {
			if len(results) >= p.maxMatches {
				break
			}
		}

		if len(lineBytes) == 0 {
			continue
		}

		matchedStr, found := p.
			processByteLine(lineBytes, patternBytes, reg)
		if found {
			results = append(results, matchedStr)
		}
	}

	return results, nil
}

func (p *Parser) parseLineByLine(path, pattern string, reg *regexp.Regexp) ([]string, error) {
	// G304: The path is sanitized using filepath.Clean and validated against a strict allowlist (allowedPrefixes).
	//nolint:gosec
	file, err := os.Open(path)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to open file %s", path)
	}

	defer file.Close() //nolint:errcheck // standard defer function which error would not change anything.

	var (
		results []string
		scanner = bufio.NewScanner(file)
	)

	for scanner.Scan() {
		// Check match limit at the start of iteration
		if p.maxMatches > 0 {
			if len(results) >= p.maxMatches {
				break
			}
		}

		line := scanner.Text()

		// If we found a match, we append it. We do NOT return immediately, maxLines decides.
		matchedStr, found := p.processStringLine(line, pattern, reg)
		if found {
			results = append(results, matchedStr)
		}
	}

	err = scanner.Err()
	if err != nil {
		return nil, fmt.Errorf("scan error: %w", err)
	}

	return results, nil
}

// processByteLine handles the specific matching logic for a single line using byte slices.
//
//nolint:cyclop // this is simple switch and is readable.
func (p *Parser) processByteLine(line, pattern []byte, reg *regexp.Regexp) (string, bool) {
	switch p.matchMode {
	case ModeContains:
		if bytes.Contains(line, pattern) {
			return string(line), true
		}
	case ModePrefix:
		if bytes.HasPrefix(line, pattern) {
			return string(line), true
		}
	case ModeSuffix:
		if bytes.HasSuffix(line, pattern) {
			return string(line), true
		}
	case ModeRegex:
		if reg == nil {
			return "", false
		}

		if reg.Match(line) {
			return string(line), true
		}
	default:
		return "", false
	}

	return "", false
}

// processStringLine handles the specific matching logic for a single line.
//
//nolint:cyclop // this is simple switch and is readable.
func (p *Parser) processStringLine(line, pattern string, reg *regexp.Regexp) (string, bool) {
	switch p.matchMode {
	case ModeContains:
		if strings.Contains(line, pattern) {
			return line, true
		}
	case ModePrefix:
		if strings.HasPrefix(line, pattern) {
			return line, true
		}
	case ModeSuffix:
		if strings.HasSuffix(line, pattern) {
			return line, true
		}
	case ModeRegex:
		if reg == nil {
			return "", false
		}

		if reg.MatchString(line) {
			return line, true
		}
	default:
		return "", false
	}

	return "", false
}
