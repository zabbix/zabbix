/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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
	"fmt"
	"os/exec"
	"runtime"
	"strings"
	"time"

	"zabbix.com/pkg/log"
	"zabbix.com/pkg/zbxerr"
)

// SmartCtl is a smartctl command runner.
type smartCtl struct {
	commandPath string
	timeout     time.Duration
}

// newSmartCtl creates a new SmartCtl instance.
func newSmartCtl(path string, timeoutSecs int) *smartCtl {
	if path == "" {
		path = "smartctl"
	}

	return &smartCtl{
		commandPath: path,
		timeout:     time.Second * time.Duration(timeoutSecs),
	}
}

// execute executes the smartctl command with the specified arguments as root.
// Does not return error on non-zero exit code. This is done because smartctl
// returns non-zero exit codes even when command executed successfully, in cases
// like when a disc is failing.
// https://linux.die.net/man/8/smartctl
func (s *smartCtl) execute(args ...string) ([]byte, error) {
	_, err := exec.LookPath(s.commandPath)
	if err != nil {
		//return nil, zbxerr.Wrap(err, "failed to look up smartctl exec path")
		return nil, zbxerr.New("failed to look up smartctl exec path").Wrap(err)
	}

	cmd := "sudo"

	cmdArgs := append([]string{"-n", s.commandPath}, args...)

	if runtime.GOOS == "windows" {
		cmd = s.commandPath
		cmdArgs = args
	}

	ctx, cancel := context.WithTimeout(context.Background(), s.timeout)
	defer cancel()

	log.Tracef(
		"executing smartctl command: %s %s", cmd, strings.Join(cmdArgs, " "),
	)

	//nolint:gosec
	out, err := exec.CommandContext(ctx, cmd, cmdArgs...).CombinedOutput()
	if err != nil {
		exitErr := &exec.ExitError{}
		if errors.As(err, &exitErr) {
			//return nil, errs.Wrapf(err, "%q", strings.TrimSuffix(string(out), "\n"))
			return nil, zbxerr.New(fmt.Sprintf("%q", strings.TrimSuffix(string(out), "\n"))).Wrap(err)
		}

		return nil, zbxerr.New("failed to get combined output of stdout and stderr for smartctl process").Wrap(err)
	}

	log.Debugf(
		"executed smartctl command: %s %s Got output: %q", cmd, strings.Join(cmdArgs, " "), out,
	)

	return out, nil
}
