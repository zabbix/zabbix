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

func Test_parseSwitches(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		content string
		want    uint64
		wantErr error
	}{
		{
			name: "+valid",
			content: "cpu  831300 1214 517796 136940034 196296 0 102135 0 0 0\n" +
				"cpu0 62729 215 48494 9745954 20063 0 57638 0 0 0\n" +
				"intr 146085590 0 0 0 0 0 0 0 0 0 95 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 408001\n" +
				"ctxt 232483817\n" +
				"btime 1764129870\n" +
				"processes 945413\n" +
				"procs_running 1\n" +
				"procs_blocked 0\n" +
				"softirq 69102762 0 7547819 234 2303788 0 0 7557992 27371530 3284 24318115\n",
			want: 232483817,
		},
		{
			name:    "+multipleCtxtEntries",
			content: "cpu  1 2 3 4\nctxt 232483817\nctxt 232483999\nbtime 1\n",
			want:    232483817,
		},
		{
			name:    "+firstCtxtInvalid",
			content: "cpu  1 2 3 4\nctxt invalid_value\nctxt 232483817\nbtime 1\n",
			want:    232483817,
		},
		{
			name:    "+tabSeparator",
			content: "cpu  1 2 3 4\nctxt\t232483817\nbtime 1\n",
			want:    232483817,
		},
		{
			name:    "+ctxtPrefixedToken",
			content: "cpu  1 2 3 4\nctxtx 4242\nbtime 1\n",
			want:    4242,
		},
		{
			name:    "+trailingNonDigits",
			content: "cpu  1 2 3 4\nctxt 4242abc\nbtime 1\n",
			want:    4242,
		},
		{
			name:    "+noTrailingNewline",
			content: "cpu  1 2 3 4\nctxt 4242",
			want:    4242,
		},
		{
			name:    "-missingCtxt",
			content: "cpu  1 2 3 4\nbtime 1\nprocesses 945413\n",
			wantErr: errCtxtNotFound,
		},
		{
			name:    "-ctxtNotAtLineStart",
			content: "cpu  1 2 3 4\n ctxt 4242\nbtime 1\n",
			wantErr: errCtxtNotFound,
		},
		{
			name:    "-invalidCtxtValue",
			content: "cpu  1 2 3 4\nctxt invalid_value\nbtime 1\n",
			wantErr: errCtxtNotFound,
		},
		{
			name:    "-ctxtNoValue",
			content: "cpu  1 2 3 4\nctxt\nbtime 1\n",
			wantErr: errCtxtNotFound,
		},
		{
			name:    "-emptyContent",
			content: "",
			wantErr: errCtxtNotFound,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := parseSwitches([]byte(tt.content))
			if !errors.Is(err, tt.wantErr) {
				t.Fatalf("parseSwitches() error = %v, wantErr %v", err, tt.wantErr)
			}

			if got != tt.want {
				t.Fatalf("parseSwitches() = %d, want %d", got, tt.want)
			}
		})
	}
}
