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
)

func TestRotateProfileKeepsNewestFiles(t *testing.T) {
	t.Parallel()

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
		err := os.WriteFile(s.profilePath(profileHeap, timestamp), []byte("profile"), profileFileMode)
		if err != nil {
			t.Fatalf("cannot create profile file: %s", err)
		}
	}

	err := s.rotateProfile(profileHeap)
	if err != nil {
		t.Fatalf("cannot rotate profile files: %s", err)
	}

	files, err := filepath.Glob(filepath.Join(dir, profileHeap+"_*.pprof"))
	if err != nil {
		t.Fatalf("cannot list profile files: %s", err)
	}

	slices.Sort(files)

	want := []string{
		s.profilePath(profileHeap, timestamps[1]),
		s.profilePath(profileHeap, timestamps[2]),
	}
	if !slices.Equal(files, want) {
		t.Fatalf("got profile files %v, want %v", files, want)
	}
}

func TestProfileNames(t *testing.T) {
	t.Parallel()

	wantSnapshots := []string{
		profileHeap,
		profileAllocs,
		profileGoroutine,
		profileBlock,
		profileMutex,
		profileThreadCreate,
	}
	if !slices.Equal(profileNames(), wantSnapshots) {
		t.Fatalf("unexpected snapshot profiles: %v", profileNames())
	}

	wantAll := append([]string{profileCPU}, wantSnapshots...)
	if !slices.Equal(allProfileNames(), wantAll) {
		t.Fatalf("unexpected profiles: %v", allProfileNames())
	}
}
