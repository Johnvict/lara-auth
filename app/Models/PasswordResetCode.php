<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
	protected $fillable = [
		"code",
		"user_id",
		"expire_at"
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
