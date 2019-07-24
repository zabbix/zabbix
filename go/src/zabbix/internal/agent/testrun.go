/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
)

func CheckMetric(metric string) (err error) {
	var key string
	var params []string

	defer func() {
		if err != nil {
			fmt.Printf("%-46s[m|ZBX_NOTSUPPORTED] [%s]\n", key, err.Error())
		}
	}()

	if key, params, err = itemutil.ParseKey(metric); err != nil {
		return
	}

	var p *plugin.Plugin
	if p, err = plugin.Get(key); err != nil {
		return
	}

	var exporter plugin.Exporter
	var ok bool
	if exporter, ok = p.Impl.(plugin.Exporter); !ok {
		return errors.New("not an exporter plugin")
	}

	var v interface{}
	if v, err = exporter.Export(key, params); err != nil {
		return
	}

	switch v.(type) {
	case string:
		fmt.Printf("%-46s[s|%s]\n", key, v.(string))
	case *string:
		fmt.Printf("%-46s[s|%s]\n", key, *v.(*string))
	case int, int8, int16, int32, int64:
		fmt.Printf("%-46s[i|%v]\n", key, v)
	case uint, uint8, uint16, uint32, uint64:
		fmt.Printf("%-46s[u|%v]\n", key, v)
	case float32, float64:
		fmt.Printf("%-46s[f|%v]\n", key, v)
	}

	return nil

}

func CheckMetrics() {
	metrics := []string{
		"agent.hostname",
		"system.uptime",
	}

	for _, metric := range metrics {
		_ = CheckMetric(metric)
	}
}
