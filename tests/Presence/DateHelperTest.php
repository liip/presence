<?php

namespace Presence;

class HelperTest extends PresenceTestCase
{
    public function setup()
    {
        $this->helper = new DateHelper();
    }

    public function mondayProvider()
    {
        return array(
            array('2013-05-31', '2013-05-27'),
            array('2013-06-01', '2013-06-03'),
            array('2013-06-02', '2013-06-03'),
            array('2013-06-03', '2013-06-03'),
            array('2013-06-04', '2013-06-03'),
            array('2013-06-05', '2013-06-03'),
            array('2013-06-06', '2013-06-03'),
            array('2012-12-31', '2012-12-31'),
            array('2013-01-01', '2012-12-31'),
        );
    }

    /**
     * @dataProvider mondayProvider
     */
    public function testGetNextOrLastMonday($currentDate, $monday)
    {
        $this->assertEquals(
            $this->helper->getNextOrLastMonday(new \DateTime($currentDate)),
            new \DateTime($monday)
        );
    }
}
