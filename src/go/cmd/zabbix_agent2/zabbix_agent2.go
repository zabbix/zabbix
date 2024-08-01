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
	"errors"
	"flag"
	"fmt"
	"io"
	"os"
	"os/signal"
	"path/filepath"
	"strings"
	"syscall"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/keyaccess"
	"golang.zabbix.com/agent2/internal/agent/resultcache"
	"golang.zabbix.com/agent2/internal/agent/runtimecontrol"
	"golang.zabbix.com/agent2/internal/agent/scheduler"
	"golang.zabbix.com/agent2/internal/agent/serverconnector"
	"golang.zabbix.com/agent2/internal/agent/serverlistener"
	"golang.zabbix.com/agent2/internal/agent/statuslistener"
	"golang.zabbix.com/agent2/internal/monitor"
	"golang.zabbix.com/agent2/pkg/pidfile"
	"golang.zabbix.com/agent2/pkg/tls"
	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/agent2/pkg/zbxlib"
	_ "golang.zabbix.com/agent2/plugins"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/zbxerr"
	"golang.zabbix.com/sdk/zbxflag"
)

const runtimeCommandSendingTimeout = time.Second

const usageMessageFormatRuntimeControlFormat = //
`Perform administrative functions (%s timeout)

    Remote control interface, available commands:
      log_level_increase     Increase log level
      log_level_decrease     Decrease log level
      userparameter_reload   Reload user parameters
      metrics                List available metrics
      version                Display Agent version
`

const usageMessageFormat = //
`Usage of Zabbix agent 2:
  %[1]s [-c config-file]
  %[1]s [-c config-file] [-v] -p
  %[1]s [-c config-file] [-v] -t item-key
  %[1]s [-c config-file] -T
  %[1]s [-c config-file] -R runtime-option
  %[1]s -h
  %[1]s -V
`

const helpMessageFormat = //
`A Zabbix daemon for monitoring of various server parameters.

Options:
%[1]s

Example: zabbix_agent2 -c %[2]s

Report bugs to: <https://support.zabbix.com>
Zabbix home page: <https://www.zabbix.com>
Documentation: <https://www.zabbix.com/documentation>
`

// variables set at build
var (
	confDefault     string
	applicationName string
)

//nolint:gochecknoglobals
var (
	manager          *scheduler.Manager
	listeners        []*serverlistener.ServerListener
	serverConnectors []*serverconnector.Connector
	closeChan        = make(chan bool)
	pidFile          *pidfile.File
	pluginSocket     string
)

type AgentUserParamOption struct {
	UserParameter []string `conf:"optional"`
}

// Arguments contains values of command line arguments.
type Arguments struct {
	configPath     string
	foreground     bool
	test           string
	testConfig     bool
	print          bool
	verbose        bool
	version        bool
	runtimeCommand string
	help           bool
}

func main() {
	err := run()
	if err != nil {
		fatalCloseOSItems()

		cliErr := &errs.CLIError{}

		if !errors.As(err, &cliErr) {
			fmt.Fprintf(
				os.Stderr,
				"zabbix_agent2 [%d]: ERROR: %s\n",
				os.Getpid(),
				err.Error(),
			)
			os.Exit(1)
		}

		fmt.Fprintf(
			os.Stderr,
			"zabbix_agent2 [%d]: ERROR: %s\n",
			os.Getpid(),
			cliErr.Message,
		)
		os.Exit(cliErr.ExitCode)
	}

	os.Exit(0)
}

