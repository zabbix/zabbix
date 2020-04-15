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

package memcached

import (
	"reflect"
	"testing"
	"time"
)

func TestConnManager_closeUnused(t *testing.T) {
	connMgr := NewConnManager(1*time.Microsecond, 30*time.Second, hkInterval*time.Second)
	defer connMgr.Destroy()

	uri, _ := parseURI("tcp://127.0.0.1")
	_ = connMgr.create(*uri)

	t.Run("Unused connections should have been deleted", func(t *testing.T) {
		connMgr.closeUnused()
		if len(connMgr.connections) != 0 {
			t.Errorf("connMgr.connections excpected to be empty, but actual length is %d", len(connMgr.connections))
		}
	})
}

func TestConnManager_closeAll(t *testing.T) {
	connMgr := NewConnManager(300*time.Second, 30*time.Second, hkInterval*time.Second)
	defer connMgr.Destroy()

	uri, _ := parseURI("tcp://127.0.0.1")
	_ = connMgr.create(*uri)

	t.Run("All connections should have been deleted", func(t *testing.T) {
		connMgr.closeAll()
		if len(connMgr.connections) != 0 {
			t.Errorf("connMgr.connections excpected to be empty, but actual length is %d", len(connMgr.connections))
		}
	})
}

//func TestConnManager_housekeeper(t *testing.T) {
//	connMgr := NewConnManager(
//		500*time.Millisecond,
//		30*time.Second,
//		100*time.Millisecond,
//	)
//
//	uri, _ := parseURI("tcp://127.0.0.1")
//	_ = connMgr.create(uri)
//
//	time.Sleep(1 * time.Second)
//
//	t.Run("Unused connections should have been deleted by housekeeper", func(t *testing.T) {
//		if len(connMgr.connections) != 0 {
//			t.Errorf("connMgr.connections excpected to be empty, but actual length is %d", len(connMgr.connections))
//		}
//	})
//}

func TestConnManager_create(t *testing.T) {
	uri, _ := parseURI("tcp://127.0.0.1")

	connMgr := NewConnManager(300*time.Second, 30*time.Second, hkInterval*time.Second)
	defer connMgr.Destroy()

	type args struct {
		uri URI
	}

	tests := []struct {
		name      string
		c         *ConnManager
		args      args
		want      *MCConn
		wantPanic bool
	}{
		{
			name:      "Should return *MCConn",
			c:         connMgr,
			args:      args{uri: *uri},
			want:      &MCConn{},
			wantPanic: false,
		},
		{
			name:      "Must panic if connection already exists",
			c:         connMgr,
			args:      args{uri: *uri},
			want:      nil,
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

			if got := tt.c.create(tt.args.uri); reflect.TypeOf(got) != reflect.TypeOf(tt.want) {
				t.Errorf("ConnManager.create() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestConnManager_get(t *testing.T) {
	uri, _ := parseURI("tcp://127.0.0.1")

	connMgr := NewConnManager(300*time.Second, 30*time.Second, hkInterval*time.Second)
	defer connMgr.Destroy()

	t.Run("Should return nil if connection does not exist", func(t *testing.T) {
		if got := connMgr.get(*uri); got != nil {
			t.Errorf("ConnManager.get() = %v, want <nil>", got)
		}
	})

	conn := connMgr.create(*uri)
	lastTimeAccess := conn.lastTimeAccess

	t.Run("Should return connection if it exists", func(t *testing.T) {
		got := connMgr.get(*uri)
		if !reflect.DeepEqual(got, conn) {
			t.Errorf("ConnManager.get() = %v, want %v", got, conn)
		}
		if lastTimeAccess == got.lastTimeAccess {
			t.Error("conn.lastTimeAccess should be updated, but it's not")
		}
	})
}

func TestConnManager_GetConnection(t *testing.T) {
	var conn *MCConn

	uri, _ := parseURI("tcp://127.0.0.1")

	connMgr := NewConnManager(300*time.Second, 30*time.Second, hkInterval*time.Second)
	defer connMgr.Destroy()

	t.Run("Should create connection if it does not exist", func(t *testing.T) {
		got := connMgr.GetConnection(*uri)
		if reflect.TypeOf(got) != reflect.TypeOf(conn) {
			t.Errorf("ConnManager.GetConnection() = %s, want *MCConn", reflect.TypeOf(got))
		}
		conn = got
	})

	t.Run("Should return previously created connection", func(t *testing.T) {
		got := connMgr.GetConnection(*uri)
		if !reflect.DeepEqual(got, conn) {
			t.Errorf("ConnManager.GetConnection() = %v, want %v", got, conn)
		}
	})
}
