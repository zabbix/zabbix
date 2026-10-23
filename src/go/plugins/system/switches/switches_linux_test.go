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
	"errors"
	"testing"
)

const validLine = "ctxt 232483817"

func Test_parseSwitches(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		lines   []string
		want    uint64
		wantErr error
	}{
		{
			name:  "+valid",
			lines: []string{validLine},
			want:  232483817,
		},
		{
			name:  "+multipleCtxtEntries",
			lines: []string{validLine, "ctxt 232483999"},
			want:  232483817,
		},
		{
			name:  "+firstCtxtInvalid",
			lines: []string{"ctxt invalid_value", validLine},
			want:  232483817,
		},
		{
			name:  "+tabSeparator",
			lines: []string{"ctxt\t232483817"},
			want:  232483817,
		},
		{
			name:  "+ctxtPrefixedToken",
			lines: []string{"ctxtx 4242"},
			want:  4242,
		},
		{
			name:  "+trailingNonDigits",
			lines: []string{"ctxt 4242abc"},
			want:  4242,
		},
		{
			name:    "-noLines",
			lines:   nil,
			wantErr: errCtxtNotFound,
		},
		{
			name:    "-invalidCtxtValue",
			lines:   []string{"ctxt invalid_value"},
			wantErr: errCtxtNotFound,
		},
		{
			name:    "-ctxtNoValue",
			lines:   []string{"ctxt"},
			wantErr: errCtxtNotFound,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := parseSwitches(tt.lines)
			if !errors.Is(err, tt.wantErr) {
				t.Fatalf("parseSwitches() error = %v, wantErr %v", err, tt.wantErr)
			}

			if got != tt.want {
				t.Fatalf("parseSwitches() = %d, want %d", got, tt.want)
			}
		})
	}
}

func TestPlugin_Export_params(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name   string
		params []string
	}{
		{name: "-emptyParam", params: []string{""}},
		{name: "-oneParam", params: []string{"foo"}},
		{name: "-twoParams", params: []string{"foo", "bar"}},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := (&Plugin{}).Export("system.cpu.switches", tt.params, nil)
			if !errors.Is(err, errParamsNotAllowed) {
				t.Fatalf("Plugin.Export() error = %v, wantErr %v", err, errParamsNotAllowed)
			}

			if got != nil {
				t.Fatalf("Plugin.Export() = %v, want nil", got)
			}
		})
	}
}
