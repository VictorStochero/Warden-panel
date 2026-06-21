<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panel:make-admin {email} {--name=} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user or promote an existing one to admin';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->is_admin = true;

            if ($name = $this->option('name')) {
                $user->name = $name;
            }

            if ($password = $this->option('password')) {
                $user->password = Hash::make($password);
            }

            $user->save();

            $this->info("Promoted existing user {$email} to admin.");

            return self::SUCCESS;
        }

        $password = $this->option('password') ?: Str::password(20);

        $user = User::create([
            'name' => $this->option('name') ?: $email,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->info("Created admin user {$email}.");

        if (! $this->option('password')) {
            $this->line("Generated password: {$password}");
        }

        return self::SUCCESS;
    }
}
