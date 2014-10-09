<?php

namespace Presence;

use \DateTime;

/**
 * This class represents a person, which typically is a participant of an event.
 */
class Person
{
    /**
     * Used to save error state.
     *
     * @var boolean
     */
    public $hasError        = false;

    /**
     * The Person's ID (= Email).
     *
     * @var string
     */
    public $id              = '';

    /**
     * The Person's full name.
     *
     * @var string
     */
    public $name            = '';

    /**
     * The Person's location, e.g. Zurich.
     *
     * @var string
     */
    public $location        = '';

    /**
     * Contains thhe Event objects for this Person.
     *
     * @var array
     */
    protected $events       = array();

    /**
     * Events by date.
     *
     * @var array
     */
    protected $eventsByDate = array();

    /**
     * Time slots.
     *
     * @var array
     */
    protected $timeSlots    = array();

    private $holidays = array();

    /**
     * Constructor of Person object.
     *
     * @param string $id       The Person's id.
     * @param string $name     Name of the person.
     * @param string $location Location of the person.
     */
    public function __construct($id, $name, $location)
    {
        $this->id = $id;
        $this->name = $name;
        $this->location = $location;
    }

    /**
     * Overwrites data if is set.
     *
     * @param array  $data The property value.
     * @param string $key  The property key.
     *
     * @return void
     */
    protected function setDataKey(array $data, $key)
    {
        if (isset($data[$key])) {
            $this->$key = $data[$key];
        }
    }

    /**
     * Return the name of the holiday if there is one or an empty string.
     *
     * @param DateTime $date The date to check for.
     * @param string   $type Morning or afternoon.
     *
     * @return string
     */
    protected function getHolidayName(DateTime $date, $type)
    {
        if (isset($this->holidays[$date->format('y-m-d')])) {
            $holiday = $this->holidays[$date->format('y-m-d')];
            if (true === $holiday['type'][$type] && true === $holiday['location'][$this->location]) {
                return $holiday['name'];
            }
        }
        return '';
    }

    /**
     * Sets the events and initializes each as Event().
     *
     * @param array $events Contains the data from the calendar service.
     *
     * @return void
     */
    public function setEvents(array $events)
    {
        foreach ($events as $event) {
            $this->events[] = new Event($event);
        }
    }

    /**
     * Gets the schedule for this Person.
     *
     * @param CalendarInterface $calendar Calendar object.
     *
     * @return void
     */
    public function getSchedule(CalendarInterface $calendar)
    {
        try {
            $events = $calendar->getSchedule($this->id);
            $this->setEvents($events['items']);
        } catch (\Exception $e) {
            $this->hasError = true;
        }
    }

    /**
     * Returns all events.
     *
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Gets all events by a given day (DateTime).
     *
     * @param DateTime $date The day.
     *
     * @return array
     */
    protected function getEventsByDate(DateTime $date)
    {
        $events = array();

        $id = $date->format('Ymd');

        if (!empty($this->eventsByDate[$id])) {
            return $this->eventsByDate[$id];
        }

        foreach ($this->events as $event) {
            if ($event->isActiveAtDate($date)) {
                $events[] = $event;
            }
        }

        $this->eventsByDate[$id] = $events;

        return $events;
    }

    /**
     * Gets the location for the day.
     *
     * @param DateTime $date The day.
     *
     * @return string
     */
    public function getLocationByDate(DateTime $date)
    {

        $morningHoliday = $this->getHolidayName($date, 'morning');
        $afternoonHoliday = $this->getHolidayName($date, 'afternoon');
        if (!empty($morningHoliday) && !empty($afternoonHoliday)) {
            return '';
        }

        $events = $this->getEventsByDate($date);

        foreach ($events as $event) {
            if ($event::TYPE_OFF === $event->type && true === $event->fullDay) {
                return '';
            }
            if ($event::TYPE_LOCATION === $event->type) {
                return $event->location;
            }
        }

        return strtoupper($this->location);
    }

