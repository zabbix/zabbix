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
	"crypto/tls"
	"crypto/x509"
	"errors"
	"flag"
	"fmt"
	"io/ioutil"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"git.zabbix.com/ap/plugin-support/conf"
	"git.zabbix.com/ap/plugin-support/log"
	"zabbix.com/pkg/version"
	"zabbix.com/pkg/zbxnet"
)

var (
	confDefault     string
	applicationName string
)

type handler struct {
	allowedPeers *zbxnet.AllowedPeers
}

func main() {
	var confFlag string
	var helpFlag bool
	var versionFlag bool

	version.Init(applicationName)

	const (
		confDescription    = "Path to the configuration file"
		helpDefault        = false
		helpDescription    = "Display this help message"
		versionDefault     = false
		versionDescription = "Print program version and exit"
	)

	flag.StringVar(&confFlag, "config", confDefault, confDescription)
	flag.StringVar(&confFlag, "c", confDefault, confDescription+" (shorthand)")
	flag.BoolVar(&helpFlag, "help", helpDefault, helpDescription)
	flag.BoolVar(&helpFlag, "h", helpDefault, helpDescription+" (shorthand)")
	flag.BoolVar(&versionFlag, "version", versionDefault, versionDescription)
	flag.BoolVar(&versionFlag, "V", versionDefault, versionDescription+" (shorthand)")

	flag.Parse()

	if helpFlag {
		flag.Usage()
		os.Exit(0)
	}

	if versionFlag {
		version.Display()
		os.Exit(0)
	}

	if err := conf.Load(confFlag, &options); err != nil {
		fatalExit("", err)
	}

	var logType, logLevel int

	switch options.LogType {
	case "system":
		logType = log.System
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

	if err := log.Open(logType, logLevel, options.LogFile, options.LogFileSize); err != nil {
		fatalExit("cannot initialize logger", err)
	}

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGINT, syscall.SIGTERM)

	log.Infof("starting Zabbix web service")

	go func() {
		if err := run(); err != nil {
			fatalExit("failed to start", err)
		}
	}()

	<-stop

	farewell := "Zabbix web service stopped."
	log.Infof(farewell)

	if options.LogType != "console" {
		fmt.Println(farewell)
	}
}

func run() error {
	var h handler

	var err error

	if h.allowedPeers, err = zbxnet.GetAllowedPeers(options.AllowedIP); err != nil {
		return err
	}

	http.HandleFunc("/report", h.report)

	if err := validateTLSFiles(); err != nil {
		return err
	}

	switch options.TLSAccept {
	case "cert":
		server, err := createTLSServer()
		if err != nil {
			return err
		}

		return server.ListenAndServeTLS(options.TLSCertFile, options.TLSKeyFile)
	case "", "unencrypted":
		return http.ListenAndServe(":"+options.ListenPort, nil)
	}

	return nil
}

func fatalExit(message string, err error) {
	if len(message) == 0 {
		message = err.Error()
	} else {
		message = fmt.Sprintf("%s: %s", message, err.Error())
	}

	if options.LogType == "file" {
		log.Critf("%s", message)
	}

	fmt.Fprintf(os.Stderr, "zabbix_web_service [%d]: ERROR: %s\n", os.Getpid(), message)
	os.Exit(1)
}

func validateTLSFiles() error {
	switch options.TLSAccept {
	case "cert":
		if options.TLSCAFile == "" {
			return errors.New("missing TLSCAFile configuration parameter")
		}
		if options.TLSCertFile == "" {
			return errors.New("missing TLSCertFile configuration parameter")
		}
		if options.TLSKeyFile == "" {
			return errors.New("missing TLSKeyFile configuration parameter")
		}
	case "", "unencrypted":
		if options.TLSCAFile != "" {
			return errors.New("TLSCAFile configuration parameter set without certificates being used")
		}
		if options.TLSCertFile != "" {
			return errors.New("TLSCertFile configuration parameter set without certificates being used")
		}
		if options.TLSKeyFile != "" {
			return errors.New("TLSKeyFile configuration parameter set without certificates being used")
		}
	default:
		return errors.New("invalid TLSAccept configuration parameter")
	}

	return nil
}

func createTLSServer() (*http.Server, error) {
	caCert, err := ioutil.ReadFile(options.TLSCAFile)
	if err != nil {
		return nil, fmt.Errorf("failed to read CA cert file: %s", err.Error())
	}

	caCertPool := x509.NewCertPool()
	caCertPool.AppendCertsFromPEM(caCert)

	tlsConfig := &tls.Config{
		ClientCAs:  caCertPool,
		ClientAuth: tls.RequireAndVerifyClientCert,
	}

	tlsConfig.BuildNameToCertificate()

	return &http.Server{Addr: ":" + options.ListenPort, TLSConfig: tlsConfig, ErrorLog: log.DefaultLogger}, nil
}
