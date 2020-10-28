-- example HTTP POST script which demonstrates setting the
-- HTTP method, body, and adding a header

wrk.method = "POST"
wrk.body   = "{\"taskid\": 1,\"timestamp\": 1603635439,\"sign\": \"898319b191d49a17ae86d8cb86f2ae6b\",\"ctn\": {\"hello\":\"world\"},\"receiver\": {\"channels\": {\"dingworker\": [111111111]}}}"
wrk.headers["Content-Type"] = "application/json"
