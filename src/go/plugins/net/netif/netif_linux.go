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

package netif

/*
#include <linux/wireless.h>
#include <linux/ethtool.h>
#include <linux/sockios.h>
#include <stdlib.h>
#include <sys/socket.h>
#include <sys/ioctl.h>
#include <sys/types.h>
#include <string.h>
#include <unistd.h>

typedef struct
{
	int64_t	bitrate;
	int	autoneg;
	int	administrative_state;
	int	operational_state;
	int	wifiinfo;
	int	signal_level;
	int	link_quality;
	int	noise_level;
	char	ssid[IW_ESSID_MAX_SIZE + 1];
} ll_info_t;

int	get_ll_info(const char* interface, ll_info_t* info)
{
	int sockfd;
	struct iwreq wreq;
	struct iw_statistics stats;
	struct iw_range range;
	char buffer[IW_ESSID_MAX_SIZE + 1];
	struct ifreq ifr;
	struct ethtool_cmd ecmd;

	memset(info, 0, sizeof(ll_info_t));
	sockfd = socket(AF_INET, SOCK_DGRAM, 0);

	if (-1 == sockfd)
	{
		return -1;
	}

	memset(&wreq, 0, sizeof(struct iwreq));
	strncpy(wreq.ifr_name, interface, IFNAMSIZ - 1);
	wreq.u.data.pointer = (caddr_t) &stats;
	wreq.u.data.length = sizeof(struct iw_statistics);
	wreq.u.data.flags = 1;

	if (0 == ioctl(sockfd, SIOCGIWSTATS, &wreq))
	{
		memset(&range, 0, sizeof(struct iw_range));
		wreq.u.data.pointer = (caddr_t) &range;
		wreq.u.data.length = sizeof(struct iw_range);
		wreq.u.data.flags = 0;

		if (0 == ioctl(sockfd, SIOCGIWRANGE, &wreq))
		{
			info->wifiinfo = 1;

			if (0 != (stats.qual.updated & IW_QUAL_DBM))
				info->signal_level = stats.qual.level - 256;
			else
				info->signal_level = stats.qual.level;

			if (0 != range.max_qual.qual)
				info->link_quality = (stats.qual.qual * 100) / range.max_qual.qual;
			else
				info->link_quality = stats.qual.qual;

			if (0 != (stats.qual.updated & IW_QUAL_DBM))
				info->noise_level = stats.qual.noise - 256;
			else
				info->noise_level = stats.qual.noise;
		}
	}

	memset(buffer, 0, sizeof(buffer));
	wreq.u.essid.pointer = buffer;
	wreq.u.essid.length = IW_ESSID_MAX_SIZE;
	wreq.u.essid.flags = 0;

	if (0 == ioctl(sockfd, SIOCGIWESSID, &wreq))
	{
		strncpy(info->ssid, buffer, IW_ESSID_MAX_SIZE);
		info->ssid[IW_ESSID_MAX_SIZE] = '\0';
	}

	info->bitrate = -1;
	if (0 == ioctl(sockfd, SIOCGIWRATE, &wreq))
		info->bitrate = (int)wreq.u.bitrate.value / 1000000;

	memset(&ifr, 0, sizeof(ifr));
	memset(&ecmd, 0, sizeof(ecmd));
	ecmd.cmd = ETHTOOL_GSET;
	ifr.ifr_data = (char*)&ecmd;
	strncpy(ifr.ifr_name, interface, IFNAMSIZ - 1);

	if (0 == ioctl(sockfd, SIOCETHTOOL, &ifr))
		info->autoneg = ecmd.autoneg;

	memset(&ifr, 0, sizeof(ifr));
	strncpy(ifr.ifr_name, interface, IFNAMSIZ - 1);

	if (0 == ioctl(sockfd, SIOCGIFFLAGS, &ifr))
	{
		if (0 != (ifr.ifr_flags & IFF_UP))
			info->administrative_state = 1;
		else
			info->administrative_state = 2;

		if (0 != (ifr.ifr_flags & IFF_RUNNING))
			info->operational_state = 1;
		else
			info->operational_state = 2;
	}

	close(sockfd);
	return 0;
}
*/
import "C" //nolint:gocritic

