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
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/google/go-cmp/cmp"
)

const unauthorizedPath = "/unauthorized/path.txt"

func TestParser_Parse(t *testing.T) {
	t.Parallel()
	tmpDir := t.TempDir()

	// generateBigFile creates a large simulated log file content
	generateBigFile := func() string {
		var sb strings.Builder

		for i := range 1000 {
			// Every 100th line is an error
			if i%100 == 0 {
				sb.WriteString(fmt.Sprintf("2025-01-01 10:00:%02d [ERROR] System crash %d\n", i%60, i))
			} else {
				sb.WriteString(fmt.Sprintf("2025-01-01 10:00:%02d [INFO] System healthy %d\n", i%60, i))
			}
		}

		return sb.String()
	}

	bigLogContent := generateBigFile()

	type fields struct {
		maxMatches   int
		scanStrategy ScanStrategy
		matchMode    MatchMode
		splitIndex   int
		splitSep     string
		pattern      string
	}

	type args struct {
		path string
	}

	tests := []struct {
		name        string
		fields      fields
		args        args
		fileContent string
		want        []string
		wantErr     bool
	}{
		// -------------------------------------------------------------------
		// Basic Tests
		// -------------------------------------------------------------------
		{
			name: "+ReadAllContainsMatch",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModeContains,
				pattern:      "error",
			},
			args: args{path: "basic_contains.txt"},
			fileContent: "line1\n" +
				"this is an error log\n" +
				"line3",
			want:    []string{"this is an error log"},
			wantErr: false,
		},
		{
			name: "+ReadLineByLineRegexMatch",
			fields: fields{
				maxMatches:   2,
				scanStrategy: StrategyReadLineByLine,
				matchMode:    ModeRegex,
				pattern:      "^ERR", // Matches any string that starts with "ERR".
			},
			args: args{path: "basic_regex.txt"},
			fileContent: "ERR: failure 1\n" +
				"INFO: success\n" +
				"ERR: failure 2\n" +
				"ERR: failure 3",
			want:    []string{"ERR: failure 1", "ERR: failure 2"},
			wantErr: false,
		},
		{
			name: "+SplitLogic",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModeContains,
				pattern:      "CPU",
				splitSep:     ":",
				splitIndex:   1,
			},
			args: args{path: "basic_split.txt"},
			fileContent: "Disk: 50%\n" +
				"CPU: 90%\n" +
				"Mem: 20%",
			want:    []string{"90%"},
			wantErr: false,
		},

		// -------------------------------------------------------------------
		// Prefix & Suffix Modes
		// -------------------------------------------------------------------
		{
			name: "+ModePrefixMemInfo",
			fields: fields{
				scanStrategy: StrategyReadLineByLine,
				matchMode:    ModePrefix,
				pattern:      "MemTotal",
				splitSep:     ":",
				splitIndex:   1,
			},
			args: args{path: "meminfo.txt"},
			fileContent: "MemTotal:        16326540 kB\n" +
				"MemFree:         123456 kB",
			want:    []string{"16326540 kB"}, // TrimSpace is applied by parser
			wantErr: false,
		},
		{
			name: "+ModePrefixNoMatch",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModePrefix,
				pattern:      "MemTotal",
			},
			args: args{path: "meminfo_nomatch.txt"},
			fileContent: " MemTotal: 100 (leading space)\n" +
				"MemFree: 200",
			want:    []string{}, // Should be empty, not nil
			wantErr: false,
		},
		{
			name: "+ModeSuffixServiceStatus",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModeSuffix,
				pattern:      ".service",
			},
			args: args{path: "services.txt"},
			fileContent: "httpd.service\n" +
				"nginx.conf\n" +
				"mysql.service\n" +
				"readme.txt",
			want:    []string{"httpd.service", "mysql.service"},
			wantErr: false,
		},
		{
			name: "+ModeSuffixWithSplit",
			fields: fields{
				scanStrategy: StrategyReadLineByLine,
				matchMode:    ModeSuffix,
				pattern:      "KB",
				splitSep:     " ",
				splitIndex:   0,
			},
			args: args{path: "sizes.txt"},
			fileContent: "100 KB\n" +
				"200 MB\n" +
				"300 KB",
			want:    []string{"100", "300"},
			wantErr: false,
		},

		// -------------------------------------------------------------------
		// Regex Scenarios
		// -------------------------------------------------------------------
		{
			name: "+RegexIPAddress",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModeRegex, // Matches a standard IPv4 address format.
				pattern:      `\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}`,
			},
			args: args{path: "ips.txt"},
			fileContent: "Host: localhost\n" +
				"IP: 192.168.1.1 connected\n" +
				"No ip here",
			want:    []string{"IP: 192.168.1.1 connected"},
			wantErr: false,
		},
		{
			name: "+RegexComplexSplit",
			fields: fields{
				scanStrategy: StrategyReadLineByLine,
				matchMode:    ModeRegex,
				pattern:      `^eth\d+`, // Starts with eth0, eth1...
				splitSep:     ":",
				splitIndex:   1,
			},
			args: args{path: "netdev.txt"},
			fileContent: "lo: 123 456\n" +
				"eth0: 999 888\n" +
				"eth1: 777 666\n" +
				"wlan0: 111 222",
			want:    []string{"999 888", "777 666"},
			wantErr: false,
		},
		{
			name: "-RegexInvalidPattern",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModeRegex,
				pattern:      `[unclosed-bracket`, // A syntactically invalid regular expression.
			},
			args:        args{path: "regex_fail.txt"},
			fileContent: "content irrelevant",
			want:        nil,
			wantErr:     true,
		},

		// -------------------------------------------------------------------
		// Splitter Logic & Edge Cases
		// -------------------------------------------------------------------
		{
			name: "+SplitIndexOutOfBounds",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModeContains,
				pattern:      "data",
				splitSep:     ",",
				splitIndex:   5, // Only 3 items exist
			},
			args:        args{path: "csv.txt"},
			fileContent: "id,name,data",
			want:        []string{}, // Line matches pattern, but split fails -> ignored
			wantErr:     false,
		},
		{
			name: "+SplitMultiCharSeparator",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModeContains,
				pattern:      "value",
				splitSep:     "=>",
				splitIndex:   1,
			},
			args: args{path: "arrow.txt"},
			fileContent: "key=>value\n" +
				"ignore=>me",
			want:    []string{"value"},
			wantErr: false,
		},
		{
			name: "+SplitEmptyPatternExtractField",
			fields: fields{
				scanStrategy: StrategyReadLineByLine,
				matchMode:    ModeContains, // Mode doesn't matter much if pattern is empty, acts as "MatchAll"
				pattern:      "",           // Empty pattern matches all lines
				splitSep:     "=",
				splitIndex:   1,
			},
			args: args{path: "config.txt"},
			fileContent: "user=admin  \n" +
				"   pass=1234\n" +
				"role=editor",
			want:    []string{"admin", "1234", "editor"}, // will trim lines.
			wantErr: false,
		},

		// -------------------------------------------------------------------
		// MaxMatches Scenarios
		// -------------------------------------------------------------------
		{
			name: "+MaxMatchesExact",
			fields: fields{
				maxMatches:   3,
				scanStrategy: StrategyReadAll,
				matchMode:    ModeContains,
				pattern:      "match",
			},
			args: args{path: "matches.txt"},
			fileContent: "match 1\n" +
				"match 2\n" +
				"match 3",
			want:    []string{"match 1", "match 2", "match 3"},
			wantErr: false,
		},
		{
			name: "+MaxMatchesStoppedEarly",
			fields: fields{
				maxMatches:   1,
				scanStrategy: StrategyReadAll,
				matchMode:    ModeContains,
				pattern:      "match",
			},
			args: args{path: "matches_limit.txt"},
			fileContent: "match 1\n" +
				"match 2\n" +
				"match 3",
			want:    []string{"match 1"},
			wantErr: false,
		},

		// -------------------------------------------------------------------
		// Big File Scenarios
		// -------------------------------------------------------------------
		{
			name: "+BigFileReadAllErrorsOnly",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModeContains,
				pattern:      "[ERROR]",
			},
			args:        args{path: "big_log_readall.log"},
			fileContent: bigLogContent,
			// Logic: 1000 lines, every 100th is error -> 10 errors.
			// We verify the count in the assertions or just expect the first few strings if explicit.
			// Since we use 'cmp.Diff', we need exact output.
			// 0, 100, 200, 300, 400, 500, 600, 700, 800, 900
			want: []string{
				"2025-01-01 10:00:00 [ERROR] System crash 0",
				"2025-01-01 10:00:40 [ERROR] System crash 100",
				"2025-01-01 10:00:20 [ERROR] System crash 200",
				"2025-01-01 10:00:00 [ERROR] System crash 300",
				"2025-01-01 10:00:40 [ERROR] System crash 400",
				"2025-01-01 10:00:20 [ERROR] System crash 500",
				"2025-01-01 10:00:00 [ERROR] System crash 600",
				"2025-01-01 10:00:40 [ERROR] System crash 700",
				"2025-01-01 10:00:20 [ERROR] System crash 800",
				"2025-01-01 10:00:00 [ERROR] System crash 900",
			},
			wantErr: false,
		},
		{
			name: "+BigFileLineByLineFirstError",
			fields: fields{
				maxMatches:   1,
				scanStrategy: StrategyReadLineByLine,
				matchMode:    ModeContains,
				pattern:      "[ERROR]",
			},
			args:        args{path: "big_log_lbl.log"},
			fileContent: bigLogContent,
			want:        []string{"2025-01-01 10:00:00 [ERROR] System crash 0"},
			wantErr:     false,
		},

		// -------------------------------------------------------------------
		// File System Errors & Edge Cases
		// -------------------------------------------------------------------
		{
			name: "-PathNotAllowed",
			fields: fields{
				scanStrategy: StrategyReadAll,
			},
			args:        args{path: unauthorizedPath},
			fileContent: "",
			want:        nil,
			wantErr:     true,
		},
		{
			name: "-FileNotFoundReadAll",
			fields: fields{
				scanStrategy: StrategyReadAll,
			},
			args:        args{path: "non_existent_file.txt"},
			fileContent: "", // File not created
			want:        nil,
			wantErr:     true,
		},
		{
			name: "-FileNotFoundOSReadFile",
			fields: fields{
				scanStrategy: StrategyOSReadFile,
			},
			args:        args{path: "non_existent_file_os.txt"},
			fileContent: "", // File not created
			want:        nil,
			wantErr:     true,
		},
		{
			name: "-FileNotFoundLineByLine",
			fields: fields{
				scanStrategy: StrategyReadLineByLine,
			},
			args:        args{path: "non_existent_file_lbl.txt"},
			fileContent: "", // File not created
			want:        nil,
			wantErr:     true,
		},
		{
			name: "+EmptyFile",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModeContains,
				pattern:      "anything",
			},
			args:        args{path: "empty.txt"},
			fileContent: "",
			want:        []string{},
			wantErr:     false,
		},
		{
			name: "+EmptyLineHandling",
			fields: fields{
				scanStrategy: StrategyReadAll,
				matchMode:    ModeContains,
				pattern:      "",
			},
			args: args{path: "empty_lines.txt"},
			fileContent: "\n" +
				"line2\n" +
				"\n" +
				"line4\n",
			want:    []string{"line2", "line4"}, // ParseReadAll explicitly checks len(lineBytes) == 0
			wantErr: false,
		},
		{
			name: "-UnknownStrategy",
			fields: fields{
				scanStrategy: ScanStrategy(99), // Invalid enum
			},
			args:        args{path: "strategy_fail.txt"},
			fileContent: "content",
			want:        nil,
			wantErr:     true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			var targetPath string

			if tt.args.path == unauthorizedPath {
				targetPath = tt.args.path
			} else {
				targetPath = filepath.Join(tmpDir, tt.args.path)
				// Only create file if the test expects it (i.e., not a "FileNotFound" test)
				if !strings.HasPrefix(tt.name, "-FileNotFound") {
					err := os.WriteFile(targetPath, []byte(tt.fileContent), 0600)
					if err != nil {
						t.Fatalf("failed to create temp file: %v", err)
					}
				}
			}

			p := NewParser().
				SetMaxMatches(tt.fields.maxMatches).
				SetScanStrategy(tt.fields.scanStrategy).
				SetMatchMode(tt.fields.matchMode).
				SetPattern(tt.fields.pattern).
				SetSplitter(tt.fields.splitSep, tt.fields.splitIndex)

			got, err := p.Parse(targetPath)

			if (err != nil) != tt.wantErr {
				t.Fatalf("Parser.Parse() error = %v, wantErr %v", err, tt.wantErr)
			}

			if !tt.wantErr {
				if diff := cmp.Diff(tt.want, got); diff != "" {
					t.Fatalf("Parser.Parse() mismatch (-want +got):\n%s", diff)
				}
			}
		})
	}
}