//nolint:gocognit,gocyclo,cyclop
func run() error {
	version.Init(
		applicationName,
		tls.CopyrightMessage(),
		copyrightMessageMQTT(),
		copyrightMessageModbus(),
	)

	flagsUsage, args, err := parseArgs()
	if err != nil {
		return errs.Wrap(err, "failed to parse args")
	}

	if args.help {
		fmt.Print(helpMessage(flagsUsage))

		return nil
	}

	setServiceRun(args.foreground)

	if args.version {
		version.Display([]string{fmt.Sprintf(
			"Plugin communication protocol version is %s",
			comms.ProtocolVersion,
		)})

		return nil
	}

	err = openEventLog()
	if err != nil {
		return errs.Wrap(err, "failed to open event log")
	}

	err = validateExclusiveFlags(args)
	if err != nil {
		return eventLogErr(errs.Wrap(err, "failed to validate exclusive flags"))
	}

	if args.testConfig {
		fmt.Fprintf(os.Stdout, "Validating configuration file %q\n", args.configPath)
	}

	err = conf.Load(args.configPath, &agent.Options)
	if err != nil {
		if args.configPath != "" || args.testConfig {
			return eventLogErr(
				errors.Join(
					errs.NewCLIError(err.Error(), 1),
					errs.Wrap(err, "failed to load configuration"),
				),
			)
		}

		// create default configuration for testing options
		// pass empty string to config arg to trigger this
		err = conf.UnmarshalStrict([]byte{}, &agent.Options)
		if err != nil {
			return errs.Wrap(err, "failed to create default configuration")
		}

		log.Infof("Using default configuration")
	}

	err = agent.ValidateOptions(&agent.Options)
	if err != nil {
		return eventLogErr(errs.Wrap(err, "cannot validate configuration"))
	}

	err = handleWindowsService(args.configPath)
	if err != nil {
		return eventLogErr(errs.Wrap(err, "failed to handle Windows service"))
	}

	err = log.Open(log.Console, log.Warning, "", 0)
	if err != nil {
		return errs.Wrap(err, "failed to open console logger")
	}

	if args.runtimeCommand != "" {
		if args.runtimeCommand == "help" {
			fmt.Fprintf(
				os.Stdout,
				usageMessageFormatRuntimeControlFormat,
				runtimeCommandSendingTimeout.String(),
			)

			return nil
		}

		if agent.Options.ControlSocket == "" {
			return errs.New("cannot send remote command: configuration parameter ControlSocket is not defined")
		}

		reply, err := runtimecontrol.SendCommand(
			agent.Options.ControlSocket, args.runtimeCommand, runtimeCommandSendingTimeout,
		)
		if err != nil {
			return errs.Wrap(err, "cannot send remote command")
		}

		fmt.Fprintf(os.Stderr, "%s\n", reply)

		return nil
	}

	systemOpt, err := agent.Options.LoadSystemOptions()
	if err != nil {
		fatalExit("cannot initialize plugin system option", err)
	}

	pluginSocket, err = initExternalPlugins(&agent.Options, systemOpt)
	if err != nil {
		return errs.Wrap(err, "cannot register plugins")
	}

	defer cleanUpExternal()

	if args.test != "" || args.print || args.testConfig {
		m, err := prepareMetricPrintManager(args.verbose, systemOpt)
		if err != nil {
			return errs.Wrap(err, "failed to prepare metric print manager")
		}

		if args.test != "" {
			checkMetric(m, args.test)
		} else if args.print {
			checkMetrics(m)
		}

		m.Stop()
		monitor.Wait(monitor.Scheduler)
		cleanUpExternal()

		if args.testConfig {
			fmt.Print("Validation successful\n")
		}

		return nil
	}

	if args.verbose {
		return errs.New("verbose parameter can be specified only with test or print parameters")
	}

	err = runAgent(args.foreground, args.configPath)
	if err != nil {
		if agent.Options.LogType == "file" {
			log.Critf("%s", err.Error())
		}

		return errs.Wrap(err, "failed to run agent")
	}

	return nil
}

