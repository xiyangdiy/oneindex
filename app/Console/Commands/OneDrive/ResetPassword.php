<?php

namespace App\Console\Commands\OneDrive;

use App\Helpers\Tool;
use Illuminate\Console\Command;

class ResetPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'od:password';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset Password';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        $password = str_random(8);
        Tool::updateConfig(['password' => md5($password)]);
        $this->info("New Password:[ {$password} ]");
    }
}
