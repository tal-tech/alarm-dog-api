#!/bin/sh

if [ "$BASE_URI" != "" ]; then
    reportUrl="$BASE_URI/alarm/report"
else
    reportUrl="http://127.0.0.1:9501/alarm/report"
fi

echo "benchmark target: $reportUrl"

echo "benchmark command: wrk -c200 -t200 -s alarmReport.lua -d120s --latency $reportUrl"

# 可以复制出来手动执行
wrk -c200 -t200 -s alarmReport.lua -d120s --latency $reportUrl
