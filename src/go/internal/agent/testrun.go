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

package agent

import (
	"errors"
	"fmt"

	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/plugin"
)

func CheckMetric(metric string) (err error) {
	var key string
	var params []string

	defer func() {
		if err != nil {
			fmt.Printf("%-46s[m|ZBX_NOTSUPPORTED] [%s]\n", metric, err.Error())
		}
	}()

	if key, params, err = itemutil.ParseKey(metric); err != nil {
		return
	}

	var acc plugin.Accessor
	if acc, err = plugin.Get(key); err != nil {
		return
	}

	var exporter plugin.Exporter
	var ok bool
	if exporter, ok = acc.(plugin.Exporter); !ok {
		return errors.New("not an exporter plugin")
	}

	var conf plugin.Configurator
	if conf, ok = acc.(plugin.Configurator); ok {
		conf.Configure(GlobalOptions(&Options), Options.Plugins[acc.Name()])
	}

	var u interface{}
	if u, err = exporter.Export(key, params, nil); err != nil {
		return
	}

	switch v := u.(type) {
	case string:
		fmt.Printf("%-46s[s|%s]\n", metric, v)
	case *string:
		fmt.Printf("%-46s[s|%s]\n", metric, *v)
	case int, int8, int16, int32, int64:
		fmt.Printf("%-46s[i|%v]\n", metric, v)
	case uint, uint8, uint16, uint32, uint64:
		fmt.Printf("%-46s[u|%v]\n", metric, v)
	case float32, float64:
		fmt.Printf("%-46s[f|%v]\n", metric, v)
	default:
		fmt.Printf("%-46s[?|%+v]\n", metric, v)
	}

	return nil

}
