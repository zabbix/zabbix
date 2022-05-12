package mongodb

import (
	"encoding/json"
	"errors"
	"io/ioutil"
	"log"
	"reflect"
	"strings"
	"testing"

	"git.zabbix.com/ap/plugin-support/zbxerr"
	"gopkg.in/mgo.v2/bson"
)

func Test_databaseStatsHandler(t *testing.T) {
	var testData map[string]interface{}

	jsonData, err := ioutil.ReadFile("testdata/dbStats.json")
	if err != nil {
		log.Fatal(err)
	}

	err = json.Unmarshal(jsonData, &testData)
	if err != nil {
		log.Fatal(err)
	}

	mockSession := NewMockConn()
	db := mockSession.DB("testdb")
	db.(*MockMongoDatabase).RunFunc = func(dbName, cmd string) ([]byte, error) {
		if cmd == "dbStats" {
			return bson.Marshal(testData)
		}

		return nil, errors.New("no such cmd: " + cmd)
	}

	type args struct {
		s      Session
		params map[string]string
	}

	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr error
	}{
		{
			name: "Must parse an output of \" + dbStats + \"command",
			args: args{
				s:      mockSession,
				params: map[string]string{"Database": "testdb"},
			},
			want:    strings.TrimSpace(string(jsonData)),
			wantErr: nil,
		},
		{
			name: "Must not fail on unknown db",
			args: args{
				s:      mockSession,
				params: map[string]string{"Database": "not_exists"},
			},
			want:    "{\"ok\":1}",
			wantErr: nil,
		},
		{
			name: "Must catch DB.Run() error",
			args: args{
				s:      mockSession,
				params: map[string]string{"Database": mustFail},
			},
			want:    nil,
			wantErr: zbxerr.ErrorCannotFetchData,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := databaseStatsHandler(tt.args.s, tt.args.params)
			if !errors.Is(err, tt.wantErr) {
				t.Errorf("databaseStatsHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("databaseStatsHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}
