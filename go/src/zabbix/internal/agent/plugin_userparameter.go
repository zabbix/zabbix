/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package agent

import (
	"context"
	"errors"
	"fmt"
	"os/exec"
	"strings"
	"time"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

type parameterInfo struct {
	cmd      string
	flexible bool
}

// Plugin -
type UserParameterPlugin struct {
	plugin.Base
	parameters map[string]parameterInfo
}

var userParameter UserParameterPlugin

// Export -
func (p *UserParameterPlugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	parameter := p.parameters[key]

	p.Debugf("[%d] executing command:'%s'", ctx.ClientID(), parameter.cmd)

	cmdCtx, cancel := context.WithTimeout(context.Background(), time.Second*time.Duration(Options.Timeout))
	defer cancel()

	cmd := exec.CommandContext(cmdCtx, "sh", "-c", parameter.cmd)

	stdoutStderr, err := cmd.CombinedOutput()

	if err != nil {
		if cmdCtx.Err() == context.DeadlineExceeded {
			p.Debugf("Failed to execute command \"%s\": timeout", parameter.cmd)
			return nil, fmt.Errorf("Timeout while executing a shell script.")
		}

		if len(stdoutStderr) == 0 {
			p.Debugf("Failed to execute command \"%s\": %s", parameter.cmd, err)
			return nil, err
		}

		p.Debugf("Failed to execute command \"%s\": %s", parameter.cmd, string(stdoutStderr))
		return nil, errors.New(string(stdoutStderr))
	}

	p.Debugf("[%d] command:'%s' len:%d cmd_result:'%.20s'", ctx.ClientID(), parameter.cmd, len(stdoutStderr), string(stdoutStderr))

	return string(stdoutStderr), nil
}

func InitUserParameterPlugin() {
	userParameter.parameters = make(map[string]parameterInfo)

	for i := 0; i < len(Options.UserParameter); i++ {
		s := strings.SplitN(Options.UserParameter[i], ",", 2)

		if len(s) != 2 {
			log.Critf("cannot add user parameter \"%s\": not comma-separated", Options.UserParameter[i])
		}

		key, p, err := itemutil.ParseKey(s[0])
		if err != nil {
			log.Critf("cannot add user parameter \"%s\": %s", Options.UserParameter[i], err)
		}

		parameter := parameterInfo{cmd: s[1]}

		if len(p) == 1 && p[0] == "*" {
			parameter.flexible = true
		} else if len(p) != 0 {
			log.Critf("cannot add user parameter \"%s\": syntax error, parameter must be empty or '[*]'", Options.UserParameter[i])
		}

		userParameter.parameters[key] = parameter
		plugin.RegisterMetric(&userParameter, "userparameter", key, "test")
	}
}
