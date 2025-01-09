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

package cpu

import (
	"fmt"
	"unsafe"

	"golang.zabbix.com/agent2/pkg/pdh"
	"golang.zabbix.com/agent2/pkg/win32"
	"golang.zabbix.com/sdk/log"
)

type pdhCollector struct {
	log      log.Logger
	hQuery   win32.PDH_HQUERY
	hCpuUtil []win32.PDH_HCOUNTER
	hCpuLoad win32.PDH_HCOUNTER
	iter     uint64
}

func getNumaNodeCount() (count int) {
	size, err := win32.GetLogicalProcessorInformationEx(win32.RelationNumaNode, nil)
	if err != nil {
		return 1
	}

	b := make([]byte, size)
	size, err = win32.GetLogicalProcessorInformationEx(win32.RelationNumaNode, b)
	if err != nil {
		return 1
	}

	var sinfo *win32.SYSTEM_LOGICAL_PROCESSOR_INFORMATION_EX
	for i := uint32(0); i < size; i += sinfo.Size {
		sinfo = (*win32.SYSTEM_LOGICAL_PROCESSOR_INFORMATION_EX)(unsafe.Pointer(&b[i]))
		count++
	}
	return
}

// open function initializes PDH query/counters for cpu metric gathering
func (c *pdhCollector) open(numCpus int, numGroups int) {
	var err error
	if c.hQuery, err = win32.PdhOpenQuery(nil, 0); err != nil {
		c.log.Errf("cannot open performance monitor query for CPU statistics: %s", err)
		return
	}
	// add CPU load counter
	path := pdh.CounterPath(pdh.ObjectSystem, pdh.CounterProcessorQueue)
	if c.hCpuLoad, err = win32.PdhAddEnglishCounter(c.hQuery, path, 0); err != nil {
		c.log.Errf("cannot add performance counter for CPU load statistics: %s", err)
	}

	c.hCpuUtil = make([]win32.PDH_HCOUNTER, numCpus+1)
	cpe := pdh.CounterPathElements{
		ObjectName:    pdh.CounterName(pdh.ObjectProcessor),
		InstanceName:  "_Total",
		InstanceIndex: -1,
		CounterName:   pdh.CounterName(pdh.CounterProcessorTime),
	}
	// add total cpu utilization counter
	path, err = pdh.MakePath(&cpe)
	if err != nil {
		c.log.Errf("cannot make counter path for total CPU utilization: %s", err)
	}
	c.hCpuUtil[0], err = win32.PdhAddEnglishCounter(c.hQuery, path, 0)
	if err != nil {
		c.log.Errf("cannot add performance counter for total CPU utilization: %s", err)
	}

	if numCpus == 0 || numGroups == 0 {
		return
	}

	// add per cpu utilization counters

	cpe.ObjectName = pdh.CounterName(pdh.ObjectProcessorInfo)
	cpuPerGroup := numCpus / numGroups
	c.log.Debugf("cpu_groups = %d, cpus_per_group = %d, cpus = %d", numGroups, cpuPerGroup, numCpus)
	index := 1

	for g := 0; g < numGroups; g++ {
		for i := 0; i < cpuPerGroup; i++ {
			cpe.InstanceName = fmt.Sprintf("%d,%d", g, i)
			path, err = pdh.MakePath(&cpe)
			if err != nil {
				c.log.Errf("cannot make counter path for CPU#%s utilization: %s", cpe.InstanceName, err)
			}
			c.hCpuUtil[index], err = win32.PdhAddEnglishCounter(c.hQuery, path, 0)
			if err != nil {
				c.log.Errf("cannot add performance counter for CPU#%s utilization: %s", cpe.InstanceName, err)
			}
			index++
		}
	}
}

// close function closes opened PDH query
func (c *pdhCollector) close() {
	if c.hQuery != 0 {
		_ = win32.PdhCloseQuery(c.hQuery)
		c.hQuery = 0
		c.hCpuLoad = 0
		c.hCpuUtil = nil
	}
}

func (c *pdhCollector) collect() (ok bool, err error) {
	if c.hQuery == 0 {
		return
	}
	if err = win32.PdhCollectQueryData(c.hQuery); err != nil {
		return
	}
	// ignore first query result - no data will be gathered yet
	c.iter++
	return c.iter > 1, nil
}

// cpuLoad function returns collected CPU load counter- \Processor\Processor Queue Length
func (c *pdhCollector) cpuLoad() (value float64) {
	if c.hCpuLoad == 0 {
		return
	}
	pvalue, err := win32.PdhGetFormattedCounterValueDouble(c.hCpuLoad, 2)
	if err != nil {
		c.log.Debugf("cannot obtain CPU load counter value: %s", err)
	}
	if pvalue != nil {
		return *pvalue
	}
	return
}

// cpuLoad function returns collected CPU utilization for the specified CPU index (0 - total, 1 - first(0), ...) -
// \Processor\% Processor Time
func (c *pdhCollector) cpuUtil(cpuIndex int) (value float64) {
	if c.hCpuUtil[cpuIndex] == 0 {
		return
	}
	pvalue, err := win32.PdhGetFormattedCounterValueDouble(c.hCpuUtil[cpuIndex], 2)
	if err != nil {
		var suffix string
		if cpuIndex != 0 {
			suffix = fmt.Sprintf("#%d", cpuIndex-1)
		}
		c.log.Debugf("cannot obtain CPU%s utilization counter value: %s", suffix, err)
	}
	if pvalue != nil {
		return *pvalue
	}
	return
}

func newPdhCollector(log log.Logger) (c *pdhCollector) {
	return &pdhCollector{log: log}
}
