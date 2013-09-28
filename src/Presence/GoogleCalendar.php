<?php

namespace Presence;

use \DateTime;

/**
 * Google Calendar API implemenation.
 */
class GoogleCalendar implements CalendarInterface
{
    /**
     * The definition of the OAuth scopes.
     *
     * @var array
     */
    protected $scopes = array('https://www.googleapis.com/auth/calendar.readonly');

    /**
     * The Google calendar service object.
     *
     * @var Google_CalendarService
     */
    protected $api = null;

    /**
     * The start of the date range.
     *
     * @var DateTime
     */
    protected $startDate = null;

    /**
     * The end of the date range.
     *
     * @var DateTime
     */
    protected $endDate = null;

    /**
     * The configuration settings for the Google API.
     *
     * @var array
     */
    protected $config = array();


    /**
     * The private key for the Google API.
     *
     * @var string
     */
    protected $key = '';

    /**
     * Google Client Object.
     *
     * @var Google_Client
     */
    protected $client = null;

    /**
     * The time to live for the cache.
     *
     * @var integer
     */
    protected $cacheTtl  = 0;

    /**
     * Assign variable and intialize the API.
     *
     * @param array    $config    The configuration array.
     * @param DateTime $startDate The start of the date range for which the schedule should be returned.
     * @param DateTime $endDate   The end of the date range for which the schedule should be returned.
     */
    public function __construct(array $config, DateTime $startDate, DateTime $endDate)
    {
        $this->config    = $config;
        $this->startDate = $startDate->format(DateTime::ATOM);
        $this->endDate   = $endDate->format(DateTime::ATOM);
        $this->key       = file_get_contents($config['keyFile']);
        $this->cacheTtl  = $config['cacheTtl'];
        $this->api       = $this->initializeApi();
    }

    /**
     * Initialize the calendar API object.
     *
     * @return Google_CalendarService A new Google_CalendarService object
     */
    protected function initializeApi()
    {
        if (is_null($this->client)) {
            $this->client = new \Google_Client();
        }

        $this->client->setApplicationName("Availabilities");
        $this->client->setAssertionCredentials(
            new \Google_AssertionCredentials(
                $this->config['serviceAccountName'],
                $this->scopes,
                $this->key
            )
        );
        $this->client->setClientId($this->config['clientId']);

        return new \Google_CalendarService($this->client);
    }

    /**
     * Returns the calendar for a specific user.
     *
     * @param string   $user      The user of which the schedule should be returned.
     * @param DateTime $startDate The start of the date range for which the schedule should be returned.
     * @param DateTime $endDate   The end of the date range for which the schedule should be returned.
     *
     * @return mixed the events
     */
    public function getSchedule($user, DateTime $startDate = null, DateTime $endDate = null)
    {
        return $this->api->events->listEvents(
            $user,
            array(
                'singleEvents' => true,
                'orderBy'      => 'startTime',
                'fields'       => 'items(end,start,summary,location,locked,organizer,attendees),summary',
                'timeMin'      => $this->startDate,
                'timeMax'      => $this->endDate,
            )
        );
    }

    /**
     * Get the start date.
     *
     * @return string
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Get the start date.
     *
     * @return string
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * Get the cache TTL.
     *
     * @return string
     */
    public function getCacheTtl()
    {
        return $this->cacheTtl;
    }
}
