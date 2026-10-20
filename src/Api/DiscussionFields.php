<?php

namespace Ernestdefoe\Buoy\Api;

use Carbon\Carbon;
use Ernestdefoe\Buoy\Bump;
use Ernestdefoe\Buoy\Bumper;
use Ernestdefoe\Buoy\Schedule;
use Flarum\Api\Context;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use WeakMap;

/**
 * Buoy's fields on discussions.
 *
 * Lists get only `buoyBumpedAt`, a column, so they cost nothing. The rest
 * needs a policy check and a query or two, so it is only worked out for the
 * one discussion a discussion page shows (or a bump/plan just saved).
 */
class DiscussionFields
{
    /** @var WeakMap<Discussion, array<string, mixed>> */
    private WeakMap $memo;

    public function __construct(private Bumper $bumper, private TranslatorInterface $translator)
    {
        $this->memo = new WeakMap();
    }

    /** @return array<\Tobyz\JsonApiServer\Schema\Field\Field> */
    public function __invoke(): array
    {
        $single = fn (Discussion $d, Context $c) => $c->showing(DiscussionResource::class) || $c->updating(DiscussionResource::class);

        return [
            Schema\DateTime::make('buoyBumpedAt')
                ->get(fn (Discussion $d) => $d->buoy_bumped_at),

            Schema\Boolean::make('buoyCanBump')
                ->visible($single)
                ->get(fn (Discussion $d, Context $c) => $this->state($d, $c)['canBump']),

            // When the actor may bump it next, if not now: the cooldown since
            // its last activity, or the daily limit.
            Schema\DateTime::make('buoyNextBumpAt')
                ->nullable()
                ->visible($single)
                ->get(fn (Discussion $d, Context $c) => $this->state($d, $c)['status']['nextAt'] ?? null),

            // Bumps the actor has left in the current 24 hours; null: no limit.
            Schema\Integer::make('buoyBumpsLeft')
                ->nullable()
                ->visible($single)
                ->get(fn (Discussion $d, Context $c) => $this->state($d, $c)['status']['left'] ?? null),

            // The cooldown in hours for the actor; the planner keeps repeats at least this far apart.
            Schema\Number::make('buoyCooldown')
                ->visible($single)
                ->get(fn (Discussion $d, Context $c) => $this->state($d, $c)['status']['cooldown'] ?? 0),

            Schema\Boolean::make('buoyCanSchedule')
                ->visible($single)
                ->get(fn (Discussion $d, Context $c) => $this->state($d, $c)['canSchedule']),

            Schema\Boolean::make('buoyCanViewLog')
                ->visible($single)
                ->get(fn (Discussion $d, Context $c) => $c->getActor()->can('buoyBumpAny', $d)),

            Schema\Boolean::make('buoyBump')
                ->hidden()
                ->writable(fn (Discussion $d, Context $c) => $c->updating() && $c->getActor()->can('buoyBump', $d))
                ->set(fn () => null)
                ->save(function (Discussion $d, mixed $value, Context $c) {
                    if ($value) {
                        $this->bumper->bump($c->getActor(), $d, Bump::MANUAL);
                        unset($this->memo[$d]);
                    }
                }),

            // The plan, for those who may make one: {at, every, times}, or null.
            Schema\Arr::make('buoySchedule')
                ->nullable()
                ->visible(fn (Discussion $d, Context $c) => $single($d, $c) && $this->state($d, $c)['canSchedule'])
                ->get(fn (Discussion $d) => $this->plan($d))
                ->writable(fn (Discussion $d, Context $c) => $c->updating() && $c->getActor()->can('buoySchedule', $d))
                ->set(fn () => null)
                ->save(function (Discussion $d, mixed $value, Context $c) {
                    $this->savePlan($d, $value, $c);
                    unset($this->memo[$d]);
                }),
        ];
    }

    /** @return array{canBump: bool, canSchedule: bool, status: array<string, mixed>|null} */
    private function state(Discussion $d, Context $c): array
    {
        if (isset($this->memo[$d])) {
            return $this->memo[$d];
        }

        $actor = $c->getActor();
        $canBump = $actor->can('buoyBump', $d);

        return $this->memo[$d] = [
            'canBump' => $canBump,
            'canSchedule' => $canBump && $actor->can('buoySchedule', $d),
            'status' => $canBump ? $this->bumper->status($actor, $d) : null,
        ];
    }

    /** @return array{at: string, every: int|null, times: int|null}|null */
    private function plan(Discussion $d): ?array
    {
        $schedule = Schedule::query()->where('discussion_id', $d->id)->first();

        return $schedule ? [
            'at' => $schedule->next_at->toIso8601String(),
            'every' => $schedule->interval_hours,
            'times' => $schedule->remaining,
        ] : null;
    }

    private function savePlan(Discussion $d, mixed $value, Context $c): void
    {
        if ($value === null) {
            Schedule::query()->where('discussion_id', $d->id)->delete();

            return;
        }

        $actor = $c->getActor();
        $value = is_array($value) ? $value : [];
        $errors = [];

        try {
            $at = Carbon::parse((string) ($value['at'] ?? ''))->utc()->startOfSecond();
        } catch (\Throwable) {
            $at = null;
        }
        if (! $at || empty($value['at']) || $at->lt(Carbon::now()->subMinutes(5)) || $at->gt(Carbon::now()->addYear())) {
            $errors['at'] = $this->translator->trans('ernestdefoe-buoy.api.plan_at');
        }

        $every = $value['every'] ?? null;
        if ($every !== null && (! is_int($every) || $every < 1 || $every > 8760)) {
            $errors['every'] = $this->translator->trans('ernestdefoe-buoy.api.plan_every');
            $every = null;
        }

        $times = $every === null ? null : ($value['times'] ?? null);
        if ($times !== null && (! is_int($times) || $times < 1 || $times > 999)) {
            $errors['times'] = $this->translator->trans('ernestdefoe-buoy.api.plan_times');
        }

        // Members' planned bumps keep to their own limits, so a plan that
        // would always land inside the cooldown is refused up front.
        $status = $this->bumper->status($actor, $d);
        $cooldown = (int) ceil($status['cooldown']);
        if (! $status['bypass'] && $every !== null && $every < $cooldown) {
            $errors['every'] = $this->translator->trans('ernestdefoe-buoy.api.plan_every_cooldown', ['hours' => $cooldown]);
        }
        if (! $status['bypass'] && $at && $d->last_posted_at && $status['cooldown'] > 0
            && $at->lt($d->last_posted_at->copy()->addMinutes((int) ceil($status['cooldown'] * 60)))) {
            $errors['at'] = $this->translator->trans('ernestdefoe-buoy.api.plan_at_cooldown');
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $schedule = Schedule::query()->where('discussion_id', $d->id)->first() ?? new Schedule();
        $schedule->discussion_id = $d->id;
        $schedule->user_id = $actor->id;
        $schedule->next_at = $at;
        $schedule->interval_hours = $every;
        $schedule->remaining = $times;
        $schedule->save();
    }
}
