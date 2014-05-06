Presence
========

Presence shows you the availabilities of your set up team by analyzing their Google Calendar entries.

Features include:

- Set up teams
- Show availabilities of team
- Show details of single person in team
- Search and display availabilities for individual persons
- Show projects people working on and calculates man days

Installation
------------

* install [Composer](http://getcomposer.org/): `curl -s https://getcomposer.org/installer | php`
* run Composer to install the dependencies: `php composer.phar install`
  in case you want to help out and contribute to the project or just want to run the provided tests do:
  `php composer.phar install --dev`
  instead.
* copy `config/services.yaml.sample` file to `config/services.yaml`
* go to https://code.google.com/apis/console
* create a new SERVICE ACCOUNT
* download the privatekey.p12 and place it somewhere on your machine
* set `clientId`, `serviceAccountName` (Google dev e-mail address) and `keyFile` in `config/services.yaml` file
* copy `config/people.yaml.sample` file to `config/people.yaml` and configure the teams
* install the `pre-commit.sh` script as a pre-commit hook in your local repository `ln -s ../../pre-commit.sh .git/hooks/pre-commit`

Run tests
---------

* make sure you run `php composer.phar install --dev` (use `update --dev` to update dependencies) to fetch necessary dependencies.
* open a shell and change into the project root
* run `phpunit`
  (see [PHPUnit documentation](http://www.phpunit.de/manual/current/en/index.html) to get to know PHPUnit.)

Contribute
----------
We strongly encourage you to contribute to the project. Please use the following workflow to keep it easy for everyone merging the Pull Requests back to the upstream.

1. Fork it
2. Create your feature branch (git checkout -b my-new-feature)
3. Commit your changes (git commit -am 'Add some feature')
4. Push to the branch (git push origin my-new-feature)
5. Create new Pull Request

Google Sources
--------------

  * PHP API Client: http://code.google.com/p/google-api-php-client

  * Google Calendar OAuth 2.0: https://developers.google.com/google-apps/calendar/auth

  * Google Calendar Events List Documentation: https://developers.google.com/google-apps/calendar/v3/reference/events/list

  * Google Calendar API Explorer: https://developers.google.com/apis-explorer/#s/calendar/v3/calendar.events.list?calendarId=bombastic%2540example.com&_h=1&

  * Google API Console: https://code.google.com/apis/console

  * Google Calendar API Reference: https://developers.google.com/google-apps/calendar/v2/reference

  * Google Calendar API Event Fields https://developers.google.com/google-apps/calendar/v3/reference/events

Copyright
---------

This software is licensed under the GNU GENERAL PUBLIC LICENSE Version 3. Please see the `LICENSE` file for detailed information.

Copyright 2013 Bastian Widmer, Patrick Zahnd, Waldvogel, Hansmartion Geiser
