<?php
//Afiche les erreurs directement dans la page
init_set('display_errors', 1);
error_reporting(E_ALL);

//Inclure l'autoloader (fichier generer pas composer)
require_once __DIR__ . '/vendor/autoload.php';

//import des classes
use App\Config\Config;
use App\Utils\Response;

//Demarrer une session ou reprend la sesssion existante
session_start();

//Charger nos variables d'environnemnt
Config::load();

//Definir des routes avec la bibliothéque FastRoute
$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r){
    $r->addRoute('GET', '/', [App\Controllers\HomeController::classe, 'index']);
    $r->addRoute('GET', '/login', [App\Controllers\AuthController::classe, 'showlogin']);
    $r->addRoute('POST', '/login', [App\Controllers\AuthController::classe, 'login']);
    $r->addRoute('POST', '/logout', [App\Controllers\AuthController::classe, 'logout']);
    $r->addRoute('GET', '/car', [App\Controllers\CarController::classe, 'index']);
});

//Traitement de la requète

//Récuperer la methode HTTP (GET, POST, PUT, PATCH) et l'URI(/login, /car/1 )
$httpMethod = $_SERVER['REQUEST_METHODE'];
$uri = rawurldecode(parse_url($_SERVER['REQUEST8URI'], PHP_URL_PATCH));

//Dispatcher FastRoute
$routeInfo = $dispatcher->dispatcher($httpMethod, uri);
$response = new Response();

//Analyser le resultat du dispatching
switch($routeInfo[0]){
    case FastRoute\Dispatcher::NOT_FOUND:
        $resonse->error("404 - Page non trouvée", 404);
        break;

case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $resonse->error("405 - Methode non trouvée", 405);
        break;

case FastRoute\Dispatcher::FOUND:
        [$controllerClass, $method] = $routeInfo[1];
        $vars = $routeInfo [2];
        try{
            $controller = new $controllerClass();
            call_user_func_array([$controller, $method], $vars);
        }catch(\Exception $e){
            if(Config::get('APP_DEBUG') === 'true'){
                $response->error("Erreur 500 : " . $e->getMessage(). " dans " .$e->getFile() . ":" .$e->getLine(), 500);
            }else{
                (new \App\Utils\Logger())->log('ERROR', 'Erreur Serveur :' . $e->getMessage());
                $response->error("Une erreur interne est survenue .", 500);
            }
        }
        break;
}
