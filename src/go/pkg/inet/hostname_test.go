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

func Test_IsDNSName(t *testing.T) {
	t.Parallel()

	// Synchronize with tests/libs/zbxip/zbx_is_dnsname.yaml

	type args struct {
		host string
	}

	tests := []struct {
		name string
		args args
		want bool
	}{
		{"-empty_string", args{""}, false},
		{"-starts_with_hyphen", args{"-"}, false},
		{"-hyphen_before_dot", args{"a-.a"}, false},
		{"-hyphen_after_dot", args{"a.-a"}, false},
		{"-too_long_label", args{"0123456789012345678901234567890123456789012345678901234567890123456789"},
			false},
		{"+label_is_63_character_long", args{"123456789-123456789-123456789-123456789-123456789-123456789-123"}, true},
		{"-label_is_64_character_long", args{"123456789-123456789-123456789-123456789-123456789-123456789-1234"}, false},
		{"+hostname_is_253_character_long", args{"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789.123"}, true},
		{"-hostname_is_254_character_long", args{"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789.1234"}, false},
		{"-unicode_character", args{"tēst.zabbix.com"}, false},
		{"-leading_dot", args{".example.com"}, false},
		{"-underscore", args{"example_com.com"}, false},
		{"-space", args{"ex ample.com"}, false},
		{"-ends_with_hyphen", args{"example.com-"}, false},
		{"-empty_label", args{"example.."}, false},
		{"+minimal_label", args{"a.com"}, true},
		{"+punycode", args{"xn--bcher-kva.com"}, true},
		{"+localhost", args{"localhost"}, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := IsDNSName(tt.args.host)

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("IsDNSName() = %s", diff)
			}
		})
	}
}
