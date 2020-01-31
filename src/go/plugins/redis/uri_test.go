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

package redis

import (
	"reflect"
	"testing"
)

func TestURI_Scheme(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		socket   string
		password string
	}
	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return scheme from URI structure",
			fields{scheme: "tcp"},
			"tcp",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				socket:   tt.fields.socket,
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
		socket   string
		password string
	}
	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return host:port from URI structure",
			fields{host: "127.0.0.1", port: "6379"},
			"127.0.0.1:6379",
		},
		{
			"Should return socket from URI structure",
			fields{socket: "unix:///var/run/redis.sock"},
			"unix:///var/run/redis.sock",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				socket:   tt.fields.socket,
				password: tt.fields.password,
			}
			if got := u.Addr(); got != tt.want {
				t.Errorf("URI.Addr() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestURI_Password(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		socket   string
		password string
	}
	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return password from URI structure",
			fields{password: "sEcReT"},
			"sEcReT",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				socket:   tt.fields.socket,
				password: tt.fields.password,
			}
			if got := u.Password(); got != tt.want {
				t.Errorf("URI.Password() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestURI_Uri(t *testing.T) {
	type fields struct {
		scheme   string
		host     string
		port     string
		socket   string
		password string
	}
	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Should return URI with creds",
			fields{scheme: "tcp", host: "127.0.0.1", port: "6379", password: "sEcReT"},
			"tcp://user:sEcReT@127.0.0.1:6379",
		},
		{
			"Should return URI without creds",
			fields{scheme: "tcp", host: "127.0.0.1", port: "6379"},
			"tcp://127.0.0.1:6379",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			u := &URI{
				scheme:   tt.fields.scheme,
				host:     tt.fields.host,
				port:     tt.fields.port,
				socket:   tt.fields.socket,
				password: tt.fields.password,
			}
			if got := u.Uri(); got != tt.want {
				t.Errorf("URI.Uri() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_newUriWithPassword(t *testing.T) {
	type args struct {
		uri      string
		password string
	}
	tests := []struct {
		name    string
		args    args
		wantU   URI
		wantErr bool
	}{
		{
			"Should return URI structure",
			args{"tcp://192.168.1.1:6380", "sEcReT"},
			URI{scheme: "tcp", host: "192.168.1.1", port: "6380", password: "sEcReT"},
			false,
		},
		{
			"Should return error if failed to parse URI",
			args{"://", "sEcReT"},
			URI{},
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotU, err := newUriWithCreds(tt.args.uri, tt.args.password)
			if (err != nil) != tt.wantErr {
				t.Errorf("newUriWithPassword() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(gotU, tt.wantU) {
				t.Errorf("newUriWithPassword() = %v, want %v", gotU, tt.wantU)
			}
		})
	}
}

func Test_parseUri(t *testing.T) {
	type args struct {
		uri string
	}
	tests := []struct {
		name    string
		args    args
		wantU   URI
		wantErr bool
	}{
		{
			"Parse URI with tcp scheme",
			args{"tcp://localhost:6379"},
			URI{scheme: "tcp", host: "localhost", port: "6379"},
			false,
		},
		{
			"Parse URI without scheme",
			args{"localhost:6379"},
			URI{},
			true,
		},
		{
			"Should fail when URI without host is passed",
			args{"tcp://:6379"},
			URI{},
			true,
		},
		{
			"Should fail if port is less than 1",
			args{"tcp://localhost:0"},
			URI{},
			true,
		},
		{
			"Should fail if port is greater than 65535",
			args{"tcp://localhost:99999"},
			URI{},
			true,
		},
		{
			"Should fail if port is not integer",
			args{"tcp://:foo"},
			URI{},
			true,
		},
		{
			"Parse URI without port",
			args{"tcp://localhost"},
			URI{scheme: "tcp", host: "localhost", port: "6379"},
			false,
		},
		{
			"Parse URI with unix scheme",
			args{"unix:///var/run/redis.sock"},
			URI{scheme: "unix", socket: "/var/run/redis.sock"},
			false,
		},
		{
			"Should fail if an unknown scheme is passed",
			args{"foo://bar"},
			URI{},
			true,
		},
		{
			"Should fail if a wrong format URI is passed",
			args{"!@#$%^&*()"},
			URI{},
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotU, err := parseUri(tt.args.uri)
			if (err != nil) != tt.wantErr {
				t.Errorf("parseUri() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(gotU, tt.wantU) {
				t.Errorf("parseUri() = %v, want %v", gotU, tt.wantU)
			}
		})
	}
}

func Test_validateUri(t *testing.T) {
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
			args{"tcp://localhost:6379"},
			false,
		},
		{
			"Should fail when a malformed URI is given",
			args{"tcp:/localhost:6379"},
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if err := validateUri(tt.args.uri); (err != nil) != tt.wantErr {
				t.Errorf("validateUri() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func Test_isLooksLikeUri(t *testing.T) {
	type args struct {
		s string
	}
	tests := []struct {
		name string
		args args
		want bool
	}{
		{
			"Should return true if it is URI with tcp scheme",
			args{"tcp://localhost:6379"},
			true,
		},
		{
			"Should return true if it is URI with unix scheme",
			args{"unix:/var/run/redis.sock"},
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
			if got := isLooksLikeUri(tt.args.s); got != tt.want {
				t.Errorf("isUri() = %v, want %v", got, tt.want)
			}
		})
	}
}
