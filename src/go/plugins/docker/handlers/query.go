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

*
 */
package handlers

import (
	"encoding/json"
	"io/ioutil"
	"net/http"
	"path"

	"golang.zabbix.com/sdk/zbxerr"
)

const dockerVersion = "1.28"

// errorMessage represents the API error.
type errorMessage struct {
	Message string `json:"message"`
}

func queryDockerAPI(client *http.Client, query string) (result []byte, err error) {
	resp, err := client.Get("http://" + path.Join(dockerVersion, query))
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}
	defer resp.Body.Close()

	body, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if resp.StatusCode != http.StatusOK {
		var apiErr errorMessage

		if err = json.Unmarshal(body, &apiErr); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		return nil, zbxerr.New(apiErr.Message)
	}

	return body, nil
}
