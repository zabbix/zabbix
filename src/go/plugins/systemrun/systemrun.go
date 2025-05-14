/*
** Copyright (C) 2001-2025 Zabbix SIA
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

package systemrun

import (
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/pkg/zbxcmd"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

type Options struct {
	LogRemoteCommands int `conf:"optional,range=0:1,default=0"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	options          Options
	executor         zbxcmd.Executor
	executorInitFunc func() (zbxcmd.Executor, error)
}

func init() {
	err := plugin.RegisterMetrics(&impl, "SystemRun", "system.run", "Run specified command.")
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	impl.SetHandleTimeout(true)
}

// Configure configures plugin based on options and other required initialization.
func (p *Plugin) Configure(_ *plugin.GlobalOptions, options any) {
	p.executorInitFunc = zbxcmd.InitExecutor

	err := conf.UnmarshalStrict(options, &p.options)
	if err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
}

// Validate validates plugin options.
func (*Plugin) Validate(options any) error {
	var o Options

	err := conf.UnmarshalStrict(options, &o)
	if err != nil {
		return errs.Wrap(err, "plugin config validation failed")
	}

	return nil
}

// Export -
func (p *Plugin) Export(_ string, params []string, ctx plugin.ContextProvider) (any, error) {
	command, wait, err := parseParameters(params)
	if err != nil {
		return nil, err
	}

	if p.options.LogRemoteCommands == 1 && ctx.ClientID() != agent.LocalChecksClientID {
		p.Warningf("Executing command:'%s'", params[0])
	} else {
		p.Debugf("Executing command:'%s'", params[0])
	}

	// Needed so the executor is initialized once, this should be done in configure, but then Zabbix agent 2
	// will not start if there are issues with finding cmd.exe on windows, and that will break backwards compatibility.
	if p.executor == nil {
		var err error

		p.executor, err = p.executorInitFunc()
		if err != nil {
			return nil, errs.Wrap(err, "command init failed")
		}
	}

	return p.runCommand(command, wait, ctx.Timeout())
}

func (p *Plugin) runCommand(command string, wait bool, timeout int) (any, error) {
	if wait {
		stdoutStderr, err := p.executor.Execute(command, time.Second*time.Duration(timeout), "")
		if err != nil {
			return nil, errs.Wrap(err, "execute failed")
		}

		p.Debugf("command:'%s' length:%d output:'%.20s'", command, len(stdoutStderr), stdoutStderr)

		return stdoutStderr, nil
	}

	err := p.executor.ExecuteBackground(command)
	if err != nil {
		return nil, errs.Wrap(err, "background execute failed")
	}

	return 1, nil
}

func parseParameters(params []string) (string, bool, error) {
	switch len(params) {
	case 0:
		return "", false, errs.New("invalid first parameter")
	case 1:
		if params[0] == "" {
			return "", false, errs.New("invalid first parameter")
		}

		return params[0], true, nil
	case 2:
		switch params[1] {
		case "wait", "":
			return params[0], true, nil
		case "nowait":
			return params[0], false, nil
		default:
			return "", false, errs.New("invalid second parameter")
		}
	default:
		return "", false, errs.New("too many parameters")
	}
}
