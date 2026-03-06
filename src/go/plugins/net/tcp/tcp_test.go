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

package tcpudp

import (
	"testing"

	"github.com/google/go-cmp/cmp"
)

func Test_removeScheme(t *testing.T) {
	type args struct {
		in string
	}
	tests := []struct {
		name       string
		args       args
		wantScheme string
		wantHost   string
		wantErr    bool
	}{
		{"+base", args{"https://localhost"}, "https", "localhost", false},
		{"+full", args{"https://www.google.com:443/path1/path2"}, "https", "www.google.com:443/path1/path2", false},
		{"+no_scheme", args{"localhost"}, "", "localhost", false},
		{"+no_input", args{""}, "", "", false},
		{"-malformed", args{"https://https://localhost"}, "", "https://https://localhost", true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotScheme, gotHost, err := removeScheme(tt.args.in)
			if (err != nil) != tt.wantErr {
				t.Errorf("removeScheme() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if gotScheme != tt.wantScheme {
				t.Errorf("removeScheme() gotScheme = %v, want %v", gotScheme, tt.wantScheme)
			}
			if gotHost != tt.wantHost {
				t.Errorf("removeScheme() gotHost = %v, want %v", gotHost, tt.wantHost)
			}
		})
	}
}

func Test_encloseIPv6(t *testing.T) {
	type args struct {
		in string
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{"+basic", args{"::1"}, "[::1]"},
		{"+not_ip", args{"localhost:443"}, "localhost:443"},
		{"+already_enclosed", args{"[::1]"}, "[::1]"},
		{"+ipV4", args{"127.0.0.1"}, "127.0.0.1"},
		{"-empty", args{""}, ""},
		//unsupported cases for this function
		{"-with_port", args{"::1:443"}, "[::1:443]"},
		{"-with_scheme", args{"https://::1"}, "https://::1"},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := encloseIPv6(tt.args.in); got != tt.want {
				t.Errorf("encloseIPv6() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_loginReceived(t *testing.T) {
	type args struct {
		buf []byte
	}
	tests := []struct {
		name string
		args args
		want bool
	}{
		{"+basic", args{[]byte("foobar:            ")}, true},
		{"+no_trailing_space", args{[]byte("foobar:")}, true},
		{"+space_in_string", args{[]byte("foo     bar:               ")}, true},
		{"-not_login", args{[]byte("foobar")}, false},
		{"-empty", args{[]byte("")}, false},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := loginReceived(tt.args.buf); got != tt.want {
				t.Errorf("loginReceived() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_isDNS(t *testing.T) {
	t.Parallel()

	// Synchronize with tests/libs/zbxip/zbx_is_dns.yaml

	type args struct {
		host string
	}
	tests := []struct {
		name string
		args args
		want bool
	}{
		{"-empty", args{""}, false},
		{"-starts_with_dash", args{"-"}, false},
		{"-dash_before_sep", args{"a-.a"}, false},
		{"-starts_after_sep", args{"a.-a"}, false},
		{"-too_long_label", args{"0123456789012345678901234567890123456789012345678901234567890123456789"}, false},
		{"+label63", args{"123456789-123456789-123456789-123456789-123456789-123456789-123"}, true},
		{"-label64", args{"123456789-123456789-123456789-123456789-123456789-123456789-1234"}, false},
		{"+hostname253", args{"123456789-123456789-123456789-123456789-123456789.123456789-123456789-123456789-123456789-123456789.123456789-123456789-123456789-123456789-123456789.123456789-123456789-123456789-123456789-123456789.123456789-123456789-123456789-123456789-123456789.123"}, true},
		{"-label254", args{"123456789-123456789-123456789-123456789-123456789.123456789-123456789-123456789-123456789-123456789.123456789-123456789-123456789-123456789-123456789.123456789-123456789-123456789-123456789-123456789.123456789-123456789-123456789-123456789-123456789.1234"}, false},
		{"-unicode", args{"tēst.zabbix.com"}, false},
		{"-leading_dot", args{".example.com"}, false},
		{"-underscore", args{"example_com.com"}, false},
		{"-space", args{"ex ample.com"}, false},
		{"-end_with_dash", args{"example.com-"}, false},
		{"-empty_label", args{"example.."}, false},
		{"+min_label", args{"a.com"}, true},
		{"+punny_code", args{"xn--bcher-kva.com"}, true},
		{"+localhost", args{"localhost"}, true},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := isDNS(tt.args.host)
			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("isDNS() = %s", diff)
			}
		})
	}
}
