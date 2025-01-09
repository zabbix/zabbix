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

package oracle

import (
	"context"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
)

const (
	keyASMDiskGroups          = "oracle.diskgroups.stats"
	keyASMDiskGroupsDiscovery = "oracle.diskgroups.discovery"
	keyArchive                = "oracle.archive.info"
	keyArchiveDiscovery       = "oracle.archive.discovery"
	keyCDB                    = "oracle.cdb.info"
	keyCustomQuery            = "oracle.custom.query"
	keyDataFiles              = "oracle.datafiles.stats"
	keyDatabasesDiscovery     = "oracle.db.discovery"
	keyFRA                    = "oracle.fra.stats"
	keyInstance               = "oracle.instance.info"
	keyPDB                    = "oracle.pdb.info"
	keyPDBDiscovery           = "oracle.pdb.discovery"
	keyPGA                    = "oracle.pga.stats"
	keyPing                   = "oracle.ping"
	keyProc                   = "oracle.proc.stats"
	keyRedoLog                = "oracle.redolog.info"
	keySGA                    = "oracle.sga.stats"
	keySessions               = "oracle.sessions.stats"
	keySysMetrics             = "oracle.sys.metrics"
	keySysParams              = "oracle.sys.params"
	keyTablespaces            = "oracle.ts.stats"
	keyTablespacesDiscovery   = "oracle.ts.discovery"
	keyUser                   = "oracle.user.info"
	keyVersion                = "oracle.version"
)

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(
	ctx context.Context, conn OraClient, params map[string]string, extraParams ...string,
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
	case keyASMDiskGroups:
		return asmDiskGroupsHandler
	case keyASMDiskGroupsDiscovery:
		return asmDiskGroupsDiscovery
	case keyArchive:
		return archiveHandler
	case keyArchiveDiscovery:
		return archiveDiscoveryHandler
	case keyCDB:
		return cdbHandler
	case keyCustomQuery:
		return customQueryHandler
	case keyDataFiles:
		return dataFileHandler
	case keyDatabasesDiscovery:
		return databasesDiscoveryHandler
	case keyFRA:
		return fraHandler
	case keyInstance:
		return instanceHandler
	case keyPDB:
		return pdbHandler
	case keyPDBDiscovery:
		return pdbDiscoveryHandler
	case keyPGA:
		return pgaHandler
	case keyPing:
		return pingHandler
	case keyProc:
		return procHandler
	case keyRedoLog:
		return redoLogHandler
	case keySGA:
		return sgaHandler
	case keySessions:
		return sessionsHandler
	case keySysMetrics:
		return sysMetricsHandler
	case keySysParams:
		return sysParamsHandler
	case keyTablespaces:
		return tablespacesHandler
	case keyTablespacesDiscovery:
		return tablespacesDiscoveryHandler
	case keyUser:
		return userHandler
	case keyVersion:
		return versionHandler
	default:
		return nil
	}
}

var uriDefaults = &uri.Defaults{Scheme: "tcp", Port: "1521"}

// Common params: [URI|Session][,User][,Password][,Service]
var (
	paramURI = metric.NewConnParam("URI", "URI to connect or session name.").
			WithDefault(uriDefaults.Scheme + "://localhost:" + uriDefaults.Port).
			WithSession().
			WithValidator(uri.URIValidator{Defaults: uriDefaults, AllowedSchemes: []string{"tcp"}})
	paramUsername = metric.NewConnParam("User", "Oracle user.").WithDefault("")
	paramPassword = metric.NewConnParam("Password", "User's password.").
			WithDefault("")
	paramService = metric.NewConnParam("Service", "Service name to be used for connection.").
			WithDefault("XE")
)

var metrics = metric.MetricSet{
	keyASMDiskGroups: metric.New(
		"Returns ASM disk groups statistics.",
		[]*metric.Param{
			paramURI, paramUsername, paramPassword, paramService,
			metric.NewParam("Diskgroup", "Diskgroup name."),
		},
		false,
	),

	keyASMDiskGroupsDiscovery: metric.New(
		"Returns list of ASM disk groups in LLD format.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyArchive: metric.New(
		"Returns archive logs statistics.",
		[]*metric.Param{
			paramURI, paramUsername, paramPassword, paramService,
			metric.NewParam("Destination", "Destination name."),
		},
		false,
	),

	keyArchiveDiscovery: metric.New(
		"Returns list of archive logs in LLD format.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyCDB: metric.New(
		"Returns CDBs info.",
		[]*metric.Param{
			paramURI, paramUsername, paramPassword, paramService,
			metric.NewParam("Database", "Database name."),
		},
		false,
	),

	keyCustomQuery: metric.New(
		"Returns result of a custom query.",
		[]*metric.Param{
			paramURI, paramUsername, paramPassword, paramService,
			metric.NewParam(
				"QueryName", "Name of a custom query "+
					"(must be equal to a name of an SQL file without an extension).",
			).SetRequired(),
		},
		true,
	),

	keyDataFiles: metric.New(
		"Returns data files statistics.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyDatabasesDiscovery: metric.New(
		"Returns list of databases in LLD format.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyFRA: metric.New(
		"Returns FRA statistics.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyInstance: metric.New(
		"Returns instance stats.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyPDB: metric.New(
		"Returns PDBs info.",
		[]*metric.Param{
			paramURI, paramUsername, paramPassword, paramService,
			metric.NewParam("Database", "Database name."),
		},
		false,
	),

	keyPDBDiscovery: metric.New(
		"Returns list of PDBs in LLD format.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyPGA: metric.New(
		"Returns PGA statistics.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyPing: metric.New(
		"Tests if connection is alive or not.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyProc: metric.New(
		"Returns processes statistics.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyRedoLog: metric.New(
		"Returns log file information from the control file.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keySGA: metric.New(
		"Returns SGA statistics.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keySessions: metric.New(
		"Returns sessions statistics.",
		[]*metric.Param{
			paramURI, paramUsername, paramPassword, paramService,
			metric.NewParam(
				"LockMaxTime",
				"Maximum session lock duration in seconds to count "+
					"the session as a prolongedly locked.",
			).
				WithDefault("600").
				WithValidator(metric.NumberValidator{}),
		},
		false,
	),

	keySysMetrics: metric.New(
		"Returns a set of system metric values.",
		[]*metric.Param{
			paramURI, paramUsername, paramPassword, paramService,
			metric.NewParam(
				"Duration",
				"Capturing interval in seconds of system metric values.",
			).
				WithDefault("60").
				WithValidator(metric.SetValidator{Set: []string{"15", "60"}}),
		},
		false,
	),

	keySysParams: metric.New(
		"Returns a set of system parameter values.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyTablespaces: metric.New(
		"Returns tablespaces statistics.",
		[]*metric.Param{
			paramURI, paramUsername, paramPassword, paramService,
			metric.NewParam("Tablespace", "Table-space name."),
			metric.NewParam("Type", "table-space type."),
			metric.NewParam("Conname", "Container name."),
		},
		false,
	),

	keyTablespacesDiscovery: metric.New(
		"Returns list of tablespaces in LLD format.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),

	keyUser: metric.New(
		"Returns user information.",
		[]*metric.Param{
			paramURI, paramUsername, paramPassword, paramService,
			metric.NewParam(
				"Username",
				"Username for which the information is needed.",
			),
		},
		false,
	),

	keyVersion: metric.New(
		"Returns database version.",
		[]*metric.Param{paramURI, paramUsername, paramPassword, paramService},
		false,
	),
}
