package mongodb

import (
	"errors"
	"reflect"
	"testing"

	"zabbix.com/pkg/zbxerr"
)

func Test_databasesDiscoveryHandler(t *testing.T) {
	type args struct {
		s   Session
		dbs []string
	}

	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr error
	}{
		{
			name: "Must return a list of databases",
			args: args{
				s:   NewMockConn(),
				dbs: []string{"testdb", "local", "config"},
			},
			want:    "[{\"{#DBNAME}\":\"config\"},{\"{#DBNAME}\":\"local\"},{\"{#DBNAME}\":\"testdb\"}]",
			wantErr: nil,
		},
		{
			name: "Must catch DB.DatabaseNames() error",
			args: args{
				s:   NewMockConn(),
				dbs: []string{mustFail},
			},
			want:    nil,
			wantErr: zbxerr.ErrorCannotFetchData,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			for _, db := range tt.args.dbs {
				tt.args.s.DB(db)
			}

			got, err := databasesDiscoveryHandler(tt.args.s, nil)
			if !errors.Is(err, tt.wantErr) {
				t.Errorf("databasesDiscoveryHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("databasesDiscoveryHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}
