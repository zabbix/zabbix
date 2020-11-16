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
	"context"
	"errors"
	"net/http"
	"time"

	"github.com/omeid/go-yarn"
	"zabbix.com/pkg/plugin"
)

var (
	opts     PluginOptions
	err      error
	database string
)

const (
	pluginName = "Postgres"
	sqlExt     = ".sql"
	hkInterval = 10
	// Common params: [connString][,user][,password][,database]
	commonParamsNum = 4
)

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	connMgr *ConnManager
	options PluginOptions
}

type requestHandler func(ctx context.Context, conn PostgresClient, key string, params []string) (res interface{}, err error)

// impl is the pointer to the plugin implementation.
var impl Plugin

// Start implements the Runner interface and performs initialization when plugin is activated.
func (p *Plugin) Start() {
	queryStorage, err := yarn.New(http.Dir(p.options.CustomQueriesPath), "*"+sqlExt)
	if err != nil {
		p.Errf(err.Error())
		// create empty storage if error occurred
		queryStorage = yarn.NewFromMap(map[string]string{})
	}
	p.connMgr = p.NewConnManager(
		time.Duration(p.options.KeepAlive)*time.Second,
		time.Duration(p.options.ConnectTimeout)*time.Second,
		time.Duration(p.options.CallTimeout)*time.Second,
		hkInterval*time.Second,
		queryStorage,
	)
}

// Stop implements the Runner interface and frees resources when plugin is deactivated.
func (p *Plugin) Stop() {
	p.connMgr.Destroy()
	p.connMgr = nil
}

// whereToConnect builds a session based on key's parameters and a configuration file.
func whereToConnect(params []string, defaultPluginOptions *PluginOptions) (u *URI, err error) {
	var uri string
	user := ""
	if len(params) > 1 {
		user = params[1]
	}

	password := ""
	if len(params) > 2 {
		password = params[2]
	}

	database := defaultPluginOptions.Database
	if len(params) > 3 {
		database = params[3]
	}

	// The first param can be either a URI or a session identifier
	if len(params) > 0 && len(params[0]) > 0 {
		if isLooksLikeURI(params[0]) {
			// Use the URI defined as key's parameter
			uri = params[0]
		} else {
			if _, ok := defaultPluginOptions.Sessions[params[0]]; !ok {
				return nil, errorUnknownSession
			}

			// Use a pre-defined session
			uri = defaultPluginOptions.Sessions[params[0]].URI
			user = defaultPluginOptions.Sessions[params[0]].User
			password = defaultPluginOptions.Sessions[params[0]].Password
			database = defaultPluginOptions.Sessions[params[0]].Database
		}
	}

	if len(user) > 0 || len(password) > 0 || len(database) > 0 {
		return newURIWithCreds(uri, user, password, database)
	}

	return parseURI(uri)
}

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (result interface{}, err error) {
	var (
		handler       requestHandler
		handlerParams []string
	)

	u, err := whereToConnect(params, &p.options)
	if err != nil {
		return nil, err
	}

	// get connection string for PostgreSQL
	connString := u.URI()
	switch key {
	case keyPostgresDiscoveryDatabases:
		handler = p.databasesDiscoveryHandler // postgres.databasesdiscovery[[connString][,section]]

	case keyPostgresDatabasesBloating:
		handler = p.databasesBloatingHandler // postgres.databases[[connString][,section]]

	case keyPostgresDatabasesSize:
		handler = p.databasesSizeHandler // postgres.databases[[connString][,section]]

	case keyPostgresDatabasesAge:
		handler = p.databasesAgeHandler // postgres.databases[[connString][,section]]

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

	case keyPostgresBgwriter:
		handler = p.bgwriterHandler // postgres.bgwriter[[connString]]

	case keyPostgresCustom:
		handler = p.customQueryHandler // postgres.custom.query[[connString][,section]]

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

	case keyPostgresLocks:
		handler = p.locksHandler // postgres.locks[[connString]]

	case keyPostgresOldestXid:
		handler = p.oldestHandler // postgres.oldestXid[[connString]

	default:
		return nil, errorUnsupportedMetric
	}

	if len(params) > commonParamsNum && key != keyPostgresCustom {
		return nil, errorTooManyParameters
	}

	conn, err := p.connMgr.GetPostgresConnection(connString)
	if err != nil {
		// Here is another logic of processing connection errors if postgres.ping is requested
		if key == keyPostgresPing {
			return postgresPingFailed, nil
		}
		p.Errf("connection error: %s", err)
		p.Debugf("parameters: %+v", params)
		return nil, errors.New(formatZabbixError(err.Error()))
	}

	ctx, cancel := context.WithTimeout(conn.ctx, time.Duration(p.options.CallTimeout)*time.Second)
	defer cancel()

	if key == keyPostgresDatabasesSize || key == keyPostgresDatabasesAge {
		handlerParams = []string{u.Database()}
	} else if key == keyPostgresCustom {
		handlerParams = params[commonParamsNum:]
	} else {
		handlerParams = make([]string, 0)
	}
	return handler(ctx, conn, key, handlerParams)
}

// init registers metrics.
func init() {
	plugin.RegisterMetrics(&impl, pluginName,
		keyPostgresPing, "Test if connection is alive or not.",
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
		keyPostgresCustom, "Returns results for custom queries from files.",
		keyPostgresAutovacuum, "Returns count of autovacuum workers.",
		keyPostgresReplicationMasterDiscoveryApplicationName, "Returns JSON discovery with application name from pg_stat_replication.",
	)
	/* registerConnectionsMertics() */
}
