/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	"errors"
	"flag"
	"fmt"
	"os"
	"os/signal"
	"strings"
	"syscall"

	_ "zabbix.com/plugins"

	"zabbix.com/internal/agent"
	"zabbix.com/internal/agent/keyaccess"
	"zabbix.com/internal/agent/remotecontrol"
	"zabbix.com/internal/agent/resultcache"
	"zabbix.com/internal/agent/scheduler"
	"zabbix.com/internal/agent/serverconnector"
	"zabbix.com/internal/agent/serverlistener"
	"zabbix.com/internal/agent/statuslistener"
	"zabbix.com/internal/monitor"
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/pidfile"
	"zabbix.com/pkg/tls"
	"zabbix.com/pkg/version"
	"zabbix.com/pkg/zbxlib"
)

var manager *scheduler.Manager
var listeners []*serverlistener.ServerListener
var serverConnectors []*serverconnector.Connector

func processLoglevelCommand(c *remotecontrol.Client, params []string) (err error) {
	if len(params) != 2 {
		return errors.New("No 'loglevel' parameter specified")
	}
	switch params[1] {
	case "increase":
		if log.IncreaseLogLevel() {
			message := fmt.Sprintf("Increased log level to %s", log.Level())
			log.Infof(message)
			err = c.Reply(message)
		} else {
			err = fmt.Errorf("Cannot increase log level above %s", log.Level())
			log.Infof(err.Error())
		}
	case "decrease":
		if log.DecreaseLogLevel() {
			message := fmt.Sprintf("Decreased log level to %s", log.Level())
			log.Infof(message)
			err = c.Reply(message)
		} else {
			err = fmt.Errorf("Cannot decrease log level below %s", log.Level())
			log.Infof(err.Error())
		}
	default:
		return errors.New("Invalid 'loglevel' parameter")
	}
	return
}

func processMetricsCommand(c *remotecontrol.Client, params []string) (err error) {
	data := manager.Query("metrics")
	return c.Reply(data)
}

func processVersionCommand(c *remotecontrol.Client, params []string) (err error) {
	data := version.Long()
	return c.Reply(data)
}

func processHelpCommand(c *remotecontrol.Client, params []string) (err error) {
	help := `Remote control interface, available commands:
	loglevel increase - Increase log level
	loglevel decrease - Decrease log level
	metrics - List available metrics
	version - Display Agent version
	help - Display this help message`
	return c.Reply(help)
}

func processRemoteCommand(c *remotecontrol.Client) (err error) {
	params := strings.Fields(c.Request())
	if len(params) == 0 {
		return errors.New("Empty command")
	}
	switch params[0] {
	case "loglevel":
		err = processLoglevelCommand(c, params)
	case "help":
		err = processHelpCommand(c, params)
	case "metrics":
		err = processMetricsCommand(c, params)
	case "version":
		err = processVersionCommand(c, params)
	default:
		return errors.New("Unknown command")
	}
	return
}

var pidFile *pidfile.File

func run() (err error) {
	sigs := make(chan os.Signal, 1)
	signal.Notify(sigs, syscall.SIGINT, syscall.SIGTERM)

	var control *remotecontrol.Conn
	if control, err = remotecontrol.New(agent.Options.ControlSocket); err != nil {
		return
	}
	control.Start()

loop:
	for {
		select {
		case sig := <-sigs:
			switch sig {
			case syscall.SIGINT, syscall.SIGTERM:
				break loop
			}
		case client := <-control.Client():
			if rerr := processRemoteCommand(client); rerr != nil {
				if rerr = client.Reply("error: " + rerr.Error()); rerr != nil {
					log.Warningf("cannot reply to remote command: %s", rerr)
				}
			}
			client.Close()
		}
	}
	control.Stop()
	return nil
}

var confDefault string

