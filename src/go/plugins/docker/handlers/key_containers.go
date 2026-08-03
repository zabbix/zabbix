/*
** Copyright (C) 2001-2026 Zabbix SIA
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

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

// container represents each container in the list returned by ListContainers.
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions.
type container struct {
	ID         string   `json:"Id"`
	Image      string   `json:"Image"`
	Command    string   `json:"Command"`
	Created    int64    `json:"Created"`
	State      string   `json:"State"`
	Status     string   `json:"Status"`
	Ports      []port   `json:"Ports"`
	SizeRw     int64    `json:"SizeRw"`
	SizeRootFs int64    `json:"SizeRootFs"`
	Names      []string `json:"Names"`
	Mounts     []mount  `json:"Mounts"`
}

// port is a type that represents a port mapping returned by the Docker API.
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions
type port struct {
	PrivatePort int64  `json:"PrivatePort"`
	PublicPort  int64  `json:"PublicPort"`
	Type        string `json:"Type"`
	IP          string `json:"IP"`
}

// mount represents a mount point for a container.
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions
type mount struct {
	Name        string `json:"Name"`
	Source      string `json:"Source"`
	Destination string `json:"Destination"`
	Driver      string `json:"Driver"`
	Mode        string `json:"Mode"`
	RW          bool   `json:"RW"`
	Propagation string `json:"Propagation"`
	Type        string `json:"Type"`
}

func keyContainersHandler(client *http.Client, query string, _ ...string) (string, error) {
	var data []container

	body, err := queryDockerAPI(client, query)
	if err != nil {
		return "", err
	}

	err = json.Unmarshal(body, &data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	result, err := json.Marshal(data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return string(result), nil
}
