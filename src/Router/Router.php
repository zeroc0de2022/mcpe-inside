<?php 
/** 
 * Description of Router
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 */

    namespace Api\Router;

    use Api\Common\Common;
    use Api\Controllers\NotfoundController;
    use Api\Common\Helper;

    class Router {
    
    private array $routes;
    private array $parameters;
    
    public function __construct(){           
        $routesPath = ROOT."/config/routes.php";
        $this->routes = include($routesPath);
        # проверяем соответствие ключа
        Common::apiAuth();
    }
    /**
     * Returns request method
     */
    private function getUri(): false|string
    {
        if(!empty($_SERVER['REQUEST_URI'])){
            # Очищаем от GET параметров
            $_SERVER['REQUEST_URI'] = preg_replace('~\?(.*)~', '', $_SERVER['REQUEST_URI']);
            return trim($_SERVER['REQUEST_URI'], '/');
        }
        return false;
    }
    /**
     * Готовим поисковый запрос для передачи контроллеру
     */
    private function prepareGetQuery(): void
    {
        if(isset($_GET) && count($_GET) > 0){
           $this->parameters = $_GET;
        }
        else {
            Helper::printPre(json_encode(['error' => true, 'message' => 'Необходимо наличие параметра'], JSON_UNESCAPED_UNICODE), true);
        }
    }
    
    
    
    
    public function run(): void
    {
        // получить строку запроса
        $uri = $this->getUri();
        // проверить наличие такого запроса в routes.php
        foreach($this->routes as $uriPattern => $path) {
            // сравниваем $uriPattern и $uri
            // если есть совпадение, 
            if(preg_match("~^$uriPattern$~i", $uri)){
                // Получаем внутренний путь из внешнего согласно правилу
                $internalRoute = preg_replace("~^$uriPattern$~", $path, $uri);
                // Опеределить action, controller, параметр
                $segments = explode('/', $internalRoute);
                $controllerName = ucfirst(array_shift($segments)."Controller"); 
                $actionName = "action".ucfirst(array_shift($segments));
                $this->prepareGetQuery();
                
                /*
                echo "controllerName - {$controllerName}\n";
                echo "actionName - {$actionName}\n";
                Helper::printPre($this->parameters, true);
                */
                
                # создать объект, вызвать метод (т.е. action) 
                $controllerObject = new $controllerName;
                // Если существует контроллер соответствущий роуту, вызывает контроллер, в противном случае вызов Notfound
                
                $result = (method_exists($controllerName, $actionName)) ?
                    call_user_func_array(array($controllerObject, $actionName), array($this->parameters)) : 
                    NotfoundController::actionNotfound();
                # call_user_func_array вызывает action $actionName у объекта $controllerObject
                #в случае отсутствия контроллера, вызываем NotFound
                #с параметрами $parameters
               
                if($result != null){
                    break;
                }
            }
        }     
        
    }
    
   
} 
