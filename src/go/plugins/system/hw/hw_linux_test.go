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

package hw

import (
	"testing"
)

func Test_getChassisType(t *testing.T) {
	type args struct {
		num byte
	}
	tests := []struct {
		name    string
		args    args
		wantOut string
	}{
		{"other", args{1}, "Other"},
		{"unknown", args{2}, "Unknown"},
		{"desktop", args{3}, "Desktop"},
		{"low_profile_desktop", args{4}, "Low Profile Desktop"},
		{"other", args{5}, "Pizza Box"},
		{"mini_tower", args{6}, "Mini Tower"},
		{"tower", args{7}, "Tower"},
		{"portable", args{8}, "Portable"},
		{"lapTop", args{9}, "LapTop"},
		{"notebook", args{10}, "Notebook"},
		{"hand_held", args{11}, "Hand Held"},
		{"docking_station", args{12}, "Docking Station"},
		{"all_in_one", args{13}, "All in One"},
		{"sub_notebook", args{14}, "Sub Notebook"},
		{"space_saving", args{15}, "Space-saving"},
		{"lunch_box", args{16}, "Lunch Box"},
		{"main_server_chassis", args{17}, "Main Server Chassis"},
		{"expansio_chassis", args{18}, "Expansion Chassis"},
		{"sub_chassis", args{19}, "SubChassis"},
		{"bus_expansion_chassis", args{20}, "Bus Expansion Chassis"},
		{"peripheral_chassis", args{21}, "Peripheral Chassis"},
		{"raid_chassis", args{22}, "RAID Chassis"},
		{"rack_mount_chassis", args{23}, "Rack Mount Chassis"},
		{"sealed-case_pc", args{24}, "Sealed-case PC"},
		{"multi-system_chassis", args{25}, "Multi-system chassis"},
		{"compact_pci", args{26}, "Compact PCI"},
		{"advanced_tca", args{27}, "Advanced TCA"},
		{"blade", args{28}, "Blade"},
		{"blade_enclosure", args{29}, "Blade Enclosure"},
		{"tablet", args{30}, "Tablet"},
		{"convertible", args{31}, "Convertible"},
		{"detachable", args{32}, "Detachable"},
		{"iot_gateway", args{33}, "IoT Gateway"},
		{"embedded_pc", args{34}, "Embedded PC"},
		{"mini_pc", args{35}, "Mini PC"},
		{"stick_pc", args{36}, "Stick PC"},
		{"zero_input", args{0}, ""},
		{"over_max", args{37}, ""},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getChassisType(tt.args.num); gotOut != tt.wantOut {
				t.Errorf("getChassisType() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_getFlags(t *testing.T) {
	type args struct {
		params []string
	}
	tests := []struct {
		name    string
		args    args
		want    int
		wantErr bool
	}{
		{"full", args{[]string{"full"}}, 120, false},
		{"vendor", args{[]string{"vendor"}}, 8, false},
		{"model", args{[]string{"model"}}, 16, false},
		{"serial", args{[]string{"serial"}}, 32, false},
		{"type", args{[]string{"type"}}, 64, false},
		{"empty_string", args{[]string{""}}, 120, false},
		{"no_param", args{[]string{}}, 120, false},
		{"too_many_params", args{[]string{"foo", "bar"}}, 0, true},
		{"wrong_param", args{[]string{"foobar"}}, 0, true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := getFlags(tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("getFlags() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if got != tt.want {
				t.Errorf("getFlags() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getDeviceCmd(t *testing.T) {
	type args struct {
		params []string
	}
	tests := []struct {
		name    string
		args    args
		want    string
		wantErr bool
	}{
		{"no_params", args{}, pciCMD, false},
		{"pci_param", args{[]string{"pci"}}, pciCMD, false},
		{"usb_param", args{[]string{"usb"}}, usbCMD, false},
		{"invalid_param", args{[]string{"foobar"}}, "", true},
		{"too_many_params", args{[]string{"foo", "bar"}}, "", true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := getDeviceCmd(tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("getDeviceCmd() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if got != tt.want {
				t.Errorf("getDeviceCmd() = %v, want %v", got, tt.want)
			}
		})
	}
}
