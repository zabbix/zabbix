/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package redis

import (
	"testing"
)

func Test_zabbixError_Error(t *testing.T) {
	tests := []struct {
		name string
		e    zabbixError
		want string
	}{
		{
			"ZabbixError stringify",
			zabbixError("foobar"),
			"foobar",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := tt.e.Error(); got != tt.want {
				t.Errorf("zabbixError.Error() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_formatZabbixError(t *testing.T) {
	type args struct {
		errText string
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{
			"Should fix if wrong formatted error text is passed",
			args{"foobar"},
			"Foobar.",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := formatZabbixError(tt.args.errText); got != tt.want {
				t.Errorf("formatZabbixError() = %v, want %v", got, tt.want)
			}
		})
	}
}
