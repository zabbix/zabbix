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

package uri

import (
	"reflect"
	"testing"
)

func TestURI_Addr(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		rawQuery string
		socket   string
		user     string
		password string
		rawUri   string
	}
	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return host:port",
			fields{host: "127.0.0.1", port: "8003"},
			"127.0.0.1:8003",
		},
		{
			"Should return socket",
			fields{host: "127.0.0.1", port: "8003", socket: "/var/lib/mysql/mysql.sock"},
			"/var/lib/mysql/mysql.sock",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				rawQuery: tt.fields.rawQuery,
				socket:   tt.fields.socket,
				user:     tt.fields.user,
				password: tt.fields.password,
				rawUri:   tt.fields.rawUri,
			}
			if got := u.Addr(); got != tt.want {
				t.Errorf("Addr() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestURI_String(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		rawQuery string
		socket   string
		user     string
		password string
		rawUri   string
	}
	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return URI with creds. Test 1",
			fields{scheme: "https", host: "127.0.0.1", port: "8003", user: "zabbix",
				password: "a35c2787-6ab4-4f6b-b538-0fcf91e678ed"},
			"https://zabbix:a35c2787-6ab4-4f6b-b538-0fcf91e678ed@127.0.0.1:8003",
		},
		{
			"Should return URI with creds. Test 2",
			fields{scheme: "unix", socket: "/tmp/redis.sock", user: "zabbix", password: "secret"},
			"unix://zabbix:secret@/tmp/redis.sock",
		},
		{
			"Should return URI with user only",
			fields{scheme: "unix", socket: "/tmp/redis.sock", user: "zabbix"},
			"unix://zabbix@/tmp/redis.sock",
		},
		{
			"Should return URI with creds containing special characters",
			fields{scheme: "https", host: "127.0.0.1", port: "8003", user: "zabbix",
				password: `!@#$%^&*()_+{}?|\/., -=_+`},
			"https://zabbix:%21%40%23$%25%5E&%2A%28%29_+%7B%7D%3F%7C%5C%2F.,%20-=_+@127.0.0.1:8003",
		},
		{
			"Should return URI with username",
			fields{scheme: "https", host: "127.0.0.1", port: "8003", user: "zabbix"},
			"https://zabbix@127.0.0.1:8003",
		},
		{
			"Should return URI without creds",
			fields{scheme: "https", host: "127.0.0.1", port: "8003"},
			"https://127.0.0.1:8003",
		},
		{
			"Should return URI with path",
			fields{scheme: "oracle", host: "127.0.0.1", port: "1521", rawQuery: "dbname=XE"},
			"oracle://127.0.0.1:1521?dbname=XE",
		},
		{
			"Should return URI without port",
			fields{scheme: "https", host: "127.0.0.1"},
			"https://127.0.0.1",
		},
		{
			"Should return URI with socket",
			fields{scheme: "unix", socket: "/var/lib/mysql/mysql.sock"},
			"unix:///var/lib/mysql/mysql.sock",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				rawQuery: tt.fields.rawQuery,
				socket:   tt.fields.socket,
				user:     tt.fields.user,
				password: tt.fields.password,
				rawUri:   tt.fields.rawUri,
			}
			if got := u.String(); got != tt.want {
				t.Errorf("String() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestURI_NoQueryString(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		rawQuery string
		socket   string
		user     string
		password string
		rawUri   string
		path     string
	}
	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return URI with creds. Test 1",
			fields{scheme: "https", host: "127.0.0.1", port: "8003", user: "zabbix",
				password: "a35c2787-6ab4-4f6b-b538-0fcf91e678ed"},
			"https://zabbix:a35c2787-6ab4-4f6b-b538-0fcf91e678ed@127.0.0.1:8003",
		},
		{
			"Should return URI with creds. Test 2",
			fields{scheme: "unix", socket: "/tmp/redis.sock", user: "zabbix", password: "secret"},
			"unix://zabbix:secret@/tmp/redis.sock",
		},
		{
			"Should return URI with user only",
			fields{scheme: "unix", socket: "/tmp/redis.sock", user: "zabbix"},
			"unix://zabbix@/tmp/redis.sock",
		},
		{
			"Should return URI with creds containing special characters",
			fields{scheme: "https", host: "127.0.0.1", port: "8003", user: "zabbix",
				password: `!@#$%^&*()_+{}?|\/., -=_+`},
			"https://zabbix:%21%40%23$%25%5E&%2A%28%29_+%7B%7D%3F%7C%5C%2F.,%20-=_+@127.0.0.1:8003",
		},
		{
			"Should return URI with username",
			fields{scheme: "https", host: "127.0.0.1", port: "8003", user: "zabbix"},
			"https://zabbix@127.0.0.1:8003",
		},
		{
			"Should return URI without creds",
			fields{scheme: "https", host: "127.0.0.1", port: "8003"},
			"https://127.0.0.1:8003",
		},
		{
			"Should return URI with path and no query",
			fields{scheme: "oracle", host: "127.0.0.1", port: "1521", rawQuery: "dbname=XE"},
			"oracle://127.0.0.1:1521",
		},
		{
			"Should return URI without port",
			fields{scheme: "https", host: "127.0.0.1"},
			"https://127.0.0.1",
		},
		{
			"Should return URI with socket",
			fields{scheme: "unix", socket: "/var/lib/mysql/mysql.sock"},
			"unix:///var/lib/mysql/mysql.sock",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				rawQuery: tt.fields.rawQuery,
				socket:   tt.fields.socket,
				user:     tt.fields.user,
				password: tt.fields.password,
				rawUri:   tt.fields.rawUri,
				path:     tt.fields.path,
			}
			if got := u.NoQueryString(); got != tt.want {
				t.Errorf("URI.NoQueryString() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestURI_string(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		rawQuery string
		socket   string
		user     string
		password string
		rawUri   string
	}
	type args struct {
		query string
	}
	tests := []struct {
		name   string
		fields fields
		args   args
		want   string
	}{
		{
			"Should return URI with creds. Test 1",
			fields{scheme: "https", host: "127.0.0.1", port: "8003", user: "zabbix",
				password: "a35c2787-6ab4-4f6b-b538-0fcf91e678ed"},
			args{""},
			"https://zabbix:a35c2787-6ab4-4f6b-b538-0fcf91e678ed@127.0.0.1:8003",
		},
		{
			"Should return URI with creds. Test 2",
			fields{scheme: "unix", socket: "/tmp/redis.sock", user: "zabbix", password: "secret"},
			args{""},
			"unix://zabbix:secret@/tmp/redis.sock",
		},
		{
			"Should return URI with user only",
			fields{scheme: "unix", socket: "/tmp/redis.sock", user: "zabbix"},
			args{""},
			"unix://zabbix@/tmp/redis.sock",
		},
		{
			"Should return URI with creds containing special characters",
			fields{scheme: "https", host: "127.0.0.1", port: "8003", user: "zabbix",
				password: `!@#$%^&*()_+{}?|\/., -=_+`},
			args{""},
			"https://zabbix:%21%40%23$%25%5E&%2A%28%29_+%7B%7D%3F%7C%5C%2F.,%20-=_+@127.0.0.1:8003",
		},
		{
			"Should return URI with username",
			fields{scheme: "https", host: "127.0.0.1", port: "8003", user: "zabbix"},
			args{""},
			"https://zabbix@127.0.0.1:8003",
		},
		{
			"Should return URI without creds",
			fields{scheme: "https", host: "127.0.0.1", port: "8003"},
			args{""},
			"https://127.0.0.1:8003",
		},
		{
			"Should return URI with path and no query",
			fields{scheme: "oracle", host: "127.0.0.1", port: "1521", rawQuery: "dbname=XE"},
			args{""},
			"oracle://127.0.0.1:1521",
		},
		{
			"Should return URI with path and with query",
			fields{scheme: "oracle", host: "127.0.0.1", port: "1521", rawQuery: "dbname=XE"},
			args{"dbname=XE"},
			"oracle://127.0.0.1:1521?dbname=XE",
		},
		{
			"Should return URI without port",
			fields{scheme: "https", host: "127.0.0.1"},
			args{""},
			"https://127.0.0.1",
		},
		{
			"Should return URI with socket",
			fields{scheme: "unix", socket: "/var/lib/mysql/mysql.sock"},
			args{""},
			"unix:///var/lib/mysql/mysql.sock",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				rawQuery: tt.fields.rawQuery,
				socket:   tt.fields.socket,
				user:     tt.fields.user,
				password: tt.fields.password,
				rawUri:   tt.fields.rawUri,
			}
			if got := u.string(tt.args.query); got != tt.want {
				t.Errorf("string() = %v, want %v", got, tt.want)
			}
		})
	}
}