func main() {
	var confFlag string
	const (
		confDescription = "Path to the configuration file"
	)
	flag.StringVar(&confFlag, "config", confDefault, confDescription)
	flag.StringVar(&confFlag, "c", confDefault, confDescription+" (shorthand)")

	var foregroundFlag bool
	const (
		foregroundDefault     = true
		foregroundDescription = "Run Zabbix agent in foreground"
	)
	flag.BoolVar(&foregroundFlag, "foreground", foregroundDefault, foregroundDescription)
	flag.BoolVar(&foregroundFlag, "f", foregroundDefault, foregroundDescription+" (shorthand)")

	var testFlag string
	const (
		testDefault     = ""
		testDescription = "Test specified item and exit"
	)
	flag.StringVar(&testFlag, "test", testDefault, testDescription)
	flag.StringVar(&testFlag, "t", testDefault, testDescription+" (shorthand)")

	var printFlag bool
	const (
		printDefault     = false
		printDescription = "Print known items and exit"
	)
	flag.BoolVar(&printFlag, "print", printDefault, printDescription)
	flag.BoolVar(&printFlag, "p", printDefault, printDescription+" (shorthand)")

	var verboseFlag bool
	const (
		verboseDefault     = false
		verboseDescription = "Enable verbose output for metric testing or printing"
	)
	flag.BoolVar(&verboseFlag, "verbose", verboseDefault, verboseDescription)
	flag.BoolVar(&verboseFlag, "v", verboseDefault, verboseDescription+" (shorthand)")

	var versionFlag bool
	const (
		versionDefault     = false
		versionDescription = "Print program version and exit"
	)
	flag.BoolVar(&versionFlag, "version", versionDefault, versionDescription)
	flag.BoolVar(&versionFlag, "V", versionDefault, versionDescription+" (shorthand)")

	var remoteCommand string
	const (
		remoteDefault     = ""
		remoteDescription = "Perform administrative functions (send 'help' for available commands)"
	)
	flag.StringVar(&remoteCommand, "R", remoteDefault, remoteDescription)

	flag.Parse()

	var argConfig, argTest, argPrint, argVersion, argVerbose bool

	// Need to manually check if the flag was specified, as default flag package
	// does not offer automatic detection. Consider using third party package.
	flag.Visit(func(f *flag.Flag) {
		switch f.Name {
		case "c", "config":
			argConfig = true
		case "t", "test":
			argTest = true
		case "p", "print":
			argPrint = true
		case "V", "version":
			argVersion = true
		case "v", "verbose":
			argVerbose = true
		}
	})

	if argVersion {
		version.Display()
		os.Exit(0)
	}

	if err := conf.Load(confFlag, &agent.Options); err != nil {
		if argConfig || !(argTest || argPrint) {
			fatalExit("", err)
		}
		// create default configuration for testing options
		if !argConfig {
			_ = conf.Unmarshal([]byte{}, &agent.Options)
		}
	}

	if err := agent.ValidateOptions(agent.Options); err != nil {
		fatalExit("cannot validate configuration", err)
	}

	if err := log.Open(log.Console, log.Warning, "", 0); err != nil {
		fatalExit("cannot initialize logger", err)
	}

	if argTest || argPrint {
		var level int
		if argVerbose {
			level = log.Trace
		} else {
			level = log.None
		}
		if err := log.Open(log.Console, level, "", 0); err != nil {
			fatalExit("cannot initialize logger", err)
		}

		if err := keyaccess.LoadRules(agent.Options.AllowKey, agent.Options.DenyKey); err != nil {
			fatalExit("failed to load key access rules", err)
		}

		var m *scheduler.Manager
		var err error
		if m, err = scheduler.NewManager(&agent.Options); err != nil {
			fatalExit("cannot create scheduling manager", err)
		}
		m.Start()

		if argTest {
			checkMetric(m, testFlag)
		} else {
			checkMetrics(m)
		}

		m.Stop()
		monitor.Wait(monitor.Scheduler)
		os.Exit(0)
	}

	if argVerbose {
		fatalExit("", errors.New("verbose parameter can be specified only with test or print parameters"))
	}

	if remoteCommand != "" {
		if agent.Options.ControlSocket == "" {
			log.Errf("Cannot send remote command: ControlSocket configuration parameter is not defined")
			os.Exit(0)
		}

		if reply, err := remotecontrol.SendCommand(agent.Options.ControlSocket, remoteCommand); err != nil {
			log.Errf("Cannot send remote command: %s", err)
		} else {
			log.Infof(reply)
		}
		os.Exit(0)
	}

	var logType, logLevel int
	switch agent.Options.LogType {
	case "system":
		logType = log.System
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

	if err := log.Open(logType, logLevel, agent.Options.LogFile, agent.Options.LogFileSize); err != nil {
		fatalExit("cannot initialize logger", err)
	}

	zbxlib.SetLogLevel(logLevel)

	greeting := fmt.Sprintf("Starting Zabbix Agent 2 [%s]. (%s)", agent.Options.Hostname, version.Long())
	log.Infof(greeting)

	addresses, err := serverconnector.ParseServerActive()
	if err != nil {
		fatalExit("cannot parse the \"ServerActive\" parameter", err)
	}

	if err = resultcache.Prepare(&agent.Options, addresses); err != nil {
		fatalExit("cannot prepare result cache", err)
	}

	if tlsConfig, err := agent.GetTLSConfig(&agent.Options); err != nil {
		fatalExit("cannot use encryption configuration", err)
	} else {
		if tlsConfig != nil {
			if err := tls.Init(tlsConfig); err != nil {
				fatalExit("cannot configure encryption", err)
			}
		}
	}

	if pidFile, err = pidfile.New(agent.Options.PidFile); err != nil {
		fatalExit("cannot initialize PID file", err)
	}
	defer pidFile.Delete()

	log.Infof("using configuration file: %s", confFlag)

	if err := keyaccess.LoadRules(agent.Options.AllowKey, agent.Options.DenyKey); err != nil {
		log.Errf("Failed to load key access rules: %s", err.Error())
		os.Exit(1)
	}

	if err = agent.InitUserParameterPlugin(agent.Options.UserParameter, agent.Options.UnsafeUserParameters); err != nil {
		fatalExit("cannot initialize user parameters", err)
	}

	if manager, err = scheduler.NewManager(&agent.Options); err != nil {
		fatalExit("cannot create scheduling manager", err)
	}

	// replacement of deprecated StartAgents
	if 0 != len(agent.Options.Server) {
		var listenIPs []string
		if listenIPs, err = serverlistener.ParseListenIP(&agent.Options); err != nil {
			fatalExit("cannot parse \"ListenIP\" parameter", err)
		}
		for i := 0; i < len(listenIPs); i++ {
			listener := serverlistener.New(i, manager, listenIPs[i], &agent.Options)
			listeners = append(listeners, listener)
		}
	}

	if foregroundFlag {
		if agent.Options.LogType != "console" {
			fmt.Println(greeting)
		}
		fmt.Println("Press Ctrl+C to exit.")
	}

	manager.Start()

	if err = configUpdateItemParameters(manager, &agent.Options); err != nil {
		fatalExit("cannot process configuration", err)
	}

	serverConnectors = make([]*serverconnector.Connector, len(addresses))

	for i := 0; i < len(serverConnectors); i++ {
		if serverConnectors[i], err = serverconnector.New(manager, addresses[i], &agent.Options); err != nil {
			fatalExit("cannot create server connector", err)
		}
		serverConnectors[i].Start()
	}

	for _, listener := range listeners {
		if err = listener.Start(); err != nil {
			fatalExit("cannot start server listener", err)
		}
	}

	if agent.Options.StatusPort != 0 {
		if err = statuslistener.Start(manager, confFlag); err != nil {
			fatalExit("cannot start HTTP listener", err)
		}
	}

	if err == nil {
		err = run()
	}
	if err != nil {
		log.Errf("cannot start agent: %s", err.Error())
	}

	if agent.Options.StatusPort != 0 {
		statuslistener.Stop()
	}
	for _, listener := range listeners {
		listener.Stop()
	}
	for i := 0; i < len(serverConnectors); i++ {
		serverConnectors[i].StopConnector()
	}
	monitor.Wait(monitor.Input)

	manager.Stop()
	monitor.Wait(monitor.Scheduler)

	// split shutdown in two steps to ensure that result cache is still running while manager is
	// being stopped, because there might be pending exporters that could block if result cache
	// is stoppped and its input channel is full.
	for i := 0; i < len(serverConnectors); i++ {
		serverConnectors[i].StopCache()
	}
	monitor.Wait(monitor.Output)

	farewell := fmt.Sprintf("Zabbix Agent 2 stopped. (%s)", version.Long())
	log.Infof(farewell)

	if foregroundFlag && agent.Options.LogType != "console" {
		fmt.Println(farewell)
	}
}

func fatalExit(message string, err error) {
	if len(message) == 0 {
		message = err.Error()
	} else {
		message = fmt.Sprintf("%s: %s", message, err.Error())
	}

	if agent.Options.LogType == "file" {
		log.Critf("%s", message)
	}

	fmt.Fprintf(os.Stderr, "zabbix_agent2 [%d]: ERROR: %s\n", os.Getpid(), message)
	os.Exit(1)
}
