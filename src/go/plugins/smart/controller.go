/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
	"context"
	"errors"
	"os/exec"
	"runtime"
	"strings"
	"time"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
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
		return nil, errs.Wrap(err, "failed to look up smartctl exec path")
	}

	cmd := "sudo"
	cmdArgs := append([]string{"-n", s.commandPath}, args...)

	if runtime.GOOS == "windows" {
		cmd = s.commandPath
		cmdArgs = args
	}

	ctx, cancel := context.WithTimeout(context.Background(), s.timeout)
	defer cancel()

	s.logr.Tracef(
		"executing smartctl command: %s %s", cmd, strings.Join(cmdArgs, " "),
	)

	out, err := exec.CommandContext(ctx, cmd, cmdArgs...).CombinedOutput()
	if err != nil {
		exitErr := &exec.ExitError{}
		if errors.As(err, &exitErr) {
			return out, nil
		}

		return nil, errs.Wrap(
			err,
			"failed to get combined output of stdout and stderr for smartctl process",
		)
	}

	return out, nil
}
