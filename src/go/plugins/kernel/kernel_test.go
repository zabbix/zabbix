//go:build linux && (amd64 || arm64)

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

package kernel

import (
	_ "embed"
	"os"
	"path/filepath"
	"testing"

	"github.com/google/go-cmp/cmp"
)

//go:embed testdata/kernel.maxfiles_valid.txt
var maxfilesValid []byte

//go:embed testdata/kernel.maxfiles_invalid.txt
var maxfilesInvalid []byte

//go:embed testdata/kernel.maxproc_valid.txt
var maxprocValid []byte

//go:embed testdata/kernel.maxproc_invalid.txt
var maxprocInvalid []byte

//go:embed testdata/kernel.openfiles_valid.txt
var openfilesValid []byte

//go:embed testdata/kernel.openfiles_invalid.txt
var openfilesInvalid []byte

func TestPlugin_Export(t *testing.T) {
	t.Parallel()

	type args struct {
		key    string
		params []string
	}

	tests := []struct {
		name           string
		args           args
		pidMaxContent  []byte
		fileMaxContent []byte
		fileNrContent  []byte
		want           any
		wantErr        bool
	}{
		// Empty file tests
		{
			name: "-maxfilesEmptyFile",
			args: args{
				key:    "kernel.maxfiles",
				params: []string{},
			},
			fileMaxContent: nil,
			want:           uint64(0),
			wantErr:        true,
		},
		{
			name: "-maxprocEmptyFile",
			args: args{
				key:    "kernel.maxproc",
				params: []string{},
			},
			pidMaxContent: nil,
			want:          uint64(0),
			wantErr:       true,
		},
		{
			name: "-openfilesEmptyFile",
			args: args{
				key:    "kernel.openfiles",
				params: []string{},
			},
			fileNrContent: nil,
			want:          uint64(0),
			wantErr:       true,
		},

		// kernel.maxfiles tests
		{
			name: "+maxfilesValid",
			args: args{
				key:    "kernel.maxfiles",
				params: []string{},
			},
			fileMaxContent: maxfilesValid,
			want:           uint64(18446744073709551615),
			wantErr:        false,
		},
		{
			name: "-maxfilesInvalid",
			args: args{
				key:    "kernel.maxfiles",
				params: []string{},
			},
			fileMaxContent: maxfilesInvalid,
			want:           uint64(0),
			wantErr:        true,
		},
		{
			name: "-maxfilesWithEmptyParam",
			args: args{
				key:    "kernel.maxfiles",
				params: []string{""},
			},
			fileMaxContent: maxfilesValid,
			want:           nil,
			wantErr:        true,
		},
		{
			name: "-maxfilesWithParam",
			args: args{
				key:    "kernel.maxfiles",
				params: []string{"param"},
			},
			fileMaxContent: maxfilesValid,
			want:           nil,
			wantErr:        true,
		},

		// kernel.maxproc tests
		{
			name: "+maxprocValid",
			args: args{
				key:    "kernel.maxproc",
				params: []string{},
			},
			pidMaxContent: maxprocValid,
			want:          uint64(18446744073709551615),
			wantErr:       false,
		},
		{
			name: "-maxprocInvalidContent",
			args: args{
				key:    "kernel.maxproc",
				params: []string{},
			},
			pidMaxContent: maxprocInvalid,
			want:          uint64(0),
			wantErr:       true,
		},
		{
			name: "-maxprocWithEmptyParam",
			args: args{
				key:    "kernel.maxproc",
				params: []string{""},
			},
			pidMaxContent: maxprocValid,
			want:          nil,
			wantErr:       true,
		},
		{
			name: "-maxprocWithWrongParam",
			args: args{
				key:    "kernel.maxproc",
				params: []string{"param"},
			},
			pidMaxContent: maxprocValid,
			want:          nil,
			wantErr:       true,
		},

		// kernel.openfiles tests
		{
			name: "+openfilesValid",
			args: args{
				key:    "kernel.openfiles",
				params: []string{},
			},
			fileNrContent: openfilesValid,
			want:          uint64(3392),
			wantErr:       false,
		},
		{
			name: "-openfilesInvalidContent",
			args: args{
				key:    "kernel.openfiles",
				params: []string{},
			},
			fileNrContent: openfilesInvalid,
			want:          uint64(0),
			wantErr:       true,
		},
		{
			name: "-openfilesWithEmptyParam",
			args: args{
				key:    "kernel.openfiles",
				params: []string{""},
			},
			fileNrContent: openfilesValid,
			want:          nil,
			wantErr:       true,
		},
		{
			name: "-openfilesWithWrongParam",
			args: args{
				key:    "kernel.openfiles",
				params: []string{"param"},
			},
			fileNrContent: openfilesValid,
			want:          nil,
			wantErr:       true,
		},
		{
			name: "-wrongKey",
			args: args{
				key:    "wrong.key",
				params: []string{},
			},
			want:    0,
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			p := &Plugin{
				pidMaxPath:  createMockFile(t, tt.pidMaxContent),
				fileMaxPath: createMockFile(t, tt.fileMaxContent),
				fileNrPath:  createMockFile(t, tt.fileNrContent),
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
		path   = filepath.Join(tmpDir, "mockfile")
	)

	err := os.WriteFile(path, content, 0600)
	if err != nil {
		t.Fatalf("failed to write temp mock file: %v", err)
	}

	return path
}
