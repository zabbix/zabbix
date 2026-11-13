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
	"testing"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

func TestPlugin_getCPUIntr(t *testing.T) {
	t.Parallel()

	errRead := errors.New("read error")

	tests := []struct {
		name     string
		params   []string
		line     string
		readErr  error
		want     any
		wantErr  bool
		errIs    error
		wantRead bool
	}{
		{
			name:     "+valid",
			params:   nil,
			line:     "intr 308874139 44 0 1 13",
			readErr:  nil,
			want:     uint64(308874139),
			wantErr:  false,
			errIs:    nil,
			wantRead: true,
		},
		{
			name:     "-withParam",
			params:   []string{""},
			line:     "",
			readErr:  nil,
			want:     nil,
			wantErr:  true,
			errIs:    zbxerr.ErrorTooManyParameters,
			wantRead: false,
		},
		{
			name:     "-readError",
			params:   nil,
			line:     "",
			readErr:  errRead,
			want:     nil,
			wantErr:  true,
			errIs:    errRead,
			wantRead: true,
		},
		{
			name:     "-invalidLine",
			params:   nil,
			line:     "intr invalid",
			readErr:  nil,
			want:     nil,
			wantErr:  true,
			errIs:    nil,
			wantRead: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			var gotPrefix string

			p := &Plugin{
				Base: plugin.Base{Logger: log.New("test")},
				cpus: nil,
				findStatLineFunc: func(prefix string) (string, error) {
					gotPrefix = prefix

					return tt.line, tt.readErr
				},
			}

			got, err := p.getCPUIntr(tt.params)

			if (err != nil) != tt.wantErr {
				t.Fatalf("getCPUIntr() error = %v, wantErr %t", err, tt.wantErr)
			}

			if tt.errIs != nil && !errors.Is(err, tt.errIs) {
				t.Fatalf("getCPUIntr() error = %v, want %v", err, tt.errIs)
			}

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("getCPUIntr() mismatch (-want +got):\n%s", diff)
			}

			wasRead := gotPrefix != ""
			if wasRead != tt.wantRead {
				t.Fatalf("getCPUIntr() read /proc/stat = %t, want %t", wasRead, tt.wantRead)
			}

			if wasRead && gotPrefix != intrPrefix {
				t.Fatalf("getCPUIntr() prefix = %q, want %q", gotPrefix, intrPrefix)
			}
		})
	}
}

func Test_parseIntrTotal(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		line    string
		want    uint64
		wantErr bool
	}{
		{
			name:    "+total",
			line:    "intr 308874139 44 0 1 13",
			want:    308874139,
			wantErr: false,
		},
		{
			name:    "+onlyTotal",
			line:    "intr 123",
			want:    123,
			wantErr: false,
		},
		{
			name:    "+zero",
			line:    "intr 0 0 0",
			want:    0,
			wantErr: false,
		},
		{
			name:    "+maxUint64",
			line:    "intr 18446744073709551615",
			want:    ^uint64(0),
			wantErr: false,
		},
		{
			name:    "+whitespace",
			line:    "intr\t  123\t44 0",
			want:    123,
			wantErr: false,
		},
		{
			name:    "-empty",
			line:    "",
			want:    0,
			wantErr: true,
		},
		{
			name:    "-noValue",
			line:    "intr",
			want:    0,
			wantErr: true,
		},
		{
			name:    "-invalidValue",
			line:    "intr invalid 123",
			want:    0,
			wantErr: true,
		},
		{
			name:    "-negativeValue",
			line:    "intr -1",
			want:    0,
			wantErr: true,
		},
		{
			name:    "-overflow",
			line:    "intr 18446744073709551616",
			want:    0,
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := parseIntrTotal(tt.line)
			if (err != nil) != tt.wantErr {
				t.Fatalf("parseIntrTotal() error = %v, wantErr %t", err, tt.wantErr)
			}

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("parseIntrTotal() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
