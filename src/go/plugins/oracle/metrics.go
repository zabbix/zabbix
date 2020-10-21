package oracle

import (
	"context"

	"zabbix.com/pkg/plugin"
)

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(ctx context.Context, conn OraClient, params []string) (res interface{}, err error)

// getHandlerFunc returns a handlerFunc related to a given key.
func getHandlerFunc(key string) handlerFunc {
	switch key {
	case keyASMDiskGroups:
		return ASMDiskGroupsHandler
	case keyASMDiskGroupsDiscovery:
		return ASMDiskGroupsDiscovery
	case keyArchive:
		return archiveHandler
	case keyArchiveDiscovery:
		return archiveDiscoveryHandler
	case keyCDB:
		return CDBHandler
	case keyCustomQuery:
		return customQueryHandler // oracle.custom.query[<commonParams>,queryName[,args...]]
	case keyDataFiles:
		return DataFileHandler
	case keyDatabasesDiscovery:
		return databasesDiscoveryHandler
	case keyFRA:
		return FRAHandler
	case keyInstance:
		return instanceHandler
	case keyPDB:
		return PDBHandler
	case keyPDBDiscovery:
		return PDBDiscoveryHandler
	case keyPGA:
		return PGAHandler
	case keyPing:
		return pingHandler
	case keyProc:
		return ProcHandler
	case keyRedoLog:
		return RedoLogHandler
	case keySGA:
		return SGAHandler
	case keySessions:
		return sessionsHandler
	case keySysMetrics:
		return sysMetricsHandler // oracle.sys.metrics[<commonParams>[,duration]]
	case keySysParams:
		return sysParamsHandler
	case keyTablespaces:
		return tablespacesHandler
	case keyTablespacesDiscovery:
		return tablespacesDiscoveryHandler
	case keyUser:
		return UserHandler

	default:
		return nil
	}
}

func init() {
	plugin.RegisterMetrics(&impl, pluginName,
		keyASMDiskGroups, "Returns ASM disk groups statistics.",
		keyASMDiskGroupsDiscovery, "Returns list of ASM disk groups in LLD format.",
		keyArchive, "Returns archive logs statistics.",
		keyArchiveDiscovery, "Returns list of archive logs in LLD format.",
		keyCDB, "Returns CDBs info.",
		keyCustomQuery, "Returns result of a custom query.",
		keyDataFiles, "Returns data files statistics.",
		keyDatabasesDiscovery, "Returns list of databases in LLD format.",
		keyFRA, "Returns FRA statistics.",
		keyInstance, "Returns instance stats.",
		keyPDB, "Returns PDBs info.",
		keyPDBDiscovery, "Returns list of PDBs in LLD format.",
		keyPGA, "Returns PGA statistics.",
		keyPing, "Tests if connection is alive or not.",
		keyProc, "Returns processes statistics.",
		keyRedoLog, "Returns log file information from the control file.",
		keySGA, "Returns SGA statistics.",
		keySessions, "Returns sessions statistics.",
		keySysMetrics, "Returns a set of system metric values.",
		keySysParams, "Returns a set of system parameter values.",
		keyTablespaces, "Returns tablespaces statistics.",
		keyTablespacesDiscovery, "Returns list of tablespaces in LLD format.",
		keyUser, "Returns user information.")
}
