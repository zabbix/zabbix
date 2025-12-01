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

import (
	"encoding/json"
	"net/http"

	"golang.zabbix.com/sdk/zbxerr"
)

// DiskUsage contains response of Engine API:
// GET "/system/df"
type DiskUsage struct {
	LayersSize int64
	Images     []*Image
	Containers []*Container
	Volumes    []*Volume
}

// Volume volume
type Volume struct {
	CreatedAt  unixTime
	Mountpoint string
	Name       string
	UsageData  *VolumeUsageData
}

// VolumeUsageData Usage details about the volume.
type VolumeUsageData struct {
	RefCount int64
	Size     int64
}

func keyDataUsageHandler(client *http.Client, query string, _ ...string) (string, error) {
	var data DiskUsage

	body, err := queryDockerAPI(client, query)
	if err != nil {
		return "", err
	}

	if err = json.Unmarshal(body, &data); err != nil {
		return "", zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	result, err := json.Marshal(data)
	if err != nil {
		return "", zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(result), nil

}
