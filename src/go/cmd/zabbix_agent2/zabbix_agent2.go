/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	"strings"
	"time"

	"git.zabbix.com/ap/plugin-support/conf"
	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/plugin/comms"
	"git.zabbix.com/ap/plugin-support/zbxflag"
	"zabbix.com/internal/agent"
	"zabbix.com/internal/agent/keyaccess"
	"zabbix.com/internal/agent/resultcache"
	"zabbix.com/internal/agent/runtimecontrol"
	"zabbix.com/internal/agent/scheduler"
	"zabbix.com/internal/agent/serverconnector"
	"zabbix.com/internal/agent/serverlistener"
	"zabbix.com/internal/agent/statuslistener"
	"zabbix.com/internal/monitor"
	"zabbix.com/pkg/pidfile"
	"zabbix.com/pkg/tls"
	"zabbix.com/pkg/version"
	"zabbix.com/pkg/zbxlib"
	_ "zabbix.com/plugins"
)

const runtimeCommandSendingTimeout = time.Second

const usageMessageFormat = //
`Usage of Zabbix agent 2:
  %[1]s [-c config-file]
  %[1]s [-c config-file] [-v] -p
  %[1]s [-c config-file] [-v] -t item-key
  %[1]s [-c config-file] -R runtime-option
  %[1]s -h
  %[1]s -V

A Zabbix daemon for monitoring of various server parameters.

Options:
%[2]s

Default loadable module location:
  LoadModulePath                 "/usr/local/lib/modules"

Example: %[1]s -c %[3]s

Report bugs to: <https://support.zabbix.com>
Zabbix home page: <http://www.zabbix.com>
Documentation: <https://www.zabbix.com/documentation>
`

const usageMessageFormatRuntimeControlFormat = //
`Perform administrative functions (%s timeout)

    Remote control interface, available commands:
      log_level_increase     Increase log level
      log_level_decrease     Decrease log level
      userparameter_reload   Reload user parameters
      metrics                List available metrics
      version                Display Agent version
`

// variables set at build
var (
	confDefault     string
	applicationName string
)

var (
	manager          *scheduler.Manager
	listeners        []*serverlistener.ServerListener
	serverConnectors []*serverconnector.Connector
	closeChan        = make(chan bool)
	stopChan         = make(chan bool)
	pidFile          *pidfile.File
	pluginsocket     string
)

type AgentUserParamOption struct {
	UserParameter []string `conf:"optional"`
}

func processLoglevelIncreaseCommand(c *runtimecontrol.Client) (err error) {
	if log.IncreaseLogLevel() {
		message := fmt.Sprintf("Increased log level to %s", log.Level())
		log.Infof(message)
		err = c.Reply(message)
		return
	}
	err = fmt.Errorf("Cannot increase log level above %s", log.Level())
	log.Infof(err.Error())

	return
}

func processLoglevelDecreaseCommand(c *runtimecontrol.Client) (err error) {
	if log.DecreaseLogLevel() {
		message := fmt.Sprintf("Decreased log level to %s", log.Level())
		log.Infof(message)
		err = c.Reply(message)
		return
	}
	err = fmt.Errorf("Cannot decrease log level below %s", log.Level())
	log.Infof(err.Error())

	return
}

func processMetricsCommand(c *runtimecontrol.Client) (err error) {
	data := manager.Query("metrics")
	return c.Reply(data)
}

func processVersionCommand(c *runtimecontrol.Client) (err error) {
	data := version.Long()
	return c.Reply(data)
}

func processUserParamReloadCommand(c *runtimecontrol.Client) (err error) {
	var userparams AgentUserParamOption

	if err = conf.LoadUserParams(&userparams); err != nil {
		err = fmt.Errorf("Cannot load user parameters: %s", err)
		log.Infof(err.Error())
		return
	}

	agent.Options.UserParameter = userparams.UserParameter

	if res := manager.QueryUserParams(); res != "ok" {
		err = fmt.Errorf("Failed to reload user parameters: %s", res)
		log.Infof(err.Error())
		return
	}

	message := "User parameters reloaded"
	log.Infof(message)
	err = c.Reply(message)

	return
}

