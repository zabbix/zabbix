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

func Test_IsRFCExtendedHostName(t *testing.T) {
	t.Parallel()

	// Synchronize with tests/libs/zbxip/zbx_is_rfc_extended_hostname.yaml

	type args struct {
		host string
	}

	tests := []struct {
		name string
		args args
		want bool
	}{
		{"+almostNumericalX92.168.10.4", args{"X92.168.10.4"}, true},
		{"+underscoreAtStart", args{"_example.com"}, true},
		{"+underscore", args{"example_com.com"}, true},
		{"+localhost", args{"localhost"}, true},
		{"+punycode", args{"xn--bcher-kva.com"}, true},
		{"+minimalLabel", args{"a.com"}, true},
		{"+trailingDot", args{"example.com."}, true},
		{"+labelIs63CharactersLong", args{"123456789-123456789-123456789-123456789-123456789-123456789-123"},
			true},
		{"+hostnameIs253CharactersLong", args{"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789.123"}, true},
		{"+hostnameIs254CharactersLongWithTrailingDot",
			args{"123456789-123456789-123456789-123456789-123456789." +
				"123456789-123456789-123456789-123456789-123456789." +
				"123456789-123456789-123456789-123456789-123456789." +
				"123456789-123456789-123456789-123456789-123456789." +
				"123456789-123456789-123456789-123456789-123456789.123."}, true},
		{"-emptyString", args{""}, false},
		{"-hostnameIs254CharactersLong", args{"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789." +
			"123456789-123456789-123456789-123456789-123456789.1234"}, false},
		{"-hostnameIs255CharactersLongWithDotAtPosition254",
			args{"123456789-123456789-123456789-123456789-123456789." +
				"123456789-123456789-123456789-123456789-123456789." +
				"123456789-123456789-123456789-123456789-123456789." +
				"123456789-123456789-123456789-123456789-123456789." +
				"123456789-123456789-123456789-123456789-123456789.123.5"}, false},
		{"-startsWithHyphen", args{"-"}, false},
		{"-leadingDot", args{".example.com"}, false},
		{"-labelStartsWithHyphen", args{"a.-a"}, false},
		{"-labelIsEmpty", args{"example.."}, false},
		{"-labelEndsWithHyphen", args{"a-.a"}, false},
		{"-labelIs64CharactersLong",
			args{"123456789-123456789-123456789-123456789-123456789-123456789-1234"}, false},
		{"-labelIsTooLong", args{"0123456789012345678901234567890123456789012345678901234567890123456789"},
			false},
		{"-space", args{"ex ample.com"}, false},
		{"-unicodeCharacter", args{"tēst.zabbix.com"}, false},
		{"-endsWithHyphen", args{"example.com-"}, false},
		{"-purelyNumeric192", args{"192"}, false},
		{"-purelyNumeric192.168", args{"192.168"}, false},
		{"-purelyNumeric192.168.10", args{"192.168.10"}, false},
		{"-purelyNumeric192.168.10.4", args{"192.168.10.4"}, false},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := IsRFCExtendedHostName(tt.args.host)

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("IsRFCExtendedHostName() = %s", diff)
			}
		})
	}
}
