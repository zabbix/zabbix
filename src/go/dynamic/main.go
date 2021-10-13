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

package main

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"time"

	"github.com/go-zeromq/zmq4"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/shared"
)

const SockAddr = "/tmp/dynamic.sock"

func main() {
	f, _ := os.Create("/tmp/dynamic.log")
	defer f.Close()

	writeDebug(f, "starting")
	rep := zmq4.NewRep(context.Background())
	defer rep.Close()

	if err := os.RemoveAll(SockAddr); err != nil {
		writeDebug(f, err.Error())
	}

	err := rep.Listen(fmt.Sprintf("ipc://%s", SockAddr))
	if err != nil {
		writeDebug(f, err.Error())
		return
	}

	// loop:
	for {
		writeDebug(f, "listening for req")
		msg, err := rep.Recv()
		if err != nil {
			writeDebug(f, err.Error())
			return
		}

		writeDebug(f, "got msg")
		var reqData shared.Plugin

		err = json.Unmarshal(msg.Bytes(), &reqData)
		if err != nil {
			writeDebug(f, err.Error())
			return
		}

		writeDebug(f, fmt.Sprintf("values: %+v", reqData))
		switch reqData.Command {
		case shared.Export:
			writeDebug(f, "got Export")
			export(rep, reqData.Key, reqData.Params, f)
		case shared.Metrics:
			writeDebug(f, "got Metric")
			metric(rep, f)
		}
	}
}

func writeDebug(f *os.File, in string) {
	d1 := []byte(in + "\n")
	_, err := f.Write(d1)
	if err != nil {
		panic(err)
	}
}

func metric(sock zmq4.Socket, f *os.File) {
	var respData shared.Plugin
	respData.RespType = shared.Metrics
	respData.Supported = shared.Export
	respData.Name = impl.Name()
	for key, metric := range plugin.Metrics {
		respData.Params = append(respData.Params, key)
		respData.Params = append(respData.Params, metric.Description)
	}

	respBytes, err := json.Marshal(respData)
	if err != nil {
		writeDebug(f, fmt.Sprintf("could not create reply: %s", err.Error()))
		return
	}

	writeDebug(f, "Sending metric output\n")
	err = sock.Send(zmq4.NewMsg(respBytes))
	if err != nil {
		writeDebug(f, fmt.Sprintf("could not send reply: %s", err.Error()))
		return
	}
}

func export(sock zmq4.Socket, key string, params []string, f *os.File) {
	var reqData shared.Plugin
	reqData.RespType = shared.Response
	reqData.Value, reqData.Error = impl.Export(key, params, emptyCtx{})

	respBytes, err := json.Marshal(reqData)
	if err != nil {
		writeDebug(f, fmt.Sprintf("could not create reply: %s", err.Error()))
		return
	}

	writeDebug(f, "Sending output")
	time.Sleep(time.Second)
	err = sock.Send(zmq4.NewMsg(respBytes))
	if err != nil {
		writeDebug(f, fmt.Sprintf("could not send reply: %s", err.Error()))
		return
	}
	writeDebug(f, "Sent")
}
