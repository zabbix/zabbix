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
	"zabbix.com/pkg/dynamic"
)

func main() {
	//  Socket to talk to clients
	write("starting\n")
	rep := zmq4.NewRep(context.Background())
	defer rep.Close()

	err := rep.Listen("ipc:///tmp/dynamic.test")
	if err != nil {
		// return err or log
		write(err.Error())
		return
	}

loop:
	for {
		//  Wait for next request from client
		msg, err := rep.Recv()
		if err != nil {
			// return err or log
			write(err.Error())
			return
		}

		write("got msg")
		var reqData dynamic.Plugin

		err = json.Unmarshal(msg.Bytes(), &reqData)
		if err != nil {
			// return err or log
			write(err.Error())
			return
		}

		write(fmt.Sprintf("values: %+v\n", reqData))

		switch reqData.Command {
		case dynamic.Export:

			var reqData dynamic.Plugin
			reqData.RespType = dynamic.Response
			reqData.Value = "Dynamic plugin export test success"

			respBytes, err := json.Marshal(reqData)
			if err != nil {
				write(fmt.Sprintf("could not create reply: %s\n", err.Error()))
				return
			}

			write("Sending output\n")
			time.Sleep(time.Second)
			err = rep.Send(zmq4.NewMsg(respBytes))
			if err != nil {
				write(fmt.Sprintf("could not send reply: %s\n", err.Error()))
				return
			}
			write("Sent\n")

			// Export(reqData.Params, rep)
			break loop
			// should be continue to wait for next request or shut down
		}
	}
}

func write(in string) {
	d1 := []byte(in)
	err := os.WriteFile("/tmp/dynamic.log", d1, 0644)
	if err != nil {
		panic(err)
	}
}
