/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package external

import (
	"errors"
	"net"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
)

const networkTCP4 = "tcp4"

type expected struct {
	connection bool
	checkError func(t *testing.T, err error)
	verify     func(t *testing.T, listener net.Listener)
}

func Test_getConnection(t *testing.T) {
	t.Parallel()

	type args struct {
		listener func(t *testing.T) net.Listener
		timeout  time.Duration
	}

	tests := []struct {
		name     string
		args     args
		setup    func(t *testing.T, listener net.Listener)
		expected expected
	}{
		{
			name: "+success",
			args: args{
				listener: newTestTCPListener,
				timeout:  5 * time.Second,
			},
			setup: setupTestConnection,
			expected: expected{
				connection: true,
			},
		},
		{
			name: "-acceptError",
			args: args{
				listener: newTestTCPListener,
				timeout:  5 * time.Second,
			},
			setup: setupClosedListener,
			expected: expected{
				checkError: func(t *testing.T, err error) {
					t.Helper()

					if !errors.Is(err, net.ErrClosed) {
						t.Fatalf(
							"getConnection() error = %v, want %v",
							err,
							net.ErrClosed,
						)
					}
				},
			},
		},
		{
			name: "-timeout",
			args: args{
				listener: newTestTCPListener,
				timeout:  50 * time.Millisecond,
			},
			expected: expected{
				checkError: func(t *testing.T, err error) {
					t.Helper()

					if err == nil {
						t.Fatal("getConnection() error = nil, want timeout error")
					}

					want := "Failed to get connection within the time limit 50ms."

					diff := cmp.Diff(want, err.Error())
					if diff != "" {
						t.Fatalf(
							"getConnection() error mismatch (-want +got):\n%s",
							diff,
						)
					}
				},
				verify: verifyListenerReusable,
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			listener := tt.args.listener(t)

			if tt.setup != nil {
				tt.setup(t, listener)
			}

			connection, err := getConnection(listener, tt.args.timeout)

			tt.expected.assert(t, connection, err)

			if connection != nil {
				closeTestConnection(t, connection)
			}

			tt.expected.verifyListener(t, listener)
		})
	}
}

func (e expected) assert(
	t *testing.T,
	connection net.Conn,
	err error,
) {
	t.Helper()

	if e.checkError != nil {
		e.checkError(t, err)
	} else if err != nil {
		t.Fatalf("getConnection() error = %v", err)
	}

	if (connection != nil) == e.connection {
		return
	}

	if connection != nil {
		closeTestConnection(t, connection)
	}

	t.Fatalf(
		"getConnection() connection present = %v, want %v",
		connection != nil,
		e.connection,
	)
}

func (e expected) verifyListener(t *testing.T, listener net.Listener) {
	t.Helper()

	if e.verify != nil {
		e.verify(t, listener)
	}
}

func setupTestConnection(t *testing.T, listener net.Listener) {
	t.Helper()

	connection := dialTestConnection(t, listener)

	t.Cleanup(func() {
		closeTestConnection(t, connection)
	})
}

func setupClosedListener(t *testing.T, listener net.Listener) {
	t.Helper()

	err := listener.Close()
	if err != nil {
		t.Fatalf("net.Listener.Close() error = %v", err)
	}
}

func verifyListenerReusable(t *testing.T, listener net.Listener) {
	t.Helper()

	clientConnection := dialTestConnection(t, listener)

	t.Cleanup(func() {
		closeTestConnection(t, clientConnection)
	})

	serverConnection, err := getConnection(listener, 5*time.Second)
	if err != nil {
		t.Fatalf("getConnection() after timeout error = %v", err)
	}

	if serverConnection == nil {
		t.Fatal("getConnection() after timeout connection = nil")
	}

	closeTestConnection(t, serverConnection)
}

func dialTestConnection(t *testing.T, listener net.Listener) net.Conn {
	t.Helper()

	dialer := net.Dialer{
		Timeout: 5 * time.Second,
	}

	connection, err := dialer.DialContext(
		t.Context(),
		networkTCP4,
		listener.Addr().String(),
	)
	if err != nil {
		t.Fatalf("net.Dialer.DialContext() error = %v", err)
	}

	return connection
}

func closeTestConnection(t *testing.T, connection net.Conn) {
	t.Helper()

	err := connection.Close()
	if err != nil && !errors.Is(err, net.ErrClosed) {
		t.Errorf("net.Conn.Close() error = %v", err)
	}
}

func newTestTCPListener(t *testing.T) net.Listener {
	t.Helper()

	listenConfig := net.ListenConfig{}

	listener, err := listenConfig.Listen(
		t.Context(),
		networkTCP4,
		"127.0.0.1:0",
	)
	if err != nil {
		t.Fatalf("net.ListenConfig.Listen() error = %v", err)
	}

	t.Cleanup(func() {
		err := listener.Close()
		if err != nil && !errors.Is(err, net.ErrClosed) {
			t.Errorf("net.Listener.Close() error = %v", err)
		}
	})

	return listener
}
