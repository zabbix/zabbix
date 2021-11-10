/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

package shared

import (
	"encoding/binary"
	"encoding/json"
	"fmt"
	"net"

	"zabbix.com/pkg/plugin"
)

const JSONType = uint32(1)

func CreateRegisterRequest() RegisterRequest {
	return RegisterRequest{Common{GetID(), RegisterRequestType}, Version}
}

func CreateTerminateRequest() TerminateRequest {
	return TerminateRequest{Common{GetID(), TerminateRequestType}}
}

func CreateExportRequest(key string, params []string) ExportRequest {
	return ExportRequest{Common{GetID(), ExportRequestType}, key, params}
}

func CreateCollectRequest() CollectRequest {
	return CollectRequest{Common{GetID(), CollectorRequestType}}
}

func CreatePeriodRequest() PeriodRequest {
	return PeriodRequest{Common{GetID(), PeriodRequestType}}
}

func CreateConfigurateRequest(pluginOption *plugin.GlobalOptions, privateOptions interface{}) ConfigureRequest {
	return ConfigureRequest{Common{GetID(), ConfigureRequestType}, pluginOption, privateOptions}
}

func CreateValidateRequest(options interface{}) ValidateRequest {
	return ValidateRequest{Common{GetID(), ValidateRequestType}, options}
}

func CreateLogRequest(severity uint32, message string) LogRequest {
	return LogRequest{Common{GetID(), LogRequestType}, severity, message}
}

func CreateEmptyRegisterResponse(id uint32) RegisterResponse {
	return RegisterResponse{Common{id, RegisterResponseType}, "", []string{}, 0, ""}
}

func CreateEmptyExportResponse(id uint32) ExportResponse {
	return ExportResponse{Common{id, ExportResponseType}, nil, ""}
}

func CreateEmptyValidateResponse(id uint32) ValidateResponse {
	return ValidateResponse{Common{id, ValidateResponseType}, ""}
}

func CreateEmptyCollectResponse(id uint32) CollectResponse {
	return CollectResponse{Common{id, CollectorResponseType}, ""}
}

func CreatePeriodResponse(id uint32, period int) PeriodResponse {
	return PeriodResponse{Common{id, PeriodResponseType}, period}
}

func Read(conn net.Conn) (dataType uint32, requestData []byte, err error) {
	reqByteType := make([]byte, 4)
	reqByteLen := make([]byte, 4)
	_, err = conn.Read(reqByteType)
	if err != nil {
		return
	}

	if JSONType != binary.LittleEndian.Uint32(reqByteType) {
		err = fmt.Errorf("only json data type (%d) supported", JSONType)
		return
	}

	_, err = conn.Read(reqByteLen)
	if err != nil {
		return
	}

	reqLen := int32(binary.LittleEndian.Uint32(reqByteLen))
	data := make([]byte, reqLen)

	_, err = conn.Read(data)
	if err != nil {
		return
	}

	var c Common
	if err := json.Unmarshal(data, &c); err != nil {
		return 0, nil, err
	}

	return c.Type, data, nil
}

func Write(conn net.Conn, in interface{}) (err error) {
	reqBytes, err := json.Marshal(in)
	if err != nil {
		return
	}

	dataType := make([]byte, 4)
	binary.LittleEndian.PutUint32(dataType, uint32(JSONType))

	dataLen := make([]byte, 4)
	binary.LittleEndian.PutUint32(dataLen, uint32(len(reqBytes)))

	var data []byte
	data = append(data, dataType...)
	data = append(data, dataLen...)
	data = append(data, reqBytes...)

	if _, err = conn.Write(data); err != nil {
		return
	}

	return
}
