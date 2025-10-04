<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Jetstream\HasProfilePhoto;

class Client extends Authenticatable
{
    use HasFactory;
    use HasProfilePhoto;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'apellido',
        'correo',
        'nombre_fantasia',
        'password',
        'is_active',
        'profile_photo_path'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Automatically hash the password when setting it
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    /**
     * Scope for searching clients
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            $searchTerm = strtolower($search);

            if (strpos('activo', $searchTerm) !== false) {
                $query->where('is_active', 1);
            } elseif (strpos('inactivo', $searchTerm) !== false) {
                $query->where('is_active', 0);
            } else {
                $query->where(function (Builder $query) use ($searchTerm) {
                    $columns = ['nombre', 'apellido', 'correo', 'nombre_fantasia'];

                    foreach ($columns as $column) {
                        $query->orWhere($column, 'LIKE', "%{$searchTerm}%");
                    }
                });
            }
        }

        return $query->orderBy('updated_at', 'desc');
    }
}
