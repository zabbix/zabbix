/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

package mongodb

import (
	"errors"
	"reflect"
	"testing"

	"gopkg.in/mgo.v2/bson"
)

func Test_oplogStatsHandler(t *testing.T) {
	var (
		opFirst = map[string]int64{"ts": 6908630134576644097}
		opLast  = map[string]int64{"ts": 6925804549152178177}
	)

	mockSession := NewMockConn()
	localDb := mockSession.DB("local")

	dataFunc := func(_ string, _ interface{}, sortFields ...string) ([]byte, error) {
		if len(sortFields) == 0 {
			panic("sortFields must be set")
		}

		switch sortFields[0] {
		case sortAsc:
			return bson.Marshal(opFirst)

		case sortDesc:
			return bson.Marshal(opLast)

		default:
			panic("unknown sort type")
		}
	}

	type args struct {
		s           Session
		collections []string
	}

	// WARN: tests order is significant
	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr error
	}{
		{
			name: "Must return 0 if neither oplog.rs nor oplog.$main collection found",
			args: args{
				s:           mockSession,
				collections: []string{},
			},
			want:    "{\"timediff\":0}",
			wantErr: nil,
		},
		{
			name: "Must calculate timediff from oplog.$main collection",
			args: args{
				s:           mockSession,
				collections: []string{oplogMasterSlave},
			},
			want:    "{\"timediff\":3998730}",
			wantErr: nil,
		},
		{
			name: "Must calculate timediff from oplog.rs collection",
			args: args{
				s:           mockSession,
				collections: []string{oplogReplicaSet},
			},
			want:    "{\"timediff\":3998730}",
			wantErr: nil,
		},
	}

	for _, tt := range tests {
		for _, col := range tt.args.collections {
			localDb.C(col).Find(oplogQuery).(*MockMongoQuery).DataFunc = dataFunc
		}

		t.Run(tt.name, func(t *testing.T) {
			got, err := oplogStatsHandler(tt.args.s, nil)
			if !errors.Is(err, tt.wantErr) {
				t.Errorf("oplogStatsHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("oplogStatsHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}
