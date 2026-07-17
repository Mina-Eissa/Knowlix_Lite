<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToWorkspace
{
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $query) {
            if (auth()->check()) {
                $query->where(
                    $query->getModel()->getTable() . '.workspace_id',
                    auth()->user()->workspace_id
                );
            }
        });

        static::creating(function ($model) {
            if (auth()->check() && empty($model->workspace_id)) {
                $model->workspace_id = auth()->user()->workspace_id;
            }
        });
    }
}
