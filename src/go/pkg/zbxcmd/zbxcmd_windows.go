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
	"golang.zabbix.com/sdk/errs"
)

const cmd = "cmd.exe"

// ZBXExec holds wrapper for os.exec.
type ZBXExec struct {
	shellPath string
}

func InitExecutor() (Executor, error) {
	cmdPath, err := exec.LookPath(cmd)
	if err != nil && !errors.Is(err, exec.ErrDot) {
		return nil, fmt.Errorf("cannot find path to %s command: %s", cmdPath, err)
	}

	cmdFullPath, err := filepath.Abs(cmdPath)
	if err != nil {
		return nil, fmt.Errorf("cannot find full path to %s command: %s", cmdFullPath, err)
	}

	return &ZBXExec{shellPath: cmdFullPath}, nil
}

func (e *ZBXExec) execute(command string, timeout time.Duration, execDir string, strict bool) (string, error) {
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	defer cancel()

	var b bytes.Buffer

	cmd := exec.CommandContext(ctx, e.shellPath)
	cmd.Dir = execDir
	cmd.Stdout = &b
	cmd.Stderr = &b
	cmd.SysProcAttr = &windows.SysProcAttr{
		CmdLine: fmt.Sprintf(`/C "%s"`, command),
	}

	err := cmd.Start()
	if err != nil {
		return "", fmt.Errorf("failed to start command (%s, path: %s): %s", command, execDir, err)
	}

	werr := cmd.Wait()

	// we need to check context error so we can inform the user if timeout was reached and Zabbix agent2
	// terminated the command
	if errors.Is(ctx.Err(), context.DeadlineExceeded) {
		return "", fmt.Errorf("command execution failed: %s", ctx.Err())
	}

	if strict && werr != nil {
		return "", fmt.Errorf("command execution failed: %s", werr.Error())
	}

	if MaxExecuteOutputLenB <= len(b.String()) {
		return "", fmt.Errorf("command output exceeded limit of %d KB", MaxExecuteOutputLenB/1024)
	}

	return strings.TrimRight(b.String(), " \t\r\n"), nil
}

func (e *ZBXExec) executeBackground(s string) (err error) {
	cmd := exec.Command(e.shellPath)
	cmd.SysProcAttr = &windows.SysProcAttr{
		CmdLine: fmt.Sprintf(`/C "%s"`, s),
	}

	if err = cmd.Start(); err != nil {
		return errs.Wrapf(err, "cannot execute command (%s)", s)
	}

	go cmd.Wait()

	return nil
}
