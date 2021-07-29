/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
package webcertificate

import "testing"

func Test_validateURL(t *testing.T) {
	type args struct {
		url  string
		port string
	}
	tests := []struct {
		name    string
		args    args
		host    string
		port    string
		wantErr bool
	}{
		{"+port_in_param", args{"www.google.com", "443"}, "www.google.com", "443", false},
		{"+no_port_param", args{"www.google.com", ""}, "www.google.com", "443", false},
		{"+port_in_url", args{"www.google.com:443", ""}, "www.google.com", "443", false},
		{"+custom_port_in_url", args{"www.google.com:123", ""}, "www.google.com", "123", false},
		{"+port_in_both", args{"www.google.com:443", "443"}, "www.google.com", "443", false},
		{"+scheme", args{"https://www.google.com", ""}, "www.google.com", "443", false},
		{"+scheme_and_port_param", args{"https://www.google.com", "443"}, "www.google.com", "443", false},
		{"+scheme_and_port_in_url", args{"https://www.google.com:443", ""}, "www.google.com", "443", false},
		{"+scheme_and_custom_port_in_url", args{"https://www.google.com:123", ""}, "www.google.com", "123", false},
		{"+scheme_and_port_in_both", args{"https://www.google.com:443", "443"}, "www.google.com", "443", false},
		{"+path", args{"www.google.com/foo/bar", "443"}, "www.google.com", "443", false},
		{"+path_port", args{"www.google.com:443/foo/bar", "443"}, "www.google.com", "443", false},
		{"+full_path", args{"https://www.google.com:443/foo/bar", "443"}, "www.google.com", "443", false},
		{"+ip", args{"https://127.0.0.1:443", "443"}, "127.0.0.1", "443", false},
		{"-mismatch_port", args{"www.google.com:123", "443"}, "", "", true},
		{"-invalid_url", args{"www.google.com:999999", ""}, "", "", true},
		{"-invalid_scheme", args{"http://www.google.com", ""}, "", "", true},
		{"-invalid_scheme_and_mismatch_port", args{"http://www.google.com:8080", "443"}, "", "", true},
		{"-empty_url", args{"", "443"}, "", "", true},
		{"-only_port_in_url", args{":443", ""}, "", "", true},
		{"-empty", args{"", ""}, "", "", true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, got1, err := validateURL(tt.args.url, tt.args.port)
			if (err != nil) != tt.wantErr {
				t.Errorf("validateURL() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if got != tt.host {
				t.Errorf("validateURL() got = %v, want %v", got, tt.host)
			}
			if got1 != tt.port {
				t.Errorf("validateURL() got1 = %v, want %v", got1, tt.port)
			}
		})
	}
}
