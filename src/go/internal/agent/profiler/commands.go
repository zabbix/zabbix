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
	"strconv"
	"strings"
	"time"

	"golang.zabbix.com/agent2/internal/agent/runtimecontrol"
	"golang.zabbix.com/sdk/errs"
)

const (
	commandEnable      = "periodic_prof_enable"
	commandDisable     = "periodic_prof_disable"
	commandExecute     = "periodic_prof_execute"
	commandSetInterval = "periodic_prof_set_interval"
)

type commandHandler func([]string) (string, error)

func newCommandHandlers(c *Controller) map[string]commandHandler {
	return map[string]commandHandler{
		commandEnable:      commandWithoutParameters(c.Enable),
		commandDisable:     commandWithoutParameters(c.Disable),
		commandExecute:     commandWithoutParameters(c.Execute),
		commandSetInterval: c.executeSetIntervalCommand,
	}
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

// ExecuteCommand parses and executes a profiler runtime command.
func (c *Controller) ExecuteCommand(params []string) (string, error) {
	if len(params) == 0 {
		return "", errs.New("empty command")
	}

	handler, ok := c.handlers[params[0]]
	if !ok {
		return "", errs.New("unknown command")
	}

	return handler(params)
}

// ProcessCommand executes a profiler runtime command and replies to the command client.
func (c *Controller) ProcessCommand(client runtimecontrol.Exchanger) error {
	if c == nil {
		return errs.New("profiler is not initialized")
	}

	params := strings.Fields(client.Request())
	if len(params) == 1 && params[0] == commandExecute {
		c.executeAsync()

		err := client.Reply(fmt.Sprintf(
			"profiler: collecting CPU profile; results will be written to %q in %d seconds; "+
				"CPU data will cover the next %d seconds; heap, allocs, goroutine, block, mutex, and threadcreate "+
				"profiles will also be written there; for more precise CPU data, enable periodic profiling with %s",
			c.options.Dir,
			onDemandCPUProfileSeconds,
			onDemandCPUProfileSeconds,
			commandEnable,
		))
		if err != nil {
			return errs.Wrap(err, "cannot reply to remote command")
		}

		return nil
	}

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

func commandWithoutParameters(execute func() (string, error)) commandHandler {
	return func(params []string) (string, error) {
		if len(params) != 1 {
			return "", errs.New("too many parameters")
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
		return "", errs.New("invalid number of parameters")
	}

	seconds, err := strconv.Atoi(params[1])
	if err != nil {
		return "", errs.New("failed to parse interval")
	}

	if seconds < 1 || seconds > 86400 {
		return "", errs.New("interval out of range")
	}

	message, err := c.SetInterval(time.Duration(seconds) * time.Second)
	if err != nil {
		return "", errs.Wrap(err, "cannot set profiler interval")
	}

	return message, nil
}
