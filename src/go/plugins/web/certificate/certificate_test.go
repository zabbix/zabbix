/*
** Copyright (C) 2001-2025 Zabbix SIA
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
package webcertificate

import (
	"testing"

	"golang.zabbix.com/sdk/uri"
)

func Test_getParameters(t *testing.T) {
	type args struct {
		params []string
	}
	tests := []struct {
		name         string
		args         args
		wantHostname string
		wantPort     string
		wantDomain   string
		wantErr      bool
	}{
		{"+only_hostname", args{[]string{"example.com"}}, "example.com", "443", "example.com", false},
		{"+only_ip", args{[]string{"127.0.0.1"}}, "127.0.0.1", "443", "127.0.0.1", false},
		{"+full", args{[]string{"example.com", "443", "127.0.0.1"}}, "127.0.0.1", "443", "example.com", false},
		{"+empty_port", args{[]string{"example.com", "", "127.0.0.1"}}, "127.0.0.1", "443", "example.com", false},
		{"+empty_port_and_ip", args{[]string{"example.com", "", ""}}, "example.com", "443", "example.com", false},
		{"+two_params", args{[]string{"example.com", "443"}}, "example.com", "443", "example.com", false},
		{"+two_params_port_with_host", args{[]string{"example.com:443", ""}}, "example.com", "443", "example.com", false},
		{"+two_params_full", args{[]string{"example.com:443", "443"}}, "example.com", "443", "example.com", false},
		{"-full_ipv6", args{[]string{"example.com", "443", "::1"}}, "", "", "", true},
		{"-full_ipv6_first", args{[]string{"::1", "443", ""}}, "", "", "", true},
		{"-too_many_params", args{[]string{"example.com", "443", "127.0.0.1", "foobar"}}, "", "", "", true},
		{"-too_few_params", args{[]string{}}, "", "", "", true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotHostname, gotPort, gotDomain, err := getParameters(tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("getParameters() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if gotHostname != tt.wantHostname {
				t.Errorf("getParameters() gotHostname = %v, want %v", gotHostname, tt.wantHostname)
			}
			if gotPort != tt.wantPort {
				t.Errorf("getParameters() gotPort = %v, want %v", gotPort, tt.wantPort)
			}
			if gotDomain != tt.wantDomain {
				t.Errorf("getParameters() gotDomain = %v, want %v", gotDomain, tt.wantDomain)
			}
		})
	}
}

func Test_parseParameters(t *testing.T) {
	type args struct {
		params []string
	}
	tests := []struct {
		name        string
		args        args
		wantAddress string
		wantPort    string
		wantDomain  string
		wantErr     bool
	}{
		{"+1st_param_hostname", args{[]string{"example.com", "443", "1.2.3.4"}}, "1.2.3.4", "443", "example.com", false},
		{"+1st_param_hostname_full",
			args{[]string{"https://www.example.com", "443", "1.2.3.4"}}, "1.2.3.4", "443", "www.example.com", false,
		},
		{"+3rd_param_hostname", args{[]string{"1.2.3.4", "443", "example.com"}}, "1.2.3.4", "443", "example.com", false},
		{"+1st_param_full",
			args{[]string{"https://1.2.3.4:443", "443", "example.com"}}, "1.2.3.4", "443", "example.com", false,
		},
		{"+empty_3rdParam", args{[]string{"example.com", "443", ""}}, "example.com", "443", "example.com", false},
		{"-ipv6", args{[]string{"example.com", "443", "::1"}}, "", "", "", true},
		{"-ipv6_brackets", args{[]string{"example.com", "443", "[::1]"}}, "", "", "", true},
		{"-not_ip", args{[]string{"example.com", "443", "www.example.com"}}, "example.com", "443", "www.example.com", false},
		{"-3rd_param_not_hostname", args{[]string{"1.2.3.4", "443", "https://www.example.com"}}, "", "", "", true},
		{"-1st_param_http", args{[]string{"http://1.2.3.4:443", "443", "example.com"}}, "", "", "", true},
		{"-not_http", args{[]string{"http://1.2.3.4:443", "443", "example.com"}}, "", "", "", true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotHost, gotPort, gotDomain, err := getParsedParameters(tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("parseParameters() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if gotHost != tt.wantAddress {
				t.Errorf("parseParameters() gotAddress = %v, want %v", gotHost, tt.wantAddress)
			}
			if gotPort != tt.wantPort {
				t.Errorf("parseParameters() gotPort = %v, want %v", gotPort, tt.wantPort)
			}
			if gotDomain != tt.wantDomain {
				t.Errorf("parseParameters() gotDomain = %v, want %v", gotDomain, tt.wantDomain)
			}
		})
	}
}

func Test_cutBrackets(t *testing.T) {
	type args struct {
		in string
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{"+ipv6", args{"[::1]"}, "::1"},
		{"+ipv4", args{"127.0.0.1"}, "127.0.0.1"},
		{"+empty", args{""}, ""},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := cutBrackets(tt.args.in); got != tt.want {
				t.Errorf("cutBrackets() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_parseURL(t *testing.T) {
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
		{"+ipv6_full", args{"https://::1:443", "443"}, "", "", true},
		{"+ipv6_full_brackets", args{"https://[::1]:443", "443"}, "", "", true},
		{"+ipv6_brackets", args{"[::1]", "443"}, "", "", true},
		{"+ipv6_port_both_brackets", args{"[::1]:443", "443"}, "", "", true},
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
			got, got1, err := parseURL(tt.args.url, tt.args.port)
			if (err != nil) != tt.wantErr {
				t.Errorf("parseURL() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if got != tt.host {
				t.Errorf("parseURL() got = %v, want %v", got, tt.host)
			}
			if got1 != tt.port {
				t.Errorf("parseURL() got1 = %v, want %v", got1, tt.port)
			}
		})
	}
}

func Test_getHostAndPort(t *testing.T) {
	type args struct {
		rawUri string
		port   string
	}
	tests := []struct {
		name     string
		args     args
		wantHost string
		wantPort string
		wantErr  bool
	}{
		{"+full", args{"https://example.com:443", "443"}, "example.com", "443", false},
		{"+empty_port", args{"example.com:443", ""}, "example.com", "443", false},
		{"+no_port", args{"example.com", ""}, "example.com", "443", false},
		{"-port_mismatch", args{"example.com:443", "332"}, "", "", true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u, _ := uri.New(tt.args.rawUri, nil)
			got, got1, err := getHostAndPort(u, tt.args.port)
			if (err != nil) != tt.wantErr {
				t.Errorf("getHostAndPort() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if got != tt.wantHost {
				t.Errorf("getHostAndPort() host = %v, want %v", got, tt.wantHost)
			}
			if got1 != tt.wantPort {
				t.Errorf("getHostAndPort() port = %v, want %v", got1, tt.wantPort)
			}
		})
	}
}
