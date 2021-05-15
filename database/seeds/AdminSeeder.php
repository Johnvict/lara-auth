<?php

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
			"name"		=> "Super Admin",
			"phone"		=> "07084677075",
			"email"		=> "super.admin@johnvict.com",
			"password"	=> Hash::make('sup3r@dm!n'),
			"type"		=> "super"
		]);
    }
}
