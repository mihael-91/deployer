<?php
/*
 * This file has been generated automatically.
 * Please change the configuration for correct use deploy.
 */

require 'recipe/common.php';

// Set configurations
set('repository', 'git@github.com:mihael-91/deployer.git');
set('shared_files', []);
set('shared_dirs', []);
set('writable_dirs', []);

// Configure servers
server('production', 'azzryel.com')
    ->user('root')
    ->identityFile()
    ->env('deploy_path', '/var/www/hram.azzryel.com');

server('dev', 'azzryel.com')
  ->user('root')
  ->identityFile()
  ->env('deploy_path', '/var/www/dev.hram.azzryel.com')
  -env('branch', 'test');

/**
 * Restart php-fpm on success deploy.
 */
task('php-fpm:restart', function () {
    // Attention: The user must have rights for restart service
    // Attention: the command "sudo /bin/systemctl restart php-fpm.service" used only on CentOS system
    // /etc/sudoers: username ALL=NOPASSWD:/bin/systemctl restart php-fpm.service
    //run('sudo /bin/systemctl restart php-fpm.service');
})->desc('Restart PHP-FPM service');

after('success', 'php-fpm:restart');

/**
 * Main task
 */
task('deploy', [
    'deploy:prepare',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:symlink',
    'cleanup',
])->desc('Deploy your project');

after('deploy', 'success');
