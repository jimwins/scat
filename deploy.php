<?php
namespace Deployer;

require 'recipe/common.php';

// Project name
set('application', 'scat');

// Project repository
set('repository', 'https://github.com/jimwins/scat.git');

// Shared files/dirs between deploys 
set('shared_files', []);
set('shared_dirs', []);

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

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
