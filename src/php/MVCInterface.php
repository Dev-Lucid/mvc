<?php
namespace Lucid\Component\MVC;

interface MVCInterface
{
    public function setPath($type, $path);

    public function findModel(string $name);
    public function loadModel(string $name);
    public function model(string $name, $id=null);

    public function findView(string $name);
    public function loadView(string $name);
    public function view(string $name, $parameters=[]);

    public function findController(string $name);
    public function loadController(string $name);
    public function controller(string $name);
}
