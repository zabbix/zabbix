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
				p := params[s[i+1]-'0'-1]
				if Options.UnsafeUserParameters == 0 {
					if j := strings.IndexAny(p, "\\'\"`*?[]{}~$!&;()<>|#@\n"); j != -1 {
						if unicode.IsPrint(rune(p[j])) {
							return nil, fmt.Errorf("Character \"%c\" is not allowed", p[j])
						} else {
							return nil, fmt.Errorf("Character 0x%02x is not allowed", p[j])
						}
					}
				}

				b.WriteString(s[:i])
				b.WriteString(p)
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
		return nil, fmt.Errorf("Parameters are not allowed.")
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

	cmdResult := strings.TrimRight(string(stdoutStderr), " \t\r\n")
	p.Debugf("[%d] command:'%s' len:%d cmd_result:'%.20s'", ctx.ClientID(), s, len(cmdResult), cmdResult)

	return cmdResult, nil
}

func InitUserParameterPlugin() error {
	userParameter.parameters = make(map[string]parameterInfo)

	for i := 0; i < len(Options.UserParameter); i++ {
		s := strings.SplitN(Options.UserParameter[i], ",", 2)

		if len(s) != 2 {
			return fmt.Errorf("cannot add user parameter \"%s\": not comma-separated", Options.UserParameter[i])
		}

		key, p, err := itemutil.ParseKey(s[0])
		if err != nil {
			return fmt.Errorf("cannot add user parameter \"%s\": %s", Options.UserParameter[i], err)
		}

		parameter := parameterInfo{cmd: s[1]}

		if len(p) == 1 && p[0] == "*" {
			parameter.flexible = true
		} else if len(p) != 0 {
			return fmt.Errorf("cannot add user parameter \"%s\": syntax error", Options.UserParameter[i])
		}

		userParameter.parameters[key] = parameter
		plugin.RegisterMetric(&userParameter, "userparameter", key, "")
	}

	return nil
}
