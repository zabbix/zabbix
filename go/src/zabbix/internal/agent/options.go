/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

import (
	"fmt"
	"unicode"
)

type AgentOptions struct {
	LogType              string   `conf:",optional,,console"`
	LogFile              string   `conf:",optional"`
	DebugLevel           int      `conf:",optional,0:5,3"`
	ServerActive         string   `conf:",optional"`
	RefreshActiveChecks  int      `conf:",optional,30:3600,120"`
	Timeout              int      `conf:",optional,1:30,3"`
	Hostname             string   `conf:",optional"`
	HostnameItem         string   `conf:",optional"`
	HostMetadata         string   `conf:",optional"`
	HostMetadataItem     string   `conf:",optional"`
	BufferSend           int      `conf:",optional,1:3600,5"`
	BufferSize           int      `conf:",optional,2:65535,100"`
	ListenIP             string   `conf:",optional"`
	ListenPort           int      `conf:",optional,1024:32767,10050"`
	MaxLinesPerSecond    int      `conf:",optional,1:1000,20"`
	UserParameter        []string `conf:",optional"`
	UnsafeUserParameters int      `conf:",optional,0:1,0"`
	LogRemoteCommands    int      `conf:",optional,0:1,0"`
	EnableRemoteCommands int      `conf:",optional,0:1,0"`
	ControlSocket        string   `conf:",optional"`
	Alias                []string `conf:",optional"`
	Plugins              map[string]map[string]string
}

var Options AgentOptions

func CutAfterN(s string, n int) (string, int) {
	var l int

	for i := range s {
		if i > n {
			s = s[:l]
			break
		}
		l = i
	}

	return s, l
}

func CheckHostname(s string) error {
	for i := 0; i < len(s); i++ {
		if s[i] == '.' || s[i] == ' ' || s[i] == '_' || s[i] == '-' ||
			(s[i] >= 'A' && s[i] <= 'Z') || (s[i] >= 'a' && s[i] <= 'z') || (s[i] >= '0' && s[i] <= '9') {
			continue
		}

		if unicode.IsPrint(rune(s[i])) {
			return fmt.Errorf("character \"%c\" is not allowed in host name", s[i])
		} else {
			return fmt.Errorf("character 0x%02x is not allowed in host name", s[i])
		}
	}

	return nil
}
