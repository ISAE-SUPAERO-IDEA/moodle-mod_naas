
# Moodle Nugget plugin

This plugin enables the use of “nuggets” provided by the NaaS (Nugget as a Service) platform in the Moodle ecosystem.

Example of a Nugget integrated into a Moodle course:
![nugget](https://t2594656.p.clickup-attachments.com/t2594656/98f394c0-8c09-40d3-b6d0-e249af8906d0/image.png)

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

The username and institute ID default to public Open Education (OER) credentials so openly licensed Nuggets can be searched. The API password is not shipped in the plugin: enter it on the settings page, or set the `NAAS_API_PASSWORD` environment variable. To access all the Nuggets available to your school, retrieve the NaaS API keys for your institute from `idea.lab@isae-supaero.fr`.


🛠️ **Access** the Moodle administration page : `Administration > Plugins > Activity modules > Nugget`.

![setup-naas-plugin](https://t2594656.p.clickup-attachments.com/t2594656/457711f5-b548-4ce7-b483-863b2aaef71c/image.png)

📝 Connection parameters:
- `NaaS API URL`
- `API username`
- `API password`
- `Institute ID`

Save the form, then use **Test connection** (it always uses the saved values, not unsaved edits).

Other optional parameters are:
- Extra CSS for Nugget player: CSS sent to the Nugget player, not the Moodle theme.
- Catalogue search filter: an NQL query that limits which Nuggets teachers can pick (example: `type:video`).

The Privacy section controls personal information sent from Moodle to NaaS for learning analytics:
- Send learner email to NaaS: if selected, the Moodle account email is sent when the learner opens a nugget.
- Send learner name to NaaS: if selected, the Moodle account name is sent when the learner opens a nugget.

Anonymous mode: if either name or email is not selected, personal data transfer is not allowed, and no personal information is sent to NaaS when the
learner accesses a nugget.

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

