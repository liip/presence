<?php

namespace Presence;

/**
 * A single person to display is treated as a team of one.
 */
class TeamOfOne extends Team
{
    /**
     * Assemble the team for this instance.
     *
     * @param string            $id       The team id.
     * @param CalendarInterface $calendar Calendar object.
     * @param Sqlite            $sqlite   Sqlite object.
     * @param boolean           $refresh  Refresh or not.
     */
    public function __construct($id, CalendarInterface $calendar, array $holidays, Sqlite $sqlite, $refresh)
    {
        // TODO add validation
        $this->id       = $id;
        $this->calendar = $calendar;
        $this->holidays = $holidays;
        $this->refresh  = $refresh;
        $person         = $sqlite->getPerson($id);
        $this->name     = $person[0]['name'];
        $this->location = $person[0]['location'];
        $this->members  = array($this->getPerson($id, $this->name, $this->location));
    }
}
