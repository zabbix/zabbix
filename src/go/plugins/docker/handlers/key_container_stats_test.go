/*
** Copyright (C) 2001-2026 Zabbix SIA
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
	"encoding/json"
	"math"
	"testing"
)

func Test_stats_setCPUPercentUsage(t *testing.T) {
	t.Parallel()

	type usage struct {
		total  uint64
		system uint64
	}

	tests := []struct {
		name       string
		cur        usage
		pre        usage
		onlineCPUs uint32
		percpu     []uint64
		want       float64
	}{
		{
			"+normal",
			usage{total: 3_000_000_000, system: 20_000_000_000},
			usage{total: 1_000_000_000, system: 10_000_000_000},
			4,
			nil,
			80,
		},
		{
			"+onlineCPUsFallbackToPercpu",
			usage{total: 3_000_000_000, system: 20_000_000_000},
			usage{total: 1_000_000_000, system: 10_000_000_000},
			0,
			[]uint64{1, 1},
			40,
		},
		{
			// values from ZBX-28108, total usage counter reset after container restart
			"-totalUsageReset",
			usage{total: 104_859_885, system: 66_207_037_850_000_000},
			usage{total: 8_740_746_497, system: 66_207_022_420_000_000},
			8,
			nil,
			0,
		},
		{
			"-systemUsageReset",
			usage{total: 3_000_000_000, system: 10_000_000_000},
			usage{total: 1_000_000_000, system: 20_000_000_000},
			4,
			nil,
			0,
		},
		{
			"-noPreviousSample",
			usage{total: 3_000_000_000, system: 20_000_000_000},
			usage{},
			4,
			nil,
			60,
		},
		{
			"-stoppedContainer",
			usage{},
			usage{},
			0,
			nil,
			0,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			s := stats{
				CPUStats: cpuStats{
					CPUUsage:    cpuUsage{TotalUsage: tt.cur.total, PercpuUsage: tt.percpu},
					SystemUsage: tt.cur.system,
					OnlineCPUs:  tt.onlineCPUs,
				},
				PreCPUStats: cpuStats{
					CPUUsage:    cpuUsage{TotalUsage: tt.pre.total},
					SystemUsage: tt.pre.system,
				},
			}

			s.setCPUPercentUsage()

			got := s.CPUStats.CPUUsage.PercentUsage
			if math.Abs(got-tt.want) > 1e-9 {
				t.Fatalf("setCPUPercentUsage() = %v, want %v", got, tt.want)
			}

			// NaN or Inf would make the whole item fail on marshaling
			if _, err := json.Marshal(s); err != nil {
				t.Fatalf("json.Marshal() error = %v", err)
			}
		})
	}
}