func processRemoteCommand(c *runtimecontrol.Client) (err error) {
	params := strings.Fields(c.Request())
	switch len(params) {
	case 0:
		return errors.New("Empty command")
	case 2:
		return errors.New("Too many commands")
	default:
	}

	switch params[0] {
	case "log_level_increase":
		err = processLoglevelIncreaseCommand(c)
	case "log_level_decrease":
		err = processLoglevelDecreaseCommand(c)
	case "metrics":
		err = processMetricsCommand(c)
	case "version":
		err = processVersionCommand(c)
	case "userparameter_reload":
		err = processUserParamReloadCommand(c)
	default:
		return errors.New("Unknown command")
	}
	return
}

func run() error {
	sigs := createSigsChan()

	control, err := runtimecontrol.New(agent.Options.ControlSocket, runtimeCommandSendingTimeout)
	if err != nil {
		return err
	}

	confirmService()
	control.Start()

loop:
	for {
		select {
		case sig := <-sigs:
			if !handleSig(sig) {
				break loop
			}
		case client := <-control.Client():
			if rerr := processRemoteCommand(client); rerr != nil {
				if rerr = client.Reply("error: " + rerr.Error()); rerr != nil {
					log.Warningf("cannot reply to runtime command: %s", rerr)
				}
			}

			client.Close()
		case serviceStop := <-closeChan:
			if serviceStop {
				break loop
			}
		}
	}
	control.Stop()
	return nil
}

