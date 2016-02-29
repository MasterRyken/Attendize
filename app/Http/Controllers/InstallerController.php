<?php namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Timezone;
use View;
use Response;
use Config;
use Input;
use Redirect;
use Artisan;
use DB;
use File;
use App\Http\Controllers\Controller;


class InstallerController extends Controller
{

    public function __construct()
    {
        set_time_limit(300);
    }

    public function showInstaller()
    {
        $data['paths'] = [
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
            public_path('user_content'),
            base_path('.env')
        ];
        $data['requirements'] = [
            'openssl',
            'pdo',
            'mbstring',
            'fileinfo',
            'tokenizer',
        ];

        return View::make('Installer.Installer', $data);
    }

    public function postInstaller()
    {

        $database['type'] = 'mysql';
        $database['host'] = Input::get('database_host');
        $database['name'] = Input::get('database_name');
        $database['username'] = Input::get('database_username');
        $database['password'] = Input::get('database_password');

        $mail['driver'] = Input::get('mail_driver');
        $mail['port'] = Input::get('mail_port');
        $mail['username'] = Input::get('mail_username');
        $mail['password'] = Input::get('mail_password');
        $mail['encryption'] = Input::get('mail_encryption');
        $mail['from_address'] = Input::get('mail_from_address');
        $mail['from_name'] = Input::get('mail_from_name');
        $mail['host'] = Input::get('mail_host');

        $app_url = Input::get('app_url');
        $app_key = str_random(16);


        if (Input::get('test') === 'db') {

            $is_db_valid = self::testDatabase($database);

            if ($is_db_valid === 'yes') {
                return Response::json([
                    'status' => 'success',
                    'message' => 'Success, Your connection works!',
                    'test' => 1
                ]);
            }

            return Response::json([
                'status' => 'error',
                'message' => 'Unable to connect! Please check your settings',
                'test' => 1
            ]);
        }


        $config = "APP_ENV=production\n" .
            "APP_DEBUG=false\n" .
            "APP_URL={$app_url}\n" .
            "APP_KEY={$app_key}\n\n" .
            "DB_TYPE=mysql\n" .
            "DB_HOST={$database['host']}\n" .
            "DB_DATABASE={$database['name']}\n" .
            "DB_USERNAME={$database['username']}\n" .
            "DB_PASSWORD={$database['password']}\n\n" .
            "MAIL_DRIVER={$mail['driver']}\n" .
            "MAIL_PORT={$mail['port']}\n" .
            "MAIL_ENCRYPTION={$mail['encryption']}\n" .
            "MAIL_HOST={$mail['host']}\n" .
            "MAIL_USERNAME={$mail['username']}\n" .
            "MAIL_FROM_NAME={$mail['from_name']}\n" .
            "MAIL_FROM_ADDRESS={$mail['from_address']}\n" .
            "MAIL_PASSWORD={$mail['password']}\n\n";

        $fp = fopen(base_path()."/.env", 'w');
        fwrite($fp, $config);
        fclose($fp);

        Artisan::call('migrate', array('--force' => true));
        if (Timezone::count() == 0) {
            Artisan::call('db:seed', array('--force' => true));
        }
        Artisan::call('optimize', array('--force' => true));

        $fp = fopen(base_path()."/installed", 'w');
        fwrite($fp, '0.1.0');
        fclose($fp);

        return Redirect::route('showSignup',['first_run' => 'yup']);
    }


    private function testDatabase($database)
    {
        Config::set('database.default', $database['type']);
        Config::set("database.connections.mysql.host", $database['host']);
        Config::set("database.connections.mysql.database", $database['name']);
        Config::set("database.connections.mysql.username", $database['username']);
        Config::set("database.connections.mysql.password", $database['password']);

        try {
            DB::reconnect();
            $success = DB::connection()->getDatabaseName() ? 'yes' : 'no';
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return $success;
    }


}