//nolint:gocognit,gocyclo,cyclop,maintidx
func runAgent(isForeground bool, configPath string) error {
	var logType int

	switch agent.Options.LogType {
	case "system":
		logType = log.System
	case "console":
		logType = log.Console
	case "file":
		logType = log.File
	}

	err := log.Open(
		logType,
		agent.Options.DebugLevel,
		agent.Options.LogFile,
		agent.Options.LogFileSize,
	)
	if err != nil {
		return errs.Wrap(err, "cannot initialize logger")
	}

	zbxlib.SetLogLevel(agent.Options.DebugLevel)

	greeting := fmt.Sprintf("Starting Zabbix Agent 2 (%s)", version.Long())
	log.Infof(greeting)

	addresses, err := serverconnector.ParseServerActive()
	if err != nil {
		return errs.Wrap(err, "cannot parse the \"ServerActive\" parameter")
	}

	tlsConfig, err := agent.GetTLSConfig(&agent.Options)
	if err != nil {
		return errs.Wrap(err, "failed to get encryption configuration")
	}

	if tlsConfig != nil {
		err = tls.Init(tlsConfig)
		if err != nil {
			return errs.Wrap(err, "failed to initialize encryption")
		}
	}

	pidFile, err = pidfile.New(agent.Options.PidFile)
	if err != nil {
		return errs.Wrap(err, "cannot initialize PID file")
	}

	defer pidFile.Delete()

	log.Infof("using configuration file: %s", configPath)

	if err = keyaccess.LoadRules(agent.Options.AllowKey, agent.Options.DenyKey); err != nil {
		return errs.Wrap(err, "Failed to load key access rules")
	}

	_, err = agent.InitUserParameterPlugin(
		agent.Options.UserParameter,
		agent.Options.UnsafeUserParameters,
		agent.Options.UserParameterDir,
	)
	if err != nil {
		return errs.Wrap(err, "cannot initialize user parameters")
	}

	manager, err = scheduler.NewManager(&agent.Options, systemOpt)
	if err != nil {
		return errs.Wrap(err, "cannot create scheduling manager")
	}

	// replacement of deprecated StartAgents
	if len(agent.Options.Server) != 0 {
		var listenIPs []string

		listenIPs, err = serverlistener.ParseListenIP(&agent.Options)
		if err != nil {
			return errs.Wrap(err, "cannot parse \"ListenIP\" parameter")
		}

		for i := 0; i < len(listenIPs); i++ {
			listener := serverlistener.New(
				i,
				manager,
				listenIPs[i],
				&agent.Options,
			)
			listeners = append(listeners, listener)
		}
	}

	err = loadOSDependentItems()
	if err != nil {
		return errs.Wrap(err, "cannot load os dependent items")
	}

	if isForeground {
		if agent.Options.LogType != "console" {
			fmt.Println(greeting)
		}
	}

	manager.Start()

	err = configUpdateItemParameters(manager, &agent.Options)
	if err != nil {
		return errs.Wrap(err, "cannot process configuration")
	}

	hostnames, err := agent.ValidateHostnames(agent.Options.Hostname)
	if err != nil {
		return errs.Wrap(err, "cannot parse the \"Hostname\" parameter")
	}

	agent.FirstHostname = hostnames[0]
	hostmessage := fmt.Sprintf(
		"Zabbix Agent2 hostname: [%s]",
		agent.Options.Hostname,
	)
	log.Infof(hostmessage)

	if isForeground {
		if agent.Options.LogType != "console" {
			fmt.Fprintln(os.Stdout, hostmessage)
		}

		fmt.Fprintln(os.Stdout, "Press Ctrl+C to exit.")
	}

	err = resultcache.Prepare(&agent.Options, addresses, hostnames)
	if err != nil {
		return errs.Wrap(err, "cannot prepare result cache")
	}

	serverConnectors = make(
		[]*serverconnector.Connector,
		len(addresses)*len(hostnames),
	)

	var idx int
	for i := 0; i < len(addresses); i++ {
		for j := 0; j < len(hostnames); j++ {
			serverConnectors[idx], err = serverconnector.New(
				manager, addresses[i], hostnames[j], &agent.Options,
			)
			if err != nil {
				return errs.Wrap(err, "cannot create server connector")
			}

			serverConnectors[idx].Start()
			agent.SetHostname(serverConnectors[idx].ClientID(), hostnames[j])
			idx++
		}
	}

	agent.SetPerformTask(manager.PerformTask)

	for _, listener := range listeners {
		err = listener.Start()
		if err != nil {
			return errs.Wrap(err, "cannot start server listener")
		}
	}

	if agent.Options.StatusPort != 0 {
		err = statuslistener.Start(manager, configPath)
		if err != nil {
			return errs.Wrap(err, "cannot start HTTP listener")
		}
	}

	err = waitStop()
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

	// split shutdown in two steps to ensure that result cache is still running
	// while manager is being stopped, because there might be pending exporters
	// that could block if result cache is stopped and its input channel is full.
	for _, connector := range serverConnectors {
		connector.StopCache()
	}

	monitor.Wait(monitor.Output)
	farewell := fmt.Sprintf("Zabbix Agent 2 stopped. (%s)", version.Long())
	log.Infof(farewell)

	if isForeground && agent.Options.LogType != "console" {
		fmt.Println(farewell)
	}

	waitServiceClose()

	return nil
}

