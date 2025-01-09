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

package main

func copyrightMessageMQTT() string {
	return "\nWe use the library Eclipse Paho (eclipse/paho.mqtt.golang), which is\n" +
		"distributed under the terms of the Eclipse Distribution License 1.0 (The 3-Clause BSD License)\n" +
		"available at https://www.eclipse.org/org/documents/edl-v10.php\n"
}

func copyrightMessageModbus() string {
	return "\nWe use the library go-modbus (goburrow/modbus), which is\n" +
		"distributed under the terms of the 3-Clause BSD License\n" +
		"available at https://github.com/goburrow/modbus/blob/master/LICENSE\n"
}
