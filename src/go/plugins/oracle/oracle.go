/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package oracle

import (
	"context"
	"errors"
	"github.com/godror/godror"
	"github.com/omeid/go-yarn"
	"net/http"
	"time"

	"zabbix.com/pkg/plugin"
)

const pluginName = "Oracle"

const hkInterval = 10

// Common params: [connString][,user][,password][,service]
const commonParamsNum = 4

const sqlExt = ".sql"

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	connMgr *ConnManager
	options PluginOptions
}

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(ctx context.Context, conn OraClient, params []string) (res interface{}, err error)

// impl is the pointer to the plugin implementation.
var impl Plugin

// whereToConnect builds a URI based on key's parameters and a configuration file.
func whereToConnect(params []string, sessions map[string]*Session, defaultURI string) (u *URI, err error) {
	// TODO: rework it!
	user := ""
	if len(params) > 1 {
		user = params[1]
	}

	password := ""
	if len(params) > 2 {
		password = params[2]
	}

	serviceName := ""
	if len(params) > 3 {
		serviceName = params[3]
	}

	uri := defaultURI

	// The first param can be either a URI or a session identifier
	if len(params) > 0 && len(params[0]) > 0 {
		if isLooksLikeURI(params[0]) {
			// Use a URI defined as key's parameter
			uri = params[0]
		} else {
			if _, ok := sessions[params[0]]; !ok {
				return nil, errorUnknownSession
			}

			// Use a pre-defined session
			uri = sessions[params[0]].URI
			user = sessions[params[0]].User
			password = sessions[params[0]].Password
			serviceName = sessions[params[0]].ServiceName
		}
	}

	return newURIWithCreds(uri, user, password, serviceName)

}

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (result interface{}, err error) {
	var (
		handleMetric  handlerFunc
		handlerParams []string
		zbxErr        zabbixError
	)

	uri, err := whereToConnect(params, p.options.Sessions, p.options.URI)
	if err != nil {
		return nil, err
	}

	// Extract handler related params
	if len(params) > commonParamsNum {
		handlerParams = params[commonParamsNum:]
	}

	switch key {
	case keyASMDiskGroups:
		handleMetric = ASMDiskGroupsHandler
	case keyASMDiskGroupsDiscovery:
		handleMetric = ASMDiskGroupsDiscovery
	case keyArchive:
		handleMetric = archiveHandler
	case keyArchiveDiscovery:
		handleMetric = archiveDiscoveryHandler
	case keyCDB:
		handleMetric = CDBHandler
	case keyCustomQuery:
		handleMetric = customQueryHandler // oracle.custom.query[<commonParams>,queryName[,args...]]
	case keyDataFiles:
		handleMetric = DataFileHandler
	case keyDatabasesDiscovery:
		handleMetric = databasesDiscoveryHandler
	case keyFRA:
		handleMetric = FRAHandler
	case keyInstance:
		handleMetric = instanceHandler
	case keyPDB:
		handleMetric = PDBHandler
	case keyPDBDiscovery:
		handleMetric = PDBDiscoveryHandler
	case keyPGA:
		handleMetric = PGAHandler
	case keyPing:
		handleMetric = pingHandler
	case keyProc:
		handleMetric = ProcHandler
	case keySGA:
		handleMetric = SGAHandler
	case keySessions:
		handleMetric = sessionsHandler
	case keySysMetrics:
		handleMetric = sysMetricsHandler
	case keySysParams:
		handleMetric = sysParamsHandler
	case keyTablespaces:
		handleMetric = tablespacesHandler
	case keyTablespacesDiscovery:
		handleMetric = tablespacesDiscoveryHandler

	default:
		return nil, errorUnsupportedMetric
	}

	conn, err := p.connMgr.GetConnection(*uri)
	if err != nil {
		// Special logic of processing connection errors should be used if redis.ping is requested
		// because it must return pingFailed if any error occurred.
		if key == keyPing {
			return pingFailed, nil
		}

		if oraErr, isOraErr := godror.AsOraErr(err); isOraErr {
			p.Errf(oraErr.Error())
			return nil, zabbixError{oraErr.Error()}
		}

		p.Errf(err.Error())

		return nil, zabbixError{err.Error()}
	}

	ctx, cancel := context.WithTimeout(conn.ctx, conn.callTimeout)
	defer cancel()

	result, err = handleMetric(ctx, conn, handlerParams)

	if err != nil {
		if oraErr, isOraErr := godror.AsOraErr(err); isOraErr {
			p.Errf(oraErr.Error())
		} else {
			p.Errf(err.Error())
		}

		if errors.As(err, &zbxErr) {
			return nil, zbxErr
		}
	}

	return result, nil
}

// Start implements the Runner interface and performs initialization when plugin is activated.
func (p *Plugin) Start() {
	queryStorage, err := yarn.New(http.Dir(p.options.CustomQueriesPath), "*"+sqlExt)
	if err != nil {
		p.Errf(err.Error())
		// create empty storage if error occurred
		queryStorage = yarn.NewFromMap(map[string]string{})
	}

	p.connMgr = NewConnManager(
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

func init() {
	plugin.RegisterMetrics(&impl, pluginName,
		keyASMDiskGroups, "Returns ASM disk groups statistics.",
		keyASMDiskGroupsDiscovery, "Returns list of ASM disk groups in LLD format.",
		keyArchive, "Returns archive logs statistics.",
		keyArchiveDiscovery, "Returns list of archive logs in LLD format.",
		keyCDB, "Returns CDBs info.",
		keyCustomQuery, "Returns result of custom query.",
		keyDataFiles, "Returns data files statistics.",
		keyDatabasesDiscovery, "Returns list of databases in LLD format.",
		keyFRA, "Returns FRA statistics.",
		keyInstance, "Returns instance stats.",
		keyPDB, "Returns PDBs info.",
		keyPDBDiscovery, "Returns list of PDBs in LLD format.",
		keyPGA, "Returns PGA statistics.",
		keyPing, "Tests if connection is alive or not.",
		keyProc, "Returns processes statistics.",
		keySGA, "Returns SGA statistics.",
		keySessions, "Returns sessions statistics.",
		keySysMetrics, "Returns a set of system metric values.",
		keySysParams, "Returns a set of system parameter values.",
		keyTablespaces, "Returns tablespaces statistics.",
		keyTablespacesDiscovery, "Returns list of tablespaces in LLD format.")
}
