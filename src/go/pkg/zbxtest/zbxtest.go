/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

package zbxtest

import (
	"git.zabbix.com/ap/plugin-support/plugin"
)

type MockEmptyCtx struct {
}

func (ctx MockEmptyCtx) ClientID() uint64 {
	return 0
}

func (ctx MockEmptyCtx) ItemID() uint64 {
	return 0
}

func (ctx MockEmptyCtx) Output() plugin.ResultWriter {
	return nil
}

func (ctx MockEmptyCtx) Meta() *plugin.Meta {
	return nil
}

func (ctx MockEmptyCtx) GlobalRegexp() plugin.RegexpMatcher {
	return nil
}

func (ctx MockEmptyCtx) Timeout() int {
	const defaultTimeout = 3

	return defaultTimeout
}
