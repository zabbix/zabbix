/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package handlers

import (
	"errors"
	"testing"

	"github.com/google/go-cmp/cmp"
	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/agent2/plugins/redis/conn"
)

const (
	infoCommonSectionOutput = `# CommonSection
foo:123
bar:0.00`

	infoDefaultSectionOutput = `# DefaultSection
test:111`

	infoExtendedSectionOutput = `
# Commandstats
cmdstat_info:calls=11150,usec=823882,usec_per_call=73.89
cmdstat_config:calls=10,usec=383,usec_per_call=38.30`

	infoMasterReplicationOutput = `# Replication
role:master
connected_slaves:1
slave0:ip=172.18.0.2,port=6379,state=online,offset=953099,lag=1
master_replid:5a9346f8855b4766efca35d4a83cfd151db3fa4a`

	infoSlaveReplicationOutput = `# Replication
role:slave
master_host:redis-master
master_port:6379
slave_repl_offset:953057
connected_slaves:0`

	infoMalformedSectionOutput = `# 
test:111`
)

func TestParseRedisInfo(t *testing.T) {
	t.Parallel()

	type args struct {
		info string
	}

	tests := []struct {
		name    string
		args    args
		wantRes redisInfo
		wantErr bool
	}{
		{
			"Should fail on malformed input",
			args{"foobar"},
			nil,
			true,
		},
		{
			"Should fail on empty section name",
			args{infoMalformedSectionOutput},
			nil,
			true,
		},
		{
			"Should fail on empty input",
			args{""},
			nil,
			true,
		},
		{
			`Parse of output of "info CommonSection" command`,
			args{infoCommonSectionOutput},
			redisInfo{
				"CommonSection": infoKeySpace{
					"foo": "123", "bar": "0.00",
				},
			},
			false,
		},
		{
			`Parse of output of "info Commandstats" command`,
			args{infoExtendedSectionOutput},
			redisInfo{
				"Commandstats": infoKeySpace{
					"cmdstat_info": infoExtKeySpace{
						"calls":         "11150",
						"usec":          "823882",
						"usec_per_call": "73.89",
					},
					"cmdstat_config": infoExtKeySpace{
						"calls":         "10",
						"usec":          "383",
						"usec_per_call": "38.30",
					},
				},
			},
			false,
		},
		{
			`Parse of output of "info Replication" command for Master role`,
			args{infoMasterReplicationOutput},
			redisInfo{
				"Replication": infoKeySpace{
					"role":             "master",
					"connected_slaves": "1",
					"slave0": infoExtKeySpace{
						"ip":     "172.18.0.2",
						"port":   "6379",
						"state":  "online",
						"offset": "953099",
						"lag":    "1",
					},
					"master_replid": "5a9346f8855b4766efca35d4a83cfd151db3fa4a",
				},
			},
			false,
		},
		{
			`Parse of output of "info Replication" command for Slave role`,
			args{infoSlaveReplicationOutput},
			redisInfo{
				"Replication": infoKeySpace{
					"role":              "slave",
					"master_host":       "redis-master",
					"master_port":       "6379",
					"slave_repl_offset": "953057",
					"connected_slaves":  "0",
				},
			},
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotRes, err := parseRedisInfo(tt.args.info)
			if (err != nil) != tt.wantErr {
				t.Errorf("parseRedisInfo() error = %#v, wantErr %#v", err, tt.wantErr)

				return
			}

			diff := cmp.Diff(tt.wantRes, gotRes)
			if diff != "" {
				t.Fatalf("parseRedisInfo() mismatch (-want +got):\n%s\ngot=%#v\nwant=%#v", diff, gotRes, tt.wantRes)
			}
		})
	}
}

func BenchmarkParseRedisInfo_Common(b *testing.B) {
	for range b.N {
		_, _ = parseRedisInfo(infoExtendedSectionOutput)
	}
}

func BenchmarkParseRedisInfo_Extended(b *testing.B) {
	for range b.N {
		_, _ = parseRedisInfo(infoCommonSectionOutput)
	}
}

func TestInfoHandler(t *testing.T) {
	t.Parallel()

	stubConn := radix.Stub("", "", func(args []string) any {
		switch args[1] {
		case "commonsection":
			return infoCommonSectionOutput

		case "default":
			return infoDefaultSectionOutput

		case "unknownsection":
			return ""

		default:
			return errors.New("cannot fetch data")
		}
	})

	t.Cleanup(func() {
		err := stubConn.Close()
		if err != nil {
			t.Errorf("failed to close stubConn: %v", err)
		}
	})

	connection := conn.NewRedisConn(stubConn)

	type args struct {
		conn   conn.RedisClient
		params map[string]string
	}

	tests := []struct {
		name    string
		args    args
		want    any
		wantErr bool
	}{
		{
			"Default section should be used if it is not explicitly specified",
			args{conn: connection, params: map[string]string{"Section": "default"}},
			`{"DefaultSection":{"test":"111"}}`,
			false,
		},
		{
			"Should fetch specified section and return marshalled result",
			args{conn: connection, params: map[string]string{"Section": "COMMONSECTION"}},
			`{"CommonSection":{"bar":"0.00","foo":"123"}}`,
			false,
		},
		{
			"Should fail if error occurred",
			args{conn: connection, params: map[string]string{"Section": "WantErr"}},
			nil,
			true,
		},
		{
			"Should fail on malformed data",
			args{conn: connection, params: map[string]string{"Section": "UnknownSection"}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := InfoHandler(tt.args.conn, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.InfoHandler() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("Plugin.InfoHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
