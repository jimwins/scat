<?php
# this is a very blunt hammer.

require '../vendor/autoload.php';

$debug= true;

$_ENV['PHINX_HOST_NAME']= $_ENV['MYSQL_HOST'] ?? 'db';
$_ENV['PHINX_DATABASE']= $_ENV['MYSQL_DATABASE'] ?? 'scat';
$_ENV['PHINX_USER']= 'root'; //getenv('MYSQL_USER');
$_ENV['PHINX_PASSWORD']= getenv('MYSQL_ROOT_PASSWORD');

$app= new \Phinx\Console\PhinxApplication();
$wrap= new \Phinx\Wrapper\TextWrapper($app);
$wrap->setOption('configuration', '../phinx.yml');

$routes= [
  'status' => 'getStatus',
  'migrate' => 'getMigrate',
  'seed' => 'getSeed',
  'rollback' => 'getRollback',
];

$command= @$_REQUEST['command'];

if (!in_array($command, [ 'status', 'migrate', 'seed', 'rollback' ])) {
  $command= 'status';
}

$env= '';
$target= '';

$output= call_user_func([$wrap, $routes[$command]], $env, $target);
$error= $wrap->getExitCode() > 0;

header('Content-Type: text/html', true, $error ? 500 : 200);
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Scat Setup</title>
    <link rel="stylesheet" href="/static/css/web.css">
  </head>
  <body>
    <h1 class="page-header">
      Scat Setup
    </h1>
    <p class="lead">
      Scat is a web-based Point-of-Sale (POS) system.
    </p>
    <p>
      This is a very blunt-edged tool to set up the database (using Phinx) and
      populate the database with test data. If you find yourself here, it
      probably means that Scat was unable to connect to its database or find
      its configuration data.
    </p>
    <p>
      <a class="button" href="setup.php?command=migrate">
        <span class="label">Set up the database</span>
      </a>
      <a class="button" href="setup.php?command=seed">
        <span class="label">Load some sample data</span>
      </a>
      <?php if ($command != 'status') {?>
        <a href="/" class="button">Return to Scat</a>
      <?php }?>
    </p>
    <h2>Phinx Output</h2>
    <hr>
    <pre>
      <?php
        echo '<pre>';
        if ($debug) {
          // Show what command was executed based on request parameters.
          $args = implode(', ', [var_export($env, true), var_export($target, true)]);
          echo "DEBUG: $command($args)" . PHP_EOL . PHP_EOL;
        }

        echo $output;
      ?>
    </pre>
  </body>
</html>
