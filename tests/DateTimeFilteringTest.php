<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Tests\Models\TestModel;

class DateTimeFilteringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed deterministic records around dates and datetimes
        TestModel::unguard();

        // Same date, different times
        TestModel::create([
            'name' => 'Morning Record',
            'birth_date' => '1990-01-15',
            'created_at' => '2023-01-15 08:00:00',
            'updated_at' => '2023-01-15 08:00:00',
        ]);

        TestModel::create([
            'name' => 'Evening Record',
            'birth_date' => '1990-01-15',
            'created_at' => '2023-01-15 20:30:00',
            'updated_at' => '2023-01-15 20:30:00',
        ]);

        // Different date
        TestModel::create([
            'name' => 'Next Day Record',
            'birth_date' => '1995-06-20',
            'created_at' => '2023-06-20 14:45:00',
            'updated_at' => '2023-06-20 14:45:00',
        ]);
    }

    public function test_date_filtering_uses_whereDate_and_matches_whole_day()
    {
        // Filter by YYYY-MM-DD on a datetime field should behave as whereDate
        $results = TestModel::filter(['created_at' => '2023-01-15'])->get();

        $this->assertCount(2, $results);
        $this->assertContains('Morning Record', $results->pluck('name'));
        $this->assertContains('Evening Record', $results->pluck('name'));
    }

    public function test_datetime_filtering_with_full_timestamp_matches_exact()
    {
        $results = TestModel::filter(['created_at' => '2023-01-15 08:00:00'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Morning Record', $results->first()->name);
    }

    public function test_date_comparison_operators()
    {
        // birth_date is a date type, so comparisons should work on dates
        $after1992 = TestModel::filter(['birth_date' => ['>' => '1992-01-01']])->get();
        $this->assertCount(1, $after1992);
        $this->assertEquals('Next Day Record', $after1992->first()->name);

        $onOrBefore1990 = TestModel::filter(['birth_date' => ['<=' => '1990-01-15']])->get();
        $this->assertCount(2, $onOrBefore1990);
        $this->assertContains('Morning Record', $onOrBefore1990->pluck('name'));
        $this->assertContains('Evening Record', $onOrBefore1990->pluck('name'));
    }

    public function test_datetime_comparison_operators_with_aliases()
    {
        $afterMorning = TestModel::filter(['created_at' => ['gt' => '2023-01-15 08:00:00']])->get();
        $this->assertCount(2, $afterMorning);
        $this->assertContains('Evening Record', $afterMorning->pluck('name'));
        $this->assertContains('Next Day Record', $afterMorning->pluck('name'));

        $beforeOrAtEvening = TestModel::filter(['created_at' => ['lte' => '2023-01-15 20:30:00']])->get();
        $this->assertCount(2, $beforeOrAtEvening);
        $this->assertContains('Morning Record', $beforeOrAtEvening->pluck('name'));
        $this->assertContains('Evening Record', $beforeOrAtEvening->pluck('name'));
    }
}

