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

package agent

import (
	"testing"

	"golang.zabbix.com/sdk/plugin"
)

var results = []Result{ //nolint:gochecknoglobals // const for tests.
	{
		data: []string{"system.test,who | wc -l",
			"vfs.dir.size[*],dir=\"$1\"; du -s -B 1 \"${dir:-/tmp}\" | cut -f1",
			"proc.cpu[*],proc=\"$1\"; ps -o pcpu= -C \"${proc:-zabbix_agentd}\" | awk '{sum += $$1} END {print sum}",
			"unix_mail.queue,mailq | grep -v \"Mail queue is empty\" | grep -c '^[0-9A-Z]",
			"vfs.partitions.discovery.linux," +
				"for partition in $(awk 'NR > 2 {print $4}' /proc/partitions); " +
				"do partitionlist=\"$partitionlist,\"'{\"{#PARTITION}\":\"'$partition'\"}'; " +
				"done; echo '{\"data\":['${partitionlist#,}']}'",
			"vfs.partitions.discovery.solaris,/somewhere/solaris_partitions.sh"},
		failed: false,
	},
	{
		data:   []string{""},
		failed: true,
	},
	{
		data:   []string{","},
		failed: true,
	},
	{
		data:   []string{"a"},
		failed: true,
	},
	{
		data:   []string{"a,"},
		failed: true,
	},
	{
		data:   []string{"a,"},
		failed: true,
	},
	{
		data:   []string{"!,a"},
		failed: true,
	},
	{
		data:   []string{"a,a"},
		failed: false,
	},
	{
		data:   []string{"a[,a"},
		failed: true,
	},
	{
		data:   []string{"a[],a"},
		failed: true,
	},
	{
		data:   []string{"a[b],a"},
		failed: true,
	},
	{
		data:   []string{"a[*,a"},
		failed: true,
	},
	{
		data:   []string{"a*],a"},
		failed: true,
	},
	{
		data:   []string{"a[*],a"},
		failed: false,
	},
	{
		data:   []string{"a[ *],a"},
		failed: false,
	},
	{
		data:   []string{"a[* ],a"},
		failed: true,
	},
	{
		data:   []string{"a[ * ],a"},
		failed: true,
	},
}

