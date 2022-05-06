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
	"git.zabbix.com/ap/plugin-support/metric"
	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/uri"
)

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(s Session, params map[string]string) (res interface{}, err error)

// getHandlerFunc returns a handlerFunc related to a given key.
func getHandlerFunc(key string) handlerFunc {
	switch key {
	case keyConfigDiscovery:
		return configDiscoveryHandler

	case keyCollectionStats:
		return collectionStatsHandler

	case keyCollectionsDiscovery:
		return collectionsDiscoveryHandler

	case keyCollectionsUsage:
		return collectionsUsageHandler

	case keyConnPoolStats:
		return connPoolStatsHandler

	case keyDatabaseStats:
		return databaseStatsHandler

	case keyDatabasesDiscovery:
		return databasesDiscoveryHandler

	case keyJumboChunks:
		return jumboChunksHandler

	case keyOplogStats:
		return oplogStatsHandler

	case keyPing:
		return pingHandler

	case keyReplSetConfig:
		return replSetConfigHandler

	case keyReplSetStatus:
		return replSetStatusHandler

	case keyServerStatus:
		return serverStatusHandler

	case keyShardsDiscovery:
		return shardsDiscoveryHandler

	default:
		return nil
	}
}

const (
	keyConfigDiscovery      = "mongodb.cfg.discovery"
	keyCollectionStats      = "mongodb.collection.stats"
	keyCollectionsDiscovery = "mongodb.collections.discovery"
	keyCollectionsUsage     = "mongodb.collections.usage"
	keyConnPoolStats        = "mongodb.connpool.stats"
	keyDatabaseStats        = "mongodb.db.stats"
	keyDatabasesDiscovery   = "mongodb.db.discovery"
	keyJumboChunks          = "mongodb.jumbo_chunks.count"
	keyOplogStats           = "mongodb.oplog.stats"
	keyPing                 = "mongodb.ping"
	keyReplSetConfig        = "mongodb.rs.config"
	keyReplSetStatus        = "mongodb.rs.status"
	keyServerStatus         = "mongodb.server.status"
	keyShardsDiscovery      = "mongodb.sh.discovery"
)

var uriDefaults = &uri.Defaults{Scheme: "tcp", Port: "27017"}

// Common params: [URI|Session][,User][,Password]
var (
	paramURI = metric.NewConnParam("URI", "URI to connect or session name.").
			WithDefault(uriDefaults.Scheme + "://localhost:" + uriDefaults.Port).WithSession().
			WithValidator(uri.URIValidator{Defaults: uriDefaults, AllowedSchemes: []string{"tcp"}})
	paramUser       = metric.NewConnParam("User", "MongoDB user.")
	paramPassword   = metric.NewConnParam("Password", "User's password.")
	paramDatabase   = metric.NewParam("Database", "Database name.").WithDefault("admin")
	paramCollection = metric.NewParam("Collection", "Collection name.").SetRequired()
)

var metrics = metric.MetricSet{
	keyConfigDiscovery: metric.New("Returns a list of discovered config servers.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyCollectionStats: metric.New("Returns a variety of storage statistics for a given collection.",
		[]*metric.Param{paramURI, paramUser, paramPassword, paramDatabase, paramCollection}, false),

	keyCollectionsDiscovery: metric.New("Returns a list of discovered collections.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyCollectionsUsage: metric.New("Returns usage statistics for collections.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyConnPoolStats: metric.New("Returns information regarding the open outgoing connections from the "+
		"current database instance to other members of the sharded cluster or replica set.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyDatabaseStats: metric.New("Returns statistics reflecting a given database system’s state.",
		[]*metric.Param{paramURI, paramUser, paramPassword, paramDatabase}, false),

	keyDatabasesDiscovery: metric.New("Returns a list of discovered databases.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyJumboChunks: metric.New("Returns count of jumbo chunks.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyOplogStats: metric.New("Returns a status of the replica set, using data polled from the oplog.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyPing: metric.New("Test if connection is alive or not.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyReplSetConfig: metric.New("Returns a current configuration of the replica set.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyReplSetStatus: metric.New("Returns a replica set status from the point of view of the member "+
		"where the method is run.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyServerStatus: metric.New("Returns a database’s state.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyShardsDiscovery: metric.New("Returns a list of discovered shards present in the cluster.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),
}

func init() {
	plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
}
