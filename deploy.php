<?php
namespace Deployer;

require 'recipe/common.php';
require 'vendor/deployer/recipes/recipe/phinx.php';
require 'vendor/deployer/recipes/recipe/sentry.php';

// Project name
set('application', 'scat');

// Project repository
set('repository', 'https://github.com/jimwins/scat.git');

// Shared files/dirs between deploys 
set('shared_files', []);
set('shared_dirs', []);

if ($_ENV['SENTRY_TOKEN']) {
  set('sentry', [
    'organization' => $_ENV['SENTRY_ORG'],
    'projects' => [ 'scat-web' ],
    'token' => $_ENV['SENTRY_TOKEN'],
  ]);
}

// Hosts
inventory('hosts.yml');

// Tasks

desc('Deploy your project');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);

after('cleanup', 'phinx:migrate');

if ($_ENV['SENTRY_TOKEN']) {
  after('deploy', 'sentry');
}

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
