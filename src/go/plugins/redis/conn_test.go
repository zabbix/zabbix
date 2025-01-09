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

package redis

import (
	"reflect"
	"testing"
	"time"

	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/sdk/uri"
)

func TestConnManager_closeUnused(t *testing.T) {
	connMgr := NewConnManager(1*time.Microsecond, 30*time.Second, hkInterval*time.Second)
	defer connMgr.Destroy()

	u, _ := uri.New("tcp://127.0.0.1", nil)
	_, _ = connMgr.create(*u)

	t.Run("Unused connections should have been deleted", func(t *testing.T) {
		connMgr.closeUnused()
		if len(connMgr.connections) != 0 {
			t.Errorf("connMgr.connections expected to be empty, but actual length is %d", len(connMgr.connections))
		}
	})
}

func TestConnManager_closeAll(t *testing.T) {
	connMgr := NewConnManager(300*time.Second, 30*time.Second, hkInterval*time.Second)
	defer connMgr.Destroy()

	u, _ := uri.New("tcp://127.0.0.1", nil)
	_, _ = connMgr.create(*u)

	t.Run("All connections should have been deleted", func(t *testing.T) {
		connMgr.closeAll()
		if len(connMgr.connections) != 0 {
			t.Errorf("connMgr.connections expected to be empty, but actual length is %d", len(connMgr.connections))
		}
	})
}

func TestConnManager_create(t *testing.T) {
	u, _ := uri.New("tcp://127.0.0.1", nil)

	connMgr := NewConnManager(300*time.Second, 30*time.Second, hkInterval*time.Second)
	defer connMgr.Destroy()

	connMgr.connections[*u] = &RedisConn{
		client:         radix.Stub("", "", nil),
		lastTimeAccess: time.Now(),
	}

	type args struct {
		uri uri.URI
	}

	tests := []struct {
		name      string
		c         *ConnManager
		args      args
		want      *RedisConn
		wantErr   bool
		wantPanic bool
	}{
		{
			name:      "Must panic if connection already exists",
			c:         connMgr,
			args:      args{uri: *u},
			want:      nil,
			wantErr:   false,
			wantPanic: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.wantPanic {
				defer func() {
					if r := recover(); r == nil {
						t.Error("ConnManager.create() must panic with runtime error")
					}
				}()
			}

			got, err := tt.c.create(tt.args.uri)

			if (err != nil) != tt.wantErr {
				t.Errorf("ConnManager.create() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if reflect.TypeOf(got) != reflect.TypeOf(tt.want) {
				t.Errorf("ConnManager.create() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestConnManager_get(t *testing.T) {
	u, _ := uri.New("tcp://127.0.0.1", nil)

	connMgr := NewConnManager(300*time.Second, 30*time.Second, hkInterval*time.Second)
	defer connMgr.Destroy()

	t.Run("Should return nil if connection does not exist", func(t *testing.T) {
		if got := connMgr.get(*u); got != nil {
			t.Errorf("ConnManager.get() = %v, want <nil>", got)
		}
	})

	lastTimeAccess := time.Now()
	conn := &RedisConn{
		client:         radix.Stub("", "", nil),
		lastTimeAccess: lastTimeAccess,
	}

	connMgr.connections[*u] = conn

	t.Run("Should return connection if it exists", func(t *testing.T) {
		got := connMgr.get(*u)
		if !reflect.DeepEqual(got, conn) {
			t.Errorf("ConnManager.get() = %v, want %v", got, conn)
		}
		if lastTimeAccess == got.lastTimeAccess {
			t.Error("conn.lastTimeAccess should be updated, but it's not")
		}
	})
}
