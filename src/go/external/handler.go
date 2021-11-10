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

package external

import (
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"os"
	"strconv"
	"time"

	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/plugin/shared"
)

const (
	Info    = 0
	Crit    = 1
	Err     = 2
	Warning = 3
	Debug   = 4
	Trace   = 5
)

type handler struct {
	name          string
	socket        string
	registerStart bool
	connection    net.Conn
}

var supportedVersion map[string]bool

func NewHandler(name string) (h handler, err error) {
	h.name = name

	if len(os.Args) < 2 {
		return
	}

	h.socket = os.Args[1]

	if len(os.Args) < 3 {
		h.registerStart = false
		return
	}

	h.registerStart, err = strconv.ParseBool(os.Args[2])
	if err != nil {
		return
	}

	return
}

func (h *handler) Execute() error {
	err := h.setConnection(h.socket, 3*time.Second)
	if err != nil {
		return err
	}

	err = h.start()
	if err != nil {
		return err
	}

	h.run()

	return nil
}

func (h *handler) run() {
	for {
		err := h.handle()
		if err != nil {
			h.Errf(err.Error())
		}
	}
}

func (h *handler) handle() error {
	reqType, data, err := shared.Read(h.connection)
	if err != nil {
		return err
	}

	h.Infof("Plugin %s executing %s", h.name, shared.GetRequestName(reqType))

	switch reqType {
	case shared.RegisterRequestType:
		err = h.register(data)
		if err != nil {
			return err
		}
	case shared.TerminateRequestType:
		h.terminate()
	case shared.ValidateRequestType:
		err = h.validate(data)
		if err != nil {
			return err
		}
	case shared.ExportRequestType:
		err = h.export(data)
		if err != nil {
			return err
		}
	case shared.ConfigureRequestType:
		err = h.configure(data)
		if err != nil {
			return err
		}
	// case shared.CollectorRequestType:
	// 	h.Debugf("got CollectorRequestType")
	// 	err = h.collect(data)
	// 	if err != nil {
	// 		return err
	// 	}
	// case shared.PeriodRequestType:
	// 	h.Debugf("got PeriodRequestType")
	// 	err = h.period(data)
	// 	if err != nil {
	// 		return err
	// }
	default:
		return fmt.Errorf("unknown request recivied: %d", reqType)
	}

	h.Infof("Plugin %s executed %s", h.name, shared.GetRequestName(reqType))

	return nil
}

func (h *handler) start() error {
	if h.registerStart {
		return nil
	}

	acc, err := plugin.GetByName(h.name)
	if err != nil {
		return err
	}

	p, ok := acc.(plugin.Runner)
	if !ok {
		return nil
	}

	p.Start()

	return nil
}

func (h *handler) stop() error {
	if h.registerStart {
		return nil
	}

	acc, err := plugin.GetByName(h.name)
	if err != nil {
		return err
	}

	p, ok := acc.(plugin.Runner)
	if !ok {
		return nil
	}

	p.Stop()
	return nil
}

func (h *handler) register(data []byte) error {
	var req shared.RegisterRequest
	err := json.Unmarshal(data, &req)
	if err != nil {
		return err
	}

	var metrics []string

	for key, metric := range plugin.Metrics {
		metrics = append(metrics, key)
		metrics = append(metrics, metric.Description)
	}

	interfaces, err := h.getInterfaces()
	if err != nil {
		return err
	}

	response := shared.CreateEmptyRegisterResponse(req.Id)

	err = checkVersion(req.Version)
	if err != nil {
		response.Error = err.Error()
		return shared.Write(h.connection, response)
	}

	response.Name = h.name
	response.Metrics = metrics
	response.Interfaces = interfaces

	return shared.Write(h.connection, response)
}

func checkVersion(version string) error {
	if supportedVersion[version] {
		return nil
	}

	return fmt.Errorf("plugin does not support version %s", version)
}

func (h *handler) validate(data []byte) error {
	var req shared.ValidateRequest
	err := json.Unmarshal(data, &req)
	if err != nil {
		return err
	}

	response := shared.CreateEmptyValidateResponse(req.Id)
	acc, err := plugin.GetByName(h.name)
	if err != nil {
		return err
	}

	p, ok := acc.(plugin.Configurator)
	if !ok {
		response.Error = "plugin does not implement Configurator interface"
		return shared.Write(h.connection, response)
	}

	err = p.Validate(req.PrivateOptions)
	if err != nil {
		response.Error = err.Error()
	}

	return shared.Write(h.connection, response)
}

