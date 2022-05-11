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
