<?php

namespace Presence;

use \DateTime;
use \DateInterval;

/**
 * Helper to assist with start and enddate parsing/handling.
 */
class DateHelper
{
    /**
     * Start date from when to get events.
     *
     * @var DateTime
     */
    protected $startDate = null;

    /**
     * Intialize the $startDate.
     */
    public function __construct()
    {
        $this->startDate = new DateTime();
    }

    /**
     * Gets the starting date (Monday).
     *
     * @param string $weekString The week GET parameter.
     *
     * @return DateTime
     */
    public function getStartDate($weekString = '')
    {
        if (!empty($weekString)) {
            $this->parseWeekString($weekString);
        } else {
            $this->setStartDateToNextOrLastMonday();
        }

        // get the date at the time "00:00:00"
        $this->startDate = DateTime::createFromFormat('Y-m-d H:i:s', $this->startDate->format('Y-m-d') . ' 00:00:00');

        return $this->startDate;
    }

    /**
     * Gets the end date.
     *
     * @param integer $weeks Number of weeks.
     * @param integer $days  Number of days.
     *
     * @return DateTime
     */
    public function getEndDate($weeks = 1, $days = 0)
    {
        $endDate = clone $this->startDate;

        $endDate->add(new DateInterval("P{$weeks}W"));
        $endDate->add(new DateInterval("P{$days}D"));

        return $endDate;
    }

    /**
     * Gets the days.
     *
     * @param DateTime $start Start date.
     * @param DateTime $end   End date.
     *
     * @return array $days
     */
    public function getDays(DateTime $start, DateTime $end)
    {
        $days = array();

        $period = new \DatePeriod($start, new DateInterval('P1D'), $end);

        foreach ($period as $day) {
            // skip saturdays and sundays
            if ($day->format('N') < 6) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * Parse the passed parameter and convert it do DateTime.
     *
     * @param string $weekString The week GET parameter.
     *
     * @throws InvalidWeekStringException Exception thrown on error.
     *
     * @return void
     */
    protected function parseWeekString($weekString)
    {
        preg_match('/^([0-9]{4,4})\-([0-9]{1,2})$/i', $weekString, $matches);

        $weekTimeStamp =  $this->getWeekTimeStamp($matches);

        if (empty($matches)
            || !$weekTimeStamp
            || !$this->startDate->setTimestamp($weekTimeStamp)) {
            throw new InvalidWeekStringException(
                'You have to set the GET parameter "week" in the format Y-w (2012-44)'
            );
        }
    }

    /**
     * Depending on where we stand in the week take the last or next Monday.
     *
     * @return void
     */
    protected function setStartDateToNextOrLastMonday()
    {
        $dayNr = $this->startDate->format('N');
        if ($dayNr > 5) {
            $this->startDate->add(new DateInterval('P' . (8 - $dayNr) . 'D'));
        } else {
            $this->startDate->sub(new DateInterval('P' . ($dayNr - 1) . 'D'));
        }
    }

    /**
     * Parse matches into format e.g. '2013W10' and produce a timestamp or false if it fails.
     *
     * @param array $matches The matches from the regexp.
     *
     * @return int | false   The week or false if the strtotime operation fails.
     */
    protected function getWeekTimeStamp(array $matches)
    {
        return strtotime($matches[1] . 'W' . str_pad($matches[2], 2, "0", STR_PAD_LEFT));
    }
}
