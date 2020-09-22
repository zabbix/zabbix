package ceph

import (
	"encoding/json"
	"reflect"
	"testing"
)

func Test_statusHandler(t *testing.T) {
	out := outStatus{OverallStatus: 0,
		NumMon:   3,
		NumOsd:   3,
		NumOsdIn: 3,
		NumOsdUp: 3,
		NumPg:    33,
		PgStates: map[string]uint64{
			"active": 33, "backfill_toofull": 0, "backfill_wait": 0, "backfilling": 0, "clean": 33, "degraded": 0,
			"inconsistent": 0, "peering": 0, "recovering": 0, "recovery_wait": 0, "remapped": 0, "scrubbing": 0,
			"undersized": 0, "unknown": 0},
		MinMonReleaseName: "octopus"}

	success, err := json.Marshal(out)
	if err != nil {
		t.Fatal(err)
	}

	type args struct {
		data map[command][]byte
	}
	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr bool
	}{
		{
			"Must parse an output of " + cmdStatus + "command",
			args{map[command][]byte{cmdStatus: fixtures[cmdStatus]}},
			string(success),
			false,
		},
		{
			"Must fail on malformed input",
			args{map[command][]byte{cmdStatus: fixtures[cmdBroken]}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := statusHandler(tt.args.data)
			if (err != nil) != tt.wantErr {
				t.Errorf("statusHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("statusHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}

func Benchmark_statusHandler(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = statusHandler(map[command][]byte{cmdStatus: fixtures[cmdStatus]})
	}
}
