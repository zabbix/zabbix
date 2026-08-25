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

package main

import (
	"testing"
)

func TestParseArgs(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name string
		args []string
		want Arguments
	}{
		{
			name: "+defaults",
			want: Arguments{configPath: confDefault, foreground: true},
		},
		{
			name: "+configurationAndPrint",
			args: []string{"-c", "/tmp/zabbix_agent2.conf", "-p", "-v"},
			want: Arguments{
				configPath: "/tmp/zabbix_agent2.conf",
				foreground: true,
				print:      true, //nolint:forbidigo // Arguments field, not the print built-in.
				verbose:    true,
			},
		},
		{
			name: "+testItem",
			args: []string{"--test", "system.cpu.load[percpu,avg1]", "--verbose"},
			want: Arguments{
				configPath: confDefault,
				foreground: true,
				test:       "system.cpu.load[percpu,avg1]",
				verbose:    true,
			},
		},
		{
			name: "+testConfiguration",
			args: []string{"-T"},
			want: Arguments{configPath: confDefault, foreground: true, testConfig: true},
		},
		{
			name: "+background",
			args: []string{"--foreground=false"},
			want: Arguments{configPath: confDefault},
		},
		{
			name: "+applicationHelp",
			args: []string{"-h"},
			want: Arguments{configPath: confDefault, foreground: true, help: true},
		},
		{
			name: "+applicationVersion",
			args: []string{"--version"},
			want: Arguments{configPath: confDefault, foreground: true, version: true},
		},
		{
			name: "+quotedCommand",
			args: []string{"-R", "periodic_prof_set_interval 10"},
			want: Arguments{
				configPath:     confDefault,
				foreground:     true,
				runtimeCommand: "periodic_prof_set_interval 10",
			},
		},
		{
			name: "+logLevelIncrease",
			args: []string{"-R", "log_level_increase"},
			want: Arguments{configPath: confDefault, foreground: true, runtimeCommand: "log_level_increase"},
		},
		{
			name: "+logLevelDecrease",
			args: []string{"--runtime-control", "log_level_decrease"},
			want: Arguments{configPath: confDefault, foreground: true, runtimeCommand: "log_level_decrease"},
		},
		{
			name: "+userParameterReload",
			args: []string{"-R", "userparameter_reload"},
			want: Arguments{configPath: confDefault, foreground: true, runtimeCommand: "userparameter_reload"},
		},
		{
			name: "+metrics",
			args: []string{"-R", "metrics"},
			want: Arguments{configPath: confDefault, foreground: true, runtimeCommand: "metrics"},
		},
		{
			name: "+version",
			args: []string{"-R", "version"},
			want: Arguments{configPath: confDefault, foreground: true, runtimeCommand: "version"},
		},
		{
			name: "+help",
			args: []string{"-R", "help"},
			want: Arguments{configPath: confDefault, foreground: true, runtimeCommand: "help"},
		},
		{
			name: "+unquotedCommand",
			args: []string{"-R", "periodic_prof_set_interval", "10"},
			want: Arguments{
				configPath:     confDefault,
				foreground:     true,
				runtimeCommand: "periodic_prof_set_interval 10",
			},
		},
		{
			name: "+multipleParameters",
			args: []string{"--runtime-control", "command", "first", "second"},
			want: Arguments{
				configPath:     confDefault,
				foreground:     true,
				runtimeCommand: "command first second",
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			_, args, err := parseArgsFrom(tt.args)
			if err != nil {
				t.Fatalf("parseArgs() error = %v", err)
			}

			if *args != tt.want {
				t.Fatalf("arguments = %+v, want %+v", *args, tt.want)
			}
		})
	}
}
