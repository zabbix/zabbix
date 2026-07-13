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
	"path/filepath"
	"runtime/pprof"
	"slices"
	"time"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
)

const (
	profileFileMode = 0o600
	profileDirMode  = 0o700
	timeFormat      = "20060102_150405"

	profileCPU = "cpu"
)

func (s *state) dump(restartCPUProfile bool) error {
	err := os.MkdirAll(s.options.Dir, profileDirMode)
	if err != nil {
		return errs.Wrap(err, "cannot create profiler directory")
	}

	err = s.stopCPUProfile()
	if err != nil {
		return err
	}

	if restartCPUProfile {
		// Keep periodic CPU profiling active even if writing another profile fails.
		defer func() {
			restartErr := s.startCPUProfile()
			if restartErr != nil {
				log.Errf("profiler: cannot restart CPU profile after dump: %s", restartErr.Error())
			}
		}()
	}

	timestamp := time.Now().Format(timeFormat)

	profiles := profilesExcludingCPU()
	for _, profile := range profiles {
		err = s.writeProfile(profile, timestamp)
		if err != nil {
			return err
		}
	}

	err = s.rotate(append([]string{profileCPU}, profiles...))
	if err != nil {
		return err
	}

	return nil
}

func (s *state) startCPUProfile() error {
	if s.cpuFile != nil {
		return nil
	}

	path := s.profilePath(profileCPU, time.Now().Format(timeFormat))

	file, err := os.OpenFile(path, os.O_RDWR|os.O_CREATE|os.O_TRUNC, profileFileMode) //nolint:gosec
	if err != nil {
		return errs.Wrap(err, "cannot create CPU profile file")
	}

	err = pprof.StartCPUProfile(file)
	if err != nil {
		closeErr := file.Close()
		if closeErr != nil {
			log.Debugf("cannot close CPU profile file: %s", closeErr.Error())
		}

		return errs.Wrap(err, "cannot start CPU profile")
	}

	s.cpuFile = file

	return nil
}

func (s *state) stopCPUProfile() error {
	if s.cpuFile == nil {
		return nil
	}

	pprof.StopCPUProfile()

	err := s.cpuFile.Close()
	s.cpuFile = nil

	if err != nil {
		return errs.Wrap(err, "cannot close CPU profile file")
	}

	return nil
}

func (s *state) writeProfile(name, timestamp string) error {
	profile := pprof.Lookup(name)
	if profile == nil {
		return errs.Wrapf(errs.New("profile is not available"), "profile %q", name)
	}

	file, err := os.OpenFile(
		s.profilePath(name, timestamp), os.O_RDWR|os.O_CREATE|os.O_TRUNC, profileFileMode,
	)
	if err != nil {
		return errs.Wrapf(err, "cannot create %s profile file", name)
	}

	defer func() {
		closeErr := file.Close()
		if closeErr != nil {
			log.Debugf("cannot close profile file: %s", closeErr.Error())
		}
	}()

	err = profile.WriteTo(file, 0)
	if err != nil {
		return errs.Wrapf(err, "cannot write %s profile", name)
	}

	return nil
}

func (s *state) rotate(profiles []string) error {
	for _, profile := range profiles {
		err := s.rotateProfile(profile)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *state) rotateProfile(profile string) error {
	files, err := filepath.Glob(filepath.Join(s.options.Dir, profile+"_*.pprof"))
	if err != nil {
		return errs.Wrapf(err, "cannot list %s profile files", profile)
	}

	if len(files) <= s.options.MaxFilesPerProfile {
		return nil
	}

	slices.Sort(files)

	for _, file := range files[:len(files)-s.options.MaxFilesPerProfile] {
		err = os.Remove(file)
		if err != nil && !os.IsNotExist(err) {
			return errs.Wrapf(err, "cannot remove old profile file %s", file)
		}
	}

	return nil
}

func (s *state) profilePath(profile, timestamp string) string {
	return filepath.Join(s.options.Dir, fmt.Sprintf("%s_%s.pprof", profile, timestamp))
}

func profilesExcludingCPU() []string {
	// By default pprof.Profiles returns allocs, block, goroutine, heap, mutex, and threadcreate.
	// It also includes any profiles registered with pprof.NewProfile.
	profiles := pprof.Profiles()
	names := make([]string, 0, len(profiles))

	for _, profile := range profiles {
		names = append(names, profile.Name())
	}

	return names
}
