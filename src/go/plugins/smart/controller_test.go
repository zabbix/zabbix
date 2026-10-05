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

package smart

import (
	"bytes"
	"encoding/json"
	"os"
	"path/filepath"
	"runtime"
	"strconv"
	"strings"
	"testing"

	"golang.zabbix.com/sdk/log"
)

const (
	successNVMeFixture     = "exit_status/success_nvme.json"
	deviceOpenErrorFixture = "exit_status/device_open_error.json"
)

func TestSmartCtl_ExecuteUsesProcessExitStatus(t *testing.T) {
	if runtime.GOOS == "windows" {
		t.Skip("the Unix smartctl runner invokes sudo")
	}

	testDir := t.TempDir()
	smartctlPath := filepath.Join(testDir, "smartctl")

	writeExecutable(t, filepath.Join(testDir, "sudo"), `#!/bin/sh
if [ "$1" = "-n" ]; then
	shift
fi
exec "$@"
`)
	writeExecutable(t, smartctlPath, `#!/bin/sh
printf '%s' "$SMARTCTL_TEST_OUTPUT"
exit "$SMARTCTL_TEST_EXIT_STATUS"
`)

	t.Setenv("PATH", testDir+string(os.PathListSeparator)+os.Getenv("PATH"))

	ctl := NewSmartCtl(log.New(""), smartctlPath, 5)
	tests := []struct {
		name       string
		fixture    string
		exitStatus int
		wantErr    bool
	}{
		{
			name:       "+successfulProcess",
			fixture:    successNVMeFixture,
			exitStatus: 0,
		},
		{
			name:       "-commandLineErrorProcess",
			fixture:    "exit_status/command_line_error.json",
			exitStatus: 1,
			wantErr:    true,
		},
		{
			name:       "-deviceOpenErrorProcess",
			fixture:    deviceOpenErrorFixture,
			exitStatus: 2,
			wantErr:    true,
		},
		{
			name:       "+optionalCommandErrorProcess",
			fixture:    "exit_status/optional_command_error_nvme.json",
			exitStatus: 4,
		},
		{
			name:       "+selfTestLogErrorProcess",
			fixture:    "exit_status/self_test_error_scsi.json",
			exitStatus: 128,
		},
		{
			name:       "+combinedHealthErrorsProcess",
			fixture:    "exit_status/combined_health_errors_scsi.json",
			exitStatus: 136,
		},
		{
			name:       "-fatalProcessWithNonfatalJSONStatus",
			fixture:    successNVMeFixture,
			exitStatus: 130,
			wantErr:    true,
		},
		{
			name:       "+nonfatalProcessWithFatalJSONStatus",
			fixture:    deviceOpenErrorFixture,
			exitStatus: 4,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			fixture := readControllerFixture(t, tt.fixture)
			t.Setenv("SMARTCTL_TEST_OUTPUT", string(fixture))
			t.Setenv("SMARTCTL_TEST_EXIT_STATUS", strconv.Itoa(tt.exitStatus))

			got, err := ctl.Execute("-a", "/dev/test", "-j")
			if (err != nil) != tt.wantErr {
				t.Fatalf("SmartCtl.Execute() error = %v, wantErr %t", err, tt.wantErr)
			}

			if tt.wantErr {
				if got != nil {
					t.Fatalf("SmartCtl.Execute() output = %q, want nil", got)
				}

				if !strings.Contains(err.Error(), "exit status "+strconv.Itoa(tt.exitStatus)) {
					t.Fatalf(
						"SmartCtl.Execute() error = %q, want process exit status %d",
						err.Error(),
						tt.exitStatus,
					)
				}

				return
			}

			if !bytes.Equal(fixture, got) {
				t.Fatalf("SmartCtl.Execute() output differs from fixture")
			}
		})
	}
}

func writeExecutable(t *testing.T, path, contents string) {
	t.Helper()

	// The test helper must be executable by the subprocess runner.
	err := os.WriteFile(path, []byte(contents), 0o700) //nolint:gosec
	if err != nil {
		t.Fatalf("failed to write executable %q: %v", path, err)
	}
}

