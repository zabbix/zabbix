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

package smart

import "golang.zabbix.com/sdk/metric"

const (
	typeParameterName     = "type"
	pathParameterName     = "path"
	raidTypeParameterName = "raid"
)

//nolint:gochecknoglobals // global constants.
var (
	searchType = metric.NewParam(
		typeParameterName, "type to search the smart device by",
	).WithDefault(
		"name",
	).WithValidator(
		metric.SetValidator{Set: []string{"name", "id"}, CaseInsensitive: false},
	)

	path     = metric.NewParam(pathParameterName, "path by which to search device")
	raidType = metric.NewParam(raidTypeParameterName, "type to search the smart device by")
)
