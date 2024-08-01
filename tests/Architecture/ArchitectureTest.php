<?php

arch('debugs are removed')
    ->expect(['dd', 'dump'])
    ->not->toBeUsed();