var (
	defaults              = &Defaults{Scheme: "https", Port: "443"}
	defaultsWithoutPort   = &Defaults{Scheme: "https"}
	defaultsWithoutScheme = &Defaults{Port: "443"}
	emptyDefaults         = &Defaults{}
	invalidDefaults       = &Defaults{Port: "99999"}
)

func TestNew(t *testing.T) {
	type args struct {
		rawuri   string
		defaults *Defaults
	}
	tests := []struct {
		name    string
		args    args
		wantRes *URI
		wantErr bool
	}{
		{
			"Parse URI with scheme and port, defaults are not set",
			args{"http://localhost:80", nil},
			&URI{scheme: "http", host: "localhost", port: "80", rawUri: "http://localhost:80"},
			false,
		},
		{
			"Parse URI with scheme, path and port, defaults are not set",
			args{"http://localhost:80/foo/bar", nil},
			&URI{scheme: "http", host: "localhost", port: "80", rawUri: "http://localhost:80/foo/bar", path: "/foo/bar"},
			false,
		},
		{
			"Parse URI without scheme and port, defaults are not set",
			args{"localhost", nil},
			&URI{scheme: "tcp", host: "localhost", rawUri: "localhost"},
			false,
		},
		{
			"Parse URI without scheme and port, defaults are empty",
			args{"localhost", emptyDefaults},
			&URI{scheme: "tcp", host: "localhost", rawUri: "localhost"},
			false,
		},
		{
			"Parse URI without scheme and port, defaults are fully set",
			args{"localhost", defaults},
			&URI{scheme: "https", host: "localhost", port: "443", rawUri: "localhost"},
			false,
		},
		{
			"Parse URI without scheme and port, defaults are partly set (only scheme)",
			args{"localhost", defaultsWithoutPort},
			&URI{scheme: "https", host: "localhost", rawUri: "localhost"},
			false,
		},
		{
			"Parse URI without scheme and port, defaults are partly set (only port)",
			args{"localhost", defaultsWithoutScheme},
			&URI{scheme: "tcp", host: "localhost", port: "443", rawUri: "localhost"},
			false,
		},
		{
			"Must fail if defaults are invalid",
			args{"localhost", invalidDefaults},
			nil,
			true,
		},
		{
			"Must fail if scheme is omitted",
			args{"://localhost", nil},
			nil,
			true,
		},
		{
			"Must fail if host is omitted",
			args{"tcp://:1521", nil},
			nil,
			true,
		},
		{
			"Must fail if port is greater than 65535",
			args{"tcp://localhost:65536", nil},
			nil,
			true,
		},
		{
			"Must fail if port is not integer",
			args{"tcp://:foo", nil},
			nil,
			true,
		},
		{
			"Should fail if URI is invalid",
			args{"!@#$%^&*()", nil},
			nil,
			true,
		},
		{
			"Parse URI with query params",
			args{"oracle://localhost:1521?dbname=XE", nil},
			&URI{scheme: "oracle", host: "localhost", port: "1521", rawQuery: "dbname=XE",
				rawUri: "oracle://localhost:1521?dbname=XE"},
			false,
		},
		{
			"Parse URI with unix scheme. Test 1",
			args{"unix:/var/run/memcached.sock", nil},
			&URI{scheme: "unix", socket: "/var/run/memcached.sock", rawUri: "unix:/var/run/memcached.sock"},
			false,
		},
		{
			"Parse URI with unix scheme. Test 2",
			args{"unix:///var/run/memcached.sock", nil},
			&URI{scheme: "unix", socket: "/var/run/memcached.sock", rawUri: "unix:///var/run/memcached.sock"},
			false,
		},
		{
			"Parse URI without unix scheme",
			args{"/var/run/memcached.sock", nil},
			&URI{scheme: "unix", socket: "/var/run/memcached.sock", rawUri: "/var/run/memcached.sock"},
			false,
		},
		{
			"Parse socket with query params",
			args{"/var/run/memcached.sock?dbname=postgres", nil},
			&URI{scheme: "unix", socket: "/var/run/memcached.sock",
				rawQuery: "dbname=postgres", rawUri: "/var/run/memcached.sock?dbname=postgres"},
			false,
		},
		{
			"Must fail if scheme is wrong",
			args{"tcp:///var/run/memcached.sock", nil},
			nil,
			true,
		},
		{
			"Must fail if socket is not specified",
			args{"unix://", nil},
			nil,
			true,
		},
		{
			"Parse URI with ipv6 address. Test 1",
			args{"tcp://[fe80::1ce7:d24a:97f0:3d83%25en0]:11211", nil},
			&URI{scheme: "tcp", host: "fe80::1ce7:d24a:97f0:3d83%en0", port: "11211",
				rawUri: "tcp://[fe80::1ce7:d24a:97f0:3d83%25en0]:11211"},
			false,
		},
		{
			"Parse URI with ipv6 address. Test 2",
			args{"tcp://[fe80::1ce7:d24a:97f0:3d83%en0]:11211", nil},
			&URI{scheme: "tcp", host: "fe80::1ce7:d24a:97f0:3d83%en0", port: "11211",
				rawUri: "tcp://[fe80::1ce7:d24a:97f0:3d83%en0]:11211"},
			false,
		},
		{
			"Parse URI with ipv6 address. Test 3",
			args{"tcp://[fe80::1%25lo0]:11211", nil},
			&URI{scheme: "tcp", host: "fe80::1%lo0", port: "11211", rawUri: "tcp://[fe80::1%25lo0]:11211"},
			false,
		},
		{
			"Parse URI with ipv6 address. Test 4",
			args{"https://[::1]", defaults},
			&URI{scheme: "https", host: "::1", port: "443", rawUri: "https://[::1]"},
			false,
		},
		{
			"Parse URI with ipv6 address. Test 5",
			args{"https://[::1]", nil},
			&URI{scheme: "https", host: "::1", rawUri: "https://[::1]"},
			false,
		},
		{
			"Parse URI with ipv6 address. Test 6",
			args{"tcp://fe80::1:11211", nil},
			&URI{scheme: "tcp", host: "fe80::1", port: "11211", rawUri: "tcp://fe80::1:11211"},
			false,
		},
		{
			"Parse URI with ipv6 address. Test 7",
			args{"tcp://::1:11289", nil},
			&URI{scheme: "tcp", host: "::1", port: "11289", rawUri: "tcp://::1:11289"},
			false,
		},
		{
			"Parse URI with whitespaces",
			args{"  http://localhost:80  ", nil},
			&URI{scheme: "http", host: "localhost", port: "80", rawUri: "http://localhost:80"},
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotRes, err := New(tt.args.rawuri, tt.args.defaults)
			if (err != nil) != tt.wantErr {
				t.Errorf("New() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if !reflect.DeepEqual(gotRes, tt.wantRes) {
				t.Errorf("New() gotRes = %#v, want %#v", gotRes, tt.wantRes)
			}
		})
	}
}

