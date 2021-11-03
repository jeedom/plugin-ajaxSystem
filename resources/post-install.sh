#!/bin/bash
ver=$(python3 -V 2>&1 | sed 's/.* \([0-9]\).\([0-9]\).*/\1\2/')
if [ "$ver" -lt "38" ]; then
   sudo pip3 install pysiaalarm==3.0.0b9
fi