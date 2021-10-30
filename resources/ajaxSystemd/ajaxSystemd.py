# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

import shared
import logging
import string
import sys
import traceback
import os
import time
import datetime
import re
from typing import Any
import signal
from optparse import OptionParser
from os.path import join
import json
import argparse
from pysiaalarm import CommunicationsProtocol, SIAAccount, SIAClient, SIAEvent

try:
    from jeedom.jeedom import *
except ImportError:
    print("Error: importing module jeedom.jeedom")
    sys.exit(1)


def func(event: SIAEvent):
    info = get_event_data_from_sia_event(event)
    if(info['code'] == 'RP'):
        return
    logging.debug('Got an event : ' + str(json.dumps(info)))
    shared.JEEDOM_COM.add_changes('devices::'+str(info['message']), info)


def listen():
    account = [SIAAccount(_account, _key)]
    sia_client = SIAClient('', _siaport, account, function=func,
                           protocol=CommunicationsProtocol('TCP'))
    sia_client.start()


def get_event_data_from_sia_event(event: SIAEvent) -> dict[str, Any]:
    return {
        "message_type": event.message_type.value,
        "receiver": event.receiver,
        "line": event.line,
        "account": event.account,
        "sequence": event.sequence,
        "content": event.content,
        "ti": event.ti,
        "id": event.id,
        "ri": event.ri,
        "code": event.code,
        "message": event.message,
        "x_data": event.x_data,
        "timestamp": event.timestamp.isoformat()
        if event.timestamp
        else utcnow().isoformat(),
        "event_qualifier": event.event_qualifier,
        "event_type": event.event_type,
        "partition": event.partition,
        "extended_data": [
            {
                "identifier": xd.identifier,
                "name": xd.name,
                "description": xd.description,
                "length": xd.length,
                "characters": xd.characters,
                "value": xd.value,
            }
            for xd in event.extended_data
        ]
        if event.extended_data is not None
        else None,
        "sia_code": {
            "code": event.sia_code.code,
            "type": event.sia_code.type,
            "description": event.sia_code.description,
            "concerns": event.sia_code.concerns,
        }
        if event.sia_code is not None
        else None,
    }
# ----------------------------------------------------------------------------


def handler(signum=None, frame=None):
    logging.debug("Signal %i caught, exiting..." % int(signum))
    shutdown()


def shutdown():
    logging.debug("Shutdown")
    logging.debug("Removing PID file " + str(_pidfile))
    try:
        os.remove(_pidfile)
    except:
        pass
    logging.debug("Exit 0")
    sys.stdout.flush()
    os._exit(0)

# ----------------------------------------------------------------------------


_log_level = "error"
_device = 'auto'
_pidfile = '/tmp/demond.pid'
_apikey = ''
_callback = ''
_cycle = 0.3
_siaport = 51000

parser = argparse.ArgumentParser(
    description='AjaxSystem Daemon for Jeedom plugin')
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Callback", type=str)
parser.add_argument("--apikey", help="Apikey", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=str)
parser.add_argument("--pid", help="Pid file", type=str)
parser.add_argument("--account", help="SIA account", type=str)
parser.add_argument("--key", help="SIA key", type=str)
parser.add_argument("--siaport", help="Port for Zigbee server", type=str)
args = parser.parse_args()

if args.loglevel:
    _log_level = args.loglevel
if args.callback:
    _callback = args.callback
if args.apikey:
    _apikey = args.apikey
if args.pid:
    _pidfile = args.pid
if args.cycle:
    _cycle = float(args.cycle)
if args.siaport:
    _siaport = int(args.siaport)
if args.account:
    _account = args.account
if args.key:
    _key = args.key


jeedom_utils.set_log_level(_log_level)

logging.info('Start demond')
logging.info('Log level : '+str(_log_level))
logging.info('PID file : '+str(_pidfile))
logging.info('Apikey : '+str(_apikey))
logging.info('Device : '+str(_device))
logging.info('Cycle : '+str(_cycle))
logging.info('Sia port : '+str(_siaport))
logging.info('Sia Account : '+str(_account))
logging.info('Sia key : '+str(_key))

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)

try:
    jeedom_utils.write_pid(str(_pidfile))
    shared.JEEDOM_COM = jeedom_com(apikey=_apikey, url=_callback, cycle=_cycle)
    if not shared.JEEDOM_COM.test():
        logging.error(
            'Network communication issues. Please fixe your Jeedom network configuration.')
        shutdown()
    listen()
except Exception as e:
    logging.error('Fatal error : '+str(e))
    logging.info(traceback.format_exc())
    shutdown()
