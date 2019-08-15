package agent

import (
	"testing"
	"zabbix/internal/plugin"
)

type Input struct {
	key    string
	params []string
	cmd    string
}

type Result struct {
	data   []string
	failed bool
	input  []Input
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

var resultsCmd = []Result{
	Result{data: []string{"system.test,who | wc -l",
		"vfs.dir.size[*],dir=\"$1\"; du -s -B 1 \"${dir:-/tmp}\" | cut -f1",
		"proc.cpu[*],proc=\"$1\"; ps -o pcpu= -C \"${proc:-zabbix_agentd}\" | awk '{sum += $$1} END {print sum}",
		"unix_mail.queue,mailq | grep -v \"Mail queue is empty\" | grep -c '^[0-9A-Z]",
		"vfs.partitions.discovery.linux,for partition in $(awk 'NR > 2 {print $4}' /proc/partitions); do partitionlist=\"$partitionlist,\"'{\"{#PARTITION}\":\"'$partition'\"}'; done; echo '{\"data\":['${partitionlist#,}']}",
		"vfs.partitions.discovery.solaris,/somewhere/solaris_partitions.sh"},
		input: []Input{
			Input{key: "system.test", params: []string{}, cmd: "who | wc -l"},
			Input{key: "vfs.dir.size", params: []string{"/tmp"}, cmd: "vfs.dir.size[*],dir=\"/tmp\"; du -s -B 1 \"${dir:-/tmp}\" | cut -f1"},
			Input{key: "proc.cpu", params: []string{"foo"}, cmd: "proc=\"foo\"; ps -o pcpu= -C \"${proc:-zabbix_agentd}\" | awk '{sum += $foo} END {print sum}"},
			Input{key: "unix_mail.queue", params: []string{}, cmd: "mailq | grep -v \"Mail queue is empty\" | grep -c '^[0-9A-Z]"},
			Input{key: "vfs.partitions.discovery.linux", params: []string{}, cmd: "for partition in $(awk 'NR > 2 {print $4}' /proc/partitions); do partitionlist=\"$partitionlist,\"'{\"{#PARTITION}\":\"'$partition'\"}'; done; echo '{\"data\":['${partitionlist#,}']}"},
			Input{key: "vfs.partitions.discovery.solaris", params: []string{}, cmd: "/somewhere/solaris_partitions.sh"},
		},
	},
}

func TestUserParameterPlugin(t *testing.T) {
	for i := 0; i < len(results); i++ {
		t.Run(results[i].data[0], func(t *testing.T) {
			plugin.Metrics = make(map[string]*plugin.Metric)

			if err := InitUserParameterPlugin(results[i].data); err != nil {
				if !results[i].failed {
					t.Errorf("Expected success while got error %s", err)
				}
			} else if results[i].failed {
				t.Errorf("Expected error while got success")
			}
		})
	}
}

func TestCmd(t *testing.T) {
	for i := 0; i < len(resultsCmd); i++ {
		t.Run(resultsCmd[i].data[0], func(t *testing.T) {
			plugin.Metrics = make(map[string]*plugin.Metric)

			if err := InitUserParameterPlugin(resultsCmd[i].data); err != nil {
				t.Errorf("Plugin init failed: %s", err)
			}

			for j := 0; j < len(resultsCmd[i].input); j++ {
				cmd, err := userParameter.cmd(resultsCmd[i].input[j].key, resultsCmd[i].input[j].params)
				if err != nil {
					t.Errorf("cmd test %s failed %s", resultsCmd[i].input[j].key, err)
				} else {
					if resultsCmd[i].input[j].cmd != cmd {
						t.Errorf("cmd test %s failed: command mismatch", resultsCmd[i].input[j].key)
					}
				}
			}
		})
	}
}
