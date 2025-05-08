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
	"fmt"
	"os/exec"
	"path/filepath"
	"strings"
	"time"

	"golang.org/x/sys/windows"
)

const cmd = "cmd.exe"

var (
	cmd_path string
)

func InitExecutor(s string, timeout time.Duration, path string, strict bool) (*Executor, error) {
	cmdPath, err := exec.LookPath(cmd)
	if err != nil && !errors.Is(err, exec.ErrDot) {
		return nil, fmt.Errorf("cannot find path to %s command: %s", cmdPath, err)
	}

	cmdFullPath, err := filepath.Abs(cmdPath)
	if err != nil {
		return nil, fmt.Errorf("cannot find full path to %s command: %s", cmdFullPath, err)
	}

	return &Executor{
		shellPath: cmdFullPath,
		command:   s,
		execDir:   path,
		strict:    strict,
		timeout:   timeout,
	}, nil
}

func (e *Executor) execute() (string, error) {
	ctx, cancel := context.WithTimeout(context.Background(), e.timeout)
	defer cancel()

	var b bytes.Buffer

	cmd := exec.CommandContext(ctx, e.shellPath)
	cmd.Dir = e.execDir
	cmd.Stdout = &b
	cmd.Stderr = &b
	cmd.SysProcAttr = &windows.SysProcAttr{
		CmdLine: fmt.Sprintf(`/C "%s"`, e.command),
	}

	err := cmd.Start()
	if err != nil {
		return "", fmt.Errorf("failed to start command (%s, path: %s): %s", e.command, e.execDir, err)
	}

	werr := cmd.Wait()

	// we need to check context error so we can inform the user if timeout was reached and Zabbix agent2
	// terminated the command
	if errors.Is(ctx.Err(), context.DeadlineExceeded) {
		return "", fmt.Errorf("command execution failed: %s", ctx.Err())
	}

	if e.strict && werr != nil {
		return "", fmt.Errorf("command execution failed: %s", werr.Error())
	}

	if MaxExecuteOutputLenB <= len(b.String()) {
		return "", fmt.Errorf("command output exceeded limit of %d KB", MaxExecuteOutputLenB/1024)
	}

	return strings.TrimRight(b.String(), " \t\r\n"), nil
}

func ExecuteBackground(s string) (err error) {
	if cmd_path == "" {
		cmd_exe := "cmd.exe"
		if cmd_exe, err = exec.LookPath(cmd_exe); err != nil && !errors.Is(err, exec.ErrDot) {
			return fmt.Errorf("Cannot find path to %s command: %s", cmd_exe, err)
		}
		if cmd_path, err = filepath.Abs(cmd_exe); err != nil {
			return fmt.Errorf("Cannot find full path to %s command: %s", cmd_exe, err)
		}
	}
	cmd := exec.Command(cmd_path)
	cmd.SysProcAttr = &windows.SysProcAttr{
		CmdLine: fmt.Sprintf(`/C "%s"`, s),
	}

	if err = cmd.Start(); err != nil {
		return fmt.Errorf("Cannot execute command (%s): %s", s, err)
	}

	go func() { _ = cmd.Wait() }()

	return nil
}
