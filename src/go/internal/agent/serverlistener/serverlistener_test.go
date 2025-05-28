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
	"fmt"
	"net"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/agent2/internal/agent"
	mockscheduler "golang.zabbix.com/agent2/internal/agent/scheduler/mocks"
	"golang.zabbix.com/agent2/pkg/version"
	mockcomms "golang.zabbix.com/agent2/pkg/zbxcomms/mocks"
	"golang.zabbix.com/sdk/errs"
)

func Test_ParseListenIP(t *testing.T) {
	t.Parallel()

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

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			opts := &agent.AgentOptions{ListenIP: tt.listenIP}
			got, err := parseListenIP(opts, tt.mockIPs)

			if (err != nil) != tt.expectErr {
				t.Fatalf("ParseListenIP() error = %v, expectErr = %v", err, tt.expectErr)
			}

			if !tt.expectErr {
				if diff := cmp.Diff(tt.want, got); diff != "" {
					t.Errorf("ParseListenIP() mismatch (-want +got):\n%s", diff)
				}
			}
		})
	}
}

func Test_processPlainTextRequest(t *testing.T) {
	t.Parallel()

	type helperFunc func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler)

	type testCase struct {
		name  string
		key   string
		setup helperFunc
	}

	tests := []testCase{
		{
			name: "+successfulWrite",
			key:  "system.hostname",
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler) {
				t.Helper()

				result := "zabbix-agent"
				sched.
					On("PerformTask", "system.hostname", time.Minute, uint64(agent.PassiveChecksClientID)).
					Return(&result, nil)

				conn.On("Write", []byte(result)).Return(nil)
			},
		},
		{
			name: "-failedPerformTaskReturnsError",
			key:  "system.uptime",
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler) {
				t.Helper()

				sched.
					On("PerformTask", "system.uptime", time.Minute, uint64(agent.PassiveChecksClientID)).
					Return(nil, errs.New("task failed"))

				conn.On("Write", formatError("Task failed.")).Return(nil)
			},
		},
		{
			name: "-failedPerformTaskReturnsNil",
			key:  "system.cpu.load",
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler) {
				t.Helper()

				sched.
					On("PerformTask", "system.cpu.load", time.Minute, uint64(agent.PassiveChecksClientID)).
					Return(nil, nil)
			},
		},
		{
			name: "-failedWrite",
			key:  "system.hostname",
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler) {
				t.Helper()

				result := "zabbix-agent"
				sched.
					On("PerformTask", "system.hostname", time.Minute, uint64(agent.PassiveChecksClientID)).
					Return(&result, nil)
				conn.On("Write", []byte(result)).Return(errs.New("write failed"))
				conn.On("RemoteIP").Return("127.0.0.1")
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			mockSched := mockscheduler.NewScheduler(t)
			mockConn := mockcomms.NewConnectionInterface(t)

			tt.setup(t, mockConn, mockSched)

			processPlainTextRequest(mockConn, mockSched, tt.key)

			mockSched.AssertExpectations(t)
			mockConn.AssertExpectations(t)
		})
	}
}

func Test_sendJSONParsingErrorResponse(t *testing.T) {
	t.Parallel()

	type helperFunc func(t *testing.T, conn *mockcomms.ConnectionInterface)

	type testCase struct {
		name    string
		errText string
		setup   helperFunc
	}

	expectedJSON := func(errorMsg string) []byte {
		return []byte(fmt.Sprintf(
			`{"version":"%s","variant":%d,"error":"%s"}`,
			version.Long(),
			agent.Variant,
			errorMsg,
		))
	}

	tests := []testCase{
		{
			name:    "+valid",
			errText: "invalid JSON syntax",
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface) {
				t.Helper()
				conn.On("Write", expectedJSON("invalid JSON syntax")).Return(nil)
			},
		},
		{
			name:    "+emptyErrorString",
			errText: "",
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface) {
				t.Helper()
				conn.On("Write", expectedJSON("")).Return(nil)
			},
		},
		{
			name:    "-failedWrite",
			errText: "server-side issue",
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface) {
				t.Helper()

				conn.On("Write", expectedJSON("server-side issue")).Return(errs.New("write failed"))
				conn.On("RemoteIP").Return("127.0.0.1")
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			conn := mockcomms.NewConnectionInterface(t)
			tt.setup(t, conn)

			sendJSONParsingErrorResponse(conn, tt.errText)

			conn.AssertExpectations(t)
		})
	}
}

func Test_sendTaskErrorResponse(t *testing.T) {
	t.Parallel()

	type helperFunc func(t *testing.T, conn *mockcomms.ConnectionInterface)

	type testCase struct {
		name    string
		errText string
		isJSON  bool
		setup   helperFunc
	}

	expectedPayload := func(errText string, isJSON bool) []byte {
		if isJSON {
			return []byte(fmt.Sprintf(
				`{"version":"%s","variant":%d,"data":[{"error":"%s"}]}`,
				version.Long(),
				agent.Variant,
				errText,
			))
		}

		return append([]byte("ZBX_NOTSUPPORTED\x00"), []byte(errText)...)
	}

	tests := []testCase{
		{
			name:    "+plainText",
			errText: "invalid item key",
			isJSON:  false,
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface) {
				t.Helper()
				conn.On("Write", expectedPayload("invalid item key", false)).Return(nil)
			},
		},
		{
			name:    "+JSON",
			errText: "timeout reached",
			isJSON:  true,
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface) {
				t.Helper()
				conn.On("Write", expectedPayload("timeout reached", true)).Return(nil)
			},
		},
		{
			name:    "-writeFails",
			errText: "write error",
			isJSON:  true,
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface) {
				t.Helper()
				conn.On("Write", expectedPayload("write error", true)).Return(errs.New("write failed"))
				conn.On("RemoteIP").Return("127.0.0.1")
			},
		},
	}

	for _, tt := range tests {
		tt := tt // capture range variable
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			conn := mockcomms.NewConnectionInterface(t)
			tt.setup(t, conn)

			sendTaskErrorResponse(conn, tt.errText, tt.isJSON)

			conn.AssertExpectations(t)
		})
	}
}
