<?php

namespace App\Models;

use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'rut',
        'email',
        'password',
        'phone',
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
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function sendPasswordResetNotification($token, $type = null)
    {
        if ($type == 'create-user') {
            $this->notify(new CustomResetPasswordNotification($token));
        } else {
            $this->notify(new ResetPassword($token));
        }
    }

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
                    $columns = ['username', 'first_name', 'last_name', 'rut', 'email'];

                    foreach ($columns as $column) {
                        $query->orWhere($column, 'LIKE', "%{$searchTerm}%");
                    }

                    $query->orWhereHas('roles', function (Builder $subQuery) use ($searchTerm) {
                        $subQuery->where('name', 'LIKE', "%{$searchTerm}%");
                    });

                });

            }
        }

        return $query->orderBy('updated_at', 'desc');
    }

}
