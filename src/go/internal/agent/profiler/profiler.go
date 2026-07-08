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
	"runtime"
	"runtime/pprof"
	"slices"
	"strconv"
	"strings"
	"time"

	"golang.zabbix.com/agent2/internal/agent/runtimecontrol"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
)

const (
	profileFileMode = 0o600
	profileDirMode  = 0o700
	timeFormat      = "20060102_150405_000000000"
)

const (
	profileCPU          = "cpu"
	profileHeap         = "heap"
	profileAllocs       = "allocs"
	profileGoroutine    = "goroutine"
	profileBlock        = "block"
	profileMutex        = "mutex"
	profileThreadCreate = "threadcreate"
)

const (
	commandEnable      = "periodic_prof_enable"
	commandDisable     = "periodic_prof_disable"
	commandExecute     = "periodic_prof_execute"
	commandSetInterval = "periodic_prof_set_interval"
)

const (
	actionEnable      action = "enable"
	actionDisable     action = "disable"
	actionExecute     action = "execute"
	actionSetInterval action = "set_interval"
)

var (
	errEmptyCommand           = errors.New("empty command")
	errInvalidInterval        = errors.New("interval must be greater than 0")
	errInvalidParameterNumber = errors.New("invalid number of parameters")
	errIntervalOutOfRange     = errors.New("interval out of range")
	errProfileNotFound        = errors.New("profile is not available")
	errProfilerNotInitialized = errors.New("profiler is not initialized")
	errTooManyParameters      = errors.New("too many parameters")
	errUnknownAction          = errors.New("unknown profiler action")
	errUnknownCommand         = errors.New("unknown command")
)

// Options contains periodic profiler settings.
type Options struct {
	Enabled            bool
	Dir                string
	MaxFilesPerProfile int
	Interval           time.Duration
}

// Controller serializes profiler state changes and profile writes.
type Controller struct {
	options  Options
	handlers map[string]commandHandler
	requests chan request
	done     chan struct{}
}

type commandHandler func([]string) (string, error)

type request struct {
	action   action
	interval time.Duration
	reply    chan response
}

type response struct {
	message string
	err     error
}

type action string

type state struct {
	options Options
	cpuFile *os.File
	timer   *time.Timer
}

// New creates a stopped profiler controller.
func New(options Options) *Controller {
	c := &Controller{
		options:  options,
		requests: make(chan request),
		done:     make(chan struct{}),
	}

	c.handlers = map[string]commandHandler{
		commandEnable:      commandWithoutParameters(c.Enable),
		commandDisable:     commandWithoutParameters(c.Disable),
		commandExecute:     commandWithoutParameters(c.Execute),
		commandSetInterval: c.executeSetIntervalCommand,
	}

	return c
}

// HasCommand returns true if command is handled by the profiler.
func HasCommand(command string) bool {
	switch command {
	case commandEnable, commandDisable, commandExecute, commandSetInterval:
		return true
	default:
		return false
	}
}

// Commands returns profiler runtime command names.
func Commands() []string {
	return []string{
		commandEnable,
		commandDisable,
		commandExecute,
		commandSetInterval,
	}
}

// Start runs the profiler controller until context cancellation.
func (c *Controller) Start(ctx context.Context) {
	go c.run(ctx)
}

// Wait waits for the profiler controller to stop.
func (c *Controller) Wait() {
	<-c.done
}

// Enable enables periodic profiling at runtime.
func (c *Controller) Enable() (string, error) {
	return c.call(request{action: actionEnable})
}

// Disable disables periodic profiling at runtime.
func (c *Controller) Disable() (string, error) {
	return c.call(request{action: actionDisable})
}

// Execute writes profiles immediately.
func (c *Controller) Execute() (string, error) {
	return c.call(request{action: actionExecute})
}

// ExecuteCommand parses and executes a profiler runtime command.
func (c *Controller) ExecuteCommand(params []string) (string, error) {
	if len(params) == 0 {
		return "", errEmptyCommand
	}

	handler, ok := c.handlers[params[0]]
	if !ok {
		return "", errUnknownCommand
	}

	return handler(params)
}

// ProcessCommand executes a profiler runtime command and replies to the command client.
func (c *Controller) ProcessCommand(client *runtimecontrol.Client) error {
	if c == nil {
		return errProfilerNotInitialized
	}

	params := strings.Fields(client.Request())

	message, err := c.ExecuteCommand(params)
	if err != nil {
		return err
	}

	err = client.Reply(message)
	if err != nil {
		return errs.Wrap(err, "cannot reply to remote command")
	}

	return nil
}

// SetInterval updates the periodic profiling interval.
func (c *Controller) SetInterval(interval time.Duration) (string, error) {
	return c.call(request{action: actionSetInterval, interval: interval})
}

