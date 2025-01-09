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

package zbxtest

import (
	"golang.zabbix.com/sdk/plugin"
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

func (ctx MockEmptyCtx) Delay() string {
	return ""
}
