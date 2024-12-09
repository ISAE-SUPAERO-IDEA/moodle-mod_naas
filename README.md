
# Moodle Nugget plugin

This plugin enables the use of “nuggets” provided by the NaaS (Nugget as a Service) platform in the Moodle ecosystem.

## Description

### Digital Nugget
"_Digital nuggets_" are short online learning units designed to provide specific and targeted training
on a particular subject.
Digital nuggets are typically offered in the form of short videos, simulations, interactive modules, 
quizzes, or infographics. 
Their name refers to the shape of a small "nugget," meaning a bite-sized piece of 
easily digestible knowledge...

Nuggets are reusable micro-content developed by authors who are experts in their fields. 
Defined according to precise specifications, they are decontextualised, 
*short - less than 30 minutes in learner time - multimedia, documented and 
certified by the reputation of the major establishments from which the expert authors come.

### Nuggets as a Service (NaaS)
NaaS is a complete ecosystem designed to facilitate the reuse of digital content in education. 
Thanks to a specific production engineering, you will be able to develop, manage, 
integrate your nuggets in educational platforms and control their pedagogical use.

![The big picture](https://www.naas-edu.eu/en/assets/images/naas-big-picture-1175x1228.webp)


More information on the [NaaS website](https://www.naas-edu.eu/) :

[![Logo](https://t2594656.p.clickup-attachments.com/t2594656/4202512a-08f8-47d9-9f4b-3f175153ed7d/image.png)](https://www.naas-edu.eu/)


### Functionalities provided by this plugin

This plugin enables Moodle users to add Nugget activities in their courses by connecting to a NaaS Server 
to fetch the content.

The main features of the Nuggets plugin are as follows: 
- Keyword search
- Nugget filtering
- Metadata display 
- One-click integration of a Nugget into the course space.

## Installation

### Requirements
- Moodle 4.0 or later (the plugin has been tested successfully up to Moodle 4.5.1)
- PHP 7.3 (the plugin has been tested successfully up to PHP 8.3)

### Plugin settings

The plugin settings allow an administrator to configure the options and specific access keys for accessing Nuggets via the Moodle platform.

By default, the plugin is configured with open education keys, so that Nuggets with this type of licence can be integrated. 
To access all the Nuggets available to your school, you need to retrieve the NaaS API keys for your school. You can
contact `idea.lab@isae-supaero.fr` to get your private keys.


🛠️ **Access** the Moodle administration page : `Administration > Plugins > Nugget`.

![setup-naas-plugin](https://t2594656.p.clickup-attachments.com/t2594656/457711f5-b548-4ce7-b483-863b2aaef71c/image.png)

📝 Those parameters allow you to connect to your distribution space on the NaaS platform :
- `NaaS API access point `
- `NaaS API user name `
- `NaaS API institute ID `
- `NaaS API password `

Other optional parameters are :
- NaaS CSS : a CSS file to adapt the display of Nuggets from NaaS to your local style.
- Search filter: a filter limiting the Nuggets that can be integrated.

The NaaS privacy section provides a way to select the personal information that is sent from the Moodle LMS Platform to the NaaS for learning analytics purposes.
- Learner email: if this checkbox is selected, the learner's email address associated with the Moodle account is communicated to the NaaS platform when he accesses a nugget. 
- Learner name: if this checkbox is selected, the learner's name associated with the Moodle account is communicated to the NaaS platform when he accesses a nugget.

Anonymous mode: If either name or email is not selected, personal data transfer is globally not allowed, and no personal information is sent to the NaaS infrastructure when the 
learner access and learns with a nugget.

Then, in any case, when the learner access and interacts with the Digital Nugget, his learning usages (e.g., video views, interaction and results in quizzes, etc.) 
are collected through learning traces. Nevertheless, depending on the transfer of the learner's email or not, the traces are either anonymous or associated with the learner.

See [PLUGIN NUGGET: Privacy Notice (en)](https://doc.clickup.com/2594656/p/h/2f5v0-8202/267a2f1cc205119).


## Usage 

### Integrating a nugget 🧩 into a course

![Add-a-nugget](https://www.naas-edu.eu/en/assets/images/snap-nugget-moodle-1080x1020.webp)

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


## Website
[https://www.naas-edu.eu/](https://www.naas-edu.eu/) (French & English).

## Support and Suggestion
If you encounter any issues or have suggestions for improvements, please feel free to [open issues on GitHub](https://github.com/ISAE-SUPAERO-IDEA/moodle-mod_naas/issues).
You can also contact us by email at the following address email: idea.lab@isae-supaero.fr

## ChangeLog
The change log is available [here](CHANGES.md).


## Source code
The source code is available at [https://github.com/ISAE-SUPAERO-IDEA/moodle-mod_naas](https://github.com/ISAE-SUPAERO-IDEA/moodle-mod_naas).

## Development
This Moodle plugin uses a VueJS component to handle the search and selection of a nugget. 
See [vue/README.md](vue/README.md)


## Copyright
Copyright ISAE-SUPAERO for this plugin is licensed under the GPLv3 license. [GNU AFFERO GENERAL PUBLIC LICENSE v3](LICENSE.md).

