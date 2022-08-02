//go:build oracle_tests
// +build oracle_tests

/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package oracle

import (
	"reflect"
	"testing"
	"time"

	"zabbix.com/pkg/uri"

	"github.com/omeid/go-yarn"
)

func TestConnManager_closeUnused(t *testing.T) {
	connMgr := NewConnManager(1*time.Microsecond, 30*time.Second, 30*time.Second, hkInterval*time.Second,
		yarn.NewFromMap(map[string]string{}))
	defer connMgr.Destroy()

	u, _ := uri.NewWithCreds(Config.ora_uri+"/"+Config.ora_srv, Config.ora_user, Config.ora_pwd, uriDefaults)

	_, err := connMgr.create(*u)
	if err != nil {
		t.Errorf("ConnManager.create() should create a connection, but got error: %s", err.Error())
		return
	}

	t.Run("Unused connections should have been deleted", func(t *testing.T) {
		connMgr.closeUnused()
		if len(connMgr.connections) != 0 {
			t.Errorf("connMgr.connections expected to be empty, but actual length is %d", len(connMgr.connections))
		}
	})
}

func TestConnManager_closeAll(t *testing.T) {
	connMgr := NewConnManager(300*time.Second, 30*time.Second, 30*time.Second, hkInterval*time.Second,
		yarn.NewFromMap(map[string]string{}))
	defer connMgr.Destroy()

	u, _ := uri.NewWithCreds(Config.ora_uri+"/"+Config.ora_srv, Config.ora_user, Config.ora_pwd, uriDefaults)

	_, err := connMgr.create(*u)
	if err != nil {
		t.Errorf("ConnManager.create() should create a connection, but got error: %s", err.Error())
		return
	}

	t.Run("All connections should have been deleted", func(t *testing.T) {
		connMgr.closeAll()
		if len(connMgr.connections) != 0 {
			t.Errorf("connMgr.connections expected to be empty, but actual length is %d", len(connMgr.connections))
		}
	})
}

func TestConnManager_create(t *testing.T) {
	u, _ := uri.NewWithCreds(Config.ora_uri+"/"+Config.ora_srv, Config.ora_user, Config.ora_pwd, uriDefaults)

	connMgr := NewConnManager(300*time.Second, 30*time.Second, 30*time.Second, hkInterval*time.Second,
		yarn.NewFromMap(map[string]string{}))
	defer connMgr.Destroy()

	type args struct {
		uri uri.URI
	}

	tests := []struct {
		name      string
		c         *ConnManager
		args      args
		want      *OraConn
		wantPanic bool
	}{
		{
			name:      "Should return *OraConn",
			c:         connMgr,
			args:      args{uri: *u},
			want:      &OraConn{},
			wantPanic: false,
		},
		{
			name:      "Must panic if connection already exists",
			c:         connMgr,
			args:      args{uri: *u},
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

			if got, err := tt.c.create(tt.args.uri); reflect.TypeOf(got) != reflect.TypeOf(tt.want) {
				if err != nil {
					t.Errorf("ConnManager.create() should create a connection, but got error: %s", err.Error())
					return
				}
				t.Errorf("ConnManager.create() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestConnManager_get(t *testing.T) {
	u, _ := uri.NewWithCreds(Config.ora_uri+"/"+Config.ora_srv, Config.ora_user, Config.ora_pwd, uriDefaults)

	connMgr := NewConnManager(300*time.Second, 30*time.Second, 30*time.Second, hkInterval*time.Second,
		yarn.NewFromMap(map[string]string{}))
	defer connMgr.Destroy()

	t.Run("Should return nil if connection does not exist", func(t *testing.T) {
		if got := connMgr.get(*u); got != nil {
			t.Errorf("ConnManager.get() = %v, want <nil>", got)
		}
	})

	conn, err := connMgr.create(*u)
	if err != nil {
		t.Errorf("ConnManager.create() should create a connection, but got error: %s", err.Error())
		return
	}

	lastTimeAccess := conn.lastTimeAccess

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

func TestConnManager_GetConnection(t *testing.T) {
	var conn *OraConn

	u, _ := uri.NewWithCreds(Config.ora_uri+"/"+Config.ora_srv, Config.ora_user, Config.ora_pwd, uriDefaults)

	connMgr := NewConnManager(300*time.Second, 30*time.Second, 30*time.Second, hkInterval*time.Second,
		yarn.NewFromMap(map[string]string{}))
	defer connMgr.Destroy()

	t.Run("Should create connection if it does not exist", func(t *testing.T) {
		got, _ := connMgr.GetConnection(*u)
		if reflect.TypeOf(got) != reflect.TypeOf(conn) {
			t.Errorf("ConnManager.GetConnection() = %s, want *OraConn", reflect.TypeOf(got))
		}
		conn = got
	})

	t.Run("Should return previously created connection", func(t *testing.T) {
		got, _ := connMgr.GetConnection(*u)
		if !reflect.DeepEqual(got, conn) {
			t.Errorf("ConnManager.GetConnection() = %v, want %v", got, conn)
		}
	})
}
