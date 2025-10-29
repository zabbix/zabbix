//go:build !windows

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

import (
	"bytes"
	"context"
	"errors"
	"os/exec"
	"strings"
	"syscall"
	"time"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
)

// ZBXExec holds wrapper for os.exec.
//
//nolint:ireturn
type ZBXExec struct {
}

// InitExecutor initialized empty ZBXExec will allow support of different shells in the future.
//
//nolint:ireturn
func InitExecutor() (Executor, error) {
	return &ZBXExec{}, nil
}

func (*ZBXExec) execute(s string, timeout time.Duration, path string, strict bool) (string, error) {
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	defer cancel()

	cmd := exec.CommandContext(ctx, "sh", "-c", s)
	cmd.Dir = path

	var b bytes.Buffer
	cmd.Stdout = &b
	cmd.Stderr = &b

	cmd.SysProcAttr = &syscall.SysProcAttr{Setpgid: true}

	err := cmd.Start()
	if err != nil {
		return "", errs.Errorf("cannot execute command: %s", err)
	}

	werr := cmd.Wait()

	// we need to check context error so we can inform the user if timeout was reached and Zabbix agent2
	// terminated the command
	if errors.Is(ctx.Err(), context.DeadlineExceeded) {
		return "", errs.Errorf("command execution failed: %s", ctx.Err())
	}

	// we need to check error after t.Stop so we can inform the user if timeout was reached and Zabbix agent2 terminated the command
	if strict && werr != nil && !errors.Is(werr, syscall.ECHILD) {
		log.Debugf("Command [%s] execution failed: %s\n%s", s, werr, b.String())

		return "", errs.Errorf("command execution failed: %s", werr.Error())
	}

	if MaxExecuteOutputLenB <= len(b.String()) {
		return "", errs.Errorf("command output exceeded limit of %d KB", MaxExecuteOutputLenB/1024)
	}

	return strings.TrimRight(b.String(), " \t\r\n"), nil
}

func (*ZBXExec) executeBackground(s string) error {
	cmd := exec.Command("sh", "-c", s)

	err := cmd.Start()
	if err != nil {
		return errs.Wrap(err, "cannot execute command")
	}

	go cmd.Wait()

	return nil
}
