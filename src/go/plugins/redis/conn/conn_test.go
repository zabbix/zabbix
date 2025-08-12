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

package conn

import (
	"reflect"
	"testing"
	"time"

	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/uri"
)

var unitLogger = log.New("unit test logger") //nolint:gochecknoglobals // logger just for testing.

func TestManager_closeUnused(t *testing.T) {
	t.Parallel()

	connMgr := NewManager(unitLogger, 1*time.Microsecond, 30*time.Second, HouseKeeperInterval*time.Second)

	t.Cleanup(func() {
		connMgr.Destroy()
	})

	u, _ := uri.New("tcp://127.0.0.1", nil)
	_, _ = connMgr.create(createConnKey(u, map[string]string{}))

	t.Run("Unused connections should have been deleted", func(t *testing.T) {
		t.Parallel()

		connMgr.closeUnused()

		if len(connMgr.connections) != 0 {
			t.Errorf("connMgr.connections expected to be empty, but actual length is %d", len(connMgr.connections))
		}
	})
}

func TestManager_closeAll(t *testing.T) {
	t.Parallel()

	connMgr := NewManager(unitLogger, 300*time.Second, 30*time.Second, HouseKeeperInterval*time.Second)

	t.Cleanup(func() {
		connMgr.Destroy()
	})

	u, _ := uri.New("tcp://127.0.0.1", nil)
	_, _ = connMgr.create(createConnKey(u, map[string]string{}))

	t.Run("All connections should have been deleted", func(t *testing.T) {
		t.Parallel()

		connMgr.closeAll()

		if len(connMgr.connections) != 0 {
			t.Errorf("connMgr.connections expected to be empty, but actual length is %d", len(connMgr.connections))
		}
	})
}

func TestManager_create(t *testing.T) {
	t.Parallel()

	u, _ := uri.New("tcp://127.0.0.1", nil)

	connMgr := NewManager(unitLogger, 300*time.Second, 30*time.Second, HouseKeeperInterval*time.Second)

	t.Cleanup(func() {
		connMgr.Destroy()
	})

	connMgr.connections[*createConnKey(u, map[string]string{})] = NewRedisConn(radix.Stub("", "", nil))

	type args struct {
		uri *uri.URI
	}

	tests := []struct {
		name      string
		c         *Manager
		args      args
		want      *RedisConn
		wantErr   bool
		wantPanic bool
	}{
		{
			name:      "-connectionExists",
			c:         connMgr,
			args:      args{uri: u},
			want:      nil,
			wantErr:   false,
			wantPanic: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			if tt.wantPanic {
				defer func() {
					if r := recover(); r == nil {
						t.Error("Manager.create() must panic with runtime error")
					}
				}()
			}

			got, err := tt.c.create(createConnKey(tt.args.uri, map[string]string{}))

			if (err != nil) != tt.wantErr {
				t.Errorf("Manager.create() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			if reflect.TypeOf(got) != reflect.TypeOf(tt.want) {
				t.Errorf("Manager.create() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestManager_get(t *testing.T) {
	t.Parallel()

	u, _ := uri.New("tcp://127.0.0.1", nil)

	connMgr := NewManager(unitLogger, 300*time.Second, 30*time.Second, HouseKeeperInterval*time.Second)

	t.Cleanup(func() {
		connMgr.Destroy()
	})

	//nolint:paralleltest //should be done before attempt to make connection is made
	t.Run("Should return nil if connection does not exist", func(t *testing.T) {
		if got := connMgr.get(createConnKey(u, map[string]string{})); got != nil {
			t.Errorf("Manager.get() = %v, want <nil>", got)
		}
	})

	stubClient := radix.Stub("", "", nil)

	lastTimeAccess := time.Now()
	conn := &RedisConn{
		client:         stubClient,
		lastTimeAccess: lastTimeAccess,
	}

	connMgr.connections[*createConnKey(u, map[string]string{})] = conn

	t.Run("Should return connection if it exists", func(t *testing.T) {
		t.Parallel()

		got := connMgr.get(createConnKey(u, map[string]string{}))

		// has to return the same pointer.
		if conn != got {
			t.Errorf("Manager.get() = %v, want %v", got, conn)
		}

		if lastTimeAccess.Equal(got.lastTimeAccess) {
			t.Error("conn.lastTimeAccess should be updated, but it's not")
		}
	})
}
