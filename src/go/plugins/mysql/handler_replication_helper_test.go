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

package mysql

import (
	"testing"

	"github.com/google/go-cmp/cmp"
)

var (
	oldStyleData = map[string]string{ //nolint:gochecknoglobals //readability
		"Master_Host":           "mysql-8.3-master",
		"Master_User":           "zbx_monitor",
		"Master_Port":           "33061",
		"Master_Log_File":       "",
		"Read_Master_Log_Pos":   "4",
		"Get_master_public_key": "0",
		"SMTH_Master":           "0",

		"Connect_Retry":        "60",
		"Relay_Log_File":       "mysql-relay.000001",
		"Relay_Log_Pos":        "4",
		"Replicate_Rewrite_DB": "",

		"Slave_IO_State":          "",
		"Slave_SQL_Running_State": "",
	}

	newStyleData = map[string]string{ //nolint:gochecknoglobals //readability
		"Source_Host":           "mysql-8.3-master",
		"Source_User":           "zbx_monitor",
		"Source_Port":           "33061",
		"Source_Log_File":       "",
		"Read_Source_Log_Pos":   "4",
		"Get_Source_public_key": "0",
		"SMTH_Source":           "0",

		"Connect_Retry":        "60",
		"Relay_Log_File":       "mysql-relay.000001",
		"Relay_Log_Pos":        "4",
		"Replicate_Rewrite_DB": "",

		"Replica_IO_State":          "",
		"Replica_SQL_Running_State": "",
	}

	duplicatedData = map[string]string{ //nolint:gochecknoglobals //readability
		"Master_Host": "mysql-8.3-master", "Source_Host": "mysql-8.3-master",
		"Master_User": "zbx_monitor", "Source_User": "zbx_monitor",
		"Master_Port": "33061", "Source_Port": "33061",
		"Master_Log_File": "", "Source_Log_File": "",
		"Read_Master_Log_Pos": "4", "Read_Source_Log_Pos": "4",
		"Get_master_public_key": "0", "Get_Source_public_key": "0",
		"SMTH_Master": "0", "SMTH_Source": "0",

		"Connect_Retry":        "60",
		"Relay_Log_File":       "mysql-relay.000001",
		"Relay_Log_Pos":        "4",
		"Replicate_Rewrite_DB": "",

		"Slave_IO_State": "", "Replica_IO_State": "",
		"Slave_SQL_Running_State": "", "Replica_SQL_Running_State": "",
	}
)

func Test_substituteKey(t *testing.T) {
	t.Parallel()

	type args = struct {
		key   string
		rules map[string]string
	}

	tests := []struct {
		name string
		args args
		want string
	}{
		{
			"+substStart",
			args{
				"Master_Host",
				substituteRulesOld2New,
			},
			"Source_Host",
		},
		{
			"+substEnd",
			args{
				"SMTH_Master",
				substituteRulesOld2New,
			},
			"SMTH_Source",
		},
		{
			"+substMId",
			args{
				"Read_Master_Log_Pos",
				substituteRulesOld2New,
			},
			"Read_Source_Log_Pos",
		},
		{
			"+substWhole",
			args{
				"Master",
				substituteRulesOld2New,
			},
			"Source",
		},
		{
			"+substWholeKey",
			args{
				"Get_master_public_key",
				substituteRulesOld2New,
			},
			"Get_Source_public_key",
		},
		{
			"+empty",
			args{
				"",
				substituteRulesOld2New,
			},
			"", //nothing to substitute
		},
		{
			"+noRules",
			args{
				"Smth_Master",
				nil,
			},
			"Smth_Master", //no rules, stays intact
		},
		{
			"+substMultiple",
			args{
				"Smth_Master_aa_Master",
				substituteRulesOld2New,
			},
			"Smth_Source_aa_Source", //no rules, stays intact
		},
		{
			"+spearatorWrong",
			args{
				"Smth-Master",
				substituteRulesOld2New,
			},
			"Smth-Master", //no rules, stays intact
		},
		{
			"+noSeparator",
			args{
				"aaMaster",
				substituteRulesOld2New,
			},
			"aaMaster", //no rules, stays intact
		},
		{
			"+doubleRulesNoCycle",
			args{
				"aa_Master",
				map[string]string{
					"Master": "Source",
					"Source": "Master",
				},
			},
			"aa_Source", //no rules, stays intact
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := substituteKey(tt.args.key, tt.args.rules)

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Errorf("substituteKey(): %s", diff)
			}
		})
	}
}

func Test_mergeKeysResult(t *testing.T) {
	t.Parallel()

	type args = struct {
		initialData map[string]string
		rules       map[string]string
	}

	tests := []struct {
		name string
		args args
		want map[string]string
	}{
		{
			"+old2new",
			args{
				oldStyleData,
				substituteRulesOld2New,
			},
			duplicatedData,
		},
		{
			"+new2old",
			args{
				newStyleData,
				substituteRulesNew2Old,
			},
			duplicatedData,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := duplicate(tt.args.initialData, tt.args.rules)

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Errorf("renameToNewStyle(): %s", diff)
			}
		})
	}
}
