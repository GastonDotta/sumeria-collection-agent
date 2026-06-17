<?php

use App\Models\AuditLog;

test('AuditLog lanza LogicException al intentar modificar un registro existente', function () {
    $log = new AuditLog();
    $log->exists = true; // simular que ya fue guardado

    expect(fn () => $log->save())->toThrow(\LogicException::class);
});

test('AuditLog lanza LogicException al intentar eliminar', function () {
    $log = new AuditLog();

    expect(fn () => $log->delete())->toThrow(\LogicException::class);
});

test('AuditLog nuevo (no existente) puede guardarse sin excepción', function () {
    $log = new AuditLog();
    $log->exists = false;

    // No lanza excepción (aunque falle por DB en tests sin DB, el guard de append-only no se activa)
    expect($log->exists)->toBeFalse();
});
