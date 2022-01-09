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

package comms

import (
	"zabbix.com/pkg/plugin"
)

const Version = "1.0"

const NonRequiredID = 0

const (
	Exporter = 1 << iota
	Configurator
	Runner
)

const (
	LogRequestType = iota + 1
	RegisterRequestType
	RegisterResponseType
	StartRequestType
	TerminateRequestType
	ExportRequestType
	ExportResponseType
	ConfigureRequestType
	ValidateRequestType
	ValidateResponseType
	PeriodRequestType
	PeriodResponseType
)

type request int

var toString = map[request]string{
	LogRequestType:       "Log Request",
	RegisterRequestType:  "Register Request",
	RegisterResponseType: "Register Response",
	StartRequestType:     "Start Request",
	TerminateRequestType: "Terminate Request",
	ExportRequestType:    "Export Request",
	ExportResponseType:   "Export Response",
	ConfigureRequestType: "Configure Request",
	ValidateRequestType:  "Validate Request",
	ValidateResponseType: "Validate Response",
	PeriodRequestType:    "Period Request",
	PeriodResponseType:   "Period Response",
}

func GetRequestName(reqType uint32) string {
	return toString[request(reqType)]
}

func ImplementsConfigurator(in uint32) bool {
	return in&Configurator != 0
}

func ImplementsExporter(in uint32) bool {
	return in&Exporter != 0
}

func ImplementsRunner(in uint32) bool {
	return in&Runner != 0
}

type Common struct {
	Id   uint32 `json:"id"`
	Type uint32 `json:"type"`
}

type LogRequest struct {
	Common
	Severity uint32 `json:"severity"`
	Message  string `json:"message"`
}

type RegisterRequest struct {
	Common
	Version string `json:"version"`
}

type RegisterResponse struct {
	Common
	Name       string   `json:"name"`
	Metrics    []string `json:"metrics,omitempty"`
	Interfaces uint32   `json:"interfaces,omitempty"`
	Error      string   `json:"error,omitempty"`
}

type ValidateRequest struct {
	Common
	PrivateOptions interface{} `json:"private_options,omitempty"`
}

type ValidateResponse struct {
	Common
	Error string `json:"error,omitempty"`
}

type StartRequest struct {
	Common
}

type TerminateRequest struct {
	Common
}

type ExportRequest struct {
	Common
	Key    string   `json:"key"`
	Params []string `json:"parameters,omitempty"`
}

type ExportResponse struct {
	Common
	Value interface{} `json:"value,omitempty"`
	Error string      `json:"error,omitempty"`
}

type ConfigureRequest struct {
	Common
	GlobalOptions  *plugin.GlobalOptions `json:"global_options"`
	PrivateOptions interface{}           `json:"private_options,omitempty"`
}