var resultsCmd = []Result{ //nolint:gochecknoglobals // const for tests.
	{
		data: []string{"system.test,who | wc -l",
			"vfs.dir.size[*],dir=\"$1\"; du -s -B 1 \"${dir:-/tmp}\" | cut -f1",
			"proc.cpu[*],proc=\"$1\"; ps -o pcpu= -C \"${proc:-zabbix_agentd}\" | awk '{sum += $$1} END {print sum}",
			"unix_mail.queue,mailq | grep -v \"Mail queue is empty\" | grep -c '^[0-9A-Z]",
			"vfs.partitions.discovery.linux," +
				"for partition in $(awk 'NR > 2 {print $4}' /proc/partitions); " +
				"do partitionlist=\"$partitionlist,\"'{\"{#PARTITION}\":\"'$partition'\"}'; " +
				"done; echo '{\"data\":['${partitionlist#,}']}'",
			"vfs.partitions.discovery.solaris,/somewhere/solaris_partitions.sh",
		},
		input: []Input{
			{
				key:    "system.test",
				params: []string{},
				cmd:    "who | wc -l",
			},
			{
				key:    "vfs.dir.size",
				params: []string{"/tmp"},
				cmd:    "dir=\"/tmp\"; du -s -B 1 \"${dir:-/tmp}\" | cut -f1",
			},
			{
				key:    "proc.cpu",
				params: []string{"foo"},
				cmd:    "proc=\"foo\"; ps -o pcpu= -C \"${proc:-zabbix_agentd}\" | awk '{sum += $1} END {print sum}",
			},
			{
				key:    "unix_mail.queue",
				params: []string{},
				cmd:    "mailq | grep -v \"Mail queue is empty\" | grep -c '^[0-9A-Z]",
			},
			{
				key:    "vfs.partitions.discovery.linux",
				params: []string{},
				cmd: "for partition in $(awk 'NR > 2 {print $4}' /proc/partitions); " +
					"do partitionlist=\"$partitionlist,\"'{\"{#PARTITION}\":\"'$partition'\"}'; " +
					"done; echo '{\"data\":['${partitionlist#,}']}'",
			},
			{
				key:    "vfs.partitions.discovery.solaris",
				params: []string{},
				cmd:    "/somewhere/solaris_partitions.sh",
			},
		},
	},
	{
		data: []string{"a,b"},
		input: []Input{
			{
				key:    "a",
				params: []string{},
				cmd:    "b",
			},
		},
	},
	{
		data: []string{"a,b"},
		input: []Input{
			{
				failed: true, key: "a",
				params: []string{"c"},
				cmd:    "b",
			},
		},
	},
	{
		data: []string{"a,$b"},
		input: []Input{
			{
				failed: true, key: "a",
				params: []string{"c"},
				cmd:    "$b",
			},
		},
	},
	{
		data: []string{"a,$"},
		input: []Input{
			{
				failed: true, key: "a",
				params: []string{"c"},
				cmd:    "$",
			},
		},
	},

	{
		data: []string{"a[*],b"},
		input: []Input{
			{
				key:    "a",
				params: []string{"c"},
				cmd:    "b",
			},
		},
	},
	{
		data: []string{"a[*],$"},
		input: []Input{
			{
				key:    "a",
				params: []string{"c"},
				cmd:    "$",
			},
		},
	},
	{
		data: []string{"a[*],$b"},
		input: []Input{
			{
				key:    "a",
				params: []string{"c"},
				cmd:    "$b",
			},
		},
	},
	{
		data: []string{"a[*],b$"},
		input: []Input{
			{
				key:    "a",
				params: []string{"c"},
				cmd:    "b$",
			},
		},
	},
	{
		data: []string{"a[*],$$"},
		input: []Input{
			{
				key:    "a",
				params: []string{"c"},
				cmd:    "$",
			},
		},
	},

	{
		data: []string{"a[*],$1$1$2$3$2$4$5$6$5$7$8$9"},
		input: []Input{
			{
				key:    "a",
				params: []string{"1", "2", "3", "4", "5", "6", "7", "8", "9"},
				cmd:    "112324565789",
			},
		},
	},
	{
		data: []string{"a[*],$1$1$2$3$2$4$5$6$5$7$8$9"},
		input: []Input{
			{
				key:    "a",
				params: []string{"foo"},
				cmd:    "foofoo",
			},
		},
	},
	{
		data: []string{"a[*],$1$1$2$3$2$4$5$6$5$7$8$9"},
		input: []Input{
			{
				key:    "a",
				params: []string{"1a", "2a", "3a", "4a", "5a", "6a", "7a", "8a", "9a"},
				cmd:    "1a1a2a3a2a4a5a6a5a7a8a9a",
			},
		},
	},
	{
		data: []string{"a[*],$1$1$2$3$2$4$5$6$5$7$8$9"},
		input: []Input{
			{
				key:    "a",
				params: []string{"1a", "2a", "3a", "4a", "5a", "6", "7a", "8a", "9a"},
				cmd:    "1a1a2a3a2a4a5a65a7a8a9a",
			},
		},
	},
	{
		data: []string{"a[*],echo $1"},
		input: []Input{
			{
				key:    "a",
				params: []string{},
				cmd:    "echo ",
			},
		},
	},
	{
		data: []string{"a[*],echo $1 foo"},
		input: []Input{
			{
				key:    "a",
				params: []string{},
				cmd:    "echo  foo",
			},
		},
	},
	{
		data: []string{"a[*],echo foo"},
		input: []Input{
			{
				key:    "a",
				params: []string{"foo"},
				cmd:    "echo foo",
			},
		},
	},
	{
		data: []string{"a[*],echo $1 foo"},
		input: []Input{
			{
				key:    "a",
				params: []string{"foo"},
				cmd:    "echo foo foo", //nolint:dupword // intended.
			},
		},
	},
	{
		data: []string{"a[*],$1"},
		input: []Input{
			{
				key:    "a",
				params: []string{"c"},
				cmd:    "c",
			},
		},
	},
	{
		data: []string{"a[*],echo $1"},
		input: []Input{
			{
				failed: true,
				key:    "a",
				params: []string{"%"},
				cmd:    "",
			},
		},
	},
	{
		data: []string{"a[*],echo $1"},
		input: []Input{
			{
				failed: true,
				key:    "a",
				params: []string{"foo%bar"},
				cmd:    "",
			},
		},
	},
	{
		data:                 []string{"a[*],echo $1"},
		unsafeUserParameters: 1,
		input: []Input{
			{
				key:    "a",
				params: []string{"%"},
				cmd:    "echo %",
			},
		},
	},
	{
		data:                 []string{"a[*],echo $1"},
		unsafeUserParameters: 1,
		input: []Input{
			{
				key:    "a",
				params: []string{"foo%bar"},
				cmd:    "echo foo%bar",
			},
		},
	},
	{
		data: []string{"a,echo " + notAllowedCharacters},
		input: []Input{
			{
				key:    "a",
				params: []string{},
				cmd:    "echo " + notAllowedCharacters,
			},
		},
	},
	{
		data: []string{"a[*],echo $1 " + notAllowedCharacters},
		input: []Input{
			{
				key:    "a",
				params: []string{"foo"},
				cmd:    "echo foo " + notAllowedCharacters,
			},
		},
	},
	{
		data: []string{"a[*],echo $1"},
		input: []Input{
			{
				failed: true,
				key:    "a",
				params: []string{notAllowedCharacters},
				cmd:    "",
			},
		},
	},
	{
		data: []string{"a[*],echo $1"}, unsafeUserParameters: 1,
		input: []Input{
			{
				key:    "a",
				params: []string{notAllowedCharacters},
				cmd:    "echo " + notAllowedCharacters,
			},
		},
	},
	{
		data: []string{"a[*],echo $0"},
		input: []Input{
			{
				key:    "a",
				params: []string{},
				cmd:    "echo echo $0", //nolint:dupword // intended.
			},
		},
	},
	{
		data: []string{"a[*],echo $$$1"},
		input: []Input{
			{
				key:    "a",
				params: []string{},
				cmd:    "echo $",
			},
		},
	},
}

