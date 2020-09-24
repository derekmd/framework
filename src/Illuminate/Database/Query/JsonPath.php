<?php

namespace Illuminate\Database\Query;

use ArrayAccess;
use Illuminate\Support\Str;
use LogicException;
use UnexpectedValueException;

class JsonPath implements ArrayAccess
{
    /**
     * The JSON database column being queried.
     *
     * @var \Illuminate\Database\Query\Expression|string
     */
    protected $column;

    /**
     * The JSON path segments containing 'name' and 'type'.
     *
     * @var array
     */
    protected $segments = [];

    /**
     * The scope for the JSON path.
     *
     * @var string
     */
    protected $scope = '$';

    /**
     * Create a JSON path expression.
     *
     * @param \Illuminate\Database\Query\Expression|string|null $column
     */
    public function __construct($column = null)
    {
        $this->column = $column;
    }

    /**
     * Create a new instance of the JsonPath expression.
     *
     * @param  \Illuminate\Database\Query\Expression|string|null  $column
     * @return static
     */
    public static function make($column = null)
    {
        return new static($column);
    }

    /**
     * Set a scope for the JSON path.
     *
     * @param  string  $scope
     * @return $this
     */
    public function scope($scope)
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Get the JSON database column being queried.
     *
     * @return \Illuminate\Database\Query\Expression|string|null
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * Add a segment for all array items.
     *
     * @return $this
     */
    public function allArrayItems()
    {
        return $this->arrayIndex('*');
    }

    /**
     * Add a segment for all child members.
     *
     * @return $this
     */
    public function allKeys()
    {
        return $this->identifier('*');
    }

    /**
     * Add a segment for an array index.
     *
     * @param  string|int  $index  An index or an asterisk character.
     * @return $this
     */
    public function arrayIndex($index)
    {
        return $this->addSegment('arrayIndex', $index);
    }

    /**
     * Add a segment for an ECMAScript identifier.
     *
     * @param  string  $name
     * @return $this
     */
    public function identifier($name)
    {
        return $this->addSegment('identifier', $name);
    }

    /**
     * Add a segment for a general pattern match.
     *
     * @return $this
     */
    public function matchAny()
    {
        return $this->addSegment('match', $this->lastSegmentTypeIs(['arrayIndex', 'match']) ? '.' : '');
    }

    /**
     * Add a segment for an object key.
     *
     * @param  string  $name
     * @return $this
     */
    public function key($name)
    {
        if ($name === '*') {
            return $this->allKeys();
        }

        if (! Str::contains($name, '**')) {
            return $this->addSegment('key', $name);
        }

        [$prefix, $suffix] = explode('**', $name, 2);

        if (! empty($prefix)) {
            $this->key($prefix);
        }

        return $this->matchAny()->key($suffix);
    }

    /**
     * Build the JSON path string for an SQL query.
     *
     * @return string
     */
    public function toSql()
    {
        if (empty($this->segments) && $this->scope === '$') {
            throw new UnexpectedValueException('The JSON path segments are empty.');
        }

        if ($this->lastSegmentTypeIs('match')) {
            throw new UnexpectedValueException('The JSON path pattern match ** requires a suffix.');
        }

        return "'".array_reduce($this->segments, static function ($sql, array $item) {
            return $sql .= static::wrapSegment($item['type'], $item['name']);
        }, $this->scope)."'";
    }

    /**
     * Adds a segment to the JSON path.
     *
     * @param  string  $type
     * @param  string  $name
     * @return $this
     */
    protected function addSegment($type, $name)
    {
        $this->segments[] = compact('type', 'name');

        return $this;
    }

    /**
     * Format the SQL for a JSON path segment.
     *
     * @param  string  $type
     * @param  string  $name
     * @return string
     */
    protected static function wrapSegment($type, $name)
    {
        switch ($type) {
            case 'key':
                return '."'.static::escapeKey($name).'"';
            case 'identifier':
                return '.'.$name;
            case 'arrayIndex':
                return '['.$name.']';
            case 'match':
                return $name.'**';
            default:
                return '';
        }
    }

    /**
     * Escape single-quote characters in a JSON path key name.
     *
     * @param  string  $name
     * @return string
     */
    protected static function escapeKey($name)
    {
        return preg_replace("/([\\\\]+)?\\'/", "''", $name);
    }

    /**
     * Determine if the last-appended segment is a given type.
     *
     * @param  array|string  $type
     * @return bool
     */
    protected function lastSegmentTypeIs($type)
    {
        return collect($this->segments)
            ->pluck('type')
            ->reverse()
            ->take(1)
            ->intersect($type)
            ->isNotEmpty();
    }

    /**
     * Dynamically add an object key segment.
     *
     * @param  string  $key
     * @return $this
     */
    public function __get($key)
    {
        return $this->key($key);
    }

    /**
     * Convert the JSON path to a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toSql();
    }

    /**
     * Determine if the given offset exists.
     *
     * @param  string  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return collect($this->segments)->contains(function ($item) use ($offset) {
            return $item['name'] === $offset && $item['type'] === 'arrayIndex';
        });
    }

    /**
     * Get the value for a given offset.
     *
     * @param  string  $offset
     * @return $this
     */
    public function offsetGet($offset)
    {
        return $this->arrayIndex($offset);
    }

    /**
     * Set the value at the given offset.
     *
     * @param  string  $offset
     * @param  mixed  $value
     * @return void
     *
     * @throws \LogicException
     */
    public function offsetSet($offset, $value)
    {
        throw new LogicException('JSON path may not be mutated using array access.');
    }

    /**
     * Unset the value at the given offset.
     *
     * @param  string  $offset
     * @return void
     *
     * @throws \LogicException
     */
    public function offsetUnset($offset)
    {
        throw new LogicException('JSON path may not be mutated using array access.');
    }
}
