
### Usage of Websockets server
---------------


### Configuration and description of services

config/autoload/server.json contains all information about services. There are few types of services suported:

- **Connector service**: main purpose is to serve all the client's requests via websockets (RPC, subscribe/unsubscribe, receive events);
- **Gate service**: WS service that is used for initialize process of WS clients and used as a gate. 
It aim to serve clients and give them appropriate Connector service address and port during init process;
- **Backend service**: particular type of services intended to serve all RPC requests. All business logic will be executed inside this kind of services;
- **Job service**: such services are used as workers for instant jobs, which are working using RabbitMQ queues;

# Example of configuration file for server:

	{
	  "ws-server": {
		"services": {
		  "gate-server-1": {
			"php_path": "php",
			"name": "gate-server-1",
			"class": "Icekson\\WsAppServer\\Service\\GateService",
			"host": "vps.cricket.program-ace.net/ws-gate/",
			"port": 80,
			"bind_port": 5000
		  },
		  "connector-server-1": {
			"php_path": "php",
			"name": "connector-server-1",
			"class": "Icekson\\WsAppServer\\Service\\ConnectorService",
			"host": "vps.cricket.program-ace.net/ws1/",
			"identity_finder_class" : "Application\\Utils\\UserIdentityFinder",
			"port": 80,
			"bind_port": 5001
		  },
		  "connector-server-2": {
			"php_path": "php",
			"name": "connector-server-2",
			"class": "Icekson\\WsAppServer\\Service\\ConnectorService",
			"host": "vps.cricket.program-ace.net/ws2/",
			"identity_finder_class" : "Application\\Utils\\UserIdentityFinder",
			"port": 80,
			"bind_port": 5002
		  },
		  "backend-server": {
			"php_path": "php",
			"name": "backend-server-1",
			"class": "Application\\Service\\BackendService",
			"processes_limit": 20,
			"count": 1,
			"debug": true
		  },
		  "jobs-server-1": {
			"php_path": "php",
			"name": "jobs-server-1",
			"class": "Icekson\\WsAppServer\\Service\\JobsService",
			"routing_key": "match"
		  },
		}
	  }
	}

Script commands
--------------------

# run server
path to config directory or file, not required field

    php scripts/runner.php app-server:start --config-path="./config/autoload/"
    
# check server state
check server and if not started start it

    php scripts/runner.php app-server:check
    
# stop server

    php scripts/runner.php app-server:stop
    
Test Tools
-----------------
     
#Run monitor for application logs 
It works via RabbitMQ, it shows all the logs for scripts in system which uses Icekson\Utils\Logger

     php scripts/runner.php tool:logs-monitor


