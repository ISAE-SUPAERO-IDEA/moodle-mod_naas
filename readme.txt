NaaS Module
===========

Overview
---------------

This module enables Moodle users to add Nugget activities in their courses by connecting to a NaaS Server to fetch the content.


Developer notes
---------------

This module uses a VueJS component to handle the search and selection of a nugget. The sources for this component are located in the vue directory. To develop this component, you will need Node.js (https://nodejs.org/en/).

In the vue directory:
- Install the dependencies with `yarn install`
- To build the component, run `yarn build`


Apache configuration
---------------

This configuration should be added to the Virtualhost configuration of your Moodle instance to solve CORS configuration errors. By accessing the component on the same webserver as your Moodle instance, the following Apache configuration should be added:

ProxyPass /sockjs-node/  http://localhost:8083/sockjs-node/
ProxyPass /vue/  http://localhost:8083/vue/
ProxyPassReverse /vue/ http://localhost:8083/vue/
ProxyPreserveHost On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/?(.*) "ws://moodle.local.isae.fr/$1" [P,L]


Development Configuration File
---------------

The following file contains the development configuration for the Vue component: `vue/dev_config.js`
  - `proxy_url`: the url where the `proxy.php` of the NaaS module can be reached
  - `nugget_id`: initial nugget_id to simulate the fact that a nugget has already been selected
  - `labels`: translation to be displayed on the interface

=> The assets will be deployed in `assets/vue`
- To develop the component in a sandbox, run `yarn serve -- --port 8083`.


Contributing to this module
---------------

This module is currently hosted on a private repository. To gain access please contact `idea-lab@isae-supaero.fr`.

Once you have access. You should:
- Clone the repository
- Branch from the `develop` branch 
- Make your changes (while regularly merging the `develop branch`)
- Create a merge request

Note: For the time being, with regards to the Vue search widget, you should commit both the sources and the artifacts resulting from the build command.
