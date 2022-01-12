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

package ceph

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"

	"zabbix.com/pkg/log"
	"zabbix.com/pkg/zbxerr"
)

type cephResponse struct {
	Failed []struct {
		Outs string `json:"outs"`
	} `json:"failed"`
	Finished []struct {
		Outb string `json:"outb"`
	} `json:"finished"`
	HasFailed bool   `json:"has_failed"`
	Message   string `json:"message"`
}

// request makes an http request to Ceph RESTful API Module with a given command and extra parameters.
func request(ctx context.Context, client *http.Client, uri, cmd string, args map[string]string) ([]byte, error) {
	var resp cephResponse

	params := map[string]string{
		"prefix": cmd,
		"format": "json",
	}

	for k, v := range args {
		params[k] = v
	}

	requestBody, err := json.Marshal(params)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	req, err := http.NewRequestWithContext(ctx, "POST", uri+"/request?wait=1", bytes.NewBuffer(requestBody))
	if err != nil {
		return nil, zbxerr.New(err.Error())
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Add("User-Agent", "zbx_monitor")

	res, err := client.Do(req)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}
	defer res.Body.Close()

	err = json.NewDecoder(res.Body).Decode(&resp)
	if err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	if resp.HasFailed {
		if len(resp.Failed) == 0 {
			return nil, zbxerr.ErrorCannotParseResult
		}

		return nil, zbxerr.New(resp.Failed[0].Outs)
	}

	if len(resp.Message) > 0 {
		return nil, zbxerr.New(resp.Message)
	}

	log.Debugf("Response = %+v", resp)

	if len(resp.Finished) == 0 {
		return nil, zbxerr.ErrorEmptyResult
	}

	return []byte(resp.Finished[0].Outb), nil
}

type response struct {
	cmd  string
	data []byte
	err  error
}

// asyncRequest makes asynchronous https requests to Ceph RESTful API Module for each metric's command and sends
// results to the channel.
func asyncRequest(ctx context.Context, cancel context.CancelFunc, client *http.Client,
	uri string, meta metricMeta) <-chan *response {
	ch := make(chan *response, len(meta.commands))

	for _, cmd := range meta.commands {
		go func(cmd string) {
			data, err := request(ctx, client, uri, cmd, meta.args)
			if err != nil {
				cancel()
				ch <- &response{cmd, nil, err}
			}
			ch <- &response{cmd, data, err}
		}(string(cmd))
	}

	return ch
}
