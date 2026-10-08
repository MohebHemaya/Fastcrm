<?php

test('tests run on a database ending with _test', function () {
    $dbName = config('database.connections.'.config('database.default').'.database');

    expect($dbName)->toEndWith('_test')
        ->and(str_ends_with($dbName, '_test'))->toBeTrue();
});
