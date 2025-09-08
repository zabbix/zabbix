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

package main

import (
	"net"
	"net/http"
	"testing"

	"github.com/google/go-cmp/cmp"
)

func Test_extractIPv4AddrFromHTTPReq(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		input   http.Request
		wantIP  net.IP
		wantErr bool
	}{
		{
			name:    "+validIPpv4WithPort", // there must always be a port in input data
			input:   http.Request{RemoteAddr: "192.168.56.201:51332"},
			wantIP:  net.ParseIP("192.168.56.201"),
			wantErr: false,
		},
		{
			name:    "+validLinkLocalIPv4WithPort",
			input:   http.Request{RemoteAddr: "169.254.100.101%Ethernet 3:50134"},
			wantIP:  net.ParseIP("169.254.100.101"),
			wantErr: false,
		},
		{
			name:    "-invalidIP",
			input:   http.Request{RemoteAddr: "127.0.0.0.1"},
			wantIP:  nil,
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			result, err := extractIPv4AddrFromHTTPReq(&tt.input)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"extractIPv4AddrFromHTTPReq() error = %v, wantErr %v", err, tt.wantErr,
				)
			}

			diff := cmp.Diff(tt.wantIP, result)
			if diff != "" {
				t.Fatalf("extractIPv4AddrFromHTTPReq() return value mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

// Due to a suspected bug in net.ResolveTCPAddr, it cannot handle IPv4 addresses with interfaces,
// for example, "169.254.100.101%Ethernet 3:34018". It is needed for on Windows.
// This test is expected when net.ResolveTCPAddr when will be fixed.
// The bug was registered here https://github.com/golang/go/issues/73071.
func Test_netResolveTCPAddr(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		input   string
		wantIP  net.IP
		wantErr bool
	}{
		{
			name:    "+validLinkLocalIPv4WithPort",
			input:   "169.254.100.101%Ethernet 3:34018",
			wantIP:  net.ParseIP("169.254.100.101"),
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			_, err := net.ResolveTCPAddr("tcp", tt.input)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"net.ResolveTCPAddr() error = %v, wantErr %v", err, tt.wantErr,
				)
			}
		})
	}
}
