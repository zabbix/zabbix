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
package container

import "zabbix.com/pkg/plugin"

type emptyCtx struct {
	resultWriter emptyResultWriter
	matcher      emptyMatcher
}

func (ctx emptyCtx) ClientID() uint64 {
	return 0
}
func (ctx emptyCtx) ItemID() uint64 {
	return 0
}
func (ctx emptyCtx) Output() plugin.ResultWriter {
	return ctx.resultWriter
}
func (ctx emptyCtx) Meta() *plugin.Meta {
	return nil
}
func (ctx emptyCtx) GlobalRegexp() plugin.RegexpMatcher {
	return ctx.matcher
}

type emptyMatcher struct{}

func (em emptyMatcher) Match(value string, pattern string, mode int, output_template *string) (bool, string) {
	return false, ""
}

type emptyResultWriter struct{}

func (rw emptyResultWriter) Write(result *plugin.Result) {}
func (rw emptyResultWriter) Flush()                      {}
func (rw emptyResultWriter) SlotsAvailable() int         { return 0 }
func (rw emptyResultWriter) PersistSlotsAvailable() int  { return 0 }
