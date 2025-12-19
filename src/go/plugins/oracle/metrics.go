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

	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/agent2/plugins/oracle/handlers"
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

var metricsMeta = map[string]handlerFunc{ //nolint:gochecknoglobals
	keyASMDiskGroups:          handlers.AsmDiskGroupsHandler,
	keyASMDiskGroupsDiscovery: handlers.AsmDiskGroupsDiscovery,
	keyArchive:                handlers.ArchiveHandler,
	keyArchiveDiscovery:       handlers.ArchiveDiscoveryHandler,
	keyCDB:                    handlers.CdbHandler,
	keyCustomQuery:            handlers.CustomQueryHandler,
	keyDataFiles:              handlers.DataFileHandler,
	keyDatabasesDiscovery:     handlers.DatabasesDiscoveryHandler,
	keyFRA:                    handlers.FraHandler,
	keyInstance:               handlers.InstanceHandler,
	keyPDB:                    handlers.PdbHandler,
	keyPDBDiscovery:           handlers.PdbDiscoveryHandler,
	keyPGA:                    handlers.PgaHandler,
	keyPing:                   handlers.PingHandler,
	keyProc:                   handlers.ProcHandler,
	keyRedoLog:                handlers.RedoLogHandler,
	keySGA:                    handlers.SgaHandler,
	keySessions:               handlers.SessionsHandler,
	keySysMetrics:             handlers.SysMetricsHandler,
	keySysParams:              handlers.SysParamsHandler,
	keyTablespaces:            handlers.TablespacesHandler,
	keyTablespacesDiscovery:   handlers.TablespacesDiscoveryHandler,
	keyUser:                   handlers.UserHandler,
	keyVersion:                handlers.VersionHandler,
}

// Common params: [URI|Session][,User][,Password][,Service].
var (
	paramURI = metric.NewConnParam("URI", "URI to connect or session name."). //nolint:gochecknoglobals
			WithDefault(dbconn.URIDefaults.Scheme + "://localhost:" + dbconn.URIDefaults.Port).
			WithSession().
			WithValidator(uri.URIValidator{Defaults: dbconn.URIDefaults, AllowedSchemes: []string{"tcp"}})
	paramUsername = metric.NewConnParam("User", "Oracle user."). //nolint:gochecknoglobals
			WithDefault("")
	paramPassword = metric.NewConnParam("Password", "User's password."). //nolint:gochecknoglobals
			WithDefault("")
	paramService = metric.NewConnParam("Service", "Service name to be used for connection."). //nolint:gochecknoglobals
			WithDefault("XE")
)

var metrics = metric.MetricSet{ //nolint:gochecknoglobals
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

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(
	ctx context.Context, conn dbconn.OraClient, params map[string]string, extraParams ...string,
) (res any, err error)

func init() { //nolint:gochecknoinits
	err := plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}
