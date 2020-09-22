package ceph

import (
	"encoding/json"
	"reflect"
	"testing"
)

func Test_dfHandler(t *testing.T) {
	out := outDf{Pools: map[string]poolStat{
		"device_health_metrics": {PercentUsed: 0, Objects: 0, BytesUsed: 0, Rd: 0, RdBytes: 0, Wr: 0, WrBytes: 0, StoredRaw: 0},
		"new_pool":              {PercentUsed: 0, Objects: 0, BytesUsed: 0, Rd: 0, RdBytes: 0, Wr: 0, WrBytes: 0, StoredRaw: 0},
		"test_zabbix":           {PercentUsed: 0, Objects: 4, BytesUsed: 786432, Rd: 0, RdBytes: 0, Wr: 0, WrBytes: 0, StoredRaw: 0},
		"zabbix":                {PercentUsed: 0, Objects: 0, BytesUsed: 0, Rd: 0, RdBytes: 0, Wr: 0, WrBytes: 0, StoredRaw: 0}},
		Rd:              0,
		RdBytes:         0,
		Wr:              0,
		WrBytes:         0,
		NumPools:        4,
		TotalBytes:      12872318976,
		TotalAvailBytes: 6903169024,
		TotalUsedBytes:  2747924480,
		TotalObjects:    4,
	}

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
			"Must parse an output of " + cmdDf + "command",
			args{map[command][]byte{cmdDf: fixtures[cmdDf]}},
			string(success),
			false,
		},
		{
			"Must fail on malformed input",
			args{map[command][]byte{cmdDf: fixtures[cmdBroken]}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := dfHandler(tt.args.data)
			if (err != nil) != tt.wantErr {
				t.Errorf("dfHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("dfHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}

func Benchmark_dfHandler(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = dfHandler(map[command][]byte{cmdDf: fixtures[cmdDf]})
	}
}
