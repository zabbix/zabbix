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
	"testing"
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

func Test_getType(t *testing.T) {
	type args struct {
		devType string
		rate    int
	}
	tests := []struct {
		name    string
		args    args
		wantOut string
	}{
		{"ssd", args{"SAT", 0}, "ssd"},
		{"hdd", args{"SAT", 12}, "hdd"},
		{"nvme", args{"nvme", 0}, "nvme"},
		{"unknown", args{"SAT", -1}, "unknown"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getType(tt.args.devType, tt.args.rate); gotOut != tt.wantOut {
				t.Errorf("getType() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}
