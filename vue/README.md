# VueJS UI components

This Moodle plugin uses a VueJS component to handle the search and selection of a nugget.

## Requirements 
- Node v14
- Yarn 1.22

## Project setup

```
yarn install
```

### Compiles and hot-reloads for development

```
yarn serve
```

### Compiles and minifies for production

```
yarn build
```

### Lints and fixes files

```
yarn lint
```

### Customize configuration

See [Configuration Reference](https://cli.vuejs.org/config/).


## Configuration for development

This configuration should be added to the Virtualhost configuration of your Moodle instance to solve CORS configuration errors. 
By accessing the component on the same webserver as your Moodle instance, the following Apache configuration should be added:

```bash
ProxyPass /sockjs-node/  http://localhost:8083/sockjs-node/
ProxyPass /vue/  http://localhost:8083/vue/
ProxyPassReverse /vue/ http://localhost:8083/vue/
ProxyPreserveHost On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/?(.*) "ws://moodle.local.isae.fr/$1" [P,L]
```

### Development Configuration File

The following file contains the development configuration for the Vue component: `vue/dev_config.js`
- `proxy_url`: the url where the `proxy.php` of the NaaS module can be reached
- `nugget_id`: initial nugget_id to simulate the fact that a nugget has already been selected
- `labels`: translation to be displayed on the interface

=> The assets will be deployed in `assets/vue`
- To develop the component in a sandbox, run
```bash
yarn serve -- --port 8083`.
```