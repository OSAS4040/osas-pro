<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasTenantScope
{
    protected static function bootHasTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (app()->has('tenant_company_id')) {
                $query->where($query->getModel()->getTable() . '.company_id', app('tenant_company_id'));
            }
        });

        static::creating(function (Model $model) {
            if (app()->has('tenant_company_id') && empty($model->company_id)) {
                $model->company_id = app('tenant_company_id');
            }
            if (app()->has('tenant_branch_id') && in_array('branch_id', $model->getFillable()) && empty($model->branch_id)) {
                $model->branch_id = app('tenant_branch_id');
            }
        });
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->withoutGlobalScope('tenant')
            ->where($this->getTable() . '.company_id', $companyId);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where($this->getTable() . '.branch_id', $branchId);
    }
}
