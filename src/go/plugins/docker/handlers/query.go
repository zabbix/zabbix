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
	"context"
	"encoding/json"
	"io"
	"net/http"
	"path"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

const dockerVersion = "1.28"

// errorMessage represents the API error.
type errorMessage struct {
	Message string `json:"message"`
}

func queryDockerAPI(client *http.Client, query string) ([]byte, error) {
	req, err := http.NewRequestWithContext(
		context.Background(),
		http.MethodGet,
		"http://"+path.Join(dockerVersion, query),
		http.NoBody,
	)

	if err != nil {
		return nil, errs.Wrap(err, "failed to create request")
	}

	resp, err := client.Do(req)
	if err != nil {
		return nil, errs.Wrap(err, "failed to do request")
	}
	defer resp.Body.Close() //nolint:errcheck // typical defer close.

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	if resp.StatusCode != http.StatusOK {
		var apiErr errorMessage

		err = json.Unmarshal(body, &apiErr)
		if err != nil {
			return nil, errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
		}

		return nil, errs.New(apiErr.Message)
	}

	return body, nil
}
