<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixClientPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:client-password';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix client password encryption';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = User::where('email', 'cliente@test.com')->first();
        
        if ($user) {
            $user->password = bcrypt('Cliente123!');
            $user->save();
            $this->info('Client password updated successfully!');
        } else {
            $this->error('Client not found!');
        }
    }
}