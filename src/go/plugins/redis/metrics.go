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

package redis

import (
	"golang.zabbix.com/agent2/plugins/redis/conn"
	"golang.zabbix.com/agent2/plugins/redis/handlers"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/tlsconfig"
	"golang.zabbix.com/sdk/uri"
)

const (
	keyConfig  = "redis.config"
	keyInfo    = "redis.info"
	keyPing    = "redis.ping"
	keySlowlog = "redis.slowlog.count"

	pluginName = "Redis"
)

var (
	//nolint:gochecknoglobals // just a runtime constant
	maxAuthPassLen = 512
	//nolint:gochecknoglobals // just a runtime constant
	uriDefaults = &uri.Defaults{Scheme: "tcp", Port: "6379"}
)

// Common params: [URI|session][,Password][,User].
var (

	//nolint:gochecknoglobals // just a runtime constant
	paramURI = metric.NewConnParam("URI", "URI to connect or session name.").
			WithDefault(uriDefaults.Scheme + "://localhost:" + uriDefaults.Port).
			WithSession().
			WithValidator(uri.URIValidator{
			Defaults:       uriDefaults,
			AllowedSchemes: []string{"tcp", "unix"}},
		)
	//nolint:gochecknoglobals // just a runtime constant
	paramPassword = metric.NewConnParam("Password", "Redis password.").WithDefault("").
			WithValidator(metric.LenValidator{Max: &maxAuthPassLen})
	//nolint:gochecknoglobals // just a runtime constant
	paramUser = metric.NewConnParam("User", "Redis user.").WithDefault("default")

	//nolint:gochecknoglobals // just a runtime constant
	paramTLSConnect = metric.NewSessionOnlyParam(
		string(comms.TLSConnect),
		"DB connection encryption type.").
		WithDefault("").
		WithValidator(metric.SetValidator{
			Set: []string{
				"",
				string(tlsconfig.Disabled),
				string(tlsconfig.Required),
				string(tlsconfig.VerifyCA),
				string(tlsconfig.VerifyFull),
			},
		})

	//nolint:gochecknoglobals // just a runtime constant
	paramTLSCaFile = metric.NewSessionOnlyParam(
		string(comms.TLSCAFile),
		"TLS ca file path.").
		WithDefault("")

	//nolint:gochecknoglobals // just a runtime constant
	paramTLSCertFile = metric.NewSessionOnlyParam(
		string(comms.TLSCertFile),
		"TLS cert file path.").
		WithDefault("")

	//nolint:gochecknoglobals // just a runtime constant
	paramTLSKeyFile = metric.NewSessionOnlyParam(
		string(comms.TLSKeyFile),
		"TLS key file path.").
		WithDefault("")
)

//nolint:gochecknoglobals // just a runtime constant
var metrics = metric.MetricSet{
	keyConfig: metric.NewUnordered("Returns configuration parameters of Redis server.",
		[]*metric.Param{
			paramURI, paramPassword,
			metric.NewParam("Pattern", "Glob-style pattern to filter configuration parameters.").
				WithDefault("*"),
			paramUser, paramTLSConnect, paramTLSCaFile, paramTLSCertFile, paramTLSKeyFile,
		}, false),

	keyInfo: metric.NewUnordered("Returns output of INFO command.",
		[]*metric.Param{
			paramURI, paramPassword,
			metric.NewParam("Section", "Section of information to return.").WithDefault("default"),
			paramUser, paramTLSConnect, paramTLSCaFile, paramTLSCertFile, paramTLSKeyFile,
		}, false),

	keyPing: metric.New("Test if connection is alive or not.",
		[]*metric.Param{paramURI, paramPassword, paramUser,
			paramTLSConnect, paramTLSCaFile, paramTLSCertFile, paramTLSKeyFile}, false),

	keySlowlog: metric.New("Returns the number of slow log entries since Redis has been started.",
		[]*metric.Param{paramURI, paramPassword, paramUser,
			paramTLSConnect, paramTLSCaFile, paramTLSCertFile, paramTLSKeyFile}, false),
}

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(redisClient conn.RedisClient, params map[string]string) (res any, err error)

//nolint:gochecknoinits //legacy implementation
func init() {
	err := plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// getHandlerFunc returns a handlerFunc related to a given key.
func getHandlerFunc(key string) handlerFunc {
	switch key {
	case keyConfig:
		return handlers.ConfigHandler
	case keyInfo:
		return handlers.InfoHandler
	case keyPing:
		return handlers.PingHandler
	case keySlowlog:
		return handlers.SlowlogHandler

	default:
		return nil
	}
}
