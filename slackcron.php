#!/usr/bin/php -d apc.enable_cli=1
<?php

namespace Presence;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\Provider\DoctrineServiceProvider;
use TailoredTunes\SlackNotifier;
use PDO;

// we need APC for the caching
if (! (extension_loaded('apc') && ini_get('apc.enabled'))) {
    throw new \Exception("Cannot find the PHP APC module. Please install or enable it for this app to work");
}

require_once __DIR__ . '/vendor/autoload.php';

// parse and store the yaml configuration
$yaml             = new \Symfony\Component\Yaml\Parser();
$config           = new Config();
$config->settings = $yaml->parse(file_get_contents(__DIR__ . '/config/settings.yaml'));


// register the db
$db = new PDO("sqlite:{$config->settings['dbPath']}");
$sqlite = new Sqlite($db);
$slackteams = $sqlite->getSlackTeams();

foreach ($slackteams as $slackteam) {
    $today = new \DateTime();
    $startDate = \DateTime::createFromFormat('Y-m-d H:i:s', $today->format('Y-m-d') . ' 00:00:00');
    $endDate = \DateTime::createFromFormat('Y-m-d H:i:s', $today->format('Y-m-d') . ' 23:59:59');
    $calendar = new GoogleCalendar($config->settings['google'], $startDate, $endDate);
    $zebra = new Zebra($config->settings['zebra']);
    $holidays     = $zebra->getHolidays();
    $zebra->syncLocationWithDatabase($sqlite);
    $refresh = true;

    $team = new Team(
        $slackteam['slug'],
        $calendar,
        $holidays,
        $sqlite,
        $refresh
    );

    $base_url = '';
    if (isset($config->settings['slack']['base_url'])) {
        $base_url = $config->settings['slack']['base_url'];
    }
    $teamName =  $base_url ? "<{$base_url}/{$slackteam['slug']}|{$team->getName()}>" : $team->getName();
    $message = "Team status for {$teamName}\n";

    foreach ($team->getMembers() as $member) {

        $morning = $member->getTimeSlotByDate('morning', $startDate);
        $afternoon = $member->getTimeSlotByDate('afternoon', $startDate);
        $wholeDay = $morning['class'] === $afternoon['class'];
        $sameTitle = !empty($morning['title']) && $morning['title'] === $afternoon['title'];
        $oneTitle = !$sameTitle && (!empty($morning['title']) || !empty($afternoon['title']));
        $noTitle = empty($morning['title']) && empty($afternoon['title']);

        $status = '';

        $fclass = function ($class) {
            switch ($class) {
                case 'off':
                return '`' . $class . '`';
                break;
                case 'busy':
                return '_' . $class . '_';
                break;
                default:
                return '*' . $class . '*';
                break;
            }
        };

        $ftitle = function ($title) {
            return ' "' . $title . '"';
        };

        if ($wholeDay && $sameTitle) {
            $status = sprintf('%s %s', $fclass($morning['class']), $ftitle($morning['title']));
        } elseif ($wholeDay) {
            if ($oneTitle) {
                $title = '';
                if ($morning['title']) {
                    $title = sprintf('morning: %s', $ftitle($morning['title']));
                } else {
                    $title = sprintf('afternoon: %s', $ftitle($morning['title']));
                }
                $status = sprintf('%s - %s', $fclass($morning['class']), $title);
            } else {
                if ($noTitle) {
                    $status = $fclass($morning['class']);
                } else {
                    $status = sprintf(
                        '%s %s',
                        $fclass($morning['class']),
                        sprintf('morning: %s / afternoon: %s', $ftitle($morning['title']), $ftitle($afternoon['title']))
                    );
                }
            }
        } else {
            $status = sprintf(
                'morning: %s%s / afternoon: %s%s',
                $fclass($morning['class']),
                empty($morning['title']) ? '' : ' ' . $ftitle($morning['title']),
                $fclass($afternoon['class']),
                empty($afternoon['title']) ? '' : ' ' . $ftitle($afternoon['title'])
            );
        }

        $message .= sprintf("%s:  %s\n", $member->name, $status);

    }

    $slackWebhookUrl = sprintf(
        'https://%s.slack.com/services/hooks/incoming-webhook?token=%s',
        $config->settings['slack']['team'],
        $config->settings['slack']['token']
    );

    $slack = new SlackNotifier($slackWebhookUrl);
    $channel = $slackteam['slack'];
    // if there is no # then add it
    if ('#' !== substr($channel, 0, 1)) {
        $channel = '#' . $channel;
    }
    $slack->send($message, $channel);
}
