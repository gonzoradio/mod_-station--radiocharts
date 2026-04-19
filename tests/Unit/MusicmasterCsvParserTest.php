<?php

/**
 * Unit tests for the Music Master CSV parsing logic.
 *
 * The parseCsv() method is private, so we subclass the plugin and expose it
 * via a public wrapper using ReflectionMethod.
 */

namespace Gonzoradio\Tests\Unit;

use Gonzoradio\Plugin\Task\CiwvRadiochartsMusicmaster\Extension\CiwvRadiochartsMusicmaster;
use PHPUnit\Framework\TestCase;

/**
 * Subclass that exposes the private parseCsv() method for unit testing.
 */
class TestableMusicmaster extends CiwvRadiochartsMusicmaster
{
    public function __construct()
    {
        // Skip parent constructor to avoid requiring a live Joomla instance.
    }

    public function exposeParseCsv(
        string $path,
        bool $hasHeaderRow,
        int $colPosition,
        int $colArtist,
        int $colTitle,
        ?int $colLabel,
        ?int $colPlays,
        ?int $colPeakPosition,
        ?int $colWeeksOnChart
    ): ?array {
        $method = new \ReflectionMethod(CiwvRadiochartsMusicmaster::class, 'parseCsv');
        $method->setAccessible(true);

        return $method->invoke(
            $this,
            $path,
            $hasHeaderRow,
            $colPosition,
            $colArtist,
            $colTitle,
            $colLabel,
            $colPlays,
            $colPeakPosition,
            $colWeeksOnChart
        );
    }
}

class MusicmasterCsvParserTest extends TestCase
{
    /** @var string Temp CSV file path */
    private string $csvFile;

    protected function setUp(): void
    {
        $this->csvFile = tempnam(sys_get_temp_dir(), 'mm_csv_');
    }

    protected function tearDown(): void
    {
        if (is_file($this->csvFile)) {
            unlink($this->csvFile);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function writeCsv(array $rows): void
    {
        $handle = fopen($this->csvFile, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    private function makePlugin(): TestableMusicmaster
    {
        return new TestableMusicmaster();
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function testParsesSimpleRowsWithoutHeader(): void
    {
        $this->writeCsv([
            ['1', 'Artist One', 'Song One', 'Label A', '100', '1', '5'],
            ['2', 'Artist Two', 'Song Two', 'Label B', '80',  '2', '3'],
        ]);

        $result = $this->makePlugin()->exposeParseCsv(
            $this->csvFile, false, 0, 1, 2, 3, 4, 5, 6
        );

        $this->assertCount(2, $result);

        $this->assertSame(1, $result[0]['position']);
        $this->assertSame('Artist One', $result[0]['artist']);
        $this->assertSame('Song One', $result[0]['title']);
        $this->assertSame('Label A', $result[0]['label']);
        $this->assertSame(100, $result[0]['plays']);
        $this->assertSame(1, $result[0]['peak_position']);
        $this->assertSame(5, $result[0]['weeks_on_chart']);
    }

    public function testSkipsHeaderRowWhenEnabled(): void
    {
        $this->writeCsv([
            ['Position', 'Artist', 'Title', 'Label', 'Plays', 'Peak', 'WOC'],
            ['1', 'Artist One', 'Song One', 'Label A', '100', '1', '5'],
        ]);

        $result = $this->makePlugin()->exposeParseCsv(
            $this->csvFile, true, 0, 1, 2, 3, 4, 5, 6
        );

        $this->assertCount(1, $result);
        $this->assertSame('Artist One', $result[0]['artist']);
    }

    public function testDoesNotSkipFirstDataRowWhenHeaderDisabled(): void
    {
        $this->writeCsv([
            ['1', 'Artist One', 'Song One'],
            ['2', 'Artist Two', 'Song Two'],
        ]);

        $result = $this->makePlugin()->exposeParseCsv(
            $this->csvFile, false, 0, 1, 2, null, null, null, null
        );

        $this->assertCount(2, $result);
    }

    public function testSkipsRowsWithEmptyArtistAndTitle(): void
    {
        $this->writeCsv([
            ['1', 'Artist One', 'Song One'],
            ['',  '',           ''],
            ['3', 'Artist Three', 'Song Three'],
        ]);

        $result = $this->makePlugin()->exposeParseCsv(
            $this->csvFile, false, 0, 1, 2, null, null, null, null
        );

        $this->assertCount(2, $result);
    }

    public function testOmitsOptionalColumnsWhenNullIndexGiven(): void
    {
        $this->writeCsv([
            ['1', 'Artist One', 'Song One'],
        ]);

        $result = $this->makePlugin()->exposeParseCsv(
            $this->csvFile, false, 0, 1, 2, null, null, null, null
        );

        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('label', $result[0]);
        $this->assertArrayNotHasKey('plays', $result[0]);
        $this->assertArrayNotHasKey('peak_position', $result[0]);
        $this->assertArrayNotHasKey('weeks_on_chart', $result[0]);
    }

    public function testReturnsNullForUnreadableFile(): void
    {
        $result = $this->makePlugin()->exposeParseCsv(
            '/nonexistent/path/file.csv', false, 0, 1, 2, null, null, null, null
        );

        $this->assertNull($result);
    }

    public function testHandlesQuotedFieldsAndCommasInValues(): void
    {
        $this->writeCsv([
            ['1', 'Smith, John', 'Greatest Hits Vol. 1', 'Big Label'],
        ]);

        $result = $this->makePlugin()->exposeParseCsv(
            $this->csvFile, false, 0, 1, 2, 3, null, null, null
        );

        $this->assertCount(1, $result);
        $this->assertSame('Smith, John', $result[0]['artist']);
    }

    public function testPositionIsCastToInt(): void
    {
        $this->writeCsv([
            ['007', 'Artist', 'Title'],
        ]);

        $result = $this->makePlugin()->exposeParseCsv(
            $this->csvFile, false, 0, 1, 2, null, null, null, null
        );

        $this->assertSame(7, $result[0]['position']);
        $this->assertIsInt($result[0]['position']);
    }

    public function testEmptyFileReturnsEmptyArray(): void
    {
        file_put_contents($this->csvFile, '');

        $result = $this->makePlugin()->exposeParseCsv(
            $this->csvFile, false, 0, 1, 2, null, null, null, null
        );

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
