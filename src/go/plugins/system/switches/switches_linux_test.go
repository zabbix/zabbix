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

package switches

import (
	_ "embed"
	"errors"
	"io/fs"
	"os"
	"path/filepath"
	"testing"

	"github.com/google/go-cmp/cmp"
)

const testKey = "system.cpu.switches"

//go:embed testdata/system.cpu.switches_valid.txt
var validContent []byte

//go:embed testdata/system.cpu.switches_valid_minimal.txt
var validMinimalContent []byte

//go:embed testdata/system.cpu.switches_multiple.txt
var multipleCtxtContent []byte

//go:embed testdata/system.cpu.switches_first_invalid.txt
var firstInvalidCtxtContent []byte

//go:embed testdata/system.cpu.switches_whitespace_variations.txt
var whitespaceVariationsContent []byte

//go:embed testdata/system.cpu.switches_missing.txt
var missingCtxtContent []byte

//go:embed testdata/system.cpu.switches_invalid_value.txt
var invalidValueContent []byte

//go:embed testdata/system.cpu.switches_no_value.txt
var noValueContent []byte

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
		wantErr         error
	}{
		{
			name:            "+valid",
			args:            args{key: testKey, params: []string{}},
			procStatContent: validContent,
			want:            uint64(232483817),
		},
		{
			name:            "+validMinimalProcStat",
			args:            args{key: testKey, params: []string{}},
			procStatContent: validMinimalContent,
			want:            uint64(1000),
		},
		{
			name:            "+multipleCtxtEntries",
			args:            args{key: testKey, params: []string{}},
			procStatContent: multipleCtxtContent,
			want:            uint64(232483817),
		},
		{
			name:            "+firstCtxtInvalid",
			args:            args{key: testKey, params: []string{}},
			procStatContent: firstInvalidCtxtContent,
			want:            uint64(232483817),
		},
		{
			name:            "+whitespaceVariations",
			args:            args{key: testKey, params: []string{}},
			procStatContent: whitespaceVariationsContent,
			want:            uint64(232483817),
		},
		{
			name:            "+ctxtPrefixedToken",
			args:            args{key: testKey, params: []string{}},
			procStatContent: []byte("cpu  1 2 3 4\nctxtx 4242\nbtime 1\n"),
			want:            uint64(4242),
		},
		{
			name:            "+trailingNonDigits",
			args:            args{key: testKey, params: []string{}},
			procStatContent: []byte("cpu  1 2 3 4\nctxt 4242abc\nbtime 1\n"),
			want:            uint64(4242),
		},
		{
			name:            "+withEmptyParamIgnored",
			args:            args{key: testKey, params: []string{""}},
			procStatContent: validContent,
			want:            uint64(232483817),
		},
		{
			name:            "+withParamIgnored",
			args:            args{key: testKey, params: []string{"param"}},
			procStatContent: validContent,
			want:            uint64(232483817),
		},
		{
			name:            "+withMultipleParamsIgnored",
			args:            args{key: testKey, params: []string{"param1", "param2"}},
			procStatContent: validContent,
			want:            uint64(232483817),
		},
		{
			name:            "-missingCtxt",
			args:            args{key: testKey, params: []string{}},
			procStatContent: missingCtxtContent,
			wantErr:         errCtxtNotFound,
		},
		{
			name:            "-ctxtNotAtLineStart",
			args:            args{key: testKey, params: []string{}},
			procStatContent: []byte("cpu  1 2 3 4\n ctxt 4242\nbtime 1\n"),
			wantErr:         errCtxtNotFound,
		},
		{
			name:            "-invalidCtxtValue",
			args:            args{key: testKey, params: []string{}},
			procStatContent: invalidValueContent,
			wantErr:         errCtxtNotFound,
		},
		{
			name:            "-ctxtNoValue",
			args:            args{key: testKey, params: []string{}},
			procStatContent: noValueContent,
			wantErr:         errCtxtNotFound,
		},
		{
			name:            "-emptyFile",
			args:            args{key: testKey, params: []string{}},
			procStatContent: nil,
			wantErr:         errCtxtNotFound,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			p := &Plugin{
				procStatFilepath: createMockFile(t, tt.procStatContent),
			}

			got, err := p.Export(tt.args.key, tt.args.params, nil)
			if !errors.Is(err, tt.wantErr) {
				t.Fatalf("Plugin.Export() error = %v, wantErr %v", err, tt.wantErr)
			}

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("Plugin.Export() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func TestPlugin_Export_fileMissing(t *testing.T) {
	t.Parallel()

	p := &Plugin{
		procStatFilepath: filepath.Join(t.TempDir(), "missing"),
	}

	got, err := p.Export(testKey, []string{}, nil)
	if !errors.Is(err, fs.ErrNotExist) {
		t.Fatalf("Plugin.Export() error = %v, wantErr %v", err, fs.ErrNotExist)
	}

	if got != nil {
		t.Fatalf("Plugin.Export() = %v, want nil", got)
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
