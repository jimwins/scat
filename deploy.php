<?php
namespace Deployer;

require 'recipe/composer.php';

require 'contrib/phinx.php';
require 'contrib/sentry.php';

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
import('hosts.yml');

// Tasks

after('deploy:cleanup', 'phinx:migrate');

if ($_ENV['SENTRY_TOKEN']) {
  after('deploy:success', 'deploy:sentry');
}

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
