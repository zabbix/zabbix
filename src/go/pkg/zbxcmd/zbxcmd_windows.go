/*
** Copyright (C) 2001-2026 Zabbix SIA
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
	"unsafe"

	"golang.org/x/sys/windows"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
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
	job, err := createWinJob()
	if err != nil {
		return "", err
	}

	var b bytes.Buffer

	cmd := exec.Command(e.shellPath)
	cmd.Dir = execDir
	cmd.Stdout = &b
	cmd.Stderr = &b
	cmd.SysProcAttr = &windows.SysProcAttr{
		CreationFlags: windows.CREATE_BREAKAWAY_FROM_JOB,
		CmdLine:       fmt.Sprintf(`/C "%s"`, command),
	}

	// used only in this function to release the goroutine waiting for ctx.Done, no leaks.
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	err = cmd.Start()
	if err != nil {
		return "", errs.Errorf("failed to start command (%s, path: %s): %s", command, execDir, err)
	}

	procHandle, err := windows.OpenProcess(
		windows.PROCESS_SET_QUOTA|windows.PROCESS_TERMINATE,
		false,
		uint32(cmd.Process.Pid),
	)

	if err != nil {
		perr := cmd.Process.Kill()
		if perr != nil {
			return "", errs.Errorf("open process failed: %s and process kill failed: %s", err, perr)
		}

		return "", errs.Errorf("open process failed: %s", err)
	}

	if err := windows.AssignProcessToJobObject(job, procHandle); err != nil {
		perr := cmd.Process.Kill()
		if perr != nil {
			return "", errs.Errorf("process job assignment failed: %s and process kill failed: %s", err, perr)
		}

		return "", errs.Errorf("process job assignment failed: %s", err)
	}

	done := make(chan error, 1)

	go jobDoneListener(done, cmd)
	go timeoutListener(ctx, job)

	err = <-done

	cancel()

	// we need to check context error so we can inform the user if timeout was reached and Zabbix agent2
	// terminated the command
	if errors.Is(ctx.Err(), context.DeadlineExceeded) {
		return "", fmt.Errorf("command execution failed: %s", ctx.Err())
	}

	if strict && err != nil {
		return "", fmt.Errorf("command execution failed: %s", err.Error())
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

func jobDoneListener(done chan<- error, cmd *exec.Cmd) {
	done <- cmd.Wait()
}

func timeoutListener(ctx context.Context, job windows.Handle) {
	<-ctx.Done()

	err := windows.CloseHandle(job)
	if err != nil {
		log.Debugf("failed to kill cmd processes %s", err)
	}
}

func createWinJob() (windows.Handle, error) {
	job, err := windows.CreateJobObject(nil, nil)
	if err != nil {
		return 0, errs.Errorf("failed to create win job: %s", err)
	}

	info := windows.JOBOBJECT_EXTENDED_LIMIT_INFORMATION{
		BasicLimitInformation: windows.JOBOBJECT_BASIC_LIMIT_INFORMATION{
			LimitFlags: windows.JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE,
		},
	}

	if _, err := windows.SetInformationJobObject(
		job,
		windows.JobObjectExtendedLimitInformation,
		uintptr(unsafe.Pointer(&info)),
		uint32(unsafe.Sizeof(info))); err != nil {
		return 0, errs.Errorf("failed to populate win job: %s", err)
	}

	return job, nil
}
