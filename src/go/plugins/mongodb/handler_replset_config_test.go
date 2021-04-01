package mongodb

import (
	"encoding/json"
	"errors"
	"io/ioutil"
	"log"
	"reflect"
	"strings"
	"testing"

	"gopkg.in/mgo.v2/bson"
)

func Test_replSetConfigHandler(t *testing.T) {
	var testData map[string]interface{}

	jsonData, err := ioutil.ReadFile("testdata/replSetGetConfig.json")
	if err != nil {
		log.Fatal(err)
	}

	err = json.Unmarshal(jsonData, &testData)
	if err != nil {
		log.Fatal(err)
	}

	mockSession := NewMockConn()
	db := mockSession.DB("admin")
	db.(*MockMongoDatabase).RunFunc = func(dbName, cmd string) ([]byte, error) {
		if cmd == "replSetGetConfig" {
			return bson.Marshal(testData)
		}

		return nil, errors.New("no such cmd: " + cmd)
	}

	type args struct {
		s Session
	}

	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr error
	}{
		{
			name: "Must parse an output of \" + replSetGetConfig + \"command",
			args: args{
				s: mockSession,
			},
			want:    strings.TrimSpace(string(jsonData)),
			wantErr: nil,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := replSetConfigHandler(tt.args.s, nil)
			if !errors.Is(err, tt.wantErr) {
				t.Errorf("replSetConfigHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("replSetConfigHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}
