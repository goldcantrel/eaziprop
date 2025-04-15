<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\SupabaseService;

abstract class SupabaseModel extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    protected $connection = 'supabase';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Let Supabase handle UUID generation
            if (!$model->getKey()) {
                $model->setAttribute($model->getKeyName(), null);
            }
        });
    }

    /**
     * Get the Supabase service instance
     */
    protected function supabase()
    {
        return app(SupabaseService::class);
    }

    /**
     * Get the table associated with the model with session ID.
     */
    public function getTable()
    {
        return parent::getTable() . '_593nwd';
    }

    /**
     * Save the model to Supabase.
     */
    public function save(array $options = [])
    {
        $attributes = $this->getAttributes();

        if ($this->exists) {
            // Update
            $result = $this->supabase()->update(
                $this->getTable(),
                $attributes,
                ['id' => $this->getKey()]
            );
        } else {
            // Insert
            $result = $this->supabase()->insert(
                $this->getTable(),
                $attributes
            );
        }

        if (!empty($result)) {
            $this->setRawAttributes((array) $result[0], true);
            $this->exists = true;
            $this->wasRecentlyCreated = !$this->wasRecentlyCreated;
        }

        return $result;
    }

    /**
     * Delete the model from Supabase.
     */
    public function delete()
    {
        if ($this->exists) {
            $this->supabase()->delete(
                $this->getTable(),
                ['id' => $this->getKey()]
            );

            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * Get all records from Supabase.
     */
    public static function all($columns = ['*'])
    {
        $instance = new static;
        $results = $instance->supabase()->query($instance->getTable());

        return collect($results)->map(function ($attributes) {
            $model = new static;
            $model->setRawAttributes((array) $attributes, true);
            $model->exists = true;
            return $model;
        });
    }

    /**
     * Find a record by its primary key.
     */
    public static function find($id, $columns = ['*'])
    {
        $instance = new static;
        $results = $instance->supabase()->query(
            $instance->getTable(),
            ['id' => 'eq.' . $id]
        );

        if (empty($results)) {
            return null;
        }

        $model = new static;
        $model->setRawAttributes((array) $results[0], true);
        $model->exists = true;

        return $model;
    }

    /**
     * Get records by a where clause.
     */
    public static function where($column, $operator, $value = null)
    {
        $instance = new static;
        $results = $instance->supabase()->query(
            $instance->getTable(),
            [$column => $operator . '.' . $value]
        );

        return collect($results)->map(function ($attributes) {
            $model = new static;
            $model->setRawAttributes((array) $attributes, true);
            $model->exists = true;
            return $model;
        });
    }
}