func (h *handler) configure(data []byte) error {
	var req shared.ConfigureRequest
	err := json.Unmarshal(data, &req)
	if err != nil {
		return err
	}

	acc, err := plugin.GetByName(h.name)
	if err != nil {
		return err
	}

	p, ok := acc.(plugin.Configurator)
	if !ok {
		return errors.New("plugin does not implement Configurator interface")
	}

	p.Configure(req.GlobalOptions, req.PrivateOptions)
	return nil
}

func (h *handler) export(data []byte) error {
	var req shared.ExportRequest
	err := json.Unmarshal(data, &req)
	if err != nil {
		return err
	}

	acc, err := plugin.GetByName(h.name)
	if err != nil {
		return err
	}

	p, ok := acc.(plugin.Exporter)
	if !ok {
		return errors.New("plugin does not implement Exporter interface")
	}

	response := shared.CreateEmptyExportResponse(req.Id)
	response.Value, err = p.Export(req.Key, req.Params, emptyCtx{})
	if err != nil {
		response.Error = err.Error()
	}

	return shared.Write(h.connection, response)
}

func (h *handler) period(data []byte) error {
	var req shared.PeriodRequest
	err := json.Unmarshal(data, &req)
	if err != nil {
		return err
	}

	acc, err := plugin.GetByName(h.name)
	if err != nil {
		return err
	}

	p, ok := acc.(plugin.Collector)
	if !ok {
		return errors.New("plugin does not implement Collector interface")
	}

	return shared.Write(h.connection, shared.CreatePeriodResponse(req.Id, p.Period()))
}

func (h *handler) collect(data []byte) error {
	var req shared.CollectRequest
	err := json.Unmarshal(data, &req)
	if err != nil {
		return err
	}

	acc, err := plugin.GetByName(h.name)
	if err != nil {
		return err
	}

	p, ok := acc.(plugin.Collector)
	if !ok {
		return errors.New("plugin does not implement Collector interface")
	}

	response := shared.CreateEmptyCollectResponse(req.Id)

	err = p.Collect()
	if err != nil {
		response.Error = err.Error()
	}

	return shared.Write(h.connection, response)
}

func (h *handler) terminate() {
	err := h.stop()
	if err != nil {
		h.Errf(fmt.Sprintf("failed to execute stop: %s\n", err.Error()))
	}

	os.Exit(0)
}

func (h *handler) setConnection(path string, timeout time.Duration) (err error) {
	var i int

	for start := time.Now(); ; {
		if i%5 == 0 {
			if time.Since(start) > timeout {
				break
			}
		}

		var conn net.Conn
		conn, err = net.DialTimeout("unix", path, timeout)
		if err != nil {
			continue
		}

		h.connection = conn

		return
	}

	return
}

func (h *handler) getInterfaces() (uint32, error) {
	var interfaces uint32
	acc, err := plugin.GetByName(h.name)
	if err != nil {
		return interfaces, err
	}

	_, ok := acc.(plugin.Exporter)
	if ok {
		interfaces |= shared.Exporter
	}

	_, ok = acc.(plugin.Configurator)
	if ok {
		interfaces |= shared.Configurator
	}

	_, ok = acc.(plugin.Runner)
	if ok {
		interfaces |= shared.Runner
	}

	_, ok = acc.(plugin.Collector)
	if ok {
		interfaces |= shared.Collector
	}

	return interfaces, nil
}

func (h *handler) Tracef(format string, args ...interface{}) {
	h.sendLog(shared.CreateLogRequest(Trace, fmt.Sprintf(format, args...)))
}

func (h *handler) Debugf(format string, args ...interface{}) {
	h.sendLog(shared.CreateLogRequest(Debug, fmt.Sprintf(format, args...)))
}

func (h *handler) Warningf(format string, args ...interface{}) {
	h.sendLog(shared.CreateLogRequest(Warning, fmt.Sprintf(format, args...)))
}

func (h *handler) Infof(format string, args ...interface{}) {
	h.sendLog(shared.CreateLogRequest(Info, fmt.Sprintf(format, args...)))
}

func (h *handler) Errf(format string, args ...interface{}) {
	h.sendLog(shared.CreateLogRequest(Err, fmt.Sprintf(format, args...)))
}

func (h *handler) Critf(format string, args ...interface{}) {
	h.sendLog(shared.CreateLogRequest(Crit, fmt.Sprintf(format, args...)))
}

func (h *handler) sendLog(request shared.LogRequest) {
	shared.Write(h.connection, request)
}

func init() {
	supportedVersion = map[string]bool{}
	supportedVersion[shared.Version] = true
}
