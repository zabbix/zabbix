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
	"strings"

	"golang.zabbix.com/sdk/errs"
)

// pgStates is a list of all possible placement group states according to the Ceph's documentation.
// https://docs.ceph.com/en/octopus/rados/operations/pg-states/
var pgStates = []string{
	"creating",
	"activating",
	"active",
	"clean",
	"down",
	"laggy",
	"wait",
	"scrubbing",
	"deep",
	"degraded",
	"inconsistent",
	"peering",
	"repair",
	"recovering",
	"forced_recovery",
	"recovery_wait",
	"recovery_toofull",
	"recovery_unfound",
	"backfilling",
	"forced_backfill",
	"backfill_wait",
	"backfill_toofull",
	"backfill_unfound",
	"incomplete",
	"stale",
	"remapped",
	"undersized",
	"peered",
	"snaptrim",
	"snaptrim_wait",
	"snaptrim_error",
	"unknown",
}

var healthMap = map[string]int8{
	"HEALTH_OK":   0,
	"HEALTH_WARN": 1,
	"HEALTH_ERR":  2,
}

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
	OSDMap struct {
		NumOsds   *uint64 `json:"num_osds"`
		NumInOsds *uint64 `json:"num_in_osds"`
		NumUpOsds *uint64 `json:"num_up_osds"`
		OSDMap    *struct {
			NumOsds   uint64 `json:"num_osds"`
			NumInOsds uint64 `json:"num_in_osds"`
			NumUpOsds uint64 `json:"num_up_osds"`
		} `json:"osdmap"`
	} `json:"osdmap"`
	MonMap struct {
		NumMons           *uint64    `json:"num_mons"`
		MinMonReleaseName string     `json:"min_mon_release_name"`
		Mons              []struct{} `json:"mons"`
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

// statusHandler returns data provided by "status" command.
func statusHandler(data map[command][]byte) (any, error) {
	cStatus := &cephStatus{}

	err := json.Unmarshal(data[cmdStatus], cStatus)
	if err != nil {
		return nil, errs.Wrap(err, "failed to unmarshal status output")
	}

	intHealth, ok := healthMap[cStatus.Health.Status]
	if !ok {
		return nil, errs.Errorf(
			"unknown health status %q", cStatus.Health.Status,
		)
	}

	pgStats := make(map[string]uint64)

	for _, s := range pgStates {
		pgStats[s] = 0
	}

	for _, pbs := range cStatus.PgMap.PgsByState {
		for _, s := range strings.Split(pbs.StateName, "+") {
			if _, ok := pgStats[s]; ok {
				pgStats[s] += pbs.Count
			} else {
				return nil, errs.Errorf("unknown pg state %q", s)
			}
		}
	}

	status := outStatus{
		OverallStatus:     intHealth,
		NumPg:             cStatus.PgMap.NumPgs,
		PgStates:          pgStats,
		MinMonReleaseName: cStatus.MonMap.MinMonReleaseName,
	}

	switch {
	case cStatus.MonMap.NumMons != nil:
		status.NumMon = *cStatus.MonMap.NumMons
	case cStatus.MonMap.Mons != nil:
		status.NumMon = uint64(len(cStatus.MonMap.Mons))
	default:
		return nil, errs.New(
			"unable to get data for num_mon: " +
				"both monmap.num_mons and monmap.mons are empty",
		)
	}

	switch {
	case cStatus.OSDMap.NumOsds != nil &&
		cStatus.OSDMap.NumInOsds != nil &&
		cStatus.OSDMap.NumUpOsds != nil:
		status.NumOsd = *cStatus.OSDMap.NumOsds
		status.NumOsdIn = *cStatus.OSDMap.NumInOsds
		status.NumOsdUp = *cStatus.OSDMap.NumUpOsds
	case cStatus.OSDMap.OSDMap != nil:
		status.NumOsd = cStatus.OSDMap.OSDMap.NumOsds
		status.NumOsdIn = cStatus.OSDMap.OSDMap.NumInOsds
		status.NumOsdUp = cStatus.OSDMap.OSDMap.NumUpOsds
	default:
		return nil, errs.New(
			"unable to get data for num_osd, num_osd_in and num_osd_up: " +
				"both osdmap.num_* and osdmap.osdmap.num_* are empty",
		)
	}

	jsonRes, err := json.Marshal(status)
	if err != nil {
		return nil, errs.Wrap(err, "failed to marshal status output")
	}

	return string(jsonRes), nil
}
