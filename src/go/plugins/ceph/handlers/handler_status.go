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
	"strings"

	"golang.zabbix.com/sdk/errs"
)

// pgStates is a list of all possible placement group states according to the Ceph's documentation.
// https://docs.ceph.com/en/octopus/rados/operations/pg-states/
var pgStates = []string{ //nolint:gochecknoglobals //used as a static const
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

var healthMap = map[string]int8{ //nolint:gochecknoglobals // used as a static const
	"HEALTH_OK":   0,
	"HEALTH_WARN": 1,
	"HEALTH_ERR":  2,
}

type cephStatus struct {
	PgMap struct { //nolint:revive //part of response from ceph
		PgsByState []struct { //nolint:revive //part of response from ceph
			StateName string `json:"state_name"`
			Count     uint64 `json:"count"`
		} `json:"pgs_by_state"`
		NumPgs uint64 `json:"num_pgs"`
	} `json:"pgmap"`
	Health struct { //nolint:revive //part of response from ceph
		Status string `json:"status"`
	} `json:"health"`
	OSDMap struct { //nolint:revive //part of response from ceph
		NumOsds   *uint64   `json:"num_osds"`
		NumInOsds *uint64   `json:"num_in_osds"`
		NumUpOsds *uint64   `json:"num_up_osds"`
		OSDMap    *struct { //nolint:revive //part of response from ceph
			NumOsds   uint64 `json:"num_osds"`
			NumInOsds uint64 `json:"num_in_osds"`
			NumUpOsds uint64 `json:"num_up_osds"`
		} `json:"osdmap"`
	} `json:"osdmap"`
	MonMap struct { //nolint:revive //part of response from ceph
		NumMons           *uint64    `json:"num_mons"`
		MinMonReleaseName string     `json:"min_mon_release_name"`
		Mons              []struct{} `json:"mons"` //nolint:revive //part of response from ceph
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

// statusHandler returns data provided by "status" Command.
func statusHandler(data map[Command][]byte) (any, error) {
	cStatus := &cephStatus{}

	err := json.Unmarshal(data[cmdStatus], cStatus)
	if err != nil {
		return nil, errs.Wrap(err, "failed to unmarshal status output")
	}

	intHealth, ok := healthMap[cStatus.Health.Status]
	if !ok {
		return nil, errs.Errorf("unknown health status %q", cStatus.Health.Status)
	}

	pgStats := make(map[string]uint64, len(pgStates))
	for _, s := range pgStates {
		pgStats[s] = 0
	}

	for _, pbs := range cStatus.PgMap.PgsByState {
		for _, s := range strings.Split(pbs.StateName, "+") {
			if _, ok := pgStats[s]; !ok {
				return nil, errs.Errorf("unknown pg state %q", s)
			}

			pgStats[s] += pbs.Count
		}
	}

	status := outStatus{
		OverallStatus:     intHealth,
		NumPg:             cStatus.PgMap.NumPgs,
		PgStates:          pgStats,
		MinMonReleaseName: cStatus.MonMap.MinMonReleaseName,
	}

	err = getMonData(cStatus, &status)
	if err != nil {
		return nil, err
	}

	err = getOSDData(cStatus, &status)
	if err != nil {
		return nil, err
	}

	jsonRes, err := json.Marshal(status)
	if err != nil {
		return nil, errs.Wrap(err, "failed to marshal status output")
	}

	return string(jsonRes), nil
}

// getMonData extracts monitor count information from the cephStatus and populates the outStatus.
func getMonData(c *cephStatus, s *outStatus) error {
	if c.MonMap.NumMons != nil {
		s.NumMon = *c.MonMap.NumMons

		return nil
	}

	if c.MonMap.Mons != nil {
		s.NumMon = uint64(len(c.MonMap.Mons))

		return nil
	}

	return errs.New("unable to get data for num_mon: both monmap.num_mons and monmap.mons are empty")
}

// getOSDData extracts OSD count information from the cephStatus and populates the outStatus.
func getOSDData(c *cephStatus, s *outStatus) error {
	if c.OSDMap.NumOsds != nil && c.OSDMap.NumInOsds != nil && c.OSDMap.NumUpOsds != nil {
		s.NumOsd = *c.OSDMap.NumOsds
		s.NumOsdIn = *c.OSDMap.NumInOsds
		s.NumOsdUp = *c.OSDMap.NumUpOsds

		return nil
	}

	if c.OSDMap.OSDMap != nil {
		s.NumOsd = c.OSDMap.OSDMap.NumOsds
		s.NumOsdIn = c.OSDMap.OSDMap.NumInOsds
		s.NumOsdUp = c.OSDMap.OSDMap.NumUpOsds

		return nil
	}

	return errs.New("unable to get data for num_osd, num_osd_in and num_osd_up: " +
		"both osdmap.num_* and osdmap.osdmap.num_* are empty")
}
