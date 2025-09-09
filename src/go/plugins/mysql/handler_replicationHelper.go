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

package mysql

import "golang.org/x/mod/semver"

// In MySQL 8.4, `Master_Host` was fully replaced by `Source_Host`.
const (
	masterKey = "Master_Host"
	sourceKey = "Source_Host"
)

// In MySQL 8.4, `SHOW SLAVE STATUS` was fully replaced by `SHOW REPLICA STATUS`.
const (
	replicaQueryOld replicQuery = `SHOW SLAVE STATUS`
	replicaQueryNew replicQuery = `SHOW REPLICA STATUS`
)

const versionThreshold = "8.4"

type replicQuery string

// getReplicationQuery function returns right query depending on the MySQL server versionThreshold.
func getReplicationQuery(version string) replicQuery {
	if semver.Compare("v"+versionThreshold, "v"+version) <= 0 {
		return replicaQueryNew
	}

	return replicaQueryOld
}

// extractMasterHost function extracts master or source host (depending on the MySQL server versionThreshold)
// values.
func extractMasterHost(data []map[string]string) []map[string]string {
	res := make([]map[string]string, 0)

	for _, row := range data {
		masterOrSourceHost := row[sourceKey]

		if masterOrSourceHost == "" {
			masterOrSourceHost = row[masterKey]
		}

		if masterOrSourceHost != "" {
			res = append(res, map[string]string{masterKey: masterOrSourceHost})
		}
	}

	return res
}