    /**
     * Gets the event list for a given date (having type Event::TYPE_APPOINTMENT).
     *
     * @param DateTime $date The day.
     *
     * @return array
     */
    public function getEventListByDate(DateTime $date)
    {
        $relevantEvents = array();
        $events = $this->getEventsByDate($date);

        foreach ($events as $event) {
            if ($event::TYPE_APPOINTMENT === $event->type) {
                $relevantEvents[] = $event;
            }
        }

        return $relevantEvents;
    }

    public function setHolidays($holidays)
    {
        $this->holidays = $holidays;
    }

    /**
     * Gets the time slot class and title for a given date and timeslot (morning, afternoon).
     *
     * @param string   $type     The type of the event (morning/afternoon).
     * @param DateTime $date     The day.
     *
     * @return array
     */
    public function getTimeSlotByDate($type, DateTime $date)
    {
        $events = $this->getEventsByDate($date);
        $class = 'available';

        $id = $type . $date->format('Ymd');

        if (!empty($this->timeSlots[$id])) {
            return $this->timeSlots[$id];
        }

        $holiday = $this->getHolidayName($date, $type);
        if (!empty($holiday)) {
            return $this->timeSlots[$id] = array(
                'class' => 'off',
                'title' => $holiday
            );
        }

        foreach ($events as $event) {
            // Skip declined events
            if ($event->getResponseStatus($this) === 'declined') {
                continue;
            }

            // Skip location events
            if ($event::TYPE_LOCATION === $event->type) {
                continue;
            }

            // Skip if event is not active at timeslot
            if (true !== $event->fullDay && true !== $this->checkEventActiveOnDayTime($type, $event, $date)) {
                continue;
            }

            // Off events force an off class
            if ($event::TYPE_OFF === $event->type) {
                return $this->timeSlots[$id] = array(
                    'class' => 'off',
                    'title' => str_replace('#', '', $event->summary)
                );
            }

            if ($event::TYPE_PROJECT === $event->type) {
                return $this->timeSlots[$id] = array(
                    'class' => 'busy',
                    'title' => str_replace('#', '', $event->summary)
                );
            }

            if ($event::TYPE_APPOINTMENT === $event->type) {
                $class = 'busy';
            }
        }

        return $this->timeSlots[$id] = array(
            'class'     => $class,
            'title'     => ''
        );
    }

    /**
     * Checks if the event is active at a given timeslot.
     *
     * Morning:     Skip if the event begins in the afternoon on the same day
     * Afternoon:   Skip if the event ends in the morning on the same day
     *
     * @param string   $type  Event type (Morninga/fternoon).
     * @param Event    $event The Event object.
     * @param DateTime $date  The date to check.
     *
     * @return boolean
     */
    protected function checkEventActiveOnDayTime($type, Event $event, DateTime $date)
    {
        // Morning: Skip if the event begins in the afternoon on the same day
        if ('morning' === $type
            && $event->start->format('d.m.Y') === $date->format('d.m.Y')
            && 12 <= $event->start->format('H')) {

            return false;

            // Afternoon: Skip if the event ends in the morning on the same day
        } elseif ('afternoon' === $type &&
                  $event->end->format('d.m.Y') === $date->format('d.m.Y') &&
                  12 >= $event->end->format('H')) {
            return false;
        }

        return true;
    }

    /**
     * Get the availability class for a given date (full day).
     *
     * @param DateTime $date The date.
     *
     * @return string
     */
    public function getLocationAvailabilityClassByDate(DateTime $date, $holidays = array())
    {
        $events     = $this->getEventsByDate($date);

        // get morning and afternoon stats
        $morning    = $this->getTimeSlotByDate('morning', $date);
        $afternoon  = $this->getTimeSlotByDate('afternoon', $date);

        // fully off
        if ('off' === $morning['class'] && 'off' === $afternoon['class']) {
            return 'off';
        }

        // fully available
        if ('available' === $morning['class'] && 'available' === $afternoon['class']) {
            return 'available';
        }

        // busy if not off and not fully available
        return 'busy';
    }
}