func main() {
	version.Init(
		applicationName,
		tls.CopyrightMessage(),
		copyrightMessageMQTT(),
		copyrightMessageModbus(),
	)

	var (
		confFlag           string
		foregroundFlag     bool
		testFlag           string
		printFlag          bool
		verboseFlag        bool
		versionFlag        bool
		runtimeCommandFlag string
		helpFlag           bool
	)

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
				Name:        "foreground",
				Shorthand:   "f",
				Description: "Run Zabbix agent in foreground",
			},
			Default: true,
			Dest:    &foregroundFlag,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "print",
				Shorthand:   "p",
				Description: "Print known items and exit",
			},
			Default: false,
			Dest:    &printFlag,
		},
		&zbxflag.StringFlag{
			Flag: zbxflag.Flag{
				Name:        "test",
				Shorthand:   "t",
				Description: "Test specified item and exit",
			},
			Default: "",
			Dest:    &testFlag,
		},
		&zbxflag.StringFlag{
			Flag: zbxflag.Flag{
				Name:      "runtime-control",
				Shorthand: "R",
				Description: fmt.Sprintf(
					usageMessageFormatRuntimeControlFormat,
					runtimeCommandSendingTimeout.String(),
				),
			},
			Default: "",
			Dest:    &runtimeCommandFlag,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "verbose",
				Shorthand:   "v",
				Description: "Enable verbose output for metric testing or printing",
			},
			Default: false,
			Dest:    &verboseFlag,
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
		osDependentFlags(),
	}

	f.Register(flag.CommandLine)
	flag.Usage = func() {
		fmt.Printf(
			usageMessageFormat,
			os.Args[0],
			f.Usage(),
			usageMessageExampleConfPath,
		)
	}

	flag.Parse()

	setServiceRun(foregroundFlag)

	if helpFlag {
		flag.Usage()
		os.Exit(0)
	}

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
		version.Display([]string{fmt.Sprintf("Plugin communication protocol version is %s", comms.ProtocolVersion)})
		os.Exit(0)
	}

	err := openEventLog()
	if err != nil {
		fatalExit("", err)
	}

	err = validateExclusiveFlags()
	if err != nil {
		eerr := eventLogErr(err)
		if eerr != nil {
			err = fmt.Errorf("%s and %s", err, eerr)
		}
		fatalExit("", err)
	}

	if err = conf.Load(confFlag, &agent.Options); err != nil {
		if argConfig || !(argTest || argPrint) {
			if eerr := eventLogErr(err); eerr != nil {
				err = fmt.Errorf("%s and %s", err, eerr)
			}
			fatalExit("", err)
		}
		// create default configuration for testing options
		if !argConfig {
			_ = conf.Unmarshal([]byte{}, &agent.Options)
		}
	}

	if err = agent.ValidateOptions(&agent.Options); err != nil {
		if eerr := eventLogErr(err); eerr != nil {
			err = fmt.Errorf("%s and %s", err, eerr)
		}
		fatalExit("cannot validate configuration", err)
	}

	if err = handleWindowsService(confFlag); err != nil {
		if eerr := eventLogErr(err); eerr != nil {
			err = fmt.Errorf("%s and %s", err, eerr)
		}
		fatalExit("", err)
	}

	if err = log.Open(log.Console, log.Warning, "", 0); err != nil {
		fatalExit("cannot initialize logger", err)
	}

	if runtimeCommandFlag != "" {
		if agent.Options.ControlSocket == "" {
			log.Errf("Cannot send runtime command: ControlSocket configuration parameter is not defined")

			os.Exit(0)
		}

		reply, err := runtimecontrol.SendCommand(
			agent.Options.ControlSocket, runtimeCommandFlag, runtimeCommandSendingTimeout,
		)
		if err != nil {
			log.Errf("Cannot send runtime command: %s", err)
		} else {
			log.Infof(reply)
		}

		os.Exit(0)
	}

	if pluginsocket, err = initExternalPlugins(&agent.Options); err != nil {
		fatalExit("cannot register plugins", err)
	}

	defer cleanUpExternal()

	if argTest || argPrint {
		var level int
		if argVerbose {
			level = log.Trace
		} else {
			level = log.None
		}
		if err = log.Open(log.Console, level, "", 0); err != nil {
			fatalExit("cannot initialize logger", err)
		}

		if err = keyaccess.LoadRules(agent.Options.AllowKey, agent.Options.DenyKey); err != nil {
			fatalExit("failed to load key access rules", err)
		}

		if _, err = agent.InitUserParameterPlugin(agent.Options.UserParameter, agent.Options.UnsafeUserParameters,
			agent.Options.UserParameterDir); err != nil {
			fatalExit("cannot initialize user parameters", err)
		}

		var m *scheduler.Manager
		if m, err = scheduler.NewManager(&agent.Options); err != nil {
			fatalExit("cannot create scheduling manager", err)
		}

		m.Start()

		if err = configUpdateItemParameters(m, &agent.Options); err != nil {
			fatalExit("cannot process configuration", err)
		}
		hostnames, err := agent.ValidateHostnames(agent.Options.Hostname)
		if err != nil {
			fatalExit("cannot parse the \"Hostname\" parameter", err)
		}
		agent.FirstHostname = hostnames[0]

		if err = configUpdateItemParameters(m, &agent.Options); err != nil {
			fatalExit("cannot process configuration", err)
		}

		agent.SetPerformTask(scheduler.Scheduler(m).PerformTask)

		if argTest {
			checkMetric(m, testFlag)
		} else {
			checkMetrics(m)
		}

		m.Stop()

		monitor.Wait(monitor.Scheduler)

		cleanUpExternal()

		os.Exit(0)
	}

	if argVerbose {
		fatalExit("", errors.New("verbose parameter can be specified only with test or print parameters"))
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

	if err = log.Open(logType, logLevel, agent.Options.LogFile, agent.Options.LogFileSize); err != nil {
		fatalExit("cannot initialize logger", err)
	}

	zbxlib.SetLogLevel(logLevel)

	greeting := fmt.Sprintf("Starting Zabbix Agent 2 (%s)", version.Long())
	log.Infof(greeting)

	addresses, err := serverconnector.ParseServerActive()
	if err != nil {
		fatalExit("cannot parse the \"ServerActive\" parameter", err)
	}

	if tlsConfig, err := agent.GetTLSConfig(&agent.Options); err != nil {
		fatalExit("cannot use encryption configuration", err)
	} else {
		if tlsConfig != nil {
			if err = tls.Init(tlsConfig); err != nil {
				fatalExit("cannot configure encryption", err)
			}
		}
	}

	if pidFile, err = pidfile.New(agent.Options.PidFile); err != nil {
		fatalExit("cannot initialize PID file", err)
	}
	defer pidFile.Delete()

	log.Infof("using configuration file: %s", confFlag)

	if err = keyaccess.LoadRules(agent.Options.AllowKey, agent.Options.DenyKey); err != nil {
		fatalExit("Failed to load key access rules", err)
		os.Exit(1)
	}

	if _, err = agent.InitUserParameterPlugin(agent.Options.UserParameter, agent.Options.UnsafeUserParameters,
		agent.Options.UserParameterDir); err != nil {
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
	if err = loadOSDependentItems(); err != nil {
		fatalExit("cannot load os dependent items", err)
	}

	if foregroundFlag {
		if agent.Options.LogType != "console" {
			fmt.Println(greeting)
		}
	}

	manager.Start()

	if err = configUpdateItemParameters(manager, &agent.Options); err != nil {
		fatalExit("cannot process configuration", err)
	}

	hostnames, err := agent.ValidateHostnames(agent.Options.Hostname)
	if err != nil {
		fatalExit("cannot parse the \"Hostname\" parameter", err)
	}
	agent.FirstHostname = hostnames[0]
	hostmessage := fmt.Sprintf("Zabbix Agent2 hostname: [%s]", agent.Options.Hostname)
	log.Infof(hostmessage)
	if foregroundFlag {
		if agent.Options.LogType != "console" {
			fmt.Println(hostmessage)
		}
		fmt.Println("Press Ctrl+C to exit.")
	}
	if err = resultcache.Prepare(&agent.Options, addresses, hostnames); err != nil {
		fatalExit("cannot prepare result cache", err)
	}

	serverConnectors = make([]*serverconnector.Connector, len(addresses)*len(hostnames))

	var idx int
	for i := 0; i < len(addresses); i++ {
		for j := 0; j < len(hostnames); j++ {
			if serverConnectors[idx], err = serverconnector.New(manager, addresses[i], hostnames[j], &agent.Options); err != nil {
				fatalExit("cannot create server connector", err)
			}
			serverConnectors[idx].Start()
			agent.SetHostname(serverConnectors[idx].ClientID(), hostnames[j])
			idx++
		}
	}
	agent.SetPerformTask(manager.PerformTask)

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
	// is stopped and its input channel is full.
	for i := 0; i < len(serverConnectors); i++ {
		serverConnectors[i].StopCache()
	}
	monitor.Wait(monitor.Output)
	farewell := fmt.Sprintf("Zabbix Agent 2 stopped. (%s)", version.Long())
	log.Infof(farewell)

	if foregroundFlag && agent.Options.LogType != "console" {
		fmt.Println(farewell)
	}

	waitServiceClose()
}

func fatalExit(message string, err error) {
	fatalCloseOSItems()

	if pluginsocket != "" {
		cleanUpExternal()
	}

	if len(message) == 0 {
		message = err.Error()
	} else {
		message = fmt.Sprintf("%s: %s", message, err.Error())
	}

	fmt.Fprintf(os.Stderr, "zabbix_agent2 [%d]: ERROR: %s\n", os.Getpid(), message)

	if agent.Options.LogType == "file" {
		log.Critf("%s", message)
	}

	os.Exit(1)
}
