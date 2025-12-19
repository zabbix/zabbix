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
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"github.com/google/go-cmp/cmp/cmpopts"
	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/tlsconfig"
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
		u *connKey
	}

	tests := []struct {
		name      string
		args      args
		want      *RedisConn
		wantErr   bool
		wantPanic bool
	}{
		{
			name:      "-connectionExists",
			args:      args{u: createConnKey(u, map[string]string{})},
			want:      nil,
			wantErr:   false,
			wantPanic: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			defer func() {
				r := recover()
				if tt.wantPanic && r == nil {
					t.Fatalf("Manager.create() must panic with runtime error")
				}
			}()

			got, err := connMgr.create(tt.args.u)
			if (err != nil) != tt.wantErr {
				t.Fatalf("Manager.create() error = %v, wantErr %v", err, tt.wantErr)
			}

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("Manager.create() = %s", diff)
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

	stubClient := radix.Stub("", "", nil)

	lastTimeAccess := time.Now()
	conn := &RedisConn{
		client:         stubClient,
		lastTimeAccess: lastTimeAccess,
	}

	connMgr.connections[*createConnKey(u, map[string]string{})] = conn

	type args struct {
		u *connKey
	}

	tests := []struct {
		name string
		args args
		want *RedisConn
	}{
		{
			name: "+getConn",
			args: args{
				u: createConnKey(u, map[string]string{}),
			},
			want: conn,
		},
		{
			name: "-getConn",
			args: args{
				u: createConnKey(u, map[string]string{string(comms.TLSConnect): string(tlsconfig.Required)}),
			},
			want: nil,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := connMgr.get(tt.args.u)
			if diff := cmp.Diff(tt.want, got, cmpopts.IgnoreUnexported(RedisConn{})); diff != "" {
				t.Fatalf("Manager.get() = %s", diff)
			}
		})
	}
}
