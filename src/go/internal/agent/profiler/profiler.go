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
	"time"

	"golang.zabbix.com/sdk/log"
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

type request struct {
	action   action
	interval time.Duration
	reply    chan response
}

type response struct {
	message string
	err     error
}

// New creates a stopped profiler controller.
func New(options Options) *Controller {
	c := &Controller{
		options:  options,
		requests: make(chan request, 1),
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

// Execute collects an on-demand CPU profile and writes all profiles.
func (c *Controller) Execute() (string, error) {
	return c.call(request{action: actionExecute})
}

// SetInterval updates the periodic profiling interval.
func (c *Controller) SetInterval(interval time.Duration) (string, error) {
	return c.call(request{action: actionSetInterval, interval: interval})
}

func (c *Controller) executeAsync() {
	c.requests <- request{action: actionExecute}
}

func (c *Controller) call(req request) (string, error) {
	req.reply = make(chan response)
	c.requests <- req

	resp := <-req.reply
	if resp.err != nil {
		return "", resp.err
	}

	return resp.message, nil
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

			switch {
			case req.reply != nil:
				req.reply <- response{message: message, err: err}
			case err != nil:
				log.Errf("profiler: cannot execute command: %s", err.Error())
			default:
				log.Infof("%s", message)
			}
		case <-ctx.Done():
			s.stop()

			return
		}
	}
}
