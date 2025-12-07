<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// LOGIN / LOGOUT (sem filtro)
$routes->match(['get', 'post'], 'login', 'AuthController::login');
$routes->get('logout', 'AuthController::logout');

// ROTA PRINCIPAL / (com filtro)
$routes->get('/', 'AccessController::dashboard', ['filter' => 'authMachine']);

// APIs protegidas
$routes->group('api', ['filter' => 'authMachine'], static function (RouteCollection $routes) {
    $routes->get('hosts', 'AccessController::apiHostsStatus');
    $routes->post('block', 'AccessController::apiBlock');
    $routes->post('unblock', 'AccessController::apiUnblock');
    $routes->post('block-room', 'AccessController::apiBlockRoom');

    $routes->post('discover-room', 'AccessController::apiDiscoverRoom');
    $routes->post('refresh-hosts', 'AccessController::apiRefreshHosts');
});

// Agendamentos protegidos
$routes->group('', ['filter' => 'authMachine'], static function (RouteCollection $routes) {
    $routes->get('schedules', 'SchedulesController::index');
    $routes->match(['get', 'post'], 'schedules/new', 'SchedulesController::new');

    $routes->get('logs', 'AccessController::logs');
});
