/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package tcpudp

import (
	"testing"
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
