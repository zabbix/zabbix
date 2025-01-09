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
	"fmt"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/scheduler"
	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/sdk/log"
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

type passiveCheck struct {
	conn      *passiveConnection
	scheduler scheduler.Scheduler
}

func (pc *passiveCheck) formatError(msg string) (data []byte) {
	data = make([]byte, len(notsupported)+len(msg)+1)
	copy(data, notsupported)
	copy(data[len(notsupported)+1:], msg)
	return
}

// handleCheckJSON handles json formatted passive check request.
// False is returned if the json parsing failed and request must
// be treated as plain text format request.
func (pc *passiveCheck) handleCheckJSON(data []byte) (errJson error) {
	var request passiveChecksRequest
	var timeout int
	var err error

	errJson = json.Unmarshal(data, &request)
	if errJson != nil {
		return errJson
	}

	if len(request.Data) == 0 {
		err = fmt.Errorf("received empty \"data\" tag")
	} else if request.Request != "passive checks" {
		err = fmt.Errorf("unknown request \"%s\"", request.Request)
	}

	var response passiveChecksResponse

	if err != nil {
		errString := err.Error()
		response = passiveChecksResponse{
			Version: version.Long(),
			Variant: agent.Variant,
			Error:   &errString,
		}
	} else {
		var value *string

		if timeout, err = scheduler.ParseItemTimeoutAny(request.Data[0].Timeout); err == nil {
			// direct passive check timeout is handled by the scheduler
			value, err = pc.scheduler.PerformTask(request.Data[0].Key, time.Second*time.Duration(timeout), agent.PassiveChecksClientID)
		}

		if err != nil {
			errString := err.Error()
			response = passiveChecksResponse{
				Version: version.Long(),
				Variant: agent.Variant,
				Data:    []any{passiveChecksErrorResponseData{Error: &errString}},
			}
		} else {
			response = passiveChecksResponse{
				Version: version.Long(),
				Variant: agent.Variant,
				Data:    []any{passiveChecksResponseData{Value: value}},
			}
		}
	}

	out, err := json.Marshal(response)
	if err == nil {
		log.Debugf("sending passive check response: '%s' to '%s'", string(out), pc.conn.Address())
		_, err = pc.conn.Write(out)
	}

	if err != nil {
		log.Debugf("could not send response to server '%s': %s", pc.conn.Address(), err.Error())
	}

	return nil
}

func (pc *passiveCheck) handleCheck(data []byte) {
	// the timeout is one minute to allow see any timeout problem with passive checks
	const timeoutForSinglePassiveChecks = time.Minute
	var checkTimeout time.Duration

	err := pc.handleCheckJSON(data)
	if err == nil {
		return
	}

	checkTimeout = timeoutForSinglePassiveChecks

	// direct passive check timeout is handled by the scheduler
	taskResult, err := pc.scheduler.PerformTask(string(data), checkTimeout, agent.PassiveChecksClientID)

	if err != nil {
		log.Debugf("sending passive check response: %s: '%s' to '%s'", notsupported, err.Error(), pc.conn.Address())
		_, err = pc.conn.Write(pc.formatError(err.Error()))
	} else if taskResult != nil {
		log.Debugf("sending passive check response: '%s' to '%s'", *taskResult, pc.conn.Address())
		_, err = pc.conn.Write([]byte(*taskResult))
	} else {
		log.Debugf("got nil value, skipping sending of response")
	}

	if err != nil {
		log.Debugf("could not send response to server '%s': %s", pc.conn.Address(), err.Error())
	}
}
