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
	"testing"
)

func TestPlugin_dbStatHandler(t *testing.T) {
	sharedPool, err := getConnPool(t)
	if err != nil {
		t.Fatal(err)
	}

	type args struct {
		conn   *postgresConn
		key    string
		params []string
	}
	tests := []struct {
		name    string
		p       *Plugin
		args    args
		wantErr bool
	}{
		{
			fmt.Sprintf("dbStatHandler should return json with data for pgsql.dbstat.sum key if OK"),
			&impl,
			args{conn: sharedPool, key: "pgsql.dbstat.sum"},
			false,
		},
		{
			fmt.Sprintf("dbStatHandler should return json with data for pgsql.dbstat key if OK"),
			&impl,
			args{conn: sharedPool, key: "pgsql.dbstat"},
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {

			_, err := tt.p.dbStatHandler(tt.args.conn, tt.args.key, []string{})
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.statHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			/* if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Plugin.walHandler() = %v, want %v", got, tt.want)
			} */
		})
	}
}
