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

type AgentOptions struct {
	LogType             string `conf:",,,console"`
	LogFile             string `conf:",optional"`
	DebugLevel          int    `conf:",,0:5,3"`
	ServerActive        string `conf:",optional"`
	RefreshActiveChecks int    `conf:",,30:3600,120"`
	Timeout             int    `conf:",,1-30,3"`
	Hostname            string
	ListenPort          int `conf:",,1024:32767,10050"`
	Plugins             map[string]map[string]string
}

var Options AgentOptions
