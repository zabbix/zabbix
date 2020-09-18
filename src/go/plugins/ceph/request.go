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

func request(ctx context.Context, client *http.Client, uri, cmd string, extraParams map[string]string) ([]byte, error) {
	var resp cephResponse

	params := map[string]string{
		"prefix": cmd,
		"format": "json",
	}

	for k, v := range extraParams {
		params[k] = v
	}

	requestBody, err := json.Marshal(params)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	req, err := http.NewRequestWithContext(ctx, "POST", uri, bytes.NewBuffer(requestBody))
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

func asyncRequest(ctx context.Context, cancel context.CancelFunc, client *http.Client, uri string, m metric) <-chan *response {
	ch := make(chan *response, len(m.commands))

	for _, cmd := range m.commands {
		go func(cmd string) {
			// TODO: add context
			data, err := request(ctx, client, uri, cmd, m.params)
			if err != nil {
				cancel()
				ch <- &response{cmd, nil, err}
			}
			ch <- &response{cmd, data, err}
		}(string(cmd))
	}

	return ch
}
