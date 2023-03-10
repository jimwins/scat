<?php
# this is a very blunt hammer.

require 'vendor/autoload.php';

$debug= true;

$_ENV['PHINX_HOST_NAME']= $_ENV['MYSQL_HOST'] ?? 'db';
$_ENV['PHINX_DATABASE']= $_ENV['MYSQL_DATABASE'] ?? 'scat';
$_ENV['PHINX_USER']= 'root'; //getenv('MYSQL_USER');
$_ENV['PHINX_PASSWORD']= getenv('MYSQL_ROOT_PASSWORD');

$app= new \Phinx\Console\PhinxApplication();
$wrap= new \Phinx\Wrapper\TextWrapper($app);

$routes= [
  'status' => 'getStatus',
  'migrate' => 'getMigrate',
  'rollback' => 'getRollback',
];

$command= @$_REQUEST['command'];

if (!in_array($command, [ 'status', 'migrate', 'rollback' ])) {
  $command= 'status';
}

$env= '';
$target= '';

$output= call_user_func([$wrap, $routes[$command]], $env, $target);
$error= $wrap->getExitCode() > 0;

header('Content-Type: text/html', true, $error ? 500 : 200);
?>
<p>
  <a href="setup.php?command=migrate">setup / migrate</a>
  <a href="setup.php?command=init">init config</a>
</p>
<?php
echo '<pre>';
if ($debug) {
  // Show what command was executed based on request parameters.
  $args = implode(', ', [var_export($env, true), var_export($target, true)]);
  echo "DEBUG: $command($args)" . PHP_EOL . PHP_EOL;
}

echo $output;
