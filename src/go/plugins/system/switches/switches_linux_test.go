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
	"testing"
)

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

func Test_parseSwitches(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		content []byte
		want    uint64
		wantErr error
	}{
		{
			name:    "+valid",
			content: validContent,
			want:    232483817,
		},
		{
			name:    "+validMinimalProcStat",
			content: validMinimalContent,
			want:    1000,
		},
		{
			name:    "+multipleCtxtEntries",
			content: multipleCtxtContent,
			want:    232483817,
		},
		{
			name:    "+firstCtxtInvalid",
			content: firstInvalidCtxtContent,
			want:    232483817,
		},
		{
			name:    "+whitespaceVariations",
			content: whitespaceVariationsContent,
			want:    232483817,
		},
		{
			name:    "+ctxtPrefixedToken",
			content: []byte("cpu  1 2 3 4\nctxtx 4242\nbtime 1\n"),
			want:    4242,
		},
		{
			name:    "+trailingNonDigits",
			content: []byte("cpu  1 2 3 4\nctxt 4242abc\nbtime 1\n"),
			want:    4242,
		},
		{
			name:    "+noTrailingNewline",
			content: []byte("cpu  1 2 3 4\nctxt 4242"),
			want:    4242,
		},
		{
			name:    "-missingCtxt",
			content: missingCtxtContent,
			wantErr: errCtxtNotFound,
		},
		{
			name:    "-ctxtNotAtLineStart",
			content: []byte("cpu  1 2 3 4\n ctxt 4242\nbtime 1\n"),
			wantErr: errCtxtNotFound,
		},
		{
			name:    "-invalidCtxtValue",
			content: invalidValueContent,
			wantErr: errCtxtNotFound,
		},
		{
			name:    "-ctxtNoValue",
			content: noValueContent,
			wantErr: errCtxtNotFound,
		},
		{
			name:    "-emptyContent",
			content: nil,
			wantErr: errCtxtNotFound,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := parseSwitches(tt.content)
			if !errors.Is(err, tt.wantErr) {
				t.Fatalf("parseSwitches() error = %v, wantErr %v", err, tt.wantErr)
			}

			if got != tt.want {
				t.Fatalf("parseSwitches() = %d, want %d", got, tt.want)
			}
		})
	}
}
