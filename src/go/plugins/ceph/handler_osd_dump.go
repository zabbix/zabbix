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

package ceph

import (
	"encoding/json"
	"math"

	"git.zabbix.com/ap/plugin-support/zbxerr"
)

type cephOsdDump struct {
	BackfillFullRatio float64 `json:"backfillfull_ratio"`
	FullRatio         float64 `json:"full_ratio"`
	NearFullRatio     float64 `json:"nearfull_ratio"`
	Osds              []struct {
		Name json.Number `json:"osd"`
		osdStatus
	} `json:"osds"`
	PgTemp []struct{} `json:"pg_temp"`
}

type osdStatus struct {
	In int8 `json:"in"`
	Up int8 `json:"up"`
}

type outOsdDump struct {
	BackfillFullRatio float64              `json:"osd_backfillfull_ratio"`
	FullRatio         float64              `json:"osd_full_ratio"`
	NearFullRatio     float64              `json:"osd_nearfull_ratio"`
	NumPgTemp         int                  `json:"num_pg_temp"`
	Osds              map[string]osdStatus `json:"osds"`
}

// osdDumpHandler returns OSDs dump provided by "osd dump" command.
func osdDumpHandler(data map[command][]byte) (interface{}, error) {
	var osdDump cephOsdDump

	err := json.Unmarshal(data[cmdOSDDump], &osdDump)
	if err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	out := outOsdDump{
		NumPgTemp:         len(osdDump.PgTemp),
		BackfillFullRatio: math.Round(osdDump.BackfillFullRatio*100) / 100,
		FullRatio:         math.Round(osdDump.FullRatio*100) / 100,
		NearFullRatio:     math.Round(osdDump.NearFullRatio*100) / 100,
		Osds:              make(map[string]osdStatus),
	}

	for _, osd := range osdDump.Osds {
		out.Osds[osd.Name.String()] = osdStatus{
			In: osd.In,
			Up: osd.Up,
		}
	}

	jsonRes, err := json.Marshal(out)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
