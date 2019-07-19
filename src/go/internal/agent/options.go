package agent

type AgentOptions struct {
	LogType    string `conf:",,,console"`
	LogFile    string `conf:",optional"`
	DebugLevel int    `conf:",,0:5,3"`
}

var Options AgentOptions
