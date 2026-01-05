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
	"golang.zabbix.com/sdk/log"
)

const parserLogMessage = "[File Parser]"

// minimal possible set of allowed paths, add, whenever need to add new path to some parsing.
//
//nolint:gochecknoglobals // used as a constant check slice.
var allowedPrefixes = []string{
	// Specific /proc
	"/proc/net/",
	"/proc/sys/",
	"/proc/stat",
	// need to think how to implement /proc/[PID], most likely with sub-allowlist.

	"/tmp/",
}

// Parser holds the configuration for the file parsing process.
type Parser struct {
	maxMatches          int
	validatePath        bool
	scanStrategy        ScanStrategy
	matchMode           MatchMode
	splitIndex          int
	splitSeparator      string
	splitSeparatorBytes []byte
	pattern             string
	patternBytes        []byte
}

// NewParser creates a new Parser instance with default settings.
func NewParser() *Parser {
	return &Parser{
		matchMode:    ModeContains,
		scanStrategy: StrategyOSReadFile,
		validatePath: true,
	}
}

// Parse opens the file at path and returns content string lines that match set rules.
func (p *Parser) Parse(path string) ([]string, error) {
	var (
		cleanPath string
		err       error
	)

	if p.validatePath {
		cleanPath, err = validateAndCleanPath(path)
		if err != nil {
			return nil, err
		}
	} else {
		log.Debugf("%s parsing %s without validation", parserLogMessage, path)
		cleanPath = path
	}

	var reg *regexp.Regexp

	if p.matchMode == ModeRegex {
		reg, err = regexp.Compile(p.pattern)
		if err != nil {
			return nil, errs.Wrapf(err, "failed to compile regex pattern %s", p.pattern)
		}
	}

	switch p.scanStrategy {
	case StrategyReadAll, StrategyOSReadFile:
		return p.parseByReadingAllIntoMemory(cleanPath, reg)
	case StrategyReadLineByLine:
		return p.parseLineByScanning(cleanPath, reg)

	default:
		return nil, errs.Errorf("unknown scan strategy: %d", p.scanStrategy)
	}
}

func validateAndCleanPath(path string) (string, error) {
	var (
		//cleanPath     string
		canonicalPath string
		err           error
	)

	//cleanPath = filepath.Clean(path)

	canonicalPath, err = filepath.EvalSymlinks(path)
	if err != nil {
		log.Warningf("%s failed to resolve symlinks for path %s: %s", parserLogMessage, path, err)

		return "", errs.Wrapf(err, "failed to resolve path")
	}

	canonicalPath, err = filepath.Abs(canonicalPath)
	if err != nil {
		return "", errs.Wrapf(err, "failed to get absolute path")
	}

	if !isAllowedPath(canonicalPath) {
		log.Debugf("%s attempted access to non-allowed path: %s", parserLogMessage, canonicalPath)

		return "", errs.Errorf("path not in allowed list: %s", path)
	}

	return canonicalPath, nil
}

func isAllowedPath(path string) bool {
	for _, prefix := range allowedPrefixes {
		if strings.HasPrefix(path, prefix) {
			return true
		}
	}

	return false
}

// reads all file into memory.
func (p *Parser) parseByReadingAllIntoMemory(path string, reg *regexp.Regexp) ([]string, error) {
	var (
		content []byte
		err     error
	)

	switch p.scanStrategy {
	case StrategyReadAll:
		content, err = ReadAll(path)
	case StrategyOSReadFile:
		// G304: The path is sanitized using filepath.Clean and validated against a strict allowlist (allowedPrefixes).
		//nolint:gosec
		content, err = os.ReadFile(path)
	default:
		return nil, errs.Errorf("unknown scan strategy: %d", p.scanStrategy)
	}

	if err != nil {
		return nil, errs.Wrapf(err, "failed to read file %s", path)
	}

	var (
		results = make([]string, 0, p.maxMatches)
		lines   = bytes.Split(content, []byte("\n"))
	)

	log.Tracef("%s Opened file %s, with size of %d bytes, of %d lines",
		parserLogMessage,
		path,
		len(content),
		len(lines),
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
func (p *Parser) parseLineByScanning(path string, reg *regexp.Regexp) ([]string, error) {
	// G304: The path is sanitized using filepath.Clean and validated against a strict allowlist (allowedPrefixes).
	//nolint:gosec
	file, err := os.Open(path)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to open file %s", path)
	}

	stats, err := file.Stat()
	if err == nil {
		log.Tracef("%s Opened file %s, with size of %d bytes", parserLogMessage, path, stats.Size())
	} else {
		log.Tracef("%s Opened file %s, failed to read size of it", parserLogMessage, path)
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
//nolint:cyclop,gocyclo // this is simple switch with split conditional and is readable.
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

	if len(p.splitSeparatorBytes) == 0 {
		return string(bytes.TrimSpace(line)), true
	}

	parts := bytes.Split(line, p.splitSeparatorBytes)
	if len(parts) > p.splitIndex {
		return string(bytes.TrimSpace(parts[p.splitIndex])), true
	}

	return "", false
}

// processStringLine handles the specific matching logic for a single line.
//
//nolint:cyclop,gocyclo // this is simple switch with split conditional and is readable.
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

	if p.splitSeparator == "" {
		return strings.TrimSpace(line), true
	}

	parts := strings.Split(line, p.splitSeparator)
	if len(parts) > p.splitIndex {
		return strings.TrimSpace(parts[p.splitIndex]), true
	}

	return "", false
}
