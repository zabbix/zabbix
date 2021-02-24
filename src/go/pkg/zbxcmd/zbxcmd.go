/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

import "time"

const maxExecuteOutputLenB = 512 * 1024

// Execute runs the 's' command without checking cmd.Wait error.
// This means that non zero exit status code will not return an error.
// Returns an error if there is an issue with executing the command or
// if the specified timeout has been reached or if maximum output length
// has been reached.
func Execute(s string, timeout time.Duration) (string, error) {
	return execute(s, timeout, false)
}

// ExecuteStrict runs the 's' command and checks cmd.Wait error.
// This means that non zero exit status code will return an error.
// Also returns an error if there is an issue with executing the command or
// if the specified timeout has been reached or if maximum output length
// has been reached.
func ExecuteStrict(s string, timeout time.Duration) (string, error) {
	return execute(s, timeout, true)
}
