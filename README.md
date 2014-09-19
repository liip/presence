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
* copy `config/settings.yaml.sample` file to `config/settings.yaml`
* create a project at Google: https://console.developers.google.com/project
* under APIs make sure the Calendar API is ON
* generate the OAUTH2 credentials there and add the `key` and `secret` to `settings.yaml`

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
