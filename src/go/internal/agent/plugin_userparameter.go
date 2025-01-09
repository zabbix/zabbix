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

package agent

import (
	"bytes"
	"fmt"
	"strings"
	"time"
	"unicode"

	"golang.zabbix.com/agent2/pkg/itemutil"
	"golang.zabbix.com/agent2/pkg/zbxcmd"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
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
func (p *UserParameterPlugin) Export(
	key string,
	params []string,
	ctx plugin.ContextProvider,
) (result interface{}, err error) {
	s, err := p.cmd(key, params)
	if err != nil {
		return nil, err
	}

	p.Debugf("executing command:'%s'", s)

	stdoutStderr, err := zbxcmd.Execute(
		s, time.Second*time.Duration(ctx.Timeout()), p.userParameterDir,
	)
	if err != nil {
		return nil, err
	}

	p.Debugf(
		"command:'%s' length:%d output:'%.20s'",
		s,
		len(stdoutStderr),
		stdoutStderr,
	)

	return stdoutStderr, nil
}

func InitUserParameterPlugin(
	userParameterConfig []string,
	unsafeUserParameters int,
	userParameterDir string,
) ([]string, error) {
	var (
		keys   = make([]string, 0, len(userParameterConfig))
		params = make(map[string]*parameterInfo)
	)

	for _, userParam := range userParameterConfig {
		// split by first comma.
		parts := strings.SplitN(userParam, ",", 2) //nolint:gomnd
		if len(parts) != 2 {                       //nolint:gomnd
			return nil, fmt.Errorf(
				"cannot add user parameter %q: not comma-separated", userParam,
			)
		}

		key, keyParams, err := itemutil.ParseKey(parts[0])
		if err != nil {
			return nil, fmt.Errorf(
				"cannot add user parameter %q: %s", userParam, err.Error(),
			)
		}

		if acc, _ := plugin.Get(key); acc != nil {
			return nil, fmt.Errorf(
				"cannot register user parameter %q: key already used",
				userParam,
			)
		}

		_, ok := params[key]
		if ok {
			return nil, fmt.Errorf(
				"cannot register user parameter %q: duplicate user parameter",
				userParam,
			)
		}

		if len(strings.TrimSpace(parts[1])) == 0 {
			return nil, fmt.Errorf(
				"cannot add user parameter %q: command is missing", userParam,
			)
		}

		parameter := &parameterInfo{cmd: parts[1]}

		if len(keyParams) == 1 && keyParams[0] == "*" {
			parameter.flexible = true
		}

		if len(keyParams) != 0 && !parameter.flexible {
			return nil, fmt.Errorf(
				"cannot add user parameter %q: syntax error", userParam,
			)
		}

		params[key] = parameter
		keys = append(keys, key)
	}

	for key, param := range params {
		err := plugin.RegisterMetrics(
			&userParameter,
			"UserParameter",
			key,
			fmt.Sprintf("User parameter: %s.", param.cmd),
		)
		if err != nil {
			return nil, errs.Wrap(err, "failed to register user parameter metrics")
		}
	}

	userParameter.parameters = params
	userParameter.unsafeUserParameters = unsafeUserParameters
	userParameter.userParameterDir = userParameterDir

	return keys, nil
}
