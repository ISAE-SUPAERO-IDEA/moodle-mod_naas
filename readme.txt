NaaS Module
===========

Overview
________

This module enables Moodle user to add Nugget activiies in their courses. It connects to a NaasServer to fetch the content.


Developer notes
---------------

This module uses a VueJS component to handle the search and the selection of a nugget. The sources for this component are in the vue directory. To develop this component, You will need nodejs https://nodejs.org/en/.

In the vue directory:
- Install the dependencies: yarn install
- To build the component run `yarn build`

=> Apache configuration
 to put in your moodle instance Virtualhost configurationµ. this will solve the CORS configuration error since you will be avle to access the component on the same webserver your moodle instance is working on.

ProxyPass /sockjs-node/  http://localhost:8083/sockjs-node/
ProxyPass /vue/  http://localhost:8083/vue/
ProxyPassReverse /vue/ http://localhost:8083/vue/
ProxyPreserveHost On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/?(.*) "ws://moodle.local.isae.fr/$1" [P,L]

=> development configuration file
: vue/dev_config.js
--- proxy_url: the url where the proxy.php of the naas module can be reached
--- nugget_id: initial nugget_id to simulate the fact that a nugget has already been selected
--- labels: translation to be displayed on the interface

=> The assets will be deployed in assets/vue
- To develop the component in a sandbox and run `yarn serve -- --port 8083`.


Contributing to this module
______________________

This module is currently hosted on a private repository. To gain access please contact idea-lab@isae-supaero.fr

Once you have access. You should:
- Clone the repository
- Branch from the develop branch 
- Make your changes (while regularly merging the develop branch)
- Create a merge request

For the time being, in regard with vue search widget,  you should commit both the sources and the artifacts resulting from the build wommand.
