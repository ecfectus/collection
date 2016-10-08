<?php
namespace Ecfectus\Collection;

use ArrayAccess;
use IteratorAggregate;
use Countable;
use JsonSerializable;
use Traversable;
use Ecfectus\Dotable\DotableTrait;

/**
 * Class Collection
 * @package Ecfectus
 */
class Collection implements CollectionInterface, ArrayAccess, IteratorAggregate, Countable, JsonSerializable
{
    use DotableTrait;

    public function __construct(array $items = [])
    {
        $this->set(null, $items);
    }

    /**
     * Get the average value of a given key.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function avg($callback = null)
    {
        if ($count = $this->count()) {
            return $this->sum($callback) / $count;
        }
    }

    /**
     * Alias for the "avg" method.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function average($callback = null)
    {
        return $this->avg($callback);
    }

    /**
     * Get the median of a given key.
     *
     * @param  null $key
     * @return mixed|null
     */
    public function median($key = null)
    {
        $count = $this->count();
        if ($count == 0) {
            return;
        }
        $values = isset($key) ? $this->pluck($key) : $this;
        $values->sort()->values();
        $middle = (int) ($count / 2);
        if ($count % 2) {
            return $values->get($middle);
        }
        return (new static([
            $values->get($middle - 1), $values->get($middle),
        ]))->average();
    }

