#!/bin/bash
if [ $(sudo pip3 list | grep -E "pysiaalarm" | wc -l) -gt 0 ]; then
   sudo pip3 install pysiaalarm==3.0.0b9
fi