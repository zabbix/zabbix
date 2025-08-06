package handlers

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

var metricsMeta = map[Key]MetricMeta{
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

type Key string

type Command string

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(data map[Command][]byte) (res any, err error)

type MetricMeta struct {
	Commands  []Command
	Arguments map[string]string
	handler   handlerFunc
}

// GetMetricMeta provides MetricMeta which has commands and arguments to construct request.
func GetMetricMeta(key Key) MetricMeta {
	return metricsMeta[key]
}

// Handle runs metric's handler.
func (m *MetricMeta) Handle(data map[Command][]byte) (res any, err error) {
	return m.handler(data)
}
