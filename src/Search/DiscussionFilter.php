<?php

namespace Ernestdefoe\Buoy\Search;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * One discussion's bumps: filter[discussion]=<id>.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class DiscussionFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'discussion';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $state->getQuery()->where('buoy_bumps.discussion_id', $negate ? '!=' : '=', $this->asInt($value));
    }
}
