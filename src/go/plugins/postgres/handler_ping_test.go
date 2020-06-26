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

	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
)

// TestMain does the before and after setup
func TestMain(m *testing.M) {
	var code int

	_ = log.Open(log.Console, log.Debug, "", 0)

	log.Infof("[TestMain] Start connecting to PostgreSQL...")
	if err := сreateConnection(); err != nil {
		log.Infof("failed to create connection to PostgreSQL for tests")
		os.Exit(code)
	}
	// initialize plugin
	impl.Init(pluginName)
	impl.Configure(&plugin.GlobalOptions{Timeout: 30}, nil)

	code = m.Run()
	if code != 0 {
		log.Critf("failed to run PostgreSQL tests")
		os.Exit(code)
	}
	log.Infof("[TestMain] Cleaning up...")
	os.Exit(code)
}

func TestPlugin_pingHandler(t *testing.T) {
	var pingOK int64 = 1
	// create pool or aquare conn from old pool for test
	sharedPool, err := getConnPool(t)
	if err != nil {
		t.Fatal(err)
	}

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
			got, err := tt.p.pingHandler(tt.args.conn, keyPostgresPing, tt.args.params)
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
