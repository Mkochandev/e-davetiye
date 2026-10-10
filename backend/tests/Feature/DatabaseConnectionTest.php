<?php

use Illuminate\Support\Facades\DB;

it('uses the postgres test database', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('zarf_test');
});
