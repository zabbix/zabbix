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

package log

import (
	"fmt"
	"runtime"
	"time"
	"unsafe"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/pkg/glexpr"
	"golang.zabbix.com/agent2/pkg/itemutil"
	"golang.zabbix.com/agent2/pkg/zbxlib"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

type Options struct {
	plugin.SystemOptions `conf:"optional"`
	MaxLinesPerSecond    int `conf:"range=1:1000,default=20"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

type metadata struct {
	key    string
	params []string
	blob   unsafe.Pointer
}

func init() {
	err := plugin.RegisterMetrics(
		&impl, "Log",
		"log", "Log file monitoring.",
		"logrt", "Log file monitoring with log rotation support.",
		"log.count", "Count of matched lines in log file monitoring.",
		"logrt.count", "Count of matched lines in log file monitoring with log rotation support.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	impl.SetHandleTimeout(true)
}

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}

	zbxlib.SetMaxLinesPerSecond(p.options.MaxLinesPerSecond)
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if ctx == nil || ctx.ClientID() <= agent.MaxBuiltinClientID {
		return nil, fmt.Errorf(`The "%s" key is not supported in test or single passive check mode`, key)
	}
	meta := ctx.Meta()
	var data *metadata
	if meta.Data == nil {
		data = &metadata{key: key, params: params}
		runtime.SetFinalizer(data, func(d *metadata) { zbxlib.FreeActiveMetric(d.blob) })

		data.blob, err = zbxlib.NewActiveMetric(ctx.ItemID(), key, params, meta.LastLogsize(), meta.Mtime())
		if err != nil {
			return nil, err
		}
		meta.Data = data
	} else {
		data = meta.Data.(*metadata)
		if !itemutil.CompareKeysParams(key, params, data.key, data.params) {
			zbxlib.FreeActiveMetric(data.blob)
			data.key = key
			data.params = params
			// recreate if item key has been changed
			data.blob, err = zbxlib.NewActiveMetric(ctx.ItemID(), key, params, meta.LastLogsize(), meta.Mtime())
			if err != nil {
				return nil, err
			}
		}
	}

	if ctx.Output().PersistSlotsAvailable() == 0 {
		p.Warningf("buffer is full, cannot store persistent value")
		return nil, nil
	}

	// with flexible checks there are no guaranteed refresh time,
	// so using number of seconds elapsed since last check
	now := time.Now()
	nextcheck := zbxlib.GetNextcheckSeconds(ctx.ItemID(), ctx.Delay(), now)
	logitem := zbxlib.LogItem{Results: make([]*zbxlib.LogResult, 0), Output: ctx.Output()}
	grxp := ctx.GlobalRegexp().(*glexpr.Bundle)
	zbxlib.ProcessLogCheck(data.blob, &logitem, nextcheck, grxp.Cblob, ctx.ItemID())

	if len(logitem.Results) != 0 {
		results := make([]plugin.Result, len(logitem.Results))
		for i, r := range logitem.Results {
			results[i].Itemid = ctx.ItemID()
			results[i].Value = r.Value
			results[i].Error = r.Error
			results[i].Ts = r.Ts
			results[i].LastLogsize = &r.LastLogsize
			results[i].Mtime = &r.Mtime
			results[i].Persistent = true
		}
		return results, nil
	}
	return nil, nil
}
