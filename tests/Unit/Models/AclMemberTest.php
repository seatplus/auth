<?php


namespace Seatplus\Auth\Tests\Unit\Models;


use Seatplus\Auth\Models\AccessControl\AclMember;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Tests\TestCase;

class AclMemberTest extends TestCase
{
    /**
     * @var \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    private $role;

    public function setUp(): void
    {

        parent::setUp();

        $this->role = Role::create(['name' => 'derp']);
    }

    /** @test */
    public function it_has_user_relationship()
    {
        $this->role->members()->create([
            'user_id' => $this->test_user->id,
            'status' => 'member'
        ]);

        $member = AclMember::where('user_id',$this->test_user->id)
            ->get()->first();

        $this->assertEquals(User::class, $member->user::class);

    }
}
