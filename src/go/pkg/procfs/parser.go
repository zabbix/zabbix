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

var allowedPrefixes = []string{"/proc", "/dev", "/tmp"} //nolint:gochecknoglobals // used as a constant check slice.

// Parser holds the configuration for the file parsing process.
type Parser struct {
	maxMatches    int
	scanStrategy  ScanStrategy
	matchMode     MatchMode
	splitIndex    int
	splitSep      string
	splitSepBytes []byte
	pattern       string
	patternBytes  []byte
}

// NewParser creates a new Parser instance with default settings (Unlimited lines, Contains mode).
func NewParser() *Parser {
	return &Parser{
		matchMode:    ModeContains,
		scanStrategy: StrategyReadAll,
	}
}

// Parse opens the file at path and returns the remaining content of all lines matching the pattern.
func (p *Parser) Parse(path string) ([]string, error) {
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

		reg, err = regexp.Compile(p.pattern)
		if err != nil {
			return nil, errs.Wrapf(err, "failed to compile regex pattern %s", p.pattern)
		}
	}

	switch p.scanStrategy {
	case StrategyReadLineByLine:
		return p.parseLineByLine(cleanPath, reg)
	case StrategyReadAll:
		return p.parseReadAll(cleanPath, reg)
	default:
		return nil, errs.Errorf("unknown scan strategy: %d", p.scanStrategy)
	}
}

// reads all file into memory.
func (p *Parser) parseReadAll(path string, reg *regexp.Regexp) ([]string, error) {
	content, err := ReadAll(path)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to read file %s", path)
	}

	var (
		results = make([]string, 0, p.maxMatches)
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

		matchedStr, found := p.processByteLine(lineBytes, reg)
		if found {
			results = append(results, matchedStr)
		}
	}

	return results, nil
}

// reads file one by one.
func (p *Parser) parseLineByLine(path string, reg *regexp.Regexp) ([]string, error) {
	// G304: The path is sanitized using filepath.Clean and validated against a strict allowlist (allowedPrefixes).
	//nolint:gosec
	file, err := os.Open(path)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to open file %s", path)
	}

	defer file.Close() //nolint:errcheck // standard defer function which error would not change anything.

	var (
		results = make([]string, 0, p.maxMatches)
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
		matchedStr, found := p.processStringLine(line, reg)
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
//nolint:cyclop,gocyclo // this is simple switch and is readable.
func (p *Parser) processByteLine(line []byte, reg *regexp.Regexp) (string, bool) {
	if len(p.patternBytes) > 0 { //nolint:nestif // this is only one switch.
		var matched bool

		switch p.matchMode {
		case ModeContains:
			if bytes.Contains(line, p.patternBytes) {
				matched = true
			}
		case ModePrefix:
			if bytes.HasPrefix(line, p.patternBytes) {
				matched = true
			}
		case ModeSuffix:
			if bytes.HasSuffix(line, p.patternBytes) {
				matched = true
			}
		case ModeRegex:
			if reg != nil && reg.Match(line) {
				matched = true
			}
		default:
			return "", false
		}

		if !matched {
			return "", false
		}
	}

	if len(p.splitSepBytes) == 0 {
		return string(bytes.TrimSpace(line)), true
	}

	parts := bytes.Split(line, p.splitSepBytes)
	if len(parts) > p.splitIndex {
		return string(bytes.TrimSpace(parts[p.splitIndex])), true
	}

	return "", false
}

// processStringLine handles the specific matching logic for a single line.
//
//nolint:cyclop,gocyclo // this is simple switch and one check and is readable.
func (p *Parser) processStringLine(line string, reg *regexp.Regexp) (string, bool) {
	if p.pattern != "" { //nolint:nestif // this is one switch.
		var matched bool

		switch p.matchMode {
		case ModeContains:
			if strings.Contains(line, p.pattern) {
				matched = true
			}
		case ModePrefix:
			if strings.HasPrefix(line, p.pattern) {
				matched = true
			}
		case ModeSuffix:
			if strings.HasSuffix(line, p.pattern) {
				matched = true
			}
		case ModeRegex:
			if reg != nil && reg.MatchString(line) {
				matched = true
			}
		default:
			return "", false
		}

		if !matched {
			return "", false
		}
	}

	if p.splitSep == "" {
		return line, true
	}

	parts := strings.Split(line, p.splitSep)
	if len(parts) > p.splitIndex {
		return strings.TrimSpace(parts[p.splitIndex]), true
	}

	return "", false
}
