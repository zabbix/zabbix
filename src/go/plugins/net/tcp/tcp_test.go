/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

func Test_isValidPort(t *testing.T) {
	type args struct {
		port string
	}
	tests := []struct {
		name string
		args args
		want bool
	}{
		{"+basic", args{"443"}, true},
		{"+empty", args{""}, true},
		{"-negative", args{"-1"}, false},
		{"-zero", args{"0"}, false},
		{"-out_of_range", args{"65536"}, false},
		{"-malformed", args{"44ava3"}, false},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := isValidPort(tt.args.port); got != tt.want {
				t.Errorf("isValidPort() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_splitAndRemovePort(t *testing.T) {
	type args struct {
		in string
	}
	tests := []struct {
		name         string
		args         args
		wantScheme   string
		wantHostname string
		wantErr      bool
	}{
		{"+set_scheme", args{"https://example.com"}, "https", "example.com", false},
		{"+set_port", args{"example.com:443"}, "", "example.com", false},
		{"+full", args{"https://example.com:443/path1/path2"}, "https", "example.com/path1/path2", false},
		{"-no_scheme", args{"example.com"}, "", "example.com", false},
		{"-malformed_scheme", args{"https://https://example.com"}, "", "https://https://example.com", true},
		{"-malformed_port", args{"https://example.com:443:12121"}, "", "https://example.com:443:12121", true},
		{"-incorrect_port", args{"https://example.com:444ad"}, "", "https://example.com:444ad", true},
		{"-malformed_all", args{"https://https://example.com:443:12121"}, "", "https://https://example.com:443:12121", true},
		{"-empty", args{""}, "", "", false},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			scheme, hostname, err := splitAndRemovePort(tt.args.in)
			if (err != nil) != tt.wantErr {
				t.Errorf("splitAndRemovePort() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if scheme != tt.wantScheme {
				t.Errorf("splitAndRemovePort() scheme = %v, want %v", scheme, tt.wantScheme)
			}
			if hostname != tt.wantHostname {
				t.Errorf("splitAndRemovePort() hostname = %v, want %v", hostname, tt.wantHostname)
			}
		})
	}
}

func Test_buildURL(t *testing.T) {
	type args struct {
		scheme string
		ip     string
		port   string
	}
	tests := []struct {
		name    string
		args    args
		wantOut string
	}{
		{"+basic", args{"", "localHost", "443"}, "localHost:443"},
		{"+longPath", args{"", "localHost/path1/path2/path3", "443"}, "localHost:443/path1/path2/path3"},
		{"+http", args{"http", "www.localHost.com/path1/path2", "443"}, "http://www.localHost.com:443/path1/path2"},
		{"+https", args{"https", "localHost/path1/path2/path3", "443"}, "https://localHost:443/path1/path2/path3"},
		{"+ip", args{"https", "127.0.0.0/path1", "443"}, "https://127.0.0.0:443/path1"},
		{"+trailingSlash", args{"https", "localHost/path1/", "443"}, "https://localHost:443/path1/"},
		{"-emptyScheme", args{"", "www.localHost.com/path1", "443"}, "www.localHost.com:443/path1"},
		{"-emptyUrl", args{"https", "", "443"}, ""},
		{"-emptyUrlAndPort", args{"https", "", ""}, ""},
		{"-emptyPort", args{"https", "localHost/path1", ""}, "https://localHost/path1"},
		{"-emptyPortAndScheme", args{"", "localHost/path1", ""}, "localHost/path1"},
		{"-emptyScheme", args{"", "localHost/path1", ""}, "localHost/path1"},
		{"-emptySchemeAndUrl", args{"", "", "443"}, ""},
		{"-emptyAll", args{"", "", ""}, ""},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := buildURL(tt.args.scheme, tt.args.ip, tt.args.port); gotOut != tt.wantOut {
				t.Errorf("buildURL() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}
