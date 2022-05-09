/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package agent

type AgentOptions struct {
	LogType                  string   `conf:"optional,default=file"`
	LogFile                  string   `conf:"optional,default=c:\\zabbix_agent2.log"`
	LogFileSize              int      `conf:"optional,range=0:1024,default=1"`
	DebugLevel               int      `conf:"optional,range=0:5,default=3"`
	PidFile                  string   `conf:"optional"`
	ServerActive             string   `conf:"optional"`
	RefreshActiveChecks      int      `conf:"optional,range=30:3600,default=120"`
	Timeout                  int      `conf:"optional,range=1:30,default=3"`
	Hostname                 string   `conf:"optional"`
	HostnameItem             string   `conf:"optional"`
	HostMetadata             string   `conf:"optional"`
	HostMetadataItem         string   `conf:"optional"`
	HostInterface            string   `conf:"optional"`
	HostInterfaceItem        string   `conf:"optional"`
	BufferSend               int      `conf:"optional,range=1:3600,default=5"`
	BufferSize               int      `conf:"optional,range=2:65535,default=100"`
	EnablePersistentBuffer   int      `conf:"optional,range=0:1,default=0"`
	PersistentBufferPeriod   int      `conf:"optional,range=60:31536000,default=3600"`
	PersistentBufferFile     string   `conf:"optional"`
	ListenIP                 string   `conf:"optional"`
	ListenPort               int      `conf:"optional,range=1024:32767,default=10050"`
	StatusPort               int      `conf:"optional,range=1024:32767"`
	SourceIP                 string   `conf:"optional"`
	Server                   string   `conf:"optional"`
	UserParameter            []string `conf:"optional"`
	UnsafeUserParameters     int      `conf:"optional,range=0:1,default=0"`
	UserParameterDir         string   `conf:"optional"`
	ControlSocket            string   `conf:"optional"`
	Alias                    []string `conf:"optional"`
	PerfCounter              []string `conf:"optional"`
	PerfCounterEn            []string `conf:"optional"`
	TLSConnect               string   `conf:"optional"`
	TLSAccept                string   `conf:"optional"`
	TLSPSKIdentity           string   `conf:"optional"`
	TLSPSKFile               string   `conf:"optional"`
	TLSCAFile                string   `conf:"optional"`
	TLSCRLFile               string   `conf:"optional"`
	TLSCertFile              string   `conf:"optional"`
	TLSKeyFile               string   `conf:"optional"`
	TLSServerCertIssuer      string   `conf:"optional"`
	TLSServerCertSubject     string   `conf:"optional"`
	ExternalPlugins          []string `conf:"optional,name=PluginPath"`
	ExternalPluginTimeout    int      `conf:"optional,name=PluginTimeout,range=1:30"`
	ExternalPluginsSocket    string   `conf:"optional,name=PluginSocket,default=\\\\.\\pipe\\agent.plugin.sock"`
	ForceActiveChecksOnStart int      `conf:"optional,range=0:1,default=0"`
	HeartbeatFrequency       int      `conf:"optional,range=0:3600,default=60"`

	AllowKey interface{} `conf:"optional"`
	DenyKey  interface{} `conf:"optional"`

	Plugins map[string]interface{} `conf:"optional"`
}
