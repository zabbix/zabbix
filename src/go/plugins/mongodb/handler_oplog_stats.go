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

package mongodb

import (
	"encoding/json"
	"time"

	"git.zabbix.com/ap/plugin-support/zbxerr"
	"gopkg.in/mgo.v2"
	"gopkg.in/mgo.v2/bson"
)

type oplogStats struct {
	TimeDiff int `json:"timediff"` // in seconds
}

type oplogEntry struct {
	Timestamp bson.MongoTimestamp `bson:"ts"`
}

const (
	oplogReplicaSet  = "oplog.rs"    // the capped collection that holds the oplog for Replica Set Members
	oplogMasterSlave = "oplog.$main" // oplog for the master-slave configuration
)

const (
	sortAsc  = "$natural"
	sortDesc = "-$natural"
)

var oplogQuery = bson.M{"ts": bson.M{"$exists": true}}

// oplogStatsHandler
// https://docs.mongodb.com/manual/reference/method/db.getReplicationInfo/index.html
func oplogStatsHandler(s Session, _ map[string]string) (interface{}, error) {
	var (
		stats           oplogStats
		opFirst, opLast oplogEntry
	)

	localDb := s.DB("local")

	for _, collection := range []string{oplogReplicaSet, oplogMasterSlave} {
		if err := localDb.C(collection).Find(oplogQuery).
			Sort(sortAsc).Limit(1).
			SetMaxTime(time.Duration(s.GetMaxTimeMS()) * time.Millisecond).
			One(&opFirst); err != nil {
			if err == mgo.ErrNotFound {
				continue
			}

			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		if err := localDb.C(collection).Find(oplogQuery).Sort(sortDesc).Limit(1).One(&opLast); err != nil {
			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		break
	}

	// BSON has a special timestamp type for internal MongoDB use and is not associated with the regular Date type.
	// This internal timestamp type is a 64 bit value where:
	// the most significant 32 bits are a time_t value (seconds since the Unix epoch)
	// the least significant 32 bits are an incrementing ordinal for operations within a given second.
	stats.TimeDiff = int(opLast.Timestamp>>32 - opFirst.Timestamp>>32)

	jsonRes, err := json.Marshal(stats)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
