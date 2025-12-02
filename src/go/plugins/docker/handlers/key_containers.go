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

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

// container represents each container in the list returned by ListContainers.
type container struct {
	ID         string `json:"Id"` //nolint:tagliatelle
	Image      string
	Command    string
	Created    int64
	State      string
	Status     string
	Ports      []Port
	SizeRw     int64
	SizeRootFs int64
	Names      []string
	Mounts     []Mount
}

// Port is a type that represents a port mapping returned by the Docker API.
type Port struct {
	PrivatePort int64
	PublicPort  int64
	Type        string
	IP          string
}

// Mount represents a mount point for a container.
type Mount struct {
	Name        string
	Source      string
	Destination string
	Driver      string
	Mode        string
	RW          bool
	Propagation string
	Type        string
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
