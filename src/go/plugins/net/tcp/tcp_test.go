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

import "testing"

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
				t.Errorf("setPort() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}
