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
     * @param Sqlite            $sqlite   Sqlite object.
     * @param string            $id       The team id.
     * @param CalendarInterface $calendar Calendar object.
     */
    public function __construct($sqlite, $id, CalendarInterface $calendar)
    {
        // TODO add validation
        $this->id       = $id;
        $this->calendar = $calendar;
        $this->refresh  = $sqlite->app['request']->get('refresh');
        $person         = $sqlite->getPerson($id);
        $this->name     = $person[0]['name'];
        $this->members  = array($this->getPerson($id, $this->name));
    }
}
