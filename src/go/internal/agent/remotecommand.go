package agent

type RemoteCommand struct {
	Id      uint64 `json:"id"`
	Command string `json:"command"`
	Wait    int    `json:"wait"`
}
