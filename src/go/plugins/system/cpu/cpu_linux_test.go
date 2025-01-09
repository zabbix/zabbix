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

package cpu

import (
	"reflect"
	"testing"
)

func TestPlugin_addCpu(t *testing.T) {
	type fields struct {
		cpus []*cpuUnit
	}
	type args struct {
		index int
	}
	tests := []struct {
		name   string
		fields fields
		args   args
		want   []*cpuUnit
	}{
		{
			"one_offline_cpu",
			fields{
				[]*cpuUnit{
					{index: -1, status: cpuStatusOffline},
					{index: 0, status: cpuStatusOffline},
					{index: 1, status: cpuStatusOffline},
				},
			},
			args{2},
			[]*cpuUnit{
				{index: -1, status: cpuStatusOffline},
				{index: 0, status: cpuStatusOffline},
				{index: 1, status: cpuStatusOffline},
				{index: 2, status: cpuStatusOffline},
			},
		},
		{
			"two_offline_cpu",
			fields{
				[]*cpuUnit{
					{index: -1, status: cpuStatusOffline},
					{index: 0, status: cpuStatusOffline},
					{index: 1, status: cpuStatusOffline},
				},
			},
			args{3},
			[]*cpuUnit{
				{index: -1, status: cpuStatusOffline},
				{index: 0, status: cpuStatusOffline},
				{index: 1, status: cpuStatusOffline},
				{index: 2, status: cpuStatusOffline},
				{index: 3, status: cpuStatusOffline},
			},
		},
		{
			"ten_offline_cpu",
			fields{
				[]*cpuUnit{
					{index: -1, status: cpuStatusOffline},
					{index: 0, status: cpuStatusOffline},
					{index: 1, status: cpuStatusOffline},
				},
			},
			args{11},
			[]*cpuUnit{
				{index: -1, status: cpuStatusOffline},
				{index: 0, status: cpuStatusOffline},
				{index: 1, status: cpuStatusOffline},
				{index: 2, status: cpuStatusOffline},
				{index: 3, status: cpuStatusOffline},
				{index: 4, status: cpuStatusOffline},
				{index: 5, status: cpuStatusOffline},
				{index: 6, status: cpuStatusOffline},
				{index: 7, status: cpuStatusOffline},
				{index: 8, status: cpuStatusOffline},
				{index: 9, status: cpuStatusOffline},
				{index: 10, status: cpuStatusOffline},
				{index: 11, status: cpuStatusOffline},
			},
		},
		{
			"no_offline_cpu",
			fields{
				[]*cpuUnit{
					{index: -1, status: cpuStatusOffline},
					{index: 0, status: cpuStatusOffline},
					{index: 1, status: cpuStatusOffline},
				},
			},
			args{1},
			[]*cpuUnit{
				{index: -1, status: cpuStatusOffline},
				{index: 0, status: cpuStatusOffline},
				{index: 1, status: cpuStatusOffline},
			},
		},
		{
			"empty", fields{[]*cpuUnit{}}, args{}, []*cpuUnit{},
		},
		{
			"nil", fields{nil}, args{}, nil,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			p := &Plugin{
				cpus: tt.fields.cpus,
			}
			p.addCpu(tt.args.index)

			if !reflect.DeepEqual(p.cpus, tt.want) {
				t.Errorf("addCpu() got = %v, want %v", p.cpus, tt.want)
			}
		})
	}
}
