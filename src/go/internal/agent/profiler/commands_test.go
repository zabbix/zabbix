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
	"context"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"
	"time"

	"golang.zabbix.com/sdk/log"
)

type commandClient struct {
	request  string
	replies  chan string
	replyErr error
}

func (c *commandClient) Request() string {
	return c.request
}

func (c *commandClient) Reply(response string) error {
	if c.replyErr != nil {
		return c.replyErr
	}

	c.replies <- response

	return nil
}

func (c *commandClient) Close() {}

func TestExecuteCommandCollectsCPUProfile(t *testing.T) { //nolint:paralleltest // CPU profiling is process-global.
	openTestLog(t)

	const sampleInterval = onDemandCPUProfileDuration

	dir := t.TempDir()
	controller := New(Options{
		Dir:                dir,
		MaxFilesPerProfile: 2,
		Interval:           time.Hour,
	})
	ctx, cancel := context.WithCancel(context.Background())

	controller.Start(ctx)
	t.Cleanup(func() {
		cancel()
		controller.Wait()
	})

	client := &commandClient{
		request: commandExecute,
		replies: make(chan string),
	}
	errCh := make(chan error, 1)
	started := time.Now()

	go func() {
		errCh <- controller.ProcessCommand(client)
	}()

	wantProgress := fmt.Sprintf(
		"profiler: collecting CPU profile; results will be written to %q in %d seconds; "+
			"CPU data will cover the next %d seconds; heap, allocs, goroutine, block, mutex, and threadcreate "+
			"profiles will also be written there; for more precise CPU data, enable periodic profiling with %s",
		dir,
		onDemandCPUProfileSeconds,
		onDemandCPUProfileSeconds,
		commandEnable,
	)
	progress := waitForReply(t, client.replies)

	if progress != wantProgress {
		t.Fatalf("unexpected progress response: %q", progress)
	}

	commandErr := <-errCh
	if commandErr != nil {
		t.Fatalf("cannot execute profiler command: %s", commandErr)
	}

	result, err := controller.SetInterval(time.Hour)
	if err != nil {
		t.Fatalf("cannot wait for profiler command completion: %s", err)
	}

	if !strings.Contains(result, "interval set") {
		t.Fatalf("unexpected interval response: %q", result)
	}

	if elapsed := time.Since(started); elapsed < sampleInterval {
		t.Fatalf("CPU profile was collected for %s, expected at least %s", elapsed, sampleInterval)
	}

	checkProfileFiles(t, dir)
}

func TestCommands(t *testing.T) {
	t.Parallel()

	controller := New(Options{})
	want := []string{
		commandDisable,
		commandEnable,
		commandExecute,
		commandSetInterval,
	}
	commands := controller.Commands()

	if !slices.Equal(commands, want) {
		t.Fatalf("got commands %v, want %v", commands, want)
	}
}

func TestExecuteCommandValidation(t *testing.T) {
	t.Parallel()

	controller := New(Options{})
	tests := []struct {
		name   string
		params []string
		want   error
	}{
		{
			name: "empty command",
			want: errEmptyCommand,
		},
		{
			name:   "unknown command",
			params: []string{"unknown"},
			want:   errUnknownCommand,
		},
		{
			name:   "enable with parameter",
			params: []string{commandEnable, "unexpected"},
			want:   errTooManyParameters,
		},
		{
			name:   "missing interval",
			params: []string{commandSetInterval},
			want:   errInvalidParameterNumber,
		},
		{
			name:   "invalid interval",
			params: []string{commandSetInterval, "invalid"},
			want:   errFailedToParseInterval,
		},
		{
			name:   "zero interval",
			params: []string{commandSetInterval, "0"},
			want:   errIntervalOutOfRange,
		},
		{
			name:   "too large interval",
			params: []string{commandSetInterval, "86401"},
			want:   errIntervalOutOfRange,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			_, err := controller.ExecuteCommand(tt.params)
			if !errors.Is(err, tt.want) {
				t.Fatalf("got error %v, want %v", err, tt.want)
			}
		})
	}
}

func TestProcessCommandReplyError(t *testing.T) {
	t.Parallel()

	controller := startTestController(t, Options{})
	client := &commandClient{
		request:  commandDisable,
		replyErr: errors.New("connection closed"),
	}

	err := controller.ProcessCommand(client)
	if err == nil || !strings.Contains(err.Error(), "Cannot reply to remote command") {
		t.Fatalf("unexpected error: %v", err)
	}
}

func TestProcessCommandWithNilController(t *testing.T) {
	t.Parallel()

	var controller *Controller

	err := controller.ProcessCommand(&commandClient{})
	if err == nil || err.Error() != "Profiler is not initialized." {
		t.Fatalf("unexpected error: %v", err)
	}
}

func openTestLog(t *testing.T) {
	t.Helper()

	err := log.Open(log.Console, log.Debug, "", 0)
	if err != nil {
		t.Fatalf("cannot initialize logger: %s", err)
	}
}

func startTestController(t *testing.T, options Options) *Controller {
	t.Helper()

	controller := New(options)
	ctx, cancel := context.WithCancel(context.Background())
	controller.Start(ctx)
	t.Cleanup(func() {
		cancel()
		controller.Wait()
	})

	return controller
}

func waitForReply(t *testing.T, replies <-chan string) string {
	t.Helper()

	select {
	case reply := <-replies:
		return reply
	case <-time.After(time.Second):
		t.Fatal("progress response was not sent immediately")

		return ""
	}
}

func checkProfileFiles(t *testing.T, dir string) {
	t.Helper()

	profiles := append([]string{profileCPU}, profilesExcludingCPU()...)
	for _, profile := range profiles {
		matches, err := filepath.Glob(filepath.Join(dir, profile+"_*.pprof"))
		if err != nil {
			t.Fatalf("cannot list %s profiles: %s", profile, err)
		}

		if len(matches) != 1 {
			t.Errorf("got %d %s profiles, expected 1", len(matches), profile)

			continue
		}

		info, err := os.Stat(matches[0])
		if err != nil {
			t.Fatalf("cannot stat %s profile: %s", profile, err)
		}

		if info.Size() == 0 {
			t.Errorf("%s profile is empty", profile)
		}
	}
}
