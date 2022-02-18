/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package smart

import (
	"fmt"
	"reflect"
	"testing"
)

var (
	table1    = table{"test1", 1, 11}
	table2    = table{"test2", 2, 22}
	table3    = table{"test3", 3, 33}
	table4    = table{"test4", 4, 44}
	attrTable = table{"Spin_Up_Time", 5, 55}
)

func Test_setDiskFields(t *testing.T) {
	//nolint:lll
	jsonSdaStr := `{"device": {"name": "/dev/sda","info_name": "/dev/sda [SAT]","type": "sat","protocol": "ATA"},"rotation_rate": 0}`
	//nolint:lll
	sdaOutStr := map[string]interface{}{
		"device":    map[string]interface{}{"name": "/dev/sda", "info_name": "/dev/sda [SAT]", "type": "sat", "protocol": "ATA"},
		"disk_name": "sda", "disk_type": "ssd", "rotation_rate": 0,
	}

	type args struct {
		deviceJsons map[string]jsonDevice
	}

	tests := []struct {
		name    string
		args    args
		want    []interface{}
		wantErr bool
	}{
		{"+one_drive", args{map[string]jsonDevice{"/dev/sda": {jsonData: jsonSdaStr}}}, []interface{}{sdaOutStr}, false},
		{"-failed_json", args{map[string]jsonDevice{"/dev/sda": {jsonData: `{"device":}`}}}, nil, true},
		{"-failed_device_data_json", args{map[string]jsonDevice{"/dev/sda": {jsonData: `{"device": foo,"rotation_rate": 0}`}}}, nil, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := setDiskFields(tt.args.deviceJsons)
			if (err != nil) != tt.wantErr {
				t.Errorf("setDiskFields() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if fmt.Sprint(got) != fmt.Sprint(tt.want) {
				t.Errorf("setDiskFields() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getRateFromJson(t *testing.T) {
	type args struct {
		in map[string]interface{}
	}
	tests := []struct {
		name    string
		args    args
		wantOut int
	}{
		{"rate", args{map[string]interface{}{"rotation_rate": 10}}, 10},
		{"multiple_fields", args{map[string]interface{}{"foobar": "abc", "rotation_rate": 10}}, 10},
		{"no_rate", args{map[string]interface{}{"foobar": "abc"}}, 0},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getRateFromJson(tt.args.in); gotOut != tt.wantOut {
				t.Errorf("getRateFromJson() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_getTypeFromJson(t *testing.T) {
	map1 := make(map[string]interface{})
	map1["device"] = map[string]interface{}{"type": "sat"}

	map2 := make(map[string]interface{})
	map2["device"] = map[string]interface{}{"type": "sat", "foobar": "abc"}

	map3 := make(map[string]interface{})
	map3["device"] = map[string]interface{}{"foobar": "abc"}

	type args struct {
		in map[string]interface{}
	}
	tests := []struct {
		name    string
		args    args
		wantOut string
	}{
		{"type", args{map1}, "sat"},
		{"multiple_fields", args{map2}, "sat"},
		{"no_type", args{map3}, ""},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getTypeFromJson(tt.args.in); gotOut != tt.wantOut {
				t.Errorf("getTypeFromJson() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_getTablesFromJson(t *testing.T) {
	map1 := make(map[string]interface{})
	map1["table"] = []interface{}{table1, table2, attrTable}

	map2 := make(map[string]interface{})
	map2["table"] = []interface{}{table1, table2, table4}

	attrTable1 := map[string]interface{}{"ata_smart_attributes": map1}
	attrTable2 := map[string]interface{}{"ata_smart_attributes": map2}
	attrTable3 := map[string]interface{}{"ata_smart_attributes": nil}
	attrTable4 := map[string]interface{}{"ata_smart_attributes": []table{}}
	attrTable5 := map[string]interface{}{"ata_smart_attributes": map[string][]table{}}

	type args struct {
		in map[string]interface{}
	}
	tests := []struct {
		name string
		args args
		want []table
	}{
		{"attr_table", args{attrTable1}, []table{table1, table2, attrTable}},
		{"no_attr_table", args{attrTable2}, []table{table1, table2, table4}},
		{"no_table", args{attrTable3}, nil},
		{"incorrect_table_value", args{attrTable4}, nil},
		{"empty_map", args{attrTable5}, nil},
		{"no_ata_attributes", args{nil}, nil},
		{"empty_ata_attributes", args{map[string]interface{}{}}, nil},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getTablesFromJson(tt.args.in); !reflect.DeepEqual(got, tt.want) {
				t.Errorf("getTablesFromJson() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getAttributeType(t *testing.T) {
	type args struct {
		devType string
		rate    int
		tables  []table
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{"ssd_no_tables", args{"SAT", 0, nil}, "ssd"},
		{"ssd_tables_no_spin_up_table", args{"SAT", 0, []table{table1, table2, table4}}, "ssd"},
		{"hdd_no_tables", args{"SAT", 12, nil}, "hdd"},
		{"hdd_rate_spin_up_table", args{"SAT", 12, []table{table1, table2, table4, attrTable}}, "hdd"},
		{"hdd_no_rate_spin_up_table", args{"SAT", 0, []table{table1, table2, table4, attrTable}}, "hdd"},
		{"hdd_no_spin_up_table", args{"SAT", 12, []table{table1, table2, table4}}, "hdd"},
		{"unknown_no_attr_table", args{"unknown", 1000, []table{table1, table2, table4}}, "unknown"},
		{"unknown_value_table", args{"unknown", 1000, []table{table1, table2, table4, attrTable}}, "unknown"},
		{"unknown_no_rate_no_tables", args{"unknown", 0, nil}, "unknown"},
		{"unknown_no_rate_no_attr_table", args{"unknown", 0, []table{table1, table2, table4}}, "unknown"},
		{"unknown_no_rate_value_table", args{"unknown", 0, []table{table1, table2, table4, attrTable}}, "unknown"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getAttributeType(tt.args.devType, tt.args.rate, tt.args.tables); got != tt.want {
				t.Errorf("getAttributeType() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getType(t *testing.T) {
	type args struct {
		devType string
		rate    int
		tables  []table
	}
	tests := []struct {
		name    string
		args    args
		wantOut string
	}{
		{"ssd_no_tables", args{"SAT", 0, nil}, "ssd"},
		{"ssd_tables_no_spin_up_table", args{"SAT", 0, []table{table1, table2, table4}}, "ssd"},
		{"hdd_no_tables", args{"SAT", 12, nil}, "hdd"},
		{"hdd_rate_spin_up_table", args{"SAT", 12, []table{table1, table2, table4, attrTable}}, "hdd"},
		{"hdd_no_rate_spin_up_table", args{"SAT", 0, []table{table1, table2, table4, attrTable}}, "hdd"},
		{"hdd_no_spin_up_table", args{"SAT", 12, []table{table1, table2, table4}}, "hdd"},
		{"nvme_no_tables", args{"nvme", 1000, nil}, "nvme"},
		{"nvme_no_attr_table", args{"nvme", 1000, []table{table1, table2, table4}}, "nvme"},
		{"nvme_value_table", args{"nvme", 1000, []table{table1, table2, table4, attrTable}}, "nvme"},
		{"nvme_no_rate_no_tables", args{"nvme", 0, nil}, "nvme"},
		{"nvme_no_rate_no_attr_table", args{"nvme", 0, []table{table1, table2, table4}}, "nvme"},
		{"nvme_no_rate_value_table", args{"nvme", 0, []table{table1, table2, table4, attrTable}}, "nvme"},
		{"unknown_no_tables", args{"unknown", 1000, nil}, "unknown"},
		{"unknown_no_attr_table", args{"unknown", 1000, []table{table1, table2, table4}}, "unknown"},
		{"unknown_value_table", args{"unknown", 1000, []table{table1, table2, table4, attrTable}}, "unknown"},
		{"unknown_no_rate_no_tables", args{"unknown", 0, nil}, "unknown"},
		{"unknown_no_rate_no_attr_table", args{"unknown", 0, []table{table1, table2, table4}}, "unknown"},
		{"unknown_no_rate_value_table", args{"unknown", 0, []table{table1, table2, table4, attrTable}}, "unknown"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getType(tt.args.devType, tt.args.rate, tt.args.tables); gotOut != tt.wantOut {
				t.Errorf("getType() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_getTypeByRateAndAttr(t *testing.T) {
	type args struct {
		rate   int
		tables []table
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{"zero_rate_zero_spin_up", args{0, []table{table1, table2}}, "ssd"},
		{"zero_rate_no_tables", args{0, nil}, "ssd"},
		{"negative_rate_no_tables", args{-1000, nil}, "ssd"},
		{"positive_rate_spin_up_table", args{12, []table{table1, table2, table3, attrTable}}, "hdd"},
		{"positive_rate_no_tables", args{12, nil}, "hdd"},
		{"zero_rate_spin_up_table", args{0, []table{table1, table2, table3, attrTable}}, "hdd"},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getTypeByRateAndAttr(tt.args.rate, tt.args.tables); got != tt.want {
				t.Errorf("getTypeByRate() = %v, want %v", got, tt.want)
			}
		})
	}
}
