/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package plugin

import (
	"fmt"
	"reflect"
	"regexp"
	"unicode"
)

type Metric struct {
	Plugin      Accessor
	Key         string
	Description string
	UsrPrm      bool
}

var Metrics map[string]*Metric = make(map[string]*Metric)
var Plugins map[string]Accessor = make(map[string]Accessor)

func registerMetric(plugin Accessor, name string, key string, description string) {
	var usrprm bool

	if ok, _ := regexp.MatchString(`^[A-Za-z0-9\._-]+$`, key); !ok {
		panic(fmt.Sprintf(`cannot register metric "%s" having invalid format`, key))
	}

	if 0 == len(description) {
		panic(fmt.Sprintf(`cannot register metric "%s" with empty description`, key))
	}

	if unicode.IsLower([]rune(description)[0]) {
		panic(fmt.Sprintf(`cannot register metric "%s" with description without capital first letter: "%s"`, key, description))
	}

	if description[len(description)-1] != '.' {
		panic(fmt.Sprintf(`cannot register metric "%s" without dot at the end of description: "%s"`, key, description))
	}

	if _, ok := Metrics[key]; ok {
		panic(fmt.Sprintf(`cannot register duplicate metric "%s"`, key))
	}

	t := reflect.TypeOf(plugin)
	for i := 0; i < t.NumMethod(); i++ {
		method := t.Method(i)
		switch method.Name {
		case "Export":
			if _, ok := plugin.(Exporter); !ok {
				panic(fmt.Sprintf(`the "%s" plugin has %s method, but does implement Exporter interface`, name, method.Name))
			}
		case "Collect", "Period":
			if _, ok := plugin.(Collector); !ok {
				panic(fmt.Sprintf(`the "%s" plugin has %s method, but does not implement Collector interface`, name, method.Name))
			}
		case "Watch":
			if _, ok := plugin.(Watcher); !ok {
				panic(fmt.Sprintf(`the "%s" plugin has %s method, but does not implement Watcher interface`, name, method.Name))
			}
		case "Configure", "Validate":
			if _, ok := plugin.(Configurator); !ok {
				panic(fmt.Sprintf(`the "%s" plugin has %s method, but does not implement Configurator interface`, name, method.Name))
			}
		case "Start", "Stop":
			if _, ok := plugin.(Runner); !ok {
				panic(fmt.Sprintf(`the "%s" plugin has %s method, but does not implement Runner interface`, name, method.Name))
			}
		}
	}
	switch plugin.(type) {
	case Exporter, Collector, Runner, Watcher, Configurator:
	default:
		panic(fmt.Sprintf(`plugin "%s" does not implement any plugin interfaces`, name))
	}

	if p, ok := Plugins[name]; ok {
		if p != plugin {
			panic(fmt.Sprintf(`plugin name "%s" has been already registered by other plugin`, name))
		}
	} else {
		Plugins[name] = plugin
		plugin.Init(name)
	}

	if name == "UserParameter" {
		usrprm = true
	} else {
		usrprm = false
	}

	Metrics[key] = &Metric{Plugin: plugin, Key: key, Description: description, UsrPrm: usrprm}
}

func RegisterMetrics(impl Accessor, name string, params ...string) {
	if len(params) < 2 {
		panic("expected at least one metric and its description")
	}
	if len(params)&1 != 0 {
		panic("expected even number of metric and description parameters")
	}
	for i := 0; i < len(params); i += 2 {
		registerMetric(impl, name, params[i], params[i+1])
	}
}

func Get(key string) (acc Accessor, err error) {
	if m, ok := Metrics[key]; ok {
		return m.Plugin, nil
	}
	return nil, UnsupportedMetricError
}

func ClearRegistry() {
	Metrics = make(map[string]*Metric)
	Plugins = make(map[string]Accessor)
}

func GetByName(name string) (acc Accessor, err error) {
	if p, ok := Plugins[name]; ok {
		return p, nil
	}
	return nil, UnsupportedMetricError
}

func ClearUserParamMetrics() (metricsFallback map[string]*Metric) {
	metricsFallback = make(map[string]*Metric)

	for key, metric := range Metrics {
		if metric.UsrPrm {
			metricsFallback[key] = metric
			delete(Metrics, key)
		}
	}

	return
}

func RestoreUserParamMetrics(metrics map[string]*Metric) {
	for key, metric := range metrics {
		Metrics[key] = metric
	}
}
