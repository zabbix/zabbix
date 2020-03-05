// +build postgres_tests

/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package postgres

import (
	"fmt"
	"os"
	"reflect"
	"testing"

	log "github.com/sirupsen/logrus"
	"zabbix.com/pkg/plugin"
)

// TestMain does the before and after setup
func TestMain(m *testing.M) {
	var confPath string
	var stopDB func()
	var code int
	log.Infoln("[TestMain] About to start PostgreSQL...")
	versionsPG := []uint32{10, 11, 12}
	for _, versionPG := range versionsPG {
		confPath, stopDB = startPostgreSQL(versionPG)
		log.Infoln("[TestMain] PostgreSQL started!")
		log.Infof("[TestMain] conf path  = %v", confPath)

		code = m.Run()
		if code != 0 {
			log.Panicf("failed on PostgreSQL version %v", versionPG)
			os.Exit(code)
		}
		log.Infoln("[TestMain] Cleaning up...")
		stopDB()

	}
	os.Exit(code)
}

func TestPlugin_pingHandler(t *testing.T) {
	var pingOK int64 = 1
	// create pool or aquare conn from old pool for test
	sharedPool, err := getConnPool(t)
	if err != nil {
		t.Fatal(err)
	}

	impl.Configure(&plugin.GlobalOptions{}, nil)

	type args struct {
		conn   *postgresConn
		params []string
	}
	tests := []struct {
		name    string
		p       *Plugin
		args    args
		want    interface{}
		wantErr bool
	}{
		{
			fmt.Sprintf("pingHandler should return %d if connection is ok", postgresPingOk),
			&impl,
			args{conn: sharedPool},
			pingOK,
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := tt.p.pingHandler(tt.args.conn, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.pingHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Plugin.pingHandler() = %v, want %v", got, tt.want)
			}
		})
	}
}