var (
	uri              = "ssh://localhost:22"
	uriWithoutScheme = "localhost:22"
	uriOnlyHost      = "localhost"
)

func TestURIValidator_Validate(t *testing.T) {
	type fields struct {
		Defaults       *Defaults
		AllowedSchemes []string
	}
	type args struct {
		value *string
	}
	tests := []struct {
		name    string
		fields  fields
		args    args
		wantErr bool
	}{
		{
			"Validate uri with scheme in specified range",
			fields{nil, []string{"ssh"}},
			args{&uri},
			false,
		},
		{
			"Validate uri, scheme is not limited",
			fields{nil, nil},
			args{&uriWithoutScheme},
			false,
		},
		{
			"Must fail if scheme is out of range",
			fields{nil, []string{"ssh"}},
			args{&uriWithoutScheme},
			true,
		},
		{
			"Must fail if default scheme is out of range",
			fields{defaults, []string{"ssh"}},
			args{&uriOnlyHost},
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			v := URIValidator{
				Defaults:       tt.fields.Defaults,
				AllowedSchemes: tt.fields.AllowedSchemes,
			}
			if err := v.Validate(tt.args.value); (err != nil) != tt.wantErr {
				t.Errorf("Validate() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func TestIsHostnameOnly(t *testing.T) {
	type args struct {
		host string
	}
	tests := []struct {
		name    string
		args    args
		wantErr bool
	}{
		{"valid_hostname", args{"example.com"}, false},
		{"valid_hostname_2", args{"www.example.com"}, false},
		{"ip", args{"1.2.3.4"}, false},
		{"full_url", args{"https://www.example.com/foo/bar.tst?foo=example&bar=test"}, true},
		{"scheme_url", args{"https://www.example.com"}, true},
		{"path", args{"www.example.com/foo/bar.tst"}, true},
		{"query", args{"www.example.com?foo=example&bar=test"}, true},
		{"user_and_password", args{"username:password@example.com/"}, true},
		{"port", args{"example.com:443"}, true},
		{"fake_port", args{"example.com:abc"}, true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if err := IsHostnameOnly(tt.args.host); (err != nil) != tt.wantErr {
				t.Errorf("IsHostnameOnly() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}
