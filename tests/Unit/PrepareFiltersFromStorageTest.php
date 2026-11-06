<?php

namespace Aurora\Modules\PersonalContacts\Tests\Unit;

use Aurora\Modules\Contacts\Enums\StorageType;
use Aurora\Modules\PersonalContacts\Module;
use Aurora\System\Api;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class FakeUser
{
    public $Id = 100;
    public $PublicId = 'user@example.com';
    public $Role = 0;
}

class PrepareFiltersFromStorageTest extends TestCase
{
    private $module;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->schema()->create('adav_addressbooks', function ($table) {
            $table->increments('id');
            $table->string('principaluri');
            $table->string('uri');
        });
        $capsule->schema()->create('adav_cards', function ($table) {
            $table->increments('id');
            $table->integer('addressbookid');
        });

        $capsule->schema()->create('contacts_cards', function ($table) {
            $table->increments('Id');
            $table->integer('CardId');
            $table->integer('AddressBookId');
        });

        Capsule::table('adav_addressbooks')->insert([
            ['id' => 1, 'principaluri' => 'principals/user@example.com', 'uri' => 'Default'],
            ['id' => 2, 'principaluri' => 'principals/user@example.com', 'uri' => 'Collected'],
            ['id' => 3, 'principaluri' => 'principals/other@example.com', 'uri' => 'Default'],
        ]);
        Capsule::table('adav_cards')->insert([
            ['id' => 10, 'addressbookid' => 1],
            ['id' => 20, 'addressbookid' => 2],
            ['id' => 30, 'addressbookid' => 3],
        ]);
        Capsule::table('contacts_cards')->insert([
            ['CardId' => 10, 'AddressBookId' => 1],
            ['CardId' => 20, 'AddressBookId' => 2],
            ['CardId' => 30, 'AddressBookId' => 3],
        ]);

        Api::skipCheckUserRole(true);
        Api::GetModuleManager();
        $this->module = new Module(dirname(__DIR__, 4) . '/');

        (new \ReflectionProperty(Api::class, 'usersCache'))->setValue(null, [100 => new FakeUser()]);
    }

    protected function tearDown(): void
    {
        Api::skipCheckUserRole(false);
        (new \ReflectionProperty(Api::class, 'usersCache'))->setValue(null, []);
    }

    private function buildQuery($suggestions)
    {
        $query = Capsule::table('contacts_cards')
            ->select('adav_cards.id')
            ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
            ->join('adav_addressbooks', 'adav_addressbooks.id', '=', 'adav_cards.addressbookid');

        $query->where(function ($whereQuery) use ($query, $suggestions) {
            $aArgs = ['UserId' => 100, 'Storage' => StorageType::All, 'Query' => $query, 'Suggestions' => $suggestions];
            $this->module->onPrepareFiltersFromStorage($aArgs, $whereQuery);
        });

        return $query;
    }

    public function testAddressBookIdsAreLiteralSoTheIndexCanBeUsed()
    {
        $sql = strtolower($this->buildQuery(true)->toSql());

        $this->assertStringNotContainsString('(select', $sql);
    }

    public function testSuggestionsIncludeCollectedContactsOfTheUserOnly()
    {
        $ids = $this->buildQuery(true)->orderBy('adav_cards.id')->pluck('id')->all();

        $this->assertSame([10, 20], array_map('intval', $ids));
    }

    public function testContactListExcludesCollectedContacts()
    {
        $ids = $this->buildQuery(false)->pluck('id')->all();

        $this->assertSame([10], array_map('intval', $ids));
    }
}
