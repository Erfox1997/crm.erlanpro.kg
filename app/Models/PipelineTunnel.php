<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineTunnel extends Model
{
    protected $fillable = [
        'company_id',
        'from_pipeline_id',
        'to_pipeline_id',
        'name',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fromPipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'from_pipeline_id');
    }

    public function toPipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'to_pipeline_id');
    }
}
