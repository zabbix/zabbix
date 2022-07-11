//go:build !windows
// +build !windows

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

package swap

import (
	"errors"
	"fmt"
	"regexp"
	"sort"
	"strings"
	"time"

	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/zbxerr"
	"zabbix.com/pkg/zbxcmd"
)

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

// Options -
type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	Timeout              int
}

type manager struct {
	name    string
	testCmd string
	cmd     string
	parser  func(in []string, regex string) ([]string, error)
}

var impl Plugin

// Configure -
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	p.options.Timeout = global.Timeout
}

// Validate -
func (p *Plugin) Validate(options interface{}) error { return nil }

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if key != "system.sw.packages" {
		return nil, plugin.UnsupportedMetricError
	}

	if len(params) > 3 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	var short bool

	var regex string

	manager := "all"

	switch len(params) {
	case 3:
		switch params[2] {
		case "short":
			short = true
		case "full", "":
		default:
			return nil, errors.New("Invalid third parameter.")
		}

		fallthrough
	case 2:
		if params[1] != "" {
			manager = params[1]
		}

		fallthrough
	case 1:
		regex = params[0]
	}

	managers := getManagers()

	for _, m := range managers {
		if manager != "all" && m.name != manager {
			continue
		}

		test, err := zbxcmd.Execute(m.testCmd, time.Second*time.Duration(p.options.Timeout), "")
		if err != nil || test == "" {
			continue
		}

		tmp, err := zbxcmd.Execute(m.cmd, time.Second*time.Duration(p.options.Timeout), "")
		if err != nil {
			p.Errf("Failed to execute command '%s', err: %s", m.cmd, err.Error())

			continue
		}

		var s []string

		if tmp != "" {
			s, err = m.parser(strings.Split(tmp, "\n"), regex)
			if err != nil {
				p.Errf("Failed to parse '%s' output, err: %s", m.cmd, err.Error())

				continue
			}
		}

		sort.Strings(s)

		var out string

		if short {
			out = strings.Join(s, ", ")
		} else {
			if len(s) != 0 {
				out = fmt.Sprintf("[%s] %s", m.name, strings.Join(s, ", "))
			} else {
				out = fmt.Sprintf("[%s]", m.name)
			}
		}

		if result == nil {
			result = out
		} else if out != "" {
			result = fmt.Sprintf("%s\n%s", result, out)
		}
	}

	if result == nil {
		return nil, errors.New("Cannot obtain package information.")
	}

	return
}

func getManagers() []manager {
	return []manager{
		{"dpkg", "dpkg --version 2> /dev/null", "dpkg --get-selections", parseDpkg},
		{"pkgtools", "[ -d /var/log/packages ] && echo true", "ls /var/log/packages", parseRegex},
		{"rpm", "rpm --version 2> /dev/null", "rpm -qa", parseRegex},
		{"pacman", "pacman --version 2> /dev/null", "pacman -Q", parseRegex},
	}
}

func parseRegex(in []string, regex string) (out []string, err error) {
	if regex == "" {
		return in, nil
	}

	rgx, err := regexp.Compile(regex)
	if err != nil {
		return nil, err
	}

	for _, s := range in {
		matched := rgx.MatchString(s)
		if !matched {
			continue
		}

		out = append(out, s)
	}

	return
}

func parseDpkg(in []string, regex string) (out []string, err error) {
	rgx, err := regexp.Compile(regex)
	if err != nil {
		return nil, err
	}

	for _, s := range in {
		split := strings.Fields(s)
		if len(split) < 2 || split[len(split)-1] != "install" {
			continue
		}

		str := strings.Join(split[:len(split)-1], " ")

		matched := rgx.MatchString(str)
		if !matched {
			continue
		}

		out = append(out, str)
	}

	return
}

func init() {
	plugin.RegisterMetrics(&impl, "Sw",
		"system.sw.packages", "Lists installed packages whose name matches the given package regular expression.",
	)
}
