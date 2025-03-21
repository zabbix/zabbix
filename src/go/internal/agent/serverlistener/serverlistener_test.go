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

package serverlistener

import (
	"net"
	"reflect"
	"testing"

	"golang.zabbix.com/agent2/internal/agent"
)

//nolint:tparallel
func Test_ParseListenIP(t *testing.T) {
	t.Parallel()

	originalGetter := getLocalIPs
	defer func() { getLocalIPs = originalGetter }()

	tests := []struct {
		name      string
		listenIP  string
		mockIPs   []net.IP
		want      []string
		expectErr bool
	}{
		{
			name:     "+emptyInput",
			listenIP: "",
			want:     []string{"0.0.0.0"},
		},
		{
			name:     "+wildcardInput",
			listenIP: "0.0.0.0",
			want:     []string{"0.0.0.0"},
		},
		{
			name:     "+singleValidIP",
			listenIP: "192.168.1.10",
			mockIPs:  []net.IP{net.ParseIP("192.168.1.10")},
			want:     []string{"192.168.1.10"},
		},
		{
			name:     "+loopback",
			listenIP: "127.0.0.1",
			mockIPs:  []net.IP{},
			want:     []string{"127.0.0.1"},
		},
		{
			name:     "+multipleValidIPs",
			listenIP: "192.168.1.10,10.0.0.1",
			mockIPs:  []net.IP{net.ParseIP("192.168.1.10"), net.ParseIP("10.0.0.1")},
			want:     []string{"192.168.1.10", "10.0.0.1"},
		},
		{
			name:      "-ipNotInList",
			listenIP:  "8.8.8.8",
			mockIPs:   []net.IP{net.ParseIP("192.168.1.10")},
			expectErr: true,
		},
		{
			name:      "-invalidIPFormat",
			listenIP:  "not-an-ip",
			mockIPs:   []net.IP{net.ParseIP("192.168.1.10")},
			expectErr: true,
		},
		{
			name:      "-mixedValidAndInvalid",
			listenIP:  "192.168.1.10,8.8.8.8",
			mockIPs:   []net.IP{net.ParseIP("192.168.1.10")},
			expectErr: true,
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			// Should not run in parallel. Accesses global getLocalIPs.
			getLocalIPs = func() []net.IP {
				return tt.mockIPs
			}

			opts := &agent.AgentOptions{ListenIP: tt.listenIP}
			got, err := ParseListenIP(opts)

			if (err != nil) != tt.expectErr {
				t.Fatalf("ParseListenIP() error = %v, expectErr = %v", err, tt.expectErr)
			}

			if !tt.expectErr && !reflect.DeepEqual(got, tt.want) {
				t.Errorf("ParseListenIP() = %v, want = %v", got, tt.want)
			}
		})
	}
}

func Test_getListLocalIP(t *testing.T) {
	t.Parallel()

	_ = getListLocalIP()
}