import (
	"bufio"
	"encoding/json"
	"fmt"
	"os"
	"regexp"
	"strconv"
	"strings"
	"unsafe" //nolint:gocritic

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

const (
	errorCannotFindIf = "Cannot find information for this network interface in /proc/net/dev."
	netDevFilepath    = "/proc/net/dev"
)

func init() { //nolint:gochecknoinits // legacy implementation
	impl := &Plugin{}

	err := plugin.RegisterMetrics(
		impl, "NetIf",
		"net.if.collisions", "Returns number of out-of-window collisions.",
		"net.if.in", "Returns incoming traffic statistics on network interface.",
		"net.if.out", "Returns outgoing traffic statistics on network interface.",
		"net.if.total", "Returns sum of incoming and outgoing traffic statistics on network interface.",
		"net.if.discovery", "Returns list of network interfaces. Used for low-level discovery.",
		"net.if.get", "Returns list of network interfaces with parameters.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	impl.netDevFilepath = netDevFilepath
}

// Export implements plugin.Exporter interface.
//
//nolint:cyclop // export function delegates its requests, so high cyclo is expected.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (any, error) {
	switch key {
	case "net.if.discovery":
		if len(params) > 0 {
			return nil, errs.New(errorParametersNotAllowed)
		}

		devices, err := p.getDevDiscovery()
		if err != nil {
			return nil, err
		}

		b, err := json.Marshal(devices)
		if err != nil {
			return nil, errs.Wrap(err, "failed to marshal devices")
		}

		return string(b), nil

	case "net.if.get":
		if len(params) > 1 {
			return nil, errs.New(errorTooManyParams)
		}

		expression := ""
		if len(params) > 0 {
			expression = params[0]
		}

		devices, err := p.getIfGet(expression)
		if err != nil {
			return nil, err
		}

		b, err := json.Marshal(devices)
		if err != nil {
			return nil, errs.Wrap(err, "failed to marshal devices")
		}

		return string(b), nil

	case "net.if.collisions":
		err := validateParams(params, 1, 1)
		if err != nil {
			return nil, err
		}

		return p.getNetStats(params[0], "collisions", directionOut)

	case "net.if.in":
		return p.handleNetIfMetric(params, directionIn)

	case "net.if.out":
		return p.handleNetIfMetric(params, directionOut)

	case "net.if.total":
		return p.handleNetIfMetric(params, directionTotal)
	default:
		/* SHOULD_NEVER_HAPPEN */
		return nil, errs.New(errorUnsupportedMetric)
	}
}

func (p *Plugin) handleNetIfMetric(
	params []string, direction networkDirection,
) (any, error) {
	err := validateParams(params, 1, 2)
	if err != nil {
		return nil, err
	}

	mode := "bytes"
	if len(params) == 2 && params[1] != "" {
		mode = params[1]
	}

	return p.getNetStats(params[0], mode, direction)
}

func validateParams(params []string, minParams, maxParams int) error {
	if len(params) < minParams {
		return errs.New(errorEmptyIfName)
	}

	if len(params) > maxParams {
		return errs.New(errorTooManyParams)
	}

	if params[0] == "" {
		return errs.New(errorEmptyIfName)
	}

	return nil
}

// fillNetIfGetParams reads sysfs parameters.
func (*Plugin) fillNetIfGetParams(ifName, param string) string {
	path := fmt.Sprintf("/sys/class/net/%s/%s", ifName, param)

	data, err := os.ReadFile(path)
	if err != nil {
		return ""
	}

	return strings.TrimSpace(string(data))
}

// parseUintPointer attempts to parse a string into a uint64.
func parseUintPointer(s string) *uint64 {
	val, err := strconv.ParseUint(s, 10, 64)
	if err != nil {
		return nil
	}

	return &val
}

// getIfGet retrieves interface data.
//
//nolint:gocyclo,cyclop // Complexity is high due to CGo integration and data aggregation.
func (p *Plugin) getIfGet(regexExpr string) (netIfResult, error) {
	var compiledRegex *regexp.Regexp
	var result netIfResult

	f, err := os.Open(netDevFilepath)
	if err != nil {
		return result, fmt.Errorf("cannot open %s: %w", netDevFilepath, err)
	}
	defer f.Close()

	if regexExpr != "" {
		compiledRegex, err = regexp.Compile(regexExpr)
		if err != nil {
			return result, fmt.Errorf("invalid regex expression '%s': %w", regexExpr, err)
		}
	}

	result.Config = make([]ifConfigData, 0)
	result.Values = make([]IfValuesData, 0)

	for sLines := bufio.NewScanner(f); sLines.Scan(); {
		dev := strings.Split(sLines.Text(), ":")
		ifName := strings.TrimSpace(dev[0])

		if len(dev) <= 1 {
			continue
		}

		if compiledRegex != nil {
			if !compiledRegex.MatchString(ifName) {
				continue
			}
		}

		stats := strings.Fields(dev[1])
		if len(stats) < 16 {
			continue
		}

		cIface := C.CString(ifName)
		cInfo := (*C.ll_info_t)(C.malloc(C.sizeof_ll_info_t))

		llresult := C.get_ll_info(cIface, cInfo)
		if llresult != 0 {
			C.free(unsafe.Pointer(cIface))
			C.free(unsafe.Pointer(cInfo))

			continue
		}

		isAutonegEnabled := "off"
		if int(cInfo.autoneg) != 0 {
			isAutonegEnabled = "on"
		}

		speedPtr := parseUintPointer(p.fillNetIfGetParams(ifName, "speed"))
		typePtr := parseUintPointer(p.fillNetIfGetParams(ifName, "type"))
		carrierPtr := parseUintPointer(p.fillNetIfGetParams(ifName, "carrier"))

		var operstatusPtr *string
		if int(cInfo.operational_state) == 1 {
			val := "up"
			operstatusPtr = &val
		} else if int(cInfo.operational_state) == 2 {
			val := "down"
			operstatusPtr = &val
		}

		var administrativePtr *string
		if int(cInfo.administrative_state) == 1 {
			val := "up"
			administrativePtr = &val
		} else if int(cInfo.administrative_state) == 2 {
			val := "down"
			administrativePtr = &val
		}

		result.Config = append(result.Config, ifConfigData{
			Ifname:      ifName,
			Ifalias:     p.fillNetIfGetParams(ifName, "ifalias"),
			Ifmac:       p.fillNetIfGetParams(ifName, "address"),
			IfOperState: operstatusPtr,
			IfAdmState:  administrativePtr,
		})

		var levelPtr *int64
		var qualityPtr *int64
		var noiselevelPtr *int64

		if int(cInfo.wifiinfo) == 1 {
			levelVal := int64(cInfo.signal_level)
			levelPtr = &levelVal
			qualityVal := int64(cInfo.link_quality)
			qualityPtr = &qualityVal
			noiselevelVal := int64(cInfo.noise_level)
			noiselevelPtr = &noiselevelVal
		}

		valSSID := C.GoString((*C.char)(unsafe.Pointer(&cInfo.ssid[0])))
		var ssidPtr *string

		if valSSID != "" {
			ssidPtr = &valSSID
		}
		var bitratePtr *int64
		bitrateVal := int64(cInfo.bitrate)
		if bitrateVal > 0 {
			bitratePtr = &bitrateVal
		}

		result.Values = append(result.Values, IfValuesData{
			Ifname:  ifName,
			Ifalias: p.fillNetIfGetParams(ifName, "ifalias"),
			Ifmac:   p.fillNetIfGetParams(ifName, "address"),
			Iftype:  typePtr,

			In: IfStatistics{
				Ifbytes:      parseUintPointer(stats[0]),
				Ifpackets:    parseUintPointer(stats[1]),
				Iferrors:     parseUintPointer(stats[2]),
				Ifdropped:    parseUintPointer(stats[3]),
				Ifoverrruns:  parseUintPointer(stats[4]),
				Ifframe:      parseUintPointer(stats[5]),
				Ifcompressed: parseUintPointer(stats[6]),
				Ifmulticast:  parseUintPointer(stats[7]),
			},
			Out: IfStatistics{
				Ifbytes:      parseUintPointer(stats[8]),
				Ifpackets:    parseUintPointer(stats[9]),
				Iferrors:     parseUintPointer(stats[10]),
				Ifdropped:    parseUintPointer(stats[11]),
				Ifoverrruns:  parseUintPointer(stats[12]),
				Ifcollisions: parseUintPointer(stats[13]),
				Ifcarrier:    parseUintPointer(stats[14]),
				Ifcompressed: parseUintPointer(stats[15]),
			},
			Ifcarrier:     carrierPtr,
			Ifnegotiation: isAutonegEnabled,
			Ifduplex:      p.fillNetIfGetParams(ifName, "duplex"),
			Ifspeed:       speedPtr,
			Ifslevel:      levelPtr,
			Iflquality:    qualityPtr,
			Ifnoiselevel:  noiselevelPtr,
			Ifssid:        ssidPtr,
			Ifbitrate:     bitratePtr,
		})

		C.free(unsafe.Pointer(cIface))
		C.free(unsafe.Pointer(cInfo))
	}

	return result, nil
}
