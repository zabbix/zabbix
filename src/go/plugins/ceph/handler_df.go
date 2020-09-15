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

package ceph

import (
	"encoding/json"
	"math"
	"zabbix.com/pkg/zbxerr"
)

type cephDf struct {
	Stats struct {
		TotalBytes      uint64 `json:"total_bytes"`
		TotalAvailBytes uint64 `json:"total_avail_bytes"`
		TotalUsedBytes  uint64 `json:"total_used_bytes"`
	} `json:"stats"`
	Pools []struct {
		Name  string `json:"name"`
		Stats struct {
			PercentUsed float64 `json:"percent_used"`
			Objects     uint64  `json:"objects"`
			BytesUsed   uint64  `json:"bytes_used"`
			Rd          uint64  `json:"rd"`
			RdBytes     uint64  `json:"rd_bytes"`
			Wr          uint64  `json:"wr"`
			WrBytes     uint64  `json:"wr_bytes"`
			StoredRaw   uint64  `json:"stored_raw"`
		} `json:"stats"`
	} `json:"pools"`
}

type poolStat struct {
	PercentUsed float64 `json:"percent_used"`
	BytesUsed   uint64  `json:"bytes_used"`
	Rd          uint64  `json:"rd_ops"`
	RdBytes     uint64  `json:"rd_bytes"`
	Wr          uint64  `json:"wr_ops"`
	WrBytes     uint64  `json:"wr_bytes"`
	StoredRaw   uint64  `json:"stored_raw"`
}

type outDf struct {
	Pools           map[string]poolStat `json:"pools"`
	Rd              uint64              `json:"rd_ops"`
	RdBytes         uint64              `json:"rd_bytes"`
	Wr              uint64              `json:"wr_ops"`
	WrBytes         uint64              `json:"wr_bytes"`
	NumPools        int                 `json:"num_pools"`
	TotalBytes      uint64              `json:"total_bytes"`
	TotalAvailBytes uint64              `json:"total_avail_bytes"`
	TotalUsedBytes  uint64              `json:"total_used_bytes"`
	TotalObjects    uint64              `json:"total_objects"`
}

// dfHandler TODO.
func dfHandler(data []byte) (interface{}, error) {
	var df cephDf

	err := json.Unmarshal(data, &df)
	if err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON
	}

	var totalObjects uint64 = 0
	var poolsStat = make(map[string]poolStat)
	var totalPool poolStat

	for _, p := range df.Pools {
		poolsStat[p.Name] = poolStat{
			PercentUsed: math.Round(p.Stats.PercentUsed),
			BytesUsed:   p.Stats.BytesUsed,
			Rd:          p.Stats.Rd,
			RdBytes:     p.Stats.RdBytes,
			Wr:          p.Stats.Wr,
			WrBytes:     p.Stats.WrBytes,
			StoredRaw:   p.Stats.StoredRaw,
		}

		totalObjects += p.Stats.Objects

		totalPool.Rd += p.Stats.Rd
		totalPool.RdBytes += p.Stats.RdBytes
		totalPool.Wr += p.Stats.Wr
		totalPool.WrBytes += p.Stats.WrBytes
	}

	out := outDf{
		Pools:           poolsStat,
		Rd:              totalPool.Rd,
		RdBytes:         totalPool.RdBytes,
		Wr:              totalPool.Wr,
		WrBytes:         totalPool.WrBytes,
		NumPools:        len(df.Pools),
		TotalBytes:      df.Stats.TotalBytes,
		TotalAvailBytes: df.Stats.TotalAvailBytes,
		TotalUsedBytes:  df.Stats.TotalUsedBytes,
		TotalObjects:    totalObjects,
	}

	jsonRes, err := json.Marshal(out)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON
	}

	return string(jsonRes), nil
}
