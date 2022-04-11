//go:build !windows

/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	"bytes"
	"errors"
	"fmt"
	"os/exec"
	"strings"
	"syscall"
	"time"

	"zabbix.com/pkg/log"
)

func execute(s string, timeout time.Duration, path string, strict bool) (string, error) {
	cmd := exec.Command("sh", "-c", s)
	cmd.Dir = path

	var b bytes.Buffer
	cmd.Stdout = &b
	cmd.Stderr = &b

	cmd.SysProcAttr = &syscall.SysProcAttr{Setpgid: true}

	err := cmd.Start()

	if err != nil {
		return "", fmt.Errorf("Cannot execute command: %s", err)
	}

	t := time.AfterFunc(timeout, func() {
		errKill := syscall.Kill(-cmd.Process.Pid, syscall.SIGTERM)
		if errKill != nil {
			log.Warningf("failed to kill [%s]: %s", s, errKill)
		}
	})

	werr := cmd.Wait()

	if !t.Stop() {
		return "", fmt.Errorf("Timeout while executing a shell script.")
	}

	// we need to check error after t.Stop so we can inform the user if timeout was reached and Zabbix agent2 terminated the command
	if strict && werr != nil && !errors.Is(werr, syscall.ECHILD) {
		log.Debugf("Command [%s] execution failed: %s\n%s", s, werr, b.String())

		return "", fmt.Errorf("Command execution failed: %w", werr)
	}

	if MaxExecuteOutputLenB <= len(b.String()) {
		return "", fmt.Errorf("Command output exceeded limit of %d KB", MaxExecuteOutputLenB/1024)
	}

	return strings.TrimRight(b.String(), " \t\r\n"), nil
}

func ExecuteBackground(s string) error {
	cmd := exec.Command("sh", "-c", s)
	err := cmd.Start()

	if err != nil {
		return fmt.Errorf("Cannot execute command: %s", err)
	}

	go cmd.Wait()

	return nil
}
