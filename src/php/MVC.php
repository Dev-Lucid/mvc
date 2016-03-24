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
        }
        $result = include($fileName);
        foreach ($arrayParameters as $key=>$val) {
            unset($GLOBALS[$key]);
        }

        # if the view did NOT return content, but instead defined a view class, then
        # call its __construct method with the parameters bound, and then its render method
        if (class_exists($class) === true) {
            $boundParameters = $this->buildParameters($class, '__construct', $parameters);

            $view = new $class(...$boundParameters);
            if (in_array('Lucid\\Component\\MVC\\ViewInterface', class_implements($view)) === false) {
                throw new \Exception('Could not use view '.$name.'. For compatibility, a view class must implement Lucid\\Component\\MVC\\ViewInterface.');
            }
            return $view->render();
        } else {
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
}
