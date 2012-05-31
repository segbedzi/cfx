#!/usr/bin/env php
<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

require "lib/Db.php";

echo <<< WELCOME

Welcome to the WYF Framework
============================
This setup guide would help you get up and running with the WYF framework
as fast as possible.

WELCOME;

$name = get_response(
    "What is the name of your application", 
    null, null, true
);

$home = get_response("Where is your application residing", getcwd(), null, null, true) . "/";
$prefix = get_response("What is the prefix of your application (Enter 'no prefix' if you do not want a prefix)", basename($home));
$db = get_db_credentials();

do
{
    try{
        echo "\nTesting your database connection ... ";
        @Db::get($db);
        $failed = false;
    }
    catch(Exception $e)
    {
        //fputs(STDERR, "Failed\n Could not establish a connection to the database.");
        $response = get_response(
            "Failed\nCould not establish a connection to the database. Would you like to provide new credentials", 
            'yes', array('yes','no'), true
        );
        
        if($response == 'yes')
        {
            echo "Getting new credentials ...\n";
            $db = get_db_credentials();        
            $failed = true;
        }
        else
        {
            exit();
        }
    }
} while($failed);

echo("OK");

echo "\nSetting up the configuration files ...\n";

mkdir2($home . 'app');
mkdir2($home . 'app/cache');
mkdir2($home . 'app/cache/code');
mkdir2($home . 'app/cache/menus');
mkdir2($home . 'app/cache/template_compile');
mkdir2($home . 'app/logs');
mkdir2($home . 'app/modules');
mkdir2($home . 'app/modules/system');
mkdir2($home . 'app/temp');
mkdir2($home . 'app/themes');
mkdir2($home . 'app/uploads');

copy_dir("lib/setup/factory/*", "$home/app");
copy("lib/setup/htaccess", ".htaccess");
create_file(
    "$home/app/cache/menus/side_menu_1.html",
    str_replace(
        '{$prefix}', 
        "/$prefix", 
        file_get_contents("lib/setup/factory/cache/menus/side_menu_1.html")
    )
);

$system = <<< SYSTEM
<?php
\$redirect_path = "lib/modules/system";
\$package_name = "System";
\$package_path = "system";
\$package_schema = "system";
SYSTEM;
create_file($home . 'app/modules/system/package_redirect.php', $system);

$index = <<< "INDEX"
<?php
/**
 * Generated by WYF setup script.
 * 
 */
require "lib/entry.php";
INDEX;

create_file($home . 'index.php', $index);

$config = <<< "CONFIG"
<?php
error_reporting(E_ALL ^ E_NOTICE);

\$selected = "main";

\$config = array(
    'home' => "$home",
    'prefix' => "/$prefix",
    'name' => "$name",
    'db' => array(
    	"main" => array(
            'driver' => 'postgresql',
            'user' => '{$db['user']}',
            'host' => '{$db['host']}',
            'password' => '{$db['password']}',
            'name' => '{$db['name']}',
            'port' => '{$db['port']}'    	
    	)
    ),
    'cache' => array(
        'method' => 'file',
        'models' => true
    ),
    'audit_trails' => false,
    'theme' => 'default'
);
CONFIG;

create_file($home . 'app/config.php', $config);
create_file($home . 'app/includes.php', "<?php\n");
create_file($home . 'app/bootstrap.php', "<?php\n");

// Try to initialize the wyf framework.
require "lib/wyf_bootstrap.php";

echo "\nSetting up the database ...\n";

Db::query(file_get_contents("lib/setup/schema.sql"));
$username = get_response("Enter a name for the superuser account", 'super', null, true);
$email = get_response('Provide your email address', null, null, true);
Db::query("INSERT INTO system.roles(role_id, role_name) VALUES(1, 'Super User')");
Db::query(
    sprintf(
    	"INSERT INTO system.users
    		(user_name, password, role_id, first_name, last_name, user_status, email) 
    	VALUES
    	 	('%s', '%s', 1, 'Super', 'User', 2, '%s')", 
        Db::escape($username),
        Db::escape($username),
        Db::escape($email)
    )
);

echo "\nDone! Happy programming ;)\n\n";

/**
 * A utility function for creating files. Checks if the files are writable and
 * goes ahead to create them. If they are not it just dies!
 */
function create_file($file, $contents)
{
    if(is_writable(dirname($file)))
    {
        file_put_contents($file, $contents);
        return true;
    }
    else
    {
        fputs(
            STDERR,
            "Error writing to file $file. Please ensure you have the correct permissions"
        );
        return false;
    }
}

/**
 * A function for getting answers to questions from users interractively.
 * @param $question The question you want to ask
 * @param $answers An array of possible answers that this function should validate
 * @param $default The default answer this function should assume for the user.
 * @param $notNull Is the answer required
 */
function get_response($question, $default=null, $answers=null, $notNull = false)
{
    echo $question;
    if(is_array($answers))
    {
        if(count($answers) > 0) echo " (" . implode("/", $answers) . ")";
    }
    
    echo " [$default]: ";
    $response = str_replace(array("\n", "\r"),array("",""),fgets(STDIN));

    if($response == "" && $notNull === true && $default == '')
    {
        echo "A value is required.\n";
        return get_response($question, $answers, $default, $notNull);
    }
    else if($response == "" && $notNull === true && $default != '')
    {
        return $default;
    }
    else if($response == "")
    {
        return $default;
    }
    else
    {
        if(count($answers) == 0)
        {
            return $response;
        }
        foreach($answers as $answer)
        {
            if(strtolower($answer) == strtolower($response))
            {
                return strtolower($answer);
            }
        }
        echo "Please provide a valid answer.\n";
        return get_response($question, $answers, $default, $notNull);
    }
}

function mkdir2($path)
{
    echo("Creating directory $path\n");
    if(!\is_writable(dirname($path)))
    {
        fputs(STDERR, "You do not have permissions to create the $path directory\n");
        die();
    }
    else if(\is_dir($path))
    {
        echo ("Directory $path already exists. I will skip creating it ...\n");
    }
    else
    {
        mkdir($path);
    }
    return $path;
}

function copy_dir($source, $destination)
{
    foreach(glob($source) as $file)
    {
        $newFile = (is_dir($destination) ?  "$destination/" : ''). basename("$file");
        
        if(is_dir($file))
        {
            mkdir2($newFile);
            copy_dir("$file/*", $newFile);
        }
        else
        {
            copy($file, $newFile);
        }
    }
}

function get_db_credentials()
{
    $db = array();
    $db['host'] =     get_response("Where is your application's database hosted", 'localhost', null, true);
    $db['port'] =     get_response("What is the port of this database", '5432', null, true);
    $db['user'] = get_response("What is the database username", null, null, true);
    $db['password'] = get_response("What is the password for the database");
    $db['name'] = get_response("What is the name of your application's database (please ensure that the database exists)", null, null, true);
    
    return $db;
}


