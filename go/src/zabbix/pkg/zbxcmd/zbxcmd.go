/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	"context"
	"errors"
	"fmt"
	"os/exec"
	"strings"
	"time"
	"zabbix/pkg/log"
)

const maxExecuteOutputLenB = 512 * 1024

func Run(s string, timeout time.Duration) (string, error) {
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	defer cancel()

	cmd := exec.CommandContext(ctx, "sh", "-c", s)

	stdoutStderr, err := cmd.CombinedOutput()

	if err != nil {
		if ctx.Err() == context.DeadlineExceeded {
			return "", fmt.Errorf("Timeout while executing a shell script.")
		}

		if len(stdoutStderr) == 0 {
			return "", err
		}

		return "", errors.New(string(stdoutStderr))
	}

	if maxExecuteOutputLenB <= len(stdoutStderr) {
		return "", fmt.Errorf("Command output exceeded limit of %d KB", maxExecuteOutputLenB/1024)
	}

	return strings.TrimRight(string(stdoutStderr), " \t\r\n"), nil
}

func wait(cmd *exec.Cmd) {
	err := cmd.Wait()

	if err != nil {
		log.Debugf("Command %s finished with error: %s", cmd.Args, err)
	}
}

func Start(s string) error {
	cmd := exec.Command("sh", "-c", s)
	err := cmd.Start()

	if err != nil {
		return fmt.Errorf("Cannot execute command: %s", err)
	}

	go wait(cmd)

	return nil
}
