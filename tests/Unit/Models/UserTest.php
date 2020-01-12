<?php

namespace Seatplus\Auth\Tests\Unit\Models;

use Seatplus\Auth\Models\User;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class UserTest extends TestCase
{
    /** @test */
    public function it_has_owner_relationship()
    {
        $test_user = factory(User::class)->create();

        $this->assertInstanceOf(CharacterInfo::class, $test_user->main_character);
    }
}
