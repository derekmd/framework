<?php

namespace Illuminate\Tests\Integration\Database;

use Illuminate\Database\Query\JsonPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @requires extension pdo_mysql
 */
class DatabaseMySqlConnectionTest extends DatabaseMySqlTestCase
{
    const TABLE = 'player';
    const FLOAT_COL = 'float_col';
    const JSON_COL = 'json_col';
    const FLOAT_VAL = 0.2;

    protected function setUp(): void
    {
        parent::setUp();

        if (! isset($_SERVER['CI']) || windows_os()) {
            // $this->markTestSkipped('This test is only executed on CI in Linux.');
        }

        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->json(self::JSON_COL)->nullable();
                $table->float(self::FLOAT_COL)->nullable();
            });
        }
    }

    protected function tearDown(): void
    {
        Schema::drop(self::TABLE);

        parent::tearDown();
    }

    /**
     * @dataProvider floatComparisonsDataProvider
     */
    public function testJsonFloatComparison($value, $operator, $shouldMatch)
    {
        DB::table(self::TABLE)->insert([self::JSON_COL => '{"rank":'.self::FLOAT_VAL.'}']);

        $this->assertSame(
            $shouldMatch,
            DB::table(self::TABLE)->where(self::JSON_COL.'->rank', $operator, $value)->exists(),
            self::JSON_COL.'->rank should '.($shouldMatch ? '' : 'not ')."be $operator $value"
        );
    }

    public function floatComparisonsDataProvider()
    {
        return [
            [0.2, '=', true],
            [0.2, '>', false],
            [0.2, '<', false],
            [0.1, '=', false],
            [0.1, '<', false],
            [0.1, '>', true],
            [0.3, '=', false],
            [0.3, '<', true],
            [0.3, '>', false],
        ];
    }

    public function testFloatValueStoredCorrectly()
    {
        DB::table(self::TABLE)->insert([self::FLOAT_COL => self::FLOAT_VAL]);

        $this->assertEquals(self::FLOAT_VAL, DB::table(self::TABLE)->value(self::FLOAT_COL));
    }

    /**
     * @dataProvider jsonWhereNullDataProvider
     */
    public function testJsonWhereNull($expected, $key, array $value = ['value' => 123])
    {
        DB::table(self::TABLE)->insert([self::JSON_COL => json_encode($value)]);

        $this->assertSame($expected, DB::table(self::TABLE)->whereNull(self::JSON_COL.'->'.$key)->exists());
    }

    /**
     * @dataProvider jsonWhereNullDataProvider
     */
    public function testJsonWhereNotNull($expected, $key, array $value = ['value' => 123])
    {
        DB::table(self::TABLE)->insert([self::JSON_COL => json_encode($value)]);

        $this->assertSame(! $expected, DB::table(self::TABLE)->whereNotNull(self::JSON_COL.'->'.$key)->exists());
    }

    public function jsonWhereNullDataProvider()
    {
        return [
            'key not exists' => [true, 'invalid'],
            'key exists and null' => [true, 'value', ['value' => null]],
            'key exists and "null"' => [false, 'value', ['value' => 'null']],
            'key exists and not null' => [false, 'value', ['value' => false]],
            'nested key not exists' => [true, 'nested->invalid'],
            'nested key exists and null' => [true, 'nested->value', ['nested' => ['value' => null]]],
            'nested key exists and "null"' => [false, 'nested->value', ['nested' => ['value' => 'null']]],
            'nested key exists and not null' => [false, 'nested->value', ['nested' => ['value' => false]]],
        ];
    }

    public function testJsonPathSelect()
    {
        DB::table(self::TABLE)->insert([
            [self::JSON_COL => '{"rank":0.2}'],
            [self::JSON_COL => '{"rank":0.8}'],
        ]);
        $this->assertTrue(
            DB::table(self::TABLE)->where(JsonPath::make(self::JSON_COL)->rank, '>', 0.5)->exists(),
            self::JSON_COL.', \'$."rank"\' should be > 0.5'
        );
    }

    public function testJsonPathUpdate()
    {
        DB::table(self::TABLE)->insert([
            [self::JSON_COL => '{"rank":0.2}'],
            [self::JSON_COL => '{"rank":0.8}'],
        ]);
        $updatedCount = DB::table(self::TABLE)->where(JsonPath::make(self::JSON_COL)->rank, '>', 0.5)->update([
            [JsonPath::make(self::JSON_COL)->rank, 0.7],
        ]);
        $this->assertSame(1, $updatedCount);
        $this->assertDatabaseHas(self::TABLE, [
            self::JSON_COL => '{"rank": 0.7}',
        ]);
    }
}
