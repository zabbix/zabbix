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

package handlers

const (
	cmdDf               Command = "df"
	cmdPgDump           Command = "pg dump"
	cmdOSDCrushRuleDump Command = "osd crush rule dump"
	cmdOSDCrushTree     Command = "osd crush tree"
	cmdOSDDump          Command = "osd dump"
	cmdHealth           Command = "health"
	cmdStatus           Command = "status"
)

// Command represents a command to be executed, typically a Ceph CLI command.
type Command string