func commandWithoutParameters(execute func() (string, error)) commandHandler {
	return func(params []string) (string, error) {
		if len(params) != 1 {
			return "", errTooManyParameters
		}

		message, err := execute()
		if err != nil {
			return "", errs.Wrap(err, "cannot execute profiler command")
		}

		return message, nil
	}
}

func (c *Controller) executeSetIntervalCommand(params []string) (string, error) {
	if len(params) != 2 { //nolint:mnd
		return "", errInvalidParameterNumber
	}

	seconds, err := strconv.Atoi(params[1])
	if err != nil {
		return "", errInvalidInterval
	}

	if seconds < 1 || seconds > 86400 {
		return "", errIntervalOutOfRange
	}

	message, err := c.SetInterval(time.Duration(seconds) * time.Second)
	if err != nil {
		return "", errs.Wrap(err, "cannot set profiler interval")
	}

	return message, nil
}

func (c *Controller) call(req request) (string, error) {
	req.reply = make(chan response)

	c.requests <- req

	resp := <-req.reply

	return resp.message, resp.err
}

func (c *Controller) run(ctx context.Context) {
	defer close(c.done)

	s := state{options: c.options}
	s.start()

	for {
		select {
		case <-s.timerChannel():
			s.processTimer()
		case req := <-c.requests:
			message, err := s.handle(req)
			req.reply <- response{message: message, err: err}
		case <-ctx.Done():
			s.stop()

			return
		}
	}
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
		return "", errUnknownAction
	}
}

func (s *state) enableRequest() (string, error) {
	if s.options.Enabled {
		return "profiler: already started", nil
	}

	err := s.enable()
	if err != nil {
		return "", err
	}

	log.Infof("profiler: started")

	return "profiler: started", nil
}

func (s *state) disableRequest() (string, error) {
	if !s.options.Enabled {
		return "profiler: already stopped", nil
	}

	err := s.disable(true)
	if err != nil {
		return "", err
	}

	log.Infof("profiler: stopped")

	return "profiler: stopped", nil
}

func (s *state) executeRequest() (string, error) {
	err := s.dump(s.options.Enabled)
	if err != nil {
		return "", err
	}

	return "profiler: profiles written", nil
}

func (s *state) setIntervalRequest(interval time.Duration) (string, error) {
	if interval <= 0 {
		return "", errInvalidInterval
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

	err := s.disable(true)
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

	runtime.SetBlockProfileRate(1)
	runtime.SetMutexProfileFraction(1)

	err = s.startCPUProfile()
	if err != nil {
		runtime.SetBlockProfileRate(0)
		runtime.SetMutexProfileFraction(0)

		return err
	}

	s.options.Enabled = true
	s.resetTimer()

	return nil
}

func (s *state) disable(writeCurrent bool) error {
	s.stopTimer()

	var err error
	if writeCurrent {
		err = s.dump(false)
	} else {
		err = s.stopCPUProfile()
	}

	runtime.SetBlockProfileRate(0)
	runtime.SetMutexProfileFraction(0)

	s.options.Enabled = false

	return err
}

func (s *state) dump(restartCPUProfile bool) error {
	err := os.MkdirAll(s.options.Dir, profileDirMode)
	if err != nil {
		return errs.Wrap(err, "cannot create profiler directory")
	}

	err = s.stopCPUProfile()
	if err != nil {
		return err
	}

	timestamp := time.Now().Format(timeFormat)
	for _, profile := range profileNames() {
		err = s.writeProfile(profile, timestamp)
		if err != nil {
			return err
		}
	}

	err = s.rotate()
	if err != nil {
		return err
	}

	if restartCPUProfile {
		return s.startCPUProfile()
	}

	return nil
}

func (s *state) startCPUProfile() error {
	if s.cpuFile != nil {
		return nil
	}

	path := s.profilePath(profileCPU, time.Now().Format(timeFormat))

	file, err := os.OpenFile(path, os.O_RDWR|os.O_CREATE|os.O_EXCL, profileFileMode) //nolint:gosec
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
		return errs.Wrapf(errProfileNotFound, "profile %q", name)
	}

	file, err := os.OpenFile(s.profilePath(name, timestamp), os.O_RDWR|os.O_CREATE|os.O_EXCL, profileFileMode)
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

func (s *state) rotate() error {
	for _, profile := range allProfileNames() {
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

func (s *state) resetTimer() {
	s.stopTimer()
	s.timer = time.NewTimer(s.options.Interval)
}

func (s *state) stopTimer() {
	if s.timer == nil {
		return
	}

	if !s.timer.Stop() {
		select {
		case <-s.timer.C:
		default:
		}
	}

	s.timer = nil
}

func profileNames() []string {
	return []string{
		profileHeap,
		profileAllocs,
		profileGoroutine,
		profileBlock,
		profileMutex,
		profileThreadCreate,
	}
}

func allProfileNames() []string {
	return []string{
		profileCPU,
		profileHeap,
		profileAllocs,
		profileGoroutine,
		profileBlock,
		profileMutex,
		profileThreadCreate,
	}
}
