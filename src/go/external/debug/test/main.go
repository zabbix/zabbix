/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

import (
	"fmt"

	"zabbix.com/external"
)

func main() {
	h, err := external.NewHandler(impl.Name())
	if err != nil {
		panic(fmt.Sprintf("failed to create external plugin handler %s", err.Error()))
	}
	impl.Logger = &h

	err = h.Execute()
	if err != nil {
		panic(fmt.Sprintf("failed to execute external plugin handler %s", err.Error()))
	}
}
