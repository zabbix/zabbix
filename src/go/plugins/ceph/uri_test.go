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

package ceph

import (
	"reflect"
	"testing"
)

func TestURI_Scheme(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		user     string
		password string
	}

	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return scheme from URI structure",
			fields{scheme: "https"},
			"https",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				user:     tt.fields.user,
				password: tt.fields.password,
			}
			if got := u.Scheme(); got != tt.want {
				t.Errorf("URI.Scheme() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestURI_Addr(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		user     string
		password string
	}

	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return host:port from URI structure",
			fields{host: "127.0.0.1", port: "8003"},
			"127.0.0.1:8003",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				user:     tt.fields.user,
				password: tt.fields.password,
			}
			if got := u.Addr(); got != tt.want {
				t.Errorf("URI.Addr() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestURI_User(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		user     string
		password string
	}

	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return username from URI structure",
			fields{user: "zabbix"},
			"zabbix",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				user:     tt.fields.user,
				password: tt.fields.password,
			}
			if got := u.User(); got != tt.want {
				t.Errorf("URI.User() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestURI_Password(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		user     string
		password string
	}

	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return password from URI structure",
			fields{password: "a35c2787-6ab4-4f6b-b538-0fcf91e678ed"},
			"a35c2787-6ab4-4f6b-b538-0fcf91e678ed",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				user:     tt.fields.user,
				password: tt.fields.password,
			}
			if got := u.Password(); got != tt.want {
				t.Errorf("URI.Password() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestURI_String(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		user     string
		password string
	}

	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return URI with creds",
			fields{scheme: "https", host: "127.0.0.1", port: "8003", user: "zabbix", password: "a35c2787-6ab4-4f6b-b538-0fcf91e678ed"},
			"https://zabbix:a35c2787-6ab4-4f6b-b538-0fcf91e678ed@127.0.0.1:8003/request?wait=1",
		},
		{
			"Should return URI without creds",
			fields{scheme: "https", host: "127.0.0.1", port: "8003"},
			"https://127.0.0.1:8003/request?wait=1",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				user:     tt.fields.user,
				password: tt.fields.password,
			}
			if got := u.String(); got != tt.want {
				t.Errorf("URI.String() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_newURIWithCreds(t *testing.T) {
	type args struct {
		uri      string
		user     string
		password string
	}

	tests := []struct {
		name    string
		args    args
		wantU   *URI
		wantErr bool
	}{
		{
			"Should return URI structure",
			args{"https://192.168.1.1:8003", "zabbix", "a35c2787-6ab4-4f6b-b538-0fcf91e678ed"},
			&URI{scheme: "https", host: "192.168.1.1", port: "8003", user: "zabbix", password: "a35c2787-6ab4-4f6b-b538-0fcf91e678ed"},
			false,
		},
		{
			"Should return error if failed to parse URI",
			args{"://", "zabbix", "a35c2787-6ab4-4f6b-b538-0fcf91e678ed"},
			nil,
			true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotU, err := newURIWithCreds(tt.args.uri, tt.args.user, tt.args.password)
			if (err != nil) != tt.wantErr {
				t.Errorf("newURIWithPassword() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(gotU, tt.wantU) {
				t.Errorf("newURIWithPassword() = %v, want %v", gotU, tt.wantU)
			}
		})
	}
}

func Test_parseURI(t *testing.T) {
	type args struct {
		uri string
	}

	tests := []struct {
		name    string
		args    args
		wantU   *URI
		wantErr bool
	}{
		{
			"Handle URI with https scheme",
			args{"https://localhost:8003"},
			&URI{scheme: "https", host: "localhost", port: "8003"},
			false,
		},
		{
			"Handle URI without scheme",
			args{"localhost:8003"},
			nil,
			true,
		},
		{
			"Handle URI with ipv6 address. Test 1",
			args{"https://[fe80::1ce7:d24a:97f0:3d83%25en0]:8003"},
			&URI{scheme: "https", host: "fe80::1ce7:d24a:97f0:3d83%en0", port: "8003"},
			false,
		},
		{
			"Handle URI with ipv6 address. Test 2",
			args{"https://[fe80::1ce7:d24a:97f0:3d83%en0]:8003"},
			&URI{scheme: "https", host: "fe80::1ce7:d24a:97f0:3d83%en0", port: "8003"},
			false,
		},
		{
			"Handle URI with ipv6 address. Test 3",
			args{"https://[fe80::1%25lo0]:8003"},
			&URI{scheme: "https", host: "fe80::1%lo0", port: "8003"},
			false,
		},
		{
			"Handle URI with ipv6 address. Test 4",
			args{"https://[::1]"},
			&URI{scheme: "https", host: "::1", port: "8003"},
			false,
		},
		{
			"Handle URI with ipv6 address. Test 5",
			args{"https://fe80::1:8003"},
			&URI{scheme: "https", host: "fe80::1", port: "8003"},
			false,
		},
		{
			"Handle URI with ipv6 address. Test 6",
			args{"https://::1:8003"},
			&URI{scheme: "https", host: "::1", port: "8003"},
			false,
		},
		{
			"Should fail when URI without host is passed",
			args{"https://:8003"},
			nil,
			true,
		},
		{
			"Should fail if port is greater than 65535",
			args{"https://localhost:65536"},
			nil,
			true,
		},
		{
			"Should fail if port is not integer",
			args{"https://:foo"},
			nil,
			true,
		},
		{
			"Handle URI without port",
			args{"https://localhost"},
			&URI{scheme: "https", host: "localhost", port: "8003"},
			false,
		},
		{
			"Should fail if a wrong format URI is passed",
			args{"!@#$%^&*()"},
			nil,
			true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotU, err := parseURI(tt.args.uri)
			if (err != nil) != tt.wantErr {
				t.Errorf("parseURI() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(gotU, tt.wantU) {
				t.Errorf("parseURI() = %v, want %v", gotU, tt.wantU)
			}
		})
	}
}

func Test_validateURI(t *testing.T) {
	type args struct {
		uri string
	}

	tests := []struct {
		name    string
		args    args
		wantErr bool
	}{
		{
			"Should pass when a valid URI is given",
			args{"https://localhost:8003"},
			false,
		},
		{
			"Should fail when a malformed URI is given",
			args{"https:/localhost:8003"},
			true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if err := validateURI(tt.args.uri); (err != nil) != tt.wantErr {
				t.Errorf("validateURI() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func Test_isLooksLikeURI(t *testing.T) {
	type args struct {
		s string
	}

	tests := []struct {
		name string
		args args
		want bool
	}{
		{
			"Should return true if it is URI with https scheme",
			args{"https://localhost:8003"},
			true,
		},
		{
			"Should return false if it is not URI",
			args{"testSession"},
			false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := isLooksLikeURI(tt.args.s); got != tt.want {
				t.Errorf("isLooksLikeURI() = %v, want %v", got, tt.want)
			}
		})
	}
}
