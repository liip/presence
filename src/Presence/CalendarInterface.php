<?php

namespace Presence;

use \DateTime;

/**
 * Interface for a generic Calendar Service.
 */
interface CalendarInterface
{
    /**
     * Returns the calendar for a specific user.
     *
     * @param string   $user      The user of which the schedule should be returned.
     * @param DateTime $startDate The start of the date range for which the schedule should be returned.
     * @param DateTime $endDate   The end of the date range for which the schedule should be returned.
     *
     * @return mixed the events
     */
    public function getSchedule($user, DateTime $startDate = null, DateTime $endDate = null);
}
