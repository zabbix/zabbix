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
	"fmt"
	"strings"

	"zabbix.com/pkg/zbxerr"
)

type cephStatus struct {
	PgMap struct {
		PgsByState []struct {
			StateName string `json:"state_name"`
			Count     uint64 `json:"count"`
		} `json:"pgs_by_state"`
		NumPgs uint64 `json:"num_pgs"`
	} `json:"pgmap"`
	Health struct {
		Status string `json:"status"`
	} `json:"health"`
	OsdMap struct {
		NumOsds   uint64 `json:"num_osds"`
		NumInOsds uint64 `json:"num_in_osds"`
		NumUpOsds uint64 `json:"num_up_osds"`
	} `json:"osdmap"`
	MonMap struct {
		NumMons           uint64 `json:"num_mons"`
		MinMonReleaseName string `json:"min_mon_release_name"`
	} `json:"monmap"`
}

type outStatus struct {
	OverallStatus     int8              `json:"overall_status"`
	NumMon            uint64            `json:"num_mon"`
	NumOsd            uint64            `json:"num_osd"`
	NumOsdIn          uint64            `json:"num_osd_in"`
	NumOsdUp          uint64            `json:"num_osd_up"`
	NumPg             uint64            `json:"num_pg"`
	PgStates          map[string]uint64 `json:"pg_states"`
	MinMonReleaseName string            `json:"min_mon_release_name"`
}

// pgStates is a list of all possible placement group states according to the Ceph's documentation.
// https://docs.ceph.com/en/octopus/rados/operations/pg-states/
var pgStates = []string{"creating", "activating", "active", "clean", "down", "laggy", "wait", "scrubbing", "deep",
	"degraded", "inconsistent", "peering", "repair", "recovering", "forced_recovery", "recovery_wait",
	"recovery_toofull", "recovery_unfound", "backfilling", "forced_backfill", "backfill_wait", "backfill_toofull",
	"backfill_unfound", "incomplete", "stale", "remapped", "undersized", "peered", "snaptrim", "snaptrim_wait",
	"snaptrim_error", "unknown"}

var healthMap = map[string]int8{
	"HEALTH_OK":   0,
	"HEALTH_WARN": 1,
	"HEALTH_ERR":  2,
}

// statusHandler returns data provided by "status" command.
func statusHandler(data map[command][]byte) (interface{}, error) {
	var status cephStatus

	err := json.Unmarshal(data[cmdStatus], &status)
	if err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	var intHealth int8

	if val, ok := healthMap[status.Health.Status]; ok {
		intHealth = val
	} else {
		return nil, zbxerr.ErrorCannotParseResult.Wrap(fmt.Errorf("unknown health status %q", status.Health.Status))
	}

	var pgStats = make(map[string]uint64)

	for _, s := range pgStates {
		pgStats[s] = 0
	}

	for _, pbs := range status.PgMap.PgsByState {
		for _, s := range strings.Split(pbs.StateName, "+") {
			if _, ok := pgStats[s]; ok {
				pgStats[s] += pbs.Count
			} else {
				return nil, zbxerr.ErrorCannotParseResult.Wrap(fmt.Errorf("unknown pg state %q", s))
			}
		}
	}

	out := outStatus{
		OverallStatus:     intHealth,
		NumMon:            status.MonMap.NumMons,
		NumOsd:            status.OsdMap.NumOsds,
		NumOsdIn:          status.OsdMap.NumInOsds,
		NumOsdUp:          status.OsdMap.NumUpOsds,
		NumPg:             status.PgMap.NumPgs,
		PgStates:          pgStats,
		MinMonReleaseName: status.MonMap.MinMonReleaseName,
	}

	jsonRes, err := json.Marshal(out)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
