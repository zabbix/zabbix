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

package procfs

import (
	"bufio"
	"bytes"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"
)

// Benchmark setup helpers

func generateBenchLogFile(lines int) string {
	var sb strings.Builder

	for i := range lines {
		if i%10 == 0 {
			sb.WriteString(fmt.Sprintf("2025-01-01 10:00:%02d [ERROR] Critical failure %d\n", i%60, i))
		} else {
			sb.WriteString(fmt.Sprintf("2025-01-01 10:00:%02d [INFO] Normal operation %d\n", i%60, i))
		}
	}

	return sb.String()
}

func createBenchTempFile(b *testing.B, content string) string {
	b.Helper()

	tmpDir := b.TempDir()

	path := filepath.Join(tmpDir, "bench.log")

	err := os.WriteFile(path, []byte(content), 0600)
	if err != nil {
		b.Fatalf("failed to create benchmark file: %v", err)
	}

	return path
}

// -------------------------------------------------------------------
// Strategy Comparison Benchmarks
// -------------------------------------------------------------------

func BenchmarkParser_Strategies(b *testing.B) {
	sizes := []int{1, 5, 10, 50, 100, 1000, 10000, 100000}

	for _, size := range sizes {
		content := generateBenchLogFile(size)
		path := createBenchTempFile(b, content)

		b.Run(fmt.Sprintf("ReadAll_%dlines", size), func(b *testing.B) {
			p := NewParser().
				SetScanStrategy(StrategyReadAll).
				SetMatchMode(ModeContains).
				SetPattern("[ERROR]")

			b.ResetTimer()

			for range b.N {
				_, err := p.Parse(path)
				if err != nil {
					b.Fatalf("Parse() error = %v", err)
				}
			}
		})

		b.Run(fmt.Sprintf("OSReadFile_%dlines", size), func(b *testing.B) {
			p := NewParser().
				SetScanStrategy(StrategyOSReadFile).
				SetMatchMode(ModeContains).
				SetPattern("[ERROR]")

			b.ResetTimer()

			for range b.N {
				_, err := p.Parse(path)
				if err != nil {
					b.Fatalf("Parse() error = %v", err)
				}
			}
		})

		b.Run(fmt.Sprintf("ReadLineByLine_%dlines", size), func(b *testing.B) {
			p := NewParser().
				SetScanStrategy(StrategyReadLineByLine).
				SetMatchMode(ModeContains).
				SetPattern("[ERROR]")

			b.ResetTimer()

			for range b.N {
				_, err := p.Parse(path)
				if err != nil {
					b.Fatalf("Parse() error = %v", err)
				}
			}
		})
	}
}

// -------------------------------------------------------------------
// Match Mode Comparison Benchmarks
// -------------------------------------------------------------------

func BenchmarkParser_MatchModes(b *testing.B) {
	content := generateBenchLogFile(1000)
	path := createBenchTempFile(b, content)

	modes := []struct {
		name    string
		mode    MatchMode
		pattern string
	}{
		{"ContainsVPrefix", ModeContains, "2025-01-01"},
		{"Prefix", ModePrefix, "2025-01-01"},
		{"ContainsVSuffix", ModeContains, "operation 999"},
		{"Suffix", ModeSuffix, "operation 999"},
		{"ContainsVRegex", ModeContains, "[ERROR\\]"},
		{"Regex", ModeRegex, `\[ERROR\]`},
		{"Contains", ModeContains, "Normal operation"},
	}

	for _, mode := range modes {
		b.Run(mode.name, func(b *testing.B) {
			p := NewParser().
				SetScanStrategy(StrategyReadAll).
				SetMatchMode(mode.mode).
				SetPattern(mode.pattern)

			b.ResetTimer()

			for range b.N {
				_, err := p.Parse(path)
				if err != nil {
					b.Fatalf("Parse() error = %v", err)
				}
			}
		})
	}
}

// -------------------------------------------------------------------
// Splitter Performance Benchmarks
// -------------------------------------------------------------------

func BenchmarkParser_Splitter(b *testing.B) {
	var sb strings.Builder

	for i := range 1000 {
		sb.WriteString(fmt.Sprintf("field1:field2:field3:data%d\n", i))
	}

	content := sb.String()
	path := createBenchTempFile(b, content)

	b.Run("WithoutSplit", func(b *testing.B) {
		p := NewParser().
			SetScanStrategy(StrategyReadAll).
			SetMatchMode(ModeContains).
			SetPattern("field")

		b.ResetTimer()

		for range b.N {
			_, err := p.Parse(path)
			if err != nil {
				b.Fatalf("Parse() error = %v", err)
			}
		}
	})

	b.Run("WithSplit", func(b *testing.B) {
		p := NewParser().
			SetScanStrategy(StrategyReadAll).
			SetMatchMode(ModeContains).
			SetPattern("field").
			SetSplitter(":", 3)

		b.ResetTimer()

		for range b.N {
			_, err := p.Parse(path)
			if err != nil {
				b.Fatalf("Parse() error = %v", err)
			}
		}
	})
}

// -------------------------------------------------------------------
// MaxMatches Performance Benchmarks
// -------------------------------------------------------------------

func BenchmarkParser_MaxMatches(b *testing.B) {
	content := generateBenchLogFile(10000)
	path := createBenchTempFile(b, content)

	limits := []int{0, 10, 100, 500}

	for _, limit := range limits {
		name := "Unlimited"
		if limit > 0 {
			name = fmt.Sprintf("Limit%d", limit)
		}

		b.Run(name, func(b *testing.B) {
			p := NewParser().
				SetMaxMatches(limit).
				SetScanStrategy(StrategyReadLineByLine).
				SetMatchMode(ModeContains).
				SetPattern("[ERROR]")

			b.ResetTimer()

			for range b.N {
				_, err := p.Parse(path)
				if err != nil {
					b.Fatalf("Parse() error = %v", err)
				}
			}
		})
	}
}

