
# Plugin Nugget (NaaS module)

This module enables Moodle users to add Nugget activities in their courses by connecting to a NaaS Server to fetch the content.
The main features of the Nuggets plugin are as follows: 
- Keyword search
- Nugget filtering : 
- Metadata display : 
- One-click integration: once the Nugget has been found, simply select it and validate to integrate it into the course space.

#### What are Nuggets?:

Nuggets are veritable digital educational nuggets: reusable micro-content developed by authors who are experts in their fields. Defined according to precise specifications, they are decontextualised, short - less than 30 minutes in learner time - multimedia, documented and certified by the reputation of the major establishments from which the expert authors come.

[More information on the NaaS website](https://www.naas-edu.eu/)





![Logo](https://t2594656.p.clickup-attachments.com/t2594656/4202512a-08f8-47d9-9f4b-3f175153ed7d/image.png)





# Deployment: setting up the development environment


This module uses a VueJS component to handle the search and selection of a nugget. The sources for this component are located in the vue directory. To develop this component, you will need Node.js (https://nodejs.org/en/).

Create a symbolic link:
`ln -s /home/idea/moodle_plugins/mod/naas /var/www/html/moodle/mod/naas`

In the vue directory:
- Install the dependencies with 
```bash 
yarn install 
```
- To build the component, run 
```bash 
yarn build
```

This configuration should be added to the Virtualhost configuration of your Moodle instance to solve CORS configuration errors. By accessing the component on the same webserver as your Moodle instance, the following Apache configuration should be added:

```bash
ProxyPass /sockjs-node/  http://localhost:8083/sockjs-node/
ProxyPass /vue/  http://localhost:8083/vue/
ProxyPassReverse /vue/ http://localhost:8083/vue/
ProxyPreserveHost On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/?(.*) "ws://moodle.local.isae.fr/$1" [P,L]
```
Development Configuration File
---------------

The following file contains the development configuration for the Vue component: `vue/dev_config.js`
  - `proxy_url`: the url where the `proxy.php` of the NaaS module can be reached
  - `nugget_id`: initial nugget_id to simulate the fact that a nugget has already been selected
  - `labels`: translation to be displayed on the interface

=> The assets will be deployed in `assets/vue`
- To develop the component in a sandbox, run 
```bash
yarn serve -- --port 8083`.
```

# Usage

This module is currently hosted on a private repository. To gain access please contact `idea-lab@isae-supaero.fr`.

Once you have access. You should:
- Clone the repository
- Branch from the `develop` branch 
- Make your changes (while regularly merging the `develop branch`)
- Create a merge request

### Plugin settings

The plugin settings allow an administrator to configure the options and specific keys for accessing Nuggets via the Moodle platform.

By default, the plugin is configured with open education keys, so that Nuggets with this type of licence can be integrated. To access all the Nuggets available to your school, you need to retrieve the NaaS API keys for your school. 

🛠️ **Access** the Moodle administration page : `Administration > Plugins > Nugget`.

📝 You will need to obtain the following information from the NaaS publisher :
- `NaaS API access point `
- `NaaS API user name `
- `NaaS API institute ID `
- `NaaS API password `

Other optional parameters are :
NaaS CSS : You can provide a CSS file to adapt the display of Nuggets from NaaS to your local style. 
Search filter: a filter limiting the Nuggets that can be integrated.

A NaaS privacy section allows the administrator to regulate certain aspects of learners' personal information supplied by the Moodle platform to NaaS.


> ℹ️ **Information :** The Moodle Nugget Plugin requires the collection and storage of personal data such as a user's name and email address in order to improve the user experience through anonymous statistical analysis of the data. The data collected is stored on the NaaS server and is never shared with third parties.

### Integrating a nugget 🧩 into a course

    1. Switch course space to edit mode
    2. Add an activity or resource
    3. Choose the Nugget resource
    4. Start typing a keyword in the search field
    5. Filter nuggets by clicking on one or more criteria
    6. Access a nugget's metadata by clicking on the ‘ABOUT’ button
    7. Preview the nugget using the ‘Preview’ button
    8. Select the Nugget to be integrated
    9. Enter the name under which the Nugget will be displayed in the course area.
    10. Read and Accept the NaaS General Terms and Conditions of Use by clicking on the tick
    11. Register










## Support

For support, email idea.lab@isae-supaero.fr




## Licence
Sous licence [GPLv3](https://www.gnu.org/licenses/gpl-3.0.fr.html).