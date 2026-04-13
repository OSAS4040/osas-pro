<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDocument extends Model
{
    protected $fillable = [
        'company_id', 'vehicle_id', 'uploaded_by', 'document_type', 'title',
        'file_path', 'file_name', 'file_size', 'expiry_date', 'alert_days_before',
        'alert_sent', 'notes',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'alert_sent'  => 'boolean',
    ];

    public function vehicle(): BelongsTo    { return $this->belongsTo(Vehicle::class); }
    public function uploader(): BelongsTo   { return $this->belongsTo(User::class, 'uploaded_by'); }
}
