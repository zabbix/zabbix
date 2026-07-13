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

package profiler

import (
	"fmt"
	"os"
	"strings"
	"testing"
	"time"
)

func TestEnableAndDisable(t *testing.T) { //nolint:paralleltest // CPU profiling is process-global.
	openTestLog(t)

	dir := t.TempDir()
	controller := startTestController(t, Options{
		Dir:                dir,
		MaxFilesPerProfile: 2,
		Interval:           time.Hour,
	})

	message, err := controller.Enable()
	if err != nil {
		t.Fatalf("cannot enable profiler: %s", err)
	}

	wantStarted := fmt.Sprintf("profiler: started; profile files will be stored in %q", dir)
	if message != wantStarted {
		t.Fatalf("got %q, want %q", message, wantStarted)
	}

	message, err = controller.Enable()
	if err != nil {
		t.Fatalf("cannot enable an already enabled profiler: %s", err)
	}

	wantAlreadyStarted := fmt.Sprintf("profiler: already started; profile files are stored in %q", dir)
	if message != wantAlreadyStarted {
		t.Fatalf("got %q, want %q", message, wantAlreadyStarted)
	}

	message, err = controller.Disable()
	if err != nil {
		t.Fatalf("cannot disable profiler: %s", err)
	}

	if message != "profiler: stopped" {
		t.Fatalf("unexpected disable response: %q", message)
	}

	checkProfileFiles(t, dir)

	message, err = controller.Disable()
	if err != nil {
		t.Fatalf("cannot disable an already disabled profiler: %s", err)
	}

	if message != "profiler: already stopped" {
		t.Fatalf("unexpected disable response: %q", message)
	}
}

func TestSetInterval(t *testing.T) {
	t.Parallel()

	openTestLog(t)

	controller := startTestController(t, Options{})

	message, err := controller.SetInterval(42 * time.Second)
	if err != nil {
		t.Fatalf("cannot set profiler interval: %s", err)
	}

	if message != "profiler: interval set to 42 seconds" {
		t.Fatalf("unexpected interval response: %q", message)
	}

	_, err = controller.SetInterval(0)

	if err == nil || err.Error() != "Interval must be greater than 0." {
		t.Fatalf("unexpected error: %v", err)
	}
}

func TestEnableFailsWhenProfileDirectoryIsFile(t *testing.T) {
	t.Parallel()

	path := t.TempDir() + "/profile-file"

	err := os.WriteFile(path, []byte("not a directory"), profileFileMode)
	if err != nil {
		t.Fatalf("cannot create profile path: %s", err)
	}

	controller := startTestController(t, Options{Dir: path})

	_, err = controller.Enable()
	if err == nil || !strings.Contains(err.Error(), "Cannot create profiler directory") {
		t.Fatalf("unexpected error: %v", err)
	}
}
