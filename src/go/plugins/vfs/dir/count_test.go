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

package dir

import (
	"io/fs"
	"reflect"
	"testing"
	"time"
)

func Test_parseByte(t *testing.T) {
	type args struct {
		in string
	}
	tests := []struct {
		name    string
		args    args
		want    int64
		wantErr bool
	}{
		{"+byte", args{"1024"}, 1024, false},
		{"+kB", args{"1K"}, 1024, false},
		{"+mB", args{"1M"}, 1024 * 1024, false},
		{"+gB", args{"1G"}, 1024 * 1024 * 1024, false},
		{"+tB", args{"1T"}, 1024 * 1024 * 1024 * 1024, false},
		{"-empty", args{""}, 0, false},
		{"-invalid_string", args{"foobar"}, 0, true},
		{"-invalid_suffix", args{"1A"}, 0, true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := parseByte(tt.args.in)
			if (err != nil) != tt.wantErr {
				t.Errorf("parseByte() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if got != tt.want {
				t.Errorf("parseByte() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_parseTime(t *testing.T) {
	type args struct {
		in string
	}
	tests := []struct {
		name    string
		args    args
		want    time.Duration
		wantErr bool
	}{
		{"+second", args{"60"}, time.Minute, false},
		{"+second_suffix", args{"60s"}, time.Minute, false},
		{"+minute", args{"1m"}, time.Minute, false},
		{"+hour", args{"1h"}, time.Hour, false},
		{"+hour", args{"1d"}, time.Hour * dayMultiplier, false},
		{"+hour", args{"1w"}, time.Hour * dayMultiplier * weekMultiplier, false},
		{"-empty", args{""}, 0, false},
		{"-invalid_string", args{"foobar"}, 0, true},
		{"-invalid_suffix", args{"1A"}, 0, true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := parseTime(tt.args.in)
			if (err != nil) != tt.wantErr {
				t.Errorf("parseTime() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if got != tt.want {
				t.Errorf("parseTime() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getAllMode(t *testing.T) {
	tests := []struct {
		name string
		want map[fs.FileMode]bool
	}{
		{"new", map[fs.FileMode]bool{
			regularFile:                       true,
			fs.ModeDir:                        true,
			fs.ModeSymlink:                    true,
			fs.ModeSocket:                     true,
			fs.ModeDevice:                     true,
			fs.ModeCharDevice + fs.ModeDevice: true,
			fs.ModeNamedPipe:                  true,
		}},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getAllMode(); !reflect.DeepEqual(got, tt.want) {
				t.Errorf("getAllMode() = %v, want %v", got, tt.want)
			}
		})
	}
}
