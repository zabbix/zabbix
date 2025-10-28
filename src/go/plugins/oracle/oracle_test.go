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
	"errors"
	"reflect"
	"runtime"
	"strings"
	"testing"

	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

//nolint:paralleltest
func TestHandlersExcessiveParams(t *testing.T) {
	type test struct {
		name   string
		metric string
	}

	var tests []test //nolint:prealloc

	// Generate test slice.
	for metric := range plugin.Metrics {
		if metric == keyCustomQuery || metric == keyTablespaces {
			continue
		}

		tests = append(tests, test{"-" + metric, metric})
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			_, err := impl.Export(
				tt.metric,
				[]string{
					"config.OraIP", "config.OraUser", "config.OraPwd", "config.OraSrv",
					"excess_param", "excess_param", "excess_param",
				},
				nil)
			if !errors.Is(err, zbxerr.ErrorTooManyParameters) {
				t.Errorf("Plugin.%s() should fail if too many parameters passed", getHandlerName(t, tt.metric))
			}
		})
	}
}

func getHandlerName(t *testing.T, metric string) string {
	t.Helper()

	function := runtime.FuncForPC(reflect.ValueOf(metricsMeta[metric]).Pointer()).Name()
	parts := strings.Split(function, ".")

	return parts[len(parts)-1]
}
