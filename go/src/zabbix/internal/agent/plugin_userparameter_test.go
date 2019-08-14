package agent

import (
	"testing"
	"zabbix/internal/plugin"
)

type Result struct {
	data   []string
	failed bool
}

var results = []Result{
	Result{data: []string{"system.test,who | wc -l", "vfs.dir.size[*],dir=\"$1\"; du -s -B 1 \"${dir:-/tmp}\" | cut -f1",
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
