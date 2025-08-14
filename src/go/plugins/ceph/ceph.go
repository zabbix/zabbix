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

package ceph

import (
	"context"

	"golang.zabbix.com/agent2/plugins/ceph/handlers"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
)

const pluginName = "Ceph"
const modeParamName = "Mode"

const (
	restful = "restful"
	native  = "native"
)

var _ plugin.Runner = (*Plugin)(nil)
var _ plugin.Exporter = (*Plugin)(nil)

// impl is the pointer to the plugin implementation.
var impl Plugin //nolint:gochecknoglobals // this is flagship (legacy) implementation

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, rawParams []string, _ plugin.ContextProvider) (any, error) {
	params, _, hc, err := metrics[key].EvalParams(rawParams, p.options.Sessions)
	if err != nil {
		return nil, errs.Wrap(err, "failed to eval params")
	}

	err = metric.SetDefaults(params, hc, p.options.Default)
	if err != nil {
		return nil, errs.Wrap(err, "failed to set metric defaults")
	}

	u, err := uri.NewWithCreds(params["URI"], params["User"], params["APIKey"], uriDefaults)
	if err != nil {
		return nil, errs.Wrap(err, "failed to create URI")
	}

	handlerKey := handlers.Key(key)

	meta := handlers.GetMetricMeta(handlerKey)
	if meta == nil {
		return nil, errs.New("no metric found for key " + key)
	}

	responses := make(map[handlers.Command][]byte)

	var resCh <-chan *response

	switch params[modeParamName] {
	case restful:
		ctx, cancel := context.WithCancel(context.Background())
		defer cancel()

		resCh = p.asyncRestfulRequest(ctx, cancel, u, meta)
	case native:
		// handle this differently due to OS differences.
		resCh, err = p.handleNativeMode(u, meta)
		if err != nil {
			return nil, err
		}

	default:
		return nil, errs.Errorf("unknown mode: %s", params[modeParamName])
	}

	for range meta.Commands {
		r := <-resCh
		if r.err != nil {
			err = r.err

			break
		}

		responses[r.command] = r.data
	}

	if err != nil {
		// Special logic of processing connection errors is used if keyPing is requested
		// because it must return pingFailed if any error occurred.
		if handlerKey == handlers.KeyPing {
			return handlers.PingFailed, nil
		}

		return nil, err
	}

	result, err := meta.Handle(responses)
	if err != nil {
		p.Errf(err.Error())

		return nil, errs.Wrap(err, "failed to execute command")
	}

	return result, nil
}