// -------------------------------------------------------------------
// Comparison with Native Go Approaches
// -------------------------------------------------------------------

//nolint:gocognit,gocyclo,cyclop // this benchmark has several ways of parsing.
func BenchmarkParser_VsNativeGo(b *testing.B) {
	content := generateBenchLogFile(1000)
	path := createBenchTempFile(b, content)

	b.Run("Parser_ReadAll", func(b *testing.B) {
		p := NewParser().
			SetScanStrategy(StrategyReadAll).
			SetMatchMode(ModeContains).
			SetPattern("[ERROR]")

		b.ResetTimer()

		for range b.N {
			_, err := p.Parse(path)
			if err != nil {
				b.Fatalf("Parse() error = %v", err)
			}
		}
	})

	b.Run("Parser_OSReadFile", func(b *testing.B) {
		p := NewParser().
			SetScanStrategy(StrategyOSReadFile).
			SetMatchMode(ModeContains).
			SetPattern("[ERROR]")

		b.ResetTimer()

		for range b.N {
			_, err := p.Parse(path)
			if err != nil {
				b.Fatalf("Parse() error = %v", err)
			}
		}
	})

	b.Run("Parser_ReadLineByLine", func(b *testing.B) {
		p := NewParser().
			SetScanStrategy(StrategyReadLineByLine).
			SetMatchMode(ModeContains).
			SetPattern("[ERROR]")

		b.ResetTimer()

		for range b.N {
			_, err := p.Parse(path)
			if err != nil {
				b.Fatalf("Parse() error = %v", err)
			}
		}
	})

	b.Run("Native_ReadFile_Bytes", func(b *testing.B) {
		pattern := []byte("[ERROR]")

		b.ResetTimer()

		for range b.N {
			data, err := os.ReadFile(path) //nolint:gosec // this is benchmark.
			if err != nil {
				b.Fatalf("ReadFile() error = %v", err)
			}

			var (
				results []string
				lines   = bytes.Split(data, []byte("\n"))
			)

			for _, line := range lines {
				if len(line) == 0 {
					continue
				}

				if bytes.Contains(line, pattern) {
					results = append(results, string(line)) //nolint:staticcheck // this is a benchmark.
				}
			}
		}
	})

	b.Run("Native_BufioScanner", func(b *testing.B) {
		b.ResetTimer()

		for range b.N {
			file, err := os.Open(path) //nolint:gosec // this is benchmark.
			if err != nil {
				b.Fatalf("Open() error = %v", err)
			}

			var (
				results []string
				scanner = bufio.NewScanner(file)
			)

			for scanner.Scan() {
				line := scanner.Text()
				if strings.Contains(line, "[ERROR]") {
					results = append(results, line) //nolint:staticcheck // this is a benchmark.
				}
			}

			err = file.Close()
			if err != nil {
				b.Fatalf("Close() error = %v", err)
			}

			err = scanner.Err()
			if err != nil {
				b.Fatalf("scanner.Err() = %v", err)
			}
		}
	})

	b.Run("Native_RegexpScan", func(b *testing.B) {
		re := regexp.MustCompile(`\[ERROR\]`)

		b.ResetTimer()

		for range b.N {
			data, err := os.ReadFile(path) //nolint:gosec //this is benchmark.
			if err != nil {
				b.Fatalf("ReadFile() error = %v", err)
			}

			var (
				results []string
				lines   = bytes.Split(data, []byte("\n"))
			)

			for _, line := range lines {
				if len(line) == 0 {
					continue
				}

				if re.Match(line) {
					results = append(results, string(line)) //nolint:staticcheck // this is a benchmark.
				}
			}
		}
	})
}

// -------------------------------------------------------------------
// Memory Allocation Benchmarks
// -------------------------------------------------------------------

func BenchmarkParser_MemoryAllocation(b *testing.B) {
	content := generateBenchLogFile(1000)
	path := createBenchTempFile(b, content)

	b.Run("ReadAll", func(b *testing.B) {
		p := NewParser().
			SetScanStrategy(StrategyReadAll).
			SetMatchMode(ModeContains).
			SetPattern("[ERROR]")

		b.ReportAllocs()
		b.ResetTimer()

		for range b.N {
			_, err := p.Parse(path)
			if err != nil {
				b.Fatalf("Parse() error = %v", err)
			}
		}
	})

	b.Run("OSReadFile", func(b *testing.B) {
		p := NewParser().
			SetScanStrategy(StrategyOSReadFile).
			SetMatchMode(ModeContains).
			SetPattern("[ERROR]")

		b.ReportAllocs()
		b.ResetTimer()

		for range b.N {
			_, err := p.Parse(path)
			if err != nil {
				b.Fatalf("Parse() error = %v", err)
			}
		}
	})

	b.Run("ReadLineByLine", func(b *testing.B) {
		p := NewParser().
			SetScanStrategy(StrategyReadLineByLine).
			SetMatchMode(ModeContains).
			SetPattern("[ERROR]")

		b.ReportAllocs()
		b.ResetTimer()

		for range b.N {
			_, err := p.Parse(path)
			if err != nil {
				b.Fatalf("Parse() error = %v", err)
			}
		}
	})
}
