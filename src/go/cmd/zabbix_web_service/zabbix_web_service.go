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
	"flag"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/zbxcomms"
)

var confDefault string

type handler struct {
	allowedPeers *zbxcomms.AllowedPeers
}

func main() {
	var confFlag string
	var helpFlag bool

	const (
		confDescription = "Path to the configuration file"
		helpDefault     = false
		helpDescription = "Display this help message"
	)

	flag.StringVar(&confFlag, "config", confDefault, confDescription)
	flag.StringVar(&confFlag, "c", confDefault, confDescription+" (shorthand)")
	flag.BoolVar(&helpFlag, "help", helpDefault, helpDescription)
	flag.BoolVar(&helpFlag, "h", helpDefault, helpDescription+" (shorthand)")

	flag.Parse()

	if helpFlag {
		flag.Usage()
		os.Exit(0)
	}

	if err := conf.Load(confFlag, &options); err != nil {
		fatalExit("", err)
	}

	var logType int
	switch options.LogType {
	case "system":
		logType = log.System
	case "console":
		logType = log.Console
	case "file":
		logType = log.File
	}

	if err := log.Open(logType, log.Info, options.LogFile, options.LogFileSize); err != nil {
		fatalExit("cannot initialize logger", err)
	}

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGINT, syscall.SIGTERM)

	log.Infof("Starting Zabbix web service")

	go func() {
		if err := run(options.TLSAccept); err != nil {
			fatalExit("failed to start", err)
		}
	}()

	<-stop

	farewell := fmt.Sprint("Zabbix web service stopped.")
	log.Infof(farewell)

	if options.LogType != "console" {
		fmt.Println(farewell)
	}
}

func run(tls string) error {
	var h handler
	var err error
	var isTLS bool

	if tls == "cert" {
		isTLS = true
	}

	if h.allowedPeers, err = zbxcomms.GetAllowedPeers(options.AllowedIP); err != nil {
		return err
	}

	http.HandleFunc("/report", h.report)

	if isTLS {
		return http.ListenAndServeTLS(":"+options.ListenPort, options.TLSCertFile, options.TLSKeyFile, nil)
	}

	return http.ListenAndServe(":"+options.ListenPort, nil)
}

func fatalExit(message string, err error) {
	if len(message) == 0 {
		message = err.Error()
	} else {
		message = fmt.Sprintf("%s: %s", message, err.Error())
	}

	if options.LogType == "file" {
		log.Infof("%s", message)
	}

	fmt.Fprintf(os.Stderr, "zabbix_web_service [%d]: ERROR: %s\n", os.Getpid(), message)
	os.Exit(1)
}