    /**
     * Get the mode of a given key.
     *
     * @param  null $key
     * @return array
     */
    public function mode($key = null)
    {
        $count = $this->count();
        if ($count == 0) {
            return;
        }
        $collection = isset($key) ? $this->pluck($key) : $this;
        $counts = new self;
        $collection->each(function ($value) use ($counts) {
            $counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1;
        });
        $sorted = $counts->sort();
        $highestValue = $sorted->last();
        return $sorted->filter(function ($value) use ($highestValue) {
            return $value == $highestValue;
        })->sort()->keys()->get();
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return Ecfectus\Collection\Collection
     */
    public function collapse() : Collection
    {
        $results = [];
        $array = $this->get();
        foreach ($array as $values) {
            if ($values instanceof Collection) {
                $values = $values->get();
            } elseif (! is_array($values)) {
                continue;
            }
            $results = array_merge($results, $values);
        }
        return new static($results);
    }

    /**
     * Determine if an item exists in the collection.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return bool
     */
    public function contains($key = '', $value = null) : bool
    {
        if (func_num_args() == 2) {
            return $this->contains(function ($item) use ($key, $value) {
                if(is_array($item)){
                    return (new static($item))->get($key) == $value;
                }else{
                    return $item == $value;
                }
            });
        }
        if ($this->useAsCallable($key)) {
            return ! is_null($this->first($key));
        }
        return in_array($key, $this->get());
    }

    /**
     * Determine if an item exists in the collection using strict comparison.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return bool
     */
    public function containsStrict($key = '', $value = null) : bool
    {
        if (func_num_args() == 2) {
            return $this->contains(function ($item) use ($key, $value) {
                if(is_array($item)){
                    return (new static($item))->get($key) === $value;
                }else{
                    return $item === $value;
                }
            });
        }
        if ($this->useAsCallable($key)) {
            return ! is_null($this->first($key));
        }
        return in_array($key, $this->get(), true);
    }

    /**
     * Get the items in the collection that are not present in the given items.
     *
     * @param  mixed  $items
     * @return \Ecfectus\Collection\Collection
     */
    public function diff($items) : Collection
    {
        return new static(array_diff($this->get(), $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @param  mixed  $items
     * @return \Ecfectus\Collection\Collection
     */
    public function diffKeys($items) : Collection
    {
        return new static(array_diff_key($this->get(), $this->getArrayableItems($items)));
    }

    /**
     * Execute a callback over each item.
     *
     * @param  callable  $callback
     * @return \Ecfectus\Collection\Collection
     */
    public function each(callable $callback) : Collection
    {
        foreach ($this->get() as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }

    /**
     * Create a new collection consisting of every n-th element.
     *
     * @param  int  $step
     * @param  int  $offset
     * @return \Ecfectus\Collection\Collection
     */
    public function every($step, $offset = 0) : Collection
    {
        $new = [];
        $position = 0;
        foreach ($this->get() as $item) {
            if ($position % $step === $offset) {
                $new[] = $item;
            }
            $position++;
        }
        return new static($new);
    }

    /**
     * Get all items except for those with the specified keys.
     *
     * @param  array  $keys
     * @return \Ecfectus\Collection\Collection
     */
    public function except(array $keys = []) : Collection
    {
        $collection = new static($this->get());
        foreach($keys as $key){
            $collection->forget($key);
        }
        return $collection;
    }

    /**
     * Run a filter over each of the items.
     *
     * @param  callable|null  $callback
     * @return \Ecfectus\Collection\Collection
     */
    public function filter(callable $callback = null) : Collection
    {
        if ($callback) {
            return new static(array_filter($this->get(), $callback, ARRAY_FILTER_USE_BOTH));
        }
        return new static(array_filter($this->get()));
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return \Ecfectus\Collection\Collection
     */
    public function where(string $key = '', $operator = '=', $value = null) : Collection
    {
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->filter($this->operatorForWhere($key, $operator, $value));
    }
    /**
     * Get an operator checker callback.
     *
     * @param  string  $key
     * @param  string  $operator
     * @param  mixed  $value
     * @return \Closure
     */
    protected function operatorForWhere(string $key = '', string $operator = '=', $value) : \Closure
    {
        return function ($item) use ($key, $operator, $value) {
            $retrieved = (is_array($item)) ? (new static($item))->get($key) : $item;
            switch ($operator) {
                default:
                case '=':
                case '==':  return $retrieved == $value;
                case '!=':
                case '<>':  return $retrieved != $value;
                case '<':   return $retrieved < $value;
                case '>':   return $retrieved > $value;
                case '<=':  return $retrieved <= $value;
                case '>=':  return $retrieved >= $value;
                case '===': return $retrieved === $value;
                case '!==': return $retrieved !== $value;
            }
        };
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return \Ecfectus\Collection\Collection
     */
    public function whereStrict(string $key = '', $value) : Collection
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  mixed  $values
     * @param  bool  $strict
     * @return \Ecfectus\Collection\Collection
     */
    public function whereIn(string $key = '', $values, $strict = false) : Collection
    {
        $values = $this->getArrayableItems($values);
        return $this->filter(function ($item) use ($key, $values, $strict) {
            $retrieved = (is_array($item)) ? (new static($item))->get($key) : $item;
            return in_array($retrieved, $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string  $key
     * @param  mixed  $values
     * @return \Ecfectus\Collection\Collection
     */
    public function whereInStrict(string $key = '', $values) : Collection
    {
        return $this->whereIn($key, $values, true);
    }

    /**
     * Get the first item from the collection.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        $array = $this->get();
        if (is_null($callback)) {
            if (empty($array)) {
                return $default;
            }
            foreach ($array as $item) {
                return $item;
            }
        }
        foreach ($array as $key => $value) {
            if (call_user_func($callback, $value, $key)) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param  int  $depth
     * @return \Ecfectus\Collection\Collection
     */
    public function flatten(int $depth = PHP_INT_MAX) : Collection
    {
        return new static(array_reduce($this->get(), function ($result, $item) use ($depth) {
            $item = $item instanceof Collection ? $item->get() : $item;
            if (! is_array($item)) {
                return array_merge($result, [$item]);
            } elseif ($depth === 1) {
                return array_merge($result, array_values($item));
            } else {
                return array_merge($result, (new static($item))->flatten($depth - 1)->get());
            }
        }, []));
    }

    /**
     * Flip the items in the collection.
     *
     * @return static
     */
    public function flip()
    {
        return new static(array_flip($this->get()));
    }

    /**
     * Group an associative array by a field or using a callback.
     *
     * @param  callable|string  $groupBy
     * @param  bool  $preserveKeys
     * @return \Ecfectus\Collection\Collection
     */
    public function groupBy($groupBy, $preserveKeys = false) : Collection
    {
        $groupBy = $this->valueRetriever($groupBy);
        $results = [];
        $array = $this->get();
        foreach ($array as $key => $value) {
            $groupKeys = $groupBy($value, $key);
            if (! is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }
            foreach ($groupKeys as $groupKey) {
                if (! array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static;
                }
                $results[$groupKey]->offsetSet($preserveKeys ? $key : $results[$groupKey]->count(), $value);
            }
        }
        return new static($results);
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param  callable|string  $keyBy
     * @return \Ecfectus\Collection\Collection
     */
    public function keyBy($keyBy) : Collection
    {
        $keyBy = $this->valueRetriever($keyBy);
        $results = [];
        foreach ($this->get() as $key => $item) {
            $results[$keyBy($item, $key)] = $item;
        }
        return new static($results);
    }

    /**
     * Concatenate values of a given key as a string.
     *
     * @param  string  $value
     * @param  string  $glue
     * @return string
     */
    public function implode($value, $glue = null) : string
    {
        $first = $this->first();
        if (is_array($first) || is_object($first)) {
            return implode($glue, $this->pluck($value)->get());
        }
        return implode($value, $this->get());
    }

    /**
     * Intersect the collection with the given items.
     *
     * @param  mixed  $items
     * @return \Ecfectus\Collection\Collection
     */
    public function intersect($items) : Collection
    {
        return new static(array_intersect($this->get(), $this->getArrayableItems($items)));
    }

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty() : bool
    {
        return empty($this->get());
    }

    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function useAsCallable($value) : bool
    {
        return ! is_string($value) && is_callable($value);
    }

    /**
     * Get the keys of the collection items.
     *
     * @return \Ecfectus\Collection\Collection
     */
    public function keys() : Collection
    {
        return new static(array_keys($this->get()));
    }

    /**
     * Get the last item from the collection.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function last(callable $callback = null, $default = null)
    {
        $array = $this->get();
        if (is_null($callback)) {
            return empty($array) ? $default : end($array);
        }

        return (new static(array_reverse($array, true)))->first($callback, $default);
    }

    /**
     * Get the values of a given key.
     *
     * @param  string  $value
     * @param  string|null  $key
     * @return \Ecfectus\Collection\Collection
     */
    public function pluck($value = '', $key = null) : Collection
    {
        $array = $this->get();
        $results = [];

        //$value = is_string($value) ? explode('.', $value) : $value;
        //$key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        foreach ($array as $item) {
            $itemValue = (new static($this->getArrayableItems($item)))->get($value);
            // If the key is "null", we will just append the value to the array and keep
            // looping. Otherwise we will key the array using the value of the key we
            // received from the developer. Then we'll return the final array form.
            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = (new static($this->getArrayableItems($item)))->get($key);
                $results[$itemKey] = $itemValue;
            }
        }
        return new static($results);
    }

    /**
     * Run a map over each of the items.
     *
     * @param  callable  $callback
     * @return \Ecfectus\Collection\Collection
     */
    public function map(callable $callback) : Collection
    {
        $array = $this->get();
        $keys = array_keys($array);
        $items = array_map($callback, $array, $keys);
        return new static(array_combine($keys, $items));
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param  callable  $callback
     * @return \Ecfectus\Collection\Collection
     */
    public function mapWithKeys(callable $callback) : Collection
    {
        return $this->flatMap($callback);
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @param  callable  $callback
     * @return \Ecfectus\Collection\Collection
     */
    public function flatMap(callable $callback) : Collection
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Get the max value of a given key.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function max($callback = null)
    {
        $callback = $this->valueRetriever($callback);
        return $this->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);
            $value = is_array($value) ? $value[0] : $value;
            return is_null($result) || $value > $result ? $value : $result;
        });
    }

    /**
     * Create a collection by using this collection for keys and another for its values.
     *
     * @param  mixed  $values
     * @return \Ecfectus\Collection\Collection
     */
    public function combine($values) : Collection
    {
        return new static(array_combine($this->get(), $this->getArrayableItems($values)));
    }

    /**
     * Union the collection with the given items.
     *
     * @param  mixed  $items
     * @return \Ecfectus\Collection\Collection
     */
    public function union($items) : Collection
    {
        return new static($this->get() + $this->getArrayableItems($items));
    }

    /**
     * Get the min value of a given key.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function min($callback = null)
    {
        $callback = $this->valueRetriever($callback);
        return $this->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);
            $value = (is_array($value)) ? $value[0] : $value;
            return is_null($result) || $value < $result ? $value : $result;
        });
    }

    /**
     * Get the items with the specified keys.
     *
     * @param  mixed  $keys
     * @return \Ecfectus\Collection\Collection
     */
    public function only(array $keys = []) : Collection
    {
        if (is_null($keys)) {
            return new static($this->get());
        }
        return new static(array_intersect_key($this->get(), array_flip((array) $keys)));
    }

    /**
     * "Paginate" the collection by slicing it into a smaller collection.
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return \Ecfectus\Collection\Collection
     */
    public function forPage(int $page, int $perPage) : Collection
    {
        return $this->slice(($page - 1) * $perPage, $perPage);
    }

    /**
     * Pass the collection to the given callback and return the result.
     *
     * @param  callable $callback
     * @return mixed
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
    }

    /**
     * Get and remove the last item from the collection.
     *
     * @return mixed
     */
    public function pop()
    {
        $items = $this->get();
        $item = array_pop($items);
        $this->set('', $items);
        return $item;
    }

    /**
     * Get and remove an item from the collection.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function pull($key = null, $default = null)
    {
        $item = $this->get($key);
        $this->forget($key);
        return $item ?? $default;
    }

    /**
     * Put an item in the collection by key.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return \Ecfectus\Collection\Collection
     */
    public function put($key = null, $value) : Collection
    {
        $this->offsetSet($key, $value);
        return $this;
    }

    /**
     * Get one or more items randomly from the collection.
     *
     * @param  int  $amount
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function random(int $amount = 1)
    {
        if ($amount > ($count = $this->count())) {
            throw new \InvalidArgumentException("You requested {$amount} items, but there are only {$count} items in the collection");
        }
        $keys = array_rand($this->get(), $amount);
        if ($amount == 1) {
            return $this->get($keys);
        }
        return new static(array_intersect_key($this->get(), array_flip($keys)));
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param  callable  $callback
     * @param  mixed     $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->get(), $callback, $initial);
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param  callable|mixed  $callback
     * @return \Ecfectus\Collection\Collection
     */
    public function reject($callback) : Collection
    {
        if ($this->useAsCallable($callback)) {
            return $this->filter(function ($value, $key) use ($callback) {
                return ! $callback($value, $key);
            });
        }
        return $this->filter(function ($item) use ($callback) {
            return $item != $callback;
        });
    }

    /**
     * Reverse items order.
     *
     * @return static
     */
    public function reverse()
    {
        return new static(array_reverse($this->get(), true));
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     *
     * @param  mixed  $value
     * @param  bool   $strict
     * @return mixed
     */
    public function search($value, $strict = false)
    {
        if (! $this->useAsCallable($value)) {
            return array_search($value, $this->get(), $strict);
        }
        foreach ($this->get() as $key => $item) {
            if (call_user_func($value, $item, $key)) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Get and remove the first item from the collection.
     *
     * @return mixed
     */
    public function shift()
    {
        $array = $this->get();
        $item = array_shift($array);
        $this->set('', $array);
        return $item;
    }

    /**
     * Shuffle the items in the collection.
     *
     * @param int $seed
     * @return \Ecfectus\Collection\Collection
     */
    public function shuffle($seed = null) : Collection
    {
        $items = $this->get();
        if (is_null($seed)) {
            shuffle($items);
        } else {
            srand($seed);
            usort($items, function () {
                return rand(-1, 1);
            });
        }
        return new static($items);
    }

    /**
     * Slice the underlying collection array.
     *
     * @param  int   $offset
     * @param  int   $length
     * @return \Ecfectus\Collection\Collection
     */
    public function slice(int $offset, int $length = null) : Collection
    {
        return new static(array_slice($this->get(), $offset, $length, true));
    }

    /**
     * Split a collection into a certain number of groups.
     *
     * @param  int  $numberOfGroups
     * @return \Ecfectus\Collection\Collection
     */
    public function split(int $numberOfGroups) : Collection
    {
        if ($this->isEmpty()) {
            return new static;
        }
        $groupSize = ceil($this->count() / $numberOfGroups);
        return $this->chunk($groupSize);
    }

    /**
     * Chunk the underlying collection array.
     *
     * @param  int   $size
     * @return \Ecfectus\Collection\Collection
     */
    public function chunk(int $size) : Collection
    {
        $chunks = [];
        foreach (array_chunk($this->get(), $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }
        return new static($chunks);
    }

    /**
     * Sort through each item with a callback.
     *
     * @param  callable|null  $callback
     * @return \Ecfectus\Collection\Collection
     */
    public function sort(callable $callback = null) : Collection
    {
        $items = $this->get();
        $callback
            ? uasort($items, $callback)
            : asort($items);
        return new static($items);
    }

    /**
     * Sort the collection using the given callback.
     *
     * @param  callable|string  $callback
     * @param  int   $options
     * @param  bool  $descending
     * @return \Ecfectus\Collection\Collection
     */
    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false) : Collection
    {
        $items = $this->get();
        $results = [];
        $callback = $this->valueRetriever($callback);
        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }
        $descending ? arsort($results, $options)
            : asort($results, $options);
        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        $keys = array_keys($results);
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $items[$key];
        }
        return new static($results);
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param  callable|string  $callback
     * @param  int  $options
     * @return \Ecfectus\Collection\Collection
     */
    public function sortByDesc($callback, int $options = SORT_REGULAR) : Collection
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Splice a portion of the underlying collection array.
     *
     * @param  int  $offset
     * @param  int|null  $length
     * @param  mixed  $replacement
     * @return \Ecfectus\Collection\Collection
     */
    public function splice(int $offset, int $length = null, $replacement = []) : Collection
    {
        $array = $this->get();
        if (func_num_args() == 1) {
            $result = array_splice($array, $offset);
        }else{
            $result = array_splice($array, $offset, $length, $replacement);
        }
        $this->set(null, $array);
        return new static($result);
    }

    /**
     * Get the sum of the given values.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function sum($callback = null)
    {
        if (is_null($callback)) {
            return array_sum($this->get());
        }
        $callback = $this->valueRetriever($callback);
        return $this->reduce(function ($result, $item) use ($callback) {
            return $result + $callback($item);
        }, 0);
    }

    /**
     * Take the first or last {$limit} items.
     *
     * @param  int  $limit
     * @return \Ecfectus\Collection\Collection
     */
    public function take(int $limit) : Collection
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }
        return $this->slice(0, $limit);
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @param  callable  $callback
     * @return \Ecfectus\Collection\Collection
     */
    public function transform(callable $callback) : Collection
    {
        $this->set('', $this->map($callback)->get());
        return $this;
    }

    /**
     * Return only unique items from the collection array.
     *
     * @param  string|callable|null  $key
     * @param  bool  $strict
     *
     * @return \Ecfectus\Collection\Collection
     */
    public function unique($key = null, bool $strict = false) : Collection
    {
        if (is_null($key)) {
            return new static(array_unique($this->get(), SORT_REGULAR));
        }
        $key = $this->valueRetriever($key);
        $exists = [];
        return $this->reject(function ($item) use ($key, $strict, &$exists) {
            if (in_array($id = $key($item), $exists, $strict)) {
                return true;
            }
            $exists[] = $id;
        });
    }

    /**
     * Return only unique items from the collection array using strict comparison.
     *
     * @param  string|callable|null  $key
     * @return \Ecfectus\Collection\Collection
     */
    public function uniqueStrict($key = null) : Collection
    {
        return $this->unique($key, true);
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return \Ecfectus\Collection\Collection
     */
    public function values() : Collection
    {
        return new static(array_values($this->get()));
    }

    /**
     * Get a value retrieving callback.
     *
     * @param  string  $value
     * @return callable
     */
    protected function valueRetriever($value) : callable
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }
        return function ($item) use ($value) {
            return (new static($this->getArrayableItems($item)))->get($value ?? null);
        };
    }

    /**
     * Zip the collection together with one or more arrays.
     *
     * e.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]]
     *
     * @param  mixed ...$items
     * @return \Ecfectus\Collection\Collection
     */
    public function zip($items) : Collection
    {
        $arrayableItems = array_map(function ($items) {
            return $this->getArrayableItems($items);
        }, func_get_args());
        $params = array_merge([function () {
            return new static(func_get_args());
        }, $this->get()], $arrayableItems);
        return new static(call_user_func_array('array_map', $params));
    }

    /**
     * Get a base Support collection instance from this collection.
     *
     * @return \Ecfectus\Collection\Collection
     */
    public function toBase() : Collection
    {
        return new self($this->get());
    }

    /**
     * Results array of items from Collection or Arrayable.
     *
     * @param  mixed  $items
     * @return array
     */
    protected function getArrayableItems($items) : array
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof static) {
            return $items->get();
        } elseif (is_object($items) && is_callable([$items, 'toArray'])) {
            return $items->toArray();
        } elseif ($items instanceof JsonSerializable) {
            return $items->jsonSerialize();
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        }
        return (array) $items;
    }
}
