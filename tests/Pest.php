<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Les tests Feature au format Pest utilisent le TestCase de Laravel.
| Les tests Unit restent purs : le moteur de calcul ne dépend pas du framework.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
