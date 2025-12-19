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

import (
	"context"
	"database/sql"
	"strings"

	"golang.zabbix.com/sdk/errs"
)

// In MySQL 9.x, `SHOW SLAVE STATUS` is fully replaced by `SHOW REPLICA STATUS`. To maintain compatibility with
// both new and old MySQL versions without a separate version-check query, the new query style is attempted  first
// and fell back to the old, if failed. The old style result has 'Master' & 'Slave' terminology used in its
// keys, new style - 'Source' and 'Replica'.
// For Example, the old style key value pair:	"Master_Host": "myserver" or "Slave_IO_Running": "Yes",
// new style, respectively: 					"Source_Host": "myserver" or "Replica_IO_Running": "Yes".
const (
	masterKey = "Master_Host"
	sourceKey = "Source_Host"
)

var pioneeringQueries = []string{ //nolint:gochecknoglobals //readability
	// Applicable for MySQL & Percona since 8.0.22 and MariaDB.
	`SHOW REPLICA STATUS`,
	// Applicable for older versions of MySQL & Percona pre-8.0 and MariaDB. The last one supports both queries but
	// returns old style result.
	`SHOW SLAVE STATUS`,
}

var (
	substituteRulesOld2New = substituteRules{ //nolint:gochecknoglobals //readability
		// The whole key specified as exception because in MySQL 8.3 styles have a different initial letter case.
		"Get_master_public_key": "Get_Source_public_key",
		"Master":                "Source",
		"Slave":                 "Replica",
	}

	substituteRulesNew2Old = substituteRules{ //nolint:gochecknoglobals //readability
		"Get_Source_public_key": "Get_master_public_key",
		"Source":                "Master",
		"Replica":               "Slave",
	}
)
var errGetStatusFailed = errs.New("error getting slave or replica status")

// substituteRules defines what key or part of the key is to be substituted by another.
type substituteRules map[string]string

// querySlaveOrReplicaStatus function returns a data map from a query which tries to retrieve the data using
// old and new query. A result of the succeeded one is returned. Starts with the new style to minimize
// execution of the wrong query.
func querySlaveOrReplicaStatus(ctx context.Context, conn MyClient) ([]map[string]string, error) {
	var (
		rows           *sql.Rows
		err            error
		querySucceeded bool
	)

	for _, query := range pioneeringQueries {
		rows, err = conn.Query(ctx, query)
		if err == nil {
			querySucceeded = true

			break
		}
	}

	if !querySucceeded {
		// if both attempts fail, returns an error from the *last* attempted query.
		return nil, errs.WrapConst(err, errGetStatusFailed)
	}

	data, err := rows2data(rows)
	if err != nil {
		return nil, errs.WrapConst(err, errGetStatusFailed)
	}

	return data, nil
}

// substituteKey function takes an individual key and makes substitutions according to the rules.
func substituteKey(key string, rules substituteRules) string {
	// Processes the entire key if it is subject to substitution. If successful, there is no need to process
	// individual parts.
	for k, v := range rules {
		if key == k {
			return v
		}
	}

	// Processes a key parts. Once substituted there is no need to process next rule to avoid the incidental loop
	// by improper rules definition.
	parts := strings.Split(key, "_")
	for i, part := range parts {
		for k, v := range rules {
			if part == k {
				parts[i] = v

				continue
			}
		}
	}

	return strings.Join(parts, "_")
}

// duplicate function creates duplicated records from 'keyValueRow' by copying and substituting records keys
// according to rules. The function returns a map with original and additional records.
func duplicate(keyValueRow map[string]string, rules substituteRules) map[string]string {
	newMap := make(map[string]string, len(keyValueRow))
	for k, v := range keyValueRow {
		newMap[k] = v // keep original key

		newKey := substituteKey(k, rules)
		if newKey != k {
			newMap[newKey] = v // add substituted key if different
		}
	}

	return newMap
}

// duplicateAll function creates a new maps slice containing maps with duplicated records.
func duplicateAll(keyValueRows []map[string]string, rules substituteRules) []map[string]string {
	result := make([]map[string]string, 0, len(keyValueRows))

	for _, keyValueRow := range keyValueRows {
		duplicated := duplicate(keyValueRow, rules)
		result = append(result, duplicated)
	}

	return result
}

// duplicateByKey function creates a new maps slice containing maps with only records with key/value passed by 'key'.
func duplicateByKey(keyValueRows []map[string]string, key string, rules substituteRules) []map[string]string {
	res := make([]map[string]string, 0)

	for _, row := range keyValueRows {
		res = append(res, duplicate(map[string]string{key: row[key]}, rules))
	}

	return res
}

// isOldSyle returns true if the result is based of the old style query. All results in the array are always based
// on the same style.
func isOldSyle(keyValueRows []map[string]string) bool {
	if len(keyValueRows) < 1 {
		return false
	}

	return keyValueRows[0][masterKey] != ""
}
