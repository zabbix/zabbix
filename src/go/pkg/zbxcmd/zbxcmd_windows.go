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
	"fmt"
	"os/exec"
	"strings"
	"syscall"
	"time"
	"unsafe"

	"zabbix.com/pkg/log"

	"golang.org/x/sys/windows"
)

type process struct {
	Pid    int
	Handle uintptr
}

var ntResumeProcess *syscall.Proc

func execute(s string, timeout time.Duration, path string, strict bool) (out string, err error) {
	cmd := exec.Command("cmd")
	cmd.Dir = path

	var b bytes.Buffer
	cmd.Stdout = &b
	cmd.Stderr = &b

	var job windows.Handle
	if job, err = windows.CreateJobObject(nil, nil); err != nil {
		return
	}
	defer windows.CloseHandle(job)

	cmd.SysProcAttr = &windows.SysProcAttr{
		CreationFlags: windows.CREATE_SUSPENDED,
		CmdLine:       fmt.Sprintf(`/C "%s"`, s),
	}
	if err = cmd.Start(); err != nil {
		return "", fmt.Errorf("Cannot execute command: %s", err)
	}

	processHandle := windows.Handle((*process)(unsafe.Pointer(cmd.Process)).Handle)

	defer func() {
		if cmd.ProcessState == nil {
			log.Warningf("attempting to terminate process because normal processing was interrupted by error: %s", err)
			windows.TerminateProcess(processHandle, 0)
			_ = cmd.Wait()
		}
	}()

	if err = windows.AssignProcessToJobObject(job, processHandle); err != nil {
		log.Warningf("cannot assign process to a job: %s", err)
		return
	}

	t := time.AfterFunc(timeout, func() {
		if err = windows.TerminateJobObject(job, 0); err != nil {
			log.Warningf("failed to kill [%s]: %s", s, err)
		}
	})

	var rc uintptr
	if rc, _, err = ntResumeProcess.Call(uintptr(processHandle)); int32(rc) < 0 {
		log.Warningf("cannot resume process: %s", err)
		return
	}

	werr := cmd.Wait()

	if !t.Stop() {
		return "", fmt.Errorf("Timeout while executing a shell script.")
	}

	// we need to check error after t.Stop so we can inform the user if timeout was reached and Zabbix agent2 terminated the command
	if strict && werr != nil {
		return "", fmt.Errorf("Command execution failed: %s", werr)
	}

	if MaxExecuteOutputLenB <= len(b.String()) {
		return "", fmt.Errorf("Command output exceeded limit of %d KB", MaxExecuteOutputLenB/1024)
	}

	return strings.TrimRight(b.String(), " \t\r\n"), nil
}

func ExecuteBackground(s string) error {
	cmd := exec.Command("cmd")
	cmd.SysProcAttr = &windows.SysProcAttr{
		CmdLine: fmt.Sprintf(`/C %s`, s),
	}

	if err := cmd.Start(); err != nil {
		return fmt.Errorf("Cannot execute command: %s", err)
	}

	go func() { _ = cmd.Wait() }()

	return nil
}

func init() {
	dll := syscall.MustLoadDLL("ntdll.dll")
	defer dll.Release()
	ntResumeProcess = dll.MustFindProc("NtResumeProcess")
}
