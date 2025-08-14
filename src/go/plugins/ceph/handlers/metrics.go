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

*
 */
package handlers

// The constants below define the unique keys for each supported Ceph metric.
const (
	KeyDf            Key = "ceph.df.details"
	KeyOSD           Key = "ceph.osd.stats"
	KeyOSDDiscovery  Key = "ceph.osd.discovery"
	KeyOSDDump       Key = "ceph.osd.dump"
	KeyPing          Key = "ceph.ping"
	KeyPoolDiscovery Key = "ceph.pool.discovery"
	KeyStatus        Key = "ceph.status"
)

const (
	cmdDf               Command = "df"
	cmdPgDump           Command = "pg dump"
	cmdOSDCrushRuleDump Command = "osd crush rule dump"
	cmdOSDCrushTree     Command = "osd crush tree"
	cmdOSDDump          Command = "osd dump"
	cmdHealth           Command = "health"
	cmdStatus           Command = "status"
)

var metricsMeta = map[Key]MetricMeta{ //nolint:gochecknoglobals // used as a static const
	KeyDf: {
		Commands:  []Command{cmdDf},
		Arguments: map[string]string{"detail": "detail"},
		handler:   dfHandler,
	},
	KeyOSD: {
		Commands:  []Command{cmdPgDump},
		Arguments: nil,
		handler:   osdHandler,
	},
	KeyOSDDiscovery: {
		Commands:  []Command{cmdOSDCrushTree},
		Arguments: nil,
		handler:   osdDiscoveryHandler,
	},
	KeyOSDDump: {
		Commands:  []Command{cmdOSDDump},
		Arguments: nil,
		handler:   osdDumpHandler,
	},
	KeyPing: {
		Commands:  []Command{cmdHealth},
		Arguments: nil,
		handler:   pingHandler,
	},
	KeyPoolDiscovery: {
		Commands:  []Command{cmdOSDDump, cmdOSDCrushRuleDump},
		Arguments: nil,
		handler:   poolDiscoveryHandler,
	},
	KeyStatus: {
		Commands:  []Command{cmdStatus},
		Arguments: nil,
		handler:   statusHandler,
	},
}

// Key is a unique identifier for a specific metric.
type Key string

// Command represents a command to be executed, typically a Ceph CLI command.
type Command string

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(data map[Command][]byte) (res any, err error)

// MetricMeta holds the metadata required to collect a specific Ceph metric.
type MetricMeta struct {
	Commands  []Command
	Arguments map[string]string
	handler   handlerFunc
}

// GetMetricMeta provides MetricMeta which has commands and arguments to construct request.
func GetMetricMeta(key Key) *MetricMeta {
	meta, ok := metricsMeta[key]
	if !ok {
		return nil
	}

	return &meta
}

// Handle runs metric's handler.
func (m *MetricMeta) Handle(data map[Command][]byte) (any, error) {
	return m.handler(data)
}
