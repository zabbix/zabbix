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

package agent

type AgentOptions struct {
	LogType                  string   `conf:"optional,default=file"`
	LogFile                  string   `conf:"optional,default=c:\\zabbix_agent2.log"`
	LogFileSize              int      `conf:"optional,range=0:1024,default=1"`
	DebugLevel               int      `conf:"optional,range=0:5,default=3"`
	PidFile                  string   `conf:"optional"`
	ServerActive             string   `conf:"optional"`
	RefreshActiveChecks      int      `conf:"optional,nonempty,range=1:86400,default=5"`
	Timeout                  int      `conf:"optional,range=1:30,default=3"`
	Hostname                 string   `conf:"optional"`
	HostnameItem             string   `conf:"optional"`
	HostMetadata             string   `conf:"optional"`
	HostMetadataItem         string   `conf:"optional"`
	HostInterface            string   `conf:"optional"`
	HostInterfaceItem        string   `conf:"optional"`
	BufferSend               int      `conf:"optional,range=1:3600,default=5"`
	BufferSize               int      `conf:"optional,range=2:65535,default=1000"`
	EnablePersistentBuffer   int      `conf:"optional,nonempty,range=0:1,default=0"`
	PersistentBufferPeriod   int      `conf:"optional,range=60:31536000,default=3600"`
	PersistentBufferFile     string   `conf:"optional"`
	ListenIP                 string   `conf:"optional"`
	ListenPort               int      `conf:"optional,range=1024:32767,default=10050"`
	StatusPort               int      `conf:"optional,range=1024:32767"`
	SourceIP                 string   `conf:"optional"`
	Server                   string   `conf:"optional"`
	UserParameter            []string `conf:"optional"`
	UnsafeUserParameters     int      `conf:"optional,nonempty,range=0:1,default=0"`
	UserParameterDir         string   `conf:"optional"`
	ControlSocket            string   `conf:"optional"`
	Alias                    []string `conf:"optional"`
	PerfCounter              []string `conf:"optional"`
	PerfCounterEn            []string `conf:"optional"`
	TLSConnect               string   `conf:"optional,nonempty"`
	TLSAccept                string   `conf:"optional,nonempty"`
	TLSPSKIdentity           string   `conf:"optional,nonempty"`
	TLSPSKFile               string   `conf:"optional,nonempty"`
	TLSCAFile                string   `conf:"optional,nonempty"`
	TLSCRLFile               string   `conf:"optional,nonempty"`
	TLSCertFile              string   `conf:"optional,nonempty"`
	TLSKeyFile               string   `conf:"optional,nonempty"`
	TLSServerCertIssuer      string   `conf:"optional,nonempty"`
	TLSServerCertSubject     string   `conf:"optional,nonempty"`
	TLSCipherCert            string   `conf:"optional,nonempty"`
	TLSCipherCert13          string   `conf:"optional,nonempty"`
	TLSCipherPSK             string   `conf:"optional,nonempty"`
	TLSCipherPSK13           string   `conf:"optional,nonempty"`
	TLSCipherAll             string   `conf:"optional,nonempty"`
	TLSCipherAll13           string   `conf:"optional,nonempty"`
	ExternalPluginTimeout    int      `conf:"optional,name=PluginTimeout,range=1:30"`
	ExternalPluginsSocket    string   `conf:"optional,name=PluginSocket,default=\\\\.\\pipe\\agent.plugin.sock"`
	ForceActiveChecksOnStart int      `conf:"optional,nonempty,range=0:1,default=0"`
	HeartbeatFrequency       int      `conf:"optional,nonempty,range=0:3600,default=60"`

	AllowKey interface{} `conf:"optional"`
	DenyKey  interface{} `conf:"optional"`

	Plugins              map[string]interface{}   `conf:"optional"`
	PluginsSystemOptions map[string]SystemOptions `conf:"-"`
}

type SystemOptions struct {
	Path                     string `conf:"optional"`
	ForceActiveChecksOnStart *int   `conf:"optional"`
	Capacity                 int    `conf:"optional"`
}
