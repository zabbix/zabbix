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

package handlers

import (
	"bufio"
	"encoding/json"
	"regexp"
	"strings"

	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/agent2/plugins/redis/conn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

var redisSlaveMetricRE = regexp.MustCompile(`^slave\d+`)

type infoSection string
type infoKey string
type infoKeySpace map[infoKey]any
type infoExtKey string
type infoExtKeySpace map[infoExtKey]string
type redisInfo map[infoSection]infoKeySpace

// parseRedisInfo parses an output of 'INFO' command.
// https://redis.io/commands/info
//
//nolint:gocyclo,cyclop // this is a parser.
func parseRedisInfo(info string) (redisInfo, error) {
	var (
		section infoSection
	)

	scanner := bufio.NewScanner(strings.NewReader(info))
	res := make(redisInfo)

	for scanner.Scan() {
		line := scanner.Text()

		if line == "" {
			continue
		}

		// Names of sections are preceded by '#'.
		if line[0] == '#' {
			section = infoSection(line[2:])

			_, ok := res[section]
			if !ok {
				res[section] = make(infoKeySpace)
			}

			continue
		}

		// Each parameter represented in the 'key:value' format.
		kv := strings.SplitN(line, ":", 2)
		if len(kv) != 2 {
			continue
		}

		key := infoKey(kv[0])
		value := strings.TrimSpace(kv[1])

		// Followed sections has a bit more complicated format.
		// E.g: dbXXX: keys=XXX,expires=XXX
		if section == "Keyspace" || section == "Commandstats" ||
			(section == "Replication" && redisSlaveMetricRE.MatchString(string(key))) {
			extKeySpace := make(infoExtKeySpace)

			for _, ksParams := range strings.Split(value, ",") {
				ksParts := strings.Split(ksParams, "=")
				extKeySpace[infoExtKey(ksParts[0])] = ksParts[1]
			}

			res[section][key] = extKeySpace

			continue
		}

		if len(section) == 0 {
			return nil, zbxerr.ErrorCannotParseResult
		}

		res[section][key] = value
	}

	err := scanner.Err()
	if err != nil {
		return nil, errs.Wrap(err, "failed to parse info")
	}

	if len(res) == 0 {
		return nil, zbxerr.ErrorEmptyResult
	}

	return res, nil
}

// InfoHandler gets an output of 'INFO' command, parses it and returns it in JSON format.
func InfoHandler(redisClient conn.RedisClient, params map[string]string) (any, error) {
	var res string

	section := infoSection(strings.ToLower(params["Section"]))

	err := redisClient.Query(radix.Cmd(&res, "INFO", string(section)))
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	parsedRedisInfo, err := parseRedisInfo(res)
	if err != nil {
		return nil, err
	}

	jsonRes, err := json.Marshal(parsedRedisInfo)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return string(jsonRes), nil
}
