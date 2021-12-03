package agent

import (
	"testing"

	"zabbix.com/pkg/plugin"
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
	Result{data: []string{"system.test,who | wc -l",
		"vfs.dir.size[*],dir=\"$1\"; du -s -B 1 \"${dir:-/tmp}\" | cut -f1",
		"proc.cpu[*],proc=\"$1\"; ps -o pcpu= -C \"${proc:-zabbix_agentd}\" | awk '{sum += $$1} END {print sum}",
		"unix_mail.queue,mailq | grep -v \"Mail queue is empty\" | grep -c '^[0-9A-Z]",
		"vfs.partitions.discovery.linux,for partition in $(awk 'NR > 2 {print $4}' /proc/partitions); do partitionlist=\"$partitionlist,\"'{\"{#PARTITION}\":\"'$partition'\"}'; done; echo '{\"data\":['${partitionlist#,}']}",
		"vfs.partitions.discovery.solaris,/somewhere/solaris_partitions.sh"}},
	Result{failed: true, data: []string{""}},
	Result{failed: true, data: []string{","}},
	Result{failed: true, data: []string{"a"}},
	Result{failed: true, data: []string{"a,"}},
	Result{failed: true, data: []string{"a,"}},
	Result{failed: true, data: []string{"!,a"}},
	Result{data: []string{"a,a"}},
	Result{failed: true, data: []string{"a[,a"}},
	Result{failed: true, data: []string{"a[],a"}},
	Result{failed: true, data: []string{"a[b],a"}},
	Result{failed: true, data: []string{"a[*,a"}},
	Result{failed: true, data: []string{"a*],a"}},
	Result{data: []string{"a[*],a"}},
	Result{data: []string{"a[ *],a"}},
	Result{failed: true, data: []string{"a[* ],a"}},
	Result{failed: true, data: []string{"a[ * ],a"}},
}

func TestUserParameterPlugin(t *testing.T) {
	for i := 0; i < len(results); i++ {
		t.Run(results[i].data[0], func(t *testing.T) {
			plugin.Metrics = make(map[string]*plugin.Metric)

			if _, err := InitUserParameterPlugin(results[i].data, results[i].unsafeUserParameters, ""); err != nil {
				if !results[i].failed {
					t.Errorf("Expected success while got error %s", err)
				}
			} else if results[i].failed {
				t.Errorf("Expected error while got success")
			}
		})
	}
}

const notAllowedCharacters = "\\'\"`*?[]{}~$!&;()<>|#@\n"

