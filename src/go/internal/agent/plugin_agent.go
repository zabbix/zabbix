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

package agent

import (
	"errors"
	"fmt"
	"time"
	"unicode/utf8"

	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

var (
	impl          Plugin
	hostnames     = map[uint64]string{}
	FirstHostname string
	performTask   PerformTask
)

type PerformTask func(key string, timeout time.Duration, clientID uint64) (result *string, err error)

// Plugin -
type Plugin struct {
	plugin.Base
}

func init() {
	err := plugin.RegisterMetrics(
		&impl, "Agent",
		"agent.hostname", "Returns Hostname from agent configuration.",
		"agent.hostmetadata", "Returns string with agent host metadata.",
		"agent.ping", "Returns agent availability check result.",
		"agent.variant", "Returns agent variant.",
		"agent.version", "Version of Zabbix agent.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func SetHostname(clientID uint64, hostname string) {
	hostnames[clientID] = hostname
}

func getHostname(clientID uint64) string {
	return hostnames[clientID]
}

func SetPerformTask(f PerformTask) {
	performTask = f
}

func processConfigItem(timeout time.Duration, name, value, item string, length int, clientID uint64) (string, error) {
	if len(item) == 0 {
		return value, nil
	}

	if len(value) > 0 {
		log.Warningf("both \"%s\" and \"%sItem\" configuration parameter defined, using \"%s\".", name, name, name)

		return value, nil
	}

	var err error
	var taskResult *string
	taskResult, err = performTask(item, timeout, clientID)
	if err != nil {
		return "", err
	} else if taskResult == nil {
		return "", fmt.Errorf("no value was received")
	}

	value = *taskResult

	if !utf8.ValidString(value) {
		return "", fmt.Errorf("value is not a UTF-8 string.")
	}

	if utf8.RuneCountInString(value) > length {
		log.Warningf("the returned value of \"%s\" item specified by \"%sItem\" configuration parameter"+
			" is too long, using first %d characters.", item, name, length)

		return CutAfterN(value, length), nil
	}

	return value, nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) > 0 {
		return nil, errors.New("Too many parameters.")
	}

	switch key {
	case "agent.hostname":
		if ctx.ClientID() > MaxBuiltinClientID {
			return getHostname(ctx.ClientID()), nil
		}

		return FirstHostname, nil
	case "agent.hostmetadata":
		if Options.HostMetadataItem == "agent.hostmetadata" {
			return nil, errors.New("Invalid recursive HostMetadataItem value.")
		}

		return processConfigItem(time.Duration(ctx.Timeout())*time.Second, "HostMetadata",
			Options.HostMetadata, Options.HostMetadataItem, HostMetadataLen, LocalChecksClientID)
	case "agent.ping":
		return 1, nil
	case "agent.variant":
		return 2, nil
	case "agent.version":
		return version.Long(), nil
	}

	return nil, fmt.Errorf("Not implemented: %s", key)
}
