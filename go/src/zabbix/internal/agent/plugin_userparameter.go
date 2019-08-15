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
	"bytes"
	"context"
	"errors"
	"fmt"
	"os/exec"
	"strings"
	"time"
	"unicode"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
)

const maxExecuteOutputLen = 512 * 1024

type parameterInfo struct {
	cmd      string
	flexible bool
}

// Plugin -
type UserParameterPlugin struct {
	plugin.Base
	parameters           map[string]parameterInfo
	unsafeUserParameters int
}

var userParameter UserParameterPlugin

func (p UserParameterPlugin) cmd(key string, params []string) (string, error) {
	var b bytes.Buffer

	parameter := p.parameters[key]
	s := parameter.cmd

	if parameter.flexible {
		var n int

		for i := 0; i < len(params); i++ {
			n += len(params[i])
		}

		b.Grow(len(s) + n)

		for i := strings.IndexByte(s, '$'); i != -1; i = strings.IndexByte(s, '$') {
			if len(s) > i+1 && s[i+1] >= '1' && s[i+1] <= '9' && int(s[i+1]-'0') <= len(params) {
				param := params[s[i+1]-'0'-1]
				if p.unsafeUserParameters == 0 {
					if j := strings.IndexAny(param, "\\'\"`*?[]{}~$!&;()<>|#@\n"); j != -1 {
						if unicode.IsPrint(rune(param[j])) {
							return "", fmt.Errorf("Character \"%c\" is not allowed", param[j])
						}

						return "", fmt.Errorf("Character 0x%02x is not allowed", param[j])
					}
				}

				b.WriteString(s[:i])
				b.WriteString(param)
				s = s[i+2:]
			} else {
				b.WriteString(s[:i+1])
				s = s[i+1:]
			}
		}

		if len(s) != 0 {
			b.WriteString(s)
		}

		s = b.String()
	} else if len(params) > 0 {
		return "", fmt.Errorf("Parameters are not allowed.")
	}

	return s, nil
}

// Export -
func (p *UserParameterPlugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {

	s, err := p.cmd(key, params)
	if err != nil {
		return nil, err
	}

	p.Debugf("[%d] executing command:'%s'", ctx.ClientID(), s)

	cmdCtx, cancel := context.WithTimeout(context.Background(), time.Second*time.Duration(Options.Timeout))
	defer cancel()

	cmd := exec.CommandContext(cmdCtx, "sh", "-c", s)

	stdoutStderr, err := cmd.CombinedOutput()

	if err != nil {
		if cmdCtx.Err() == context.DeadlineExceeded {
			p.Debugf("Failed to execute command \"%s\": timeout", s)
			return nil, fmt.Errorf("Timeout while executing a shell script.")
		}

		if len(stdoutStderr) == 0 {
			p.Debugf("Failed to execute command \"%s\": %s", s, err)
			return nil, err
		}

		p.Debugf("Failed to execute command \"%s\": %s", s, string(stdoutStderr))
		return nil, errors.New(string(stdoutStderr))
	}

	if maxExecuteOutputLen <= len(stdoutStderr) {
		return nil, fmt.Errorf("Command output exceeded limit of %d KB", maxExecuteOutputLen/1024)
	}

	cmdResult := strings.TrimRight(string(stdoutStderr), " \t\r\n")
	p.Debugf("[%d] command:'%s' len:%d cmd_result:'%.20s'", ctx.ClientID(), s, len(cmdResult), cmdResult)

	return cmdResult, nil
}

func InitUserParameterPlugin(userParameterConfig []string, unsafeUserParameters int) error {
	userParameter.parameters = make(map[string]parameterInfo)
	userParameter.unsafeUserParameters = unsafeUserParameters

	for i := 0; i < len(userParameterConfig); i++ {
		s := strings.SplitN(userParameterConfig[i], ",", 2)

		if len(s) != 2 {
			return fmt.Errorf("cannot add user parameter \"%s\": not comma-separated", userParameterConfig[i])
		}

		key, p, err := itemutil.ParseKey(s[0])
		if err != nil {
			return fmt.Errorf("cannot add user parameter \"%s\": %s", userParameterConfig[i], err)
		}

		if len(strings.TrimSpace(s[1])) == 0 {
			return fmt.Errorf("cannot add user parameter \"%s\": command is missing", userParameterConfig[i])
		}

		parameter := parameterInfo{cmd: s[1]}

		if len(p) == 1 && p[0] == "*" {
			parameter.flexible = true
		} else if len(p) != 0 {
			return fmt.Errorf("cannot add user parameter \"%s\": syntax error", userParameterConfig[i])
		}

		userParameter.parameters[key] = parameter
		plugin.RegisterMetric(&userParameter, "userparameter", key, "")
	}

	return nil
}
