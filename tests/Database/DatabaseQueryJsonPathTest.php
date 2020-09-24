<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Query\JsonPath;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class DatabaseQueryJsonPathTest extends TestCase
{
    public function testItThrowsAnExceptionWhenNoPathIsGiven()
    {
        $this->expectException(UnexpectedValueException::class);

        (string) JsonPath::make();
    }

    public function testItThrowsAnyExceptionWhenPatternMatchIsMissingSuffix()
    {
        $this->expectException(UnexpectedValueException::class);

        (string) JsonPath::make()->identifier('foo')->matchAny();
    }

    public function testItFormatsAnIdentifer()
    {
        $this->assertSame("'$.foo'", (string) JsonPath::make()->identifier('foo'));
    }

    public function testItFormatsAnIdentiferWithScope()
    {
        $this->assertSame("'foo.bar_baz'", (string) JsonPath::make()->scope('foo')->identifier('bar_baz'));
    }

    public function testItFormatsArrayIndices()
    {
        $this->assertSame("'$.foo[1]'", (string) JsonPath::make()->identifier('foo')->arrayIndex(1));
        $this->assertSame("'$.foo[1]'", (string) JsonPath::make()->identifier('foo')[1]);
        $this->assertSame("'$.foo[#-1]'", (string) JsonPath::make()->identifier('foo')->arrayIndex('#-1'));
        $this->assertSame("'$.foo[#-1]'", (string) JsonPath::make()->identifier('foo')['#-1']);
    }

    public function testItFormatsAllArrayItems()
    {
        $this->assertSame("'$.foo[*]'", (string) JsonPath::make()->identifier('foo')->allArrayItems());
    }

    public function testItFormatsObjectKeys()
    {
        $this->assertSame('\'$.foo."bar"\'', (string) JsonPath::make()->identifier('foo')->key('bar'));
        $this->assertSame('\'$.foo."bar"\'', (string) JsonPath::make()->identifier('foo')->bar);
        $this->assertSame('\'$.foo."bar baz"\'', (string) JsonPath::make()->identifier('foo')->key('bar baz'));
        $this->assertSame('\'$.foo."bar-baz"\'', (string) JsonPath::make()->identifier('foo')->key('bar-baz'));
        $this->assertSame('\'$.foo."bar"[2]\'', (string) JsonPath::make()->identifier('foo')->key('bar')[2]);
        $this->assertSame('\'$.foo."bar"[2][1]."baz"\'', (string) JsonPath::make()->identifier('foo')->bar[2][1]->baz);
    }

    public function testItEscapesObjectKeysWithSingleQuoteCharacters()
    {
        $this->assertSame('\'$.foo."\'\'bar\'\'"\'', (string) JsonPath::make()->identifier('foo')->key("'bar'"));
    }

    public function testItFormatsAllObjectKeys()
    {
        $this->assertSame("'$.foo.*'", (string) JsonPath::make()->identifier('foo')->allKeys());
        $this->assertSame("'$.foo.*[*]'", (string) JsonPath::make()->identifier('foo')->allKeys('bar')->allArrayItems());
    }

    public function testItReformatsPatternMatchesWhenKeyHasAsterisk()
    {
        $this->assertSame('\'$.foo."bar"**."baz"\'', (string) JsonPath::make()->identifier('foo')->key('bar**baz'));
    }

    public function testItFormatsPatternMatches()
    {
        $this->assertSame('\'$**."foo"\'', (string) JsonPath::make()->matchAny()->foo);
        $this->assertSame('\'$.foo**."bar"\'', (string) JsonPath::make()->identifier('foo')->matchAny()->bar);
        $this->assertSame('\'$.foo**."bar"**."baz"\'', (string) JsonPath::make()->identifier('foo')->matchAny()->bar->matchAny()->baz);
        $this->assertSame('\'$.foo[0].**."bar"\'', (string) JsonPath::make()->identifier('foo')[0]->matchAny()->bar);
    }
}
