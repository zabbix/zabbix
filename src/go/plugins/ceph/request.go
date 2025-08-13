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

package ceph

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"

	"golang.zabbix.com/agent2/plugins/ceph/handlers"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxerr"
)

type cephResponse struct {
	Failed []struct { //nolint:revive //this is ceph reply struct
		Outs string `json:"outs"`
	} `json:"failed"`
	Finished []struct { //nolint:revive //this is ceph reply struct
		Outb string `json:"outb"`
	} `json:"finished"`
	HasFailed bool   `json:"has_failed"`
	Message   string `json:"message"`
}

type response struct {
	command handlers.Command
	data    []byte
	err     error
}

// request makes an http request to Ceph RESTful API Module with a given command and extra parameters.
func request(
	ctx context.Context,
	client *http.Client,
	uri string,
	cmd handlers.Command,
	args map[string]string,
) ([]byte, error) {
	var resp cephResponse

	params := map[string]string{
		"prefix": string(cmd),
		"format": "json",
	}

	for k, v := range args {
		params[k] = v
	}

	requestBody, err := json.Marshal(params)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, uri+"/request?wait=1", bytes.NewBuffer(requestBody))
	if err != nil {
		return nil, errs.New(err.Error())
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Add("User-Agent", "zbx_monitor")

	res, err := client.Do(req)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}
	defer res.Body.Close() //nolint:errcheck // this is a defer close function.

	err = json.NewDecoder(res.Body).Decode(&resp)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	if resp.HasFailed {
		if len(resp.Failed) == 0 {
			return nil, zbxerr.ErrorCannotParseResult
		}

		return nil, errs.New(resp.Failed[0].Outs)
	}

	if resp.Message != "" {
		return nil, errs.New(resp.Message)
	}

	log.Debugf("Response = %+v", resp)

	if len(resp.Finished) == 0 {
		return nil, zbxerr.ErrorEmptyResult
	}

	return []byte(resp.Finished[0].Outb), nil
}

// asyncRequest makes asynchronous https requests to Ceph RESTful API Module for each metric's command and sends
// results to the channel.
func asyncRequest(ctx context.Context, cancel context.CancelFunc, client *http.Client,
	uri string, meta handlers.MetricMeta) <-chan *response {
	ch := make(chan *response, len(meta.Commands))

	for _, cmd := range meta.Commands {
		go func(cmd handlers.Command) {
			data, err := request(ctx, client, uri, cmd, meta.Arguments)
			if err != nil {
				cancel()

				ch <- &response{cmd, nil, err}
			}

			ch <- &response{cmd, data, err}
		}(cmd)
	}

	return ch
}
