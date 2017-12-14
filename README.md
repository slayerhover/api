# api

nginx配置
# lumen框架部分接口配置
server {
        listen       80;
        server_name  api01.com;
        root /www/user/public/;
        charset utf-8;
        index  index.php index.html;
        include php71.conf;
	
        location / {
                try_files $uri $uri/ /index.php?$query_string;
        }      

        access_log /home/logs/home_api01.log access;
}

# yaf框架部分接口配置
server {
        listen       80;
        server_name  api02.com;
        root /www/home/public/;
        charset utf-8;
        index  index.php index.html;
        include php71.conf;

        location / {
		#try_files $uri $uri/ /index.php?$query_string;
		if (!-e $request_filename) {
                        rewrite ^(.*)$ /index.php?$1 last;
                        break;
                }
        }      

        access_log /home/logs/home_api02.log access;
}

#总配置,设置反向代理配置两个不同的框架协同工作
limit_req_zone $binary_remote_addr zone=qps1:1m  rate=3056r/s;
limit_req_zone $binary_remote_addr zone=qps2:1m  rate=4565r/s;
limit_req_zone $binary_remote_addr zone=qps3:1m  rate=5812r/s;

#upstream backend {
#    drizzle_server 127.0.0.1:3306 dbname=scsj user=root password=asdfasdf protocol=mysql;
#    drizzle_keepalive max=1000 overflow=ignore mode=single;
#}

server {
	listen       80;
	server_name  api.com;
	root /www/;
	charset utf-8;
	index  index.php index.html;
	include php71.conf;

	header_filter_by_lua_block{ 
		ngx.header["X-Powered-By"] ="[JIT]ASP.NET";
		ngx.header["Access-Control-Allow-Origin"] ="*";
		ngx.header["Server"] ="Bfe/2.4";
	}

	rewrite "^/user/(.*)"    /u/$1 last;
	location  ~ /u/(.*)$ 
	{
		include killbot.conf;
		proxy_set_header Host "api01.com";
		proxy_set_header X-Real-Ip $remote_addr;
		proxy_set_header REMOTEHOST $remote_addr;
		proxy_set_header MAHESHAN $remote_addr;
		proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
		
		proxy_pass  http://api01.com;
	}

	rewrite "^/home/(.*)"    /h/$1 last;
	location /h/
	{ 
		include killbot.conf;
		proxy_set_header Host "api02.scsj.net.cn";
		proxy_set_header X-Real-Ip $remote_addr;
		proxy_set_header REMOTE-ADDR $remote_addr;
		proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
		
		proxy_pass  http://api02.com;
	}

	limit_req  zone=qps1 burst=5000;
	access_log  /home/logs/host.log  access;
}
