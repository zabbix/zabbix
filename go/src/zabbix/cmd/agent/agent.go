/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	"net"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"zabbix/internal/agent"
	"zabbix/internal/agent/scheduler"
	"zabbix/internal/agent/server_connector"
	"zabbix/internal/monitor"
	"zabbix/pkg/conf"
	"zabbix/pkg/log"
	_ "zabbix/plugins"
)

func parseServerActive() ([]string, error) {
	addresses := strings.Split(agent.Options.ServerActive, ",")

	for i := 0; i < len(addresses); i++ {
		if strings.IndexByte(addresses[i], ':') == -1 {
			if _, _, err := net.SplitHostPort(addresses[i] + ":10051"); err != nil {
				return nil, fmt.Errorf("error parsing the \"ServerActive\" parameter: address \"%s\": %s", addresses[i], err)
			}
			addresses[i] += ":10051"
		} else {
			if _, _, err := net.SplitHostPort(addresses[i] + ":10051"); err != nil {
				return nil, fmt.Errorf("error parsing the \"ServerActive\" parameter: address \"%s\": %s", addresses[i], err)
			}
		}

		for j := 0; j < i; j++ {
			if addresses[j] == addresses[i] {
				return nil, fmt.Errorf("error parsing the \"ServerActive\" parameter: address \"%s\" specified more than once", addresses[i])
			}
		}
	}

	return addresses, nil
}

func main() {
	var taskManager scheduler.Manager
	var confFlag string
	const (
		confDefault     = "agent.conf"
		confDescription = "Path to the configuration file"
	)
	flag.StringVar(&confFlag, "config", confDefault, confDescription)
	flag.StringVar(&confFlag, "c", confDefault, confDescription+" (shorhand)")

	var foregroundFlag bool
	const (
		foregroundDefault     = true
		foregroundDescription = "Run Zabbix agent in foreground"
	)
	flag.BoolVar(&foregroundFlag, "foreground", foregroundDefault, foregroundDescription)
	flag.BoolVar(&foregroundFlag, "f", foregroundDefault, foregroundDescription+" (shorhand)")

	var testFlag string
	const (
		testDefault     = ""
		testDescription = "Test specified item and exit"
	)
	flag.StringVar(&testFlag, "test", testDefault, testDescription)
	flag.StringVar(&testFlag, "t", testDefault, testDescription+" (shorhand)")

	var printFlag bool
	const (
		printDefault     = false
		printDescription = "Print known items and exit"
	)
	flag.BoolVar(&printFlag, "print", printDefault, printDescription)
	flag.BoolVar(&printFlag, "p", printDefault, printDescription+" (shorhand)")

	flag.Parse()

	var argConfig, argTest, argPrint bool

	// Need to manually check if the flag was specified, as default flag package
	// does not offer automatic detection. Consider using third party package.
	flag.Visit(func(f *flag.Flag) {
		switch f.Name {
		case "t", "test":
			argTest = true
		case "p", "print":
			argPrint = true
		case "c", "config":
			argConfig = true
		}
	})

	if argConfig {
		if err := conf.Load(confFlag, &agent.Options); err != nil {
			fmt.Fprintf(os.Stderr, "%s\n", err.Error())
			os.Exit(1)
		}
	}

	if argTest || argPrint {
		if err := log.Open(log.Console, log.Warning, ""); err != nil {
			fmt.Fprintf(os.Stderr, "Cannot initialize logger: %s\n", err.Error())
			os.Exit(1)
		}

		if argTest {
			if err := agent.CheckMetric(testFlag); err != nil {
				os.Exit(1)
			}
		} else {
			agent.CheckMetrics()
		}

		os.Exit(0)
	}

	var logType, logLevel int
	switch agent.Options.LogType {
	case "console":
		logType = log.Console
	case "file":
		logType = log.File
	}
	switch agent.Options.DebugLevel {
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

	if err := log.Open(logType, logLevel, agent.Options.LogFile); err != nil {
		fmt.Fprintf(os.Stderr, "Cannot initialize logger: %s\n", err.Error())
		os.Exit(1)
	}

	addresses, err := parseServerActive()
	if err != nil {
		log.Critf("%s", err)
		os.Exit(1)
	}

	greeting := fmt.Sprintf("Starting Zabbix Agent [(hostname placeholder)]. (version placeholder)")
	log.Infof(greeting)

	if foregroundFlag {
		if agent.Options.LogType != "console" {
			fmt.Println(greeting)
		}
		fmt.Println("Press Ctrl+C to exit.")
	}

	log.Infof("using configuration file: %s", confFlag)

	sigs := make(chan os.Signal, 1)
	signal.Notify(sigs, syscall.SIGINT, syscall.SIGTERM, syscall.SIGUSR1)

	taskManager.Start()

	serverConnectors := make([]server_connector.ServerConnector, len(addresses))
	resultCaches := make([]agent.ResultCache, len(addresses))

	for i := 0; i < len(addresses); i++ {
		resultCaches[i].Start()
		serverConnectors[i].TaskManager = &taskManager
		serverConnectors[i].ResultCache = &resultCaches[i]
		serverConnectors[i].Address = addresses[i]
		serverConnectors[i].Start()
	}

loop:
	for {
		sig := <-sigs
		switch sig {
		case syscall.SIGINT, syscall.SIGTERM:
			break loop
		case syscall.SIGUSR1:
			log.Debugf("user signal received")
			break loop
		}
	}

	for i := 0; i < len(addresses); i++ {
		serverConnectors[i].Stop()
		resultCaches[i].Stop()
	}

	taskManager.Stop()
	monitor.Wait()

	farewell := fmt.Sprintf("Zabbix Agent stopped. (version placeholder)")
	log.Infof(farewell)

	if foregroundFlag {
		if agent.Options.LogType != "console" {
			fmt.Println(farewell)
		}
		fmt.Println("Press Ctrl+C to exit.")
	}
}
