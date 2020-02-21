/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package postgres

import (
	"errors"
	"fmt"
	"net/url"
	"strconv"
	"time"

	"zabbix.com/pkg/plugin"
)

const pluginName = "Postgres"

var maxParams = map[string]int{

	keyPostgresPing:                                      1,
	keyPostgresTransactions:                              1,
	keyPostgresConnections:                               1,
	keyPostgresWal:                                       1,
	keyPostgresStat:                                      2,
	keyPostgresStatSum:                                   1,
	keyPostgresReplicationCount:                          1,
	keyPostgresReplicationStatus:                         1,
	keyPostgresReplicationLagSec:                         1,
	keyPostgresReplicationRecoveryRole:                   1,
	keyPostgresReplicationLagB:                           1,
	keyPostgresReplicationMasterDiscoveryApplicationName: 1,
	keyPostgresLocks:                                     1,
	keyPostgresOldestXid:                                 1,
	keyPostgresUptime:                                    1,
	keyPostgresCache:                                     1,
	keyPostgresSizeArchive:                               1,
	keyPostgresDiscoveryDatabases:                        2,
	keyPostgresDatabasesBloating:                         2,
	keyPostgresDatabasesSize:                             2,
	keyPostgresDatabasesAge:                              2,
}

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	connMgr *connManager
	options PluginOptions
}

type handler func(conn *postgresConn, params []string) (res interface{}, err error)

// impl is the pointer to the plugin implementation.
var impl Plugin

func (p *Plugin) Start() {
	p.connMgr = p.NewConnManager(
		time.Duration(p.options.KeepAlive)*time.Second,
		time.Duration(p.options.Timeout)*time.Second,
	)
}

func (p *Plugin) Stop() {
	p.connMgr.stop()
	p.connMgr = nil
}

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var (
		connString string
		handler    handler
	)

	if len(params) > 0 && len(params[0]) > 0 {
		// TODO: identify parameter by type: port,host or smth else
		// for now expect only one parameter and expect it to be a database name
		strPort := strconv.Itoa(int(p.options.Port))
		databaseName := url.PathEscape(params[0])
		connString = "postgresql://" + p.options.User + ":" + p.options.Password + "@" + p.options.Host + ":" + strPort + "/" + databaseName
	} else {
		strPort := strconv.Itoa(int(p.options.Port))
		connString = "postgresql://" + p.options.User + ":" + p.options.Password + "@" + p.options.Host + ":" + strPort + "/" + p.options.Database
	}

	switch key {
	case keyPostgresDiscoveryDatabases:
		handler = p.databasesDiscoveryHandler // postgres.databasesdiscovery[[connString][,section]]

	case keyPostgresDatabasesBloating:
		handler = p.databasesBloatingHandler // postgres.databases[[connString][,section]]

	case keyPostgresDatabasesSize:
		handler = p.databasesSizeHandler // postgres.databases[[connString][,section]]

	case keyPostgresDatabasesAge:
		handler = p.databasesAgeHandler // postgres.databases[[connString][,section]]

	case keyPostgresTransactions:
		handler = p.transactionsHandler // postgres.transactions[[connString]]

	case keyPostgresSizeArchive:
		handler = p.archiveHandler // postgres.archive[[connString]]

	case keyPostgresPing:
		handler = p.pingHandler // postgres.ping[[connString]]

	case keyPostgresConnections:
		handler = p.connectionsHandler // postgres.connections[[connString]]

	case keyPostgresWal:
		handler = p.walHandler // postgres.wal[[connString]]

	case keyPostgresAutovacuum:
		handler = p.autovacuumHandler // postgres.autovacuum.count[[connString]]

	case keyPostgresStat,
		keyPostgresStatSum:
		handler = p.dbStatHandler // postgres.stat[[connString][,section]]
		params = make([]string, 1)
		params[0] = key

	case keyPostgresBgwriter:
		handler = p.bgwriterHandler // postgres.bgwriter[[connString]]

	case keyPostgresUptime:
		handler = p.uptimeHandler // postgres.uptime[[connString]]

	case keyPostgresCache:
		handler = p.cacheHandler // postgres.cache[[connString]]

	case keyPostgresReplicationCount,
		keyPostgresReplicationStatus,
		keyPostgresReplicationLagSec,
		keyPostgresReplicationRecoveryRole,
		keyPostgresReplicationLagB,
		keyPostgresReplicationMasterDiscoveryApplicationName:
		handler = p.replicationHandler // postgres.replication[[connString][,section]]
		params = make([]string, 1)
		params[0] = key

	case keyPostgresLocks:
		handler = p.locksHandler // postgres.locks[[connString]]

	case keyPostgresOldestXid:
		handler = p.oldestHandler // postgres.locks[[connString]

	default:
		return nil, errorUnsupportedMetric
	}

	if len(params) > maxParams[key] {
		return nil, errorTooManyParameters
	}

	conn, err := p.connMgr.GetPostgresConnection(connString)
	if err != nil {
		// Here is another logic of processing connection errors if postgres.ping is requested
		if key == keyPostgresPing {
			return postgresPingFailed, nil
		}
		p.Errf(err.Error())
		fmt.Println(" error in get postgres if connection ", key)
		if len(params) > 0 {
			fmt.Print(params[0])
		}
		return nil, errors.New(formatZabbixError(err.Error()))
	}

	return handler(conn, params)
}

// init registers metrics.
func init() {
	plugin.RegisterMetrics(&impl, pluginName,
		keyPostgresPing, "Test if connection is alive or not.",
		keyPostgresTransactions, "Returns JSON for active,idle, waiting and prepared transactions.",
		keyPostgresConnections, "Returns JSON for sum of each type of connection.",
		keyPostgresWal, "Returns JSON wal by type.",
		keyPostgresStat, "Returns JSON for sum of each type of statistic.",
		keyPostgresBgwriter, "Returns JSON for sum of each type of bgwriter statistic.",
		keyPostgresUptime, "Returns uptime.",
		keyPostgresCache, "Returns cache hit percent.",
		keyPostgresSizeArchive, "Returns info about size of archive files.",
		keyPostgresDiscoveryDatabases, "Returns JSON discovery rule with names of databases.",
		keyPostgresDatabasesBloating, "Returns percent of bloating tables for each database.",
		keyPostgresDatabasesSize, "Returns size for each database.",
		keyPostgresDatabasesAge, "Returns age for each database.",
		keyPostgresStatSum, "Returns JSON for sum of each type of statistic for all database.",
		keyPostgresReplicationCount, "Returns number of standby servers.",
		keyPostgresReplicationStatus, "Returns postgreSQL replication status.",
		keyPostgresReplicationLagSec, "Returns replication lag with Master in seconds.",
		keyPostgresReplicationLagB, "Returns replication lag with Master in byte.",
		keyPostgresReplicationRecoveryRole, "Returns postgreSQL recovery role.",
		keyPostgresLocks, "Returns collect all metrics from pg_locks.",
		keyPostgresOldestXid, "Returns age of oldest xid.",
		keyPostgresAutovacuum, "Returns count of autovacuum workers.",
		keyPostgresReplicationMasterDiscoveryApplicationName, "Returns JSON discovery with application name from pg_stat_replication.",
	)
	/* registerConnectionsMertics() */
}
