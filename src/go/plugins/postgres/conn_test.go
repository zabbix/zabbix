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

/*
func Test_connManager_create(t *testing.T) {
	type fields struct {
		Mutex       sync.Mutex
		connMutex   sync.Mutex
		connections map[string]*postgresConn
		keepAlive   time.Duration
		timeout     time.Duration
	}
	type args struct {
		cid string
	}
	sharedPool, err := getConnPool(t)
	if err != nil {
		t.Fatal(err)
	}
	tests := []struct {
		name   string
		fields fields
		args   args
		want   *postgresConn
	}{
		// TODO: Add test cases.
		{
			"Should return valid connID",
			fields{connections: make(map[string]*postgresConn), keepAlive: 30, timeout: 300},
			args{cid: "postgresql://postgres:postgres@localhost:5433/postgres"},
			sharedPool,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			c := &connManager{
				connections: tt.fields.connections,
				keepAlive:   tt.fields.keepAlive,
				timeout:     tt.fields.timeout,
			}
			got, err := c.create(tt.args.cid)
			if err != nil {
				t.Fatal(err)
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("connManager.get() = %v, want %v", got, tt.want)
			}
		})
	}
}
*/
