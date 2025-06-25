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
	"errors"
	"net/http"
	"net/url"
	"strings"
	"time"

	"github.com/godror/godror/dsn"
	"github.com/omeid/go-yarn"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	pluginName = "Oracle"
	hkInterval = 10
	sqlExt     = ".sql"
)

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	connMgr *ConnManager
	options PluginOptions
}

// impl is the pointer to the plugin implementation.
var impl Plugin

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, rawParams []string, _ plugin.ContextProvider) (result interface{}, err error) {
	params, extraParams, hc, err := metrics[key].EvalParams(rawParams, p.options.Sessions)
	if err != nil {
		return nil, err
	}

	err = metric.SetDefaults(params, hc, p.options.Default)
	if err != nil {
		return nil, err
	}

	service := url.QueryEscape(params["Service"])

	user, adminRole, err := splitUserAndPrivilege(params)
	if err != nil {
		return nil, zbxerr.ErrorInvalidParams.Wrap(err)
	}

	uri, err := uri.NewWithCreds(params["URI"]+"?service="+service, user, params["Password"], uriDefaults)
	if err != nil {
		return nil, err
	}

	handleMetric := getHandlerFunc(key)
	if handleMetric == nil {
		return nil, zbxerr.ErrorUnsupportedMetric
	}

	conn, err := p.connMgr.getConnection(&connDetails{*uri, adminRole})
	if err != nil {
		// Special logic of processing connection errors should be used if oracle.ping is requested
		// because it must return pingFailed if any error occurred.
		if key == keyPing {
			return pingFailed, nil
		}

		p.Errf(err.Error())

		return nil, err
	}

	ctx, cancel := context.WithTimeout(conn.ctx, conn.callTimeout)
	defer cancel()

	result, err = handleMetric(ctx, conn, params, extraParams...)

	if err != nil {
		p.Errf(err.Error())
	}

	return result, err
}

// Start implements the Runner interface and performs initialization when plugin is activated.
func (p *Plugin) Start() {
	p.connMgr = NewConnManager(
		time.Duration(p.options.KeepAlive)*time.Second,
		time.Duration(p.options.ConnectTimeout)*time.Second,
		time.Duration(p.options.CallTimeout)*time.Second,
		hkInterval*time.Second,
		p.setCustomQuery(),
	)
}

func (p *Plugin) setCustomQuery() yarn.Yarn {
	if p.options.CustomQueriesPath == "" {
		return yarn.NewFromMap(map[string]string{})
	}

	queryStorage, err := yarn.New(http.Dir(p.options.CustomQueriesPath), "*"+sqlExt)
	if err != nil {
		p.Errf(err.Error())
		// create empty storage if error occurred
		return yarn.NewFromMap(map[string]string{})
	}

	return queryStorage
}

// Stop implements the Runner interface and frees resources when plugin is deactivated.
func (p *Plugin) Stop() {
	p.connMgr.Destroy()
	p.connMgr = nil
}

// splitUserAndPrivilege parses a user string that may contain a privilege role.
// It accepts formats like "system", "system as sysdba", or "SYSTEM AS SYSDBA".
// It returns the clean username, a validated dsn.AdminRole, and an error if the
// format is invalid or the role is unknown.
func splitUserAndPrivilege(params map[string]string) (string, dsn.AdminRole, error) {
	var validAdminRoles = map[dsn.AdminRole]bool{
		dsn.SysDBA:    true,
		dsn.SysOPER:   true,
		dsn.SysBACKUP: true,
		dsn.SysDG:     true,
		dsn.SysKM:     true,
		dsn.SysRAC:    true,
		dsn.SysASM:    true,
	}

	userStr, ok := params["User"]
	if !ok {
		// No 'User' parameter provided.
		return "", "", errors.New("missing parameter 'User'")
	}

	// strings.Fields is robust against multiple spaces and leading/trailing whitespace.
	parts := strings.Fields(userStr)

	switch len(parts) {
	case 0:
		// The user string was empty or just whitespace.
		return "", "", nil // returning nil to keep backwards compatible code
	case 1:
		// A simple username with no privilege, e.g., "myuser". This is valid.
		return parts[0], dsn.NoRole, nil
	case 3:
		// Potential "user as privilege" format.
		// Use strings.EqualFold for a case-insensitive "as" check.
		if !strings.EqualFold(parts[1], "as") {
			return userStr, dsn.NoRole, nil // returning given string and no error to keep backwards compatible code
		}

		role := dsn.AdminRole(strings.ToUpper(parts[2]))

		if validAdminRoles[role] {
			return parts[0], role, nil
		}

		return userStr, dsn.NoRole, nil // returning given string and no error to keep backwards compatible code
	}

	// All other formats (e.g., "user sysdba", "user as", "user as sysdba extra") are invalid.
	return "", dsn.NoRole,
		errs.Wrap(errs.New("invalid user format: expected 'user' or 'user as <privilege>', but got :"), userStr)
}
