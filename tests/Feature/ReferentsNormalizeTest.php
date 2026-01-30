<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('normalizes referents on a set of few but specific edge cases', function () {
    $teamId = 1;
    $otherTeamId = 2;
    $email = 'a@example.com';

    // Create two duplicate referents (same email + team_id), keep MAX(id) (most recent)
    $oldId = DB::table('referents')->insertGetId([
        'email' => $email,
        'team_id' => $teamId,
        'name' => 'Mario',
        'last_name' => 'Rossi',
        'phone' => '1234567890',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $keepId = DB::table('referents')->insertGetId([
        'email' => $email,
        'team_id' => $teamId,
        'name' => 'Mario',
        'last_name' => 'Rossi',
        'phone' => '1234567890',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $diffTeamId = DB::table('referents')->insertGetId([
        'email' => $email,
        'team_id' => $otherTeamId,
        'name' => 'Mario',
        'last_name' => 'Rossi',
        'phone' => '1234567890',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherId = DB::table('referents')->insertGetId([
        'email' => 'b@example.com',
        'team_id' => $teamId,
        'name' => 'Other',
        'last_name' => 'Referent',
        'phone' => '1234567890',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create shipment rows referencing both old, keep (with different scopes) and diffTeam
    DB::table('referent_shipment')->insert([
        'referent_id' => $oldId,
        'shipment_id' => 1001,
        'scope' => 'start',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    //This one should become a duplicate therefore it should be eliminated
    DB::table('referent_shipment')->insert([
        'referent_id' => $oldId,
        'shipment_id' => 1002,
        'scope' => 'start',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('referent_shipment')->insert([
        'referent_id' => $keepId,
        'shipment_id' => 1002,
        'scope' => 'start',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('referent_shipment')->insert([
        'referent_id' => $oldId,
        'shipment_id' => 1002,
        'scope' => 'end',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('referent_shipment')->insert([
        'referent_id' => $diffTeamId,
        'shipment_id' => 1003,
        'scope' => 'end',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $exitCode = Artisan::call('referents:normalize');
    expect($exitCode)->toBe(0);

    $hasDupes = DB::table('referents')
        ->select('email', 'team_id', DB::raw('COUNT(*) as c'))
        ->groupBy('email', 'team_id')
        ->having('c', '>', 1)
        ->exists();

    expect($hasDupes)->toBeFalse()
        ->and(DB::table('referents')->where('id', $oldId)->exists())->toBeFalse()
        ->and(DB::table('referents')->where('id', $keepId)->exists())->toBeTrue()
        ->and(DB::table('referents')->where('id', $diffTeamId)->exists())->toBeTrue()
        ->and(DB::table('referents')->where('id', $otherId)->exists())->toBeTrue()
        ->and(DB::table('referent_shipment')->where('referent_id', $oldId)->count())->toBe(0)
        ->and(DB::table('referent_shipment')->where('referent_id', $keepId)->count())->ToBe(3)
        ->and(DB::table('referent_shipment')->where('referent_id', $diffTeamId)->count())->ToBe(1);

    $broken = DB::table('referent_shipment as rs')
        ->leftJoin('referents as r', 'r.id', '=', 'rs.referent_id')
        ->whereNull('r.id')
        ->count();

    expect($broken)->toBe(0);
});

it('normalizes referents on the provided seeder', function () {
    Artisan::call('db:seed');

    $expectedNumberOfReferents = DB::query()->from(DB::table('referents')
        ->select('email', 'team_id')
        ->groupBy('email', 'team_id'))->count();

    $exitCode = Artisan::call('referents:normalize');
    expect($exitCode)->toBe(0);

    $hasDupes = DB::table('referents')
        ->select('email', 'team_id', DB::raw('COUNT(*) as c'))
        ->groupBy('email', 'team_id')
        ->having('c', '>', 1)
        ->exists();

    expect($hasDupes)->toBeFalse()
        ->and(DB::table('referents')->count())->toBe($expectedNumberOfReferents);
});
