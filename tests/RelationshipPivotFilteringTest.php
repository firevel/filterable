<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class RelationshipPivotFilteringTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // bookings, contacts and the booking_contact pivot all expose an "id"
        // column. A relationship sub-filter on contacts.id must qualify the
        // column or the join becomes ambiguous (MySQL 1052 / SQLite
        // "ambiguous column name: id").
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('booking_contact', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('contact_id');
        });

        $booking1 = Booking::create(['reference' => 'BK-1']);
        $booking2 = Booking::create(['reference' => 'BK-2']);

        $contact1 = Contact::create(['name' => 'Alice']);
        $contact2 = Contact::create(['name' => 'Bob']);
        $contact3 = Contact::create(['name' => 'Carol']);

        $booking1->contacts()->attach([$contact1->id, $contact2->id]);
        $booking2->contacts()->attach([$contact3->id]);
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_relationship_filter_through_pivot_qualifies_column()
    {
        // The generated SQL must qualify the column against the related table.
        $sql = Booking::filter(['contacts.id' => 2])->toSql();

        $this->assertStringContainsString('"contacts"."id"', $sql);

        // And it must execute without an ambiguous-column error.
        $results = Booking::filter(['contacts.id' => 2])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('BK-1', $results->first()->reference);
    }

    public function test_relationship_in_filter_is_scoped_inside_where_has()
    {
        $sql = Booking::filter(['contacts.id' => ['in' => '1,3']])->toSql();

        // whereIn must run against the qualified related column inside the
        // exists() subquery, not the outer bookings query.
        $this->assertStringContainsString('"contacts"."id" in', $sql);
        $this->assertStringContainsString('exists', $sql);

        $results = Booking::filter(['contacts.id' => ['in' => '1,3']])
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals(['BK-1', 'BK-2'], $results->pluck('reference')->all());
    }
}

class Booking extends Model
{
    use Filterable;

    protected $fillable = ['reference'];

    protected $filterable = [
        'id' => 'id',
        'reference' => 'string',
        'contacts' => 'relationship',
        'contacts.id' => 'id',
        'contacts.name' => 'string',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'booking_contact');
    }
}

class Contact extends Model
{
    protected $fillable = ['name'];
}
