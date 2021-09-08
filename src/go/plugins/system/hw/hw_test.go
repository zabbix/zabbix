// +build !windows

/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

import "testing"

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
