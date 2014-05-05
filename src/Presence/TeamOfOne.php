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
     * @param array             $people   Configuration.
     * @param CalendarInterface $calendar Calendar object.
     */
    public function __construct($app, $id, CalendarInterface $calendar)
    {
        // TODO add validation
        $this->id       = $id;
        $this->calendar = $calendar;
        $this->refresh  = $app['request']->get('refresh');
        $person = Sqlite::getPerson($app, $id);
        $this->name     = $person[0]['name'];
        $this->members  = array($this->getPerson($id, $this->name));
    }
}
