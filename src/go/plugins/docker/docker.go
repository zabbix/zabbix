/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package docker

import (
	"fmt"
	"net/http"
	"regexp"
	"strconv"
	"strings"

	"golang.zabbix.com/agent2/plugins/docker/handlers"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	pluginName = "Docker"
)

var (
	// name that starts with alphanumeric and continues with alphanumeric, dot or underscore.
	containerNameRegex = regexp.MustCompile(`^[a-zA-Z0-9][a-zA-Z0-9_.-]*$`)
)

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base

	options Options
	client  *http.Client
}

//nolint:gochecknoinits // this is plugin only one init function.
func init() {
	impl := &Plugin{}

	err := plugin.RegisterMetrics(impl, pluginName, metrics.List()...)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Export implements the plugin.Exporter interface.
//
//nolint:gocyclo,cyclop // this is export function, it can be with high cyclo sometimes.
func (p *Plugin) Export(key string, rawParams []string, _ plugin.ContextProvider) (any, error) {
	params, _, _, err := metrics[key].EvalParams(rawParams, nil)
	if err != nil {
		return nil, errs.Wrap(err, "failed to evaluate params")
	}

	dockerKey := handlers.DockerKey(key)

	handler := handlers.GetDockerHandler(dockerKey)
	if handler == nil {
		return nil, zbxerr.ErrorUnsupportedMetric
	}

	var (
		queryPath = metricsMeta[dockerKey].path
		query     string
	)

	switch dockerKey {
	case handlers.KeyInfo,
		handlers.KeyContainers,
		handlers.KeyImages,
		handlers.KeyImagesDiscovery,
		handlers.KeyDataUsage,
		handlers.KeyPing:
		query = queryPath

		return handler(p.client, query)
	case handlers.KeyContainersDiscovery:
		_, err = strconv.ParseBool(params["All"])
		if err != nil {
			return nil, errs.New("invalid value for second argument")
		}

		query = fmt.Sprintf(queryPath, params["All"])

		return handler(p.client, query)
	case handlers.KeyContainerInfo:
		container := params["Container"]

		// Strip leading slash if present to maintain backwards compatibility with older template discovery.
		container = strings.TrimPrefix(container, "/")

		if !containerNameRegex.MatchString(container) {
			return nil, errs.New("invalid container identifier")
		}

		query = fmt.Sprintf(queryPath, container)

		info := params["Info"]
		if info != "full" && info != "short" {
			return nil, errs.New("info must be either 'full' or 'short'")
		}

		return handler(p.client, query, info)
	case handlers.KeyContainerStats:
		container := params["Container"]

		// Strip leading slash if present to maintain backwards compatibility with older template discovery.
		container = strings.TrimPrefix(container, "/")

		if !containerNameRegex.MatchString(container) {
			return nil, errs.New("invalid container identifier")
		}

		query = fmt.Sprintf(queryPath, container)

		return handler(p.client, query)
	default:
		return nil, zbxerr.ErrorUnsupportedMetric
	}
}
