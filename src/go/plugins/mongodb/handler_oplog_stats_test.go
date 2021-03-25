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
