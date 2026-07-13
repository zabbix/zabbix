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
	"os"
	"path/filepath"
	"slices"
	"testing"
	"time"
)

//nolint:paralleltest // CPU profiling is process-global.
func TestRestartCPUProfileWithinSameSecond(t *testing.T) {
	dir := t.TempDir()
	s := state{options: Options{Dir: dir}}

	now := time.Now()
	time.Sleep(time.Second - time.Duration(now.Nanosecond()) + 10*time.Millisecond)

	err := s.startCPUProfile()
	if err != nil {
		t.Fatalf("cannot start CPU profile: %s", err)
	}

	t.Cleanup(func() {
		stopErr := s.stopCPUProfile()
		if stopErr != nil {
			t.Errorf("cannot stop CPU profile: %s", stopErr)
		}
	})

	err = s.stopCPUProfile()
	if err != nil {
		t.Fatalf("cannot stop CPU profile: %s", err)
	}

	err = s.startCPUProfile()
	if err != nil {
		t.Fatalf("cannot restart CPU profile within the same second: %s", err)
	}
}

//nolint:paralleltest // CPU profiling is process-global.
func TestDumpRestartsCPUProfileAfterRotationFailure(t *testing.T) {
	dir := filepath.Join(t.TempDir(), "profiles[")

	err := os.MkdirAll(dir, profileDirMode)
	if err != nil {
		t.Fatalf("cannot create profile directory: %s", err)
	}

	s := state{options: Options{
		Dir:                dir,
		MaxFilesPerProfile: 2,
	}}

	err = s.startCPUProfile()
	if err != nil {
		t.Fatalf("cannot start CPU profile: %s", err)
	}

	t.Cleanup(func() {
		stopErr := s.stopCPUProfile()
		if stopErr != nil {
			t.Errorf("cannot stop CPU profile: %s", stopErr)
		}
	})

	err = s.dump(true)
	if err == nil {
		t.Fatal("expected profile rotation to fail")
	}

	if s.cpuFile == nil {
		t.Fatal("CPU profiling was not restarted after rotation failure")
	}
}

func TestWriteProfileWithinSameSecond(t *testing.T) {
	t.Parallel()

	const timestamp = "20260713_163841"

	s := state{options: Options{Dir: t.TempDir()}}

	err := s.writeProfile("heap", timestamp)
	if err != nil {
		t.Fatalf("cannot write profile: %s", err)
	}

	err = s.writeProfile("heap", timestamp)
	if err != nil {
		t.Fatalf("cannot rewrite profile within the same second: %s", err)
	}
}

func TestRotateProfileKeepsNewestFiles(t *testing.T) {
	t.Parallel()

	const testProfile = "heap"

	dir := t.TempDir()
	s := state{options: Options{
		Dir:                dir,
		MaxFilesPerProfile: 2,
	}}

	timestamps := []string{
		"20260101_000000_000000001",
		"20260101_000000_000000002",
		"20260101_000000_000000003",
	}
	for _, timestamp := range timestamps {
		err := os.WriteFile(s.profilePath(testProfile, timestamp), []byte("profile"), profileFileMode)
		if err != nil {
			t.Fatalf("cannot create profile file: %s", err)
		}
	}

	err := s.rotateProfile(testProfile)
	if err != nil {
		t.Fatalf("cannot rotate profile files: %s", err)
	}

	files, err := filepath.Glob(filepath.Join(dir, testProfile+"_*.pprof"))
	if err != nil {
		t.Fatalf("cannot list profile files: %s", err)
	}

	slices.Sort(files)

	want := []string{
		s.profilePath(testProfile, timestamps[1]),
		s.profilePath(testProfile, timestamps[2]),
	}
	if !slices.Equal(files, want) {
		t.Fatalf("got profile files %v, want %v", files, want)
	}
}

func TestProfilesExcludingCPU(t *testing.T) {
	t.Parallel()

	wantSnapshots := []string{
		"allocs",
		"block",
		"goroutine",
		"heap",
		"mutex",
		"threadcreate",
	}

	profiles := profilesExcludingCPU()
	if !slices.Equal(profiles, wantSnapshots) {
		t.Fatalf("got profiles %v, want %v", profiles, wantSnapshots)
	}
}
