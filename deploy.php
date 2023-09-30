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

// Hosts
import('hosts.yml');

// Tasks

after('deploy:cleanup', 'phinx:migrate');

if ($_ENV['SENTRY_TOKEN']) {
  after('deploy:success', 'deploy:sentry');
}

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
