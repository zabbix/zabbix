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

package main

import (
	"crypto/tls"
	"crypto/x509"
	"errors"
	"flag"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxflag"
	"golang.zabbix.com/sdk/zbxnet"
)

const usageMessageFormat = //
`Usage of Zabbix web service:
  %[1]s [-c config-file]
  %[1]s [-c config-file] -T
  %[1]s -h
  %[1]s -V

Options:
%[2]s
`

var (
	confDefault     string
	applicationName string
)

type handler struct {
	allowedPeers *zbxnet.AllowedPeers
}

func main() {
	var (
		confFlag       string
		helpFlag       bool
		testConfigFlag bool
		versionFlag    bool
	)

	version.Init(applicationName)

	f := zbxflag.Flags{
		&zbxflag.StringFlag{
			Flag: zbxflag.Flag{
				Name:      "config",
				Shorthand: "c",
				Description: fmt.Sprintf(
					"Path to the configuration file (default: %q)", confDefault,
				),
			},
			Default: confDefault,
			Dest:    &confFlag,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "test-config",
				Shorthand:   "T",
				Description: "Validate configuration file and exit",
			},
			Default: false,
			Dest:    &testConfigFlag,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "help",
				Shorthand:   "h",
				Description: "Display this help message",
			},
			Default: false,
			Dest:    &helpFlag,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "version",
				Shorthand:   "V",
				Description: "Print program version and exit",
			},
			Default: false,
			Dest:    &versionFlag,
		},
	}

	f.Register(flag.CommandLine)

	flag.Usage = func() {
		fmt.Printf(
			usageMessageFormat,
			os.Args[0],
			f.Usage(),
		)
	}

	flag.Parse()

	if helpFlag {
		flag.Usage()
		os.Exit(0)
	}

	if versionFlag {
		version.Display(nil)
		os.Exit(0)
	}

	if testConfigFlag {
		if confFlag == "" {
			fmt.Fprintf(os.Stderr, "cannot validate configuration file: %s\n", "it was not specified")
			os.Exit(1)
		}

		fmt.Printf("Validating configuration file \"%s\"\n", confFlag)
	}

	if err := conf.Load(confFlag, &options); err != nil {
		fatalExit("", err)
	}

	if testConfigFlag {
		fmt.Println("Validation successful")
		os.Exit(0)
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
	var (
		h   handler
		err error
	)

	h.allowedPeers, err = zbxnet.GetAllowedPeers(options.AllowedIP)
	if err != nil {
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
	caCert, err := os.ReadFile(options.TLSCAFile)
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

	return &http.Server{
		Addr:      ":" + options.ListenPort,
		TLSConfig: tlsConfig,
		ErrorLog:  log.DefaultLogger,
	}, nil
}
