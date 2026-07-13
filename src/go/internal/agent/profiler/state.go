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
	"runtime"
	"time"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
)

const (
	extensiveProfilingEnabled  = true
	onDemandCPUProfileSeconds  = 5
	onDemandCPUProfileDuration = onDemandCPUProfileSeconds * time.Second
)

const (
	actionEnable      action = "enable"
	actionDisable     action = "disable"
	actionExecute     action = "execute"
	actionSetInterval action = "set_interval"
)

type action string

type state struct {
	options Options
	cpuFile *os.File
	timer   *time.Timer
}

func (s *state) start() {
	if !s.options.Enabled {
		return
	}

	err := s.enable()
	if err != nil {
		log.Errf("profiler: cannot start: %s", err.Error())

		s.options.Enabled = false
	}
}

func (s *state) stop() {
	err := s.stopRequest()
	if err != nil {
		log.Errf("profiler: cannot stop: %s", err.Error())
	}
}

func (s *state) handle(req request) (string, error) {
	switch req.action {
	case actionEnable:
		return s.enableRequest()
	case actionDisable:
		return s.disableRequest()
	case actionExecute:
		return s.executeRequest()
	case actionSetInterval:
		return s.setIntervalRequest(req.interval)
	default:
		return "", errs.New("unknown profiler action")
	}
}

func (s *state) enableRequest() (string, error) {
	if s.options.Enabled {
		return fmt.Sprintf("profiler: already started; profile files are stored in %q", s.options.Dir), nil
	}

	err := s.enable()
	if err != nil {
		return "", err
	}

	log.Infof("profiler: started")

	return fmt.Sprintf("profiler: started; profile files will be stored in %q", s.options.Dir), nil
}

func (s *state) disableRequest() (string, error) {
	if !s.options.Enabled {
		return "profiler: already stopped", nil
	}

	err := s.disable()
	if err != nil {
		return "", err
	}

	log.Infof("profiler: stopped")

	return "profiler: stopped", nil
}

func (s *state) executeRequest() (string, error) {
	periodicEnabled := s.options.Enabled
	if periodicEnabled {
		s.stopTimer()
	} else {
		err := os.MkdirAll(s.options.Dir, profileDirMode)
		if err != nil {
			return "", errs.Wrap(err, "cannot create profiler directory")
		}

		setExtensiveProfiling(true)
	}

	err := s.stopCPUProfile()
	if err != nil {
		s.finishOnDemandProfile(periodicEnabled)

		return "", err
	}

	err = s.startCPUProfile()
	if err != nil {
		s.finishOnDemandProfile(periodicEnabled)

		return "", err
	}

	time.Sleep(onDemandCPUProfileDuration)

	err = s.dump(periodicEnabled)
	s.finishOnDemandProfile(periodicEnabled)

	if err != nil {
		return "", err
	}

	message := fmt.Sprintf(
		"profiler: profiles written to %q; CPU profile collected over the last %d seconds",
		s.options.Dir,
		onDemandCPUProfileSeconds,
	)
	if periodicEnabled {
		return message + "; periodic profiling remains enabled for longer-term CPU metrics", nil
	}

	return message + "; for more precise CPU data, enable periodic profiling with periodic_prof_enable", nil
}

func (s *state) finishOnDemandProfile(periodicEnabled bool) {
	if periodicEnabled {
		s.resetTimer()

		return
	}

	setExtensiveProfiling(false)
}

func (s *state) setIntervalRequest(interval time.Duration) (string, error) {
	if interval <= 0 {
		return "", errs.New("interval must be greater than 0")
	}

	s.options.Interval = interval
	if s.options.Enabled {
		s.resetTimer()
	}

	message := fmt.Sprintf("profiler: interval set to %d seconds", int(interval.Seconds()))
	log.Infof(message)

	return message, nil
}

func (s *state) stopRequest() error {
	if !s.options.Enabled {
		return nil
	}

	err := s.disable()
	if err != nil {
		return err
	}

	return nil
}

func (s *state) enable() error {
	err := os.MkdirAll(s.options.Dir, profileDirMode)
	if err != nil {
		return errs.Wrap(err, "cannot create profiler directory")
	}

	setExtensiveProfiling(true)

	err = s.startCPUProfile()
	if err != nil {
		setExtensiveProfiling(false)

		return err
	}

	s.options.Enabled = true
	s.resetTimer()

	return nil
}

func (s *state) disable() error {
	s.stopTimer()

	err := s.dump(false)

	setExtensiveProfiling(false)

	s.options.Enabled = false

	if err != nil {
		return err
	}

	return nil
}

func (s *state) timerChannel() <-chan time.Time {
	if s.timer == nil {
		return nil
	}

	return s.timer.C
}

func (s *state) processTimer() {
	err := s.dump(true)
	if err != nil {
		log.Errf("profiler: cannot write profiles: %s", err.Error())
	}

	if s.options.Enabled {
		s.resetTimer()
	}
}

func (s *state) resetTimer() {
	s.stopTimer()
	s.timer = time.NewTimer(s.options.Interval)
}

func (s *state) stopTimer() {
	if s.timer == nil {
		return
	}

	s.timer.Stop()
	s.timer = nil
}

func setExtensiveProfiling(enabled bool) {
	if !extensiveProfilingEnabled {
		return
	}

	rate := 0
	if enabled {
		rate = 1
	}

	runtime.SetBlockProfileRate(rate)
	runtime.SetMutexProfileFraction(rate)
}
