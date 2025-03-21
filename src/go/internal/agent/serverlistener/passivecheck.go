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

package serverlistener

import (
	"encoding/json"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/scheduler"
	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/agent2/pkg/zbxcomms"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxnet"
)

const notsupported = "ZBX_NOTSUPPORTED"

type passiveCheckRequestData struct {
	Key     string `json:"key"`
	Timeout any    `json:"timeout"`
}

type passiveChecksRequest struct {
	Request string                    `json:"request"`
	Data    []passiveCheckRequestData `json:"data"`
}

type passiveChecksResponseData struct {
	Value *string `json:"value"`
}

type passiveChecksErrorResponseData struct {
	Error *string `json:"error"`
}

type passiveChecksResponse struct {
	Version string  `json:"version"`
	Variant int     `json:"variant"`
	Data    []any   `json:"data,omitempty"`
	Error   *string `json:"error,omitempty"`
}

func formatError(msg string) []byte {
	data := make([]byte, 0, len(notsupported)+len(msg)+1)
	data = append(data, notsupported...)
	data = append(data, 0)
	data = append(data, msg...)

	return data
}

func handleConnection(
	sched scheduler.Scheduler,
	conn zbxcomms.ConnectionInterface,
	allowedPeers *zbxnet.AllowedPeers,
	agentOptions *agent.AgentOptions,
) {
	defer conn.Close() //nolint:errcheck

	if !isAllowedConnection(conn.RemoteIP(), allowedPeers) {
		log.Warningf(
			"connection from %q rejected, allowed hosts: %q",
			conn.RemoteIP(),
			agentOptions.Server,
		)

		return
	}

	rawRequest, err := conn.Read()
	if err != nil {
		log.Warningf("failed to read request from %s: %s", conn.RemoteIP(), err.Error())

		return
	}

	log.Debugf(
		"received passive check request from %q: %q",
		string(rawRequest),
		conn.RemoteIP(),
	)

	if json.Valid(rawRequest) {
		processJSONRequest(conn, sched, rawRequest)
	} else {
		processPlainTextRequest(conn, sched, string(rawRequest))
	}
}

func parsePassiveCheckJSONRequest(rawRequest []byte) (string, time.Duration, error) {
	var request passiveChecksRequest

	err := json.Unmarshal(rawRequest, &request)
	if err != nil {
		return "", 0, errs.New(`failed to unmarshall json request into passiveChecksRequest`)
	}

	if len(request.Data) == 0 {
		return "", 0, errs.New(`received empty "data" tag`)
	}

	if request.Request != "passive checks" {
		return "", 0, errs.Errorf("unknown request type %q", request.Request)
	}

	timeout, err := scheduler.ParseItemTimeoutAny(request.Data[0].Timeout)
	if err != nil {
		return "", 0, errs.Wrap(err, "failed to parse passive check timeout")
	}

	return request.Data[0].Key, time.Duration(timeout) * time.Second, nil
}

func formatCheckDataPayload(checkResult string, isJSON bool) ([]byte, error) {
	if !isJSON {
		return []byte(checkResult), nil
	}

	response := passiveChecksResponse{
		Version: version.Long(),
		Variant: agent.Variant,
		Data: []any{
			passiveChecksResponseData{
				Value: &checkResult,
			},
		},
	}

	out, err := json.Marshal(response)
	if err != nil {
		return nil, errs.New("failed to marshall JSON")
	}

	return out, nil
}

func formatCheckErrorPayload(errText string, isJSON bool) ([]byte, error) {
	if !isJSON {
		return formatError(errText), nil
	}

	response := passiveChecksResponse{
		Version: version.Long(),
		Variant: agent.Variant,
		Data: []any{
			passiveChecksErrorResponseData{
				Error: &errText,
			},
		},
	}

	out, err := json.Marshal(response)
	if err != nil {
		return nil, errs.New("failed to marshall JSON")
	}

	return out, nil
}

func formatJSONParsingError(errText string) ([]byte, error) {
	response := passiveChecksResponse{
		Version: version.Long(),
		Variant: agent.Variant,
		Error:   &errText,
	}

	out, err := json.Marshal(response)
	if err != nil {
		return nil, errs.New("failed to marshall JSON")
	}

	return out, nil
}

func processPlainTextRequest(conn zbxcomms.ConnectionInterface, sched scheduler.Scheduler, key string) {
	result, err := sched.PerformTask(key, time.Minute, agent.PassiveChecksClientID)
	if err != nil {
		sendTaskErrorResponse(conn, err.Error(), false)

		return
	}

	if result == nil {
		log.Debugf("got nil value, skipping sending of response")

		return
	}

	err = conn.Write([]byte(*result))
	if err != nil {
		log.Debugf("could not send response to server '%s': %s", conn.RemoteIP(), err.Error())
	}
}

func processJSONRequest(conn zbxcomms.ConnectionInterface, sched scheduler.Scheduler, rawRequest []byte) {
	key, timeout, err := parsePassiveCheckJSONRequest(rawRequest)
	if err != nil {
		sendJSONParsingErrorResponse(conn, err.Error())

		return
	}

	result, err := sched.PerformTask(key, timeout, agent.PassiveChecksClientID)
	if err != nil {
		sendTaskErrorResponse(conn, err.Error(), true)

		return
	}

	if result == nil {
		log.Debugf("got nil value, skipping sending of response")

		return
	}

	payload, err := formatCheckDataPayload(*result, true)
	if err != nil {
		log.Debugf("could not format JSON response: %s", err.Error())

		return
	}

	err = conn.Write(payload)
	if err != nil {
		log.Debugf("could not send response to server '%s': %s", conn.RemoteIP(), err.Error())
	}
}

func sendJSONParsingErrorResponse(conn zbxcomms.ConnectionInterface, errText string) {
	payload, err := formatJSONParsingError(errText)
	if err != nil {
		log.Debugf("could not format parse error response: %s", err.Error())

		return
	}

	if wErr := conn.Write(payload); wErr != nil {
		log.Debugf("could not send response to server '%s': %s", conn.RemoteIP(), wErr.Error())
	}
}

func sendTaskErrorResponse(conn zbxcomms.ConnectionInterface, errText string, isJSON bool) {
	payload, err := formatCheckErrorPayload(errText, isJSON)
	if err != nil {
		log.Debugf("could not format error response: %s", err.Error())

		return
	}

	err = conn.Write(payload)
	if err != nil {
		log.Debugf("could not send response to server '%s': %s", conn.RemoteIP(), err.Error())
	}
}
