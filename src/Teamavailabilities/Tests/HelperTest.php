<?php

namespace Teamavailabilities\Tests;

class DateHelperTest extends TeamAvailabilitiesTestCase
{
    /**
     * @covers DateHelper::getStartDate()
     */
    public function testGetStartDateValid()
    {
        $resultDate = DateHelper::getStartDate("2013-01");
        $expectedDate = DateTime::createFromFormat('Y-m-d H:i:s', '2012-12-31 00:00:00');
        $this->assertEquals($expectedDate, $resultDate);
    }

    /**
     * @expectedException InvalidWeekStringException
     * @covers DateHelper::getStartDate()
     */
    public function testGetStartDateInvalid()
    {
        $resultDate = DateHelper::getStartDate("2013-55");
    }

    /**
     * @covers DateHelper::getStartDate()
     */
    public function testGetStartDateOneDigitWeek()
    {
        $resultDate = DateHelper::getStartDate("2013-2");
        $expectedDate = DateTime::createFromFormat('Y-m-d H:i:s', '2013-01-07 00:00:00');
        $this->assertEquals($expectedDate, $resultDate);
    }

    /**
     * @covers DateHelper::getStartDate()
     */
    public function testGetStartDateWithoutParameterWeekday()
    {
        $resultDate = DateHelper::getStartDate();
        $testDate = new DateTime();
        if ($testDate->format("N") >= 6) {
            $testDate->modify("next Monday");
        } else {
            $testDate->modify("last Monday");
        }
        $this->assertEquals($testDate, $resultDate);
    }
}
