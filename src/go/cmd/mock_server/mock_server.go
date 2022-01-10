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

package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"io/ioutil"
	"os"
	"strconv"
	"time"

	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/zbxcomms"
)

type MockServerOptions struct {
	LogType          string `conf:"default=console"`
	LogFile          string `conf:"optional"`
	DebugLevel       int    `conf:"range=0:5,default=3"`
	Port             int    `conf:"range=1:65535,default=10051"`
	Timeout          int    `conf:"range=1:30,default=5"`
	ActiveChecksFile string `conf:"optional"`
}

var options MockServerOptions

func handleConnection(c *zbxcomms.Connection) {
	defer c.Close()

	js, err := c.Read()
	if err != nil {
		log.Warningf("Read failed: %s\n", err)
		return
	}

	log.Debugf("got '%s'", string(js))

	var pairs map[string]interface{}
	if err := json.Unmarshal(js, &pairs); err != nil {
		log.Warningf("Unmarshal failed: %s\n", err)
		return
	}

	switch pairs["request"] {
	case "active checks":
		activeChecks, err := ioutil.ReadFile(options.ActiveChecksFile)
		if err == nil {
			err = c.Write(activeChecks)
		}
		if err != nil {
			log.Warningf("Write failed: %s\n", err)
			return
		}
	case "agent data":
		err = c.WriteString("{\"response\":\"success\",\"info\":\"processed: 0; failed: 0; total: 0; seconds spent: 0.042523\"}")
		if err != nil {
			log.Warningf("Write failed: %s\n", err)
			return
		}

	default:
		log.Warningf("Unsupported request: %s\n", pairs["request"])
		return
	}

}

func main() {
	var confFlag string
	const (
		confDefault     = "mock_server.conf"
		confDescription = "Path to the configuration file"
	)
	flag.StringVar(&confFlag, "config", confDefault, confDescription)
	flag.StringVar(&confFlag, "c", confDefault, confDescription+" (shorhand)")

	var foregroundFlag bool
	const (
		foregroundDefault     = true
		foregroundDescription = "Run Zabbix mock server in foreground"
	)
	flag.BoolVar(&foregroundFlag, "foreground", foregroundDefault, foregroundDescription)
	flag.BoolVar(&foregroundFlag, "f", foregroundDefault, foregroundDescription+" (shorhand)")
	flag.Parse()

	if err := conf.Load(confFlag, &options); err != nil {
		fmt.Fprintf(os.Stderr, "%s\n", err.Error())
		os.Exit(1)
	}

	var logType, logLevel int
	switch options.LogType {
	case "console":
		logType = log.Console
	case "file":
		logType = log.File
	}
	switch options.DebugLevel {
	case 0:
		logLevel = log.Info
	case 1:
		logLevel = log.Crit
	case 2:
		logLevel = log.Err
	case 3:
		logLevel = log.Warning
	case 4:
		logLevel = log.Debug
	case 5:
		logLevel = log.Trace
	}

	if err := log.Open(logType, logLevel, options.LogFile, 0); err != nil {
		fmt.Fprintf(os.Stderr, "Cannot initialize logger: %s\n", err.Error())
		os.Exit(1)
	}

	greeting := fmt.Sprintf("Starting Zabbix Mock server [(hostname placeholder)]. (version placeholder)")
	log.Infof(greeting)

	if foregroundFlag {
		if options.LogType != "console" {
			fmt.Println(greeting)
		}
		fmt.Println("Press Ctrl+C to exit.")
	}

	log.Infof("using configuration file: %s", confFlag)

	listener, err := zbxcomms.Listen(":" + strconv.Itoa(options.Port))
	if err != nil {
		log.Critf("Listen failed: %s\n", err)
		return
	}
	defer listener.Close()

	for {
		c, err := listener.Accept(time.Second*time.Duration(options.Timeout), zbxcomms.TimeoutModeShift)
		if err != nil {
			log.Critf("Accept failed: %s\n", err)
			return
		}
		go handleConnection(c)
	}
}
