<?php
namespace Lucid\Component\MVC;
use Lucid\Lucid;

class MVC implements MVCInterface
{
    public $paths = [
        'model'=>null,
        'view'=>null,
        'controller'=>null,
    ];

    public $classPrefixes = [
        'model'      =>'\\App\\Model\\',
        'view'       =>'\\App\\View\\',
        'controller' =>'\\App\\Controller\\',
    ];

    public $classSuffixes = [
        'model'      =>'',
        'view'       =>'',
        'controller' =>'',
    ];

    public function __construct()
    {
        \Model::$auto_prefix_models = '\\App\\Model\\';
    }

    public function setPath($type, $path)
    {
        $this->paths[$type] = realpath($path);
        return $this;
    }

    protected function cleanFileName(string $name): string
    {
        $name = preg_replace('/[^a-z0-9_\-\/]+/i', '', $name);
        return $name;
    }

    protected function findFile(string $name, string $type)
    {
        $path = $this->paths[$type];
        $name = $this->cleanFileName($name);
        $fileName = $path . '/' . $name . '.php';
        if (file_exists($fileName) === true) {
            return $fileName;
        }

        throw new \Exception('MVC loader failure, type='.$type.', name='.$name.', path='.$path.'. Check for typos.');
    }

    public function findModel(string $name)
    {
        return $this->findFile($name, 'model');
    }

    public function loadModel(string $name): string
    {
        $class = $this->classPrefixes['model'] . $name . $this->classSuffixes['model'];

        if (class_exists($class) === false) {
            $fileName = $this->findModel($name);
            include($fileName);
        }

        if (class_exists($class) === false) {
            throw new \Exception('MVC model instantiation failure. File '.$fileName.' must contain a class named '.$class.' that inherits from Lucid\\Component\\MVC\\Model.');
        }
        if (in_array('Lucid\\Component\\MVC\\ModelInterface', class_implements($class)) === false) {
            throw new \Exception('Could not use model '.$name.'. For compatibility, a model class must implement Lucid\\Component\\MVC\\ModelInterface.');
        }

        return $class;
    }

    public function model(string $name, $id=null)
    {
        $class = $this->loadModel($name);

        if (is_null($id) === true) {
            return \Model::factory($name);
        } else {
            if ($id == 0) {
                return \Model::factory($name)->create();
            } else {
                return \Model::factory($name)->find_one($id);
            }
        }
    }

    public function findView(string $name)
    {
        return $this->findFile($name, 'view');
    }

    public function loadView(string $name)
    {
        return include($this->findView($name));
    }

    public function view(string $name, $parameters=[])
    {
        $class = $this->classPrefixes['view'] . $name . $this->classSuffixes['view'];
        $fileName = $this->findView($name);
        /*
        if (is_object($parameters) === true) {
            $arrayParameters = $parameters->get_array();
        } elseif(is_array($parameters)) {
            $arrayParameters = $parameters;
        } else {
            $arrayParameters = [];
        }


        foreach ($arrayParameters as $key=>$val) {
            global $$key;
            $$key = $val;
        }*/
        $result = include($fileName);
        /*
        foreach ($arrayParameters as $key=>$val) {
            unset($GLOBALS[$key]);
        }
        */

        # if the view did NOT return content, but instead defined a view class, then
        # call its __construct method with the parameters bound, and then its render method
        if (class_exists($class) === true) {
            $view = new $class();
            $boundParameters = $this->buildParameters($view, 'render', $parameters);

            if (in_array('Lucid\\Component\\MVC\\ViewInterface', class_implements($view)) === false) {
                throw new \Exception('Could not use view '.$name.'. For compatibility, a view class must implement Lucid\\Component\\MVC\\ViewInterface.');
            }
            return $view->render(...$boundParameters);
        } else {
            lucid::logger()->debug('class does NOT exist');
            return $result;
        }
    }

    public function findController(string $name)
    {
        return $this->findFile($name, 'controller');
    }

    public function loadController(string $name): string
    {
        $class = $this->classPrefixes['controller'] . $name . $this->classSuffixes['controller'];
        if (class_exists($class) === false) {
            $fileName = $this->findController($name);
            include($fileName);
        }
        if (class_exists($class) === false) {
            throw new \Exception('MVC controller instantiation failure. File '.$fileName.' must contain a class named '.$class.' that inherits from Lucid\\Component\\MVC\\Controller');
        }
        if (in_array('Lucid\\Component\\MVC\\ControllerInterface', class_implements($class)) === false) {
            throw new \Exception('Could not use controller '.$name.'. For compatibility, a controller class must implement Lucid\\Component\\MVC\\ControllerInterface.');
        }

        return $class;
    }

    public function controller(string $name)
    {
        $class = $this->loadController($name);
        $object = new $class();
        return $object;
    }

    protected function buildParameters($object, string $method, $parameters=[])
    {
        $objectClass = get_class($object);

        # we need to use the Request object's methods for casting parameters
        if(is_array($parameters) === true) {
            $parameters = new \Lucid\Component\Store\Store($parameters);
        }

        if (method_exists($objectClass, $method) === false) {
            throw new \Exception($objectClass.' does not contain a method named '.$method.'. Valid methods are: '.implode(', ', get_class_methods($objectClass)));
        }

        $r = new \ReflectionMethod($objectClass, $method);
        $methodParameters = $r->getParameters();

        # construct an array of parameters in the right order using the passed parameters
        $boundParameters = [];
        foreach ($methodParameters as $methodParameter) {
            $type = strval($methodParameter->getType());
            if ($parameters->is_set($methodParameter->name)) {
                if (is_null($type) === true || $type == '' || method_exists($parameters, $type) === false) {
                    $boundParameters[] = $parameters->raw($methodParameter->name);
                } else {
                    $boundParameters[] = $parameters->$type($methodParameter->name);
                }
            } else {
                if ($methodParameter->isDefaultValueAvailable() === true) {
                    $boundParameters[] = $methodParameter->getDefaultValue();
                } else {
                    throw new \Exception('Could not find a value to set for parameter '.$methodParameter->name.' of function '.$thisClass.'->'.$method.', and no default value was set.');
                }
            }
        }
        return $boundParameters;
    }
}
