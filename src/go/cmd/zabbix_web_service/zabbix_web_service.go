/*
** Copyright (C) 2001-2026 Zabbix SIA
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
	"context"
	"crypto/tls"
	"crypto/x509"
	"errors"
	"flag"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
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

const (
	tlsAcceptCert         = "cert"
	serverShutdownTimeout = 5 * time.Second
)

var (
	confDefault     string
	applicationName string
)

type handler struct {
	allowedPeers    *zbxnet.AllowedPeers
	breakpadDumpDir string
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

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	log.Infof("starting Zabbix web service")

	err := run(ctx)
	if err != nil {
		fatalExit("failed to start", err)
	}

	farewell := "Zabbix web service stopped."
	log.Infof(farewell)

	if options.LogType != "console" {
		fmt.Println(farewell)
	}
}

func run(ctx context.Context) error {
	allowedPeers, err := zbxnet.GetAllowedPeers(options.AllowedIP)
	if err != nil {
		return errs.Wrap(err, "failed to parse allowed peers")
	}

	breakpadDumpDir, err := os.MkdirTemp("", "zabbix-web-service-breakpad-*")
	if err != nil {
		return errs.Wrap(err, "failed to create temp dir for breakpad dump")
	}

	defer func() {
		cleanupErr := os.RemoveAll(breakpadDumpDir)
		if cleanupErr != nil {
			log.Errf("failed to remove breakpad dump directory %q: %s", breakpadDumpDir, cleanupErr)
		}
	}()

	h := handler{
		allowedPeers:    allowedPeers,
		breakpadDumpDir: breakpadDumpDir,
	}
	mux := http.NewServeMux()
	mux.HandleFunc("/report", h.report)

	err = validateTLSFiles()
	if err != nil {
		return err
	}

	server, err := createHTTPServer(mux)
	if err != nil {
		return err
	}

	serverErr := make(chan error, 1)

	go func() {
		serverErr <- serve(server)
	}()

	select {
	case <-ctx.Done():
		// The shutdown deadline must not inherit cancellation from ctx.
		shutdownCtx, cancel := context.WithTimeout(context.Background(), serverShutdownTimeout)
		defer cancel()

		err = server.Shutdown(shutdownCtx) //nolint:contextcheck
		if err != nil {
			return errs.Wrap(err, "failed to shutdown HTTP server")
		}

		return normalizeServerError(<-serverErr)

	case err := <-serverErr:
		return normalizeServerError(err)
	}
}

func createHTTPServer(handler http.Handler) (*http.Server, error) {
	if options.TLSAccept == tlsAcceptCert {
		server, err := createTLSServer()
		if err != nil {
			return nil, err
		}

		server.Handler = handler

		return server, nil
	}

	// Keep the existing timeout behavior; configuring server timeouts is outside this refactor.
	return &http.Server{ //nolint:gosec
		Addr:    ":" + options.ListenPort,
		Handler: handler,
	}, nil
}

func serve(server *http.Server) error {
	if options.TLSAccept == tlsAcceptCert {
		err := server.ListenAndServeTLS(options.TLSCertFile, options.TLSKeyFile)

		return errs.Wrap(err, "failed to serve HTTPS")
	}

	err := server.ListenAndServe()

	return errs.Wrap(err, "failed to serve HTTP")
}

func normalizeServerError(err error) error {
	if errors.Is(err, http.ErrServerClosed) {
		return nil
	}

	return err
}

func fatalExit(message string, err error) {
	if message == "" {
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
	case tlsAcceptCert:
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
