/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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

package zbxcmd

import (
	"time"

	"golang.zabbix.com/sdk/errs"
)

var (
	_ Executor = (*ZBXExecMock)(nil)
)

// ZBXExecMock mock for ZBX command execution.
type ZBXExecMock struct {
	Success bool
}

// Execute mock function.
func (e *ZBXExecMock) Execute(string, time.Duration, string) (string, error) {
	if !e.Success {
		return "", errs.New("fail")
	}

	return "success", nil
}

// ExecuteStrict mock function.
func (e *ZBXExecMock) ExecuteStrict(string, time.Duration, string) (string, error) {
	if !e.Success {
		return "", errs.New("fail")
	}

	return "success", nil
}

// ExecuteBackground mock function.
func (e *ZBXExecMock) ExecuteBackground(string) error {
	if !e.Success {
		return errs.New("fail")
	}

	return nil
}
