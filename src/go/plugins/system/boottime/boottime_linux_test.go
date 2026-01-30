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

package boottime

import (
	_ "embed"
	"os"
	"path/filepath"
	"testing"

	"github.com/google/go-cmp/cmp"
)

//go:embed testdata/system.boottime_valid.txt
var validContent []byte

//go:embed testdata/system.boottime_valid_minimal.txt
var validMinimalContent []byte

//go:embed testdata/system.boottime_missing.txt
var missingBtimeContent []byte

//go:embed testdata/system.boottime_invalid_value.txt
var invalidValueContent []byte

//go:embed testdata/system.boottime_no_value.txt
var noValueContent []byte

//go:embed testdata/system.boottime_multiple.txt
var multipleBtimeContent []byte

//go:embed testdata/system.boottime_whitespace_variations.txt
var whitespaceVariationsContent []byte

func TestPlugin_Export(t *testing.T) {
	t.Parallel()

	type args struct {
		key    string
		params []string
	}

	tests := []struct {
		name            string
		args            args
		procStatContent []byte
		want            any
		wantErr         bool
	}{
		{
			name: "+validFullProcStat",
			args: args{
				key:    "system.boottime",
				params: []string{},
			},
			procStatContent: validContent,
			want:            uint64(1764129870),
			wantErr:         false,
		},
		{
			name: "+validMinimalProcStat",
			args: args{
				key:    "system.boottime",
				params: []string{},
			},
			procStatContent: validMinimalContent,
			want:            uint64(1732704123),
			wantErr:         false,
		},
		{
			name: "+multipleBtimeEntries",
			args: args{
				key:    "system.boottime",
				params: []string{},
			},
			procStatContent: multipleBtimeContent,
			want:            uint64(1764129870),
			wantErr:         false,
		},
		{
			name: "+whitespaceVariations",
			args: args{
				key:    "system.boottime",
				params: []string{},
			},
			procStatContent: whitespaceVariationsContent,
			want:            uint64(1764129870),
			wantErr:         false,
		},
		{
			name: "-missingBtime",
			args: args{
				key:    "system.boottime",
				params: []string{},
			},
			procStatContent: missingBtimeContent,
			want:            nil,
			wantErr:         true,
		},
		{
			name: "-invalidBtimeValue",
			args: args{
				key:    "system.boottime",
				params: []string{},
			},
			procStatContent: invalidValueContent,
			want:            nil,
			wantErr:         true,
		},
		{
			name: "-btimeNoValue",
			args: args{
				key:    "system.boottime",
				params: []string{},
			},
			procStatContent: noValueContent,
			want:            nil,
			wantErr:         true,
		},
		{
			name: "-emptyFile",
			args: args{
				key:    "system.boottime",
				params: []string{},
			},
			procStatContent: nil,
			want:            nil,
			wantErr:         true,
		},
		{
			name: "-withEmptyParam",
			args: args{
				key:    "system.boottime",
				params: []string{""},
			},
			procStatContent: validContent,
			want:            nil,
			wantErr:         true,
		},
		{
			name: "-withParam",
			args: args{
				key:    "system.boottime",
				params: []string{"param"},
			},
			procStatContent: validContent,
			want:            nil,
			wantErr:         true,
		},
		{
			name: "-withMultipleParams",
			args: args{
				key:    "system.boottime",
				params: []string{"param1", "param2"},
			},
			procStatContent: validContent,
			want:            nil,
			wantErr:         true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			p := &Plugin{
				procStatFilepath: createMockFile(t, tt.procStatContent),
			}

			got, err := p.Export(tt.args.key, tt.args.params, nil)
			if (err != nil) != tt.wantErr {
				t.Fatalf("Plugin.Export() error = %v, wantErr %v", err, tt.wantErr)
			}

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("Plugin.Export() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func createMockFile(t *testing.T, content []byte) string {
	t.Helper()

	var (
		tmpDir = t.TempDir()
		path   = filepath.Join(tmpDir, "stat")
	)

	err := os.WriteFile(path, content, 0600)
	if err != nil {
		t.Fatalf("failed to write temp mock file: %v", err)
	}

	return path
}
