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

package smart

import (
	"fmt"
	"os/exec"
	"runtime"
	"strings"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
	"zabbix.com/pkg/zbxcmd"
)

var _ SmartController = (*SmartCtl)(nil)

// SmartController describes the signature for a smartctl runner.
type SmartController interface {
	Execute(args ...string) ([]byte, error)
}

// SmartCtl is a smartctl command runner for nix systems.
// Implements the SmartController interface.
type SmartCtl struct {
	commandPath string
	logr        log.Logger
	timeout     time.Duration
}

// NewSmartCtl creates a new SmartCtl instance.
func NewSmartCtl(logr log.Logger, path string, timeoutSecs int) *SmartCtl {
	if path == "" {
		path = "smartctl"
	}

	return &SmartCtl{
		commandPath: path,
		logr:        logr,
		timeout:     time.Second * time.Duration(timeoutSecs),
	}
}

// Execute executes the smartctl command with the specified arguments as root.
// Does not return error on non-zero exit code. This is done because smartctl
// returns non-zero exit codes even when command executed successfully, in cases
// like when a disc is failing.
// https://linux.die.net/man/8/smartctl
func (s *SmartCtl) Execute(args ...string) ([]byte, error) {
	_, err := exec.LookPath(s.commandPath)
	if err != nil {
		return nil, err
	}

	executable := fmt.Sprintf(
		"sudo -n %s %s", s.commandPath, strings.Join(args, " "),
	)

	if runtime.GOOS == "windows" {
		executable = fmt.Sprintf(
			"%s %s", s.commandPath, strings.Join(args, " "),
		)
	}

	s.logr.Tracef("executing smartctl command: %s", executable)

	out, err := zbxcmd.Execute(executable, s.timeout, "")
	if err != nil {
		return nil, err
	}

	s.logr.Tracef("command %s smartctl raw response: %s", executable, out)

	return []byte(out), nil
}
