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

package zbxcmd

import "time"

// MaxExecuteOutputLenB maximum output length for Execute and ExecuteStrict in bytes.
const MaxExecuteOutputLenB = 16 * 1024 * 1024

var (
	_ Executor = (*ZBXExec)(nil)
)

// Executor interface required for executing commands.
type Executor interface {
	Execute(command string, timeout time.Duration, execDir string) (string, error)
	ExecuteStrict(command string, timeout time.Duration, execDir string) (string, error)
	ExecuteBackground(command string) error
}

// Execute runs the 's' command without checking cmd.Wait error.
// This means that non zero exit status code will not return an error.
// Returns an error if there is an issue with executing the command or
// if the specified timeout has been reached or if maximum output length
// has been reached.
func (e *ZBXExec) Execute(command string, timeout time.Duration, execDir string) (string, error) {
	return e.execute(command, timeout, execDir, false)
}

// ExecuteStrict runs the 's' command and checks cmd.Wait error.
// This means that non zero exit status code will return an error.
// Also returns an error if there is an issue with executing the command or
// if the specified timeout has been reached or if maximum output length
// has been reached.
func (e *ZBXExec) ExecuteStrict(command string, timeout time.Duration, path string) (string, error) {
	return e.execute(command, timeout, path, true)
}

// ExecuteBackground runs the 's' command and waits for cmd.Wait in a go routine
// This does not check cmd.Wait error.
func (e *ZBXExec) ExecuteBackground(command string) error {
	return e.executeBackground(command)
}
