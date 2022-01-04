#!/bin/bash

PROGRESS_FILE=/tmp/dependancy_ajaxSystem_in_progress
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "Launch install of ajax system dependancy"
BASEDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
sudo apt-get clean
echo 30 > ${PROGRESS_FILE}
sudo apt-get update
sudo apt-get install -y python3 python3-pip python3-serial python3-pyudev python3-requests python3-setuptools python3-dev
echo 60 > ${PROGRESS_FILE}

sudo pip3 install pysiaalarm

if [ $(sudo pip3 list | grep -E "pysiaalarm" | wc -l) -gt 0 ]; then
   sudo pip3 install pysiaalarm==3.0.0b9
fi

rm ${PROGRESS_FILE}
echo "Everything is successfully installed!"
