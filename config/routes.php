<?php

declare(strict_types=1);

use App\Http\Controller\FilmController;
use App\Http\Controller\FilmsController;
use App\Http\Controller\MemberController;
use App\Http\Controller\OverviewController;
use App\Http\Controller\RoundController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

$routes->add('overview', new Route('/api/overview', ['_controller' => OverviewController::class]));
$routes->add('films', new Route('/api/films', ['_controller' => FilmsController::class]));
$routes->add('film', new Route('/api/films/{slug}', ['_controller' => FilmController::class]));
$routes->add('members', new Route('/api/members', ['_controller' => MemberController::class]));
$routes->add('rounds', new Route('/api/rounds', ['_controller' => RoundController::class]));

return $routes;
