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

package inet

import (
	"testing"

	"github.com/google/go-cmp/cmp"
)

func Test_IsRFCHostName(t *testing.T) {
	t.Parallel()

	// Synchronize with tests/libs/zbxip/zbx_is_rfc_hostname.yaml

	type args struct {
		host string
	}

	tests := []struct {
		name string
		args args
		want bool
	}{
		{"-emptyString", args{""}, false},
		{"-startsWithHyphen", args{"-"}, false},
		{"-hyphenBeforeDot", args{"a-.a"}, false},
		{"-hyphenAfterDot", args{"a.-a"}, false},
		{"-tooLongLabel", args{"0123456789012345678901234567890123456789012345678901234567890123456789"},
			false},
		{"+labelIs63CharactersLong", args{"123456789-123456789-123456789-123456789-123456789-123456789-123"},
			true},
		{"-labelIs64CharactersLong",
			args{"123456789-123456789-123456789-123456789-123456789-123456789-1234"}, false},
		{"+hostnameIs253CharactersLong", args{"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789.123"}, true},
		{"-hostnameIs254CharactersLong", args{"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789.1234"}, false},
		{"-unicodeCharacter", args{"tēst.zabbix.com"}, false},
		{"-leadingDot", args{".example.com"}, false},
		{"-underscore", args{"example_com.com"}, false},
		{"-space", args{"ex ample.com"}, false},
		{"-endsWithHyphen", args{"example.com-"}, false},
		{"-emptyLabel", args{"example.."}, false},
		{"+minimalLabel", args{"a.com"}, true},
		{"+punycode", args{"xn--bcher-kva.com"}, true},
		{"+localhost", args{"localhost"}, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := IsRFCHostName(tt.args.host)

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("IsRFCHostName() = %s", diff)
			}
		})
	}
}
