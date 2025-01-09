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

	"golang.zabbix.com/sdk/zbxerr"
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
			MaxAvail    uint64  `json:"max_avail"`
		} `json:"stats"`
	} `json:"pools"`
}

type poolStat struct {
	PercentUsed float64 `json:"percent_used"`
	Objects     uint64  `json:"objects"`
	BytesUsed   uint64  `json:"bytes_used"`
	Rd          uint64  `json:"rd_ops"`
	RdBytes     uint64  `json:"rd_bytes"`
	Wr          uint64  `json:"wr_ops"`
	WrBytes     uint64  `json:"wr_bytes"`
	StoredRaw   uint64  `json:"stored_raw"`
	MaxAvail    uint64  `json:"max_avail"`
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

// dfHandler returns statistics provided by "df detail" command.
func dfHandler(data map[command][]byte) (interface{}, error) {
	var df cephDf

	err := json.Unmarshal(data[cmdDf], &df)
	if err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	var (
		totalObjects uint64
		totalPool    poolStat
	)

	poolsStat := make(map[string]poolStat)

	for _, p := range df.Pools {
		poolsStat[p.Name] = poolStat{
			PercentUsed: p.Stats.PercentUsed,
			Objects:     p.Stats.Objects,
			BytesUsed:   p.Stats.BytesUsed,
			Rd:          p.Stats.Rd,
			RdBytes:     p.Stats.RdBytes,
			Wr:          p.Stats.Wr,
			WrBytes:     p.Stats.WrBytes,
			StoredRaw:   p.Stats.StoredRaw,
			MaxAvail:    p.Stats.MaxAvail,
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
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
