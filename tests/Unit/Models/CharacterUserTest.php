<?php

it('has character', function () {
    $test_user = $this->test_user;

    $character_user = $test_user->characterUsers->first();

    expect($character_user->character->character_id)->toBe($this->test_character->character_id);
});