var resultsCmd = []Result{
	Result{data: []string{"system.test,who | wc -l",
		"vfs.dir.size[*],dir=\"$1\"; du -s -B 1 \"${dir:-/tmp}\" | cut -f1",
		"proc.cpu[*],proc=\"$1\"; ps -o pcpu= -C \"${proc:-zabbix_agentd}\" | awk '{sum += $$1} END {print sum}",
		"unix_mail.queue,mailq | grep -v \"Mail queue is empty\" | grep -c '^[0-9A-Z]",
		"vfs.partitions.discovery.linux,for partition in $(awk 'NR > 2 {print $4}' /proc/partitions); do partitionlist=\"$partitionlist,\"'{\"{#PARTITION}\":\"'$partition'\"}'; done; echo '{\"data\":['${partitionlist#,}']}",
		"vfs.partitions.discovery.solaris,/somewhere/solaris_partitions.sh"},
		input: []Input{
			Input{key: "system.test", params: []string{}, cmd: "who | wc -l"},
			Input{key: "vfs.dir.size", params: []string{"/tmp"}, cmd: "dir=\"/tmp\"; du -s -B 1 \"${dir:-/tmp}\" | cut -f1"},
			Input{key: "proc.cpu", params: []string{"foo"}, cmd: "proc=\"foo\"; ps -o pcpu= -C \"${proc:-zabbix_agentd}\" | awk '{sum += $1} END {print sum}"},
			Input{key: "unix_mail.queue", params: []string{}, cmd: "mailq | grep -v \"Mail queue is empty\" | grep -c '^[0-9A-Z]"},
			Input{key: "vfs.partitions.discovery.linux", params: []string{}, cmd: "for partition in $(awk 'NR > 2 {print $4}' /proc/partitions); do partitionlist=\"$partitionlist,\"'{\"{#PARTITION}\":\"'$partition'\"}'; done; echo '{\"data\":['${partitionlist#,}']}"},
			Input{key: "vfs.partitions.discovery.solaris", params: []string{}, cmd: "/somewhere/solaris_partitions.sh"},
		},
	},
	Result{data: []string{"a,b"},
		input: []Input{
			Input{key: "a", params: []string{}, cmd: "b"},
		},
	},
	Result{data: []string{"a,b"},
		input: []Input{
			Input{failed: true, key: "a", params: []string{"c"}, cmd: "b"},
		},
	},
	Result{data: []string{"a,$b"},
		input: []Input{
			Input{failed: true, key: "a", params: []string{"c"}, cmd: "$b"},
		},
	},
	Result{data: []string{"a,$"},
		input: []Input{
			Input{failed: true, key: "a", params: []string{"c"}, cmd: "$"},
		},
	},

	Result{data: []string{"a[*],b"},
		input: []Input{
			Input{key: "a", params: []string{"c"}, cmd: "b"},
		},
	},
	Result{data: []string{"a[*],$"},
		input: []Input{
			Input{key: "a", params: []string{"c"}, cmd: "$"},
		},
	},
	Result{data: []string{"a[*],$b"},
		input: []Input{
			Input{key: "a", params: []string{"c"}, cmd: "$b"},
		},
	},
	Result{data: []string{"a[*],b$"},
		input: []Input{
			Input{key: "a", params: []string{"c"}, cmd: "b$"},
		},
	},
	Result{data: []string{"a[*],$$"},
		input: []Input{
			Input{key: "a", params: []string{"c"}, cmd: "$"},
		},
	},

	Result{data: []string{"a[*],$1$1$2$3$2$4$5$6$5$7$8$9"},
		input: []Input{
			Input{key: "a", params: []string{"1", "2", "3", "4", "5", "6", "7", "8", "9"}, cmd: "112324565789"},
		},
	},
	Result{data: []string{"a[*],$1$1$2$3$2$4$5$6$5$7$8$9"},
		input: []Input{
			Input{key: "a", params: []string{"foo"}, cmd: "foofoo"},
		},
	},
	Result{data: []string{"a[*],$1$1$2$3$2$4$5$6$5$7$8$9"},
		input: []Input{
			Input{key: "a", params: []string{"1a", "2a", "3a", "4a", "5a", "6a", "7a", "8a", "9a"}, cmd: "1a1a2a3a2a4a5a6a5a7a8a9a"},
		},
	},
	Result{data: []string{"a[*],$1$1$2$3$2$4$5$6$5$7$8$9"},
		input: []Input{
			Input{key: "a", params: []string{"1a", "2a", "3a", "4a", "5a", "6", "7a", "8a", "9a"}, cmd: "1a1a2a3a2a4a5a65a7a8a9a"},
		},
	},
	Result{data: []string{"a[*],echo $1"},
		input: []Input{
			Input{key: "a", params: []string{}, cmd: "echo "},
		},
	},
	Result{data: []string{"a[*],echo $1 foo"},
		input: []Input{
			Input{key: "a", params: []string{}, cmd: "echo  foo"},
		},
	},
	Result{data: []string{"a[*],echo foo"},
		input: []Input{
			Input{key: "a", params: []string{"foo"}, cmd: "echo foo"},
		},
	},
	Result{data: []string{"a[*],echo $1 foo"},
		input: []Input{
			Input{key: "a", params: []string{"foo"}, cmd: "echo foo foo"},
		},
	},
	Result{data: []string{"a[*],$1"},
		input: []Input{
			Input{key: "a", params: []string{"c"}, cmd: "c"},
		},
	},

	Result{data: []string{"a,echo \\'\"`*?[]{}~$!&;()<>|#@\n"},
		input: []Input{
			Input{key: "a", params: []string{}, cmd: "echo \\'\"`*?[]{}~$!&;()<>|#@\n"},
		},
	},
	Result{data: []string{"a[*],echo $1 \\'\"`*?[]{}~$!&;()<>|#@\n"},
		input: []Input{
			Input{key: "a", params: []string{"foo"}, cmd: "echo foo \\'\"`*?[]{}~$!&;()<>|#@\n"},
		},
	},
	Result{data: []string{"a[*],echo $1"},
		input: []Input{
			Input{failed: true, key: "a", params: []string{"\\'\"`*?[]{}~$!&;()<>|#@\n"}, cmd: ""},
		},
	},
	Result{data: []string{"a[*],echo $1"}, unsafeUserParameters: 1,
		input: []Input{
			Input{key: "a", params: []string{"\\'\"`*?[]{}~$!&;()<>|#@\n"}, cmd: "echo \\'\"`*?[]{}~$!&;()<>|#@\n"},
		},
	},
	Result{data: []string{"a[*],echo $0"},
		input: []Input{
			Input{key: "a", params: []string{}, cmd: "echo echo $0"},
		},
	},
	Result{data: []string{"a[*],echo $$$1"},
		input: []Input{
			Input{key: "a", params: []string{}, cmd: "echo $"},
		},
	},
}

func TestCmd(t *testing.T) {
	for i := 0; i < len(resultsCmd); i++ {
		t.Run(resultsCmd[i].data[0], func(t *testing.T) {
			plugin.Metrics = make(map[string]*plugin.Metric)

			if _, err := InitUserParameterPlugin(resultsCmd[i].data, resultsCmd[i].unsafeUserParameters, ""); err != nil {
				t.Errorf("Plugin init failed: %s", err)
			}

			for j := 0; j < len(resultsCmd[i].input); j++ {
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
						t.Errorf("cmd test %s failed: expected command: [%s] got: [%s]", resultsCmd[i].input[j].key, resultsCmd[i].input[j].cmd, cmd)
					}
				}
			}
		})
	}
}

func TestUnsafeCmd(t *testing.T) {

	t.Run("", func(t *testing.T) {
		plugin.Metrics = make(map[string]*plugin.Metric)

		if _, err := InitUserParameterPlugin([]string{"a[*],echo $1"}, 0, ""); err != nil {
			t.Errorf("Plugin init failed: %s", err)
		}

		for _, c := range notAllowedCharacters {
			_, err := userParameter.cmd("a", []string{string(c)})
			if err == nil {
				t.Errorf("Not allowed character is present")
			}
		}

		plugin.Metrics = make(map[string]*plugin.Metric)

		if _, err := InitUserParameterPlugin([]string{"a[*],echo $1"}, 1, ""); err != nil {
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
