<?php

namespace Ernestdefoe\Buoy\Search;

use Ernestdefoe\Buoy\Bump;
use Flarum\Discussion\Discussion;
use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The log lists bumps of discussions the reader can see: private discussions
 * and restricted tags they cannot see stay out.
 */
class BumpSearcher extends AbstractSearcher
{
    /** @return Builder<Bump> */
    public function getQuery(User $actor): Builder
    {
        return Bump::query()
            ->select('buoy_bumps.*')
            ->whereIn('buoy_bumps.discussion_id', Discussion::whereVisibleTo($actor)->select('discussions.id'));
    }
}
