<?php
namespace Lucid\Components\MVC;

interface ModelInterface
{
    public function hasPermissionSelect(array $data);
    public function hasPermissionInsert(array $data);
    public function hasPermissionUpdate(array $data);
    public function hasPermissionDelete(array $data);
}
