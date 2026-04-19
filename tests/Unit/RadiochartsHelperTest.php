<?php

/**
 * Unit tests for RadiochartsHelper.
 *
 * Only the methods that do NOT require a live database are covered here:
 * - getWeekDate()       – pure date arithmetic
 * - buildPreviousPositionMap() – pure array logic
 *
 * Methods that execute SQL queries (getChartEntries, upsertEntry) require an
 * integration test environment with a real or in-memory database and are
 * therefore not covered in this unit test suite.
 */

namespace Gonzoradio\Tests\Unit;

use Gonzoradio\Module\CiwvRadiocharts\Site\Helper\RadiochartsHelper;
use PHPUnit\Framework\TestCase;

class RadiochartsHelperTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Creates a helper backed by a mock that satisfies the DatabaseInterface
     * type-hint (no real DB calls are made in the tested methods).
     */
    private function makeHelper(): RadiochartsHelper
    {
        $db = $this->createStub(\Joomla\Database\DatabaseInterface::class);

        return new RadiochartsHelper($db);
    }

    // -----------------------------------------------------------------------
    // getWeekDate()
    // -----------------------------------------------------------------------

    public function testGetWeekDateOffsetZeroReturnsCurrentMonday(): void
    {
        $helper   = $this->makeHelper();
        $result   = $helper->getWeekDate(0);
        $expected = (new \DateTime('monday this week'))->format('Y-m-d');

        $this->assertSame($expected, $result);
    }

    public function testGetWeekDateOffsetMinusOneReturnsPreviousMonday(): void
    {
        $helper   = $this->makeHelper();
        $result   = $helper->getWeekDate(-1);
        $expected = (new \DateTime('monday this week'))->modify('-1 week')->format('Y-m-d');

        $this->assertSame($expected, $result);
    }

    public function testGetWeekDateOffsetMinusTwoReturnsCorrectMonday(): void
    {
        $helper   = $this->makeHelper();
        $result   = $helper->getWeekDate(-2);
        $expected = (new \DateTime('monday this week'))->modify('-2 weeks')->format('Y-m-d');

        $this->assertSame($expected, $result);
    }

    public function testGetWeekDateReturnsDateFormattedAsYMD(): void
    {
        $helper = $this->makeHelper();
        $result = $helper->getWeekDate(0);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
    }

    // -----------------------------------------------------------------------
    // buildPreviousPositionMap()
    // -----------------------------------------------------------------------

    public function testBuildPreviousPositionMapReturnsEmptyArrayForEmptyInput(): void
    {
        $map = RadiochartsHelper::buildPreviousPositionMap([]);

        $this->assertIsArray($map);
        $this->assertEmpty($map);
    }

    public function testBuildPreviousPositionMapKeyIsLowercaseSourceArtistTitle(): void
    {
        $entry           = new \stdClass();
        $entry->artist   = 'Test Artist';
        $entry->title    = 'Test Title';
        $entry->position = 5;

        $previousEntries = ['mediabase_national' => [$entry]];
        $map             = RadiochartsHelper::buildPreviousPositionMap($previousEntries);

        $this->assertArrayHasKey('mediabase_national|test artist|test title', $map);
        $this->assertSame(5, $map['mediabase_national|test artist|test title']);
    }

    public function testBuildPreviousPositionMapHandlesMultipleSources(): void
    {
        $entry1           = new \stdClass();
        $entry1->artist   = 'Artist A';
        $entry1->title    = 'Song A';
        $entry1->position = 1;

        $entry2           = new \stdClass();
        $entry2->artist   = 'Artist B';
        $entry2->title    = 'Song B';
        $entry2->position = 3;

        $previousEntries = [
            'mediabase_national' => [$entry1],
            'luminate'           => [$entry2],
        ];

        $map = RadiochartsHelper::buildPreviousPositionMap($previousEntries);

        $this->assertCount(2, $map);
        $this->assertSame(1, $map['mediabase_national|artist a|song a']);
        $this->assertSame(3, $map['luminate|artist b|song b']);
    }

    public function testBuildPreviousPositionMapPositionIsCastToInt(): void
    {
        $entry           = new \stdClass();
        $entry->artist   = 'X';
        $entry->title    = 'Y';
        $entry->position = '7'; // string from DB

        $map = RadiochartsHelper::buildPreviousPositionMap(['musicmaster' => [$entry]]);

        $this->assertSame(7, $map['musicmaster|x|y']);
        $this->assertIsInt($map['musicmaster|x|y']);
    }
}
