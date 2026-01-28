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

var results = []Result{
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
	},
	{
		failed: true,
		data:   []string{""},
	},
	{
		failed: true,
		data:   []string{","},
	},
	{
		failed: true,
		data:   []string{"a"},
	},
	{
		failed: true,
		data:   []string{"a,"},
	},
	{
		failed: true,
		data:   []string{"a,"},
	},
	{
		failed: true,
		data:   []string{"!,a"},
	},
	{
		data: []string{"a,a"},
	},
	{
		failed: true,
		data:   []string{"a[,a"},
	},
	{
		failed: true,
		data:   []string{"a[],a"},
	},
	{
		failed: true,
		data:   []string{"a[b],a"},
	},
	{
		failed: true,
		data:   []string{"a[*,a"},
	},
	{
		failed: true,
		data:   []string{"a*],a"},
	},
	{
		data: []string{"a[*],a"},
	},
	{
		data: []string{"a[ *],a"},
	},
	{
		failed: true,
		data:   []string{"a[* ],a"},
	},
	{
		failed: true,
		data:   []string{"a[ * ],a"},
	},
}

func TestUserParameterPlugin(t *testing.T) { //nolint:paralleltest // not possible because of plugin.Metrics.
	for i := range results { //nolint:paralleltest // not possible because of plugin.Metrics.
		t.Run(results[i].data[0], func(t *testing.T) {
			plugin.Metrics = make(map[string]*plugin.Metric)

			_, err := InitUserParameterPlugin(results[i].data, results[i].unsafeUserParameters, "")
			if err != nil {
				if !results[i].failed {
					t.Errorf("Expected success while got error %s", err)
				}
			} else if results[i].failed {
				t.Errorf("Expected error while got success")
			}
		})
	}
}

var resultsCmd = []Result{
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
		data: []string{"a,echo \\'\"`*?[]{}~$!&;()<>|#@\n"},
		input: []Input{
			{
				key:    "a",
				params: []string{},
				cmd:    "echo \\'\"`*?[]{}~$!&;()<>|#@\n",
			},
		},
	},
	{
		data: []string{"a[*],echo $1 \\'\"`*?[]{}~$!&;()<>|#@\n"},
		input: []Input{
			{
				key:    "a",
				params: []string{"foo"},
				cmd:    "echo foo \\'\"`*?[]{}~$!&;()<>|#@\n",
			},
		},
	},
	{
		data: []string{"a[*],echo $1"},
		input: []Input{
			{
				failed: true, key: "a",
				params: []string{"\\'\"`*?[]{}~$!&;()<>|#@\n"},
				cmd:    "",
			},
		},
	},
	{
		data: []string{"a[*],echo $1"}, unsafeUserParameters: 1,
		input: []Input{
			{
				key:    "a",
				params: []string{"\\'\"`*?[]{}~$!&;()<>|#@\n"},
				cmd:    "echo \\'\"`*?[]{}~$!&;()<>|#@\n",
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

func TestCmd(t *testing.T) { //nolint:paralleltest // not possible because of plugin.Metrics.
	for i := range resultsCmd { //nolint:paralleltest // not possible because of plugin.Metrics.
		t.Run(resultsCmd[i].data[0], func(t *testing.T) {
			plugin.Metrics = make(map[string]*plugin.Metric)

			_, err := InitUserParameterPlugin(resultsCmd[i].data, resultsCmd[i].unsafeUserParameters, "")
			if err != nil {
				t.Errorf("Plugin init failed: %s", err)
			}

			for j := range resultsCmd[i].input {
				cmd, err := userParameter.cmd(resultsCmd[i].input[j].key, resultsCmd[i].input[j].params)
				if err != nil {
					if !resultsCmd[i].input[j].failed {
						t.Errorf("cmd test %s failed %s", resultsCmd[i].input[j].key, err)
					}
				} else {
					if resultsCmd[i].input[j].failed {
						t.Errorf("Expected error while got success")
					}

					if resultsCmd[i].input[j].cmd != cmd {
						t.Errorf("cmd test %s failed: expected command: [%s] got: [%s]",
							resultsCmd[i].input[j].key,
							resultsCmd[i].input[j].cmd,
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
