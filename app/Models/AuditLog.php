<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // Tabla append-only: sin updated_at, sin soft deletes
    public const UPDATED_AT = null;

    protected $table = 'audit_log';

    protected $fillable = [
        'collection_case_id',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    // Prevenir UPDATE y DELETE a nivel de modelo
    public function save(array $options = []): bool
    {
        if (! $this->exists) {
            return parent::save($options);
        }
        throw new \LogicException('AuditLog es append-only: no se permite modificar registros existentes.');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('AuditLog es append-only: no se permite eliminar registros.');
    }

    public function collectionCase(): BelongsTo
    {
        return $this->belongsTo(CollectionCase::class);
    }
}
