#!/bin/bash
set -eu
cd /home2/joabef36/jurisflow-ai
source scripts/lib-hostgator.sh

jurisflow_stop || true
if [ -f jurisflow-supervisor.pid ]; then
  kill "$(cat jurisflow-supervisor.pid)" 2>/dev/null || true
fi
pkill -f jurisflow-supervisor-hostgator.sh 2>/dev/null || true
sleep 2

jurisflow_start
sleep 8
echo -n "HEALTH1="
curl -s -m 5 "http://127.0.0.1:$PORT/health" || echo FAIL
echo

nohup setsid bash scripts/jurisflow-supervisor-hostgator.sh </dev/null >> supervisor.log 2>&1 &
sleep 2
echo -n "HEALTH2="
curl -s -m 5 "http://127.0.0.1:$PORT/health" || echo FAIL
echo
echo "PID_FILE=$(cat jurisflow.pid 2>/dev/null || echo none)"
