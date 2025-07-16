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
	"golang.zabbix.com/agent2/plugins/redis/info"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
)

const (
	keyConfig  = "redis.config"
	keyInfo    = "redis.info"
	keyPing    = "redis.ping"
	keySlowlog = "redis.slowlog.count"
)

var (
	maxAuthPassLen = 512
	uriDefaults    = &uri.Defaults{Scheme: "tcp", Port: "6379"}
)

// Common params: [URI|Session][,Password][,User]
//
//nolint:gochecknoglobals
var (
	paramURI = metric.NewConnParam("URI", "URI to connect or session name.").
			WithDefault(uriDefaults.Scheme + "://localhost:" + uriDefaults.Port).WithSession().
			WithValidator(uri.URIValidator{Defaults: uriDefaults, AllowedSchemes: []string{"tcp", "unix"}})
	paramPassword = metric.NewConnParam("Password", "Redis password.").WithDefault("").
			WithValidator(metric.LenValidator{Max: &maxAuthPassLen})
	paramUser          = metric.NewConnParam("User", "Redis user.").WithDefault("default")
	paramTLSConnect    = metric.NewSessionOnlyParam("TLSConnect", "DB connection encryption type.").WithDefault("")
	paramTLSCaFile     = metric.NewSessionOnlyParam("TLSCAFile", "TLS ca file path.").WithDefault("")
	paramTLSServerName = metric.NewSessionOnlyParam("TLSServerName", "TLS server name.").WithDefault("")
	paramTLSCertFile   = metric.NewSessionOnlyParam("TLSCertFile", "TLS cert file path.").WithDefault("")
	paramTLSKeyFile    = metric.NewSessionOnlyParam("TLSKeyFile", "TLS key file path.").WithDefault("")
)

var metrics = metric.MetricSet{
	keyConfig: metric.NewUnordered("Returns configuration parameters of Redis server.",
		[]*metric.Param{
			paramURI, paramPassword,
			metric.NewParam("Pattern", "Glob-style pattern to filter configuration parameters.").
				WithDefault("*"),
			paramUser, paramTLSConnect, paramTLSCaFile, paramTLSServerName, paramTLSCertFile, paramTLSKeyFile,
		}, false),

	keyInfo: metric.NewUnordered("Returns output of INFO command.",
		[]*metric.Param{
			paramURI, paramPassword,
			metric.NewParam("Section", "Section of information to return.").WithDefault("default"),
			paramUser, paramTLSConnect, paramTLSCaFile, paramTLSServerName, paramTLSCertFile, paramTLSKeyFile,
		}, false),

	keyPing: metric.New("Test if connection is alive or not.",
		[]*metric.Param{paramURI, paramPassword, paramUser,
			paramTLSConnect, paramTLSCaFile, paramTLSServerName, paramTLSCertFile, paramTLSKeyFile}, false),

	keySlowlog: metric.New("Returns the number of slow log entries since Redis has been started.",
		[]*metric.Param{paramURI, paramPassword, paramUser,
			paramTLSConnect, paramTLSCaFile, paramTLSServerName, paramTLSCertFile, paramTLSKeyFile}, false),
}

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(conn conn.RedisClient, params map[string]string) (res any, err error)

//nolint:gochecknoinits //legacy implementation
func init() {
	err := plugin.RegisterMetrics(&impl, info.PluginName, metrics.List()...)
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