type Input struct {
	key    string
	params []string
	cmd    string
	failed bool
}

type Result struct {
	data                 []string
	failed               bool
	input                []Input
	unsafeUserParameters int
}

func TestUserParameterPlugin(t *testing.T) { //nolint:paralleltest // not possible because of plugin.Metrics.
	for _, result := range results { //nolint:paralleltest // not possible because of plugin.Metrics.
		t.Run(result.data[0], func(t *testing.T) {
			plugin.Metrics = make(map[string]*plugin.Metric)

			_, err := InitUserParameterPlugin(result.data, result.unsafeUserParameters, "")
			if err != nil {
				if !result.failed {
					t.Errorf("Expected success while got error %s", err)
				}
			} else if result.failed {
				t.Errorf("Expected error while got success")
			}
		})
	}
}

func TestCmd(t *testing.T) { //nolint:paralleltest // not possible because of plugin.Metrics.
	for _, resultCmd := range resultsCmd { //nolint:paralleltest // not possible because of plugin.Metrics.
		t.Run(resultCmd.data[0], func(t *testing.T) {
			plugin.Metrics = make(map[string]*plugin.Metric)

			_, err := InitUserParameterPlugin(resultCmd.data, resultCmd.unsafeUserParameters, "")
			if err != nil {
				t.Errorf("Plugin init failed: %s", err)
			}

			for j := range resultCmd.input {
				cmd, err := userParameter.cmd(resultCmd.input[j].key, resultCmd.input[j].params)
				if err != nil {
					if !resultCmd.input[j].failed {
						t.Errorf("cmd test %s failed %s", resultCmd.input[j].key, err)
					}
				} else {
					if resultCmd.input[j].failed {
						t.Errorf("Expected error while got success")
					}

					if resultCmd.input[j].cmd != cmd {
						t.Errorf("cmd test %s failed: expected command: [%s] got: [%s]",
							resultCmd.input[j].key,
							resultCmd.input[j].cmd,
							cmd,
						)
					}
				}
			}
		})
	}
}

func TestUnsafeCmd(t *testing.T) { //nolint:paralleltest // not possible because of plugin.Metrics.
	t.Run("", func(t *testing.T) {
		plugin.Metrics = make(map[string]*plugin.Metric)

		_, err := InitUserParameterPlugin([]string{"a[*],echo $1"}, 0, "")
		if err != nil {
			t.Errorf("Plugin init failed: %s", err)
		}

		for _, c := range notAllowedCharacters {
			_, err = userParameter.cmd("a", []string{string(c)})
			if err == nil {
				t.Errorf("Not allowed character is present")
			}
		}

		plugin.Metrics = make(map[string]*plugin.Metric)

		_, err = InitUserParameterPlugin([]string{"a[*],echo $1"}, 1, "")
		if err != nil {
			t.Errorf("Plugin init failed: %s", err)
		}

		for _, c := range notAllowedCharacters {
			_, err := userParameter.cmd("a", []string{string(c)})
			if err != nil {
				t.Errorf("Not allowed character is present")
			}
		}
	})
}
