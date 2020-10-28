-- example HTTP POST script which demonstrates setting the
-- HTTP method, body, and adding a header

wrk.method = "POST"
wrk.body   = "{\"taskid\": 1,\"timestamp\": 1603635439,\"sign\": \"15c1b0e06e337a06d73c8b4cf9911cbb\",\"ctn\": {\"hello\":\"world\"}}"
wrk.headers["Content-Type"] = "application/json"
