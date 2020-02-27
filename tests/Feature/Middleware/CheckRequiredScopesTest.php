<?php


namespace Seatplus\Auth\Tests\Feature\Middleware;


use Illuminate\Http\Request;
use Mockery;
use Seatplus\Auth\Http\Middleware\CheckRequiredScopes;
use Seatplus\Auth\Tests\TestCase;

class CheckRequiredScopesTest extends TestCase
{
    /**
     * @var \Mockery\Mock
     */
    private $middleware;

    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface
     */
    private $request;

    /**
     * @var \Closure
     */
    private $next;

    public function setUp(): void
    {

        parent::setUp(); // TODO: Change the autogenerated stub

        $this->middleware = Mockery::mock(CheckRequiredScopes::class)->makePartial();

        $this->mockRequest();

    }

    private function mockRequest() : void
    {
        $this->request = Mockery::mock(Request::class);

        $this->next = function ($request) {
            $request->forward();
        };
    }

    /** @test */
    public function it_lets_request_through_if_no_scopes_are_required()
    {
        $this->actingAs($this->test_user);

        //$this->middleware->shouldReceive('redirectTo')->once();
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);

    }


}
