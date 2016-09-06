# Presence

Presence shows you the availabilities of your set up team by analyzing their Google Calendar entries.

Features include:

- Set up teams
- Show availabilities of team
- Show details of single person in team
- Search and display availabilities for individual persons
- Show projects people working on and calculates man days

## Software Requirements

* [VirtualBox](https://www.virtualbox.org/wiki/Downloads)
* [Vagrant](http://www.vagrantup.com/downloads.html)
    * Vagrant plugin for hosts, could be installed in cli by ```vagrant plugin install vagrant-hostmanager```

## Installation

* install [Composer](http://getcomposer.org/): `curl -s https://getcomposer.org/installer | php`
* run Composer to install the dependencies: `php composer.phar install`
  in case you want to help out and contribute to the project or just want to run the provided tests do:
  `php composer.phar install --dev`
  instead.
* copy `config/settings.yaml.sample` file to `config/settings.yaml`
* create a project at Google: https://console.developers.google.com/project
* configure the "Consent Screen" to manage your organization/server
* under APIs make sure the Calendar API is ON

### OAuth2

User access to the website is managed through OAuth2.

* under Credentials generate a Client ID for "Web Application"
* add the `key` and `secret` to `settings.yaml`

### Service Account

To fetch the calendar information a service account is used.

* under Credentials generate a Client ID for "Service account"
* copy the email address to `settings.yaml`
* copy the .p12 to the server
* set the path to the .p12 key in `settings.yaml`
* set the `impersonated_user` to the email of the account that has access to your calendars
* copy the Client ID of the Service Account and add it on the Google Admin Console (https://admin.google.com/AdminHome?chromeless=1#OGX:ManageOauthClients).
* as scope for the calendar, use `https://www.googleapis.com/auth/calendar.readonly`

## Deployment

* login to `presence.liip.ch` server via `liip-ssh` tool
* go to `/var/www/presence.liip.ch/src`
* switch user to `vh000`, password you can find in pw.liip.ch SPD tool
* `git pull`

# Contribute

We strongly encourage you to contribute to the project. Please use the following workflow to keep it easy for everyone merging the Pull Requests back to the upstream.

1. Fork it
2. Create your feature branch (git checkout -b my-new-feature)
3. Commit your changes (git commit -am 'Add some feature')
4. Push to the branch (git push origin my-new-feature)
5. Create new Pull Request

# Copyright

This software is licensed under the GNU GENERAL PUBLIC LICENSE Version 3. Please see the `LICENSE` file for detailed information.

Copyright 2013 Bastian Widmer, Patrick Zahnd, Waldvogel, Hansmartin Geiser
