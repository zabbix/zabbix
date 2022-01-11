/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package redis

import (
	"bufio"
	"encoding/json"
	"regexp"
	"strings"

	"zabbix.com/pkg/zbxerr"

	"github.com/mediocregopher/radix/v3"
)

type infoSection string
type infoKey string
type infoKeySpace map[infoKey]interface{}
type infoExtKey string
type infoExtKeySpace map[infoExtKey]string
type redisInfo map[infoSection]infoKeySpace

var redisSlaveMetricRE = regexp.MustCompile(`^slave\d+`)

// parseRedisInfo parses an output of 'INFO' command.
// https://redis.io/commands/info
func parseRedisInfo(info string) (res redisInfo, err error) {
	var (
		section infoSection
	)

	scanner := bufio.NewScanner(strings.NewReader(info))
	res = make(redisInfo)

	for scanner.Scan() {
		line := scanner.Text()

		if len(line) == 0 {
			continue
		}

		// Names of sections are preceded by '#'.
		if line[0] == '#' {
			section = infoSection(line[2:])
			if _, ok := res[section]; !ok {
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

	if err = scanner.Err(); err != nil {
		return nil, err
	}

	if len(res) == 0 {
		return nil, zbxerr.ErrorEmptyResult
	}

	return res, nil
}

// infoHandler gets an output of 'INFO' command, parses it and returns it in JSON format.
func infoHandler(conn redisClient, params map[string]string) (interface{}, error) {
	var res string

	section := infoSection(strings.ToLower(params["Section"]))

	if err := conn.Query(radix.Cmd(&res, "INFO", string(section))); err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	redisInfo, err := parseRedisInfo(res)
	if err != nil {
		return nil, err
	}

	jsonRes, err := json.Marshal(redisInfo)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
