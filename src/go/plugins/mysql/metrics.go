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

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
)

const (
	keyCustomQuery            = "mysql.custom.query"
	keyDatabasesDiscovery     = "mysql.db.discovery"
	keyDatabaseSize           = "mysql.db.size"
	keyPing                   = "mysql.ping"
	keyReplicationDiscovery   = "mysql.replication.discovery"
	keyReplicationSlaveStatus = "mysql.replication.get_slave_status"
	keyStatusVars             = "mysql.get_status_variables"
	keyVersion                = "mysql.version"

	uriParam        = "URI"
	tlsConnectParam = "TLSConnect"
	tlsCAParam      = "TLSCAFile"
	tlsCertParam    = "TLSCertFile"
	tlsKeyParam     = "TLSKeyFile"
	masterHostParam = "Master"
)

var (
	uriDefaults = &uri.Defaults{Scheme: "tcp", Port: "3306"}

	// Common params: [URI|Session][,User][,Password]
	paramURI = metric.NewConnParam(uriParam, "URI to connect or session name.").
			WithDefault(uriDefaults.Scheme + "://localhost:" + uriDefaults.Port).WithSession().
			WithValidator(uri.URIValidator{Defaults: uriDefaults, AllowedSchemes: []string{"tcp", "unix"}})
	paramUsername    = metric.NewConnParam("User", "MySQL user.").WithDefault("root")
	paramPassword    = metric.NewConnParam("Password", "User's password.").WithDefault("")
	paramTLSConnect  = metric.NewSessionOnlyParam("TLSConnect", "DB connection encryption type.").WithDefault("")
	paramTLSCaFile   = metric.NewSessionOnlyParam("TLSCAFile", "TLS ca file path.").WithDefault("")
	paramTLSCertFile = metric.NewSessionOnlyParam("TLSCertFile", "TLS cert file path.").WithDefault("")
	paramTLSKeyFile  = metric.NewSessionOnlyParam("TLSKeyFile", "TLS key file path.").WithDefault("")

	metrics = metric.MetricSet{
		keyCustomQuery: metric.New("Returns result of a custom query.",
			[]*metric.Param{
				paramURI, paramUsername, paramPassword,
				metric.NewParam("QueryName", "Name of a custom query "+
					"(must be equal to a name of an SQL file without an extension).").SetRequired(),
				paramTLSConnect, paramTLSCaFile, paramTLSCertFile, paramTLSKeyFile,
			}, true),

		keyDatabasesDiscovery: metric.New("Returns list of databases in LLD format.",
			[]*metric.Param{
				paramURI, paramUsername, paramPassword, paramTLSConnect, paramTLSCaFile, paramTLSCertFile,
				paramTLSKeyFile,
			}, false),

		keyDatabaseSize: metric.New("Returns size of given database in bytes.",
			[]*metric.Param{
				paramURI, paramUsername, paramPassword,
				metric.NewParam("Database", "Database name.").SetRequired(),
				paramTLSConnect, paramTLSCaFile, paramTLSCertFile, paramTLSKeyFile,
			}, false),

		keyPing: metric.New("Tests if connection is alive or not.",
			[]*metric.Param{
				paramURI, paramUsername, paramPassword, paramTLSConnect, paramTLSCaFile, paramTLSCertFile,
				paramTLSKeyFile,
			}, false),

		keyReplicationDiscovery: metric.New("Returns replication information in LLD format.",
			[]*metric.Param{
				paramURI, paramUsername, paramPassword, paramTLSConnect, paramTLSCaFile, paramTLSCertFile,
				paramTLSKeyFile,
			}, false),

		keyReplicationSlaveStatus: metric.New("Returns replication status.",
			[]*metric.Param{
				paramURI, paramUsername, paramPassword, metric.NewParam(masterHostParam, "Master host."),
				paramTLSConnect, paramTLSCaFile, paramTLSCertFile, paramTLSKeyFile,
			}, false),

		keyStatusVars: metric.New("Returns values of global status variables.",
			[]*metric.Param{
				paramURI, paramUsername, paramPassword, paramTLSConnect, paramTLSCaFile, paramTLSCertFile,
				paramTLSKeyFile,
			}, false),

		keyVersion: metric.New("Returns MySQL version.",
			[]*metric.Param{
				paramURI, paramUsername, paramPassword, paramTLSConnect, paramTLSCaFile, paramTLSCertFile,
				paramTLSKeyFile,
			}, false),
	}
)

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(
	ctx context.Context, conn MyClient, params map[string]string, extraParams ...string,
) (res interface{}, err error)

func init() {
	err := plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// getHandlerFunc returns a handlerFunc related to a given key.
func getHandlerFunc(key string) handlerFunc {
	switch key {
	case keyCustomQuery:
		return customQueryHandler
	case keyDatabasesDiscovery:
		return databasesDiscoveryHandler
	case keyDatabaseSize:
		return databaseSizeHandler
	case keyPing:
		return pingHandler
	case keyReplicationDiscovery:
		return replicationDiscoveryHandler
	case keyReplicationSlaveStatus:
		return replicationSlaveStatusHandler
	case keyStatusVars:
		return statusVarsHandler
	case keyVersion:
		return versionHandler
	default:
		return nil
	}
}
