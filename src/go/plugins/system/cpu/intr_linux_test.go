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

package cpu

import (
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"syscall"
	"testing"

	"golang.zabbix.com/sdk/zbxerr"
)

const intrMetricKey = "system.cpu.intr"

func TestPlugin_ExportIntr(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		content string
		want    uint64
		wantErr bool
	}{
		{
			name:    "+totalInterrupts",
			content: "cpu  100 20 30 40\ncpu0 100 20 30 40\nintr 308874139 44 0 1 13\nctxt 684166014\n",
			want:    308874139,
		},
		{
			name:    "+zero",
			content: "intr 0 0 0\n",
			want:    0,
		},
		{
			name:    "+maxUint64",
			content: "intr 18446744073709551615\n",
			want:    ^uint64(0),
		},
		{
			name:    "+whitespace",
			content: "intr\t  123\t44 0\n",
			want:    123,
		},
		{
			name:    "+noFinalNewline",
			content: "intr 123",
			want:    123,
		},
		{
			name:    "+firstEntry",
			content: "intr 123 44\nintr 456 0\n",
			want:    123,
		},
		{
			name:    "+longIntrLine",
			content: "intr 123 " + strings.Repeat("0 ", 40000) + "\n",
			want:    123,
		},
		{
			name:    "+longPrecedingLine",
			content: "cpu " + strings.Repeat("0 ", 40000) + "\nintr 123\n",
			want:    123,
		},
		{
			name:    "-emptyFile",
			wantErr: true,
		},
		{
			name:    "-missingIntr",
			content: "cpu 100 20 30 40\nctxt 123\nsoftirq 456\n",
			wantErr: true,
		},
		{
			name:    "-similarLabel",
			content: "intr_extra 123\n",
			wantErr: true,
		},
		{
			name:    "-noValue",
			content: "intr\n",
			wantErr: true,
		},
		{
			name:    "-invalidValue",
			content: "intr invalid 123\n",
			wantErr: true,
		},
		{
			name:    "-invalidFirstEntry",
			content: "intr invalid\nintr 123\n",
			wantErr: true,
		},
		{
			name:    "-negativeValue",
			content: "intr -1\n",
			wantErr: true,
		},
		{
			name:    "-overflow",
			content: "intr 18446744073709551616\n",
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			path := filepath.Join(t.TempDir(), "stat")

			err := os.WriteFile(path, []byte(tt.content), 0600)
			if err != nil {
				t.Fatalf("failed to write proc stat fixture: %v", err)
			}

			p := &Plugin{
				procStatFilepath: path,
			}

			got, err := p.Export(intrMetricKey, nil, nil)

			if tt.wantErr {
				wantErr := fmt.Sprintf("Cannot find a line with %q in %s.", intrPattern, path)
				if err == nil || err.Error() != wantErr || got != nil {
					t.Fatalf("Export() = (%v, %v), want (nil, %q)", got, err, wantErr)
				}

				return
			}

			if err != nil || got != tt.want {
				t.Fatalf("Export() = (%v, %v), want (%d, nil)", got, err, tt.want)
			}
		})
	}
}

func TestPlugin_ExportIntrReadError(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name string
		want error
	}{
		{
			name: "-missingFile",
			want: syscall.ENOENT,
		},
		{
			name: "-directory",
			want: syscall.EISDIR,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			path := t.TempDir()
			if tt.name == "-missingFile" {
				path = filepath.Join(path, "stat")
			}

			p := &Plugin{
				procStatFilepath: path,
			}

			got, err := p.Export(intrMetricKey, nil, nil)
			if got != nil || !errors.Is(err, tt.want) {
				t.Fatalf("Export() = (%v, %v), want (nil, %v)", got, err, tt.want)
			}
		})
	}
}

func TestPlugin_ExportIntrValidation(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name   string
		key    string
		params []string
		want   error
	}{
		{
			name:   "-withEmptyParam",
			key:    intrMetricKey,
			params: []string{""},
			want:   zbxerr.ErrorTooManyParameters,
		},
		{
			name:   "-withParam",
			key:    intrMetricKey,
			params: []string{"all"},
			want:   zbxerr.ErrorTooManyParameters,
		},
		{
			name:   "-withMultipleParams",
			key:    intrMetricKey,
			params: []string{"all", "avg1"},
			want:   zbxerr.ErrorTooManyParameters,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			p := &Plugin{}

			got, err := p.Export(tt.key, tt.params, nil)
			if got != nil || !errors.Is(err, tt.want) {
				t.Fatalf("Export() = (%v, %v), want (nil, %v)", got, err, tt.want)
			}
		})
	}
}
