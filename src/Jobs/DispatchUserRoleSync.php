<?php


namespace Seatplus\Auth\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Jobs\Middleware\RateLimitedJobMiddleware;
use Seatplus\Eveapi\Jobs\Middleware\RedisFunnelMiddleware;

class DispatchUserRoleSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {

        return [
            new RedisFunnelMiddleware
        ];
    }

    /**
     * Assign this job a tag so that Horizon can categorize and allow
     * for specific tags to be monitored.
     *
     * If a job specifies the tags property, that is added.
     *
     * @return array
     */
    public function tags()
    {
        return [
            'Dispatch Role Updates',
        ];
    }

    public function handle()
    {
        foreach (User::cursor() as $user) {
            UserRolesSync::dispatch($user)->onQueue('high');
        }
    }

}
