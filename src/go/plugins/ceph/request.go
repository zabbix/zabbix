package ceph

import (
	"bytes"
	"encoding/json"
	"net/http"

	"zabbix.com/pkg/log"
	"zabbix.com/pkg/zbxerr"
)

type Response struct {
	Failed []struct {
		Outs string `json:"outs"`
	} `json:"failed"`
	Finished []struct {
		Outb string `json:"outb"`
	} `json:"finished"`
	HasFailed bool   `json:"has_failed"`
	Message   string `json:"message"`
}

func request(client *http.Client, uri, cmd string, extraParams map[string]string) ([]byte, error) {
	var resp Response

	params := map[string]string{
		"prefix": cmd,
		"format": "json",
	}

	if extraParams != nil {
		for k, v := range extraParams {
			params[k] = v
		}
	}

	requestBody, err := json.Marshal(params)
	if err != nil {
		return nil, zbxerr.New(err.Error())
	}

	req, err := http.NewRequest("POST", uri, bytes.NewBuffer(requestBody))
	if err != nil {
		return nil, zbxerr.New(err.Error())
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Add("User-Agent", "zbx_monitor")

	res, err := client.Do(req)
	if err != nil {
		return nil, zbxerr.New(err.Error())
	}
	defer res.Body.Close()

	err = json.NewDecoder(res.Body).Decode(&resp)
	if err != nil {
		return nil, zbxerr.New(err.Error())
	}

	if resp.HasFailed == true {
		if len(resp.Failed) == 0 {
			return nil, zbxerr.ErrorCannotParseResult
		}

		return nil, zbxerr.New(resp.Failed[0].Outs)
	}

	if len(resp.Message) > 0 {
		return nil, zbxerr.New(resp.Message)
	}

	log.Infof("Response = %+v", resp)

	if len(resp.Finished) == 0 {
		return nil, zbxerr.ErrorEmptyResult
	}

	return []byte(resp.Finished[0].Outb), nil
}