func Test_isFatalExitStatusWithSmartctlResponses(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name       string
		fixture    string
		wantStatus int
		wantFatal  bool
		wantParsed bool
	}{
		{
			name:       "+successfulNVMeResponse",
			fixture:    successNVMeFixture,
			wantStatus: 0,
			wantParsed: true,
		},
		{
			name:       "-commandLineError",
			fixture:    "exit_status/command_line_error.json",
			wantStatus: 1,
			wantFatal:  true,
		},
		{
			name:       "-deviceOpenError",
			fixture:    deviceOpenErrorFixture,
			wantStatus: 2,
			wantFatal:  true,
		},
		{
			name:       "+optionalNVMeCommandErrorWithUsableData",
			fixture:    "exit_status/optional_command_error_nvme.json",
			wantStatus: 4,
			wantParsed: true,
		},
		{
			name:       "+SCSISelfTestLogErrorWithUsableData",
			fixture:    "exit_status/self_test_error_scsi.json",
			wantStatus: 128,
			wantParsed: true,
		},
		{
			name:       "+combinedSCSIHealthErrorsWithUsableData",
			fixture:    "exit_status/combined_health_errors_scsi.json",
			wantStatus: 136,
			wantParsed: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			testSmartctlResponseFixture(
				t,
				tt.fixture,
				tt.wantStatus,
				tt.wantFatal,
				tt.wantParsed,
			)
		})
	}
}

func testSmartctlResponseFixture(
	t *testing.T,
	fixture string,
	wantStatus int,
	wantFatal bool,
	wantParsed bool,
) {
	t.Helper()

	data := readControllerFixture(t, fixture)

	var response struct {
		Smartctl struct {
			ExitStatus int `json:"exit_status"`
		} `json:"smartctl"`
	}

	err := json.Unmarshal(data, &response)
	if err != nil {
		t.Fatalf("failed to parse fixture %q: %v", fixture, err)
	}

	if response.Smartctl.ExitStatus != wantStatus {
		t.Fatalf(
			"fixture exit status = %d, want %d",
			response.Smartctl.ExitStatus,
			wantStatus,
		)
	}

	if got := isFatalExitStatus(response.Smartctl.ExitStatus); got != wantFatal {
		t.Fatalf(
			"isFatalExitStatus(%d) = %t, want %t",
			response.Smartctl.ExitStatus,
			got,
			wantFatal,
		)
	}

	if !wantParsed {
		return
	}

	fields, err := setSingleDiskFields(data)
	if err != nil {
		t.Fatalf("setSingleDiskFields() error: %v", err)
	}

	if got := fields["exit_status"]; got != wantStatus {
		t.Fatalf("parsed exit status = %v, want %d", got, wantStatus)
	}

	if got, ok := fields["serial_number"].(string); !ok || got == "" {
		t.Fatalf("parsed serial number = %v, want a non-empty string", fields["serial_number"])
	}
}

func Test_isFatalExitStatus(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name   string
		status int
		want   bool
	}{
		{
			name:   "+success",
			status: 0,
			want:   false,
		},
		{
			name:   "+smartCommandFailed",
			status: 4,
			want:   false,
		},
		{
			name:   "+smartStatusFailed",
			status: 8,
			want:   false,
		},
		{
			name:   "+nonfatalCombinedStatus",
			status: 136,
			want:   false,
		},
		{
			name:   "+allNonfatalStatuses",
			status: 252,
			want:   false,
		},
		{
			name:   "-commandLineDidNotParse",
			status: 1,
			want:   true,
		},
		{
			name:   "-deviceOpenFailed",
			status: 2,
			want:   true,
		},
		{
			name:   "-bothFatalStatuses",
			status: 3,
			want:   true,
		},
		{
			name:   "-fatalAndNonfatalCombinedStatus",
			status: 130,
			want:   true,
		},
		{
			name:   "-allStatuses",
			status: 255,
			want:   true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			if got := isFatalExitStatus(tt.status); got != tt.want {
				t.Fatalf("isFatalExitStatus(%d) = %t, want %t", tt.status, got, tt.want)
			}
		})
	}
}
