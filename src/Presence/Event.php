<?php

namespace Presence;

use \DateTime;

/**
 * Every event is stored in such an Event object.
 */
class Event
{
    const TYPE_LOCATION    = 1;
    const TYPE_APPOINTMENT = 2;
    const TYPE_OFF         = 3;
    const TYPE_IRRELEVANT  = 4;
    const TYPE_PROJECT     = 5;

    const OFF_TYPE_REGEX      = '/(^|.*\s+)((?:\#)?(?:off|holiday|ferien|frei|free\ day|daddy\ day))(\s+.*|$)/i';
    const LOCATION_TYPE_REGEX = '/\@([a-zA-Z]+)/i';

    /**
     * The raw data of the event.
     *
     * @var array
     */
    public $raw       = array();

    /**
     * The event's summary.
     *
     * @var string
     */
    public $summary   = '';

    /**
     * The event's location.
     *
     * @var string
     */
    public $location  = '';

    /**
     * The event's organizer.
     *
     * @var string
     */
    public $organizer = '';

    /**
     * The event's attendees.
     *
     * @var string
     */
    public $attendees = null;

    /**
     * Start time.
     *
     * @var DateTime
     */
    public $start     = null;

    /**
     * End time.
     *
     * @var DateTime
     */
    public $end       = null;

    /**
     * If it is a full day event.
     *
     * @var boolean
     */
    public $fullDay   = false;

    /**
     * The type of the Event.
     *
     * @var integer
     */
    public $type      = 0;

    /**
     * Parse the raw data.
     *
     * @param array $raw The raw data.
     */
    public function __construct(array $raw)
    {
        $this->raw = $raw;

        $this->initialize();
    }

    /**
     * Sets the event parameters from the raw google event data (retrieved from the API).
     *
     * Params: Summary, Location, Start date, End date, Fullday, Type
     *
     * @return void
     */
    protected function initialize()
    {

        // set summary
        $this->summary = $this->getRawValue('summary');

        // set location
        $this->location = $this->getRawValue('location');

        // set organizer
        $this->organizer = $this->getRawValue('organizer');

        // attendees
        $this->attendees = $this->getRawValue('attendees');

        // set start
        $this->start = $this->getRawValue('start');

        // set end
        $this->end = $this->getRawValue('end');

        if (isset($this->start['date'])) {
            $this->fullDay = true;
        }

        // converts DateTime:ATOM formatted String to DateTime()
        $this->start = $this->parseDate($this->start);
        $this->end   = $this->parseDate($this->end);

        // parses the type of the event (Off, Location, Appointment)
        $this->type = $this->parseType();

    }

    /**
     * Gets the raw data by key.
     *
     * @param string $name Name of the event.
     *
     * @return string|array|null
     */
    protected function getRawValue($name)
    {
        if (isset($this->raw[$name])) {
            return $this->raw[$name];
        }
        return null;
    }

    /**
     * Converts DateTime:ATOM formatted String to DateTime().
     *
     * @param string $eventDateValue The date information.
     *
     * @throws \InvalidArgumentException Thrown if date information is not valid.
     *
     * @return DateTime
     */
    protected function parseDate($eventDateValue)
    {
        if (isset($eventDateValue['date'])) {
            return DateTime::createFromFormat('Y-m-d H:i:s', $eventDateValue['date'] . ' 00:00:00');
        } elseif (isset($eventDateValue['dateTime'])) {
            return DateTime::createFromFormat(DateTime::ATOM, $eventDateValue['dateTime']);
        } else {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * Parses the type of the event (Off, Location, Appointment).
     *
     * @return integer
     * @todo allow multiple types, in order to be able to set Location for #off tag
     */
    protected function parseType()
    {
        // parse off type (if #off tag is set in summary)
        if ($this->isOffType()) {
            return self::TYPE_OFF;
        }

        if ($this->isProjectType()) {
            return self::TYPE_PROJECT;
        }

        // parse location type (if @[LOCATION] tag is set in summary)
        if ($this->isLocationType()) {
            return self::TYPE_LOCATION;
        }

        // check if event is irrelevant
        if ($this->eventIsIrrelevant()) {
            return self::TYPE_IRRELEVANT;
        }

        // return type appointment
        return self::TYPE_APPOINTMENT;
    }

    /**
     * Parse off type (if #off tag is set in summary).
     *
     * @return boolean
     */
    protected function isOffType()
    {
        preg_match(self::OFF_TYPE_REGEX, $this->summary, $matches);
        if (!empty($matches)) {
            if (in_array($matches[2], array('#off', '#holiday'))) {
                $this->summary = trim($matches[1] . $matches[3]);
            }
            return true;
        }

        if (!empty($organizer['displayName']) && 'Liip absences' === $organizer['displayName']) {
            $this->summary = strtolower($this->summary);
            return true;
        }

        return false;
    }

    /**
     * Parse location type (if @Location tag is set in summary).
     *
     * @return boolean
     */
    protected function isLocationType()
    {
        preg_match(self::LOCATION_TYPE_REGEX, $this->summary, $matches);
        if (!empty($matches)) {
            $this->location = $matches[1];
            return true;
        }

        return false;
    }

    /**
     * Check if the event has a project tag.
     *
     * @return boolean
     */
    protected function isProjectType()
    {
        return preg_match('/#[a-zA-Z]{3,5}/', $this->summary);
    }

    /**
     * Check if the event is relevant (15min long or more).
     *
     * @return boolean
     */
    protected function eventIsIrrelevant()
    {
        if (is_null($this->start) && is_null($this->end)) {
            return true;
        }

        if ((15 * 60) >= ($this->end->getTimestamp() - $this->start->getTimestamp())) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the event is active on a given date.
     *
     * @param DateTime $date The date.
     *
     * @return boolean
     */
    public function isActiveAtDate(DateTime $date)
    {
        $dateEnd = clone $date;
        $dateEnd->add(new \DateInterval('P1D'));

        if ($this->end > $date && $this->start < $dateEnd) {
            return true;
        }
        return false;
    }

    /**
     * Checks if the event was accepted by the participant..
     *
     * @param Person $person The Person object.
     *
     * @return boolean
     */
    public function getResponseStatus(Person $person)
    {
        if (!empty($this->organizer['email']) && $person->id === $this->organizer['email']) {
            return 'accepted';
        }
        if (!empty($this->attendees)) {
            foreach ($this->attendees as $attendee) {
                if ($attendee['email'] === $person->id) {
                    return $attendee['responseStatus'];
                }
            }
        }
        return '';
    }
}