func parseArgs() (string, *Arguments, error) {
	fs := flag.NewFlagSet("", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	// set to empty cause lib triggers Usage func on --help/-h and invalid
	// flags error, we want to handle these cases manually
	fs.Usage = func() {}

	args := &Arguments{}

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
			Dest:    &args.configPath,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "foreground",
				Shorthand:   "f",
				Description: "Run Zabbix agent in foreground",
			},
			Default: true,
			Dest:    &args.foreground,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "print",
				Shorthand:   "p",
				Description: "Print known items and exit",
			},
			Default: false,
			Dest:    &args.print,
		},
		&zbxflag.StringFlag{
			Flag: zbxflag.Flag{
				Name:        "test",
				Shorthand:   "t",
				Description: "Test specified item and exit",
			},
			Default: "",
			Dest:    &args.test,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "test-config",
				Shorthand:   "T",
				Description: "Validate configuration file and exit",
			},
			Default: false,
			Dest:    &args.testConfig,
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
			Dest:    &args.runtimeCommand,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "verbose",
				Shorthand:   "v",
				Description: "Enable verbose output for metric testing or printing",
			},
			Default: false,
			Dest:    &args.verbose,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "help",
				Shorthand:   "h",
				Description: "Display this help message",
			},
			Default: false,
			Dest:    &args.help,
		},
		&zbxflag.BoolFlag{
			Flag: zbxflag.Flag{
				Name:        "version",
				Shorthand:   "V",
				Description: "Print program version and exit",
			},
			Default: false,
			Dest:    &args.version,
		},
		osDependentFlags(),
	}

	f.Register(fs)

	err := fs.Parse(os.Args[1:])
	if err != nil {
		fmt.Fprint(os.Stdout, usageMessage())

		return "", nil, errors.Join(
			errs.NewCLIError(err.Error(), 1),
			errs.Wrap(err, "failed to parse command line arguments"),
		)
	}

	return f.Usage(), args, nil
}

func usageMessage() string {
	return fmt.Sprintf(
		usageMessageFormat+osDependentUsageMessageFormat,
		filepath.Base(os.Args[0]),
	)
}

func helpMessage(flagsUsage string) string {
	return fmt.Sprintf(
		"%s\n%s",
		usageMessage(),
		fmt.Sprintf(
			helpMessageFormat,
			flagsUsage,
			usageMessageExampleConfPath,
		),
	)
}

func prepareMetricPrintManager(verbose bool, pluginSysOpt agent.PluginSystemOptions) (*scheduler.Manager, error) {
	level := log.None

	if verbose {
		level = log.Trace
	}

	err := log.Open(log.Console, level, "", 0)
	if err != nil {
		return nil, zbxerr.New("failed to initialize logger").Wrap(err)
	}

	err = keyaccess.LoadRules(agent.Options.AllowKey, agent.Options.DenyKey)
	if err != nil {
		return nil, zbxerr.New("failed to load key access rules").Wrap(err)
	}

	_, err = agent.InitUserParameterPlugin(
		agent.Options.UserParameter,
		agent.Options.UnsafeUserParameters,
		agent.Options.UserParameterDir,
	)
	if err != nil {
		return nil, zbxerr.New("failed to initialize user parameters").Wrap(err)
	}

	err = loadOSDependentItems()
	if err != nil {
		return nil, zbxerr.New("cannot load os dependent items").Wrap(err)
	}

	m, err := scheduler.NewManager(&agent.Options, pluginSysOpt)
	if err != nil {
		return nil, zbxerr.New("failed to create scheduling manager").Wrap(err)
	}

	m.Start()

	err = configUpdateItemParameters(m, &agent.Options)
	if err != nil {
		return nil, zbxerr.New("failed to process configuration").Wrap(err)
	}

	hostnames, err := agent.ValidateHostnames(agent.Options.Hostname)
	if err != nil {
		return nil, zbxerr.New(`failed to parse "Hostname" parameter`).Wrap(err)
	}

	agent.FirstHostname = hostnames[0]

	err = configUpdateItemParameters(m, &agent.Options)
	if err != nil {
		return nil, zbxerr.New("failed to uptade item parameters").Wrap(err)
	}

	agent.SetPerformTask(scheduler.Scheduler(m).PerformTask)

	return m, nil
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
		err = fmt.Errorf("Cannot load user parameters: %s", err.Error())
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
	case 2: //nolint:gomnd
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

func waitStop() error {
	sigs := make(chan os.Signal, 1)
	signal.Notify(sigs, syscall.SIGINT, syscall.SIGTERM)

	control, err := runtimecontrol.New(agent.Options.ControlSocket, runtimeCommandSendingTimeout)
	if err != nil {
		return err
	}

	confirmService()
	control.Start()

	defer control.Stop()

	for {
		select {
		case <-sigs:
			sendServiceStop()

			return nil
		case client := <-control.Client():
			err := processRemoteCommand(client)
			if err != nil {
				rerr := client.Reply(fmt.Sprintf("error: %s", err.Error()))
				if rerr != nil {
					log.Warningf("cannot reply to remote command: %s", rerr)
				}
			}

			client.Close()
		case serviceStop := <-closeChan:
			if serviceStop {
				return nil
			}
		}
	}
}
