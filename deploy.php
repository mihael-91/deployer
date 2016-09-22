<?php

/**
 * DEFAULTS
 * !!! This options are set for deployment on dev server !!!
 * !!! Enable login to server as deploy user via your ssh key !!!
 *
 * All defaults can be overwritten by passing arguments like --key=value
 *
 * example:
 * $ dep deploy server host=host.com
 *
 * NOTE
 * use -vvv when calling dep to print server output
 *
 * BASIC COMMANDS:
 *
 * // deploy with default options
 * $ dep deploy -vvv
 *
 * // deploy with default setup and create databases, run migrations and fixtures
 * $ dep deploy --tasks=init
 *
 * // deploy with release setup
 * $ dep deploy --tasks=release
 *
 */

$options = [

  'host' => 'azzryel.com',

  'repository' => 'git@github.com:mihael-91/deployer.git',
  'branch' => 'master',

  'user' => 'root',
  'deploy_path' => '/var/www/hram.azzryel.com',

  'instance' => '',

  'tasks' => '', // comma delimited task names in order which will be executed

];

$argvRegex = '/^--(' . implode('|', array_keys($options)) . ')=(.*)$/';
foreach ($argv as $key => $val) {
  if (preg_match($argvRegex, $val, $matches) === 1) {
    /**
     * Since deployer has no way to pass args to deploy script (only closures have access to them via input() function)
     * this will enable them and prevent warnings symfony console errors
     *
     * So to overwrite defaults use --$key=$value example: --server=production.com --branch=prod
     */
    // \Symfony\Component\Console\Input\InputArgument::OPTIONAL === 2
    option($matches[1], null, \Symfony\Component\Console\Input\InputArgument::OPTIONAL, '');
    $val = trim($matches[2]);
    if ($val !== '') {
      $options[$matches[1]] = $val;
    }
  }
}

require 'recipe/common.php';

// Set configurations
set('repository', $options['repository']);
set('shared_files', ['config/conf.php']);
set('shared_dirs', []);
set('writable_dirs', []);

// Configure servers
server('server', $options['host'])
  ->user($options['user'])
  ->identityFile()
  ->env('deploy_path', $options['deploy_path'])
  ->env('branch', $options['branch']);

function goToProjectRoot ($options) { run('cd ' . $options['deploy_path'] . '/current'); }
function runMigrations ($options) { run('php ' . $options['deploy_path'] . '/current/bin/migrations.php migrate'); }
function runFixtures ($options) { run('php ' . $options['deploy_path'] . '/current/bin/fixtures.php'); }
function createDatabases ($options) { run('php ' . $options['deploy_path'] . '/current/bin/createDatabases.php'); }

/**
 * init task should be executed during first deploy on server
 */
task('init', function () use ($options) {
  goToProjectRoot($options);
  createDatabases($options);
  runMigrations($options);
  runFixtures($options);
});

/**
 * Migration task updates all databases
 */
task('migrations', function () use ($options) {
  goToProjectRoot($options);
  runMigrations($options);
})->desc('Migrations task');

/**
 * New instance task should be run after adding new instance
 */
task('new_instance', function () use ($options) {
  goToProjectRoot($options);
  createDatabases($options);
  runMigrations($options);
  if ($options['instance'] !== '') {
    run('php ' . $options['deploy_path'] . '/current/bin/fixtures.php instance=' . $options['instance']);
  }
});

/**
 * Release task
 */
task('release', function () use ($options) {
  goToProjectRoot($options);
  createDatabases($options);
  runMigrations($options);
  // todo: uncomment after permissions task is updated
  //run('php ' . $options['deploy_path'] . '/current/apps/cli.php permissions main');
  run('php ' . $options['deploy_path'] . '/current/bin/cache.php command=proxy');
  run('php ' . $options['deploy_path'] . '/current/bin/cache.php command=metadata');
  run('php ' . $options['deploy_path'] . '/current/bin/cache.php command=query');
})->desc('Migrations task');

/**
 * Main task list which is executed on every deploy
 */
$deployOptions = [
  'deploy:prepare',
  'deploy:release',
  'deploy:update_code',
  'deploy:shared',
  'deploy:writable',
  'deploy:symlink',
  'cleanup',
];

$tasks = array_filter(explode(',', $options['tasks']), function ($val) { return $val !== ''; });

if (count($tasks) > 0) {
  foreach ($tasks as $task) {
    $deployOptions[] = $task;
  }
}

if (in_array('new_instance', $tasks) && $options['instance'] == '') {
  throw new \Exception('Task new_instance requires instance option to be set');
}

task('deploy', $deployOptions)->desc('Deploy');

after('deploy', 'success');
