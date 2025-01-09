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

package ceph

import (
	"encoding/json"
	"math"
	"sort"

	"golang.zabbix.com/sdk/zbxerr"
)

type aggDataInt struct {
	Min uint64  `json:"min"`
	Max uint64  `json:"max"`
	Avg float64 `json:"avg"`
}

// newAggDataInt calculates min, max and average values for a given slice of integers.
func newAggDataInt(v []uint64) aggDataInt {
	if len(v) == 0 {
		return aggDataInt{0, 0, 0}
	}

	var total uint64 = 0

	for _, value := range v {
		total += value
	}

	sort.Slice(v, func(i, j int) bool { return v[i] < v[j] })

	return aggDataInt{
		Min: v[0],
		Max: v[len(v)-1],
		Avg: float64(total) / float64(len(v)),
	}
}

type aggDataFloat struct {
	Min float64 `json:"min"`
	Max float64 `json:"max"`
	Avg float64 `json:"avg"`
}

// newAggDataFloat calculates min, max and average values for a given slice of floats.
func newAggDataFloat(v []float64) aggDataFloat {
	if len(v) == 0 {
		return aggDataFloat{0, 0, 0}
	}

	var total float64 = 0

	for _, value := range v {
		total += value
	}

	sort.Float64s(v)

	return aggDataFloat{
		Min: v[0],
		Max: v[len(v)-1],
		Avg: total / float64(len(v)),
	}
}

type cephPgDump struct {
	PgMap struct {
		OsdStats []struct {
			Name     json.Number `json:"osd"`
			PerfStat struct {
				LatencyApply  uint64 `json:"apply_latency_ms"`
				LatencyCommit uint64 `json:"commit_latency_ms"`
			} `json:"perf_stat"`
			NumPgs uint64  `json:"num_pgs"`
			Kb     float64 `json:"kb"`
			KbUsed float64 `json:"kb_used"`
		} `json:"osd_stats"`
	} `json:"pg_map"`
}

type osdStat struct {
	LatencyApply  uint64  `json:"osd_latency_apply"`
	LatencyCommit uint64  `json:"osd_latency_commit"`
	NumPgs        uint64  `json:"num_pgs"`
	OsdFill       float64 `json:"osd_fill"`
}

type outOsdStats struct {
	LatencyApply  aggDataInt         `json:"osd_latency_apply"`
	LatencyCommit aggDataInt         `json:"osd_latency_commit"`
	Fill          aggDataFloat       `json:"osd_fill"`
	Pgs           aggDataInt         `json:"osd_pgs"`
	Osds          map[string]osdStat `json:"osds"`
}

// osdHandler returns OSDs statistics provided by "pg dump" command.
func osdHandler(data map[command][]byte) (interface{}, error) {
	var pgDump cephPgDump

	err := json.Unmarshal(data[cmdPgDump], &pgDump)
	if err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	var (
		fill              float64
		latencyApplyList  []uint64
		latencyCommitList []uint64
		fillList          []float64
		PgsList           []uint64
		out               outOsdStats
	)

	out.Osds = make(map[string]osdStat)

	for _, osd := range pgDump.PgMap.OsdStats {
		fill = 0
		if osd.Kb != 0 {
			fill = math.Ceil(osd.KbUsed / osd.Kb * 100)
		}

		out.Osds[osd.Name.String()] = osdStat{
			LatencyApply:  osd.PerfStat.LatencyApply,
			LatencyCommit: osd.PerfStat.LatencyCommit,
			NumPgs:        osd.NumPgs,
			OsdFill:       fill,
		}

		latencyApplyList = append(latencyApplyList, osd.PerfStat.LatencyApply)
		latencyCommitList = append(latencyCommitList, osd.PerfStat.LatencyCommit)
		PgsList = append(PgsList, osd.NumPgs)
		fillList = append(fillList, fill)
	}

	out.LatencyApply = newAggDataInt(latencyApplyList)
	out.LatencyCommit = newAggDataInt(latencyCommitList)
	out.Fill = newAggDataFloat(fillList)
	out.Pgs = newAggDataInt(PgsList)

	jsonRes, err := json.Marshal(out)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
