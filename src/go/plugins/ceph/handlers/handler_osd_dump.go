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

package handlers

import (
	"encoding/json"
	"math"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

type cephOsdDump struct {
	BackfillFullRatio float64    `json:"backfillfull_ratio"`
	FullRatio         float64    `json:"full_ratio"`
	NearFullRatio     float64    `json:"nearfull_ratio"`
	Osds              []struct { //nolint:revive //part of ceph response
		Name json.Number `json:"osd"`
		osdStatus
	} `json:"osds"`
	PgTemp []struct{} `json:"pg_temp"` //nolint:revive //part of ceph response
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

// osdDumpHandler returns OSDs dump provided by "osd dump" Command.
func osdDumpHandler(data map[Command][]byte) (any, error) {
	var osdDump cephOsdDump

	err := json.Unmarshal(data[cmdOSDDump], &osdDump)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
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
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return string(jsonRes), nil
}
