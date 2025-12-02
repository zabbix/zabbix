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

package localtime

import (
	"strconv"
	"time"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const pluginName = "Localtime"

var impl Plugin //nolint:gochecknoglobals

// Plugin is a base struct for a plugin.
type Plugin struct {
	plugin.Base
}

//nolint:gochecknoinits
func init() {
	err := plugin.RegisterMetrics(
		&impl, pluginName,
		"system.localtime", "Returns system local time.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Export implements Exporter interface.
func (*Plugin) Export(_ string, params []string, _ plugin.ContextProvider) (any, error) {
	if len(params) > 1 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	requestType := "utc"
	if len(params) == 1 && params[0] != "" {
		requestType = params[0]
	}

	now := time.Now()

	switch requestType {
	case "utc":
		return strconv.Itoa(int(now.Unix())), nil
	case "local":
		const layout = "2006-01-02,15:04:05.000,-07:00"

		return now.Format(layout), nil

	default:
		return nil, errs.Wrapf(zbxerr.ErrorInvalidParams, "invalid parameter '%s'", requestType)
	}
}
