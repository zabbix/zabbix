/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	"fmt"
	"strings"
	"time"
	"unicode"

	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxcmd"
)

type parameterInfo struct {
	cmd      string
	flexible bool
}

// Plugin -
type UserParameterPlugin struct {
	plugin.Base
	parameters           map[string]*parameterInfo
	unsafeUserParameters int
	userParameterDir     string
}

var userParameter UserParameterPlugin

func (p *UserParameterPlugin) cmd(key string, params []string) (string, error) {
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
			b.WriteString(s[:i])

			if len(s) > i+1 {
				i++
			}

			if s[i] == '0' {
				b.WriteString(parameter.cmd)
			} else if s[i] >= '1' && s[i] <= '9' {
				if int(s[i]-'0') <= len(params) {
					param := params[s[i]-'0'-1]
					if p.unsafeUserParameters == 0 {
						if j := strings.IndexAny(param, "\\'\"`*?[]{}~$!&;()<>|#@\n"); j != -1 {
							if unicode.IsPrint(rune(param[j])) {
								return "", fmt.Errorf("Character \"%c\" is not allowed", param[j])
							}

							return "", fmt.Errorf("Character 0x%02x is not allowed", param[j])
						}
					}
					b.WriteString(param)
				}
			} else {
				if s[i] != '$' {
					b.WriteByte('$')
				}
				b.WriteByte(s[i])
			}
			s = s[i+1:]
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

	p.Debugf("executing command:'%s'", s)

	stdoutStderr, err := zbxcmd.Execute(s, time.Second*time.Duration(Options.Timeout), p.userParameterDir)
	if err != nil {
		return nil, err
	}

	p.Debugf("command:'%s' length:%d output:'%.20s'", s, len(stdoutStderr), stdoutStderr)

	return stdoutStderr, nil
}

func InitUserParameterPlugin(userParameterConfig []string, unsafeUserParameters int, userParameterDir string) (keys []string, err error) {
	params := make(map[string]*parameterInfo)

	for i := 0; i < len(userParameterConfig); i++ {
		s := strings.SplitN(userParameterConfig[i], ",", 2)
		if len(s) != 2 {
			return nil, fmt.Errorf("cannot add user parameter \"%s\": not comma-separated", userParameterConfig[i])
		}

		key, p, err := itemutil.ParseKey(s[0])
		if err != nil {
			return nil, fmt.Errorf("cannot add user parameter \"%s\": %s", userParameterConfig[i], err)
		}

		if acc, _ := plugin.Get(key); acc != nil {
			return nil, fmt.Errorf(`cannot register user parameter "%s": key already used`, userParameterConfig[i])
		}

		if len(strings.TrimSpace(s[1])) == 0 {
			return nil, fmt.Errorf("cannot add user parameter \"%s\": command is missing", userParameterConfig[i])
		}

		parameter := &parameterInfo{cmd: s[1]}

		if len(p) == 1 && p[0] == "*" {
			parameter.flexible = true
		} else if len(p) != 0 {
			return nil, fmt.Errorf("cannot add user parameter \"%s\": syntax error", userParameterConfig[i])
		}

		params[key] = parameter
		keys = append(keys, key)
	}

	userParameter.parameters = params
	userParameter.unsafeUserParameters = unsafeUserParameters
	userParameter.userParameterDir = userParameterDir

	for _, key := range keys {
		plugin.RegisterMetrics(&userParameter, "UserParameter", key, fmt.Sprintf("User parameter: %s.", userParameter.parameters[key].cmd))
	}

	return keys, nil
}
