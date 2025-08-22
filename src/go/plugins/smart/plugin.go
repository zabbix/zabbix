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

package smart

import (
	"regexp"
	"runtime"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	// Name hold the plugin name.
	Name = "Smart"

	attributeDiscoveryMetric = metricKey("smart.attribute.discovery")
	diskDiscoveryMetric      = metricKey("smart.disk.discovery")
	diskGetMetric            = metricKey("smart.disk.get")
)

var (
	_ plugin.Configurator = (*Plugin)(nil)
	_ plugin.Exporter     = (*Plugin)(nil)
)

//nolint:gochecknoglobals
var impl Plugin

var pathRegex = regexp.MustCompile(`^(?:\s*-|'*"*\s*-)`)

type metricKey string

type smartMetric struct {
	metric  *metric.Metric
	handler handlerFunc
}

// Plugin hold plugin data.
type Plugin struct {
	plugin.Base
	options  Options
	ctl      SmartController
	cpuCount int
	metrics  map[metricKey]*smartMetric
}

//nolint:gochecknoinits
func init() {
	impl.metrics = map[metricKey]*smartMetric{
		attributeDiscoveryMetric: {
			metric.New(
				"Returns JSON array of smart devices.",
				[]*metric.Param{},
				false,
			),
			impl.attributeDiscovery,
		},
		diskDiscoveryMetric: {
			metric.New(
				"Returns JSON array of smart devices.",
				[]*metric.Param{searchType},
				false,
			),
			impl.diskDiscovery,
		},
		diskGetMetric: {
			metric.New(
				"Returns JSON data of smart device.",
				[]*metric.Param{path, raidType},
				false,
			),
			impl.diskGet,
		},
	}

	metricSet := metric.MetricSet{}

	for k, m := range impl.metrics {
		metricSet[string(k)] = m.metric
	}

	err := plugin.RegisterMetrics(&impl, Name, metricSet.List()...)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	cpuCount := runtime.NumCPU()
	if cpuCount < 1 {
		cpuCount = 1
	}

	impl.cpuCount = cpuCount
}

// Export is the main plugin call function that handles all items.
func (p *Plugin) Export(key string, rawParams []string, _ plugin.ContextProvider) (any, error) {
	err := p.validateExport(rawParams)
	if err != nil {
		return nil, errs.Wrap(err, "export validation failed")
	}

	m, ok := p.metrics[metricKey(key)]
	if !ok {
		return nil, zbxerr.ErrorUnsupportedMetric
	}

	metricParams, _, _, err := m.metric.EvalParams(rawParams, map[string]string{})
	if err != nil {
		return nil, errs.Wrap(err, "failed to evaluate metric parameters")
	}

	out, err := m.handler(metricParams)
	if err != nil {
		//nolint:wrapcheck // it is wrapped but with a constant
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	return string(out), nil
}

// validateExport function validates key, export params and version.
func (p *Plugin) validateExport(params []string) error {
	err := validateParams(params)
	if err != nil {
		return err
	}

	return p.checkVersion()
}

// validateParams validates the key's params quantity aspect.
func validateParams(params []string) error {
	// No params - nothing to validate.
	if len(params) == 0 {
		return nil
	}

	// Validates the param disk path in the context of an input sanitization.
	if pathRegex.MatchString(params[0]) {
		return errs.New("invalid disk descriptor format")
	}

	return nil
}
