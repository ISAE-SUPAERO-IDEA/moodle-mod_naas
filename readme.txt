NaaS Module
===========

Overview
________

This module enables Moodle user to add Nugget activiies in their courses



Developing this module
______________________


This module uses a VueJS componenet to handle the search and the selection of a nugget.
The sources for this component are in the vue directory

To build the component go the vue directory and run `yarn build`

To develop the component go to the vue directory and run `yarn serve`
The development environnement can be setup to improve the developpement experience


- Apache configuration
----------------------
 to put in your moodle instance Virtualhost configurationµ. this will solve the CORS configuration error since you will be avle to access the component on the same webserver your moodle instance is working on.

ProxyPass /sockjs-node/  http://localhost:8083/sockjs-node/
ProxyPass /vue/  http://localhost:8083/vue/
ProxyPassReverse /vue/ http://localhost:8083/vue/
ProxyPreserveHost On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/?(.*) "ws://moodle.local.isae.fr/$1" [P,L]

- development configuration file
--------------------------------

: vue/dev_config.js
-- proxy_url: the url where the proxy.php of the naas module can be reached
-- nugget_id: initial nugget_id to simulate the fact that a nugget has already been selected
-- labels: translation to be displayed on the interface